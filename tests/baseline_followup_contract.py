"""Current fixture inverses fail closed; no SQL or network."""
from pathlib import Path
import json
from baseline_followup_wiring import predecessor
ROOT=Path(__file__).resolve().parents[1]
checks=[]
def check(name,passed):
    checks.append({'name':name,'passed':bool(passed)})
    if not passed:raise AssertionError(name)
def denied(name,work):
    try:work()
    except AssertionError:check(name,True);return
    check(name,False)
for path in ['tests/apache_acceptance.py','tests/provider_catalog_import_native.php']:
    value=(ROOT/path).read_bytes();old=predecessor(ROOT,path,value)
    check('Reviewed fixture reconstructs distinct predecessor: '+path,old!=value and len(old)>0)
    denied('Unreviewed appended source fails: '+path,lambda:predecessor(ROOT,path,value+b'\n# drift'))
    denied('Truncated fixture cannot pass: '+path,lambda:predecessor(ROOT,path,value[:-1]))
check('Other source is unchanged',predecessor(ROOT,'tests/unrelated.py',b'unchanged')==b'unchanged')
print(json.dumps({'passed':True,'scope':'Exact historical inverse contract only','checks':checks},indent=2))
