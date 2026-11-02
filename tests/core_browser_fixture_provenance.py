"""Exact reversible current-session corrections; not SQL or browser acceptance."""
from pathlib import Path
import hashlib
import json
PATHS=frozenset('tests/'+name+'_cases.py' for name in (
    'support','catalog','provider_catalog_import','payments','attempts',
    'notifications','ledger','delivery','activity','release'))
BASE='8ead3459fd458725c32f4a29dc36deefbce085ae'
def predecessor(root,path,value):
    data=json.loads((root/'docs/CORE_BROWSER_FIXTURE_REPAIRS.json').read_text())
    if data['base']!=BASE or set(data['files'])!=PATHS or path not in PATHS:
        raise AssertionError('Unreviewed core browser correction scope')
    row=data['files'][path]
    if hashlib.sha256(value).hexdigest()!=row['after_sha256']:
        raise AssertionError('Corrected browser source differs: '+path)
    text=value.decode()
    for old,new in reversed(row['edits']):
        if old==new or text.count(new)!=1:
            raise AssertionError('Nonunique core correction inverse: '+path)
        text=text.replace(new,old,1)
    result=text.encode()
    if hashlib.sha256(result).hexdigest()!=row['before_sha256']:
        raise AssertionError('Original browser bytes not retained: '+path)
    return result
if __name__=='__main__':
    root=Path(__file__).resolve().parents[1]
    for path in sorted(PATHS):predecessor(root,path,(root/path).read_bytes())
    print('All ten original browser families reconstruct exactly.')
