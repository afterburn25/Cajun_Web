"""Installed monthly/annual owner forms and two exact client checkouts.

Runs after the preserved storefront, guided setup and purchase inbox journeys.
The bridge is read-only; all new commercial records and artwork use real forms.
No provider refresh, payment, fixture mutation, clock edit or account seed occurs.
"""
from collections import Counter
from datetime import date, timedelta
import calendar
import hashlib
import math
import re
import struct
import time
from types import SimpleNamespace
from urllib.parse import parse_qs, urlencode, urljoin, urlparse
import zlib


CAPTURES = tuple('Hosting-Billing-Options-' + stage + '-' + size + '.png'
                 for stage in ('Owner-Annual', 'Public-Monthly', 'Public-Annual', 'Client-Annual')
                 for size in ('Desktop', 'Mobile'))
COUNT_KEYS = {'reviews', 'results', 'jobs', 'invoices', 'receipts', 'attempts', 'publications',
              'products', 'variants', 'media', 'plans', 'plan_events', 'mappings',
              'provider_refreshes', 'provider_checks'}
ANNUAL_PRICE = 27000
ANNUAL_SKU = 'INSTALLED-BILLING-ANNUAL'
ANNUAL_TITLE = 'Reviewed annual hosting'
ANNUAL_DETAILS = {'inclusions': 'One managed website\nAnnual hosting service review',
                  'exclusions': 'Domain registration\nCustom development',
                  'limits': 'websites = 1\nsupport_requests = 12',
                  'support': 'Email support during stated studio hours for this annual plan'}


def billing_snapshot(value):
    """Reject missing/malformed bridge evidence instead of treating it as zero."""
    keys = {'counts', 'variants_ready', 'products', 'publications', 'reviews', 'results',
            'retained_rows', 'provider_io_sha256'}
    if not isinstance(value, dict) or set(value) != keys or type(value['variants_ready']) is not bool:
        raise AssertionError('Exact installed billing-state response required')
    if (not isinstance(value['counts'], dict) or set(value['counts']) != COUNT_KEYS
            or any(type(n) is not int or not 0 <= n <= 128 for n in value['counts'].values())):
        raise AssertionError('Bounded exact billing counters required')
    if not isinstance(value['provider_io_sha256'], str) or re.fullmatch('[a-f0-9]{64}', value['provider_io_sha256']) is None:
        raise AssertionError('Provider history digest required')
    for name in ('products', 'publications', 'reviews', 'results', 'retained_rows'):
        if not isinstance(value[name], dict) or not value[name]:
            raise AssertionError('Original nonempty billing history required: ' + name)
    for key, product in value['products'].items():
        if (not isinstance(product, dict) or str(product.get('id')) != key
                or any(type(product.get(k)) is not int or product[k] <= 0 for k in ('id', 'revision', 'canonical_import_id', 'canonical_product_id'))
                or product.get('term') not in ('month', 'year')
                or product.get('amount_cents') is not None and (type(product['amount_cents']) is not int or product['amount_cents'] < 0)
                or type(product.get('media_revision')) is not int or product['media_revision'] < 0
                or any(product.get(k) is not None and (not isinstance(product[k], str) or re.fullmatch('[a-f0-9]{32}', product[k]) is None) for k in ('variant_id', 'media_id'))):
            raise AssertionError('Validated imported product identity required')
    for name in ('publications', 'reviews', 'results'):
        field = 'publication_id' if name == 'publications' else 'request_id'
        for key, row in value[name].items():
            if not isinstance(key, str) or re.fullmatch('[a-f0-9]{32}', key) is None or not isinstance(row, dict) or row.get(field) != key:
                raise AssertionError('Exact original publication or checkout identity required')
        if len(value[name]) != value['counts'][name]:
            raise AssertionError('Billing row count differs from projection')
    for table, rows in value['retained_rows'].items():
        if (not isinstance(table, str) or re.fullmatch('[a-z][a-z0-9_]*', table) is None
                or not isinstance(rows, list) or len(rows) > 128
                or any(not isinstance(h, str) or re.fullmatch('[a-f0-9]{64}', h) is None for h in rows)
                or rows != sorted(rows)):
            raise AssertionError('Sorted original row hash multiset required')
    return value


