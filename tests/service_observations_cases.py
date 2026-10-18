"""Actual packaged hosting history routes and Chromium; local TLS provider only."""
import json
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from cloud_assignment_fixture import age_unprotected_refreshes
from staff_cases import OWNER

def run_browser(env,browser):
    check,soup,form_data,form_for,submit=(env[k] for k in ['check','soup','form_data','form_for','submit'])
    base,qa,provider=env['BASE'],env['QA'],env['provider_server'];admin=base+'/existing/admin/index.php';portal=base+'/existing/portal/index.php';settings=admin+'?view=provider-settings';url=portal+'?service_history=1'
    owner=requests.Session();submit(owner,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER});db('DELETE FROM ci_rate_limits')
    client=requests.Session();submit(client,portal,'login',{'email':'registrar-client1@example.invalid','password':OWNER})
    other=requests.Session();submit(other,portal,'login',{'email':'registrar-client2@example.invalid','password':OWNER})
    cid=db('SELECT id FROM ci_studio_clients WHERE email=?',['registrar-client1@example.invalid'])[0]['id'];profile=db('SELECT * FROM ci_provider_profiles')[0]
    calls,posts=provider.calls,list(provider.posts);prepare='provider_inventory_history_prepare';change='provider_inventory_history_change';old_packages=list(provider.packages);old_mode=provider.mode
    check('Hosting history GET has no implicit setup or provider call',prepare in owner.get(settings).text and not db('SHOW TABLES LIKE "ci_service_observation_permissions"') and 'No hosting history is shared' in client.get(url).text and provider.calls==calls)
    for fields,label in [({'csrf':'bad','current_password':OWNER},'CSRF'),({'current_password':'wrong'},'password')]:
        submit(owner,settings,prepare,fields);check('Hosting history preparation refuses '+label,not db('SHOW TABLES LIKE "ci_service_observation_permissions"') and provider.calls==calls)
    submit(owner,settings,prepare,{'current_password':OWNER});check('Hosting history preparation is local and explicit',not db('SELECT * FROM ci_service_observation_permissions') and provider.calls==calls)
    package={'id':2701,'typeRef':1,'name':'before-history.invalid','names':['before-history.invalid'],'enabled':True,'created':'2026-01-01T00:00:00Z','packageTypeName':'Linux Hosting','stackUsers':['stack-user:987'],'packageLabels':['HISTORY PRIVATE LABEL']}
    provider.packages=old_packages+[package,dict(package,id=2702,name='history-sibling.invalid',names=['history-sibling.invalid'])];provider.mode='success'
    def post(action,**values):
        return owner.post(settings,data={'csrf':env['token'](owner.get(settings)),'action':action,'profile_id':profile['id'],'revision':str(profile['revision']),'current_password':OWNER,**values})
    def read():
        saved=db('SELECT id,started_epoch FROM ci_provider_refreshes WHERE operation IS NULL OR operation<>?', ['cloudservers.list']);age_unprotected_refreshes(7200)
        try:return post('provider_inventory_refresh')
        finally:
            for row in saved:db('UPDATE ci_provider_refreshes SET started_epoch=? WHERE id=?',[row['started_epoch'],row['id']])
    read();post('provider_inventory_link',package_id='2701',client_id=str(cid));link=db('SELECT id FROM ci_provider_package_links WHERE package_id=2701')[0]['id']
    check('Package assignment does not automatically expose earlier reads','before-history.invalid' not in client.get(url).text)
    package['name']='history-client.invalid';package['names']=['history-client.invalid'];read();calls=provider.calls
    fields=form_data(next(f for f in soup(owner.get(settings)).select('form') if f.select_one('[name=action][value='+change+']') and f.select_one('[name=link_id]')['value']==link))
    for mutation,label in [({'csrf':'bad'},'CSRF'),({'current_password':'wrong'},'password'),({'refresh_id':'f'*32},'stale read')]:
        owner.post(settings,data={**fields,'sharing':'enable','current_password':OWNER,**mutation});check('Hosting history sharing refuses '+label,not db('SELECT * FROM ci_service_observation_permissions') and provider.calls==calls)
    context=browser.new_context(viewport={'width':1440,'height':1000});context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in owner.cookies]);page=context.new_page();page.goto(settings);form=page.locator('form').filter(has=page.locator('[name=action][value='+change+']')).filter(has=page.locator('[name=link_id][value="'+link+'"]'));form.locator('[name=current_password]').fill(OWNER)
    with page.expect_navigation(wait_until='domcontentloaded'):form.get_by_role('button',name='Share from latest saved read',exact=True).click()
    expect(page.locator('#service-observations')).to_contain_text('Sharing enabled for the approved assignment.');context.close()
    response=client.get(url);check('Chromium sharing exposes only the approved client observation','history-client.invalid' in response.text and 'before-history.invalid' not in response.text and 'history-sibling.invalid' not in response.text and 'HISTORY PRIVATE LABEL' not in response.text and 'stack-user:987' not in response.text and provider.calls==calls)
    check('Hosting history uses private response headers','no-store' in response.headers.get('Cache-Control','') and response.headers.get('Referrer-Policy')=='no-referrer')
    check('Sibling captured link cannot expose history','history-client.invalid' not in other.get(url+'&service_link='+link).text)
    package['enabled']=False;package['packageTypeName']='Updated <type>';read();calls=provider.calls;response=client.get(url)
    check('Later refresh retains distinct enabled and disabled observations','Enabled' in response.text and 'Disabled' in response.text and 'Updated &lt;type&gt;' in response.text and len(soup(response).select('[data-service-observation]'))==2)
    for session,title,target,selector in [(owner,'Hosting-History-Owner',settings,'#service-observations'),(client,'Hosting-History-Client',url,'[data-service-history]')]:
        context=browser.new_context();context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in session.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        for width,height,label in [(1440,1000,'Desktop'),(320,740,'Mobile')]:
            page.set_viewport_size({'width':width,'height':height});page.goto(target);page.evaluate('document.fonts.ready');expect(page.locator(selector).first).to_be_visible()
            if session is owner and label=='Mobile':expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
            page.screenshot(path=str(qa/(title+'-'+label+'.png')),full_page=True);page.locator(selector).first.screenshot(path=str(qa/(title+'-'+label+'-Section.png')))
            check(title+' '+label+' fits viewport',not errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        context.close()
    provider.mode='authentication';read();calls=provider.calls;last=db('SELECT id,started_epoch FROM ci_provider_refreshes WHERE operation=? ORDER BY seq DESC LIMIT 1',['packages.list'])[0];db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-600 WHERE id=?',[last['id']]);response=client.get(url)
    check('Newer failed sequence stays current despite older wall clock','Latest refresh did not provide an observation' in response.text and 'history-client.invalid' in response.text and 'private synthetic response' not in response.text)
    chosen_form(owner,settings,change,'link_id',link,{'sharing':'enable','current_password':OWNER});check('Latest failure refuses a replacement sharing anchor',len(db('SELECT * FROM ci_service_observation_permissions'))==1 and provider.calls==calls)
    provider.mode='success';read();calls=provider.calls;post('provider_inventory_relink',link_id=link);response=client.get(url);check('Current assignment reapproval revokes old shared history','needs to confirm this assignment' in response.text and 'history-client.invalid' not in response.text)
    chosen_form(owner,settings,change,'link_id',link,{'sharing':'enable','current_password':OWNER});check('Pre-reapproval read cannot become a new visibility anchor',len(db('SELECT * FROM ci_service_observation_permissions'))==1)
    read();calls=provider.calls
    chosen_form(owner,settings,change,'link_id',link,{'sharing':'enable','current_password':OWNER});check('Explicit new approval starts a new history boundary',len(soup(client.get(url)).select('[data-service-observation]'))==1)
    retained=db('SELECT * FROM ci_service_observation_permissions ORDER BY id');db('RENAME TABLE ci_service_observation_permissions TO ci_saved_history_permissions')
    try:
        check('Missing sharing history gives controlled private refusal','needs to review the saved hosting history' in client.get(url).text)
        owner.post(settings,data={'csrf':env['token'](owner.get(settings)),'action':prepare,'current_password':OWNER});check('Owner cannot silently recreate missing permission history',not db('SHOW TABLES LIKE "ci_service_observation_permissions"'))
    finally:db('RENAME TABLE ci_saved_history_permissions TO ci_service_observation_permissions')
    check('Exact restored sharing history preserves original documents',db('SELECT * FROM ci_service_observation_permissions ORDER BY id')==retained and 'history-client.invalid' in client.get(url).text)
    chosen_form(owner,settings,change,'link_id',link,{'sharing':'disable','current_password':OWNER});check('Owner disable immediately removes client history','history-client.invalid' not in client.get(url).text and provider.calls==calls and provider.posts==posts)
    for path in ['app/providers/service_observations.php','app/providers/service_observations_storage.php','app/providers/service_observations_document.php','app/providers/service_observations_views.php','admin/controllers/service_observations.php','admin/views/service_observations.php','portal/controllers/service_observations.php','portal/views/service_observations.php','database/schema/service_observations_schema.php']:
        for prefix in ['/existing/','/fresh/']:check('Hosting history private HTTP denial '+prefix+path,requests.get(base+prefix+path).status_code==404)
    check('Hosting history workflow sends only explicit fixture reads',provider.calls==calls and provider.posts==posts and not provider.errors)
    provider.packages=old_packages;provider.mode=old_mode;client.close();other.close();owner.close()
