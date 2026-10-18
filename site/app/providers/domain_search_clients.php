<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/provider_domain_client_schema.php';
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';

function provider_domain_client_has_history():bool {
    return one('SELECT id FROM '.table('audit').' WHERE action IN (?,?,?,?) LIMIT 1',['provider.domain_client_prepared','provider.domain_client_configured','provider.domain_client_started','provider.domain_client_finished'])!==null;
}
/** No GET preparation, and no missing client history silently removed from shared limits. */
function provider_domain_client_family():bool {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_domain_client_schema_version'])['value']??'';
    $names=array_keys(provider_domain_client_schema(config()['db']['prefix']));$found=0;
    foreach($names as $name)if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))$found++;
    if($version==='1'&&$found===count($names))return true;
    if($version===''&&$found===0&&!one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_domain_client_policy'])&&!provider_domain_client_has_history())return false;
    throw new RuntimeException('Client domain search history needs recovery. Restore the original database or finish its initial preparation.');
}
function provider_domain_client_prepare(#[SensitiveParameter] string $password):void {
    staff_locked(function()use($password):void {
        $actor=provider_settings_owner($password);
        if(!provider_domain_search_ready()||!studio_ready())throw new RuntimeException('Prepare the client workspace and owner domain search first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_domain_client_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unsupported client search storage.');
        if($version===''&&(provider_domain_client_has_history()||one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_domain_client_policy'])))throw new RuntimeException('Restore the original client search history and preparation marker.');
        $schema=provider_domain_client_schema(config()['db']['prefix']);$missing=[];
        foreach($schema as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
                if($version==='1')throw new RuntimeException('Restore the original client search history before preparing.');
                $missing[$name]=$sql;continue;
            }
            provider_domain_search_validate_schema($name,$sql);
            if($version===''&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Restore the original client search history and preparation marker.');
        }
        foreach($missing as $sql)db()->exec($sql);
        foreach($schema as $name=>$sql)provider_domain_search_validate_schema($name,$sql);
        staff_transaction(function()use($actor):void {
            save_setting('provider_domain_client_schema_version','1');
            provider_settings_audit((int)$actor['id'],'domain_client_prepared','Client domain lookup history verified');
        });
    });
}
function provider_domain_client_id(string $id):void {
    if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Reload the current domain search form.');
}
/** Internal immutable policy, never returned to the client. */
function provider_domain_client_policy():?array {
    if(!provider_domain_client_family())return null;
    $id=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_domain_client_policy'])['value']??'';
    if($id===''){
        if(one('SELECT id FROM '.table('provider_domain_client_policies').' LIMIT 1')||one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',['provider.domain_client_configured']))throw new RuntimeException('Restore the original client search policy pointer.');
        return null;
    }
    provider_domain_client_id($id);
    $row=one('SELECT * FROM '.table('provider_domain_client_policies').' WHERE id=?',[$id]);
    if(!$row||strlen($row['document'])>8192||!hash_equals($row['sha256'],hash('sha256',$row['document'])))throw new RuntimeException('Client search policy needs recovery.');
    try{$p=json_decode($row['document'],true,8,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Client search policy needs recovery.');}
    if(!is_array($p)||array_keys($p)!==['format','id','revision','enabled','owner_id','owner_auth_version','profile_id','profile_revision','config_revision','credential_sha256','created_epoch']
        ||$p['format']!==1||$p['id']!==$id||$p['revision']!==(int)$row['revision']||$p['revision']<1||!is_bool($p['enabled'])
        ||$p['owner_id']!==(int)$row['owner_id']||$p['created_epoch']!==(int)$row['created_epoch']
        ||!is_string($p['owner_auth_version'])||!preg_match('/^[a-f0-9]{32}$/D',$p['owner_auth_version'])
        ||!is_string($p['profile_id'])||!is_int($p['profile_revision'])||!is_int($p['config_revision'])||!is_string($p['credential_sha256']))throw new RuntimeException('Client search policy needs recovery.');
    if($p['enabled']&&(!preg_match('/^[a-f0-9]{32}$/D',$p['profile_id'])||$p['profile_revision']<1||$p['config_revision']<1||!preg_match('/^[a-f0-9]{64}$/D',$p['credential_sha256'])))throw new RuntimeException('Client search policy needs recovery.');
    $sequence=one('SELECT COUNT(*) AS total,MAX(revision) AS latest FROM '.table('provider_domain_client_policies'));
    if((int)$sequence['total']!==$p['revision']||(int)$sequence['latest']!==$p['revision']||json_encode($p,JSON_THROW_ON_ERROR)!==$row['document'])throw new RuntimeException('Original client search policy history is missing or stale.');
    $audit=all('SELECT user_id FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['provider.domain_client_configured','Policy '.$id.' sha256 '.$row['sha256']]);
    if(count($audit)!==1||(int)$audit[0]['user_id']!==$p['owner_id'])throw new RuntimeException('Client search policy approval is missing.');
    return $p;
}
function provider_domain_client_configure(int $revision,bool $enabled,#[SensitiveParameter] string $password):void {
    staff_locked(fn()=>staff_transaction(function()use($revision,$enabled,$password):void {
        $owner=provider_settings_owner($password);
        if(!provider_domain_search_ready()||!provider_domain_client_family())throw new RuntimeException('Prepare client domain search first.');
        $old=provider_domain_client_policy();
        if($revision!==($old['revision']??0)||$revision>=2147483647)throw new RuntimeException('Client domain search settings changed. Reload the form.');
        $cfg=provider_domain_search_configuration_record();$profile=null;
        if($enabled){
            if(!$cfg['enabled'])throw new RuntimeException('Enable owner domain lookups and choose offered extensions first.');
            [, $profile]=provider_checks_authority($cfg['profile_id'],$cfg['profile_revision']);
        }
        $id=bin2hex(random_bytes(16));$p=['format'=>1,'id'=>$id,'revision'=>$revision+1,'enabled'=>$enabled,'owner_id'=>(int)$owner['id'],'owner_auth_version'=>$owner['auth_version'],'profile_id'=>$profile['id']??'','profile_revision'=>$profile?(int)$profile['revision']:0,'config_revision'=>$cfg['revision'],'credential_sha256'=>$profile?hash('sha256',$profile['credentials']):'','created_epoch'=>time()];
        $document=json_encode($p,JSON_THROW_ON_ERROR);$hash=hash('sha256',$document);
        query('INSERT INTO '.table('provider_domain_client_policies').' (id,revision,owner_id,created_epoch,document,sha256) VALUES (?,?,?,?,?,?)',[$id,$p['revision'],$p['owner_id'],$p['created_epoch'],$document,$hash]);
        save_setting('provider_domain_client_policy',$id);
        provider_settings_audit($p['owner_id'],'domain_client_configured','Policy '.$id.' sha256 '.$hash);
    }));
}
function provider_domain_client_actor(int $id):array {
    if($id<1||!studio_ready()||support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Sign in as a client outside support access.');
    $client=studio_client();
    if(!$client||(int)$client['id']!==$id)throw new RuntimeException('Your client session is no longer current. Sign in again.');
    return $client;
}
function provider_domain_client_authority(int $clientId,string $policyId):array {
    $client=provider_domain_client_actor($clientId);
    if(!provider_settings_ready()||!staff_ready()||!provider_domain_search_ready()||!provider_domain_client_family())throw new RuntimeException('Domain search is unavailable.');
    $policy=provider_domain_client_policy();
    if(!$policy||!$policy['enabled']||$policy['id']!==$policyId)throw new RuntimeException('Domain search settings changed or searches are unavailable. Reload the page.');
    $owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$policy['owner_id']]);$owner=$owner?staff_profile($owner):null;
    if(!$owner||$owner['role']!=='owner'||!hash_equals($owner['auth_version'],$policy['owner_auth_version']))throw new RuntimeException('Domain search is unavailable.');
    $cfg=provider_domain_search_configuration_record();$profile=provider_settings_load($policy['profile_id']);
    if(!$cfg['enabled']||$cfg['revision']!==$policy['config_revision']||$cfg['profile_id']!==$policy['profile_id']||$cfg['profile_revision']!==$policy['profile_revision']||(int)$profile['revision']!==$policy['profile_revision']||(int)$profile['enabled']!==1||$profile['credentials']===''||!hash_equals($policy['credential_sha256'],hash('sha256',$profile['credentials'])))throw new RuntimeException('Domain search settings changed or searches are unavailable. Reload the page.');
    return [$client,$profile,$cfg];
}
/** Client identity belongs in the audit detail, never in the staff user column. */
function provider_domain_client_audit(array $intent,string $action,string $outcome=''):void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (0,?,?,?)',['provider.domain_client_'.$action,'Client '.$intent['client_id'].' search '.$intent['id'].($outcome!==''?' '.$outcome:''),gmdate('Y-m-d H:i:s')]);
}
function provider_domain_client_record(array $intent,string $state,string $reason,?array $result,string $fingerprint,int $elapsed):void {
    staff_transaction(function()use($intent,$state,$reason,$result,$fingerprint,$elapsed):void {
        $now=time();
        query('INSERT INTO '.table('provider_domain_client_results').' (id,state,reason,result,key_fingerprint,finished_at,finished_epoch,elapsed_ms) VALUES (?,?,?,?,?,?,?,?)',[$intent['id'],$state,$reason,json_encode($result,JSON_THROW_ON_ERROR),$fingerprint,gmdate('Y-m-d H:i:s',$now),$now,max(0,min($elapsed,2147483647))]);
        provider_domain_client_audit($intent,'finished',$state.' '.$reason);
    });
}
function provider_domain_client_run(int $clientId,string $policyId,string $request,mixed $query,?callable $resolver=null,?callable $transport=null):string {
    provider_domain_client_id($policyId);provider_domain_client_id($request);
    $query=\CajunWebPro\Foundation\TwentyIInventoryReader\DomainSearch::name($query);
    if(db()->inTransaction())throw new LogicException('Domain lookups cannot run inside a transaction.');
    $claim=staff_locked(fn()=>staff_transaction(function()use($clientId,$policyId,$request,$query):array {
        [$client,$profile,$cfg]=provider_domain_client_authority($clientId,$policyId);
        $old=one('SELECT * FROM '.table('provider_domain_client_searches').' WHERE id=?',[$request]);
        $audit=all('SELECT user_id FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['provider.domain_client_started','Client '.$clientId.' search '.$request]);
        if($old){
            if(count($audit)!==1||(int)$audit[0]['user_id']!==0)throw new RuntimeException('Original lookup history needs recovery.');
            if((int)$old['client_id']!==$clientId||$old['policy_id']!==$policyId||$old['query_name']!==$query)throw new RuntimeException('This lookup request was already used. Reload the page.');
            return ['replay'=>true,'intent'=>$old];
        }
        if($audit)throw new RuntimeException('Original lookup history needs recovery.');
        if(!provider_domain_search_offered($query,$cfg['tlds']))throw new InvalidArgumentException('That extension is not currently offered.');
        $now=time();provider_domain_search_limits($profile['id'],$now);
        $intent=['id'=>$request,'profile_id'=>$profile['id'],'profile_revision'=>(int)$profile['revision'],'config_revision'=>$cfg['revision'],'policy_id'=>$policyId,'client_id'=>$clientId,'client_auth_version'=>$client['auth_version'],'query_name'=>$query,'tlds'=>json_encode($cfg['tlds'],JSON_THROW_ON_ERROR),'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now];
        query('INSERT INTO '.table('provider_domain_client_searches').' (id,profile_id,profile_revision,config_revision,policy_id,client_id,client_auth_version,query_name,tlds,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?,?,?)',array_values($intent));
        provider_domain_client_audit($intent,'started');return ['replay'=>false,'intent'=>$intent];
    }));
    if($claim['replay'])return $request;
    $intent=$claim['intent'];
    $authority=static function()use($intent):array {
        $a=provider_domain_client_authority($intent['client_id'],$intent['policy_id']);
        if(!hash_equals($intent['client_auth_version'],$a[0]['auth_version']))throw new RuntimeException('Client access changed.');
        return $a;
    };
    return provider_domain_search_execute($intent,$authority,'provider_domain_client_record',$resolver,$transport);
}
/** Client projection never carries provider IDs, credentials, policy details or another client's query. */
function provider_domain_client_history(int $clientId,int $page=1):array {
    return staff_locked(function()use($clientId,$page):array {
    provider_domain_client_actor($clientId);if(!provider_domain_client_family())return [];
    $page=max(1,min($page,100000));
    $rows=all('SELECT s.id,s.query_name,s.started_at,r.state,r.reason,r.result FROM '.table('provider_domain_client_searches').' s LEFT JOIN '.table('provider_domain_client_results').' r ON r.id=s.id WHERE s.client_id=? ORDER BY s.started_epoch DESC,s.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$clientId]);
    $out=[];
    foreach($rows as $row){
        try{$r=json_decode($row['result']??'null',true,8,JSON_THROW_ON_ERROR);}catch(JsonException){$r=null;}
        $suggestions=[];foreach((is_array($r['suggestions']??null)?array_slice($r['suggestions'],0,256):[]) as $name)if(is_string($name))$suggestions[]=$name;
        $out[]=['id'=>$row['id'],'domain'=>$row['query_name'],'at'=>$row['started_at'],'state'=>$row['state'],'reason'=>$row['reason'],'available'=>$row['state']==='checked'&&($r['query']??null)===$row['query_name']&&($r['registration_available']??null)===true,'suggestions'=>$suggestions];
    }
    return $out;
    });
}
