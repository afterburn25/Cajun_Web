"""Portable tests for the new retention boundary; no SQL/Apache/provider execution."""
from pathlib import Path
from copy import deepcopy
import argparse
import ast
import hashlib
import json
import tempfile
import unittest
import zipfile
from unittest.mock import patch
from types import SimpleNamespace
import provider_php_controls_upgrade as subject


def h(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()


def row(**values):
    return {key: h(value) for key, value in values.items()}


def table(**rows):
    return {'count': len(rows), 'rows': {h(key): value for key, value in rows.items()}}


def snapshots():
    before = {'version': '1.25.0', 'tables': {
        'settings': table(counter=row(name='provider_php_control_lifecycle_terminal_count', value='1'), other=row(name='unchanged', value='kept')),
        'provider_php_changes': table(first=row(proof='first'), second=row(proof='second')),
        'provider_php_dispatch_ends': table(first=row(proof='first terminal')),
        'provider_php_change_checks': table(first=row(document='first check')),
        'provider_operations': table(first=row(fingerprint='first wire'), second=row(fingerprint='second wire')),
        'audit': table(first=row(detail='original'), updater=row(detail='legitimate earlier update')),
    }, 'private_files': {'provider.key': {'sha256': h('original key'), 'bytes': 32}}}
    after = deepcopy(before)
    after['tables']['settings']['rows'][h('counter')]['value'] = h('2')
    for name in ('provider_php_dispatch_ends', 'audit'):
        after['tables'][name]['rows'][h('new')] = row(detail='one actual local recovery')
        after['tables'][name]['count'] += 1
    return before, after


class Guards(unittest.TestCase):
    def setUp(self):
        self.before, self.after = snapshots()
        self.companion = Path(__file__).with_name('provider_php_controls_upgrade_fixture.php').read_text(encoding='utf-8')

    def refuses(self):
        with self.assertRaises((AssertionError, KeyError)):
            subject.terminal_recovery_retained(self.before, self.after)

    def test_one_terminal_counter_and_audit_append_preserves_originals(self):
        saved = deepcopy((self.before, self.after))
        self.assertTrue(subject.terminal_recovery_retained(self.before, self.after))
        self.assertEqual((self.before, self.after), saved)

    def test_original_terminal_edit_refuses(self):
        self.after['tables']['provider_php_dispatch_ends']['rows'][h('first')]['proof'] = h('changed')
        self.refuses()

    def test_original_audit_edit_refuses(self):
        self.after['tables']['audit']['rows'][h('first')]['detail'] = h('changed')
        self.refuses()

    def test_missing_original_audit_refuses(self):
        del self.after['tables']['audit']['rows'][h('first')]
        self.after['tables']['audit']['count'] -= 1
        self.refuses()

    def test_extra_audit_refuses(self):
        self.after['tables']['audit']['rows'][h('extra')] = row(detail='unrelated action')
        self.after['tables']['audit']['count'] += 1
        self.refuses()

    def test_missing_new_audit_refuses(self):
        self.after['tables']['audit'] = deepcopy(self.before['tables']['audit'])
        self.refuses()

    def test_missing_new_terminal_refuses(self):
        self.after['tables']['provider_php_dispatch_ends'] = deepcopy(self.before['tables']['provider_php_dispatch_ends'])
        self.refuses()

    def test_extra_terminal_refuses(self):
        t = self.after['tables']['provider_php_dispatch_ends']
        t['rows'][h('extra')] = row(proof='unexpected'); t['count'] += 1
        self.refuses()

    def test_counter_must_advance_exactly_once(self):
        self.after['tables']['settings']['rows'][h('counter')]['value'] = h('3')
        self.refuses()

    def test_original_counter_must_be_one(self):
        self.before['tables']['settings']['rows'][h('counter')]['value'] = h('0')
        self.refuses()

    def test_counter_identity_cannot_be_duplicated(self):
        t = self.before['tables']['settings']
        t['rows'][h('duplicate')] = deepcopy(t['rows'][h('counter')]); t['count'] += 1
        self.refuses()

    def test_other_setting_change_refuses(self):
        self.after['tables']['settings']['rows'][h('other')]['value'] = h('changed')
        self.refuses()

    def test_original_request_change_refuses(self):
        self.after['tables']['provider_php_changes']['rows'][h('second')]['proof'] = h('changed')
        self.refuses()

    def test_original_ledger_change_refuses(self):
        self.after['tables']['provider_operations']['rows'][h('second')]['fingerprint'] = h('changed')
        self.refuses()

    def test_original_check_loss_refuses(self):
        self.after['tables']['provider_php_change_checks'] = table()
        self.refuses()

    def test_original_private_key_change_refuses(self):
        self.after['private_files']['provider.key']['sha256'] = h('changed')
        self.refuses()

    def test_added_private_key_refuses(self):
        self.after['private_files']['another.key'] = {'sha256': h('extra'), 'bytes': 32}
        self.refuses()

    def test_added_business_column_refuses(self):
        self.after['tables']['provider_operations']['rows'][h('second')]['extra'] = h('extra')
        self.refuses()

    def test_added_original_terminal_column_refuses(self):
        self.after['tables']['provider_php_dispatch_ends']['rows'][h('first')]['extra'] = h('extra')
        self.refuses()

    def test_added_original_audit_column_refuses(self):
        self.after['tables']['audit']['rows'][h('first')]['extra'] = h('extra')
        self.refuses()

    def test_added_business_table_refuses(self):
        self.after['tables']['unexpected'] = table()
        self.refuses()

    def test_runtime_version_change_at_local_recovery_refuses(self):
        self.after['version'] = '9.9.9'
        self.refuses()

    def target(self, *, source_change=False, payload_change=False, missing=False):
        # Actual new feature/Git byte comparison, with only inherited archive and
        # subprocess I/O substituted. The inherited archive guards run separately.
        with tempfile.TemporaryDirectory() as directory:
            repo = Path(directory)
            originals, files = {}, {}
            for index, name in enumerate(subject.FEATURE_PATHS):
                data = ('<?php exact ' + str(index)).encode()
                path = repo / 'site' / name; path.parent.mkdir(parents=True, exist_ok=True); path.write_bytes(data)
                originals['a' * 40 + ':site/' + name] = data
                files['cwp-update-1.25.0/site/' + name] = subject.digest(path)
            first = subject.FEATURE_PATHS[0]
            if source_change:
                (repo / 'site' / first).write_bytes(b'changed local source')
            if payload_change:
                files['cwp-update-1.25.0/site/' + first] = '0' * 64
            if missing:
                del files['cwp-update-1.25.0/site/' + first]
            expected = (repo / 'update.zip', '1.25.0', 'a' * 40, files)
            with patch.object(subject, 'target_inputs', return_value=expected), patch.object(subject.subprocess, 'check_output', side_effect=lambda argv, **kw: originals[argv[2]]):
                return subject.changes_target_inputs(repo) == expected

    def test_all_current_feature_files_bind_to_git_and_payload(self):
        self.assertTrue(self.target())

    def test_local_feature_drift_refuses(self):
        with self.assertRaises(AssertionError):
            self.target(source_change=True)

    def test_feature_payload_drift_refuses(self):
        with self.assertRaises(AssertionError):
            self.target(payload_change=True)

    def test_missing_current_feature_payload_refuses(self):
        with self.assertRaises(KeyError):
            self.target(missing=True)

    def test_exact_seven_owner_actions_and_recovery_snapshot_boundary(self):
        source = Path(subject.__file__).read_text(encoding='utf-8')
        calls = [node for node in ast.walk(ast.parse(source)) if isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id == 'action']
        self.assertEqual([node.args[0].value for node in sorted(calls, key=lambda n: n.lineno)], ['apply', 'apply', 'recover', 'apply', 'apply', 'recover', 'apply'])
        self.assertIn("terminal_recovery_retained(phases['before_terminal_recovery'], phases['terminal_recovered'])", source)
        self.assertLess(source.index("phases['before_terminal_recovery'] = command('snapshot')"), source.index("command('recover-terminal')"))

    def test_installed_snapshot_survives_without_current_feature_code(self):
        self.assertLess(self.companion.index("if($mode==='snapshot')"), self.companion.index("require CWP_CODE_ROOT.'/app/providers/php_controls_changes.php'"))
        self.assertIn("CWP_ROOT',$root", self.companion)
        self.assertIn("$root!==$web.'/subfolder'", self.companion)
        self.assertIn("PHP_OS_FAMILY==='Windows'", self.companion)

    def test_database_preflight_uses_selected_mapped_loopback_port(self):
        preflight = self.companion.split("if($mode==='preflight'){", 1)[1].split("if(!is_file($root", 1)[0]
        self.assertIn("'mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4'", preflight)
        self.assertIn("$s->execute(['phcu_'])", preflight)
        self.assertNotIn('@@port', preflight)

    def test_real_gateway_and_no_transport_outside_seed(self):
        self.assertIn('new ProviderGateway(new TwentyIOperationAdapter(),new PhcuWriteTransport($authorize),new ProviderOperationLedger())', self.companion)
        self.assertIn("$mode==='seed'?($posts===2&&$optionsCalls===2):[$dns,$gets,$optionsCalls,$posts]===[0,0,0,0]", self.companion)
        self.assertIn("studio_activate($invite['invite'],PHCU_CLIENT_PASSWORD)", self.companion)

    def test_terminal_fault_is_actual_post_insert_and_cleaned(self):
        self.assertIn('@phcu_rows=(SELECT COUNT(*)', self.companion)
        self.assertIn("query('SELECT @phcu_rows')->fetchColumn()===2", self.companion)
        self.assertIn("finally{db()->exec('DROP TRIGGER phcu_terminal_fault');}", self.companion)

    def test_default_options_remain_unavailable_and_cooldown_real(self):
        self.assertIn("phcu_read($link,'available',false)", self.companion)
        self.assertIn("$unavailable['reason']==='unexpected_response'", self.companion)
        self.assertIn('PackagePhpOptionSet::fromNormalizedRows', self.companion)
        self.assertIn('while(time()<$at+30)', self.companion)
        self.assertNotIn('SET started_epoch', self.companion)
        self.assertNotIn('SET recorded_epoch', self.companion)

    def archive(self, entries):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'fixture.zip'
            with zipfile.ZipFile(path, 'w') as archive:
                for name, value in entries:
                    archive.writestr(name, value)
            return subject.archive_map(path, subject.digest(path))

    def test_safe_archive_maps_exact_bytes(self):
        self.assertEqual(self.archive([('a/b.php', b'original')]), {'a/b.php': hashlib.sha256(b'original').hexdigest()})

    def test_archive_traversal_refuses(self):
        with self.assertRaises(AssertionError): self.archive([('../escape', b'no')])

    def test_archive_absolute_refuses(self):
        with self.assertRaises(AssertionError): self.archive([('/escape', b'no')])

    def test_archive_backslash_refuses(self):
        # Windows ZipInfo normalizes native separators on creation. Put the
        # hostile original bytes in both headers to exercise the reader itself.
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'fixture.zip'
            with zipfile.ZipFile(path, 'w') as archive: archive.writestr('a/escape', b'no')
            data = path.read_bytes(); self.assertEqual(data.count(b'a/escape'), 2)
            path.write_bytes(data.replace(b'a/escape', b'a\\escape'))
            with self.assertRaises(AssertionError): subject.archive_map(path)

    def test_archive_case_collision_refuses(self):
        with self.assertRaises(AssertionError): self.archive([('A.php', b'a'), ('a.php', b'b')])

    def test_archive_symlink_refuses(self):
        info = zipfile.ZipInfo('link'); info.create_system = 3; info.external_attr = 0o120777 << 16
        with self.assertRaises(AssertionError): self.archive([(info, b'outside')])

    def test_archive_digest_refuses(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'bad.zip'; path.write_bytes(b'not the immutable archive')
            with self.assertRaises(AssertionError): subject.archive_map(path, '0' * 64)

    def test_fresh_output_does_not_replace_existing_evidence(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory).resolve(); path = root / 'existing.json'; path.write_text('original')
            with self.assertRaises(AssertionError): subject.fresh_path(path, root)
            self.assertEqual(path.read_text(), 'original')

    def test_source_contains_no_other_lane_import_or_target_version_override(self):
        source = Path(subject.__file__).read_text(encoding='utf-8')
        self.assertNotIn('registered_nameservers_upgrade', source)
        self.assertNotIn('1.26.0', source)
        self.assertIn("version = (repo / 'VERSION').read_text", source)
        self.assertIn("source != BASELINE_SOURCE", source)
        self.assertEqual(subject.BASELINE_MEMBERS, 2895)
        self.assertEqual(subject.BASELINE_BYTES, 15843208)

    def test_corruption_probe_uses_original_nested_review_reference(self):
        probe = self.companion.split('function phcu_probes():void', 1)[1].split('function phcu_refuse():void', 1)[0]
        self.assertIn("[$r['review']['options_read_id']]", probe)
        self.assertNotIn("[$r['options_read_id']]", probe)

    def test_private_launcher_restores_destination_after_success_and_failure(self):
        for fails in [False, True]:
            module = SimpleNamespace(QA=Path('/public/qa'))
            def launch(work, web, rewrite):
                self.assertEqual(module.QA, work); self.assertTrue(rewrite)
                if fails: raise RuntimeError('synthetic launcher failure')
                return 'original process'
            module.launch = launch
            if fails:
                with self.assertRaises(RuntimeError): subject.launch_private(module, Path('/private/work'), Path('/private/work/web'))
            else:
                self.assertEqual(subject.launch_private(module, Path('/private/work'), Path('/private/work/web')), 'original process')
            self.assertEqual(module.QA, Path('/public/qa'))

    def test_public_log_summary_exports_no_raw_diagnostic(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'private.log'
            raw = b'PHP Warning: private synthetic password must not appear in report\n'
            path.write_bytes(raw)
            result = subject.log_summary(path)
            self.assertEqual(set(result), {'present', 'bytes', 'sha256', 'within_bound', 'php_diagnostic'})
            self.assertTrue(result['php_diagnostic'])
            self.assertEqual(result['sha256'], hashlib.sha256(raw).hexdigest())
            self.assertNotIn('password', json.dumps(result))
            path.write_bytes(b'x' * (4 * 1024 * 1024 + 1))
            self.assertFalse(subject.log_summary(path)['within_bound'])



class Results(unittest.TextTestResult):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs); self.checks = []
    def addSuccess(self, test):
        super().addSuccess(test); self.checks.append({'name': test.id(), 'passed': True})
    def addFailure(self, test, err):
        super().addFailure(test, err); self.checks.append({'name': test.id(), 'passed': False})
    def addError(self, test, err):
        super().addError(test, err); self.checks.append({'name': test.id(), 'passed': False})


if __name__ == '__main__':
    parser = argparse.ArgumentParser(); parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    if args.report:
        assert not args.report.exists() and not args.report.is_symlink()
    result = unittest.TextTestRunner(verbosity=2, resultclass=Results).run(unittest.defaultTestLoader.loadTestsFromTestCase(Guards))
    if args.report:
        with args.report.open('x', encoding='utf-8') as output:
            json.dump({'passed': result.wasSuccessful(), 'scope': 'Portable retention/feature-byte predicates and explicit source contracts only; no installed SQL/Apache/provider execution', 'checks': result.checks}, output, indent=2)
    raise SystemExit(0 if result.wasSuccessful() else 1)
