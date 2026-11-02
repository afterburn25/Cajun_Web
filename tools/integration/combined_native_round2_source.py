#!/usr/bin/env python3
"""Exact-source test corrections; no application, permission or merchant changes."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BASE='5b2cf2be9fafb54c74a736054a08403d2c46122d'
RECEIPT=ROOT/'docs/COMBINED_NATIVE_ROUND2.json'
changes={}
def sha(raw):return hashlib.sha256(raw).hexdigest()
def change(path,old,new):
 p=ROOT/path
 if path not in changes:
  raw=subprocess.check_output(['git','show',BASE+':'+path],cwd=ROOT)
  if p.is_symlink() or p.read_bytes()!=raw:raise SystemExit('Source drift: '+path)
  changes[path]={'before_sha256':sha(raw),'edits':[]}
 text=p.read_text()
 if text.count(old)!=1:raise SystemExit('Nonunique correction: '+path+' '+old[:70])
 p.write_text(text.replace(old,new,1));changes[path]['edits'].append([old,new])
def wrap(path,old,new):change(path,old,new)
def main():
 if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/combined-acceptance-repair-20261031':raise SystemExit('Isolated correction branch only')
 if RECEIPT.exists():
  data=json.loads(RECEIPT.read_text())
  if data['base_tree']!=BASE:raise SystemExit('Unknown source receipt')
  for path,r in data['files'].items():
   if sha((ROOT/path).read_bytes())!=r['after_sha256']:raise SystemExit('Recorded correction changed: '+path)
  print('Round-two corrections already verified');return
 for name in ['appointments_native','reviews_native']:
  change('tests/'+name+'.php',"require_once __DIR__.'/current_owner_authority.php';","require_once __DIR__.'/current_owner_authority.php';\nrequire_once __DIR__.'/current_staff_permission.php';")
 change('tests/appointments_native.php',"a_deny('Revoked owner refuses: Confirm needs current password',fn()=>cwp_test_owner_revoked(fn()=>appointments_mutate($lead,1,'confirm',null,null,'wrong')))","a_deny('Current delegated staff permission is required to confirm',fn()=>cwp_test_staff_permission_revoked('website.manage',fn()=>appointments_mutate($lead,1,'confirm',null,null,$password)))")
 change('tests/reviews_native.php',"r_deny('Revoked owner refuses: Delivery needs current password',fn()=>cwp_test_owner_revoked(fn()=>reviews_deliver($lead,7,$now,'email','Synthetic mail reference','wrong')))","r_deny('Current delegated staff permission is required for delivery',fn()=>cwp_test_staff_permission_revoked('website.manage',fn()=>reviews_deliver($lead,7,$now,'email','Synthetic mail reference',$password)))")
 change('tests/recurring_billing_checkout_native.php',"    rcn_owner();service_plans_migrate($password);service_plans_checkout_migrate($password);recurring_billing_migrate($password);","""    rcn_owner();$retainedClients=all('SELECT * FROM '.table('studio_clients').' ORDER BY id');$retainedJobs=all('SELECT * FROM '.table('studio_jobs').' ORDER BY id');
    payments_migrate($password);
    rcn_ok('Current payment prerequisite is migrated with retained clients and jobs '.$prefix,payments_ready()&&setting('payments_schema_version')==='2'&&all('SELECT * FROM '.table('studio_clients').' ORDER BY id')===$retainedClients&&all('SELECT * FROM '.table('studio_jobs').' ORDER BY id')===$retainedJobs);
    service_plans_migrate($password);service_plans_checkout_migrate($password);recurring_billing_migrate($password);""")
 change('tests/provider_catalog_import_native.php',"function pim_ok(","""/** The existing-inventory fixture must explicitly select that source in the revised UI. */
