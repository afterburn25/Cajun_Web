<?php
/** Scoped additional contacts for a client account: separate sign-in identities with project/billing/download permissions. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/clients/accounts.php';
require_once CWP_CODE_ROOT.'/database/schema/contacts_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/contacts_migrate.php';

function contacts_ready(): bool {return studio_ready()&&setting('contacts_schema_version')==='1';}
function contact_permissions(): array {return ['projects'=>'Project workspace & approvals','billing'=>'Invoices & payment requests','downloads'=>'Files & downloads'];}
function contact_perms_decode($json): array {
    if(!is_string($json))return [];
    $perms=json_decode($json,true);if(!is_array($perms))return [];
    return array_values(array_intersect($perms,array_keys(contact_permissions())));
}
/** The signed-in additional contact, or null for the primary client account and support sessions. A present but revoked contact identity invalidates the whole portal session and stops the request — it must never degrade to primary-account access. */
function studio_contact(): ?array {
    if(support_current()||empty($_SESSION['contact_id'])||!contacts_ready())return null;
    $c=one('SELECT * FROM '.table('studio_contacts').' WHERE id=? AND enabled=1',[(int)$_SESSION['contact_id']]);
    if(!$c||!hash_equals($c['auth_version'],(string)($_SESSION['contact_auth']??''))){
        unset($_SESSION['client_id'],$_SESSION['client_auth'],$_SESSION['contact_id'],$_SESSION['contact_auth']);
        fail_page('Your sign-in session ended. Please sign in again.',403);
    }
    return $c;
}
function contact_can(string $permission): bool {
    if(!isset(contact_permissions()[$permission]))return false;
    if(support_current()||empty($_SESSION['contact_id']))return true;
    $c=studio_contact();return $c!==null&&in_array($permission,contact_perms_decode($c['permissions']),true);
}
function contact_record(int $id,int $client=0): array {
    $r=one('SELECT * FROM '.table('studio_contacts').' WHERE id=?',[$id]);
    if(!$r||($client&&(int)$r['client_id']!==$client))throw new InvalidArgumentException('Contact not found.');
    return $r;
}
function contact_email_free(string $email,int $id): void {
    if(one('SELECT id FROM '.table('studio_clients').' WHERE email=?',[$email]))throw new InvalidArgumentException('That email belongs to the primary client account.');
    $other=one('SELECT id FROM '.table('studio_contacts').' WHERE email=? AND id<>?',[$email,$id]);
    if($other)throw new InvalidArgumentException('That email is already used by another contact.');
}
function contact_save(int $id,int $clientId,string $expected,string $name,string $email,array $perms,bool $enabled): int {
    return staff_locked(fn()=>staff_transaction(function() use($id,$clientId,$expected,$name,$email,$perms,$enabled): int {
        $actor=studio_client_manager();if(!contacts_ready())throw new RuntimeException('Enable client contacts first.');
        $name=trim($name);$email=strtolower(trim($email));
        if($id<0||$clientId<1||$name===''||strlen($name)>120||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1F\x7F]/',$name)||strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a contact name up to 120 bytes and a valid email.');
        $perms=array_values(array_intersect($perms,array_keys(contact_permissions())));
        if(!$perms)throw new InvalidArgumentException('Choose at least one contact permission.');
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new InvalidArgumentException('Active client not found.');
        contact_email_free($email,$id);
        if($id){
            $contact=one('SELECT id,auth_version FROM '.table('studio_contacts').' WHERE id=? AND client_id=? FOR UPDATE',[$id,$clientId]);
            if(!$contact)throw new InvalidArgumentException('Contact not found for this client.');
            if(!hash_equals($contact['auth_version'],$expected))throw new RuntimeException('This contact changed. Reload and review before saving.');
            query('UPDATE '.table('studio_contacts').' SET name=?,email=?,permissions=?,enabled=?,auth_version=?,invite_hash=NULL,invite_expires=NULL WHERE id=?',[$name,$email,json_encode($perms),(int)$enabled,bin2hex(random_bytes(16)),$id]);
        }else{
            if($expected!=='')throw new InvalidArgumentException('A new contact cannot carry a saved account version.');
            query('INSERT INTO '.table('studio_contacts').' (client_id,name,email,permissions,enabled,auth_version,created_at) VALUES (?,?,?,?,?,?,?)',[$clientId,$name,$email,json_encode($perms),(int)$enabled,bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);$id=(int)db()->lastInsertId();
        }
        studio_client_audit((int)$actor['id'],'studio_contact',$clientId);return $id;
    }));
}
function contact_invite(int $id,int $clientId,string $expected): string {
    return staff_locked(fn()=>staff_transaction(function() use($id,$clientId,$expected): string {
        $actor=studio_client_manager();if(!contacts_ready())throw new RuntimeException('Enable client contacts first.');
        $contact=one('SELECT id,enabled,auth_version FROM '.table('studio_contacts').' WHERE id=? AND client_id=? FOR UPDATE',[$id,$clientId]);
        if(!$contact||(int)$contact['enabled']!==1)throw new InvalidArgumentException('Enabled contact not found.');
        if(!hash_equals($contact['auth_version'],$expected))throw new RuntimeException('This contact changed. Reload and review before inviting.');
        $token=bin2hex(random_bytes(32));
        query('UPDATE '.table('studio_contacts').' SET invite_hash=?,invite_expires=?,auth_version=? WHERE id=?',[hash('sha256',$token),time()+86400,bin2hex(random_bytes(16)),$id]);
        studio_client_audit((int)$actor['id'],'contact_invite',$clientId);return studio_url(['cinvite'=>$token]);
    }));
}
function contact_activate(#[\SensitiveParameter] string $token,#[\SensitiveParameter] string $password): void {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Client security changes are blocked during support.');
    validate_password($password);
    if(!preg_match('/\A[a-f0-9]{64}\z/',$token))throw new InvalidArgumentException('Invalid invitation.');
    staff_locked(fn()=>staff_transaction(function() use($token,$password): void {
        if(!contacts_ready())throw new RuntimeException('The client workspace is unavailable.');
        $contact=one('SELECT * FROM '.table('studio_contacts').' WHERE invite_hash=? AND enabled=1 FOR UPDATE',[hash('sha256',$token)]);
        if(!$contact||(int)$contact['invite_expires']<=time())throw new InvalidArgumentException('This invitation expired or has already been used. Ask the studio for a new link.');
        query('UPDATE '.table('studio_contacts').' SET password_hash=?,auth_version=?,invite_hash=NULL,invite_expires=NULL WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$contact['id']]);
        studio_client_audit(0,'contact_activate',(int)$contact['client_id']);
    }));
}
