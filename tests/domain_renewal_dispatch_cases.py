"""Packaged renewal allocation/payment/one-send flow on the separate fresh installation."""
import hashlib
import json
import time
from datetime import datetime, timedelta, timezone
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form


def run_browser(env, browser):
    base, qa, provider, check = (env[k] for k in ['BASE', 'QA', 'provider_server', 'check'])
    soup, token, submit, form_data, form_for = (env[k] for k in ['soup', 'token', 'submit', 'form_data', 'form_for'])
    admin, portal = base+'/fresh/admin/index.php', base+'/fresh/portal/index.php'
    settings = admin+'?view=provider-settings'
    password = 'Fresh synthetic passphrase 2026'
    email = 'renewal-dispatch-owner@example.invalid'
    inherited_tables = ['provider_profiles', 'provider_refreshes', 'provider_refresh_results', 'provider_registrar_observations',
                        'provider_registrar_links', 'provider_registrar_events', 'domain_renewal_requests', 'domain_renewal_events',
                        'domain_renewal_agreements', 'domain_renewal_agreement_heads', 'service_plans', 'service_plan_events',
                        'service_subscriptions', 'service_subscription_events', 'service_orders', 'service_order_events',
                        'studio_invoices', 'invoice_payments', 'audit']

    def digest(value):
        return hashlib.sha256(json.dumps(value, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

    def rows(name):
        return db('SELECT * FROM fresh_'+name+' ORDER BY id')

    def optional_rows(prefix, name):
        if not db('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [prefix+name]):
            return None
        return db('SELECT * FROM '+prefix+name+' ORDER BY id')

    inherited = {name: optional_rows('fresh_', name) for name in inherited_tables}
    unrelated = {name: digest(optional_rows('ci_', name)) for name in inherited_tables}
    original_domains, original_mode = list(provider.registrar_domains), provider.registrar_mode
    original_renewal_mode = provider.renewal_mode
    initial_calls, initial_posts, initial_renewals = provider.calls, list(provider.posts), list(provider.renewal_posts)
    mailbox = (qa/'mail-capture.txt').read_bytes()
    sessions = []
    setup = requests.Session(); sessions.append(setup)
    before_owner = db('SELECT * FROM fresh_users WHERE email=?', ['fresh@example.invalid'])[0]
    before_profile = db('SELECT * FROM fresh_staff_profiles WHERE user_id=?', [before_owner['id']])[0]
    login = submit(setup, admin+'?view=login', 'login', {'email': 'fresh@example.invalid', 'password': password})
    original_owner = db('SELECT * FROM fresh_users WHERE id=?', [before_owner['id']])[0]
    setup_evidence = {'login': {'status': login.status_code, 'dashboard': urlparse(login.url).query == 'view=dashboard',
        'changed_fields': sorted(k for k in set(before_owner) | set(original_owner) if before_owner.get(k) != original_owner.get(k)),
        'profile_unchanged': db('SELECT * FROM fresh_staff_profiles WHERE user_id=?', [before_owner['id']]) == [before_profile]}}
    (qa/'domain-renewal-dispatch-setup.json').write_text(json.dumps(setup_evidence, indent=2), encoding='utf-8')
    check('Renewal dispatch setup preserves original owner authority across normal login', login.status_code == 200 and setup_evidence['login']['dashboard']
          and {k: v for k, v in before_owner.items() if k != 'last_login'} == {k: v for k, v in original_owner.items() if k != 'last_login'}
          and original_owner['last_login'] is not None and setup_evidence['login']['profile_unchanged'])
    check('Renewal dispatch dedicated owner starts absent', not db('SELECT id FROM fresh_users WHERE email=?', [email]))
    submit(setup, admin+'?view=staff', 'staff_save', {'id': '0', 'name': 'Domain renewal approval owner', 'email': email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': password})
    actor = db('SELECT id FROM fresh_users WHERE email=?', [email])[0]['id']
    invite = chosen_form(setup, admin+'?view=staff', 'staff_invite', 'id', actor, {'owner_password': password})
    invitation = soup(invite).select_one('[data-staff-invite]')['href']
    activation = requests.Session(); sessions.append(activation)
    activation_page = activation.get(invitation)
    activated = activation.post(admin+'?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(activation_page),
        'token': parse_qs(urlparse(invitation).fragment)['token'][0], 'password': password, 'confirm_password': password})
    setup_evidence['activation'] = {'status': activated.status_code, 'login_page': urlparse(activated.url).query == 'view=login',
        'distinct_actor': actor != original_owner['id'], 'authorized_owner': db('SELECT role,enabled FROM fresh_staff_profiles WHERE user_id=?', [actor]) == [{'role': 'owner', 'enabled': 1}],
        'activated': db('SELECT password_hash FROM fresh_users WHERE id=?', [actor])[0]['password_hash'] != '',
        'original_owner_unchanged': db('SELECT * FROM fresh_users WHERE id=?', [original_owner['id']]) == [original_owner],
        'original_profile_unchanged': db('SELECT * FROM fresh_staff_profiles WHERE user_id=?', [original_owner['id']]) == [before_profile]}
    (qa/'domain-renewal-dispatch-setup.json').write_text(json.dumps(setup_evidence, indent=2), encoding='utf-8')
    check('Renewal dispatch activates separate owner without changing original authority', all(v is True for k, v in setup_evidence['activation'].items() if k != 'status') and activated.status_code == 200)
    owner = requests.Session(); sessions.append(owner)
    submit(owner, admin+'?view=login', 'login', {'email': email, 'password': password})
    profile = rows('provider_profiles')[0]
    check('Renewal dispatch owner begins with no provider read or approval history', not db('SELECT id FROM fresh_provider_refreshes WHERE actor_id=?', [actor]) and not db('SHOW TABLES LIKE "fresh_domain_renewal_invoices"'))
    diagnostics = []

    def evidence(label, response):
        diagnostics.append({'step': label, 'status': response.status_code, 'redirect_statuses': [r.status_code for r in response.history],
            'errors': [n.get_text(' ', strip=True).replace(password, '[redacted]').replace('SYNTHETIC_RAW_GENERAL_KEY', '[redacted]') for n in soup(response).select('.notice.error')],
            'provider_calls': provider.calls, 'renewal_posts': len(provider.renewal_posts)})
        (qa/'domain-renewal-dispatch-responses.json').write_text(json.dumps(diagnostics, indent=2), encoding='utf-8')

    def snapshot():
        return digest({name: rows(name) for name in ['domain_renewal_invoices', 'domain_renewal_dispatches', 'domain_renewal_dispatch_ends', 'provider_operation_grants', 'provider_operations', 'invoice_payments', 'audit']})

    try:
        clients = []
        for number in [1, 2]:
            address = 'renewal-dispatch-client'+str(number)+'@example.invalid'
            chosen_form(owner, admin+'?view=studio-clients', 'studio_client', 'id', 0, {'name': 'Renewal dispatch client '+str(number), 'email': address})
            cid = db('SELECT id FROM fresh_studio_clients WHERE email=?', [address])[0]['id']
            invitation = chosen_form(owner, admin+'?view=studio-clients', 'studio_invite', 'id', cid, {})
            link = soup(invitation).select_one('.notice.info a[href*="invite="]')['href']
            client = requests.Session(); sessions.append(client)
            submit(client, link, 'activate', {'password': password, 'confirm_password': password})
            response = submit(client, portal, 'login', {'email': address, 'password': password})
            check('Renewal payment client '+str(number)+' activates and logs in normally', response.status_code == 200 and 'Welcome, ' in response.text)
            clients.append((cid, client))
        (cid, client), (_, foreign) = clients
        response = submit(owner, admin+'?view=studio-clients', 'studio_job', {'client_id': str(cid), 'title': 'Domain renewal invoice project', 'stage': 'discovery', 'summary': 'Synthetic exact renewal invoices', 'milestones': 'Review agreed renewal terms'})
        jobs = db('SELECT id,client_id,title,stage FROM fresh_studio_jobs WHERE client_id=? AND title=?', [cid, 'Domain renewal invoice project'])
        project_created = response.status_code == 200 and [r.status_code for r in response.history] == [303] and not soup(response).select('.notice.error') and len(jobs) == 1 and jobs[0]['client_id'] == cid and jobs[0]['stage'] == 'discovery' and jobs[0]['title'] == 'Domain renewal invoice project' and parse_qs(urlparse(response.url).query) == {'view': ['studio-job'], 'id': [str(jobs[0]['id'])]}
        if not project_created: evidence('Create renewal invoice project', response)
        check('Renewal payment project uses supported stage and exact client before invoices', project_created)
        job = jobs[0]['id']
        job_url = admin+'?view=studio-job&id='+str(job)
        for view, action, fields in [('payments', 'payments_migrate', {'owner_password': password, 'confirm': '1'}),
                                     ('invoice-payments', 'payments_ledger_setup', {'owner_password': password, 'confirm': '1'}),
                                     ('provider-operations', 'provider_operations_setup', {'current_password': password})]:
            target = admin+'?view='+view
            if soup(owner.get(target)).select('[name=action][value='+action+']'):
                response = submit(owner, target, action, fields); evidence('Prepare '+view, response)
        domains = [{'id': 19002, 'name': 'paid-renewal.fixture.invalid'}, {'id': 19003, 'name': 'failed-renewal.fixture.invalid'}]
        provider.registrar_domains = original_domains+domains; provider.registrar_mode = 'success'
        last = db('SELECT MAX(started_epoch) AS epoch FROM fresh_provider_refreshes WHERE profile_id=?', [profile['id']])[0]['epoch']
        time.sleep(max(0, int(last or 0)+31-time.time()))
        response = submit(owner, settings, 'provider_inventory_registrar_refresh', {'current_password': password}); evidence('Explicit current registered domains', response)
        check('Renewal payment setup makes exactly one explicit owner registrar GET', provider.calls == initial_calls+1 and provider.posts == initial_posts)
        terms = {'name': 'Approved domain renewal service', 'cadence': 'one-time', 'price': '24.99', 'inclusions': 'Arrange only the displayed domain term and privacy choice', 'exclusions': 'Automatic future renewal', 'limits': '', 'support': 'Studio assistance', 'current_password': password}
        response = submit(owner, admin+'?view=service-plan', 'service_plans_create', terms)
        plan = parse_qs(urlparse(response.url).query)['record'][0]; plan_url = admin+'?view=service-plan&record='+plan
        submit(owner, plan_url, 'service_plans_publish', {'confirm': '1', 'current_password': password})
        created = []
        for index, domain in enumerate(domains):
            chosen_form(owner, settings, 'provider_inventory_registrar_link', 'domain_id', domain['id'], {'client_id': str(cid), 'current_password': password})
            link = db('SELECT id FROM fresh_provider_registrar_links WHERE domain_id=?', [domain['id']])[0]['id']
            chosen_form(client, portal+'?domain_renewals=1', 'domain_renewal_create', 'link_id', link, {'requested_years': '2', 'renew_privacy': 'ask', 'request_confirm': '1'})
            rid = db('SELECT id FROM fresh_domain_renewal_requests WHERE link_id=?', [link])[0]['id']
            response = submit(owner, plan_url, 'service_plans_offer', {'client_id': str(cid), 'confirm': '1', 'current_password': password})
            agreement = parse_qs(urlparse(response.url).query)['record'][0]
            chosen_form(owner, admin+'?view=domain-renewal-agreements&request='+rid, 'provider_inventory_renewal_agreement_bind', 'agreement_id', agreement,
                        {'renew_privacy': '0', 'offer_expires': (datetime.now(timezone.utc)+timedelta(hours=1)).strftime('%Y-%m-%dT%H:%M'), 'confirm': '1', 'current_password': password})
            submit(client, portal+'?agreement='+agreement, 'service_agreements_consent', {'confirm': '1'})
            submit(owner, admin+'?view=service-agreement&record='+agreement, 'service_plans_activate', {'confirm': '1', 'current_password': password})
            target = admin+'?view=domain-renewal-dispatch&request='+rid
            if index == 0:
                response = submit(owner, target, 'provider_renewal_dispatch_prepare', {'owner_password': password}); evidence('Prepare permanent purpose and approval history', response)
                check('Explicit renewal setup creates empty charging history without a POST', rows('domain_renewal_invoices') == rows('domain_renewal_dispatches') == rows('domain_renewal_dispatch_ends') == [] and provider.posts == initial_posts)
            title = 'Exact renewal fee '+str(index+1)
            submit(owner, job_url, 'studio_invoice', {'title': title, 'amount': '24.99', 'instructions': 'Synthetic instruction: pay the displayed exact renewal service only.'})
            invoice = db('SELECT * FROM fresh_studio_invoices WHERE title=?', [title])[0]
            form = form_data(form_for(owner.get(target), 'provider_renewal_dispatch_allocate'))
            if index == 0:
                before = snapshot()
                for label, changes in [('CSRF', {'csrf': 'bad'}), ('password', {'owner_password': 'wrong'}), ('terms', {'agreement_sha256': 'f'*64})]:
                    response = owner.post(target, data={**form, 'allocation_confirmed': '1', 'owner_password': password, **changes})
                    check('Renewal invoice allocation rejects '+label+' before any mutation or send', snapshot() == before and provider.posts == initial_posts)
                for label, bad in [('missing', None), ('stale', '999999999')]:
                    fields = {**form, 'allocation_confirmed': '1', 'owner_password': password}
                    if bad is None: fields.pop('invoice_id')
                    else: fields['invoice_id'] = bad
                    response = owner.post(target, data=fields)
                    check('Actual allocation retry with '+label+' selector cannot adopt a fresh invoice review', snapshot() == before and not soup(response).select('.renewal-invoice-allocation') and 'Reload this page' in response.text)
            response = chosen_form(owner, target, 'provider_renewal_dispatch_allocate', 'invoice_id', invoice['id'], {'allocation_confirmed': '1', 'owner_password': password}); evidence('Reserve unpaid invoice '+str(index+1), response)
            allocation = db('SELECT * FROM fresh_domain_renewal_invoices WHERE invoice_id=?', [invoice['id']])[0]
            check('Renewal invoice '+str(index+1)+' purpose precedes all payment and provider work', not db('SELECT id FROM fresh_invoice_payments WHERE invoice_id=?', [invoice['id']]) and len(provider.renewal_posts) == len(initial_renewals)+index and 'Approve and submit this renewal once' not in response.text)
            statement = portal+'?invoice='+str(invoice['id'])
            response = client.get(statement)
            purpose = soup(response).select_one('.renewal-invoice-purpose')
            check('Client invoice '+str(index+1)+' shows exact agreed renewal before payment instructions', purpose is not None and domain['name'] in purpose.text and '2 years' in purpose.text and 'Do not renew privacy' in purpose.text and 'USD 24.99' in purpose.text and response.text.index('Domain renewal invoice purpose') < response.text.index('Synthetic instruction:'))
            if index == 0:
                check('Reserved invoice purpose refuses foreign support and anonymous clients', domain['name'] not in foreign.get(statement).text and domain['name'] not in client.get(statement+'&support=fixture').text and domain['name'] not in requests.get(statement).text)
                workspace = client.get(portal+'?job='+str(job))
                check('Client workspace also shows original renewal purpose before payment instructions', workspace.text.index('Domain renewal invoice purpose') < workspace.text.index('Synthetic instruction:'))
            ledger_url = admin+'?view=invoice-payments&invoice_id='+str(invoice['id'])
            response = submit(owner, ledger_url, 'payments_ledger_receive', {'amount': '24.99', 'method': 'bank', 'reference': 'Synthetic verified domain transfer '+str(index), 'received_on': datetime.now(timezone.utc).date().isoformat(), 'confirm': '1'}); evidence('Record verified external receipt '+str(index+1), response)
            receipt = db('SELECT * FROM fresh_invoice_payments WHERE invoice_id=?', [invoice['id']])[0]
            check('Invoice '+str(index+1)+' payment alone never dispatches renewal', receipt['kind'] == 'manual' and db('SELECT status FROM fresh_studio_invoices WHERE id=?', [invoice['id']]) == [{'status': 'paid'}] and not db('SELECT id FROM fresh_domain_renewal_dispatches WHERE allocation_id=?', [allocation['id']]))
            approval = form_data(form_for(owner.get(target), 'provider_renewal_dispatch_send'))
            fields = {'owner_password': password, **dict.fromkeys(['eligible_years', 'privacy_scope', 'provider_cost', 'balance_charge'], '1')}
            if index == 0:
                before = snapshot(); network = provider.calls
                for label, changes in [('CSRF', {'csrf': 'bad'}), ('password', {'owner_password': 'wrong'}), ('scope', {'review_sha256': 'f'*64}), ('cost approval', {'provider_cost': ''}), ('stale selector', {'allocation_id': 'f'*32})]:
                    response = owner.post(target, data={**approval, **fields, **changes})
                    check('Captured renewal approval rejects '+label+' before DNS or journal mutation', snapshot() == before and provider.calls == network)
                    if label == 'stale selector': check('Actual stale approval selector does not receive refreshed authority', not soup(response).select('.renewal-dispatch-approval') and 'Reload this page' in response.text)
                for session, url, audience in [(owner, target, 'Owner'), (client, statement, 'Client')]:
                    context = browser.new_context(); errors = []
                    try:
                        context.add_cookies([{'name': c.name, 'value': c.value, 'url': base+'/fresh/'} for c in session.cookies])
                        page = context.new_page(); page.on('pageerror', lambda e: errors.append(str(e)))
                        for width, height, label in [(1440, 1000, 'Desktop'), (320, 740, 'Mobile')]:
                            page.set_viewport_size({'width': width, 'height': height}); page.goto(url, wait_until='domcontentloaded')
                            expect(page.locator('.renewal-invoice-purpose').first).to_contain_text(domain['name'])
                            page.evaluate('document.fonts.ready')
                            page.screenshot(path=str(qa/('Domain-Renewal-Dispatch-'+audience+'-'+label+'.png')), full_page=True)
                            check('Renewal dispatch '+audience+' '+label+' fits actual viewport', not errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
                        if audience == 'Owner':
                            form = page.locator('.renewal-dispatch-approval')
                            approval = {name: form.locator('[name='+name+']').input_value() for name in ['csrf', 'action', 'dispatch_id', 'allocation_id', 'review_sha256']}
                            for name in ['eligible_years', 'privacy_scope', 'provider_cost', 'balance_charge']: form.locator('[name='+name+']').check()
                            form.locator('[name=owner_password]').fill(password)
                            provider.renewal_mode = 'success'
                            with page.expect_navigation(wait_until='domcontentloaded'):
                                form.get_by_role('button', name='Approve and submit this renewal once', exact=True).click()
                            expect(page.locator('.renewal-invoice-purpose').first).to_contain_text('20i reported this renewal successful.')
                    finally:
                        context.close()
            else:
                provider.renewal_mode = 'failure'
                response = owner.post(target, data={**approval, **fields}); evidence('Provider boolean false response', response)
            dispatch = db('SELECT * FROM fresh_domain_renewal_dispatches WHERE allocation_id=?', [allocation['id']])[0]
            result = db('SELECT status,provider_code FROM fresh_provider_operations WHERE idempotency_key=?', ['domain-renewal:'+dispatch['id']])[0]
            check('Explicit paid renewal '+str(index+1)+' sends exact scope once through retained ledger', provider.renewal_posts[-1] == {'path': '/reseller/*/renewDomain', 'body': {'name': domain['name'], 'years': 2, 'renewPrivacy': False}} and result == ({'status': 'succeeded', 'provider_code': 'renewal_reported_success'} if index == 0 else {'status': 'failed', 'provider_code': 'renewal_reported_failure'}))
            before = snapshot(); network = provider.calls
            owner.post(target, data={**approval, **fields})
            check('Captured renewal '+str(index+1)+' approval replay never sends twice', snapshot() == before and provider.calls == network)
            response = client.get(statement)
            check('Client saved renewal '+str(index+1)+' outcome is honest without new provider traffic', ('20i reported this renewal successful.' if index == 0 else 'does not prove no balance charge occurred') in response.text and provider.calls == network)
            created.append({'request_id': rid, 'invoice_id': invoice['id'], 'allocation_id': allocation['id'], 'dispatch_id': dispatch['id']})
        failed = created[1]
        chosen_form(owner, settings, 'provider_inventory_renewal_review', 'request_id', failed['request_id'], {'current_password': password, 'command': 'declined', 'review_note': 'Investigate the recorded provider failure before any further renewal.'})
        response = client.get(portal+'?domain_renewals=1')
        page = soup(response)
        failed_link = db('SELECT link_id FROM fresh_domain_renewal_requests WHERE id=?', [failed['request_id']])[0]['link_id']
        failed_history = [n for n in page.select('[data-domain-renewal-history] [data-renewal-request]') if n.select_one('h3').get_text(strip=True) == domains[1]['name']]
        check('Provider false remains blocked for new intake after request decline', response.status_code == 200
              and len(failed_history) == 1 and failed_history[0].select_one('strong').get_text(strip=True) == 'Declined'
              and not page.select('form:has([name=action][value=domain_renewal_create]) [name=link_id][value="'+failed_link+'"]'))
        actor_reads = db('SELECT f.operation,r.state FROM fresh_provider_refreshes f LEFT JOIN fresh_provider_refresh_results r ON r.id=f.id WHERE f.actor_id=? ORDER BY f.seq', [actor])
        check('Renewal dispatch uses one owner GET and two explicitly approved POSTs only', actor_reads == [{'operation': 'domains.list', 'state': 'complete'}] and provider.calls == initial_calls+3 and provider.posts == initial_posts+provider.renewal_posts[len(initial_renewals):] and len(provider.renewal_posts) == len(initial_renewals)+2 and not provider.errors)
        preserved = {}
        for name, original in inherited.items():
            current_rows = optional_rows('fresh_', name)
            if original is None:
                preserved[name] = current_rows is None
            else:
                current = {row['id']: row for row in current_rows}
                preserved[name] = all(current.get(row['id']) == row for row in original)
        check('Renewal dispatch preserves every inherited fresh and unrelated existing record', all(preserved.values()) and all(digest(optional_rows('ci_', name)) == value for name, value in unrelated.items())
              and db('SELECT * FROM fresh_users WHERE id=?', [original_owner['id']]) == [original_owner]
              and db('SELECT * FROM fresh_staff_profiles WHERE user_id=?', [original_owner['id']]) == [before_profile]
              and (qa/'mail-capture.txt').read_bytes() == mailbox)
        (qa/'domain-renewal-dispatch-history.json').write_text(json.dumps({'scope': 'Independent fresh_ renewal dispatch fixture; hashes only for inherited rows.', 'actor_reads': actor_reads, 'approved_posts': provider.renewal_posts[len(initial_renewals):],
            'created': created, 'inherited': {name: {'before_sha256': digest(value), 'rows': None if value is None else len(value), 'preserved': preserved[name]} for name, value in inherited.items()}, 'existing_unchanged': True}, indent=2), encoding='utf-8')
    finally:
        provider.registrar_domains, provider.registrar_mode, provider.renewal_mode = original_domains, original_mode, original_renewal_mode
        for session in sessions: session.close()
