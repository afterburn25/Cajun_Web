<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/php_controls_reads.php';
require_once CWP_CODE_ROOT.'/app/providers/operations.php';

use CajunWebPro\Foundation\MailTransport\EndpointPolicy;
use CajunWebPro\Foundation\Provider\ProviderOperation;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyI\Operations\PhpVersionChange;

/** Only real owner/primary-client sessions can supply this internal context. */
function provider_php_controls_change_context(string $linkId,?int $clientId,#[SensitiveParameter] ?string $password=null): array
{
    provider_php_controls_guard(true);
    unset($GLOBALS['cwp_settings']);
    if ($clientId===null) {
        if ($password!==null) provider_settings_owner($password);
        $context=provider_php_controls_owner_context($linkId);
    } else {
        if ($password!==null) provider_php_controls_client_password($clientId,$password);
        $context=provider_php_controls_client_context($clientId,$linkId);
    }
    if (!provider_php_controls_lifecycle_ready() || !provider_operations_ready()) {
        throw new RuntimeException('Prepare the original PHP control and operation history first.');
    }
    return $context;
}

/** Always use the latest intents, including an unfinished or failed newer read. */
function provider_php_controls_change_review(array $context,string $currentId,string $optionsId,string $desired): array
{
    provider_php_controls_guard(true);
    $link=$context['binding']->data['link_id'];
    $current=provider_php_controls_lifecycle_latest($link,'current');
    $options=provider_php_controls_lifecycle_latest($link,'available');
    if (($current['read']['document']['id']??null)!==$currentId
        || ($options['read']['document']['id']??null)!==$optionsId
        || !isset($current['result'],$options['result'])) {
        throw new RuntimeException('Refresh and review both latest PHP observations.');
    }
    $read=$current['read']['document'];$result=$current['result']['document'];
    $available=$options['read']['document'];$availableResult=$options['result']['document'];
    provider_php_controls_lifecycle_fresh($read,$result,time());
    provider_php_controls_lifecycle_fresh($available,$availableResult,time());
    $review=provider_php_controls_lifecycle_review($context['document'],$read,$result,$available,$availableResult,$desired);
    return ['review'=>$review,'current'=>$read,'current_result'=>$result,'available'=>$available,'available_result'=>$availableResult,
        'current_audit_id'=>$current['read']['audit_id'],'options_audit_id'=>$options['read']['audit_id']];
}

/** A new approval always needs new observations after the previous approval. */
function provider_php_controls_change_admission(array $context,array $review): void
{
    provider_php_controls_guard(true);
    $b=$context['binding']->data;
    if (provider_php_controls_lifecycle_pending($b['profile_id'],$b['package_id'])!==null) {
        throw new RuntimeException('A previous PHP change is unresolved. Check its result before a new approval.');
    }
    $previous=one('SELECT id FROM '.table('provider_php_changes').' WHERE profile_id=? AND package_id=? ORDER BY seq DESC LIMIT 1',[$b['profile_id'],$b['package_id']]);
    if ($previous) {
        $old=provider_php_controls_lifecycle_get('request',$previous['id'])['document'];
        $oldCurrent=provider_php_controls_lifecycle_get('read',$old['review']['current_read_id'])['document'];
        $oldOptions=provider_php_controls_lifecycle_get('read',$old['review']['options_read_id'])['document'];
        $floor=max($oldCurrent['seq'],$oldOptions['seq']);
        $terminal=provider_php_controls_lifecycle_get('terminal',$old['id']);
        $release=($terminal['document']['phase']??null)==='not_sent'?$terminal['audit_id']:null;
        if ($release===null) {
            $check=one('SELECT id FROM '.table('provider_php_change_checks')." WHERE request_id=? AND BINARY disposition='observed'",[$old['id']]);
            $release=$check?provider_php_controls_lifecycle_get('check',$check['id'])['audit_id']:null;
        }
        if ($review['current']['seq']<=$floor || $review['available']['seq']<=$floor
            || $review['current']['started_epoch']<$old['approved_epoch']
            || $review['available']['started_epoch']<$old['approved_epoch']
            || $release===null || $review['current_audit_id']<=$release || $review['options_audit_id']<=$release) {
            throw new RuntimeException('Save new PHP observations before approving another change.');
        }
    }
}

/** Closed safe form projection; private bindings and authorization witnesses stay internal. */
function provider_php_controls_review_for(?int $clientId,string $linkId,string $currentId,string $optionsId,string $desired): array
{
    if (db()->inTransaction()) throw new LogicException('PHP review cannot inherit a transaction.');
    foreach ([$linkId,$currentId,$optionsId] as $id) provider_php_controls_lifecycle_id($id);
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$linkId,$currentId,$optionsId,$desired):array {
        $context=provider_php_controls_change_context($linkId,$clientId);
        $r=provider_php_controls_change_review($context,$currentId,$optionsId,$desired);
        provider_php_controls_change_admission($context,$r);
        return ['review_sha256'=>provider_php_controls_lifecycle_sha($r['review']),
            'current_read_id'=>$currentId,'options_read_id'=>$optionsId,
            'before_value'=>$r['review']['before_value'],'desired_value'=>$r['review']['selected']['value'],
            'label'=>$r['review']['selected']['label'],'handler'=>$r['review']['selected']['handler'],
            'current_observed_epoch'=>$r['current_result']['finished_epoch'],
            'options_observed_epoch'=>$r['available_result']['finished_epoch']];
    }));
}
function provider_php_controls_review_owner(string $linkId,string $currentId,string $optionsId,string $desired): array
{
    return provider_php_controls_review_for(null,$linkId,$currentId,$optionsId,$desired);
}
function provider_php_controls_review_client(int $clientId,string $linkId,string $currentId,string $optionsId,string $desired): array
{
    return provider_php_controls_review_for($clientId,$linkId,$currentId,$optionsId,$desired);
}

