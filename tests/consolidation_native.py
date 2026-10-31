"""Run whole legacy suites in fresh isolated SQL namespaces; retain every failure."""
from pathlib import Path
import argparse,json,os,shutil,subprocess,time
ROOT=Path(__file__).resolve().parents[1];OUT=ROOT/'qa/consolidation-native';OUT.mkdir(parents=True,exist_ok=True)
p=argparse.ArgumentParser();p.add_argument('--shard',type=int,required=True);a=p.parse_args()
if a.shard not in range(3):raise SystemExit('Unknown test shard')
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Disposable loopback SQL only')
receipt=json.loads((ROOT/'docs/CONSOLIDATION_AFTER_BACKUPS.json').read_text())
parents={'directory_listing_controls_recovery_native':'provider_directory_listing_controls_native','force_ssl_controls_recovery_native':'provider_force_ssl_controls_native','hosting_fulfillment_native_cases':'recurring_billing_native','service_orders_native_cases':'recurring_billing_native'}
cases={}
for r in receipt['fixture_files']:
 name=Path(r['path']).stem;name=parents.get(name,name);cases[name]=cases.get(name,0)+r['assertions']
# Retain the seven previously verified native suites, not just this new correction batch.
for name,count in [('mail_notices_native',1),('mail_drafts_native',1),('provider_settings_native',3),('provider_checks_native',1),('staff_native',1),('software_commerce_native',8),('mail_submissions_native',1)]:
 if name in cases:raise SystemExit('Unexpected overlapping predecessor case')
 cases[name]=count
if len(cases)!=63 or sum(cases.values())!=122:raise SystemExit('Native suite inventory changed')
selected=sorted(cases)[a.shard::3]
reset=r'''
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
$name=getenv('CWP_TEST_DATABASE');$port=(int)getenv('CWP_TEST_PORT');
if(!(($name==='cwp_test'&&$port===33306)||($name==='cwp_checkout_test'&&$port===43319)))exit(2);
$p=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$name,getenv('CWP_TEST_USER'),getenv('CWP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$rows=$p->query('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
foreach($rows as $t)if(!preg_match('/^[a-zA-Z0-9_]+$/D',$t))exit(2);
$p->exec('SET SESSION FOREIGN_KEY_CHECKS=0');try{foreach($rows as $t)$p->exec('DROP TABLE `'.$t.'`');}finally{$p->exec('SET SESSION FOREIGN_KEY_CHECKS=1');}
$cfg=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath('test-runtime/shared-private'),'key'=>str_repeat('a',64),'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>$name,'user'=>getenv('CWP_TEST_USER'),'pass'=>getenv('CWP_TEST_PASSWORD'),'prefix'=>'ci_']];
if(!$cfg['storage'])exit(2);file_put_contents('test-runtime/config-fixture.php','<?php return '.var_export($cfg,true).';');
'''
flags={'provider_cloud_site_requests_native':'CWP_TEST_CLOUD_SITE_REQUESTS','provider_password_reset_template_native':'CWP_TEST_PASSWORD_RESET_TEMPLATE','service_plans_checkout_native':'CWP_TEST_CHECKOUT_STORAGE','recurring_billing_checkout_native':'CWP_TEST_RECURRING_CHECKOUT_STORAGE'}
image=os.environ['CWP_TEST_IMAGE']
if image!='cwp-consolidation-php:8.4':raise SystemExit('Unexpected runtime image')
base=['docker','run','--rm','--init','-i','--network','host','--user',f'{os.getuid()}:{os.getgid()}','-v',str(ROOT)+':/workspace','-w','/workspace']
keys=['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD','CWP_SOURCE_SHA','CWP_CONSOLIDATION_CASE',*flags.values()]
for key in keys:base+=['-e',key]
results=[]
for name in selected:
 runtime=ROOT/'test-runtime'
 if runtime.is_symlink():raise SystemExit('Unsafe fixture path')
 # All generated files belong to this runner; source and prior evidence are not removed.
 if runtime.exists():shutil.rmtree(runtime)
 (runtime/'shared-private').mkdir(parents=True,mode=0o700)
 env={**os.environ,'CWP_CONSOLIDATION_CASE':name,**{flag:'0' for flag in flags.values()}}
 if name in flags:env[flags[name]]='1'
 if name in ('service_plans_checkout_native','recurring_billing_checkout_native'):env.update(CWP_TEST_DATABASE='cwp_checkout_test',CWP_TEST_PORT='43319')
 target=OUT/(name+'.php.json')
 if target.exists():raise SystemExit('Refuse stale fixture evidence')
 started=time.monotonic();code=125;error=None;container='cwp-consolidation-'+str(a.shard)+'-'+name.replace('_','-')
 try:
  subprocess.run(base+[image,'php','-r',reset],cwd=ROOT,env=env,check=True,capture_output=True,timeout=90)
  with (OUT/(name+'.stdout.log')).open('w') as out,(OUT/(name+'.stderr.log')).open('w') as err:
   r=subprocess.run(base+['--name',container,image,'php','-d','auto_prepend_file=tests/consolidation_evidence.php','tests/'+name+'.php'],cwd=ROOT,env=env,stdout=out,stderr=err,timeout=1200);code=r.returncode
 except subprocess.TimeoutExpired:
  code=124;error='The complete suite exceeded its retained 1200-second bound';subprocess.run(['docker','rm','-f',container],capture_output=True)
 except subprocess.CalledProcessError as exc:error='Fixture setup failed: '+exc.stderr.decode(errors='replace')[-2000:]
 facts={}
 try:facts=json.loads(target.read_text())
 except (ValueError,OSError):error=error or 'Missing or malformed complete-suite evidence'
 checks=facts.get('checks',[]);proof=facts.get('authority',[]);diagnostics=facts.get('expected_diagnostics',[])
 stderr=(OUT/(name+'.stderr.log')).read_text() if (OUT/(name+'.stderr.log')).exists() else 'Missing stderr evidence'
 valid_checks=isinstance(checks,list) and len(checks)>0 and all(isinstance(c,dict) and c.get('passed') is True for c in checks)
 valid_proof=len(proof)>=cases[name] and all(x.get('state_unchanged') is True and x.get('tables',0)>0 for x in proof)
 expected=3 if name=='software_commerce_native' else 0
 valid_diagnostics=len(diagnostics)==expected and all(x.get('expected') is True and x.get('status')=='pending' for x in diagnostics)
 passed=code==0 and not error and stderr=='' and valid_checks and valid_proof and valid_diagnostics and facts.get('source_sha')==os.environ['CWP_SOURCE_SHA']
 row={'suite':name,'passed':bool(passed),'exit_code':code,'checks':len(checks),'authority_checks':len(proof),'expected_min_authority_checks':cases[name],'stderr_empty':stderr=='','seconds':round(time.monotonic()-started,2),'error':error}
 results.append(row);print(json.dumps(row),flush=True)
 (OUT/'summary.json').write_text(json.dumps({'passed':all(x['passed'] for x in results) and len(results)==len(selected),'source_sha':os.environ['CWP_SOURCE_SHA'],'shard':a.shard,'expected_suites':len(selected),'scope':'Full isolated SQL suites; no production data, real payment, provider request or delivery','suites':results},indent=2)+'\n')
raise SystemExit(0 if all(x['passed'] for x in results) and len(results)==len(selected) else 1)
