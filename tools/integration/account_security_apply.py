#!/usr/bin/env python3
"""Expand exact reviewed client account changes on the isolated development branch only."""
from pathlib import Path
import gzip,hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BASE='a827fca40f5c056d249293b11f07e425f0b71600'
DIGEST='f6b4d537c21aed538588625aaad9f927b9dbcec7b670fa97052c352aab93c17c'
PATHS={'docs/ACCOUNT_SECURITY_SOURCE.json','docs/CLIENT_ACCOUNT_SECURITY.md','site/app/clients/account_security.php','site/portal/controllers/actions.php','site/portal/controllers/router.php','site/portal/views/account.php','site/portal/views/workspace.php','tests/account_integration_contract.py','tests/account_security_browser.py','tests/account_security_native.php','tests/account_security_policy.php','tests/tickets_integration_contract.py'}
def sha(raw):return hashlib.sha256(raw).hexdigest()
def main():
    if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/client-account-security-20261101':raise SystemExit('Isolated account review branch only')
    packed=b''.join((ROOT/f'tools/integration/account-source.{n}.gzpart').read_bytes() for n in range(4))
    if len(packed)!=15587 or sha(packed)!=DIGEST:raise SystemExit('Account recipe identity mismatch')
    raw=gzip.decompress(packed)
    if len(raw)!=49031:raise SystemExit('Unexpected account recipe length')
    recipe=json.loads(raw)
    if recipe['base']!=BASE or len(recipe['records'])!=12 or {r['path'] for r in recipe['records']}!=PATHS:raise SystemExit('Unreviewed account change scope')
    outputs=[]
    for row in recipe['records']:
        p=ROOT/row['path']
        if p.is_symlink() or any(parent.is_symlink() for parent in p.parents):raise SystemExit('Symlink source refused')
        current=p.read_bytes() if p.exists() else None
        if (sha(current) if current is not None else None) not in (row['before'],row['after']):raise SystemExit('Account target drift: '+row['path'])
        original=subprocess.run(['git','show',BASE+':'+row['path']],cwd=ROOT,capture_output=True)
        if row['before'] is None:
            if original.returncode==0 or 'content' not in row:raise SystemExit('Unexpected account addition: '+row['path'])
            data=row['content'].encode()
        else:
            if original.returncode or sha(original.stdout)!=row['before']:raise SystemExit('Account predecessor mismatch: '+row['path'])
            text=original.stdout.decode();limit=len(text)+1
            for start,end,replacement in reversed(row['edits']):
                if not 0<=start<=end<limit:raise SystemExit('Overlapping account source edits')
                text=text[:start]+replacement+text[end:];limit=start
            data=text.encode()
        if sha(data)!=row['after']:raise SystemExit('Account output mismatch: '+row['path'])
        if data!=current:outputs.append((p,data))
    for p,data in outputs:p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data)
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
    print('Verified twelve bounded account source files; changed',len(outputs))
if __name__=='__main__':main()
