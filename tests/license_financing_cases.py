"""Installed financed-license acceptance using the real client portal and validation API."""
import hashlib
import json
import os
import re
import subprocess
from urllib.parse import parse_qs, urlparse

import requests
from playwright.sync_api import expect
from release_cases import chosen_form, db as release_db


def _form(soup,response,action,*,license_id=None):
    for form in soup(response).select('form'):
        marker=form.select_one('[name=action]')
        if not marker or marker.get('value')!=action:
            continue
        if license_id is not None:
            hidden=form.select_one('[name=license_id]')
            if not hidden or hidden.get('value')!=str(license_id):
                continue
        return form
    raise AssertionError('Missing financing form '+action)


def run_browser(env,browser):
    env=dict(env)
    env.setdefault('db',release_db)
    root,qa,base,check=(env[k] for k in ['ROOT','QA','BASE','check'])
    soup,token,form_data,submit=(env[k] for k in ['soup','token','form_data','submit'])
    admin,alice,bob=(env[k] for k in ['admin','alice','bob'])
    php=env['php']

    def fixture(operation,value):
        result=subprocess.run(
            [php,str(root/'tests/license_financing_browser_fixture.php'),operation,str(value)],
            capture_output=True,text=True,timeout=90,
            env={**os.environ,'CWP_TEST_DATABASE':'cwp_test'})
        stderr=result.stderr.strip()
        recoverable=(operation=='setup-initial' and result.returncode==0
                     and bool(re.fullmatch(r'CWP \[[0-9A-F]{8}\] RuntimeException code=0 in payments\.php:[0-9]+',stderr)))
        if result.returncode!=0 or (stderr and not recoverable):
            diagnostic=(stderr or result.stdout or 'no subprocess diagnostic').strip()
            for secret in ('Synthetic owner passphrase 2026','local-ci-only'):
                diagnostic=diagnostic.replace(secret,'[redacted]')
            diagnostic=' '.join(diagnostic.split())[:900]
            check('Financing fixture '+operation+' succeeds · subprocess: '+diagnostic,False)
        if recoverable:
            check('Financing fixture setup-initial retains signed agreement when payment handoff needs recovery',True)
        check('Financing fixture '+operation+' succeeds',True)
        return json.loads(result.stdout)

    initial=fixture('setup-initial','0')
    validate=base+'/existing/api/validate.php'
    initial_denied=requests.post(validate,json={
        'license_key':initial['license_key'],
        'domain':initial['domain']
    },timeout=25)
    check('Signed financing with unpaid initial payment has its own definitive lock reason',
          initial_denied.status_code==200
          and initial_denied.json()=={
              'authorized':False,
              'error':'Financing initial payment is still required'
          })
    initial_page=alice.get(base+'/existing/portal/index.php?finance='+initial['offer_id'],timeout=25)
    check('Client portal shows signed down-payment agreement and initial payment handoff',
          'Signed by Alice Initial Payment' in initial_page.text
          and ('Continue to initial payment' in initial_page.text or 'Prepare initial payment page' in initial_page.text))

    setup=fixture('setup','0')
    offer=setup['offer_id'];license_id=setup['license_id'];license_key=setup['license_key']
    domain=setup['domain']
    finance=base+'/existing/portal/index.php?finance='+offer

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

    workspace=alice.get(base+'/existing/portal/index.php',timeout=25)
    move_form=_form(soup,workspace,'license_domain_replace',license_id=license_id)
    move=form_data(move_form);move['new_domain']='finance-browser-moved.example'
    before_binding=env['db']('SELECT schedule_id,terms_sha256,installments,grace_days FROM ci_license_financing WHERE license_id=?',[license_id])[0]
    moved=alice.post(base+'/existing/portal/index.php',data=move,timeout=25)
    after_binding=env['db']('SELECT schedule_id,terms_sha256,installments,grace_days FROM ci_license_financing WHERE license_id=?',[license_id])[0]
    check('Financed customer can move its approved domain without repurchase',
          moved.status_code==200
          and env['db']('SELECT domain FROM ci_license_domains WHERE license_id=? AND is_active=1',[license_id])
              ==[{'domain':'finance-browser-moved.example'}]
          and before_binding==after_binding
          and env['db']('SELECT license_key FROM ci_website_licenses WHERE id=?',[license_id])
              ==[{'license_key':license_key}])
    old_domain=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    domain='finance-browser-moved.example'
    new_domain=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Financed domain move deauthorizes old site and authorizes new site on same key',
          old_domain.status_code==200 and old_domain.json()['authorized'] is False
          and new_domain.status_code==200 and new_domain.json()=={'authorized':True,'error':None})

    bundle=fixture('bundle',offer)
    bundle_url=base+'/existing/api/bundle-download.php?id='+str(bundle['bundle_id'])
    active_bundle=alice.get(bundle_url,timeout=25)
    if active_bundle.status_code!=200 or hashlib.sha256(active_bundle.content).hexdigest()!=bundle['sha256']:
        print('bundle-download diagnostic: status=%s content-type=%s body=%s'
              % (active_bundle.status_code,active_bundle.headers.get('Content-Type'),active_bundle.text[:300]))
    check('Active financing permits the owned website bundle',
          active_bundle.status_code==200
          and len(active_bundle.content)==bundle['bytes']
          and hashlib.sha256(active_bundle.content).hexdigest()==bundle['sha256']
          and active_bundle.headers.get('Content-Type')=='application/zip')

    release=fixture('release',offer)
    release_url=base+'/existing/portal/index.php?download_release='+release['release_id']
    release_detail=base+'/existing/portal/index.php?release='+release['release_id']
    active_release=alice.get(release_url,timeout=25)
    check('Active financing permits the published project release',
          active_release.status_code==200
          and len(active_release.content)==release['bytes']
          and hashlib.sha256(active_release.content).hexdigest()==release['sha256']
          and active_release.headers.get('Content-Type')=='application/zip')
    check('Active financing release detail exposes the client download action',
          'Download website ZIP' in alice.get(release_detail,timeout=25).text)

    late=fixture('late',offer)
    check('Issued unpaid installment past grace moves financing to suspended',
          late['state']=='suspended')
    denied=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Past-due financed license is definitively payment-suspended',
          denied.status_code==200
          and denied.json()=={'authorized':False,'error':'Financing payment is past the agreed grace period'})
    foreign_probe=requests.post(validate,json={
        'license_key':license_key,
        'domain':'not-authorized-finance.example'
    },timeout=25)
    check('Foreign domain cannot probe a financed customer billing state',
          foreign_probe.status_code==200
          and foreign_probe.json()=={'authorized':False,'error':'Domain not registered for this license'})
    blocked_bundle=alice.get(bundle_url,timeout=25)
    suspended_workspace=alice.get(base+'/existing/portal/index.php',timeout=25)
    check('Payment suspension blocks financed website bundle delivery at UI and endpoint',
          blocked_bundle.status_code==403
          and blocked_bundle.json()=={'error':'Financing payment is past the agreed grace period'}
          and 'Financed browser website bundle' not in suspended_workspace.text
          and 'Website bundle downloads are paused until financing access is current' in suspended_workspace.text)
    blocked_release=alice.get(release_url,timeout=25)
    blocked_release_detail=alice.get(release_detail,timeout=25)
    admin_release=admin.get(base+'/existing/admin/index.php?view=deliveries&download_release='+release['release_id'],timeout=25)
    check('Payment suspension blocks client project release bytes but preserves owner review',
          blocked_release.status_code==404
          and 'Website release download is paused until financing access is current' in blocked_release_detail.text
          and 'Download website ZIP' not in blocked_release_detail.text
          and admin_release.status_code==200
          and hashlib.sha256(admin_release.content).hexdigest()==release['sha256'])
    suspended_page=alice.get(finance,timeout=25)
    check('Client portal shows financing suspension without revoking the underlying key',
          'License financing:</strong> Suspended' in suspended_page.text
          and env['db']('SELECT is_active,license_key FROM ci_website_licenses WHERE id=?',[license_id])
              ==[{'is_active':1,'license_key':license_key}])

    suspended_gateway=fixture('gateway-suspended',offer)
    recovery_page=alice.get(finance,timeout=25)
    recovery_link=soup(recovery_page).find('a',string=lambda value: value and 'Pay the overdue installment securely' in value)
    check('Suspended financing scheduler prepares the overdue recovery handoff',
          suspended_gateway['subscription_status']=='billing_suspended'
          and suspended_gateway['amount_cents']==2500
          and recovery_link is not None)
    recovery_url=recovery_link.get('href') if recovery_link else ''
    recovery_response=requests.get(recovery_url,timeout=25) if recovery_url else None
    check('Financing recovery link opens the hardened private payment request',
          recovery_response is not None and recovery_response.status_code==200
          and 'no-store' in recovery_response.headers.get('Cache-Control','')
          and recovery_response.headers.get('Referrer-Policy')=='no-referrer')

    support_admin=base+'/existing/admin/index.php?view=support'
    if (env['db']("SELECT value FROM ci_settings WHERE name='support_schema_version'") != [{'value':'1'}]
        or env['db']("SELECT value FROM ci_settings WHERE name='support_policy_confirmed'") != [{'value':'1'}]):
        support_setup=submit(admin,support_admin,'support_migrate',{'owner_password':'Synthetic owner passphrase 2026','confirm':'1'})
        check('Financing support acceptance prepares support storage when needed',
              support_setup.status_code==200 and 'Support access is ready' in support_setup.text)
    if env['db']('SELECT allowed FROM ci_support_clients WHERE client_id=1') != [{'allowed':1}]:
        chosen_form(admin,support_admin,'support_policy','client_id',1,
                    {'allowed':'1','owner_password':'Synthetic owner passphrase 2026'})
    started=chosen_form(admin,support_admin,'support_start','client_id',1,{})
    support_id=parse_qs(urlparse(started.url).query).get('support',[''])[0]
    support_root=base+'/existing/portal/index.php?support='+support_id
    blocked=admin.get(support_root+'&finance='+offer,timeout=25)
    check('Staff support session cannot open the customer financing agreement',
          len(support_id)==32 and blocked.status_code==403
          and 'Sign in to your own account to review financing' in blocked.text)
    support_home=admin.get(support_root,timeout=25)
    forged=admin.post(support_root+'&finance='+offer,data={
        'csrf':token(support_home),
        'action':'finance_offer_payment',
        'finance_id':offer
    },timeout=25)
    check('Staff support session cannot trigger financing payment actions',
          'action is blocked during support' in forged.text.lower())
    support_release=admin.get(support_root+'&download_release='+release['release_id'],timeout=25)
    check('Support download permission cannot bypass a financed project release lock',
          support_release.status_code==404)
    submit(admin,support_root,'support_end')

    caught=fixture('catchup',offer)
    check('Verified catch-up payment clears financing suspension on the same key',
          caught['state'] in ['active','grace'] and caught['paid_off_at'] is None)
    resumed=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Catch-up payment immediately restores financed license authorization',
          resumed.status_code==200 and resumed.json()=={'authorized':True,'error':None})
    restored_bundle=alice.get(bundle_url,timeout=25)
    check('Catch-up restores the same financed website bundle without regenerating it',
          restored_bundle.status_code==200
          and hashlib.sha256(restored_bundle.content).hexdigest()==bundle['sha256'])
    restored_release=alice.get(release_url,timeout=25)
    check('Catch-up restores the same published project release without republishing it',
          restored_release.status_code==200
          and hashlib.sha256(restored_release.content).hexdigest()==release['sha256'])
    replacement_page=alice.get(finance,timeout=25)
    replacement_form=_form(soup,replacement_page,'finance_subscription_reenroll')
    replacement_data=form_data(replacement_form)
    check('Caught-up suspended subscription exposes replacement only for remaining installments',
          'Pay the overdue installment securely' not in replacement_page.text
          and 'Restore automatic installment payments' in replacement_page.text
          and '3 remaining installments' in replacement_page.text
          and replacement_data.get('finance_id')==offer)
    check('Recovery request is terminal after synthetic verified catch-up',
          env['db']("SELECT status FROM ci_payment_requests WHERE invoice_id=? AND purpose='financing' ORDER BY id DESC LIMIT 1",
                    [suspended_gateway['invoice_id']])==[{'status':'superseded'}])
    fixture('gateway-off',offer)

    payoff=fixture('payoff',offer)
    check('All signed installments reach permanent financing payoff',
          payoff['state']=='paid_off' and bool(payoff['paid_off_at']))
    paid_page=alice.get(finance,timeout=25)
    check('Client portal clearly shows permanent financing payoff',
          'Paid Off' in paid_page.text and 'permanently paid off' in paid_page.text)
    check('Paid-off financing leaves website bundle permanently downloadable',
          alice.get(bundle_url,timeout=25).status_code==200)
    check('Paid-off financing leaves published project release permanently downloadable',
          alice.get(release_url,timeout=25).status_code==200)

    fixture('force-inactive-paid-off',offer)
    check('Synthetic imported paid-off record begins inactive before validation recovery',
          env['db']('SELECT is_active FROM ci_website_licenses WHERE id=?',[license_id])==[{'is_active':0}])
    recovered=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Validation self-heals an inactive immutable paid-off key on its registered domain',
          recovered.status_code==200 and recovered.json()=={'authorized':True,'error':None}
          and env['db']('SELECT is_active FROM ci_website_licenses WHERE id=?',[license_id])==[{'is_active':1}])
    wrong_recovery=requests.post(validate,json={'license_key':license_key,'domain':'paid-off-probe.example'},timeout=25)
    check('Paid-off recovery does not weaken domain binding',
          wrong_recovery.status_code==200
          and wrong_recovery.json()=={'authorized':False,'error':'Domain not registered for this license'})

    licenses=base+'/existing/admin/index.php?view=studio-licenses'
    owner_paid=admin.get(licenses,timeout=25)
    paid_forms=[form for form in soup(owner_paid).select('form')
                if (form.select_one('[name=action]') is not None
                    and form.select_one('[name=action]').get('value')=='studio_license_deactivate'
                    and form.select_one('[name=id]') is not None
                    and form.select_one('[name=id]').get('value')==str(license_id))]
    paid_domain_forms=[form for form in soup(owner_paid).select('form')
                       if (form.select_one('[name=action]') is not None
                           and form.select_one('[name=action]').get('value')=='studio_domain_deactivate'
                           and form.select_one('[name=license_id]') is not None
                           and form.select_one('[name=license_id]').get('value')==str(license_id))]
    check('Paid-off financed license owner UI removes whole-license and standalone-domain deactivation',
          not paid_forms and not paid_domain_forms and 'Permanently unlocked' in owner_paid.text)
    forged_disable=admin.post(licenses,data={
        'csrf':token(owner_paid),'action':'studio_license_deactivate','id':str(license_id)
    },timeout=25)
    check('Forged owner deactivation cannot disable a fully paid financed key',
          'permanently unlocked' in forged_disable.text.lower()
          and env['db']('SELECT is_active FROM ci_website_licenses WHERE id=?',[license_id])==[{'is_active':1}])

    paid_workspace=alice.get(base+'/existing/portal/index.php',timeout=25)
    client_domain_deactivate=[form for form in soup(paid_workspace).select('form')
                              if (form.select_one('[name=action]') is not None
                                  and form.select_one('[name=action]').get('value')=='license_domain_deactivate'
                                  and form.select_one('[name=license_id]') is not None
                                  and form.select_one('[name=license_id]').get('value')==str(license_id))]
    check('Paid-off client workspace removes standalone domain deactivation but retains replacement',
          not client_domain_deactivate
          and _form(soup,paid_workspace,'license_domain_replace',license_id=license_id) is not None)
    forged_domain=alice.post(base+'/existing/portal/index.php',data={
        'csrf':token(paid_workspace),'action':'license_domain_deactivate',
        'license_id':str(license_id),'domain':domain
    },timeout=25)
    check('Forged client domain deactivation cannot disable a paid-off financed site',
          'must remain bound to an active domain' in forged_domain.text.lower()
          and env['db']('SELECT domain FROM ci_license_domains WHERE license_id=? AND is_active=1',[license_id])
              ==[{'domain':domain}])

    paid_workspace=alice.get(base+'/existing/portal/index.php',timeout=25)
    paid_move=_form(soup,paid_workspace,'license_domain_replace',license_id=license_id)
    paid_move_data=form_data(paid_move);paid_move_data['new_domain']='finance-paid-moved.example'
    moved_after_payoff=alice.post(base+'/existing/portal/index.php',data=paid_move_data,timeout=25)
    old_paid_domain=domain;domain='finance-paid-moved.example'
    check('Permanent payoff still allows client-owned domain replacement on the same key',
          moved_after_payoff.status_code==200
          and env['db']('SELECT domain FROM ci_license_domains WHERE license_id=? AND is_active=1',[license_id])
              ==[{'domain':domain}]
          and env['db']('SELECT license_key,is_active FROM ci_website_licenses WHERE id=?',[license_id])
              ==[{'license_key':license_key,'is_active':1}])
    old_paid_probe=requests.post(validate,json={'license_key':license_key,'domain':old_paid_domain},timeout=25)
    new_paid_probe=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Post-payoff domain move deauthorizes only the old domain, not the paid-off key',
          old_paid_probe.status_code==200 and old_paid_probe.json()['authorized'] is False
          and new_paid_probe.status_code==200 and new_paid_probe.json()=={'authorized':True,'error':None})

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

    rights_invoice=fixture('rights-invoice',offer)
    rights_page=admin.get(licenses,timeout=25)
    rights_form=_form(soup,rights_page,'studio_license_financing_rights',license_id=license_id)
    rights_data=form_data(rights_form)
    rights_data.update({
        'allow_resale':'1',
        'exclusive_resale':'1',
        'rights_invoice_id':str(rights_invoice['invoice_id']),
        'evidence':'Paid exclusive resale addendum accepted in browser acceptance',
        'current_password':'Synthetic owner passphrase 2026',
    })
    granted=admin.post(licenses,data=rights_data,timeout=25)
    current_rights=env['db']('SELECT state,paid_off_at,allow_resale,allow_distribution,exclusive_resale,exclusive_distribution FROM ci_license_financing WHERE license_id=?',[license_id])[0]
    check('Owner grants paid exclusive resale through actual financing controls',
          'Commercial rights grant recorded' in granted.text
          and current_rights['state']=='paid_off' and bool(current_rights['paid_off_at'])
          and current_rights['allow_resale']==1 and current_rights['exclusive_resale']==1
          and current_rights['allow_distribution']==0 and current_rights['exclusive_distribution']==0)
    grant_row=env['db']('SELECT invoice_id,allow_resale,allow_distribution,exclusive_resale,exclusive_distribution FROM ci_license_financing_rights_grants WHERE license_id=? ORDER BY id DESC LIMIT 1',[license_id])
    check('Commercial rights grant is backed by the separate paid evidence invoice',
          grant_row==[{
              'invoice_id':rights_invoice['invoice_id'],
              'allow_resale':1,'allow_distribution':0,
              'exclusive_resale':1,'exclusive_distribution':0
          }])
    rights_portal=alice.get(finance,timeout=25)
    check('Client portal distinguishes signed rights from later paid current rights',
          'Signed agreement rights:</strong> site-use only; resale and distribution are not granted.' in rights_portal.text
          and 'Current commercial rights:</strong> Exclusive resale rights granted.' in rights_portal.text
          and ('Invoice #'+str(rights_invoice['invoice_id'])) in rights_portal.text)

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
