"""Real local catalog/import/image HTTP retention around the 1.25-to-1.26 update.

Catalog read uses an explicitly injected synthetic production reader in a CLI
fixture; this file never calls an external provider or invents provider artwork.
"""
import hashlib
import json
import struct
import zlib
from urllib.parse import parse_qs, urlparse

import requests
from release_126_retention import retained, exact_business, SCHEMA_MARKER_CELLS


def png():
    def chunk(kind, data):
        return struct.pack('!I', len(data)) + kind + data + struct.pack('!I', zlib.crc32(kind + data) & 0xffffffff)
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('!2I5B', 32, 24, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress((b'\x00' + bytes((30, 80, 120)) * 32) * 24)) + chunk(b'IEND', b''))


def count_transition(before, after, changed_cells=()):
    """Only the explicitly exercised second import changes the existing count cell."""
    key = hashlib.sha256(json.dumps({'name': 'provider_catalog_import_count'}, separators=(',', ':')).encode()).hexdigest()
    column = ('settings', key, 'value')
    assert before['tables']['settings']['rows'][key]['value'] == hashlib.sha256(b'"1"').hexdigest()
    assert after['tables']['settings']['rows'][key]['value'] == hashlib.sha256(b'"2"').hexdigest()
    return retained(before, after, changed_cells=[column] + list(changed_cells))


def prepare_original(base, owner, password, fields, submit, snapshot, identity, ok):
    url = base + '/admin/index.php?view=catalog'
    before = snapshot()
    response = submit(owner, url, 'catalog_import_prepare', {'owner_password': password, 'confirm': '1'})
    ok('Original1.25 owner explicitly enables existing hosting import without changing original records',
       response.status_code == 200 and retained(before, snapshot()))
    response = submit(owner, url, 'catalog_import_create',
                      {'sku': 'ORIGINAL-125-HOST', 'term': 'month', 'owner_password': password, 'confirm': '1'}, {'type_ref': 9})
    values = parse_qs(urlparse(response.url).query).get('id', [])
    assert response.status_code == 200 and len(values) == 1 and values[0].isdigit()
    product = int(values[0]); edit = url + '&id=' + str(product)
    response = submit(owner, edit, 'catalog_save',
                      {'title': 'Original locally reviewed hosting', 'amount': '42.50', 'setup': '1.00', 'cost': '8.50',
                       'summary': 'Owner description retained from1.25', 'features': 'Owner supplied features',
                       'terms': 'Owner supplied monthly terms', 'provider_ref': 'Original owner reference'}, {'id': product})
    ok('Original1.25 hosting draft retains real local pricing and wording before upgrade',
       response.status_code == 200 and 'Original locally reviewed hosting' in response.text and '42.50' in response.text)
    response = submit(owner, edit, 'catalog_import_image', {'owner_password': password},
                      {'id': product}, files={'product_image': ('original125.png', png() + b'SYNTHETIC_STRIPPED_METADATA', 'image/png')})
    imports = identity()['catalog_imports']
    assert len(imports) == 1 and int(imports[0]['product_id']) == product
    original = imports[0]
    media = base + '/media/index.php?id=' + original['media_id']
    image = requests.get(media, timeout=120)
    ok('Original1.25 genuine multipart image is retained and anonymously readable only through existing public media policy',
       response.status_code == 200 and image.status_code == 200 and image.headers.get('Content-Type', '').startswith('image/png')
       and image.content.startswith(b'\x89PNG\r\n\x1a\n') and b'SYNTHETIC_STRIPPED_METADATA' not in image.content
       and int(original['media_revision']) == 1 and original['type_ref'] == '9' and original['type_name'] == 'Retained web')
    image_hash = hashlib.sha256(image.content).hexdigest()

    def verify(stage):
        stable = snapshot()
        rows = [row for row in identity()['catalog_imports'] if int(row['product_id']) == product]
        response = owner.get(edit, timeout=120)
        current = requests.get(media, timeout=120)
        ok(stage + ' original hosting draft provenance and local pricing remain exact',
           rows == [original] and response.status_code == 200 and all(text in response.text for text in
           ['Original locally reviewed hosting', '42.50', 'Owner supplied monthly terms', 'Original owner reference']))
        ok(stage + ' original uploaded image bytes and reference remain exact',
           current.status_code == 200 and hashlib.sha256(current.content).hexdigest() == image_hash and exact_business(stable, snapshot()))
        public = requests.get(base + '/index.php?page=packages', timeout=120)
        ok(stage + ' imported hosting draft remains outside public catalog',
           public.status_code == 200 and 'ORIGINAL-125-HOST' not in public.text and original['media_id'] not in public.text)

    verify('original1.25')
    return {'product_id': product, 'original': original, 'verify': verify}


