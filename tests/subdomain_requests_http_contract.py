"""Actual extracted fixture Handler, in-memory HTTP streams; no listener, SQL or provider."""
import argparse
import ast
import base64
import copy
import io
import json
from pathlib import Path
import re
from http.server import BaseHTTPRequestHandler
from types import SimpleNamespace


AUTH = 'Bearer ' + base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY').decode()
PATH = '/package/3101/web/subdomains'
NAME = 'shop.subdomain-requests.synthetic.invalid'
BODY = b'{"add":["shop.subdomain-requests.synthetic.invalid"],"rem":[]}'
CHECKS = []


def check(name, passed):
    if type(passed) is not bool or any(c['name'] == name for c in CHECKS):
        raise AssertionError('Invalid or duplicate test outcome')
    CHECKS.append({'name': name, 'passed': passed})
    if not passed:
        raise AssertionError(name)


def extracted_handler():
    source = Path(__file__).with_name('provider_http_server.py')
    tree = ast.parse(source.read_text(encoding='utf-8'), filename=str(source))
    server = next(n for n in tree.body if isinstance(n, ast.ClassDef) and n.name == 'ProviderServer')
    init = next(n for n in server.body if isinstance(n, ast.FunctionDef) and n.name == '__init__')
    handler = next(n for n in init.body if isinstance(n, ast.ClassDef) and n.name == 'Handler')
    # Only the actual constructor's fixture-state assignments precede the actual
    # helper/Handler. Certificate creation, imports and server construction never run.
    assignments = []
    for node in init.body:
        if node is handler:
            break
        if isinstance(node, ast.Assign) and all(
            (isinstance(t, ast.Attribute) and isinstance(t.value, ast.Name) and t.value.id == 'self')
            or (isinstance(t, ast.Name) and t.id == 'fixture') for t in node.targets
        ):
            assignments.append(node)
    fixture = SimpleNamespace()
    namespace = {'self': fixture, 'fixture': fixture, 'BaseHTTPRequestHandler': BaseHTTPRequestHandler,
                 'base64': base64, 'json': json, 're': re}
    module = ast.Module(body=assignments + [handler], type_ignores=[])
    exec(compile(module, str(source), 'exec'), namespace)
    return fixture, namespace['Handler']


def request(handler, method, path, raw=b'', headers=None):
    instance = handler.__new__(handler)
    instance.path = path
    instance.headers = {'Authorization': AUTH, 'Accept': 'application/json',
                        'Content-Type': 'application/json', 'Content-Length': str(len(raw))}
    if headers:
        instance.headers.update(headers)
    instance.rfile, instance.wfile = io.BytesIO(raw), io.BytesIO()
    response = {'status': None, 'headers': {}}
    instance.send_response = lambda status: response.update(status=status)
    instance.send_header = lambda name, value: response['headers'].update({name: value})
    instance.end_headers = lambda: None
    getattr(instance, 'do_' + method)()
    response['body'] = instance.wfile.getvalue()
    return response


def configured():
    fixture, handler = extracted_handler()
    fixture.subdomain_add_mode = 'uncertain'
    fixture.subdomain_add_package_id = 3101
    fixture.document_roots = {'subdomain-requests.synthetic.invalid': 'public_html/original'}
    fixture.packages = [{'id': 3101, 'name': 'subdomain-requests.synthetic.invalid'}]
    return fixture, handler


def reject(label, raw=BODY, path=PATH, headers=None, configure=None):
    fixture, handler = configured()
    if configure:
        configure(fixture)
    original = copy.deepcopy((fixture.document_roots, fixture.packages))
    response = request(handler, 'POST', path, raw, headers)
    check('Reject ' + label, response['status'] == 400
          and response['body'] == b'{"error":"synthetic subdomain scope refused"}'
          and fixture.errors == ['Unexpected subdomain add request']
          and fixture.calls == 1 and fixture.posts == [] and fixture.subdomain_add_posts == []
          and (fixture.document_roots, fixture.packages) == original)


