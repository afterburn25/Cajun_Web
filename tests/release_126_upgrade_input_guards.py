"""Read-only verification of the actual pinned published ZIP through the transfer helper.

No constant override and no synthetic application baseline. Negative cases alter only
temporary copies or declared public manifest entries, never the original asset.
"""
from pathlib import Path
import argparse
from copy import deepcopy
import hashlib
import importlib.util
import json
import shutil
import tempfile
import zipfile


def main(helper, baseline, output):
    spec = importlib.util.spec_from_file_location('release126_transfer', helper)
    a = importlib.util.module_from_spec(spec); spec.loader.exec_module(a)
    checks = []

    def check(name, condition):
        checks.append({'name': name, 'passed': bool(condition)})
        assert condition, name

    def deny(name, callback):
        try: callback()
        except (ValueError, OSError, zipfile.BadZipFile): check(name, True)
        else: check(name, False)

    baseline = Path(baseline)
    original = {'bytes': baseline.stat().st_size, 'sha256': a.digest(baseline)}
    check('Actual published1.25 digest remains immutable', original['sha256'] == 'bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39')
    check('Production baseline constants are never overridden', a.PUBLISHED_BASELINE_126_SHA256 == original['sha256'] and a.PUBLISHED_BASELINE_126_MEMBERS == 2895)
    with zipfile.ZipFile(baseline) as archive:
        members = {n: {'size': archive.getinfo(n).file_size, 'sha256': hashlib.sha256(archive.read(n)).hexdigest()} for n in archive.namelist()}
        check('Actual original asset has2895 unique CRC-clean members', len(members) == len(archive.namelist()) == 2895 and archive.testzip() is None)
    files = {'test-runtime/released-1.25.0/' + name: facts for name, facts in members.items()}
    with tempfile.TemporaryDirectory(prefix='cwp-published-baseline-') as temporary:
        root = Path(temporary); (root / 'test-runtime').mkdir()
        target = root / 'test-runtime' / a.PUBLISHED_BASELINE_126; shutil.copyfile(baseline, target)
        a.validate_published_baseline_126(root, files)
        check('Actual helper verifies every pinned archive member against retained original files', True)
        check('Only exact public archive path is an input', a.input_allowed('test-runtime/' + a.PUBLISHED_BASELINE_126))
        for path in ['test-runtime/Other-COMPLETE-UPLOAD.zip', 'test-runtime/../' + a.PUBLISHED_BASELINE_126,
                     'test-runtime/' + a.PUBLISHED_BASELINE_126 + '/nested.php', 'test-runtime/private/' + a.PUBLISHED_BASELINE_126,
                     'qa/' + a.PUBLISHED_BASELINE_126, 'test-runtime/CajunWebPro-1.26.0-COMPLETE-UPLOAD.zip']:
            def rejected_path():
                if a.input_allowed(path): raise AssertionError('Admitted unintended raw ZIP path')
            try: rejected_path()
            except ValueError: pass
            check('Raw published archive allowance excludes ' + path, True)
        first = next(iter(files))
        missing = deepcopy(files); del missing[first]
        deny('Missing original declared member fails', lambda: a.validate_published_baseline_126(root, missing))
        extra = deepcopy(files); extra['test-runtime/released-1.25.0/not-original.txt'] = {'size': 1, 'sha256': '0' * 64}
        deny('Extra declared baseline member fails', lambda: a.validate_published_baseline_126(root, extra))
        changed = deepcopy(files); changed[first]['sha256'] = '0' * 64
        deny('Same-size count with changed retained member hash fails', lambda: a.validate_published_baseline_126(root, changed))
        changed = deepcopy(files); changed[first]['size'] += 1
        deny('Changed declared original member size fails', lambda: a.validate_published_baseline_126(root, changed))
        unsafe = deepcopy(files); unsafe['test-runtime/released-1.25.0/../escape.php'] = unsafe.pop(first)
        deny('Canonical original member names cannot be replaced by traversal', lambda: a.validate_published_baseline_126(root, unsafe))
        original_byte = target.read_bytes()[:1]
        with target.open('r+b') as stream: stream.write(bytes([original_byte[0] ^ 1]))
        deny('Any mutation of actual raw archive fails its pinned digest', lambda: a.validate_published_baseline_126(root, files))
        target.unlink()
        deny('Missing raw original asset cannot fall back to synthesized archive', lambda: a.validate_published_baseline_126(root, files))
    check('Original published asset remains byte exact after negative tests', baseline.stat().st_size == original['bytes'] and a.digest(baseline) == original['sha256'])
    Path(output).write_text(json.dumps({'passed': True, 'scope': 'Actual public1.25 archive and transfer helper; no DB/HTTP/upgrade execution',
        'baseline': original, 'helper_sha256': a.digest(Path(helper)), 'checks': checks}, indent=2) + '\n', encoding='utf-8')
    print('PASS', len(checks), 'actual published-baseline transfer guards')


if __name__ == '__main__':
    root = Path(__file__).resolve().parents[1]
    parser = argparse.ArgumentParser()
    parser.add_argument('--helper', type=Path, default=root / 'tools/acceptance_artifacts.py')
    parser.add_argument('--baseline', type=Path, default=root / 'test-runtime/CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip')
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args(); main(args.helper, args.baseline, args.report)
