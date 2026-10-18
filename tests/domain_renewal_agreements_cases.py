"""Packaged renewal agreements on the independent fresh install; synthetic registrar only."""
import hashlib
import json
import time
from datetime import datetime, timezone, timedelta
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form


def run_browser(env, browser):
    check, soup, form_data, form_for, submit = (env[k] for k in ['check', 'soup', 'form_data', 'form_for', 'submit'])
    base, qa, provider = env['BASE'], env['QA'], env['provider_server']
    admin, portal = base+'/fresh/admin/index.php', base+'/fresh/portal/index.php'
    settings, requests_url = admin+'?view=provider-settings', portal+'?domain_renewals=1'
    password, secret = 'Fresh synthetic passphrase 2026', 'SYNTHETIC_RAW_GENERAL_KEY'
    domain, domain_id = 'renewal-terms.fixture.invalid', 19001
    inherited_tables = ['provider_profiles', 'provider_refreshes', 'provider_refresh_results',
                        'provider_registrar_observations', 'provider_registrar_links', 'provider_registrar_events',
                        'domain_renewal_requests', 'domain_renewal_events', 'service_plans', 'service_plan_events',
                        'service_subscriptions', 'service_subscription_events', 'service_orders', 'service_order_events',
                        'studio_invoices', 'invoice_payments', 'audit']

    def digest(rows):
        return hashlib.sha256(json.dumps(rows, sort_keys=True, separators=(',', ':')).encode('utf-8')).hexdigest()

    inherited = {name: digest(db('SELECT * FROM ci_'+name+' ORDER BY id')) for name in inherited_tables}
    original_domains, original_mode = list(provider.registrar_domains), provider.registrar_mode
    calls, posts = provider.calls, list(provider.posts)
    mailbox = (qa/'mail-capture.txt').read_bytes()
    optional = ['service_plans', 'service_plan_events', 'service_subscriptions', 'service_subscription_events',
                'domain_renewal_agreements', 'domain_renewal_agreement_heads']

    def optional_absent():
        return not db('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('+
                      ','.join(['?']*len(optional))+')', ['fresh_'+name for name in optional])

    def rows(name):
        return db('SELECT * FROM fresh_'+name+' ORDER BY id')

    def agreement_state(agreement):
        return db('SELECT journal_json FROM fresh_service_subscriptions WHERE id=?', [agreement])[0]['journal_json']

    def agreement_snapshot():
        return digest({name: rows(name) for name in ['domain_renewal_agreements', 'domain_renewal_agreement_heads',
                                                   'service_subscriptions', 'service_subscription_events', 'audit']})

    def captured(session, url, action):
        return form_data(form_for(session.get(url), action))

    sessions = []
    owner = requests.Session(); sessions.append(owner)
    login = submit(owner, admin+'?view=login', 'login', {'email': 'fresh@example.invalid', 'password': password})
    check('Renewal terms use the independent authorized fresh owner and never-prepared optional families',
          login.status_code == 200 and parse_qs(urlparse(login.url).query).get('view') == ['dashboard'] and optional_absent())
    original_owner = rows('users')
    setup = {'owner_login_status': login.status_code, 'owner_dashboard': parse_qs(urlparse(login.url).query).get('view') == ['dashboard'],
             'optional_families_absent': optional_absent()}
    (qa/'domain-renewal-agreements-setup.json').write_text(json.dumps(setup, indent=2), encoding='utf-8')
    try:
        editor = form_data(soup(owner.get(settings)).select_one('[data-provider-editor]'))
        response = owner.post(settings, data={**editor, 'label': 'Renewal terms fixture', 'enabled': '1',
                                            'general_key': secret, 'current_password': password})
        profile = rows('provider_profiles')[0]
        check('Renewal terms connection is saved privately without provider traffic',
              profile['revision'] == 1 and profile['enabled'] == 1 and secret not in response.text and provider.calls == calls)
        submit(owner, settings, 'provider_inventory_registrar_prepare', {'current_password': password})
        submit(owner, settings, 'provider_inventory_renewal_prepare', {'current_password': password})
        clients = []
        for number in [1, 2]:
            email = 'renewal-terms-client'+str(number)+'@example.invalid'
            chosen_form(owner, admin+'?view=studio-clients', 'studio_client', 'id', 0,
                        {'name': 'Renewal terms client '+str(number), 'email': email})
            cid = db('SELECT id FROM fresh_studio_clients WHERE email=?', [email])[0]['id']
            invitation = chosen_form(owner, admin+'?view=studio-clients', 'studio_invite', 'id', cid, {})
            link = soup(invitation).select_one('.notice.info a[href*="invite="]')['href']
            client = requests.Session(); sessions.append(client)
            submit(client, link, 'activate', {'password': password, 'confirm_password': password})
            logged_in = submit(client, portal, 'login', {'email': email, 'password': password})
            check('Renewal terms client '+str(number)+' activates and signs in normally',
                  logged_in.status_code == 200 and 'Welcome, ' in logged_in.text)
            clients.append((cid, client))
        (cid, client), (_, foreign) = clients
        provider.registrar_domains = original_domains+[{'id': domain_id, 'name': domain}]
        provider.registrar_mode = 'success'

        def refresh():
            last = db('SELECT MAX(started_epoch) AS epoch FROM fresh_provider_refreshes')[0]['epoch']
            wait = max(0, int(last or 0)+31-int(time.time()))
            if wait:
                time.sleep(wait)
            return submit(owner, settings, 'provider_inventory_registrar_refresh', {'current_password': password})

        refresh()
        chosen_form(owner, settings, 'provider_inventory_registrar_link', 'domain_id', domain_id,
                    {'client_id': str(cid), 'current_password': password})
        created = submit(client, requests_url, 'domain_renewal_create',
                         {'requested_years': '2', 'renew_privacy': 'ask', 'request_confirm': '1'})
        request = rows('domain_renewal_requests')[0]; rid = request['id']
        check('Renewal request is saved before either optional agreement family is prepared',
              created.status_code == 200 and request['state'] == 'pending_review' and optional_absent())
        inherited_request = digest(rows('domain_renewal_requests')+rows('domain_renewal_events'))
        audit = digest(rows('audit'))
        for session, url, audience, control in [(owner, settings, 'owner', 'Save review message'),
                                               (client, requests_url, 'client', 'Withdraw request')]:
            response = session.get(url)
            check('Existing '+audience+' renewal history works with agreement and plan families absent',
                  response.status_code == 200 and domain in response.text and 'Pending studio review' in response.text
                  and control in response.text and 'prepare service-plan storage' not in response.text)
            context = browser.new_context(viewport={'width': 320, 'height': 740})
            try:
                context.add_cookies([{'name': c.name, 'value': c.value, 'url': base+'/fresh/'} for c in session.cookies])
                page = context.new_page(); page.goto(url, wait_until='domcontentloaded')
                expect(page.locator('[data-renewal-request]')).to_contain_text(domain)
                expect(page.get_by_role('button', name=control, exact=True)).to_be_visible()
                check('Chromium '+audience+' request controls survive never-prepared optional storage',
                      page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            finally:
                context.close()
        check('Never-prepared renewal rendering preserves request audit families and provider state',
              optional_absent() and digest(rows('domain_renewal_requests')+rows('domain_renewal_events')) == inherited_request
              and digest(rows('audit')) == audit and provider.calls == calls+1 and provider.posts == posts)

        submit(owner, admin+'?view=service-plans', 'service_plans_setup', {'current_password': password})
        offer_url = admin+'?view=domain-renewal-agreements&request='+rid
        for change, label in [({'csrf': 'wrong', 'current_password': password}, 'CSRF'),
                              ({'current_password': 'wrong'}, 'owner password')]:
            submit(owner, offer_url, 'provider_inventory_renewal_agreements_prepare', change)
            check('Renewal agreement preparation refuses '+label, not db('SHOW TABLES LIKE "fresh_domain_renewal_agreements"'))
        submit(owner, offer_url, 'provider_inventory_renewal_agreements_prepare', {'current_password': password})
        check('Renewal agreement preparation adds empty history without a provider read',
              not rows('domain_renewal_agreements') and provider.calls == calls+1)
        terms = {'name': 'Renewal service <review>', 'cadence': 'one-time', 'price': '24.99',
                 'inclusions': 'Arrange the reviewed domain renewal', 'exclusions': 'Automatic registry purchase',
                 'limits': '', 'support': 'Studio assistance', 'current_password': password}
        new_plan = submit(owner, admin+'?view=service-plan', 'service_plans_create', terms)
        plan_id = parse_qs(urlparse(new_plan.url).query)['record'][0]
        plan_url = admin+'?view=service-plan&record='+plan_id
        submit(owner, plan_url, 'service_plans_publish', {'confirm': '1', 'current_password': password})

        def offer():
            response = submit(owner, plan_url, 'service_plans_offer',
                              {'client_id': str(cid), 'confirm': '1', 'current_password': password})
            return parse_qs(urlparse(response.url).query)['record'][0]

        def bind(agreement):
            return chosen_form(owner, offer_url, 'provider_inventory_renewal_agreement_bind', 'agreement_id', agreement,
                               {'renew_privacy': '0', 'offer_expires': (datetime.now(timezone.utc)+timedelta(hours=1)).strftime('%Y-%m-%dT%H:%M'),
                                'confirm': '1', 'current_password': password})

        agreement = offer(); client_url = portal+'?agreement='+agreement
        generic = captured(client, client_url, 'service_agreements_consent')
        before = agreement_snapshot()
        form = captured(owner, offer_url, 'provider_inventory_renewal_agreement_bind')
        for changes, label in [({'csrf': 'bad'}, 'CSRF'), ({'current_password': 'wrong'}, 'owner password'),
                               ({'renew_privacy': ''}, 'unresolved privacy'), ({'request_sha256': 'f'*64}, 'changed request')]:
            owner.post(offer_url, data={**form, 'renew_privacy': '0', 'confirm': '1', 'current_password': password, **changes})
            check('Renewal binding refuses '+label+' without side effects', agreement_snapshot() == before)
        bind(agreement)
        binding = rows('domain_renewal_agreements')[0]
        check('Owner binds exact domain years privacy and existing USD agreement',
              binding['agreement_id'] == agreement and len(rows('domain_renewal_requests')) == 1
              and json.loads(binding['document_json'])['requested_years'] == 2
              and json.loads(binding['document_json'])['requested_privacy'] is None
              and json.loads(binding['document_json'])['renew_privacy'] is False
              and json.loads(binding['document_json'])['price_minor'] == 2499)
        before = agreement_snapshot()
        denied = client.post(client_url, data={**generic, 'confirm': '1'})
        check('Captured generic agreement form cannot bypass newly linked renewal terms',
              'Review the linked renewal terms before this agreement action.' in denied.text and agreement_snapshot() == before)
        response = client.get(client_url)
        check('Client sees exact private renewal scope separately from payment and provider execution',
              domain in response.text and 'USD 24.99' in response.text and '2 years' in response.text
              and 'Do not renew privacy' in response.text and 'does not submit a renewal, collect a payment, or confirm registry eligibility' in response.text
              and secret not in response.text and profile['id'] not in response.text
              and response.headers.get('Cache-Control') == 'no-store, private'
              and response.headers.get('Referrer-Policy') == 'no-referrer'
              and not soup(response).select('[data-service-order]'))
        check('Support and anonymous sessions cannot review linked renewal terms',
              client.get(client_url+'&support=fixture').status_code == 403
              and owner.get(admin+'?view=domain-renewal-agreements&request='+rid+'&support=fixture').status_code == 403
              and domain not in requests.get(client_url).text and agreement_snapshot() == before)
        consent = captured(client, client_url, 'service_agreements_consent')
        foreign_token = form_data(form_for(foreign.get(portal), 'logout'))['csrf']
        denied = foreign.post(client_url, data={**consent, 'confirm': '1', 'csrf': foreign_token})
        check('Foreign client cannot read or accept captured renewal agreement',
              domain not in denied.text and agreement_snapshot() == before)
        for invalid, label in [(str('a'*65), 'oversize'), ('x\x00y', 'NUL')]:
            invalid_response = client.post(client_url, data={**consent, 'confirm': '1', 'renewal_binding': invalid})
            redisplayed = form_for(invalid_response, 'service_agreements_consent')
            check('Malformed '+label+' binding preserves complete error controls without new authority',
                  invalid_response.status_code == 200 and form_data(redisplayed)['renewal_binding'] == ''
                  and agreement_snapshot() == before)
        array_fields = [(key, value) for key, value in {**consent, 'confirm': '1'}.items() if key != 'renewal_binding']
        array_response = client.post(client_url, data=array_fields+[('renewal_binding[]', 'invalid')])
        check('Malformed array binding preserves complete error controls without new authority',
              array_response.status_code == 200 and form_data(form_for(array_response, 'service_agreements_consent'))['renewal_binding'] == ''
              and agreement_snapshot() == before)
        for session, url, audience in [(owner, admin+'?view=service-agreement&record='+agreement, 'Owner'),
                                       (client, client_url, 'Client')]:
            context = browser.new_context(); errors = []
            try:
                context.add_cookies([{'name': c.name, 'value': c.value, 'url': base+'/fresh/'} for c in session.cookies])
                page = context.new_page(); page.on('pageerror', lambda error: errors.append(str(error)))
                for width, height, label in [(1440, 1000, 'Desktop'), (320, 740, 'Mobile')]:
                    page.set_viewport_size({'width': width, 'height': height}); page.goto(url, wait_until='domcontentloaded')
                    expect(page.locator('.renewal-agreement-scope')).to_contain_text(domain)
                    page.evaluate('document.fonts.ready')
                    page.screenshot(path=str(qa/('Domain-Renewal-Agreement-'+audience+'-'+label+'.png')), full_page=True)
                    check('Renewal agreement '+audience+' '+label+' fits the viewport',
                          not errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
                if audience == 'Client':
                    form = page.locator('form').filter(has=page.locator('[name=action][value=service_agreements_consent]'))
                    consent = {name: form.locator('[name='+name+']').input_value() for name in ['csrf', 'action', 'record_id', 'revision', 'event_id', 'terms_hash', 'renewal_binding']}
                    form.locator('[name=confirm]').check()
                    with page.expect_navigation(wait_until='domcontentloaded'):
                        form.get_by_role('button', name='Accept these service terms', exact=True).click()
                    expect(page.get_by_text('Your agreement to these service terms was recorded.', exact=True)).to_be_visible()
            finally:
                context.close()
        check('Chromium consent records one direct-client event without renewal or payment',
              len(rows('service_subscription_events')) == 1 and rows('service_subscription_events')[0]['actor_client_id'] == cid
              and provider.calls == calls+1 and provider.posts == posts and rows('domain_renewal_requests')[0]['state'] == 'pending_review')
        accepted = agreement_snapshot()
        client.post(client_url, data={**consent, 'confirm': '1'})
        check('Captured accepted consent replay creates no duplicate event', agreement_snapshot() == accepted)
        submit(client, client_url, 'service_agreements_abandon', {'confirm': '1'})
        second = offer(); bind(second); second_url = portal+'?agreement='+second
        second_consent = captured(client, second_url, 'service_agreements_consent')
        before = agreement_snapshot()
        stale = client.post(client_url, data={**consent, 'confirm': '1'})
        check('Ended offer and replacement retain one request and refuse old captured URL',
              len(rows('domain_renewal_requests')) == 1 and len(rows('domain_renewal_agreements')) == 2
              and agreement_snapshot() == before and 'The renewal request or offer changed.' in stale.text)
        chosen_form(owner, settings, 'provider_inventory_renewal_review', 'request_id', rid,
                    {'current_password': password, 'command': 'reviewed', 'review_note': 'Please review the revised request.'})
        before = agreement_snapshot()
        stale = client.post(second_url, data={**second_consent, 'confirm': '1'})
        check('Changed request revision rejects captured renewal consent without an event',
              'The renewal request or offer changed.' in stale.text and agreement_snapshot() == before)
        submit(client, second_url, 'service_agreements_abandon', {'confirm': '1'})
        third = offer(); bind(third); third_url = portal+'?agreement='+third
        third_consent = captured(client, third_url, 'service_agreements_consent')
        provider.registrar_mode = 'authentication'; refresh(); before = agreement_snapshot()
        stale = client.post(third_url, data={**third_consent, 'confirm': '1'})
        check('Latest failed registrar read hides action and rejects captured consent',
              'Refresh registered domains and confirm the current assignment' in stale.text
              and not soup(client.get(third_url)).select('[name=action][value=service_agreements_consent]')
              and agreement_snapshot() == before)
        submit(client, third_url, 'service_agreements_abandon', {'confirm': '1'})
        chosen_form(client, requests_url, 'domain_renewal_withdraw', 'request_id', rid, {})
        history = owner.get(offer_url)
        check('Stale offers can be ended and request withdrawn while all historical terms remain',
              len(rows('domain_renewal_agreements')) == 3 and rows('domain_renewal_requests')[0]['state'] == 'withdrawn'
              and not soup(history).select('[name=action][value=provider_inventory_renewal_agreement_bind]')
              and all(agreement_id in history.text for agreement_id in [agreement, second, third]))
        read_history = db('SELECT f.operation,r.state FROM fresh_provider_refreshes f LEFT JOIN fresh_provider_refresh_results r ON r.id=f.id ORDER BY f.seq')
        check('Renewal terms retain exactly two explicit registrar reads and no other remote action',
              read_history == [{'operation': 'domains.list', 'state': 'complete'}, {'operation': 'domains.list', 'state': 'unavailable'}]
              and provider.calls == calls+2 and provider.posts == posts and not provider.errors)
        check('Renewal terms leave original owner and all unrelated histories intact',
              rows('users') == original_owner and all(digest(db('SELECT * FROM ci_'+name+' ORDER BY id')) == value for name, value in inherited.items())
              and not rows('studio_invoices') and not rows('invoice_payments') and (qa/'mail-capture.txt').read_bytes() == mailbox)
        for path in ['app/providers/domain_renewal_agreements.php', 'app/providers/domain_renewal_agreements_document.php',
                     'app/providers/domain_renewal_agreements_storage.php', 'app/providers/domain_renewal_agreements_views.php',
                     'admin/controllers/domain_renewal_agreements.php', 'admin/views/domain_renewal_agreements.php',
                     'database/schema/domain_renewal_agreements_schema.php']:
            for prefix in ['/existing/', '/fresh/']:
                check('Renewal agreement private HTTP denial '+prefix+path, requests.get(base+prefix+path).status_code == 404)
        (qa/'domain-renewal-agreements-history.json').write_text(json.dumps({'inherited_hashes': inherited, 'unchanged': True,
            'original_owner_sha256': digest(original_owner), 'explicit_reads': read_history, 'binding_count': 3,
            'request_count': 1, 'provider_writes': 0}, indent=2), encoding='utf-8')
    finally:
        provider.registrar_domains, provider.registrar_mode = original_domains, original_mode
        for session in sessions:
            session.close()
