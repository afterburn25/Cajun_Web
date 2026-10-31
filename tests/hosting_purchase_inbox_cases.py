"""Installed read-only purchase inbox after the original five checkout results.

The existing runner owns packaging, exact source, HTTP lifetime and the report.
Only login/support session state changes here. The established contact-key probe
is a negative session-provenance test, never a claim of a real contact login.
"""
import hashlib
import math
import re
import struct
from urllib.parse import parse_qs, urlencode, urlparse


CAPTURES = tuple('Hosting-Purchase-Inbox-' + actor + '-' + size + '.png'
                 for actor in ('Owner', 'Client') for size in ('Desktop', 'Mobile'))
PAID_REQUEST = format(30, '032x')
SETUP_KEYS = {'plans', 'plan_events', 'mappings', 'provider_refreshes', 'provider_checks'}
COUNT_KEYS = {'reviews', 'results', 'jobs', 'invoices', 'receipts', 'attempts', 'publications'}


def snapshot(value):
    """Require the actual predecessor's closed five-purchase read-only snapshot."""
    if not isinstance(value, dict) or set(value) != {'business_sha256', 'counts', 'latest_publication', 'results', 'setup_counts'}:
        raise AssertionError('Exact existing checkout state required')
    if not isinstance(value['business_sha256'], str) or re.fullmatch('[a-f0-9]{64}', value['business_sha256']) is None:
        raise AssertionError('Exact commercial digest required')
    for key, names in (('counts', COUNT_KEYS), ('setup_counts', SETUP_KEYS)):
        if not isinstance(value[key], dict) or set(value[key]) != names or any(type(n) is not int or n < 0 for n in value[key].values()):
            raise AssertionError('Exact nonnegative predecessor counters required')
    counts, rows = value['counts'], value['results']
    if counts['results'] != 5 or counts['invoices'] != 5 or counts['receipts'] != 1 or counts['attempts'] != 0 or not isinstance(rows, dict) or len(rows) != 5:
        raise AssertionError('Five original results and invoices with one original local receipt required')
    for key, row in rows.items():
        if not isinstance(key, str) or re.fullmatch('[a-f0-9]{32}', key) is None or not isinstance(row, dict) or row.get('request_id') != key:
            raise AssertionError('Original result identity required')
        if any(type(row.get(k)) is not int or row[k] <= 0 for k in ('invoice_id', 'project_id', 'accepted_at')):
            raise AssertionError('Original result record identities required')
        if not isinstance(row.get('agreement_id'), str) or re.fullmatch('[a-f0-9]{32}', row['agreement_id']) is None:
            raise AssertionError('Original agreement identity required')
        if row.get('automatic_charge') is not False or row.get('provider_activation') is not False:
            raise AssertionError('Checkout is not automatic payment or provider activation')
        if row.get('invoice_status') != ('paid' if key == PAID_REQUEST else 'issued'):
            raise AssertionError('Retain one original paid purchase and four unpaid purchases')
        if type(row.get('amount_cents')) is not int or row['amount_cents'] != 2500 or row.get('currency') != 'USD' or not isinstance(row.get('invoice_reference'), str) or not row['invoice_reference']:
            raise AssertionError('Exact original invoice terms required')
    if PAID_REQUEST not in rows or len({r['invoice_id'] for r in rows.values()}) != 5 or len({r['agreement_id'] for r in rows.values()}) != 5:
        raise AssertionError('Five distinct retained invoices and agreements required')
    return value


def destination(href, endpoint, query):
    """Compare exact installed routes, not a status200 from a fallback page."""
    if not isinstance(href, str):
        return False
    got, base = urlparse(href), urlparse(endpoint)
    return (got.scheme, got.netloc, got.path, got.fragment) == (base.scheme, base.netloc, base.path, '') and parse_qs(got.query, keep_blank_values=True) == {k: [str(v)] for k, v in query.items()}


