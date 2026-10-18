"""Packaged storefront continuation after the original checkout HTTP cases.

Uses the same installed hcc_ fixture and real owner/client forms. The fixed PNG
is local synthetic artwork; no account signup, provider call or payment occurs.
The runner owns source/package validation, server lifetime and evidence reports.
"""
import base64
import math
from types import SimpleNamespace
from urllib.parse import parse_qs, urljoin, urlparse
import re
import time


CAPTURES = (
    'Catalog-Storefront-Comparison-Desktop.png',
    'Catalog-Storefront-Comparison-Mobile.png',
    'Catalog-Storefront-Selected-Review.png',
    'Catalog-Storefront-Unpaid-Invoice.png',
)
SYNTHETIC_PNG = base64.b64decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGOQC6gAAAF2AOe0MgrYAAAAAElFTkSuQmCC'
)


def run_browser(env, browser):
    """Call after hosting_checkout_http_cases.run_browser with its same env."""
    fixture, qa, seed = env['fixture'], env['QA'], env['seed']
    site = env['BASE'] + '/hosting-checkout'
    portal, admin = site + '/portal/index.php', site + '/admin/index.php'
    owner_password, client_password = 'Synthetic native checkout owner', 'Synthetic checkout client'
    contexts, errors, blocked, responses, captures, checks = [], [], [], [], [], []

    def check(name, passed):
        if type(passed) is not bool or any(row['name'] == name for row in checks):
            raise AssertionError('Unique strict storefront outcome required')
        checks.append({'name': name, 'passed': passed})
        env['check'](name, passed)  # The existing runner retains progress even on failure.
        if not passed:
            raise AssertionError(name)
    for name in CAPTURES:
        if (qa / name).exists() or (qa / name).is_symlink():
            raise RuntimeError('Preserve prior storefront capture: ' + name)

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
        page.on('response', lambda response: responses.append((response.url, response.status)))
        return ctx, page

    def goto(page, url):
        response = page.goto(url, wait_until='load', timeout=30000)
        if response is None:
            raise AssertionError('Storefront navigation returned no response')
        return response.status

    def form(page, action):
        return page.locator('form').filter(has=page.locator('input[name="action"][value="' + action + '"]'))

    def values(page, action):
        return env['form_data'](env['form_for'](SimpleNamespace(text=page.content(), url=page.url), action))

    def click(page, action, label):
        form(page, action).get_by_role('button', name=label, exact=True).click()
        page.wait_for_load_state('load', timeout=30000)

    def safe(page):
        text = page.content()
        return all(value not in text for value in (
            owner_password, client_password, 'SYNTHETIC_CHECKOUT_PROVIDER_KEY',
            'PRIVATE_', 'source_key_fingerprint', 'credential_sha256', 'mapping_json', 'plan_json', '$9.87',
        ))

    def unchanged(label, before):
        check(label, fixture('state')['business_sha256'] == before['business_sha256'])

    def capture(page, locator, name, width, height):
        page.set_viewport_size({'width': width, 'height': height})
        page.evaluate('document.fonts.ready')
        locator.scroll_into_view_if_needed(timeout=30000)
        check('Storefront capture ' + name + ' fits the document',
              page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1') is True)
        # Preserve the original-width/height layout check above. Only screenshot
        # framing may grow vertically; never hide a production overlay or shrink
        # the target. Escape closes the existing menu, and blur dismisses a
        # focused skip link before measuring the real public header.
        page.keyboard.press('Escape')
        page.evaluate('document.activeElement?.blur()')
        size = locator.evaluate('''el => {
            const r=el.getBoundingClientRect(), header=document.querySelector('.site-header');
            if (!header) throw new Error('Expected public header');
            const h=header.getBoundingClientRect();
            return {height:r.height, header:h.height};
        }''')
        required = math.ceil(size['height'] + size['header'] + 40)
        if not (0 < size['height'] <= 12000 and 0 < size['header'] < 500 and required <= 12540):
            raise AssertionError('Bounded exact storefront capture extent required')
        capture_height = max(height, required)
        try:
            if capture_height != height:
                page.set_viewport_size({'width': width, 'height': capture_height})
            locator.evaluate('''el => {
                const r=el.getBoundingClientRect(), h=document.querySelector('.site-header').getBoundingClientRect();
                window.scrollTo({top:Math.max(0,scrollY+r.top-h.height-16),left:0,behavior:'instant'});
            }''')
            extent_ready = '''el => {
                const r=el.getBoundingClientRect(), h=document.querySelector('.site-header').getBoundingClientRect();
                const skip=document.querySelector('.skip-link'), s=skip?.getBoundingClientRect();
                const overlap=s&&s.right>r.left&&s.left<r.right&&s.bottom>r.top&&s.top<r.bottom;
                return r.width>0&&r.height>0&&r.left>=-1&&r.right<=innerWidth+1
                    &&r.top>=Math.max(0,h.bottom)+8&&r.bottom<=innerHeight-8&&!overlap
                    &&[.02,.5,.98].every(x=>[.01,.5,.99].every(y=>{
                        const hit=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);
                        return hit!==null&&(hit===el||el.contains(hit));
                    }));
            }'''
            page.wait_for_function(extent_ready, arg=locator.element_handle(), timeout=30000)
            check('Storefront capture ' + name + ' has its full unobscured extent',
                  locator.evaluate(extent_ready) is True)
            targets_ready = '''(el, requireAction) => {
                const heading=el.querySelector('h1,h2,h3,caption');
                const actions=[...el.querySelectorAll('button,a.btn,input[type="checkbox"]')];
                if(!heading||(requireAction&&!actions.length))return false;
                return [heading,...actions].every(node=>{
                    const r=node.getBoundingClientRect();
                    return r.width>0&&r.height>0&&r.left>=0&&r.right<=innerWidth
                        &&r.top>=0&&r.bottom<=innerHeight
                        &&[[.5,.5],[.1,.1],[.9,.1],[.1,.9],[.9,.9]].every(([x,y])=>{
                            const hit=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);
                            return hit!==null&&(hit===node||node.contains(hit));
                        });
                });
            }'''
            check('Storefront capture ' + name + ' has unobscured heading and action points',
                  locator.evaluate(targets_ready, name != CAPTURES[3]) is True)
            box = locator.bounding_box()
            locator.screenshot(path=str(qa / name), animations='disabled', timeout=30000)
            captures.append({'name': name, 'mode': 'locator',
                             'layout_viewport': {'width': width, 'height': height},
                             'viewport': {'width': width, 'height': capture_height},
                             'measured_header_height': size['header'], 'viewport_box': box,
                             'coverage': 'Complete target extent; original layout checked before capture-only height growth.'})
        finally:
            if capture_height != height:
                page.set_viewport_size({'width': width, 'height': height})

    try:
        owner, op = context()
        visitor, vp = context()  # Intentionally anonymous until the selected-plan login form.
        initial = fixture('state')
        check('Storefront continues the existing completed checkout HTTP fixture',
              initial['counts']['results'] == 4 and initial['counts']['invoices'] == 4
              and initial['counts']['attempts'] == 0 and bool(initial['latest_publication']))
        check('Storefront owner reaches the real installed sign-in form',
              goto(op, admin + '?view=login') == 200 and form(op, 'login').count() == 1)
        login = form(op, 'login')
        login.locator('[name="email"]').fill('checkout-owner@example.invalid')
        login.locator('[name="password"]').fill(owner_password)
        login.locator('button[type="submit"],button:not([type])').click()
        op.wait_for_load_state('load', timeout=30000)
        check('Storefront owner authenticates through the installed login', 'Sales pipeline' in op.content())

        # A genuine multipart upload supplies public artwork, then an actual new
        # publication retains it. No fixture or SQL shortcut edits commercial rows.
        check('Storefront imported draft exposes the real image form',
              goto(op, admin + '?view=catalog&id=' + str(seed['product_id'])) == 200
              and form(op, 'catalog_import_image').count() == 1)
        image_form = form(op, 'catalog_import_image')
        image_form.locator('[name="product_image"]').set_input_files({
            'name': 'synthetic-storefront.png', 'mimeType': 'image/png', 'buffer': SYNTHETIC_PNG,
        })
        image_form.locator('[name="owner_password"]').fill(owner_password)
        click(op, 'catalog_import_image', 'Save image')
        uploaded_image = op.locator('img[alt="Uploaded draft product image"]')
        check('Storefront owner saves local artwork through the actual multipart form',
              'Draft product image saved. Publication has not changed.' in op.content()
              and uploaded_image.count() == 1)
        image_url = urljoin(op.url, uploaded_image.get_attribute('src'))
        check('Storefront uploaded image uses only the installed media endpoint',
              re.fullmatch(re.escape(site) + r'/media/index\.php\?id=[a-f0-9]{32}', image_url) is not None)
        uploaded = fixture('state')
        check('Storefront image upload leaves prior invoices and publications unchanged',
              uploaded['counts'] == initial['counts'] and uploaded['results'] == initial['results'])

        owner_url = admin + '?view=hosting-offers&product_id=' + str(seed['product_id'])
        check('Storefront owner opens the existing imported product and matching plan',
              goto(op, owner_url) == 200 and form(op, 'hosting_catalog_preview').count() == 1)
        preview = form(op, 'hosting_catalog_preview')
        preview.locator('[name="plan_id"]').select_option(seed['plan_id'])
        preview.locator('[name="checkout_mode"]').select_option('invoice-on-confirmation')
        preview.locator('[name="billing_timezone"]').fill('UTC')
        preview.locator('[name="billing_alignment"]').select_option('day-of-month')
        click(op, 'hosting_catalog_preview', 'Preview offer')
        publish = values(op, 'hosting_catalog_publish')
        unchanged('Storefront owner preview performs no commercial write', uploaded)
        form(op, 'hosting_catalog_publish').locator('[name="current_password"]').fill(owner_password)
        form(op, 'hosting_catalog_publish').locator('[name="confirm"]').check()
        click(op, 'hosting_catalog_publish', 'Publish hosting offer')
        published = fixture('state')
        publication = published['latest_publication']
        check('Storefront owner publishes exactly the selected image-bearing checkout offer',
              publication['id'] == publish['publication_id']
              and published['counts'] == {**uploaded['counts'], 'publications': uploaded['counts']['publications'] + 1}
              and published['results'] == uploaded['results'])
        expected_query = {'hosting': ['checkout'], 'offer': [publication['id']],
                          'offer_sha256': [publication['document_sha256']]}

        public_url = site + '/index.php?page=shared-hosting'
        check('Anonymous Shared Hosting alias serves the actual comparison page',
              goto(vp, public_url) == 200 and vp.locator('#hosting-plans .hosting-comparison-table').count() == 1)
        css = vp.locator('link[rel="stylesheet"][href*="/assets/css/hosting-comparison.css?"]')
        check('Shared Hosting includes its versioned local comparison stylesheet',
              css.count() == 1 and urlparse(css.get_attribute('href')).path == '/hosting-checkout/assets/css/hosting-comparison.css')
        css_url = urljoin(vp.url, css.get_attribute('href'))
        vp.wait_for_function("() => [...document.styleSheets].some(s => s.href && s.href.includes('/assets/css/hosting-comparison.css?') && [...s.cssRules].some(r => r.selectorText === '.hosting-comparison-scroll'))", timeout=30000)
        check('Shared Hosting loads the real comparison stylesheet with HTTP 200', (css_url, 200) in responses)
        image = vp.locator('#hosting-plans .hosting-catalog-image')
        check('Shared Hosting displays the exact image uploaded before publication',
              image.count() == 1 and urljoin(vp.url, image.get_attribute('src')) == image_url)
        image.scroll_into_view_if_needed()
        vp.wait_for_function("() => { const i=document.querySelector('#hosting-plans .hosting-catalog-image'); return i && i.complete && i.naturalWidth>0 && i.naturalHeight>0; }", timeout=30000)
        check('Shared Hosting actually loads the local uploaded PNG through HTTP',
              (image_url, 200) in responses and image.evaluate('i => i.naturalWidth === 1 && i.naturalHeight === 1') is True)
        check('Shared Hosting publishes only reviewed terms and no private source data',
              safe(vp) and '$25.00' in vp.inner_text('#hosting-plans')
              and 'verified payment and owner approval' in vp.inner_text('#hosting-plans'))
        choose = vp.locator('#hosting-plans a[href*="hosting=checkout"]')
        check('Public plan link binds the exact immutable offer and fingerprint',
              choose.count() == 1 and parse_qs(urlparse(choose.get_attribute('href')).query) == expected_query)
        selected_url = urljoin(vp.url, choose.get_attribute('href'))
        region = vp.locator('.hosting-comparison-scroll')
        for width, height, name in ((1440, 1050, CAPTURES[0]), (320, 740, CAPTURES[1])):
            vp.set_viewport_size({'width': width, 'height': height})
            region.focus()
            check('Storefront comparison ' + str(width) + ' keeps aligned price and service rows',
                  vp.locator('.hosting-comparison-table tbody tr').evaluate_all('rows => rows.every(r => {const c=[...r.cells].map(n=>n.getBoundingClientRect()); return c.length===2 && c.every(n=>Math.abs(n.top-c[0].top)<1 && Math.abs(n.bottom-c[0].bottom)<1);})') is True)
            check('Storefront comparison ' + str(width) + ' is keyboard focusable',
                  region.evaluate('n => document.activeElement === n && !!n.getAttribute("aria-labelledby")') is True)
            capture(vp, vp.locator('#hosting-plans'), name, width, height)
        unchanged('Anonymous comparison and image reads leave the commercial graph unchanged', published)

        # The browser actually clicks the public link while logged out. No
        # artificial return URL or authenticated navigation replaces this step.
        choose.click()
        vp.wait_for_load_state('load', timeout=30000)
        check('Selected public plan reaches login with its exact offer and hash',
              vp.url == selected_url and form(vp, 'login').count() == 1
              and parse_qs(urlparse(vp.url).query) == expected_query
              and 'Sign in to review your selected hosting plan and its terms.' in vp.content())
        check('Storefront login truthfully requires an existing invited client',
              'Ask the studio to issue a new private invitation link.' in vp.content()
              and form(vp, 'activate').count() == 0 and form(vp, 'hosting_catalog_checkout_confirm').count() == 0)
        login = form(vp, 'login')
        login.locator('[name="email"]').fill('checkout-client1@example.invalid')
        login.locator('[name="password"]').fill(client_password)
        login.locator('button[type="submit"],button:not([type])').click()
        vp.wait_for_load_state('load', timeout=30000)
        selection = values(vp, 'hosting_catalog_checkout_preview')
        check('Actual client login returns directly to the exact selected offer',
              vp.url == selected_url and selection['publication_id'] == publication['id']
              and selection['publication_sha256'] == publication['document_sha256']
              and form(vp, 'login').count() == 0 and safe(vp))
        unchanged('Selected-plan login creates no review or invoice', published)

        wrong_hash = ('0' if publication['document_sha256'][0] != '0' else '1') + publication['document_sha256'][1:]
        stale_url = portal + '?hosting=checkout&offer=' + publication['id'] + '&offer_sha256=' + wrong_hash
        check('A stale public offer hash is refused without a replacement selection',
              goto(vp, stale_url) == 200
              and vp.get_by_role('alert').filter(has_text='This hosting offer changed. Review the current offer before continuing.').count() == 1
              and form(vp, 'hosting_catalog_checkout_preview').count() == 0
              and form(vp, 'hosting_catalog_checkout_confirm').count() == 0)
        unchanged('Stale public offer hash creates no review or invoice', published)
        check('Original selected offer remains available after stale-hash refusal', goto(vp, selected_url) == 200)
        selection = values(vp, 'hosting_catalog_checkout_preview')
        preview = form(vp, 'hosting_catalog_checkout_preview')
        preview.locator('[name="project_mode"]').select_option('existing')
        preview.locator('[name="project_id"]').select_option('1')
        click(vp, 'hosting_catalog_checkout_preview', 'Review plan and billing dates')
        confirm = values(vp, 'hosting_catalog_checkout_confirm')
        reviewed = fixture('state')
        check('Selected storefront plan creates one exact retained review and no invoice',
              confirm['request_id'] == selection['request_id']
              and reviewed['counts'] == {**published['counts'], 'reviews': published['counts']['reviews'] + 1}
              and reviewed['results'] == published['results'])
        check('Storefront review shows actual plan image project price and billing dates',
              vp.get_by_role('heading', name='Confirm your plan and first invoice', exact=True).count() == 1
              and all(text in vp.inner_text('body') for text in ('Reviewed checkout hosting', 'Existing project #1', '$25.00', 'First billing period', 'First invoice due', 'Next billing period starts', 'UTC'))
              and vp.locator('.hosting-catalog-image').get_attribute('src') == image_url and safe(vp))
        review_card = vp.locator('section.workspace-card').filter(has=vp.get_by_role('heading', name='Confirm your plan and first invoice', exact=True))
        capture(vp, review_card, CAPTURES[2], 320, 740)
        started = int(time.time())
        form(vp, 'hosting_catalog_checkout_confirm').locator('[name="confirm"]').check()
        click(vp, 'hosting_catalog_checkout_confirm', 'Agree and create first invoice')
        finished = int(time.time())
        complete = fixture('state')
        result = complete['results'][confirm['request_id']]
        check('One storefront confirmation creates one unpaid first invoice on the chosen project',
              vp.get_by_role('heading', name='Your hosting invoice', exact=True).count() == 1
              and result['request_id'] == confirm['request_id'] and result['project_id'] == 1
              and result['invoice_status'] == 'issued' and result['amount_cents'] == 2500 and result['currency'] == 'USD'
              and started <= result['accepted_at'] <= finished
              and complete['counts'] == {**reviewed['counts'], 'results': reviewed['counts']['results'] + 1, 'invoices': reviewed['counts']['invoices'] + 1})
        check('Storefront purchase preserves prior results and records no payment or activation',
              all(complete['results'][key] == value for key, value in published['results'].items())
              and result['automatic_charge'] is False and result['provider_activation'] is False
              and complete['counts']['receipts'] == initial['counts']['receipts'] and complete['counts']['attempts'] == 0)
        result_url = vp.url
        invoice_link = vp.get_by_role('link', name='View invoice', exact=True)
        check('Storefront result links to its exact owned invoice',
              parse_qs(urlparse(invoice_link.get_attribute('href')).query) == {'invoice': [str(result['invoice_id'])]})
        invoice_link.click()
        vp.wait_for_load_state('load', timeout=30000)
        check('Storefront client sees the real unpaid invoice and honest payment boundary',
              result['invoice_reference'] in vp.inner_text('body') and 'Status: issued' in vp.inner_text('body')
              and 'No payments recorded yet.' in vp.inner_text('body')
              and 'Online card checkout is not available yet.' in vp.inner_text('body') and safe(vp))
        capture(vp, vp.locator('.ledger-document article.workspace-card'), CAPTURES[3], 1440, 1050)
        response = visitor.request.post(portal + '?hosting=checkout', form={**confirm, 'confirm': '1'}, max_redirects=0, timeout=30000)
        check('Storefront exact confirmation retry returns the original invoice result',
              response.status == 303 and urljoin(portal, response.headers.get('location', '')) == result_url)
        unchanged('Storefront confirmation retry and invoice reads never duplicate the purchase', complete)
        check('Storefront packaged browser has no JavaScript page errors', not errors)
        check('Storefront packaged browser uses only local resources', not blocked)
        check('Storefront produces exactly its four scoped captures', tuple(item['name'] for item in captures) == CAPTURES)
        return {'passed': all(row['passed'] is True for row in checks), 'checks': checks, 'captures': captures,
                'scope': 'Actual installed public plan selection, invited-client login and one unpaid first invoice. One current plan; fixed local synthetic PNG. No signup, payment or provider operation. Pixel approval remains separate.',
                'publication_id': publication['id'], 'publication_sha256': publication['document_sha256'],
                'result': result, 'actual_comparison_plan_count': 1, 'local_synthetic_artwork': True,
                'new_account_signup': False, 'payment_or_provider_operation': False}
    finally:
        for ctx in contexts:
            ctx.close()
