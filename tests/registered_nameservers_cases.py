"""Packaged owner observation forms; only the existing synthetic loopback TLS provider.

Run last: preceding workflows may deliberately age their own provider fixtures.
No timestamp/rate reset, registrar write, live provider or native acceptance claim.
"""
import hashlib
import json
import re
import time
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER, STAFF


CAPTURES = tuple('Registered-Nameservers-Owner-'+state+'-'+size+'.png'
                 for state in ('Setup', 'Observed', 'Failed', 'Unfinished')
                 for size in ('Desktop', 'Mobile'))
PRIVATE_PATHS = (
    'app/providers/registered_nameservers.php',
    'app/providers/registered_nameservers_storage.php',
    'app/providers/registered_nameservers_views.php',
    'admin/controllers/registered_nameservers.php',
    'admin/views/registered_nameservers.php',
    'database/schema/registered_nameservers_schema.php',
    'includes/foundation/twentyi-registrar-nameservers/RegisteredNameservers.php',
    'includes/foundation/twentyi-registrar-nameservers/RegisteredNameserverBinding.php',
    'includes/foundation/twentyi-registrar-nameservers/RegisteredNameserverObservation.php',
)
NS = ['ns1.nameservers.invalid', 'ns2.nameservers.invalid']
PASSWORD = 'Synthetic nameserver HTTP owner 2026'
EMAIL = 'nameservers-owner@example.invalid'
OPERATION = 'registrar.nameservers.read'


def cooldown_seconds(latest, now):
    """Bound an honest wait; never repair/age a surprising fixture clock."""
    if type(latest) is not int or type(now) is not int or latest < 0 or now < 0:
        raise ValueError('Invalid nameserver fixture clock')
    remaining = max(0, latest + 31 - now)
    if remaining > 31:
        raise ValueError('Nameserver fixture clock is in the future')
    return remaining


