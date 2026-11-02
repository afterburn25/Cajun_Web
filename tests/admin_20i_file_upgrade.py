"""Exercise real updater with exact deployed RC files. Local files only, no SQL."""
from pathlib import Path
import hashlib,json,subprocess,os,shutil,zipfile,importlib.util
ROOT=Path(__file__).resolve().parents[1];QA=ROOT/'qa'/'admin-20i-upgrade';QA.mkdir(parents=True,exist_ok=True);checks=[]
BASELINE=ROOT/'test-runtime'/'rc126-original'
if not BASELINE.is_dir() or BASELINE.is_symlink():raise SystemExit('Verified disposable RC snapshot required')
def check(name,ok):
 checks.append({'name':name,'passed':bool(ok)})
 if not ok:raise AssertionError(name)
def digest(data):return hashlib.sha256(data).hexdigest()
spec=importlib.util.spec_from_file_location('build',ROOT/'tools/build.py');mod=importlib.util.module_from_spec(spec)
import sys;sys.path.insert(0,str(ROOT/'tools'));spec.loader.exec_module(mod);mod.generated(False)
check('Candidate version consistently identifies 1.26.1',mod.release_version(ROOT,ROOT/'site')=='1.26.1')
history=json.loads((ROOT/'tools/legacy-release-files.json').read_text())
if any(p.is_symlink() for p in BASELINE.rglob('*')):raise ValueError('Symlink in baseline')
source={p.relative_to(BASELINE).as_posix():p.read_bytes() for p in BASELINE.rglob('*') if p.is_file()}
rc_files={n:digest(b) for n,b in source.items()}
check('All 2956 deployed RC files match the exact recorded inventory',len(rc_files)==2956 and digest(json.dumps(rc_files,sort_keys=True,separators=(',',':')).encode())=='4f0a9b3363c5ade78614a79c4752dae6233234a196fa8765eba34c420effd037')
check('Committed updater history recognizes the exact deployed RC, not merely its version',history['1.26.0']['files']==rc_files and history['1.26.0']['archive_sha256']=='1694d5c94dc88e7d5493b89831d79f4c88c85ee09db3d199fee4a33496f46e44' and history['1.26.0']['source_kind']=='deployed-release-candidate')
files={p.relative_to(ROOT/'site').as_posix():p.read_bytes() for p in (ROOT/'site').rglob('*') if p.is_file()}
payload={n:b for n,b in files.items() if n!='install.php' and not n.startswith(('install/','storage/','upload/'))}
manifest={'version':'1.26.1','source_commit':os.environ.get('CWP_SOURCE_SHA','local-file-test-'+digest(json.dumps({n:digest(b) for n,b in payload.items()},sort_keys=True).encode())),'files':{n:digest(b) for n,b in payload.items()},'shims':{}}
def snapshot(root):return {str(p.relative_to(root)):digest(p.read_bytes()) for p in root.rglob('*') if p.is_file() and not str(p.relative_to(root)).startswith('cwp-update-1.26.1/')}
def fixture(name):
 root=ROOT/'test-runtime'/('rc126-file-ci-'+name);private=ROOT/'test-runtime'/('rc126-private-ci-'+name)
 if root.exists() or private.exists():raise ValueError('Preserve previous fixture evidence')
 root.mkdir(parents=True);private.mkdir();(private/'media').mkdir()
 for n,b in source.items():
  if n=='install.php' or n.startswith('install/'):continue
  p=root/n;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b)
 (root/'config.php').write_text("<?php return ['site_url'=>'http://127.0.0.1:8091/rc-fixture','key'=>'SYNTHETIC-ORIGINAL-CONFIG-KEY','storage'=>"+repr(str(private))+",'db'=>['host'=>'127.0.0.1','port'=>33306,'name'=>'cwp_test','user'=>'cwp_test','pass'=>'local-ci-only','prefix'=>'rc_file_only_']];")
 (root/'.installed.php').write_text('<?php exit; ?>SYNTHETIC ORIGINAL INSTALLATION GUARD')
 for name in ['payment-master.key','provider-master.key','customer-document.php']:(private/name).write_text('SYNTHETIC ORIGINAL PRIVATE BYTES '+name)
 (private/'media'/'customer.txt').write_text('SYNTHETIC CUSTOMER UPLOAD')
 (root/'images'/'customer-art.svg').write_text('<svg><!-- retained synthetic artwork --></svg>')
 stage=root/'cwp-update-1.26.1';stage.mkdir()
 for n,b in payload.items():
  p=stage/'site'/n;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b)
 import base64
 (stage/'manifest.php').write_text("<?php return json_decode(base64_decode('"+base64.b64encode(json.dumps(manifest).encode()).decode()+"'),true);")
 (stage/'legacy-release-files.json').write_text(json.dumps(history))
 return root,private

