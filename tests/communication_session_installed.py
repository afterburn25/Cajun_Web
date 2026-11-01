"""Installed current-owner communication controls; disposable SQL and loopback HTTP only.

This is focused acceptance, not the complete historical browser or release gate.
No mailbox login, message delivery, provider API call, or payment is attempted.
"""
from pathlib import Path
from urllib.parse import urljoin, urlsplit, parse_qs
import ast
import json
import os
import re
import shutil
import subprocess
import time
import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright, expect
from installed_owner_authority import revoked_owner
from owner_browser_forms import refuse_owner_action, assert_no_owner_reprompt

ROOT=Path(__file__).resolve().parents[1]
WEB=ROOT/'test-runtime/communication-session-web'
QA=ROOT/'qa/communication-session'
BASE='http://127.0.0.1:8091'
php=os.environ.get('PHP_BINARY','php')
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':
    raise SystemExit('Disposable loopback SQL only')
QA.mkdir(parents=True,exist_ok=True)
for node in ast.parse((ROOT/'tests/browser_acceptance.py').read_text()).body:
    if isinstance(node,ast.FunctionDef) and node.name in {'soup','token','form_data','form_for','submit'}:
        exec(compile(ast.Module(body=[node],type_ignores=[]),'retained-form-helpers','exec'),globals())
checks=[]
errors=[]
external=[]
def check(name,ok):
    checks.append({'name':name,'passed':bool(ok)})
    if not ok:raise AssertionError(name)

subprocess.run([php,str(ROOT/'tests/tickets_installed_seed.php')],check=True,cwd=ROOT)
site=WEB/'existing'
if site.exists():raise SystemExit('Stale installed fixture refused')
shutil.copytree(ROOT/'site',site)
shutil.copyfile(ROOT/'test-runtime/config-fixture.php',site/'config.php')
(site/'.installed.php').write_text('<?php exit; ?>synthetic installed guard')
import release_cases
release_cases.ROOT=ROOT
release_cases.php=php
release_cases.soup=soup
release_cases.form_data=form_data
from release_cases import db
server='cwp-communication-session-http'
log=(QA/'server.log').open('w')
args=['docker','run','--rm','--init','--name',server,'--network','host','--user',str(os.getuid())+':'+str(os.getgid()),'-v',str(ROOT)+':'+str(ROOT),'-w',str(ROOT)]
for key in ['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD','CWP_SOURCE_SHA']:
    args+=['-e',key]
