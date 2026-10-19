"""Packaged current-clock checkout after its exact completed native fixture. No external transports."""
import argparse
import ast
import json
import os
from pathlib import Path
import re
import subprocess
import time
import traceback
from urllib.parse import urljoin

from hosting_catalog_purchase import clean_environment, runtime_selection, sha
from hosting_purchase_handoff_browser import fresh_directory, extract_package, DISABLED
from hosting_checkout_http_cases import run_browser, CAPTURE_STEMS
from catalog_storefront_checkout_cases import run_browser as storefront_browser, CAPTURES as STOREFRONT_CAPTURES
from hosting_catalog_plan_setup_cases import CAPTURES as PLAN_SETUP_CAPTURES
from hosting_purchase_inbox_cases import run_browser as inbox_browser, CAPTURES as INBOX_CAPTURES

ROOT = Path(__file__).resolve().parents[1]
WEB = ROOT/'test-runtime/hosting-checkout-web'; SITE = WEB/'hosting-checkout'
SESSIONS = ROOT/'test-runtime/hosting-checkout-browser-sessions'
BASE = 'http://127.0.0.1:8091'
CAPTURES = tuple('Hosting-Checkout-' + stem + '-' + size + '.png' for stem in CAPTURE_STEMS for size in ('Desktop', 'Mobile'))
ALL_CAPTURES = CAPTURES + STOREFRONT_CAPTURES + PLAN_SETUP_CAPTURES + INBOX_CAPTURES
SOURCE_PATHS = [
    'tests/hosting_purchase_inbox_cases.py',
    'site/app/hosting/checkout_purchases.php', 'site/app/hosting/checkout_purchases_views.php',
    'site/admin/controllers/hosting_purchases.php', 'site/admin/views/hosting_purchases.php',
    'site/portal/controllers/hosting_purchases.php', 'site/portal/views/hosting_purchases.php',
    'site/admin/controllers/router.php', 'site/portal/controllers/router.php',
    'site/includes/layout/service_orders.php', 'site/includes/layout/hosting_order_links.php', 'site/app/hosting/order_views.php',
    'tests/hosting_catalog_plan_setup_cases.py',
    'site/app/hosting/catalog_offer_setup.php', 'site/app/hosting/catalog_offer_setup_document.php',
    'site/app/hosting/catalog_offer_setup_views.php', 'site/app/hosting/catalog_offer_views.php', 'site/app/plans/records.php',
    'tests/catalog_storefront_checkout_cases.py',
    'tests/hosting_checkout_http_browser.py', 'tests/hosting_checkout_http_cases.py', 'tests/hosting_checkout_http_fixture.php', 'tests/hosting_checkout_http_guards.py',
    'tests/hosting_checkout_native.php', 'tests/hosting_catalog_purchase.py', 'tests/hosting_purchase_handoff_browser.py',
    'tests/hosting_purchase_handoff_cases.py', 'tests/hosting_catalog_browser.py', 'tests/browser_acceptance.py',
    'site/app/hosting/catalog_checkout.php', 'site/app/hosting/catalog_checkout_storage.php', 'site/app/hosting/catalog_checkout_document.php',
    'site/app/hosting/catalog_offers.php', 'site/app/hosting/catalog_offers_context.php', 'site/app/hosting/catalog_offers_storage.php',
    'site/admin/controllers/hosting_catalog.php', 'site/admin/views/hosting_catalog.php', 'site/portal/controllers/hosting_checkout.php',
    'site/portal/views/hosting_checkout.php', 'site/portal/controllers/actions.php', 'site/admin/controllers/actions.php',
    'site/admin/controllers/support.php', 'site/includes/layout/ledger.php', 'site/includes/layout/plans.php', 'site/includes/layout/recurring_billing.php', 'site/includes/auth/session.php',
    'site/app/providers/settings.php', 'site/app/providers/plan_catalog.php', 'site/app/providers/inventory.php',
    'site/app/billing/ledger.php', 'site/database/migrations/attempts_migrate.php',
]


def require(value, message):
    if not value: raise RuntimeError(message)


