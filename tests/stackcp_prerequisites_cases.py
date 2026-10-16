"""Packaged owner-only StackCP setup; no provider authentication/session request."""
import hashlib
import json
import time
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    base, qa, check, provider = (env[k] for k in ('BASE', 'QA', 'check', 'provider_server'))
    soup, token, submit = (env[k] for k in ('soup', 'token', 'submit'))
    admin = base + '/existing/admin/index.php'; portal = base + '/existing/portal/index.php'
    url = admin + '?view=provider-settings'; staff_url = admin + '?view=staff'
    secret = 'SYNTHETIC_STACKCP_AUTH_CLIENT_KEY'; identity = 'stack-user:24001'; package = 2401
    db('DELETE FROM ci_rate_limits')
    inherited = {name: db('SELECT * FROM ci_' + name + ' ORDER BY ' + ('seq' if name == 'provider_refreshes' else 'id'))
                 for name in ('provider_refreshes', 'provider_refresh_results', 'provider_package_links', 'provider_package_link_events', 'audit')}
    # Later root integration also contains directory controls. Preserve them when present,
    # and preserve genuine absence on this earlier feature base; never prepare a sibling.
    optional_history = {}
    for name in ('provider_operation_grants', 'provider_operations', 'provider_directory_changes',
                 'provider_directory_dispatch_ends', 'provider_directory_change_checks',
                 'provider_directory_indexing_reads', 'provider_directory_indexing_observations',
                 'provider_directory_indexing_permissions'):
        exists = bool(db('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', ['ci_' + name]))
        optional_history[name] = db('SELECT * FROM ci_' + name + ' ORDER BY id') if exists else None
    original = db('SELECT * FROM ci_users WHERE email=?', ['owner@example.invalid'])[0]
    original_staff = db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original['id']])[0]
    setup = requests.Session(); login = submit(setup, admin + '?view=login', 'login', {'email': original['email'], 'password': OWNER})
    baseline = db('SELECT * FROM ci_users WHERE id=?', [original['id']])[0]
    check('StackCP setup login changes only expected login metadata', login.status_code == 200 and urlparse(login.url).query == 'view=dashboard'
          and {k: v for k, v in baseline.items() if k != 'last_login'} == {k: v for k, v in original.items() if k != 'last_login'}
          and baseline['last_login'] is not None and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original['id']]) == [original_staff])
    email = 'stackcp-setup-owner@example.invalid'
    check('StackCP dedicated owner and package start absent', not db('SELECT id FROM ci_users WHERE email=?', [email]) and not db('SELECT id FROM ci_provider_package_links WHERE package_id=?', [package]))
    submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'StackCP setup owner', 'email': email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
    actor = db('SELECT id FROM ci_users WHERE email=?', [email])[0]['id']
    invite = chosen_form(setup, staff_url, 'staff_invite', 'id', actor, {'owner_password': OWNER})
    invitation = soup(invite).select_one('[data-staff-invite]')['href']; code = parse_qs(urlparse(invitation).fragment)['token'][0]
    activation = requests.Session(); form = activation.get(invitation)
    activated = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(form), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
    check('StackCP dedicated owner is activated without changing existing authority', activated.status_code == 200 and urlparse(activated.url).query == 'view=login'
          and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [actor]) == [{'role': 'owner', 'enabled': 1}]
          and db('SELECT * FROM ci_users WHERE id=?', [original['id']]) == [baseline] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original['id']]) == [original_staff])
    setup.close(); activation.close()
    owner = requests.Session(); submit(owner, admin + '?view=login', 'login', {'email': email, 'password': OWNER})
    member = requests.Session(); submit(member, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    client = requests.Session(); submit(client, portal, 'login', {'email': 'alice@example.invalid', 'password': 'Synthetic client passphrase 2026'})
    profile = db('SELECT * FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
    client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']
    diagnostics = []

    def evidence(label, response, expected_error=None):
        errors = [n.get_text(' ', strip=True) for n in soup(response).select('.notice.error')]
        item = {'step': label, 'status': response.status_code, 'redirect_statuses': [r.status_code for r in response.history],
                'expected_error_present': expected_error is None or any(expected_error in n for n in errors),
                'sensitive_values_absent': secret not in response.text and OWNER not in response.text,
                'provider_calls': provider.calls}
        diagnostics.append(item); (qa / 'stackcp-prerequisites-response-diagnostics.json').write_text(json.dumps(diagnostics, indent=2))
        check('StackCP ' + label + ' returns safe expected response', response.status_code == 200 and item['expected_error_present'] and item['sensitive_values_absent'])

    def post(action, **fields):
        response = owner.get(url); candidates = [f for f in soup(response).select('form') if f.select_one('input[name=action][value="' + action + '"]')]
        chosen = next((f for f in candidates if not f.select_one('input[name=package_id]') or f.select_one('input[name=package_id]').get('value') == str(package)), None)
        check('StackCP real form exists for ' + action, chosen is not None)
        data = {n['name']: n.get('value', '') for n in chosen.select('input[name]')}
        data.update({'current_password': OWNER, **fields})
        if 'review_sha256[]' in fields:
            data.pop('review_sha256', None)
        return owner.post(url, data=data)

    prefix = 'provider_inventory_stackcp_'; calls = provider.calls
    before = owner.get(url)
    check('StackCP GET offers explicit preparation without creating tables or sessions', before.status_code == 200 and prefix + 'auth_prepare' in before.text
          and not db('SHOW TABLES LIKE "ci_provider_stackcp_auth"') and provider.calls == calls)
    check('StackCP administrator and support context are refused', member.get(url).status_code == 403 and owner.get(url + '&support=fixture').status_code == 403)
    for label, fields, reason in [('CSRF', {'csrf': 'invalid'}, 'Your session expired. Refresh the page and try again.'), ('password', {'current_password': 'wrong'}, 'current password')]:
        response = post(prefix + 'auth_prepare', **fields); evidence('prepare invalid ' + label, response, reason)
        check('StackCP invalid preparation writes no family or provider request ' + label, not db('SHOW TABLES LIKE "ci_provider_stackcp_auth"') and provider.calls == calls)
    response = post(prefix + 'auth_prepare'); evidence('local authentication preparation', response)
    check('StackCP preparation creates an empty auth family only', db('SHOW TABLES LIKE "ci_provider_stackcp_auth"') and not db('SELECT * FROM ci_provider_stackcp_auth') and not db('SHOW TABLES LIKE "ci_provider_stackcp_assignments"') and provider.calls == calls)
    response = post(prefix + 'auth_save', auth_client_key=secret); evidence('store separate key', response)
    auth_rows = db('SELECT * FROM ci_provider_stackcp_auth ORDER BY ordinal')
    check('StackCP key is encrypted and never changes the General profile', len(auth_rows) == 1 and secret not in json.dumps(auth_rows) and db('SELECT * FROM ci_provider_profiles WHERE id=?', [profile['id']]) == [profile] and provider.calls == calls)
    for label, fields, reason in [('array review', {'review_sha256[]': 'bad'}, 'length and format'), ('whitespace key', {'auth_client_key': ' '}, 'without whitespace')]:
        response = post(prefix + 'auth_save', **fields); evidence('invalid ' + label, response, reason)
        check('StackCP invalid key form retains exact history ' + label, db('SELECT * FROM ci_provider_stackcp_auth ORDER BY ordinal') == auth_rows and provider.calls == calls)
    response = post(prefix + 'assignment_prepare'); evidence('local assignment preparation', response)
    check('StackCP assignment preparation does not create drafts', db('SHOW TABLES LIKE "ci_provider_stackcp_assignments"') and not db('SELECT * FROM ci_provider_stackcp_assignments') and provider.calls == calls)
    provider.mode = 'success'; provider.packages += [{'id': package, 'typeRef': 12, 'name': 'stackcp-setup.synthetic.invalid', 'names': ['stackcp-setup.synthetic.invalid'], 'enabled': True, 'created': '2000-01-01T00:00:00Z'}]
    latest = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes WHERE profile_id=?', [profile['id']])[0]['epoch']; time.sleep(max(0, int(latest or 0) + 31 - time.time()))
    page = owner.get(url); response = owner.post(url, data={'action': 'provider_inventory_refresh', 'csrf': token(page), 'profile_id': profile['id'], 'revision': str(profile['revision']), 'current_password': OWNER})
    check('StackCP fixture makes one ordinary hosting-list read only', response.status_code == 200 and provider.calls == calls + 1 and provider.get_paths[-1] == '/package')
    page = owner.get(url); response = owner.post(url, data={'action': 'provider_inventory_link', 'csrf': token(page), 'profile_id': profile['id'], 'revision': str(profile['revision']), 'package_id': str(package), 'client_id': str(client_id), 'current_password': OWNER})
    links = db('SELECT * FROM ci_provider_package_links WHERE profile_id=? ORDER BY package_id', [profile['id']]); link = next(r for r in links if r['package_id'] == package)
    page_number = [r['package_id'] for r in links].index(package) // 20 + 1; url += '&stackcp_page=' + str(page_number); calls = provider.calls
    response = post(prefix + 'assignment_save', stack_identity=identity, acknowledged='1'); evidence('save unverified local draft', response)
    draft_rows = db('SELECT * FROM ci_provider_stackcp_assignments ORDER BY ordinal')
    check('StackCP local draft binds exact client package and auth without provider verification', len(draft_rows) == 1 and json.loads(draft_rows[0]['document_json'])['state'] == 'unverified'
          and json.loads(draft_rows[0]['document_json'])['link_id'] == link['id'] and json.loads(draft_rows[0]['document_json'])['client_id'] == client_id and provider.calls == calls)
    check('StackCP client portal receives no authentication key or Stack-user draft', identity not in client.get(portal).text and secret not in client.get(portal).text)

    def capture(title, selector):
        context = browser.new_context(); context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in owner.cookies]); page = context.new_page(); errors = []
        page.on('pageerror', lambda e: errors.append(str(e)))
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.goto(url); page.evaluate('document.fonts.ready'); target = page.locator(selector); expect(target).to_be_visible()
            target.screenshot(path=str(qa / (title + '-' + label + '.png')))
            check(title + ' ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            check(title + ' ' + label + ' action text fits', target.locator('button').evaluate_all('''buttons => buttons.length > 0 && buttons.every(b => { const r=b.getBoundingClientRect(), f=b.closest('form').getBoundingClientRect(), t=document.createRange();t.selectNodeContents(b);return r.left>=f.left-1&&r.right<=f.right+1&&b.scrollWidth<=b.clientWidth+1&&[...t.getClientRects()].every(x=>x.left>=r.left-1&&x.right<=r.right+1&&x.top>=r.top-1&&x.bottom<=r.bottom+1);})'''))
        check(title + ' has no script errors', not errors); context.close()

    own = '[data-stackcp-assignment]:has(h3:text-is("Package 2401"))'
    capture('StackCP-Setup-Key', '[data-stackcp-auth]'); capture('StackCP-Setup-Unverified', own)
    captured = next(f for f in soup(owner.get(url)).select('[data-stackcp-assignment-save]') if f.select_one('input[name=package_id]')['value'] == str(package))
    before_form = captured.select_one('input[name=review_sha256]')['value']
    response = post(prefix + 'auth_disable'); evidence('disable local key', response)
    # Disabled setup deliberately removes the save form; submit the captured old form directly to verify the server fence.
    page = owner.get(url); response = owner.post(url, data={'action': prefix + 'assignment_save', 'csrf': token(page), 'profile_id': profile['id'], 'package_id': str(package), 'link_id': link['id'], 'review_sha256': before_form, 'stack_identity': identity, 'acknowledged': '1', 'current_password': OWNER})
    evidence('stale captured draft refusal', response, 'authority changed')
    check('StackCP disabled configuration leaves draft historical and performs no request', db('SELECT * FROM ci_provider_stackcp_assignments ORDER BY ordinal') == draft_rows and provider.calls == calls and 'Local assignment needs owner review' in response.text)
    capture('StackCP-Setup-Stale', own)
    response = post(prefix + 'assignment_revoke'); evidence('revoke local draft', response)
    check('StackCP explicit revocation is local and cannot claim provider session revocation', json.loads(db('SELECT document_json FROM ci_provider_stackcp_assignments ORDER BY ordinal DESC LIMIT 1')[0]['document_json'])['state'] == 'revoked' and provider.calls == calls and 'does not end a provider session' in response.text)
    capture('StackCP-Setup-Revoked', own)
    retained = {}
    for name, rows in inherited.items():
        current = {r['id']: r for r in db('SELECT * FROM ci_' + name)}; retained[name] = all(current.get(r['id']) == r for r in rows)
    for name, rows in optional_history.items():
        exists = bool(db('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', ['ci_' + name]))
        retained[name] = not exists if rows is None else exists and db('SELECT * FROM ci_' + name + ' ORDER BY id') == rows
    reads = db('SELECT operation FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [actor])
    check('StackCP fixture preserves inherited history and spends only one package read', all(retained.values()) and reads == [{'operation': 'packages.list'}]
          and db('SELECT * FROM ci_provider_profiles WHERE id=?', [profile['id']]) == [profile]
          and db('SELECT * FROM ci_users WHERE id=?', [original['id']]) == [baseline] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original['id']]) == [original_staff])
    (qa / 'stackcp-prerequisites-history-preservation.json').write_text(json.dumps({'actor_id': actor, 'retained': retained, 'inherited_sha256': {k: hashlib.sha256(json.dumps(v, sort_keys=True, separators=(',', ':')).encode()).hexdigest() for k, v in (inherited | optional_history).items()}, 'reads': reads}, indent=2))
    owner.close(); member.close(); client.close()
