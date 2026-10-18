"""Actual synthetic Handler only, extracted without sockets or provider credentials."""
import ast
import base64
import json
import re
import sys
from pathlib import Path
from types import SimpleNamespace
from provider_wordpress_installed_http_contract import OfflineResponse


def run(source):
    checks = []

    def check(name, passed):
        checks.append({'name': name, 'passed': bool(passed)})

    nodes = [n for n in ast.walk(ast.parse(source.read_text(encoding='utf-8'))) if isinstance(n, ast.ClassDef) and n.name == 'Handler']
    assert len(nodes) == 1
    fixture = SimpleNamespace(calls=0, get_paths=[], posts=[], errors=[], mode='success', registrar_mode='success', cloud_mode='success', packages=[{'id': 2501}], registrar_domains=[], cloud_servers=[], stackcp_membership=[], stackcp_grants=[], wp_installed=True, wp_installed_mode='success')
    namespace = {'BaseHTTPRequestHandler': OfflineResponse, 'fixture': fixture, 'base64': base64, 'json': json, 're': re}
    exec(compile(ast.Module(body=nodes, type_ignores=[]), str(source), 'exec'), namespace)
    Handler = namespace['Handler']
    routes = ['/package/2501/stackUserList', '/reseller/*/stackUser/25001/grants']
    for route, attribute, samples in [(routes[0], 'stackcp_membership', [[{'identity': 'stack-user:25001'}], [], None, {'unexpected': True}]), (routes[1], 'stackcp_grants', [['package:2501'], [], None, {'unexpected': True}])]:
        for i, value in enumerate(samples):
            setattr(fixture, attribute, value); response = Handler(route); response.do_GET(); body = json.dumps(value).encode()
            check('Exact synthetic access response ' + attribute + ' ' + str(i), response.status == 200 and response.wfile.getvalue() == body)
            check('Exact synthetic access response headers ' + attribute + ' ' + str(i), response.response_headers == {'Content-Type': 'application/json', 'Content-Length': str(len(body)), 'Connection': 'close'})
    for label, route, expected in [('hosting', '/package', fixture.packages), ('WordPress', '/package/2501/web/wordpressIsInstalled', True)]:
        response = Handler(route); response.do_GET(); check('Existing synthetic ' + label + ' remains unchanged', response.status == 200 and json.loads(response.wfile.getvalue()) == expected)
    check('Synthetic access calls have exact count paths and no POST', fixture.calls == 10 and fixture.get_paths == [routes[0]] * 4 + [routes[1]] * 4 + ['/package', '/package/2501/web/wordpressIsInstalled'] and fixture.posts == [] and fixture.errors == [])
    response = Handler(routes[0]); response.headers['Authorization'] = 'invalid'; response.do_GET()
    check('Synthetic access rejects unexpected authentication', fixture.errors == ['Unexpected fixed request'])
    response = Handler('/reseller/*/stackUser/25001/token'); response.do_GET()
    check('Synthetic access never admits a token route', fixture.errors == ['Unexpected fixed request', 'Unexpected fixed request'])
    return {'passed': all(c['passed'] for c in checks), 'scope': 'Actual extracted loopback fixture Handler, no server/socket/database/application/provider execution.', 'checks': checks}


if __name__ == '__main__':
    report = run(Path(sys.argv[1]) if len(sys.argv) > 1 else Path(__file__).with_name('provider_http_server.py'))
    print(json.dumps(report, indent=2)); raise SystemExit(0 if report['passed'] else 1)
