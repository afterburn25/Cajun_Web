"""Installed primary-client account security; synthetic loopback HTTP, SQL and Chromium only."""
from pathlib import Path
from urllib.parse import urljoin, urlsplit, parse_qs
import ast,json,os,shutil,subprocess,time
import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];WEB=ROOT/'test-runtime/account-web';QA=ROOT/'qa/account-security';BASE='http://127.0.0.1:8091'
QA.mkdir(parents=True,exist_ok=True);php=os.environ.get('PHP_BINARY','php')
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Synthetic loopback only')
for node in ast.parse((ROOT/'tests/browser_acceptance.py').read_text()).body:
 if isinstance(node,ast.FunctionDef) and node.name in {'soup','token','form_data','form_for','submit'}:exec(compile(ast.Module(body=[node],type_ignores=[]),'retained-http-helpers','exec'),globals())
checks=[]
def check(name,ok):
 checks.append({'name':name,'passed':bool(ok)})
 if not ok:raise AssertionError(name)
subprocess.run([php,str(ROOT/'tests/tickets_installed_seed.php')],check=True,cwd=ROOT)
site=WEB/'existing'
if site.exists():raise SystemExit('Stale account fixture refused')
shutil.copytree(ROOT/'site',site);shutil.copyfile(ROOT/'test-runtime/config-fixture.php',site/'config.php');(site/'.installed.php').write_text('<?php exit; ?>synthetic installed guard')
name='cwp-account-security-http';args=['docker','run','--rm','--init','--name',name,'--network','host','--user',f'{os.getuid()}:{os.getgid()}','-v',f'{ROOT}:{ROOT}','-w',str(ROOT)]
for k in ['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD','CWP_SOURCE_SHA']:args+=['-e',k]
log=(QA/'server.log').open('w');proc=subprocess.Popen(args+['cwp-account-review','php','-S','127.0.0.1:8091','-t',str(WEB)],cwd=ROOT,stdout=log,stderr=log,start_new_session=True)
import release_cases
release_cases.ROOT=ROOT;release_cases.php=php
for key in ['soup','form_data']:setattr(release_cases,key,globals()[key])
from release_cases import db,chosen_form
old='Synthetic client passphrase 2026';new='Synthetic updated client passphrase 2026';final='Synthetic browser changed passphrase 2026'
portal=BASE+'/existing/portal/index.php';account=portal+'?account=1';admin_url=BASE+'/existing/admin/index.php'
def state():return {t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['studio_clients','users','audit','studio_jobs','studio_messages']}
def sign_in(email,password,url=account):
 s=requests.Session();r=submit(s,url,'login',{'email':email,'password':password});check('Actual account login '+email,r.status_code==200 and soup(r).select_one('[data-client-account]') is not None);return s
