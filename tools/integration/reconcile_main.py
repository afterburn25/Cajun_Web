#!/usr/bin/env python3
"""Reconcile fixed main history on an isolated branch, without changing release bytes."""
from collections import Counter
from pathlib import Path
import difflib
import hashlib
import json
import os
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
MAIN = '0099b9190756c31e690b83b2cdf93c366de03f5c'
RELEASE = '5c54d5fdd0c2ca1b006885b7fc533e8ba9d1140d'
BASE = 'fdf40a25be3b9a7c2e154a1fad17ab4c7ae55827'
BRANCH = 'refs/heads/chatgpt/1261-main-reconcile-20261030'
RECEIPT = 'docs/MAIN_RECONCILIATION_1.26.1.json'
MAIN_PATHS = {
    '.github/workflows/license-financing-authorize-sandbox.yml',
    '.github/workflows/sandbox-workflow-security.yml',
    '.github/workflows/software-authorize-sandbox.yml',
    'PROJECT_STATUS.md', 'SESSION_HANDOFF.md',
    'docs/CODEX_SANDBOX_WORKFLOW_SECURITY_REVIEW.md',
    'docs/PR266_SANDBOX_BOOTSTRAP.md',
    'docs/evidence/sandbox-review-queue-policy.json',
    'tests/anet_sandbox_runtime.py', 'tests/anet_sandbox_runtime_contract.py',
    'tests/anet_sandbox_tokenize.py', 'tests/finance_anet_sandbox_acceptance.py',
    'tests/sandbox_workflow_contract.py', 'tests/software_anet_sandbox_acceptance.py',
}
REVIEWED_RESOLUTIONS = {
    '.github/workflows/license-financing-authorize-sandbox.yml',
    '.github/workflows/software-authorize-sandbox.yml',
    'tests/sandbox_workflow_contract.py',
}
NOTES = {'PROJECT_STATUS.md', 'SESSION_HANDOFF.md'}
IMMUTABLE = ['site', 'foundation', 'tools/structure', 'tools/build.py', 'VERSION', 'START-HERE.txt']


def git(*args, check=True):
    return subprocess.run(['git', *args], cwd=ROOT, capture_output=True, check=check)


def text(*args):
    return git(*args).stdout.decode('utf-8').strip()


def show(ref, path):
    return git('show', ref + ':' + path).stdout


def preserve_notes(ours: bytes, base: bytes, theirs: bytes) -> bytes:
    """Union only append-only prose; retain every release line and all main additions."""
    old, new = base.decode().splitlines(), theirs.decode().splitlines()
    matcher = difflib.SequenceMatcher(a=old, b=new, autojunk=False)
    added = []
    for op, a, b, c, d in matcher.get_opcodes():
        if op not in ('equal', 'insert'):
            raise RuntimeError('Main changed existing prose; explicit review required')
        if op == 'insert':
            added.extend(new[c:d])
    with tempfile.TemporaryDirectory(prefix='cwp-note-merge-') as temp:
        files = [Path(temp)/x for x in ('ours', 'base', 'theirs')]
        for path, data in zip(files, (ours, base, theirs)):
            path.write_bytes(data)
        r = subprocess.run(['git', 'merge-file', '-p', '--union', *map(str, files)], capture_output=True)
        if r.returncode not in (0, 1):
            raise RuntimeError('Prose union failed: ' + r.stderr.decode())
        merged = r.stdout
    lines = Counter(merged.decode().splitlines())
    if Counter(ours.decode().splitlines()) - lines or Counter(added) - lines:
        raise RuntimeError('Prose union would discard release notes or main additions')
    if any(line.startswith(('<<<<<<< ', '=======', '>>>>>>> ')) for line in merged.decode().splitlines()):
        raise RuntimeError('Unresolved prose markers')
    return merged


def verify_runtime():
    git('diff', '--exit-code', RELEASE, '--', *IMMUTABLE)
    subprocess.run(['python3', 'tests/sandbox_workflow_contract.py'], cwd=ROOT, check=True)
    subprocess.run(['python3', 'tests/anet_sandbox_runtime_contract.py'], cwd=ROOT, check=True)


