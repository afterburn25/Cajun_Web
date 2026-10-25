"""Portable installed cloud request upgrade guards. No SQL, Apache or provider execution."""
from pathlib import Path
from copy import deepcopy
import argparse
import ast
import base64
import hashlib
import json
import tempfile
import unittest
import zipfile
from unittest.mock import patch
import provider_cloud_site_requests_upgrade as subject


def h(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()


def row(**values):
    return {key: h(value) for key, value in values.items()}


def table(**rows):
    return {'count': len(rows), 'rows': {h(key): value for key, value in rows.items()}}


def snapshots():
    before = {'version': '1.25.0', 'tables': {
        'users': table(owner=row(id=1, auth_version='original', password_hash='original')),
        'settings': table(head=row(name='provider_cloud_site_request_head', value='10:original')),
        'provider_cloud_site_request_events': table(request=row(document='original', fingerprint='original')),
        'provider_operation_grants': table(grant=row(scope_params='signed request origin')),
        'provider_operations': table(claim=row(status='claimed', fingerprint='original')),
        'provider_cloud_confirmations': table(confirmed=row(document='original independent observation')),
        'audit': table(prepared=row(id=1, user_id=1, action='provider.cloud_site_request_prepared', detail='original', created_at='original')),
    }, 'private_files': {'provider.key': {'sha256': h('original key'), 'bytes': 32}}}
    after = deepcopy(before)
    after['tables']['audit']['rows'][h('update')] = row(id=2, user_id=1, action='structure.apply', detail='Version 1.25.0; state complete', created_at='now')
    after['tables']['audit']['count'] += 1
    return before, after


class Guards(unittest.TestCase):
    def setUp(self):
        self.before, self.after = snapshots()

    def refuses(self):
        with self.assertRaises((AssertionError, KeyError)):
            subject.updater_retained(self.before, self.after, ['apply'], '1.25.0')

    def test_exact_updater_append_is_nonmutating(self):
        original = deepcopy((self.before, self.after))
        self.assertTrue(subject.updater_retained(self.before, self.after, ['apply'], '1.25.0'))
        self.assertEqual((self.before, self.after), original)

    def test_original_columns_never_change(self):
        for name, contents in self.before['tables'].items():
            for key, columns in contents['rows'].items():
                for column in columns:
                    with self.subTest(table=name, column=column):
                        saved = deepcopy(self.after)
                        self.after['tables'][name]['rows'][key][column] = h('tampered')
                        self.refuses(); self.after = saved

    def test_added_original_column_refuses_including_audit(self):
        for name, contents in self.before['tables'].items():
            with self.subTest(table=name):
                saved = deepcopy(self.after)
                self.after['tables'][name]['rows'][next(iter(contents['rows']))]['extra'] = h('extra')
                self.refuses(); self.after = saved

    def test_original_row_loss_refuses(self):
        self.after['tables']['provider_operations']['rows'].clear()
        self.after['tables']['provider_operations']['count'] = 0
        self.refuses()

    def test_unrelated_table_refuses(self):
        self.after['tables']['new'] = table(); self.refuses()

    def test_added_non_audit_row_refuses(self):
        self.after['tables']['provider_operations']['rows'][h('other')] = row(status='recreated')
        self.after['tables']['provider_operations']['count'] += 1; self.refuses()

    def test_private_key_changed_refuses(self):
        self.after['private_files']['provider.key']['sha256'] = h('changed'); self.refuses()

    def test_new_private_file_refuses(self):
        self.after['private_files']['new'] = {'bytes': 1, 'sha256': h('new')}; self.refuses()

    def test_audit_action_is_exact(self):
        self.after['tables']['audit']['rows'][h('update')]['action'] = h('provider.send'); self.refuses()

    def test_audit_detail_is_exact(self):
        self.after['tables']['audit']['rows'][h('update')]['detail'] = h('Version 1.26.0; state complete'); self.refuses()

    def test_audit_actual_owner_is_exact(self):
        self.after['tables']['audit']['rows'][h('update')]['user_id'] = h(0); self.refuses()

    def test_audit_columns_are_closed(self):
        self.after['tables']['audit']['rows'][h('update')]['extra'] = h('extra'); self.refuses()

    def test_missing_update_audit_refuses(self):
        self.after['tables']['audit'] = deepcopy(self.before['tables']['audit']); self.refuses()

    def test_second_audit_refuses(self):
        self.after['tables']['audit']['rows'][h('other')] = deepcopy(self.after['tables']['audit']['rows'][h('update')])
        self.after['tables']['audit']['count'] += 1; self.refuses()

    def test_unknown_or_empty_actions_refuse(self):
        for actions in ([], ['inspect'], ['delete']):
            with self.subTest(actions=actions), self.assertRaises(AssertionError):
                subject.updater_retained(self.before, self.after, actions, '1.25.0')

    def test_rollback_exact_audit_and_old_version_allowed(self):
        self.after['version'] = '1.24.0'
        self.after['tables']['audit']['rows'][h('update')].update(action=h('structure.recover'), detail=h('Version 1.25.0; state rolled-back'))
        self.assertTrue(subject.updater_retained(self.before, self.after, ['recover'], '1.25.0'))

    def test_damaged_head_is_retained_not_reconstructed(self):
        self.before['tables']['settings'] = table(); self.after['tables']['settings'] = table()
        self.assertTrue(subject.updater_retained(self.before, self.after, ['apply'], '1.25.0'))
        self.after['tables']['settings'] = table(head=row(name='provider_cloud_site_request_head', value='rebuilt'))
        self.refuses()

    def test_seven_real_action_calls_and_constant_agree(self):
        tree = ast.parse(Path(subject.__file__).read_text())
        main = next(n for n in tree.body if isinstance(n, ast.FunctionDef) and n.name == 'main')
        calls = sorted((n for n in ast.walk(main) if isinstance(n, ast.Call) and isinstance(n.func, ast.Name) and n.func.id == 'action'), key=lambda n:n.lineno)
        self.assertEqual(tuple(ast.literal_eval(n.args[0]) for n in calls), subject.ACTIONS)
        self.assertEqual(subject.ACTIONS, ('apply','apply','recover','apply','apply','recover','apply'))

    def test_mapped_port_is_not_server_internal_port(self):
        text = Path(subject.__file__).with_name('provider_cloud_site_requests_upgrade_fixture.php').read_text()
        self.assertIn("port='.$port.';dbname=cwp_test", text)
        self.assertNotIn('SELECT @@port', text)
        self.assertIn("(int)config()['db']['port']!==(int)$port", text)

    def test_snapshot_precedes_candidate_only_include(self):
        text = Path(subject.__file__).with_name('provider_cloud_site_requests_upgrade_fixture.php').read_text()
        self.assertLess(text.index("if($mode==='snapshot')"), text.index("if($mode!=='baseline-seed')require_once"))
        self.assertIn("PHP_OS_FAMILY==='Windows'", text)
        self.assertIn("'baseline-seed'=>[3,2,1],'seed'=>[6,3,2]", text)

    def test_companion_and_helper_exact_git_binding(self):
        names = ('provider_cloud_site_requests_upgrade.py','provider_cloud_site_requests_upgrade_fixture.php','provider_cloud_site_requests_upgrade_test.py','release_125_upgrade_acceptance.py','release_125_upgrade_cases.py','release_125_retention.py','acceptance_environment.py')
        with tempfile.TemporaryDirectory() as name:
            root = Path(name); (root/'tests').mkdir()
            for path in names: (root/'tests'/path).write_bytes(b'exact source\n')
            with patch.object(subject.subprocess, 'check_output', return_value=b'exact source\n'):
                result=subject.fixture_inputs(root,'a'*40)
                self.assertEqual(len(result),7)
                (root/'tests'/names[1]).write_bytes(b'changed')
                with self.assertRaises(AssertionError):subject.fixture_inputs(root,'a'*40)

    def test_unavailable_baseline_and_wrong_size_refuse(self):
        with tempfile.TemporaryDirectory() as name:
            root=Path(name);(root/'test-runtime').mkdir()
            with self.assertRaises(AssertionError):subject.baseline_inputs(root)
            (root/'test-runtime'/subject.BASELINE_NAME).write_bytes(b'wrong')
            with self.assertRaises(AssertionError):subject.baseline_inputs(root)

    def test_actual_baseline_map_must_equal_explicit_inventory(self):
        with tempfile.TemporaryDirectory() as name:
            root=Path(name);(root/'test-runtime').mkdir();(root/'tools').mkdir()
            archive=root/'test-runtime'/subject.BASELINE_NAME
            with zipfile.ZipFile(archive,'w') as z:z.writestr('app/old.php',b'old')
            actual=subject.archive_map(archive)
            inventory=root/'tools/legacy-release-files.json';inventory.write_text(json.dumps({'1.25.0':{'files':actual}}))
            with patch.object(subject,'BASELINE_SHA',subject.digest(archive)),patch.object(subject,'BASELINE_MEMBERS',1),patch.object(subject,'BASELINE_BYTES',archive.stat().st_size):
                self.assertEqual(subject.baseline_inputs(root),(archive,actual))
                inventory.write_text(json.dumps({'1.25.0':{'files':{}}}))
                with self.assertRaises(AssertionError):subject.baseline_inputs(root)

    def actual_target(self, mutate=None, source='a'*40):
        with tempfile.TemporaryDirectory() as name:
            root=Path(name);(root/'site/app').mkdir(parents=True);(root/'tools/update').mkdir(parents=True);(root/'dist').mkdir()
            (root/'VERSION').write_text('1.25.0\n');(root/'site/app/code.php').write_bytes(b'<?php exact candidate;')
            (root/'tools/legacy-release-files.json').write_bytes(b'{}');(root/'tools/update/owner-update.php').write_bytes(b'<?php exact owner updater;')
            payload={'app/code.php':subject.digest(root/'site/app/code.php')}
            manifest={'source_commit':source,'version':'1.25.0','files':payload,'history_sha256':subject.digest(root/'tools/legacy-release-files.json')}
            envelope=b"<?php\nif (!defined('CWP_BOOT')) { http_response_code(404); exit; }\nreturn json_decode(base64_decode('"+base64.b64encode(json.dumps(manifest).encode())+b"'),true,64,JSON_THROW_ON_ERROR);\n"
            archive=root/'dist/CajunWebPro-1.25.0-UPDATE.zip';prefix='cwp-update-1.25.0/'
            with zipfile.ZipFile(archive,'w') as z:
                z.writestr(prefix+'site/app/code.php',(root/'site/app/code.php').read_bytes())
                z.writestr(prefix+'manifest.php',envelope)
                z.writestr(prefix+'legacy-release-files.json',b'{}')
                z.writestr(prefix+'owner-update.php',b'<?php exact owner updater;')
            descriptor={'source_commit':source,'version':'1.25.0','artifact':archive.name,'sha256':subject.digest(archive),'files':subject.archive_map(archive)}
            if mutate:mutate(root,descriptor)
            archive.with_suffix('.manifest.json').write_text(json.dumps(descriptor))
            def output(args, **kwargs):
                return source if args[1]=='rev-parse' else 'site/app/code.php\n'
            with patch.object(subject.subprocess,'check_output',side_effect=output),patch.object(subject.subprocess,'check_call',return_value=0):
                return subject.target_inputs(root)

    def test_same_version_distinct_source_passes_actual_target_validator(self):
        _,version,source,files=self.actual_target()
        self.assertEqual(version,'1.25.0');self.assertEqual(source,'a'*40)
        self.assertIn('cwp-update-1.25.0/site/app/code.php',files)

    def test_actual_target_rejects_baseline_source(self):
        with self.assertRaises(AssertionError):self.actual_target(source=subject.BASELINE_SOURCE)

    def test_actual_target_rejects_mismatched_source_descriptor(self):
        with self.assertRaises(AssertionError):self.actual_target(lambda root,d:d.update(source_commit='b'*40))

    def test_actual_target_rejects_changed_shipping_and_history_bytes(self):
        for relative in ('site/app/code.php','tools/legacy-release-files.json','tools/update/owner-update.php'):
            with self.subTest(path=relative),self.assertRaises(AssertionError):
                self.actual_target(lambda root,d:(root/relative).write_bytes(b'changed'))

    def test_actual_target_rejects_descriptor_member_map_drift(self):
        with self.assertRaises(AssertionError):self.actual_target(lambda root,d:d['files'].update({'injected':'0'*64}))

    def test_linked_parent_refusal_is_explicitly_modeled(self):
        with tempfile.TemporaryDirectory() as name:
            root=Path(name).resolve();child=root/'linked'/'output'
            with patch.object(Path,'is_symlink',autospec=True,side_effect=lambda p:p==root/'linked'):
                with self.assertRaises(AssertionError):subject.fresh_path(child,root)

    def test_fresh_path_rejects_existing_path_and_parent_escape(self):
        with tempfile.TemporaryDirectory() as name:
            root = Path(name).resolve(); child = root / 'new'
            subject.fresh_path(child, root)
            child.mkdir()
            with self.assertRaises(AssertionError): subject.fresh_path(child, root)
            with self.assertRaises(AssertionError): subject.fresh_path(root / '..' / 'escape', root)

    def test_actual_archive_guards_reject_unsafe_members(self):
        for member in ('../escape', '/escape', 'a/../b', 'a:b', 'a//b'):
            with self.subTest(member=member), tempfile.TemporaryDirectory() as name:
                archive = Path(name) / 'fixture.zip'
                with zipfile.ZipFile(archive, 'w') as z: z.writestr(member, b'bounded')
                with self.assertRaises(AssertionError): subject.archive_map(archive, subject.digest(archive))

    def test_actual_archive_guards_reject_duplicates_and_aliases(self):
        with tempfile.TemporaryDirectory() as name:
            archive = Path(name) / 'fixture.zip'
            with zipfile.ZipFile(archive, 'w') as z:
                z.writestr('File.php', b'first'); z.writestr('file.php', b'second')
            with self.assertRaises(AssertionError): subject.archive_map(archive, subject.digest(archive))

    def test_archive_hash_and_exact_members(self):
        with tempfile.TemporaryDirectory() as name:
            archive = Path(name) / 'fixture.zip'
            with zipfile.ZipFile(archive, 'w') as z: z.writestr('app/code.php', b'exact')
            self.assertEqual(subject.archive_map(archive, subject.digest(archive)), {'app/code.php': hashlib.sha256(b'exact').hexdigest()})
            with self.assertRaises(AssertionError): subject.archive_map(archive, '0' * 64)

    def test_log_summary_never_returns_raw_error(self):
        with tempfile.TemporaryDirectory() as name:
            path = Path(name) / 'private.log'; path.write_bytes(b'PHP Warning: SYNTHETIC_SECRET')
            report = subject.log_summary(path)
            self.assertTrue(report['php_diagnostic']); self.assertNotIn('SYNTHETIC_SECRET', json.dumps(report))
            path.write_bytes(b'x' * (4 * 1024 * 1024 + 1))
            self.assertFalse(subject.log_summary(path)['within_bound'])
            self.assertFalse(subject.log_summary(Path(name) / 'missing')['present'])

    def test_feature_files_bind_to_exact_git_and_archive(self):
        with tempfile.TemporaryDirectory() as name:
            root = Path(name); originals, files = {}, {}
            for index, path in enumerate(subject.FEATURE_PATHS):
                data = ('<?php exact ' + str(index)).encode(); target = root / 'site' / path
                target.parent.mkdir(parents=True, exist_ok=True); target.write_bytes(data)
                originals['a' * 40 + ':site/' + path] = data
                files['cwp-update-1.25.0/site/' + path] = subject.digest(target)
            expected = (root / 'update.zip', '1.25.0', 'a' * 40, files)
            with patch.object(subject, 'target_inputs', return_value=expected), patch.object(subject.subprocess, 'check_output', side_effect=lambda argv, **kwargs: originals[argv[2]]):
                self.assertEqual(subject.changes_target_inputs(root), expected)
                (root / 'site' / subject.FEATURE_PATHS[0]).write_bytes(b'changed')
                with self.assertRaises(AssertionError): subject.changes_target_inputs(root)

def main():
    parser = argparse.ArgumentParser(); parser.add_argument('--report', type=Path, required=True); args = parser.parse_args()
    names = unittest.defaultTestLoader.getTestCaseNames(Guards); checks = []
    for name in names:
        result = unittest.TestResult(); Guards(name).run(result)
        checks.append({'name': name, 'passed': result.wasSuccessful()})
    report = {'passed': all(c['passed'] for c in checks), 'checks': checks,
              'scope': 'Actual portable updater-retention/path/archive/source/log guards with synthetic temporary inputs. No native SQL, installed UPDATE, Apache, browser or provider execution.'}
    args.report.parent.mkdir(parents=True, exist_ok=True); args.report.write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print(json.dumps({'passed': report['passed'], 'checks': len(checks)}))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
