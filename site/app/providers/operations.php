<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/settings.php';
require_once CWP_CODE_ROOT.'/database/migrations/provider_operations_migrate.php';
require_once CWP_CODE_ROOT.'/includes/foundation/provider/LedgerClaim.php';
require_once CWP_CODE_ROOT.'/includes/foundation/provider/OperationResult.php';
require_once CWP_CODE_ROOT.'/includes/foundation/provider/OperationLedger.php';

use CajunWebPro\Foundation\Provider\{LedgerClaim,OperationLedger,OperationResult};

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

/** Owner-approved grant for one exact operation+scope on a profile. */
function provider_operation_grant(string $profileId,string $operation,array $scopeParams,#[\SensitiveParameter] string $password,int $ttlSeconds=3600): string {
    if (!preg_match('/^[a-z0-9]+(\.[a-z0-9]+)*$/',$operation)) throw new InvalidArgumentException('Operation names use dotted lowercase form.');
    if ($ttlSeconds<60||$ttlSeconds>86400) throw new InvalidArgumentException('Grant duration must be between 60 seconds and 24 hours.');
    return staff_locked(function()use($profileId,$operation,$scopeParams,$password,$ttlSeconds):string {
        $actor=provider_settings_owner($password);
        $profile=provider_settings_load($profileId,true);
        if((int)$profile['enabled']!==1) throw new RuntimeException('Enable the saved connection before approving operations.');
        $scope=provider_operations_scope($operation,$scopeParams);
        $id=bin2hex(random_bytes(16));
        return staff_transaction(function()use($profile,$operation,$scope,$actor,$ttlSeconds,$id):string {
            query('INSERT INTO '.table('provider_operation_grants').' (id,profile_id,operation,scope_fingerprint,profile_revision,granted_by,granted_at,expires_epoch) VALUES (?,?,?,?,?,?,?,?)',
                [$id,$profile['id'],$operation,$scope,(int)$profile['revision'],(int)$actor['id'],gmdate('Y-m-d H:i:s'),time()+$ttlSeconds]);
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
