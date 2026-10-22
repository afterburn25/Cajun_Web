"""Execute the real synthetic handler without sockets, credentials or a database."""
from pathlib import Path
from types import SimpleNamespace
import ast
import base64
import io
import json
import re
import sys


class OfflineResponse:
    def __init__(self, path, body=b''):
        self.path = path
        self.headers = {'Authorization': 'Bearer ' + base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY').decode(), 'Accept': 'application/json', 'Content-Type': 'application/json', 'Content-Length': str(len(body))}
        self.rfile = io.BytesIO(body)
        self.wfile = io.BytesIO()
        self.response_headers = {}
        self.status = None

    def send_response(self, status):
        self.status = status

    def send_header(self, name, value):
        self.response_headers[name] = value

    def end_headers(self):
        pass


def run(source):
    checks = []
    def check(name, passed):
        checks.append({'name': name, 'passed': bool(passed)})
    tree = ast.parse(source.read_text(encoding='utf-8'))
    handlers = [n for n in ast.walk(tree) if isinstance(n, ast.ClassDef) and n.name == 'Handler']
    assert len(handlers) == 1
    node = handlers[0]
    methods = [n.name for n in node.body if isinstance(n, (ast.FunctionDef, ast.AsyncFunctionDef))]
    check('Actual synthetic handler has unique methods and one GET and POST', len(methods) == len(set(methods)) and methods.count('do_GET') == methods.count('do_POST') == 1)
    fixture = SimpleNamespace(calls=0, get_paths=[], posts=[], errors=[], mode='success', write_mode='success', registrar_mode='success', cloud_mode='success', packages=[{'id': 2301}], registrar_domains=[], cloud_servers=[], cloud_providers=[], cloud_providers_mode='success', contract_dates={}, contract_mode='success', expiry_dates={}, expiry_mode='success', domain_mode='success', period_mode='success', period_map={'.invalid': [1]}, php_version='php82', php_version_mode='success', document_roots={}, document_roots_mode='success', force_ssl=True, force_ssl_mode='success', directory_indexing=False, directory_indexing_mode='success', wp_installed=True, wp_installed_mode='success')
    namespace = {'BaseHTTPRequestHandler': OfflineResponse, 'fixture': fixture, 'base64': base64, 'json': json, 're': re}
    helpers = [n for n in tree.body if isinstance(n, (ast.FunctionDef, ast.AsyncFunctionDef))]
    exec(compile(ast.Module(body=helpers + [node], type_ignores=[]), str(source), 'exec'), namespace)
    Handler = namespace['Handler']
    route = '/package/2301/web/directoryIndexing'
    for desired in [True, False]:
        fixture.directory_indexing = not desired
        start = fixture.calls
        response = Handler(route, json.dumps({'value': desired}).encode()); response.do_POST()
        check('Actual boolean POST has only synthetic uncertain acknowledgement ' + str(desired), response.status == 200 and response.wfile.getvalue() == b'true' and fixture.posts[-1] == {'path': route, 'body': {'value': desired}} and not fixture.errors)
        read = Handler(route); read.do_GET()
        check('POST does not silently change independently observed GET flag ' + str(desired), json.loads(read.wfile.getvalue()) is not desired and fixture.directory_indexing is not desired and fixture.calls == start + 2)
    for label, mode, status, body in [('null', 'null', 200, b'null'), ('auth', 'authentication', 401, b'private synthetic Directory listing response')]:
        fixture.directory_indexing_mode = mode
        response = Handler(route); response.do_GET()
        check('Directory GET preserves exact ' + label + ' state', response.status == status and response.wfile.getvalue() == body)
    fixture.directory_indexing_mode = 'success'
    for body in [b'{}', b'{"value":1}', b'{"value":"true"}', b'{"value":null}', b'{"value":true,"extra":false}']:
        fixture.errors.clear(); response = Handler(route, body); response.do_POST()
        check('Actual handler records invalid boolean request ' + body.decode(), fixture.errors == ['Unexpected directory listing boolean write body'])
    for label, change in [('authorization', lambda r: r.headers.update(Authorization='Bearer wrong')), ('origin path', lambda r: setattr(r, 'path', route + '/')), ('content type', lambda r: r.headers.update({'Content-Type': 'text/plain'}))]:
        fixture.errors.clear(); response = Handler(route, b'{"value":true}'); change(response); response.do_POST()
        check('Actual handler records unexpected ' + label, any(e.startswith('Unexpected write request ') for e in fixture.errors))
    fixture.errors.clear()
    for label, path, expected in [('Force SSL', '/package/2301/web/forceSSL', True), ('WordPress', '/package/2301/web/wordpressIsInstalled', True), ('hosting', '/package', fixture.packages)]:
        response = Handler(path); response.do_GET()
        check('Other exact synthetic ' + label + ' GET is preserved', response.status == 200 and json.loads(response.wfile.getvalue()) == expected)
    before = fixture.directory_indexing
    response = Handler('/package/2301/web/forceSSL', b'{"value":false}'); response.do_POST()
    check('Existing Force SSL POST keeps both independent GET fixtures unchanged', response.status == 200 and response.wfile.getvalue() == b'true' and fixture.force_ssl is True and fixture.directory_indexing is before and not fixture.errors)
    return {'passed': all(c['passed'] for c in checks), 'scope': 'Actual extracted synthetic HTTP Handler only. No socket, TLS, database, provider, credentials or browser execution.', 'checks': checks}


if __name__ == '__main__':
    report = run(Path(sys.argv[1]) if len(sys.argv) > 1 else Path(__file__).with_name('provider_http_server.py'))
    print(json.dumps(report, indent=2))
    raise SystemExit(0 if report['passed'] else 1)
