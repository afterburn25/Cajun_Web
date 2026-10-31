"""Run complete existing suites; require both suite evidence and new refusal state proof."""
from pathlib import Path
import json,os,re,subprocess
ROOT=Path(__file__).resolve().parents[1];OUT=ROOT/'qa/legacy-authority';OUT.mkdir(parents=True,exist_ok=True)
if os.environ.get('CWP_TEST_DATABASE')!='cwp_test' or os.environ.get('CWP_TEST_HOST')!='127.0.0.1':raise SystemExit('Disposable loopback fixtures only')
# Keep all preexisting assertions. This runner does not select or skip cases within a suite.
suites=[('mail_notices_native',1,'mail-notices-native-results.json'),('mail_drafts_native',1,'mail-drafts-native-results.json'),('provider_settings_native',3,'provider-settings-native-results.json'),('provider_checks_native',1,'provider-checks-native-results.json'),('staff_native',1,'staff-native-results.json'),('software_commerce_native',8,None)]
results=[]
for name,expected,report in suites:
 env={**os.environ,'CWP_CURRENT_AUTHORITY_CASE':name}
 with (OUT/(name+'.stdout.log')).open('w') as out,(OUT/(name+'.stderr.log')).open('w') as err:
  try:r=subprocess.run(['php','-d','auto_prepend_file=tests/current_authority_evidence.php','tests/'+name+'.php'],cwd=ROOT,env=env,stdout=out,stderr=err,timeout=600);code=r.returncode
  except subprocess.TimeoutExpired:code=124
 stdout=(OUT/(name+'.stdout.log')).read_text();stderr=(OUT/(name+'.stderr.log')).read_text()
 evidence=OUT/(name+'.php.authority.json');d=json.loads(evidence.read_text()) if evidence.exists() else {};proof=d.get('checks',[])
 diagnostics=d.get('expected_diagnostics',[])
 expected_diagnostics=3 if name=='software_commerce_native' else 0
 good_diagnostics=len(diagnostics)==expected_diagnostics and all(x.get('expected') is True and x.get('status')=='pending' for x in diagnostics)
 good=good_diagnostics and code==0 and stderr=='' and d.get('source_sha')==os.environ['CWP_SOURCE_SHA'] and len(proof)==expected and all(x['state_unchanged'] is True and x['tables']>0 for x in proof)
 if report:
  p=ROOT/'qa'/report;j=json.loads(p.read_text()) if p.exists() else {};checks=j.get('checks',[]);count=len(checks)
  good=good and j.get('passed') is True and count>0 and all(x['passed'] is True for x in checks)
 else:
  match=re.search(r'^Software commerce native: (\d+) checks passed$',stdout,re.M);count=int(match[1]) if match else 0
  good=good and count>100 and count==len(re.findall(r'^PASS ',stdout,re.M))
 result={'suite':name,'passed':bool(good),'exit_code':code,'checks':count,'authority_checks':len(proof),'expected_authority_checks':expected,'stderr_empty':stderr=='','expected_diagnostics':len(diagnostics)}
 results.append(result);print(json.dumps(result),flush=True)
passed=all(x['passed'] for x in results)
(OUT/'results.json').write_text(json.dumps({'passed':passed,'source_sha':os.environ['CWP_SOURCE_SHA'],'scope':'Full existing isolated SQL suites; real owner-role revocation and exact stored-state comparison. No live account or provider action.','suites':results},indent=2)+'\n')
raise SystemExit(0 if passed else 1)
