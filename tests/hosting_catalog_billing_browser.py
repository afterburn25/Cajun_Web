"""Actual billing selector/query/read/controller/render bodies over explicit local business models.
Loopback HTTP and closed-proxy Chromium only; no installed SQL or purchase acceptance.
"""
import argparse
from contextlib import contextmanager
import hashlib
from http.cookiejar import CookieJar
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import threading
import time
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlparse, parse_qs
from urllib.request import build_opener, HTTPCookieProcessor, HTTPRedirectHandler, ProxyHandler, Request

ROOT = Path(__file__).resolve().parents[1]
SOURCE_PATHS = (
 'tests/hosting_catalog_billing_browser.py','tests/hosting_catalog_billing_http_fixture.php',
 'site/app/hosting/catalog_offers_context.php','site/app/hosting/catalog_offers.php',
 'site/app/hosting/catalog_offers_document.php','site/app/hosting/catalog_checkout.php',
 'site/pages/services/hosting.php','site/includes/layout/hosting_catalog.php',
 'site/portal/controllers/hosting_catalog.php','site/portal/views/hosting_catalog.php',
 'site/portal/controllers/hosting_checkout.php','site/portal/views/hosting_checkout.php',
 'site/app/plans/forms.php','site/app/plans/documents.php','site/app/plans/records.php',
 'site/app/marketing/growth.php','site/includes/layout/public.php','site/portal/controllers/router.php',
 'site/includes/urls.php',
 'site/includes/security/input.php','site/includes/security/csrf.php','site/includes/security/http.php',
 'site/assets/css/site.css','site/assets/css/premium.css','site/assets/css/growth.css','site/assets/css/hosting-comparison.css',
)

class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, new_url):
        return None


class LocalSession:
    def __init__(self, base):
        self.base = base
        self.opener = build_opener(ProxyHandler({}), HTTPCookieProcessor(CookieJar()), NoRedirect())

    def request(self, path, fields=None):
        if not path.startswith('/') or path.startswith('//'):
            raise ValueError('Only the selected loopback fixture is permitted')
        request = Request(self.base + path, data=None if fields is None else urlencode(fields).encode())
        try:
            response = self.opener.open(request, timeout=10)
        except HTTPError as error:
            return error.code, dict(error.headers), error.read().decode('utf-8')
        with response:
            return response.status, dict(response.headers), response.read().decode('utf-8')

    def seed(self, mode):
        status, _, text = self.request('/__seed?' + urlencode({'mode': mode}))
        if status != 200:
            raise RuntimeError('Synthetic session setup failed')
        return json.loads(text)['csrf']

    def state(self):
        status, _, text = self.request('/__state')
        if status != 200:
            raise RuntimeError('Synthetic state read failed')
        return json.loads(text)


def form_fields(html, marker):
    class Form(HTMLParser):
        def __init__(self):
            super().__init__()
            self.active = False
            self.fields = {}
            self.checked = False
            self.action = None

        def handle_starttag(self, tag, pairs):
            attrs = dict(pairs)
            if tag == 'form':
                self.active = marker in attrs
                if self.active:
                    self.action = attrs.get('action', '')
            elif self.active and tag == 'input':
                if attrs.get('type') == 'hidden':
                    self.fields[attrs['name']] = attrs.get('value', '')
                elif attrs.get('type') == 'checkbox':
                    self.checked = self.checked or 'checked' in attrs

        def handle_endtag(self, tag):
            if tag == 'form':
                self.active = False
    parser = Form()
    parser.feed(html)
    return parser.fields, parser.checked, parser.action


