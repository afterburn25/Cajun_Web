"""Real installed-page re-link after the separate completed joined native purchase."""
from types import SimpleNamespace
from urllib.parse import urlparse


def run_browser(env, browser):
    check, fixture, qa = env['check'], env['fixture'], env['QA']
    seed = env['seed']; site = env['BASE'] + '/hosting-purchase'
    portal = site + '/portal/index.php'; providers = site + '/admin/index.php?view=provider-settings'
    agreement = site + '/admin/index.php?view=service-agreement&record=' + seed['agreement']
    owner_password = 'Synthetic joined hosting owner'; client_password = 'Synthetic joined hosting client'
    contexts = []; errors = []; blocked = []; captures = []

    def context():
        ctx = browser.new_context(viewport={'width': 1440, 'height': 1050})
        def route(request):
            if request.request.url.startswith(env['BASE'] + '/') or request.request.url.startswith(('data:', 'about:')):
                request.continue_()
            else:
                blocked.append(urlparse(request.request.url).scheme); request.abort()
        ctx.route('**/*', route); contexts.append(ctx)
        page = ctx.new_page(); page.on('pageerror', lambda error: errors.append(type(error).__name__))
        return ctx, page

    def goto(page, url):
        response = page.goto(url, wait_until='load', timeout=30000)
        if response is None: raise AssertionError('Navigation returned no response')
        return response.status

    def form(page, action, scope=None):
        parent = scope if scope is not None else page
        return parent.locator('form').filter(has=page.locator('input[name="action"][value="' + action + '"]'))

    def login(page, email, password, client=False):
        goto(page, portal if client else site + '/admin/index.php?view=login')
        f = form(page, 'login'); f.locator('[name="email"]').fill(email); f.locator('[name="password"]').fill(password)
        f.locator('button[type="submit"], button:not([type])').click()
        page.wait_for_load_state('load', timeout=30000)
        return 'Welcome, ' in page.content() if client else 'Sales pipeline' in page.content()

    def safe(text):
        return all(x not in text for x in (owner_password, client_password, 'SYNTHETIC_JOINED_HOSTING_KEY',
            'PRIVATE_JOINED', 'source_key_fingerprint', 'credential_sha256', '$9.87'))

    def capture(page, stem, selector=None):
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.evaluate('document.fonts.ready'); page.wait_for_timeout(350)
            check(stem + ' ' + label + ' has no horizontal document overflow', page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
            name = stem + '-' + label + '.png'
            if selector:
                target = page.locator(selector); target.scroll_into_view_if_needed(); box = target.bounding_box()
                check(stem + ' ' + label + ' target is visible and nonempty', box is not None and box['width'] > 0 and box['height'] > 0)
                target.screenshot(path=str(qa / name)); mode = 'element'
            else:
                page.evaluate('scrollTo(0,0)'); box = None; page.screenshot(path=str(qa / name), full_page=True); mode = 'full_page'
            captures.append({'name': name, 'mode': mode, 'selector': selector, 'viewport': {'width': width, 'height': height}, 'viewport_box': box})
        page.set_viewport_size({'width': 1440, 'height': 1050})

    def hosting(page, label):
        check(label + ' workspace navigation is available', goto(page, portal) == 200)
        link = page.get_by_role('link', name='Hosting services', exact=True)
        check(label + ' follows the actual Hosting services link', link.count() == 1 and link.get_attribute('href') == portal + '?services=1')
        link.click(); page.wait_for_load_state('load', timeout=30000)
        check(label + ' reaches the installed services page', page.url == portal + '?services=1' and page.get_by_role('heading', name='Hosting services', exact=True).count() == 1)

    try:
        owner, op = context(); alice, ap = context(); bob, bp = context()
        check('Purchase handoff owner signs in through the installed app', login(op, 'purchase-owner@example.invalid', owner_password))
        check('Purchase handoff client signs in through the installed app', login(ap, 'purchase-client1@example.invalid', client_password, True))
        check('Purchase handoff other client signs in through the installed app', login(bp, 'purchase-client2@example.invalid', client_password, True))
        initial = fixture('state')
        check('Joined native purchase starts linked to its exact client', initial['assignment']['state'] == 'linked' and initial['link']['client_id'] == 1 and initial['link']['package_id'] == seed['package_id'])
        check('Owner opens the exact completed agreement page', goto(op, agreement) == 200)
        card = op.locator('[data-hosting-client-handoff]')
        check('Saved-linked card names the expected client package and saved inventory', card.count() == 1 and 'Purchase client 1' in card.inner_text() and 'Package #' + str(seed['package_id']) in card.inner_text() and 'linked to the expected client' in card.inner_text() and 'Last saved inventory:' in card.inner_text())
        check('Owner card contains no provider key or private cost', safe(card.inner_text()))
        capture(op, 'Hosting-Purchase-Owner-Linked', '[data-hosting-client-handoff]')
        review = card.get_by_role('link', name='Review client access', exact=True)
        target = providers + '#provider-package-' + str(seed['package_id'])
        check('Review client access has the exact existing package anchor', review.get_attribute('href') == target)
        review.click(); op.wait_for_load_state('load', timeout=30000)
        package = op.locator('#provider-package-' + str(seed['package_id']))
        check('Actual review navigation reaches the retained package article', op.url == target and package.count() == 1 and seed['domain'] in package.inner_text())
        check('Read-only handoff navigation preserves business and link state', fixture('state') == initial)
        unlink = form(op, 'provider_inventory_unlink', package)
        check('The existing unlink form binds the retained link ID', unlink.count() == 1 and unlink.locator('[name="link_id"]').input_value() == initial['link']['id'])
        unlink.locator('[name="current_password"]').fill(owner_password)
        unlink.get_by_role('button', name='Unlink service', exact=True).click(); op.wait_for_load_state('load', timeout=30000)
        unlinked = fixture('state')
        check('Real owner HTTP unlink records only the intended assignment removal', 'Service link removed.' in op.content() and unlinked['link'] is None and unlinked['assignment']['state'] == 'unlinked' and unlinked['commercial_sha256'] == initial['commercial_sha256'] and unlinked['link_events'] == initial['link_events'] + 1)
        hosting(ap, 'Unlinked client')
        check('Confirmed purchase is hidden from Hosting while explicitly unlinked', seed['domain'] not in ap.content() and 'No hosting services are linked' in ap.content())
        check('Owner returns to the original confirmed order', goto(op, agreement) == 200)
        card = op.locator('[data-hosting-client-handoff]')
        check('Unlinked order card preserves the expected client and honest saved-data guidance', 'Not linked.' in card.inner_text() and 'Purchase client 1' in card.inner_text() and 'Saved observation only.' in card.inner_text())
        capture(op, 'Hosting-Purchase-Owner-Unlinked', '[data-hosting-client-handoff]')
        card.get_by_role('link', name='Review client access', exact=True).click(); op.wait_for_load_state('load', timeout=30000)
        package = op.locator('#provider-package-' + str(seed['package_id']))
        link_form = form(op, 'provider_inventory_link', package)
        check('Guided re-link navigates to the real original package form', op.url == target and link_form.count() == 1 and link_form.locator('[name="profile_id"]').input_value() == seed['profile'] and link_form.locator('[name="package_id"]').input_value() == str(seed['package_id']))
        link_form.locator('[name="client_id"]').select_option('1')
        check('Owner explicitly selects the expected client', link_form.locator('[name="client_id"]').input_value() == '1' and 'Purchase client 1' in link_form.locator('[name="client_id"] option:checked').inner_text())
        capture(op, 'Hosting-Purchase-Owner-Package', '#provider-package-' + str(seed['package_id']))
        # The retained existing helper parses the actual form; no fabricated production endpoint.
        data = env['form_data'](env['form_for'](SimpleNamespace(text=package.inner_html(), url=op.url), 'provider_inventory_link'))
        check('Existing full-browser form helpers bind the selected package', data['profile_id'] == seed['profile'] and data['package_id'] == str(seed['package_id']))
        link_form.locator('[name="current_password"]').fill(owner_password)
        link_form.get_by_role('button', name='Link to client', exact=True).click(); op.wait_for_load_state('load', timeout=30000)
        linked = fixture('state')
        check('Real owner HTTP re-link creates exactly one new expected-client assignment', 'Service linked to the client.' in op.content() and linked['link']['id'] != initial['link']['id'] and linked['link']['client_id'] == 1 and linked['link']['package_id'] == seed['package_id'] and linked['link_events'] == initial['link_events'] + 2 and linked['commercial_sha256'] == initial['commercial_sha256'])
        goto(op, agreement)
        check('Original order card now shows saved linked state', 'linked to the expected client' in op.locator('[data-hosting-client-handoff]').inner_text() and fixture('state')['assignment']['state'] == 'linked')
        hosting(ap, 'Linked client')
        service = ap.locator('article.workspace-card').filter(has=ap.get_by_role('heading', name=seed['domain'], exact=True))
        check('Expected client sees the actual purchased package and saved active observation', service.count() == 1 and 'Active' in service.inner_text() and 'last confirmed read' in service.inner_text())
        check('Client Hosting exposes no private provider mapping or credentials', safe(ap.content()) and seed['mapping'] not in ap.content() and seed['profile'] not in ap.content())
        capture(ap, 'Hosting-Purchase-Client-Hosting')
        hosting(bp, 'Other client')
        check('Other client cannot see the purchased package or controls', seed['domain'] not in bp.content() and 'No hosting services are linked' in bp.content() and linked['link']['id'] not in bp.content())
        response = bob.request.get(portal + '?services=1&client_id=1', timeout=15000)
        check('Forged client selector cannot change authenticated Hosting ownership', response.status == 200 and seed['domain'] not in response.text() and 'No hosting services are linked' in response.text())
        capture(bp, 'Hosting-Purchase-Other-Client')
        check('Final client navigation preserves all business and assignment state', fixture('state') == linked)
        check('Purchase handoff browser has no JavaScript page errors', not errors)
        check('Purchase handoff browser requests only local resources', not blocked)
        return {'scope': 'Browser re-link after separately proven native first link', 'captures': captures, 'linked_client': 1, 'package_id': seed['package_id'], 'commercial_sha256': linked['commercial_sha256']}
    finally:
        for ctx in contexts: ctx.close()
