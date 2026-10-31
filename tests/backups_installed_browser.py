"""Execute retained backup HTTP cases and current-session denials in a real fixture."""
from pathlib import Path
from urllib.parse import urljoin, urlsplit
import ast,json,os,shutil,subprocess,time
import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];WEB=ROOT/'test-runtime/backup-web';QA=ROOT/'qa/backup-integration';BASE='http://127.0.0.1:8091'
QA.mkdir(parents=True,exist_ok=True);php=os.environ.get('PHP_BINARY','php')
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Synthetic loopback SQL only')
# Reuse the unchanged historical request/form helpers, without executing its suite.
scope=globals();source=ast.parse((ROOT/'tests/browser_acceptance.py').read_text())
names={'soup','token','form_data','form_for','submit'}
for node in source.body:
 if isinstance(node,ast.FunctionDef) and node.name in names:exec(compile(ast.Module(body=[node],type_ignores=[]),'retained-request-helpers','exec'),scope)
checks=[]
def check(name,ok):
 checks.append({'name':name,'passed':bool(ok)})
 if not ok:raise AssertionError(name)
def login(email,password,portal=False):
 if portal:raise AssertionError('No customer login required by backup fixture')
 session=requests.Session();r=submit(session,BASE+'/existing/admin/index.php?view=login','login',{'email':email,'password':password})
 check('Actual administrator login '+email,r.status_code==200 and 'view=login' not in r.url and 'We could not complete' not in r.text)
 return session
subprocess.run([php,str(ROOT/'tests/backups_installed_seed.php')],check=True,cwd=ROOT)
site=WEB/'existing'
if site.exists():raise SystemExit('Stale installed fixture refused')
shutil.copytree(ROOT/'site',site);shutil.copyfile(ROOT/'test-runtime/config-fixture.php',site/'config.php');(site/'.installed.php').write_text('<?php exit; ?>synthetic installed guard')
log=(QA/'server.log').open('w');proc=subprocess.Popen([php,'-S','127.0.0.1:8091','-t',str(WEB)],cwd=ROOT,stdout=log,stderr=log,start_new_session=True)
import release_cases
release_cases.ROOT=ROOT;release_cases.php=php
from release_cases import db
from staff_cases import OWNER
try:
 for _ in range(50):
  try:requests.get(BASE+'/existing/admin/index.php?view=login',timeout=1);break
  except requests.RequestException:time.sleep(.2)
 else:raise AssertionError('Installed fixture unavailable')
 with sync_playwright() as pw:
  browser=pw.chromium.launch(headless=True)
  from backups_cases import run_browser
  run_browser(globals(),browser)
  owner=login('owner@example.invalid',OWNER);url=BASE+'/existing/admin/index.php?view=backups'
  r=owner.get(url);before=db('SELECT * FROM ci_backups ORDER BY id');data=form_data(form_for(r,'backups_prune'))
  r=owner.post(url,data=data);check('HTTP pruning without confirmation is refused and preserves records','Confirm removal of expired backups.' in r.text and db('SELECT * FROM ci_backups ORDER BY id')==before)
  form=form_data(form_for(owner.get(url),'backups_create'));form.update(confirm='1',retain_days='30',notes='Must not create')
  r=owner.post(url+'&support=synthetic-invalid',data=form)
  check('Support-context request cannot create a backup',db('SELECT * FROM ci_backups ORDER BY id')==before and 'Backup snapshot recorded' not in r.text)
  context=browser.new_context(viewport={'width':1440,'height':1000});external=[];errors=[]
  def bound(route):
   u=urlsplit(route.request.url)
   if u.hostname!='127.0.0.1' or u.port!=8091:external.append(u.hostname);route.abort()
   else:route.continue_()
  context.route('**/*',bound)
  context.add_cookies([{'name':c.name,'value':c.value,'domain':'127.0.0.1','path':c.path} for c in owner.cookies])
  page=context.new_page();page.on('pageerror',lambda e:errors.append(str(e)));resp=page.goto(url)
  check('Backup workspace fully renders in Chromium',resp.status==200 and page.get_by_role('heading',name='Stored backups',exact=True).count()==1 and page.locator('footer').count()>0)
  check('Backup workspace contains no repeated password field',page.locator('input[type=password]').count()==0)
  page.screenshot(path=str(QA/'backups-desktop.png'),full_page=True)
  page.set_viewport_size({'width':390,'height':844});page.wait_for_function("()=>document.querySelector('.admin-sidebar').getBoundingClientRect().right<=1")
  check('Backup workspace fits mobile viewport',page.evaluate('document.documentElement.scrollWidth<=innerWidth+2'))
  page.screenshot(path=str(QA/'backups-mobile.png'),full_page=True,animations='disabled')
  check('No external browser request or JavaScript error',not external and not errors)
  context.close();browser.close()
  db('UPDATE ci_users SET auth_version=? WHERE id=1',['f'*32]);r=owner.post(url,data=form)
  check('Revoked owner session cannot create or retain archive access','view=login' in r.url and db('SELECT * FROM ci_backups ORDER BY id')==before)
  bid=before[0]['id'];r=owner.get(url+'&download='+bid,allow_redirects=False)
  check('Revoked archive download is denied',r.status_code in (302,303,403,404) and 'application/zip' not in r.headers.get('Content-Type',''))
except Exception as exc:checks.append({'name':type(exc).__name__+': '+str(exc),'passed':False})
finally:
 proc.terminate();proc.wait(timeout=20);log.close();text=(QA/'server.log').read_text()
 check_logs=not any(x in text for x in ['PHP Fatal error','PHP Warning','PHP Notice','PHP Deprecated','CWP ['])
 checks.append({'name':'No unexpected PHP or handled application diagnostics','passed':check_logs})
 passed=bool(checks) and all(c['passed'] for c in checks)
 (QA/'browser-results.json').write_text(json.dumps({'passed':passed,'source_sha':os.environ.get('CWP_SOURCE_SHA'),'scope':'Real installed synthetic SQL/admin HTTP and Chromium; no production or external provider','checks':checks},indent=2)+'\n')
print(json.dumps({'passed':passed,'checks':len(checks)}));raise SystemExit(0 if passed else 1)
