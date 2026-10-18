"""Bounded packaged settings timing after the real disposable StackCP native fixture.

This is not the accumulated full-browser dataset or four-environment acceptance.
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
from urllib.parse import urljoin
import zipfile

import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
QA = ROOT / 'qa/stackcp-access-http'
WEB = ROOT / 'test-runtime/stackcp-access-web'
BASE = 'http://127.0.0.1:8091'
SITE = WEB / 'existing'
PASSWORD = 'Synthetic access snapshot owner passphrase'
TABLES = ['users', 'staff_profiles', 'studio_clients', 'settings', 'audit',
          'provider_profiles', 'provider_revisions', 'provider_refreshes', 'provider_refresh_results',
          'provider_observation_packages', 'provider_package_links', 'provider_package_link_events',
          'provider_stackcp_auth', 'provider_stackcp_assignments', 'provider_stackcp_reads',
          'provider_stackcp_observations']
SOURCE_PATHS = ['tests/stackcp_access_read_diagnostic.py', 'tests/stackcp_access_read_snapshot_native.php',
                'tests/fixtures/stackcp_access_read_original.json', 'tests/browser_acceptance.py',
                'tests/acceptance_environment.py', 'tools/build.py',
                '.github/workflows/stackcp-access-read-diagnostic.yml',
                'site/includes/bootstrap.php', 'site/admin/controllers/router.php',
                'site/admin/controllers/providers.php', 'site/admin/views/providers.php',
                *['site/app/providers/' + name + '.php' for name in (
                    'stackcp_read_projection', 'stackcp_access_storage', 'stackcp_access',
                    'stackcp_access_views', 'stackcp_auth_storage', 'stackcp_assignments',
                    'stackcp_prerequisite_views')]]
CONFIG = r'''
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$root=getcwd();$private=realpath($root.'/test-runtime/stackcp-access-read-private');
if(!$private||!is_file($private.'/provider-master.key'))throw new RuntimeException('Native seed is absent.');
$config=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>$private,'key'=>str_repeat('synthetic-access-read-',4),'db'=>['host'=>getenv('CWP_TEST_HOST'),'port'=>(int)getenv('CWP_TEST_PORT'),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER'),'pass'=>getenv('CWP_TEST_PASSWORD'),'prefix'=>'sapr_']];
$target=$root.'/test-runtime/stackcp-access-web/existing/config.php';
if(file_exists($target))throw new RuntimeException('Installed config already exists.');
if(file_put_contents($target,'<?php return '.var_export($config,true).';')===false||!chmod($target,0600))throw new RuntimeException('Cannot protect installed config.');
echo json_encode(['configured'=>true,'prefix'=>'sapr_'],JSON_THROW_ON_ERROR);
'''
SNAPSHOT = r'''
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',getcwd().'/test-runtime/stackcp-access-web/existing');
require CWP_ROOT.'/includes/bootstrap.php';$GLOBALS['cwp_config']=require CWP_ROOT.'/config.php';
$rows=[];$counts=[];$names=json_decode($argv[1],true,32,JSON_THROW_ON_ERROR);
foreach($names as $name){$order=match($name){'settings'=>'name','staff_profiles'=>'user_id','provider_observation_packages'=>'refresh_id,package_id',default=>'id'};
 $exists=one('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',['sapr_'.$name])!==null;
 $rows[$name]=$exists?all('SELECT * FROM '.table($name).' ORDER BY '.$order):null;$counts[$name]=$exists?count($rows[$name]):null;}
echo json_encode(['database_sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)),'counts'=>$counts,'master_key_sha256'=>hash_file('sha256',config()['storage'].'/provider-master.key')],JSON_THROW_ON_ERROR);
'''


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def write(path, value):
    path.write_text(json.dumps(value, indent=2) + '\n', encoding='utf-8')


def main():
    assert os.geteuid() != 0, 'Use the unprivileged disposable CI runner.'
    assert os.environ.get('CWP_TEST_DATABASE') == 'cwp_test'
    assert os.environ.get('CWP_PHP_VERSION') == '8.3' and os.environ.get('CWP_DATABASE') == 'mariadb-10.11'
    php = os.environ['PHP_BINARY']
    assert Path(php).is_absolute() and Path(php).is_file()
    assert not QA.exists() and not WEB.exists(), 'Use a fresh isolated diagnostic runtime.'
    QA.mkdir(parents=True); SITE.mkdir(parents=True)
    checks = []; timings = []; commands = []; source = {}; package = {}; runtime = {}; seed = {}
    before = {}; after = {}; failure = None; completed = False; server = None; log = None

    def check(name, condition):
        checks.append({'name': name, 'passed': bool(condition)})
        write(QA/'progress.json', {'complete': False, 'checks': checks, 'timings': timings})
        if not condition:
            raise AssertionError(name)

    def php_json(operation, code, args=()):
        result = subprocess.run([php, '-r', code, *args], cwd=ROOT, text=True,
                                encoding='utf-8', capture_output=True, timeout=120)
        commands.append({'operation': operation, 'returncode': result.returncode,
                         'stdout_bytes': len(result.stdout.encode()), 'stdout_sha256': sha(result.stdout.encode()),
                         'stderr_bytes': len(result.stderr.encode()), 'stderr_sha256': sha(result.stderr.encode())})
        write(QA/'commands.json', commands)
        if result.returncode != 0 or result.stderr:
            raise RuntimeError('Isolated diagnostic child failed: ' + operation)
        return json.loads(result.stdout)

    def snapshot():
        return php_json('read-only-state', SNAPSHOT, [json.dumps(TABLES)])

    def page_contents(label, html, number):
        soup = BeautifulSoup(html, 'html.parser')
        section = soup.select_one('#stackcp-access-observations')
        expected = list(range(701, 721)) if number == 1 else [721]
        cards = section.select('[data-stackcp-access-package]') if section else []
        actual = [int(card['data-stackcp-access-package']) for card in cards]
        check(label + ' retains every expected hosting-link access card', actual == expected)
        check(label + ' retains complete administration shell and finite access output',
              soup.select_one('main#admin-main') is not None and soup.select_one('footer.admin-footer') is not None
              and section is not None and not section.select('.notice.error'))
        match = section.select_one('[data-stackcp-access-package="' + str(701 if number == 1 else 721) + '"]')
        check(label + ' retains saved match and honest no-login wording',
              match is not None and 'Assignment matched two saved observations.' in match.get_text()
              and 'These separate checks do not create a login' in match.get_text())
        if number == 1:
            missing = section.select_one('[data-stackcp-access-package="702"]')
            check(label + ' retains the unassigned hosting link without observation forms',
                  missing is not None and not missing.select('form')
                  and 'A current local assignment' in missing.get_text())
        check(label + ' never renders synthetic provider keys or owner password',
              all(value not in html for value in (PASSWORD, 'SYNTHETIC_ACCESS_SNAPSHOT_GENERAL_KEY',
                                                  'SYNTHETIC_ACCESS_SNAPSHOT_AUTH_KEY')))
        return actual

    try:
        head = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
        check('Diagnostic exact checkout equals workflow source', head == os.environ['GITHUB_SHA'])
        source = {'source': head, 'tree': subprocess.check_output(['git', 'rev-parse', 'HEAD^{tree}'], cwd=ROOT, text=True).strip(),
                  'run': os.environ['GITHUB_RUN_ID'], 'attempt': os.environ['GITHUB_RUN_ATTEMPT'], 'files': {}}
        for path in SOURCE_PATHS:
            raw = (ROOT/path).read_bytes(); source['files'][path] = sha(raw)
            check('Diagnostic source equals exact Git: ' + path, raw == subprocess.check_output(['git', 'show', head+':'+path], cwd=ROOT))
        native_path = ROOT/'qa/stackcp-access-read-snapshot-native-results.json'
        seed_path = ROOT/'qa/stackcp-access-read-snapshot-seed.json'
        native = json.loads(native_path.read_text(encoding='utf-8')); seed = json.loads(seed_path.read_text(encoding='utf-8'))
        check('Diagnostic requires actual successful native profiling and all named checks',
              native['passed'] is True and native['checks'] and all(c['passed'] is True for c in native['checks']))
        check('Diagnostic requires the exact restored synthetic dataset', seed['passed'] is True
              and seed['prefix'] == 'sapr_' and seed['owner_email'] == 'accesssnapshotowner1@example.invalid'
              and seed['storage_relative'] == 'test-runtime/stackcp-access-read-private'
              and seed['linked_packages'] == 21 and seed['local_assignments'] == 20
              and seed['unassigned_package'] == 702 and seed['membership_and_grants_packages'] == [701, 721])
        source['native_report_sha256'] = sha(native_path.read_bytes()); source['seed_receipt_sha256'] = sha(seed_path.read_bytes())
        from acceptance_environment import probe, php_matches
        probe('native'); runtime = json.loads((ROOT/'qa/native-environment-results.json').read_text(encoding='utf-8'))
        check('Diagnostic actual CLI minimal and database runtime probes pass', runtime['passed'] is True
              and runtime['selected'] == {'php': '8.3', 'database': 'mariadb-10.11'})
        archives = list((ROOT/'dist').glob('CajunWebPro-*-COMPLETE-UPLOAD.zip'))
        check('Diagnostic has exactly one actual COMPLETE package', len(archives) == 1)
        archive = archives[0]; manifest_path = archive.with_suffix('.manifest.json')
        manifest = json.loads(manifest_path.read_text(encoding='utf-8'))
        check('Diagnostic package bytes and manifest bind this source', manifest['source_commit'] == head
              and manifest['artifact'] == archive.name and manifest['sha256'] == sha(archive.read_bytes()))
        with zipfile.ZipFile(archive) as z:
            infos = z.infolist(); names = [i.filename for i in infos]
            check('Diagnostic package has exact unique safe regular files',
                  len(names) == len(set(n.casefold() for n in names)) and set(names) == set(manifest['files'])
                  and all(n and not n.startswith('/') and '\\' not in n and ':' not in n and '..' not in PurePosixPath(n).parts for n in names)
                  and all(not i.is_dir() and stat.S_IFMT(i.external_attr >> 16) in (0, stat.S_IFREG) for i in infos))
            check('Diagnostic package CRC and every member hash match', z.testzip() is None
                  and all(sha(z.read(n)) == manifest['files'][n] for n in names))
            z.extractall(SITE)
        package = {'name': archive.name, 'sha256': manifest['sha256'], 'bytes': archive.stat().st_size,
                   'members': len(names), 'manifest_sha256': sha(manifest_path.read_bytes())}
        check('Diagnostic installs only its isolated synthetic config', php_json('installed-config', CONFIG) == {'configured': True, 'prefix': 'sapr_'})
        # The staged package represents an already installed site. Never remove repository files.
        (SITE/'install.php').unlink()
        before = snapshot()
        check('Packaged reads see the exact native-restored data and protected key',
              before['database_sha256'] == seed['restored_database_sha256'] and before['master_key_sha256'] == seed['master_key_sha256'])
        helpers = {'BeautifulSoup': BeautifulSoup, 'urljoin': urljoin}
        names = {'soup', 'token', 'form_data', 'form_for', 'submit'}
        parsed = ast.parse((ROOT/'tests/browser_acceptance.py').read_text(encoding='utf-8'))
        definitions = [n for n in parsed.body if isinstance(n, ast.FunctionDef) and n.name in names]
        check('Diagnostic reuses the five exact complete-browser form helpers', len(definitions) == len(names) and {n.name for n in definitions} == names)
        exec(compile(ast.Module(body=definitions, type_ignores=[]), 'browser_acceptance.py', 'exec'), helpers)
        log = open(QA/'server.log', 'w', encoding='utf-8')
        server = subprocess.Popen([php, '-S', '127.0.0.1:8091', '-t', str(WEB)], cwd=ROOT, stdout=log, stderr=log)
        probe_path = WEB/'stackcp-runtime.php'; probe_path.write_text("<?php echo json_encode(['version'=>PHP_VERSION,'sapi'=>PHP_SAPI]);\n", encoding='utf-8')
        try:
            deadline = time.monotonic()+15
            while True:
                if server.poll() is not None: raise RuntimeError('Diagnostic PHP server exited before readiness')
                try:
                    response = requests.get(BASE+'/stackcp-runtime.php', timeout=1); response.raise_for_status(); body = response.json(); break
                except (requests.RequestException, ValueError):
                    if time.monotonic() >= deadline: raise RuntimeError('Diagnostic server did not become ready')
                    time.sleep(.1)
            check('Diagnostic actual HTTP is selected PHP 8.3 cli-server', set(body) == {'version', 'sapi'} and php_matches(body['version'], '8.3') and body['sapi'] == 'cli-server')
            runtime['http'] = body
        finally:
            probe_path.unlink()
        with requests.Session() as owner:
            login = helpers['submit'](owner, BASE+'/existing/admin/index.php?view=login', 'login', {'email': seed['owner_email'], 'password': PASSWORD})
            check('Diagnostic actual owner login succeeds', login.status_code == 200 and 'Sales pipeline' in login.text)
            before = snapshot()  # Normal login audit/rate-limit effects precede the read-only measurement.
            with sync_playwright() as playwright:
                browser = playwright.chromium.launch(headless=True)
                try:
                    context = browser.new_context(); context.add_cookies([{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in owner.cookies])
                    page = context.new_page(); script_errors = []; page.on('pageerror', lambda e: script_errors.append(str(e)))
                    for number, width, height, label in [(1, 1440, 1050, 'Twenty-Desktop'), (1, 320, 740, 'Twenty-Mobile'), (2, 1440, 1050, 'Last-Desktop'), (2, 320, 740, 'Last-Mobile')]:
                        url = BASE+'/existing/admin/index.php?view=provider-settings&stackcp_page='+str(number)+'&stackcp_access_page='+str(number)
                        page.set_viewport_size({'width': width, 'height': height}); started = time.monotonic()
                        row = {'label': label, 'page': number, 'timeout_ms': 30000, 'wait_until': 'load', 'completed': False}; timings.append(row)
                        try:
                            response = page.goto(url, wait_until='load', timeout=30000)
                        finally:
                            row['seconds'] = time.monotonic()-started; write(QA/'timings.json', timings)
                        check(label + ' whole-page navigation completes with original 30-second limit', response is not None and response.status == 200 and page.url == url and page.evaluate('document.readyState') == 'complete')
                        row['packages'] = page_contents(label, page.content(), number); row['completed'] = True
                        page.evaluate('document.fonts.ready')
                        page.locator('#stackcp-access-observations').screenshot(path=str(QA/('StackCP-Access-Diagnostic-'+label+'.png')))
                        row['navigation'] = page.evaluate('JSON.parse(JSON.stringify(performance.getEntriesByType("navigation")[0]))')
                        write(QA/'timings.json', timings)
                    check('Diagnostic real browser has no script errors', not script_errors)
                    context.close()
                finally:
                    browser.close()
            after = snapshot()
            check('Four real page reads preserve all selected rows and the original protected key', after == before)
        completed = True
    except Exception as error:
        failure = {'class': type(error).__name__, 'last_check': checks[-1]['name'] if checks else None,
                   'last_navigation': timings[-1]['label'] if timings else None}
    finally:
        if server is not None:
            server.terminate()
            try: server.wait(timeout=10)
            except subprocess.TimeoutExpired: server.kill(); server.wait()
        if log is not None: log.close()
        if completed:
            clean = re.search(r'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated):', (QA/'server.log').read_text(encoding='utf-8', errors='replace')) is None
            checks.append({'name': 'Diagnostic HTTP log contains no PHP runtime warnings or errors', 'passed': clean})
            if not clean: completed = False; failure = {'class': 'RuntimeLogFailure'}
        write(QA/'source.json', source); write(QA/'runtime.json', runtime)
        write(QA/'state.json', {'after_login_before_reads': before, 'after_reads': after})
        files = {p.name: {'bytes': p.stat().st_size, 'sha256': sha(p.read_bytes())} for p in QA.iterdir() if p.is_file()}
        report = {'passed': completed and all(c['passed'] for c in checks), 'full_acceptance': False,
                  'scope': 'Supplemental PHP8.3/MariaDB packaged full-page timing on 21 synthetic hosting links, 20 local assignments and two membership/grants pairs. Unrelated accumulated full-browser histories are not reproduced. No PHP8.2 reproduction or four-environment/visual/release clearance.',
                  'source': source, 'runtime': runtime, 'package': package, 'seed': seed,
                  'checks': checks, 'timings': timings, 'failure': failure, 'files': files}
        write(QA/'results.json', report)
        print(json.dumps({'passed': report['passed'], 'checks': len(checks), 'failure': failure, 'full_acceptance': False}))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
