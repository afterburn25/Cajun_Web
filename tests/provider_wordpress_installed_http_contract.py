"""Execute the real synthetic GET handler without sockets, TLS setup or a database."""
from pathlib import Path
from types import SimpleNamespace
import ast
import base64
import io
import json
import re
import sys


class OfflineResponse:
    def __init__(self, path):
        self.path = path
        self.headers = {'Authorization': 'Bearer ' + base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY').decode(), 'Accept': 'application/json'}
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
    check('Synthetic handler defines each method exactly once', len(methods) == len(set(methods)))
    check('Synthetic handler has exactly one GET and POST entry', methods.count('do_GET') == methods.count('do_POST') == 1)
    fixture = SimpleNamespace(calls=0, get_paths=[], posts=[], errors=[], mode='success', registrar_mode='success', cloud_mode='success', packages=[{'id': 2201}], registrar_domains=[], cloud_servers=[], cloud_providers=[], cloud_providers_mode='success', contract_dates={}, contract_mode='success', expiry_dates={}, expiry_mode='success', domain_mode='success', period_mode='success', period_map={'.invalid': [1]}, php_version='php82', php_version_mode='success', document_roots={}, document_roots_mode='success', force_ssl=True, force_ssl_mode='success', directory_indexing=False, directory_indexing_mode='success', wp_installed=True, wp_installed_mode='success')
    namespace = {'BaseHTTPRequestHandler': OfflineResponse, 'fixture': fixture, 'base64': base64, 'json': json, 're': re}
    helpers = [n for n in tree.body if isinstance(n, ast.FunctionDef) and n.name in ('registered_nameserver_reply', 'registered_nameserver_change_reply')]
    exec(compile(ast.Module(body=helpers + [node], type_ignores=[]), str(source), 'exec'), namespace)
    Handler = namespace['Handler']

    def get(path):
        response = Handler(path)
        response.do_GET()
        return response

    route = '/package/2201/web/wordpressIsInstalled'
    for label, mode, value, status, body in [
        ('reported true', 'success', True, 200, b'true'),
        ('reported false', 'success', False, 200, b'false'),
        ('unavailable null', 'null', True, 200, b'null'),
        ('unexpected object', 'object', True, 200, b'{"value":true}'),
        ('authentication failure', 'authentication', True, 401, b'private synthetic WordPress installation response'),
    ]:
        fixture.wp_installed_mode, fixture.wp_installed = mode, value
        response = get(route)
        check('Actual synthetic WordPress GET emits ' + label, response.status == status and response.wfile.getvalue() == body)
        check('Actual synthetic WordPress GET headers match ' + label, response.response_headers == {'Content-Type': 'application/json', 'Content-Length': str(len(body)), 'Connection': 'close'})
    fixture.wp_installed_mode = 'success'
    for label, path, expected in [('hosting', '/package', fixture.packages), ('Force SSL', '/package/2201/web/forceSSL', True), ('directory listing', '/package/2201/web/directoryIndexing', False)]:
        response = get(path)
        check('Existing synthetic ' + label + ' GET remains exact', response.status == 200 and json.loads(response.wfile.getvalue()) == expected)
    check('Actual synthetic GET handlers retain exact request accounting', fixture.calls == 8 and fixture.get_paths == [route] * 5 + ['/package', '/package/2201/web/forceSSL', '/package/2201/web/directoryIndexing'])
    check('Synthetic reads create no provider POST or unexpected request', fixture.posts == [] and fixture.errors == [])
    passed = all(c['passed'] for c in checks)
    return {'passed': passed, 'scope': 'Actual extracted synthetic Handler executed offline; no socket, TLS, database, real provider or application browser execution.', 'checks': checks}


if __name__ == '__main__':
    source = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(__file__).with_name('provider_http_server.py')
    report = run(source)
    print(json.dumps(report, indent=2))
    raise SystemExit(0 if report['passed'] else 1)
