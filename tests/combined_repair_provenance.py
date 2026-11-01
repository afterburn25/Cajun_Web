"""Validate exact corrected files and reconstruct their complete predecessor bytes."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
RECEIPTS=['docs/COMBINED_ACCEPTANCE_REPAIR_SOURCE.json','docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json']
def predecessor(root,path,value):
    found=[]
    for name in RECEIPTS:
        row=json.loads((root/name).read_text())['files'].get(path)
        if row is not None:found.append(row)
    if len(found)!=1:raise AssertionError('Unknown or overlapping correction: '+path)
    row=found[0]
    if hashlib.sha256(value).hexdigest()!=row['after_sha256']:raise AssertionError('Repaired source changed: '+path)
    if row['before_sha256'] is None:return None
    text=value.decode()
    for old,new in reversed(row['edits']):
        if text.count(new)!=1:raise AssertionError('Nonunique correction inverse: '+path)
        text=text.replace(new,old,1)
    raw=text.encode()
    if hashlib.sha256(raw).hexdigest()!=row['before_sha256']:raise AssertionError('Prior source changed outside correction: '+path)
    return raw
def validate(root=ROOT):
    checks=[];seen=set()
    for name in RECEIPTS:
        data=json.loads((root/name).read_text())
        if data['base_tree']!='2f11bc1fa202020880ead9abc1ee72d19205cac5':raise AssertionError('Unknown reviewed base tree')
        for path in data['files']:
            if path in seen or '..' in Path(path).parts or Path(path).is_absolute():raise AssertionError('Unsafe correction path')
            seen.add(path);predecessor(root,path,(root/path).read_bytes());checks.append({'name':'Exact repaired file and predecessor retained '+path,'passed':True})
    if not checks:raise AssertionError('Missing correction evidence')
    return checks
if __name__=='__main__':
    from provider_browser_fixture_provenance import projected_validation
    checks=projected_validation(ROOT,validate,RECEIPTS)
    print(json.dumps({'passed':True,'scope':'Exact source identities and predecessor inverses only','checks':checks},indent=2))
