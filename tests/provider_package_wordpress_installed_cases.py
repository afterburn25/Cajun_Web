"""Exact packaged WordPress installation forms and saved client observation; loopback TLS only."""
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
    (qa / 'package-wordpress-installed-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics, indent=2))
    check('WordPress installation setup login preserves all account authority and updates only login metadata',
          login.status_code == 200 and setup_diagnostics['login']['dashboard']
          and {k: v for k, v in original_owner.items() if k != 'last_login'} == {k: v for k, v in before_setup_owner.items() if k != 'last_login'}
          and original_owner['last_login'] is not None
          and setup_diagnostics['login']['profile_unchanged'])
    staff_url = admin + '?view=staff'; wp_installed_email = 'wordpress-installed-owner@example.invalid'
    check('WordPress installation fixture owner starts absent', not db('SELECT id FROM ci_users WHERE email=?', [wp_installed_email]))
    submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'WordPress installation observation owner', 'email': wp_installed_email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
    wp_installed_owner = db('SELECT id,password_hash FROM ci_users WHERE email=?', [wp_installed_email])[0]
    invite = chosen_form(setup, staff_url, 'staff_invite', 'id', wp_installed_owner['id'], {'owner_password': OWNER})
    invitation = soup(invite).select_one('[data-staff-invite]')['href']; code = parse_qs(urlparse(invitation).fragment)['token'][0]
    activation = requests.Session(); form = activation.get(invitation)
    activated = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(form), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
    setup_diagnostics['activation'] = {'status': activated.status_code, 'redirect_statuses': [r.status_code for r in activated.history],
        'login_page': urlparse(activated.url).path == urlparse(admin).path and urlparse(activated.url).query == 'view=login', 'distinct_actor': wp_installed_owner['id'] != original_owner['id'],
        'authorized_owner': db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [wp_installed_owner['id']]) == [{'role': 'owner', 'enabled': 1}],
        'password_set': db('SELECT password_hash FROM ci_users WHERE id=?', [wp_installed_owner['id']])[0]['password_hash'] != '',
        'original_account_unchanged': db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner],
        'original_profile_unchanged': db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile]}
    (qa / 'package-wordpress-installed-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics, indent=2))
    activation.close(); setup.close()
    check('WordPress installation fixture activates a distinct authorized owner without changing original authority',
          activated.status_code == 200 and setup_diagnostics['activation']['login_page']
          and wp_installed_owner['id'] != original_owner['id'] and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [wp_installed_owner['id']]) == [{'role': 'owner', 'enabled': 1}]
          and db('SELECT password_hash FROM ci_users WHERE id=?', [wp_installed_owner['id']])[0]['password_hash'] != ''
          and db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner]
          and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    owner = requests.Session(); submit(owner, admin + '?view=login', 'login', {'email': wp_installed_email, 'password': OWNER})
    check('WordPress installation dedicated owner has an unused read budget', not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?', [wp_installed_owner['id']]))
    diagnostics = []
    def evidence(label, response):
        # Keep finite error text and outcome metadata, never complete HTML or tokens.
        notices = [n.get_text(' ', strip=True) for n in soup(response).select('.notice.error')]
        notices = [n.replace(OWNER, '[redacted]').replace('SYNTHETIC_RAW_GENERAL_KEY', '[redacted]') for n in notices]
        rows = db('SELECT actor_id,operation,started_epoch FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [wp_installed_owner['id']])
        results = db('SELECT r.state,r.reason,r.package_count FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id WHERE f.actor_id=? ORDER BY f.seq', [wp_installed_owner['id']])
        diagnostics.append({'step': label, 'status': response.status_code, 'redirect_statuses': [r.status_code for r in response.history],
                            'inline_errors': notices, 'actor_id': wp_installed_owner['id'], 'actor_reads': rows, 'results': results, 'provider_calls': provider.calls})
        (qa / 'package-wordpress-installed-response-diagnostics.json').write_text(json.dumps(diagnostics, indent=2))
    member = requests.Session(); submit(member, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    client = requests.Session(); submit(client, portal, 'login', {'email': 'alice@example.invalid', 'password': 'Synthetic client passphrase 2026'})
    profile = db('SELECT id,revision FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
    client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']
    prepare = 'provider_inventory_wp_installed_prepare'; refresh = 'provider_inventory_wp_installed_refresh'; share = 'provider_inventory_wp_installed_share'
    def post(action, **fields):
        page = owner.get(url); forms = soup(page).select('form'); data = {'csrf': token(page), 'action': action, 'current_password': OWNER, 'profile_id': profile['id'], 'revision': str(profile['revision'])}
        for form in forms:
            if form.select_one('input[name=action][value="' + action + '"]'):
                link = form.select_one('input[name=link_id]')
                if not link or link.get('value') == fields.get('link_id'):
                    data.update({n['name']: n.get('value', '') for n in form.select('input[name]')}); break
        data.update({'current_password': OWNER, **fields})
        post.last = data
        return owner.post(url, data=data)
    def cool():
        last = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes WHERE profile_id=?', [profile['id']])[0]['epoch']
        time.sleep(max(0, int(last or 0) + 31 - time.time()))
    calls, posts = provider.calls, list(provider.posts)
    check('WordPress installation observation GET offers explicit preparation without DDL', prepare in owner.get(url).text and not db('SHOW TABLES LIKE "ci_provider_wp_installed_reads"') and provider.calls == calls)
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'})]:
        post(prepare, **fields)
        check('WordPress installation observation preparation refuses invalid ' + label, not db('SHOW TABLES LIKE "ci_provider_wp_installed_reads"') and provider.calls == calls)
    post(prepare)
    check('WordPress installation observation preparation is local and explicit', db('SHOW TABLES LIKE "ci_provider_wp_installed_reads"') != [] and not db('SELECT * FROM ci_provider_wp_installed_reads') and provider.calls == calls)
    check('WordPress installation observation administrator and support access are refused', member.get(url).status_code == 403 and owner.get(url + '&support=fixture').status_code == 403)
    provider.mode = 'success'
    provider.packages += [{'id': 2201, 'typeRef': 12, 'name': 'wordpress-installed-observation.synthetic.invalid', 'names': ['wordpress-installed-observation.synthetic.invalid'], 'enabled': True, 'created': '2000-01-01T00:00:00Z'}]
    cool(); response = post('provider_inventory_refresh'); evidence('Hosting observation before assignment', response)
    post('provider_inventory_link', package_id='2201', client_id=str(client_id))
    link = db('SELECT * FROM ci_provider_package_links WHERE profile_id=? AND package_id=2201', [profile['id']])[0]
    target = portal + '?wp_installed=1&service_link=' + link['id']
    check('WordPress installation assignment alone does not share scalar', 'WordPress reported installed' not in client.get(target).text and 'sharing is off' in client.get(target).text)
    cool(); response = post('provider_inventory_refresh'); evidence('Hosting observation after assignment', response)
    calls = provider.calls
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'}), ('binding', {'binding_sha256': 'a' * 64})]:
        post(refresh, link_id=link['id'], **fields)
        check('WordPress installation refresh refuses invalid ' + label + ' before request', provider.calls == calls and not db('SELECT * FROM ci_provider_wp_installed_reads'))
    cool(); provider.wp_installed = True; response = post(refresh, link_id=link['id'])
    evidence('Complete WordPress installation observation', response)
    section = soup(response).select_one('#package-wordpress-installed')
    check('WordPress installation owner form sends one exact assigned-package GET', provider.calls == calls + 1 and provider.get_paths[-1] == '/package/2201/web/wordpressIsInstalled' and section is not None and 'WordPress reported installed' in section.text and 'Latest saved WordPress installation indication' in section.text)
    saved = db('SELECT * FROM ci_provider_wp_installed_observations')[0]
    check('WordPress installation true is retained as an exact boolean document', saved['value_json'] == 'true')
    check('WordPress installation owner observation has date and separate sharing', 'UTC' in section.text and 'Share saved WordPress installation indication' in section.text and 'WordPress reported installed' not in client.get(target).text)
    for _ in range(3):
        repeated = owner.post(url, data=post.last); evidence('Shared cooldown refusal', repeated)
        if any('Wait 30 seconds' in n for n in diagnostics[-1]['inline_errors']):
            break
    check('WordPress installation immediate repeat obeys common cooldown', provider.calls == calls + 1 and db('SELECT * FROM ci_provider_wp_installed_observations') == [saved] and any('Wait 30 seconds' in n for n in diagnostics[-1]['inline_errors']))
    post(share, link_id=link['id'], sharing='enable')
    response = client.get(target)
    check('WordPress installation explicit owner sharing exposes only scalar and date', 'WordPress reported installed' in response.text and 'UTC' in response.text and 'Refresh WordPress installation indication' not in response.text and 'no-store' in response.headers.get('Cache-Control', ''))
    check('WordPress installation foreign assignment identifier discloses no scalar', 'WordPress reported installed' not in client.get(portal + '?wp_installed=1&service_link=' + 'f' * 32).text)
    def capture(session, title, address, selector):
        context = browser.new_context(viewport={'width': 1440, 'height': 1050}); context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in session.cookies])
        page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.goto(address); page.evaluate('document.fonts.ready'); expect(page.locator(selector)).to_be_visible(); page.screenshot(path=str(qa / (title + '-' + label + '.png')), full_page=True)
            check(title + ' ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            if selector == '#package-wordpress-installed':
                check(title + ' ' + label + ' action labels fit their forms without clipping', page.locator(selector + ' button').evaluate_all('''buttons => buttons.length > 0 && buttons.every(button => {
                    const bounds = button.getBoundingClientRect();
                    const form = button.closest('form').getBoundingClientRect();
                    const text = document.createRange();
                    text.selectNodeContents(button);
                    return bounds.left >= form.left - 1 && bounds.right <= form.right + 1
                        && button.scrollWidth <= button.clientWidth + 1
                        && [...text.getClientRects()].every(rect => rect.left >= bounds.left - 1
                            && rect.right <= bounds.right + 1 && rect.top >= bounds.top - 1
                            && rect.bottom <= bounds.bottom + 1);
                })'''))
        check(title + ' has no script errors', not errors); context.close()
    capture(owner, 'Package-WordPress-Installed-Owner', url, '#package-wordpress-installed')
    capture(client, 'Package-WordPress-Installed-Client', target, '[data-client-wordpress-installed]')
    cool(); provider.wp_installed = False; response = post(refresh, link_id=link['id'])
    evidence('Complete reported-false observation', response)
    saved_values = db('SELECT * FROM ci_provider_wp_installed_observations ORDER BY id')
    false_page = client.get(target)
    check('WordPress installation false is a complete dated boolean rather than unavailable',
          provider.calls == calls + 2 and diagnostics[-1]['results'][-1]['state'] == 'complete'
          and len(saved_values) == 2 and sorted(r['value_json'] for r in saved_values) == ['false', 'true']
          and 'Provider reported false' in false_page.text and 'WordPress reported installed' not in false_page.text
          and 'Latest saved observation' in false_page.text
          and 'does not check its version, security or whether the site works' in false_page.text)
    capture(owner, 'Package-WordPress-Installed-False-Owner', url, '#package-wordpress-installed')
    capture(client, 'Package-WordPress-Installed-False-Client', target, '[data-client-wordpress-installed]')
    cool(); provider.wp_installed_mode = 'null'; response = post(refresh, link_id=link['id'])
    evidence('Unavailable WordPress installation observation', response)
    check('WordPress installation five reads consume only the dedicated owner budget', len(diagnostics[-1]['actor_reads']) == 5 and all(r['actor_id'] == wp_installed_owner['id'] for r in diagnostics[-1]['actor_reads']) and [r['operation'] for r in diagnostics[-1]['actor_reads']] == ['packages.list', 'packages.list', 'package.wordpress-installed', 'package.wordpress-installed', 'package.wordpress-installed'] and [r['state'] for r in diagnostics[-1]['results']] == ['complete', 'complete', 'complete', 'complete', 'unavailable'])
    response = client.get(target)
    check('WordPress installation null preserves prior false only as historical', provider.calls == calls + 3 and 'Provider reported false' in response.text and 'Historical observation' in response.text and 'Latest refresh did not provide a WordPress installation indication' in response.text and diagnostics[-1]['results'][-1]['reason'] == 'value_null' and db('SELECT * FROM ci_provider_wp_installed_observations ORDER BY id') == saved_values)
    capture(client, 'Package-WordPress-Installed-Historical', target, '[data-client-wordpress-installed]')
    post(share, link_id=link['id'], sharing='disable')
    check('WordPress installation owner revocation immediately hides saved scalar', 'Provider reported false' not in client.get(target).text and 'sharing is off' in client.get(target).text)
    counter = db('SELECT value FROM ci_settings WHERE name=?', ['provider_wp_installed_started_count'])[0]['value']; db('DELETE FROM ci_settings WHERE name=?', ['provider_wp_installed_started_count'])
    try:
        response = owner.get(url)
        check('WordPress installation missing history refuses forms without read-path repair', 'WordPress installation observation counts need recovery' in response.text and not soup(response).select_one('#package-wordpress-installed [name=action]') and provider.calls == calls + 3)
    finally:
        db('INSERT INTO ci_settings (name,value) VALUES (?,?)', ['provider_wp_installed_started_count', counter])
    check('WordPress installation exact history restoration keeps scalar historical', 'Historical observation' in soup(owner.get(url)).select_one('#package-wordpress-installed').text)
    check('WordPress installation observation never posts a provider change or leaks raw body', provider.posts == posts and not provider.errors and 'private synthetic WordPress installation response' not in response.text)
    retained = db('SELECT * FROM ci_provider_refreshes WHERE seq<=? ORDER BY seq', [max((r['seq'] for r in inherited), default=0)])
    retained_audits = db('SELECT * FROM ci_audit WHERE id<=? ORDER BY id', [max((r['id'] for r in inherited_audits), default=0)])
    check('WordPress installation workflow preserves every inherited intent and original audit byte', retained == inherited and retained_audits == inherited_audits)
    check('WordPress installation workflow preserves the original owner account and role', db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    (qa / 'package-wordpress-installed-history-preservation.json').write_text(json.dumps({'inherited_intents': len(inherited), 'inherited_audits': len(inherited_audits), 'intents_sha256': hashlib.sha256(json.dumps(inherited, sort_keys=True).encode()).hexdigest(), 'audits_sha256': hashlib.sha256(json.dumps(inherited_audits, sort_keys=True).encode()).hexdigest(), 'retained_intents_sha256': hashlib.sha256(json.dumps(retained, sort_keys=True).encode()).hexdigest(), 'retained_audits_sha256': hashlib.sha256(json.dumps(retained_audits, sort_keys=True).encode()).hexdigest(), 'dedicated_actor': wp_installed_owner['id'], 'dedicated_reads': len(diagnostics[-1]['actor_reads'])}, indent=2))
    provider.wp_installed_mode = 'success'; owner.close(); member.close(); client.close()
