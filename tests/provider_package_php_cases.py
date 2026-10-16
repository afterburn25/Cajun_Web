"""Exact packaged PHP-version forms and saved client observation; loopback TLS only."""
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
    (qa / 'package-php-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics, indent=2))
    check('PHP setup login preserves all account authority and updates only login metadata',
          login.status_code == 200 and setup_diagnostics['login']['dashboard']
          and {k: v for k, v in original_owner.items() if k != 'last_login'} == {k: v for k, v in before_setup_owner.items() if k != 'last_login'}
          and original_owner['last_login'] is not None
          and setup_diagnostics['login']['profile_unchanged'])
    staff_url = admin + '?view=staff'; php_email = 'php-version-owner@example.invalid'
    check('PHP fixture owner starts absent', not db('SELECT id FROM ci_users WHERE email=?', [php_email]))
    submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'PHP observation owner', 'email': php_email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
    php_owner = db('SELECT id,password_hash FROM ci_users WHERE email=?', [php_email])[0]
    invite = chosen_form(setup, staff_url, 'staff_invite', 'id', php_owner['id'], {'owner_password': OWNER})
    invitation = soup(invite).select_one('[data-staff-invite]')['href']; code = parse_qs(urlparse(invitation).fragment)['token'][0]
    activation = requests.Session(); form = activation.get(invitation)
    activated = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(form), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
    setup_diagnostics['activation'] = {'status': activated.status_code, 'redirect_statuses': [r.status_code for r in activated.history],
        'login_page': urlparse(activated.url).path == urlparse(admin).path and urlparse(activated.url).query == 'view=login', 'distinct_actor': php_owner['id'] != original_owner['id'],
        'authorized_owner': db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [php_owner['id']]) == [{'role': 'owner', 'enabled': 1}],
        'password_set': db('SELECT password_hash FROM ci_users WHERE id=?', [php_owner['id']])[0]['password_hash'] != '',
        'original_account_unchanged': db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner],
        'original_profile_unchanged': db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile]}
    (qa / 'package-php-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics, indent=2))
    activation.close(); setup.close()
    check('PHP fixture activates a distinct authorized owner without changing original authority',
          activated.status_code == 200 and setup_diagnostics['activation']['login_page']
          and php_owner['id'] != original_owner['id'] and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [php_owner['id']]) == [{'role': 'owner', 'enabled': 1}]
          and db('SELECT password_hash FROM ci_users WHERE id=?', [php_owner['id']])[0]['password_hash'] != ''
          and db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner]
          and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    owner = requests.Session(); submit(owner, admin + '?view=login', 'login', {'email': php_email, 'password': OWNER})
    check('PHP dedicated owner has an unused read budget', not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?', [php_owner['id']]))
    diagnostics = []
    def evidence(label, response):
        # Keep finite error text and outcome metadata, never complete HTML or tokens.
        notices = [n.get_text(' ', strip=True) for n in soup(response).select('.notice.error')]
        notices = [n.replace(OWNER, '[redacted]').replace('SYNTHETIC_RAW_GENERAL_KEY', '[redacted]') for n in notices]
        rows = db('SELECT actor_id,operation,started_epoch FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [php_owner['id']])
        results = db('SELECT r.state,r.reason,r.package_count FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id WHERE f.actor_id=? ORDER BY f.seq', [php_owner['id']])
        diagnostics.append({'step': label, 'status': response.status_code, 'redirect_statuses': [r.status_code for r in response.history],
                            'inline_errors': notices, 'actor_id': php_owner['id'], 'actor_reads': rows, 'results': results, 'provider_calls': provider.calls})
        (qa / 'package-php-response-diagnostics.json').write_text(json.dumps(diagnostics, indent=2))
    member = requests.Session(); submit(member, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    client = requests.Session(); submit(client, portal, 'login', {'email': 'alice@example.invalid', 'password': 'Synthetic client passphrase 2026'})
    profile = db('SELECT id,revision FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
    client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']
    prepare = 'provider_inventory_php_prepare'; refresh = 'provider_inventory_php_refresh'; share = 'provider_inventory_php_share'
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
    check('PHP observation GET offers explicit preparation without DDL', prepare in owner.get(url).text and not db('SHOW TABLES LIKE "ci_provider_php_reads"') and provider.calls == calls)
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'})]:
        post(prepare, **fields)
        check('PHP observation preparation refuses invalid ' + label, not db('SHOW TABLES LIKE "ci_provider_php_reads"') and provider.calls == calls)
    post(prepare)
    check('PHP observation preparation is local and explicit', db('SHOW TABLES LIKE "ci_provider_php_reads"') != [] and not db('SELECT * FROM ci_provider_php_reads') and provider.calls == calls)
    check('PHP observation administrator and support access are refused', member.get(url).status_code == 403 and owner.get(url + '&support=fixture').status_code == 403)
    provider.mode = 'success'
    provider.packages += [{'id': 1701, 'typeRef': 12, 'name': 'php-observation.synthetic.invalid', 'names': ['php-observation.synthetic.invalid'], 'enabled': True, 'created': '2000-01-01T00:00:00Z'}]
    cool(); response = post('provider_inventory_refresh'); evidence('Hosting observation before assignment', response)
    post('provider_inventory_link', package_id='1701', client_id=str(client_id))
    link = db('SELECT * FROM ci_provider_package_links WHERE profile_id=? AND package_id=1701', [profile['id']])[0]
    target = portal + '?php_version=1&service_link=' + link['id']
    check('PHP assignment alone does not share scalar', 'php82' not in client.get(target).text and 'sharing is off' in client.get(target).text)
    cool(); response = post('provider_inventory_refresh'); evidence('Hosting observation after assignment', response)
    calls = provider.calls
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'}), ('binding', {'binding_sha256': 'a' * 64})]:
        post(refresh, link_id=link['id'], **fields)
        check('PHP refresh refuses invalid ' + label + ' before request', provider.calls == calls and not db('SELECT * FROM ci_provider_php_reads'))
    cool(); provider.php_version = 'php82'; response = post(refresh, link_id=link['id'])
    evidence('Complete PHP observation', response)
    section = soup(response).select_one('#package-php-version')
    check('PHP owner form sends one exact assigned-package GET', provider.calls == calls + 1 and provider.get_paths[-1] == '/package/1701/web/phpVersion' and section is not None and 'php82' in section.text and 'Latest saved PHP version' in section.text)
    saved = db('SELECT * FROM ci_provider_php_observations')[0]
    check('PHP owner observation has date and separate sharing', 'UTC' in section.text and 'Share saved PHP version' in section.text and 'php82' not in client.get(target).text)
    repeated = post(refresh, link_id=link['id']); evidence('Shared cooldown refusal', repeated)
    check('PHP immediate repeat obeys common cooldown', provider.calls == calls + 1 and db('SELECT * FROM ci_provider_php_observations') == [saved] and any('Wait 30 seconds' in n for n in diagnostics[-1]['inline_errors']))
    post(share, link_id=link['id'], sharing='enable')
    response = client.get(target)
    check('PHP explicit owner sharing exposes only scalar and date', 'php82' in response.text and 'UTC' in response.text and 'Refresh PHP version' not in response.text and 'no-store' in response.headers.get('Cache-Control', ''))
    check('PHP foreign assignment identifier discloses no scalar', 'php82' not in client.get(portal + '?php_version=1&service_link=' + 'f' * 32).text)
    def capture(session, title, address, selector):
        context = browser.new_context(viewport={'width': 1440, 'height': 1050}); context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in session.cookies])
        page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.goto(address); page.evaluate('document.fonts.ready'); expect(page.locator(selector)).to_be_visible(); page.screenshot(path=str(qa / (title + '-' + label + '.png')), full_page=True)
            check(title + ' ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        check(title + ' has no script errors', not errors); context.close()
    capture(owner, 'Package-PHP-Owner', url, '#package-php-version')
    capture(client, 'Package-PHP-Client', target, '[data-client-php-version]')
    cool(); provider.php_version_mode = 'object'; response = post(refresh, link_id=link['id'])
    evidence('Unavailable PHP observation', response)
    check('PHP four reads consume only the dedicated owner budget', len(diagnostics[-1]['actor_reads']) == 4 and all(r['actor_id'] == php_owner['id'] for r in diagnostics[-1]['actor_reads']) and [r['operation'] for r in diagnostics[-1]['actor_reads']] == ['packages.list', 'packages.list', 'package.php-version', 'package.php-version'] and [r['state'] for r in diagnostics[-1]['results']] == ['complete', 'complete', 'complete', 'unavailable'])
    response = client.get(target)
    check('PHP later invalid response preserves prior value as historical', provider.calls == calls + 2 and 'php82' in response.text and 'Historical observation' in response.text and 'Latest refresh did not provide a PHP version' in response.text and db('SELECT * FROM ci_provider_php_observations') == [saved])
    capture(client, 'Package-PHP-Historical', target, '[data-client-php-version]')
    post(share, link_id=link['id'], sharing='disable')
    check('PHP owner revocation immediately hides saved scalar', 'php82' not in client.get(target).text and 'sharing is off' in client.get(target).text)
    counter = db('SELECT value FROM ci_settings WHERE name=?', ['provider_php_started_count'])[0]['value']; db('DELETE FROM ci_settings WHERE name=?', ['provider_php_started_count'])
    try:
        response = owner.get(url)
        check('PHP missing history refuses forms without read-path repair', 'PHP observation counts need recovery' in response.text and not soup(response).select_one('#package-php-version [name=action]') and provider.calls == calls + 2)
    finally:
        db('INSERT INTO ci_settings (name,value) VALUES (?,?)', ['provider_php_started_count', counter])
    check('PHP exact history restoration keeps scalar historical', 'Historical observation' in soup(owner.get(url)).select_one('#package-php-version').text)
    check('PHP observation never posts a provider change or leaks raw body', provider.posts == posts and not provider.errors and 'private synthetic PHP response' not in response.text)
    retained = db('SELECT * FROM ci_provider_refreshes WHERE seq<=? ORDER BY seq', [max((r['seq'] for r in inherited), default=0)])
    retained_audits = db('SELECT * FROM ci_audit WHERE id<=? ORDER BY id', [max((r['id'] for r in inherited_audits), default=0)])
    check('PHP workflow preserves every inherited intent and original audit byte', retained == inherited and retained_audits == inherited_audits)
    check('PHP workflow preserves the original owner account and role', db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    (qa / 'package-php-history-preservation.json').write_text(json.dumps({'inherited_intents': len(inherited), 'inherited_audits': len(inherited_audits), 'intents_sha256': hashlib.sha256(json.dumps(inherited, sort_keys=True).encode()).hexdigest(), 'audits_sha256': hashlib.sha256(json.dumps(inherited_audits, sort_keys=True).encode()).hexdigest(), 'retained_intents_sha256': hashlib.sha256(json.dumps(retained, sort_keys=True).encode()).hexdigest(), 'retained_audits_sha256': hashlib.sha256(json.dumps(retained_audits, sort_keys=True).encode()).hexdigest(), 'dedicated_actor': php_owner['id'], 'dedicated_reads': len(diagnostics[-1]['actor_reads'])}, indent=2))
    provider.php_version_mode = 'success'; owner.close(); member.close(); client.close()
