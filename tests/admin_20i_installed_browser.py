"""Real Chromium + HTTP + fixture SQL. No production URL or external request."""
from pathlib import Path
from urllib.parse import urlsplit
import json, os, shutil, subprocess, time, urllib.request
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];OUT=ROOT/'qa'/'admin-20i-browser';OUT.mkdir(parents=True,exist_ok=True)
WEB=ROOT/'test-runtime'/'integration-web';image=os.environ['CWP_TEST_IMAGE']
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Only disposable loopback SQL is permitted')
args=['docker','run','--rm','--network','host','--user',str(os.getuid())+':'+str(os.getgid()),'-v',str(ROOT)+':/workspace','-w','/workspace']
for key in ['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD']:args+=['-e',key]
def php(*tail):return subprocess.check_output(args+[image,'php',*tail],cwd=ROOT)
def state():return json.loads(php('tests/admin_20i_browser_probe.php','snapshot'))
checks=[];diagnostics=[];external=[]
def check(name,ok):
 checks.append({'name':name,'passed':bool(ok)})
 if not ok:raise AssertionError(name)
for fixture in ['provider-package-types','hosting-billing']:
 dest=WEB/fixture
 if dest.exists():raise SystemExit('Refuse to overwrite an installed fixture')
 shutil.copytree(ROOT/'site',dest)
 (dest/'config.php').write_bytes(php('tests/admin_20i_browser_probe.php','config',fixture))
 (dest/'.installed.php').write_text('<?php exit; ?>synthetic installation guard')
