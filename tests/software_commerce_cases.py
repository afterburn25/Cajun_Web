"""Installed software-commerce HTTP/Chromium acceptance. No external payment or mail traffic."""
import hashlib
import json
import os
import subprocess

import requests
from playwright.sync_api import expect
from release_cases import db
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


def _action_form(soup, response, action, *, product_id=None):
    for form in soup(response).select('form'):
        marker=form.select_one('input[name=action]')
        if not marker or marker.get('value') != action:
            continue
        if product_id is not None:
            product=form.select_one('input[name=product_id]')
            if not product or product.get('value') != str(product_id):
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

    download=base+'/existing/api/software-download.php?id='+str(asset_id)
    good=alice.get(download,timeout=25)
    check('Owned software download returns exact verified private ZIP',
          good.status_code==200 and len(good.content)==paid['bytes']
          and hashlib.sha256(good.content).hexdigest()==paid['sha256']
          and good.headers.get('Content-Type')=='application/zip'
          and good.headers.get('Cache-Control')=='private, no-store')
    check('Foreign customer cannot download another tenant software ZIP',
          bob.get(download,timeout=25).status_code==404)
    check('Anonymous software ZIP request is refused',
          requests.get(download,timeout=25).status_code==403)

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
