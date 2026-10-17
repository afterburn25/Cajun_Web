"""Scoped additional-contact HTTP/browser acceptance; synthetic identities only."""
import json
from urllib.parse import parse_qs, urlparse
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER

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
    r=submit(owner,upgrade,'studio_contacts_setup',{'current_password':'wrong'})
    check('Contacts setup reauthenticates the owner','current password is not correct' in r.text)
    r=submit(owner,upgrade,'studio_contacts_setup',{'current_password':OWNER})
    check('Owner prepares contacts storage','contacts storage prepared' in r.text and db("SELECT value FROM ci_settings WHERE name='contacts_schema_version'")==[{'value':'1'}])
    r=submit(owner,upgrade,'studio_contacts_setup',{'current_password':OWNER})
    check('Contacts setup is idempotent','contacts storage prepared' in r.text)
    db("UPDATE ci_settings SET value='bogus' WHERE name='contacts_schema_version'")
    r=submit(owner,upgrade,'studio_contacts_setup',{'current_password':OWNER})
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
    r=billing.get(portal+'?activity=1')
    check('Billing-only contact cannot open project activity',r.status_code==403)

    # Primary client account remains unaffected by contact rows.
    alice=requests.Session()
    submit(alice,portal,'login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    r=alice.get(portal+'?job=1')
    check('Primary account keeps full project access','Designs & proposals' in r.text and 'Invoices & payment instructions' in r.text and 'HTTP manual invoice' in r.text)

    # Disabling or editing a contact revokes its session and outstanding invitation.
    edit=find_form('studio_contact','id',ctid);edit.pop('enabled',None)
    r=owner.post(clients,data=edit)
    check('Disabled contact loses its session immediately',soup(contact.get(portal)).select_one('[name=action][value=login]') is not None)
    r=contact.post(portal,data={'csrf':token(contact.get(portal)),'action':'login','email':'review-contact@example.invalid','password':CPASS})
    check('Disabled contact cannot sign back in','Email or password not recognized' in r.text)
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
