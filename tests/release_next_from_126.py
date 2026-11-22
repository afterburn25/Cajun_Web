"""Pinned published v1.26.1 -> future release extraction diagnostic.

This is a bounded packaged-source check, NOT a database migration, hosted
installation, live payment/provider operation, or release clearance.
"""
from pathlib import Path, PurePosixPath
import hashlib
import json
import os
import re
import stat
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
BASELINE_VERSION = '1.26.1'
BASELINE_URL = (
    'https://github.com/afterburn25/Cajun_Web/releases/download/v1.26.1/'
    'CajunWebPro-1.26.1-COMPLETE-UPLOAD.zip'
)
# Immutable published release asset: official GitHub digest and byte length.
BASELINE_SHA256 = '7d7117a25fab556cb3f8dfce7f0b859d072cfc992b98fc91026d96b490e1dad3'
BASELINE_BYTES = 17429306
MAX_BASELINE_BYTES = 128 * 1024 * 1024
SEMVER = re.compile(r'\A(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\Z')


def candidate_version(root=ROOT):
    version = (root / 'VERSION').read_text(encoding='utf-8').strip()
    if SEMVER.fullmatch(version) is None:
        raise RuntimeError('Release candidate has an invalid semantic version.')
    if tuple(map(int, version.split('.'))) <= (1, 26, 1):
        raise RuntimeError('Release candidate must be newer than published v1.26.1.')
    defaults = (root / 'site/config/defaults.php').read_text(encoding='utf-8')
    detected = re.findall(
        r"^const CWP_VERSION = '([0-9]+\.[0-9]+\.[0-9]+)';\r?$",
        defaults,
        re.M,
    )
    if detected != [version]:
        raise RuntimeError('Source VERSION and the installed PHP version differ.')
    return version


def archive_facts(path):
    """Reject unsafe/repeated paths before extracting any archive member."""
    with zipfile.ZipFile(path) as archive:
        names = archive.namelist()
        if not names or len(names) > 20000:
            raise RuntimeError('Unexpected release archive size.')
        seen = set()
        for entry in archive.infolist():
            name = entry.filename
            trimmed = name.rstrip('/')
            member = PurePosixPath(trimmed)
            if (
                not trimmed or '\\' in name or ':' in name
                or member.is_absolute() or '..' in member.parts
                or member.as_posix() != trimmed
                or any(part in ('', '.', '..') for part in member.parts)
                or any(ord(char) < 32 for char in name)
            ):
                raise RuntimeError('Unsafe release archive member.')
            folded = trimmed.casefold()
            if folded in seen:
                raise RuntimeError('Duplicate or case-colliding release member.')
            seen.add(folded)
            kind = stat.S_IFMT(entry.external_attr >> 16)
            if kind not in (0, stat.S_IFREG, stat.S_IFDIR):
                raise RuntimeError('Nonregular release archive member.')
        bad = archive.testzip()
        if bad:
            raise RuntimeError('Release archive CRC failure: ' + bad)
        return {'members': len(names), 'names': seen}


def baseline_version(path):
    with zipfile.ZipFile(path) as archive:
        defaults = archive.read('config/defaults.php').decode('utf-8')
    found = re.findall(
        r"^const CWP_VERSION = '([0-9]+\.[0-9]+\.[0-9]+)';\r?$",
        defaults,
        re.M,
    )
    if found != [BASELINE_VERSION]:
        raise RuntimeError('Downloaded baseline does not declare v1.26.1.')


def obtain_baseline(target):
    from urllib.request import urlopen
    with urlopen(BASELINE_URL, timeout=90) as response:
        raw = response.read(MAX_BASELINE_BYTES + 1)
    if len(raw) != BASELINE_BYTES or hashlib.sha256(raw).hexdigest() != BASELINE_SHA256:
        raise RuntimeError('Published v1.26.1 archive disagrees with the pinned release asset.')
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(raw)
    return {'url': BASELINE_URL, 'bytes': len(raw), 'sha256': BASELINE_SHA256}


def main():
    if os.environ.get('CWP_TEST_DATABASE') != 'cwp_test':
        raise RuntimeError('Only the disposable cwp_test release diagnostic is permitted.')
    version = candidate_version()
    source = os.environ.get('GITHUB_SHA', '')
    if re.fullmatch(r'[0-9a-f]{40}', source) is None:
        raise RuntimeError('Exact candidate commit SHA is required.')
    actual = subprocess.check_output(
        ['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True,
    ).strip()
    if actual != source:
        raise RuntimeError('Checkout is not the declared candidate commit.')
    if subprocess.check_output(
        ['git', 'status', '--porcelain', '--untracked-files=all'],
        cwd=ROOT, text=True,
    ).strip():
        raise RuntimeError('Release diagnostic requires a clean source checkout.')

    original = ROOT / 'test-runtime/CajunWebPro-1.26.1-COMPLETE-UPLOAD.zip'
    if original.exists():
        raise RuntimeError('Refuse to overwrite an existing published-version fixture.')
    baseline = obtain_baseline(original)
    original_members = archive_facts(original)
    baseline_version(original)

    subprocess.run(
        [sys.executable, 'tools/build.py', '--commit', source],
        cwd=ROOT, check=True,
    )
    update = ROOT / 'dist' / ('CajunWebPro-' + version + '-UPDATE.zip')
    complete = ROOT / 'dist' / ('CajunWebPro-' + version + '-COMPLETE-UPLOAD.zip')
    if not update.is_file() or not complete.is_file():
        raise RuntimeError('Complete and UPDATE candidate archives are required.')
    update_members = archive_facts(update)
    complete_members = archive_facts(complete)

    # A staging-only dry extraction never applies migration code or modifies live files.
    stage = 'cwp-update-' + version
    if stage + '/owner-update.php' not in update_members['names']:
        raise RuntimeError('UPDATE archive lacks its versioned owner update entry point.')
    destination = ROOT / 'test-runtime/released-1.26.1'
    if destination.exists():
        raise RuntimeError('Use a fresh destination for release extraction diagnostics.')
    destination.mkdir(parents=True)
    with zipfile.ZipFile(original) as archive:
        archive.extractall(destination)
    with zipfile.ZipFile(update) as archive:
        archive.extractall(destination)
    if not (destination / stage / 'owner-update.php').is_file():
        raise RuntimeError('Validated UPDATE staging entry did not extract.')

    report = {
        'schema': 1, 'source': source,
        'baseline_version': BASELINE_VERSION,
        'candidate_version': version,
        'baseline': {
            **baseline, 'members': original_members['members'],
        },
        'candidate_update': {
            'members': update_members['members'],
            'bytes': update.stat().st_size,
            'sha256': hashlib.sha256(update.read_bytes()).hexdigest(),
        },
        'candidate_complete': {
            'members': complete_members['members'],
            'bytes': complete.stat().st_size,
            'sha256': hashlib.sha256(complete.read_bytes()).hexdigest(),
        },
        'scope': (
            'Pinned published 1.26.1 to exact candidate package extraction '
            'only; actual updater, populated database, HTTP/browser and '
            'target-host acceptance are separate gates.'
        ),
        'release_clearance': False,
    }
    qa = ROOT / 'qa'
    qa.mkdir(exist_ok=True)
    (qa / 'release-next-from-126-results.json').write_text(
        json.dumps(report, indent=2) + '\n', encoding='utf-8',
    )
    print('PASS: Published v1.26.1 bound to candidate ' + version + ' extraction diagnostic.')


if __name__ == '__main__':
    main()
