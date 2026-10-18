"""Actual owner StackCP observations through synthetic fixed HTTPS; never a login."""
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
    package, identity = 2501, 'stack-user:25001'
    secret = 'SYNTHETIC_STACKCP_ACCESS_AUTH_KEY'; prefix = 'provider_inventory_stackcp_access_'
    db('DELETE FROM ci_rate_limits')
    families = ('provider_refreshes', 'provider_refresh_results', 'provider_package_links', 'provider_package_link_events',
                'provider_stackcp_auth', 'provider_stackcp_assignments', 'provider_operation_grants', 'provider_operations',
                'provider_directory_changes', 'provider_directory_dispatch_ends', 'provider_directory_change_checks',
                'provider_directory_indexing_reads', 'provider_directory_indexing_observations', 'provider_directory_indexing_permissions',
                'provider_stackcp_reads', 'provider_stackcp_observations', 'audit')
    inherited = {}
    for name in families:
        exists = bool(db('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', ['ci_' + name]))
        inherited[name] = db('SELECT * FROM ci_' + name + ' ORDER BY id') if exists else None
    original = db('SELECT * FROM ci_users WHERE email=?', ['owner@example.invalid'])[0]
    staff = db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original['id']])[0]
    setup = requests.Session(); login = submit(setup, admin + '?view=login', 'login', {'email': original['email'], 'password': OWNER})
    baseline = db('SELECT * FROM ci_users WHERE id=?', [original['id']])[0]
    check('StackCP access setup login preserves authority', login.status_code == 200 and urlparse(login.url).query == 'view=dashboard'
          and {k: v for k, v in baseline.items() if k != 'last_login'} == {k: v for k, v in original.items() if k != 'last_login'}
          and baseline['last_login'] is not None and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original['id']]) == [staff])
    email = 'stackcp-access-owner@example.invalid'
    check('StackCP access dedicated owner and package are new', not db('SELECT id FROM ci_users WHERE email=?', [email]) and not db('SELECT id FROM ci_provider_package_links WHERE package_id=?', [package]))
    response = submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'StackCP access owner', 'email': email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
    actors = db('SELECT id FROM ci_users WHERE email=?', [email]); check('StackCP access owner creation succeeds', response.status_code == 200 and len(actors) == 1)
    actor = actors[0]['id']; invite = chosen_form(setup, staff_url, 'staff_invite', 'id', actor, {'owner_password': OWNER})
    invitation = soup(invite).select_one('[data-staff-invite]')['href']; code = parse_qs(urlparse(invitation).fragment)['token'][0]
    activation = requests.Session(); form = activation.get(invitation)
    activated = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(form), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
    check('StackCP access real owner activation succeeds', activated.status_code == 200 and urlparse(activated.url).query == 'view=login' and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [actor]) == [{'role': 'owner', 'enabled': 1}])
    setup.close(); activation.close(); owner = requests.Session(); login = submit(owner, admin + '?view=login', 'login', {'email': email, 'password': OWNER})
    check('StackCP access dedicated owner logs in', login.status_code == 200 and urlparse(login.url).query == 'view=dashboard')
    member = requests.Session(); submit(member, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    client = requests.Session(); submit(client, portal, 'login', {'email': 'alice@example.invalid', 'password': 'Synthetic client passphrase 2026'})
    profile = db('SELECT * FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
    client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']
    start_calls, start_posts = provider.calls, list(provider.posts); diagnostics = []

    def evidence(label, response, refusal=None):
        errors = [n.get_text(' ', strip=True) for n in soup(response).select('.notice.error')]
        item = {'step': label, 'status': response.status_code, 'redirect_statuses': [r.status_code for r in response.history],
                'expected_refusal': refusal is None or any(refusal in e for e in errors),
                'private_values_absent': secret not in response.text and OWNER not in response.text, 'provider_calls': provider.calls}
        diagnostics.append(item); (qa / 'stackcp-access-response-diagnostics.json').write_text(json.dumps(diagnostics, indent=2))
        check('StackCP access ' + label + ' has expected safe response', response.status_code == 200 and item['expected_refusal'] and item['private_values_absent'])

    def form_data(action):
        response = owner.get(url); forms = [f for f in soup(response).select('form') if f.select_one('input[name=action][value="' + action + '"]')]
        chosen = next((f for f in forms if not f.select_one('input[name=package_id]') or f.select_one('input[name=package_id]').get('value') == str(package)), None)
        check('StackCP access real form exists ' + action, response.status_code == 200 and chosen is not None)
        return {n['name']: n.get('value', '') for n in chosen.select('input[name]')}

    def post(action, **fields):
        data = form_data(action); data.update({'current_password': OWNER, **fields})
        if 'review_sha256[]' in fields: data.pop('review_sha256', None)
        return owner.post(url, data=data)

    def cool():
        latest = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes WHERE profile_id=?', [profile['id']])[0]['epoch']
        time.sleep(max(0, int(latest or 0) + 31 - time.time()))

    check('StackCP access follows explicit prior prerequisite setup', inherited['provider_stackcp_auth'] is not None and inherited['provider_stackcp_assignments'] is not None)
    response = post('provider_inventory_stackcp_auth_save', auth_client_key=secret); evidence('fresh separate local key', response)
    provider.mode = 'success'; provider.packages += [{'id': package, 'typeRef': 12, 'name': 'stackcp-access.synthetic.invalid', 'names': ['stackcp-access.synthetic.invalid'], 'enabled': True, 'created': '2000-01-01T00:00:00Z'}]
    cool(); response = post('provider_inventory_refresh'); evidence('initial hosting source', response)
    page = owner.get(url); response = owner.post(url, data={'action': 'provider_inventory_link', 'csrf': token(page), 'profile_id': profile['id'], 'revision': str(profile['revision']), 'package_id': str(package), 'client_id': str(client_id), 'current_password': OWNER})
    evidence('explicit hosting assignment', response); links = db('SELECT * FROM ci_provider_package_links WHERE profile_id=? ORDER BY package_id', [profile['id']])
    own_links = [r for r in links if r['package_id'] == package]; check('StackCP access selected package links to exact client', len(own_links) == 1 and own_links[0]['client_id'] == client_id)
    page_number = [r['package_id'] for r in links].index(package) // 20 + 1
    url += '&stackcp_page=' + str(page_number) + '&stackcp_access_page=' + str(page_number)
    response = post('provider_inventory_stackcp_assignment_save', stack_identity=identity, acknowledged='1'); evidence('unverified assignment', response)
    own = '[data-stackcp-access-package="' + str(package) + '"]'
    before = owner.get(url)
    check('StackCP access GET does not silently prepare or call provider', before.status_code == 200 and not db('SHOW TABLES LIKE "ci_provider_stackcp_reads"') and provider.calls == start_calls + 1)
    check('StackCP access excludes delegated and support identities', member.get(url).status_code == 403 and owner.get(url + '&support=fixture').status_code == 403)
    response = post(prefix + 'prepare'); evidence('explicit observation preparation', response)
    cool(); response = post('provider_inventory_refresh'); evidence('hosting source after link', response)
    for label, fields, refusal in [('CSRF', {'csrf': 'invalid'}, 'Your session expired'), ('password', {'current_password': 'wrong'}, 'current password'), ('confirmation', {'confirmed': '0'}, 'Confirm this explicit access observation'), ('array review', {'review_sha256[]': 'bad'}, 'length and format')]:
        response = post(prefix + 'membership', confirmed='1', **fields) if 'confirmed' not in fields else post(prefix + 'membership', **fields)
        evidence('invalid ' + label, response, refusal)
        check('StackCP access invalid form creates no read ' + label, not db('SELECT id FROM ci_provider_stackcp_reads') and provider.calls == start_calls + 2)

    def capture(title):
        context = browser.new_context(); context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in owner.cookies]); page = context.new_page(); errors = []
        page.on('pageerror', lambda e: errors.append(str(e)))
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.goto(url); page.evaluate('document.fonts.ready'); target = page.locator(own); expect(target).to_be_visible(); target.screenshot(path=str(qa / (title + '-' + label + '.png')))
            check(title + ' ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            check(title + ' ' + label + ' action text fits', target.locator('button').evaluate_all('''buttons => buttons.length > 0 && buttons.every(b => {const r=b.getBoundingClientRect(),f=b.closest('form').getBoundingClientRect(),t=document.createRange();t.selectNodeContents(b);return r.left>=f.left-1&&r.right<=f.right+1&&b.scrollWidth<=b.clientWidth+1&&[...t.getClientRects()].every(x=>x.left>=r.left-1&&x.right<=r.right+1&&x.top>=r.top-1&&x.bottom<=r.bottom+1);})'''))
        check(title + ' has no script errors', not errors); context.close()

    provider.stackcp_membership = [{'identity': 'stack-user:99999', 'email': 'discarded-stackcp@example.invalid'}, {'identity': identity}]
    provider.stackcp_grants = ['package:' + str(package)]
    cool(); response = post(prefix + 'membership', confirmed='1'); evidence('saved selected membership', response)
    check('StackCP access membership uses exact package GET and discards metadata', provider.calls == start_calls + 3 and provider.get_paths[-1] == '/package/2501/stackUserList' and 'discarded-stackcp@example.invalid' not in json.dumps(db('SELECT * FROM ci_provider_stackcp_observations')))
    cool(); response = post(prefix + 'grants', confirmed='1'); evidence('saved whole-user grants', response)
    current = owner.get(url); section = soup(current).select_one(own)
    check('StackCP access match is saved observation without login', current.status_code == 200 and 'Assignment matched two saved observations.' in section.text and provider.get_paths[-1] == '/reseller/*/stackUser/25001/grants' and not section.select('a') and provider.posts == start_posts)
    capture('StackCP-Access-Matched'); captured = form_data(prefix + 'membership')
    provider.stackcp_grants = None; cool(); response = post(prefix + 'grants', confirmed='1'); evidence('unavailable latest grants', response)
    section = soup(owner.get(url)).select_one(own); check('StackCP access failed latest grants hides prior match', 'The latest read is unavailable' in section.text and 'Assignment matched two saved observations.' not in section.text)
    capture('StackCP-Access-Unavailable')
    provider.stackcp_membership = []; cool(); response = post(prefix + 'membership', confirmed='1'); evidence('explicit empty membership', response)
    section = soup(owner.get(url)).select_one(own); check('StackCP access empty is saved without implied permission or grant form', 'An empty response was saved; access is not verified' in section.text and not section.select('input[name=action][value="' + prefix + 'grants"]'))
    capture('StackCP-Access-Empty'); calls = provider.calls
    response = post('provider_inventory_stackcp_auth_disable'); evidence('local auth disable', response)
    page = owner.get(url); captured.update({'csrf': token(page), 'current_password': OWNER, 'confirmed': '1'})
    response = owner.post(url, data=captured); evidence('captured current authority refusal', response, 'Review a current protected StackCP assignment first.')
    current = owner.get(url); section = soup(current).select_one(own)
    check('StackCP access local revocation disables forms without remote session claim', current.status_code == 200 and 'A current local assignment' in section.text and not section.select('form') and provider.calls == calls)
    client_page = client.get(portal); check('StackCP access private identity keys and observations stay out of client portal', client_page.status_code == 200 and identity not in client_page.text and secret not in client_page.text and 'stackcp_access' not in client_page.text)
    retained = {}
    for name, rows in inherited.items():
        exists = bool(db('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', ['ci_' + name]))
        if rows is None: retained[name] = exists if name in ('provider_stackcp_reads', 'provider_stackcp_observations') else not exists
        else:
            current_rows = {r['id']: r for r in db('SELECT * FROM ci_' + name)} if exists else {}; retained[name] = all(current_rows.get(r['id']) == r for r in rows)
    reads = db('SELECT operation FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [actor])
    outcomes = db('SELECT z.state FROM ci_provider_refreshes f JOIN ci_provider_refresh_results z ON z.id=f.id WHERE f.actor_id=? ORDER BY f.seq', [actor])
    check('StackCP access uses six own reads with exact terminal sequence and no writes', reads == [{'operation': v} for v in ['packages.list', 'packages.list', 'stackcp.membership', 'stackcp.grants', 'stackcp.grants', 'stackcp.membership']] and outcomes == [{'state': v} for v in ['complete', 'complete', 'complete', 'complete', 'unavailable', 'complete']] and provider.calls == start_calls + 6 and provider.posts == start_posts and not provider.errors)
    check('StackCP access preserves inherited rows original actor and profile', all(retained.values()) and db('SELECT * FROM ci_provider_profiles WHERE id=?', [profile['id']]) == [profile] and db('SELECT * FROM ci_users WHERE id=?', [original['id']]) == [baseline] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original['id']]) == [staff])
    (qa / 'stackcp-access-history-preservation.json').write_text(json.dumps({'actor_id': actor, 'retained': retained, 'inherited_sha256': {k: hashlib.sha256(json.dumps(v, sort_keys=True, separators=(',', ':')).encode()).hexdigest() for k, v in inherited.items()}, 'reads': reads, 'outcomes': outcomes}, indent=2))
    owner.close(); member.close(); client.close()
