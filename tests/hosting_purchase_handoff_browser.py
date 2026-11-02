"""Packaged browser re-link of a completed synthetic joined purchase. Linux CI only."""
import ast
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import subprocess
import time
import traceback
from urllib.parse import urljoin
import zipfile

from hosting_catalog_purchase import arguments, clean_environment, decode_report, purchase_output_paths, runtime_selection, sha
from hosting_purchase_handoff_cases import run_browser

ROOT = Path(__file__).resolve().parents[1]
QA = ROOT / 'qa/hosting-purchase-http'
WEB = ROOT / 'test-runtime/hosting-purchase-web'
SITE = WEB / 'hosting-purchase'
BASE = 'http://127.0.0.1:8091'
PHASES = ('prepare', 'due', 'portal-own', 'portal-other')
DISABLED = 'curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail,exec,shell_exec,system,passthru,popen,proc_open'
SOURCE_PATHS = [
    'tests/hosting_purchase_handoff_browser.py', 'tests/hosting_purchase_handoff_cases.py',
    'tests/hosting_purchase_handoff_fixture.php', 'tests/hosting_purchase_handoff_guards.py', 'tests/hosting_catalog_purchase_native.php',
    'tests/hosting_catalog_purchase.py', 'tests/hosting_catalog_browser.py', 'tests/browser_acceptance.py',
    'site/app/hosting/fulfillment_views.php', 'site/includes/layout/hosting_fulfillment.php',
    'site/app/hosting/catalog_offers_storage.php', 'site/app/hosting/order_links.php',
    'site/app/hosting/fulfillment.php', 'site/app/hosting/fulfillment_confirmation.php',
    'site/app/providers/inventory.php', 'site/admin/controllers/providers.php',
    'site/admin/views/providers.php', 'site/includes/layout/provider_services.php',
    'site/admin/controllers/plans.php', 'site/admin/views/plans.php',
    'site/portal/controllers/router.php', 'site/portal/views/workspace.php', '.github/workflows/hosting-catalog-offers.yml',
]


def require(value, message):
    if not value: raise RuntimeError(message)


def write(path, value):
    path.write_text(json.dumps(value, indent=2) + '\n', encoding='utf-8')


def validate_joined(proof, reports, source, fixture_hash, full_suite=False):
    """Retained original four-phase proof, never substituted by a green unrelated command."""
    require(proof.get('passed') is True and 'error' not in proof and proof.get('source') == source and proof.get('fixture_sha256') == fixture_hash, 'Exact completed joined purchase source required')
    require(proof.get('immediate_purchase_proven') is False and proof.get('live_provider_or_payment') is False, 'Retain native synthetic/time-shift limits')
    require(proof.get('checks') and all(c.get('passed') is True for c in proof['checks']), 'Joined runner checks failed')
    phases = proof.get('phases', [])
    require([r.get('phase') for r in phases] == list(PHASES) and set(reports) == set(PHASES), 'All original joined phases required')
    for record in phases:
        phase = record['phase']; raw = reports[phase]; report = decode_report(raw, phase)
        require(record.get('exit_code') == 0 and record.get('stderr_bytes') == 0 and record.get('stderr_sha256') == sha(b'') and record.get('report') == ('hosting-purchase-' if full_suite else '') + phase + '-results.json' and record.get('report_sha256') == sha(raw), 'Original phase exit or report hash differs')
        require(report.get('passed') is True and report.get('phase') == phase and not report.get('error'), 'Native purchase phase failed')
        checks = report.get('checks', []); names = [c.get('name') for c in checks]
        require(len(checks) > 3 and len(names) == len(set(names)) and all(isinstance(n, str) and n for n in names) and all(c.get('passed') is True for c in checks), 'Native named outcomes are incomplete')
        require(record.get('named_checks') == len(checks) and report.get('simulated_application_clock') is (phase == 'prepare'), 'Native phase scope differs')
    due = json.loads(reports['due'])
    require(due['joined']['client_id'] == 1 and due['joined']['package_id'] == 876001 and due['joined']['synthetic_transport'] == [{'method': 'POST', 'operation': 'packages.create'}, {'method': 'GET', 'operation': 'packages.list'}], 'Actual completed synthetic first-link journey required')
    return due


