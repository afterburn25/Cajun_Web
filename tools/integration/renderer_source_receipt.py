#!/usr/bin/env python3
"""Bind corrected renderer source while retaining exact original contract inverses."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/combined-acceptance-repair-20261031':raise SystemExit('Isolated combined-repair branch only')
sha=lambda b:hashlib.sha256(b).hexdigest()
render=json.loads((ROOT/'docs/COMBINED_RENDER_CONTRACTS.json').read_text())
# Every reviewed render edit must reverse to its original complete file.
for path,row in render['files'].items():
    raw=(ROOT/path).read_bytes()
    if sha(raw)!=row['after_sha256']:raise SystemExit('Renderer changed outside reviewed correction: '+path)
    text=raw.decode()
    for old,new in reversed(row['edits']):
        if text.count(new)!=1:raise SystemExit('Nonunique renderer inverse: '+path)
        text=text.replace(new,old,1)
    if sha(text.encode())!=row['before_sha256']:raise SystemExit('Renderer predecessor was not retained: '+path)
receipt=ROOT/'docs/COMBINED_RENDER_SOURCE_BINDING.json'
if receipt.exists():
    for path,digest in json.loads(receipt.read_text())['files'].items():
        if sha((ROOT/path).read_bytes())!=digest:raise SystemExit('Recorded renderer binding differs: '+path)
    print('Six complete renderer predecessors and recorded contract identities verified');raise SystemExit(0)
expected={
 'tools/acceptance-contract.json':'ceadd8712e199177e5d9d65570daefa8890098955e3b3a24e3216a389ff4b337',
 'docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json':'cdf53268e52e2c074372943bea5f1ea4a0de957b9e26cd94e934be31800ef1ee',
 'docs/COMBINED_NATIVE_ROUND2.json':'e96e97eccaa17979fac00817c475e8bfca9c7aecbf80191d7ab5803c21b44257'}
for path,digest in expected.items():
    if sha((ROOT/path).read_bytes())!=digest:raise SystemExit('Renderer binding predecessor drift: '+path)
path='tools/acceptance-contract.json';before=(ROOT/path).read_text()
file='tests/cloud_site_requests_ui_contract.php';row=render['files'][file]
old,new=row['before_sha256'],row['after_sha256']
if before.count(old)!=1:raise SystemExit('Expected one existing cloud-render source binding')
contract=json.loads(before)
if contract['cloud_site_requests']['source_sha256'][file]!=old:raise SystemExit('Unexpected cloud source binding')
after=before.replace(old,new,1)
(ROOT/path).write_text(after)
# The old source recipes remain reconstructible byte-for-byte, including the
# previously recorded whole-contract hash. No original guard is removed.
remaining_path='docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json'
previous_remaining=(ROOT/remaining_path).read_text();remaining=json.loads(previous_remaining)
bound=remaining['files'][path]
if bound['after_sha256']!=sha(before.encode()):raise SystemExit('Prior contract receipt differs')
bound['edits'].append([old,new]);bound['after_sha256']=sha(after.encode())
next_remaining=json.dumps(remaining,indent=2)+'\n';(ROOT/remaining_path).write_text(next_remaining)
round_path='docs/COMBINED_NATIVE_ROUND2.json';second=json.loads((ROOT/round_path).read_text())
for name,prior,result,edits in [(path,before,after,[[old,new]]),(remaining_path,previous_remaining,next_remaining,[[previous_remaining,next_remaining]])]:
    record=second['files'][name]
    if record['after_sha256']!=sha(prior.encode()):raise SystemExit('Round-two predecessor differs: '+name)
    record.setdefault('edits',[]).extend(edits);record['after_sha256']=sha(result.encode())
(ROOT/round_path).write_text(json.dumps(second,indent=2)+'\n')
receipt.write_text(json.dumps({'predecessor_commit':'d3bb0c231a511f945d8455aaf08e630f76cab096','files':{path:sha((ROOT/path).read_bytes()) for path in expected},'scope':'Current cloud renderer identity plus reversible predecessor receipts. All six renderer predecessors verified; no application, native case, permission or assertion removed.'},indent=2)+'\n')
subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
print('Recorded current renderer identity with all exact predecessor inverses retained')
