#!/usr/bin/env python3
"""Apply reviewed test-only expectation updates with whole-file identity checks."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
def blob(b):return hashlib.sha1(b'blob '+str(len(b)).encode()+b'\0'+b).hexdigest()
def main():
 if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/1261-legacy-tests-20261030':raise SystemExit('Isolated test-validation branch only')
 recipe=ROOT/'tools/integration/legacy_authority_recipe.json';records=json.loads(recipe.read_text());out=[]
 if len(records)!=8 or len({r['path'] for r in records})!=8:raise SystemExit('Unexpected recipe scope')
 for item in records:
  rel=Path(item['path']);p=ROOT/rel
  if not item['path'].startswith('tests/') or rel.is_absolute() or '..' in rel.parts or p.is_symlink():raise SystemExit('Unsafe test path')
  raw=p.read_bytes()
  if blob(raw)==item['after']:continue
  if blob(raw)!=item['before']:raise SystemExit('Test input drift: '+item['path'])
  text=raw.decode()
  for old,new in item['edits']:
   if text.count(old)!=1:raise SystemExit('Test replacement is not unique: '+item['path'])
   text=text.replace(old,new,1)
  data=text.encode()
  if blob(data)!=item['after']:raise SystemExit('Test output drift: '+item['path'])
  out.append((p,data))
 for p,data in out:p.write_bytes(data)
 subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
 print('Verified eight exact test files; changed',len(out))
if __name__=='__main__':main()
