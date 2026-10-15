"""Actual packaged mailbox permissions and Chromium creation against loopback TLS only."""
import requests
from release_cases import db, chosen_form
from staff_cases import OWNER


def run_browser(env, browser):
    check, soup, form_data, form_for, submit = (env[k] for k in ['check', 'soup', 'form_data', 'form_for', 'submit'])
    base, qa, provider = env['BASE'], env['QA'], env['provider_server']
    admin, portal = base+'/existing/admin/index.php', base+'/existing/portal/index.php'
    settings, url = admin+'?view=provider-settings', portal+'?hosting_mail=1'
    db('DELETE FROM ci_rate_limits')
    owner=requests.Session();submit(owner,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    client=requests.Session();submit(client,portal,'login',{'email':'domain-client1@example.invalid','password':OWNER})
    foreign=requests.Session();submit(foreign,portal,'login',{'email':'domain-client2@example.invalid','password':OWNER})
    cid=db('SELECT id FROM ci_studio_clients WHERE email=?',['domain-client1@example.invalid'])[0]['id']
    calls, posts=provider.calls, len(provider.posts)
    prepare, policy_action, create='provider_inventory_mailbox_prepare','provider_inventory_mailbox_policy','client_mailbox_create'
    check('Mailbox GET offers explicit preparation without changing storage',prepare in owner.get(settings).text and not db('SHOW TABLES LIKE "ci_provider_mailbox_requests"') and provider.calls==calls)
    for fields, label in [({'csrf':'bad','current_password':OWNER},'CSRF'),({'current_password':'wrong'},'owner password')]:
        submit(owner,settings,prepare,fields)
        check('Mailbox preparation rejects '+label,not db('SHOW TABLES LIKE "ci_provider_mailbox_requests"') and provider.calls==calls)
    submit(owner,settings,prepare,{'current_password':OWNER})
    check('Mailbox preparation creates empty history without network',db('SELECT * FROM ci_provider_mailbox_requests')==[] and provider.calls==calls)
    check('Client mailbox form requires an explicit domain policy',create not in client.get(url).text)
    provider.mode='success';provider.write_mode='success'
    provider.packages.append({'id':85001,'typeRef':9,'enabled':True,'name':'mailbox.example.invalid','names':['mailbox.example.invalid'],'created':'2024-01-01T00:00:00Z'})
    db('UPDATE ci_provider_refreshes SET started_epoch=started_epoch-120')
    submit(owner,settings,'provider_inventory_refresh',{'current_password':OWNER})
    profile=db('SELECT id,revision FROM ci_provider_profiles')[0]
    fields={'csrf':env['token'](owner.get(settings)),'action':'provider_inventory_domain_link','profile_id':profile['id'],'revision':profile['revision'],'package_id':'85001','domain':'mailbox.example.invalid','client_id':cid,'current_password':OWNER}
    owner.post(settings,data=fields)
    links=db('SELECT id FROM ci_provider_domain_links WHERE domain=?',['mailbox.example.invalid'])
    check('Owner explicitly links the mailbox test domain',len(links)==1)
    link=links[0]['id']
    def policy(**changes):
        return chosen_form(owner,settings,policy_action,'link_id',link,{'current_password':OWNER,'mailbox_enabled':'1','client_enabled':'1','daily_limit':'5','permission_hours':'24','capacity_reviewed':'1',**changes})
    for changes,label in [({'csrf':'bad'},'CSRF'),({'current_password':'wrong'},'password'),({'capacity_reviewed':'0'},'capacity review')]:
        policy(**changes)
        check('Mailbox policy refuses '+label,not db('SELECT * FROM ci_provider_mailbox_policies'))
    policy()
    response=client.get(url)
    check('Owner-approved mailbox form is private and scoped',create in response.text and 'mailbox.example.invalid' in response.text and 'no-store' in response.headers.get('Cache-Control','') and response.headers.get('Referrer-Policy')=='no-referrer' and 'profile_id' not in response.text)
    check('Mailbox portal navigation appears only for approved client','Hosting mailboxes' in client.get(portal).text and create not in foreign.get(url).text)
    original=form_data(form_for(response,create));original['mailbox_policy']=soup(response).select_one('[name=mailbox_policy] option:not([value=""])')['value'];password='Synthetic browser mailbox pass 123!'
    values={'mailbox_local':'hello','mailbox_password':password,'mailbox_password_confirm':password,'mailbox_confirm':'1'}
    calls=provider.calls
    for changes,label in [({'csrf':'bad'},'CSRF'),({'mailbox_policy':'f'*32},'forged policy'),({'mailbox_password_confirm':'different'},'password mismatch'),({'mailbox_confirm':'0'},'confirmation')]:
        result=client.post(url,data={**original,**values,**changes})
        check('Mailbox request refuses '+label,not db('SELECT * FROM ci_provider_mailbox_requests') and provider.calls==calls and password not in result.text)
    # This client's mailbox page correctly has no creation form or CSRF field.
    # Use its real workspace session token so this POST reaches ownership checks.
    foreign_csrf=form_data(form_for(foreign.get(portal),'logout'))['csrf']
    result=foreign.post(url,data={**original,**values,'csrf':foreign_csrf})
    check('Foreign client cannot use captured mailbox permission',result.status_code==200 and 'Mailbox permissions changed or expired.' in result.text and password not in result.text and not db('SELECT * FROM ci_provider_mailbox_requests') and provider.calls==calls)
    context=browser.new_context(viewport={'width':1440,'height':1000});context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in client.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.goto(url)
    form=page.locator('form').filter(has=page.locator('[name=action][value='+create+']'))
    form.locator('[name=mailbox_policy]').select_option(original['mailbox_policy'])
    sent_fields={name:form.locator('[name='+name+']').input_value() for name in ['csrf','action','mailbox_policy','mailbox_request']}
    form.locator('[name=mailbox_local]').fill('hello');form.locator('[name=mailbox_password]').fill(password);form.locator('[name=mailbox_password_confirm]').fill(password);form.locator('[name=mailbox_confirm]').check()
    with page.expect_navigation():form.get_by_role('button',name='Create mailbox',exact=True).click()
    page.wait_for_load_state('networkidle')
    rows=db('SELECT * FROM ci_provider_mailbox_requests')
    check('Chromium creates one mailbox through authenticated fixed TLS transport',len(rows)==1 and provider.calls==calls+1 and len(provider.posts)==posts+1 and 'The provider confirmed mailbox creation.' in page.content() and not errors and not provider.errors)
    check('Created mailbox history and fresh form contain no password', 'hello@mailbox.example.invalid' in page.content() and password not in page.content() and page.locator('[name=mailbox_password]').input_value()=='')
    page.screenshot(path=str(qa/'Client-Mailbox-Desktop.png'),full_page=True);page.set_viewport_size({'width':390,'height':844});page.screenshot(path=str(qa/'Client-Mailbox-Mobile.png'),full_page=True)
    check('Client mailbox mobile view has no horizontal overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    context.close();calls=provider.calls
    client.post(url,data={**sent_fields,**values});check('Actual HTTP nonce replay cannot resend',provider.calls==calls and len(db('SELECT * FROM ci_provider_mailbox_requests'))==1)
    submit(client,url,create,{**values,'mailbox_policy':original['mailbox_policy']});check('New form cannot recreate confirmed mailbox',provider.calls==calls and len(db('SELECT * FROM ci_provider_mailbox_requests'))==1)
    check('Mailbox history stays isolated from another client','hello@mailbox.example.invalid' not in foreign.get(url).text)
    support=chosen_form(owner,admin+'?view=support','support_start','client_id',cid,{})
    check('Support cannot open mailbox controls',owner.get(support.url+'&hosting_mail=1').status_code==403 and provider.calls==calls)
    submit(owner,support.url,'support_end',{})
    policy(mailbox_enabled='0',client_enabled='0',capacity_reviewed='0')
    check('Revoking mailbox policy hides creation and preserves history',create not in client.get(url).text and 'hello@mailbox.example.invalid' in client.get(url).text and provider.calls==calls)
    for table in ['provider_mailbox_requests','provider_mailbox_policies','provider_operation_grants','provider_operations','audit']:
        check('Packaged mailbox secret excluded from '+table,all(password not in str(row) for row in db('SELECT * FROM ci_'+table)))
    check('Mailbox TLS fixture saw only expected authenticated requests',not provider.errors)
    client.close();foreign.close();owner.close()
