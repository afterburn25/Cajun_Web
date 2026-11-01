"""Actual packaged confirmation UI; provider reads use the existing loopback TLS fixture."""
import time
from playwright.sync_api import expect
from release_cases import db
from cloud_assignment_fixture import age_unprotected_refreshes
from owner_browser_forms import refuse_owner_action


def run_browser(env, browser, owner, client, other, profile):
    base, qa, check = env['BASE'], env['QA'], env['check']
    soup, token, form_data, provider = (env[k] for k in ['soup', 'token', 'form_data', 'provider_server'])
    url = base + '/existing/admin/index.php?view=provider-settings&section=cloud'
    inventory_url = base + '/existing/admin/index.php?view=provider-settings&section=services'
    services = base + '/existing/portal/index.php?services=1'
    request = db('SELECT id FROM ci_provider_operation_grants WHERE operation="cloudsites.create" ORDER BY seq DESC LIMIT 1')[0]['id']
    ledger = db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['cloudsite:' + request])[0]

    def post(action, **fields):
        target = inventory_url if action in ('provider_inventory_refresh', 'provider_inventory_unlink') else url
        return owner.post(target, data={'csrf': token(owner.get(target)), 'action': action,
                                    'profile_id': profile['id'], 'revision': str(profile['revision']),
                                    **fields})

    calls = provider.calls
    check('Cloud confirmation preparation form is explicit and read-only', soup(owner.get(url)).select_one('[data-cloud-confirmation-prepare]') is not None and provider.calls == calls)
    post('provider_inventory_cloud_confirmation_prepare')
    check('Preparing cloud confirmation makes no provider call', provider.calls == calls)
    check('Cloud confirmation does not appear from acknowledgement alone', soup(owner.get(url)).select_one('[data-cloud-confirmation]') is None)
    # Advance only this disposable fixture past its documented refresh cooldown.
    age_unprotected_refreshes(60, profile['id'])
    time.sleep(1)
    post('provider_inventory_refresh')
    def confirmation_form(response, action):
        found = [f for f in soup(response).select('form[data-cloud-confirmation]')
                 if f.select_one('[name=action][value="' + action + '"]') is not None
                 and f.select_one('[name=request_id][value="' + request + '"]') is not None]
        if len(found) != 1:
            raise AssertionError('Expected one confirmation form for the accepted cloud request')
        return found[0]
    confirm = 'provider_inventory_cloud_confirmation_confirm'
    form = confirmation_form(owner.get(url), confirm)
    check('Fresh hosting read exposes exact allocated package confirmation', form is not None and ('#' + ledger['external_id']) in form.text and 'does not verify current server location' in form.text)
    fields = form_data(form); fields.update(reviewed='1')
    calls = provider.calls
    for label, delta in [('CSRF', {'csrf': 'bad'}),
                         ('explicit review', {'reviewed': '0'}), ('review fingerprint', {'review': 'a' * 64}),
                         ('refresh identity', {'refresh_id': 'b' * 32})]:
        owner.post(url, data={**fields, **delta})
        check('Cloud confirmation refuses invalid ' + label, not db('SELECT id FROM ci_provider_cloud_confirmations') and provider.calls == calls)
    refuse_owner_action({**env, 'form_for': confirmation_form}, owner, url, confirm, {'reviewed': '1'})
    check('Cloud confirmation requires current owner authority', not db('SELECT id FROM ci_provider_cloud_confirmations') and provider.calls == calls)
    response = owner.post(url, data=fields)
    check('Owner confirms exact package and client through actual form', 'Allocated package observed and assigned' in response.text and len(db('SELECT id FROM ci_provider_cloud_confirmations')) == 1)
    check('Confirmation keeps provider acknowledgement immutable without another provider call', db('SELECT * FROM ci_provider_operations WHERE idempotency_key=?', ['cloudsite:' + request])[0] == ledger and provider.calls == calls)
    owner.post(url, data=fields)
    check('Cloud confirmation replay cannot duplicate assignment or history', len(db('SELECT id FROM ci_provider_cloud_confirmations')) == 1 and provider.calls == calls)
    own, foreign = client.get(services), other.get(services)
    check('Only original client sees bounded cloud confirmation', 'Allocated package observed and assigned' in own.text and 'website health and current server location are not verified' in own.text and 'cloud-website.example' not in foreign.text)
    for session, title, target, selector in [(owner, 'Cloud-Confirmation-Owner', url, '[data-cloud-confirmed]'),
                                             (client, 'Cloud-Confirmation-Client', services, '[data-client-cloud-request]')]:
        context = browser.new_context(viewport={'width': 1440, 'height': 1050})
        context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in session.cookies])
        page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.goto(target); page.evaluate('document.fonts.ready')
            expect(page.locator(selector)).to_be_visible(); page.screenshot(path=str(qa / (title + '-' + label + '.png')), full_page=True)
            check(title + ' ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        check(title + ' has no script errors', not errors); context.close()
    link = db('SELECT id FROM ci_provider_package_links WHERE profile_id=? AND package_id=?', [profile['id'], ledger['external_id']])[0]['id']
    post('provider_inventory_unlink', link_id=link)
    check('Removing cloud package assignment retains history and changes its label', 'Package was observed; assignment changed' in owner.get(url).text and len(db('SELECT id FROM ci_provider_cloud_confirmations')) == 1)
    owner.post(url, data=fields)
    check('Old confirmation cannot restore an explicitly removed package link', not db('SELECT id FROM ci_provider_package_links WHERE id=?', [link]) and provider.calls == calls)
