#!/usr/bin/env python3
"""Exercise actual release callbacks with local Chromium and real loopback failures.

The optional --source accepts retained historical release_cases.py bytes, allowing
the original callback failure to be reproduced without rewriting the current file.
Only the capture function/callback and rejection expression are compiled from that
source; the full application fixtures, database and provider code never execute.
"""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from threading import Event, Thread
import argparse
import ast
import hashlib
import importlib.metadata
import json
import socket

from playwright.sync_api import sync_playwright


def capture_from_source(source, audience):
    tree = ast.parse(source)
    run, = (node for node in tree.body if isinstance(node, ast.FunctionDef) and node.name == 'run_browser')
    target = 'failed' if audience == 'public' else 'owner_failed'
    page = 'page' if audience == 'public' else 'p'
    helper = [node for node in tree.body if isinstance(node, ast.FunctionDef) and node.name == 'retain_request_failures']
    if helper:
        helper, = helper
        bindings = [node for node in ast.walk(run) if isinstance(node, ast.Call)
                    and isinstance(node.func, ast.Name) and node.func.id == helper.name
                    and [ast.dump(arg) for arg in node.args] == [ast.dump(ast.Name(id=page, ctx=ast.Load())), ast.dump(ast.Name(id=target, ctx=ast.Load()))]]
        if len(bindings) != 1:
            raise AssertionError('Expected one actual capture binding for ' + audience)
        namespace = {}
        exec(compile(ast.Module(body=[helper], type_ignores=[]), '<actual-release-helper>', 'exec'), namespace)
        return namespace[helper.name], 'actual-helper'
    # Execute the original lambda from the retained source, not a copied mirror.
    callbacks = [node.args[1] for node in ast.walk(run) if isinstance(node, ast.Call)
                 and isinstance(node.func, ast.Attribute) and node.func.attr == 'on'
                 and isinstance(node.func.value, ast.Name) and node.func.value.id == page
                 and len(node.args) == 2 and isinstance(node.args[0], ast.Constant)
                 and node.args[0].value == 'requestfailed' and isinstance(node.args[1], ast.Lambda)]
    callback, = callbacks
    code = compile(ast.Expression(body=callback), '<actual-historical-callback>', 'eval')
    return lambda actual_page, failures: actual_page.on('requestfailed', eval(code, {target: failures})), 'actual-historical-lambda'


def unexpected_from_source(source, audience, base, failed):
    target = 'unexpected_failed' if audience == 'public' else 'owner_unexpected'
    tree = ast.parse(source)
    values = [node.value for node in ast.walk(tree) if isinstance(node, ast.Assign)
              and any(isinstance(name, ast.Name) and name.id == target for name in node.targets)]
    value, = values
    return eval(compile(ast.Expression(body=value), '<actual-release-failure-predicate>', 'eval'),
                {'BASE': base, 'failed': failed, 'owner_failed': failed})


