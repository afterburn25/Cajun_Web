<?php
/** Verified financed-site payment reversals. Partial refunds stop in review; full reversals reopen one retained invoice once. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/finance_charge.php';
require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
require_once CWP_CODE_ROOT.'/app/billing/authorize_net_contract.php';

function license_finance_reversal_origin(string $originalTransactionId,bool $lock=false):?array{
    if(!preg_match('/^[0-9]{5,30}$/D',$originalTransactionId))return null;
    $suffix=$lock?' FOR UPDATE':'';
    $charge=one('SELECT invoice_id,offer_id,amount_cents FROM '.table('license_finance_charges').
        ' WHERE transaction_id=?'.$suffix,[$originalTransactionId]);
    if($charge){
        return [
            'source'=>'charge','invoice_id'=>(int)$charge['invoice_id'],'offer_id'=>(string)$charge['offer_id'],
            'period_index'=>null,'amount_cents'=>(int)$charge['amount_cents']
        ];
    }
    $receipt=one('SELECT invoice_id,offer_id,period_index FROM '.table('license_finance_subscription_receipts').
        ' WHERE gateway_transaction_id=?'.$suffix,[$originalTransactionId]);
    if($receipt){
        $invoice=ledger_invoice((int)$receipt['invoice_id']);
        return [
            'source'=>'subscription','invoice_id'=>(int)$receipt['invoice_id'],'offer_id'=>(string)$receipt['offer_id'],
            'period_index'=>(int)$receipt['period_index'],'amount_cents'=>(int)$invoice['amount_cents']
        ];
    }
    // A one-time charge row is reused after a verified full reversal and a
    // later repayment, so preserve delayed webhook/replay ownership through
    // the immutable reversal history itself.
    $history=one('SELECT invoice_id,offer_id FROM '.table('license_finance_reversals').
        ' WHERE original_transaction_id=? ORDER BY id LIMIT 1'.$suffix,[$originalTransactionId]);
    if(!$history)return null;
    $invoice=ledger_invoice((int)$history['invoice_id']);
    return [
        'source'=>'history','invoice_id'=>(int)$history['invoice_id'],'offer_id'=>(string)$history['offer_id'],
        'period_index'=>null,'amount_cents'=>(int)$invoice['amount_cents']
    ];
}

/**
 * Apply a transaction-detail-verified refund/void.
 * Full cumulative refund or void reverses the original gateway ledger entry once.
 * Partial refunds remain review-only until an owner resolves the accounting outcome.
 */
