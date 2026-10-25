"""Supplemental real published1.25 upgrade hosting cases, on the existing Apache sites.

All commercial changes use actual owner/client forms or the closed production-wrapper
fixture. This is not published1.26 upgrade coverage or a paid provisioning journey.
"""
from pathlib import Path
import hashlib
import json
import re
import subprocess
from urllib.parse import urlparse

import requests
from bs4 import BeautifulSoup
from release_126_retention import exact_business, SCHEMA_MARKER_CELLS
from release_126_catalog_records import count_transition, png

PREFIXES = ('r126_ron_', 'r126_son_', 'r126_roff_', 'r126_soff_')
TABLES = ('hosting_catalog_decisions', 'hosting_catalog_publications', 'hosting_catalog_requests', 'hosting_catalog_withdrawals')
REPORT = 'apache-release126-hosting-results.json'
TITLE = 'Upgraded catalog-only hosting'


def names(prefix):
    assert prefix in PREFIXES
    return {'retention': 'apache-release126-hosting-' + prefix + '-retention.json',
            'images': ['apache-release126-hosting-' + prefix + '-Upgraded-Hosting-Offers-' + size + '.png'
                       for size in ('Desktop', 'Mobile')]}


def new_evidence():
    return {'checks': [], 'scopes': [], 'stages': {}, 'evidence': {}}


def _check(evidence, qa, prefix, name, value):
    assert prefix in PREFIXES
    label = prefix + ' ' + name
    assert all(c['name'] != label for c in evidence['checks']), 'Duplicate hosting outcome'
    evidence['checks'].append({'name': label, 'passed': bool(value)})
    (qa / 'apache-release126-hosting-progress.txt').write_text(json.dumps(
        {'complete': False, 'checks': evidence['checks']}, indent=2) + '\n', encoding='utf-8')
    assert value, label


def _fixture(repo, installed, php, target, mode):
    assert mode in ('state', 'plan-original', 'plan-positive')
    result = subprocess.run([php, str(repo / 'tests/release_126_hosting_fixture.php'), str(installed), mode, target],
                            capture_output=True, text=True, timeout=120)
    if result.returncode != 0:
        # PHP emits a finite redacted error, never arbitrary stderr/configuration.
        try:
            error = json.loads(result.stderr)
            assert set(error) <= {'operation', 'class', 'message', 'file', 'line', 'sqlstate'}
            assert error['operation'] == mode and re.fullmatch(r'[A-Za-z_\\]{1,120}', error['class'])
            assert error['message'] == 'Isolated hosting upgrade fixture failed.'
            assert re.fullmatch(r'[A-Za-z0-9_.-]{1,120}', error['file']) and type(error['line']) is int
            detail = json.dumps(error, sort_keys=True)
        except (ValueError, KeyError, AssertionError, TypeError):
            detail = 'stderr_sha256=' + hashlib.sha256(result.stderr.encode()).hexdigest()
        raise AssertionError('Hosting fixture ' + mode + ' failed: ' + detail)
    assert result.stderr == '', 'Hosting fixture emitted an unexpected diagnostic'
    return json.loads(result.stdout)


def _tree(root):
    root = Path(root)
    assert root.is_dir() and not root.is_symlink()
    files = {}; directories = []
    for path in sorted(root.rglob('*')):
        assert not path.is_symlink(), 'Unexpected fixture link'
        relative = path.relative_to(root).as_posix()
        if path.is_dir():
            directories.append(relative)
        else:
            assert path.is_file(), 'Unexpected fixture file type'
            body = path.read_bytes()
            files[relative] = {'bytes': len(body), 'sha256': hashlib.sha256(body).hexdigest()}
    return {'files': files, 'directories': directories}


def _refusal_files(installed, private, target):
    # Includes installed .htaccess/config/staged UPDATE and the actual journal/backup payload.
    # Sessions and rate counters are tracked separately, never reset by this fixture.
    return {'installed': _tree(installed), 'recovery': _tree(Path(private) / ('structure-' + target))}


