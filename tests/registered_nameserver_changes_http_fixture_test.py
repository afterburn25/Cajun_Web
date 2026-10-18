"""Portable synthetic ProviderServer handler tests, not application acceptance.

The actual nested Handler runs on its own loopback TLS listener with an ephemeral
CA and the existing synthetic key. No PHP, SQL, installed site or provider API.
Raw request bodies stay in memory; the optional report contains names/booleans.
"""
from pathlib import Path
import argparse
import base64
import copy
from email.message import Message
import http.client
import json
import shutil
import socket
import ssl
import tempfile
from types import SimpleNamespace
import unittest

from provider_http_server import ProviderServer, registered_nameserver_change_reply


OLD = ['ns1.nameservers.invalid', 'ns2.nameservers.invalid']
NEW = ['ns1.changed-nameservers.invalid', 'ns2.changed-nameservers.invalid']
FINAL = ['ns1.final-nameservers.invalid', 'ns2.final-nameservers.invalid']
PATH = '/domain/14701/nameservers'
AUTH = 'Bearer '+base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY').decode()


def wire(desired=NEW, old=OLD):
    return json.dumps({'ns': desired, 'old-ns': old}, separators=(',', ':')).encode('ascii')


class HandlerTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix='registered-nameserver-handler-')
        cls.addClassCleanup(cls.temp.cleanup)
        root = Path(cls.temp.name)
        helpers = root/'tests/twentyi/tls_test.py'
        helpers.parent.mkdir(parents=True)
        shutil.copyfile(Path(__file__).with_name('twentyi')/'tls_test.py', helpers)
        cls.fixture = ProviderServer(root)
        cls.addClassCleanup(cls.fixture.close)
        cls.initial_change_state = (cls.fixture.nameserver_change_mode, list(cls.fixture.nameserver_change_posts))
        # Use the existing fixture CA/hostname validation. Its historical leaf
        # lacks AKI, so do not opt into stricter optional X509-profile flags.
        cls.context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
        cls.context.load_verify_locations(str(root/'test-runtime/provider-http/ca.pem'))

    def setUp(self):
        fixture = self.fixture
        fixture.mode = fixture.write_mode = fixture.nameserver_mode = 'success'
        fixture.nameserver_change_mode = 'uncertain_unlanded'
        fixture.nameservers = {'14701': list(OLD), '14702': ['ns.other.invalid']}
        fixture.nameserver_change_posts = []
        fixture.posts = []; fixture.errors = []; fixture.get_paths = []
        fixture.calls = 0; fixture.names = []; fixture.packages = []
        fixture.renewal_mode = 'success'; fixture.renewal_posts = []

    def request(self, method='POST', path=PATH, raw=None, changes=None, extra=()):
        if raw is None:
            raw = wire() if method == 'POST' else b''
        headers = {'Host': 'api.20i.com', 'Authorization': AUTH,
                   'Content-Type': 'application/json', 'Accept': 'application/json',
                   'Content-Length': str(len(raw)), 'Connection': 'close'}
        for key, value in (changes or {}).items():
            if value is None:
                headers.pop(key, None)
            else:
                headers[key] = value
        packet = (method+' '+path+' HTTP/1.1\r\n'+''.join(key+': '+value+'\r\n'
                  for key, value in [*headers.items(), *extra])+'\r\n').encode('ascii')+raw
        with socket.create_connection(('127.0.0.1', self.fixture.server.server_port), timeout=5) as stream:
            with self.context.wrap_socket(stream, server_hostname='api.20i.com') as secure:
                secure.sendall(packet)
                response = http.client.HTTPResponse(secure)
                response.begin()
                body = response.read()
                self.assertEqual(response.getheader('Content-Type'), 'application/json')
                self.assertEqual(response.getheader('Content-Length'), str(len(body)))
                self.assertEqual(response.getheader('Connection'), 'close')
                return response.status, body

    def refused(self, **request):
        before = copy.deepcopy(self.fixture.nameservers)
        status, body = self.request(**request)
        self.assertEqual(status, 400)
        self.assertEqual(body, b'{"error":"synthetic nameserver change refused"}')
        self.assertEqual(self.fixture.errors, ['Unexpected registered nameserver change request'])
        self.assertEqual(self.fixture.nameservers, before)
        self.assertEqual(self.fixture.nameserver_change_posts, [])
        self.assertEqual(self.fixture.posts, [])
        self.assertEqual(self.fixture.calls, 1)

    def test_tls_validates_own_ca_and_api_hostname(self):
        self.assertEqual(self.context.verify_mode, ssl.CERT_REQUIRED)
        self.assertTrue(self.context.check_hostname)
        self.request()
        self.assertEqual(self.fixture.names, ['api.20i.com'])

    def test_actual_constructor_starts_disabled_without_posts(self):
        self.assertEqual(self.initial_change_state, ('disabled', []))

    def test_unlanded_post_retains_exact_wire_without_changing_get(self):
        status, body = self.request()
        self.assertEqual(status, 202)
        self.assertIn(b'unverified', body)
        payload = {'ns': NEW, 'old-ns': OLD}
        self.assertEqual(self.fixture.nameserver_change_posts, [{'path': PATH, 'body': payload, 'raw': wire().decode()}])
        self.assertEqual(self.fixture.posts, [{'path': PATH, 'body': payload}])
        self.assertEqual(self.fixture.nameservers['14701'], OLD)
        self.assertEqual(self.request('GET'), (200, json.dumps({'result': OLD}, separators=(',', ':')).encode()))
        self.assertEqual(self.fixture.errors, [])

    def test_only_explicit_landed_mode_changes_separate_get(self):
        self.fixture.nameserver_change_mode = 'uncertain_landed'
        self.assertEqual(self.request()[0], 202)
        self.assertEqual(self.request('GET')[0], 200)
        self.assertEqual(self.fixture.nameservers['14701'], NEW)
        self.assertEqual(self.fixture.nameservers['14702'], ['ns.other.invalid'])
        self.fixture.nameserver_change_posts[0]['body']['ns'].append('ns.extra.invalid')
        self.assertEqual(self.fixture.nameservers['14701'], NEW)

    def test_authentication_post_retained_but_does_not_change_get(self):
        self.fixture.nameserver_change_mode = 'authentication'
        self.assertEqual(self.request(), (401, b'private synthetic nameserver change response'))
        self.assertEqual(len(self.fixture.nameserver_change_posts), 1)
        self.assertEqual(self.fixture.nameservers['14701'], OLD)
        self.assertEqual(self.fixture.errors, [])

    def test_separate_current_observation_supports_second_approved_body(self):
        self.assertEqual(self.request()[0], 202)
        self.fixture.nameservers['14701'] = list(NEW)  # Independent synthetic GET state.
        second = wire(FINAL, NEW)
        self.assertEqual(self.request(raw=second)[0], 202)
        self.assertEqual(len(self.fixture.nameserver_change_posts), 2)
        self.assertEqual([row['raw'] for row in self.fixture.nameserver_change_posts], [wire().decode(), second.decode()])
        self.assertEqual(self.fixture.nameservers['14701'], NEW)

    def test_missing_target_observation_refuses(self):
        del self.fixture.nameservers['14701']
        self.refused()

    def test_malformed_saved_observation_refuses(self):
        self.fixture.nameservers['14701'] = [OLD[0], OLD[0]]
        self.refused()

    def test_unknown_mode_refuses(self):
        self.fixture.nameserver_change_mode = 'success'
        self.refused()

    def test_sixty_four_normalized_names_admitted(self):
        names = sorted('ns'+str(i)+'.bound.invalid' for i in range(64))
        self.assertEqual(self.request(raw=wire(names))[0], 202)
        self.assertEqual(self.fixture.nameserver_change_posts[0]['body']['ns'], names)

    def test_maximum_normalized_hostname_admitted(self):
        name = '.'.join(['a'*63, 'b'*63, 'c'*63, 'd'*61])
        self.assertEqual(len(name), 253)
        self.assertEqual(self.request(raw=wire([name]))[0], 202)

    def test_disabled_default_preserves_original_generic_rejection_behavior(self):
        self.fixture.nameserver_change_mode = 'disabled'
        status, body = self.request()
        # Historical generic handler returns its configured synthetic reply but
        # records the unsupported path as an error. Do not invent old admission.
        self.assertEqual((status, body), (200, b'{"id": 100}'))
        self.assertEqual(self.fixture.errors, ['Unexpected write request '+PATH])
        self.assertEqual(self.fixture.posts, [{'path': PATH, 'body': {'ns': NEW, 'old-ns': OLD}}])
        self.assertEqual(self.fixture.nameserver_change_posts, [])
        self.assertEqual(self.fixture.nameservers['14701'], OLD)

    def test_unrelated_force_ssl_handler_is_unchanged_when_enabled(self):
        path = '/package/101/web/forceSSL'
        self.assertEqual(self.request(path=path, raw=b'{"value":false}'), (200, b'true'))
        self.assertEqual(self.fixture.posts, [{'path': path, 'body': {'value': False}}])
        self.assertEqual(self.fixture.nameserver_change_posts, [])
        self.assertEqual(self.fixture.errors, [])

    def test_unrelated_directory_handler_is_unchanged_when_enabled(self):
        self.assertEqual(self.request(path='/package/101/web/directoryIndexing', raw=b'{"value":false}'), (200, b'true'))
        self.assertEqual(self.fixture.nameserver_change_posts, [])
        self.assertEqual(self.fixture.errors, [])

    def test_unrelated_renewal_handler_is_unchanged_when_enabled(self):
        raw = b'{"name":"domain.invalid","years":1,"renewPrivacy":false}'
        self.assertEqual(self.request(path='/reseller/*/renewDomain', raw=raw), (200, b'true'))
        self.assertEqual(len(self.fixture.renewal_posts), 1)
        self.assertEqual(self.fixture.nameserver_change_posts, [])
        self.assertEqual(self.fixture.errors, [])

    def test_unrelated_package_creation_handler_is_unchanged_when_enabled(self):
        self.assertEqual(self.request(path='/reseller/*/addWeb', raw=b'{"domain_name":"new.invalid","type":1}'), (200, b'{"result": 100}'))
        self.assertEqual(self.fixture.packages[0]['name'], 'new.invalid')
        self.assertEqual(self.fixture.nameserver_change_posts, [])
        self.assertEqual(self.fixture.errors, [])

    def test_unrelated_mailbox_handler_is_unchanged_when_enabled(self):
        raw = b'{"new":{"mailbox":{"local":"synthetic","send":"true","receive":"true","password":"synthetic-only"}}}'
        status, body = self.request(path='/package/101/email/mail.invalid', raw=raw)
        self.assertEqual(status, 200)
        self.assertEqual(json.loads(body)['result']['result'][0]['id'], 'synthetic')
        self.assertEqual(self.fixture.posts[0]['body'], {'mailbox_local': 'synthetic'})
        self.assertEqual(self.fixture.nameserver_change_posts, [])
        self.assertEqual(self.fixture.errors, [])


