"""Installed notification forms, real project-message hooks and current account privacy.
No message is sent to an external server: application email stays disabled.
"""
from pathlib import Path
from urllib.parse import urljoin, urlsplit
import ast, json, os, shutil, subprocess, time
import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright, expect
from installed_owner_authority import revoked_owner
ROOT=Path(__file__).resolve().parents[1];QA=ROOT/'qa/notification-center';WEB=ROOT/'test-runtime/notification-web';BASE='http://127.0.0.1:8091'
php=os.environ.get('PHP_BINARY','php')
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Disposable loopback database only')
QA.mkdir(parents=True,exist_ok=True)
for node in ast.parse((ROOT/'tests/browser_acceptance.py').read_text()).body:
    if isinstance(node,ast.FunctionDef) and node.name in {'soup','token','form_data','form_for','submit'}:exec(compile(ast.Module(body=[node],type_ignores=[]),'retained-form-helpers','exec'),globals())
checks=[];errors=[];external=[]
def check(name,ok):
    checks.append({'name':name,'passed':bool(ok)})
    if not ok:raise AssertionError(name)
subprocess.run([php,str(ROOT/'tests/tickets_installed_seed.php')],cwd=ROOT,check=True)
site=WEB/'existing'
if site.exists():raise SystemExit('Stale installed notification fixture refused')
shutil.copytree(ROOT/'site',site);shutil.copyfile(ROOT/'test-runtime/config-fixture.php',site/'config.php');(site/'.installed.php').write_text('<?php exit; ?>synthetic installed guard')
import release_cases
release_cases.ROOT=ROOT;release_cases.php=php
from release_cases import db
server='cwp-notification-center-http';log=(QA/'server.log').open('w')
args=['docker','run','--rm','--init','--name',server,'--network','host','--user',str(os.getuid())+':'+str(os.getgid()),'-v',str(ROOT)+':'+str(ROOT),'-w',str(ROOT)]
for key in ['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD','CWP_SOURCE_SHA']:args+=['-e',key]
proc=subprocess.Popen(args+['cwp-notification-center','php','-S','127.0.0.1:8091','-t',str(WEB)],stdout=log,stderr=log)
owner=requests.Session();alice=requests.Session();bob=requests.Session();admin=BASE+'/existing/admin/index.php';portal=BASE+'/existing/portal/index.php';url=admin+'?view=notifications'
def all_notifications():return [db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['notification_feed','notification_prefs','notification_deliveries']]
try:
    for _ in range(60):
        try:requests.get(admin+'?view=login',timeout=1);break
        except requests.RequestException:time.sleep(.2)
    else:raise AssertionError('Installed server unavailable')
    r=submit(owner,admin+'?view=login','login',{'email':'owner@example.invalid','password':'Synthetic owner passphrase 2026'})
    check('Owner establishes actual administrator session',r.status_code==200 and 'view=login' not in r.url)
    original_clients=db('SELECT * FROM ci_studio_clients ORDER BY id');original_jobs=db('SELECT * FROM ci_studio_jobs ORDER BY id')
    r=owner.get(url);form=form_for(r,'notifications_setup')
    check('Setup is a CSRF-protected form without a repeated password',form.select_one('[name=csrf]') is not None and not form.select('input[type=password]'))
    bad=submit(owner,url,'notifications_setup',{'confirm':'1','csrf':'invalid'})
    check('Invalid CSRF cannot create notification schema','session expired' in bad.text.lower() and not db("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ci_notification_feed'"))
    bad=submit(owner,url,'notifications_setup',{})
    check('Explicit backup confirmation is required','Confirm that you saved' in bad.text and not db("SELECT value FROM ci_settings WHERE name='notification_feed_version'"))
    with revoked_owner(site,php):
        bad=submit(owner,url,'notifications_setup',{'confirm':'1'})
        check('Same-owner permission withdrawal blocks setup',('does not have access' in bad.text or 'current owner' in bad.text) and not db("SELECT value FROM ci_settings WHERE name='notification_feed_version'"))
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True);context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
        def bounded(route):
            u=urlsplit(route.request.url)
            if u.hostname!='127.0.0.1' or u.port!=8091:external.append(route.request.url);route.abort()
            else:route.continue_()
        context.route('**/*',bounded);context.add_cookies([{'name':c.name,'value':c.value,'url':BASE} for c in owner.cookies]);page=context.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
        page.goto(url);setup=page.locator('form').filter(has=page.locator('[name=action][value=notifications_setup]'));setup.locator('[name=confirm]').check()
        with page.expect_navigation(wait_until='domcontentloaded'):setup.locator('button').click()
        check('Actual browser enables the center using current owner session',db("SELECT value FROM ci_settings WHERE name='notification_feed_version'")==[{'value':'1'}] and page.locator('[data-notification-outbox]').count()==1)
        check('Fresh feature does not enable outbound email',not db("SELECT value FROM ci_settings WHERE name='notification_email_enabled'"))
        configure=page.locator('form').filter(has=page.locator('[name=action][value=notification_email_configure]'));configure.locator('[name=enabled]').check();configure.locator('[name=confirm]').check()
        with page.expect_navigation(wait_until='domcontentloaded'):configure.locator('button').click()
        check('Owner explicitly opts future notifications into the queue',db("SELECT value FROM ci_settings WHERE name='notification_email_enabled'")==[{'value':'1'}])
        result=submit(owner,admin+'?view=studio-job&id=1','studio_message',{'body':'Synthetic private project message <script>not executable</script>'})
        check('Real staff project-message action creates one directed customer update',result.status_code==200 and len(db("SELECT * FROM ci_notification_feed WHERE audience='client' AND recipient_id=1"))==1)
        client_notice=db("SELECT * FROM ci_notification_feed WHERE audience='client' AND recipient_id=1")[0]
        pending=db("SELECT * FROM ci_notification_deliveries WHERE channel='email'")
        check('Actual project event queues locally without contacting transport',len(pending)==1 and pending[0]['state']=='pending' and int(pending[0]['attempts'])==0 and 'script' not in client_notice['body'])
        before=all_notifications();page.reload();page.reload();check('Repeated page loads never dispatch or mutate the queue',all_notifications()==before)
        rejected=submit(owner,url,'notification_dispatch',{'confirm':'1'})
        check('Disabled application email blocks explicit processing without losing queue','configure outbound delivery' in rejected.text and all_notifications()==before)
        for session,name in [(alice,'alice'),(bob,'bob')]:
            response=submit(session,portal,'login',{'email':name+'@example.invalid','password':'Synthetic client passphrase 2026'})
            check('Actual customer sign-in '+name,response.status_code==200 and soup(response).select_one('[name=action][value=login]') is None)
        r=alice.get(portal+'?notifications=1');check('Customer sees only their directed notification',r.status_code==200 and client_notice['id'] in r.text and 'Controlled email delivery' not in r.text)
        r=bob.get(portal+'?notifications=1');check('Other account cannot see notification content',client_notice['id'] not in r.text and 'New project message' not in r.text)
        before=all_notifications();forged={'action':'notification_client_mark','id':client_notice['id'],'read':'1','csrf':token(r)};r=bob.post(portal+'?notifications=1',data=forged)
        check('Forged foreign read-state change is denied with records unchanged','Notification unavailable' in r.text and all_notifications()==before)
        r=submit(alice,portal+'?notifications=1','notification_client_mark',{'id':client_notice['id'],'read':'1'})
        check('Customer can mark their own notification read',db('SELECT read_at FROM ci_notification_feed WHERE id=?',[client_notice['id']])[0]['read_at'] is not None)
        pref=form_data(form_for(alice.get(portal+'?notifications=1'),'notification_client_prefs'));pref.pop('email_project-update',None);r=alice.post(portal+'?notifications=1',data=pref)
        check('Customer email opt-out persists independently of in-app feed',db("SELECT email FROM ci_notification_prefs WHERE audience='client' AND recipient_id=1 AND kind='project-update'")==[{'email':0}] and client_notice['id'] in r.text)
        response=submit(alice,portal+'?job=1','message',{'body':'Synthetic client reply for directed notification testing.'})
        check('Real customer reply emits an authorized staff notification',response.status_code==200 and len(db("SELECT id FROM ci_notification_feed WHERE audience='staff' AND recipient_id=1"))==1)
        before=all_notifications();anonymous=requests.get(portal+'?notifications=1');support=owner.get(url+'&support=invalid')
        check('Anonymous customer feed is private and denied',anonymous.status_code==403 and 'no-store' in anonymous.headers.get('Cache-Control','') and client_notice['id'] not in anonymous.text)
        check('Support selector cannot enter the personal notification center',support.status_code==403 and all_notifications()==before)
        for width,height,label in [(1440,1000,'Desktop'),(390,844,'Mobile')]:
            page.set_viewport_size({'width':width,'height':height});page.goto(url)
            expect(page.locator('[data-notification-outbox]')).to_be_visible();check('Authenticated notification workspace '+label+' contains no repeated password',page.locator('input[type=password]').count()==0)
            check('Notification workspace '+label+' fits the viewport',page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
            page.screenshot(path=str(QA/('Notification-Center-'+label+'.png')),full_page=True,animations='disabled')
        context.close();browser.close()
    check('Preexisting customer records are retained',db('SELECT * FROM ci_studio_clients ORDER BY id')==original_clients)
    check('Preexisting project metadata is retained',db('SELECT * FROM ci_studio_jobs ORDER BY id')==original_jobs)
    check('No external mailbox or delivery mode was enabled',db("SELECT value FROM ci_settings WHERE name='email_mode'")==[{'value':'disabled'}])
    check('No browser JavaScript errors',not errors);check('No external browser requests',not external)
except Exception as e:checks.append({'name':type(e).__name__+': '+str(e),'passed':False})
finally:
    owner.close();alice.close();bob.close();subprocess.run(['docker','stop','-t','3',server],capture_output=True)
    try:proc.wait(timeout=10)
    except subprocess.TimeoutExpired:proc.kill();proc.wait()
    log.close();text=(QA/'server.log').read_text();checks.append({'name':'No unexpected PHP/application diagnostics','passed':not any(x in text for x in ['PHP Fatal error','PHP Warning','PHP Notice','PHP Deprecated','CWP ['])})
    passed=bool(checks) and all(c['passed'] for c in checks);(QA/'browser.json').write_text(json.dumps({'passed':passed,'source_sha':os.environ.get('CWP_SOURCE_SHA'),'scope':'Actual installed PHP/SQL/HTTP/Chromium, synthetic accounts; outbound email disabled','checks':checks},indent=2)+'\n')
print(json.dumps({'passed':passed,'checks':len(checks)}));raise SystemExit(0 if passed else 1)
