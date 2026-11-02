"""Bind the small notification settings correction to the published 3cc source.

Historical projections are only inputs to source comparisons. Native and browser
acceptance execute the current application and the complete retained native suite.
"""
from pathlib import Path
import copy
import hashlib
import json
import subprocess

PARENT = '3cc10c144e2cd4e78ff5952cee9f60710a07a9ff'
RECEIPT = 'docs/NOTIFICATION_SETTINGS_FRESHNESS_CORRECTION.json'
VERIFIER = 'tests/notification_settings_freshness_provenance.py'
REGRESSION = 'tests/notification_settings_freshness_cases.php'
SHIPPING = frozenset((
    'site/app/notifications/feed.php',
    'site/app/notifications/outbox.php',
    'site/install/inventory.generated.php',
))
PINNED_BEFORE = {
    'site/app/notifications/feed.php': '9968652e7f6982cf858dcff4e18377a045a3b080223415a288cd331730a6ee22',
    'site/app/notifications/outbox.php': '4ed8d00ed7a4588c918df625ada0f9d024847ff87635a964bb0efe8536c61969',
    'site/install/inventory.generated.php': '32562a0bb8fa0b6cbf82539be91b95916b7d740ff5cd3d1681aee7781ab034fa',
    'tests/account_notification_contract.py': '4eb1252e865f6b7733a82f42c0e856a08daf3fda751adba1602205f2ffb41865',
    'tests/notification_center_native.php': '85e707a0728272247667f4b2490c7730c6eb80ce353963814b291765556cb27b',
}
AREAS = ['site', 'foundation', 'tools/structure', 'tools/build.py', 'VERSION', 'START-HERE.txt']
RETAINED = [
    'tools/integration',
    '.github/workflows/account-notifications-acceptance.yml',
    '.github/workflows/account-notifications-combined.yml',
    '.github/workflows/notification-center.yml',
    '.github/workflows/application-tests.yml',
    'docs/ACCOUNT_NOTIFICATION_COMBINATION.json',
    'docs/ACCOUNT_NOTIFICATION_FIXTURE_COMBINATION.json',
    'docs/NOTIFICATION_CENTER_SOURCE.json',
    'docs/NOTIFICATION_CENTER_CORRECTIONS.json',
    'tests/account_integration_contract.py',
    'tests/notification_integration_contract.py',
    'tests/tickets_integration_contract.py',
    'tests/account_notification_fixture_provenance.py',
    'tests/account_notification_workflow_contract.py',
]


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def git(root, *args):
    return subprocess.check_output(['git', *args], cwd=root)


def validate_data(data, verifier):
    require(set(data) == {'parent', 'shipping', 'verifier_sha256', 'files', 'additions'},
            'Unexpected settings-correction receipt fields')
    require(data['parent'] == PARENT, 'Wrong settings-correction parent')
    require(set(data['shipping']) == SHIPPING and len(data['shipping']) == len(SHIPPING),
            'Unexpected settings-correction shipping scope')
    require(data['verifier_sha256'] == digest(verifier), 'Settings-correction verifier changed')
    require(set(data['files']) == set(PINNED_BEFORE), 'Unexpected settings-correction paths')
    require(set(data['additions']) == {REGRESSION}, 'Unexpected settings-correction additions')
    for path, row in data['files'].items():
        require(set(row) == {'before_sha256', 'after_sha256', 'edits'}
                and row['before_sha256'] == PINNED_BEFORE[path],
                'Settings-correction predecessor changed: ' + path)
        require(isinstance(row['after_sha256'], str) and len(row['after_sha256']) == 64,
                'Missing settings-correction output identity: ' + path)
        require(isinstance(row['edits'], list) and bool(row['edits']),
                'Missing settings-correction inverse: ' + path)
        for edit in row['edits']:
            require(isinstance(edit, list) and len(edit) == 2
                    and all(isinstance(value, str) and value for value in edit)
                    and edit[0] != edit[1], 'Malformed settings-correction inverse: ' + path)
    require(isinstance(data['additions'][REGRESSION], str) and len(data['additions'][REGRESSION]) == 64,
            'Missing settings regression identity')
    return data


def receipt(root):
    require(not (root / RECEIPT).is_symlink() and not (root / VERIFIER).is_symlink(),
            'Symlink settings-correction proof')
    return validate_data(json.loads((root / RECEIPT).read_text()), (root / VERIFIER).read_bytes())


def reconstruct(data, path, value):
    require(path in PINNED_BEFORE, 'Unknown settings-correction projection')
    row = data['files'][path]
    require(digest(value) == row['after_sha256'], 'Settings-correction source changed: ' + path)
    text = value.decode('utf-8')
    for old, new in reversed(row['edits']):
        require(text.count(new) == 1, 'Nonunique settings-correction inverse: ' + path)
        text = text.replace(new, old, 1)
    original = text.encode('utf-8')
    require(digest(original) == PINNED_BEFORE[path] == row['before_sha256'],
            'Settings-correction reconstructed predecessor differs: ' + path)
    for old, new in row['edits']:
        require(text.count(old) == 1, 'Nonunique settings-correction forward edit: ' + path)
        text = text.replace(old, new, 1)
    require(text.encode('utf-8') == value, 'Settings correction is not reversible: ' + path)
    return original