/** A client can only inspect/check its own still-authorized original request. */
function provider_php_controls_change_access(array $request,?int $clientId,#[SensitiveParameter] ?string $password=null): array
{
    $original=$request['review']['context'];
    $context=provider_php_controls_change_context($original['binding']['link_id'],$clientId,$password);
    $later=$context['document'];
    if (!provider_php_controls_lifecycle_same_resource($original['binding'],$later['binding'])
        || ($clientId!==null && ($original['actor']!==$later['actor']
            || $original['permission_id']!==$later['permission_id']
            || $original['permission_sha256']!==$later['permission_sha256']))) {
        throw new RuntimeException('The original PHP request authorization or hosting assignment changed.');
    }
    return $context;
}

/** Recheck this exact uncompleted request before DNS, credential access and each send boundary. */
function provider_php_controls_change_authorize(array $expected): array
{
    if (db()->inTransaction()) throw new LogicException('PHP dispatch cannot inherit a transaction.');
    provider_php_controls_guard();
    $context=staff_transaction(function()use($expected):array {
        if (!provider_php_controls_lifecycle_ready() || !provider_operations_ready()) throw new RuntimeException('Restore PHP dispatch history first.');
        $request=provider_php_controls_lifecycle_get('request',$expected['id']);
        if (($request['document']??null)!==$expected || $expected['expires_epoch']<=time()
            || provider_php_controls_lifecycle_get('terminal',$expected['id'])!==null) {
            throw new RuntimeException('This PHP change is no longer authorized to send.');
        }
        $d=$expected['review']['context'];
        $context=['document'=>$d,'sha256'=>provider_php_controls_lifecycle_sha($d),
            'binding'=>new \CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding($d['binding'])];
        $context=provider_php_controls_reauthorize($context);
        $r=provider_php_controls_change_review($context,$expected['review']['current_read_id'],$expected['review']['options_read_id'],$expected['review']['selected']['value']);
        $b=$context['binding']->data;
        $pending=provider_php_controls_lifecycle_pending($b['profile_id'],$b['package_id']);
        if ($r['review']!==$expected['review'] || ($pending['request']['document']['id']??null)!==$expected['id']) {
            throw new RuntimeException('The PHP review or pending request changed.');
        }
        return $context;
    });
    return provider_php_controls_connection($context);
}

/** The storage validator authenticates the original ledger before this local snapshot is used. */
function provider_php_controls_change_ledger(array $request,int $now,bool $lock=false): ?array
{
    provider_php_controls_guard(true);
    if ($lock) one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=? FOR UPDATE',[$request['idempotency_key']]);
    return provider_php_controls_lifecycle_ledger(provider_php_controls_lifecycle_ledger_snapshot($request['id']),$request,$now);
}

/** Recording the attempted phase is independent of later authorization revocation. */
function provider_php_controls_change_end(array $expected,bool $entered): void
{
    provider_php_controls_guard(true);
    $request=provider_php_controls_lifecycle_get('request',$expected['id']);
    if (($request['document']??null)!==$expected) throw new RuntimeException('Restore the original PHP request.');
    $now=max(time(),$expected['approved_epoch']);$ledger=provider_php_controls_change_ledger($expected,$now,true);
    // A surviving claim is never proof that nothing was sent, even if entry failed locally.
    $phase=$entered||$ledger!==null?'attempted':'not_sent';
    provider_php_controls_lifecycle_insert('terminal',['format'=>1,'request_id'=>$expected['id'],
        'request_sha256'=>$request['sha256'],'request_actor'=>$expected['review']['context']['actor'],
        'recorded_by'=>$expected['review']['context']['actor'],'phase'=>$phase,'recorded_epoch'=>$now,
        'ledger'=>$ledger,'ledger_sha256'=>hash('sha256',json_encode($ledger,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),
        'recovered_from_ledger'=>false]);
}

/** Internal receipt projection, usable after an admitted operation without disclosing provider details. */
function provider_php_controls_summary_locked(string $requestId): array
{
    provider_php_controls_guard(true);
    if (!provider_php_controls_lifecycle_ready()) throw new RuntimeException('Restore PHP control history.');
    $request=provider_php_controls_lifecycle_get('request',$requestId)['document'];
    $terminal=provider_php_controls_lifecycle_get('terminal',$requestId)['document']??null;
    $row=one('SELECT c.id FROM '.table('provider_php_change_checks').' c JOIN '.table('provider_php_control_reads').' r ON r.id=c.read_id WHERE c.request_id=? ORDER BY r.seq DESC LIMIT 1',[$requestId]);
    $check=$row?provider_php_controls_lifecycle_get('check',$row['id'])['document']:null;
    return ['id'=>$requestId,'link_id'=>$request['review']['context']['binding']['link_id'],
        'before_value'=>$request['review']['before_value'],'desired_value'=>$request['review']['selected']['value'],
        'approved_epoch'=>$request['approved_epoch'],'phase'=>$terminal['phase']??'pending',
        'readback'=>$check['disposition']??'pending','observed_value'=>$check['value']??null,
        'readback_epoch'=>$check['recorded_epoch']??null,'recovered_from_ledger'=>$terminal['recovered_from_ledger']??false];
}

/** One owning mutex spans admission, network entry and truthful immutable completion. */
function provider_php_controls_change_for(?int $clientId,string $requestId,string $linkId,string $currentId,string $optionsId,string $desired,string $reviewHash,bool $confirmed,#[SensitiveParameter] string $password,?callable $resolver,?callable $perform): array
{
    if (db()->inTransaction()) throw new LogicException('PHP changes cannot inherit a transaction.');
    foreach ([$requestId,$linkId,$currentId,$optionsId] as $id) provider_php_controls_lifecycle_id($id);
    provider_php_controls_lifecycle_hash($reviewHash);
    if (!$confirmed) throw new InvalidArgumentException('Confirm this exact PHP change first.');
    return staff_locked(function()use($clientId,$requestId,$linkId,$currentId,$optionsId,$desired,$reviewHash,$password,$resolver,$perform):array {
        $request=staff_transaction(function()use($clientId,$requestId,$linkId,$currentId,$optionsId,$desired,$reviewHash,$password):?array {
            $context=provider_php_controls_change_context($linkId,$clientId,$password);
            $old=provider_php_controls_lifecycle_get('request',$requestId);
            if ($old!==null) {
                $d=$old['document'];$r=$d['review'];
                provider_php_controls_change_access($d,$clientId);
                if ($r['context']['actor']!==$context['document']['actor']
                    || $r['context']['binding']['link_id']!==$linkId || $r['current_read_id']!==$currentId
                    || $r['options_read_id']!==$optionsId || $r['selected']['value']!==$desired
                    || !hash_equals($d['review_sha256'],$reviewHash)) {
                    throw new RuntimeException('This PHP request identifier was already used.');
                }
                return null; // Replaying any original request never reaches DNS or a gateway.
            }
            $r=provider_php_controls_change_review($context,$currentId,$optionsId,$desired);
            provider_php_controls_change_admission($context,$r);
            if (!hash_equals(provider_php_controls_lifecycle_sha($r['review']),$reviewHash)) {
                throw new RuntimeException('Review the exact PHP choices and assignment again.');
            }
            $now=time();
            $d=['format'=>1,'id'=>$requestId,'seq'=>provider_php_controls_lifecycle_next('request'),
                'review'=>$r['review'],'review_sha256'=>$reviewHash,'confirmed'=>true,
                'approved_epoch'=>$now,'expires_epoch'=>$now+900,'idempotency_key'=>'phpversion:'.$requestId];
            return provider_php_controls_lifecycle_insert('request',$d)['document'];
        });
        if ($request===null) return staff_transaction(fn()=>provider_php_controls_summary_locked($requestId));
        $entered=false;$unexpected=null;
        try {
            $authorize=static fn():array=>provider_php_controls_change_authorize($request);
            $authorize();
            $address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];
            $profile=$authorize();
            $secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
            if (!hash_equals(provider_inventory_fingerprint($secret),$request['review']['context']['binding']['key_fingerprint'])) {
                throw new RuntimeException('The approved PHP connection changed.');
            }
            $authorize();
            $operation=new ProviderOperation(PhpVersionChange::OPERATION,$request['idempotency_key'],$request['review']['params']);
            $entered=true;
            ($perform??'provider_operations_perform')($profile,$secret,$address,$authorize,$operation);
        } catch (Throwable $e) {
            if ($e instanceof PDOException) throw $e;
            // No exception text, status code or acknowledgment establishes the remote result.
            if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException && !$e instanceof JsonException) $unexpected=$e;
        } finally { unset($secret,$profile); }
        staff_transaction(fn()=>provider_php_controls_change_end($request,$entered));
        if ($unexpected!==null) throw $unexpected;
        return staff_transaction(fn()=>provider_php_controls_summary_locked($requestId));
    });
}
function provider_php_controls_change_owner(string $requestId,string $linkId,string $currentId,string $optionsId,string $desired,string $reviewHash,bool $confirmed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null): array
{
    return provider_php_controls_change_for(null,$requestId,$linkId,$currentId,$optionsId,$desired,$reviewHash,$confirmed,$password,$resolver,$perform);
}
function provider_php_controls_change_client(int $clientId,string $requestId,string $linkId,string $currentId,string $optionsId,string $desired,string $reviewHash,bool $confirmed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null): array
{
    return provider_php_controls_change_for($clientId,$requestId,$linkId,$currentId,$optionsId,$desired,$reviewHash,$confirmed,$password,$resolver,$perform);
}

/** A new scalar GET is the only verification path; it never resends the change. */
function provider_php_controls_verify_for(?int $clientId,string $requestId,#[SensitiveParameter] string $password,?callable $resolver,?callable $transport): array
{
    if (db()->inTransaction()) throw new LogicException('PHP verification cannot inherit a transaction.');
    provider_php_controls_lifecycle_id($requestId);
    return staff_locked(function()use($clientId,$requestId,$password,$resolver,$transport):array {
        $plan=staff_transaction(function()use($clientId,$requestId,$password):?array {
            if (!provider_php_controls_lifecycle_ready()) throw new RuntimeException('Restore PHP control history.');
            $request=provider_php_controls_lifecycle_get('request',$requestId);
            if ($request===null) throw new RuntimeException('This PHP request is unavailable.');
            $context=provider_php_controls_change_access($request['document'],$clientId,$password);
            $terminal=provider_php_controls_lifecycle_get('terminal',$requestId);
            if ($terminal===null || $terminal['document']['phase']!=='attempted'
                || $terminal['document']['ledger']===null || $terminal['document']['ledger']['status']==='claimed') {
                throw new RuntimeException('The original PHP dispatch needs a finished operation record before verification.');
            }
            if (one('SELECT id FROM '.table('provider_php_change_checks')." WHERE request_id=? AND BINARY disposition='observed'",[$requestId])) return null;
            return ['context'=>$context,'request'=>$request,'terminal'=>$terminal,
                'floor'=>provider_php_controls_lifecycle_next('read')-1];
        });
        if ($plan===null) return staff_transaction(fn()=>provider_php_controls_summary_locked($requestId));
        $linkId=$plan['context']['binding']->data['link_id'];
        $receipt=$clientId===null
            ?provider_php_controls_read_owner($linkId,'current',$password,$resolver,$transport)
            :provider_php_controls_read_client($clientId,$linkId,'current',$resolver,$transport);
        return staff_transaction(function()use($plan,$receipt,$requestId,$clientId):array {
            provider_php_controls_reauthorize($plan['context']);
            $request=provider_php_controls_lifecycle_get('request',$requestId);
            $terminal=provider_php_controls_lifecycle_get('terminal',$requestId);
            if ($request!==$plan['request'] || $terminal!==$plan['terminal']) throw new RuntimeException('The original PHP verification plan changed.');
            provider_php_controls_change_access($request['document'],$clientId);
            $read=provider_php_controls_lifecycle_get('read',$receipt['id']);
            $result=provider_php_controls_lifecycle_get('result',$receipt['id']);
            if (($read['document']['context']??null)!==$plan['context']['document'] || $result===null) throw new RuntimeException('The exact later PHP read is unavailable.');
            $value=$result['document']['state']==='complete'?$result['document']['value']:null;
            $disposition=$value===null?'unavailable':($value===$request['document']['review']['selected']['value']?'observed':'different');
            provider_php_controls_lifecycle_insert('check',['format'=>1,'id'=>bin2hex(random_bytes(16)),
                'request_id'=>$requestId,'request_sha256'=>$request['sha256'],'terminal_sha256'=>$terminal['sha256'],
                'read_id'=>$receipt['id'],'read_sha256'=>$read['sha256'],'result_sha256'=>$result['sha256'],
                'checked_by'=>$plan['context']['document']['actor'],'floor'=>$plan['floor'],
                'recorded_epoch'=>max(time(),$result['document']['finished_epoch']),
                'disposition'=>$disposition,'value'=>$value]);
            return provider_php_controls_summary_locked($requestId);
        });
    });
}
function provider_php_controls_verify_owner(string $requestId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null): array
{
    return provider_php_controls_verify_for(null,$requestId,$password,$resolver,$transport);
}
function provider_php_controls_verify_client(int $clientId,string $requestId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null): array
{
    return provider_php_controls_verify_for($clientId,$requestId,$password,$resolver,$transport);
}

/** Review of original local evidence only; missing/claimed rows never permit reconstruction. */
function provider_php_controls_recovery_review_owner(string $requestId): array
{
    if (db()->inTransaction()) throw new LogicException('PHP recovery review cannot inherit a transaction.');
    provider_php_controls_lifecycle_id($requestId);
    return staff_locked(fn()=>staff_transaction(function()use($requestId):array {
        provider_settings_manager();
        if (!provider_php_controls_lifecycle_ready()) throw new RuntimeException('Restore PHP control history.');
        $request=provider_php_controls_lifecycle_get('request',$requestId);
        if ($request===null) throw new RuntimeException('This PHP request is unavailable.');
        provider_php_controls_change_access($request['document'],null);
        $ledger=provider_php_controls_change_ledger($request['document'],time());
        $ready=provider_php_controls_lifecycle_get('terminal',$requestId)===null && $ledger!==null && $ledger['status']!=='claimed';
        return ['request_id'=>$requestId,'ledger_sha256'=>$ready?provider_php_controls_lifecycle_sha($ledger):null,'ready'=>$ready];
    }));
}
function provider_php_controls_recover_owner(string $requestId,string $reviewedLedgerHash,#[SensitiveParameter] string $password): array
{
    if (db()->inTransaction()) throw new LogicException('PHP recovery cannot inherit a transaction.');
    provider_php_controls_lifecycle_id($requestId);provider_php_controls_lifecycle_hash($reviewedLedgerHash);
    return staff_locked(fn()=>staff_transaction(function()use($requestId,$reviewedLedgerHash,$password):array {
        provider_settings_owner($password);
        if (!provider_php_controls_lifecycle_ready()) throw new RuntimeException('Restore PHP control history.');
        $request=provider_php_controls_lifecycle_get('request',$requestId);
        if ($request===null) throw new RuntimeException('This PHP request is unavailable.');
        $context=provider_php_controls_change_access($request['document'],null);
        $now=time();$ledger=provider_php_controls_change_ledger($request['document'],$now,true);
        if ($ledger===null || $ledger['status']==='claimed' || provider_php_controls_lifecycle_get('terminal',$requestId)!==null
            || !hash_equals(provider_php_controls_lifecycle_sha($ledger),$reviewedLedgerHash)) {
            throw new RuntimeException('Review the original finished PHP operation before recovery.');
        }
        provider_php_controls_lifecycle_insert('terminal',['format'=>1,'request_id'=>$requestId,
            'request_sha256'=>$request['sha256'],'request_actor'=>$request['document']['review']['context']['actor'],
            'recorded_by'=>$context['document']['actor'],'phase'=>'attempted','recorded_epoch'=>$now,
            'ledger'=>$ledger,'ledger_sha256'=>$reviewedLedgerHash,'recovered_from_ledger'=>true]);
        return provider_php_controls_summary_locked($requestId);
    }));
}
