"""Real packaged owner controls and fixed TLS loopback GET/package; no external provider."""
import json
import html
import re
from bs4 import BeautifulSoup
import subprocess
import time
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER, STAFF


def schedule_projection_privacy(markup, approval):
    """Return finite checks only; never include retained secrets or response markup."""
    document = BeautifulSoup(markup, 'html.parser')
    marked = document.select('[data-provider-schedule]')
    identified = document.select('#scheduled-refresh')
    exact_section = (len(marked) == 1 and len(identified) == 1
                     and marked[0] is identified[0] and marked[0].name == 'section')
    section = str(marked[0]).lower() if exact_section else ''
    # Inspect raw/HTML-decoded attributes as well as visible text. The response
    # parser in env expects response.text, so synthetic markup is parsed here.
    whole = html.unescape(markup) + '\n' + document.get_text('', strip=False)
    data = approval.get('data') if isinstance(approval, dict) else None
    hashes = [data.get(key) for key in ('owner_auth_sha256', 'credential_sha256')] if isinstance(data, dict) else []
    retained_hashes_valid = (len(hashes) == 2 and all(isinstance(value, str)
                             and re.fullmatch(r'[0-9a-f]{64}', value) for value in hashes))
    return {
        'one_real_schedule_section': exact_section,
        'global_sensitive_keys_absent': all(key not in whole.lower() for key in ('owner_auth_sha256', 'credential_sha256')),
        'retained_hashes_valid': retained_hashes_valid,
        'global_retained_hashes_absent': bool(retained_hashes_valid and all(value not in whole.lower() for value in hashes)),
        'schedule_document_root_absent': bool(exact_section and 'document_root' not in section),
        # Singular field names remain forbidden globally. A separate feature's
        # legitimate provider_inventory_document_roots_* action is not that field.
        'global_document_root_field_absent': re.search(r'(?<![a-z0-9])document_root(?![a-z0-9_])', whole.lower()) is None,
    }


def schedule_projection_privacy_regressions():
    approved = {'data': {'owner_auth_sha256': 'a' * 64, 'credential_sha256': 'b' * 64}}
    section = '<section id="scheduled-refresh" data-provider-schedule><p>Approved read</p></section>'
    unrelated = '<section id="document-roots"><form><input type="hidden" name="action" value="provider_inventory_document_roots_share"></form></section>'
    cases = [
        ('accepts exact schedule section', section, approved, True),
        ('allows unrelated website-folder action', unrelated + section, approved, True),
        ('rejects absent schedule section', unrelated, approved, False),
        ('rejects duplicate schedule section', section + section, approved, False),
        ('rejects duplicate schedule identifier', section + '<div id="scheduled-refresh"></div>', approved, False),
        ('rejects wrong section element', section.replace('section', 'div'), approved, False),
        ('rejects mismatched section identifier', section.replace('scheduled-refresh', 'other'), approved, False),
        ('rejects scheduler document-root text', section.replace('Approved read', 'document_root'), approved, False),
        ('rejects scheduler document-root attribute', section.replace('<p>', '<p data-document_root="private">'), approved, False),
        ('rejects misplaced website-folder action in scheduler', section.replace('Approved read', unrelated), approved, False),
        ('rejects outside singular document-root field', section + '<input name="document_root">', approved, False),
        ('rejects outside auth key text', section + '<p>owner_auth_sha256</p>', approved, False),
        ('rejects outside credential key attribute', section + '<input name="credential_sha256">', approved, False),
        ('rejects scheduler auth key attribute', section.replace('<p>', '<p data-owner_auth_sha256="private">'), approved, False),
        ('rejects scheduler credential key text', section.replace('Approved read', 'credential_sha256'), approved, False),
        ('rejects outside exact auth hash text', section + '<p>' + 'a' * 64 + '</p>', approved, False),
        ('rejects outside exact credential hash attribute', section + '<input value="' + 'b' * 64 + '">', approved, False),
        ('rejects HTML-encoded sensitive hash', section + '<p>' + '&#97;' * 64 + '</p>', approved, False),
        ('rejects split visible sensitive hash', section + '<p>' + 'a' * 32 + '<span>' + 'a' * 32 + '</span></p>', approved, False),
        ('rejects missing retained hashes', section, {'data': {}}, False),
        ('rejects malformed retained hashes', section, {'data': {'owner_auth_sha256': [], 'credential_sha256': 'b' * 64}}, False),
    ]
    return [('Schedule privacy regression ' + name, all(schedule_projection_privacy(markup, approval).values()) == expected)
            for name, markup, approval, expected in cases]


