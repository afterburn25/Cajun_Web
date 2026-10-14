<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/notices.php';
function mail_notices_admin_action(string $action): never {
    if($action==='mail_notices_setup') {
        mail_notices_migrate(posted('current_password',200));flash('Email notice storage is ready. Load recent headers in your inboxes to populate it.');redirect(admin_url('mail-accounts'));
    }
    if($action!=='mail_notices_dismiss'||($_GET['view']??null)!=='mail-notices')throw new InvalidArgumentException('Open the current email notices page before changing a notice.');
    $tuple=[];foreach(['account_id','account_revision','folder','uidvalidity','uid'] as $key)$tuple[$key]=posted($key,32);
    mail_notices_dismiss(posted('handle',32),$tuple);flash('This notice was dismissed for your administrator account. Server read flags are unchanged.');redirect(admin_url('mail-notices'));
}
function mail_notices_admin_render(string $error): never {
    header('Cache-Control: no-store, private');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
    try {
        $actor=mail_notices_actor(false);$ready=mail_notices_ready()&&mail_inbox_ready()&&mail_messages_ready();
        $page=max(1,min((int)($_GET['p']??1),100000));$accountPage=max(1,min((int)($_GET['accounts_page']??1),100000));
        $items=$ready?mail_notices_feed($page):[];$accounts=$ready?mail_notices_accounts($accountPage):[];$count=$ready?mail_notices_count():0;
    }catch(InvalidArgumentException|RuntimeException $e) {
        if($e instanceof PDOException)throw $e;http_response_code(403);admin_head('Email notices unavailable','mail-notices',require_admin());
        echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';admin_foot();exit;
    }
    admin_head('Recent email notices','mail-notices',$actor);
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    require CWP_CODE_ROOT.'/admin/views/mail_notices.php';admin_foot();exit;
}
