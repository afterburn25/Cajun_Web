"""Bind SW-AUTH-01 to exact84 before any historical source comparison.

Only source validators receive reconstructed predecessors. Native, browser and
package acceptance continue to use the complete current files in this checkout.
This standalone proof does not admit the separate finance or account corrections.
"""
from pathlib import Path
import copy
import hashlib
import json
import subprocess

PARENT = '84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd'
PARENT_TREE = 'a206e95562ab751cd9461db3a8ed32c4305ecbec'
RECEIPT = 'docs/SOFTWARE_CLIENT_AUTHORITY_CORRECTION.json'
VERIFIER = 'tests/software_authority_provenance.py'
INVENTORY = 'site/install/inventory.generated.php'
AREAS = ['site', 'foundation', 'tools/structure', 'tools/build.py', 'VERSION', 'START-HERE.txt']
GUARD_AREAS = ['tests', 'tools', '.github/workflows']
SHIPPING = frozenset((
    'site/app/billing/software_orders.php',
    'site/app/billing/software_charge_guard.php',
    'site/app/billing/software_subscription_enroll.php',
    'site/app/billing/software_subscription_cancel.php',
    'site/app/billing/software_client.php',
    INVENTORY,
))
PINNED_BEFORE = {'.github/workflows/software-commerce-native-fast.yml': '86d1e0b486632b7616a96ed36ac2c202fb7a0a0a26987a2c8541fd2109e61f26',
 'site/app/billing/software_charge_guard.php': '71a4f180ee4ee7d0f92e69adb50c58cfd4e15c3c3d1923fea635d435abe47ce0',
 'site/app/billing/software_orders.php': 'f5cc28db4b6aa06752ba04ce35e55ce9e7064d11126cb9e68563592b4932acdf',
 'site/app/billing/software_subscription_cancel.php': 'cf76198fd005b9c281aef7fe375dc5a07085f7fb468e521fe1a7f3eb26d9b5d5',
 'site/app/billing/software_subscription_enroll.php': 'b85fa4d80084874385cd5a100cc71d9dc298b945d15f7ba400f1214bd026f07f',
 'site/install/inventory.generated.php': 'd950fdb0cd0e5583d0792c02ff4c4f23b6dae6852bd42e1bcf1114d79ff5b35a',
 'tests/provider_browser_fixture_provenance.py': '5aacf4e03e1d28039b94e56e06fd8bdcc0524ea0ef8e66d39693a77e1bf94446',
 'tests/software_commerce_native.php': 'f0e4276cee60eb90ccff49eea2d7a674a72e5a0c5ce436bf7f94d33f47f8a56f',
 'tests/tickets_integration_contract.py': '318992e307c192108e6de7ceb975ed2df719d6e9358d4bdda574c6a4305552df'}
PINNED_AFTER = {'.github/workflows/software-commerce-native-fast.yml': 'a63ac4633a5d0f661af92a4b28db94934e675b8dcd06513b19413d31e626e408',
 'site/app/billing/software_charge_guard.php': '41cb73561a53b324cc484f91125f1d1d8e1555de69e9898a9b3ce7352bd5285a',
 'site/app/billing/software_orders.php': 'd875a9367664eebcc1cf74856270d50f7ba65a33dab70e998c361420c0b76397',
 'site/app/billing/software_subscription_cancel.php': '74cd6b10fa88bba889f4d70aaab8bf13d8dcab833c93f0a22bcd9695b5406c0f',
 'site/app/billing/software_subscription_enroll.php': 'b6ed161e407e80fdb933fee0f126699a779433de30819c14ace662b4965ef025',
 'site/install/inventory.generated.php': '1e8dc8d47f401036c815003e2a6b2fb9fee64a25352eee8d0ce6a16c7dc2201c',
 'tests/provider_browser_fixture_provenance.py': '57dc13e390af46559d8a5405503e3f5ab5f80ea7664a18cc2569a7034e5201bb',
 'tests/software_commerce_native.php': 'e416abbf2c10a12260ad42a9752c0148a2cf15e4b868d3d1b310d62f9d16ef99',
 'tests/tickets_integration_contract.py': 'b4f29e2dc63c887f81ac04810c2508afffae3e23350e488d2c02cbec4854bd2e'}
