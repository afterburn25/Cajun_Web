"""Bind the composed fixture source to both unchanged historical proof chains.

These projections are used only for source comparisons. Installed/browser tests
always execute the current files, including both retained navigation assertions.
"""
from pathlib import Path
import ast
import copy
import hashlib
import json

ROOT_SOURCE = '84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd'
FEATURE_SOURCE = 'ebcbbb56336249fee2073c8f02e9d6998740b64e'
COMMON_SOURCE = '366d53b1ed6b367b32664f2b91ee073fc6813544'
RECEIPT = 'docs/ACCOUNT_NOTIFICATION_FIXTURE_COMBINATION.json'
VERIFIER = 'tests/account_notification_fixture_provenance.py'
PATHS = frozenset((
    'tests/release_cases.py',
    'tests/owner_navigation_contract.py',
    'tests/provider_browser_fixture_provenance.py',
    'tests/core_browser_fixture_contract.py',
))
RELEASE = 'tests/release_cases.py'
COMMON_RELEASE = '807837a2d42796a8b3661c3fe5da647a4a6f88fc75c25a5a81c42c35943b41af'
# Replaced by the generator with hashes independently read from both parent trees.
PINNED_BEFORE = {'tests/release_cases.py': {'root': '09f49d2f6d67f07d774d69d4872af2724d9a41258c4dee0658aa5e472b2d27d5', 'feature': '178bccbd9538de1e7d47e51659225f61c7e4324effdedc2b0a311001d36281e3'}, 'tests/owner_navigation_contract.py': {'root': None, 'feature': 'ad6f2a75bacca9d6f43bbc15cf05d649be3c4fdb2e975501e6c74db1d147c5c2'}, 'tests/provider_browser_fixture_provenance.py': {'root': '5aacf4e03e1d28039b94e56e06fd8bdcc0524ea0ef8e66d39693a77e1bf94446', 'feature': None}, 'tests/core_browser_fixture_contract.py': {'root': 'dff5a0b2fcc0b130350ff6f77cd3b5a73b2f2894d5b2def610b2035c2d4c9216', 'feature': '31255b4878647cd150d805d30f70d3005102607fe5aff192ad418457b89de8db'}}
RETAINED = {'docs/PROVIDER_BROWSER_FIXTURE_REPAIRS_20261101.json': '9ea6c2f18879510b5475ae0d2d4ddccfbc2cbca4f86874ff3b0a579657801e2e', 'docs/CORE_BROWSER_FIXTURE_REPAIRS.json': 'f7aca2b087bbf43fb26be0e158d9b7945819f07ce46061191bed38e7b7ae4bbf', 'tests/core_browser_fixture_provenance.py': '489603afc4cdf1cf3a8ffd08329df93af77fee336904b10ff32bdadcf858ef71'}


def digest(value):
    return hashlib.sha256(value).hexdigest()


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def validate_data(data, verifier):
    require(set(data) == {'parents', 'common_source', 'verifier_sha256', 'files'},
            'Unexpected fixture-combination receipt fields')
    require(data['parents'] == {'root': ROOT_SOURCE, 'feature': FEATURE_SOURCE}
            and data['common_source'] == COMMON_SOURCE, 'Wrong fixture-combination sources')
    require(data['verifier_sha256'] == digest(verifier), 'Fixture-combination verifier changed')
    require(set(data['files']) == PATHS, 'Unexpected fixture-combination paths')
    for path, row in data['files'].items():
        require(set(row) == {'before', 'after_sha256', 'edits'}
                and row['before'] == PINNED_BEFORE[path]
                and set(row['edits']) == {'root', 'feature'},
                'Historical fixture identity changed: ' + path)
        require(isinstance(row['after_sha256'], str) and len(row['after_sha256']) == 64,
                'Missing combined fixture identity: ' + path)
        for side, before in row['before'].items():
            edits = row['edits'][side]
            if before is None:
                require(edits is None, 'Unexpected absent predecessor edits: ' + path)
                continue
            require(isinstance(edits, list) and bool(edits), 'Missing combination inverse: ' + path)
            for edit in edits:
                require(isinstance(edit, list) and len(edit) == 2
                        and all(isinstance(value, str) and value for value in edit)
                        and edit[0] != edit[1], 'Malformed combination inverse: ' + path)
    return data


def receipt(root):
    for path, expected in RETAINED.items():
        target = root / path
        require(not target.is_symlink() and digest(target.read_bytes()) == expected,
                'Retained historical fixture evidence changed: ' + path)
    return validate_data(json.loads((root / RECEIPT).read_text()), (root / VERIFIER).read_bytes())


def reconstruct(data, path, value, side):
    require(path in PATHS and side in {'root', 'feature'}, 'Unknown combination projection')
    row = data['files'][path]
    require(row['before'][side] is not None, 'No requested historical predecessor: ' + path)
    require(digest(value) == row['after_sha256'], 'Combined fixture source changed: ' + path)
    text = value.decode('utf-8')
    for old, new in reversed(row['edits'][side]):
        require(text.count(new) == 1, 'Nonunique combination inverse: ' + path)
        text = text.replace(new, old, 1)
    original = text.encode('utf-8')
    require(digest(original) == row['before'][side], 'Combination predecessor differs: ' + path)
    for old, new in row['edits'][side]:
        require(text.count(old) == 1, 'Nonunique combination forward edit: ' + path)
        text = text.replace(old, new, 1)
    require(text.encode('utf-8') == value, 'Combination edit is not reversible: ' + path)
    return original


