"""Actual staff/client recurring forms. Historical invoice dates are synthetic fixtures."""
import datetime
import json
import os
import subprocess
from pathlib import Path
from urllib.parse import parse_qs, urlparse
import requests
from release_cases import db, chosen_form
from staff_cases import OWNER


def run_browser(env, browser):
    globals().update({k: env[k] for k in ['BASE', 'QA', 'check', 'soup', 'form_data', 'form_for', 'submit']})
    db('DELETE FROM ci_rate_limits')
    admin, portal = BASE+'/existing/admin/index.php', BASE+'/existing/portal/index.php'
    owner = requests.Session(); submit(owner, admin+'?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    listing = admin+'?view=recurring-billing'
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
