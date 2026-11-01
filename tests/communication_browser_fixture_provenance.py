"""Exact reversible current-session corrections; not SQL or browser acceptance."""
from pathlib import Path
import hashlib
import json
PATHS=frozenset('tests/'+name+'_cases.py' for name in (
    'mail_templates','mail_accounts','reviews','appointments','mail_connections',
    'mail_inbox','mail_message','mail_notices','mail_drafts','mail_submissions'))
BASE='44d00efb72363d7ebfae81180a404d60af5d45c5'
def predecessor(root,path,value):
    data=json.loads((root/'docs/COMMUNICATION_BROWSER_FIXTURE_REPAIRS.json').read_text())
    if data['base']!=BASE or set(data['files'])!=PATHS or path not in PATHS:
        raise AssertionError('Unreviewed communication browser correction scope')
    row=data['files'][path]
    if hashlib.sha256(value).hexdigest()!=row['after_sha256']:
        raise AssertionError('Corrected browser source differs: '+path)
    text=value.decode()
    for old,new in reversed(row['edits']):
        if old==new or text.count(new)!=1:
            raise AssertionError('Nonunique communication correction inverse: '+path)
        text=text.replace(new,old,1)
    result=text.encode()
    if hashlib.sha256(result).hexdigest()!=row['before_sha256']:
        raise AssertionError('Original browser bytes not retained: '+path)
    return result
if __name__=='__main__':
    root=Path(__file__).resolve().parents[1]
    for path in sorted(PATHS):predecessor(root,path,(root/path).read_bytes())
    print('All ten original communication browser families reconstruct exactly.')
