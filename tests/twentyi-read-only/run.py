"""Offline tests of the exact workflow script. No credentials or API calls."""
import base64
import contextlib
from email.message import Message
import io
from pathlib import Path
import ssl
import textwrap
import types
import unittest
from unittest.mock import patch
import urllib.error

root = Path(__file__).resolve().parents[2]
workflow = (root / '.github/workflows/twentyi-read-only.yml').read_text(encoding='utf-8')
start = "          python3 -I -B - <<'PY'\n"
end = '\n          PY\n'
if workflow.count(start) != 1 or workflow.count(end) != 1:
    raise RuntimeError('Expected exactly one inline diagnostic script')
source = textwrap.dedent(workflow.split(start, 1)[1].split(end, 1)[0])
c = types.ModuleType('twentyi_check')
exec(compile(source, 'inline-twentyi-diagnostic', 'exec'), c.__dict__)

class Response:
    status = 200
    def __init__(self, body=b'[]', content_type='application/json'):
        self.body = body
        self.headers = Message()
        self.headers['Content-Type'] = content_type
    def __enter__(self): return self
    def __exit__(self, *args): return False
    def read(self, n):
        assert n == c.MAX_BYTES + 1
        return self.body[:n]

class Tests(unittest.TestCase):
    def result(self, body=b'[]', content_type='application/json'):
        with patch.object(c.urllib.request, 'build_opener') as build:
            build.return_value.open.return_value = Response(body, content_type)
            out = c.check('synthetic-key-not-a-credential')
            args = build.call_args.args
            self.assertEqual(args[0].proxies, {})
            self.assertIsInstance(args[1], c.NoRedirect)
            self.assertTrue(args[2]._context.check_hostname)
            self.assertEqual(args[2]._context.verify_mode, ssl.CERT_REQUIRED)
            req = build.return_value.open.call_args.args[0]
            self.assertEqual(req.get_method(), 'GET')
            self.assertEqual(req.full_url, 'https://api.20i.com/package')
            self.assertIsNone(req.data)
            self.assertEqual(req.get_header('Authorization'), 'Bearer ' + base64.b64encode(b'synthetic-key-not-a-credential').decode())
            self.assertEqual(build.return_value.open.call_args.kwargs, {'timeout': 10})
            self.assertEqual(build.return_value.open.call_count, 1)
            return out
    def test_empty(self): self.assertEqual(self.result(), 'authenticated_package_read_succeeded')
    def test_populated(self): self.assertEqual(self.result(b'[{"id":123,"name":"synthetic.invalid"}]'), 'authenticated_package_read_succeeded')
    def test_charset(self): self.result(content_type='application/json; charset=utf-8')
    def test_missing(self):
        with patch.object(c.urllib.request, 'build_opener') as build:
            with self.assertRaisesRegex(c.CheckError, 'missing_secret'): c.check('')
            build.assert_not_called()
    def test_bad_keys(self):
        for key in ['key\n', ' key', 'x\x00y', 'x\u00a0y', 'x' * 16385]:
            with self.subTest(key=repr(key[:5])), patch.object(c.urllib.request, 'build_opener') as build:
                with self.assertRaisesRegex(c.CheckError, 'invalid_secret_format'): c.check(key)
                build.assert_not_called()
    def test_json_errors(self):
        for body in [b'{bad', b'\xff', b'[NaN]', b'[] garbage']:
            with self.subTest(body=body), self.assertRaisesRegex(c.CheckError, 'invalid_json'): self.result(body)
    def test_shapes(self):
        for body in [b'null', b'{}', b'[3]', b'"[]"']:
            with self.subTest(body=body), self.assertRaisesRegex(c.CheckError, 'unexpected_response_shape'): self.result(body)
    def test_content_type(self):
        with self.assertRaisesRegex(c.CheckError, 'unexpected_content_type'): self.result(content_type='text/html')
    def test_size(self):
        with self.assertRaisesRegex(c.CheckError, 'response_limit'): self.result(b'x' * (c.MAX_BYTES + 1))
    def test_http(self):
        for status, code in [(301,'redirect_refused'), (302,'redirect_refused'), (307,'redirect_refused'), (401,'http_401'), (403,'http_403'), (404,'http_404'), (429,'http_429'), (500,'provider_http_failure')]:
            with self.subTest(status=status), patch.object(c.urllib.request, 'build_opener') as build:
                build.return_value.open.side_effect = urllib.error.HTTPError(c.URL, status, 'untrusted-message', {}, io.BytesIO(b'private-data'))
                with self.assertRaisesRegex(c.CheckError, code): c.check('fixture')
                self.assertEqual(build.return_value.open.call_count, 1)
    def test_redirect_hook(self): self.assertIsNone(c.NoRedirect().redirect_request(None, None, 302, '', {}, 'https://other.invalid'))
    def test_transport(self):
        for error in [urllib.error.URLError('secret-value'), TimeoutError('secret-value')]:
            with patch.object(c.urllib.request, 'build_opener') as build:
                build.return_value.open.side_effect = error
                with self.assertRaisesRegex(c.CheckError, 'network_or_tls_failure'): c.check('fixture')
    def test_output_success_and_failure(self):
        for outcome in ['authenticated_package_read_succeeded', RuntimeError('private-user-data')]:
            output = io.StringIO()
            with patch.object(c, 'check') as check, patch.dict(c.os.environ, {'TWENTYI_API_KEY': 'synthetic-fixture-secret'}), patch.object(c.signal, 'signal'), patch.object(c.signal, 'alarm'), contextlib.redirect_stdout(output):
                if isinstance(outcome, Exception): check.side_effect = outcome
                else: check.return_value = outcome
                result = c.main()
                self.assertNotIn('TWENTYI_API_KEY', c.os.environ)
                self.assertEqual(result, 1 if isinstance(outcome, Exception) else 0)
            self.assertNotIn('private-user-data', output.getvalue())
            self.assertNotIn('synthetic-fixture-secret', output.getvalue())
    def test_deadline(self):
        with self.assertRaisesRegex(c.CheckError, 'deadline'): c.deadline(None, None)

if __name__ == '__main__':
    unittest.main(verbosity=2)
