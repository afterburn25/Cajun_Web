"""Retained 1.9 upgrade and real HTTP/Chromium privacy, activity and polling flows."""
import json,shutil,subprocess,zipfile
from urllib.parse import parse_qs,urlparse
import requests
from playwright.sync_api import expect
from release_cases import db,chosen_form
from staff_cases import OWNER,STAFF

def upgrade():
    dest=WEB/'activity-upgrade';shutil.copytree(ROOT/'test-runtime/released-1.9.0',dest)
    seed=subprocess.run([php,str(ROOT/'tests/fixture_db.php')],input=json.dumps({'operation':'seed-activity-upgrade'}),text=True,capture_output=True)
    (QA/'activity-upgrade-seed.log').write_text(seed.stdout+seed.stderr,encoding='utf-8');seed.check_returncode()
    shutil.copyfile(ROOT/'test-runtime/activity-upgrade-config.php',dest/'config.php');(dest/'.installed.php').write_text('<?php exit; ?>retained guard');(dest/'unrelated.txt').write_text('Retain unrelated file')
    private=ROOT/'test-runtime/activity-upgrade-private';preserved={p:p.read_bytes() for p in [dest/'config.php',dest/'.installed.php',dest/'unrelated.txt',private/'media/retained.php',private/'payment-master.key',*list((private/'deliveries').glob('*'))]}
    tables=['staff_profiles','staff_invitations','support_clients','support_events','studio_clients','studio_jobs','studio_messages','studio_versions','studio_invoices','catalog_products','catalog_revisions','catalog_invoice_snapshots','payment_gateways','payment_requests','payment_attempts','payment_attempt_events','payment_notifications','invoice_payments','website_releases','delivery_events','leads','projects']
    before={t:db('SELECT * FROM activityup_'+t) for t in tables};settings=db('SELECT * FROM activityup_settings');users=db('SELECT * FROM activityup_users')
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(dest)
    from structure_helpers import apply_file_update
    apply_file_update(globals(),dest)
    check('Actual 1.9 update preserves configuration guards private ZIPs media and master key',all(p.read_bytes()==b for p,b in preserved.items()))
    owner=requests.Session();root=BASE+'/activity-upgrade/admin/index.php';url=root+'?view=activity';portal=BASE+'/activity-upgrade/portal/index.php'
    submit(owner,root+'?view=login','login',{'email':'upgrade@example.invalid','password':'Synthetic upgrade passphrase 2026'})
    r=submit(owner,url,'activity_setup',{'password':'Synthetic upgrade passphrase 2026'});check('Actual 1.9 owner enables empty per-person read state','Workspace activity is enabled' in r.text and db('SELECT * FROM activityup_activity_reads')==[])
    check('Actual 1.9 activity setup adds empty preference storage',db('SELECT * FROM activityup_activity_prefs')==[])
    check('Actual 1.9 activity setup preserves retained business and delivery records',all(db('SELECT * FROM activityup_'+t)==rows for t,rows in before.items()))
    after={s['name']:s['value'] for s in db('SELECT * FROM activityup_settings')};check('Actual 1.9 activity setup preserves every original setting',all(after[s['name']]==s['value'] for s in settings))
    check('Actual 1.9 activity setup preserves passwords and auth versions',all(all(a[k]==b[k] for k in b if k!='last_login') for a,b in zip(db('SELECT * FROM activityup_users'),users)))
    client=requests.Session();submit(client,portal,'login',{'email':'retained@example.invalid','password':'Synthetic retained client passphrase'})
    r=client.get(portal+'?activity=1');published=next(v for v in before['website_releases'] if v['status']=='published');draft=next(v for v in before['website_releases'] if v['status']=='draft')
    check('Retained 1.9 client sees studio review invoice receipt and published ZIP updates',all(x in r.text for x in ['Studio message','Review published','Invoice issued','Payment history updated',published['id']]) and draft['id'] not in r.text)
    response=client.get(portal+'?download_release='+published['id']);check('Retained published website downloads unchanged after 1.9 activity upgrade',response.content==(ROOT/'test-runtime/activity-upgrade.zip').read_bytes())
    check('Retained invoice balance renders after activity setup','$375.00' in owner.get(root+'?view=invoice-payments&invoice_id=3').text)
    r=submit(owner,root+'?view=payment-settings','payments_save',{'owner_password':'Synthetic upgrade passphrase 2026'});check('Retained protected credentials decrypt after 1.9 activity upgrade','Credentials protected and saved' in r.text)

