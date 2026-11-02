#!/usr/bin/env python3
"""Replay the reviewed proposal integration on its isolated branch; no main/deployment writes."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BRANCH='refs/heads/chatgpt/proposals-combined-20261031'
BASE='3930ad96504006c5adcd511f9061da2fc8dec180'
SOURCE='052c42fee5c2e3eb79ee8fa3fcf0bd9350be3aab'
def sha(raw):return hashlib.sha256(raw).hexdigest()
def main():
    if os.environ.get('GITHUB_REF')!=BRANCH:raise SystemExit('Isolated proposal integration branch only')
    data=json.loads((ROOT/'tools/integration/proposals_combined_changes.json').read_text())
    if (data['base'],data['proposal_source'])!=(BASE,SOURCE):raise SystemExit('Unknown reviewed source')
    edits={r['path']:r for r in data['edits']}
    if len(edits)!=13 or len(data['imports'])!=16 or len(data['direct_files'])!=4:raise SystemExit('Unexpected reviewed scope')
    for item in data['imports']:
        original=subprocess.check_output(['git','show',SOURCE+':'+item['path']],cwd=ROOT)
        if sha(original)!=item['sha256']:raise SystemExit('Imported source drift: '+item['path'])
        current=sha((ROOT/item['path']).read_bytes())
        if current not in {item['sha256'],edits.get(item['path'],{}).get('after')}:raise SystemExit('Unreviewed imported bytes: '+item['path'])
    for path,digest in data['direct_files'].items():
        if sha((ROOT/path).read_bytes())!=digest:raise SystemExit('Integration control changed: '+path)
    pending=[]
    for path,item in edits.items():
        p=ROOT/path
        if Path(path).is_absolute() or '..' in Path(path).parts or p.is_symlink() or any(q.is_symlink() for q in p.parents):raise SystemExit('Unsafe integration path')
        raw=p.read_bytes()
        if sha(raw)==item['after']:continue
        if sha(raw)!=item['before']:raise SystemExit('Integration predecessor changed: '+path)
        text=raw.decode('utf-8');previous=len(text)+1
        for start,end,new in reversed(item['edits']):
            if not 0<=start<=end<previous:raise SystemExit('Overlapping correction: '+path)
            text=text[:start]+new+text[end:];previous=start
        after=text.encode('utf-8')
        if sha(after)!=item['after']:raise SystemExit('Incorrect resolved source: '+path)
        pending.append((p,after))
    for p,raw in pending:p.write_bytes(raw)
    subprocess.run(['python3','-B','tools/build.py','--refresh'],cwd=ROOT,check=True)
    manifest=json.loads((ROOT/'docs/PROPOSALS_COMBINED_SOURCE.json').read_text())
    for path,item in manifest['shipping'].items():
        if sha((ROOT/path).read_bytes())!=item['after']:raise SystemExit('Unexpected shipping output: '+path)
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
    subprocess.run(['git','diff','--exit-code','--','.github/workflows'],cwd=ROOT,check=True)
    print('Verified 17 shipping changes; all other combined application source retained.')
if __name__=='__main__':main()
