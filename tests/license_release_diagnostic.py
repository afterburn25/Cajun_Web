"""Supplemental real packaged licensing HTTP/Chromium diagnostic, not full acceptance."""
import ast
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import time
from types import SimpleNamespace
from urllib.parse import urljoin
import zipfile

import requests
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
QA = ROOT / 'qa/licensing-http'
WEB = ROOT / 'test-runtime/web'
BASE = 'http://127.0.0.1:8091'
IMAGES = {f'Licensing-{name}-{size}.png' for name in (
    'Legacy-Setup', 'Customer-Downloads', 'Owner-Invoice-Review') for size in ('Desktop', 'Mobile')}
RESPONSES = [
    'setup rejects CSRF', 'setup rejects password', 'setup upgrades populated legacy storage',
    'ordinary administrator cannot create master', 'owner creates internal master',
    'owner cannot package internal master',
    *['customer master ' + action + ' refusal' for action in (
        'license_domain_add', 'license_domain_deactivate', 'license_domain_replace')],
    *['legacy review rejects ' + kind for kind in (
        'CSRF', 'password', 'confirmation', 'foreign license', 'master license', 'nonowner')],
    'owner explicitly links original revoked license', 'replayed decision remains refused',
    'owner deliberately creates never-fulfilled license', 'isolated support preparation',
    'isolated support permission',
]
SEED = r'''
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require getcwd().'/site/includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/database/schema/base.php';
$private=getcwd().'/test-runtime/licensing-http-baseline-private';
if(file_exists($private)||file_exists(getcwd().'/test-runtime/config-fixture.php'))throw new RuntimeException('Diagnostic baseline must start absent.');
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>$private,'key'=>str_repeat('a',64),'db'=>['host'=>getenv('CWP_TEST_HOST'),'port'=>(int)getenv('CWP_TEST_PORT'),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER'),'pass'=>getenv('CWP_TEST_PASSWORD'),'prefix'=>'ci_']];
if(all('SHOW TABLES')!==[])throw new RuntimeException('Diagnostic database must start empty.');
mkdir($private,0700,true);mkdir($private.'/media',0700);
foreach(schema_statements('ci_') as $sql)db()->exec($sql);
foreach(default_settings() as $key=>$value)save_setting($key,$value);
$now=gmdate('Y-m-d H:i:s');
query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (1,?,?,?,?,?)',['Diagnostic baseline owner','baseline@example.invalid',password_hash('Synthetic diagnostic baseline password',PASSWORD_DEFAULT),str_repeat('b',32),$now]);
staff_seed_owner(1);
query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (1,?,?,?)',['fixture.licensing.baseline','Retain this unrelated baseline during isolated licensing HTTP checks.',$now]);
file_put_contents(getcwd().'/test-runtime/config-fixture.php','<?php return '.var_export(config(),true).';');
echo json_encode(['seeded'=>true,'prefix'=>'ci_','users'=>1,'staff_profiles'=>1,'audit'=>1,'provider_refreshes'=>0,'provider_refresh_results'=>0],JSON_THROW_ON_ERROR);
'''


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def main():
    assert os.geteuid() != 0, 'Use the unprivileged disposable CI runner.'
    assert os.environ.get('CWP_TEST_DATABASE') == 'cwp_test'
    assert os.environ.get('CWP_PHP_VERSION') == '8.3'
    assert os.environ.get('CWP_DATABASE') == 'mariadb-10.11'
    php = os.environ['PHP_BINARY']
    assert Path(php).is_absolute() and Path(php).is_file()
    assert not QA.exists() and not WEB.exists(), 'Use a fresh diagnostic checkout/runtime.'
    QA.mkdir(parents=True); WEB.mkdir(parents=True)
    checks = []; commands = []; failure = None; completed = False
    package_receipt = {}; runtime = {}; baseline = {}; source_receipt = {}
    provider = None; server = None; log = None

    def check(name, condition):
        checks.append({'name': name, 'passed': bool(condition)})
        (QA / 'progress.json').write_text(json.dumps({'complete': False, 'checks': checks}, indent=2)+'\n')
        if not condition:
            raise AssertionError(name)

    def command_receipt(operation, process):
        stderr = process.stderr or ''; stdout = process.stdout or ''
        undefined = re.search(r'Call to undefined function ([A-Za-z_][A-Za-z0-9_]*)\(\)', stderr)
        sqlstate = re.search(r'SQLSTATE\[([A-Z0-9]{5})\]', stderr)
        commands.append({'operation': operation, 'returncode': process.returncode,
                         'stdout_bytes': len(stdout.encode()), 'stdout_sha256': sha(stdout.encode()),
                         'stderr_bytes': len(stderr.encode()), 'stderr_sha256': sha(stderr.encode()),
                         'undefined_function': undefined.group(1) if undefined else None,
                         'sqlstate': sqlstate.group(1) if sqlstate else None})
        (QA / 'command-diagnostics.json').write_text(json.dumps(commands, indent=2)+'\n')

    try:
        source = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
        check('Diagnostic source equals this exact workflow source', source == os.environ['GITHUB_SHA'])
        tree = subprocess.check_output(['git', 'rev-parse', 'HEAD^{tree}'], cwd=ROOT, text=True).strip()
        paths = ['tests/license_release_diagnostic.py', 'tests/license_release_cases.py',
                 'tests/license_release_browser_fixture.php', 'tests/browser_acceptance.py',
                 'tests/release_cases.py', 'tests/fixture_db.php', 'tests/provider_http_server.py',
                 'tests/acceptance_environment.py', 'tools/build.py', '.github/workflows/release-licensing.yml']
        source_receipt = {'source': source, 'tree': tree, 'run': os.environ['GITHUB_RUN_ID'],
                          'attempt': os.environ['GITHUB_RUN_ATTEMPT'],
                          'files': {path: sha((ROOT/path).read_bytes()) for path in paths}}
        for path in paths:
            check('Diagnostic current source matches Git: '+path,
                  (ROOT/path).read_bytes() == subprocess.check_output(['git', 'show', source+':'+path], cwd=ROOT))
        check('Corrected old-fulfillment fixture loads workspace before the actual call',
              "}elseif($operation==='old-fulfillment'){\n    require_once CWP_CODE_ROOT.'/app/clients/workspace.php';\n    require_once CWP_CODE_ROOT.'/app/licenses/fulfill.php';"
              in (ROOT/'tests/license_release_browser_fixture.php').read_text())

        from acceptance_environment import probe, php_matches
        probe('native')
        runtime = json.loads((ROOT/'qa/native-environment-results.json').read_text())
        check('Diagnostic actual CLI minimal PHP and database probes pass', runtime['passed'] is True and runtime['selected'] == {'php': '8.3', 'database': 'mariadb-10.11'})
        packages = list((ROOT/'dist').glob('CajunWebPro-*-COMPLETE-UPLOAD.zip'))
        check('Diagnostic has one actual built COMPLETE package', len(packages) == 1)
        package = packages[0]
        manifest = json.loads(package.with_suffix('.manifest.json').read_text())
        check('Diagnostic COMPLETE manifest binds exact source and archive bytes', manifest['source_commit'] == source and manifest['artifact'] == package.name and manifest['sha256'] == sha(package.read_bytes()))
        with zipfile.ZipFile(package) as archive:
            names = archive.namelist()
            check('Diagnostic COMPLETE has safe unique members and exact manifest bytes',
                  len(names) == len(set(names)) and set(names) == set(manifest['files'])
                  and all(not n.startswith('/') and '\\' not in n and '..' not in n.split('/') for n in names)
                  and all(sha(archive.read(n)) == manifest['files'][n] for n in names))
        package_receipt = {'name': package.name, 'bytes': package.stat().st_size,
                           'sha256': manifest['sha256'], 'members': len(names),
                           'manifest_sha256': sha(package.with_suffix('.manifest.json').read_bytes())}

        seed = subprocess.run([php, '-r', SEED], cwd=ROOT, text=True, capture_output=True, timeout=120)
        command_receipt('diagnostic-baseline', seed)
        check('Diagnostic minimal real baseline setup succeeds', seed.returncode == 0 and seed.stderr == '')
        baseline = json.loads(seed.stdout)
        check('Diagnostic baseline has actual unrelated owner profile audit and empty provider history', baseline == {'seeded': True, 'prefix': 'ci_', 'users': 1, 'staff_profiles': 1, 'audit': 1, 'provider_refreshes': 0, 'provider_refresh_results': 0})

        # Execute only these exact helper definitions. Importing the whole driver would run its full suite.
        helper_names = {'soup', 'token', 'form_data', 'form_for', 'submit'}
        parsed = ast.parse((ROOT/'tests/browser_acceptance.py').read_text())
        definitions = [node for node in parsed.body if isinstance(node, ast.FunctionDef) and node.name in helper_names]
        check('Diagnostic reuses all five exact full-browser form helpers once', len(definitions) == len(helper_names) and {node.name for node in definitions} == helper_names)
        helpers = {'BeautifulSoup': BeautifulSoup, 'urljoin': urljoin}
        exec(compile(ast.Module(body=definitions, type_ignores=[]), str(ROOT/'tests/browser_acceptance.py'), 'exec'), helpers)
        import release_cases
        release_cases.ROOT = ROOT; release_cases.php = php
        release_cases.soup = helpers['soup']; release_cases.form_data = helpers['form_data']

        from provider_http_server import ProviderServer
        provider = ProviderServer(ROOT)
        log = open(QA/'server.log', 'w')
        server = subprocess.Popen([php, '-S', '127.0.0.1:8091', '-t', str(WEB)], cwd=ROOT, stdout=log, stderr=log)
        probe_path = WEB/'licensing-runtime.php'
        probe_path.write_text("<?php echo json_encode(['version'=>PHP_VERSION,'sapi'=>PHP_SAPI]);\n")
        try:
            deadline = time.monotonic()+15
            while True:
                if server.poll() is not None: raise RuntimeError('Diagnostic PHP server exited before readiness')
                try:
                    response = requests.get(BASE+'/licensing-runtime.php', timeout=1)
                    response.raise_for_status(); body = response.json(); break
                except (requests.RequestException, ValueError):
                    if time.monotonic() >= deadline: raise RuntimeError('Diagnostic PHP server did not become ready')
                    time.sleep(.1)
            check('Diagnostic actual HTTP PHP is selected 8.3 cli-server', set(body) == {'version', 'sapi'} and php_matches(body['version'], '8.3') and body['sapi'] == 'cli-server')
            runtime['http'] = body
        finally:
            probe_path.unlink()

        import license_release_cases
        original_subprocess = license_release_cases.subprocess
        def recorded_run(args, **kwargs):
            if args[:2] != [php, str(ROOT/'tests/license_release_browser_fixture.php')] or len(args) != 3 or args[2] not in ('seed', 'old-fulfillment', 'master-bundle'):
                raise RuntimeError('Unexpected licensing diagnostic child command')
            try:
                result = subprocess.run(args, **kwargs)
            except subprocess.TimeoutExpired as error:
                commands.append({'operation': args[2], 'timed_out': True})
                (QA/'command-diagnostics.json').write_text(json.dumps(commands, indent=2)+'\n')
                raise error
            command_receipt(args[2], result)
            return result
        # Record finite child diagnostics without changing any original assertion or subprocess result.
        license_release_cases.subprocess = SimpleNamespace(run=recorded_run)
        env = {'ROOT': ROOT, 'WEB': WEB, 'QA': QA, 'BASE': BASE, 'check': check,
               'soup': helpers['soup'], 'token': helpers['token'], 'submit': helpers['submit'],
               'php': php, 'provider_server': provider}
        try:
            with sync_playwright() as playwright:
                browser = playwright.chromium.launch(headless=True)
                try: license_release_cases.run_browser(env, browser)
                finally: browser.close()
        finally:
            license_release_cases.subprocess = original_subprocess
        responses = json.loads((QA/'licensing-release-response-diagnostics.json').read_text())
        check('Diagnostic completed all twenty original licensing response steps', [row['step'] for row in responses] == RESPONSES)
        check('Diagnostic retains exactly six original desktop and mobile licensing captures', {p.name for p in QA.glob('*.png')} == IMAGES)
        check('Diagnostic made no synthetic provider request or write', provider.calls == 0 and provider.posts == [] and provider.errors == [])
        completed = True
    except Exception as error:
        failure = {'class': type(error).__name__, 'last_check': checks[-1]['name'] if checks else None}
    finally:
        if server is not None:
            server.terminate()
            try: server.wait(timeout=10)
            except subprocess.TimeoutExpired: server.kill(); server.wait()
        if log is not None: log.close()
        if provider is not None: provider.close()
        if completed:
            clean = re.search(r'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated):', (QA/'server.log').read_text(errors='replace')) is None
            checks.append({'name': 'Diagnostic actual PHP HTTP log contains no runtime warnings or errors', 'passed': clean})
            if not clean: completed = False; failure = {'class': 'RuntimeLogFailure', 'last_check': checks[-1]['name']}
        (QA/'runtime.json').write_text(json.dumps(runtime, indent=2)+'\n')
        (QA/'source.json').write_text(json.dumps(source_receipt, indent=2)+'\n')
        files = {p.name: {'bytes': p.stat().st_size, 'sha256': sha(p.read_bytes())} for p in QA.iterdir() if p.is_file()}
        report = {'schema': 1, 'passed': completed and all(c['passed'] for c in checks),
                  'full_acceptance': False, 'release_clearance': False,
                  'scope': 'Supplemental isolated licensing HTTP and Chromium on maintained PHP8.3/MariaDB only. The complete four-environment candidate/main acceptance remains required.',
                  'source': source_receipt, 'runtime': runtime, 'package': package_receipt,
                  'baseline': baseline, 'checks': checks, 'failure': failure, 'files': files}
        (QA/'results.json').write_text(json.dumps(report, indent=2)+'\n')
        print(json.dumps({'passed': report['passed'], 'checks': len(checks), 'failure': failure, 'full_acceptance': False}))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
