from pathlib import Path
import subprocess, json, os, re
import argparse
parser=argparse.ArgumentParser(description="Run focused development tests only; no SQL or external provider actions.")
parser.add_argument('--root',type=Path,required=True)
parser.add_argument('--out',type=Path,required=True)
args=parser.parse_args();R=args.root.resolve(strict=True);E=args.out.resolve();E.mkdir(parents=True,exist_ok=True)
if not (R/'tests/admin_session_no_reentry.php').is_file():raise SystemExit('Expected patched source was not found.')
results=[]
def run(name,args,report=None,scope='portable PHP; synthetic inputs, no SQL/provider execution'):
    r=subprocess.run(args,cwd=R,capture_output=True,text=True,timeout=40)
    (E/(name+'.stdout')).write_text(r.stdout);(E/(name+'.stderr')).write_text(r.stderr)
    doc={}
    if report and Path(report).exists():
        doc=json.loads(Path(report).read_text())
    if not doc:
        try:doc=json.loads(r.stdout)
        except ValueError: pass
    checks=doc.get('checks',[])
    count=len(checks) if isinstance(checks,list) else checks
    if not count:
        lines=[s for s in r.stdout.splitlines() if s.startswith('PASS ')]
        count=len(lines) if lines else None
    ok=r.returncode==0 and doc.get('passed',True) is not False
    info={'suite':name,'passed':ok,'exit_code':r.returncode,'checks':count,'scope':doc.get('scope',scope)}
    if not ok:info['error']=doc.get('failure',doc.get('error',r.stderr[-2000:]))
    if doc:(E/(name+'.json')).write_text(json.dumps(doc,indent=2)+'\n')
    results.append(info);print(json.dumps(info),flush=True)
for t in ['admin_session_no_reentry','provider_workspace_policy','owner_update_session_policy']:
    run(t,['php','tests/'+t+'.php'])
portable=['provider_package_types','provider_catalog_import','hosting_catalog_document','hosting_checkout_document','hosting_catalog_plan_setup_document','hosting_catalog_variants_document','hosting_catalog_billing','hosting_catalog_comparison','hosting_catalog_login','hosting_checkout_views','hosting_catalog_plan_setup_views','hosting_catalog_variants_contract','hosting_checkout_purchases_guards']
file_reports={'hosting_catalog_document','hosting_checkout_document','hosting_catalog_plan_setup_document'}
for t in portable:
    p=str(E/(t+'.json')) if t in file_reports else None
    run(t,['php','tests/'+t+'.php']+([p] if p else []),p)
license_tests=['software_license_policy','license_financing_policy','software_commerce_contract','software_admin_routing_contract','software_production_gate_contract','software_payment_reversal_contract','finance_subscription_lifecycle','finance_enrollment_sandbox_boundary','finance_charge_attempt_bindings','finance_replacement_outcomes','finance_initial_enrollment_handler','finance_reconcile_snapshot','finance_webhook_isolation']
for t in license_tests:
    run(t,['php','tests/'+t+'.php'])
(E/'suites.json').write_text(json.dumps({'passed':all(x['passed'] for x in results),'suites':results,'known_not_run':['This portable report does not replace the separate SQL and installed-browser jobs.','Provider API transactions, payments, deployment and customer data changes are not attempted.']},indent=2)+'\n')

raise SystemExit(0 if all(x['passed'] for x in results) else 1)
