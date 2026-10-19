"""Actual PHP controllers/forms/security and Chromium, with explicit modeled business/session seams.

Only loopback is reachable. The inherited closed TLS proxy never forwards or resolves
the synthetic StackCP destination. This is not installed application or SQL acceptance.
"""
import argparse
import ast
from contextlib import contextmanager
import datetime
import hashlib
from http.cookiejar import CookieJar
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import shutil
import socket
import ssl
import subprocess
import tempfile
import threading
import time
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import build_opener, HTTPCookieProcessor, HTTPRedirectHandler, ProxyHandler, Request

ROOT = Path(__file__).resolve().parents[1]
DESTINATION = 'https://www.stackcp.com/login/sso?PHPSESSID=synthetic-session%2Bonly'
SOURCE_PATHS = (
    'tests/client_stackcp_browser.py', 'tests/client_stackcp_http_fixture.php', 'tests/stackcp_launch_browser.py',
    'site/portal/controllers/client_stackcp.php', 'site/portal/controllers/router.php',
    'site/portal/controllers/actions.php', 'site/admin/controllers/provider_client_stackcp.php',
    'site/admin/controllers/providers.php', 'site/admin/controllers/actions.php',
    'site/admin/controllers/auth.php', 'site/app/providers/client_stackcp_views.php',
    'site/app/providers/stackcp_prerequisite_views.php', 'site/app/providers/stackcp_launch_views.php',
    'site/app/providers/stackcp_launch_http.php', 'site/includes/layout/admin.php',
    'site/includes/security/input.php', 'site/includes/security/csrf.php', 'site/includes/security/http.php',
)


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, new_url):
        return None


def retained_form(html):
    class Form(HTMLParser):
        def __init__(self):
            super().__init__()
            self.active = False
            self.fields = {}
            self.checked = False

        def handle_starttag(self, tag, pairs):
            attrs = dict(pairs)
            if tag == 'form':
                self.active = 'data-client-stackcp-open' in attrs
            elif self.active and tag == 'input':
                if attrs.get('type') == 'hidden':
                    self.fields[attrs['name']] = attrs.get('value', '')
                elif attrs.get('name') == 'confirmed':
                    self.checked = 'checked' in attrs

        def handle_endtag(self, tag):
            if tag == 'form':
                self.active = False
    form = Form()
    form.feed(html)
    return form.fields, form.checked


class LocalSession:
    def __init__(self, base):
        self.base = base
        self.cookies = CookieJar()
        self.opener = build_opener(ProxyHandler({}), HTTPCookieProcessor(self.cookies), NoRedirect())

    def request(self, path, fields=None):
        if not path.startswith('/') or path.startswith('//'):
            raise ValueError('Only local fixture paths are allowed')
        data = urlencode(fields).encode() if fields is not None else None
        request = Request(self.base + path, data=data)
        try:
            response = self.opener.open(request, timeout=10)
        except HTTPError as response:
            return response.code, dict(response.headers), response.read().decode('utf-8')
        with response:
            return response.status, dict(response.headers), response.read().decode('utf-8')

    def seed(self, mode):
        status, _, body = self.request('/__seed?' + urlencode({'mode': mode}))
        if status != 200:
            raise RuntimeError('Synthetic session setup failed')
        return json.loads(body)['csrf']

    def state(self):
        status, _, body = self.request('/__state')
        if status != 200:
            raise RuntimeError('Synthetic state read failed')
        return json.loads(body)


def closed_proxy(directory):
    """Reuse only the exact existing closed proxy function, without importing its old runner."""
    from cryptography import x509
    from cryptography.hazmat.primitives import hashes, serialization
    from cryptography.hazmat.primitives.asymmetric import rsa
    from cryptography.x509.oid import NameOID
    source = ROOT / 'tests/stackcp_launch_browser.py'
    tree = ast.parse(source.read_text(encoding='utf-8'))
    functions = [node for node in tree.body if isinstance(node, ast.FunctionDef) and node.name == 'synthetic_proxy']
    if len(functions) != 1:
        raise RuntimeError('Closed proxy source is ambiguous')
    namespace = dict(globals(), x509=x509, hashes=hashes, serialization=serialization, rsa=rsa, NameOID=NameOID)
    exec(compile(ast.Module(body=functions, type_ignores=[]), str(source), 'exec'), namespace)
    return namespace['synthetic_proxy'](directory)


