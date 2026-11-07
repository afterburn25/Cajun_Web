"""Installed licensing workflow on disposable SQL and loopback HTTP, no payment/provider calls."""
from pathlib import Path
from urllib.parse import urlsplit
from datetime import datetime, timedelta, timezone
import hashlib, json, os, shutil, subprocess, time, urllib.request, zipfile
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];OUT=ROOT/'qa/licensing-workspace';OUT.mkdir(parents=True,exist_ok=True)
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Disposable loopback SQL only')
image='cwp-licensing-workspace';args=['docker','run','--rm','--network','host','--user',f'{os.getuid()}:{os.getgid()}','-v',str(ROOT)+':/workspace','-w','/workspace']
for k in ['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD']:args+=['-e',k]
def probe(mode):return subprocess.check_output(args+[image,'php','tests/licensing_workspace_probe.php',mode],cwd=ROOT,timeout=90)
def state():return json.loads(probe('snapshot'))
checks=[];external=[];errors=[]
def check(name,ok):
 checks.append({'name':name,'passed':bool(ok)})
 if not ok:raise AssertionError(name)
probe('seed');web=ROOT/'test-runtime/licensing-web';dest=web/'studio'
if dest.exists():raise SystemExit('Refuse stale installed fixture')
shutil.copytree(ROOT/'site',dest);(dest/'config.php').write_bytes(probe('config'));(dest/'.installed.php').write_text('<?php exit; ?>synthetic installation')
(dest/'install.php').unlink(missing_ok=True)
archive=ROOT/'test-runtime/licensing-private/release.zip'
with zipfile.ZipFile(archive,'w') as z:z.writestr('README.txt','Synthetic software download; no executable payload.')
archive_sha=hashlib.sha256(archive.read_bytes()).hexdigest()
server='cwp-licensing-workspace-http';base='http://127.0.0.1:8094/studio';admin=base+'/admin/index.php';licenses=admin+'?view=studio-licenses';password='Synthetic licensing passphrase';sku='SOFTWARE-UI-TEST'
subprocess.run(args[:2]+['-d']+args[2:]+['--name',server,image,'php','-S','127.0.0.1:8094','-t','test-runtime/licensing-web'],check=True,capture_output=True)
try:
 for _ in range(40):
  try:urllib.request.urlopen(admin+'?view=login',timeout=2).read();break
  except OSError:time.sleep(.5)
 else:raise RuntimeError('Fixture unavailable')
 with sync_playwright() as p:
  browser=p.chromium.launch(headless=True)
  def context():
   ctx=browser.new_context(viewport={'width':1440,'height':1000})
   def bound(route):
    u=urlsplit(route.request.url)
    if u.hostname!='127.0.0.1' or u.port!=8094:external.append(u.hostname);route.abort()
    else:route.continue_()
   ctx.route('**/*',bound);return ctx
  def form(page,action):return page.locator('form').filter(has=page.locator('[name="action"][value="'+action+'"]'))
  def submit(page,action,values=None,selector=None):
   f=selector if selector is not None else form(page,action)
   for name,value in (values or {}).items():
    el=f.locator('[name="'+name+'"]');tag=el.evaluate('(e)=>e.tagName')
    if tag=='SELECT':el.select_option(str(value))
    elif el.get_attribute('type')=='checkbox':el.set_checked(bool(value))
    elif el.get_attribute('type')=='hidden':el.evaluate('(e,v)=>e.value=v',str(value))
    else:el.fill(str(value))
   with page.expect_navigation():f.locator('button[type="submit"],button:not([type])').first.click()
   check('Form '+action+' returns without application error',page.locator('.notice.error').count()==0 and 'We could not complete' not in page.content())
  def login(page,url,email):
   page.goto(url);submit(page,'login',{'email':email,'password':password})
  owner=context();page=owner.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  login(page,admin+'?view=login','owner@licensing.invalid')
  page.locator('[data-nav-group="catalog"] > summary').click()
  page.locator('.admin-sidebar').get_by_role('link',name='Licenses & software',exact=True).click()
  check('Visible admin navigation opens licensing workspace',page.locator('#licensing-overview').count()==1 and page.get_by_role('heading',name='Licenses & software',exact=True).count()==1)
  before=state();response=page.goto(licenses)
  check('Licensing GET is private and makes no licenses','no-store' in response.headers.get('cache-control','') and state()==before)
  check('Unprepared setup is explicit and has no repeat password',page.locator('#licensing-setup').count()==1 and page.locator('input[type="password"]').count()==0)
  submit(page,'studio_license_setup')
  check('Missing catalog points to working prerequisite rather than failed software setup',page.get_by_role('link',name='Prepare service catalog',exact=True).count()==1 and form(page,'software_commerce_setup').count()==0)
  page.get_by_role('link',name='Prepare service catalog',exact=True).click();submit(page,'catalog_migrate',{'confirm':True})
  submit(page,'catalog_save',{'sku':sku,'title':'Synthetic software product','family':'software','status':'published','term':'once','amount':'29.99','summary':'Synthetic licensing acceptance product','features':'One fixture feature','terms':'Synthetic test terms'})
  page.goto(licenses);submit(page,'software_commerce_setup')
  issue=form(page,'software_license_review')
  check('Actual owner preparation enables guided customer licensing without repeat password',issue.count()==1 and issue.locator('input[type="password"]').count()==0 and form(page,'software_license_issue').count()==0)
  check('Software readiness is visible after actual setup',page.locator('[data-license-readiness="software"]').inner_text()=='Prepared')
  fields=issue.evaluate('(f)=>Object.fromEntries(new FormData(f))');before=state()
  owner.request.post(licenses,form={**fields,'csrf':'invalid','customer_id':'1','product_id':sku,'license_type':'standard','domain':'denied.example.invalid'})
  check('Invalid license-review CSRF preserves stored licenses',state()==before)
  for target in [licenses+'&support=invalid',admin+'?view=dashboard&support=invalid']:
   page.goto(licenses)
   issue=form(page,'software_license_review')
   valid=issue.evaluate('(f)=>Object.fromEntries(new FormData(f))');before=state()
   refused=owner.request.post(target,form={**valid,'customer_id':'1','product_id':sku,'license_type':'standard','domain':'blocked.example.invalid'})
   check('Support-scoped license POST is refused before mutation through '+urlsplit(target).query,
         refused.status in (200,403) and state()==before and ('Leave client support' in refused.text()))
  page.goto(licenses);before=state()
  submit(page,'software_license_review',{'customer_id':'1','product_id':sku,'license_type':'standard','domain':'unreleased.example.invalid'})
  check('Review names the missing approved release and exact next action without issuing',
        state()==before and 'No customer-visible release yet' in page.content()
        and 'License issuance is unavailable' in page.content()
        and page.get_by_role('link',name='Add another version',exact=True).count()==1
        and form(page,'software_license_issue').count()==0)
  submit(page,'software_license_review_clear')
  submit(page,'software_asset_publish',{'sku':sku,'version':'1.0.0','title':'Synthetic release download','private_path':'/workspace/test-runtime/licensing-private/release.zip','customer_visible':True})
  asset=state()['assets'][0];check('Real publication records verified immutable ZIP checksum',asset['sha256']==archive_sha and asset['is_published']==1)
  page.goto(licenses);submit(page,'software_license_review',{'customer_id':'1','product_id':sku,'license_type':'standard','domain':'forged-token.example.invalid'})
  reviewed=form(page,'software_license_issue').evaluate('(f)=>Object.fromEntries(new FormData(f))')
  refused=owner.request.post(licenses,form={**reviewed,'confirm':'1','review_token':'0'*64})
  check('Forged review token cannot issue and invalidates the stale review',
        state()['license_count']==0 and 'license review expired' in refused.text())
  page.goto(licenses);check('Invalidated review is no longer confirmable',form(page,'software_license_issue').count()==0)
  page.goto(licenses);submit(page,'software_license_review',{'customer_id':'1','product_id':sku,'license_type':'standard','domain':'stale-policy.example.invalid'})
  stale_issue=form(page,'software_license_issue').evaluate('(f)=>Object.fromEntries(new FormData(f))')
  policy_form=form(page,'software_product_policy').filter(has=page.locator('[name="product_id"]')).first
  policy_values=policy_form.evaluate('(f)=>Object.fromEntries(new FormData(f))');policy_values.update({'max_installations':'3','current_password':password})
  changed=owner.request.post(licenses,form=policy_values);check('Owner can revise current product policy while a review is open',changed.status==200)
  stale=owner.request.post(licenses,form={**stale_issue,'confirm':'1'})
  check('Changed policy invalidates the stale review before license mutation',
        state()['license_count']==0 and 'policy or approved release changed' in stale.text())
  for kind in ['standard','trial','temporary']:
   page.goto(licenses);issue=form(page,'software_license_review')
   values={'customer_id':'1','product_id':sku,'license_type':kind,'domain':kind+'.example.invalid'}
   if kind in ['trial','temporary']:values['expires_at']=(datetime.now(timezone.utc)+timedelta(days=7)).strftime('%Y-%m-%dT%H:%M')
   before=state();submit(page,'software_license_review',values,issue)
   check('Guided '+kind+' review makes no license before confirmation',state()==before)
   review=form(page,'software_license_issue')
   check('Guided '+kind+' review shows exact customer product domain policy and approved release',
         review.count()==1 and 'Licensing Client' in page.content() and sku in page.content()
         and kind+'.example.invalid' in page.content() and 'v1.0.0' in page.content()
         and '3 concurrent installations' in page.content())
   submit(page,'software_license_issue',{'confirm':True},review)
  page.goto(licenses);master_details=page.locator('details').filter(has=page.get_by_text('Internal master license (owner-only)',exact=True));master_details.locator('summary').click()
  master=form(page,'studio_license_create').filter(has=page.locator('[name="license_type"][value="master"]'))
  check('Internal master issuance is separate from the customer-license flow',master.count()==1 and issue.count()==1)
  submit(page,'studio_license_create',{'customer_id':'1','product_id':sku,'licensee_name':'Internal QA','master_purpose':'Internal synthetic acceptance only'},master)
  rows={x['license_type']:x for x in state()['licenses']}
  check('All four license types are persisted by real admin forms',set(rows)=={'standard','trial','temporary','master'} and state()['license_count']==4)
  page.goto(licenses);check('License register displays all four masked software key identities',all(x['license_key'][:8] in page.content() and x['license_key'] not in page.content() for x in rows.values()))
  register=page.locator('#license-register + .workspace-grid')
  register_text=register.inner_text()
  check('License register shows readable linked product, customer, domain, type, status and expiry',
        register.get_by_role('link',name='Synthetic software product',exact=True).count()==4
        and 'Licensing Client' in register_text and 'standard.example.invalid' in register_text
        and 'Standard' in register_text and 'Active' in register_text and 'Never' in register_text)
  page.screenshot(path=str(OUT/'licensing-admin.png'),full_page=True)
  def validate(kind,domain,product=sku):
   r=owner.request.post(base+'/api/validate.php',data=json.dumps({'license_key':rows[kind]['license_key'],'domain':domain,'product_id':product}),headers={'Content-Type':'application/json'})
   check('Validation HTTP response for '+kind,r.status==200);return r.json()['authorized']
  check('Standard license authorizes its registered product and domain',validate('standard','standard.example.invalid'))
  check('Wrong installation domain is denied',not validate('standard','foreign.example.invalid'))
  check('Wrong product is denied',not validate('standard','standard.example.invalid','SOFTWARE-OTHER'))
  check('Internal master authorizes another domain for its own product',validate('master','qa.example.invalid'))
  check('Temporary license authorizes before expiration',validate('temporary','temporary.example.invalid'))
  probe('expire-trial');check('Expired trial is denied by actual validation API',not validate('trial','trial.example.invalid'))
  page.goto(licenses)
  lifecycle=form(page,'software_license_lifecycle').filter(has=page.locator('[name="id"][value="'+str(rows['standard']['id'])+'"]')).filter(has=page.locator('[name="transition"][value="suspend"]'))
  submit(page,'software_license_lifecycle',{'reason':'Synthetic temporary suspension'},lifecycle)
  check('Suspended software key is denied',not validate('standard','standard.example.invalid'))
  lifecycle=form(page,'software_license_lifecycle').filter(has=page.locator('[name="id"][value="'+str(rows['standard']['id'])+'"]')).filter(has=page.locator('[name="transition"][value="resume"]'))
  submit(page,'software_license_lifecycle',{'reason':'Synthetic access restoration'},lifecycle)
  check('Resume restores the same software key',validate('standard','standard.example.invalid') and state()['licenses'][0]['license_key']==rows['standard']['license_key'])
  customer=context();cp=customer.new_page();login(cp,base+'/portal/index.php?software=1','client@licensing.invalid')
  check('My Software route renders actual licenses, not project fallback',cp.get_by_role('heading',name='Software & Licenses',exact=True).count()==1 and rows['standard']['license_key'] in cp.content())
  check('Customer view excludes internal master keys',rows['master']['license_key'] not in cp.content())
  download=base+'/api/software-download.php?id='+str(asset['id'])+'&license='+str(rows['standard']['id'])
  content=customer.request.get(download);check('Entitled customer downloads exact private ZIP',content.status==200 and hashlib.sha256(content.body()).hexdigest()==archive_sha)
  cp.screenshot(path=str(OUT/'software-client.png'),full_page=True)
  cp.goto(base+'/portal/index.php?finance=1');check('Financing link renders the real financing page',cp.get_by_role('heading',name='Financing agreement',exact=True).count()==1)
  other=context();op=other.new_page();login(op,base+'/portal/index.php?software=1','other@licensing.invalid')
  check('Unrelated customer sees no foreign key',all(x['license_key'] not in op.content() for x in rows.values()))
  check('Unrelated customer cannot download another license',other.request.get(download).status==404)
  check('Malformed software route is refused',other.request.get(base+'/portal/index.php?software[]=1').status==400)
  check('Mixed software and finance routes are refused',other.request.get(base+'/portal/index.php?software=1&finance=1').status==400)
  staff=context();sp=staff.new_page();login(sp,admin+'?view=login','staff@licensing.invalid');sp.goto(licenses)
  check('Nonowner cannot view licensing keys',sp.locator('#licensing-overview').count()==0 and rows['standard']['license_key'] not in sp.content())
  check('Support query cannot disclose admin keys',owner.request.get(licenses+'&support=invalid').status==403)
  check('Customer review route cannot act as software customer',owner.request.get(base+'/portal/index.php?software=1&support=invalid').status==403)
  page.goto(licenses);page.set_viewport_size({'width':390,'height':844});page.wait_for_timeout(350)
  check('Licensing workspace fits mobile width',page.evaluate('document.documentElement.scrollWidth<=innerWidth+2'))
  page.screenshot(path=str(OUT/'licensing-mobile.png'),full_page=True,animations='disabled')
  probe('revoke-owner');page.goto(licenses);check('Revoked owner returns to sign-in','view=login' in page.url and page.locator('#licensing-overview').count()==0)
  check('License setup did not activate card payments',state()['software_mode']=='disabled')
  check('No third-party browser requests or JS errors',external==[] and errors==[])
  browser.close()
