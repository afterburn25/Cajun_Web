#!/usr/bin/env python3
"""Expand bounded fixture repairs from an exact baseline; preserve complete inverses."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BASE='47d93ff55587195aa952ab7c56d0304c7029d672'
RECEIPT='docs/COMBINED_ACCEPTANCE_FOLLOWUP_SOURCE.json'
records={}
def digest(data):return hashlib.sha256(data).hexdigest()
def edit(path,old,new):
    p=ROOT/path
    if path not in records:
        original=subprocess.check_output(['git','show',BASE+':'+path],cwd=ROOT)
        if p.is_symlink() or p.read_bytes()!=original:raise SystemExit('Source drift: '+path)
        records[path]={'before_sha256':digest(original),'edits':[]}
    text=p.read_text()
    if text.count(old)!=1:raise SystemExit('Nonunique correction: '+path)
    p.write_text(text.replace(old,new,1));records[path]['edits'].append([old,new])
def main():
    if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/native-fixture-followup-20261031':raise SystemExit('Isolated fixture branch only')
    if (ROOT/RECEIPT).exists():
        subprocess.run(['python3','tests/combined_repair_provenance.py'],cwd=ROOT,check=True);return
    receipts={name:json.loads((ROOT/name).read_text()) for name in ['docs/COMBINED_ACCEPTANCE_REPAIR_SOURCE.json','docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json']}
    new={'schema':1,'base_tree':'2f11bc1fa202020880ead9abc1ee72d19205cac5','files':{},'scope':'Source-bound follow-up for delegated-staff authority and later legacy fixture branches; no production code changes.'}
    for path in ['tests/appointments_native.php','tests/reviews_native.php']:
        edit(path,"require_once __DIR__.'/current_owner_authority.php';","require_once __DIR__.'/current_owner_authority.php';\nrequire_once __DIR__.'/current_staff_authority.php';")
    edit('tests/appointments_native.php',"a_deny('Revoked owner refuses: Confirm needs current password',fn()=>cwp_test_owner_revoked(fn()=>appointments_mutate($lead,1,'confirm',null,null,'wrong')))","a_deny('Revoked delegated website permission refuses appointment confirmation',fn()=>cwp_test_website_staff_revoked(fn()=>appointments_mutate($lead,1,'confirm',null,null,'wrong')))")
    edit('tests/reviews_native.php',"r_deny('Revoked owner refuses: Delivery needs current password',fn()=>cwp_test_owner_revoked(fn()=>reviews_deliver($lead,7,$now,'email','Synthetic mail reference','wrong')))","r_deny('Revoked delegated website permission refuses report delivery',fn()=>cwp_test_website_staff_revoked(fn()=>reviews_deliver($lead,7,$now,'email','Synthetic mail reference','wrong')))")
    edit('tests/recurring_billing_checkout_native.php',"'payments_schema_version'=>'1'","'payments_schema_version'=>'2'")
    edit('tests/recurring_billing_checkout_native.php',"    rcn_owner();service_plans_migrate($password);","    rcn_owner();rcn_ok('Checkout fixture uses current payment readiness in '.$prefix,payments_ready()&&setting('payments_schema_version')==='2');service_plans_migrate($password);")
    edit('tests/provider_catalog_import_native.php',"        ob_start();provider_catalog_import_admin_view(admin_user());$changedHtml=ob_get_clean();","        $savedQuery=$_GET;$savedPost=$_POST;$_GET=['import_source'=>'packages.list'];$_POST=[];ob_start();try{provider_catalog_import_admin_view(admin_user());$changedHtml=(string)ob_get_contents();}finally{ob_end_clean();$_GET=$savedQuery;$_POST=$savedPost;}")
    edit('tests/provider_catalog_import_native.php',"pim_ok('Preview explains an existing draft from changed connection details',str_contains($changedHtml,'Your 20i connection details changed')","pim_ok('Saved-inventory preview explains an existing draft from changed connection details',str_contains($changedHtml,'aria-current=\"page\">Existing hosting inventory')&&str_contains($changedHtml,'Your 20i connection details changed')")
    edit('tests/provider_inventory_cloud_link_cases.php',"pi_deny('Cloud assignment refuses wrong current password',fn()=>provider_cloud_link($id,1201,1,7,$rid,'wrong'));","pi_deny('Cloud assignment refuses revoked current owner before any provider operation',fn()=>cwp_test_owner_revoked(fn()=>provider_cloud_link($id,1201,1,7,$rid,'wrong')));")
    for path,old,replacement in [
        ('tests/service_orders_links_cases.php',"rb_deny('Order association setup requires owner password',fn()=>hosting_order_links_migrate('wrong'));","rb_deny('Order association setup refuses revoked owner',fn()=>cwp_test_owner_revoked(fn()=>hosting_order_links_migrate('wrong')));"),
        ('tests/service_orders_links_cases.php',"rb_deny('Association requires current owner password',fn()=>hosting_order_links_create($o->data()['id'],1,$o->revision(),$o->fingerprint(),rb_id(30100),$solPackage,$solProfile,1,42,'wrong'));","rb_deny('Association refuses revoked current owner',fn()=>cwp_test_owner_revoked(fn()=>hosting_order_links_create($o->data()['id'],1,$o->revision(),$o->fingerprint(),rb_id(30100),$solPackage,$solProfile,1,42,'wrong')));"),
        ('tests/hosting_fulfillment_view_cases.php',"rb_deny('Preparation wrapper requires owner password',fn()=>hosting_fulfillment_prepare('wrong'));","rb_deny('Preparation wrapper refuses revoked owner',fn()=>cwp_test_owner_revoked(fn()=>hosting_fulfillment_prepare('wrong')));"),
        ('tests/hosting_fulfillment_confirmation_cases.php',"rb_deny('Status history preparation requires the owner password',fn()=>hosting_fulfillment_confirmation_prepare('wrong'));","rb_deny('Status history preparation refuses revoked owner',fn()=>cwp_test_owner_revoked(fn()=>hosting_fulfillment_confirmation_prepare('wrong')));"),
        ('tests/provider_plan_catalog_cases.php',"rb_deny('Catalog setup requires correct password',fn()=>provider_plan_catalog_prepare('wrong'));","rb_deny('Catalog setup refuses revoked owner',fn()=>cwp_test_owner_revoked(fn()=>provider_plan_catalog_prepare('wrong')));"),
    ]:edit(path,old,replacement)
    edit('tests/service_orders_link_views_cases.php',"rb_deny('Preparation form refuses stale or unconfirmed intent '.$i,fn()=>service_orders_form_action('service_plans_order_map_service',$viewAgreement,1,true));","$attempt=fn()=>service_orders_form_action('service_plans_order_map_service',$viewAgreement,1,true);rb_deny('Preparation form refuses stale unconfirmed or revoked intent '.$i,array_key_exists('current_password',$bad)?fn()=>cwp_test_owner_revoked($attempt):$attempt);")
    edit('tests/hosting_fulfillment_native_cases.php',"rb_deny('Local recovery rejects foreign or stale reviewed authority '.$field,fn()=>hosting_fulfillment_recover(...$bad));","$attempt=fn()=>hosting_fulfillment_recover(...$bad);rb_deny('Local recovery rejects foreign stale or revoked authority '.$field,$field===5?fn()=>cwp_test_owner_revoked($attempt):$attempt);")
    edit('tests/hosting_fulfillment_confirmation_cases.php',"rb_deny('Confirmation refuses changed client attempt approval or password '.$index,fn()=>hosting_fulfillment_confirm(...$bad));","$attempt=fn()=>hosting_fulfillment_confirm(...$bad);rb_deny('Confirmation refuses changed client attempt approval or revoked owner '.$index,$index===4?fn()=>cwp_test_owner_revoked($attempt):$attempt);")
    edit('tests/consolidation_native.py',"p=argparse.ArgumentParser();p.add_argument('--shard',type=int,required=True);a=p.parse_args()","p=argparse.ArgumentParser();p.add_argument('--shard',type=int,required=True);p.add_argument('--only',help='Explicit focused suite names; omission retains all63 suites');a=p.parse_args()")
    edit('tests/consolidation_native.py',"selected=sorted(cases)[a.shard::3]","cases['provider_inventory_native']+=1\ncases['recurring_billing_native']+=8\nselected=sorted(cases)[a.shard::3]\nif a.only is not None:\n requested=a.only.split(',')\n if a.shard!=0 or not requested or len(requested)!=len(set(requested)) or any(name not in cases for name in requested):raise SystemExit('Unknown or duplicated focused suite selection')\n selected=sorted(requested)")
    for path,expected in [('tests/current_staff_authority.php','f774566401e29645689f80accf4095c69e2303b277cc67482010ef520a4451f9'),('tests/current_staff_authority_contract.php','3e5ed44f0a08ea502224b35d8d9dbdae78b381839c6a4c670995b19a41d531c5')]:
        if digest((ROOT/path).read_bytes())!=expected:raise SystemExit('Reviewed helper changed: '+path)
        records[path]={'before_sha256':None}
    contract='tools/acceptance-contract.json'
    for path,row in list(records.items()):
        old=row['before_sha256'];after=digest((ROOT/path).read_bytes())
        if old and old in (ROOT/contract).read_text():edit(contract,old,after)
    edit('tests/combined_repair_provenance.py',"'docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json']","'docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json','docs/COMBINED_ACCEPTANCE_FOLLOWUP_SOURCE.json']")
    for path,row in records.items():
        owners=[doc for doc in receipts.values() if path in doc['files']]
        if len(owners)>1:raise SystemExit('Overlapping source receipts')
        target=owners[0]['files'] if owners else new['files']
        if path not in target:
            # Newly covered existing files must still equal the recorded original combined tree.
            if row['before_sha256'] is not None:
                old=subprocess.check_output(['git','show',new['base_tree']+':'+path],cwd=ROOT)
                if digest(old)!=row['before_sha256']:raise SystemExit('New predecessor needs an explicit chain: '+path)
            target[path]={'before_sha256':row['before_sha256'],'edits':[]}
        if target[path]['before_sha256'] is not None:target[path]['edits']+=row.get('edits',[])
        target[path]['after_sha256']=digest((ROOT/path).read_bytes())
    for name,data in receipts.items():(ROOT/name).write_text(json.dumps(data,indent=2)+'\n')
    (ROOT/RECEIPT).write_text(json.dumps(new,indent=2)+'\n')
    subprocess.run(['python3','tests/combined_repair_provenance.py'],cwd=ROOT,check=True)
    subprocess.run(['git','diff','--exit-code',BASE,'--','site','foundation','tools/structure','tools/build.py','VERSION','START-HERE.txt'],cwd=ROOT,check=True)
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
    print('Verified fixture-only corrections:',len(records))
if __name__=='__main__':main()