def validate_scope(changed, untracked):
    require(set(changed) == SHIPPING, 'Unreviewed settings-correction shipping change: '
            + repr(set(changed).symmetric_difference(SHIPPING)))
    require(not untracked, 'Untracked settings-correction shipping additions: ' + repr(untracked))


def validate(root):
    data = receipt(root)
    verifier = (root / VERIFIER).read_bytes()
    checks = []

    def check(name, passed):
        require(passed, name)
        checks.append({'name': name, 'passed': True})

    def refused(name, operation):
        try:
            operation()
        except AssertionError:
            check(name, True)
        else:
            raise AssertionError(name)

    subprocess.run(['git', 'merge-base', '--is-ancestor', PARENT, 'HEAD'], cwd=root, check=True)
    check('Correction descends from the exact published parent', True)
    changed = git(root, 'diff', '--name-only', PARENT, '--', *AREAS).decode().splitlines()
    untracked = git(root, 'ls-files', '--others', '--exclude-standard', '--', *AREAS).decode().splitlines()
    validate_scope(changed, untracked)
    check('Only the two notification modules and canonical inventory change shipping bytes', True)
    check('Historical builders, receipts, source guards and all focused/full workflow bodies remain unchanged',
          not git(root, 'diff', '--name-only', PARENT, '--', *RETAINED)
          and not git(root, 'ls-files', '--others', '--exclude-standard', '--', *RETAINED))

    for path in sorted(PINNED_BEFORE):
        target = root / path
        check('Correction is an ordinary file ' + path, not target.is_symlink())
        value = target.read_bytes()
        original = reconstruct(data, path, value)
        check('Exact published predecessor reconstructed ' + path,
              original == git(root, 'show', PARENT + ':' + path))
        refused('Changed current source refused ' + path,
                lambda: reconstruct(data, path, value + b'\n# unreviewed drift\n'))
        changed_data = copy.deepcopy(data)
        changed_data['files'][path]['before_sha256'] = '0' * 64
        refused('Changed predecessor receipt refused ' + path,
                lambda: validate_data(changed_data, verifier))
        changed_data = copy.deepcopy(data)
        changed_data['files'][path]['edits'].pop()
        refused('Missing inverse refused ' + path,
                lambda: reconstruct(changed_data, path, value))
        changed_data = copy.deepcopy(data)
        changed_data['files'][path]['after_sha256'] = '0' * 64
        refused('Changed output receipt refused ' + path,
                lambda: reconstruct(changed_data, path, value))

    regression = root / REGRESSION
    check('Actual cross-process native regression is retained exactly',
          not regression.is_symlink() and digest(regression.read_bytes()) == data['additions'][REGRESSION])
    entry = (root / 'tests/notification_center_native.php').read_text()
    original = reconstruct(data, 'tests/notification_center_native.php', entry.encode()).decode()
    include = "require_once __DIR__.'/notification_settings_freshness_cases.php';\n"
    call = '    nc_settings_freshness_cases();\n'
    check('All original native assertions and their order are retained with two additive hooks',
          entry.count(include) == 1 and entry.count(call) == 1
          and entry.replace(include, '', 1).replace(call, '', 1) == original)

    for kind in ('parent', 'paths', 'shipping', 'verifier', 'additions'):
        changed_data = copy.deepcopy(data)
        if kind == 'parent':
            changed_data['parent'] = '0' * 40
        elif kind == 'paths':
            changed_data['files'].pop('site/app/notifications/feed.php')
        elif kind == 'shipping':
            changed_data['shipping'].append('site/app/settings/store.php')
        elif kind == 'verifier':
            changed_data['verifier_sha256'] = '0' * 64
        else:
            changed_data['additions']['tests/unreviewed.php'] = '0' * 64
        refused('Unreviewed settings-correction ' + kind + ' refused',
                lambda: validate_data(changed_data, verifier))
    refused('Broader shipping change refused',
            lambda: validate_scope(SHIPPING | {'site/app/settings/store.php'}, []))
    refused('Omitted shipping correction refused',
            lambda: validate_scope(SHIPPING - {'site/app/notifications/feed.php'}, []))
    refused('Untracked shipping file refused',
            lambda: validate_scope(SHIPPING, ['site/app/unreviewed.php']))
    return checks


def source_revision(root):
    """Verify current bytes before the historical six-file comparison resumes."""
    validate(root)
    return PARENT


def predecessor_bytes(root, path, value):
    """Supply only a verified historical comparison input, never runtime code."""
    return reconstruct(receipt(root), path, value) if path in PINNED_BEFORE else value


if __name__ == '__main__':
    outcomes = validate(Path(__file__).resolve().parents[1])
    print(json.dumps({'passed': True, 'parent': PARENT,
                      'scope': 'Exact reversible source correction and retained assertions; not installed acceptance',
                      'checks': outcomes}, indent=2))
