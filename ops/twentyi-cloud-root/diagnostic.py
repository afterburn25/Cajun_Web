"""One fixed cloud metadata GET; expose only root kind and closed container shape."""
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
    ('cloud_server_product_data', '/reseller/*/cloudServerProductData'),
)
MAX_BODY = 2 * 1024 * 1024
MAX_DEPTH = 12
MAX_NODES = 50000
MAX_OBSERVATIONS = 256
MAX_SHAPE_BYTES = 48 * 1024
MAX_OUTPUT = 100 * 1024
SOCKET_SECONDS = 10
TOTAL_SECONDS = 35
OUTPUT_DIRECTORY = 'cloud-root-evidence'
OUTPUT_FILE = 'cloud-root-shape.json'

# These spellings occur in the retained owner reference (including adjacent
# CREATE inputs) or the existing GET /package projection. They are NOT a GET
# response contract. Emit our constant, never a response-supplied key object.
REFERENCE_NAMES = (
    'typeRef', 'packageTypeName', 'label', 'platform', 'limit', 'installApps',
    'welcomeEmail', 'from', 'subject', 'data', 'passwordResetEmail', 'defaultPages',
    'IndexPageHtml', 'DeactivatedPageHtml', 'ServiceUnavailablePageHtml',
    'extraData', 'temporaryUrlDomain', 'phpVersion',
)
# Generic catalog vocabulary probes, not asserted 20i response properties or
# meanings. Unknown/dynamic names still collapse even when nested below these.
CATALOG_LEXEMES = (
    'id', 'name', 'title', 'description', 'slug', 'sku', 'code', 'type',
    'productId', 'product_id', 'packageTypeId', 'package_type_id',
    'product', 'products', 'packageTypes', 'items', 'category', 'categories',
    'specifications', 'specs', 'cpu', 'cores', 'memory', 'ram', 'disk',
    'storage', 'bandwidth', 'price', 'prices', 'cost', 'currency', 'amount',
    'period', 'term', 'unit', 'units', 'quantity', 'setupFee', 'monthly', 'annually',
)
# Lexical probes only: matches do not establish field semantics, URL validity,
# artwork availability, permission to download, or absence of other image keys.
IMAGE_LEXEMES = (
    'image', 'images', 'imageUrl', 'imageURL', 'image_url', 'imageUrls',
    'icon', 'iconUrl', 'iconURL', 'icon_url', 'logo', 'logoUrl', 'logoURL',
    'logo_url', 'thumbnail', 'thumbnailUrl', 'thumbnailURL', 'thumbnail_url',
)
CANONICAL_NAMES = {name: name for name in REFERENCE_NAMES + CATALOG_LEXEMES + IMAGE_LEXEMES}
TYPES = ('null', 'boolean', 'integer', 'number', 'string', 'array', 'object')
ERRORS = frozenset((
    'missing_secret', 'invalid_secret', 'unsupported_operation', 'http_401',
    'http_403', 'http_404', 'http_429', 'redirect_refused', 'http_failure',
    'network_or_tls_failure', 'invalid_headers', 'content_type',
    'content_encoding', 'body_limit', 'body_length_mismatch', 'invalid_json', 'shape_limit',
    'unexpected_root', 'deadline', 'unsupported_deadline_runtime',
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


def shape(raw, operation):
    if operation not in dict(OPERATIONS):
        refuse('unsupported_operation')
    if len(raw) > MAX_BODY:
        refuse('body_limit')
    try:
        value = json.loads(raw.decode('utf-8', errors='strict'),
                           object_pairs_hook=unique_object, parse_constant=reject_constant)
    except (ValueError, UnicodeError, RecursionError):
        refuse('invalid_json')
    root = json_type(value)
    if root not in ('object', 'array'):
        # A valid scalar supplies only its fixed type enum, never its value.
        return {
            'root_type': root,
            'semantics': 'root_kind_only_value_withheld',
            'catalog_contract_established': False,
            'image_contract_established': False,
        }
    observations = {}
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
        # The raw node's value, string length, collection length, map key,
        # checksum and numeric contents are never included in the output.
    result = {
        'root_type': root,
        'unknown_or_dynamic_keys_withheld': unknown,
        'whole_bounded_document_traversed': True,
        'observations': [
            {'path_tokens': list(path), 'types': sorted(kinds)}
            for path, kinds in sorted(observations.items())
        ],
        'semantics': 'lexical_matches_only_unknown_key_paths_collapsed',
        'catalog_contract_established': False,
        'image_contract_established': False,
    }
    if len(json.dumps(result, ensure_ascii=True).encode('ascii')) > MAX_SHAPE_BYTES:
        refuse('shape_limit')
    return result


def one_header(headers, name, default=None):
    values = headers.get_all(name, [])
    if len(values) > 1 or (values and len(values[0]) > 1024):
        refuse('invalid_headers')
    return values[0].strip() if values else default


def fetch_shape(operation, key):
    # The caller cannot provide a URL, path, host, port, method or headers.
    paths = dict(OPERATIONS)
    if operation not in paths:
        refuse('unsupported_operation')
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
            'User-Agent': 'CajunWebPro-catalog-shape/1.0',
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
        return shape(raw, operation)
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
    for record in records:
        try:
            record['shape'] = fetch_shape(record['operation'], key)
            record['outcome'] = ('shape_observed' if record['shape']['root_type'] in ('object', 'array')
                                 else 'root_kind_observed')
        except Exception as error:
            record['outcome'] = finite_error(error)
            if record['outcome'] == 'deadline':
                break
    return {
        'schema': 1,
        'outcome': 'complete' if all(row['outcome'] in ('shape_observed', 'root_kind_observed') for row in records) else 'incomplete',
        'operations': records,
        'catalog_import_accepted': False,
        'image_download_authorized': False,
        'raw_values_unknown_keys_urls_headers_and_response_digests_exported': False,
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
        # The one request plus parsing shares one absolute deadline, including DNS.
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
        print('Cloud root diagnostic: output_failure')
        return 1
    # Only our finite outcome is printed. Neither raw exceptions nor data are logged.
    print('Cloud root diagnostic: ' + result['outcome'])
    return 0 if result['outcome'] == 'complete' else 1


if __name__ == '__main__':
    sys.exit(main())
