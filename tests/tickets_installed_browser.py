"""Actual installed ticket forms, permissions, privacy and browser layouts. Synthetic loopback fixture only."""
from pathlib import Path
from urllib.parse import urljoin, urlsplit
import ast,json,os,shutil,subprocess,time
import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];WEB=ROOT/'test-runtime/tickets-web';QA=ROOT/'qa/tickets-integration';BASE='http://127.0.0.1:8091'
QA.mkdir(parents=True,exist_ok=True);php=os.environ.get('PHP_BINARY','php')
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Synthetic loopback SQL only')
for node in ast.parse((ROOT/'tests/browser_acceptance.py').read_text()).body:
 if isinstance(node,ast.FunctionDef) and node.name in {'soup','token','form_data','form_for','submit'}:exec(compile(ast.Module(body=[node],type_ignores=[]),'retained-http-helpers','exec'),globals())
checks=[]
def check(name,ok):
 checks.append({'name':name,'passed':bool(ok)})
 if not ok:raise AssertionError(name)
subprocess.run([php,str(ROOT/'tests/tickets_installed_seed.php')],check=True,cwd=ROOT)
from provider_http_server import ProviderServer
from mail_inbox_http_server import InboxServer
provider_server=ProviderServer(ROOT);inbox_server=InboxServer(ROOT)
(QA/'mail-capture.txt').write_bytes(b'')
site=WEB/'existing'
if site.exists():raise SystemExit('Refuse stale installed test fixture')
shutil.copytree(ROOT/'site',site);shutil.copyfile(ROOT/'test-runtime/config-fixture.php',site/'config.php');(site/'.installed.php').write_text('<?php exit; ?>synthetic installed guard')
server_name='cwp-tickets-integration-http'
server_args=['docker','run','--rm','--init','--name',server_name,'--network','host','--user',str(os.getuid())+':'+str(os.getgid()),'-v',str(ROOT)+':'+str(ROOT),'-w',str(ROOT)]
for key in ['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD','CWP_SOURCE_SHA']:server_args+=['-e',key]
log=(QA/'server.log').open('w');proc=subprocess.Popen(server_args+['cwp-tickets-review','php','-S','127.0.0.1:8091','-t',str(WEB)],cwd=ROOT,stdout=log,stderr=log,start_new_session=True)
import release_cases
release_cases.ROOT=ROOT;release_cases.php=php
# chosen_form is intentionally the unchanged shared HTTP helper.
for helper in ['soup','form_data']:setattr(release_cases,helper,globals()[helper])
from release_cases import db
installed_root=site
from staff_cases import OWNER
try:
 for _ in range(50):
  try:requests.get(BASE+'/existing/admin/index.php?view=login',timeout=1);break
  except requests.RequestException:time.sleep(.2)
 else:raise AssertionError('Installed fixture unavailable')
 with sync_playwright() as pw:
  browser=pw.chromium.launch(headless=True)
  from ticket_cases import run_browser
  try:run_browser(globals(),browser)
  finally:browser.close()
except Exception as exc:
 failure=type(exc).__name__+': '+str(exc);checks.append({'name':failure,'passed':False});print(failure,flush=True)
 if isinstance(exc,subprocess.CalledProcessError) and exc.stderr:(QA/'fixture-command.stderr.log').write_text(exc.stderr)
finally:
 # Stop this fixture container, not merely its attached docker client. Preserve
 # the earlier assertion failure even if cleanup itself encounters an error.
 try:
  stopped=subprocess.run(['docker','stop','--time','5',server_name],capture_output=True,text=True,timeout=15)
  if stopped.returncode!=0:checks.append({'name':'Fixture container cleanup failed: '+stopped.stderr.strip(),'passed':False})
  proc.wait(timeout=10)
 except (subprocess.TimeoutExpired,OSError) as exc:
  checks.append({'name':'Fixture cleanup failure: '+str(exc),'passed':False})
  try:subprocess.run(['docker','rm','-f',server_name],capture_output=True,text=True,timeout=15)
  except (subprocess.TimeoutExpired,OSError) as cleanup:checks.append({'name':'Fixture removal failure: '+str(cleanup),'passed':False})
  if proc.poll() is None:
   proc.kill()
   try:proc.wait(timeout=5)
   except subprocess.TimeoutExpired:checks.append({'name':'Fixture docker client did not terminate','passed':False})
 finally:
  log.close()
  provider_server.close();inbox_server.close()
 text=(QA/'server.log').read_text();checks.append({'name':'No PHP or handled application errors','passed':not any(x in text for x in ['PHP Fatal error','PHP Warning','PHP Notice','PHP Deprecated','CWP ['])})
 passed=bool(checks) and all(c['passed'] for c in checks)
 (QA/'browser-results.json').write_text(json.dumps({'passed':passed,'source_sha':os.environ.get('CWP_SOURCE_SHA'),'scope':'Actual installed synthetic SQL and complete ticket HTTP/Chromium cases: owner, dedicated staff, clients, support context, public/private records; no real provider or payment operation','checks':checks},indent=2)+'\n')
print(json.dumps({'passed':passed,'checks':len(checks)}));raise SystemExit(0 if passed else 1)
