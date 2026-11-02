#!/usr/bin/env python3
"""Apply reviewed test-only corrections on one isolated branch, retaining exact inverses."""
from pathlib import Path
import gzip,hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BRANCH='refs/heads/chatgpt/standalone-acceptance-20261031'
BASE='60e35bd0e22872f1a9473c25b8f6a6ef67900ff0'
BASE_TREE='4691a768bb33e0d7aada2e8786264c46811b5714'
RECEIPT=ROOT/'docs/STANDALONE_ACCEPTANCE_REPAIRS.json'
def digest(raw):return hashlib.sha256(raw).hexdigest()
def main():
    if os.environ.get('GITHUB_REF')!=BRANCH:raise SystemExit('Isolated correction branch only')
    packed=(ROOT/'tools/integration/standalone_acceptance_recipe.json.gz').read_bytes()
    if digest(packed)!='b26e858462f4b6f6e981299768db782dd0c557891f7f410d4d088cffe64137ec':raise SystemExit('Correction recipe changed')
    recipe=json.loads(gzip.decompress(packed))
    if recipe['base']!=BASE or len(recipe['files'])!=11:raise SystemExit('Unexpected reviewed recipe')
    if RECEIPT.exists():
        subprocess.run(['python3','-B','tests/standalone_repair_provenance.py'],cwd=ROOT,check=True)
        print('Recorded standalone corrections already verified');return
    outputs=[]
    for path,row in recipe['files'].items():
        p=ROOT/path
        if not path.startswith('tests/') or '..' in Path(path).parts or p.is_symlink() or any(q.is_symlink() for q in p.parents):raise SystemExit('Unsafe correction path')
        if row['before'] is None:
            if p.exists():raise SystemExit('Unexpected existing correction: '+path)
            result=row['content'].encode()
        else:
            raw=p.read_bytes()
            if digest(raw)!=row['before']:raise SystemExit('Source drift: '+path)
            text=raw.decode()
            for old,new,count in row['edits']:
                if text.count(old)!=count:raise SystemExit('Nonunique correction: '+path)
                text=text.replace(old,new)
            result=text.encode()
        if digest(result)!=row['after']:raise SystemExit('Reviewed output mismatch: '+path)
        outputs.append((p,result))
    # Retain earlier predecessor guards for the two already-tracked harness/upgrade files.
    path='docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json';p=ROOT/path
    before=subprocess.check_output(['git','show',BASE_TREE+':'+path],cwd=ROOT)
    if p.read_bytes()!=before:raise SystemExit('Earlier source receipt drift')
    data=json.loads(before)
    for target in ['tests/organized_update_cases.py','tests/acceptance_artifacts_test.py']:
        current=data['files'][target];row=recipe['files'][target]
        if current['after_sha256']!=row['before']:raise SystemExit('Incorrect earlier receipt link')
        for old,new,count in row['edits']:
            if count!=1:raise SystemExit('Earlier inverse requires unique edits')
            current['edits'].append([old,new])
        current['after_sha256']=row['after']
    after=(json.dumps(data,indent=2)+'\n').encode()
    if digest(after)!='ebe74dd3ebcd1865dea73f3ae6ed87b8d7988e482dbb60e266195ead334c96d2':raise SystemExit('Earlier inverse output mismatch')
    outputs.append((p,after))
    files={}
    for target,row in recipe['files'].items():
        if target=='tests/standalone_repair_provenance.py':
            files[path]={'before':digest(before),'after':digest(after),'edits':[[before.decode(),after.decode(),1]]}
        files[target]=row
    recipe['files']=files
    for p,raw in outputs:p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(raw)
    RECEIPT.write_text(json.dumps(recipe,indent=2)+'\n')
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
    subprocess.run(['git','diff','--exit-code','--','site','foundation','tools/build.py','VERSION','START-HERE.txt','.github/workflows'],cwd=ROOT,check=True)
    subprocess.run(['python3','-B','tests/standalone_repair_provenance.py'],cwd=ROOT,check=True)
    print('Recorded thirteen exact test/receipt corrections; shipping source unchanged.')
if __name__=='__main__':
    main()
    subprocess.run(['python3','-B','tools/integration/owner_http_authority_patch.py'],cwd=ROOT,check=True)
