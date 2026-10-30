"""Actual catalog controller/render/CSRF checks over explicit local identity/business models.
No installed SQL, provider, payment, publication or checkout acceptance is claimed.
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
 'tests/hosting_catalog_variants_browser.py','tests/hosting_catalog_variants_http_fixture.php',
 'site/admin/controllers/catalog.php','site/admin/controllers/catalog_variants.php',
 'site/admin/views/catalog_variants.php','site/admin/controllers/actions.php','site/admin/controllers/auth.php',
 'site/admin/controllers/studio.php','site/app/staff/accounts.php','site/app/providers/settings.php',
 'site/app/providers/catalog_import.php','site/app/providers/catalog_variants.php','site/app/billing/catalog.php','site/app/marketing/growth.php',
 'site/app/plans/documents.php','site/app/hosting/catalog_offers_document.php',
 'site/includes/layout/admin.php','site/includes/layout/hosting_catalog.php',
 'site/includes/security/input.php','site/includes/security/csrf.php','site/includes/security/http.php',
 'site/assets/css/site.css','site/assets/css/admin.css','site/assets/css/premium.css','site/assets/css/growth.css','site/assets/css/hosting-comparison.css','site/includes/layout/public.php',
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
    source_hashes = {p: hashlib.sha256((ROOT / p).read_bytes()).hexdigest() for p in SOURCE_PATHS}

    def check(name, value, metrics=None):
        if any(row['name'] == name for row in checks):
            raise RuntimeError('Duplicate outcome')
        checks.append({'name': name, 'passed': bool(value)})
        if metrics is not None:
            checks[-1]['metrics'] = metrics
        if not value:
            raise AssertionError(name)

    def business(session):
        model = session.state()['model']
        return {k: v for k, v in model.items() if k != 'calls'}

    def retained(html, original):
        hidden, consent, _ = form_fields(html, 'data-catalog-variant-create')
        return all(hidden.get(k) == original[k] for k in ('variant_request_id', 'source_product_id', 'source_revision', 'variant_review_sha256')) and not consent

    password = 'Synthetic catalog variant owner passphrase'
    owner_path = '/owner?view=catalog&id=101'
    with tempfile.TemporaryDirectory(prefix='cwp-catalog-variants-http-') as temporary:
        directory = Path(temporary)
        (directory / 'sessions').mkdir()
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        nonce = os.urandom(24).hex()
        env = {**os.environ, 'CWP_VARIANTS_HTTP_FIXTURE': '1', 'CWP_VARIANTS_SOURCE': str(ROOT), 'CWP_VARIANTS_TEMP': str(directory), 'CWP_VARIANTS_HEALTH': nonce}
        with (directory / 'php.log').open('wb') as log:
            server = subprocess.Popen([php, '-d', 'display_errors=0', '-S', f'127.0.0.1:{port}', str(ROOT / 'tests/hosting_catalog_variants_http_fixture.php')], cwd=ROOT, env=env, stdout=log, stderr=log)
            try:
                health = LocalSession(base)
                for _ in range(100):
                    if server.poll() is not None:
                        raise RuntimeError('Loopback PHP server exited')
                    try:
                        status, _, body = health.request('/health')
                        if status == 200 and body == nonce:
                            break
                        raise RuntimeError('Unexpected fixture response')
                    except URLError:
                        time.sleep(.05)
                else:
                    raise RuntimeError('Loopback fixture did not start')
                check('Loopback source fixture starts without application bootstrap', server.poll() is None)
                owner = LocalSession(base)
                csrf = owner.seed('owner')
                before = business(owner)
                status, _, html = owner.request(owner_path)
                prepare, checked, action = form_fields(html, 'data-catalog-variant-prepare')
                check('Cold variants show explicit preparation on selected imported product without writes', status == 200 and prepare['source_product_id'] == '101' and not checked and business(owner) == before)
                check('Preparation keeps exact selected catalog navigation', action == owner_path)
                for mode in ('anonymous', 'client', 'staff', 'support', 'owner-contact-null', 'owner-client-null', 'owner-support-null'):
                    denied = LocalSession(base)
                    token = denied.seed(mode)
                    initial = business(denied)
                    status, _, html = denied.request(owner_path)
                    check('Variant controls hidden from modeled nonowner or support: ' + mode, 'data-catalog-variant-' not in html and status in (200, 403))
                    status, _, html = denied.request(owner_path, {**prepare, 'csrf': token, 'confirm': '1', 'owner_password': password})
                    check('Actual dispatcher/owner gate prevents preparation side effect: ' + mode, business(denied) == initial and denied.state()['counts'].get('prepare', 0) == 0)
                bad = {**prepare, 'confirm': '1', 'owner_password': password, 'csrf': 'wrong'}
                status, _, html = owner.request(owner_path, bad)
                check('Actual CSRF gate rejects preparation before modeled service', status == 200 and 'Your session expired.' in html and business(owner) == before)
                status, headers, _ = owner.request(owner_path, {**prepare, 'confirm': '1', 'owner_password': password})
                check('Actual preparation action redirects to original product', status == 303 and headers.get('Location') == owner_path and owner.state()['model']['writes']['prepare'] == 1)
                status, _, html = owner.request(owner_path)
                fields, checked, action = form_fields(html, 'data-catalog-variant-create')
                check('New form has original selected revision and review with unchecked consent', status == 200 and len(fields['variant_request_id']) == 32 and fields['source_product_id'] == '101' and fields['source_revision'] == '1' and len(fields['variant_review_sha256']) == 64 and not checked and action == owner_path)
                baseline = business(owner)
                create = {**fields, 'sku': 'EXTRA-YEAR', 'term': 'year', 'owner_password': password, 'confirm': '1'}
                for label, values, error in (
                    ('unchecked confirmation', {**create, 'confirm': ''}, 'Confirm this billing option action'),
                    ('bad CSRF', {**create, 'csrf': 'wrong'}, 'Your session expired.'),
                    ('wrong password', {**create, 'owner_password': 'INCORRECT_PRIVATE_PASSWORD'}, 'Your current password is not correct.'),
                    ('stale review', {**create, 'variant_review_sha256': '0' * 64}, 'This product review changed.'),
                    ('malformed original identity', {**create, 'variant_request_id': '<broken>'}, 'Review the original billing option values.'),
                    ('unsupported period', {**create, 'term': 'once'}, 'Review the original billing option values.'),
                    ('hostile period', {**create, 'term': '<x>'}, 'Review the original billing option values.'),
                    ('malformed source', {**create, 'source_product_id': '01'}, 'Reload the original product'),
                    ('duplicate SKU', {**create, 'sku': 'BASE-MONTH'}, 'That SKU already exists.'),
                ):
                    status, _, html = owner.request(owner_path, values)
                    check('Actual failed POST retains original proof and refuses writes: ' + label, status == 200 and error in html and retained(html, values) and business(owner) == baseline and 'INCORRECT_PRIVATE_PASSWORD' not in html)
                    if label in ('unsupported period', 'hostile period'):
                        from html import escape
                        check('Malformed period remains escaped and selected for review: ' + label, 'value="' + escape(values['term'], quote=True) + '" selected' in html and 'Previously entered:' in html)
                    if label in ('stale review', 'malformed source'):
                        check('Stale source retry is disabled with explicit safe GET review link: ' + label, '<button class="btn gold" disabled>Create billing option</button>' in html and 'href="/owner?view=catalog&amp;id=101">Reload product review</a>' in html)
                    check('Actual failed POST clears owner password and opens retry disclosure: ' + label, 'name="owner_password" type="password" value=""' in html and 'class="catalog-variant-create" open' in html)
                owner.request('/__scenario?name=unavailable')
                status, _, html = owner.request(owner_path, create)
                check('Unavailable source preserves exact original retry in disabled form', 'href="/owner?view=catalog&amp;id=101">Reload product review</a>' in html and status == 200 and retained(html, create) and 'Create billing option</button>' in html and ' disabled' in html and business(owner) == baseline)
                owner.request('/__scenario?name=')
                status, headers, _ = owner.request(owner_path, create)
                created = owner.state()['model']
                check('Actual create controller passes exact original fields and redirects to returned product', status == 303 and headers.get('Location') == '/owner?view=catalog&id=103' and created['calls'][-1]['args'] == [fields['variant_request_id'], 101, 1, fields['variant_review_sha256'], 'EXTRA-YEAR', 'year'])
                check('Modeled returned variant is separate draft with no inherited price image or terms', created['products']['103']['amount_cents'] is None and created['products']['103']['media_id'] is None and created['products']['103']['terms'] == '' and created['products']['103']['term'] == 'year' and created['products']['101'] == baseline['products']['101'] and created['products']['102'] == baseline['products']['102'])
                status, _, html = owner.request('/owner?view=catalog&id=103')
                child, _, child_action = form_fields(html, 'data-catalog-variant-create')
                check('Actual returned product editor selects child without replacing canonical family navigation', status == 200 and child['source_product_id'] == '103' and child_action == '/owner?view=catalog&id=103' and '/owner?view=catalog&amp;id=101' in html and '/owner?view=catalog&amp;id=102' in html)
                owner.request('/__scenario?name=edit-child')
                edited = business(owner)
                status, headers, _ = owner.request(owner_path, create)
                check('Same original POST reaches exact modeled replay without duplicates or commercial reset', status == 303 and headers.get('Location') == '/owner?view=catalog&id=103' and business(owner) == edited)
                status, _, html = owner.request(owner_path, {**create, 'sku': 'CHANGED-YEAR'})
                check('Changed payload on retained request ID is not implicit new creation', status == 200 and 'Original billing option input differs.' in html and business(owner) == edited)
                owner.request('/__scenario?name=edit-source')
                changed = business(owner)
                status, _, html = owner.request(owner_path, {**create, 'variant_request_id': '7' * 32})
                check('Changed source revision preserves stale original proof and creates nothing', '<button class="btn gold" disabled>Create billing option</button>' in html and 'Reload product review</a>' in html and status == 200 and 'This product review changed.' in html and retained(html, {**create, 'variant_request_id': '7' * 32}) and business(owner) == changed)
                before_browser = business(owner)
                with loopback_driver(), sync_playwright() as playwright, closed_browser(playwright, executable) as browser:
                    context = browser.new_context(viewport={'width': 1440, 'height': 1000})
                    page = context.new_page()
                    css_responses = []
                    page.on('response', lambda r: css_responses.append({'path': urlparse(r.url).path, 'status': r.status, 'type': r.headers.get('content-type')}) if '/assets/css/' in r.url else None)
                    page.goto(base + '/__seed?mode=owner')
                    page.goto(base + owner_path)
                    check('Actual source stylesheets load as CSS through loopback only', len(css_responses) == 3 and all(r['status'] == 200 and 'text/css' in (r['type'] or '') for r in css_responses), css_responses)
                    disclosure = page.locator('details.catalog-variant-create')
                    check('Billing option disclosure is closed for a fresh GET', not disclosure.evaluate('(e)=>e.open'))
                    disclosure.locator('summary').focus()
                    page.keyboard.press('Enter')
                    check('Keyboard opens native billing-option disclosure', disclosure.evaluate('(e)=>e.open'))
                    form = page.locator('[data-catalog-variant-create]')
                    original = {e['name']: e['value'] for e in form.locator('input[type=hidden]').evaluate_all('(es)=>es.map(e=>({name:e.name,value:e.value}))')}
                    form.locator('[name=sku]').fill('BROWSER-MONTH')
                    form.locator('[name=term]').select_option('month')
                    form.locator('[name=owner_password]').fill(password)
                    form.locator('button').click()
                    form = page.locator('[data-catalog-variant-create]')
                    check('Real browser unchecked POST retains SKU term and original identity', form.locator('[name=sku]').input_value() == 'BROWSER-MONTH' and form.locator('[name=term]').input_value() == 'month' and all(form.locator(f'[name={k}]').input_value() == v for k, v in original.items() if k != 'csrf') and not form.locator('[name=confirm]').is_checked())
                    check('Real browser retry clears password and remains expanded', form.locator('[name=owner_password]').input_value() == '' and page.locator('details.catalog-variant-create').evaluate('(e)=>e.open'))
                    check('Untrusted product metadata is text in actual admin renderer', page.locator('[data-catalog-variants] script').count() == 0 and '<script>bad()</script>' in page.locator('[data-catalog-variants]').inner_text())
                    for width in (1440, 390):
                        page.set_viewport_size({'width': width, 'height': 1000})
                        metrics = form.evaluate('''e=>({viewport:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+1, inputs:[...e.querySelectorAll('input:not([type=hidden]),select,button')].map(x=>({tag:x.tagName,type:x.type,font:parseFloat(getComputedStyle(x).fontSize),width:x.getBoundingClientRect().width,left:x.getBoundingClientRect().left,right:x.getBoundingClientRect().right}))})''')
                        check('Actual variant form stays within viewport at ' + str(width), not metrics['overflow'] and all(x['left'] >= 0 and x['right'] <= width + 1 for x in metrics['inputs']), metrics)
                        check('Actual variant form input text remains readable at ' + str(width), all(x['font'] >= 14 for x in metrics['inputs'] if x['type'] != 'checkbox'))
                        if preview_dir is not None:
                            preview_dir.mkdir(parents=True, exist_ok=True)
                            target = preview_dir / f'Modeled-Variant-Owner-{width}.png'
                            if target.exists():
                                raise RuntimeError('Preview already exists')
                            form.screenshot(path=str(target))
                    page.goto(base + '/hosting')
                    page.locator('.hosting-comparison-image').evaluate_all('(es)=>Promise.all(es.map(e=>e.decode()))')
                    images = page.locator('.hosting-comparison-image')
                    check('Actual comparison renderer loads distinct selected images', images.count() == 2 and images.nth(0).get_attribute('src') == '/media/index.php?id=' + 'a' * 32 and images.nth(1).get_attribute('src') == '/media/index.php?id=' + 'b' * 32 and images.evaluate_all('(es)=>es.every(e=>e.complete&&e.naturalWidth>0)'))
                    check('Actual comparison renderer preserves independent monthly and annual prices', page.locator('.hosting-comparison-price').all_text_contents() == ['$12.00', '$120.00'] and page.locator('.hosting-comparison-period').all_text_contents() == ['USD per month', 'USD per year'])
                    check('Public metadata is escaped and internal references remain absent', page.locator('script, img[src=x]').count() == 0 and '<img src=x onerror=alert(1)>' in page.locator('.hosting-comparison-table').inner_text() and 'PRIVATE_PROVIDER_REFERENCE' not in page.content())
                    links = page.get_by_role('link', name='Review Hosting', exact=False)
                    destinations = [links.nth(i).get_attribute('href') for i in range(links.count())]
                    for index, (product, amount, cadence, identity, digest) in enumerate(((101, 1200, 'monthly', 'c', 'd'), (102, 12000, 'annually', 'e', 'f'))):
                        query = parse_qs(urlparse(destinations[index]).query)
                        check('Actual choice link binds exact publication and digest for product ' + str(product), query == {'hosting': ['checkout'], 'offer': [identity * 32], 'offer_sha256': [digest * 64]})
                        page.goto(base + destinations[index])
                        payload = json.loads(page.locator('body').inner_text())
                        check('Selected renderer link arrives at corresponding modeled product destination ' + str(product), payload['product_id'] == product and payload['amount_cents'] == amount and payload['cadence'] == cadence)
                    page.goto(base + '/hosting')
                    scroll = page.locator('.hosting-comparison-scroll')
                    scroll.focus()
                    page.keyboard.press('ArrowRight')
                    page.wait_for_timeout(150)
                    scroll_metrics = scroll.evaluate('(e)=>({left:e.scrollLeft,width:e.clientWidth,full:e.scrollWidth,page:document.documentElement.scrollWidth,viewport:innerWidth})')
                    check('Mobile comparison remains keyboard-scrollable without page overflow', scroll_metrics['left'] > 0 and scroll_metrics['page'] <= scroll_metrics['viewport'] + 1, scroll_metrics)
                    if preview_dir is not None:
                        target = preview_dir / 'Modeled-Variant-Public-390.png'
                        if target.exists():
                            raise RuntimeError('Preview already exists')
                        page.screenshot(path=str(target), full_page=True)
                    context.close()
                check('Browser renderer and choice navigation add no modeled business provider payment or publication effects', business(owner) == before_browser and before_browser['provider_calls'] == 0 and before_browser['invoices'] == 0 and before_browser['publications'] == 2)
                check('All actual source files remain byte-identical through local checks', source_hashes == {p: hashlib.sha256((ROOT / p).read_bytes()).hexdigest() for p in SOURCE_PATHS})
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
    result = {'passed': False, 'scope': 'Actual extracted controllers/renderers/CSRF over modeled identity, SQL and business seams; local Chromium; no native or installed acceptance.', 'checks': []}
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
