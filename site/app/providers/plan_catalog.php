<?php
/** Durable owner-reviewed plan mappings; existing order/payment/provider engines retain authority. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog_document.php';
require_once CWP_CODE_ROOT.'/app/providers/checks.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_plan_catalog_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/service_orders_migrate.php';

function provider_plan_catalog_state():array {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_plan_catalog_version'])['value']??'';
    $exists=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_plan_catalog']);
    $history=(bool)one('SELECT id FROM '.table('audit').' WHERE action IN (?,?) LIMIT 1',['provider_catalog.setup','provider_catalog.saved']);
    if(($version!==''&&$version!=='1')||(($version!==''||$history)&&($version!=='1'||!$exists)))throw new RuntimeException('Original provider catalog history is unavailable. Restore the original database.');
    if($version==='1'){
        if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['provider_catalog.setup'])->fetchColumn()!==1)throw new RuntimeException('Original catalog setup audit is missing or inconsistent.');
        $rows=(int)query('SELECT COUNT(*) FROM '.table('provider_plan_catalog'))->fetchColumn();
        $audits=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['provider_catalog.saved'])->fetchColumn();
        if($rows!==$audits)throw new RuntimeException('Original provider catalog records or audit history are missing.');
    }
    return ['ready'=>$version==='1','exists'=>$exists];
}
function provider_plan_catalog_prepare(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Catalog setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        $actor=service_plans_password($password);provider_settings_owner($password);$state=provider_plan_catalog_state();
        if(!$state['ready']&&$state['exists']&&one('SELECT 1 FROM '.table('provider_plan_catalog').' LIMIT 1'))throw new RuntimeException('Unrecognized catalog history was preserved.');
        $sql=provider_plan_catalog_schema(config()['db']['prefix']);if(!$state['exists'])db()->exec($sql);
        service_orders_validate_schema('provider_plan_catalog',$sql);
        if(!$state['ready'])staff_transaction(function()use($actor):void {save_setting('provider_plan_catalog_version','1');provider_plan_catalog_audit((int)$actor['id'],'setup','Owner-reviewed provider plan catalog prepared');});
    });
}
function provider_plan_catalog_audit(int $actor,string $action,string $detail):void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'provider_catalog.'.$action,$detail,gmdate('Y-m-d H:i:s')]);}
function provider_plan_catalog_detail(array $row):string {return 'Plan '.$row['plan_id'].' mapping '.$row['id'].' revision '.$row['revision'].' sha256 '.$row['binding_sha256'];}
/** Historical records remain readable after retirement, expiry and connection changes. */
function provider_plan_catalog_history(string $plan):array {
    service_plans_id($plan);if(!provider_plan_catalog_state()['ready'])return [];
    $rows=all('SELECT * FROM '.table('provider_plan_catalog').' WHERE plan_id=? ORDER BY revision LIMIT 129',[$plan]);
    if(count($rows)>128)throw new RuntimeException('Provider catalog history exceeds its bound.');
    $result=[];$previous=null;
    foreach($rows as $index=>$row){
        $d=provider_plan_catalog_document($row['binding_json']);$sha=hash('sha256',$row['binding_json']);
        if($sha!==$row['binding_sha256']||$d['id']!==$row['id']||$d['plan_id']!==$plan||$d['revision']!==$index+1||$d['revision']!==(int)$row['revision']||$d['created_by']!==(int)$row['created_by']||$d['created_at']!==(int)$row['created_epoch']||$d['previous_id']!==($previous['id']??null)||$d['previous_sha256']!==($previous['sha256']??null))throw new RuntimeException('Original provider catalog history differs.');
        $planDoc=service_plans_validate(service_plans_row($plan))->prefix($d['plan_revision']);
        if($planDoc->fingerprint()!==$d['plan_sha256']||$planDoc->model()->status()!=='active')throw new RuntimeException('Original catalog service terms differ.');
        $profile=one('SELECT enabled,has_credentials FROM '.table('provider_revisions').' WHERE profile_id=? AND revision=?',[$d['profile_id'],$d['profile_revision']]);
        if(!$profile||(int)$profile['enabled']!==1||(int)$profile['has_credentials']!==1||($previous&&$d['created_at']<$previous['created_at']))throw new RuntimeException('Original catalog provider review differs.');
        $audit=all('SELECT user_id FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['provider_catalog.saved',provider_plan_catalog_detail($row)]);
        if(count($audit)!==1||(int)$audit[0]['user_id']!==$d['created_by'])throw new RuntimeException('Original catalog approval is missing.');
        $result[]=$d+['sha256'=>$sha];$previous=['id'=>$d['id'],'sha256'=>$sha,'created_at'=>$d['created_at']];
    }
    return $result;
}
function provider_plan_catalog_current(string $plan):?array {$history=provider_plan_catalog_history($plan);return $history?end($history):null;}
/** Caller holds the shared owner guard. No credentials are decrypted or provider calls made. */
function provider_plan_catalog_eligible(array $d,int $now):void {
    $current=provider_plan_catalog_current($d['plan_id']);
    if(!$current||$current['id']!==$d['id']||$current['sha256']!==$d['sha256']||!$d['enabled']||$now<$d['created_at']||$now>=$d['expires_at'])throw new RuntimeException('Review a current enabled provider mapping before using it.');
    $plan=service_plans_validate(service_plans_row($d['plan_id']));
    if($plan->revision()!==$d['plan_revision']||$plan->fingerprint()!==$d['plan_sha256']||$plan->model()->status()!=='active')throw new RuntimeException('The retail plan changed or was retired.');
    [, $profile]=provider_checks_authority($d['profile_id'],$d['profile_revision']);
    if(hash('sha256',$profile['credentials'])!==$d['credential_sha256'])throw new RuntimeException('The provider connection changed. Review the mapping again.');
    $owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$d['created_by']]);$staff=$owner?staff_profile($owner):null;
    if(!$staff||$staff['role']!=='owner'||!hash_equals($d['owner_auth'],$staff['auth_version']))throw new RuntimeException('The mapping owner authorization changed. Review the mapping again.');
}
function provider_plan_catalog_save(string $id,string $planId,int $planRevision,string $planHash,?string $previous,string $profileId,int $profileRevision,array $configuration,int $days,bool $enabled,#[\SensitiveParameter] string $password):array {
    service_plans_id($id);$configuration=provider_plan_catalog_config($configuration);
    if($days<1||$days>30)throw new InvalidArgumentException('Review mappings for 1 to 30 days.');
    return recurring_billing_locked(function()use($id,$planId,$planRevision,$planHash,$previous,$profileId,$profileRevision,$configuration,$days,$enabled,$password):array {
        $actor=service_plans_password($password);[, $profile]=provider_checks_authority($profileId,$profileRevision,$password);
        if(!provider_plan_catalog_state()['ready'])throw new RuntimeException('Prepare provider catalog storage first.');
        $plan=service_plans_validate(service_plans_row($planId));
        if($plan->model()->status()!=='active'||$plan->revision()!==$planRevision||$plan->fingerprint()!==$planHash)throw new RuntimeException('Reload the current published retail plan.');
        $history=provider_plan_catalog_history($planId);$current=$history?end($history):null;
        $old=one('SELECT * FROM '.table('provider_plan_catalog').' WHERE id=?',[$id]);
        if($old&&$old['plan_id']!==$planId)throw new RuntimeException('Mapping identity is already used.');
        $prior=null;foreach($history as $entry)if($entry['id']===$previous)$prior=$entry;
        if(($previous!==null&&!$prior)||(!$old&&($current['id']??null)!==$previous))throw new RuntimeException('The provider mapping changed. Reload and review it.');
        $at=$old?(int)$old['created_epoch']:time();
        $d=['format'=>1,'id'=>$id,'plan_id'=>$planId,'revision'=>($prior['revision']??0)+1,'previous_id'=>$prior['id']??null,'previous_sha256'=>$prior['sha256']??null,'plan_revision'=>$planRevision,'plan_sha256'=>$planHash,'profile_id'=>$profileId,'profile_revision'=>$profileRevision,'credential_sha256'=>hash('sha256',$profile['credentials']),'created_by'=>(int)$actor['id'],'owner_auth'=>$actor['auth_version'],'created_at'=>$at,'expires_at'=>$at+$days*86400,'enabled'=>$enabled,'configuration'=>$configuration,'evidence'=>'owner-account-review'];
        $bytes=service_plans_json($d);provider_plan_catalog_document($bytes);$sha=hash('sha256',$bytes);
        if($old){if($old['binding_json']!==$bytes)throw new RuntimeException('Mapping identity is already used for different details.');return $d+['sha256'=>$sha];}
        $row=['id'=>$id,'plan_id'=>$planId,'revision'=>$d['revision'],'created_by'=>(int)$actor['id'],'created_epoch'=>$at,'binding_json'=>$bytes,'binding_sha256'=>$sha];
        query('INSERT INTO '.table('provider_plan_catalog').' (id,plan_id,revision,created_by,created_epoch,binding_json,binding_sha256) VALUES (?,?,?,?,?,?,?)',array_values($row));provider_plan_catalog_audit((int)$actor['id'],'saved',provider_plan_catalog_detail($row));provider_plan_catalog_history($planId);return $d+['sha256'=>$sha];
    });
}
function provider_plan_catalog_context(string $plan):array {
    return recurring_billing_locked(function()use($plan):array {
        service_plans_owner();provider_settings_manager();$state=provider_plan_catalog_state();$history=provider_plan_catalog_history($plan);$current=$history?end($history):null;$eligible=false;
        if($current)try{provider_plan_catalog_eligible($current,time());$eligible=true;}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
        return ['ready'=>$state['ready'],'history'=>$history,'current'=>$current,'eligible'=>$eligible,'profile'=>provider_settings_profile()];
    });
}
/** Use the exact plan accepted for this order, never an unrelated current offer. */
function provider_plan_catalog_order(array $mapping,ServiceOrderDocument $order,bool $current):void {
    $o=$order->data();$a=$o['agreement'];
    $source=service_orders_agreement($a['id'],$o['client_id'],$a['revision'],$a['sha256'],$a['terms_hash'])['prefix'];
    $plan=ServicePlanDocument::fromJson(service_plans_json($source->data()['plan']));
    if($plan->data()['id']!==$mapping['plan_id']||$plan->revision()!==$mapping['plan_revision']||$plan->fingerprint()!==$mapping['plan_sha256'])throw new RuntimeException('The provider mapping does not match this client’s exact agreed retail plan.');
    if($current)provider_plan_catalog_eligible($mapping,time());
}
function provider_plan_catalog_lookup(string $id):array {
    service_plans_id($id);if(!provider_plan_catalog_state()['ready'])throw new RuntimeException('Provider catalog history is unavailable.');
    $row=one('SELECT plan_id FROM '.table('provider_plan_catalog').' WHERE id=?',[$id]);
    if($row)foreach(provider_plan_catalog_history($row['plan_id']) as $entry)if($entry['id']===$id)return $entry;
    throw new RuntimeException('The original provider catalog mapping is unavailable.');
}
