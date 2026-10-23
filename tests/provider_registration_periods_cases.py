"""Real installed owner/client forms and fixed TLS fixture read; no live provider."""
import hashlib
import json
import threading
import time
import requests
from urllib.parse import parse_qs, urlparse
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    check, soup, form_data, form_for, submit = (env[k] for k in ['check', 'soup', 'form_data', 'form_for', 'submit'])
    base, qa, provider = env['BASE'], env['QA'], env['provider_server']
    admin, portal = base+'/existing/admin/index.php', base+'/existing/portal/index.php'
    url, client_url = admin+'?view=provider-settings', portal+'?domains=1'
    # Earlier workflows legitimately consume this owner's 20/hour read budget.
    # Create/activate a separate synthetic owner through the existing staff UI;
    # retain every original owner, audit and immutable intent without aging it.
    inherited=db('SELECT * FROM ci_provider_refreshes ORDER BY seq')
    inherited_audits=db('SELECT * FROM ci_audit ORDER BY id')
    original_owner=db('SELECT * FROM ci_users WHERE email=?',['owner@example.invalid'])[0]
    original_profile=db('SELECT * FROM ci_staff_profiles WHERE user_id=?',[original_owner['id']])[0]
    before_setup_owner=original_owner
    setup=requests.Session();login=submit(setup,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    # A real login updates last_login. Check every authority field across it,
    # then retain the entire post-login row for the rest of this workflow.
    original_owner=db('SELECT * FROM ci_users WHERE id=?',[original_owner['id']])[0]
    setup_diagnostics={'login':{'status':login.status_code,'redirect_statuses':[r.status_code for r in login.history],
        'dashboard':urlparse(login.url).path==urlparse(admin).path and urlparse(login.url).query=='view=dashboard',
        'changed_fields':sorted(k for k in set(before_setup_owner)|set(original_owner) if before_setup_owner.get(k)!=original_owner.get(k)),
        'profile_unchanged':db('SELECT * FROM ci_staff_profiles WHERE user_id=?',[original_owner['id']])==[original_profile]}}
    (qa/'registration-period-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics,indent=2))
    check('Period setup login preserves all account authority and updates only login metadata',
          login.status_code==200 and setup_diagnostics['login']['dashboard']
          and {k:v for k,v in original_owner.items() if k!='last_login'}=={k:v for k,v in before_setup_owner.items() if k!='last_login'}
          and original_owner['last_login'] is not None
          and setup_diagnostics['login']['profile_unchanged'])
    staff_url=admin+'?view=staff';period_email='period-owner@example.invalid'
    check('Period fixture owner starts absent',not db('SELECT id FROM ci_users WHERE email=?',[period_email]))
    submit(setup,staff_url,'staff_save',{'id':'0','name':'Registration period owner','email':period_email,'role':'owner','enabled':'1','permissions[]':[],'owner_password':OWNER})
    period_owner=db('SELECT id,password_hash FROM ci_users WHERE email=?',[period_email])[0]
    invite=chosen_form(setup,staff_url,'staff_invite','id',period_owner['id'],{'owner_password':OWNER})
    link=soup(invite).select_one('[data-staff-invite]')['href'];code=parse_qs(urlparse(link).fragment)['token'][0]
    activation=requests.Session();form=activation.get(link)
    activated=activation.post(admin+'?view=staff-setup',data={'action':'staff_activate','csrf':env['token'](form),'token':code,'password':OWNER,'confirm_password':OWNER})
    setup_diagnostics['activation']={'status':activated.status_code,'redirect_statuses':[r.status_code for r in activated.history],
        'login_page':urlparse(activated.url).path==urlparse(admin).path and urlparse(activated.url).query=='view=login','distinct_actor':period_owner['id']!=original_owner['id'],
        'authorized_owner':db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?',[period_owner['id']])==[{'role':'owner','enabled':1}],
        'password_set':db('SELECT password_hash FROM ci_users WHERE id=?',[period_owner['id']])[0]['password_hash']!='',
        'original_account_unchanged':db('SELECT * FROM ci_users WHERE id=?',[original_owner['id']])==[original_owner],
        'original_profile_unchanged':db('SELECT * FROM ci_staff_profiles WHERE user_id=?',[original_owner['id']])==[original_profile]}
    (qa/'registration-period-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics,indent=2))
    activation.close();setup.close()
    check('Period fixture activates a distinct authorized owner without changing original authority',
          activated.status_code==200 and setup_diagnostics['activation']['login_page']
          and period_owner['id']!=original_owner['id'] and db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?',[period_owner['id']])==[{'role':'owner','enabled':1}]
          and db('SELECT password_hash FROM ci_users WHERE id=?',[period_owner['id']])[0]['password_hash']!=''
          and db('SELECT * FROM ci_users WHERE id=?',[original_owner['id']])==[original_owner]
          and db('SELECT * FROM ci_staff_profiles WHERE user_id=?',[original_owner['id']])==[original_profile])
    owner=requests.Session();submit(owner,admin+'?view=login','login',{'email':period_email,'password':OWNER})
    check('Dedicated period owner has an unused read budget',not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?',[period_owner['id']]))
    diagnostics=[]
    def evidence(label,response):
        # No whole HTML, invitation token, CSRF, password or API key is retained.
        notices=[n.get_text(' ',strip=True) for n in soup(response).select('.notice.error')]
        notices=[n.replace(OWNER,'[redacted]').replace('SYNTHETIC_RAW_GENERAL_KEY','[redacted]') for n in notices]
        rows=db('SELECT actor_id,operation,started_epoch FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq',[period_owner['id']])
        results=db('SELECT r.state,r.reason,r.package_count FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id WHERE f.actor_id=? ORDER BY f.seq',[period_owner['id']])
        diagnostics.append({'step':label,'status':response.status_code,'redirect_statuses':[r.status_code for r in response.history],
                            'inline_errors':notices,'actor_id':period_owner['id'],'actor_reads':rows,'results':results,'provider_calls':provider.calls})
        (qa/'registration-period-response-diagnostics.json').write_text(json.dumps(diagnostics,indent=2))
    calls, posts = provider.calls, list(provider.posts)
    response=owner.get(url)
    check('Registration periods GET creates no storage or provider request', response.status_code==200 and 'Prepare registration periods' in response.text and not db('SHOW TABLES LIKE "ci_provider_registration_period_reads"') and provider.calls==calls)
    for delta,label in [({'csrf':'bad','current_password':OWNER},'CSRF'),({'current_password':'wrong'},'owner password')]:
        rejected=submit(owner,url,'provider_inventory_period_prepare',delta)
        check('Registration period preparation rejects '+label,not db('SHOW TABLES LIKE "ci_provider_registration_period_reads"') and provider.calls==calls and OWNER not in rejected.text)
    submit(owner,url,'provider_inventory_period_prepare',{'current_password':OWNER})
    check('Period preparation remains an empty local observation history',db('SELECT * FROM ci_provider_registration_period_reads')==[] and provider.calls==calls)
    fields=form_data(form_for(owner.get(url),'provider_inventory_period_refresh'))
    for delta,label in [({'csrf':'bad'},'CSRF'),({'current_password':'wrong'},'password'),({'period_revision':'0'},'revision'),({'period_profile':'f'*32},'profile'),({'period_revision':[],'period_revision[]':'1'},'malformed revision')]:
        rejected=owner.post(url,data={**fields,'current_password':OWNER,**delta})
        evidence('Refused '+label,rejected)
        check('Registration period refresh rejects '+label,not db('SELECT * FROM ci_provider_registration_period_reads') and provider.calls==calls and rejected.status_code==200 and OWNER not in rejected.text)
    member=requests.Session();submit(member,admin+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    check('Registration period owner controls refuse administrator and support access',member.get(url).status_code==403 and owner.get(url+'&support=fixture').status_code==403)
    cfg=form_data(form_for(owner.get(url),'provider_inventory_domain_search_configure'));owner.post(url,data={**cfg,'search_tlds':'invalid\nco.uk','search_enabled':'1','current_password':OWNER})
    def client_policy(enabled):
        f=form_data(form_for(owner.get(url),'provider_inventory_domain_search_client_configure'))
        return owner.post(url,data={**f,'client_search_enabled':'1' if enabled else '0','current_password':OWNER})
    client_policy(True)
    client=requests.Session();submit(client,portal,'login',{'email':'domain-client1@example.invalid','password':OWNER})
    check('Client period projection makes no implicit provider request','Registration options' in client.get(client_url).text and provider.calls==calls)
    def cooldown():
        latest=db('SELECT MAX(started_epoch) AS latest FROM ci_provider_refreshes')[0]['latest'] or 0
        wait=max(0,int(latest)+31-int(time.time()))
        if wait:time.sleep(min(32,wait))
    cooldown()
    context=browser.new_context(viewport={'width':1440,'height':1050});context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in owner.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.goto(url+'#registration-periods')
    assignments={t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['provider_package_links','provider_domain_links','provider_cloud_links']}
    repeater=requests.Session()
    for cookie in owner.cookies:
        repeater.cookies.set(cookie.name,cookie.value)
    reposted={}
    def repeat_once_admitted():
        base_seq=db("SELECT COALESCE(MAX(seq),0) AS s FROM ci_provider_refreshes WHERE BINARY operation='domain.registration.periods'",[])[0]['s']
        deadline=time.time()+240
        while time.time()<deadline:
            if db("SELECT id FROM ci_provider_refreshes WHERE BINARY operation='domain.registration.periods' AND seq>?",[base_seq]):
                reposted['r']=submit(repeater,url,'provider_inventory_period_refresh',{'current_password':OWNER})
                return
            time.sleep(0.2)
    repeat_thread=threading.Thread(target=repeat_once_admitted,daemon=True);repeat_thread.start()
    form=page.locator('[data-registration-period-refresh]');form.locator('[name=current_password]').fill(OWNER);form.get_by_role('button',name='Refresh registration options',exact=True).click()
    expect(page.locator('[data-registration-periods]')).to_contain_text('1 year, 3 years')
    repeat_thread.join()
    check('Chromium owner action makes one fixed authenticated period GET',provider.calls==calls+1 and provider.get_paths[-1]=='/domain-period' and provider.posts==posts)
    check('Period read preserves every explicit client assignment',all(db('SELECT * FROM ci_'+t+' ORDER BY id')==v for t,v in assignments.items()))
    rows=db('SELECT * FROM ci_provider_observation_registration_periods');check('Period observation persists bounded typed years without secrets',len(rows)==1 and json.loads(rows[0]['document_json'])['state']=='complete' and 'SYNTHETIC_RAW_GENERAL_KEY' not in json.dumps(rows))
    repeated=reposted['r'] if 'r' in reposted else submit(owner,url,'provider_inventory_period_refresh',{'current_password':OWNER});evidence('Shared cooldown refusal',repeated)
    check('Repeated period action respects shared cooldown',provider.calls==calls+1 and len(db('SELECT * FROM ci_provider_registration_period_reads'))==1 and any('Wait 30 seconds' in n for n in diagnostics[-1]['inline_errors']))
    client_response=client.get(client_url);part=soup(client_response).select_one('[data-client-registration-periods]')
    check('Client sees only currently offered saved options and time',part is not None and '.invalid' in part.text and '.co.uk' in part.text and '.com' not in part.text and '1 year, 3 years' in part.text and 'Recorded ' in part.text and fields['period_profile'] not in client_response.text)
    check('Client period display remains private and does not imply renewal or price','no-store' in client_response.headers.get('Cache-Control','') and client_response.headers.get('Referrer-Policy')=='no-referrer' and 'do not establish a price' in part.text)
    for session,title,target,selector in [(owner,'Registration-Periods-Owner',url+'#registration-periods','[data-registration-periods]'),(client,'Registration-Periods-Client',client_url,'[data-client-registration-periods]')]:
        capture=browser.new_context(viewport={'width':1440,'height':1050});capture.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in session.cookies]);shot=capture.new_page();shot.on('pageerror',lambda e:errors.append(str(e)))
        for width,height,label in [(1440,1050,'Desktop'),(320,740,'Mobile')]:
            shot.set_viewport_size({'width':width,'height':height});shot.goto(target);shot.evaluate('document.fonts.ready');expect(shot.locator(selector)).to_be_visible();shot.locator(selector).screenshot(path=str(qa/(title+'-'+label+'.png')));check(title+' '+label+' fits viewport',shot.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        capture.close()
    client_policy(False);check('Revoked client policy removes saved registration options',soup(client.get(client_url)).select_one('[data-client-registration-periods]') is None and provider.calls==calls+1);client_policy(True)
    cooldown();provider.period_mode='authentication';failed=submit(owner,url,'provider_inventory_period_refresh',{'current_password':OWNER})
    evidence('Authentication failure outcome',failed)
    check('Failed period read retains finite evidence and no previous current options',provider.calls==calls+2 and 'private synthetic period response' not in failed.text and 'Current options are unknown' in soup(failed).select_one('[data-registration-periods]').text and '1 year, 3 years' not in soup(failed).select_one('[data-registration-periods]').text)
    # Settle the actual mobile layout before capturing the newly persisted failure.
    page.set_viewport_size({'width':320,'height':740});page.reload();page.evaluate('document.fonts.ready')
    expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
    expect(page.locator('[data-registration-periods]')).to_contain_text('Current options are unknown')
    page.locator('[data-registration-periods]').screenshot(path=str(qa/'Registration-Periods-Failure-Mobile.png'))
    # Preserve full-page context without altering rendered DOM or CSS.
    page.evaluate('window.scrollTo({top:0,left:0,behavior:"instant"})')
    page.screenshot(path=str(qa/'Registration-Periods-Failure-Mobile-Full.png'),full_page=True)
    check('Failed latest lookup also removes current client year options','1 year, 3 years' not in soup(client.get(client_url)).select_one('[data-client-registration-periods]').text)
    cooldown();provider.period_mode='success';provider.period_map={};empty=submit(owner,url,'provider_inventory_period_refresh',{'current_password':OWNER})
    evidence('Complete empty outcome',empty)
    latest=db('SELECT s.document_json FROM ci_provider_observation_registration_periods s JOIN ci_provider_refreshes r ON r.id=s.id ORDER BY r.seq DESC LIMIT 1')[0]
    check('Actual COMPLETE-empty response stays explicit saved empty',json.loads(latest['document_json'])['periods']==[] and json.loads(latest['document_json'])['state']=='complete' and 'No registration period options were returned' in empty.text and provider.calls==calls+3)
    check('Three period reads consume only the dedicated owner budget',len(diagnostics[-1]['actor_reads'])==3 and all(r['actor_id']==period_owner['id'] and r['operation']=='domain.registration.periods' for r in diagnostics[-1]['actor_reads']) and [r['state'] for r in diagnostics[-1]['results']]==['complete','unavailable','complete'])
    stale=form_data(form_for(owner.get(url),'provider_inventory_period_refresh'));edit=form_data(form_for(owner.get(url),'provider_settings_save'));owner.post(url,data={**edit,'label':'Registration options changed connection','general_key':'','enabled':'1','current_password':OWNER})
    rejected=owner.post(url,data={**stale,'current_password':OWNER});evidence('Stale profile refusal',rejected)
    check('Changed profile refuses stale period action before network',provider.calls==calls+3 and 'provider settings changed' in soup(rejected).select_one('[data-registration-periods]').text.lower() and soup(rejected).select_one('[data-registration-period-refresh] [name=period_revision]')['value']==stale['period_revision'])
    check('Changed profile invalidates prior client search policy projection',soup(client.get(client_url)).select_one('[data-client-registration-periods]') is None)
    for path in ['app/providers/registration_periods.php','app/providers/registration_period_storage.php','app/providers/registration_period_views.php','database/schema/provider_registration_period_schema.php','admin/controllers/provider_registration_periods.php','admin/views/provider_registration_periods.php','includes/foundation/twentyi-inventory/RegistrationPeriod.php','includes/foundation/twentyi-inventory-reader/RegistrationPeriods.php']:
        check('Registration period internal HTTP denial '+path,requests.get(base+'/existing/'+path).status_code==404)
    check('Registration period browser fixture stays clear and performs no writes',not errors and not provider.errors and provider.posts==posts and all(n.get('value','')=='' for n in soup(rejected).select('[data-registration-periods] input[type=password]')))
    retained=db('SELECT * FROM ci_provider_refreshes WHERE seq<=? ORDER BY seq',[max((r['seq'] for r in inherited),default=0)])
    retained_audits=db('SELECT * FROM ci_audit WHERE id<=? ORDER BY id',[max((r['id'] for r in inherited_audits),default=0)])
    check('Period workflow preserves every inherited intent and original audit byte',retained==inherited and retained_audits==inherited_audits)
    check('Period workflow preserves the original owner account and role',db('SELECT * FROM ci_users WHERE id=?',[original_owner['id']])==[original_owner] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?',[original_owner['id']])==[original_profile])
    (qa/'registration-period-history-preservation.json').write_text(json.dumps({'inherited_intents':len(inherited),'inherited_audits':len(inherited_audits),'intents_sha256':hashlib.sha256(json.dumps(inherited,sort_keys=True).encode()).hexdigest(),'audits_sha256':hashlib.sha256(json.dumps(inherited_audits,sort_keys=True).encode()).hexdigest(),'retained_intents_sha256':hashlib.sha256(json.dumps(retained,sort_keys=True).encode()).hexdigest(),'retained_audits_sha256':hashlib.sha256(json.dumps(retained_audits,sort_keys=True).encode()).hexdigest(),'dedicated_actor':period_owner['id'],'dedicated_reads':3},indent=2))
    (qa/'registration-period-browser-diagnostics.json').write_text(json.dumps({'errors':errors,'fixture_errors':provider.errors},indent=2))
    context.close();owner.close();client.close();member.close()