def before_setup(repo, installed, base, prefix, php, target, owner, snapshot, evidence, qa):
    assert prefix in PREFIXES and prefix not in evidence['stages']
    for name in [names(prefix)['retention'], *names(prefix)['images']]:
        assert not (qa / name).exists(), 'Never overwrite hosting evidence'
    check = lambda name, value: _check(evidence, qa, prefix, name, value)
    state = _fixture(repo, installed, php, target, 'state'); before = snapshot()
    check('First updated site has no hosting schema marker counters or audit',
          not state['ready'] and state['tables'] == [] and state['settings'] == [] and state['audit_count'] == 0)
    public = requests.get(base + '/index.php?page=hosting', timeout=120)
    after_get = snapshot()
    (qa / ('apache-release126-' + prefix.rstrip('_') + '-anonymous-get.json')).write_text(json.dumps(
        {'status': public.status_code, 'honest_message': 'Let’s find the right hosting for you.' in public.text,
         'fingerprint_leak': 'source_key_fingerprint' in public.text,
         'business_unchanged': exact_business(before, after_get), 'excerpt': public.text[:1200]}, indent=2), encoding='utf-8')
    check('No-schema anonymous Hosting GET is honest and does not prepare storage',
          public.status_code == 200 and 'Let’s find the right hosting for you.' in public.text
          and 'source_key_fingerprint' not in public.text and exact_business(before, after_get))
    admin = owner.get(base + '/admin/index.php?view=hosting-offers', timeout=120)
    after = _fixture(repo, installed, php, target, 'state')
    after_business = snapshot()
    drift = {key: {'state': repr(state.get(key))[:400], 'after': repr(after.get(key))[:400]}
             for key in set(state) | set(after) if state.get(key) != after.get(key)}
    owner_state = {'status': admin.status_code, 'setup_form': 'hosting_catalog_setup' in admin.text,
                   'state_unchanged': state == after, 'drift': drift, 'excerpt': admin.text[:1200]}
    business_ok = True
    try:
        exact_business(before, after_business)
    except AssertionError as error:
        business_ok = False
        owner_state['business_error'] = str(error)
    owner_state['business_unchanged'] = business_ok
    (qa / ('apache-release126-' + prefix.rstrip('_') + '-owner-get.json')).write_text(
        json.dumps(owner_state, indent=2), encoding='utf-8')
    check('No-schema owner Hosting GET leaves every original row and file unchanged',
          admin.status_code == 200 and 'hosting_catalog_setup' in admin.text and state == after
          and business_ok)
    evidence['stages'][prefix] = {'no_schema': before}


