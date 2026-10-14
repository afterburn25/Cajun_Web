"""Actual packaged owner/client agreement forms; synthetic terms and identities only."""
import json
from urllib.parse import parse_qs, urlparse
import requests
from playwright.sync_api import expect
from release_cases import db, chosen_form
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    globals().update({k: env[k] for k in ['BASE', 'QA', 'check', 'soup', 'form_data', 'form_for', 'submit', 'inbox_server', 'provider_server']})
    db('DELETE FROM ci_rate_limits')
    admin = BASE+'/existing/admin/index.php'
    portal = BASE+'/existing/portal/index.php'
    owner = requests.Session()
    submit(owner, admin+'?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    calls, connections = provider_server.calls, inbox_server.connections
    capture = (QA/'mail-capture.txt').read_bytes()
    invoices = db('SELECT * FROM ci_studio_invoices ORDER BY id')
    listing = admin+'?view=service-plans'

    def data(session, url, action):
        return form_data(form_for(session.get(url), action))

    def snapshot():
        return [db('SELECT * FROM ci_'+table+' ORDER BY id') for table in ['service_plans', 'service_plan_events', 'service_subscriptions', 'service_subscription_events']]

    r = owner.get(listing)
    check('Plan page requires explicit setup on an existing installation', 'Prepare service agreements' in r.text and not db("SELECT * FROM ci_settings WHERE name='service_plans_schema_version'"))
    for changes in [{'current_password': OWNER, 'csrf': 'wrong'}, {'current_password': 'wrong'}]:
        r = submit(owner, listing, 'service_plans_setup', changes)
        check('Plan setup refuses '+('bad CSRF' if 'csrf' in changes else 'wrong password'), 'Prepare service agreements' in r.text and not db("SELECT * FROM ci_settings WHERE name='service_plans_schema_version'"))
    r = submit(owner, listing, 'service_plans_setup', {'current_password': OWNER})
    check('Owner explicitly enables empty agreement storage over HTTP', 'Service agreements are ready' in r.text and db("SELECT value FROM ci_settings WHERE name='service_plans_schema_version'")==[{'value': '1'}] and not db('SELECT * FROM ci_service_plans'))
    check('Agreement pages prohibit caching and referrer disclosure', r.headers.get('Cache-Control')=='no-store, private' and r.headers.get('Referrer-Policy')=='no-referrer')

    # Use existing client administration and real invitation activation for two fresh clients.
    clients = []
    for number in [1, 2]:
        email = 'agreement-client'+str(number)+'@example.invalid'
        chosen_form(owner, admin+'?view=studio-clients', 'studio_client', 'id', 0, {'name': 'Agreement client '+str(number), 'email': email})
        cid = db('SELECT id FROM ci_studio_clients WHERE email=?', [email])[0]['id']
        r = chosen_form(owner, admin+'?view=studio-clients', 'studio_invite', 'id', cid, {})
        invitation = soup(r).select_one('.notice.info a[href*="invite="]')['href']
        client = requests.Session()
        password = 'Synthetic agreement client passphrase '+str(number)
        submit(client, invitation, 'activate', {'password': password, 'confirm_password': password})
        submit(client, portal, 'login', {'email': email, 'password': password})
        check('Activated client '+str(number)+' sees the agreement entry', 'Service agreements' in client.get(portal).text)
        clients.append((cid, client))
    cid, client = clients[0]
    foreign_id, foreign = clients[1]

    new = admin+'?view=service-plan'
    terms = {'name': 'Website care café <review>', 'cadence': 'monthly', 'price': '129.00', 'inclusions': 'Reviewed website updates\nMonthly report', 'exclusions': 'New features', 'limits': 'updates = 4', 'support': 'Response within two business days', 'current_password': OWNER}
    creation = data(owner, new, 'service_plans_create')
    r = owner.post(new, data=dict(creation, **terms, csrf='bad'))
    check('Draft create requires session CSRF and preserves escaped unsaved fields', not db('SELECT * FROM ci_service_plans') and soup(r).select_one('[name=name]')['value']==terms['name'] and '<review>' not in r.text)
    r = owner.post(new, data=dict(creation, **terms))
    plan_id = parse_qs(urlparse(r.url).query)['record'][0]
    detail = admin+'?view=service-plan&record='+plan_id
    check('HTTP draft saves exact USD cents and escaped reviewed terms', json.loads(db('SELECT journal_json FROM ci_service_plans WHERE id=?', [plan_id])[0]['journal_json'])['terms']['price']==12900 and '&lt;review&gt;' in r.text)
    old_edit, old_publish = data(owner, detail, 'service_plans_edit'), data(owner, detail, 'service_plans_publish')
    submit(owner, detail, 'service_plans_edit', dict(terms, name='Reviewed care plan'))
    saved = snapshot()
    hostile = '</textarea><script>window.agreementInjected=true</script>'
    r = owner.post(detail, data=dict(old_edit, name=hostile, current_password=OWNER))
    check('Stale edit keeps unsaved escaped input and its original revision', snapshot()==saved and soup(r).select_one('[name=name]')['value']==hostile and form_data(form_for(r, 'service_plans_edit'))['revision']==old_edit['revision'] and '<script>window.agreementInjected' not in r.text)
    owner.post(detail, data=dict(old_publish, confirm='1', current_password=OWNER))
    check('Stale publish cannot silently publish newer terms', snapshot()==saved)
    publish = data(owner, detail, 'service_plans_publish')
    owner.post(detail, data=dict(publish, current_password=OWNER))
    check('Publishing requires an explicit unchecked confirmation', snapshot()==saved)
    r = owner.post(detail, data=dict(publish, confirm='1', current_password=OWNER))
    check('Published plan offers exact terms without an edit form', 'Offer these terms to a client' in r.text and soup(r).select_one('[name=action][value=service_plans_edit]') is None)
    published = snapshot()
    r = owner.post(detail, data=dict(old_edit, name=hostile, current_password=OWNER))
    check('Concurrent publication preserves unsaved draft text with save disabled', snapshot()==published and soup(r).select_one('[name=name]')['value']==hostile and form_for(r, 'service_plans_edit').select_one('button').has_attr('disabled'))

    def offer():
        request = data(owner, detail, 'service_plans_offer')
        request.update(client_id=str(cid), confirm='1', current_password=OWNER)
        response = owner.post(detail, data=request)
        record = parse_qs(urlparse(response.url).query)['record'][0]
        check('Offering creates an agreement for the selected current client', record!=plan_id and db('SELECT client_id FROM ci_service_subscriptions WHERE id=?', [record])==[{'client_id': cid}])
        return record, request

    request = data(owner, detail, 'service_plans_offer')
    owner.post(detail, data=dict(request, client_id=str(cid), confirm='1', current_password=OWNER, plan_hash='0'*64))
    check('Offer refuses a mismatched reviewed plan hash', snapshot()==published)
    agreement, original_offer = offer()
    offered = snapshot()
    owner.post(detail, data=original_offer)
    check('Identical offered request does not duplicate the agreement', snapshot()==offered)
    page_url = portal+'?agreement='+agreement
    owner_url = admin+'?view=service-agreement&record='+agreement
    r = client.get(page_url)
    check('Client sees pinned scope price and separate payment explanation', 'Reviewed care plan' in r.text and '$129.00 USD' in r.text and 'Reviewed website updates' in r.text and 'does not authorize automatic payments' in r.text)
    check('Client agreement page uses no-store and no-referrer', r.headers.get('Cache-Control')=='no-store, private' and r.headers.get('Referrer-Policy')=='no-referrer')
    check('Client agreement uses the workspace footer without a sales call to action', soup(r).select_one('#cta-title') is None)
    for outsider in [foreign, requests.Session()]:
        r = outsider.get(page_url)
        check('Foreign or anonymous client cannot read offered terms', 'Reviewed care plan' not in r.text and soup(r).select_one('[name=action][value=service_agreements_consent]') is None)
    check('Client list excludes another account offers', 'Reviewed care plan' not in foreign.get(portal+'?agreements=1').text)
    consent = data(client, page_url, 'service_agreements_consent')
    for label, changes in [('CSRF', {'confirm': '1', 'csrf': 'bad'}), ('missing confirmation', {}), ('wrong terms hash', {'confirm': '1', 'terms_hash': '0'*64}), ('wrong record identity', {'confirm': '1', 'record_id': '0'*32})]:
        client.post(page_url, data=dict(consent, **changes))
        check('Client consent refuses '+label+' without modifying saved agreement', snapshot()==offered)
    foreign_form = dict(consent, confirm='1', csrf=env['token'](foreign.get(portal)))
    foreign.post(page_url, data=foreign_form)
    check('Valid foreign-session CSRF cannot grant consent to another client terms', snapshot()==offered)

    # Consent is performed by actual Chromium, then owner activation and local end are tested.
    context = browser.new_context(viewport={'width': 1440, 'height': 1000})
    context.add_cookies([{'name': c.name, 'value': c.value, 'url': BASE+'/existing/'} for c in client.cookies])
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.goto(page_url)
    form = page.locator('form').filter(has=page.locator('[name=action][value=service_agreements_consent]'))
    expect(form.locator('[name=confirm]')).not_to_be_checked()
    page.screenshot(path=str(QA/'Service-Agreement-Desktop.png'), full_page=True)
    page.set_viewport_size({'width': 320, 'height': 800})
    check('Client terms and controls fit a narrow viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    page.screenshot(path=str(QA/'Service-Agreement-Mobile.png'), full_page=True)
    form.locator('[name=confirm]').check()
    form.get_by_role('button', name='Accept these service terms', exact=True).click()
    expect(page.get_by_text('Your agreement to these service terms was recorded.', exact=True)).to_be_visible()
    check('Actual browser consent is attributed to exact client and does not activate service', db('SELECT actor_client_id,actor_staff_id FROM ci_service_subscription_events WHERE subscription_id=?', [agreement])==[{'actor_client_id': cid, 'actor_staff_id': None}] and 'awaiting studio activation' in page.content())
    check('Client agreement browser has no script errors', not errors)
    context.close()
    agreed = snapshot()
    client.post(page_url, data=dict(consent, confirm='1'))
    check('Exact browser consent replay records no second event', snapshot()==agreed)
    submit(owner, owner_url, 'service_plans_activate', {'confirm': '1', 'current_password': OWNER})
    check('Owner can activate only the separately agreed service', 'Active agreement' in client.get(page_url).text)
    submit(owner, owner_url, 'service_plans_pause', {'confirm': '1', 'current_password': OWNER})
    check('Owner pause is visible to the bound client', 'Paused agreement' in client.get(page_url).text)
    submit(owner, owner_url, 'service_plans_resume', {'confirm': '1', 'current_password': OWNER})
    r = submit(client, page_url, 'service_agreements_cancel', {'confirm': '1', 'reason': 'Project completed <end>'})
    check('Client can end with an escaped retained reason and no further action form', 'Ended agreement' in r.text and 'Project completed &lt;end&gt;' in r.text and soup(r).select_one('[name=action][value=service_agreements_cancel]') is None)
    second, _ = offer()
    r = submit(client, portal+'?agreement='+second, 'service_agreements_abandon', {'confirm': '1'})
    check('Client may decline an unactivated offer without agreeing', 'Declined offer' in r.text and 'Service terms accepted' not in r.text)

    member = requests.Session()
    submit(member, admin+'?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    before = snapshot()
    r = member.get(listing)
    check('Delegated staff cannot open owner service-plan administration', r.status_code==403 and 'Reviewed care plan' not in r.text)
    r = owner.get(listing+'&support=fixture')
    check('Owner support selector excludes plan administration', r.status_code==403 and 'Reviewed care plan' not in r.text)
    # Enter real attributed support for this client, then prove agreements are excluded.
    chosen_form(owner, admin+'?view=support', 'support_policy', 'client_id', cid, {'allowed': '1', 'owner_password': OWNER})
    support = chosen_form(owner, admin+'?view=support', 'support_start', 'client_id', cid, {})
    sid = parse_qs(urlparse(support.url).query)['support'][0]
    r = owner.get(portal+'?support='+sid+'&agreement='+agreement)
    check('Actual support session cannot open service agreements', r.status_code==403 and 'Reviewed care plan' not in r.text)
    check('Blocked audience reads preserve agreement records', snapshot()==before)
    submit(owner, portal+'?support='+sid, 'support_end')
    context = browser.new_context(viewport={'width': 1440, 'height': 1000})
    context.add_cookies([{'name': c.name, 'value': c.value, 'url': BASE+'/existing/'} for c in owner.cookies])
    page = context.new_page()
    page.goto(detail)
    expect(page.get_by_role('heading', name='Offer these terms to a client')).to_be_visible()
    page.screenshot(path=str(QA/'Service-Plan-Owner-Desktop.png'), full_page=True)
    page.set_viewport_size({'width': 320, 'height': 800})
    expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
    page.evaluate("window.scrollTo({top:0,left:0,behavior:'instant'})")
    expect(page.locator('.sidebar-toggle')).to_be_in_viewport()
    page.locator('.sidebar-toggle').click()
    expect(page.locator('.sidebar-toggle')).to_have_attribute('aria-expanded', 'true')
    expect(page.locator('.admin-sidebar')).to_be_in_viewport()
    page.keyboard.press('Escape')
    expect(page.locator('.sidebar-toggle')).to_have_attribute('aria-expanded', 'false')
    expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
    check('Owner mobile menu opens and fully closes before reading plan terms', page.locator('.sidebar-toggle').get_attribute('aria-expanded')=='false')
    check('Owner service-plan forms fit a narrow viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    page.screenshot(path=str(QA/'Service-Plan-Owner-Mobile.png'), full_page=True)
    context.close()
    for private in ['app/plans/views.php', 'admin/controllers/plans.php', 'admin/views/plans.php', 'portal/controllers/plans.php', 'portal/views/plans.php']:
        check('Direct HTTP access denies agreement internal '+private, requests.get(BASE+'/existing/'+private).status_code==404)
    check('Agreement workflows create no invoice or provider or mail activity', db('SELECT * FROM ci_studio_invoices ORDER BY id')==invoices and provider_server.calls==calls and inbox_server.connections==connections and (QA/'mail-capture.txt').read_bytes()==capture)
