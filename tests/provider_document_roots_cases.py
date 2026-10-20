"""Exact packaged document-roots forms and saved client observation; loopback TLS only."""
import hashlib
import json
import time
import requests
from urllib.parse import parse_qs, urlparse
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    base, qa, check, provider = (env[k] for k in ['BASE', 'QA', 'check', 'provider_server'])
    soup, token, submit = (env[k] for k in ['soup', 'token', 'submit'])
    admin = base + '/existing/admin/index.php'; url = admin + '?view=provider-settings'
    portal = base + '/existing/portal/index.php'
    db('DELETE FROM ci_rate_limits')
    # Give this workflow its own real owner budget; retain all prior intent/audit
    # bytes and use the staff invite/activation flow instead of changing authority.
    inherited = db('SELECT * FROM ci_provider_refreshes ORDER BY seq')
    inherited_audits = db('SELECT * FROM ci_audit ORDER BY id')
    original_owner_before_login = db('SELECT * FROM ci_users WHERE email=?', ['owner@example.invalid'])[0]
    original_profile = db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner_before_login['id']])[0]
    setup_diagnostics = []
    def setup_evidence(step, response, **facts):
        route = urlparse(response.url)
        outcome = parse_qs(route.query).get('view', [''])[0]
        setup_diagnostics.append({'step': step, 'status': response.status_code,
            'redirect_statuses': [r.status_code for r in response.history],
            'dashboard': route.path == urlparse(admin).path and outcome == 'dashboard',
            'login': route.path == urlparse(admin).path and outcome == 'login', **facts})
        # Persist only finite outcomes and field names, never account values,
        # password hashes, invitation/CSRF tokens, or response HTML.
        (qa/'document-roots-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics, indent=2), encoding='utf-8')
    setup = requests.Session(); setup_response = submit(setup, admin + '?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    original_owner = db('SELECT * FROM ci_users WHERE id=?', [original_owner_before_login['id']])[0]
    changed_fields = sorted(key for key in set(original_owner_before_login) | set(original_owner)
                            if original_owner_before_login.get(key) != original_owner.get(key))
    setup_evidence('authorized_setup_login', setup_response, changed_user_fields=changed_fields,
        original_profile_unchanged=db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    check('Document roots setup login changes only permitted login bookkeeping',
          setup_response.status_code == 200 and setup_diagnostics[-1]['dashboard']
          and {k:v for k,v in original_owner.items() if k != 'last_login'} == {k:v for k,v in original_owner_before_login.items() if k != 'last_login'}
          and original_owner['last_login'] is not None and setup_diagnostics[-1]['original_profile_unchanged'])
    # All later assertions use the full post-login row: even last_login must
    # stay exact after this explicitly authorized setup authentication.
    staff_url = admin + '?view=staff'; roots_email = 'roots-observation-owner@example.invalid'
    check('Document roots fixture owner starts absent', not db('SELECT id FROM ci_users WHERE email=?', [roots_email]))
    submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'Document roots observation owner', 'email': roots_email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
    roots_owner = db('SELECT id,password_hash FROM ci_users WHERE email=?', [roots_email])[0]
    invite = chosen_form(setup, staff_url, 'staff_invite', 'id', roots_owner['id'], {'owner_password': OWNER})
    invitation = soup(invite).select_one('[data-staff-invite]')['href']; code = parse_qs(urlparse(invitation).fragment)['token'][0]
    activation = requests.Session(); form = activation.get(invitation)
    activation_response = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(form), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
    activation.close(); setup.close()
    roots_profile = db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [roots_owner['id']])
    roots_activated = db('SELECT password_hash FROM ci_users WHERE id=?', [roots_owner['id']])[0]['password_hash'] != ''
    setup_evidence('dedicated_owner_activation', activation_response,
        distinct_owner=roots_owner['id'] != original_owner['id'], owner_role=roots_profile == [{'role': 'owner', 'enabled': 1}], activated=roots_activated,
        original_user_unchanged=db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner],
        original_profile_unchanged=db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    check('Document roots fixture activates a distinct authorized owner without changing original authority',
          activation_response.status_code == 200 and setup_diagnostics[-1]['login']
          and roots_owner['id'] != original_owner['id'] and roots_profile == [{'role': 'owner', 'enabled': 1}]
          and roots_activated
          and db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner]
          and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    owner = requests.Session(); submit(owner, admin + '?view=login', 'login', {'email': roots_email, 'password': OWNER})
    check('Document roots dedicated owner has an unused read budget', not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?', [roots_owner['id']]))

    diagnostics = []
    def evidence(label, response):
        # Keep finite error text and outcome metadata, never complete HTML or tokens.
        notices = [n.get_text(' ', strip=True) for n in soup(response).select('.notice.error')]
        notices = [n.replace(OWNER, '[redacted]').replace('SYNTHETIC_RAW_GENERAL_KEY', '[redacted]') for n in notices]
        rows = db('SELECT actor_id,operation,started_epoch FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [roots_owner['id']])
        results = db('SELECT r.state,r.reason,r.package_count FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id WHERE f.actor_id=? ORDER BY f.seq', [roots_owner['id']])
        diagnostics.append({'step': label, 'status': response.status_code, 'redirect_statuses': [r.status_code for r in response.history],
                            'inline_errors': notices, 'actor_id': roots_owner['id'], 'actor_reads': rows, 'results': results, 'provider_calls': provider.calls})
        (qa / 'document-roots-response-diagnostics.json').write_text(json.dumps(diagnostics, indent=2))
    member = requests.Session(); submit(member, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    client = requests.Session(); submit(client, portal, 'login', {'email': 'alice@example.invalid', 'password': 'Synthetic client passphrase 2026'})
    profile = db('SELECT id,revision FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
    client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']
    prepare = 'provider_inventory_document_roots_prepare'; refresh = 'provider_inventory_document_roots_refresh'; share = 'provider_inventory_document_roots_share'
    def post(action, **fields):
        page = owner.get(url); forms = soup(page).select('form'); data = {'csrf': token(page), 'action': action, 'current_password': OWNER, 'profile_id': profile['id'], 'revision': str(profile['revision'])}
        for form in forms:
            if form.select_one('input[name=action][value="' + action + '"]'):
                link = form.select_one('input[name=link_id]')
                if not link or link.get('value') == fields.get('link_id'):
                    data.update({n['name']: n.get('value', '') for n in form.select('input[name]')}); break
        data.update({'current_password': OWNER, **fields})
        return owner.post(url, data=data)
    def cool():
        last = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes WHERE profile_id=?', [profile['id']])[0]['epoch']
        time.sleep(max(0, int(last or 0) + 31 - time.time()))
    calls, posts = provider.calls, list(provider.posts)
    old_roots, old_roots_mode = provider.document_roots, provider.document_roots_mode
    check('Document roots observation GET offers explicit preparation without DDL', prepare in owner.get(url).text and not db('SHOW TABLES LIKE "ci_provider_document_roots_reads"') and provider.calls == calls)
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'})]:
        post(prepare, **fields)
        check('Document roots observation preparation refuses invalid ' + label, not db('SHOW TABLES LIKE "ci_provider_document_roots_reads"') and provider.calls == calls)
    post(prepare)
    check('Document roots observation preparation is local and explicit', db('SHOW TABLES LIKE "ci_provider_document_roots_reads"') != [] and not db('SELECT * FROM ci_provider_document_roots_reads') and provider.calls == calls)
    check('Document roots observation administrator and support access are refused', member.get(url).status_code == 403 and owner.get(url + '&support=fixture').status_code == 403)
    provider.mode = 'success'
    provider.packages += [{'id': 1801, 'typeRef': 12, 'name': 'roots-observation.synthetic.invalid', 'names': ['roots-observation.synthetic.invalid'], 'enabled': True, 'created': '2000-01-01T00:00:00Z'}]
    cool(); response = post('provider_inventory_refresh'); evidence('Hosting observation before assignment', response)
    post('provider_inventory_link', package_id='1801', client_id=str(client_id))
    link = db('SELECT * FROM ci_provider_package_links WHERE profile_id=? AND package_id=1801', [profile['id']])[0]
    target = portal + '?document_roots=1&service_link=' + link['id']
    check('Document roots assignment alone does not share map', '../private/<folder> é' not in soup(client.get(target)).text and 'sharing is off' in client.get(target).text)
    cool(); response = post('provider_inventory_refresh'); evidence('Hosting observation after assignment', response)
    calls = provider.calls
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'}), ('binding', {'binding_sha256': 'a' * 64})]:
        post(refresh, link_id=link['id'], **fields)
        check('Document roots refresh refuses invalid ' + label + ' before request', provider.calls == calls and not db('SELECT * FROM ci_provider_document_roots_reads'))
    cool(); provider.document_roots = {'roots-observation.synthetic.invalid': '../private/<folder> é', 'empty.synthetic.invalid': ''}; response = post(refresh, link_id=link['id'])
    evidence('Complete Document roots observation', response)
    saved = db('SELECT * FROM ci_provider_document_roots_observations')[0]
    repeated = post(refresh, link_id=link['id']); evidence('Shared cooldown refusal', repeated)
    section = soup(response).select_one('#document-roots')
    check('Document roots owner form sends one exact assigned-package GET', provider.calls == calls + 1 and provider.get_paths[-1] == '/package/1801/web/documentRoots' and section is not None and '../private/<folder> é' in section.text and 'Latest saved website folders' in section.text)
    check('Document roots displays exact escaped text without filesystem links', section.select_one('pre').text == '../private/<folder> é' and '<folder>' not in response.text and '&lt;folder&gt;' in response.text and not any('../private' in n.get('href', '') for n in section.select('a[href]')) and 'Empty text value' in section.text)
    check('Document roots owner observation has date and separate sharing', 'UTC' in section.text and 'Share saved folders' in section.text and '../private/<folder> é' not in soup(client.get(target)).text)
    check('Document roots immediate repeat obeys common cooldown', provider.calls == calls + 1 and db('SELECT * FROM ci_provider_document_roots_observations') == [saved] and any('Wait 30 seconds' in n for n in diagnostics[-1]['inline_errors']))
    post(share, link_id=link['id'], sharing='enable')
    response = client.get(target)
    check('Document roots explicit owner sharing exposes only map and date', '../private/<folder> é' in soup(response).text and 'UTC' in response.text and 'Refresh website folders' not in response.text and 'no-store' in response.headers.get('Cache-Control', ''))
    check('Document roots foreign assignment identifier discloses no map', '../private/<folder> é' not in soup(client.get(portal + '?document_roots=1&service_link=' + 'f' * 32)).text)
    def capture(session, title, address, selector):
        context = browser.new_context(viewport={'width': 1440, 'height': 1050}); context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in session.cookies])
        page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.goto(address); page.evaluate('document.fonts.ready'); expect(page.locator(selector)).to_be_visible(); page.screenshot(path=str(qa / (title + '-' + label + '.png')), full_page=True)
            check(title + ' ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        check(title + ' has no script errors', not errors); context.close()
    capture(owner, 'Document-Roots-Owner', url, '#document-roots')
    capture(client, 'Document-Roots-Client', target, '[data-client-document-roots]')
    cool(); provider.document_roots_mode = 'error_array'; response = post(refresh, link_id=link['id'])
    evidence('Unavailable Document roots observation', response)
    check('Document roots first four reads consume only the dedicated owner budget', len(diagnostics[-1]['actor_reads']) == 4 and all(r['actor_id'] == roots_owner['id'] for r in diagnostics[-1]['actor_reads']) and [r['operation'] for r in diagnostics[-1]['actor_reads']] == ['packages.list', 'packages.list', 'package.document-roots', 'package.document-roots'] and [r['state'] for r in diagnostics[-1]['results']] == ['complete', 'complete', 'complete', 'unavailable'])
    response = client.get(target)
    check('Document roots later invalid response preserves prior value as historical', provider.calls == calls + 2 and '../private/<folder> é' in soup(response).text and 'Historical observation' in response.text and 'Latest refresh did not provide website folders' in response.text and db('SELECT * FROM ci_provider_document_roots_observations') == [saved])
    capture(client, 'Document-Roots-Historical', target, '[data-client-document-roots]')
    cool(); provider.document_roots_mode = 'empty_object'; response = post(refresh, link_id=link['id']); evidence('Explicit empty document-root map', response)
    check('Document roots complete empty map remains a saved observation', provider.calls == calls + 3 and len(db('SELECT * FROM ci_provider_document_roots_observations')) == 2 and 'The provider returned an empty map.' in soup(response).text)
    empty_response = client.get(target)
    check('Document roots shared empty map does not claim no hosting', 'The provider returned an empty map.' in empty_response.text and 'does not establish' in empty_response.text and '../private/<folder> é' not in soup(empty_response).text)
    capture(client, 'Document-Roots-Empty', target, '[data-client-document-roots]')
    check('Document roots exact five reads use only the dedicated owner', len(diagnostics[-1]['actor_reads']) == 5 and [r['operation'] for r in diagnostics[-1]['actor_reads']] == ['packages.list', 'packages.list', 'package.document-roots', 'package.document-roots', 'package.document-roots'] and [r['state'] for r in diagnostics[-1]['results']] == ['complete', 'complete', 'complete', 'unavailable', 'complete'])
    post(share, link_id=link['id'], sharing='disable')
    check('Document roots owner revocation immediately hides saved map', '../private/<folder> é' not in soup(client.get(target)).text and 'sharing is off' in client.get(target).text)
    counter = db('SELECT value FROM ci_settings WHERE name=?', ['provider_document_roots_started_count'])[0]['value']; db('DELETE FROM ci_settings WHERE name=?', ['provider_document_roots_started_count'])
    try:
        response = owner.get(url)
        check('Document roots missing history refuses forms without read-path repair', 'Document-root observation counts need recovery' in response.text and not soup(response).select_one('#document-roots [name=action]') and provider.calls == calls + 3)
    finally:
        db('INSERT INTO ci_settings (name,value) VALUES (?,?)', ['provider_document_roots_started_count', counter])
    check('Document roots exact history restoration preserves explicit empty map', 'The provider returned an empty map.' in soup(owner.get(url)).select_one('#document-roots').text)
    check('Document roots observation never posts a provider change or leaks raw body', provider.posts == posts and not provider.errors and 'private synthetic roots response' not in response.text)
    retained = db('SELECT * FROM ci_provider_refreshes WHERE seq<=? ORDER BY seq', [max((r['seq'] for r in inherited), default=0)])
    retained_audits = db('SELECT * FROM ci_audit WHERE id<=? ORDER BY id', [max((r['id'] for r in inherited_audits), default=0)])
    check('Document roots workflow preserves every inherited intent and original audit byte', retained == inherited and retained_audits == inherited_audits)
    check('Document roots workflow preserves the original owner account and role', db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    (qa / 'document-roots-history-preservation.json').write_text(json.dumps({'inherited_intents': len(inherited), 'inherited_audits': len(inherited_audits), 'intents_sha256': hashlib.sha256(json.dumps(inherited, sort_keys=True).encode()).hexdigest(), 'audits_sha256': hashlib.sha256(json.dumps(inherited_audits, sort_keys=True).encode()).hexdigest(), 'retained_intents_sha256': hashlib.sha256(json.dumps(retained, sort_keys=True).encode()).hexdigest(), 'retained_audits_sha256': hashlib.sha256(json.dumps(retained_audits, sort_keys=True).encode()).hexdigest(), 'dedicated_actor': roots_owner['id'], 'dedicated_reads': len(diagnostics[-1]['actor_reads'])}, indent=2))
    for path in ['app/providers/document_roots.php', 'app/providers/document_roots_storage.php', 'app/providers/document_roots_views.php', 'database/schema/provider_document_roots_schema.php', 'admin/controllers/provider_document_roots.php', 'admin/views/provider_document_roots.php', 'portal/controllers/document_roots.php', 'portal/views/document_roots.php', 'includes/foundation/twentyi-document-roots/DocumentRootsBinding.php', 'includes/foundation/twentyi-document-roots/DocumentRootsObservation.php', 'includes/foundation/twentyi-document-roots/DocumentRootsReader.php']:
        check('Private document roots route refuses GET and POST '+path, owner.get(base+'/existing/'+path).status_code==404 and owner.post(base+'/existing/'+path,data={}).status_code==404)
    provider.document_roots, provider.document_roots_mode = old_roots, old_roots_mode; owner.close(); member.close(); client.close()
