"""Portable guards only; no SQL, Apache, provider or installed upgrade execution."""
from pathlib import Path
import argparse
import hashlib
import json
import tempfile
import unittest
from unittest.mock import patch
import zipfile
import registered_nameservers_upgrade as subject


class Guards(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()

    def archive(self, entries=None):
        path = self.root / 'fixture.zip'
        with zipfile.ZipFile(path, 'w') as out:
            for name, data in entries or [('a.php', b'original')]:
                out.writestr(name, data)
        return path

    def test_exact_archive_map(self):
        archive = self.archive()
        self.assertEqual(subject.archive_map(archive, subject.digest(archive)), {'a.php': hashlib.sha256(b'original').hexdigest()})

    def test_changed_archive_digest_refuses(self):
        with self.assertRaises(AssertionError):
            subject.archive_map(self.archive(), '0' * 64)

    def test_parent_member_refuses(self):
        with self.assertRaises(AssertionError):
            subject.archive_map(self.archive([('../outside', b'x')]))

    def test_absolute_member_refuses(self):
        with self.assertRaises(AssertionError):
            subject.archive_map(self.archive([('/outside', b'x')]))

    def test_windows_member_refuses(self):
        with self.assertRaises(AssertionError):
            subject.archive_map(self.archive([('C:/outside', b'x')]))

    def test_backslash_member_refuses(self):
        archive = self.archive([('a/b', b'x')])
        # ZipInfo normalizes Windows separators when authoring. Mutate the raw
        # local/central filename bytes to exercise an actual hostile archive.
        archive.write_bytes(archive.read_bytes().replace(b'a/b', b'a\\b'))
        with self.assertRaises(AssertionError):
            subject.archive_map(archive)

    def test_case_collision_refuses(self):
        with self.assertRaises(AssertionError):
            subject.archive_map(self.archive([('a.php', b'a'), ('A.php', b'b')]))

    def test_directory_member_refuses(self):
        with self.assertRaises(AssertionError):
            subject.archive_map(self.archive([('a/', b'')]))

    def test_symlink_member_refuses(self):
        entry = zipfile.ZipInfo('link'); entry.create_system = 3; entry.external_attr = 0o120777 << 16
        with self.assertRaises(AssertionError):
            subject.archive_map(self.archive([(entry, b'outside')]))

    def test_crc_damage_refuses(self):
        archive = self.archive(); archive.write_bytes(archive.read_bytes().replace(b'original', b'changed!'))
        with self.assertRaises(AssertionError):
            subject.archive_map(archive)

    def test_empty_archive_refuses(self):
        archive = self.root / 'empty.zip'
        with zipfile.ZipFile(archive, 'w'):
            pass
        with self.assertRaises(AssertionError):
            subject.archive_map(archive)

    def test_existing_output_refuses(self):
        path = self.root / 'report.json'; path.write_text('preserved')
        with self.assertRaises(AssertionError):
            subject.fresh_path(path, self.root)
        self.assertEqual(path.read_text(), 'preserved')

    def test_outside_path_refuses(self):
        with self.assertRaises(AssertionError):
            subject.fresh_path(self.root.parent / 'outside', self.root)

    def test_fresh_child_path_permitted(self):
        subject.fresh_path(self.root / 'new' / 'report.json', self.root)
        self.assertFalse((self.root / 'new').exists())

    def test_link_parent_refuses_modeled_filesystem(self):
        original = Path.is_symlink
        with patch.object(Path, 'is_symlink', lambda path: path == self.root / 'parent' or original(path)):
            with self.assertRaises(AssertionError):
                subject.fresh_path(self.root / 'parent' / 'report', self.root)

    def test_equal_version_changed_bytes_refuses(self):
        path = self.root / 'config'; path.mkdir(); (path / 'defaults.php').write_bytes(b'current build')
        old = {'config/defaults.php': hashlib.sha256(b'published build').hexdigest()}
        self.assertFalse(subject.code_matches(self.root, old))
        (path / 'defaults.php').write_bytes(b'published build')
        self.assertTrue(subject.code_matches(self.root, old))

    def test_target_prefix_and_missing_member_refuse(self):
        desired = {'cwp-update-1.25.0/site/a.php': hashlib.sha256(b'current').hexdigest()}
        self.assertFalse(subject.code_matches(self.root, desired, True, '1.25.0'))
        (self.root / 'a.php').write_bytes(b'current')
        self.assertTrue(subject.code_matches(self.root, desired, True, '1.25.0'))
        self.assertFalse(subject.code_matches(self.root, desired, True, '9.9.9'))

    def test_baseline_inventory_requires_exact_explicit_entry(self):
        archive = self.archive(); (self.root / 'test-runtime').mkdir(); (self.root / 'tools').mkdir()
        target = self.root / 'test-runtime' / subject.BASELINE_NAME; target.write_bytes(archive.read_bytes())
        history = self.root / 'tools/legacy-release-files.json'; history.write_text('{}')
        with patch.multiple(subject, BASELINE_SHA=subject.digest(target), BASELINE_MEMBERS=1):
            with self.assertRaises(AssertionError):
                subject.baseline_inputs(self.root)
            files = subject.archive_map(target)
            history.write_text(json.dumps({'1.25.0': {'files': files}}))
            self.assertEqual(subject.baseline_inputs(self.root)[1], files)
            history.write_text(json.dumps({'1.25.0': {'files': {**files, 'extra': '0' * 64}}}))
            with self.assertRaises(AssertionError):
                subject.baseline_inputs(self.root)

    def test_installed_preflight_keeps_mapped_client_port_not_server_port(self):
        # GitHub exposes an internal 3306 server through a different selected
        # loopback port. Check the real companion's closed connection predicate;
        # this source guard does not simulate a database connection.
        source = Path(__file__).with_name('registered_nameservers_upgrade_fixture.php').read_text(encoding='utf-8')
        preflight = source.split("if($mode==='preflight'){", 1)[1].split("if(!is_file($root", 1)[0]
        self.assertIn("'mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4'", preflight)
        self.assertIn("$s->execute(['rnsu_'])", preflight)
        self.assertIn('(int)$s->fetchColumn()===0', preflight)
        self.assertNotIn('@@port', preflight)

    def test_resolver_outside_lock_transport_inside_lock_source_contract(self):
        source = Path(__file__).with_name('registered_nameservers_upgrade_fixture.php').read_text(encoding='utf-8')
        resolver = source.split('function rnu_resolve():string{', 1)[1].split('\n', 1)[0]
        self.assertIn('db()->inTransaction()', resolver)
        self.assertNotIn('registered_nameservers_guard', resolver)
        transport = source.split('public function send(', 1)[1].split('\n}', 1)[0]
        self.assertIn('registered_nameservers_guard(false)', transport)
        self.assertIn('($this->authorize)()', transport)


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
            json.dump({'passed': result.wasSuccessful(), 'scope': 'Portable archive/path/byte-identity guards only', 'checks': result.checks}, output, indent=2)
    raise SystemExit(0 if result.wasSuccessful() else 1)
