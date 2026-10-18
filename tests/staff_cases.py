"""Actual package upgrade and administrator HTTP/browser acceptance; synthetic identities only."""
import hashlib, json, shutil, subprocess, zipfile
from urllib.parse import parse_qs, urlparse
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form

OWNER='Synthetic owner passphrase 2026'
STAFF='Synthetic staff browser passphrase'

def run_http(env):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php','admin','bob']})
    # Isolate the new bounded cases from prior fixture rate-limit consumption.
    db('DELETE FROM ci_rate_limits')
    check('Fresh installed owner has account-management role',db('SELECT role,enabled,permissions FROM fresh_staff_profiles')==[{'role':'owner','enabled':1,'permissions':'[]'}])
    dest=WEB/'staff-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.1.0',dest)
    subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-staff-upgrade'}),text=True,capture_output=True,check=True)
    shutil.copyfile(ROOT/'test-runtime/staff-upgrade-config.php',dest/'config.php')
    (dest/'.installed.php').write_text('<?php exit; ?>original 1.1.0 guard');(dest/'unrelated.txt').write_text('Retain unrelated 1.1.0 file')
    preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',ROOT/'test-runtime/staff-upgrade-private/media/retained.php']}
    tables=['users','settings','leads','projects','studio_clients','studio_jobs']
    before={t:db('SELECT * FROM staffup_'+t) for t in tables}
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update, removed_setup_unavailable
    apply_file_update(globals(),dest)
    check('1.1.0 package update preserves configuration locks media unrelated files',all(p.read_bytes()==v for p,v in preserved.items()))
    up=requests.Session();root=BASE+'/staff-upgrade/admin/index.php'
    r=submit(up,root+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    check('Actual 1.1.0 owner signs in before explicit staff migration','Sales pipeline' in r.text)
    r=submit(up,root+'?view=staff','staff_migrate',{'owner_id':'1','owner_password':'Synthetic upgrade passphrase 2026','confirm':'1'})
    check('Actual 1.1.0 account upgrade succeeds','Administrator access is enabled' in r.text)
    for t in ['leads','projects','studio_clients','studio_jobs']:
        check('Actual 1.1.0 retains populated '+t,db('SELECT * FROM staffup_'+t)==before[t])
    after={r['name']:r['value'] for r in db('SELECT * FROM staffup_settings')}
    check('Actual 1.1.0 retains all original settings',all(after[r['name']]==r['value'] for r in before['settings']))
    users=db('SELECT * FROM staffup_users')
    check('Actual 1.1.0 retains all passwords identifiers and auth versions',all(all(a[k]==b[k] for k in b if k!='last_login') for a,b in zip(users,before['users'])))
    profiles=db('SELECT role,enabled,permissions FROM staffup_staff_profiles ORDER BY user_id')
    check('Migration explicitly assigns owner and other existing staff',profiles[0]['role']=='owner' and profiles[1]['role']=='administrator' and json.loads(profiles[1]['permissions'])==['website.manage','clients.manage'])
    check('Upgraded 1.1.0 fresh setup remains blocked',removed_setup_unavailable(globals(),dest,up))
    other=requests.Session();r=submit(other,root+'?view=login','login',{'email':'other-legacy@example.invalid','password':'Synthetic other legacy passphrase'})
    check('Other legacy administrator retains normal sign-in and client access','Sales pipeline' in r.text and other.get(root+'?view=studio-clients').status_code==200 and other.get(root+'?view=staff').status_code==403)
    # Upgrade the regression fixture only after its original release acceptance is complete.
    url=BASE+'/existing/admin/index.php?view=staff'
    original=db('SELECT id,email,password_hash,auth_version FROM ci_users WHERE id=1')[0]
    r=submit(admin,url,'staff_migrate',{'owner_id':'1','owner_password':OWNER,'confirm':'1'})
    check('HTTP owner enables administrator access with reauthentication','Administrator access is enabled' in r.text and db('SELECT id,email,password_hash,auth_version FROM ci_users WHERE id=1')[0]==original)
    create={'id':'0','name':'Website staff','email':'website-staff@example.invalid','role':'administrator','enabled':'1','permissions[]':['website.manage'],'owner_password':OWNER}
    r=submit(admin,url,'staff_save',dict(create,csrf='bad'))
    check('HTTP staff create rejects bad CSRF','session expired' in r.text.lower() and not db('SELECT id FROM ci_users WHERE email=?',[create['email']]))
    r=submit(admin,url,'staff_save',dict(create,owner_password='incorrect'))
    check('HTTP staff create requires current owner password','current password is not correct' in r.text and not db('SELECT id FROM ci_users WHERE email=?',[create['email']]))
    r=submit(admin,url,'staff_save',create);uid=db('SELECT id FROM ci_users WHERE email=?',[create['email']])[0]['id']
    check('HTTP creates pending administrator without a password',db('SELECT password_hash FROM ci_users WHERE id=?',[uid])[0]['password_hash']=='')
    capture=(QA/'mail-capture.txt').read_bytes()
    r=chosen_form(admin,url,'staff_invite','id',uid,{'owner_password':OWNER});link=soup(r).select_one('[data-staff-invite]')['href'];code=parse_qs(urlparse(link).fragment)['token'][0]
    check('Private staff link uses a fragment and is shown once',urlparse(link).query=='view=staff-setup' and len(code)==64 and not soup(admin.get(url)).select_one('[data-staff-invite]'))
    get=requests.get(link);requests.get(link)
    check('Viewing staff setup link cannot consume it',db('SELECT consumed_at FROM ci_staff_invitations WHERE subject_id=?',[str(uid)])[0]['consumed_at'] is None and get.headers.get('Referrer-Policy')=='no-referrer')
    check('Invitations do not send customer or staff mail',(QA/'mail-capture.txt').read_bytes()==capture)
    check('Database invitation holds only its separated digest',db('SELECT token_hash FROM ci_staff_invitations WHERE subject_id=?',[str(uid)])[0]['token_hash']==hashlib.sha256(('cwp:staff-invitation:v1\0'+code).encode()).hexdigest())
    plain=requests.Session();r=plain.get(link);r=plain.post(BASE+'/existing/admin/index.php?view=staff-setup',data={'action':'staff_activate','csrf':token(r),'token[]':'bad','password':STAFF,'confirm_password':STAFF})
    check('Malformed setup code returns usable inline error',r.status_code==200 and 'data-setup-token' in r.text and 'notice error' in r.text)
    # A forged role/grant POST is checked independently of the displayed page.
    r=bob.post(BASE+'/existing/admin/index.php?view=staff',data={'csrf':token(bob.get(BASE+'/existing/portal/index.php?job=2')),'action':'staff_save',**create},allow_redirects=False)
    check('Client identity cannot create administrator accounts',r.status_code==303 and 'login' in r.headers.get('Location',''))
    r=chosen_form(admin,url,'staff_save','id',1,{'role':'administrator','owner_password':OWNER})
    check('HTTP cannot demote last activated owner','at least one active owner' in r.text and db('SELECT role FROM ci_staff_profiles WHERE user_id=1')[0]['role']=='owner')
    # Staff-only project notes never reach clients or staff without client grants.
    r=submit(admin,BASE+'/existing/admin/index.php?view=studio-job&id=1','studio_note',{'body':'Staff-only internal marker note.'})
    check('Owner saves an internal staff note','Project updated' in r.text and db("SELECT author_type FROM ci_studio_messages WHERE body='Staff-only internal marker note.'")[0]['author_type']=='internal')
    r=admin.get(BASE+'/existing/admin/index.php?view=studio-job&id=1')
    check('Admin project renders the note privately once','staff only' in r.text and r.text.count('Staff-only internal marker note.')==1 and 'Internal notes are never shown' in r.text)
    alice=requests.Session();submit(alice,BASE+'/existing/portal/index.php','login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    check('Client conversation never shows internal notes','Staff-only internal marker note.' not in alice.get(BASE+'/existing/portal/index.php?job=1').text)
    # The upgraded install gates workspace activity behind owner setup; enable it before asserting feed content.
    if not db("SELECT value FROM ci_settings WHERE name='activity_schema_version'"):
        r=submit(admin,BASE+'/existing/admin/index.php?view=activity','activity_setup',{'password':OWNER})
        check('Owner enables workspace activity on the upgraded install','Workspace activity is enabled' in r.text)
    check('Workspace activity is enabled for feed assertions',db("SELECT value FROM ci_settings WHERE name='activity_schema_version'")==[{'value':'1'}])
    check('Client activity never surfaces internal notes','Staff-only internal marker note.' not in alice.get(BASE+'/existing/portal/index.php?activity=1').text)
    # Structured milestones carry a state and optional target date the client can follow.
    job=BASE+'/existing/admin/index.php?view=studio-job&id=1'
    r=chosen_form(admin,job,'studio_milestone','id',0,{'title':'Synthetic milestone content approved','state':'done','target_date':'2026-11-30'})
    check('Owner adds a structured milestone','Project updated' in r.text and db("SELECT state,target_date FROM ci_studio_milestones WHERE title='Synthetic milestone content approved'")[0]=={'state':'done','target_date':'2026-11-30'})
    r=chosen_form(admin,job,'studio_milestone','id',0,{'title':'Synthetic milestone launch window','state':'pending','target_date':''})
    mid=db("SELECT id FROM ci_studio_milestones WHERE title='Synthetic milestone launch window'")[0]['id']
    r=chosen_form(admin,job,'studio_milestone','id',mid,{'title':'Synthetic milestone launch window','state':'progress','target_date':'2027-01-15'})
    check('Milestone state update persists','Project updated' in r.text and db("SELECT state,target_date FROM ci_studio_milestones WHERE id=?",[mid])[0]=={'state':'progress','target_date':'2027-01-15'})
    r=admin.post(job,data={'csrf':token(admin.get(job)),'action':'studio_milestone','job_id':'1','id':'0','title':'Bad date','state':'done','target_date':'next Friday'})
    check('Milestone target date must be ISO','YYYY-MM-DD' in r.text and not db("SELECT id FROM ci_studio_milestones WHERE title='Bad date'"))
    r=chosen_form(admin,job,'studio_milestone','id',0,{'title':'Synthetic milestone awaiting client content','state':'blocked','target_date':''})
    check('Blocked milestone persists',db("SELECT state FROM ci_studio_milestones WHERE title='Synthetic milestone awaiting client content'")[0]['state']=='blocked')
    for bad in ['2026-02-31','2026-13-01','2026-00-00','2027-02-29']:
        r=admin.post(job,data={'csrf':token(admin.get(job)),'action':'studio_milestone','job_id':'1','id':'0','title':'Bad date','state':'done','target_date':bad})
        check('Milestone target date must be a real calendar date','calendar date' in r.text and not db("SELECT id FROM ci_studio_milestones WHERE title='Bad date'"))
    r=chosen_form(admin,job,'studio_milestone','id',mid,{'target_date':'2028-02-29'})
    check('Milestone target accepts a real leap day','Project updated' in r.text and db('SELECT target_date FROM ci_studio_milestones WHERE id=?',[mid])[0]['target_date']=='2028-02-29')
    r=alice.get(BASE+'/existing/portal/index.php?job=1')
    check('Client sees milestone states and target dates','Synthetic milestone content approved' in r.text and '2026-11-30' in r.text and 'milestone-'+str(mid) in r.text)
    check('Client sees open blockers and the needed-actions section','Open blockers' in r.text and 'What we need from you' in r.text)
    feed=alice.get(BASE+'/existing/portal/index.php?activity=1')
    check('Milestone change enters client activity feed','milestone:'+str(mid) in feed.text)
    check('Milestones stay isolated to the owning client',bob.get(BASE+'/existing/portal/index.php?job=1').status_code==404)
    return {'url':url,'id':uid,'link':link,'code':code}

def run_browser(env,browser):
    fixture=run_http(env);url=fixture['url'];uid=fixture['id']
    owner=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=owner.new_page();errors=[]
    p.on('pageerror',lambda e:errors.append(str(e)))
    p.goto(BASE+'/existing/admin/index.php?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill(OWNER);p.locator('form button').click();p.wait_for_url('**/admin/index.php?view=dashboard')
    p.goto(url);p.screenshot(path=str(QA/'Administrators-Desktop.png'),full_page=True)
    check('Administrator desktop fits',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    p.set_viewport_size({'width':320,'height':800});p.screenshot(path=str(QA/'Administrators-Narrow.png'),full_page=True)
    check('Administrator narrow mobile fits',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    # Create a client-workspace-only member entirely in the actual browser.
    create=p.locator('form').filter(has=p.locator('[name=action][value=staff_save]')).filter(has=p.locator('[name=id][value="0"]'))
    create.locator('[name=name]').fill('Client support staff');create.locator('[name=email]').fill('client-staff@example.invalid');create.locator('[name="permissions[]"][value="clients.manage"]').check();create.locator('[name=owner_password]').fill(OWNER);create.locator('button').click()
    expect(p.locator('[name=email][value="client-staff@example.invalid"]')).to_be_visible()
    cid=db('SELECT id FROM ci_users WHERE email=?',['client-staff@example.invalid'])[0]['id']
    check('Browser administrator creation persists selected access',json.loads(db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[cid])[0]['permissions'])==['clients.manage'])
    invite=p.locator('form').filter(has=p.locator('[name=action][value=staff_invite]')).filter(has=p.locator('[name=id][value="'+str(cid)+'"]'));invite.locator('[name=owner_password]').fill(OWNER);invite.locator('button').click()
    expect(p.locator('[data-staff-invite]')).to_be_visible();client_link=p.locator('[data-staff-invite]').get_attribute('href')
    check('Long private link fits narrow mobile',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    # Activate website staff using the generated fragment and actual local script.
    staff=browser.new_context(viewport={'width':390,'height':844});s=staff.new_page();s.on('pageerror',lambda e:errors.append(str(e)))
    response=s.goto(fixture['link']);expect(s.locator('[data-setup-token]')).to_have_value(fixture['code'])
    check('Browser setup removes token from location without consuming it','#' not in s.url and db('SELECT consumed_at FROM ci_staff_invitations WHERE subject_id=?',[str(uid)])[0]['consumed_at'] is None)
    check('Private setup page prevents referrer disclosure',response.headers.get('referrer-policy')=='no-referrer')
    s.locator('[name=password]').fill(STAFF);s.locator('[name=confirm_password]').fill(STAFF);s.get_by_role('button',name='Set up account').click();expect(s.locator('[name=email]')).to_be_visible()
    check('Browser activates account and consumes single-use link',db('SELECT consumed_at FROM ci_staff_invitations WHERE subject_id=?',[str(uid)])[0]['consumed_at'] is not None)
    s.locator('[name=email]').fill('website-staff@example.invalid');s.locator('[name=password]').fill(STAFF);s.locator('form button').click();s.wait_for_url('**/admin/index.php?view=dashboard')
    nav=s.locator('nav[aria-label=Administration]')
    s.get_by_role('button',name='Toggle administration navigation').click()
    website_group=nav.locator('[data-nav-group=website]')
    website_group.locator('summary').click()
    expect(website_group).to_have_attribute('open','')
    expect(nav.get_by_role('link',name='Portfolio',exact=True)).to_be_visible()
    check('Assigned website navigation hides owner and client sections',nav.get_by_role('link',name='Portfolio',exact=True).count()==1 and nav.get_by_role('link',name='Administrators',exact=True,include_hidden=True).count()==0 and nav.get_by_role('link',name='Client workspace',exact=True,include_hidden=True).count()==0 and nav.get_by_role('link',name='Email delivery',exact=True,include_hidden=True).count()==0)
    web=requests.Session();r=submit(web,BASE+'/existing/admin/index.php?view=login','login',{'email':'website-staff@example.invalid','password':STAFF})
    chosen_form(web,BASE+'/existing/admin/index.php?view=studio-pipeline','studio_pipeline','id',1,{'status':'qualified','notes':'Website staff saved this note','follow_up':''})
    check('Website staff can save authorized inquiry work',db('SELECT notes FROM ci_leads WHERE id=1')[0]['notes']=='Website staff saved this note')
    for view in ['staff','email','health','studio-upgrade','studio-clients','studio-job&id=1','studio-job&file=1']:
        r=web.get(BASE+'/existing/admin/index.php?view='+view);check('Direct website staff denial '+view,r.status_code==403)
    count=db('SELECT COUNT(*) AS n FROM ci_studio_clients')[0]['n']
    r=web.get(BASE+'/existing/admin/index.php?view=security');r=web.post(r.url,data={'csrf':token(r),'action':'studio_client','id':'0','name':'Forbidden','email':'forbidden@example.invalid'})
    check('POST authorization cannot be bypassed through own-account page','does not have access' in r.text and db('SELECT COUNT(*) AS n FROM ci_studio_clients')[0]['n']==count)
    r=web.get(BASE+'/existing/admin/index.php?view=security');r=web.post(r.url,data={'csrf':token(r),'action':'studio_note','job_id':'1','body':'Unauthorized note attempt.'})
    check('Website staff cannot write internal project notes','does not have access' in r.text and not db("SELECT id FROM ci_studio_messages WHERE body='Unauthorized note attempt.'"))
    # The second administrator activates without JavaScript and gets the correct landing page.
    plain=browser.new_context(java_script_enabled=False);c=plain.new_page();c.goto(client_link);c.locator('[data-setup-token]').fill(parse_qs(urlparse(client_link).fragment)['token'][0]);c.locator('[name=password]').fill(STAFF);c.locator('[name=confirm_password]').fill(STAFF);c.get_by_role('button',name='Set up account').click();expect(c.locator('[name=email]')).to_be_visible()
    c.locator('[name=email]').fill('client-staff@example.invalid');c.locator('[name=password]').fill(STAFF);c.locator('form button').click();c.wait_for_url('**/admin/index.php?view=studio-clients')
    check('No-JavaScript setup and client-only landing work',c.locator('h1').inner_text()=='Client workspace')
    nav=c.locator('nav[aria-label=Administration]');check('Client-only navigation excludes inquiries website and accounts',nav.get_by_role('link',name='Inquiries',exact=True,include_hidden=True).count()==0 and nav.get_by_role('link',name='Portfolio',exact=True,include_hidden=True).count()==0 and nav.get_by_role('link',name='Administrators',exact=True,include_hidden=True).count()==0)
    client=requests.Session();submit(client,BASE+'/existing/admin/index.php?view=login','login',{'email':'client-staff@example.invalid','password':STAFF})
    submit(client,BASE+'/existing/admin/index.php?view=studio-job&id=1','studio_message',{'body':'Client support staff can update this project.'})
    check('Client-only staff can save authorized project updates',bool(db('SELECT id FROM ci_studio_messages WHERE job_id=1 AND body=?',['Client support staff can update this project.'])))
    for view in ['leads','projects','staff','email','studio-content']:
        check('Direct client-only staff denial '+view,client.get(BASE+'/existing/admin/index.php?view='+view).status_code==403)
    r=client.get(BASE+'/existing/admin/index.php?view=security');client.post(r.url,data={'csrf':token(r),'action':'project_delete','id':'1'})
    check('Client-only account cannot forge website mutations',bool(db('SELECT id FROM ci_projects WHERE id=1')))
    # Profile changes in the browser invalidate existing server sessions.
    p.goto(url);edit=p.locator('form').filter(has=p.locator('[name=action][value=staff_save]')).filter(has=p.locator('[name=id][value="'+str(uid)+'"]'))
    edit.locator('[name=enabled]').uncheck();edit.locator('[name=owner_password]').fill(OWNER);edit.locator('button').click();expect(edit.locator('[name=enabled]')).not_to_be_checked()
    s.reload();expect(s.locator('[name=email]')).to_be_visible();check('Browser deactivation revokes existing administrator session',True)
    r=submit(web,BASE+'/existing/admin/index.php?view=login','login',{'email':'website-staff@example.invalid','password':STAFF});check('Disabled administrator password cannot sign in','email or password is not correct' in r.text)
    edit.locator('[name=enabled]').check();edit.locator('[name=owner_password]').fill(OWNER);edit.locator('button').click();expect(edit.locator('[name=enabled]')).to_be_checked()
    invite=p.locator('form').filter(has=p.locator('[name=action][value=staff_invite]')).filter(has=p.locator('[name=id][value="'+str(uid)+'"]'));invite.locator('[name=owner_password]').fill(OWNER);invite.locator('button').click();expect(p.locator('[data-staff-invite]')).to_be_visible()
    recovered=p.locator('[data-staff-invite]').get_attribute('href');check('Recovery immediately retires previous password',db('SELECT password_hash FROM ci_users WHERE id=?',[uid])[0]['password_hash']=='')
    s.goto(recovered);s.locator('[name=password]').fill(STAFF);s.locator('[name=confirm_password]').fill(STAFF);s.get_by_role('button',name='Set up account').click();expect(s.locator('[name=email]')).to_be_visible()
    check('Owner-issued recovery restores completed setup',db('SELECT password_hash FROM ci_users WHERE id=?',[uid])[0]['password_hash']!='')
    # Forbidden delegation cannot smuggle management capabilities into explicit grants.
    r=client.get(BASE+'/existing/admin/index.php?view=security');client.post(r.url,data={'csrf':token(r),'action':'staff_save','id':cid,'name':'Attack','email':'client-staff@example.invalid','role':'owner','enabled':'1','owner_password':STAFF})
    check('Administrator cannot promote itself through forged POST',db('SELECT role FROM ci_staff_profiles WHERE user_id=?',[cid])[0]['role']=='administrator')
    chosen_form(admin,url,'staff_save','id',cid,{'permissions[]':[],'owner_password':OWNER})
    r=client.get(BASE+'/existing/admin/index.php?view=studio-clients');check('Removing grants revokes the active session','view=login' in r.url)
    r=submit(client,BASE+'/existing/admin/index.php?view=login','login',{'email':'client-staff@example.invalid','password':STAFF})
    check('No-grant staff can sign in to own account only','view=security' in r.url and client.get(BASE+'/existing/admin/index.php?view=studio-clients').status_code==403)
    check('Staff browser has no uncaught page errors',not errors)
    check('Setup secret absent from server request log',fixture['code'] not in (QA/'http-server.log').read_text())
    (QA/'staff-browser-diagnostics.json').write_text(json.dumps({'page_errors':errors},indent=2))
    owner.close();staff.close();plain.close()
