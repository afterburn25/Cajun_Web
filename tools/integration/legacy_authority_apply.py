#!/usr/bin/env python3
"""Apply reviewed test-only expectation updates with whole-file identity checks."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
def blob(b):return hashlib.sha1(b'blob '+str(len(b)).encode()+b'\0'+b).hexdigest()
def main():
 if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/1261-legacy-tests-20261030':raise SystemExit('Isolated test-validation branch only')
 records=json.loads((ROOT/'tools/integration/legacy_authority_recipe.json').read_text());chains={}
 if len(records)!=8 or len({r['path'] for r in records})!=8:raise SystemExit('Unexpected recipe scope')
 for r in records:chains[r['path']]=[r]
 follow=ROOT/'tools/integration/legacy_authority_followup.json'
 if follow.exists():
  for r in json.loads(follow.read_text()):
   if r['path'] not in chains or chains[r['path']][-1]['after']!=r['before']:raise SystemExit('Invalid follow-up chain')
   chains[r['path']].append(r)
 out=[]
 for name,chain in chains.items():
  rel=Path(name);p=ROOT/rel
  if not name.startswith('tests/') or rel.is_absolute() or '..' in rel.parts or p.is_symlink() or any(x.is_symlink() for x in p.parents):raise SystemExit('Unsafe test path')
  raw=p.read_bytes();states=[chain[0]['before']]+[r['after'] for r in chain];current=blob(raw)
  if current not in states:raise SystemExit('Test input drift: '+name)
  remaining=chain[states.index(current):];data=raw
  for r in remaining:
   text=data.decode()
   for old,new in r['edits']:
    if text.count(old)!=1:raise SystemExit('Test replacement is not unique: '+name)
    text=text.replace(old,new,1)
   data=text.encode()
   if blob(data)!=r['after']:raise SystemExit('Test output drift: '+name)
  if data!=raw:out.append((p,data))
 for p,data in out:p.write_bytes(data)
 subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
 print('Verified eight exact test files; changed',len(out))
if __name__=='__main__':main()
