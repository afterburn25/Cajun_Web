"""Actual installed first-invoice checkout; no automatic payment or provider fulfillment."""
from types import SimpleNamespace
from urllib.parse import urlparse, parse_qs, urlencode
import time

CAPTURE_STEMS = ('Owner-Publication', 'Project', 'Review', 'Result', 'Invoice', 'New-Project')


def run_browser(env, browser):
    check, fixture, qa, seed = env['check'], env['fixture'], env['QA'], env['seed']
    site = env['BASE'] + '/hosting-checkout'; portal = site + '/portal/index.php'; admin = site + '/admin/index.php'
    owner_password = 'Synthetic native checkout owner'; client_password = 'Synthetic checkout client'
    contexts = []; errors = []; blocked = []; captures = []

    def context():
        ctx = browser.new_context(viewport={'width': 1440, 'height': 1050})
        def route(request):
            if request.request.url.startswith(env['BASE'] + '/') or request.request.url.startswith(('data:', 'about:')): request.continue_()
            else: blocked.append(urlparse(request.request.url).scheme); request.abort()
        ctx.route('**/*', route); contexts.append(ctx); page = ctx.new_page()
        page.on('pageerror', lambda e: errors.append(type(e).__name__))
        return ctx, page

    def goto(page, url):
        response = page.goto(url, wait_until='load', timeout=30000)
        if response is None: raise AssertionError('Missing navigation response')
        return response.status

    def form(page, action):
        return page.locator('form').filter(has=page.locator('input[name="action"][value="' + action + '"]'))

    def data(page, action):
        return env['form_data'](env['form_for'](SimpleNamespace(text=page.content(), url=page.url), action))

    def post(ctx, url, fields):
        # Explicit request bypasses client-side constraints only for negative/replay probes.
        return ctx.request.post(url, form=fields, max_redirects=0, timeout=30000)

    def login(page, email, password, client=False):
        goto(page, portal if client else admin + '?view=login'); f = form(page, 'login')
        f.locator('[name="email"]').fill(email); f.locator('[name="password"]').fill(password)
        f.locator('button[type="submit"],button:not([type])').click(); page.wait_for_load_state('load', timeout=30000)
        return 'Welcome, ' in page.content() if client else 'Sales pipeline' in page.content()

    def safe(text):
        return all(x not in text for x in (owner_password, client_password, 'SYNTHETIC_CHECKOUT_PROVIDER_KEY',
            'PRIVATE_', 'source_key_fingerprint', 'credential_sha256', 'mapping_json', 'plan_json', '$9.87'))

    def capture(page, stem):
        for width, height, size in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.evaluate('document.fonts.ready'); page.wait_for_timeout(350)
            check('Checkout ' + stem + ' ' + size + ' fits the viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
            name = 'Hosting-Checkout-' + stem + '-' + size + '.png'; page.evaluate('scrollTo(0,0)'); page.screenshot(path=str(qa/name), full_page=True)
            captures.append({'name': name, 'mode': 'full_page', 'viewport': {'width': width, 'height': height}})
        page.set_viewport_size({'width': 1440, 'height': 1050})

    def unchanged(label, before):
        check(label, fixture('state')['business_sha256'] == before['business_sha256'])

    try:
        owner, op = context(); alice, ap = context(); bob, bp = context(); contact, cp = context()
        check('Checkout owner signs in through installed login', login(op, 'checkout-owner@example.invalid', owner_password))
        check('Checkout primary client signs in through installed login', login(ap, 'checkout-client1@example.invalid', client_password, True))
        check('Checkout other client signs in through installed login', login(bp, 'checkout-client2@example.invalid', client_password, True))
        check('Contact-context negative starts with an actual primary login', login(cp, 'checkout-client1@example.invalid', client_password, True))
        initial = fixture('state'); owner_url = admin + '?view=hosting-offers&product_id=' + str(seed['product_id'])
        check('Owner opens current imported draft and plan controls', goto(op, owner_url) == 200 and form(op, 'hosting_catalog_preview').count() == 1)
        f = form(op, 'hosting_catalog_preview')
        check('Owner invoice checkout remains explicit opt-in', f.locator('[name="checkout_mode"]').input_value() == 'request-only')
        f.locator('[name="plan_id"]').select_option(seed['plan_id']); f.locator('[name="checkout_mode"]').select_option('invoice-on-confirmation')
        f.locator('[name="billing_timezone"]').fill('UTC'); f.locator('[name="billing_alignment"]').select_option('day-of-month')
        f.get_by_role('button', name='Preview offer', exact=True).click(); op.wait_for_load_state('load', timeout=30000)
        publish = data(op, 'hosting_catalog_publish'); before = fixture('state')
        check('Owner preview retains exact checkout policy without publishing', publish['checkout_mode'] == 'invoice-on-confirmation' and publish['billing_timezone'] == 'UTC' and before['counts'] == initial['counts'])
        response = post(owner, owner_url, {**publish, 'csrf': 'invalid', 'current_password': owner_password, 'confirm': '1'})
        check('Owner publication rejects invalid CSRF', response.status in (200, 400, 403) and 'Your session expired.' in response.text())
        unchanged('Owner invalid CSRF leaves commercial state unchanged', before)
        f = form(op, 'hosting_catalog_publish'); f.locator('[name="current_password"]').fill(owner_password); f.locator('[name="confirm"]').check()
        f.get_by_role('button', name='Publish hosting offer', exact=True).click(); op.wait_for_load_state('load', timeout=30000)
        published = fixture('state'); pub = published['latest_publication']
        check('Real owner POST publishes exactly the reviewed invoice checkout', 'Primary clients can review the terms and create their first invoice.' in op.content() and pub['id'] == publish['publication_id'] and published['counts']['publications'] == initial['counts']['publications'] + 1)
        capture(op, 'Owner-Publication')
        check('Client sees the current offer through installed navigation', goto(ap, portal + '?hosting=offers') == 200)
        link = ap.get_by_role('link', name='Review plan and first invoice', exact=True)
        check('Current offer exposes one exact checkout link', link.count() == 1 and parse_qs(urlparse(link.get_attribute('href')).query).get('offer') == [pub['id']])
        link.click(); ap.wait_for_load_state('load', timeout=30000); checkout_url = ap.url
        f = form(ap, 'hosting_catalog_checkout_preview'); selection = data(ap, 'hosting_catalog_checkout_preview')
        check('Project choices contain owned project and exclude other client', f.locator('[name="project_id"] option[value="1"]').count() == 1 and f.locator('[name="project_id"] option[value="2"]').count() == 0)
        check('Client selection withholds retained private publication details', safe(ap.content()))
        capture(ap, 'Project'); before = fixture('state')
        response = post(alice, checkout_url, {**selection, 'csrf': 'invalid', 'project_mode': 'existing', 'project_id': '1'})
        check('Client review rejects invalid CSRF', response.status in (200, 400, 403) and 'Your session expired.' in response.text())
        unchanged('Invalid client CSRF creates no retained review', before)
        response = post(alice, checkout_url, {**selection, 'project_mode': 'existing', 'project_id': '2'})
        check('Forged other-client project does not reach confirmation', response.status in (200, 403) and 'Agree and create first invoice' not in response.text())
        unchanged('Forged project choice preserves commercial rows', before)
        f.locator('[name="project_mode"]').select_option('existing'); f.locator('[name="project_id"]').select_option('1')
        f.get_by_role('button', name='Review plan and billing dates', exact=True).click(); ap.wait_for_load_state('load', timeout=30000)
        confirm = data(ap, 'hosting_catalog_checkout_confirm'); review_state = fixture('state'); request = confirm['request_id']
        check('Real client preview saves exactly one review and no invoice', review_state['counts']['reviews'] == before['counts']['reviews'] + 1 and review_state['counts']['invoices'] == before['counts']['invoices'])
        check('Single confirmation displays service price project and all billing dates', ap.get_by_role('heading', name='Confirm your plan and first invoice', exact=True).count() == 1 and all(t in ap.inner_text('body') for t in ['Existing project #1', 'First billing period', 'First invoice due', 'Next billing period starts', 'Billing timezone', 'UTC', '$25.00', 'full first-period invoice']))
        check('Review has one explicit commercial confirmation and no private document', form(ap, 'hosting_catalog_checkout_confirm').locator('[name="confirm"]').count() == 1 and safe(ap.content()))
        capture(ap, 'Review')
        for label, extra in [('Unchecked confirmation', {'confirm': ''}), ('Changed review fingerprint', {'confirm': '1', 'review_sha256': '0'*64})]:
            response = post(alice, portal + '?hosting=checkout', {**confirm, **extra})
            check(label + ' refuses invoice creation through HTTP', response.status in (200, 403) and 'Your hosting invoice' not in response.text())
            unchanged(label + ' leaves commercial state unchanged', review_state)
        # Cross-client request retains a valid token from that other client's own installed page.
        goto(bp, checkout_url); bob_csrf = data(bp, 'hosting_catalog_checkout_preview')['csrf']
        response = post(bob, portal + '?hosting=checkout', {**confirm, 'csrf': bob_csrf, 'confirm': '1'})
        check('Other authenticated client cannot confirm the retained review', response.status in (200, 403) and 'Your hosting invoice' not in response.text())
        unchanged('Other-client confirmation creates no commercial mutation', review_state)
        # Deliberately inject only established contact-context keys into this disposable logged-in session.
        goto(cp, checkout_url); contact_token = data(cp, 'hosting_catalog_checkout_preview')['csrf']
        cookies = [c for c in contact.cookies(portal) if c['name'].startswith('cwp_')]
        check('Contact-context probe has one existing isolated application session', len(cookies) == 1)
        mark = fixture('contact-context', {'session_id': cookies[0]['value']})
        check('Negative contact-context keys are set without a contact-login claim', mark['contact_context_set'] is True)
        response = post(contact, portal + '?hosting=checkout', {**confirm, 'csrf': contact_token, 'confirm': '1'})
        check('Contact-context HTTP confirmation is refused', response.status in (200, 403) and 'primary client' in response.text() and 'Your hosting invoice' not in response.text())
        unchanged('Contact-context denial leaves commercial state unchanged', review_state)
        goto(op, admin + '?view=support')
        support = form(op, 'support_start').filter(has=op.locator('input[name="client_id"][value="1"]'))
        check('Owner uses the actual support-start form for this client', support.count() == 1)
        support.get_by_role('button', name='Open client portal', exact=True).click(); op.wait_for_load_state('load', timeout=30000)
        check('Real support session banner is visible', op.get_by_role('complementary', name='Support session').count() == 1)
        support_id = parse_qs(urlparse(op.url).query)['support'][0]; support_data = data(op, 'support_end')
        response = post(owner, portal + '?' + urlencode({'support': support_id, 'hosting': 'checkout'}), {**confirm, 'csrf': support_data['csrf'], 'confirm': '1'})
        check('Real support session cannot confirm client checkout', response.status in (200, 403) and 'blocked during support' in response.text() and 'Your hosting invoice' not in response.text())
        unchanged('Support denial preserves commercial graph while support audit remains separate', review_state)
        form(op, 'support_end').get_by_role('button', name='Return to admin', exact=True).click(); op.wait_for_load_state('load', timeout=30000)
        started = int(time.time()); f = form(ap, 'hosting_catalog_checkout_confirm'); f.locator('[name="confirm"]').check()
        f.get_by_role('button', name='Agree and create first invoice', exact=True).click(); ap.wait_for_load_state('load', timeout=30000); finished = int(time.time())
        complete = fixture('state'); result = complete['results'][request]
        check('One real confirmation creates a first invoice now on the chosen owned project', ap.get_by_role('heading', name='Your hosting invoice', exact=True).count() == 1 and result['project_id'] == 1 and result['invoice_status'] == 'issued' and result['amount_cents'] == 2500 and started <= result['accepted_at'] <= finished and complete['counts']['invoices'] == review_state['counts']['invoices'] + 1 and complete['counts']['results'] == review_state['counts']['results'] + 1 and complete['counts']['jobs'] == review_state['counts']['jobs'])
        check('Confirmation records no payment attempt receipt or provider activation', complete['counts']['receipts'] == initial['counts']['receipts'] and complete['counts']['attempts'] == 0 and result['automatic_charge'] is False and result['provider_activation'] is False)
        capture(ap, 'Result'); result_url = ap.url
        expected_links = {'View invoice': {'invoice': str(result['invoice_id'])}, 'View project': {'job': str(result['project_id'])}, 'Service agreement': {'agreement': result['agreement_id']}, 'Billing schedule': {'recurring': '1', 'job': str(result['project_id']), 'schedule': result['schedule_id']}}
        for label, query in expected_links.items():
            target = ap.get_by_role('link', name=label, exact=True); actual = parse_qs(urlparse(target.get_attribute('href')).query)
            check('Result ' + label + ' link binds its original object', actual == {k:[v] for k,v in query.items()})
            with ap.expect_navigation(wait_until='load', timeout=30000) as navigation: target.click()
            response = navigation.value
            check('Result ' + label + ' navigation reaches an owned installed page', response is not None and response.status == 200 and 'Record unavailable' not in ap.content() and 'Request failed' not in ap.content() and safe(ap.content()))
            if label == 'View invoice':
                check('Current invoice page retains its exact reference and amount', result['invoice_reference'] in ap.inner_text('body') and '$25.00' in ap.inner_text('body'))
                capture(ap, 'Invoice')
            elif label == 'Service agreement':
                text = ap.inner_text('body')
                check('Agreement history attributes client consent and separate automatic activation truthfully', all(x in text for x in ['by Client #1.', 'Service terms confirmed at checkout', 'Agreement activated after confirmation', 'Automatic checkout']) and 'Studio #0' not in text)
            elif label == 'Billing schedule':
                text = ap.inner_text('body')
                check('Billing history attributes checkout confirmation to the actual client', 'Confirmed by Client #1 on ' in text and 'Billing terms confirmed at checkout' in text and 'Studio #0' not in text)
            goto(ap, result_url)
        response = post(alice, portal + '?hosting=checkout', {**confirm, 'confirm': '1'})
        check('Exact HTTP confirmation retry redirects to the original result', response.status == 303 and parse_qs(urlparse(response.headers['location']).query).get('checkout_result') == [request])
        unchanged('Completed HTTP retry creates no duplicate commercial rows', complete)
        response = bob.request.get(result_url, timeout=30000)
        check('Other client cannot read the completed invoice result', 'Your hosting invoice' not in response.text() and result['invoice_reference'] not in response.text())
        unchanged('Foreign result read is commercially inert', complete)
        # Use the actual owner receipt form fields, but the client's own valid session CSRF.
        goto(op, admin + '?view=invoice-payments&invoice_id=' + str(result['invoice_id'])); receipt = data(op, 'payments_ledger_receive')
        receipt.update(csrf=confirm['csrf'], amount='25.00', method='bank', reference='Synthetic unauthorized HTTP receipt', confirm='1')
        response = post(alice, op.url, receipt)
        check('Client ledger POST reaches real admin-auth redirect instead of recording a receipt', response.status == 303 and 'view=login' in response.headers.get('location', ''))
        unchanged('Unauthorized ledger redirect preserves invoice payment and commercial history', complete)
        # Second independent purchase uses the same real offer but explicitly selects a dedicated project.
        goto(ap, checkout_url); f = form(ap, 'hosting_catalog_checkout_preview'); f.locator('[name="project_mode"]').select_option('new-hosting')
        f.get_by_role('button', name='Review plan and billing dates', exact=True).click(); ap.wait_for_load_state('load', timeout=30000)
        new_confirm = data(ap, 'hosting_catalog_checkout_confirm'); f = form(ap, 'hosting_catalog_checkout_confirm'); f.locator('[name="confirm"]').check()
        f.get_by_role('button', name='Agree and create first invoice', exact=True).click(); ap.wait_for_load_state('load', timeout=30000)
        last = fixture('state'); new = last['results'][new_confirm['request_id']]
        check('Client may create a dedicated hosting project and current first invoice', new['project_id'] not in (1, 2, result['project_id']) and new['invoice_status'] == 'issued' and last['counts']['jobs'] == complete['counts']['jobs'] + 1 and last['counts']['invoices'] == complete['counts']['invoices'] + 1)
        capture(ap, 'New-Project'); response = post(alice, portal + '?hosting=checkout', {**new_confirm, 'confirm': '1'})
        check('Dedicated-project HTTP retry returns its original result', response.status == 303 and parse_qs(urlparse(response.headers['location']).query).get('checkout_result') == [new_confirm['request_id']])
        unchanged('Dedicated project and invoice are never duplicated by exact retry', last)
        check('Checkout browser has no JavaScript page errors', not errors)
        check('Checkout browser requests only local resources', not blocked)
        return {'captures': captures, 'existing_project_result': result, 'new_project_result': new, 'contact_probe': 'Established-key negative only; not contact-login acceptance', 'live_charge_or_provider_write': False}
    finally:
        for ctx in contexts: ctx.close()
