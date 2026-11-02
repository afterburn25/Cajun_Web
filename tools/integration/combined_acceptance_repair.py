#!/usr/bin/env python3
"""Apply reviewed source changes on an isolated branch; never modify main or deploy."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BASE='2f11bc1fa202020880ead9abc1ee72d19205cac5'
BRANCH='refs/heads/chatgpt/combined-acceptance-repair-20261031'
RECEIPT=ROOT/'docs/COMBINED_ACCEPTANCE_REPAIR_SOURCE.json'
changes={}
def digest(raw):return hashlib.sha256(raw).hexdigest()
def original(path):return subprocess.check_output(['git','show',BASE+':'+path],cwd=ROOT)
def change(path,old,new):
    p=ROOT/path
    if path not in changes:
        before=original(path)
        if p.is_symlink() or p.read_bytes()!=before:raise SystemExit('Source drift: '+path)
        changes[path]={'before_sha256':digest(before),'edits':[]}
    text=p.read_text()
    if text.count(old)!=1:raise SystemExit('Non-unique correction: '+path+' '+old[:80])
    changes[path]['edits'].append([old,new]);p.write_text(text.replace(old,new,1))
def create(path,text):
    p=ROOT/path
    if p.exists() or p.is_symlink():raise SystemExit('Unexpected existing file: '+path)
    p.parent.mkdir(parents=True,exist_ok=True);p.write_text(text);changes[path]={'before_sha256':None}
def main():
    if os.environ.get('GITHUB_REF')!=BRANCH:raise SystemExit('Isolated correction branch only')
    if RECEIPT.exists():
        receipt=json.loads(RECEIPT.read_text())
        if receipt['base_tree']!=BASE:raise SystemExit('Unknown correction receipt')
        for path,row in receipt['files'].items():
            if digest((ROOT/path).read_bytes())!=row['after_sha256']:raise SystemExit('Recorded correction changed: '+path)
        print('Recorded repairs already present');return
    change('tests/current_owner_authority.php',"        'Only an owner can verify administrator access.',","        'Only an owner can verify administrator access.',\n        'Current website and inquiry permission is required.',\n        'Your current account does not have access to client administration.',")
    change('tests/current_owner_authority_contract.php'," reset_fixture();must_reject('A callback that wrongly succeeds cannot pass',fn()=>null);", """ foreach(['Current website and inquiry permission is required.','Your current account does not have access to client administration.'] as $message){
  reset_fixture();$before=cwp_test_authority_snapshot();$correct=false;
  try{cwp_test_owner_revoked(fn()=>throw new RuntimeException($message));}catch(RuntimeException $e){$correct=$e->getMessage()===$message;}
  ok_fixture('Actual service authority gate is recognized: '.$message,$correct&&cwp_test_authority_snapshot()===$before);
  reset_fixture();must_reject('Matching service error cannot hide a write: '.$message,function()use($message){$GLOBALS['rows']['authority_audit'][]=['id'=>2];throw new RuntimeException($message);});
 }
 reset_fixture();must_reject('A callback that wrongly succeeds cannot pass',fn()=>null);""")
    change('tests/software_commerce_native.php',"$n=0;\nfunction swok(bool $pass,string $name):void{global $n;if(!$pass)throw new RuntimeException($name);$n++;echo 'PASS '.$name.PHP_EOL;}","$n=0;$checks=[];\nfunction swok(bool $pass,string $name):void{global $n,$checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);$n++;echo 'PASS '.$name.PHP_EOL;}")
    create('tests/checkout_endpoint.php',"""<?php
/** Explicit client/server port distinction for disposable checkout fixtures only. */
declare(strict_types=1);
function cwp_test_checkout_endpoint(int $clientPort,int $reportedPort): bool {
    $mapped=getenv('CWP_TEST_CHECKOUT_DOCKER_PORT');
    if($clientPort!==43319)return false;
    if($mapped===false||$mapped==='')return $reportedPort===43319;
    return $mapped==='3306'&&getenv('CWP_TEST_HOST')==='127.0.0.1'
        &&getenv('CWP_TEST_DATABASE')==='cwp_checkout_test'&&$reportedPort===3306;
}
""")
    for path in ['tests/service_plans_checkout_native.php','tests/recurring_billing_checkout_native.php']:
        change(path,"require_once __DIR__.'/current_owner_authority.php';","require_once __DIR__.'/current_owner_authority.php';\nrequire_once __DIR__.'/checkout_endpoint.php';")
        change(path,"(int)query('SELECT @@port')->fetchColumn()===$port&&query('SELECT DATABASE()')->fetchColumn()===$database","cwp_test_checkout_endpoint($port,(int)query('SELECT @@port')->fetchColumn())&&(int)config()['db']['port']===$port&&query('SELECT DATABASE()')->fetchColumn()===$database")
    change('tests/consolidation_native.py',"'CWP_CONSOLIDATION_CASE',*flags.values()","'CWP_CONSOLIDATION_CASE','CWP_TEST_CHECKOUT_DOCKER_PORT',*flags.values()")
    change('tests/consolidation_native.py',"env.update(CWP_TEST_DATABASE='cwp_checkout_test',CWP_TEST_PORT='43319')","env.update(CWP_TEST_DATABASE='cwp_checkout_test',CWP_TEST_PORT='43319',CWP_TEST_CHECKOUT_DOCKER_PORT='3306')")
    change('tests/consolidation_native.py',"if len(cases)!=63 or sum(cases.values())!=122:raise SystemExit('Native suite inventory changed')","if len(cases)!=63 or sum(cases.values())!=122:raise SystemExit('Native suite inventory changed')\n# Additional actual owner-denial assertion replaces the obsolete inline password branch.\ncases['provider_password_reset_template_native']+=1")
    create('tests/provider_workspace_render.php',"""<?php
