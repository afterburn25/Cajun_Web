"""Actual endpoint/admin HTTP and Chromium with synthetic signed messages; no provider delivery."""
import hashlib,hmac,json,shutil,subprocess,uuid,zipfile
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF

def upgrade():
    dest=WEB/'notification-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.6.0',dest)
    seed=subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-notification-upgrade'}),text=True,capture_output=True)
    (QA/'notification-upgrade-seed.log').write_text(seed.stdout+seed.stderr,encoding='utf-8');seed.check_returncode()
    shutil.copyfile(ROOT/'test-runtime/notification-upgrade-config.php',dest/'config.php');(dest/'.installed.php').write_text('<?php exit; ?>original guard');(dest/'unrelated.txt').write_text('Retain unrelated file')
    preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',ROOT/'test-runtime/notification-upgrade-private/media/retained.php',ROOT/'test-runtime/notification-upgrade-private/payment-master.key']}
    tables=['staff_profiles','staff_invitations','support_clients','support_events','studio_clients','studio_jobs','studio_invoices','catalog_products','catalog_revisions','catalog_invoice_snapshots','payment_gateways','payment_requests','payment_attempts','payment_attempt_events','leads','projects']
    before={t:db('SELECT * FROM notifyup_'+t) for t in tables};settings=db('SELECT * FROM notifyup_settings')
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update
    apply_file_update(globals(),dest)
    check('Actual 1.6 update preserves config locks private files and master key',all(p.read_bytes()==v for p,v in preserved.items()))
    up=requests.Session();url=BASE+'/notification-upgrade/admin/index.php';submit(up,url+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    r=submit(up,url+'?view=payments','payments_migrate',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'});check('Payment schema upgrade precedes the feature migration','Payment preparation enabled' in r.text)
    r=submit(up,url+'?view=payment-notifications','payments_notification_setup',{'owner_password':'wrong','confirm':'1'});check('Notification upgrade refuses wrong password','current password' in r.text.lower())
    r=submit(up,url+'?view=payment-notifications','payments_notification_setup',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'});check('Actual 1.6 additive notification upgrade succeeds','Sandbox notification inbox enabled' in r.text)
    after={t:db('SELECT * FROM notifyup_'+t) for t in before};check('Actual 1.6 update preserves attempts events ciphertext requests and prior records',all(len(after[t])==len(v) and all(row[k]==v[i][k] for i,row in enumerate(after[t]) for k in v[i]) for t,v in before.items()))
    now={s['name']:s['value'] for s in db('SELECT * FROM notifyup_settings')};check('Actual 1.6 update preserves all original settings',all(now[s['name']]==s['value'] for s in settings if s['name'] not in ('payments_schema_version','payment_notifications_schema_version')) and now['payments_schema_version']=='2' and now['payment_notifications_schema_version']=='1')
    check('Actual 1.6 upgraded inbox empty with receiver disabled',db('SELECT * FROM notifyup_payment_notifications')==[] and 'Disabled' in r.text)
    r=submit(up,url+'?view=payment-settings','payments_save',{'owner_password':'Synthetic upgrade passphrase 2026'});check('Actual 1.6 protected key still decrypts original saved credentials','Credentials protected and saved' in r.text and 'RetainedSandboxFixture' not in r.text)

def run_browser(env,browser):
    globals().update(env);upgrade();url=BASE+'/existing/admin/index.php?view=payment-notifications';endpoint=BASE+'/existing/webhooks/authorize-net/index.php'
    check('Fresh installer creates empty inbox and disabled receiver',db("SELECT value FROM fresh_settings WHERE name='payment_notifications_schema_version'")==[{'value':'1'}] and db('SELECT * FROM fresh_payment_notifications')==[] and db("SELECT * FROM fresh_settings WHERE name='sandbox_notification_receiver'")==[])
    admin=requests.Session();submit(admin,BASE+'/existing/admin/index.php?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    r=admin.get(url)
    if 'Enable sandbox inbox' in r.text:r=submit(admin,url,'payments_notification_setup',{'owner_password':OWNER,'confirm':'1'})
    check('Owner notification page displays truthful sandbox boundary','Notification inbox' in r.text and 'Production and client checkout remain disabled' in r.text)
    check('Anonymous cannot read private notification inbox','Notification inbox' not in requests.get(url).text)
    staff=requests.Session();submit(staff,BASE+'/existing/admin/index.php?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    check('Non-owner cannot read notification inbox',staff.get(url).status_code==403)
    check('Callback GET returns 405 without login redirect',requests.get(endpoint,allow_redirects=False).status_code==405)
    check('Callback refuses plain HTTP without redirect',requests.post(endpoint,json={},allow_redirects=False).status_code==403)
    headers={'X-Forwarded-Proto':'https','Content-Type':'application/json'}
    r=requests.post(endpoint,headers=headers,data='{}');check('Disabled callback reports retryable unavailable without cookies',r.status_code==503 and r.json()=={'received':False} and 'Set-Cookie' not in r.headers)
    revision=db("SELECT revision FROM ci_payment_gateways WHERE environment='sandbox'")[0]['revision']
    webhook='01234567-89ab-cdef-0123-456789abcdef';payload={'owner_password':OWNER,'revision':0,'gateway_revision':revision,'webhook_id':webhook,'enabled':'1'}
    r=admin.post(url,data=dict(payload,action='payments_notification_configure',csrf='invalid'),headers={'X-Forwarded-Proto':'https'});check('Receiver configuration requires CSRF','Your session expired' in r.text)
    r=submit(admin,url,'payments_notification_configure',payload);check('Receiver configuration refuses HTTP','requires HTTPS' in r.text)
    admin.headers.update({'X-Forwarded-Proto':'https'});r=submit(admin,url,'payments_notification_configure',payload);check('Owner configures sandbox receiver over trusted HTTPS gate','Sandbox receiver settings saved' in r.text and 'Configured for receipt' in r.text)
    body=json.dumps({'notificationId':str(uuid.uuid4()),'webhookId':webhook,'eventType':'net.authorize.payment.authcapture.created','eventDate':'2026-10-13T01:02:03Z','payload':{'id':'920001','entityName':'transaction','merchantReferenceId':'CW'+'c'*18,'authAmount':0.01,'customer':{'email':'NeverStoreHttpPayload@example.invalid'}}}).encode()
    signature='sha512='+hmac.new(bytes.fromhex('b'*128),body,hashlib.sha512).hexdigest();signed=dict(headers,**{'X-ANET-Signature':signature})
    r=requests.post(endpoint,headers=headers,data=body);check('Actual endpoint rejects missing HMAC',r.status_code==401 and r.json()=={'received':False})
    r=requests.post(endpoint,headers=signed,data=body+b' ');check('Actual endpoint authenticates exact raw bytes',r.status_code==401)
    r=requests.post(endpoint,headers=dict(signed,**{'Content-Type':'text/plain'}),data=body);check('Actual callback rejects non-JSON content type',r.status_code==415)
    r=requests.post(endpoint,headers=signed,data=b'x'*32769);check('Actual callback rejects oversized payload',r.status_code==413)
    before=db('SELECT * FROM ci_studio_invoices');r=requests.post(endpoint,headers=signed,data=body);check('Actual callback durably acknowledges synthetic signed notification',r.status_code==200 and r.json()=={'received':True} and 'Set-Cookie' not in r.headers and 'Location' not in r.headers)
    rows=db('SELECT * FROM ci_payment_notifications');check('HTTP receipt has minimal queued record and does not pay invoices',len(rows)==1 and rows[0]['status']=='queued' and db('SELECT * FROM ci_studio_invoices')==before and db('SELECT * FROM ci_payment_attempts')==[] and 'NeverStoreHttpPayload' not in json.dumps(rows))
    r=requests.post(endpoint,headers=signed,data=body);check('Actual callback duplicate acknowledged exactly once',r.status_code==200 and db('SELECT * FROM ci_payment_notifications')==rows)
    changed=body+b' ';changed_headers=dict(signed,**{'X-ANET-Signature':'sha512='+hmac.new(bytes.fromhex('b'*128),changed,hashlib.sha512).hexdigest()});r=requests.post(endpoint,headers=changed_headers,data=changed);check('Actual callback refuses changed-body replay of same notification ID',r.status_code==409)
    r=submit(admin,url,'payments_notification_verify',{'notification_id':rows[0]['id'],'gateway_revision':revision,'owner_password':OWNER});check('Unmatched HTTP notification stays queued for investigation','No unique sandbox attempt matches' in r.text and db('SELECT * FROM ci_payment_notifications')==rows)
    ctx=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=ctx.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)));external=[]
    p.on('request',lambda req:external.append(req.url) if 'authorize.net' in req.url else None)
    p.goto(BASE+'/existing/admin/index.php?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill(OWNER);p.locator('form button').click();p.wait_for_url('**/admin/index.php?view=dashboard');ctx.set_extra_http_headers({'X-Forwarded-Proto':'https'});p.goto(url);expect(p.get_by_role('heading',name='Notification inbox')).to_be_visible()
    check('Browser inbox redacts saved credentials and provider payload',all(x not in p.content() for x in ['HttpFixtureLogin','HttpFixtureKey12','b'*128,'NeverStoreHttpPayload']))
    p.screenshot(path=str(QA/'Sandbox-Notifications-Desktop.png'),full_page=True);p.set_viewport_size({'width':320,'height':844});p.screenshot(path=str(QA/'Sandbox-Notifications-Narrow.png'),full_page=True);check('Notification page fits 320px viewport',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    form=p.locator('form').filter(has=p.locator('[name=action][value=payments_notification_configure]'));form.locator('[name=enabled]').uncheck();form.locator('[name=owner_password]').fill(OWNER);form.get_by_role('button',name='Save receiver settings').click();expect(p.get_by_text('Sandbox receiver settings saved.',exact=False)).to_be_visible()
    check('Browser owner can disable callback while retaining history',requests.post(endpoint,headers=signed,data=body).status_code==503 and db('SELECT * FROM ci_payment_notifications')==rows)
    check('Inbox browser has no runtime errors or external provider requests',not errors and not external);ctx.close()
    (QA/'notification-browser-diagnostics.json').write_text(json.dumps({'page_errors':errors,'provider_requests':external,'signatures':'Synthetic Python HMAC over exact HTTP request bytes','tls':'Trusted loopback proxy fixture only; not actual TLS or merchant delivery'},indent=2),encoding='utf-8')
