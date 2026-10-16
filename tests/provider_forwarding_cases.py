"""Packaged owner/client forwarding request UI, with no forwarding transport."""
import json
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from cloud_assignment_fixture import age_unprotected_refreshes
from staff_cases import OWNER


def run_browser(env,browser):
    check,soup,form_data,form_for,submit=(env[k] for k in ['check','soup','form_data','form_for','submit'])
    base,qa,provider=env['BASE'],env['QA'],env['provider_server'];admin=base+'/existing/admin/index.php';portal=base+'/existing/portal/index.php';settings=admin+'?view=provider-settings';url=portal+'?forwarding=1'
    db('DELETE FROM ci_rate_limits')
    owner=requests.Session();submit(owner,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    client=requests.Session();submit(client,portal,'login',{'email':'domain-client1@example.invalid','password':OWNER})
    other=requests.Session();submit(other,portal,'login',{'email':'domain-client2@example.invalid','password':OWNER})
    cid=db('SELECT id FROM ci_studio_clients WHERE email=?',['domain-client1@example.invalid'])[0]['id'];prepare='provider_inventory_forwarding_prepare';permission='provider_inventory_forwarding_permission';create='forwarding_create';review='provider_inventory_forwarding_review'
    calls,posts=provider.calls,list(provider.posts);ledger=db('SELECT * FROM ci_provider_operations ORDER BY id');grants=db('SELECT * FROM ci_provider_operation_grants ORDER BY id')
    check('Forwarding GET offers explicit preparation without DDL',prepare in owner.get(settings).text and not db('SHOW TABLES LIKE "ci_provider_forwarding_events"') and provider.calls==calls)
    for changes,label in [({'csrf':'bad','current_password':OWNER},'CSRF'),({'current_password':'wrong'},'password')]:
        submit(owner,settings,prepare,changes);check('Forwarding preparation refuses '+label,not db('SHOW TABLES LIKE "ci_provider_forwarding_events"'))
    submit(owner,settings,prepare,{'current_password':OWNER});check('Forwarding preparation has no provider effect',db('SELECT * FROM ci_provider_forwarding_events')==[] and provider.calls==calls)
    provider.mode='success';provider.packages.append({'id':85002,'typeRef':9,'enabled':True,'name':'forwarding.example.invalid','names':['forwarding.example.invalid'],'created':'2024-01-01T00:00:00Z'})
    age_unprotected_refreshes(120);submit(owner,settings,'provider_inventory_refresh',{'current_password':OWNER});profile=db('SELECT id,revision FROM ci_provider_profiles')[0]
    owner.post(settings,data={'csrf':env['token'](owner.get(settings)),'action':'provider_inventory_domain_link','profile_id':profile['id'],'revision':profile['revision'],'package_id':'85002','domain':'forwarding.example.invalid','client_id':cid,'current_password':OWNER});link=db('SELECT id FROM ci_provider_domain_links WHERE domain=?',['forwarding.example.invalid'])[0]['id'];calls=provider.calls
    check('Domain assignment alone does not permit forwarding',create not in client.get(url).text)
    def policy(**changes):return chosen_form(owner,settings,permission,'link_id',link,{'current_password':OWNER,'enabled':'1','daily_limit':'5','hours':'24','permission_confirm':'1',**changes})
    for changes,label in [({'csrf':'bad'},'CSRF'),({'current_password':'wrong'},'password'),({'permission_confirm':'0'},'separate forwarding confirmation'),({'context':'0'*64},'stale assignment form')]:
        policy(**changes);check('Forwarding permission refuses '+label,not db('SELECT * FROM ci_provider_forwarding_events'))
    ctx=browser.new_context(viewport={'width':1440,'height':1000});ctx.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in owner.cookies]);page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.goto(settings)
    form=page.locator('form').filter(has=page.locator('[name=link_id][value="'+link+'"]')).filter(has=page.locator('[name=action][value='+permission+']'))
    form.locator('[name=enabled]').select_option('1');form.locator('[name=daily_limit]').fill('5');form.locator('[name=hours]').fill('24');form.locator('[name=permission_confirm]').check();form.locator('[name=current_password]').fill(OWNER)
    with page.expect_navigation(wait_until='domcontentloaded'):form.get_by_role('button',name='Save forwarding permission',exact=True).click()
    check('Chromium owner enables only local forwarding request permission',len(db('SELECT * FROM ci_provider_forwarding_events WHERE kind=?',['permission']))==1 and provider.calls==calls and not errors);ctx.close()
    response=client.get(url);fields=form_data(form_for(response,create));values={'local':'enquiries','remote':'delivery@example.invalid','request_confirm':'1'}
    check('Forwarding client form is private and accurately bounded','does not change email delivery or activate forwarding' in response.text and 'no-store' in response.headers.get('Cache-Control','') and response.headers.get('Referrer-Policy')=='no-referrer' and 'profile_id' not in response.text)
    for changes,label in [({'csrf':'bad'},'CSRF'),({'request_confirm':'0'},'review-only confirmation'),({'permission_id':'f'*32},'foreign permission'),({'local':'*'},'wildcard'),({'remote':'a@example.invalid,b@example.invalid'},'multiple destinations')]:
        client.post(url,data={**fields,**values,**changes});check('Forwarding intake refuses '+label,not db('SELECT id FROM ci_provider_forwarding_events WHERE kind=?',['request']) and provider.calls==calls)
    token=form_data(form_for(other.get(portal),'logout'))['csrf'];other.post(url,data={**fields,**values,'csrf':token});check('Sibling client cannot use captured forwarding permission',not db('SELECT id FROM ci_provider_forwarding_events WHERE kind=?',['request']))
    ctx=browser.new_context(viewport={'width':1440,'height':1000});ctx.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in client.cookies]);page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto(url);form=page.locator('form').filter(has=page.locator('[name=action][value='+create+']'))
    original={n:form.locator('[name='+n+']').input_value() for n in ['csrf','action','request_id','link_id','permission_id','context']};form.locator('[name=local]').fill(values['local']);form.locator('[name=remote]').fill(values['remote']);form.locator('[name=request_confirm]').check()
    page.screenshot(path=str(qa/'Forwarding-Client-Request-Form.png'),full_page=True)
    with page.expect_navigation(wait_until='domcontentloaded'):form.get_by_role('button',name='Request forwarding review',exact=True).click()
    expect(page.locator('[data-forwarding-history]')).to_contain_text('Pending studio review');rid=db('SELECT id FROM ci_provider_forwarding_events WHERE kind=?',['request'])[0]['id'];check('Chromium creates pending forwarding request without provider dispatch',provider.calls==calls and provider.posts==posts and not errors)
    client.post(url,data={**original,**values});check('Forwarding replay does not add another event',len(db('SELECT id FROM ci_provider_forwarding_events WHERE kind=?',['request']))==1)
    ctx.close();ctx=browser.new_context(viewport={'width':1440,'height':1000});ctx.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in owner.cookies]);page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto(settings)
    form=page.locator('form').filter(has=page.locator('[name=action][value='+review+']')).filter(has=page.locator('[name=request_id][value="'+rid+'"]'));form.locator('[name=review_note]').fill('Checking <delivery> with you.');form.locator('[name=current_password]').fill(OWNER)
    with page.expect_navigation(wait_until='domcontentloaded'):form.get_by_role('button',name='Save review message',exact=True).click()
    expect(page.locator('#forwarding-requests')).to_contain_text('Checking <delivery> with you.');check('Chromium owner review remains pending without activation',len(db('SELECT id FROM ci_provider_forwarding_events WHERE kind=?',['review']))==1 and 'Checking &lt;delivery&gt; with you.' in client.get(url).text and provider.calls==calls)
    for width,height,label in [(1440,1000,'Desktop'),(320,740,'Mobile')]:
        page.set_viewport_size({'width':width,'height':height});page.goto(settings);page.evaluate('document.fonts.ready');page.screenshot(path=str(qa/('Forwarding-Owner-'+label+'.png')),full_page=True);page.locator('#forwarding-requests').screenshot(path=str(qa/('Forwarding-Owner-'+label+'-Detail.png')));check('Forwarding owner '+label+' fits viewport',not errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    ctx.close();ctx=browser.new_context();ctx.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in client.cookies]);page=ctx.new_page()
    for width,height,label in [(1440,1000,'Desktop'),(320,740,'Mobile')]:
        page.set_viewport_size({'width':width,'height':height});page.goto(url);page.evaluate('document.fonts.ready');page.screenshot(path=str(qa/('Forwarding-Client-'+label+'.png')),full_page=True);check('Forwarding client '+label+' fits viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    ctx.close();check('Forwarding history stays private','delivery@example.invalid' not in other.get(url).text)
    chosen_form(client,url,'forwarding_cancel','request_id',rid,{});check('Client cancels without deleting original review',len(db('SELECT * FROM ci_provider_forwarding_events WHERE subject_id=?',[rid]))==3 and 'Cancelled' in client.get(url).text)
    submit(client,url,create,{**values,'local':'sales'});rid2=db('SELECT id FROM ci_provider_forwarding_events WHERE kind=? ORDER BY ordinal DESC',['request'])[0]['id'];chosen_form(owner,settings,review,'request_id',rid2,{'current_password':OWNER,'command':'decline','review_note':'Please contact the studio.'});check('Owner decline retains history without forwarding','Declined' in client.get(url).text)
    support=chosen_form(owner,admin+'?view=support','support_start','client_id',cid,{});check('Support session cannot open forwarding controls',owner.get(support.url+'&forwarding=1').status_code==403);submit(owner,support.url,'support_end',{})
    stale=form_data(form_for(client.get(url),create));policy(enabled='0',permission_confirm='0');before=db('SELECT * FROM ci_provider_forwarding_events ORDER BY ordinal');client.post(url,data={**stale,**values,'local':'stale'});check('Revocation blocks captured request form and keeps history',db('SELECT * FROM ci_provider_forwarding_events ORDER BY ordinal')==before and create not in client.get(url).text and 'Cancelled' in client.get(url).text)
    db('RENAME TABLE ci_provider_forwarding_events TO ci_saved_forwarding_events')
    try:
        check('Lost forwarding history produces controlled private response','needs to review the forwarding request records' in client.get(url).text);owner.post(settings,data={'csrf':env['token'](owner.get(settings)),'action':prepare,'current_password':OWNER});check('Owner cannot silently recreate established forwarding history',not db('SHOW TABLES LIKE "ci_provider_forwarding_events"'))
    finally:db('RENAME TABLE ci_saved_forwarding_events TO ci_provider_forwarding_events')
    check('Exact restored forwarding history has no provider operation or result',db('SELECT * FROM ci_provider_forwarding_events ORDER BY ordinal')==before and provider.calls==calls and provider.posts==posts and db('SELECT * FROM ci_provider_operations ORDER BY id')==ledger and db('SELECT * FROM ci_provider_operation_grants ORDER BY id')==grants)
    for path in ['app/providers/forwarding.php','app/providers/forwarding_document.php','app/providers/forwarding_storage.php','app/providers/forwarding_views.php','admin/controllers/provider_forwarding.php','admin/views/provider_forwarding.php','portal/controllers/forwarding.php','portal/views/forwarding.php','database/schema/provider_forwarding_schema.php','includes/foundation/twentyi-operations/ForwardingRequest.php']:
        check('Forwarding internal HTTP denial '+path,requests.get(base+'/existing/'+path).status_code==404)
    client.close();other.close();owner.close()
