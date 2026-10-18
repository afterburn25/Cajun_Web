"""Exercise the real synthetic HTTP handler; no socket, database or provider call."""
from pathlib import Path
from types import SimpleNamespace
import ast
import base64
import json
import re
from provider_directory_controls_http_contract import OfflineResponse


def run():
    checks = []
    def check(name, passed):
        checks.append({'name': name, 'passed': bool(passed)})
    source = Path(__file__).with_name('provider_http_server.py')
    tree = ast.parse(source.read_text(encoding='utf-8'))
    nodes = [n for n in ast.walk(tree) if isinstance(n, ast.ClassDef) and n.name == 'Handler']
    assert len(nodes) == 1
    fixture = SimpleNamespace(calls=0, get_paths=[], posts=[], errors=[], mode='success', write_mode='success',
        registrar_mode='success', cloud_mode='success', packages=[{'id': 2701}], registrar_domains=[], cloud_servers=[],
        cloud_providers=[], cloud_providers_mode='success', contract_dates={}, contract_mode='success', expiry_dates={},
        expiry_mode='success', domain_mode='success', period_mode='success', period_map={'.invalid': [1]},
        php_version='php82', php_version_mode='success', document_roots={'website.invalid': 'public_html'},
        document_roots_mode='success', force_ssl=True, force_ssl_mode='success', directory_indexing=False,
        directory_indexing_mode='success', wp_installed=True, wp_installed_mode='success')
    namespace = {'BaseHTTPRequestHandler': OfflineResponse, 'fixture': fixture, 'base64': base64, 'json': json, 're': re}
    exec(compile(ast.Module(body=nodes, type_ignores=[]), str(source), 'exec'), namespace)
    Handler = namespace['Handler']
    route = '/package/2701/web/updateDocRoot'
    read_route = '/package/2701/web/documentRoots'
    raw = b'{"update":{"website.invalid":"public_html/newsite"}}'
    response = Handler(route, raw); response.do_POST()
    check('Canonical single-website POST receives synthetic unverified acknowledgement',
          response.status == 200 and response.wfile.getvalue() == b'true' and not fixture.errors
          and fixture.posts == [{'path': route, 'body': json.loads(raw)}])
    response = Handler(read_route); response.do_GET()
    check('POST cannot silently change independently observed folder map', json.loads(response.wfile.getvalue()) == {'website.invalid': 'public_html'})
    fixture.document_roots = {'website.invalid': 'public_html/newsite'}
    response = Handler(read_route); response.do_GET()
    check('A separately selected complete map can report the desired folder', json.loads(response.wfile.getvalue()) == fixture.document_roots)
    for index, body in enumerate([b'{}', b'[]', b'null', b'{"update":{}}', b'{"update":{"website.invalid":""}}',
        b'{"update":{"website.invalid":"../private"}}', b'{"update":{"website.invalid":"public_html"},"extra":true}',
        b'{"update":{"website.invalid":"public_html","other.invalid":"public_html"}}',
        b'{"update":{"website.invalid":"first","website.invalid":"second"}}',
        b'{"update":{"website.invalid":"public_html"},"update":{"website.invalid":"public_html"}}',
        b'{"update": {"website.invalid":"public_html"}}', b'{"update":{"website.invalid":false}}',
        b'{"update":{"UPPER.invalid":"public_html"}}', b'{"update":{"website.invalid":"/public_html"}}']):
        fixture.errors.clear(); response = Handler(route, body); response.do_POST()
        check('Malformed noncanonical or multi-website scope is refused '+str(index), response.status == 400
              and fixture.errors == ['Unexpected single-website folder change body'])
    for label, change in [('authorization', lambda r: r.headers.update(Authorization='Bearer wrong')),
                          ('path', lambda r: setattr(r, 'path', route+'/')),
                          ('content-type', lambda r: r.headers.update({'Content-Type': 'text/plain'}))]:
        fixture.errors.clear(); response = Handler(route, raw); change(response); response.do_POST()
        check('Synthetic handler records invalid '+label, any(x.startswith('Unexpected write request') for x in fixture.errors))
    fixture.errors.clear()
    for label, path, expected in [('hosting','/package',fixture.packages), ('Force SSL','/package/2701/web/forceSSL',True),
                                   ('directory listing','/package/2701/web/directoryIndexing',False)]:
        response = Handler(path); response.do_GET()
        check('Existing '+label+' GET remains unchanged', response.status == 200 and json.loads(response.wfile.getvalue()) == expected)
    check('Neighboring routes introduce no fixture errors', not fixture.errors)
    return {'passed': all(x['passed'] for x in checks), 'scope': 'Actual extracted synthetic Handler only; no socket, TLS, native database, provider or browser execution.', 'checks': checks}


if __name__ == '__main__':
    report = run()
    print(json.dumps(report, indent=2))
    raise SystemExit(0 if report['passed'] else 1)
