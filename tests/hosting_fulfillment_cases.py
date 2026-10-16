"""Extracted-package HTTP/Chromium order fulfillment against the existing isolated TLS fixture."""
import datetime
import json
import os
import subprocess
from pathlib import Path
from urllib.parse import parse_qs, urlparse
from release_cases import db
from staff_cases import OWNER


def run_browser(env, browser, owner, client, foreign, cid, job, command, capture):
    base, web, qa = env['BASE'], env['WEB'], env['QA']
    check, form_for, form_data, soup = (env[k] for k in ['check', 'form_for', 'form_data', 'soup'])
    admin, portal = base+'/existing/admin/index.php', base+'/existing/portal/index.php'
    provider = env['provider_server']
    db('DELETE FROM ci_rate_limits')

    def post(session, url, action, changes):
        fields = form_data(form_for(session.get(url), action))
        return session.post(url, data={**fields, **changes})

    def context(name, paid=False, catalog=False):
        if env.get('seed_recurring_history'):
            historical = env['seed_recurring_history'](cid, job)
        else:
            result = subprocess.run([os.environ.get('PHP_BINARY','php'), str(Path(__file__).with_name('recurring_billing_browser_fixture.php')), str(web/'existing'), str(cid), str(job)], text=True, capture_output=True, env={**os.environ, 'CWP_TEST_DATABASE': 'cwp_test'})
            if result.returncode:
                raise RuntimeError(result.stdout+result.stderr)
            historical = json.loads(result.stdout)
        period = admin+'?view=recurring-billing&job='+str(job)+'&schedule='+historical['schedule_id']+'&period_date=2020-02-01'
        response = owner.post(period, data={**command(owner, period, 'issue'), 'confirm': '1'})
        invoice = parse_qs(urlparse(response.url).query)['invoice_id'][0]
        staff_url = admin+'?view=service-agreement&record='+historical['source_id']
        client_url = portal+'?agreement='+historical['source_id']
        offer_form = form_for(owner.get(staff_url), 'service_plans_order_offer')
        choice = next(o['value'] for o in offer_form.select('[name=invoice_choice] option') if o.get('value', '').startswith(invoice+':'))
        owner.post(staff_url, data={**form_data(offer_form), 'invoice_choice': choice, 'confirm': '1', 'current_password': OWNER})
        domain = name+'.example'
        post(client, client_url, 'service_agreements_order_accept', {'domain_name': domain, 'confirm': '1'})
        if paid:
            post(owner, admin+'?view=invoice-payments&invoice_id='+invoice, 'payments_ledger_receive', {'amount': '25.00', 'method': 'bank', 'reference': 'Synthetic fulfillment receipt '+name, 'received_on': datetime.datetime.now(datetime.timezone.utc).date().isoformat(), 'confirm': '1'})
        else:
            post(owner, staff_url, 'service_plans_order_allow_manual', {'reason': 'Synthetic reviewed fulfillment '+name, 'confirm': '1', 'current_password': OWNER})
        if catalog:
            from provider_plan_catalog_cases import prepare
            prepare(env, browser, owner, client, historical['source_id'], staff_url, client_url, capture)
        else:
            post(owner, staff_url, 'service_plans_order_map_service', {'reseller_id': '42', 'package_type': '811', 'package_label': 'Reviewed fulfillment package', 'package_region': 'Reviewed fixture location', 'package_websites': '1', 'mapping_only': '1', 'confirm': '1', 'current_password': OWNER})
        order = db('SELECT * FROM ci_service_orders WHERE agreement_id=?', [historical['source_id']])[0]
        link = db('SELECT * FROM ci_hosting_order_links WHERE id=?', [order['id']])[0]
        return {'owner': staff_url, 'client': client_url, 'invoice': invoice, 'order': order, 'link': link, 'domain': domain}

    def intent(c):
        return {**form_data(form_for(owner.get(c['owner']), 'service_plans_order_fulfill_service')), 'review_account_type': '1', 'review_defaults': '1', 'review_costs': '1', 'provision_confirmation': 'CREATE '+c['domain'], 'confirm': '1', 'current_password': OWNER}

    def history():
        return {table: db('SELECT * FROM ci_'+table+' ORDER BY id') for table in ['hosting_fulfillments', 'hosting_records', 'hosting_record_events', 'hosting_dispatches', 'hosting_operation_results', 'provider_operations', 'service_orders', 'service_order_events', 'invoice_payments', 'audit']}

    c = context('paid-fulfillment', paid=True, catalog=True)
    setup = form_data(form_for(owner.get(c['owner']), 'service_plans_order_prepare_fulfillment'))
    check('Fulfillment GET does not create approval storage', not db("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ci_hosting_fulfillments'"))
    calls, posts = provider.calls, len(provider.posts)
    for delta, label in [({'csrf': 'bad', 'confirm': '1', 'current_password': OWNER}, 'CSRF'), ({'confirm': '1', 'current_password': 'wrong'}, 'owner password'), ({'current_password': OWNER}, 'confirmation')]:
        response = owner.post(c['owner'], data={**setup, **delta})
        check('Fulfillment preparation rejects '+label, not db("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ci_hosting_fulfillments'") and OWNER not in response.text)
    owner.post(c['owner'], data={**setup, 'confirm': '1', 'current_password': OWNER})
    check('Owner prepares additive fulfillment history without contacting provider', db("SELECT value FROM ci_settings WHERE name='hosting_fulfillment_schema_version'")==[{'value': '1'}] and provider.calls==calls and len(provider.posts)==posts)
    data = intent(c); before = history()
    for delta, label in [({'csrf': 'bad'}, 'CSRF'), ({'confirm': ''}, 'confirmation'), ({'current_password': 'wrong'}, 'password'), ({'review_account_type': ''}, 'account type review'), ({'review_defaults': ''}, 'package defaults'), ({'review_costs': ''}, 'cost approval'), ({'provision_confirmation': 'CREATE foreign.example'}, 'accepted domain'), ({'link_hash': 'a'*64}, 'saved mapping'), ({'order_hash': 'b'*64}, 'current order')]:
        response = owner.post(c['owner'], data={**data, **delta})
        check('Actual provider approval refuses '+label, history()==before and len(provider.posts)==posts and OWNER not in response.text)
    client_csrf = form_data(form_for(client.get(c['client']), 'service_agreements_order_cancel'))['csrf']
    client.post(c['client'], data={**data, 'action': 'service_agreements_order_fulfill_service', 'csrf': client_csrf})
    check('Client cannot invoke owner provider setup with its own valid CSRF', history()==before and len(provider.posts)==posts)
    check('Foreign client sees no private fulfillment control', 'data-hosting-fulfillment' not in foreign.get(c['client']).text and c['domain'] not in foreign.get(c['client']).text)
    capture(owner, c['owner'], 'staff-fulfillment-approval')
    invoice_before = db('SELECT * FROM ci_studio_invoices WHERE id=?', [c['invoice']])
    receipt_before = db('SELECT * FROM ci_invoice_payments WHERE invoice_id=? ORDER BY id', [c['invoice']])
    provider.write_mode = 'success'
    browser_context = browser.new_context(viewport={'width': 1440, 'height': 1000})
    browser_context.add_cookies([{'name': cookie.name, 'value': cookie.value, 'url': base} for cookie in owner.cookies])
    page = browser_context.new_page(); errors=[]; page.on('pageerror', lambda e: errors.append(str(e))); page.goto(c['owner'])
    form = page.locator('form').filter(has=page.locator('[name=action][value=service_plans_order_fulfill_service]'))
    request = form.locator('[name=event_id]').input_value()
    replay = {item['name']: item['value'] for item in form.locator('input[type=hidden]').evaluate_all('(items)=>items.map(i=>({name:i.name,value:i.value}))')}
    for key in ['review_account_type', 'review_defaults', 'review_costs', 'confirm']:
        form.locator('[name='+key+']').check()
    form.locator('[name=provision_confirmation]').fill('CREATE '+c['domain']); form.locator('[name=current_password]').fill(OWNER)
    with page.expect_navigation():
        form.get_by_role('button', name='Request hosting setup', exact=True).click()
    page.wait_for_load_state('networkidle'); page.screenshot(path=str(qa/'hosting-fulfillment-submit.png'), full_page=True)
    check('Chromium submits the exact reviewed paid hosting order without errors', not errors and '20i accepted the request' in page.content())
    browser_context.close()
    approval = db('SELECT * FROM ci_hosting_fulfillments WHERE order_id=?', [c['order']['id']])[0]
    binding = json.loads(approval['binding_json'])
    provider_row = db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['hosting-order:'+request])[0]
    check('Actual TLS request matches the approved stored intent exactly', len(provider.posts)==posts+1 and provider.posts[-1]=={'path': '/reseller/42/addWeb', 'body': json.loads(binding['request']['body'])} and provider_row['attempts']==1 and provider_row['status']=='accepted_pending' and binding['commercial']['basis']=='verified-payment')
    check('Provider setup preserves the actual receipt and invoice', db('SELECT * FROM ci_studio_invoices WHERE id=?', [c['invoice']])==invoice_before and db('SELECT * FROM ci_invoice_payments WHERE invoice_id=? ORDER BY id', [c['invoice']])==receipt_before)
    result = json.loads(db('SELECT result_json FROM ci_hosting_operation_results WHERE id=?', [request])[0]['result_json'])
    check('Assigned provider ID remains pending on the original client service', result['outcome']=='accepted_pending' and result['resource_ref']==provider_row['external_id'] and not db('SELECT id FROM ci_hosting_resources WHERE account_id=?', [c['link']['account_id']]))
    before=history(); posts=len(provider.posts)
    owner.post(c['owner'], data={**replay, **{k:data[k] for k in ['review_account_type','review_defaults','review_costs','provision_confirmation','confirm','current_password']}})
    check('Actual approval POST replay does not resend or append history', len(provider.posts)==posts and history()==before)
    client_text=client.get(c['client']).text
    check('Actual client service view hides provider approval details', 'Hosting setup in progress' in client_text and 'data-hosting-fulfillment' not in client_text and 'Provider package number' not in client_text and 'Synthetic fulfillment receipt' not in client_text)
    capture(owner,c['owner'],'staff-fulfillment-pending');capture(client,c['client'],'client-fulfillment-pending')

    recovery = context('recovery-fulfillment'); recovery_data=intent(recovery); request=recovery_data['event_id']; posts=len(provider.posts)
    db("CREATE TRIGGER ci_fulfillment_result_failure BEFORE INSERT ON ci_audit FOR EACH ROW BEGIN IF NEW.action='hosting_records.result_recorded' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic hosting result audit failure'; END IF; END")
    try:
        owner.post(recovery['owner'],data=recovery_data)
    finally:
        db('DROP TRIGGER ci_fulfillment_result_failure')
    check('HTTP finalization failure retains original provider result and fence', len(provider.posts)==posts+1 and not db('SELECT id FROM ci_hosting_operation_results WHERE id=?',[request]) and db('SELECT status FROM ci_provider_operations WHERE idempotency_key=?',['hosting-order:'+request])==[{'status':'accepted_pending'}])
    recovery_form=form_data(form_for(owner.get(recovery['owner']),'service_plans_order_recover_fulfillment'));before=history();posts=len(provider.posts)
    for delta,label in [({'csrf':'bad'},'CSRF'),({'provider_hash':'c'*64},'retained response hash'),({'current_password':'wrong'},'owner password')]:
        owner.post(recovery['owner'],data={**recovery_form,'confirm':'1','current_password':OWNER,**delta})
        check('Actual local recovery refuses '+label,history()==before and len(provider.posts)==posts)
    capture(owner,recovery['owner'],'staff-fulfillment-recovery')
    owner.post(recovery['owner'],data={**recovery_form,'confirm':'1','current_password':OWNER})
    check('Owner recovers original pending response through HTTP without another send',len(provider.posts)==posts and json.loads(db('SELECT result_json FROM ci_hosting_operation_results WHERE id=?',[request])[0]['result_json'])['outcome']=='accepted_pending')
    before=history();owner.post(recovery['owner'],data={**recovery_form,'confirm':'1','current_password':OWNER})
    check('Actual recovery POST replay preserves every saved row',history()==before and len(provider.posts)==posts)

    failed=context('failed-fulfillment');provider.write_mode='failure';failed_data=intent(failed)
    try:
        owner.post(failed['owner'],data=failed_data)
    finally:
        provider.write_mode='success'
    retry=form_data(form_for(owner.get(failed['owner']),'service_plans_order_retry_fulfillment'));posts=len(provider.posts)
    owner.post(failed['owner'],data={**retry,'confirm':'1','current_password':OWNER})
    check('Actual definite-failure retry only prepares a new approval',len(provider.posts)==posts and soup(owner.get(failed['owner'])).select_one('[name=action][value=service_plans_order_fulfill_service]') is not None)
    unknown=context('unknown-fulfillment');unknown_data=intent(unknown);provider.write_mode='uncertain'
    try:
        owner.post(unknown['owner'],data=unknown_data)
    finally:
        provider.write_mode='success'
    response=owner.get(unknown['owner']);posts=len(provider.posts);before=history()
    check('Unknown HTTP result exposes no resend or retry action', 'provider outcome is unknown' in response.text and soup(response).select_one('[name=action][value=service_plans_order_fulfill_service]') is None and soup(response).select_one('[name=action][value=service_plans_order_retry_fulfillment]') is None)
    owner.post(unknown['owner'],data=unknown_data)
    check('Unknown original HTTP request replay cannot repeat purchase',history()==before and len(provider.posts)==posts)
    check('Client sees honest uncertainty after ambiguous provider response','Hosting setup outcome needs review' in client.get(unknown['client']).text)
    capture(owner,unknown['owner'],'staff-fulfillment-unknown');capture(client,unknown['client'],'client-fulfillment-unknown')
    check('Fulfillment fixture observed only valid protected requests',not provider.errors)

    from hosting_confirmation_cases import run_browser as confirm_browser
    confirm_browser(env,browser,owner,client,foreign,c,unknown,capture)
