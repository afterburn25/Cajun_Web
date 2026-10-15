"""Actual packaged HTTP/Chromium with a local verified HTTPS provider fixture only."""
import json,time
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit','provider_server']})
    db('DELETE FROM ci_rate_limits')
    root=BASE+'/existing/admin/index.php';url=root+'?view=provider-settings';secret='SYNTHETIC_RAW_GENERAL_KEY'
    check('Fresh installation prepares empty provider settings',db("SELECT value FROM fresh_settings WHERE name='provider_settings_schema_version'")==[{'value':'1'}] and not db('SELECT * FROM fresh_provider_profiles'))
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    r=submit(owner,url,'provider_settings_setup',{'current_password':OWNER,'csrf':'invalid'})
    check('Provider setup rejects invalid CSRF','session expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='provider_settings_schema_version'"))
    r=submit(owner,url,'provider_settings_setup',{'current_password':'wrong'})
    check('Provider setup requires current owner password','current password' in r.text.lower())
    before=db('SELECT * FROM ci_settings');jobs=db('SELECT * FROM ci_studio_jobs')
    r=submit(owner,url,'provider_settings_setup',{'current_password':OWNER})
    after={v['name']:v['value'] for v in db('SELECT * FROM ci_settings')}
    check('Additive provider setup preserves earlier settings and projects',soup(r).select_one('[data-provider-editor]') is not None and all(after[v['name']]==v['value'] for v in before) and db('SELECT * FROM ci_studio_jobs')==jobs and provider_server.calls==0)
    def edit(**values):
        fields=form_data(soup(owner.get(url)).select_one('[data-provider-editor]'));fields.update({'current_password':OWNER,**values});return owner.post(url,data=fields)
    r=edit(label='Studio provider fixture',general_key=secret,enabled='1')
    profile=db('SELECT * FROM ci_provider_profiles')[0];pid=profile['id'];cipher=profile['credentials']
    check('Owner HTTP saves protected key without contacting provider',profile['revision']==1 and profile['enabled']==1 and secret not in cipher and provider_server.calls==0)
    check('Provider HTML omits key ciphertext and password refill',secret not in r.text and cipher not in r.text and all(n.get('value','')=='' for n in soup(r).select('input[type=password]')))
    check('Provider page is private with no referrer','no-store' in r.headers.get('Cache-Control','') and r.headers.get('Referrer-Policy')=='no-referrer')
    r=edit(label='Preserved invalid edit',general_key='SecretMustNeverEcho',current_password='wrong')
    check('Provider error preserves name but clears key and password','Preserved invalid edit' in r.text and 'SecretMustNeverEcho' not in r.text and all(n.get('value','')=='' for n in soup(r).select('input[type=password]')) and db('SELECT * FROM ci_provider_profiles')[0]==profile)
    r=edit(label='<img src=x onerror=alert(1)>',general_key='')
    check('Provider labels are escaped in forms and history',soup(r).select_one('[data-provider-editor] [name=label]')['value']=='<img src=x onerror=alert(1)>' and not soup(r).select('img[onerror]'))
    def action(name,**values):
        current=db('SELECT revision FROM ci_provider_profiles')[0]['revision']
        return owner.post(url,data={'csrf':token(owner.get(url)),'action':'provider_settings_'+name,'profile_id':pid,'revision':str(current),'current_password':OWNER,**values})
    stale=form_data(soup(owner.get(url)).select_one('[data-provider-editor]'));stale.update(current_password=OWNER,label='Stale provider form')
    action('disable');r=owner.post(url,data=stale)
    check('Stale provider form retains original revision without overwriting','profile changed' in r.text and 'Stale provider form' in r.text and soup(r).select_one('[data-provider-editor] [name=revision]')['value']=='2' and db('SELECT revision,enabled FROM ci_provider_profiles')==[{'revision':3,'enabled':0}])
    action('enable')
    r=action('check',csrf='invalid');check('Provider check rejects invalid CSRF before request','session expired' in r.text.lower() and provider_server.calls==0 and not db('SELECT * FROM ci_provider_checks'))
    r=action('check',current_password='wrong');check('Provider check rejects password before request','current password' in r.text.lower() and provider_server.calls==0 and not db('SELECT * FROM ci_provider_checks'))
    r=action('check',revision='1');check('Provider check rejects stale settings before request','profile changed' in r.text and provider_server.calls==0 and not db('SELECT * FROM ci_provider_checks'))
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    r=member.get(url);check('Administrator cannot view owner provider settings',r.status_code==403 and 'Studio provider fixture' not in r.text and soup(r).select_one('[data-provider-editor]') is None)
    r=member.post(root+'?view=security',data={'csrf':token(member.get(root+'?view=security')),'action':'provider_settings_check','profile_id':pid,'revision':'4','current_password':STAFF})
    check('Forged administrator provider check creates no request',provider_server.calls==0 and not db('SELECT * FROM ci_provider_checks'))
    r=owner.get(url+'&support=fixture');check('Support context refuses owner provider screen',r.status_code==403 and soup(r).select_one('[data-provider-editor]') is None)
    check('Anonymous visitor has no provider settings',soup(requests.get(url)).select_one('[data-provider-editor]') is None)
    r=action('check');results=db('SELECT * FROM ci_provider_check_results')
    check('Packaged check performs one fixed verified loopback HTTPS read',provider_server.calls==1 and len(results)==1 and results[0]['state']=='verified' and results[0]['result_code']=='connected' and 'Read verified' in r.text)
    r=action('check');check('HTTP cooldown blocks a second network call','Wait 30 seconds' in r.text and provider_server.calls==1)
    for mode,code in [('authentication','authentication'),('object','unexpected_response'),('echo','protocol')]:
        provider_server.mode=mode;db('UPDATE ci_provider_checks SET started_epoch=?',[int(time.time())-31]);r=action('check')
        check('Packaged finite provider outcome '+mode,db('SELECT COUNT(*) AS n FROM ci_provider_check_results WHERE result_code=?',[code])[0]['n']==1 and secret not in r.text and 'private synthetic response' not in r.text)
    provider_server.mode='success';action('clear')
    check('HTTP clear disables key while preserving attempts',db('SELECT credentials,enabled FROM ci_provider_profiles')==[{'credentials':'','enabled':0}] and len(db('SELECT * FROM ci_provider_checks'))==4)
    r=owner.get(url);check('Edited profile clearly labels prior checks historical','Historical settings' in r.text and soup(r).select_one('[data-provider-check]') is None)
    edit(label='Owner 20i connection',general_key=secret,enabled='1')
    for path in ['app/providers/settings.php','app/providers/credentials.php','app/providers/checks.php','database/schema/provider_settings_schema.php','database/migrations/provider_settings_migrate.php','admin/controllers/providers.php','admin/views/providers.php','includes/foundation/twentyi/FixedHttpTransport.php','includes/foundation/provider/ProviderGateway.php']:
        check('Provider internal direct HTTP denial '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    context=browser.new_context(viewport={'width':1440,'height':1050});page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_url(root+'?view=dashboard');page.goto(url)
    form=page.locator('[data-provider-editor]');form.locator('[name=label]').fill('Browser 20i connection');form.locator('[name=current_password]').fill(OWNER);form.get_by_role('button',name='Save 20i settings',exact=True).click()
    expect(page.locator('[data-provider-editor] [name=label]')).to_have_value('Browser 20i connection')
    check('Chromium provider save clears secret inputs',page.locator('[name=general_key]').input_value()=='' and secret not in page.content())
    page.locator('[data-provider-check] [name=current_password]').fill('wrong');page.get_by_role('button',name='Check saved 20i connection',exact=True).click();expect(page.locator('.notice.error').first).to_contain_text('current password')
    check('Chromium check error clears confirmation password',page.locator('[data-provider-check] [name=current_password]').input_value()=='')
    db('UPDATE ci_provider_checks SET started_epoch=?',[int(time.time())-31]);calls=provider_server.calls
    page.locator('[data-provider-check] [name=current_password]').fill(OWNER);page.get_by_role('button',name='Check saved 20i connection',exact=True).click();expect(page.locator('[data-provider-history]')).to_contain_text('Read verified')
    check('Chromium explicit check reaches isolated HTTPS once',provider_server.calls==calls+1)
    check('Provider UI keeps operation capabilities unverified','remain unverified and unavailable' in page.content())
    page.screenshot(path=str(QA/'Provider-Settings-Desktop.png'),full_page=True);page.set_viewport_size({'width':320,'height':740});expect(page.locator('.admin-sidebar')).not_to_be_in_viewport();page.screenshot(path=str(QA/'Provider-Settings-Mobile.png'),full_page=True)
    check('Provider owner page fits 320px viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('Provider browser has no script errors',not errors);context.close()
    check('Provider fixture verified exact request and original TLS hostname',not provider_server.errors and provider_server.names and all(n=='api.20i.com' for n in provider_server.names))

    # Domain ownership links over a seeded observed package.
    provider_server.packages.append({'id':910,'typeRef':1,'name':'client-pack.example','names':['client-pack.example','linked-domain.example','shared.example'],'enabled':True,'created':'2000-01-01T00:00:00Z'})
    r=owner.post(url,data={'csrf':token(owner.get(url)),'action':'provider_inventory_prepare','current_password':OWNER})
    check('Packaged inventory storage prepares with domain link tables',db('SHOW TABLES LIKE "ci_provider_domain_links"')!=[] and db('SELECT value FROM ci_settings WHERE name="provider_inventory_schema_version"')==[{'value':'3'}])
    def inv_post(action_name,**values):
        current=db('SELECT revision FROM ci_provider_profiles')[0]['revision']
        return owner.post(url,data={'csrf':token(owner.get(url)),'action':action_name,'profile_id':pid,'revision':str(current),'current_password':OWNER,**values})
    refresh_response=inv_post('provider_inventory_refresh')
    (QA/'provider-first-refresh.json').write_text(json.dumps({'status':refresh_response.status_code,'results':db('SELECT state,coverage,reason,package_count FROM ci_provider_refresh_results'),'package_ids':db('SELECT package_id FROM ci_provider_observation_packages'),'fixture_errors':provider_server.errors}),encoding='utf-8')
    check('Packaged refresh records a complete observation',db('SELECT r.state FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id ORDER BY f.seq DESC LIMIT 1')[0]['state']=='complete' and db('SELECT COUNT(*) AS n FROM ci_provider_observation_packages')[0]['n']==1)
    alice_id=db('SELECT id FROM ci_studio_clients WHERE email=?',['alice@example.invalid'])[0]['id']
    alice=requests.Session();submit(alice,BASE+'/existing/portal/index.php','login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    services_url=BASE+'/existing/portal/index.php?services=1'
    inv_post('provider_inventory_link',package_id='910',client_id=str(alice_id))
    package_link=db('SELECT id FROM ci_provider_package_links WHERE package_id=910')[0]['id']
    preserved_tables=['provider_refreshes','provider_refresh_results','provider_observation_packages','provider_package_links','provider_package_link_events','provider_profiles']
    def snapshot():
        return {t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in preserved_tables}
    for old_version in ['1','2']:
        # These are actual historical table layouts populated through the HTTP APIs.
        for table in ['provider_domain_links','provider_domain_link_events']:
            assert not db('SELECT * FROM ci_'+table), 'Only empty isolated fixture tables may be replaced'
            db('DROP TABLE ci_'+table)
        if old_version=='1':
            db('ALTER TABLE ci_provider_refresh_results DROP COLUMN key_fingerprint')
            db('ALTER TABLE ci_provider_package_links DROP COLUMN key_fingerprint, DROP COLUMN profile_revision')
        db('UPDATE ci_settings SET value=? WHERE name="provider_inventory_schema_version"',[old_version])
        before=snapshot();schema_before=db('SHOW TABLES')
        r=owner.get(url);client_page=alice.get(services_url)
        check('Populated v'+old_version+' routes offer owner preparation and safe client state',r.status_code==200 and 'Prepare inventory storage' in r.text and client_page.status_code==200 and 'once the studio enables hosting inventory' in client_page.text and 'client-pack.example' not in client_page.text)
        check('Populated v'+old_version+' GET changes neither schema nor retained records',db('SHOW TABLES')==schema_before and snapshot()==before)
        r=inv_post('provider_inventory_prepare',csrf='invalid')
        check('Populated v'+old_version+' upgrade rejects invalid CSRF','session expired' in r.text.lower() and db('SHOW TABLES')==schema_before)
        r=inv_post('provider_inventory_prepare',current_password='wrong')
        check('Populated v'+old_version+' upgrade requires current owner password','current password' in r.text.lower() and db('SHOW TABLES')==schema_before)
        r=inv_post('provider_inventory_prepare')
        after=snapshot()
        projected={t:[{k:row[k] for k in before[t][i]} for i,row in enumerate(rows)] for t,rows in after.items()}
        check('Populated v'+old_version+' HTTP upgrade preserves every original record and binding',r.status_code==200 and projected==before and db('SELECT value FROM ci_settings WHERE name="provider_inventory_schema_version"')==[{'value':'3'}])
        if old_version=='1':
            client_page=alice.get(services_url)
            check('Legacy v1 client link stays suppressed after upgrade','client-pack.example' not in client_page.text and 'Details are hidden' in client_page.text)
            db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-120')
            inv_post('provider_inventory_refresh');inv_post('provider_inventory_relink',link_id=package_link)
            check('Legacy v1 link requires fresh observation and explicit approval','client-pack.example' in alice.get(services_url).text)
        else:
            check('Verified v2 link preserves authorized client visibility','client-pack.example' in alice.get(services_url).text)
        before=snapshot();inv_post('provider_inventory_prepare')
        check('Repeated v'+old_version+' HTTP preparation retains history and fingerprints',snapshot()==before)
    inv_post('provider_inventory_unlink',link_id=package_link)
    r=inv_post('provider_inventory_domain_link',package_id='910',domain='linked-domain.example',client_id=str(alice_id),current_password='wrong')
    check('Packaged domain link requires owner password','current password' in r.text.lower() and not db('SELECT * FROM ci_provider_domain_links'))
    r=inv_post('provider_inventory_domain_link',package_id='910',domain='unseen.example',client_id=str(alice_id))
    check('An unobserved domain cannot be linked',not db('SELECT * FROM ci_provider_domain_links'))
    r=inv_post('provider_inventory_domain_link',package_id='910',domain='linked-domain.example',client_id=str(alice_id))
    links=db('SELECT * FROM ci_provider_domain_links')
    check('Owner links an observed domain exactly once',len(links)==1 and links[0]['domain']=='linked-domain.example' and 'linked' in owner.get(url).text.lower())
    r=alice.get(BASE+'/existing/portal/index.php?services=1')
    check('Client portal shows only the owner-linked domain and approved package','linked-domain.example' in r.text and 'package #910' in r.text and 'client-pack.example' not in r.text and 'shared.example' not in r.text)
    bob=requests.Session();submit(bob,BASE+'/existing/portal/index.php','login',{'email':'bob@example.invalid','password':'Synthetic client passphrase 2026'})
    check('Other client portal hides the linked domain','linked-domain.example' not in bob.get(BASE+'/existing/portal/index.php?services=1').text)
    provider_server.packages[-1]['id']=911
    provider_server.packages[-1]['name']='another-client-private.example'
    provider_server.packages[-1]['names']=['another-client-private.example','linked-domain.example','shared.example']
    db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-120')
    inv_post('provider_inventory_refresh');r=owner.get(url)
    check('Moved domain owner page shows approved and observed package identities','Approved package #910' in r.text and 'package #911' in r.text and 'Unlink the old binding' in r.text)
    domain_forms=[f for f in soup(r).select('form') if f.select_one('[name=action][value=provider_inventory_domain_relink]')]
    check('Moved domain offers no silent reapproval form',not domain_forms)
    r=alice.get(services_url)
    check('Moved domain hides new package details from the client','linked-domain.example' in r.text and 'another-client-private.example' not in r.text and 'package #911' not in r.text and 'not present' in r.text)
    retained_link=db('SELECT * FROM ci_provider_domain_links');retained_events=db('SELECT * FROM ci_provider_domain_link_events')
    inv_post('provider_inventory_domain_relink',link_id=links[0]['id'])
    check('Forged moved-domain reapproval preserves original link and journal',db('SELECT * FROM ci_provider_domain_links')==retained_link and db('SELECT * FROM ci_provider_domain_link_events')==retained_events)
    check('Moved domain remains hidden from the other client','linked-domain.example' not in bob.get(services_url).text)
    for session,capture_url,title in [(owner,url,'Provider-Moved-Domain-Owner'),(alice,services_url,'Provider-Moved-Domain-Client')]:
        capture=browser.new_context(viewport={'width':1440,'height':1050})
        capture.add_cookies([{'name':c.name,'value':c.value,'domain':c.domain,'path':c.path} for c in session.cookies])
        rendered=capture.new_page();capture_errors=[];rendered.on('pageerror',lambda e:capture_errors.append(str(e)))
        for width,height,label in [(1440,1050,'Desktop'),(320,740,'Mobile')]:
            rendered.set_viewport_size({'width':width,'height':height});rendered.bring_to_front();rendered.goto(capture_url)
            expect(rendered.locator('[data-activity-bell] a.activity-bell')).to_be_visible()
            rendered.evaluate('document.fonts.ready')
            expect(rendered.locator('body')).to_contain_text('linked-domain.example')
            rendered.screenshot(path=str(QA/(title+'-'+label+'.png')),full_page=True)
            check(title+' '+label+' fits viewport',rendered.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        check(title+' has no browser errors',not capture_errors);capture.close()
    r=inv_post('provider_inventory_domain_unlink',link_id=links[0]['id'])
    check('Domain unlink removes the mapping and records the event',not db('SELECT * FROM ci_provider_domain_links') and db('SELECT event FROM ci_provider_domain_link_events ORDER BY id DESC')[0]['event']=='unlink')
    check('Unlinked domain leaves the client view','linked-domain.example' not in alice.get(BASE+'/existing/portal/index.php?services=1').text)
    check('Provider fixture stays clean after inventory reads',not provider_server.errors)