@contextmanager
def closed_browser(playwright, executable):
    """Reject all external HTTP/CONNECT. Only the explicit localhost bypass is usable."""
    class Deny(BaseHTTPRequestHandler):
        def log_message(self, *args):
            pass

        def refuse(self):
            try:
                self.send_error(502)
            except OSError:
                pass
        do_CONNECT = refuse
        do_GET = refuse
        do_POST = refuse
    proxy = ThreadingHTTPServer(('127.0.0.1', 0), Deny)
    proxy.daemon_threads = True
    thread = threading.Thread(target=proxy.serve_forever, daemon=True)
    thread.start()
    browser = None
    try:
        options = {'headless': True, 'proxy': {'server': f'http://127.0.0.1:{proxy.server_port}', 'bypass': '127.0.0.1,localhost'}}
        if executable:
            options['executable_path'] = executable
        browser = playwright.chromium.launch(**options)
        yield browser
    finally:
        if browser is not None:
            browser.close()
        proxy.shutdown()
        proxy.server_close()
        thread.join(timeout=5)


@contextmanager
def loopback_driver():
    # Keep Playwright from appending <-loopback> after the explicit local bypass.
    key = 'PLAYWRIGHT_DISABLE_FORCED_CHROMIUM_PROXIED_LOOPBACK'
    previous = os.environ.get(key)
    os.environ[key] = '1'
    try:
        yield
    finally:
        if previous is None:
            os.environ.pop(key, None)
        else:
            os.environ[key] = previous


