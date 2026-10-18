"""Native packaged local renewal requests; every provider response is synthetic."""
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from cloud_assignment_fixture import age_unprotected_refreshes
from staff_cases import OWNER

def run_browser(env,browser):
    check,soup,form_data,form_for,submit=(env[k] for k in ['check','soup','form_data','form_for','submit'])
    base,qa,provider=env['BASE'],env['QA'],env['provider_server'];admin=base+'/existing/admin/index.php';portal=base+'/existing/portal/index.php';settings=admin+'?view=provider-settings';url=portal+'?domain_renewals=1'
    owner=requests.Session();submit(owner,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    client=requests.Session();submit(client,portal,'login',{'email':'registrar-client1@example.invalid','password':OWNER})
    other=requests.Session();submit(other,portal,'login',{'email':'registrar-client2@example.invalid','password':OWNER})
    cid=db('SELECT id FROM ci_studio_clients WHERE email=?',['registrar-client1@example.invalid'])[0]['id']
    calls,posts=provider.calls,list(provider.posts);prepare='provider_inventory_renewal_prepare';create='domain_renewal_create'
    check('Renewal GET offers explicit preparation with no DDL',prepare in owner.get(settings).text and not db('SHOW TABLES LIKE "ci_domain_renewal_requests"') and provider.calls==calls)
    for fields,label in [({'csrf':'bad','current_password':OWNER},'CSRF'),({'current_password':'wrong'},'password')]:
        submit(owner,settings,prepare,fields);check('Renewal prepare refuses '+label,not db('SHOW TABLES LIKE "ci_domain_renewal_requests"') and provider.calls==calls)
    submit(owner,settings,prepare,{'current_password':OWNER});check('Renewal storage preparation makes no provider call',not db('SELECT * FROM ci_domain_renewal_requests') and provider.calls==calls)
    provider.registrar_mode='success';provider.registrar_domains.append({'id':1703,'name':'renewal.registrar.invalid'});age_unprotected_refreshes(7200)
    submit(owner,settings,'provider_inventory_registrar_refresh',{'current_password':OWNER})
    chosen_form(owner,settings,'provider_inventory_registrar_link','domain_id',1703,{'client_id':cid,'current_password':OWNER});calls=provider.calls
    response=client.get(url);fields=form_data(form_for(response,create));check('Renewal request form is private and does not offer payment','renewal.registrar.invalid' in response.text and 'does not place a renewal or payment' in response.text and 'no-store' in response.headers.get('Cache-Control','') and response.headers.get('Referrer-Policy')=='no-referrer')
    values={'requested_years':'2','renew_privacy':'ask','request_confirm':'1'}
    for changes,label in [({'csrf':'bad'},'CSRF'),({'request_confirm':'0'},'review-only confirmation'),({'link_revision':'999'},'changed assignment'),({'requested_years':'11'},'local term limit')]:
        client.post(url,data={**fields,**values,**changes});check('Renewal intake refuses '+label,not db('SELECT * FROM ci_domain_renewal_requests') and provider.calls==calls)
    token=form_data(form_for(other.get(portal),'logout'))['csrf'];other.post(url,data={**fields,**values,'csrf':token});check('Other client cannot request captured registrar assignment',not db('SELECT * FROM ci_domain_renewal_requests') and provider.calls==calls)
    context=browser.new_context(viewport={'width':1440,'height':1000});context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in client.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.goto(url)
    form=page.locator('form').filter(has=page.locator('[name=action][value='+create+']'));sent={n:form.locator('[name='+n+']').input_value() for n in ['csrf','action','request_id','link_id','link_revision','profile_revision','refresh_id']};form.locator('[name=requested_years]').fill('2');form.locator('[name=request_confirm]').check()
    with page.expect_navigation(wait_until='domcontentloaded'):form.get_by_role('button',name='Request renewal review',exact=True).click()
    expect(page.locator('[data-domain-renewal-history]')).to_contain_text('Pending studio review');rows=db('SELECT * FROM ci_domain_renewal_requests');check('Chromium records one pending request without any provider write',len(rows)==1 and rows[0]['state']=='pending_review' and provider.calls==calls and provider.posts==posts and not errors)
    client.post(url,data={**sent,**values});check('Renewal request replay cannot create another request',len(db('SELECT * FROM ci_domain_renewal_requests'))==1 and provider.calls==calls)
    rid=rows[0]['id'];chosen_form(owner,settings,'provider_inventory_renewal_review','request_id',rid,{'current_password':OWNER,'command':'reviewed','review_note':'Checking eligible terms <safely>.'})
    check('Owner review stays pending and client sees escaped message',db('SELECT state,revision FROM ci_domain_renewal_requests WHERE id=?',[rid])[0]=={'state':'pending_review','revision':1} and 'Checking eligible terms &lt;safely&gt;.' in client.get(url).text and 'renewal.registrar.invalid' not in other.get(url).text)
    for width,height,label in [(1440,1000,'Desktop'),(320,740,'Mobile')]:
        page.set_viewport_size({'width':width,'height':height});page.goto(url);page.evaluate('document.fonts.ready');page.screenshot(path=str(qa/('Domain-Renewals-Client-'+label+'.png')),full_page=True);check('Renewal client '+label+' fits viewport',not errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    context.close();context=browser.new_context();context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in owner.cookies]);page=context.new_page()
    for width,height,label in [(1440,1000,'Desktop'),(320,740,'Mobile')]:
        page.set_viewport_size({'width':width,'height':height});page.goto(settings);page.evaluate('document.fonts.ready')
        if label=='Mobile':expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
        page.screenshot(path=str(qa/('Domain-Renewals-Owner-'+label+'.png')),full_page=True);check('Renewal owner '+label+' fits viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    context.close();chosen_form(client,url,'domain_renewal_withdraw','request_id',rid,{})
    check('Client withdrawal retains request and owner review history',db('SELECT state,revision FROM ci_domain_renewal_requests WHERE id=?',[rid])[0]=={'state':'withdrawn','revision':2} and len(db('SELECT * FROM ci_domain_renewal_events WHERE request_id=?',[rid]))==2)
    submit(client,url,create,values);rid2=db('SELECT id FROM ci_domain_renewal_requests WHERE state=?',['pending_review'])[0]['id'];chosen_form(owner,settings,'provider_inventory_renewal_review','request_id',rid2,{'current_password':OWNER,'command':'declined','review_note':'Please contact the studio before requesting again.'})
    check('Owner decline is shown without claiming a renewal','Declined' in client.get(url).text and db('SELECT state FROM ci_domain_renewal_requests WHERE id=?',[rid2])[0]['state']=='declined')
    stale=form_data(form_for(client.get(url),create));provider.registrar_mode='authentication';age_unprotected_refreshes(7200);submit(owner,settings,'provider_inventory_registrar_refresh',{'current_password':OWNER});calls=provider.calls;client.post(url,data={**stale,**values});check('New failed registrar read fences captured renewal intake',len(db('SELECT * FROM ci_domain_renewal_requests'))==2 and create not in client.get(url).text and provider.calls==calls)
    provider.registrar_mode='success';age_unprotected_refreshes(7200);submit(owner,settings,'provider_inventory_registrar_refresh',{'current_password':OWNER});calls=provider.calls
    saved=db('SELECT * FROM ci_domain_renewal_events ORDER BY id');db('RENAME TABLE ci_domain_renewal_events TO ci_saved_renewal_events')
    try:
        check('Lost renewal history gives controlled private unavailable response','needs to review the renewal request records' in client.get(url).text)
        owner.post(settings,data={'csrf':env['token'](owner.get(settings)),'action':prepare,'current_password':OWNER});check('Owner cannot recreate lost renewal review history',not db('SHOW TABLES LIKE "ci_domain_renewal_events"'))
    finally:db('RENAME TABLE ci_saved_renewal_events TO ci_domain_renewal_events')
    check('Restored review history retains exact original events',db('SELECT * FROM ci_domain_renewal_events ORDER BY id')==saved and provider.calls==calls and provider.posts==posts)
    for path in ['app/providers/domain_renewals.php','app/providers/domain_renewals_storage.php','app/providers/domain_renewals_document.php','app/providers/domain_renewals_views.php','admin/controllers/domain_renewals.php','admin/views/domain_renewals.php','portal/controllers/domain_renewals.php','portal/views/domain_renewals.php','database/schema/domain_renewals_schema.php']:
        check('Renewal internal subfolder HTTP denial '+path,requests.get(base+'/existing/'+path).status_code==404)
    client.close();other.close();owner.close()
