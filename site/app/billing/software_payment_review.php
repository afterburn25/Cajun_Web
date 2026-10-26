<?php
/** Reversal/lifecycle reconciliation. Verified gateway facts move records to review; they never auto-revoke paid access. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/software_charge_guard.php';

function software_payment_reversal_review(array $details,string $eventTransactionId,string $eventType):string{
    if(!software_commerce_ready()||!ledger_ready())throw new RuntimeException('Payments not prepared.');
    $gateway=software_anet_settings();
    if($gateway['mode']!=='production')return 'sandbox_reversal';
    $reversal=software_anet_reversal_details($details,$eventTransactionId,$eventType);
    if($reversal['kind']==='refund' && ($reversal['amount_cents']===null || $reversal['amount_cents']<1)){
        throw new RuntimeException('Refund amount is not trustworthy.');
    }
    if(db()->inTransaction())throw new LogicException('Payment reversal review must own the transaction.');
    db()->beginTransaction();
    try{
        $original=$reversal['original_transaction_id'];
        $charge=one('SELECT * FROM '.table('software_charges').' WHERE transaction_id=? FOR UPDATE',[$original]);
        if($charge){
            if($charge['status']==='reversal_review'){db()->commit();return 'replay';}
            query('UPDATE '.table('software_charges').' SET status=?,updated_at=? WHERE id=?',
                ['reversal_review',gmdate('Y-m-d H:i:s'),$charge['id']]);
            db()->commit();
            return 'one_time_reversal_review';
        }
        $receipt=one('SELECT * FROM '.table('software_subscription_receipts').' WHERE gateway_transaction_id=? FOR UPDATE',[$original]);
        if($receipt){
            $sub=one('SELECT * FROM '.table('software_subscriptions').' WHERE invoice_id=? FOR UPDATE',
                [(int)$receipt['subscription_invoice_id']]);
            if(!$sub)throw new RuntimeException('Recurring reversal has no subscription record.');
            if($sub['status']==='review'){db()->commit();return 'replay';}
            query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['review',$sub['id']]);
            db()->commit();
            return 'subscription_reversal_review';
        }
        db()->commit();
        return 'unknown_original';
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        throw $e;
    }
}

function software_subscription_lifecycle_review(string $eventType,string $subscriptionId):string{
    if(!preg_match('/^[0-9]{1,25}$/D',$subscriptionId))throw new InvalidArgumentException('Invalid subscription identifier.');
    $map=[
        'net.authorize.customer.subscription.failed'=>'review',
        'net.authorize.customer.subscription.suspended'=>'review',
        'net.authorize.customer.subscription.terminated'=>'review',
        'net.authorize.customer.subscription.cancelled'=>'cancelled',
        'net.authorize.customer.subscription.expired'=>'cancelled',
    ];
    if(!isset($map[$eventType]))return 'ignored';
    $sub=one('SELECT * FROM '.table('software_subscriptions').' WHERE gateway_subscription_id=?',[$subscriptionId]);
    if(!$sub)return 'unknown_subscription';
    $target=$map[$eventType];
    if($sub['status']===$target)return 'replay';
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',[$target,$sub['id']]);
    // Paid-through entitlements are deliberately not revoked here.
    return $target==='review'?'subscription_review':'subscription_cancelled';
}
