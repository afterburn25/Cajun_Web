"""Refusal tests for the immutable published-v1.26.1 release input."""
from pathlib import Path
import argparse
from copy import deepcopy
import hashlib
import importlib.util
import io
import json
import stat
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

    check('Manifest asset identity is immutable',
          (fetch.MANIFEST_ASSET_ID, fetch.MANIFEST_BYTES, fetch.MANIFEST_SHA256)
          == (711656643, 482435,
              'a903b2850ab4ff25ed94ceddd1794370341865183ee515a72bd816cd5324d0ee'))
    check('Archive asset source and identity are immutable',
          (fetch.ARCHIVE_ASSET_ID, fetch.SOURCE, fetch.MEMBERS)
          == (711656635, 'f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391', 3072))

    deny('Unknown published asset hosts are refused before a request',
         lambda: fetch.fetch('https://untrusted.invalid/release.zip', fetch.ARCHIVE_BYTES))
    deny('Insecure asset URLs are refused before a request',
         lambda: fetch.fetch('http://api.github.com/release.zip', fetch.ARCHIVE_BYTES))
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
