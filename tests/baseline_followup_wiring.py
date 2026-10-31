"""Reconstruct only two reviewed predecessor fixtures; all old hashes remain checked."""
import hashlib
import json

def predecessor(root, path, value):
    records=json.loads((root/'tools/integration/baseline-acceptance-inverses.json').read_text())
    if set(records)!={'tests/apache_acceptance.py','tests/provider_catalog_import_native.php'}:
        raise AssertionError('Unexpected current-session fixture inverse scope')
    if path not in records:
        return value
    item=records[path]
    if hashlib.sha256(value).hexdigest()!=item['after']:
        raise AssertionError('Current fixture drift: '+path)
    for before,after in reversed(item['edits']):
        source=after.encode();target=before.encode()
        if value.count(source)!=1:
            raise AssertionError('Fixture inverse must be unique: '+path)
        value=value.replace(source,target,1)
    if hashlib.sha256(value).hexdigest()!=item['before']:
        raise AssertionError('Historical fixture bytes changed: '+path)
    return value
