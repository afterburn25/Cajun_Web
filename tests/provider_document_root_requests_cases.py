"""Installed client website-folder requests; disposable SQL and synthetic 20i only."""
import math
import json
import time
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER


CAPTURES = tuple('Website-Folder-Requests-' + state + '-' + size + '.png'
                 for state in ('Client-Pending', 'Owner-Review', 'Owner-Readback', 'Client-Observed')
                 for size in ('Desktop', 'Mobile'))
RETAINED_TABLES = ('provider_document_root_changes', 'provider_document_root_dispatch_ends',
                   'provider_document_root_change_checks', 'provider_operation_grants',
                   'provider_operations', 'provider_refreshes', 'audit')
FINANCIAL_TABLES = ('studio_invoices', 'catalog_invoice_snapshots', 'payment_requests',
                    'payment_attempts', 'payment_attempt_events', 'invoice_payments')


def retained_rows(before, after):
    """Every original row must survive byte-for-byte; new rows may be appended."""
    return isinstance(before, list) and isinstance(after, list) and all(row in after for row in before)


def capture_complete(result, qa):
    if not isinstance(result, dict) or result.get('passed') is not True:
        return False
    checks = result.get('checks')
    if not isinstance(checks, list) or not checks:
        return False
    if any(not isinstance(row, dict) or set(row) != {'name', 'passed'}
           or not isinstance(row['name'], str) or not row['name'] or row['passed'] is not True for row in checks):
        return False
    if len({row['name'] for row in checks}) != len(checks) or tuple(result.get('captures', ())) != CAPTURES:
        return False
    return all((qa / name).is_file() and not (qa / name).is_symlink() and (qa / name).stat().st_size > 0 for name in CAPTURES)


