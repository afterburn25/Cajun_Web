"""Linux-only focused hosting UI runner; reuse exact existing form helpers.

Requires the actual native hoci_ seed and a package built from this exact checkout.
This does not replace complete application acceptance or a human pixel review.
"""
import ast
import hashlib
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

import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
from hosting_catalog_cases import run_browser

ROOT = Path(__file__).resolve().parents[1]
QA = ROOT / 'qa/hosting-catalog-http'
WEB = ROOT / 'test-runtime/hosting-catalog-web'
SITE = WEB / 'hosting-catalog'
BASE = 'http://127.0.0.1:8091'
SOURCE_PATHS = [
    'tests/hosting_catalog_browser.py', 'tests/hosting_catalog_cases.py',
    'tests/hosting_catalog_browser_fixture.php', 'tests/hosting_catalog_native.php',
    'tests/browser_acceptance.py', '.github/workflows/hosting-catalog-offers.yml',
    'site/app/hosting/catalog_offers.php', 'site/app/hosting/catalog_offers_context.php',
    'site/app/hosting/catalog_offers_storage.php', 'site/app/hosting/catalog_offers_document.php',
    'site/app/hosting/catalog_offer_views.php', 'site/admin/controllers/hosting_catalog.php',
    'site/admin/views/hosting_catalog.php', 'site/portal/controllers/hosting_catalog.php',
    'site/portal/views/hosting_catalog.php', 'site/includes/layout/hosting_catalog.php',
    'site/admin/controllers/router.php', 'site/admin/controllers/actions.php',
    'site/portal/controllers/router.php', 'site/portal/controllers/actions.php',
    'site/admin/views/catalog_import.php', 'site/pages/services/hosting.php',
]


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def write(path, value):
    path.write_text(json.dumps(value, indent=2) + '\n', encoding='utf-8')


def fixture_diagnostic(raw, operation):
    """Only the PHP fixture's closed diagnostic shape may enter retained evidence."""
    fallback = {'operation': operation, 'class': 'FixtureChildFailure',
                'message': 'Fixture failed without a valid structured diagnostic.'}
    if len(raw.encode('utf-8')) > 2048:
        return fallback
    try:
        value = json.loads(raw)
    except (ValueError, TypeError):
        return fallback
    if not isinstance(value, dict) or set(value) not in (
            {'operation', 'class', 'message', 'file', 'line'},
            {'operation', 'class', 'message', 'file', 'line', 'sqlstate', 'driver_code'}):
        return fallback
    messages = {'Disposable database operation failed.', 'Disposable fixture emitted a PHP diagnostic.',
                'Disposable fixture bootstrap or call failed.', 'Disposable fixture precondition or application guard failed.'}
    if (value['operation'] != operation or not isinstance(value['class'], str)
            or re.fullmatch(r'[A-Za-z_\\][A-Za-z0-9_\\]{0,119}', value['class']) is None
            or value['message'] not in messages or not isinstance(value['file'], str)
            or re.fullmatch(r'[A-Za-z0-9_.-]{1,120}', value['file']) is None
            or type(value['line']) is not int or not 1 <= value['line'] <= 1000000):
        return fallback
    if 'sqlstate' in value and (
            value['sqlstate'] is not None and (not isinstance(value['sqlstate'], str) or re.fullmatch(r'[A-Z0-9]{5}', value['sqlstate']) is None)
            or value['driver_code'] is not None and (type(value['driver_code']) is not int or not 0 <= value['driver_code'] <= 2147483647)):
        return fallback
    return value


