"""Run the actual synthetic POST handler in memory; never bind a socket."""
from pathlib import Path
from types import SimpleNamespace
import ast
import base64
import io
import json
import re
import sys


class OfflineResponse:
    def __init__(self, path, payload):
        self.path = path
        raw = json.dumps(payload).encode()
        self.headers = {'Authorization': 'Bearer ' + base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY').decode(), 'Accept': 'application/json', 'Content-Type': 'application/json', 'Content-Length': str(len(raw))}
        self.rfile = io.BytesIO(raw)
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
    check('Actual Handler retains one GET and POST method', len(methods) == len(set(methods)) and methods.count('do_GET') == methods.count('do_POST') == 1)
    fixture = SimpleNamespace(calls=0, posts=[], errors=[], packages=[], write_mode='success', renewal_mode='success', renewal_posts=[])
    namespace = {'BaseHTTPRequestHandler': OfflineResponse, 'fixture': fixture, 'base64': base64, 'json': json, 're': re}
    helpers = [n for n in tree.body if isinstance(n, (ast.FunctionDef, ast.AsyncFunctionDef))]
    exec(compile(ast.Module(body=helpers + [node], type_ignores=[]), str(source), 'exec'), namespace)
    Handler = namespace['Handler']

    def post(path, payload):
        response = Handler(path, payload)
        response.do_POST()
        return response

    payload = {'name': 'renew.synthetic.invalid', 'years': 2, 'renewPrivacy': False}
    route = '/reseller/*/renewDomain'
    for mode, status, body in [('success', 200, b'true'), ('failure', 200, b'false'), ('null', 200, b'null'), ('object', 200, b'{"result":true}'), ('authentication', 401, b'private synthetic renewal response'), ('uncertain', 202, b'true'), ('echo', 200, b'"SYNTHETIC_RAW_GENERAL_KEY"')]:
        fixture.renewal_mode = mode
        response = post(route, payload)
        check('Actual renewal Handler emits ' + mode, response.status == status and response.wfile.getvalue() == body)
        check('Actual renewal Handler has exact ' + mode + ' headers', response.response_headers == {'Content-Type': 'application/json', 'Content-Length': str(len(body)), 'Connection': 'close'})
    check('Seven renewal requests retain exact scope without changing hosting inventory', fixture.renewal_posts == [{'path': route, 'body': payload}] * 7 and len(fixture.posts) == 7 and fixture.packages == [] and fixture.errors == [])
    fixture.renewal_mode = 'success'
    for label, bad in [('missing privacy', {'name': payload['name'], 'years': 2}), ('string privacy', dict(payload, renewPrivacy='false')), ('boolean years', dict(payload, years=True)), ('unknown extra', dict(payload, amount=1))]:
        before = len(fixture.errors)
        response = post(route, bad)
        check('Actual renewal Handler rejects ' + label, response.status == 400 and len(fixture.errors) == before + 1)
    before = len(fixture.errors)
    post('/reseller/123/renewDomain', payload)
    check('Numeric reseller is not an accepted renewal fixture path', len(fixture.errors) == before + 1)
    before = len(fixture.errors)
    response = post('/package/701/web/forceSSL', {'value': False})
    check('Existing Force SSL fixture acknowledgement is unchanged', response.status == 200 and response.wfile.getvalue() == b'true' and len(fixture.errors) == before)
    response = post('/cloud_server/701/addWeb', {'domain_name': 'cloud.synthetic.invalid', 'type': 1})
    check('Existing cloud website response remains numeric result', response.status == 200 and json.loads(response.wfile.getvalue()) == {'result': 100} and fixture.packages[0]['name'] == 'cloud.synthetic.invalid')
    response = post('/package/701/email/mail.synthetic.invalid', {'new': {'mailbox': {'local': 'box', 'send': 'true', 'receive': 'true', 'password': 'SYNTHETIC_MAILBOX_SECRET'}}})
    check('Existing mailbox fixture retains typed nested result', response.status == 200 and json.loads(response.wfile.getvalue())['result']['result'][0]['id'] == 'box' and fixture.posts[-1]['body'] == {'mailbox_local': 'box'})
    check('Only renewal route enters renewal accounting', len(fixture.renewal_posts) == 11 and fixture.calls == 15)
    return {'passed': all(c['passed'] for c in checks), 'scope': 'Actual extracted synthetic POST Handler executed offline; no socket, TLS, database, real provider or browser execution.', 'checks': checks}


if __name__ == '__main__':
    source = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(__file__).with_name('provider_http_server.py')
    report = run(source)
    if len(sys.argv) > 2:
        Path(sys.argv[2]).write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(report, indent=2))
    raise SystemExit(0 if report['passed'] else 1)