def run_browser(env, browser):
    check, soup, token, form_data, submit = (env[k] for k in ('check', 'soup', 'token', 'form_data', 'submit'))
    base, qa, provider = env['BASE'], env['QA'], env['provider_server']
    admin = base+'/existing/admin/index.php'
    portal = base+'/existing/portal/index.php'
    settings = admin+'?view=provider-settings'
    prepare, refresh = 'provider_inventory_nameservers_prepare', 'provider_inventory_nameservers_refresh'
    sessions, contexts, errors = [], [], []
    original = (list(provider.registrar_domains), provider.registrar_mode,
                dict(provider.nameservers), provider.nameserver_mode)
    calls, posts = provider.calls, list(provider.posts)
    inherited = db('SELECT id,started_at,started_epoch FROM ci_provider_refreshes ORDER BY seq')
    ledger = db('SELECT * FROM ci_provider_operations ORDER BY id')
    trigger_created = table_created = False

    def session():
        value = requests.Session(); sessions.append(value); return value

    def current_page(owner):
        response = owner.get(settings, timeout=15)
        if response.status_code != 200:
            raise AssertionError('Nameserver owner settings were unavailable')
        return response

    def state():
        names = ('registered_nameserver_bindings', 'registered_nameserver_sets')
        tables = {name: db('SELECT * FROM ci_'+name+' ORDER BY id')
                  if db('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?', ['ci_'+name]) else None
                  for name in names}
        tables['intents'] = db('SELECT * FROM ci_provider_refreshes ORDER BY seq')
        tables['results'] = db('SELECT * FROM ci_provider_refresh_results ORDER BY id')
        tables['settings'] = db('SELECT * FROM ci_settings WHERE BINARY LEFT(name,?)=BINARY ? ORDER BY name', [len('registered_nameservers_'), 'registered_nameservers_'])
        tables['audits'] = db('SELECT * FROM ci_audit WHERE BINARY LEFT(action,?)=BINARY ? ORDER BY id', [len('registered_nameservers.'), 'registered_nameservers.'])
        return hashlib.sha256(json.dumps(tables, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

    def wait_read(profile_id):
        row = db('SELECT MAX(started_epoch) AS latest FROM ci_provider_refreshes WHERE profile_id=?', [profile_id])[0]
        remaining = cooldown_seconds(int(row['latest'] or 0), int(time.time()))
        if remaining:
            time.sleep(remaining)

    def scoped(response, link_id=None):
        item = soup(response).select_one('#nameserver-'+link_id if link_id else '#registered-nameservers')
        if item is None:
            raise AssertionError('Nameserver section missing')
        return item

    def fields(owner, link_id):
        item = scoped(current_page(owner), link_id)
        form = item.select_one('form[data-nameserver-refresh]')
        if form is None:
            raise AssertionError('Nameserver refresh form missing')
        return form_data(form)

    def capture(owner, title, selector):
        context = browser.new_context(); contexts.append(context)
        context.add_cookies([{'name': c.name, 'value': c.value, 'url': base} for c in owner.cookies])
        page = context.new_page(); page.on('pageerror', lambda error: errors.append(type(error).__name__))
        for width, height, size in ((1440, 1000, 'Desktop'), (320, 740, 'Mobile')):
            page.set_viewport_size({'width': width, 'height': height}); page.goto(settings)
            page.evaluate('document.fonts.ready')
            if size == 'Mobile':
                expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
            item = page.locator(selector); expect(item).to_be_visible()
            item.locator('details[data-nameserver-history]').evaluate_all('(nodes)=>nodes.forEach(n=>n.open=true)')
            item.scroll_into_view_if_needed()
            page.evaluate('new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))')
            check('Registered nameservers '+title+' '+size+' fits without script errors',
                  not errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
            item.screenshot(path=str(qa/('Registered-Nameservers-Owner-'+title+'-'+size+'.png')), animations='disabled')
        context.close(); contexts.remove(context)

    try:
        check('Registered nameserver captures refuse prior outputs', all(not (qa/name).exists() and not (qa/name).is_symlink() for name in CAPTURES))
        check('Registered nameservers begin unprepared with no prior read',
              not db('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(table_name,?)=BINARY ?', [len('ci_registered_nameserver_'), 'ci_registered_nameserver_'])
              and not db('SELECT id FROM ci_provider_refreshes WHERE BINARY operation=?', [OPERATION]))
        # Account creation/activation uses production forms. No role/password rows are seeded.
        bootstrap = session()
        submit(bootstrap, admin+'?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
        check('Registered nameserver owner fixture refuses an existing account', not db('SELECT id FROM ci_users WHERE email=?', [EMAIL]))
        chosen_form(bootstrap, admin+'?view=staff', 'staff_save', 'id', 0,
                    {'name': 'Nameserver HTTP owner', 'email': EMAIL, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
        user = db('SELECT u.id,u.password_hash,p.role,p.enabled FROM ci_users u JOIN ci_staff_profiles p ON p.user_id=u.id WHERE u.email=?', [EMAIL])
        check('Registered nameserver isolated owner created through account form', len(user) == 1 and user[0]['role'] == 'owner' and user[0]['enabled'] == 1 and user[0]['password_hash'] == '')
        invitation = chosen_form(bootstrap, admin+'?view=staff', 'staff_invite', 'id', user[0]['id'], {'owner_password': OWNER})
        link_node = soup(invitation).select_one('[data-staff-invite]')
        if link_node is None:
            raise AssertionError('Nameserver owner invitation missing')
        setup = link_node['href']; code = parse_qs(urlparse(setup).fragment).get('token', [''])[0]
        check('Registered nameserver owner invitation has a private fragment', bool(re.fullmatch('[a-f0-9]{64}', code)) and urlparse(setup).query == 'view=staff-setup')
        owner = session()
        submit(owner, admin+'?view=staff-setup', 'staff_activate', {'token': code, 'password': PASSWORD, 'confirm_password': PASSWORD})
        submit(owner, admin+'?view=login', 'login', {'email': EMAIL, 'password': PASSWORD})
        check('Registered nameserver owner authenticates with a fresh provider quota',
              bool(scoped(current_page(owner)).select_one('form[data-nameserver-prepare]'))
              and not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?', [user[0]['id']]))
        before = state(); response = current_page(owner)
        check('Registered nameservers GET creates no storage or provider request', state() == before and provider.calls == calls and prepare in response.text)
        capture(owner, 'Setup', '#registered-nameservers')
        for changed, label in (({'csrf': 'bad', 'current_password': PASSWORD}, 'CSRF'), ({'current_password': 'wrong'}, 'password')):
            response = submit(owner, settings, prepare, changed)
            check('Registered nameserver preparation rejects '+label, state() == before and provider.calls == calls and bool(scoped(response).select_one('form[data-nameserver-prepare]')))
        context = browser.new_context(); contexts.append(context)
        context.add_cookies([{'name': c.name, 'value': c.value, 'url': base} for c in owner.cookies])
        page = context.new_page(); page.on('pageerror', lambda error: errors.append(type(error).__name__)); page.goto(settings)
        form = page.locator('form[data-nameserver-prepare]'); form.locator('[name=current_password]').fill(PASSWORD)
        with page.expect_navigation(wait_until='domcontentloaded'):
            form.get_by_role('button', name='Enable nameserver observations', exact=True).click()
        expect(page.locator('#registered-nameservers')).not_to_contain_text('Enable nameserver observations')
        context.close(); contexts.remove(context)
        check('Chromium prepares empty nameserver storage without a provider read', provider.calls == calls and not db('SELECT * FROM ci_registered_nameserver_bindings') and not db('SELECT * FROM ci_registered_nameserver_sets') and db('SELECT value FROM ci_settings WHERE name=?', ['registered_nameservers_schema_version']) == [{'value': '1'}])
        # Discover a new numeric registrar domain through the ordinary real HTTP refresh.
        profile = db('SELECT * FROM ci_provider_profiles')[0]
        client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['registrar-client1@example.invalid'])[0]['id']
        check('Registered nameserver synthetic numeric identity is unused', not db('SELECT id FROM ci_provider_registrar_links WHERE domain_id=?', [14701]) and all(str(row.get('id')) != '14701' for row in original[0]))
        provider.registrar_mode = 'success'
        provider.registrar_domains = original[0]+[{'id': 14701, 'name': 'nameservers-client.invalid'}]
        provider.nameservers = {'14701': list(NS)}; provider.nameserver_mode = 'success'
        wait_read(profile['id']); path_count = len(provider.get_paths)
        submit(owner, settings, 'provider_inventory_registrar_refresh', {'current_password': PASSWORD})
        source = db('SELECT f.id,r.state FROM ci_provider_refreshes f LEFT JOIN ci_provider_refresh_results r ON r.id=f.id WHERE f.profile_id=? AND BINARY f.operation=? ORDER BY f.seq DESC LIMIT 1', [profile['id'], 'domains.list'])
        check('Registered nameserver assignment uses a real complete registrar refresh', len(source) == 1 and source[0]['state'] == 'complete' and provider.get_paths[path_count:] == ['/domain'])
        chosen_form(owner, settings, 'provider_inventory_registrar_link', 'domain_id', 14701, {'client_id': client_id, 'current_password': PASSWORD})
        links = db('SELECT * FROM ci_provider_registrar_links WHERE domain_id=?', [14701])
        check('Registered nameserver assignment is made through the owner form', len(links) == 1 and links[0]['client_id'] == client_id)
        link = links[0]; selector = '#nameserver-'+link['id']; saved = fields(owner, link['id'])
        calls = provider.calls; before = state()
        for changed, label in (({'csrf': 'bad'}, 'CSRF'), ({'current_password': 'wrong'}, 'password'), ({'link_revision': '999'}, 'assignment revision'), ({'profile_revision': '999'}, 'profile revision'), ({'refresh_id': 'f'*32}, 'source identity')):
            response = owner.post(settings, data={**saved, 'current_password': PASSWORD, **changed}, timeout=25)
            check('Registered nameserver refresh rejects '+label, state() == before and provider.calls == calls and response.status_code == 200 and all(n.get('value', '') == '' for n in scoped(response, link['id']).select('input[type=password]')))
        wait_read(profile['id']); paths = len(provider.get_paths)
        context = browser.new_context(); contexts.append(context)
        context.add_cookies([{'name': c.name, 'value': c.value, 'url': base} for c in owner.cookies])
        page = context.new_page(); page.on('pageerror', lambda error: errors.append(type(error).__name__)); page.goto(settings); form = page.locator(selector+' form[data-nameserver-refresh]')
        form.locator('[name=current_password]').fill(PASSWORD)
        with page.expect_navigation(wait_until='domcontentloaded'):
            form.get_by_role('button', name='Refresh nameservers', exact=True).click()
        expect(page.locator(selector+' [data-nameserver-current]')).to_contain_text(NS[0])
        context.close(); contexts.remove(context)
        reads = db('SELECT f.id,f.actor_id,r.state FROM ci_provider_refreshes f LEFT JOIN ci_provider_refresh_results r ON r.id=f.id WHERE BINARY f.operation=? ORDER BY f.seq', [OPERATION])
        check('Chromium nameserver refresh saves one exact numeric GET and original owner', len(reads) == 1 and reads[0]['state'] == 'complete' and reads[0]['actor_id'] == user[0]['id'] and provider.get_paths[paths:] == ['/domain/14701/nameservers'] and provider.posts == posts)
        observed = db('SELECT * FROM ci_registered_nameserver_sets ORDER BY id')
        check('Registered nameserver saved set is the complete synthetic response', len(observed) == 1 and json.loads(observed[0]['nameservers_json']) == NS)
        response = current_page(owner); card = scoped(response, link['id'])
        check('Registered nameserver observation has dated history and no write controls',
              bool(card.select_one('[data-nameserver-current]')) and bool(card.select_one('details[data-nameserver-history]'))
              and 'Observed ' in card.get_text() and 'UTC' in card.get_text()
              and 'does not confirm propagation or permission to change' in card.get_text()
              and [n.get('value') for n in card.select('form [name=action]')] == [refresh]
              and not card.select('[name=ns],[name="old-ns"],[name=desired],[name=nameservers]'))
        check('Registered nameserver owner projection hides credentials and private binding', all(value not in str(card) for value in ('SYNTHETIC_RAW_GENERAL_KEY', 'binding_json', 'key_fingerprint', 'link_audit_sha256')) and response.headers.get('Referrer-Policy') == 'no-referrer')
        capture(owner, 'Observed', selector)

        # Every denial starts from the same authentic form and must preserve the saved state.
        member = session(); submit(member, admin+'?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
        client = session(); submit(client, portal, 'login', {'email': 'registrar-client1@example.invalid', 'password': OWNER})
        before = state(); calls = provider.calls
        response = member.get(admin+'?view=security', timeout=15)
        check('Registered nameserver ordinary staff is authenticated without owner role', response.status_code == 200 and bool(soup(response).select_one('[name=csrf]')) and member.get(settings, timeout=15).status_code == 403)
        denied = member.post(settings, data={**saved, 'csrf': token(response), 'current_password': STAFF}, timeout=25)
        check('Registered nameserver ordinary staff POST cannot read', denied.status_code == 403 and state() == before and provider.calls == calls)
        response = client.get(portal, timeout=15)
        denied = client.post(settings, data={**saved, 'csrf': token(response), 'current_password': OWNER}, allow_redirects=False, timeout=25)
        check('Registered nameserver primary client POST cannot read', denied.status_code == 303 and 'login' in denied.headers.get('Location', '') and state() == before and provider.calls == calls)
        check('Registered nameserver client query exposes no owner observation', not soup(client.get(portal+'?registered_nameservers=1&link_id='+link['id'], timeout=15)).select_one('[data-registered-nameservers]') and state() == before and provider.calls == calls)
        chosen_form(owner, admin+'?view=support', 'support_policy', 'client_id', client_id, {'allowed': '1', 'owner_password': PASSWORD})
        support = chosen_form(owner, admin+'?view=support', 'support_start', 'client_id', client_id, {})
        sid = parse_qs(urlparse(support.url).query).get('support', [''])[0]
        check('Registered nameserver support denial uses a real active support session', bool(re.fullmatch('[a-f0-9]{32}', sid)) and support.status_code == 200 and 'Support session' in support.text)
        denied = owner.post(settings+'&support='+sid, data={**saved, 'csrf': token(support), 'current_password': PASSWORD}, timeout=25)
        check('Registered nameserver support context cannot read provider data', denied.status_code == 403 and state() == before and provider.calls == calls)
        submit(owner, portal+'?support='+sid, 'support_end')
        chosen_form(owner, admin+'?view=support', 'support_policy', 'client_id', client_id, {'allowed': None, 'owner_password': PASSWORD})

        provider.nameserver_mode = 'authentication'; wait_read(profile['id']); paths = len(provider.get_paths)
        response = chosen_form(owner, settings, refresh, 'link_id', link['id'], {'current_password': PASSWORD})
        card = scoped(response, link['id'])
        check('Registered nameserver latest failed read masks current and preserves dated history', not card.select_one('[data-nameserver-current]') and 'Nameservers are unavailable from the latest refresh.' in card.get_text() and NS[0] in card.select_one('details[data-nameserver-history]').get_text() and 'private synthetic nameserver response' not in response.text and provider.get_paths[paths:] == ['/domain/14701/nameservers'] and db('SELECT * FROM ci_registered_nameserver_sets ORDER BY id') == observed)
        capture(owner, 'Failed', selector)
        # The next real GET succeeds, but actual result/audit persistence rolls back.
        # MyISAM is only a test witness across the intentional InnoDB rollback.
        check('Registered nameserver fault refuses preexisting fixture objects', not db('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?', ['ci_rns_http_fault']) and not db('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND BINARY trigger_name=?', ['ci_rns_http_fault']))
        db('CREATE TABLE ci_rns_http_fault (id INT PRIMARY KEY,read_id CHAR(32) NOT NULL,set_count INT NOT NULL) ENGINE=MyISAM'); table_created = True
        db("CREATE TRIGGER ci_rns_http_fault BEFORE INSERT ON ci_audit FOR EACH ROW BEGIN IF NEW.action='registered_nameservers.observed' THEN INSERT INTO ci_rns_http_fault VALUES (1,SUBSTRING(NEW.detail,6,32),(SELECT COUNT(*) FROM ci_registered_nameserver_sets)); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic nameserver HTTP audit refusal'; END IF; END"); trigger_created = True
        provider.nameserver_mode = 'success'; wait_read(profile['id']); paths = len(provider.get_paths)
        previous_reads = db('SELECT id FROM ci_registered_nameserver_bindings ORDER BY id')
        response = chosen_form(owner, settings, refresh, 'link_id', link['id'], {'current_password': PASSWORD})
        witness = db('SELECT * FROM ci_rns_http_fault')
        db('DROP TRIGGER ci_rns_http_fault'); trigger_created = False
        db('DROP TABLE ci_rns_http_fault'); table_created = False
        newest = db('SELECT f.id,r.state FROM ci_provider_refreshes f LEFT JOIN ci_provider_refresh_results r ON r.id=f.id WHERE BINARY f.operation=? ORDER BY f.seq DESC LIMIT 1', [OPERATION])
        check('Registered nameserver real post-GET audit fault leaves only durable intent', len(witness) == 1 and len(newest) == 1 and witness[0]['read_id'] == newest[0]['id'] and witness[0]['set_count'] == 2 and newest[0]['state'] is None and newest[0]['id'] not in {row['id'] for row in previous_reads} and provider.get_paths[paths:] == ['/domain/14701/nameservers'] and db('SELECT * FROM ci_registered_nameserver_sets ORDER BY id') == observed)
        check('Registered nameserver audit failure gives a safe owner error', response.status_code == 200 and 'The change could not be saved.' in response.text and 'Synthetic nameserver HTTP audit refusal' not in response.text and 'SQLSTATE' not in response.text)
        calls = provider.calls; card = scoped(current_page(owner), link['id'])
        check('Registered nameserver latest unfinished masks prior success without retry', not card.select_one('[data-nameserver-current]') and 'The latest nameserver refresh has not completed.' in card.get_text() and NS[0] in card.select_one('details[data-nameserver-history]').get_text() and provider.calls == calls)
        capture(owner, 'Unfinished', selector)
        for path in PRIVATE_PATHS:
            for prefix in ('/existing/', '/fresh/'):
                check('Registered nameserver private HTTP denial '+prefix+path, requests.get(base+prefix+path, timeout=15).status_code == 404)
        check('Registered nameserver read workflow creates no provider write or operation ledger', provider.posts == posts and db('SELECT * FROM ci_provider_operations ORDER BY id') == ledger and provider.calls == calls and not provider.errors)
        retained = {row['id']: row for row in db('SELECT id,started_at,started_epoch FROM ci_provider_refreshes ORDER BY seq')}
        check('Registered nameserver workflow preserves every inherited observation timestamp', all(retained.get(row['id']) == row for row in inherited))
        check('Registered nameserver browser has no page errors and all eight captures', not errors and all((qa/name).is_file() and (qa/name).stat().st_size > 0 for name in CAPTURES))
    finally:
        if trigger_created:
            db('DROP TRIGGER ci_rns_http_fault')
        if table_created:
            db('DROP TABLE ci_rns_http_fault')
        provider.registrar_domains, provider.registrar_mode, provider.nameservers, provider.nameserver_mode = original
        for context in contexts:
            context.close()
        for value in sessions:
            value.close()
    from registered_nameserver_changes_cases import run_browser as changes_browser, CAPTURES as CHANGE_CAPTURES
    changes = changes_browser(env, browser)
    check('Installed nameserver change continuation completes all scoped forms and captures',
          changes['passed'] is True and tuple(c['name'] for c in changes['captures']) == CHANGE_CAPTURES
          and all((qa/name).is_file() and (qa/name).stat().st_size > 0 for name in CHANGE_CAPTURES))
    from registered_nameserver_requests_cases import run_browser as client_requests_browser, CAPTURES as CLIENT_REQUEST_CAPTURES
    client_requests = client_requests_browser(env, browser)
    check('Installed client nameserver requests complete all scoped forms and captures',
          client_requests['passed'] is True and tuple(c['name'] for c in client_requests['captures']) == CLIENT_REQUEST_CAPTURES
          and all((qa/name).is_file() and (qa/name).stat().st_size > 0 for name in CLIENT_REQUEST_CAPTURES))