def fresh_directory(path):
    for parent in (path, *path.parents):
        require(not parent.is_symlink(), 'Browser paths must not contain links')
        if parent.exists(): require(parent.is_dir(), 'Browser parent is not a directory')
    require(not path.exists(), 'Preserve prior browser evidence and runtime')
    path.mkdir(parents=True, mode=0o700)


def extract_package(archive, manifest, site, source):
    require(manifest['source_commit'] == source and manifest['artifact'] == archive.name and manifest['sha256'] == sha(archive.read_bytes()), 'Package source or digest differs')
    with zipfile.ZipFile(archive) as z:
        infos = z.infolist(); names = [i.filename for i in infos]
        require(0 < len(infos) <= 10000 and sum(i.file_size for i in infos) <= 512 * 1024**2, 'COMPLETE package exceeds bounded extraction size')
        require(len(names) == len(set(n.casefold() for n in names)) and set(names) == set(manifest['files']) and all(n and not n.startswith('/') and '\\' not in n and ':' not in n and all(p not in ('', '.', '..') for p in n.split('/')) and str(PurePosixPath(n)) == n for n in names) and all(not i.is_dir() and stat.S_IFMT(i.external_attr >> 16) in (0, stat.S_IFREG) and not i.flag_bits & 1 for i in infos), 'Package has unsafe or unexpected members')
        require(z.testzip() is None and all(sha(z.read(n)) == manifest['files'][n] for n in names), 'Package CRC or member hash differs')
        z.extractall(site)
    return len(names)


FULL_SUITE_SOURCE_PATHS = ['.github/workflows/application-tests.yml', 'tools/acceptance-contract.json', 'tools/acceptance-reports.json']
CAPTURE_NAMES = tuple('Hosting-Purchase-' + stem + '-' + size + '.png'
                      for stem in ('Owner-Linked', 'Owner-Unlinked', 'Owner-Package', 'Client-Hosting', 'Other-Client')
                      for size in ('Desktop', 'Mobile'))


def handoff_output_paths(full_suite):
    qa = ROOT / 'qa' if full_suite else QA
    names = {'results.json': 'hosting-purchase-http-results.json',
             'progress.json': 'hosting-purchase-http-progress.txt',
             'commands.json': 'hosting-purchase-http-commands.json',
             'server.log': 'hosting-purchase-http-server.log'}
    return qa, {name: qa / (flat if full_suite else name) for name, flat in names.items()}


def prepare_handoff_outputs(full_suite):
    qa, outputs = handoff_output_paths(full_suite)
    if full_suite:
        for parent in (qa, *qa.parents):
            require(not parent.is_symlink() and (not parent.exists() or parent.is_dir()), 'Browser output parent is not a regular directory')
        require(all(not p.exists() and not p.is_symlink() for p in [*outputs.values(), *(qa/n for n in CAPTURE_NAMES)]), 'Preserve previous handoff evidence')
        require(not WEB.exists() and not WEB.is_symlink(), 'Preserve previous handoff runtime')
        qa.mkdir(parents=True, exist_ok=True)
    else:
        fresh_directory(qa)
    return qa, outputs


def handoff_report_files(qa, outputs, full_suite):
    paths = ([p for key,p in outputs.items() if key != 'results.json'] + [qa/n for n in CAPTURE_NAMES]) if full_suite else qa.iterdir()
    return {p.name: {'bytes': p.stat().st_size, 'sha256': sha(p.read_bytes())} for p in paths if p.is_file()}


