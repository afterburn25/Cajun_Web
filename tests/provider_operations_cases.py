"""Packaged HTTP/Chromium coverage for owner-approved provider operations."""
import json,secrets,time
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

    def label_grant(label,package='910',**values):
        return op_post('provider_operations_label_grant',package_id=package,package_label=label,ttl_seconds='3600',**values)
    def legacy_record(key,domain,status='uncertain',external_id=None):
        # Test-only historical records from the previous generic purchase path.
        # This does not enable or authorize a new purchase. Only read-only
        # recovery is exercised through the real HTTP reconciliation handler.
        revision=db('SELECT revision FROM ci_provider_profiles')[0]['revision']
        scope=json.dumps({'resellerId':'*','type':'synthetic','domain_name':domain})
        gid=secrets.token_hex(16);now=time.strftime('%Y-%m-%d %H:%M:%S',time.gmtime())
        actor=db('SELECT id FROM ci_users WHERE email=?',['owner@example.invalid'])[0]['id']
        db('INSERT INTO ci_provider_operation_grants (id,profile_id,operation,scope_fingerprint,profile_revision,scope_params,granted_by,granted_at,expires_epoch,consumed_key,consumed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[gid,pid,'packages.create','a'*64,revision,scope,actor,now,int(time.time())+3600,key,now])
        db('INSERT INTO ci_provider_operations (id,idempotency_key,fingerprint,operation,status,result_message,external_id,attempts,claimed_at,claimed_epoch,resolved_epoch) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[secrets.token_hex(16),key,'b'*64,'packages.create',status,'Synthetic historical provider acknowledgement',external_id,1,now,int(time.time()),int(time.time())])
        return gid

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

    scope=json.dumps({'resellerId':'*','id':[910],'labels':{'910':'Packaged label'}})
    r=label_grant('Packaged label',current_password='wrong')
    check('Grant approval requires owner password','current password' in r.text.lower() and not db('SELECT * FROM ci_provider_operation_grants'))
    r=label_grant('Packaged label',csrf='invalid')
    check('Grant approval rejects invalid CSRF','session expired' in r.text.lower() and not db('SELECT * FROM ci_provider_operation_grants'))
    r=grant('packages.update','not json')
    check('Malformed scope JSON is refused','json' in r.text.lower() and not db('SELECT * FROM ci_provider_operation_grants'))
    r=grant('packages.update',json.dumps({'resellerId':'*','id':[910],'labels':{'910':'a'},'bogus_field':1}))
    check('A scope with an undocumented field is refused',not db('SELECT * FROM ci_provider_operation_grants'))
    r=grant('packages.userstatus',json.dumps({'packageId':7}))
    check('Shape-incomplete operations cannot be approved',not db('SELECT * FROM ci_provider_operation_grants'))
    for package,label in [('0','a'),('9e10','a'),('910',''),('910','a'*161)]:
        label_grant(label,package)
        check('Typed package label rejects invalid values '+package+' '+str(len(label)),not db('SELECT * FROM ci_provider_operation_grants'))
    calls=provider_server.calls
    for operation,params in [
        ('packages.create',{'resellerId':'*','type':'synthetic','domain_name':'purchase.example','sendWelcomeEmail':True}),
        ('domains.renew',{'resellerId':'*','name':'renew.example','years':1}),
        ('packages.delete',{'resellerId':'*','delete-id':[910]}),
        ('packages.update',{'resellerId':'*','id':[910],'billing':{'910':{'renew':True}}}),
        ('packages.update',{'resellerId':'*','id':[910],'labels':{'910':'a'},'delete-id':[911]}),
    ]:
        r=grant(operation,json.dumps(params))
        check('HTTP generic approval requires dedicated consent '+operation+' '+str(len(params)),'dedicated' in r.text.lower() and not db('SELECT * FROM ci_provider_operation_grants') and provider_server.calls==calls)
    r=label_grant('Packaged label')
    grants=db('SELECT * FROM ci_provider_operation_grants')
    check('Owner approves exactly the typed package label',len(grants)==1 and grants[0]['operation']=='packages.update' and json.loads(grants[0]['scope_params'])==json.loads(scope) and grants[0]['consumed_key'] is None)
    check('Owner approval form has no raw JSON or operation selector',soup(r).select_one('[data-provider-grant] [name=scope_params]') is None and soup(r).select_one('[data-provider-grant] [name=operation]') is None)
    gid=grants[0]['id'];posts_before=len(provider_server.posts)
    r=op_post('provider_operations_run',grant_id=gid,idempotency_key='packaged-label-1')
    ops=db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?',['packaged-label-1'])
    check('Approved label run sends exactly one bounded POST',provider_server.calls==calls+1 and len(provider_server.posts)==posts_before+1 and provider_server.posts[-1]=={'path':'/reseller/*/updatePackage','body':{'id':[910],'labels':{'910':'Packaged label'}}})
    check('Unverified label acknowledgement stays uncertain',len(ops)==1 and ops[0]['status']=='uncertain' and ops[0]['external_id'] is None and ops[0]['operation']=='packages.update')
    check('The grant is consumed by its single run',db('SELECT consumed_key FROM ci_provider_operation_grants WHERE id=?',[gid])[0]['consumed_key']=='packaged-label-1')
    label_grant('Another label')
    gid2=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    posts_before_replay=len(provider_server.posts)
    r=op_post('provider_operations_run',grant_id=gid2,idempotency_key='packaged-label-1')
    check('A replayed idempotency key never sends again',len(provider_server.posts)==posts_before_replay and db('SELECT attempts FROM ci_provider_operations WHERE idempotency_key=?',['packaged-label-1'])[0]['attempts']==1)
    r=op_post('provider_operations_reconcile',idempotency_key='packaged-label-1')
    check('Ambiguous reused label scope refuses reconciliation','ambiguous' in r.text.lower())

    provider_server.write_mode='uncertain'
    label_grant('Third label');gid3=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    r=op_post('provider_operations_run',grant_id=gid3,idempotency_key='packaged-label-3')
    check('A 202 response classifies uncertain, never silently retried',db('SELECT status FROM ci_provider_operations WHERE idempotency_key=?',['packaged-label-3'])==[{'status':'uncertain'}])
    check('Uncertain operations show the reconciliation control','Check the provider now' in owner.get(url).text)
    calls=provider_server.calls;r=op_post('provider_operations_reconcile',idempotency_key='packaged-label-3')
    check('Unobservable label reconciliation sends no read',provider_server.calls==calls and db('SELECT reconciled_outcome FROM ci_provider_operations WHERE idempotency_key=?',['packaged-label-3'])==[{'reconciled_outcome':'inconclusive'}])

    legacy_record('packaged-create-3','packaged-three.example')
    r=op_post('provider_operations_reconcile',idempotency_key='packaged-create-3')
    row=db('SELECT reconciled_outcome FROM ci_provider_operations WHERE idempotency_key=?',['packaged-create-3'])[0]
    check('Historical unlanded create reconciles not_observed',row['reconciled_outcome']=='not_observed' and 'not_observed' in r.text)
    legacy_record('packaged-create-4','packaged-four.example')
    provider_server.packages.append({'id':704,'typeRef':1,'name':'packaged-four.example','names':['packaged-four.example'],'enabled':True,'created':'2000-01-01T00:00:00Z'})
    r=op_post('provider_operations_reconcile',idempotency_key='packaged-create-4')
    row4=db('SELECT status,reconciled_outcome FROM ci_provider_operations WHERE idempotency_key=?',['packaged-create-4'])[0]
    check('A domain match cannot prove which request created a package',row4['status']=='uncertain' and row4['reconciled_outcome']=='inconclusive')
    legacy_record('packaged-accepted','accepted.example','accepted_pending','707')
    provider_server.packages.append({'id':708,'typeRef':1,'name':'accepted.example','names':['accepted.example'],'enabled':True,'created':'2000-01-01T00:00:00Z'})
    op_post('provider_operations_reconcile',idempotency_key='packaged-accepted')
    check('Historical acknowledged package cannot be adopted by name',db('SELECT reconciled_outcome FROM ci_provider_operations WHERE idempotency_key=?',['packaged-accepted'])==[{'reconciled_outcome':'inconclusive'}])
    provider_server.packages[-1]['id']=707
    op_post('provider_operations_reconcile',idempotency_key='packaged-accepted')
    check('Historical pending ID is observed without rewriting original result',db('SELECT status,external_id,attempts,reconciled_outcome FROM ci_provider_operations WHERE idempotency_key=?',['packaged-accepted'])==[{'status':'accepted_pending','external_id':'707','attempts':1,'reconciled_outcome':'observed'}])

    label_grant('Ambiguous A');ga=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    label_grant('Ambiguous B');gb=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    posts=len(provider_server.posts)
    op_post('provider_operations_run',grant_id=ga,idempotency_key='packaged-ambig-1')
    op_post('provider_operations_run',grant_id=gb,idempotency_key='packaged-ambig-1')
    amb=db('SELECT status FROM ci_provider_operations WHERE idempotency_key=?',['packaged-ambig-1'])[0]
    check('A conflicting second grant sends nothing and the row stays uncertain',amb['status']=='uncertain' and len(provider_server.posts)==posts+1)
    r=op_post('provider_operations_reconcile',idempotency_key='packaged-ambig-1')
    check('Ambiguous consumed history refuses reconciliation','ambiguous' in r.text.lower())
    legacy_record('packaged-ctx-1','packaged-ctx.example')
    # A real settings change retains its revision history. Directly incrementing
    # the profile alone leaves an impossible fixture for later service mapping.
    previous_revision=db('SELECT revision FROM ci_provider_profiles WHERE id=?',[pid])[0]['revision']
    previous_history=db('SELECT * FROM ci_provider_revisions WHERE profile_id=? ORDER BY revision',[pid])
    previous_calls=provider_server.calls;previous_posts=list(provider_server.posts)
    settings_url=root+'?view=provider-settings'
    r=submit(owner,settings_url,'provider_settings_save',{'label':'Revised operation fixture connection','enabled':'1','general_key':'','current_password':OWNER})
    current_history=db('SELECT * FROM ci_provider_revisions WHERE profile_id=? ORDER BY revision',[pid])
    check('Changed operation context retains exact prior and current provider history',
          r.status_code==200 and db('SELECT revision,enabled FROM ci_provider_profiles WHERE id=?',[pid])==[{'revision':previous_revision+1,'enabled':1}]
          and current_history[:-1]==previous_history and current_history[-1]['revision']==previous_revision+1
          and current_history[-1]['enabled']==1 and current_history[-1]['has_credentials']==1
          and provider_server.calls==previous_calls and provider_server.posts==previous_posts)
    r=op_post('provider_operations_reconcile',idempotency_key='packaged-ctx-1')
    check('A changed approved context refuses reconciliation','approved context' in r.text.lower())
    provider_server.write_mode='success'

    label_grant('Revoked label');gid5=db('SELECT id FROM ci_provider_operation_grants WHERE consumed_key IS NULL ORDER BY seq DESC')[0]['id']
    posts=len(provider_server.posts)
    op_post('provider_operations_revoke',grant_id=gid5)
    r=op_post('provider_operations_run',grant_id=gid5,idempotency_key='packaged-label-5')
    check('A revoked grant cannot send',len(provider_server.posts)==posts and db('SELECT revoked_at FROM ci_provider_operation_grants WHERE id=?',[gid5])[0]['revoked_at'] is not None)
    check('No provider operation row was created for the revoked run',not db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?',['packaged-label-5']))

    # A missing table is an explicit recovery state, including through GET.
    for missing in ['provider_operations','provider_operation_grants']:
        rows=db('SELECT * FROM ci_'+missing+' ORDER BY id')
        db('RENAME TABLE ci_'+missing+' TO ci_'+missing+'_held')
        try:
            r=owner.get(url)
            check('Missing operation history page stays usable '+missing,r.status_code==200 and 'Prepare operation records' in r.text)
            r=op_post('provider_operations_setup')
            check('HTTP preparation requires original history recovery '+missing,'Restore the original database backup' in r.text and not db('SHOW TABLES LIKE "ci_'+missing+'"'))
        finally:
            db('RENAME TABLE ci_'+missing+'_held TO ci_'+missing)
        check('HTTP failed preparation preserves every historical row '+missing,rows==db('SELECT * FROM ci_'+missing+' ORDER BY id'))

    check('Provider secret never appears in any operations response',secret not in r.text)
    for path in ['app/providers/operations.php','app/providers/reconcile.php','database/schema/provider_operations_schema.php','database/migrations/provider_operations_migrate.php','includes/foundation/twentyi-operations/TwentyIOperationRegister.php','includes/foundation/twentyi-operations/TwentyIOperationAdapter.php','includes/foundation/twentyi-operations/TwentyIRequestBuilder.php']:
        check('Provider operation internals deny direct HTTP '+path,requests.get(BASE+'/existing/'+path).status_code==404)

    context=browser.new_context(viewport={'width':1440,'height':1050});page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(root+'?view=login');page.locator('[name=email]').fill('owner@example.invalid');page.locator('[name=password]').fill(OWNER);page.locator('form button').first.click();page.wait_for_url(root+'?view=dashboard');page.goto(url)
    form=page.locator('[data-provider-grant]');form.locator('[name=package_id]').fill('910');form.locator('[name=package_label]').fill('Browser package label');form.locator('[name=current_password]').fill(OWNER);form.get_by_role('button',name='Approve one request',exact=True).click()
    expect(page.locator('[data-provider-grant]')).to_be_visible()
    check('Chromium grant approval clears the password field',page.locator('[data-provider-grant] [name=current_password]').input_value()=='' and secret not in page.content())
    page.screenshot(path=str(QA/'Provider-Operations-Desktop.png'),full_page=True);page.set_viewport_size({'width':320,'height':740});expect(page.locator('.admin-sidebar')).not_to_be_in_viewport();page.screenshot(path=str(QA/'Provider-Operations-Mobile.png'),full_page=True)
    check('Operations owner page fits 320px viewport',page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('Operations browser has no script errors',not errors);context.close()
    check('Provider fixture saw only well-formed requests',not provider_server.errors)
