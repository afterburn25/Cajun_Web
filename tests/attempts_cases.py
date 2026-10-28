"""Actual HTTP/Chromium and original-release upgrades; tokenization is explicitly simulated."""
import json, shutil, subprocess, zipfile
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF
from payment_upgrade_helpers import prepare_retained_payments

def upgrade():
    dest=WEB/'trial-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.5.0',dest)
    seed=subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-trial-upgrade'}),text=True,capture_output=True)
    (QA/'trial-upgrade-seed.log').write_text(seed.stdout+seed.stderr);seed.check_returncode()
    shutil.copyfile(ROOT/'test-runtime/trial-upgrade-config.php',dest/'config.php');(dest/'.installed.php').write_text('<?php exit; ?>original guard');(dest/'unrelated.txt').write_text('Retain unrelated file')
    preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',ROOT/'test-runtime/trial-upgrade-private/media/retained.php',ROOT/'test-runtime/trial-upgrade-private/payment-master.key']}
    tables=['staff_profiles','staff_invitations','support_clients','support_events','studio_clients','studio_jobs','studio_invoices','catalog_products','catalog_revisions','catalog_invoice_snapshots','payment_gateways','payment_requests','leads','projects']
    before={t:db('SELECT * FROM trialup_'+t) for t in tables};settings=db('SELECT * FROM trialup_settings')
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update
    apply_file_update(globals(),dest)
    check('Original 1.5 update preserves config guard private files and external master key',all(p.read_bytes()==v for p,v in preserved.items()))
    up=requests.Session();url=BASE+'/trial-upgrade/admin/index.php';submit(up,url+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    before,settings=prepare_retained_payments(globals(),up,url,'trialup_',before,settings,preserved)
    r=submit(up,url+'?view=payment-trials','payments_trial_setup',{'owner_password':'wrong','confirm':'1'});check('Trial upgrade refuses wrong owner password','current password' in r.text.lower())
    r=submit(up,url+'?view=payment-trials','payments_trial_setup',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'});check('Original 1.5 upgrade enables additive sandbox history','Sandbox trial history enabled' in r.text)
    check('Original 1.5 gateway ciphertext requests invoices and all retained records preserved',all(db('SELECT * FROM trialup_'+t)==v for t,v in before.items()))
    check('Upgraded trial schema empty and real payment remains disabled',db('SELECT * FROM trialup_payment_attempts')==[] and 'Production and client checkout remain disabled' in r.text)
    r=submit(up,url+'?view=payment-settings','payments_save',{'owner_password':'Synthetic upgrade passphrase 2026'});check('Retained encrypted credentials remain readable for blank retention','Credentials protected and saved' in r.text and 'RetainedSandboxFixture' not in r.text)

def run_browser(env,browser):
    globals().update(env);upgrade();url=BASE+'/existing/admin/index.php?view=payment-trials'
    admin=requests.Session();submit(admin,BASE+'/existing/admin/index.php?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    r=admin.get(url)
    if 'Enable sandbox trials' in r.text:r=submit(admin,url,'payments_trial_setup',{'owner_password':OWNER,'confirm':'1'})
    check('Owner sees sandbox history and truthful real-payment boundary',r.status_code==200 and 'Sandbox attempt history' in r.text and 'Test card data only' in r.text)
    check('Unauthenticated visitor cannot see sandbox trial data','Sandbox attempt history' not in requests.get(url).text)
    staff=requests.Session();submit(staff,BASE+'/existing/admin/index.php?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    check('Non-owner cannot render sandbox trials',staff.get(url).status_code==403)
    request=db("SELECT * FROM ci_payment_requests WHERE status='prepared' ORDER BY id DESC LIMIT 1")[0];target=url+'&request_id='+str(request['id'])
    r=admin.get(target);check('Plain HTTP cannot render card fields or load external payment scripts','Open this page over HTTPS' in r.text and 'id="sandbox-card"' not in r.text and 'https://jstest.authorize.net' not in r.headers.get('Content-Security-Policy',''))
    payload={'csrf':token(r),'action':'payments_trial_submit','request_id':request['id'],'revision':1,'attempt_key':'a'*64,'dataValue':'SyntheticNonceOnly','owner_password':OWNER,'confirm':'1'}
    r=admin.post(target,data=dict(payload,csrf='bad'));check('Trial submit enforces actual HTTP CSRF protection','Your session expired' in r.text and db('SELECT * FROM ci_payment_attempts')==[])
    r=admin.post(target,data=payload);check('Forged HTTP trial cannot bypass HTTPS requirement','Sandbox trials require HTTPS' in r.text and db('SELECT * FROM ci_payment_attempts')==[])
    r=admin.post(target,data=dict(payload,cardNumber='synthetic-raw-card'));check('Unexpected raw-card form fields rejected without reflecting values','Unexpected sandbox form fields' in r.text and 'synthetic-raw-card' not in r.text)
    # The copied test deployment alone trusts loopback proxy headers, enabling the HTTPS-only rendering gate.
    (WEB/'existing/proxy-config.php').write_text("<?php return ['127.0.0.1'];")
    db("UPDATE ci_payment_gateways SET validation='authenticated' WHERE environment='sandbox'")
    ctx=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=ctx.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
    p.goto(BASE+'/existing/admin/index.php?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill(OWNER);p.locator('form button').click();p.wait_for_url('**/admin/index.php?view=dashboard');ctx.set_extra_http_headers({'X-Forwarded-Proto':'https'})
    scripts=[];posted=[]
    fixture="window.Accept={dispatchData:function(data,cb){window.syntheticTokenInput=data;cb({messages:{resultCode:'Ok'},opaqueData:{dataDescriptor:'COMMON.ACCEPT.INAPP.PAYMENT',dataValue:'BrowserSyntheticNonce'}});cb({messages:{resultCode:'Ok'},opaqueData:{dataDescriptor:'COMMON.ACCEPT.INAPP.PAYMENT',dataValue:'BrowserSyntheticNonce'}});}};"
    def accept(route):scripts.append(route.request.url);route.fulfill(status=200,content_type='application/javascript',body=fixture)
    ctx.route('https://jstest.authorize.net/**',accept)
    ctx.route('https://apitest.authorize.net/**',lambda route:route.abort())
    response=p.goto(target);expect(p.locator('#sandbox-trial')).to_be_visible()
    check('Authorized ready trial has narrowly scoped sandbox CSP','https://jstest.authorize.net' in response.headers['content-security-policy'] and 'https://apitest.authorize.net' in response.headers['content-security-policy'])
    check('Card inputs are outside submitted form and have no field names',p.locator('#sandbox-token-form #sandbox-card').count()==0 and p.locator('#sandbox-card-fields input[name]').count()==0)
    check('Trial page exposes public tokenization identifiers only','HttpFixtureLogin' in p.content() and 'HttpFixtureKey12' not in p.content() and 'b'*128 not in p.content())
    p.screenshot(path=str(QA/'Sandbox-Trial-Desktop.png'),full_page=True);p.set_viewport_size({'width':320,'height':844});p.screenshot(path=str(QA/'Sandbox-Trial-Narrow.png'),full_page=True);check('Sandbox trial fits narrow viewport',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    def capture(route):
        if route.request.method=='POST':posted.append(route.request.post_data);route.fulfill(status=200,content_type='text/html',body='<p>Simulated form boundary only</p>')
        else:route.continue_()
    p.route('**/admin/index.php?view=payment-trials*',capture)
    for key,value in [('card','4111111111111111'),('month','12'),('year','2030'),('code','123')]:p.locator('#sandbox-'+key).fill(value)
    p.locator('#sandbox-token-form [name=owner_password]').fill(OWNER);p.locator('#sandbox-token-form [name=confirm]').check();p.locator('#sandbox-send').click();expect(p.get_by_text('Simulated form boundary only')).to_be_visible()
    check('Simulated Accept.js callback submits only token once despite duplicate callback',len(posted)==1 and 'BrowserSyntheticNonce' in posted[0] and '4111111111111111' not in posted[0] and 'cardNumber' not in posted[0] and 'cardCode' not in posted[0])
    check('Sandbox browser ran intercepted provider script without external gateway traffic',len(scripts)==1 and not errors and db('SELECT * FROM ci_payment_attempts')==[])
    p.unroute('**/admin/index.php?view=payment-trials*',capture);response=p.goto(BASE+'/existing/admin/index.php?view=dashboard');check('Ordinary admin page keeps self-only payment CSP','authorize.net' not in response.headers['content-security-policy'])
    (QA/'sandbox-browser-diagnostics.json').write_text(json.dumps({'page_errors':errors,'tokenization':'Synthetic intercepted Accept.js; captured POST boundary only','provider_acceptance':'Not performed; no credentials supplied'},indent=2));ctx.close()
