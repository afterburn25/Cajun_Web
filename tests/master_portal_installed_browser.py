"""Real installed HTTP/Chromium journey against synthetic master-portal SQL only."""
from pathlib import Path
from urllib.parse import urlsplit
import json, os, shutil, subprocess, time, urllib.request
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'qa'/'master-portal-browser';OUT.mkdir(parents=True,exist_ok=True)
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Disposable loopback SQL only')
image=os.environ['CWP_TEST_IMAGE']
if not image.startswith('cwp-integration-php:'):raise SystemExit('Unexpected fixture image')
args=['docker','run','--rm','--network','host','--user',f'{os.getuid()}:{os.getgid()}','-v',str(ROOT)+':/workspace','-w','/workspace']
for key in ['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD']:args+=['-e',key]
def probe(mode):return subprocess.check_output(args+[image,'php','tests/master_portal_native.php',mode],cwd=ROOT)
def state():return json.loads(probe('snapshot'))
checks=[];errors=[];external=[]
def check(name,ok):
 checks.append({'name':name,'passed':bool(ok)})
 if not ok:raise AssertionError(name)
web=ROOT/'test-runtime'/'master-web';dest=web/'master'
if dest.exists():raise SystemExit('Refuse stale browser fixture')
shutil.copytree(ROOT/'site',dest);(dest/'config.php').write_bytes(probe('config'));(dest/'.installed.php').write_text('<?php exit; ?>synthetic guard')
(dest/'install.php').unlink(missing_ok=True)
server='cwp-master-portal-browser';base='http://127.0.0.1:8092/master';hub=base+'/portal/index.php?master=1'
subprocess.run(args[:2]+['-d']+args[2:]+['--name',server,image,'php','-S','127.0.0.1:8092','-t','test-runtime/master-web'],check=True,capture_output=True)
try:
 for _ in range(40):
  try:urllib.request.urlopen(base+'/admin/index.php?view=login',timeout=2).read();break
  except OSError:time.sleep(.5)
 else:raise RuntimeError('Loopback fixture unavailable')
 with sync_playwright() as p:
  browser=p.chromium.launch(headless=True)
  def context():
   c=browser.new_context(viewport={'width':1440,'height':1000})
   def bound(route):
    u=urlsplit(route.request.url)
    if u.hostname!='127.0.0.1' or u.port!=8092:external.append(u.hostname);route.abort()
    else:route.continue_()
   c.route('**/*',bound);return c
  owner=context();page=owner.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  initial=state();r=page.goto(hub)
  check('Signed-out master route uses existing admin sign-in',r.status==200 and 'view=login' in page.url and 'portal=master' in page.url)
  form=page.locator('form').filter(has=page.locator('[name="action"][value="login"]'))
  form.locator('[name="email"]').fill('master-owner@example.invalid');form.locator('[name="password"]').fill('Synthetic master portal passphrase')
  with page.expect_navigation():form.locator('button').first.click()
  page.screenshot(path=str(OUT/'master-after-login.png'),full_page=True)
  check('Same administrator credentials return directly to master hub','master=1' in page.url and page.get_by_role('heading',name='Master client portal',exact=True).count()==1)
  check('Master hub has no second password control',page.locator('input[type="password"]').count()==0)
  check('Admin login creates no customer and preserves credentials',state()['clients']==initial['clients'] and state()['credentials_hash']==initial['credentials_hash'])
  page.goto(hub+'&q=Alpha');check('Owner can find an active client not opted in for staff','Alpha Client' in page.locator('#master-client-accounts').inner_text())
  check('Customer sharing owner email is not silently signed in',page.locator('[name="action"][value="support_start"]').count()==1 and 'Master client portal' in page.title())
  form=page.locator('form').filter(has=page.locator('[name="action"][value="support_start"]'))
  fields=form.evaluate('(f)=>Object.fromEntries(new FormData(f))');before=state()
  bad=owner.request.post(base+'/admin/index.php?view=support',form=dict(fields,csrf='invalid'))
  check('Forged open-review CSRF does not create a session',bad.status in (200,400,403) and state()['sessions']==before['sessions'])
  with page.expect_navigation():form.locator('button').first.click()
  check('Client opens through audited owner-review context','support=' in page.url and page.locator('.support-banner').count()==1)
  observed=state();check('Owner attribution and customer identity retained',observed['owner_reviews']==before['owner_reviews']+1 and observed['last_session']=={'actor_id':1,'client_id':1,'context':'Owner account review'})
  check('Customer credentials untouched by review',observed['credentials_hash']==initial['credentials_hash'])
  with page.expect_navigation():page.get_by_role('button',name='Return to admin',exact=True).click()
  page.get_by_role('link',name='Master client portal',exact=True).click()
  check('Return and re-open master hub need no additional sign-in','master=1' in page.url and page.locator('input[type="password"]').count()==0)
  page.goto(base+'/portal/index.php');check('Bare portal recognizes current admin session','master=1' in page.url)
  page.goto(hub+'&q=%25');check('Literal wildcard search finds only exact matching client',page.locator('#master-client-accounts article').count()==1 and '100% Studio' in page.locator('#master-client-accounts').inner_text())
  page.goto(hub+'&q=Disabled');check('Disabled client is not offered for review',page.locator('#master-client-accounts article').count()==0)
  page.goto(hub+'&portal_page=2');check('Second page of owner clients is reachable',page.locator('#master-client-accounts article').count()==7)
  page.goto(hub+'&q=Alpha');page.screenshot(path=str(OUT/'master-owner.png'),full_page=True)
  page.set_viewport_size({'width':390,'height':844});check('Master portal mobile layout does not overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth+2'));page.screenshot(path=str(OUT/'master-mobile.png'),full_page=True)
  r=page.goto(hub+'&support=invalid');check('Mixed support and master route is refused',r.status==403 and page.locator('#master-client-accounts').count()==0)
  r=owner.request.post(hub,form={'action':'support_start','client_id':'2'});check('Hub does not accept state-changing POST requests',r.status==405)
  staff=context();sp=staff.new_page();sp.goto(hub)
  sf=sp.locator('form').filter(has=sp.locator('[name="action"][value="login"]'));sf.locator('[name="email"]').fill('master-staff@example.invalid');sf.locator('[name="password"]').fill('Synthetic master portal passphrase')
  with sp.expect_navigation():sf.locator('button').first.click()
  check('Permitted administrator enters master workspace','master=1' in sp.url)
  body=sp.locator('#master-client-accounts').inner_text();check('Staff sees only approved client without owner-wide discovery','Beta Client' in body and 'Alpha Client' not in body and sp.locator('#master-client-accounts article').count()==1)
  sf=sp.locator('form').filter(has=sp.locator('[name="action"][value="support_start"]'));fields=sf.evaluate('(f)=>Object.fromEntries(new FormData(f))');before=state()
  staff.request.post(base+'/admin/index.php?view=support',form=dict(fields,client_id='1'))
  check('Direct staff request cannot switch to unapproved client',state()['sessions']==before['sessions'])
  customer=context();cp=customer.new_page();cp.goto(base+'/portal/index.php')
  cf=cp.locator('form').filter(has=cp.locator('[name="action"][value="login"]'));cf.locator('[name="email"]').fill('master-owner@example.invalid');cf.locator('[name="password"]').fill('Synthetic customer-only passphrase')
  with cp.expect_navigation():cf.locator('button').first.click()
  check('Customer authenticates with independent customer password',cp.locator('[name="action"][value="login"]').count()==0 and 'master=1' not in cp.url)
  cp.goto(hub);check('Matching customer email does not grant master access','view=login' in cp.url and cp.locator('#master-client-accounts').count()==0)
  page.goto(base+'/portal/index.php?hosting=catalog');check('Customer deep link is not replaced by master hub','master=1' not in page.url)
  probe('revoke');page.goto(hub);check('Revoked owner cannot reuse master portal session','view=login' in page.url and page.locator('#master-client-accounts').count()==0)
  check('No unexpected external browser requests',external==[]);check('No browser JavaScript errors',errors==[])
  for c in [owner,staff,customer]:c.close()
  browser.close()
except Exception as e:checks.append({'name':type(e).__name__+': '+str(e),'passed':False})
finally:
 logs=subprocess.run(['docker','logs',server],capture_output=True,text=True);text=logs.stdout+logs.stderr;(OUT/'server.log').write_text(text)
 subprocess.run(['docker','rm','-f',server],capture_output=True)
 checks.append({'name':'No PHP errors warnings or handled application exceptions in fixture server','passed':not any(s in text for s in ['PHP Fatal error','PHP Warning','PHP Notice','PHP Deprecated','CWP ['])})
 passed=bool(checks) and all(c['passed'] for c in checks)
 (OUT/'results.json').write_text(json.dumps({'passed':passed,'scope':'Installed synthetic SQL, actual administrator/customer logins and Chromium; no live account or provider action','source_sha':os.environ.get('CWP_SOURCE_SHA'),'checks':checks,'javascript_errors':errors,'external_requests':external},indent=2)+'\n')
print(json.dumps({'passed':passed,'checks':len(checks)}));raise SystemExit(0 if passed else 1)
