"""Portable installed-upgrade guards; no SQL, Apache or provider execution."""
from pathlib import Path
from copy import deepcopy
import argparse
import hashlib
import json
import tempfile
import unittest
import zipfile
from unittest.mock import patch
import provider_document_root_requests_upgrade as subject


def h(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()


def row(**values):
    return {key: h(value) for key, value in values.items()}


def table(**rows):
    return {'count': len(rows), 'rows': {h(key): value for key, value in rows.items()}}


def snapshots():
    before = {'version': '1.25.0', 'tables': {
        'settings': table(counter=row(name='provider_document_root_control_terminal_count', value='2'),
                          other=row(name='unchanged', value='kept')),
        'provider_document_root_changes': table(first=row(proof='first'), second=row(proof='second')),
        'provider_document_root_dispatch_ends': table(first=row(proof='first terminal')),
        'provider_document_root_change_checks': table(first=row(document='first check')),
        'provider_website_folder_requests_events': table(request=row(document='original client intent'), dispatch=row(document='reciprocal approval')),
        'provider_operation_grants': table(first=row(scope_params='exact compact commitment')),
        'provider_operations': table(first=row(fingerprint='first wire'), second=row(fingerprint='second wire')),
        'audit': table(first=row(detail='original'), updater=row(detail='legitimate earlier update')),
    }, 'private_files': {'provider.key': {'sha256': h('original key'), 'bytes': 32}}}
    after = deepcopy(before)
    after['tables']['settings']['rows'][h('counter')]['value'] = h('3')
    for name in ('provider_document_root_dispatch_ends', 'audit'):
        after['tables'][name]['rows'][h('new')] = row(detail='one actual local recovery')
        after['tables'][name]['count'] += 1
    transition = {'terminal_row_id': h('new'), 'audit_row_id': h('new'), 'counter_row_id': h('counter')}
    return before, after, transition


def file_snapshots(action='apply'):
    before, _, _ = snapshots()
    before['tables']['users'] = table(owner=row(id=1, email='drr-upgrade@example.invalid'))
    after = deepcopy(before)
    after['version'] = '1.26.0' if action == 'apply' else '1.25.0'
    state = 'complete' if action == 'apply' else 'rolled-back'
    after['tables']['audit']['rows'][h('file action')] = row(
        id=98, user_id=1, action='structure.' + action,
        detail='Version 1.26.0; state ' + state, created_at='2026-10-19 00:00:00')
    after['tables']['audit']['count'] += 1
    return before, after


class Guards(unittest.TestCase):
    def setUp(self):
        self.before, self.after, self.transition = snapshots()

    def refuses(self):
        with self.assertRaises((AssertionError, KeyError)):
            subject.terminal_recovery_retained(self.before, self.after, self.transition)

    def test_exact_recovery_is_nonmutating(self):
        original = deepcopy((self.before, self.after, self.transition))
        self.assertTrue(subject.terminal_recovery_retained(self.before, self.after, self.transition))
        self.assertEqual((self.before, self.after, self.transition), original)

    def test_every_original_column_is_retained(self):
        for name, contents in self.before['tables'].items():
            for key, columns in contents['rows'].items():
                for column in columns:
                    if (name, key, column) == ('settings', h('counter'), 'value'):
                        continue
                    with self.subTest(table=name, column=column):
                        saved = deepcopy(self.after)
                        self.after['tables'][name]['rows'][key][column] = h('tampered')
                        self.refuses(); self.after = saved

    def test_original_row_loss_is_not_replacement(self):
        del self.after['tables']['provider_document_root_changes']['rows'][h('first')]
        self.after['tables']['provider_document_root_changes']['count'] -= 1
        self.refuses()

    def test_added_original_row_column_refuses(self):
        self.after['tables']['audit']['rows'][h('first')]['extra'] = h('extra')
        self.refuses()

    def test_unrelated_table_addition_refuses(self):
        self.after['tables']['unexpected'] = table()
        self.refuses()

    def test_each_recovery_row_identity_must_match(self):
        for key in self.transition:
            with self.subTest(identity=key):
                saved = self.transition[key]; self.transition[key] = h('wrong')
                self.refuses(); self.transition[key] = saved

    def test_unknown_transition_field_refuses(self):
        self.transition['extra'] = h('extra'); self.refuses()

    def test_missing_transition_field_refuses(self):
        del self.transition['audit_row_id']; self.refuses()

    def test_transition_identity_must_be_a_digest(self):
        self.transition['audit_row_id'] = 'raw row identity'; self.refuses()

    def test_counter_advances_exactly_once(self):
        for value in ('0', '1', '2', '4'):
            with self.subTest(value=value):
                self.after['tables']['settings']['rows'][h('counter')]['value'] = h(value)
                self.refuses()

    def test_counter_original_must_be_two(self):
        self.before['tables']['settings']['rows'][h('counter')]['value'] = h('0'); self.refuses()

    def test_duplicate_counter_identity_refuses(self):
        t = self.before['tables']['settings']; t['rows'][h('duplicate')] = deepcopy(t['rows'][h('counter')]); t['count'] += 1
        self.refuses()

    def test_extra_recovery_row_refuses(self):
        for name in ('audit', 'provider_document_root_dispatch_ends'):
            with self.subTest(table=name):
                saved = deepcopy(self.after)
                t = self.after['tables'][name]; t['rows'][h('extra')] = row(detail='unrelated'); t['count'] += 1
                self.refuses(); self.after = saved

    def test_missing_recovery_row_refuses(self):
        self.after['tables']['audit'] = deepcopy(self.before['tables']['audit']); self.refuses()

    def test_private_key_drift_refuses(self):
        self.after['private_files']['provider.key']['sha256'] = h('changed'); self.refuses()

    def test_new_private_file_refuses(self):
        self.after['private_files']['extra'] = {'sha256': h('extra'), 'bytes': 1}; self.refuses()

    def test_runtime_change_during_local_recovery_refuses(self):
        self.after['version'] = '9.9.9'; self.refuses()

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

    def test_file_action_allows_only_exact_apply_or_recovery_audit(self):
        for action in ('apply', 'recover'):
            before, after = file_snapshots(action)
            original = deepcopy((before, after))
            self.assertTrue(subject.file_action_retained(before, after, action, '1.26.0'))
            self.assertEqual((before, after), original)

    def test_same_version_file_retry_is_supported(self):
        before, after = file_snapshots()
        before['version'] = after['version'] = '1.25.0'
        after['tables']['audit']['rows'][h('file action')]['detail'] = h('Version 1.25.0; state complete')
        self.assertTrue(subject.file_action_retained(before, after, 'apply', '1.25.0'))

    def test_file_action_rejects_wrong_actor_action_or_detail(self):
        for column in ('user_id', 'action', 'detail'):
            with self.subTest(column=column):
                before, after = file_snapshots()
                after['tables']['audit']['rows'][h('file action')][column] = h('wrong')
                with self.assertRaises(AssertionError): subject.file_action_retained(before, after, 'apply', '1.26.0')

    def test_file_action_rejects_missing_extra_or_changed_original_audits(self):
        for change in ('missing', 'extra', 'changed'):
            with self.subTest(change=change):
                before, after = file_snapshots()
                if change == 'missing': after['tables']['audit'] = deepcopy(before['tables']['audit'])
                elif change == 'extra':
                    after['tables']['audit']['rows'][h('extra')] = row(detail='unrelated')
                    after['tables']['audit']['count'] += 1
                else: after['tables']['audit']['rows'][h('first')]['detail'] = h('changed')
                with self.assertRaises(AssertionError): subject.file_action_retained(before, after, 'apply', '1.26.0')

    def test_file_action_rejects_columns_added_to_original_or_new_audit(self):
        for identity in ('first', 'file action'):
            before, after = file_snapshots()
            after['tables']['audit']['rows'][h(identity)]['extra'] = h('extra')
            with self.assertRaises(AssertionError): subject.file_action_retained(before, after, 'apply', '1.26.0')

    def test_file_action_retains_client_journal_and_original_private_bytes(self):
        for change in ('journal', 'private', 'table'):
            before, after = file_snapshots()
            if change == 'journal': after['tables']['provider_website_folder_requests_events']['rows'][h('dispatch')]['document'] = h('changed')
            elif change == 'private': after['private_files']['provider.key']['sha256'] = h('changed')
            else: after['tables']['extra'] = table()
            with self.assertRaises(AssertionError): subject.file_action_retained(before, after, 'apply', '1.26.0')

    def test_file_action_refuses_unknown_owner_and_wrong_restored_version(self):
        for change in ('owner', 'version'):
            before, after = file_snapshots('recover')
            if change == 'owner': before['tables']['users']['rows'][h('owner')]['email'] = h('foreign')
            else: after['version'] = '1.26.0'
            with self.assertRaises(AssertionError): subject.file_action_retained(before, after, 'recover', '1.26.0')


def main():
    parser = argparse.ArgumentParser(); parser.add_argument('--report', type=Path, required=True); args = parser.parse_args()
    names = unittest.defaultTestLoader.getTestCaseNames(Guards); checks = []
    for name in names:
        result = unittest.TestResult(); Guards(name).run(result)
        checks.append({'name': name, 'passed': result.wasSuccessful()})
    report = {'passed': all(c['passed'] for c in checks), 'checks': checks,
              'scope': 'Actual portable retention/path/archive/source/log guard functions with synthetic temporary inputs. No native SQL, installed UPDATE, Apache, browser or provider execution.'}
    args.report.parent.mkdir(parents=True, exist_ok=True); args.report.write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print(json.dumps({'passed': report['passed'], 'checks': len(checks)}))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
