<?php
/** Explicit exact-message reads. Contents exist only in the authorized response. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,EndpointResolver,ProbeFailure};
use CajunWebPro\Foundation\MailReceiving\{MessageReader,BodyDecoder,AttachmentIndex,ReceivingFailure};
require_once CWP_CODE_ROOT.'/app/mail/inbox.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_messages_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_messages_migrate.php';

function mail_messages_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_messages_schema_version'])['value']??'')==='2';}
function mail_message_outcomes(): array {
    return array_replace(mail_inbox_messages(),[
        'ok'=>'Message loaded. The server’s read flag was left unchanged.',
        'protocol'=>'The server or message returned an unsupported or incomplete response. No message is displayed.',
        'limit'=>'This message exceeds the supported size or formatting limits. Open it in your regular email app.',
        'timeout'=>'The message request reached its time limit. Try again later.',
        'access_changed'=>'The account settings or your access changed. Reload the folder before opening a message.',
        'mailbox_changed'=>'This exact message is no longer available in the current folder. Load the recent headers again.',
    ]);
}
function mail_message_outcome(string $code): string {return mail_message_outcomes()[$code]??'No final result was recorded. Try again after the request limit.';}
function mail_message_identity(mixed $validity,mixed $uid): void {
    if(!mail_inbox_uint($validity)||!mail_inbox_uint($uid))throw new InvalidArgumentException('Open a message from the current folder headers.');
}
function mail_message_authority(string $id,?int $revision=null,bool $ready=true): array {
    [$actor,$row]=mail_inbox_authority($id,$revision,false);
    if($ready&&!mail_messages_ready())throw new RuntimeException('An owner must prepare message reading in Email accounts first.');
    return [$actor,$row];
}
function mail_message_transport(array $row,#[\SensitiveParameter] array $secrets,string $address,string $validity,string $uid,callable $authorize,string $folder='INBOX'): array {
    require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/bootstrap.php';
    return MessageReader::read($row['imap_host'],(int)$row['imap_port'],$row['imap_encryption'],$address,$row['imap_username'],$secrets['imap_password'],$validity,$uid,$authorize,$folder);
}
/** Even injected transports supply raw MIME, never trusted HTML or decoded metadata. */
function mail_message_content(#[\SensitiveParameter] array $response,string $validity,string $uid,string $folder='INBOX'): array {
    $folder=mail_inbox_folder($folder);
    if(($response['folder']??null)!==$folder||($response['uidvalidity']??null)!==$validity||($response['uid']??null)!==$uid
        ||!is_bool($response['seen']??null)||!mail_inbox_uint($response['bytes']??null,true)||!is_string($response['raw']??null))throw new ReceivingFailure('protocol');
    if(strlen($response['raw'])>262144)throw new ReceivingFailure('limit');
    if((string)strlen($response['raw'])!==$response['bytes'])throw new ReceivingFailure('protocol');
    require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/bootstrap.php';
    require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/AttachmentIndex.php';
    return ['folder'=>$folder,'uidvalidity'=>$validity,'uid'=>$uid,'seen'=>$response['seen'],'bytes'=>$response['bytes']]+BodyDecoder::decode($response['raw'])+['attachments'=>AttachmentIndex::list($response['raw'])];
}
function mail_message_record(array $started,string $code,int $elapsed,#[\SensitiveParameter] ?array $content=null): void {
    if(!array_key_exists($code,mail_message_outcomes())||($code==='ok')!==($content!==null))throw new LogicException('Invalid local message result.');
    staff_transaction(function()use($started,$code,$elapsed,$content):void {
        query('INSERT INTO '.table('mail_message_results').' (id,outcome,result_code,finished_at,elapsed_ms,message_bytes) VALUES (?,?,?,?,?,?)',[$started['id'],$code==='ok'?'success':'failed',$code,gmdate('Y-m-d H:i:s'),max(0,min($elapsed,2147483647)),$content['bytes']??null]);
        if($content!==null&&$started['folder']==='INBOX'){require_once CWP_CODE_ROOT.'/app/mail/notices.php';mail_notices_opened($started,$content);}
        mail_accounts_audit((int)$started['actor_id'],'message_finished','Read '.$started['id'].' '.$code);
    });
}
/**
 * Internal finite request engine. Raw MIME never returns. It is consumed
 * immediately into either one bounded attachment or one non-serializable
 * CopiedDraftSource, then cleared before the result leaves this function.
 * Test resolver/transport seams are never exposed through owner/HTTP state.
 */
