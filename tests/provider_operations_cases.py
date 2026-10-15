"""Packaged HTTP/Chromium coverage for owner-approved provider operations."""
import json
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit','provider_server']})
    root=BASE+'/existing/admin/index.php';url=root+'?view=provider-operations';secret='SYNTHETIC_RAW_GENERAL_KEY'
    db('DELETE FROM ci_rate_limits')
    owner=requests.Session();submit(owner,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    profile=db('SELECT id,revision,enabled FROM ci_provider_profiles')
    check('Operations suite starts with an enabled provider profile',len(profile)==1 and profile[0]['enabled']==1)
    pid=profile[0]['id']

    def op_post(action,**values):
        current=db('SELECT revision FROM ci_provider_profiles')[0]['revision']
        return owner.post(url,data={'csrf':token(owner.get(url)),'action':action,'profile_id':pid,'revision':str(current),'current_password':OWNER,**values})
    def grant(operation,scope,key_ttl='3600',password=OWNER,csrf=None):
        data={'csrf':csrf if csrf is not None else token(owner.get(url)),'action':'provider_operations_grant','profile_id':pid,'operation':operation,'scope_params':scope,'ttl_seconds':key_ttl,'current_password':password}
        return owner.post(url,data=data)

    member=requests.Session();submit(member,root+'?view=login','login',{'email':'request-staff@example.invalid','password':STAFF})
    r=member.get(url);check('Administrator cannot view owner provider operations',r.status_code==403 and 'data-provider-grant' not in r.text)
    r=member.post(url,data={'csrf':token(member.get(root+'?view=security')),'action':'provider_operations_setup','current_password':STAFF})
    check('Forged administrator operation setup creates no storage','provider_operations_schema_version' not in [v['name'] for v in db('SELECT name FROM ci_settings')] and not db('SHOW TABLES LIKE "ci_provider_operations"'))
    check('Anonymous visitor has no provider operations',soup(requests.get(url)).select_one('[data-provider-grant]') is None)
    r=owner.get(url+'&support=fixture');check('Support context refuses owner operations screen',r.status_code==403 and soup(r).select_one('[data-provider-grant]') is None)

    r=op_post('provider_operations_setup',current_password='wrong')
    check('Operation storage setup requires owner password','current password' in r.text.lower() and not db('SHOW TABLES LIKE "ci_provider_operations"'))
    r=op_post('provider_operations_setup',csrf='invalid')
    check('Operation setup rejects invalid CSRF','session expired' in r.text.lower())
    r=op_post('provider_operations_setup')
    check('Preparing operation storage creates both tables',db('SHOW TABLES LIKE "ci_provider_operations"')!=[] and db('SHOW TABLES LIKE "ci_provider_operation_grants"')!=[])
    r=owner.get(url);check('Operations page renders the approval form',r.status_code==200 and soup(r).select_one('[data-provider-grant]') is not None)

    scope=json.dumps({'resellerId':'*','type':'synthetic','domain_name':'packaged-one.example'})
    r=grant('packages.create',scope,password='wrong')
    check('Grant approval requires owner password','current password' in r.text.lower() and not db('SELECT * FROM ci_provider_operation_grants'))
    r=grant('packages.create',scope,csrf='invalid')
    check('Grant approval rejects invalid CSRF','session expired' in r.text.lower() and not db('SELECT * FROM ci_provider_operation_grants'))
    r=grant('packages.create','not json')
    check('Malformed scope JSON is refused','json' in r.text.lower() and not db('SELECT * FROM ci_provider_operation_grants'))
    r=grant('packages.create',json.dumps({'resellerId':'*','type':'synthetic','domain_name':'packaged-one.example','bogus_field':1}))
    check('A scope with an undocumented field is refused',not db('SELECT * FROM ci_provider_operation_grants'))
    r=grant('packages.userstatus',json.dumps({'packageId':7}))
    check('Shape-incomplete operations cannot be approved',not db('SELECT * FROM ci_provider_operation_grants'))
    r=grant('packages.create',scope)
    grants=db('SELECT * FROM ci_provider_operation_grants')
    check('Owner approves the exact packages.create scope',len(grants)==1 and grants[0]['operation']=='packages.create' and json.loads(grants[0]['scope_params'])['domain_name']=='packaged-one.example' and grants[0]['consumed_key'] is None)
    gid=grants[0]['id']

    calls=provider_server.calls
    r=op_post('provider_operations_run',grant_id=gid,idempotency_key='packaged-create-1')
    ops=db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?',['packaged-create-1'])
    check('Approved run sends exactly one bounded POST',provider_server.calls==calls+1 and len(provider_server.posts)==1 and provider_server.posts[0]['path']=='/reseller/*/addWeb' and provider_server.posts[0]['body'].get('domain_name')=='packaged-one.example')
    check('The write classifies succeeded against the fixture',len(ops)==1 and ops[0]['status']=='succeeded' and ops[0]['operation']=='packages.create')
    check('The grant is consumed by its single run',db('SELECT consumed_key FROM ci_provider_operation_grants WHERE id=?',[gid])[0]['consumed_key']=='packaged-create-1')

    grant('domains.renew',json.dumps({'resellerId':'*','name':'packaged-renew.example','years':1}))
    gdr=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    op_post('provider_operations_run',grant_id=gdr,idempotency_key='packaged-renew-1')
    check('A bounded domain renewal sends the documented POST once',provider_server.posts[-1]['path']=='/reseller/*/renewDomain' and provider_server.posts[-1]['body']=={'name':'packaged-renew.example','years':1})
    check('The renewal classifies succeeded against the fixture',db('SELECT status FROM ci_provider_operations WHERE idempotency_key=?',['packaged-renew-1'])[0]['status']=='succeeded')

    grant('packages.create',json.dumps({'resellerId':'*','type':'synthetic','domain_name':'packaged-two.example'}))
    gid2=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    r=op_post('provider_operations_run',grant_id=gid2,idempotency_key='packaged-create-1')
    check('A replayed idempotency key never sends again',len(provider_server.posts)==1 and db('SELECT attempts FROM ci_provider_operations WHERE idempotency_key=?',['packaged-create-1'])[0]['attempts']==1)

    provider_server.write_mode='uncertain'
    grant('packages.create',json.dumps({'resellerId':'*','type':'synthetic','domain_name':'packaged-three.example'}))
    gid3=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    r=op_post('provider_operations_run',grant_id=gid3,idempotency_key='packaged-create-3')
    ops3=db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?',['packaged-create-3'])
    check('A 202 response classifies uncertain, never silently retried',len(ops3)==1 and ops3[0]['status']=='uncertain')
    r=owner.get(url);check('Uncertain operations show the reconciliation control','Check the provider now' in r.text)

    r=op_post('provider_operations_reconcile',idempotency_key='packaged-create-3')
    row=db('SELECT reconciled_outcome FROM ci_provider_operations WHERE idempotency_key=?',['packaged-create-3'])[0]
    check('Reconcile reports not_observed for an unlanded create',row['reconciled_outcome']=='not_observed' and 'not_observed' in r.text)

    provider_server.write_mode='uncertain_landed'
    grant('packages.create',json.dumps({'resellerId':'*','type':'synthetic','domain_name':'packaged-four.example'}))
    gid4=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    op_post('provider_operations_run',grant_id=gid4,idempotency_key='packaged-create-4')
    r=op_post('provider_operations_reconcile',idempotency_key='packaged-create-4')
    row4=db('SELECT status,reconciled_outcome FROM ci_provider_operations WHERE idempotency_key=?',['packaged-create-4'])[0]
    check('Reconcile observes a landed create without rewriting the result',row4['status']=='uncertain' and row4['reconciled_outcome']=='observed')

    r=op_post('provider_operations_reconcile',idempotency_key='packaged-create-1')
    check('Only uncertain operations can be reconciled','uncertain' in r.text.lower())

    # Reused key: a second grant consumed by the same key before the ledger
    # conflict leaves ambiguous consumed history — reconcile refuses.
    grant('packages.create',json.dumps({'resellerId':'*','type':'synthetic','domain_name':'packaged-ambig-a.example'}))
    ga=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    grant('packages.create',json.dumps({'resellerId':'*','type':'synthetic','domain_name':'packaged-ambig-b.example'}))
    gb=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    posts=len(provider_server.posts)
    op_post('provider_operations_run',grant_id=ga,idempotency_key='packaged-ambig-1')
    op_post('provider_operations_run',grant_id=gb,idempotency_key='packaged-ambig-1')
    amb=db('SELECT status FROM ci_provider_operations WHERE idempotency_key=?',['packaged-ambig-1'])[0]
    check('A conflicting second grant sends nothing and the row stays uncertain',amb['status']=='uncertain' and len(provider_server.posts)==posts+1)
    r=op_post('provider_operations_reconcile',idempotency_key='packaged-ambig-1')
    check('Ambiguous consumed history refuses reconciliation','ambiguous' in r.text.lower())

    # A credential-context change after the write refuses reconciliation.
    grant('packages.create',json.dumps({'resellerId':'*','type':'synthetic','domain_name':'packaged-ctx.example'}))
    gc=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    op_post('provider_operations_run',grant_id=gc,idempotency_key='packaged-ctx-1')
    db('UPDATE ci_provider_profiles SET revision=revision+1 WHERE id=?',[pid])
    r=op_post('provider_operations_reconcile',idempotency_key='packaged-ctx-1')
    check('A changed approved context refuses reconciliation','approved context' in r.text.lower())
    provider_server.write_mode='success'

    grant('packages.create',json.dumps({'resellerId':'*','type':'synthetic','domain_name':'packaged-five.example'}))
    gid5=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    posts=len(provider_server.posts)
    op_post('provider_operations_revoke',grant_id=gid5)
    r=op_post('provider_operations_run',grant_id=gid5,idempotency_key='packaged-create-5')
    check('A revoked grant cannot send',len(provider_server.posts)==posts and db('SELECT revoked_at FROM ci_provider_operation_grants WHERE id=?',[gid5])[0]['revoked_at'] is not None)
    check('No provider operation row was created for the revoked run',not db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?',['packaged-create-5']))

    check('Provider secret never appears in any operations response',secret not in r.text)
    for path in ['app/providers/operations.php','app/providers/reconcile.php','database/schema/provider_operations_schema.php','database/migrations/provider_operations_migrate.php','includes/foundation/twentyi-operations/TwentyIOperationRegister.php','includes/foundation/twentyi-operations/TwentyIOperationAdapter.php','includes/foundation/twentyi-operations/TwentyIRequestBuilder.php']:
        check('Provider operation internals deny direct HTTP '+path,requests.get(BASE+'/existing/'+path).status_code==404)

    context=browser.new_context(viewport={'width':1440,'height':1050});page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_url(root+'?view=dashboard');page.goto(url)
    form=page.locator('[data-provider-grant]');form.locator('[name=scope_params]').fill(json.dumps({'resellerId':'*','type':'synthetic','domain_name':'packaged-browser.example'}));form.locator('[name=current_password]').fill(OWNER);form.get_by_role('button',name='Approve one request',exact=True).click()
    expect(page.locator('[data-provider-grant]')).to_be_visible()
    check('Chromium grant approval clears the password field',page.locator('[data-provider-grant] [name=current_password]').input_value()=='' and secret not in page.content())
    page.screenshot(path=str(QA/'Provider-Operations-Desktop.png'),full_page=True);page.set_viewport_size({'width':320,'height':740});expect(page.locator('.admin-sidebar')).not_to_be_in_viewport();page.screenshot(path=str(QA/'Provider-Operations-Mobile.png'),full_page=True)
    check('Operations owner page fits 320px viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('Operations browser has no script errors',not errors);context.close()
    check('Provider fixture saw only well-formed requests',not provider_server.errors)
