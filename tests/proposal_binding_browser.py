"""Real loopback SQL, authenticated HTTP and two-tab Chromium proposal review."""
from pathlib import Path
from urllib.parse import urljoin, urlsplit
import ast,json,os,shutil,subprocess,time
import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];WEB=ROOT/'test-runtime/proposal-web';QA=ROOT/'qa/proposal-binding';BASE='http://127.0.0.1:8091'
QA.mkdir(parents=True,exist_ok=True);php=os.environ.get('PHP_BINARY','php')
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Synthetic loopback SQL only')
for node in ast.parse((ROOT/'tests/browser_acceptance.py').read_text()).body:
 if isinstance(node,ast.FunctionDef) and node.name in {'soup','token','form_data','form_for','submit'}:exec(compile(ast.Module(body=[node],type_ignores=[]),'retained-http-helpers','exec'),globals())
checks=[]
def check(name,ok):
 checks.append({'name':name,'passed':bool(ok)})
 if not ok:raise AssertionError(name)
subprocess.run([php,str(ROOT/'tests/proposal_binding_seed.php')],check=True,cwd=ROOT)
site=WEB/'existing'
if site.exists():raise SystemExit('Refuse stale installed test fixture')
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
  from proposals_cases import run_browser
  run_browser(globals(),browser)
  root=BASE+'/existing/admin/index.php';portal=BASE+'/existing/portal/index.php';url=root+'?view=proposals'
  owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
  alice_id=db("SELECT id FROM ci_studio_clients WHERE email='alice@example.invalid'")[0]['id']
  draft={'client_id':str(alice_id),'title':'Two-tab reviewed proposal','deliverables':'Build reviewed scope','exclusions':'Unrelated scope','milestones':'Design | 1000.00','schedule_notes':'Initial reviewed schedule'}
  submit(owner,url,'proposal_create',draft);pid=db('SELECT id FROM ci_proposals WHERE title=?',[draft['title']])[0]['id'];detail=root+'?view=proposal&id='+pid
  submit(owner,detail,'proposal_send',{})
  context=browser.new_context(viewport={'width':1440,'height':1000});external=[];errors=[]
  def bound(route):
   u=urlsplit(route.request.url)
   if u.hostname!='127.0.0.1' or u.port!=8091:external.append(u.hostname);route.abort()
   else:route.continue_()
  context.route('**/*',bound);page=context.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto(portal);login=page.locator('form').filter(has=page.locator('[name="action"][value="login"]'));login.locator('[name=email]').fill('alice@example.invalid');login.locator('[name=password]').fill('Synthetic client passphrase 2026')
  with page.expect_navigation():login.locator('button').first.click()
  page.goto(portal+'?proposal='+pid);stale=page.locator('form').filter(has=page.locator('[name="action"][value="proposal_decide"]'));old_id=stale.locator('[name=version_id]').input_value()
  second=context.new_page();second.goto(page.url);form=second.locator('form').filter(has=second.locator('[name="action"][value="proposal_decide"]'));form.locator('[name=choice]').select_option('changes');form.locator('[name=note]').fill('Please revise the schedule');form.locator('[name=confirm]').check()
  with second.expect_navigation():form.locator('button').click()
  check('Customer change request through Chromium is persisted',db('SELECT status FROM ci_proposals WHERE id=?',[pid])[0]['status']=='changes-requested')
  submit(owner,detail,'proposal_revise',{**draft,'schedule_notes':'Revised and separately reviewed schedule'});submit(owner,detail,'proposal_send',{})
  def state():return [db('SELECT * FROM ci_'+t+' WHERE '+('id' if t=='proposals' else 'proposal_id')+'=? ORDER BY '+('version' if t=='proposal_versions' else 'id'),[pid]) for t in ['proposals','proposal_versions','proposal_decisions','proposal_events']]
  before=state();stale.locator('[name=confirm]').check()
  with page.expect_navigation():stale.locator('button').click()
  check('Actual old browser tab is refused without changing proposal or history','changed after you reviewed' in page.locator('body').inner_text() and state()==before)
  fresh=page.locator('form').filter(has=page.locator('[name="action"][value="proposal_decide"]'));check('Returned form displays and binds the new version',fresh.locator('[name=version_id]').input_value()!=old_id and 'Revised and separately reviewed schedule' in page.locator('body').inner_text())
  page.screenshot(path=str(QA/'stale-proposal-refused.png'),full_page=True)
  fresh.locator('[name=confirm]').check()
  with page.expect_navigation():fresh.locator('button').click()
  check('Explicitly reviewed new version can be approved',db('SELECT status,current_version FROM ci_proposals WHERE id=?',[pid])==[{'status':'approved','current_version':2}] and page.locator('[name=choice]').count()==0)
  page.set_viewport_size({'width':390,'height':844});page.wait_for_timeout(250)
  check('Approved proposal fits mobile and renders footer',page.evaluate('document.documentElement.scrollWidth<=innerWidth+2') and page.locator('footer').count()>0)
  page.screenshot(path=str(QA/'proposal-approved-mobile.png'),full_page=True,animations='disabled')
  check('Browser had no external calls or JavaScript errors',not external and not errors)
  context.close();browser.close();owner.close()
except Exception as exc:checks.append({'name':type(exc).__name__+': '+str(exc),'passed':False})
finally:
 proc.terminate();proc.wait(timeout=20);log.close();text=(QA/'server.log').read_text();checks.append({'name':'No PHP or handled application errors','passed':not any(x in text for x in ['PHP Fatal error','PHP Warning','PHP Notice','PHP Deprecated','CWP ['])})
 passed=bool(checks) and all(c['passed'] for c in checks)
 (QA/'browser-results.json').write_text(json.dumps({'passed':passed,'source_sha':os.environ.get('CWP_SOURCE_SHA'),'scope':'Actual installed synthetic SQL, authenticated forms and two-tab Chromium; no live customer or payment operation','checks':checks},indent=2)+'\n')
print(json.dumps({'passed':passed,'checks':len(checks)}));raise SystemExit(0 if passed else 1)