def retained_rows_preserved(before, after):
    """Addition is allowed; loss or a changed original cell never counts as retained."""
    billing_snapshot(before)
    billing_snapshot(after)
    return (before['provider_io_sha256'] == after['provider_io_sha256']
            and all(table in after['retained_rows'] and not (Counter(rows) - Counter(after['retained_rows'][table]))
                    for table, rows in before['retained_rows'].items())
            and all(after['results'].get(key) == row for key, row in before['results'].items())
            and all(after['publications'].get(key) == row for key, row in before['publications'].items())
            and after['counts']['receipts'] == before['counts']['receipts']
            and after['counts']['attempts'] == before['counts']['attempts'])


def billing_period_matches(billing, cadence, amount):
    """Independent calendar boundary assertion, including short-month clamping."""
    if (cadence not in ('monthly', 'annually') or type(amount) is not int or amount <= 0
            or not isinstance(billing, dict) or billing.get('cadence') != cadence
            or type(billing.get('amount_cents')) is not int or billing['amount_cents'] != amount or billing.get('currency') != 'USD'
            or billing.get('timezone') != 'UTC' or billing.get('alignment') != 'day-of-month'
            or type(billing.get('setup_cents')) is not int or billing['setup_cents'] != 0
            or type(billing.get('due_after_days')) is not int or billing['due_after_days'] != 0
            or billing.get('collection') != 'invoice-only'
            or not isinstance(billing.get('first_period'), dict) or type(billing['first_period'].get('index')) is not int):
        return False
    try:
        start = date.fromisoformat(billing['anchor'])
        month = start.month + (1 if cadence == 'monthly' else 12)
        year, month = start.year + (month - 1) // 12, (month - 1) % 12 + 1
        end = date(year, month, min(start.day, calendar.monthrange(year, month)[1])).isoformat()
        return billing['first_period'] == {'index': 0, 'start': start.isoformat(), 'end': end,
                                           'due': start.isoformat(), 'next_start': end}
    except (ValueError, TypeError, KeyError):
        return False


def annual_png():
    """Two fixed synthetic RGB pixels; distinct from the original one-pixel artwork."""
    def chunk(kind, data):
        return struct.pack('!I', len(data)) + kind + data + struct.pack('!I', zlib.crc32(kind + data) & 0xffffffff)
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('!IIBBBBB', 2, 1, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(b'\x00\xd9\xb5\x62\x19\x2c\x49')) + chunk(b'IEND', b''))


