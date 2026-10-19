"""Actual retained 1.8 upgrade, adversarial HTTP and Chromium client ZIP handover."""
import hashlib,io,json,shutil,subprocess,zipfile
from urllib.parse import parse_qs,urlparse
import requests
from playwright.sync_api import expect
from release_cases import db,chosen_form
from staff_cases import OWNER,STAFF

def archive(extra=None):
    stream=io.BytesIO()
    with zipfile.ZipFile(stream,'w') as z:
        for name,body in {'index.html':'<!doctype html><title>Delivered client site</title><h1>Your website</h1>','HANDOVER.md':'Open index.html locally or upload these static files. No database is required.','LICENSES.md':'Synthetic site and assets authored for this test. All included files may be transferred.',**(extra or {})}.items():z.writestr(zipfile.ZipInfo(name,date_time=(2026,10,12,0,0,0)),body)
    return stream.getvalue()

def upload(session,url,version='1.0.0',payload=None,changes=None):
    return submit(session,url,'delivery_upload',{'job_id':'1','version':version,'title':'Client website <script>neverExecute()</script>','notes':'New website handover. Read HANDOVER.md and LICENSES.md. <script>neverExecute()</script>','scope':'static',**(changes or {})},files={'archive':('untrusted-name.php',payload if payload is not None else archive(),'application/octet-stream')})

def publish(session,url,changes=None):
    return submit(session,url,'delivery_publish',{'owner_password':OWNER,'confirm':'1','rights':'1',**(changes or {})})