@contextmanager
def loopback_bypass_driver():
    # Otherwise Playwright appends <-loopback> after the explicit local bypass.
    # Only localhost bypasses the closed, non-forwarding external proxy.
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


def run(php, executable, checks):
    from playwright.sync_api import sync_playwright

    def check(name, value):
        if any(row['name'] == name for row in checks):
            raise RuntimeError('Duplicate check label')
        checks.append({'name': name, 'passed': bool(value)})
        if not value:
            raise AssertionError('Named HTTP/browser check failed')

    with tempfile.TemporaryDirectory(prefix='cwp-client-stackcp-http-') as temporary:
        directory = Path(temporary)
        (directory / 'sessions').mkdir(mode=0o700)
        nonce = os.urandom(16).hex()
        env = dict(os.environ, CWP_CLIENT_STACKCP_HTTP_FIXTURE='1', CWP_CLIENT_STACKCP_SOURCE=str(ROOT), CWP_CLIENT_STACKCP_TEMP=str(directory), CWP_CLIENT_STACKCP_HEALTH=nonce)
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 0))
            port = probe.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        fixture = ROOT / 'tests/client_stackcp_http_fixture.php'
        with (directory / 'private-php.log').open('wb') as log:
            server = subprocess.Popen([php, '-S', f'127.0.0.1:{port}', str(fixture)], cwd=directory, env=env, stdout=log, stderr=log)
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
                    raise RuntimeError('Loopback PHP server did not start')
                check('Loopback-only PHP fixture starts without application bootstrap', server.poll() is None)
                for mode in ('contact-null', 'contact-auth-empty', 'uid-null', 'resolved-support'):
                    session = LocalSession(base)
                    csrf = session.seed(mode)
                    status, headers, body = session.request('/portal?hosting_panel=1')
                    check('Raw forbidden context blocks GET before normalization: ' + mode, status == 403 and session.state()['counts'] == {} and 'requires an independent primary client session' in body)
                    status, _, _ = session.request('/portal', {'action': 'client_stackcp_open', 'csrf': csrf})
                    check('Raw forbidden context blocks direct action before normalization: ' + mode, status == 403 and session.state()['counts'] == {})
                    check('Forbidden context receives no-store and no-referrer: ' + mode, headers.get('Cache-Control') == 'no-store, private, max-age=0' and headers.get('Referrer-Policy') == 'no-referrer')
                for suffix in ('support=', 'support=synthetic', 'support%5B%5D=synthetic'):
                    session = LocalSession(base)
                    session.seed('client')
                    status, _, _ = session.request('/portal?hosting_panel=1&' + suffix)
                    check('Raw support query blocks before resolution: ' + suffix, status == 403 and session.state()['counts'] == {})
                anonymous = LocalSession(base)
                anonymous.seed('anonymous')
                status, _, _ = anonymous.request('/portal?hosting_panel=1')
                check('Anonymous session never receives review or launch form', status == 401 and anonymous.state()['counts'] == {'normalizations': 1})
                other = LocalSession(base)
                other.seed('other')
                status, _, body = other.request('/portal?hosting_panel=1')
                check('Other modeled client receives no account or approval', status == 200 and 'data-client-stackcp-open' not in body and other.state()['counts'].get('reviews', 0) == 0)
                for mode in ('client', 'staff'):
                    session = LocalSession(base)
                    session.seed(mode)
                    status, _, _ = session.request('/owner')
                    check('Actual owner screen refuses modeled nonowner: ' + mode, status == 403 and session.state()['counts'].get('owner_snapshots', 0) == 0)

                with loopback_bypass_driver(), sync_playwright() as playwright:
                    proxy, thread, received = closed_proxy(directory)
                    options = {'headless': True, 'proxy': {'server': f'http://127.0.0.1:{proxy.server_port}', 'bypass': '127.0.0.1,localhost'}}
                    if executable:
                        options['executable_path'] = executable
                    browser = None
                    try:
                        browser = playwright.chromium.launch(**options)
                        context = browser.new_context(service_workers='block', ignore_https_errors=True)
                        context.set_default_timeout(10000)
                        page = context.new_page()
                        page.goto(base + '/__seed?mode=client')
                        response = page.goto(base + '/portal?hosting_panel=1')
                        form = page.locator('[data-client-stackcp-open]')
                        form.wait_for()
                        fields = {item['name']: item['value'] for item in form.locator('input[type=hidden]').evaluate_all('(els)=>els.map(e=>({name:e.name,value:e.value}))')}
                        check('Actual client form supplies exact local approval fields', set(fields) == {'csrf', 'action', 'permission_id', 'admission_id', 'expires_epoch', 'review_proof'} and fields['action'] == 'client_stackcp_open')
                        check('Client form never asks for owner password or posted actor', form.locator('input[type=password],input[name=client_id],input[name=actor_id]').count() == 0)
                        check('Client form opens a protected new tab', form.get_attribute('target') == '_blank' and form.get_attribute('rel') == 'noopener noreferrer')
                        check('Client form requires fresh whole-user consent', not form.locator('input[name=confirmed]').is_checked() and 'whole account' in page.inner_text('body') and 'five minutes' in page.inner_text('body'))
                        check('Authenticated client page sets narrowly allowed form destination', "form-action 'self' https://www.stackcp.com/login/sso;" in response.headers['content-security-policy'])
                        check('Client response forbids cache and referrer forwarding', response.headers['cache-control'] == 'no-store, private, max-age=0' and response.headers['referrer-policy'] == 'no-referrer')
                        check('Client HTML contains no provider session or destination', 'PHPSESSID' not in page.content() and 'www.stackcp.com' not in page.content())
                        for width, height in ((390, 844), (1440, 1000)):
                            page.set_viewport_size({'width': width, 'height': height})
                            check('Actual client controls remain reachable at width ' + str(width), form.locator('button').is_visible() and form.locator('input[name=confirmed]').is_visible())
                        before = context.request.get(base + '/__state').json()
                        failed = context.request.post(base + '/portal?hosting_panel=1', form={**fields, 'csrf': 'wrong', 'confirmed': '1'})
                        after = context.request.get(base + '/__state').json()
                        check('Actual common portal CSRF rejects before launch service', 'Your session expired.' in failed.text() and after['counts'].get('opens', 0) == before['counts'].get('opens', 0))
                        retained, checked = retained_form(failed.text())
                        check('Failed CSRF POST retains original approval without consent or renewal', retained == fields and not checked and after['counts'].get('reviews', 0) == before['counts'].get('reviews', 0))
                        failed = context.request.post(base + '/portal?hosting_panel=1', form=fields)
                        after = context.request.get(base + '/__state').json()
                        check('Unconfirmed POST reaches finite refusal without synthetic delivery', 'Review and confirm the original approval.' in failed.text() and after['counts'].get('synthetic_deliveries', 0) == 0)
                        retained, checked = retained_form(failed.text())
                        check('Rejected client POST retains exact original attempt and unchecked consent', retained == fields and not checked and after['counts']['reviews'] == before['counts']['reviews'] and 'keeps its original approval' in failed.text() and 'Continue this original' in failed.text())
                        for key, value in (('permission_id', 'f' * 32), ('admission_id', 'invalid'), ('expires_epoch', '0'), ('review_proof', 'invalid')):
                            rejected = context.request.post(base + '/portal?hosting_panel=1', form={**fields, key: value, 'confirmed': '1'})
                            after = context.request.get(base + '/__state').json()
                            check('Foreign or malformed posted approval stays unavailable without renewal: ' + key, not retained_form(rejected.text())[0] and after['counts']['reviews'] == before['counts']['reviews'] and after['counts'].get('synthetic_deliveries', 0) == 0)
                        form.locator('input[name=confirmed]').check()
                        with context.expect_page() as opened:
                            form.get_by_role('button', name='Open hosting control panel').click()
                        popup = opened.value
                        popup.wait_for_url(DESTINATION)
                        popup.wait_for_load_state()
                        handoffs = [row for row in received if row['request'].startswith('GET /login/sso?')]
                        check('Actual client controller reaches fixed synthetic 303 in new tab', popup.url == DESTINATION and popup.title() == 'Synthetic StackCP' and len(handoffs) == 1)
                        check('Client destination has no opener or referrer', popup.evaluate('window.opener === null') and 'referer' not in handoffs[0]['headers'])
                        check('Origin URL and HTML never receive the provider session', page.url == base + '/portal?hosting_panel=1' and 'PHPSESSID' not in page.content())
                        before = context.request.get(base + '/__state').json()
                        replay = context.request.post(base + '/portal?hosting_panel=1', form={**fields, 'confirmed': '1'}, max_redirects=0)
                        after = context.request.get(base + '/__state').json()
                        check('Controller projects modeled consumed outcome without redirect or approval renewal', replay.status == 200 and 'location' not in replay.headers and after['counts']['synthetic_deliveries'] == before['counts']['synthetic_deliveries'] and after['counts']['reviews'] == before['counts']['reviews'] and 'browser delivery and remote sign-in are not confirmed' in replay.text())
                        context.close()

                        owner = browser.new_context(service_workers='block', ignore_https_errors=True)
                        owner.set_default_timeout(10000)
                        owner_page = owner.new_page()
                        owner_page.goto(base + '/__seed?mode=owner')
                        response = owner_page.goto(base + '/owner')
                        permission = owner_page.locator('[data-client-stackcp-permission-form]')
                        owner_fields = {item['name']: item['value'] for item in permission.locator('input[type=hidden]').evaluate_all('(els)=>els.map(e=>({name:e.name,value:e.value}))')}
                        check('Actual owner permission form binds profile package review and enable', set(owner_fields) == {'csrf', 'action', 'profile_id', 'package_id', 'review_proof', 'enabled'} and owner_fields['enabled'] == '1')
                        check('Owner delegation needs blank password and unchecked consent', permission.locator('input[name=current_password]').input_value() == '' and not permission.locator('input[name=confirmed]').is_checked())
                        check('Owner permission page does not widen its form policy', "form-action 'self';" in response.headers['content-security-policy'])
                        for width, height in ((390, 844), (1440, 1000)):
                            owner_page.set_viewport_size({'width': width, 'height': height})
                            check('Actual owner controls remain reachable at width ' + str(width), permission.locator('button').is_visible() and permission.locator('input[name=current_password]').is_visible())
                        fail = owner.request.post(base + '/owner', form={**owner_fields, 'csrf': 'wrong', 'confirmed': '1', 'current_password': 'Synthetic owner password'})
                        state = owner.request.get(base + '/__state').json()
                        check('Actual common admin CSRF precedes permission writer', 'Your session expired.' in fail.text() and state['counts'].get('permission_calls', 0) == 0)
                        owner_page.locator('input[name=current_password]').fill('PRIVATE_WRONG_PASSWORD')
                        owner_page.locator('input[name=confirmed]').check()
                        owner_page.get_by_role('button', name='Enable client access').click()
                        owner_page.get_by_role('alert').wait_for()
                        permission = owner_page.locator('[data-client-stackcp-permission-form]')
                        check('Actual owner POST displays specific password refusal', 'Your current password is not correct.' in owner_page.get_by_role('alert').inner_text())
                        check('Owner password failure preserves original proof and clears secrets', permission.locator('input[name=review_proof]').input_value() == owner_fields['review_proof'] and permission.locator('input[name=current_password]').input_value() == '' and not permission.locator('input[name=confirmed]').is_checked() and 'PRIVATE_WRONG_PASSWORD' not in owner_page.content())
                        state = owner.request.get(base + '/__state').json()
                        check('Rejected owner form has no modeled permission mutation', state['counts'].get('permission_writes', 0) == 0)
                        permission.locator('input[name=current_password]').fill('Synthetic owner password')
                        permission.locator('input[name=confirmed]').check()
                        permission.get_by_role('button', name='Enable client access').click()
                        owner_page.wait_for_url(base + '/owner?view=provider-settings#client-stackcp')
                        state = owner.request.get(base + '/__state').json()
                        check('Actual owner action dispatches once and redirects locally', state['counts'].get('permission_writes', 0) == 1 and len([row for row in received if row['request'].startswith('GET /login/sso?')]) == 1)
                        owner.close()

                        for label, suffix in (('page 2', 'panel_page=2'), ('filtered service', 'service_link=' + 'e' * 32)):
                            navigation = browser.new_context(service_workers='block', ignore_https_errors=True)
                            navigation.set_default_timeout(10000)
                            try:
                                selected_page = navigation.new_page()
                                selected_page.goto(base + '/__seed?mode=client')
                                selected_page.goto(base + '/portal?hosting_panel=1&' + suffix)
                                selected_form = selected_page.locator('[data-client-stackcp-open]')
                                selected_form.wait_for()
                                original = {item['name']: item['value'] for item in selected_form.locator('input[type=hidden]').evaluate_all('(els)=>els.map(e=>({name:e.name,value:e.value}))')}
                                action = selected_form.get_attribute('action')
                                check('Actual form keeps selected route context: ' + label, action == '/portal?hosting_panel=1&' + suffix and original['permission_id'] == 'f' * 32)
                                previous = navigation.request.get(base + '/__state').json()
                                rejected = navigation.request.post(base + action, form=original)
                                current = navigation.request.get(base + '/__state').json()
                                retained, checked = retained_form(rejected.text())
                                check('Failed POST retains exact selected approval and card: ' + label, 'Review and confirm the original approval.' in rejected.text() and 'Synthetic second hosting' in rejected.text() and retained == original and not checked)
                                check('Failed selected POST never mints a review or delivers: ' + label, current['counts']['reviews'] == previous['counts']['reviews'] and current['counts'].get('synthetic_deliveries', 0) == 0)
                                rejected_csrf = navigation.request.post(base + action, form={**original, 'csrf': 'wrong', 'confirmed': '1'})
                                current = navigation.request.get(base + '/__state').json()
                                retained, checked = retained_form(rejected_csrf.text())
                                check('Selected-route CSRF refusal retains original proof without renewal: ' + label, 'Your session expired.' in rejected_csrf.text() and retained == original and not checked and current['counts']['reviews'] == previous['counts']['reviews'] and current['counts']['opens'] == previous['counts'].get('opens', 0) + 1)
                            finally:
                                navigation.close()
                    finally:
                        if browser is not None:
                            browser.close()
                        proxy.shutdown()
                        proxy.server_close()
                        thread.join(timeout=5)
            finally:
                server.terminate()
                try:
                    server.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=10)
    return {'passed': True, 'scope': 'Actual extracted PHP route/controller/form/security functions and common dispatchers over loopback HTTP with Chromium; modeled session/business lookups and closed synthetic TLS destination. No application SQL, installed identity, real provider traffic or remote login acceptance.', 'checks': checks, 'source_sha256': {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in SOURCE_PATHS}, 'captures': []}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', default=os.environ.get('PHP_BINARY') or shutil.which('php'))
    parser.add_argument('--browser', default=os.environ.get('CWP_BROWSER_EXECUTABLE'))
    parser.add_argument('--report')
    args = parser.parse_args()
    checks = []
    try:
        if not args.php:
            raise RuntimeError('PHP executable required')
        report = run(args.php, args.browser, checks)
    except Exception as error:
        report = {'passed': False, 'scope': 'Local client StackCP controller/browser fixture did not complete; no installed or provider acceptance.', 'checks': checks, 'failure': type(error).__name__, 'captures': []}
    output = json.dumps(report, indent=2) + '\n'
    if args.report:
        path = Path(args.report)
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open('x', encoding='utf-8', newline='\n') as stream:
            stream.write(output)
    else:
        print(output, end='')
    raise SystemExit(0 if report['passed'] else 1)
