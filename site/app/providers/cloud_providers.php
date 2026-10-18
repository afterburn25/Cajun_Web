<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\TwentyIInventory\{CloudProviderList,InventoryCloudProvider,InventoryRead,InventorySnapshot};
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory/CloudProviderList.php';
require_once CWP_CODE_ROOT.'/app/providers/cloud_providers_storage.php';

function provider_cloud_providers_started(array $started):void {
    provider_cloud_providers_guard();
    if(($started['operation']??'')!==InventoryRead::CLOUD_PROVIDERS_LIST)throw new LogicException('Cloud provider witness requires its exact operation.');
    $source=one('SELECT * FROM '.table('provider_refreshes').' WHERE id=?',[$started['id']]);
    if(!$source||(int)$source['seq']<1)throw new LogicException('Cloud provider witness requires its retained insertion sequence.');
    foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation'] as $key)if((string)$source[$key]!==(string)$started[$key])throw new LogicException('Cloud provider witness does not match its retained intent.');
    provider_settings_audit((int)$source['actor_id'],'cloud_providers_started',provider_cloud_providers_start_detail($source));
    provider_cloud_providers_advance('started');
}
function provider_cloud_providers_proof(array $source,string $identifiers,int $observedAt):string {
    return hash_hmac('sha256',json_encode([provider_cloud_providers_result_context($source),$identifiers,$observedAt],JSON_THROW_ON_ERROR),provider_settings_vault_key());
}
/** Called only by the single inventory recorder, after its common result INSERT. */
function provider_cloud_providers_record(array $started,?InventorySnapshot $snapshot,string $fingerprint):void {
    provider_cloud_providers_guard();
    $source=one('SELECT f.*,r.* FROM '.table('provider_refreshes').' f JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=?',[$started['id']]);
    if(!$source||$source['operation']!==InventoryRead::CLOUD_PROVIDERS_LIST||$source['profile_id']!==$started['profile_id']||(int)$source['actor_id']!==(int)$started['actor_id']||(int)$source['profile_revision']!==(int)$started['profile_revision']||$source['key_fingerprint']!==$fingerprint)throw new LogicException('Cloud provider result context does not match its intent.');
    $proof='';$json='';$observedAt=null;
    if($source['state']==='complete'){
        if(!$snapshot||$snapshot->state!=='complete'||$snapshot->scope!=='twenty-i|'.$source['profile_id'].'|'.InventoryRead::CLOUD_PROVIDERS_LIST||$snapshot->coverage!=='unknown'||$snapshot->skipped!==0||$snapshot->skipReasons!==[]||$snapshot->reason!==''||!preg_match('/\A[a-f0-9]{64}\z/',$fingerprint)||$snapshot->observedAt<(int)$source['started_epoch']||$snapshot->observedAt>time()+30)throw new LogicException('Cloud provider observation is not a complete current typed result.');
        $values=[];foreach($snapshot->packages as $record){if(!$record instanceof InventoryCloudProvider)throw new LogicException('Only cloud provider identifiers may persist.');$values[]=$record->identifier;}
        CloudProviderList::fromValues($values);$json=json_encode($values,JSON_THROW_ON_ERROR);
        if((int)$source['package_count']!==count($values)||$source['scope']!==$snapshot->scope||$source['coverage']!==$snapshot->coverage||$source['reason']!==''||(int)$source['skipped']!==0||$source['skip_reasons']!=='[]')throw new LogicException('Cloud provider metadata does not match the result.');
        $observedAt=$snapshot->observedAt;$proof=provider_cloud_providers_proof($source,$json,$observedAt);
        query('INSERT INTO '.table('provider_observation_cloud_providers').' (id,identifiers,observed_epoch,proof) VALUES (?,?,?,?)',[$source['id'],$json,$snapshot->observedAt,$proof]);
    }elseif(!in_array($source['state'],['unavailable','refused','access_changed','credential'],true)||(int)$source['package_count']!==0){throw new LogicException('Cloud provider outcome is unsupported.');}
    provider_settings_audit((int)$source['actor_id'],'cloud_providers_finished',provider_cloud_providers_finish_detail($source,$proof,$json,$observedAt));
    provider_cloud_providers_advance('finished');
}
function provider_cloud_providers_observation(array $source):array {
    $doc=one('SELECT * FROM '.table('provider_observation_cloud_providers').' WHERE id=?',[$source['id']]);
    if(!$doc||$source['operation']!==InventoryRead::CLOUD_PROVIDERS_LIST||$source['state']!=='complete'||$source['scope']!=='twenty-i|'.$source['profile_id'].'|'.InventoryRead::CLOUD_PROVIDERS_LIST||$source['coverage']!=='unknown'||(int)$source['skipped']!==0||$source['reason']!==''||$source['skip_reasons']!=='[]'||!preg_match('/\A[a-f0-9]{64}\z/',$source['key_fingerprint']))throw new RuntimeException('Cloud provider observation needs recovery.');
    try{$values=json_decode($doc['identifiers'],false,4,JSON_THROW_ON_ERROR);$records=CloudProviderList::fromValues($values);}catch(JsonException|InvalidArgumentException){throw new RuntimeException('Cloud provider observation needs recovery.');}
    if(json_encode($values,JSON_THROW_ON_ERROR)!==$doc['identifiers']||(int)$source['package_count']!==count($records)||!hash_equals(provider_cloud_providers_proof($source,$doc['identifiers'],(int)$doc['observed_epoch']),$doc['proof']))throw new RuntimeException('Cloud provider observation integrity needs recovery.');
    return ['identifiers'=>$values,'observed_epoch'=>(int)$doc['observed_epoch']];
}
/** Owner projection only. Sequence order fences later failed/unfinished attempts despite clock rollback. */
function provider_cloud_providers_view(string $profileId,?int $now=null):array {
    provider_settings_manager();if(!provider_cloud_providers_ready())throw new RuntimeException('Prepare cloud provider storage first.');
    $profile=provider_settings_load($profileId);$latest=provider_cloud_providers_source($profileId);$saved=provider_cloud_providers_source($profileId,true);$observation=$saved?provider_cloud_providers_observation($saved):null;
    $current=false;$now??=time();
    if($saved&&$latest['id']===$saved['id']&&(int)$profile['enabled']===1&&$profile['credentials']!==''&&(int)$profile['revision']===(int)$saved['profile_revision']&&(int)$saved['started_epoch']<=$now+30&&(int)$saved['started_epoch']>=$now-900&&$observation['observed_epoch']<=$now+30&&$observation['observed_epoch']>=(int)$saved['started_epoch']){
        try{$secret=provider_settings_key_value(provider_settings_unseal($profileId,$profile['environment'],$profile['credentials']));$current=hash_equals($saved['key_fingerprint'],provider_inventory_fingerprint($secret));}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$current=false;}finally{unset($secret);}
    }
    return ['latest'=>$latest?['id'=>$latest['id'],'state'=>$latest['state']??'unfinished','started_at'=>$latest['started_at']]:null,'saved'=>$saved?['id'=>$saved['id'],'observed_at'=>gmdate('Y-m-d H:i:s',$observation['observed_epoch']),'identifiers'=>$observation['identifiers']]:null,'current'=>$current];
}
