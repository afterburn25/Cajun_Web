"""Exact source/inverse guard for reviewed updater and staff browser corrections."""
from pathlib import Path
import hashlib
import json

PATHS = frozenset(('tests/structure_helpers.py', 'tests/staff_cases.py'))

def predecessor(root, path, value):
    data = json.loads((root / 'docs/BROWSER_OWNER_FIXTURE_REPAIRS.json').read_text())
    if data['base'] != 'a827fca40f5c056d249293b11f07e425f0b71600' or set(data['files']) != PATHS:
        raise AssertionError('Unreviewed browser correction scope')
    row = data['files'][path]
    if hashlib.sha256(value).hexdigest() != row['after_sha256']:
        raise AssertionError('Corrected browser source differs: ' + path)
    text = value.decode()
    for old, new in reversed(row['edits']):
        if text.count(new) != 1:
            raise AssertionError('Nonunique browser correction inverse: ' + path)
        text = text.replace(new, old, 1)
    raw = text.encode()
    if hashlib.sha256(raw).hexdigest() != row['before_sha256']:
        raise AssertionError('Original browser assertion bytes were not retained: ' + path)
    return raw

if __name__ == '__main__':
    root = Path(__file__).resolve().parents[1]
    for path in sorted(PATHS):
        predecessor(root, path, (root / path).read_bytes())
    print('Both browser predecessors reconstruct exactly.')
