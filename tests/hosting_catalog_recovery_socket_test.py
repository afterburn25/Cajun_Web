"""Bounded recovery preflight regression; never imports or executes the app runner.

Portable tests execute its exact AST blocks against socket/open models. Two Linux
tests use only newly owned ephemeral loopback sockets: real TIME_WAIT reuse and
active-listener refusal. They are skipped elsewhere, not credited as Linux proof.
No PHP, SQL, packages, HTTP, existing server or provider is accessed.
"""
import ast
import errno
import json
from pathlib import Path
import socket
import sys
import types
import unittest

SOURCE = Path(__file__).with_name('hosting_catalog_recovery_http.py').read_text(encoding='utf-8')
MAIN = next(n for n in ast.parse(SOURCE).body if isinstance(n, ast.FunctionDef) and n.name == 'main')
TRY = next(n for n in MAIN.body if isinstance(n, ast.Try))
START = next(i for i,n in enumerate(TRY.body) if isinstance(n, ast.Assign)
             and any(isinstance(t, ast.Name) and t.id == 'server_start_stage' for t in n.targets))
END = next(i for i,n in enumerate(TRY.body[START:], START) if isinstance(n, ast.Assign)
           and isinstance(n.value, ast.Constant) and n.value.value is None)
BLOCK = compile(ast.Module(body=TRY.body[START:END + 1], type_ignores=[]), '<exact recovery startup>', 'exec')
PROBE = compile(ast.Module(body=[TRY.body[START + 1]], type_ignores=[]), '<exact recovery socket>', 'exec')
HANDLER = compile(ast.Module(body=TRY.handlers[0].body[:-1], type_ignores=[]), '<exact recovery failure>', 'exec')