def main():
    if os.environ.get('GITHUB_REF') != BRANCH:
        raise SystemExit('Reconciliation branch only; never writes main')
    head = text('rev-parse', 'HEAD')
    if head != os.environ.get('EXPECTED_HEAD'):
        raise SystemExit('Checkout is not the requested immutable source')
    if text('status', '--porcelain'):
        raise SystemExit('Refuse a modified checkout')
    if text('ls-remote', 'origin', 'refs/heads/main').split()[0] != MAIN:
        raise SystemExit('Main moved; review new changes before reconciling')
    receipt = ROOT / RECEIPT
    if receipt.exists():
        saved = json.loads(receipt.read_text())
        if saved.get('main_sha') != MAIN or saved.get('release_sha') != RELEASE:
            raise SystemExit('Unexpected prior receipt')
        git('merge-base', '--is-ancestor', MAIN, 'HEAD')
        verify_runtime()
        print('Reconciliation already recorded and verified')
        return
    if text('merge-base', RELEASE, MAIN) != BASE:
        raise SystemExit('Unexpected merge ancestry')
    paths = set(text('diff', '--name-only', BASE, MAIN).splitlines())
    if paths != MAIN_PATHS:
        raise SystemExit('Unreviewed main-only file changes: ' + str(paths ^ MAIN_PATHS))
    git('merge-base', '--is-ancestor', RELEASE, 'HEAD')
    result = git('merge', '--no-ff', '--no-commit', MAIN, check=False)
    print(result.stdout.decode(), result.stderr.decode())
    conflicts = set(text('diff', '--name-only', '--diff-filter=U').splitlines())
    if result.returncode not in (0, 1) or conflicts - REVIEWED_RESOLUTIONS - NOTES:
        raise SystemExit('Unexpected merge conflicts; leave failure evidence intact: ' + str(conflicts))
    decisions = []
    for path in sorted(conflicts):
        current = show(RELEASE, path)
        if path in NOTES:
            resolved = preserve_notes(current, show(BASE, path), show(MAIN, path))
            policy = 'Union append-only notes; every release line and main-added line retained'
        else:
            resolved = current
            policy = 'Retain tested three-runner secret isolation and its unchanged hash-pinned contracts'
        target = ROOT/path
        if target.is_symlink():
            raise SystemExit('Symlink conflict refused')
        target.write_bytes(resolved)
        git('add', '--', path)
        decisions.append({'path': path, 'resolution': policy, 'sha256': hashlib.sha256(resolved).hexdigest()})
    if text('diff', '--name-only', '--diff-filter=U'):
        raise SystemExit('Unresolved files remain')
    git('diff', '--cached', '--check')
    verify_runtime()
    main_evidence = []
    for path in sorted(MAIN_PATHS):
        candidate_result = git('show', RELEASE + ':' + path, check=False)
        if candidate_result.returncode and path not in {
            'docs/CODEX_SANDBOX_WORKFLOW_SECURITY_REVIEW.md',
            'docs/PR266_SANDBOX_BOOTSTRAP.md',
            'docs/evidence/sandbox-review-queue-policy.json',
        }:
            raise RuntimeError('Unexpected absent release input: ' + path)
        candidate = candidate_result.stdout if candidate_result.returncode == 0 else None
        earlier = show(MAIN, path)
        actual = (ROOT/path).read_bytes()
        main_evidence.append({'path':path, 'main_sha256':hashlib.sha256(earlier).hexdigest(),
            'release_sha256':hashlib.sha256(candidate).hexdigest() if candidate is not None else None, 'resolved_sha256':hashlib.sha256(actual).hexdigest(),
            'same_as_release':actual==candidate, 'same_as_main':actual==earlier})
    receipt.write_text(json.dumps({'schema':1,'main_sha':MAIN,'release_sha':RELEASE,
        'merge_base':BASE,'input_head':head,'conflicts':decisions,'main_only_files':main_evidence,
        'immutable_paths':IMMUTABLE,'runtime_matches_delivered_release':True,
        'status':'Reconciled source only; pending exact-commit acceptance and normal protected main integration',
        'scope':'No changes to live services, main, credentials, payment configuration or delivered archives'},indent=2)+'\n')
    git('add', '--', RECEIPT)
    git('commit', '-m', 'merge: reconcile current main with tested 1.26.1 without changing shipping bytes')
    git('merge-base', '--is-ancestor', MAIN, 'HEAD')
    git('merge-base', '--is-ancestor', RELEASE, 'HEAD')
    verify_runtime()
    if text('status', '--porcelain'):
        raise SystemExit('Reconciled source is not clean')
    print('Reconciled commit:', text('rev-parse', 'HEAD'))


if __name__ == '__main__':
    main()
