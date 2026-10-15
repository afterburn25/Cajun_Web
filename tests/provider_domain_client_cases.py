"""Actual owner grant, client portal forms and isolated authenticated domain lookup."""
import requests
from release_cases import db, chosen_form
from staff_cases import OWNER


def run_browser(env, browser, owner, settings_url, configure_owner):
    check, soup, form_data, form_for, submit = (env[k] for k in ['check', 'soup', 'form_data', 'form_for', 'submit'])
    base, qa, provider = env['BASE'], env['QA'], env['provider_server']
    admin, portal = base+'/existing/admin/index.php', base+'/existing/portal/index.php'
    url = portal+'?domains=1'
    prepare = 'provider_inventory_domain_search_client_prepare'
    configure = 'provider_inventory_domain_search_client_configure'
    action = 'client_domain_search'
    calls, posts = provider.calls, list(provider.posts)
    owner_history = db('SELECT * FROM ci_provider_domain_searches ORDER BY id')
    check('Client search GET offers preparation without DDL', prepare in owner.get(settings_url).text and not db('SHOW TABLES LIKE "ci_provider_domain_client_searches"'))
    for changes, label in [({'csrf': 'bad', 'current_password': OWNER}, 'CSRF'), ({'current_password': 'wrong'}, 'password')]:
        submit(owner, settings_url, prepare, changes)
        check('Client search preparation refuses '+label, not db('SHOW TABLES LIKE "ci_provider_domain_client_searches"') and provider.calls==calls)
    submit(owner, settings_url, prepare, {'current_password': OWNER})
    check('Client search preparation preserves all owner records', owner_history==db('SELECT * FROM ci_provider_domain_searches ORDER BY id') and not db('SELECT * FROM ci_provider_domain_client_searches'))
    clients=[]
    for number in [1, 2]:
        email='domain-client'+str(number)+'@example.invalid'
        chosen_form(owner, admin+'?view=studio-clients', 'studio_client', 'id', 0, {'name': 'Domain client '+str(number), 'email': email})
        cid=db('SELECT id FROM ci_studio_clients WHERE email=?', [email])[0]['id']
        response=chosen_form(owner, admin+'?view=studio-clients', 'studio_invite', 'id', cid, {})
        invitation=soup(response).select_one('.notice.info a[href*="invite="]')['href']
        session=requests.Session();submit(session, invitation, 'activate', {'password': OWNER, 'confirm_password': OWNER});submit(session, portal, 'login', {'email': email, 'password': OWNER})
        clients.append((cid, session))
    (cid, client), (_, foreign)=clients
    check('Client lookup starts unavailable until owner explicitly enables it', action not in client.get(url).text and 'Find a domain' not in client.get(portal).text and provider.calls==calls)
    configure_owner()
    fields=form_data(form_for(owner.get(settings_url), configure))
    for changes, label in [({'csrf': 'bad'}, 'CSRF'), ({'current_password': 'wrong'}, 'password')]:
        owner.post(settings_url, data={**fields, 'client_search_enabled': '1', 'current_password': OWNER, **changes})
        check('Client search approval refuses '+label, not db('SELECT * FROM ci_provider_domain_client_policies') and provider.calls==calls)
    owner.post(settings_url, data={**fields, 'client_search_enabled': '1', 'current_password': OWNER})
    r=client.get(url)
    check('Owner approval exposes native client form without provider identity', action in r.text and 'Find a domain' in client.get(portal).text and 'SYNTHETIC_RAW_GENERAL_KEY' not in r.text and 'profile_id' not in r.text and 'current_password' not in r.text)
    check('Client search uses private uncached subfolder route', 'no-store' in r.headers.get('Cache-Control', '') and r.headers.get('Referrer-Policy')=='no-referrer' and soup(r).select_one('a[href="'+portal+'"]') is not None)
    original=form_data(form_for(r, action))
    for changes, label in [({'csrf': 'bad'}, 'CSRF'), ({'domain_query': 'example.com'}, 'unoffered extension'), ({'domain_query': '<script>bad</script>'}, 'invalid query'), ({'search_policy': 'f'*32}, 'stale approval')]:
        r=client.post(url, data={**original, 'domain_query': 'example.invalid', **changes})
        check('Client domain form refuses '+label, not db('SELECT * FROM ci_provider_domain_client_searches') and provider.calls==calls and '<script>bad</script>' not in r.text)
    chosen_form(owner, admin+'?view=support', 'support_policy', 'client_id', cid, {'allowed':'1','owner_password':OWNER})
    check('Fixture owner explicitly permits support for the new client', db('SELECT allowed FROM ci_support_clients WHERE client_id=?',[cid])[0]['allowed']==1)
    support=chosen_form(owner, admin+'?view=support', 'support_start', 'client_id', cid, {})
    support_url=support.url
    check('Fixture opens a real client support session', '?support=' in support_url and 'Support session' in support.text)
    check('Actual support session cannot open client domain search', owner.get(support_url+'&domains=1').status_code==403 and provider.calls==calls)
    submit(owner, support_url, 'support_end', {})
    db('UPDATE ci_provider_domain_searches SET started_epoch=started_epoch-7200')
    context=browser.new_context(viewport={'width':1440,'height':1000})
    context.add_cookies([{'name': c.name, 'value': c.value, 'url': base} for c in client.cookies])
    page=context.new_page();errors=[];page.on('pageerror', lambda e: errors.append(str(e)));page.goto(url)
    form=page.locator('form').filter(has=page.locator('[name=action][value='+action+']'))
    sent_fields={name: form.locator('[name='+name+']').input_value() for name in ['csrf','action','search_policy','search_request']}
    form.locator('[name=domain_query]').fill('example.invalid')
    with page.expect_navigation(): form.get_by_role('button', name='Check availability', exact=True).click()
    page.wait_for_load_state('networkidle')
    records=db('SELECT * FROM ci_provider_domain_client_searches')
    check('Real Chromium client form makes one authenticated lookup', provider.calls==calls+1 and len(records)==1 and records[0]['client_id']==cid and 'Available when checked' in page.content() and not errors)
    check('Client history shows only offered suggestions', 'suggested.invalid' in page.content() and 'unsold.com' not in page.content() and 'check separately' in page.content())
    for width, height, label in [(1440,1000,'Desktop'), (320,740,'Mobile')]:
        page.set_viewport_size({'width':width,'height':height});page.evaluate('document.fonts.ready')
        page.screenshot(path=str(qa/('Client-Domain-Search-'+label+'.png')),full_page=True)
        check('Client domain search '+label+' fits viewport without script errors', not errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    context.close()
    client.post(url, data={**sent_fields, 'domain_query':'example.invalid'})
    check('Replaying completed client request does not repeat lookup', provider.calls==calls+1 and db('SELECT * FROM ci_provider_domain_client_searches')==records)
    foreign_fields=form_data(form_for(foreign.get(url), action))
    foreign.post(url, data={**foreign_fields, 'search_request':records[0]['id'], 'domain_query':'example.invalid'})
    check('Other client cannot view or reuse first client lookup', 'Available when checked' not in foreign.get(url).text and 'example.invalid' not in soup(foreign.get(url)).select_one('[data-client-domain-history]').text and provider.calls==calls+1)
    r=submit(owner, settings_url, 'provider_inventory_domain_search_run', {'domain_query':'example.invalid'})
    check('Client request consumes shared owner cooldown over HTTP', 'Wait 30 seconds' in r.text and provider.calls==calls+1)
    db('UPDATE ci_provider_domain_client_searches SET started_epoch=started_epoch-120')
    provider.domain_mode='partial';submit(foreign, url, action, {'domain_query':'example.invalid'})
    check('Incomplete client result never claims available', 'Available when checked' not in foreign.get(url).text and 'incomplete result' in foreign.get(url).text)
    db('UPDATE ci_provider_domain_client_searches SET started_epoch=started_epoch-120')
    provider.domain_mode='limited';submit(foreign, url, action, {'domain_query':'example.invalid'});paused_calls=provider.calls
    db('UPDATE ci_provider_domain_client_searches SET started_epoch=started_epoch-120')
    r=submit(client, url, action, {'domain_query':'example.invalid'})
    check('Provider pause from one client applies to another client', provider.calls==paused_calls and 'paused for 24 hours' in r.text)
    r=submit(owner, settings_url, 'provider_inventory_domain_search_run', {'domain_query':'example.invalid'})
    check('Provider client pause applies to owner lookups', provider.calls==paused_calls and 'paused for 24 hours' in r.text)
    provider.domain_mode='success';db('UPDATE ci_provider_domain_client_results SET finished_epoch=finished_epoch-86401 WHERE reason=?',['rate_limited'])
    retained={t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['provider_domain_client_policies','provider_domain_client_searches','provider_domain_client_results']}
    db('RENAME TABLE ci_provider_domain_client_results TO ci_domain_client_saved_results')
    try:
        r=client.get(url)
        check('Lost client history produces controlled unavailable view without DDL', r.status_code==200 and 'Search history is temporarily unavailable' in r.text and not db('SHOW TABLES LIKE "ci_provider_domain_client_results"'))
        submit(owner, settings_url, prepare, {'current_password':OWNER})
        check('Owner cannot recreate lost client result history through preparation', not db('SHOW TABLES LIKE "ci_provider_domain_client_results"'))
    finally: db('RENAME TABLE ci_domain_client_saved_results TO ci_provider_domain_client_results')
    check('Original client search restoration preserves all saved records', all(db('SELECT * FROM ci_'+t+' ORDER BY id')==rows for t,rows in retained.items()) and 'Available when checked' in client.get(url).text)
    submit(owner, settings_url, configure, {'client_search_enabled':'0','current_password':OWNER})
    r=client.post(url, data={**sent_fields,'search_request':'e'*32,'domain_query':'example.invalid'})
    check('Owner revocation removes client form and keeps prior search results', action not in r.text and 'Available when checked' in r.text and provider.calls==paused_calls)
    check('Client search performs no provider writes or leaks raw provider responses',provider.posts==posts and not provider.errors and 'private synthetic domain response' not in foreign.get(url).text)
    configure_owner(search_enabled='0')
    for path in ['app/providers/domain_search_clients.php','database/schema/provider_domain_client_schema.php','portal/controllers/domain_search.php','portal/views/domain_search.php','admin/views/provider_domain_clients.php']:
        check('Client search internal HTTP denial '+path, requests.get(base+'/existing/'+path).status_code==404)
    client.close();foreign.close()
