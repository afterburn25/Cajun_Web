<?php
/** Multiple email-account preparation. This module never opens a network connection. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Access\AccessContext;
use CajunWebPro\Foundation\Access\MailboxGrant;
use CajunWebPro\Foundation\Access\PermissionPolicy;
use CajunWebPro\Foundation\Webmail\MailboxAssignment;
use CajunWebPro\Foundation\Webmail\MailboxProfile;

require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/mail/credentials.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_accounts_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_accounts_migrate.php';
foreach (['MailboxProfile','MailboxAssignment'] as $class) require_once CWP_CODE_ROOT.'/includes/foundation/webmail/'.$class.'.php';

function mail_accounts_ready(): bool {
    return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_accounts_schema_version'])['value']??'')==='1';
}
function mail_accounts_actor(bool $ready=true): array {
    if (support_current() || array_key_exists('support',$_GET)) throw new RuntimeException('Leave client support before using staff email accounts.');
    $actor=admin_user();
    if (!$actor) throw new RuntimeException('An active administrator account is required.');
    if (!staff_ready()) throw new RuntimeException('Enable administrator accounts in Administrators first, then return to email accounts.');
    if ($ready && !mail_accounts_ready()) throw new RuntimeException('An owner must enable email accounts first.');
    return $actor;
}
function mail_accounts_manager(bool $ready=true): array {
    $actor=mail_accounts_actor($ready);
    if ($actor['role']!=='owner' || !staff_can('mail.accounts.manage',$actor)) throw new RuntimeException('Only an owner can manage email accounts and assignments.');
    return $actor;
}
function mail_accounts_owner(#[\SensitiveParameter] string $password,bool $ready=true): array {
    $actor=mail_accounts_manager($ready);
    if (!admin_session_matches($actor)) throw new InvalidArgumentException('Your administrator session is no longer valid. Sign in again.');
    return $actor;
}
function mail_accounts_audit(int $actor,string $action,string $detail): void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'mail_accounts.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
function mail_accounts_fields(): array {
    return ['label'=>120,'address'=>190,'imap_host'=>253,'imap_port'=>5,'imap_encryption'=>10,'imap_username'=>190,'smtp_host'=>253,'smtp_port'=>5,'smtp_encryption'=>10,'smtp_username'=>190];
}
function mail_accounts_content(array $input): array {
    $values=[];
    foreach (mail_accounts_fields() as $key=>$max) {
        $value=$input[$key]??null;
        if (!is_string($value)) throw new InvalidArgumentException('Complete all email account fields.');
        $value=trim($value);
        if ($value==='' || strlen($value)>$max || !preg_match('//u',$value) || preg_match('/[\x00-\x1F\x7F]/',$value)) throw new InvalidArgumentException('Check the email account fields and their length limits.');
        $values[$key]=$value;
    }
    if (!filter_var($values['address'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
    foreach (['imap','smtp'] as $protocol) {
        $host=strtolower($values[$protocol.'_host']);
        // Validate names here; future transports must separately validate DNS, TLS and egress.
        if (!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME) || !str_contains($host,'.') || str_ends_with($host,'.')) throw new InvalidArgumentException('Enter a full incoming and outgoing server hostname without a URL, path or port.');
        $values[$protocol.'_host']=$host;
        $port=$values[$protocol.'_port'];
        if (!ctype_digit($port) || (int)$port<1 || (int)$port>65535) throw new InvalidArgumentException('Server ports must be from 1 to 65535.');
        $values[$protocol.'_port']=(int)$port;
        if (!in_array($values[$protocol.'_encryption'],['ssl','starttls'],true)) throw new InvalidArgumentException('Choose TLS or STARTTLS for both servers. Plaintext connections are unavailable.');
    }
    return $values;
}
function mail_accounts_id(string $id): void {
    if (!preg_match('/^[a-f0-9]{32}$/D',$id)) throw new InvalidArgumentException('Email account unavailable.');
}
function mail_accounts_profile(array $row): MailboxProfile {
    return new MailboxProfile($row['id'],$row['label'],(string)$row['owner_id'],$row['imap_host'],(int)$row['imap_port'],$row['imap_encryption'],
        $row['smtp_host'],(int)$row['smtp_port'],$row['smtp_encryption'],'account:'.$row['id'],(int)$row['enabled']===1?MailboxProfile::STATUS_CONFIGURED:MailboxProfile::STATUS_DISABLED);
}
/** Explicit projection: passwords and ciphertext never leave the persistence boundary. */
function mail_accounts_projection(array $row): array {
    $keys=['id','revision',...array_keys(mail_accounts_fields()),'owner_id','enabled','updated_by','updated_at'];$result=[];
    foreach ($keys as $key) $result[$key]=$row[$key];
    $result['has_credentials']=$row['credentials']!=='';$result['connection']='untested';return $result;
}
function mail_accounts_load(string $id,bool $lock=false): array {
    mail_accounts_id($id);
    $row=one('SELECT * FROM '.table('mail_accounts').' WHERE id=?'.($lock?' FOR UPDATE':''),[$id]);
    if (!$row) throw new InvalidArgumentException('Email account unavailable.');return $row;
}
function mail_accounts_get(string $id): array {
    mail_accounts_manager();return mail_accounts_projection(mail_accounts_load($id));
}
function mail_accounts_list(int $page=1): array {
    mail_accounts_manager();$page=max(1,min($page,100000));
    return array_map('mail_accounts_projection',all('SELECT * FROM '.table('mail_accounts').' ORDER BY label,id LIMIT 21 OFFSET '.(($page-1)*20)));
}
function mail_accounts_history(string $id,int $page=1): array {
    mail_accounts_manager();mail_accounts_load($id);$page=max(1,min($page,100000));
    return all('SELECT r.*,u.name AS actor_name FROM '.table('mail_account_revisions').' r LEFT JOIN '.table('users').' u ON u.id=r.actor_id WHERE r.account_id=? ORDER BY revision DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id]);
}
function mail_accounts_snapshot(string $id,int $revision,int $actor,string $action): void {
    $metadata=mail_accounts_projection(mail_accounts_load($id));
    query('INSERT INTO '.table('mail_account_revisions').' (account_id,revision,metadata,action,actor_id,recorded_at) VALUES (?,?,?,?,?,?)',[$id,$revision,json_encode($metadata,JSON_THROW_ON_ERROR),$action,$actor,gmdate('Y-m-d H:i:s')]);
    mail_accounts_audit($actor,$action,'Account '.$id.' revision '.$revision);
}
function mail_accounts_save(string $id,int $revision,array $input,bool $enabled,#[\SensitiveParameter] array $passwords,#[\SensitiveParameter] string $password): string {
    return staff_locked(fn()=>staff_transaction(function() use ($id,$revision,$input,$enabled,$passwords,$password): string {
        $actor=mail_accounts_owner($password);$old=$id!==''?mail_accounts_load($id,true):null;
        if ($revision<0 || (int)($old['revision']??0)!==$revision) throw new RuntimeException('This email account changed. Reload before saving; your submitted settings are kept below.');
        $values=mail_accounts_content($input);$id=$old?$id:bin2hex(random_bytes(16));
        $secrets=mail_accounts_unseal($id,$old['credentials']??'');
        foreach (array_keys($secrets) as $key) {
            $value=$passwords[$key]??'';
            if (!is_string($value)) throw new InvalidArgumentException('Enter valid account passwords.');
            if ($value!=='') $secrets[$key]=$value;
        }
        $encoded=mail_accounts_seal($id,$secrets);$now=gmdate('Y-m-d H:i:s');$owner=(int)($old['owner_id']??$actor['id']);
        mail_accounts_profile(['id'=>$id,'owner_id'=>$owner,'enabled'=>(int)$enabled]+$values);
        $args=[$revision+1,...array_values($values),$encoded,(int)$enabled,(int)$actor['id'],$now];
        if ($old) {
            query('UPDATE '.table('mail_accounts').' SET revision=?,'.implode(',',array_map(fn($key)=>$key.'=?',array_keys($values))).',credentials=?,enabled=?,updated_by=?,updated_at=? WHERE id=?',[...$args,$id]);
        } else {
            query('INSERT INTO '.table('mail_accounts').' (revision,'.implode(',',array_keys($values)).',credentials,enabled,updated_by,updated_at,id,owner_id) VALUES ('.implode(',',array_fill(0,count($args)+2,'?')).')',[...$args,$id,$owner]);
        }
        mail_accounts_snapshot($id,$revision+1,(int)$actor['id'],$old?'save':'create');return $id;
    }));
}
function mail_accounts_state(string $id,int $revision,string $action,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function() use ($id,$revision,$action,$password): void {
        $actor=mail_accounts_owner($password);$row=mail_accounts_load($id,true);
        if ($revision<1 || (int)$row['revision']!==$revision) throw new RuntimeException('This email account changed. Reload before changing its state.');
        if (!in_array($action,['enable','disable','clear'],true)) throw new InvalidArgumentException('Unknown email account action.');
        if ($action==='enable') {
            mail_accounts_profile($row);mail_accounts_passwords(mail_accounts_unseal($id,$row['credentials']));
        }
        query('UPDATE '.table('mail_accounts').' SET revision=?,enabled=?,credentials=?,updated_by=?,updated_at=? WHERE id=?',[$revision+1,(int)($action==='enable'),$action==='clear'?'':$row['credentials'],(int)$actor['id'],gmdate('Y-m-d H:i:s'),$id]);
        mail_accounts_snapshot($id,$revision+1,(int)$actor['id'],$action);
    }));
}
function mail_accounts_grants(string $id): array {
    mail_accounts_manager();mail_accounts_load($id);
    return all('SELECT g.*,u.name,u.email,p.role,p.enabled AS staff_enabled FROM '.table('mail_account_grants').' g JOIN '.table('users').' u ON u.id=g.user_id LEFT JOIN '.table('staff_profiles').' p ON p.user_id=u.id WHERE g.account_id=? ORDER BY u.name,u.id',[$id]);
}
function mail_accounts_grant_history(string $id,int $page=1): array {
    mail_accounts_manager();mail_accounts_load($id);$page=max(1,min($page,100000));
    return all('SELECT r.*,u.name AS staff_name,a.name AS actor_name FROM '.table('mail_grant_revisions').' r LEFT JOIN '.table('users').' u ON u.id=r.user_id LEFT JOIN '.table('users').' a ON a.id=r.actor_id WHERE r.account_id=? ORDER BY r.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id]);
}
function mail_accounts_grant(string $id,int $userId,int $revision,bool $read,bool $send,bool $enabled,int $days,#[\SensitiveParameter] string $password): void {
    if ($userId<1 || $revision<0 || $days<0 || $days>365) throw new InvalidArgumentException('Choose an administrator and an expiry from 1 to 365 days, or no expiry.');
    staff_locked(fn()=>staff_transaction(function() use ($id,$userId,$revision,$read,$send,$enabled,$days,$password): void {
        $actor=mail_accounts_owner($password);mail_accounts_load($id,true);
        $old=one('SELECT * FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=? FOR UPDATE',[$id,$userId]);
        if ((int)($old['revision']??0)!==$revision) throw new RuntimeException('This assignment changed. Reload before saving it.');
        $user=one('SELECT * FROM '.table('users').' WHERE id=?',[$userId]);$profile=$user?staff_profile($user):null;
        if (!$user || ($enabled && (!$profile || $user['password_hash']===''))) throw new InvalidArgumentException('Choose an enabled administrator who has finished password setup. Disabled assignments may still be revoked.');
        if ($enabled && !$read && !$send) throw new InvalidArgumentException('Choose read or send access for an enabled assignment.');
        $at=time();$expires=$days?$at+$days*86400:null;$now=gmdate('Y-m-d H:i:s',$at);
        new MailboxAssignment($id.':'.$userId.':'.($revision+1),$id,(string)$userId,(string)$actor['id'],$at,$expires);
        $values=[$revision+1,(int)$read,(int)$send,(int)$enabled,$at,$expires,(int)$actor['id'],$now,$id,$userId];
        if ($old) query('UPDATE '.table('mail_account_grants').' SET revision=?,can_read=?,can_send=?,enabled=?,issued_at=?,expires_at=?,issued_by=?,updated_at=? WHERE account_id=? AND user_id=?',$values);
        else query('INSERT INTO '.table('mail_account_grants').' (revision,can_read,can_send,enabled,issued_at,expires_at,issued_by,updated_at,account_id,user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',$values);
        query('INSERT INTO '.table('mail_grant_revisions').' (revision,can_read,can_send,enabled,issued_at,expires_at,actor_id,recorded_at,account_id,user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',$values);
        mail_accounts_audit((int)$actor['id'],'assignment','Account '.$id.' staff '.$userId.' revision '.($revision+1));
    }));
}
/** Fresh session, role, profile and assignment are checked on every call. No transport authorization is implied. */
function mail_accounts_can(string $permission,string $id): bool {
    if (!in_array($permission,['mail.read','mail.send'],true)) return false;
    try {$actor=mail_accounts_actor();mail_accounts_id($id);} catch (InvalidArgumentException|RuntimeException $e) {if ($e instanceof PDOException) throw $e;return false;}
    $row=one('SELECT g.*,a.enabled AS account_enabled,(a.credentials<>\'\') AS has_credentials FROM '.table('mail_account_grants').' g JOIN '.table('mail_accounts').' a ON a.id=g.account_id WHERE g.account_id=? AND g.user_id=?',[$id,$actor['id']]);
    if (!$row || (int)$row['account_enabled']!==1 || (int)$row['enabled']!==1 || (int)$row['has_credentials']!==1) return false;
    try {
        $assignment=new MailboxAssignment((string)$row['id'],$id,(string)$actor['id'],(string)$row['issued_by'],(int)$row['issued_at'],$row['expires_at']===null?null:(int)$row['expires_at']);
        if (!$assignment->active(time())) return false;
    } catch (InvalidArgumentException) {return false;}
    $grant=new MailboxGrant($id,(int)$row['can_read']===1,(int)$row['can_send']===1,true);
    $context=new AccessContext((string)$actor['id'],'staff',$actor['role'],true,(string)$actor['auth_version'],(string)($_SESSION['auth_version']??''),json_decode($actor['permissions'],true),[$grant]);
    return (new PermissionPolicy())->allows($context,$permission,$id);
}
/** Navigation eligibility only; actual data still uses current per-account decisions. */
function mail_accounts_access(array $actor): bool {
    if (support_current() || !staff_ready()) return false;
    $permissions=json_decode($actor['permissions']??'[]',true);
    return ($actor['role']??'')==='owner' || (is_array($permissions) && (in_array('mail.read',$permissions,true) || in_array('mail.send',$permissions,true)));
}
function mail_accounts_assigned(int $page=1): array {
    $actor=mail_accounts_actor();
    if (!mail_accounts_access($actor)) throw new RuntimeException('Your account has no staff email permission.');
    $permissions=json_decode($actor['permissions'],true);$owner=$actor['role']==='owner';
    $read=$owner || in_array('mail.read',$permissions,true);$send=$owner || in_array('mail.send',$permissions,true);
    $page=max(1,min($page,100000));$now=time();
    $rows=all('SELECT a.id,a.label,a.address,g.expires_at FROM '.table('mail_accounts').' a JOIN '.table('mail_account_grants').' g ON g.account_id=a.id WHERE g.user_id=? AND g.enabled=1 AND a.enabled=1 AND a.credentials<>\'\' AND g.issued_at<=? AND (g.expires_at IS NULL OR g.expires_at>?) AND ((g.can_read=1 AND ?=1) OR (g.can_send=1 AND ?=1)) ORDER BY a.label,a.id LIMIT 21 OFFSET '.(($page-1)*20),[$actor['id'],$now,$now,(int)$read,(int)$send]);
    $result=[];
    foreach ($rows as $row) {
        $row['can_read']=mail_accounts_can('mail.read',$row['id']);$row['can_send']=mail_accounts_can('mail.send',$row['id']);
        if ($row['can_read'] || $row['can_send']) $result[]=$row;
    }
    return $result;
}
