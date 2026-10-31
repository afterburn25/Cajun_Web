<?php
/** Explicit fixed package-list authentication check; no inventory or general operation route. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Provider\{ConnectionCheck,ProviderCredential,ProviderGateway,TransportException};
use CajunWebPro\Foundation\TwentyI\{TwentyIAdapter,FixedHttpTransport,ProbeOnlyLedger};
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;
require_once CWP_CODE_ROOT.'/app/providers/settings.php';
foreach(['ProviderCapability','ProviderCredential','TransportRequest','TransportResponse','TransportException','HttpTransport','ProviderOperation','OperationResult','ConnectionCheck','RetryPolicy','LedgerClaim','OperationLedger','ProviderAdapter','ProviderGateway'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/provider/'.$class.'.php';
foreach(['EndpointPolicy','ProbeFailure','EndpointResolver'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/'.$class.'.php';
foreach(['TwentyIAdapter','ProbeOnlyLedger','FixedHttpTransport'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/twentyi/'.$class.'.php';

final class ProviderCheckAccessChanged extends RuntimeException {}

/** Durable codes are independently mapped here; arbitrary adapter detail is never stored. */
function provider_checks_codes():array {
    return [
        'connected'=>['verified','20i accepted the saved key for the package-list read at the recorded time. Other operations remain unverified.'],
        'authentication'=>['authentication_failed','20i rejected authentication. Check the saved General API key.'],
        'connect'=>['unreachable','The API connection could not be established. Check the host outbound connection policy.'],
        'tls'=>['unreachable','Certificate or encrypted connection verification failed. Check the host trust store.'],
        'unavailable'=>['unreachable','The public API destination or required PHP transport is unavailable.'],
        'wrong_provider'=>['unsupported','The saved profile is not supported by this connection check.'],
        'invalid_credential'=>['unsupported','The saved General API key is not usable by this connection check.'],
        'credential'=>['unsupported','Protected credentials could not be opened. Check the original private key and saved settings.'],
        'rate_limited'=>['unknown','20i limited this request. No usable connection verdict was recorded.'],
        'remote_error'=>['unknown','20i returned a server error. No usable connection verdict was recorded.'],
        'unexpected_status'=>['unknown','The API returned an unexpected status. No usable connection verdict was recorded.'],
        'response_limit'=>['unknown','The API response exceeded the accepted size. No usable connection verdict was recorded.'],
        'unexpected_response'=>['unknown','The API did not return an accepted package-list response.'],
        'timeout'=>['unknown','The connection exceeded a transfer time limit. Its outcome is uncertain.'],
        'protocol'=>['unknown','The connection did not complete within the accepted protocol or response limits.'],
        'unknown'=>['unknown','No usable connection verdict was obtained. The outcome is uncertain.'],
        'access_changed'=>['unknown','Current access or the saved profile changed. This attempt cannot establish a usable connection.'],
        'invalid_result'=>['unknown','The connection returned an unrecognized local result. No usable verdict was recorded.'],
    ];
}
function provider_checks_message(?string $code):string {return provider_checks_codes()[$code??''][1]??'No final result was recorded. This attempt is incomplete; retry explicitly after the cooldown.';}
function provider_checks_authority(string $id,int $revision,#[\SensitiveParameter] ?string $password=null):array {
    $actor=$password===null?provider_settings_manager():provider_settings_owner($password);$row=provider_settings_load($id);
    if($revision<1||(int)$row['revision']!==$revision)throw new RuntimeException('This provider profile changed. Reload before checking.');
    if((int)$row['enabled']!==1||$row['credentials']==='')throw new RuntimeException('Enable the profile with a saved General API key before checking.');
    return [$actor,$row];
}
/** Internal only. Current authority and the staff mutex must precede opening the credential. */
function provider_checks_transport(array $row,#[\SensitiveParameter] string $secret,string $address,callable $authorize):ConnectionCheck {
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($secret,$authorize):string{$authorize();return $secret;});
    return (new ProviderGateway(new TwentyIAdapter(),new FixedHttpTransport($address,$authorize),new ProbeOnlyLedger()))->checkConnection($credential);
}
function provider_checks_record(array $started,string $code,int $elapsed):void {
    $state=provider_checks_codes()[$code][0]??null;if($state===null)throw new LogicException('Unknown local provider result.');
    staff_transaction(function()use($started,$code,$state,$elapsed):void {
        query('INSERT INTO '.table('provider_check_results').' (id,state,result_code,finished_at,elapsed_ms) VALUES (?,?,?,?,?)',[$started['id'],$state,$code,gmdate('Y-m-d H:i:s'),max(0,min($elapsed,2147483647))]);
        provider_settings_audit((int)$started['actor_id'],'check_finished','Check '.$started['id'].' '.$state.' '.$code);
    });
}
/** Resolver and transport seams are internal test dependencies, never HTTP/configuration input. */
function provider_checks_check(string $id,int $revision,#[\SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):string {
    if(db()->inTransaction())throw new LogicException('Provider checks cannot run inside a transaction.');
    $started=staff_locked(fn()=>staff_transaction(function()use($id,$revision,$password):array {
        [$actor]=provider_checks_authority($id,$revision,$password);$now=time();
        if(one('SELECT id FROM '.table('provider_checks').' WHERE profile_id=? AND started_epoch>? LIMIT 1',[$id,$now-30]))throw new RuntimeException('Wait 30 seconds between checks of this profile.');
        if((int)query('SELECT COUNT(*) FROM '.table('provider_checks').' WHERE actor_id=? AND started_epoch>?',[$actor['id'],$now-3600])->fetchColumn()>=20)throw new RuntimeException('Connection-check limit reached. Try again later.');
        $started=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$id,'profile_revision'=>$revision,'actor_id'=>(int)$actor['id'],'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now];
        query('INSERT INTO '.table('provider_checks').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch) VALUES (?,?,?,?,?,?)',array_values($started));
        provider_settings_audit((int)$actor['id'],'check_started','Check '.$started['id'].' profile '.$id.' revision '.$revision);return $started;
    }));
    $begin=hrtime(true);$code=null;$address='';
    try{$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];}
    catch(Throwable $e){if($e instanceof PDOException)throw $e;$code='unavailable';}
    return staff_locked(function()use($started,$password,$address,$code,$begin,$transport):string {
        $authorize=static function()use($started):void {
            try{[$actor]=provider_checks_authority($started['profile_id'],$started['profile_revision']);if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;throw new ProviderCheckAccessChanged('Current provider access changed.');}
        };
        try{[$actor,$row]=provider_checks_authority($started['profile_id'],$started['profile_revision'],$password);if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');}
        catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;provider_checks_record($started,'access_changed',(int)((hrtime(true)-$begin)/1e6));return 'access_changed';}
        if($code===null) {
            try{$authorize();$secret=provider_settings_key_value(provider_settings_unseal($row['id'],$row['environment'],$row['credentials']));}
            catch(ProviderCheckAccessChanged){$code='access_changed';}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$code='credential';}
            if($code===null) {
                try{
                    $result=($transport??'provider_checks_transport')($row,$secret,$address,$authorize);
                    $expected=$result instanceof ConnectionCheck?(provider_checks_codes()[$result->detail][0]??null):null;
                    $code=$expected!==null&&$expected===$result->state?$result->detail:'invalid_result';
                }catch(ProviderCheckAccessChanged){$code='access_changed';}
                catch(TransportException $e){$code=$e->kind;}
                catch(Throwable $e){if($e instanceof PDOException)throw $e;$code='unknown';}
                finally{unset($secret,$result);}
            }
        }
        // Even thrown/invalid transport outcomes cannot retain success after current access changes.
        try{$authorize();}catch(ProviderCheckAccessChanged){$code='access_changed';}
        provider_checks_record($started,$code,(int)((hrtime(true)-$begin)/1e6));return $code;
    });
}
/** Saved current-settings success only; viewing the next step never checks the provider. */
function provider_checks_current_success(string $id):bool {
    provider_settings_manager();$profile=provider_settings_load($id);
    if((int)$profile['enabled']!==1||$profile['credentials']==='')return false;
    // Inspect intents, including unfinished ones, independently of the history page.
    $attempts=all('SELECT c.profile_revision,c.started_epoch,r.state,r.result_code,r.finished_at FROM '.table('provider_checks').' c LEFT JOIN '.table('provider_check_results').' r ON r.id=c.id WHERE c.profile_id=? ORDER BY c.started_epoch DESC,c.id DESC LIMIT 2',[$id]);
    if(!$attempts)return false;
    $latest=$attempts[0];
    // Normal starts are separated by the cooldown; an ambiguous retained tie is not success.
    if(isset($attempts[1])&&(string)$latest['started_epoch']===(string)$attempts[1]['started_epoch'])return false;
    return (int)$latest['profile_revision']===(int)$profile['revision']&&$latest['state']==='verified'
        &&$latest['result_code']==='connected'&&$latest['finished_at']!==null;
}
function provider_checks_history(string $id,int $page=1):array {
    provider_settings_manager();provider_settings_load($id);$page=max(1,min($page,100000));
    return all('SELECT c.*,r.state,r.result_code,r.finished_at,r.elapsed_ms,u.name AS actor_name FROM '.table('provider_checks').' c LEFT JOIN '.table('provider_check_results').' r ON r.id=c.id LEFT JOIN '.table('users').' u ON u.id=c.actor_id WHERE c.profile_id=? ORDER BY c.started_epoch DESC,c.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id]);
}