function pim_render_inventory():string {$get=$_GET;$post=$_POST;$_GET=['import_source'=>'packages.list'];$_POST=[];ob_start();try{provider_catalog_import_admin_view(admin_user());return (string)ob_get_contents();}finally{ob_end_clean();$_GET=$get;$_POST=$post;}}
function pim_ok(""")
 change('tests/provider_catalog_import_native.php',"ob_start();provider_catalog_import_admin_view(admin_user());$changedHtml=ob_get_clean();","$changedHtml=pim_render_inventory();")
 change('tests/provider_catalog_import_native.php',"ob_start();provider_catalog_import_admin_view(admin_user());$html=ob_get_clean();","$html=pim_render_inventory();")
 change('tests/provider_inventory_cloud_link_cases.php',"pi_deny('Cloud assignment refuses wrong current password',fn()=>provider_cloud_link($id,1201,1,7,$rid,'wrong'));","pi_deny('Cloud assignment refuses a revoked owner without changing stored state',fn()=>cwp_test_owner_revoked(fn()=>provider_cloud_link($id,1201,1,7,$rid,$password)));")
 change('tests/service_orders_links_cases.php',"rb_deny('Order association setup requires owner password',fn()=>hosting_order_links_migrate('wrong'));","rb_deny('Order association setup requires current owner authority',fn()=>cwp_test_owner_revoked(fn()=>hosting_order_links_migrate($password)));")
 change('tests/service_orders_links_cases.php',"rb_deny('Association requires current owner password',fn()=>hosting_order_links_create($o->data()['id'],1,$o->revision(),$o->fingerprint(),rb_id(30100),$solPackage,$solProfile,1,42,'wrong'));","rb_deny('Association requires current owner authority',fn()=>cwp_test_owner_revoked(fn()=>hosting_order_links_create($o->data()['id'],1,$o->revision(),$o->fingerprint(),rb_id(30100),$solPackage,$solProfile,1,42,$password)));")
 change('tests/service_orders_link_views_cases.php',"    foreach([['confirm'=>''],['mapping_only'=>''],['agreement_id'=>rb_id(99999)],['order_hash'=>str_repeat('a',64)],['profile_revision'=>'1'],['current_password'=>'wrong']] as $i=>$bad)","    $_POST=$input;rb_deny('Preparation form refuses revoked owner without changing stored state',fn()=>cwp_test_owner_revoked(fn()=>service_orders_form_action('service_plans_order_map_service',$viewAgreement,1,true)));\n    foreach([['confirm'=>''],['mapping_only'=>''],['agreement_id'=>rb_id(99999)],['order_hash'=>str_repeat('a',64)],['profile_revision'=>'1']] as $i=>$bad)")
 change('tests/hosting_fulfillment_native_cases.php',"        foreach([1=>2,3=>str_repeat('a',64),4=>str_repeat('b',64),5=>'wrong'] as $field=>$value)","        rb_deny('Local recovery requires current owner authority',fn()=>cwp_test_owner_revoked(fn()=>hosting_fulfillment_recover(...$recover)));\n        foreach([1=>2,3=>str_repeat('a',64),4=>str_repeat('b',64)] as $field=>$value)")
 change('tests/hosting_fulfillment_view_cases.php',"rb_deny('Preparation wrapper requires owner password',fn()=>hosting_fulfillment_prepare('wrong'));","rb_deny('Preparation wrapper requires current owner authority',fn()=>cwp_test_owner_revoked(fn()=>hosting_fulfillment_prepare($password)));")
 change('tests/hosting_fulfillment_confirmation_cases.php',"rb_deny('Status history preparation requires the owner password',fn()=>hosting_fulfillment_confirmation_prepare('wrong'));","rb_deny('Status history preparation requires current owner authority',fn()=>cwp_test_owner_revoked(fn()=>hosting_fulfillment_confirmation_prepare($password)));")
 change('tests/hosting_fulfillment_confirmation_cases.php',"        foreach([[1,2],[2,rb_id(999999)],[3,str_repeat('a',64)],[4,'wrong']] as [$index,$value]){","        rb_deny('Status confirmation requires current owner authority',fn()=>cwp_test_owner_revoked(fn()=>hosting_fulfillment_confirm(...$args)));\n        foreach([[1,2],[2,rb_id(999999)],[3,str_repeat('a',64)]] as [$index,$value]){")
 change('tests/consolidation_native.py',"cases['provider_password_reset_template_native']+=1","cases['provider_password_reset_template_native']+=1\n# Retain the nested cloud/order/fulfillment refusals as actual authority checks.\ncases['provider_inventory_native']+=1\ncases['recurring_billing_native']+=7")
 change('tests/organized_update_cases.py',"    if version=='1.24.0':\n        settings=url+'/admin/index.php?view=provider-settings'\n        response=session.get(settings)","    if version=='1.24.0':\n        settings=url+'/admin/index.php?view=provider-settings&section=services'\n        response=session.get(settings)")
 change('tests/consolidation_evidence.php'," $out=__DIR__.'/../qa/consolidation-native';@mkdir($out,0700,true);"," $lastError=error_get_last();$out=__DIR__.'/../qa/consolidation-native';if(!is_dir($out)&&!mkdir($out,0700,true))throw new RuntimeException('Cannot create private test evidence directory.');")
 change('tests/consolidation_evidence.php',"'final_error'=>error_get_last()","'final_error'=>$lastError")
 contract=json.loads((ROOT/'tools/acceptance-contract.json').read_text());old=contract['provider_catalog_import']['source_sha256']['tests/provider_catalog_import_native.php']
 change('tools/acceptance-contract.json',old,sha((ROOT/'tests/provider_catalog_import_native.php').read_bytes()))
 # Extend, rather than discard, the exact predecessor inverses already checked by CI.
 for receipt in ['docs/COMBINED_ACCEPTANCE_REPAIR_SOURCE.json','docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json']:
  p=ROOT/receipt;original=p.read_bytes();data=json.loads(original)
  for path,row in changes.items():
   if path in data['files']:
    previous=data['files'][path]
    if previous['after_sha256']!=row['before_sha256']:raise SystemExit('Prior source receipt mismatch: '+path)
    previous.setdefault('edits',[]).extend(row['edits']);previous['after_sha256']=sha((ROOT/path).read_bytes())
  rewritten=(json.dumps(data,indent=2)+'\n').encode()
  if rewritten!=original:change(receipt,original.decode(),rewritten.decode())
 for path,row in changes.items():row['after_sha256']=sha((ROOT/path).read_bytes())
 for path in ['tests/current_staff_permission.php','tests/current_staff_permission_contract.php']:
  changes[path]={'before_sha256':None,'after_sha256':sha((ROOT/path).read_bytes())}
 RECEIPT.write_text(json.dumps({'schema':1,'base_tree':BASE,'files':changes,'scope':'Test-only corrections preserve actual session/permission/data-retention checks and all pre-existing lifecycle cases. No application change or production operation.'},indent=2)+'\n')
 subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
 print('Recorded test-only continuation files:',len(changes))
if __name__=='__main__':main()
