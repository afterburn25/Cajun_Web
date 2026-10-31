#!/usr/bin/env python3
"""Idempotent, hash-bound correction chains on the isolated backup candidate only."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/backups-runtime-integration-20261030':raise SystemExit('Isolated backup candidate only')
expected={'site/app/backups/archive.php','tests/backups_native.php','tools/acceptance-contract.json'}
chains={path:[] for path in expected}
recipes=[('backup-runtime-corrections.json',expected),('backup-runtime-followup.json',expected),('backup-runtime-drill-fix.json',{'site/app/backups/archive.php'})]
for name,scope in recipes:
 rows=json.loads((ROOT/'tools/integration'/name).read_text())
 if len(rows)!=len(scope) or {r['path'] for r in rows}!=scope:raise SystemExit('Unexpected correction scope')
 for row in rows:
  chain=chains[row['path']]
  if chain and chain[-1]['after']!=row['before']:raise SystemExit('Broken correction chain')
  chain.append(row)
pending=[]
for name,chain in sorted(chains.items()):
 p=ROOT/name
 if p.is_symlink() or any(q.is_symlink() for q in p.parents):raise SystemExit('Symlink correction refused')
 raw=p.read_bytes();data=raw
 for row in chain:
  actual=hashlib.sha256(data).hexdigest()
  if actual==row['before']:
   text=data.decode()
   for old,new in row['edits']:
    if text.count(old)!=1:raise SystemExit('Non-unique correction: '+name)
    text=text.replace(old,new,1)
   data=text.encode()
   if hashlib.sha256(data).hexdigest()!=row['after']:raise SystemExit('Correction identity mismatch: '+name)
 if hashlib.sha256(data).hexdigest()!=chain[-1]['after']:raise SystemExit('Source drift: '+name)
 if data!=raw:pending.append((p,data))
for p,data in pending:p.write_bytes(data)
subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
subprocess.run(['php','-n','tests/backups/archive_commit_policy.php'],cwd=ROOT,check=True)
print('Verified three reviewed correction chains; changed',len(pending))