try:
 for _ in range(60):
  try:requests.get(account,timeout=1);break
  except requests.RequestException:time.sleep(.2)
 else:raise AssertionError('Account fixture unavailable')
 anon=requests.Session();r=anon.get(account);before=state()
 check('Anonymous account page asks for login without customer information',r.status_code==200 and soup(r).select_one('[data-account-password]') is None and 'alice@example.invalid' not in r.text)
 check('Private account pages are noncacheable and nonindexable','no-store' in r.headers.get('Cache-Control','') and 'private' in r.headers.get('Cache-Control','') and 'noindex' in r.headers.get('X-Robots-Tag','') and r.headers.get('Referrer-Policy')=='no-referrer')
 r=anon.post(account,data={'action':'account_password','csrf':token(r),'current_password':old,'password':new,'confirm_password':new})
 check('Anonymous password POST cannot mutate account or audit',state()==before and 'session ended' in r.text)
 for query in ['account[]=1','account=2','account=1&software=1','account=1&finance=1','account=1&job=1']:
  r=anon.get(portal+'?'+query);check('Conflicting or malformed account request is refused '+query,r.status_code==400 and soup(r).select_one('[data-account-password]') is None)
 for path in ['app/clients/account_security.php','portal/views/account.php']:
  r=anon.get(BASE+'/existing/'+path);check('Private account entry denies direct request '+path,r.status_code==404 and 'password_hash' not in r.text)
 owner_setup=requests.Session();submit(owner_setup,admin_url+'?view=login','login',{'email':'owner@example.invalid','password':'Synthetic owner passphrase 2026'})
 submit(owner_setup,admin_url+'?view=notifications','notifications_setup',{'confirm':'1'})
 check('Combined owner session enables notification storage without another password',db("SELECT value FROM ci_settings WHERE name='notification_feed_version'")==[{'value':'1'}])
 submit(owner_setup,admin_url+'?view=studio-job&id=1','studio_message',{'body':'Synthetic combined account notification'})
 directed=db("SELECT id FROM ci_notification_feed WHERE audience='client' AND recipient_id=1")
 check('Real project message creates the combined private notification',len(directed)==1)
 notification_id=directed[0]['id'];notification_before={t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['notification_feed','notification_deliveries']}
 owner_setup.close()
 alice=sign_in('alice@example.invalid',old);stale=sign_in('alice@example.invalid',old);bob=sign_in('bob@example.invalid',old)
 r=alice.get(portal);check('Customer workspace links to account settings',bool(soup(r).select_one('a[href$="?account=1"]')))
 check('Same customer workspace retains both notification and account navigation',bool(soup(r).select_one('a[href$="?notifications=1"]')) and bool(soup(r).select_one('a[href$="?account=1"]')))
 check('Notification center works alongside account settings',notification_id in alice.get(portal+'?notifications=1').text and notification_id not in bob.get(portal+'?notifications=1').text)

 before=state();r=submit(alice,account,'account_password',{'current_password':'Wrong synthetic current','password':new,'confirm_password':new})
 check('Wrong current password preserves all account and project records','current password is not correct' in r.text and state()==before)
 check('Failed form never echoes any password',all(not e.get('value') for e in soup(r).select('input[type=password]')) and 'Wrong synthetic current' not in r.text)
 r=submit(alice,account,'account_password',{'current_password':old,'password':new,'confirm_password':new+'!'})
 check('Mismatched confirmation preserves credentials and audit','do not match' in r.text and state()==before)
 r=submit(alice,account,'account_password',{'current_password':old,'password':new,'confirm_password':new,'csrf':'forged'})
 check('Forged CSRF cannot change credentials','session expired' in r.text.lower() and state()==before)
 db('UPDATE ci_studio_clients SET invite_hash=?,invite_expires=? WHERE id=1',['e'*64,int(time.time())+3600])
 before=state();r=alice.get(account);old_csrf=token(r);old_cookie=alice.cookies.get_dict();r=submit(alice,account,'account_password',{'current_password':old,'password':new,'confirm_password':new,'client_id':'2','id':'2'})
 check('Authenticated password change succeeds and stays on account page','Password updated.' in r.text and soup(r).select_one('[data-client-account]') is not None and r.url.endswith('account=1'))
 after=state();check('Submitted target ID cannot modify another customer',after['studio_clients'][1]==before['studio_clients'][1] and after['users']==before['users'] and after['studio_jobs']==before['studio_jobs'] and after['studio_messages']==before['studio_messages'])
 check('Credentials rotate and unused invitations are revoked',after['studio_clients'][0]['password_hash']!=before['studio_clients'][0]['password_hash'] and after['studio_clients'][0]['auth_version']!=before['studio_clients'][0]['auth_version'] and after['studio_clients'][0]['invite_hash'] is None and after['studio_clients'][0]['invite_expires'] is None)
 check('HTTP session cookie and CSRF rotate',alice.cookies.get_dict()!=old_cookie and token(r)!=old_csrf)
 check('Other previously signed-in browser loses account access',soup(stale.get(account)).select_one('[data-client-account]') is None)
 check('Password rotation revokes the old session from private notifications',notification_id not in stale.get(portal+'?notifications=1').text)
 check('Updated current session retains its own private notification',notification_id in alice.get(portal+'?notifications=1').text)
 check('Credential change does not mutate feed state or delivery history',{t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['notification_feed','notification_deliveries']}==notification_before)

 r=alice.post(account,data={'action':'account_password','csrf':old_csrf,'current_password':new,'password':final,'confirm_password':final})
 check('Old form token cannot perform another credential change','session expired' in r.text.lower() and state()==after)
 old_login=requests.Session();r=submit(old_login,account,'login',{'email':'alice@example.invalid','password':old});check('Old password no longer signs in','Email or password not recognized' in r.text and soup(r).select_one('[data-client-account]') is None)
 owner=requests.Session();submit(owner,admin_url+'?view=login','login',{'email':'owner@example.invalid','password':'Synthetic owner passphrase 2026'})
 r=owner.get(account);check('Administrator sign-in is not treated as customer authentication',soup(r).select_one('[data-client-account]') is None)
 before=state();r=owner.post(account,data={'action':'account_password','csrf':token(r),'client_id':'1','current_password':new,'password':final,'confirm_password':final});check('Admin-only forged customer target is refused',state()==before and 'session ended' in r.text)
 db("UPDATE ci_settings SET value='1' WHERE name='support_policy_confirmed'")
 r=chosen_form(owner,admin_url+'?view=support','support_start','client_id',1,{})
 sid=parse_qs(urlsplit(r.url).query).get('support',[''])[0];check('Actual owner review session opens',len(sid)==32 and 'Support session' in r.text)
 before=state();review_token=token(r);r=owner.get(portal+'?support='+sid+'&account=1');check('Actual support review cannot view customer password form',r.status_code==403 and soup(r).select_one('[data-account-password]') is None and 'no-store' in r.headers.get('Cache-Control',''))
 r=owner.post(portal+'?support='+sid,data={'action':'account_password','csrf':review_token,'current_password':new,'password':final,'confirm_password':final});check('Actual support review cannot change customer credentials',state()['studio_clients']==before['studio_clients'] and soup(r).select_one('[data-account-password]') is None)
 with sync_playwright() as pw:
  browser=pw.chromium.launch(headless=True);ctx=browser.new_context(viewport={'width':1440,'height':1050});outside=[];errors=[]
  def route(req):
   u=urlsplit(req.request.url)
   if u.hostname!='127.0.0.1' or u.port!=8091:outside.append(u.hostname);req.abort()
   else:req.continue_()
  # Reuse the exact full-release navigation assertion with a separate real owner browser.
  admin_ctx=browser.new_context(viewport={'width':1440,'height':1050});admin_ctx.route('**/*',route);admin_page=admin_ctx.new_page();admin_page.on('pageerror',lambda e:errors.append(str(e)))
  admin_page.goto(admin_url+'?view=login');admin_page.get_by_label('Email address',exact=True).fill('owner@example.invalid');admin_page.get_by_label('Password',exact=True).fill('Synthetic owner passphrase 2026')
  with admin_page.expect_navigation():admin_page.locator('form button').first.click()
  from owner_navigation_contract import core_admin_destinations
  check('Actual owner navigation retains named same-origin destinations',core_admin_destinations(admin_page))
  group=admin_page.locator('[data-admin-navigation] [data-nav-group=hosting]');group.locator('summary').click()
  check('20i workspace remains visible in expanded Hosting and Domains',group.get_by_role('link',name='20i workspace',exact=True).is_visible())
  with admin_page.expect_navigation():group.get_by_role('link',name='20i workspace',exact=True).click()
  setup=admin_page.locator('form').filter(has=admin_page.locator('input[name="action"][value="provider_settings_setup"]'))
  check('Fresh 20i navigation requires explicit preparation with owner session and CSRF',parse_qs(urlsplit(admin_page.url).query)=={'view':['provider-settings']} and setup.count()==1 and bool(setup.locator('input[name="csrf"]').input_value()) and setup.locator('input[type="password"]').count()==0 and admin_page.locator('[data-provider-hub]').count()==0 and db("SELECT value FROM ci_settings WHERE name='provider_settings_schema_version'")==[])
  admin_page.screenshot(path=str(QA/'Owner-20i-Preparation.png'),full_page=True)
  provider_before={table:db('SELECT * FROM ci_'+table+' ORDER BY id') for table in ['provider_profiles','provider_revisions','provider_checks','provider_check_results']}
  setup_audit_before=db("SELECT * FROM ci_audit WHERE action='provider.setup' ORDER BY id")
  fields=setup.evaluate('(form)=>Object.fromEntries(new FormData(form))')
  refused=admin_ctx.request.post(admin_page.url,form=dict(fields,csrf='forged-20i-preparation'))
  check('Forged 20i preparation CSRF preserves unprepared provider storage',refused.status==200 and 'session expired' in refused.text().lower() and db("SELECT value FROM ci_settings WHERE name='provider_settings_schema_version'")==[] and db("SELECT * FROM ci_audit WHERE action='provider.setup' ORDER BY id")==setup_audit_before and {table:db('SELECT * FROM ci_'+table+' ORDER BY id') for table in provider_before}==provider_before)
  with admin_page.expect_navigation():setup.get_by_role('button',name='Prepare 20i settings',exact=True).click()
  setup_audit=db("SELECT user_id FROM ci_audit WHERE action='provider.setup' ORDER BY id")
  check('Actual owner preparation opens overview with empty audited provider storage',parse_qs(urlsplit(admin_page.url).query)=={'view':['provider-settings'],'section':['overview']} and admin_page.locator('[data-provider-hub]').count()==1 and db("SELECT value FROM ci_settings WHERE name='provider_settings_schema_version'")==[{'value':'1'}] and len(setup_audit)==1 and int(setup_audit[0]['user_id'])==1 and all(rows==[] for rows in provider_before.values()) and {table:db('SELECT * FROM ci_'+table+' ORDER BY id') for table in provider_before}==provider_before)
  if group.get_attribute('open') is None:group.locator('summary').click()
  with admin_page.expect_navigation():group.get_by_role('link',name='20i workspace',exact=True).click()
  check('Actual 20i navigation opens the protected workspace',parse_qs(urlsplit(admin_page.url).query)=={'view':['provider-settings']} and admin_page.locator('[data-provider-hub]').count()==1 and core_admin_destinations(admin_page))
  admin_page.screenshot(path=str(QA/'Owner-20i-Navigation.png'),full_page=True);admin_ctx.close()
  ctx.route('**/*',route);ctx.add_cookies([{'name':c.name,'value':c.value,'domain':'127.0.0.1','path':c.path} for c in alice.cookies]);page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));resp=page.goto(account)
  check('Account page renders actual customer identity in Chromium',resp.status==200 and page.locator('[data-client-account]').count()==1 and 'alice@example.invalid' in page.locator('body').inner_text())
  check('Password fields have explicit autocomplete and accessible labels',page.get_by_label('Current password',exact=True).get_attribute('autocomplete')=='current-password' and page.get_by_label('New password',exact=True).get_attribute('autocomplete')=='new-password')
  page.screenshot(path=str(QA/'Client-Account-Desktop.png'),full_page=True)
  page.get_by_label('Current password',exact=True).fill(new);page.get_by_label('New password',exact=True).fill(final);page.get_by_label('Confirm new password',exact=True).fill(final)
  with page.expect_navigation():page.get_by_role('button',name='Update password',exact=True).click()
  check('Actual browser password submission succeeds and empties fields','Password updated.' in page.locator('body').inner_text() and page.get_by_label('Current password',exact=True).input_value()=='')
  check('Browser credential change revokes the prior HTTP cookie jar',soup(alice.get(account)).select_one('[data-client-account]') is None)
  page.set_viewport_size({'width':390,'height':844});page.wait_for_timeout(200);check('Account page fits mobile viewport and retains footer',page.evaluate('document.documentElement.scrollWidth<=innerWidth+2') and page.locator('footer').count()>0);page.screenshot(path=str(QA/'Client-Account-Mobile.png'),full_page=True,animations='disabled')
  check('No external browser request or JavaScript error',not outside and not errors);ctx.close();browser.close()
 valid=sign_in('alice@example.invalid',final);check('New password works on a separate fresh session',soup(valid.get(account)).select_one('[data-client-account]') is not None)
 before=state();r=None
 for _ in range(6):r=submit(bob,account,'account_password',{'current_password':'Wrong limited synthetic value','password':new,'confirm_password':new})
 check('Repeated incorrect attempts are rate limited without credential changes','Too many password-change attempts' in r.text and state()['studio_clients']==before['studio_clients'])
 audit=json.dumps(db("SELECT user_id,action,detail FROM ci_audit WHERE action='client.password_change'"));check('Credential audit excludes raw passwords, hashes and recovery tokens',len(db("SELECT id FROM ci_audit WHERE action='client.password_change'"))==2 and all(secret not in audit for secret in [old,new,final,'@example.invalid','e'*64]))
