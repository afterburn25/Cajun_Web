"""Packaged owner import and genuine multipart image upload; isolated native fixture prefix."""
import hashlib
import struct
import subprocess
import zipfile
import zlib

import requests
from playwright.sync_api import expect
from release_cases import db


def png(color):
    def chunk(kind, data):
        return struct.pack('!I', len(data)) + kind + data + struct.pack('!I', zlib.crc32(kind + data) & 0xffffffff)
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('!2I5B', 32, 24, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress((b'\x00' + bytes(color) * 32) * 24)) + chunk(b'IEND', b''))


def run_browser(env, browser):
    root, web, base, qa = (env[k] for k in ('ROOT', 'WEB', 'BASE', 'QA'))
    check, soup, form_data, form_for, submit = (env[k] for k in ('check', 'soup', 'form_data', 'form_for', 'submit'))
    password = 'Synthetic catalog import owner'
    native = __import__('json').loads((qa / 'provider-catalog-import-native-results.json').read_text(encoding='utf-8'))
    check('Catalog import HTTP starts from successful isolated native preparation', native['passed'] is True and all(c['passed'] is True for c in native['checks']))
    destination = web / 'provider-catalog-import'
    if destination.exists():
        raise AssertionError('Import HTTP scope must start absent')
    destination.mkdir()
    package, = (root / 'dist').glob('*-COMPLETE-UPLOAD.zip')
    with zipfile.ZipFile(package) as archive:
        archive.extractall(destination)
    # Native fixture's fixed isolated configuration; never emitted to QA or a response.
    configured = subprocess.run([env['php'], str(root / 'tests/provider_catalog_import_native.php'), 'browser-config'], capture_output=True, check=True)
    if configured.stderr or not configured.stdout.startswith(b'<?php return '):
        raise AssertionError('Import HTTP isolated configuration failed')
    (destination / 'config.php').write_bytes(configured.stdout)
    (destination / '.installed.php').write_bytes(b'<?php exit; ?>synthetic installed guard')
    url = base + '/provider-catalog-import/admin/index.php?view=catalog'
    login = base + '/provider-catalog-import/admin/index.php?view=login'
    owner = requests.Session()
    response = submit(owner, login, 'login', {'email': 'import1@example.invalid', 'password': password})
    check('Catalog import owner signs in through the actual installed app', response.status_code == 200 and 'Sales pipeline' in response.text)
    calls = env['provider_server'].calls

    def state():
        return [db('SELECT * FROM pimci_' + table + ' ORDER BY id') for table in ('catalog_products', 'catalog_revisions', 'catalog_provider_imports', 'media', 'audit')]

    def selected(response, type_ref):
        forms = [form for form in soup(response).select('form') if form.select_one('[name=action][value=catalog_import_create]') is not None and form.select_one('[name=type_ref]')['value'] == type_ref]
        if len(forms) != 1:
            raise AssertionError('One exact current observed-type form is required')
        return form_data(forms[0])

    response = owner.get(url, timeout=15)
    fields = selected(response, '3')
    fields.update(sku='HTTP-IMPORT-THREE', term='year', confirm='1', owner_password=password)
    before = state()
    response = owner.post(url, data=dict(fields, csrf='invalid'), timeout=25)
    check('Catalog import HTTP rejects invalid CSRF without changing records', response.status_code == 200 and 'session expired' in response.text.lower() and state() == before)
    response = owner.post(url, data=dict(fields, owner_password='wrong'), timeout=25)
    retry = selected(response, '3')
    check('Catalog import error preserves selected SKU period consent and captured preview', response.status_code == 200 and 'current password is not correct' in response.text and all(retry[k] == fields[k] for k in ('sku', 'term', 'confirm', 'preview_hash')) and retry['owner_password'] == '' and state() == before)
    response = owner.post(url, data=fields, timeout=25)
    imported, = db('SELECT * FROM pimci_catalog_provider_imports WHERE type_ref=?', ['3'])
    product_id = imported['product_id']
    edit = url + '&id=' + str(product_id)
    product, = db('SELECT * FROM pimci_catalog_products WHERE id=?', [product_id])
    check('Catalog import HTTP creates an unpriced local hosting draft', response.status_code == 200 and product['status'] == 'draft' and product['family'] == 'hosting' and product['amount_cents'] is None and product['terms'] == '')
    before = state()
    response = owner.post(url, data=fields, timeout=25)
    check('Catalog import HTTP replay preserves the exact existing product and provenance', response.status_code == 200 and state() == before)
    staff = requests.Session()
    response = submit(staff, login, 'login', {'email': 'import2@example.invalid', 'password': password})
    check('Catalog import ordinary administrator signs in', response.status_code == 200 and 'Sales pipeline' in response.text)
    response = staff.get(url, timeout=15)
    check('Ordinary catalog administrator sees no owner import or image actions', response.status_code == 200 and not soup(response).select('[value^=catalog_import_]'))
    before = state()  # Login has its own audit; compare only the following refused action.
    denied = dict(fields, csrf=env['token'](response))
    response = staff.post(url, data=denied, timeout=25)
    check('Forged administrator import fails without changing records', response.status_code == 200 and 'owner account is required' in response.text and state() == before)

    def image_fields():
        return form_data(form_for(owner.get(edit, timeout=15), 'catalog_import_image'))

    image = image_fields()
    image['owner_password'] = password
    pixels = png((30, 80, 120)) + b'PRIVATE_UPLOAD_METADATA_MARKER'
    media_root = root / 'test-runtime/provider-catalog-import-private/media'
    media_before = {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in media_root.iterdir() if p.is_file()}
    db("CREATE TRIGGER pimci_upload_audit_failure BEFORE INSERT ON pimci_audit FOR EACH ROW BEGIN IF NEW.action='catalog.provider_import.image' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic product image audit failure'; END IF; END")
    try:
        response = owner.post(edit, data=image, files={'product_image': ('audit-failure.png', pixels, 'image/png')}, timeout=25)
        check('Failed image audit preserves old media and removes only the newly allocated file', response.status_code == 200 and 'The change could not be saved.' in response.text and state() == before and {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in media_root.iterdir() if p.is_file()} == media_before)
    finally:
        db('DROP TRIGGER pimci_upload_audit_failure')
    response = owner.post(edit, data=dict(image, csrf='bad'), files={'product_image': ('image.png', pixels, 'image/png')}, timeout=25)
    check('Product multipart image upload requires valid CSRF', response.status_code == 200 and 'session expired' in response.text.lower() and state() == before)
    response = owner.post(edit, data=image, files={'product_image': ('image.svg', b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml')}, timeout=25)
    check('Product image SVG and executable markup are rejected', response.status_code == 200 and 'Use a valid JPG, PNG or WebP' in response.text and state() == before)
    response = owner.post(edit, data=image, files={'product_image': ('image.png', pixels, 'image/png')}, timeout=25)
    mapping, = db('SELECT * FROM pimci_catalog_provider_imports WHERE product_id=?', [product_id])
    media, = db('SELECT * FROM pimci_media WHERE id=?', [mapping['media_id']])
    image_url = base + '/provider-catalog-import/media/index.php?id=' + mapping['media_id']
    fetched = owner.get(image_url, timeout=15)
    check('Real multipart image is decoded reencoded and attached to the draft', response.status_code == 200 and mapping['media_revision'] == 1 and media['mime'] == 'image/png' and media['width'] == 32 and media['height'] == 24 and fetched.status_code == 200 and fetched.headers['Content-Type'].startswith('image/png') and fetched.content.startswith(b'\x89PNG\r\n\x1a\n') and b'PRIVATE_UPLOAD_METADATA_MARKER' not in fetched.content)
    anonymous = requests.get(image_url, timeout=15)
    check('Existing public media URL exposure is stated accurately for uploaded artwork', anonymous.status_code == 200 and anonymous.content == fetched.content and 'Images are accessible via their URL' in response.text)
    original_image = mapping['media_id']
    original_bytes = fetched.content
    capture = image_fields()
    response = owner.post(edit, data=dict(capture, owner_password=password), files={'product_image': ('replacement.png', png((160, 90, 40)), 'image/png')}, timeout=25)
    replaced, = db('SELECT * FROM pimci_catalog_provider_imports WHERE product_id=?', [product_id])
    check('Owner replacement selects a new image and retains the original file', response.status_code == 200 and replaced['media_revision'] == 2 and replaced['media_id'] != original_image and owner.get(image_url, timeout=15).content == original_bytes)
    before = state()
    response = owner.post(edit, data=dict(capture, owner_password=password, remove_image='1'), timeout=25)
    check('Captured stale image form cannot remove the replacement', response.status_code == 200 and 'product or image changed' in response.text and state() == before and form_data(form_for(response, 'catalog_import_image'))['image_revision'] == capture['image_revision'])
    edit_fields = form_data(form_for(owner.get(edit, timeout=15), 'catalog_save'))
    response = owner.post(edit, data=dict(edit_fields, family='addon', status='published', amount='10.00', summary='Mislabelled hosting', terms='Manual period'), timeout=25)
    check('Forged family disguise cannot publish imported hosting', response.status_code == 200 and 'must remain hosting drafts' in response.text and state() == before)
    public = owner.get(base + '/provider-catalog-import/index.php?page=packages', timeout=15)
    check('Imported product image and internal provenance remain outside public catalog', 'HTTP-IMPORT-THREE' not in public.text and replaced['media_id'] not in public.text and imported['source_hash'] not in public.text)
    health = base + '/provider-catalog-import/admin/index.php?view=health'
    response = submit(owner, health, 'media_cleanup')
    current_image = owner.get(base + '/provider-catalog-import/media/index.php?id=' + replaced['media_id'], timeout=15)
    check('Actual housekeeping retains the currently attached draft image', response.status_code == 200 and current_image.status_code == 200 and db('SELECT id FROM pimci_media WHERE id=?', [replaced['media_id']]) == [{'id': replaced['media_id']}])

    context = browser.new_context(viewport={'width': 1440, 'height': 1000})
    context.add_cookies([{'name': c.name, 'value': c.value, 'domain': '127.0.0.1', 'path': c.path} for c in owner.cookies])
    page = context.new_page()
    for name, width, height in [('Desktop', 1440, 1000), ('Mobile', 320, 900)]:
        page.set_viewport_size({'width': width, 'height': height})
        page.goto(edit, wait_until='load')
        card = page.locator('section').filter(has=page.get_by_role('heading', name='Imported draft image', exact=True))
        expect(card).to_be_visible()
        expect(card.locator('img')).to_be_visible()
        check('Imported draft image page has no horizontal overflow ' + name, page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
        check('Imported draft image Save control fits ' + name, card.get_by_role('button', name='Save image', exact=True).evaluate("b => {const r=b.getBoundingClientRect(),f=b.closest('form').getBoundingClientRect(),t=document.createRange();t.selectNodeContents(b);const x=t.getBoundingClientRect();return r.left>=f.left-1&&r.right<=f.right+1&&x.left>=r.left-1&&x.right<=r.right+1;}"))
        save = card.get_by_role('button', name='Save image', exact=True)
        save.evaluate("b => b.scrollIntoView({block:'center',inline:'nearest',behavior:'instant'})")
        save.click(trial=True)  # Actionability only; multipart persistence is exercised above.
        check('Imported draft image Save control is fully reachable ' + name, save.evaluate("b => {const r=b.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight&&[[.5,.5],[.1,.1],[.9,.1],[.1,.9],[.9,.9]].every(([x,y])=>{const h=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);return h===b||b.contains(h);});}"))
        # Preserve320x900 interaction checks; give this section-only capture room
        # for the existing108px scroll padding and mobile sticky header.
        if name == 'Mobile':
            page.set_viewport_size({'width': width, 'height': 1100})
        card.scroll_into_view_if_needed()
        check('Imported draft image capture contains the whole card ' + name, card.evaluate("c => {const r=c.getBoundingClientRect(),h=document.querySelector('.admin-topbar').getBoundingClientRect();return r.top>=Math.max(0,h.bottom)&&r.bottom<=innerHeight;}"))
        card.screenshot(path=str(qa / ('20i-Product-Import-' + name + '.png')))
    context.close()
    check('Catalog imports and local images send no provider request', env['provider_server'].calls == calls)
    owner.close()
    staff.close()