def main(full_suite=False):
    require(os.name == 'posix' and os.geteuid() != 0 and os.environ.get('CWP_TEST_DATABASE') == 'cwp_test', 'Use the unprivileged isolated Linux database job')
    env = clean_environment(dict(os.environ))
    php, port = runtime_selection(env, full_suite)
    child_env = dict(env)
    if full_suite: child_env['CWP_TEST_HOSTING_PURCHASE_FULL_SUITE'] = '1'
    # Optional browser dependencies load only after the no-side-effect platform guards.
    import requests
    from bs4 import BeautifulSoup
    from playwright.sync_api import sync_playwright
    from hosting_catalog_browser import fixture_diagnostic
    qa, outputs = prepare_handoff_outputs(full_suite)
    fresh_directory(WEB); SITE.mkdir(mode=0o700)
    sessions = ROOT / 'test-runtime/hosting-purchase/browser-sessions'; fresh_directory(sessions)
    checks = []; commands = []; source = {}; package = {}; result = {}; runtime = {}; failure = None
    server = None; log = None; complete = False

    def check(name, condition):
        require(name not in {c['name'] for c in checks}, 'Duplicate handoff check label')
        checks.append({'name': name, 'passed': bool(condition)}); write(outputs['progress.json'], {'complete': False, 'checks': checks})
        if not condition: raise AssertionError(name)

    def fixture(operation):
        child = subprocess.run([php, '-d', 'display_errors=0', '-d', 'zend.exception_ignore_args=1', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=' + DISABLED, str(ROOT / 'tests/hosting_purchase_handoff_fixture.php'), operation], cwd=ROOT, env=child_env, capture_output=True, text=True, encoding='utf-8', timeout=120)
        record = {'operation': operation, 'returncode': child.returncode, 'stdout_bytes': len(child.stdout.encode()), 'stdout_sha256': sha(child.stdout.encode()), 'stderr_bytes': len(child.stderr.encode()), 'stderr_sha256': sha(child.stderr.encode())}; commands.append(record)
        if child.returncode != 0 or child.stderr: record['diagnostic'] = fixture_diagnostic(child.stderr, operation)
        write(outputs['commands.json'], commands)
        require(child.returncode == 0 and not child.stderr, 'Purchase browser fixture failed: ' + operation)
        return json.loads(child.stdout)

    try:
        head = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
        check('Purchase browser checkout equals its actual workflow source', head == os.environ['GITHUB_SHA'])
        source = {'source': head, 'tree': subprocess.check_output(['git', 'rev-parse', 'HEAD^{tree}'], cwd=ROOT, text=True).strip(), 'run': os.environ['GITHUB_RUN_ID'], 'attempt': os.environ['GITHUB_RUN_ATTEMPT'], 'files': {}}
        for path in SOURCE_PATHS + (FULL_SUITE_SOURCE_PATHS if full_suite else []):
            raw = (ROOT / path).read_bytes(); source['files'][path] = sha(raw)
            check('Purchase browser source equals Git: ' + path, raw == subprocess.check_output(['git', 'show', head + ':' + path], cwd=ROOT))
        native_qa, native_outputs = purchase_output_paths(ROOT, full_suite)
        proof_raw = native_outputs['joined'].read_bytes()
        reports = {phase: native_outputs[phase].read_bytes() for phase in PHASES}
        due = validate_joined(json.loads(proof_raw), reports, head, source['files']['tests/hosting_catalog_purchase_native.php'], full_suite)
        source['native_proof_sha256'] = sha(proof_raw); source['native_reports'] = {phase: sha(raw) for phase, raw in reports.items()}
        runtime['native'] = due['clock']; check('Purchase browser requires all four exact original native phases', True)
        archives = list((ROOT / 'dist').glob('CajunWebPro-*-COMPLETE-UPLOAD.zip')); check('Purchase browser uses exactly one current COMPLETE archive', len(archives) == 1)
        archive = archives[0]; mp = archive.with_suffix('.manifest.json'); manifest = json.loads(mp.read_bytes())
        members = extract_package(archive, manifest, SITE, head)
        package = {'name': archive.name, 'sha256': manifest['sha256'], 'bytes': archive.stat().st_size, 'members': members, 'manifest_sha256': sha(mp.read_bytes())}
        check('Purchase browser actual package passes source member and CRC checks', True)
        seed = fixture('prepare'); check('Purchase browser configuration preserves the completed hpci purchase', seed['prepared'] is True and seed['prefix'] == 'hpci_' and seed['package_id'] == 876001)
        helpers = {'BeautifulSoup': BeautifulSoup, 'urljoin': urljoin}; required = {'soup', 'token', 'form_data', 'form_for', 'submit'}
        tree = ast.parse((ROOT / 'tests/browser_acceptance.py').read_text(encoding='utf-8')); definitions = [n for n in tree.body if isinstance(n, ast.FunctionDef) and n.name in required]
        check('Purchase browser reuses five exact original full-browser form helpers', len(definitions) == len(required) and {n.name for n in definitions} == required)
        exec(compile(ast.Module(body=definitions, type_ignores=[]), 'browser_acceptance.py', 'exec'), helpers)
        log = (outputs['server.log']).open('x', encoding='utf-8')
        server = subprocess.Popen([php, '-d', 'display_errors=0', '-d', 'zend.exception_ignore_args=1', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=' + DISABLED, '-d', 'session.save_path=' + str(sessions), '-S', '127.0.0.1:8091', '-t', str(WEB)], cwd=ROOT, env=child_env, stdout=log, stderr=log)
        deadline = time.monotonic() + 15
        while True:
            require(server.poll() is None, 'PHP server exited before readiness')
            try:
                response = requests.get(BASE + '/hosting-purchase/admin/index.php?view=login', timeout=1, allow_redirects=False); response.raise_for_status(); break
            except requests.RequestException:
                if time.monotonic() >= deadline: raise RuntimeError('PHP server did not become ready')
                time.sleep(.1)
        check('Purchase browser serves the actual installed owner login', response.status_code == 200 and 'name="action" value="login"' in response.text)
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True)
            try: result = run_browser({**helpers, 'BASE': BASE, 'QA': qa, 'check': check, 'fixture': fixture, 'seed': seed}, browser)
            finally: browser.close()
        complete = True
    except Exception as error:
        locations = [{'file': Path(f.filename).name, 'line': f.lineno, 'function': f.name} for f in traceback.extract_tb(error.__traceback__) if Path(f.filename).name in {'hosting_purchase_handoff_browser.py', 'hosting_purchase_handoff_cases.py'}][-8:]
        failed = checks[-1]['name'] if checks and not checks[-1]['passed'] else None
        failure = {'class': type(error).__name__, 'message': failed or 'Packaged browser re-link failed; inspect bounded source locations.', 'locations': locations}
    finally:
        if server is not None:
            server.terminate()
            try: server.wait(timeout=10)
            except subprocess.TimeoutExpired: server.kill(); server.wait()
        if log is not None: log.close()
        if log is not None:
            clean = re.search(r'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated):', (outputs['server.log']).read_text(encoding='utf-8', errors='replace')) is None
            checks.append({'name': 'Purchase handoff server has no PHP diagnostics', 'passed': clean}); complete = complete and clean
        files = handoff_report_files(qa, outputs, full_suite)
        report = {'passed': complete and all(c['passed'] is True for c in checks), 'source': source, 'package': package, 'runtime': runtime, 'checks': checks, 'result': result, 'failure': failure, 'files': files, 'full_acceptance': False, 'visual_approval': False, 'scope': 'Packaged owner HTTP unlink and guided re-link of the completed synthetic native purchase, with current client Hosting isolation; no first-purchase browser or live provider/payment claim.'}
        write(outputs['results.json'], report); print(json.dumps({'passed': report['passed'], 'checks': len(checks), 'full_acceptance': False}))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main(arguments().full_suite))
