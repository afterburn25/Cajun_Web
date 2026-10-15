<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/settings.php';
require_once CWP_CODE_ROOT.'/app/providers/checks.php';
require_once CWP_CODE_ROOT.'/database/migrations/provider_operations_migrate.php';
foreach(['ProviderCapability','ProviderCredential','TransportRequest','TransportResponse','TransportException','HttpTransport','ProviderOperation','OperationResult','ConnectionCheck','RetryPolicy','LedgerClaim','OperationLedger','ProviderAdapter','ProviderGateway'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/provider/'.$class.'.php';
foreach(['EndpointPolicy','ProbeFailure','EndpointResolver'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/mail-transport/'.$class.'.php';
foreach(['TwentyIAdapter','ProbeOnlyLedger','FixedHttpTransport'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/twentyi/'.$class.'.php';
foreach(['TwentyIOperation','TwentyIOperationRegister','TwentyIRequestBuilder','TwentyIResultClassifier','TwentyIOperationAdapter'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-operations/'.$class.'.php';

use CajunWebPro\Foundation\Provider\{LedgerClaim,OperationLedger,OperationResult,ProviderCredential,ProviderGateway,ProviderOperation};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyI\Operations\{TwentyIOperationAdapter,TwentyIOperationRegister,TwentyIRequestBuilder};
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;

/**
 * Durable provider operation layer.
 *
 * ProviderOperationLedger is the OperationLedger implementation the
 * gateway requires before any provider operation may execute: an
 * atomic INSERT claims the idempotency key, a resolved row replays the
 * recorded result, a foreign fingerprint conflicts, and a stale claim
 * classifies uncertain — a crashed execution can never be silently
 * retried.
 *
 * The grant functions are the owner approval records a future
 * execution path must verify before claiming. A grant binds profile,
 * operation and the canonical scope fingerprint of the exact approved
 * parameters; expiry and revocation are durable. Granting is an
 * owner-password action and writes an audit event.
 *
 * Nothing here resolves a credential, builds a request, or calls a
 * provider — it is the persistence and approval substrate only.
 */
final class ProviderOperationLedger implements OperationLedger
{
    private const FINAL_STATES = ['succeeded','failed','unsupported','uncertain','conflict','in_progress','retryable'];

    public function claim(string $idempotencyKey, string $fingerprint, int $now): LedgerClaim
    {
        if (!provider_operations_ready()) throw new RuntimeException('Provider operation storage is not ready.');
        try {
            query('INSERT INTO '.table('provider_operations').' (id,idempotency_key,fingerprint,status,result_message,attempts,claimed_at,claimed_epoch) VALUES (?,?,?,?,?,?,?,?)',
                [bin2hex(random_bytes(16)),$idempotencyKey,$fingerprint,'claimed','',0,gmdate('Y-m-d H:i:s',$now),$now]);
            return LedgerClaim::claimed();
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1062) throw $e;
        }
        $row=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$idempotencyKey]);
        if ($row===null || !hash_equals($row['fingerprint'],$fingerprint)) return LedgerClaim::conflict();
        if ($row['status']!=='claimed') return LedgerClaim::replay(self::recorded($row));
        if ($now-(int)$row['claimed_epoch']>self::RESERVATION_TTL) return LedgerClaim::uncertain();
        return LedgerClaim::inProgress();
    }

    public function record(string $idempotencyKey, string $fingerprint, OperationResult $result, int $now): void
    {
        $statement=query('UPDATE '.table('provider_operations').' SET operation=?,status=?,result_message=?,provider_code=?,external_id=?,attempts=?,resolved_epoch=? WHERE idempotency_key=? AND fingerprint=? AND status=?',
            [$result->operation,$result->status,$result->message,$result->providerCode,$result->externalId,$result->attempts,$now,$idempotencyKey,$fingerprint,'claimed']);
        if ($statement->rowCount()!==1) throw new RuntimeException('The operation reservation no longer matches the recorded claim.');
    }

    /** Rebuild the bounded recorded result for replay. */
    private static function recorded(array $row): OperationResult
    {
        $operation=(string)($row['operation']??'provider.operation');
        $result=match((string)$row['status']) {
            'succeeded'=>OperationResult::succeeded($operation,(string)$row['result_message'],$row['external_id'],$row['provider_code']),
            'failed'=>OperationResult::failed($operation,(string)$row['result_message'],$row['provider_code']),
            'retryable'=>OperationResult::retryable($operation,(string)$row['result_message'],$row['provider_code']),
            'uncertain'=>OperationResult::uncertain($operation,$row['provider_code']),
            'in_progress'=>OperationResult::inProgress($operation),
            'conflict'=>OperationResult::conflict($operation),
            default=>OperationResult::unsupported($operation),
        };
        return $result->withAttempts((int)$row['attempts']);
    }
}

/**
 * The canonical fingerprint of an approved request scope: operation
 * name plus recursively-sorted bounded parameters, keyed to this
 * installation's vault key. Grant checks use hash_equals.
 */
function provider_operations_scope(string $operation,array $params): string {
    $nodes=0;
    return hash_hmac('sha256',$operation."\n".provider_operations_canonical($params,0,$nodes),provider_settings_vault_key());
}

function provider_operations_canonical(mixed $value,int $depth,int &$nodes): string {
    if (++$nodes>2048||$depth>32) throw new InvalidArgumentException('Operation scope exceeds the supported size and depth.');
    if (is_array($value)) {
        $parts=[];
        if (array_is_list($value)) {
            foreach($value as $item)$parts[]=provider_operations_canonical($item,$depth+1,$nodes);
            return '['.implode(',',$parts).']';
        }
        ksort($value,SORT_STRING);
        foreach($value as $key=>$item) {
            $encoded=json_encode((string)$key);
            if($encoded===false) throw new InvalidArgumentException('Operation scope map keys must be encodable UTF-8.');
            $parts[]=$encoded.':'.provider_operations_canonical($item,$depth+1,$nodes);
        }
        return '{'.implode(',',$parts).'}';
    }
    if (is_object($value)||is_resource($value)) throw new InvalidArgumentException('Operation scope accepts JSON scalars, lists and maps.');
    $json=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_LINE_TERMINATORS);
    if ($json===false) throw new InvalidArgumentException('Operation scope must be JSON-representable.');
    return $json;
}

/**
 * Owner-approved grant for one exact operation+scope on a profile.
 * The approved parameters are stored as bounded canonical JSON so a
 * grant is a complete approved request — execution supplies only the
 * idempotency key and consumes the grant once.
 */
function provider_operation_grant(string $profileId,string $operation,array $scopeParams,#[\SensitiveParameter] string $password,int $ttlSeconds=3600): string {
    if (!preg_match('/^[a-z0-9]+(\.[a-z0-9]+)*$/',$operation)) throw new InvalidArgumentException('Operation names use dotted lowercase form.');
    if ($ttlSeconds<60||$ttlSeconds>86400) throw new InvalidArgumentException('Grant duration must be between 60 seconds and 24 hours.');
    $register=new TwentyIOperationRegister();
    $spec=$register->get($operation);
    if ($spec===null||!$spec->shapeComplete) throw new InvalidArgumentException('Only documented, shape-complete operations can be granted.');
    $nodes=0;$scopeJson=provider_operations_canonical($scopeParams,0,$nodes);
    if (strlen($scopeJson)>4096) throw new InvalidArgumentException('The approved scope exceeds the bounded grant size.');
    // The approved scope must actually build a bounded documented
    // request — approving an unbuildable scope can never produce a run.
    try{(new TwentyIRequestBuilder($register))->build(new ProviderOperation($operation,'grant-validation',json_decode($scopeJson,true)));}
    catch(InvalidArgumentException $e){throw new InvalidArgumentException('The approved request fields do not form a documented request: '.$e->getMessage());}
    return staff_locked(function()use($profileId,$operation,$scopeJson,$password,$ttlSeconds):string {
        $actor=provider_settings_owner($password);
        $profile=provider_settings_load($profileId,true);
        if((int)$profile['enabled']!==1) throw new RuntimeException('Enable the saved connection before approving operations.');
        $scope=provider_operations_scope($operation,json_decode($scopeJson,true));
        $id=bin2hex(random_bytes(16));
        return staff_transaction(function()use($profile,$operation,$scope,$scopeJson,$actor,$ttlSeconds,$id):string {
            query('INSERT INTO '.table('provider_operation_grants').' (id,profile_id,operation,scope_fingerprint,profile_revision,scope_params,granted_by,granted_at,expires_epoch) VALUES (?,?,?,?,?,?,?,?,?)',
                [$id,$profile['id'],$operation,$scope,(int)$profile['revision'],$scopeJson,(int)$actor['id'],gmdate('Y-m-d H:i:s'),time()+$ttlSeconds]);
            provider_settings_audit((int)$actor['id'],'operation_grant','Granted '.$operation.' on '.$profile['id']);
            return $id;
        });
    });
}

/**
 * Whether an unexpired, unrevoked grant covers this exact scope under
 * the CURRENT profile configuration. The grant is bound to the
 * profile revision approved at grant time — any settings save
 * (including a credential replacement) invalidates it, and a disabled
 * profile suspends it. The newest grant for this exact scope decides;
 * older entries are superseded history.
 */
function provider_operation_grant_active(string $profileId,string $operation,string $scopeFingerprint): bool {
    if (!provider_operations_ready()) return false;
    $row=one('SELECT expires_epoch,revoked_at,profile_revision FROM '.table('provider_operation_grants').' WHERE profile_id=? AND operation=? AND scope_fingerprint=? ORDER BY seq DESC LIMIT 1',[$profileId,$operation,$scopeFingerprint]);
    if($row===null||$row['revoked_at']!==null||(int)$row['expires_epoch']<=time()) return false;
    try{$profile=provider_settings_load($profileId);}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;return false;}
    return (int)$profile['revision']===(int)$row['profile_revision']&&(int)$profile['enabled']===1;
}

/** Recent grant records for owner review, newest first. */
function provider_operation_grants(string $profileId,int $limit=40): array {
    if (!provider_operations_ready()||$limit<1) return [];
    return all('SELECT id,operation,scope_params,granted_at,expires_epoch,revoked_at,consumed_key,consumed_at FROM '.table('provider_operation_grants').' WHERE profile_id=? ORDER BY seq DESC LIMIT '.$limit,[$profileId]);
}

/** Recent ledger outcomes for reconciliation review, newest first. */
function provider_operations_history(int $limit=40): array {
    if (!provider_operations_ready()||$limit<1) return [];
    return all('SELECT idempotency_key,operation,status,result_message,provider_code,external_id,attempts,claimed_at,resolved_epoch,reconciled_outcome,reconciled_epoch FROM '.table('provider_operations').' ORDER BY claimed_epoch DESC,id DESC LIMIT '.$limit);
}

/** Revoke a grant durably; revoking twice or an unknown grant refuses. */
function provider_operation_revoke(string $grantId,#[\SensitiveParameter] string $password): void {
    if (!preg_match('/^[a-f0-9]{32}$/D',$grantId)) throw new InvalidArgumentException('Grant unavailable.');
    staff_locked(function()use($grantId,$password):void {
        $actor=provider_settings_owner($password);
        staff_transaction(function()use($grantId,$actor):void {
            $statement=query('UPDATE '.table('provider_operation_grants').' SET revoked_at=?,revoked_by=? WHERE id=? AND revoked_at IS NULL',[gmdate('Y-m-d H:i:s'),(int)$actor['id'],$grantId]);
            if ($statement->rowCount()!==1) throw new InvalidArgumentException('Grant unavailable.');
            provider_settings_audit((int)$actor['id'],'operation_revoke','Revoked operation grant '.$grantId);
        });
    });
}

final class ProviderOperationAccessChanged extends RuntimeException {}

/**
 * Internal only. Builds the access-gated credential and performs one
 * ledger-claimed operation over the injected transport. The adapter
 * attaches the Authorization material at send time only.
 */
function provider_operations_perform(array $row,#[\SensitiveParameter] string $secret,string $address,callable $authorize,ProviderOperation $operation): OperationResult {
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($secret,$authorize):string{$authorize();return $secret;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new FixedHttpTransport($address,$authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}

/**
 * One owner-approved provider write against a stored grant. Requires:
 * owner password + current profile revision, and an unconsumed,
 * unexpired, unrevoked grant whose stored canonical scope reconstructs
 * the exact request — all verified under the staff lock before any
 * credential is opened or address resolved. The grant is consumed
 * atomically at approval time: one grant, one request, one key. The
 * gateway's durable ledger claim then guarantees at-most-once
 * execution; ambiguous outcomes classify uncertain, never silently
 * retried.
 *
 * Returns a bounded OperationResult — never throws for provider-side
 * outcomes. Resolver/perform are internal test seams, never HTTP input.
 */
function provider_operations_execute(string $profileId,int $revision,string $grantId,string $idempotencyKey,#[\SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null): OperationResult {
    if(db()->inTransaction())throw new LogicException('Provider operations cannot run inside a transaction.');
    if(!preg_match('/^[\x20-\x7e]{1,190}$/D',$idempotencyKey))throw new InvalidArgumentException('The idempotency key must be 1-190 printable characters.');
    $register=new TwentyIOperationRegister();
    $started=staff_locked(fn()=>staff_transaction(function()use($profileId,$revision,$grantId,$idempotencyKey,$password,$register):array {
        [$actor,$row]=provider_checks_authority($profileId,$revision,$password);
        if(!provider_operations_ready())throw new RuntimeException('Prepare provider operation storage first.');
        $out=static function(string $reason,?string $name,array $actor):array {
            return ['actor'=>(int)$actor['id'],'operation'=>$name,'reason'=>$reason,'params'=>[]];
        };
        $grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=? AND profile_id=?',[$grantId,$profileId]);
        $valid=$grant!==null&&$grant['revoked_at']===null&&(int)$grant['expires_epoch']>time()&&$grant['consumed_key']===null&&$grant['scope_params']!==null
            &&(int)$grant['profile_revision']===$revision; // approval dies with any configuration change
        if(!$valid)return $out('no_grant',null,$actor);
        $params=json_decode((string)$grant['scope_params'],true);
        // Stored scope must reconstruct the approved fingerprint exactly.
        if(!is_array($params)||!hash_equals((string)$grant['scope_fingerprint'],provider_operations_scope((string)$grant['operation'],$params)))
            return $out('no_grant',null,$actor);
        $spec=$register->get((string)$grant['operation']);
        if($spec===null||!$spec->shapeComplete)return $out('unbuildable',(string)$grant['operation'],$actor);
        // Atomically consume the approval: one grant, one request, one key.
        $consumed=query('UPDATE '.table('provider_operation_grants').' SET consumed_key=?,consumed_at=? WHERE id=? AND consumed_key IS NULL AND revoked_at IS NULL',
            [$idempotencyKey,gmdate('Y-m-d H:i:s'),$grant['id']]);
        if($consumed->rowCount()!==1)return $out('no_grant',(string)$grant['operation'],$actor);
        provider_settings_audit((int)$actor['id'],'operation_approved','Approved '.(string)$grant['operation'].' for one request on '.$profileId);
        return ['actor'=>(int)$actor['id'],'operation'=>(string)$grant['operation'],'reason'=>'ok','params'=>$params];
    }));
    if($started['reason']==='unbuildable') {
        provider_settings_audit($started['actor'],'operation_refused','Refused undocumented or incomplete operation '.$started['operation']);
        return OperationResult::unsupported((string)$started['operation']);
    }
    if($started['reason']!=='ok') {
        provider_settings_audit($started['actor'],'operation_refused','No active grant '.$grantId.' on profile '.$profileId);
        return OperationResult::failed($started['operation']??'provider.operation','No active owner grant covers this exact request.','no_grant');
    }
    $operation=new ProviderOperation((string)$started['operation'],$idempotencyKey,$started['params']);
    $address='';
    try{$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];}
    catch(Throwable $e){if($e instanceof PDOException)throw $e;return OperationResult::failed($operation->name,'Provider endpoint unavailable before send.','unreachable');}
    return staff_locked(function()use($profileId,$revision,$password,$operation,$address,$perform,$started,$register):OperationResult {
        $authorize=static function()use($profileId,$revision,$started):void {
            try{[$actor]=provider_checks_authority($profileId,$revision);if((int)$actor['id']!==$started['actor'])throw new RuntimeException('Changed actor.');}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;throw new ProviderOperationAccessChanged('Current provider access changed.');}
        };
        try{[$actor,$row]=provider_checks_authority($profileId,$revision,$password);if((int)$actor['id']!==$started['actor'])throw new RuntimeException('Changed actor.');}
        catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;return OperationResult::uncertain($operation->name,'access_changed');}
        $result=null;
        try{
            $authorize();$secret=provider_settings_key_value(provider_settings_unseal($row['id'],$row['environment'],$row['credentials']));
            try{$result=($perform??'provider_operations_perform')($row,$secret,$address,$authorize,$operation);}
            finally{unset($secret);}
        }catch(ProviderOperationAccessChanged){$result=null;}
        catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$result=OperationResult::failed($operation->name,'The credential could not be opened.','credential');}
        catch(Throwable $e){if($e instanceof PDOException)throw $e;$result=OperationResult::uncertain($operation->name);}
        // Access changed before or during execution: a write may have
        // landed whose ownership cannot now be proved — uncertain, not a
        // clean failure.
        if($result===null){$result=OperationResult::uncertain($operation->name,'access_changed');}
        try{$authorize();}catch(ProviderOperationAccessChanged){if($result->status!==OperationResult::UNCERTAIN)$result=OperationResult::uncertain($operation->name,'access_changed');}
        provider_settings_audit($started['actor'],'operation_result','Operation '.$operation->name.' '.$result->status);
        return $result;
    });
}
