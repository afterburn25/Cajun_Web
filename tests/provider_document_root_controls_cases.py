"""Packaged owner folder controls; real local HTTP/SQL, synthetic provider only."""
import math
import time
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER, STAFF


CAPTURES = tuple('Document-Root-Control-' + state + '-' + size + '.png'
                 for state in ('Ready', 'Requested', 'Different', 'Observed')
                 for size in ('Desktop', 'Mobile'))


def run_browser(env, browser):
    base, qa, provider = (env[k] for k in ('BASE', 'QA', 'provider_server'))
    soup, token, submit = (env[k] for k in ('soup', 'token', 'submit'))
    outcomes = []

    def check(name, passed):
        if any(row['name'] == name for row in outcomes):
            raise AssertionError('Duplicate folder control outcome')
        outcomes.append({'name': name, 'passed': bool(passed)})
        env['check'](name, passed)

    admin = base + '/existing/admin/index.php'
    url = admin + '?view=provider-settings'
    portal = base + '/existing/portal/index.php'
    inherited = db('SELECT * FROM ci_provider_refreshes ORDER BY seq')
    inherited_audits = db('SELECT * FROM ci_audit ORDER BY id')
    original_owner = db('SELECT * FROM ci_users WHERE email=?', ['owner@example.invalid'])[0]
    original_profile = db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']])
    sessions = []

    def session():
        value = requests.Session(); sessions.append(value); return value

    old = {key: getattr(provider, key) for key in ('mode', 'write_mode', 'packages', 'document_roots', 'document_roots_mode')}
    posts = list(provider.posts)
    captured = []
    try:
        # A real distinct owner gets a fresh quota. No timestamps or original
        # intents, audits, authority records or rate-limit rows are rewritten.
        setup = session(); login = submit(setup, admin + '?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
        after_login = db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']])[0]
        check('Folder controls setup login preserves original owner authority',
              login.status_code == 200 and parse_qs(urlparse(login.url).query).get('view') == ['dashboard']
              and {k: v for k, v in after_login.items() if k != 'last_login'} == {k: v for k, v in original_owner.items() if k != 'last_login'}
              and after_login['last_login'] is not None
              and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == original_profile)
        original_owner = after_login
        email = 'folder-controls-owner@example.invalid'
        check('Folder controls dedicated owner starts absent', not db('SELECT id FROM ci_users WHERE email=?', [email]))
        staff_url = admin + '?view=staff'
        submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'Website folder controls owner', 'email': email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
        actor = db('SELECT id FROM ci_users WHERE email=?', [email])[0]['id']
        invitation = chosen_form(setup, staff_url, 'staff_invite', 'id', actor, {'owner_password': OWNER})
        invite_url = soup(invitation).select_one('[data-staff-invite]')['href']
        code = parse_qs(urlparse(invite_url).fragment)['token'][0]
        activation = session(); form = activation.get(invite_url)
        activated = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(form), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
        check('Folder controls real invitation activates separate owner',
              activated.status_code == 200 and parse_qs(urlparse(activated.url).query).get('view') == ['login']
              and actor != original_owner['id']
              and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [actor]) == [{'role': 'owner', 'enabled': 1}]
              and db('SELECT password_hash FROM ci_users WHERE id=?', [actor])[0]['password_hash'] != '')
        owner = session(); submit(owner, admin + '?view=login', 'login', {'email': email, 'password': OWNER})
        member = session(); submit(member, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
        client = session(); submit(client, portal, 'login', {'email': 'alice@example.invalid', 'password': 'Synthetic client passphrase 2026'})
        check('Folder controls dedicated owner begins with no reads', not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?', [actor]))
        profile = db('SELECT id,revision FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
        client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']

        def post(action, source=None, **fields):
            page = source if source is not None else owner.get(url)
            data = {'csrf': token(page), 'action': action, 'profile_id': profile['id'], 'revision': str(profile['revision']), 'current_password': OWNER}
            for candidate in soup(page).select('form'):
                if not candidate.select_one('input[name=action][value="' + action + '"]'):
                    continue
                if any(candidate.select_one('input[name=' + key + ']') is not None
                       and candidate.select_one('input[name=' + key + ']').get('value') != str(fields[key])
                       for key in ('link_id', 'request_id') if key in fields):
                    continue
                data.update({n['name']: n.get('value', '') for n in candidate.select('input[name]')}); break
            data.update(current_password=OWNER); data.update(fields)
            return owner.post(url, data=data)

        def cool():
            # Common profile cooldown is shared with connection checks.
            epochs = [db('SELECT MAX(started_epoch) AS epoch FROM ci_' + table + ' WHERE profile_id=?', [profile['id']])[0]['epoch']
                      for table in ('provider_refreshes', 'provider_checks')]
            time.sleep(max(0, max(int(e or 0) for e in epochs) + 31 - time.time()))

        prepare = 'provider_document_root_control_prepare'
        change = 'provider_document_root_control_change'
        verify = 'provider_document_root_control_verify'
        refresh = 'provider_inventory_document_roots_refresh'
        share = 'provider_inventory_document_roots_share'
        calls = provider.calls
        check('Folder controls GET offers preparation without DDL or provider traffic',
              prepare in owner.get(url).text and not db('SHOW TABLES LIKE "ci_provider_document_root_changes"') and provider.calls == calls)
        for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'})]:
            post(prepare, **fields)
            check('Folder controls preparation refuses invalid ' + label,
                  not db('SHOW TABLES LIKE "ci_provider_document_root_changes"') and provider.calls == calls)
        post(prepare)
        check('Folder controls preparation creates only local empty request history',
              bool(db('SHOW TABLES LIKE "ci_provider_document_root_changes"')) and not db('SELECT id FROM ci_provider_document_root_changes') and provider.calls == calls)
        check('Folder controls reject nonowner and support route access', member.get(url).status_code == 403 and owner.get(url + '&support=fixture').status_code == 403)
        hostname = 'folder-controls.synthetic.invalid'; desired = 'public_html/newsite'
        check('Folder controls dedicated package starts absent', not db('SELECT id FROM ci_provider_package_links WHERE package_id=2701'))
        provider.mode = 'success'; provider.document_roots_mode = 'success'
        provider.packages = [*old['packages'], {'id': 2701, 'typeRef': 12, 'name': hostname, 'names': [hostname], 'enabled': True, 'created': '2000-01-01T00:00:00Z'}]
        cool(); post('provider_inventory_refresh')
        post('provider_inventory_link', package_id='2701', client_id=str(client_id))
        link = db('SELECT * FROM ci_provider_package_links WHERE profile_id=? AND package_id=2701', [profile['id']])[0]
        cool(); post('provider_inventory_refresh')
        provider.document_roots = {hostname: '../legacy/<folder> & text'}
        cool(); ready = post(refresh, link_id=link['id'])
        target = portal + '?document_roots=1&service_link=' + link['id']
        post(share, link_id=link['id'], sharing='enable')
        selector = '[data-document-root-control-package]:has(input[name=link_id][value="' + link['id'] + '"])'
        ready_form = soup(ready).select_one(selector + ' [data-document-root-control-change]')
        check('Folder controls real form binds latest map and manual attestation', ready_form is not None
              and ready_form.select_one('select[name=name] option[value="' + hostname + '"]') is not None
              and 'destination folder already exists' in ready_form.text and 'Managed WordPress' in ready.text)
        check('Folder controls show opaque old folder without posting it', '../legacy/<folder> & text' in soup(ready).text
              and ready_form.select_one('[name=before_path]') is None and ready_form.select_one('[name=entries]') is None)

        def capture(state, target_selector):
            context = browser.new_context(); errors = []
            try:
                context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path, 'secure': c.secure} for c in owner.cookies])
                page = context.new_page(); page.on('pageerror', lambda error: errors.append(str(error)))
                for width, height, size in ((1440, 1050, 'Desktop'), (320, 740, 'Mobile')):
                    name = 'Document-Root-Control-' + state + '-' + size + '.png'
                    destination = qa / name
                    if destination.exists() or destination.is_symlink():
                        raise RuntimeError('Folder control capture already exists')
                    page.set_viewport_size({'width': width, 'height': height}); navigation = page.goto(url)
                    page.evaluate('document.fonts.ready')
                    card = page.locator(target_selector); expect(card).to_have_count(1); expect(card).to_be_visible()
                    if width == 320:
                        expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
                    check('Folder controls ' + state + ' ' + size + ' original viewport has no horizontal overflow',
                          navigation.status == 200 and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
                    # Capture the actual bounded card at the same width after
                    # original viewport assertions. No DOM/style/motion changes.
                    required = math.ceil(card.bounding_box()['height']) + 160
                    if required > 2400:
                        raise AssertionError('Folder control capture target exceeded bounded height')
                    page.set_viewport_size({'width': width, 'height': max(height, required)})
                    card.evaluate('(el) => {const h=document.querySelector(".admin-topbar");window.scrollBy(0,el.getBoundingClientRect().top-(h?h.getBoundingClientRect().height:0)-16)}')
                    expect(card).to_be_in_viewport(ratio=1)
                    clear = card.evaluate('''el => {const r=el.getBoundingClientRect();return [0.08,0.5,0.92].every(x=>[0.03,0.5,0.97].every(y=>{const n=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);return n&&(n===el||el.contains(n));}));}''')
                    check('Folder controls ' + state + ' ' + size + ' capture card is unobscured', clear)
                    card.screenshot(path=str(destination))
                    captured.append(name)
                check('Folder controls ' + state + ' capture has no script errors', not errors)
            finally:
                context.close()

        capture('Ready', selector)
        fields = {n['name']: n.get('value', '') for n in ready_form.select('input[name]')}
        fields.update(name=hostname, desired_path=desired, reviewed='1', current_password=OWNER)
        calls = provider.calls
        for label, changed in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'}),
                               ('attestation', {'reviewed': '0'}), ('unlisted website', {'name': 'foreign.synthetic.invalid'}),
                               ('untrimmed path', {'desired_path': desired + ' '}), ('oversize path', {'desired_path': 'x' * 1025}),
                               ('stale map', {'anchor_id': 'a' * 32})]:
            failed = owner.post(url, data={**fields, **changed})
            check('Folder controls invalid ' + label + ' sends nothing and creates no request',
                  failed.status_code == 200 and bool(soup(failed).select('.notice.error')) and provider.calls == calls
                  and not db('SELECT id FROM ci_provider_document_root_changes'))
            if label == 'password':
                retry = soup(failed).select_one(selector + ' [data-document-root-control-change]')
                check('Folder controls password error keeps request website and destination but clears password',
                      retry is not None and retry.select_one('[name=request_id]')['value'] == fields['request_id']
                      and retry.select_one('[name=desired_path]')['value'] == desired
                      and retry.select_one('option[selected]')['value'] == hostname
                      and retry.select_one('[name=current_password]')['value'] == ''
                      and not retry.select_one('[name=reviewed]').has_attr('checked'))
        provider.write_mode = 'success'
        response = owner.post(url, data=fields)
        request = db('SELECT * FROM ci_provider_document_root_changes WHERE link_id=?', [link['id']])[0]
        ledger = db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['documentroot:' + request['id']])[0]
        terminal = db('SELECT * FROM ci_provider_document_root_dispatch_ends WHERE id=?', [request['id']])[0]
        expected_post = {'path': '/package/2701/web/updateDocRoot', 'body': {'update': {hostname: desired}}}
        check('Folder controls approve one exact synthetic POST with uncertain terminal',
              request['id'] == fields['request_id'] and provider.calls == calls + 1 and provider.posts == posts + [expected_post]
              and ledger['status'] == 'uncertain' and ledger['attempts'] == 1 and terminal['disposition'] == 'attempted'
              and 'effect is uncertain' in response.text and provider.document_roots[hostname] == '../legacy/<folder> & text')
        before_replay_audits = db('SELECT * FROM ci_audit ORDER BY id')
        owner.post(url, data=fields)
        check('Folder controls exact captured approval replay cannot send again', provider.posts == posts + [expected_post]
              and db('SELECT * FROM ci_provider_operations WHERE id=?', [ledger['id']]) == [ledger]
              and db('SELECT * FROM ci_audit ORDER BY id') == before_replay_audits)
        fenced = owner.post(url, data={**fields, 'request_id': 'd' * 32})
        check('Folder controls unresolved package refuses a new approval',
              'previous website folder change is unresolved' in fenced.text
              and not db('SELECT id FROM ci_provider_document_root_changes WHERE id=?', ['d' * 32])
              and provider.posts == posts + [expected_post])
        history_selector = '[data-document-root-control-history]:has(input[name=request_id][value="' + request['id'] + '"])'
        check('Folder controls unresolved request makes shared observation historical',
              'Historical observation' in client.get(target).text and change not in client.get(target).text and request['id'] not in client.get(target).text)
        capture('Requested', history_selector)
        cool(); different = post(verify, request_id=request['id'])
        check('Folder controls independent unchanged map records different and remains fenced',
              db('SELECT disposition FROM ci_provider_document_root_change_checks WHERE request_id=?', [request['id']]) == [{'disposition': 'different'}]
              and 'a different folder or no entry' in different.text and provider.posts == posts + [expected_post])
        capture('Different', history_selector)
        cool(); provider.document_roots_mode = 'error_array'; unavailable = post(verify, request_id=request['id'])
        check('Folder controls independent invalid map records unavailable without fallback',
              sorted(r['disposition'] for r in db('SELECT disposition FROM ci_provider_document_root_change_checks WHERE request_id=?', [request['id']])) == ['different', 'unavailable']
              and 'no usable folder map was reported' in unavailable.text and 'effect is uncertain' in unavailable.text)
        cool(); provider.document_roots_mode = 'success'; provider.document_roots = {hostname: desired}
        observed = post(verify, request_id=request['id'])
        check('Folder controls separate matching GET records observed without rewriting original outcome',
              'Requested folder observed after the request' in observed.text
              and 'does not prove this request caused the value or that website routing works' in observed.text
              and db('SELECT * FROM ci_provider_operations WHERE id=?', [ledger['id']]) == [ledger]
              and db('SELECT * FROM ci_provider_document_root_dispatch_ends WHERE id=?', [request['id']]) == [terminal])
        capture('Observed', '[data-document-root-control-history]')
        shared = client.get(target)
        check('Folder controls separately shared client sees observation without owner controls',
              desired in shared.text and 'Latest saved observation' in shared.text and change not in shared.text and request['id'] not in shared.text)
        check('Folder controls exactly six dedicated reads preserve all original history',
              [r['operation'] for r in db('SELECT operation FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [actor])] == ['packages.list', 'packages.list'] + ['package.document-roots'] * 4
              and db('SELECT * FROM ci_provider_refreshes WHERE seq<=? ORDER BY seq', [max((r['seq'] for r in inherited), default=0)]) == inherited
              and db('SELECT * FROM ci_audit WHERE id<=? ORDER BY id', [max((r['id'] for r in inherited_audits), default=0)]) == inherited_audits)
        check('Folder controls preserve original owner account and role after setup login',
              db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner]
              and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == original_profile)
        check('Folder controls captured all eight exact names without provider errors', tuple(captured) == CAPTURES and not provider.errors)
        for path in ('app/providers/document_root_controls.php', 'app/providers/document_root_controls_storage.php',
                     'app/providers/document_root_controls_views.php', 'database/schema/provider_document_root_controls_schema.php',
                     'admin/controllers/provider_document_root_controls.php'):
            check('Private website folder control route refuses GET and POST ' + path,
                  owner.get(base + '/existing/' + path).status_code == 404 and owner.post(base + '/existing/' + path, data={}).status_code == 404)
        return {'passed': True, 'checks': outcomes, 'captures': captured}
    finally:
        for key, value in old.items():
            setattr(provider, key, value)
        for value in sessions:
            value.close()
