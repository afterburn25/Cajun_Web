"""Actual packaged expiry-timestamp owner/client UI; explicit local TLS reads only."""
import requests,time,hashlib,json
from urllib.parse import parse_qs, urlparse
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER


def run_browser(env,browser):
    check,soup,form_data,submit=(env[k] for k in ['check','soup','form_data','submit'])
    base,qa,provider=env['BASE'],env['QA'],env['provider_server'];admin=base+'/existing/admin/index.php';portal=base+'/existing/portal/index.php';settings=admin+'?view=provider-settings';url=portal+'?domain_expiry=1'
    token=env['token']
    db('DELETE FROM ci_rate_limits')
    # Give this workflow its own real owner budget; retain all prior intent/audit
    # bytes and use the staff invite/activation flow instead of changing authority.
    inherited_full = db('SELECT * FROM ci_provider_refreshes ORDER BY seq')
    inherited_audits = db('SELECT * FROM ci_audit ORDER BY id')
    original_owner_before_login = db('SELECT * FROM ci_users WHERE email=?', ['owner@example.invalid'])[0]
    original_profile = db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner_before_login['id']])[0]
    setup_diagnostics = []
    def setup_evidence(step, response, **facts):
        route = urlparse(response.url)
        outcome = parse_qs(route.query).get('view', [''])[0]
        setup_diagnostics.append({'step': step, 'status': response.status_code,
            'redirect_statuses': [r.status_code for r in response.history],
            'dashboard': route.path == urlparse(admin).path and outcome == 'dashboard',
            'login': route.path == urlparse(admin).path and outcome == 'login', **facts})
        # Persist only finite outcomes and field names, never account values,
        # password hashes, invitation/CSRF tokens, or response HTML.
        (qa/'domain-expiry-setup-diagnostics.json').write_text(json.dumps(setup_diagnostics, indent=2), encoding='utf-8')
    setup = requests.Session(); setup_response = submit(setup, admin + '?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    original_owner = db('SELECT * FROM ci_users WHERE id=?', [original_owner_before_login['id']])[0]
    changed_fields = sorted(key for key in set(original_owner_before_login) | set(original_owner)
                            if original_owner_before_login.get(key) != original_owner.get(key))
    setup_evidence('authorized_setup_login', setup_response, changed_user_fields=changed_fields,
        original_profile_unchanged=db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    check('Expiry setup login changes only permitted login bookkeeping',
          setup_response.status_code == 200 and setup_diagnostics[-1]['dashboard']
          and {k:v for k,v in original_owner.items() if k != 'last_login'} == {k:v for k,v in original_owner_before_login.items() if k != 'last_login'}
          and original_owner['last_login'] is not None and setup_diagnostics[-1]['original_profile_unchanged'])
    # All later assertions use the full post-login row: even last_login must
    # stay exact after this explicitly authorized setup authentication.
    staff_url = admin + '?view=staff'; expiry_email = 'expiry-observation-owner@example.invalid'
    check('Expiry fixture owner starts absent', not db('SELECT id FROM ci_users WHERE email=?', [expiry_email]))
    submit(setup, staff_url, 'staff_save', {'id': '0', 'name': 'Expiry observation owner', 'email': expiry_email, 'role': 'owner', 'enabled': '1', 'permissions[]': [], 'owner_password': OWNER})
    expiry_owner = db('SELECT id,password_hash FROM ci_users WHERE email=?', [expiry_email])[0]
    invite = chosen_form(setup, staff_url, 'staff_invite', 'id', expiry_owner['id'], {'owner_password': OWNER})
    invitation = soup(invite).select_one('[data-staff-invite]')['href']; code = parse_qs(urlparse(invitation).fragment)['token'][0]
    activation = requests.Session(); form = activation.get(invitation)
    activation_response = activation.post(admin + '?view=staff-setup', data={'action': 'staff_activate', 'csrf': token(form), 'token': code, 'password': OWNER, 'confirm_password': OWNER})
    activation.close(); setup.close()
    expiry_profile = db('SELECT role,enabled FROM ci_staff_profiles WHERE user_id=?', [expiry_owner['id']])
    expiry_activated = db('SELECT password_hash FROM ci_users WHERE id=?', [expiry_owner['id']])[0]['password_hash'] != ''
    setup_evidence('dedicated_owner_activation', activation_response,
        distinct_owner=expiry_owner['id'] != original_owner['id'], owner_role=expiry_profile == [{'role': 'owner', 'enabled': 1}], activated=expiry_activated,
        original_user_unchanged=db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner],
        original_profile_unchanged=db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    check('Expiry fixture activates a distinct authorized owner without changing original authority',
          activation_response.status_code == 200 and setup_diagnostics[-1]['login']
          and expiry_owner['id'] != original_owner['id'] and expiry_profile == [{'role': 'owner', 'enabled': 1}]
          and expiry_activated
          and db('SELECT * FROM ci_users WHERE id=?', [original_owner['id']]) == [original_owner]
          and db('SELECT * FROM ci_staff_profiles WHERE user_id=?', [original_owner['id']]) == [original_profile])
    owner = requests.Session(); submit(owner, admin + '?view=login', 'login', {'email': expiry_email, 'password': OWNER})
    check('Expiry dedicated owner has an unused read budget', not db('SELECT id FROM ci_provider_refreshes WHERE actor_id=?', [expiry_owner['id']]))

    client=requests.Session();submit(client,portal,'login',{'email':'registrar-client1@example.invalid','password':OWNER});other=requests.Session();submit(other,portal,'login',{'email':'registrar-client2@example.invalid','password':OWNER})
    cid=db('SELECT id FROM ci_studio_clients WHERE email=?',['registrar-client1@example.invalid'])[0]['id'];profile=db('SELECT * FROM ci_provider_profiles')[0]
    calls,posts=provider.calls,list(provider.posts);prepare='provider_inventory_expiry_prepare';refresh='provider_inventory_expiry_refresh'
    check('Expiry observations GET creates no storage or provider read',prepare in owner.get(settings).text and not db('SHOW TABLES LIKE "ci_domain_expiry_bindings"') and 'No expiry timestamps are available' in client.get(url).text and provider.calls==calls)
    for fields,label in [({'csrf':'bad','current_password':OWNER},'CSRF'),({'current_password':'wrong'},'password')]:
        submit(owner,settings,prepare,fields);check('Expiry observations preparation refuses '+label,not db('SHOW TABLES LIKE "ci_domain_expiry_bindings"') and provider.calls==calls)
    submit(owner,settings,prepare,{'current_password':OWNER});check('Expiry observations prepare is local and explicit',not db('SELECT * FROM ci_domain_expiry_bindings') and provider.calls==calls)
    check('Expiry observation support context is refused',owner.get(settings+'&support=fixture').status_code==403)
    old_domains=list(provider.registrar_domains);old_mode=provider.registrar_mode
    provider.registrar_mode='success';provider.registrar_domains=old_domains+[{'id':4701,'name':'expiry-client.invalid'},{'id':4702,'name':'expiry-sibling.invalid'}]
    # Earlier browser workflows have immutable evidence tied to their original read times.
    # Preserve every inherited intent and wait out only the real shared cooldown.
    inherited=db('SELECT id,started_epoch FROM ci_provider_refreshes ORDER BY seq')
    def wait_read():
        rows=db('SELECT MAX(started_epoch) AS latest FROM ci_provider_refreshes WHERE profile_id=?',[profile['id']]);latest=int(rows[0]['latest'] or 0)
        remaining=latest+31-int(time.time())
        if remaining>0:time.sleep(remaining)
    wait_read()
    response=submit(owner,settings,'provider_inventory_registrar_refresh',{'current_password':OWNER})
    check('Expiry observation fixture preserves inherited observation epochs',all(db('SELECT started_epoch FROM ci_provider_refreshes WHERE id=?',[row['id']])==[{'started_epoch':row['started_epoch']}] for row in inherited))
    source=db('SELECT id FROM ci_provider_refreshes WHERE operation=? ORDER BY seq DESC LIMIT 1',['domains.list'])[0]['id']
    check('Expiry observation fixture establishes one new complete registrar source',source not in {row['id'] for row in inherited} and db('SELECT state FROM ci_provider_refresh_results WHERE id=?',[source])==[{'state':'complete'}] and provider.get_paths[-1]=='/domain')
    chosen_form(owner,settings,'provider_inventory_registrar_link','domain_id',4701,{'client_id':cid,'current_password':OWNER})
    sibling=db('SELECT id FROM ci_studio_clients WHERE email=?',['registrar-client2@example.invalid'])[0]['id'];chosen_form(owner,settings,'provider_inventory_registrar_link','domain_id',4702,{'client_id':sibling,'current_password':OWNER})
    link=db('SELECT * FROM ci_provider_registrar_links WHERE domain_id=4701')[0];calls=provider.calls
    provider.expiry_dates={'expiry-client.invalid':'2028-02-29T01:02:03.000+05:45','expiry-sibling.invalid':'2029-01-01T00:00:00Z'}
    def fields():return form_data(next(f for f in soup(owner.get(settings)).select('form') if f.select_one('[name=action][value='+refresh+']') and f.select_one('[name=link_id]')['value']==link['id']))
    def read():
        wait_read()
        return chosen_form(owner,settings,refresh,'link_id',link['id'],{'current_password':OWNER})
    stale=fields()
    for changed,label in [({'csrf':'bad'},'CSRF'),({'current_password':'wrong'},'password'),({'link_revision':'999'},'assignment revision'),({'refresh_id':'f'*32},'source revision')]:
        owner.post(settings,data={**stale,'current_password':OWNER,**changed});check('Expiry observation refresh refuses '+label,not db('SELECT * FROM ci_domain_expiry_bindings') and provider.calls==calls)
    # Exercise the real shared cooldown without changing any retained observation or binding.
    wait_read()
    context=browser.new_context(viewport={'width':1440,'height':1000});context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in owner.cookies]);page=context.new_page();page.goto(settings);form=page.locator('form').filter(has=page.locator('[name=action][value='+refresh+']')).filter(has=page.locator('[name=link_id][value="'+link['id']+'"]'));form.locator('[name=current_password]').fill(OWNER)
    with page.expect_navigation(wait_until='domcontentloaded'):form.get_by_role('button',name='Refresh expiry timestamp',exact=True).click()
    expect(page.locator('#domain-expiry')).to_contain_text('Provider expiry timestamp: 2028-02-29T01:02:03.000+05:45')
    context.close();calls=provider.calls
    check('Chromium saves one exact expiry read with no provider mutation',len(db('SELECT * FROM ci_domain_expiry_timestamps'))==1 and provider.get_paths[-1]=='/domain/expiry-client.invalid/upstreamExpiryDate' and provider.posts==posts)
    response=client.get(url);check('Expiry client sees own timestamp observation without private provider fields','Provider expiry timestamp: 2028-02-29T01:02:03.000+05:45' in response.text and 'expiry-client.invalid' in response.text and 'expiry-sibling.invalid' not in response.text and 'EXPIRY_PRIVATE_MARKER' not in response.text and provider.calls==calls)
    check('Expiry observation route uses private headers','no-store' in response.headers.get('Cache-Control','') and response.headers.get('Referrer-Policy')=='no-referrer')
    check('Expiry other client query cannot reveal first timestamp','2028-02-29T01:02:03.000+05:45' not in other.get(url+'&client_id='+str(cid)+'&link_id='+link['id']).text)
    check('Expiry client page offers renewal review separately','Request renewal review' in response.text and 'Renewal dates' in response.text and 'does not confirm current domain status, a price, or a completed renewal' in response.text)
    for session,title,target,selector in [(owner,'Domain-Expiry-Owner',settings,'#domain-expiry'),(client,'Domain-Expiry-Client',url,'[data-domain-expiry]')]:
        context=browser.new_context();context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in session.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        for width,height,label in [(1440,1000,'Desktop'),(320,740,'Mobile')]:
            page.set_viewport_size({'width':width,'height':height});page.goto(target);page.evaluate('document.fonts.ready');expect(page.locator(selector).first).to_be_visible()
            if session is owner and label=='Mobile':expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
            page.evaluate('window.scrollTo(0,0)');page.screenshot(path=str(qa/(title+'-'+label+'.png')),full_page=True)
            check(title+' '+label+' fits viewport',not errors and page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        context.close()
    provider.expiry_mode='authentication';read();calls=provider.calls;response=client.get(url)
    check('New failed expiry refresh suppresses current date and labels history','Provider expiry timestamp: 2028-02-29T01:02:03.000+05:45' not in response.text and 'Observed expiry timestamp 2028-02-29T01:02:03.000+05:45' in response.text and 'latest expiry timestamp is unavailable' in response.text and 'private expiry marker' not in response.text)
    provider.expiry_mode='null';read();calls=provider.calls;response=client.get(url)
    check('Null expiry response stays unavailable without inferred status','Provider expiry timestamp: 2028-02-29T01:02:03.000+05:45' not in response.text and 'latest expiry timestamp is unavailable' in response.text and db('SELECT reason FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id WHERE f.operation=? ORDER BY f.seq DESC LIMIT 1',['domain.upstream_expiry_date'])==[{'reason':'timestamp_null'}])
    rows=db('SELECT * FROM ci_domain_expiry_bindings ORDER BY id');db('RENAME TABLE ci_domain_expiry_bindings TO ci_saved_expiry_bindings')
    try:
        check('Lost expiry bindings return controlled client refusal','needs to review the saved expiry timestamps' in client.get(url).text)
        owner.post(settings,data={'csrf':env['token'](owner.get(settings)),'action':prepare,'current_password':OWNER});check('Expiry owner cannot recreate lost authority',not db('SHOW TABLES LIKE "ci_domain_expiry_bindings"'))
    finally:db('RENAME TABLE ci_saved_expiry_bindings TO ci_domain_expiry_bindings')
    check('Exact expiry bindings restoration preserves latest failed state',db('SELECT * FROM ci_domain_expiry_bindings ORDER BY id')==rows and 'latest expiry timestamp is unavailable' in client.get(url).text)
    provider.expiry_mode='success';read();calls=provider.calls;chosen_form(owner,settings,'provider_inventory_registrar_relink','link_id',link['id'],{'current_password':OWNER})
    check('Expiry assignment reapproval removes prior-context timestamps','2028-02-29T01:02:03.000+05:45' not in client.get(url).text and 'refresh the expiry observation for this assignment' in client.get(url).text)
    read();calls=provider.calls;check('Explicit expiry refresh after reapproval restores own timestamp','Provider expiry timestamp: 2028-02-29T01:02:03.000+05:45' in client.get(url).text)
    for path in ['app/providers/domain_expiry.php','app/providers/domain_expiry_storage.php','app/providers/domain_expiry_views.php','admin/controllers/domain_expiry.php','admin/views/domain_expiry.php','portal/controllers/domain_expiry.php','portal/views/domain_expiry.php','database/schema/domain_expiry_schema.php','includes/foundation/twentyi-domain-expiry/DomainExpiry.php','includes/foundation/twentyi-domain-expiry/DomainExpiryBinding.php','includes/foundation/twentyi-domain-expiry/DomainExpiryObservation.php','includes/foundation/twentyi-domain-expiry/DomainExpiryReader.php']:
        for prefix in ['/existing/','/fresh/']:check('Expiry observation private HTTP denial '+prefix+path,requests.get(base+prefix+path).status_code==404)
    check('Expiry observation browser workflow sends only explicit reads',provider.calls==calls and provider.posts==posts and not provider.errors)
    check('Expiry observation fixture leaves all inherited read times intact',all(db('SELECT started_epoch FROM ci_provider_refreshes WHERE id=?',[row['id']])==[{'started_epoch':row['started_epoch']}] for row in inherited))
    retained=db('SELECT * FROM ci_provider_refreshes WHERE seq<=? ORDER BY seq',[max((row['seq'] for row in inherited_full),default=0)])
    retained_audits=db('SELECT * FROM ci_audit WHERE id<=? ORDER BY id',[max((row['id'] for row in inherited_audits),default=0)])
    check('Expiry fixture preserves every inherited intent and audit byte',retained==inherited_full and retained_audits==inherited_audits)
    own_reads=db('SELECT operation FROM ci_provider_refreshes WHERE actor_id=? ORDER BY seq',[expiry_owner['id']])
    own_results=db('SELECT r.state FROM ci_provider_refresh_results r JOIN ci_provider_refreshes f ON f.id=r.id WHERE f.actor_id=? ORDER BY f.seq',[expiry_owner['id']])
    check('Expiry dedicated owner retains exact six-read sequence',own_reads==[{'operation':'domains.list'}]+[{'operation':'domain.upstream_expiry_date'}]*5 and own_results==[{'state':state} for state in ['complete','complete','unavailable','unavailable','complete','complete']])
    check('Expiry workflow preserves original owner authority',db('SELECT * FROM ci_users WHERE id=?',[original_owner['id']])==[original_owner] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?',[original_owner['id']])==[original_profile])
    (qa/'domain-expiry-history-preservation.json').write_text(json.dumps({'inherited_intents':len(inherited_full),'inherited_audits':len(inherited_audits),'intents_sha256':hashlib.sha256(json.dumps(inherited_full,sort_keys=True).encode()).hexdigest(),'audits_sha256':hashlib.sha256(json.dumps(inherited_audits,sort_keys=True).encode()).hexdigest(),'retained_intents_sha256':hashlib.sha256(json.dumps(retained,sort_keys=True).encode()).hexdigest(),'retained_audits_sha256':hashlib.sha256(json.dumps(retained_audits,sort_keys=True).encode()).hexdigest(),'dedicated_actor':expiry_owner['id'],'dedicated_reads':len(own_reads),'operations':[row['operation'] for row in own_reads],'states':[row['state'] for row in own_results]},indent=2),encoding='utf-8')
    provider.registrar_domains=old_domains;provider.registrar_mode=old_mode;client.close();other.close();owner.close()