def continue_after_retention(repo, installed, base, prefix, php, target, owner, alice, bob, first, second,
                             job_id, original, catalog, private, password, staff_password, fields, submit,
                             snapshot, identity, browser, evidence, qa, baseline_source, baseline_sha):
    assert prefix in evidence['stages'] and browser is not None
    check = lambda name, value: _check(evidence, qa, prefix, name, value)
    fixture = lambda mode='state': _fixture(repo, installed, php, target, mode)
    admin = base + '/admin/index.php?view=hosting-offers'; portal = base + '/portal/index.php'
    updater = base + '/cwp-update-' + target + '/owner-update.php'
    catalog_url = base + '/admin/index.php?view=catalog'
    stages = evidence['stages'][prefix]; stages['historical_complete'] = snapshot()
    old_retention = qa / ('apache-release126-' + prefix + '-upgrade-retention.json')
    sealed_old = hashlib.sha256(old_retention.read_bytes()).hexdigest()
    old_image = base + '/media/index.php?id=' + catalog['original']['media_id']
    image_response = requests.get(old_image, timeout=120)
    assert image_response.status_code == 200
    old_image_sha = hashlib.sha256(image_response.content).hexdigest()
    original_plan = fixture('plan-original')
    check('Real owner wrappers add a matching original plan and saved mapping without provider requests',
          original_plan['product_id'] == catalog['product_id'] and original_plan['type_ref'] == '9'
          and original_plan['live_requests'] == 0 and count_transition(original, snapshot(), SCHEMA_MARKER_CELLS))
    before = snapshot(); target_url, setup = fields(owner.get(admin, timeout=120), 'hosting_catalog_setup')
    for label, changes in [('confirmation', {'confirm': ''}), ('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'})]:
        response = owner.post(target_url, data={**setup, 'current_password': password, 'confirm': '1', **changes}, timeout=120)
        check('Explicit hosting setup refuses invalid ' + label + ' without creating history',
              response.status_code == 200 and not fixture()['ready'] and exact_business(before, snapshot()))
    staff = requests.Session()
    response = submit(staff, base + '/admin/index.php?view=login', 'login',
                      {'email': 'retained-staff@example.invalid', 'password': staff_password})
    check('Original delegated administrator remains excluded from hosting setup',
          response.status_code == 200 and staff.get(admin, timeout=120).status_code == 403 and exact_business(before, snapshot()))
    check('Support selector cannot expose hosting owner setup',
          owner.get(admin + '&support=', timeout=120).status_code == 403 and exact_business(before, snapshot()))
    response = owner.post(target_url, data={**setup, 'current_password': password, 'confirm': '1'}, timeout=120)
    prepared = fixture(); stages['prepared_empty'] = snapshot()
    check('Owner explicitly creates exact empty hosting history and retains published rows',
          response.status_code == 200 and prepared['ready'] and prepared['tables'] == list(TABLES)
          and set(prepared['counts']) == set(TABLES) and set(prepared['counts'].values()) == {0}
          and dict((r['name'], r['value']) for r in prepared['settings']) == {
              **{t + '_count': '0' for t in TABLES}, 'hosting_catalog_version': '1'}
          and prepared['audit_count'] == 1 and count_transition(original, snapshot(), SCHEMA_MARKER_CELLS))
    # The captured exact setup retry is valid even though the successful page no longer offers setup.
    before = snapshot(); response = owner.post(target_url, data={**setup, 'current_password': password, 'confirm': '1'}, timeout=120)
    check('Repeated explicit setup is idempotent', response.status_code == 200 and exact_business(before, snapshot()))

    def update(action, label):
        response = submit(owner, updater, action, {'email': 'organized@example.invalid', 'owner_password': password, 'backup_confirm': '1'})
        check(label, response.status_code == 200 and ('<h2>Rolled-back</h2>' if action == 'recover' else '<h2>Complete</h2>') in response.text)
        return response

    update('recover', 'Prepared-empty hosting permits real original-file recovery')
    recovered = snapshot(); stages['prepared_empty_recovered'] = recovered
    check('Actual recovered1.25 preserves original and prepared-empty database and private bytes',
          identity()['version'] == '1.25.0' and exact_business(stages['prepared_empty'], recovered, changed_cells=SCHEMA_MARKER_CELLS)
          and alice.get(portal + '?job=' + str(job_id), timeout=120).status_code == 200)
    update('apply', 'Prepared-empty recovery reapplies the exact candidate UPDATE')
    check('Reapplied current hosting validates retained empty history', identity()['version'] == target
          and fixture()['ready'] and exact_business(stages['prepared_empty'], snapshot()))
    selected_old = admin + '&product_id=' + str(catalog['product_id'])
    before = snapshot()
    rejected = submit(owner, selected_old, 'hosting_catalog_preview', {'plan_id': original_plan['plan_id']})
    rejected_page = BeautifulSoup(rejected.text, 'html.parser')
    refusal = 'Choose a priced monthly or annual hosting draft without a separate setup fee.'
    check('Original setup-priced product is explicitly refused without changing retained terms or image',
          rejected.status_code == 200 and any(refusal in alert.get_text() for alert in rejected_page.select('[role="alert"]'))
          and not rejected_page.select('form [name="action"][value="hosting_catalog_publish"]:not([disabled])')
          and not fixture()['publications'] and exact_business(before, snapshot()))

    imported = [r for r in identity()['catalog_imports'] if r['type_ref'] == '901']
    assert len(imported) == 1
    product = int(imported[0]['product_id']); edit = catalog_url + '&id=' + str(product)
    response = submit(owner, edit, 'catalog_save', {'title': TITLE, 'amount': '120.00', 'setup': '0.00',
        'summary': 'A reviewed yearly hosting offer after the published upgrade.', 'features': 'PRIVATE_UPGRADE_FEATURES',
        'terms': 'PRIVATE_UPGRADE_TERMS', 'provider_ref': 'PRIVATE_UPGRADE_REFERENCE'}, {'id': product})
    check('Owner prices only the new catalog-only draft through the real form', response.status_code == 200
          and TITLE in response.text and count_transition(original, snapshot(), SCHEMA_MARKER_CELLS))
    response = submit(owner, edit, 'catalog_import_image', {'owner_password': password}, {'id': product},
                      files={'product_image': ('hosting-upgrade.png', png(), 'image/png')})
    image = next(r for r in identity()['catalog_imports'] if int(r['product_id']) == product)
    check('Owner adds a real local multipart image only to the new draft', response.status_code == 200
          and isinstance(image['media_id'], str) and re.fullmatch('[a-f0-9]{32}', image['media_id']) is not None
          and image['media_id'] != catalog['original']['media_id'] and count_transition(original, snapshot(), SCHEMA_MARKER_CELLS))
    positive_plan = fixture('plan-positive'); stages['reviewed_local_terms'] = snapshot()
    selected = admin + '&product_id=' + str(product)
    response = submit(owner, selected, 'hosting_catalog_preview', {'plan_id': positive_plan['plan_id']})
    publish_url, publication = fields(response, 'hosting_catalog_publish')
    response = owner.post(publish_url, data={**publication, 'confirm': '1', 'current_password': password}, timeout=120)
    published = fixture(); pub = next(p for p in published['publications'] if p['id'] == publication['publication_id'])
    check('Actual owner publication binds exact imported draft plan mapping observation and image',
          response.status_code == 200 and pub['product_id'] == product and pub['media_id'] == image['media_id']
          and pub['plan_sha256'] == positive_plan['plan_sha256'] and pub['mapping_id'] == positive_plan['mapping_id']
          and pub['import_type'] == pub['observation_type'] == '901' and positive_plan['live_requests'] == 0)
    stages['published'] = snapshot()

    context = browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='reduce')
    page = context.new_page(); errors = []; external = []
    page.on('pageerror', lambda error: errors.append(type(error).__name__))
    def route(route):
        url = route.request.url
        if urlparse(url).netloc == urlparse(base).netloc or url.startswith(('data:', 'about:')): route.continue_()
        else: external.append('external resource refused'); route.abort()
    context.route('**/*', route)
    try:
        for size, width, height in [('Desktop', 1440, 1000), ('Mobile', 320, 844)]:
            page.set_viewport_size({'width': width, 'height': height})
            response = page.goto(base + '/index.php?page=hosting', wait_until='load', timeout=30000)
            plan = page.locator('.hosting-comparison-plan').filter(has=page.get_by_role('heading', name=TITLE, exact=True))
            if plan.count() != 1:
                (qa / ('apache-release126-' + prefix + '-hosting-page-' + size.lower() + '.html')).write_text(page.content(), encoding='utf-8')
                (qa / ('apache-release126-' + prefix + '-hosting-count-' + size.lower() + '.json')).write_text(json.dumps(
                    {'status': response.status if response else None, 'plans': plan.count(),
                     'all_titles': page.locator('.hosting-comparison-plan h2,.hosting-comparison-plan h3,.hosting-comparison-plan h4').all_inner_texts(),
                     'notices': page.locator('.notice,.hosting-comparison-empty').all_inner_texts(),
                     'page_errors': errors}, indent=2) + '\n', encoding='utf-8')
            assert plan.count() == 1
            column = plan.evaluate("el => Array.prototype.indexOf.call(el.closest('th').parentElement.children, el.closest('th')) - 1")
            price_cell = page.locator('table.hosting-comparison-table tbody tr', has_text='Price & billing period').locator('td').nth(column)
            plan.scroll_into_view_if_needed(timeout=30000)
            page.wait_for_timeout(350)
            page.wait_for_function("() => {const e=document.querySelector('.hosting-catalog-image');return e&&e.complete&&e.naturalWidth>0;}", timeout=30000)
            check('Actual upgraded hosting ' + size + ' shows safe public terms and decoded local image',
                  response.status == 200 and TITLE in plan.inner_text() and '$120.00' in price_cell.inner_text()
                  and 'per year' in price_cell.inner_text() and plan.locator('img').get_attribute('src').startswith(base + '/media/index.php?id=')
                  and all(s not in page.content() for s in ['PRIVATE_UPGRADE_', 'source_key_fingerprint', 'credential_sha256', password]))
            check('Actual upgraded hosting ' + size + ' fits the viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
            name = names(prefix)['images'][0 if size == 'Desktop' else 1]
            page.screenshot(path=str(qa / name), full_page=True)
        check('Upgraded hosting browser has no page errors or external requests', not errors and not external)
    finally:
        context.close()

    request_url, request = fields(alice.get(portal + '?hosting=offers', timeout=120), 'hosting_catalog_request', {'publication_id': pub['id']})
    before = snapshot(); response = alice.post(request_url, data={**request, 'confirm': '1'}, timeout=120)
    requested = fixture(); req = next(r for r in requested['requests'] if r['id'] == request['request_id'])
    check('Original client requests exactly its published terms', response.status_code == 200 and int(req['client_id']) == first['id']
          and req['publication_id'] == pub['id'] and request['publication_sha256'] == pub['sha256']
          and len(requested['agreements']) == 0)
    stable = snapshot(); replay = alice.post(request_url, data={**request, 'confirm': '1'}, timeout=120)
    check('Captured hosting request retry is idempotent', replay.status_code == 200 and exact_business(stable, snapshot()))
    _, other = fields(bob.get(portal + '?hosting=offers', timeout=120), 'hosting_catalog_request', {'publication_id': pub['id']})
    bob.post(request_url, data={**request, 'csrf': other['csrf'], 'confirm': '1'}, timeout=120)
    foreign = bob.get(portal + '?hosting=requests', timeout=120)
    check('Other original client cannot replay or read the private request', foreign.status_code == 200
          and req['id'] not in foreign.text and req['document_sha256'] not in foreign.text and exact_business(stable, snapshot()))
    review_url = admin + '&tab=requests&request_id=' + req['id']
    conversion_url, conversion = fields(owner.get(review_url, timeout=120), 'hosting_catalog_convert')
    conversion_values = {**conversion, 'job_id': str(job_id), 'confirm': '1', 'current_password': password}
    response = owner.post(conversion_url, data=conversion_values, timeout=120)
    converted = fixture(); decision = next(d for d in converted['decisions'] if d['request_id'] == req['id'])
    agreement_id = decision['agreement_id']; agreement = converted['agreements'][agreement_id]
    check('Owner conversion links exact original client project and accepted plan', response.status_code == 200
          and decision['kind'] == 'offered' and int(decision['job_id']) == job_id and agreement['client_id'] == first['id']
          and agreement['plan_sha256'] == pub['plan_sha256'] and agreement['revision'] == 0 and not agreement['consented']
          and agreement['state'] == 'pending-consent')
    stable = snapshot(); replay = owner.post(conversion_url, data=conversion_values, timeout=120)
    check('Captured conversion retry does not duplicate an agreement', replay.status_code == 200 and exact_business(stable, snapshot()))
    agreement_url = portal + '?agreement=' + agreement_id
    foreign = bob.get(agreement_url, timeout=120)
    check('Other original client cannot access the new agreement', foreign.status_code == 200
          and 'Subscription unavailable.' in foreign.text and 'service_agreements_consent' not in foreign.text)
    response = submit(alice, agreement_url, 'service_agreements_consent', {'confirm': '1'})
    consented = fixture(); agreement = consented['agreements'][agreement_id]
    check('Real client consent preserves pending activation and exact published terms', response.status_code == 200
          and agreement['consented'] and agreement['revision'] == 1 and agreement['state'] == 'pending-consent'
          and agreement['plan_sha256'] == pub['plan_sha256'])
    stages['consented'] = snapshot()

    def refuse_recovery(stage):
        before = snapshot(); files = _refusal_files(installed, private, target)
        response = submit(owner, updater, 'recover', {'email': 'organized@example.invalid', 'owner_password': password, 'backup_confirm': '1'})
        after = snapshot(); after_files = _refusal_files(installed, private, target)
        check(stage + ' hosting history blocks actual incompatible owner recovery before mutation',
              response.status_code == 200 and 'Automatic file recovery is blocked.' in response.text
              and before == after and files == after_files and identity()['version'] == target)
        stages[stage + '_refusal'] = {'before': before, 'after': after, 'files_before': files, 'files_after': after_files}

    refuse_recovery('published')
    response = submit(owner, selected, 'hosting_catalog_withdraw', {'reason': 'Close the supplemental upgrade offer.',
                      'confirm': '1', 'current_password': password})
    closed = fixture()
    check('Withdrawal preserves original client consent and immutable hosting terms', response.status_code == 200
          and closed['agreements'] == consented['agreements'] and closed['publications'] == consented['publications']
          and closed['counts']['hosting_catalog_withdrawals'] == 1)
    refuse_recovery('withdrawn')
    final = snapshot(); stages['final'] = final
    public = requests.get(base + '/index.php?page=hosting', timeout=120)
    history = alice.get(portal + '?hosting=requests', timeout=120)
    check('Withdrawn offer is unavailable publicly while client history retains its agreement', public.status_code == 200
          and TITLE not in public.text and history.status_code == 200 and TITLE in history.text and agreement_id in history.text)
    preserved = ['studio_invoices', 'invoice_payments', 'payment_requests', 'provider_operations', 'provider_operation_grants']
    check('Hosting supplement adds no invoice payment provider dispatch or automatic activation',
          all(final['tables'].get(t) == stages['historical_complete']['tables'].get(t) for t in preserved)
          and fixture()['agreements'][agreement_id]['state'] == 'pending-consent')
    original_image = requests.get(old_image, timeout=120)
    current_original = [r for r in identity()['catalog_imports'] if int(r['product_id']) == catalog['product_id']]
    check('Every original business row key provenance and image survives the complete extension',
          count_transition(original, final, SCHEMA_MARKER_CELLS) and current_original == [catalog['original']]
          and original_image.status_code == 200 and hashlib.sha256(original_image.content).hexdigest() == old_image_sha)
    check('Original completed upgrade snapshot remains byte exact', hashlib.sha256(old_retention.read_bytes()).hexdigest() == sealed_old)
    record = {'baseline_source': baseline_source, 'baseline_sha256': baseline_sha, 'candidate_version': target,
              'historical_snapshot_sha256': sealed_old, 'stages': stages,
              'limits': ['Rate-limit rows, sessions and owner last_login are normal auth bookkeeping and excluded by the existing retention snapshot.',
                         'Real published1.25 supplement; no published1.26 baseline, payment, provider operation or hosting activation.']}
    path = qa / names(prefix)['retention']; path.write_text(json.dumps(record, indent=2) + '\n', encoding='utf-8')
    for name in [names(prefix)['retention'], *names(prefix)['images']]:
        body = (qa / name).read_bytes()
        evidence['evidence'][name] = {'bytes': len(body), 'sha256': hashlib.sha256(body).hexdigest()}
    evidence['scopes'].append({'prefix': prefix, 'passed': True})


def finish(evidence, qa, failure, candidate, selected, apache_http, baseline_source, baseline_sha):
    expected = {n for p in PREFIXES for n in [names(p)['retention'], *names(p)['images']]}
    files_valid = set(evidence['evidence']) == expected
    if files_valid:
        for name, descriptor in evidence['evidence'].items():
            path = qa / name
            if not path.is_file() or path.is_symlink():
                files_valid = False; break
            body = path.read_bytes()
            if descriptor != {'bytes': len(body), 'sha256': hashlib.sha256(body).hexdigest()} or (name.endswith('.png') and not body.startswith(b'\x89PNG\r\n\x1a\n')):
                files_valid = False; break
    passed = (failure is None and [s['prefix'] for s in evidence['scopes']] == list(PREFIXES)
              and all(s['passed'] is True for s in evidence['scopes']) and files_valid
              and bool(evidence['checks']) and len({c['name'] for c in evidence['checks']}) == len(evidence['checks'])
              and all(isinstance(c['name'], str) and c['passed'] is True for c in evidence['checks']))
    report = {'passed': passed, 'baseline_source': baseline_source, 'baseline_sha256': baseline_sha,
              'candidate_version': candidate, 'selected': selected, 'apache_http': apache_http,
              'scope': 'Actual published1.25 supplemental hosting upgrade; four original Apache contexts; no live provider/payment or published1.26 claim',
              'cases': evidence['scopes'], 'checks': evidence['checks'], 'evidence': evidence['evidence'], 'failure': failure,
              'full_acceptance': False, 'visual_approval': False}
    (qa / REPORT).write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    return passed
