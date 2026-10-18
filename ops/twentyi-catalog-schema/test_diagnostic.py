"""Offline adversarial checks. All HTTP is fake; DNS/socket creation is forbidden."""
import base64
import contextlib
import email.message
import importlib.util
import io
import json
import os
from pathlib import Path
import ssl
import tempfile
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location('catalog_shape', Path(__file__).with_name('diagnostic.py'))
D = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(D)
KEY = 'synthetic-general-key-CANARY'
PRIVATE = 'CUSTOMER_VALUE_CANARY@example.invalid'
DYNAMIC = 'PRIVATE_DYNAMIC_KEY_CANARY'


class Response:
    def __init__(self, raw=b'[]', status=200, headers=None):
        self.raw = raw
        self.status = status
        self.headers = email.message.Message()
        for name, value in (headers if headers is not None else [('Content-Type', 'application/json')]):
            self.headers[name] = value
        self.reads = []

    def read(self, limit):
        self.reads.append(limit)
        return self.raw[:limit]


class Connection:
    def __init__(self, response):
        self.response = response
        self.requests = []
        self.closed = False

    def request(self, method, path, body=None, headers=None):
        self.requests.append((method, path, body, headers))

    def getresponse(self):
        if isinstance(self.response, Exception):
            raise self.response
        return self.response

    def close(self):
        self.closed = True


