#!/usr/bin/env python3
"""Apply the reviewed baseline correction on one isolated branch, never main."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BASE='4c4ebae721baf26e3b0340aadaec618bc9d6c0da'
PINS=['4cee1d91befd68d01fbe650b6d57719aeb2a2b52','2a2aa604719fbd7a4c1c90a55fc436988d59378a','5deeaa896f619407451839c97fe50baec525b077']
def blob(data):return hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()
def main():
 if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/combined-baseline-repair-20261031':raise SystemExit('Isolated correction branch only')
 rows=[]
 for i,pin in enumerate(PINS):
  p=ROOT/f'tools/integration/baseline-repair-{i}.json';raw=p.read_bytes()
  if len(raw)>65536 or blob(raw)!=pin:raise SystemExit('Reviewed recipe drift')
  doc=json.loads(raw)
  if doc.get('schema')!=1 or doc.get('base')!=BASE:raise SystemExit('Unknown recipe source')
  rows.extend(doc['records'])
 if len(rows)!=33 or len({r['path'] for r in rows})!=33:raise SystemExit('Unexpected correction inventory')
 shipping={'site/admin/views/providers.php','site/admin/views/provider_workspace/cloud.php','site/install/inventory.generated.php'}
 if {r['path'] for r in rows if r['path'].startswith('site/')}!=shipping:raise SystemExit('Unexpected shipping change')
 pending=[]
 for row in rows:
  name=row['path'];path=Path(name)
  if path.is_absolute() or '..' in path.parts or '\\' in name or not(name.startswith(('site/','tests/','docs/')) or name in ['PROJECT_STATUS.md','SESSION_HANDOFF.md','tools/integration/baseline-acceptance-inverses.json']):raise SystemExit('Unsafe correction path')
  p=ROOT/path
  if p.is_symlink() or any(q.is_symlink() for q in p.parents):raise SystemExit('Symlink correction refused')
  existing=p.read_bytes() if p.exists() else None
  actual=hashlib.sha256(existing).hexdigest() if existing is not None else None
  if actual==row['after']:continue
  if actual!=row['before']:raise SystemExit('Source drift: '+name)
  if existing is None:data=row['content'].encode('utf-8')
  else:
   data=existing;limit=len(data)+1
   for start,end,text in reversed(row['edits']):
    if not 0<=start<=end<limit:raise SystemExit('Overlapping correction offsets')
    data=data[:start]+text.encode('utf-8')+data[end:];limit=start
  if hashlib.sha256(data).hexdigest()!=row['after']:raise SystemExit('Reviewed output mismatch: '+name)
  pending.append((p,data))
 for p,data in pending:p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data)
 subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
 print('Verified all33 reviewed files; applied',len(pending))
if __name__=='__main__':main()