def run():
    fixture, handler = extracted_handler()
    check('Actual constructor keeps endpoint disabled and unconfigured',
          fixture.subdomain_add_mode == 'disabled' and fixture.subdomain_add_package_id is None
          and fixture.subdomain_add_posts == [])
    response = request(handler, 'POST', PATH, BODY)
    check('Disabled endpoint preserves predecessor unexpected-route behavior',
          response['status'] == 200 and fixture.errors == ['Unexpected write request ' + PATH]
          and fixture.subdomain_add_posts == [] and fixture.posts == [{'path': PATH, 'body': json.loads(BODY)}])

    for mode, status, body in [
        ('uncertain', 202, b'{"status":"accepted"}'), ('success', 200, b'true'),
        ('failure', 400, b'{"error":"synthetic refusal"}'),
        ('authentication', 401, b'private synthetic subdomain response'), ('malformed', 200, b'not JSON'),
    ]:
        fixture, handler = configured(); fixture.subdomain_add_mode = mode
        original = copy.deepcopy((fixture.document_roots, fixture.packages))
        response = request(handler, 'POST', PATH, BODY)
        check('Exact canonical POST records scope and raw bytes for ' + mode,
              response['status'] == status and response['body'] == body
              and fixture.errors == [] and fixture.calls == 1
              and fixture.subdomain_add_posts == [{'path': PATH, 'body': json.loads(BODY), 'raw': BODY.decode()}]
              and fixture.posts == [{'path': PATH, 'body': json.loads(BODY)}]
              and (fixture.document_roots, fixture.packages) == original)
        check('Actual response framing remains bounded for ' + mode,
              response['headers'] == {'Content-Type': 'application/json', 'Content-Length': str(len(body)), 'Connection': 'close'})
        observed = request(handler, 'GET', '/package/3101/web/documentRoots')
        check('Independent GET retains original map after ' + mode,
              observed['status'] == 200 and json.loads(observed['body']) == original[0]
              and fixture.get_paths == ['/package/3101/web/documentRoots'] and fixture.calls == 2)

    fixture, handler = configured()
    request(handler, 'POST', PATH, BODY)
    fixture.document_roots = {'subdomain-requests.synthetic.invalid': 'public_html/original', NAME: ''}
    response = request(handler, 'GET', '/package/3101/web/documentRoots')
    check('Only an independently configured later GET reports the requested name',
          json.loads(response['body']) == fixture.document_roots and json.loads(response['body'])[NAME] == ''
          and len(fixture.subdomain_add_posts) == len(fixture.posts) == 1)
    fixture.document_roots_mode = 'authentication'
    response = request(handler, 'GET', '/package/3101/web/documentRoots')
    check('Later authentication failure stays a separate read result',
          response['status'] == 401 and response['body'] == b'private synthetic roots response'
          and len(fixture.subdomain_add_posts) == 1)

    for label, path in [
        ('foreign package', '/package/3102/web/subdomains'), ('zero package', '/package/0/web/subdomains'),
        ('leading zero package', '/package/03101/web/subdomains'), ('oversized package', '/package/9999999999999999999/web/subdomains'),
        ('named package', '/package/example.invalid/web/subdomains'), ('trailing path', PATH + '/'),
        ('query', PATH + '?id=3101'), ('encoded suffix', PATH + '%2f'), ('wrong root', '/domain/3101/web/subdomains'),
    ]:
        reject(label, path=path)
    for label, headers in [
        ('missing bearer', {'Authorization': ''}), ('foreign bearer', {'Authorization': 'Bearer PRIVATE_OTHER_KEY'}),
        ('wrong accept', {'Accept': 'text/html'}), ('wrong content type', {'Content-Type': 'text/plain'}),
        ('content type parameters', {'Content-Type': 'application/json; charset=utf-8'}),
        ('zero declared length', {'Content-Length': '0'}), ('oversized declared length', {'Content-Length': '8193'}),
        ('truncated declared body', {'Content-Length': str(len(BODY)-1)}),
        ('incomplete declared body', {'Content-Length': str(len(BODY)+1)}),
    ]:
        reject(label, headers=headers)
    for label, body in [
        ('empty body', b''), ('invalid JSON', b'{'), ('invalid UTF8', b'\xff'), ('overlong body', b' ' * 8193),
        ('root array', b'[]'), ('root null', b'null'), ('root scalar', b'true'),
        ('duplicate key', BODY[:-1] + b',"rem":[]}'), ('reordered keys', b'{"rem":[],"add":["' + NAME.encode() + b'"]}'),
        ('whitespace alternative', b' ' + BODY), ('newline alternative', BODY + b'\n'),
        ('escaped-name alternative', BODY.replace(b'shop', b'\\u0073hop')),
        ('extra parent', BODY[:-1] + b',"parentDomain":"subdomain-requests.synthetic.invalid"}'),
        ('extra path', BODY[:-1] + b',"path":"public_html"}'), ('extra billing', BODY[:-1] + b',"price":1}'),
        ('no add', b'{"rem":[]}'), ('no rem', b'{"add":["' + NAME.encode() + b'"]}'),
        ('empty add', b'{"add":[],"rem":[]}'), ('two adds', b'{"add":["' + NAME.encode() + b'","other.example.invalid"],"rem":[]}'),
        ('string add', b'{"add":"' + NAME.encode() + b'","rem":[]}'),
        ('object add', b'{"add":{"0":"' + NAME.encode() + b'"},"rem":[]}'),
        ('null hostname', b'{"add":[null],"rem":[]}'), ('remove operation', b'{"add":["' + NAME.encode() + b'"],"rem":["old.example.invalid"]}'),
        ('object rem', b'{"add":["' + NAME.encode() + b'"],"rem":{}}'),
    ]:
        reject(label, raw=body)
    for label, name in [
        ('two-label domain', 'example.invalid'), ('wildcard', '*.example.invalid'), ('IPv4', '127.0.0.1'),
        ('URL', 'https://shop.example.invalid'), ('port', 'shop.example.invalid:443'),
        ('uppercase', 'Shop.example.invalid'), ('Unicode', 'shÃ¶p.example.invalid'),
        ('leading hyphen', '-shop.example.invalid'), ('trailing hyphen', 'shop-.example.invalid'),
        ('empty label', 'shop..example.invalid'), ('trailing dot', 'shop.example.invalid.'),
        ('underscore', 'my_shop.example.invalid'), ('long label', 'x'*64+'.example.invalid'),
        ('long hostname', '.'.join(['x'*63]*4)),
    ]:
        reject(label, raw=json.dumps({'add': [name], 'rem': []}, separators=(',', ':')).encode())
    reject('unconfigured package', configure=lambda f: setattr(f, 'subdomain_add_package_id', None))
    reject('string package configuration', configure=lambda f: setattr(f, 'subdomain_add_package_id', '3101'))
    reject('other configured package', configure=lambda f: setattr(f, 'subdomain_add_package_id', 3102))
    reject('unknown response mode', configure=lambda f: setattr(f, 'subdomain_add_mode', 'assume_success'))

    # Representative original endpoints run through the same extracted GET/POST bodies.
    fixture, handler = configured()
    response = request(handler, 'GET', '/package')
    check('Original package listing remains intact', response['status'] == 200 and json.loads(response['body']) == fixture.packages)
    fixture.registrar_domains = [{'id': 777, 'name': 'retained.invalid'}]
    response = request(handler, 'GET', '/domain')
    check('Original registrar list remains intact', response['status'] == 200 and json.loads(response['body']) == fixture.registrar_domains)
    response = request(handler, 'GET', '/domain-period')
    check('Original domain period map remains intact', response['status'] == 200 and json.loads(response['body']) == fixture.period_map)
    for endpoint, attribute in [('forceSSL', 'force_ssl'), ('directoryIndexing', 'directory_indexing')]:
        response = request(handler, 'POST', '/package/3101/web/' + endpoint, b'{"value":false}')
        check('Original ' + endpoint + ' POST keeps independent flag', response['status'] == 200 and response['body'] == b'true' and getattr(fixture, attribute) is True)
        response = request(handler, 'GET', '/package/3101/web/' + endpoint)
        check('Original ' + endpoint + ' GET remains independent', response['status'] == 200 and response['body'] == b'true')
    renew = b'{"name":"retained.invalid","years":1,"renewPrivacy":false}'
    response = request(handler, 'POST', '/reseller/*/renewDomain', renew)
    check('Original renewal POST retains exact record and reply', response['status'] == 200 and response['body'] == b'true'
          and fixture.renewal_posts == [{'path': '/reseller/*/renewDomain', 'body': json.loads(renew)}])
    mailbox = {'new': {'mailbox': {'local': 'office', 'send': 'true', 'receive': 'true', 'password': 'SYNTHETIC_MAIL_PASSWORD'}}}
    response = request(handler, 'POST', '/package/3101/email/example.invalid', json.dumps(mailbox).encode())
    check('Original mailbox reply and redacted record remain intact', response['status'] == 200
          and json.loads(response['body'])['result']['result'][0]['id'] == 'office'
          and fixture.posts[-1] == {'path': '/package/3101/email/example.invalid', 'body': {'mailbox_local': 'office'}})
    count = len(fixture.packages)
    response = request(handler, 'POST', '/cloud_server/1401/addWeb', b'{"domain_name":"new-cloud.synthetic.invalid","type":1}')
    check('Original cloud addWeb preserves its existing synthetic list effect', response['status'] == 200
          and len(fixture.packages) == count+1 and fixture.packages[-1]['name'] == 'new-cloud.synthetic.invalid'
          and json.loads(response['body']) == {'result': 100+count})
    check('Unrelated endpoints never populate new subdomain records', fixture.subdomain_add_posts == [] and fixture.errors == [])


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    # Exclusive output: preserve prior evidence; refuse symlinks before any execution.
    if args.report.is_symlink() or args.report.exists() or any(p.is_symlink() for p in args.report.parents):
        raise SystemExit('Report target must be new and outside symlinks')
    args.report.parent.mkdir(parents=True, exist_ok=True)
    with args.report.open('x', encoding='utf-8', newline='\n') as stream:
        error = None
        try:
            run()
        except Exception as exc:
            error = {'class': type(exc).__name__}
        result = {'passed': error is None, 'scope': __doc__, 'checks': CHECKS, 'failure': error}
        json.dump(result, stream, indent=2); stream.write('\n')
    print(json.dumps({'passed': result['passed'], 'checks': len(CHECKS), 'failure': error}))
    return 0 if error is None else 1


if __name__ == '__main__':
    raise SystemExit(main())
