"""Current-owner forms over real local HTTP and Chromium. Disposable SQL only."""
from pathlib import Path
from urllib.parse import urljoin, urlsplit, parse_qs
import ast, json, os, shutil, subprocess, time
import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright, expect
from owner_browser_forms import refuse_owner_action, assert_no_owner_reprompt
ROOT=Path(__file__).resolve().parents[1];WEB=ROOT/'test-runtime/owner-session-web';QA=ROOT/'qa/owner-session';BASE='http://127.0.0.1:8091'
QA.mkdir(parents=True,exist_ok=True);php=os.environ.get('PHP_BINARY','php')
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Disposable loopback SQL only')
for node in ast.parse((ROOT/'tests/browser_acceptance.py').read_text()).body:
 if isinstance(node,ast.FunctionDef) and node.name in {'soup','token','form_data','form_for','submit'}:exec(compile(ast.Module(body=[node],type_ignores=[]),'retained-form-helpers','exec'),globals())
checks=[]
def check(name,ok):
 checks.append({'name':name,'passed':bool(ok)})
 if not ok:raise AssertionError(name)
subprocess.run([php,str(ROOT/'tests/tickets_installed_seed.php')],check=True,cwd=ROOT)
site=WEB/'existing'
if site.exists():raise SystemExit('Stale installed fixture refused')
shutil.copytree(ROOT/'site',site);shutil.copyfile(ROOT/'test-runtime/config-fixture.php',site/'config.php');(site/'.installed.php').write_text('<?php exit; ?>synthetic installed guard')
server='cwp-owner-session-http';log=(QA/'server.log').open('w')
args=['docker','run','--rm','--init','--name',server,'--network','host','--user',str(os.getuid())+':'+str(os.getgid()),'-v',str(ROOT)+':'+str(ROOT),'-w',str(ROOT)]
for key in ['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD','CWP_SOURCE_SHA']:args+=['-e',key]
proc=subprocess.Popen(args+['cwp-owner-session','php','-S','127.0.0.1:8091','-t',str(WEB)],stdout=log,stderr=log)
import release_cases
release_cases.ROOT=ROOT;release_cases.php=php
for key in ['soup','form_data']:setattr(release_cases,key,globals()[key])
from release_cases import db,chosen_form
OWNER='Synthetic owner passphrase 2026';STAFF='Synthetic staff current-session passphrase';adminurl=BASE+'/existing/admin/index.php';url=adminurl+'?view=staff'
try:
 for _ in range(50):
  try:requests.get(adminurl+'?view=login',timeout=1);break
  except requests.RequestException:time.sleep(.2)
 else:raise AssertionError('Installed fixture unavailable')
 owner=requests.Session();r=submit(owner,adminurl+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
 check('Actual owner login succeeds',r.status_code==200 and 'view=login' not in r.url)
 original=db('SELECT * FROM ci_studio_clients ORDER BY id');jobs=db('SELECT * FROM ci_studio_jobs ORDER BY id')
 fields={'id':'0','name':'HTTP delegated staff','email':'delegated@example.invalid','role':'administrator','enabled':'1','permissions[]':['website.manage']}
 r=owner.get(url);check('Owner account management renders without repeat password',r.status_code==200 and soup(r).select_one('[name=owner_password],[name=current_password]') is None)
 r=submit(owner,url,'staff_save',{**fields,'csrf':'invalid'})
 check('Invalid CSRF still refuses staff creation','session expired' in r.text.lower() and not db('SELECT id FROM ci_users WHERE email=?',[fields['email']]))
 refuse_owner_action(globals(),owner,url,'staff_save',fields)
 r=submit(owner,url,'staff_save',fields);uid=db('SELECT id FROM ci_users WHERE email=?',[fields['email']])[0]['id']
 check('Current owner creates staff without a second password',r.status_code==200 and db('SELECT password_hash FROM ci_users WHERE id=?',[uid])==[{'password_hash':''}])
 r=chosen_form(owner,url,'staff_invite','id',uid,{})
 link=soup(r).select_one('[data-staff-invite]')['href'];code=parse_qs(urlsplit(link).fragment)['token'][0]
 check('Private invitation remains fragment-only and displayed once',len(code)==64 and 'token=' not in urlsplit(link).query and soup(owner.get(url)).select_one('[data-staff-invite]') is None)
 member=requests.Session();r=submit(member,link,'staff_activate',{'token':code,'password':STAFF,'confirm_password':STAFF})
 check('New staff chooses a real new password',db('SELECT password_hash FROM ci_users WHERE id=?',[uid])[0]['password_hash']!='' and 'view=login' in r.url)
 r=submit(member,adminurl+'?view=login','login',{'email':fields['email'],'password':STAFF})
 check('Activated staff signs in normally',r.status_code==200 and 'view=login' not in r.url)
 check('Delegated staff has only selected permission',json.loads(db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[uid])[0]['permissions'])==['website.manage'])
 for view in ['staff','email','studio-clients','payments','health']:
  check('Website-only staff is denied '+view,member.get(adminurl+'?view='+view).status_code==403)
 r=member.get(adminurl+'?view=security');member.post(r.url,data={'csrf':token(r),'action':'staff_save',**fields,'id':str(uid),'role':'owner'})
 check('Forged self-promotion remains blocked',db('SELECT role FROM ci_staff_profiles WHERE user_id=?',[uid])==[{'role':'administrator'}])
 with sync_playwright() as pw:
  browser=pw.chromium.launch(headless=True);context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');errors=[];external=[]
  def bounded(route):
   u=urlsplit(route.request.url)
   if u.hostname!='127.0.0.1' or u.port!=8091:external.append(u.hostname);route.abort()
   else:route.continue_()
  context.route('**/*',bounded);page=context.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto(adminurl+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').click();page.wait_for_url('**/admin/index.php?view=dashboard');page.goto(url)
  edit=page.locator('form').filter(has=page.locator('[name=action][value=staff_save]')).filter(has=page.locator('[name=id][value="'+str(uid)+'"]'))
  assert_no_owner_reprompt(check,edit,'Disable staff');edit.locator('[name=enabled]').uncheck();edit.locator('button').click();expect(edit.locator('[name=enabled]')).not_to_be_checked()
  check('Disabling staff revokes its already-open session','view=login' in member.get(adminurl).url)
  r=submit(member,adminurl+'?view=login','login',{'email':fields['email'],'password':STAFF})
  check('Disabled staff cannot sign in with the old password','email or password is not correct' in r.text)
  edit.locator('[name=enabled]').check();assert_no_owner_reprompt(check,edit,'Reenable staff');edit.locator('button').click();expect(edit.locator('[name=enabled]')).to_be_checked()
  invite=page.locator('form').filter(has=page.locator('[name=action][value=staff_invite]')).filter(has=page.locator('[name=id][value="'+str(uid)+'"]'))
  assert_no_owner_reprompt(check,invite,'Recover staff');invite.locator('button').click();expect(page.locator('[data-staff-invite]')).to_be_visible()
  check('Recovery still retires the old password',db('SELECT password_hash FROM ci_users WHERE id=?',[uid])==[{'password_hash':''}])
  newlink=page.locator('[data-staff-invite]').get_attribute('href');recipient_context=browser.new_context();recipient_context.route('**/*',bounded);recovery=recipient_context.new_page();recovery.on('pageerror',lambda e:errors.append(str(e)));recovery.goto(newlink);recovery.locator('[name=password]').fill(STAFF);recovery.locator('[name=confirm_password]').fill(STAFF);recovery.get_by_role('button',name='Set up account').click();expect(recovery.locator('[name=email]')).to_be_visible()
  check('Recovery restores a chosen credential without changing delegated permissions',db('SELECT password_hash FROM ci_users WHERE id=?',[uid])[0]['password_hash']!='' and json.loads(db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[uid])[0]['permissions'])==['website.manage'])
  recipient_context.close();response=page.goto(url)
  check('Owner remains authenticated after separate recipient activation',response.status==200 and page.url==url and page.locator('form [name=action][value=staff_save]').count()>=2 and page.locator('form [name=action][value=login]').count()==0)
  check('Desktop capture shows actual staff management',page.locator('footer').count()>0 and page.locator('form [name=email][value="'+fields['email']+'"]').count()==1)
  page.screenshot(path=str(QA/'Owner-Staff-Desktop.png'),full_page=True)
  page.set_viewport_size({'width':390,'height':844})
  check('Mobile capture retains actual staff-management forms',page.url==url and page.locator('form [name=action][value=staff_save]').count()>=2)
  page.screenshot(path=str(QA/'Owner-Staff-Mobile.png'),full_page=True,animations='disabled')
  check('Staff workspace fits mobile',page.evaluate('document.documentElement.scrollWidth<=innerWidth+2'))
  check('No external request or browser error',not external and not errors)
  context.close();browser.close()
 check('All existing client records and projects retained',db('SELECT * FROM ci_studio_clients ORDER BY id')==original and db('SELECT * FROM ci_studio_jobs ORDER BY id')==jobs)
 owner.close();member.close()
