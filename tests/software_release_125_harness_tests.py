"""Offline regression tests for the real published-v1.25 gate orchestration.

Execute the source functions with process/HTTP/database boundaries replaced.
These tests do not claim Apache, OPCache, database or populated-upgrade acceptance.
"""
import ast
import getpass
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
from types import SimpleNamespace
import unittest
from unittest.mock import Mock, patch


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
        self.env = patch.dict(os.environ, {'CWP_TEST_DATABASE': 'cwp_test'})
        self.env.start()
        self.addCleanup(self.env.stop)
        self.process = Mock()
        self.process.poll.return_value = None
        self.requests = SimpleNamespace(
            get=Mock(return_value=SimpleNamespace(status_code=200, text='upgrade-gate-ready')),
            RequestException=ConnectionError)
        self.calls = []
        self.ns = functions(ROOT / 'tests/software_release_125_upgrade.py', {
            'ROOT': self.root, 'QA': self.qa,
            'RUNTIME': self.root / 'test-runtime/gate', 'VERSION': '1.26.0',
            'Path': Path, 'json': json, 'os': os, 'shutil': shutil, 'time': time,
            'socket': socket, 'subprocess': subprocess, 'getpass': getpass,
            'grp': SimpleNamespace(getgrgid=lambda _: SimpleNamespace(gr_name='qa')),
            'requests': self.requests, 'organized_update': self.organized,
        })

    def organized(self, web, base, check, qa, php, label, prefix, version, *, at_root=False):
        self.calls.append((web, label, prefix, version, at_root))
        self.assertFalse(any(web.iterdir()), 'Readiness probe must not enter the installed fixture')
        check(label + ' retained fixture callback', True)

    def launch_stub(self, work, web, rewrite):
        return self.process, 'http://127.0.0.1:12345'

    def test_launcher_supplies_cache_contract_and_retains_startup_log(self):
        for rewrite in (False, True):
            work = self.root / str(rewrite)
            web = work / 'web'
            web.mkdir(parents=True)
            with patch.object(os, 'getgid', return_value=0, create=True), \
                    patch.object(subprocess, 'Popen', return_value=self.process) as popen:
                self.ns['launch'](work, web, rewrite)
            kwargs = popen.call_args.kwargs
            self.assertIn('env', kwargs, 'Apache must receive the isolated PHP scan directory')
            scan = kwargs['env']['PHP_INI_SCAN_DIR']
            self.assertTrue(scan.startswith('/etc/php/8.3/apache2/conf.d:'))
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
        self.assertTrue(all(row['checks'] == 2 for row in report['scopes']))

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


if __name__ == '__main__':
    unittest.main()
