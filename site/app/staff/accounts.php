<?php
/** Administrator lifecycle adapter. Existing users remain the authentication identity. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Access\AccessContext;
use CajunWebPro\Foundation\Access\PermissionPolicy;
use CajunWebPro\Foundation\Invitations\StaffInvitationStore;

foreach (['access/AuthorizationVersion','access/AccessContext','access/MailboxGrant','access/PermissionPolicy','invitations/IssuedInvitation','invitations/StaffInvitationStore'] as $class) {
    require_once CWP_CODE_ROOT.'/includes/foundation/'.$class.'.php';
}
function staff_ready(): bool { return setting('staff_schema_version') === '1'; }
require_once CWP_CODE_ROOT.'/database/schema/staff_schema.php';
function staff_permissions(): array { return ['mail.read'=>'Read recent headers from explicitly assigned email accounts', 'mail.send'=>'Review and send saved drafts from explicitly assigned email accounts', 'mail.templates.manage'=>'Personal and shared email templates (no sending or mailbox access)', 'payments.manage'=>'Invoice payment requests and verified external receipts (also needs client access)', 'catalog.manage'=>'Service catalog, internal costs and catalog invoices (also needs client access)', 'website.manage'=>'Website, portfolio and inquiries','clients.manage'=>'Client projects, files and manual invoices','support.tickets'=>'Client support conversations and private staff notes','support.login'=>'Open permitted client portals for support','support.write'=>'Send Studio messages and upload files during support','support.download'=>'Download private files during support']; }
function staff_profile(array $user): ?array {
    if (!staff_ready()) return $user + ['role'=>'owner','enabled'=>1,'permissions'=>'[]']; // Existing access until explicit upgrade.
    $p=one('SELECT role,enabled,permissions FROM '.table('staff_profiles').' WHERE user_id=?',[$user['id']]);
    if (!$p || (int)$p['enabled']!==1 || !in_array($p['role'],['owner','administrator'],true)) return null;
    $permissions=json_decode($p['permissions'],true);
    if (!is_array($permissions) || !array_is_list($permissions) || array_filter($permissions,fn($v)=>!is_string($v)||!array_key_exists($v,staff_permissions()))) return null;
    return array_merge($user,$p);
}
function staff_can(string $permission,?array $user=null): bool {
    $user=$user ?? admin_user();
    if (!$user) return false;
    $profile=staff_profile($user);
    if (!$profile) return false;
    $context=new AccessContext((string)$profile['id'],'staff',$profile['role'],true,(string)$profile['auth_version'],(string)($_SESSION['auth_version']??''),json_decode($profile['permissions'],true));
    return (new PermissionPolicy())->allows($context,$permission);
}
function staff_require(string $permission): array {
    $user=require_admin();
    if (!staff_can($permission,$user)) throw new RuntimeException('Your account does not have access to this action.');
    return $user;
}
/** All credential/owner transitions share one DB-scoped mutex, including activation and reset. */
function staff_locked(callable $work): mixed {
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if ((int)query('SELECT GET_LOCK(?,5)',[$key])->fetchColumn()!==1) throw new RuntimeException('Another account change is in progress. Please retry.');
    try { return $work(); } finally { query('SELECT RELEASE_LOCK(?)',[$key]); }
}
function staff_transaction(callable $work): mixed {
    if (db()->inTransaction()) throw new LogicException('Account transaction must not be nested.');
    db()->beginTransaction();
    try { $result=$work();db()->commit();return $result; }
    catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack();throw $e; }
}
function staff_store(): StaffInvitationStore { return new StaffInvitationStore(db(),config()['db']['prefix'].'staff_invitations'); }
function staff_seed_owner(int $id): void {
    query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,\'owner\',1,\'[]\')',[$id]);
    save_setting('staff_schema_version','1');
}
require_once CWP_CODE_ROOT.'/database/schema/staff_validate_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/staff_migrate.php';
function staff_owner(#[\SensitiveParameter] string $password): array {
    if(!staff_ready())throw new RuntimeException('Enable administrator access first.');
    $actor=staff_require('staff.manage');
    if(!password_verify($password,$actor['password_hash']))throw new InvalidArgumentException('Your current password is not correct.');
    return $actor;
}
function staff_revoke_credentials(int $id): void {
    if(staff_ready())staff_store()->revoke((string)$id);
    query('DELETE FROM '.table('resets').' WHERE user_id=?',[$id]);
}
function staff_save(int $id,string $name,string $email,string $role,bool $enabled,array $permissions,#[\SensitiveParameter] string $password): int {
    $email=strtolower(trim($email));$name=trim($name);
    if($name===''||strlen($name)>120||strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['owner','administrator'],true)||!array_is_list($permissions))throw new InvalidArgumentException('Enter a name, valid email and role.');
    foreach($permissions as $p)if(!is_string($p)||!array_key_exists($p,staff_permissions()))throw new InvalidArgumentException('Unknown administrator permission.');
    return staff_locked(fn()=>staff_transaction(function() use($id,$name,$email,$role,$enabled,$permissions,$password): int {
        staff_owner($password);$old=$id?one('SELECT u.*,p.role,p.enabled FROM '.table('users').' u JOIN '.table('staff_profiles').' p ON p.user_id=u.id WHERE u.id=? FOR UPDATE',[$id]):null;
        if($id&&!$old)throw new InvalidArgumentException('Administrator not found.');
        if($old&&$old['role']==='owner'&&(int)$old['enabled']===1&&$old['password_hash']!==''&&($role!=='owner'||!$enabled)){
            $owners=(int)query('SELECT COUNT(*) FROM '.table('staff_profiles').' p JOIN '.table('users').' u ON u.id=p.user_id WHERE p.role=\'owner\' AND p.enabled=1 AND u.password_hash<>\'\'')->fetchColumn();
            if($owners<=1)throw new InvalidArgumentException('Keep at least one active owner with a completed password setup.');
        }
        $version=bin2hex(random_bytes(16));
        if($old){staff_revoke_credentials($id);query('UPDATE '.table('users').' SET name=?,email=?,auth_version=? WHERE id=?',[$name,$email,$version,$id]);query('UPDATE '.table('staff_profiles').' SET role=?,enabled=?,permissions=? WHERE user_id=?',[$role,(int)$enabled,json_encode(array_values(array_unique($permissions)),JSON_THROW_ON_ERROR),$id]);}
        else{query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,\'\',?,?)',[$name,$email,$version,gmdate('Y-m-d H:i:s')]);$id=(int)db()->lastInsertId();query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,?,?)',[$id,$role,(int)$enabled,json_encode(array_values(array_unique($permissions)),JSON_THROW_ON_ERROR)]);}
        log_event($old?'staff.update':'staff.create','Administrator '.$id);return $id;
    }));
}
function staff_invite(int $id,#[\SensitiveParameter] string $password): string {
    return staff_locked(fn()=>staff_transaction(function() use($id,$password): string {
        $actor=staff_owner($password);
        $u=one('SELECT * FROM '.table('users').' WHERE id=? FOR UPDATE',[$id]);
        if(!$u||!staff_profile($u)||(int)$actor['id']===$id)throw new InvalidArgumentException('Choose another enabled administrator.');
        // Recovery requires fresh activation; existing sessions/resets are invalid immediately.
        $version=bin2hex(random_bytes(16));staff_revoke_credentials($id);query('UPDATE '.table('users').' SET password_hash=\'\',auth_version=? WHERE id=?',[$version,$id]);
        $invitation=staff_store()->issue((string)$id,$version);log_event('staff.invite','Administrator '.$id);
        return admin_url('staff-setup').'#token='.$invitation->token;
    }));
}
function staff_activate(#[\SensitiveParameter] string $token,#[\SensitiveParameter] string $password): void {
    validate_password($password);$hash=password_hash($password,PASSWORD_DEFAULT);
    $ok=staff_locked(function() use($token,$hash): bool {
        if(!staff_ready())return false;
        return staff_store()->redeem($token,function(PDO $db,string $id,string $version) use($hash): bool {
            $u=one('SELECT * FROM '.table('users').' WHERE id=? FOR UPDATE',[$id]);
            if(!$u||!staff_profile($u)||!hash_equals($version,$u['auth_version']))return false;
            query('UPDATE '.table('users').' SET password_hash=?,auth_version=? WHERE id=?',[$hash,bin2hex(random_bytes(16)),$id]);query('DELETE FROM '.table('resets').' WHERE user_id=?',[$id]);log_event('staff.activate','Administrator '.$id);return true;
        });
    });
    if(!$ok)throw new InvalidArgumentException('This setup link expired or is no longer available. Ask an owner for a new one.');
}
function staff_account_save(int $id,string $name,string $email,#[\SensitiveParameter] string $current,#[\SensitiveParameter] string $password): string {
    if(trim($name)===''||strlen($name)>120||strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('A name and valid email are required.');
    if($password!=='')validate_password($password);
    return staff_locked(fn()=>staff_transaction(function() use($id,$name,$email,$current,$password): string {
        $u=require_admin();if((int)$u['id']!==$id||!password_verify($current,$u['password_hash']))throw new InvalidArgumentException('Your current password is not correct.');
        $version=bin2hex(random_bytes(16));staff_revoke_credentials($id);query('UPDATE '.table('users').' SET name=?,email=?,password_hash=?,auth_version=? WHERE id=?',[trim($name),strtolower($email),$password===''?$u['password_hash']:password_hash($password,PASSWORD_DEFAULT),$version,$id]);log_event('account.update');return $version;
    }));
}
function staff_reset(#[\SensitiveParameter] string $token,#[\SensitiveParameter] string $password): void {
    validate_password($password);if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new InvalidArgumentException('This reset link is not valid.');
    $hash=password_hash($password,PASSWORD_DEFAULT);
    staff_locked(fn()=>staff_transaction(function() use($token,$hash): void {
        $r=one('SELECT * FROM '.table('resets').' WHERE token_hash=? FOR UPDATE',[hash('sha256',$token)]);
        if(!$r||(int)$r['expires']<=time())throw new InvalidArgumentException('This link expired or has already been used. Request a new one.');
        $u=one('SELECT * FROM '.table('users').' WHERE id=? FOR UPDATE',[$r['user_id']]);
        if(!$u||$u['password_hash']===''||!staff_profile($u))throw new InvalidArgumentException('This reset link is no longer available.');
        staff_revoke_credentials((int)$u['id']);query('UPDATE '.table('users').' SET password_hash=?,auth_version=? WHERE id=?',[$hash,bin2hex(random_bytes(16)),$u['id']]);log_event('staff.password_reset','Administrator '.$u['id']);
    }));
}
function staff_reset_token(string $email): ?array {
    return staff_locked(fn()=>staff_transaction(function() use($email): ?array {
        $u=one('SELECT * FROM '.table('users').' WHERE email=? FOR UPDATE',[$email]);
        if(!$u||$u['password_hash']===''||!staff_profile($u))return null;
        $token=bin2hex(random_bytes(32));query('INSERT INTO '.table('resets').' (token_hash,user_id,expires) VALUES (?,?,?)',[hash('sha256',$token),$u['id'],time()+3600]);return ['email'=>$u['email'],'token'=>$token];
    }));
}
function staff_view_permission(string $view): ?string {
    if($view==='recurring-billing')return 'payments.manage'; // Controller also requires current clients.manage.
    if($view==='activity')return null; // Current project/ticket actors independently authorize the activity controller.
    if(in_array($view,['tickets','ticket','ticket-new'],true))return 'support.tickets';
    if(in_array($view,['service-plans','service-plan','service-agreements','service-agreement'],true))return 'staff.manage';
    if(in_array($view,['mail-submissions','mail-submission','mail-send','mail-drafts','mail-draft'],true))return null; // Draft controller enforces current Send plus exact mailbox/author.
    if($view==='mail-notices')return null; // The mail-specific actor and exact grants protect this projection.
    // The inbox controller checks mail.read with its exact current mailbox grant.
    if(in_array($view,['mail-inbox','mail-message'],true))return null; // Mandatory current account authority in both controllers.
    if($view==='consultation-settings')return 'staff.manage';
    if(in_array($view,['consultations','consultation'],true))return 'website.manage';
    if(in_array($view,['login','forgot','reset','staff-setup','security','mailboxes'],true))return null;
    if(in_array($view,['provider-settings','staff','mail-accounts','mail-account','payment-settings','payment-trials','payment-notifications'],true))return 'staff.manage';
    if(in_array($view,['payments','invoice-payments'],true))return 'payments.manage';
    if(in_array($view,['mail-templates','mail-template'],true))return 'mail.templates.manage';
    if($view==='support')return 'support.login';
    if(in_array($view,['catalog','catalog-invoice'],true))return 'catalog.manage';
    if(in_array($view,['email','health','studio-upgrade'],true))return 'staff.manage';
    if(in_array($view,['studio-clients','studio-job','deliveries','activity'],true))return 'clients.manage';
    return 'website.manage';
}
function staff_action_permission(string $action): ?string {
    if($action==='recurring_setup')return 'staff.manage';
    if(str_starts_with($action,'recurring_'))return 'payments.manage';
    if($action==='design_financial_setup')return 'staff.manage';
    if(str_starts_with($action,'design_financial_'))return 'payments.manage';
    if($action==='tickets_setup')return 'staff.manage';
    if(str_starts_with($action,'tickets_'))return 'support.tickets';
    if(str_starts_with($action,'service_plans_'))return 'staff.manage';
    if($action==='mail_submissions_setup')return 'staff.manage';
    if(str_starts_with($action,'mail_submissions_'))return null; // Current author/Send/mailbox/review checks in submission controller.
    if($action==='mail_drafts_setup')return 'staff.manage';
    if(str_starts_with($action,'mail_drafts_'))return null; // Strict actor/account/author checks in every draft action.
    if($action==='mail_notices_setup')return 'staff.manage';
    if($action==='mail_notices_dismiss')return null; // Current mail actor plus exact tuple/grant rechecked by the action.
    if($action==='mail_message_setup')return 'staff.manage';
    if($action==='mail_message_read')return null; // mail_message_read enforces the exact current account scope.
    if($action==='mail_inbox_setup')return 'staff.manage';
    if($action==='mail_inbox_read')return null; // mail_inbox_read enforces the exact account scope.
    if(str_starts_with($action,'mail_inbox_'))return 'mail.read';
    if(in_array($action,['appointments_setup','appointments_configure'],true))return 'staff.manage';
    if(str_starts_with($action,'appointments_'))return 'website.manage';
    if(in_array($action,['logout','account_save'],true))return null;
    if(str_starts_with($action,'provider_settings_'))return 'staff.manage';
    if(str_starts_with($action,'mail_accounts_'))return 'staff.manage';
    if($action==='reviews_setup')return 'staff.manage';
    if($action==='mail_templates_setup')return 'staff.manage';
    if(str_starts_with($action,'mail_templates_'))return 'mail.templates.manage';
    if($action==='support_start')return 'support.login';
    if(in_array($action,['payments_migrate','payments_ledger_setup','payments_ledger_reverse','payments_save','payments_clear','payments_verify','payments_trial_setup','payments_trial_submit','payments_trial_verify','payments_notification_setup','payments_notification_configure','payments_notification_verify'],true))return 'staff.manage';
    if(str_starts_with($action,'payments_'))return 'payments.manage';
    if(in_array($action,['delivery_setup','delivery_publish'],true))return 'staff.manage';
    if(in_array($action,['activity_setup','project_progress_setup','project_planning_setup'],true))return 'staff.manage';
    if($action==='activity_mark')return 'clients.manage';
    if($action==='delivery_upload')return 'clients.manage';
    if($action==='catalog_migrate')return 'staff.manage';
    if(str_starts_with($action,'catalog_'))return 'catalog.manage';
    if(in_array($action,['support_migrate','support_policy'],true))return 'staff.manage';
    if(str_starts_with($action,'staff_')||in_array($action,['email_save','email_test','installer_cleanup','storage_repair','clear_limits','media_cleanup','studio_migrate'],true))return 'staff.manage';
    if(in_array($action,['project_planning_save','studio_client','studio_invite','studio_job','studio_message','studio_publish','studio_upload','studio_invoice','studio_paid'],true))return 'clients.manage';
    return 'website.manage';
}
function staff_home(array $user): string {
    if(staff_can('support.tickets',$user)&&!staff_can('website.manage',$user)&&!staff_can('clients.manage',$user))return 'tickets';
    return staff_can('website.manage',$user)?'dashboard':(staff_can('clients.manage',$user)?'studio-clients':(staff_can('catalog.manage',$user)?'catalog':(staff_can('mail.templates.manage',$user)?'mail-templates':(staff_can('support.login',$user)?'support':((function_exists('mail_accounts_access')&&mail_accounts_access($user))?'mailboxes':'security')))));
}
