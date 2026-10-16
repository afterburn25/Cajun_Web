"""Isolated actual packaged licensing HTTP/forms/downloads; no Website_Core execution."""
import hashlib
import json
import os
import subprocess
import zipfile
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form

OWNER = 'Synthetic licensing HTTP passphrase 2026'
CLIENT = 'Synthetic licensing client passphrase 2026'


def run_browser(env, browser):
    root, web, qa, base, check = (env[k] for k in ['ROOT', 'WEB', 'QA', 'BASE', 'check'])
    soup, token, submit = (env[k] for k in ['soup', 'token', 'submit'])
    dest = web / 'license-release'
    check('Licensing release HTTP fixture starts absent', not dest.exists() and not db('SHOW TABLES LIKE "licweb_settings"'))
    inherited = {t: db('SELECT * FROM ci_' + t + ' ORDER BY ' + ('seq' if t == 'provider_refreshes' else 'id'))
                 for t in ['users', 'staff_profiles', 'provider_refreshes', 'provider_refresh_results', 'audit'] if t != 'staff_profiles'}
    inherited['staff_profiles'] = db('SELECT * FROM ci_staff_profiles ORDER BY user_id')
    provider = env['provider_server']; calls, posts = provider.calls, list(provider.posts)
    packages = list((root / 'dist').glob('CajunWebPro-*-COMPLETE-UPLOAD.zip'))
    check('Licensing HTTP uses exactly one built complete package', len(packages) == 1)
    dest.mkdir()
    with zipfile.ZipFile(packages[0]) as package:
        package.extractall(dest)

    def fixture(operation):
        result = subprocess.run([env['php'], str(root / 'tests/license_release_browser_fixture.php'), operation],
                                text=True, capture_output=True, timeout=120,
                                env={**os.environ, 'CWP_TEST_DATABASE': 'cwp_test'})
        check('Licensing isolated fixture command ' + operation + ' succeeds', result.returncode == 0 and result.stderr == '')
        return json.loads(result.stdout)

    check('Licensing HTTP seeds a genuine unmarked populated pre-master schema', fixture('seed') == {'seeded': True, 'prefix': 'licweb_', 'old_invoice_max': 900}
          and not db("SELECT * FROM licweb_settings WHERE name IN ('license_schema_version','license_invoice_cutover')")
          and 'invoice_id' not in [r['Field'] for r in db('SHOW FULL COLUMNS FROM licweb_website_licenses')])
    admin = base + '/license-release/admin/index.php'; page_url = admin + '?view=studio-licenses'
    portal = base + '/license-release/portal/index.php'; download = base + '/license-release/api/bundle-download.php?id='
    diagnostics = []

    def evidence(label, response, errors=()):
        actual = [n.get_text(' ', strip=True) for n in soup(response).select('.notice.error')]
        diagnostics.append({'step': label, 'status': response.status_code, 'errors': actual,
                            'redirect_statuses': [r.status_code for r in response.history]})
        (qa / 'licensing-release-response-diagnostics.json').write_text(json.dumps(diagnostics, indent=2))
        check('Licensing response ' + label + ' has exact expected status and errors', response.status_code == 200 and actual == list(errors))

    def state():
        return {t: db('SELECT * FROM licweb_' + t + ' ORDER BY ' + ('name' if t == 'settings' else 'id'))
                for t in ['settings', 'website_licenses', 'license_domains', 'license_bundles', 'audit']}

    def raw_post(session, action, **changes):
        current = session.get(page_url, timeout=25)
        return session.post(page_url, data={'csrf': token(current), 'action': action, **changes}, timeout=30)

    owner = requests.Session(); login = submit(owner, admin + '?view=login', 'login', {'email': 'license-owner@example.invalid', 'password': OWNER})
    check('Licensing isolated current owner signs in through the actual login', login.status_code == 200 and urlparse(login.url).query == 'view=dashboard')
    initial = state(); response = owner.get(page_url, timeout=25)
    check('Licensing setup is visible before populated legacy storage is ready and GET makes no schema writes',
          response.status_code == 200 and soup(response).select_one('input[name=action][value=studio_license_setup]') is not None and state() == initial)

    def capture(session, name, address, selector):
        context = browser.new_context(viewport={'width': 1440, 'height': 1000})
        context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in session.cookies])
        page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        for width, height, label in [(1440, 1000, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.goto(address); page.evaluate('document.fonts.ready')
            expect(page.locator(selector)).to_be_visible(); page.screenshot(path=str(qa / (name + '-' + label + '.png')), full_page=True)
            check(name + ' ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
        check(name + ' has no script errors', not errors); context.close()

    capture(owner, 'Licensing-Legacy-Setup', page_url, 'form:has(input[name=action][value=studio_license_setup])')
    for label, changes, reason in [('CSRF', {'csrf': 'invalid'}, 'Your session expired. Refresh the page and try again.'),
                                    ('password', {'current_password': 'wrong'}, 'Your current password is not correct.')]:
        response = submit(owner, page_url, 'studio_license_setup', {'current_password': OWNER, **changes})
        evidence('setup rejects ' + label, response, [reason])
        check('Licensing setup denied ' + label + ' preserves all original rows and audit', state() == initial)
    response = submit(owner, page_url, 'studio_license_setup', {'current_password': OWNER}); evidence('setup upgrades populated legacy storage', response)
    after = state()
    check('Licensing actual owner setup records exact old invoice boundary and preserves every old license domain bundle',
          {r['name']: r['value'] for r in after['settings']}['license_invoice_cutover'] == '900'
          and {r['name']: r['value'] for r in after['settings']}['license_schema_version'] == '3'
          and all(len(after[t]) == len(initial[t]) and all({k: a[k] for k in b} == b for a, b in zip(after[t], initial[t])) for t in ['website_licenses', 'license_domains', 'license_bundles'])
          and all(r in after['audit'] for r in initial['audit']))
    held = state(); outcome = fixture('old-fulfillment')
    check('Licensing real fulfillment entry refuses pre-cutover replay without minting a duplicate', outcome == {'refused': True, 'reason': 'This older invoice needs an explicit owner licensing review before fulfillment.'} and state() == held)

    staff = requests.Session(); submit(staff, admin + '?view=login', 'login', {'email': 'license-staff@example.invalid', 'password': OWNER})
    check('Licensing ordinary administrator has no master option or legacy review form', 'name="master"' not in staff.get(page_url).text and 'value="studio_license_legacy_review"' not in staff.get(page_url).text)
    before = state(); response = raw_post(staff, 'studio_license_create', customer_id='1', product_id='WEBSITE-RESTAURANT', master='1')
    evidence('ordinary administrator cannot create master', response, ['Your account does not have access to this action.'])
    check('Licensing rejected master creation preserves all license history', state() == before)
    response = submit(owner, page_url, 'studio_license_create', {'customer_id': '1', 'product_id': 'WEBSITE-RESTAURANT', 'master': '1'})
    evidence('owner creates internal master', response)
    master = db('SELECT * FROM licweb_website_licenses WHERE is_master=1')[0]
    master_bundle = fixture('master-bundle')['bundle_id']
    before = state(); response = raw_post(owner, 'studio_license_bundle', id=str(master['id']), industry='restaurant')
    evidence('owner cannot package internal master', response, ['Master license keys are internal and cannot be bundled for delivery.'])
    check('Licensing master bundle rejection does not execute builder or add history', state() == before)

    client = requests.Session(); foreign = requests.Session()
    for session, ident in [(client, 1), (foreign, 2)]:
        response = submit(session, portal, 'login', {'email': 'license-client' + str(ident) + '@example.invalid', 'password': CLIENT})
        check('Licensing client ' + str(ident) + ' signs in through actual portal', response.status_code == 200 and 'Welcome,' in response.text)
    response = client.get(portal)
    check('Licensing actual customer portal hides master key prefix forms and historical master download',
          master['license_key'] not in response.text and master['license_key'][:8] not in response.text
          and soup(response).select_one('input[name=license_id][value="' + str(master['id']) + '"]') is None
          and 'id=' + str(master_bundle) not in ' '.join(n.get('href', '') for n in soup(response).select('a[href*="bundle-download.php"]'))
          and '2222-222' in response.text and '3333-333' not in response.text)
    capture(client, 'Licensing-Customer-Downloads', portal, 'article:has(input[name=license_id][value="2"])')
    expected_bytes = (root / 'test-runtime/license-release-private/bundles/synthetic.zip').read_bytes()
    response = client.get(download + '2', timeout=25)
    check('Licensing actual legacy bundle download retains exact bytes and headers with NULL checksum', response.status_code == 200 and response.content == expected_bytes and response.headers.get('Content-Type') == 'application/zip' and response.headers.get('Cache-Control') == 'private, no-store' and db('SELECT sha256 FROM licweb_license_bundles WHERE id=2') == [{'sha256': None}])
    digest = hashlib.sha256(expected_bytes).hexdigest()
    db('UPDATE licweb_license_bundles SET sha256=? WHERE id=2', [digest])
    response = client.get(download + '2', timeout=25)
    check('Licensing actual non-null bundle checksum allows original exact bytes', response.status_code == 200 and response.content == expected_bytes and db('SELECT sha256 FROM licweb_license_bundles WHERE id=2') == [{'sha256': digest}])
    bundle_file = root / 'test-runtime/license-release-private/bundles/synthetic.zip'
    before_integrity = db("SELECT * FROM licweb_audit WHERE action='bundle.download.integrity_failed' ORDER BY id")
    try:
        bundle_file.write_bytes(expected_bytes + b'SYNTHETIC CORRUPTION')
        response = client.get(download + '2', timeout=25)
        check('Licensing actual corrupted bundle is refused and audited without download', response.status_code == 404 and response.json() == {'error': 'Bundle file is unavailable'}
              and len(db("SELECT * FROM licweb_audit WHERE action='bundle.download.integrity_failed' ORDER BY id")) == len(before_integrity) + 1)
    finally:
        bundle_file.write_bytes(expected_bytes)
    response = client.get(download + '2', timeout=25)
    check('Licensing exact original bundle restoration restores checksum-verified download', response.status_code == 200 and response.content == expected_bytes and hashlib.sha256(bundle_file.read_bytes()).hexdigest() == digest)
    for label, session, ident, status in [('master', client, master_bundle, 404), ('revoked', client, 1, 404), ('foreign', foreign, 2, 404), ('anonymous', requests.Session(), 2, 403)]:
        response = session.get(download + str(ident), timeout=25)
        check('Licensing actual bundle endpoint denies ' + label + ' without ZIP or key', response.status_code == status and response.content != expected_bytes and master['license_key'] not in response.text)
    for action, extra in [('license_domain_add', {'domain': 'blocked.example'}), ('license_domain_deactivate', {'domain': 'blocked.example'}), ('license_domain_replace', {'old_domain': 'blocked.example', 'new_domain': 'blocked2.example'})]:
        before = state(); response = client.post(portal, data={'csrf': token(client.get(portal)), 'action': action, 'license_id': str(master['id']), **extra})
        evidence('customer master ' + action + ' refusal', response, ['License not found.'])
        check('Licensing forbidden master portal action ' + action + ' preserves all license history', state() == before)

    original_invoices = db('SELECT * FROM licweb_studio_invoices ORDER BY id')
    original_snapshots = db('SELECT * FROM licweb_catalog_invoice_snapshots ORDER BY id')
    review = {'invoice_id': '900', 'license_id': '1', 'confirm': '1', 'evidence': 'Owner checked exact original synthetic purchase record', 'current_password': OWNER}
    cases = [('CSRF', owner, {'csrf': 'invalid'}, 'Your session expired. Refresh the page and try again.'),
             ('password', owner, {'current_password': 'wrong'}, 'Your current password is not correct.'),
             ('confirmation', owner, {'confirm': '0'}, 'Confirm the exact invoice decision and record its evidence (10–200 characters).'),
             ('foreign license', owner, {'license_id': '3'}, 'Choose an unlinked ordinary license for this exact client and product.'),
             ('master license', owner, {'license_id': str(master['id'])}, 'Choose an unlinked ordinary license for this exact client and product.'),
             ('nonowner', staff, {}, 'Your account does not have access to this action.')]
    for label, session, changes, reason in cases:
        before = state(); response = raw_post(session, 'studio_license_legacy_review', **{**review, **changes})
        evidence('legacy review rejects ' + label, response, [reason])
        check('Licensing legacy review denied ' + label + ' preserves all original license history', state() == before)
    capture(owner, 'Licensing-Owner-Invoice-Review', page_url, 'form:has(input[name=action][value=studio_license_legacy_review])')
    before = state(); response = submit(owner, page_url, 'studio_license_legacy_review', review); evidence('owner explicitly links original revoked license', response)
    check('Licensing actual owner review attaches exact license without reactivation or second license', db('SELECT invoice_id,is_active,is_master FROM licweb_website_licenses WHERE id=1') == [{'invoice_id': 900, 'is_active': 0, 'is_master': 0}]
          and len(state()['website_licenses']) == len(before['website_licenses']) and state()['license_bundles'] == before['license_bundles']
          and len(db("SELECT id FROM licweb_audit WHERE action='license.legacy.resolve'")) == 1)
    before = state(); response = submit(owner, page_url, 'studio_license_legacy_review', {**review, 'license_id': '0'})
    evidence('replayed decision remains refused', response, ['This invoice already has a license. Review its existing record.'])
    check('Licensing replay of exact owner review cannot create or change a license', state() == before)
    response = submit(owner, page_url, 'studio_license_legacy_review', {**review, 'invoice_id': '899', 'license_id': '0', 'evidence': 'Owner verified original synthetic purchase was never fulfilled'})
    evidence('owner deliberately creates never-fulfilled license', response)
    check('Licensing explicit new decision creates exactly one ordinary invoice-linked license', len(db('SELECT id FROM licweb_website_licenses WHERE invoice_id=899 AND is_master=0')) == 1 and len(db("SELECT id FROM licweb_audit WHERE action='license.legacy.resolve'")) == 2)
    check('Licensing review preserves every original paid invoice and snapshot field', db('SELECT * FROM licweb_studio_invoices ORDER BY id') == original_invoices and db('SELECT * FROM licweb_catalog_invoice_snapshots ORDER BY id') == original_snapshots)

    # Use actual support setup and grant. No borrowed client cookie establishes support authority.
    support = admin + '?view=support'
    response = submit(owner, support, 'support_migrate', {'owner_password': OWNER, 'confirm': '1'})
    evidence('isolated support preparation', response)
    check('Licensing support setup is explicitly ready before opening a session', 'Support access is ready.' in response.text
          and db("SELECT name,value FROM licweb_settings WHERE name IN ('support_schema_version','support_policy_confirmed') ORDER BY name") == [{'name': 'support_policy_confirmed', 'value': '1'}, {'name': 'support_schema_version', 'value': '1'}])
    response = chosen_form(owner, support, 'support_policy', 'client_id', 1, {'allowed': '1', 'owner_password': OWNER})
    evidence('isolated support permission', response)
    check('Licensing support grant is saved for only the selected client', db('SELECT client_id,allowed FROM licweb_support_clients') == [{'client_id': 1, 'allowed': 1}])
    response = chosen_form(owner, support, 'support_start', 'client_id', 1, {})
    sid = parse_qs(urlparse(response.url).query).get('support', [''])[0]
    check('Licensing fixture enters a real audited support session', len(sid) == 32 and response.status_code == 200 and 'Support session' in response.text)
    response = owner.get(download + '2&support=' + sid)
    check('Licensing actual support bundle request cannot disclose customer ZIP', response.status_code == 403 and response.content != expected_bytes)
    response = owner.get(portal + '?support=' + sid)
    check('Licensing actual support portal omits ordinary and master license controls', response.status_code == 200 and '2222-222' not in response.text and master['license_key'][:8] not in response.text and not soup(response).select('input[name=license_id]'))
    check('Licensing isolated HTTP suite preserves original staff provider intents results and audit',
          all(db('SELECT * FROM ci_' + t + ' ORDER BY ' + ('seq' if t == 'provider_refreshes' else 'user_id' if t == 'staff_profiles' else 'id')) == rows for t, rows in inherited.items())
          and provider.calls == calls and provider.posts == posts)
    check('Licensing HTTP diagnostic output excludes passwords master key and database paths', all(secret not in (qa / 'licensing-release-response-diagnostics.json').read_text() for secret in [OWNER, CLIENT, master['license_key'], str(root), str(dest)]))
    for session in [owner, staff, client, foreign]:
        session.close()
