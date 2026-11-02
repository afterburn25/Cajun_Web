"""Real isolated SQL policy tests for the master client portal."""
from pathlib import Path
import json,os,subprocess
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'qa'/'master-portal-native';OUT.mkdir(parents=True,exist_ok=True)
image=os.environ['CWP_TEST_IMAGE']
if not image.startswith('cwp-integration-php:') or os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Disposable loopback SQL only')
args=['docker','run','--rm','--network','host','--user',str(os.getuid())+':'+str(os.getgid()),'-v',str(ROOT)+':/workspace','-w','/workspace']
for key in ['CWP_TEST_DATABASE','CWP_TEST_HOST','CWP_TEST_PORT','CWP_TEST_USER','CWP_TEST_PASSWORD','CWP_SOURCE_SHA']:args+=['-e',key]
report=ROOT/'qa/master-portal-native-results.json'
if report.exists():raise SystemExit('Stale report refused')
with (OUT/'stdout.log').open('w') as out,(OUT/'stderr.log').open('w') as err:
    result=subprocess.run(args+[image,'php','tests/master_portal_native.php'],cwd=ROOT,stdout=out,stderr=err,timeout=240)
facts=json.loads(report.read_text());checks=facts.get('checks')
passed=result.returncode==0 and facts.get('passed') is True and isinstance(checks,list) and len(checks)>0 and all(c.get('passed') is True for c in checks)
print(json.dumps({'passed':passed,'checks':len(checks) if isinstance(checks,list) else 0,'source_sha':os.environ.get('CWP_SOURCE_SHA')}))
raise SystemExit(0 if passed else 1)
