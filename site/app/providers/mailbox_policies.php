<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/mailbox_storage.php';
use CajunWebPro\Foundation\TwentyI\Operations\MailboxCreate;

/** Internal binding from an explicit domain link and the latest complete, recent package observation. */
function provider_mailbox_resource(string $linkId):array {
    provider_mailbox_id($linkId);
    if(!provider_settings_ready()||!provider_operations_ready()||!provider_inventory_ready()||!studio_ready()||!staff_ready())throw new RuntimeException('Prepare hosting inventory, operations and client accounts first.');
    $link=one('SELECT * FROM '.table('provider_domain_links').' WHERE id=?',[$linkId]);
    if(!$link||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]))throw new RuntimeException('The approved client domain is unavailable.');
    $profile=provider_settings_load($link['profile_id']);
    if((int)$profile['enabled']!==1||$profile['credentials']===''||(int)$profile['revision']!==(int)$link['profile_revision'])throw new RuntimeException('The provider connection changed. Refresh and reapprove the domain link.');
    $observation=one('SELECT f.id,f.profile_revision,f.started_epoch,r.state,r.key_fingerprint FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.profile_id=? AND COALESCE(f.operation,\'packages.list\')=\'packages.list\' ORDER BY f.seq DESC LIMIT 1',[$profile['id']]);
    if(!$observation||$observation['state']!=='complete'||(int)$observation['started_epoch']<time()-900||(int)$observation['started_epoch']>time()+30
        ||!provider_inventory_link_context_matches($link,$profile,$observation))throw new RuntimeException('Refresh the complete hosting inventory before managing mailboxes.');
    $package=one('SELECT names,enabled FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$observation['id'],$link['package_id']]);
    try{$names=$package?json_decode($package['names'],true,8,JSON_THROW_ON_ERROR):null;}catch(JsonException){$names=null;}
    if(!$package||(int)$package['enabled']!==1||!is_array($names)||!in_array($link['domain'],$names,true))throw new RuntimeException('The domain is no longer confirmed on its approved active hosting service.');
    MailboxCreate::domain($link['domain']);
    $binding=['link_id'=>$link['id'],'profile_id'=>$link['profile_id'],'profile_revision'=>(int)$link['profile_revision'],'package_id'=>(int)$link['package_id'],'client_id'=>(int)$link['client_id'],'domain'=>$link['domain'],'key_fingerprint'=>$link['key_fingerprint'],'linked_by'=>(int)$link['linked_by'],'linked_at'=>$link['linked_at']];
    return ['binding'=>$binding,'link_sha256'=>hash('sha256',provider_mailbox_json($binding)),'profile'=>$profile,'observation_id'=>$observation['id']];
}
function provider_mailbox_policy(string $linkId):?array {
    provider_mailbox_id($linkId);if(!provider_mailbox_ready())return null;
    $id=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_mailbox_policy_'.$linkId])['value']??'';
    if($id===''){
        if(one('SELECT id FROM '.table('provider_mailbox_policies').' WHERE link_id=? LIMIT 1',[$linkId]))throw new RuntimeException('Restore the original mailbox policy pointer.');
        return null;
    }
    provider_mailbox_id($id);$row=one('SELECT * FROM '.table('provider_mailbox_policies').' WHERE id=? AND link_id=?',[$id,$linkId]);
    if(!$row)throw new RuntimeException('Restore the original mailbox policy.');$p=provider_mailbox_document($row);
    if(array_keys($p)!==['format','id','link_id','revision','enabled','client_enabled','daily_limit','expires_epoch','owner_id','owner_auth_version','created_epoch','binding','link_sha256','credential_sha256']
        ||$p['link_id']!==$linkId||$p['revision']!==(int)$row['revision']||$p['owner_id']!==(int)$row['owner_id']||$p['created_epoch']!==(int)$row['created_epoch']
        ||!is_bool($p['enabled'])||!is_bool($p['client_enabled'])||!is_int($p['daily_limit'])||$p['daily_limit']<1||$p['daily_limit']>20
        ||!is_int($p['expires_epoch'])||!is_array($p['binding'])||!is_string($p['owner_auth_version'])||!preg_match('/^[a-f0-9]{32}$/D',$p['owner_auth_version'])
        ||!is_string($p['link_sha256'])||!hash_equals($p['link_sha256'],hash('sha256',provider_mailbox_json($p['binding'])))||!is_string($p['credential_sha256'])||!preg_match('/^[a-f0-9]{64}$/D',$p['credential_sha256']))throw new RuntimeException('Mailbox policy needs recovery.');
    $sequence=one('SELECT COUNT(*) AS total,MAX(revision) AS latest FROM '.table('provider_mailbox_policies').' WHERE link_id=?',[$linkId]);
    if($p['revision']<1||(int)$sequence['total']!==$p['revision']||(int)$sequence['latest']!==$p['revision'])throw new RuntimeException('Original mailbox policy history is incomplete.');
    $audit=all('SELECT user_id FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['provider.mailbox_policy','Policy '.$id.' sha256 '.$row['sha256']]);
    if(count($audit)!==1||(int)$audit[0]['user_id']!==$p['owner_id'])throw new RuntimeException('Original mailbox approval is missing.');
    return $p;
}
/** Owner policy is a limited permission, not a claim of an automatically discovered provider quota. */
function provider_mailbox_configure(string $linkId,string $expected,bool $enabled,bool $clientEnabled,int $dailyLimit,int $hours,bool $reviewed,#[SensitiveParameter] string $password):string {
    return staff_locked(fn()=>staff_transaction(function()use($linkId,$expected,$enabled,$clientEnabled,$dailyLimit,$hours,$reviewed,$password):string {
        $owner=provider_settings_owner($password);
        if(!provider_mailbox_ready())throw new RuntimeException('Prepare mailbox history first.');
        $old=provider_mailbox_policy($linkId);
        if(($old['id']??'')!==$expected)throw new RuntimeException('Mailbox permissions changed. Reload the form.');
        if($dailyLimit<1||$dailyLimit>20||$hours<1||$hours>720||($enabled&&!$reviewed))throw new InvalidArgumentException('Review provider mailbox availability, set 1–20 attempts per day and an expiry of 1–720 hours.');
        if($enabled||!$old){$resource=provider_mailbox_resource($linkId);$binding=$resource['binding'];$hash=$resource['link_sha256'];$credential=hash('sha256',$resource['profile']['credentials']);}
        else{$binding=$old['binding'];$hash=$old['link_sha256'];$credential=$old['credential_sha256'];}
        $revision=($old['revision']??0)+1;if($revision>2147483647)throw new RuntimeException('Mailbox policy history limit reached.');
        $id=bin2hex(random_bytes(16));$at=time();
        $p=['format'=>1,'id'=>$id,'link_id'=>$linkId,'revision'=>$revision,'enabled'=>$enabled,'client_enabled'=>$enabled&&$clientEnabled,'daily_limit'=>$dailyLimit,'expires_epoch'=>$at+$hours*3600,'owner_id'=>(int)$owner['id'],'owner_auth_version'=>$owner['auth_version'],'created_epoch'=>$at,'binding'=>$binding,'link_sha256'=>$hash,'credential_sha256'=>$credential];
        $doc=provider_mailbox_json($p);$sha=hash('sha256',$doc);
        query('INSERT INTO '.table('provider_mailbox_policies').' (id,link_id,revision,owner_id,created_epoch,document,sha256) VALUES (?,?,?,?,?,?,?)',[$id,$linkId,$revision,$p['owner_id'],$at,$doc,$sha]);
        save_setting('provider_mailbox_policy_'.$linkId,$id);provider_settings_audit($p['owner_id'],'mailbox_policy','Policy '.$id.' sha256 '.$sha);return $id;
    }));
}
function provider_mailbox_actor(?int $clientId,#[SensitiveParameter] ?string $password=null):array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Leave support access before managing mailboxes.');
    if($clientId!==null){
        $client=studio_client();if(!$client||$clientId<1||(int)$client['id']!==$clientId)throw new RuntimeException('Sign in as the client who owns this service.');
        return ['kind'=>'client','id'=>$clientId,'auth_version'=>$client['auth_version']];
    }
    $owner=$password===null?provider_settings_manager():provider_settings_owner($password);
    return ['kind'=>'owner','id'=>(int)$owner['id'],'auth_version'=>$owner['auth_version']];
}
function provider_mailbox_authority(string $policyId,?int $clientId,#[SensitiveParameter] ?string $password=null):array {
    $actor=provider_mailbox_actor($clientId,$password);
    if(!provider_mailbox_ready())throw new RuntimeException('Mailbox management is not prepared.');provider_mailbox_id($policyId);
    $row=one('SELECT link_id FROM '.table('provider_mailbox_policies').' WHERE id=?',[$policyId]);
    if(!$row)throw new RuntimeException('Mailbox permission is unavailable.');$p=provider_mailbox_policy($row['link_id']);
    if(!$p||$p['id']!==$policyId||!$p['enabled']||$p['expires_epoch']<=time()||($clientId!==null&&(!$p['client_enabled']||$p['binding']['client_id']!==$clientId)))throw new RuntimeException('Mailbox permissions changed or expired. Reload the page.');
    $owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$p['owner_id']]);$owner=$owner?staff_profile($owner):null;
    if(!$owner||$owner['role']!=='owner'||!hash_equals($owner['auth_version'],$p['owner_auth_version']))throw new RuntimeException('The original mailbox approval is no longer active.');
    $resource=provider_mailbox_resource($p['link_id']);
    if($resource['binding']!==$p['binding']||!hash_equals($p['credential_sha256'],hash('sha256',$resource['profile']['credentials'])))throw new RuntimeException('The approved mailbox service changed.');
    return [$actor,$p,$resource];
}
