"""Closed CI input/evidence transfer. No database, provider, credential or network access.

Artifacts are transport, not authority: expected digests/IDs come from this run's
job outputs; every envelope independently binds the exact checkout/run/attempt.
Only public pristine fixtures cross into test jobs. QA evidence is never reused
as application input. Missing partitions always produce incomplete acceptance.
"""
from __future__ import annotations
import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
LANES = ('build', 'native-browser', 'recovery', 'apache')
STEP_KEYS = {'build': {'setup', 'helpers', 'build', 'fixtures', 'inputs', 'mutation'},
             'native-browser': {'setup', 'input', 'native', 'browser', 'mutation'},
             'recovery': {'setup', 'input', 'test', 'mutation'}, 'apache': {'setup', 'input', 'test', 'mutation'}}
BUILD_QA = {'build-results.txt', 'build-guards-results.json', 'parallel-helper-tests.json', 'legacy-preparation.txt'}
VERSIONS = ('1.1.0', '1.2.0', '1.3.0', '1.4.0', '1.5.0', '1.6.0', '1.7.0', '1.8.0', '1.9.0', '1.10.0', '1.11.0', '1.11.1', '1.11.2', '1.12.0', '1.13.0', '1.14.0', '1.15.0', '1.16.0', '1.17.0', '1.18.0', '1.19.0', '1.20.0', '1.21.0', '1.22.0', '1.23.0', '1.24.0')
FIXTURES = {'legacy', *('released-' + v for v in VERSIONS)}
FIXTURE_JSON = {'legacy-schema.json', 'organized-shims.json'}
MAX_FILES = 500_000
MAX_BYTES = 8 * 1024**3  # Local transfer safeguards, not a provider/application limit.


def require(condition, reason):
    if not condition:
        raise ValueError(reason)


def canonical(value):
    return (json.dumps(value, sort_keys=True, separators=(',', ':'), ensure_ascii=True) + '\n').encode()


