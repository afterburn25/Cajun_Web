<?php
/** Verified Authorize.net charge -> existing invoice payment ledger -> license. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/software_charge_guard.php';

function software_charge_record_paid(int $invoice,string $transactionId,int $cents):void{
    db()->beginTransaction();
    try{
        $attempt=one('SELECT * FROM '.table('software_charges').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        if(!$attempt||$attempt['status']!=='processing'||(int)$attempt['amount_cents']!==$cents)
            throw new RuntimeException('Gateway attempt no longer matches this payment.');
        $i=ledger_invoice($invoice);$balance=ledger_balance($i);
        if($i['status']!=='issued'||$balance['paid_cents']!==0||$balance['due_cents']!==$cents)
            throw new RuntimeException('Invoice changed while charge was in progress. Owner reconciliation required.');
        $external=hash('sha256','authorize-net|'.$transactionId);
        $id=ledger_insert($i,0,'gateway',$cents,'authorize_net',
            'Confirmed Authorize.net transaction '.$transactionId,gmdate('Y-m-d'),null,null,$external);
        query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
            ['paid','Verified gateway receipt '.$id,gmdate('Y-m-d H:i:s'),$invoice]);
        query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND status='prepared'",[$invoice]);
        $paidAt=gmdate('Y-m-d H:i:s');
        query('UPDATE '.table('software_charge_attempts').' SET status=?,transaction_id=?,updated_at=? WHERE request_key=? AND status=?',
            ['paid',$transactionId,$paidAt,$attempt['request_key'],'processing']);
        query('UPDATE '.table('software_charges').' SET status=?,transaction_id=?,updated_at=? WHERE invoice_id=?',
            ['paid',$transactionId,$paidAt,$invoice]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    try{license_fulfill_invoice($invoice);}catch(Throwable $e){report_error($e);}
}
/**
 * Finalize an owner-reconciled charge only after an independent exact gateway
 * transaction readback. This is idempotent for the same paid transaction and
 * refuses declined/sandbox/reversal/dispute states.
 */
