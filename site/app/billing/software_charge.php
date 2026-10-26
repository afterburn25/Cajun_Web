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
/** The original Accept.js token is short lived and never stored or logged. */
function software_charge_run(array $client,int $invoice,string $key,
    string $descriptor,#[SensitiveParameter] string $nonce):string{
    if(support_current())throw new RuntimeException('Payment is unavailable during support access.');
    $gateway=software_anet_settings(); // check private credentials before reserving the attempt
    $amount=software_charge_prepare($invoice,(int)$client['id'],$key);
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
        software_charge_mark($invoice,'uncertain');
        report_error($e);
        return 'review';
    }
}
