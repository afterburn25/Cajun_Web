"""Installed owner plan setup after the original hcc_ storefront purchase.

All mutations use actual authenticated forms. The existing read-only state bridge
proves retained financial history and exact local setup counts. No provider read,
fixture seed, session injection, timestamp edit or automatic publication occurs.
"""
import hashlib
import math
import re
import struct
from types import SimpleNamespace
from urllib.parse import parse_qs, urlencode, urljoin, urlparse

from playwright.sync_api import expect


CAPTURES = tuple('Hosting-Plan-Setup-' + stage + '-' + size + '.png'
                 for stage in ('Ready', 'Preview') for size in ('Desktop', 'Mobile'))
IDENTITIES = ('product_id', 'plan_id', 'activation_event_id', 'mapping_id', 'review_sha256')
SETUP_COUNTERS = ('plans', 'plan_events', 'mappings', 'provider_refreshes', 'provider_checks')
ACTION = 'hosting_catalog_finish_plan'


def setup_counts(state):
    """Missing/extra or loosely typed bridge counters never count as proof."""
    counters = state.get('setup_counts')
    if (not isinstance(counters, dict) or set(counters) != set(SETUP_COUNTERS)
            or any(type(counters[k]) is not int or counters[k] < 0 for k in SETUP_COUNTERS)):
        raise AssertionError('Exact read-only setup counters required')
    return counters


def owner_fields(saved):
    """Keep the actual form's identities; only explicit synthetic owner choices are added."""
    if (not isinstance(saved, dict) or saved.get('action') != ACTION
            or not isinstance(saved.get('csrf'), str) or not saved['csrf']
            or not isinstance(saved.get('product_id'), str)
            or re.fullmatch(r'[1-9][0-9]{0,9}', saved['product_id']) is None
            or any(not isinstance(saved.get(k), str) or re.fullmatch(r'[a-f0-9]{32}', saved[k]) is None
                   for k in ('plan_id', 'activation_event_id', 'mapping_id'))
            or len({saved[k] for k in ('plan_id', 'activation_event_id', 'mapping_id')}) != 3
            or not isinstance(saved.get('review_sha256'), str)
            or re.fullmatch(r'[a-f0-9]{64}', saved['review_sha256']) is None):
        raise AssertionError('Exact installed setup form required')
    return {**saved, 'inclusions': 'One managed website\nStudio email support',
            'exclusions': 'Domain registration', 'limits': 'websites = 1\nsupport_requests = 4',
            'support': 'Email during stated studio hours', 'reseller_id': '42',
            'package_label': 'Reviewed guided hosting package', 'package_region': 'Reviewed region',
            'review_days': '7', 'limit_websites': '1', 'limit_disk_mb': '',
            'limit_bandwidth_mb': '', 'limit_mailboxes': '', 'limit_databases': '',
            'cost_known': '0', 'cost_amount': '', 'cost_currency': '', 'cost_period': '',
            'cost_tax': 'unknown', 'cost_basis': 'per-package', 'cost_reference': '',
            'confirm': '1', 'reviewed_defaults': '1'}