BODY_REFUSALS = {
    'invalid_json': b'not json', 'invalid_utf8': b'\xff', 'empty': b'',
    'array': b'[]', 'scalar': b'42', 'null': b'null',
    'extra_field': b'{"ns":["ns.new.invalid"],"old-ns":["ns.old.invalid"],"domainId":14701}',
    'duplicate_key': wire()[:-1]+b',"ns":["ns.hidden.invalid"]}',
    'reversed_keys': json.dumps({'old-ns': OLD, 'ns': NEW}, separators=(',', ':')).encode(),
    'noncanonical_whitespace': json.dumps({'ns': NEW, 'old-ns': OLD}).encode(),
    'escaped_hostname': wire().replace(b'ns1.', b'ns\\u0031.', 1),
    'empty_desired': wire([]), 'empty_old': wire(NEW, []),
    'duplicate_desired': wire([NEW[0], NEW[0]]),
    'duplicate_old': wire(NEW, [OLD[0], OLD[0]]),
    'overlap': wire([NEW[0], OLD[0]]), 'noop': wire(OLD),
    'incomplete_old': wire(NEW, [OLD[0]]),
    'different_old': wire(NEW, ['ns.other.invalid']),
    'unsorted_desired': wire(list(reversed(NEW))),
    'unsorted_old': wire(NEW, list(reversed(OLD))),
    'uppercase': wire(['NS1.NEW.INVALID']), 'trailing_dot': wire(['ns.new.invalid.']),
    'ip_address': wire(['192.0.2.1']), 'url': wire(['https://ns.new.invalid']),
    'port': wire(['ns.new.invalid:53']), 'path': wire(['ns.new.invalid/path']),
    'single_label': wire(['localhost']), 'non_string': wire([False]),
    'control': wire(['ns.new.invalid\n']), 'long_label': wire(['a'*64+'.invalid']),
    'long_hostname': wire(['.'.join(['a'*63]*4)]),
    'too_many_names': wire(sorted('ns'+str(i)+'.invalid' for i in range(65))),
    'oversized_body': wire(sorted(str(i)+'.'+'.'.join(['a'*63]*3) for i in range(45))),
}


