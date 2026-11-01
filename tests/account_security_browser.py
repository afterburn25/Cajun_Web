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
 alice=sign_in('alice@example.invalid',old);stale=sign_in('alice@example.invalid',old);bob=sign_in('bob@example.invalid',old)
 r=alice.get(portal);check('Customer workspace links to account settings',bool(soup(r).select_one('a[href$="?account=1"]')))
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
