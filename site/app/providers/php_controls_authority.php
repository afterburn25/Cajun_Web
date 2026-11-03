<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/php_controls_delegation_storage.php';

/** These internal contexts never go directly to a public view or response. */
function provider_php_controls_context(array $actor,\CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding $binding,?array $permission):array {
    $d=['format'=>1,'operation'=>'packages.phpversion','actor'=>$actor,'binding'=>$binding->data,'permission_id'=>$permission['document']['id']??null,'permission_sha256'=>$permission['sha256']??null];
    return ['document'=>$d,'sha256'=>hash('sha256',provider_php_controls_json($d)),'binding'=>$binding];
}
function provider_php_controls_primary_client(int $clientId):array {
    provider_php_controls_guard(true);
    // Reject retained contact identities before studio_client() may clear/normalize them.
    if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Sign in directly as the primary client to change PHP.');
    $client=studio_client();
    if($clientId<1||!$client||(int)$client['id']!==$clientId||(int)$client['active']!==1||$client['password_hash']===''||!\CajunWebPro\Foundation\Access\AuthorizationVersion::valid($client['auth_version']))throw new RuntimeException('Your primary client session is no longer current.');
    return $client;
}
function provider_php_controls_client_password(int $clientId,#[SensitiveParameter] string $password):array {
    $client=provider_php_controls_primary_client($clientId);
    if(!password_verify($password,$client['password_hash']))throw new InvalidArgumentException('Your current password is not correct.');return $client;
}
function provider_php_controls_owner_context(string $linkId):array {
    provider_php_controls_guard(true);unset($GLOBALS['cwp_settings']);$owner=provider_settings_manager();
    if(!provider_php_controls_ready())throw new RuntimeException('Prepare PHP change delegation first.');
    $binding=provider_php_binding($linkId);
    return provider_php_controls_context(['kind'=>'staff','id'=>(int)$owner['id'],'auth_version'=>$owner['auth_version']],$binding,null);
}
function provider_php_controls_client_context(int $clientId,string $linkId):array {
    provider_php_controls_guard(true);unset($GLOBALS['cwp_settings']);$client=provider_php_controls_primary_client($clientId);
    $permission=provider_php_controls_permission($linkId);$d=$permission['document']??null;
    if(!$d||!$d['enabled']||$d['client_id']!==$clientId||!hash_equals($d['client_auth_version'],$client['auth_version']))throw new RuntimeException('PHP changes are not enabled for this client assignment.');
    if(!staff_ready())throw new RuntimeException('PHP change authorization is unavailable.');
    $owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$d['owner_id']]);$owner=$owner?staff_profile($owner):null;
    if(!$owner||$owner['role']!=='owner'||$owner['password_hash']===''||!hash_equals($d['owner_auth_version'],$owner['auth_version'])||!provider_php_assignment_matches($permission['binding']))throw new RuntimeException('The owner must review PHP change permission again.');
    $binding=provider_php_binding($linkId);
    if($binding->data['client_id']!==$clientId)throw new RuntimeException('This hosting assignment changed.');
    return provider_php_controls_context(['kind'=>'client','id'=>$clientId,'auth_version'=>$client['auth_version']],$binding,$permission);
}
function provider_php_controls_reauthorize(array $expected):array {
    provider_php_controls_guard(true);$d=$expected['document']??null;
    if(!is_array($d)||array_keys($d)!==['format','operation','actor','binding','permission_id','permission_sha256']||$d['format']!==1||$d['operation']!=='packages.phpversion'||!is_array($d['actor'])||array_keys($d['actor'])!==['kind','id','auth_version']||!is_int($d['actor']['id'])||!is_array($d['binding'])||!is_string($expected['sha256']??null)||!hash_equals(hash('sha256',provider_php_controls_json($d)),$expected['sha256']))throw new RuntimeException('PHP change authority needs recovery.');
    $binding=new \CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding($d['binding']);
    $current=match($d['actor']['kind']){'staff'=>provider_php_controls_owner_context($binding->data['link_id']),'client'=>provider_php_controls_client_context($d['actor']['id'],$binding->data['link_id']),default=>throw new RuntimeException('PHP change actor is unavailable.')};
    if(!hash_equals($current['sha256'],$expected['sha256']))throw new RuntimeException('PHP change access or reviewed hosting context changed.');return $current;
}
function provider_php_controls_delegation_review(string $linkId,bool $enabled):array {
    provider_php_controls_guard(true);unset($GLOBALS['cwp_settings']);$owner=provider_settings_manager();$old=provider_php_controls_permission($linkId);
    if(!provider_php_controls_ready())throw new RuntimeException('Prepare PHP change delegation first.');
    if($enabled){$binding=provider_php_binding($linkId);$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[$binding->data['client_id']]);if(!$client||$client['password_hash']===''||!\CajunWebPro\Foundation\Access\AuthorizationVersion::valid($client['auth_version']))throw new RuntimeException('The primary client must have a current sign-in account.');$clientAuth=$client['auth_version'];}
    else{if(!$old)throw new RuntimeException('No PHP change delegation exists.');$binding=$old['binding'];$clientAuth=$old['document']['client_auth_version'];}
    if($old&&$old['document']['client_id']!==$binding->data['client_id'])throw new RuntimeException('Restore the original assignment history.');
    $review=['format'=>1,'link_id'=>$linkId,'enabled'=>$enabled,'previous_id'=>$old['document']['id']??'','previous_sha256'=>$old['sha256']??str_repeat('0',64),'owner_id'=>(int)$owner['id'],'owner_auth_version'=>$owner['auth_version'],'client_auth_version'=>$clientAuth,'binding'=>$binding->data];
    return ['review_sha256'=>hash('sha256',provider_php_controls_json($review)),'permission_id'=>$review['previous_id'],'review'=>$review,'binding'=>$binding,'previous'=>$old];
}
function provider_php_controls_delegate(string $linkId,string $expectedPermissionId,string $eventId,bool $enabled,string $reviewSha256,#[SensitiveParameter] string $password):array {
    if(db()->inTransaction())throw new LogicException('PHP delegation cannot inherit a transaction.');
    provider_php_id($linkId);provider_php_id($eventId);if($expectedPermissionId!=='')provider_php_id($expectedPermissionId);
    if(preg_match('/\A[a-f0-9]{64}\z/',$reviewSha256)!==1)throw new InvalidArgumentException('Review this exact PHP delegation again.');
    return staff_locked(fn()=>staff_transaction(function()use($linkId,$expectedPermissionId,$eventId,$enabled,$reviewSha256,$password):array{
        unset($GLOBALS['cwp_settings']);$owner=provider_settings_owner($password);$old=provider_php_controls_permission($linkId);
        if(!provider_php_controls_ready())throw new RuntimeException('Prepare PHP change delegation first.');
        if(($old['document']['id']??'')===$eventId){$d=$old['document'];if($d['previous_id']!==$expectedPermissionId||$d['enabled']!==$enabled||$d['owner_id']!==(int)$owner['id']||!hash_equals($d['owner_auth_version'],$owner['auth_version'])||!hash_equals($d['review_sha256'],$reviewSha256))throw new RuntimeException('This PHP delegation request was already used.');return $old;}
        if(($old['document']['id']??'')!==$expectedPermissionId||one('SELECT id FROM '.table('provider_php_control_delegations').' WHERE id=?',[$eventId]))throw new RuntimeException('PHP delegation changed. Reload the owner form.');
        $review=provider_php_controls_delegation_review($linkId,$enabled);if(!hash_equals($review['review_sha256'],$reviewSha256))throw new RuntimeException('PHP delegation context changed. Review it again.');
        $v=$review['review'];$count=provider_php_controls_counter();if($count>=999999999999999)throw new RuntimeException('PHP delegation history is full.');
        $d=provider_php_controls_delegation_document(['format'=>1,'id'=>$eventId,'link_id'=>$linkId,'client_id'=>$review['binding']->data['client_id'],'revision'=>($old['document']['revision']??0)+1,'owner_id'=>(int)$owner['id'],'owner_auth_version'=>$owner['auth_version'],'client_auth_version'=>$v['client_auth_version'],'recorded_epoch'=>max(time(),$old['document']['recorded_epoch']??0),'previous_id'=>$expectedPermissionId,'previous_sha256'=>$v['previous_sha256'],'enabled'=>$enabled,'review_sha256'=>$reviewSha256,'binding'=>$review['binding']->data]);
        $json=provider_php_controls_json($d);$row=['id'=>$eventId,'link_id'=>$linkId,'client_id'=>$d['client_id'],'revision'=>$d['revision'],'owner_id'=>$d['owner_id'],'recorded_epoch'=>$d['recorded_epoch'],'previous_sha256'=>$d['previous_sha256'],'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];$row['proof']=provider_php_controls_proof($row);
        query('INSERT INTO '.table('provider_php_control_delegations').' (id,link_id,client_id,revision,owner_id,recorded_epoch,previous_sha256,document_json,document_sha256,proof) VALUES (?,?,?,?,?,?,?,?,?,?)',array_values($row));
        provider_settings_audit((int)$owner['id'],'php_control_delegation_changed',provider_php_controls_audit_detail($row));save_setting('provider_php_control_delegation_count',(string)($count+1));
        if(!provider_php_controls_ready())throw new RuntimeException('PHP delegation history is unavailable.');return provider_php_controls_decode($row);
    }));
}