/** Render the real selected workspace without replacing the fixture's $checks array. */
declare(strict_types=1);
function cwp_test_provider_workspace(array $query): string {
    $saved=$_GET;$_GET=$query;ob_start();
    try{require CWP_CODE_ROOT.'/admin/views/providers.php';return (string)ob_get_contents();}
    finally{ob_end_clean();$_GET=$saved;}
}
""")
    change('tests/provider_inventory_native.php',"require_once CWP_CODE_ROOT.'/includes/layout/admin.php';ob_start();require CWP_CODE_ROOT.'/admin/views/providers.php';$cloudHtml=ob_get_clean();","require_once CWP_CODE_ROOT.'/includes/layout/admin.php';require_once __DIR__.'/provider_workspace_render.php';$domainHtml=cwp_test_provider_workspace(['section'=>'domains']);$cloudHtml=cwp_test_provider_workspace(['section'=>'cloud']);")
    change('tests/provider_inventory_native.php',"str_contains($cloudHtml,'Prepare domain search')","str_contains($domainHtml,'Prepare domain search')")
    change('tests/provider_inventory_native.php',"provider_settings_save($id,6,'Cloud context changed',true,'',$password);ob_start();require CWP_CODE_ROOT.'/admin/views/providers.php';$cloudHtml=ob_get_clean();","provider_settings_save($id,6,'Cloud context changed',true,'',$password);$cloudHtml=cwp_test_provider_workspace(['section'=>'cloud']);")
    change('tests/provider_inventory_upgrade_native.php',"function pu_render():string {ob_start();try{require CWP_CODE_ROOT.'/admin/views/providers.php';return ob_get_contents();}finally{ob_end_clean();}}","require_once __DIR__.'/provider_workspace_render.php';\nfunction pu_render(string $section='services'):string {return cwp_test_provider_workspace(['section'=>$section]);}")
    change('tests/provider_inventory_upgrade_native.php',"str_contains($unpreparedHtml,'Prepare domain search')","str_contains(pu_render('domains'),'Prepare domain search')")
    change('tests/provider_cloud_sites_native.php',"'Actual owner view includes typed consent/password and pending-ID guidance',str_contains($html,'data-cloud-website-create')&&str_contains($html,'name=\"reviewed\"')&&str_contains($html,'name=\"current_password\"')","'Actual owner view retains explicit consent and CSRF without repeat password',str_contains($html,'data-cloud-website-create')&&str_contains($html,'name=\"reviewed\"')&&str_contains($html,'name=\"csrf\"')&&!str_contains($html,'name=\"current_password\"')")
    change('tests/provider_mailboxes_native.php',"'Rendered owner creation form requires current password',str_contains($html,'current_password')&&str_contains($html,'provider_inventory_mailbox_create')","'Rendered owner form uses current session and retains CSRF and create action',!str_contains($html,'current_password')&&str_contains($html,'name=\"csrf\"')&&str_contains($html,'provider_inventory_mailbox_create')")
    change('tests/domain_registration_drafts_native.php',"str_contains($html,'owner_password')&&!str_contains($html,'registration_drafts_client_submit')","!str_contains($html,'owner_password')&&str_contains($html,'registration_drafts_owner_save')&&str_contains($html,'name=\"client_id\"')&&str_contains($html,'name=\"csrf\"')&&!str_contains($html,'registration_drafts_client_submit')")
    change('tests/provider_password_reset_template_native.php',"    foreach(['password','confirmation','proof','text'] as $bad)","    $before=[$dns,$reads,$writes,prtn_snapshot()];prtn_deny('Save refuses revoked owner and preserves provider request history',fn()=>cwp_test_owner_revoked(fn()=>prtn_change($review)));prtn_ok('Revoked owner consumes no approval or provider call',[$dns,$reads,$writes,prtn_snapshot()]===$before);\n    foreach(['confirmation','proof','text'] as $bad)")
    change('site/admin/views/providers.php',"require_once CWP_CODE_ROOT.'/app/providers/workspace.php';", """require_once CWP_CODE_ROOT.'/app/providers/workspace.php';
$cloudAssignmentFocused=($_GET['focus']??null)==='cloud-assignment';
if($cloudAssignmentFocused){
    ?><section class="workspace-card" data-cloud-assignment-focus><h2>Cloud assignments</h2><p>Review cloud observations, client assignments and their recorded history. This focused page does not load unrelated website or package controls.</p><a class="btn outline" href="<?=esc(admin_url('provider-settings'))?>">All 20i workspaces</a></section><?php
    require CWP_CODE_ROOT.'/admin/views/provider_cloud.php';
    return;
}""")
    change('tests/provider_cloud_assignment_native.php',"str_contains($focused,'name=\"current_password\"')","!str_contains($focused,'name=\"current_password\"')")
    for path,row in changes.items():row['after_sha256']=digest((ROOT/path).read_bytes())
    RECEIPT.write_text(json.dumps({'schema':1,'base_tree':BASE,'files':changes,'scope':'Reviewed current-session, reporting, render-routing and isolated-checkout corrections. Native and installed acceptance remain required; no deployment.'},indent=2)+'\n')
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
    print('Applied reviewed source corrections:',len(changes))
if __name__=='__main__':main()
