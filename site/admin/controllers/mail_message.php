<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/messages.php';
require_once CWP_CODE_ROOT.'/app/mail/notices.php';
require_once CWP_CODE_ROOT.'/app/mail/copied_drafts.php';
require_once CWP_CODE_ROOT.'/app/mail/flags.php';
function mail_message_route(): array {
    $id=$_GET['account_id']??null;$revision=$_GET['revision']??null;$validity=$_GET['uidvalidity']??null;$uid=$_GET['uid']??null;
    if(!is_string($id)||!is_string($revision)||!preg_match('/\A[1-9][0-9]{0,9}\z/D',$revision)||(float)$revision>4294967295)throw new InvalidArgumentException('Open a message from the current folder headers.');
    mail_message_identity($validity,$uid);
    try {$folder=mail_inbox_folder($_GET['folder']??'INBOX');}
    catch(InvalidArgumentException) {throw new InvalidArgumentException('This message link no longer matches the selected folder.');}
    $route=['account_id'=>$id,'revision'=>$revision,'uidvalidity'=>$validity,'uid'=>$uid,'folder'=>$folder];
    if(array_key_exists('notice',$_GET)){if($folder!=='INBOX'||!is_string($_GET['notice']))throw new InvalidArgumentException('This email notice is unavailable.');mail_notices_handle($_GET['notice']);$route['notice']=$_GET['notice'];}
    return $route;
}
function mail_message_admin_action(string $action): array {
    if($action==='mail_message_setup') {
        mail_messages_migrate(posted('current_password',200));flash('Message request history is ready. No server was contacted.');redirect(admin_url('mail-accounts'));
    }
    if($action==='mail_message_flags_setup') {
        mail_flags_migrate(posted('current_password',200));flash('Message flag history is ready. No server was contacted.');redirect(admin_url('mail-accounts'));
    }
    $copyModes=['mail_message_reply'=>'reply','mail_message_reply_all'=>'reply-all','mail_message_forward'=>'forward'];
    if(($_GET['view']??null)!=='mail-message'||!in_array($action,['mail_message_read','mail_message_flag','mail_message_attachment',...array_keys($copyModes)],true))throw new InvalidArgumentException('Open the current message form before requesting content.');
    $route=mail_message_route();
    foreach($route as $key=>$value)if(posted($key,64)!==$value)throw new InvalidArgumentException('This message form no longer matches the selected folder message.');
    if($action==='mail_message_attachment') {
        $index=posted('attachment',4);if(!is_string($index)||!preg_match('/\A(?:0|[1-9][0-9]?)\z/D',$index))throw new InvalidArgumentException('This attachment is unavailable.');
        try {$read=mail_message_attachment($route['account_id'],(int)$route['revision'],$route['uidvalidity'],$route['uid'],(int)$index,null,null,$route['notice']??null,$route['folder']);}
        catch(InvalidArgumentException) {flash('This attachment is unavailable.');redirect(admin_url('mail-message',$route));}
        if($read['code']!=='ok'||$read['attachment']===null){flash(mail_message_outcome($read['code']));redirect(admin_url('mail-message',$route));}
        $part=$read['attachment'];header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.$part['name'].'"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Length: '.strlen($part['bytes']));
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();echo $part['bytes'];exit;
    }
    if($action==='mail_message_flag') {
        $mark=posted('mark',6);
        if(!in_array($mark,['read','unread'],true))throw new InvalidArgumentException('Choose whether to mark the message read or unread.');
        $flag=mail_message_mark($route['account_id'],(int)$route['revision'],$route['folder'],$route['uidvalidity'],$route['uid'],$mark==='read');
        flash(mail_flag_outcome($flag['code']));redirect(admin_url('mail-message',$route));
    }
    if($action==='mail_message_read')return mail_message_read($route['account_id'],(int)$route['revision'],$route['uidvalidity'],$route['uid'],null,null,$route['notice']??null,$route['folder']);
    if($route['folder']!=='INBOX')throw new InvalidArgumentException('Copied-message drafts are available only from INBOX.');
    $draftId=posted('draft_id',32);mail_drafts_id($draftId);[$sender,$senderRevision]=mail_drafts_reference(posted('sender',43));
    $created=mail_copied_draft_create($draftId,$route['account_id'],(int)$route['revision'],$route['uidvalidity'],$route['uid'],$copyModes[$action],$sender,$senderRevision,$route['notice']??null);
    if($created['state']==='read_failed')return $created['result'];
    flash($created['state']==='existing'?'Your existing copied-message draft is open. No email was sent.':'A protected copied-message draft was created. No email was sent.');
    redirect(admin_url('mail-draft',['draft_id'=>$created['draft_id']]));
}
/** Caller supplies only BodyDecoder's sanitized output in a currently authorized response. */
function mail_message_document(#[\SensitiveParameter] string $sanitized): string {
    $token=$GLOBALS['cwp_nonce']??'';
    if(!is_string($token)||!preg_match('/\A[A-Za-z0-9+\/]{24}\z/D',$token))throw new RuntimeException('Message formatting could not be prepared.');
    $policy="default-src 'none'; script-src 'none'; style-src 'nonce-".$token."'; img-src 'none'; base-uri 'none'; form-action 'none'; connect-src 'none'; object-src 'none'; frame-src 'none'";
    $style='body{margin:16px;background:#102338;color:#eff6ff;font:16px/1.6 system-ui;overflow-wrap:anywhere}a{color:#efc36b}table{max-width:100%;border-collapse:collapse}td,th{padding:4px;border:1px solid #476079}pre{white-space:pre-wrap}blockquote{margin-left:12px;padding-left:12px;border-left:3px solid #efc36b}';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="referrer" content="no-referrer"><meta http-equiv="Content-Security-Policy" content="'.esc($policy).'"><style nonce="'.esc($token).'">'.$style.'</style></head><body>'.$sanitized.'</body></html>';
}
function mail_message_admin_render(string $error,#[\SensitiveParameter] ?array $result): never {
    header('Cache-Control: no-store, private');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
    try {
        $route=mail_message_route();$accountId=$route['account_id'];
        [$actor,$row]=mail_message_authority($accountId,(int)$route['revision'],false);
        if(isset($route['notice']))mail_notices_resolve($route['notice'],['account_id'=>$accountId,'account_revision'=>(int)$route['revision'],'folder'=>'INBOX','uidvalidity'=>$route['uidvalidity'],'uid'=>$route['uid']]);
        $record=mail_accounts_projection($row);unset($row);
        if($result!==null) {
            [$current]=mail_message_authority($accountId,(int)$route['revision']);
            if($result['account_id']!==$accountId||$result['actor_id']!==(int)$current['id']||$result['account_revision']!==(int)$route['revision']||$result['folder']!==$route['folder']||$result['uidvalidity']!==$route['uidvalidity']||$result['uid']!==$route['uid']||($result['notice_handle']??null)!==($route['notice']??null))throw new RuntimeException('Reload the current folder before viewing this message.');
        }
        $senderPage=max(1,min((int)($_GET['sender_page']??1),100000));$replySenders=[];$moreReplySenders=false;
        $mailCopyReady=mail_replies_ready()&&mail_drafts_ready();$mailCopyAllowed=false;
        if($route['folder']==='INBOX'&&$mailCopyReady&&mail_drafts_admin_access($actor)){
            try{$draftActor=mail_drafts_actor();$mailCopyAllowed=(int)$draftActor['id']===(int)$actor['id']&&hash_equals($draftActor['auth_version'],$actor['auth_version']);}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
        }
        if($mailCopyAllowed){
            $assigned=mail_drafts_senders($senderPage);$moreReplySenders=count($assigned)>20;
            foreach(array_slice($assigned,0,20) as $senderAccount)$replySenders[$senderAccount['id']]=$senderAccount;
        }
    }catch(InvalidArgumentException|RuntimeException $e) {
        if($e instanceof PDOException)throw $e;
        $result=null;http_response_code(403);admin_head('Message unavailable','mailboxes',require_admin());
        echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p><a class="btn outline" href="'.esc(admin_url('mailboxes')).'">My email accounts</a>';admin_foot();exit;
    }
    admin_head('Email message','mailboxes',$actor);
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    require CWP_CODE_ROOT.'/admin/views/mail_message.php';admin_foot();exit;
}