def write(path, value):
    path.write_text(json.dumps(value, indent=2) + '\n', encoding='utf-8')


def native_labels(source):
    """Exact literal native producer labels, including its closed two-key contact loop."""
    text = source
    for line in source.splitlines():
        if line.lstrip().startswith("foreach(['contact_id','contact_auth'] as $key){"):
            body = line.split('){', 1)[1].rsplit('}', 1)[0]
            text = text.replace(line, '\n'.join(body.replace('$key', repr(key)) for key in ('contact_id', 'contact_auth')))
    text = re.sub(r"'\.'(contact_id|contact_auth)'", r"\1'", text)
    labels = re.findall(r"\bhcc_(?:ok|deny)\('([^']*)'", text)
    require(len(labels) == 79 and len(labels) == len(set(labels)), 'Exact corrected native producer required')
    return labels


def validate_native(raw, producer, version):
    require(0 < len(raw) <= 256*1024, 'Bounded original native report required')
    def pairs(items):
        result = {}
        for key, value in items:
            require(key not in result, 'Duplicate native report key'); result[key] = value
        return result
    report = json.loads(raw, object_pairs_hook=pairs)
    checks = report.get('checks', [])
    require(report.get('passed') is True and not report.get('error') and [c.get('name') for c in checks] == native_labels(producer) and all(c.get('passed') is True for c in checks), 'Every exact native assertion must have passed')
    runtime = report.get('runtime', {})
    require(isinstance(runtime.get('php'), str) and runtime['php'].startswith(version + '.') and isinstance(runtime.get('database'), str) and runtime['database'], 'Actual native runtime required')
    return report


def output_paths(full_suite):
    qa = ROOT/'qa' if full_suite else ROOT/'qa/hosting-checkout-http'
    files = {name: qa/(('hosting-checkout-http-' + name) if full_suite else name) for name in ('results.json', 'progress.txt', 'commands.json', 'server.log')}
    return qa, files


def prepare_outputs(full_suite):
    qa, outputs = output_paths(full_suite)
    if full_suite:
        for parent in (qa, *qa.parents): require(not parent.is_symlink() and (not parent.exists() or parent.is_dir()), 'Exact output directory required')
        require(all(not p.exists() and not p.is_symlink() for p in [*outputs.values(), *(qa/n for n in ALL_CAPTURES)]), 'Preserve prior checkout HTTP outputs')
        qa.mkdir(parents=True, exist_ok=True)
    else: fresh_directory(qa)
    fresh_directory(WEB); SITE.mkdir(mode=0o700); fresh_directory(SESSIONS)
    return qa, outputs