ADDITIONS = {'site/app/billing/software_client.php': 'bc5c9e8b935da05315742d2868688874c35348184250602faad4cd18f2cfa2e7',
 'tests/software_commerce_authority.php': '6eb139ba00638fea501c9a0a86670bf58f9888ef0e1ee0f66519a00eb6e7f6e7',
 'tests/software_commerce_authority_boundaries.php': '53c23873ac815634ba3d93d70065fec0f66fe5c805d36944ba914baebd3af057',
 'tests/software_commerce_authority_worker.php': 'f68f4a9a62cbcf6df16d2aa5e3cd7ebaad1e9faf366e58bfbde636a6c22c26da'}


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def git(root, *args):
    return subprocess.check_output(['git', *args], cwd=root)


def ordinary_bytes(root, path):
    target = root / path
    require(target.is_file() and not target.is_symlink(), 'Nonregular SW authority source: ' + path)
    require(all(not parent.is_symlink() for parent in target.parents if parent != root.parent),
            'Symlink ancestor of SW authority source: ' + path)
    return target.read_bytes()


def validate_data(data, verifier):
    require(isinstance(data, dict) and set(data) == {
        'parent', 'parent_tree', 'shipping', 'verifier_sha256', 'files', 'additions'},
        'Unexpected SW authority receipt fields')
    require(data['parent'] == PARENT and data['parent_tree'] == PARENT_TREE,
            'Wrong SW authority parent identity')
    require(isinstance(data['shipping'], list) and set(data['shipping']) == SHIPPING
            and len(data['shipping']) == len(SHIPPING), 'Unexpected SW authority shipping scope')
    require(data['verifier_sha256'] == digest(verifier), 'SW authority verifier changed')
    require(isinstance(data['files'], dict) and set(data['files']) == set(PINNED_BEFORE),
            'Unexpected SW authority inverse paths')
    require(isinstance(data['additions'], dict) and data['additions'] == ADDITIONS,
            'Unexpected SW authority additions')
    for path, row in data['files'].items():
        require(isinstance(row, dict) and set(row) == {'before_sha256', 'after_sha256', 'edits'},
                'Unexpected SW authority inverse fields: ' + path)
        require(row['before_sha256'] == PINNED_BEFORE[path],
                'SW authority predecessor identity changed: ' + path)
        require(row['after_sha256'] == PINNED_AFTER[path],
                'SW authority output identity changed: ' + path)
        require(isinstance(row['edits'], list) and bool(row['edits']),
                'Missing SW authority inverse: ' + path)
        for edit in row['edits']:
            require(isinstance(edit, list) and len(edit) == 2
                    and all(isinstance(value, str) and bool(value) for value in edit)
                    and edit[0] != edit[1], 'Malformed SW authority inverse: ' + path)
    return data


def receipt(root):
    return validate_data(json.loads(ordinary_bytes(root, RECEIPT)), ordinary_bytes(root, VERIFIER))


def reconstruct(data, path, value):
    require(path in PINNED_BEFORE, 'Unknown SW authority predecessor path')
    row = data['files'][path]
    require(row['before_sha256'] == PINNED_BEFORE[path]
            and row['after_sha256'] == PINNED_AFTER[path], 'Changed SW authority inverse identities: ' + path)
    require(digest(value) == PINNED_AFTER[path], 'Current SW authority bytes changed: ' + path)
    text = value.decode('utf-8')
    for old, new in reversed(row['edits']):
        require(text.count(new) == 1, 'Nonunique SW authority inverse: ' + path)
        text = text.replace(new, old, 1)
    original = text.encode('utf-8')
    require(digest(original) == PINNED_BEFORE[path], 'SW authority predecessor reconstruction differs: ' + path)
    for old, new in row['edits']:
        require(text.count(old) == 1, 'Nonunique SW authority forward edit: ' + path)
        text = text.replace(old, new, 1)
    require(text.encode('utf-8') == value, 'SW authority correction is not reversible: ' + path)
    return original


def validate_parent(parent, tree, ancestor):
    require(parent == PARENT and tree == PARENT_TREE and ancestor,
            'SW authority correction does not descend from exact84')


def validate_scope(changed, untracked):
    require(set(changed) == SHIPPING, 'Unreviewed SW authority shipping difference: '
            + repr(set(changed).symmetric_difference(SHIPPING)))
    require(not untracked, 'Untracked SW authority shipping source: ' + repr(untracked))


