"""Installed client intake and owner subdomain approval; synthetic TLS provider only.

Called after provider_schedule_cases in the existing packaged browser process.
All business changes use real forms. SQL below is read-only evidence, never setup.
"""
import copy
import json
import time
from urllib.parse import parse_qs, urljoin, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER


CAPTURES = (
    'Subdomain-Requests-Client-Pending-Desktop.png',
    'Subdomain-Requests-Client-Pending-Mobile.png',
    'Subdomain-Requests-Owner-Review-Desktop.png',
    'Subdomain-Requests-Owner-Review-Mobile.png',
    'Subdomain-Requests-Client-Observed-Desktop.png',
    'Subdomain-Requests-Client-Observed-Mobile.png',
)
REPORT = 'subdomain-requests-http-browser-results.json'
PARENT = 'subdomain-requests.synthetic.invalid'
CHILD = 'shop.' + PARENT
PACKAGE = 3101
CLIENT_PASSWORD = 'Synthetic client passphrase 2026'
SCOPE = 'Installed packaged PHP forms, native saved history and Chromium; closed synthetic TLS provider'


def run_browser(env, browser):
    base, qa, provider = (env[k] for k in ('BASE', 'QA', 'provider_server'))
    soup, token, form_data, form_for, submit = (env[k] for k in ('soup', 'token', 'form_data', 'form_for', 'submit'))
    admin = base + '/existing/admin/index.php'
    portal = base + '/existing/portal/index.php'
    settings = admin + '?view=provider-settings'
    owner_url = admin + '?view=subdomain-requests'
    client_url = portal + '?subdomain_requests=1'
    report_path = qa / REPORT
    checks, names, captures, sessions, contexts, script_errors = [], set(), [], [], [], []
    original = {key: copy.deepcopy(getattr(provider, key)) for key in (
        'mode', 'packages', 'document_roots', 'document_roots_mode',
        'subdomain_add_mode', 'subdomain_add_package_id')}

    def check(label, condition):
        name = 'Client subdomain installed: ' + label
        if name in names:
            raise AssertionError('Duplicate installed outcome name')
        names.add(name)
        checks.append({'name': name, 'passed': bool(condition)})
        env['check'](name, bool(condition))
        if not condition:
            raise AssertionError(name)

    def session():
        value = requests.Session()
        sessions.append(value)
        return value

    def get(client, address):
        return client.get(address, timeout=30)

    def actual_form(response, action, field=None, value=None):
        matches = []
        for form in soup(response).select('form'):
            a = form.select_one('[name=action]')
            f = form.select_one('[name=' + field + ']') if field else None
            if a and a.get('value') == action and (field is None or (f and f.get('value') == str(value))):
                matches.append(form)
        if len(matches) != 1:
            raise AssertionError('Expected one actual action form')
        return matches[0]

    def fields(response, action, field=None, value=None):
        return form_data(actual_form(response, action, field, value))

    def post_form(client, address, action, changes=None, field=None, value=None):
        page = get(client, address)
        form = actual_form(page, action, field, value)
        data = {**form_data(form), **(changes or {})}
        return client.post(urljoin(page.url, form.get('action') or page.url), data=data, timeout=40)

    def post_exact(client, address, data):
        return client.post(address, data=data, timeout=40)

    def private_headers(response):
        return 'no-store' in response.headers.get('Cache-Control', '') and response.headers.get('Referrer-Policy') == 'no-referrer'

    def detail(owner, request_id):
        return (owner_url if owner else client_url) + '&request_id=' + request_id

    tables = ('provider_subdomain_requests_events', 'provider_subdomain_add_requests',
              'provider_subdomain_add_terminals', 'provider_subdomain_add_checks')

    def local_state():
        state = {}
        for table in tables:
            exists = bool(db('SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', ['ci_' + table]))
            state[table] = db('SELECT * FROM ci_' + table + ' ORDER BY id') if exists else None
        state['markers'] = db("SELECT name,value FROM ci_settings WHERE name LIKE 'provider_subdomain_%' ORDER BY name")
        state['grants'] = db('SELECT * FROM ci_provider_operation_grants ORDER BY id')
        state['ledger'] = db('SELECT * FROM ci_provider_operations ORDER BY id')
        state['audits'] = db("SELECT * FROM ci_audit WHERE action LIKE 'provider.subdomain_%' ORDER BY id")
        state['refreshes'] = db('SELECT * FROM ci_provider_refreshes ORDER BY id')
        state['results'] = db('SELECT * FROM ci_provider_refresh_results ORDER BY id')
        return state

    def read_boundary():
        return (provider.calls, copy.deepcopy(provider.posts), local_state())

    def events(request_id=None):
        if request_id is None:
            return db('SELECT * FROM ci_provider_subdomain_requests_events ORDER BY ordinal')
        return db('SELECT * FROM ci_provider_subdomain_requests_events WHERE subject_id=? ORDER BY ordinal', [request_id])

    def safe_client(response, forbidden=()):
        html = response.text
        return not any(v in html for v in (
            'SYNTHETIC_RAW_GENERAL_KEY', 'SYNTHETIC_RAW_AUTH_KEY',
            'name="review_proof"', 'name="owner_request_id"', 'name="current_password"',
            'subdomain-private-folder', *forbidden))

    def activity_address(is_owner=False):
        return admin + '?view=activity' if is_owner else portal + '?activity=1'

    def activity_rows(client, is_owner=False):
        """Follow the actual bounded page links; never infer an off-page item."""
        address = activity_address(is_owner)
        rows, visited = {}, set()
        for _ in range(20):
            if address in visited:
                raise AssertionError('Activity cursor repeated')
            visited.add(address)
            response = get(client, address)
            if response.status_code != 200:
                raise AssertionError('Activity page unavailable')
            document = soup(response)
            page_rows = document.select('[data-event-key]')
            if len(page_rows) > 30:
                raise AssertionError('Activity page exceeded bound')
            for row in page_rows:
                key = row['data-event-key']
                if key in rows:
                    raise AssertionError('Activity event repeated across pages')
                rows[key] = row
            older = document.find('a', string='Older updates →')
            if not older:
                return rows
            address = urljoin(response.url, older['href'])
            parsed = urlparse(address)
            if parsed.netloc != urlparse(base).netloc or parsed.path != urlparse(activity_address(is_owner)).path:
                raise AssertionError('Activity cursor escaped its local route')
        raise AssertionError('Activity pagination exceeded fixture bound')

    def activity_mark_form(client, keys, state='read', is_owner=False, csrf=None):
        address = activity_address(is_owner)
        page = get(client, address)
        return post_exact(client, address, {'action': 'activity_mark', 'csrf': token(page) if csrf is None else csrf,
                                          'keys': json.dumps(keys), 'state': state})

    def activity_reads():
        return db('SELECT * FROM ci_activity_reads ORDER BY id')

    def cleared(response, action):
        form = actual_form(response, action)
        return all(n.get('value', '') == '' for n in form.select('input[type=password]')) and not form.select('input[type=checkbox][checked]')

    def browser_page(client):
        context = browser.new_context(viewport={'width': 1440, 'height': 1050})
        contexts.append(context)
        context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in client.cookies])
        page = context.new_page()
        page.on('pageerror', lambda error: script_errors.append(type(error).__name__))
        return page

    def cleanup():
        ok = True
        for key, value in original.items():
            setattr(provider, key, value)
        while contexts:
            try:
                contexts.pop().close()
            except Exception:
                ok = False
        while sessions:
            try:
                sessions.pop().close()
            except Exception:
                ok = False
        return ok

    def capture(client, prefix, address, selector, owner=False):
        page = browser_page(client)
        for width, height, suffix in ((1440, 1050, 'Desktop'), (320, 740, 'Mobile')):
            page.set_viewport_size({'width': width, 'height': height})
            response = page.goto(address, wait_until='load')
            page.evaluate('document.fonts.ready')
            target = page.locator(selector)
            expect(target).to_be_visible()
            if owner and width == 320:
                expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
            check(prefix + ' ' + suffix + ' original viewport and response',
                  response.status == 200 and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            # Keep real CSS/motion. Only grow the same-width capture viewport after
            # the original 320x740 predicate, so a tall form is legible in full.
            box = target.bounding_box()
            capture_height = max(height, int(box['height']) + 260)
            check(prefix + ' ' + suffix + ' bounded full-card capture height', capture_height <= 2600)
            page.set_viewport_size({'width': width, 'height': capture_height})
            target.evaluate('(e) => scrollTo(0, Math.max(0, e.getBoundingClientRect().top + scrollY - 150))')
            page.set_default_timeout(120000)
            page.wait_for_function("""(selector) => {
                const e=document.querySelector(selector), r=e.getBoundingClientRect();
                const nodes=[e.querySelector('h3'), ...e.querySelectorAll('button')].filter(n=>n&&n.getClientRects().length);
                return getComputedStyle(e).opacity==='1' && r.left>=0 && r.right<=innerWidth && r.top>=0 && r.bottom<=innerHeight &&
                    nodes.every(n=>{const b=n.getBoundingClientRect(), h=document.elementFromPoint(b.x+b.width/2,b.y+b.height/2);return h&&(h===n||n.contains(h));});
            }""", arg=selector)
            check(prefix + ' ' + suffix + ' full card and controls unobscured',
                  target.evaluate('(e)=>e.getBoundingClientRect().height>150') and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            name = prefix + '-' + suffix + '.png'
            target.screenshot(path=str(qa / name), animations='allow')
            check(prefix + ' ' + suffix + ' actual capture retained', (qa / name).is_file() and not (qa / name).is_symlink() and (qa / name).stat().st_size > 0)
            captures.append(name)
        page.context.close()
        contexts.remove(page.context)

    try:
        check('fresh output paths refuse prior files and broken symlinks',
              all(not (qa / name).exists() and not (qa / name).is_symlink() for name in (*CAPTURES, REPORT)))
        # Preserve every old row, including old assignments deliberately made stale
        # by the final schedule fixture. Only new records may be appended here.
        inherited_tables = ('provider_refreshes', 'provider_refresh_results', 'provider_observation_packages',
                            'provider_document_roots_reads', 'provider_document_roots_observations',
                            'provider_document_roots_permissions', 'provider_package_links',
                            'provider_package_link_events', 'provider_operation_grants', 'provider_operations', 'audit')
        inherited = {table: db('SELECT * FROM ci_' + table + ' ORDER BY id') for table in inherited_tables}
        check('dedicated package starts absent from saved assignments and provider fixture',
              not db('SELECT id FROM ci_provider_package_links WHERE package_id=?', [PACKAGE])
              and not any(int(p['id']) == PACKAGE for p in provider.packages))
        old_owner = db('SELECT * FROM ci_users WHERE email=?', ['owner@example.invalid'])[0]
        old_role = db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [old_owner['id']])
        setup = session()
        login = submit(setup, admin + '?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
        owner_after_login = db('SELECT * FROM ci_users WHERE id=?', [old_owner['id']])[0]
        check('setup login preserves original authority except normal login bookkeeping',
              login.status_code == 200 and parse_qs(urlparse(login.url).query).get('view') == ['dashboard']
              and {k: v for k, v in old_owner.items() if k != 'last_login'} == {k: v for k, v in owner_after_login.items() if k != 'last_login'}
              and old_role == db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [old_owner['id']]))
        email = 'subdomain-requests-owner@example.invalid'
        check('dedicated owner starts absent', not db('SELECT id FROM ci_users WHERE email=?', [email]))
        staff_url = admin + '?view=staff'
        submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'Subdomain requests acceptance owner',
               'email': email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
        owner_id = db('SELECT id FROM ci_users WHERE email=?', [email])[0]['id']
        invite = chosen_form(setup, staff_url, 'staff_invite', 'id', owner_id, {'owner_password': OWNER})
        invitation = soup(invite).select_one('[data-staff-invite]')['href']
        code = parse_qs(urlparse(invitation).fragment)['token'][0]
        activation = session()
        activation_page = get(activation, invitation)
        activated = post_exact(activation, admin + '?view=staff-setup', {'action': 'staff_activate', 'csrf': token(activation_page),
                               'token': code, 'password': OWNER, 'confirm_password': OWNER})
        check('real staff invitation activates a distinct enabled owner', activated.status_code == 200
              and parse_qs(urlparse(activated.url).query).get('view') == ['login'] and owner_id != old_owner['id']
              and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [owner_id]) == [{'role': 'owner', 'enabled': 1}])
        owner, alice, bob = session(), session(), session()
        logged = submit(owner, admin + '?view=login', 'login', {'email': email, 'password': OWNER})
        check('dedicated owner signs in normally with unused read budget', logged.status_code == 200
              and parse_qs(urlparse(logged.url).query).get('view') == ['dashboard']
              and not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?', [owner_id]))
        for client, email_value, label in ((alice, 'alice@example.invalid', 'primary client'), (bob, 'bob@example.invalid', 'other client')):
            response = submit(client, portal, 'login', {'email': email_value, 'password': CLIENT_PASSWORD})
            check(label + ' signs in through actual login', response.status_code == 200 and soup(response).select_one('form [name=action][value=logout]') is not None)
        client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']
        check('notification continuation retains actual earlier activity preparation',
              db("SELECT value FROM ci_settings WHERE name='activity_schema_version'") == [{'value': '1'}])
        profile = db('SELECT * FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
        before_get = read_boundary()
        page = get(owner, owner_url)
        client_page = get(alice, client_url)
        check('new direct owner and client pages are private and GET performs no setup',
              page.status_code == client_page.status_code == 200 and private_headers(page) and private_headers(client_page)
              and read_boundary() == before_get and before_get[2]['provider_subdomain_requests_events'] is None
              and before_get[2]['provider_subdomain_add_requests'] is None)
        anonymous = session()
        anonymous_page = get(anonymous, client_url)
        check('anonymous request link reaches sign-in without disclosure', anonymous_page.status_code == 200
              and soup(anonymous_page).select_one('[name=action][value=login]') is not None and not soup(anonymous_page).select('[data-subdomain-request-history]'))
        check('owner and client navigation expose actual new routes',
              any(parse_qs(urlparse(a.get('href', '')).query).get('view') == ['subdomain-requests'] for a in soup(get(owner, admin + '?view=dashboard')).select('a[href]'))
              and any(parse_qs(urlparse(a.get('href', '')).query).get('subdomain_requests') == ['1'] for a in soup(get(alice, portal + '?services=1')).select('a[href]')))
        # Prepare both existing owner additions and the new local intake explicitly.
        post_form(owner, settings, 'provider_inventory_subdomain_prepare', {'current_password': OWNER})
        local_before_prepare = read_boundary()
        bad_prepare = post_form(owner, owner_url, 'provider_subdomain_request_prepare', {'current_password': OWNER, 'reviewed': '1', 'csrf': 'invalid'})
        check('intake setup invalid CSRF makes no local or provider change', read_boundary() == local_before_prepare and bad_prepare.status_code in (200, 403))
        post_form(owner, owner_url, 'provider_subdomain_request_prepare', {'current_password': OWNER, 'reviewed': '1'})
        check('explicit owner setup creates empty local histories only', all(local_state()[table] == [] for table in tables)
              and provider.calls == before_get[0] and provider.posts == before_get[1])
        provider.mode = 'success'
        provider.document_roots_mode = 'success'
        provider.subdomain_add_package_id = PACKAGE
        provider.subdomain_add_mode = 'uncertain'
        provider.packages.append({'id': PACKAGE, 'typeRef': 12, 'name': PARENT, 'names': [PARENT],
                                  'enabled': True, 'created': '2000-01-01T00:00:00Z'})

        def cool():
            last = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes WHERE profile_id=?', [profile['id']])[0]['epoch']
            check_epoch = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_checks WHERE profile_id=?', [profile['id']])[0]['epoch']
            delay = max(0.0, max(int(last or 0), int(check_epoch or 0)) + 31 - time.time())
            if delay > 35:
                raise AssertionError('Retained provider cooldown exceeds bounded real wait')
            time.sleep(delay)

        def refresh_inventory():
            cool()
            return post_form(owner, settings, 'provider_inventory_refresh', {'current_password': OWNER})

        read_start = provider.calls
        get_paths_before = list(provider.get_paths)
        posts_before = copy.deepcopy(provider.posts)
        refresh_inventory()
        post_form(owner, settings, 'provider_inventory_link', {'client_id': str(client_id), 'current_password': OWNER}, 'package_id', PACKAGE)
        link = db('SELECT * FROM ci_provider_package_links WHERE package_id=? AND profile_id=?', [PACKAGE, profile['id']])[0]
        check('new assignment uses current enabled profile and primary client', link['client_id'] == client_id
              and link['profile_revision'] == profile['revision'] and int(profile['enabled']) == 1)
        refresh_inventory()
        link_ids = [row['id'] for row in db('SELECT id FROM ci_provider_package_links ORDER BY id')]
        map_url = settings + '&document_roots_page=' + str(link_ids.index(link['id']) // 20 + 1)

        def refresh_map():
            cool()
            return post_form(owner, map_url, 'provider_inventory_document_roots_refresh', {'current_password': OWNER}, 'link_id', link['id'])

        provider.document_roots = {PARENT: '../subdomain-private-folder/initial'}
        refresh_map()
        check('three real prerequisite reads use only the dedicated owner', provider.calls == read_start + 3
              and [r['operation'] for r in db('SELECT operation FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [owner_id])] == ['packages.list', 'packages.list', 'package.document-roots'])
        # Exercise support provenance before retaining an approval or its CSRF;
        # support start/end rotates the genuine admin session token.
        support_before = read_boundary()
        support = chosen_form(owner, admin + '?view=support', 'support_start', 'client_id', client_id, {})
        support_url = support.url
        check('real support session starts in original support route', parse_qs(urlparse(support_url).query).get('support') is not None)
        support_get = get(owner, support_url + '&subdomain_requests=1')
        support_post = post_exact(owner, support_url, {'action': 'subdomain_request_create', 'csrf': 'invalid', 'request_id': 'e' * 32})
        check('support GET and POST-only intake are refused before normalization', support_get.status_code == support_post.status_code == 403
              and PARENT not in support_get.text and read_boundary() == support_before)
        support_reads = activity_reads()
        support_activity = get(owner, support_url + '&activity=1')
        support_count = get(owner, base + '/existing/api/activity.php?audience=client&support=' + parse_qs(urlparse(support_url).query)['support'][0])
        check('notification center and passive count refuse actual support context',
              support_activity.status_code == 403 and support_count.status_code == 204
              and activity_reads() == support_reads and read_boundary() == support_before)
        submit(owner, support_url, 'support_end', {})
        check('support end restores direct owner request access', get(owner, owner_url).status_code == 200)

        def create_fields():
            return fields(get(alice, client_url), 'subdomain_request_create', 'parent_domain', PARENT)

        creation = create_fields()
        held = read_boundary()
        for label, changes, expected_name in (
            ('invalid CSRF', {'csrf': 'invalid', 'confirmed': '1'}, CHILD),
            ('missing confirmation', {}, CHILD),
            ('invalid exact hostname', {'confirmed': '1', 'subdomain': 'UPPER.' + PARENT}, 'UPPER.' + PARENT),
        ):
            data = {**creation, 'subdomain': CHILD, **changes}
            # No query string: the route must retain the POST-only inline retry.
            denied = post_exact(alice, portal, data)
            retry = fields(denied, 'subdomain_request_create')
            check(label + ' retains original form and makes no change', denied.status_code == 200
                  and soup(denied).select_one('.notice.error') is not None and read_boundary() == held
                  and all(retry[k] == creation[k] for k in ('request_id', 'link_id', 'review_sha256', 'parent_domain'))
                  and retry['subdomain'] == expected_name and 'confirmed' not in retry)
        cancel_data = {**create_fields(), 'subdomain': 'cancel.' + PARENT, 'confirmed': '1'}
        cancelled_id = cancel_data['request_id']
        created = post_exact(alice, client_url, cancel_data)
        check('first actual client request is review-only', created.status_code == 200 and len(events(cancelled_id)) == 1
              and events(cancelled_id)[0]['kind'] == 'request' and provider.calls == held[0] and provider.posts == held[1])
        cancel_form = fields(get(alice, detail(False, cancelled_id)), 'subdomain_request_cancel')
        cancel_data = {**cancel_form, 'confirmed': '1'}
        post_exact(alice, client_url, cancel_data)
        cancelled_rows = events(cancelled_id)
        replay = post_exact(alice, client_url, cancel_data)
        check('withdrawal and exact replay retain one cancellation', [r['kind'] for r in cancelled_rows] == ['request', 'cancel']
              and events(cancelled_id) == cancelled_rows and 'Request withdrawn' in replay.text)
        declined_data = {**create_fields(), 'subdomain': 'decline.' + PARENT, 'confirmed': '1'}
        declined_id = declined_data['request_id']
        post_exact(alice, client_url, declined_data)
        decline = fields(get(owner, detail(True, declined_id)), 'provider_subdomain_request_decline')
        decline = {**decline, 'note': 'Please discuss <website scope> with the studio.', 'reviewed': '1', 'current_password': OWNER}
        post_exact(owner, owner_url, decline)
        decline_rows = events(declined_id)
        post_exact(owner, owner_url, decline)
        declined_page = get(alice, detail(False, declined_id))
        check('owner decline and replay retain one escaped reason for the client', [r['kind'] for r in decline_rows] == ['request', 'decline']
              and events(declined_id) == decline_rows and 'Request declined' in declined_page.text
              and 'Please discuss &lt;website scope&gt; with the studio.' in declined_page.text and safe_client(declined_page))
        # The third intake and the only remote approval use the actual Chromium forms.
        cp = browser_page(alice)
        cp.goto(portal + '?services=1')
        with cp.expect_navigation(wait_until='load'):
            cp.get_by_role('link', name='Subdomain requests', exact=True).click()
        check('Chromium services navigation reaches actual request page', parse_qs(urlparse(cp.url).query).get('subdomain_requests') == ['1'])
        form = cp.locator('[data-subdomain-request-create-form]').filter(has=cp.locator('[name=parent_domain][value="' + PARENT + '"]'))
        selected = {k: form.locator('[name=' + k + ']').input_value() for k in ('action', 'csrf', 'request_id', 'link_id', 'review_sha256', 'parent_domain')}
        request_id = selected['request_id']
        form.locator('[name=subdomain]').fill(CHILD)
        form.locator('[name=confirmed]').check()
        before_create = read_boundary()
        with cp.expect_navigation(wait_until='load'):
            form.get_by_role('button', name='Request subdomain review', exact=True).click()
        expect(cp.locator('#subdomain-request-' + request_id)).to_contain_text('Awaiting studio review')
        after_create = local_state()
        check('Chromium primary-client intake appends only its event head and real client audit', len(events(request_id)) == 1
              and len(after_create['provider_subdomain_requests_events']) == len(before_create[2]['provider_subdomain_requests_events']) + 1
              and all(after_create[k] == before_create[2][k] for k in ('provider_subdomain_add_requests', 'provider_subdomain_add_terminals', 'provider_subdomain_add_checks', 'grants', 'ledger', 'refreshes', 'results'))
              and after_create['audits'][:-1] == before_create[2]['audits'] and after_create['audits'][-1]['user_id'] == 0
              and after_create['audits'][-1]['action'] == 'provider.subdomain_requests_event'
              and {r['name']: r['value'] for r in after_create['markers'] if r['name'] != 'provider_subdomain_requests_head'}
              == {r['name']: r['value'] for r in before_create[2]['markers'] if r['name'] != 'provider_subdomain_requests_head'}
              and after_create['markers'] != before_create[2]['markers']
              and provider.calls == before_create[0] and provider.posts == before_create[1])
        created_snapshot = read_boundary()
        post_exact(alice, client_url, {**selected, 'subdomain': CHILD, 'confirmed': '1'})
        check('exact client create replay sends nothing and appends nothing', read_boundary() == created_snapshot)
        duplicate = post_exact(alice, client_url, {**create_fields(), 'subdomain': CHILD, 'confirmed': '1'})
        check('second ID for same pending hostname is refused', 'A request for this hostname still needs studio review.' in duplicate.text and read_boundary() == created_snapshot)
        bob_detail = get(bob, detail(False, request_id))
        bob_cancel = fields(get(alice, detail(False, request_id)), 'subdomain_request_cancel')
        # Bob has no assigned parent website, so his request page renders no
        # form. The workspace sign-out form carries the same session token.
        real_token = token
        token = lambda response: real_token(get(bob, portal))
        bob_response = post_exact(bob, client_url, {**bob_cancel, 'csrf': token(get(bob, client_url)), 'confirmed': '1'})
        token = real_token
        check('other primary client cannot inspect or cancel captured request', bob_detail.status_code == bob_response.status_code == 200
              and CHILD not in bob_detail.text and CHILD not in bob_response.text
              and not soup(bob_detail).select('[data-subdomain-request-history]') and read_boundary() == created_snapshot)
        capture(alice, 'Subdomain-Requests-Client-Pending', detail(False, request_id), '#subdomain-request-' + request_id)
        notify_before, notify_reads = read_boundary(), activity_reads()
        owner_updates = activity_rows(owner, True)
        request_key = 'subdomain-request:' + request_id
        cancel_key = 'subdomain-request:' + cancelled_rows[-1]['id']
        decline_key = 'subdomain-request:' + decline_rows[-1]['id']
        expected_owner = {'subdomain-request:' + i for i in (cancelled_id, declined_id, request_id)} | {cancel_key}
        check('notification owner gets each request and cancellation exactly once despite replay',
              {k for k in owner_updates if k.startswith('subdomain-')} == expected_owner
              and 'Subdomain requested' in owner_updates[request_key].get_text(' ', strip=True)
              and 'Subdomain request withdrawn' in owner_updates[cancel_key].get_text(' ', strip=True))
        client_updates = activity_rows(alice)
        check('notification decline is safe and client does not receive its own request events',
              {k for k in client_updates if k.startswith('subdomain-')} == {decline_key}
              and 'Subdomain request declined' in client_updates[decline_key].get_text(' ', strip=True)
              and 'website scope' not in str(client_updates[decline_key])
              and not any(k.startswith('subdomain-') for k in activity_rows(bob)))
        np = browser_page(owner)
        np.goto(activity_address(True))
        with np.expect_navigation(wait_until='load'):
            np.locator('[data-event-key="' + request_key + '"] h2 a').click()
        check('notification Chromium owner link opens exact immutable request',
              parse_qs(urlparse(np.url).query).get('view') == ['subdomain-requests']
              and parse_qs(urlparse(np.url).query).get('request_id') == [request_id])
        check('notification listing paging and exact opening do not mark or send',
              activity_reads() == notify_reads and read_boundary() == notify_before)
        review = get(owner, detail(True, request_id))
        approval = fields(review, 'provider_subdomain_request_approve')
        check('owner exact detail contains client identity and separate signed approval',
              soup(review).select_one('[data-subdomain-request-client]') is not None and len(approval['owner_request_id']) == 32
              and len(approval['review_proof']) > 64 and safe_client(get(alice, detail(False, request_id)), (approval['review_proof'], approval['owner_request_id'])))
        before_approval = read_boundary()
        for label, changes, message in (
            ('approval invalid CSRF', {'csrf': 'invalid', 'reviewed': '1', 'current_password': OWNER}, None),
            ('approval missing confirmation', {'current_password': OWNER}, 'Confirm the request before continuing.'),
            ('approval wrong current password', {'reviewed': '1', 'current_password': 'not-the-owner-password'}, 'Your current password is not correct.'),
        ):
            refused = post_exact(owner, owner_url, {**approval, **changes})
            retained = fields(refused, 'provider_subdomain_request_approve')
            check(label + ' retains exact original approval without send', refused.status_code == 200
                  and soup(refused).select_one('.notice.error') is not None and (message is None or message in refused.text)
                  and all(retained[k] == approval[k] for k in ('request_id', 'expected_event', 'request_sha256', 'owner_request_id', 'review_proof'))
                  and cleared(refused, 'provider_subdomain_request_approve') and read_boundary() == before_approval)
        provider.document_roots = {PARENT: '../subdomain-private-folder/new-observation'}
        refresh_map()
        changed_context = read_boundary()
        refused = post_exact(owner, owner_url, {**approval, 'reviewed': '1', 'current_password': OWNER})
        stale = fields(refused, 'provider_subdomain_request_approve')
        check('fresh map makes captured old approval unavailable without replacing it', refused.status_code == 200
              and 'Refresh and review the latest subdomain map before changing it.' in refused.text
              and stale['owner_request_id'] == approval['owner_request_id'] and stale['review_proof'] == approval['review_proof']
              and cleared(refused, 'provider_subdomain_request_approve') and read_boundary() == changed_context)
        capture(owner, 'Subdomain-Requests-Owner-Review', detail(True, request_id), '#subdomain-request-' + request_id, True)
        op = browser_page(owner)
        op.goto(detail(True, request_id))
        form = op.locator('[data-subdomain-request-approve]')
        sent = {k: form.locator('[name=' + k + ']').input_value() for k in ('action', 'csrf', 'request_id', 'expected_event', 'request_sha256', 'owner_request_id', 'review_proof')}
        check('fresh review is visibly separate from the refused approval', sent['owner_request_id'] != approval['owner_request_id'] and sent['review_proof'] != approval['review_proof'])
        before_send = (provider.calls, len(provider.posts), len(provider.subdomain_add_posts), copy.deepcopy(provider.document_roots))
        form.locator('[name=reviewed]').check()
        form.locator('[name=current_password]').fill(OWNER)
        with op.expect_navigation(wait_until='load'):
            form.get_by_role('button', name='Approve and request subdomain', exact=True).click()
        expect(op.locator('#subdomain-request-' + request_id)).to_contain_text('Subdomain requested; result not yet confirmed')
        native_id = sent['owner_request_id']
        native = db('SELECT * FROM ci_provider_subdomain_add_requests WHERE id=?', [native_id])[0]
        native_document = json.loads(native['document_json'])
        exact_body = {'add': [CHILD], 'rem': []}
        check('one exact signed Chromium approval enters actual fixed TLS transport', len(provider.posts) == before_send[1] + 1
              and len(provider.subdomain_add_posts) == before_send[2] + 1
              and provider.subdomain_add_posts[-1] == {'path': '/package/3101/web/subdomains', 'body': exact_body, 'raw': json.dumps(exact_body, separators=(',', ':'))}
              and native_document['review_proof'] == sent['review_proof'] and native_document['version'] == 2
              and native_document['client_origin'] == {'id': request_id, 'request_sha256': sent['request_sha256']}
              and provider.document_roots == before_send[3])
        ledger = db('SELECT status,attempts FROM ci_provider_operations WHERE idempotency_key=?', ['subdomainadd:' + native_id])
        check('uncertain response retains one finished attempt without observed success', ledger == [{'status': 'uncertain', 'attempts': 1}]
              and db('SELECT disposition FROM ci_provider_subdomain_add_terminals WHERE id=?', [native_id]) == [{'disposition': 'attempted'}]
              and [r['kind'] for r in events(request_id)] == ['request', 'dispatch'])
        sent_state = read_boundary()
        post_exact(owner, owner_url, {**sent, 'reviewed': '1', 'current_password': OWNER})
        check('exact owner approval replay never sends or records another attempt', read_boundary() == sent_state)
        pending = get(alice, detail(False, request_id))
        check('client sees uncertain progress without private map or owner proof',
              'Subdomain requested; result not yet confirmed' in pending.text and 'does not confirm that the subdomain is ready to use' in pending.text
              and safe_client(pending, (native_id, sent['review_proof'])))
        uncertain_updates = activity_rows(alice)
        terminal_key = 'subdomain-terminal:' + native_id
        dispatch_key = 'subdomain-request:' + native_id
        check('notification finished uncertain send stays distinct from review and later observation',
              {k for k in uncertain_updates if k.startswith('subdomain-')} == {decline_key, dispatch_key, terminal_key}
              and 'Subdomain request reviewed' in uncertain_updates[dispatch_key].get_text(' ', strip=True)
              and 'Subdomain result not confirmed' in uncertain_updates[terminal_key].get_text(' ', strip=True)
              and 'may have been sent' in uncertain_updates[terminal_key].get_text(' ', strip=True)
              and not any(k.startswith('subdomain-check:') for k in uncertain_updates))
        notification_bytes = ''.join(str(row) for row in uncertain_updates.values())
        check('notification metadata omits paths credentials decline notes and review proofs',
              all(value not in notification_bytes for value in ('subdomain-private-folder', 'website scope', sent['review_proof'],
                                                               sent['request_sha256'], 'SYNTHETIC_RAW_GENERAL_KEY', 'SYNTHETIC_RAW_AUTH_KEY')))
        notify_before, notify_reads = read_boundary(), activity_reads()
        legacy_key = next(k for k, row in uncertain_updates.items() if not k.startswith('subdomain-')
                          and row.select_one('.workspace-status').get_text(strip=True) == 'Unread')
        invalid_mark = activity_mark_form(alice, [legacy_key, terminal_key], csrf='invalid')
        check('notification mixed mark rejects invalid CSRF without read state changes',
              'session expired' in invalid_mark.text.lower() and activity_reads() == notify_reads)
        forbidden_mark = activity_mark_form(alice, [legacy_key, request_key])
        check('notification mixed inaccessible provider mark is atomic for legacy item',
              forbidden_mark.status_code == 200 and 'Activity changed or is unavailable.' in forbidden_mark.text
              and activity_reads() == notify_reads)
        activity_mark_form(alice, [legacy_key, terminal_key])
        marked = activity_reads()
        activity_mark_form(alice, [legacy_key, terminal_key])
        check('notification mixed mark and exact retry retain one row and first timestamp each',
              len(marked) == len(notify_reads) + 2 and activity_reads() == marked
              and {r['event_key'] for r in marked if r not in notify_reads} == {legacy_key, terminal_key})
        check('notification marking does not change another audience or original business history',
              [r for r in marked if r['actor_type'] == 'admin'] == [r for r in notify_reads if r['actor_type'] == 'admin']
              and read_boundary() == notify_before)
        activity_mark_form(alice, [legacy_key, terminal_key], 'unread')
        check('notification explicit unread restores original read rows exactly', activity_reads() == notify_reads)
        foreign_open = get(bob, activity_address() + '&open=' + terminal_key)
        foreign_mark = activity_mark_form(bob, [terminal_key])
        check('notification other primary account cannot open or mark captured provider key',
              foreign_open.status_code == 403 and CHILD not in foreign_open.text and CHILD not in foreign_mark.text
              and activity_reads() == notify_reads and read_boundary() == notify_before)
        cool()
        before_absent = provider.calls
        absent = post_form(owner, detail(True, request_id), 'provider_subdomain_request_verify', {'current_password': OWNER})
        check('independent absent readback retains uncertainty and exact new receipt', provider.calls == before_absent + 1
              and provider.get_paths[-1] == '/package/3101/web/documentRoots'
              and db('SELECT disposition FROM ci_provider_subdomain_add_checks WHERE request_id=?', [native_id]) == [{'disposition': 'different'}]
              and 'Requested name not reported' in absent.text and 'Subdomain requested; result not yet confirmed' in absent.text
              and len(provider.subdomain_add_posts) == before_send[2] + 1)
        absent_client = get(alice, detail(False, request_id))
        check('client absent result remains safely unconfirmed', 'Requested name not reported' in absent_client.text
              and 'Subdomain requested; result not yet confirmed' in absent_client.text and safe_client(absent_client, (native_id,)))
        provider.document_roots = {**provider.document_roots, CHILD: '../subdomain-private-folder/observed-child'}
        cool()
        before_observed = provider.calls
        observed = post_form(owner, detail(True, request_id), 'provider_subdomain_request_verify', {'current_password': OWNER})
        check_rows = db('SELECT c.disposition,r.seq FROM ci_provider_subdomain_add_checks c JOIN ci_provider_refreshes r ON r.id=c.receipt_id WHERE c.request_id=? ORDER BY r.seq', [native_id])
        check('separate matching readback records observation without second add', provider.calls == before_observed + 1
              and [r['disposition'] for r in check_rows] == ['different', 'observed'] and check_rows[1]['seq'] > check_rows[0]['seq']
              and 'Subdomain appeared in a later check' in observed.text and len(provider.subdomain_add_posts) == before_send[2] + 1)
        observed_client = get(alice, detail(False, request_id))
        check('client observed status preserves limits and omits owner/private fields', safe_client(observed_client, (native_id, sent['review_proof']))
              and 'Subdomain appeared in a later check' in observed_client.text and 'DNS, certificate and website availability have not been checked.' in observed_client.text
              and not soup(observed_client).select('[data-subdomain-request-cancel],[data-subdomain-request-verify]'))
        capture(alice, 'Subdomain-Requests-Client-Observed', detail(False, request_id), '#subdomain-request-' + request_id)
        notify_before, notify_reads = read_boundary(), activity_reads()
        all_updates = activity_rows(alice)
        native_checks = db('SELECT id,disposition FROM ci_provider_subdomain_add_checks WHERE request_id=? ORDER BY id', [native_id])
        observed_key = 'subdomain-check:' + next(r['id'] for r in native_checks if r['disposition'] == 'observed')
        different_key = 'subdomain-check:' + next(r['id'] for r in native_checks if r['disposition'] == 'different')
        check('notification independent readbacks preserve uncertain event and exact separate keys',
              {k for k in all_updates if k.startswith('subdomain-')} == {decline_key, dispatch_key, terminal_key, observed_key, different_key}
              and 'Subdomain result not confirmed' in all_updates[terminal_key].get_text(' ', strip=True)
              and 'Subdomain not observed in this check' in all_updates[different_key].get_text(' ', strip=True)
              and 'Subdomain observed in a later check' in all_updates[observed_key].get_text(' ', strip=True)
              and 'does not confirm DNS or website readiness' in all_updates[observed_key].get_text(' ', strip=True))
        order = [(row.select_one('time').get_text(strip=True), key) for key, row in all_updates.items()]
        check('notification combined HTTP pages retain strict time and key order', order == sorted(order, reverse=True)
              and any(not key.startswith('subdomain-') for key in all_updates) and len(order) > 30)
        nc = browser_page(alice)
        nc.goto(activity_address())
        with nc.expect_navigation(wait_until='load'):
            nc.locator('[data-event-key="' + observed_key + '"] h2 a').click()
        check('notification Chromium client readback link selects exact request detail',
              parse_qs(urlparse(nc.url).query).get('subdomain_requests') == ['1']
              and parse_qs(urlparse(nc.url).query).get('request_id') == [request_id]
              and nc.locator('#subdomain-request-' + request_id).is_visible())
        check('notification final feed and exact navigation are read only with original read state',
              activity_reads() == notify_reads and read_boundary() == notify_before)
        reads = db('SELECT id,operation FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [owner_id])
        results = db('SELECT r.state FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id WHERE f.actor_id=? ORDER BY f.seq', [owner_id])
        check('exact six successful real reads stay within fresh owner budget', [r['operation'] for r in reads] == ['packages.list', 'packages.list'] + ['package.document-roots'] * 4
              and results == [{'state': 'complete'}] * 6 and provider.calls == read_start + 7
              and provider.get_paths == get_paths_before + ['/package', '/package'] + ['/package/3101/web/documentRoots'] * 4
              and provider.posts == posts_before + [{'path': '/package/3101/web/subdomains', 'body': exact_body}])
        check('three intakes retain complete cancel decline and dispatch histories',
              [r['kind'] for r in events()] == ['request', 'cancel', 'request', 'decline', 'request', 'dispatch']
              and len(local_state()['provider_subdomain_add_requests']) == 1 and len(local_state()['provider_subdomain_add_terminals']) == 1)
        for table, rows in inherited.items():
            current = {row['id']: row for row in db('SELECT * FROM ci_' + table + ' ORDER BY id')}
            check('retains every inherited ' + table + ' row byte-for-field', all(current.get(row['id']) == row for row in rows))
        check('original owner authority and current provider profile stay exact',
              db('SELECT * FROM ci_users WHERE id=?', [old_owner['id']]) == [owner_after_login]
              and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [old_owner['id']]) == old_role
              and db('SELECT * FROM ci_provider_profiles WHERE id=?', [profile['id']]) == [profile])
        check('all required real captures and clean browser/provider diagnostics', captures == list(CAPTURES) and not script_errors and not provider.errors)
        check('fixture controls and authenticated browser sessions close cleanly', cleanup()
              and all(getattr(provider, key) == value for key, value in original.items()))
        result = {'passed': True, 'scope': SCOPE, 'checks': checks, 'captures': captures}
        report_path.write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
        return result
    except Exception as exc:
        # Never publish response bodies, tokens, SQL, addresses or exception text.
        # Preserve partial actual outcomes; a failed run cannot become a producer pass.
        if not report_path.exists() and not report_path.is_symlink():
            failure = {'passed': False, 'scope': SCOPE, 'checks': checks, 'captures': captures,
                       'error_class': type(exc).__name__ if type(exc).__name__ in ('AssertionError', 'TimeoutError', 'Error', 'KeyError', 'IndexError', 'ValueError', 'RuntimeError', 'ConnectionError') else 'UnexpectedError'}
            report_path.write_text(json.dumps(failure, indent=2) + '\n', encoding='utf-8')
        raise
    finally:
        # On failure this cannot replace the original exception or its failed JSON.
        cleanup()
