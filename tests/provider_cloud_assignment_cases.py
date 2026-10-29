"""Actual packaged owner/client cloud assignment UI; synthetic local provider only."""
import copy
import hashlib
import json
import time
from urllib.parse import parse_qs, urlparse
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER, STAFF
from cloud_assignment_fixture import dedicated_cloud_owner


def run_browser(env, browser):
    base, qa, check, provider = (env[k] for k in ('BASE', 'QA', 'check', 'provider_server'))
    soup, token, submit, form_data = (env[k] for k in ('soup', 'token', 'submit', 'form_data'))
    admin=base+'/existing/admin/index.php';url=admin+'?view=provider-settings&focus=cloud-assignment';portal=base+'/existing/portal/index.php';services=portal+'?services=1'
    db('DELETE FROM ci_rate_limits')
    inherited={name:db('SELECT * FROM ci_'+name+' ORDER BY '+('seq' if name=='provider_refreshes' else 'id')) for name in ['provider_refreshes','audit','provider_cloud_assignment_evidence','provider_cloud_links']}
    before_owner=db('SELECT * FROM ci_users WHERE email=?',['owner@example.invalid'])[0]
    original_staff=db('SELECT * FROM ci_staff_profiles WHERE user_id=?',[before_owner['id']])
    setup=requests.Session();login=submit(setup,admin+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    original_owner=db('SELECT * FROM ci_users WHERE id=?',[before_owner['id']])[0]
    check('Cloud assignment setup login preserves authority apart from expected login metadata',login.status_code==200 and urlparse(login.url).path==urlparse(admin).path and urlparse(login.url).query=='view=dashboard'
          and {k:v for k,v in original_owner.items() if k!='last_login'}=={k:v for k,v in before_owner.items() if k!='last_login'}
          and original_owner['last_login'] is not None and db('SELECT * FROM ci_staff_profiles WHERE user_id=?',[before_owner['id']])==original_staff)
    owner,actor=dedicated_cloud_owner(env,setup,'cloud-assignment-owner@example.invalid','Cloud assignment evidence owner');setup.close()
    member=requests.Session();submit(member,admin+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    client=requests.Session();submit(client,portal,'login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    other=requests.Session();submit(other,portal,'login',{'email':'bob@example.invalid','password':'Synthetic client passphrase 2026'})
    alice=db('SELECT id FROM ci_studio_clients WHERE email=?',['alice@example.invalid'])[0]['id'];bob=db('SELECT id FROM ci_studio_clients WHERE email=?',['bob@example.invalid'])[0]['id']
    profile=db('SELECT id,revision FROM ci_provider_profiles WHERE provider_code=?',['twenty-i'])[0]
    original_cloud=copy.deepcopy(provider.cloud_servers);original_mode=provider.cloud_mode;calls=provider.calls;posts=copy.deepcopy(provider.posts)
    check('Cloud assignment fixture has a distinct unassigned server identity',not db('SELECT id FROM ci_provider_cloud_links WHERE server_id=2601') and all(v.get('id')!=2601 for v in original_cloud))
    item={'id':2601,'name':'cloud-assignment.browser.invalid','location':'dc_location-uk','configuration':{'CpuCores':2,'RamMb':2048,'OsDiskSizeGb':40,'DataDiskSizeGb':0},'packageIds':[2600991]}
    provider.cloud_servers=original_cloud+[item];provider.cloud_mode='success';diagnostics=[]
    def post(action,**fields):
        page=owner.get(url)
        return owner.post(url,data={'csrf':token(page),'action':action,'profile_id':profile['id'],'revision':str(profile['revision']),'current_password':OWNER,**fields})
    def cool():
        epoch=db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes WHERE profile_id=?',[profile['id']])[0]['epoch']
        time.sleep(max(0,int(epoch or 0)+31-time.time()))
    def evidence(label,response):
        check(label+' retains the focused cloud assignment workspace',parse_qs(urlparse(response.url).query).get('focus')==['cloud-assignment'] and soup(response).select_one('[data-cloud-assignment-focus]') is not None)
        section=soup(response).select_one('[data-cloud-assignments]')
        rows=db('SELECT f.id,f.seq,f.operation,r.state,r.reason,r.package_count,r.skipped FROM ci_provider_refreshes f LEFT JOIN ci_provider_refresh_results r ON r.id=f.id WHERE f.actor_id=? ORDER BY f.seq',[actor])
        diagnostics.append({'step':label,'status':response.status_code,'read_results':rows,'provider_calls':provider.calls,'post_count':len(provider.posts),
                            'assignment_unavailable':bool(section and section.select_one('[data-cloud-assignment-unavailable]')),
                            'own_assignment_count':len(db('SELECT id FROM ci_provider_cloud_links WHERE server_id=2601'))})
        (qa/'cloud-assignment-response-diagnostics.json').write_text(json.dumps(diagnostics,indent=2))
    def capture(session,title,address,selector):
        context=browser.new_context(viewport={'width':1440,'height':1050});context.add_cookies([{'name':c.name,'value':c.value,'domain':c.domain,'path':c.path} for c in session.cookies])
        page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        for width,height,label in [(1440,1050,'Desktop'),(320,740,'Mobile')]:
            page.set_viewport_size({'width':width,'height':height});page.goto(address);page.evaluate('document.fonts.ready');expect(page.locator(selector)).to_be_visible();page.screenshot(path=str(qa/(title+'-'+label+'.png')),full_page=True)
            check(title+' '+label+' fits viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        check(title+' has no script errors',not errors);context.close()
    def card(response):
        return next((v for v in soup(response).select('[data-client-cloud]') if 'Cloud server #2601' in v.text or 'cloud-assignment.browser.invalid' in v.text),None)
    focused=owner.get(url);focused_soup=soup(focused)
    check('Cloud assignment focus keeps cloud controls and full settings navigation',focused.status_code==200 and any(urlparse(a.get('href','')).query=='view=provider-settings' for a in focused_soup.select('[data-cloud-assignment-focus] a')) and focused_soup.select_one('[data-provider-cloud-refresh]') is not None)
    check('Cloud assignment focus excludes unrelated provider workspaces',focused_soup.select_one('[data-provider-editor], [data-provider-inventory], [data-cloudsite-create], [data-stackcp-auth]') is None)
    check('Cloud assignment status uses prepared history without a new provider call',focused_soup.select_one('[data-cloud-assignment-prepare]') is None and provider.calls==calls)
    cool();response=post('provider_inventory_cloud_refresh');evidence('Initial complete cloud read',response)
    refresh=diagnostics[-1]['read_results'][-1]['id'];check('Cloud assignment read uses the exact shared cloud GET',provider.calls==calls+1 and provider.get_paths[-1]=='/cloud_server' and diagnostics[-1]['read_results'][-1]['state']=='complete')
    form=soup(response).select_one('[data-cloud-link]');check('Fresh complete cloud observation exposes an explicit local client assignment',form is not None and '2601' in form.text)
    fields=form_data(form);fields.update(server_id='2601',client_id=str(alice),current_password=OWNER)
    for label,delta,reason in [('CSRF',{'csrf':'bad'},'session expired'),('password',{'current_password':'wrong'},'current password'),('old observation',{'refresh_id':'f'*32},'latest complete observation')]:
        denied=owner.post(url,data={**fields,**delta});check('Cloud assignment owner form refuses '+label,denied.status_code==200 and reason in denied.text.lower() and not db('SELECT id FROM ci_provider_cloud_links WHERE server_id=2601') and provider.calls==calls+1)
    check('Cloud assignment administrator and support selectors remain blocked',member.get(url).status_code==403 and owner.get(url+'&support=fixture').status_code==403)
    response=owner.post(url,data=fields);evidence('Local client approval',response);link=db('SELECT * FROM ci_provider_cloud_links WHERE server_id=2601')[0]
    check('Cloud assignment saves one exact owner client read binding with no provider write',link['client_id']==alice and link['revision']==1 and link['refresh_id']==refresh and link['linked_by']==actor and provider.calls==calls+1 and provider.posts==posts)
    mine=client.get(services);own_card=card(mine)
    check('Assigned client sees only safe current saved cloud specifications',mine.status_code==200 and own_card is not None and 'Current reviewed assignment' in own_card.text and '2048' in own_card.text and '2600991' not in own_card.text and profile['id'] not in own_card.text and 'not a live health check' in own_card.text and 'no-store' in mine.headers.get('Cache-Control',''))
    check('Unassigned client receives none of the cloud assignment facts',card(other.get(services)) is None)
    capture(owner,'Cloud-Assignment-Current-Owner',url,'[data-cloud-assignments]');capture(client,'Cloud-Assignment-Current-Client',services,'[data-client-cloud]:has-text("cloud-assignment.browser.invalid")')
    cool();provider.cloud_mode='authentication';response=post('provider_inventory_cloud_refresh');evidence('Later unavailable cloud read',response)
    denied=post('provider_inventory_cloud_relink',link_id=link['id'],link_revision='1',refresh_id=refresh)
    check('Later failed cloud intent refuses captured approval without new event','latest complete observation' in denied.text.lower() and db('SELECT * FROM ci_provider_cloud_links WHERE id=?',[link['id']])==[link] and provider.calls==calls+2)
    own_card=card(client.get(services));check('Unavailable cloud read preserves original authorized facts only as historical',own_card is not None and 'Historical saved specifications' in own_card.text and 'Current reviewed assignment' not in own_card.text and '2048' in own_card.text)
    capture(client,'Cloud-Assignment-Historical-Client',services,'[data-client-cloud]:has-text("cloud-assignment.browser.invalid")')
    cool();provider.cloud_mode='success';provider.cloud_servers=original_cloud+[item,{'id':False}];response=post('provider_inventory_cloud_refresh');evidence('Partial cloud read',response)
    partial=diagnostics[-1]['read_results'][-1];denied=post('provider_inventory_cloud_relink',link_id=link['id'],link_revision='1',refresh_id=partial['id'])
    check('Partial cloud facts remain historical and cannot approve a client',partial['state']=='partial' and partial['skipped']==1 and 'latest complete observation' in denied.text.lower() and db('SELECT * FROM ci_provider_cloud_links WHERE id=?',[link['id']])==[link] and 'Historical saved specifications' in card(client.get(services)).text and provider.calls==calls+3)
    cool();provider.cloud_servers=original_cloud+[item];response=post('provider_inventory_cloud_refresh');evidence('New complete cloud read',response)
    refresh=diagnostics[-1]['read_results'][-1]['id'];check('New exact complete sequence restores original unchanged assignment currentness','Current reviewed assignment' in card(client.get(services)).text and db('SELECT * FROM ci_provider_cloud_links WHERE id=?',[link['id']])==[link])
    retained_head=db('SELECT * FROM ci_settings WHERE name=?',['provider_cloud_assignment_head'])[0];retained_rows=db('SELECT * FROM ci_provider_cloud_assignment_evidence ORDER BY id');retained_audits=db('SELECT * FROM ci_audit ORDER BY id')
    db('DELETE FROM ci_settings WHERE name=?',['provider_cloud_assignment_head'])
    try:
        response=owner.get(url);evidence('Missing history head',response)
        section=soup(response).select_one('[data-cloud-assignments]')
        check('Owner missing cloud history shows recovery without approvals or read-path repair',response.status_code==200 and section is not None and 'needs recovery from the original database' in section.text and not section.select('form') and not db('SELECT * FROM ci_settings WHERE name=?',['provider_cloud_assignment_head']))
        unavailable=client.get(services);check('Client missing cloud history hides facts with a private unavailable message',unavailable.status_code==200 and soup(unavailable).select_one('[data-client-cloud-unavailable]') is not None and card(unavailable) is None and 'original database' not in unavailable.text)
        refused=post('provider_inventory_cloud_assignment_prepare');check('Existing cloud history cannot be recreated as empty preparation','needs recovery from the original database' in refused.text and not db('SELECT * FROM ci_settings WHERE name=?',['provider_cloud_assignment_head']) and db('SELECT * FROM ci_provider_cloud_assignment_evidence ORDER BY id')==retained_rows and db('SELECT * FROM ci_audit ORDER BY id')==retained_audits and provider.calls==calls+4)
        capture(owner,'Cloud-Assignment-Recovery-Owner',url,'[data-cloud-assignment-unavailable]');capture(client,'Cloud-Assignment-Recovery-Client',services,'[data-client-cloud-unavailable]')
    finally:db('INSERT INTO ci_settings (name,value) VALUES (?,?)',[retained_head['name'],retained_head['value']])
    check('Exact cloud history restoration recovers same assignment without a read','Current reviewed assignment' in card(client.get(services)).text and db('SELECT * FROM ci_provider_cloud_assignment_evidence ORDER BY id')==retained_rows and provider.calls==calls+4)
    post('provider_inventory_cloud_unlink',link_id=link['id'],link_revision='1');check('Cloud assignment removal immediately revokes original client facts',card(client.get(services)) is None and not db('SELECT id FROM ci_provider_cloud_links WHERE id=?',[link['id']]))
    post('provider_inventory_cloud_link',server_id='2601',client_id=str(bob),refresh_id=refresh);replacement=db('SELECT * FROM ci_provider_cloud_links WHERE server_id=2601')[0]
    denied=post('provider_inventory_cloud_unlink',link_id=link['id'],link_revision='1')
    check('Reassignment uses a new identity and refuses old removal form','assignment changed' in denied.text.lower() and replacement['id']!=link['id'] and replacement['client_id']==bob and card(client.get(services)) is None and 'Current reviewed assignment' in card(other.get(services)).text)
    post('provider_inventory_cloud_unlink',link_id=replacement['id'],link_revision='1');response=owner.get(url);evidence('Local removal after reassignment',response)
    check('Cloud workflow has exactly four immutable owner reads and no provider write',[r['state'] for r in diagnostics[-1]['read_results']]==['complete','unavailable','partial','complete'] and all(r['operation']=='cloudservers.list' for r in diagnostics[-1]['read_results']) and provider.calls==calls+4 and provider.posts==posts and not provider.errors)
    proof={'dedicated_actor':actor,'dedicated_reads':4,'inherited':{}}
    for name,rows in inherited.items():
        if name=='provider_cloud_links':retained=db('SELECT * FROM ci_provider_cloud_links ORDER BY id')
        else:
            key='seq' if name=='provider_refreshes' else 'id';limit=max((r[key] for r in rows),default=0);retained=db('SELECT * FROM ci_'+name+' WHERE '+key+'<=? ORDER BY '+key,[limit])
        check('Cloud assignment preserves inherited '+name+' bytes',retained==rows)
        proof['inherited'][name]={'count':len(rows),'sha256':hashlib.sha256(json.dumps(rows,sort_keys=True).encode()).hexdigest(),'retained_sha256':hashlib.sha256(json.dumps(retained,sort_keys=True).encode()).hexdigest()}
    check('Cloud assignment preserves original owner account and role',db('SELECT * FROM ci_users WHERE id=?',[original_owner['id']])==[original_owner] and db('SELECT * FROM ci_staff_profiles WHERE user_id=?',[original_owner['id']])==original_staff)
    (qa/'cloud-assignment-history-preservation.json').write_text(json.dumps(proof,indent=2));provider.cloud_servers=original_cloud;provider.cloud_mode=original_mode
    for session in (owner,member,client,other):session.close()
