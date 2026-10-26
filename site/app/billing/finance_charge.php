<?php
/** One-time financed-site Authorize.Net charges using the shared invoice ledger. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/migrations/license_finance_gateway_migrate.php';
require_once CWP_CODE_ROOT.'/app/billing/finance_authorize_net.php';
require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
require_once CWP_CODE_ROOT.'/app/billing/ledger.php';

function license_finance_offer_for_invoice(int $invoice,bool $lock=false):array{
    if($invoice<1)throw new InvalidArgumentException('Invalid financing invoice.');
    $suffix=$lock?' FOR UPDATE':'';
    $offer=one('SELECT * FROM '.table('license_finance_offers').' WHERE activation_invoice_id=? AND status=?'.$suffix,[$invoice,'signed']);
    if(!$offer){
        $offer=one('SELECT o.* FROM '.table('recurring_billing_invoices').' r JOIN '.table('license_finance_offers').' o ON o.schedule_id=r.schedule_id WHERE r.invoice_id=? AND o.status=?'.$suffix,[$invoice,'signed']);
    }
    if(!$offer)throw new RuntimeException('Invoice is not part of a signed finance agreement.');
    return $offer;
}
function license_finance_charge_prepare(array $request,string $attemptKey):array{
    if(!license_finance_gateway_ready()||!ledger_ready())throw new RuntimeException('Financing card checkout is not prepared.');
    if(!preg_match('/^[a-f0-9]{64}$/D',$attemptKey))throw new InvalidArgumentException('Invalid financing checkout request.');
    if(($request['purpose']??'')!=='financing'||($request['status']??'')!=='prepared')throw new RuntimeException('Choose a current financing payment request.');
    if(db()->inTransaction())throw new LogicException('Financing charge preparation requires its own transaction.');
    $result=[];
    staff_locked(function()use($request,$attemptKey,&$result):void{
        staff_transaction(function()use($request,$attemptKey,&$result):void{
            $saved=one('SELECT * FROM '.table('payment_requests').' WHERE id=? FOR UPDATE',[$request['id']]);
            if(!$saved||!hash_equals((string)$saved['request_key'],(string)$request['request_key'])||payments_request_state($saved)!=='prepared'){
                throw new RuntimeException('Financing payment request changed. Reload the current link.');
            }
            $invoice=(int)$saved['invoice_id'];$offer=license_finance_offer_for_invoice($invoice,true);
            if((int)$offer['client_id']!==(int)$saved['client_id'])throw new RuntimeException('Financing payment ownership changed.');
            $i=ledger_invoice($invoice);$balance=ledger_balance($i);
            if($i['status']!=='issued'||$balance['due_cents']!==(int)$saved['amount_cents']||(int)$saved['amount_cents']<1){
                throw new RuntimeException('Financing invoice amount changed. Reload the current payment request.');
            }
            $existing=one('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
            $now=gmdate('Y-m-d H:i:s');
            if($existing){
                if(!in_array((string)$existing['status'],['declined','sandbox_test'],true)){
                    throw new RuntimeException('A financing gateway attempt already exists. Reconcile its outcome before charging again.');
                }
                query('UPDATE '.table('license_finance_charges').' SET request_id=?,status=?,request_key=?,transaction_id=NULL,updated_at=? WHERE id=?',
                    [$saved['id'],'processing',$attemptKey,$now,$existing['id']]);
            }else{
                query('INSERT INTO '.table('license_finance_charges').' (request_id,invoice_id,offer_id,amount_cents,status,request_key,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',
                    [$saved['id'],$invoice,$offer['id'],$saved['amount_cents'],'processing',$attemptKey,$now,$now]);
            }
            query('INSERT INTO '.table('license_finance_charge_attempts').' (invoice_id,request_key,amount_cents,status,created_at,updated_at) VALUES (?,?,?,?,?,?)',
                [$invoice,$attemptKey,$saved['amount_cents'],'processing',$now,$now]);
            $subscription=license_finance_subscription_row((string)$offer['id'],true);
            $result=[
                'invoice'=>$invoice,'amount_cents'=>(int)$saved['amount_cents'],'offer'=>$offer,
                'create_profile'=>$offer['activation_invoice_id']!==null&&(int)$offer['activation_invoice_id']===$invoice&&$subscription===null
            ];
        });
    });
    return $result;
}
function license_finance_charge_mark(int $invoice,string $state,?string $transactionId=null):void{
    if(!in_array($state,['declined','uncertain','review','sandbox_test'],true))throw new InvalidArgumentException('Unsupported financing payment outcome.');
    staff_locked(fn()=>staff_transaction(function()use($invoice,$state,$transactionId):void{
        $charge=one('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        if(!$charge||$charge['status']!=='processing')return;
        $now=gmdate('Y-m-d H:i:s');
        query('UPDATE '.table('license_finance_charge_attempts').' SET status=?,transaction_id=?,updated_at=? WHERE request_key=? AND status=?',
            [$state,$transactionId,$now,$charge['request_key'],'processing']);
        query('UPDATE '.table('license_finance_charges').' SET status=?,transaction_id=?,updated_at=? WHERE id=?',
            [$state,$transactionId,$now,$charge['id']]);
    }));
}
function license_finance_charge_record_paid(int $invoice,string $transactionId,int $cents,?array $profiles=null):void{
    staff_locked(fn()=>staff_transaction(function()use($invoice,$transactionId,$cents,$profiles):void{
        $charge=one('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        if(!$charge||!in_array((string)$charge['status'],['processing','uncertain','review'],true)||(int)$charge['amount_cents']!==$cents){
            throw new RuntimeException('Financing gateway attempt no longer matches this payment.');
        }
        $i=ledger_invoice($invoice);$balance=ledger_balance($i);
        if($i['status']!=='issued'||$balance['due_cents']!==$cents)throw new RuntimeException('Financing invoice changed during payment.');
        $external=hash('sha256','authorize-net|'.$transactionId);
        $entry=ledger_insert($i,0,'gateway',$cents,'authorize_net','Verified financing transaction '.$transactionId,gmdate('Y-m-d'),null,null,$external);
        $now=gmdate('Y-m-d H:i:s');
        query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',['paid','Verified financing gateway receipt '.$entry,$now,$invoice]);
        query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND status='prepared'",[$invoice]);
        query('UPDATE '.table('license_finance_charge_attempts').' SET status=?,transaction_id=?,updated_at=? WHERE request_key=? AND status IN (?,?,?)',
            ['paid',$transactionId,$now,$charge['request_key'],'processing','uncertain','review']);
        query('UPDATE '.table('license_finance_charges').' SET status=?,transaction_id=?,customer_profile_id=?,payment_profile_id=?,updated_at=? WHERE id=?',
            ['paid',$transactionId,$profiles['customer_profile_id']??null,$profiles['payment_profile_id']??null,$now,$charge['id']]);
        license_audit(0,'financing.payment','Finance agreement '.$charge['offer_id'].' invoice '.$invoice.' paid by gateway transaction '.$transactionId);
    }));
    try{license_financing_sync_by_invoice($invoice);}catch(Throwable $e){report_error($e);}
}
function license_finance_charge_run(array $request,string $attemptKey,string $descriptor,#[\SensitiveParameter]string $nonce,array $billing):string{
    software_anet_nonce($descriptor,$nonce);
    $gateway=license_finance_anet_settings();
    $prepared=license_finance_charge_prepare($request,$attemptKey);
    $invoice=$prepared['invoice'];$offer=$prepared['offer'];$transactionId=null;
    try{
        $client=one('SELECT email FROM '.table('studio_clients').' WHERE id=?',[$offer['client_id']]);
        if(!$client||!filter_var($client['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Client email is unavailable for financing checkout.');
        $reply=license_finance_anet_execute('createTransactionRequest',
            license_finance_anet_charge_request($invoice,$prepared['amount_cents'],(string)$client['email'],$billing,$descriptor,$nonce,(bool)$prepared['create_profile']));
        $transactionId=software_anet_approved($reply);
        if($transactionId===null){
            $code=(string)($reply['transactionResponse']['responseCode']??'');
            license_finance_charge_mark($invoice,$code==='2'?'declined':'review');
            return $code==='2'?'declined':'review';
        }
        $details=license_finance_anet_execute('getTransactionDetailsRequest',['transId'=>$transactionId]);
        if(!software_anet_one_time_charge_confirmed($details,$transactionId,$invoice,$prepared['amount_cents'])){
            license_finance_charge_mark($invoice,'uncertain',$transactionId);return 'review';
        }
        $profiles=null;
        if($prepared['create_profile']){
            $profiles=license_finance_anet_profile_ids($reply,$details);
            if($profiles===null){
                try{$profiles=license_finance_anet_profile_from_transaction($transactionId);}catch(Throwable $profileError){report_error($profileError);}
            }
        }
        if($gateway['mode']==='sandbox'){
            license_finance_charge_mark($invoice,'sandbox_test',$transactionId);
            if($prepared['create_profile']&&$profiles!==null)license_finance_subscription_create_profile((string)$offer['id'],$profiles);
            return 'sandbox';
        }
        license_finance_charge_record_paid($invoice,$transactionId,$prepared['amount_cents'],$profiles);
        if($prepared['create_profile']){
            if($profiles===null){
                license_audit(0,'financing.subscription.review','Finance agreement '.$offer['id'].' initial payment succeeded but reusable payment profile was not confirmed');
                return 'paid_subscription_review';
            }
            $subscription=license_finance_subscription_create_profile((string)$offer['id'],$profiles);
            if($subscription==='review')return 'paid_subscription_review';
        }
        return 'paid';
    }catch(Throwable $e){
        try{license_finance_charge_mark($invoice,'uncertain',
            is_string($transactionId)&&preg_match('/^[0-9]{5,30}$/D',$transactionId)?$transactionId:null);}catch(Throwable $markError){report_error($markError);}
        report_error($e);return 'review';
    }
}
function license_finance_charge_reconcile(int $invoice,string $transactionId,#[\SensitiveParameter]string $password):string{
    if(support_current())throw new RuntimeException('Financing payment reconciliation is unavailable during support access.');
    if(!license_finance_gateway_ready()||!ledger_ready())throw new RuntimeException('Financing payment reconciliation is not prepared.');
    if($invoice<1||!preg_match('/^[0-9]{5,30}$/D',$transactionId))throw new InvalidArgumentException('Enter a financing invoice and verified Authorize.Net transaction ID.');
    $actor=staff_owner($password);
    $charge=one('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=?',[$invoice]);
    if(!$charge)throw new RuntimeException('Financing charge history is missing.');
    if($charge['status']==='paid'){
        if((string)($charge['transaction_id']??'')!==$transactionId)throw new RuntimeException('Paid financing charge references a different gateway transaction.');
        return 'already_paid';
    }
    if(!in_array((string)$charge['status'],['processing','uncertain','review'],true))throw new RuntimeException('This financing charge state cannot be reconciled as paid.');
    if($charge['transaction_id']!==null&&!hash_equals((string)$charge['transaction_id'],$transactionId))throw new RuntimeException('Recorded financing transaction disagrees with the reconciliation request.');
    $details=license_finance_anet_execute('getTransactionDetailsRequest',['transId'=>$transactionId]);
    if(!software_anet_one_time_charge_confirmed($details,$transactionId,$invoice,(int)$charge['amount_cents'])){
        throw new RuntimeException('Gateway transaction does not exactly confirm this financing invoice and amount.');
    }
    $gateway=license_finance_anet_settings();
    if($gateway['mode']!=='production'){
        license_audit((int)$actor['id'],'financing.payment.reconcile.sandbox','Sandbox readback verified finance invoice '.$invoice.' transaction '.$transactionId.'; no paid transition');
        return 'sandbox_verified';
    }
    $offer=license_finance_offer_row((string)$charge['offer_id']);
    $profiles=license_finance_anet_profile_ids([],$details);
    $needsSubscription=(int)($offer['activation_invoice_id']??0)===$invoice&&license_finance_subscription_row((string)$offer['id'])===null;
    if($needsSubscription&&$profiles===null){
        try{$profiles=license_finance_anet_profile_from_transaction($transactionId);}catch(Throwable $e){report_error($e);}
    }
    license_finance_charge_record_paid($invoice,$transactionId,(int)$charge['amount_cents'],$profiles);
    $result='paid';
    if($needsSubscription){
        if($profiles===null)$result='paid_subscription_review';
        else{
            $state=license_finance_subscription_create_profile((string)$offer['id'],$profiles);
            if($state==='review')$result='paid_subscription_review';
        }
    }
    license_audit((int)$actor['id'],'financing.payment.reconcile','Finance invoice '.$invoice.' reconciled to verified Authorize.Net transaction '.$transactionId.' state '.$result);
    return $result;
}

function license_finance_charge_webhook_review(array $details,string $transactionId):string{
    if(!preg_match('/^[0-9]{5,30}$/D',$transactionId))throw new InvalidArgumentException('Invalid financing transaction ID.');
    $tx=$details['transaction']??null;if(!is_array($tx)||isset($tx['subscription']))return 'finance_payment_unmatched';
    $invoiceNumber=(string)($tx['order']['invoiceNumber']??'');
    if(!preg_match('/^CWP-([1-9][0-9]{0,14})$/D',$invoiceNumber,$m))return 'finance_payment_unmatched';
    $invoice=(int)$m[1];$charge=one('SELECT * FROM '.table('license_finance_charges').' WHERE invoice_id=?',[$invoice]);
    if(!$charge)return 'finance_payment_unmatched';
    if(!software_anet_one_time_charge_confirmed($details,$transactionId,$invoice,(int)$charge['amount_cents']))return 'finance_payment_mismatch';
    if((string)$charge['status']==='paid'&&(string)$charge['transaction_id']===$transactionId)return 'finance_payment_replay';
    if(in_array((string)$charge['status'],['processing','uncertain','review'],true)){
        staff_locked(fn()=>staff_transaction(function()use($charge,$transactionId):void{
            $current=one('SELECT * FROM '.table('license_finance_charges').' WHERE id=? FOR UPDATE',[$charge['id']]);
            if(!$current||$current['status']==='paid')return;
            if($current['transaction_id']!==null&&!hash_equals((string)$current['transaction_id'],$transactionId))throw new RuntimeException('Financing charge retains a different gateway transaction.');
            query('UPDATE '.table('license_finance_charges').' SET status=?,transaction_id=?,updated_at=? WHERE id=?',
                ['review',$transactionId,gmdate('Y-m-d H:i:s'),$current['id']]);
        }));
        return 'finance_payment_review';
    }
    return 'finance_payment_replay';
}
