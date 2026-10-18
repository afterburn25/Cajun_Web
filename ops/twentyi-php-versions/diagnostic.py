"""One package selection GET, then one fixed PHP-choice metadata GET; no writes."""
import base64
import http.client
import json
import math
import os
from pathlib import Path
import signal
import ssl
import sys

HOST = 'api.20i.com'
OPERATIONS = (
    ('package_selection', '/package'),
    ('available_php_versions', '/package/{selected}/web/availablePhpVersions'),
)
MAX_BODY = 2 * 1024 * 1024
MAX_PACKAGES = 4096
MAX_DEPTH = 12
MAX_NODES = 50000
MAX_OBSERVATIONS = 256
MAX_SHAPE_BYTES = 48 * 1024
MAX_OUTPUT = 100 * 1024
SOCKET_SECONDS = 10
TOTAL_SECONDS = 35
OUTPUT_DIRECTORY = 'php-versions-evidence'
OUTPUT_FILE = 'php-versions-shape.json'

# Lexical probes, NOT asserted response fields or a supported-version list.
# Emit our constants only, never substrings, normalized or response-owned text.
PHP_LEXEMES = (
    'version', 'versions', 'value', 'handler', 'handlerType', 'type', 'title',
    'name', 'label', 'items', 'data', 'phpVersion', 'availablePhpVersions',
)
PUBLIC_PHP_TOKENS = (
    'php53', '5.3', 'PHP 5.3', 'php54', '5.4', 'PHP 5.4',
    'php55', '5.5', 'PHP 5.5', 'php56', '5.6', 'PHP 5.6',
    'php70', '7.0', 'PHP 7.0', 'php71', '7.1', 'PHP 7.1',
    'php72', '7.2', 'PHP 7.2', 'php73', '7.3', 'PHP 7.3',
    'php74', '7.4', 'PHP 7.4', 'php80', '8.0', 'PHP 8.0',
    'php81', '8.1', 'PHP 8.1', 'php82', '8.2', 'PHP 8.2',
    'php83', '8.3', 'PHP 8.3', 'php84', '8.4', 'PHP 8.4',
    'php85', '8.5', 'PHP 8.5',
)
# No handler values are admitted: the retained reference names the concept,
# but supplies no handler literals. Their types/paths remain observable.
CANONICAL_NAMES = {name: name for name in PHP_LEXEMES + PUBLIC_PHP_TOKENS}
CANONICAL_VALUES = {name: name for name in PUBLIC_PHP_TOKENS}
TYPES = ('null', 'boolean', 'integer', 'number', 'string', 'array', 'object')
ERRORS = frozenset((
    'missing_secret', 'invalid_secret', 'unsupported_operation', 'http_401',
    'http_403', 'http_404', 'http_429', 'redirect_refused', 'http_failure',
    'network_or_tls_failure', 'invalid_headers', 'content_type',
    'content_encoding', 'body_limit', 'body_length_mismatch', 'invalid_json', 'shape_limit',
    'unexpected_root', 'invalid_package_identity', 'duplicate_package_identity',
    'package_limit', 'no_package', 'deadline', 'unsupported_deadline_runtime',
    'internal_failure', 'output_failure',
))


class Refusal(Exception):
    pass


def refuse(code):
    raise Refusal(code)


def finite_error(error):
    return error.args[0] if (type(error) is Refusal and len(error.args) == 1 and type(error.args[0]) is str
                           and error.args[0] in ERRORS) else 'internal_failure'


def validate_key(key):
    if not key:
        refuse('missing_secret')
    if type(key) is not str or len(key) > 16384 or any(not 33 <= ord(c) <= 126 for c in key):
        refuse('invalid_secret')


def reject_constant(_value):
    refuse('invalid_json')


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            refuse('invalid_json')
        result[key] = value
    return result


def json_type(value):
    if value is None:
        return 'null'
    if type(value) is bool:
        return 'boolean'
    if type(value) is int:
        return 'integer'
    if type(value) is float and math.isfinite(value):
        return 'number'
    if type(value) is str:
        # UTF-8 decoding alone permits JSON-escaped isolated surrogates.
        try:
            value.encode('utf-8', errors='strict')
        except UnicodeError:
            refuse('invalid_json')
        return 'string'
    if type(value) is list:
        return 'array'
    if type(value) is dict:
        return 'object'
    refuse('invalid_json')


def decode(raw):
    if len(raw) > MAX_BODY:
        refuse('body_limit')
    try:
        value = json.loads(raw.decode('utf-8', errors='strict'),
                           object_pairs_hook=unique_object, parse_constant=reject_constant)
    except (ValueError, UnicodeError, RecursionError):
        refuse('invalid_json')
    return value


