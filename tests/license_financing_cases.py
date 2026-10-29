"""Installed financed-license acceptance using the real client portal and validation API."""
import hashlib
import json
import os
import re
import subprocess
import time
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
    php=env['php']

    # Full acceptance reaches financing after the long general browser matrix
    # and software-commerce checks. Use identities established at this suite
    # boundary instead of borrowing bootstrap cookies that may have reached the
    # application's real 30-minute idle limit.
    admin=requests.Session()
    admin_login=submit(admin,base+'/existing/admin/index.php?view=login','login',{
        'email':'owner@example.invalid','password':'Synthetic owner passphrase 2026',
    })
    check('Financing browser establishes a fresh isolated owner session',
          admin_login.status_code==200 and 'Sales pipeline' in admin_login.text)
    alice=requests.Session()
    alice_login=submit(alice,base+'/existing/portal/index.php','login',{
        'email':'alice@example.invalid','password':'Synthetic client passphrase 2026',
    })
    check('Financing browser establishes a fresh isolated buyer session',
          alice_login.status_code==200 and 'Welcome,' in alice_login.text)
    bob=requests.Session()
    bob_login=submit(bob,base+'/existing/portal/index.php','login',{
        'email':'bob@example.invalid','password':'Synthetic client passphrase 2026',
    })
    check('Financing browser establishes a fresh isolated foreign-client session',
          bob_login.status_code==200 and 'Welcome,' in bob_login.text)
    env.update({'admin':admin,'alice':alice,'bob':bob})

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
    projects=env['db']('SELECT o.id,o.client_id,o.job_id,l.customer_id AS license_client_id,s.job_id AS schedule_job_id '
                       'FROM ci_license_finance_offers o '
                       'JOIN ci_website_licenses l ON l.id=o.license_id '
                       'JOIN ci_recurring_billing_schedules s ON s.id=o.schedule_id '
                       'WHERE o.id IN (?,?)',[initial['offer_id'],offer])
    check('Down-payment and zero-down financing use distinct owned projects with matching licenses and schedules',
          len(projects)==2 and len({row['job_id'] for row in projects})==2
          and all(row['client_id']==row['license_client_id']==1 and row['job_id']==row['schedule_job_id']
                  for row in projects))
    finance=base+'/existing/portal/index.php?finance='+offer

    accounts=fixture('setup-mail',offer)
    run_mail_draft_checks(env,offer,accounts)

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
          foreign.status_code==404
          and foreign.headers.get('Cache-Control')=='no-store, private'
          and foreign.headers.get('Referrer-Policy')=='no-referrer'
          and 'Browser financed website' not in foreign.text)

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
    if active_release.status_code!=200 or len(active_release.content)!=release['bytes']:
        print('release-download diagnostic: status=%s content-type=%s body=%s'
              % (active_release.status_code,active_release.headers.get('Content-Type'),active_release.text[:300]))
    check('Active financing permits the published project release',
          active_release.status_code==200
          and len(active_release.content)==release['bytes']
          and hashlib.sha256(active_release.content).hexdigest()==release['sha256']
          and active_release.headers.get('Content-Type')=='application/zip')
    check('Active financing release detail exposes the client download action',
          'Download website ZIP' in alice.get(release_detail,timeout=25).text)

    initial_release=fixture('release',initial['offer_id'])
    initial_release_url=base+'/existing/portal/index.php?download_release='+initial_release['release_id']
    check('Unpaid down-payment project release remains locked while the separate active project downloads',
          alice.get(initial_release_url,timeout=25).status_code==404
          and alice.get(release_url,timeout=25).status_code==200)
    # The validate rate bucket is a fixed per-IP minute window shared with the
    # adjacent software suite; roll the bucket before this probe.
    time.sleep(61)
    initial_still_denied=requests.post(validate,json={
        'license_key':initial['license_key'],'domain':initial['domain']
    },timeout=25)
    check('Independent active financing does not unlock the unpaid initial agreement',
          initial_still_denied.status_code==200
          and initial_still_denied.json()=={
              'authorized':False,'error':'Financing initial payment is still required'
          })

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
    recovery_link=soup(recovery_page).find('a',string=lambda value: value and 'Pay the outstanding installment securely' in value)
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
    offer_before=env['db'](
        'SELECT status,activation_invoice_id,payment_request_id FROM ci_license_finance_offers WHERE id=?',
        [offer])
    requests_before=env['db'](
        'SELECT id,invoice_id,status,amount_cents FROM ci_payment_requests WHERE client_id=1 ORDER BY id')
    blocked_before=env['db'](
        "SELECT COUNT(*) AS total FROM ci_support_events WHERE support_id=? AND actor_id=1 AND client_id=1 "
        "AND action='action.blocked' AND outcome='denied'",[support_id])[0]['total']
    forged=admin.post(support_root,data={
        'csrf':token(support_home),
        'action':'finance_offer_payment',
        'finance_id':offer
    },timeout=25)
    blocked_after=env['db'](
        "SELECT COUNT(*) AS total FROM ci_support_events WHERE support_id=? AND actor_id=1 AND client_id=1 "
        "AND action='action.blocked' AND outcome='denied'",[support_id])[0]['total']
    if ('action is blocked during support' not in forged.text.lower()
        or blocked_after!=blocked_before+1):
        print('support-forge diagnostic: status=%s url=%s body=%s'
              % (forged.status_code,forged.url,' '.join(forged.text.split())[:300]))
    check('Staff support session cannot trigger financing payment actions',
          'action is blocked during support' in forged.text.lower()
          and blocked_after==blocked_before+1
          and env['db'](
              'SELECT status,activation_invoice_id,payment_request_id FROM ci_license_finance_offers WHERE id=?',
              [offer])==offer_before
          and env['db'](
              'SELECT id,invoice_id,status,amount_cents FROM ci_payment_requests WHERE client_id=1 ORDER BY id')
              ==requests_before)
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
          'Pay the outstanding installment securely' not in replacement_page.text
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

    # api/validate.php allows 10 requests per minute per IP; the flow has
    # already used eight. Start the full post-payoff security sequence in one
    # fresh window so its six probes exercise policy rather than HTTP 429.
    time.sleep(61)
    fixture('force-inactive-paid-off',offer)
    check('Synthetic paid-off record begins explicitly owner-inactive',
          env['db']('SELECT is_active FROM ci_website_licenses WHERE id=?',[license_id])==[{'is_active':0}])
    inactive_probe=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Paid-off financing cannot silently override explicit owner deactivation',
          inactive_probe.status_code==200
          and inactive_probe.json()=={'authorized':False,'error':'License is deactivated'}
          and env['db']('SELECT is_active FROM ci_website_licenses WHERE id=?',[license_id])==[{'is_active':0}])

    licenses=base+'/existing/admin/index.php?view=studio-licenses'
    reactivated_response=chosen_form(admin,licenses,'studio_license_reactivate','id',license_id,{})
    reactivated_row=env['db']('SELECT license_key,is_active FROM ci_website_licenses WHERE id=?',[license_id])
    if reactivated_row!=[{'license_key':license_key,'is_active':1}]:
        notices=[' '.join(node.get_text(' ',strip=True).split())
                 for node in soup(reactivated_response).select('.notice')]
        print('paid-off owner-reactivation diagnostic: status=%s url=%s notices=%s'
              % (reactivated_response.status_code,reactivated_response.url,notices[:5]))
    check('Owner can explicitly reactivate the same paid-off license key',
          reactivated_row==[{'license_key':license_key,'is_active':1}])
    wrong_recovery=requests.post(validate,json={'license_key':license_key,'domain':'paid-off-probe.example'},timeout=25)
    check('Paid-off owner reactivation does not weaken domain binding',
          wrong_recovery.status_code==200
          and wrong_recovery.json()=={'authorized':False,'error':'Domain not registered for this license'})

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
    check('Paid-off owner UI preserves license control while preventing standalone domain removal',
          len(paid_forms)==1 and not paid_domain_forms
          and 'payment status can no longer suspend this license' in owner_paid.text.lower())
    chosen_form(admin,licenses,'studio_license_deactivate','id',license_id,{})
    owner_disabled=requests.post(validate,json={'license_key':license_key,'domain':domain},timeout=25)
    check('Owner deactivation remains authoritative after financing payoff',
          owner_disabled.status_code==200
          and owner_disabled.json()=={'authorized':False,'error':'License is deactivated'}
          and env['db']('SELECT is_active FROM ci_website_licenses WHERE id=?',[license_id])==[{'is_active':0}])
    chosen_form(admin,licenses,'studio_license_reactivate','id',license_id,{})
    check('Owner reactivation restores the same paid-off license without changing its key',
          env['db']('SELECT license_key,is_active FROM ci_website_licenses WHERE id=?',[license_id])
              ==[{'license_key':license_key,'is_active':1}])

    paid_workspace=alice.get(base+'/existing/portal/index.php',timeout=25)
    client_domain_deactivate=[form for form in soup(paid_workspace).select('form')
                              if (form.select_one('[name=action]') is not None
                                  and form.select_one('[name=action]').get('value')=='license_domain_deactivate'
                                  and form.select_one('[name=license_id]') is not None
                                  and form.select_one('[name=license_id]').get('value')==str(license_id))]
    check('Paid-off client workspace explains payment-only unlock, removes standalone domain deactivation and retains replacement',
          'payment status can no longer suspend this license' in paid_workspace.text.lower()
          and 'owner license controls' in paid_workspace.text.lower()
          and not client_domain_deactivate
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
    admin.close();alice.close();bob.close()


def run_mail_draft_checks(env,offer,accounts):
    """Exercise the real sender picker and local draft action without submission."""
    admin,check,db=(env[k] for k in ['admin','check','db'])
    soup,form_data=(env[k] for k in ['soup','form_data'])
    licenses=env['BASE']+'/existing/admin/index.php?view=studio-licenses'
    page=admin.get(licenses,timeout=25)
    forms=[form for form in soup(page).select('form')
           if form.select_one('[name=action][value=studio_license_finance_offer_email]')
           and form.select_one('[name=finance_id]').get('value')==offer]
    check('Finance agreement exposes a local email draft form for an authorized sender',
          page.status_code==200 and len(forms)==1)
    form=forms[0];sender=accounts['sender'];readonly=accounts['readonly']
    options={o.get('value'):o.get_text(' ',strip=True) for o in form.select('select[name=sender] option')}
    reference=sender['id']+':'+str(sender['revision'])
    check('Finance sender picker preserves current account revision and excludes read-only mailboxes',
          reference in options and sender['address'] in options[reference]
          and not any(value.startswith(readonly['id']+':') for value in options))
    protected=db('SELECT * FROM ci_license_finance_offers WHERE id=?',[offer])
    before=db('SELECT id FROM ci_mail_drafts ORDER BY id')
    for name,change,message in [
        ('invalid CSRF',{'csrf':'invalid'},'Your session expired.'),
        ('read-only sender',{'sender':readonly['id']+':'+str(readonly['revision'])},'Send access'),
        ('stale sender revision',{'sender':sender['id']+':'+str(sender['revision']+1)},'sending account changed'),
    ]:
        data=form_data(form);data.update({'sender':reference,**change})
        denied=admin.post(licenses,data=data,timeout=25)
        check('Finance email draft rejects '+name+' without creating a draft',
              message in denied.text and db('SELECT id FROM ci_mail_drafts ORDER BY id')==before)
    data=form_data(form);data['sender']=reference
    response=admin.post(licenses,data=data,timeout=25,allow_redirects=False)
    rows=db('SELECT id,account_id,account_revision,status,to_json,subject,body FROM ci_mail_drafts ORDER BY id')
    created=[row for row in rows if {'id':row['id']} not in before]
    check('Authorized finance email action creates exactly one local draft',
          response.status_code in (302,303) and 'view=mail-send' in response.headers.get('Location','')
          and len(created)==1)
    draft=created[0]
    client=db('SELECT c.email FROM ci_license_finance_offers o JOIN ci_studio_clients c ON c.id=o.client_id WHERE o.id=?',[offer])[0]
    check('Finance draft retains the selected sender, client recipient and authenticated portal link',
          draft['account_id']==sender['id'] and draft['account_revision']==sender['revision']
          and draft['status']=='draft' and json.loads(draft['to_json'])==[client['email']]
          and ('finance='+offer) in draft['body']
          and ('matching client account' in draft['body'] or 'Sign in with your client account' in draft['body']))
    check('Preparing an agreement email preserves financing terms and signature state',
          db('SELECT * FROM ci_license_finance_offers WHERE id=?',[offer])==protected)