def mark(session,url,keys,state='read',**changes):
    return session.post(url,data={'csrf':token(session.get(url)),'action':'activity_mark','keys':json.dumps(keys),'state':state,**changes})

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php']})
    db('DELETE FROM ci_rate_limits');upgrade()
    check('Fresh install enables empty activity state',db("SELECT value FROM fresh_settings WHERE name='activity_schema_version'")==[{'value':'1'}] and db('SELECT * FROM fresh_activity_reads')==[])
    root=BASE+'/existing/admin/index.php';url=root+'?view=activity';portal=BASE+'/existing/portal/index.php';center=portal+'?activity=1';endpoint=BASE+'/existing/api/activity.php'
    admin=requests.Session();submit(admin,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    prior=db("SELECT value FROM ci_settings WHERE name='activity_schema_version'")
    r=admin.post(url,data={'csrf':'wrong','action':'activity_setup','password':OWNER});check('Activity setup rejects CSRF','session expired' in r.text.lower() and db("SELECT value FROM ci_settings WHERE name='activity_schema_version'")==prior)
    r=admin.post(url,data={'csrf':token(admin.get(url)),'action':'activity_setup','password':'wrong'});check('Activity setup requires owner password','current password' in r.text.lower() and db("SELECT value FROM ci_settings WHERE name='activity_schema_version'")==prior)
    r=admin.post(url,data={'csrf':token(admin.get(url)),'action':'activity_setup','password':OWNER});check('HTTP owner enables workspace activity','Workspace activity is enabled' in r.text and db("SELECT value FROM ci_settings WHERE name='activity_schema_version'")==[{'value':'1'}])
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    viewer=requests.Session();submit(viewer,root+'?view=login','login',{'email':'client-staff@example.invalid','password':STAFF})
    alice=requests.Session();bob=requests.Session()
    for session,email in [(alice,'alice@example.invalid'),(bob,'bob@example.invalid')]:submit(session,portal,'login',{'email':email,'password':'Synthetic client passphrase 2026'})
    check('Anonymous count endpoint returns no payload',requests.get(endpoint+'?audience=admin').status_code==204 and requests.get(endpoint+'?audience=client').content==b'')
    check('Client login alone cannot read staff counts',alice.get(endpoint+'?audience=admin').status_code==204)
    check('Support-only staff cannot read activity counts or center',viewer.get(endpoint+'?audience=admin').status_code==204 and viewer.get(url).status_code==403)
    check('Count endpoint rejects mutations',admin.post(endpoint+'?audience=admin').status_code==405)
    response=admin.get(endpoint+'?audience=admin',headers={'Origin':'https://untrusted.invalid'});check('Private count uses no-store cookie variation and no cross-origin permission','no-store' in response.headers['Cache-Control'] and response.headers['Vary']=='Cookie' and 'Access-Control-Allow-Origin' not in response.headers and set(response.json())=={'count','capped','url'})
    original_title=db('SELECT title FROM ci_studio_jobs WHERE id=1')[0]['title'];db('UPDATE ci_studio_jobs SET title=? WHERE id=1',['Project <img src=x onerror=alert(1)>'])
    r=submit(admin,root+'?view=studio-job&id=1','studio_message',{'body':'Activity exact-source secret body'});mid=db("SELECT id FROM ci_studio_messages WHERE body='Activity exact-source secret body'")[0]['id'];key='message:'+str(mid)
    r=alice.get(center);check('Client center lists safe metadata and escapes project title',key in r.text and '&lt;img src=x onerror=alert(1)&gt;' in r.text and '<img src=x onerror=alert(1)>' not in r.text and 'Activity exact-source secret body' not in r.text)
    check('Cross-client center and exact source stay private',key not in bob.get(center).text and bob.get(center+'&open='+key).status_code==403)
    check('Malformed source is refused',alice.get(center+'&open=message:1%20OR%201=1').status_code==403)
    with (ROOT/'site/images/icons/favicon.png').open('rb') as f:submit(admin,root+'?view=studio-job&id=1','studio_upload',files={'document':('Activity-studio-file.png',f,'image/png')})
    source_keys=[];next_url=center
    while next_url:
        page=soup(alice.get(next_url));source_keys.extend(n['data-event-key'] for n in page.select('[data-event-key]'));link=page.find('a',string='Older updates →');next_url=link['href'] if link else None
    for kind in ['message','review','file','invoice','payment','release']:
        event=next(k for k in source_keys if k.startswith(kind+':'));result=alice.get(center+'&open='+event);fragment=urlparse(result.url).fragment
        check('Activity navigation resolves exact authorized '+kind,result.status_code==200 and (soup(result).find(id=fragment) is not None if fragment else parse_qs(urlparse(result.url).query).get('release')==[event.split(':')[1]]))
    check('Listing and opening do not implicitly mark read',db('SELECT * FROM ci_activity_reads')==[] and alice.get(center+'&open='+key).status_code==200 and db('SELECT * FROM ci_activity_reads')==[])
    r=mark(alice,center,[key],csrf='wrong');check('Read mutation rejects CSRF','session expired' in r.text.lower() and db('SELECT * FROM ci_activity_reads')==[])
    r=mark(alice,center,[key]);check('Client can explicitly mark one update read','read status was saved' in r.text and len(db('SELECT * FROM ci_activity_reads'))==1)
    first=db('SELECT * FROM ci_activity_reads');mark(alice,center,[key]);check('Retried read action retains same row and timestamp',db('SELECT * FROM ci_activity_reads')==first)
    check('Unread-only view omits read update',key not in alice.get(center+'&filter=unread').text)
    mark(alice,center,[key],'unread');check('Client can explicitly mark update unread',db('SELECT * FROM ci_activity_reads')==[])
    # The HTML form captures exactly its reviewed page. A concurrent arrival is not in that set.
    viewed=alice.get(center);data=form_data(form_for(viewed,'activity_mark'));saved_keys=json.loads(data['keys'])
    submit(admin,root+'?view=studio-job&id=1','studio_message',{'body':'Arrival after reviewed activity page'});newid=db("SELECT id FROM ci_studio_messages WHERE body='Arrival after reviewed activity page'")[0]['id'];newkey='message:'+str(newid)
    alice.post(center,data=data);check('Mark visible affects only captured keys and leaves new arrival unread',newkey not in [v['event_key'] for v in db('SELECT * FROM ci_activity_reads')] and 'data-event-key="'+newkey+'"' in alice.get(center+'&filter=unread').text)
    check('Other client cannot mark a known event',bob.post(center,data={'csrf':token(bob.get(portal)),'action':'activity_mark','keys':json.dumps([key]),'state':'read'}).status_code==200 and not db("SELECT * FROM ci_activity_reads WHERE actor_type='client' AND actor_id=2"))
    submit(alice,portal+'?job=1','message',{'body':'Client activity reply'});cmid=db("SELECT id FROM ci_studio_messages WHERE body='Client activity reply'")[0]['id'];ckey='message:'+str(cmid)
    # Per-actor preferences hide an update type for that person only; the project record is unchanged.
    r=alice.post(center,data={'csrf':token(alice.get(center)),'action':'activity_prefs','hide[]':['message']})
    check('Client hides one update type','preferences were saved' in r.text and db("SELECT kind FROM ci_activity_prefs WHERE actor_type='client' AND actor_id=1")==[{'kind':'message'}])
    r=alice.get(center);check('Hidden kind leaves that client feed and unread list','data-event-key="'+key+'"' not in r.text and 'data-event-key="'+newkey+'"' not in r.text and 'data-event-key="'+ckey+'"' not in r.text)
    r=alice.get(center+'&open='+newkey);check('Hidden update cannot be opened',r.status_code==403)
    r=mark(alice,center,[newkey]);check('Hidden update cannot be marked','unavailable' in r.text and not db('SELECT id FROM ci_activity_reads WHERE event_key=?',[newkey]))
    check('Hidden kind stays in the project record and an eligible recipient feed',db("SELECT id FROM ci_studio_messages WHERE id=?",[newid])!=[] and 'data-event-key="'+ckey+'"' in member.get(url).text and 'data-event-key="'+newkey+'"' not in member.get(url).text)
    check('Preferences do not leak across client accounts',db("SELECT kind FROM ci_activity_prefs WHERE actor_id=2")==[])
    r=alice.post(center,data={'csrf':token(alice.get(center)),'action':'activity_prefs'})
    check('Re-enabling restores hidden updates',db('SELECT * FROM ci_activity_prefs')==[] and 'data-event-key="'+newkey+'"' in alice.get(center).text)
    check('Staff center includes client replies','data-event-key="'+ckey+'"' in admin.get(url).text and 'data-event-key="'+ckey+'"' in member.get(url).text)
    mark(admin,url,[ckey]);check('One staff read does not mark another staff inbox',ckey not in admin.get(url+'&filter=unread').text and ckey in member.get(url+'&filter=unread').text)
    count=alice.get(endpoint+'?audience=client').json()['count'];db("CREATE TRIGGER ci_activity_fail BEFORE INSERT ON ci_audit FOR EACH ROW BEGIN IF NEW.action LIKE 'activity.%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic activity audit failure'; END IF; END")
    try:
        r=mark(alice,center,[newkey]);check('Activity audit failure rolls back read state and hides database details','Reference:' in r.text and 'SQLSTATE' not in r.text and alice.get(endpoint+'?audience=client').json()['count']==count)
    finally:db('DROP TRIGGER ci_activity_fail')
    db('RENAME TABLE ci_activity_reads TO ci_activity_reads_offline')
    try:
        r=alice.get(endpoint+'?audience=client');check('Unavailable activity fails without fabricated zero or source content',r.status_code==503 and r.json()=={'unavailable':True})
        r=submit(alice,portal+'?job=1','message',{'body':'Business message while notifications offline'});check('Notification failure cannot block saved project messages','Message saved' in r.text and bool(db("SELECT id FROM ci_studio_messages WHERE body='Business message while notifications offline'")))
    finally:db('RENAME TABLE ci_activity_reads_offline TO ci_activity_reads')
    offline_id=db("SELECT id FROM ci_studio_messages WHERE body='Business message while notifications offline'")[0]['id'];check('Restored activity discovers source saved during outage','message:'+str(offline_id) in member.get(url).text)
    r=chosen_form(admin,root+'?view=support','support_policy','client_id',1,{'allowed':'1','owner_password':OWNER});r=chosen_form(admin,root+'?view=support','support_start','client_id',1,{});sid=parse_qs(urlparse(r.url).query)['support'][0]
    before=db('SELECT * FROM ci_activity_reads');check('Support cannot view or count client activity',admin.get(center+'&support='+sid).status_code==403 and admin.get(endpoint+'?audience=client&support='+sid).status_code==204)
    support_page=admin.get(portal+'?support='+sid);r=admin.post(center+'&support='+sid,data={'csrf':token(support_page),'action':'activity_mark','keys':json.dumps([key]),'state':'read'});check('Support cannot change client read state',db('SELECT * FROM ci_activity_reads')==before)
    # Old sources remain reachable even beyond the normal 100-message project window.
    for n in range(105):db("INSERT INTO ci_studio_messages (job_id,author_type,author_id,body,created_at) VALUES (1,'admin',1,?,?)",['Historical activity '+str(n),'2026-10-13 01:00:00'])
    r=alice.get(center+'&open='+key);check('Activity opens exact old message beyond project window',soup(r).select_one('#message-'+str(mid)) is not None and 'Activity exact-source secret body' in soup(r).select_one('#message-'+str(mid)).get_text())
    seen=[];next_url=center
    while next_url:
        page=soup(alice.get(next_url));seen.extend(n['data-event-key'] for n in page.select('[data-event-key]'));link=page.find('a',string='Older updates →');next_url=link['href'] if link else None
    check('HTTP pagination reaches older activity without duplicates',len(seen)>105 and len(set(seen))==len(seen))
    check('Unread bell count caps at 99+',alice.get(endpoint+'?audience=client').json()['count']==100 and '99+ unread' in alice.get(center).text)
    old_review=db('SELECT * FROM ci_studio_versions WHERE job_id=1 ORDER BY id LIMIT 1')[0];version=db('SELECT MAX(version_no) AS n FROM ci_studio_versions WHERE job_id=1 AND kind=?',[old_review['kind']])[0]['n']
    for n in range(101):db("INSERT INTO ci_studio_versions (job_id,kind,version_no,title,body,feedback,created_at) VALUES (1,?,?,?,?,?,?)",[old_review['kind'],version+n+1,'Later review '+str(n),'Later immutable review','', '2026-10-13 01:00:00'])
    result=alice.get(center+'&open=review:'+str(old_review['id']));old_node=soup(result).find(id='review-'+str(old_review['id']))
    check('Focused review outside newest hundred remains visible without stale decision form',result.status_code==200 and old_node is not None and old_node.find('form') is None)
    # Dual identity uses the explicitly requested audience.
    submit(admin,portal,'login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'});check('One browser can keep separate staff and client notifications',admin.get(endpoint+'?audience=client').json()['count']==100 and admin.get(endpoint+'?audience=admin').json()['url']==url)
    uid=db("SELECT id FROM ci_users WHERE email='request-staff@example.invalid'")[0]['id'];old_grants=db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[uid])[0]['permissions'];db("UPDATE ci_staff_profiles SET permissions='[]' WHERE user_id=?",[uid])
    check('Current grant revocation removes count center and exact navigation',member.get(endpoint+'?audience=admin').status_code==204 and member.get(url).status_code==403 and member.get(url+'&open='+ckey).status_code==403);db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[old_grants,uid])
    db('UPDATE ci_studio_clients SET active=0 WHERE id=1');check('Client deactivation removes count and exact navigation',alice.get(endpoint+'?audience=client').status_code==204 and alice.get(center+'&open='+key).status_code==403);db('UPDATE ci_studio_clients SET active=1 WHERE id=1')
    # A milestone revision gets a new event identity; an older acknowledgement cannot consume it.
    jobAdmin=root+'?view=studio-job&id=1'
    r=chosen_form(admin,jobAdmin,'studio_milestone','id',0,{'title':'Activity milestone revision fixture','state':'pending','target_date':''})
    mmid=db("SELECT id FROM ci_studio_milestones WHERE title='Activity milestone revision fixture'")[0]['id']
    mkey=[n['data-event-key'] for n in soup(alice.get(center)).select('[data-event-key]') if n['data-event-key'].startswith('milestone:'+str(mmid)+':')][0]
    r=mark(alice,center,[mkey])
    check('Client acknowledges the exact milestone revision','read status was saved' in r.text and bool(db('SELECT * FROM ci_activity_reads WHERE actor_type=? AND event_key=?',['client',mkey])))
    r=chosen_form(admin,jobAdmin,'studio_milestone','id',mmid,{'state':'blocked','target_date':'2027-03-01'})
    feed=alice.get(center);mkey2=[n['data-event-key'] for n in soup(feed).select('[data-event-key]') if n['data-event-key'].startswith('milestone:'+str(mmid)+':')][0]
    check('Later milestone update replaces the event identity','data-event-key="'+mkey+'"' not in feed.text and mkey2!=mkey and mkey2.startswith('milestone:'+str(mmid)+':'))
    check('Acknowledged milestone revision cannot consume the later update','data-event-key="'+mkey2+'"' in alice.get(center+'&filter=unread').text and not db('SELECT * FROM ci_activity_reads WHERE actor_type=? AND event_key=?',['client',mkey2]))
    r=mark(alice,center,[mkey]);check('Submitted stale milestone key is rejected and leaves the new revision unread','read status was saved' not in r.text and not db('SELECT * FROM ci_activity_reads WHERE event_key=?',[mkey2]))
    # Actual Chromium rendering and controlled browser clock for one-minute polling.
    ctx=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');p=ctx.new_page();p.bring_to_front();errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
    p.goto(root+'?view=login');p.locator('[name=email]').fill('owner@example.invalid');p.locator('[name=password]').fill(OWNER);p.locator('form button').click();p.wait_for_url('**/admin/index.php?view=dashboard');p.goto(url);expect(p.locator('.activity-bell')).to_be_visible();p.screenshot(path=str(QA/'Activity-Admin-Desktop.png'),full_page=True)
    p.set_viewport_size({'width':320,'height':844});p.goto(url);p.wait_for_load_state('networkidle');p.evaluate('document.fonts.ready');p.screenshot(path=str(QA/'Activity-Admin-Narrow.png'),full_page=True);check('Admin activity center and bell fit 320px',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    p.goto(BASE+'/existing/index.php');expect(p.locator('.activity-bell')).to_be_visible();check('Authenticated administrator gets public-site workspace bell',p.locator('.activity-bell').get_attribute('href')==url)
    p.goto(portal);p.locator('[name=email]').fill('alice@example.invalid');p.locator('[name=password]').fill('Synthetic client passphrase 2026');p.locator('form button').click();p.wait_for_url('**/portal/index.php');p.goto(center);expect(p.locator('.activity-bell')).to_have_text('🔔 99+');p.wait_for_load_state('networkidle');p.evaluate('document.fonts.ready');p.screenshot(path=str(QA/'Activity-Client-Narrow.png'),full_page=True);check('Client activity fits 320px and bell opens client center',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1') and p.locator('.activity-bell').get_attribute('href')==center)
    p.set_viewport_size({'width':1440,'height':1000});p.screenshot(path=str(QA/'Activity-Client-Desktop.png'),full_page=True)
    calls=[];p.on('request',lambda request:calls.append(request.url) if '/api/activity.php?' in request.url else None);p.clock.install();p.goto(center);expect(p.locator('.activity-bell')).to_be_visible();initial=len(calls)
    p.evaluate("window.dispatchEvent(new Event('focus'));document.dispatchEvent(new Event('visibilitychange'))");p.clock.fast_forward(59000);check('Focus and early timer do not over-poll',len(calls)==initial)
    with p.expect_response('**/api/activity.php?audience=client'):p.clock.fast_forward(2000)
    check('Visible active page checks again after one minute',len(calls)==initial+1)
    p.evaluate("Object.defineProperty(document,'hidden',{configurable:true,get:()=>true})");p.clock.fast_forward(120000);check('Hidden pages stop polling',len(calls)==initial+1)
    p.evaluate("Object.defineProperty(document,'hidden',{configurable:true,get:()=>false})")
    p.route('**/api/activity.php?audience=client',lambda route:route.fulfill(status=503,body='{"unavailable":true}',content_type='application/json'))
    p.evaluate("document.dispatchEvent(new Event('visibilitychange'))");expect(p.locator('.activity-bell')).to_have_text('🔔 ?');check('Failed refresh marks established count unavailable',p.locator('.activity-bell').get_attribute('aria-label').startswith('Workspace activity unavailable'))
    p.unroute('**/api/activity.php?audience=client');p.clock.fast_forward(61000);expect(p.locator('.activity-bell')).to_have_text('🔔 99+');check('Recovered count restores live display',True)
    db('UPDATE ci_studio_clients SET auth_version=? WHERE id=1',['c'*32]);p.clock.fast_forward(61000);expect(p.locator('[data-activity-bell]')).to_be_hidden();check('Revoked client browser loses bell payload',p.locator('.activity-bell').count()==0)
    check('Activity browser emits no uncaught errors',not errors);ctx.close()
    anon=browser.new_context(viewport={'width':320,'height':844});p=anon.new_page();p.goto(BASE+'/existing/index.php');p.wait_for_load_state('networkidle');check('Anonymous public page never reveals staff bell or metadata',p.locator('.activity-bell').count()==0 and key not in p.content() and 'Project &lt;img' not in p.content());anon.close()
    db('UPDATE ci_studio_jobs SET title=? WHERE id=1',[original_title]);(QA/'activity-browser-diagnostics.json').write_text(json.dumps({'page_errors':errors,'poll_requests':len(calls),'scope':'Real HTTP/Chromium, synthetic identities, virtual browser clock; no customer messaging or live deployment'},indent=2),encoding='utf-8')
