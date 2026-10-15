<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';

use CajunWebPro\Foundation\Provider\TransportException;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyIInventory\InventoryPackage;
use CajunWebPro\Foundation\TwentyIInventory\InventorySnapshot;
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;

/**
 * Reconciliation for uncertain provider writes.
 *
 * An `uncertain` ledger row means a request may have reached the
 * provider — it is never silently retried. Reconciliation performs ONE
 * evidence-backed observation (the verified package list read) against
 * the current credential context and reports whether the approved
 * effect is visible. It never re-sends, mutates the recorded result or
 * guesses: absence of evidence is reported honestly, and operations
 * whose effect is not observable report inconclusive.
 *
 * The ledger row itself is immutable evidence; the reconcile outcome is
 * recorded beside it (reconciled_outcome/reconciled_epoch) plus an
 * audit event. Only the owner may reconcile — current password, exact
 * profile revision and enabled credentials, re-verified at the send
 * boundary and after the read.
 */

/** Outcomes an owner-facing reconcile can return. */
function provider_operations_reconcile_outcomes(): array {
    return ['observed','not_observed','inconclusive','unavailable','refused'];
}

/**
 * Internal only. Performs the evidence-backed package list read used
 * for reconciliation — the only provider read with authenticated
 * evidence today.
 */
function provider_operations_reconcile_read(array $row,#[\SensitiveParameter] string $secret,string $address,callable $authorize): InventorySnapshot {
    return provider_inventory_read($row,$secret,$address,$authorize);
}

/**
 * Maps an approved operation scope to its observation target, or null
 * when the operation has no honest observation check.
 *  - packages.create  → the approved domain must appear in a package's
 *    observed names.
 *  - packages.update with delete-id → the deleted package id must be
 *    absent from the observed list.
 *  - packages.update without delete-id → the remaining change (billing,
 *    limits, labels) is not reliably visible in the package list —
 *    inconclusive.
 */
function provider_operations_reconcile_target(string $operation,array $params,?string $acceptedId=null): ?array {
    if($operation==='packages.create') {
        if($acceptedId!==null && preg_match('/\A[1-9][0-9]{0,17}\z/',$acceptedId))return ['kind'=>'accepted_package','value'=>$acceptedId];
        $domain=$params['domain_name']??null;
        return is_string($domain)&&$domain!==''?['kind'=>'domain','value'=>strtolower($domain)]:null;
    }
    if(in_array($operation,['packages.update','packages.delete'],true)) {
        $delete=$params['delete-id']??null;
        // Old scalar approvals remain historical; no implicit conversion/replay.
        if(is_int($delete)&&$delete>0)return ['kind'=>'absent','value'=>[(string)$delete]];
        if(is_array($delete)&&array_is_list($delete)&&$delete && array_reduce($delete,fn($ok,$id)=>$ok&&is_int($id)&&$id>0,true))return ['kind'=>'absent','value'=>array_map('strval',$delete)];
        return ['kind'=>'inconclusive','value'=>''];
    }
    return null;
}

/** Evaluate an observation target against a fresh snapshot. */
function provider_operations_reconcile_evaluate(array $target,InventorySnapshot $snapshot): array {
    if($target['kind']==='accepted_package') {
        foreach($snapshot->packages as $package) {
            if($package instanceof InventoryPackage && in_array((string)$package->id,(array)$target['value'],true))
                return ['observed','The provider-issued package ID is present. Service readiness and order fulfillment still require their own verification.'];
        }
        return ['inconclusive','The acknowledged package ID is not in this observation. Partial coverage or pending setup cannot prove failure; the request is not retried.'];
    }
    if($target['kind']==='domain') {
        foreach($snapshot->packages as $package) {
            if(!$package instanceof InventoryPackage)continue;
            if(in_array($target['value'],array_map('strtolower',array_map('strval',$package->names)),true))
                return ['inconclusive','A matching domain is present, but a name match does not prove this request created the package. No resource is adopted and the request is not retried.'];
        }
        return ['not_observed','No observed package carries the approved domain. The request may not have landed — verify in the 20i panel before considering another request.'];
    }
    if($target['kind']==='absent') {
        foreach($snapshot->packages as $package) {
            if($package instanceof InventoryPackage&&in_array((string)$package->id,(array)$target['value'],true))
                return ['not_observed','The targeted package is still present; the removal may not have landed. Verify in the 20i panel before considering another request.'];
        }
        // The observed list never claims account-wide coverage, so its
        // absence cannot prove a deletion — only an authoritative target
        // read or a documented complete collection could confirm it.
        return ['inconclusive','The targeted package is absent from this observation, but partial coverage cannot prove the removal landed. Verify in the 20i panel; the request is not retried.'];
    }
    return ['inconclusive','The approved change cannot be confirmed or ruled out from the package list. Verify the result in the 20i panel; the request is not retried.'];
}

/**
 * One owner-approved reconciliation read for an uncertain operation.
 * Returns ['operation'=>?, 'outcome'=>?, 'message'=>?] — bounded,
 * secret-free. Resolver/read are internal test seams, never HTTP input.
 */
