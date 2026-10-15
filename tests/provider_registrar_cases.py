"""Actual extracted-package owner/client routes and Chromium; local TLS provider only."""
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    check, soup, form_data, form_for, submit = (env[k] for k in ['check', 'soup', 'form_data', 'form_for', 'submit'])
    base, qa, provider = env['BASE'], env['QA'], env['provider_server']
    admin, portal = base+'/existing/admin/index.php', base+'/existing/portal/index.php'
    url, services = admin+'?view=provider-settings', portal+'?services=1'
    owner=requests.Session();submit(owner,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    db('DELETE FROM ci_rate_limits')
    calls, posts = provider.calls, list(provider.posts)
    prepare='provider_inventory_registrar_prepare';refresh='provider_inventory_registrar_refresh';link_action='provider_inventory_registrar_link'
    check('Registrar GET offers additive preparation without remote read',prepare in owner.get(url).text and not db('SHOW TABLES LIKE "ci_provider_registrar_links"') and provider.calls==calls)
    for change,label in [({'csrf':'bad','current_password':OWNER},'CSRF'),({'current_password':'wrong'},'owner password')]:
        submit(owner,url,prepare,change)
        check('Registrar preparation refuses '+label,not db('SHOW TABLES LIKE "ci_provider_registrar_links"') and provider.calls==calls)
    submit(owner,url,prepare,{'current_password':OWNER})
    check('Actual registrar prepare adds empty history without remote read',not db('SELECT * FROM ci_provider_registrar_links') and provider.calls==calls)
    profile=db('SELECT * FROM ci_provider_profiles')[0]
    provider.registrar_domains=[{'id':1701,'name':'one.registrar.invalid','contact':{'email':'REGISTRAR_BROWSER_PRIVATE'}},{'id':1702,'name':'two.registrar.invalid'}]
    def read():
        db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-7200')
        return submit(owner,url,refresh,{'current_password':OWNER})
    read()
    latest=db('SELECT id FROM ci_provider_refreshes WHERE operation=? ORDER BY seq DESC LIMIT 1',['domains.list'])[0]['id']
    check('Actual registrar refresh sends one authenticated fixed GET',provider.calls==calls+1 and provider.get_paths[-1]=='/domain' and len(db('SELECT * FROM ci_provider_registrar_observations'))==2 and 'REGISTRAR_BROWSER_PRIVATE' not in owner.get(url).text)
    clients=[]
    for number in [1,2]:
        email='registrar-client'+str(number)+'@example.invalid'
        chosen_form(owner,admin+'?view=studio-clients','studio_client','id',0,{'name':'Registrar client '+str(number),'email':email})
        cid=db('SELECT id FROM ci_studio_clients WHERE email=?',[email])[0]['id']
        response=chosen_form(owner,admin+'?view=studio-clients','studio_invite','id',cid,{})
        invitation=soup(response).select_one('.notice.info a[href*="invite="]')['href']
        session=requests.Session();submit(session,invitation,'activate',{'password':OWNER,'confirm_password':OWNER});submit(session,portal,'login',{'email':email,'password':OWNER});clients.append((cid,session))
    (cid,client),(other_id,other)=clients
    fields=form_data(form_for(owner.get(url),link_action))
    for changes,label in [({'csrf':'bad'},'CSRF'),({'current_password':'wrong'},'password'),({'domain_id':'999999'},'unobserved identity'),({'refresh_id':'f'*32},'stale observation')]:
        owner.post(url,data={**fields,'client_id':str(cid),'current_password':OWNER,**changes})
        check('Registrar assignment refuses '+label,not db('SELECT * FROM ci_provider_registrar_links') and provider.calls==calls+1)
    member=requests.Session();submit(member,admin+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    member.post(admin+'?view=security',data={**fields,'csrf':env['token'](member.get(admin+'?view=security')),'client_id':str(cid),'current_password':STAFF})
    check('Administrator cannot forge registrar assignment',not db('SELECT * FROM ci_provider_registrar_links') and member.get(url).status_code==403)
    check('Support cannot see owner registrar controls',owner.get(url+'&support=fixture').status_code==403)
    context=browser.new_context(viewport={'width':1440,'height':1000});context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in owner.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.goto(url)
    form=page.locator('form').filter(has=page.locator('[name=action][value='+link_action+']')).first
    form.locator('[name=client_id]').select_option(str(cid));form.locator('[name=current_password]').fill(OWNER)
    with page.expect_navigation():form.get_by_role('button',name='Assign registered domain',exact=True).click()
    check('Chromium owner assignment saves one explicit domain without provider mutation',len(db('SELECT * FROM ci_provider_registrar_links'))==1 and provider.calls==calls+1 and provider.posts==posts and not errors)
    for width,height,label in [(1440,1000,'Desktop'),(320,740,'Mobile')]:
        page.set_viewport_size({'width':width,'height':height});page.goto(url);page.evaluate('document.fonts.ready')
        if label=='Mobile':expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
        page.evaluate('window.scrollTo({top:0,left:0,behavior:"instant"})');page.screenshot(path=str(qa/('Registered-Domains-Owner-'+label+'.png')),full_page=True)
        check('Registered domain owner '+label+' fits viewport',not errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    context.close()
    chosen_form(owner,url,link_action,'domain_id',1702,{'client_id':str(other_id),'current_password':OWNER})
    response=client.get(services);cards=soup(response).select('[data-client-registered-domain]')
    check('Client sees its registered identity without sibling domain',len(cards)==1 and 'one.registrar.invalid' in cards[0].text and 'two.registrar.invalid' not in response.text and 'REGISTRAR_BROWSER_PRIVATE' not in response.text)
    check('Registered-domain client response uses private subfolder route','no-store' in response.headers.get('Cache-Control','') and response.headers.get('Referrer-Policy')=='no-referrer')
    check('Other client query tampering cannot disclose first identity','one.registrar.invalid' not in other.get(services+'&client_id='+str(cid)+'&domain_id=1701').text)
    context=browser.new_context();context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in client.cookies]);page=context.new_page();client_errors=[];page.on('pageerror',lambda e:client_errors.append(str(e)))
    for width,height,label in [(1440,1000,'Desktop'),(320,740,'Mobile')]:
        page.set_viewport_size({'width':width,'height':height});page.goto(services);page.evaluate('document.fonts.ready');page.screenshot(path=str(qa/('Registered-Domains-Client-'+label+'.png')),full_page=True)
        check('Registered domain client '+label+' fits viewport',not client_errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    context.close()
    saved_link=db('SELECT * FROM ci_provider_registrar_links WHERE client_id=?',[cid])[0]
    old=form_data(form_for(owner.get(url),'provider_inventory_registrar_relink'))
    provider.registrar_mode='authentication';read()
    check('New failed registrar read hides old client identity','one.registrar.invalid' not in client.get(services).text and 'does not indicate cancellation' in client.get(services).text)
    owner.post(url,data={**old,'current_password':OWNER})
    check('Captured reapproval cannot bypass newer registrar failure',db('SELECT * FROM ci_provider_registrar_links WHERE id=?',[saved_link['id']])==[saved_link])
    provider.registrar_mode='success';provider.registrar_domains.append({});read()
    check('Partial registered-domain response cannot become current client authority','one.registrar.invalid' not in client.get(services).text and 'partial' in owner.get(url).text)
    provider.registrar_domains.pop();read()
    check('New matching complete registrar observation restores current linked identity','one.registrar.invalid' in client.get(services).text)
    db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-1000 WHERE operation=?',['domains.list'])
    check('Stale registrar observation hides client metadata','one.registrar.invalid' not in client.get(services).text)
    read()
    retained={t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['provider_registrar_observations','provider_registrar_links','provider_registrar_events']}
    db('RENAME TABLE ci_provider_registrar_observations TO ci_registrar_saved_observations')
    try:
        response=client.get(services)
        check('Missing registrar history gives a controlled private unavailable page',response.status_code==200 and 'needs to review the registered-domain records' in response.text and not db('SHOW TABLES LIKE "ci_provider_registrar_observations"'))
        owner.post(url,data={'csrf':env['token'](owner.get(url)),'action':prepare,'current_password':OWNER})
        check('Owner cannot recreate lost registrar history through HTTP',not db('SHOW TABLES LIKE "ci_provider_registrar_observations"'))
    finally:db('RENAME TABLE ci_registrar_saved_observations TO ci_provider_registrar_observations')
    check('Restored registrar history retains exact links and events',all(db('SELECT * FROM ci_'+t+' ORDER BY id')==rows for t,rows in retained.items()))
    chosen_form(owner,url,'provider_inventory_registrar_unlink','link_id',saved_link['id'],{'current_password':OWNER})
    check('Unlink removes client identity without provider cancellation','one.registrar.invalid' not in client.get(services).text and 'two.registrar.invalid' in other.get(services).text and provider.posts==posts)
    for path in ['app/providers/registrar.php','app/providers/registrar_storage.php','admin/controllers/provider_registrar.php','admin/views/provider_registrar.php','portal/views/registered_domains.php','database/schema/provider_registrar_schema.php','includes/foundation/twentyi-inventory/InventoryDomain.php']:
        check('Registrar internal subfolder HTTP denial '+path,requests.get(base+'/existing/'+path).status_code==404)
    check('Registrar browser flow has no provider fixture violations',not provider.errors)
    owner.close();member.close();client.close();other.close()
