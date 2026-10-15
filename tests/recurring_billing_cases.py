"""Actual staff/client recurring forms. Historical invoice dates are synthetic fixtures."""
import datetime
import json
import os
import subprocess
from pathlib import Path
from urllib.parse import parse_qs, urlparse
import requests
from release_cases import db, chosen_form, fixture_command
from staff_cases import OWNER


def run_browser(env, browser):
    globals().update({k: env[k] for k in ['BASE', 'QA', 'check', 'soup', 'form_data', 'form_for', 'submit']})
    db('DELETE FROM ci_rate_limits')
    admin, portal = BASE+'/existing/admin/index.php', BASE+'/existing/portal/index.php'
    owner = requests.Session(); submit(owner, admin+'?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    listing = admin+'?view=recurring-billing'
    original_setup = form_data(form_for(owner.get(listing), 'recurring_setup'))
    check('Recurring staff entry is present before owner setup', 'Recurring billing' in owner.get(admin).text and 'Prepare recurring billing' in owner.get(listing).text)
    for delta, label in [({'owner_password': OWNER, 'confirm': '1', 'csrf': 'bad'}, 'CSRF'), ({'owner_password': 'wrong', 'confirm': '1'}, 'owner password'), ({'owner_password': OWNER}, 'backup confirmation')]:
        response = submit(owner, listing, 'recurring_setup', delta)
        check('Recurring setup requires '+label, not db("SELECT value FROM ci_settings WHERE name='recurring_billing_schema_version'"))
        check('Failed recurring setup never echoes password '+label, OWNER not in response.text and 'value="wrong"' not in response.text)
    submit(owner, listing, 'recurring_setup', {'owner_password': OWNER, 'confirm': '1'})
    check('Explicit recurring owner setup is additive and enabled', db("SELECT value FROM ci_settings WHERE name='recurring_billing_schema_version'")==[{'value': '1'}])
    clients=[]
    for n in [1,2]:
        email='recurring-browser'+str(n)+'@example.invalid'
        chosen_form(owner, admin+'?view=studio-clients', 'studio_client', 'id', 0, {'name':'Recurring browser client '+str(n), 'email':email})
        cid=db('SELECT id FROM ci_studio_clients WHERE email=?',[email])[0]['id']
        response=chosen_form(owner,admin+'?view=studio-clients','studio_invite','id',cid,{})
        invitation=soup(response).select_one('.notice.info a[href*="invite="]')['href']; client=requests.Session()
        submit(client,invitation,'activate',{'password':OWNER,'confirm_password':OWNER});submit(client,portal,'login',{'email':email,'password':OWNER});clients.append((cid,client))
    (cid,client),(_,foreign)=clients
    submit(owner,admin+'?view=studio-clients','studio_job',{'client_id':str(cid),'title':'Recurring browser project','summary':'Synthetic web care','stage':'design','milestones':''})
    job=db('SELECT id FROM ci_studio_jobs WHERE title=?',['Recurring browser project'])[0]['id']
    project=listing+'&job='+str(job)
    terms={'name':'Recurring browser care','cadence':'monthly','price':'125.01','inclusions':'Agreed web care','exclusions':'New features','limits':'','support':'Business hours','current_password':OWNER}
    response=submit(owner,admin+'?view=service-plan','service_plans_create',terms);plan=parse_qs(urlparse(response.url).query)['record'][0]
    plan_url=admin+'?view=service-plan&record='+plan
    submit(owner,plan_url,'service_plans_publish',{'confirm':'1','current_password':OWNER})
    response=submit(owner,plan_url,'service_plans_offer',{'client_id':str(cid),'confirm':'1','current_password':OWNER});source=parse_qs(urlparse(response.url).query)['record'][0]
    submit(client,portal+'?agreement='+source,'service_agreements_consent',{'confirm':'1'})
    submit(owner,admin+'?view=service-agreement&record='+source,'service_plans_activate',{'confirm':'1','current_password':OWNER})
    offer_url=project+'&source='+source
    response=owner.get(offer_url);draft=form_data(form_for(response,'recurring_offer'))
    anchor=(datetime.date.today()+datetime.timedelta(days=4)).isoformat()
    draft.update({'title':'Monthly care <review>','terms':'First line\r\nSecond line <script>literal</script>','anchor':anchor,'timezone':'America/Chicago','due_after_days':'14','intent':'preview'})
    before=db('SELECT * FROM ci_recurring_billing_schedules')
    response=owner.post(offer_url,data=draft)
    check('Recurring preview shows exact price calendar escaped terms without persistence','Preview only' in response.text and '$125.01 USD' in response.text and 'America/Chicago' in response.text and '&lt;script&gt;literal' in response.text and db('SELECT * FROM ci_recurring_billing_schedules')==before)
    invalid=owner.post(offer_url,data={**draft,'intent':'publish','confirm':'1','due_after_days':'61'})
    saved=form_data(form_for(invalid,'recurring_offer'))
    check('Invalid recurring offer keeps entered text date and original tokens',saved['due_after_days']=='61' and saved['request_id']==draft['request_id'] and saved['source_sha256']==draft['source_sha256'] and saved['title']==draft['title'] and saved['anchor']==anchor and db('SELECT * FROM ci_recurring_billing_schedules')==before)
    for delta,label in [({'confirm':'','intent':'publish'},'confirmation'),({'confirm':'1','intent':'publish','csrf':'bad'},'CSRF'),({'confirm':'1','intent':'publish','amount_cents':'1'},'browser price override')]:
        owner.post(offer_url,data={**draft,**delta});check('Recurring publication refuses missing or untrusted '+label,db('SELECT * FROM ci_recurring_billing_schedules')==before)
    response=owner.post(offer_url,data={**draft,'intent':'publish','confirm':'1'});schedule=parse_qs(urlparse(response.url).query)['schedule'][0]
    detail=project+'&schedule='+schedule;client_detail=portal+'?recurring=1&job='+str(job)+'&schedule='+schedule
    check('Published recurring offer retains exact source-derived price and normalized browser lines',json.loads(db('SELECT journal_json FROM ci_recurring_billing_schedules WHERE id=?',[schedule])[0]['journal_json'])['terms']['amount_cents']==12501 and 'First line\nSecond line' in json.loads(db('SELECT journal_json FROM ci_recurring_billing_schedules WHERE id=?',[schedule])[0]['journal_json'])['terms']['terms'])
    owner.post(offer_url,data={**draft,'intent':'publish','confirm':'1'});check('Recurring offer retry preserves one offer',len(db('SELECT id FROM ci_recurring_billing_schedules WHERE subscription_id=?',[source]))==1)
    check('Client recurring entry is discoverable from portal and project','Recurring billing' in client.get(portal).text and 'Project recurring billing' in client.get(portal+'?job='+str(job)).text)
    response=client.get(client_detail)
    check('Client recurring response is private',response.headers.get('Cache-Control')=='no-store, private' and response.headers.get('Referrer-Policy')=='no-referrer')
    check('Foreign client cannot inspect billing terms','Monthly care &lt;review&gt;' not in foreign.get(client_detail).text)
    check('Support parameter cannot expose recurring client terms',client.get(client_detail+'&support=invalid').status_code in [403,404])

    def command(session,url,name):
        response=session.get(url)
        for form in soup(response).select('form'):
            n=form.select_one('[name=command]')
            if n and n.get('value')==name:return form_data(form)
        (QA/'missing-recurring-command.html').write_text(response.text,encoding='utf-8');raise AssertionError('Missing command '+name)

    accept=command(client,client_detail,'accept');events=db('SELECT * FROM ci_recurring_billing_events')
    for delta,label in [({'confirm':''},'confirmation'),({'confirm':'1','terms_sha256':'0'*64},'exact terms hash'),({'confirm':'1','csrf':'bad'},'CSRF')]:
        client.post(client_detail,data={**accept,**delta});check('Client acceptance requires '+label,db('SELECT * FROM ci_recurring_billing_events')==events)
    owner.post(detail,data={**command(owner,detail,'withdraw'),'command':'accept','confirm':'1'});check('Staff cannot record client consent through billing form',db('SELECT * FROM ci_recurring_billing_events')==events)
    consent_context=browser.new_context();consent_context.add_cookies([{'name':c.name,'value':c.value,'url':BASE} for c in client.cookies]);consent_page=consent_context.new_page();consent_page.goto(client_detail)
    consent_form=consent_page.locator('form').filter(has=consent_page.locator('[name=command][value=accept]'));consent_form.locator('[name=confirm]').check()
    with consent_page.expect_navigation():consent_form.locator('button').click()
    consent_context.close();check('Actual Chromium client accepts exact billing terms',json.loads(db('SELECT journal_json FROM ci_recurring_billing_schedules WHERE id=?',[schedule])[0]['journal_json'])['events'][0]['actor']=='client:'+str(cid))
    activate=command(owner,detail,'activate');owner.post(detail,data={**activate,'confirm':'1'})
    pause=command(owner,detail,'pause');response=owner.post(detail,data={**pause,'confirm':'1','effective_date':(datetime.date.fromisoformat(anchor)+datetime.timedelta(days=1)).isoformat(),'reason':'Preserved reason <tag>'})
    retained=form_data(form_for(response,'recurring_command'));check('Rejected lifecycle date retains original revision request and reason',retained['revision']==pause['revision'] and retained['request_id']==pause['request_id'] and retained['reason']=='Preserved reason <tag>')
    owner.post(detail,data={**pause,'confirm':'1','reason':'Pause by agreement'})
    resume=command(owner,detail,'resume');owner.post(detail,data={**resume,'confirm':'1','reason':'Resume by agreement'})
    end=command(client,client_detail,'end');client.post(client_detail,data={**end,'confirm':'1','reason':'Client ends future billing'})
    response=client.post(client_detail,data={**accept,'confirm':'1','request_id':'f'*32,'revision':'0'})
    stale=form_data(form_for(response,'recurring_command'));check('Stale client action keeps old consent tokens after phase changes',stale['revision']=='0' and stale['request_id']=='f'*32 and 'Your retained attempt' in response.text)
    check('Billing history shows future effective dates and client attribution',anchor in client.get(client_detail).text and 'Client #'+str(cid) in client.get(client_detail).text and 'Client ends future billing' in client.get(client_detail).text)
    check('HTTP lifecycle records every intended consent activation pause resume and end',[e['command'] for e in json.loads(db('SELECT journal_json FROM ci_recurring_billing_schedules WHERE id=?',[schedule])[0]['journal_json'])['events']]==['accept','activate','pause','resume','end'])
    for decision,actor in [('decline',client),('withdraw',owner)]:
        replacement=form_data(form_for(owner.get(offer_url),'recurring_offer'));replacement.update({'terms':'New reviewed replacement terms','intent':'publish','confirm':'1'})
        replacement_response=owner.post(offer_url,data=replacement);replacement_id=parse_qs(urlparse(replacement_response.url).query)['schedule'][0]
        decision_url=(project if decision=='withdraw' else portal+'?recurring=1&job='+str(job))+'&schedule='+replacement_id
        actor.post(decision_url,data={**command(actor,decision_url,decision),'confirm':'1','reason':'Explicit '+decision+' decision'})
        check('HTTP '+decision+' retains explicit actor reason and terminal decision',json.loads(db('SELECT journal_json FROM ci_recurring_billing_schedules WHERE id=?',[replacement_id])[0]['journal_json'])['events'][-1]['command']==decision)

    fixture=env.get('seed_recurring_history')
    if fixture:historical=fixture(cid,job)
    else:
        result=subprocess.run(['php',str(Path(__file__).with_name('recurring_billing_browser_fixture.php')),str(env['WEB']/'existing'),str(cid),str(job)],text=True,capture_output=True,env={**os.environ,'CWP_TEST_DATABASE':'cwp_test'})
        if result.returncode:raise RuntimeError(result.stdout+result.stderr)
        historical=json.loads(result.stdout)
    history_url=project+'&schedule='+historical['schedule_id']+'&period_date=2020-02-01'
    issue=command(owner,history_url,'issue');owner.post(history_url,data={**issue,'confirm':'1','period_date':'2020-03-01'})
    check('Invoice POST cannot switch away from the reviewed period',not db('SELECT invoice_id FROM ci_recurring_billing_invoices WHERE schedule_id=?',[historical['schedule_id']]))
    response=owner.post(history_url,data={**issue,'confirm':'1'});invoice=parse_qs(urlparse(response.url).query)['invoice_id'][0]
    check('Actual historical period control creates existing-ledger invoice with exact amount',db('SELECT amount_cents FROM ci_studio_invoices WHERE id=?',[invoice])==[{'amount_cents':2500}])
    owner.post(history_url,data={**issue,'confirm':'1'});check('HTTP period invoice retry cannot duplicate debt',len(db('SELECT invoice_id FROM ci_recurring_billing_invoices WHERE schedule_id=?',[historical['schedule_id']]))==1)
    history_client=portal+'?recurring=1&job='+str(job)+'&schedule='+historical['schedule_id']+'&period_date=2020-02-01'
    response=client.get(history_client);check('Client sees original due dates and authorized existing invoice link','2020-02-15' in response.text and 'invoice='+invoice in response.text and '$25.00 USD' in response.text and 'Recorded payments:' in response.text)
    check('Existing client invoice opens from recurring history','25.00' in client.get(portal+'?invoice='+invoice).text)

    def capture(session,url,name):
        for width,label in [(1440,'desktop'),(320,'mobile')]:
            context=browser.new_context(viewport={'width':width,'height':1000});context.add_cookies([{'name':c.name,'value':c.value,'url':BASE} for c in session.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.goto(url);page.wait_for_load_state('networkidle')
            check('Recurring '+name+' '+label+' has no horizontal overflow or script errors',page.evaluate('document.documentElement.scrollWidth <= window.innerWidth') and not errors)
            page.screenshot(path=str(QA/('recurring-'+name+'-'+label+'.png')),full_page=True);context.close()
    capture(owner,history_url,'staff-invoices');capture(client,history_client,'client-invoices')
    # A new draft uses the same active source after its previous billing agreement ended.
    capture(owner,offer_url,'staff-offer')
    # Keep the explicit stale form visible for a real browser render, using its HTTP POST.
    for width,label in [(1440,'desktop'),(320,'mobile')]:
        context=browser.new_context(viewport={'width':width,'height':1000});context.add_cookies([{'name':c.name,'value':c.value,'url':BASE} for c in client.cookies]);page=context.new_page();page.goto(client_detail)
        with page.expect_navigation():page.evaluate("data=>{const f=document.createElement('form');f.method='post';for(const [name,value] of Object.entries(data)){const i=document.createElement('input');i.type='hidden';i.name=name;i.value=value;f.appendChild(i);}document.body.appendChild(f);f.submit();}",{**accept,'confirm':'1','request_id':'e'*32,'revision':'0'})
        page.wait_for_load_state('networkidle')
        check('Retained client error '+label+' keeps original revision without overflow',page.locator('[name=revision]').input_value()=='0' and page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'))
        page.screenshot(path=str(QA/('recurring-client-error-'+label+'.png')),full_page=True);context.close()

    # Established consent/invoice history cannot become an empty setup through a stale form.
    tables = ['recurring_billing_sources', 'recurring_billing_schedules', 'recurring_billing_events', 'recurring_billing_invoices']
    def saved_records():
        return {table: db('SELECT * FROM ci_'+table+' ORDER BY id') for table in tables+['studio_invoices', 'invoice_payments', 'audit']}
    # Individual binding loss must not reclassify recurring debt through editable text.
    owner_invoice = admin+'?view=invoice-payments&invoice_id='+invoice
    receipt = form_data(form_for(owner.get(owner_invoice), 'payments_ledger_receive'))
    receipt.update({'amount': '1.00', 'method': 'bank', 'reference': 'Synthetic lost mapping receipt',
                    'received_on': datetime.datetime.now(datetime.timezone.utc).date().isoformat(), 'confirm': '1'})
    original_binding = db('SELECT * FROM ci_recurring_billing_invoices WHERE invoice_id=?', [invoice])[0]
    original_invoice = db('SELECT reference,title,instructions FROM ci_studio_invoices WHERE id=?', [invoice])[0]
    before_origin_loss = saved_records()
    db('DELETE FROM ci_recurring_billing_invoices WHERE invoice_id=?', [invoice])
    db('UPDATE ci_studio_invoices SET reference=?,title=?,instructions=? WHERE id=?', ['Changed legacy reference', 'UNTRUSTED_RECURRING_TITLE', 'UNTRUSTED_RECURRING_PAYMENT_INSTRUCTIONS', invoice])
    try:
        response = owner.get(owner_invoice)
        check('Lost recurring row and changed reference hide owner receipt controls',
              'recurring invoice binding is missing' in response.text
              and soup(response).select_one('[name=action][value=payments_ledger_receive]') is None)
        response = client.get(portal+'?invoice='+invoice)
        check('Lost recurring row and changed reference hide client invoice balance',
              response.status_code==404 and 'Payment record unavailable' in response.text and 'Outstanding balance' not in response.text)
        project_response = client.get(portal+'?job='+str(job))
        check('Client project suppresses corrupted recurring title and payment instructions',
              'Invoice details are unavailable' in project_response.text
              and 'UNTRUSTED_RECURRING_TITLE' not in project_response.text
              and 'UNTRUSTED_RECURRING_PAYMENT_INSTRUCTIONS' not in project_response.text)
        owner.post(owner_invoice, data=receipt)
        owner.post(history_url, data={**issue, 'confirm': '1'})
        after_origin_loss = saved_records()
        check('Lost recurring binding rejects stale receipt and period issuance without side effects',
              after_origin_loss['invoice_payments']==before_origin_loss['invoice_payments']
              and after_origin_loss['audit']==before_origin_loss['audit']
              and len(after_origin_loss['studio_invoices'])==len(before_origin_loss['studio_invoices'])
              and len(after_origin_loss['recurring_billing_invoices'])==len(before_origin_loss['recurring_billing_invoices'])-1)
    finally:
        db('UPDATE ci_studio_invoices SET reference=?,title=?,instructions=? WHERE id=?', [original_invoice['reference'], original_invoice['title'], original_invoice['instructions'], invoice])
        columns = list(original_binding)
        db('INSERT INTO ci_recurring_billing_invoices (`'+'`,`'.join(columns)+'`) VALUES ('+','.join('?' for _ in columns)+')', [original_binding[c] for c in columns])
    check('Original recurring row restoration recovers exact invoice consent and audit',
          saved_records()==before_origin_loss and '25.00' in client.get(portal+'?invoice='+invoice).text)
    db('DELETE FROM ci_settings WHERE name=?', ['recurring_billing_schema_version'])
    db('UPDATE ci_studio_invoices SET reference=?,title=?,instructions=? WHERE id=?', ['Changed legacy reference', 'UNTRUSTED_RECURRING_TITLE', 'UNTRUSTED_RECURRING_PAYMENT_INSTRUCTIONS', invoice])
    try:
        response = owner.get(owner_invoice)
        check('Lost recurring version marker hides owner receipt controls despite changed reference',
              'Restore the original database' in response.text and soup(response).select_one('[name=action][value=payments_ledger_receive]') is None)
        response = client.get(portal+'?job='+str(job))
        check('Lost recurring version marker suppresses corrupted client project payment details',
              'Invoice details are unavailable' in response.text
              and 'UNTRUSTED_RECURRING_TITLE' not in response.text
              and 'UNTRUSTED_RECURRING_PAYMENT_INSTRUCTIONS' not in response.text)
    finally:
        db('INSERT INTO ci_settings (name,value) VALUES (?,?)', ['recurring_billing_schema_version', '1'])
        db('UPDATE ci_studio_invoices SET reference=?,title=?,instructions=? WHERE id=?', [original_invoice['reference'], original_invoice['title'], original_invoice['instructions'], invoice])
    check('Restoring recurring version marker recovers original invoice history', saved_records()==before_origin_loss)
    original_schedule = db('SELECT * FROM ci_recurring_billing_schedules WHERE id=?', [historical['schedule_id']])[0]
    for corruption in ['missing', 'malformed']:
        if corruption == 'missing':
            check('Synthetic recovery fixture removes only the intended schedule', fixture_command({'operation': 'remove-recurring-schedule', 'schedule_id': historical['schedule_id']}) == {'removed': 1})
        else:
            db('UPDATE ci_recurring_billing_schedules SET journal_json=? WHERE id=?', ['{invalid', historical['schedule_id']])
        try:
            response = client.get(portal+'?job='+str(job))
            check('Client project remains usable with '+corruption+' recurring schedule',
                  response.status_code == 200 and 'Recurring browser project' in response.text
                  and 'Project conversation' in response.text and 'Private project files' in response.text
                  and 'Invoice details are unavailable' in response.text
                  and original_invoice['reference'] not in response.text
                  and 'Fatal error' not in response.text and 'Uncaught' not in response.text)
            check('Corrupt recurring schedule cannot expose another clients project: '+corruption,
                  'Recurring browser project' not in foreign.get(portal+'?job='+str(job)).text)
        finally:
            if corruption == 'missing':
                columns = list(original_schedule)
                db('INSERT INTO ci_recurring_billing_schedules (`'+'`,`'.join(columns)+'`) VALUES ('+','.join('?' for _ in columns)+')', [original_schedule[c] for c in columns])
            else:
                db('UPDATE ci_recurring_billing_schedules SET journal_json=? WHERE id=?', [original_schedule['journal_json'], historical['schedule_id']])
        check('Restoring '+corruption+' recurring schedule preserves exact history and restores project invoice',
              saved_records() == before_origin_loss and original_invoice['reference'] in client.get(portal+'?job='+str(job)).text)
    before_missing = saved_records()
    for table in tables:
        retained = 'ci_retained_'+table
        db('RENAME TABLE ci_'+table+' TO '+retained)
        try:
            response = owner.get(listing)
            check('Missing '+table+' owner view requires restoration without setup', 'Restore the original database' in response.text and soup(response).select_one('[name=action][value=recurring_setup]') is None)
            response = client.get(portal+'?recurring=1')
            check('Missing '+table+' client view hides billing records', 'Restore the original database' in response.text and 'Synthetic historical invoice agreement' not in response.text)
            owner.post(listing, data={**original_setup, 'owner_password': OWNER, 'confirm': '1'})
            check('Missing '+table+' stale setup cannot recreate retained billing history', not db('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', ['ci_'+table]))
        finally:
            db('RENAME TABLE '+retained+' TO ci_'+table)
        check('Restoring '+table+' preserves exact consent invoices and audit', saved_records()==before_missing)

    # Real receipt forms distinguish payment evidence from a provider order.
    before_review = saved_records()
    page_text = owner.get(history_url).text
    check('Staff sees incomplete verified payment evidence on its bound recurring invoice',
          'Payment evidence for service setup' in page_text
          and 'Verified external receipts: $0.00 USD' in page_text
          and 'Verified receipts do not yet cover this invoice.' in page_text)
    check('Payment evidence GET leaves invoices receipts and audit unchanged', saved_records() == before_review)
    check('Client invoice history excludes staff fulfillment review',
          'Payment evidence for service setup' not in client.get(history_client).text)
    actual_receipt = form_data(form_for(owner.get(owner_invoice), 'payments_ledger_receive'))
    actual_receipt.update({'amount': '25.00', 'method': 'bank', 'reference': 'Synthetic service setup receipt',
                          'received_on': datetime.datetime.now(datetime.timezone.utc).date().isoformat(), 'confirm': '1'})
    owner.post(owner_invoice, data=actual_receipt)
    paid_rows = db("SELECT id,amount_cents FROM ci_invoice_payments WHERE invoice_id=? AND kind='manual'", [invoice])
    check('Actual existing receipt form records exact synthetic service invoice payment',
          len(paid_rows) == 1 and paid_rows[0]['amount_cents'] == 2500)
    page_text = owner.get(history_url).text
    check('Staff review reflects verified receipt while retaining separate order requirement',
          'Verified external receipts: $25.00 USD' in page_text
          and 'An approved service order is still required before setup.' in page_text)
    check('Client sees its actual paid balance without private receipt reference',
          'Recorded payments: $25.00 USD' in client.get(history_client).text
          and 'Synthetic service setup receipt' not in client.get(history_client).text)
    capture(owner, history_url, 'staff-payment-evidence')
    reverse = form_data(form_for(owner.get(owner_invoice), 'payments_ledger_reverse'))
    reverse.update({'reason': 'Synthetic recorded payment was corrected', 'owner_password': OWNER, 'confirm': '1'})
    owner.post(owner_invoice, data=reverse)
    check('Actual reversal form retains the original receipt and blocks setup evidence',
          len(db('SELECT id FROM ci_invoice_payments WHERE invoice_id=?', [invoice])) == 2
          and 'Verified external receipts: $0.00 USD' in owner.get(history_url).text
          and 'Verified receipts do not yet cover this invoice.' in owner.get(history_url).text)


    # Initial hosting request uses the real historical first invoice and current client session.
    hosting_owner = admin+'?view=service-agreement&record='+historical['source_id']
    hosting_client = portal+'?agreement='+historical['source_id']
    hosting_setup = form_data(form_for(owner.get(hosting_owner), 'service_plans_order_setup'))
    check('Hosting order GET does not prepare storage', not db("SELECT value FROM ci_settings WHERE name='service_orders_schema_version'"))
    for values, label in [({'csrf': 'bad', 'confirm': '1', 'current_password': OWNER}, 'CSRF'),
                          ({'confirm': '1', 'current_password': 'wrong'}, 'owner password'),
                          ({'current_password': OWNER}, 'confirmation')]:
        response = owner.post(hosting_owner, data={**hosting_setup, **values})
        check('Hosting order setup enforces '+label, not db("SELECT value FROM ci_settings WHERE name='service_orders_schema_version'") and OWNER not in response.text)
    owner.post(hosting_owner, data={**hosting_setup, 'confirm': '1', 'current_password': OWNER})
    check('Actual hosting setup creates additive empty order history', db("SELECT value FROM ci_settings WHERE name='service_orders_schema_version'")==[{'value': '1'}] and not db('SELECT id FROM ci_service_orders'))
    offer_response = owner.get(hosting_owner)
    offer_form = form_for(offer_response, 'service_plans_order_offer')
    hosting_offer = form_data(offer_form)
    options = [o for o in offer_form.select('[name=invoice_choice] option') if o.get('value', '').startswith(invoice+':')]
    check('Owner sees original first recurring invoice as initial hosting choice', len(options)==1)
    hosting_offer.update({'invoice_choice': options[0]['value'], 'confirm': '1', 'current_password': OWNER})
    response = owner.post(hosting_owner, data=hosting_offer)
    order_row = db('SELECT * FROM ci_service_orders WHERE agreement_id=?', [historical['source_id']])[0]
    check('Actual owner offer pins existing client agreement invoice and original price',
          order_row['client_id']==cid and str(order_row['invoice_id'])==invoice and json.loads(order_row['journal_json'])['invoice']['amount_cents']==2500)
    owner.post(hosting_owner, data=hosting_offer)
    check('Exact HTTP hosting offer retry retains one order', len(db('SELECT id FROM ci_service_orders WHERE agreement_id=?', [historical['source_id']]))==1)
    hosting_accept = form_data(form_for(client.get(hosting_client), 'service_agreements_order_accept'))
    before_order = db('SELECT * FROM ci_service_orders WHERE id=?', [order_row['id']])
    foreign.post(hosting_client, data={**hosting_accept, 'domain_name': 'stolen.example', 'confirm': '1'})
    check('Foreign client cannot see or accept another hosting order', 'Confirm hosting request' not in foreign.get(hosting_client).text and db('SELECT * FROM ci_service_orders WHERE id=?', [order_row['id']])==before_order)
    for values, label in [({'csrf': 'bad', 'confirm': '1', 'domain_name': 'client.example'}, 'CSRF'),
                          ({'confirm': '1', 'domain_name': 'https://client.example'}, 'invalid domain'),
                          ({'domain_name': 'client.example'}, 'confirmation')]:
        response = client.post(hosting_client, data={**hosting_accept, **values})
        check('Client hosting acceptance rejects '+label, db('SELECT * FROM ci_service_orders WHERE id=?', [order_row['id']])==before_order)
    context=browser.new_context(viewport={'width':1440,'height':1000})
    context.add_cookies([{'name':c.name,'value':c.value,'url':BASE} for c in client.cookies])
    page=context.new_page();page.goto(hosting_client)
    form=page.locator('form').filter(has=page.locator('input[name=action][value=service_agreements_order_accept]'))
    form.locator('[name=domain_name]').fill('Client.Example.')
    form.locator('[name=confirm]').check()
    with page.expect_navigation():
        form.get_by_role('button',name='Confirm hosting request',exact=True).click()
    page.wait_for_load_state('networkidle')
    check('Real Chromium form confirms domain and displays pending setup', 'client.example' in page.content() and 'Setup is pending studio fulfillment.' in page.content())
    context.close()
    accepted_order = db('SELECT * FROM ci_service_orders WHERE id=?', [order_row['id']])[0]
    check('Browser acceptance retains actual client attribution and exact invoice', accepted_order['revision']==1 and db('SELECT actor_client_id FROM ci_service_order_events WHERE order_id=?', [order_row['id']])==[{'actor_client_id':cid}])
    client.post(hosting_client, data={**hosting_accept, 'domain_name': 'Client.Example.', 'confirm': '1'})
    check('Browser form replay cannot duplicate hosting acceptance', len(db('SELECT id FROM ci_service_order_events WHERE order_id=?', [order_row['id']]))==1)
    before_manual = db('SELECT * FROM ci_invoice_payments WHERE invoice_id=? ORDER BY id', [invoice])
    policy = form_data(form_for(owner.get(hosting_owner), 'service_plans_order_allow_manual'))
    policy.update({'reason':'Synthetic private temporary hosting permission','confirm':'1','current_password':OWNER})
    owner.post(hosting_owner, data=policy)
    check('Actual manual fulfillment form preserves unpaid invoice and receipts',
          'Commercial review is ready.' in owner.get(hosting_owner).text and db('SELECT * FROM ci_invoice_payments WHERE invoice_id=? ORDER BY id',[invoice])==before_manual)
    check('Client hosting screen hides private manual policy and receipt reference',
          policy['reason'] not in client.get(hosting_client).text and 'Synthetic service setup receipt' not in client.get(hosting_client).text)
    # Use the real owner/client forms in the extracted application; no provider call is needed.
    provider_posts_before = len(env['provider_server'].posts)
    provider_calls_before = env['provider_server'].calls
    provider_attempts_before = db('SELECT * FROM ci_provider_operations ORDER BY id')
    service_setup = form_data(form_for(owner.get(hosting_owner), 'service_plans_order_prepare_service'))
    check('Hosting service preparation GET performs no schema change', not db("SELECT value FROM ci_settings WHERE name='hosting_order_links_schema_version'"))
    check('Clients do not receive provider preparation forms', 'name="package_type"' not in client.get(hosting_client).text)
    for delta, label in [({'csrf':'bad','confirm':'1','current_password':OWNER},'CSRF'),
                         ({'confirm':'1','current_password':'wrong'},'owner password'),
                         ({'current_password':OWNER},'confirmation')]:
        response = owner.post(hosting_owner, data={**service_setup, **delta})
        check('Hosting history preparation requires '+label, not db("SELECT value FROM ci_settings WHERE name='hosting_order_links_schema_version'") and OWNER not in response.text)
    owner.post(hosting_owner, data={**service_setup,'confirm':'1','current_password':OWNER})
    check('Actual hosting preparation creates both native storage families', db("SELECT name,value FROM ci_settings WHERE name IN ('hosting_order_links_schema_version','hosting_records_schema_version') ORDER BY name")==[{'name':'hosting_order_links_schema_version','value':'1'},{'name':'hosting_records_schema_version','value':'1'}])
    mapping = form_data(form_for(owner.get(hosting_owner), 'service_plans_order_map_service'))
    check('Hosting browser fixture retains enabled original provider revision',
          db('SELECT enabled,has_credentials FROM ci_provider_revisions WHERE profile_id=? AND revision=?',[mapping['profile_id'],int(mapping['profile_revision'])])==[{'enabled':1,'has_credentials':1}])
    mapping.update({'reseller_id':'42','package_type':'811','package_label':'Synthetic <reviewed package>','package_region':'Synthetic location','package_websites':'1','package_disk_mb':'0','mapping_only':'1','confirm':'1','current_password':OWNER})
    for delta,label in [({'csrf':'bad'},'CSRF'),({'current_password':'wrong'},'owner password'),({'confirm':''},'confirmation'),({'mapping_only':''},'local mapping review'),({'package_websites':'-1'},'negative limit'),({'profile_revision':'0'},'stale connection'),({'order_hash':'a'*64},'stale order')]:
        response = owner.post(hosting_owner,data={**mapping,**delta})
        check('Actual hosting mapping rejects '+label, not db('SELECT id FROM ci_hosting_order_links') and not db('SELECT id FROM ci_hosting_records') and OWNER not in response.text)
    check('Failed mapping retains reviewed package text with HTML escaping', 'Synthetic &lt;reviewed package&gt;' in response.text and 'value="'+mapping['order_hash']+'"' not in response.text)
    foreign.post(hosting_client,data={**mapping,'action':'service_agreements_order_map_service'})
    check('Forged client mapping action cannot create a service', not db('SELECT id FROM ci_hosting_order_links'))
    capture(owner,hosting_owner,'staff-hosting-preparation')
    context=browser.new_context(viewport={'width':1440,'height':1000});context.add_cookies([{'name':c.name,'value':c.value,'url':BASE} for c in owner.cookies]);page=context.new_page();page.goto(hosting_owner)
    form=page.locator('form').filter(has=page.locator('[name=action][value=service_plans_order_map_service]'))
    for key in ['reseller_id','package_type','package_label','package_region','package_websites','package_disk_mb','current_password']:
        form.locator('[name='+key+']').fill(mapping[key])
    form.locator('[name=mapping_only]').check();form.locator('[name=confirm]').check();form.locator('button').click();page.wait_for_load_state('networkidle')
    (QA/'hosting-preparation-submit.json').write_text(json.dumps({'alerts':page.locator('.notice.error').all_text_contents(),'saved_associations':db('SELECT id,account_id,client_id FROM ci_hosting_order_links'),'saved_service_count':len(db('SELECT id FROM ci_hosting_records'))},indent=2))
    page.screenshot(path=str(QA/'recurring-staff-hosting-preparation-submit.png'),full_page=True)
    check('Real Chromium mapping submits and displays prepared pending service','Hosting record prepared' in page.content() and 'provider setup has not started' in page.content().lower())
    context.close()
    mapped = db('SELECT * FROM ci_hosting_order_links WHERE id=?',[order_row['id']])[0]
    account = db('SELECT * FROM ci_hosting_records WHERE id=?',[mapped['account_id']])[0]
    check('Saved HTTP service mapping retains exact client domain request and known limits', mapped['client_id']==cid and account['client_id']==cid and json.loads(mapped['binding_json'])['request']['params']['domain_name']=='client.example' and json.loads(account['initial_json'])['package']['limits']=={'websites':1,'disk_mb':0})
    client_progress=client.get(hosting_client)
    check('Client sees prepared service without private provider configuration','Hosting record prepared' in client_progress.text and mapped['account_id'] in client_progress.text and 'Synthetic location' not in client_progress.text and 'Synthetic &lt;reviewed package&gt;' not in client_progress.text and policy['reason'] not in client_progress.text)
    check('Foreign client cannot see the service reference',mapped['account_id'] not in foreign.get(hosting_client).text)
    check('Preparation performs no provider reads writes or ledger attempt',len(env['provider_server'].posts)==provider_posts_before and env['provider_server'].calls==provider_calls_before and db('SELECT * FROM ci_provider_operations ORDER BY id')==provider_attempts_before)
    capture(owner, hosting_owner, 'staff-hosting-order')
    capture(client, hosting_client, 'client-hosting-order')
    revoke = form_data(form_for(owner.get(hosting_owner), 'service_plans_order_revoke_manual'))
    owner.post(hosting_owner, data={**revoke,'reason':'Revoke this temporary policy','confirm':'1','current_password':OWNER})
    check('Actual policy revocation restores unpaid commercial blocker', 'verified payment incomplete' in owner.get(hosting_owner).text)
    cancel = form_data(form_for(client.get(hosting_client), 'service_agreements_order_cancel'))
    client.post(hosting_client, data={**cancel,'reason':'Cancel this synthetic hosting request','confirm':'1'})
    final_order = json.loads(db('SELECT journal_json FROM ci_service_orders WHERE id=?',[order_row['id']])[0]['journal_json'])
    check('Client cancels actual hosting request without deleting billing history',
          final_order['events'][-1]['command']=='cancel'
          and 'Hosting request cancelled' in client.get(hosting_client).text
          and db('SELECT * FROM ci_invoice_payments WHERE invoice_id=? ORDER BY id',[invoice])==before_manual)
    check('Actual client cancellation retains mapped service and shows honest progress',db('SELECT * FROM ci_hosting_order_links WHERE id=?',[order_row['id']])==[mapped] and db('SELECT * FROM ci_hosting_records WHERE id=?',[mapped['account_id']])==[account] and 'service record and invoice history are retained' in client.get(hosting_client).text)
    db('RENAME TABLE ci_hosting_order_links TO ci_retained_ui_hosting_order_links')
    try:
        for session,url in [(owner,hosting_owner),(client,hosting_client)]:
            response=session.get(url)
            check('Missing hosting association displays restoration without SQL/runtime error','Restore the original database' in response.text and 'Fatal error' not in response.text and 'SQLSTATE' not in response.text)
        owner.post(hosting_owner,data={**service_setup,'confirm':'1','current_password':OWNER})
        check('Stale preparation cannot recreate missing service history',not db('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['ci_hosting_order_links']))
    finally:
        db('RENAME TABLE ci_retained_ui_hosting_order_links TO ci_hosting_order_links')
    check('Exact service restoration preserves client progress and original mapping',db('SELECT * FROM ci_hosting_order_links WHERE id=?',[order_row['id']])==[mapped] and 'Hosting record prepared' in client.get(hosting_client).text)
    orders_before_restore = db('SELECT * FROM ci_service_orders ORDER BY id')
    order_events_before_restore = db('SELECT * FROM ci_service_order_events ORDER BY id')
    for name in ['service_orders','service_order_events']:
        retained='ci_retained_'+name
        db('RENAME TABLE ci_'+name+' TO '+retained)
        try:
            for session, url in [(owner,hosting_owner),(client,hosting_client)]:
                response=session.get(url)
                check('Missing '+name+' displays hosting restoration state', 'Restore the original database' in response.text and 'Fatal error' not in response.text)
            owner.post(hosting_owner,data={**hosting_setup,'confirm':'1','current_password':OWNER})
            check('Stale hosting setup cannot recreate '+name, not db('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['ci_'+name]))
        finally:
            db('RENAME TABLE '+retained+' TO ci_'+name)
        check('Restored '+name+' preserves exact hosting history', db('SELECT * FROM ci_service_orders ORDER BY id')==orders_before_restore and db('SELECT * FROM ci_service_order_events ORDER BY id')==order_events_before_restore)

    original_order_row = db('SELECT * FROM ci_service_orders WHERE id=?',[order_row['id']])[0]
    db('UPDATE ci_service_orders SET journal_json=? WHERE id=?',['{invalid',order_row['id']])
    try:
        for session, url in [(owner,hosting_owner),(client,hosting_client)]:
            response=session.get(url)
            check('Malformed order journal shows controlled restoration without runtime errors',
                  response.status_code==200 and 'Restore the original database' in response.text
                  and 'Fatal error' not in response.text and 'Uncaught' not in response.text)
    finally:
        db('UPDATE ci_service_orders SET journal_json=? WHERE id=?',[original_order_row['journal_json'],order_row['id']])
    check('Order journal restoration preserves exact original record',db('SELECT * FROM ci_service_orders WHERE id=?',[order_row['id']])==[original_order_row])
