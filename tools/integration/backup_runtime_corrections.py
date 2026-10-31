#!/usr/bin/env python3
"""Idempotent, whole-file-checked correction on the isolated backup candidate only."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/backups-runtime-integration-20261030':raise SystemExit('Isolated backup candidate only')
rows=json.loads((ROOT/'tools/integration/backup-runtime-corrections.json').read_text())
expected={'site/app/backups/archive.php','tests/backups_native.php','tools/acceptance-contract.json'}
if len(rows)!=3 or {r['path'] for r in rows}!=expected:raise SystemExit('Unexpected correction scope')
pending=[]
for row in rows:
 p=ROOT/row['path']
 if p.is_symlink() or any(q.is_symlink() for q in p.parents):raise SystemExit('Symlink correction refused')
 raw=p.read_bytes();actual=hashlib.sha256(raw).hexdigest()
 if actual==row['after']:continue
 if actual!=row['before']:raise SystemExit('Source drift: '+row['path'])
 text=raw.decode()
 for old,new in row['edits']:
  if text.count(old)!=1:raise SystemExit('Non-unique correction: '+row['path'])
  text=text.replace(old,new,1)
 data=text.encode()
 if hashlib.sha256(data).hexdigest()!=row['after']:raise SystemExit('Correction identity mismatch: '+row['path'])
 pending.append((p,data))
for p,data in pending:p.write_bytes(data)
subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
print('Verified three reviewed correction files; changed',len(pending))