function license_finance_payment_reversal_apply(array $reversal):string{
    if(!license_finance_gateway_ready()||!ledger_ready())throw new RuntimeException('Financing reversal review is not prepared.');
    $kind=(string)($reversal['kind']??'');
    $event=(string)($reversal['transaction_id']??'');
    $original=(string)($reversal['original_transaction_id']??'');
    $amount=$reversal['amount_cents']??null;
    if(!in_array($kind,['refund','void'],true)
        ||!preg_match('/^[0-9]{5,30}$/D',$event)
        ||!preg_match('/^[0-9]{5,30}$/D',$original)
        ||($kind==='refund'&&(!is_int($amount)||$amount<1))
        ||($kind==='void'&&$amount!==null)){
        throw new InvalidArgumentException('Invalid verified financing reversal.');
    }
    if(db()->inTransaction())throw new LogicException('Financing reversal review must own its transaction.');
    $result='finance_reversal_unmatched';$syncInvoice=null;
    staff_locked(function()use($reversal,$kind,$event,$original,$amount,&$result,&$syncInvoice):void{
        staff_transaction(function()use($reversal,$kind,$event,$original,$amount,&$result,&$syncInvoice):void{
            $origin=license_finance_reversal_origin($original,true);
            if(!$origin){$result='finance_reversal_unmatched';return;}
            $invoiceId=(int)$origin['invoice_id'];$originalAmount=(int)$origin['amount_cents'];
            if($originalAmount<1)throw new RuntimeException('Financing reversal original amount is invalid.');
            $eventAmount=$kind==='void'?$originalAmount:(int)$amount;
            $existing=one('SELECT original_transaction_id,kind,amount_cents,status FROM '.table('license_finance_reversals').
                ' WHERE gateway_transaction_id=? FOR UPDATE',[$event]);
            if($existing){
                $same=(string)$existing['original_transaction_id']===$original
                    &&(string)$existing['kind']===$kind
                    &&(($existing['amount_cents']===null&&$amount===null)
                        ||($existing['amount_cents']!==null&&$amount!==null&&(int)$existing['amount_cents']===(int)$amount))
                    &&in_array((string)$existing['status'],['refund_review','refund_reversed','void_reversed'],true);
                if(!$same)throw new RuntimeException('Gateway financing reversal identifier was reused with conflicting details.');
                $result='replay';return;
            }
            if(one('SELECT id FROM '.table('license_finance_reversals').
                ' WHERE original_transaction_id=? AND kind=? LIMIT 1',[$original,'void'])){
                throw new RuntimeException('The original financing payment is already voided.');
            }
            $priorRefund=(int)query('SELECT COALESCE(SUM(amount_cents),0) FROM '.table('license_finance_reversals').
                " WHERE original_transaction_id=? AND kind='refund'",[$original])->fetchColumn();
            if($kind==='refund'&&$priorRefund+$eventAmount>$originalAmount){
                throw new RuntimeException('Verified financing refunds exceed the original payment.');
            }
            if($kind==='void'&&$priorRefund!==0){
                throw new RuntimeException('A verified financing void conflicts with retained refund history.');
            }
            $full=$kind==='void'||$priorRefund+$eventAmount===$originalAmount;
            $eventStatus=$kind==='void'?'void_reversed':($full?'refund_reversed':'refund_review');
            $now=gmdate('Y-m-d H:i:s');
            query('INSERT INTO '.table('license_finance_reversals').
                ' (offer_id,invoice_id,gateway_transaction_id,original_transaction_id,kind,amount_cents,status,ledger_reversal_id,created_at) '.
                'VALUES (?,?,?,?,?,?,?,?,?)',
                [$origin['offer_id'],$invoiceId,$event,$original,$kind,$kind==='refund'?$eventAmount:null,
                 $eventStatus,null,$now]);
            if(!$full){
                if($origin['source']==='charge'){
                    query('UPDATE '.table('license_finance_charges').' SET status=?,updated_at=? WHERE invoice_id=?',
                        ['review',$now,$invoiceId]);
                }else{
                    query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE offer_id=?',
                        ['review',$now,$origin['offer_id']]);
                }
                license_audit(0,'financing.reversal.review',
                    'Finance agreement '.$origin['offer_id'].' invoice '.$invoiceId.' partial refund '.$eventAmount.
                    ' recorded for owner review; original transaction '.$original);
                $result='finance_partial_refund_review';return;
            }

            $external=hash('sha256','authorize-net|'.$original);
            $payment=one('SELECT * FROM '.table('invoice_payments').
                " WHERE invoice_id=? AND kind='gateway' AND external_hash=? FOR UPDATE",[$invoiceId,$external]);
            if(!$payment||(int)$payment['amount_cents']!==$originalAmount){
                throw new RuntimeException('Verified financing reversal has no exact original gateway ledger receipt.');
            }
            $priorReversal=one('SELECT id FROM '.table('invoice_payments').' WHERE reversal_of=? FOR UPDATE',[$payment['id']]);
            if(!$priorReversal){
                $invoice=ledger_invoice($invoiceId);
                $entry=ledger_insert($invoice,0,'reversal',$originalAmount,'authorize_net',
                    'Verified financing '.($kind==='void'?'void':'refund').' transaction '.$event,
                    gmdate('Y-m-d'),hash('sha256','finance-ledger-reversal|'.$invoiceId.'|'.$event),
                    hash('sha256','finance-ledger-reversal-v1|'.$original.'|'.$event.'|'.$originalAmount),
                    hash('sha256','authorize-net-reversal|'.$event),(int)$payment['id']);
                query('UPDATE '.table('license_finance_reversals').' SET ledger_reversal_id=? WHERE gateway_transaction_id=?',
                    [$entry,$event]);
                query('UPDATE '.table('studio_invoices')." SET status='issued',paid_note='',paid_at=NULL WHERE id=?",[$invoiceId]);
                query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND status='prepared'",[$invoiceId]);
                license_audit(0,'financing.reversal.applied',
                    'Finance agreement '.$origin['offer_id'].' invoice '.$invoiceId.' ledger receipt '.$payment['id'].
                    ' reversed by verified '.$kind.' '.$event.'; ledger reversal '.$entry);
            }
            if($origin['source']==='charge'){
                query('UPDATE '.table('license_finance_charges').' SET status=?,updated_at=? WHERE invoice_id=?',
                    [$kind==='void'?'voided':'refunded',$now,$invoiceId]);
            }else{
                query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE offer_id=?',
                    ['reversal_recovery',$now,$origin['offer_id']]);
            }
            $syncInvoice=$invoiceId;$result=$kind==='void'?'finance_void_reversed':'finance_refund_reversed';
        });
    });
    if($syncInvoice!==null){
        try{license_financing_sync_by_invoice($syncInvoice);}catch(Throwable $e){report_error($e);}
    }
    return $result;
}

function license_finance_payment_reversal_review(array $details,string $eventTransactionId,string $eventType):string{
    if(!license_finance_gateway_ready()||!ledger_ready())return 'finance_reversal_unmatched';
    $gateway=license_finance_anet_settings();
    $reversal=software_anet_reversal_details($details,$eventTransactionId,$eventType);
    if($gateway['mode']!=='production')return 'sandbox_finance_reversal_verified';
    if($reversal['kind']==='refund'&&($reversal['amount_cents']===null||$reversal['amount_cents']<1)){
        throw new RuntimeException('Financing refund amount is not trustworthy.');
    }
    if(license_finance_reversal_origin((string)$reversal['original_transaction_id'])===null){
        return 'finance_reversal_unmatched';
    }
    return license_finance_payment_reversal_apply($reversal);
}
