"""Packaged owner forms after registered_nameservers_cases, with synthetic TLS only.

Two separately approved requests send once each. No fixture timestamp, business
row, approval, ledger or assignment is reset. SQL writes are limited to an
absent-guarded disposable audit fault and its cleanup; ordinary state uses HTTP.
"""
import hashlib
import json
import math
import re
import time
from types import SimpleNamespace
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from registered_nameservers_cases import EMAIL, PASSWORD, NS, cooldown_seconds
from staff_cases import OWNER, STAFF


CAPTURES = tuple('Nameserver-Change-' + state + '-' + size + '.png'
                 for state in ('Ready', 'Interrupted', 'Observed', 'Orphan')
                 for size in ('Desktop', 'Mobile'))
DESIRED = ['ns1.changed-nameservers.invalid', 'ns2.changed-nameservers.invalid']
FINAL = ['ns1.final-nameservers.invalid', 'ns2.final-nameservers.invalid']
PRIVATE_PATHS = (
    'app/providers/registered_nameserver_changes.php',
    'app/providers/registered_nameserver_changes_storage.php',
    'app/providers/registered_nameserver_changes_views.php',
    'admin/controllers/registered_nameserver_changes.php',
    'database/schema/registered_nameserver_changes_schema.php',
)


def run_browser(env, browser):
    """Use the existing main HTTP accumulator and installed ci_ fixture."""
    soup, token, form_data, submit = (env[k] for k in ('soup', 'token', 'form_data', 'submit'))
    base, qa, provider = env['BASE'], env['QA'], env['provider_server']
    admin, portal = base + '/existing/admin/index.php', base + '/existing/portal/index.php'
    settings = admin + '?view=provider-settings'
    prepare, send, verify, recover = ('provider_nameserver_change_' + action
                                      for action in ('prepare', 'submit', 'verify', 'recover'))
    refresh = 'provider_inventory_nameservers_refresh'
    sessions, contexts, checks, captures, errors = [], [], [], [], []
    original = (list(provider.registrar_domains), provider.registrar_mode,
                dict(provider.nameservers), provider.nameserver_mode, provider.nameserver_change_mode)
    original_posts, original_change_posts = list(provider.posts), list(provider.nameserver_change_posts)
    inherited_times = db('SELECT id,started_at,started_epoch FROM ci_provider_refreshes ORDER BY seq')
    inherited_ledger = db('SELECT * FROM ci_provider_operations ORDER BY id')
    trigger_created = table_created = False

    def check(name, passed):
        if type(passed) is not bool or any(row['name'] == name for row in checks):
            raise AssertionError('Unique strict nameserver change outcome required')
        checks.append({'name': name, 'passed': passed})
        env['check'](name, passed)
        if not passed:
            raise AssertionError(name)

    def session():
        value = requests.Session(); sessions.append(value); return value

    def exists(table):
        return bool(db('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?', ['ci_' + table]))

    def state():
        tables = ('provider_nameserver_change_requests', 'provider_nameserver_change_terminals',
                  'provider_nameserver_change_checks', 'provider_operation_grants', 'provider_operations',
                  'registered_nameserver_bindings', 'registered_nameserver_sets', 'provider_refreshes',
                  'provider_refresh_results', 'provider_registrar_links')
        data = {name: db('SELECT * FROM ci_' + name + ' ORDER BY id') if exists(name) else None for name in tables}
        data['settings'] = db('SELECT * FROM ci_settings WHERE BINARY LEFT(name,?)=BINARY ? ORDER BY name',
                              [len('registered_nameserver'), 'registered_nameserver'])
        data['audits'] = db('SELECT * FROM ci_audit WHERE BINARY LEFT(action,?)=BINARY ? ORDER BY id',
                            [len('provider.registered_nameserver_changes_'), 'provider.registered_nameserver_changes_'])
        return hashlib.sha256(json.dumps(data, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

    def no_effect(name, before, calls, posts):
        check(name, state() == before and provider.calls == calls and provider.posts == posts)

    def current():
        response = owner.get(settings, timeout=20)
        if response.status_code != 200:
            raise AssertionError('Nameserver change owner page unavailable')
        return response

    def fields(response, selector):
        node = soup(response).select_one(selector)
        if node is None:
            raise AssertionError('Expected nameserver change form missing')
        return form_data(node)

    def history(response, request_id):
        node = soup(response).select_one('#nameserver-request-' + request_id)
        if node is None:
            raise AssertionError('Original nameserver change history missing')
        return node

    def wait_read():
        latest = int(db('SELECT MAX(started_epoch) AS latest FROM ci_provider_refreshes WHERE profile_id=?', [profile['id']])[0]['latest'] or 0)
        remaining = cooldown_seconds(latest, int(time.time()))
        if remaining:
            time.sleep(remaining)

    def navigate():
        response = page.goto(settings, wait_until='load', timeout=30000)
        if response is None or response.status != 200:
            raise AssertionError('Packaged Chromium owner navigation failed')
        return SimpleNamespace(text=page.content(), url=page.url)

    def open_details(locator):
        locator.evaluate('el=>{for(let p=el;p;p=p.parentElement)if(p.tagName==="DETAILS")p.open=true}')

    def fill_form(selector, values):
        item = page.locator(selector)
        try:
            expect(item).to_have_count(1, timeout=30000)
        except Exception as error:
            raise AssertionError('One exact nameserver owner form required') from error
        open_details(item)
        for key, value in values.items():
            control = item.locator('[name="' + key + '"]')
            if key == 'reviewed':
                control.set_checked(value == '1')
            else:
                control.fill(value)
        return item

    def click_form(item, label):
        with page.expect_navigation(wait_until='load', timeout=30000) as navigation:
            item.get_by_role('button', name=label, exact=True).click()
        response = navigation.value
        if response is None or response.status != 200:
            raise AssertionError('Packaged nameserver form returned no owner response')
        return SimpleNamespace(text=page.content(), url=page.url, status_code=response.status)

    def read_form(selector, label):
        wait_read(); before = len(provider.get_paths)
        navigate()
        response = click_form(fill_form(selector, {'current_password': PASSWORD}), label)
        if provider.get_paths[before:] != ['/domain/14701/nameservers']:
            raise AssertionError('One exact numeric nameserver GET required')
        return response

    def capture(title, selector):
        # Actual page, original layout first; height growth is only capture framing.
        for width, height, size in ((1440, 1000, 'Desktop'), (320, 740, 'Mobile')):
            name = 'Nameserver-Change-' + title + '-' + size + '.png'
            page.set_viewport_size({'width': width, 'height': height})
            page.evaluate('document.fonts.ready'); page.keyboard.press('Escape')
            page.evaluate('document.activeElement?.blur()')
            if size == 'Mobile':
                expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
            item = page.locator(selector); open_details(item)
            item.locator('details').evaluate_all('nodes=>nodes.forEach(n=>n.open=true)')
            check('Nameserver change ' + title + ' ' + size + ' original layout fits',
                  not errors and page.evaluate('document.documentElement.scrollWidth<=innerWidth+1') is True)
            measured = item.evaluate('el=>({height:el.getBoundingClientRect().height,header:document.querySelector(".admin-topbar").getBoundingClientRect().height})')
            if not (0 < measured['height'] <= 12000 and 0 < measured['header'] < 500):
                raise AssertionError('Bounded nameserver capture extent required')
            capture_height = max(height, math.ceil(measured['height'] + measured['header'] + 40))
            try:
                if capture_height != height:
                    page.set_viewport_size({'width': width, 'height': capture_height})
                item.evaluate('el=>{const h=document.querySelector(".admin-topbar").getBoundingClientRect().height;window.scrollTo({top:Math.max(0,scrollY+el.getBoundingClientRect().top-h-16),left:0,behavior:"instant"})}')
                ready = '''el=>{const r=el.getBoundingClientRect(),h=document.querySelector('.admin-topbar').getBoundingClientRect();
                    const visible=n=>{const b=n.getBoundingClientRect();return b.width>0&&b.height>0};
                    const points=n=>{const b=n.getBoundingClientRect();return b.left>=-1&&b.right<=innerWidth+1&&b.top>=0&&b.bottom<=innerHeight&&[[.5,.5],[.1,.1],[.9,.9]].every(([x,y])=>{const p=document.elementFromPoint(b.left+b.width*x,b.top+b.height*y);return p&&(p===n||n.contains(p))})};
                    const nodes=[...el.querySelectorAll('h2,h3,h4,summary,button,input[type=checkbox]')].filter(visible);
                    return r.width>0&&r.height>0&&r.left>=-1&&r.right<=innerWidth+1&&r.top>=Math.max(0,h.bottom)+8&&r.bottom<=innerHeight-8
                        &&nodes.length>0&&nodes.every(points)&&[.02,.5,.98].every(x=>[.01,.5,.99].every(y=>{const p=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);return p&&(p===el||el.contains(p))}));}'''
                page.wait_for_function(ready, arg=item.element_handle(), timeout=30000)
                check('Nameserver change ' + title + ' ' + size + ' entire capture is unobscured', item.evaluate(ready) is True)
                box = item.bounding_box()
                item.screenshot(path=str(qa / name), animations='disabled', timeout=30000)
                captures.append({'name': name, 'mode': 'locator', 'layout_viewport': {'width': width, 'height': height},
                                 'capture_viewport': {'width': width, 'height': capture_height}, 'viewport_box': box,
                                 'measured_header_height': measured['header'], 'coverage': 'Complete scoped target at original width.'})
            finally:
                if capture_height != height:
                    page.set_viewport_size({'width': width, 'height': height})

    try:
        check('Nameserver change callback refuses prior captures', all(not (qa / name).exists() and not (qa / name).is_symlink() for name in CAPTURES))
        check('Nameserver change callback uses disposable original database', db('SELECT DATABASE() AS name') == [{'name': 'cwp_test'}])
        check('Nameserver change callback starts with no new change history',
              not any(exists(name) for name in ('provider_nameserver_change_requests', 'provider_nameserver_change_terminals', 'provider_nameserver_change_checks')))
        owner = session(); submit(owner, admin + '?view=login', 'login', {'email': EMAIL, 'password': PASSWORD})
        users = db('SELECT id FROM ci_users WHERE email=?', [EMAIL])
        links = db('SELECT * FROM ci_provider_registrar_links WHERE domain_id=?', [14701])
        check('Nameserver change continues the actual owner and original domain assignment', len(users) == len(links) == 1 and links[0]['domain'] == 'nameservers-client.invalid')
        owner_id, link = users[0]['id'], links[0]
        profile = db('SELECT id,revision FROM ci_provider_profiles WHERE id=?', [link['profile_id']])[0]
        selector = '#nameserver-' + link['id']
        latest = db('SELECT b.id,r.state FROM ci_registered_nameserver_bindings b JOIN ci_provider_refreshes f ON f.id=b.id LEFT JOIN ci_provider_refresh_results r ON r.id=b.id WHERE b.link_id=? ORDER BY f.seq DESC LIMIT 1', [link['id']])
        check('Nameserver change inherits genuine unfinished read without fallback', len(latest) == 1 and latest[0]['state'] is None and not soup(current()).select_one(selector + ' [data-nameserver-current]'))
        before, calls, posts = state(), provider.calls, list(provider.posts)
        response = current()
        check('Nameserver change GET presents explicit local preparation only', bool(soup(response).select_one('[data-nameserver-change-setup]')) and state() == before and provider.calls == calls)
        for changed, label in (({'csrf': 'bad', 'current_password': PASSWORD}, 'CSRF'), ({'current_password': 'wrong'}, 'password')):
            submit(owner, settings, prepare, changed)
            no_effect('Nameserver change preparation refuses ' + label, before, calls, posts)
        context = browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='reduce'); contexts.append(context)
        context.add_cookies([{'name': c.name, 'value': c.value, 'url': base} for c in owner.cookies])
        page = context.new_page(); page.on('pageerror', lambda error: errors.append(type(error).__name__))
        navigate(); click_form(fill_form('details[data-nameserver-change-setup] form', {'current_password': PASSWORD}), 'Enable nameserver changes')
        check('Chromium explicitly prepares empty nameserver change history',
              db('SELECT value FROM ci_settings WHERE name=?', ['registered_nameserver_changes_schema_version']) == [{'value': '1'}]
              and not db('SELECT * FROM ci_provider_nameserver_change_requests') and provider.calls == calls and provider.posts == posts)
        page.locator(selector).locator('details').evaluate_all('nodes=>nodes.forEach(n=>n.open=true)')
        check('Prepared changes still refuse the inherited unfinished read', page.locator(selector + ' [data-nameserver-change-form]').count() == 0 and 'Refresh nameservers for this assignment before approving a change.' in page.locator(selector).inner_text())
        provider.registrar_domains = [row for row in original[0] if str(row.get('id')) != '14701'] + [{'id': 14701, 'name': link['domain']}]
        provider.registrar_mode = 'success'; provider.nameservers = {**original[2], '14701': list(NS)}
        provider.nameserver_mode = 'success'; provider.nameserver_change_mode = 'uncertain_unlanded'
        read_form(selector + ' form[data-nameserver-refresh]', 'Refresh nameservers')
        first = fields(SimpleNamespace(text=page.content()), selector + ' form[data-nameserver-change-form]')
        check('Chromium fresh GET enables exact full-set approval without caller old set',
              bool(re.fullmatch('[a-f0-9]{32}', first['request_id'])) and not any(key in first for key in ('old-ns', 'ns', 'before_nameservers'))
              and all(name in page.locator(selector).inner_text() for name in NS))
        before, calls, posts = state(), provider.calls, list(provider.posts)
        submitted = {**first, 'desired_nameservers': '\n'.join(DESIRED), 'reviewed': '1', 'current_password': PASSWORD}
        for changed, label in (({'csrf': 'bad'}, 'CSRF'), ({'current_password': 'wrong'}, 'password'), ({'reviewed': ''}, 'confirmation'),
                               ({'binding_sha256': '0' * 64}, 'binding'), ({'anchor_id': 'f' * 32}, 'observation'),
                               ({'desired_nameservers': ''}, 'empty set'), ({'desired_nameservers': '\n'.join(NS)}, 'overlapping set')):
            response = owner.post(settings, data={**submitted, **changed}, timeout=30)
            no_effect('Nameserver change approval refuses ' + label, before, calls, posts)
            check('Nameserver change ' + label + ' failure never echoes password', response.status_code == 200 and all(n.get('value', '') == '' for n in soup(response).select('input[type=password]')))
        read_form(selector + ' form[data-nameserver-refresh]', 'Refresh nameservers')
        before, calls, posts = state(), provider.calls, list(provider.posts)
        owner.post(settings, data=submitted, timeout=30)
        no_effect('Nameserver change refuses an older successful anchor after a newer GET', before, calls, posts)

        # Genuine non-owner sessions, including a real support session. No role rows are rewritten.
        member = session(); submit(member, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
        client = session(); submit(client, portal, 'login', {'email': 'registrar-client1@example.invalid', 'password': OWNER})
        member_page, client_page = member.get(admin + '?view=security', timeout=20), client.get(portal, timeout=20)
        check('Nameserver change denial identities are genuinely signed in', member_page.status_code == 200 and bool(soup(member_page).select_one('[name=csrf]')) and 'Welcome, ' in client_page.text)
        for action in (prepare, send, verify, recover):
            response = member.post(settings, data={**submitted, 'action': action, 'csrf': token(member_page), 'current_password': STAFF}, timeout=30)
            check('Nameserver change ordinary staff denies ' + action, response.status_code == 403)
            no_effect('Nameserver change ordinary staff preserves state ' + action, before, calls, posts)
            response = client.post(settings, data={**submitted, 'action': action, 'csrf': token(client_page), 'current_password': OWNER}, allow_redirects=False, timeout=30)
            check('Nameserver change client denies ' + action, response.status_code == 303 and 'login' in response.headers.get('Location', ''))
            no_effect('Nameserver change client preserves state ' + action, before, calls, posts)
        chosen_form(owner, admin + '?view=support', 'support_policy', 'client_id', link['client_id'], {'allowed': '1', 'owner_password': PASSWORD})
        support = chosen_form(owner, admin + '?view=support', 'support_start', 'client_id', link['client_id'], {})
        support_id = parse_qs(urlparse(support.url).query).get('support', [''])[0]
        check('Nameserver change support denial uses an actual active session', bool(re.fullmatch('[a-f0-9]{32}', support_id)) and 'Support session' in support.text)
        for action in (prepare, send, verify, recover):
            response = owner.post(settings + '&support=' + support_id, data={**submitted, 'action': action, 'csrf': token(support)}, timeout=30)
            check('Nameserver change support denies ' + action, response.status_code == 403)
            no_effect('Nameserver change support preserves state ' + action, before, calls, posts)
        submit(owner, portal + '?support=' + support_id, 'support_end')
        chosen_form(owner, admin + '?view=support', 'support_policy', 'client_id', link['client_id'], {'allowed': None, 'owner_password': PASSWORD})

        navigate(); first = fields(SimpleNamespace(text=page.content()), selector + ' form[data-nameserver-change-form]')
        first_id = first['request_id']
        if re.fullmatch('[a-f0-9]{32}', first_id) is None:
            raise AssertionError('Exact native generated request identifier required')
        fill_form(selector + ' form[data-nameserver-change-form]', {'desired_nameservers': '\n'.join(DESIRED)})
        capture('Ready', selector + ' details[data-nameserver-change]')
        check('Nameserver terminal fault refuses prior disposable objects', not exists('rnsc_http_fault') and not db('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND BINARY trigger_name=?', ['ci_rnsc_http_fault']))
        db('CREATE TABLE ci_rnsc_http_fault (id INT PRIMARY KEY,request_id CHAR(32) NOT NULL,terminal_count INT NOT NULL,ledger_status VARCHAR(32) NOT NULL,attempts INT NOT NULL) ENGINE=MyISAM'); table_created = True
        prefix = 'Registered nameserver change terminal ' + first_id
        db("CREATE TRIGGER ci_rnsc_http_fault BEFORE INSERT ON ci_audit FOR EACH ROW BEGIN IF NEW.action='provider.registered_nameserver_changes_terminal' AND BINARY LEFT(NEW.detail," + str(len(prefix)) + ")=BINARY '" + prefix + "' THEN INSERT INTO ci_rnsc_http_fault VALUES (1,'" + first_id + "',(SELECT COUNT(*) FROM ci_provider_nameserver_change_terminals WHERE id='" + first_id + "'),(SELECT status FROM ci_provider_operations WHERE idempotency_key='nameservers:" + first_id + "'),(SELECT attempts FROM ci_provider_operations WHERE idempotency_key='nameservers:" + first_id + "')); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic nameserver terminal audit refusal'; END IF; END"); trigger_created = True
        submitted = {**first, 'desired_nameservers': '\n'.join(DESIRED), 'reviewed': '1', 'current_password': PASSWORD}
        response = click_form(fill_form(selector + ' form[data-nameserver-change-form]', {'desired_nameservers': '\n'.join(DESIRED), 'reviewed': '1', 'current_password': PASSWORD}), 'Request nameserver change')
        witness = db('SELECT * FROM ci_rnsc_http_fault')
        db('DROP TRIGGER ci_rnsc_http_fault'); trigger_created = False
        db('DROP TABLE ci_rnsc_http_fault'); table_created = False
        request = db('SELECT * FROM ci_provider_nameserver_change_requests WHERE id=?', [first_id])
        ledger = db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['nameservers:' + first_id])
        check('Chromium approval sends one exact complete registrar POST despite local terminal fault',
              len(request) == len(ledger) == len(witness) == 1 and witness[0] == {'id': 1, 'request_id': first_id, 'terminal_count': 1, 'ledger_status': 'uncertain', 'attempts': 1}
              and len(provider.nameserver_change_posts) == len(original_change_posts) + 1
              and provider.nameserver_change_posts[-1]['raw'] == json.dumps({'ns': DESIRED, 'old-ns': NS}, separators=(',', ':'))
              and provider.nameserver_change_posts[-1]['path'] == '/domain/14701/nameservers'
              and provider.nameservers['14701'] == NS and ledger[0]['attempts'] == 1 and ledger[0]['status'] == 'uncertain')
        check('Post-dispatch fault preserves request approval and durable ledger but rolls back terminal',
              request[0]['actor_id'] == owner_id and json.loads(request[0]['document_json'])['params'] == {'domainId': 14701, 'ns': DESIRED, 'old-ns': NS}
              and not db('SELECT * FROM ci_provider_nameserver_change_terminals WHERE id=?', [first_id])
              and db('SELECT value FROM ci_settings WHERE name=?', ['registered_nameserver_changes_terminal_count']) == [{'value': '0'}]
              and 'The change could not be saved.' in response.text and 'Synthetic nameserver terminal audit refusal' not in response.text and 'SQLSTATE' not in response.text)
        navigate(); capture('Interrupted', '#nameserver-request-' + first_id)
        before, calls, posts = state(), provider.calls, list(provider.posts)
        owner.post(settings, data=submitted, timeout=30)
        no_effect('Exact interrupted request replay never repeats the write', before, calls, posts)
        candidate = ('0' if first_id[0] != '0' else '1') + first_id[1:]
        check('Nameserver fence negative uses an unused request identifier', not db('SELECT id FROM ci_provider_nameserver_change_requests WHERE id=?', [candidate]))
        owner.post(settings, data={**submitted, 'request_id': candidate}, timeout=30)
        no_effect('Another approval remains fenced while original terminal is interrupted', before, calls, posts)
        recovery = fields(current(), '#nameserver-request-' + first_id + ' form[data-nameserver-change-recover]')
        for changed, label in (({'csrf': 'bad'}, 'CSRF'), ({'current_password': 'wrong'}, 'password'), ({'reviewed': ''}, 'confirmation'), ({'ledger_sha256': '0' * 64}, 'ledger digest')):
            owner.post(settings, data={**recovery, 'current_password': PASSWORD, 'reviewed': '1', **changed}, timeout=30)
            no_effect('Nameserver local recovery refuses ' + label, before, calls, posts)
        navigate(); click_form(fill_form('#nameserver-request-' + first_id + ' form[data-nameserver-change-recover]', {'reviewed': '1', 'current_password': PASSWORD}), 'Restore saved request history')
        terminal = db('SELECT * FROM ci_provider_nameserver_change_terminals WHERE id=?', [first_id])
        check('Chromium recovery restores only original owner terminal without resend', len(terminal) == 1 and terminal[0]['actor_id'] == owner_id
              and json.loads(terminal[0]['document_json'])['recovered_from_ledger'] is True and provider.calls == calls and provider.posts == posts
              and db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['nameservers:' + first_id]) == ledger
              and 'Saved request history restored.' in page.content() and page.locator('#nameserver-request-' + first_id + ' [data-nameserver-change-verify]').count() == 1)
        before, calls, posts = state(), provider.calls, list(provider.posts)
        owner.post(settings, data=submitted, timeout=30)
        no_effect('Exact recovered approval replay retains one immutable send', before, calls, posts)
        check_fields = fields(current(), '#nameserver-request-' + first_id + ' form[data-nameserver-change-verify]')
        for changed, label in (({'csrf': 'bad'}, 'CSRF'), ({'current_password': 'wrong'}, 'password')):
            owner.post(settings, data={**check_fields, 'current_password': PASSWORD, **changed}, timeout=30)
            no_effect('Nameserver readback refuses ' + label, before, calls, posts)
        for mode, disposition, message in (('success', 'different', 'Different nameservers were reported'), ('authentication', 'unavailable', 'Nameservers were unavailable')):
            provider.nameserver_mode = mode
            read_form('#nameserver-request-' + first_id + ' form[data-nameserver-change-verify]', 'Check current nameservers')
            latest_check = db('SELECT c.disposition FROM ci_provider_nameserver_change_checks c JOIN ci_provider_refreshes f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1', [first_id])
            check('Fresh nameserver ' + disposition + ' readback preserves unresolved fence', latest_check == [{'disposition': disposition}]
                  and message in page.locator('#nameserver-request-' + first_id).inner_text()
                  and page.locator(selector + ' [data-nameserver-current]').count() == 0 and page.locator(selector + ' [data-nameserver-change-form]').count() == 0
                  and 'private synthetic nameserver response' not in page.content() and provider.posts == posts)
        provider.nameserver_mode = 'success'; provider.nameservers['14701'] = list(DESIRED)
        read_form(selector + ' form[data-nameserver-refresh]', 'Refresh nameservers')
        check('Ordinary matching GET cannot resolve the explicit request fence', page.locator(selector + ' [data-nameserver-current]').count() == 0
              and page.locator(selector + ' [data-nameserver-change-form]').count() == 0
              and not db("SELECT id FROM ci_provider_nameserver_change_checks WHERE request_id=? AND disposition='observed'", [first_id]) and provider.posts == posts)
        read_form('#nameserver-request-' + first_id + ' form[data-nameserver-change-verify]', 'Check current nameservers')
        check('Explicit fresh matching check resolves observation without causality claim',
              len(db("SELECT id FROM ci_provider_nameserver_change_checks WHERE request_id=? AND disposition='observed'", [first_id])) == 1
              and page.locator(selector + ' [data-nameserver-current]').count() == 1 and page.locator(selector + ' [data-nameserver-change-form]').count() == 1
              and 'does not prove this request caused the change or confirm DNS propagation' in page.locator('#nameserver-request-' + first_id).inner_text()
              and 'Missing request history was restored without resending the change.' in page.locator('#nameserver-request-' + first_id).inner_text()
              and provider.posts == posts)
        capture('Observed', '#nameserver-request-' + first_id)

        # A second independent approval is necessary: changed assignments cannot
        # be artificially restored to resolve the first request's old context.
        navigate(); second = fields(SimpleNamespace(text=page.content()), selector + ' form[data-nameserver-change-form]')
        second_id = second['request_id']
        click_form(fill_form(selector + ' form[data-nameserver-change-form]', {'desired_nameservers': '\n'.join(FINAL), 'reviewed': '1', 'current_password': PASSWORD}), 'Request nameserver change')
        second_ledger = db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['nameservers:' + second_id])
        check('Second separately reviewed request sends once and remains uncertain', second_id != first_id and len(second_ledger) == 1 and second_ledger[0]['attempts'] == 1
              and second_ledger[0]['status'] == 'uncertain' and len(provider.nameserver_change_posts) == len(original_change_posts) + 2
              and provider.nameserver_change_posts[-1]['raw'] == json.dumps({'ns': FINAL, 'old-ns': DESIRED}, separators=(',', ':'))
              and 'Change requested — result not yet confirmed' in page.locator('#nameserver-request-' + second_id).inner_text())
        second_submit = {**second, 'desired_nameservers': '\n'.join(FINAL), 'reviewed': '1', 'current_password': PASSWORD}
        before, calls, posts = state(), provider.calls, list(provider.posts)
        owner.post(settings, data=second_submit, timeout=30)
        no_effect('Second exact replay never retries uncertain provider write', before, calls, posts)
        saved_rows = {table: db('SELECT * FROM ci_' + table + ' ORDER BY id') for table in ('provider_nameserver_change_requests', 'provider_nameserver_change_terminals', 'provider_nameserver_change_checks')}
        chosen_form(owner, settings, 'provider_inventory_registrar_unlink', 'link_id', link['id'], {'current_password': PASSWORD})
        response = current(); orphan = history(response, second_id)
        check('Real unlink preserves global unresolved history without follow-up authority', not db('SELECT id FROM ci_provider_registrar_links WHERE id=?', [link['id']])
              and 'The assignment or provider connection has changed.' in orphan.get_text() and not orphan.select('form')
              and all(db('SELECT * FROM ci_' + table + ' ORDER BY id') == rows for table, rows in saved_rows.items()) and provider.calls == calls and provider.posts == posts)
        other = db('SELECT id FROM ci_studio_clients WHERE email=?', ['registrar-client2@example.invalid'])[0]['id']
        chosen_form(owner, settings, 'provider_inventory_registrar_link', 'domain_id', 14701, {'client_id': other, 'current_password': PASSWORD})
        reassigned = db('SELECT * FROM ci_provider_registrar_links WHERE profile_id=? AND domain_id=?', [profile['id'], 14701])
        check('Real reassignment creates a distinct client context without clearing history', len(reassigned) == 1 and reassigned[0]['id'] != link['id'] and reassigned[0]['client_id'] == other
              and all(db('SELECT * FROM ci_' + table + ' ORDER BY id') == rows for table, rows in saved_rows.items()) and provider.posts == posts)
        new_selector = '#nameserver-' + reassigned[0]['id']
        read_form(new_selector + ' form[data-nameserver-refresh]', 'Refresh nameservers')
        page.locator(new_selector).locator('details').evaluate_all('nodes=>nodes.forEach(n=>n.open=true)')
        check('Fresh new-assignment GET remains fenced by original resource request', page.locator(new_selector + ' [data-nameserver-current]').count() == 0
              and page.locator(new_selector + ' [data-nameserver-change-form]').count() == 0
              and 'remains unresolved' in page.locator(new_selector).inner_text()
              and page.locator('#nameserver-request-' + second_id + ' form').count() == 0 and provider.posts == posts)
        # Exact retained native read fields are used only for a malicious direct
        # POST negative; the UI correctly offers no fresh approval form here.
        retained = db('SELECT b.id,b.binding_sha256 FROM ci_registered_nameserver_bindings b JOIN ci_provider_refreshes f ON f.id=b.id WHERE b.link_id=? ORDER BY f.seq DESC LIMIT 1', [reassigned[0]['id']])[0]
        unused = ('0' if second_id[0] != '0' else '1') + second_id[1:]
        check('Reassigned fence probe uses a genuinely unused request id', not db('SELECT id FROM ci_provider_nameserver_change_requests WHERE id=?', [unused]))
        before, calls, posts = state(), provider.calls, list(provider.posts)
        response = owner.post(settings, data={'action': send, 'csrf': token(current()), 'request_id': unused, 'link_id': reassigned[0]['id'],
            'anchor_id': retained['id'], 'binding_sha256': retained['binding_sha256'], 'desired_nameservers': '\n'.join(FINAL), 'reviewed': '1', 'current_password': PASSWORD}, timeout=30)
        no_effect('Exact new-context direct approval cannot bypass original resource fence', before, calls, posts)
        check('Resource fence refusal is explicit after reassignment', 'A previous nameserver change is unresolved. It cannot be sent again.' in response.text)
        navigate(); capture('Orphan', '#nameserver-request-' + second_id)
        for path in PRIVATE_PATHS:
            for prefix_path in ('/existing/', '/fresh/'):
                check('Nameserver change private HTTP denial ' + prefix_path + path, requests.get(base + prefix_path + path, timeout=15).status_code == 404)
        after_times = {row['id']: row for row in db('SELECT id,started_at,started_epoch FROM ci_provider_refreshes ORDER BY seq')}
        check('Nameserver change workflow retains every inherited observation timestamp', all(after_times.get(row['id']) == row for row in inherited_times))
        after_ledger = {row['id']: row for row in db('SELECT * FROM ci_provider_operations ORDER BY id')}
        check('Nameserver change workflow retains every prior operation ledger row', all(after_ledger.get(row['id']) == row for row in inherited_ledger))
        check('Nameserver change workflow has exactly two original sends and no provider retries',
              len(provider.posts) == len(original_posts) + 2 and provider.posts[:len(original_posts)] == original_posts
              and len(provider.nameserver_change_posts) == len(original_change_posts) + 2 and provider.nameserver_change_posts[:len(original_change_posts)] == original_change_posts
              and db('SELECT attempts FROM ci_provider_operations WHERE idempotency_key IN (?,?) ORDER BY idempotency_key', ['nameservers:' + first_id, 'nameservers:' + second_id]) == [{'attempts': 1}, {'attempts': 1}] and not provider.errors)
        check('Nameserver change browser finishes with safe projection and eight complete scoped captures', not errors
              and all((qa / name).is_file() and (qa / name).stat().st_size > 0 for name in CAPTURES)
              and all(secret not in page.content() for secret in (PASSWORD, 'SYNTHETIC_RAW_GENERAL_KEY', 'actor_auth', 'document_json', 'key_fingerprint')))
        return {'passed': True, 'scope': 'Actual packaged owner forms and Chromium; two one-shot synthetic TLS writes, genuine cooldowns and disposable audit fault. No live provider/DNS propagation claim.',
                'checks': checks, 'captures': captures, 'requests': [first_id, second_id], 'expected_sends': 2}
    finally:
        if trigger_created:
            db('DROP TRIGGER ci_rnsc_http_fault')
        if table_created:
            db('DROP TABLE ci_rnsc_http_fault')
        provider.registrar_domains, provider.registrar_mode, provider.nameservers, provider.nameserver_mode, provider.nameserver_change_mode = original
        for context in contexts:
            context.close()
        for value in sessions:
            value.close()
