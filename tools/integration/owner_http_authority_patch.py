#!/usr/bin/env python3
"""Retain financing and installed-upgrade authorization assertions without repeat-password prompts."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/standalone-acceptance-20261031':raise SystemExit('Isolated standalone correction branch only')
rows=json.loads((ROOT/'tools/integration/owner_http_authority_changes.json').read_text())
if len(rows)!=2 or {r['path'] for r in rows}!={'tests/finance_replacement_receipts_native.php','tests/mail_submission_upgrade_cases.py'}:raise SystemExit('Unexpected correction scope')
helpers={'tests/installed_owner_authority.php':'8a15e41ca8482abc0903481ab20e126ea94035e7ced2e4e2f6db2ca480053b07','tests/installed_owner_authority.py':'4d3895b45ffdcb86f6b4f7df8cb883fff9f13a22c97297e08c7f65a37357bd9d','tests/installed_owner_authority_test.py':'e7140ec913e2d3c8a4e6af7ae5d6a29990c1ecfa6d8e93edd1380117e2b9c763'}
sha=lambda raw:hashlib.sha256(raw).hexdigest()
for path,digest in helpers.items():
 if sha((ROOT/path).read_bytes())!=digest:raise SystemExit('Authority helper drift: '+path)
receipt=ROOT/'docs/OWNER_HTTP_AUTHORITY_SOURCE.json'
if not receipt.exists():
 pending=[]
 for row in rows:
  path=ROOT/row['path'];raw=path.read_bytes()
  if path.is_symlink() or sha(raw)!=row['before']:raise SystemExit('Authority correction source drift: '+row['path'])
  text=raw.decode()
  for old,new in row['edits']:
   if text.count(old)!=1:raise SystemExit('Nonunique authority correction: '+row['path'])
   text=text.replace(old,new,1)
  result=text.encode()
  if sha(result)!=row['after']:raise SystemExit('Authority correction output mismatch: '+row['path'])
  pending.append((path,result))
 for path,result in pending:path.write_bytes(result)
 receipt.write_text(json.dumps({'base':'1a7d1e538d464e7d384573c2372294d4bda68cfe','files':rows,'helpers':helpers,'scope':'Test-only current-owner refusal and exact retention. No application or merchant change; native and installed evidence remain separate.'},indent=2)+'\n')
record=json.loads(receipt.read_text())
if record.get('files')!=rows or record.get('helpers')!=helpers:raise SystemExit('Unknown authority correction receipt')
for row in rows:
 raw=(ROOT/row['path']).read_bytes()
 if sha(raw)!=row['after']:raise SystemExit('Recorded corrected file changed: '+row['path'])
 text=raw.decode()
 for old,new in reversed(row['edits']):
  if text.count(new)!=1:raise SystemExit('Nonunique authority inverse')
  text=text.replace(new,old,1)
 if sha(text.encode())!=row['before']:raise SystemExit('Predecessor behavior changed outside reviewed correction')
out=ROOT/'qa/standalone-source';out.mkdir(parents=True,exist_ok=True)
r=subprocess.run(['python3','-B','tests/installed_owner_authority_test.py'],cwd=ROOT,text=True,capture_output=True,check=True)
doc=json.loads(r.stdout)
if r.stderr or doc.get('passed') is not True or len(doc.get('checks',[]))!=42 or not all(c['passed'] is True for c in doc['checks']):raise SystemExit('Authority fixture contract failed')
(out/'owner-http-fixture.json').write_text(r.stdout)
for path in ['tests/finance_replacement_receipts_native.php','tests/installed_owner_authority.php']:subprocess.run(['php','-l',path],cwd=ROOT,check=True)
subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
print('Verified two retained-source corrections and42 authority-fixture checks; SQL/HTTP are not inferred.')
