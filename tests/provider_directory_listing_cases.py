"""Exact packaged Directory listing forms and saved client observation; loopback TLS only."""
import hashlib
import json
import threading
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
    original_owner = db('SELECT * FROM ci_users WHERE email=?', ['owner@example.invalid'])[0]
    original_profile = db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']])[0]
    before_setup_owner = original_owner
    setup = requests.Session(); login = submit(setup, admin + '?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    # Check authority across the expected login timestamp update, then preserve
    # the complete post-login account throughout the dedicated-owner workflow.
    original_owner = db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']])[0]
    setup_diagnostics = {'login': {'status': login.status_code, 'redirect_statuses': [r.status_code for r in login.history],
        'dashboard': urlparse(login.url).path == urlparse(admin).path and urlparse(login.url).query == 'view=dashboard',
        'changed_fields': sorted(k for k in set(before_setup_owner) | set(original_owner) if before_setup_owner.get(k) != original_owner.get(k)),
        'profile_unchanged': db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile]}}
    (qa / 'directory-listing-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics, indent=2))
    check('Directory listing setup login preserves all account authority and updates only login metadata',
          login.status_code == 200 and setup_diagnostics['login']['dashboard']
          and {k: v for k, v in original_owner.items() if k != 'last_login'} == {k: v for k, v in before_setup_owner.items() if k != 'last_login'}
          and original_owner['last_login'] is not None
          and setup_diagnostics['login']['profile_unchanged'])
    staff_url = admin + '?view=staff'; directory_indexing_email = 'directory-listing-owner@example.invalid'
    check('Directory listing fixture owner starts absent', not db('SELECT id FROM ci_users WHERE email=?', [directory_indexing_email]))
    submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'Directory listing observation owner', 'email': directory_indexing_email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
    directory_indexing_owner = db('SELECT id,password_hash FROM ci_users WHERE email=?', [directory_indexing_email])[0]
    invite = chosen_form(setup, staff_url, 'staff_invite', 'id', directory_indexing_owner['id'], {'owner_password': OWNER})
    invitation = soup(invite).select_one('[data-staff-invite]')['href']; code = parse_qs(urlparse(invitation).fragment)['token'][0]
    activation = requests.Session(); form = activation.get(invitation)
    activated = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(form), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
    setup_diagnostics['activation'] = {'status': activated.status_code, 'redirect_statuses': [r.status_code for r in activated.history],
        'login_page': urlparse(activated.url).path == urlparse(admin).path and urlparse(activated.url).query == 'view=login', 'distinct_actor': directory_indexing_owner['id'] != original_owner['id'],
        'authorized_owner': db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [directory_indexing_owner['id']]) == [{'role': 'owner', 'enabled': 1}],
        'password_set': db('SELECT password_hash FROM ci_users WHERE id=?', [directory_indexing_owner['id']])[0]['password_hash'] != '',
        'original_account_unchanged': db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner],
        'original_profile_unchanged': db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile]}
    (qa / 'directory-listing-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics, indent=2))
    activation.close(); setup.close()
    check('Directory listing fixture activates a distinct authorized owner without changing original authority',
          activated.status_code == 200 and setup_diagnostics['activation']['login_page']
          and directory_indexing_owner['id'] != original_owner['id'] and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [directory_indexing_owner['id']]) == [{'role': 'owner', 'enabled': 1}]
          and db('SELECT password_hash FROM ci_users WHERE id=?', [directory_indexing_owner['id']])[0]['password_hash'] != ''
          and db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner]
          and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    owner = requests.Session(); submit(owner, admin + '?view=login', 'login', {'email': directory_indexing_email, 'password': OWNER})
    check('Directory listing dedicated owner has an unused read budget', not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?', [directory_indexing_owner['id']]))
    diagnostics = []
    def evidence(label, response):
        # Keep finite error text and outcome metadata, never complete HTML or tokens.
        notices = [n.get_text(' ', strip=True) for n in soup(response).select('.notice.error')]
        notices = [n.replace(OWNER, '[redacted]').replace('SYNTHETIC_RAW_GENERAL_KEY', '[redacted]') for n in notices]
        rows = db('SELECT actor_id,operation,started_epoch FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [directory_indexing_owner['id']])
        results = db('SELECT r.state,r.reason,r.package_count FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id WHERE f.actor_id=? ORDER BY f.seq', [directory_indexing_owner['id']])
        diagnostics.append({'step': label, 'status': response.status_code, 'redirect_statuses': [r.status_code for r in response.history],
                            'inline_errors': notices, 'actor_id': directory_indexing_owner['id'], 'actor_reads': rows, 'results': results, 'provider_calls': provider.calls})
        (qa / 'directory-listing-response-diagnostics.json').write_text(json.dumps(diagnostics, indent=2))
    member = requests.Session(); submit(member, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    client = requests.Session(); submit(client, portal, 'login', {'email': 'alice@example.invalid', 'password': 'Synthetic client passphrase 2026'})
    profile = db('SELECT id,revision FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
    client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']
    prepare = 'provider_inventory_directory_indexing_prepare'; refresh = 'provider_inventory_directory_indexing_refresh'; share = 'provider_inventory_directory_indexing_share'
    def build(session, action, **fields):
        page = session.get(url); forms = soup(page).select('form'); data = {'csrf': token(page), 'action': action, 'current_password': OWNER, 'profile_id': profile['id'], 'revision': str(profile['revision'])}
        for form in forms:
            if form.select_one('input[name=action][value="' + action + '"]'):
                link = form.select_one('input[name=link_id]')
                if not link or link.get('value') == fields.get('link_id'):
                    data.update({n['name']: n.get('value', '') for n in form.select('input[name]')}); break
        data.update({'current_password': OWNER, **fields})
        return data
    def post(action, **fields):
        post.last = build(owner, action, **fields)
        return owner.post(url, data=post.last)
    def cool():
        last = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes WHERE profile_id=?', [profile['id']])[0]['epoch']
        time.sleep(max(0, int(last or 0) + 31 - time.time()))
    calls, posts = provider.calls, list(provider.posts)
    check('Directory listing observation GET offers explicit preparation without DDL', prepare in owner.get(url).text and not db('SHOW TABLES LIKE "ci_provider_directory_indexing_reads"') and provider.calls == calls)
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'})]:
        post(prepare, **fields)
        check('Directory listing observation preparation refuses invalid ' + label, not db('SHOW TABLES LIKE "ci_provider_directory_indexing_reads"') and provider.calls == calls)
    post(prepare)
    check('Directory listing observation preparation is local and explicit', db('SHOW TABLES LIKE "ci_provider_directory_indexing_reads"') != [] and not db('SELECT * FROM ci_provider_directory_indexing_reads') and provider.calls == calls)
    check('Directory listing observation administrator and support access are refused', member.get(url).status_code == 403 and owner.get(url + '&support=fixture').status_code == 403)
    provider.mode = 'success'
    provider.packages += [{'id': 2001, 'typeRef': 12, 'name': 'directory-listing.synthetic.invalid', 'names': ['directory-listing.synthetic.invalid'], 'enabled': True, 'created': '2000-01-01T00:00:00Z'}]
    cool(); response = post('provider_inventory_refresh'); evidence('Hosting observation before assignment', response)
    post('provider_inventory_link', package_id='2001', client_id=str(client_id))
    link = db('SELECT * FROM ci_provider_package_links WHERE profile_id=? AND package_id=2001', [profile['id']])[0]
    target = portal + '?directory_indexing=1&service_link=' + link['id']
    check('Directory listing assignment alone does not share scalar', 'Reported enabled' not in client.get(target).text and 'sharing is off' in client.get(target).text)
    cool(); response = post('provider_inventory_refresh'); evidence('Hosting observation after assignment', response)
    calls = provider.calls
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'}), ('binding', {'binding_sha256': 'a' * 64})]:
        post(refresh, link_id=link['id'], **fields)
        check('Directory listing refresh refuses invalid ' + label + ' before request', provider.calls == calls and not db('SELECT * FROM ci_provider_directory_indexing_reads'))
    cool(); provider.directory_indexing = True
    # The repeat must reach the application inside the shared 30-second cooldown.
    # Posting it only after the first response returns lets a congested runner
    # expire the window first, so dispatch the primary refresh on a thread and,
    # once the ledger records its admission, repost from a second owner session.
    # Reusing the same PHP session would serialize on its lock until the window
    # lapses; a second login keeps the authority and cooldown checks identical.
    data = build(owner, refresh, link_id=link['id']); post.last = data
    twin = requests.Session(); submit(twin, admin + '?view=login', 'login', {'email': directory_indexing_email, 'password': OWNER})
    held = {}
    def run_primary():
        try:
            held['response'] = owner.post(url, data=data)
        except Exception as failure:
            held['error'] = failure
    primary = threading.Thread(target=run_primary); primary.start()
    admitted = db('SELECT COUNT(*) AS n FROM ci_provider_refreshes WHERE actor_id=?', [directory_indexing_owner['id']])[0]['n']
    deadline = time.time() + 240
    while db('SELECT COUNT(*) AS n FROM ci_provider_refreshes WHERE actor_id=?', [directory_indexing_owner['id']])[0]['n'] == admitted and time.time() < deadline:
        time.sleep(0.25)
    repeated = twin.post(url, data=build(twin, refresh, link_id=link['id']))
    primary.join()
    if 'error' in held:
        raise held['error']
    response = held['response']; twin.close()
    evidence('Complete Directory listing observation', response)
    section = soup(response).select_one('#directory-listing')
    check('Directory listing owner form sends one exact assigned-package GET', provider.calls == calls + 1 and provider.get_paths[-1] == '/package/2001/web/directoryIndexing' and section is not None and 'Reported enabled' in section.text and 'Latest saved directory listing setting' in section.text)
    saved = db('SELECT * FROM ci_provider_directory_indexing_observations')[0]
    check('Directory listing true is retained as an exact boolean document', saved['value_json'] == 'true')
    check('Directory listing owner observation has date and separate sharing', 'UTC' in section.text and 'Share saved directory listing setting' in section.text and 'Reported enabled' not in client.get(target).text)
    evidence('Shared cooldown refusal', repeated)
    check('Directory listing immediate repeat obeys common cooldown', provider.calls == calls + 1 and db('SELECT * FROM ci_provider_directory_indexing_observations') == [saved] and any('Wait 30 seconds' in n for n in diagnostics[-1]['inline_errors']))
    post(share, link_id=link['id'], sharing='enable')
    response = client.get(target)
    check('Directory listing explicit owner sharing exposes only scalar and date', 'Reported enabled' in response.text and 'UTC' in response.text and 'Refresh directory listing setting' not in response.text and 'no-store' in response.headers.get('Cache-Control', ''))
    check('Directory listing foreign assignment identifier discloses no scalar', 'Reported enabled' not in client.get(portal + '?directory_indexing=1&service_link=' + 'f' * 32).text)
    def capture(session, title, address, selector):
        context = browser.new_context(viewport={'width': 1440, 'height': 1050}); context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in session.cookies])
        page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.goto(address); page.evaluate('document.fonts.ready'); expect(page.locator(selector)).to_be_visible(); page.screenshot(path=str(qa / (title + '-' + label + '.png')), full_page=True)
            check(title + ' ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        check(title + ' has no script errors', not errors); context.close()
    capture(owner, 'Directory-Listing-Owner', url, '#directory-listing')
    capture(client, 'Directory-Listing-Client', target, '[data-client-directory-listing]')
    cool(); provider.directory_indexing = False; response = post(refresh, link_id=link['id'])
    evidence('Complete reported-disabled observation', response)
    saved_values = db('SELECT * FROM ci_provider_directory_indexing_observations ORDER BY id')
    false_page = client.get(target)
    check('Directory listing false is a complete dated boolean rather than unavailable',
          provider.calls == calls + 2 and diagnostics[-1]['results'][-1]['state'] == 'complete'
          and len(saved_values) == 2 and sorted(r['value_json'] for r in saved_values) == ['false', 'true']
          and 'Reported disabled' in false_page.text and 'Reported enabled' not in false_page.text
          and 'Latest saved observation' in false_page.text
          and 'does not check whether visitors can currently see files' in false_page.text)
    capture(owner, 'Directory-Listing-False-Owner', url, '#directory-listing')
    capture(client, 'Directory-Listing-False-Client', target, '[data-client-directory-listing]')
    cool(); provider.directory_indexing_mode = 'null'; response = post(refresh, link_id=link['id'])
    evidence('Unavailable Directory listing observation', response)
    check('Directory listing five reads consume only the dedicated owner budget', len(diagnostics[-1]['actor_reads']) == 5 and all(r['actor_id'] == directory_indexing_owner['id'] for r in diagnostics[-1]['actor_reads']) and [r['operation'] for r in diagnostics[-1]['actor_reads']] == ['packages.list', 'packages.list', 'package.directory-indexing', 'package.directory-indexing', 'package.directory-indexing'] and [r['state'] for r in diagnostics[-1]['results']] == ['complete', 'complete', 'complete', 'complete', 'unavailable'])
    response = client.get(target)
    check('Directory listing null preserves prior false only as historical', provider.calls == calls + 3 and 'Reported disabled' in response.text and 'Historical observation' in response.text and 'Latest refresh did not provide a directory listing setting' in response.text and diagnostics[-1]['results'][-1]['reason'] == 'value_null' and db('SELECT * FROM ci_provider_directory_indexing_observations ORDER BY id') == saved_values)
    capture(client, 'Directory-Listing-Historical', target, '[data-client-directory-listing]')
    post(share, link_id=link['id'], sharing='disable')
    check('Directory listing owner revocation immediately hides saved scalar', 'Reported disabled' not in client.get(target).text and 'sharing is off' in client.get(target).text)
    counter = db('SELECT value FROM ci_settings WHERE name=?', ['provider_directory_indexing_started_count'])[0]['value']; db('DELETE FROM ci_settings WHERE name=?', ['provider_directory_indexing_started_count'])
    try:
        response = owner.get(url)
        check('Directory listing missing history refuses forms without read-path repair', 'Directory listing observation counts need recovery' in response.text and not soup(response).select_one('#directory-listing [name=action]') and provider.calls == calls + 3)
    finally:
        db('INSERT INTO ci_settings (name,value) VALUES (?,?)', ['provider_directory_indexing_started_count', counter])
    check('Directory listing exact history restoration keeps scalar historical', 'Historical observation' in soup(owner.get(url)).select_one('#directory-listing').text)
    check('Directory listing observation never posts a provider change or leaks raw body', provider.posts == posts and not provider.errors and 'private synthetic Directory listing response' not in response.text)
    retained = db('SELECT * FROM ci_provider_refreshes WHERE seq<=? ORDER BY seq', [max((r['seq'] for r in inherited), default=0)])
    retained_audits = db('SELECT * FROM ci_audit WHERE id<=? ORDER BY id', [max((r['id'] for r in inherited_audits), default=0)])
    check('Directory listing workflow preserves every inherited intent and original audit byte', retained == inherited and retained_audits == inherited_audits)
    check('Directory listing workflow preserves the original owner account and role', db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    (qa / 'directory-listing-history-preservation.json').write_text(json.dumps({'inherited_intents': len(inherited), 'inherited_audits': len(inherited_audits), 'intents_sha256': hashlib.sha256(json.dumps(inherited, sort_keys=True).encode()).hexdigest(), 'audits_sha256': hashlib.sha256(json.dumps(inherited_audits, sort_keys=True).encode()).hexdigest(), 'retained_intents_sha256': hashlib.sha256(json.dumps(retained, sort_keys=True).encode()).hexdigest(), 'retained_audits_sha256': hashlib.sha256(json.dumps(retained_audits, sort_keys=True).encode()).hexdigest(), 'dedicated_actor': directory_indexing_owner['id'], 'dedicated_reads': len(diagnostics[-1]['actor_reads'])}, indent=2))
    provider.directory_indexing_mode = 'success'; owner.close(); member.close(); client.close()
