"""Real installed owner/client forms and fixed TLS fixture read; no live provider."""
import json
import time
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    check, soup, form_data, form_for, submit = (env[k] for k in ['check', 'soup', 'form_data', 'form_for', 'submit'])
    base, qa, provider = env['BASE'], env['QA'], env['provider_server']
    admin, portal = base+'/existing/admin/index.php', base+'/existing/portal/index.php'
    url, client_url = admin+'?view=provider-settings', portal+'?domains=1'
    owner=requests.Session();submit(owner,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
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
    form=page.locator('[data-registration-period-refresh]');form.locator('[name=current_password]').fill(OWNER);form.get_by_role('button',name='Refresh registration options',exact=True).click()
    expect(page.locator('[data-registration-periods]')).to_contain_text('1 year, 3 years')
    check('Chromium owner action makes one fixed authenticated period GET',provider.calls==calls+1 and provider.get_paths[-1]=='/domain-period' and provider.posts==posts)
    check('Period read preserves every explicit client assignment',all(db('SELECT * FROM ci_'+t+' ORDER BY id')==v for t,v in assignments.items()))
    rows=db('SELECT * FROM ci_provider_observation_registration_periods');check('Period observation persists bounded typed years without secrets',len(rows)==1 and json.loads(rows[0]['document_json'])['state']=='complete' and 'SYNTHETIC_RAW_GENERAL_KEY' not in json.dumps(rows))
    submit(owner,url,'provider_inventory_period_refresh',{'current_password':OWNER});check('Repeated period action respects shared cooldown',provider.calls==calls+1 and len(db('SELECT * FROM ci_provider_registration_period_reads'))==1)
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
    latest=db('SELECT s.document_json FROM ci_provider_observation_registration_periods s JOIN ci_provider_refreshes r ON r.id=s.id ORDER BY r.seq DESC LIMIT 1')[0]
    check('Actual COMPLETE-empty response stays explicit saved empty',json.loads(latest['document_json'])['periods']==[] and json.loads(latest['document_json'])['state']=='complete' and 'No registration period options were returned' in empty.text and provider.calls==calls+3)
    stale=form_data(form_for(owner.get(url),'provider_inventory_period_refresh'));edit=form_data(form_for(owner.get(url),'provider_settings_save'));owner.post(url,data={**edit,'label':'Registration options changed connection','general_key':'','enabled':'1','current_password':OWNER})
    rejected=owner.post(url,data={**stale,'current_password':OWNER});check('Changed profile refuses stale period action before network',provider.calls==calls+3 and 'provider settings changed' in soup(rejected).select_one('[data-registration-periods]').text.lower() and soup(rejected).select_one('[data-registration-period-refresh] [name=period_revision]')['value']==stale['period_revision'])
    check('Changed profile invalidates prior client search policy projection',soup(client.get(client_url)).select_one('[data-client-registration-periods]') is None)
    for path in ['app/providers/registration_periods.php','app/providers/registration_period_storage.php','app/providers/registration_period_views.php','database/schema/provider_registration_period_schema.php','admin/controllers/provider_registration_periods.php','admin/views/provider_registration_periods.php','includes/foundation/twentyi-inventory/RegistrationPeriod.php','includes/foundation/twentyi-inventory-reader/RegistrationPeriods.php']:
        check('Registration period internal HTTP denial '+path,requests.get(base+'/existing/'+path).status_code==404)
    check('Registration period browser fixture stays clear and performs no writes',not errors and not provider.errors and provider.posts==posts and all(n.get('value','')=='' for n in soup(rejected).select('[data-registration-periods] input[type=password]')))
    (qa/'registration-period-browser-diagnostics.json').write_text(json.dumps({'errors':errors,'fixture_errors':provider.errors},indent=2))
    context.close();owner.close();client.close();member.close()
