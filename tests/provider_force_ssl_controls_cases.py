"""Exact owner control approval and independent GET readback; synthetic loopback TLS only."""
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
    (qa / 'force-ssl-controls-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics, indent=2))
    check('Force SSL setup login preserves all account authority and updates only login metadata',
          login.status_code == 200 and setup_diagnostics['login']['dashboard']
          and {k: v for k, v in original_owner.items() if k != 'last_login'} == {k: v for k, v in before_setup_owner.items() if k != 'last_login'}
          and original_owner['last_login'] is not None
          and setup_diagnostics['login']['profile_unchanged'])
    staff_url = admin + '?view=staff'; force_ssl_email = 'force-ssl-controls-owner@example.invalid'
    check('Force SSL fixture owner starts absent', not db('SELECT id FROM ci_users WHERE email=?', [force_ssl_email]))
    submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'Force SSL control owner', 'email': force_ssl_email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
    force_ssl_owner = db('SELECT id,password_hash FROM ci_users WHERE email=?', [force_ssl_email])[0]
    invite = chosen_form(setup, staff_url, 'staff_invite', 'id', force_ssl_owner['id'], {'owner_password': OWNER})
    invitation = soup(invite).select_one('[data-staff-invite]')['href']; code = parse_qs(urlparse(invitation).fragment)['token'][0]
    activation = requests.Session(); form = activation.get(invitation)
    activated = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(form), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
    setup_diagnostics['activation'] = {'status': activated.status_code, 'redirect_statuses': [r.status_code for r in activated.history],
        'login_page': urlparse(activated.url).path == urlparse(admin).path and urlparse(activated.url).query == 'view=login', 'distinct_actor': force_ssl_owner['id'] != original_owner['id'],
        'authorized_owner': db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [force_ssl_owner['id']]) == [{'role': 'owner', 'enabled': 1}],
        'password_set': db('SELECT password_hash FROM ci_users WHERE id=?', [force_ssl_owner['id']])[0]['password_hash'] != '',
        'original_account_unchanged': db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner],
        'original_profile_unchanged': db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile]}
    (qa / 'force-ssl-controls-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics, indent=2))
    activation.close(); setup.close()
    check('Force SSL fixture activates a distinct authorized owner without changing original authority',
          activated.status_code == 200 and setup_diagnostics['activation']['login_page']
          and force_ssl_owner['id'] != original_owner['id'] and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [force_ssl_owner['id']]) == [{'role': 'owner', 'enabled': 1}]
          and db('SELECT password_hash FROM ci_users WHERE id=?', [force_ssl_owner['id']])[0]['password_hash'] != ''
          and db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner]
          and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    owner = requests.Session(); submit(owner, admin + '?view=login', 'login', {'email': force_ssl_email, 'password': OWNER})
    check('Force SSL dedicated owner has an unused read budget', not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?', [force_ssl_owner['id']]))
    diagnostics = []
    def evidence(label, response):
        # Keep finite error text and outcome metadata, never complete HTML or tokens.
        notices = [n.get_text(' ', strip=True) for n in soup(response).select('.notice.error')]
        notices = [n.replace(OWNER, '[redacted]').replace('SYNTHETIC_RAW_GENERAL_KEY', '[redacted]') for n in notices]
        rows = db('SELECT actor_id,operation,started_epoch FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [force_ssl_owner['id']])
        results = db('SELECT r.state,r.reason,r.package_count FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id WHERE f.actor_id=? ORDER BY f.seq', [force_ssl_owner['id']])
        diagnostics.append({'step': label, 'status': response.status_code, 'redirect_statuses': [r.status_code for r in response.history],
                            'inline_errors': notices, 'actor_id': force_ssl_owner['id'], 'actor_reads': rows, 'results': results, 'provider_calls': provider.calls})
        (qa / 'force-ssl-controls-response-diagnostics.json').write_text(json.dumps(diagnostics, indent=2))
    member = requests.Session(); submit(member, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    client = requests.Session(); submit(client, portal, 'login', {'email': 'alice@example.invalid', 'password': 'Synthetic client passphrase 2026'})
    profile = db('SELECT id,revision FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
    client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']
    prepare = 'provider_force_ssl_control_prepare'; refresh = 'provider_inventory_force_ssl_refresh'; share = 'provider_inventory_force_ssl_share'
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
    check('Force SSL control GET never prepares missing storage', prepare in owner.get(url).text and not db('SHOW TABLES LIKE "ci_provider_force_ssl_changes"') and provider.calls == calls)
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'})]:
        post(prepare, **fields)
        check('Force SSL control preparation refuses invalid ' + label, not db('SHOW TABLES LIKE "ci_provider_force_ssl_changes"') and provider.calls == calls)
    response = post(prepare); evidence('Prepare local control storage', response)
    check('Force SSL control preparation is explicit and local', bool(db('SHOW TABLES LIKE "ci_provider_force_ssl_changes"')) and provider.calls == calls)
    check('Force SSL controls reject administrator and support access', member.get(url).status_code == 403 and owner.get(url + '&support=fixture').status_code == 403)
    provider.mode = 'success'; provider.force_ssl_mode = 'success'; provider.force_ssl = False
    provider.packages += [{'id': 2101, 'typeRef': 12, 'name': 'force-ssl-controls.synthetic.invalid', 'names': ['force-ssl-controls.synthetic.invalid'], 'enabled': True, 'created': '2000-01-01T00:00:00Z'}]
    cool(); response = post('provider_inventory_refresh'); evidence('Control hosting before assignment', response)
    post('provider_inventory_link', package_id='2101', client_id=str(client_id))
    link = db('SELECT * FROM ci_provider_package_links WHERE profile_id=? AND package_id=2101', [profile['id']])[0]
    target = portal + '?force_ssl=1&service_link=' + link['id']
    cool(); response = post('provider_inventory_refresh'); evidence('Control hosting after assignment', response)
    cool(); response = post(refresh, link_id=link['id']); evidence('Initial reported false', response)
    check('Control form requires the exact current flag and approval', soup(response).select_one('#force-ssl-changes [data-force-ssl-control-change] [name=reviewed]') is not None)
    post(share, link_id=link['id'], sharing='enable')
    check('Control client sharing starts separately with reported false', 'Flag reported false' in client.get(target).text)
    action = 'provider_force_ssl_control_change'; calls = provider.calls
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'}), ('confirmation', {'reviewed': '0'}), ('desired type', {'desired': '1'}), ('binding', {'binding_sha256': 'a' * 64})]:
        post(action, link_id=link['id'], reviewed='1', **fields) if label != 'confirmation' else post(action, link_id=link['id'], **fields)
        check('Force SSL control refuses invalid ' + label + ' before dispatch', provider.calls == calls and not db('SELECT * FROM ci_provider_force_ssl_changes'))
    provider.write_mode = 'success'; response = post(action, link_id=link['id'], reviewed='1'); evidence('Requested flag change with unverified HTTP 200', response)
    request = db('SELECT * FROM ci_provider_force_ssl_changes WHERE link_id=?', [link['id']])[0]
    ledger = db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['forcessl:' + request['id']])[0]
    terminal = db('SELECT * FROM ci_provider_force_ssl_dispatch_ends WHERE id=?', [request['id']])[0]
    check('HTTP 200 does not confirm the requested Force SSL effect', provider.calls == calls + 1 and provider.posts[-1] == {'path': '/package/2101/web/forceSSL', 'body': {'value': True}} and ledger['status'] == 'uncertain' and terminal['disposition'] == 'attempted' and 'Change requested — effect is uncertain' in response.text)
    check('Pending change demotes client precondition to historical without exposing approval', 'Historical observation' in client.get(target).text and 'Change requested' not in client.get(target).text and 'current_password' not in client.get(target).text)
    # Replay the exact submitted consent and then a fresh nonce. Neither may send again.
    doc = json.loads(request['document_json'])
    repeated = {'request_id': request['id'], 'link_id': link['id'], 'anchor_id': doc['anchor_id'], 'binding_sha256': db('SELECT binding_sha256 FROM ci_provider_force_ssl_reads WHERE id=?', [doc['anchor_id']])[0]['binding_sha256'], 'desired': 'true', 'reviewed': '1'}
    post(action, **repeated); repeated['request_id'] = 'e' * 32; post(action, **repeated)
    check('HTTP control replay and new nonce cannot repeat unknown effect', provider.calls == calls + 1 and len(db('SELECT * FROM ci_provider_force_ssl_changes')) == 1)
    def capture(session, title, address, selector):
        context = browser.new_context(viewport={'width': 1440, 'height': 1050}); context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in session.cookies])
        page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.goto(address); page.evaluate('document.fonts.ready'); expect(page.locator(selector)).to_be_visible(); page.screenshot(path=str(qa / (title + '-' + label + '.png')), full_page=True)
            check(title + ' ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        check(title + ' has no script errors', not errors); context.close()
    capture(owner, 'Force-SSL-Control-Uncertain-Owner', url, '#force-ssl-changes')
    capture(client, 'Force-SSL-Control-Historical-Client', target, '[data-client-force-ssl]')
    # The verify form and each follow-up verify require the latest packages.list
    # observation inside the 900-second freshness window. The replay/refusal and
    # browser capture sections above can cross it, so re-read hosting once here;
    # the recovery section re-reads again at its own boundary below.
    cool(); response = post('provider_inventory_refresh'); evidence('Control hosting re-read before follow-up verifies', response)
    verify = 'provider_force_ssl_control_verify'
    cool(); response = post(verify, request_id=request['id']); evidence('Fresh reported false differs from requested true', response)
    check('Different reported flag does not permit resend', db('SELECT disposition FROM ci_provider_force_ssl_change_checks WHERE request_id=?', [request['id']]) == [{'disposition': 'different'}] and 'Change requested — effect is uncertain' in response.text and provider.posts == posts + [{'path': '/package/2101/web/forceSSL', 'body': {'value': True}}])
    cool(); provider.force_ssl_mode = 'null'; response = post(verify, request_id=request['id']); evidence('Unavailable follow-up flag', response)
    check('Null follow-up stays unknown rather than reported false', sorted(r['disposition'] for r in db('SELECT disposition FROM ci_provider_force_ssl_change_checks WHERE request_id=?', [request['id']])) == ['different', 'unavailable'] and 'Change requested — effect is uncertain' in response.text)
    cool(); provider.force_ssl_mode = 'success'; provider.force_ssl = True; response = post(verify, request_id=request['id']); evidence('Desired reported flag after request', response)
    check('Exact new GET saves desired flag observed after request', 'Desired flag observed after the request' in response.text and 'does not prove the request caused the flag value' in response.text and db('SELECT * FROM ci_provider_operations WHERE id=?', [ledger['id']]) == [ledger] and db('SELECT * FROM ci_provider_force_ssl_dispatch_ends WHERE id=?', [request['id']]) == [terminal])
    response = client.get(target)
    check('Separately shared client sees reported flag without setter or health claim', 'Flag reported set' in response.text and 'Latest saved observation' in response.text and 'does not check its certificate or whether HTTPS works' in response.text and 'Request flag' not in response.text and request['id'] not in response.text)
    capture(owner, 'Force-SSL-Control-Observed-Owner', url, '#force-ssl-changes')
    capture(client, 'Force-SSL-Control-Observed-Client', target, '[data-client-force-ssl]')
    # Reproduce the precise recoverable window with a real terminal audit rollback
    # after the synchronous synthetic POST has saved its durable ledger result.
    db("CREATE TRIGGER ci_force_ssl_recovery_fault BEFORE INSERT ON ci_audit FOR EACH ROW BEGIN IF NEW.action='provider.force_ssl_control_terminal' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic terminal rollback'; END IF; END")
    before_write_calls = provider.calls
    try:
        response = post(action, link_id=link['id'], reviewed='1')
    finally:
        db('DROP TRIGGER ci_force_ssl_recovery_fault')
    interrupted = db('SELECT * FROM ci_provider_force_ssl_changes WHERE link_id=? ORDER BY grant_seq DESC LIMIT 1', [link['id']])[0]
    interrupted_grant = db('SELECT * FROM ci_provider_operation_grants WHERE id=?', [interrupted['id']])[0]
    interrupted_ledger = db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['forcessl:' + interrupted['id']])[0]
    check('Recovery HTTP fixture retains one finished attempt and genuinely absent terminal after rollback',
          interrupted['id'] != request['id'] and provider.calls == before_write_calls + 1
          and provider.posts[-1] == {'path': '/package/2101/web/forceSSL', 'body': {'value': False}}
          and interrupted_ledger['status'] == 'uncertain' and interrupted_ledger['attempts'] == 1
          and interrupted_ledger['resolved_epoch'] is not None
          and not db('SELECT id FROM ci_provider_force_ssl_dispatch_ends WHERE id=?', [interrupted['id']]))
    cool(); response = post('provider_inventory_refresh'); evidence('Recovery hosting context', response)
    recovery_action = 'provider_force_ssl_control_recover'
    calls = provider.calls
    terminals = db('SELECT * FROM ci_provider_force_ssl_dispatch_ends ORDER BY id')
    recovery_audits = db('SELECT * FROM ci_audit ORDER BY id')
    recovery_count = db('SELECT * FROM ci_settings WHERE name=?', ['provider_force_ssl_control_terminal_count'])
    recovery_page = owner.get(url)
    recovery_form = soup(recovery_page).select_one('[data-force-ssl-control-recover]')
    check('Recovery GET offers only a local password-reviewed restore without repairing evidence',
          recovery_form is not None and 'Restore local dispatch record' in recovery_page.text
          and provider.calls == calls and db('SELECT * FROM ci_provider_force_ssl_dispatch_ends ORDER BY id') == terminals
          and db('SELECT * FROM ci_audit ORDER BY id') == recovery_audits)
    recovery_fields = {n['name']: n.get('value', '') for n in recovery_form.select('input[name]')}
    recovery_fields.update(current_password=OWNER, reviewed='1')
    for label, changed, refusal in [('CSRF', {'csrf': 'invalid'}, 'Your session expired. Refresh the page and try again.'),
                                    ('password', {'current_password': 'wrong'}, 'Your current password is not correct.'),
                                    ('confirmation', {'reviewed': '0'}, 'Review this local dispatch recovery first.'),
                                    ('review hash', {'ledger_sha256': 'f' * 64}, 'The reviewed dispatch completion changed. Reload its history.')]:
        response = owner.post(url, data={**recovery_fields, **changed})
        check('Recovery HTTP refuses invalid ' + label + ' without a read write or local repair',
              response.status_code == 200 and refusal in [n.get_text(' ', strip=True) for n in soup(response).select('.notice.error')]
              and provider.calls == calls and db('SELECT * FROM ci_provider_force_ssl_dispatch_ends ORDER BY id') == terminals
              and db('SELECT * FROM ci_settings WHERE name=?', ['provider_force_ssl_control_terminal_count']) == recovery_count)
    check('Client does not receive owner recovery metadata or action', recovery_action not in client.get(target).text
          and interrupted['id'] not in client.get(target).text and recovery_fields['ledger_sha256'] not in client.get(target).text)
    capture(owner, 'Force-SSL-Control-Recovery-Owner', url, '[data-force-ssl-control-recover]')
    response = owner.post(url, data=recovery_fields); evidence('Restore only missing local dispatch record', response)
    recovered_terminal = db('SELECT * FROM ci_provider_force_ssl_dispatch_ends WHERE id=?', [interrupted['id']])[0]
    check('Owner HTTP recovery appends attempted witness and keeps uncertainty without provider traffic',
          provider.calls == calls and recovered_terminal['disposition'] == 'attempted'
          and json.loads(recovered_terminal['document_json'])['recovered_from_ledger'] is True
          and 'The effect remains uncertain' in response.text and 'data-force-ssl-control-recovered' in response.text
          and not soup(response).select_one('[data-force-ssl-control-recover]')
          and db('SELECT * FROM ci_provider_force_ssl_changes WHERE id=?', [interrupted['id']]) == [interrupted]
          and db('SELECT * FROM ci_provider_operation_grants WHERE id=?', [interrupted['id']]) == [interrupted_grant]
          and db('SELECT * FROM ci_provider_operations WHERE id=?', [interrupted_ledger['id']]) == [interrupted_ledger])
    replay_audits = db('SELECT * FROM ci_audit ORDER BY id')
    response = owner.post(url, data=recovery_fields)
    check('Captured recovery form cannot replace witness or append another audit',
          'already has a dispatch record' in response.text and provider.calls == calls
          and db('SELECT * FROM ci_provider_force_ssl_dispatch_ends WHERE id=?', [interrupted['id']]) == [recovered_terminal]
          and db('SELECT * FROM ci_audit ORDER BY id') == replay_audits)
    interrupted_doc = json.loads(interrupted['document_json'])
    post(action, request_id='d' * 32, link_id=link['id'], anchor_id=interrupted_doc['anchor_id'],
         binding_sha256=db('SELECT binding_sha256 FROM ci_provider_force_ssl_reads WHERE id=?', [interrupted_doc['anchor_id']])[0]['binding_sha256'], desired='false', reviewed='1')
    check('Local restoration alone leaves new writes fenced and client value historical', provider.calls == calls
          and not db('SELECT id FROM ci_provider_force_ssl_changes WHERE id=?', ['d' * 32])
          and 'Historical observation' in client.get(target).text)
    capture(owner, 'Force-SSL-Control-Recovered-Uncertain-Owner', url, '[data-force-ssl-control-history]:has([data-force-ssl-control-recovered])')
    cool(); provider.force_ssl = False; response = post(verify, request_id=interrupted['id']); evidence('Separate GET after local dispatch recovery', response)
    check('Only separate actual GET confirms desired flag after recovered request', provider.calls == calls + 1
          and db('SELECT disposition FROM ci_provider_force_ssl_change_checks WHERE request_id=?', [interrupted['id']]) == [{'disposition': 'observed'}]
          and db('SELECT * FROM ci_provider_operations WHERE id=?', [interrupted_ledger['id']]) == [interrupted_ledger]
          and db('SELECT * FROM ci_provider_force_ssl_dispatch_ends WHERE id=?', [interrupted['id']]) == [recovered_terminal])
    check('Independent client sharing survives local recovery without private evidence',
          'Flag reported false' in client.get(target).text and 'Latest saved observation' in client.get(target).text
          and interrupted['id'] not in client.get(target).text and recovery_fields['ledger_sha256'] not in client.get(target).text)
    post(share, link_id=link['id'], sharing='disable')
    check('Client revocation remains independent after observed change', all(label not in client.get(target).text for label in ['Flag reported set', 'Flag reported false']))
    counter = db('SELECT value FROM ci_settings WHERE name=?', ['provider_force_ssl_control_request_count'])[0]['value']; db('DELETE FROM ci_settings WHERE name=?', ['provider_force_ssl_control_request_count'])
    before_recovery_calls = provider.calls
    try:
        response = owner.get(url)
        check('Established missing control evidence fails closed without GET repair', 'Force SSL change counts need recovery' in response.text and not soup(response).select_one('#force-ssl-changes [name=action]') and provider.calls == before_recovery_calls and not db('SELECT name FROM ci_settings WHERE name=?', ['provider_force_ssl_control_request_count']))
    finally:
        db('INSERT INTO ci_settings (name,value) VALUES (?,?)', ['provider_force_ssl_control_request_count', counter])
    check('Exact control history restoration recovers saved owner evidence', 'Desired flag observed after the request' in owner.get(url).text)
    rows = db('SELECT operation FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [force_ssl_owner['id']])
    check('Control workflow uses nine own reads and two synthetic writes with local-only recovery', [r['operation'] for r in rows] == ['packages.list', 'packages.list', 'package.force-ssl', 'packages.list'] + ['package.force-ssl'] * 3 + ['packages.list', 'package.force-ssl'] and provider.posts == posts + [{'path': '/package/2101/web/forceSSL', 'body': {'value': True}}, {'path': '/package/2101/web/forceSSL', 'body': {'value': False}}] and not provider.errors)
    retained = db('SELECT * FROM ci_provider_refreshes WHERE seq<=? ORDER BY seq', [max((r['seq'] for r in inherited), default=0)])
    retained_audits = db('SELECT * FROM ci_audit WHERE id<=? ORDER BY id', [max((r['id'] for r in inherited_audits), default=0)])
    check('Controls preserve every inherited intent and audit byte', retained == inherited and retained_audits == inherited_audits)
    check('Controls preserve original owner account and authority', db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    (qa / 'force-ssl-controls-history-preservation.json').write_text(json.dumps({'inherited_intents': len(inherited), 'inherited_audits': len(inherited_audits), 'intents_sha256': hashlib.sha256(json.dumps(inherited, sort_keys=True).encode()).hexdigest(), 'audits_sha256': hashlib.sha256(json.dumps(inherited_audits, sort_keys=True).encode()).hexdigest(), 'retained_intents_sha256': hashlib.sha256(json.dumps(retained, sort_keys=True).encode()).hexdigest(), 'retained_audits_sha256': hashlib.sha256(json.dumps(retained_audits, sort_keys=True).encode()).hexdigest(), 'dedicated_actor': force_ssl_owner['id'], 'dedicated_reads': len(rows)}, indent=2))
    owner.close(); member.close(); client.close()
