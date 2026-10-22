<?php
/** Explicit server read-flag changes on one exact message. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\MailTransport\{EndpointPolicy,EndpointResolver,ProbeFailure};
use CajunWebPro\Foundation\MailReceiving\{FlagWriter,ReceivingFailure};
require_once CWP_CODE_ROOT.'/app/mail/inbox.php';
require_once CWP_CODE_ROOT.'/app/mail/notices.php';
require_once CWP_CODE_ROOT.'/app/mail/messages.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_flags_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_flags_migrate.php';

function mail_flags_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_flags_schema_version'])['value']??'')==='1';}
function mail_flags_outcomes(): array {
    return array_replace(mail_inbox_messages(),[
        'ok'=>'The server read flag was updated.',
        'unchanged'=>'The server already reported this read state. No flag change was sent.',
        'unconfirmed'=>'The server accepted the command but did not confirm the flag changed. The flag may already have changed; load the message again to see the current state before marking.',
        'flags'=>'The server refused the flag change. The message state is unchanged here.',
        'folder'=>'The server folder is unavailable. Load the recent headers again before marking.',
        'mailbox_changed'=>'This exact message is no longer available in the current folder. Load the recent headers again.',
        'access_changed'=>'The account settings or your access changed. Reload the inbox before marking messages.',
        'protocol'=>'The server returned an unsupported or incomplete response. No flag change is recorded.',
        'limit'=>'The flag request reached a size or count limit. No flag change is recorded.',
        'timeout'=>'The flag request reached its time limit. Try again later.',
        'upgrade'=>'The server did not accept a required connection upgrade. No flag change is recorded.',
        'tls'=>'The server certificate or TLS version could not be verified. No flag change is recorded.',
        'mechanism'=>'The server does not offer a supported sign-in mechanism. No flag change is recorded.',
        'authentication'=>'The saved sign-in was refused. An owner can update the saved settings.',
        'credential'=>'The stored sign-in could not be used for this request.',
        'connection'=>'The server could not be reached. Try again later.',
        'destination'=>'The saved incoming server is not an allowed destination. Ask an owner to check the saved settings.',
        'dns_public'=>'The saved incoming server did not resolve to a public address. Ask an owner to check the saved settings.',
        'dns_rate'=>'Address checks are busy. Try again later.',
    ]);
}
function mail_flag_outcome(string $code): string {return mail_flags_outcomes()[$code]??'No final result was recorded. Try again after the request limit.';}
function mail_flag_authority(string $id,?int $revision=null,bool $ready=true): array {
    [$actor,$row]=mail_inbox_authority($id,$revision,false);
    if($ready&&!mail_flags_ready())throw new RuntimeException('An owner must prepare message flags in Email accounts first.');
    return [$actor,$row];
}
function mail_flag_transport(array $row,#[\SensitiveParameter] array $secrets,string $address,string $folder,string $validity,string $uid,bool $seen,callable $authorize): array {
    // Load bundled dependencies only for an authorized explicit network request.
    require_once CWP_CODE_ROOT.'/includes/foundation/mail-receiving/bootstrap.php';
    return FlagWriter::mark($row['imap_host'],(int)$row['imap_port'],$row['imap_encryption'],$address,$row['imap_username'],$secrets['imap_password'],$folder,$validity,$uid,$seen,$authorize);
}
/** Project only the bounded contract. Nothing else the session saw can escape. */
function mail_flag_response(#[\SensitiveParameter] array $response,string $folder,string $validity,string $uid,bool $seen): array {
    if(($response['folder']??null)!==$folder||($response['uidvalidity']??null)!==$validity||($response['uid']??null)!==$uid
        ||($response['seen']??null)!==$seen||!is_bool($response['applied']??null)||!is_bool($response['confirmed']??null))throw new ReceivingFailure('protocol');
    return ['applied'=>$response['applied'],'confirmed'=>$response['confirmed']];
}
function mail_flag_record(array $started,string $code,int $elapsed,?bool $applied): void {
    staff_transaction(function()use($started,$code,$elapsed,$applied):void {
        $outcome=$code==='ok'||$code==='unchanged'?'success':($code==='unconfirmed'?'uncertain':'failed');
        query('INSERT INTO '.table('mail_flag_results').' (id,outcome,result_code,applied,finished_at,elapsed_ms) VALUES (?,?,?,?,?,?)',[$started['id'],$outcome,$code,$applied===null?null:(int)$applied,gmdate('Y-m-d H:i:s'),max(0,min($elapsed,2147483647))]);
        if(($code==='ok'||$code==='unchanged')&&mail_notices_ready()) {
            require_once CWP_CODE_ROOT.'/app/mail/notices.php';
            query('UPDATE '.table('mail_notice_items').' SET server_seen=?,flags_observed_at=? WHERE account_id=? AND folder=? AND uidvalidity=? AND uid=?',
                [(int)$started['mark_seen'],gmdate('Y-m-d H:i:s'),$started['account_id'],$started['folder'],$started['uidvalidity'],$started['uid']]);
        }
        mail_accounts_audit((int)$started['actor_id'],'flag_finished','Flag '.$started['id'].' '.$code);
    });
}
/**
 * Change the server \Seen flag on one exact message through an explicit action.
 * Opening or marking a message never happens silently from a read.
 */