def activate_catalog(base, owner, password, fields, submit, snapshot, identity, command, ok, initial, catalog):
    url = base + '/admin/index.php?view=catalog&import_source=package.types'
    before = snapshot(); response = owner.get(url, timeout=120)
    target, captured = fields(response, 'catalog_import_catalog_prepare')
    ok('Updated1.26 catalog GET leaves new storage unprepared and original history unchanged',
       identity()['package_types_version'] == '' and exact_business(before, snapshot()))
    captured['owner_password'] = password
    response = owner.post(target, data=dict(captured, csrf='invalid'), timeout=120)
    ok('Updated catalog activation rejects invalid CSRF without new storage',
       response.status_code == 200 and 'session expired' in response.text.lower() and exact_business(before, snapshot()))
    response = owner.post(target, data=dict(captured, owner_password='wrong'), timeout=120)
    ok('Updated catalog activation requires the current owner password without adopting history',
       response.status_code == 200 and identity()['package_types_version'] == '' and exact_business(before, snapshot()))
    response = owner.post(target, data=captured, timeout=120)
    prepared = snapshot()
    ok('Explicit updated owner activation adds both empty catalog tables and preserves original data',
       response.status_code == 200 and identity()['package_types_version'] == '1' and retained(initial, prepared, changed_cells=SCHEMA_MARKER_CELLS)
       and all(prepared['tables'][name]['count'] == 0 for name in ['provider_package_type_intents', 'provider_observation_package_types']))
    observed = command('release_126_provider_fixture.php', 'catalog-read')
    ok('Updated catalog stores one synthetic direct response without a provider request',
       observed == {'state': 'complete', 'synthetic_reads': 1, 'live_requests': 0, 'catalog_only_uninstalled': True} and retained(initial, snapshot(), changed_cells=SCHEMA_MARKER_CELLS))
    before = snapshot(); reused = command('release_126_provider_fixture.php', 'catalog-replay')
    ok('Direct catalog matching original type preserves its original provenance image and local fields',
       reused == {'existing_product_id': catalog['product_id']} and exact_business(before, snapshot()))
    target, captured_import = fields(owner.get(url, timeout=120), 'catalog_import_create', {'type_ref': 901, 'import_source': 'package.types'})
    captured_import.update(sku='NEW-126-CATALOG-ONLY', term='year', owner_password=password, confirm='1')
    response = owner.post(target, data=captured_import, timeout=120)
    rows = identity()['catalog_imports']; new = [row for row in rows if row['type_ref'] == '901']
    ok('Updated owner imports a catalog-only type as a second local draft with original rows retained',
       response.status_code == 200 and len(rows) == 2 and len(new) == 1 and new[0]['media_id'] is None
       and new[0]['type_name'] == 'Catalog-only retained hosting' and count_transition(initial, snapshot(), SCHEMA_MARKER_CELLS))

    def verify():
        stable = snapshot(); response = owner.post(target, data=captured_import, timeout=120)
        ok('Captured direct-catalog import remains idempotent after file recovery and reapplication',
           response.status_code == 200 and exact_business(stable, snapshot()))
        actual = command('release_126_provider_fixture.php', 'catalog-state')
        by_type = {row['type_ref']: row for row in actual['types']}
        ok('Reapplied1.26 validates original inventory and new direct-catalog provenance separately',
           actual['ready'] is True and actual['operation'] == 'package.types' and set(by_type) == {'9', '901'}
           and by_type['9']['origin_operation'] == 'packages.list' and by_type['9']['origin_name'] == 'Retained web'
           and int(by_type['9']['product_id']) == catalog['product_id']
           and by_type['901']['origin_operation'] == 'package.types' and by_type['901']['origin_name'] == 'Catalog-only retained hosting'
           and count_transition(initial, snapshot(), SCHEMA_MARKER_CELLS))
    verify()
    return {'verify': verify}