def body_case(raw):
    def test(self):
        self.refused(raw=raw)
    return test


for label, raw in BODY_REFUSALS.items():
    setattr(HandlerTests, 'test_body_refuses_'+label, body_case(raw))


HEADER_REFUSALS = {
    'missing_authorization': {'Authorization': None},
    'wrong_authorization': {'Authorization': 'Bearer wrong-synthetic-key'},
    'missing_accept': {'Accept': None}, 'wrong_accept': {'Accept': 'text/html'},
    'missing_content_type': {'Content-Type': None},
    'wrong_content_type': {'Content-Type': 'application/json; charset=utf-8'},
    'missing_length': {'Content-Length': None}, 'negative_length': {'Content-Length': '-1'},
    'invalid_length': {'Content-Length': 'no'}, 'oversized_length': {'Content-Length': '8193'},
    'leading_zero_length': {'Content-Length': '0'+str(len(wire()))},
    'transfer_encoding': {'Transfer-Encoding': 'chunked'},
}


def header_case(changes):
    def test(self):
        self.refused(changes=changes)
    return test


for label, changes in HEADER_REFUSALS.items():
    setattr(HandlerTests, 'test_header_refuses_'+label, header_case(changes))


def duplicate_header_case(key, value):
    def test(self):
        self.refused(extra=[(key, value)])
    return test