def run_case(source, audience):
    capture, binding = capture_from_source(source, audience)
    started, release = Event(), Event()
    requests_seen = []

    class Handler(BaseHTTPRequestHandler):
        protocol_version = 'HTTP/1.1'

        def log_message(self, *unused):
            pass

        def do_GET(self):
            requests_seen.append(self.path)
            if self.path == '/slow':
                started.set()
                release.wait(10)
                try:
                    self.send_response(200)
                    self.send_header('Content-Length', '0')
                    self.end_headers()
                except (BrokenPipeError, ConnectionResetError):
                    pass
                return
            if self.path.startswith('/truncated/'):
                self.send_response(int(self.path.rsplit('/', 1)[1]))
                self.send_header('Content-Type', 'text/plain')
                self.send_header('Content-Length', '1000')
                self.end_headers()
                self.wfile.write(b'incomplete')
                self.wfile.flush()
                self.connection.shutdown(socket.SHUT_RDWR)
                self.connection.close()
                self.close_connection = True
                return
            if self.path.startswith('/status/'):
                status = int(self.path.rsplit('/', 1)[1])
                body = b'' if status == 204 else b'synthetic response'
            else:
                status, body = 200, b'<!doctype html><title>Local diagnostic fixture</title>'
            self.send_response(status)
            self.send_header('Content-Type', 'text/html')
            self.send_header('Content-Length', str(len(body)))
            self.end_headers()
            self.wfile.write(body)

    server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    server.daemon_threads = True
    Thread(target=server.serve_forever, daemon=True).start()
    base = 'http://127.0.0.1:' + str(server.server_port)
    failed, responses, console_errors, callback_errors, operation_errors, browser_requests = [], [], [], [], [], []
    try:
        with sync_playwright() as pw:
            def callback_exception(loop, context):
                callback_errors.append(str(context.get('exception', context.get('message'))))
                loop.default_exception_handler(context)
            # Observe and retain callback exceptions; do not suppress original stderr.
            pw._impl_obj._loop.set_exception_handler(callback_exception)
            browser = pw.chromium.launch(headless=True)
            browser_version = browser.version
            context = browser.new_context()
            page = context.new_page()
            page.on('request', lambda request: browser_requests.append(request.url))
            page.on('response', lambda response: responses.append({'url': response.url, 'status': response.status}))
            page.on('console', lambda message: console_errors.append({'text': message.text, 'url': message.location.get('url')}) if message.type == 'error' else None)
            # Deliver an actual failed Request after a lifecycle listener closes its
            # context. No Request method, response, network call or event is mocked.
            page.on('requestfailed', lambda request: context.close() if request.url == base + '/slow' else None)
            capture(page, failed)
            page.goto(base + '/')
            for status in (200, 404, 500):
                page.evaluate('path => fetch(path).then(r => r.text()).catch(e => String(e))', '/truncated/' + str(status))
            for status in (404, 500, 204):
                page.evaluate('path => fetch(path).then(r => r.text()).catch(e => String(e))', '/status/' + str(status))
            page.evaluate("() => { window.slowAbort = new AbortController(); fetch('/slow', {signal: slowAbort.signal}).catch(() => {}); }")
            if not started.wait(5):
                raise AssertionError('Slow request did not reach the local fixture')
            page.evaluate('() => { setTimeout(() => slowAbort.abort(), 100); }')
            drain = browser.new_context()
            try:
                drain.new_page().wait_for_timeout(500)
            except Exception as exc:
                operation_errors.append(type(exc).__name__ + ': ' + str(exc))
            drain.close()
            browser.close()
    finally:
        release.set()
        server.shutdown()
        server.server_close()
    unexpected = unexpected_from_source(source, audience, base, failed)
    checks = []
    def check(name, value):
        checks.append({'name': audience + ': ' + name, 'passed': bool(value)})
    for status in (200, 404, 500):
        check('real truncated HTTP ' + str(status) + ' failure retains its observed status',
              any(item['url'] == base + '/truncated/' + str(status) and item['status'] == status and item['failure'] for item in failed))
    for status in (404, 500):
        check('ordinary HTTP ' + str(status) + ' console error remains visible',
              any(item['url'] == base + '/status/' + str(status) and str(status) in item['text'] for item in console_errors))
    check('completed HTTP 204 is observed without an invented failed request',
          any(item['url'] == base + '/status/204' and item['status'] == 204 for item in responses)
          and not any(item['url'] == base + '/status/204' for item in failed))
    check('closure failure is retained without an invented HTTP status',
          any(item['url'] == base + '/slow' and item['status'] is None and item['failure'] for item in failed))
    check('all four actual failed requests are retained exactly once',
          len(failed) == len({item['url'] for item in failed}) == 4)
    check('actual rejection predicate keeps every observed genuine failure', unexpected == failed)
    check('callback delivery and browser operations raise no exception', not callback_errors and not operation_errors)
    check('all requests stay within the local fixture', all(url.startswith(base + '/') for url in browser_requests))
    return {'audience': audience, 'capture_binding': binding, 'chromium_version': browser_version,
            'checks': checks, 'passed': all(item['passed'] for item in checks), 'failed_requests': failed,
            'unexpected_failed_requests': unexpected, 'responses': responses, 'console_errors': console_errors,
            'callback_errors': callback_errors, 'operation_errors': operation_errors, 'server_paths': requests_seen}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', type=Path, default=Path(__file__).with_name('release_cases.py'))
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    source_bytes = args.source.read_bytes()
    source = source_bytes.decode('utf-8')
    report = {'scope': 'Actual source-bound release callbacks and failure predicates; real ephemeral loopback HTTP and Chromium. No application/database/provider or full-release acceptance.',
              'source_sha256': hashlib.sha256(source_bytes).hexdigest(),
              'playwright_version': importlib.metadata.version('playwright'), 'cases': [], 'checks': [], 'passed': False}
    try:
        report['cases'] = [run_case(source, audience) for audience in ('public', 'owner')]
        report['checks'] = [check for case in report['cases'] for check in case['checks']]
        report['passed'] = all(case['passed'] for case in report['cases'])
    except Exception as exc:
        report['error'] = type(exc).__name__ + ': ' + str(exc)
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print(json.dumps({'passed': report['passed'], 'checks': len(report['checks']),
                      'failures': [check['name'] for check in report['checks'] if not check['passed']],
                      'error': report.get('error')}))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
