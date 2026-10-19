"""Actual PHP HTTP helpers in Chromium; all StackCP requests are intercepted.

No application database, provider credentials, provider sessions or live network.
"""
import argparse
import datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
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
import requests
from playwright.sync_api import sync_playwright
from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.x509.oid import NameOID

ROOT = Path(__file__).resolve().parents[1]
DESTINATION = 'https://www.stackcp.com/login/sso?PHPSESSID=synthetic-session%2Bonly'
FIXTURE = r'''<?php
declare(strict_types=1);
define('CWP_BOOT',true); define('CWP_ROOT',__DIR__);
require getenv('CWP_LAUNCH_TEST_ROOT').'/site/includes/security/http.php';
require getenv('CWP_LAUNCH_TEST_ROOT').'/site/app/providers/stackcp_launch_http.php';
security_headers();
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path==='/redirect') provider_stackcp_launch_redirect('https://www.stackcp.com/login/sso?PHPSESSID=synthetic-session%2Bonly');
if ($path==='/invalid') {
    try { provider_stackcp_launch_redirect((string)($_GET['url']??'')); }
    catch (RuntimeException) { http_response_code(400); echo 'Destination refused'; exit; }
}
if ($path==='/policy') {
    try { header('Content-Type: text/plain'); echo provider_stackcp_launch_form_policy((string)($_GET['policy']??'')); }
    catch (RuntimeException) { http_response_code(400); echo 'Policy refused'; }
    exit;
}
if ($path==='/duplicate') {
    header("Content-Security-Policy: default-src 'self'",false);
    try { provider_stackcp_launch_page_headers(); }
    catch (RuntimeException) { http_response_code(400); echo 'Duplicate refused'; }
    exit;
}
if ($path==='/allowed') provider_stackcp_launch_page_headers();
echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>StackCP synthetic browser contract</title><body><form method="post" action="/redirect" target="_blank" rel="noopener noreferrer"><button>Open synthetic StackCP</button></form></body></html>';
'''


def synthetic_proxy(directory):
    """A closed TLS destination: never forwards or resolves any requested host."""
    key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    name = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, 'www.stackcp.com')])
    now = datetime.datetime.now(datetime.timezone.utc)
    certificate = (x509.CertificateBuilder().subject_name(name).issuer_name(name)
                   .public_key(key.public_key()).serial_number(x509.random_serial_number())
                   .not_valid_before(now - datetime.timedelta(minutes=1))
                   .not_valid_after(now + datetime.timedelta(hours=1))
                   .add_extension(x509.SubjectAlternativeName([x509.DNSName('www.stackcp.com')]), critical=False)
                   .sign(key, hashes.SHA256()))
    cert_path, key_path = directory / 'synthetic.crt', directory / 'synthetic.key'
    cert_path.write_bytes(certificate.public_bytes(serialization.Encoding.PEM))
    key_path.write_bytes(key.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8, serialization.NoEncryption()))
    tls = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    tls.load_cert_chain(cert_path, key_path)
    received = []

    class Proxy(BaseHTTPRequestHandler):
        def log_message(self, *args):
            pass

        def do_CONNECT(self):
            if self.path != 'www.stackcp.com:443':
                try:
                    self.send_error(502)
                except OSError:
                    pass
                return
            self.send_response(200)
            self.end_headers()
            self.wfile.flush()
            try:
                with tls.wrap_socket(self.connection, server_side=True) as secure:
                    secure.settimeout(5)
                    data = b''
                    while b'\r\n\r\n' not in data and len(data) <= 16384:
                        chunk = secure.recv(4096)
                        if not chunk:
                            return
                        data += chunk
                    lines = data.decode('iso-8859-1').split('\r\n')
                    headers = dict(line.split(':', 1) for line in lines[1:] if ':' in line)
                    received.append({'request': lines[0], 'headers': {k.lower(): v.strip() for k, v in headers.items()}})
                    valid = lines[0] == 'GET /login/sso?PHPSESSID=synthetic-session%2Bonly HTTP/1.1'
                    body = b'<title>Synthetic StackCP</title><p>Intercepted only</p>' if valid else b'Refused'
                    status = b'200 OK' if valid else b'400 Bad Request'
                    secure.sendall(b'HTTP/1.1 ' + status + b'\r\nContent-Type: text/html\r\nConnection: close\r\nContent-Length: ' + str(len(body)).encode() + b'\r\n\r\n' + body)
            except (ssl.SSLError, OSError):
                return

        def do_GET(self):
            self.send_error(502)

    proxy = ThreadingHTTPServer(('127.0.0.1', 0), Proxy)
    proxy.daemon_threads = True
    thread = threading.Thread(target=proxy.serve_forever, daemon=True)
    thread.start()
    return proxy, thread, received


