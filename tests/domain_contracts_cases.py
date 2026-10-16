"""Actual packaged renewal-date owner/client UI; explicit local TLS reads only."""
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER


def run_browser(env,browser):
    check,soup,form_data,submit=(env[k] for k in ['check','soup','form_data','submit'])
    base,qa,provider=env['BASE'],env['QA'],env['provider_server'];admin=base+'/existing/admin/index.php';portal=base+'/existing/portal/index.php';settings=admin+'?view=provider-settings';url=portal+'?domain_dates=1'
    owner=requests.Session();submit(owner,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER});db('DELETE FROM ci_rate_limits')
    client=requests.Session();submit(client,portal,'login',{'email':'registrar-client1@example.invalid','password':OWNER});other=requests.Session();submit(other,portal,'login',{'email':'registrar-client2@example.invalid','password':OWNER})
    cid=db('SELECT id FROM ci_studio_clients WHERE email=?',['registrar-client1@example.invalid'])[0]['id'];profile=db('SELECT * FROM ci_provider_profiles')[0]
    calls,posts=provider.calls,list(provider.posts);prepare='provider_inventory_contract_prepare';refresh='provider_inventory_contract_refresh'
    check('Renewal dates GET creates no storage or provider read',prepare in owner.get(settings).text and not db('SHOW TABLES LIKE "ci_domain_contract_bindings"') and 'No renewal dates are available' in client.get(url).text and provider.calls==calls)
    for fields,label in [({'csrf':'bad','current_password':OWNER},'CSRF'),({'current_password':'wrong'},'password')]:
        submit(owner,settings,prepare,fields);check('Renewal dates preparation refuses '+label,not db('SHOW TABLES LIKE "ci_domain_contract_bindings"') and provider.calls==calls)
    submit(owner,settings,prepare,{'current_password':OWNER});check('Renewal dates prepare is local and explicit',not db('SELECT * FROM ci_domain_contract_bindings') and provider.calls==calls)
    old_domains=list(provider.registrar_domains);old_mode=provider.registrar_mode
    provider.registrar_mode='success';provider.registrar_domains=old_domains+[{'id':3701,'name':'dates-client.invalid'},{'id':3702,'name':'dates-sibling.invalid'}]
    saved=db('SELECT id,started_epoch FROM ci_provider_refreshes');db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-7200')
    try:submit(owner,settings,'provider_inventory_registrar_refresh',{'current_password':OWNER})
    finally:
        for row in saved:db('UPDATE ci_provider_refreshes SET started_epoch=? WHERE id=?',[row['started_epoch'],row['id']])
    source=db('SELECT id FROM ci_provider_refreshes WHERE operation=? ORDER BY seq DESC LIMIT 1',['domains.list'])[0]['id']
    # Establish a consistent synthetic source120seconds old before any binding captures it.
    db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-120 WHERE id=?',[source])
    chosen_form(owner,settings,'provider_inventory_registrar_link','domain_id',3701,{'client_id':cid,'current_password':OWNER})
    sibling=db('SELECT id FROM ci_studio_clients WHERE email=?',['registrar-client2@example.invalid'])[0]['id'];chosen_form(owner,settings,'provider_inventory_registrar_link','domain_id',3702,{'client_id':sibling,'current_password':OWNER})
    link=db('SELECT * FROM ci_provider_registrar_links WHERE domain_id=3701')[0];calls=provider.calls
    provider.contract_dates={'dates-client.invalid':'2028-02-29','dates-sibling.invalid':'2029-01-01'}
    def fields():return form_data(next(f for f in soup(owner.get(settings)).select('form') if f.select_one('[name=action][value='+refresh+']') and f.select_one('[name=link_id]')['value']==link['id']))
    def read():
        # Pass existing shared cooldown in the fixture while restoring every original row exactly.
        prior=db('SELECT id,started_epoch FROM ci_provider_refreshes WHERE id<>?',[source]);bindings=db('SELECT id,started_epoch FROM ci_domain_contract_bindings')
        db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-7200 WHERE id<>?',[source]);db('UPDATE ci_domain_contract_bindings SET started_epoch=started_epoch-7200')
        try:return chosen_form(owner,settings,refresh,'link_id',link['id'],{'current_password':OWNER})
        finally:
            for row in prior:db('UPDATE ci_provider_refreshes SET started_epoch=? WHERE id=?',[row['started_epoch'],row['id']])
            for row in bindings:db('UPDATE ci_domain_contract_bindings SET started_epoch=? WHERE id=?',[row['started_epoch'],row['id']])
    stale=fields()
    for changed,label in [({'csrf':'bad'},'CSRF'),({'current_password':'wrong'},'password'),({'link_revision':'999'},'assignment revision'),({'refresh_id':'f'*32},'source revision')]:
        owner.post(settings,data={**stale,'current_password':OWNER,**changed});check('Renewal date refresh refuses '+label,not db('SELECT * FROM ci_domain_contract_bindings') and provider.calls==calls)
    # Age only unbound existing reads for the actual Chromium submit; restore after navigation.
    prior=db('SELECT id,started_epoch FROM ci_provider_refreshes WHERE id<>?',[source]);db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-7200 WHERE id<>?',[source])
    context=browser.new_context(viewport={'width':1440,'height':1000});context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in owner.cookies]);page=context.new_page();page.goto(settings);form=page.locator('form').filter(has=page.locator('[name=action][value='+refresh+']')).filter(has=page.locator('[name=link_id][value="'+link['id']+'"]'));form.locator('[name=current_password]').fill(OWNER)
    try:
        with page.expect_navigation(wait_until='domcontentloaded'):form.get_by_role('button',name='Refresh renewal date',exact=True).click()
        expect(page.locator('#domain-contracts')).to_contain_text('Provider renewal date: 2028-02-29')
    finally:
        for row in prior:db('UPDATE ci_provider_refreshes SET started_epoch=? WHERE id=?',[row['started_epoch'],row['id']])
    context.close();calls=provider.calls
    check('Chromium saves one exact contract read with no provider mutation',len(db('SELECT * FROM ci_domain_contract_dates'))==1 and provider.get_paths[-1]=='/domain/dates-client.invalid/contract' and provider.posts==posts)
    response=client.get(url);check('Client sees own dated observation without private provider fields','Provider renewal date: 2028-02-29' in response.text and 'dates-client.invalid' in response.text and 'dates-sibling.invalid' not in response.text and 'CONTRACT_PRIVATE_MARKER' not in response.text and provider.calls==calls)
    check('Renewal date route uses private headers','no-store' in response.headers.get('Cache-Control','') and response.headers.get('Referrer-Policy')=='no-referrer')
    check('Other client query cannot reveal first date','2028-02-29' not in other.get(url+'&client_id='+str(cid)+'&link_id='+link['id']).text)
    check('Client page offers renewal review separately','Request renewal review' in response.text and 'does not confirm expiry, a price, or a completed renewal' in response.text)
    for session,title,target,selector in [(owner,'Domain-Dates-Owner',settings,'#domain-contracts'),(client,'Domain-Dates-Client',url,'[data-domain-date]')]:
        context=browser.new_context();context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in session.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        for width,height,label in [(1440,1000,'Desktop'),(320,740,'Mobile')]:
            page.set_viewport_size({'width':width,'height':height});page.goto(target);page.evaluate('document.fonts.ready');expect(page.locator(selector).first).to_be_visible()
            if session is owner and label=='Mobile':expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
            page.evaluate('window.scrollTo(0,0)');page.screenshot(path=str(qa/(title+'-'+label+'.png')),full_page=True)
            check(title+' '+label+' fits viewport',not errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        context.close()
    provider.contract_mode='authentication';read();calls=provider.calls;response=client.get(url)
    check('New failed date refresh suppresses current date and labels history','Provider renewal date: 2028-02-29' not in response.text and 'Observed renewal date 2028-02-29' in response.text and 'latest renewal date is unavailable' in response.text and 'private contract marker' not in response.text)
    rows=db('SELECT * FROM ci_domain_contract_bindings ORDER BY id');db('RENAME TABLE ci_domain_contract_bindings TO ci_saved_contract_bindings')
    try:
        check('Lost date bindings return controlled client refusal','needs to review the saved renewal dates' in client.get(url).text)
        owner.post(settings,data={'csrf':env['token'](owner.get(settings)),'action':prepare,'current_password':OWNER});check('Owner cannot recreate lost date authority',not db('SHOW TABLES LIKE "ci_domain_contract_bindings"'))
    finally:db('RENAME TABLE ci_saved_contract_bindings TO ci_domain_contract_bindings')
    check('Exact restored bindings preserve latest failed state',db('SELECT * FROM ci_domain_contract_bindings ORDER BY id')==rows and 'latest renewal date is unavailable' in client.get(url).text)
    provider.contract_mode='success';read();calls=provider.calls;chosen_form(owner,settings,'provider_inventory_registrar_relink','link_id',link['id'],{'current_password':OWNER})
    check('Assignment reapproval removes prior-context dates','2028-02-29' not in client.get(url).text and 'refresh the date for this assignment' in client.get(url).text)
    read();calls=provider.calls;check('Explicit refresh after reapproval restores own date','Provider renewal date: 2028-02-29' in client.get(url).text)
    for path in ['app/providers/domain_contracts.php','app/providers/domain_contracts_storage.php','app/providers/domain_contracts_views.php','admin/controllers/domain_contracts.php','admin/views/domain_contracts.php','portal/controllers/domain_contracts.php','portal/views/domain_contracts.php','database/schema/domain_contracts_schema.php','includes/foundation/twentyi-domain-contracts/DomainContract.php','includes/foundation/twentyi-domain-contracts/DomainContractBinding.php','includes/foundation/twentyi-domain-contracts/DomainContractObservation.php']:
        for prefix in ['/existing/','/fresh/']:check('Renewal date private HTTP denial '+prefix+path,requests.get(base+prefix+path).status_code==404)
    check('Renewal date browser workflow sends only explicit reads',provider.calls==calls and provider.posts==posts and not provider.errors)
    provider.registrar_domains=old_domains;provider.registrar_mode=old_mode;client.close();other.close();owner.close()
