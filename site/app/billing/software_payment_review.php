<?php
/** Reversal/lifecycle reconciliation. Verified gateway facts move records to review; they never auto-revoke paid access. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/software_charge_guard.php';

function software_payment_reversal_apply(array $reversal):string{
    $event=(string)($reversal['transaction_id']??'');
    $original=(string)($reversal['original_transaction_id']??'');
    $kind=(string)($reversal['kind']??'');
    $amount=$reversal['amount_cents']??null;
    if(!in_array($kind,['refund','void'],true)
        ||!preg_match('/^[0-9]{5,30}$/D',$event)
        ||!preg_match('/^[0-9]{5,30}$/D',$original)
        ||($kind==='refund'&&(!is_int($amount)||$amount<1))
        ||($kind==='void'&&$amount!==null)){
        throw new InvalidArgumentException('Invalid verified reversal record.');
    }
    if(db()->inTransaction())throw new LogicException('Payment reversal review must own the transaction.');
    db()->beginTransaction();
    try{
        if(one('SELECT id FROM '.table('software_payment_reversals').' WHERE gateway_transaction_id=? FOR UPDATE',[$event])){
            db()->commit();return 'replay';
        }
        $charge=one('SELECT * FROM '.table('software_charges').' WHERE transaction_id=? FOR UPDATE',[$original]);
        if($charge){
            if(one('SELECT id FROM '.table('software_payment_reversals').' WHERE gateway_transaction_id=?',[$event])){
                db()->commit();return 'replay';
            }
            query('INSERT INTO '.table('software_payment_reversals').' (gateway_transaction_id,original_transaction_id,kind,amount_cents,invoice_id,subscription_invoice_id,created_at) VALUES (?,?,?,?,?,?,?)',
                [$event,$original,$kind,$amount,$charge['invoice_id'],null,gmdate('Y-m-d H:i:s')]);
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
            if(one('SELECT id FROM '.table('software_payment_reversals').' WHERE gateway_transaction_id=?',[$event])){
                db()->commit();return 'replay';
            }
            query('INSERT INTO '.table('software_payment_reversals').' (gateway_transaction_id,original_transaction_id,kind,amount_cents,invoice_id,subscription_invoice_id,created_at) VALUES (?,?,?,?,?,?,?)',
                [$event,$original,$kind,$amount,$receipt['invoice_id'],$receipt['subscription_invoice_id'],gmdate('Y-m-d H:i:s')]);
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

function software_payment_reversal_review(array $details,string $eventTransactionId,string $eventType):string{
    if(!software_commerce_ready()||!ledger_ready())throw new RuntimeException('Payments not prepared.');
    $gateway=software_anet_settings();
    if($gateway['mode']!=='production')return 'sandbox_reversal';
    $reversal=software_anet_reversal_details($details,$eventTransactionId,$eventType);
    if($reversal['kind']==='refund' && ($reversal['amount_cents']===null || $reversal['amount_cents']<1)){
        throw new RuntimeException('Refund amount is not trustworthy.');
    }
    return software_payment_reversal_apply($reversal);
}

function software_payment_dispute_record(string $originalTransactionId,string $caseReference,string $evidence,int $actor):string{
    $caseReference=trim($caseReference);$evidence=trim($evidence);
    if(!preg_match('/^[0-9]{5,30}$/D',$originalTransactionId)
        ||strlen($caseReference)<5||strlen($caseReference)>100
        ||strlen($evidence)<10||strlen($evidence)>500||$actor<1){
        throw new InvalidArgumentException('Record the verified original transaction, dispute case reference and evidence.');
    }
    if(db()->inTransaction())throw new LogicException('Payment dispute review must own the transaction.');
    db()->beginTransaction();
    try{
        if(one('SELECT id FROM '.table('software_payment_disputes').' WHERE case_reference=? FOR UPDATE',[$caseReference])){
            db()->commit();return 'replay';
        }
        $charge=one('SELECT * FROM '.table('software_charges').' WHERE transaction_id=? FOR UPDATE',[$originalTransactionId]);
        if($charge){
            query('INSERT INTO '.table('software_payment_disputes').' (case_reference,original_transaction_id,evidence,invoice_id,subscription_invoice_id,recorded_by,created_at) VALUES (?,?,?,?,?,?,?)',
                [$caseReference,$originalTransactionId,$evidence,$charge['invoice_id'],null,$actor,gmdate('Y-m-d H:i:s')]);
            query('UPDATE '.table('software_charges').' SET status=?,updated_at=? WHERE id=?',
                ['dispute_review',gmdate('Y-m-d H:i:s'),$charge['id']]);
            license_audit($actor,'payment.dispute','Merchant dispute '.$caseReference.' recorded for transaction '.$originalTransactionId);
            db()->commit();
            return 'one_time_dispute_review';
        }
        $receipt=one('SELECT * FROM '.table('software_subscription_receipts').' WHERE gateway_transaction_id=? FOR UPDATE',[$originalTransactionId]);
        if($receipt){
            $sub=one('SELECT * FROM '.table('software_subscriptions').' WHERE invoice_id=? FOR UPDATE',
                [(int)$receipt['subscription_invoice_id']]);
            if(!$sub)throw new RuntimeException('Recurring dispute has no subscription record.');
            query('INSERT INTO '.table('software_payment_disputes').' (case_reference,original_transaction_id,evidence,invoice_id,subscription_invoice_id,recorded_by,created_at) VALUES (?,?,?,?,?,?,?)',
                [$caseReference,$originalTransactionId,$evidence,$receipt['invoice_id'],$receipt['subscription_invoice_id'],$actor,gmdate('Y-m-d H:i:s')]);
            query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['review',$sub['id']]);
            license_audit($actor,'payment.dispute','Merchant dispute '.$caseReference.' recorded for recurring transaction '.$originalTransactionId);
            db()->commit();
            return 'subscription_dispute_review';
        }
        throw new RuntimeException('Original software payment transaction was not found.');
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        throw $e;
    }
}

function software_subscription_lifecycle_review(string $eventType,string $subscriptionId):string{
    if(!preg_match('/^[0-9]{1,25}$/D',$subscriptionId))throw new InvalidArgumentException('Invalid subscription identifier.');
    if(db()->inTransaction())throw new LogicException('Subscription lifecycle review must own the transaction.');
    db()->beginTransaction();
    try{
        $sub=one('SELECT * FROM '.table('software_subscriptions').' WHERE gateway_subscription_id=? FOR UPDATE',[$subscriptionId]);
        if(!$sub){db()->commit();return 'unknown_subscription';}
        $target=software_subscription_event_target($eventType,(string)$sub['status']);
        if($target===null){db()->commit();return 'ignored';}
        if($sub['status']===$target){db()->commit();return 'replay';}
        query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',[$target,$sub['id']]);
        db()->commit();
        // Paid-through entitlements are deliberately not revoked here.
        return $target==='review'?'subscription_review':'subscription_cancelled';
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        throw $e;
    }
}