def run(root,action='apply',stop=0):
 return subprocess.run(['php',str(ROOT/'tests/structure_worker.php'),str(root),action,str(stop)],env={**os.environ,'CWP_TEST_DATABASE':'cwp_test'},capture_output=True,text=True,timeout=35)
try:
 root,private=fixture('resume');before=snapshot(root);private_before=snapshot(private)
 r=run(root,'inspect');check('Exact deployed RC is accepted as starting version',r.returncode==0 and json.loads(r.stdout)['version']=='1.26.0')
 check('Inspection does not change installed files',snapshot(root)==before)
 r=run(root,stop=7);check('Real interrupted migration reports bounded failure',r.returncode!=0 and 'Synthetic interruption' in r.stderr)
 check('Interrupted update leaves maintenance protection and recovery journal',b'Temporary owner-authenticated' in (root/'.htaccess').read_bytes() and (private/'structure-1.26.1/journal.php').is_file())
 r=run(root);check('Exact RC file update resumes to completion',r.returncode==0 and json.loads(r.stdout)['state']=='complete')
 check('Every staged replacement has exact target bytes',all((root/n).read_bytes()==b for n,b in payload.items()))
 check('Configuration and installed identity unchanged',all(digest((root/n).read_bytes())==before[n] for n in ['config.php','.installed.php']))
 check('Original encryption keys private files and customer uploads unchanged',all(digest((private/n).read_bytes())==h for n,h in private_before.items()))
 check('Customer artwork retained',digest((root/'images/customer-art.svg').read_bytes())==before['images/customer-art.svg'])
 r=run(root);check('Completed migration retry is idempotent',r.returncode==0 and json.loads(r.stdout)['state']=='complete')
 r=run(root,'recover');check('Rollback restores the exact original RC filesystem',r.returncode==0 and json.loads(r.stdout)['state']=='rolled-back' and snapshot(root)==before)
 check('Rollback preserves original private bytes and encryption keys',all(digest((private/n).read_bytes())==h for n,h in private_before.items()))
 root,private=fixture('customized');(root/'assets/css/admin.css').write_text('/* SYNTHETIC OWNER CUSTOMIZATION */');before=snapshot(root)
 r=run(root);check('Unrecognized customized shipping file stops update before overwrite',r.returncode!=0 and 'Customized destination preserved' in r.stderr and snapshot(root)==before)
 root,private=fixture('corrupt');(root/'cwp-update-1.26.1/site/admin/index.php').write_text('<?php /* corrupted upload */');before=snapshot(root)
 r=run(root);check('Corrupted staged payload is rejected before file switch',r.returncode!=0 and 'missing or mismatched' in r.stderr and snapshot(root)==before)
except Exception as e:
 checks.append({'name':type(e).__name__+': '+str(e),'passed':False});raise
finally:
 (QA/'file-upgrade-results.json').write_text(json.dumps({'passed':all(c['passed'] for c in checks),'scope':'Actual file updater, interrupted apply, resume and rollback against exact installed RC; no SQL, owner-login or browser acceptance','source_sha':os.environ.get('CWP_SOURCE_SHA'),'source_inventory_sha256':digest(json.dumps(rc_files,sort_keys=True,separators=(',',':')).encode()),'checks':checks},indent=2)+'\n')
print(json.dumps({'passed':True,'checks':len(checks)}))