def run_browser(env, browser):
    from playwright.sync_api import expect
    from hosting_catalog_plan_setup_cases import ACTION as SETUP_ACTION, owner_fields
    from hosting_purchase_inbox_cases import snapshot as original_snapshot

    fixture, qa, setup = env['fixture'], env['QA'], env['plan_setup']
    site = env['BASE'] + '/hosting-checkout'
    admin, portal = site + '/admin/index.php', site + '/portal/index.php'
    owner_password, client_password = 'Synthetic native checkout owner', 'Synthetic checkout client'
    contexts, checks, captures, errors, blocked = [], [], [], [], []

    def check(name, passed):
        if type(passed) is not bool or any(c['name'] == name for c in checks):
            raise AssertionError('Unique strict installed billing outcome required')
        checks.append({'name': name, 'passed': passed})
        env['check'](name, passed)
        if not passed:
            raise AssertionError(name)

    for name in CAPTURES:
        if (qa / name).exists() or (qa / name).is_symlink():
            raise RuntimeError('Preserve existing billing capture: ' + name)

    def context():
        ctx = browser.new_context(viewport={'width': 1440, 'height': 1050}, reduced_motion='reduce')
        def route(request):
            if request.request.url.startswith(env['BASE'] + '/') or request.request.url.startswith(('data:', 'about:')):
                request.continue_()
            else:
                blocked.append(urlparse(request.request.url).scheme)
                request.abort()
        ctx.route('**/*', route)
        contexts.append(ctx)
        page = ctx.new_page()
        page.on('pageerror', lambda error: errors.append(type(error).__name__))
        return ctx, page

    def goto(page, url):
        response = page.goto(url, wait_until='load', timeout=30000)
        if response is None:
            raise AssertionError('Installed billing navigation has no response')
        return response.status

    def form(page, action):
        return page.locator('form').filter(has=page.locator('input[name="action"][value="' + action + '"]'))

    def fields(page, action):
        return env['form_data'](env['form_for'](SimpleNamespace(text=page.content(), url=page.url), action))

    def click(page, target):
        with page.expect_navigation(wait_until='load', timeout=30000) as event:
            target.click()
        response = event.value
        if response is None or response.status != 200:
            raise AssertionError('Installed billing form or link did not reach its page')

    def submit(page, action, label):
        click(page, form(page, action).get_by_role('button', name=label, exact=True))

    def state():
        return billing_snapshot(fixture('billing-state'))

    def retained(label, after):
        check(label, retained_rows_preserved(initial, after)
              and after['counts']['provider_refreshes'] == initial['counts']['provider_refreshes']
              and after['counts']['provider_checks'] == initial['counts']['provider_checks'])

    def unchanged(label, before):
        check(label, state() == before)

    def counts(label, before, after, changes):
        check(label, after['counts'] == {key: n + changes.get(key, 0) for key, n in before['counts'].items()})

    def safe(page):
        return all(token not in page.content() for token in (owner_password, client_password,
                   'SYNTHETIC_CHECKOUT_PROVIDER_KEY', 'credential_sha256', 'mapping_json', 'plan_json', 'PRIVATE_'))

    def image_ready(page, target, expected_url, stage):
        expect(target).to_have_count(1)
        check('Installed billing exact local image ' + stage,
              urljoin(page.url, target.get_attribute('src')) == expected_url)
        target.scroll_into_view_if_needed()
        page.wait_for_function('image => image.complete && image.naturalWidth > 0 && image.naturalHeight > 0',
                               arg=target.element_handle(), timeout=30000)

    def capture(page, target, stage):
        for width, height, suffix in ((1440, 1050, 'Desktop'), (390, 844, 'Mobile')):
            name = 'Hosting-Billing-Options-' + stage + '-' + suffix + '.png'
            page.set_viewport_size({'width': width, 'height': height})
            expect(target).to_be_visible()
            page.evaluate('document.fonts.ready')
            check('Installed billing original viewport has no document overflow ' + name,
                  page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1') is True)
            region = target.locator('.hosting-comparison-scroll')
            if region.count() == 1:
                region.focus()
                check('Installed billing comparison is keyboard focusable ' + name,
                      region.evaluate('el => document.activeElement === el && !!el.getAttribute("aria-labelledby")') is True)
                if width == 390:
                    region.evaluate('el => {el.scrollLeft=0;}')
                    page.keyboard.press('ArrowRight')
                    page.wait_for_function('el => el.scrollWidth <= el.clientWidth || el.scrollLeft > 0', arg=region.element_handle())
                    region.evaluate('el => {el.scrollLeft=el.scrollWidth;}')
            page.evaluate('document.activeElement?.blur()')
            dimensions = target.evaluate('''el => ({height:el.getBoundingClientRect().height,
                header:Math.max(0,...[...document.querySelectorAll('.site-header,.admin-topbar')].map(h=>h.getBoundingClientRect().bottom))})''')
            required = math.ceil(dimensions['height'] + max(0, dimensions['header']) + 64)
            if not 0 < dimensions['height'] <= 12000 or required > 13000:
                raise AssertionError('Bounded installed billing capture required')
            capture_height = max(height, required)
            try:
                if capture_height != height:
                    page.set_viewport_size({'width': width, 'height': capture_height})
                target.evaluate('''el => {const h=Math.max(0,...[...document.querySelectorAll('.site-header,.admin-topbar')].map(x=>x.getBoundingClientRect().bottom));
                    window.scrollTo({top:Math.max(0,scrollY+el.getBoundingClientRect().top-h-16),left:0,behavior:'instant'});}''')
                visible = '''el => {const r=el.getBoundingClientRect(),h=Math.max(0,...[...document.querySelectorAll('.site-header,.admin-topbar')].map(x=>x.getBoundingClientRect().bottom));
                    return r.width>0&&r.height>0&&r.left>=-1&&r.right<=innerWidth+1&&r.top>=h+8&&r.bottom<=innerHeight-8
                        &&[.02,.5,.98].every(x=>[.02,.5,.98].every(y=>{const n=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);return n&&(n===el||el.contains(n));}));}'''
                page.wait_for_function(visible, arg=target.element_handle(), timeout=30000)
                check('Installed billing complete unobscured capture region ' + name, target.evaluate(visible) is True)
                path = qa / name
                target.screenshot(path=str(path), animations='disabled', timeout=30000)
                raw = path.read_bytes()
                if raw[:8] != b'\x89PNG\r\n\x1a\n':
                    raise AssertionError('Actual installed PNG capture required')
                pixels = struct.unpack('!II', raw[16:24])
                captures.append({'name': name, 'sha256': hashlib.sha256(raw).hexdigest(), 'width': pixels[0], 'height': pixels[1],
                                 'layout_viewport': {'width': width, 'height': height}, 'viewport': {'width': width, 'height': capture_height},
                                 'coverage': 'Complete scoped region at original width; capture-only height growth. Mobile comparison intentionally shows its scrolled price/choice column.'})
            finally:
                if capture_height != height:
                    page.set_viewport_size({'width': width, 'height': height})
        page.set_viewport_size({'width': 1440, 'height': 1050})

    def publish(page, product_id, plan_id, amount, cadence, image_url, stage=None):
        before = state()
        url = admin + '?' + urlencode({'view': 'hosting-offers', 'product_id': product_id, 'plan_id': plan_id})
        check('Installed ' + cadence + ' selects its exact matching plan', goto(page, url) == 200
              and form(page, 'hosting_catalog_preview').locator('[name="plan_id"]').input_value() == plan_id)
        preview = form(page, 'hosting_catalog_preview')
        preview.locator('[name="checkout_mode"]').select_option('invoice-on-confirmation')
        preview.locator('[name="billing_timezone"]').fill('UTC')
        preview.locator('[name="billing_alignment"]').select_option('day-of-month')
        submit(page, 'hosting_catalog_preview', 'Preview offer')
        selected = fields(page, 'hosting_catalog_publish')
        review = page.locator('section.workspace-card').filter(has=page.get_by_role('heading', name='Review before publishing', exact=True))
        check('Installed ' + cadence + ' preview retains exact identity and full price',
              selected['product_id'] == str(product_id) and selected['plan_id'] == plan_id
              and selected['checkout_mode'] == 'invoice-on-confirmation'
              and '$' + format(amount / 100, '.2f') in review.inner_text()
              and ('per month' if cadence == 'monthly' else 'per year') in review.inner_text()
              and form(page, 'hosting_catalog_publish').locator('[name="current_password"]').input_value() == ''
              and not form(page, 'hosting_catalog_publish').locator('[name="confirm"]').is_checked())
        image_ready(page, review.locator('.hosting-catalog-image'), image_url, cadence + ' publication preview')
        if stage:
            review.locator('details').get_by_text('Plan terms', exact=True).click()
            capture(page, review, stage)
        unchanged('Installed ' + cadence + ' preview and captures perform no commercial writes', before)
        form(page, 'hosting_catalog_publish').locator('[name="current_password"]').fill(owner_password)
        form(page, 'hosting_catalog_publish').locator('[name="confirm"]').check()
        submit(page, 'hosting_catalog_publish', 'Publish hosting offer')
        after = state()
        counts('Installed ' + cadence + ' publication appends only one publication', before, after, {'publications': 1})
        published = after['publications'][selected['publication_id']]
        check('Installed ' + cadence + ' publication preserves the chosen product plan price and image',
              published['product_id'] == product_id and published['plan_id'] == plan_id
              and published['amount_cents'] == amount and published['cadence'] == cadence
              and published['currency'] == 'USD' and published['image_id'] == parse_qs(urlparse(image_url).query)['id'][0]
              and published['checkout_policy']['mode'] == 'invoice-on-confirmation' and safe(page))
        retained('Installed ' + cadence + ' publication retains every original purchase and provider row', after)
        return published

    try:
        old = original_snapshot(fixture('state'))
        initial = state()
        check('Installed billing begins after the exact five-purchase setup continuation',
              setup.get('passed') is True and setup.get('publication_submitted') is False
              and setup['product_id'] == env['seed']['product_id']
              and initial['counts']['results'] == initial['counts']['invoices'] == 5
              and initial['counts']['receipts'] == 1 and initial['counts']['attempts'] == 0
              and not initial['variants_ready'] and initial['counts']['variants'] == 0
              and set(initial['results']) == set(old['results']))
        monthly_id = setup['product_id']
        monthly_product = initial['products'][str(monthly_id)]
        check('Retained monthly draft has the deliberately edited27 dollar price and original artwork',
              monthly_product['term'] == 'month' and monthly_product['amount_cents'] == 2700
              and monthly_product['variant_id'] is None and monthly_product['media_id'] is not None)
        monthly_image = site + '/media/index.php?id=' + monthly_product['media_id']
        owner, op = context()
        client, cp = context()
        check('Installed billing opens the actual owner login', goto(op, admin + '?view=login') == 200)
        form(op, 'login').locator('[name="email"]').fill('checkout-owner@example.invalid')
        form(op, 'login').locator('[name="password"]').fill(owner_password)
        click(op, form(op, 'login').locator('button[type="submit"],button:not([type])'))
        check('Installed billing has the authenticated owner session', 'Sales pipeline' in op.content())
        monthly = publish(op, monthly_id, setup['plan_id'], 2700, 'monthly', monthly_image)

        product_url = admin + '?' + urlencode({'view': 'catalog', 'id': monthly_id})
        before = state()
        check('Installed product exposes explicit billing option preparation', goto(op, product_url) == 200
              and form(op, 'catalog_import_variant_prepare').count() == 1)
        unchanged('Opening installed billing preparation is read-only', before)
        prepare = form(op, 'catalog_import_variant_prepare')
        prepare.locator('[name="owner_password"]').fill(owner_password)
        prepare.locator('[name="confirm"]').check()
        submit(op, 'catalog_import_variant_prepare', 'Enable billing options')
        prepared = state()
        counts('Enabling installed billing options creates no product or commercial row', before, prepared, {})
        check('Installed variant preparation is ready with no child created', prepared['variants_ready'] is True)
        op.locator('details.catalog-variant-create > summary').focus()
        op.keyboard.press('Enter')
        expect(form(op, 'catalog_import_variant_create')).to_be_visible()
        variant = form(op, 'catalog_import_variant_create')
        variant.locator('[name="sku"]').fill(ANNUAL_SKU)
        variant.locator('[name="term"]').select_option('year')
        variant.locator('[name="owner_password"]').fill(owner_password)
        variant.locator('[name="confirm"]').check()
        # HTML serialization retains default values; preserve real hidden identities
        # and explicitly copy the same owner choices used by the visible form.
        original_variant = {**fields(op, 'catalog_import_variant_create'), 'sku': ANNUAL_SKU, 'term': 'year'}
        submit(op, 'catalog_import_variant_create', 'Create billing option')
        created = state()
        counts('Installed annual creation adds one independent draft and authenticated variant', prepared, created, {'products': 1, 'variants': 1})
        query = parse_qs(urlparse(op.url).query)
        annual_id = int(query.get('id', ['0'])[0])
        annual_product = created['products'].get(str(annual_id), {})
        check('Installed annual redirect identifies a blank independent child of the original import',
              query == {'view': ['catalog'], 'id': [str(annual_id)]} and annual_id != monthly_id
              and annual_product.get('term') == 'year' and annual_product.get('sku') == ANNUAL_SKU
              and annual_product.get('amount_cents') is None and annual_product.get('media_id') is None
              and annual_product.get('variant_id') == original_variant['variant_request_id']
              and annual_product.get('canonical_import_id') == monthly_product['canonical_import_id']
              and annual_product.get('canonical_product_id') == monthly_id
              and created['products'][str(monthly_id)] == monthly_product)
        edit = form(op, 'catalog_save')
        for key, value in {'title': ANNUAL_TITLE, 'amount': '270.00', 'setup': '0.00',
                           'summary': 'A full year of managed hosting with a separate annual invoice.',
                           'features': 'One managed website\nAnnual hosting service review',
                           'terms': 'One year of service. Domain registration and custom development are excluded.'}.items():
            edit.locator('[name="' + key + '"]').fill(value)
        submit(op, 'catalog_save', 'Save product')
        edited = state()
        counts('Annual commercial edits create no invoice publication or new product', created, edited, {})
        image = form(op, 'catalog_import_image')
        image.locator('[name="product_image"]').set_input_files({'name': 'synthetic-annual-option.png', 'mimeType': 'image/png', 'buffer': annual_png()})
        image.locator('[name="owner_password"]').fill(owner_password)
        submit(op, 'catalog_import_image', 'Save image')
        imaged = state()
        counts('Annual image upload creates one independent local media row', edited, imaged, {'media': 1})
        annual_image = urljoin(op.url, op.locator('img[alt="Uploaded draft product image"]').get_attribute('src'))
        check('Annual price and artwork stay independent while the canonical monthly draft is preserved',
              imaged['products'][str(annual_id)]['amount_cents'] == ANNUAL_PRICE
              and imaged['products'][str(annual_id)]['title'] == ANNUAL_TITLE
              and annual_image != monthly_image and imaged['products'][str(monthly_id)] == monthly_product)
        replay = owner.request.post(product_url, form={**original_variant, 'owner_password': owner_password, 'confirm': '1'}, max_redirects=0, timeout=30000)
        check('Exact installed variant retry returns the original edited child', replay.status == 303
              and parse_qs(urlparse(urljoin(admin, replay.headers.get('location', ''))).query) == {'view': ['catalog'], 'id': [str(annual_id)]})
        unchanged('Variant retry preserves its independent price image and all original rows', imaged)
        offer_url = admin + '?' + urlencode({'view': 'hosting-offers', 'product_id': annual_id})
        check('Annual imported child exposes the actual guided plan setup', goto(op, offer_url) == 200 and form(op, SETUP_ACTION).count() == 1)
        selected = {**owner_fields(fields(op, SETUP_ACTION)), **ANNUAL_DETAILS,
                    'package_label': 'Reviewed annual hosting package', 'package_region': 'Reviewed annual region', 'limit_websites': '1'}
        target = form(op, SETUP_ACTION)
        for key in ('inclusions', 'exclusions', 'limits', 'support', 'reseller_id', 'package_label', 'package_region', 'review_days',
                    'limit_websites', 'limit_disk_mb', 'limit_bandwidth_mb', 'limit_mailboxes', 'limit_databases'):
            target.locator('[name="' + key + '"]').fill(selected[key])
        target.locator('[name="current_password"]').fill(owner_password)
        target.locator('[name="reviewed_defaults"]').check()
        target.locator('[name="confirm"]').check()
        submit(op, SETUP_ACTION, 'Create matching plan')
        planned = state()
        counts('Annual guided setup creates one plan activation and mapping', imaged, planned, {'plans': 1, 'plan_events': 1, 'mappings': 1})
        check('Annual guided setup redirects to its exact selected plan', parse_qs(urlparse(op.url).query)
              == {'view': ['hosting-offers'], 'product_id': [str(annual_id)], 'plan_id': [selected['plan_id']]})
        annual = publish(op, annual_id, selected['plan_id'], ANNUAL_PRICE, 'annually', annual_image, 'Owner-Annual')
        check('Published annual service terms are the explicit independent owner choices',
              annual['inclusions'] == ANNUAL_DETAILS['inclusions'].splitlines()
              and annual['exclusions'] == ANNUAL_DETAILS['exclusions'].splitlines()
              and annual['support'] == ANNUAL_DETAILS['support'] and annual['limits'] == {'support_requests': 12, 'websites': 1})

        published = state()
        public_url = site + '/index.php?page=shared-hosting'
        check('Installed public billing comparison opens with both current options', goto(cp, public_url) == 200
              and cp.locator('#hosting-plans a[href*="hosting=checkout"]').count() == 2)
        for cadence, label, offer, image_url, stage in [('monthly', 'Monthly', monthly, monthly_image, 'Public-Monthly'),
                                                     ('annually', 'Annual', annual, annual_image, 'Public-Annual')]:
            click(cp, cp.get_by_role('navigation', name='Hosting billing period').get_by_role('link', name=label, exact=True))
            links = cp.locator('#hosting-plans a[href*="hosting=checkout"]')
            check('Installed public ' + cadence + ' filter shows only its exact full-price offer',
                  parse_qs(urlparse(cp.url).query).get('billing') == [cadence] and links.count() == 1
                  and parse_qs(urlparse(links.get_attribute('href')).query) == {'hosting': ['checkout'], 'offer': [offer['publication_id']], 'offer_sha256': [offer['publication_sha256']]}
                  and cp.locator('#hosting-plans .hosting-comparison-price').inner_text() == '$' + format(offer['amount_cents'] / 100, '.2f')
                  and cp.get_by_role('navigation', name='Hosting billing period').get_by_role('link', name=label, exact=True).get_attribute('aria-current') == 'page')
            image_ready(cp, cp.locator('#hosting-plans .hosting-catalog-image'), image_url, cadence + ' public comparison')
            capture(cp, cp.locator('#hosting-plans'), stage)
        unchanged('Public billing filters images and captures create no commercial or provider records', published)
        check('Installed primary client opens the real portal login', goto(cp, portal) == 200 and form(cp, 'login').count() == 1)
        form(cp, 'login').locator('[name="email"]').fill('checkout-client1@example.invalid')
        form(cp, 'login').locator('[name="password"]').fill(client_password)
        click(cp, form(cp, 'login').locator('button[type="submit"],button:not([type])'))
        check('Installed billing authenticates the actual primary client', form(cp, 'login').count() == 0 and 'Welcome, ' in cp.content())

        purchases = {}
        for cadence, label, offer, image_url in [('monthly', 'Monthly', monthly, monthly_image), ('annually', 'Annual', annual, annual_image)]:
            before = state()
            check('Installed client opens ' + cadence + ' browsing', goto(cp, portal + '?hosting=offers') == 200)
            click(cp, cp.get_by_role('navigation', name='Hosting billing period').get_by_role('link', name=label, exact=True))
            choice = cp.get_by_role('link', name='Review plan and first invoice', exact=True)
            check('Installed client ' + cadence + ' filter keeps exact price cadence image and checkout identity', choice.count() == 1
                  and parse_qs(urlparse(choice.get_attribute('href')).query) == {'hosting': ['checkout'], 'offer': [offer['publication_id']], 'offer_sha256': [offer['publication_sha256']]}
                  and '$' + format(offer['amount_cents'] / 100, '.2f') in cp.inner_text('body')
                  and ('per month' if cadence == 'monthly' else 'per year') in cp.inner_text('body'))
            click(cp, choice)
            selection = fields(cp, 'hosting_catalog_checkout_preview')
            check('Installed ' + cadence + ' selection retains exact publication and hash',
                  selection['publication_id'] == offer['publication_id'] and selection['publication_sha256'] == offer['publication_sha256'])
            unchanged('Installed ' + cadence + ' selection and login reads are business read-only', before)
            preview = form(cp, 'hosting_catalog_checkout_preview')
            preview.locator('[name="project_mode"]').select_option('existing')
            preview.locator('[name="project_id"]').select_option('1')
            submit(cp, 'hosting_catalog_checkout_preview', 'Review plan and billing dates')
            confirm = fields(cp, 'hosting_catalog_checkout_confirm')
            reviewed = state()
            review = reviewed['reviews'][selection['request_id']]
            counts('Installed ' + cadence + ' preview saves only its exact review', before, reviewed, {'reviews': 1})
            check('Installed ' + cadence + ' review binds original selection full price and correct calendar period',
                  confirm['request_id'] == selection['request_id'] and confirm['review_sha256'] == review['review_sha256']
                  and review['publication_id'] == offer['publication_id'] and review['publication_sha256'] == offer['publication_sha256']
                  and review['client_id'] == 1 and review['project'] == {'mode': 'existing', 'id': 1}
                  and billing_period_matches(review['billing'], cadence, offer['amount_cents'])
                  and not form(cp, 'hosting_catalog_checkout_confirm').locator('[name="confirm"]').is_checked())
            card = cp.locator('section.workspace-card').filter(has=cp.get_by_role('heading', name='Confirm your plan and first invoice', exact=True))
            period = review['billing']['first_period']
            last = (date.fromisoformat(period['end']) - timedelta(days=1)).isoformat()
            check('Installed ' + cadence + ' rendered confirmation shows exact retained billing dates',
                  all(s in card.inner_text() for s in (period['start'] + ' through ' + last, period['due'], period['next_start'], 'UTC', '$' + format(offer['amount_cents'] / 100, '.2f')))
                  and urljoin(cp.url, card.locator('.hosting-catalog-image').get_attribute('src')) == image_url and safe(cp))
            if cadence == 'annually':
                card.locator('details').get_by_text('Plan terms', exact=True).click()
                capture(cp, card, 'Client-Annual')
                unchanged('Annual confirmation captures preserve its original review without invoice effects', reviewed)
            started = int(time.time())
            form(cp, 'hosting_catalog_checkout_confirm').locator('[name="confirm"]').check()
            submit(cp, 'hosting_catalog_checkout_confirm', 'Agree and create first invoice')
            finished = int(time.time())
            purchased = state()
            result = purchased['results'][confirm['request_id']]
            counts('Installed ' + cadence + ' consent creates exactly one unpaid invoice and result', reviewed, purchased, {'results': 1, 'invoices': 1})
            check('Installed ' + cadence + ' invoice retains the exact original offer review and full period',
                  result['publication_id'] == offer['publication_id'] and result['publication_sha256'] == offer['publication_sha256']
                  and result['client_id'] == result['project_id'] == 1 and result['amount_cents'] == offer['amount_cents']
                  and result['currency'] == 'USD' and result['invoice_status'] == 'issued'
                  and result['billing'] == review['billing'] and started <= result['accepted_at'] <= finished
                  and result['automatic_charge'] is False and result['provider_activation'] is False
                  and all(result['issued_period'][key] == period[key] for key in ('index', 'start', 'end', 'due'))
                  and result['issued_period']['amount_cents'] == offer['amount_cents'] and result['issued_period']['currency'] == 'USD')
            result_url = cp.url
            invoice_link = cp.get_by_role('link', name='View invoice', exact=True)
            check('Installed ' + cadence + ' result links to its exact invoice',
                  parse_qs(urlparse(invoice_link.get_attribute('href')).query) == {'invoice': [str(result['invoice_id'])]})
            click(cp, invoice_link)
            check('Installed ' + cadence + ' invoice is issued without payment or automatic activation',
                  result['invoice_reference'] in cp.inner_text('body') and 'Status: issued' in cp.inner_text('body')
                  and 'No payments recorded yet.' in cp.inner_text('body') and 'Online card checkout is not available yet.' in cp.inner_text('body') and safe(cp))
            replay = client.request.post(portal + '?hosting=checkout', form={**confirm, 'confirm': '1'}, max_redirects=0, timeout=30000)
            check('Installed ' + cadence + ' exact confirmation retry returns its original result',
                  replay.status == 303 and urljoin(portal, replay.headers.get('location', '')) == result_url)
            unchanged('Installed ' + cadence + ' retry and invoice reads do not duplicate or change purchase records', purchased)
            retained('Installed ' + cadence + ' purchase retains original five purchases and zero provider effects', purchased)
            purchases[cadence] = result

        final = state()
        counts('Final installed billing has only two new purchases publications and one annual setup', initial, final,
               {'reviews': 2, 'results': 2, 'invoices': 2, 'publications': 2, 'products': 1, 'variants': 1, 'media': 1, 'plans': 1, 'plan_events': 1, 'mappings': 1})
        check('Final installed billing has seven invoices and one retained receipt with no payment attempts',
              final['counts']['results'] == final['counts']['invoices'] == 7 and final['counts']['receipts'] == 1 and final['counts']['attempts'] == 0
              and len({row['invoice_id'] for row in final['results'].values()}) == 7
              and final['products'][str(monthly_id)] == monthly_product)
        retained('Final original row hashes and provider histories remain exactly preserved', final)
        check('Installed billing browser has no JavaScript page errors', not errors)
        check('Installed billing browser accesses only local resources', not blocked)
        check('Installed billing produces exactly eight new scoped captures', tuple(row['name'] for row in captures) == CAPTURES)
        return {'passed': True, 'checks': checks, 'captures': captures,
                'scope': 'Actual installed owner monthly/annual draft, artwork, plan, publication and primary-client checkout forms. Two new full-period unpaid invoices preserve five original purchases. Synthetic local artwork; no provider I/O or payment. Image approval remains separate.',
                'monthly': monthly, 'annual': annual, 'purchases': purchases, 'counts': final['counts'],
                'provider_requests': 0, 'payment_attempts': 0, 'original_purchase_count': 5, 'final_purchase_count': 7,
                'provider_io_sha256': final['provider_io_sha256']}
    finally:
        for ctx in contexts:
            ctx.close()
