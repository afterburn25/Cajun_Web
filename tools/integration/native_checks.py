"""Run real disposable SQL suites, never a configured production installation."""
from pathlib import Path
import json, os, subprocess, sys
ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'qa'/'admin-20i';OUT.mkdir(parents=True,exist_ok=True)
image=os.environ['CWP_TEST_IMAGE']
if not image.startswith('cwp-integration-php:'): raise SystemExit('Unexpected test image')
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1': raise SystemExit('Disposable loopback database required')
suites=[('provider_package_types_native',''),('provider_catalog_import_batch_native','CWP_TEST_CATALOG_BATCH'),('hosting_catalog_native',''),('hosting_checkout_native','CWP_TEST_HOSTING_CHECKOUT'),('hosting_catalog_plan_setup_native','CWP_TEST_HOSTING_PLAN_SETUP'),('hosting_catalog_variants_native','CWP_TEST_HOSTING_VARIANTS'),('hosting_catalog_billing_native','CWP_TEST_HOSTING_BILLING'),('hosting_checkout_purchases_native','CWP_TEST_HOSTING_PURCHASES')]
envargs=[]
for key in ['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD']:
 envargs+=['-e',key]
base=['docker','run','--rm','--network','host','--user',str(os.getuid())+':'+str(os.getgid()),'-v',str(ROOT)+':/workspace','-w','/workspace',*envargs]
results=[]
for suite,flag in suites:
 name='cwp-fixture-'+suite.replace('_','-')
 command=base+['--name',name]+(['-e',flag+'=1'] if flag else [])+[image,'php','tests/'+suite+'.php']
 try:
  with (OUT/(suite+'.stdout')).open('w') as out,(OUT/(suite+'.stderr')).open('w') as err:
   run=subprocess.run(command,cwd=ROOT,stdout=out,stderr=err,timeout=1200)
  result={'suite':suite,'passed':run.returncode==0,'exit_code':run.returncode}
 except subprocess.TimeoutExpired:
  subprocess.run(['docker','rm','-f',name],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
  result={'suite':suite,'passed':False,'error':'Fixture exceeded its 1200-second bound'}
 results.append(result);print(json.dumps(result),flush=True)
(OUT/'native-summary.json').write_text(json.dumps({'passed':all(x['passed'] for x in results),'scope':'Real isolated SQL fixtures; provider responses are synthetic; no merchant/provider transaction','source_sha':os.environ.get('CWP_SOURCE_SHA'),'runtime':image,'database':os.environ.get('CWP_DATABASE_LABEL'),'suites':results},indent=2)+'\n')
sys.exit(0 if all(x['passed'] for x in results) else 1)