def run_browser(env, browser):
    from playwright.sync_api import expect
    site = env['BASE'] + '/hosting-checkout'
    admin, portal = site + '/admin/index.php', site + '/portal/index.php'
    fixture, qa = env['fixture'], env['QA']
    contexts, checks, captures, errors, blocked = [], [], [], [], []
    owner_password, client_password = 'Synthetic native checkout owner', 'Synthetic checkout client'

    def check(name, condition):
        if type(condition) is not bool or any(row['name'] == name for row in checks):
            raise AssertionError('Unique strict inbox outcome required')
        checks.append({'name': name, 'passed': condition})
        env['check'](name, condition)
        if not condition:
            raise AssertionError(name)

    for name in CAPTURES:
        if (qa/name).exists() or (qa/name).is_symlink():
            raise RuntimeError('Preserve prior inbox capture: ' + name)

    def context():
        ctx = browser.new_context(viewport={'width': 1440, 'height': 1050}, reduced_motion='reduce')
        def route(request):
            if request.request.url.startswith(env['BASE'] + '/') or request.request.url.startswith(('about:', 'data:')):
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
            raise AssertionError('Inbox navigation has no response')
        return response.status

    def click(page, link):
        with page.expect_navigation(wait_until='load', timeout=30000) as event:
            link.click()
        response = event.value
        if response is None:
            raise AssertionError('Inbox link has no navigation response')
        return response.status

    def form(page, action):
        return page.locator('form').filter(has=page.locator('input[name="action"][value="' + action + '"]'))

    def login(page, client=None):
        if goto(page, portal if client else admin + '?view=login') != 200:
            raise AssertionError('Installed inbox login unavailable')
        target = form(page, 'login')
        target.locator('[name="email"]').fill('checkout-client' + str(client) + '@example.invalid' if client else 'checkout-owner@example.invalid')
        target.locator('[name="password"]').fill(client_password if client else owner_password)
        status = click(page, target.locator('button[type="submit"],button:not([type])'))
        return status == 200 and form(page, 'login').count() == 0 and ('Welcome, ' if client else 'Sales pipeline') in page.content()

    def safe(page):
        return all(value not in page.content() for value in (owner_password, client_password, 'SYNTHETIC_CHECKOUT_PROVIDER_KEY', 'PRIVATE_', 'credential_sha256', 'mapping_json', 'plan_json', '$9.87'))

    def unchanged(label):
        check(label, snapshot(fixture('state')) == initial)

    def list_url(owner, page=1):
        return (admin if owner else portal) + '?' + urlencode({'view': 'hosting-purchases', 'p': page} if owner else {'hosting_purchases': 1, 'p': page})

    def card(page, request):
        return page.locator('[data-hosting-purchase="' + request + '"]')

    def cards(page, actor):
        wrapper = page.locator('[data-hosting-purchases="' + actor + '"]')
        ids = wrapper.locator('[data-hosting-purchase]').evaluate_all('els=>els.map(el=>el.dataset.hostingPurchase)')
        check('Inbox ' + actor + ' lists exactly the five original purchases once', len(ids) == 5 and set(ids) == set(initial['results']))
        for request, row in initial['results'].items():
            current = card(page, request)
            text = current.inner_text()
            status = 'Paid' if request == PAID_REQUEST else 'Awaiting payment'
            setup = 'Hosting request accepted' if request == PAID_REQUEST else 'Awaiting studio setup review'
            check('Inbox ' + actor + ' retains original terms and distinct setup state ' + request,
                  row['invoice_reference'] in text and '$25.00 USD' in text and status in text and setup in text and 'Active agreement' in text
                  and ('checkout.example' in text) == (request == PAID_REQUEST)
                  and 'Hosting service active' not in text)
        check('Inbox ' + actor + ' has no client mutation or private provider fields', wrapper.locator('form').count() == 0 and safe(page))
        return wrapper

    def capture(page, actor, width, height, name):
        page.set_viewport_size({'width': width, 'height': height})
        if actor == 'owner' and width == 320:
            expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
        target = page.locator('[data-hosting-purchases="' + actor + '"]')
        expect(target).to_be_visible()
        page.evaluate('document.fonts.ready')
        check('Inbox capture preserves original viewport without horizontal overflow ' + name,
              page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1') is True)
        page.keyboard.press('Escape')
        page.evaluate('document.activeElement?.blur()')
        header = '.admin-topbar' if actor == 'owner' else '.site-header'
        bounds = target.evaluate('(el,header)=>({height:el.getBoundingClientRect().height,header:document.querySelector(header).getBoundingClientRect().height})', header)
        required = math.ceil(bounds['height'] + bounds['header'] + 48)
        if not 0 < bounds['height'] <= 12000 or not 0 < bounds['header'] < 500 or required > 12548:
            raise AssertionError('Bounded inbox capture required')
        capture_height = max(height, required)
        try:
            page.set_viewport_size({'width': width, 'height': capture_height})
            target.evaluate('(el,header)=>{const r=el.getBoundingClientRect(),h=document.querySelector(header).getBoundingClientRect();window.scrollTo({top:Math.max(0,scrollY+r.top-h.height-16),left:0,behavior:"instant"});}', header)
            visible = '''(el,header)=>{const r=el.getBoundingClientRect(),h=document.querySelector(header).getBoundingClientRect();
                return r.width>0&&r.height>0&&r.left>=-1&&r.right<=innerWidth+1&&r.top>=Math.max(0,h.bottom)+8&&r.bottom<=innerHeight-8
                &&[.02,.5,.98].every(x=>[.01,.5,.99].every(y=>{const p=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);return p&&(p===el||el.contains(p));}));}'''
            page.wait_for_function('([el,header])=>(' + visible + ')(el,header)', arg=[target.element_handle(), header], timeout=30000)
            check('Inbox capture contains all five unobscured cards ' + name, target.evaluate(visible, header) is True and target.locator('[data-hosting-purchase]').count() == 5)
            check('Inbox capture headings and destination links have unobscured hit targets ' + name, target.evaluate('''el=>{
                const nodes=[...el.querySelectorAll('h2,a')];return nodes.length>=20&&nodes.every(n=>{const r=n.getBoundingClientRect();
                return r.width>0&&r.height>0&&r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight
                &&[[.5,.5],[.1,.1],[.9,.9]].every(([x,y])=>{const p=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);return p&&(p===n||n.contains(p));});});}''') is True)
            path = qa/name
            if path.exists() or path.is_symlink():
                raise RuntimeError('Preserve prior inbox capture')
            box = target.bounding_box()
            target.screenshot(path=str(path), animations='disabled', timeout=30000)
            raw = path.read_bytes()
            if raw[:8] != b'\x89PNG\r\n\x1a\n':
                raise AssertionError('Actual inbox PNG required')
            image_width, image_height = struct.unpack('!II', raw[16:24])
            captures.append({'name': name, 'sha256': hashlib.sha256(raw).hexdigest(), 'width': image_width, 'height': image_height,
                             'layout_viewport': {'width': width, 'height': height}, 'viewport': {'width': width, 'height': capture_height},
                             'viewport_box': box, 'coverage': 'Complete five-card inbox; original viewport checked before capture-only height growth.'})
        finally:
            page.set_viewport_size({'width': width, 'height': height})

    try:
        initial = snapshot(fixture('state'))
        check('Inbox continuation retains five original invoices one local receipt and zero attempts', True)
        owner, op = context(); alice, ap = context(); bob, bp = context(); contact, cp = context()
        check('Inbox owner uses actual installed login', login(op))
        check('Inbox primary client uses actual installed login', login(ap, 1))
        check('Inbox alternate client uses actual installed login', login(bp, 2))
        check('Inbox contact-key negative begins with actual primary login', login(cp, 1))
        unpaid = max((row for key, row in initial['results'].items() if key != PAID_REQUEST), key=lambda row: (row['accepted_at'], row['request_id']))
        paid = initial['results'][PAID_REQUEST]
        check('Inbox owner enters through existing hosting navigation', goto(op, admin + '?view=hosting-offers') == 200)
        entry = op.locator('.button-row').get_by_role('link', name='Hosting purchases', exact=True)
        check('Hosting navigation reaches the actual owner inbox', entry.count() == 1 and destination(entry.get_attribute('href'), admin, {'view': 'hosting-purchases'}) and click(op, entry) == 200)
        cards(op, 'owner')
        result_url = portal + '?' + urlencode({'hosting': 'checkout', 'checkout_result': unpaid['request_id']})
        check('Original retained checkout result remains available after draft setup', goto(ap, result_url) == 200 and ap.get_by_role('heading', name='Your hosting invoice', exact=True).count() == 1)
        entry = ap.get_by_role('link', name='My hosting purchases', exact=True)
        check('Checkout result reaches the actual primary-client inbox', entry.count() == 1 and destination(entry.get_attribute('href'), portal, {'hosting_purchases': 1}) and click(ap, entry) == 200)
        cards(ap, 'client')
        for actor, page, offset in [('owner', op, 0), ('client', ap, 2)]:
            for width, height, index in [(1440, 1050, 0), (320, 740, 1)]:
                capture(page, actor, width, height, CAPTURES[offset + index])
            page.set_viewport_size({'width': 1440, 'height': 1050})
        unchanged('Inbox navigation and captures preserve all original commercial and provider state')
        # Follow actual links for the paid accepted origin and one unpaid unstarted origin.
        for actor, page, is_owner in [('owner', op, True), ('client', ap, False)]:
            endpoint = admin if is_owner else portal
            for phase, row in [('paid accepted', paid), ('unpaid unstarted', unpaid)]:
                request = row['request_id']
                for kind, label, query in [('invoice', 'View invoice', {'view': 'invoice-payments', 'invoice_id': row['invoice_id']} if is_owner else {'invoice': row['invoice_id']}),
                                           ('project', 'View project', {'view': 'studio-job', 'id': row['project_id']} if is_owner else {'job': row['project_id']}),
                                           ('agreement', None, {'view': 'service-agreement', 'record': row['agreement_id']} if is_owner else {'agreement': row['agreement_id']})]:
                    check('Inbox reloads ' + actor + ' before ' + phase + ' ' + kind, goto(page, list_url(is_owner)) == 200)
                    current = card(page, request)
                    link = current.locator('[data-hosting-purchase-next]') if label is None else current.get_by_role('link', name=label, exact=True)
                    title = current.locator('dt').filter(has_text='Project').evaluate('el=>el.nextElementSibling.textContent')
                    check('Inbox ' + actor + ' ' + phase + ' ' + kind + ' binds exact route', link.count() == 1 and destination(link.get_attribute('href'), endpoint, query))
                    check('Inbox ' + actor + ' ' + phase + ' ' + kind + ' opens actual destination', click(page, link) == 200 and destination(page.url, endpoint, query))
                    if kind == 'invoice':
                        check('Inbox ' + actor + ' ' + phase + ' selected invoice retains reference', row['invoice_reference'] in page.inner_text('body') and ('Payment history' in page.inner_text('body')))
                    elif kind == 'project':
                        if is_owner:
                            project = form(page, 'studio_job')
                            selected = (project.count() == 1 and project.locator('input[name="id"]').input_value() == str(row['project_id'])
                                        and project.locator('input[name="title"]').input_value() == title)
                        else:
                            selected = page.get_by_role('heading', name=title, exact=True, level=2).count() == 1
                        check('Inbox ' + actor + ' ' + phase + ' selected project retains title and invoice', selected and row['invoice_reference'] in page.inner_text('body'))
                    elif phase == 'paid accepted':
                        order = page.locator('[data-service-order]')
                        progress = page.locator('[data-hosting-progress]')
                        check('Inbox ' + actor + ' inherited hosting remains accepted and locally prepared', order.count() == 1 and 'checkout.example' in order.inner_text() and 'Hosting request accepted' in order.inner_text()
                              and progress.count() == 1 and 'Hosting record prepared' in progress.inner_text() and 'Hosting service active' not in progress.inner_text())
                        if is_owner:
                            policy = page.locator('[data-checkout-fulfillment-policy]')
                            check('Checkout owner uses verified-payment setup without legacy manual controls',
                                  policy.count() == 1 and 'verified payment receipt' in policy.inner_text()
                                  and 'Provider setup' in policy.inner_text()
                                  and form(page, 'service_plans_order_allow_manual').count() == 0
                                  and form(page, 'service_plans_order_revoke_manual').count() == 0
                                  and page.locator('[data-hosting-fulfillment]').count() == 1
                                  and 'active manual fulfillment policy' not in page.inner_text('body'))
                        invoice = order.get_by_role('link', name='Review invoice', exact=True)
                        expected = {'view': 'invoice-payments', 'invoice_id': row['invoice_id']} if is_owner else {'invoice': row['invoice_id']}
                        check('Inherited ' + actor + ' order invoice handoff selects original paid invoice', invoice.count() == 1 and destination(invoice.get_attribute('href'), endpoint, expected)
                              and click(page, invoice) == 200 and destination(page.url, endpoint, expected) and row['invoice_reference'] in page.inner_text('body') and 'Payment history' in page.inner_text('body'))
                    elif is_owner:
                        offer = form(page, 'service_plans_order_offer')
                        values = offer.locator('[name="invoice_choice"] option').evaluate_all('els=>els.map(el=>el.value).filter(Boolean)')
                        check('Unpaid purchase opens existing owner setup form with only original invoice', offer.count() == 1 and len(values) == 1 and re.fullmatch(str(row['invoice_id']) + ':[a-f0-9]{64}', values[0]) is not None
                              and offer.locator('[name="current_password"]').input_value() == '' and not offer.locator('[name="confirm"]').is_checked())
                    else:
                        check('Unstarted client purchase does not invent a hosting offer or provider activation', page.locator('[data-service-order]').count() == 0 and page.locator('[data-hosting-progress]').count() == 0
                              and 'Hosting service active' not in page.inner_text('body'))
        unchanged('All inbox invoice project agreement and setup links are commercially read-only')
        # Existing fixed pagination must remain real navigation, not rendered URL-only proof.
        check('Owner empty second page is explicit', goto(op, list_url(True, 2)) == 200 and op.locator('[data-hosting-purchase]').count() == 0 and 'No purchases on this page' in op.inner_text('body'))
        previous = op.locator('nav[aria-label="Hosting purchase pages"]').get_by_role('link', name='Previous', exact=True)
        check('Owner previous-page link returns the real first page', destination(previous.get_attribute('href'), admin, {'view': 'hosting-purchases', 'p': 1}) and click(op, previous) == 200 and op.locator('[data-hosting-purchase]').count() == 5)
        check('Alternate client cannot choose another client by query', goto(bp, list_url(False) + '&client_id=1') == 200 and bp.locator('[data-hosting-purchase]').count() == 0 and 'No purchases on this page' in bp.inner_text('body')
              and all(row['invoice_reference'] not in bp.content() for row in initial['results'].values()))
        check('Alternate client cannot open an original foreign invoice', goto(bp, portal + '?' + urlencode({'invoice': paid['invoice_id']})) == 404
              and 'Payment record unavailable.' in bp.inner_text('body') and paid['invoice_reference'] not in bp.content())
        check('Client owner-inbox attempt reaches actual admin login instead of purchases', goto(bp, admin + '?view=hosting-purchases') == 200 and form(bp, 'login').count() == 1 and bp.locator('[data-hosting-purchase]').count() == 0)
        cookies = [cookie for cookie in contact.cookies(portal) if re.fullmatch('cwp_[a-f0-9]{12}', cookie['name'])]
        check('Inbox contact-key probe binds one actual isolated portal session', len(cookies) == 1)
        marked = fixture('contact-context', {'session_id': cookies[0]['value']})
        check('Inbox contact provenance refuses before normalization without contact-login credit', marked.get('contact_context_set') is True and goto(cp, list_url(False)) == 403
              and 'primary client outside support access' in cp.inner_text('body') and cp.locator('[data-hosting-purchase]').count() == 0)
        check('Inbox support test reaches actual owner support controls', goto(op, admin + '?view=support') == 200)
        support = form(op, 'support_start').filter(has=op.locator('input[name="client_id"][value="1"]'))
        check('Inbox owner opens actual support session for the purchase client', support.count() == 1 and click(op, support.get_by_role('button', name='Open client portal', exact=True)) == 200
              and op.get_by_role('complementary', name='Support session').count() == 1)
        support_url = op.url
        support_ids = parse_qs(urlparse(support_url).query).get('support', [])
        check('Inbox support session has one actual opaque identity', len(support_ids) == 1 and re.fullmatch('[a-f0-9]{32}', support_ids[0]) is not None)
        response = owner.request.get(portal + '?' + urlencode({'support': support_ids[0], 'hosting_purchases': 1}), max_redirects=0, timeout=30000)
        check('Real support context cannot read primary-client purchase inbox', response.status == 403 and 'primary client outside support access' in response.text() and 'data-hosting-purchase=' not in response.text())
        response = owner.request.get(admin + '?' + urlencode({'view': 'hosting-purchases', 'support': support_ids[0]}), max_redirects=0, timeout=30000)
        check('Owner purchase list refuses support-context query', response.status == 403 and 'owner outside support access' in response.text() and 'data-hosting-purchase=' not in response.text())
        check('Actual support exit restores owner navigation', click(op, form(op, 'support_end').get_by_role('button', name='Return to admin', exact=True)) == 200
              and goto(op, list_url(True)) == 200 and op.locator('[data-hosting-purchase]').count() == 5)
        unchanged('Inbox role and support refusals preserve all five invoices results and provider counters')
        check('Inbox browser has no JavaScript errors', not errors)
        check('Inbox browser requests only local resources', not blocked)
        check('Inbox produces exactly its four scoped captures', tuple(row['name'] for row in captures) == CAPTURES)
        return {'passed': all(row['passed'] is True for row in checks), 'checks': checks, 'captures': captures,
                'scope': 'Actual installed read-only owner/client purchase navigation. Five original invoices and one inherited local receipt retained; no business write, provider request or new payment. Contact-key probe is not a real contact login. Pixel approval remains separate.',
                'invoices': 5, 'business_sha256': initial['business_sha256'], 'provider_requests': 0}
    finally:
        for ctx in contexts:
            ctx.close()