for key, value in {'Authorization': AUTH, 'Accept': 'application/json', 'Content-Type': 'application/json', 'Content-Length': str(len(wire()))}.items():
    setattr(HandlerTests, 'test_duplicate_header_refuses_'+key.lower().replace('-', '_'), duplicate_header_case(key, value))


def route_case(path, matched):
    def test(self):
        if matched:
            self.refused(path=path)
        else:
            self.request(path=path)
            self.assertEqual(self.fixture.nameserver_change_posts, [])
            self.assertEqual(self.fixture.nameservers['14701'], OLD)
            self.assertEqual(self.fixture.errors, ['Unexpected write request '+path])
    return test


for label, path, matched in (
        ('other_existing_id', '/domain/14702/nameservers', True),
        ('other_missing_id', '/domain/99999/nameservers', True),
        ('name_not_id', '/domain/domain.invalid/nameservers', False),
        ('package_route', '/package/14701/domain/domain.invalid/nameservers', False),
        ('zero_id', '/domain/0/nameservers', False),
        ('leading_zero_id', '/domain/014701/nameservers', False),
        ('long_id', '/domain/1234567890123456789/nameservers', False),
        ('query', PATH+'?domainId=14701', False),
        ('trailing_slash', PATH+'/', False),
        ('encoded_id', '/domain/%31%34%37%30%31/nameservers', False)):
    setattr(HandlerTests, 'test_route_'+label, route_case(path, matched))


class HelperTests(unittest.TestCase):
    def test_disabled_helper_does_not_inspect_headers_or_body(self):
        fixture = SimpleNamespace(nameserver_change_mode='disabled')
        self.assertIsNone(registered_nameserver_change_reply(fixture, PATH, None, object()))

    def test_unrelated_helper_does_not_inspect_headers_or_body(self):
        fixture = SimpleNamespace(nameserver_change_mode='uncertain_unlanded')
        self.assertIsNone(registered_nameserver_change_reply(fixture, '/package', None, object()))

    def test_length_mismatch_refuses_without_retaining_body(self):
        fixture = SimpleNamespace(nameserver_change_mode='uncertain_unlanded', nameservers={'14701': OLD}, errors=[], posts=[], nameserver_change_posts=[])
        headers = Message()
        for key, value in {'Authorization': AUTH, 'Accept': 'application/json', 'Content-Type': 'application/json', 'Content-Length': str(len(wire())+1)}.items():
            headers[key] = value
        self.assertEqual(registered_nameserver_change_reply(fixture, PATH, headers, wire())[0], 400)
        self.assertEqual(fixture.posts, [])
        self.assertEqual(fixture.nameserver_change_posts, [])


class Results(unittest.TextTestResult):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs); self.checks = []
    def addSuccess(self, test):
        super().addSuccess(test); self.checks.append({'name': test.id(), 'passed': True})
    def addFailure(self, test, err):
        super().addFailure(test, err); self.checks.append({'name': test.id(), 'passed': False})
    def addError(self, test, err):
        super().addError(test, err); self.checks.append({'name': test.id(), 'passed': False})


if __name__ == '__main__':
    parser = argparse.ArgumentParser(); parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    if args.report and (args.report.exists() or args.report.is_symlink()):
        raise SystemExit('Refusing existing report path')
    suite = unittest.TestSuite(unittest.defaultTestLoader.loadTestsFromTestCase(case) for case in (HandlerTests, HelperTests))
    result = unittest.TextTestRunner(verbosity=2, resultclass=Results).run(suite)
    if args.report:
        with args.report.open('x', encoding='utf-8') as output:
            json.dump({'passed': result.wasSuccessful(), 'scope': 'Portable actual loopback TLS Handler and helper checks only; no PHP/SQL/application/provider acceptance', 'checks': result.checks}, output, indent=2)
    raise SystemExit(0 if result.wasSuccessful() else 1)
