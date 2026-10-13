<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/accounts.php';
require_once CWP_CODE_ROOT.'/app/mail/connections.php';

function mail_accounts_revision(): int {
    $revision=posted('revision',10);
    if (!ctype_digit($revision)) throw new InvalidArgumentException('Reload the current account form.');return (int)$revision;
}
function mail_accounts_admin_action(string $action): void {
    $password=posted('current_password',200);
    if ($action==='mail_accounts_setup') {
        mail_accounts_migrate($password);flash('Email account storage enabled.');redirect(admin_url('mail-accounts'));
    }
    if ($action==='mail_accounts_connections_setup') {
        $id=posted('account_id',32);mail_accounts_manager();mail_accounts_load($id);
        mail_connections_migrate($password);flash('Connection-check history is ready. No server was contacted.');redirect(admin_url('mail-account',['account_id'=>$id]));
    }
    mail_accounts_manager();$id=posted('account_id',32);$revision=mail_accounts_revision();
    if ($action==='mail_accounts_check') {
        $code=mail_connections_check($id,$revision,posted('target',10),$password);
        flash(mail_connections_message($code),$code==='ok'?'success':'error');redirect(admin_url('mail-account',['account_id'=>$id]));
    }
    if ($action==='mail_accounts_save') {
        $values=[];foreach (mail_accounts_fields() as $key=>$max) $values[$key]=posted($key,$max);
        $secrets=[];foreach (['imap_password','smtp_password'] as $key) {
            $value=$_POST[$key]??'';
            if (!is_string($value) || strlen($value)>1024) throw new InvalidArgumentException('Check the account password length.');
            $secrets[$key]=$value;
        }
        $id=mail_accounts_save($id,$revision,$values,posted('enabled',1)==='1',$secrets,$password);
    } elseif ($action==='mail_accounts_grant') {
        $user=posted('user_id',10);$days=posted('days',3);
        if (!ctype_digit($user) || !ctype_digit($days)) throw new InvalidArgumentException('Choose an administrator and assignment expiry.');
        mail_accounts_grant($id,(int)$user,$revision,posted('can_read',1)==='1',posted('can_send',1)==='1',posted('enabled',1)==='1',(int)$days,$password);
    } else {
        $operation=match($action) {'mail_accounts_enable'=>'enable','mail_accounts_disable'=>'disable','mail_accounts_clear'=>'clear',default=>throw new InvalidArgumentException('Unknown email account action.')};
        mail_accounts_state($id,$revision,$operation,$password);
    }
    flash('Email settings saved. No server connection or message was sent.');redirect(admin_url('mail-account',['account_id'=>$id]));
}
function mail_accounts_admin_render(string $view,string $error): void {
    try {
        $actor=$view==='mailboxes'?mail_accounts_actor(false):mail_accounts_manager(false);
        if ($view==='mailboxes' && !mail_accounts_access($actor)) throw new RuntimeException('Your account has no staff email permission.');
    } catch (RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        http_response_code(403);admin_head('Email access unavailable','security',require_admin());
        echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';admin_foot();exit;
    }
    admin_head($view==='mailboxes'?'My email accounts':($view==='mail-accounts'?'Email accounts':'Email account settings'),$view==='mailboxes'?'mailboxes':'mail-accounts',$actor);
    if ($error!=='') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    if (!mail_accounts_ready()) {
        echo '<section class="workspace-card"><h2>Prepare your email accounts</h2><p>Save multiple account settings and assign staff access. Existing website email delivery remains unchanged.</p>';
        if ($actor['role']==='owner') {
            echo '<form method="post">';admin_action('mail_accounts_setup');admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable email account storage</button></form>';
        } else echo '<p>An owner must enable account storage first.</p>';
        echo '</section>';admin_foot();exit;
    }
    try {require CWP_CODE_ROOT.'/admin/views/mail_accounts.php';}
    catch (InvalidArgumentException|RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p><a class="btn outline" href="'.esc(admin_url($view==='mailboxes'?'mailboxes':'mail-accounts')).'">Back to email accounts</a>';
    }
    admin_foot();exit;
}
