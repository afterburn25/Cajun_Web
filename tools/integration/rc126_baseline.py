#!/usr/bin/env python3
"""Reconstruct the exact deployed RC; never infer identity from its version label."""
from pathlib import Path, PurePosixPath
import argparse, hashlib, json, os, shutil, subprocess, tempfile
ROOT=Path(__file__).resolve().parents[2]
BASE='7bfa55c13437a1ebf2c64165b69cb950cd74f128'
MAP_SHA='4f0a9b3363c5ade78614a79c4752dae6233234a196fa8765eba34c420effd037'
ZIP_SHA='1694d5c94dc88e7d5493b89831d79f4c88c85ee09db3d199fee4a33496f46e44'
OVERLAYS={
 '4e17aa6f2761eeaba725939fde2781a6acbbff08':['site/admin/controllers/catalog.php','site/admin/views/catalog_import.php','site/app/providers/catalog_import.php','site/database/migrations/provider_catalog_import_migrate.php'],
 '35607238f53873e4a77d5e6946da0b66bcc68415':['site/admin/controllers/support.php','site/app/support/sessions.php','site/database/migrations/support_migrate.php','site/portal/controllers/router.php','site/portal/views/auth.php'],
 '8f44ab8781a2fd3274399c8a50a2634342b65d96':['site/admin/views/providers.php','site/assets/css/admin.css']
}
def sha(raw):return hashlib.sha256(raw).hexdigest()
def inventory(site):
 files={}
 for p in sorted(site.rglob('*')):
  if p.is_symlink():raise ValueError('Baseline symlink refused')
  if p.is_file():files[p.relative_to(site).as_posix()]=sha(p.read_bytes())
 if len(files)!=2956 or sha(json.dumps(files,sort_keys=True,separators=(',',':')).encode())!=MAP_SHA:
  raise ValueError('Reconstructed RC does not match the downloaded deployed artifact')
 return files

def record(site,snapshot,write_history):
 files=inventory(site)
 entry={'archive_sha256':ZIP_SHA,'source_kind':'deployed-release-candidate','workflow_run':41077725683,'artifact_id':12988737538,'files':files}
 history_path=ROOT/'tools/legacy-release-files.json'
 history=json.loads(history_path.read_text())
 if write_history:
  old=history.get('1.26.0')
  if old is not None and old!=entry:raise ValueError('Existing 1.26.0 identity differs; refuse overwrite')
  if old is None:
   history['1.26.0']=entry
   # All earlier release entries stay byte-equivalent as JSON objects.
   history_path.write_text(json.dumps(history,indent=2)+'\n')
 if snapshot:
  destination=ROOT/snapshot
  parts=PurePosixPath(snapshot).parts
  if not parts or parts[0]!='test-runtime' or '..' in parts or destination.exists():raise ValueError('A new test-runtime snapshot is required')
  if any(x.is_symlink() for x in destination.parents):raise ValueError('Snapshot symlink refused')
  destination.parent.mkdir(parents=True,exist_ok=True);shutil.copytree(site,destination)
 print(json.dumps({'verified':True,'files':len(files),'inventory_sha256':MAP_SHA,'archive_sha256':ZIP_SHA,'history_recorded':write_history,'snapshot':snapshot}))

def main():
 parser=argparse.ArgumentParser();parser.add_argument('--record',action='store_true');parser.add_argument('--snapshot');parser.add_argument('--local-baseline',type=Path)
 args=parser.parse_args()
 if args.local_baseline:
  # Local diagnostics use mounted original files, subject to the identical complete digest.
  record(args.local_baseline.resolve(strict=True),args.snapshot,args.record);return
 env={**os.environ,'PYTHONDONTWRITEBYTECODE':'1'}
 for ref in [BASE,*OVERLAYS]:subprocess.run(['git','fetch','--no-tags','origin',ref],cwd=ROOT,check=True,env=env)
 with tempfile.TemporaryDirectory(prefix='cwp-verified-rc-',dir=os.environ.get('RUNNER_TEMP')) as tmp:
  work=Path(tmp)/'source'
  subprocess.run(['git','worktree','add','--detach',str(work),BASE],cwd=ROOT,check=True,env=env)
  try:
   for ref,paths in OVERLAYS.items():
    for path in paths:
     (work/path).write_bytes(subprocess.check_output(['git','cat-file','blob',ref+':'+path],cwd=ROOT))
   subprocess.run(['python3','-c','import sys;sys.path.insert(0,"tools");import build;build.generated(True);assert build.release_version(build.ROOT,build.SITE)=="1.26.0"'],cwd=work,check=True,env=env)
   record(work/'site',args.snapshot,args.record)
  finally:
   subprocess.run(['git','worktree','remove','--force',str(work)],cwd=ROOT,check=True,env=env)
if __name__=='__main__':main()
