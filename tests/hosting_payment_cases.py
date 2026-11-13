"""Installed UX-08 hosting payment and lifecycle continuation.

The shared checkout runner owns the package, server and PHP bridge.  This leaf
module owns only the browser assertions.  Every gateway/provider outcome is a
closed local fixture selected through the CLI bridge; no HTTP input can choose
the transport and no live charge or provider operation is attempted.
"""
import hashlib
import re
import struct
from urllib.parse import parse_qs, urlencode, urljoin, urlparse

CAPTURES = tuple(
    'Hosting-Payment-' + stage + '-' + size + '.png'
    for stage in ('Awaiting', 'Paid-Setup-Pending', 'Active', 'VPS-Unavailable')
    for size in ('Desktop', 'Mobile')
)


def run_browser(env, browser):
    """Continue the installed hcc_ graph prepared by the shared driver."""
    fixture, qa, driver_check = env['fixture'], env['QA'], env['check']
    base = env['BASE'] + '/hosting-checkout'
    portal = base + '/portal/index.php'
    client_password = 'Synthetic checkout client'
    contexts, errors, blocked, captures, checks = [], [], [], [], []

    def check(name, condition):
        passed = bool(condition)
        checks.append({'name': name, 'passed': passed})
        driver_check(name, passed)

    publication = env.get('payment_publication')
    publication_ok = (
        isinstance(publication, dict)
        and re.fullmatch(r'[a-f0-9]{32}', str(publication.get('publication_id', ''))) is not None
        and re.fullmatch(r'[a-f0-9]{64}', str(publication.get('publication_sha256', ''))) is not None
        and publication.get('product_id') == env['seed']['product_id']
        and publication.get('amount_cents') == 2700
        and publication.get('currency') == 'USD'
        and publication.get('cadence') == 'monthly'
    )
    check('Hosting payment binds the exact current monthly billing publication', publication_ok)
    payment_amount = publication['amount_cents']
    payment_price = f'${payment_amount // 100}.{payment_amount % 100:02d}'

    def route_handler(route):
        url = route.request.url
        host = (urlparse(url).hostname or '').lower()
        # Exercise the actual local tokenization script against an in-memory
        # Accept.js response.  The real Authorize.Net script is never fetched.
        if host in ('js.authorize.net', 'jstest.authorize.net'):
            route.fulfill(status=200, content_type='application/javascript', body='''
window.Accept={dispatchData:function(data,done){
  const card=(data&&data.cardData)||{};
  if(!card.cardNumber||!card.month||!card.year||!card.cardCode){
    done({messages:{resultCode:"Error",message:[{code:"E_TEST",text:"Synthetic card fields required"}]}});return;
  }
  done({messages:{resultCode:"Ok",message:[]},opaqueData:{dataDescriptor:"COMMON.ACCEPT.INAPP.PAYMENT",dataValue:"synthetic-local-opaque-token"}});
}};
''')
            return
        if host not in ('127.0.0.1', 'localhost'):
            blocked.append({'method': route.request.method, 'url': url})
            route.abort()
            return
        route.continue_()

    def context():
        ctx = browser.new_context(base_url=base)
        ctx.route('**/*', route_handler)
        page = ctx.new_page()
        page.on('pageerror', lambda error: errors.append(str(error)))
        contexts.append(ctx)
        return ctx, page

    def goto(page, url):
        response = page.goto(url, wait_until='load', timeout=30000)
        if response is None:
            raise AssertionError('Installed hosting payment navigation has no response')
        return response.status

    def click(page, locator):
        with page.expect_navigation(wait_until='load', timeout=30000) as event:
            locator.click()
        response = event.value
        if response is None:
            raise AssertionError('Installed hosting payment action has no response')
        return response.status

    def form(page, action):
        return page.locator('form').filter(has=page.locator('input[name="action"][value="' + action + '"]'))

    def login(page, client):
        check('Hosting payment client ' + str(client) + ' reaches the installed sign-in form',
              goto(page, portal) == 200 and form(page, 'login').count() == 1)
        target = form(page, 'login')
        target.locator('[name="email"]').fill('checkout-client' + str(client) + '@example.invalid')
        target.locator('[name="password"]').fill(client_password)
        check('Hosting payment client ' + str(client) + ' authenticates through the installed form',
              click(page, target.locator('button[type="submit"],button:not([type])')) == 200
              and form(page, 'login').count() == 0)

    def state():
        value = fixture('payment-state', {
            'publication_id': publication['publication_id'],
            'publication_sha256': publication['publication_sha256'],
            'amount_cents': payment_amount,
        })
        if not isinstance(value, dict) or value.get('prepared') is not True:
            raise AssertionError('Bounded prepared hosting payment state required')
        if (value.get('publication_id') != publication['publication_id']
                or value.get('publication_sha256') != publication['publication_sha256']
                or value.get('amount_cents') != payment_amount):
            raise AssertionError('Exact current payment publication state required')
        journeys = value.get('journeys')
        if not isinstance(journeys, dict) or set(journeys) != {'approved', 'declined', 'uncertain'}:
            raise AssertionError('Exact approved, declined and uncertain fixture journeys required')
        required = {'request_id', 'invoice_id', 'invoice_reference', 'result_url', 'invoice_url', 'payment_url',
                    'payment_stage', 'invoice_status', 'charge_state', 'receipt_count', 'attempt_count',
                    'fulfillment_count', 'provider_attempt_count'}
        for name, row in journeys.items():
            if not isinstance(row, dict) or not required.issubset(row):
                raise AssertionError('Complete ' + name + ' payment projection required')
            if not re.fullmatch(r'[a-f0-9]{32}', str(row['request_id'])):
                raise AssertionError('Opaque checkout request identity required')
            if not isinstance(row['invoice_id'], int) or row['invoice_id'] < 1:
                raise AssertionError('Positive invoice identity required')
            for key in ('result_url', 'invoice_url', 'payment_url'):
                parsed = urlparse(urljoin(base + '/', str(row[key])))
                if parsed.hostname not in ('127.0.0.1', 'localhost'):
                    raise AssertionError('Local installed payment destination required')
        if value.get('live_gateway_calls') != 0 or value.get('live_provider_calls') != 0:
            raise AssertionError('Synthetic-only hosting payment fixture required')
        return value

    def safe(page):
        text = page.content()
        return all(secret not in text for secret in (
            client_password, 'SYNTHETIC_CHECKOUT_PROVIDER_KEY', 'synthetic-local-opaque-token',
            'transaction_key', 'api_login_id', 'credential_sha256', 'mapping_json', 'plan_json'))

    def fill_card(page, outcome, action='hosting_catalog_checkout_pay'):
        target = form(page, action)
        body = page.inner_text('body')
        check('Hosting payment page retains the exact selected request before tokenization',
              parse_qs(urlparse(page.url).query).get('checkout_result') == [outcome['request_id']])
        check('Hosting payment page retains the exact selected invoice before tokenization',
              outcome['invoice_reference'] in body)
        check('Hosting payment page exposes one authoritative payment stage',
              page.locator('[data-hosting-payment-stage]').count() == 1)
        check('Hosting payment page remains awaiting payment before tokenization',
              page.get_by_text('Awaiting payment', exact=True).count() >= 1)
        check('Configured hosting payment never silently downgrades to unavailable',
              'Online card checkout is currently unavailable' not in body)
        check('Hosting payment page exposes one exact payment form', target.count() == 1)
        check('Hosting payment form exposes one unnamed card-number field',
              target.locator('[data-card-number]').count() == 1)
        check('Hosting payment form exposes one token descriptor field',
              target.locator('[name="descriptor"]').count() == 1)
        check('Hosting payment form exposes one opaque payment-token field',
              target.locator('[name="payment_token"]').count() == 1)
        check('Hosting payment form exposes one exact attempt identity',
              target.locator('[name="attempt_key"]').count() == 1)
        check('Hosting payment form never submits raw card or security-code fields',
              not target.locator('[name="card_number"],[name="cvv"],[name="card_code"]').count())
        check('Hosting payment form is the actual tokenized-card form',
              target.count() == 1 and target.locator('[data-card-number]').count() == 1
              and target.locator('[name="descriptor"]').count() == 1
              and target.locator('[name="payment_token"]').count() == 1
              and target.locator('[name="attempt_key"]').count() == 1
              and not target.locator('[name="card_number"],[name="cvv"],[name="card_code"]').count())
        target.locator('[data-card-number]').fill('4111111111111111')
        target.locator('[data-card-month]').fill('12')
        target.locator('[data-card-year]').fill('39')
        target.locator('[data-card-code]').fill('123')
        for name, value in {'first_name': 'Synthetic', 'last_name': 'Buyer', 'address': '1 Local Test Way',
                            'city': 'Lafayette', 'state': 'LA', 'zip': '70501', 'country': 'US'}.items():
            field = target.locator('[name="' + name + '"]')
            if field.count():
                field.fill(value)
        return target

    def submit_card(page, outcome):
        target = fill_card(page, outcome)
        submitted = dict(target.locator('input[name]').evaluate_all(
            'nodes=>nodes.map(node=>[node.name,node.value])'))
        configured = fixture('payment-outcome', {'request_id': outcome['request_id'],
                                                  'outcome': outcome['outcome'],
                                                  'attempt_key': submitted['attempt_key']})
        check('Fixture selects one closed local ' + outcome['outcome'] + ' gateway outcome',
              configured == {'configured': True, 'request_id': outcome['request_id'],
                             'outcome': outcome['outcome'], 'live_gateway_calls': 0})
        # The CLI bridge has now executed the exact closed outcome with this
        # form's attempt identity.  Installed HTTP must replay it without a
        # transport seam or a second charge.
        target.locator('button[type="submit"],button:not([type])').click()
        page.wait_for_load_state('load', timeout=30000)
        return submitted

    def capture(page, locator, name, width, height):
        path = qa / name
        if path.exists() or path.is_symlink():
            raise RuntimeError('Preserve prior hosting payment capture')
        page.set_viewport_size({'width': width, 'height': height})
        locator.scroll_into_view_if_needed()
        page.evaluate('document.fonts.ready')
        check('Hosting payment capture fits the original viewport ' + name,
              page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1') is True)
        box = locator.bounding_box()
        if box is None or not 0 < box['width'] <= width + 1 or not 0 < box['height'] <= 12000:
            raise AssertionError('Bounded hosting payment capture target required')
        locator.screenshot(path=str(path), animations='disabled', timeout=30000)
        raw = path.read_bytes()
        if raw[:8] != b'\x89PNG\r\n\x1a\n':
            raise AssertionError('Actual PNG capture required')
        image_width, image_height = struct.unpack('!II', raw[16:24])
        captures.append({'name': name, 'sha256': hashlib.sha256(raw).hexdigest(),
                         'width': image_width, 'height': image_height,
                         'layout_viewport': {'width': width, 'height': height}, 'viewport_box': box})

    try:
        initial = state()
        approved = initial['journeys']['approved']
        declined = initial['journeys']['declined']
        uncertain = initial['journeys']['uncertain']
        check('All hosting payment journeys begin as exact unpaid invoices',
              all(row['payment_stage'] == 'awaiting_payment' and row['invoice_status'] == 'issued'
                  and row['charge_state'] is None and row['receipt_count'] == 0
                  and row['attempt_count'] == 0 for row in initial['journeys'].values()))

        client, page = context(); foreign, other = context()
        login(page, 1); login(other, 2)
        check('Awaiting-payment result opens the exact owned invoice',
              goto(page, approved['result_url']) == 200
              and approved['invoice_reference'] in page.inner_text('body')
              and page.get_by_text('Awaiting payment', exact=True).count() >= 1)
        invoice_link = page.get_by_role('link', name='View invoice', exact=True).first
        check('Checkout result links to its bound read-only invoice', invoice_link.count() == 1
              and parse_qs(urlparse(invoice_link.get_attribute('href')).query).get('invoice') == [str(approved['invoice_id'])])
        check('Owned checkout result binds the exact request and invoice before tokenization',
              approved['invoice_reference'] in page.inner_text('body')
              and payment_price in page.inner_text('body')
              and form(page, 'hosting_catalog_checkout_pay').count() == 1 and safe(page))
        target = page.locator('section').filter(has=form(page, 'hosting_catalog_checkout_pay')).first
        for width, height, name in ((1440, 1050, CAPTURES[0]), (390, 844, CAPTURES[1])):
            capture(page, target, name, width, height)

        # A forged success-looking return is only navigation.  It supplies no
        # authenticated gateway readback and cannot change any business row.
        before_return = state()
        forged = approved['result_url'] + ('&' if '?' in approved['result_url'] else '?') + urlencode(
            {'payment': 'success', 'transactionId': '999999999'})
        check('Browser success-looking return remains an ordinary read', goto(page, forged) == 200)
        check('Browser return alone cannot settle or activate hosting', state() == before_return)

        # Another authenticated client cannot open or submit this payment.
        check('Foreign client cannot open the selected hosting payment',
              goto(other, approved['payment_url']) in (200, 403, 404)
              and approved['invoice_reference'] not in other.content()
              and form(other, 'hosting_catalog_checkout_pay').count() == 0)

        # Decline is retryable but is neither a receipt nor paid service.
        check('Declined journey opens its own payment form', goto(page, declined['payment_url']) == 200)
        submit_card(page, {**declined, 'outcome': 'declined'})
        declined_state = state()['journeys']['declined']
        check('Gateway decline keeps the exact hosting invoice unpaid',
              declined_state['payment_stage'] == 'awaiting_payment'
              and declined_state['invoice_status'] == 'issued'
              and declined_state['charge_state'] == 'declined'
              and declined_state['receipt_count'] == 0 and declined_state['attempt_count'] == 1
              and 'declined' in page.inner_text('body').casefold())

        # Unknown readback fences the obligation for review and refuses another
        # attempt rather than risking a duplicate charge.
        check('Uncertain journey opens its own payment form', goto(page, uncertain['payment_url']) == 200)
        uncertain_form = submit_card(page, {**uncertain, 'outcome': 'uncertain'})
        uncertain_state = state()['journeys']['uncertain']
        check('Uncertain result records review without receipt or paid status',
              uncertain_state['payment_stage'] == 'payment_review'
              and uncertain_state['invoice_status'] == 'issued'
              and uncertain_state['charge_state'] in ('uncertain', 'review')
              and uncertain_state['receipt_count'] == 0 and uncertain_state['attempt_count'] == 1
              and 'review' in page.inner_text('body').casefold())
        uncertain_before_retry = state()
        response = client.request.post(uncertain['payment_url'], form={
            **uncertain_form, 'action': 'hosting_catalog_checkout_pay', 'attempt_key': 'f' * 64,
            'descriptor': 'COMMON.ACCEPT.INAPP.PAYMENT', 'payment_token': 'synthetic-local-opaque-token'})
        check('Uncertain payment refuses a second browser submission', response.status in (200, 409)
              and state() == uncertain_before_retry)

        # Exact authoritative approval creates one gateway receipt and reaches
        # only Paid / setup pending. Provider fulfillment remains separate.
        check('Approved journey returns to its exact payment form', goto(page, approved['payment_url']) == 200)
        submit_card(page, {**approved, 'outcome': 'approved'})
        paid_state = state()['journeys']['approved']
        check('Authoritative approval creates one paid hosting obligation only',
              paid_state['payment_stage'] == 'paid_setup_pending'
              and paid_state['invoice_status'] == 'paid' and paid_state['charge_state'] == 'paid'
              and paid_state['receipt_count'] == 1 and paid_state['attempt_count'] == 1
              and paid_state['fulfillment_count'] == 0 and paid_state['provider_attempt_count'] == 0)
        check('Paid result is truthful before provider setup',
              'Paid / setup pending' in page.inner_text('body')
              and 'Active' not in page.inner_text('body') and safe(page))
        paid_target = page.locator('section').filter(has=page.locator('[data-hosting-payment-stage]')).first
        for width, height, name in ((1440, 1050, CAPTURES[2]), (390, 844, CAPTURES[3])):
            capture(page, paid_target, name, width, height)
        paid_before_retry = state()
        check('Paid payment URL replay is read-only', goto(page, approved['payment_url']) == 200
              and form(page, 'hosting_catalog_checkout_pay').count() == 0 and state() == paid_before_retry)

        fulfilled = fixture('payment-fulfill', {'request_id': approved['request_id']})
        check('Closed Shared fulfillment sends exactly one synthetic provider request',
              fulfilled == {'fulfilled': True, 'request_id': approved['request_id'],
                            'payment_stage': 'paid_setup_pending', 'provider_attempts': 1,
                            'live_provider_calls': 0})
        pending = state()['journeys']['approved']
        check('Provider acknowledgement remains setup pending rather than Active',
              pending['payment_stage'] == 'paid_setup_pending'
              and pending['fulfillment_count'] == 1 and pending['provider_attempt_count'] == 1)
        activated = fixture('payment-activate', {'request_id': approved['request_id']})
        check('Closed authenticated provider observation activates the exact Shared purchase',
              activated == {'activated': True, 'request_id': approved['request_id'],
                            'payment_stage': 'active', 'provider_attempts': 1,
                            'live_provider_calls': 0})
        active = state()['journeys']['approved']
        check('Shared purchase becomes Active only after exact confirmation evidence',
              active['payment_stage'] == 'active' and active['invoice_status'] == 'paid'
              and active['receipt_count'] == 1 and active['fulfillment_count'] == 1
              and active['provider_attempt_count'] == 1)
        check('Active purchase renders through the owned installed result',
              goto(page, approved['result_url']) == 200
              and page.get_by_text('Active', exact=True).count() >= 1 and safe(page))
        active_target = page.locator('section').filter(has=page.locator('[data-hosting-payment-stage]')).first
        for width, height, name in ((1440, 1050, CAPTURES[4]), (390, 844, CAPTURES[5])):
            capture(page, active_target, name, width, height)
        final = state()
        check('Fulfillment and activation retries do not duplicate payment or provider work',
              fixture('payment-fulfill', {'request_id': approved['request_id']})['provider_attempts'] == 1
              and fixture('payment-activate', {'request_id': approved['request_id']})['provider_attempts'] == 1
              and state() == final)

        # VPS is a distinct truthful unavailable destination; it can never
        # reuse the Shared checkout/payment form.
        vps_url = initial.get('vps_url', base + '/index.php?page=vps-hosting')
        check('Installed VPS destination remains explicitly unavailable', goto(page, vps_url) == 200
              and page.locator('h1').count() == 1
              and 'dedicated contract' in page.locator('h1').inner_text().casefold()
              and 'Consultation only' in page.inner_text('body')
              and 'no online VPS checkout' in page.inner_text('body')
              and form(page, 'hosting_catalog_checkout_pay').count() == 0
              and not page.locator('a[href*="hosting=checkout"]').count()
              and 'will not substitute a Shared Hosting package' in page.inner_text('body'))
        vps_target = page.locator('main').first
        for width, height, name in ((1440, 1050, CAPTURES[6]), (390, 844, CAPTURES[7])):
            capture(page, vps_target, name, width, height)
        check('Hosting payment browser has no JavaScript page errors', not errors)
        check('Hosting payment browser uses no live external resource', not blocked)
        check('Hosting payment produces exactly its eight scoped captures',
              tuple(row['name'] for row in captures) == CAPTURES)
        return {'passed': all(row['passed'] is True for row in checks), 'checks': checks, 'captures': captures,
                'scope': 'Actual installed ordinary Shared hosting invoice payment with closed local gateway/provider outcomes; no live charge, provisioning, customer message or deployment. VPS remains unavailable.',
                'journeys': {'approved': approved['request_id'], 'declined': declined['request_id'],
                             'uncertain': uncertain['request_id']},
                'live_gateway_calls': 0, 'live_provider_calls': 0}
    finally:
        for ctx in contexts:
            ctx.close()
