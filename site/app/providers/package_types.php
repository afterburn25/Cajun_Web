<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\TwentyIInventory\{InventoryPackageType,InventoryRead,InventorySnapshot};
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory/InventoryPackageType.php';
require_once CWP_CODE_ROOT.'/app/providers/package_types_storage.php';
require_once CWP_CODE_ROOT.'/database/migrations/provider_package_types_migrate.php';

/** Current owner and exact provider context. Called under the staff mutex before credentials open. */
function provider_package_types_current_context(array $source,array $binding):void {
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if((int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1)throw new LogicException('Package type credential access requires the owning account mutex.');
    [$actor,$profile]=provider_inventory_authority($source['profile_id'],(int)$source['profile_revision']);
    if((int)$actor['id']!==(int)$source['actor_id']||!CajunWebPro\Foundation\Access\AuthorizationVersion::matches($actor['auth_version'],$binding['actor_auth_version']))throw new RuntimeException('Package type refresh owner changed.');
    try{$secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
        if(!hash_equals($binding['key_fingerprint'],provider_inventory_fingerprint($secret)))throw new RuntimeException('Package type refresh credential changed.');
    }finally{unset($secret);}
}
function provider_package_types_intent_authority(array $started):void {
    if(!provider_package_types_ready())throw new RuntimeException('Prepare package type storage first.');
    $source=one('SELECT * FROM '.table('provider_refreshes').' WHERE id=?',[$started['id']]);
    if(!$source)throw new RuntimeException('Package type intent needs recovery.');
    foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation'] as $key)if((string)$source[$key]!==(string)$started[$key])throw new RuntimeException('Package type intent changed.');
    provider_package_types_current_context($source,provider_package_types_binding($source));
}
/** Common durable intent and this binding commit together, before DNS or transport. */
function provider_package_types_started(array $started):void {
    provider_package_types_guard();
    $source=one('SELECT * FROM '.table('provider_refreshes').' WHERE id=?',[$started['id']]);
    if(!$source||$source['operation']!==InventoryRead::PACKAGE_TYPES||(int)$source['seq']<1)throw new LogicException('Package type witness requires its exact retained intent.');
    foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation'] as $key)if((string)$source[$key]!==(string)$started[$key])throw new LogicException('Package type intent changed before binding.');
    [$actor,$profile]=provider_inventory_authority($source['profile_id'],(int)$source['profile_revision']);
    if((int)$actor['id']!==(int)$source['actor_id']||!CajunWebPro\Foundation\Access\AuthorizationVersion::valid($actor['auth_version']))throw new RuntimeException('Package type refresh owner changed.');
    try{$secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));$fingerprint=provider_inventory_fingerprint($secret);}finally{unset($secret);}
    $binding=['actor_auth_version'=>$actor['auth_version'],'key_fingerprint'=>$fingerprint,'proof'=>provider_package_types_intent_proof($source,$actor['auth_version'],$fingerprint)];
    query('INSERT INTO '.table('provider_package_type_intents').' (id,actor_auth_version,key_fingerprint,proof) VALUES (?,?,?,?)',[$source['id'],$binding['actor_auth_version'],$fingerprint,$binding['proof']]);
    provider_settings_audit((int)$source['actor_id'],'package_types_started',provider_package_types_start_detail($source,$binding));
    provider_package_types_advance('started');
}
function provider_package_types_proof(array $source,string $types,int $observedAt):string {
    return hash_hmac('sha256',json_encode(['package-types-observation-v1',provider_package_types_result_context($source),$types,$observedAt],JSON_THROW_ON_ERROR),provider_settings_vault_key());
}
/** Rebuild the exact bounded type list; no partial rows, duplicates or extra saved fields. */
function provider_package_types_values(array $records):array {
    if(!array_is_list($records)||count($records)>InventorySnapshot::MAX_ITEMS)throw new InvalidArgumentException('Package type observation exceeds its bounds.');
    $types=[];$seen=[];
    foreach($records as $record){
        if(!$record instanceof InventoryPackageType)throw new InvalidArgumentException('Only typed package types may persist.');
        $type=InventoryPackageType::fromStored(['id'=>$record->id,'label'=>$record->label]);
        if(isset($seen[$type->id]))throw new InvalidArgumentException('Duplicate package type identity.');
        $seen[$type->id]=true;$types[]=['id'=>$type->id,'label'=>$type->label];
    }
    return $types;
}
/** Called by the inventory recorder after the common result INSERT, in the same owned transaction. */
function provider_package_types_record(array $started,?InventorySnapshot $snapshot,string $fingerprint):void {
    provider_package_types_guard();
    $source=one('SELECT f.*,r.* FROM '.table('provider_refreshes').' f JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=?',[$started['id']]);
    if(!$source||$source['operation']!==InventoryRead::PACKAGE_TYPES||$source['profile_id']!==$started['profile_id']||(int)$source['actor_id']!==(int)$started['actor_id']||(int)$source['profile_revision']!==(int)$started['profile_revision']||$source['key_fingerprint']!==$fingerprint)throw new LogicException('Package type result context does not match its intent.');
    $binding=provider_package_types_binding($source);$proof='';$json='';$observedAt=null;
    if($source['state']==='complete'){
        provider_package_types_current_context($source,$binding);
        if(!$snapshot||$snapshot->state!=='complete'||$snapshot->scope!=='twenty-i|'.$source['profile_id'].'|'.InventoryRead::PACKAGE_TYPES||$snapshot->coverage!=='unknown'||$snapshot->skipped!==0||$snapshot->skipReasons!==[]||$snapshot->reason!==''||!hash_equals($binding['key_fingerprint'],$fingerprint)||$snapshot->observedAt<(int)$source['started_epoch']||$snapshot->observedAt>time()+30)throw new LogicException('Package type observation is not a complete current typed result.');
        $types=provider_package_types_values($snapshot->packages);$json=json_encode($types,JSON_THROW_ON_ERROR);
        if((int)$source['package_count']!==count($types)||$source['scope']!==$snapshot->scope||$source['coverage']!==$snapshot->coverage||$source['reason']!==''||(int)$source['skipped']!==0||$source['skip_reasons']!=='[]')throw new LogicException('Package type metadata does not match its result.');
        $observedAt=$snapshot->observedAt;$proof=provider_package_types_proof($source,$json,$observedAt);
        query('INSERT INTO '.table('provider_observation_package_types').' (id,types,observed_epoch,proof) VALUES (?,?,?,?)',[$source['id'],$json,$observedAt,$proof]);
    }elseif($snapshot!==null||!in_array($source['state'],['unavailable','refused','access_changed','credential'],true)||(int)$source['package_count']!==0||(int)$source['skipped']!==0||$source['skip_reasons']!=='[]'||$source['coverage']!=='unknown')throw new LogicException('Package type outcome is unsupported.');
    provider_settings_audit((int)$source['actor_id'],'package_types_finished',provider_package_types_finish_detail($source,$proof,$json,$observedAt));
    provider_package_types_advance('finished');
}
/** Validate an exact retained successful source; callers independently enforce latest/current context. */
function provider_package_types_observation(array $source):array {
    $retained=one('SELECT f.*,r.* FROM '.table('provider_refreshes').' f JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=?',[$source['id']]);
    if(!$retained||provider_package_types_result_context($retained)!==provider_package_types_result_context($source))throw new RuntimeException('Package type observation source changed.');
    $binding=provider_package_types_binding($retained);
    $doc=one('SELECT * FROM '.table('provider_observation_package_types').' WHERE id=?',[$source['id']]);
    if(!$doc||$source['operation']!==InventoryRead::PACKAGE_TYPES||$source['state']!=='complete'||$source['scope']!=='twenty-i|'.$source['profile_id'].'|'.InventoryRead::PACKAGE_TYPES||$source['coverage']!=='unknown'||(int)$source['skipped']!==0||$source['reason']!==''||$source['skip_reasons']!=='[]'||!hash_equals($binding['key_fingerprint'],$source['key_fingerprint'])||(int)$doc['observed_epoch']<(int)$source['started_epoch'])throw new RuntimeException('Package type observation needs recovery.');
    try{
        if(strlen($doc['types'])>8*1024*1024)throw new InvalidArgumentException('Package type document exceeds its bound.');
        $values=json_decode($doc['types'],true,4,JSON_THROW_ON_ERROR);
        if(!is_array($values)||!array_is_list($values)||count($values)>InventorySnapshot::MAX_ITEMS)throw new InvalidArgumentException('Invalid package type list.');
        $records=[];foreach($values as $value)$records[]=InventoryPackageType::fromStored($value);
        $types=provider_package_types_values($records);
    }catch(JsonException|InvalidArgumentException){throw new RuntimeException('Package type observation needs recovery.');}
    if(json_encode($types,JSON_THROW_ON_ERROR)!==$doc['types']||(int)$source['package_count']!==count($types)||!hash_equals(provider_package_types_proof($source,$doc['types'],(int)$doc['observed_epoch']),$doc['proof']))throw new RuntimeException('Package type observation integrity needs recovery.');
    return ['types'=>$types,'observed_epoch'=>(int)$doc['observed_epoch']];
}
