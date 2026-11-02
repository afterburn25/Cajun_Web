<?php
/** Client identity changes share the staff/hosting authorization guard. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';

function studio_client_manager():array {
    if(support_current() || array_key_exists('support',$_GET))throw new RuntimeException('Leave client support before changing client accounts.');
    $actor=admin_user();
    if(!$actor || !studio_ready() || !staff_can('clients.manage',$actor))throw new RuntimeException('Your current account does not have access to client administration.');
    return $actor;
}
function studio_client_audit(int $actor,string $action,int $client):void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,$action,'Client '.$client,gmdate('Y-m-d H:i:s')]);
}
function studio_client_version(array $client,string $expected):void {
    if(!preg_match('/\A[a-f0-9]{32}\z/',$expected) || !hash_equals($client['auth_version'],$expected))throw new RuntimeException('This client changed. Reload and review the current account before saving or creating an invitation.');
}
function studio_client_save(int $id,string $expected,string $name,string $email,bool $active):int {
    return staff_locked(fn()=>staff_transaction(function()use($id,$expected,$name,$email,$active):int {
        $actor=studio_client_manager();$name=trim($name);$email=strtolower(trim($email));
        if($id<0 || $id>2147483647 || $name==='' || strlen($name)>120 || preg_match('//u',$name)!==1 || preg_match('/[\x00-\x1F\x7F]/',$name) || strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a client name up to 120 bytes and a valid email.');
        if($id){
            $client=one('SELECT id,auth_version FROM '.table('studio_clients').' WHERE id=? FOR UPDATE',[$id]);
            if(!$client)throw new InvalidArgumentException('Client not found.');
            studio_client_version($client,$expected);
            query('UPDATE '.table('studio_clients').' SET name=?,email=?,active=?,auth_version=?,invite_hash=NULL,invite_expires=NULL WHERE id=?',[$name,$email,(int)$active,bin2hex(random_bytes(16)),$id]);
        }else{
            if($expected!=='')throw new InvalidArgumentException('A new client cannot carry a saved account version.');
            query('INSERT INTO '.table('studio_clients').' (name,email,active,auth_version,created_at) VALUES (?,?,?,?,?)',[$name,$email,(int)$active,bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);$id=(int)db()->lastInsertId();
        }
        studio_client_audit((int)$actor['id'],'studio_client',$id);return $id;
    }));
}
function studio_new_invite(int $id,string $expected):string {
    return staff_locked(fn()=>staff_transaction(function()use($id,$expected):string {
        $actor=studio_client_manager();
        $client=one('SELECT id,active,auth_version FROM '.table('studio_clients').' WHERE id=? FOR UPDATE',[$id]);
        if(!$client || (int)$client['active']!==1)throw new InvalidArgumentException('Active client not found.');
        studio_client_version($client,$expected);$token=bin2hex(random_bytes(32));
        query('UPDATE '.table('studio_clients').' SET invite_hash=?,invite_expires=?,auth_version=? WHERE id=?',[hash('sha256',$token),time()+86400,bin2hex(random_bytes(16)),$id]);
        studio_client_audit((int)$actor['id'],'studio_invite',$id);return studio_url(['invite'=>$token]);
    }));
}
function studio_activate(#[\SensitiveParameter] string $token,#[\SensitiveParameter] string $password):void {
    if(support_current() || array_key_exists('support',$_GET))throw new RuntimeException('Client security changes are blocked during support.');
    validate_password($password);
    if(!preg_match('/\A[a-f0-9]{64}\z/',$token))throw new InvalidArgumentException('Invalid invitation.');
    staff_locked(fn()=>staff_transaction(function()use($token,$password):void {
        if(!studio_ready())throw new RuntimeException('The client workspace is unavailable.');
        $client=one('SELECT * FROM '.table('studio_clients').' WHERE invite_hash=? AND active=1 FOR UPDATE',[hash('sha256',$token)]);
        if(!$client || (int)$client['invite_expires']<=time())throw new InvalidArgumentException('This invitation expired or has already been used. Ask the studio for a new link.');
        $hash=password_hash($password,PASSWORD_DEFAULT);
        if((int)$client['invite_expires']<=time())throw new InvalidArgumentException('This invitation expired. Ask the studio for a new link.');
        query('UPDATE '.table('studio_clients').' SET password_hash=?,auth_version=?,invite_hash=NULL,invite_expires=NULL WHERE id=?',[$hash,bin2hex(random_bytes(16)),$client['id']]);
        // Existing audit schema reserves zero for an action without a staff identity.
        studio_client_audit(0,'studio_activate',(int)$client['id']);
    }));
}
