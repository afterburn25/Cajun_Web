"""Local HTTP/Chromium checks for actual HTTPS-request UI/controller functions.

The PHP companion uses explicit session/business models. No application SQL,
installed identity, provider request, certificate or HTTPS-health acceptance.
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
from urllib.parse import urlencode
from urllib.request import build_opener, HTTPCookieProcessor, HTTPRedirectHandler, ProxyHandler, Request

ROOT = Path(__file__).resolve().parents[1]
SOURCE_PATHS = (
    'tests/client_force_ssl_requests_browser.py',
    'tests/client_force_ssl_requests_http_fixture.php',
    'site/app/providers/force_ssl_requests_views.php',
    'site/app/providers/force_ssl_requests.php',
    'site/app/providers/settings.php',
    'site/admin/controllers/force_ssl_requests.php',
    'site/portal/controllers/force_ssl_requests.php',
    'site/admin/controllers/router.php', 'site/admin/controllers/actions.php',
    'site/admin/controllers/auth.php', 'site/portal/controllers/router.php',
    'site/portal/controllers/actions.php', 'site/includes/layout/admin.php',
    'site/includes/security/input.php', 'site/includes/security/csrf.php',
    'site/includes/security/http.php', 'site/assets/css/site.css',
    'site/assets/css/admin.css', 'site/assets/css/premium.css', 'site/assets/css/growth.css',
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
    source_hashes = {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in SOURCE_PATHS}

    def check(name, value, metrics=None):
        if any(row['name'] == name for row in checks):
            raise RuntimeError('Duplicate named outcome')
        checks.append({'name': name, 'passed': bool(value)})
        if metrics is not None:
            checks[-1]['metrics'] = metrics
        if not value:
            raise AssertionError('Named local HTTP/browser check failed')

    def fields(form):
        return {row['name']: row['value'] for row in form.locator('input[type=hidden]').evaluate_all('(els)=>els.map(e=>({name:e.name,value:e.value}))')}

    def state(context):
        return context.request.get(base + '/__state').json()

    password = 'Synthetic HTTPS request owner passphrase'
    client_path = '/portal?https_requests=1&https_page=2'
    owner_path = '/owner?view=https-requests&https_page=2'
    with tempfile.TemporaryDirectory(prefix='cwp-https-requests-http-') as temporary:
        directory = Path(temporary)
        (directory / 'sessions').mkdir()
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        nonce = os.urandom(24).hex()
        env = {**os.environ, 'CWP_HTTPS_REQUEST_HTTP_FIXTURE': '1', 'CWP_HTTPS_REQUEST_SOURCE': str(ROOT), 'CWP_HTTPS_REQUEST_TEMP': str(directory), 'CWP_HTTPS_REQUEST_HEALTH': nonce}
        with (directory / 'php.log').open('wb') as log:
            server = subprocess.Popen([php, '-d', 'display_errors=0', '-S', f'127.0.0.1:{port}', str(ROOT / 'tests/client_force_ssl_requests_http_fixture.php')], cwd=ROOT, env=env, stdout=log, stderr=log)
            try:
                health = LocalSession(base)
                for _ in range(100):
                    if server.poll() is not None:
                        raise RuntimeError('Loopback PHP server exited')
                    try:
                        status, _, body = health.request('/health')
                        if status == 200 and body == nonce:
                            break
                        raise RuntimeError('Unexpected local fixture response')
                    except URLError:
                        time.sleep(.05)
                else:
                    raise RuntimeError('Loopback PHP fixture did not start')
                check('Loopback fixture starts with exact source functions and no application bootstrap', server.poll() is None)
                for mode in ('contact-null', 'contact-auth-empty', 'uid-null', 'resolved-support'):
                    session = LocalSession(base)
                    csrf = session.seed(mode)
                    status, _, body = session.request(client_path)
                    check('Actual raw router blocks GET before normalization: ' + mode, status == 403 and session.state()['counts'] == {} and 'Sign in directly' in body)
                    status, _, _ = session.request('/portal', {'action': 'https_request_create', 'csrf': csrf})
                    check('Actual raw router blocks direct POST before normalization: ' + mode, status == 403 and session.state()['counts'] == {})
                for suffix in ('support=', 'support=synthetic', 'support%5B%5D=synthetic'):
                    session = LocalSession(base)
                    session.seed('client')
                    status, _, _ = session.request(client_path + '&' + suffix)
                    check('Support query is rejected before modeled normalizer: ' + suffix, status == 403 and session.state()['counts'] == {})
                session = LocalSession(base)
                csrf = session.seed('client')
                status, _, _ = session.request('/portal', {'action': 'https_request_create', 'csrf': csrf, 'support': ''})
                check('Actual raw guard also rejects POST support marker', status == 403 and session.state()['counts'] == {})
                anonymous = LocalSession(base)
                anonymous.seed('anonymous')
                status, _, body = anonymous.request(client_path)
                check('Anonymous GET receives no request form or service screen', status == 401 and 'data-https-request-create' not in body and anonymous.state()['counts'] == {'normalizations': 1})
                for mode in ('client', 'staff'):
                    denied = LocalSession(base)
                    csrf = denied.seed(mode)
                    status, _, _ = denied.request(owner_path)
                    check('Actual owner controller refuses modeled nonowner: ' + mode, status == 403 and denied.state()['counts'].get('screens', 0) == 0)
                    status, _, _ = denied.request(owner_path, {'action': 'provider_https_prepare', 'csrf': csrf, 'current_password': password})
                    check('Actual admin dispatcher never calls preparation for nonowner: ' + mode, status == 403 and denied.state()['counts'].get('prepare', 0) == 0)
                cold_owner = LocalSession(base)
                cold_csrf = cold_owner.seed('owner')
                cold_owner.request('/__scenario?name=cold-provider')
                cold_before = cold_owner.state()['model']
                status, _, html = cold_owner.request(owner_path)
                cold_counts = cold_owner.state()['counts']
                check('Cold provider owner shell shows genuine setup guidance without request forms', status == 200 and 'An owner must prepare provider settings first.' in html and 'Open 20i connection settings' in html and '/owner?view=provider-settings' in html and "if($view==='provider-settings')provider_settings_admin_render($error);" in (ROOT / 'site/admin/controllers/router.php').read_text(encoding='utf-8') and '<form' not in html and cold_counts.get('owner_shell_checks') == 1 and cold_counts.get('owner_ready_checks') == 1)
                status, _, html = cold_owner.request(owner_path, {'action': 'provider_https_prepare', 'csrf': cold_csrf, 'current_password': password})
                cold_after = cold_owner.state()
                check('Cold provider POST retains guidance with no modeled preparation or business mutations', status == 200 and 'Open 20i connection settings' in html and '<form' not in html and cold_after['counts'].get('prepare', 0) == 0 and cold_after['model'] == cold_before)
                for mode in ('owner-contact-null', 'owner-contact-auth-empty', 'owner-support-null', 'owner-resolved-support'):
                    denied = LocalSession(base)
                    csrf = denied.seed(mode)
                    denied.request('/__scenario?name=cold-provider')
                    before = denied.state()['model']
                    status, _, body = denied.request(owner_path)
                    check('Cold owner raw authority gate refuses GET before readiness: ' + mode, status == 403 and '<form' not in body and denied.state()['counts'] == {})
                    status, _, _ = denied.request(owner_path, {'action': 'provider_https_prepare', 'csrf': csrf, 'current_password': password})
                    after = denied.state()
                    check('Cold owner raw authority gate refuses POST without mutations: ' + mode, status == 403 and after['counts'].get('prepare', 0) == 0 and after['model'] == before)
                for suffix in ('support=', 'support%5B%5D=synthetic'):
                    denied = LocalSession(base)
                    denied.seed('owner')
                    denied.request('/__scenario?name=cold-provider')
                    status, _, _ = denied.request(owner_path + '&' + suffix)
                    check('Cold owner support query refuses before readiness: ' + suffix, status == 403 and denied.state()['counts'] == {})
                denied = LocalSession(base)
                csrf = denied.seed('owner')
                denied.request('/__scenario?name=cold-provider')
                before = denied.state()['model']
                status, _, _ = denied.request(owner_path, {'action': 'provider_https_prepare', 'csrf': csrf, 'support': '', 'current_password': password})
                check('Cold owner POST support marker refuses without preparation', status == 403 and denied.state()['counts'].get('prepare', 0) == 0 and denied.state()['model'] == before)
                for mode in ('client', 'staff'):
                    denied = LocalSession(base)
                    denied.seed(mode)
                    denied.request('/__scenario?name=cold-provider')
                    status, _, _ = denied.request(owner_path)
                    check('Cold provider does not weaken owner-only shell: ' + mode, status == 403 and denied.state()['counts'].get('screens', 0) == 0)
                cold = LocalSession(base)
                cold.seed('client')
                _, _, cold_html = cold.request(client_path)
                check('Unprepared client screen offers contact guidance without submission', 'has not enabled HTTPS requests' in cold_html and 'data-https-request-create' not in cold_html)
                owner = LocalSession(base)
                owner.seed('owner')
                _, _, html = owner.request(owner_path)
                preparation, _, action = form_fields(html, 'data-https-request-prepare')
                check('Unprepared actual owner screen uses explicit password preparation', preparation['action'] == 'provider_https_prepare' and 'name="current_password"' in html and action == owner_path)
                status, _, html = owner.request(action, {**preparation, 'current_password': 'PRIVATE_WRONG_PASSWORD'})
                check('Wrong owner setup password remains inline and never echoes secret', status == 200 and 'Your current password is not correct.' in html and 'PRIVATE_WRONG_PASSWORD' not in html and owner.state()['model']['writes']['prepare'] == 0)
                status, headers, _ = owner.request(action, {**preparation, 'current_password': password})
                check('Explicit owner preparation redirects locally with page retained', status == 303 and headers['Location'] == owner_path and owner.state()['model']['writes']['prepare'] == 1)
                other = LocalSession(base)
                other.seed('other')
                _, _, html = other.request(client_path)
                check('Other modeled client gets no option or cross-client history', 'data-https-request-create' not in html and 'data-https-request-item' not in html)

                with loopback_driver(), sync_playwright() as playwright, closed_browser(playwright, executable) as browser:
                    unexpected_requests = []
                    def observe_request(request):
                        if not request.url.startswith(base + '/'):
                            unexpected_requests.append(request.url)
                    context = browser.new_context(service_workers='block')
                    context.set_default_timeout(10000)
                    context.on('request', observe_request)
                    page = context.new_page()
                    page.goto(base + '/__seed?mode=client')
                    response = page.goto(base + client_path)
                    form = page.locator('[data-https-request-create]')
                    form.wait_for()
                    original = fields(form)
                    check('Actual client creation form binds original id link and review', set(original) == {'csrf', 'action', 'request_id', 'link_id', 'review_sha256'} and len(original['request_id']) == 32 and original['review_sha256'] == 'b' * 64)
                    check('Creation requires explicit choice and unchecked consent without owner password', form.locator('select[name=desired]').input_value() == '' and not form.locator('[name=confirmed]').is_checked() and form.locator('[name=current_password]').count() == 0)
                    check('Actual route response is private and keeps form destination same-origin', response.headers['cache-control'] == 'no-store, private' and response.headers['referrer-policy'] == 'no-referrer' and "form-action 'self';" in response.headers['content-security-policy'])
                    check('Unsafe package metadata is text only', '<img src=x onerror=alert(1)>' in form.locator('..').inner_text() and page.locator('[data-https-requests] img,[data-https-requests] svg,[data-https-requests] script').count() == 0)
                    check('GET has no modeled business admission or provider calls', state(context)['model']['writes'] == {'prepare': 1, 'request': 0, 'cancel': 0, 'decline': 0, 'admission': 0, 'check': 0, 'recover': 0} and state(context)['model']['provider_calls'] == 0)
                    for width, height in ((390, 844), (1440, 1000)):
                        page.set_viewport_size({'width': width, 'height': height})
                        button = form.locator('button')
                        button.scroll_into_view_if_needed()
                        geometry = button.evaluate('(e)=>{let r=e.getBoundingClientRect();let hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return {hit:e===hit||e.contains(hit),inside:r.left>=0&&r.right<=innerWidth,overflow:document.documentElement.scrollWidth<=innerWidth+1}}')
                        check('Actual isolated client form remains visible and hit-testable at ' + str(width), button.is_visible() and geometry['hit'] and geometry['inside'] and geometry['overflow'])
                        readable = form.evaluate('(e)=>{let css=x=>getComputedStyle(e.querySelector(x));return parseFloat(css("label").fontSize)>=14&&parseFloat(css("select").fontSize)>=16&&parseFloat(css("button").fontSize)>=14&&e.querySelector("button").getBoundingClientRect().height>=44&&e.querySelector("input[type=checkbox]").getBoundingClientRect().width>=18}')
                        check('Actual client form text and controls remain readable at ' + str(width), readable)
                        if preview_dir is not None and width == 390:
                            page.locator('[data-https-requests]').screenshot(path=str(preview_dir / 'client-mobile.png'))
                    before = state(context)
                    rejected = context.request.post(base + client_path, form={**original, 'csrf': 'wrong', 'desired': 'true', 'confirmed': '1'})
                    retained, checked, action = form_fields(rejected.text(), 'data-https-request-create')
                    check('Common portal CSRF rejects before modeled creation', 'Your session expired.' in rejected.text() and state(context)['counts'].get('create', 0) == before['counts'].get('create', 0))
                    check('CSRF retry retains original client proof id page and unchecked consent', retained == original and not checked and action == client_path)
                    rejected = context.request.post(base + client_path, form={**original, 'desired': 'false'})
                    retained, checked, _ = form_fields(rejected.text(), 'data-https-request-create')
                    check('Unconfirmed request retains exact proof and selected Off without business write', 'Confirm that you want' in rejected.text() and retained == original and not checked and 'value="false" selected' in rejected.text() and state(context)['model']['writes']['request'] == 0)
                    rejected = context.request.post(base + client_path, form={**original, 'desired': 'maybe', 'confirmed': '1'})
                    check('Actual controller refuses nonboolean desired text before business call', 'Choose On or Off' in rejected.text() and state(context)['counts']['create'] == before['counts'].get('create', 0) + 1)
                    malformed_review = '\"><svg onload=alert(4)>'
                    rejected = context.request.post(base + client_path, form={**original, 'review_sha256': malformed_review, 'desired': 'true', 'confirmed': '1'})
                    retained, checked, _ = form_fields(rejected.text(), 'data-https-request-create')
                    check('Malformed posted proof is retained as escaped data without a business write', retained == {**original, 'review_sha256': malformed_review} and not checked and '<svg onload=alert(4)>' not in rejected.text() and state(context)['model']['writes']['request'] == 0)
                    context.request.get(base + '/__scenario?name=options-unavailable')
                    rejected = context.request.post(base + client_path, form={**original, 'desired': 'true', 'confirmed': '1'})
                    retained, checked, _ = form_fields(rejected.text(), 'data-https-request-create')
                    check('Unavailable service retains original selection only in a disabled retry', retained == original and not checked and 'Previous request' in rejected.text() and '<button class="btn gold" disabled>' in rejected.text())
                    context.request.get(base + '/__scenario?name=')
                    # Use real keyboard and native form submission for the first request/withdrawal.
                    form.locator('select').select_option('true')
                    form.locator('[name=confirmed]').focus()
                    page.keyboard.press('Space')
                    page.keyboard.press('Tab')
                    check('Keyboard moves from consent to actual submit button', page.evaluate('document.activeElement.tagName === "BUTTON"') and form.locator('[name=confirmed]').is_checked())
                    page.keyboard.press('Enter')
                    page.wait_for_url(base + client_path + '&request=' + original['request_id'])
                    item = page.locator('[data-https-request-item="' + original['request_id'] + '"]')
                    check('Actual client submission reaches pending detail through 303', 'Waiting for studio review' in item.inner_text() and state(context)['model']['writes']['request'] == 1 and state(context)['model']['writes']['admission'] == 0)
                    cancellation = item.locator('[data-https-request-cancel]')
                    cancel_fields = fields(cancellation)
                    check('Withdrawal binds exact original request and expected event', cancel_fields['request_id'] == original['request_id'] and cancel_fields['expected_event'] == original['request_id'] and len(cancel_fields['event_id']) == 32)
                    cancellation.locator('button').click()
                    page.wait_for_load_state()
                    check('Actual client withdrawal projects cancelled status without approval', 'Withdrawn by client' in page.inner_text('body') and state(context)['model']['writes']['cancel'] == 1 and state(context)['model']['writes']['admission'] == 0)
                    replay = context.request.post(base + client_path, form={**original, 'desired': 'true', 'confirmed': '1'}, max_redirects=0)
                    check('Controller safely redirects modeled original-create replay without a duplicate row', replay.status == 303 and state(context)['model']['writes']['request'] == 1)

                    owner_context = browser.new_context(service_workers='block')
                    owner_context.set_default_timeout(10000)
                    owner_context.on('request', observe_request)
                    owner_page = owner_context.new_page()
                    owner_page.goto(base + '/__seed?mode=owner')
                    page.goto(base + client_path)
                    second_form = page.locator('[data-https-request-create]')
                    second = fields(second_form)
                    second_form.locator('select').select_option('false')
                    second_form.locator('[name=confirmed]').check()
                    second_form.locator('button').click()
                    page.wait_for_url(base + client_path + '&request=' + second['request_id'])
                    detail = owner_path + '&request=' + second['request_id']
                    owner_page.goto(base + detail)
                    decline = owner_page.locator('[data-https-request-decline]')
                    disclosure = owner_page.locator('details.https-request-decline')
                    check('Decline controls are secondary and initially collapsed', not disclosure.evaluate('(e)=>e.open') and not decline.is_visible())
                    disclosure.locator('summary').focus()
                    owner_page.keyboard.press('Enter')
                    check('Keyboard opens the real native decline disclosure', disclosure.evaluate('(e)=>e.open') and decline.is_visible())
                    decline_fields = fields(decline)
                    note = 'Cannot approve <script>alert(3)</script>\nPlease contact the studio.'
                    failed = owner_context.request.post(base + detail, form={**decline_fields, 'note': note, 'current_password': 'PRIVATE_WRONG_PASSWORD'})
                    retained, _, action = form_fields(failed.text(), 'data-https-request-decline')
                    check('Owner decline failure retains exact event ids and page/detail navigation', retained == decline_fields and action == detail and 'Your current password is not correct.' in failed.text() and 'PRIVATE_WRONG_PASSWORD' not in failed.text())
                    check('Owner decline note is escaped and remains available for retry', '&lt;script&gt;alert(3)&lt;/script&gt;' in failed.text() and '<script>alert(3)</script>' not in failed.text() and state(context)['model']['writes']['decline'] == 0)
                    check('Failed decline automatically reopens its exact form', '<details class="https-request-decline" open>' in failed.text())
                    decline.locator('textarea').fill(note)
                    decline.locator('[name=current_password]').fill(password)
                    decline.locator('button').click()
                    owner_page.wait_for_load_state()
                    page.reload()
                    check('Actual decline reaches client-safe note and status', 'Declined by studio' in page.inner_text('body') and all(line in page.inner_text('body') for line in note.splitlines()) and page.locator('[data-https-requests] script').count() == 0 and state(context)['model']['writes']['decline'] == 1 and state(context)['model']['items'][second['request_id']]['note'].replace('\r\n', '\n') == note)

                    page.goto(base + client_path)
                    third_form = page.locator('[data-https-request-create]')
                    third = fields(third_form)
                    third_form.locator('select').select_option('true')
                    third_form.locator('[name=confirmed]').check()
                    third_form.locator('button').click()
                    page.wait_for_url(base + client_path + '&request=' + third['request_id'])
                    detail = owner_path + '&request=' + third['request_id']
                    owner_page.goto(base + detail)
                    approval = owner_page.locator('[data-https-request-approve]')
                    approved = fields(approval)
                    check('Owner review binds client request hash and independent approval anchor', set(approved) == {'csrf', 'action', 'request_id', 'expected_event', 'request_sha256', 'owner_request_id', 'anchor_id', 'binding_sha256'} and approved['request_id'] == third['request_id'] and approved['expected_event'] == third['request_id'] and approved['request_sha256'] == hashlib.sha256(third['request_id'].encode()).hexdigest())
                    check('Owner review clearly requires blank password and fresh unchecked consent', approval.locator('[name=current_password]').input_value() == '' and not approval.locator('[name=confirmed]').is_checked() and 'Reviewed saved setting: Off' in approval.inner_text())
                    check('Owner identity is escaped in actual renderer', 'Client <svg onload=alert(2)>' in owner_page.inner_text('body') and owner_page.locator('[data-https-requests] svg').count() == 0)
                    for width, height in ((390, 844), (1440, 1000)):
                        owner_page.set_viewport_size({'width': width, 'height': height})
                        button = approval.locator('button')
                        button.scroll_into_view_if_needed()
                        geometry = button.evaluate('(e)=>{let r=e.getBoundingClientRect();let hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return r.left>=0&&r.right<=innerWidth&&(e===hit||e.contains(hit))&&document.documentElement.scrollWidth<=innerWidth+1}')
                        check('Actual isolated owner approval is hit-testable at ' + str(width), geometry and button.is_visible())
                        metrics = approval.evaluate('(e)=>{let css=x=>getComputedStyle(e.querySelector(x));return {label:parseFloat(css("label").fontSize),input:parseFloat(css("input[type=password]").fontSize),button:parseFloat(css("button").fontSize),height:e.querySelector("button").getBoundingClientRect().height,checkbox:e.querySelector("input[type=checkbox]").getBoundingClientRect().width}}')
                        check('Actual owner form text and controls remain readable at ' + str(width), metrics['label'] >= 14 and metrics['input'] >= 16 and metrics['button'] >= 14 and metrics['height'] >= 44 and metrics['checkbox'] >= 18, metrics)
                        if preview_dir is not None and width == 1440:
                            owner_page.locator('[data-https-requests]').screenshot(path=str(preview_dir / 'owner-review-desktop.png'))
                    before = state(owner_context)
                    failed = owner_context.request.post(base + detail, form={**approved, 'csrf': 'wrong', 'confirmed': '1', 'current_password': password})
                    retained, checked, action = form_fields(failed.text(), 'data-https-request-approve')
                    check('Admin CSRF refusal precedes modeled approval', 'Your session expired.' in failed.text() and state(owner_context)['counts'].get('approve', 0) == before['counts'].get('approve', 0))
                    check('Admin CSRF retry renders only original approval fields with unchecked consent', retained == approved and not checked and action == detail and state(context)['model']['writes']['admission'] == 0)
                    failed = owner_context.request.post(base + detail, form={**approved, 'confirmed': '1', 'current_password': 'PRIVATE_WRONG_PASSWORD'})
                    retained, checked, _ = form_fields(failed.text(), 'data-https-request-approve')
                    check('Bad owner password keeps original approval and clears password', retained == approved and not checked and 'Your current password is not correct.' in failed.text() and 'PRIVATE_WRONG_PASSWORD' not in failed.text() and state(context)['model']['writes']['admission'] == 0)
                    failed = owner_context.request.post(base + detail, form={**approved, 'current_password': password})
                    check('Actual unchecked approval refuses modeled admission', 'Confirm this original owner review.' in failed.text() and not form_fields(failed.text(), 'data-https-request-approve')[1] and state(context)['model']['writes']['admission'] == 0)
                    owner_context.request.get(base + '/__scenario?name=approval-unavailable')
                    failed = owner_context.request.post(base + detail, form={**approved, 'confirmed': '1', 'current_password': password})
                    retained, checked, _ = form_fields(failed.text(), 'data-https-request-approve')
                    check('Stale unavailable owner review preserves original proof rather than replacing it', retained == approved and not checked and 'Refresh the saved HTTPS setting' in failed.text() and state(context)['model']['writes']['admission'] == 0)
                    owner_context.request.get(base + '/__scenario?name=assignment-stale')
                    failed = owner_context.request.post(base + detail, form={**approved, 'confirmed': '1', 'current_password': password})
                    check('Changed assignment retains original approval in a disabled form', form_fields(failed.text(), 'data-https-request-approve')[0] == approved and '<button class="btn gold" disabled>' in failed.text() and state(context)['model']['writes']['admission'] == 0)
                    owner_context.request.get(base + '/__scenario?name=')
                    approval.locator('[name=current_password]').fill(password)
                    approval.locator('[name=confirmed]').check()
                    approval.locator('button').click()
                    owner_page.wait_for_load_state()
                    check('Actual owner submit reaches modeled unknown outcome once', 'Change requested; result unconfirmed' in owner_page.inner_text('body') and state(context)['model']['writes']['admission'] == 1)
                    replay = owner_context.request.post(base + detail, form={**approved, 'confirmed': '1', 'current_password': password}, max_redirects=0)
                    check('Exact original modeled approval replay causes no second admission', replay.status == 303 and state(context)['model']['writes']['admission'] == 1)
                    page.reload()
                    check('Client unknown status exposes no owner approval or recovery identifiers', 'result unconfirmed' in page.inner_text('body') and 'will not be sent again automatically' in page.inner_text('body') and 'owner_request_id' not in page.content() and approved['owner_request_id'] not in page.content())
                    # Controlled model state: exercise actual recovery dispatch/form, not native ledger recovery.
                    owner_context.request.get(base + '/__scenario?name=interrupted')
                    owner_page.reload()
                    recovery = owner_page.locator('[data-https-request-recover]')
                    recovery_fields = fields(recovery)
                    failed = owner_context.request.post(base + detail, form={**recovery_fields, 'current_password': password})
                    check('Recovery requires explicit consent before modeled boundary', 'Confirm that only the saved request history' in failed.text() and state(context)['model']['writes']['recover'] == 0)
                    recovery.locator('[name=current_password]').fill(password)
                    recovery.locator('[name=confirmed]').check()
                    recovery.locator('button').click()
                    owner_page.wait_for_load_state()
                    verify = owner_page.locator('[data-https-request-verify]')
                    verification = fields(verify)
                    check('Separate check binds exact original client and owner request ids', verification['request_id'] == third['request_id'] and verification['owner_request_id'] == approved['owner_request_id'] and 'Does not resend' in verify.inner_text())
                    verify.locator('[name=current_password]').fill(password)
                    verify.locator('button').click()
                    owner_page.wait_for_load_state()
                    page.reload()
                    check('Client receives distinct observed status with explicit certificate and causality limits', 'Requested setting reported' in page.inner_text('body') and 'does not establish certificate validity or prove this request caused the change' in page.inner_text('body') and state(context)['model']['writes']['check'] == 1 and state(context)['model']['writes']['admission'] == 1)
                    check('Actual model call arguments retain desired bool and original approval association', any(row['name'] == 'create' and row['args'] == [third['request_id'], 1, 'a' * 32, 'b' * 64, True, True] for row in state(context)['model']['calls']) and any(row['name'] == 'verify' and row['args'] == [third['request_id'], approved['owner_request_id']] for row in state(context)['model']['calls']))
                    check('Every local journey remains free of actual provider operations', state(context)['model']['provider_calls'] == 0)
                    owner_context.request.get(base + '/__scenario?name=corrupt')
                    owner_page.reload()
                    check('Finite history error is visible without presenting action forms', 'Saved request history is unavailable.' in owner_page.inner_text('body') and owner_page.locator('[data-https-request-approve],[data-https-request-verify]').count() == 0)
                    owner_context.request.get(base + '/__scenario?name=')
                    owner_context.request.get(base + '/__scenario?name=screen-pdo')
                    unavailable = owner_context.request.get(base + detail)
                    check('Controller propagates modeled SQL failure without leaking raw diagnostics', unavailable.status == 500 and 'PRIVATE_DATABASE_DIAGNOSTIC' not in unavailable.text() and 'data-https-request-approve' not in unavailable.text())
                    owner_context.request.get(base + '/__scenario?name=')
                    malformed = owner_context.request.get(base + '/owner?view=https-requests&https_page=501&request=%3Cscript%3E')
                    check('Malformed navigation normalizes to safe page one and no foreign detail', 'https_page=501' not in malformed.text() and 'request=%3Cscript%3E' not in malformed.text() and 'https_page=2' in malformed.text())
                    foreign = context.request.get(base + client_path + '&request=' + 'f' * 32)
                    check('Foreign detail produces no historical item or owner controls', 'Force SSL request unavailable.' in foreign.text() and 'data-https-request-item=' not in foreign.text() and 'data-https-request-approve' not in foreign.text())
                    other_after = LocalSession(base)
                    other_after.seed('other')
                    _, _, html = other_after.request(client_path + '&request=' + third['request_id'])
                    check('Current other client cannot view the completed modeled request', 'data-https-request-item=' not in html and third['request_id'] not in html)
                    check('Both Chromium contexts requested only this loopback fixture', unexpected_requests == [])
                    context.close()
                    owner_context.close()
            finally:
                server.terminate()
                try:
                    server.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=10)
    check('All exercised source bytes remain unchanged throughout this local run', source_hashes == {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in SOURCE_PATHS})
    return {'passed': True, 'scope': 'Actual extracted PHP controller/renderer/raw session guard/router block and common dispatchers over loopback HTTP and Chromium with actual CSS; explicit modeled identity/business/SQL boundaries. Closed nonforwarding proxy. No installed application SQL, provider dispatch, real client identity, certificate or HTTPS-health acceptance.', 'checks': checks, 'source_sha256': source_hashes, 'captures': []}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', default=os.environ.get('PHP_BINARY') or shutil.which('php'))
    parser.add_argument('--browser', default=os.environ.get('CWP_BROWSER_EXECUTABLE'))
    parser.add_argument('--report')
    parser.add_argument('--preview-dir', type=Path, help='Optional new local modeled-shell preview directory; never release captures.')
    args = parser.parse_args()
    checks = []
    try:
        if not args.php:
            raise RuntimeError('PHP executable required')
        if args.preview_dir is not None:
            args.preview_dir.mkdir(parents=True, exist_ok=False)
        report = run(args.php, args.browser, checks, args.preview_dir)
    except Exception as error:
        report = {'passed': False, 'scope': 'Local HTTPS request UI/controller checks did not complete; no installed or provider acceptance.', 'checks': checks, 'failure': type(error).__name__, 'captures': []}
    output = json.dumps(report, indent=2) + '\n'
    if args.report:
        path = Path(args.report)
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open('x', encoding='utf-8', newline='\n') as stream:
            stream.write(output)
    else:
        print(output, end='')
    raise SystemExit(0 if report['passed'] else 1)
