"""Scoped additional-contact HTTP/browser acceptance; synthetic identities only."""
import json
from pathlib import Path
from urllib.parse import parse_qs, urlparse
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER
from owner_browser_forms import refuse_owner_action

CPASS='Synthetic contact passphrase 2026'

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['BASE','QA','check','soup','token','form_data','submit','inbox_server','provider_server']})
    db('DELETE FROM ci_rate_limits')
    root=BASE+'/existing/admin/index.php'
    clients=root+'?view=studio-clients'
    upgrade=root+'?view=studio-upgrade'
    portal=BASE+'/existing/portal/index.php'
    owner=requests.Session()
    submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    capture=(QA/'mail-capture.txt').read_bytes()
    connections,calls=inbox_server.connections,provider_server.calls
    cid=db("SELECT id FROM ci_studio_clients WHERE email='alice@example.invalid'")[0]['id']

    def find_form(action,field,value):
        for f in soup(owner.get(clients)).select('form'):
            a,i=f.select_one('[name=action]'),f.select_one('[name='+field+']')
            if a and i and a['value']==action and i['value']==str(value):return form_data(f)
        raise AssertionError('Missing contact form '+action+' '+field+'='+str(value))

    def add_form(c):
        for f in soup(owner.get(clients)).select('form'):
            a,i,d=f.select_one('[name=action]'),f.select_one('[name=client_id]'),f.select_one('[name=id]')
            if a and i and d and a['value']=='studio_contact' and i['value']==str(c) and d['value']=='0':return form_data(f)
        raise AssertionError('Missing add-contact form for client '+str(c))

    # Explicit owner preparation, never silent adoption.
    setup_form=env['form_for'](owner.get(upgrade),'studio_contacts_setup')
    check('Contacts setup uses current owner authority without another password',not setup_form.select('[name=current_password],[name=owner_password]') and bool(setup_form.select_one('[name=csrf]')['value']))
    setup_before=db("SELECT value FROM ci_settings WHERE name='contacts_schema_version'")
    r=submit(owner,upgrade,'studio_contacts_setup',{'confirm':'1','csrf':'invalid'})
    check('Contacts setup rejects invalid CSRF without changing readiness','expired' in r.text.lower() and db("SELECT value FROM ci_settings WHERE name='contacts_schema_version'")==setup_before)
    r=submit(owner,upgrade,'studio_contacts_setup',{'confirm':''})
    check('Contacts setup requires explicit backup confirmation','Confirm that you saved' in r.text and db("SELECT value FROM ci_settings WHERE name='contacts_schema_version'")==setup_before)
    refuse_owner_action(env,owner,upgrade,'studio_contacts_setup',{'confirm':'1'})
    r=submit(owner,upgrade,'studio_contacts_setup',{'confirm':'1'})
    check('Owner prepares contacts storage','contacts storage prepared' in r.text and db("SELECT value FROM ci_settings WHERE name='contacts_schema_version'")==[{'value':'1'}])
    r=submit(owner,upgrade,'studio_contacts_setup',{'confirm':'1'})
    check('Contacts setup is idempotent','contacts storage prepared' in r.text)
    db("UPDATE ci_settings SET value='bogus' WHERE name='contacts_schema_version'")
    r=submit(owner,upgrade,'studio_contacts_setup',{'confirm':'1'})
    check('Unknown contacts marker refuses to adopt data','Unrecognized contacts schema version' in r.text)
    db("UPDATE ci_settings SET value='1' WHERE name='contacts_schema_version'")

    # Create a project-review-only contact on Alice's account.
    data=add_form(cid);data.update(name='Review Only Contact',email='review-contact@example.invalid',**{'perms[]':'projects'})
    r=owner.post(clients,data=data)
    check('Scoped contact created','Contact saved' in r.text)
    row=db("SELECT * FROM ci_studio_contacts WHERE email='review-contact@example.invalid'")[0]
    ctid=row['id']
    check('Contact starts credential-less with exact permissions',row['password_hash']=='' and row['permissions']=='["projects"]' and int(row['client_id'])==int(cid))
    bad=add_form(cid);bad.update(name='Email Collision',email='alice@example.invalid',**{'perms[]':'projects'})
    r=owner.post(clients,data=bad)
    check('Contact email cannot collide with the primary account','belongs to the primary client account' in r.text and not db("SELECT id FROM ci_studio_contacts WHERE email='alice@example.invalid'"))
    none=add_form(cid);none.update(name='No Permissions',email='none-contact@example.invalid')
    none.pop('perms[]',None);none.pop('enabled',None)
    r=owner.post(clients,data=none)
    check('Contact requires at least one permission','at least one contact permission' in r.text and not db("SELECT id FROM ci_studio_contacts WHERE email='none-contact@example.invalid'"))

    # Private one-time invitation; hash at rest only.
    r=owner.post(clients,data=find_form('studio_contact_invite','id',ctid))
    link=soup(r).select_one('.notice.info a[href*="cinvite="]')
    check('Contact invitation displays a private one-time URL',link is not None and 'No email was sent' in r.text)
    link=link['href']
    raw=parse_qs(urlparse(link).query)['cinvite'][0]
    stored=db("SELECT invite_hash FROM ci_studio_contacts WHERE id=?",[ctid])[0]['invite_hash']
    check('Contact invitation stores only the token digest',len(raw)==64 and stored!=raw and raw not in json.dumps(db('SELECT * FROM ci_studio_contacts WHERE id=?',[ctid])))
    visitor=requests.Session()
    r=visitor.get(link);activation=form_data(soup(r).select_one('form'));activation.update(password=CPASS,confirm_password=CPASS)
    r=visitor.post(link,data=dict(activation,csrf='invalid'))
    check('Contact activation retains CSRF protection','session expired' in r.text.lower() and db("SELECT password_hash FROM ci_studio_contacts WHERE id=?",[ctid])[0]['password_hash']=='')
    r=visitor.post(link,data=activation)
    check('Contact invitation establishes credentials once','Your password is saved' in r.text and db("SELECT password_hash,invite_hash FROM ci_studio_contacts WHERE id=?",[ctid])[0]['invite_hash'] is None)
    r=visitor.post(link,data=activation)
    check('Used contact invitation cannot reset the password','already been used' in r.text)

    # Scoped portal access: project review without billing or downloads.
    contact=requests.Session()
    submit(contact,portal,'login',{'email':'review-contact@example.invalid','password':CPASS})
    r=contact.get(portal)
    check('Contact portal identifies the additional contact','Additional contact' in r.text and 'Review Only Contact' in r.text)
    r=contact.get(portal+'?job=1')
    check('Project contact sees reviews files and conversation','Designs & proposals' in r.text and 'Private project files' in r.text and 'Project conversation' in r.text)
    check('Project-only contact never sees invoice details','Invoices & payment instructions' not in r.text and 'HTTP manual invoice' not in r.text)
    invoice=db('SELECT id FROM ci_studio_invoices WHERE job_id=1 ORDER BY id DESC LIMIT 1')[0]['id']
    r=contact.get(portal+'?invoice='+str(invoice))
    check('Project-only contact cannot open invoice ledger',r.status_code==403 and 'HTTP manual invoice' not in r.text)
    file_id=db('SELECT id FROM ci_studio_files WHERE job_id=1 ORDER BY created_at DESC LIMIT 1')[0]['id']
    r=contact.get(portal+'?file='+file_id)
    check('Project-only contact cannot download project files',r.status_code==403)
    r=contact.get(portal+'?downloads=1')
    check('Project-only contact cannot open deliveries',r.status_code==403)
    r=contact.get(portal+'?job=1');post={'csrf':token(r),'action':'license_domain_add','license_id':'1','domain':'forged.example.invalid'}
    r=contact.post(portal+'?job=1',data=post)
    check('Project-only contact cannot manage license domains','do not include billing' in r.text)
    r=contact.post(portal+'?job=1',data={'csrf':token(r),'action':'message','body':'Contact-scoped review message.'})
    check('Contact project message saved with contact attribution','Contact-scoped review message' in r.text and db("SELECT author_type FROM ci_studio_messages WHERE body='Contact-scoped review message.'")[0]['author_type']=='contact')
    r=contact.get(portal+'?job=1')
    check('Contact attribution renders on the message','Review Only Contact' in r.text)
    with open(Path(__file__).resolve().parents[1]/'site/images/icons/favicon.png','rb') as f:
        r=submit(contact,portal+'?job=1','upload',files={'document':('contact-capture.png',f,'image/png')})
    check('Projects-permission contact uploads with contact attribution','File saved' in r.text and db("SELECT author_type FROM ci_studio_files WHERE original_name='contact-capture.png'")[0]['author_type']=='contact' and db("SELECT * FROM ci_audit WHERE action='file.upload.contact'"))
    r=contact.get(BASE+'/existing/api/bundle-download.php?id=1')
    check('Project-only contact cannot download license bundles',r.status_code==403)

    # Billing-only contact: invoices without project review or files.
    data=add_form(cid);data.update(name='Billing Only Contact',email='billing-contact@example.invalid',**{'perms[]':'billing'})
    r=owner.post(clients,data=data)
    btid=db("SELECT id FROM ci_studio_contacts WHERE email='billing-contact@example.invalid'")[0]['id']
    r=owner.post(clients,data=find_form('studio_contact_invite','id',btid))
    blink=soup(r).select_one('.notice.info a[href*="cinvite="]')['href']
    visitor=requests.Session();r=visitor.get(blink);activation=form_data(soup(r).select_one('form'));activation.update(password=CPASS,confirm_password=CPASS)
    visitor.post(blink,data=activation)
    billing=requests.Session()
    submit(billing,portal,'login',{'email':'billing-contact@example.invalid','password':CPASS})
    r=billing.get(portal+'?job=1')
    check('Billing contact sees invoices not project material','Invoices & payment instructions' in r.text and 'HTTP manual invoice' in r.text and 'Designs & proposals' not in r.text and 'Private project files' not in r.text)
    r=billing.post(portal+'?job=1',data={'csrf':token(billing.get(portal+'?job=1')),'action':'message','body':'Billing contact must not post.'})
    check('Billing-only contact cannot post project messages','do not include project access' in r.text and not db("SELECT id FROM ci_studio_messages WHERE body='Billing contact must not post.'"))
    r=billing.get(portal+'?file='+file_id)
    check('Billing-only contact cannot download files',r.status_code==403)
    with open(Path(__file__).resolve().parents[1]/'site/images/icons/favicon.png','rb') as f:
        r=billing.post(portal+'?job=1',data={'csrf':token(billing.get(portal+'?job=1')),'action':'upload'},files={'document':('billing-forged.png',f,'image/png')})
    check('Billing-only contact cannot upload project files','do not include project access' in r.text and not db("SELECT id FROM ci_studio_files WHERE original_name='billing-forged.png'"))
    r=billing.get(portal+'?activity=1')
    check('Billing-only contact cannot open project activity',r.status_code==403)

    # Primary client account remains unaffected by contact rows.
    alice=requests.Session()
    submit(alice,portal,'login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    r=alice.get(portal+'?job=1')
    check('Primary account keeps full project access','Designs & proposals' in r.text and 'Invoices & payment instructions' in r.text and 'HTTP manual invoice' in r.text)

    # Commercial proposals and finance terms require billing, not project-review or download grants.
    data=add_form(cid);data.update(name='Downloads Only Contact',email='downloads-contact@example.invalid',**{'perms[]':'downloads'})
    r=owner.post(clients,data=data)
    dtid=db("SELECT id FROM ci_studio_contacts WHERE email='downloads-contact@example.invalid'")[0]['id']
    r=owner.post(clients,data=find_form('studio_contact_invite','id',dtid))
    dlink=soup(r).select_one('.notice.info a[href*="cinvite="]')['href']
    downloads=requests.Session();r=downloads.get(dlink);activation=form_data(soup(r).select_one('form'));activation.update(password=CPASS,confirm_password=CPASS)
    downloads.post(dlink,data=activation)
    submit(downloads,portal,'login',{'email':'downloads-contact@example.invalid','password':CPASS})
    check('Downloads-only contact is authenticated with its exact grant',db('SELECT permissions FROM ci_studio_contacts WHERE id=?',[dtid])==[{'permissions':'["downloads"]'}] and 'Downloads Only Contact' in downloads.get(portal).text)
    proposals=root+'?view=proposals'
    if soup(owner.get(proposals)).select_one('[name=action][value=proposal_setup]'):
        submit(owner,proposals,'proposal_setup',{'confirm':'1'})
    check('Contacts commercial fixture uses explicitly prepared proposal storage',db("SELECT value FROM ci_settings WHERE name='proposals_schema_version'")==[{'value':'1'}])
    draft={'client_id':str(cid),'title':'Contact-scoped commercial proposal','deliverables':'Reviewed commercial website scope','exclusions':'Unpriced new work','milestones':'Build | 1834.56','schedule_notes':'Delivery after recorded scope approval.'}
    r=submit(owner,proposals,'proposal_create',draft)
    pid=db('SELECT id FROM ci_proposals WHERE title=?',[draft['title']])[0]['id']
    detail=root+'?view=proposal&id='+pid
    submit(owner,detail,'proposal_send',{})
    proposal_url=portal+'?proposal='+pid
    reviewed=form_data(env['form_for'](alice.get(proposal_url),'proposal_decide'))
    offers=db('SELECT id,title FROM ci_license_finance_offers WHERE client_id=? ORDER BY id',[cid])
    check('Contacts finance read controls use actual retained signed agreements',len(offers)>=2)
    finance_url=portal+'?finance='+offers[0]['id']

    def commercial_state():
        tables=['proposals','proposal_versions','proposal_decisions','proposal_events','license_finance_offers','recurring_billing_schedules','recurring_billing_events','studio_invoices','invoice_payments','payment_requests','audit']
        result={t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in tables}
        result['license_financing']=db('SELECT * FROM ci_license_financing ORDER BY license_id')
        return result

    for label,session in [('Projects-only',contact),('Downloads-only',downloads)]:
        before=commercial_state()
        for query,label_part in [('?finance=1','finance list'),('?finance='+offers[0]['id'],'finance detail'),('?proposal='+pid,'commercial proposal')]:
            denied=session.get(portal+query)
            check(label+' contact cannot read '+label_part,denied.status_code==403 and 'do not include billing' in denied.text and draft['title'] not in denied.text and all(o['title'] not in denied.text for o in offers) and commercial_state()==before)
        for choice in ['approve','changes']:
            forged={**reviewed,'csrf':token(session.get(portal)),'choice':choice,'note':'Forbidden commercial decision','confirm':'1'}
            denied=session.post(proposal_url,data=forged)
            check(label+' contact cannot '+choice+' commercial terms from a copied valid form','do not include billing' in denied.text and commercial_state()==before)
        denied=session.post(finance_url,data={'action':'finance_offer_payment','finance_id':offers[0]['id'],'csrf':token(session.get(portal))})
        check(label+' contact cannot prepare signed financing payment over HTTP','do not include billing' in denied.text and commercial_state()==before)

    for label,session in [('Primary client',alice),('Billing contact',billing)]:
        r=session.get(finance_url)
        check(label+' can read owned financing terms',r.status_code==200 and offers[0]['title'] in r.text and 'Amount financed' in r.text)
        r=session.get(proposal_url)
        check(label+' can review the exact commercial proposal',r.status_code==200 and draft['title'] in r.text and '$1,834.56' in r.text and len(form_data(env['form_for'](r,'proposal_decide'))['content_hash'])==64)
    billing_form=form_data(env['form_for'](billing.get(proposal_url),'proposal_decide'))
    edit=find_form('studio_contact','id',btid);edit['perms[]']='downloads';owner.post(clients,data=edit)
    before=commercial_state()
    denied=billing.post(proposal_url,data={**billing_form,'choice':'approve','note':'Revoked open form','confirm':'1'})
    check('Revoked billing contact cannot submit its already-open commercial proposal form',commercial_state()==before and draft['title'] not in denied.text)
    denied=billing.get(finance_url)
    login_actions=soup(denied).select('form input[name=action][value=login]')
    login_form=login_actions[0].find_parent('form') if len(login_actions)==1 else None
    check('Revoked billing contact cannot keep reading financing terms',denied.status_code==200 and login_form is not None and login_form.select_one('input[name=email][type=email]') is not None and login_form.select_one('input[name=password][type=password]') is not None and offers[0]['title'] not in denied.text and 'Amount financed' not in denied.text and commercial_state()==before)
    edit=find_form('studio_contact','id',btid);edit['perms[]']='billing';owner.post(clients,data=edit)
    billing=requests.Session();submit(billing,portal,'login',{'email':'billing-contact@example.invalid','password':CPASS})
    r=submit(billing,proposal_url,'proposal_decide',{'choice':'changes','note':'Revise the delivery schedule','confirm':'1'})
    check('Current billing contact can request commercial changes',db('SELECT status FROM ci_proposals WHERE id=?',[pid])==[{'status':'changes-requested'}] and 'decision was recorded' in r.text)
    submit(owner,detail,'proposal_revise',{**draft,'schedule_notes':'Revised delivery after recorded approval.'});submit(owner,detail,'proposal_send',{})
    r=submit(billing,proposal_url,'proposal_decide',{'choice':'approve','note':'Reviewed exact revised terms','confirm':'1'})
    check('Current billing contact approves only the exact revised commercial version',db('SELECT status,current_version FROM ci_proposals WHERE id=?',[pid])==[{'status':'approved','current_version':2}] and db('SELECT version,client_id,decision FROM ci_proposal_decisions WHERE proposal_id=? ORDER BY version',[pid])==[{'version':1,'client_id':cid,'decision':'changes-requested'},{'version':2,'client_id':cid,'decision':'approved'}] and 'decision was recorded' in r.text)

    # Disabling or editing a contact revokes its session and outstanding invitation.
    edit=find_form('studio_contact','id',ctid);edit.pop('enabled',None)
    r=owner.post(clients,data=edit)
    check('Disabled contact loses its session immediately',soup(contact.get(portal)).select_one('[name=action][value=login]') is not None)
    r=contact.post(portal,data={'csrf':token(contact.get(portal)),'action':'login','email':'review-contact@example.invalid','password':CPASS})
    check('Disabled contact cannot sign back in','Email or password not recognized' in r.text)
    r=contact.post(portal+'?job=1',data={'csrf':token(contact.get(portal)),'action':'message','body':'Revoked contact must not write.'})
    check('Revoked contact session cannot write as the client',not db("SELECT id FROM ci_studio_messages WHERE body='Revoked contact must not write.'"))
    reenable=find_form('studio_contact','id',ctid);reenable['enabled']='1'
    r=owner.post(clients,data=reenable)
    check('Contact re-enable uses the refreshed version','Contact saved' in r.text)
    submit(contact,portal,'login',{'email':'review-contact@example.invalid','password':CPASS})
    check('Reenabled contact signs in again',soup(contact.get(portal)).select_one('[name=action][value=logout]') is not None)
    r=owner.post(clients,data=find_form('studio_contact_invite','id',ctid))
    check('New contact invitation revokes the contact session',soup(contact.get(portal)).select_one('[name=action][value=login]') is not None)

    # Fresh installations ship the storage prepared and empty.
    check('Fresh install ships contacts schema ready',db("SELECT value FROM fresh_settings WHERE name='contacts_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_studio_contacts'))

    # Contact login through a real browser, scoped page rendered.
    context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
    page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(portal);page.locator('[name=email]').fill('billing-contact@example.invalid');page.locator('[name=password]').fill(CPASS);page.locator('form button').click()
    page.wait_for_url('**/portal/index.php');page.goto(portal+'?job=1')
    page.screenshot(path=str(QA/'Contact-Billing-Desktop.png'),full_page=True)
    check('Browser contact session renders scoped workspace','Invoices & payment instructions' in page.evaluate('document.body.innerText') and 'Designs & proposals' not in page.evaluate('document.body.innerText'))
    check('Contact browser session has no page errors',not errors)
    context.close()

    for path in ['app/clients/contacts.php','database/schema/contacts_schema.php','database/migrations/contacts_migrate.php']:
        check('Direct HTTP access denies contacts internal '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    check('Contact fixtures send no mail or provider requests',inbox_server.connections==connections and provider_server.calls==calls and (QA/'mail-capture.txt').read_bytes()==capture)