except Exception as e:
 checks.append({'name':type(e).__name__+': '+str(e),'passed':False})
 for label in ['page','cp']:
  try:
   candidate=locals().get(label)
   if candidate is not None and not candidate.is_closed():candidate.screenshot(path=str(OUT/('failure-'+label+'.png')),full_page=True,animations='disabled')
  except Exception:pass
finally:
 logs=subprocess.run(['docker','logs',server],capture_output=True,text=True);text=logs.stdout+logs.stderr;(OUT/'server.log').write_text(text)
 subprocess.run(['docker','rm','-f',server],capture_output=True)
 checks.append({'name':'No PHP or handled application errors','passed':not any(s in text for s in ['PHP Fatal error','PHP Warning','PHP Notice','PHP Deprecated','CWP ['])})
 passed=bool(checks) and all(c['passed'] for c in checks)
 (OUT/'results.json').write_text(json.dumps({'passed':passed,'source_sha':os.environ.get('CWP_SOURCE_SHA'),'scope':'Actual synthetic SQL, admin/customer forms, license validation and private downloads. No merchant transaction, email, provider request or production data.','checks':checks,'external_requests':external,'javascript_errors':errors},indent=2)+'\n')
print(json.dumps({'passed':passed,'checks':len(checks)}));raise SystemExit(0 if passed else 1)
