#!/usr/bin/env python3
"""Apply a bounded test-only patch; no main ref, shipping code or live operation."""
from pathlib import Path
import gzip,hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BRANCH='refs/heads/chatgpt/browser-owner-acceptance-20261101'
BASE='a827fca40f5c056d249293b11f07e425f0b71600'
DIGEST='cae6ed58d9df62b63f7865c86570012adaf51ec43594c9db130ba143f0d0666c'
PATHS={'docs/BROWSER_OWNER_FIXTURE_REPAIRS.json','tests/browser_owner_fixture_provenance.py','tests/owner_browser_forms.py','tests/owner_browser_forms_test.py','tests/owner_session_installed.py','tests/staff_cases.py','tests/structure_helpers.py'}
DERIVED={'docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json','docs/STANDALONE_ACCEPTANCE_REPAIRS.json','tests/acceptance_artifacts_test.py'}
def sha(raw):return hashlib.sha256(raw).hexdigest()
def main():
 if os.environ.get('GITHUB_REF')!=BRANCH:raise SystemExit('Isolated test-repair branch only')
 packed=b''.join((ROOT/('tools/integration/owner-browser-recipe.'+str(i)+'.gzpart')).read_bytes() for i in range(4))
 if len(packed)!=9959 or sha(packed)!=DIGEST:raise SystemExit('Reviewed recipe changed')
 raw=gzip.decompress(packed)
 if len(raw)!=35270:raise SystemExit('Unexpected recipe size')
 data=json.loads(raw)
 if data['base']!=BASE or {r['path'] for r in data['records']}!=PATHS or len(data['records'])!=7 or set(data['derived'])!=DERIVED:raise SystemExit('Unreviewed correction scope')
 rows={r['path']:r for r in data['records']};facts={**rows,**data['derived']};current={}
 for path in facts:
  p=ROOT/path
  if p.is_symlink() or any(parent.is_symlink() for parent in p.parents):raise SystemExit('Symlink source refused')
  current[path]=p.read_bytes() if p.exists() else None
 if all(current[p] is not None and sha(current[p])==row['after'] for p,row in facts.items()):
  print('All ten reviewed test corrections already recorded.');return
 for path,row in facts.items():
  digest=sha(current[path]) if current[path] is not None else None
  if digest!=row['before']:raise SystemExit('Source drift before mutation: '+path)
 outputs={}
 for path,row in rows.items():
  if 'content' in row:result=row['content'].encode()
  else:
   result=current[path];limit=len(result)+1
   for start,end,text in reversed(row['edits']):
    if not 0<=start<=end<limit:raise SystemExit('Overlapping source edits')
    result=result[:start]+text.encode()+result[end:];limit=start
  outputs[path]=result
 path='tests/acceptance_artifacts_test.py';old="        if path=='tests/stackcp_access_read_snapshot_native.php':"
 new="        if path in ('tests/structure_helpers.py','tests/staff_cases.py'):\n            from browser_owner_fixture_provenance import predecessor as browser_owner_predecessor\n            retained=browser_owner_predecessor(ROOT,path,retained)\n"+old
 text=current[path].decode()
 if text.count(old)!=1:raise SystemExit('Nonunique harness insertion')
 outputs[path]=text.replace(old,new,1).encode()
 path='docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json';receipt=json.loads(current[path]);row=receipt['files']['tests/acceptance_artifacts_test.py']
 if row['after_sha256']!=sha(current['tests/acceptance_artifacts_test.py']):raise SystemExit('Earlier harness receipt mismatch')
 row['edits'].append([old,new]);row['after_sha256']=sha(outputs['tests/acceptance_artifacts_test.py']);outputs[path]=(json.dumps(receipt,indent=2)+'\n').encode()
 path='docs/STANDALONE_ACCEPTANCE_REPAIRS.json';receipt=json.loads(current[path])
 for name in ('tests/acceptance_artifacts_test.py','docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json'):
  row=receipt['files'][name]
  if row['after']!=sha(current[name]):raise SystemExit('Earlier standalone receipt mismatch')
  row['edits'].append([current[name].decode(),outputs[name].decode(),1]);row['after']=sha(outputs[name])
 outputs[path]=(json.dumps(receipt,indent=2)+'\n').encode()
 for path,result in outputs.items():
  if sha(result)!=facts[path]['after']:raise SystemExit('Reviewed output differs: '+path)
 for path,result in outputs.items():
  p=ROOT/path;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(result)
 subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
 print('Expanded ten exact test/receipt files. No shipping file changed; installed results remain separate.')
if __name__=='__main__':main()
