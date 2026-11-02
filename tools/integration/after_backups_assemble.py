#!/usr/bin/env python3
"""Reconcile pinned current main with the reviewed application; no remote writes here."""
from pathlib import Path
import subprocess,re,json,hashlib,os,tempfile,shutil
R=Path(__file__).resolve().parents[2]
BASE='bff7e8a01e218eb54a98a5f71b1e580d07aaec37'
MAIN='1c44ca120e7768998fafa720b3e1c4c85990c330'
EXPECTED_TREE='92753e50135adb6341ef258a31cfd626214fa6c2'
BRANCH='refs/heads/chatgpt/1261-main-reconcile-20261030'
DRIVER=['tools/integration/after_backups_assemble.py','tests/consolidation_native.py','tests/consolidation_evidence.php','tests/checkout_authority_contract.php','.github/workflows/consolidation-after-backups.yml','docs/CONSOLIDATION_AFTER_BACKUPS.json']
def git(*args,**kw):return subprocess.check_output(['git',*args],cwd=R,**kw).decode().strip()
if os.environ.get('GITHUB_REF')!=BRANCH:raise SystemExit('Pinned integration branch only')
receipt=R/DRIVER[-1]
if receipt.exists():
    doc=json.loads(receipt.read_text())
    if doc.get('main')!=MAIN or doc.get('application')!=BASE:raise SystemExit('Unrecognized integration receipt')
    subprocess.run(['git','merge-base','--is-ancestor',MAIN,'HEAD'],cwd=R,check=True)
    subprocess.run(['git','merge-base','--is-ancestor',BASE,'HEAD'],cwd=R,check=True)
    subprocess.run(['python3','-B','tools/build.py','--refresh'],cwd=R,check=True)
    subprocess.run(['git','diff','--exit-code'],cwd=R,check=True)
    print('Already assembled; exact existing source retained');raise SystemExit(0)
if set(git('diff','--name-only',BASE,'HEAD').splitlines())-set(DRIVER):raise SystemExit('Unreviewed application branch changes')
if git('status','--porcelain'):raise SystemExit('Clean assembly checkout required')
result=subprocess.run(['git','merge','--no-commit','--no-ff',MAIN],cwd=R)
if result.returncode not in (0,1):raise SystemExit('Merge did not reach source reconciliation')
expected={'SESSION_HANDOFF.md','site/admin/controllers/actions.php','site/admin/controllers/router.php','site/install/inventory.generated.php','tests/acceptance_artifacts_test.py','tests/browser_acceptance.py','tests/reset_template_acceptance_contract.py','tools/acceptance-contract.json'}
if set(git('diff','--name-only','--diff-filter=U').splitlines())!=expected:raise SystemExit('Conflict set changed; review required')
def old(ref,p):return subprocess.check_output(['git','show',f'{ref}:{p}'],cwd=R).decode()
def write(p,t):(R/p).write_text(t)
def h(t):return hashlib.sha256(t.encode()).hexdigest()
ours=BASE;theirs=MAIN
# Preserve newer software dispatch while adding the independently reviewed backup action.
p='site/admin/controllers/actions.php'; t=old(ours,p)
s="    if(str_starts_with($action,'studio_')||str_starts_with($action,'software_'))"
assert t.count(s)==1
t=t.replace(s,"    if(str_starts_with($action,'backups_')){backups_admin_action($action);return;}\n"+s);write(p,t)
p='site/admin/controllers/router.php';t=old(ours,p)
s="require_once CWP_CODE_ROOT.'/admin/controllers/domain_registration_drafts.php';";assert t.count(s)==1
t=t.replace(s,s+"\nrequire_once CWP_CODE_ROOT.'/admin/controllers/backups.php';")
s="'activity','deliveries','staff'"; assert t.count(s)==1;t=t.replace(s,"'activity','deliveries','backups','staff'")
# Preserve main backup GET routing at the existing late view dispatcher.
new=old(theirs,p)
for line in new.splitlines():
 if "if($view==='backups')" in line and line not in t:
  pos="if($view==='support')"
  if pos in t:t=t.replace(pos,line+'\n'+pos,1)
  else:raise Exception('No backup view insertion anchor')