def upgrade():
    dest=WEB/'delivery-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.8.0',dest)
    seed=subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-delivery-upgrade'}),text=True,capture_output=True)
    (QA/'delivery-upgrade-seed.log').write_text(seed.stdout+seed.stderr,encoding='utf-8');seed.check_returncode()
    shutil.copyfile(ROOT/'test-runtime/delivery-upgrade-config.php',dest/'config.php');(dest/'.installed.php').write_text('<?php exit; ?>retained guard');(dest/'unrelated.txt').write_text('Retain unrelated file')
    preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',ROOT/'test-runtime/delivery-upgrade-private/media/retained.php',ROOT/'test-runtime/delivery-upgrade-private/payment-master.key']}
    tables=['staff_profiles','staff_invitations','support_clients','support_events','studio_clients','studio_jobs','studio_invoices','catalog_products','catalog_revisions','catalog_invoice_snapshots','payment_gateways','payment_requests','payment_attempts','payment_attempt_events','payment_notifications','invoice_payments','leads','projects']
    before={t:db('SELECT * FROM deliveryup_'+t) for t in tables};settings=db('SELECT * FROM deliveryup_settings');users=db('SELECT * FROM deliveryup_users')
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update
    apply_file_update(globals(),dest)
    check('Actual 1.8 delivery update preserves config locks media master key and unrelated files',all(p.read_bytes()==b for p,b in preserved.items()))
    up=requests.Session();root=BASE+'/delivery-upgrade/admin/index.php';url=root+'?view=deliveries';submit(up,root+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    r=submit(up,root+'?view=payments','payments_migrate',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'});check('Payment schema upgrade precedes the feature migration','Payment preparation enabled' in r.text)
    r=submit(up,url,'delivery_setup',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'});check('Actual 1.8 additive delivery setup succeeds','Website deliveries enabled' in r.text and db('SELECT * FROM deliveryup_website_releases')==[])
    after={t:db('SELECT * FROM deliveryup_'+t) for t in before};check('Actual 1.8 retains all prior billing notifications and client records',all(len(after[t])==len(rows) and all(row[k]==rows[i][k] for i,row in enumerate(after[t]) for k in rows[i]) for t,rows in before.items()))
    after={s['name']:s['value'] for s in db('SELECT * FROM deliveryup_settings')};check('Actual 1.8 retains original settings',all(after[s['name']]==s['value'] for s in settings if s['name'] not in ('payments_schema_version','delivery_schema_version')) and after['payments_schema_version']=='2' and after['delivery_schema_version']=='1')
    check('Actual 1.8 retains passwords and auth versions',all(all(a[k]==b[k] for k in b if k!='last_login') for a,b in zip(db('SELECT * FROM deliveryup_users'),users)))
    check('Actual 1.8 retained manual receipt and balance still render','$375.00' in up.get(root+'?view=invoice-payments&invoice_id=3').text)
    r=upload(up,url,'retained-upgrade');rid=db('SELECT id FROM deliveryup_website_releases')[0]['id'];r=publish(up,url+'&release='+rid,{'owner_password':'Synthetic upgrade passphrase 2026'});check('Upgraded 1.8 accepts and publishes new ZIP','Website release published' in r.text)
    client=requests.Session();portal=BASE+'/delivery-upgrade/portal/index.php';submit(client,portal,'login',{'email':'retained@example.invalid','password':'Synthetic retained client passphrase'});check('Retained client can download exact newly delivered ZIP',client.get(portal+'?download_release='+rid).content==archive())
    r=submit(up,root+'?view=payment-settings','payments_save',{'owner_password':'Synthetic upgrade passphrase 2026'});check('Retained credentials decrypt after delivery upgrade','Credentials protected and saved' in r.text)

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php']})
    db('DELETE FROM ci_rate_limits');upgrade()
    check('Fresh installation enables empty delivery tables',db("SELECT value FROM fresh_settings WHERE name='delivery_schema_version'")==[{'value':'1'}] and db('SELECT * FROM fresh_website_releases')==[])
    admin=requests.Session();root=BASE+'/existing/admin/index.php';url=root+'?view=deliveries';portal=BASE+'/existing/portal/index.php';submit(admin,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    r=submit(admin,url,'delivery_setup',{'owner_password':OWNER,'confirm':'1','csrf':'invalid'});check('Delivery setup rejects CSRF','session expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='delivery_schema_version'"))
    r=submit(admin,url,'delivery_setup',{'owner_password':'wrong','confirm':'1'});check('Delivery setup reauthenticates owner','current password' in r.text.lower())
    r=submit(admin,url,'delivery_setup',{'owner_password':OWNER,'confirm':'1'});check('HTTP owner enables deliveries','Website deliveries enabled' in r.text)
    check('Project links to website deliveries',url+'&job_id=1' in [a.get('href') for a in soup(admin.get(root+'?view=studio-job&id=1')).select('a')])
    check('Anonymous admin delivery view requires sign-in','Prepare a private draft' not in requests.get(url).text)
    r=upload(admin,url,changes={'csrf':'bad'});check('Multipart ZIP upload rejects CSRF',not db('SELECT id FROM ci_website_releases') and 'session expired' in r.text.lower())
    r=upload(admin,url,payload=archive({'../escape.php':'unsafe'}));check('HTTP unsafe archive is rejected without draft',not db('SELECT id FROM ci_website_releases') and 'unsafe' in r.text.lower())
    r=upload(admin,url,payload=archive({'settings.txt':'password="SyntheticLeak"'}));check('HTTP embedded credential is rejected without draft',not db('SELECT id FROM ci_website_releases') and 'credential' in r.text.lower())
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF});r=upload(member,url);row=db('SELECT * FROM ci_website_releases')[0];rid=row['id'];detail=url+'&release='+rid;download=portal+'?download_release='+rid;client_detail=portal+'?release='+rid
    check('Authorized client staff create private draft with ignored upload filename','Private draft uploaded' in r.text and row['status']=='draft' and 'untrusted-name' not in row['manifest'])
    check('Draft details escape title and notes','&lt;script&gt;neverExecute()&lt;/script&gt;' in r.text and '<script>neverExecute()' not in r.text)
    check('Non-owner sees review download but cannot publish','Download draft for review' in r.text and 'Publish to client' not in r.text)
    alice=requests.Session();bob=requests.Session()
    for session,email in [(alice,'alice@example.invalid'),(bob,'bob@example.invalid')]:submit(session,portal,'login',{'email':email,'password':'Synthetic client passphrase 2026'})
    check('Draft hidden from client list detail and download',rid not in alice.get(portal+'?downloads=1').text and alice.get(client_detail).status_code==404 and alice.get(download).status_code==404)
    check('Anonymous ZIP request never returns archive',requests.get(download).status_code==404 and not requests.get(download).content.startswith(b'PK'))
    check('Authorized staff review downloads exact draft ZIP',member.get(url+'&download_release='+rid).content==archive())
    form=form_data(form_for(admin.get(detail),'delivery_publish'));form.update(owner_password=OWNER,confirm='1',rights='1')
    r=member.post(detail,data=dict(form,csrf=token(member.get(detail))));check('Non-owner forged publication denied',db('SELECT status FROM ci_website_releases WHERE id=?',[rid])==[{'status':'draft'}])
    for changed in [{'csrf':'bad'},{'owner_password':'wrong'},{'rights':'0'},{'confirm':'0'},{'review_hash':'f'*64}]:
        r=admin.post(detail,data=dict(form,**changed));check('Publication rejects invalid '+next(iter(changed)),db('SELECT status FROM ci_website_releases WHERE id=?',[rid])==[{'status':'draft'}])
    db("CREATE TRIGGER ci_delivery_fail BEFORE INSERT ON ci_audit FOR EACH ROW BEGIN IF NEW.action LIKE 'deliveries.%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic delivery audit failure'; END IF; END")
    try:
        r=admin.post(detail,data=form);check('HTTP publication audit failure preserves draft and hides SQL','could not be saved' in r.text and 'SQLSTATE' not in r.text and db('SELECT status FROM ci_website_releases WHERE id=?',[rid])==[{'status':'draft'}])
    finally:db('DROP TRIGGER ci_delivery_fail')
    r=admin.post(detail,data=form);check('Owner publishes reviewed release to client','Website release published' in r.text and 'Publish to client' not in r.text)
    snapshot=db('SELECT * FROM ci_website_releases WHERE id=?',[rid]);admin.post(detail,data=form);check('Publish resubmission preserves original publication',db('SELECT * FROM ci_website_releases WHERE id=?',[rid])==snapshot)
    r=alice.get(download);data=archive();check('Client receives byte-exact private ZIP with checksum headers',r.content==data and r.headers['Content-Type']=='application/zip' and 'no-store' in r.headers['Cache-Control'] and r.headers['ETag']=='"'+hashlib.sha256(data).hexdigest()+'"' and r.headers['X-Content-Type-Options']=='nosniff')
    check('Filename is server-generated from project and version','website-1-1.0.0.zip' in r.headers['Content-Disposition'])
    check('Client download is durably attributed',bool(db("SELECT id FROM ci_delivery_events WHERE release_id=? AND actor_type='client' AND actor_id=1 AND action='download.started'",[rid])))
    for header,start,end in [('bytes=0-9',0,10),('bytes=10-',10,len(data)),('bytes=-20',len(data)-20,len(data))]:
        r=alice.get(download,headers={'Range':header});check('HTTP resumable '+header,r.status_code==206 and r.content==data[start:end] and r.headers['Content-Range']==f'bytes {start}-{end-1}/{len(data)}')
    check('Separate ranged downloads reconstruct original ZIP',alice.get(download,headers={'Range':'bytes=0-99'}).content+alice.get(download,headers={'Range':'bytes=100-'}).content==data)
    for header in ['bytes=9999999-','bytes=5-1','bytes=0-1,4-5','bytes=-0']:
        r=alice.get(download,headers={'Range':header});check('HTTP invalid range '+header,r.status_code==416 and r.headers['Content-Range']=='bytes */'+str(len(data)))
    r=alice.get(download,headers={'Range':'bytes=0-9','If-Range':'"old-checksum"'});check('Stale If-Range returns full verified archive',r.status_code==200 and r.content==data)
    r=alice.head(download);check('HEAD returns length without response body',r.status_code==200 and r.content==b'' and r.headers['Content-Length']==str(len(data)))
    check('Cross-client detail and stream denied',bob.get(client_detail).status_code==404 and bob.get(download).status_code==404)
    r=upload(admin,url,'1.1.0',archive({'changes.txt':'Second version with retained original.'}));rid2=db("SELECT id FROM ci_website_releases WHERE version='1.1.0'")[0]['id'];publish(admin,url+'&release='+rid2)
    r=alice.get(portal+'?downloads=1');check('Client history labels latest and prior published release',all(x in r.text for x in [rid,rid2,'Latest published release','Earlier published release']))
    check('Older published archive remains byte-identical',alice.get(download).content==data)
    check('Project portal exposes downloads',portal+'?downloads=1&amp;job=1' in alice.get(portal+'?job=1').text)
    db("CREATE TRIGGER ci_delivery_event_fail BEFORE INSERT ON ci_delivery_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic download audit failure'")
    try:r=alice.get(download);check('Download audit failure returns no ZIP bytes',r.status_code==503 and not r.content.startswith(b'PK') and 'SQLSTATE' not in r.text)
    finally:db('DROP TRIGGER ci_delivery_event_fail')
    # Private path is derived from the synthetic fixture, never from application-controlled download paths.
    private=ROOT/'test-runtime/private';file=next(ROOT.glob('test-runtime/**/deliveries/release-'+rid+'.php'));original=file.read_bytes();file.write_bytes(original+b'x')
    try:
        r=alice.get(download);check('Tampered private archive is refused',r.status_code==404 and not r.content.startswith(b'PK'));r=alice.get(client_detail);check('Integrity failure hides download link and explains unavailable file','integrity check' in r.text and 'Download website ZIP' not in r.text)
    finally:file.write_bytes(original)
    r=chosen_form(admin,root+'?view=support','support_policy','client_id',1,{'allowed':'1','owner_password':OWNER});r=chosen_form(admin,root+'?view=support','support_start','client_id',1,{});sid=parse_qs(urlparse(r.url).query)['support'][0]
    r=admin.get(client_detail+'&support='+sid);check('Support release view has persistent support banner','Support session' in r.text and 'Download website ZIP' in r.text)
    r=admin.get(download+'&support='+sid);check('Explicit support download attributed to real staff',r.content==data and bool(db("SELECT id FROM ci_delivery_events WHERE release_id=? AND actor_type='support'",[rid])) and bool(db("SELECT id FROM ci_support_events WHERE action='release.download' AND outcome='allowed' AND object_id=?",[rid])))
    db('UPDATE ci_support_sessions SET expires_at=1 WHERE id=?',[sid]);check('Expired support session cannot download',admin.get(download+'&support='+sid).status_code==403)
    uid=db("SELECT id FROM ci_users WHERE email='client-staff@example.invalid'")[0]['id'];chosen_form(admin,root+'?view=staff','staff_save','id',uid,{'permissions[]':['support.login'],'owner_password':OWNER});viewer=requests.Session();submit(viewer,root+'?view=login','login',{'email':'client-staff@example.invalid','password':STAFF});r=chosen_form(viewer,root+'?view=support','support_start','client_id',1,{});vid=parse_qs(urlparse(r.url).query)['support'][0]
    check('View-only support cannot download ZIP and denial is audited',viewer.get(download+'&support='+vid).status_code==404 and bool(db("SELECT id FROM ci_support_events WHERE action='release.download' AND support_id=? AND outcome='denied'",[vid])))
    check('View-only support detail omits download action','Download website ZIP' not in viewer.get(client_detail+'&support='+vid).text)
    db('UPDATE ci_studio_clients SET auth_version=? WHERE id=1',['b'*32]);check('Revoked client session cannot download',alice.get(download).status_code==404)
    upload(admin,url,'1.2.0-draft');draft_id=db("SELECT id FROM ci_website_releases WHERE version='1.2.0-draft'")[0]['id']
    # Real browser owner/client screens and a filesystem-verified browser download.
    ctx=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce',accept_downloads=True);p=ctx.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
    p.goto(root+'?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill(OWNER);p.locator('form button').click();p.wait_for_url('**/admin/index.php?view=dashboard');p.goto(url);p.screenshot(path=str(QA/'Website-Delivery-Admin-Desktop.png'),full_page=True);p.set_viewport_size({'width':320,'height':844});p.goto(url+'&release='+draft_id);p.locator('.delivery-detail > details > summary').click();p.evaluate('window.scrollTo(0,0)');p.screenshot(path=str(QA/'Website-Delivery-Admin-Narrow.png'),full_page=True);check('Admin draft manifest and owner review fit 320px screen',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1') and p.get_by_role('button',name='Publish to client',exact=True).count()==1)
    p.goto(portal);p.locator('[name=email]').fill('alice@example.invalid');p.locator('[name=password]').fill('Synthetic client passphrase 2026');p.locator('form button').click();p.wait_for_url('**/portal/index.php');p.get_by_role('link',name='Website downloads',exact=True).click();expect(p.get_by_role('heading',name='Website downloads',exact=True)).to_be_visible();check('Client history does not expose later unpublished draft',draft_id not in p.content());p.screenshot(path=str(QA/'Website-Delivery-Client-History.png'),full_page=True);p.goto(client_detail);p.locator('.delivery-detail > details > summary').click();p.evaluate('window.scrollTo(0,0)');p.screenshot(path=str(QA/'Website-Delivery-Client-Narrow.png'),full_page=True);check('Client release manifest fits 320px screen',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    with p.expect_download() as pending:p.get_by_role('link',name='Download website ZIP',exact=False).click()
    downloaded=QA/'Client-Website-Synthetic.zip';pending.value.save_as(downloaded);check('Chromium downloads exact published ZIP',downloaded.read_bytes()==data)
    with zipfile.ZipFile(downloaded) as z:
        check('Downloaded website ZIP passes CRC and contains handover files',z.testzip() is None and set(z.namelist())=={'index.html','HANDOVER.md','LICENSES.md'});z.extractall(QA/'client-website-extracted')
    p.goto((QA/'client-website-extracted/index.html').as_uri());expect(p.get_by_role('heading',name='Your website',exact=True)).to_be_visible();check('Delivered static site opens locally after extraction',p.title()=='Delivered client site');check('Delivery browser has no runtime errors',not errors);ctx.close()
    (QA/'delivery-browser-diagnostics.json').write_text(json.dumps({'page_errors':errors,'scope':'Synthetic client packages and identities; real PHP HTTP, Chromium download and local static-site handover; no live deployment'},indent=2),encoding='utf-8')
