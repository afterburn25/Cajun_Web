"""Real packaged confirmation form, authenticated TLS observation and retained proof."""
import json
import time
from release_cases import db
from staff_cases import OWNER


def run_browser(env, browser, owner, client, foreign, pending, unknown, capture):
    check, form_for, form_data, soup = (env[k] for k in ['check', 'form_for', 'form_data', 'soup'])
    provider, base = env['provider_server'], env['BASE']
    action = 'service_plans_order_confirm_fulfillment'

    def history():
        tables = ['hosting_observations', 'hosting_reconciliations', 'hosting_resources', 'hosting_records', 'hosting_record_events', 'hosting_operation_results', 'provider_operations', 'provider_refreshes', 'provider_refresh_results', 'provider_observation_packages', 'audit']
        return {name: db('SELECT * FROM ci_'+name+' ORDER BY id') for name in tables}

    def cooldown():
        last = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes')[0]['epoch'] or 0
        remaining = 31-(time.time()-last)
        if remaining > 0:
            time.sleep(remaining)

    fields = form_data(form_for(owner.get(pending['owner']), action))
    data = {**fields, 'confirm': '1', 'current_password': OWNER}
    before, calls, posts = history(), provider.calls, len(provider.posts)
    for delta, label in [({'csrf': 'bad'}, 'CSRF'), ({'confirm': ''}, 'confirmation'), ({'current_password': 'wrong'}, 'password'), ({'approval_hash': 'f'*64}, 'original approval'), ({'dispatch_id': 'f'*32}, 'original attempt')]:
        response = owner.post(pending['owner'], data={**data, **delta})
        check('Actual status read refuses '+label, history()==before and provider.calls==calls and len(provider.posts)==posts and OWNER not in response.text)
    client_csrf = form_data(form_for(client.get(pending['client']), 'service_agreements_order_cancel'))['csrf']
    client.post(pending['client'], data={**data, 'action': 'service_agreements_order_confirm_fulfillment', 'csrf': client_csrf})
    check('Client cannot perform private confirmation with a valid client CSRF', history()==before and provider.calls==calls)
    check('Foreign client sees no confirmation form or accepted domain', action not in foreign.get(pending['client']).text and pending['domain'] not in foreign.get(pending['client']).text)
    check('Unknown purchase cannot be confirmed by a matching name', soup(owner.get(unknown['owner'])).select_one('[name=action][value='+action+']') is None)
    native_result = json.loads(db('SELECT result_json FROM ci_hosting_operation_results WHERE id=?', [fields['dispatch_id']])[0]['result_json'])
    package_id = int(native_result['resource_ref'])
    package = next(p for p in provider.packages if p['id']==package_id)
    check('Provider fixture retains the exact approved package type', package['typeRef']==811)
    provider.mode='success'; package['enabled']=False
    cooldown(); before_provider=db('SELECT * FROM ci_provider_operations ORDER BY id');calls=provider.calls
    owner.post(pending['owner'], data=data)
    check('Actual disabled package read leaves the client service pending', provider.calls==calls+1 and len(provider.posts)==posts and 'Assigned package is not enabled' in owner.get(pending['owner']).text and 'Hosting setup in progress' in client.get(pending['client']).text and not db('SELECT id FROM ci_hosting_resources WHERE account_id=?',[pending['link']['account_id']]))
    capture(owner,pending['owner'],'staff-confirmation-pending')
    before=history();calls=provider.calls;owner.post(pending['owner'],data=data)
    check('Repeated status click honors the shared inventory cooldown',history()==before and provider.calls==calls and len(provider.posts)==posts)
    package['enabled']=True;cooldown()
    context=browser.new_context(viewport={'width':1440,'height':1000})
    context.add_cookies([{'name':c.name,'value':c.value,'url':base} for c in owner.cookies]);page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.goto(pending['owner'])
    form=page.locator('form').filter(has=page.locator('[name=action][value='+action+']'))
    form.locator('[name=confirm]').check();form.locator('[name=current_password]').fill(OWNER)
    calls=provider.calls
    with page.expect_navigation():
        form.get_by_role('button',name='Check hosting status',exact=True).click()
    page.wait_for_load_state('networkidle');page.screenshot(path=str(env['QA']/'hosting-confirmation-submit.png'),full_page=True)
    check('Chromium confirms exact enabled provider service with no new purchase',not errors and 'Exact package confirmed enabled' in page.content() and provider.calls==calls+1 and len(provider.posts)==posts)
    context.close()
    resource=db('SELECT account_id,profile_id,resource_ref FROM ci_hosting_resources WHERE account_id=?',[pending['link']['account_id']])
    check('Confirmed provider ID belongs to the original accepted order service',resource==[{'account_id':pending['link']['account_id'],'profile_id':pending['link']['profile_id'],'resource_ref':str(package_id)}] and db('SELECT * FROM ci_provider_operations ORDER BY id')==before_provider)
    decision=db('SELECT o.observation_json FROM ci_hosting_observations o JOIN ci_hosting_reconciliations r ON r.observation_id=o.id WHERE r.id=?',[fields['dispatch_id']])[0]
    proof=json.loads(decision['observation_json']);refresh=proof['evidence_ref'].removeprefix('provider-refresh:')
    check('Confirmed status retains the exact authenticated read and no raw provider response',proof['outcome']=='applied' and proof['resource_ref']==str(package_id) and len(proof['evidence_sha256'])==64 and db('SELECT state FROM ci_provider_refresh_results WHERE id=?',[refresh])==[{'state':'complete'}] and 'SYNTHETIC_RAW_GENERAL_KEY' not in str(history()))
    before=history();calls=provider.calls;owner.post(pending['owner'],data=data)
    check('Original confirmed form replay cannot read again or change history',history()==before and provider.calls==calls and len(provider.posts)==posts)
    client_text=client.get(pending['client']).text
    check('Client sees active service without provider proof or owner controls','Hosting service active' in client_text and action not in client_text and 'Provider package number' not in client_text and refresh not in client_text)
    capture(owner,pending['owner'],'staff-confirmation-active');capture(client,pending['client'],'client-confirmation-active')
    db('RENAME TABLE ci_provider_observation_packages TO ci_confirmation_saved_packages')
    try:
        response=owner.get(pending['owner']);client_response=client.get(pending['client'])
        check('Missing original confirmation proof refuses owner and client projections',response.status_code==200 and client_response.status_code==200 and 'Exact package confirmed enabled' not in response.text and 'Hosting service active' not in client_response.text and not db("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ci_provider_observation_packages'"))
        owner.post(pending['owner'],data={**data,'action':'service_plans_order_prepare_confirmation'})
        check('Owner preparation refuses to recreate missing established observation history',not db("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='ci_provider_observation_packages'"))
    finally:
        db('RENAME TABLE ci_confirmation_saved_packages TO ci_provider_observation_packages')
    check('Exact proof restoration recovers active view without another read',history()==before and provider.calls==calls and 'Hosting service active' in client.get(pending['client']).text)
    check('Confirmation fixture observed only protected bounded requests',not provider.errors)