def digest(path):
    h = hashlib.sha256()
    with Path(path).open('rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()


def load(path):
    def unique(pairs):
        out = {}
        for key, value in pairs:
            require(key not in out, 'Duplicate JSON key')
            out[key] = value
        return out
    return json.loads(Path(path).read_bytes(), object_pairs_hook=unique)


def write(path, value):
    path = Path(path); path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(canonical(value))


def safe_name(name):
    require(isinstance(name, str) and name and len(name) < 1024, 'Invalid member name')
    p = PurePosixPath(name)
    require(not p.is_absolute() and str(p) == name and all(x not in ('', '.', '..') for x in name.split('/')), 'Noncanonical member path')
    require(not any(x in name for x in ('\\', ':', '\x00')) and all(ord(x) >= 32 for x in name), 'Unsafe member path')
    return p


def file_facts(path):
    require(path.is_file() and not path.is_symlink(), 'Only regular files may transfer')
    return {'size': path.stat().st_size, 'sha256': digest(path)}


def identity(root=ROOT):
    ci = os.environ.get('GITHUB_SHA', '')
    source = os.environ.get('CWP_SOURCE_SHA', '')
    require(re.fullmatch('[0-9a-f]{40}', ci or '') and re.fullmatch('[0-9a-f]{40}', source or ''), 'Exact source identity required')
    run = os.environ.get('GITHUB_RUN_ID', ''); attempt = os.environ.get('GITHUB_RUN_ATTEMPT', '')
    require(re.fullmatch('[1-9][0-9]*', run) and re.fullmatch('[1-9][0-9]*', attempt), 'Exact run and attempt required')
    git = lambda *args: subprocess.check_output(['git', *args], cwd=root, text=True).strip()
    require(git('rev-parse', 'HEAD') == ci, 'Checkout is not the exact CI commit')
    return {'schema': 1, 'repository': os.environ['GITHUB_REPOSITORY'], 'run_id': run, 'attempt': attempt,
            'ci_sha': ci, 'source_sha': source, 'tree': git('rev-parse', 'HEAD^{tree}'),
            'workflow_sha256': digest(root / '.github/workflows/application-tests.yml'),
            'helper_sha256': digest(root / 'tools/acceptance_artifacts.py'),
            'reports_sha256': digest(root / 'tools/acceptance-reports.json'),
            'contract_sha256': digest(root / 'tools/acceptance-contract.json')}


def no_private_name(name):
    p = safe_name(name)
    require(not any(x in {'.git', '__pycache__', 'node_modules'} for x in p.parts), 'Private/runtime directory refused')
    require(p.name not in {'config.php', '.installed.php', 'proxy-config.php', 'config-fixture.php', 'payment-master.key'}
            and not p.name.startswith('.env') and p.suffix.lower() not in {'.pem', '.key', '.p12', '.sql'}, 'Private file refused')


def input_allowed(name):
    no_private_name(name); parts = PurePosixPath(name).parts
    if len(parts) == 2 and parts[0] == 'dist':
        return True
    if len(parts) == 2 and parts[0] == 'qa' and parts[1] in BUILD_QA:
        return True
    return len(parts) >= 2 and parts[0] == 'test-runtime' and (
        (parts[1] in FIXTURES and len(parts) >= 3) or (parts[1] in FIXTURE_JSON and len(parts) == 2))


def collect(root, paths):
    files = {}
    for path in paths:
        require(not path.is_symlink(), 'Symlink root refused')
        items = [path] if path.is_file() else sorted(path.rglob('*'))
        for item in items:
            require(not item.is_symlink(), 'Symlink refused')
            if item.is_dir():
                continue
            name = item.relative_to(root).as_posix(); safe_name(name)
            require(name not in files, 'Duplicate source path')
            files[name] = file_facts(item)
    require(len(files) <= MAX_FILES and sum(x['size'] for x in files.values()) <= MAX_BYTES, 'Transfer bounds exceeded')
    require(len(files) == len({x.casefold() for x in files}), 'Case-colliding source paths')
    return files


def pack(root, files, target):
    target.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(target, 'x', compression=zipfile.ZIP_DEFLATED, compresslevel=6, allowZip64=True) as z:
        for name, facts in sorted(files.items()):
            path = root / name
            require(file_facts(path) == facts, 'Input changed while packing')
            z.write(path, name)
    return file_facts(target)


def inspect_archive(archive, files):
    """Validate the whole archive before any extraction; stream bounded members."""
    with zipfile.ZipFile(archive) as z:
        items = z.infolist()
        require(len(items) == len(files) <= MAX_FILES, 'Missing/extra/duplicate archive member')
        names = [i.filename for i in items]
        require(len(names) == len(set(names)) == len({n.casefold() for n in names}), 'Duplicate/case-colliding archive member')
        require(set(names) == set(files), 'Manifest/member mismatch')
        require(sum(i.file_size for i in items) <= MAX_BYTES, 'Archive exceeds bound')
        for item in items:
            safe_name(item.filename)
            mode = item.external_attr >> 16
            require(not item.is_dir() and stat.S_IFMT(mode) in (0, stat.S_IFREG), 'Nonregular archive member')
            require(not item.flag_bits & 1, 'Encrypted archive member refused')
            facts = files[item.filename]
            require(set(facts) == {'size', 'sha256'} and type(facts['size']) is int and facts['size'] >= 0
                    and re.fullmatch('[0-9a-f]{64}', facts['sha256']), 'Invalid file facts')
            require(item.file_size == facts['size'], 'Member size mismatch')
            h = hashlib.sha256()
            with z.open(item) as stream:
                for chunk in iter(lambda: stream.read(1024 * 1024), b''): h.update(chunk)
            require(h.hexdigest() == facts['sha256'], 'Member digest mismatch')
        require(z.testzip() is None, 'Archive CRC failed')


def extract(archive, files, root, allowed=lambda n: True):
    chosen = [n for n in files if allowed(n)]
    for name in chosen:
        path = root / safe_name(name)
        require(not path.exists(), 'Refuse overwriting existing transfer destination')
        require(all(not p.is_symlink() for p in path.parents if p != root.parent), 'Symlink destination refused')
        require(path.resolve().is_relative_to(root.resolve()), 'Destination escapes workspace')
    with zipfile.ZipFile(archive) as z:
        for name in chosen:
            path = root / name; path.parent.mkdir(parents=True, exist_ok=True)
            with z.open(name) as src, path.open('xb') as dst: shutil.copyfileobj(src, dst)


def dist_valid(root, expected_ci):
    dist = root / 'dist'; checksums = dist / 'SHA256SUMS.txt'
    pairs = [line.split('  ') for line in checksums.read_text().splitlines()]
    require(len(pairs) == 11 and all(len(p) == 2 for p in pairs), 'Exact eleven package checksums required')
    seen = set()
    for expected, name in pairs:
        require(len(safe_name(name).parts) == 1 and name not in seen, 'Unsafe/duplicate package checksum')
        seen.add(name); require(digest(dist / name) == expected, 'Package checksum mismatch')
    require({p.name for p in dist.iterdir()} == seen | {'SHA256SUMS.txt'}, 'Unexpected package output')
    manifest = load(dist / 'manifest.json'); require(manifest['source_commit'] == expected_ci, 'Package source mismatch')
    zips = sorted(dist.glob('*.zip')); require(len(zips) == 3, 'Exact three archives required')
    for archive in zips:
        facts = load(archive.with_suffix('.manifest.json'))
        require(facts['source_commit'] == expected_ci and facts['artifact'] == archive.name, 'Archive source mismatch')
        # Builder manifest owns each ZIP member's size/hash, not just outer bytes.
        require(facts['sha256'] == digest(archive), 'Archive manifest digest mismatch')
        with zipfile.ZipFile(archive) as z:
            members = {n: {'size': z.getinfo(n).file_size, 'sha256': v} for n, v in facts['files'].items()}
        inspect_archive(archive, members)


def create_inputs(root, context, out):
    dist_valid(root, context['ci_sha'])
    paths = [root / 'dist'] + [root / 'test-runtime' / n for n in sorted(FIXTURES | FIXTURE_JSON)]
    paths += [root / 'qa' / n for n in sorted(BUILD_QA)]
    require(all(p.exists() for p in paths), 'Pristine input is missing')
    files = collect(root, paths); require(all(input_allowed(n) for n in files), 'Unapproved pristine input')
    archive = out / 'inputs.zip'; facts = pack(root, files, archive)
    inspect_archive(archive, files)
    manifest = {'kind': 'inputs', 'identity': context, 'archive': facts, 'files': files}
    write(out / 'inputs.json', manifest)
    return digest(out / 'inputs.json')


def verify_bundle(folder, kind, context, expected_digest):
    require(re.fullmatch('[0-9a-f]{64}', expected_digest or ''), 'Missing expected manifest digest')
    manifest_path = folder / (kind + '.json'); archive = folder / (kind + '.zip')
    require(digest(manifest_path) == expected_digest, 'Manifest digest mismatch')
    obj = load(manifest_path)
    require(set(obj) == ({'kind', 'identity', 'archive', 'files'} if kind == 'inputs' else {'kind', 'identity', 'archive', 'files', 'lane', 'steps', 'input_sha256'}), 'Unexpected envelope fields')
    require(obj['kind'] == kind and obj['identity'] == context, 'Wrong source/run/attempt identity')
    require(file_facts(archive) == obj['archive'], 'Archive size/digest mismatch')
    inspect_archive(archive, obj['files'])
    return obj


def receive_inputs(folder, root, context, expected_digest, lane):
    require(lane in LANES[1:], 'Unknown input consumer')
    obj = verify_bundle(folder, 'inputs', context, expected_digest)
    require(all(input_allowed(n) for n in obj['files']), 'Unapproved pristine input')
    required = {'test-runtime/' + n for n in FIXTURE_JSON}
    require(required <= obj['files'].keys() and all(any(n.startswith('test-runtime/' + f + '/') for n in obj['files']) for f in FIXTURES), 'Missing fixture family')
    extract(folder / 'inputs.zip', obj['files'], root, lambda n: lane == 'native-browser' or not n.startswith('qa/'))
    dist_valid(root, context['ci_sha'])
    write(root / '.acceptance/input-receipt.json', {'identity': context, 'input_sha256': expected_digest, 'lane': lane})


def evidence_allowed(name, lane):
    no_private_name(name); parts = PurePosixPath(name).parts
    # Exact public outputs of delivery_cases.archive(), retained after local browser opening.
    # No other nested QA path or evidence lane is admitted.
    if lane == 'native-browser' and name in {
            'qa/client-website-extracted/HANDOVER.md',
            'qa/client-website-extracted/LICENSES.md',
            'qa/client-website-extracted/index.html'}: return True
    if lane == 'build' and len(parts) == 2 and parts[0] == 'dist': return True
    if len(parts) != 2 or parts[0] != 'qa': return False
    filename = parts[1]
    if lane == 'build': return filename in BUILD_QA
    if lane == 'recovery': return filename == 'structure-results.json'
    if lane == 'apache': return filename.startswith(('apache-', 'organized-'))
    return filename not in {'structure-results.json'} and not filename.startswith(('apache-', 'organized-'))


def seal(root, context, out, lane, steps, input_sha):
    require(lane in LANES, 'Unknown evidence lane')
    require(isinstance(steps, dict) and set(steps) == STEP_KEYS[lane] and all(type(v) is str and v in {'success', 'failure', 'skipped', 'cancelled'} for v in steps.values()), 'Invalid step outcomes')
    paths = [root / 'qa'] if (root / 'qa').exists() else []
    if lane == 'build' and (root / 'dist').exists(): paths.append(root / 'dist')
    files = collect(root, paths)
    require(all(evidence_allowed(n, lane) for n in files), 'Evidence crosses partition/private boundary')
    facts = pack(root, files, out / 'partition.zip')
    inspect_archive(out / 'partition.zip', files)
    write(out / 'partition.json', {'kind': 'partition', 'identity': context, 'archive': facts, 'files': files,
                                'lane': lane, 'steps': steps, 'input_sha256': input_sha})
    if lane == 'native-browser':
        # Upload the same immutable snapshot used by final assembly, never a still
        # writable QA directory. No runtime/application inputs enter this snapshot.
        extract(out / 'partition.zip', files, root / '.acceptance/checkpoint')
    return digest(out / 'partition.json')


def reports(root):
    result = {}
    for path in sorted((root / 'qa').glob('*.json')):
        obj = load(path)
        if not isinstance(obj, dict) or not isinstance(obj.get('checks'), list): continue
        checks = obj['checks']; require(checks, 'Empty named report: ' + path.name)
        names = []; failed = []
        for c in checks:
            require(isinstance(c, dict), 'Malformed named check')
            name = c.get('name', c.get('label')); outcome = c.get('passed', c.get('ok'))
            require(isinstance(name, str) and name and type(outcome) is bool, 'Malformed named result')
            names.append(name)
            if not outcome: failed.append(name)
        verdict = obj.get('passed', obj.get('ok', True))
        passed = not failed and (verdict is True or type(verdict) is int and verdict == len(checks)) and not any(obj.get(k) for k in ('failed', 'failure', 'failures', 'error', 'errors', 'exception'))
        result[path.name] = {'passed': passed, 'executions': len(names), 'unique_names': len(set(names)),
                             'sha256': digest(path), 'failed_names': failed}
    return result


def aggregate(root, context, incoming, bindings, expected, checkpoint_only=False):
    """Always preserve trustworthy partial partitions; no invented passing results."""
    errors = []; accepted = {}; owners = {}; folded_owners = {}; input_sha = bindings.get('build', {}).get('input_sha256', '')
    required = {'build', 'native-browser'} if checkpoint_only else set(LANES)
    ids = [bindings.get(n, {}).get('artifact_id') for n in required if bindings.get(n, {}).get('artifact_id')]
    if len(ids) != len(set(ids)): errors.append('Duplicate partition artifact IDs')
    (root / 'qa').mkdir(parents=True, exist_ok=True)
    for lane in LANES:
        b = bindings.get(lane, {})
        if lane not in required:
            if b.get('result') != 'skipped': errors.append(lane + ': expected explicit checkpoint skip')
            continue
        if b.get('result') != 'success': errors.append(lane + ': job ' + str(b.get('result', 'missing')))
        try:
            require(re.fullmatch('[1-9][0-9]*', b.get('artifact_id', '')), 'Missing artifact identity')
            obj = verify_bundle(incoming / lane, 'partition', context, b.get('sha256', ''))
            require(obj['lane'] == lane and all(evidence_allowed(n, lane) for n in obj['files']), 'Wrong evidence partition')
            require(lane == 'build' or (input_sha and obj['input_sha256'] == input_sha), 'Wrong or missing build input receipt')
            if lane == 'native-browser':
                if not re.fullmatch('[1-9][0-9]*', b.get('checkpoint_id', '')) or not re.fullmatch('[0-9a-f]{64}', b.get('checkpoint_digest', '')):
                    errors.append('native-browser: checkpoint upload identity is missing')
            require(isinstance(obj['steps'], dict) and set(obj['steps']) == STEP_KEYS[lane], 'Missing/extra step outcome')
            if not all(v == 'success' for v in obj['steps'].values()): errors.append(lane + ': non-successful steps')
            fresh = {}
            for name, facts in obj['files'].items():
                require(folded_owners.get(name.casefold(), name) == name, 'Case-colliding evidence path')
                if name in owners:
                    require(name.startswith('qa/') and name[3:] in BUILD_QA and facts == owners[name][1]
                            and {lane, owners[name][0]} == {'build', 'native-browser'}, 'Evidence path collision')
                else:
                    fresh[name] = facts
            extract(incoming / lane / 'partition.zip', obj['files'], root, lambda n: n in fresh)
            owners.update({n: (lane, f) for n, f in fresh.items()})
            folded_owners.update({n.casefold(): n for n in fresh})
            accepted[lane] = {'artifact_id': b['artifact_id'], 'manifest_sha256': b['sha256'], 'archive': obj['archive'], 'steps': obj['steps'], 'files': obj['files']}
        except Exception as exc:
            errors.append(lane + ': ' + str(exc))
    named = {}
    try:
        named = reports(root)
        want = set(expected['browser' if checkpoint_only else 'final'])
        missing = sorted(want - named.keys()); extra = sorted(named.keys() - want)
        if missing: errors.append('Missing reports: ' + ', '.join(missing))
        if extra: errors.append('Unexpected reports: ' + ', '.join(extra))
        if any(not r['passed'] for r in named.values()): errors.append('One or more named reports failed')
        if 'build' in accepted: dist_valid(root, context['ci_sha'])
    except Exception as exc:
        errors.append('Report/package validation: ' + str(exc))
    result = {'identity': context, 'mode': 'checkpoint' if checkpoint_only else 'full', 'complete': not errors,
              'full_partition_checks_passed': not errors and not checkpoint_only, 'errors': errors,
              'job_bindings': bindings, 'partitions': accepted, 'reports': named,
              'scope': 'Exact same-attempt partition assembly. Browser visual/diagnostic review and independent package comparison remain required. Missing/cancelled aggregate is incomplete.'}
    write(root / 'qa/parallel-acceptance.json', result)
    return result


def shipping_guard(root):
    subprocess.run(['git', 'diff', '--exit-code', 'HEAD', '--', 'site/'], cwd=root, check=True)
    require(not subprocess.check_output(['git', 'ls-files', '--others', '--exclude-standard', '--', 'site/'], cwd=root).strip(), 'Untracked shipping file')


def output(name, value):
    require(re.fullmatch('[a-z_][a-z0-9_]*', name) and '\n' not in value and '\r' not in value, 'Invalid job output')
    with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as f: f.write(name + '=' + value + '\n')


def main():
    ap = argparse.ArgumentParser(); ap.add_argument('command', choices=['pack-inputs', 'receive', 'seal', 'aggregate', 'guard'])
    ap.add_argument('--lane', choices=LANES); args = ap.parse_args()
    if args.command == 'guard': shipping_guard(ROOT); return
    context = identity()
    if args.command == 'pack-inputs': output('sha256', create_inputs(ROOT, context, ROOT / '.acceptance/inputs'))
    elif args.command == 'receive': receive_inputs(ROOT / '.acceptance/download', ROOT, context, os.environ.get('CWP_INPUT_SHA256', ''), args.lane)
    elif args.command == 'seal':
        receipt = ROOT / '.acceptance/input-receipt.json'
        input_sha = os.environ.get('CWP_INPUT_SHA256', '')
        if receipt.exists():
            saved = load(receipt)
            require(saved == {'identity': context, 'input_sha256': input_sha, 'lane': args.lane}, 'Receipt identity mismatch')
        output('sha256', seal(ROOT, context, ROOT / '.acceptance/partition', args.lane, json.loads(os.environ['CWP_STEP_OUTCOMES']), input_sha))
    else:
        result = aggregate(ROOT, context, ROOT / '.acceptance/incoming', json.loads(os.environ['CWP_JOB_BINDINGS']),
                           load(ROOT / 'tools/acceptance-reports.json'), os.environ.get('CWP_CHECKPOINT_ONLY') == 'true')
        if not result['complete']: raise SystemExit('Acceptance incomplete: ' + '; '.join(result['errors']))


if __name__ == '__main__': main()