def run_browser(env, browser):
    base, qa, provider = (env[key] for key in ('BASE', 'QA', 'provider_server'))
    soup, token, submit = (env[key] for key in ('soup', 'token', 'submit'))
    checks, captures, sessions = [], [], []
    original = {name: db('SELECT * FROM ci_' + name) for name in RETAINED_TABLES}
    financial = {name: db('SELECT * FROM ci_' + name + ' ORDER BY id') for name in FINANCIAL_TABLES}
    saved_provider = {key: getattr(provider, key) for key in
                      ('mode', 'write_mode', 'packages', 'document_roots', 'document_roots_mode')}
    original_posts = list(provider.posts)
    admin = base + '/existing/admin/index.php'
    settings = admin + '?view=provider-settings'
    queue = admin + '?view=folder-requests'
    portal = base + '/existing/portal/index.php'
    client_page = portal + '?folder_requests=1'

    def check(name, passed):
        name = 'Client website folders: ' + name
        if any(row['name'] == name for row in checks):
            raise AssertionError('Duplicate installed client folder outcome')
        checks.append({'name': name, 'passed': bool(passed)})
        env['check'](name, passed)

    def session():
        value = requests.Session(); sessions.append(value); return value

    def form_fields(response, action, match=None):
        forms = [node for node in soup(response).select('form')
                 if node.select_one('input[name=action][value="' + action + '"]')]
        if match:
            forms = [node for node in forms if all(node.select_one('[name="' + key + '"]') is not None
                     and node.select_one('[name="' + key + '"]').get('value') == value for key, value in match.items())]
        if len(forms) != 1:
            raise AssertionError('Expected one real installed form for ' + action)
        return {node['name']: node.get('value', '') for node in forms[0].select('input[name]')
                if node.get('type') not in ('checkbox', 'radio') or node.has_attr('checked')}

    def post_form(actor, url, action, **extra):
        response = actor.get(url)
        fields = form_fields(response, action)
        fields.update(extra)
        return actor.post(url, data=fields), fields

    def capture(actor, url, state, selector, owner=False):
        context = browser.new_context(); errors = []
        try:
            context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain,
                                 'path': c.path, 'secure': c.secure} for c in actor.cookies])
            page = context.new_page(); page.on('pageerror', lambda error: errors.append(str(error)))
            for width, height, size in ((1440, 1050, 'Desktop'), (320, 740, 'Mobile')):
                name = 'Website-Folder-Requests-' + state + '-' + size + '.png'
                destination = qa / name
                if destination.exists() or destination.is_symlink():
                    raise AssertionError('Never overwrite an installed folder capture')
                page.set_viewport_size({'width': width, 'height': height}); navigation = page.goto(url)
                page.evaluate('document.fonts.ready')
                card = page.locator(selector); expect(card).to_have_count(1); expect(card).to_be_visible()
                if owner and width == 320:
                    expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
                check(state + ' ' + size + ' original viewport has no horizontal overflow',
                      navigation.status == 200 and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
                required = math.ceil(card.bounding_box()['height']) + 200
                if required > 3600:
                    raise AssertionError('Installed request card exceeds bounded capture height')
                page.set_viewport_size({'width': width, 'height': max(height, required)})
                card.evaluate('''el => {const top=document.querySelector('.admin-topbar')||document.querySelector('.site-header');window.scrollBy(0,el.getBoundingClientRect().top-(top?top.getBoundingClientRect().height:0)-20);}''')
                expect(card).to_be_in_viewport(ratio=1)
                clear = card.evaluate('''el => {const r=el.getBoundingClientRect();return [0.08,0.5,0.92].every(x=>[0.03,0.5,0.97].every(y=>{const n=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);return n&&(n===el||el.contains(n));}));}''')
                check(state + ' ' + size + ' entire captured card is unobscured', clear)
                card.screenshot(path=str(destination)); captures.append(name)
            check(state + ' actual page has no script errors', not errors)
        finally:
            context.close()

    try:
        # Use a real invitation for an independent owner quota; do not reset prior history.
        setup = session(); submit(setup, admin + '?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
        email = 'client-folder-requests-owner@example.invalid'
        check('dedicated owner starts absent', not db('SELECT id FROM ci_users WHERE email=?', [email]))
        staff_url = admin + '?view=staff'
        submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'Client website folder owner',
               'email': email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
        owner_id = db('SELECT id FROM ci_users WHERE email=?', [email])[0]['id']
        invited = chosen_form(setup, staff_url, 'staff_invite', 'id', owner_id, {'owner_password': OWNER})
        invite_url = soup(invited).select_one('[data-staff-invite]')['href']
        code = parse_qs(urlparse(invite_url).fragment)['token'][0]
        activation = session(); page = activation.get(invite_url)
        activated = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate',
                     'csrf': token(page), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
        check('real invitation activates independent owner', activated.status_code == 200
              and parse_qs(urlparse(activated.url).query).get('view') == ['login']
              and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [owner_id]) == [{'role': 'owner', 'enabled': 1}])
        owner = session(); submit(owner, admin + '?view=login', 'login', {'email': email, 'password': OWNER})
        client = session(); submit(client, portal, 'login', {'email': 'alice@example.invalid', 'password': 'Synthetic client passphrase 2026'})
        foreign = session(); submit(foreign, portal, 'login', {'email': 'bob@example.invalid', 'password': 'Synthetic client passphrase 2026'})
        profile = db('SELECT id,revision FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
        client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']

        def cool():
            epochs = [db('SELECT MAX(started_epoch) AS epoch FROM ci_' + table + ' WHERE profile_id=?', [profile['id']])[0]['epoch']
                      for table in ('provider_refreshes', 'provider_checks')]
            time.sleep(max(0, max(int(value or 0) for value in epochs) + 31 - time.time()))

        def settings_post(action, **extra):
            page = owner.get(settings)
            fields = {'csrf': token(page), 'action': action, 'profile_id': profile['id'],
                      'revision': str(profile['revision']), 'current_password': OWNER}
            for candidate in soup(page).select('form'):
                if not candidate.select_one('input[name=action][value="' + action + '"]'):
                    continue
                if 'link_id' in extra and candidate.select_one('input[name=link_id]') is not None and candidate.select_one('input[name=link_id]').get('value') != extra['link_id']:
                    continue
                fields.update({node['name']: node.get('value', '') for node in candidate.select('input[name]')}); break
            fields.update(current_password=OWNER); fields.update(extra)
            return owner.post(settings, data=fields)

        calls = provider.calls
        prepare = 'provider_folder_request_prepare'
        check('GET offers explicit preparation without creating history or provider traffic', prepare in owner.get(queue).text
              and not db('SHOW TABLES LIKE "ci_provider_website_folder_requests_events"') and provider.calls == calls)
        for label, invalid in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'})]:
            post_form(owner, queue, prepare, **invalid)
            check('invalid ' + label + ' cannot prepare history', not db('SHOW TABLES LIKE "ci_provider_website_folder_requests_events"') and provider.calls == calls)
        post_form(owner, queue, prepare, current_password=OWNER)
        check('explicit preparation creates empty local journal', bool(db('SHOW TABLES LIKE "ci_provider_website_folder_requests_events"'))
              and not db('SELECT id FROM ci_provider_website_folder_requests_events') and provider.calls == calls)

        hostname = 'client-folder.synthetic.invalid'; desired = 'public_html/client-website'
        old_path = '../legacy/<private-folder> & café'
        check('dedicated package starts absent', not db('SELECT id FROM ci_provider_package_links WHERE package_id=2801'))
        provider.mode = 'success'; provider.document_roots_mode = 'success'
        provider.packages = [*saved_provider['packages'], {'id': 2801, 'typeRef': 12, 'name': hostname,
                             'names': [hostname], 'enabled': True, 'created': '2000-01-01T00:00:00Z'}]
        cool(); settings_post('provider_inventory_refresh')
        settings_post('provider_inventory_link', package_id='2801', client_id=str(client_id))
        link = db('SELECT * FROM ci_provider_package_links WHERE profile_id=? AND package_id=2801', [profile['id']])[0]
        cool(); settings_post('provider_inventory_refresh')
        provider.document_roots = {hostname: old_path}
        cool(); settings_post('provider_inventory_document_roots_refresh', link_id=link['id'])
        settings_post('provider_inventory_document_roots_share', link_id=link['id'], sharing='enable')
        # Remaining interactions use only the public installed request forms.
        return run_request_forms(locals())
    finally:
        for key, value in saved_provider.items():
            setattr(provider, key, value)
        for actor in sessions:
            actor.close()


def run_request_forms(state):
    (check, client, foreign, owner, client_page, queue, form_fields, post_form, soup, provider,
     hostname, desired, old_path, link, cool, settings_post, capture, original_posts, original,
     captures, checks, qa, base) = (state[key] for key in
        ('check', 'client', 'foreign', 'owner', 'client_page', 'queue', 'form_fields', 'post_form', 'soup', 'provider',
         'hostname', 'desired', 'old_path', 'link', 'cool', 'settings_post', 'capture', 'original_posts', 'original',
         'captures', 'checks', 'qa', 'base'))

    def journal():
        return db('SELECT * FROM ci_provider_website_folder_requests_events ORDER BY ordinal')

    def details(actor, request_id, owner_view=False):
        return actor.get((queue if owner_view else client_page) + '&request=' + request_id)

    def create():
        fields = form_fields(client.get(client_page), 'folder_request_create', {'link_id': link['id']})
        fields.update(name=hostname, desired_path=desired, confirmed='1')
        response = client.post(client_page, data=fields)
        check('client creates request ' + fields['request_id'], response.status_code == 200
              and soup(response).select_one('[data-folder-request-item="' + fields['request_id'] + '"]') is not None)
        return fields, response

    calls = provider.calls
    page = client.get(client_page)
    first = form_fields(page, 'folder_request_create', {'link_id': link['id']})
    check('primary client sees assigned shared map and exact escaped original path',
          hostname in page.text and old_path in soup(page).text and '<private-folder>' not in page.text)
    for label, invalid in [('CSRF', {'csrf': 'invalid'}), ('untrimmed path', {'desired_path': desired + ' '}),
                           ('parent traversal', {'desired_path': '../outside'}), ('unshared hostname', {'name': 'foreign.synthetic.invalid'}),
                           ('missing confirmation', {'confirmed': '0'})]:
        submitted = {**first, 'name': hostname, 'desired_path': desired, 'confirmed': '1', **invalid}
        response = client.post(client_page, data=submitted)
        check('invalid client ' + label + ' keeps history empty and sends nothing', bool(soup(response).select('.notice.error'))
              and journal() == [] and provider.calls == calls)
        if label == 'untrimmed path':
            retry = soup(response).select_one('[data-folder-request-create]')
            check('invalid exact path and original context remain for correction', retry is not None
                  and retry.select_one('[name=desired_path]')['value'] == desired + ' '
                  and retry.select_one('[name=request_id]')['value'] == first['request_id']
                  and retry.select_one('[name=review_sha256]')['value'] == first['review_sha256']
                  and not retry.select_one('[name=confirmed]').has_attr('checked'))
    cancelled_fields, response = create(); cancelled_id = cancelled_fields['request_id']
    cancelled, cancel_fields = post_form(client, client_page + '&request=' + cancelled_id, 'folder_request_cancel')
    before_replay = journal(); audits = db('SELECT * FROM ci_audit ORDER BY id')
    client.post(client_page + '&request=' + cancelled_id, data=cancel_fields)
    check('client withdrawal and exact replay never send or append twice', 'Withdrawn by client' in cancelled.text
          and len(before_replay) == 2 and journal() == before_replay and db('SELECT * FROM ci_audit ORDER BY id') == audits and provider.calls == calls)

    declined_fields, _ = create(); declined_id = declined_fields['request_id']
    note = 'Please upload the agreed site files before changing the folder.'
    declined, decline_fields = post_form(owner, queue + '&request=' + declined_id, 'provider_folder_request_decline', note=note, current_password=OWNER)
    before_replay = journal(); owner.post(queue + '&request=' + declined_id, data=decline_fields)
    check('owner decline appears to client and exact replay stays local', 'Declined by studio' in declined.text
          and note in details(client, declined_id).text and len(before_replay) == 4 and journal() == before_replay and provider.calls == calls)

    fields, pending = create(); request_id = fields['request_id']
    client_detail, owner_detail = client_page + '&request=' + request_id, queue + '&request=' + request_id
    selector = '[data-folder-request-item="' + request_id + '"]'
    original_request = journal()[-1]; request_document = json.loads(original_request['document'])
    check('pending intent retains selected original path and exact destination', request_document['kind'] == 'request'
          and request_document['payload']['name'] == hostname and request_document['payload']['before_path'] == old_path
          and request_document['payload']['desired_path'] == desired and provider.calls == calls)
    before_replay = journal(); client.post(client_page, data=fields)
    check('exact pending client replay does not append history', journal() == before_replay and provider.calls == calls)
    foreign_response = foreign.get(client_detail)
    check('another client cannot discover request details', request_id not in str(soup(foreign_response).select('[data-folder-request-item]'))
          and hostname not in foreign_response.text and desired not in foreign_response.text)
    check('support query refuses client mutation route before rendering', client.get(client_page + '&support=fixture').status_code == 403)
    capture(client, client_detail, 'Client-Pending', selector)

    # A newer observation changes the owner's before-value, never the submitted intent.
    fresh_path = 'public_html/current-owner-review'
    provider.document_roots = {hostname: fresh_path}
    cool(); settings_post('provider_inventory_document_roots_refresh', link_id=link['id'])
    review = owner.get(owner_detail)
    approval = form_fields(review, 'provider_folder_request_approve')
    check('owner review separates original client path from a fresh latest map', old_path in soup(review).text
          and fresh_path in soup(review).text and desired in soup(review).text
          and json.loads(journal()[-1]['document']) == request_document
          and approval['anchor_id'] != request_document['payload']['resource']['observation']['id'])
    capture(owner, owner_detail, 'Owner-Review', selector, owner=True)
    approval.update(current_password=OWNER, reviewed='1')
    writes = db('SELECT * FROM ci_provider_document_root_changes')
    calls = provider.calls
    for label, invalid in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'}),
                           ('attestation', {'reviewed': '0'}), ('stale observation', {'anchor_id': '0' * 32})]:
        response = owner.post(owner_detail, data={**approval, **invalid})
        check('invalid owner ' + label + ' cannot approve or send', bool(soup(response).select('.notice.error'))
              and db('SELECT * FROM ci_provider_document_root_changes') == writes and journal() == before_replay and provider.calls == calls)
        if label == 'password':
            retry = soup(response).select_one('[data-folder-request-approve]')
            check('failed owner password retains exact original approval and clears consent', retry is not None
                  and retry.select_one('[name=owner_request_id]')['value'] == approval['owner_request_id']
                  and retry.select_one('[name=anchor_id]')['value'] == approval['anchor_id']
                  and retry.select_one('[name=request_sha256]')['value'] == approval['request_sha256']
                  and retry.select_one('[name=current_password]').get('value', '') == ''
                  and not retry.select_one('[name=reviewed]').has_attr('checked'))
    provider.write_mode = 'success'
    approved = owner.post(owner_detail, data=approval)
    owner_request = db('SELECT * FROM ci_provider_document_root_changes WHERE id=?', [approval['owner_request_id']])[0]
    owner_document = json.loads(owner_request['document_json'])
    dispatch = json.loads(journal()[-1]['document'])
    ledger = db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['documentroot:' + owner_request['id']])[0]
    terminal = db('SELECT * FROM ci_provider_document_root_dispatch_ends WHERE id=?', [owner_request['id']])[0]
    expected_post = {'path': '/package/2801/web/updateDocRoot', 'body': {'update': {hostname: desired}}}
    check('approval links both immutable journals and sends exactly one canonical change', approved.status_code == 200
          and len(journal()) == 6 and dispatch['kind'] == 'dispatch' and dispatch['id'] == owner_request['id']
          and dispatch['payload']['owner_request_sha256'] == owner_request['document_sha256']
          and owner_document['version'] == 2 and owner_document['client_origin']['id'] == request_id
          and owner_document['before_path'] == fresh_path and owner_document['params']['path'] == desired
          and ledger['status'] == 'uncertain' and ledger['attempts'] == 1 and terminal['disposition'] == 'attempted'
          and provider.calls == calls + 1 and provider.posts == original_posts + [expected_post])
    check('provider acknowledgement remains unconfirmed in client history', 'result unconfirmed' in details(client, request_id).text
          and provider.document_roots[hostname] == fresh_path)
    before_replay = journal(); audits = db('SELECT * FROM ci_audit ORDER BY id')
    owner.post(owner_detail, data=approval)
    check('owner approval replay cannot resend or rewrite journals', journal() == before_replay and provider.posts == original_posts + [expected_post]
          and db('SELECT * FROM ci_audit ORDER BY id') == audits and db('SELECT * FROM ci_provider_operations WHERE id=?', [ledger['id']]) == [ledger])
    cool(); different, _ = post_form(owner, owner_detail, 'provider_folder_request_verify', current_password=OWNER)
    check('separate mismatching map preserves the uncertain request', 'different folder or no website entry' in different.text
          and db('SELECT disposition FROM ci_provider_document_root_change_checks WHERE request_id=?', [owner_request['id']]) == [{'disposition': 'different'}]
          and provider.posts == original_posts + [expected_post])
    capture(owner, owner_detail, 'Owner-Readback', selector, owner=True)
    provider.document_roots = {hostname: desired}
    cool(); observed, _ = post_form(owner, owner_detail, 'provider_folder_request_verify', current_password=OWNER)
    client_observed = details(client, request_id)
    check('matching independent read appears in client history without rewriting dispatch', 'Requested folder reported' in observed.text
          and 'Requested folder reported' in client_observed.text and 'does not prove' in client_observed.text
          and db('SELECT * FROM ci_provider_operations WHERE id=?', [ledger['id']]) == [ledger]
          and db('SELECT * FROM ci_provider_document_root_dispatch_ends WHERE id=?', [terminal['id']]) == [terminal]
          and provider.posts == original_posts + [expected_post])
    capture(client, client_detail, 'Client-Observed', selector)
    check('client receives no internal owner approval or raw subsequent map', approval['owner_request_id'] not in client_observed.text
          and fresh_path not in client_observed.text and 'provider_folder_request_' not in client_observed.text)
    for table, rows in original.items():
        check('retains every inherited row in ' + table, retained_rows(rows, db('SELECT * FROM ci_' + table)))
    for table, rows in state['financial'].items():
        check('financial history remains exact in ' + table, db('SELECT * FROM ci_' + table + ' ORDER BY id') == rows)
    check('dedicated owner uses six real reads without rewriting cooldowns',
          [row['operation'] for row in db('SELECT operation FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq', [state['owner_id']])]
          == ['packages.list', 'packages.list'] + ['package.document-roots'] * 4)
    for path in ('app/providers/document_root_requests.php', 'app/providers/document_root_requests_document.php',
                 'app/providers/document_root_requests_storage.php', 'app/providers/document_root_requests_views.php',
                 'database/schema/provider_document_root_requests_schema.php', 'database/migrations/provider_document_root_requests_migrate.php',
                 'admin/controllers/document_root_requests.php', 'portal/controllers/document_root_requests.php'):
        check('private implementation refuses GET and POST ' + path, owner.get(base + '/existing/' + path).status_code == 404
              and owner.post(base + '/existing/' + path, data={}).status_code == 404)
    check('actual installed captures are complete and provider fixture reports no errors', tuple(captures) == CAPTURES and not provider.errors)
    result = {'passed': True, 'checks': checks, 'captures': captures}
    if not capture_complete(result, qa):
        raise AssertionError('Installed client folder acceptance is incomplete')
    return result