function mail_message_mark(string $id,int $revision,string $folder,string $validity,string $uid,bool $seen,?callable $resolver=null,?callable $transport=null): array {
    mail_message_identity($validity,$uid);
    $folder=mail_inbox_folder($folder);
    if(db()->inTransaction())throw new LogicException('Flag requests cannot run inside a transaction.');
    [$started,$row]=staff_locked(fn()=>staff_transaction(function()use($id,$revision,$folder,$validity,$uid,$seen):array {
        [$actor,$row]=mail_flag_authority($id,$revision);
        try {EndpointPolicy::host($row['imap_host']);EndpointPolicy::port('imap',(int)$row['imap_port'],$row['imap_encryption']);}
        catch(InvalidArgumentException) {throw new InvalidArgumentException('Message flags require a public incoming server on 993/TLS or 143/STARTTLS. Ask an owner to check the saved settings.');}
        $now=time();
        if(one('SELECT id FROM '.table('mail_flag_requests').' WHERE account_id=? AND started_epoch>? LIMIT 1',[$id,$now-5]))throw new RuntimeException('Wait 5 seconds between flag requests for this account.');
        foreach(['account_id'=>$id,'actor_id'=>$actor['id']] as $column=>$value) {
            if((int)query('SELECT COUNT(*) FROM '.table('mail_flag_requests').' WHERE '.$column.'=? AND started_epoch>?',[$value,$now-3600])->fetchColumn()>=120)throw new RuntimeException('The hourly flag request limit for this account or administrator has been reached. Try again later.');
        }
        $started=['id'=>bin2hex(random_bytes(16)),'account_id'=>$id,'account_revision'=>$revision,'actor_id'=>(int)$actor['id'],'folder'=>$folder,'uidvalidity'=>$validity,'uid'=>$uid,'mark_seen'=>(int)$seen,'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now];
        query('INSERT INTO '.table('mail_flag_requests').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,mark_seen,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?,?)',array_values($started));
        mail_accounts_audit((int)$actor['id'],'flag_started','Flag '.$started['id'].' account '.$id.' revision '.$revision.' '.$started['folder'].' uid '.$uid.' seen '.$started['mark_seen']);
        return [$started,$row];
    }));
    $begin=hrtime(true);$code='ok';$addresses=[];
    try {$addresses=EndpointPolicy::addresses(($resolver??[EndpointResolver::class,'resolve'])($row['imap_host']));}
    catch(InvalidArgumentException) {$code='destination';}
    catch(ProbeFailure $e) {$code=$e->getMessage();}
    catch(Throwable) {$code='connection';}
    return staff_locked(function()use($started,$code,$addresses,$begin,$transport,$seen):array {
        $authorize=static function()use($started):array {
            try {[$actor,$row]=mail_flag_authority($started['account_id'],$started['account_revision']);
                if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');
                return $row;
            }catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;throw new ReceivingFailure('access_changed');}
        };
        $applied=null;
        try {$current=$authorize();}catch(ReceivingFailure) {$code='access_changed';}
        if($code==='ok') {
            try {$secrets=mail_accounts_unseal($current['id'],$current['credentials']);mail_accounts_passwords($secrets);}
            catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;$code='credential';}
            if($code==='ok') {
                try {
                    $response=($transport??'mail_flag_transport')($current,$secrets,$addresses[0],$started['folder'],$started['uidvalidity'],$started['uid'],$seen,$authorize);
                    $authorize();
                    $result=mail_flag_response($response,$started['folder'],$started['uidvalidity'],$started['uid'],$seen);
                    $applied=$result['applied'];
                    if(!$applied)$code='unchanged';
                    elseif(!$result['confirmed'])$code='unconfirmed';
                }catch(ReceivingFailure $e) {$code=$e->getMessage();$applied=null;}
                catch(Throwable $e) {if($e instanceof PDOException)throw $e;$code='connection';$applied=null;}
                finally {unset($secrets,$response);}
            }
        }
        mail_flag_record($started,$code,(int)((hrtime(true)-$begin)/1e6),$applied);
        return ['code'=>$code,'request_id'=>$started['id'],'account_id'=>$started['account_id'],'account_revision'=>$started['account_revision'],'actor_id'=>$started['actor_id'],'folder'=>$started['folder'],'uidvalidity'=>$started['uidvalidity'],'uid'=>$started['uid'],'seen'=>$seen,'applied'=>$applied,'finished_at'=>gmdate('Y-m-d H:i:s')];
    });
}
function mail_flags_history(string $id,int $page=1): array {
    [$actor]=mail_flag_authority($id,null,false);if(!mail_flags_ready())return [];
    $page=max(1,min($page,100000));
    return all('SELECT r.started_at,r.account_revision,r.folder,r.uidvalidity,r.uid,r.mark_seen,o.outcome,o.result_code,o.applied FROM '.table('mail_flag_requests').' r LEFT JOIN '.table('mail_flag_results').' o ON o.id=r.id WHERE r.account_id=? AND r.actor_id=? ORDER BY r.started_epoch DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id,$actor['id']]);
}
/** Latest confirmed server read state for one exact message, or null when no confirmed outcome exists. */
function mail_flag_state(string $id,int $revision,string $folder,string $validity,string $uid): ?bool {
    [$actor]=mail_flag_authority($id,null,false);if(!mail_flags_ready())return null;
    $row=one('SELECT r.mark_seen FROM '.table('mail_flag_requests').' r JOIN '.table('mail_flag_results').' o ON o.id=r.id WHERE r.account_id=? AND r.account_revision=? AND r.actor_id=? AND r.folder=? AND r.uidvalidity=? AND r.uid=? AND o.result_code IN (\'ok\',\'unchanged\') ORDER BY r.started_epoch DESC,r.id DESC LIMIT 1',[$id,$revision,$actor['id'],$folder,$validity,$uid]);
    return $row===null?null:(bool)$row['mark_seen'];
}
