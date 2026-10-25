"""Additive real published1.25 upgrade case. No provider or SMTP network transport."""
from pathlib import Path, PurePosixPath
from urllib.parse import parse_qs, urlparse, urljoin
import datetime
import hashlib
import io
import json
import os
import re
import subprocess
import zipfile

import requests
from bs4 import BeautifulSoup
from release_126_retention import retained, exact_business, SCHEMA_MARKER_CELLS

BASELINE_SHA = 'bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39'
BASELINE_SOURCE = 'b79c67110829126916e3a65e616191542448bccc'
OWNER = 'Synthetic organized upgrade passphrase'
CLIENT = 'Synthetic original release client passphrase 2026'
STAFF = 'Synthetic original release staff passphrase 2026'


def unpack(path, destination, expected_sha=None):
    if expected_sha and hashlib.sha256(path.read_bytes()).hexdigest() != expected_sha:
        raise AssertionError('Immutable published baseline digest changed.')
    with zipfile.ZipFile(path) as archive:
        names = archive.namelist()
        assert len(names) == len(set(names)) == len({name.casefold() for name in names}) and archive.testzip() is None
        for member in archive.infolist():
            name = PurePosixPath(member.filename)
            assert not name.is_absolute() and str(name) == member.filename and all(part not in ('', '.', '..') for part in member.filename.split('/'))
            assert not any(char in member.filename for char in ('\\', ':', '\x00')) and all(ord(char) >= 32 for char in member.filename)
            assert not member.is_dir() and ((member.external_attr >> 16) & 0o170000) in (0, 0o100000) and not member.flag_bits & 1
        archive.extractall(destination)


def fields(response, action, selector=None, field='action'):
    assert response.status_code == 200, ('Form response status', action, response.status_code)
    matches = []
    for form in BeautifulSoup(response.text, 'html.parser').select('form'):
        markers = form.select('[name="' + field + '"]')
        if not any(marker.get('value') == action and not marker.has_attr('disabled') for marker in markers):
            continue
        if selector and not all(form.select_one('[name="' + k + '"]') is not None
                                and form.select_one('[name="' + k + '"]').get('value') == str(v)
                                for k, v in selector.items()):
            continue
        data = {}
        for control in form.select('input[name], textarea[name], select[name]'):
            if control.has_attr('disabled') or control.get('type') in ['submit', 'button', 'file', 'password']:
                continue
            if control.get('type') in ['checkbox', 'radio'] and not control.has_attr('checked'):
                continue
            if control.name == 'textarea':
                value = control.get_text()
            elif control.name == 'select':
                option = control.select_one('option[selected]') or control.select_one('option')
                value = option.get('value', option.get_text()) if option else ''
            else:
                value = control.get('value', '')
            data[control['name']] = value
        # A single updater form has three action buttons; submit only the selected button.
        data[field] = action
        matches.append((urljoin(response.url, form.get('action') or response.url), data))
    assert len(matches) == 1, ('Expected one exact form', action, len(matches))
    return matches[0]


def target_version(repo):
    value = (Path(repo) / "VERSION").read_text(encoding="utf-8").strip()
    assert re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+", value), "Invalid candidate version"
    return value


