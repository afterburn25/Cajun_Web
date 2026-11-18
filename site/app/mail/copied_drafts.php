<?php
/** Authorized mailbox-read to protected copied-draft adapter. No submission or sending. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

if(!function_exists('mail_message_copy_source'))require_once CWP_CODE_ROOT.'/app/mail/messages.php';
if(!function_exists('mail_reply_drafts_existing'))require_once CWP_CODE_ROOT.'/app/mail/replies.php';

/**
 * Exact replay returns before network access. A fresh request performs one
 * journaled read whose raw bytes are consumed into CopiedDraftSource inside
 * the message service, then persists only bounded derived content.
 */
function mail_copied_draft_create(
    string $draftId,
    string $sourceAccount,
    int $sourceRevision,
    string $uidvalidity,
    string $uid,
    string $mode,
    string $sender,
    int $senderRevision,
    ?string $noticeHandle=null,
    ?callable $resolver=null,
    ?callable $transport=null,
):array {
    mail_drafts_id($draftId);mail_message_identity($uidvalidity,$uid);
    if(!in_array($mode,['reply','forward'],true))throw new InvalidArgumentException('Unsupported mail action.');
    if(mail_reply_drafts_existing($draftId,$sourceAccount,$sourceRevision,$uidvalidity,$uid,$mode,$sender,$senderRevision)!==null)return ['state'=>'existing','draft_id'=>$draftId,'result'=>null];
    mail_drafts_account($sender,$senderRevision);
    $copy=mail_message_copy_source($sourceAccount,$sourceRevision,$uidvalidity,$uid,$mode,$resolver,$transport,$noticeHandle);
    $result=$copy['result'];
    if($result['code']!=='ok')return ['state'=>'read_failed','draft_id'=>null,'result'=>$result];
    if(!$copy['source'] instanceof \CajunWebPro\Foundation\MailCompose\CopiedDraftSource)throw new LogicException('Successful copied-message read returned no derived source.');
    mail_reply_drafts_create($draftId,$sender,$senderRevision,$copy['source']);
    return ['state'=>'created','draft_id'=>$draftId,'result'=>$result];
}
