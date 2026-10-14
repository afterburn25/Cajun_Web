<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/messages.php';
require_once CWP_CODE_ROOT.'/app/mail/notices.php';
function mail_message_route(): array {
    $id=$_GET['account_id']??null;$revision=$_GET['revision']??null;$validity=$_GET['uidvalidity']??null;$uid=$_GET['uid']??null;
    if(!is_string($id)||!is_string($revision)||!preg_match('/\A[1-9][0-9]{0,9}\z/D',$revision)||(float)$revision>4294967295)throw new InvalidArgumentException('Open a message from the current inbox headers.');
    mail_message_identity($validity,$uid);
    $route=['account_id'=>$id,'revision'=>$revision,'uidvalidity'=>$validity,'uid'=>$uid];
    if(array_key_exists('notice',$_GET)){if(!is_string($_GET['notice']))throw new InvalidArgumentException('This email notice is unavailable.');mail_notices_handle($_GET['notice']);$route['notice']=$_GET['notice'];}
    return $route;
}
function mail_message_admin_action(string $action): array {
    if($action==='mail_message_setup') {
        mail_messages_migrate(posted('current_password',200));flash('Message request history is ready. No server was contacted.');redirect(admin_url('mail-accounts'));
    }
    if($action!=='mail_message_read'||($_GET['view']??null)!=='mail-message')throw new InvalidArgumentException('Open the current message form before requesting content.');
    $route=mail_message_route();
    foreach($route as $key=>$value)if(posted($key,32)!==$value)throw new InvalidArgumentException('This message form no longer matches the selected inbox message.');
    return mail_message_read($route['account_id'],(int)$route['revision'],$route['uidvalidity'],$route['uid'],null,null,$route['notice']??null);
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
            if($result['account_id']!==$accountId||$result['actor_id']!==(int)$current['id']||$result['account_revision']!==(int)$route['revision']||$result['uidvalidity']!==$route['uidvalidity']||$result['uid']!==$route['uid']||($result['notice_handle']??null)!==($route['notice']??null))throw new RuntimeException('Reload the current inbox before viewing this message.');
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