class SocketModel:
    def __init__(self, *, time_wait=False, active=False, option_error=None):
        self.time_wait = time_wait; self.active = active; self.option_error = option_error
        self.calls = []; self.closed = False; self.reuse = False
    def __enter__(self): return self
    def __exit__(self, *args): self.closed = True
    def setsockopt(self, level, option, value):
        self.calls.append(('setsockopt', level, option, value))
        if self.option_error: raise self.option_error
        assert (level, option, value) == (socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self.reuse = True
    def bind(self, address):
        self.calls.append(('bind', address))
        assert address == ('127.0.0.1', 8092)
        if self.active or (self.time_wait and not self.reuse):
            raise OSError(errno.EADDRINUSE, 'PRIVATE ERROR /private/path')


def model_namespace(model, opener=lambda *a: object()):
    return {'socket': types.SimpleNamespace(socket=lambda: model, SOL_SOCKET=socket.SOL_SOCKET,
        SO_REUSEADDR=socket.SO_REUSEADDR), 'qa': Path('/private/qa'), 'open': opener,
        'server_start_stage': None, 'checks': [{'name': 'last original check'}]}


def failure(namespace, error):
    namespace['error'] = error
    exec(HANDLER, namespace)
    return namespace['failure']


class PortablePreflight(unittest.TestCase):
    def test_closed_connection_model_requires_option_before_bind(self):
        old = SocketModel(time_wait=True)
        with self.assertRaises(OSError):
            with old as probe: probe.bind(('127.0.0.1', 8092))
        new = SocketModel(time_wait=True); ns = model_namespace(new)
        exec(BLOCK, ns)
        self.assertTrue(new.closed)
        self.assertEqual([c[0] for c in new.calls], ['setsockopt', 'bind'])
        self.assertIsNone(ns['server_start_stage'])

    def test_active_listener_model_refuses_before_open_and_closes(self):
        model = SocketModel(active=True)
        ns = model_namespace(model, lambda *a: self.fail('Must not open log after refused bind'))
        with self.assertRaises(OSError) as caught: exec(BLOCK, ns)
        self.assertTrue(model.closed)
        result = failure(ns, caught.exception)
        self.assertEqual(result['server_start'], {'stage': 'port_preflight',
            'errno': errno.EADDRINUSE if 1 <= errno.EADDRINUSE <= 4095 else None})
        self.assertNotIn('PRIVATE', json.dumps(result)); self.assertNotIn('/private/', json.dumps(result))

    def test_log_open_failure_has_distinct_stage_and_closes_socket(self):
        def denied(*args): raise OSError(errno.EACCES, 'PRIVATE PATH /private/log')
        model = SocketModel(); ns = model_namespace(model, denied)
        with self.assertRaises(OSError) as caught: exec(BLOCK, ns)
        self.assertTrue(model.closed)
        result = failure(ns, caught.exception)
        self.assertEqual(result['server_start'], {'stage': 'server_log_open', 'errno': errno.EACCES})
        self.assertNotIn('PRIVATE', json.dumps(result)); self.assertNotIn('/private/', json.dumps(result))

    def test_option_failure_is_not_swallowed(self):
        model = SocketModel(option_error=OSError(errno.ENOPROTOOPT, 'private'))
        ns = model_namespace(model)
        with self.assertRaises(OSError): exec(BLOCK, ns)
        self.assertTrue(model.closed)
        self.assertEqual(len(model.calls), 1)

    def test_errno_disclosure_is_closed(self):
        for value in (None, 0, -1, 4096, 10048, True, '98'):
            with self.subTest(value=value):
                error = OSError(); error.errno = value
                result = failure({'checks': [], 'server_start_stage': 'port_preflight'}, error)
                self.assertIsNone(result['server_start']['errno'])
        result = failure({'checks': [], 'server_start_stage': 'port_preflight'}, RuntimeError('private'))
        self.assertIsNone(result['server_start']['errno'])

    def test_unrelated_failures_keep_original_shape(self):
        result = failure({'checks': [], 'server_start_stage': None}, OSError(errno.EIO, 'private'))
        self.assertEqual(set(result), {'class', 'message', 'last_check'})


class BoundSocket:
    """Test-only remap of fixed 8092 to a fresh port owned by this test."""
    def __init__(self, port): self.port = port; self.raw = socket.socket()
    def __enter__(self): return self
    def __exit__(self, *args): self.raw.close()
    def setsockopt(self, *args): self.raw.setsockopt(*args)
    def bind(self, address):
        assert address == ('127.0.0.1', 8092)
        self.raw.bind(('127.0.0.1', self.port))


def actual_probe(port):
    exec(PROBE, {'socket': types.SimpleNamespace(socket=lambda: BoundSocket(port),
        SOL_SOCKET=socket.SOL_SOCKET, SO_REUSEADDR=socket.SO_REUSEADDR)})


@unittest.skipUnless(sys.platform.startswith('linux'), 'Linux socket semantics required; no cross-platform credit')
class LinuxPreflight(unittest.TestCase):
    def test_live_listener_refused_with_and_without_reuseaddr(self):
        for reuse in (False, True):
            with self.subTest(reuse=reuse), socket.socket() as listener:
                listener.settimeout(2)
                if reuse: listener.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
                listener.bind(('127.0.0.1', 0)); listener.listen(1)
                port = listener.getsockname()[1]
                with self.assertRaises(OSError) as caught: actual_probe(port)
                self.assertEqual(caught.exception.errno, errno.EADDRINUSE)
                with socket.create_connection(('127.0.0.1', port), timeout=2) as client:
                    accepted, _ = listener.accept()
                    with accepted:
                        client.sendall(b'owned'); self.assertEqual(accepted.recv(5), b'owned')

    def test_closed_server_time_wait_refuses_old_probe_and_allows_corrected_probe(self):
        with socket.socket() as listener:
            listener.settimeout(2); listener.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            listener.bind(('127.0.0.1', 0)); listener.listen(1); port = listener.getsockname()[1]
            with socket.create_connection(('127.0.0.1', port), timeout=2) as client:
                accepted, _ = listener.accept()
                with accepted:
                    accepted.settimeout(2); accepted.shutdown(socket.SHUT_WR)
                    self.assertEqual(client.recv(1), b'')
                    client.shutdown(socket.SHUT_WR); self.assertEqual(accepted.recv(1), b'')
        with socket.socket() as old:
            with self.assertRaises(OSError) as caught: old.bind(('127.0.0.1', port))
            self.assertEqual(caught.exception.errno, errno.EADDRINUSE)
        actual_probe(port)


if __name__ == '__main__':
    if '--require-linux' in sys.argv:
        sys.argv.remove('--require-linux')
        if not sys.platform.startswith('linux'):
            raise SystemExit('Linux regression required; refusing a skipped-platform pass')
    unittest.main()
