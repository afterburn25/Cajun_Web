"""Installed financed-license acceptance using the real client portal and validation API."""
import json
import os
import subprocess

import requests
from playwright.sync_api import expect


def _form(soup,response,action):
    for form in soup(response).select('form'):
        marker=form.select_one('[name=action]')
        if marker and marker.get('value')==action:
            return form
    raise AssertionError('Missing financing form '+action)


def run_browser(env,browser):
    root,qa,base,check=(env[k] for k in ['ROOT','QA','BASE','check'])
    soup,token,form_data=(env[k] for k in ['soup','token','form_data'])
    admin,alice,bob=(env[k] for k in ['admin','alice','bob'])
    php=env['php']

    def fixture(operation,value):
        result=subprocess.run(
            [php,str(root/'tests/license_financing_browser_fixture.php'),operation,str(value)],
            capture_output=True,text=True,timeout=90,
            env={**os.environ,'CWP_TEST_DATABASE':'cwp_test'})
        check('Financing fixture '+operation+' succeeds',
              result.returncode==0 and result.stderr=='')
        return json.loads(result.stdout)

    setup=fixture('setup','0')
    offer=setup['offer_id'];license_id=setup['license_id'];license_key=setup['license_key']
    domain=setup['domain']
    finance=base+'/existing/portal/index.php?finance='+offer
    validate=base+'/existing/api/validate.php'

    page=alice.get(finance,timeout=25)
    check('Financing agreement is private and non-referring',
          page.status_code==200
          and page.headers.get('Cache-Control')=='no-store, private'
          and page.headers.get('Referrer-Policy')=='no-referrer')
    check('Client sees exact financing terms and default anti-resale rights',
          'Browser financed website' in page.text
          and '4 monthly installments' in page.text
          and '$25.00' in page.text
          and '5-day license grace period' in page.text
          and 'site-use only; resale and distribution are not granted' in page.text.lower())
    foreign=bob.get(finance,timeout=25)
    check('Another client cannot inspect a financing agreement',
          'Browser financed website' not in foreign.text)

    sign=_form(soup,page,'finance_offer_sign')
    signed=form_data(sign)
    signed.update({
        'signer_name':'Alice Browser Acceptance',
        'agree_terms':'1',
        'agree_electronic':'1',
    })
    response=alice.post(finance,data=signed,timeout=30)
    check('Authenticated client signs the exact financing agreement',
          response.status_code==200
          and 'Financing agreement signed' in response.text
          and 'Signed by Alice Browser Acceptance' in response.text)
    record=env['db']('SELECT status,signed_name,signed_email,signed_terms_sha256,signature_evidence_hash FROM ci_license_finance_offers WHERE id=?',[offer])[0]
    check('Electronic signature retains client identity exact terms and evidence',
          record['status']=='signed'
          and record['signed_name']=='Alice Browser Acceptance'
          and bool(record['signed_email'])
          and len(record['signed_terms_sha256'])==64
          and len(record['signature_evidence_hash'])==64)
    plan=env['db']('SELECT state,paid_off_at FROM ci_license_financing WHERE license_id=?',[license_id])[0]
    check('Zero-down signed financing activates the existing license without replacing its key',
          plan=={'state':'active','paid_off_at':None}
          and env['db']('SELECT license_key FROM ci_website_licenses WHERE id=?',[license_id])==[{'license_key':license_key}])

    authorized=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Active financed license validates on its retained key',
          authorized.status_code==200 and authorized.json()=={'authorized':True,'error':None})

    late=fixture('late',offer)
    check('Issued unpaid installment past grace moves financing to suspended',
          late['state']=='suspended')
    denied=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Past-due financed license is definitively payment-suspended',
          denied.status_code==200
          and denied.json()=={'authorized':False,'error':'Financing payment is past the agreed grace period'})
    suspended_page=alice.get(finance,timeout=25)
    check('Client portal shows financing suspension without revoking the underlying key',
          'License financing:</strong> Suspended' in suspended_page.text
          and env['db']('SELECT is_active,license_key FROM ci_website_licenses WHERE id=?',[license_id])
              ==[{'is_active':1,'license_key':license_key}])

    caught=fixture('catchup',offer)
    check('Verified catch-up payment clears financing suspension on the same key',
          caught['state'] in ['active','grace'] and caught['paid_off_at'] is None)
    resumed=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Catch-up payment immediately restores financed license authorization',
          resumed.status_code==200 and resumed.json()=={'authorized':True,'error':None})

    payoff=fixture('payoff',offer)
    check('All signed installments reach permanent financing payoff',
          payoff['state']=='paid_off' and bool(payoff['paid_off_at']))
    paid_page=alice.get(finance,timeout=25)
    check('Client portal clearly shows permanent financing payoff',
          'Paid Off' in paid_page.text and 'permanently paid off' in paid_page.text)

    reversed_after=fixture('reverse-after-payoff',offer)
    check('Post-payoff ledger correction cannot restore the financing payment lock',
          reversed_after['state']=='paid_off'
          and reversed_after['paid_off_at']==payoff['paid_off_at'])
    still_authorized=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Paid-off license remains authorized after later billing correction',
          still_authorized.status_code==200 and still_authorized.json()=={'authorized':True,'error':None})

    rights=env['db']('SELECT allow_resale,allow_distribution,exclusive_resale,exclusive_distribution FROM ci_license_financing WHERE license_id=?',[license_id])[0]
    check('Financing payoff does not grant resale or distribution rights',
          rights=={'allow_resale':0,'allow_distribution':0,'exclusive_resale':0,'exclusive_distribution':0})

    context=browser.new_context(viewport={'width':390,'height':844},reduced_motion='reduce')
    context.add_cookies([{'name':c.name,'value':c.value,'url':base+'/existing/'} for c in alice.cookies])
    p=context.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
    p.goto(finance)
    expect(p.get_by_text('Paid Off',exact=False).first).to_be_visible()
    p.screenshot(path=str(qa/'Financing-Agreement-Mobile.png'),full_page=True)
    check('Financing portal mobile layout fits viewport',
          p.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    check('Financing portal has no uncaught browser script errors',not errors)
    context.close()
