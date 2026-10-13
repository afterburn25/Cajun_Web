"""Actual packaged HTTP/Chromium inbox reads over a synthetic pinned TLS mailbox; no external account."""
import json,time
from urllib.parse import parse_qs,urlparse
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit','inbox_server']})
    db('DELETE FROM ci_rate_limits');root=BASE+'/existing/admin/index.php';library=root+'?view=mail-accounts';new=root+'?view=mail-account';assigned=root+'?view=mailboxes'
    check('Fresh setup creates empty inbox request storage',db("SELECT value FROM fresh_settings WHERE name='mail_inbox_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_mail_inbox_reads'))
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    before=inbox_server.connections
    r=submit(owner,library,'mail_inbox_setup',{'csrf':'bad','current_password':OWNER});check('Inbox preparation rejects CSRF','session expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='mail_inbox_schema_version'"))
    r=submit(owner,library,'mail_inbox_setup',{'current_password':'wrong'});check('Inbox preparation requires current owner password','current password' in r.text and not db("SELECT value FROM ci_settings WHERE name='mail_inbox_schema_version'"))
    r=submit(owner,library,'mail_inbox_setup',{'current_password':OWNER});check('Inbox preparation persists without connecting','Inbox request history is ready' in r.text and inbox_server.connections==before)
    fields=form_data(soup(owner.get(new)).select_one('[data-mail-account-editor]'));fields.update(label='Staff inbox fixture',address='synthetic@example.net',imap_host='inbox-fixture.example.net',imap_port='993',imap_encryption='ssl',imap_username='synthetic@example.net',smtp_host='smtp.example.invalid',smtp_port='587',smtp_encryption='starttls',smtp_username='synthetic',imap_password='Synthetic receiving HTTP password',smtp_password='Synthetic outgoing unused password',current_password=OWNER,enabled='1')
    r=owner.post(new,data=fields);aid=parse_qs(urlparse(r.url).query)['account_id'][0];detail=root+'?view=mail-account&account_id='+aid;inbox=root+'?view=mail-inbox&account_id='+aid
    def grant(user,enabled='1',read='1'):
        rows=db('SELECT revision FROM ci_mail_account_grants WHERE account_id=? AND user_id=?',[aid,user]);revision=rows[0]['revision'] if rows else 0
        return owner.post(detail,data={'csrf':token(owner.get(detail)),'action':'mail_accounts_grant','account_id':aid,'revision':revision,'user_id':user,'can_read':read,'can_send':'1','enabled':enabled,'days':'0','current_password':OWNER})
    def load(session,**overrides):
        data={'csrf':token(session.get(inbox)),'action':'mail_inbox_read','account_id':aid,'revision':'1'};data.update(overrides);return session.post(inbox,data=data,timeout=25)
    def age():db('UPDATE ci_mail_inbox_reads SET started_epoch=?',[int(time.time())-7200])
    check('Owner must be assigned before opening inbox',owner.get(inbox).status_code==403 and inbox_server.connections==before)
    grant(1);r=owner.get(inbox);check('GET opens inbox controls without a server connection','Load recent headers' in r.text and inbox_server.connections==before)
    check('Assigned account list links directly to its inbox','view=mail-inbox' in owner.get(assigned).text)
    r=load(owner,csrf='bad');check('Inbox read rejects bad CSRF before any network work','session expired' in r.text.lower() and inbox_server.connections==before and not db('SELECT * FROM ci_mail_inbox_reads'))
    r=load(owner,revision='99');check('Inbox read rejects stale settings before network work','account changed' in r.text and inbox_server.connections==before)
    r=load(owner);check('Packaged inbox uses actual synthetic TLS and renders newest headers',r.status_code==200 and inbox_server.connections==before+1 and 'SYNTHETIC PRIVATE Café' in r.text and [n['data-inbox-uid'] for n in soup(r).select('[data-inbox-uid]')]==['82','81'])
    check('Inbox provider markup is escaped without image or script nodes','&lt;img' in r.text and not soup(r).select('[data-inbox-uid] img,[data-inbox-uid] script'))
    check('Inbox result forbids browser and shared caching','no-store' in r.headers.get('Cache-Control','') and r.headers.get('Referrer-Policy')=='no-referrer')
    check('Successful read persists audited counts and exact mailbox epoch',db('SELECT outcome,result_code,message_count,mailbox_total,uidvalidity FROM ci_mail_inbox_results')==[{'outcome':'success','result_code':'ok','message_count':2,'mailbox_total':2,'uidvalidity':'1234'}])
    r=owner.get(inbox);check('Headers do not survive a later GET in session or history','SYNTHETIC PRIVATE' not in r.text and 'No headers loaded for this page' in r.text and 'Headers loaded' in r.text)
    r=load(owner);check('Immediate refresh is refused without reconnecting','15 seconds' in r.text and inbox_server.connections==before+1 and len(db('SELECT * FROM ci_mail_inbox_reads'))==1)
    member_id=db("SELECT id FROM ci_users WHERE email='request-staff@example.invalid'")[0]['id'];original=db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[member_id])[0]['permissions'];db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[json.dumps(json.loads(original)+['mail.read']),member_id])
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF});check('Global read grant alone reveals no inbox',member.get(inbox).status_code==403)
    grant(member_id);age();r=load(member);check('Assigned staff can explicitly load headers without an owner password','SYNTHETIC PRIVATE Café' in r.text and len(db('SELECT * FROM ci_mail_inbox_reads WHERE actor_id=?',[member_id]))==1)
    check('Staff inbox history is scoped to that staff identity',len(soup(member.get(inbox)).select('article.workspace-card'))==1)
    grant(member_id,enabled='0');check('Revoked staff grant stops the same browser session',member.get(inbox).status_code==403);grant(member_id)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[original,member_id]);check('Revoked global permission stops assigned mailbox access',member.get(inbox).status_code==403)
    for mode,phrase in [('empty','server reported an empty inbox'),('limit','exceeded the supported limits'),('malformed','unsupported or incomplete response')]:
        age();inbox_server.mode=mode;r=load(owner);check('HTTP '+mode+' request has an honest isolated result',phrase in r.text and not soup(r).select('[data-inbox-uid]'))
        if mode!='empty':check('HTTP '+mode+' failure never masquerades as an empty inbox','server reported an empty inbox' not in r.text)
    inbox_server.mode='ok'
    retained=json.dumps(db('SELECT * FROM ci_mail_inbox_reads')+db('SELECT * FROM ci_mail_inbox_results')+db("SELECT * FROM ci_audit WHERE action LIKE 'mail_accounts.inbox_%'"))
    check('HTTP request records and audits retain no mailbox header or password text','SYNTHETIC PRIVATE' not in retained and 'receiving HTTP password' not in retained)
    context=browser.new_context(viewport={'width':1440,'height':1050});page=context.new_page();errors=[];remote=[];page.on('pageerror',lambda e:errors.append(str(e)));page.on('request',lambda q:remote.append(q.url) if 'untrusted.example.invalid' in q.url else None)
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_url(root+'?view=dashboard');page.goto(inbox);age();page.get_by_role('button',name='Load recent headers').click();expect(page.locator('[data-inbox-uid]')).to_have_count(2)
    check('Chromium mailbox text is inert and causes no external fetch',not remote and not page.evaluate('Boolean(window.mailInjected)'))
    page.locator('[data-mail-inbox]').screenshot(path=str(QA/'Mail-Inbox-Desktop.png'));page.set_viewport_size({'width':390,'height':844});expect(page.locator('.admin-sidebar')).not_to_be_in_viewport();page.locator('[data-mail-inbox]').screenshot(path=str(QA/'Mail-Inbox-Mobile.png'))
    check('Inbox headers and controls fit the mobile screen',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('Inbox browser flow has no script errors',not errors);context.close()
    for path in ['app/mail/inbox.php','admin/controllers/mail_inbox.php','admin/views/mail_inbox.php','database/schema/mail_inbox_schema.php','database/migrations/mail_inbox_migrate.php','includes/foundation/mail-receiving/InboxReader.php','includes/foundation/mail-receiving/bootstrap.php']:
        check('Inbox direct internal PHP entry denied '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    check('Synthetic receiving server observed only read-only commands',not inbox_server.errors and all(not any(' '+verb in c for verb in ['SELECT','STORE','APPEND','EXPUNGE','SEARCH']) for c in inbox_server.commands))
