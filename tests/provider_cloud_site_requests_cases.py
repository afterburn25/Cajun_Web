"""Installed client-request continuation; only the existing loopback provider is used.

Called before provider_cloud_sites_cases restores its synthetic dictionaries. All
business creation/approval/linking uses real HTTP forms. The sole direct history
fault removes this continuation's final event and restores its exact saved row.
No native fixture state, clock edits, quota resets or live 20i calls are used.
"""
import copy
import hashlib
import json
import math
import re
import time
from urllib.parse import parse_qs, urljoin, urlparse

from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER


CAPTURES = tuple('Cloud-Client-Website-' + state + '-' + size + '.png'
                 for state in ('Request', 'Review', 'Accepted', 'Confirmed')
                 for size in ('Desktop', 'Mobile'))
SCOPE = 'installed client cloud website request and exact package confirmation'


def run_browser(env, browser, owner, client, other, profile):
    base, qa = env['BASE'], env['QA']
    if urlparse(base).hostname not in ('127.0.0.1', 'localhost'):
        raise RuntimeError('Cloud request continuation requires the local fixture.')
    soup, token, form_data, provider = (env[k] for k in
                                      ('soup', 'token', 'form_data', 'provider_server'))
    admin = base + '/existing/admin/index.php'
    settings = admin + '?view=provider-settings'
    portal = base + '/existing/portal/index.php'
    intake = portal + '?cloud_websites=1'
    services = portal + '?services=1'
    progress = qa / 'cloud-client-website-progress.txt'
    for path in [progress, *(qa / name for name in CAPTURES)]:
        if path.exists() or path.is_symlink():
            raise RuntimeError('Cloud request output already exists.')
    checks, captures, names = [], [], set()

    def check(name, condition):
        name = 'Client cloud website: ' + name
        if name in names:
            raise RuntimeError('Duplicate cloud request assertion.')
        names.add(name)
        checks.append({'name': name, 'passed': bool(condition)})
        progress.write_text(json.dumps({'scope': SCOPE, 'checks': checks,
                                        'captures': captures}, indent=2) + '\n', encoding='utf-8')
        env['check'](name, bool(condition))

    def get(session, url):
        response = session.get(url, timeout=40)
        if response.status_code != 200:
            raise RuntimeError('Expected cloud request page was unavailable.')
        return response

    def form(session, url, action, request_id=None):
        response = get(session, url)
        root = soup(response)
        if request_id:
            root = root.select_one('[data-cloud-website-request="' + request_id + '"]')
            if root is None:
                raise RuntimeError('Expected cloud request history was unavailable.')
        found = root.select('form[data-cloud-website-form="' + action + '"]')
        if len(found) != 1:
            raise RuntimeError('Expected one cloud request form.')
        return form_data(found[0])

    def post(session, url, fields, **changes):
        return session.post(url, data={**fields, **changes}, timeout=45)

    def history(session, request_id):
        return soup(get(session, settings if session is owner else intake)).select_one(
            '[data-cloud-website-request="' + request_id + '"]')

    def rows():
        return db('SELECT * FROM ci_provider_cloud_site_request_events ORDER BY ordinal')

    def state():
        return {
            'events': rows(),
            'grants': db('SELECT * FROM ci_provider_operation_grants ORDER BY seq'),
            'ledger': db('SELECT * FROM ci_provider_operations ORDER BY idempotency_key'),
            'links': db('SELECT * FROM ci_provider_package_links ORDER BY id'),
            'confirmations': db('SELECT * FROM ci_provider_cloud_confirmations ORDER BY id'),
            'head': db('SELECT * FROM ci_settings WHERE name IN (?,?) ORDER BY name',
                       ['provider_cloud_site_request_schema_version', 'provider_cloud_site_request_head']),
            'audit': db('SELECT * FROM ci_audit WHERE action IN (?,?) ORDER BY id',
                        ['provider.cloud_site_request_prepared', 'provider.cloud_site_request_event']),
            'calls': provider.calls,
            'posts': copy.deepcopy(provider.posts),
        }

    def local_intake_only(before, request_id, label):
        after = state()
        tail = after['events'][-1]
        document = json.loads(tail['document'])
        before_head = {row['name']: row['value'] for row in before['head']}
        after_head = {row['name']: row['value'] for row in after['head']}
        check(label,
              after['events'][:-1] == before['events'] and len(after['events']) == len(before['events']) + 1
              and tail['id'] == tail['subject_id'] == request_id and tail['kind'] == 'request'
              and tail['client_id'] == cid and document['actor']['kind'] == 'client' and document['actor']['id'] == cid
              and after['audit'][:-1] == before['audit'] and len(after['audit']) == len(before['audit']) + 1
              and after['audit'][-1]['user_id'] == 0
              and after['audit'][-1]['detail'] == 'Cloud website request event ' + request_id + ' ' + tail['fingerprint']
              and after_head == {**before_head, 'provider_cloud_site_request_head': str(tail['ordinal']) + ':' + tail['fingerprint']}
              and all(after[key] == before[key] for key in before if key not in ('events', 'head', 'audit')))

    def refresh(action, operation, label, expected_state='complete'):
        # Admission uses this actual profile's latest immutable refresh start.
        # Wait at most 32 seconds; never change an authenticated timestamp.
        latest = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes WHERE profile_id=?',
                    [profile['id']])[0]['epoch']
        delay = max(0.0, float(latest or 0) + 31 - time.time())
        if delay > 32:
            raise RuntimeError('Unexpected future refresh time in isolated fixture.')
        if delay:
            time.sleep(delay)
        original = db('SELECT * FROM ci_provider_refreshes ORDER BY seq')
        calls = provider.calls
        response = post(owner, settings, {'csrf': token(get(owner, settings)), 'action': action,
                        'profile_id': profile['id'], 'revision': str(profile['revision']),
                        'current_password': OWNER})
        after = db('SELECT * FROM ci_provider_refreshes ORDER BY seq')
        latest_result = db('SELECT r.state FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id WHERE f.profile_id=? AND f.operation=? ORDER BY f.seq DESC LIMIT 1',
                           [profile['id'], operation])
        check(label + ' uses one real refresh and retains every earlier intent',
              response.status_code == 200 and after[:-1] == original and len(after) == len(original) + 1
              and after[-1]['operation'] == operation and latest_result == [{'state': expected_state}]
              and provider.calls == calls + 1)

    def capture(session, target_url, selector, title, follow_services=False):
        context = browser.new_context(viewport={'width': 1440, 'height': 1050})
        context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path}
                            for c in session.cookies])
        page = context.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        calls = provider.calls
        try:
            for width, height, label in ((1440, 1050, 'Desktop'), (320, 740, 'Mobile')):
                page.set_viewport_size({'width': width, 'height': height})
                response = page.goto(target_url)
                check(title + ' ' + label + ' authenticated page loads', response.status == 200)
                page.evaluate('document.fonts.ready')
                target = page.locator(selector)
                expect(target).to_have_count(1)
                expect(target).to_be_visible()
                check(title + ' ' + label + ' original viewport has no horizontal overflow',
                      page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
                if session is owner and width == 320:
                    expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
                # Preserve the original mobile predicates, then grow only capture
                # height for a complete readable card under the actual sticky header.
                header = page.evaluate("""() => Math.max(0,...Array.from(document.querySelectorAll('.site-header,.admin-topbar')).map(e=>{
                    const r=e.getBoundingClientRect();return ['fixed','sticky'].includes(getComputedStyle(e).position)&&r.height>0?r.height:0;}))""")
                height = max(height, math.ceil(target.bounding_box()['height'] + header + 48))
                if height > 2400:
                    raise RuntimeError('Cloud request card exceeds bounded capture height.')
                page.set_viewport_size({'width': width, 'height': height})
                target.evaluate('(e,offset)=>window.scrollTo({top:Math.max(0,e.getBoundingClientRect().top+scrollY-offset),behavior:"instant"})', header + 16)
                page.wait_for_function("""selector => {const e=document.querySelector(selector),r=e.getBoundingClientRect();
                    return r.top>=0&&r.bottom<=innerHeight&&r.left>=0&&r.right<=innerWidth&&Number(getComputedStyle(e).opacity)===1;}""", arg=selector)
                geometry = target.bounding_box()
                check(title + ' ' + label + ' entire card is unobscured and controls are reachable',
                      target.evaluate("""e=>Array.from(e.querySelectorAll('h3,button,a.btn')).every(n=>{
                        const r=n.getBoundingClientRect();if(r.width===0||r.height===0)return false;
                        const at=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);
                        return at===n||n.contains(at);})"""))
                name = 'Cloud-Client-Website-' + title + '-' + label + '.png'
                path = qa / name
                if path.exists() or path.is_symlink():
                    raise RuntimeError('Cloud request capture already exists.')
                page.screenshot(path=str(path), full_page=False)
                captures.append({'name': name, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
                                 'width': width, 'height': height, 'target': geometry})
                check(title + ' ' + label + ' capture is retained', path.stat().st_size > 0)
                if follow_services:
                    target.get_by_role('link', name='View hosting services', exact=True).click()
                    page.wait_for_url(services)
                    check('Confirmed ' + label + ' follows actual hosting services navigation',
                          'client-request-accepted.example' in page.locator('body').inner_text())
            check(title + ' capture GETs make no provider call or script error',
                  provider.calls == calls and not errors)
        finally:
            context.close()

    originals = {name: copy.deepcopy(getattr(provider, name)) for name in
                 ('cloud_servers', 'cloud_mode', 'packages', 'mode', 'write_mode')}
    support_id = None
    try:
        provider.cloud_mode = provider.mode = provider.write_mode = 'success'
        cid = db('SELECT id FROM ci_studio_clients WHERE email=?', ['alice@example.invalid'])[0]['id']
        links = db('SELECT * FROM ci_provider_cloud_links WHERE profile_id=? AND server_id=1401 AND client_id=?',
                   [profile['id'], cid])
        check('inherits one actual assigned cloud server from earlier cases', len(links) == 1)
        check('intake namespace is unused before explicit preparation',
              not db('SELECT value FROM ci_settings WHERE name="provider_cloud_site_request_schema_version"'))
        calls, posts = provider.calls, copy.deepcopy(provider.posts)
        before_get = get(client, intake)
        check('unprepared client GET is read-only and has no intake form',
              'Website requests are not available yet' in before_get.text
              and soup(before_get).select_one('[data-cloud-website-form]') is None and provider.calls == calls)
        setup = form(owner, settings, 'provider_inventory_cloud_request_prepare')
        for label, fields in [('CSRF', {'csrf': 'invalid', 'current_password': OWNER}),
                              ('password', {'current_password': 'wrong'})]:
            post(owner, settings, setup, **fields)
            check('preparation refuses invalid ' + label,
                  not db('SELECT value FROM ci_settings WHERE name="provider_cloud_site_request_schema_version"')
                  and provider.calls == calls)
        post(owner, settings, setup, current_password=OWNER)
        check('owner explicitly prepares intake without provider calls',
              db('SELECT value FROM ci_settings WHERE name="provider_cloud_site_request_schema_version"') == [{'value': '1'}]
              and rows() == [] and provider.calls == calls and provider.posts == posts)
        refresh('provider_inventory_cloud_refresh', 'cloudservers.list', 'Initial cloud observation')
        original_state = state()
        fields = form(client, intake, 'cloud_website_request_create')
        check('client intake selects the actual assignment and bounded context',
              fields['link_id'] == links[0]['id'] and re.fullmatch('[a-f0-9]{64}', fields['context']) is not None)
        capture(client, intake, '[data-cloud-website-option="' + fields['link_id'] + '"]', 'Request')
        fields.update(domain='client-request-cancel.example', aliases='www.client-request-cancel.example', request_confirm='1')
        for label, delta in [('CSRF', {'csrf': 'invalid'}), ('review', {'request_confirm': '0'}),
                             ('domain', {'domain': 'bad/domain.example'}), ('oversize domain', {'domain': 'a' * 254})]:
            post(client, intake, fields, **delta)
            check('intake rejects invalid ' + label + ' without history or dispatch', state() == original_state)
        retry_response = post(client, intake, fields, request_confirm='0')
        retry_form = soup(retry_response).select_one('[data-cloud-website-form="cloud_website_request_create"]')
        check('client input error retains exact identity domains and context but clears review',
              retry_form is not None and all(form_data(retry_form).get(k) == fields[k] for k in
                                            ('request_id', 'link_id', 'context', 'domain', 'aliases'))
              and retry_form.select_one('[name=request_confirm]').get('checked') is None)
        post(client, intake, fields)
        cancel_id = fields['request_id']
        check('client submits a pending request without sending to provider',
              len(rows()) == 1 and rows()[0]['client_id'] == cid and rows()[0]['kind'] == 'request'
              and provider.calls == original_state['calls'] and provider.posts == original_state['posts']
              and 'Pending studio review' in history(client, cancel_id).text)
        # Preserve a genuine previously usable owner form, then retain a real
        # newer failed observation. Intake remains local; sending must not fall back.
        prior_dispatch = form(owner, settings, 'provider_inventory_cloud_request_dispatch', cancel_id)
        provider.cloud_mode = 'authentication'
        refresh('provider_inventory_cloud_refresh', 'cloudservers.list', 'Latest failed cloud observation', 'unavailable')
        failed_state = state()
        later_intake = form(client, intake, 'cloud_website_request_create')
        check('failed latest read leaves exact signed assignment intake context usable without GET I/O',
              later_intake['link_id'] == fields['link_id'] and later_intake['context'] == fields['context']
              and state() == failed_state)
        blocked = history(owner, cancel_id)
        check('failed latest read withholds owner dispatch and asks for current information',
              blocked.select_one('[data-cloud-website-form="provider_inventory_cloud_request_dispatch"]') is None
              and 'refresh cloud inventory' in blocked.text and state() == failed_state)
        refused_dispatch = post(owner, settings, prior_dispatch, reviewed='1', current_password=OWNER)
        check('previously valid owner approval cannot send through a newer failed read',
              refused_dispatch.status_code == 200
              and 'Refresh the cloud server list and review the latest complete observation' in refused_dispatch.text
              and state() == failed_state)
        cancel = form(client, intake, 'cloud_website_request_cancel', cancel_id)
        before = state()
        post(other, intake, cancel, csrf=token(get(other, intake)), confirm='1')
        check('other authenticated client cannot cancel original request', state() == before)
        post(client, intake, cancel, confirm='1')
        check('client cancellation is durable and has no creation send',
              len(rows()) == 2 and rows()[-1]['kind'] == 'cancel' and 'Cancelled' in history(client, cancel_id).text
              and provider.calls == before['calls'])
        before = state(); post(client, intake, cancel, confirm='1')
        check('exact client cancellation replay adds no event', state() == before)

        decline = form(client, intake, 'cloud_website_request_create')
        decline.update(domain='client-request-decline.example', aliases='', request_confirm='1')
        before_intake = state()
        post(client, intake, decline)
        decline_id = decline['request_id']
        local_intake_only(before_intake, decline_id, 'client queues decline-case request after failed read with only event head and audit changes')
        decline_fields = form(owner, settings, 'provider_inventory_cloud_request_decline', decline_id)
        before = state()
        post(owner, settings, decline_fields, note='Choose another domain.', confirm='1', current_password='wrong')
        check('owner decline requires actual password without writing history', state() == before)
        post(owner, settings, decline_fields, note='Choose another domain.', confirm='1', current_password=OWNER)
        check('owner decline reaches original client without creation',
              rows()[-1]['kind'] == 'decline' and 'Choose another domain.' in history(client, decline_id).text
              and 'Declined' in history(client, decline_id).text and provider.calls == before['calls'])

        accepted = form(client, intake, 'cloud_website_request_create')
        accepted.update(domain='client-request-accepted.example', aliases='www.client-request-accepted.example', request_confirm='1')
        before = state()
        post(other, intake, accepted, csrf=token(get(other, intake)))
        check('foreign client cannot borrow current assignment or intake context', state() == before)
        # Real support session and real support CSRF, not a fabricated browser cookie.
        support_page = get(owner, admin + '?view=support')
        support_forms = [n for n in soup(support_page).select('form')
                         if n.select_one('[name=action][value=support_start]') is not None
                         and n.select_one('[name=client_id]') is not None
                         and n.select_one('[name=client_id]').get('value') == str(cid)]
        check('existing client support policy exposes one actual owner entry', len(support_forms) == 1)
        support_response = post(owner, admin + '?view=support', form_data(support_forms[0]))
        support_id = parse_qs(urlparse(support_response.url).query).get('support', [''])[0]
        check('owner enters actual isolated support session', re.fullmatch('[a-f0-9]{32}', support_id) is not None)
        support_url = portal + '?support=' + support_id
        support_token = token(get(owner, support_url))
        forbidden_get = owner.get(support_url + '&cloud_websites=1', timeout=40)
        forbidden_post = post(owner, support_url + '&cloud_websites=1', accepted, csrf=support_token)
        check('support cannot view intake or submit as client',
              forbidden_get.status_code == 403 and forbidden_post.status_code == 403 and state() == before)
        ended = post(owner, support_url, {'action': 'support_end', 'csrf': support_token})
        check('actual support context ends before owner review', ended.status_code == 200 and 'view=support' in ended.url)
        support_id = None
        before_intake = state()
        post(client, intake, accepted)
        request_id = accepted['request_id']
        local_intake_only(before_intake, request_id, 'client queues accepted-case request after failed read with zero provider I/O or grant')
        before = state(); post(client, intake, accepted)
        check('exact intake replay retains one pending request', state() == before)
        check('foreign client history omits all three submitted domains',
              all(name not in get(other, intake).text for name in
                  ('client-request-cancel.example', 'client-request-decline.example', 'client-request-accepted.example')))
        before_fresh = state()
        provider.cloud_mode = 'success'
        refresh('provider_inventory_cloud_refresh', 'cloudservers.list', 'Owner fresh dispatch observation')
        fresh_intake = form(client, intake, 'cloud_website_request_create')
        after_fresh = state()
        check('fresh complete read restores owner dispatch without rewriting requests or changing intake context',
              fresh_intake['context'] == accepted['context'] and fresh_intake['link_id'] == accepted['link_id']
              and history(owner, request_id).select_one('[data-cloud-website-form="provider_inventory_cloud_request_dispatch"]') is not None
              and all(after_fresh[key] == before_fresh[key] for key in before_fresh if key != 'calls')
              and after_fresh['calls'] == before_fresh['calls'] + 1)
        capture(owner, settings, '[data-cloud-website-request="' + request_id + '"]', 'Review')
        dispatch = form(owner, settings, 'provider_inventory_cloud_request_dispatch', request_id)
        before = state()
        for label, delta in [('CSRF', {'csrf': 'invalid'}), ('review', {'reviewed': '0'}),
                             ('context', {'dispatch_context': 'a' * 64}), ('password', {'current_password': 'wrong'})]:
            response = post(owner, settings, {**dispatch, 'reviewed': '1', 'current_password': OWNER}, **delta)
            check('owner dispatch refuses invalid ' + label + ' before provider send', state() == before)
        retry_form = soup(response).select_one('[data-cloud-website-request="' + request_id + '"] [data-cloud-website-form="provider_inventory_cloud_request_dispatch"]')
        check('owner password failure retains exact reviewed IDs and blanks password and consent',
              retry_form is not None and all(form_data(retry_form).get(k) == dispatch[k] for k in
                                            ('request_id', 'expected_event', 'dispatch_id', 'grant_id', 'dispatch_context'))
              and retry_form.select_one('[name=current_password]').get('value', '') == ''
              and retry_form.select_one('[name=reviewed]').get('checked') is None)
        result = post(owner, settings, dispatch, reviewed='1', current_password=OWNER)
        grant_id = dispatch['grant_id']
        ledger_rows = db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['cloudsite:' + grant_id])
        check('owner approval sends exactly one documented requested website',
              result.status_code == 200 and provider.calls == before['calls'] + 1
              and provider.posts == before['posts'] + [{'path': '/cloud_server/1401/addWeb',
                  'body': {'domain_name': accepted['domain'], 'extra_domain_names': [accepted['aliases']]}}]
              and len(ledger_rows) == 1 and ledger_rows[0]['status'] == 'accepted_pending'
              and rows()[-1]['id'] == dispatch['dispatch_id'] and rows()[-1]['kind'] == 'dispatch')
        package_id = ledger_rows[0]['external_id']
        check('acknowledged request does not silently assign a hosting package',
              db('SELECT * FROM ci_provider_package_links ORDER BY id') == before['links']
              and db('SELECT * FROM ci_provider_cloud_confirmations ORDER BY id') == before['confirmations']
              and 'Accepted by 20i; confirmation needed' in history(client, request_id).text)
        before = state(); post(owner, settings, dispatch, reviewed='1', current_password=OWNER)
        check('owner exact dispatch replay cannot send or create another event', state() == before)
        capture(client, intake, '[data-cloud-website-request="' + request_id + '"]', 'Accepted')

        # Root-approved, bounded missing-history fault. Retain and restore all seven
        # original columns; do not modify head/audit/grant/ledger/read observations.
        retained_state = state()
        fault_prepare = {**setup, 'csrf': token(get(owner, settings))}
        retained = rows(); tail = retained[-1]
        columns = ('id', 'ordinal', 'subject_id', 'client_id', 'kind', 'document', 'fingerprint')
        check('missing-history fault targets only this newly dispatched final event',
              set(tail) == set(columns) and tail['id'] == dispatch['dispatch_id']
              and tail['subject_id'] == request_id and tail['kind'] == 'dispatch')
        try:
            db('DELETE FROM ci_provider_cloud_site_request_events WHERE id=?', [tail['id']])
            fault_state = state()
            owner_fault = owner.get(settings, timeout=40)
            client_fault = get(client, intake)
            check('lost dispatch history blocks both request pages without inventing empty state',
                  owner_fault.status_code == 200
                  and soup(owner_fault).select_one('[data-cloud-history-unavailable]') is not None
                  and 'Website request history is temporarily unavailable.' in client_fault.text
                  and soup(owner_fault).select_one('[data-cloud-website-form]') is None
                  and soup(client_fault).select_one('[data-cloud-website-form]') is None)
            post(owner, settings, fault_prepare, current_password=OWNER)
            post(owner, settings, dispatch, reviewed='1', current_password=OWNER)
            check('preparation and dispatch cannot replace lost established history', state() == fault_state)
        finally:
            db('INSERT INTO ci_provider_cloud_site_request_events (' + ','.join(columns) + ') VALUES (?,?,?,?,?,?,?)',
               [tail[k] for k in columns])
            check('missing-history fault restores exact original complete event rows', rows() == retained)
        check('restored history keeps every head audit grant ledger link and confirmation byte', state() == retained_state)
        owner_card = history(owner, request_id)
        link = owner_card.select_one('a[href*="cloud_website_confirm="]')
        confirmation_url = urljoin(settings, link['href']) if link else ''
        check('accepted owner history links exact retained grant independently of recent list',
              confirmation_url == settings + '&cloud_website_confirm=' + grant_id + '#cloud-website-confirmation')
        pre_confirm = get(owner, confirmation_url)
        check('exact confirmation route requires a separate fresh hosting observation',
              soup(pre_confirm).select_one('#cloud-website-confirmation [data-cloud-confirmation]') is None)
        refresh('provider_inventory_refresh', 'packages.list', 'Allocated package observation')
        exact = soup(get(owner, confirmation_url)).select_one('#cloud-website-confirmation [data-cloud-confirmation]')
        check('fresh exact package exposes owner confirmation for the accepted request',
              exact is not None and '#' + str(package_id) in exact.text and accepted['domain'] in exact.text)
        confirm = form_data(exact)
        check('confirmation form carries exact grant not client request identity', confirm['request_id'] == grant_id)
        before = state()
        post(owner, confirmation_url, confirm, reviewed='1', current_password=OWNER)
        assigned = db('SELECT * FROM ci_provider_package_links WHERE profile_id=? AND package_id=?', [profile['id'], package_id])
        check('actual owner confirmation links exact returned package to original client',
              len(assigned) == 1 and assigned[0]['client_id'] == cid
              and len(db('SELECT * FROM ci_provider_cloud_confirmations ORDER BY id')) == len(before['confirmations']) + 1
              and provider.calls == before['calls'] and provider.posts == before['posts'])
        before = state(); post(owner, confirmation_url, confirm, reviewed='1', current_password=OWNER)
        check('exact package confirmation replay retains all event and ledger history', state() == before)
        client_card = history(client, request_id)
        check('client sees assigned confirmation without internal approval fields',
              client_card.select_one('[data-cloud-website-confirmation]') is not None
              and 'Allocated package observed and assigned.' in client_card.text
              and grant_id not in str(client_card) and dispatch['dispatch_context'] not in str(client_card)
              and client_card.select_one('[name=current_password]') is None)
        navigation = client_card.select_one('a[href]')
        check('client request history offers direct fixed hosting services navigation',
              navigation is not None and urljoin(intake, navigation['href']) == services
              and accepted['domain'] in get(client, urljoin(intake, navigation['href'])).text
              and accepted['domain'] not in get(other, services).text)
        capture(client, intake, '[data-cloud-website-request="' + request_id + '"]', 'Confirmed', True)
        check('complete journey emits exactly the eight owned captures', tuple(c['name'] for c in captures) == CAPTURES)
        check('synthetic provider reports no invalid request', not provider.errors)
        return {'passed': True, 'scope': SCOPE, 'checks': checks, 'captures': captures}
    finally:
        try:
            if support_id:
                support_url = portal + '?support=' + support_id
                post(owner, support_url, {'action': 'support_end', 'csrf': token(get(owner, support_url))})
        finally:
            for name, value in originals.items():
                setattr(provider, name, value)
