#!/usr/bin/env python3
"""Correct exact staged callbacks and fail-closed audit writes, retaining initial evidence."""
from pathlib import Path
import gzip,hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
RECEIPT=ROOT/'docs/NOTIFICATION_CENTER_CORRECTIONS.json'
BASE='982c9bfeb5ae6f04c8c240c0bc5330ffff9e163f'
def sha(b):return hashlib.sha256(b).hexdigest()
def main():
 if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/notification-center-20261101':raise SystemExit('Isolated notification branch only')
 rows=json.loads((ROOT/'tools/integration/notification-runtime-corrections.json').read_text())
 if len(rows)!=9 or len({r['path'] for r in rows})!=9:raise SystemExit('Unknown correction scope')
 packed=b''.join((ROOT/f'tools/integration/notification-center.{i}.gzpart').read_bytes() for i in range(18))
 if sha(packed)!='a3bb3b78aa15a6fd2f40bddd6e445c95ca11ad55a937a15ff765e7ce0dc7b585':raise SystemExit('Initial source recipe changed')
 data=json.loads(gzip.decompress(packed));facts={r['path']:r['after'] for r in data['records']};facts.update({p:r['after']for p,r in data['generated'].items()})
 if RECEIPT.exists():
  final=json.loads(RECEIPT.read_text())
  if final['predecessor']!=BASE or set(final['after'])!=set(facts):raise SystemExit('Unknown correction receipt')
  for p,h in final['after'].items():
   if (ROOT/p).is_symlink() or sha((ROOT/p).read_bytes())!=h:raise SystemExit('Final source drift: '+p)
 else:
  for p,h in facts.items():
   if (ROOT/p).is_symlink() or sha((ROOT/p).read_bytes())!=h:raise SystemExit('Initial source drift: '+p)
  outputs={}
  for r in rows:
   p=r['path']
   if p not in facts or facts[p]!=r['before']:raise SystemExit('Unexpected correction input: '+p)
   text=(ROOT/p).read_text()
   for old,new in r['edits']:
    if old==new or text.count(old)!=1:raise SystemExit('Nonunique correction: '+p)
    text=text.replace(old,new,1)
   if sha(text.encode())!=r['after']:raise SystemExit('Unexpected correction output: '+p)
   outputs[p]=text.encode()
  source=ROOT/'docs/NOTIFICATION_CENTER_SOURCE.json';original=source.read_text()
  for p,b in outputs.items():(ROOT/p).write_bytes(b)
  subprocess.run(['python3','-B','tools/build.py','--refresh'],cwd=ROOT,check=True)
  receipt=json.loads(original)
  changed={r['path'] for r in rows if r['path'].startswith('site/')}
  changed.add('site/install/inventory.generated.php')
  for p in changed:receipt['shipping'][p]['after']=sha((ROOT/p).read_bytes())
  source.write_text(json.dumps(receipt,indent=2)+'\n')
  allowed={r['path'] for r in rows}|{'docs/NOTIFICATION_CENTER_SOURCE.json','site/install/inventory.generated.php'}
  for p,h in facts.items():
   if p not in allowed and sha((ROOT/p).read_bytes())!=h:raise SystemExit('Unrelated source changed: '+p)
  RECEIPT.write_text(json.dumps({'predecessor':BASE,'initial_source_receipt':json.loads(original),'corrections':rows,'after':{p:sha((ROOT/p).read_bytes())for p in facts},'scope':'Preserve existing payment inbox; fail closed before transport if notification audit fails. Initial failed41337802825 retained; no installed pass inferred.'},indent=2)+'\n')
 subprocess.run(['php','tests/notification_namespace_contract.php'],cwd=ROOT,check=True)
 subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
 print('Exact notification runtime corrections verified.')
if __name__=='__main__':main()
