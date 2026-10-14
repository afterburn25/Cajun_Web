<?php
/** Explicit recent header reads; headers remain only in the current response, never a shared cache. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,EndpointResolver,ProbeFailure};
use CajunWebPro\Foundation\MailReceiving\{InboxReader,ReceivingFailure};
require_once CWP_CODE_ROOT.'/app/mail/connections.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_inbox_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_inbox_migrate.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/ReceivingFailure.php';

function mail_inbox_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_inbox_schema_version'])['value']??'')==='1';}
function mail_inbox_messages(): array {
    return ['ok'=>'Recent inbox headers loaded. Message contents and attachments were not opened.',
        'connection'=>'The inbox server could not be reached. Check the saved settings or try again later.',
        'tls'=>'The inbox server’s encrypted connection or certificate could not be verified.',
        'protocol'=>'The inbox server returned an unsupported or incomplete response. No headers are displayed.',
        'limit'=>'The inbox response exceeded the supported limits. No partial inbox is displayed.',
        'timeout'=>'The inbox request reached its time limit. Try again later.',
        'authentication'=>'The inbox server rejected the saved sign-in details. Ask an owner to check the account settings.',
        'mechanism'=>'This account needs an unsupported sign-in method. OAuth accounts are not available yet.',
        'credential'=>'The saved inbox password could not be opened. Ask an owner to check the private key and account settings.',
        'upgrade'=>'The inbox server did not complete the required encryption upgrade.',
        'destination'=>'The inbox hostname or DNS answers are outside the supported public mail-server policy.',
        'access_changed'=>'The account settings or your access changed. Reload the account before requesting headers again.',
        'mailbox_changed'=>'The inbox changed while headers were loading. Request the current headers again.',
    ];
}
function mail_inbox_message(string $code): string {return mail_inbox_messages()[$code]??'No final result was recorded. Try again after the refresh limit.';}
function mail_inbox_authority(string $id,?int $revision=null,bool $ready=true): array {
    $actor=mail_accounts_actor();
    if(!mail_accounts_can('mail.read',$id))throw new RuntimeException('This inbox is unavailable to your current administrator account.');
    $row=mail_accounts_load($id);
    if($revision!==null && ($revision<1 || (int)$row['revision']!==$revision))throw new RuntimeException('This email account changed. Reload it before requesting headers.');
    if($ready && !mail_inbox_ready())throw new RuntimeException('An owner must prepare inbox reading in Email accounts first.');
    return [$actor,$row];
}
function mail_inbox_transport(array $row,#[\SensitiveParameter] array $secrets,string $address,callable $authorize): array {
    // Load bundled dependencies only for an authorized explicit network request.
    require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/bootstrap.php';
    return InboxReader::read($row['imap_host'],(int)$row['imap_port'],$row['imap_encryption'],$address,$row['imap_username'],$secrets['imap_password'],$authorize);
}
function mail_inbox_uint(mixed $value,bool $zero=false): bool {
    return is_string($value)&&preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$value)
        && (strlen($value)<10 || strcmp($value,'4294967295')<=0) && ($zero || $value!=='0');
}
/** Project only the bounded contract. No raw response or extra MIME content can escape. */
function mail_inbox_batch(#[\SensitiveParameter] array $batch): array {
    if(($batch['folder']??null)!=='INBOX' || !mail_inbox_uint($batch['uidvalidity']??null)
        || !is_int($batch['total']??null) || $batch['total']<0 || $batch['total']>4294967295
        || !is_array($batch['messages']??null) || !array_is_list($batch['messages']) || count($batch['messages'])!==min(20,$batch['total']))throw new ReceivingFailure('protocol');
    $rows=[];$uids=[];
    foreach($batch['messages'] as $row) {
        if(!is_array($row) || !mail_inbox_uint($row['uid']??null) || isset($uids[$row['uid']]) || !is_bool($row['seen']??null) || !mail_inbox_uint($row['bytes']??null,true) || !is_bool($row['headers_truncated']??null))throw new ReceivingFailure('protocol');
        $project=['uid'=>$row['uid'],'seen'=>$row['seen'],'bytes'=>$row['bytes'],'headers_truncated'=>$row['headers_truncated']];
        foreach(['subject','from','date'] as $name) {
            if(!is_string($row[$name]??null) || strlen($row[$name])>1000 || !preg_match('//u',$row[$name]) || preg_match('/[\p{Cc}\p{Cf}]/u',$row[$name]))throw new ReceivingFailure('protocol');
            $project[$name]=$row[$name];
        }
        $uids[$row['uid']]=true;$rows[]=$project;
    }
    return ['folder'=>'INBOX','uidvalidity'=>$batch['uidvalidity'],'total'=>$batch['total'],'messages'=>$rows];
}
function mail_inbox_record(array $started,string $code,int $elapsed,#[\SensitiveParameter] ?array $batch=null): void {
    if(!array_key_exists($code,mail_inbox_messages()) || ($code==='ok')!==($batch!==null))throw new LogicException('Invalid local inbox result.');
    staff_transaction(function()use($started,$code,$elapsed,$batch):void {
        query('INSERT INTO '.table('mail_inbox_results').' (id,outcome,result_code,finished_at,elapsed_ms,message_count,mailbox_total,uidvalidity) VALUES (?,?,?,?,?,?,?,?)',[$started['id'],$code==='ok'?'success':'failed',$code,gmdate('Y-m-d H:i:s'),max(0,min($elapsed,2147483647)),$batch===null?null:count($batch['messages']),$batch['total']??null,$batch['uidvalidity']??null]);
        mail_accounts_audit((int)$started['actor_id'],'inbox_finished','Read '.$started['id'].' '.$code);
    });
}
/** Resolver/transport callables are internal test seams, never HTTP or owner configuration. */
function mail_inbox_read(string $id,int $revision,?callable $resolver=null,?callable $transport=null): array {
    if(db()->inTransaction())throw new LogicException('Inbox reads cannot run inside a transaction.');
    [$started,$row]=staff_locked(fn()=>staff_transaction(function()use($id,$revision):array {
        [$actor,$row]=mail_inbox_authority($id,$revision);
        try {EndpointPolicy::host($row['imap_host']);EndpointPolicy::port('imap',(int)$row['imap_port'],$row['imap_encryption']);}
        catch(InvalidArgumentException) {throw new InvalidArgumentException('Inbox reading requires a public server hostname and incoming 993/TLS or 143/STARTTLS. Ask an owner to update the saved settings.');}
        $now=time();
        if(one('SELECT id FROM '.table('mail_inbox_reads').' WHERE account_id=? AND started_epoch>? LIMIT 1',[$id,$now-15]))throw new RuntimeException('Wait 15 seconds between requests for this inbox.');
        if((int)query('SELECT COUNT(*) FROM '.table('mail_inbox_reads').' WHERE actor_id=? AND started_epoch>?',[$actor['id'],$now-3600])->fetchColumn()>=120)throw new RuntimeException('Your hourly inbox request limit has been reached. Try again later.');
        $started=['id'=>bin2hex(random_bytes(16)),'account_id'=>$id,'account_revision'=>$revision,'actor_id'=>(int)$actor['id'],'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now];
        query('INSERT INTO '.table('mail_inbox_reads').' (id,account_id,account_revision,actor_id,started_at,started_epoch) VALUES (?,?,?,?,?,?)',array_values($started));
        mail_accounts_audit((int)$actor['id'],'inbox_started','Read '.$started['id'].' account '.$id.' revision '.$revision);
        return [$started,$row];
    }));
    $begin=hrtime(true);$code='ok';$addresses=[];
    try {$addresses=EndpointPolicy::addresses(($resolver??[EndpointResolver::class,'resolve'])($row['imap_host']));}
    catch(InvalidArgumentException) {$code='destination';}
    catch(ProbeFailure $e) {$code=$e->getMessage();}
    catch(Throwable) {$code='connection';}
    return staff_locked(function()use($started,$code,$addresses,$begin,$transport):array {
        $authorize=static function()use($started):array {
            try {[$actor,$row]=mail_inbox_authority($started['account_id'],$started['account_revision']);
                if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');
                return $row;
            }catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;throw new ReceivingFailure('access_changed');}
        };
        $batch=null;
        try {$current=$authorize();}catch(ReceivingFailure) {$code='access_changed';}
        if($code==='ok') {
            try {$secrets=mail_accounts_unseal($current['id'],$current['credentials']);mail_accounts_passwords($secrets);}
            catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;$code='credential';}
            if($code==='ok') {
                try {$batch=mail_inbox_batch(($transport??'mail_inbox_transport')($current,$secrets,$addresses[0],$authorize));$authorize();}
                catch(ReceivingFailure $e) {$code=$e->getMessage();$batch=null;}
                catch(Throwable $e) {if($e instanceof PDOException)throw $e;$code='connection';$batch=null;}
                finally {unset($secrets);}
            }
        }
        mail_inbox_record($started,$code,(int)((hrtime(true)-$begin)/1e6),$batch);
        return ['code'=>$code,'request_id'=>$started['id'],'account_id'=>$started['account_id'],'account_revision'=>$started['account_revision'],'actor_id'=>$started['actor_id'],'loaded_at'=>gmdate('Y-m-d H:i:s'),'batch'=>$batch];
    });
}
function mail_inbox_history(string $id,int $page=1): array {
    [$actor]=mail_inbox_authority($id,null,false);if(!mail_inbox_ready())return [];
    $page=max(1,min($page,100000));
    return all('SELECT q.*,r.outcome,r.result_code,r.finished_at,r.message_count,r.mailbox_total FROM '.table('mail_inbox_reads').' q LEFT JOIN '.table('mail_inbox_results').' r ON r.id=q.id WHERE q.account_id=? AND q.actor_id=? ORDER BY q.started_epoch DESC,q.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id,$actor['id']]);
}