def package_id(value):
    if type(value) is int:
        if not 1 <= value <= 999999999999999999:
            refuse('invalid_package_identity')
        return str(value)
    if (type(value) is not str or not 1 <= len(value) <= 18 or
            value[0] == '0' or any(c not in '0123456789' for c in value)):
        refuse('invalid_package_identity')
    return value


def select_package(raw):
    value = decode(raw)
    if type(value) is not list or any(type(item) is not dict for item in value):
        refuse('unexpected_root')
    if len(value) > MAX_PACKAGES:
        refuse('package_limit')
    if not value:
        refuse('no_package')
    # Validate the whole bounded JSON document, but retain no inventory shape,
    # names, counts, resource IDs, digests, or other customer values in output.
    pending = [(value, 0)]
    enqueued = 1
    while pending:
        item, depth = pending.pop()
        if depth > MAX_DEPTH:
            refuse('shape_limit')
        kind = json_type(item)
        if kind in ('array', 'object'):
            enqueued += len(item)
            if enqueued > MAX_NODES:
                refuse('shape_limit')
            if kind == 'object':
                for key in item:
                    json_type(key)
            pending.extend((child, depth + 1) for child in (item.values() if kind == 'object' else item))
    ids = set()
    for item in value:
        identity = package_id(item.get('id'))
        if identity in ids:
            refuse('duplicate_package_identity')
        ids.add(identity)
    return min(ids, key=int)


def shape(raw, operation='available_php_versions'):
    if operation != 'available_php_versions':
        refuse('unsupported_operation')
    value = decode(raw)
    root = json_type(value)  # Preserve scalar/null kinds; never guess their meaning.
    observations = {}
    matches = set()
    unknown = False
    nodes = 0
    enqueued = 1
    pending = [(value, ('$',), 0)]
    while pending:
        item, path, depth = pending.pop()
        nodes += 1
        if nodes > MAX_NODES or depth > MAX_DEPTH:
            refuse('shape_limit')
        kind = json_type(item)
        observations.setdefault(path, set()).add(kind)
        if len(observations) > MAX_OBSERVATIONS:
            refuse('shape_limit')
        if kind == 'string':
            token = CANONICAL_VALUES.get(item)
            if token is not None:
                matches.add((path, token))
                if len(matches) > MAX_OBSERVATIONS:
                    refuse('shape_limit')
        if kind == 'array':
            enqueued += len(item)
            if enqueued > MAX_NODES:
                refuse('shape_limit')
            pending.extend((child, path + ('[]',), depth + 1) for child in item)
        elif kind == 'object':
            enqueued += len(item)
            if enqueued > MAX_NODES:
                refuse('shape_limit')
            for key, child in item.items():
                json_type(key)  # Includes strict Unicode validation for withheld keys.
                token = CANONICAL_NAMES.get(key)
                if token is None:
                    unknown = True
                    token = '{withheld-key}'
                pending.append((child, path + (token,), depth + 1))
        # No arbitrary values, lengths, counts, raw keys, checksums or numeric
        # contents. Exact public-token matches are lexical observations only.
    result = {
        'root_type': root,
        'unknown_or_dynamic_keys_withheld': unknown,
        'whole_bounded_document_traversed': True,
        'observations': [
            {'path_tokens': list(path), 'types': sorted(kinds)}
            for path, kinds in sorted(observations.items())
        ],
        'public_php_token_matches': [
            {'path_tokens': list(path), 'token': token} for path, token in sorted(matches)
        ],
        'semantics': 'lexical_matches_only_unknown_key_paths_collapsed',
        'row_associations_preserved': False,
        'supported_versions_or_handler_contract_established': False,
    }
    if len(json.dumps(result, ensure_ascii=True).encode('ascii')) > MAX_SHAPE_BYTES:
        refuse('shape_limit')
    return result


def one_header(headers, name, default=None):
    values = headers.get_all(name, [])
    if len(values) > 1 or (values and len(values[0]) > 1024):
        refuse('invalid_headers')
    return values[0].strip() if values else default