class DiagnosticTests(unittest.TestCase):
    def setUp(self):
        # A missing transport mock must fail, never resolve/contact a host.
        self.addCleanup(patch.stopall)
        patch('socket.create_connection', side_effect=AssertionError('Offline test forbids sockets')).start()
        patch('socket.getaddrinfo', side_effect=AssertionError('Offline test forbids DNS')).start()

    def run_fake(self, *responses, key=KEY):
        connections = [Connection(response) for response in responses]
        with patch.object(D.http.client, 'HTTPSConnection', side_effect=connections) as factory:
            result = D.collect(key)
        return result, connections, factory

    def assert_refusal(self, code, raw, operation='package_types'):
        with self.assertRaises(D.Refusal) as caught:
            D.shape(raw, operation)
        self.assertEqual(code, D.finite_error(caught.exception))

    def test_two_exact_gets_no_body_cookies_proxy_or_arbitrary_headers(self):
        with patch.dict(os.environ, {'HTTPS_PROXY': 'http://proxy.invalid', 'HTTP_PROXY': 'http://proxy.invalid'}):
            result, connections, factory = self.run_fake(Response(), Response(b'{}'))
        self.assertEqual('complete', result['outcome'])
        self.assertEqual(2, factory.call_count)
        for (name, path), connection, call in zip(D.OPERATIONS, connections, factory.call_args_list):
            self.assertEqual(('api.20i.com', 443), call.args)
            self.assertEqual(10, call.kwargs['timeout'])
            context = call.kwargs['context']
            self.assertTrue(context.check_hostname)
            self.assertEqual(ssl.CERT_REQUIRED, context.verify_mode)
            self.assertEqual(ssl.TLSVersion.TLSv1_2, context.minimum_version)
            self.assertIsNone(context.keylog_filename)
            self.assertEqual(1, len(connection.requests))
            method, actual_path, body, headers = connection.requests[0]
            self.assertEqual(('GET', path, None), (method, actual_path, body))
            self.assertEqual({'Authorization', 'Accept', 'Accept-Encoding', 'Connection', 'User-Agent'}, set(headers))
            self.assertEqual('Bearer ' + base64.b64encode(KEY.encode()).decode(), headers['Authorization'])
            self.assertEqual('identity', headers['Accept-Encoding'])
            self.assertTrue(connection.closed)
        self.assertNotIn(KEY, json.dumps(result))

    def test_tls_keylog_environment_is_not_honored(self):
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory) / 'must-not-exist'
            with patch.dict(os.environ, {'SSLKEYLOGFILE': str(target)}):
                result, connections, factory = self.run_fake(Response(), Response(b'{}'))
            self.assertEqual('complete', result['outcome'])
            self.assertFalse(target.exists())
            self.assertIsNone(factory.call_args.kwargs['context'].keylog_filename)

    def test_unknown_operation_refused_before_transport(self):
        with patch.object(D.http.client, 'HTTPSConnection') as factory:
            with self.assertRaises(D.Refusal):
                D.fetch_shape('https://evil.invalid/private', KEY)
        factory.assert_not_called()

    def test_missing_and_invalid_secrets_make_no_requests(self):
        for key in ('', 'with space', 'line\nbreak', '\u00e9', '\x7f', 'x' * 16385, 123):
            with self.subTest(case=type(key).__name__):
                result, _connections, factory = self.run_fake(key=key)
                self.assertIn(result['outcome'], ('missing_secret', 'invalid_secret'))
                self.assertTrue(all(row['outcome'] == 'not_attempted' for row in result['operations']))
                factory.assert_not_called()

    def test_redirect_refused_without_read_or_followup_or_credential_forwarding(self):
        for status in (301, 302, 303, 307, 308):
            response = Response(PRIVATE.encode(), status, [('Location', 'https://evil.invalid/' + PRIVATE)])
            result, connections, factory = self.run_fake(response, Response(b'{}'))
            self.assertEqual('redirect_refused', result['operations'][0]['outcome'])
            self.assertEqual([], response.reads)
            self.assertEqual(2, factory.call_count)  # The distinct allowed second operation, no redirect.
            self.assertEqual([D.OPERATIONS[0][1], D.OPERATIONS[1][1]], [c.requests[0][1] for c in connections])
            self.assertNotIn(PRIVATE, json.dumps(result))

    def test_http_failures_never_read_body_or_retry(self):
        for status, code in ((401, 'http_401'), (403, 'http_403'), (404, 'http_404'),
                             (429, 'http_429'), (500, 'http_failure'), (204, 'http_failure')):
            response = Response((KEY + PRIVATE).encode(), status)
            result, connections, factory = self.run_fake(response, Response(b'{}'))
            self.assertEqual(code, result['operations'][0]['outcome'])
            self.assertEqual([], response.reads)
            self.assertEqual(2, factory.call_count)
            self.assertNotIn(PRIVATE, json.dumps(result))

    def test_network_exception_details_not_disclosed(self):
        result, connections, factory = self.run_fake(OSError(KEY + PRIVATE), Response(b'{}'))
        self.assertEqual('network_or_tls_failure', result['operations'][0]['outcome'])
        self.assertEqual(2, factory.call_count)
        self.assertNotIn(PRIVATE, json.dumps(result))
        self.assertTrue(all(c.closed for c in connections))

    def test_unexpected_exception_details_not_disclosed(self):
        result, _connections, _factory = self.run_fake(RuntimeError(KEY + PRIVATE), Response(b'{}'))
        self.assertEqual('internal_failure', result['operations'][0]['outcome'])
        self.assertNotIn(KEY, json.dumps(result))

    def test_deadline_stops_remaining_operation(self):
        result, connections, factory = self.run_fake(D.Refusal('deadline'))
        self.assertEqual(['deadline', 'not_attempted'], [row['outcome'] for row in result['operations']])
        self.assertEqual(1, factory.call_count)
        self.assertTrue(connections[0].closed)

    def test_content_type_and_charset_refused(self):
        for value in ('text/html', 'application/json; charset=latin1', 'application/json; private=' + PRIVATE, ''):
            response = Response(headers=[('Content-Type', value)])
            result, _connections, _factory = self.run_fake(response, Response(b'{}'))
            self.assertEqual('content_type', result['operations'][0]['outcome'])
            self.assertEqual([], response.reads)

    def test_utf8_content_type_accepted(self):
        for value in ('application/json', 'application/json; charset=UTF-8', 'application/json; charset="utf-8"'):
            result, _connections, _factory = self.run_fake(Response(headers=[('Content-Type', value)]), Response(b'{}'))
            self.assertEqual('complete', result['outcome'])

    def test_duplicate_relevant_headers_refused(self):
        for name, value in (('Content-Type', 'application/json'), ('Content-Length', '2'), ('Content-Encoding', 'identity'), ('Transfer-Encoding', 'chunked')):
            headers = [('Content-Type', 'application/json')] if name != 'Content-Type' else []
            response = Response(headers=headers + [(name, value), (name, value)])
            result, _connections, _factory = self.run_fake(response, Response(b'{}'))
            self.assertEqual('invalid_headers', result['operations'][0]['outcome'])
            self.assertEqual([], response.reads)

    def test_compression_refused_without_decompression(self):
        response = Response(headers=[('Content-Type', 'application/json'), ('Content-Encoding', 'gzip')])
        result, _connections, _factory = self.run_fake(response, Response(b'{}'))
        self.assertEqual('content_encoding', result['operations'][0]['outcome'])
        self.assertEqual([], response.reads)

    def test_declared_large_body_refused_without_read(self):
        response = Response(headers=[('Content-Type', 'application/json'), ('Content-Length', str(D.MAX_BODY + 1))])
        result, _connections, _factory = self.run_fake(response, Response(b'{}'))
        self.assertEqual('body_limit', result['operations'][0]['outcome'])
        self.assertEqual([], response.reads)

    def test_invalid_length_and_large_header_refused(self):
        for value in ('-1', '+2', '2,2', '\u0662', 'x' * 1025):
            response = Response(headers=[('Content-Type', 'application/json'), ('Content-Length', value)])
            result, _connections, _factory = self.run_fake(response, Response(b'{}'))
            self.assertEqual('invalid_headers', result['operations'][0]['outcome'])

    def test_actual_body_must_equal_declared_length(self):
        for length in ('1', '3'):
            response = Response(b'[]', headers=[('Content-Type', 'application/json'), ('Content-Length', length)])
            result, _connections, _factory = self.run_fake(response, Response(b'{}'))
            self.assertEqual('body_length_mismatch', result['operations'][0]['outcome'])
            self.assertNotIn('shape', result['operations'][0])

    def test_conflicting_or_unsupported_transfer_framing_refused(self):
        for framing in ([('Transfer-Encoding', 'chunked'), ('Content-Length', '2')],
                        [('Transfer-Encoding', 'gzip')], [('Transfer-Encoding', 'identity')]):
            response = Response(headers=[('Content-Type', 'application/json')] + framing)
            result, _connections, _factory = self.run_fake(response, Response(b'{}'))
            self.assertEqual('invalid_headers', result['operations'][0]['outcome'])
            self.assertEqual([], response.reads)

    def test_bounded_chunked_response_shape_is_accepted(self):
        response = Response(headers=[('Content-Type', 'application/json'), ('Transfer-Encoding', 'chunked')])
        result, _connections, _factory = self.run_fake(response, Response(b'{}'))
        self.assertEqual('complete', result['outcome'])
        self.assertEqual([D.MAX_BODY + 1], response.reads)

    def test_stream_body_cap_without_content_length(self):
        response = Response(b' ' * (D.MAX_BODY + 2))
        result, _connections, _factory = self.run_fake(response, Response(b'{}'))
        self.assertEqual('body_limit', result['operations'][0]['outcome'])
        self.assertEqual([D.MAX_BODY + 1], response.reads)

    def test_duplicate_json_keys_refused_even_when_private(self):
        self.assert_refusal('invalid_json', b'[{"secret":1,"secret":2}]')

    def test_nonfinite_numbers_and_overflow_refused(self):
        for value in ('NaN', 'Infinity', '-Infinity', '1e9999'):
            self.assert_refusal('invalid_json', ('[{"label":' + value + '}]').encode())

    def test_strict_utf8_and_escaped_surrogates_refused(self):
        for raw in (b'[{"label":"\xff"}]', b'[{"label":"\\ud800"}]', b'[{"\\udfff":"x"}]', b'\xef\xbb\xbf[]'):
            self.assert_refusal('invalid_json', raw)

    def test_valid_unicode_scalar_values_never_emitted(self):
        result = D.shape(json.dumps([{'label': '\U0001f642\u00e9'}]).encode(), 'package_types')
        self.assertEqual(['string'], result['observations'][-1]['types'])
        self.assertNotIn('\\u', json.dumps(result))

    def test_malformed_json_refused(self):
        for raw in (b'[{', b'[] trailing', b'not JSON', b''):
            self.assert_refusal('invalid_json', raw)

    def test_root_shapes_are_restricted(self):
        for raw in (b'{}', b'["private"]', b'[1]', b'null'):
            self.assert_refusal('unexpected_root', raw)
        for raw in (b'1', b'null', b'"private"'):
            self.assert_refusal('unexpected_root', raw, 'cloud_server_product_data')

    def test_depth_limit_refused_not_truncated(self):
        self.assert_refusal('shape_limit', b'[{"data":' + b'[' * 13 + b'0' + b']' * 13 + b'}]')

    def test_member_work_limit_refused_before_queue_growth(self):
        with patch.object(D, 'MAX_NODES', 8):
            self.assert_refusal('shape_limit', b'[{"label":[0,0,0,0,0,0,0,0,0]}]')
            self.assert_refusal('shape_limit', json.dumps([{str(i): 0 for i in range(9)}]).encode())

    def test_observation_and_output_limits_refused(self):
        with patch.object(D, 'MAX_OBSERVATIONS', 2):
            self.assert_refusal('shape_limit', b'[{"label":"value"}]')
        with patch.object(D, 'MAX_SHAPE_BYTES', 10):
            self.assert_refusal('shape_limit', b'[]')

    def test_values_ids_counts_urls_and_unknown_key_names_absent(self):
        payload = [{DYNAMIC: {PRIVATE: {'imageUrl': 'https://private.invalid/' + KEY}},
                    'label': PRIVATE, 'platform': 987654321, 'limit': [KEY, KEY],
                    'extraData': {'temporaryUrlDomain': 'private.invalid'}}]
        result = D.shape(json.dumps(payload).encode(), 'package_types')
        encoded = json.dumps(result)
        for forbidden in (DYNAMIC, PRIVATE, KEY, '987654321', 'https:', 'private.invalid'):
            self.assertNotIn(forbidden, encoded)
        self.assertTrue(result['unknown_or_dynamic_keys_withheld'])
        self.assertFalse(result['image_contract_established'])
        self.assertIn(['$', '[]', '{withheld-key}', '{withheld-key}', 'imageUrl'], [r['path_tokens'] for r in result['observations']])
        allowed = {'$', '[]', '{withheld-key}', *D.REFERENCE_NAMES, *D.CATALOG_LEXEMES, *D.IMAGE_LEXEMES}
        self.assertTrue(all(set(row['path_tokens']) <= allowed and set(row['types']) <= set(D.TYPES) for row in result['observations']))

    def test_raw_dynamic_keys_are_not_hashed_or_disclosed(self):
        a = D.shape(b'[{"customer-A":{"image":"URL-A"}}]', 'package_types')
        b = D.shape(b'[{"customer-B":{"image":"URL-B"}}]', 'package_types')
        self.assertEqual(a, b)

    def test_scalar_values_and_collection_lengths_do_not_change_shape(self):
        a = D.shape(b'[{"label":"A","limit":[1]}]', 'package_types')
        b = D.shape(b'[{"label":"B","limit":[999,456,789]}]', 'package_types')
        self.assertEqual(a, b)

    def test_bool_integer_number_and_null_remain_distinct(self):
        result = D.shape(b'[{"label":[true,1,1.5,null]}]', 'package_types')
        row = next(row for row in result['observations'] if row['path_tokens'][-2:] == ['label', '[]'])
        self.assertEqual(['boolean', 'integer', 'null', 'number'], row['types'])

    def test_image_lexeme_presence_never_claims_image_support(self):
        for value in ('https://example.invalid/image', 'no', 0, False, None, [], {}):
            result = D.shape(json.dumps([{'image': value}]).encode(), 'package_types')
            self.assertFalse(result['image_contract_established'])
            self.assertEqual('lexical_matches_only_unknown_key_paths_collapsed', result['semantics'])

    def test_catalog_lexemes_expose_types_not_values_or_contracts(self):
        payload = {'products': [{name: PRIVATE for name in D.CATALOG_LEXEMES}]}
        payload['products'][0]['id'] = 9988776655
        payload['products'][0]['specifications'] = {DYNAMIC: PRIVATE}
        result = D.shape(json.dumps(payload).encode(), 'cloud_server_product_data')
        encoded = json.dumps(result)
        self.assertNotIn(PRIVATE, encoded)
        self.assertNotIn(DYNAMIC, encoded)
        self.assertNotIn('9988776655', encoded)
        self.assertFalse(result['catalog_contract_established'])
        self.assertFalse(result['image_contract_established'])
        self.assertTrue(result['unknown_or_dynamic_keys_withheld'])
        self.assertIn({'path_tokens': ['$', 'products', '[]', 'id'], 'types': ['integer']}, result['observations'])
        self.assertIn({'path_tokens': ['$', 'products', '[]', 'name'], 'types': ['string']}, result['observations'])

    def test_suspicious_key_spellings_are_withheld_exactly(self):
        result = D.shape(json.dumps([{'image\n' + PRIVATE: KEY, 'Image': KEY, 'im\u0430ge': KEY}]).encode(), 'package_types')
        self.assertEqual([['$'], ['$', '[]'], ['$', '[]', '{withheld-key}']], [r['path_tokens'] for r in result['observations']])

    def test_error_allowlist_cannot_echo_exception_args(self):
        for error in (D.Refusal(PRIVATE), D.Refusal('http_401', PRIVATE), ValueError('http_401'), RuntimeError(PRIVATE)):
            self.assertEqual('internal_failure', D.finite_error(error))

    def invoke_main(self, directory, result, argv=None):
        with contextlib.chdir(directory), patch.dict(os.environ, {'TWENTYI_API_KEY': KEY}), \
                patch.object(D.sys, 'argv', argv or ['diagnostic.py']), \
                patch.object(D.signal, 'SIGALRM', 14, create=True), \
                patch.object(D.signal, 'signal'), patch.object(D.signal, 'alarm', create=True) as alarm, \
                patch.object(D, 'collect', return_value=result) as collect, \
                contextlib.redirect_stdout(io.StringIO()) as output:
            status = D.main()
            self.assertNotIn('TWENTYI_API_KEY', os.environ)
        return status, output.getvalue(), collect, alarm

    def test_main_pops_secret_sets_total_deadline_and_writes_only_shape(self):
        result, _connections, _factory = self.run_fake(Response(), Response(b'{}'))
        with tempfile.TemporaryDirectory() as directory:
            status, output, collect, alarm = self.invoke_main(directory, result)
            target = Path(directory, D.OUTPUT_DIRECTORY, D.OUTPUT_FILE)
            self.assertEqual(result, json.loads(target.read_text(encoding='ascii')))
            self.assertEqual(0, status)
            self.assertEqual('Catalog shape diagnostic: complete\n', output)
            self.assertEqual([(35,), (0,)], [c.args for c in alarm.call_args_list])
            collect.assert_called_once_with(KEY)

    def test_main_no_overwrite_and_no_exception_output(self):
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory, D.OUTPUT_DIRECTORY)
            target.mkdir()
            original = target / D.OUTPUT_FILE
            original.write_bytes(b'original')
            status, output, _collect, _alarm = self.invoke_main(directory, {'schema': 1, 'outcome': 'complete'})
            self.assertEqual(1, status)
            self.assertEqual(b'original', original.read_bytes())
            self.assertEqual('Catalog shape diagnostic: output_failure\n', output)

    def test_cli_arguments_refused_without_collect(self):
        with tempfile.TemporaryDirectory() as directory:
            status, output, collect, _alarm = self.invoke_main(directory, {}, ['diagnostic.py', 'https://evil.invalid'])
            self.assertEqual(1, status)
            collect.assert_not_called()
            self.assertNotIn('evil', output)


if __name__ == '__main__':
    unittest.main(verbosity=2)