def validate_addition(path, value, parent_paths):
    require(path in ADDITIONS and path not in parent_paths,
            'SW authority addition already exists or is unknown: ' + path)
    require(value is not None and digest(value) == ADDITIONS[path],
            'SW authority added source changed: ' + path)


def canonical_inventory(root):
    site = root / 'site'
    rows = []
    for target in sorted(site.rglob('*'), key=lambda path: path.relative_to(site).as_posix()):
        require(not target.is_symlink(), 'Symlink in SW authority shipping tree')
        if target.is_file():
            name = target.relative_to(site).as_posix()
            if name != 'install/inventory.generated.php':
                rows.append("    '" + name + "'=>'" + digest(target.read_bytes()) + "',\n")
    guard = "<?php\ndeclare(strict_types=1);\nif (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }\n"
    return (guard + 'function setup_expected_files(): array { return [\n'
            + ''.join(rows) + ']; }\n').encode('utf-8')


def validate_inventory(current, expected):
    require(current == expected, 'SW authority installer inventory is not canonical current source')


def parent_entries(root):
    entries = {}
    for entry in git(root, 'ls-tree', '-r', '-z', PARENT).split(b'\0'):
        if not entry:
            continue
        metadata, name = entry.split(b'\t', 1)
        mode, kind, oid = metadata.decode().split(' ')
        entries[name.decode()] = (mode, kind, oid)
    return entries


def retained_path(path):
    # Every pre-existing test, builder/tool, workflow and JSON receipt stays exact,
    # except the finite native/adaptor/filter inverses above. No historical receipt
    # is rewritten to make corrected source appear to have been tested earlier.
    return (path.startswith(('tests/', 'tools/', '.github/workflows/'))
            or path.startswith('docs/') and path.endswith('.json')) and path not in PINNED_BEFORE


def verify_current(root):
    data = receipt(root)
    ancestry = subprocess.run(['git', 'merge-base', '--is-ancestor', PARENT, 'HEAD'],
                              cwd=root, capture_output=True).returncode == 0
    validate_parent(PARENT, git(root, 'rev-parse', PARENT + '^{tree}').decode().strip(), ancestry)
    changed = git(root, 'diff', '--name-only', PARENT, '--', *AREAS).decode().splitlines()
    untracked = git(root, 'ls-files', '--others', '--exclude-standard', '--', *AREAS).decode().splitlines()
    ignored = git(root, 'ls-files', '--others', '--ignored', '--exclude-standard', '--', *AREAS).decode().splitlines()
    validate_scope(changed, untracked + ignored)
    expected_guards = {path for path in set(PINNED_BEFORE) | set(ADDITIONS) | {VERIFIER}
                       if path.startswith(('tests/', 'tools/', '.github/workflows/'))}
    changed_guards = set(git(root, 'diff', '--name-only', PARENT, '--', *GUARD_AREAS).decode().splitlines())
    changed_guards.update(git(root, 'ls-files', '--others', '--exclude-standard', '--', *GUARD_AREAS).decode().splitlines())
    require(changed_guards == expected_guards, 'Unreviewed SW authority guard, fixture, builder or workflow difference')
    entries = parent_entries(root)
    for path in PINNED_BEFORE:
        original = reconstruct(data, path, ordinary_bytes(root, path))
        require(original == git(root, 'cat-file', 'blob', PARENT + ':' + path),
                'SW authority inverse is not exact published source: ' + path)
    for path in ADDITIONS:
        validate_addition(path, ordinary_bytes(root, path), entries)
    require(VERIFIER not in entries and RECEIPT not in entries,
            'SW authority proof must be absent at its published parent')
    retained = 0
    for path, (mode, kind, oid) in entries.items():
        if retained_path(path):
            require(mode in ('100644', '100755') and kind == 'blob',
                    'Nonregular retained SW authority input: ' + path)
            value = ordinary_bytes(root, path)
            blob = b'blob ' + str(len(value)).encode() + b'\0' + value
            require(hashlib.sha1(blob).hexdigest() == oid, 'Historical SW authority input changed: ' + path)
            retained += 1
    validate_inventory(ordinary_bytes(root, INVENTORY), canonical_inventory(root))
    return data, retained


def source_revision(root):
    """Return exact84 only after all current correction and retained bytes verify."""
    verify_current(root)
    return PARENT


