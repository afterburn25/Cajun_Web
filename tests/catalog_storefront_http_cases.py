"""Packaged catalog continuation after provider_package_types_cases; Linux fixture only.

Uses the existing dedicated ptici_ database history and uploaded artwork without
resetting it. All mutations use authenticated application HTTP. The two catalog
reads and three connection checks use the existing loopback TLS provider server.
"""
import hashlib
import json
import os
import struct
import sys
import time
from pathlib import Path
from urllib.parse import parse_qs, urljoin, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db


CAPTURES = (
    'Catalog-Storefront-Connection-Desktop.png',
    'Catalog-Storefront-Connection-Mobile.png',
    'Catalog-Storefront-Batch-Desktop.png',
    'Catalog-Storefront-Batch-Mobile.png',
)
PROGRESS_NAME = 'catalog-storefront-import-progress.txt'
SCOPE = ('Actual packaged authenticated owner HTTP and Chromium; dedicated ptici_ '
         'continuation with synthetic loopback TLS provider. No remote 20i access, '
         'provider writes, prices/image discovery, purchase or fulfillment acceptance.')


def run_browser(env, browser):
    """Return only actual outcomes; retain a partial flat progress file on failure."""
    root, web, qa = (Path(env[key]).resolve() for key in ('ROOT', 'WEB', 'QA'))
    base = env['BASE']
    if (not sys.platform.startswith('linux') or os.environ.get('CWP_TEST_DATABASE') != 'cwp_test'
            or base != 'http://127.0.0.1:8091' or web != root / 'test-runtime/web'
            or qa != root / 'qa'):
        raise AssertionError('Catalog continuation requires the existing isolated Linux fixture')
    if any((qa / name).exists() or (qa / name).is_symlink() for name in (*CAPTURES, PROGRESS_NAME)):
        raise AssertionError('Catalog continuation preserves earlier results and must run once')
    checks, captures = [], []
    check_names = set()
    result = {'passed': False, 'scope': SCOPE, 'checks': checks, 'captures': captures}

    def progress():
        (qa / PROGRESS_NAME).write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')

    def check(name, condition):
        # Retain this subset even when the parent reporter raises on a false row.
        if name in check_names:
            raise AssertionError('Duplicate storefront assertion label')
        check_names.add(name)
        checks.append({'name': name, 'passed': bool(condition)})
        progress()
        env['check'](name, bool(condition))
        if not condition:
            raise AssertionError(name)

    def read(sql, params=()):
        if not sql.startswith('SELECT ') or 'ptici_' not in sql:
            raise AssertionError('Catalog continuation SQL is restricted to fixture reads')
        return db(sql, params)

    soup, form_data, form_for, submit = (env[key] for key in ('soup', 'form_data', 'form_for', 'submit'))
    server = env['provider_server']
    saved_server = (server.mode, server.packages, server.package_types, server.package_types_mode)
    calls_before, paths_before, posts_before = server.calls, len(server.get_paths), len(server.posts)
    errors_before = list(server.errors)
    owner, context = requests.Session(), None
    primary_error = False
    password = 'Synthetic package types owner'
    installed = web / 'provider-package-types'
    settings_url = base + '/provider-package-types/admin/index.php?view=provider-settings'
    catalog_url = base + '/provider-package-types/admin/index.php?view=catalog&import_source=package.types'
    login_url = base + '/provider-package-types/admin/index.php?view=login'
    media_root = root / 'test-runtime/provider-package-types-private/media'

    def product_state():
        rows = {table: read('SELECT * FROM ptici_' + table + ' ORDER BY id')
                for table in ('catalog_products', 'catalog_revisions', 'catalog_provider_imports', 'media')}
        rows['count'] = read("SELECT * FROM ptici_settings WHERE name='provider_catalog_import_count'")
        rows['catalog_audit'] = read("SELECT * FROM ptici_audit WHERE action LIKE 'catalog.%' ORDER BY id")
        rows['files'] = {path.name: hashlib.sha256(path.read_bytes()).hexdigest()
                         for path in media_root.iterdir() if path.is_file()}
        return rows

    def retained_originals(before, after):
        for table in ('catalog_products', 'catalog_revisions', 'catalog_provider_imports', 'media', 'catalog_audit'):
            current = {str(row['id']): row for row in after[table]}
            if any(current.get(str(row['id'])) != row for row in before[table]):
                return False
        return all(after['files'].get(name) == digest for name, digest in before['files'].items())

    def import_records():
        return {str(row['type_ref']): row for row in read('SELECT * FROM ptici_catalog_provider_imports ORDER BY id')}

    def cta(response):
        return [link for link in soup(response).select('a[href]') if link.get_text(strip=True) == 'Import 20i Catalog']

    def wait_for_admission(table, profile_id):
        if table not in ('provider_checks', 'provider_refreshes'):
            raise AssertionError('Unexpected admission bucket')
        latest, = read('SELECT COALESCE(MAX(started_epoch),0) AS epoch FROM ptici_' + table + ' WHERE profile_id=?', [profile_id])
        seconds = max(0, int(latest['epoch']) + 31 - time.time())
        if seconds > 31:
            raise AssertionError('Catalog fixture timestamp is ahead of the real clock')
        if seconds:
            time.sleep(seconds)  # Real throttle; never rewrite history or retry a request.

    def connection(mode, profile_id, phase):
        wait_for_admission('provider_checks', profile_id)
        server.mode = mode
        before = server.calls
        response = submit(owner, settings_url, 'provider_settings_check', {'current_password': password})
        check('Storefront explicit connection check uses one fixed GET ' + phase,
              response.status_code == 200 and server.calls == before + 1 and server.get_paths[-1] == '/package')
        return response

    def refresh(profile_id, phase):
        wait_for_admission('provider_refreshes', profile_id)
        before = server.calls
        response = submit(owner, catalog_url, 'catalog_import_catalog_refresh', {'owner_password': password})
        check('Storefront explicit catalog refresh uses one fixed GET ' + phase,
              response.status_code == 200 and server.calls == before + 1
              and server.get_paths[-1] == '/reseller/*/packageTypes' and '20i hosting types saved' in response.text)
        return response

    def batch_fields(response, offset=None):
        fields = form_data(form_for(response, 'catalog_import_batch'))
        if fields.get('import_source') != 'package.types':
            raise AssertionError('Catalog batch lost its exact source')
        if offset is not None and fields.get('import_offset') != str(offset):
            raise AssertionError('Catalog batch changed the returned window')
        return fields

    def retry_matches(response, expected):
        fields = batch_fields(response)
        return all(fields.get(key) == expected.get(key) for key in
                   ('preview_hash', 'import_source', 'import_offset', 'term', 'sku_prefix', 'confirm')) and fields['owner_password'] == ''

    def capture(page, card, name, width, height):
        page.set_viewport_size({'width': width, 'height': height})
        if width == 320:
            expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
        expect(card).to_be_visible()
        page.evaluate('document.fonts.ready')
        card.evaluate("c => c.scrollIntoView({block:'center',inline:'nearest',behavior:'instant'})")
        check('Storefront capture has no horizontal page overflow ' + name,
              page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
        check('Storefront capture target clears the real header ' + name,
              card.evaluate("c => {const r=c.getBoundingClientRect(),h=document.querySelector('.admin-topbar').getBoundingClientRect();return r.top>=Math.max(0,h.bottom)&&r.bottom<=innerHeight&&r.left>=0&&r.right<=innerWidth+1;}"))
        path = qa / name
        card.screenshot(path=str(path))
        raw = path.read_bytes()
        if raw[:8] != b'\x89PNG\r\n\x1a\n':
            raise AssertionError('Chromium capture is not PNG')
        image_width, image_height = struct.unpack('!II', raw[16:24])
        captures.append({'name': name, 'path': name, 'sha256': hashlib.sha256(raw).hexdigest(),
                         'width': image_width, 'height': image_height,
                         'viewport': {'width': width, 'height': height},
                         'coverage': 'Actual element extent; surrounding page is not captured.'})
        progress()

    try:
        progress()
        native = json.loads((qa / 'provider-package-types-native-results.json').read_text(encoding='utf-8'))
        check('Storefront continuation requires actual successful dedicated native preparation',
              native.get('passed') is True and bool(native.get('checks')) and all(row['passed'] is True for row in native['checks']))
        check('Storefront uses existing installed package with exact current application bodies',
              installed.is_dir() and (installed / 'config.php').is_file() and media_root.is_dir()
              and all((installed / path).read_bytes() == (root / 'site' / path).read_bytes() for path in (
                  'app/providers/checks.php', 'app/providers/catalog_import.php', 'app/providers/catalog_import_document.php',
                  'admin/controllers/catalog.php', 'admin/views/catalog_import.php', 'admin/views/providers.php')))
        profile, = read('SELECT id,revision,enabled FROM ptici_provider_profiles')
        profile_id = profile['id']
        initial_imports = import_records()
        check('Storefront continuation retains original browser-uploaded artwork prerequisite',
              '7002' in initial_imports and initial_imports['7002']['media_id'] is not None
              and int(initial_imports['7002']['media_revision']) == 1 and not any(str(value) in initial_imports for value in range(8000, 8124)))
        recent, = read('SELECT COUNT(*) AS n FROM ptici_provider_refreshes WHERE actor_id=1 AND started_epoch>?', [int(time.time()) - 3600])
        check('Storefront reserves exactly two real catalog refresh slots without resetting history', int(recent['n']) <= 18)
        check('Storefront dedicated fixture has not already run connection checks', read('SELECT id FROM ptici_provider_checks') == [])
        original = product_state()
        image_url = base + '/provider-package-types/media/index.php?id=' + initial_imports['7002']['media_id']
        response = submit(owner, login_url, 'login', {'email': 'types1@example.invalid', 'password': password})
        check('Storefront owner enters the installed application through real login', response.status_code == 200 and 'Sales pipeline' in response.text)
        image = owner.get(image_url, timeout=15)
        check('Storefront starts with the real retained local PNG', image.status_code == 200 and image.content.startswith(b'\x89PNG\r\n\x1a\n'))
        server.mode, server.packages = 'success', []  # Accepted package-list probe shape; no invented item fields.
        before_calls = server.calls
        response = owner.get(settings_url, timeout=25)
        check('Storefront settings GET neither checks provider nor presents an unverified CTA', response.status_code == 200 and not cta(response) and server.calls == before_calls)
        response = connection('success', profile_id, 'initial success')
        links = cta(response)
        check('Current successful connection presents the exact direct-catalog destination', len(links) == 1 and urljoin(response.url, links[0]['href']) == catalog_url)
        latest, = read('SELECT c.profile_revision,r.state,r.result_code FROM ptici_provider_checks c JOIN ptici_provider_check_results r ON r.id=c.id ORDER BY c.started_epoch DESC,c.id DESC LIMIT 1')
        check('Storefront CTA follows a saved current success, not response text alone', int(latest['profile_revision']) == int(profile['revision']) and latest['state'] == 'verified' and latest['result_code'] == 'connected')
        before_calls = server.calls
        response = owner.get(settings_url + '&check_page=99999&history_page=99999', timeout=25)
        check('Storefront history pagination cannot hide current-success CTA or trigger provider', len(cta(response)) == 1 and server.calls == before_calls)

        context = browser.new_context(viewport={'width': 1440, 'height': 1000})
        context.add_cookies([{'name': cookie.name, 'value': cookie.value, 'domain': '127.0.0.1', 'path': cookie.path} for cookie in owner.cookies])
        blocked_urls, page_errors = [], []

        def local_only(route):
            parsed = urlparse(route.request.url)
            if parsed.scheme == 'http' and parsed.hostname == '127.0.0.1' and parsed.port == 8091:
                route.continue_()
            else:
                blocked_urls.append('non-local browser request')
                route.abort()

        context.route('**/*', local_only)
        page = context.new_page()
        page.on('pageerror', lambda error: page_errors.append(type(error).__name__))
        for name, width, height in [('Desktop', 1440, 1000), ('Mobile', 320, 1100)]:
            page.set_viewport_size({'width': width, 'height': height})
            page.goto(settings_url, wait_until='load')
            link = page.get_by_role('link', name='Import 20i Catalog', exact=True)
            expect(link).to_have_attribute('href', catalog_url)
            card = page.locator('section.workspace-card').filter(has=page.get_by_role('heading', name='Check the saved connection', exact=True))
            capture(page, card, 'Catalog-Storefront-Connection-' + name + '.png', width, height)
            with page.expect_navigation(wait_until='load'):
                link.click()
            check('Chromium current-success CTA opens actual catalog without a read ' + name, page.url == catalog_url and server.calls == before_calls)
        fields = form_data(form_for(owner.get(settings_url, timeout=25), 'provider_settings_save'))
        fields.update(label='Storefront reviewed connection', general_key='', current_password=password)
        response = owner.post(settings_url, data=fields, timeout=25)
        current, = read('SELECT revision FROM ptici_provider_profiles WHERE id=?', [profile_id])
        check('Actual settings revision invalidates prior success without checking provider', response.status_code == 200 and int(current['revision']) == int(profile['revision']) + 1 and not cta(response) and server.calls == before_calls)
        response = connection('authentication', profile_id, 'newer authentication failure')
        check('Newest failed actual connection cannot reuse the older success CTA', not cta(response) and 'rejected authentication' in response.text)
        response = connection('success', profile_id, 'restored current success')
        check('Later explicit current success restores the CTA', len(cta(response)) == 1)

        server.package_types = [{'id': 7002, 'label': 'Catalog-only browser'}] + [
            {'id': number, 'label': ('Storefront <b>choice</b> & ' if number == 8001 else 'Storefront type ') + str(number),
             'image': 'https://private.invalid/provider-image', 'price': 999, 'customer_key': 'PRIVATE_STOREFRONT_FIELD'}
            for number in range(8000, 8124)]
        server.package_types_mode = 'success'
        source_rows = {str(row['id']): row['label'] for row in server.package_types}
        ordered_ids = sorted(source_rows)
        response = refresh(profile_id, 'first125')
        check('Actual 125-type refresh leaves every original product and image unchanged', product_state() == original)
        check('Storefront catalog response does not expose unknown provider metadata', all(marker not in response.text for marker in ('PRIVATE_STOREFRONT_FIELD', 'https://private.invalid/provider-image')) and '<b>choice</b>' in soup(response).get_text() and soup(response).find('b', string='choice') is None)
        fields = batch_fields(response, 0)
        fields.update(term='month', sku_prefix='STOREFRONT', confirm='1', owner_password=password)
        first_hash = fields['preview_hash']
        check('Actual catalog offers the fixed-window form for all125 named types', '125 named hosting type(s)' in soup(response).get_text(' ', strip=True) and len(soup(response).select('[name=action][value=catalog_import_create]')) < 50)
        before = product_state()
        response = owner.post(catalog_url, data=dict(fields, csrf='invalid'), timeout=25)
        check('Batch HTTP CSRF refusal preserves exact review fields and all product bytes', response.status_code == 200 and 'session expired' in response.text.lower() and retry_matches(response, fields) and product_state() == before)
        unconfirmed = dict(fields)
        unconfirmed.pop('confirm')
        response = owner.post(catalog_url, data=unconfirmed, timeout=25)
        check('Batch HTTP requires explicit consent without importing or prechecking retry', response.status_code == 200 and 'Confirm importing' in response.text and 'confirm' not in batch_fields(response) and product_state() == before)
        response = owner.post(catalog_url, data=dict(fields, import_source='packages.list'), timeout=25)
        check('Batch source substitution cannot consume a direct-catalog review', response.status_code == 200 and 'Refresh hosting inventory' in response.text and product_state() == before)
        response = owner.post(catalog_url, data=dict(fields, import_offset='126'), timeout=25)
        check('Batch offset beyond the saved catalog is refused and retained', response.status_code == 200 and 'Reload the saved catalog batch' in response.text and batch_fields(response)['import_offset'] == '126' and product_state() == before)

        for name, width, height in [('Desktop', 1440, 1000), ('Mobile', 320, 1100)]:
            page.set_viewport_size({'width': width, 'height': height})
            page.goto(catalog_url, wait_until='load')
            form = page.locator('form').filter(has=page.locator('[name=action][value=catalog_import_batch]'))
            form.locator('[name=sku_prefix]').fill('STOREFRONT')
            form.locator('[name=term]').select_option('month')
            form.locator('[name=confirm]').check()
            form.locator('[name=owner_password]').fill('wrong')
            with page.expect_navigation(wait_until='load'):
                form.get_by_role('button', name='Import this batch', exact=True).click()
            expect(page.get_by_role('alert')).to_contain_text('Your current password is not correct.')
            form = page.locator('form').filter(has=page.locator('[name=action][value=catalog_import_batch]'))
            check('Chromium batch password refusal keeps exact source hash and window ' + name,
                  all(form.locator('[name=' + key + ']').input_value() == fields[key] for key in
                      ('preview_hash', 'import_source', 'import_offset', 'term', 'sku_prefix'))
                  and form.locator('[name=confirm]').is_checked() and form.locator('[name=owner_password]').input_value() == '' and product_state() == before)
            card = page.locator('.catalog-review').filter(has=page.get_by_role('heading', name='Import the returned hosting catalog', exact=True))
            capture(page, card, 'Catalog-Storefront-Batch-' + name + '.png', width, height)
            button = form.get_by_role('button', name='Import this batch', exact=True)
            button.click(trial=True)
            check('Chromium batch submit remains an unobscured hit target ' + name,
                  button.evaluate("b => {const r=b.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight&&[[.5,.5],[.1,.1],[.9,.9]].every(([x,y])=>{const h=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);return h===b||b.contains(h);});}"))
        # One actual Chromium submission creates the first window; errors above never do.
        form.locator('[name=owner_password]').fill(password)
        with page.expect_navigation(wait_until='load'):
            form.get_by_role('button', name='Import this batch', exact=True).click()
        query = parse_qs(urlparse(page.url).query)
        check('Chromium first batch returns exact continuation and selected choices',
              query == {'view': ['catalog'], 'import_source': ['package.types'], 'batch_term': ['month'], 'batch_prefix': ['STOREFRONT'], 'import_offset': ['50']})
        first = import_records()
        check('First window imports exactly49 new types beyond the visible20-row page',
              set(first) - set(initial_imports) == set(ordered_ids[:50]) - set(initial_imports)
              and len(set(first) - set(initial_imports)) == 49 and '49 draft(s) created.' in page.content())
        check('First batch preserves original provenance products and uploaded files', retained_originals(original, product_state()))
        before = product_state()
        response = owner.post(catalog_url, data=fields, timeout=25)
        check('Replaying captured first window does not advance into unseen types', response.status_code == 200 and '0 draft(s) created.' in response.text and parse_qs(urlparse(response.url).query).get('import_offset') == ['50'] and product_state() == before)
        continuation = batch_fields(response, 50)
        continuation.update(owner_password=password, confirm='1')
        response = refresh(profile_id, 'newer125')
        fresh_hash = batch_fields(response, 0)['preview_hash']
        check('New actual catalog observation changes review identity without changing products', fresh_hash != first_hash and product_state() == before)
        response = owner.post(catalog_url, data=continuation, timeout=25)
        check('Captured next window refuses a newer observation without silently rebasing', response.status_code == 200 and 'changed' in response.text and retry_matches(response, continuation) and product_state() == before and batch_fields(response)['preview_hash'] == first_hash)
        for offset, created, remaining in ((50, 50, 25), (100, 25, 0)):
            url = catalog_url + '&batch_term=month&batch_prefix=STOREFRONT&import_offset=' + str(offset)
            response = owner.get(url, timeout=25)
            next_fields = batch_fields(response, offset)
            check('Continuation uses the newly reviewed exact observation at window ' + str(offset), next_fields['preview_hash'] == fresh_hash and next_fields['term'] == 'month' and next_fields['sku_prefix'] == 'STOREFRONT')
            next_fields.update(owner_password=password, confirm='1')
            prior = import_records()
            response = owner.post(url, data=next_fields, timeout=60)
            after = import_records()
            check('Actual fixed window creates only its exact selected types ' + str(offset), response.status_code == 200 and set(after) - set(prior) == set(ordered_ids[offset:offset + 50]) and len(after) - len(prior) == created)
            check('Actual continuation reports accurate remaining count ' + str(offset), str(created) + ' draft(s) created.' in response.text and str(remaining) + ' type(s) remain to import.' in response.text and parse_qs(urlparse(response.url).query).get('import_offset') == (['100'] if offset == 50 else None))
            before_replay = product_state()
            replay = owner.post(url, data=next_fields, timeout=60)
            check('Replayed continuation cannot duplicate or cross windows ' + str(offset), replay.status_code == 200 and '0 draft(s) created.' in replay.text and product_state() == before_replay)
        after = product_state()
        products = {str(row['id']): row for row in after['catalog_products']}
        imported = import_records()
        new_ids = set(imported) - set(initial_imports)
        check('All124 new types are unpriced hosting drafts with owner-selected period only', len(new_ids) == 124 and all(
            products[str(imported[type_id]['product_id'])]['sku'] == 'STOREFRONT-' + type_id
            and products[str(imported[type_id]['product_id'])]['title'] == source_rows[type_id]
            and products[str(imported[type_id]['product_id'])]['status'] == 'draft'
            and products[str(imported[type_id]['product_id'])]['family'] == 'hosting'
            and products[str(imported[type_id]['product_id'])]['term'] == 'month'
            and products[str(imported[type_id]['product_id'])]['amount_cents'] is None
            and products[str(imported[type_id]['product_id'])]['cost_cents'] is None
            and products[str(imported[type_id]['product_id'])]['features'] == ''
            and products[str(imported[type_id]['product_id'])]['terms'] == ''
            and imported[type_id]['media_id'] is None for type_id in new_ids))
        check('Completed125-type import preserves every earlier row and original local image', set(source_rows).issubset(imported) and retained_originals(original, after) and owner.get(image_url, timeout=15).content == image.content)
        before_calls = server.calls
        response = owner.get(catalog_url, timeout=25)
        settings = owner.get(settings_url, timeout=25)
        check('Completed catalog and connection GET remain local with no automatic provider read', response.status_code == 200 and settings.status_code == 200 and server.calls == before_calls)
        check('Storefront browser made no external request and has no script errors', not blocked_urls and not page_errors)
        check('Storefront uses exactly3 connection and2 catalog GETs with no provider writes',
              server.calls - calls_before == 5 and server.get_paths[paths_before:] == ['/package'] * 3 + ['/reseller/*/packageTypes'] * 2
              and len(server.posts) == posts_before and server.errors == errors_before)
        check('Storefront retains all four actual new captures', tuple(item['name'] for item in captures) == CAPTURES)
        result['passed'] = True
        progress()
        return result
    except BaseException as error:
        primary_error = True
        result['failure'] = type(error).__name__  # No response body, session, secret or SQL parameters in QA.
        progress()
        raise
    finally:
        server.mode, server.packages, server.package_types, server.package_types_mode = saved_server
        cleanup_errors = []
        for resource in (context, owner):
            if resource is not None:
                try:
                    resource.close()
                except BaseException as error:
                    cleanup_errors.append(error)
        if cleanup_errors:
            result['passed'] = False
            result['cleanup_failures'] = [type(error).__name__ for error in cleanup_errors]
            progress()
            if not primary_error:
                raise cleanup_errors[0]


if __name__ == '__main__':
    raise SystemExit('Use the existing packaged provider-package-types browser continuation; this is not a standalone runner.')
