"""Installed software-commerce HTTP/Chromium acceptance. No external payment or mail traffic."""
import hashlib
import json
import os
import subprocess
from datetime import datetime, timedelta
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER


def _product_data():
    return {
        'sku':'SOFTWARE-HTTP-ACCEPT',
        'title':'Software browser acceptance',
        'family':'software',
        'status':'published',
        'term':'once',
        'currency':'USD',
        'amount':'49.99',
        'setup':'0.00',
        'cost':'0.00',
        'provider_ref':'',
        'summary':'Synthetic installed-browser software purchase.',
        'features':'Private licensed download',
        'terms':'One-time synthetic acceptance purchase. No live payment is performed.',
        'sort_order':'0',
    }


def _action_form(soup, response, action, *, product_id=None, asset_id=None, invoice_id=None):
    for form in soup(response).select('form'):
        marker=form.select_one('input[name=action]')
        if not marker or marker.get('value') != action:
            continue
        if product_id is not None:
            product=form.select_one('input[name=product_id]')
            if not product or product.get('value') != str(product_id):
                continue
        if asset_id is not None:
            asset=form.select_one('input[name=asset_id]')
            if not asset or asset.get('value') != str(asset_id):
                continue
        if invoice_id is not None:
            invoice=form.select_one('input[name=invoice_id]')
            if not invoice or invoice.get('value') != str(invoice_id):
                continue
        return form
    raise AssertionError('Software form missing '+action)


