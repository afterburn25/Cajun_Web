<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Guard for subscription plans in the shared customer portal. */
function software_subscription_term(string $term): string {
    if (!in_array($term, ['month', 'year'], true)) {
        throw new InvalidArgumentException('Subscription term must be monthly or yearly.');
    }
    return $term;
}



/**
 * Classify a verified recurring charge without erasing cancellation/review state.
 * A captured gateway payment must still be credited even if its webhook races
 * with cancellation or arrives after a sticky review transition.
 */
function software_subscription_settlement_phase(string $status,bool $hasPriorReceipt):?array {
    if(!in_array($status,['pending_first_payment','active','payment_failed','billing_suspended','cancelling','cancelled','review'],true))return null;
    if($status==='pending_first_payment'&&$hasPriorReceipt){
        throw new RuntimeException('Pending subscription unexpectedly has paid receipt history.');
    }
    if($status==='active'&&!$hasPriorReceipt){
        throw new RuntimeException('Active subscription is missing its first paid receipt.');
    }
    return [
        'first'=>!$hasPriorReceipt,
        'preserve_status'=>in_array($status,['cancelling','cancelled','review'],true),
    ];
}

/**
 * Reconcile an already-finalized local ARB state against an authoritative
 * subscription-profile readback. Never silently reactivate a locally
 * cancelled subscription: an active-gateway/cancelled-local mismatch needs review.
 */
function software_subscription_finalized_reconcile_state(string $local,string $gateway,bool $sandbox):string {
    if(!in_array($local,['pending_first_payment','sandbox_test','active','payment_failed','billing_suspended','cancelled'],true)
        ||!in_array($gateway,['active','expired','suspended','canceled','terminated'],true)){
        throw new InvalidArgumentException('Invalid finalized subscription reconciliation state.');
    }
    $compatible=match($gateway){
        'active'=>$sandbox
            ? $local==='sandbox_test'
            : in_array($local,['pending_first_payment','active','payment_failed','billing_suspended'],true),
        'expired','canceled'=>$local==='cancelled',
        'suspended'=>$local==='billing_suspended',
        'terminated'=>false,
    };
    if($compatible)return 'same';
    if(in_array($gateway,['expired','canceled'],true))return 'cancelled';
    if($gateway==='suspended'&&in_array($local,['pending_first_payment','active','payment_failed'],true)){
        return 'billing_suspended';
    }
    return 'review';
}

/** Decide the durable local subscription state after an exactly verified payment. */
function software_subscription_post_payment_status(string $current,bool $first,bool $grantReview):string{
    if($grantReview)return 'review';
    if($first&&$current==='pending_first_payment')return 'active';
    if(in_array($current,['payment_failed','billing_suspended'],true))return 'active';
    return $current;
}

/** Plan how a verified renewal treats one existing software grant. */
function software_subscription_renewal_grant_plan(array $grant):array {
    if(($grant['license_type']??null)!=='temporary'
        ||(int)($grant['is_master']??0)===1
        ||!is_string($grant['expires_at']??null)
        ||$grant['expires_at']===''){
        throw new RuntimeException('Renewal grant metadata is inconsistent.');
    }
    return [
        'extend'=>true, // verified paid time is never discarded because access is suspended
        'review'=>(int)($grant['is_active']??0)!==1,
    ];
}

/** Extend a paid-through UTC timestamp by calendar months, clamping month-end safely. */
function software_subscription_extend_expiry(string $expiresAt,int $months,DateTimeImmutable $now):string {
    if(!in_array($months,[1,12],true))throw new InvalidArgumentException('Subscription interval must be one or twelve months.');
    $zone=new DateTimeZone('UTC');
    $current=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$expiresAt,$zone);
    $errors=DateTimeImmutable::getLastErrors();
    if($current===false||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0))
        ||$current->format('Y-m-d H:i:s')!==$expiresAt){
        throw new InvalidArgumentException('Invalid subscription expiry timestamp.');
    }
    $base=$current>$now?$current:$now->setTimezone($zone);
    $year=(int)$base->format('Y');$month=(int)$base->format('n');$day=(int)$base->format('j');
    $absolute=$year*12+($month-1)+$months;
    $targetYear=intdiv($absolute,12);$targetMonth=($absolute%12)+1;
    $first=$base->setDate($targetYear,$targetMonth,1);
    $targetDay=min($day,(int)$first->format('t'));
    return $first->setDate($targetYear,$targetMonth,$targetDay)->format('Y-m-d H:i:s');
}