def predecessor_bytes(root, path, value):
    """Historical hash/inverse comparisons only; never an executable fixture."""
    if path not in PINNED_BEFORE:
        return value
    data, _ = verify_current(root)
    return reconstruct(data, path, value)


def validate(root):
    data, retained = verify_current(root)
    verifier = ordinary_bytes(root, VERIFIER)
    checks = []

    def passed(name):
        checks.append({'name': name, 'passed': True})

    def refused(name, operation):
        try:
            operation()
        except AssertionError:
            passed(name)
        else:
            raise AssertionError(name)

    passed('SW authority exact84 parent tree and ancestry verified')
    passed('SW authority shipping scope is exactly five application modules and canonical inventory')
    passed('SW authority fixture, adapter and native-fast filter scope is exact')
    passed('SW authority preserves ' + str(retained) + ' historical test, tool, workflow and JSON receipt files')
    passed('SW authority inventory binds every current shipping file without regenerating it')
    passed('SW authority added proof and receipt are absent at exact84')
    for path in sorted(PINNED_BEFORE):
        value = ordinary_bytes(root, path)
        reconstruct(data, path, value)
        passed('SW authority exact reversible predecessor ' + path)
        refused('SW authority refuses altered current bytes ' + path,
                lambda: reconstruct(data, path, value + b'\n# drift\n'))
        for field in ('before_sha256', 'after_sha256'):
            changed = copy.deepcopy(data)
            changed['files'][path][field] = '0' * 64
            refused('SW authority refuses altered ' + field + ' ' + path,
                    lambda: validate_data(changed, verifier))
        changed = copy.deepcopy(data)
        changed['files'][path]['edits'].pop()
        refused('SW authority refuses omitted inverse ' + path,
                lambda: reconstruct(changed, path, value))
        changed = copy.deepcopy(data)
        changed['files'][path]['edits'][0][0] += '# unreviewed predecessor\n'
        refused('SW authority refuses altered inverse ' + path,
                lambda: reconstruct(changed, path, value))
    for path in sorted(ADDITIONS):
        value = ordinary_bytes(root, path)
        passed('SW authority exact added source ' + path)
        refused('SW authority refuses changed addition ' + path,
                lambda: validate_addition(path, value + b'\n# drift\n', set()))
        refused('SW authority refuses missing addition ' + path,
                lambda: validate_addition(path, None, set()))
        refused('SW authority refuses addition present at parent ' + path,
                lambda: validate_addition(path, value, {path}))
    for case in ('parent', 'parent_tree', 'shipping', 'files', 'verifier', 'additions'):
        changed = copy.deepcopy(data)
        if case in ('parent', 'parent_tree'):
            changed[case] = '0' * 40
        elif case == 'shipping':
            changed['shipping'].append('site/app/billing/unreviewed.php')
        elif case == 'files':
            changed['files'].pop(sorted(PINNED_BEFORE)[0])
        elif case == 'verifier':
            changed['verifier_sha256'] = '0' * 64
        else:
            changed['additions']['tests/unreviewed.php'] = '0' * 64
        refused('SW authority refuses receipt drift ' + case, lambda: validate_data(changed, verifier))
    refused('SW authority refuses changed verifier bytes', lambda: validate_data(data, verifier + b'\n# drift\n'))
    refused('SW authority refuses unrelated parent ancestry', lambda: validate_parent(PARENT, PARENT_TREE, False))
    refused('SW authority refuses wrong parent tree', lambda: validate_parent(PARENT, '0' * 40, True))
    refused('SW authority refuses broader shipping scope', lambda: validate_scope(SHIPPING | {'site/unreviewed.php'}, []))
    refused('SW authority refuses missing shipping scope', lambda: validate_scope(SHIPPING - {INVENTORY}, []))
    refused('SW authority refuses untracked shipping bytes', lambda: validate_scope(SHIPPING, ['site/untracked.php']))
    refused('SW authority refuses noncanonical inventory', lambda: validate_inventory(b'altered', ordinary_bytes(root, INVENTORY)))
    return checks


if __name__ == '__main__':
    checks = validate(Path(__file__).resolve().parents[1])
    print(json.dumps({'passed': True, 'count': len(checks), 'checks': checks,
                      'scope': 'Exact current-source identities and reversible historical comparisons only; no native, HTTP, browser, merchant or release certification.'}, indent=2))