def project(root, path, value, side):
    if path not in PATHS:
        return value
    return reconstruct(receipt(root), path, value, side)


def core_input(root, path, value):
    """Supply the exact feature-layer release bytes to its unchanged core verifier.

The provider inverse already verified the current composition and reconstructed
the common release source. Bind that specific input before reconstructing the
other historical parent; neither arbitrary bytes nor an unreviewed current file
can enter the core proof chain.
"""
    if path != RELEASE:
        return value
    require(digest(value) == COMMON_RELEASE, 'Unexpected common release comparison input')
    current = (root / RELEASE).read_bytes()
    return project(root, RELEASE, current, 'feature')


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

    for path in sorted(PATHS):
        target = root / path
        check('Combined fixture is an ordinary file ' + path, not target.is_symlink())
        value = target.read_bytes()
        for side, expected in data['files'][path]['before'].items():
            if expected is None:
                continue
            check('Exact ' + side + ' fixture reconstructed ' + path,
                  digest(reconstruct(data, path, value, side)) == expected)
            refused('Changed combined bytes refused for ' + side + ' ' + path,
                    lambda: reconstruct(data, path, value + b'\n# unreviewed drift\n', side))
            changed = copy.deepcopy(data)
            changed['files'][path]['before'][side] = '0' * 64
            refused('Changed historical identity refused for ' + side + ' ' + path,
                    lambda: validate_data(changed, verifier))
            changed = copy.deepcopy(data)
            changed['files'][path]['edits'][side].pop()
            refused('Missing inverse refused for ' + side + ' ' + path,
                    lambda: reconstruct(changed, path, value, side))
    for kind in ('parents', 'paths', 'verifier'):
        changed = copy.deepcopy(data)
        if kind == 'parents':
            changed['parents']['root'] = '0' * 40
        elif kind == 'paths':
            del changed['files'][RELEASE]
        else:
            changed['verifier_sha256'] = '0' * 64
        refused('Unreviewed combination ' + kind + ' refused', lambda: validate_data(changed, verifier))

    helper = ast.parse((root / 'tests/owner_navigation_contract.py').read_text())
    destinations, = (node.value for node in helper.body if isinstance(node, ast.Assign)
                     and any(isinstance(target, ast.Name) and target.id == 'DESTINATIONS'
                             for target in node.targets))
    check('Shared navigation retains all five owner destinations including licensing',
          ast.literal_eval(destinations) == (('Overview', 'dashboard'), ('Service catalog', 'catalog'),
          ('20i workspace', 'provider-settings'), ('Administrators', 'staff'), ('Licenses & software', 'studio-licenses')))
    release = ast.parse((root / RELEASE).read_text())
    calls = [node for node in ast.walk(release) if isinstance(node, ast.Call)
             and isinstance(node.func, ast.Name) and node.func.id == 'check'
             and node.args and isinstance(node.args[0], ast.Constant)]
    navigation = [node for node in calls if node.args[0].value == 'Grouped owner navigation retains core destinations']
    check('Full release driver executes the shared navigation assertion exactly once',
          len(navigation) == 1 and len(navigation[0].args) == 2
          and ast.dump(navigation[0].args[1]) == ast.dump(ast.parse('core_admin_destinations(p)', mode='eval').body))
    check('Full release driver retains the separate exact-route assertion',
          len([node for node in calls if node.args[0].value == 'Grouped owner navigation links to the actual administration routes']) == 1)
    current = (root / RELEASE).read_bytes()
    check('Feature core projection remains exact',
          core_input(root, RELEASE, _common_release(root, data, current))
          == reconstruct(data, RELEASE, current, 'feature'))
    refused('Core comparison rejects unrelated predecessor bytes',
            lambda: core_input(root, RELEASE, b'unreviewed historical source'))
    return checks


def _common_release(root, data, current):
    # Execute only the already retained inverse recipe as data, not old test code.
    historical = reconstruct(data, RELEASE, current, 'root').decode('utf-8')
    provider = json.loads((root / 'docs/PROVIDER_BROWSER_FIXTURE_REPAIRS_20261101.json').read_text())['files'][RELEASE]
    require(digest(historical.encode()) == provider['after_sha256'], 'Root release receipt input differs')
    for old, new in reversed(provider['edits']):
        require(historical.count(new) == 1, 'Root release inverse is not unique')
        historical = historical.replace(new, old, 1)
    require(digest(historical.encode()) == provider['before_sha256'] == COMMON_RELEASE,
            'Common release source changed')
    return historical.encode()


if __name__ == '__main__':
    outcomes = validate(Path(__file__).resolve().parents[1])
    print(json.dumps({'passed': True, 'scope': 'Exact fixture composition and retained assertions; not installed acceptance', 'checks': outcomes}, indent=2))
