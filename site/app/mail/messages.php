<?php
/** Explicit exact-message reads. Contents exist only in the authorized response. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,EndpointResolver,ProbeFailure};
use CajunWebPro\Foundation\MailReceiving\{MessageReader,BodyDecoder,ReceivingFailure};
require_once CWP_CODE_ROOT.'/app/mail/inbox.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_messages_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_messages_migrate.php';

function mail_messages_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_messages_schema_version'])['value']??'')==='1';}
function mail_message_outcomes(): array {
    return array_replace(mail_inbox_messages(),[
        'ok'=>'Message loaded. The server’s read flag was left unchanged.',
        'protocol'=>'The server or message returned an unsupported or incomplete response. No message is displayed.',
        'limit'=>'This message exceeds the supported size or formatting limits. Open it in your regular email app.',
        'timeout'=>'The message request reached its time limit. Try again later.',
        'access_changed'=>'The account settings or your access changed. Reload the inbox before opening a message.',
        'mailbox_changed'=>'This exact message is no longer available in the current inbox. Load the recent headers again.',
    ]);
}
function mail_message_outcome(string $code): string {return mail_message_outcomes()[$code]??'No final result was recorded. Try again after the request limit.';}
function mail_message_identity(mixed $validity,mixed $uid): void {
    if(!mail_inbox_uint($validity)||!mail_inbox_uint($uid))throw new InvalidArgumentException('Open a message from the current inbox headers.');
}
function mail_message_authority(string $id,?int $revision=null,bool $ready=true): array {
    [$actor,$row]=mail_inbox_authority($id,$revision,false);
    if($ready&&!mail_messages_ready())throw new RuntimeException('An owner must prepare message reading in Email accounts first.');
    return [$actor,$row];
}
function mail_message_transport(array $row,#[\SensitiveParameter] array $secrets,string $address,string $validity,string $uid,callable $authorize): array {
    require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/bootstrap.php';
    return MessageReader::read($row['imap_host'],(int)$row['imap_port'],$row['imap_encryption'],$address,$row['imap_username'],$secrets['imap_password'],$validity,$uid,$authorize);
}
/** Even injected transports supply raw MIME, never trusted HTML or decoded metadata. */
function mail_message_content(#[\SensitiveParameter] array $response,string $validity,string $uid): array {
    if(($response['folder']??null)!=='INBOX'||($response['uidvalidity']??null)!==$validity||($response['uid']??null)!==$uid
        ||!is_bool($response['seen']??null)||!mail_inbox_uint($response['bytes']??null,true)||!is_string($response['raw']??null))throw new ReceivingFailure('protocol');
    if(strlen($response['raw'])>262144)throw new ReceivingFailure('limit');
    if((string)strlen($response['raw'])!==$response['bytes'])throw new ReceivingFailure('protocol');
    require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/bootstrap.php';
    return ['folder'=>'INBOX','uidvalidity'=>$validity,'uid'=>$uid,'seen'=>$response['seen'],'bytes'=>$response['bytes']]+BodyDecoder::decode($response['raw']);
}
function mail_message_record(array $started,string $code,int $elapsed,#[\SensitiveParameter] ?array $content=null): void {
    if(!array_key_exists($code,mail_message_outcomes())||($code==='ok')!==($content!==null))throw new LogicException('Invalid local message result.');
    staff_transaction(function()use($started,$code,$elapsed,$content):void {
        query('INSERT INTO '.table('mail_message_results').' (id,outcome,result_code,finished_at,elapsed_ms,message_bytes) VALUES (?,?,?,?,?,?)',[$started['id'],$code==='ok'?'success':'failed',$code,gmdate('Y-m-d H:i:s'),max(0,min($elapsed,2147483647)),$content['bytes']??null]);
        if($content!==null){require_once CWP_CODE_ROOT.'/app/mail/notices.php';mail_notices_opened($started,$content);}
        mail_accounts_audit((int)$started['actor_id'],'message_finished','Read '.$started['id'].' '.$code);
    });
}
/** Test seams are internal PHP callables; never exposed through owner/HTTP configuration. */
function mail_message_read(string $id,int $revision,string $validity,string $uid,?callable $resolver=null,?callable $transport=null,?string $noticeHandle=null,bool $retainSource=false): array {
    mail_message_identity($validity,$uid);
    if($noticeHandle!==null){require_once CWP_CODE_ROOT.'/app/mail/notices.php';mail_notices_handle($noticeHandle);}
    if(db()->inTransaction())throw new LogicException('Message reads cannot run inside a transaction.');
    [$started,$row]=staff_locked(fn()=>staff_transaction(function()use($id,$revision,$validity,$uid,$noticeHandle):array {
        [$actor,$row]=mail_message_authority($id,$revision);
        if($noticeHandle!==null)mail_notices_resolve($noticeHandle,['account_id'=>$id,'account_revision'=>$revision,'folder'=>'INBOX','uidvalidity'=>$validity,'uid'=>$uid]);
        try {EndpointPolicy::host($row['imap_host']);EndpointPolicy::port('imap',(int)$row['imap_port'],$row['imap_encryption']);}
        catch(InvalidArgumentException) {throw new InvalidArgumentException('Message reading requires a public incoming server on 993/TLS or 143/STARTTLS. Ask an owner to check the saved settings.');}
        $now=time();
        if(one('SELECT id FROM '.table('mail_message_reads').' WHERE account_id=? AND started_epoch>? LIMIT 1',[$id,$now-2]))throw new RuntimeException('Wait 2 seconds between message requests for this account.');
        foreach(['account_id'=>$id,'actor_id'=>$actor['id']] as $column=>$value) {
            if((int)query('SELECT COUNT(*) FROM '.table('mail_message_reads').' WHERE '.$column.'=? AND started_epoch>?',[$value,$now-3600])->fetchColumn()>=240)throw new RuntimeException('The hourly message request limit for this account or administrator has been reached. Try again later.');
        }
        $started=['id'=>bin2hex(random_bytes(16)),'account_id'=>$id,'account_revision'=>$revision,'actor_id'=>(int)$actor['id'],'folder'=>'INBOX','uidvalidity'=>$validity,'uid'=>$uid,'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now];
        query('INSERT INTO '.table('mail_message_reads').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?)',array_values($started));
        mail_accounts_audit((int)$actor['id'],'message_started','Read '.$started['id'].' account '.$id.' revision '.$revision);
        return [$started,$row];
    }));
    $begin=hrtime(true);$code='ok';$addresses=[];
    try {$addresses=EndpointPolicy::addresses(($resolver??[EndpointResolver::class,'resolve'])($row['imap_host']));}
    catch(InvalidArgumentException) {$code='destination';}
    catch(ProbeFailure $e) {$code=$e->getMessage();}
    catch(Throwable) {$code='connection';}
    return staff_locked(function()use($started,$code,$addresses,$begin,$transport,$noticeHandle,$retainSource):array {
        $authorize=static function()use($started,$noticeHandle):array {
            try {[$actor,$row]=mail_message_authority($started['account_id'],$started['account_revision']);
                if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');
                if($noticeHandle!==null)mail_notices_resolve($noticeHandle,$started);
                return $row;
            }catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;throw new ReceivingFailure('access_changed');}
        };
        $content=null;
        try {$current=$authorize();}catch(ReceivingFailure) {$code='access_changed';}
        if($code==='ok') {
            try {$secrets=mail_accounts_unseal($current['id'],$current['credentials']);mail_accounts_passwords($secrets);}
            catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;$code='credential';}
            if($code==='ok') {
                try {
                    $response=($transport??'mail_message_transport')($current,$secrets,$addresses[0],$started['uidvalidity'],$started['uid'],$authorize);
                    $authorize();
                    $content=mail_message_content($response,$started['uidvalidity'],$started['uid']);
                    // Only an internal caller that must re-parse the exact bytes
                    // (protected Reply/Forward draft creation) may retain the raw
                    // source; it is never persisted or returned to display paths.
                    if($retainSource)$content['source']=$response['raw'];
                    $authorize();
                }catch(ReceivingFailure $e) {$code=$e->getMessage();$content=null;}
                catch(Throwable $e) {if($e instanceof PDOException)throw $e;$code='connection';$content=null;}
                finally {unset($secrets,$response);}
            }
        }
        mail_message_record($started,$code,(int)((hrtime(true)-$begin)/1e6),$content);
        return ['code'=>$code,'request_id'=>$started['id'],'account_id'=>$started['account_id'],'account_revision'=>$started['account_revision'],'actor_id'=>$started['actor_id'],'uidvalidity'=>$started['uidvalidity'],'uid'=>$started['uid'],'notice_handle'=>$noticeHandle,'loaded_at'=>gmdate('Y-m-d H:i:s'),'content'=>$content];
    });
}
function mail_message_history(string $id,int $page=1): array {
    [$actor]=mail_message_authority($id,null,false);if(!mail_messages_ready())return [];
    $page=max(1,min($page,100000));
    return all('SELECT q.*,r.outcome,r.result_code,r.finished_at,r.message_bytes FROM '.table('mail_message_reads').' q LEFT JOIN '.table('mail_message_results').' r ON r.id=q.id WHERE q.account_id=? AND q.actor_id=? ORDER BY q.started_epoch DESC,q.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id,$actor['id']]);
}
