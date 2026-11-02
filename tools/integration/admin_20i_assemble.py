#!/usr/bin/env python3
"""One-shot, hash-bound source port. Never deployed; never calls a provider.
Expand the saved reviewed candidate into ordinary Git source, then test it.
"""
from pathlib import Path, PurePosixPath
import base64, hashlib, json, os, subprocess, zlib
ROOT = Path(__file__).resolve().parents[2]
HERE = Path(__file__).resolve().parent
BASE = '7bfa55c13437a1ebf2c64165b69cb950cd74f128'
HOSTING = 'd2903bfb81c93eb9da756c128536f23a3d34e822'
BRANCH = 'chatgpt/admin-20i-integration-20261030'
COMPRESSED_SHA = 'db4f968b55afb09def3be933154f2dfa3447d482068d794c40723bf18cfb49df'
RECIPE_SHA = 'fbebb05a1c1828c317f379bb62f260ff7dd707f74db124a51aeadc5e79895716'
OVERLAYS = {
 '4e17aa6f2761eeaba725939fde2781a6acbbff08': [
  'site/admin/controllers/catalog.php','site/admin/views/catalog_import.php',
  'site/app/providers/catalog_import.php','site/database/migrations/provider_catalog_import_migrate.php'],
 '35607238f53873e4a77d5e6946da0b66bcc68415': [
  'site/admin/controllers/support.php','site/app/support/sessions.php',
  'site/database/migrations/support_migrate.php','site/portal/controllers/router.php','site/portal/views/auth.php'],
 '8f44ab8781a2fd3274399c8a50a2634342b65d96': ['site/admin/views/providers.php','site/assets/css/admin.css']
}
def sha(raw): return hashlib.sha256(raw).hexdigest()
def blob_sha(raw): return hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest()
def digest(rows): return sha(json.dumps(rows,separators=(',',':')).encode())
def git(*args): return subprocess.check_output(['git',*args],cwd=ROOT)
def checked_path(value):
 p=PurePosixPath(value)
 if p.is_absolute() or '..' in p.parts or '\\' in value or str(p)!=value:
  raise ValueError('Unsafe source path: '+value)
 if p.parts[0] not in {'site','foundation','tests','docs'}:
  raise ValueError('Source recipe cannot modify workflow/tool policy: '+value)
 target=ROOT/value
 if target.is_symlink() or any(x.is_symlink() for x in target.parents): raise ValueError('Symlink refused')
 return target

def recipe():
 raw=(HERE/'saved-candidate.0').read_bytes()
 # Exact reversible transfer repair for one omitted base64 character. Both the
 # received and repaired Git blob identities are required, then whole-stream SHA.
 if blob_sha(raw)!='3914beb20706846bb5af66dd81138676430ec6d0': raise ValueError('Part 0 drift')
 text=base64.b64encode(raw).decode('ascii')
 if text.count('Nbnc4E267')!=1: raise ValueError('Transfer marker mismatch')
 raw=base64.b64decode(text.replace('Nbnc4E267','Nbnc4bE267',1)+'Q==',validate=True)
 if blob_sha(raw)!='62ac6a9a6cf38e299876c6d05877a43f6ef82f4f': raise ValueError('Part 0 repair mismatch')
 parts=[raw]
 for n,expected in [(1,'4cff0d91e02e1bdee72c1412324f1b83607f71cd'),(2,'b33035d2ecb9a098003ee9c63fca3da439551654')]:
  data=(HERE/f'saved-candidate.{n}').read_bytes()
  if blob_sha(data)!=expected: raise ValueError('Transfer part drift')
  parts.append(data)
 compressed=b''.join(parts)
 if sha(compressed)!=COMPRESSED_SHA: raise ValueError('Compressed source digest differs')
 decompressor=zlib.decompressobj();data=decompressor.decompress(compressed,1_000_000)
 if not decompressor.eof or decompressor.unused_data or decompressor.unconsumed_tail or sha(data)!=RECIPE_SHA:
  raise ValueError('Expanded source digest differs')
 obj=json.loads(data)
 if obj['schema']!=1 or obj['hosting']!=HOSTING or obj['rc_base']!=BASE: raise ValueError('Unexpected provenance')
 return obj

