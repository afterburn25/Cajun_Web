"""Native HTTP/Chromium payment preparation; no external payment/network fixtures represented as real provider acceptance."""
import json,shutil,subprocess,zipfile
import requests
from urllib.parse import parse_qs,urlparse
from playwright.sync_api import expect
from release_cases import db,chosen_form
from staff_cases import OWNER,STAFF

def upgrade():
    dest=WEB/'payment-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.4.0',dest)
    seed=subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-payment-upgrade'}),text=True,capture_output=True)
    (QA/'payment-upgrade-seed.log').write_text(seed.stdout+seed.stderr)
    seed.check_returncode()
    shutil.copyfile(ROOT/'test-runtime/payment-upgrade-config.php',dest/'config.php');(dest/'.installed.php').write_text('<?php exit; ?>original guard');(dest/'unrelated.txt').write_text('Retain unrelated file')
    preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',ROOT/'test-runtime/payment-upgrade-private/media/retained.php']}
    tables=['staff_profiles','staff_invitations','support_clients','support_events','studio_clients','studio_jobs','studio_invoices','catalog_products','catalog_revisions','catalog_invoice_snapshots','leads','projects']
    before={t:db('SELECT * FROM paymentup_'+t) for t in tables};settings=db('SELECT * FROM paymentup_settings');users=db('SELECT * FROM paymentup_users')
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update
    apply_file_update(globals(),dest)
    check('Actual 1.4.0 update preserves configuration guards and private files',all(p.read_bytes()==v for p,v in preserved.items()))
    up=requests.Session();url=BASE+'/payment-upgrade/admin/index.php';submit(up,url+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    r=submit(up,url+'?view=payments','payments_migrate',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'})
    check('Actual 1.4.0 payment preparation migration completes','Payment preparation enabled' in r.text)
    for t in tables:check('Actual 1.4.0 retains '+t,db('SELECT * FROM paymentup_'+t)==before[t])
    now={v['name']:v['value'] for v in db('SELECT * FROM paymentup_settings')};check('Actual 1.4.0 retains all original settings',all(now[s['name']]==s['value'] for s in settings))
    check('Actual 1.4.0 retains owner passwords and account identities',all(all(a[k]==b[k] for k in b if k!='last_login') for a,b in zip(db('SELECT * FROM paymentup_users'),users)))
    check('Actual 1.4.0 upgrade creates no gateway or payable requests',not db('SELECT * FROM paymentup_payment_gateways') and not db('SELECT * FROM paymentup_payment_requests'))
    check('Actual 1.4.0 installed guard remains',requests.get(BASE+'/payment-upgrade/install.php').status_code==404)

def run_http(env):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php','admin','bob']})
    global ADMIN,PAY,SETTINGS,INVOICE,CREDS
    ADMIN=BASE+'/existing/admin/index.php';PAY=ADMIN+'?view=payments';SETTINGS=ADMIN+'?view=payment-settings'
    CREDS={'api_login_id':'HttpFixtureLogin','transaction_key':'HttpFixtureKey12','signature_key':'b'*128,'public_client_key':'HttpFixturePublicKey'}
    db('DELETE FROM ci_rate_limits');upgrade()
    check('Fresh installer initializes payment schema without credentials',db("SELECT value FROM fresh_settings WHERE name='payments_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_payment_gateways'))
    r=submit(admin,PAY,'payments_migrate',{'owner_password':'wrong','confirm':'1'});check('Payment setup HTTP reauthentication','current password is not correct' in r.text)
    r=submit(admin,PAY,'payments_migrate',{'owner_password':OWNER,'confirm':'1','csrf':'bad'});check('Payment setup HTTP CSRF denial','session expired' in r.text.lower())
    r=submit(admin,PAY,'payments_migrate',{'owner_password':OWNER,'confirm':'1'});check('Owner enables payment preparation','Payment preparation enabled' in r.text)
    r=submit(admin,SETTINGS,'payments_save',dict(CREDS,owner_password=OWNER,csrf='bad'));check('Gateway save rejects CSRF without persistence',not db('SELECT * FROM ci_payment_gateways') and 'HttpFixture' not in r.text)
    r=submit(admin,SETTINGS,'payments_save',dict(CREDS,owner_password='wrong'));check('Gateway save reauthenticates and never echoes secrets','current password is not correct' in r.text and 'HttpFixture' not in r.text and not db('SELECT * FROM ci_payment_gateways'))
    r=submit(admin,SETTINGS,'payments_save',dict(CREDS,owner_password=OWNER));check('Gateway values saved but remain blank in HTTP response','protected and saved' in r.text and 'HttpFixture' not in r.text and all(soup(r).select_one('[name='+k+']').get('value','')=='' for k in CREDS))
    row=db('SELECT * FROM ci_payment_gateways')[0];check('Gateway native record has encrypted values only','HttpFixture' not in row['credentials'] and row['validation']=='not_verified')
    production=SETTINGS+'&environment=production';r=submit(admin,production,'payments_save',dict(CREDS,api_login_id='ProductionHttpFixture',owner_password=OWNER));check('Sandbox production saved independently',len(db('SELECT * FROM ci_payment_gateways'))==2 and 'ProductionHttpFixture' not in r.text)
    r=admin.post(production,data={'action':'payments_verify','csrf':token(r),'environment':'production','revision':'1','owner_password':OWNER});check('Forged production verification fails closed','Production connection and charging are disabled' in r.text)
    before=db('SELECT * FROM ci_payment_gateways');r=submit(admin,SETTINGS,'payments_save',{'revision':'0','owner_password':OWNER});check('Stale credential edit cannot overwrite saved settings','settings changed' in r.text and db('SELECT * FROM ci_payment_gateways')==before)
    r=submit(admin,SETTINGS,'payments_save',{'owner_password':OWNER});check('Empty update keeps protected keys and increments revision','protected and saved' in r.text and db("SELECT revision FROM ci_payment_gateways WHERE environment='sandbox'")[0]['revision']==2)
    r=submit(admin,SETTINGS,'payments_clear',{'owner_password':OWNER,'confirm':'0'});check('Credential clear requires explicit confirmation','Confirm removal' in r.text)
    r=submit(admin,SETTINGS,'payments_clear',{'owner_password':OWNER,'confirm':'1'});check('Credential clear preserves other environment',db("SELECT credentials FROM ci_payment_gateways WHERE environment='sandbox'")==[{'credentials':''}] and db("SELECT credentials FROM ci_payment_gateways WHERE environment='production'")[0]['credentials']!='')
    INVOICE=db("SELECT * FROM ci_studio_invoices WHERE job_id=1 AND status='issued' ORDER BY id DESC LIMIT 1")[0];target=PAY+'&invoice_id='+str(INVOICE['id']);r=admin.get(target);review=form_data(form_for(r,'payments_prepare'));review.update(confirm='1',purpose='milestone',amount_cents='1',currency='EUR')
    r=admin.post(target,data=dict(review,csrf='bad'));check('Request creation HTTP CSRF denial',not db('SELECT * FROM ci_payment_requests'))
    r=admin.post(target,data=review);request=db('SELECT * FROM ci_payment_requests')[0];check('Request uses server invoice total and currency despite forged fields',request['amount_cents']==INVOICE['amount_cents'] and request['currency']=='USD' and 'No charge was made' in r.text)
    r=admin.post(target,data=review);check('HTTP retry does not duplicate request',len(db('SELECT * FROM ci_payment_requests'))==1)
    r=admin.post(target,data=dict(review,purpose='final'));check('HTTP changed duplicate request denied','already submitted with different details' in r.text)
    alice=requests.Session();submit(alice,BASE+'/existing/portal/index.php','login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    r=alice.get(BASE+'/existing/portal/index.php?job=1');check('Client sees own prepared request with truthful unpaid notice','Agreed milestone invoice' in r.text and 'Online payment is not available yet' in r.text and 'HttpFixture' not in r.text and 'ProductionHttpFixture' not in r.text)
    check('Other client cannot view payment request',bob.get(BASE+'/existing/portal/index.php?job=1').status_code==404 and 'Agreed milestone invoice' not in bob.get(BASE+'/existing/portal/index.php?job=2').text)
    r=alice.post(PAY,data={'action':'payments_prepare','csrf':token(r),**{k:v for k,v in review.items() if k not in ['action','csrf']}},allow_redirects=False);check('Client cannot prepare administrator request',r.status_code==303 and 'login' in r.headers.get('Location',''))
    earlier=form_data(form_for(admin.get(target),'payments_prepare'));db('UPDATE ci_studio_invoices SET amount_cents=amount_cents+1 WHERE id=?',[INVOICE['id']]);r=admin.post(target,data=dict(earlier,confirm='1'));check('Stale invoice amount rejected after review','invoice changed' in r.text.lower() and len(db('SELECT * FROM ci_payment_requests'))==1);db('UPDATE ci_studio_invoices SET amount_cents=? WHERE id=?',[INVOICE['amount_cents'],INVOICE['id']])
    r=submit(admin,target,'payments_prepare',{'confirm':'1','purpose':'final','days':'3'});check('New request supersedes old instead of enabling two',db('SELECT status FROM ci_payment_requests ORDER BY id')==[{'status':'superseded'},{'status':'prepared'}])
    r=submit(admin,PAY,'payments_revoke',{'confirm':'1'});check('Revocation preserves invoice financial state',db('SELECT status FROM ci_payment_requests ORDER BY id DESC LIMIT 1')==[{'status':'revoked'}] and db('SELECT * FROM ci_studio_invoices WHERE id=?',[INVOICE['id']])[0]==INVOICE)
    r=submit(admin,target,'payments_prepare',{'confirm':'1'});latest=db('SELECT id FROM ci_payment_requests ORDER BY id DESC LIMIT 1')[0]['id'];db('UPDATE ci_payment_requests SET expires_at=1 WHERE id=?',[latest]);check('Expired request displayed honestly','expired' in admin.get(PAY).text and 'expired' in alice.get(BASE+'/existing/portal/index.php?job=1').text)
    # Existing clients permission must not silently receive newly introduced finance permission.
    email='request-staff@example.invalid';submit(admin,ADMIN+'?view=staff','staff_save',{'name':'Request staff','email':email,'role':'administrator','permissions[]':['clients.manage'],'enabled':'1','owner_password':OWNER})
    uid=db('SELECT id FROM ci_users WHERE email=?',[email])[0]['id'];r=chosen_form(admin,ADMIN+'?view=staff','staff_invite','id',uid,{'owner_password':OWNER});link=soup(r).select_one('[data-staff-invite]')['href'];code=parse_qs(urlparse(link).fragment)['token'][0];staff=requests.Session();submit(staff,link,'staff_activate',{'token':code,'password':STAFF,'confirm_password':STAFF});submit(staff,ADMIN+'?view=login','login',{'email':email,'password':STAFF})
    check('Client-only staff cannot read payment requests',staff.get(PAY).status_code==403);check('Client-only staff cannot read gateway settings',staff.get(SETTINGS).status_code==403)
    r=chosen_form(admin,ADMIN+'?view=staff','staff_save','id',uid,{'permissions[]':['clients.manage','payments.manage'],'owner_password':OWNER});submit(staff,ADMIN+'?view=login','login',{'email':email,'password':STAFF});check('Explicit request permission admits staff without gateway access',staff.get(PAY).status_code==200 and staff.get(SETTINGS).status_code==403)
    r=submit(staff,target,'payments_prepare',{'confirm':'1'});check('Permitted staff can prepare requests','Payment request prepared' in r.text)
    # Strict audit failure must roll back both replacement and credential mutation.
    db("CREATE TRIGGER ci_payment_audit_failure BEFORE INSERT ON ci_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic payment audit failure'")
    before=db('SELECT * FROM ci_payment_requests');r=submit(admin,target,'payments_prepare',{'confirm':'1'});check('HTTP audit failure rolls back request replacement',db('SELECT * FROM ci_payment_requests')==before and 'could not be saved' in r.text)
    before=db('SELECT * FROM ci_payment_gateways');r=submit(admin,production,'payments_clear',{'confirm':'1','owner_password':OWNER});check('HTTP audit failure rolls back protected credential deletion',db('SELECT * FROM ci_payment_gateways')==before and 'ProductionHttpFixture' not in r.text)
    db('DROP TRIGGER ci_payment_audit_failure')
    check('Public pages contain no gateway keys or payment request data',all('HttpFixture' not in requests.get(BASE+'/existing/'+p).text and 'Agreed milestone invoice' not in requests.get(BASE+'/existing/'+p).text for p in ['', 'index.php?page=packages']))
    check('Payment preparations never alter invoice status',db('SELECT * FROM ci_studio_invoices WHERE id=?',[INVOICE['id']])[0]==INVOICE)

def run_browser(env,browser):
    run_http(env)
    ctx=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=ctx.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
    p.goto(ADMIN+'?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill(OWNER);p.locator('form button').click();p.wait_for_url('**/admin/index.php?view=dashboard');p.goto(SETTINGS)
    f=p.locator('form').filter(has=p.locator('[name=action][value=payments_save]'))
    for k,v in CREDS.items():f.locator('[name='+k+']').fill(v)
    f.locator('[name=owner_password]').fill(OWNER);f.get_by_role('button').click();expect(p.get_by_text('Credentials protected and saved.',exact=False)).to_be_visible()
    check('Browser credentials clear after successful save',all(p.locator('[name='+k+']').input_value()=='' for k in CREDS) and 'HttpFixture' not in p.content())
    p.screenshot(path=str(QA/'Payment-Settings-Desktop.png'),full_page=True);p.set_viewport_size({'width':320,'height':844});p.screenshot(path=str(QA/'Payment-Settings-Narrow.png'),full_page=True);check('Payment settings fit narrow screen',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    p.set_viewport_size({'width':1440,'height':1000});p.goto(PAY+'&invoice_id='+str(INVOICE['id']));p.locator('.payment-review [name=purpose]').select_option('deposit');p.locator('.payment-review [name=confirm]').check();p.screenshot(path=str(QA/'Payment-Request-Review.png'),full_page=True);p.get_by_role('button',name='Prepare request',exact=True).click();expect(p.get_by_text('Payment request prepared.',exact=False)).to_be_visible()
    check('Browser request preparation retains invoice amount',db('SELECT amount_cents,purpose FROM ci_payment_requests ORDER BY id DESC LIMIT 1')==[{'amount_cents':INVOICE['amount_cents'],'purpose':'deposit'}]);p.set_viewport_size({'width':320,'height':844});p.screenshot(path=str(QA/'Payment-Requests-Narrow.png'),full_page=True);check('Payment requests fit narrow screen',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    check('Payment browser has no uncaught errors',not errors);(QA/'payment-browser-diagnostics.json').write_text(json.dumps({'page_errors':errors,'provider_calls':'None; connection response parsing tested separately with CLI fixtures'},indent=2));ctx.close()
