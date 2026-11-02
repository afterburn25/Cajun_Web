"""Source-bound corrections with exact predecessor reconstruction; no checks are omitted."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
def manifest(root=ROOT):
    data=json.loads((root/'docs/STANDALONE_ACCEPTANCE_REPAIRS.json').read_text())
    if data['base']!='60e35bd0e22872f1a9473c25b8f6a6ef67900ff0':raise AssertionError('Unexpected correction source')
    return data

def predecessor(root,path,raw):
    row=manifest(root)['files'][path]
    if hashlib.sha256(raw).hexdigest()!=row['after']:raise AssertionError('Unreviewed repaired source: '+path)
    if row['before'] is None:return None
    text=raw.decode()
    for old,new,count in reversed(row['edits']):
        if text.count(new)!=count:raise AssertionError('Nonunique repaired-source inverse: '+path)
        text=text.replace(new,old)
    if hashlib.sha256(text.encode()).hexdigest()!=row['before']:raise AssertionError('Unexpected predecessor: '+path)
    return text.encode()

def validate(root=ROOT):
    checks=[]
    for path in manifest(root)['files']:
        p=Path(path)
        if p.is_absolute() or '..' in p.parts:raise AssertionError('Unsafe correction path')
        predecessor(root,path,(root/path).read_bytes())
        checks.append({'name':'Exact standalone correction retains predecessor '+path,'passed':True})
    if not checks:raise AssertionError('Empty corrections are not acceptance')
    return checks
if __name__=='__main__':
    from provider_browser_fixture_provenance import projected_validation
    checks=projected_validation(ROOT,validate,('docs/STANDALONE_ACCEPTANCE_REPAIRS.json',))
    print(json.dumps({'passed':True,'scope':'Reviewed file hashes and exact inverses only; not SQL or browser acceptance','checks':checks},indent=2))