def apply_recipe(obj, read_hosting):
 before=[];after=[];outputs={}
 for path,kind,source,edits in obj['files']:
  target=checked_path(path)
  source=(source or path) if kind!='new' else None
  if source: checked_path(source)
  if kind=='new': data=b''
  elif kind=='hosting': data=read_hosting(source)
  elif kind=='baseline': data=(ROOT/source).read_bytes()
  else: raise ValueError('Unknown recipe kind')
  before.append((path,kind,source,sha(data)))
  last=0
  for lo,hi,text in edits:
   if not isinstance(lo,int) or not isinstance(hi,int) or lo<last or not lo<=hi<=len(data) or not isinstance(text,str):
    raise ValueError('Invalid edit boundary')
   last=hi
  for lo,hi,text in reversed(edits): data=data[:lo]+text.encode('utf-8')+data[hi:]
  if path in outputs: raise ValueError('Duplicate output')
  outputs[path]=data;after.append((path,sha(data)))
 if digest(before)!=obj['before_digest']: raise ValueError('Baseline source drift; no recipe outputs written')
 if digest(after)!=obj['after_digest']: raise ValueError('Result source drift; no recipe outputs written')
 for path,data in outputs.items():
  target=checked_path(path);target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
 return after

# Deliberately migrate eleven obsolete repeated-password NEGATIVE assertions to
# session-revocation/expiry denial. The callback and post-denial state assertions
# remain. No acceptance pin is automatically refreshed or security test removed.
PASSWORD_CASES = {
 'provider_catalog_import_batch_native.php': ["pcbn_deny('Batch requires current owner password',fn()=>provider_catalog_import_batch($hash,'month','BATCH','wrong'));"],
 'provider_package_types_native.php': [
 "ptn_deny('Package types preparation requires current owner password',fn()=>provider_package_types_prepare('wrong'));",
 "ptn_deny('Direct catalog import requires current password',fn()=>provider_catalog_import_create($preview['hash'],'7002','DENIED','month','wrong','package.types'));"],
 'hosting_catalog_native.php': [
 "hcn_deny('Hosting preparation requires the current owner password',fn()=>hosting_catalog_prepare('wrong'));",
 "hcn_deny('Hosting publication requires the current owner password',fn()=>hosting_catalog_publish(hcn_id(20),$product,hcn_id(1),$review['review_sha256'],'wrong'));",
 "hcn_deny('Hosting conversion requires the current owner password',fn()=>hosting_catalog_convert(hcn_id(201),hcn_id(101),$request->fingerprint(),1,'wrong'));",
 "hcn_deny('Offer withdrawal requires current owner password',fn()=>hosting_catalog_withdraw(hcn_id(30),hcn_id(20),$publication->fingerprint(),'Owner withdrew offer','wrong'));"],
 'hosting_checkout_native.php': ["hcc_deny('Checkout setup requires current owner password',fn()=>hosting_checkout_prepare('wrong'));"],
 'hosting_catalog_plan_setup_native.php': ["hps_deny('Current owner password is required for setup',fn()=>hps_create(100,$products[1],$review['review_sha256'],password:'wrong'));"],
 'hosting_catalog_variants_native.php': [
 "hcv_deny('Variant preparation requires current owner password',fn()=>provider_catalog_variant_prepare('wrong'));",
 "hcv_deny('Variant creation requires current owner password',fn()=>provider_catalog_variant_create(...$wrong));"]
}
def migrate_tests():
 changes=[]
 for name,cases in PASSWORD_CASES.items():
  p=ROOT/'tests'/name;s=p.read_text();original=sha(p.read_bytes())
  for index,old in enumerate(cases):
   if s.count(old)!=1: raise ValueError('Expected negative assertion not found: '+name)
   label=old.split("'",2)[1]
   test=old.replace(label,label+' — invalid current session').replace("'wrong'","''")
   invalid="$_SESSION['last_activity']=time()-1801;" if index%2==0 else "$_SESSION['auth_version']=str_repeat('0',32);"
   s=s.replace(old,"$sessionBeforeDenial=$_SESSION; "+invalid+"\n    try { "+test+" } finally { $_SESSION=$sessionBeforeDenial; }",1)
  p.write_text(s);changes.append({'path':'tests/'+name,'before':original,'after':sha(p.read_bytes()),'migrated_denials':len(cases)})
 return changes