def run_browser(env, browser):
    for name, passed in schedule_projection_privacy_regressions():
        env['check'](name, passed)
    check, soup, form_data, form_for, submit = (env[k] for k in ['check', 'soup', 'form_data', 'form_for', 'submit'])
    base, qa, provider = env['BASE'], env['QA'], env['provider_server']
    admin = base+'/existing/admin/index.php'
    url = admin+'?view=provider-settings'
    owner = requests.Session()
    submit(owner, admin+'?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    db('DELETE FROM ci_rate_limits')
    calls, posts = provider.calls, list(provider.posts)
    prefix = 'provider_inventory_schedule_'
    response = owner.get(url)
    check('Schedule HTTP GET does not create storage or call provider', soup(response).select_one('[data-schedule-prepare]') is not None and not db('SHOW TABLES LIKE "ci_provider_schedule_events"') and provider.calls == calls)
    for changes, label in [({'csrf': 'bad', 'current_password': OWNER}, 'CSRF'), ({'current_password': 'wrong'}, 'owner password')]:
        rejected = submit(owner, url, prefix+'prepare', changes)
        check('Schedule preparation rejects '+label, not db('SHOW TABLES LIKE "ci_provider_schedule_events"') and provider.calls == calls and OWNER not in rejected.text)
    submit(owner, url, prefix+'prepare', {'current_password': OWNER})
    check('Schedule preparation remains disabled without network', len(db('SELECT * FROM ci_provider_schedule_events')) == 1 and 'disabled' in soup(owner.get(url)).select_one('[data-schedule-status]').text and provider.calls == calls)
    fields = form_data(form_for(owner.get(url), prefix+'enable'))
    valid = {'current_password': OWNER, 'schedule_interval': '900', 'schedule_reviewed': '1'}
    for delta, label in [({'current_password': 'wrong'}, 'password'), ({'csrf': 'bad'}, 'CSRF'), ({'schedule_reviewed': '0'}, 'explicit approval'), ({'revision': '0'}, 'profile version'), ({'schedule_interval': '30'}, 'unsafe frequency'), ({'schedule_interval': [], 'schedule_interval[]': '900'}, 'malformed fields')]:
        rejected = owner.post(url, data={**fields, **valid, **delta})
        check('Schedule approval refuses '+label, rejected.status_code == 200 and len(db('SELECT * FROM ci_provider_schedule_events')) == 1 and provider.calls == calls and OWNER not in rejected.text)
    member = requests.Session()
    submit(member, admin+'?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    check('Administrator has no schedule authority', member.get(url).status_code == 403)
    foreign_fields = {'csrf': env['token'](member.get(admin+'?view=security')), **fields, **valid}
    foreign_fields['csrf'] = env['token'](member.get(admin+'?view=security'))
    member.post(admin+'?view=security', data=foreign_fields)
    check('Forged administrator schedule creates no approval', len(db('SELECT * FROM ci_provider_schedule_events')) == 1 and provider.calls == calls)
    check('Owner support context cannot see scheduling', owner.get(url+'&support=fixture').status_code == 403)
    response = owner.post(url, data={**fields, **valid})
    check('Actual owner approval saves no password or raw provider key', len(db('SELECT * FROM ci_provider_schedule_events')) == 2 and provider.calls == calls and 'SYNTHETIC_RAW_GENERAL_KEY' not in json.dumps(db('SELECT * FROM ci_provider_schedule_events')) and OWNER not in response.text)
    retained_approval = json.loads(db('SELECT document_json FROM ci_provider_schedule_events WHERE kind="approval" ORDER BY sequence DESC LIMIT 1')[0]['document_json'])
    privacy = schedule_projection_privacy(response.text, retained_approval)
    (qa/'provider-schedule-privacy-diagnostics.json').write_text(json.dumps(privacy, sort_keys=True))
    check('Owner schedule projection omits auth and credential hashes', all(privacy.values()))
    owner.post(url, data={**fields, **valid})
    check('Stale approval submission cannot silently replace newer review', len(db('SELECT * FROM ci_provider_schedule_events')) == 2)

    # Respect the real production cooldown rather than rewriting retained immutable intent times.
    def cooldown():
        latest = db('SELECT MAX(started_epoch) AS latest FROM ci_provider_refreshes')[0]['latest'] or 0
        wait = max(0, int(latest)+31-int(time.time()))
        if wait:
            time.sleep(min(32, wait))

    cooldown()
    context = browser.new_context(viewport={'width': 1440, 'height': 1050})
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.goto(admin+'?view=login')
    page.locator('[name=email]').fill('owner@example.invalid')
    page.locator('[name=password]').fill(OWNER)
    page.locator('form button').first.click()
    page.wait_for_url(admin+'?view=dashboard')
    page.goto(url+'#scheduled-refresh')
    provider.mode = 'success'
    if not provider.packages:
        provider.packages.append({'id': 910, 'typeRef': 1, 'name': 'scheduled.example', 'names': ['scheduled.example'], 'enabled': True, 'created': '2024-01-02T03:04:05Z'})
    links = {name: db('SELECT * FROM ci_'+name+' ORDER BY id') for name in ['provider_package_links', 'provider_domain_links', 'provider_cloud_links']}
    form = page.locator('[data-schedule-run]')
    form.locator('[name=current_password]').fill(OWNER)
    form.get_by_role('button', name='Run if due', exact=True).click()
    expect(page.locator('[data-provider-schedule]')).to_contain_text('Refresh complete')
    check('Chromium scheduled run uses exactly one fixed authenticated GET', provider.calls == calls+1 and provider.get_paths[-1] == '/package' and provider.posts == posts)
    check('Scheduled observation preserves all explicit client assignments', all(db('SELECT * FROM ci_'+name+' ORDER BY id') == rows for name, rows in links.items()))
    submit(owner, url, prefix+'run', {'current_password': OWNER})
    check('Actual duplicate run before due time makes no request', provider.calls == calls+1 and len(db('SELECT * FROM ci_provider_schedule_events WHERE kind="start"')) == 1)
    page.locator('[data-provider-schedule]').screenshot(path=str(qa/'Provider-Schedule-Owner-Desktop.png'))
    page.set_viewport_size({'width': 320, 'height': 740})
    page.goto(url+'#scheduled-refresh')
    expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
    page.locator('[data-provider-schedule]').screenshot(path=str(qa/'Provider-Schedule-Owner-Mobile.png'))
    check('Owner scheduled refresh controls fit mobile width', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))

    submit(owner, url, prefix+'enable', valid)
    cooldown()
    provider.mode = 'authentication'
    failed = submit(owner, url, prefix+'run', {'current_password': OWNER})
    last = db('SELECT document_json FROM ci_provider_schedule_events ORDER BY sequence DESC LIMIT 1')[0]
    document = json.loads(last['document_json'])
    check('Actual failed scheduled read persists finite outcome and backoff', provider.calls == calls+2 and document['kind'] == 'finish' and document['data']['outcome'] == 'unavailable' and document['state']['failures'] == 1 and document['state']['next'] >= int(time.time())+1790 and 'private synthetic response' not in failed.text)
    # The requests session changed the server state while Chromium already has this exact anchored URL open.
    # Navigating to the same fragment can reuse the old document, so fetch the real current page explicitly.
    page.reload()
    expect(page.locator('[data-provider-schedule]')).to_contain_text('Refresh unavailable')
    page.locator('[data-provider-schedule]').screenshot(path=str(qa/'Provider-Schedule-Failure-Mobile.png'))
    provider.mode = 'success'

    submit(owner, url, prefix+'enable', valid)
    stale_run = form_data(form_for(owner.get(url), prefix+'run'))
    edit = form_data(form_for(owner.get(url), 'provider_settings_save'))
    owner.post(url, data={**edit, 'label': 'Schedule revoked connection revision', 'general_key': '', 'enabled': '1', 'current_password': OWNER})
    rejected = owner.post(url, data={**stale_run, 'current_password': OWNER})
    check('Saved connection revision invalidates scheduling before remote call', provider.calls == calls+2 and 'approve the schedule again' in rejected.text and soup(rejected).select_one('[data-schedule-run]') is None)
    page.reload()
    expect(page.locator('[data-schedule-status]')).to_contain_text('approval required')
    page.locator('[data-provider-schedule]').screenshot(path=str(qa/'Provider-Schedule-Revoked-Mobile.png'))
    response = submit(owner, url, prefix+'disable', {'current_password': OWNER})
    check('Owner can disable stale approval while retaining history', 'Scheduled refreshes are disabled' in response.text and len(db('SELECT * FROM ci_provider_schedule_events WHERE kind="finish"')) == 2 and provider.calls == calls+2)
    check('All scheduling password inputs are cleared', all(n.get('value', '') == '' for n in soup(response).select('[data-provider-schedule] input[type=password]')))
    # Invoke the actual installed CLI entry. The test-only connector also refuses any unexpected CLI DNS.
    command = [env['php'], '-d', 'auto_prepend_file='+str(env['ROOT']/'tests/mail_inbox_http_connector.php'), str(env['WEB']/'existing/app/jobs/run.php')]
    cli = subprocess.run(command, capture_output=True, text=True, timeout=35)
    check('Installed shared-host CLI loads configuration without an interactive session', cli.returncode == 0 and cli.stdout.strip() == 'Hosting inventory schedule: disabled' and not cli.stderr and provider.calls == calls+2)
    bad_cli = subprocess.run(command+['unexpected'], capture_output=True, text=True, timeout=10)
    check('Installed scheduled entry refuses caller arguments before work', bad_cli.returncode == 2 and 'takes no arguments' in bad_cli.stderr and provider.calls == calls+2)
    for path in ['app/jobs/run.php', 'app/providers/schedule.php', 'app/providers/schedule_storage.php', 'app/providers/schedule_document.php', 'admin/controllers/provider_schedule.php', 'admin/views/provider_schedule.php', 'database/schema/provider_schedule_schema.php']:
        check('Scheduled task internal HTTP denial '+path, requests.get(base+'/existing/'+path).status_code == 404)
    check('Scheduled read browser flow has no script or provider fixture errors', not errors and not provider.errors)
    context.close()
