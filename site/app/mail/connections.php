<?php
/** Explicit saved-account diagnostics; no background login or message operations. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\MailTransport\EndpointPolicy;
use CajunWebPro\Foundation\MailTransport\EndpointResolver;
use CajunWebPro\Foundation\MailTransport\SecureChannel;
use CajunWebPro\Foundation\MailTransport\ConnectionProbe;
use CajunWebPro\Foundation\MailTransport\ProbeFailure;
require_once CWP_CODE_ROOT.'/app/mail/accounts.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_connections_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_connections_migrate.php';
foreach(['EndpointPolicy','EndpointResolver','ProbeChannel','ProbeFailure','SecureChannel','ConnectionProbe'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/'.$class.'.php';

function mail_connections_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_connections_schema_version'])['value']??'')==='1';}
function mail_connections_messages(): array {
    return ['ok'=>'Encrypted connection and password authentication succeeded at the recorded time. No message was read or sent.',
        'connection'=>'The server could not be reached. Check its hostname and your host’s outbound connection policy.',
        'tls'=>'Certificate or encrypted connection verification failed. Check the server name, certificate and host trust store.',
        'protocol'=>'The server returned an unsupported or invalid protocol response.',
        'limit'=>'The server response exceeded the allowed limits.',
        'timeout'=>'The connection exceeded its response or operation time limit.',
        'authentication'=>'The server rejected password authentication. Check the saved username and provider-approved password.',
        'mechanism'=>'This server does not offer a supported password sign-in method. OAuth-only accounts require a separate integration.',
        'credential'=>'The saved credentials could not be used. Verify the private key and account passwords.',
        'upgrade'=>'The server did not complete the required encryption upgrade.',
        'destination'=>'The hostname or DNS answers are outside the supported public mail-server policy.',
        'access_changed'=>'Account settings or current access changed; this result cannot establish a usable connection.',
    ];
}
function mail_connections_message(string $code): string {return mail_connections_messages()[$code]??'No final connection result was recorded. You can retry after the cooldown.';}
function mail_connections_authority(string $id,int $revision,string $target,#[\SensitiveParameter] ?string $password=null): array {
    $actor=$password===null?mail_accounts_manager():mail_accounts_owner($password);
    if(!mail_connections_ready())throw new RuntimeException('An owner must prepare connection checks first.');
    if(!in_array($target,['imap','smtp'],true))throw new InvalidArgumentException('Choose incoming or outgoing mail.');
    $row=mail_accounts_load($id);
    if($revision<1 || (int)$row['revision']!==$revision)throw new RuntimeException('This email account changed. Reload before testing its saved settings.');
    if(!mail_accounts_can($target==='imap'?'mail.read':'mail.send',$id))throw new RuntimeException('Enable this account and your current matching mailbox assignment before testing.');
    return [$actor,$row];
}
/** Internal boundary: caller must already hold the staff lock and enforce current authority. */
function mail_connections_transport(array $row,string $target,#[\SensitiveParameter] array $secrets,string $address,callable $authorize): void {
    $channel=SecureChannel::open($target,$row[$target.'_host'],(int)$row[$target.'_port'],$row[$target.'_encryption'],$address);
    (new ConnectionProbe($channel,$authorize))->run($target,$row[$target.'_encryption'],$row[$target.'_username'],$secrets[$target.'_password']);
}
function mail_connections_record(array $started,string $code,int $elapsed): void {
    if(!array_key_exists($code,mail_connections_messages()))throw new LogicException('Invalid local connection result.');
    staff_transaction(function() use($started,$code,$elapsed): void {
        query('INSERT INTO '.table('mail_connection_results').' (id,outcome,result_code,finished_at,elapsed_ms) VALUES (?,?,?,?,?)',[$started['id'],$code==='ok'?'success':'failed',$code,gmdate('Y-m-d H:i:s'),max(0,min($elapsed,2147483647))]);
        mail_accounts_audit((int)$started['actor_id'],'check_finished','Check '.$started['id'].' '.$started['target'].' '.$code);
    });
}
/**
 * Only internal tests can supply resolver/transport callables. No HTTP/configuration driver, CA,
 * address override or arbitrary endpoint is accepted. Network work never holds an InnoDB transaction.
 */
