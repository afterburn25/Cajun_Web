"""Focused real HTTP/Chromium continuation of the isolated hosting native fixture.

The caller supplies the existing complete-browser form helpers, actual PHP fixture
operation, Chromium instance and evidence directory. No provider/payment action.
"""
import base64
import json
from types import SimpleNamespace
from urllib.parse import parse_qs, urlparse


def run_browser(env, browser):
    check, fixture, qa = env['check'], env['fixture'], env['QA']
    parse, fields = env['form_for'], env['form_data']
    site = env['BASE'] + '/hosting-catalog'
    admin = site + '/admin/index.php?view=hosting-offers'
    portal = site + '/portal/index.php'
    public = site + '/index.php?page=hosting'
    seed = env['seed']; owner_password = 'Synthetic hosting catalog owner'
    client_password = 'Synthetic hosting browser client'
    contexts = []; errors = []; forbidden_network = []

    def context():
        ctx = browser.new_context(viewport={'width': 1440, 'height': 1050})
        # Public/browser resources must stay local. This is not a server-egress proof.
        def route(request):
            url = request.request.url
            if url.startswith(env['BASE'] + '/') or url.startswith(('data:', 'about:')):
                request.continue_()
            else:
                forbidden_network.append(url.split('?', 1)[0]); request.abort()
        ctx.route('**/*', route); contexts.append(ctx)
        page = ctx.new_page(); page.on('pageerror', lambda error: errors.append(type(error).__name__))
        return ctx, page

    def goto(page, url):
        response = page.goto(url, wait_until='load', timeout=30000)
        if response is None: raise AssertionError('Navigation returned no response')
        return response.status

    def form(page, action):
        return page.locator('form').filter(has=page.locator('input[name="action"][value="' + action + '"]'))

    def values(page, action):
        return fields(parse(SimpleNamespace(text=page.content(), url=page.url), action))

    def post(ctx, url, data):
        response = ctx.request.post(url, form=data, timeout=25000)
        return SimpleNamespace(text=response.text(), url=response.url, status_code=response.status)

    def get(ctx, url):
        response = ctx.request.get(url, timeout=15000)
        return SimpleNamespace(text=response.text(), url=response.url, status_code=response.status)

    def click(page, action, label):
        form(page, action).get_by_role('button', name=label, exact=True).click()
        page.wait_for_load_state('load', timeout=30000)

    def login(page, email, password, client=False):
        url = portal if client else site + '/admin/index.php?view=login'
        goto(page, url); f = form(page, 'login')
        f.locator('[name="email"]').fill(email); f.locator('[name="password"]').fill(password)
        f.locator('button[type="submit"], button:not([type])').click()
        page.wait_for_load_state('load', timeout=30000)
        return 'Welcome, ' in page.content() if client else 'Sales pipeline' in page.content()

    def stable(before):
        return fixture('state')['sha256'] == before['sha256']

    def retained(response, action, data, keys, disabled=False):
        f = parse(response, action); observed = fields(f)
        return (all(observed.get(k) == data[k] for k in keys)
                and observed.get('current_password', '') == ''
                and 'confirm' not in observed
                and (not disabled or f.select_one('button[disabled]') is not None))

    def capture(page, stem):
        for summary in page.locator('details > summary').all():
            if summary.inner_text().strip() == 'Plan terms' and not summary.evaluate('(node)=>node.parentElement.open'):
                summary.click()
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height})
            page.evaluate('document.fonts.ready')
            # Existing drawer transition has a finite duration after desktop/mobile resize.
            page.wait_for_timeout(350)
            page.evaluate('scrollTo(0, 0)')
            check(stem + ' ' + label + ' has no horizontal document overflow',
                  page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
            page.screenshot(path=str(qa / (stem + '-' + label + '.png')), full_page=True)
        page.set_viewport_size({'width': 1440, 'height': 1050})

    def safe_text(text):
        return all(v not in text for v in ('PRIVATE_PROVIDER_REFERENCE', 'PRIVATE_DRAFT_FEATURES',
            'PRIVATE_DRAFT_TERMS', 'PRIVATE_COST_REFERENCE', 'SYNTHETIC_HOSTING_CATALOG_KEY',
            'source_key_fingerprint', 'credential_sha256', '$9.87', owner_password, client_password))

    try:
        owner, op = context(); alice, ap = context(); bob, bp = context(); staff, sp = context(); anon, pp = context()
        check('Hosting browser owner signs in through the installed app', login(op, 'hosting-staff1@example.invalid', owner_password))
        check('Hosting browser first client signs in through the installed app', login(ap, 'hosting-client1@example.invalid', client_password, True))
        check('Hosting browser second client signs in through the installed app', login(bp, 'hosting-client2@example.invalid', client_password, True))
        check('Hosting browser delegated administrator signs in through the installed app', login(sp, 'hosting-staff2@example.invalid', owner_password))
        before = fixture('state')
        check('Hosting browser native history is initially ready', before['ready'] is True)
        response = get(anon, portal + '?hosting=requests')
        check('Anonymous hosting request history requires login', 'hosting_catalog_withdraw_request' not in response.text and 'hosting_catalog_request' not in response.text and 'hosting-client1@example.invalid' not in response.text)
        check('Delegated administrator cannot open owner hosting screen', get(staff, admin).status_code == 403 and stable(before))
        check('Invalid support selector cannot open owner hosting screen', get(owner, admin + '&support=').status_code == 403 and stable(before))
        check('Invalid support selector cannot open client hosting screen', get(alice, portal + '?hosting=offers&support=').status_code == 403 and stable(before))
        catalog = get(owner, site + '/admin/index.php?view=catalog&id=' + str(seed['product_id']))
        link = env['soup'](catalog).select_one('a[href*="view=hosting-offers"][href*="product_id=' + str(seed['product_id']) + '"]')
        check('Imported draft offer link retains the selected product_id', catalog.status_code == 200 and link is not None)
        goto(op, catalog.url)
        image_form = form(op, 'catalog_import_image')
        image_form.locator('[name="product_image"]').set_input_files({
            'name': 'synthetic-hosting-browser.png', 'mimeType': 'image/png',
            'buffer': base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGOQC6gAAAF2AOe0MgrYAAAAAElFTkSuQmCC'),
        })
        image_form.locator('[name="owner_password"]').fill(owner_password)
        click(op, 'catalog_import_image', 'Save image')
        uploaded = fixture('state')
        image_record = next(i for i in uploaded['images'] if int(i['product_id']) == seed['product_id'])
        check('Chromium owner uploads a local PNG through the actual multipart image form', 'Draft product image saved.' in op.content() and uploaded['counts']['media'] == before['counts']['media'] + 1 and isinstance(image_record['media_id'], str) and len(image_record['media_id']) == 32)
        before = uploaded  # All subsequent refusal/review baselines include the legitimate image change.
        selected = admin + '&product_id=' + str(seed['product_id'])
        check('Owner can open its imported draft and matching saved plan', goto(op, selected) == 200 and form(op, 'hosting_catalog_preview').locator('option[value="' + seed['plan_id'] + '"]').count() == 1)
        form(op, 'hosting_catalog_preview').locator('[name="plan_id"]').select_option(seed['plan_id'])
        click(op, 'hosting_catalog_preview', 'Preview offer')
        stale = values(op, 'hosting_catalog_publish')
        check('Owner preview is read-only and binds an exact public card', stable(before) and len(stale['review_sha256']) == 64 and 'One reviewed website' in op.content())
        fixture('edit-summary'); after_edit = fixture('state')
        rejected = post(owner, selected, {**stale, 'confirm': '1', 'current_password': owner_password})
        check('Changed product refuses the old review without replacing its identity', stable(after_edit) and retained(rejected, 'hosting_catalog_publish', stale, ['publication_id', 'product_id', 'plan_id', 'review_sha256'], True))
        goto(op, selected); form(op, 'hosting_catalog_preview').locator('[name="plan_id"]').select_option(seed['plan_id'])
        click(op, 'hosting_catalog_preview', 'Preview offer'); publish = values(op, 'hosting_catalog_publish')
        for label, override in [('CSRF', {'csrf': 'invalid-csrf'}), ('confirmation', {'confirm': ''}), ('current password', {'current_password': 'incorrect'})]:
            data = {**publish, 'confirm': '1', 'current_password': owner_password, **override}
            rejected = post(owner, selected, data)
            check('Owner publication rejects invalid ' + label + ' without business writes', stable(after_edit))
            if label == 'current password':
                check('Publication retry retains review fields but clears password and confirmation', retained(rejected, 'hosting_catalog_publish', publish, ['publication_id', 'product_id', 'plan_id', 'review_sha256']))
        capture(op, 'Hosting-Offers-Owner-Preview')
        form(op, 'hosting_catalog_publish').locator('[name="current_password"]').fill(owner_password)
        form(op, 'hosting_catalog_publish').locator('[name="confirm"]').check()
        click(op, 'hosting_catalog_publish', 'Publish hosting offer')
        published = fixture('state'); pub = next(p for p in published['publications'] if p['id'] == publish['publication_id'])
        check('Chromium owner publishes exactly one new immutable offer', published['counts']['hosting_catalog_publications'] == after_edit['counts']['hosting_catalog_publications'] + 1 and int(pub['product_id']) == seed['product_id'])
        replay = post(owner, selected, {**publish, 'confirm': '1', 'current_password': owner_password})
        check('Exact HTTP publication retry does not duplicate history', replay.status_code == 200 and stable(published))
        check('Anonymous public page renders reviewed commercial terms safely', goto(pp, public) == 200 and 'Reviewed shared hosting' in pp.content() and 'One reviewed website' in pp.content() and '$25.00' in pp.content() and safe_text(pp.content()))
        public_image = pp.locator('.hosting-catalog-image')
        check('Published public card uses the exact uploaded local image URL', public_image.count() == 1 and public_image.get_attribute('src') == site + '/media/index.php?id=' + image_record['media_id'] and json.loads(pub['document_json'])['product']['media_id'] == image_record['media_id'])
        pp.wait_for_function('()=>{const i=document.querySelector(".hosting-catalog-image");return i&&i.complete&&i.naturalWidth>0}', timeout=30000)
        check('Anonymous public card actually loads the uploaded image pixels', public_image.evaluate('(i)=>i.complete&&i.naturalWidth>0&&i.naturalHeight>0'))
        capture(pp, 'Hosting-Offers-Public')
        support, sup = context()
        check('Support probe uses a separate real owner login', login(sup, 'hosting-staff1@example.invalid', owner_password))
        support_url = site + '/admin/index.php?view=support'
        goto(sup, support_url)
        form(sup, 'support_migrate').locator('[name="owner_password"]').fill(owner_password)
        form(sup, 'support_migrate').locator('[name="confirm"]').check()
        click(sup, 'support_migrate', 'Enable support sessions')
        policy = form(sup, 'support_policy').filter(has=sup.locator('[name="client_id"][value="1"]'))
        policy.locator('[name="allowed"]').check(); policy.locator('[name="owner_password"]').fill(owner_password)
        policy.get_by_role('button', name='Save client support policy', exact=True).click(); sup.wait_for_load_state('load', timeout=30000)
        opening = form(sup, 'support_start').filter(has=sup.locator('[name="client_id"][value="1"]'))
        opening.get_by_role('button', name='Open client portal', exact=True).click(); sup.wait_for_load_state('load', timeout=30000)
        support_id = parse_qs(urlparse(sup.url).query)['support'][0]
        support_end = values(sup, 'support_end')
        check('Actual authorized support session cannot render hosting request controls', len(support_id) == 32 and get(support, portal + '?support=' + support_id + '&hosting=offers').status_code == 403)
        goto(ap, portal + '?hosting=offers'); request = values(ap, 'hosting_catalog_request')
        blocked = post(support, portal + '?support=' + support_id + '&hosting=offers', {**request, 'csrf': support_end['csrf'], 'confirm': '1'})
        check('Actual support session cannot submit an independent client hosting request', blocked.status_code == 403 and stable(published))
        post(support, portal + '?support=' + support_id, support_end)
        check('Authenticated request form binds the exact published document', request['publication_id'] == pub['id'] and request['publication_sha256'] == pub['document_sha256'])
        for label, override in [('CSRF', {'csrf': 'invalid-csrf'}), ('confirmation', {'confirm': ''}), ('publication hash', {'publication_sha256': '0' * 64})]:
            data = {**request, 'confirm': '1', **override}; rejected = post(alice, portal + '?hosting=offers', data)
            check('Client request rejects invalid ' + label + ' without business writes', stable(published))
            if label == 'publication hash':
                check('Stale client request keeps its original hash in a disabled form', retained(rejected, 'hosting_catalog_request', data, ['request_id', 'publication_id', 'publication_sha256'], True))
        form(ap, 'hosting_catalog_request').locator('[name="confirm"]').check()
        click(ap, 'hosting_catalog_request', 'Request this plan')
        requested = fixture('state'); req = next(r for r in requested['requests'] if r['id'] == request['request_id'])
        check('Chromium client creates one own request without an agreement or invoice', int(req['client_id']) == 1 and requested['counts']['hosting_catalog_requests'] == published['counts']['hosting_catalog_requests'] + 1 and requested['counts']['service_subscriptions'] == published['counts']['service_subscriptions'] and requested['counts']['studio_invoices'] == 0)
        replay = post(alice, portal + '?hosting=offers', {**request, 'confirm': '1'})
        check('Exact client HTTP retry does not duplicate a request', replay.status_code == 200 and stable(requested))
        goto(bp, portal + '?hosting=offers'); other_csrf = values(bp, 'hosting_catalog_request')['csrf']
        post(bob, portal + '?hosting=offers', {**request, 'csrf': other_csrf, 'confirm': '1'})
        check('Another authenticated client cannot replay the first client request', stable(requested))
        foreign_history = get(bob, portal + '?hosting=requests')
        check('Other client history does not contain the new private request', foreign_history.status_code == 200 and req['id'] not in foreign_history.text and req['document_sha256'] not in foreign_history.text)
        request_url = admin + '&tab=requests&request_id=' + req['id']
        goto(op, request_url); conversion = values(op, 'hosting_catalog_convert')
        options = form(op, 'hosting_catalog_convert').locator('[name="job_id"] option').evaluate_all('(nodes)=>nodes.map(n=>n.value)')
        check('Owner project picker contains only this client project', '1' in options and '2' not in options)
        for label, override in [('CSRF', {'csrf': 'invalid-csrf'}), ('other client project', {'job_id': '2'}), ('request hash', {'request_sha256': '0' * 64}), ('current password', {'current_password': 'incorrect'})]:
            data = {**conversion, 'job_id': '1', 'confirm': '1', 'current_password': owner_password, **override}
            rejected = post(owner, request_url, data)
            check('Agreement conversion rejects invalid ' + label + ' without business writes', stable(requested))
            if label == 'current password':
                check('Conversion retry retains decision request and project while clearing secrets', retained(rejected, 'hosting_catalog_convert', data, ['decision_id', 'request_id', 'request_sha256', 'job_id']))
        capture(op, 'Hosting-Offers-Owner-Request')
        form(op, 'hosting_catalog_convert').locator('[name="job_id"]').select_option('1')
        form(op, 'hosting_catalog_convert').locator('[name="current_password"]').fill(owner_password)
        form(op, 'hosting_catalog_convert').locator('[name="confirm"]').check()
        click(op, 'hosting_catalog_convert', 'Offer agreement')
        converted = fixture('state'); decision = next(d for d in converted['decisions'] if d['request_id'] == req['id'])
        agreement_id = decision['agreement_id']; agreement = converted['agreements'][agreement_id]
        check('Chromium conversion links one exact pending same-client plan agreement', decision['kind'] == 'offered' and int(decision['job_id']) == 1 and agreement['client_id'] == 1 and agreement['state'] == 'pending-consent' and agreement['revision'] == 0 and not agreement['consented'] and agreement['plan_json'] == json.loads(pub['document_json'])['plan_json'] and converted['counts']['service_subscriptions'] == requested['counts']['service_subscriptions'] + 1)
        replay = post(owner, request_url, {**conversion, 'job_id': '1', 'confirm': '1', 'current_password': owner_password})
        check('Exact conversion retry returns the same agreement without duplicates', replay.status_code == 200 and stable(converted))
        goto(ap, portal + '?hosting=requests')
        agreement_link = ap.locator('a[href*="agreement=' + agreement_id + '"]')
        check('Client request history links to its exact service agreement', agreement_link.count() == 1 and safe_text(ap.content()))
        capture(ap, 'Hosting-Offers-Client-History')
        agreement_link.click(); ap.wait_for_load_state('load', timeout=30000)
        consent = values(ap, 'service_agreements_consent')
        check('Linked agreement requires independent consent for the displayed terms', consent['record_id'] == agreement_id and 'does not authorize automatic payments' in ap.content())
        foreign = get(bob, portal + '?agreement=' + agreement_id)
        check('Other client cannot open the new linked agreement', foreign.status_code == 200 and 'Subscription unavailable.' in foreign.text and 'service_agreements_consent' not in foreign.text and 'Accept these service terms' not in foreign.text)
        form(ap, 'service_agreements_consent').locator('[name="confirm"]').check()
        click(ap, 'service_agreements_consent', 'Accept these service terms')
        consented = fixture('state'); now = consented['agreements'][agreement_id]
        check('Chromium consent records one client event without activating hosting', now['revision'] == 1 and now['consented'] and now['state'] == 'pending-consent')
        capture(ap, 'Hosting-Offers-Client-Agreement')
        goto(ap, portal + '?hosting=offers'); decline_request = values(ap, 'hosting_catalog_request')
        form(ap, 'hosting_catalog_request').locator('[name="confirm"]').check()
        click(ap, 'hosting_catalog_request', 'Request this plan')
        goto(op, admin + '&tab=requests&request_id=' + decline_request['request_id'])
        form(op, 'hosting_catalog_decline').locator('[name="reason"]').fill('Please review the project requirements first.')
        form(op, 'hosting_catalog_decline').locator('[name="current_password"]').fill(owner_password)
        form(op, 'hosting_catalog_decline').locator('[name="confirm"]').check()
        click(op, 'hosting_catalog_decline', 'Decline request')
        declined = fixture('state')
        check('Chromium owner decline records a reason without another agreement', next(d for d in declined['decisions'] if d['request_id'] == decline_request['request_id'])['kind'] == 'declined' and declined['agreements'] == consented['agreements'])
        goto(ap, portal + '?hosting=requests')
        check('Client sees the owner decline reason in its own history', 'Please review the project requirements first.' in ap.content())
        # Historical request withdrawal and owner decline remain distinct from agreement cancellation.
        pending_withdraw = format(103, '032x'); pending_decline = format(105, '032x')
        goto(ap, portal + '?hosting=requests')
        withdrawal = ap.locator('form').filter(has=ap.locator('input[name="action"][value="hosting_catalog_withdraw_request"]')).filter(has=ap.locator('input[name="request_id"][value="' + pending_withdraw + '"]'))
        withdrawal.locator('[name="reason"]').fill('Browser client no longer needs this request.')
        withdrawal.locator('[name="confirm"]').check(); withdrawal.get_by_role('button', name='Withdraw request', exact=True).click(); ap.wait_for_load_state('load', timeout=30000)
        withdrawn_request = fixture('state')
        check('Chromium client withdraws an old pending request without cancelling agreements', next(d for d in withdrawn_request['decisions'] if d['request_id'] == pending_withdraw)['kind'] == 'withdrawn' and withdrawn_request['agreements'] == consented['agreements'])
        # Original native request105 was already declined. Its real detail must not offer conversion again.
        goto(op, admin + '&tab=requests&request_id=' + pending_decline)
        check('Handled owner request detail exposes history without another decision form', form(op, 'hosting_catalog_convert').count() == 0 and form(op, 'hosting_catalog_decline').count() == 0 and 'already been handled' in op.content())
        goto(op, selected); withdraw_offer = values(op, 'hosting_catalog_withdraw')
        form(op, 'hosting_catalog_withdraw').locator('[name="reason"]').fill('Browser owner closes new requests.')
        form(op, 'hosting_catalog_withdraw').locator('[name="current_password"]').fill(owner_password)
        form(op, 'hosting_catalog_withdraw').locator('[name="confirm"]').check()
        click(op, 'hosting_catalog_withdraw', 'Withdraw offer')
        closed = fixture('state')
        check('Owner withdrawal removes public availability and preserves client agreement', goto(pp, public) == 200 and 'Reviewed shared hosting' not in pp.content() and closed['agreements'] == consented['agreements'])
        stale_request = {**request, 'request_id': 'f' * 32, 'confirm': '1'}
        rejected = post(alice, portal + '?hosting=offers', stale_request)
        check('Withdrawn offer refuses a new request and retains the disabled submission', stable(closed) and retained(rejected, 'hosting_catalog_request', stale_request, ['request_id', 'publication_id', 'publication_sha256'], True))
        fixture('damage-marker')
        try:
            unavailable = get(anon, public); private = get(alice, portal + '?hosting=requests')
            check('Damaged history renders generic public and private unavailable messages', unavailable.status_code == 200 and private.status_code == 200 and 'temporarily unavailable' in unavailable.text.lower() and 'temporarily unavailable' in private.text.lower() and safe_text(unavailable.text) and safe_text(private.text) and all(v not in unavailable.text + private.text for v in ('SQLSTATE', 'hoci_', 'Restore its original', 'provider-master.key')))
        finally:
            fixture('restore-marker')
        final = fixture('state')
        check('Exact history recovery restores all business rows and original provider key', final['sha256'] == closed['sha256'] and final['provider_key_sha256'] == before['provider_key_sha256'] and final['ready'])
        check('Browser flow creates no invoice or payment request and leaves activation pending', final['counts']['studio_invoices'] == 0 and final['counts']['catalog_invoice_snapshots'] == 0 and final['counts']['payment_requests'] == 0 and all(a['state'] == 'pending-consent' for a in final['agreements'].values()))
        check('Hosting browser has no script errors or external resource requests', not errors and not forbidden_network)
        return {'post_upload_business_sha256': before['sha256'], 'final_business_sha256': final['sha256'],
                'provider_key_sha256': final['provider_key_sha256'], 'agreement_revision': now['revision'],
                'scope': 'Real packaged owner/client UI and local HTTP on the preserved hoci_ synthetic native dataset. CLI seed and one real catalog edit; no provider transport, payment, full-matrix browser or visual approval.'}
    finally:
        for ctx in contexts:
            ctx.close()
