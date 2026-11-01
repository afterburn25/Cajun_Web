#!/usr/bin/env python3
"""Reproduce exact test-only repairs on an isolated branch; never mutate shipping code."""
from pathlib import Path
import gzip,hashlib,json,os
ROOT=Path(__file__).resolve().parents[2]
BRANCH='refs/heads/chatgpt/core-browser-repairs-20261101'
BASE='8ead3459fd458725c32f4a29dc36deefbce085ae'
def sha(raw):return hashlib.sha256(raw).hexdigest()
def main():
 if os.environ.get('GITHUB_REF')!=BRANCH:raise SystemExit('Isolated test-repair branch only')
 packed=(ROOT/'tools/integration/core-browser-recipe.gzpart').read_bytes()
 if sha(packed)!='de93ee20bfbceb1923126fa94caacad04924cea48e731e74b8c827c5bb5dd675':raise SystemExit('Reviewed recipe changed')
 raw=gzip.decompress(packed)
 if len(raw)!=27007:raise SystemExit('Unexpected recipe size')
 data=json.loads(raw)
 paths={'tests/'+x+'_cases.py' for x in ['support','catalog','payments','attempts','notifications','ledger','delivery','activity','provider_catalog_import','staff']}
 if data['base']!=BASE or {r['path'] for r in data['records']}!=paths or len(data['records'])!=10:raise SystemExit('Unreviewed correction scope')
 if set(data['additions'])!={'tests/core_browser_fixture_provenance.py','tests/core_browser_fixture_contract.py'}:raise SystemExit('Unknown helper addition')
 if set(data['derived'])!={'tests/acceptance_artifacts_test.py','docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json','docs/STANDALONE_ACCEPTANCE_REPAIRS.json'}:raise SystemExit('Unknown derived receipt')
 facts={r['path']:{'before':r['before'],'after':r['after']} for r in data['records']}
 facts.update(data['derived'])
 facts.update({p:{'before':None,'after':v['sha256']} for p,v in data['additions'].items()})
 core=(json.dumps(data['core_receipt'],indent=2)+'\n').encode()
 facts['docs/CORE_BROWSER_FIXTURE_REPAIRS.json']={'before':None,'after':sha(core)}
 facts['docs/BROWSER_OWNER_FIXTURE_REPAIRS.json']={'before':data['owner_receipt_before'],'after':data['owner_receipt_sha256']}
 current={}
 for p in facts:
  path=ROOT/p
  if path.is_symlink() or any(parent.is_symlink() for parent in path.parents):raise SystemExit('Symlink source refused')
  current[p]=path.read_bytes() if path.exists() else None
 if all(current[p] is not None and sha(current[p])==r['after'] for p,r in facts.items()):print('All17 exact corrections already recorded.');return
 for p,r in facts.items():
  if (sha(current[p]) if current[p] is not None else None)!=r['before']:raise SystemExit('Source drift before mutation: '+p)
 outputs={}
 for r in data['records']:
  text=current[r['path']].decode()
  for old,new in r['edits']:
   if old==new or text.count(old)!=1:raise SystemExit('Nonunique reviewed edit: '+r['path'])
   text=text.replace(old,new,1)
  outputs[r['path']]=text.encode()
 for p,v in data['additions'].items():outputs[p]=v['content'].encode()
 outputs['docs/CORE_BROWSER_FIXTURE_REPAIRS.json']=core
 p='docs/BROWSER_OWNER_FIXTURE_REPAIRS.json';receipt=json.loads(current[p]);staff=next(r for r in data['records'] if r['path']=='tests/staff_cases.py');row=receipt['files'][staff['path']]
 if row['after_sha256']!=staff['before']:raise SystemExit('Existing staff receipt mismatch')
 row['edits'].extend(staff['edits']);row['after_sha256']=staff['after'];outputs[p]=(json.dumps(receipt,indent=2)+'\n').encode()
 p='tests/acceptance_artifacts_test.py';text=current[p].decode()
 for old,new in data['harness_edits']:
  if text.count(old)!=1:raise SystemExit('Nonunique harness edit')
  text=text.replace(old,new,1)
 outputs[p]=text.encode()
 p='docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json';receipt=json.loads(current[p]);row=receipt['files']['tests/acceptance_artifacts_test.py']
 if row['after_sha256']!=sha(current['tests/acceptance_artifacts_test.py']):raise SystemExit('Earlier harness receipt mismatch')
 row['edits'].extend(data['harness_edits']);row['after_sha256']=sha(outputs['tests/acceptance_artifacts_test.py']);outputs[p]=(json.dumps(receipt,indent=2)+'\n').encode()
 p='docs/STANDALONE_ACCEPTANCE_REPAIRS.json';receipt=json.loads(current[p])
 for name in ['tests/acceptance_artifacts_test.py','docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json']:
  row=receipt['files'][name]
  if row['after']!=sha(current[name]):raise SystemExit('Earlier standalone receipt mismatch')
  row['edits'].append([current[name].decode(),outputs[name].decode(),1]);row['after']=sha(outputs[name])
 outputs[p]=(json.dumps(receipt,indent=2)+'\n').encode()
 if set(outputs)!=set(facts):raise SystemExit('Unexpected output set')
 for p,v in outputs.items():
  if sha(v)!=facts[p]['after']:raise SystemExit('Reviewed output differs: '+p)
 for p,v in outputs.items():
  path=ROOT/p;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(v)
 print('Expanded17 exact test and receipt files. Application files and main are untouched.')
if __name__=='__main__':main()
