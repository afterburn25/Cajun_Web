<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/messages.php';
function mail_inbox_admin_action(string $action): array {
    if($action==='mail_inbox_setup') {
        mail_inbox_migrate(posted('current_password',200));flash('Inbox request history is ready. No server was contacted.');redirect(admin_url('mail-accounts'));
    }
    if($action!=='mail_inbox_read')throw new InvalidArgumentException('Unknown inbox action.');
    $id=posted('account_id',32);
    if(($_GET['view']??null)!=='mail-inbox' || ($_GET['account_id']??null)!==$id)throw new InvalidArgumentException('Open the current inbox form before requesting headers.');
    $folder=mail_inbox_folder($_GET['folder']??'INBOX');
    if(posted('folder',64)!==$folder)throw new InvalidArgumentException('This inbox form no longer matches the selected folder.');
    return mail_inbox_read($id,mail_accounts_revision(),null,null,$folder);
}
function mail_inbox_admin_render(string $error,#[\SensitiveParameter] ?array $result): never {
    header('Cache-Control: no-store, private');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
    try {
        $accountId=is_string($_GET['account_id']??null)?$_GET['account_id']:'';
        $folder=mail_inbox_folder($_GET['folder']??'INBOX');
        [$actor,$row]=mail_inbox_authority($accountId,null,false);$record=mail_accounts_projection($row);unset($row);
        if($result!==null) {
            [$current]=mail_inbox_authority($accountId,(int)$result['account_revision']);
            if($result['account_id']!==$accountId||$result['actor_id']!==(int)$current['id']||$result['folder']!==$folder)throw new RuntimeException('Reload your current folder before viewing headers.');
        }
    }catch(InvalidArgumentException|RuntimeException $e) {
        if($e instanceof PDOException)throw $e;
        $result=null;http_response_code(403);admin_head('Inbox unavailable','mailboxes',require_admin());
        echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p><a class="btn outline" href="'.esc(admin_url('mailboxes')).'">My email accounts</a>';admin_foot();exit;
    }
    admin_head('Recent inbox headers','mailboxes',$actor);
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    require CWP_CODE_ROOT.'/admin/views/mail_inbox.php';admin_foot();exit;
}