write(p,t)
p='tests/browser_acceptance.py';browser=old(ours,p)
hook='            from backups_cases import run_browser as backups_browser\n            backups_browser(globals(),browser)\n'
anchor='            delivery_browser(globals(),browser)\n';assert browser.count(anchor)==1
write(p,browser.replace(anchor,anchor+hook))
p='tests/backups_acceptance_wiring.py';t=(R/p).read_text().replace("expected = '        from backups_cases import run_browser as backups_browser\\n        backups_browser(globals(),browser)\\n'","expected = '            from backups_cases import run_browser as backups_browser\\n            backups_browser(globals(),browser)\\n'");write(p,t)
p='tests/reset_template_acceptance_contract.py';t=old(ours,p).replace("    driver = (root / 'tests/browser_acceptance.py').read_text()","    from backups_acceptance_wiring import predecessor_browser\n    driver = predecessor_browser(root, contract)");write(p,t)
p='tools/acceptance-contract.json';a=json.loads(old(ours,p));b=json.loads(old(theirs,p))
prior=a['native_commands'][:]; cmd='"$PHP_BINARY" tests/backups_native.php';a['native_commands'].insert(a['native_commands'].index('"$PHP_BINARY" tests/delivery_native.php')+1,cmd)
ext=b['non20i_native_command_extension'];ext['predecessor_native_count']=len(prior);ext['predecessor_native_sha256']=hashlib.sha256(json.dumps(prior,separators=(',',':')).encode()).hexdigest();a['non20i_native_command_extension']=ext
a['backups_browser_extension']={'hook':hook,'source_sha256':h((R/'tests/browser_acceptance.py').read_text()),'predecessor_sha256':h(browser)}
write(p,json.dumps(a,indent=2)+'\n')
p='tests/acceptance_artifacts_test.py';t=old(ours,p)
t=t.replace('        retained=predecessor_bytes(ROOT,path,contract)',"        from backups_acceptance_wiring import predecessor_browser\n        retained=predecessor_browser(ROOT,contract).encode() if path=='tests/browser_acceptance.py' else predecessor_bytes(ROOT,path,contract)")
# First undo only the new backup command, then execute the existing software and all older checks.
bt=old(theirs,p);block=bt[bt.index("    ext=contract.get('non20i_native_command_extension')"):bt.index('    from cloud_site_requests_wiring import verify as cloud_request_wiring')]
assert t.count("    software_native=contract['software_commerce_native_command']")==1
t=t.replace("    software_native=contract['software_commerce_native_command']",block+"    software_native=contract['software_commerce_native_command']")
write(p,t)
# Both append-only handoffs retained, visibly labelled as historical.
p='SESSION_HANDOFF.md';s=(R/p).read_text();s=re.sub(r'<<<<<<< HEAD\n(.*?)=======\n(.*?)>>>>>>> [^\n]*\n',lambda m:m[1]+'\n'+m[2],s,flags=re.S);assert '<<<<<<<' not in s;write(p,s.rstrip()+'\n')
write('site/install/inventory.generated.php',old(ours,'site/install/inventory.generated.php'))

def endcall(s,start):
 depth=0;quote=None;escaped=False
 for i in range(start,len(s)):
  c=s[i]
  if quote:
   if escaped:escaped=False
   elif c=='\\':escaped=True
   elif c==quote:quote=None
  elif c in "'\"":quote=c
  elif c=='(':depth+=1
  elif c==')':
   depth-=1
   if depth==0:return i+1
 raise ValueError('Unterminated')
def args(s):
 out=[];start=0;depth=0;quote=None;escaped=False
 for i,c in enumerate(s):
  if quote:
   if escaped:escaped=False
   elif c=='\\':escaped=True
   elif c==quote:quote=None
  elif c in "'\"":quote=c
  elif c in '([{':depth+=1
  elif c in ')]}':depth-=1
  elif c==',' and depth==0:out.append(s[start:i]);start=i+1
 out.append(s[start:]);return out
rows=[]
for p in sorted((R/'tests').glob('*native*.php')):
 s=p.read_text()
 for m in re.finditer(r'\b(?:[a-z]+_(?:deny|denied|bad|refused))\(',s):
  end=endcall(s,m.end()-1);call=s[m.start():end];parts=args(call[call.index('(')+1:-1])
  if len(parts)<2:continue
  label=parts[0]
  if not label.startswith("'") or 'password' not in label.lower() and 'reauth' not in label.lower():continue
  callback=parts[1]
  if not re.search("'(?:wrong|bad|incorrect|bad-password)'",callback) or 'cwp_test_owner_revoked' in callback:continue
  rows.append({'path':str(p.relative_to(R)),'line':s[:m.start()].count('\n')+1,'old':call,'parts':parts})

files={}
for row in rows:files.setdefault(row['path'],[]).append(row)
receipt=[]
for path,rs in files.items():
 p=R/path;original=p.read_text();text=original
 for r in rs:
  parts=r['parts'][:];cb=parts[1].strip();assert cb.startswith('fn()=>'),(path,cb)
  # The original mutation expression and synthetic password value remain unchanged.
  parts[1]='fn()=>cwp_test_owner_revoked('+cb+')'
  label=parts[0][1:-1];parts[0]="'Revoked owner refuses: "+label.replace("'","\\'")+"'"
  if len(parts)>2:
   assert parts[2]=="'Your current password is not correct.'",parts
   parts[2]="'Your account does not have access to this action.'"
  replacement=r['old'].split('(',1)[0]+'('+','.join(parts)+')'
  assert text.count(r['old'])==1,(path,r['old']);text=text.replace(r['old'],replacement,1)
 # Included sub-suites use require_once safely; no behavior is installed in shipping code.
 include="require_once __DIR__.'/current_owner_authority.php';\n"
 anchor='declare(strict_types=1);'
 if include not in text:
  if anchor in text:text=text.replace(anchor,anchor+'\n'+include,1)
  else:
   assert text.startswith('<?php');text=text.replace('<?php','<?php\n'+include,1)
 p.write_text(text)
 receipt.append({'path':path,'before':hashlib.sha256(original.encode()).hexdigest(),'after':hashlib.sha256(text.encode()).hexdigest(),'assertions':len(rs),'old_assertions':[r['old'] for r in rs]})
