"""Packaged owner cloud creation and client summaries, using loopback HTTPS only."""
import copy
import requests
from playwright.sync_api import expect
from release_cases import db
from cloud_assignment_fixture import age_unprotected_refreshes
from staff_cases import OWNER

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['BASE','QA','check','soup','token','form_data','submit','provider_server']})
    db('DELETE FROM ci_rate_limits')
    admin=BASE+'/existing/admin/index.php';url=admin+'?view=provider-settings';portal=BASE+'/existing/portal/index.php';services=portal+'?services=1'
    owner=requests.Session();submit(owner,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    client=requests.Session();submit(client,portal,'login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    other=requests.Session();submit(other,portal,'login',{'email':'bob@example.invalid','password':'Synthetic client passphrase 2026'})
    before_fixture=copy.deepcopy(provider_server.cloud_servers);before_packages=copy.deepcopy(provider_server.packages)
    provider_server.cloud_servers=[{'id':1401,'name':'cloud-create.synthetic.invalid','location':'dc_location-uk','packageIds':[]}];provider_server.cloud_mode='success';provider_server.write_mode='success'
    profile=db('SELECT id,revision FROM ci_provider_profiles')[0];cid=db('SELECT id FROM ci_studio_clients WHERE email=?',['alice@example.invalid'])[0]['id']
    def post(action,**fields):
        return owner.post(url,data={'csrf':token(owner.get(url)),'action':action,'profile_id':profile['id'],'revision':str(profile['revision']),'current_password':OWNER,**fields})
    age_unprotected_refreshes(7200)
    post('provider_inventory_cloud_refresh')
    refresh=db('SELECT id FROM ci_provider_refreshes WHERE operation="cloudservers.list" ORDER BY seq DESC LIMIT 1')[0]['id']
    post('provider_inventory_cloud_link',server_id='1401',client_id=str(cid),refresh_id=refresh)
    form=soup(owner.get(url)).select_one('[data-cloud-website-create]')
    check('Cloud website form requires a current explicitly linked server',form is not None and '1401' in form.text)
    fields=form_data(form);fields.update(domain_name='cloud-website.example',extra_domains='www.cloud-website.example',reviewed='1',current_password=OWNER)
    original_calls=provider_server.calls;original_links=db('SELECT * FROM ci_provider_package_links ORDER BY id')
    for label,change in [('CSRF',{'csrf':'bad'}),('password',{'current_password':'wrong'}),('confirmation',{'reviewed':'0'}),('domain',{'domain_name':'bad/name.example'})]:
        response=owner.post(url,data={**fields,**change})
        check('Cloud website form rejects invalid '+label,provider_server.calls==original_calls and not db('SELECT id FROM ci_provider_operation_grants WHERE operation="cloudsites.create"'))
    response=owner.post(url,data=fields)
    check('Cloud website form sends exactly the documented server request',provider_server.calls==original_calls+1 and provider_server.posts[-1]=={'path':'/cloud_server/1401/addWeb','body':{'domain_name':'cloud-website.example','extra_domain_names':['www.cloud-website.example']}})
    ledger=db('SELECT status,external_id FROM ci_provider_operations WHERE operation="cloudsites.create"')
    check('Accepted cloud website remains pending and not silently linked',len(ledger)==1 and ledger[0]['status']=='accepted_pending' and ledger[0]['external_id'] and db('SELECT * FROM ci_provider_package_links ORDER BY id')==original_links and 'Refresh hosting inventory' in response.text)
    owner.post(url,data=fields)
    check('Replaying the original cloud form cannot send twice',provider_server.calls==original_calls+1)
    duplicate=form_data(soup(owner.get(url)).select_one('[data-cloud-website-create]'));duplicate.update(domain_name='cloud-website.example',reviewed='1',current_password=OWNER)
    response=owner.post(url,data=duplicate)
    check('New form cannot recreate a domain with an accepted request',provider_server.calls==original_calls+1 and 'prior request' in response.text)
    own=client.get(services);foreign=other.get(services+'&client_id='+str(cid)+'&server_id=1401')
    check('Only the assigned client sees safe pending cloud request status','cloud-website.example' in own.text and 'website verification pending' in own.text and 'cloud-website.example' not in foreign.text)
    check('Support selector cannot open owner cloud creation',owner.get(url+'&support=fixture').status_code==403)
    for session,title,target,selector in [(owner,'Cloud-Website-Owner',url,'[data-cloud-websites]'),(client,'Cloud-Website-Client',services,'[data-client-cloud-request]')]:
        context=browser.new_context(viewport={'width':1440,'height':1050});context.add_cookies([{'name':c.name,'value':c.value,'domain':c.domain,'path':c.path} for c in session.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        for width,height,label in [(1440,1050,'Desktop'),(320,740,'Mobile')]:
            page.set_viewport_size({'width':width,'height':height});page.goto(target);page.evaluate('document.fonts.ready');expect(page.locator(selector)).to_be_visible();page.screenshot(path=str(QA/(title+'-'+label+'.png')),full_page=True)
            check(title+' '+label+' fits viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        check(title+' has no script errors',not errors);context.close()
    check('Cloud website provider fixture contains no invalid requests',not provider_server.errors)
    from provider_cloud_confirmation_cases import run_browser as confirmation_browser
    confirmation_browser(env,browser,owner,client,other,profile)
    provider_server.cloud_servers=before_fixture;provider_server.packages=before_packages
