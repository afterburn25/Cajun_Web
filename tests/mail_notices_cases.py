"""Packaged recent-notice HTTP/browser acceptance using the isolated real TLS mail fixture."""
import json,time
from urllib.parse import urljoin
import requests
from playwright.sync_api import expect
from release_cases import db,chosen_form
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['BASE','QA','check','soup','token','form_data','submit','inbox_server']})
    db('DELETE FROM ci_rate_limits')
    root=BASE+'/existing/admin/index.php';home=BASE+'/existing/';center=root+'?view=mail-notices';endpoint=BASE+'/existing/api/mail-notices.php';library=root+'?view=mail-accounts'
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    before=inbox_server.connections
    check('Fresh notice setup is ready with no invented messages',db("SELECT value FROM fresh_settings WHERE name='mail_notices_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_mail_notice_items'))
    check('Existing installation waits for explicit owner notice preparation','must prepare' in owner.get(center).text and owner.get(endpoint).status_code==204)
    for changes in [{'csrf':'bad','current_password':OWNER},{'current_password':'wrong'}]:
        r=submit(owner,library,'mail_notices_setup',changes)
        check('Notice preparation denies invalid confirmation '+str(list(changes)),not db("SELECT value FROM ci_settings WHERE name='mail_notices_schema_version'") and ('session expired' in r.text.lower() or 'current password' in r.text))
    r=submit(owner,library,'mail_notices_setup',{'current_password':OWNER})
    check('Owner prepares notices without any mailbox connection','Email notice storage is ready' in r.text and inbox_server.connections==before and not db('SELECT * FROM ci_mail_notice_items'))
    aid=db("SELECT id FROM ci_mail_accounts WHERE label='Staff inbox fixture'")[0]['id'];inbox=root+'?view=mail-inbox&account_id='+aid
    check('Never checked notice account has an explicit empty status','Not checked with these saved settings' in owner.get(center).text)
    def headers():
        db('UPDATE ci_mail_inbox_reads SET started_epoch=?',[int(time.time())-7200]);inbox_server.mode='ok'
        return submit(owner,inbox,'mail_inbox_read',{'account_id':aid,'revision':'1'})
    def age():db('UPDATE ci_mail_message_reads SET started_epoch=?',[int(time.time())-7200])
    def form(session,selector):
        r=session.get(center);f=soup(r).select_one(selector);assert f is not None,selector
        return urljoin(center,f['action']),form_data(f)
    headers();items=db('SELECT * FROM ci_mail_notice_items WHERE account_id=? ORDER BY slot',[aid])
    check('Real TLS inbox result produces two bounded local notices',len(items)==2 and [i['uid'] for i in items]==['82','81'])
    r=owner.get(center);summary=owner.get(endpoint)
    check('Notice page explains recent window and retains no subject or sender','newest 20' in r.text and 'not a live count' in r.text and 'SYNTHETIC PRIVATE' not in r.text and 'Last successful inbox check' in r.text)
    check('Assigned staff count is private bounded local metadata',summary.json()=={'count':1,'capped':False,'url':center} and 'no-store' in summary.headers.get('Cache-Control','') and 'Cookie' in summary.headers.get('Vary',''))
    # The fixture reports UID 81 as Seen, UID 82 as unseen.
    observed=inbox_server.connections
    for session in [requests.Session(),owner]:
        page=session.get(home);mount=soup(page).select_one('[data-mail-notice-bell]')
        check('Public HTML contains only a neutral hidden mail mount '+('owner' if session is owner else 'anonymous'),mount is not None and mount.has_attr('hidden') and not mount.text and aid not in page.text and items[0]['handle'] not in page.text)
    anonymous=requests.get(endpoint);check('Anonymous private count has no payload',anonymous.status_code==204 and not anonymous.content and 'no-store' in anonymous.headers.get('Cache-Control',''))
    check('Private count endpoint rejects mutations',owner.post(endpoint).status_code==405 and owner.post(endpoint).headers.get('Allow')=='GET')
    check('Support query cannot display notice metadata',owner.get(endpoint+'?support=synthetic').status_code==204 and owner.get(center+'&support=synthetic').status_code==403)
    chosen_form(owner,root+'?view=support','support_policy','client_id',1,{'allowed':'1','owner_password':OWNER})
    supported=chosen_form(owner,root+'?view=support','support_start','client_id',1,{})
    check('Actual client support page contains no email notice mount','support=' in supported.url and not soup(supported).select('[data-mail-notice-bell]'))
    support_context=browser.new_context();support_context.add_cookies([{'name':c.name,'value':c.value,'url':BASE+'/existing/'} for c in owner.cookies]);support_page=support_context.new_page();support_requests=[]
    support_page.on('request',lambda request:support_requests.append(request.url) if '/api/mail-notices.php' in request.url else None)
    support_page.goto(supported.url,wait_until='networkidle');support_page.bring_to_front()
    check('Actual support browser displays no mail bell and performs no count fetch',support_page.locator('[data-mail-notice-bell],.mail-notice-bell').count()==0 and not support_requests)
    support_context.close();submit(owner,supported.url,'support_end')
    client=requests.Session();submit(client,BASE+'/existing/portal/index.php','login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    check('Client login does not expose staff email count',client.get(endpoint).status_code==204)
    member_id=db("SELECT id FROM ci_users WHERE email='request-staff@example.invalid'")[0]['id'];original=db('SELECT permissions FROM ci_staff_profiles WHERE user_id=?',[member_id])[0]['permissions']
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    check('Global permission required despite a saved mailbox assignment',member.get(endpoint).status_code==204 and member.get(center).status_code==403)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[json.dumps(['mail.read']),member_id])
    check('Mail-only staff sees own notices without client workspace access',member.get(center).status_code==200 and member.get(endpoint).json()['count']==1 and member.get(root+'?view=activity').status_code==403)
    check('All notice GETs and count polling perform no mailbox connection',inbox_server.connections==observed)
    url,fields=form(member,'[data-mail-notice="'+items[0]['handle']+'"] [data-mail-notice-dismiss]')
    r=member.post(url,data={**fields,'csrf':'bad'});check('Notice dismissal requires CSRF','session expired' in r.text.lower() and not db('SELECT * FROM ci_mail_notice_states'))
    r=member.post(url,data={**fields,'uid':'123'});check('Dismissal rejects handle with mismatched independent identity','unavailable' in r.text and not db('SELECT * FROM ci_mail_notice_states'))
    r=member.post(url,data=fields);check('Dismissal is local to staff and changes no server flag',member.get(endpoint).json()['count']==0 and owner.get(endpoint).json()['count']==1 and db('SELECT server_seen FROM ci_mail_notice_items WHERE handle=?',[items[0]['handle']])[0]['server_seen']==0 and inbox_server.connections==observed)
    url,fields=form(owner,'[data-mail-notice="'+items[0]['handle']+'"] [data-mail-notice-open]')
    check('Exact notice GET never contacts the mailbox',owner.get(url).status_code==200 and inbox_server.connections==observed)
    r=owner.post(url,data={**fields,'notice':items[1]['handle']});check('Open notice POST must match its independent query handle','no longer matches' in r.text and inbox_server.connections==observed)
    r=owner.post(url,data={**fields,'csrf':'bad'});check('Open notice requires CSRF','session expired' in r.text.lower() and inbox_server.connections==observed)
    age();inbox_server.mode='message';r=owner.post(url,data=fields)
    check('Notice opens the exact message over actual synthetic TLS','SYNTHETIC PRIVATE BODY' in r.text and inbox_server.connections==observed+1 and 'uid=82' in r.url)
    check('Successful notice open records only local opened state',owner.get(endpoint).json()['count']==0 and db('SELECT server_seen FROM ci_mail_notice_items WHERE handle=?',[items[0]['handle']])[0]['server_seen']==0 and db('SELECT opened_at FROM ci_mail_notice_states WHERE handle=? AND actor_id=1',[items[0]['handle']])[0]['opened_at'] is not None)
    # A real account revision change makes the previously captured full tuple stale.
    db('UPDATE ci_mail_accounts SET revision=revision+1 WHERE id=?',[aid]);observed=inbox_server.connections
    check('Saved-revision change removes stale count and page entries',owner.get(endpoint).json()['count']==0 and not soup(owner.get(center)).select('[data-mail-notice]'))
    r=owner.post(url,data=fields);check('Captured stale notice cannot fetch content',r.status_code==403 and 'SYNTHETIC PRIVATE BODY' not in r.text and inbox_server.connections==observed)
    db('UPDATE ci_mail_accounts SET revision=1 WHERE id=?',[aid]);headers()
    # Revoke/expire the actual grant; labels and handles must disappear as well as counts.
    for mode,assignment in [('revoked','enabled=0'),('send only','can_read=0'),('future','issued_at='+str(int(time.time())+3600)),('expired','expires_at='+str(int(time.time())-1))]:
        saved=db('SELECT * FROM ci_mail_account_grants WHERE account_id=? AND user_id=?',[aid,member_id])[0]
        db('UPDATE ci_mail_account_grants SET '+assignment+' WHERE account_id=? AND user_id=?',[aid,member_id])
        r=member.get(center);check('Current '+mode+' grant removes count labels and handles',member.get(endpoint).status_code==204 and 'Staff inbox fixture' not in r.text and items[0]['handle'] not in r.text)
        db('UPDATE ci_mail_account_grants SET enabled=?,can_read=?,issued_at=?,expires_at=? WHERE account_id=? AND user_id=?',[saved['enabled'],saved['can_read'],saved['issued_at'],saved['expires_at'],aid,member_id])
    db('DELETE FROM ci_mail_notice_states');observed=inbox_server.connections
    context=browser.new_context(viewport={'width':1440,'height':1050});page=context.new_page();page.clock.install();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(root+'?view=login');page.locator('[name=email]').fill('request-staff@example.invalid');page.locator('[name=password]').fill(STAFF);page.locator('form button').first.click();page.wait_for_load_state('load')
    page.goto(center);page.bring_to_front();expect(page.locator('.mail-notice-bell')).to_have_text('🔔 Mail 1')
    check('Mail-only admin bell links to own notice center',page.locator('.mail-notice-bell').get_attribute('href')==center)
    page.wait_for_load_state('networkidle');page.screenshot(path=str(QA/'Mail-Notices-Desktop.png'))
    page.set_viewport_size({'width':320,'height':800});expect(page.locator('.admin-sidebar')).not_to_be_in_viewport();page.screenshot(path=str(QA/'Mail-Notices-Mobile.png'),full_page=True)
    check('Mail-only notice page fits 320px',page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    page.goto(home);page.bring_to_front();expect(page.locator('.mail-notice-bell')).to_have_text('🔔 Mail 1');page.screenshot(path=str(QA/'Mail-Notices-Public-Mobile.png'))
    check('Authenticated public header bell fits 320px',page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    page.locator('.mail-notice-bell').focus();page.keyboard.press('Enter');page.wait_for_url(center);expect(page.locator('[data-mail-notices]')).to_be_visible()
    check('Keyboard activation opens the notice page without IMAP',inbox_server.connections==observed)
    page.evaluate("window.dispatchEvent(new Event('blur'))");expect(page.locator('[data-mail-notice-bell]')).to_be_hidden()
    check('Blur immediately removes private notice display',page.locator('.mail-notice-bell').count()==0)
    page.evaluate("window.dispatchEvent(new Event('focus'))");expect(page.locator('.mail-notice-bell')).to_be_visible()
    page.route('**/api/mail-notices.php',lambda route:route.fulfill(status=503,content_type='application/json',body='{"unavailable":true}'))
    with page.expect_response(endpoint) as failed_response:page.clock.fast_forward(61000)
    expect(page.locator('[data-mail-notice-bell]')).to_be_hidden();check('Notice endpoint failure clears the old count',failed_response.value.status==503 and page.locator('.mail-notice-bell').count()==0)
    page.unroute('**/api/mail-notices.php')
    with page.expect_response(endpoint) as recovered_response:page.clock.fast_forward(61000)
    expect(page.locator('.mail-notice-bell')).to_be_visible();check('Recovered notice endpoint restores the current count',recovered_response.value.status==200)
    db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',['[]',member_id])
    with page.expect_response(endpoint) as revoked_response:page.clock.fast_forward(61000)
    expect(page.locator('[data-mail-notice-bell]')).to_be_hidden()
    check('Live permission revocation removes the public-capable bell',revoked_response.value.status==204 and page.locator('.mail-notice-bell').count()==0 and inbox_server.connections==observed)
    check('Notice browser controls produce no JavaScript errors',not errors);context.close();db('UPDATE ci_staff_profiles SET permissions=? WHERE user_id=?',[original,member_id])
    context=browser.new_context(viewport={'width':320,'height':800});page=context.new_page()
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_load_state('load')
    page.goto(center);page.bring_to_front();expect(page.locator('.mail-notice-bell')).to_be_visible();expect(page.locator('.activity-bell')).to_be_visible()
    page.screenshot(path=str(QA/'Mail-Notices-Owner-Two-Bells-Mobile.png'))
    check('Owner email and workspace bells coexist at 320px',page.locator('.mail-notice-bell').count()==1 and page.locator('.activity-bell').count()==1 and page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
    check('Owner bells keep separate authorized destinations',page.locator('.mail-notice-bell').get_attribute('href')==center and page.locator('.activity-bell').get_attribute('href')==root+'?view=activity')
    context.close()
    delayed=browser.new_context();delayed.add_cookies([{'name':c.name,'value':c.value,'url':BASE+'/existing/'} for c in owner.cookies]);pending=[];p=delayed.new_page()
    p.route('**/api/mail-notices.php',lambda route:pending.append(route));p.goto(center);p.bring_to_front();p.wait_for_timeout(150)
    check('Ordinary page show keeps a single pending count request',len(pending)==1)
    pending[0].fulfill(status=200,content_type='application/json',body=json.dumps({'count':1,'capped':False,'url':center}));expect(p.locator('.mail-notice-bell')).to_have_text('🔔 Mail 1')
    check('Pending initial count is accepted without a page-show abort',True);delayed.close()
    context=browser.new_context(viewport={'width':320,'height':800});page=context.new_page();page.goto(home);page.bring_to_front();page.wait_for_load_state('networkidle')
    check('Anonymous browser shows no mail bell',page.locator('.mail-notice-bell').count()==0 and page.locator('[data-mail-notice-bell]').is_hidden());context.close()
    for path in ['app/mail/notices.php','admin/controllers/mail_notices.php','admin/views/mail_notices.php','database/schema/mail_notices_schema.php','database/migrations/mail_notices_migrate.php','api/handlers/mail_notices.php']:
        check('Notice internal PHP path is directly denied '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    retained=json.dumps(db('SELECT * FROM ci_mail_notice_windows')+db('SELECT * FROM ci_mail_notice_items')+db('SELECT * FROM ci_mail_notice_states'))
    check('Actual TLS notice flow retains no subject body or credentials','SYNTHETIC PRIVATE' not in retained and 'receiving HTTP password' not in retained)
    inbox_server.mode='ok'