def run(php, executable=None):
    checks = []

    def check(name, value):
        checks.append({'name': name, 'passed': bool(value)})

    with tempfile.TemporaryDirectory(prefix='cwp-stackcp-launch-') as temporary:
        directory = Path(temporary)
        router = directory / 'router.php'
        router.write_text(FIXTURE, encoding='utf-8')
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 0))
            port = probe.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        env = dict(os.environ, CWP_LAUNCH_TEST_ROOT=str(ROOT))
        with (directory / 'php.log').open('w', encoding='utf-8') as log:
            server = subprocess.Popen([php, '-S', f'127.0.0.1:{port}', str(router)], cwd=directory, env=env, stdout=log, stderr=log)
            try:
                for _ in range(100):
                    if server.poll() is not None:
                        raise RuntimeError('Synthetic PHP server exited')
                    try:
                        requests.get(base, timeout=1)
                        break
                    except requests.ConnectionError:
                        time.sleep(.05)
                else:
                    raise RuntimeError('Synthetic PHP server did not start')
                ordinary = requests.get(base, timeout=5)
                allowed = requests.get(base + '/allowed', timeout=5)
                check('Actual default page keeps self-only form policy', "form-action 'self';" in ordinary.headers['Content-Security-Policy'])
                check('Owner helper allows the exact StackCP form destination', "form-action 'self' https://www.stackcp.com/login/sso;" in allowed.headers['Content-Security-Policy'])
                check('Owner page forbids caching and referrer forwarding', allowed.headers['Cache-Control'] == 'no-store, private, max-age=0' and allowed.headers['Referrer-Policy'] == 'no-referrer')
                check('Owner page HTML contains no session value', 'synthetic-session' not in allowed.text and 'PHPSESSID' not in allowed.text)
                response = requests.post(base + '/redirect', allow_redirects=False, timeout=5)
                check('Actual helper emits exact 303 destination', response.status_code == 303 and response.headers.get('Location') == DESTINATION)
                check('303 has no body or secret in HTML', response.content == b'')
                check('303 privacy headers protect handoff', response.headers['Referrer-Policy'] == 'no-referrer' and response.headers['Cache-Control'] == 'no-store, private, max-age=0' and response.headers['Pragma'] == 'no-cache' and response.headers['Expires'] == '0' and response.headers['Cross-Origin-Opener-Policy'] == 'same-origin')
                for index, url in enumerate([
                    '', 'http://www.stackcp.com/login/sso?PHPSESSID=x',
                    'https://www.stackcp.com.evil.invalid/login/sso?PHPSESSID=x',
                    'https://www.stackcp.com/login/sso?PHPSESSID=',
                    'https://www.stackcp.com/login/sso?PHPSESSID=x&other=y',
                    'https://www.stackcp.com/login/sso?PHPSESSID=x#fragment',
                    'https://www.stackcp.com/login/sso?PHPSESSID=x+y',
                    'https://www.stackcp.com/login/sso?PHPSESSID=%0D%0Ainjected',
                    'https://www.stackcp.com/login/sso?PHPSESSID=%FF',
                    'https://www.stackcp.com/login/sso?PHPSESSID=%2b',
                    'https://www.stackcp.com/login/sso?PHPSESSID=' + 'x' * 4097,
                ]):
                    bad = requests.get(base + '/invalid', params={'url': url}, allow_redirects=False, timeout=5)
                    check(f'Destination refusal {index + 1}', bad.status_code == 400 and 'Location' not in bad.headers and bad.text == 'Destination refused')
                for index, policy in enumerate([
                    "default-src 'self'", "form-action *", "form-action 'self'; form-action *",
                    "form-action 'self'; FORM-ACTION 'none'", "form-action 'none'",
                ]):
                    bad = requests.get(base + '/policy', params={'policy': policy}, timeout=5)
                    check(f'Unknown or duplicate policy refused {index + 1}', bad.status_code == 400)
                check('Duplicate CSP response headers refused', requests.get(base + '/duplicate', timeout=5).status_code == 400)
                with sync_playwright() as playwright:
                    proxy, proxy_thread, received = synthetic_proxy(directory)
                    options = {'headless': True, 'proxy': {'server': f'http://127.0.0.1:{proxy.server_port}', 'bypass': '127.0.0.1,localhost'}}
                    if executable:
                        options['executable_path'] = executable
                    browser = playwright.chromium.launch(**options)
                    try:
                        context = browser.new_context(service_workers='block', ignore_https_errors=True)
                        context.set_default_timeout(10000)
                        page = context.new_page()
                        page.goto(base + '/allowed')
                        with context.expect_page() as opened:
                            page.get_by_role('button', name='Open synthetic StackCP').click()
                        popup = opened.value
                        popup.wait_for_url(DESTINATION)
                        popup.wait_for_load_state()
                        check('Chromium completes synthetic 303 in new tab', popup.url == DESTINATION and popup.title() == 'Synthetic StackCP')
                        handoffs = [item for item in received if item['request'].startswith('GET /login/sso?')]
                        check('Exactly one synthetic destination request', len(handoffs) == 1)
                        check('Browser sends no referrer to destination', len(handoffs) == 1 and 'referer' not in handoffs[0]['headers'])
                        check('Destination has no opener', popup.evaluate('window.opener === null'))
                        check('Origin stays on owner page without session URL', page.url == base + '/allowed' and 'synthetic-session' not in page.content())
                        page.goto(base + '/ordinary')
                        with context.expect_event('console', predicate=lambda message: 'form-action' in message.text) as violation:
                            page.get_by_role('button', name='Open synthetic StackCP').click()
                        check('Unmodified self-only form policy blocks the same cross-origin handoff', 'form-action' in violation.value.text and len([item for item in received if item['request'].startswith('GET /login/sso?')]) == 1)
                        context.close()
                    finally:
                        browser.close()
                        proxy.shutdown()
                        proxy.server_close()
                        proxy_thread.join(timeout=5)
            finally:
                server.terminate()
                try:
                    server.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=10)
    return {'passed': all(item['passed'] for item in checks), 'scope': 'Actual PHP HTTP helpers and Chromium on loopback; exact synthetic StackCP destination intercepted. No SQL or live provider execution.', 'checks': checks}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', default=os.environ.get('PHP_BINARY') or shutil.which('php'))
    parser.add_argument('--browser', default=os.environ.get('CWP_BROWSER_EXECUTABLE'))
    parser.add_argument('--report')
    args = parser.parse_args()
    if not args.php:
        parser.error('PHP executable is required')
    try:
        report = run(args.php, args.browser)
    except Exception as error:
        report = {
            'passed': False,
            'scope': 'Loopback PHP/Chromium helper fixture did not complete; no provider acceptance.',
            'checks': [{'name': 'Browser helper execution completed', 'passed': False, 'error_class': type(error).__name__}],
        }
    encoded = json.dumps(report, indent=2)
    if args.report:
        Path(args.report).parent.mkdir(parents=True, exist_ok=True)
        Path(args.report).write_text(encoded + '\n', encoding='utf-8')
    print(encoded)
    raise SystemExit(0 if report['passed'] else 1)
