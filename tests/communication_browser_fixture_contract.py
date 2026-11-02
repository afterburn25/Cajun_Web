"""Fail-closed exact-source receipts, not installed application acceptance."""
from pathlib import Path
from tempfile import TemporaryDirectory
import copy,hashlib,json
from communication_browser_fixture_provenance import PATHS,predecessor
ROOT=Path(__file__).resolve().parents[1]
checks=[]
def check(name,value):
    checks.append({'name':name,'passed':bool(value)})
    if not value:raise AssertionError(name)
receipt=json.loads((ROOT/'docs/COMMUNICATION_BROWSER_FIXTURE_REPAIRS.json').read_text())
with TemporaryDirectory(prefix='cwp-communication-receipts-') as tmp:
    root=Path(tmp);(root/'docs').mkdir()
    for path in sorted(PATHS):
        current=(ROOT/path).read_bytes()
        restored=predecessor(ROOT,path,current)
        check('Original bytes reconstructed '+path,hashlib.sha256(restored).hexdigest()==receipt['files'][path]['before_sha256'])
        for case in ('changed-output','changed-predecessor','missing-edit','wrong-base'):
            data=copy.deepcopy(receipt);value=current
            if case=='changed-output':value+=b'\n# synthetic unreviewed modification\n'
            elif case=='changed-predecessor':data['files'][path]['before_sha256']='0'*64
            elif case=='missing-edit':data['files'][path]['edits'].pop()
            else:data['base']='0'*40
            (root/'docs/COMMUNICATION_BROWSER_FIXTURE_REPAIRS.json').write_text(json.dumps(data))
            rejected=False
            try:predecessor(root,path,value)
            except AssertionError:rejected=True
            check('Reject '+case+' '+path,rejected)
print(json.dumps({'passed':True,'scope':'Exact fixture identities and negative receipt checks only; not SQL, HTTP or browser results','checks':checks},indent=2))
