<?php
/** Current-authorized explicit saved-message review and private submission history. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/admin/controllers/mail_drafts.php';
require_once CWP_CODE_ROOT.'/app/mail/submission_views.php';
require_once CWP_CODE_ROOT.'/app/mail/submission_dispatch.php';
require_once CWP_CODE_ROOT.'/app/mail/copies.php';

function mail_submissions_form(array $input,bool $confirmation=true):array {
    $id=mail_drafts_form_text($input,'submission_id',32);mail_drafts_id($id);
    $draft=mail_drafts_form_text($input,'draft_id',32);mail_drafts_id($draft);
    $revision=mail_drafts_form_revision($input,'draft_revision');$accountRevision=mail_drafts_form_revision($input,'account_revision');
    if($revision<1||$accountRevision<1)throw new InvalidArgumentException('Review a saved draft and sending account first.');
    if($confirmation&&($input['confirm_send']??null)!=='1')throw new InvalidArgumentException('Confirm that you reviewed the sender, recipients and saved message.');
    return compact('id','draft','revision','accountRevision');
}
function mail_submissions_admin_action(string $action):void {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new InvalidArgumentException('Sending requires an explicit form submission.');
    check_csrf();
    if($action==='mail_submissions_setup') {
        mail_submissions_migrate(mail_drafts_form_text($_POST,'current_password',200));flash('Email submission storage is ready.');redirect(admin_url('mail-submissions'));
    }
    if($action==='mail_submissions_copy_sent') {
        $id=mail_drafts_form_text($_POST,'submission_id',32);
        $result=mail_submission_copy($id);
        flash($result['code']==='appended'?'A copy was saved in the server Sent folder.':mail_copy_outcome($result['code']),$result['code']==='appended'?'success':'error');
        redirect(admin_url('mail-submission',['submission_id'=>$id]));
    }
    if($action!=='mail_submissions_send')throw new InvalidArgumentException('Unknown email submission action.');
    $form=mail_submissions_form($_POST);
    $id=mail_submissions_send($form['id'],$form['draft'],$form['revision'],$form['accountRevision']);
    // The destination rechecks current authorization before showing any result or private content.
    redirect(admin_url('mail-submission',['submission_id'=>$id]));
}
function mail_submissions_admin_data(string $view):array {
    return staff_locked(function()use($view):array {
        $actor=mail_submissions_actor();
        if($view==='mail-submission') {
            $id=mail_drafts_form_text($_GET,'submission_id',32);$row=mail_submissions_get($id);
            return ['actor'=>$actor,'row'=>$row,'status'=>mail_submissions_status($row['result'],$row['dispatch']),'copy'=>mail_copy_status($id),'sentFolder'=>mail_sent_folder($row['account_id'])];
        }
        if($view==='mail-send') {
            $draft=mail_drafts_form_text($_GET,'draft_id',32);mail_drafts_id($draft);$form=null;
            if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??null)==='mail_submissions_send') {
                $form=mail_submissions_form($_POST,false);
                if($form['draft']!==$draft)throw new InvalidArgumentException('The reviewed draft does not match this page.');
            }
            $review=mail_submissions_review($draft,$form['revision']??null,$form['accountRevision']??null);
            return ['actor'=>$actor,'submission_id'=>$form['id']??bin2hex(random_bytes(16))]+$review;
        }
        if($view!=='mail-submissions')throw new InvalidArgumentException('Unknown email submission view.');
        $account=mail_drafts_form_text($_GET,'account_id',32);$accountPage=max(1,min((int)($_GET['account_page']??1),100000));$page=max(1,min((int)($_GET['p']??1),100000));
        $assigned=mail_accounts_assigned($accountPage);$moreAccounts=count($assigned)>20;$accounts=[];
        foreach(array_slice($assigned,0,20) as $row)if($row['can_send'])$accounts[$row['id']]=mail_drafts_account($row['id']);
        if($account!=='')$accounts[$account]=mail_drafts_account($account);elseif($accounts)$account=array_key_first($accounts);
        return compact('actor','accounts','account','accountPage','moreAccounts','page')+['rows'=>$account!==''?mail_submissions_list($account,$page):[]];
    });
}
function mail_submissions_admin_render(string $view,string $error):void {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try {$actor=mail_accounts_actor(false);if(!mail_drafts_admin_access($actor))throw new RuntimeException('Current email Send permission is required.');}
    catch(InvalidArgumentException|RuntimeException $e) {
        if($e instanceof PDOException)throw $e;http_response_code(403);admin_head('Email access unavailable','mailboxes',require_admin());echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';admin_foot();exit;
    }
    admin_head($view==='mail-send'?'Review and send email':'My email submissions','mailboxes',$actor);
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    if(!mail_accounts_ready()||!mail_drafts_ready())echo '<p class="notice">An owner must prepare email accounts and local drafts first.</p>';
    elseif(!mail_submissions_ready()) {
        echo '<section class="workspace-card"><h2>Prepare email submissions</h2><p>Keep a private record of each explicitly reviewed sending attempt. This setup does not send an email.</p>';
        if($actor['role']==='owner') {echo '<form method="post">';admin_action('mail_submissions_setup');admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Prepare submissions</button></form>';}
        else echo '<p>An owner must prepare submissions before you can send email.</p>';
        echo '</section>';
    }else {
        try {$data=mail_submissions_admin_data($view);require CWP_CODE_ROOT.'/admin/views/mail_submissions.php';}
        catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p><p><a class="text-link" href="'.esc(admin_url('mail-drafts')).'">Open my saved drafts</a></p>';}
    }
    admin_foot();exit;
}
