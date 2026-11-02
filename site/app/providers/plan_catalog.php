<?php
/** Durable owner-reviewed plan mappings; existing order/payment/provider engines retain authority. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog_document.php';
require_once CWP_CODE_ROOT.'/app/providers/checks.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_plan_catalog_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/service_orders_migrate.php';

/** Closed local readiness reasons; never expose arbitrary storage/provider errors. */
final class ProviderPlanCatalogNotReady extends RuntimeException {
    public function __construct(public readonly string $reason,string $message){parent::__construct($message);}
}

function provider_plan_catalog_state():array {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_plan_catalog_version'])['value']??'';
    $exists=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_plan_catalog']);
    $history=(bool)one('SELECT id FROM '.table('audit').' WHERE action IN (?,?) LIMIT 1',['provider_catalog.setup','provider_catalog.saved'])||(bool)one('SELECT name FROM '.table('settings').' WHERE name LIKE ? LIMIT 1',['provider_plan_catalog_latest_%']);
    if(($version!==''&&$version!=='1')||(($version!==''||$history)&&($version!=='1'||!$exists)))throw new RuntimeException('Original provider catalog history is unavailable. Restore the original database.');
    if($version==='1'){
        if((int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['provider_catalog.setup'])->fetchColumn()!==1)throw new RuntimeException('Original catalog setup audit is missing or inconsistent.');
        $rows=(int)query('SELECT COUNT(*) FROM '.table('provider_plan_catalog'))->fetchColumn();
        $audits=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['provider_catalog.saved'])->fetchColumn();
        if($rows!==$audits)throw new RuntimeException('Original provider catalog records or audit history are missing.');
        if((int)query('SELECT COUNT(DISTINCT plan_id) FROM '.table('provider_plan_catalog'))->fetchColumn()!==(int)query('SELECT COUNT(*) FROM '.table('settings').' WHERE name LIKE ?',['provider_plan_catalog_latest_%'])->fetchColumn())throw new RuntimeException('Original provider catalog version pointers are missing.');
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
    $pointer=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_plan_catalog_latest_'.$plan])['value']??null;
    $expected=$previous?$previous['id'].':'.$previous['sha256']:null;
    if($pointer!==$expected)throw new RuntimeException('Original latest provider catalog review is missing. Restore its history before continuing.');
    return $result;
}
function provider_plan_catalog_current(string $plan):?array {$history=provider_plan_catalog_history($plan);return $history?end($history):null;}
/** Caller holds the shared owner guard. No credentials are decrypted or provider calls made. */
function provider_plan_catalog_eligible(array $d,int $now):void {
    $current=provider_plan_catalog_current($d['plan_id']);
    if(!$current||$current['id']!==$d['id']||$current['sha256']!==$d['sha256'])throw new ProviderPlanCatalogNotReady('superseded','This mapping was replaced. Review the latest mapping.');
    if(!$d['enabled'])throw new ProviderPlanCatalogNotReady('withdrawn','This mapping was withdrawn from new hosting preparation.');
    if($now<$d['created_at'])throw new ProviderPlanCatalogNotReady('future','This review is dated in the future. Check the server clock before reviewing it again.');
    if($now>=$d['expires_at'])throw new ProviderPlanCatalogNotReady('expired','This provider review expired. Review the account and save a new mapping version.');
    $plan=service_plans_validate(service_plans_row($d['plan_id']));
    if($plan->model()->status()!=='active')throw new ProviderPlanCatalogNotReady('retail_inactive','This retail plan is no longer published for new preparation. Existing agreements remain unchanged.');
    if($plan->revision()!==$d['plan_revision']||$plan->fingerprint()!==$d['plan_sha256'])throw new ProviderPlanCatalogNotReady('retail_changed','The retail plan version changed. Review the mapping against its current terms.');
    provider_settings_manager();
    try{$profile=provider_settings_load($d['profile_id']);}
    catch(InvalidArgumentException){throw new ProviderPlanCatalogNotReady('connection_missing','The reviewed provider connection is unavailable. Review provider settings.');}
    if((int)$profile['revision']!==$d['profile_revision'])throw new ProviderPlanCatalogNotReady('connection_changed','The provider connection version changed. Review its current settings and save a new mapping.');
    if((int)$profile['enabled']!==1)throw new ProviderPlanCatalogNotReady('connection_disabled','The provider connection is disabled. Review provider settings.');
    if($profile['credentials']==='')throw new ProviderPlanCatalogNotReady('credentials_missing','The provider connection has no saved key. Review provider settings.');
    // Keep the shared provider authority gate authoritative after classifying its known local refusals.
    [, $profile]=provider_checks_authority($d['profile_id'],$d['profile_revision']);
    if(hash('sha256',$profile['credentials'])!==$d['credential_sha256'])throw new ProviderPlanCatalogNotReady('credentials_changed','The saved provider key changed. Review the mapping again.');
    $owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$d['created_by']]);$staff=$owner?staff_profile($owner):null;
    if(!$staff||$staff['role']!=='owner'||!hash_equals($d['owner_auth'],$staff['auth_version']))throw new ProviderPlanCatalogNotReady('reviewer_changed','The original reviewer’s owner access or credentials changed. A current owner must review this mapping again.');
}
function provider_plan_catalog_save(string $id,string $planId,int $planRevision,string $planHash,?string $previous,string $profileId,int $profileRevision,array $configuration,int $days,bool $enabled,#[\SensitiveParameter] string $password):array {
    service_plans_id($id);$configuration=provider_plan_catalog_config($configuration);
    if($days<1||$days>30)throw new InvalidArgumentException('Review mappings for 1 to 30 days.');
    return recurring_billing_locked(fn()=>provider_plan_catalog_save_locked($id,$planId,$planRevision,$planHash,$previous,$profileId,$profileRevision,$configuration,$days,$enabled,$password));
}
/** Internal composition point; direct callers must supply the same validated mapping inputs. */
function provider_plan_catalog_save_locked(string $id,string $planId,int $planRevision,string $planHash,?string $previous,string $profileId,int $profileRevision,array $configuration,int $days,bool $enabled,#[\SensitiveParameter] string $password):array {
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(!db()->inTransaction()||(int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1)throw new LogicException('Saving a provider mapping requires the owning account transaction.');
    service_plans_id($id);$configuration=provider_plan_catalog_config($configuration);
    if($days<1||$days>30)throw new InvalidArgumentException('Review mappings for 1 to 30 days.');
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
    query('INSERT INTO '.table('provider_plan_catalog').' (id,plan_id,revision,created_by,created_epoch,binding_json,binding_sha256) VALUES (?,?,?,?,?,?,?)',array_values($row));provider_plan_catalog_audit((int)$actor['id'],'saved',provider_plan_catalog_detail($row));save_setting('provider_plan_catalog_latest_'.$planId,$id.':'.$sha);provider_plan_catalog_history($planId);return $d+['sha256'=>$sha];
}
/** Internal projection under the shared owner lock/transaction. History corruption is never a readiness state. */
function provider_plan_catalog_projection(string $plan,?int $revision=null):array {
    service_plans_owner();provider_settings_manager(false);service_plans_id($plan);
    if($revision!==null&&($revision<1||$revision>128))throw new InvalidArgumentException('Choose an available mapping review.');
    $state=provider_plan_catalog_state();$configured=provider_settings_ready();
    if(!$configured&&$state['ready'])throw new RuntimeException('Original provider settings are unavailable. Restore the original database before reviewing mappings.');
    $history=provider_plan_catalog_history($plan);$current=$history?end($history):null;$eligible=false;
    $reason=$configured?($state['ready']?'missing_review':'storage_setup'):'connection_setup';
    $message=match($reason){'missing_review'=>'No provider mapping has been reviewed for this retail plan.','storage_setup'=>'Prepare provider mapping history before saving the first review.',default=>'Prepare the provider connection before reviewing a mapping.'};
    if($current)try{provider_plan_catalog_eligible($current,time());$eligible=true;$reason='ready';$message='Ready for local preparation. Provider entitlement and setup still require their separate review.';}
    catch(ProviderPlanCatalogNotReady $e){$reason=$e->reason;$message=$e->getMessage();}
    $selected=$revision===null?$current:null;
    if($revision!==null){foreach($history as $entry)if($entry['revision']===$revision)$selected=$entry;if(!$selected)throw new InvalidArgumentException('The requested mapping review is unavailable for this retail plan.');}
    // The selected-review display excludes credential/auth fingerprints; existing internal history remains separate.
    $review=$selected?array_intersect_key($selected,array_flip(['revision','plan_revision','created_by','created_at','expires_at','enabled','configuration'])):null;
    if($review)$review['current']=$selected['id']===$current['id'];
    return ['ready'=>$state['ready'],'history'=>$history,'current'=>$current,'eligible'=>$eligible,'profile'=>$configured?provider_settings_profile():null,'readiness'=>['code'=>$reason,'message'=>$message],'selected'=>$review];
}
function provider_plan_catalog_context(string $plan,?int $revision=null):array {
    return recurring_billing_locked(fn()=>provider_plan_catalog_projection($plan,$revision));
}
/** Bounded owner-only summaries for the existing ten-plan page; never expose private cost or auth evidence. */
function provider_plan_catalog_summaries(array $plans):array {
    if(count($plans)>10||!array_is_list($plans))throw new InvalidArgumentException('Choose one page of retail plans.');
    return recurring_billing_locked(function()use($plans):array {
        service_plans_owner();provider_settings_manager(false);$result=[];
        foreach($plans as $plan){if(!is_string($plan))throw new InvalidArgumentException('Choose an available retail plan.');service_plans_id($plan);service_plans_validate(service_plans_row($plan));$ctx=provider_plan_catalog_projection($plan);$result[$plan]=$ctx['readiness']+['eligible'=>$ctx['eligible'],'revision'=>$ctx['current']['revision']??null];}
        return $result;
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