except Exception as exc:
 checks.append({'name':type(exc).__name__+': '+str(exc),'passed':False});print(type(exc).__name__+': '+str(exc),flush=True)
 if isinstance(exc,subprocess.CalledProcessError) and exc.stderr:(QA/'fixture-command.stderr.log').write_text(exc.stderr)
finally:
 try:
  stopped=subprocess.run(['docker','stop','--time','5',name],capture_output=True,text=True,timeout=15);proc.wait(timeout=10)
  if stopped.returncode:checks.append({'name':'Fixture container cleanup failed','passed':False})
 except (OSError,subprocess.TimeoutExpired) as exc:checks.append({'name':'Fixture cleanup failed: '+str(exc),'passed':False})
 log.close();text=(QA/'server.log').read_text();checks.append({'name':'No unexpected PHP or application diagnostics','passed':not any(s in text for s in ['PHP Fatal error','PHP Warning','PHP Notice','PHP Deprecated','CWP ['])})
 passed=bool(checks) and all(c['passed'] for c in checks);(QA/'browser-results.json').write_text(json.dumps({'passed':passed,'source_sha':os.environ.get('CWP_SOURCE_SHA'),'scope':'Actual isolated customer/admin/support HTTP, database and Chromium credential lifecycle; no real user or external provider','checks':checks},indent=2)+'\n')
print(json.dumps({'passed':passed,'checks':len(checks)}));raise SystemExit(0 if passed else 1)
