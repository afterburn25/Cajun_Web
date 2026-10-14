"""Actual packaged HTTP/Chromium with a local verified HTTPS provider fixture only."""
import time
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