def run_browser(env, browser):
    root, qa, base, check = (env[k] for k in ['ROOT','QA','BASE','check'])
    soup, token, form_data, submit = (env[k] for k in ['soup','token','form_data','submit'])
    admin, alice, bob = (env[k] for k in ['admin','alice','bob'])
    php=env['php']
    licenses=base+'/existing/admin/index.php?view=studio-licenses'
    catalog=base+'/existing/admin/index.php?view=catalog'
    portal=base+'/existing/portal/index.php?software=1'

    # Prepare optional dependencies through their real owner forms so this
    # suite does not depend on another browser module having run first.
    settings=lambda name: db('SELECT value FROM ci_settings WHERE name=?',[name])
    if settings('license_schema_version') != [{'value':'3'}]:
        response=submit(admin,licenses,'studio_license_setup',{'current_password':OWNER})
        check('Software browser prepares website licensing through owner form',
              response.status_code==200 and settings('license_schema_version')==[{'value':'3'}])
    if settings('catalog_schema_version') != [{'value':'1'}]:
        response=submit(admin,catalog,'catalog_migrate',{'owner_password':OWNER,'confirm':'1'})
        check('Software browser prepares catalog through owner form',
              response.status_code==200 and settings('catalog_schema_version')==[{'value':'1'}])
    payments=base+'/existing/admin/index.php?view=payments'
    if settings('payments_schema_version') != [{'value':'1'}]:
        response=submit(admin,payments,'payments_migrate',{'owner_password':OWNER,'confirm':'1'})
        check('Software browser prepares payment settings through owner form',
              response.status_code==200 and settings('payments_schema_version')==[{'value':'1'}])
    ledger=base+'/existing/admin/index.php?view=invoice-payments'
    if settings('invoice_ledger_schema_version') != [{'value':'1'}]:
        response=submit(admin,ledger,'payments_ledger_setup',{'owner_password':OWNER,'confirm':'1'})
        check('Software browser prepares invoice ledger through owner form',
              response.status_code==200 and settings('invoice_ledger_schema_version')==[{'value':'1'}])

    before=admin.get(licenses,timeout=25)
    if settings('software_commerce_version') != [{'value':'3'}]:
        setup=_action_form(soup,before,'software_commerce_setup')
        check('Software commerce owner setup is explicit before use',True)
        data=form_data(setup);data['current_password']=OWNER
        response=admin.post(licenses,data=data,timeout=40)
        check('Software commerce owner preparation succeeds through actual admin form',
              response.status_code==200
              and settings('software_commerce_version')==[{'value':'3'}]
              and 'Software license and purchase storage is prepared' in response.text)
    else:
        check('Software commerce storage is already prepared for installed acceptance',True)

    gateway_form=_action_form(soup,admin.get(licenses,timeout=25),'software_gateway_select')
    forged_gateway=form_data(gateway_form)
    forged_gateway.update({'gateway_mode':'production','current_password':OWNER})
    refused=admin.post(licenses,data=forged_gateway,timeout=25)
    check('Forged production software gateway activation is refused server-side',
          'Live payments remain gated' in refused.text
          and db("SELECT value FROM ci_settings WHERE name='software_gateway_mode' AND value='production'")==[])

    def fixture(operation, value=0):
        result=subprocess.run(
            [php,str(root/'tests/software_commerce_browser_fixture.php'),operation,str(value)],
            capture_output=True,text=True,timeout=60,
            env={**os.environ,'CWP_TEST_DATABASE':'cwp_test'})
        check('Software browser fixture '+operation+' succeeds',result.returncode==0 and result.stderr=='')
        return json.loads(result.stdout)

    # Synthetic encrypted sandbox credentials render the real payment page only;
    # requests does not execute Accept.js and the fixture is switched off immediately.
    fixture('sandbox-on')
    sandbox=alice.get(portal,timeout=25)
    policy=sandbox.headers.get('Content-Security-Policy','')
    doc=soup(sandbox)
    accept=doc.select_one('script[src="https://jstest.authorize.net/v1/Accept.js"]')
    local=doc.select_one('script[src$="assets/js/authorize-net-checkout.js"]')
    check('Sandbox My Software response scopes CSP to exact Authorize.net hosts',
          sandbox.status_code==200
          and 'https://jstest.authorize.net' in policy
          and 'https://apitest.authorize.net' in policy
          and '*.authorize.net' not in policy)
    check('Sandbox checkout scripts carry the response nonce',
          accept is not None and local is not None
          and bool(accept.get('nonce')) and accept.get('nonce')==local.get('nonce'))
    fixture('sandbox-off')
    disabled=alice.get(portal,timeout=25)
    check('Disabling software gateway removes external checkout script',
          'jstest.authorize.net/v1/Accept.js' not in disabled.text)

    # Create a real published software offer through the existing catalog UI.
    response=submit(admin,catalog,'catalog_save',_product_data())
    product=db("SELECT id,revision,status,amount_cents,term FROM ci_catalog_products WHERE sku='SOFTWARE-HTTP-ACCEPT'")
    check('Software catalog offer is published through actual owner form',
          len(product)==1 and product[0]['status']=='published'
          and product[0]['amount_cents']==4999 and product[0]['term']=='once')
    pid=product[0]['id'];revision=product[0]['revision']

    policy_page=admin.get(licenses,timeout=25)
    policy_form=_action_form(soup,policy_page,'software_product_policy',product_id=pid)
    policy_data=form_data(policy_form)
    policy_data.update({
        'module_key':'browser-acceptance',
        'dependencies':'',
        'update_months':'6',
        'max_installations':'1',
        'current_password':OWNER,
    })
    policy_response=admin.post(licenses,data=policy_data,timeout=25)
    policy_rows=db('SELECT module_key,update_months,max_installations,revision FROM ci_software_product_policies WHERE product_id=?',[pid])
    check('Owner saves software update and installation policy through actual form',
          policy_response.status_code==200
          and policy_rows==[{'module_key':'browser-acceptance','update_months':6,'max_installations':1,'revision':1}])

    orphan=fixture('orphan-license',pid)
    validate=base+'/existing/api/validate.php'
    response=requests.post(validate,json={
        'license_key':orphan['license_key'],
        'domain':'orphan-software.example'
    },timeout=25)
    check('Inconsistent software entitlement history is inconclusive over public validation API',
          response.status_code==500
          and response.headers.get('Content-Type','').startswith('application/json')
          and response.json()=={'authorized':False,'error':'Internal server error'})

    expired=fixture('expired-trial',pid)
    expired_response=requests.post(validate,json={
        'license_key':expired['license_key'],
        'domain':'expired-trial.example',
        'product_id':expired['sku'],
    },timeout=25)
    check('Expired trial is definitively denied by installed validation API',
          expired_response.status_code==200
          and expired_response.json()=={
              'authorized':False,
              'error':'License expired, over its installation limit, or not authorized'
          })

    page=alice.get(portal,timeout=25)
    form=_action_form(soup,page,'software_order_buy',product_id=pid)
    order=form_data(form)
    order.update({'domain':'software-http.example','quantity':'1'})
    starting=db('SELECT COUNT(*) AS n FROM ci_software_orders')[0]['n']

    bad=dict(order,csrf='invalid')
    denied=alice.post(portal,data=bad,timeout=25)
    check('Software order rejects bad CSRF without invoice or order',
          'session expired' in denied.text.lower()
          and db('SELECT COUNT(*) AS n FROM ci_software_orders')[0]['n']==starting)

    wrong=dict(order,subscribe='1')
    denied=alice.post(portal,data=wrong,timeout=25)
    check('One-time software refuses forged recurring consent',
          'does not use recurring billing consent' in denied.text
          and db('SELECT COUNT(*) AS n FROM ci_software_orders')[0]['n']==starting)

    fresh_policy=_action_form(soup,admin.get(licenses,timeout=25),'software_product_policy',product_id=pid)
    revised_policy=form_data(fresh_policy)
    revised_policy.update({
        'module_key':'browser-acceptance-v2',
        'dependencies':'',
        'update_months':'6',
        'max_installations':'1',
        'current_password':OWNER,
    })
    revised=admin.post(licenses,data=revised_policy,timeout=25)
    check('Owner can revise software policy while an unsubmitted customer form is open',
          revised.status_code==200
          and db('SELECT revision FROM ci_software_product_policies WHERE product_id=?',[pid])==[{'revision':2}])
    stale=alice.post(portal,data=order,timeout=25)
    check('Customer must reload after disclosed software policy changes',
          'licensing policy changed' in stale.text
          and db('SELECT COUNT(*) AS n FROM ci_software_orders')[0]['n']==starting)

    page=alice.get(portal,timeout=25)
    form=_action_form(soup,page,'software_order_buy',product_id=pid)
    order=form_data(form)
    order.update({'domain':'software-http.example','quantity':'1'})
    check('Reloaded software order form carries the current policy revision',order.get('policy_revision')=='2')

    created=alice.post(portal,data=order,timeout=30)
    rows=db("SELECT o.*,i.reference,i.status,i.amount_cents FROM ci_software_orders o JOIN ci_studio_invoices i ON i.id=o.invoice_id WHERE o.product_id=? ORDER BY o.id",[pid])
    check('Software order creates one exact issued invoice through client portal',
          len(rows)==1 and rows[0]['status']=='issued' and rows[0]['amount_cents']==4999
          and rows[0]['domain']=='software-http.example' and rows[0]['quantity']==1
          and 'Your software invoice was created' in created.text)
    invoice=rows[0]['invoice_id'];reference=rows[0]['reference']

    replay=alice.post(portal,data=order,timeout=30)
    check('Exact repeated software order reuses one invoice',
          replay.status_code==200
          and db('SELECT COUNT(*) AS n FROM ci_software_orders WHERE product_id=?',[pid])[0]['n']==1)

    alice_page=alice.get(portal,timeout=25)
    bob_page=bob.get(portal,timeout=25)
    check('Software invoice is tenant-isolated in My Software',
          reference in alice_page.text and reference not in bob_page.text)
    anonymous=requests.get(portal,timeout=25)
    check('Anonymous My Software request is refused',anonymous.status_code==403)

    # Test-only transition to the state *after* an independently verified payment.
    paid=fixture('fulfill-asset',invoice)
    asset_id=paid['asset_id'];license_id=paid['license_id']

    fixture('mail-off')
    notice_page=admin.get(licenses,timeout=25)
    notice_form=_action_form(soup,notice_page,'software_notice_retry',invoice_id=invoice)
    notice_data=form_data(notice_form);notice_data['current_password']=OWNER
    notice_response=admin.post(licenses,data=notice_data,timeout=25)
    notice=db('SELECT status,attempts,sent_at,last_error FROM ci_software_notices WHERE invoice_id=?',[invoice])[0]
    check('Owner delivery retry keeps unaccepted disabled-mail notice retryable',
          'was not sent' in notice_response.text.lower()
          and notice['status']=='pending' and notice['attempts']==1 and notice['sent_at'] is None
          and bool(notice['last_error']))

    review=fixture('payment-review',invoice)
    review_page=admin.get(licenses,timeout=25)
    check('Verified refund is visible in owner payment reversal review',
          review['reversal_transaction_id'] in review_page.text
          and review['original_transaction_id'] in review_page.text
          and '$5.00' in review_page.text)
    dispute_form=_action_form(soup,review_page,'software_dispute_record')
    dispute_data=form_data(dispute_form)
    dispute_data.update({
        'original_transaction_id':review['original_transaction_id'],
        'case_reference':'MSP-BROWSER-001',
        'evidence':'Verified synthetic browser dispute evidence for owner review',
        'current_password':OWNER,
    })
    dispute_response=admin.post(licenses,data=dispute_data,timeout=25)
    check('Owner records merchant dispute through actual review form',
          'Merchant dispute recorded for owner review' in dispute_response.text
          and db('SELECT original_transaction_id,evidence FROM ci_software_payment_disputes WHERE case_reference=?',
                 ['MSP-BROWSER-001'])==[{
                     'original_transaction_id':review['original_transaction_id'],
                     'evidence':'Verified synthetic browser dispute evidence for owner review'
                 }])
    check('Refund and dispute review do not revoke the paid software entitlement',
          db('SELECT is_active FROM ci_website_licenses WHERE id=?',[license_id])==[{'is_active':1}])

    valid=requests.post(validate,json={
        'license_key':paid['license_key'],
        'domain':'software-http.example',
        'product_id':'SOFTWARE-HTTP-ACCEPT',
        'version':paid['version'],
    },timeout=25)
    check('Version-aware validation authorizes the purchased product and entitled release',
          valid.status_code==200 and valid.json()=={'authorized':True,'error':None})
    wrong_product=requests.post(validate,json={
        'license_key':paid['license_key'],
        'domain':'software-http.example',
        'product_id':'SOFTWARE-WRONG',
        'version':paid['version'],
    },timeout=25)
    check('Version-aware validation definitively rejects product mismatch',
          wrong_product.status_code==200 and wrong_product.json()['authorized'] is False
          and 'not authorized for this product' in wrong_product.json()['error'])
    unknown_version=requests.post(validate,json={
        'license_key':paid['license_key'],
        'domain':'software-http.example',
        'product_id':'SOFTWARE-HTTP-ACCEPT',
        'version':'9.9.9-unknown',
    },timeout=25)
    check('Unknown client version is inconclusive rather than taking a valid license offline',
          unknown_version.status_code==400 and unknown_version.json()=={'authorized':False,'error':'Unknown software version'})

    owned=alice.get(portal,timeout=25)
    check('Paid software portal exposes owned license and approved download',
          'SOFTWARE-HTTP-ACCEPT' in owned.text
          and 'Synthetic browser acceptance download' in owned.text
          and 'software-http.example' in owned.text
          and 'Installations: 1 / 1' in owned.text
          and 'Updates: through ' in owned.text)
    extra={'csrf':token(owned),'action':'license_domain_add','license_id':str(license_id),'domain':'second-http.example'}
    denied_install=alice.post(portal,data=extra,timeout=25)
    check('Generic client domain action cannot bypass software installation cap',
          'already has 1 active installation' in denied_install.text
          and db('SELECT domain FROM ci_license_domains WHERE license_id=? AND is_active=1',[license_id])
              ==[{'domain':'software-http.example'}])
    check('Another customer cannot see the paid license or asset',
          'Synthetic browser acceptance download' not in bob.get(portal,timeout=25).text)
    owner_register=admin.get(licenses,timeout=25)
    check('Owner register exposes software issuance source and observed validation evidence',
          ('Verified invoice #'+str(invoice)) in owner_register.text
          and '1 active domain' in owner_register.text
          and 'Last successful validation' in owner_register.text
          and 'software-http.example' in owner_register.text
          and 'bucketed check' in owner_register.text)

    download=base+'/existing/api/software-download.php?id='+str(asset_id)+'&license='+str(license_id)
    good=alice.get(download,timeout=25)
    check('Owned software download returns exact verified private ZIP',
          good.status_code==200 and len(good.content)==paid['bytes']
          and hashlib.sha256(good.content).hexdigest()==paid['sha256']
          and good.headers.get('Content-Type')=='application/zip'
          and good.headers.get('Cache-Control')=='private, no-store')
    download_audit=db("SELECT detail FROM ci_audit WHERE action='software.download' ORDER BY id DESC LIMIT 1")
    check('Software download audit binds asset license invoice and unit',
          download_audit==[{'detail':'Asset '+str(asset_id)+' license '+str(license_id)
              +' invoice '+str(invoice)+' unit 1 downloaded'}])
    check('Foreign customer cannot download another tenant software ZIP',
          bob.get(download,timeout=25).status_code==404)
    wrong_entitlement=base+'/existing/api/software-download.php?id='+str(asset_id)+'&license='+str(orphan['license_id'])
    check('Owned product asset cannot be downloaded through a different incomplete entitlement',
          alice.get(wrong_entitlement,timeout=25).status_code==404)
    check('Ambiguous asset-only software download URL is refused',
          alice.get(base+'/existing/api/software-download.php?id='+str(asset_id),timeout=25).status_code==404)
    check('Anonymous software ZIP request is refused',
          requests.get(download,timeout=25).status_code==403)

    support_admin=base+'/existing/admin/index.php?view=support'
    if db("SELECT value FROM ci_settings WHERE name='support_schema_version'") != [{'value':'1'}]:
        response=submit(admin,support_admin,'support_migrate',{'owner_password':OWNER,'confirm':'1'})
        check('Software support acceptance prepares support storage when needed',
              response.status_code==200 and 'Support access is ready' in response.text)
    allowed=db('SELECT allowed FROM ci_support_clients WHERE client_id=1')
    if allowed != [{'allowed':1}]:
        chosen_form(admin,support_admin,'support_policy','client_id',1,{'allowed':'1','owner_password':OWNER})
    started=chosen_form(admin,support_admin,'support_start','client_id',1,{})
    support_id=parse_qs(urlparse(started.url).query).get('support',[''])[0]
    check('Software support acceptance opens audited client context',
          len(support_id)==32 and 'Support session' in started.text)
    support_root=base+'/existing/portal/index.php?support='+support_id
    support_software=support_root+'&software=1'
    blocked=admin.get(support_software,timeout=25)
    check('My Software is unavailable during staff support context',
          blocked.status_code==403 and 'Sign in to your own account to manage software' in blocked.text)
    support_home=admin.get(support_root,timeout=25)
    forged_support={
        'csrf':token(support_home),
        'action':'license_domain_replace',
        'license_id':str(license_id),
        'old_domain':'software-http.example',
        'new_domain':'support-forged.example'
    }
    rejected=admin.post(support_software,data=forged_support,timeout=25)
    check('Support context cannot mutate customer software domain',
          'action is blocked during support' in rejected.text.lower()
          and db('SELECT domain FROM ci_license_domains WHERE license_id=? AND is_active=1',[license_id])
              ==[{'domain':'software-http.example'}])
    check('Support browser context cannot retrieve customer software ZIP',
          admin.get(download+'&support='+support_id,timeout=25).status_code!=200)
    submit(admin,support_root,'support_end')

    visibility_page=admin.get(licenses,timeout=25)
    visibility_form=_action_form(soup,visibility_page,'software_asset_visibility',asset_id=asset_id)
    visibility=form_data(visibility_form);visibility['current_password']=OWNER
    hidden=admin.post(licenses,data=visibility,timeout=25)
    check('Owner can make an immutable release owner-private through actual admin form',
          hidden.status_code==200
          and db('SELECT is_published FROM ci_software_assets WHERE id=?',[asset_id])==[{'is_published':0}])
    check('Owner-private release disappears from customer portal and direct download',
          'Synthetic browser acceptance download' not in alice.get(portal,timeout=25).text
          and alice.get(download,timeout=25).status_code==404)
    private_validation=requests.post(validate,json={
        'license_key':paid['license_key'],
        'domain':'software-http.example',
        'product_id':'SOFTWARE-HTTP-ACCEPT',
        'version':paid['version'],
    },timeout=25)
    check('Owner-private release is inconclusive for installed version validation, not a license revocation',
          private_validation.status_code==400
          and private_validation.json()=={'authorized':False,'error':'Unknown software version'})
    restore_page=admin.get(licenses,timeout=25)
    restore_form=_action_form(soup,restore_page,'software_asset_visibility',asset_id=asset_id)
    restore=form_data(restore_form);restore['current_password']=OWNER
    restored=admin.post(licenses,data=restore,timeout=25)
    check('Owner can restore customer visibility without changing immutable release identity',
          restored.status_code==200
          and db('SELECT is_published FROM ci_software_assets WHERE id=?',[asset_id])==[{'is_published':1}]
          and alice.get(download,timeout=25).status_code==200)

    entitlement=db('SELECT updates_until FROM ci_software_entitlements WHERE license_id=?',[license_id])[0]
    cutoff=datetime.strptime(entitlement['updates_until'],'%Y-%m-%d %H:%M:%S')
    future_release=(cutoff+timedelta(days=1)).strftime('%Y-%m-%d %H:%M:%S')
    db('UPDATE ci_software_assets SET created_at=? WHERE id=?',[future_release,asset_id])
    outside=requests.post(validate,json={
        'license_key':paid['license_key'],
        'domain':'software-http.example',
        'product_id':'SOFTWARE-HTTP-ACCEPT',
        'version':paid['version'],
    },timeout=25)
    check('Validation denies a known release published after the update entitlement',
          outside.status_code==200 and outside.json()=={
              'authorized':False,
              'error':'Software version is outside this license update entitlement'
          })
    check('Direct download also denies a release outside the update entitlement',
          alice.get(download,timeout=25).status_code==404)
    db('UPDATE ci_software_assets SET created_at=? WHERE id=?',[paid['asset_created_at'],asset_id])
    check('Restoring original immutable release timestamp restores authorized download',
          alice.get(download,timeout=25).status_code==200)

    # Real domain-replacement action and cross-tenant denial.
    own=alice.get(portal,timeout=25)
    replace=_action_form(soup,own,'license_domain_replace')
    replace_data=form_data(replace);replace_data['new_domain']='software-moved.example'
    moved=alice.post(portal,data=replace_data,timeout=25)
    active=db('SELECT domain FROM ci_license_domains WHERE license_id=? AND is_active=1 ORDER BY domain',[license_id])
    check('Owner client can move its software installation domain through portal',
          active==[{'domain':'software-moved.example'}]
          and 'Domain replaced' in moved.text)

    foreign=bob.get(portal,timeout=25)
    forged={'csrf':token(foreign),'action':'license_domain_replace','license_id':str(license_id),
            'old_domain':'software-moved.example','new_domain':'foreign.example'}
    denied=bob.post(portal,data=forged,timeout=25)
    check('Foreign customer cannot move another tenant software license',
          'License not found' in denied.text
          and db('SELECT domain FROM ci_license_domains WHERE license_id=? AND is_active=1 ORDER BY domain',[license_id])
              ==[{'domain':'software-moved.example'}])
    owner_history=admin.get(licenses,timeout=25)
    check('Owner register retains software domain transfer history',
          'Registered domain history' in owner_history.text
          and 'software-http.example' in owner_history.text
          and 'deactivated' in owner_history.text
          and 'software-moved.example' in owner_history.text)

    # Chromium: real packaged My Software page renders without JS errors or overflow.
    context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
    context.add_cookies([{'name':c.name,'value':c.value,'url':base+'/existing/'} for c in alice.cookies])
    p=context.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
    p.goto(portal);expect(p.get_by_role('heading',name='Software & Licenses')).to_be_visible()
    expect(p.get_by_text('SOFTWARE-HTTP-ACCEPT',exact=False).first).to_be_visible()
    p.screenshot(path=str(qa/'Software-Commerce-Desktop.png'),full_page=True)
    check('Software commerce desktop fits viewport',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    p.set_viewport_size({'width':390,'height':844})
    p.screenshot(path=str(qa/'Software-Commerce-Mobile.png'),full_page=True)
    check('Software commerce mobile fits viewport',p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    check('Software commerce browser has no uncaught script errors',not errors)
    context.close()