def run_browser(env, browser):
    fixture, qa, seed = env['fixture'], env['QA'], env['seed']
    site = env['BASE'] + '/hosting-checkout'
    admin, portal = site + '/admin/index.php', site + '/portal/index.php'
    owner_password = 'Synthetic native checkout owner'
    contexts, errors, blocked, checks, captures = [], [], [], [], []

    def check(name, passed):
        if type(passed) is not bool or any(row['name'] == name for row in checks):
            raise AssertionError('Unique strict setup outcome required')
        checks.append({'name': name, 'passed': passed})
        env['check'](name, passed)
        if not passed:
            raise AssertionError(name)

    for name in CAPTURES:
        if (qa / name).exists() or (qa / name).is_symlink():
            raise RuntimeError('Preserve prior setup capture: ' + name)

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
            raise AssertionError('Setup navigation has no response')
        return response.status

    def form(page, action):
        return page.locator('form').filter(has=page.locator('input[name="action"][value="' + action + '"]'))

    def fields(page, action):
        return env['form_data'](env['form_for'](SimpleNamespace(text=page.content(), url=page.url), action))

    def response_fields(response, action):
        return env['form_data'](env['form_for'](SimpleNamespace(text=response.text(), url=response.url), action))

    def click(page, action, label):
        form(page, action).get_by_role('button', name=label, exact=True).click()
        page.wait_for_load_state('load', timeout=30000)

    def post(ctx, url, data):
        return ctx.request.post(url, form=data, max_redirects=0, timeout=30000)

    def unchanged(name, before):
        after = fixture('state')
        check(name, after['business_sha256'] == before['business_sha256']
              and setup_counts(after) == setup_counts(before))

    def fill(page, selected, password=''):
        target = form(page, ACTION)
        for key in ('inclusions', 'exclusions', 'limits', 'support', 'reseller_id', 'package_label', 'package_region', 'review_days',
                    'limit_websites', 'limit_disk_mb', 'limit_bandwidth_mb', 'limit_mailboxes', 'limit_databases'):
            target.locator('[name="' + key + '"]').fill(selected[key])
        target.locator('[name="current_password"]').fill(password)
        target.locator('[name="reviewed_defaults"]').check()
        target.locator('[name="confirm"]').check()

    def capture(page, target, name, width, height):
        page.set_viewport_size({'width': width, 'height': height})
        if width == 320:
            expect(page.locator('.admin-sidebar')).not_to_be_in_viewport()
        expect(target).to_be_visible()
        page.evaluate('document.fonts.ready')
        check('Guided setup capture preserves original width without overflow ' + name,
              page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1') is True)
        page.keyboard.press('Escape')
        page.evaluate('document.activeElement?.blur()')
        size = target.evaluate('''el => ({height:el.getBoundingClientRect().height,
            header:document.querySelector('.admin-topbar').getBoundingClientRect().height})''')
        required = math.ceil(size['height'] + size['header'] + 48)
        if not (0 < size['height'] <= 12000 and 0 < size['header'] < 500 and required <= 12548):
            raise AssertionError('Bounded setup target required')
        capture_height = max(height, required)
        try:
            if capture_height != height:
                page.set_viewport_size({'width': width, 'height': capture_height})
            target.evaluate('''el => {const r=el.getBoundingClientRect(),h=document.querySelector('.admin-topbar').getBoundingClientRect();
                window.scrollTo({top:Math.max(0,scrollY+r.top-h.height-16),left:0,behavior:'instant'});}''')
            visible = '''el => {
                const r=el.getBoundingClientRect(),h=document.querySelector('.admin-topbar').getBoundingClientRect();
                return r.width>0&&r.height>0&&r.left>=-1&&r.right<=innerWidth+1&&r.top>=Math.max(0,h.bottom)+8&&r.bottom<=innerHeight-8
                    &&[.02,.5,.98].every(x=>[.01,.5,.99].every(y=>{const p=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);return p&&(p===el||el.contains(p));}));
            }'''
            page.wait_for_function(visible, arg=target.element_handle(), timeout=30000)
            check('Guided setup capture has its complete unobscured target ' + name, target.evaluate(visible) is True)
            check('Guided setup capture headings and visible controls are clickable ' + name, target.evaluate('''el => {
                const heading=el.querySelector('h2'), nodes=[heading,...el.querySelectorAll('button,a,input:not([type="hidden"]),textarea,select')];
                return heading&&nodes.filter(n=>n&&n.getBoundingClientRect().width&&n.getBoundingClientRect().height).every(n=>{
                    const r=n.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight
                        &&[[.5,.5],[.1,.1],[.9,.9]].every(([x,y])=>{const p=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);return p&&(p===n||n.contains(p));});});
            }''') is True)
            path = qa / name
            box = target.bounding_box()
            target.screenshot(path=str(path), animations='disabled', timeout=30000)
            raw = path.read_bytes()
            if raw[:8] != b'\x89PNG\r\n\x1a\n':
                raise AssertionError('Actual PNG capture required')
            image_width, image_height = struct.unpack('!II', raw[16:24])
            captures.append({'name': name, 'sha256': hashlib.sha256(raw).hexdigest(), 'width': image_width, 'height': image_height,
                             'layout_viewport': {'width': width, 'height': height}, 'viewport': {'width': width, 'height': capture_height},
                             'viewport_box': box, 'coverage': 'Complete scoped target; original width/height checked before capture-only height growth.'})
        finally:
            if capture_height != height:
                page.set_viewport_size({'width': width, 'height': height})

    try:
        initial = fixture('state')
        initial_setup = setup_counts(initial)
        check('Guided setup follows the original five completed invoice results',
              initial['counts']['results'] == 5 and initial['counts']['invoices'] == 5
              and initial['counts']['attempts'] == 0 and len(initial['results']) == 5)
        owner, op = context()
        client, cp = context()
        check('Guided setup opens the actual owner login', goto(op, admin + '?view=login') == 200 and form(op, 'login').count() == 1)
        login = form(op, 'login')
        login.locator('[name="email"]').fill('checkout-owner@example.invalid')
        login.locator('[name="password"]').fill(owner_password)
        login.locator('button[type="submit"],button:not([type])').click()
        op.wait_for_load_state('load', timeout=30000)
        check('Guided setup authenticates the real owner session', 'Sales pipeline' in op.content())
        product_url = admin + '?view=catalog&id=' + str(seed['product_id'])
        owner_url = admin + '?view=hosting-offers&product_id=' + str(seed['product_id'])
        check('Imported draft exposes the actual guided setup form', goto(op, owner_url) == 200 and form(op, ACTION).count() == 1)
        stale = owner_fields(fields(op, ACTION))
        check('Guided setup copies the existing retail price without writable overrides',
              '$25.00 USD per month' in op.inner_text('[data-hosting-plan-setup]')
              and form(op, ACTION).locator('[name="price"],[name="currency"],[name="type"],[name="package_type"],[name="profile_id"]').count() == 0)
        unchanged('Opening guided setup does not create a plan or provider intent', initial)
        check('Guided setup opens the real imported product editor', goto(op, product_url) == 200 and form(op, 'catalog_save').count() == 1)
        image_url = op.locator('img[alt="Uploaded draft product image"]').get_attribute('src')
        edit = form(op, 'catalog_save')
        edit.locator('[name="amount"]').fill('27.00')
        edit.locator('[name="summary"]').fill('One reviewed hosting service with owner-completed setup.')
        click(op, 'catalog_save', 'Save product')
        changed = fixture('state')
        check('Actual product edit changes only its local draft while retaining prior purchases',
              'Product saved with a new revision.' in op.content() and changed['counts'] == initial['counts']
              and changed['results'] == initial['results'] and setup_counts(changed) == initial_setup
              and changed['business_sha256'] != initial['business_sha256'])
        check('Product edit keeps the original uploaded local image', op.locator('img[alt="Uploaded draft product image"]').get_attribute('src') == image_url)
        response = post(owner, owner_url, {**stale, 'current_password': owner_password})
        stale_fields = response_fields(response, ACTION)
        check('Stale setup review is refused with original entries and digest retained',
              response.status == 200 and 'These details changed or are unavailable.' in response.text()
              and all(stale_fields[k] == stale[k] for k in IDENTITIES + ('inclusions', 'exclusions', 'limits', 'support', 'package_region'))
              and stale_fields['current_password'] == '')
        unchanged('Stale setup refusal creates no plan mapping invoice or provider intent', changed)
        sp = owner.new_page()
        check('Guided setup uses the real support start page', goto(sp, admin + '?view=support') == 200)
        support = form(sp, 'support_start').filter(has=sp.locator('input[name="client_id"][value="1"]'))
        support.get_by_role('button', name='Open client portal', exact=True).click()
        sp.wait_for_load_state('load', timeout=30000)
        check('Guided setup support denial uses an actual active support session', sp.get_by_role('complementary', name='Support session').count() == 1)
        support_id = parse_qs(urlparse(sp.url).query)['support'][0]
        # Admin forms use the current base token; the support banner has a distinct HMAC.
        owner_review = owner.request.get(owner_url, max_redirects=0, timeout=30000)
        check('Support refusal obtains a genuine current owner form token', owner_review.status == 200)
        support_csrf = response_fields(owner_review, ACTION)['csrf']
        response = post(owner, owner_url + '&' + urlencode({'support': support_id}), {**stale, 'csrf': support_csrf, 'current_password': owner_password})
        check('Active support cannot finish an owner hosting plan', response.status in (200, 403)
              and 'outside client support' in response.text() and 'Your session expired.' not in response.text())
        unchanged('Support setup refusal leaves business history unchanged', changed)
        click(sp, 'support_end', 'Return to admin')
        sp.close()
        # Support start/end rotate CSRF; this explicit reload creates the final review afterward.
        check('Explicit reload reviews the current product price', goto(op, owner_url) == 200 and '$27.00 USD per month' in op.inner_text('[data-hosting-plan-setup]'))
        selected = owner_fields(fields(op, ACTION))
        check('Fresh review changes digest and setup identities only after explicit reload',
              selected['review_sha256'] != stale['review_sha256']
              and all(selected[k] != stale[k] for k in ('plan_id', 'activation_event_id', 'mapping_id')))
        response = post(owner, owner_url, {**selected, 'csrf': 'invalid-csrf', 'current_password': owner_password})
        check('Invalid CSRF is refused by the installed setup route', response.status in (200, 403) and 'Your session expired.' in response.text())
        unchanged('CSRF refusal leaves complete commercial and provider history unchanged', changed)
        for key in ('confirm', 'reviewed_defaults'):
            response = post(owner, owner_url, {**selected, key: '', 'current_password': owner_password})
            check('Installed setup requires explicit ' + key, response.status == 200 and 'Create matching plan' in response.text() and 'role="alert"' in response.text())
            unchanged('Missing ' + key + ' leaves all business rows unchanged', changed)
        check('Other client uses the actual installed portal login', goto(cp, portal) == 200 and form(cp, 'login').count() == 1)
        login = form(cp, 'login')
        login.locator('[name="email"]').fill('checkout-client2@example.invalid')
        login.locator('[name="password"]').fill('Synthetic checkout client')
        login.locator('button[type="submit"],button:not([type])').click()
        cp.wait_for_load_state('load', timeout=30000)
        check('Other client is authenticated without an owner session', form(cp, 'login').count() == 0 and cp.locator('input[name="csrf"]').count() > 0)
        client_csrf = cp.locator('input[name="csrf"]').first.get_attribute('value')
        response = post(client, owner_url, {**selected, 'csrf': client_csrf, 'current_password': owner_password})
        check('Authenticated client cannot use the owner setup action', response.status == 303 and parse_qs(urlparse(response.headers.get('location', '')).query).get('view') == ['login'])
        unchanged('Client setup attempt creates no local or provider record', changed)
        fill(op, selected, 'deliberately-wrong-password')
        click(op, ACTION, 'Create matching plan')
        retried = fields(op, ACTION)
        check('Password error keeps original setup choices and clears password and consent',
              op.get_by_role('alert').filter(has_text='Your current password is not correct.').count() == 1
              and all(retried[k] == selected[k] for k in IDENTITIES + ('inclusions', 'exclusions', 'limits', 'support', 'package_label', 'package_region', 'reseller_id'))
              and retried['current_password'] == '' and not form(op, ACTION).locator('[name="confirm"]').is_checked()
              and not form(op, ACTION).locator('[name="reviewed_defaults"]').is_checked())
        unchanged('Password failure leaves all plan and provider records unchanged', changed)
        for width, height, name in ((1440, 1050, CAPTURES[0]), (320, 740, CAPTURES[1])):
            capture(op, op.locator('[data-hosting-plan-setup]'), name, width, height)
        fill(op, selected, owner_password)
        click(op, ACTION, 'Create matching plan')
        created = fixture('state')
        check('Owner creates exactly one plan activation and mapping through the real form',
              parse_qs(urlparse(op.url).query) == {'view': ['hosting-offers'], 'product_id': [str(seed['product_id'])], 'plan_id': [selected['plan_id']]}
              and 'Matching plan and hosting setup saved.' in op.content()
              and setup_counts(created) == {**initial_setup, 'plans': initial_setup['plans'] + 1, 'plan_events': initial_setup['plan_events'] + 1, 'mappings': initial_setup['mappings'] + 1}
              and created['counts'] == changed['counts'] and created['results'] == changed['results'])
        preview_form = form(op, 'hosting_catalog_preview')
        check('Created exact active plan is selected before separate preview', preview_form.locator('[name="plan_id"]').input_value() == selected['plan_id'])
        selected_url = op.url
        response = post(owner, owner_url, {**selected, 'current_password': owner_password})
        check('Exact setup retry redirects to the same created plan', response.status == 303 and urljoin(admin, response.headers.get('location', '')) == selected_url)
        unchanged('Exact setup retry never creates another plan activation or mapping', created)
        check('Exact newly created plan remains selectable outside the matching search page',
              goto(op, selected_url + '&plan_search=does-not-match&plan_page=99') == 200
              and form(op, 'hosting_catalog_preview').locator('[name="plan_id"]').input_value() == selected['plan_id'])
        preview_form = form(op, 'hosting_catalog_preview')
        preview_form.locator('[name="checkout_mode"]').select_option('invoice-on-confirmation')
        preview_form.locator('[name="billing_timezone"]').fill('UTC')
        preview_form.locator('[name="billing_alignment"]').select_option('day-of-month')
        click(op, 'hosting_catalog_preview', 'Preview offer')
        publish = fields(op, 'hosting_catalog_publish')
        check('Separate publication preview binds the exact new plan and owner commercial terms',
              publish['plan_id'] == selected['plan_id'] and publish['product_id'] == str(seed['product_id'])
              and publish['checkout_mode'] == 'invoice-on-confirmation'
              and re.fullmatch(r'[a-f0-9]{64}', publish['review_sha256']) is not None)
        review = op.locator('section.workspace-card').filter(has=op.get_by_role('heading', name='Review before publishing', exact=True))
        text = review.inner_text()
        check('New offer preview shows copied price service terms and retained local image',
              all(value in text for value in ('$27.00', 'One managed website', 'Studio email support', 'Domain registration', 'Email during stated studio hours'))
              and urljoin(op.url, review.locator('.hosting-catalog-image').get_attribute('src')) == urljoin(product_url, image_url))
        check('Preview contains no provider credentials private mapping or submitted password',
              all(value not in review.inner_text() for value in ('SYNTHETIC_CHECKOUT_PROVIDER_KEY', 'PRIVATE_', 'credential_sha256', 'mapping_json', owner_password)))
        check('Publication remains a separate unchecked password-confirmed action',
              form(op, 'hosting_catalog_publish').locator('[name="current_password"]').input_value() == ''
              and not form(op, 'hosting_catalog_publish').locator('[name="confirm"]').is_checked())
        preview_image = review.locator('.hosting-catalog-image')
        preview_image.scroll_into_view_if_needed()
        op.wait_for_function('image => image.complete && image.naturalWidth > 0 && image.naturalHeight > 0',
                             arg=preview_image.element_handle(), timeout=30000)
        check('Guided offer preview loads the retained original local artwork',
              preview_image.evaluate('image => image.naturalWidth === 1 && image.naturalHeight === 1') is True)
        for width, height, name in ((1440, 1050, CAPTURES[2]), (320, 740, CAPTURES[3])):
            capture(op, review, name, width, height)
        unchanged('Setup preview and captures do not publish order invoice or contact provider', created)
        final = fixture('state')
        check('All original invoices results publication records and provider intent counts remain',
              final['counts'] == initial['counts'] and final['results'] == initial['results']
              and final['latest_publication'] == initial['latest_publication']
              and all(setup_counts(final)[key] == initial_setup[key] for key in ('provider_refreshes', 'provider_checks')))
        check('Guided setup browser has no JavaScript errors', not errors)
        check('Guided setup browser requests only local resources', not blocked)
        check('Guided setup produces exactly its four scoped captures', tuple(row['name'] for row in captures) == CAPTURES)
        return {'passed': all(row['passed'] is True for row in checks), 'checks': checks, 'captures': captures,
                'scope': 'Actual installed owner setup and independent publication preview after five retained purchases. Original product revision intentionally advances; prior publication becomes stale for new orders. No new publication, invoice, provider read/write or payment.',
                'product_id': seed['product_id'], 'plan_id': selected['plan_id'], 'mapping_id': selected['mapping_id'],
                'publication_preview_id': publish['publication_id'], 'provider_requests': 0, 'publication_submitted': False}
    finally:
        for ctx in contexts:
            ctx.close()