def run(repo, installed, base, prefix, php, check, qa, browser=None, *, document_root, hosting_evidence):
    """Apache already serves installed (at / or /subfolder); DB prefix must be unused."""
    repo, installed, qa = Path(repo), Path(installed), Path(qa)
    document_root = Path(document_root).resolve()
    assert document_root in (installed.resolve(), installed.resolve().parent)
    assert document_root.is_relative_to((repo / 'test-runtime').resolve())
    assert os.environ.get('CWP_TEST_DATABASE') == 'cwp_test'
    assert installed.is_relative_to(repo / 'test-runtime')
    baseline = repo / 'test-runtime/CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip'
    candidate_version = target_version(repo)
    updates = list((repo / 'dist').glob('CajunWebPro-' + candidate_version + '-UPDATE.zip'))
    assert len(updates) == 1, 'Bind this release fixture only after candidate scope/version freeze.'
    unpack(baseline, installed, BASELINE_SHA)
    checks = []

    def ok(name, value):
        checks.append({'name': name, 'passed': bool(value)})
        check(prefix + ' ' + name, value)

    def command(helper, mode):
        arguments = [php, str(repo / 'tests' / helper), str(installed), mode]
        if helper == 'release_126_provider_fixture.php' and mode in ('catalog-read', 'catalog-state', 'catalog-replay'):
            arguments.append(candidate_version)
        if helper == 'organized_update_fixture.php':
            arguments.append(str(document_root))
        result = subprocess.run(arguments,
                                capture_output=True, text=True, timeout=120)
        assert result.returncode == 0, ('Release fixture failed', helper, mode, result.stderr)
        return json.loads(result.stdout)

    def snapshot():
        return command('release_126_retention_fixture.php', 'snapshot')

    def identity():
        return command('release_126_retention_fixture.php', 'identity')

    def old_records(mode='snapshot'):
        return command('organized_update_fixture.php', mode)

    def submit(session, target, action, values=None, selector=None, files=None, field='action'):
        target, data = fields(session.get(target, timeout=120), action, selector, field)
        data.update(values or {})
        return session.post(target, data=data, files=files, timeout=120)

    admin = base + '/admin/index.php'
    portal = base + '/portal/index.php'
    clients = admin + '?view=studio-clients'

    def login(email, password, client=False):
        session = requests.Session()
        response = submit(session, portal if client else admin + '?view=login', 'login',
                          {'email': email, 'password': password})
        ok(('Client' if client else 'Owner') + ' actual fresh login ' + email,
           response.status_code == 200 and ('Welcome,' in response.text if client else ('Sales pipeline' in response.text or 'Client workspace' in response.text)))
        return session

    session = requests.Session()
    install = base + '/install.php'
    target, data = fields(session.get(install, timeout=30), 'install')
    data.update({'db_host': os.environ['CWP_TEST_HOST'], 'db_port': os.environ['CWP_TEST_PORT'],
                 'db_name': 'cwp_test', 'db_user': os.environ['CWP_TEST_USER'],
                 'db_password': os.environ['CWP_TEST_PASSWORD'], 'db_prefix': prefix,
                 'owner_name': 'Organized fixture owner', 'owner_email': 'organized@example.invalid',
                 'owner_password': OWNER, 'owner_confirm': OWNER, 'site_url': base,
                 'confirm_install': '1', 'seed': '1', 'preview': '1'})
    response = session.post(target, data=data, timeout=120)
    result = response.json()
    ok('Actual published1.25 installer succeeds', response.status_code == 200 and result.get('ok'))
    private = Path(result['storage'])
    ok('Original private store is outside the served document root', not private.is_relative_to(document_root))
    ok('Original installed code reports1.25', identity()['version'] == '1.25.0')
    old_records('seed')
    owner = login('organized@example.invalid', OWNER)
    staff_url = admin + '?view=staff'
    response = submit(owner, staff_url, 'staff_save',
                      {'name': 'Original restricted administrator', 'email': 'retained-staff@example.invalid',
                       'role': 'administrator', 'enabled': '1', 'permissions[]': ['clients.manage'], 'owner_password': OWNER}, {'id': 0})
    staff_rows = [row for row in identity()['users'] if row['email'] == 'retained-staff@example.invalid']
    ok('Original UI creates one restricted administrator', response.status_code == 200 and len(staff_rows) == 1)
    response = submit(owner, staff_url, 'staff_invite', {'owner_password': OWNER}, {'id': staff_rows[0]['id']})
    links = BeautifulSoup(response.text, 'html.parser').select('[data-staff-invite]')
    ok('Original staff invitation is shown once', len(links) == 1)
    link = urljoin(base + '/', links[0]['href']); codes = parse_qs(urlparse(link).fragment).get('token', [])
    ok('Original staff setup has its separate single-use token', len(codes) == 1 and len(codes[0]) == 64)
    response = submit(requests.Session(), link, 'staff_activate', {'token': codes[0], 'password': STAFF, 'confirm_password': STAFF})
    ok('Original administrator activates through its current form', response.status_code == 200 and 'name="email"' in response.text)
    restricted = login('retained-staff@example.invalid', STAFF)
    ok('Original administrator retains client permission without provider authority', restricted.get(clients, timeout=120).status_code == 200
       and restricted.get(admin + '?view=provider-settings', timeout=120).status_code == 403)
    first = identity()['clients'][0]
    response = submit(owner, clients, 'studio_client',
                      {'name': 'Other retained client', 'email': 'other-retained@example.invalid'}, {'id': 0})
    ids = identity()
    second = next(row for row in ids['clients'] if row['email'] == 'other-retained@example.invalid')
    ok('Original UI creates distinct second client', response.status_code == 200 and first['id'] != second['id'])
    clients_sessions = []
    for row in [first, second]:
        response = submit(owner, clients, 'studio_invite', selector={'id': row['id']})
        links = BeautifulSoup(response.text, 'html.parser').select('.notice.info a[href*="invite="]')
        ok('Original invitation exists for client ' + str(row['id']), len(links) == 1)
        activate = requests.Session()
        response = submit(activate, urljoin(base + '/', links[0]['href']), 'activate',
                          {'password': CLIENT, 'confirm_password': CLIENT})
        ok('Original client activation returns login', response.status_code == 200 and 'name="email"' in response.text)
        clients_sessions.append(login(row['email'], CLIENT, True))
    alice, bob = clients_sessions
    job_id = next(row['id'] for row in identity()['jobs'] if row['client_id'] == first['id'])
    job = admin + '?view=studio-job&id=' + str(job_id)
    client_job = portal + '?job=' + str(job_id)
    response = submit(owner, job, 'studio_message', {'body': 'Original owner project message'})
    ok('Original owner message persists', 'Original owner project message' in response.text)
    response = submit(alice, client_job, 'message', {'body': 'Original direct-client project message'})
    ok('Original direct-client message persists', 'Original direct-client project message' in response.text)
    image = (installed / 'images/icons/favicon.png').read_bytes()
    response = submit(alice, client_job, 'upload', files={'document': ('retained-original.png', image, 'image/png')})
    file_rows = [row for row in identity()['files'] if row['original_name'] == 'retained-original.png']
    ok('Original private upload has exact project identity', response.status_code == 200 and len(file_rows) == 1 and file_rows[0]['job_id'] == job_id)
    file_url = portal + '?file=' + file_rows[0]['id']
    archive = io.BytesIO()
    with zipfile.ZipFile(archive, 'w') as package:
        for name, body in {'index.html': '<!doctype html><title>Retained website</title><h1>Original delivery</h1>',
                           'HANDOVER.md': 'Upload these static files. No database or provider call is needed.',
                           'LICENSES.md': 'These synthetic files were authored for this release test.'}.items():
            package.writestr(zipfile.ZipInfo(name, date_time=(2026, 10, 12, 0, 0, 0)), body)
    delivery_bytes = archive.getvalue()
    deliveries = admin + '?view=deliveries'
    page = owner.get(deliveries, timeout=120)
    if BeautifulSoup(page.text, 'html.parser').select_one('[name=action][value=delivery_setup]'):
        submit(owner, deliveries, 'delivery_setup', {'owner_password': OWNER, 'confirm': '1'})
    response = submit(owner, deliveries, 'delivery_upload',
                      {'job_id': str(job_id), 'version': 'original-1.25', 'title': 'Original published website',
                       'notes': 'Original static handover retained through release recovery.', 'scope': 'static'},
                      files={'archive': ('retained-website.zip', delivery_bytes, 'application/zip')})
    release_ids = parse_qs(urlparse(response.url).query).get('release', [])
    ok('Original package prepares one private website release', response.status_code == 200 and len(release_ids) == 1 and 'Private draft uploaded' in response.text)
    release_id = release_ids[0]
    response = submit(owner, deliveries + '&release=' + release_id, 'delivery_publish',
                      {'owner_password': OWNER, 'confirm': '1', 'rights': '1'})
    ok('Original owner publishes website before upgrade', response.status_code == 200 and 'Website release published' in response.text)
    download = portal + '?download_release=' + release_id
    invoice_id = next(row['id'] for row in identity()['invoices'] if row['job_id'] == job_id)
    invoice = admin + '?view=invoice-payments&invoice_id=' + str(invoice_id)
    receipt_target, receipt_request = fields(owner.get(invoice, timeout=120), 'payments_ledger_receive')
    receipt_request.update({'amount': '5.00', 'method': 'bank', 'reference': 'Synthetic original release receipt',
                            'received_on': datetime.datetime.now(datetime.timezone.utc).date().isoformat(), 'confirm': '1'})
    response = owner.post(receipt_target, data=receipt_request, timeout=120)
    ok('Original invoice accepts one synthetic external receipt', 'External payment recorded' in response.text)
    support = admin + '?view=support'
    if 'support_migrate' in owner.get(support, timeout=120).text:
        submit(owner, support, 'support_migrate', {'owner_password': OWNER, 'confirm': '1'})
    submit(owner, support, 'support_policy', {'allowed': '1', 'owner_password': OWNER}, {'client_id': first['id']})
    response = submit(owner, support, 'support_start', selector={'client_id': first['id']})
    sid = parse_qs(urlparse(response.url).query).get('support', [''])[0]
    ok('Original support session opens exact client', len(sid) == 32 and 'Support session' in response.text)
    support_job = client_job + '&support=' + sid
    submit(owner, support_job, 'message', {'body': 'Original attributed support message'})
    end = submit(owner, support_job, 'support_end')
    ok('Original support session is ended before immutable baseline', end.status_code == 200 and owner.get(support_job, timeout=120).status_code == 403)
    for mode in ['drafts-seed', 'submissions-seed', 'mail-checks-seed', 'inbox-seed', 'messages-seed', 'notices-seed']:
        old_records(mode)
    settings = admin + '?view=provider-settings'
    response = submit(owner, settings, 'provider_settings_save',
                      {'profile_id': '', 'revision': '0', 'current_password': OWNER,
                       'label': 'Original release provider', 'general_key': 'SYNTHETIC_ORIGINAL_120_GENERAL_KEY', 'enabled': '1'})
    ok('Original provider settings save succeeds', '20i settings saved' in response.text)
    old_records('provider-seed')
    seed = command('release_126_provider_fixture.php', 'seed')
    ok('Original uncertain provider operation came from published APIs', seed == {'seeded': True, 'synthetic_posts': 1})
    from release_126_original_records import prepare as original_prepare
    original = original_prepare(repo, installed, base, php, owner, alice, bob, first['id'], job_id, OWNER, fields, submit, snapshot, ok)
    from release_126_catalog_records import prepare_original, activate_catalog
    catalog = prepare_original(base, owner, OWNER, fields, submit, snapshot, identity, ok)
    initial = snapshot()
    (qa / ('apache-release126-' + prefix + '-original-retention.json')).write_text(json.dumps({'baseline_source': BASELINE_SOURCE,
        'baseline_sha256': BASELINE_SHA, 'snapshot': initial}, indent=2) + '\n', encoding='utf-8')
    required = ['users', 'staff_profiles', 'staff_invitations', 'studio_clients', 'studio_jobs', 'studio_messages', 'studio_files', 'studio_invoices',
                'invoice_payments', 'website_releases', 'delivery_events', 'support_clients', 'support_sessions', 'support_events',
                'mail_accounts', 'mail_account_grants', 'mail_drafts', 'mail_draft_revisions',
                'mail_submissions', 'mail_submission_dispatches', 'mail_submission_results',
                'provider_profiles', 'provider_revisions', 'provider_refreshes', 'provider_refresh_results',
                'provider_package_links', 'provider_domain_links', 'provider_operations', 'provider_operation_grants',
                'service_plans', 'service_subscriptions', 'recurring_billing_schedules', 'recurring_billing_invoices',
                'website_licenses', 'license_domains', 'catalog_products', 'catalog_revisions', 'catalog_provider_imports', 'media']
    ok('Actual original database has every required populated family', all(initial['tables'][name]['count'] > 0 for name in required))
    ok('Actual original1.25 has populated business licensing imports and local media before update',
       all(initial['tables'][name]['count'] > 0 for name in ['service_plans', 'recurring_billing_invoices', 'website_licenses', 'catalog_provider_imports', 'media']))
    ok('Original1.25 has no future package type observation storage',
       all(name not in initial['tables'] for name in ['provider_package_type_intents', 'provider_observation_package_types']))
    config_bytes = (installed / 'config.php').read_bytes()
    guard_bytes = (installed / '.installed.php').read_bytes()
    unpack(updates[0], installed)
    updater = base + '/cwp-update-' + candidate_version + '/owner-update.php'

    def update(action):
        response = submit(owner, updater, action,
                          {'email': 'organized@example.invalid', 'owner_password': OWNER, 'backup_confirm': '1'})
        expected = '<h2>Complete</h2>' if action == 'apply' else '<h2>Rolled-back</h2>'
        ok('Owner updater ' + action + ' finishes', response.status_code == 200 and expected in response.text)
        ok('Updater preserves original config and installed guard',
           (installed / 'config.php').read_bytes() == config_bytes and (installed / '.installed.php').read_bytes() == guard_bytes)

    def retained_access(stage):
        nonlocal alice, bob
        active_owner = login('organized@example.invalid', OWNER)
        restricted = login('retained-staff@example.invalid', STAFF)
        ok(stage + ' original restricted administrator retains exact permission boundary', restricted.get(clients, timeout=120).status_code == 200
           and restricted.get(settings, timeout=120).status_code == 403)
        alice, bob = login(first['email'], CLIENT, True), login(second['email'], CLIENT, True)
        page = alice.get(client_job, timeout=120)
        ok(stage + ' client sees original project and attributed messages', page.status_code == 200 and all(text in page.text for text in
           ['Organized retained website', 'Original owner project message', 'Original direct-client project message', 'Original attributed support message']))
        ok(stage + ' private bytes remain exactly downloadable', alice.get(file_url, timeout=120).content == image)
        ok(stage + ' other client cannot read project or private file', bob.get(client_job, timeout=120).status_code == 404 and bob.get(file_url, timeout=120).status_code == 404)
        ok(stage + ' anonymous private file is refused', requests.get(file_url, timeout=120).status_code == 403)
        website = alice.get(download, timeout=120)
        ok(stage + ' original published ZIP remains exact and private', website.status_code == 200 and website.content == delivery_bytes
           and website.headers.get('ETag') == '"' + hashlib.sha256(delivery_bytes).hexdigest() + '"')
        ok(stage + ' other client and anonymous cannot download website ZIP', bob.get(download, timeout=120).status_code == 404
           and requests.get(download, timeout=120).status_code == 404)
        statement = portal + '?invoice=' + str(invoice_id)
        response = alice.get(statement, timeout=120)
        ok(stage + ' own original invoice retains partial receipt balance', response.status_code == 200 and '$5.00' in response.text and '$250.00' in response.text)
        ok(stage + ' other client cannot read invoice', bob.get(statement, timeout=120).status_code == 404)
        ok(stage + ' ended original support URL never revives', owner.get(support_job, timeout=120).status_code == 403)
        response = active_owner.get(admin + '?view=mail-submissions', timeout=120)
        ok(stage + ' original nonempty mail outcome history remains visible', response.status_code == 200 and all(text in response.text for text in
           ['Server accepted', 'Confirmation missing', 'Submission unresolved', 'Preparation incomplete']))
        replay = command('release_126_provider_fixture.php', 'replay')
        ok(stage + ' original consumed provider approval cannot resolve or send again', replay == {'network_calls': 0, 'status': 'failed'})

    failed = subprocess.run([php, str(repo / 'tests/structure_worker.php'), str(installed), 'apply', '2'], capture_output=True, text=True, timeout=120)
    ok('Actual published file update interrupts after durable journal', failed.returncode != 0 and 'Synthetic interruption' in failed.stderr)
    ok('Interrupted update denies application under actual Apache', owner.get(admin, timeout=120).status_code == 403)
    update('apply')
    ok('First update retains every original table row column and private file', retained(initial, snapshot()))
    # The released1.25 baseline carries studio_schema_version 2; the owner-run
    # workspace migration to 4 must precede owner-gated hosting views.
    response = submit(owner, admin + '?view=studio-upgrade', 'studio_migrate', {'confirm': '1'})
    ok('Workspace schema update precedes upgraded hosting checks', response.status_code == 200 and 'Database update completed' in response.text)
    # Published 1.25 carries payments_schema_version 1; the explicit owner-run
    # payment preparation to schema 2 must precede retained recurring reads.
    response = submit(owner, admin + '?view=payments', 'payments_migrate', {'owner_password': OWNER, 'confirm': '1'})
    ok('Payment schema update precedes upgraded retained checks', response.status_code == 200 and 'Payment preparation enabled' in response.text)
    from release_126_hosting_records import before_setup, continue_after_retention
    before_setup(repo, installed, base, prefix, php, candidate_version, owner, snapshot, hosting_evidence, qa)
    before_get = snapshot(); response = owner.get(settings, timeout=120)
    ok('Upgraded provider GET does not migrate or replace original history', response.status_code == 200 and exact_business(before_get, snapshot()))
    ok('Updated1.26 keeps the original current inventory marker without implicit migration', identity()['inventory_version'] == '5')
    changed = []
    migrated = list(SCHEMA_MARKER_CELLS)
    original['verify'](); catalog['verify']('updated')
    retained_access('updated')
    stable = snapshot(); unpack(updates[0], installed); update('apply')
    ok('Completed repeated update adds no business table row or private file', exact_business(stable, snapshot()))
    update('recover'); retained_access('recovered')
    ok('Preactivation recovery serves actual original1.25 code', identity()['version'] == '1.25.0')
    original['verify'](); catalog['verify']('recovered before activation')
    ok('Recovered old code retains all original columns and unchanged inventory marker', retained(initial, snapshot(), changed_cells=changed))
    update('apply'); retained_access('reapplied')
    ok('Reapplication retains original encrypted files and business rows', retained(initial, snapshot(), changed_cells=migrated))
    receipt_before = snapshot()
    response = owner.post(receipt_target, data=receipt_request, timeout=120)
    ok('Original captured receipt cannot duplicate payment after reapplication', response.status_code == 200 and exact_business(receipt_before, snapshot()))
    # New catalog preparation is explicit and follows a proved old-file recovery/reapply cycle.
    activation = activate_catalog(base, owner, OWNER, fields, submit, snapshot, identity, command, ok, initial, catalog)
    activated = snapshot(); update('recover')
    ok('Recovery after catalog activation serves actual original1.25 code', identity()['version'] == '1.25.0')
    ok('Recovered1.25 files preserve every new catalog intent observation import and image', retained(activated, snapshot(), changed_cells=migrated))
    # Original files do not implement package.types; do not treat their UI as new-catalog readiness.
    original['verify'](); catalog['verify']('recovered after activation'); retained_access('recovered after activation')
    update('apply')
    ok('Reapplication preserves all original and newly activated data', retained(activated, snapshot()))
    activation['verify'](); original['verify'](); catalog['verify']('final'); retained_access('final')
    if browser is not None:
        context = browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='reduce')
        page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        page.goto(portal); page.locator('[name=email]').fill(first['email']); page.locator('[name=password]').fill(CLIENT)
        page.locator('form button').click(); page.wait_for_url('**/portal/index.php')
        for width, label in [(1440, 'Desktop'), (320, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': 1000 if width == 1440 else 844})
            page.goto(client_job, wait_until='domcontentloaded')
            ok('Actual upgraded client ' + label + ' has original messages', 'Original direct-client project message' in page.content())
            ok('Actual upgraded client ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
            page.screenshot(path=str(qa / ('apache-release126-' + prefix + '-Upgraded-Client-' + label + '.png')), full_page=True)
        ok('Upgraded client browser has no page errors', not errors); context.close()
    # Evidence stores only hashes/counts. No raw config, credential, contact, draft or license keys.
    (qa / ('apache-release126-' + prefix + '-upgrade-retention.json')).write_text(json.dumps({'baseline_source': BASELINE_SOURCE,
        'baseline_sha256': BASELINE_SHA, 'initial': initial, 'final': snapshot(), 'observations': checks}, indent=2) + '\n', encoding='utf-8')
    continue_after_retention(repo, installed, base, prefix, php, candidate_version, owner, alice, bob, first, second,
                             job_id, initial, catalog, private, OWNER, STAFF, fields, submit, snapshot, identity,
                             browser, hosting_evidence, qa, BASELINE_SOURCE, BASELINE_SHA)
    return checks
