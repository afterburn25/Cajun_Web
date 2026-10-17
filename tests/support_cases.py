"""A-09 actual package upgrade, HTTP isolation, failure injection and Chromium workflows."""
import json, shutil, subprocess, zipfile
from urllib.parse import parse_qs, urlparse
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER, STAFF

def run_upgrade():
    dest=WEB/'support-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.2.0',dest)
    subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-support-upgrade'}),text=True,capture_output=True,check=True)
    shutil.copyfile(ROOT/'test-runtime/support-upgrade-config.php',dest/'config.php')
    (dest/'.installed.php').write_text('<?php exit; ?>original 1.2.0 guard');(dest/'unrelated.txt').write_text('Retain original file')
    preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',ROOT/'test-runtime/support-upgrade-private/media/retained.php']}
    tables=['users','settings','staff_profiles','staff_invitations','studio_clients','studio_jobs','leads','projects']
    before={t:db('SELECT * FROM supportup_'+t) for t in tables}
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update
    apply_file_update(globals(),dest)
    check('Actual 1.2.0 update preserves configuration locks private media and unrelated files',all(p.read_bytes()==v for p,v in preserved.items()))
    up=requests.Session();root=BASE+'/support-upgrade/admin/index.php'
    r=submit(up,root+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    check('Actual 1.2.0 owner signs in before support migration','Sales pipeline' in r.text)
    r=submit(up,root+'?view=support','support_migrate',{'owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'})
    check('Actual 1.2.0 explicit support upgrade succeeds','Support access is ready' in r.text)
    for t in ['staff_profiles','staff_invitations','studio_clients','studio_jobs','leads','projects']:
        check('Actual 1.2.0 support update retains '+t,db('SELECT * FROM supportup_'+t)==before[t])
    after={r['name']:r['value'] for r in db('SELECT * FROM supportup_settings')}
    check('Actual 1.2.0 retains original settings',all(after[r['name']]==r['value'] for r in before['settings'] if not r['name'].endswith('_schema_version')))
    users=db('SELECT * FROM supportup_users')
    check('Actual 1.2.0 retains passwords and auth versions',all(all(a[k]==b[k] for k in b if k!='last_login') for a,b in zip(users,before['users'])))
    check('Actual 1.2.0 has no implicit client support grants',not db('SELECT * FROM supportup_support_clients'))
    check('Actual 1.2.0 installed guard still blocks fresh setup',requests.get(BASE+'/support-upgrade/install.php').status_code==404)

def start(session,client):
    r=chosen_form(session,ADMIN+'?view=support','support_start','client_id',client,{})
    sid=parse_qs(urlparse(r.url).query).get('support',[''])[0]
    check('Support start opens selected client '+str(client),len(sid)==32 and r.status_code==200 and 'Support session' in r.text)
    return sid

def portal(sid,**params):
    return PORTAL+'?'+requests.compat.urlencode({'support':sid,**params})

def end(session,sid):
    return submit(session,portal(sid),'support_end')

def run_http(env):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php','admin','bob']})
    global ADMIN, PORTAL
    ADMIN=BASE+'/existing/admin/index.php';PORTAL=BASE+'/existing/portal/index.php'
    db('DELETE FROM ci_rate_limits');run_upgrade()
    check('Fresh installation includes support tables but no automatic client access',db("SELECT value FROM fresh_settings WHERE name='support_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_support_clients'))
    r=submit(admin,ADMIN+'?view=support','support_migrate',{'owner_password':'wrong','confirm':'1'})
    check('HTTP support setup requires current owner password','current password is not correct' in r.text)
    r=submit(admin,ADMIN+'?view=support','support_migrate',{'owner_password':OWNER,'confirm':'1','csrf':'bad'})
    check('HTTP support setup rejects forged CSRF','session expired' in r.text.lower())
    r=submit(admin,ADMIN+'?view=support','support_migrate',{'owner_password':OWNER,'confirm':'1'})
    check('Owner explicitly enables support schema and policy','Support access is ready' in r.text)
    r=admin.get(ADMIN+'?view=support');r=admin.post(r.url,data={'csrf':token(r),'action':'support_start','client_id':'1'})
    check('Client support defaults to restricted','unavailable for support access' in r.text and not db('SELECT * FROM ci_support_sessions'))
    for cid in [1,2]:chosen_form(admin,ADMIN+'?view=support','support_policy','client_id',cid,{'allowed':'1','owner_password':OWNER})
    check('Client record exposes one-click support action',bool(soup(admin.get(ADMIN+'?view=studio-clients')).select_one('[name=action][value=support_start]')))
    r=bob.get(PORTAL);r=bob.post(ADMIN+'?view=support',data={'csrf':token(r),'action':'support_start','client_id':'1'},allow_redirects=False)
    check('Client cannot start a staff support session',r.status_code==303 and 'login' in r.headers.get('Location',''))
    # Actual customer login coexists with the owner in the same browser cookie jar.
    submit(admin,PORTAL,'login',{'email':'bob@example.invalid','password':'Synthetic client passphrase 2026'})
    credentials=db('SELECT id,password_hash,auth_version FROM ci_studio_clients')
    sid=start(admin,1);r=admin.get(portal(sid,job=1));support_token=token(r)
    check('Support portal is scoped to Alice while normal portal stays Bob','Welcome, <span class="gold-text">Alice' in r.text and 'Welcome, <span class="gold-text">Bob' in admin.get(PORTAL).text)
    r=admin.post(PORTAL+'?job=2',data={'csrf':support_token,'action':'message','body':'Stripped context must not mutate Bob'})
    check('Stripping support context cannot reuse form CSRF','session expired' in r.text.lower() and not db('SELECT id FROM ci_studio_messages WHERE body=?',['Stripped context must not mutate Bob']))
    r=admin.post(portal(sid,job=1),data={'csrf':token(admin.get(PORTAL)),'action':'message','body':'Client token must not mutate support'})
    check('Ordinary client token cannot authorize support form','support form expired' in r.text)
    r=chosen_form(admin,ADMIN+'?view=support','support_start','client_id',2,{})
    check('Second client cannot replace active support context','End the current support session' in r.text)
    other=requests.Session();submit(other,ADMIN+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    check('Copied support URL fails in another owner browser',other.get(portal(sid)).status_code==403)
    check('Cross-client project URL denied',admin.get(portal(sid,job=2)).status_code==404)
    r=admin.post(portal(sid,job=2),data={'csrf':token(admin.get(portal(sid))),'action':'message','body':'Cross client must fail'})
    check('Cross-client forged message cannot persist',r.status_code==404 and not db('SELECT id FROM ci_studio_messages WHERE body=?',['Cross client must fail']))
    decisions=db('SELECT id,decision,feedback FROM ci_studio_versions')
    for action in ['decision','activate','login','logout','payment','recurring_enrollment']:
        r=admin.post(portal(sid,job=1),data={'csrf':token(admin.get(portal(sid))),'action':action,'version':'1','choice':'approved','confirm':'1','password':'Forged client passphrase 2026'})
        check('Support blocks sensitive action '+action,'action is blocked during support' in r.text)
    check('Blocked support actions preserve client credentials and decisions',db('SELECT id,password_hash,auth_version FROM ci_studio_clients')==credentials and db('SELECT id,decision,feedback FROM ci_studio_versions')==decisions)
    submit(admin,portal(sid,job=1),'message',{'body':'HTTP attributed support update'})
    m=db('SELECT author_type,author_id FROM ci_studio_messages WHERE body=?',['HTTP attributed support update'])[0]
    check('HTTP support message is attributed to real staff',m=={'author_type':'admin','author_id':1})
    png=(ROOT/'site/images/icons/favicon.png').read_bytes()
    submit(admin,portal(sid,job=1),'upload',files={'document':('support-upload.png',png,'image/png')})
    file=db('SELECT id,author_type FROM ci_studio_files WHERE original_name=?',['support-upload.png'])[0]
    check('HTTP support upload retains staff author',file['author_type']=='admin')
    r=admin.get(portal(sid,file=file['id']))
    check('Authorized support download matches actual private bytes',r.content==png and r.status_code==200)
    # Inject audit failure after permission checks, before commit/streaming.
    for action,operation in [('message.send','message'),('file.upload','upload'),('file.download','download')]:
        csrf=token(admin.get(portal(sid,job=1)));before=db('SELECT COUNT(*) AS n FROM ci_studio_messages')[0]['n'];files=db('SELECT * FROM ci_studio_files');private=list((ROOT/'test-runtime/private/media').glob('studio-*.php'))
        db("CREATE TRIGGER ci_support_audit_failure BEFORE INSERT ON ci_support_events FOR EACH ROW BEGIN IF NEW.action='"+action+"' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'; END IF; END")
        try:
            if operation=='download':r=admin.get(portal(sid,file=file['id']))
            else:r=admin.post(portal(sid,job=1),data={'csrf':csrf,'action':operation,'body':'Audit failure cannot save'},files={'document':('failed-audit.png',png,'image/png')} if operation=='upload' else None)
            check('Audit failure blocks '+operation, r.status_code>=500 if operation=='download' else 'could not be recorded' in r.text)
            check('Audit failure leaves no '+operation+' side effect',db('SELECT COUNT(*) AS n FROM ci_studio_messages')[0]['n']==before and db('SELECT * FROM ci_studio_files')==files and list((ROOT/'test-runtime/private/media').glob('studio-*.php'))==private and r.content!=png)
        finally:db('DROP TRIGGER ci_support_audit_failure')
    end(admin,sid);sid2=start(admin,2)
    check('Old tab cannot become new client context',admin.get(portal(sid,job=1)).status_code==403)
    check('Cross-client private file download is denied',admin.get(portal(sid2,file=file['id'])).status_code==404)
    db('UPDATE ci_support_sessions SET expires_at=0 WHERE id=?',[sid2])
    r=admin.get(portal(sid2));check('Expired support never falls back to logged-in customer',r.status_code==403 and 'Welcome,' not in r.text)
    sid=start(admin,1);chosen_form(admin,ADMIN+'?view=support','support_policy','client_id',1,{'allowed':None,'owner_password':OWNER})
    check('Client restriction immediately revokes open support URL',admin.get(portal(sid)).status_code==403)
    chosen_form(admin,ADMIN+'?view=support','support_policy','client_id',1,{'allowed':'1','owner_password':OWNER})
    # Reuse an existing, activated synthetic administrator with explicit view-only access.
    uid=db('SELECT id FROM ci_users WHERE email=?',['client-staff@example.invalid'])[0]['id']
    chosen_form(admin,ADMIN+'?view=staff','staff_save','id',uid,{'permissions[]':['support.login'],'owner_password':OWNER})
    viewer=requests.Session();r=submit(viewer,ADMIN+'?view=login','login',{'email':'client-staff@example.invalid','password':STAFF})
    check('Support-only administrator lands in client support','view=support' in r.url and viewer.get(ADMIN+'?view=studio-clients').status_code==403)
    vid=start(viewer,1);r=viewer.get(portal(vid,job=1));csrf=token(r)
    check('View-only support hides writes downloads and client approvals',not soup(r).select('[name=action][value=message],[name=action][value=upload],[name=action][value=decision],a[href*="file="]'))
    for action in ['message','upload']:
        r=viewer.post(portal(vid,job=1),data={'csrf':csrf,'action':action,'body':'Denied viewer write'},files={'document':('denied.png',png,'image/png')} if action=='upload' else None)
        check('Forged view-only '+action+' is denied','notice error' in r.text and not db('SELECT id FROM ci_studio_messages WHERE body=?',['Denied viewer write']))
    check('View-only forged download returns no private bytes',viewer.get(portal(vid,file=file['id'])).status_code==403)
    chosen_form(admin,ADMIN+'?view=staff','staff_save','id',uid,{'permissions[]':['support.login','support.write','support.download'],'owner_password':OWNER})
    check('Permission change revokes open support context',viewer.get(portal(vid)).status_code==403)
    submit(viewer,ADMIN+'?view=login','login',{'email':'client-staff@example.invalid','password':STAFF});vid=start(viewer,1)
    check('Explicit support download grant works without client-management grant',viewer.get(portal(vid,file=file['id'])).content==png)
    submit(viewer,portal(vid,job=1),'message',{'body':'Explicit staff support writer'})
    check('Explicit write grant retains restricted staff identity',db('SELECT author_id FROM ci_studio_messages WHERE body=?',['Explicit staff support writer'])==[{'author_id':uid}])
    r=viewer.get(ADMIN+'?view=support');viewer.post(ADMIN,data={'csrf':token(r),'action':'logout'})
    check('Admin logout ends open support context',viewer.get(portal(vid)).status_code==403 and db('SELECT end_reason FROM ci_support_sessions WHERE id=?',[vid])[0]['end_reason']=='admin-logout')
    events=db('SELECT * FROM ci_support_events');check('HTTP audit covers start end write upload download and denial',all(a in [e['action'] for e in events] for a in ['session.start','session.end','message.send','file.upload','file.download','action.blocked']) and any(e['outcome']=='denied' for e in events))
    check('HTTP support audit excludes message bodies and passwords',not any(secret in json.dumps(events) for secret in ['HTTP attributed support update',OWNER,STAFF]))
    check('Support leaves actual client login intact','Welcome, <span class="gold-text">Bob' in admin.get(PORTAL).text)
    db('DELETE FROM ci_rate_limits')

def run_browser(env,browser):
    run_http(env)
    owner=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=owner.new_page();errors=[]
    p.on('pageerror',lambda e:errors.append(str(e)))
    p.goto(ADMIN+'?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill(OWNER);p.locator('form button').click();p.wait_for_url('**/admin/index.php?view=dashboard')
    p.goto(ADMIN+'?view=support');p.screenshot(path=str(QA/'Client-Support-Desktop.png'),full_page=True)
    check('Support admin desktop fits',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    p.set_viewport_size({'width':320,'height':800});p.screenshot(path=str(QA/'Client-Support-Narrow.png'),full_page=True)
    check('Support admin narrow viewport fits',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    p.goto(ADMIN+'?view=studio-clients')
    form=p.locator('form').filter(has=p.locator('[name=action][value=support_start]')).filter(has=p.locator('[name=client_id][value="1"]'));form.locator('button').click();expect(p.locator('[aria-label="Support session"]')).to_be_visible()
    sid=parse_qs(urlparse(p.url).query)['support'][0];check('Browser client-record button opens correct support context',len(sid)==32 and 'Alice' in p.locator('[aria-label="Support session"]').inner_text())
    p.get_by_role('link',name='Open project').first.click();expect(p.locator('[aria-label="Support session"]')).to_be_visible()
    check('Browser project navigation retains support binding','support='+sid in p.url)
    p.screenshot(path=str(QA/'Support-Portal-Narrow.png'),full_page=True);check('Support portal narrow viewport fits',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    p.locator('[name=body]').fill('Browser staff support message');p.get_by_role('button',name='Send message').click();expect(p.get_by_text('Browser staff support message',exact=False)).to_be_visible()
    check('Browser support message preserves real staff author',db('SELECT author_type,author_id FROM ci_studio_messages WHERE body=?',['Browser staff support message'])==[{'author_type':'admin','author_id':1}])
    p.set_viewport_size({'width':1440,'height':1000});p.screenshot(path=str(QA/'Support-Portal-Desktop.png'),full_page=True)
    p.evaluate('window.scrollTo(0, document.body.scrollHeight)');check('Support banner remains visible while scrolling',p.locator('[aria-label="Support session"]').bounding_box()['y']>=0)
    p.get_by_role('button',name='Return to admin').click();p.wait_for_url('**/admin/index.php?view=support')
    check('Browser return ends support session',db('SELECT end_reason FROM ci_support_sessions WHERE id=?',[sid])[0]['end_reason']=='returned-to-admin')
    check('Support browser has no uncaught page errors',not errors)
    (QA/'support-browser-diagnostics.json').write_text(json.dumps({'page_errors':errors},indent=2));owner.close()
