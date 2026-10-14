"""Actual project financial forms; synthetic HTTP, browser, consent and ledger evidence."""
import datetime
from urllib.parse import urlparse, parse_qs
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER


def run_browser(env, browser):
    globals().update({k: env[k] for k in ['BASE', 'QA', 'check', 'soup', 'token', 'form_data', 'form_for', 'submit']})
    db('DELETE FROM ci_rate_limits')
    admin, portal = BASE+'/existing/admin/index.php', BASE+'/existing/portal/index.php'
    owner = requests.Session(); submit(owner, admin+'?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    clients = []
    for n in [1, 2]:
        email = 'financial-client'+str(n)+'@example.invalid'
        chosen_form(owner, admin+'?view=studio-clients', 'studio_client', 'id', 0, {'name': 'Financial client '+str(n), 'email': email})
        cid = db('SELECT id FROM ci_studio_clients WHERE email=?', [email])[0]['id']
        invitation = chosen_form(owner, admin+'?view=studio-clients', 'studio_invite', 'id', cid, {})
        link = soup(invitation).select_one('.notice.info a')['href']; client = requests.Session()
        submit(client, link, 'activate', {'password': OWNER, 'confirm_password': OWNER})
        submit(client, portal, 'login', {'email': email, 'password': OWNER}); clients.append((client, cid))
    (client, cid), (other, _) = clients
    submit(owner, admin+'?view=studio-clients', 'studio_job', {'client_id': str(cid), 'title': 'Financial acceptance project', 'summary': 'Synthetic design service', 'stage': 'design', 'milestones': 'Original project milestones'})
    job = db('SELECT id FROM ci_studio_jobs WHERE title=?', ['Financial acceptance project'])[0]['id']
    staff_url, client_url = admin+'?view=studio-job&id='+str(job), portal+'?job='+str(job)
    submit(owner, staff_url, 'studio_publish', {'kind': 'proposal', 'title': 'Financial source proposal', 'body': 'One website design service', 'preview_url': ''})
    submit(client, client_url, 'decision', {'choice': 'approved', 'confirm': '1', 'feedback': 'Scope approved'})
    check('Project financial section is discoverable before preparation', 'Financial agreement' in owner.get(staff_url).text)
    for data, label in [({'owner_password': OWNER, 'confirm': '1', 'csrf': 'bad'}, 'CSRF'), ({'owner_password': 'wrong', 'confirm': '1'}, 'owner password'), ({'owner_password': OWNER, 'confirm': None}, 'backup acknowledgement')]:
        r = submit(owner, staff_url, 'design_financial_setup', data)
        check('Actual financial preparation requires '+label, not db("SELECT value FROM ci_settings WHERE name='design_payments_schema_version'"))
        check('Failed financial setup omits password for '+label, OWNER not in r.text and 'value="wrong"' not in r.text)
    submit(owner, staff_url, 'design_financial_setup', {'owner_password': OWNER, 'confirm': '1'})
    r = owner.get(staff_url)
    check('Explicit owner preparation enables financial form and invoice mapping', form_for(r, 'design_financial_form') is not None and db("SELECT value FROM ci_settings WHERE name='design_payment_invoices_schema_version'")==[{'value': '1'}])
    check('Financial project response is private', r.headers.get('Cache-Control')=='no-store, private' and r.headers.get('Referrer-Policy')=='no-referrer')
    original_job = db('SELECT * FROM ci_studio_jobs WHERE id=?', [job])
    first = form_data(form_for(r, 'design_financial_form'))
    first.update({'title': 'Design <agreement>', 'terms': 'Exact financial terms <script>literal</script>', 'items[0][description]': 'Design café <img src=x>', 'items[0][unit]': '100.01', 'form_action': 'publish'})
    owner.post(staff_url, data={**first, 'csrf': 'bad'})
    check('Actual offer publication requires CSRF', not db('SELECT id FROM ci_design_payment_events WHERE job_id=?', [job]))
    invalid = owner.post(staff_url, data={**first, 'items[0][unit]': '100.015'}); retained = form_data(form_for(invalid, 'design_financial_form'))
    check('Invalid exact decimal remains repairable with original request and head', retained['items[0][unit]']=='100.015' and retained['request_id']==first['request_id'] and retained['expected']==first['expected'] and not db('SELECT id FROM ci_design_payment_events WHERE job_id=?', [job]))
    r = owner.post(staff_url, data=first)
    check('Published full offer records once and opens exact event', len(db('SELECT id FROM ci_design_payment_events WHERE job_id=?', [job]))==1 and r.url.endswith('#financial-event-'+first['request_id']))
    check('Offered price is not shown as paid', 'Scheduled: $100.01 USD' in client.get(client_url).text and 'Recorded payments:' not in client.get(client_url).text)
    old_decision = form_data(form_for(client.get(client_url), 'financial_decide')); old_decision.update({'choice': 'accepted', 'confirm': '1'})
    second = form_data(form_for(owner.get(staff_url), 'design_financial_form')); second.update({'items[0][unit]': '120.01', 'form_action': 'publish'})
    owner.post(staff_url, data=second); latest = db('SELECT * FROM ci_design_payment_heads WHERE id=?', [job])
    r = client.post(client_url, data={**old_decision, 'comment': 'My retained feedback <text>'}); retained = form_data(form_for(r, 'financial_decide'))
    check('Stale client consent preserves original version and feedback without accepting replacement', retained['request_id']==old_decision['request_id'] and retained['expected']==old_decision['expected'] and retained['comment']=='My retained feedback <text>' and db('SELECT * FROM ci_design_payment_heads WHERE id=?', [job])==latest)
    r = owner.post(staff_url, data=first)
    check('Original offer retry preserves later financial terms', 'already saved' in r.text and db('SELECT * FROM ci_design_payment_heads WHERE id=?', [job])==latest)
    decision = form_data(form_for(client.get(client_url), 'financial_decide'))
    client.post(client_url, data={**decision, 'choice': 'accepted', 'confirm': '1', 'csrf': 'bad'})
    check('Client financial consent requires CSRF', db('SELECT * FROM ci_design_payment_heads WHERE id=?', [job])==latest)
    client.post(client_url, data={**decision, 'choice': 'accepted', 'confirm': ''})
    check('Client financial consent requires explicit acknowledgement', db('SELECT * FROM ci_design_payment_heads WHERE id=?', [job])==latest)
    client.post(client_url, data={**decision, 'choice': 'declined', 'confirm': '1', 'comment': ''})
    check('Decline without meaningful feedback leaves offer unchanged', db('SELECT * FROM ci_design_payment_heads WHERE id=?', [job])==latest)
    submit(client, client_url, 'financial_decide', {'choice': 'declined', 'confirm': '1', 'comment': 'Please split the payment.'})
    check('Client decline is visible in retained financial history', 'Please split the payment.' in owner.get(staff_url).text)
    third = form_data(form_for(owner.get(staff_url), 'design_financial_form')); third['form_action']='add_stage'
    r = owner.post(staff_url, data=third); split = form_data(form_for(r, 'design_financial_form'))
    split.update({'stages[0][value]': '25.00', 'stages[0][due_date]': '2028-02-29', 'form_action': 'preview'})
    r = owner.post(staff_url, data=split)
    check('No-JavaScript split preview conserves exact total', 'Unsaved preview' in r.text and 'Scheduled: $30.00 USD' in r.text and 'Scheduled: $90.01 USD' in r.text)
    split['form_action']='publish'; owner.post(staff_url, data=split)
    submit(owner, staff_url, 'design_financial_withdraw', {'comment': 'Replace before invoicing.'})
    check('Unissued offer can be withdrawn without deleting its history', 'Replace before invoicing.' in client.get(client_url).text and form_for(owner.get(staff_url), 'design_financial_form') is not None)
    final = form_data(form_for(owner.get(staff_url), 'design_financial_form')); final['form_action']='publish'; owner.post(staff_url, data=final)
    accepted = form_data(form_for(client.get(client_url), 'financial_decide')); accepted.update({'choice': 'accepted', 'confirm': '1', 'comment': 'I accept these installments.'})
    client.post(client_url, data=accepted)
    frozen_draft=form_data(form_for(owner.get(staff_url),'design_financial_form'));frozen_draft.update({'title':'My retained concurrent draft','items[0][unit]':'140.01','form_action':'publish'})
    check('Client explicitly accepts the exact split offer', db("SELECT kind FROM ci_design_payment_events WHERE job_id=? ORDER BY sequence DESC LIMIT 1", [job])==[{'kind': 'accepted'}])
    check('Foreign client cannot read financial agreement', other.get(client_url).status_code==404)
    issue = form_data(form_for(owner.get(staff_url), 'design_financial_issue')); issue['csrf']=token(owner.get(staff_url))
    r = owner.post(staff_url, data=issue); invoice = db('SELECT * FROM ci_studio_invoices WHERE job_id=? ORDER BY id', [job])[0]
    check('Issue action opens existing invoice with exact accepted deposit', 'view=invoice-payments' in r.url and 'invoice_id='+str(invoice['id']) in r.url and invoice['amount_cents']==3000)
    r = owner.post(staff_url, data=issue)
    check('Repeated issuance opens the same invoice without duplicate', 'invoice_id='+str(invoice['id']) in r.url and len(db('SELECT id FROM ci_studio_invoices WHERE job_id=?', [job]))==1)
    r = owner.get(staff_url)
    check('First invoice freezes terms and removes replacement withdrawal controls', 'terms are frozen' in r.text and 'value="design_financial_form"' not in r.text and 'value="design_financial_withdraw"' not in r.text)
    r=owner.post(staff_url,data=frozen_draft)
    check('Concurrent draft remains visible after issuance freezes editing','My retained concurrent draft' in r.text and '140.01' in r.text and 'name="expected" value="'+frozen_draft['expected']+'"' in r.text and 'value="design_financial_form"' not in r.text and len(db('SELECT id FROM ci_studio_invoices WHERE job_id=?',[job]))==1)
    r = owner.post(staff_url, data=final)
    check('Original published offer remains replayable after invoice freezes terms', 'already saved' in r.text and len(db('SELECT id FROM ci_studio_invoices WHERE job_id=?', [job]))==1)
    r = client.post(client_url, data=accepted)
    check('Original client decision remains replayable after invoicing', 'already recorded' in r.text)
    r = client.get(client_url)
    check('Client agreement separates recorded payments from remaining invoice balance', 'Recorded payments: $0.00 USD' in r.text and 'Invoice balance: $30.00 USD' in r.text)
    invoice_url=portal+'?invoice='+str(invoice['id'])
    check('Client invoice link resolves existing statement and foreign client is denied', client.get(invoice_url).status_code==200 and other.get(invoice_url).status_code in [403,404])
    ledger_url=admin+'?view=invoice-payments&invoice_id='+str(invoice['id'])
    paid=form_data(form_for(owner.get(ledger_url),'payments_ledger_receive'));paid.update({'amount':'10.00','method':'bank','reference':'Synthetic private financial receipt','received_on':datetime.datetime.now(datetime.timezone.utc).date().isoformat(),'confirm':'1'})
    owner.post(ledger_url,data=paid);entry=db('SELECT id FROM ci_invoice_payments WHERE invoice_id=? ORDER BY id DESC LIMIT 1',[invoice['id']])[0]['id']
    r=client.get(client_url)
    check('Actual financial agreement reflects verified partial receipt without private notes','Recorded payments: $10.00 USD' in r.text and 'Invoice balance: $20.00 USD' in r.text and 'Synthetic private financial receipt' not in r.text)
    owner.post(ledger_url,data=paid)
    check('Original financial receipt retry cannot duplicate payment',len(db('SELECT id FROM ci_invoice_payments WHERE invoice_id=?',[invoice['id']]))==1)
    check('Financial client can download authorized receipt and foreign client cannot',client.get(portal+'?receipt='+str(entry)).status_code==200 and other.get(portal+'?receipt='+str(entry)).status_code==404)
    check('Financial work preserves project fields', db('SELECT * FROM ci_studio_jobs WHERE id=?', [job])==original_job)
    contexts=[]; errors=[]
    def context(session, javascript=True):
        ctx=browser.new_context(viewport={'width':1440,'height':1000},device_scale_factor=1,reduced_motion='reduce',java_script_enabled=javascript);contexts.append(ctx)
        ctx.add_cookies([{'name':c.name,'value':c.value,'domain':urlparse(BASE).hostname,'path':c.path or '/'} for c in session.cookies]);return ctx
    def capture(page, role):
        for width,label in [(1440,'Desktop'),(320,'Mobile')]:
            page.set_viewport_size({'width':width,'height':1000});page.screenshot(path=str(QA/('Financial-'+role+'-'+label+'.png')),full_page=True)
            check('Financial '+role.lower()+' fits '+str(width)+' pixels',page.evaluate('document.documentElement.scrollWidth<=innerWidth'))
    try:
        p=context(owner).new_page();p.on('pageerror',lambda e:errors.append(str(e)));p.goto(staff_url);expect(p.locator('#financial-heading')).to_be_visible();capture(p,'Staff')
        cp=context(client).new_page();cp.goto(client_url);expect(cp.locator('#financial-heading')).to_be_visible();capture(cp,'Client')
        # A second actual project keeps the editor usable after the first one's invoices freeze it.
        submit(owner,admin+'?view=studio-clients','studio_job',{'client_id':str(cid),'title':'Financial draft controls','summary':'Draft editing','stage':'design','milestones':''})
        draft_job=db('SELECT id FROM ci_studio_jobs WHERE title=?',['Financial draft controls'])[0]['id'];draft_url=admin+'?view=studio-job&id='+str(draft_job)
        submit(owner,draft_url,'studio_publish',{'kind':'proposal','title':'Draft proposal','body':'Draft project scope','preview_url':''})
        submit(client,portal+'?job='+str(draft_job),'decision',{'choice':'approved','confirm':'1','feedback':''})
        nojs=context(owner,False).new_page();nojs.goto(draft_url);nojs.locator('.financial-editor summary').click();nojs.locator('#financial-title').fill('Keyboard draft');nojs.locator('#financial-title').press('Tab')
        expect(nojs.locator('#financial-terms')).to_be_focused();check('Financial editor keyboard focus reaches complete terms',True)
        nojs.locator('#financial-terms').fill('Exact no-JavaScript terms');nojs.locator('#financial-item-0-description').fill('Original item');nojs.locator('#financial-item-0-unit').fill('20.01')
        original_request=nojs.locator('.financial-offer-form [name=request_id]').input_value();nojs.get_by_role('button',name='Add priced item',exact=True).click()
        expect(nojs.locator('#financial-item-0-unit')).to_have_value('20.01');nojs.locator('#financial-item-1-description').fill('Second item');nojs.locator('#financial-item-1-unit').fill('10.00')
        nojs.locator('.financial-row').nth(1).get_by_role('button',name='Move up',exact=True).click()
        expect(nojs.locator('#financial-item-0-description')).to_have_value('Second item')
        nojs.locator('.financial-row').nth(0).get_by_role('button',name='Remove',exact=True).click();expect(nojs.locator('#financial-item-0-description')).to_have_value('Original item')
        check('No-JavaScript row additions reorder and removal retain original request',nojs.locator('.financial-offer-form [name=request_id]').input_value()==original_request and not db('SELECT id FROM ci_design_payment_events WHERE job_id=?',[draft_job]))
        nojs.locator('#financial-item-0-unit').fill('20.015');nojs.get_by_role('button',name='Publish financial offer',exact=True).click();expect(nojs.locator('#financial-item-0-unit')).to_have_value('20.015');capture(nojs,'Error')
        check('Browser invalid amount cannot create an offer',not db('SELECT id FROM ci_design_payment_events WHERE job_id=?',[draft_job]))
        check('Financial browser has no script errors',errors==[])
    finally:
        for ctx in contexts:ctx.close()
    chosen_form(owner,admin+'?view=support','support_policy','client_id',cid,{'allowed':'1','owner_password':OWNER})
    response=chosen_form(owner,admin+'?view=studio-clients','support_start','client_id',cid,{})
    sid=parse_qs(urlparse(response.url).query)['support'][0];support_url=portal+'?support='+sid+'&job='+str(job)
    r=owner.get(support_url)
    check('Scoped support sees balances but no client financial decisions','Invoice balance: $20.00 USD' in r.text and 'value="financial_decide"' not in r.text)
    before=db('SELECT * FROM ci_design_payment_heads WHERE id=?',[job]);owner.post(support_url,data={**accepted,'csrf':token(r)})
    check('Support cannot forge a financial acceptance',db('SELECT * FROM ci_design_payment_heads WHERE id=?',[job])==before)
    submit(owner,portal+'?support='+sid,'support_end')
    for path in ['app/billing/design_payment_forms.php','app/billing/design_payment_actions.php','includes/layout/design_financial.php','includes/layout/design_financial_editor.php']:
        check('HTTP denies direct financial internal '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    check('Financial section offers no card or payment submission button','>Pay now<' not in client.get(client_url).text and 'Online payment is not available' in client.get(client_url).text)