server='cwp-installed-admin-browser'
subprocess.run(args[:2]+['-d']+args[2:]+['--name',server,image,'php','-S','127.0.0.1:8091','-t','test-runtime/integration-web'],check=True,capture_output=True)
base='http://127.0.0.1:8091/provider-package-types';catalog=base+'/admin/index.php?view=catalog&import_source=package.types'
try:
 for _ in range(60):
  try:urllib.request.urlopen(base+'/admin/index.php?view=login',timeout=2).read();break
  except OSError:time.sleep(.5)
 else:raise RuntimeError('Installed loopback fixture did not become ready')
 with sync_playwright() as p:
  browser=p.chromium.launch(headless=True)
  context=browser.new_context(viewport={'width':1440,'height':1000})
  def restrict(route):
   u=urlsplit(route.request.url)
   if u.scheme in ('data','about'):route.continue_();return
   if u.hostname!='127.0.0.1' or u.port!=8091:
    external.append(u.hostname);route.abort();return
   route.continue_()
  context.route('**/*',restrict)
  page=context.new_page();page.on('pageerror',lambda e:diagnostics.append(str(e)))
  response=page.goto(base+'/admin/index.php?view=login');check('Actual installed login responds',response.status==200)
  form=page.locator('form').filter(has=page.locator('input[name="action"][value="login"]'))
  form.locator('[name="email"]').fill('types1@example.invalid');form.locator('[name="password"]').fill('Synthetic package types owner')
  with page.expect_navigation():form.locator('button[type="submit"],button:not([type])').first.click()
  check('Synthetic owner authenticates using real login',page.locator('a[href*="view=catalog"]').count()>0 and 'view=login' not in page.url)
  response=page.goto(catalog);check('Actual catalog reads saved SQL plan types',response.status==200 and page.locator('#catalog-products').count()==1)
  check('No repeat-password fields in installed catalog',page.locator('input[name="owner_password"],input[name="current_password"]').count()==0)
  form=page.locator('form').filter(has=page.locator('input[name="action"][value="catalog_import_create"]')).filter(has=page.locator('input[name="type_ref"][value="7002"]'))
  check('Plan without installed account is selectable',form.count()==1)
  fields=form.evaluate('(f)=>Object.fromEntries(new FormData(f))');fields.update(sku='CWP-BROWSER-7002',term='year',confirm='1')
  before=state();bad=dict(fields,csrf='invalid')
  reply=context.request.post(catalog,form=bad)
  check('CSRF refusal preserves real catalog rows',reply.status in (200,400,403) and state()==before)
  page.goto(catalog)
  form=page.locator('form').filter(has=page.locator('input[name="action"][value="catalog_import_create"]')).filter(has=page.locator('input[name="type_ref"][value="7002"]'))
  form.locator('[name="sku"]').fill('CWP-BROWSER-7002');form.locator('[name="term"]').select_option('year');form.locator('[name="confirm"]').check()
  fields=form.evaluate('(f)=>Object.fromEntries(new FormData(f))')
  with page.expect_navigation():form.get_by_role('button',name='Create draft',exact=True).click()
  saved=state();check('Real form saves exactly one hosting draft without repeat password',saved['mapping_count']==1 and saved['product']['sku']=='CWP-BROWSER-7002' and saved['product']['status']=='draft' and saved['product']['family']=='hosting' and saved['product']['amount_cents'] is None)
  reply=context.request.post(catalog,form=fields)
  check('Import replay preserves product identity and edited source rows',reply.status==200 and state()==saved)
  page.goto(catalog+'&product_search=CWP-BROWSER-7002')
  check('Saved draft appears in discoverable filtered product list','CWP-BROWSER-7002' in page.locator('#catalog-products').inner_text())
  page.screenshot(path=str(OUT/'installed-catalog.png'),full_page=True)
  for section in ['overview','connection','services','cloud','domains','website','advanced']:
   response=page.goto(base+'/admin/index.php?view=provider-settings&section='+section)
   check('Authenticated 20i section '+section+' loads',response.status==200 and 'view=login' not in page.url)
   check('No repeated password on 20i '+section,page.locator('input[name="owner_password"],input[name="current_password"]').count()==0)
  page.set_viewport_size({'width':390,'height':844});page.goto(base+'/admin/index.php?view=provider-settings')
  check('Installed 20i mobile layout contains horizontal overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth+2'))
  page.screenshot(path=str(OUT/'installed-20i-mobile.png'),full_page=True)
  php('tests/admin_20i_browser_probe.php','revoke');page.goto(catalog)
  check('Revoked owner is rejected at installed HTTP boundary','view=login' in page.url and page.locator('[name="password"]').count()==1)
  page.set_viewport_size({'width':1440,'height':1000})
  public='http://127.0.0.1:8091/hosting-billing/index.php?page=hosting'
  for period,label in [('monthly','per month'),('annually','per year')]:
   response=page.goto(public+'&billing='+period)
   check('Published '+period+' offers appear on actual Hosting page',response.status==200 and page.locator('.hosting-comparison-plan').count()>0 and label in page.locator('#hosting-plans').inner_text())
   check('Public hosting excludes internal provider secrets','PRIVATE_BILLING' not in page.content() and 'SYNTHETIC_HOSTING_BILLING_KEY' not in page.content())
  page.screenshot(path=str(OUT/'installed-hosting-annual.png'),full_page=True)
  check('No unexpected external browser requests',external==[]);check('No uncaught browser JavaScript errors',diagnostics==[])
  context.close();browser.close()
except Exception as e:
 checks.append({'name':type(e).__name__+': '+str(e),'passed':False})
finally:
 logs=subprocess.run(['docker','logs',server],capture_output=True,text=True);(OUT/'php-server.log').write_text(logs.stdout+logs.stderr)
 subprocess.run(['docker','rm','-f',server],capture_output=True)
 (OUT/'results.json').write_text(json.dumps({'passed':bool(checks) and all(x['passed'] for x in checks),'scope':'Installed synthetic SQL fixtures, real HTTP and Chromium; no production or provider calls','source_sha':os.environ.get('CWP_SOURCE_SHA'),'checks':checks,'javascript_errors':diagnostics,'external_requests':external},indent=2)+'\n')
print(json.dumps({'passed':all(x['passed'] for x in checks),'checks':len(checks)}))
raise SystemExit(0 if checks and all(x['passed'] for x in checks) else 1)