function mail_connections_check(string $id,int $revision,string $target,#[\SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null): string {
    if(db()->inTransaction())throw new LogicException('Connection checks cannot run inside a transaction.');
    [$started,$row]=staff_locked(fn()=>staff_transaction(function() use($id,$revision,$target,$password): array {
        [$actor,$row]=mail_connections_authority($id,$revision,$target,$password);
        try {EndpointPolicy::host($row[$target.'_host']);EndpointPolicy::port($target,(int)$row[$target.'_port'],$row[$target.'_encryption']);}
        catch(InvalidArgumentException) {throw new InvalidArgumentException('Connection checks require a public server hostname and IMAP 993/TLS or 143/STARTTLS, or SMTP 465/TLS or 587/STARTTLS. Edit and save settings first.');}
        $now=time();
        if(one('SELECT id FROM '.table('mail_connection_checks').' WHERE account_id=? AND target=? AND started_epoch>? LIMIT 1',[$id,$target,$now-30]))throw new RuntimeException('Wait 30 seconds between checks of this connection.');
        if((int)query('SELECT COUNT(*) FROM '.table('mail_connection_checks').' WHERE actor_id=? AND started_epoch>?',[$actor['id'],$now-3600])->fetchColumn()>=20)throw new RuntimeException('Connection-check limit reached. Try again later.');
        $started=['id'=>bin2hex(random_bytes(16)),'account_id'=>$id,'account_revision'=>$revision,'target'=>$target,'actor_id'=>(int)$actor['id'],'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now];
        query('INSERT INTO '.table('mail_connection_checks').' (id,account_id,account_revision,target,actor_id,started_at,started_epoch) VALUES (?,?,?,?,?,?,?)',array_values($started));
        mail_accounts_audit((int)$actor['id'],'check_started','Check '.$started['id'].' '.$target.' account '.$id.' revision '.$revision);
        return [$started,$row];
    }));
    $begin=hrtime(true);$code='ok';$addresses=[];
    try {$addresses=EndpointPolicy::addresses(($resolver??[EndpointResolver::class,'resolve'])($row[$target.'_host']));}
    catch(InvalidArgumentException) {$code='destination';}
    catch(ProbeFailure $e) {$code=$e->getMessage();}
    catch(Throwable) {$code='connection';}
    return staff_locked(function() use($started,$row,$target,$password,$addresses,$code,$begin,$transport): string {
        $authorize=static function() use($started,$target): void {
            try {[$actor]=mail_connections_authority($started['account_id'],$started['account_revision'],$target);
                if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');
            } catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;throw new ProbeFailure('access_changed');}
        };
        try {[$actor,$current]=mail_connections_authority($started['account_id'],$started['account_revision'],$target,$password);
            if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');
        } catch(InvalidArgumentException|RuntimeException $e) {
            if($e instanceof PDOException)throw $e;
            mail_connections_record($started,'access_changed',(int)((hrtime(true)-$begin)/1e6));return 'access_changed';
        }
        if($code==='ok') {
            try {$secrets=mail_accounts_unseal($current['id'],$current['credentials']);mail_accounts_passwords($secrets);}
            catch(InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;$code='credential';}
            if($code==='ok') {
                try {($transport??'mail_connections_transport')($current,$target,$secrets,$addresses[0],$authorize);$authorize();}
                catch(ProbeFailure $e) {$code=$e->getMessage();}
                catch(Throwable $e) {if($e instanceof PDOException)throw $e;$code='connection';}
                finally {unset($secrets);}
            }
        }
        mail_connections_record($started,$code,(int)((hrtime(true)-$begin)/1e6));return $code;
    });
}
function mail_connections_history(string $id,int $page=1): array {
    mail_accounts_manager();mail_accounts_load($id);
    if(!mail_connections_ready())return [];
    $page=max(1,min($page,100000));
    return all('SELECT c.*,r.outcome,r.result_code,r.finished_at,r.elapsed_ms,u.name AS actor_name FROM '.table('mail_connection_checks').' c LEFT JOIN '.table('mail_connection_results').' r ON r.id=c.id LEFT JOIN '.table('users').' u ON u.id=c.actor_id WHERE c.account_id=? ORDER BY c.started_epoch DESC,c.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id]);
}