def main():
 os.environ['PYTHONDONTWRITEBYTECODE']='1'
 receipt=ROOT/'docs/ADMIN_20I_REMOTE_INTEGRATION.json'
 if receipt.exists():
  obj=json.loads(receipt.read_text())
  if obj.get('recipe_sha256')!=RECIPE_SHA: raise ValueError('Unexpected existing integration')
  print('Source already integrated at '+git('rev-parse','HEAD').decode().strip());return
 if os.environ.get('GITHUB_REF')!='refs/heads/'+BRANCH: raise ValueError('Only the isolated integration branch is allowed')
 if git('status','--porcelain','--untracked-files=all').strip(): raise ValueError('Assembly checkout is not clean')
 git('merge-base','--is-ancestor',BASE,'HEAD')
 workflow_files={p:sha((ROOT/p).read_bytes()) for p in git('ls-files','.github').decode().splitlines()}
 for ref,paths in OVERLAYS.items():
  git('fetch','--no-tags','origin',ref)
  for path in paths: checked_path(path).write_bytes(git('cat-file','blob',ref+':'+path))
 subprocess.run(['python3','tools/build.py','--refresh'],cwd=ROOT,check=True)
 git('fetch','--no-tags','origin',HOSTING)
 obj=recipe();after=apply_recipe(obj,lambda p:git('cat-file','blob',HOSTING+':'+p))
 subprocess.run(['python3','tools/build.py','--refresh'],cwd=ROOT,check=True)
 for item in obj['generated']:
  if sha((ROOT/item['path']).read_bytes())!=item['sha256']: raise ValueError('Saved candidate generated bytes differ: '+item['path'])
 migrated=migrate_tests()
 for path,old,new in [
  ('VERSION','1.26.0\n','1.26.1\n'),
  ('site/config/defaults.php',"const CWP_VERSION = '1.26.0'","const CWP_VERSION = '1.26.1'"),
  ('site/install.php',"const CWP_INSTALLER_BUILD = '1.26.0'","const CWP_INSTALLER_BUILD = '1.26.1'")]:
  p=ROOT/path;s=p.read_text()
  if s.count(old)!=1: raise ValueError('Unexpected candidate version: '+path)
  p.write_text(s.replace(old,new,1))
 subprocess.run(['python3','tools/build.py','--refresh'],cwd=ROOT,check=True)
 if workflow_files!={p:sha((ROOT/p).read_bytes()) for p in workflow_files}: raise ValueError('Security/workflow files changed')
 receipt.write_text(json.dumps({'schema':1,'status':'source integrated; release acceptance pending','candidate_version':'1.26.1','rc_base':BASE,'hosting_source':HOSTING,'recipe_sha256':RECIPE_SHA,'saved_candidate_after_digest':obj['after_digest'],'files_ported':len(after),'negative_session_tests':migrated,'preserved_workflow_sha256':workflow_files,'not_authorized':['live deployment','real charges','provider orders','customer-data changes'],'remaining_gates':['complete native and installed-browser runs','populated upgrade from exact deployed RC and published 1.25','reconcile full-suite contracts without dropping assertions','independent security review']},indent=2)+'\n')
 git('diff','--check')
 print('Reconstructed, verified and expanded source; candidate 1.26.1. Not a release.')
if __name__=='__main__': main()