def main():
    assert os.name == 'posix' and os.geteuid() != 0, 'Use the unprivileged disposable Linux runner.'
    assert os.environ.get('CWP_TEST_DATABASE') == 'cwp_test'
    php = os.environ['PHP_BINARY']
    assert Path(php).is_absolute() and Path(php).is_file()
    assert not QA.exists() and not WEB.exists(), 'Preserve previous evidence and use a fresh isolated runtime.'
    QA.mkdir(parents=True); SITE.mkdir(parents=True)
    checks = []; commands = []; source = {}; package = {}; runtime = {}; result = {}
    completed = False; failure = None; server = None; log = None

    def check(name, condition):
        checks.append({'name': name, 'passed': bool(condition)})
        write(QA / 'progress.json', {'complete': False, 'checks': checks})
        if not condition:
            raise AssertionError(name)

    def fixture(operation):
        child = subprocess.run([php, str(ROOT / 'tests/hosting_catalog_browser_fixture.php'), operation],
            cwd=ROOT, capture_output=True, text=True, encoding='utf-8', timeout=120)
        commands.append({'operation': operation, 'returncode': child.returncode,
            'stdout_bytes': len(child.stdout.encode()), 'stdout_sha256': sha(child.stdout.encode()),
            'stderr_bytes': len(child.stderr.encode()), 'stderr_sha256': sha(child.stderr.encode())})
        write(QA / 'commands.json', commands)
        if child.returncode != 0 or child.stderr:
            commands[-1]['diagnostic'] = fixture_diagnostic(child.stderr, operation)
            write(QA / 'commands.json', commands)
            raise RuntimeError('Isolated hosting fixture failed: ' + operation)
        return json.loads(child.stdout)

    try:
        head = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
        check('Hosting UI checkout equals its workflow source', head == os.environ['GITHUB_SHA'])
        source = {'source': head, 'tree': subprocess.check_output(['git', 'rev-parse', 'HEAD^{tree}'], cwd=ROOT, text=True).strip(),
                  'run': os.environ['GITHUB_RUN_ID'], 'attempt': os.environ['GITHUB_RUN_ATTEMPT'], 'files': {}}
        for path in SOURCE_PATHS:
            raw = (ROOT / path).read_bytes(); source['files'][path] = sha(raw)
            check('Hosting UI source equals Git: ' + path, raw == subprocess.check_output(['git', 'show', head + ':' + path], cwd=ROOT))
        native_path = ROOT / 'qa/hosting-catalog-native-results.json'
        native = json.loads(native_path.read_text(encoding='utf-8'))
        check('Hosting UI requires the actual complete passing native fixture', native['passed'] is True
              and native['checks'] and all(c['passed'] is True for c in native['checks'])
              and len({c['name'] for c in native['checks']}) == len(native['checks'])
              and native['runtime']['synthetic_catalog_transport_calls'] == 1)
        source['native_report_sha256'] = sha(native_path.read_bytes()); runtime['native'] = native['runtime']
        archives = list((ROOT / 'dist').glob('CajunWebPro-*-COMPLETE-UPLOAD.zip'))
        check('Hosting UI has exactly one COMPLETE package', len(archives) == 1)
        archive = archives[0]; mp = archive.with_suffix('.manifest.json'); manifest = json.loads(mp.read_text(encoding='utf-8'))
        check('Hosting UI package manifest binds exact source and bytes', manifest['source_commit'] == head and manifest['artifact'] == archive.name and manifest['sha256'] == sha(archive.read_bytes()))
        with zipfile.ZipFile(archive) as z:
            infos = z.infolist(); names = [i.filename for i in infos]
            check('Hosting UI package members are unique safe regular files', len(names) == len(set(n.casefold() for n in names))
                and set(names) == set(manifest['files'])
                and all(n and not n.startswith('/') and '\\' not in n and ':' not in n and '..' not in PurePosixPath(n).parts for n in names)
                and all(not i.is_dir() and stat.S_IFMT(i.external_attr >> 16) in (0, stat.S_IFREG) for i in infos))
            check('Hosting UI package CRC and all member hashes pass', z.testzip() is None and all(sha(z.read(n)) == manifest['files'][n] for n in names))
            z.extractall(SITE)
        package = {'name': archive.name, 'sha256': manifest['sha256'], 'bytes': archive.stat().st_size,
                   'members': len(names), 'manifest_sha256': sha(mp.read_bytes())}
        seed = fixture('prepare')
        check('Hosting UI uses only its isolated native prefix and product', seed['prepared'] is True and seed['prefix'] == 'hoci_' and type(seed['product_id']) is int and seed['product_id'] > 0)
        helpers = {'BeautifulSoup': BeautifulSoup, 'urljoin': urljoin}
        required = {'soup', 'token', 'form_data', 'form_for', 'submit'}
        tree = ast.parse((ROOT / 'tests/browser_acceptance.py').read_text(encoding='utf-8'))
        definitions = [n for n in tree.body if isinstance(n, ast.FunctionDef) and n.name in required]
        check('Hosting UI reuses the five exact full-browser form helpers', len(definitions) == len(required) and {n.name for n in definitions} == required)
        exec(compile(ast.Module(body=definitions, type_ignores=[]), 'browser_acceptance.py', 'exec'), helpers)
        log = open(QA / 'server.log', 'w', encoding='utf-8')
        server = subprocess.Popen([php, '-S', '127.0.0.1:8091', '-t', str(WEB)], cwd=ROOT, stdout=log, stderr=log)
        probe = WEB / 'hosting-runtime.php'
        probe.write_text("<?php echo json_encode(['version'=>PHP_VERSION,'sapi'=>PHP_SAPI]);\n", encoding='utf-8')
        try:
            deadline = time.monotonic() + 15
            while True:
                if server.poll() is not None: raise RuntimeError('PHP server exited before readiness')
                try:
                    response = requests.get(BASE + '/hosting-runtime.php', timeout=1); response.raise_for_status()
                    runtime['http'] = response.json(); break
                except (requests.RequestException, ValueError):
                    if time.monotonic() >= deadline: raise RuntimeError('PHP server did not become ready')
                    time.sleep(.1)
            check('Hosting UI HTTP uses the same actual PHP as native', runtime['http']['version'] == runtime['native']['php'] and runtime['http']['sapi'] == 'cli-server')
        finally:
            probe.unlink()
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True)
            try:
                result = run_browser({**helpers, 'ROOT': ROOT, 'BASE': BASE, 'QA': QA,
                    'check': check, 'fixture': fixture, 'seed': seed}, browser)
            finally:
                browser.close()
        completed = True
    except Exception as error:
        # Playwright exception text may contain entered passwords or cookies. Keep source
        # locations and finite messages instead; exact failed check labels are already safe.
        frames = [{'file': Path(f.filename).name, 'line': f.lineno, 'function': f.name}
                  for f in traceback.extract_tb(error.__traceback__)
                  if Path(f.filename).name in {'hosting_catalog_browser.py', 'hosting_catalog_cases.py'}][-8:]
        failed = checks[-1]['name'] if checks and checks[-1]['passed'] is False else None
        failure = {'class': type(error).__name__, 'last_check': checks[-1]['name'] if checks else None,
                   'message': failed or 'Focused browser operation failed; inspect retained source locations and fixture diagnostic.',
                   'locations': frames}
    finally:
        if server is not None:
            server.terminate()
            try: server.wait(timeout=10)
            except subprocess.TimeoutExpired: server.kill(); server.wait()
        if log is not None: log.close()
        if completed:
            clean = re.search(r'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated):', (QA / 'server.log').read_text(encoding='utf-8', errors='replace')) is None
            checks.append({'name': 'Hosting UI server has no PHP runtime warnings or errors', 'passed': clean})
            if not clean: completed = False; failure = {'class': 'RuntimeLogFailure'}
        files = {p.name: {'bytes': p.stat().st_size, 'sha256': sha(p.read_bytes())} for p in QA.iterdir() if p.is_file()}
        report = {'passed': completed and all(c['passed'] is True for c in checks), 'full_acceptance': False,
            'visual_approval': False, 'source': source, 'package': package, 'runtime': runtime, 'result': result,
            'checks': checks, 'failure': failure, 'files': files,
            'scope': 'Focused packaged owner/client hosting UI on one disposable Linux database. Actual retained outcomes only; not full application, provider, payment or release acceptance.'}
        write(QA / 'results.json', report)
        print(json.dumps({'passed': report['passed'], 'checks': len(checks), 'failure': failure, 'full_acceptance': False}))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
