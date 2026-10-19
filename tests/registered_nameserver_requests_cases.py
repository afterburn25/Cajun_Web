"""Actual installed client/owner forms after both original nameserver callbacks.

Only the local synthetic TLS provider is used. Domain 14701 and its unresolved
history remain untouched; 14702 is discovered and assigned through real forms.
No SQL writes, provider timestamp edits, quota resets or fabricated business rows.
"""
import hashlib
import json
import math
import re
import time
from types import SimpleNamespace
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from registered_nameservers_cases import EMAIL, PASSWORD, NS, cooldown_seconds
from staff_cases import OWNER, STAFF

CAPTURES = tuple('Client-Nameserver-Request-' + state + '-' + size + '.png'
                 for state in ('Client-Ready', 'Owner-Review', 'Owner-Uncertain', 'Client-Observed')
                 for size in ('Desktop', 'Mobile'))
DOMAIN_ID = 14702
DOMAIN = 'client-request-nameservers.invalid'
DESIRED = ['ns1.client-request.invalid', 'ns2.client-request.invalid']


def run_browser(env, browser):
    soup, token, form_data, submit = (env[k] for k in ('soup', 'token', 'form_data', 'submit'))
    base, qa, provider = env['BASE'], env['QA'], env['provider_server']
    admin, portal = base + '/existing/admin/index.php', base + '/existing/portal/index.php'
    settings = admin + '?view=provider-settings'
    owner_url, client_url = admin + '?view=nameserver-requests', portal + '?nameserver_requests=1'
    prefix = 'provider_nameserver_request_'
    sessions, contexts, checks, captures, errors = [], [], [], [], []
    original = (list(provider.registrar_domains), provider.registrar_mode, dict(provider.nameservers),
                provider.nameserver_mode, provider.nameserver_change_mode, provider.nameserver_client_request_enabled)
    old_posts, old_change_posts = list(provider.posts), list(provider.nameserver_change_posts)
    old_times = db('SELECT id,started_at,started_epoch FROM ci_provider_refreshes ORDER BY seq')
    old_ledger = db('SELECT * FROM ci_provider_operations ORDER BY id')
    old_history = {table: db('SELECT * FROM ci_' + table + ' ORDER BY id') for table in (
        'provider_nameserver_change_requests', 'provider_nameserver_change_terminals', 'provider_nameserver_change_checks')}
    old_assignment = db('SELECT * FROM ci_provider_registrar_links WHERE domain_id=14701')

    def check(name, passed):
        if type(passed) is not bool or any(row['name'] == name for row in checks):
            raise AssertionError('Unique strict client nameserver outcome required')
        checks.append({'name': name, 'passed': passed}); env['check'](name, passed)
        if not passed:
            raise AssertionError(name)

    def session():
        result = requests.Session(); sessions.append(result); return result

    def exists(table):
        return bool(db('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?', ['ci_' + table]))

    def state():
        names = ('provider_registrar_ns_requests_events', 'provider_nameserver_change_requests', 'provider_nameserver_change_terminals',
                 'provider_nameserver_change_checks', 'provider_operation_grants', 'provider_operations', 'provider_registrar_links',
                 'registered_nameserver_bindings', 'registered_nameserver_sets', 'provider_refreshes', 'provider_refresh_results')
        rows = {name: db('SELECT * FROM ci_' + name + ' ORDER BY id') if exists(name) else None for name in names}
        rows['settings'] = db('SELECT * FROM ci_settings WHERE BINARY LEFT(name,?)=BINARY ? ORDER BY name', [len('registered_nameserver'), 'registered_nameserver'])
        rows['audit'] = db('SELECT * FROM ci_audit WHERE BINARY LEFT(action,?)=BINARY ? ORDER BY id', [len('provider.registered_nameserver'), 'provider.registered_nameserver'])
        return hashlib.sha256(json.dumps(rows, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

    def no_effect(name, before, calls, posts):
        check(name, state() == before and provider.calls == calls and provider.posts == posts)

    def fields(response, selector):
        item = soup(response).select_one(selector)
        if item is None:
            raise AssertionError('Expected installed client nameserver form missing')
        return form_data(item)

    def current(session_value, url):
        response = session_value.get(url, timeout=25)
        if response.status_code != 200:
            raise AssertionError('Installed nameserver request page unavailable')
        return response

    def wait_read():
        latest = int(db('SELECT MAX(started_epoch) AS latest FROM ci_provider_refreshes WHERE profile_id=?', [profile['id']])[0]['latest'] or 0)
        seconds = cooldown_seconds(latest, int(time.time()))
        if seconds:
            time.sleep(seconds)

    def page_for(session_value):
        context = browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='reduce'); contexts.append(context)
        context.add_cookies([{'name': c.name, 'value': c.value, 'url': base} for c in session_value.cookies])
        page = context.new_page(); page.on('pageerror', lambda error: errors.append(type(error).__name__))
        return page

    def navigate(page, url):
        response = page.goto(url, wait_until='load', timeout=30000)
        if response is None or response.status != 200:
            raise AssertionError('Installed nameserver request browser navigation failed')
        return SimpleNamespace(text=page.content(), url=page.url, status_code=response.status)

    def click(page, selector, values, label):
        item = page.locator(selector)
        if item.count() != 1:
            raise AssertionError('Exactly one current nameserver request form required')
        item.evaluate('el=>{for(let p=el;p;p=p.parentElement)if(p.tagName==="DETAILS")p.open=true}')
        for name, value in values.items():
            field = item.locator('[name="' + name + '"]')
            if name in ('confirmed', 'reviewed'):
                field.set_checked(value == '1')
            else:
                field.fill(value)
        with page.expect_navigation(wait_until='load', timeout=30000) as navigation:
            item.get_by_role('button', name=label, exact=True).click()
        response = navigation.value
        if response is None or response.status != 200:
            raise AssertionError('Installed nameserver request form navigation failed')
        return SimpleNamespace(text=page.content(), url=page.url, status_code=response.status)

    def request_id(page):
        value = parse_qs(urlparse(page.url).query).get('request_id', [''])[0]
        if not re.fullmatch('[a-f0-9]{32}', value):
            raise AssertionError('Exact new request redirect required')
        return value

    def capture(page, title, selector):
        for width, height, size in ((1440, 1000, 'Desktop'), (320, 740, 'Mobile')):
            page.set_viewport_size({'width': width, 'height': height})
            page.evaluate('document.fonts.ready'); page.keyboard.press('Escape'); page.evaluate('document.activeElement?.blur()')
            if size == 'Mobile' and page.locator('.admin-sidebar').count():
                expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
            target = page.locator(selector)
            target.locator('details').evaluate_all('items=>items.forEach(el=>el.open=true)')
            check('Client nameserver ' + title + ' ' + size + ' original layout fits', not errors and page.evaluate('document.documentElement.scrollWidth<=innerWidth+1') is True)
            measured = target.evaluate('el=>({height:el.getBoundingClientRect().height,header:document.querySelector(".admin-topbar,.site-header")?.getBoundingClientRect().height||0})')
            if not (0 < measured['height'] <= 12000 and 0 <= measured['header'] < 500):
                raise AssertionError('Bounded request capture required')
            capture_height = max(height, math.ceil(measured['height'] + measured['header'] + 48))
            try:
                page.set_viewport_size({'width': width, 'height': capture_height})
                target.evaluate('el=>{const h=document.querySelector(".admin-topbar,.site-header")?.getBoundingClientRect().height||0;window.scrollTo({top:Math.max(0,scrollY+el.getBoundingClientRect().top-h-16),left:0,behavior:"instant"})}')
                ready = '''el=>{const r=el.getBoundingClientRect(),header=document.querySelector('.admin-topbar,.site-header'),h=header?header.getBoundingClientRect().bottom:0;
                    const visible=n=>{let b=n.getBoundingClientRect();return b.width>0&&b.height>0};
                    const hit=n=>{let b=n.getBoundingClientRect(),p=document.elementFromPoint(b.left+b.width/2,b.top+b.height/2);return b.left>=-1&&b.right<=innerWidth+1&&b.top>=0&&b.bottom<=innerHeight&&p&&(p===n||n.contains(p))};
                    return r.left>=-1&&r.right<=innerWidth+1&&r.top>=Math.max(0,h)+8&&r.bottom<=innerHeight-8
                      &&[...el.querySelectorAll('h2,h3,h4,summary,button,input[type=checkbox]')].filter(visible).every(hit); }'''
                page.wait_for_function(ready, arg=target.element_handle(), timeout=30000)
                check('Client nameserver ' + title + ' ' + size + ' scoped capture unobscured', target.evaluate(ready) is True)
                name = 'Client-Nameserver-Request-' + title + '-' + size + '.png'
                target.screenshot(path=str(qa / name), animations='disabled', timeout=30000)
                captures.append({'name': name, 'mode': 'locator', 'layout_viewport': {'width': width, 'height': height},
                                 'capture_viewport': {'width': width, 'height': capture_height}, 'viewport_box': target.bounding_box(),
                                 'coverage': 'Complete scoped target at original width; taller capture only after original layout checks.'})
            finally:
                page.set_viewport_size({'width': width, 'height': height})

    try:
        check('Client nameserver continuation refuses existing captures', all(not (qa / n).exists() and not (qa / n).is_symlink() for n in CAPTURES))
        check('Client nameserver continuation owns only original disposable ci database', db('SELECT DATABASE() AS name') == [{'name': 'cwp_test'}])
        check('Client nameserver new domain and request history begin absent', not db('SELECT id FROM ci_provider_registrar_links WHERE domain_id=?', [DOMAIN_ID]) and not exists('provider_registrar_ns_requests_events') and all(str(r.get('id')) != str(DOMAIN_ID) for r in original[0]))
        check('Client nameserver continuation retains original unresolved second request', len(old_assignment) == 1 and len(old_history['provider_nameserver_change_requests']) == 2 and len(old_change_posts) >= 2)
        owner, client, other, staff = session(), session(), session(), session()
        submit(owner, admin + '?view=login', 'login', {'email': EMAIL, 'password': PASSWORD})
        submit(client, portal, 'login', {'email': 'registrar-client1@example.invalid', 'password': OWNER})
        submit(other, portal, 'login', {'email': 'registrar-client2@example.invalid', 'password': OWNER})
        submit(staff, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
        client_id = db('SELECT id FROM ci_studio_clients WHERE email=?', ['registrar-client1@example.invalid'])[0]['id']
        profile = db('SELECT id,revision FROM ci_provider_profiles WHERE id=?', [old_assignment[0]['profile_id']])[0]
        owner_id = db('SELECT id FROM ci_users WHERE email=?', [EMAIL])[0]['id']
        check('Client nameserver inherited owner has room for exactly five reads', int(db('SELECT COUNT(*) AS n FROM ci_provider_refreshes WHERE actor_id=? AND started_epoch>=?', [owner_id, int(time.time())-3600])[0]['n']) <= 15)
        provider.registrar_domains = [r for r in original[0] if str(r.get('id')) not in ('14701', str(DOMAIN_ID))] + [{'id':14701,'name':'nameservers-client.invalid'}, {'id': DOMAIN_ID, 'name': DOMAIN}]
        provider.registrar_mode = 'success'; provider.nameservers = {**original[2], str(DOMAIN_ID): list(NS)}
        provider.nameserver_mode = 'success'; provider.nameserver_change_mode = 'uncertain_unlanded'; provider.nameserver_client_request_enabled = True
        wait_read(); gets = len(provider.get_paths)
        submit(owner, settings, 'provider_inventory_registrar_refresh', {'current_password': PASSWORD})
        chosen_form(owner, settings, 'provider_inventory_registrar_link', 'domain_id', DOMAIN_ID, {'client_id': client_id, 'current_password': PASSWORD})
        links = db('SELECT * FROM ci_provider_registrar_links WHERE domain_id=?', [DOMAIN_ID])
        check('Client request domain is discovered and assigned by real owner forms', len(links)==1 and links[0]['client_id']==client_id and provider.get_paths[gets:]==['/domain'] and provider.posts==old_posts)
        link = links[0]
        before,calls,posts=state(),provider.calls,list(provider.posts)
        response=current(owner,owner_url)
        check('Client request GET offers explicit setup without provider work', bool(soup(response).select_one('[data-nameserver-request-prepare]')))
        no_effect('Client request setup GET preserves history and provider calls',before,calls,posts)
        for override,label in (({'csrf':'bad'},'CSRF'),({'current_password':'wrong'},'password')):
            response=submit(owner,owner_url,prefix+'prepare',{'current_password':PASSWORD,**override})
            no_effect('Client request setup refuses '+label,before,calls,posts)
        submit(owner,owner_url,prefix+'prepare',{'current_password':PASSWORD})
        check('Client request setup creates empty native event history without network', exists('provider_registrar_ns_requests_events') and not db('SELECT * FROM ci_provider_registrar_ns_requests_events') and provider.calls==calls and provider.posts==posts)
        chosen_form(owner,admin+'?view=support','support_policy','client_id',client_id,{'allowed':'1','owner_password':PASSWORD})
        supported=chosen_form(owner,admin+'?view=support','support_start','client_id',client_id,{})
        support_id=parse_qs(urlparse(supported.url).query).get('support',[''])[0]
        before,calls,posts=state(),provider.calls,list(provider.posts)
        check('Client nameserver support page is excluded before client normalization',bool(re.fullmatch('[a-f0-9]{32}',support_id)) and owner.get(client_url+'&support='+support_id,timeout=20).status_code==403)
        no_effect('Client nameserver support exclusion has no domain effect',before,calls,posts)
        submit(owner,portal+'?support='+support_id,'support_end')
        chosen_form(owner,admin+'?view=support','support_policy','client_id',client_id,{'allowed':None,'owner_password':PASSWORD})
        check('Ordinary staff cannot open owner client-request review',staff.get(owner_url,timeout=20).status_code==403)
        cp,op=page_for(client),page_for(owner)
        services=current(client,portal+'?services=1')
        check('Client hosting services links the actual nameserver request page', any(parse_qs(urlparse(n.get('href','')).query).get('nameserver_requests')==['1'] for n in soup(services).select('a[href]')))
        navigate(cp,client_url)
        selector='form[data-nameserver-request-create-form]:has(input[name="link_id"][value="'+link['id']+'"])'
        first=fields(SimpleNamespace(text=cp.content()),selector)
        check('Assigned client can request locally before any nameserver observation', first['link_id']==link['id'] and not db('SELECT id FROM ci_registered_nameserver_bindings WHERE link_id=?',[link['id']]))
        before,calls,posts=state(),provider.calls,list(provider.posts)
        for override,label in (({'csrf':'bad'},'CSRF'),({'confirmed':''},'confirmation')):
            response=client.post(client_url,data={**first,'desired_nameservers':'\n'.join(DESIRED),'confirmed':'1',**override},timeout=25)
            no_effect('Client intake refuses '+label,before,calls,posts)
        click(cp,selector,{'desired_nameservers':'\n'.join(DESIRED),'confirmed':'1'},'Request nameserver review');cancel_id=request_id(cp)
        event=db('SELECT client_id,kind FROM ci_provider_registrar_ns_requests_events WHERE id=?',[cancel_id])
        check('Chromium client creates its own pending local request with zero provider IO',event==[{'client_id':client_id,'kind':'request'}] and 'Awaiting studio review' in cp.content() and provider.calls==calls and provider.posts==posts)
        cancel=fields(SimpleNamespace(text=cp.content()),'[data-nameserver-request-cancel]')
        before=state();foreign=current(other,portal)
        response=other.post(client_url,data={**cancel,'csrf':token(foreign),'confirmed':'1'},timeout=25)
        no_effect('Other client cannot withdraw the original request',before,calls,posts)
        check('Other client exact detail omits original requested list',DOMAIN not in other.get(client_url+'&request_id='+cancel_id,timeout=20).text)
        click(cp,'[data-nameserver-request-cancel]',{'confirmed':'1'},'Withdraw request')
        check('Chromium client withdraws its pending request without dispatch','Request withdrawn' in cp.content() and not db('SELECT id FROM ci_provider_nameserver_change_requests WHERE id NOT IN (?,?)',[r['id'] for r in old_history['provider_nameserver_change_requests']]) and provider.calls==calls)
        navigate(cp,client_url);click(cp,selector,{'desired_nameservers':'\n'.join(DESIRED),'confirmed':'1'},'Request nameserver review');decline_id=request_id(cp)
        navigate(op,owner_url+'&request_id='+decline_id)
        check('Owner approval is withheld until a fresh complete nameserver read',op.locator('[data-nameserver-request-approve]').count()==0)
        click(op,'[data-nameserver-request-decline]',{'note':'Please confirm the new hosting provider first.','reviewed':'1','current_password':PASSWORD},'Decline request')
        navigate(cp,client_url+'&request_id='+decline_id)
        check('Owner decline and client-visible reason use actual forms','Request declined' in cp.content() and 'Please confirm the new hosting provider first.' in cp.content() and provider.calls==calls and provider.posts==posts)
        navigate(cp,client_url);cp.locator(selector+' textarea').fill('\n'.join(DESIRED));capture(cp,'Client-Ready','[data-nameserver-request-create]:has(input[name="link_id"][value="'+link['id']+'"])')
        third=fields(SimpleNamespace(text=cp.content()),selector)
        click(cp,selector,{'confirmed':'1'},'Request nameserver review');approved_id=request_id(cp)
        before=state();client.post(client_url,data={**third,'desired_nameservers':'\n'.join(DESIRED),'confirmed':'1'},timeout=25)
        no_effect('Exact client request replay adds no duplicate event or provider work',before,calls,posts)
        wait_read();gets=len(provider.get_paths)
        chosen_form(owner,settings,'provider_inventory_nameservers_refresh','link_id',link['id'],{'current_password':PASSWORD})
        check('Owner approval uses its separate fresh exact numeric nameserver GET',provider.get_paths[gets:]==['/domain/14702/nameservers'])
        navigate(op,owner_url+'&request_id='+approved_id);capture(op,'Owner-Review','#registered-nameserver-request-'+approved_id)
        approval=fields(SimpleNamespace(text=op.content()),'[data-nameserver-request-approve]');dispatch_id=approval['dispatch_id']
        before,calls,posts=state(),provider.calls,list(provider.posts)
        for override,label in (({'csrf':'bad'},'CSRF'),({'reviewed':''},'confirmation'),({'current_password':'wrong'},'password'),({'approval_review_sha256':'0'*64},'review identity')):
            sent={**approval,'reviewed':'1','current_password':PASSWORD,**override}
            response=owner.post(owner_url+'&request_id='+approved_id,data=sent,timeout=25)
            no_effect('Client-origin owner approval refuses '+label,before,calls,posts)
            if label in ('password','review identity'):
                retained=fields(response,'[data-nameserver-request-approve]')
                check('Client-origin '+label+' failure preserves review without password',all(retained[k]==sent[k] for k in ('request_id','expected_event','request_sha256','dispatch_id','approval_review_sha256')) and retained['current_password']=='' and all(not n.has_attr('checked') for n in soup(response).select('[data-nameserver-request-approve] input[type=checkbox]')))
            if label=='password':
                check('Client-origin password refusal is the actual current-password error','Your current password is not correct.' in response.text)
        # Reload explicitly after rejected stale review; the browser generates a new unused dispatch identity.
        navigate(op,owner_url+'&request_id='+approved_id);approval=fields(SimpleNamespace(text=op.content()),'[data-nameserver-request-approve]');dispatch_id=approval['dispatch_id']
        click(op,'[data-nameserver-request-approve]',{'reviewed':'1','current_password':PASSWORD},'Approve and request change')
        owner_record=db('SELECT document_json FROM ci_provider_nameserver_change_requests WHERE id=?',[dispatch_id])
        ledger=db('SELECT attempts,status FROM ci_provider_operations WHERE idempotency_key=?',['nameservers:'+dispatch_id])
        check('Chromium owner admission binds original client intent and sends once',len(owner_record)==1 and json.loads(owner_record[0]['document_json'])['client_origin']['id']==approved_id and ledger==[{'attempts':1,'status':'uncertain'}] and provider.nameserver_change_posts[len(old_change_posts):]==[{'path':'/domain/14702/nameservers','body':{'ns':DESIRED,'old-ns':NS},'raw':json.dumps({'ns':DESIRED,'old-ns':NS},separators=(',',':'))}] and provider.nameservers[str(DOMAIN_ID)]==NS)
        capture(op,'Owner-Uncertain','#registered-nameserver-request-'+approved_id)
        before,calls,posts=state(),provider.calls,list(provider.posts)
        owner.post(owner_url+'&request_id='+approved_id,data={**approval,'reviewed':'1','current_password':PASSWORD},timeout=25)
        no_effect('Exact owner client-origin replay never resends',before,calls,posts)
        navigate(cp,client_url+'&request_id='+approved_id)
        check('Client sees uncertainty with no owner send verify or password form','result not yet confirmed' in cp.content() and cp.locator('[data-nameserver-requests] form').count()==0 and cp.locator('input[type=password]').count()==0)
        for mode,disposition,text in [('success','different','A different list was reported'),('authentication','unavailable','The latest check was unavailable'),('success','observed','Requested nameservers were later reported')]:
            provider.nameserver_mode=mode
            if disposition=='observed':provider.nameservers[str(DOMAIN_ID)]=list(DESIRED)
            wait_read();gets=len(provider.get_paths);navigate(op,owner_url+'&request_id='+approved_id)
            click(op,'#nameserver-request-'+dispatch_id+' [data-nameserver-change-verify]',{'current_password':PASSWORD},'Check current nameservers')
            latest=db('SELECT c.disposition FROM ci_provider_nameserver_change_checks c JOIN ci_provider_refreshes f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1',[dispatch_id])
            check('Client-origin '+disposition+' readback returns exact original review',parse_qs(urlparse(op.url).query).get('request_id')==[approved_id] and latest==[{'disposition':disposition}] and provider.get_paths[gets:]==['/domain/14702/nameservers'] and provider.posts==posts)
            navigate(cp,client_url+'&request_id='+approved_id)
            check('Client displays independently observed '+disposition+' outcome',text in cp.content() and cp.locator('[data-nameserver-requests] form').count()==0 and 'key_fingerprint' not in cp.content() and 'private synthetic' not in cp.content())
        capture(cp,'Client-Observed','#registered-nameserver-request-'+approved_id)
        check('Client matching result does not claim worldwide propagation','does not confirm worldwide DNS propagation or prove what caused the change' in cp.content())
        check('Client nameserver callback preserves original14701 assignment and unresolved history',db('SELECT * FROM ci_provider_registrar_links WHERE domain_id=14701')==old_assignment and all({row['id']:row for row in db('SELECT * FROM ci_'+table+' ORDER BY id')}.get(row['id'])==row for table,rows in old_history.items() for row in rows))
        after_times={r['id']:r for r in db('SELECT id,started_at,started_epoch FROM ci_provider_refreshes ORDER BY seq')};after_ledger={r['id']:r for r in db('SELECT * FROM ci_provider_operations ORDER BY id')}
        check('Client nameserver callback never edits inherited timestamps or ledger rows',all(after_times.get(r['id'])==r for r in old_times) and all(after_ledger.get(r['id'])==r for r in old_ledger))
        check('Client nameserver final exactly three requests and one send',len(db("SELECT id FROM ci_provider_registrar_ns_requests_events WHERE kind='request'"))==3 and len(provider.posts)==len(old_posts)+1 and provider.posts[:len(old_posts)]==old_posts and len(provider.nameserver_change_posts)==len(old_change_posts)+1 and provider.nameserver_change_posts[:len(old_change_posts)]==old_change_posts and not provider.errors)
        check('Client nameserver browser completed all eight real captures without diagnostics',not errors and tuple(c['name'] for c in captures)==CAPTURES and all((qa/n).is_file() and (qa/n).stat().st_size>0 for n in CAPTURES))
        return {'passed':True,'scope':'Actual installed client/owner forms and separate readback through loopback synthetic TLS. No live provider, propagation or payment claim.','checks':checks,'captures':captures,'requests':[cancel_id,decline_id,approved_id],'expected_sends':1}
    finally:
        provider.registrar_domains,provider.registrar_mode,provider.nameservers,provider.nameserver_mode,provider.nameserver_change_mode,provider.nameserver_client_request_enabled=original
        for context in contexts:context.close()
        for value in sessions:value.close()