# Only update direct pins to deliberately revised fixture files; record every reference replacement.
references=[]
for path in ['tools/acceptance-contract.json']:
 p=R/path;text=p.read_text()
 for r in receipt:
  if r['before'] in text:
   references.append({'path':path,'fixture':r['path'],'before':r['before'],'after':r['after'],'occurrences':text.count(r['before'])});text=text.replace(r['before'],r['after'])
 p.write_text(text)

p=R/'tests/current_owner_authority.php';s=p.read_text()
helper='''/** Two legacy SQL-only checkout fixtures use a separately pinned loopback database. */
function cwp_test_sql_only_checkout(array $cfg): bool {
    $db=$cfg['db']??[];$name=$db['name']??'';$prefix=$db['prefix']??'';
    $service=in_array($name,['cwp_checkout_test','cwp_checkout_retry1'],true)
        &&getenv('CWP_TEST_CHECKOUT_STORAGE')==='1'
        &&in_array($prefix,['cscp_main_','cscp_start_','cscp_partial_','cscp_reverse_','cscp_unknown_','cscp_missing_'],true);
    $recurring=in_array($name,['cwp_checkout_test','cwp_checkout_recurring_retry1'],true)
        &&getenv('CWP_TEST_RECURRING_CHECKOUT_STORAGE')==='1'
        &&in_array($prefix,['crbc_main_','crbc_start_','crbc_partial_','crbc_reverse_','crbc_unknown_','crbc_missing_'],true);
    return PHP_SAPI==='cli'&&($service||$recurring)
        &&getenv('CWP_TEST_DATABASE')===$name&&getenv('CWP_TEST_HOST')==='127.0.0.1'
        &&getenv('CWP_TEST_PORT')==='43319'&&($db['host']??'')==='127.0.0.1'&&($db['port']??0)===43319
        &&($cfg['storage']??null)===''&&($cfg['key']??null)==='';
}

'''
s=s.replace('/** Hash all rows',helper+'/** Hash all rows',1)
s=s.replace("    if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'\n        ||($cfg['db']['name']??'')!=='cwp_test'||!in_array", "    $sqlOnly=cwp_test_sql_only_checkout($cfg);\n    if(PHP_SAPI!=='cli'||(!$sqlOnly&&(getenv('CWP_TEST_DATABASE')!=='cwp_test'||($cfg['db']['name']??'')!=='cwp_test'))||!in_array",1)
s=s.replace("    ksort($tables);\n    $storage=", "    ksort($tables);\n    if($sqlOnly)return ['tables'=>$tables,'files'=>[]];\n    $storage=",1)
p.write_text(s)

if len(rows)!=106 or len(receipt)!=60:raise SystemExit('Legacy assertion inventory changed')
subprocess.run(['python3','-B','tools/build.py','--refresh'],cwd=R,check=True)
subprocess.run(['git','diff','--check'],cwd=R,check=True)
subprocess.run(['git','add','--all'],cwd=R,check=True)
index=R/git('rev-parse','--git-path','index')
with tempfile.TemporaryDirectory() as temp:
    alternate=Path(temp)/'index';shutil.copyfile(index,alternate)
    env={**os.environ,'GIT_INDEX_FILE':str(alternate)}
    subprocess.run(['git','update-index','--force-remove','--',*DRIVER],cwd=R,env=env,check=True)
    actual=git('write-tree',env=env)
    if actual!=EXPECTED_TREE:raise SystemExit('Combined source differs from locally reviewed tree: '+actual)
(R/DRIVER[-1]).write_text(json.dumps({'schema':1,'main':MAIN,'application':BASE,'reviewed_tree_without_driver':EXPECTED_TREE,'scope':'Source integration; full runtime and installed acceptance still required. No live action or replacement of delivered 1.26.1 ZIPs.','owner_authority_assertions':106,'fixture_files':receipt,'pin_updates':references,'remaining_groups':['company contacts and accounts','tickets and notifications','email workspace','advanced StackCP delegation','versioned proposals','payment-link privacy']},indent=2)+'\n')
subprocess.run(['git','add','--',DRIVER[-1]],cwd=R,check=True)
print('Verified pinned combined tree and all106 retained operation callbacks')