proc=subprocess.Popen(args+['cwp-communication-session','php','-S','127.0.0.1:8091','-t',str(WEB)],stdout=log,stderr=log)
admin=BASE+'/existing/admin/index.php'
OWNER='Synthetic owner passphrase 2026'
owner=requests.Session()
try:
    for _ in range(50):
        try:requests.get(admin+'?view=login',timeout=1);break
        except requests.RequestException:time.sleep(.2)
    else:raise AssertionError('Installed fixture unavailable')
    before_clients=db('SELECT * FROM ci_studio_clients ORDER BY id')
    before_jobs=db('SELECT * FROM ci_studio_jobs ORDER BY id')
    before_users=db('SELECT * FROM ci_users ORDER BY id')
    anonymous=requests.Session()
    bad=submit(anonymous,admin+'?view=login','login',{'email':'owner@example.invalid','password':'Deliberately incorrect initial login'})
    check('Wrong initial administrator password is still refused','view=login' in bad.url and soup(bad).select_one('[name=password]') is not None)
    anonymous.close()
    response=submit(owner,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    check('Actual owner credentials establish the authenticated session',response.status_code==200 and 'view=login' not in response.url)
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True)
        context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
        def bounded(route):
            target=urlsplit(route.request.url)
            if target.hostname!='127.0.0.1' or target.port!=8091:
                external.append(route.request.url);route.abort()
            else:route.continue_()
        context.route('**/*',bounded)
        context.add_cookies([{'name':c.name,'value':c.value,'url':BASE} for c in owner.cookies])
        page=context.new_page()
        page.on('pageerror',lambda e:errors.append(str(e)))
        def form(action):
            return page.locator('form').filter(has=page.locator('[name=action][value="'+action+'"]'))
        def prepare(view,action,marker):
            url=admin+'?view='+view
            response=owner.get(url,timeout=20)
            captured=form_for(response,action)
            check(action+' actual preparation form has no repeated owner password',not captured.select('[name=current_password],[name=owner_password]'))
            check(action+' begins unprepared',not db('SELECT value FROM ci_settings WHERE name=?',[marker]))
            invalid=submit(owner,url,action,{'csrf':'invalid'})
            check(action+' invalid CSRF is refused before schema preparation','session expired' in invalid.text.lower() and not db('SELECT value FROM ci_settings WHERE name=?',[marker]))
            refuse_owner_action(globals(),owner,url,action,{})
            page.goto(url)
            control=form(action)
            assert_no_owner_reprompt(check,control,action)
            with page.expect_navigation(wait_until='domcontentloaded'):
                control.locator('button[type=submit],button:not([type])').first.click()
            check(action+' actual browser submits successfully without repeated password',db('SELECT value FROM ci_settings WHERE name=?',[marker])==[{'value':'1'}] and 'view=login' not in page.url)
        prepare('mail-templates','mail_templates_setup','mail_templates_schema_version')
        prepare('mail-accounts','mail_accounts_setup','mail_accounts_schema_version')
        for view,action,marker in [
            ('mail-accounts','mail_inbox_setup','mail_inbox_schema_version'),
            ('mail-accounts','mail_message_setup','mail_messages_schema_version'),
            ('mail-accounts','mail_notices_setup','mail_notices_schema_version'),
            ('mail-drafts','mail_drafts_setup','mail_drafts_schema_version'),
            ('mail-submissions','mail_submissions_setup','mail_submissions_schema_version'),
            ('consultation-settings','appointments_setup','appointments_schema_version')]:
            prepare(view,action,marker)
        # Seed only an explicitly synthetic preexisting inquiry for the real review form.
        db("INSERT INTO ci_leads (reference,request_key,name,email,service,budget,timeline,message,consent,ip_hash,status,notes,created_at) VALUES (?,?,?,?,?,?,?,?,1,?,'new',?,?)",['COMM-REVIEW','d'*64,'Synthetic review prospect','communication-review@example.invalid','Website review','Synthetic','Flexible','Preserve this existing review inquiry.','e'*64,'Retain original note',time.strftime('%Y-%m-%d %H:%M:%S',time.gmtime())])
        lead=db('SELECT * FROM ci_leads WHERE reference=?',['COMM-REVIEW'])[0]
        prepare('website-review&id='+str(lead['id']),'reviews_setup','website_reviews_schema_version')
        check('Review preparation retains the entire existing inquiry',db('SELECT * FROM ci_leads WHERE id=?',[lead['id']])==[lead])
        page.goto(admin+'?view=mail-account')
        account=page.locator('[data-mail-account-editor]')
        assert_no_owner_reprompt(check,account,'Save synthetic mailbox profile')
        credentials={'imap_password':'Synthetic IMAP secret only','smtp_password':'Synthetic SMTP secret only'}
        values={'label':'Current session mailbox','address':'current-session@example.invalid','imap_host':'imap.example.invalid','imap_port':'993','imap_username':'synthetic-incoming','smtp_host':'smtp.example.invalid','smtp_port':'465','smtp_username':'synthetic-outgoing',**credentials}
        for name,value in values.items():account.locator('[name='+name+']').fill(value)
        for name in ['imap_encryption','smtp_encryption']:account.locator('[name='+name+']').select_option('ssl')
        account.locator('[name=enabled]').check()
        with page.expect_navigation(wait_until='domcontentloaded'):
            account.get_by_role('button',name='Save email account',exact=True).click()
        aid=parse_qs(urlsplit(page.url).query)['account_id'][0]
        detail=admin+'?view=mail-account&account_id='+aid
        saved=db('SELECT * FROM ci_mail_accounts WHERE id=?',[aid])[0]
        check('Actual browser saves mailbox metadata with encrypted credentials',saved['label']==values['label'] and int(saved['revision'])==1 and bool(saved['credentials']) and all(v not in saved['credentials'] for v in credentials.values()))
        check('Mailbox credential inputs remain available but never echo saved secrets',all(page.locator('[name='+name+']').count()==1 and page.locator('[name='+name+']').input_value()=='' and value not in page.content() for name,value in credentials.items()))
        refuse_owner_action(globals(),owner,detail,'mail_accounts_save',{'label':'Must not persist under revoked authority'})
        check('Revoked account edit preserves encrypted credentials and original metadata',db('SELECT * FROM ci_mail_accounts WHERE id=?',[aid])==[saved])
        prepare('mail-account&account_id='+aid,'mail_accounts_connections_setup','mail_connections_schema_version')
        check('Owner account creation alone does not grant mailbox Read permission','assign yourself Read access' in page.locator('body').inner_text() and page.locator('[data-mail-check=imap] button').count()==0)
        grant=submit(owner,detail,'mail_accounts_grant',{'user_id':'1','revision':'0','can_read':'1','can_send':'1','enabled':'1','days':'0'})
        check('Separate owner mailbox assignment is explicit',grant.status_code==200 and len(db('SELECT * FROM ci_mail_account_grants WHERE account_id=?',[aid]))==1)
        page.goto(detail)
        assert_no_owner_reprompt(check,page.locator('[data-mail-check=imap]'),'Incoming connection diagnostic')
        with revoked_owner(site,php):
            with page.expect_navigation(wait_until='domcontentloaded') as denied:
                page.get_by_role('button',name='Test incoming connection',exact=True).click()
            check('Actual browser denies a captured diagnostic after same-owner permission revocation',denied.value is not None and (denied.value.status==403 or (denied.value.status==200 and 'Your account does not have access to this action.' in page.locator('body').inner_text())))
        page.goto(detail)
        assert_no_owner_reprompt(check,page.locator('[data-mail-check=imap]'),'Restored-owner incoming diagnostic')
        with page.expect_navigation(wait_until='domcontentloaded'):
            page.get_by_role('button',name='Test incoming connection',exact=True).click()
        expect(page.locator('.notice.error').first).to_contain_text('public server hostname')
        check('Synthetic reserved hostname is refused without an external check or result',not db('SELECT * FROM ci_mail_connection_checks') and not db('SELECT * FROM ci_mail_connection_results'))
        for width,height,label in [(1440,1000,'Desktop'),(390,844,'Mobile')]:
            page.set_viewport_size({'width':width,'height':height})
            page.goto(detail)
            expect(page.locator('[data-mail-account-editor]')).to_be_visible()
            assert_no_owner_reprompt(check,page.locator('[data-mail-account-editor]'),'Authenticated mailbox '+label)
            check('Authenticated mailbox '+label+' fits viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth+1'))
            page.screenshot(path=str(QA/('Mail-Current-Session-'+label+'.png')),full_page=True,animations='disabled')
        context.close();browser.close()
    check('Existing client accounts are unchanged',db('SELECT * FROM ci_studio_clients ORDER BY id')==before_clients)
    check('Existing projects are unchanged',db('SELECT * FROM ci_studio_jobs ORDER BY id')==before_jobs)
    check('Administrator credentials and identity are unchanged',db('SELECT * FROM ci_users ORDER BY id')==before_users)
    check('Application mail delivery remains disabled',db("SELECT value FROM ci_settings WHERE name='email_mode'")==[{'value':'disabled'}])
    check('No browser JavaScript errors',not errors)
    check('No external browser requests',not external)
except Exception as exc:
    checks.append({'name':type(exc).__name__+': '+str(exc),'passed':False})
finally:
    owner.close()
    subprocess.run(['docker','stop','-t','3',server],capture_output=True)
    try:proc.wait(timeout=10)
    except subprocess.TimeoutExpired:proc.kill();proc.wait()
    log.close()
    text=(QA/'server.log').read_text()
    diagnostics=re.findall(r'^.*(?:PHP (?:Warning|Fatal error|Parse error|Deprecated|Notice)|CWP \[).*$',text,re.M)
    checks.append({'name':'No PHP application diagnostics','passed':not diagnostics})
    passed=bool(checks) and all(x['passed'] for x in checks)
    (QA/'results.json').write_text(json.dumps({'passed':passed,'source_sha':os.environ.get('CWP_SOURCE_SHA'),'scope':'Focused installed owner communication preparation, mailbox profile and diagnostic authorization; real synthetic SQL/HTTP/Chromium, not the full historical application/release gate. No external mail/provider/payment operations.','checks':checks,'javascript_errors':errors,'external_requests':external,'php_diagnostics':diagnostics},indent=2)+'\n')
print(json.dumps({'passed':passed,'checks':len(checks)}))
raise SystemExit(0 if passed else 1)