function provider_operations_reconcile(string $profileId,int $revision,string $idempotencyKey,#[\SensitiveParameter] string $password,?callable $resolver=null,?callable $read=null): array {
    if(db()->inTransaction())throw new LogicException('Provider reconciliations cannot run inside a transaction.');
    if(!preg_match('/^[\x20-\x7e]{1,190}$/D',$idempotencyKey))throw new InvalidArgumentException('The idempotency key must be 1-190 printable characters.');
    $started=staff_locked(fn()=>staff_transaction(function()use($profileId,$revision,$idempotencyKey,$password):array {
        [$actor,$row]=provider_checks_authority($profileId,$revision,$password);
        if(!provider_operations_ready())throw new RuntimeException('Prepare provider operation storage first.');
        $refuse=static function(string $message,array $actor):array {
            return ['actor'=>(int)$actor['id'],'operation'=>'provider.operation','target'=>null,'refused'=>$message];
        };
        $op=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$idempotencyKey]);
        if($op===null||!in_array($op['status'],['uncertain','accepted_pending'],true))return $refuse('Only a recorded uncertain or accepted pending operation can be reconciled.',$actor);
        // A reused key may have consumed a second grant before the ledger
        // rejected its differing scope as a conflict — only exactly one
        // same-operation grant identifies the originally claimed scope.
        // Ambiguous or mismatched history refuses rather than evaluating
        // against the wrong approved scope.
        $matching=[];
        foreach(all('SELECT operation,scope_params,profile_revision FROM '.table('provider_operation_grants').' WHERE consumed_key=? AND profile_id=?',[$idempotencyKey,$profileId]) as $consumed)
            if((string)$consumed['operation']===(string)$op['operation'])$matching[]=$consumed;
        if($matching===[])return $refuse('No approved scope record exists for this request.',$actor);
        if(count($matching)!==1)return $refuse('The approved-scope history for this request is ambiguous. Verify the result in the 20i panel.',$actor);
        $grant=$matching[0];
        $params=json_decode((string)$grant['scope_params'],true);
        if(!is_array($params))return $refuse('No approved scope record exists for this request.',$actor);
        // The observation must run against the same approved credential
        // generation — a settings change or replacement account cannot
        // reconcile another context's operation.
        if((int)$grant['profile_revision']!==$revision)return $refuse('The approved context changed since this request; verify the result in the 20i panel.',$actor);
        $target=provider_operations_reconcile_target((string)$op['operation'],$params,$op['status']==='accepted_pending'?$op['external_id']:null);
        if($target===null)return $refuse('This operation has no observation check. Verify the result in the 20i panel.',$actor);
        return ['actor'=>(int)$actor['id'],'operation'=>(string)$op['operation'],'target'=>$target,'refused'=>null];
    }));
    $finish=static function(string $outcome,string $message)use($started,$idempotencyKey):array {
        // The recorded outcome and its audit entry commit atomically —
        // a failed audit must never leave an unaudited observation.
        staff_transaction(function()use($outcome,$started,$idempotencyKey):void {
            if($outcome!=='refused') {
                query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE idempotency_key=? AND status IN (?,?)',[$outcome,time(),$idempotencyKey,'uncertain','accepted_pending']);
                provider_settings_audit($started['actor'],'operation_reconcile','Reconciled '.$idempotencyKey.' '.$started['operation'].' '.$outcome);
            } else {
                provider_settings_audit($started['actor'],'operation_reconcile_refused','Reconcile refused for '.$idempotencyKey);
            }
        });
        return ['operation'=>$started['operation'],'outcome'=>$outcome,'message'=>$message];
    };
    if($started['refused']!==null)return $finish('refused',$started['refused']);
    // An inconclusive target (e.g. a label/limit update) cannot be
    // proved or ruled out from the package list — no read is sent.
    if($started['target']['kind']==='inconclusive')return $finish('inconclusive','The approved change cannot be confirmed or ruled out from the package list. Verify the result in the 20i panel; the request is not retried.');
    $address='';
    try{$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];}
    catch(Throwable $e){if($e instanceof PDOException)throw $e;return $finish('unavailable','Provider endpoint unavailable before the observation.');}
    return staff_locked(function()use($profileId,$revision,$password,$started,$address,$read,$finish):array {
        $authorize=static function()use($profileId,$revision,$started):void {
            try{[$actor]=provider_checks_authority($profileId,$revision);if((int)$actor['id']!==$started['actor'])throw new RuntimeException('Changed actor.');}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;throw new ProviderOperationAccessChanged('Current provider access changed.');}
        };
        try{[$actor,$row]=provider_checks_authority($profileId,$revision,$password);if((int)$actor['id']!==$started['actor'])throw new RuntimeException('Changed actor.');}
        catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;return $finish('unavailable','Current provider access changed before the observation.');}
        $snapshot=null;$failure=null;
        try{
            $authorize();
            $secret=provider_settings_key_value(provider_settings_unseal($row['id'],$row['environment'],$row['credentials']));
            try{$snapshot=($read??'provider_operations_reconcile_read')($row,$secret,$address,$authorize);}
            finally{unset($secret);}
        }catch(ProviderOperationAccessChanged|ProviderInventoryAccessChanged){$failure='access_changed';}
        catch(TransportException){$failure='unavailable';}
        catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$failure='credential';}
        catch(Throwable $e){if($e instanceof PDOException)throw $e;$failure='unavailable';}
        if($failure!==null)return $finish('unavailable','The observation could not be completed ('.$failure.'). The operation stays uncertain.');
        try{$authorize();}catch(ProviderOperationAccessChanged){return $finish('unavailable','Current provider access changed during the observation. The operation stays uncertain.');}
        if(!$snapshot instanceof InventorySnapshot)return $finish('unavailable','The observation read returned no usable data. The operation stays uncertain.');
        if(!provider_inventory_observed($snapshot->state))return $finish('unavailable','The provider returned no usable observation ('.$snapshot->state.'). The operation stays uncertain.');
        [$outcome,$message]=provider_operations_reconcile_evaluate($started['target'],$snapshot);
        return $finish($outcome,$message);
    });
}