def main(full_suite=False):
    require(os.name == 'posix' and os.geteuid() != 0 and os.environ.get('CWP_TEST_DATABASE') == 'cwp_test', 'Use the unprivileged isolated Linux job')
    env = clean_environment(dict(os.environ)); php, port = runtime_selection(env, True)
    env.update(CWP_TEST_HOSTING_CHECKOUT_HTTP='1', CWP_TEST_HOST='127.0.0.1', CWP_TEST_PORT=str(port))
    import requests
    from bs4 import BeautifulSoup
    from playwright.sync_api import sync_playwright
    from hosting_catalog_browser import fixture_diagnostic
    qa, outputs = prepare_outputs(full_suite)
    checks = []; commands = []; source = {}; package = {}; runtime = {}; result = {}; failure = None; complete = False
    server = None; log = None

    def check(name, condition):
        require(name not in {c['name'] for c in checks}, 'Duplicate checkout check label')
        checks.append({'name': name, 'passed': bool(condition)}); write(outputs['progress.txt'], {'complete': False, 'checks': checks})
        if not condition: raise AssertionError(name)

    def fixture(operation, payload=None):
        child = subprocess.run([php, '-d', 'display_errors=0', '-d', 'zend.exception_ignore_args=1', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=' + DISABLED,
            str(ROOT/'tests/hosting_checkout_http_fixture.php'), operation], cwd=ROOT, env=env, input=json.dumps(payload) if payload is not None else None,
            capture_output=True, text=True, encoding='utf-8', timeout=180)
        record = {'operation': operation, 'exit_code': child.returncode, 'stdout_bytes': len(child.stdout.encode()), 'stdout_sha256': sha(child.stdout.encode()), 'stderr_bytes': len(child.stderr.encode()), 'stderr_sha256': sha(child.stderr.encode())}
        if child.returncode != 0 or child.stderr: record['diagnostic'] = fixture_diagnostic(child.stderr, operation)
        commands.append(record); write(outputs['commands.json'], commands)
        require(child.returncode == 0 and not child.stderr and len(child.stdout.encode()) <= 256*1024, 'Checkout bridge failed: ' + operation)
        return json.loads(child.stdout)

    try:
        head = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
        check('Checkout browser binds the actual original CI source', head == env['GITHUB_SHA'] and env['GITHUB_RUN_ATTEMPT'] == '1')
        source = {'commit': head, 'tree': subprocess.check_output(['git', 'rev-parse', 'HEAD^{tree}'], cwd=ROOT, text=True).strip(), 'run': env['GITHUB_RUN_ID'], 'attempt': env['GITHUB_RUN_ATTEMPT'], 'files': {}}
        for path in SOURCE_PATHS:
            raw = (ROOT/path).read_bytes(); source['files'][path] = sha(raw)
            check('Checkout browser source equals Git: ' + path, raw == subprocess.check_output(['git', 'show', head + ':' + path], cwd=ROOT))
        native_raw = (ROOT/'qa/hosting-checkout-native-results.json').read_bytes()
        native = validate_native(native_raw, (ROOT/'tests/hosting_checkout_native.php').read_text(encoding='utf-8'), env['CWP_PHP_VERSION'])
        source['native_report_sha256'] = sha(native_raw); env['CWP_CHECKOUT_NATIVE_REPORT_SHA256'] = sha(native_raw); runtime['native'] = native['runtime']
        check('Checkout browser requires all 79 exact original native outcomes', True)
        archives = list((ROOT/'dist').glob('CajunWebPro-*-COMPLETE-UPLOAD.zip'))
        check('Checkout browser uses one exact current COMPLETE package', len(archives) == 1)
        archive = archives[0]; mp = archive.with_suffix('.manifest.json'); manifest = json.loads(mp.read_bytes()); members = extract_package(archive, manifest, SITE, head)
        package = {'name': archive.name, 'sha256': manifest['sha256'], 'manifest_sha256': sha(mp.read_bytes()), 'bytes': archive.stat().st_size, 'members': members}
        check('Checkout package passes original source member CRC and containment guards', True)
        seed = fixture('prepare')
        check('Bridge preserves native financial history and refreshes only one closed synthetic read', seed['prepared'] is True and seed['prefix'] == 'hcc_' and seed['native_report_sha256'] == sha(native_raw) and seed['synthetic_refresh_calls'] == 1 and seed['native_end_state_sealed'] is False)
        helper_names = {'soup', 'token', 'form_data', 'form_for', 'submit'}; tree = ast.parse((ROOT/'tests/browser_acceptance.py').read_text(encoding='utf-8'))
        definitions = [n for n in tree.body if isinstance(n, ast.FunctionDef) and n.name in helper_names]
        check('Checkout browser reuses all five original form helpers', len(definitions) == 5 and {n.name for n in definitions} == helper_names)
        helpers = {'BeautifulSoup': BeautifulSoup, 'urljoin': urljoin}; exec(compile(ast.Module(body=definitions, type_ignores=[]), 'browser_acceptance.py', 'exec'), helpers)
        log = outputs['server.log'].open('x', encoding='utf-8')
        server = subprocess.Popen([php, '-d', 'display_errors=0', '-d', 'zend.exception_ignore_args=1', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=' + DISABLED, '-d', 'session.save_path=' + str(SESSIONS), '-S', '127.0.0.1:8091', '-t', str(WEB)], cwd=ROOT, env=env, stdout=log, stderr=log)
        deadline = time.monotonic() + 15
        while True:
            require(server.poll() is None, 'Installed server exited before readiness')
            try:
                response = requests.get(BASE + '/hosting-checkout/admin/index.php?view=login', timeout=1, allow_redirects=False); response.raise_for_status(); break
            except requests.RequestException:
                if time.monotonic() >= deadline: raise RuntimeError('Installed server did not become ready')
                time.sleep(.1)
        check('Checkout server exposes the installed login form', response.status_code == 200 and 'name="action" value="login"' in response.text)
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            try:
                browser_env = {**helpers, 'BASE': BASE, 'QA': qa, 'check': check, 'fixture': fixture, 'seed': seed}
                result = run_browser(browser_env, browser)
                result['catalog_storefront'] = storefront_browser(browser_env, browser)
                check('Installed storefront completes the selected-plan login and invoice journey',
                      result['catalog_storefront']['passed'] is True
                      and tuple(c['name'] for c in result['catalog_storefront']['captures']) == STOREFRONT_CAPTURES
                      and all((qa/n).is_file() and (qa/n).stat().st_size > 0 for n in STOREFRONT_CAPTURES))
                check('Installed imported product completes plan setup and separate publication preview',
                      result['catalog_storefront']['plan_setup']['passed'] is True
                      and tuple(c['name'] for c in result['catalog_storefront']['plan_setup']['captures']) == PLAN_SETUP_CAPTURES
                      and all((qa/n).is_file() and (qa/n).stat().st_size > 0 for n in PLAN_SETUP_CAPTURES))
                result['purchase_inbox'] = inbox_browser(browser_env, browser)
                check('Installed purchase inbox follows exact original records without new business writes',
                      result['purchase_inbox']['passed'] is True
                      and tuple(c['name'] for c in result['purchase_inbox']['captures']) == INBOX_CAPTURES
                      and all(not (qa/n).is_symlink() and (qa/n).is_file() and (qa/n).stat().st_size > 0 for n in INBOX_CAPTURES))
            finally: browser.close()
        check('All twelve current checkout captures were produced', {p.name for p in qa.glob('Hosting-Checkout-*.png')} == set(CAPTURES))
        complete = True
    except Exception as error:
        names = {'hosting_checkout_http_browser.py', 'hosting_checkout_http_cases.py', 'catalog_storefront_checkout_cases.py', 'hosting_catalog_plan_setup_cases.py', 'hosting_purchase_inbox_cases.py'}
        locations = [{'file': Path(f.filename).name, 'line': f.lineno, 'function': f.name} for f in traceback.extract_tb(error.__traceback__) if Path(f.filename).name in names][-8:]
        failure = {'class': type(error).__name__, 'message': checks[-1]['name'] if checks and checks[-1]['passed'] is False else 'Packaged checkout stopped; inspect bounded source locations.', 'locations': locations}
    finally:
        if server is not None:
            server.terminate()
            try: server.wait(timeout=10)
            except subprocess.TimeoutExpired: server.kill(); server.wait()
        if log is not None:
            log.close(); clean = re.search(r'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated):', outputs['server.log'].read_text(encoding='utf-8', errors='replace')) is None
            checks.append({'name': 'Checkout HTTP server has no PHP diagnostics after shutdown', 'passed': clean}); complete = complete and clean
        owned = [p for name,p in outputs.items() if name != 'results.json'] + [qa/n for n in ALL_CAPTURES]
        files = {p.name: {'bytes': p.stat().st_size, 'sha256': sha(p.read_bytes())} for p in owned if p.is_file()}
        report = {'passed': complete and all(c['passed'] is True for c in checks), 'source': source, 'package': package, 'runtime': runtime, 'checks': checks, 'result': result, 'failure': failure, 'files': files, 'full_acceptance': False, 'visual_approval': False, 'scope': 'Actual packaged owner publication and primary-client first unpaid invoice now; separate native synthetic receipt predates this browser. Contact-key denial is not contact-login acceptance. No live payment, charge or provider activation.'}
        write(outputs['results.json'], report); print(json.dumps({'passed': report['passed'], 'checks': len(checks), 'full_acceptance': False}))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    parser = argparse.ArgumentParser(); parser.add_argument('--full-suite', action='store_true')
    raise SystemExit(main(parser.parse_args().full_suite))
