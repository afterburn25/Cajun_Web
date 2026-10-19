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
    'tests/support_stackcp_http.py', 'tests/support_stackcp_http_fixture.php', 'tests/stackcp_launch_browser.py',
    'site/portal/controllers/support_stackcp.php', 'site/portal/controllers/router.php',
    'site/portal/controllers/actions.php', 'site/admin/controllers/provider_support_stackcp.php',
    'site/admin/controllers/providers.php', 'site/admin/controllers/actions.php',
    'site/app/providers/support_stackcp_permissions.php', 'site/app/providers/support_stackcp_launch.php',
    'site/app/support/sessions.php', 'site/app/clients/identity.php', 'site/admin/controllers/support.php',
    'site/assets/css/site.css', 'site/assets/css/premium.css', 'site/assets/css/growth.css',
    'site/admin/controllers/auth.php', 'site/app/providers/support_stackcp_views.php',
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
                self.active = 'data-support-stackcp-open' in attrs
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



SUPPORT_ID = 'a' * 32
ROUTE = '/portal/index.php?' + urlencode({'support': SUPPORT_ID, 'support_hosting_panel': '1'})


def run(php, executable, checks):
    from playwright.sync_api import sync_playwright

    def check(name, value):
        if any(row['name'] == name for row in checks):
            raise RuntimeError('Duplicate check label')
        checks.append({'name': name, 'passed': bool(value)})
        if not value:
            raise AssertionError('Named HTTP/browser check failed')

    with tempfile.TemporaryDirectory(prefix='cwp-support-stackcp-http-') as temporary:
        directory = Path(temporary)
        (directory / 'sessions').mkdir(mode=0o700)
        nonce = os.urandom(16).hex()
        env = dict(os.environ, CWP_SUPPORT_STACKCP_HTTP_FIXTURE='1', CWP_SUPPORT_STACKCP_SOURCE=str(ROOT), CWP_SUPPORT_STACKCP_TEMP=str(directory), CWP_SUPPORT_STACKCP_HEALTH=nonce)
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 0))
            port = probe.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        fixture = ROOT / 'tests/support_stackcp_http_fixture.php'
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
                check('Loopback-only fixture starts without application SQL bootstrap', server.poll() is None)
                for mode in ('anonymous', 'client', 'owner', 'staff', 'contact-null', 'contact-auth-empty'):
                    session = LocalSession(base)
                    session.seed(mode)
                    for method in ('GET', 'POST'):
                        status, headers, body = session.request(ROUTE, {'action': 'support_stackcp_open'} if method == 'POST' else None)
                        state = session.state()
                        check('Raw route excludes unsupported context before support resolution: ' + mode + ' ' + method,
                              status == 403 and state['counts'].get('resolves', 0) == 0 and state['counts'].get('opens', 0) == 0 and 'no-store' in headers['Cache-Control'] and 'data-support-stackcp-open' not in body)
                for mode in ('uid-null', 'expired', 'binding-swapped', 'no-permission'):
                    session = LocalSession(base)
                    session.seed(mode)
                    status, headers, body = session.request(ROUTE)
                    state = session.state()
                    check('Resolved support policy rejects without review or delivery: ' + mode,
                          status == 403 and state['counts'].get('reviews', 0) == 0 and state['counts'].get('synthetic_deliveries', 0) == 0 and 'data-support-stackcp-open' not in body)
                session = LocalSession(base)
                session.seed('support')
                for label, route in (('missing', '/portal/index.php?support_hosting_panel=1'), ('array', '/portal/index.php?support[]=x&support_hosting_panel=1'), ('foreign', ROUTE.replace(SUPPORT_ID, '9' * 32))):
                    status, _, body = session.request(route)
                    check('Support route identity refuses ' + label, status == 403 and 'data-support-stackcp-open' not in body)
                session.seed('mixed-client')
                status, _, body = session.request(ROUTE)
                check('Ambient ordinary client login never replaces resolved staff support client', status == 200 and 'Client &lt;script&gt;bad()&lt;/script&gt;' in body and session.state()['counts']['reviews'] == 1)
                for suffix in ('service_link[]=bad', 'service_link=invalid'):
                    session.seed('support')
                    status, _, body = session.request(ROUTE + '&' + suffix)
                    check('Malformed selected service stays unavailable: ' + suffix, status == 200 and 'data-support-stackcp-open' not in body and session.state()['counts'].get('reviews', 0) == 0)
                for mode in ('owner', 'staff', 'support', 'contact-null'):
                    session.seed(mode)
                    status, _, body = session.request('/owner' + ('?support=' + SUPPORT_ID if mode == 'support' else ''))
                    check('Owner settings route authority: ' + mode, status == (200 if mode == 'owner' else 403) and ('data-support-stackcp-permission-form' in body) == (mode == 'owner'))

                with loopback_bypass_driver(), sync_playwright() as playwright:
                    proxy, thread, received = closed_proxy(directory)
                    browser = None
                    try:
                        options = {'headless': True, 'proxy': {'server': f'http://127.0.0.1:{proxy.server_address[1]}', 'bypass': '127.0.0.1,localhost'}}
                        if executable:
                            options['executable_path'] = executable
                        browser = playwright.chromium.launch(**options)
                        context = browser.new_context(service_workers='block', ignore_https_errors=True)
                        context.set_default_timeout(10000)
                        page = context.new_page()
                        page.goto(base + '/__seed?mode=support')
                        response = page.goto(base + ROUTE)
                        form = page.locator('[data-support-stackcp-open]')
                        form.wait_for()
                        fields = {item['name']: item['value'] for item in form.locator('input[type=hidden]').evaluate_all('(els)=>els.map(e=>({name:e.name,value:e.value}))')}
                        check('Support form binds exact support ID and immutable approval fields', set(fields) == {'csrf', 'action', 'support_id', 'permission_id', 'admission_id', 'expires_epoch', 'review_proof'} and fields['action'] == 'support_stackcp_open' and fields['support_id'] == SUPPORT_ID)
                        check('Support form does not solicit owner password or posted actor', form.locator('input[type=password],input[name=client_id],input[name=staff_id],input[name=actor_id]').count() == 0)
                        check('Support form uses protected new tab and whole-user consent', form.get_attribute('target') == '_blank' and form.get_attribute('rel') == 'noopener noreferrer' and not form.locator('[name=confirmed]').is_checked() and 'whole account' in page.inner_text('body') and 'does not sign out' in page.inner_text('body'))
                        check('Support response allows only documented form destination and forbids referrer/cache', "form-action 'self' https://www.stackcp.com/login/sso;" in response.headers['content-security-policy'] and response.headers['cache-control'] == 'no-store, private, max-age=0' and response.headers['referrer-policy'] == 'no-referrer')
                        check('Hostile staff client and hosting metadata is text without active elements', page.locator('script,img[src=x]').count() == 0 and 'Staff <img src=x onerror=alert(1)>' in page.inner_text('body') and 'Hosting <script>bad()</script>' in page.inner_text('body'))
                        check('Support HTML never discloses provider URL or session', 'PHPSESSID' not in page.content() and 'www.stackcp.com' not in page.content())
                        before = context.request.get(base + '/__state').json()
                        check('Support GET records only modeled view audit and no launch or permission effect', before['counts'].get('support_audits') == 1 and before['counts'].get('opens', 0) == 0 and before['counts'].get('permission_writes', 0) == 0)
                        for width, height in ((390, 844), (1440, 1000)):
                            page.set_viewport_size({'width': width, 'height': height})
                            form.locator('[name=confirmed]').focus()
                            check('Actual support controls are keyboard reachable without page overflow at ' + str(width), form.locator('button').is_visible() and form.locator('[name=confirmed]').evaluate('(e)=>e===document.activeElement') and page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
                        failed = context.request.post(base + ROUTE, form={**fields, 'csrf': 'wrong', 'confirmed': '1'})
                        after = context.request.get(base + '/__state').json()
                        retained, checked = retained_form(failed.text())
                        check('Common support CSRF rejects before open and retains original form', 'Your support form expired.' in failed.text() and retained == fields and not checked and after['counts'].get('opens', 0) == 0 and after['counts']['reviews'] == before['counts']['reviews'])
                        failed = context.request.post(base + ROUTE, form=fields)
                        after = context.request.get(base + '/__state').json()
                        retained, checked = retained_form(failed.text())
                        check('Unconfirmed POST preserves exact original proof and retry consent with no renewal', 'Review and confirm the original approval.' in failed.text() and retained == fields and not checked and 'Continue this original' in failed.text() and after['counts']['reviews'] == before['counts']['reviews'] and after['counts'].get('synthetic_deliveries', 0) == 0)
                        for key, value in (('support_id', '9' * 32), ('permission_id', 'f' * 32), ('admission_id', 'invalid'), ('expires_epoch', '0'), ('review_proof', 'invalid')):
                            rejected = context.request.post(base + ROUTE, form={**fields, key: value, 'confirmed': '1'})
                            after = context.request.get(base + '/__state').json()
                            check('Foreign or malformed approval is unavailable without replacement: ' + key, not retained_form(rejected.text())[0] and after['counts']['reviews'] == before['counts']['reviews'] and after['counts'].get('synthetic_deliveries', 0) == 0)
                        form.locator('[name=confirmed]').check()
                        with context.expect_page() as opened:
                            form.get_by_role('button', name='Open hosting control panel').click()
                        popup = opened.value
                        popup.wait_for_url(DESTINATION)
                        popup.wait_for_load_state()
                        handoffs = [row for row in received if row['request'].startswith('GET /login/sso?')]
                        check('Actual support controller redirects through closed fixed synthetic TLS destination', popup.url == DESTINATION and popup.title() == 'Synthetic StackCP' and len(handoffs) == 1)
                        check('Support destination has no opener or referrer and origin retains no session', popup.evaluate('window.opener === null') and 'referer' not in handoffs[0]['headers'] and page.url == base + ROUTE and 'PHPSESSID' not in page.content())
                        before = context.request.get(base + '/__state').json()
                        replay = context.request.post(base + ROUTE, form={**fields, 'confirmed': '1'}, max_redirects=0)
                        after = context.request.get(base + '/__state').json()
                        check('Consumed modeled attempt is projected without redirect provider traffic or new review', replay.status == 200 and 'location' not in replay.headers and after['counts']['synthetic_deliveries'] == before['counts']['synthetic_deliveries'] and after['counts']['reviews'] == before['counts']['reviews'] and 'remote sign-in are not confirmed' in replay.text())
                        context.close()

                        for label, suffix in (('page 2', 'support_panel_page=2'), ('filtered service', 'service_link=' + 'e' * 32)):
                            navigation = browser.new_context(service_workers='block', ignore_https_errors=True)
                            try:
                                p = navigation.new_page()
                                p.goto(base + '/__seed?mode=support')
                                p.goto(base + ROUTE + '&' + suffix)
                                selected = p.locator('[data-support-stackcp-open]')
                                original = {row['name']: row['value'] for row in selected.locator('input[type=hidden]').evaluate_all('(els)=>els.map(e=>({name:e.name,value:e.value}))')}
                                action = selected.get_attribute('action')
                                check('Selected support navigation retains session and exact service: ' + label, action == ROUTE + '&' + suffix and original['support_id'] == SUPPORT_ID and original['permission_id'] == 'f' * 32)
                                previous = navigation.request.get(base + '/__state').json()
                                rejected = navigation.request.post(base + action, form=original)
                                current = navigation.request.get(base + '/__state').json()
                                retained, checked = retained_form(rejected.text())
                                check('Selected failed support POST retains original card and approval: ' + label, 'Synthetic second hosting' in rejected.text() and retained == original and not checked and current['counts']['reviews'] == previous['counts']['reviews'] and current['counts'].get('synthetic_deliveries', 0) == 0)
                            finally:
                                navigation.close()
                        for mode in ('expired', 'ended', 'binding-swapped', 'revoked'):
                            isolated = LocalSession(base)
                            isolated.seed('support')
                            _, _, html = isolated.request(ROUTE)
                            original, _ = retained_form(html)
                            isolated.request('/__invalidate?' + urlencode({'mode': mode}))
                            status, _, html = isolated.request(ROUTE, {**original, 'confirmed': '1'})
                            state = isolated.state()
                            check('Captured approval cannot outlive current support authority: ' + mode, status == (200 if mode == 'revoked' else 403) and 'data-support-stackcp-open' not in html and state['counts']['reviews'] == 1 and state['counts'].get('synthetic_deliveries', 0) == 0)

                        owner = browser.new_context(service_workers='block', ignore_https_errors=True)
                        owner_page = owner.new_page()
                        owner_page.goto(base + '/__seed?mode=owner')
                        response = owner_page.goto(base + '/owner')
                        summary = owner_page.locator('#support-stackcp > summary')
                        summary.focus()
                        summary.press('Enter')
                        permission = owner_page.locator('[data-support-stackcp-permission-form]')
                        check('Owner disclosure opens by keyboard and names actual selected staff', permission.is_visible() and owner_page.locator('[data-support-stackcp-staff] select').input_value() == '7' and 'Staff <img src=x onerror=alert(1)>' in permission.locator('..').inner_text())
                        owner_fields = {row['name']: row['value'] for row in permission.locator('input[type=hidden]').evaluate_all('(els)=>els.map(e=>({name:e.name,value:e.value}))')}
                        check('Owner delegation binds named staff and untouched owner CSP', set(owner_fields) == {'csrf', 'action', 'profile_id', 'package_id', 'staff_id', 'review_proof', 'enabled'} and owner_fields['staff_id'] == '7' and "form-action 'self';" in response.headers['content-security-policy'])
                        failed = owner.request.post(base + '/owner', form={**owner_fields, 'csrf': 'wrong', 'confirmed': '1', 'current_password': 'Synthetic owner password'})
                        state = owner.request.get(base + '/__state').json()
                        check('Common administrator CSRF blocks before permission mutation', 'Your session expired.' in failed.text() and state['counts'].get('permission_calls', 0) == 0)
                        permission.locator('[name=current_password]').fill('PRIVATE_WRONG_PASSWORD')
                        permission.locator('[name=confirmed]').check()
                        permission.get_by_role('button', name='Enable hosting support').click()
                        permission = owner_page.locator('[data-support-stackcp-permission-form]')
                        check('Owner password refusal retains exact named approval and clears secret and consent', 'Your current password is not correct.' in owner_page.get_by_role('alert').inner_text() and permission.locator('[name=review_proof]').input_value() == owner_fields['review_proof'] and permission.locator('[name=staff_id]').input_value() == '7' and permission.locator('[name=current_password]').input_value() == '' and not permission.locator('[name=confirmed]').is_checked() and 'PRIVATE_WRONG_PASSWORD' not in owner_page.content())
                        check('Owner failed POST does not record permission', owner.request.get(base + '/__state').json()['counts'].get('permission_writes', 0) == 0)
                        permission.locator('[name=current_password]').fill('Synthetic owner password')
                        permission.locator('[name=confirmed]').check()
                        permission.get_by_role('button', name='Enable hosting support').click()
                        owner_page.wait_for_url(base + '/owner?view=provider-settings&support_stackcp_staff=7#support-stackcp')
                        state = owner.request.get(base + '/__state').json()
                        check('Owner action records only selected staff policy and redirects locally', state['counts']['permission_writes'] == 1 and state['permission_target'] == 7 and state['permission_enabled'] is True and len([row for row in received if row['request'].startswith('GET /login/sso?')]) == 1)
                        owner_page.locator('[data-support-stackcp-staff] select').select_option('8')
                        owner_page.get_by_role('button', name='Show hosting accounts').click()
                        check('Owner staff selector uses exact local GET with selected policy target', 'support_stackcp_staff=8' in owner_page.url and owner_page.locator('[data-support-stackcp-permission-form] [name=staff_id]').input_value() == '8' and owner.request.get(base + '/__state').json()['counts']['permission_writes'] == 1)
                        owner_page.goto(base + '/__seed?mode=stale-owner')
                        owner_page.goto(base + '/owner?view=provider-settings&support_stackcp_staff=7')
                        stale = owner_page.locator('[data-support-stackcp-permission-form]')
                        check('Stale owner delegation can explicitly disable future launches', 'Access needs a new owner review.' in owner_page.inner_text('body') and stale.locator('[name=enabled]').input_value() == '0' and 'Existing provider sessions' in stale.inner_text())
                        stale.locator('[name=current_password]').fill('Synthetic owner password')
                        stale.locator('[name=confirmed]').check()
                        stale.get_by_role('button', name='Disable hosting support').click()
                        owner_page.wait_for_url(base + '/owner?view=provider-settings&support_stackcp_staff=7#support-stackcp')
                        check('Explicit disable changes only modeled delegation not remote session', owner.request.get(base + '/__state').json()['permission_enabled'] is False and len([row for row in received if row['request'].startswith('GET /login/sso?')]) == 1)
                        owner_page.goto(base + '/__seed?mode=cold-owner')
                        owner_page.goto(base + '/owner?view=provider-settings&support_stackcp_staff=7')
                        check('Cold owner UI exposes explicit preparation without automatic schema write', owner_page.locator('[data-support-stackcp-prepare]').count() == 1 and owner_page.locator('[data-support-stackcp-permission-form]').count() == 0 and owner.request.get(base + '/__state').json()['counts'].get('prepare_writes', 0) == 0)
                        owner.close()
                        check('Only one closed synthetic destination GET occurs in entire browser journey', len([row for row in received if row['request'].startswith('GET /login/sso?')]) == 1)
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
    return {'passed': True, 'scope': 'Actual extracted PHP support/owner controllers, renderers, support CSRF and actor checks, exact router segment and common POST dispatchers over loopback HTTP with Chromium. Session, current SQL authority, journal and business mutations are explicit models; destination is a closed synthetic TLS proxy. No installed SQL, provider exchange or remote login acceptance.', 'checks': checks, 'source_sha256': {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in SOURCE_PATHS}, 'captures': []}


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
        report = {'passed': False, 'scope': 'Local modeled support controller/browser fixture did not complete; no installed or provider acceptance.', 'checks': checks, 'failure': type(error).__name__, 'source_sha256': {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in SOURCE_PATHS}, 'captures': []}
    output = json.dumps(report, indent=2) + '\n'
    if args.report:
        path = Path(args.report)
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open('x', encoding='utf-8', newline='\n') as stream:
            stream.write(output)
    else:
        print(output, end='')
    raise SystemExit(0 if report['passed'] else 1)
