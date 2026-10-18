"""Real extracted-package forms and Chromium against the loopback TLS fixture only."""
import json,time
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit','provider_server']})
    db('DELETE FROM ci_rate_limits')
    root=BASE+'/existing/admin/index.php';url=root+'?view=provider-settings'
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    start_calls=provider_server.calls;start_posts=list(provider_server.posts)
    page=owner.get(url)
    check('Packaged domain search offers explicit preparation without a GET migration',page.status_code==200 and 'Prepare domain search' in page.text and db('SHOW TABLES LIKE "ci_provider_domain_searches"')==[] and provider_server.calls==start_calls)
    before=db('SELECT * FROM ci_provider_profiles');before_inventory=db('SELECT * FROM ci_provider_refreshes ORDER BY id')
    for label,change in [('CSRF',{'csrf':'invalid','current_password':OWNER}),('password',{'current_password':'wrong'})]:
        r=submit(owner,url,'provider_inventory_domain_search_prepare',change)
        check('Domain search preparation refuses invalid '+label,db('SHOW TABLES LIKE "ci_provider_domain_searches"')==[] and provider_server.calls==start_calls)
    r=submit(owner,url,'provider_inventory_domain_search_prepare',{'current_password':OWNER})
    check('Domain search preparation preserves existing connection and inventory',soup(r).select_one('[data-domain-search-settings]') is not None and db('SELECT * FROM ci_provider_profiles')==before and db('SELECT * FROM ci_provider_refreshes ORDER BY id')==before_inventory and provider_server.calls==start_calls)
    pid=before[0]['id']
    def config(**change):
        fields=form_data(soup(owner.get(url)).select_one('[data-domain-search-settings]'))
        fields.update(current_password=OWNER,search_tlds='invalid',search_enabled='1');fields.update(change)
        return owner.post(url,data=fields)
    config(search_tlds='invalid?bad')
    check('Malformed offering stays inline without a lookup','invalid?bad' in owner.post(url,data={'csrf':token(owner.get(url)),'action':'provider_inventory_domain_search_configure','profile_id':pid,'revision':str(before[0]['revision']),'search_revision':'0','current_password':OWNER,'search_tlds':'invalid?bad','search_enabled':'1'}).text and not db("SELECT value FROM ci_settings WHERE name='provider_domain_search_configuration'") and provider_server.calls==start_calls)
    r=config()
    check('Owner can enable typed domain lookup for selected extensions',soup(r).select_one('[data-domain-search-form]') is not None and provider_server.calls==start_calls)
    stale=form_data(soup(owner.get(url)).select_one('[data-domain-search-form]'));stale['domain_query']='example.invalid'
    stale_config=form_data(soup(owner.get(url)).select_one('[data-domain-search-settings]'));stale_config.update(current_password=OWNER,search_tlds='com',search_enabled='1')
    config();saved_config=db("SELECT value FROM ci_settings WHERE name='provider_domain_search_configuration'")
    r=owner.post(url,data=stale_config)
    check('Stale domain configuration retains original revision and cannot overwrite the offering','settings changed' in r.text and db("SELECT value FROM ci_settings WHERE name='provider_domain_search_configuration'")==saved_config and soup(r).select_one('[data-domain-search-settings] [name=search_revision]')['value']==stale_config['search_revision'])
    r=owner.post(url,data=stale)
    check('Old search-policy form is refused before networking','settings changed' in r.text and provider_server.calls==start_calls and not db('SELECT * FROM ci_provider_domain_searches') and soup(r).select_one('[data-domain-search-form] [name=search_revision]')['value']==stale['search_revision'])
    def search(**change):
        fields=form_data(soup(owner.get(url)).select_one('[data-domain-search-form]'));fields['domain_query']='EXAMPLE.invalid';fields.update(change)
        return owner.post(url,data=fields)
    r=search(csrf='invalid')
    check('Domain search CSRF rejection retains no attempt','session expired' in r.text.lower() and not db('SELECT * FROM ci_provider_domain_searches') and provider_server.calls==start_calls)
    r=search(domain_query='example.com')
    check('Unsold exact-domain query is refused locally','not in your configured offering' in r.text and not db('SELECT * FROM ci_provider_domain_searches') and provider_server.calls==start_calls)
    r=search(domain_query='"><script>alert(1)</script>')
    check('Malformed domain remains escaped in the actual form','&lt;script&gt;' in r.text and soup(r).find('script',string='alert(1)') is None and provider_server.calls==start_calls)
    forged=form_data(soup(owner.get(url)).select_one('[data-domain-search-form]'));forged['domain_query']='example.invalid'
    visitor=requests.Session();visitor.post(url,data=forged)
    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    member.post(root+'?view=security',data=dict(forged,csrf=token(member.get(root+'?view=security'))))
    check('Visitor and administrator cannot run owner domain search',provider_server.calls==start_calls and not db('SELECT * FROM ci_provider_domain_searches') and member.get(url).status_code==403)
    r=owner.get(url+'&support=fixture')
    check('Support session cannot see or use reseller domain search',r.status_code==403 and soup(r).select_one('[data-domain-search-form]') is None)
    client=requests.Session();submit(client,BASE+'/existing/portal/index.php','login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    client.post(url,data=forged)
    check('Client cannot acquire reseller search permission or history',provider_server.calls==start_calls and soup(client.get(url)).select_one('[data-domain-search]') is None)
    provider_server.domain_mode='success';r=search()
    results=db('SELECT * FROM ci_provider_domain_search_results')
    result=json.loads(results[0]['result']) if results else {}
    check('Actual packaged lookup uses one canonical verified HTTPS GET',provider_server.calls==start_calls+1 and provider_server.get_paths[-1]=='/domain-search/example.invalid' and len(results)==1 and result.get('registration_available') is True)
    history=soup(r).select_one('[data-domain-search-history]')
    check('Native history shows observed availability and only offered suggestions',history is not None and 'Available when checked' in history.text and 'suggested.invalid' in history.text and 'unsold.com' not in history.text and 'requiring a separate check' in history.text)
    check('Domain search makes no purchase or provider write',provider_server.posts==start_posts)
    check('Domain search response is private and omits secrets','no-store' in r.headers.get('Cache-Control','') and r.headers.get('Referrer-Policy')=='no-referrer' and 'SYNTHETIC_RAW_GENERAL_KEY' not in r.text)
    r=search()
    check('Actual form replay cannot bypass durable cooldown','Wait 30 seconds' in r.text and provider_server.calls==start_calls+1 and len(db('SELECT * FROM ci_provider_domain_searches'))==1)
    db('UPDATE ci_provider_domain_searches SET started_epoch=started_epoch-120');provider_server.domain_mode='partial';r=search()
    check('Incomplete domain stream is recorded honestly',len(db("SELECT * FROM ci_provider_domain_search_results WHERE state='partial'"))==1 and 'incomplete or unverified result' in r.text)
    db('UPDATE ci_provider_domain_searches SET started_epoch=started_epoch-120');provider_server.domain_mode='limited';r=search()
    calls=provider_server.calls
    check('HTTP provider rate limit creates a visible durable pause',len(db("SELECT * FROM ci_provider_domain_search_results WHERE reason='rate_limited'"))==1 and 'Search is paused for 24 hours' in r.text and 'private synthetic domain response' not in r.text)
    db('UPDATE ci_provider_domain_searches SET started_epoch=started_epoch-120');config();r=search()
    check('Configuration changes cannot bypass provider rate-limit pause','paused for 24 hours' in r.text and provider_server.calls==calls)
    provider_server.domain_mode='success';db('UPDATE ci_provider_domain_search_results SET finished_epoch=finished_epoch-86401 WHERE reason=?',['rate_limited'])
    context=browser.new_context(viewport={'width':1440,'height':1050});page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_url(root+'?view=dashboard');page.goto(url)
    form=page.locator('[data-domain-search-form]');form.locator('[name=domain_query]').fill('example.invalid');form.get_by_role('button',name='Check availability',exact=True).click()
    expect(page.locator('[data-domain-search-history]')).to_contain_text('Available when checked')
    check('Chromium domain form reaches the isolated provider exactly once',provider_server.calls==calls+1)
    for width,height,label in [(1440,1050,'Desktop'),(320,740,'Mobile')]:
        page.set_viewport_size({'width':width,'height':height});page.evaluate('document.fonts.ready');page.evaluate('window.scrollTo({top:0,left:0,behavior:"instant"})')
        if label=='Mobile':
            expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
        page.screenshot(path=str(QA/('Domain-Search-'+label+'.png')),full_page=True)
        check('Domain search '+label+' fits the viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('Domain-search browser has no script errors',not errors);context.close()
    for path in ['app/providers/domain_search.php','database/schema/provider_domain_search_schema.php','database/migrations/provider_domain_search_migrate.php','admin/views/provider_domain_search.php','includes/foundation/twentyi-inventory-reader/DomainSearch.php']:
        check('Domain search internal HTTP denial '+path,requests.get(BASE+'/existing/'+path).status_code==404)
    saved={t:db('SELECT * FROM ci_'+t+' ORDER BY id') for t in ['provider_domain_searches','provider_domain_search_results']}
    r=owner.post(url,data={'csrf':token(owner.get(url)),'action':'provider_inventory_domain_search_prepare','current_password':OWNER})
    check('HTTP preparation reapplication preserves all saved search history',r.status_code==200 and all(db('SELECT * FROM ci_'+t+' ORDER BY id')==rows for t,rows in saved.items()))
    config(search_enabled='0');r=owner.post(url,data=forged)
    check('Disabled search refuses captured forms and keeps history',provider_server.calls==calls+1 and all(db('SELECT * FROM ci_'+t+' ORDER BY id')==rows for t,rows in saved.items()) and soup(r).select_one('[data-domain-search-form]') is None)
    check('Domain search preserves original inventory and provider writes',provider_server.posts==start_posts and db('SELECT * FROM ci_provider_refreshes ORDER BY id')==before_inventory and not provider_server.errors)
    from provider_domain_client_cases import run_browser as client_domain_browser
    client_domain_browser(env,browser,owner,url,config)
    owner.close();member.close();visitor.close();client.close()