def run(php, executable, checks, preview_dir=None):
    from playwright.sync_api import sync_playwright
    source_paths = list(SOURCE_PATHS)
    if (ROOT / 'site/app/hosting/catalog_billing.php').is_file():
        source_paths.append('site/app/hosting/catalog_billing.php')
    source_hashes = {p: hashlib.sha256((ROOT / p).read_bytes()).hexdigest() for p in source_paths}

    def check(name, value, metrics=None):
        if any(row['name'] == name for row in checks):
            raise RuntimeError('Duplicate outcome')
        checks.append({'name': name, 'passed': bool(value)})
        if metrics is not None:
            checks[-1]['metrics'] = metrics
        if not value:
            raise AssertionError(name)

    class Links(HTMLParser):
        def __init__(self, html):
            super().__init__()
            self.rows = []
            self.active = None
            self.feed(html)

        def handle_starttag(self, tag, pairs):
            if tag == 'a':
                self.active = dict(pairs)
                self.active['text'] = ''

        def handle_data(self, data):
            if self.active is not None:
                self.active['text'] += data

        def handle_endtag(self, tag):
            if tag == 'a' and self.active is not None:
                self.rows.append(self.active)
                self.active = None

    def choice_queries(html):
        return [parse_qs(urlparse(row.get('href', '')).query) for row in Links(html).rows if parse_qs(urlparse(row.get('href', '')).query).get('hosting') == ['checkout']]

    def offer_ids(html):
        return [int(q['offer'][0], 16) for q in choice_queries(html)]

    def page_link(html, page, field):
        return [r for r in Links(html).rows if parse_qs(urlparse(r.get('href', '')).query).get(field) == [str(page)]]

    with tempfile.TemporaryDirectory(prefix='cwp-hosting-billing-http-') as temporary:
        directory = Path(temporary)
        (directory / 'sessions').mkdir()
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        nonce = os.urandom(24).hex()
        env = {**os.environ, 'CWP_BILLING_HTTP_FIXTURE': '1', 'CWP_BILLING_SOURCE': str(ROOT), 'CWP_BILLING_TEMP': str(directory), 'CWP_BILLING_HEALTH': nonce}
        with (directory / 'php.log').open('wb') as log:
            server = subprocess.Popen([php, '-d', 'display_errors=0', '-S', f'127.0.0.1:{port}', str(ROOT / 'tests/hosting_catalog_billing_http_fixture.php')], cwd=ROOT, env=env, stdout=log, stderr=log)
            try:
                client = LocalSession(base)
                for _ in range(100):
                    if server.poll() is not None:
                        raise RuntimeError('Loopback PHP server exited')
                    try:
                        status, _, body = client.request('/health')
                        if status == 200 and body == nonce:
                            break
                        raise RuntimeError('Unexpected fixture response')
                    except URLError:
                        time.sleep(.05)
                else:
                    raise RuntimeError('Loopback fixture did not start')
                client.seed('client')
                business_before = client.state()['business']
                status, headers, body = client.request('/hosting')
                check('Actual public default preserves all-plan ordering and first twenty offers', status == 200 and offer_ids(body) == list(range(101, 121)))
                check('Public comparison remains no-store and does not expose internal model diagnostics', 'no-store' in headers.get('Cache-Control', '') and 'PRIVATE_' not in body)
                check('Default selector visibly marks all periods rather than silently choosing monthly', any(r.get('aria-current') in ('page', 'true') and parse_qs(urlparse(r.get('href', '')).query).get('billing', ['all']) == ['all'] for r in Links(body).rows))
                for billing, first, last in [('monthly', 101, 120), ('annually', 125, 144)]:
                    status, _, body = client.request('/hosting?' + urlencode({'billing': billing}))
                    check('Actual public reader selects ' + billing + ' before the twenty-offer cutoff', status == 200 and offer_ids(body) == list(range(first, last + 1)))
                    selection = client.state()['selection']
                    check('Actual SQL binds the closed local product term for ' + billing, selection['args'] == ['month' if billing == 'monthly' else 'year'] and selection['offset'] == 0)
                    check('Public next-page link preserves ' + billing, any(parse_qs(urlparse(r['href']).query).get('billing') == [billing] for r in page_link(body, 2, 'hosting_page')))
                status, _, annual = client.request('/hosting?billing=annually&hosting_page=2')
                check('Annual public second page contains only the three remaining annual products', status == 200 and offer_ids(annual) == [145, 146, 147])
                check('Public previous link preserves annual filter and the correct page', any(parse_qs(urlparse(r['href']).query).get('billing') == ['annually'] for r in page_link(annual, 1, 'hosting_page')))
                check('Annual pricing is the full independent yearly amount rather than monthly equivalent', '$120.45' in annual and 'USD per year' in annual and '$10.' not in annual)
                for query in ['billing%5B%5D=monthly', 'billing=Monthly', 'billing=once', 'billing=%20monthly', 'billing=', 'hosting_page%5B%5D=2', 'hosting_page=0', 'hosting_page=01', 'hosting_page=100001']:
                    before = client.state()['counts'].get('sql_read', 0)
                    status, _, body = client.request('/hosting?' + query)
                    check('Public malformed query refuses before catalog reads: ' + query, status == 404 and not offer_ids(body) and client.state()['counts'].get('sql_read', 0) == before)
                status, _, portal = client.request('/portal/index.php?hosting=offers&billing=annually&p=1')
                check('Actual portal controller applies annual selection before pagination', status == 200 and offer_ids(portal) == list(range(125, 145)))
                check('Portal next-page link preserves annual selection', any(parse_qs(urlparse(r['href']).query).get('billing') == ['annually'] for r in page_link(portal, 2, 'p')))
                status, _, portal2 = client.request('/portal/index.php?hosting=offers&billing=annually&p=2')
                check('Portal second page preserves exact selected annual products', status == 200 and offer_ids(portal2) == [145, 146, 147])
                for query in ['billing%5B%5D=monthly', 'billing=year', 'billing=ANNUALLY', 'p%5B%5D=2', 'p=0']:
                    before = client.state()['counts'].get('origin_read', 0)
                    status, _, body = client.request('/portal/index.php?hosting=offers&' + query)
                    check('Portal malformed selector refuses without offer projection: ' + query, status == 404 and not offer_ids(body) and client.state()['counts'].get('origin_read', 0) == before)
                for product in [101, 125, 147]:
                    cadence = 'monthly' if product < 125 else 'annually'
                    publication = f'{product:032x}'
                    digest = hashlib.sha256(('publication-' + str(product)).encode()).hexdigest()
                    _, _, body = client.request('/hosting?' + urlencode({'billing': cadence, 'hosting_page': 2 if product == 147 else 1}))
                    query = next(q for q in choice_queries(body) if q['offer'] == [publication])
                    check('Choice link retains exact original publication and hash for ' + str(product), query == {'hosting': ['checkout'], 'offer': [publication], 'offer_sha256': [digest]})
                    status, _, selection = client.request('/portal/index.php?' + urlencode({k: v[0] for k, v in query.items()}))
                    check('Actual checkout selection retains the selected offer without creating a review for ' + str(product), status == 200 and 'name="publication_id" value="' + publication + '"' in selection and 'name="publication_sha256" value="' + digest + '"' in selection and 'Plan ' + str(product) in selection)
                    check('Selected checkout shows independent amount and cadence for ' + str(product), ('$' + f'{(1200 + product - 100 if product < 125 else 12000 + product - 100) / 100:.2f}' + ' USD') in selection and ('per month' if cadence == 'monthly' else 'per year') in selection)
                status, _, changed = client.request('/portal/index.php?' + urlencode({'hosting': 'checkout', 'offer': f'{125:032x}', 'offer_sha256': '0' * 64}))
                check('Changed publication hash cannot produce a checkout selection or replacement identity', status == 200 and 'This hosting offer changed.' in changed and 'name="publication_id"' not in changed)
                for mode in ['support', 'stale', 'anonymous']:
                    client.seed(mode)
                    status, _, body = client.request('/portal/index.php?hosting=offers&billing=monthly')
                    check('Existing actual portal authority rejects ' + mode + ' session', status == 403 and not offer_ids(body))
                client.seed('contact-null')
                status, _, body = client.request('/portal/index.php?' + urlencode({'hosting': 'checkout', 'offer': f'{125:032x}', 'offer_sha256': hashlib.sha256(b'publication-125').hexdigest()}))
                check('Actual checkout contact-presence guard refuses even a null marker', status == 200 and 'Sign in as the primary client' in body and 'name="publication_id"' not in body)
                client.seed('client2')
                status, _, body = client.request('/portal/index.php?hosting=offers&billing=annually')
                check('Another authenticated client may view the same public offer selection', status == 200 and offer_ids(body) == list(range(125, 145)))
                client.seed('client')
                client.request('/__scenario?name=annual-empty')
                status, _, body = client.request('/hosting?billing=annually')
                check('Empty annual selection never substitutes monthly products', status == 200 and not offer_ids(body) and 'per month' not in body)
                client.request('/__scenario?name=unavailable')
                status, _, body = client.request('/hosting?billing=annually')
                check('Public read failure remains unavailable without private diagnostics or fallback offers', status == 200 and not offer_ids(body) and 'PRIVATE_' not in body and 'unavailable' in body.lower())
                status, _, body = client.request('/portal/index.php?hosting=offers&billing=annually')
                check('Portal read failure preserves selector and safe unavailable state', status == 200 and not offer_ids(body) and 'PRIVATE_' not in body and 'temporarily unavailable' in body)
                client.request('/__scenario?name=one-unavailable')
                status, _, body = client.request('/hosting?billing=annually')
                check('Unavailable selected publication does not introduce another period', status == 200 and offer_ids(body) == list(range(126, 145)) and 'temporarily unavailable' in body)
                client.request('/__scenario?name=')
                with loopback_driver(), sync_playwright() as playwright, closed_browser(playwright, executable) as browser:
                    for width in [1440, 390]:
                        context = browser.new_context(viewport={'width': width, 'height': 1000})
                        page = context.new_page()
                        page.goto(base + '/hosting?billing=annually&hosting_page=2')
                        page.locator('.hosting-comparison-table').wait_for()
                        check('Chromium annual comparison keeps three exact choices at ' + str(width), page.locator('.hosting-comparison-price').all_text_contents() == ['$120.45', '$120.46', '$120.47'] and page.locator('.hosting-comparison-period').all_text_contents() == ['USD per year'] * 3)
                        images = page.locator('.hosting-comparison-image')
                        images.first.scroll_into_view_if_needed()
                        page.wait_for_function('Array.from(document.querySelectorAll(".hosting-comparison-image")).every(e=>e.complete&&e.naturalWidth>0)')
                        check('Independent annual artwork is loaded at ' + str(width), images.count() == 3 and all(parse_qs(urlparse(images.nth(i).get_attribute('src')).query) == {'id': ['b' * 32]} for i in range(3)))
                        check('Actual renderer escapes plan and support metadata at ' + str(width), page.locator('script,img[src=x]').count() == 0 and '<img src=x onerror=alert(1)>' in page.locator('.hosting-comparison-table').inner_text())
                        monthly = page.locator('a[href*="billing=monthly"]').first
                        monthly.focus()
                        page.keyboard.press('Enter')
                        page.wait_for_url(lambda url: parse_qs(urlparse(url).query).get('billing') == ['monthly'])
                        check('Billing filter is keyboard navigable and resets pagination at ' + str(width), parse_qs(urlparse(page.url).query).get('hosting_page', ['1']) == ['1'] and page.locator('.hosting-comparison-period').first.inner_text() == 'USD per month')
                        metrics = page.locator('a[href*="billing=annually"]').first
                        measured = metrics.evaluate('(e)=>({font:parseFloat(getComputedStyle(e).fontSize),height:e.getBoundingClientRect().height,width:e.getBoundingClientRect().width,page:document.documentElement.scrollWidth,viewport:innerWidth})')
                        check('Billing links are readable and stay within viewport at ' + str(width), measured['font'] >= 14 and measured['height'] >= 40 and measured['page'] <= measured['viewport'] + 1, measured)
                        page.goto(base + '/hosting?billing=annually&hosting_page=2')
                        if width == 390:
                            scroll = page.locator('.hosting-comparison-scroll').first
                            scroll.focus()
                            page.keyboard.press('ArrowRight')
                            page.wait_for_timeout(150)
                            check('Mobile comparison is keyboard scrollable without changing offer identity', scroll.evaluate('(e)=>e.scrollLeft') > 0 and len(offer_ids(page.content())) == 3)
                        if preview_dir is not None:
                            preview_dir.mkdir(parents=True, exist_ok=True)
                            target = preview_dir / ('Modeled-Billing-Public-' + str(width) + '.png')
                            if target.exists():
                                raise RuntimeError('Preview already exists')
                            page.screenshot(path=str(target), full_page=True)
                        context.close()
                state = client.state()
                check('All filter and exact-selection GETs preserve modeled business counters', state['business'] == business_before)
                check('No actual read path in any HTTP or browser session reached a business SQL invoice review request or provider writer', state['forbidden_calls'] == [])
                check('Source bytes remain unchanged throughout the browser run', source_hashes == {p: hashlib.sha256((ROOT / p).read_bytes()).hexdigest() for p in source_paths})
                return source_hashes
            finally:
                server.terminate()
                server.wait(timeout=10)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', default=shutil.which('php'))
    parser.add_argument('--browser')
    parser.add_argument('--report', type=Path, required=True)
    parser.add_argument('--preview-dir', type=Path)
    args = parser.parse_args()
    if not args.php or args.report.exists():
        raise SystemExit('Select PHP and a new report path')
    result = {'passed': False, 'scope': 'Actual source query/read/controller/render bodies over explicit SQL, publication and client models; local loopback HTTP and closed-proxy Chromium; no native SQL or installed checkout acceptance.', 'checks': []}
    try:
        result['source_sha256'] = run(args.php, args.browser, result['checks'], args.preview_dir)
        result['passed'] = True
    except Exception as error:
        result['error'] = type(error).__name__
    args.report.parent.mkdir(parents=True, exist_ok=True)
    with args.report.open('x', encoding='utf-8', newline='\n') as stream:
        json.dump(result, stream, indent=2)
        stream.write('\n')
    print(json.dumps({'passed': result['passed'], 'checks': len(result['checks']), 'report': str(args.report)}))
    raise SystemExit(0 if result['passed'] else 1)


if __name__ == '__main__':
    main()