def fetch_shape(operation, key, selected=None):
    # The caller cannot provide a URL, path, host, port, method or headers.
    paths = dict(OPERATIONS)
    if operation not in paths:
        refuse('unsupported_operation')
    if operation == 'package_selection':
        if selected is not None:
            refuse('unsupported_operation')
    else:
        paths[operation] = '/package/' + package_id(selected) + '/web/availablePhpVersions'
    validate_key(key)
    # Construct explicitly: create_default_context can honor SSLKEYLOGFILE.
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
    context.load_default_certs()
    context.minimum_version = ssl.TLSVersion.TLSv1_2
    # Direct HTTPSConnection ignores proxy environment and has no cookie jar,
    # redirect handler or retry loop. It verifies hostname/certificate by default.
    connection = http.client.HTTPSConnection(HOST, 443, timeout=SOCKET_SECONDS, context=context)
    try:
        connection.request('GET', paths[operation], body=None, headers={
            'Authorization': 'Bearer ' + base64.b64encode(key.encode('ascii')).decode('ascii'),
            'Accept': 'application/json',
            'Accept-Encoding': 'identity',
            'Connection': 'close',
            'User-Agent': 'CajunWebPro-php-versions-shape/1.0',
        })
        response = connection.getresponse()
        if 300 <= response.status < 400:
            refuse('redirect_refused')
        if response.status in (401, 403, 404, 429):
            refuse('http_' + str(response.status))
        if response.status != 200:
            refuse('http_failure')
        content_type = one_header(response.headers, 'Content-Type', '').lower()
        parts = [part.strip() for part in content_type.split(';')]
        if parts[0] != 'application/json' or parts[1:] not in ([], ['charset=utf-8'], ['charset="utf-8"']):
            refuse('content_type')
        if one_header(response.headers, 'Content-Encoding', 'identity').lower() != 'identity':
            refuse('content_encoding')
        length = one_header(response.headers, 'Content-Length')
        transfer = one_header(response.headers, 'Transfer-Encoding')
        if transfer is not None and (transfer.lower() != 'chunked' or length is not None):
            refuse('invalid_headers')
        if length is not None:
            if not length.isascii() or not length.isdecimal():
                refuse('invalid_headers')
            if len(length) > 10 or int(length) > MAX_BODY:
                refuse('body_limit')
        raw = response.read(MAX_BODY + 1)
        if length is not None and len(raw) != int(length):
            refuse('body_length_mismatch')
        return select_package(raw) if operation == 'package_selection' else shape(raw, operation)
    except (OSError, http.client.HTTPException):
        refuse('network_or_tls_failure')
    finally:
        connection.close()


def collect(key):
    records = [{'operation': name, 'outcome': 'not_attempted'} for name, _path in OPERATIONS]
    try:
        validate_key(key)
    except Refusal as error:
        return {'schema': 1, 'outcome': finite_error(error), 'operations': records}
    record = records[0]
    selected = None
    try:
        selected = fetch_shape('package_selection', key)
        record['outcome'] = 'selected_in_memory'
        record = records[1]
        record['shape'] = fetch_shape('available_php_versions', key, selected)
        record['outcome'] = 'shape_observed'
    except Exception as error:
        record['outcome'] = finite_error(error)
    finally:
        selected = None
    return {
        'schema': 1,
        'outcome': 'complete' if records[1]['outcome'] == 'shape_observed' else 'incomplete',
        'operations': records,
        'php_change_attempted': False,
        'selected_package_identifiers_names_or_counts_exported': False,
        'nonallowlisted_values_unknown_keys_urls_headers_and_response_digests_exported': False,
    }


def deadline(_signum, _frame):
    refuse('deadline')


def main():
    key = os.environ.pop('TWENTYI_API_KEY', '')
    result = {'schema': 1, 'outcome': 'internal_failure', 'operations': []}
    armed = False
    try:
        if len(sys.argv) != 1:
            refuse('unsupported_operation')
        if not hasattr(signal, 'SIGALRM') or not hasattr(signal, 'alarm'):
            refuse('unsupported_deadline_runtime')
        # Both requests plus parsing share one absolute deadline, including DNS.
        signal.signal(signal.SIGALRM, deadline)
        signal.alarm(TOTAL_SECONDS)
        armed = True
        result = collect(key)
    except Exception as error:
        result = {'schema': 1, 'outcome': finite_error(error), 'operations': []}
    finally:
        key = None
        if armed:
            signal.alarm(0)
    try:
        encoded = (json.dumps(result, ensure_ascii=True, sort_keys=True, indent=2) + '\n').encode('ascii')
        if len(encoded) > MAX_OUTPUT:
            refuse('output_failure')
        destination = Path(OUTPUT_DIRECTORY)
        destination.mkdir(mode=0o700, exist_ok=False)
        with (destination / OUTPUT_FILE).open('xb') as handle:
            handle.write(encoded)
    except Exception:
        print('PHP versions diagnostic: output_failure')
        return 1
    # Only our finite outcome is printed. Neither raw exceptions nor data are logged.
    print('PHP versions diagnostic: ' + result['outcome'])
    return 0 if result['outcome'] == 'complete' else 1


if __name__ == '__main__':
    sys.exit(main())