except Exception as exc:
 checks.append({'name':type(exc).__name__+': '+str(exc),'passed':False});print(str(exc),flush=True)
finally:
 try:
  stopped=subprocess.run(['docker','stop','--time','5',server],capture_output=True,text=True,timeout=15)
  if stopped.returncode:checks.append({'name':'Fixture cleanup failed','passed':False})
  proc.wait(timeout=10)
 except (OSError,subprocess.TimeoutExpired) as exc:
  checks.append({'name':'Fixture cleanup exception: '+str(exc),'passed':False})
  subprocess.run(['docker','rm','-f',server],capture_output=True,timeout=15)
 finally:log.close()
 text=(QA/'server.log').read_text();checks.append({'name':'No PHP or handled application diagnostics','passed':not any(s in text for s in ['PHP Fatal error','PHP Warning','PHP Notice','PHP Deprecated','CWP ['])})
 passed=bool(checks) and all(c['passed'] for c in checks)
 (QA/'results.json').write_text(json.dumps({'passed':passed,'source_sha':os.environ.get('CWP_SOURCE_SHA'),'scope':'Real synthetic SQL, HTTP and Chromium current-owner/staff management; no live email/provider/payment operation','checks':checks},indent=2)+'\n')
print(json.dumps({'passed':passed,'checks':len(checks)}));raise SystemExit(0 if passed else 1)
