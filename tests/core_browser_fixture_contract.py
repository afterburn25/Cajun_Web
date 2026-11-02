"""Fail-closed exact-source receipts, not installed application acceptance."""
from pathlib import Path
from tempfile import TemporaryDirectory
import copy,hashlib,json
from core_browser_fixture_provenance import PATHS,predecessor
from provider_browser_fixture_provenance import PATHS as provider_paths, predecessor as provider_predecessor
ROOT=Path(__file__).resolve().parents[1]
checks=[]
def check(name,value):
    checks.append({'name':name,'passed':bool(value)})
    if not value:raise AssertionError(name)
receipt=json.loads((ROOT/'docs/CORE_BROWSER_FIXTURE_REPAIRS.json').read_text())
with TemporaryDirectory(prefix='cwp-core-receipts-') as tmp:
    root=Path(tmp);(root/'docs').mkdir()
    for path in sorted(PATHS):
        current=(ROOT/path).read_bytes()
        if path in provider_paths:current=provider_predecessor(ROOT,path,current)
        from account_notification_fixture_provenance import core_input
        current=core_input(ROOT,path,current)
        restored=predecessor(ROOT,path,current)
        check('Original bytes reconstructed '+path,hashlib.sha256(restored).hexdigest()==receipt['files'][path]['before_sha256'])
        for case in ('changed-output','changed-predecessor','missing-edit','wrong-base'):
            data=copy.deepcopy(receipt);value=current
            if case=='changed-output':value+=b'\n# synthetic unreviewed modification\n'
            elif case=='changed-predecessor':data['files'][path]['before_sha256']='0'*64
            elif case=='missing-edit':data['files'][path]['edits'].pop()
            else:data['base']='0'*40
            (root/'docs/CORE_BROWSER_FIXTURE_REPAIRS.json').write_text(json.dumps(data))
            rejected=False
            try:predecessor(root,path,value)
            except AssertionError:rejected=True
            check('Reject '+case+' '+path,rejected)
print(json.dumps({'passed':True,'scope':'Exact fixture identities and negative receipt checks only; not SQL, HTTP or browser results','checks':checks},indent=2))
