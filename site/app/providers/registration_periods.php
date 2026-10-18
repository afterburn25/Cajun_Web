<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot,RegistrationPeriod};
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/registration_period_storage.php';

/** Owner approval is interactive; the common executor continues to own credentials, limits and dispatch. */
function provider_registration_period_authority(string $profile,int $revision,#[SensitiveParameter] ?string $password=null):array {
    provider_registration_period_guard();$owner=$password===null?provider_settings_manager():provider_settings_owner($password);
    if(!provider_registration_period_ready())throw new RuntimeException('Prepare registration period storage first.');
    [, $row]=provider_inventory_authority($profile,$revision,$password);return [$owner,$row];
}
function provider_registration_period_refresh(string $profile,int $revision,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    return provider_inventory_refresh_attempt($profile,$revision,$password,$resolver,$transport,InventoryRead::DOMAIN_REGISTRATION_PERIODS);
}
function provider_registration_period_intent_authority(array $started):void {
    [$owner,$row]=provider_registration_period_authority($started['profile_id'],$started['profile_revision']);
    $context=one('SELECT credential_sha256 FROM '.table('provider_registration_period_reads').' WHERE id=?',[$started['id']]);
    if(!$context||(int)$owner['id']!==$started['actor_id']||!hash_equals($context['credential_sha256'],hash('sha256',$row['credentials'])))throw new RuntimeException('Registration period access changed.');
}
/** Companion context uses the shared durable intent ID/sequence; it never dispatches a request. */
function provider_registration_period_start(array $started):void {
    provider_registration_period_guard(true);$row=provider_settings_load($started['profile_id']);
    if((int)$row['revision']!==$started['profile_revision']||$row['credentials']==='')throw new RuntimeException('Saved provider settings changed.');
    $ordinal=(int)(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_registration_period_count'])['value']??-1)+1;
    if($ordinal<1||$ordinal>2147483647)throw new RuntimeException('Registration period history limit reached.');
    $intent=one('SELECT seq FROM '.table('provider_refreshes').' WHERE id=?',[$started['id']]);if(!$intent)throw new LogicException('The shared refresh intent is required.');
    $d=['id'=>$started['id'],'ordinal'=>$ordinal,'profile_id'=>$started['profile_id'],'profile_revision'=>$started['profile_revision'],'actor_id'=>$started['actor_id'],'started_epoch'=>$started['started_epoch'],'refresh_seq'=>(int)$intent['seq'],'credential_sha256'=>hash('sha256',$row['credentials'])];
    $bytes=provider_registration_period_json($d);$sha=hash('sha256',$bytes);
    query('INSERT INTO '.table('provider_registration_period_reads').' (id,ordinal,profile_id,profile_revision,actor_id,started_epoch,refresh_seq,credential_sha256,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?,?,?)',[...array_values($d),$bytes,$sha]);
    save_setting('provider_registration_period_count',(string)$ordinal);provider_settings_audit($started['actor_id'],'registration_period_started','Registration periods '.$started['id'].' sha256 '.$sha);
}
/** Called by the common recorder after inserting its result and before its final audit, in the same transaction. */
function provider_registration_period_record(array $started,string $state,?InventorySnapshot $snapshot):void {
    provider_registration_period_guard(true);$context=one('SELECT * FROM '.table('provider_registration_period_reads').' WHERE id=?',[$started['id']]);if(!$context)provider_registration_period_error();
    $result=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$started['id']]);if(!$result)throw new LogicException('The shared refresh result is required.');
    $periods=[];
    if(provider_inventory_observed($state)){
        if(!$snapshot||$snapshot->scope!=='twenty-i|'.$started['profile_id'].'|'.InventoryRead::DOMAIN_REGISTRATION_PERIODS||$snapshot->state!==$state)throw new LogicException('An exact registration period snapshot is required.');
        foreach($snapshot->packages as $record){if(!$record instanceof RegistrationPeriod)throw new LogicException('Only typed registration period rows may persist.');$periods[]=['tld'=>$record->tld,'years'=>$record->years];}
    }elseif($snapshot!==null)throw new LogicException('An unavailable period read cannot carry observations.');
    $d=['format'=>1,'id'=>$started['id'],'state'=>$state,'observed_epoch'=>$snapshot?->observedAt??0,'periods'=>$periods];$bytes=provider_registration_period_json($d);
    if(strlen($bytes)>2097152)throw new LogicException('Registration period observation exceeds its local bound.');
    $sha=hash('sha256',$bytes);$resultSha=provider_registration_period_result_hash($result);
    query('INSERT INTO '.table('provider_observation_registration_periods').' (id,document_json,document_sha256,result_sha256) VALUES (?,?,?,?)',[$started['id'],$bytes,$sha,$resultSha]);
    $finished=(int)(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_registration_period_finished'])['value']??-1);if($finished<0||$finished>=2147483647)provider_registration_period_error();
    save_setting('provider_registration_period_finished',(string)($finished+1));provider_settings_audit($started['actor_id'],'registration_period_saved','Registration periods '.$started['id'].' sha256 '.$sha.' result '.$resultSha);
}
function provider_registration_period_document(array $row):array {
    try{$d=json_decode($row['document_json'],true,8,JSON_THROW_ON_ERROR);}catch(JsonException){provider_registration_period_error();}
    if(!is_array($d)||array_keys($d)!==['format','id','state','observed_epoch','periods']||$d['format']!==1||$d['id']!==$row['id']||$d['state']!==$row['state']||!is_int($d['observed_epoch'])||$d['observed_epoch']<0||!is_array($d['periods'])||!array_is_list($d['periods'])||count($d['periods'])>4096||provider_registration_period_json($d)!==$row['document_json']||!hash_equals($row['document_sha256'],hash('sha256',$row['document_json'])))provider_registration_period_error();
    $out=[];
    foreach($d['periods'] as $record){
        if(!is_array($record)||array_keys($record)!==['tld','years'])provider_registration_period_error();
        try{$r=RegistrationPeriod::fromItem($record['tld'],$record['years']);}catch(InvalidArgumentException){provider_registration_period_error();}
        if(isset($out[$r->tld])||$r->years!==$record['years'])provider_registration_period_error();$out[$r->tld]=$r->years;
    }
    if(count($out)!==(int)$row['package_count']||(!provider_inventory_observed($d['state'])&&($out!==[]||$d['observed_epoch']!==0)))provider_registration_period_error();
    return $d+['map'=>$out];
}
/** Internal, already authorized. Latest intent by sequence wins, including an unfinished or failed read. */
function provider_registration_period_projection(array $profile,array $tlds,int $page=1,int $catalogPage=1):array {
    provider_registration_period_guard();
    if(!provider_registration_period_ready())return ['state'=>'not_prepared','at'=>null,'rows'=>[],'history'=>[],'more'=>false];
    $history=all('SELECT c.id,c.profile_revision,c.started_epoch,c.credential_sha256,r.seq,o.state,o.reason,o.finished_at,o.package_count,o.skipped FROM '.table('provider_registration_period_reads').' c JOIN '.table('provider_refreshes').' r ON r.id=c.id LEFT JOIN '.table('provider_refresh_results').' o ON o.id=c.id WHERE c.profile_id=? ORDER BY r.seq DESC LIMIT 21 OFFSET '.((max(1,min($page,100000))-1)*20),[$profile['id']]);
    $latest=one('SELECT c.id,c.profile_revision,c.credential_sha256,o.state,o.reason,o.package_count,o.finished_at,s.document_json,s.document_sha256 FROM '.table('provider_registration_period_reads').' c JOIN '.table('provider_refreshes').' r ON r.id=c.id LEFT JOIN '.table('provider_refresh_results').' o ON o.id=c.id LEFT JOIN '.table('provider_observation_registration_periods').' s ON s.id=c.id WHERE c.profile_id=? ORDER BY r.seq DESC LIMIT 1',[$profile['id']]);
    $out=['state'=>'not_observed','at'=>null,'rows'=>[],'history'=>array_map(static fn(array $h):array=>['state'=>$h['state']??'pending','reason'=>$h['reason']??'','at'=>gmdate('Y-m-d H:i:s',(int)$h['started_epoch']),'count'=>(int)($h['package_count']??0),'skipped'=>(int)($h['skipped']??0),'revision'=>(int)$h['profile_revision']],array_slice($history,0,20)),'more'=>count($history)>20];
    if(!$latest)return $out;
    $out['at']=$latest['finished_at'];
    if((int)$profile['enabled']!==1||$profile['credentials']===''||(int)$latest['profile_revision']!==(int)$profile['revision']||!hash_equals($latest['credential_sha256'],hash('sha256',$profile['credentials']))){$out['state']='settings_changed';return $out;}
    $out['state']=$latest['state']??'pending';if($latest['state']===null)return $out;
    $d=provider_registration_period_document($latest);
    if(!provider_inventory_observed($latest['state']))return $out;
    // Empty tlds denotes the owner's bounded catalog page, never an unrestricted client projection.
    $map=$d['map'];$keys=$tlds?:array_keys($map);
    $offset=$tlds?0:(max(1,min($catalogPage,100000))-1)*50;
    foreach(array_slice($keys,$offset,50) as $tld){$key=str_starts_with($tld,'.')?$tld:'.'.$tld;$out['rows'][]=['tld'=>$key,'years'=>$map[$key]??null];}
    $out['total']=count($map);$out['listed']=count($out['rows']);$out['more_catalog']=count($keys)>$offset+50;return $out;
}
function provider_registration_period_owner_context(string $profile,int $page=1,int $catalogPage=1):array {
    return staff_locked(function()use($profile,$page,$catalogPage):array{provider_settings_manager();return provider_registration_period_projection(provider_settings_load($profile),[],$page,$catalogPage);});
}
function provider_registration_period_client_context(int $client,string $policy):array {
    return staff_locked(function()use($client,$policy):array{
        require_once CWP_CODE_ROOT.'/app/providers/domain_search.php';[, $profile,$cfg]=provider_domain_client_authority($client,$policy);
        if(!$cfg['tlds'])throw new RuntimeException('Domain search is unavailable.');$out=provider_registration_period_projection($profile,$cfg['tlds']);unset($out['history'],$out['more']);return $out;
    });
}