function mail_message_request_internal(string $id,int $revision,string $validity,string $uid,?string $copyMode,?string $copySelf,?int $attachmentIndex,?callable $resolver,?callable $transport,?string $noticeHandle,string $folder='INBOX'):array {
    if($copyMode!==null&&!in_array($copyMode,['reply','reply-all','forward'],true))throw new LogicException('Invalid copied-message request mode.');
    if(($copyMode===null&&$copySelf!==null)||($copyMode==='reply-all')!==($copySelf!==null))throw new LogicException('Invalid copied-message request context.');
    if($attachmentIndex!==null&&($copyMode!==null||$attachmentIndex<0||$attachmentIndex>31))throw new LogicException('Invalid attachment request context.');
    mail_message_identity($validity,$uid);
    $folder=mail_inbox_folder($folder);
    if($copyMode!==null&&$folder!=='INBOX')throw new InvalidArgumentException('Copied-message drafts are available only from INBOX.');
    if($noticeHandle!==null&&$folder!=='INBOX')throw new InvalidArgumentException('Email notices can open only the INBOX message they observed.');
    if($noticeHandle!==null){require_once CWP_CODE_ROOT.'/app/mail/notices.php';mail_notices_handle($noticeHandle);}
    if(db()->inTransaction())throw new LogicException('Message reads cannot run inside a transaction.');
    [$started,$row]=staff_locked(fn()=>staff_transaction(function()use($id,$revision,$validity,$uid,$noticeHandle,$folder):array {
        [$actor,$row]=mail_message_authority($id,$revision);
        if($noticeHandle!==null)mail_notices_resolve($noticeHandle,['account_id'=>$id,'account_revision'=>$revision,'folder'=>$folder,'uidvalidity'=>$validity,'uid'=>$uid]);
        try {EndpointPolicy::host($row['imap_host']);EndpointPolicy::port('imap',(int)$row['imap_port'],$row['imap_encryption']);}
        catch(InvalidArgumentException) {throw new InvalidArgumentException('Message reading requires a public incoming server on 993/TLS or 143/STARTTLS. Ask an owner to check the saved settings.');}
        $now=time();
        if(one('SELECT id FROM '.table('mail_message_reads').' WHERE account_id=? AND started_epoch>? LIMIT 1',[$id,$now-2]))throw new RuntimeException('Wait 2 seconds between message requests for this account.');
        foreach(['account_id'=>$id,'actor_id'=>$actor['id']] as $column=>$value) {
            if((int)query('SELECT COUNT(*) FROM '.table('mail_message_reads').' WHERE '.$column.'=? AND started_epoch>?',[$value,$now-3600])->fetchColumn()>=240)throw new RuntimeException('The hourly message request limit for this account or administrator has been reached. Try again later.');
        }
        $started=['id'=>bin2hex(random_bytes(16)),'account_id'=>$id,'account_revision'=>$revision,'actor_id'=>(int)$actor['id'],'folder'=>$folder,'uidvalidity'=>$validity,'uid'=>$uid,'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now];
        query('INSERT INTO '.table('mail_message_reads').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?)',array_values($started));
        mail_accounts_audit((int)$actor['id'],'message_started','Read '.$started['id'].' account '.$id.' revision '.$revision);
        return [$started,$row];
    }));
    $begin=hrtime(true);$code='ok';$addresses=[];
    try {$addresses=EndpointPolicy::addresses(($resolver??[EndpointResolver::class,'resolve'])($row['imap_host']));}
    catch(InvalidArgumentException) {$code='destination';}
    catch(ProbeFailure $e) {$code=$e->getMessage();}
    catch(Throwable) {$code='connection';}
    return staff_locked(function()use($started,$code,$addresses,$begin,$transport,$noticeHandle,$copyMode,$copySelf,$attachmentIndex,$folder):array {
        $authorize=static function()use($started,$noticeHandle):array {
            try {[$actor,$row]=mail_message_authority($started['account_id'],$started['account_revision']);
                if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');
                if($noticeHandle!==null)mail_notices_resolve($noticeHandle,$started);
                return $row;
            }catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;throw new ReceivingFailure('access_changed');}
        };
        $content=null;$source=null;$attachment=null;$attachmentFailure=null;$copyUnsupported=false;$attachmentUnavailable=false;
        try {$current=$authorize();}catch(ReceivingFailure) {$code='access_changed';}
        if($code==='ok') {
            try {$secrets=mail_accounts_unseal($current['id'],$current['credentials']);mail_accounts_passwords($secrets);}
            catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;$code='credential';}
            if($code==='ok') {
                try {
                    $response=($transport??'mail_message_transport')($current,$secrets,$addresses[0],$started['uidvalidity'],$started['uid'],$authorize,$folder);
                    $authorize();
                    $content=mail_message_content($response,$started['uidvalidity'],$started['uid'],$folder);
                    if($copyMode!==null) {
                        require_once CWP_CODE_ROOT.'/includes/foundation/mail-compose/CopiedDraftSource.php';
                        try{$source=\CajunWebPro\Foundation\MailCompose\CopiedDraftSource::fromRead((int)$started['actor_id'],$started['account_id'],(int)$started['account_revision'],$started['uidvalidity'],$started['uid'],$started['id'],$copyMode,$response['raw'],$copySelf);}
                        catch(InvalidArgumentException){$copyUnsupported=true;$source=null;}
                    }
                    if($attachmentIndex!==null) {
                        try{$attachment=AttachmentIndex::extract($response['raw'],$attachmentIndex);}
                        catch(InvalidArgumentException){$attachmentUnavailable=true;$attachment=null;}
                        catch(ReceivingFailure $e){$attachmentFailure=$e;$attachment=null;}
                    }
                    $authorize();
                }catch(ReceivingFailure $e) {$code=$e->getMessage();$content=null;$source=null;$attachment=null;$attachmentFailure=null;$copyUnsupported=false;$attachmentUnavailable=false;}
                catch(Throwable $e) {if($e instanceof PDOException)throw $e;$code='connection';$content=null;$source=null;$attachment=null;$attachmentFailure=null;$copyUnsupported=false;$attachmentUnavailable=false;}
                finally {unset($secrets,$response);}
            }
        }
        mail_message_record($started,$code,(int)((hrtime(true)-$begin)/1e6),$content);
        if($copyUnsupported)throw new InvalidArgumentException('This message cannot be copied into a supported draft. The successful read was recorded without saving message content.');
        if($attachmentUnavailable)throw new InvalidArgumentException('This attachment is unavailable. The successful read was recorded without returning message content.');
        if($attachmentFailure instanceof ReceivingFailure)throw $attachmentFailure;
        $result=['code'=>$code,'request_id'=>$started['id'],'account_id'=>$started['account_id'],'account_revision'=>$started['account_revision'],'actor_id'=>$started['actor_id'],'folder'=>$folder,'uidvalidity'=>$started['uidvalidity'],'uid'=>$started['uid'],'notice_handle'=>$noticeHandle,'loaded_at'=>gmdate('Y-m-d H:i:s'),'content'=>$content];
        return ['result'=>$result,'source'=>$source,'attachment'=>$attachment];
    });
}
/** Existing exact-message display behavior remains the INBOX-compatible default. */
function mail_message_read(string $id,int $revision,string $validity,string $uid,?callable $resolver=null,?callable $transport=null,?string $noticeHandle=null,string $folder='INBOX'):array {
    return mail_message_request_internal($id,$revision,$validity,$uid,null,null,null,$resolver,$transport,$noticeHandle,$folder)['result'];
}
/** Dedicated raw-to-derived copy read; no raw bytes or generic retain flag escape. */
function mail_message_copy_source(string $id,int $revision,string $validity,string $uid,string $mode,?string $self=null,?callable $resolver=null,?callable $transport=null,?string $noticeHandle=null,string $folder='INBOX'):array {
    if(($mode==='reply-all')!==($self!==null))throw new InvalidArgumentException('Reply-all sender context must be exact.');
    if(!in_array($mode,['reply','reply-all','forward'],true))throw new InvalidArgumentException('Unsupported mail action.');
    if(mail_inbox_folder($folder)!=='INBOX')throw new InvalidArgumentException('Copied-message drafts are available only from INBOX.');
    return mail_message_request_internal($id,$revision,$validity,$uid,$mode,$self,null,$resolver,$transport,$noticeHandle,'INBOX');
}
/** A protected attachment download is another recorded exact-message read. */
function mail_message_attachment(string $id,int $revision,string $validity,string $uid,int $index,?callable $resolver=null,?callable $transport=null,?string $noticeHandle=null,string $folder='INBOX'): array {
    if($index<0||$index>31)throw new InvalidArgumentException('This attachment is unavailable.');
    $bundle=mail_message_request_internal($id,$revision,$validity,$uid,null,null,$index,$resolver,$transport,$noticeHandle,$folder);
    $read=$bundle['result'];unset($read['content']);
    return $read+['attachment'=>$bundle['attachment']];
}
function mail_message_history(string $id,int $page=1): array {
    [$actor]=mail_message_authority($id,null,false);if(!mail_messages_ready())return [];
    $page=max(1,min($page,100000));
    return all('SELECT q.*,r.outcome,r.result_code,r.finished_at,r.message_bytes FROM '.table('mail_message_reads').' q LEFT JOIN '.table('mail_message_results').' r ON r.id=q.id WHERE q.account_id=? AND q.actor_id=? ORDER BY q.started_epoch DESC,q.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id,$actor['id']]);
}
