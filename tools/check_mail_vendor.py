"""Verify pinned runtime dependency bytes, versions, licenses and optional clean reproduction."""
from pathlib import Path,PurePosixPath
import argparse,hashlib,json,os,subprocess,tempfile,urllib.request,shutil
ROOT=Path(__file__).resolve().parents[1];BUNDLE=ROOT/'foundation/mail-receiving'
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def checked_path(name):
 p=PurePosixPath(name)
 if p.is_absolute() or '..' in p.parts or '\\' in name or ':' in name or p.as_posix()!=name:raise RuntimeError('Unsafe dependency path')
 if p.parts[0] not in ['vendor','licenses'] and name not in ['composer.json','composer.lock']:raise RuntimeError('Unexpected dependency manifest scope')
 return BUNDLE/name
def check(check_runtime=True):
 m=json.loads((BUNDLE/'vendor-manifest.json').read_text(encoding='utf-8'));lock=json.loads((BUNDLE/'composer.lock').read_text(encoding='utf-8'));config=json.loads((BUNDLE/'composer.json').read_text(encoding='utf-8'))
 assert m['schema']==1 and m['php_platform']=='8.2.0'
 assert config['require']=={'directorytree/imapengine':'1.25.6','zbateson/mail-mime-parser':'4.0.6','symfony/html-sanitizer':'7.4.20'} and config['config']['allow-plugins'] is False and config['config']['platform']['php']=='8.2.0' and config['config']['bin-compat']=='full'
 assert not lock.get('packages-dev') and len(lock['packages'])==len(m['packages'])==40
 expected={p['name']:p for p in m['packages']}
 for p in lock['packages']:
  e=expected[p['name']];assert (e['version'],e['reference'],e['license'])==(p['version'],p['source']['reference'],p['license'])
  assert e['license_files'] and all(name in m['files'] for name in e['license_files'])
 names=set()
 for area in ['vendor','licenses']:
  for p in (BUNDLE/area).rglob('*'):
   assert not p.is_symlink(),str(p)
   if p.is_file():names.add(p.relative_to(BUNDLE).as_posix())
 names.update(['composer.json','composer.lock']);assert names==set(m['files'])
 for name,h in m['files'].items():
  p=checked_path(name);assert p.is_file() and not p.is_symlink() and digest(p)==h,name
  assert not p.name.startswith('.env') and p.suffix not in ['.pem','.key','.p12','.sql'],name
 print(f'PASS: {len(m["packages"])} locked packages; {len(names)} exact dependency/license files; no drift.')
 if check_runtime:check_mirror()
 return m

def check_mirror(required=False):
 mirror=ROOT/'site/includes/foundation/mail-receiving'
 if not mirror.exists():
  assert not required,'Receiving runtime mirror is required'
  return
 sources={p.relative_to(BUNDLE).as_posix():p for p in BUNDLE.rglob('*') if p.is_file()}
 targets={p.relative_to(mirror).as_posix():p for p in mirror.rglob('*') if p.is_file()}
 assert set(sources)==set(targets),'Receiving runtime mirror has missing or extra files'
 for name,p in targets.items():assert not p.is_symlink() and p.read_bytes()==sources[name].read_bytes(),name
 assert not any(p.is_symlink() for p in mirror.rglob('*')),'Receiving runtime contains a symlink'
 print(f'PASS: {len(targets)} receiving runtime files match the canonical bundle byte-for-byte.')
def reproduce(m):
 # A sibling stage remains outside the checkout so Composer cannot embed branch/commit metadata.
 base=ROOT.parent.resolve()
 with tempfile.TemporaryDirectory(prefix='cwp-mail-deps-',dir=base) as temp:
  stage=Path(temp).resolve();assert stage.parent==base and stage.name.startswith('cwp-mail-deps-')
  project=stage/'project';project.mkdir()
  probe=subprocess.run(['git','rev-parse','--show-toplevel'],cwd=project,capture_output=True,text=True)
  assert probe.returncode!=0,'Dependency reproduction must run outside a Git checkout'
  for name in ['composer.json','composer.lock']:shutil.copyfile(BUNDLE/name,project/name)
  data=urllib.request.urlopen(m['composer']['url'],timeout=60).read();assert hashlib.sha256(data).hexdigest()==m['composer']['sha256']
  phar=stage/'composer.phar';phar.write_bytes(data)
  env={k:v for k,v in os.environ.items() if not k.startswith('COMPOSER_')};env['COMPOSER_HOME']=str(stage/'composer-home');env['COMPOSER_CACHE_DIR']=str(stage/'composer-cache');env.pop('COMPOSER_AUTH',None);env.pop('COMPOSER_ROOT_VERSION',None)
  result=subprocess.run(['php',str(phar),'install','--no-dev','--no-plugins','--no-scripts','--prefer-dist','--no-interaction'],cwd=project,env=env,capture_output=True,text=True)
  assert result.returncode==0,result.stderr
  assert 'lock file is not up to date' not in result.stderr and (project/'composer.lock').read_bytes()==(BUNDLE/'composer.lock').read_bytes()
  actual={p.relative_to(project).as_posix():digest(p) for p in (project/'vendor').rglob('*') if p.is_file()}
  expected={n:h for n,h in m['files'].items() if n.startswith('vendor/')}
  differences=sorted(n for n in actual.keys()|expected.keys() if actual.get(n)!=expected.get(n))
  assert not differences,'Clean vendor reproduction differs: '+', '.join(differences)
  print('PASS: clean lock-only dependency install reproduces every vendor byte; no plugins/scripts or customer CLI.')
if __name__=='__main__':
 ap=argparse.ArgumentParser();ap.add_argument('--reproduce',action='store_true');args=ap.parse_args();manifest=check()
 if args.reproduce:reproduce(manifest)
