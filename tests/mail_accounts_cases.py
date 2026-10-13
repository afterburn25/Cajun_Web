"""Actual account preparation flows. Synthetic passwords only; no mail transport."""
import json
from urllib.parse import parse_qs,urlparse
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit']})
    db('DELETE FROM ci_rate_limits')
    root=BASE+'/existing/admin/index.php';library=root+'?view=mail-accounts';new=root+'?view=mail-account';assigned=root+'?view=mailboxes'
    check('Fresh setup prepares empty email account storage',db("SELECT value FROM fresh_settings WHERE name='mail_accounts_schema_version'")==[{'value':'1'}] and db('SELECT * FROM fresh_mail_accounts')==[])
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    before=db('SELECT * FROM ci_studio_jobs');settings=db('SELECT * FROM ci_settings')
    r=submit(owner,library,'mail_accounts_setup',{'current_password':OWNER,'csrf':'invalid'})
    check('Email setup rejects invalid CSRF','session expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='mail_accounts_schema_version'"))
    r=submit(owner,library,'mail_accounts_setup',{'current_password':'incorrect'})
    check('Email setup requires owner reauthentication','current password' in r.text.lower())
    r=submit(owner,library,'mail_accounts_setup',{'current_password':OWNER})
    after={s['name']:s['value'] for s in db('SELECT * FROM ci_settings')}
    check('Email setup retains existing business and mail settings','Email account storage enabled' in r.text and db('SELECT * FROM ci_studio_jobs')==before and all(after[s['name']]==s['value'] for s in settings))
    def edit(url,**changes):
        r=owner.get(url);form=soup(r).select_one('[data-mail-account-editor]');assert form is not None
        fields=form_data(form);fields.update({'current_password':OWNER,**changes});return owner.post(url,data=fields)
    fields={'label':'Support fixture account','address':'support@example.invalid','imap_host':'imap.example.invalid','imap_port':'993','imap_encryption':'ssl','imap_username':'support-incoming','smtp_host':'smtp.example.invalid','smtp_port':'587','smtp_encryption':'starttls','smtp_username':'support-outgoing','imap_password':'Synthetic IMAP fixture secret','smtp_password':'Synthetic SMTP fixture secret','enabled':'1'}
    r=edit(new,**fields);aid=parse_qs(urlparse(r.url).query)['account_id'][0];detail=new+'&account_id='+aid
    check('HTTP saves multiple-server profile with retained metadata revision',r.status_code==200 and len(db('SELECT * FROM ci_mail_account_revisions WHERE account_id=?',[aid]))==1)
    encrypted=db('SELECT credentials FROM ci_mail_accounts WHERE id=?',[aid])[0]['credentials']
    check('Account HTML never echoes saved passwords or ciphertext',all(value not in r.text for value in [fields['imap_password'],fields['smtp_password'],encrypted]) and all(n.get('value','')=='' for n in soup(r).select('input[type=password]')))
    check('Owner profile creation does not silently assign mailbox access','Support fixture account' not in owner.get(assigned).text)
    stored=db('SELECT * FROM ci_mail_accounts WHERE id=?',[aid])
    r=edit(detail,label='Preserved account edit',imap_host='bad..host.invalid',imap_password='NeverEchoThisSecret')
    check('Invalid account edit preserves metadata but not password fields','Preserved account edit' in r.text and 'NeverEchoThisSecret' not in r.text and db('SELECT * FROM ci_mail_accounts WHERE id=?',[aid])==stored)
    r=edit(detail,label='<img src=x onerror=alert(1)>',imap_host='imap.example.invalid')
    check('Account names are escaped in HTML',soup(r).select_one('[data-mail-account-editor] [name=label]')['value']=='<img src=x onerror=alert(1)>' and not soup(r).select('img[onerror]'))
    def state(action,revision):
        return owner.post(detail,data={'csrf':token(owner.get(detail)),'action':'mail_accounts_'+action,'account_id':aid,'revision':revision,'current_password':OWNER})
    stale=form_data(soup(owner.get(detail)).select_one('[data-mail-account-editor]'));stale.update({'current_password':OWNER,'label':'Unsaved stale account'})
    state('disable',2);r=owner.post(detail,data=stale)
    check('Stale account form retains old revision and submitted settings','Unsaved stale account' in r.text and 'account changed' in r.text and soup(r).select_one('[data-mail-account-editor] [name=revision]')['value']=='2' and db('SELECT revision,enabled FROM ci_mail_accounts WHERE id=?',[aid])==[{'revision':3,'enabled':0}])
    state('enable',3)
    r=edit(new,**dict(fields,label='Unassigned private account',address='other@example.invalid'))
    other=parse_qs(urlparse(r.url).query)['account_id'][0]
    member_id=db("SELECT id FROM ci_users WHERE email='request-staff@example.invalid'")[0]['id'];original=db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[member_id])[0]['permissions']
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    check('Staff cannot open owner mail configuration',member.get(library).status_code==403 and member.get(detail).status_code==403)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[json.dumps(json.loads(original)+['mail.read']),member_id])
    check('Global mail permission alone gives no account data','Unassigned private account' not in member.get(assigned).text and 'No currently available' in member.get(assigned).text)
    def grant(revision,enabled='1'):
        return owner.post(detail,data={'csrf':token(owner.get(detail)),'action':'mail_accounts_grant','account_id':aid,'revision':revision,'user_id':member_id,'can_read':'1','can_send':'1','enabled':enabled,'days':'7','current_password':OWNER})
    r=grant(0);r=member.get(assigned)
    check('Assigned list requires global and per-account capability','&lt;img' in r.text and 'Assigned: Read' in r.text and 'Read and Send' not in r.text and 'Unassigned private account' not in r.text and 'support-incoming' not in r.text)
    before=db('SELECT * FROM ci_mail_account_grants WHERE account_id=?',[aid])
    r=member.post(assigned,data={'csrf':token(member.get(assigned)),'action':'mail_accounts_grant','account_id':other,'revision':'0','user_id':member_id,'can_read':'1','can_send':'1','enabled':'1','days':'0','current_password':STAFF})
    check('Forged staff assignment mutation is rejected',db('SELECT * FROM ci_mail_account_grants WHERE account_id=?',[aid])==before and not db('SELECT * FROM ci_mail_account_grants WHERE account_id=?',[other]))
    state('disable',4);check('Account disabling removes live-session listing immediately','No currently available' in member.get(assigned).text)
    state('enable',5);grant(1,'0');check('Assignment revocation removes live-session listing immediately','No currently available' in member.get(assigned).text)
    state('clear',6)
    check('Clear credentials disables account without deleting history',db('SELECT credentials,enabled FROM ci_mail_accounts WHERE id=?',[aid])==[{'credentials':'','enabled':0}] and len(db('SELECT * FROM ci_mail_account_revisions WHERE account_id=?',[aid]))==7)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[original,member_id])
    for path in ['app/mail/accounts.php','app/mail/credentials.php','database/schema/mail_accounts_schema.php','database/migrations/mail_accounts_migrate.php','admin/controllers/mail_accounts.php','admin/views/mail_accounts.php','includes/foundation/webmail/MailboxAssignment.php']:
        check('Direct HTTP denial for mail account internal '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    context=browser.new_context(viewport={'width':1440,'height':1050});page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_url(root+'?view=dashboard')
    page.goto(new);form=page.locator('[data-mail-account-editor]')
    for key,value in dict(fields,label='Browser email account').items():
        if key=='enabled':form.locator('[name=enabled]').check()
        elif key.endswith('_encryption'):form.locator('[name='+key+']').select_option(value)
        else:form.locator('[name='+key+']').fill(value)
    form.locator('[name=current_password]').fill(OWNER);form.get_by_role('button',name='Save email account',exact=True).click();page.wait_for_url('**account_id=*')
    expect(page.locator('[data-mail-account-editor] [name=label]')).to_have_value('Browser email account')
    check('Chromium saves account without exposing passwords',page.locator('[name=imap_password]').input_value()=='' and fields['imap_password'] not in page.content())
    page.screenshot(path=str(QA/'Email-Accounts-Desktop.png'),full_page=True)
    page.set_viewport_size({'width':390,'height':844});expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
    page.screenshot(path=str(QA/'Email-Accounts-Mobile.png'),full_page=True)
    check('Email account form fits mobile viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('Email account UI reports untested settings and current message limits','Connection not checked' in page.content() and 'Message bodies, attachments and sending are not available yet' in page.content())
    check('Email account page has no browser errors',not errors);context.close()
