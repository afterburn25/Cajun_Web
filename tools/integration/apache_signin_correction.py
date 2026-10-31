#!/usr/bin/env python3
"""Apply one exact reviewed test correction; no runtime or deployment mutation."""
from pathlib import Path
import hashlib,json,os
ROOT=Path(__file__).resolve().parents[2]
if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/apache-signin-integration-20261031':raise SystemExit('Isolated test-correction branch only')
fix={'before_sha256':'c913995e424a4f4c979c42fef8b1a404c02c9080a54b6e261949846cd37182dc','after_sha256':'8b060b6148d79eecca96db3e4689a5f07db342e3fc57c843f5d93df4a20cad6f','before':"            check(label+' authenticated owner recovery page remains reachable in maintenance',r.status_code==200 and 'Current owner password' in r.text)",'after':"            recovery_form=BeautifulSoup(r.text,'html.parser').select_one('form[method=post]')\n            check(label+' owner recovery sign-in remains reachable in maintenance',\n                  r.status_code==200 and recovery_form is not None\n                  and recovery_form.select_one('input[name=email][type=email]') is not None\n                  and recovery_form.select_one('input[name=owner_password][type=password]') is not None\n                  and recovery_form.select_one('input[name=csrf]') is not None)"}
wiring_old="    if path == 'tests/prepare_legacy.py':"
wiring_new="""    if path == 'tests/apache_acceptance.py':
        fix = contract.get('owner_update_signin_test')
        if fix is not None:
            before, after = fix['before'].encode(), fix['after'].encode()
            if hashlib.sha256(value).hexdigest() != fix['after_sha256'] or value.count(after) != 1:
                raise AssertionError('Owner-update sign-in correction changed beyond its reviewed extent')
            value = value.replace(after, before, 1)
            if hashlib.sha256(value).hexdigest() != fix['before_sha256']:
                raise AssertionError('Owner-update correction lost predecessor Apache coverage')
    if path == 'tests/prepare_legacy.py':"""
identities={'tests/apache_acceptance.py':(fix['before_sha256'],fix['after_sha256']),'tests/cloud_site_requests_wiring.py':('df71ec091795b411d8e67b14769c1591fe9bfa1b5963a15abc07e2ac2cda6624','2144ecd31944f8538b5de0ccc880d3585ca1179d765621afceb79dafa0801df6'),'tools/acceptance-contract.json':('bffbb7004d7ee2d2a3eae598e068979703634a4444037f2bdc687da4b4ce7ecb','b30b4e6210744274610beff297854ed41eaed8ee4fd3980a20e503fc20cd7584')}
def digest(data):return hashlib.sha256(data).hexdigest()
raw={}
for name in identities:
    path=ROOT/name
    if path.is_symlink() or any(p.is_symlink() for p in path.parents):raise SystemExit('Symlink source refused')
    raw[name]=path.read_bytes()
if all(digest(raw[n])==ids[1] for n,ids in identities.items()):
    print('Exact Apache correction already recorded');raise SystemExit(0)
if any(digest(raw[n])!=ids[0] for n,ids in identities.items()):raise SystemExit('Reviewed predecessor source drift')
outputs={}
for name,old,new in [('tests/apache_acceptance.py',fix['before'],fix['after']),('tests/cloud_site_requests_wiring.py',wiring_old,wiring_new)]:
    text=raw[name].decode()
    if text.count(old)!=1:raise SystemExit('Correction is not unique: '+name)
    outputs[name]=text.replace(old,new,1).encode()
contract=json.loads(raw['tools/acceptance-contract.json'])
if 'owner_update_signin_test' in contract:raise SystemExit('Unexpected previous correction')
contract['owner_update_signin_test']=fix
contract['cloud_site_requests']['source_sha256']['tests/cloud_site_requests_wiring.py']=identities['tests/cloud_site_requests_wiring.py'][1]
outputs['tools/acceptance-contract.json']=(json.dumps(contract,indent=2)+'\n').encode()
for name,data in outputs.items():
    if digest(data)!=identities[name][1]:raise SystemExit('Reviewed correction output mismatch: '+name)
for name,data in outputs.items():(ROOT/name).write_bytes(data)
print(json.dumps({'passed':True,'scope':'Exact three-file test correction; installed acceptance remains separate','paths':list(outputs)}))
