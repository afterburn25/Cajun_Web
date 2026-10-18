"""Actual packaged owner catalog refresh/import with loopback TLS; separate native fixture."""
import hashlib
import json
import subprocess
import time
import zipfile

import requests
from playwright.sync_api import expect
from release_cases import db
from provider_catalog_import_cases import png


def run_browser(env, browser):
    root, web, base, qa = (env[k] for k in ('ROOT', 'WEB', 'BASE', 'QA'))
    check, soup, form_data, form_for, submit = (env[k] for k in ('check', 'soup', 'form_data', 'form_for', 'submit'))
    native = json.loads((qa / 'provider-package-types-native-results.json').read_text(encoding='utf-8'))
    check('Direct catalog HTTP starts from successful separate native preparation', native['passed'] is True and native['checks'] and all(c['passed'] is True for c in native['checks']))
    destination = web / 'provider-package-types'
    if destination.exists():
        raise AssertionError('Direct catalog HTTP scope must start absent')
    destination.mkdir()
    package, = (root / 'dist').glob('*-COMPLETE-UPLOAD.zip')
    with zipfile.ZipFile(package) as archive:
        archive.extractall(destination)
    configured = subprocess.run([env['php'], str(root / 'tests/provider_package_types_native.php'), 'browser-config'], capture_output=True, check=True)
    if configured.stderr or not configured.stdout.startswith(b'<?php return '):
        raise AssertionError('Direct catalog isolated configuration failed')
    (destination / 'config.php').write_bytes(configured.stdout)
    (destination / '.installed.php').write_bytes(b'<?php exit; ?>synthetic installed guard')
    url = base + '/provider-package-types/admin/index.php?view=catalog&import_source=package.types'
    login = base + '/provider-package-types/admin/index.php?view=login'
    password = 'Synthetic package types owner'
    owner = requests.Session()
    response = submit(owner, login, 'login', {'email': 'types1@example.invalid', 'password': password})
    check('Direct catalog owner signs in through actual installed application', response.status_code == 200 and 'Sales pipeline' in response.text)
    server = env['provider_server']
    saved_server = (server.package_types, server.package_types_mode)
    calls_before, paths_before, posts_before, errors_before = server.calls, len(server.get_paths), len(server.posts), list(server.errors)
    media_root = root / 'test-runtime/provider-package-types-private/media'

    def product_state():
        rows = [db('SELECT * FROM ptici_' + table + ' ORDER BY id') for table in ('catalog_products', 'catalog_revisions', 'catalog_provider_imports', 'media')]
        rows.append(db("SELECT * FROM ptici_settings WHERE name='provider_catalog_import_count'"))
        files = {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in media_root.iterdir() if p.is_file()}
        return rows, files

    def selected(response, type_ref):
        forms = [f for f in soup(response).select('form') if f.select_one('[name=action][value=catalog_import_create]') is not None and f.select_one('[name=type_ref]')['value'] == type_ref]
        if len(forms) != 1:
            raise AssertionError('One exact catalog type form is required')
        fields = form_data(forms[0])
        if fields.get('import_source') != 'package.types':
            raise AssertionError('Direct catalog form lost its source')
        return fields

    def refresh():
        # Observe the actual throttle; do not rewrite signed history or relax deadlines.
        latest, = db('SELECT COALESCE(MAX(started_epoch),0) AS epoch FROM ptici_provider_refreshes')
        wait = max(0, int(latest['epoch']) + 31 - time.time())
        if wait > 31:
            raise AssertionError('Direct catalog fixture clock is ahead')
        if wait:
            time.sleep(wait)
        return submit(owner, url, 'catalog_import_catalog_refresh', {'owner_password': password})

    try:
        server.package_types = [{'id': 1, 'label': 'Catalog shared label'}, {'id': 7001, 'label': 'Catalog-only native'}, {'id': 7002, 'label': 'Catalog-only browser', 'platform': 'PRIVATE_CATALOG_PLATFORM', 'image': 'https://private.invalid/secret', 'price': 999, 'customer_key': 'PRIVATE_CATALOG_VALUE'}, {'id': 7003, 'label': '<b>Catalog & choice</b>'}]
        server.package_types_mode = 'success'
        response = owner.get(url, timeout=15)
        refresh_fields = form_data(form_for(response, 'catalog_import_catalog_refresh'))
        before = product_state()
        count = server.calls
        response = owner.post(url, data=dict(refresh_fields, csrf='invalid', owner_password=password), timeout=25)
        check('Catalog refresh rejects bad CSRF without provider request or product changes', response.status_code == 200 and 'session expired' in response.text.lower() and server.calls == count and product_state() == before)
        response = owner.post(url, data=dict(refresh_fields, owner_password='wrong'), timeout=25)
        check('Catalog refresh requires owner password before provider request', response.status_code == 200 and 'current password is not correct' in response.text and server.calls == count and product_state() == before)
        response = refresh()
        check('Owner catalog refresh sends one exact self GET and saves returned types', response.status_code == 200 and server.calls == count + 1 and server.get_paths[-1] == '/reseller/*/packageTypes' and '20i hosting types saved' in response.text)
        installed = db('SELECT * FROM ptici_provider_observation_packages ORDER BY id')
        check('Direct catalog offers a type absent from installed inventory', all(str(row['type_ref']) != '7002' for row in installed) and selected(response, '7002')['type_ref'] == '7002')
        check('Direct catalog UI withholds unknown metadata price and remote image fields', all(marker not in response.text for marker in ('PRIVATE_CATALOG_PLATFORM', 'PRIVATE_CATALOG_VALUE', 'https://private.invalid/secret')) and 'Prices are not imported' in response.text and 'Images are not copied from 20i' in response.text)
        check('Direct catalog labels render as text rather than provider HTML', '<b>Catalog & choice</b>' in soup(response).get_text() and soup(response).find('b', string='Catalog & choice') is None)
        fields = selected(response, '7002')
        fields.update(sku='CATALOG-HTTP', term='year', confirm='1', owner_password=password)
        before = product_state()
        response = owner.post(url, data=dict(fields, import_source='packages.list'), timeout=25)
        check('Catalog import source swap cannot authorize an inventory import', response.status_code == 200 and 'changed' in response.text.lower() and product_state() == before)
        response = owner.post(url, data=dict(fields, owner_password='wrong'), timeout=25)
        retry = selected(response, '7002')
        check('Catalog retry retains source SKU period and hash while clearing password', response.status_code == 200 and all(retry[k] == fields[k] for k in ('import_source', 'sku', 'term', 'preview_hash', 'confirm')) and retry['owner_password'] == '' and product_state() == before)
        response = owner.post(url, data=fields, timeout=25)
        imported, = db("SELECT i.*,f.operation FROM ptici_catalog_provider_imports i JOIN ptici_provider_refreshes f ON f.id=i.refresh_id WHERE i.type_ref='7002'")
        product, = db('SELECT * FROM ptici_catalog_products WHERE id=?', [imported['product_id']])
        check('HTTP imports catalog-only plan as unpriced draft with exact retained source', response.status_code == 200 and imported['operation'] == 'package.types' and imported['type_name'] == 'Catalog-only browser' and product['family'] == 'hosting' and product['status'] == 'draft' and product['amount_cents'] is None and product['cost_cents'] is None and product['features'] == '' and product['terms'] == '' and db('SELECT * FROM ptici_provider_observation_packages ORDER BY id') == installed)
        edit = url + '&id=' + str(imported['product_id'])
        image_fields = form_data(form_for(owner.get(edit, timeout=15), 'catalog_import_image'))
        response = owner.post(edit, data=dict(image_fields, owner_password=password), files={'product_image': ('catalog.png', png((48, 120, 84)) + b'PRIVATE_CATALOG_IMAGE_METADATA', 'image/png')}, timeout=25)
        mapping, = db('SELECT * FROM ptici_catalog_provider_imports WHERE product_id=?', [imported['product_id']])
        image_url = base + '/provider-package-types/media/index.php?id=' + mapping['media_id']
        image = owner.get(image_url, timeout=15)
        check('Direct catalog draft accepts only local genuine multipart artwork', response.status_code == 200 and mapping['media_revision'] == 1 and image.status_code == 200 and image.content.startswith(b'\x89PNG\r\n\x1a\n') and b'PRIVATE_CATALOG_IMAGE_METADATA' not in image.content and server.calls == count + 1)
        before = product_state()
        response = owner.post(url, data=fields, timeout=25)
        check('Catalog replay preserves product provenance and uploaded artwork exactly', response.status_code == 200 and product_state() == before and owner.get(image_url, timeout=15).content == image.content)
        staff = requests.Session()
        try:
            response = submit(staff, login, 'login', {'email': 'types2@example.invalid', 'password': password})
            check('Direct catalog ordinary administrator signs in', response.status_code == 200 and 'Sales pipeline' in response.text)
            response = staff.get(url, timeout=15)
            check('Ordinary administrator sees no catalog refresh or import actions', response.status_code == 200 and not soup(response).select('[value^=catalog_import_]'))
            before = product_state()
            response = staff.post(url, data=dict(fields, csrf=env['token'](response)), timeout=25)
            check('Forged administrator catalog import preserves records and image', response.status_code == 200 and 'owner account is required' in response.text and product_state() == before)
        finally:
            staff.close()
        before = product_state()
        good_rows = list(server.package_types)
        for mode, rows in [('success', [{'id': 7002, 'label': 'Duplicate'}, {'id': 7002, 'label': 'Duplicate'}]), ('object', good_rows), ('authentication', good_rows)]:
            server.package_types, server.package_types_mode = rows, mode
            old_calls = server.calls
            response = refresh()
            check('Failed catalog HTTP refresh preserves products and images ' + mode + (' duplicate' if len(rows) == 2 else ''), response.status_code == 200 and 'could not be completed' in response.text and server.calls == old_calls + 1 and product_state() == before and owner.get(image_url, timeout=15).content == image.content)
            check('Failed catalog HTTP refresh exposes no stale create forms ' + mode + (' duplicate' if len(rows) == 2 else ''), not soup(response).select('[name=action][value=catalog_import_create]'))
            response = owner.post(url, data=fields, timeout=25)
            check('Captured catalog import cannot bypass latest failed response ' + mode + (' duplicate' if len(rows) == 2 else ''), response.status_code == 200 and 'newest response must finish' in response.text and product_state() == before)
        server.package_types, server.package_types_mode = [], 'success'
        response = refresh()
        check('Empty catalog HTTP observation preserves products and image without fallback choices', response.status_code == 200 and not soup(response).select('[name=action][value=catalog_import_create]') and product_state() == before and owner.get(image_url, timeout=15).content == image.content)
        server.package_types = good_rows
        response = refresh()
        check('Later valid catalog restores choices without reimporting or changing local products', response.status_code == 200 and selected(response, '7003')['import_source'] == 'package.types' and product_state() == before)
        response = owner.post(url, data=fields, timeout=25)
        check('Old catalog preview remains stale after a newer successful refresh', response.status_code == 200 and 'changed' in response.text.lower() and product_state() == before)
        context = browser.new_context(viewport={'width': 1440, 'height': 1000})
        context.add_cookies([{'name': c.name, 'value': c.value, 'domain': '127.0.0.1', 'path': c.path} for c in owner.cookies])
        try:
            page = context.new_page()
            for name, width, height in [('Desktop', 1440, 1000), ('Mobile', 320, 900)]:
                page.set_viewport_size({'width': width, 'height': height})
                page.goto(url, wait_until='load')
                card = page.locator('section').filter(has=page.get_by_role('heading', name='Import hosting products', exact=True))
                expect(card).to_be_visible()
                expect(card.get_by_role('button', name='Refresh 20i hosting catalog', exact=True)).to_be_visible()
                check('Direct hosting catalog page has no horizontal overflow ' + name, page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
                card.screenshot(path=str(qa / ('20i-Hosting-Catalog-' + name + '.png')))
        finally:
            context.close()
        check('Catalog HTTP uses only six explicit fixed reads and no provider writes', server.calls - calls_before == 6 and server.get_paths[paths_before:] == ['/reseller/*/packageTypes'] * 6 and len(server.posts) == posts_before and server.errors == errors_before)
    finally:
        server.package_types, server.package_types_mode = saved_server
        owner.close()
