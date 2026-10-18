"""Packaged owner cloud-provider observations; loopback verified HTTPS and real Chromium only."""
import time
import requests
from playwright.sync_api import expect
from release_cases import db
from staff_cases import OWNER, STAFF


def run_browser(env, browser):
    base, qa, check, provider = (env[k] for k in ['BASE', 'QA', 'check', 'provider_server'])
    soup, token, form_data, submit = (env[k] for k in ['soup', 'token', 'form_data', 'submit'])
    admin = base + '/existing/admin/index.php'; url = admin + '?view=provider-settings'
    portal = base + '/existing/portal/index.php'; services = portal + '?services=1'
    db('DELETE FROM ci_rate_limits')
    owner = requests.Session(); submit(owner, admin + '?view=login', 'login', {'email': 'owner@example.invalid', 'password': OWNER})
    member = requests.Session(); submit(member, admin + '?view=login', 'login', {'email': 'request-staff@example.invalid', 'password': STAFF})
    client = requests.Session(); submit(client, portal, 'login', {'email': 'alice@example.invalid', 'password': 'Synthetic client passphrase 2026'})
    profile = db('SELECT id,revision FROM ci_provider_profiles WHERE provider_code=?', ['twenty-i'])[0]
    prepare = 'provider_inventory_cloud_providers_prepare'; refresh = 'provider_inventory_cloud_providers_refresh'
    def post(action, **fields):
        return owner.post(url, data={'csrf': token(owner.get(url)), 'action': action, 'profile_id': profile['id'], 'revision': str(profile['revision']), 'current_password': OWNER, **fields})
    def cool():
        last = db('SELECT MAX(started_epoch) AS epoch FROM ci_provider_refreshes WHERE profile_id=?', [profile['id']])[0]['epoch']
        time.sleep(max(0, int(last or 0) + 31 - time.time()))
    calls, posts = provider.calls, list(provider.posts)
    response = owner.get(url)
    check('Cloud providers GET offers explicit preparation without schema or network changes', prepare in response.text and not db('SHOW TABLES LIKE "ci_provider_observation_cloud_providers"') and provider.calls == calls)
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'})]:
        post(prepare, **fields)
        check('Cloud providers preparation refuses invalid ' + label, not db('SHOW TABLES LIKE "ci_provider_observation_cloud_providers"') and provider.calls == calls)
    post(prepare)
    check('Cloud providers preparation is explicit and local', db('SHOW TABLES LIKE "ci_provider_observation_cloud_providers"') != [] and db('SELECT * FROM ci_provider_observation_cloud_providers') == [] and provider.calls == calls)
    check('Cloud providers administrator and support views are refused', member.get(url).status_code == 403 and owner.get(url + '&support=fixture').status_code == 403)
    for label, fields in [('CSRF', {'csrf': 'invalid'}), ('password', {'current_password': 'wrong'}), ('revision', {'revision': '999999'}), ('profile', {'profile_id': 'a' * 32})]:
        post(refresh, **fields)
        check('Cloud providers refresh refuses invalid ' + label, not db('SELECT * FROM ci_provider_observation_cloud_providers') and provider.calls == calls)
    cool(); provider.cloud_providers = ['provider-A', '007', 'Provider_A', 'p' * 128]
    response = post(refresh); saved = db('SELECT * FROM ci_provider_observation_cloud_providers')[0]
    section = soup(response).select_one('#cloud-providers')
    check('Cloud providers form sends one exact fixed GET and saves returned identities', provider.calls == calls + 1 and provider.get_paths[-1] == '/reseller/*/cloudProviders' and [node.text for node in section.select('li')] == provider.cloud_providers)
    check('Cloud providers current observation has date and bounded capability wording', 'Saved observation:' in section.text and 'Observed recently under the current connection' in section.text and 'server sizes, locations, prices or permission to place an order' in section.text)
    post(refresh)
    check('Cloud providers repeated refresh obeys shared cooldown without replacing evidence', provider.calls == calls + 1 and db('SELECT * FROM ci_provider_observation_cloud_providers') == [saved])
    check('Cloud provider identifiers stay absent from client portal', 'provider-A' not in client.get(services).text and 'Cloud providers' not in client.get(services).text)
    def capture(session, title, target, selector):
        context = browser.new_context(viewport={'width': 1440, 'height': 1050})
        context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in session.cookies])
        page = context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        for width, height, label in [(1440, 1050, 'Desktop'), (320, 740, 'Mobile')]:
            page.set_viewport_size({'width': width, 'height': height}); page.goto(target); page.evaluate('document.fonts.ready'); expect(page.locator(selector)).to_be_visible()
            page.screenshot(path=str(qa / (title + '-' + label + '.png')), full_page=True)
            check(title + ' ' + label + ' fits viewport', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        check(title + ' has no script errors', not errors); context.close()
    capture(owner, 'Cloud-Providers-Owner', url, '#cloud-providers')
    capture(client, 'Cloud-Providers-Client', services, 'main')
    cool(); provider.cloud_providers_mode = 'object'; response = post(refresh)
    section = soup(response).select_one('#cloud-providers')
    check('Cloud providers malformed later read retains prior list as historical', 'Historical observation only' in section.text and 'provider-A' in section.text and db('SELECT * FROM ci_provider_observation_cloud_providers') == [saved] and provider.calls == calls + 2)
    capture(owner, 'Cloud-Providers-Historical', url, '#cloud-providers')
    cool(); provider.cloud_providers_mode = 'success'; provider.cloud_providers = []; response = post(refresh)
    check('Cloud providers complete empty response is saved without an absence claim', 'No provider identifiers were returned in this observation' in response.text and 'This does not establish that services have been cancelled' in response.text and len(db('SELECT * FROM ci_provider_observation_cloud_providers')) == 2 and provider.calls == calls + 3)
    counter = db('SELECT value FROM ci_settings WHERE name=?', ['provider_cloud_providers_started_count'])[0]['value']
    db('DELETE FROM ci_settings WHERE name=?', ['provider_cloud_providers_started_count'])
    try:
        response = owner.get(url)
        check('Cloud providers missing history shows recovery with no usable refresh form', 'Cloud provider count history needs recovery' in response.text and not soup(response).select_one('#cloud-providers [name=action]') and provider.calls == calls + 3)
    finally:
        db('INSERT INTO ci_settings (name,value) VALUES (?,?)', ['provider_cloud_providers_started_count', counter])
    check('Cloud providers exact history restoration returns saved observation', 'No provider identifiers were returned' in owner.get(url).text)
    check('Cloud provider discovery never dispatches a provider write or emits private response bodies', provider.posts == posts and not provider.errors and 'private cloud provider response' not in response.text)
    owner.close(); member.close(); client.close()