function software_charge_reconcile_finalize(int $invoice,string $transactionId,int $cents,int $actor):string{
    if($invoice<1||$actor<1||$cents<1||!preg_match('/^[0-9]{5,30}$/D',$transactionId)){
        throw new InvalidArgumentException('Invalid reconciled software payment.');
    }
    if(db()->inTransaction())throw new LogicException('Charge reconciliation finalization owns its transaction.');
    db()->beginTransaction();
    try{
        $charge=one('SELECT * FROM '.table('software_charges').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        if(!$charge)throw new RuntimeException('Software charge history is missing.');
        if((int)$charge['amount_cents']!==$cents)throw new RuntimeException('Reconciled amount does not match the recorded software charge.');
        if($charge['status']==='paid'){
            if((string)($charge['transaction_id']??'')!==$transactionId){
                throw new RuntimeException('Paid software charge references a different gateway transaction.');
            }
            $paid=one('SELECT status FROM '.table('studio_invoices').' WHERE id=?',[$invoice]);
            if(($paid['status']??'')!=='paid')throw new RuntimeException('Paid software charge and invoice state disagree.');
            db()->commit();return 'already_paid';
        }
        if(!in_array((string)$charge['status'],['processing','uncertain','review'],true)){
            throw new RuntimeException('This software charge state cannot be reconciled as a new paid transaction.');
        }
        if($charge['transaction_id']!==null&&(string)$charge['transaction_id']!==$transactionId){
            throw new RuntimeException('Recorded gateway transaction disagrees with the reconciliation request.');
        }
        $other=one('SELECT invoice_id FROM '.table('software_charges').' WHERE transaction_id=? AND invoice_id<>? FOR UPDATE',
            [$transactionId,$invoice]);
        if($other)throw new RuntimeException('Gateway transaction is already assigned to another software invoice.');
        $attempt=one('SELECT * FROM '.table('software_charge_attempts').' WHERE request_key=? FOR UPDATE',
            [$charge['request_key']]);
        if(!$attempt||(int)$attempt['invoice_id']!==$invoice||(int)$attempt['amount_cents']!==$cents
            ||!in_array((string)$attempt['status'],['processing','uncertain','review'],true)
            ||($attempt['transaction_id']!==null&&(string)$attempt['transaction_id']!==$transactionId)){
            throw new RuntimeException('Software charge attempt history is inconsistent.');
        }
        $i=ledger_invoice($invoice);$balance=ledger_balance($i);
        if($i['status']!=='issued'||$balance['paid_cents']!==0||$balance['due_cents']!==$cents){
            throw new RuntimeException('Invoice changed before payment reconciliation. Owner review remains required.');
        }
        $order=one('SELECT client_id FROM '.table('software_orders').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        if(!$order||(int)$order['client_id']!==(int)$charge['client_id']||(int)$i['client_id']!==(int)$charge['client_id']){
            throw new RuntimeException('Software order, invoice and charge ownership disagree.');
        }
        $external=hash('sha256','authorize-net|'.$transactionId);
        $entry=ledger_insert($i,0,'gateway',$cents,'authorize_net',
            'Owner-reconciled Authorize.net transaction '.$transactionId,gmdate('Y-m-d'),null,null,$external);
        $now=gmdate('Y-m-d H:i:s');
        query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
            ['paid','Reconciled verified gateway receipt '.$entry,$now,$invoice]);
        query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND status='prepared'",[$invoice]);
        query('UPDATE '.table('software_charge_attempts').' SET status=?,transaction_id=?,updated_at=? WHERE id=?',
            ['paid',$transactionId,$now,$attempt['id']]);
        query('UPDATE '.table('software_charges').' SET status=?,transaction_id=?,updated_at=? WHERE id=?',
            ['paid',$transactionId,$now,$charge['id']]);
        license_audit($actor,'payment.reconcile',
            'Software invoice '.$invoice.' reconciled to verified Authorize.net transaction '.$transactionId);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    try{license_fulfill_invoice($invoice);}catch(Throwable $e){report_error($e);}
    return 'paid';
}

/** Owner recovery for uncertain/processing one-time charges. Sandbox verifies shape only and never settles. */
function software_charge_reconcile(int $invoice,string $transactionId,#[SensitiveParameter]string $password):string{
    if(support_current())throw new RuntimeException('Payment reconciliation is unavailable during support access.');
    if(!software_commerce_ready()||!ledger_ready())throw new RuntimeException('Software checkout is not prepared.');
    if($invoice<1||!preg_match('/^[0-9]{5,30}$/D',$transactionId)){
        throw new InvalidArgumentException('Enter the software invoice and verified gateway transaction ID.');
    }
    staff_require('staff.manage');
    staff_owner($password); // authenticate before any gateway lookup
    $charge=one('SELECT amount_cents,status,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$invoice]);
    if(!$charge)throw new RuntimeException('Software charge history is missing.');
    if($charge['status']==='paid'){
        if((string)($charge['transaction_id']??'')!==$transactionId){
            throw new RuntimeException('Paid software charge references a different gateway transaction.');
        }
        return 'already_paid';
    }
    if(!in_array((string)$charge['status'],['processing','uncertain','review'],true)){
        throw new RuntimeException('Only processing, uncertain, or review charges can be reconciled.');
    }
    if($charge['transaction_id']!==null&&(string)$charge['transaction_id']!==$transactionId){
        throw new RuntimeException('Recorded gateway transaction disagrees with the reconciliation request.');
    }
    $gateway=software_anet_settings();
    $details=software_anet_execute('getTransactionDetailsRequest',['transId'=>$transactionId]);
    if(!software_charge_confirmed($details,$transactionId,$invoice,(int)$charge['amount_cents'])){
        throw new RuntimeException('Gateway transaction does not exactly confirm this invoice and amount.');
    }
    if($gateway['mode']!=='production'){
        staff_locked(function()use($password,$invoice,$transactionId):void{
            $actor=staff_owner($password);
            license_audit((int)$actor['id'],'payment.reconcile.sandbox',
                'Sandbox readback verified for software invoice '.$invoice.' transaction '.$transactionId.'; no paid transition');
        });
        return 'sandbox_verified';
    }
    return staff_locked(function()use($password,$invoice,$transactionId,$charge):string{
        $actor=staff_owner($password);
        return software_charge_reconcile_finalize(
            $invoice,$transactionId,(int)$charge['amount_cents'],(int)$actor['id']
        );
    });
}

/** The original Accept.js token is short lived and never stored or logged. */
function software_charge_run(array $client,int $invoice,string $key,
    string $descriptor,#[SensitiveParameter] string $nonce):string{
    if(support_current())throw new RuntimeException('Payment is unavailable during support access.');
    software_anet_nonce($descriptor,$nonce); // reject malformed local input before reserving an attempt
    $gateway=software_anet_settings(); // check private credentials before reserving the attempt
    $amount=software_charge_prepare($invoice,(int)$client['id'],$key);
    $transactionId=null;
    try{
        $response=software_anet_execute('createTransactionRequest',
            software_anet_charge($invoice,$amount,$descriptor,$nonce));
        $transactionId=software_anet_approved($response);
        if($transactionId===null){
            $status=(string)($response['transactionResponse']['responseCode']??'');
            software_charge_mark($invoice,$status==='2'?'declined':'review');
            return $status==='2'?'declined':'review';
        }
        $details=software_anet_execute('getTransactionDetailsRequest',['transId'=>$transactionId]);
        if(!software_charge_confirmed($details,$transactionId,$invoice,$amount)){
            software_charge_mark($invoice,'uncertain',$transactionId);
            return 'review';
        }
        if($gateway['mode']==='sandbox'){
            software_charge_mark($invoice,'sandbox_test',$transactionId);
            return 'sandbox'; // Sandbox does not constitute real payment or release licenses.
        }
        software_charge_record_paid($invoice,$transactionId,$amount);
        return 'paid';
    }catch(Throwable $e){
        software_charge_mark($invoice,'uncertain',
            is_string($transactionId)&&preg_match('/^[0-9]{5,30}$/D',$transactionId)?$transactionId:null);
        report_error($e);
        return 'review';
    }
}
