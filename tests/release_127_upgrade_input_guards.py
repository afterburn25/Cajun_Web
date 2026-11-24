"""Refusal tests for the immutable published-v1.26.1 release input."""
from pathlib import Path
import argparse
import ast
from copy import deepcopy
import hashlib
import importlib.util
import io
import json
import os
import stat
import subprocess
import sys
import tempfile
import zipfile


def load_module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def main(baseline, output):
    root = Path(__file__).resolve().parents[1]
    fetch = load_module('release127_fetch', root / 'tools/fetch_release_127_baseline.py')
    archive_guard = load_module('release127_archive', root / 'tests/release_127_archive.py')
    checks = []

    def check(name, condition):
        checks.append({'name': name, 'passed': bool(condition)})
        assert condition, name

    def deny(name, callback):
        try:
            callback()
        except (RuntimeError, ValueError, OSError, zipfile.BadZipFile, KeyError):
            check(name, True)
        else:
            check(name, False)

    baseline = Path(baseline)
    raw = baseline.read_bytes()
    identity = {'bytes': len(raw), 'sha256': hashlib.sha256(raw).hexdigest()}
    check('Published v1.26.1 bytes equal the pinned release asset',
          identity == {'bytes': fetch.ARCHIVE_BYTES, 'sha256': fetch.ARCHIVE_SHA256})

    with zipfile.ZipFile(io.BytesIO(raw)) as package:
        names = package.namelist()
        files = {name: hashlib.sha256(package.read(name)).hexdigest() for name in names}
        check('Published baseline has the exact unique CRC-clean member count',
              len(names) == len(set(names)) == len({name.casefold() for name in names})
              == fetch.MEMBERS and package.testzip() is None)
    manifest = {
        'version': '1.26.1',
        'source_commit': fetch.SOURCE,
        'artifact': fetch.ARCHIVE_NAME,
        'sha256': fetch.ARCHIVE_SHA256,
        'directories': sorted(fetch.expected_directories(files)),
        'files': files,
    }
    fetch.validate_manifest_document(manifest)
    fetch.validate_archive_bytes(raw, files)
    check('Strict manifest and every published member digest agree', True)
    history = json.loads((root / 'tools/legacy-release-files.json').read_text(encoding='utf-8'))
    history_entry = history.get('1.26.1')
    check('Updater history binds the exact published 1.26.1 source asset manifest and all members',
          list(history)[-2:] == ['1.26.0', '1.26.1']
          and history_entry == {
              'archive_sha256': fetch.ARCHIVE_SHA256,
              'source_kind': 'published-release',
              'source_commit': fetch.SOURCE,
              'release_asset_id': fetch.ARCHIVE_ASSET_ID,
              'manifest_asset_id': fetch.MANIFEST_ASSET_ID,
              'manifest_sha256': fetch.MANIFEST_SHA256,
              'files': files,
          }
          and history_entry['files']['config/defaults.php'] == files['config/defaults.php'])

    check('Manifest asset identity is immutable',
          (fetch.MANIFEST_ASSET_ID, fetch.MANIFEST_BYTES, fetch.MANIFEST_SHA256)
          == (711656643, 482435,
              'a903b2850ab4ff25ed94ceddd1794370341865183ee515a72bd816cd5324d0ee'))
    check('Archive asset source and identity are immutable',
          (fetch.ARCHIVE_ASSET_ID, fetch.SOURCE, fetch.MEMBERS)
          == (711656635, 'f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391', 3072))
    check('Public release URLs retain exact immutable asset names without REST quota input',
          fetch.ARCHIVE_URL == 'https://github.com/afterburn25/Cajun_Web/releases/download/v1.26.1/' + fetch.ARCHIVE_NAME
          and fetch.MANIFEST_URL == 'https://github.com/afterburn25/Cajun_Web/releases/download/v1.26.1/' + fetch.MANIFEST_NAME
          and 'api.github.com' not in fetch.ALLOWED_HOSTS)

    acceptance_source = (root / 'tests/release_127_upgrade_acceptance.py').read_text(
        encoding='utf-8')
    acceptance_tree = ast.parse(acceptance_source)
    acceptance_functions = {
        node.name: ast.get_source_segment(acceptance_source, node)
        for node in acceptance_tree.body if isinstance(node, ast.FunctionDef)
    }
    repeated_source = acceptance_functions.get('repeated_form_fields', '')
    submit_source = acceptance_functions.get('submit', '')
    self_check_source = acceptance_functions.get('repeated_form_fields_self_check', '')
    diagnostic_source = acceptance_functions.get('database_apply_diagnostic', '')
    check('Reviewed PHP array parser keeps exact enabled ordered fields and refuses ambiguity',
          all(fragment in repeated_source for fragment in (
              'Expected one exact repeated-field form',
              'Repeated form field contains an empty value',
              'Repeated form field contains a duplicate value',
              'not control.has_attr(\'checked\')')))
    check('Reviewed migration submission overlays exact arrays before explicit confirmed values',
          submit_source.find('data.update(repeated_form_fields(response, action))')
          < submit_source.find('data.update(values or {})')
          < submit_source.find('session.post(target, data=data, timeout=120)')
          and min(submit_source.find(fragment) for fragment in (
              'data.update(repeated_form_fields(response, action))',
              'data.update(values or {})',
              'session.post(target, data=data, timeout=120)')) >= 0)
    check('Installed native acceptance directly exercises ordered scalar and duplicate refusal',
          all(fragment in self_check_source for fragment in (
              "['database_updates[]'] == ['first', 'second']",
              "submission['data']['csrf'] == 'csrf-token'",
              "submission['data']['review_hash'] == 'review-hash'",
              "submission['data']['confirm'] == '1'",
              "except ValueError:",
              "check('Duplicate reviewed PHP array fields are refused before submission', refused)")))
    check('Failed migration application retains only bounded redacted loopback diagnostics',
          all(fragment in diagnostic_source for fragment in (
              "parsed.hostname != '127.0.0.1'",
              "'.notice.error[role=\"alert\"]'",
              "'[redacted-reference]'",
              "'path': parsed.path",
              "'error_notices': notices[:3]",
              "message[:500]"))
          and "parsed.query" not in diagnostic_source
          and "'html':" not in diagnostic_source
          and "'url': response.url" not in diagnostic_source)
    check('Installed acceptance directly proves diagnostic query and secret exclusion',
          all(fragment in self_check_source for fragment in (
              "'path': '/admin/index.php'",
              "'error_notices': ['Database state changed. Reference: [redacted-reference]']",
              "'completed': False",
              "Migration failure diagnostic excludes query form and reference secrets")))

    deny('Unknown published asset hosts are refused before a request',
         lambda: fetch.fetch('https://untrusted.invalid/release.zip', fetch.ARCHIVE_BYTES))
    deny('Insecure asset URLs are refused before a request',
         lambda: fetch.fetch('http://api.github.com/release.zip', fetch.ARCHIVE_BYTES))
    deny('REST asset API URLs are refused before a request',
         lambda: fetch.fetch('https://api.github.com/repos/afterburn25/Cajun_Web/releases/assets/711656635',
                             fetch.ARCHIVE_BYTES))
    deny('Unknown redirect targets are refused before the redirected request',
         lambda: fetch.TrustedRedirectHandler().redirect_request(
             None, None, 302, 'Found', {}, 'https://untrusted.invalid/release.zip'))
    proxies = [handler.proxies for handler in fetch.asset_opener().handlers
               if isinstance(handler, fetch.urllib.request.ProxyHandler)]
    check('Published asset transfer ignores ambient proxy configuration', proxies == [])

    deny('Duplicate JSON keys are refused',
         lambda: fetch.load_unique_json(b'{"files":{},"files":{}}'))
    changed = deepcopy(manifest)
    changed['unexpected'] = True
    deny('Unknown manifest fields are refused',
         lambda: fetch.validate_manifest_document(changed))
    changed = deepcopy(manifest)
    changed['source_commit'] = '0' * 40
    deny('Substituted release source is refused',
         lambda: fetch.validate_manifest_document(changed))
    changed = deepcopy(manifest)
    changed['sha256'] = '0' * 64
    deny('Substituted archive identity is refused',
         lambda: fetch.validate_manifest_document(changed))
    changed = deepcopy(manifest)
    first = next(iter(changed['files']))
    changed['files']['../escape.php'] = changed['files'].pop(first)
    deny('Manifest traversal replacement is refused',
         lambda: fetch.validate_manifest_document(changed))
    changed = deepcopy(manifest)
    keys = list(changed['files'])
    changed['files'][keys[0].upper()] = changed['files'].pop(keys[1])
    deny('Manifest case collision replacement is refused',
         lambda: fetch.validate_manifest_document(changed))

    snapshot = archive_guard.published_snapshot(
        baseline, fetch.ARCHIVE_BYTES, fetch.ARCHIVE_SHA256)
    check('One verified snapshot is returned for extraction', snapshot == raw)
    with tempfile.TemporaryDirectory(prefix='cwp-release127-guards-') as temporary:
        temporary = Path(temporary)
        destination = temporary / 'extracted'
        destination.mkdir()
        archive_guard.extract_snapshot(snapshot, destination)
        check('The same verified bytes extract to the exact member set',
              len([path for path in destination.rglob('*') if path.is_file()]) == fetch.MEMBERS)
        deny('Existing extraction targets are never replaced',
             lambda: archive_guard.extract_snapshot(snapshot, destination))
        for name in ('../escape.php', '/absolute.php', 'C:/drive.php', 'nested\\file.php'):
            bad = temporary / 'bad.zip'
            with zipfile.ZipFile(bad, 'w') as package:
                entry = zipfile.ZipInfo()
                entry.filename = name
                package.writestr(entry, b'x')
            deny('Unsafe archive name refused: ' + repr(name),
                 lambda path=bad: archive_guard.inspect_snapshot(path.read_bytes()))
        symbolic = io.BytesIO()
        with zipfile.ZipFile(symbolic, 'w') as package:
            entry = zipfile.ZipInfo('symbolic')
            entry.create_system = 3
            entry.external_attr = (stat.S_IFLNK | 0o777) << 16
            package.writestr(entry, b'target')
        deny('Symbolic-link archive member is refused',
             lambda: archive_guard.inspect_snapshot(symbolic.getvalue()))

        candidate = temporary / 'CajunWebPro-1.27.0-UPDATE.zip'
        with zipfile.ZipFile(candidate, 'w') as package:
            package.writestr('cwp-update-1.27.0/index.php', b'<?php // synthetic candidate\n')
        candidate_bytes = candidate.read_bytes()
        candidate_file = 'cwp-update-1.27.0/index.php'
        candidate_manifest = {
            'version': '1.27.0', 'source_commit': 'a' * 40,
            'artifact': candidate.name,
            'sha256': hashlib.sha256(candidate_bytes).hexdigest(),
            'directories': ['cwp-update-1.27.0/'],
            'files': {candidate_file: hashlib.sha256(b'<?php // synthetic candidate\n').hexdigest()},
        }
        candidate.with_suffix('.manifest.json').write_text(
            json.dumps(candidate_manifest, separators=(',', ':')) + '\n', encoding='utf-8')
        candidate_snapshot, candidate_identity = archive_guard.candidate_snapshot(
            candidate, 'a' * 40, '1.27.0')
        check('Candidate snapshot binds exact archive manifest source and version',
              candidate_snapshot == candidate_bytes
              and candidate_identity['source_commit'] == 'a' * 40
              and candidate_identity['version'] == '1.27.0')
        deny('Candidate snapshot refuses a different expected source',
             lambda: archive_guard.candidate_snapshot(candidate, 'b' * 40, '1.27.0'))
        deny('Candidate snapshot refuses a different expected version',
             lambda: archive_guard.candidate_snapshot(candidate, 'a' * 40, '1.27.1'))

        if os.name == 'posix':
            escape = temporary / 'escape'
            escape.mkdir()
            symlink_root = temporary / 'symlink-root'
            symlink_root.mkdir()
            (symlink_root / 'link').symlink_to(escape, target_is_directory=True)
            link_archive = io.BytesIO()
            with zipfile.ZipFile(link_archive, 'w') as package:
                package.writestr('link/new/file.txt', b'escape attempt')
            deny('Pre-existing extraction-parent symlink is refused before creating outside paths',
                 lambda: archive_guard.extract_snapshot(link_archive.getvalue(), symlink_root))
            check('Refused extraction-parent symlink creates nothing outside the destination',
                  not (escape / 'new').exists())

            private_parent = temporary / 'private-parent'
            private_parent.mkdir(mode=0o700)
            public = temporary / 'web'
            installed = public / 'studio'
            installed.mkdir(parents=True)
            storage = private_parent / 'cajunwebpro-private-test'
            storage.mkdir(mode=0o700)
            check('Synthetic installer storage resolves only beneath its exact ordinary private parent',
                  archive_guard.fixture_storage_path(str(storage), private_parent, public) == storage)
            check('Container storage maps one exact virtual root to the same bounded host store',
                  archive_guard.fixture_storage_path(
                      '/workspace/private-parent/cajunwebpro-private-test', private_parent, public,
                      virtual_root='/workspace', host_root=temporary) == storage)
            deny('Installer storage traversal is refused before evidence reads',
                 lambda: archive_guard.fixture_storage_path(
                     str(private_parent / '..' / 'escape'), private_parent, public))
            linked_storage = private_parent / 'linked-storage'
            linked_storage.symlink_to(escape, target_is_directory=True)
            deny('Installer storage with a symlink component is refused before evidence reads',
                 lambda: archive_guard.fixture_storage_path(
                     str(linked_storage), private_parent, public))

            structure = storage / 'structure-1.27.0'
            structure.mkdir(mode=0o700)
            normal_policy = b'candidate server policy\n'
            journal_data = {
                'root': str(installed.resolve()), 'version': '1.26.1', 'target': '1.27.0',
                'commit': 'a' * 40, 'state': 'applying', 'last_completed': 'app/example.php',
                'operations': {
                    'app/example.php': {'old': None, 'new': 'b' * 64, 'source': 'app/example.php'},
                    '.htaccess': {'old': 'c' * 64,
                                  'new': hashlib.sha256(normal_policy).hexdigest(),
                                  'source': '.htaccess'},
                },
            }
            journal = structure / 'journal.php'
            journal.write_bytes(archive_guard.PRIVATE_GUARD
                                + json.dumps(journal_data, separators=(',', ':')).encode())
            journal.chmod(0o600)
            parsed = archive_guard.interrupted_journal(
                storage, installed, '1.27.0', 'a' * 40, normal_policy,
                starting_version='1.26.1')
            check('Interrupted journal binds exact root source target state operation policy and modes',
                  parsed == journal_data)
            journal_data['root'] = '/workspace/web/studio'
            journal.write_bytes(archive_guard.PRIVATE_GUARD
                                + json.dumps(journal_data, separators=(',', ':')).encode())
            journal.chmod(0o600)
            parsed = archive_guard.interrupted_journal(
                storage, installed, '1.27.0', 'a' * 40, normal_policy,
                starting_version='1.26.1',
                expected_root='/workspace/web/studio',
                virtual_root='/workspace', host_root=temporary)
            check('Container journal binds its one explicit canonical virtual installation root',
                  parsed == journal_data)
            deny('Container journal root that maps to another host installation is refused',
                 lambda: archive_guard.interrupted_journal(
                     storage, installed, '1.27.0', 'a' * 40, normal_policy,
                     starting_version='1.26.1',
                     expected_root='/workspace/escape',
                     virtual_root='/workspace', host_root=temporary))
            journal_data['root'] = str(escape.resolve())
            journal.write_bytes(archive_guard.PRIVATE_GUARD
                                + json.dumps(journal_data, separators=(',', ':')).encode())
            journal.chmod(0o600)
            deny('Guarded journal for another installation is refused',
                 lambda: archive_guard.interrupted_journal(
                     storage, installed, '1.27.0', 'a' * 40, normal_policy,
                     starting_version='1.26.1'))
            journal_data['root'] = str(installed.resolve())
            journal.write_bytes(archive_guard.PRIVATE_GUARD
                                + json.dumps(journal_data, separators=(',', ':')).encode())
            journal.chmod(0o644)
            deny('Interrupted journal without private mode 0600 is refused',
                 lambda: archive_guard.interrupted_journal(
                     storage, installed, '1.27.0', 'a' * 40, normal_policy,
                     starting_version='1.26.1'))
            policy_target = temporary / 'outside-policy'
            policy_target.write_bytes(normal_policy)
            (installed / '.htaccess').symlink_to(policy_target)
            deny('Policy evidence reached through a symlink is refused',
                 lambda: archive_guard.ordinary_file_bytes(installed / '.htaccess', installed))

        history_copy = temporary / 'legacy-release-files.json'
        predecessor_history = deepcopy(history)
        expected_history_entry = predecessor_history.pop('1.26.1')
        history_copy.write_text(json.dumps(predecessor_history, indent=2) + '\n', encoding='utf-8')
        command = [sys.executable, str(root / 'tools/record_release_1261_history.py'),
                   str(baseline), str(baseline.with_suffix('.manifest.json')),
                   '--history', str(history_copy)]
        first = subprocess.run(command, cwd=root, capture_output=True, text=True, timeout=30)
        recorded = history_copy.read_bytes()
        appended_history = json.loads(recorded)
        second = subprocess.run(command, cwd=root, capture_output=True, text=True, timeout=30)
        check('Published history recorder appends only the exact entry and is idempotent',
              first.returncode == second.returncode == 0
              and list(appended_history)[-2:] == ['1.26.0', '1.26.1']
              and appended_history['1.26.1'] == expected_history_entry
              and {key: value for key, value in appended_history.items() if key != '1.26.1'} == predecessor_history
              and history_copy.read_bytes() == recorded
              and recorded == (root / 'tools/legacy-release-files.json').read_bytes())
        changed_history = json.loads(history_copy.read_text(encoding='utf-8'))
        changed_history['1.26.1']['archive_sha256'] = '0' * 64
        history_copy.write_text(json.dumps(changed_history, indent=2) + '\n', encoding='utf-8')
        changed_bytes = history_copy.read_bytes()
        refused = subprocess.run(command, cwd=root, capture_output=True, text=True, timeout=30)
        check('Published history recorder refuses a conflicting existing identity without mutation',
              refused.returncode != 0 and history_copy.read_bytes() == changed_bytes)

    Path(output).parent.mkdir(parents=True, exist_ok=True)
    Path(output).write_text(json.dumps({
        'passed': True,
        'scope': 'Pinned public v1.26.1 bytes, strict manifest, trusted transfer and immutable extraction snapshot; no database, HTTP, payment, provider, deployment or publication',
        'baseline': identity,
        'checks': checks,
    }, indent=2) + '\n', encoding='utf-8')
    print('PASS', len(checks), 'published-1.26.1 input guards')


if __name__ == '__main__':
    root = Path(__file__).resolve().parents[1]
    parser = argparse.ArgumentParser()
    parser.add_argument('--baseline', type=Path,
                        default=root / 'test-runtime/CajunWebPro-1.26.1-COMPLETE-UPLOAD.zip')
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    main(args.baseline, args.report)
