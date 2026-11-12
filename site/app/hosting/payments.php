<?php
/** Primary-client hosting payment capture with exact production readback and separately authorized fulfillment. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout.php';
require_once CWP_CODE_ROOT.'/app/billing/authorize_net_contract.php';
require_once CWP_CODE_ROOT.'/app/billing/authorize_net_client.php';
require_once CWP_CODE_ROOT.'/app/billing/gateway_receipts.php';
require_once CWP_CODE_ROOT.'/database/schema/hosting_payments_schema.php';

function hosting_payments_ready():bool {
    if(setting('hosting_payments_schema_version')!=='1'||!gateway_receipts_ready())return false;
    foreach(hosting_payments_schema(config()['db']['prefix']) as $name=>$sql){
        if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))return false;
        hosting_payments_validate_schema($name,$sql);
    }
    return true;
}
function hosting_payments_require():void {if(!hosting_payments_ready())throw new RuntimeException('Hosting card payments are not prepared.');}
function hosting_anet_production_enabled():bool {
    return defined('CWP_HOSTING_PRODUCTION_PAYMENTS')&&CWP_HOSTING_PRODUCTION_PAYMENTS===true&&setting('hosting_gateway_production_reviewed')==='1';
}
function hosting_payment_configure(string $mode,bool $productionReviewed,#[\SensitiveParameter]string $password):void {
    if(!in_array($mode,['disabled','sandbox','production'],true))throw new InvalidArgumentException('Choose disabled, sandbox or production hosting payments.');
    if($mode==='production'&&(!$productionReviewed||!defined('CWP_HOSTING_PRODUCTION_PAYMENTS')||CWP_HOSTING_PRODUCTION_PAYMENTS!==true)){
        throw new RuntimeException('Production hosting payments require the code-level lock and explicit owner review.');
    }
    if(support_current())throw new RuntimeException('End client review before configuring hosting payments.');
    staff_locked(function()use($mode,$productionReviewed,$password):void {
        $actor=payments_owner($password);hosting_payments_require();
        if($mode!=='disabled'){
            $row=one('SELECT credentials FROM '.table('payment_gateways').' WHERE environment=?',[$mode]);
            if(!$row||!is_string($row['credentials'])||$row['credentials']==='')throw new RuntimeException('Save the selected gateway credentials before enabling hosting payments.');
            $secrets=payments_unseal($mode,$row['credentials']);
            if($secrets['api_login_id']===''||$secrets['transaction_key']===''||$secrets['public_client_key']==='')throw new RuntimeException('The selected gateway credentials are incomplete.');
        }
        staff_transaction(function()use($mode,$productionReviewed,$actor):void {
            save_setting('hosting_gateway_mode',$mode);save_setting('hosting_gateway_production_reviewed',$mode==='production'&&$productionReviewed?'1':'0');
            payments_audit((int)$actor['id'],'hosting_payments.configured','Hosting gateway mode '.$mode.'; production review '.($mode==='production'&&$productionReviewed?'confirmed':'not active'));
        });
    });
}
function hosting_anet_settings():array {
    require_once CWP_CODE_ROOT.'/app/billing/payments.php';
    if(!payments_ready())throw new RuntimeException('Prepare the existing payment gateway settings first.');
    $mode=(string)setting('hosting_gateway_mode');
    if(!in_array($mode,['sandbox','production'],true))throw new RuntimeException('Hosting card checkout is disabled.');
    if($mode==='production'&&!hosting_anet_production_enabled())throw new RuntimeException('Production hosting checkout is locked until explicit code-level and owner-reviewed activation.');
    $row=one('SELECT credentials,revision FROM '.table('payment_gateways').' WHERE environment=?',[$mode]);
    if(!$row||!is_string($row['credentials'])||$row['credentials']==='')throw new RuntimeException('Save gateway credentials in the existing payment settings.');
    $secrets=payments_unseal($mode,$row['credentials']);
    if($secrets['api_login_id']===''||$secrets['transaction_key']===''||$secrets['public_client_key']==='')throw new RuntimeException('Gateway configuration is incomplete.');
    return ['mode'=>$mode,'login'=>$secrets['api_login_id'],'key'=>$secrets['transaction_key'],'client_key'=>$secrets['public_client_key'],
        'revision'=>(int)$row['revision'],'account_sha256'=>gateway_receipt_account_hash($secrets['api_login_id'])];
}
function hosting_anet_context_matches(array $expected,array $current):bool {
    return ($expected['mode']??null)===$current['mode']&&($expected['revision']??null)===$current['revision']
        &&is_string($expected['account_sha256']??null)&&hash_equals($expected['account_sha256'],$current['account_sha256']);
}
function hosting_payment_row_context(array $row):array {
    return ['mode'=>(string)($row['environment']??''),'account_sha256'=>(string)($row['account_sha256']??''),'revision'=>(int)($row['gateway_revision']??0)];
}
function hosting_anet_execute(string $method,array $request,#[\SensitiveParameter]?array $context=null):array {
    if(!in_array($method,['createTransactionRequest','getTransactionDetailsRequest'],true))throw new InvalidArgumentException('Unsupported hosting gateway method.');
    if($context!==null)unset($GLOBALS['cwp_settings']);$cfg=hosting_anet_settings();
    if($context!==null&&!hosting_anet_context_matches($context,$cfg))throw new RuntimeException('Hosting gateway account or environment changed before transport.');
    return authorize_net_execute_configured('Hosting',$method,$request,$cfg);
}
function hosting_anet_customer_id(string $request):string {service_plans_id($request);return substr('CWP-H-'.$request,0,20);}
function hosting_anet_charge_request(array $source,string $descriptor,#[\SensitiveParameter]string $nonce):array {
    if(!filter_var($source['email']??null,FILTER_VALIDATE_EMAIL))throw new RuntimeException('The primary client email is unavailable for hosting checkout.');
    return ['refId'=>substr('H'.$source['request_id'],0,20),'transactionRequest'=>[
        'transactionType'=>'authCaptureTransaction','amount'=>software_anet_amount($source['amount_cents']),
        'payment'=>software_anet_nonce($descriptor,$nonce),'order'=>['invoiceNumber'=>substr('CWP-'.$source['invoice_id'],0,20),'description'=>'CajunWebPro hosting payment'],
        'customer'=>['type'=>'individual','id'=>hosting_anet_customer_id($source['request_id']),'email'=>$source['email']]
    ]];
}
function hosting_anet_readback(array $details,string $transactionId,array $source):array {
    if(!software_anet_one_time_charge_confirmed($details,$transactionId,$source['invoice_id'],$source['amount_cents']))throw new RuntimeException('Gateway transaction does not exactly confirm this hosting invoice and amount.');
    $tx=$details['transaction'];$customer=$tx['customer']??null;
    if(!is_array($customer)||(string)($customer['id']??'')!==hosting_anet_customer_id($source['request_id'])
        ||strcasecmp((string)($customer['email']??''),$source['email'])!==0)throw new RuntimeException('Gateway customer readback does not match this hosting checkout.');
    return ['transaction_id'=>$transactionId,'transaction_type'=>(string)$tx['transactionType'],'transaction_status'=>(string)$tx['transactionStatus'],
        'invoice_number'=>(string)$tx['order']['invoiceNumber'],'amount_cents'=>$source['amount_cents'],'currency'=>'USD',
        'customer_id'=>(string)$customer['id'],'customer_email'=>strtolower((string)$customer['email'])];
}
/** Exact immutable checkout and invoice source. Caller owns the transaction. */
function hosting_payment_source_locked(string $request,int $client,bool $primary):array {
    service_plans_id($request);service_plans_actor($client);hosting_checkout_require();
    if($primary)hosting_checkout_primary_client($client);
    $row=one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=? AND client_id=? FOR UPDATE',[$request,$client]);
    if(!$row)throw new RuntimeException('Hosting checkout history unavailable.');
    $validated=hosting_checkout_result_row($row);$d=$validated['document'];$v=$validated['invoice'];$i=ledger_invoice($d['invoice_id']);$balance=ledger_balance($i);
    if((int)$i['client_id']!==$client||(int)$i['job_id']!==$d['project_id']||$i['currency']!=='USD'||(int)$i['amount_cents']<1
        ||(int)$i['amount_cents']>99999999||(int)$v['id']!==(int)$i['id']||(int)$v['job_id']!==(int)$i['job_id']
        ||(int)$v['amount_cents']!==(int)$i['amount_cents']||$v['currency']!==$i['currency']||$v['reference']!==$i['reference']
        ||$v['title']!==$i['title']||$v['instructions']!==$i['instructions'])throw new RuntimeException('Hosting invoice identity changed.');
    $account=service_plans_client_row($client,false);if($primary&&(int)$account['active']!==1)throw new RuntimeException('The primary client account is inactive.');
    return ['request_id'=>$request,'client_id'=>$client,'project_id'=>(int)$i['job_id'],'agreement_id'=>$d['agreement_id'],'invoice_id'=>(int)$i['id'],
        'amount_cents'=>(int)$i['amount_cents'],'currency'=>$i['currency'],'invoice_sha256'=>ledger_invoice_hash($i),'invoice_status'=>$i['status'],
        'paid_cents'=>$balance['paid_cents'],'due_cents'=>$balance['due_cents'],'email'=>(string)$account['email'],'invoice'=>$i];
}
function hosting_payment_event(string $request,string $attempt,string $event,array $detail):void {
    query('INSERT INTO '.table('hosting_payment_events').' (request_id,attempt_key,event,detail_sha256,created_at) VALUES (?,?,?,?,?)',[$request,$attempt,$event,hash('sha256',gateway_receipt_json($detail)),gmdate('Y-m-d H:i:s')]);
}
/** Durable intent is committed before any transport. Closed decline/sandbox attempts alone may retry. */
function hosting_payment_prepare(string $request,int $client,string $attemptKey):array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$attemptKey))throw new InvalidArgumentException('Reload this hosting payment form.');
    $gateway=hosting_anet_settings();
    return hosting_checkout_locked(function()use($request,$client,$attemptKey,$gateway):array {
        hosting_payments_require();$current=hosting_anet_settings();if(!hosting_anet_context_matches($gateway,$current))throw new RuntimeException('Hosting gateway changed. Reload before paying.');
        $source=hosting_payment_source_locked($request,$client,true);$charge=one('SELECT * FROM '.table('hosting_payment_charges').' WHERE request_id=? FOR UPDATE',[$request]);
        $old=one('SELECT * FROM '.table('hosting_payment_attempts').' WHERE attempt_key=? FOR UPDATE',[$attemptKey]);
        if($old){
            if($old['request_id']!==$request||(int)$old['client_id']!==$client||(int)$old['invoice_id']!==$source['invoice_id'])throw new RuntimeException('This payment request was already used with different details.');
            return ['source'=>$source,'gateway'=>$gateway,'replay_state'=>$old['state']];
        }
        if($source['invoice_status']==='paid'&&$source['due_cents']===0){
            if(!$charge||$charge['state']!=='paid'||$charge['receipt_id']===null)throw new RuntimeException('The paid invoice needs owner review before another charge.');
            return ['source'=>$source,'gateway'=>$gateway,'replay_state'=>'paid'];
        }
        if($source['invoice_status']!=='issued'||$source['due_cents']!==$source['amount_cents']||$source['paid_cents']!==0)throw new RuntimeException('Hosting invoice balance changed. Reload before paying.');
        $now=gmdate('Y-m-d H:i:s');
        if($charge){
            if(!in_array($charge['state'],['declined','sandbox_test'],true))throw new RuntimeException('A hosting payment is pending review or already complete. Do not submit another charge.');
            query('UPDATE '.table('hosting_payment_charges').' SET state=?,current_attempt_key=?,environment=?,account_sha256=?,gateway_revision=?,transaction_id=NULL,receipt_id=NULL,updated_at=? WHERE request_id=?',
                ['processing',$attemptKey,$gateway['mode'],$gateway['account_sha256'],$gateway['revision'],$now,$request]);
        }else{
            query('INSERT INTO '.table('hosting_payment_charges').' (request_id,invoice_id,client_id,amount_cents,currency,invoice_sha256,state,current_attempt_key,environment,account_sha256,gateway_revision,transaction_id,receipt_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NULL,?,?)',
                [$request,$source['invoice_id'],$client,$source['amount_cents'],$source['currency'],$source['invoice_sha256'],'processing',$attemptKey,$gateway['mode'],$gateway['account_sha256'],$gateway['revision'],null,$now,$now]);
        }
        query('INSERT INTO '.table('hosting_payment_attempts').' (attempt_key,request_id,invoice_id,client_id,amount_cents,environment,account_sha256,gateway_revision,state,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NULL,?,?)',
            [$attemptKey,$request,$source['invoice_id'],$client,$source['amount_cents'],$gateway['mode'],$gateway['account_sha256'],$gateway['revision'],'processing',$now,$now]);
        hosting_payment_event($request,$attemptKey,'processing',['invoice_id'=>$source['invoice_id'],'invoice_sha256'=>$source['invoice_sha256'],'environment'=>$gateway['mode'],'account_sha256'=>$gateway['account_sha256'],'gateway_revision'=>$gateway['revision']]);
        return ['source'=>$source,'gateway'=>$gateway,'replay_state'=>null];
    });
}
function hosting_payment_mark(string $request,string $attemptKey,string $state,?string $transactionId=null):bool {
    if(!in_array($state,['declined','uncertain','review','sandbox_test'],true)||($transactionId!==null&&!preg_match('/^[0-9]{5,30}$/D',$transactionId)))throw new InvalidArgumentException('Unsupported hosting payment outcome.');
    return staff_locked(fn()=>staff_transaction(function()use($request,$attemptKey,$state,$transactionId):bool {
        hosting_payments_require();$charge=one('SELECT * FROM '.table('hosting_payment_charges').' WHERE request_id=? FOR UPDATE',[$request]);
        $attempt=one('SELECT * FROM '.table('hosting_payment_attempts').' WHERE attempt_key=? FOR UPDATE',[$attemptKey]);
        if(!$charge||!$attempt||$charge['state']!=='processing'||$attempt['state']!=='processing'||!hash_equals($charge['current_attempt_key'],$attemptKey))return false;
        $now=gmdate('Y-m-d H:i:s');query('UPDATE '.table('hosting_payment_attempts').' SET state=?,transaction_id=?,updated_at=? WHERE attempt_key=?',[$state,$transactionId,$now,$attemptKey]);
        query('UPDATE '.table('hosting_payment_charges').' SET state=?,transaction_id=?,updated_at=? WHERE request_id=?',[$state,$transactionId,$now,$request]);
        hosting_payment_event($request,$attemptKey,$state,['transaction_id'=>$transactionId]);return true;
    }));
}
/** Record a verified production capture atomically with the native invoice ledger and central receipt. */
function hosting_payment_record_paid(string $request,int $client,string $attemptKey,string $transactionId,array $readback,array $expectedGateway):void {
    staff_locked(fn()=>staff_transaction(function()use($request,$client,$attemptKey,$transactionId,$readback,$expectedGateway):void {
        hosting_payments_require();unset($GLOBALS['cwp_settings']);$current=hosting_anet_settings();
        if($current['mode']!=='production'||!hosting_anet_context_matches($expectedGateway,$current))throw new RuntimeException('Hosting gateway changed before payment recording.');
        $source=hosting_payment_source_locked($request,$client,false);$charge=one('SELECT * FROM '.table('hosting_payment_charges').' WHERE request_id=? FOR UPDATE',[$request]);
        $attempt=one('SELECT * FROM '.table('hosting_payment_attempts').' WHERE attempt_key=? FOR UPDATE',[$attemptKey]);
        if(!$charge||!$attempt||!in_array($charge['state'],['processing','uncertain','review'],true)||!in_array($attempt['state'],['processing','uncertain','review'],true)
            ||!hash_equals($charge['current_attempt_key'],$attemptKey)||$charge['invoice_sha256']!==$source['invoice_sha256']
            ||(int)$charge['amount_cents']!==$source['amount_cents']||$source['invoice_status']!=='issued'||$source['due_cents']!==$source['amount_cents'])throw new RuntimeException('Hosting payment intent no longer matches this invoice.');
        if(!hosting_anet_context_matches(hosting_payment_row_context($charge),$current)||!hosting_anet_context_matches(hosting_payment_row_context($attempt),$current))throw new RuntimeException('Hosting payment intent belongs to a different gateway account, environment or revision.');
        if(($charge['transaction_id']!==null&&!hash_equals($charge['transaction_id'],$transactionId))||($attempt['transaction_id']!==null&&!hash_equals($attempt['transaction_id'],$transactionId)))throw new RuntimeException('Hosting attempt retains a different gateway transaction.');
        $origin=one('SELECT attempt_key FROM '.table('hosting_payment_attempts').' WHERE environment=? AND account_sha256=? AND transaction_id=? FOR UPDATE',[$current['mode'],$current['account_sha256'],$transactionId]);
        if($origin&&!hash_equals($origin['attempt_key'],$attemptKey))throw new RuntimeException('Gateway transaction belongs to another hosting attempt.');
        $requestHash=hash('sha256',gateway_receipt_json(['source'=>'hosting_checkout','request_id'=>$request,'client_id'=>$client,'invoice_id'=>$source['invoice_id'],'invoice_sha256'=>$source['invoice_sha256'],'attempt_key'=>$attemptKey]));
        $entry=ledger_insert($source['invoice'],0,'gateway',$source['amount_cents'],'authorize_net','Verified hosting transaction '.$transactionId,gmdate('Y-m-d'),$attemptKey,$requestHash,hash('sha256','authorize-net|'.$transactionId),null,$attemptKey);
        $receipt=gateway_receipt_record($source['invoice'],$entry,['environment'=>'production','account_sha256'=>$current['account_sha256'],'gateway_revision'=>$current['revision'],
            'transaction_id'=>$transactionId,'source'=>'hosting_checkout','source_id'=>$request,'attempt_key'=>$attemptKey,'transaction_status'=>$readback['transaction_status']],$readback);
        $now=gmdate('Y-m-d H:i:s');query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',['paid','Verified hosting gateway receipt '.$receipt['id'],$now,$source['invoice_id']]);
        query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND status='prepared'",[$source['invoice_id']]);
        query('UPDATE '.table('hosting_payment_attempts').' SET state=?,transaction_id=?,updated_at=? WHERE attempt_key=?',['paid',$transactionId,$now,$attemptKey]);
        query('UPDATE '.table('hosting_payment_charges').' SET state=?,transaction_id=?,receipt_id=?,updated_at=? WHERE request_id=?',['paid',$transactionId,$receipt['id'],$now,$request]);
        hosting_payment_event($request,$attemptKey,'paid',['receipt_id'=>$receipt['id'],'ledger_entry_id'=>$entry,'transaction_id'=>$transactionId,'readback_sha256'=>$receipt['readback_sha256']]);
    }));
}
function hosting_payment_run(string $request,int $client,string $attemptKey,string $descriptor,#[\SensitiveParameter]string $nonce,?callable $execute=null):string {
    if($execute!==null&&PHP_SAPI!=='cli')throw new LogicException('Synthetic hosting payment transport is CLI-only.');
    software_anet_nonce($descriptor,$nonce);$prepared=hosting_payment_prepare($request,$client,$attemptKey);
    if($prepared['replay_state']!==null)return $prepared['replay_state']==='paid'?'already_paid':(in_array($prepared['replay_state'],['declined','sandbox_test'],true)?$prepared['replay_state']:'review');
    $source=$prepared['source'];$gateway=$prepared['gateway'];$transactionId=null;$execute??=fn(string $method,array $body,array $context):array=>hosting_anet_execute($method,$body,$context);
    try{
        $reply=$execute('createTransactionRequest',hosting_anet_charge_request($source,$descriptor,$nonce),$gateway);$transactionId=software_anet_approved($reply);
        if($transactionId===null){$state=(string)($reply['transactionResponse']['responseCode']??'')==='2'?'declined':'review';hosting_payment_mark($request,$attemptKey,$state);return $state;}
        $details=$execute('getTransactionDetailsRequest',['transId'=>$transactionId],$gateway);$readback=hosting_anet_readback($details,$transactionId,$source);
        if($gateway['mode']==='sandbox'){hosting_payment_mark($request,$attemptKey,'sandbox_test',$transactionId);return 'sandbox';}
        hosting_payment_record_paid($request,$client,$attemptKey,$transactionId,$readback,$gateway);return 'paid';
    }catch(Throwable $e){
        try{hosting_payment_mark($request,$attemptKey,'uncertain',is_string($transactionId)&&preg_match('/^[0-9]{5,30}$/D',$transactionId)?$transactionId:null);}catch(Throwable $markError){report_error($markError);}
        report_error($e);return 'review';
    }
}
/** Owner-only uncertain-outcome reconciliation; sandbox readback never settles an invoice. */
function hosting_payment_reconcile(string $request,string $transactionId,#[\SensitiveParameter]string $password,?callable $execute=null):string {
    if($execute!==null&&PHP_SAPI!=='cli')throw new LogicException('Synthetic hosting reconciliation transport is CLI-only.');
    service_plans_id($request);if(!preg_match('/^[0-9]{5,30}$/D',$transactionId))throw new InvalidArgumentException('Enter a verified Authorize.Net transaction ID.');
    if(support_current())throw new RuntimeException('Hosting payment reconciliation is unavailable during support access.');
    $actor=payments_owner($password);$snapshot=staff_locked(fn()=>staff_transaction(function()use($request):array {
        hosting_payments_require();$charge=one('SELECT * FROM '.table('hosting_payment_charges').' WHERE request_id=? FOR UPDATE',[$request]);
        if(!$charge)throw new RuntimeException('Hosting payment history is missing.');
        if($charge['state']==='paid')return ['paid'=>true,'charge'=>$charge];
        if(!in_array($charge['state'],['processing','uncertain','review'],true))throw new RuntimeException('This hosting payment state cannot be reconciled as paid.');
        $source=hosting_payment_source_locked($request,(int)$charge['client_id'],false);return ['paid'=>false,'charge'=>$charge,'source'=>$source];
    }));
    if($snapshot['paid']){if((string)$snapshot['charge']['transaction_id']!==$transactionId)throw new RuntimeException('Paid hosting charge references a different transaction.');return 'already_paid';}
    if($snapshot['charge']['transaction_id']!==null&&!hash_equals((string)$snapshot['charge']['transaction_id'],$transactionId))throw new RuntimeException('Retained hosting charge references a different transaction.');
    $gateway=hosting_anet_settings();$retained=hosting_payment_row_context($snapshot['charge']);
    if(!hosting_anet_context_matches($retained,$gateway))throw new RuntimeException('The retained payment intent belongs to a different gateway account, environment or revision.');
    $execute??=fn(string $method,array $body,array $context):array=>hosting_anet_execute($method,$body,$context);
    $details=$execute('getTransactionDetailsRequest',['transId'=>$transactionId],$gateway);$readback=hosting_anet_readback($details,$transactionId,$snapshot['source']);
    if($gateway['mode']!=='production'){payments_audit((int)$actor['id'],'hosting_payment.reconcile.sandbox','Hosting request '.$request.' transaction '.$transactionId.' verified in sandbox; no settlement');return 'sandbox_verified';}
    hosting_payment_record_paid($request,(int)$snapshot['charge']['client_id'],(string)$snapshot['charge']['current_attempt_key'],$transactionId,$readback,$gateway);
    payments_audit((int)$actor['id'],'hosting_payment.reconcile','Hosting request '.$request.' reconciled to transaction '.$transactionId);return 'paid';
}
/** A signed capture is review evidence only; customer action or owner reconciliation performs settlement. */
function hosting_payment_webhook_capture_review(array $details,string $transactionId,array $gateway):string {
    if(!hosting_payments_ready()||!preg_match('/^[0-9]{5,30}$/D',$transactionId))return 'hosting_payment_unmatched';
    $tx=$details['transaction']??null;if(!is_array($tx)||isset($tx['subscription']))return 'hosting_payment_unmatched';
    if(!preg_match('/^CWP-([1-9][0-9]{0,14})$/D',(string)($tx['order']['invoiceNumber']??''),$match))return 'hosting_payment_unmatched';
    $charge=one('SELECT * FROM '.table('hosting_payment_charges').' WHERE invoice_id=?',[(int)$match[1]]);if(!$charge)return 'hosting_payment_unmatched';
    $snapshot=staff_locked(fn()=>staff_transaction(fn():array=>hosting_payment_source_locked((string)$charge['request_id'],(int)$charge['client_id'],false)));
    try{hosting_anet_readback($details,$transactionId,$snapshot);}catch(RuntimeException){return 'hosting_payment_mismatch';}
    $webhook=['mode'=>(string)($gateway['mode']??''),'account_sha256'=>gateway_receipt_account_hash((string)($gateway['login']??'')),'revision'=>(int)($gateway['revision']??0)];
    if(!hosting_anet_context_matches(hosting_payment_row_context($charge),$webhook))return 'hosting_payment_mismatch';
    if($charge['state']==='paid'&&(string)$charge['transaction_id']===$transactionId)return 'hosting_payment_replay';
    if(!in_array($charge['state'],['processing','uncertain','review'],true))return 'hosting_payment_replay';
    return staff_locked(fn()=>staff_transaction(function()use($charge,$transactionId,$webhook):string {
        $current=one('SELECT * FROM '.table('hosting_payment_charges').' WHERE request_id=? FOR UPDATE',[$charge['request_id']]);
        $attempt=one('SELECT * FROM '.table('hosting_payment_attempts').' WHERE attempt_key=? FOR UPDATE',[$charge['current_attempt_key']]);
        if(!$current||!$attempt||!in_array($current['state'],['processing','uncertain','review'],true)||!hash_equals($current['current_attempt_key'],$charge['current_attempt_key']))return 'hosting_payment_replay';
        if(!hosting_anet_context_matches(hosting_payment_row_context($current),$webhook)||!hosting_anet_context_matches(hosting_payment_row_context($attempt),$webhook))throw new RuntimeException('Signed webhook gateway differs from the retained hosting payment intent.');
        if(($current['transaction_id']!==null&&!hash_equals($current['transaction_id'],$transactionId))||($attempt['transaction_id']!==null&&!hash_equals($attempt['transaction_id'],$transactionId)))throw new RuntimeException('Hosting payment retains a different gateway transaction.');
        $now=gmdate('Y-m-d H:i:s');query('UPDATE '.table('hosting_payment_charges').' SET state=?,transaction_id=?,updated_at=? WHERE request_id=?',['review',$transactionId,$now,$current['request_id']]);
        query('UPDATE '.table('hosting_payment_attempts').' SET state=?,transaction_id=?,updated_at=? WHERE attempt_key=?',['review',$transactionId,$now,$current['current_attempt_key']]);
        hosting_payment_event($current['request_id'],$current['current_attempt_key'],'review',['transaction_id'=>$transactionId,'source'=>'signed-webhook']);return 'hosting_payment_review';
    }));
}
/** Refund/void notifications immediately fence fulfillment; an owner separately reconciles the invoice ledger. */
function hosting_payment_webhook_reversal_review(array $details,string $eventTransactionId,string $eventType,string $eventId,array $gateway):string {
    if(!gateway_receipts_ready())return 'hosting_reversal_unmatched';
    $environment=(string)($gateway['mode']??'');$account=gateway_receipt_account_hash((string)($gateway['login']??''));
    $reversal=software_anet_reversal_details($details,$eventTransactionId,$eventType);$original=$reversal['original_transaction_id'];
    $receipts=all('SELECT source_id FROM '.table('gateway_receipts').' WHERE environment=? AND account_sha256=? AND transaction_id=? AND source=? LIMIT 2',[$environment,$account,$original,'hosting_checkout']);
    if(!$receipts)return 'hosting_reversal_unmatched';if(count($receipts)!==1)throw new RuntimeException('Hosting gateway reversal identity is ambiguous.');$receipt=$receipts[0];
    gateway_receipt_mark_reversal_review($environment,$account,$original,$eventId===''?$eventTransactionId:$eventId);
    staff_locked(fn()=>staff_transaction(function()use($receipt,$eventTransactionId):void {
        $charge=one('SELECT * FROM '.table('hosting_payment_charges').' WHERE request_id=? FOR UPDATE',[$receipt['source_id']]);if(!$charge)return;
        query('UPDATE '.table('hosting_payment_charges').' SET state=?,updated_at=? WHERE request_id=?',['review',gmdate('Y-m-d H:i:s'),$charge['request_id']]);
        hosting_payment_event($charge['request_id'],$charge['current_attempt_key'],'reversal_review',['event_transaction_id'=>$eventTransactionId]);
    }));
    return 'hosting_reversal_review';
}
function hosting_payment_service_active_locked(string $agreement):bool {
    $marker=one('SELECT value FROM '.table('settings').' WHERE name=?',['hosting_order_links_schema_version'])['value']??'';
    if($marker!=='1')return false;$order=one('SELECT id,client_id FROM '.table('service_orders').' WHERE agreement_id=?',[$agreement]);if(!$order)return false;
    $link=one('SELECT account_id FROM '.table('hosting_order_links').' WHERE id=?',[$order['id']]);if(!$link)return false;
    require_once CWP_CODE_ROOT.'/app/hosting/operations.php';$row=hosting_records_row($link['account_id'],(int)$order['client_id']);$operations=hosting_storage_state('operations');
    if($operations['version']===''&&$operations['existing']===[]&&!one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',[$operations['setup_action']])){hosting_records_journal($row);return false;}
    hosting_storage_require('operations');$journal=hosting_operations_validate($row);
    return $journal->account()->status()==='active'&&$journal->account()->providerRef()!==null;
}
function hosting_payment_projection_locked(array $source,?bool $paymentsReady=null):array {
        $request=$source['request_id'];$paymentsReady??=hosting_payments_ready();
        $charge=$paymentsReady?one('SELECT * FROM '.table('hosting_payment_charges').' WHERE request_id=?',[$request]):null;
        $receipt=null;if($charge&&$charge['receipt_id']!==null){$payment=one('SELECT p.* FROM '.table('invoice_payments').' p JOIN '.table('gateway_receipts').' g ON g.ledger_entry_id=p.id WHERE g.id=?',[$charge['receipt_id']]);if(!$payment)throw new RuntimeException('Hosting payment receipt history is missing.');$receipt=gateway_receipt_for_entry($payment,$source['invoice']);}
        $active=$source['invoice_status']==='paid'&&$receipt&&$receipt['status']==='verified'&&hosting_payment_service_active_locked($source['agreement_id']);
        $stage=$active?'active':($source['invoice_status']==='paid'&&$receipt&&$receipt['status']==='verified'?'paid_setup_pending':($source['invoice_status']==='issued'?'awaiting_payment':'payment_review'));
        if($source['invoice_status']==='paid'&&!$receipt)$stage='payment_review';
        if(($receipt&&$receipt['status']==='reversal_review')||($charge&&in_array($charge['state'],['processing','uncertain','review'],true)))$stage='payment_review';
        return ['request_id'=>$request,'invoice_id'=>$source['invoice_id'],'invoice_status'=>$source['invoice_status'],'amount_cents'=>$source['amount_cents'],'currency'=>$source['currency'],
            'charge_state'=>$charge['state']??null,'transaction_id'=>$charge['transaction_id']??null,'receipt_id'=>$receipt['id']??null,'receipt_status'=>$receipt['status']??null,
            'payment_stage'=>$stage,'retryable'=>$source['invoice_status']==='issued'&&($charge===null||in_array($charge['state'],['declined','sandbox_test'],true)),'service_active'=>$active];
}
function hosting_payment_projection(string $request,int $client):array {
    return hosting_checkout_locked(function()use($request,$client):array {
        $source=hosting_payment_source_locked($request,$client,true);return hosting_payment_projection_locked($source);
    });
}
