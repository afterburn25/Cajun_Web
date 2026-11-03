"""Portable tests for the new retention boundary; no SQL/Apache/provider execution."""
from pathlib import Path
from copy import deepcopy
import argparse
import ast
import hashlib
import json
import tempfile
import unittest
from unittest.mock import patch
import registered_nameserver_changes_upgrade as subject


def h(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()


def row(**values):
    return {key: h(value) for key, value in values.items()}


def table(**rows):
    return {'count': len(rows), 'rows': {h(key): value for key, value in rows.items()}}


def snapshots():
    before = {'version': '1.25.0', 'tables': {
        'settings': table(counter=row(name='registered_nameserver_changes_terminal_count', value='1'), other=row(name='unchanged', value='kept')),
        'provider_nameserver_change_requests': table(first=row(proof='first'), second=row(proof='second')),
        'provider_nameserver_change_terminals': table(first=row(proof='first terminal')),
        'provider_nameserver_change_checks': table(first=row(document='first check')),
        'provider_operations': table(first=row(fingerprint='first wire'), second=row(fingerprint='second wire')),
        'audit': table(first=row(detail='original'), updater=row(detail='legitimate earlier update')),
    }, 'private_files': {'provider.key': {'sha256': h('original key'), 'bytes': 32}}}
    after = deepcopy(before)
    after['tables']['settings']['rows'][h('counter')]['value'] = h('2')
    for name in ('provider_nameserver_change_terminals', 'audit'):
        after['tables'][name]['rows'][h('new')] = row(detail='one actual local recovery')
        after['tables'][name]['count'] += 1
    return before, after


class Guards(unittest.TestCase):
    def setUp(self):
        self.before, self.after = snapshots()
        self.companion = Path(__file__).with_name('registered_nameserver_changes_upgrade_fixture.php').read_text(encoding='utf-8')

    def refuses(self):
        with self.assertRaises((AssertionError, KeyError)):
            subject.terminal_recovery_retained(self.before, self.after)

    def test_one_terminal_counter_and_audit_append_preserves_originals(self):
        saved = deepcopy((self.before, self.after))
        self.assertTrue(subject.terminal_recovery_retained(self.before, self.after))
        self.assertEqual((self.before, self.after), saved)

    def test_original_terminal_edit_refuses(self):
        self.after['tables']['provider_nameserver_change_terminals']['rows'][h('first')]['proof'] = h('changed')
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
        self.after['tables']['provider_nameserver_change_terminals'] = deepcopy(self.before['tables']['provider_nameserver_change_terminals'])
        self.refuses()

    def test_extra_terminal_refuses(self):
        t = self.after['tables']['provider_nameserver_change_terminals']
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
        self.after['tables']['provider_nameserver_change_requests']['rows'][h('second')]['proof'] = h('changed')
        self.refuses()

    def test_original_ledger_change_refuses(self):
        self.after['tables']['provider_operations']['rows'][h('second')]['fingerprint'] = h('changed')
        self.refuses()

    def test_original_check_loss_refuses(self):
        self.after['tables']['provider_nameserver_change_checks'] = table()
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
        self.after['tables']['provider_nameserver_change_terminals']['rows'][h('first')]['extra'] = h('extra')
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

    def test_all_four_current_feature_files_bind_to_git_and_payload(self):
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
        self.assertLess(self.companion.index("if($mode==='snapshot')"), self.companion.index("require CWP_CODE_ROOT.'/app/providers/registered_nameserver_changes.php'"))
        self.assertIn("CWP_ROOT',$root", self.companion)
        self.assertIn("$root!==$web.'/subfolder'", self.companion)
        self.assertIn("PHP_OS_FAMILY==='Windows'", self.companion)

    def test_database_preflight_uses_selected_mapped_loopback_port(self):
        preflight = self.companion.split("if($mode==='preflight'){", 1)[1].split("if(!is_file($root", 1)[0]
        self.assertIn("'mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4'", preflight)
        self.assertIn("$s->execute(['rnscu_'])", preflight)
        self.assertNotIn('@@port', preflight)

    def test_actual_read_write_lock_contract_and_terminal_fault_witness(self):
        read = self.companion.split('function rncu_read_dns()', 1)[1].split('function rncu_write_dns()', 1)[0]
        write = self.companion.split('function rncu_write_dns()', 1)[1].split('function rncu_boundary(', 1)[0]
        self.assertIn('db()->inTransaction()', read); self.assertNotIn('!rncu_mutex()', read)
        self.assertIn('!rncu_mutex()', write); self.assertIn('consumed_key', write)
        self.assertIn('@rnscu_rows=(SELECT COUNT(*)', self.companion)
        self.assertIn("query('SELECT @rnscu_rows')->fetchColumn()===2", self.companion)
        self.assertIn("finally{db()->exec('DROP TRIGGER rnscu_terminal_fault');}", self.companion)

    def test_no_transport_outside_seed_is_asserted(self):
        self.assertIn("$mode==='seed'?[$gets,$posts]===[5,2]:[$dns,$gets,$posts]===[0,0,0]", self.companion)
        self.assertIn("new ProviderGateway(new TwentyIOperationAdapter(),new RncuWriteTransport($authorize),new ProviderOperationLedger())", self.companion)
        self.assertIn('registered_nameserver_changes_history_render()', self.companion)


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
