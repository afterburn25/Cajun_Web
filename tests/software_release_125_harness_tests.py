"""Offline regression tests for the real published-v1.25 gate orchestration.

Execute the source functions with process/HTTP/database boundaries replaced.
These tests do not claim Apache, OPCache, database or populated-upgrade acceptance.
"""
import ast
import getpass
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
from types import SimpleNamespace
import unittest
from unittest.mock import Mock, patch
import zipfile

from acceptance_environment import selected, php_matches


ROOT = Path(__file__).resolve().parents[1]


def functions(path, namespace):
    tree = ast.parse(path.read_text(encoding='utf-8'), filename=str(path))
    tree.body = [node for node in tree.body if isinstance(node, ast.FunctionDef)]
    exec(compile(tree, str(path), 'exec'), namespace)
    return namespace


class UpgradeHarness(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='cwp-upgrade-contract-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.qa = self.root / 'qa'
        self.qa.mkdir()
        self.baseline = self.root / 'test-runtime/released-1.25.0'
        self.baseline.mkdir(parents=True)
        (self.baseline / 'install.php').write_text('baseline installer', encoding='utf-8')
        (self.root / 'dist').mkdir()
        (self.root / 'dist/CajunWebPro-1.26.0-UPDATE.zip').touch()
        self.env = patch.dict(os.environ, {'CWP_TEST_DATABASE': 'cwp_test',
                                          'CWP_PHP_VERSION': '8.3', 'CWP_DATABASE': 'mariadb-10.11'})
        self.env.start()
        self.addCleanup(self.env.stop)
        self.process = Mock()
        self.process.poll.return_value = None
        self.http_runtime = {'version': '8.3.6', 'sapi': 'apache2handler'}
        self.requests = SimpleNamespace(
            get=Mock(return_value=SimpleNamespace(status_code=200, text='upgrade-gate-ready',
                                                 json=lambda: self.http_runtime)),
            RequestException=ConnectionError)
        self.calls = []
        self.ns = functions(ROOT / 'tests/software_release_125_upgrade.py', {
            'ROOT': self.root, 'QA': self.qa,
            'RUNTIME': self.root / 'test-runtime/gate', 'VERSION': '1.26.0',
            'Path': Path, 'json': json, 'os': os, 'shutil': shutil, 'time': time,
            'socket': socket, 'subprocess': subprocess, 'getpass': getpass,
            'grp': SimpleNamespace(getgrgid=lambda _: SimpleNamespace(gr_name='qa')),
            'requests': self.requests, 'organized_update': self.organized,
            'selected': selected, 'php_matches': php_matches, 'probe_environment': Mock(),
        })

    def organized(self, web, base, check, qa, php, label, prefix, version, *, at_root=False):
        self.calls.append((web, label, prefix, version, at_root))
        self.assertFalse(any(web.iterdir()), 'Readiness probe must not enter the installed fixture')
        check(label + ' retained fixture callback', True)

    def launch_stub(self, work, web, rewrite):
        return self.process, 'http://127.0.0.1:12345'

    def test_launcher_supplies_cache_contract_and_retains_startup_log(self):
        for version, rewrite in [(v, r) for v in ('8.2', '8.3', '8.4') for r in (False, True)]:
            os.environ['CWP_PHP_VERSION'] = version
            work = self.root / (version + str(rewrite))
            web = work / 'web'
            web.mkdir(parents=True)
            with patch.object(os, 'getgid', return_value=0, create=True), \
                    patch.object(subprocess, 'Popen', return_value=self.process) as popen:
                self.ns['launch'](work, web, rewrite)
            kwargs = popen.call_args.kwargs
            self.assertIn('env', kwargs, 'Apache must receive the isolated PHP scan directory')
            scan = kwargs['env']['PHP_INI_SCAN_DIR']
            self.assertTrue(scan.startswith(f'/etc/php/{version}/apache2/conf.d:'))
            ini = Path(scan.split(':', 1)[1])
            settings = dict(line.split('=', 1) for line in
                            (ini / '99-release.ini').read_text().splitlines())
            self.assertEqual(settings, {
                'opcache.enable': '1', 'opcache.validate_timestamps': '0',
                'opcache.file_update_protection': '0', 'opcache.memory_consumption': '512',
                'opcache.max_accelerated_files': '100000'})
            self.assertEqual(kwargs['stderr'], subprocess.STDOUT)
            self.assertNotEqual(kwargs['stdout'], subprocess.DEVNULL)
            config = (work / 'httpd.conf').read_text()
            self.assertEqual('mod_rewrite.so' in config, rewrite)
            self.assertIn(f'libphp{version}.so', config)
            self.assertIn(f'PHPIniDir /etc/php/{version}/apache2', config)

    def test_main_runs_root_and_subfolder_with_both_rewrite_modes(self):
        self.ns['launch'] = self.launch_stub
        self.ns['main']()
        self.assertEqual(len(self.calls), 4)
        self.assertEqual({row[1] for row in self.calls}, {
            'software-1.25 root-rewrite', 'software-1.25 subfolder-rewrite',
            'software-1.25 root-no-rewrite', 'software-1.25 subfolder-no-rewrite'})
        self.assertEqual(len({row[2] for row in self.calls}), 4, 'Distinct disposable SQL prefixes')
        self.assertEqual(sum(row[4] for row in self.calls), 2)
        report = json.loads((self.qa / 'software-release125-upgrade.json').read_text())
        self.assertTrue(report['passed'])
        self.assertTrue(report['complete'])
        self.assertEqual(len(report['scopes']), 4)
        self.assertTrue(all(row['checks'] == 3 for row in report['scopes']))
        self.assertEqual(report['selected'], {'php': '8.3', 'database': 'mariadb-10.11'})
        self.assertTrue(all(row['apache_http'] == self.http_runtime for row in report['scopes']))
        self.ns['probe_environment'].assert_called_once_with('native')

    def test_wrong_apache_version_or_sapi_fails_before_populating_database(self):
        for actual in ({'version': '8.2.35', 'sapi': 'apache2handler'},
                       {'version': '8.3.6', 'sapi': 'cli'}, {}, []):
            with self.subTest(actual=actual):
                self.ns['RUNTIME'] = self.root / ('wrong-runtime-' + str(len(self.requests.get.call_args_list)))
                self.http_runtime = actual
                self.ns['launch'] = self.launch_stub
                with self.assertRaisesRegex(AssertionError, 'actual selected Apache PHP runtime'):
                    self.ns['main']()
                self.assertEqual(self.calls, [])
                report = json.loads((self.qa / 'software-release125-upgrade.json').read_text())
                self.assertFalse(report['passed'])
                self.assertFalse(report['checks'][-1]['passed'])
                self.assertEqual(report['scopes'], [])

    def test_cli_or_database_probe_failure_stops_before_apache(self):
        launcher = Mock()
        self.ns['launch'] = launcher
        self.ns['probe_environment'].side_effect = RuntimeError('Acceptance runtime mismatch')
        with self.assertRaisesRegex(RuntimeError, 'runtime mismatch'):
            self.ns['main']()
        launcher.assert_not_called()
        self.assertEqual(self.calls, [])
        report = json.loads((self.qa / 'software-release125-upgrade.json').read_text())
        self.assertFalse(report['passed'])
        self.assertFalse(report['complete'])

    def test_failed_assertion_keeps_evidence_and_failure_exit(self):
        def fail(web, base, check, *args, **kwargs):
            check('synthetic failed data preservation', False)
        self.ns['launch'] = self.launch_stub
        self.ns['organized_update'] = fail
        with self.assertRaisesRegex(AssertionError, 'synthetic failed data preservation'):
            self.ns['main']()
        report = json.loads((self.qa / 'software-release125-upgrade.json').read_text())
        self.assertFalse(report['passed'])
        self.assertFalse(report['complete'])
        self.assertFalse(report['checks'][-1]['passed'])
        self.process.terminate.assert_called_once()

    def test_existing_runtime_is_preserved(self):
        runtime = self.ns['RUNTIME']
        runtime.mkdir()
        marker = runtime / 'retained.txt'
        marker.write_text('retained evidence')
        self.ns['launch'] = self.launch_stub
        with self.assertRaisesRegex(AssertionError, 'new test-runtime'):
            self.ns['main']()
        self.assertEqual(marker.read_text(), 'retained evidence')

    def fixture_functions(self):
        return functions(ROOT / 'tests/organized_update_cases.py', {
            'ROOT': self.root, 'shutil': shutil})

    def test_root_fixture_uses_origin_and_preserves_baseline_bytes(self):
        web = self.root / 'web-root'
        web.mkdir()
        root, url = self.fixture_functions()['prepare_baseline'](
            web, 'http://localhost:1234', '1.25.0', at_root=True)
        self.assertEqual(root, web)
        self.assertEqual(url, 'http://localhost:1234')
        self.assertEqual((root / 'install.php').read_bytes(), (self.baseline / 'install.php').read_bytes())

    def test_default_fixture_keeps_existing_subfolder_layout(self):
        web = self.root / 'web-subfolder'
        web.mkdir()
        root, url = self.fixture_functions()['prepare_baseline'](
            web, 'http://localhost:1234', '1.25.0')
        self.assertEqual(root, web / 'organized-1.25.0')
        self.assertEqual(url, 'http://localhost:1234/organized-1.25.0')
        self.assertTrue((root / 'install.php').is_file())

    def test_root_fixture_refuses_populated_target(self):
        web = self.root / 'populated'
        web.mkdir()
        marker = web / 'config.php'
        marker.write_text('retained configuration')
        with self.assertRaisesRegex(AssertionError, 'empty'):
            self.fixture_functions()['prepare_baseline'](web, 'http://localhost', '1.25.0', at_root=True)
        self.assertEqual(marker.read_text(), 'retained configuration')
        self.assertFalse((web / 'install.php').exists())

    def test_root_seed_passes_the_actual_apache_document_root(self):
        tree = ast.parse((ROOT / 'tests/organized_update_cases.py').read_text(encoding='utf-8'))
        run = next(node for node in tree.body if isinstance(node, ast.FunctionDef) and node.name == 'run')
        records = next(node for node in ast.walk(run) if isinstance(node, ast.FunctionDef) and node.name == 'records')
        for at_root in (True, False):
            web = self.root / 'apache-web'
            installed = web if at_root else web / 'organized-1.25.0'
            runner = Mock(return_value=SimpleNamespace(returncode=0, stdout='{}', stderr=''))
            namespace = {'subprocess': SimpleNamespace(run=runner), 'php': '/usr/bin/php8.3',
                         'ROOT': ROOT, 'root': installed, 'web': web, 'at_root': at_root, 'json': json}
            exec(compile(ast.Module(body=[records], type_ignores=[]), '<actual-records-helper>', 'exec'), namespace)
            self.assertEqual(namespace['records']('seed'), {})
            expected = ['/usr/bin/php8.3', str(ROOT / 'tests/organized_update_fixture.php'), str(installed), 'seed']
            if at_root:
                expected.append(str(web))
            self.assertEqual(runner.call_args.args[0], expected)

    def test_inventory_fixture_receives_the_published_baseline_version(self):
        tree = ast.parse((ROOT / 'tests/organized_update_cases.py').read_text(encoding='utf-8'))
        run = next(node for node in tree.body if isinstance(node, ast.FunctionDef) and node.name == 'run')
        helper = next(node for node in ast.walk(run) if isinstance(node, ast.FunctionDef) and node.name == 'inventory_records')
        for version in ('1.24.0', '1.25.0'):
            runner = Mock(return_value=SimpleNamespace(returncode=0, stdout='{}', stderr=''))
            namespace = {'subprocess': SimpleNamespace(run=runner), 'php': 'fixture-php',
                         'ROOT': ROOT, 'root': self.root, 'version': version, 'json': json}
            exec(compile(ast.Module(body=[helper], type_ignores=[]), '<actual-inventory-helper>', 'exec'), namespace)
            namespace['inventory_records']('seed')
            expected = ['fixture-php', str(ROOT / 'tests/inventory_retention_124_fixture.php'), str(self.root), 'seed']
            if version == '1.25.0':
                expected.append(version)
            self.assertEqual(runner.call_args.args[0], expected)

    def php_fragment(self, code):
        php = os.environ.get('PHP_BINARY') or shutil.which('php')
        self.assertIsNotNone(php, 'PHP is required for actual inventory-fixture contracts')
        return subprocess.run([php, '-r', code], capture_output=True, text=True)

    def test_inventory_seed_checks_the_exact_published_schema(self):
        source = (ROOT / 'tests/inventory_retention_124_fixture.php').read_text(encoding='utf-8')
        guard = source.split("if($argv[2]==='seed'){", 1)[1].split('    $read=', 1)[0]
        for baseline, actual, schema, accepted in [
                ('1.24.0', '1.24.0', '3', True), ('1.25.0', '1.25.0', '5', True),
                ('1.25.0', '1.25.0', '3', False), ('1.24.0', '1.24.0', '5', False),
                ('1.25.0', '1.26.0', '5', False), ('1.24.0', '1.25.0', '3', False)]:
            with self.subTest(baseline=baseline, actual=actual, schema=schema):
                prefix = ('$baseline=' + json.dumps(baseline) + ';define("CWP_VERSION",' + json.dumps(actual)
                          + ');function setting($name){return ' + json.dumps(schema) + ';}')
                result = self.php_fragment(prefix + 'try{' + guard + '}catch(Throwable $e){exit(3);}')
                self.assertEqual(result.returncode, 0 if accepted else 3, result.stderr)

    def test_inventory_snapshot_keeps_v125_operation_context(self):
        source = (ROOT / 'tests/inventory_retention_124_fixture.php').read_text(encoding='utf-8')
        fragment = source.split("$rows=all('SELECT * FROM '.table($name).' ORDER BY id');", 1)[1].split('$out[$name]=$rows;', 1)[0]
        for baseline, row, expected, accepted in [
                ('1.24.0', {'id': 'original'}, {'id': 'original'}, True),
                ('1.24.0', {'id': 'original', 'operation': None}, {'id': 'original'}, True),
                ('1.24.0', {'id': 'original', 'operation': 'packages.list'}, None, False),
                ('1.25.0', {'id': 'original', 'operation': 'packages.list'}, {'id': 'original', 'operation': 'packages.list'}, True),
                ('1.25.0', {'id': 'original', 'operation': 'cloudServers.list'}, {'id': 'original', 'operation': 'cloudServers.list'}, True),
                ('1.25.0', {'id': 'original', 'operation': None}, {'id': 'original', 'operation': None}, True)]:
            with self.subTest(baseline=baseline, row=row):
                prefix = ('$baseline=' + json.dumps(baseline) + ';$name="provider_refreshes";$rows=json_decode('
                          + json.dumps(json.dumps([row])) + ',true);')
                result = self.php_fragment(prefix + 'try{' + fragment + '}catch(Throwable $e){exit(3);}echo json_encode($rows);')
                self.assertEqual(result.returncode, 0 if accepted else 3, result.stderr)
                if accepted:
                    self.assertEqual(json.loads(result.stdout), [expected])

    def test_inventory_reader_binds_v125_account_and_operation(self):
        source = (ROOT / 'tests/inventory_retention_124_fixture.php').read_text(encoding='utf-8')
        reader = source.split('    $read=static function', 1)[1].split('    if(provider_inventory_refresh', 1)[0]
        if '    $state=provider_inventory_refresh' in reader:
            reader = reader.split('    $state=provider_inventory_refresh', 1)[0]
        requires = ''
        for path in ['provider/TransportException.php', 'provider/TransportResponse.php',
                     'twentyi-inventory/InventoryPackage.php', 'twentyi-inventory/InventorySnapshot.php',
                     'twentyi-inventory/InventoryProjector.php']:
            requires += 'require ' + json.dumps(str(ROOT / 'site/includes/foundation' / path)) + ';'
        aliases = ('use CajunWebPro\\Foundation\\Provider\\TransportResponse;'
                   'use CajunWebPro\\Foundation\\TwentyIInventory\\{InventoryProjector,InventorySnapshot};')
        for baseline, account, expected in [
                ('1.24.0', 'original-profile', ''),
                ('1.25.0', 'original-profile', 'twenty-i|original-profile|packages.list'),
                ('1.25.0', 'second-profile', 'twenty-i|second-profile|packages.list')]:
            with self.subTest(baseline=baseline, account=account):
                code = (aliases + requires + '$baseline=' + json.dumps(baseline) + ';$read=static function' + reader
                        + '$calls=0;$snapshot=$read(["id"=>' + json.dumps(account)
                        + '],"SYNTHETIC_ORIGINAL_120_GENERAL_KEY","8.8.8.8",function()use(&$calls){$calls++;});'
                        + 'echo json_encode(["scope"=>$snapshot->scope,"state"=>$snapshot->state,"authorizations"=>$calls]);')
                result = self.php_fragment(code)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(json.loads(result.stdout), {'scope': expected, 'state': 'complete', 'authorizations': 1})

    def test_release_diagnostic_reads_the_shipped_version_source(self):
        inspect = functions(ROOT / 'tests/release_next_from_125.py', {'zipfile': zipfile, 're': re, 'Path': Path})['inspect_zip']
        for label, files, expected in [
                ('published-layout', {'config/defaults.php': "<?php\nconst CWP_VERSION = '1.25.0';\n"}, '1.25.0'),
                ('wrong-version', {'config/defaults.php': "<?php\nconst CWP_VERSION = '1.24.0';\n"}, '1.24.0'),
                ('wrong-root-file', {'VERSION': '1.25.0', 'config/defaults.php': "<?php\nconst CWP_VERSION = '1.24.0';\n"}, '1.24.0'),
                ('missing-runtime-version', {'VERSION': '1.25.0'}, None),
                ('ambiguous-runtime-version', {'config/defaults.php': "<?php\nconst CWP_VERSION = '1.25.0';\nconst CWP_VERSION = '1.24.0';\n"}, None)]:
            with self.subTest(label=label):
                archive = self.root / (label + '.zip')
                with zipfile.ZipFile(archive, 'w') as bundle:
                    for name, content in files.items():
                        bundle.writestr(name, content)
                result = inspect(archive)
                self.assertEqual(result['version_text'], expected)
                self.assertEqual(result.get('version_source'), 'config/defaults.php')


if __name__ == '__main__':
    unittest.main()
