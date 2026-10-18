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

SPEC = importlib.util.spec_from_file_location('php_versions_shape', Path(__file__).with_name('diagnostic.py'))
D = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(D)
KEY = 'synthetic-general-key-CANARY'
PRIVATE = 'CUSTOMER_VALUE_CANARY@example.invalid'
DYNAMIC = 'PRIVATE_DYNAMIC_KEY_CANARY'


class Response:
    def __init__(self, raw=b'[{"id":92731}]', status=200, headers=None):
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

    def assert_refusal(self, code, raw, operation='available_php_versions'):
        with self.assertRaises(D.Refusal) as caught:
            D.shape(raw, operation)
        self.assertEqual(code, D.finite_error(caught.exception))

    def test_two_exact_gets_no_body_cookies_proxy_or_arbitrary_headers(self):
        with patch.dict(os.environ, {'HTTPS_PROXY': 'http://proxy.invalid', 'HTTP_PROXY': 'http://proxy.invalid'}):
            result, connections, factory = self.run_fake(Response(), Response(b'{}'))
        self.assertEqual('complete', result['outcome'])
        self.assertEqual(2, factory.call_count)
        for path, connection, call in zip(('/package', '/package/92731/web/availablePhpVersions'), connections, factory.call_args_list):
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
            self.assertEqual(1, factory.call_count)  # Selection failure stops; no second operation.
            self.assertEqual('/package', connections[0].requests[0][1])
            self.assertEqual([], connections[1].requests)
            self.assertNotIn(PRIVATE, json.dumps(result))

    def test_http_failures_never_read_body_or_retry(self):
        for status, code in ((401, 'http_401'), (403, 'http_403'), (404, 'http_404'),
                             (429, 'http_429'), (500, 'http_failure'), (204, 'http_failure')):
            response = Response((KEY + PRIVATE).encode(), status)
            result, connections, factory = self.run_fake(response, Response(b'{}'))
            self.assertEqual(code, result['operations'][0]['outcome'])
            self.assertEqual([], response.reads)
            self.assertEqual(1, factory.call_count)
            self.assertNotIn(PRIVATE, json.dumps(result))

    def test_network_exception_details_not_disclosed(self):
        result, connections, factory = self.run_fake(OSError(KEY + PRIVATE), Response(b'{}'))
        self.assertEqual('network_or_tls_failure', result['operations'][0]['outcome'])
        self.assertEqual(1, factory.call_count)
        self.assertNotIn(PRIVATE, json.dumps(result))
        self.assertTrue(connections[0].closed)
        self.assertFalse(connections[1].closed)

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
        result = D.shape(json.dumps([{'label': '\U0001f642\u00e9'}]).encode(), 'available_php_versions')
        self.assertEqual(['string'], result['observations'][-1]['types'])
        self.assertNotIn('\\u', json.dumps(result))

    def test_malformed_json_refused(self):
        for raw in (b'[{', b'[] trailing', b'not JSON', b''):
            self.assert_refusal('invalid_json', raw)

    def test_available_root_shapes_preserve_all_json_kinds_without_semantic_claim(self):
        for value, kind in ((None, 'null'), (True, 'boolean'), (19, 'integer'),
                            (1.5, 'number'), (PRIVATE, 'string'), ([], 'array'), ({}, 'object')):
            result = D.shape(json.dumps(value).encode())
            self.assertEqual(kind, result['root_type'])
            self.assertFalse(result['supported_versions_or_handler_contract_established'])
            self.assertNotIn(PRIVATE, json.dumps(result))

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
        payload = [{DYNAMIC: {PRIVATE: {'handler': 'https://private.invalid/' + KEY}},
                    'label': PRIVATE, 'platform': 987654321, 'limit': [KEY, KEY],
                    'extraData': {'temporaryUrlDomain': 'private.invalid'}}]
        result = D.shape(json.dumps(payload).encode(), 'available_php_versions')
        encoded = json.dumps(result)
        for forbidden in (DYNAMIC, PRIVATE, KEY, '987654321', 'https:', 'private.invalid'):
            self.assertNotIn(forbidden, encoded)
        self.assertTrue(result['unknown_or_dynamic_keys_withheld'])
        self.assertFalse(result['supported_versions_or_handler_contract_established'])
        self.assertIn(['$', '[]', '{withheld-key}', '{withheld-key}', 'handler'], [r['path_tokens'] for r in result['observations']])
        allowed = {'$', '[]', '{withheld-key}', *D.PHP_LEXEMES, *D.PUBLIC_PHP_TOKENS}
        self.assertTrue(all(set(row['path_tokens']) <= allowed and set(row['types']) <= set(D.TYPES) for row in result['observations']))

    def test_raw_dynamic_keys_are_not_hashed_or_disclosed(self):
        a = D.shape(b'[{"customer-A":{"image":"URL-A"}}]', 'available_php_versions')
        b = D.shape(b'[{"customer-B":{"image":"URL-B"}}]', 'available_php_versions')
        self.assertEqual(a, b)

    def test_scalar_values_and_collection_lengths_do_not_change_shape(self):
        a = D.shape(b'[{"label":"A","limit":[1]}]', 'available_php_versions')
        b = D.shape(b'[{"label":"B","limit":[999,456,789]}]', 'available_php_versions')
        self.assertEqual(a, b)

    def test_bool_integer_number_and_null_remain_distinct(self):
        result = D.shape(b'[{"label":[true,1,1.5,null]}]', 'available_php_versions')
        row = next(row for row in result['observations'] if row['path_tokens'][-2:] == ['label', '[]'])
        self.assertEqual(['boolean', 'integer', 'null', 'number'], row['types'])

    def test_php_lexemes_expose_only_types_and_allowlisted_tokens(self):
        payload = {name: PRIVATE for name in D.PHP_LEXEMES}
        payload['items'] = [{DYNAMIC: PRIVATE, 'value': 'php82', 'handler': 'NOT_PUBLIC_HANDLER'}]
        result = D.shape(json.dumps(payload).encode())
        encoded = json.dumps(result)
        for forbidden in (PRIVATE, DYNAMIC, 'NOT_PUBLIC_HANDLER'):
            self.assertNotIn(forbidden, encoded)
        self.assertFalse(result['supported_versions_or_handler_contract_established'])
        self.assertFalse(result['row_associations_preserved'])
        self.assertTrue(result['unknown_or_dynamic_keys_withheld'])
        self.assertIn({'path_tokens': ['$', 'items', '[]', 'value'], 'token': 'php82'}, result['public_php_token_matches'])

    def test_suspicious_key_spellings_are_withheld_exactly(self):
        result = D.shape(json.dumps([{'image\n' + PRIVATE: KEY, 'Image': KEY, 'im\u0430ge': KEY}]).encode(), 'available_php_versions')
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
            self.assertEqual('PHP versions diagnostic: complete\n', output)
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
            self.assertEqual('PHP versions diagnostic: output_failure\n', output)

    def test_cli_arguments_refused_without_collect(self):
        with tempfile.TemporaryDirectory() as directory:
            status, output, collect, _alarm = self.invoke_main(directory, {}, ['diagnostic.py', 'https://evil.invalid'])
            self.assertEqual(1, status)
            collect.assert_not_called()
            self.assertNotIn('evil', output)

    def test_selection_uses_smallest_numeric_observed_id_not_order_or_name(self):
        payload = [{'id': '100', 'name': PRIVATE}, {'id': 20, 'name': KEY}, {'id': '92731'}]
        for items in (payload, list(reversed(payload))):
            result, connections, factory = self.run_fake(Response(json.dumps(items).encode()), Response(b'{"version":"php82"}'))
            self.assertEqual('complete', result['outcome'])
            self.assertEqual('/package/20/web/availablePhpVersions', connections[1].requests[0][1])
            self.assertEqual(2, factory.call_count)
            encoded = json.dumps(result)
            for forbidden in ('92731', '100', '20', PRIVATE, KEY):
                self.assertNotIn(forbidden, encoded)
            self.assertEqual({'operation': 'package_selection', 'outcome': 'selected_in_memory'}, result['operations'][0])

    def test_invalid_or_duplicate_selection_stops_without_second_get(self):
        cases = [(b'[]', 'no_package'), (b'null', 'unexpected_root'), (b'{}', 'unexpected_root'),
                 (b'[1]', 'unexpected_root'), (b'[{}]', 'invalid_package_identity'),
                 (b'[{"id":7},{"id":"7"}]', 'duplicate_package_identity')]
        for value in (None, True, False, 0, -1, 1.5, 10**18, '', '0', '01', '1/../../web',
                      ' 1', '1 ', '+1', '1.0', '1e1', '\u0661', '1'*19, [], {}):
            cases.append((json.dumps([{'id': value}]).encode(), 'invalid_package_identity'))
        for raw, expected in cases:
            with self.subTest(expected=expected, raw=raw):
                result, connections, factory = self.run_fake(Response(raw), Response())
                self.assertEqual(expected, result['operations'][0]['outcome'])
                self.assertEqual('not_attempted', result['operations'][1]['outcome'])
                self.assertEqual(1, factory.call_count)
                self.assertEqual([], connections[1].requests)

    def test_selection_overflow_and_unselected_private_json_fail_closed(self):
        for raw, expected in ((b'[{"id":9,"private":1e9999}]', 'invalid_json'),
                              (b'[{"id":9,"private":"\\ud800"}]', 'invalid_json'),
                              (b'[{"id":9,"private":1,"private":2}]', 'invalid_json')):
            result, _connections, factory = self.run_fake(Response(raw))
            self.assertEqual(expected, result['operations'][0]['outcome'])
            self.assertEqual(1, factory.call_count)
        with patch.object(D, 'MAX_PACKAGES', 1):
            result, _connections, factory = self.run_fake(Response(b'[{"id":1},{"id":2}]'))
            self.assertEqual('package_limit', result['operations'][0]['outcome'])
            self.assertEqual(1, factory.call_count)
        with patch.object(D, 'MAX_NODES', 3):
            result, _connections, _factory = self.run_fake(Response(b'[{"id":1,"private":[1,2]}]'))
            self.assertEqual('shape_limit', result['operations'][0]['outcome'])
        deep = b'[{"id":1,"private":' + b'[' * 13 + b'0' + b']' * 13 + b'}]'
        result, _connections, _factory = self.run_fake(Response(deep))
        self.assertEqual('shape_limit', result['operations'][0]['outcome'])

    def test_identity_bounds_and_no_arbitrary_endpoint(self):
        self.assertEqual('999999999999999999', D.select_package(b'[{"id":999999999999999999}]'))
        for operation, selected in (('POST', '1'), ('phpVersion', '1'), ('available_php_versions', None),
                                    ('available_php_versions', '../'), ('package_selection', '1')):
            with patch.object(D.http.client, 'HTTPSConnection') as factory:
                with self.assertRaises(D.Refusal):
                    D.fetch_shape(operation, KEY, selected)
                factory.assert_not_called()

    def test_second_failure_never_uses_another_package_or_retries(self):
        for response in (Response(status=301), Response(status=404), Response(status=429),
                         OSError(PRIVATE), D.Refusal('deadline'), Response(b'not-json')):
            result, connections, factory = self.run_fake(Response(b'[{"id":12},{"id":13}]'), response)
            self.assertEqual('incomplete', result['outcome'])
            self.assertEqual('selected_in_memory', result['operations'][0]['outcome'])
            self.assertNotEqual('shape_observed', result['operations'][1]['outcome'])
            self.assertEqual(2, factory.call_count)
            self.assertEqual('/package/12/web/availablePhpVersions', connections[1].requests[0][1])
            self.assertNotIn(PRIVATE, json.dumps(result))

    def test_closed_php_constants_exact_in_keys_and_values(self):
        expected = tuple(token for major, minors in ((5, range(3, 7)), (7, range(5)), (8, range(6)))
                         for minor in minors for token in (f'php{major}{minor}', f'{major}.{minor}', f'PHP {major}.{minor}'))
        self.assertEqual(expected, D.PUBLIC_PHP_TOKENS)
        self.assertEqual(45, len(set(expected)))
        for token in expected:
            result = D.shape(json.dumps({token: {'value': token, 'handler': PRIVATE}}).encode())
            self.assertIn({'path_tokens': ['$', token, 'value'], 'token': token}, result['public_php_token_matches'])
            self.assertIn({'path_tokens': ['$', token, 'handler'], 'types': ['string']}, result['observations'])
            self.assertNotIn(PRIVATE, json.dumps(result))
            self.assertFalse(result['supported_versions_or_handler_contract_established'])

    def test_public_tokens_never_capture_prefix_suffix_or_normalized_private_text(self):
        for token in (' php82', 'php82 ', 'php82-' + PRIVATE, PRIVATE + 'php82', 'PHP82',
                      'PHP 8.2 (customer)', 'php86', '8.6', 'PHP 8.6', 'cgi', 'fpm', 'PHP-FPM'):
            result = D.shape(json.dumps({token: token}).encode())
            self.assertEqual([], result['public_php_token_matches'])
            self.assertEqual([['$'], ['$', '{withheld-key}']], [row['path_tokens'] for row in result['observations']])
            self.assertNotIn(token, json.dumps(result))

    def test_numeric_versions_stay_types_not_values_and_unknown_paths_collapse(self):
        result = D.shape(json.dumps({PRIVATE: {'value': 8.2, 'title': 'PHP 8.2'}, DYNAMIC: {'value': 'php83'}}).encode())
        self.assertEqual([
            {'path_tokens': ['$', '{withheld-key}', 'title'], 'token': 'PHP 8.2'},
            {'path_tokens': ['$', '{withheld-key}', 'value'], 'token': 'php83'},
        ], result['public_php_token_matches'])
        self.assertNotIn(PRIVATE, json.dumps(result))
        self.assertNotIn(DYNAMIC, json.dumps(result))
        self.assertFalse(result['row_associations_preserved'])

    def test_repeated_tokens_have_no_row_counts_or_membership_association(self):
        a = D.shape(b'[{"version":"php82"}]')
        b = D.shape(b'[{"version":"php82"},{"version":"php82"}]')
        self.assertEqual(a, b)
        with patch.object(D, 'MAX_OBSERVATIONS', 4):
            self.assert_refusal('shape_limit', b'["php80","php81","php82","php83","php84"]')


if __name__ == '__main__':
    unittest.main(verbosity=2)
