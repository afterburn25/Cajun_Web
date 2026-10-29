<?php
/** Authorize.Net adapter for financed-site payments. Reuses protected gateway credentials; no raw card data is stored. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/payments.php';
require_once CWP_CODE_ROOT.'/app/billing/authorize_net_contract.php';
require_once CWP_CODE_ROOT.'/app/billing/authorize_net_client.php';

function license_finance_anet_production_enabled():bool{
    return defined('CWP_FINANCE_PRODUCTION_PAYMENTS')
        && CWP_FINANCE_PRODUCTION_PAYMENTS===true
        && setting('finance_gateway_production_reviewed')==='1';
}
function license_finance_anet_settings():array{
    if(!payments_ready())throw new RuntimeException('Prepare payment gateway settings first.');
    $mode=(string)setting('software_gateway_mode','disabled');
    if(!in_array($mode,['sandbox','production'],true))throw new RuntimeException('Authorize.Net checkout is disabled.');
    if($mode==='production'&&(!software_anet_production_enabled()||!license_finance_anet_production_enabled())){
        throw new RuntimeException('Production financing checkout is locked until explicit code-level and owner-reviewed activation.');
    }
    $row=one('SELECT credentials,validation FROM '.table('payment_gateways').' WHERE environment=?',[$mode]);
    if(!$row||!is_string($row['credentials'])||$row['credentials']==='')throw new RuntimeException('Save Authorize.Net credentials for this environment first.');
    $secrets=payments_unseal($mode,$row['credentials']);
    if($secrets['api_login_id']===''||$secrets['transaction_key']===''||$secrets['public_client_key']===''){
        throw new RuntimeException('Authorize.Net financing configuration is incomplete.');
    }
    return ['mode'=>$mode,'login'=>$secrets['api_login_id'],'key'=>$secrets['transaction_key'],'client_key'=>$secrets['public_client_key']];
}
function license_finance_anet_execute(string $method,array $request):array{
    $allowed=['createTransactionRequest','getTransactionDetailsRequest','ARBCreateSubscriptionRequest',
        'ARBGetSubscriptionRequest','ARBCancelSubscriptionRequest','createCustomerProfileFromTransactionRequest'];
    if(!in_array($method,$allowed,true))throw new InvalidArgumentException('Unsupported financing gateway method.');
    $cfg=license_finance_anet_settings();
    if(!function_exists('curl_init'))throw new RuntimeException('cURL is required for financing payments.');
    $url=$cfg['mode']==='sandbox'?'https://apitest.authorize.net/xml/v1/request.api':'https://api.authorize.net/xml/v1/request.api';
    $payload=json_encode([$method=>['merchantAuthentication'=>['name'=>$cfg['login'],'transactionKey'=>$cfg['key']]]+$request],JSON_THROW_ON_ERROR);
    $ch=curl_init($url);if(!$ch)throw new RuntimeException('Financing gateway transport unavailable.');
    try{
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
        $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if($status!==200||!is_string($body))throw new RuntimeException('Financing payment outcome unknown. Reconcile at Authorize.Net before another attempt.');
        $reply=json_decode(ltrim($body,"\xEF\xBB\xBF"),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($reply))throw new RuntimeException('Unrecognized financing gateway response.');
        return $reply;
    }finally{curl_close($ch);}
}
function license_finance_billing_fields(array $input):array{
    $out=[];
    foreach(['first_name'=>50,'last_name'=>50,'address'=>60,'city'=>40,'state'=>40,'zip'=>20,'country'=>60] as $key=>$limit){
        $value=$input[$key]??'';
        if(!is_string($value))throw new InvalidArgumentException('Check billing information.');
        $value=trim($value);
        if($value===''||strlen($value)>$limit||preg_match('/[\x00-\x1F\x7F]/',$value))throw new InvalidArgumentException('Complete the billing address with plain text.');
        $out[$key]=$value;
    }
    return $out;
}
function license_finance_anet_charge_request(int $invoice,int $cents,string $email,array $billing,string $descriptor,string $nonce,bool $createProfile=true):array{
    if($invoice<1||$cents<1||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid financing payment identity.');
    $billing=license_finance_billing_fields($billing);
    $transaction=[
        'transactionType'=>'authCaptureTransaction','amount'=>software_anet_amount($cents),
        'payment'=>software_anet_nonce($descriptor,$nonce),
        'order'=>['invoiceNumber'=>substr('CWP-'.$invoice,0,20),'description'=>'CajunWebPro financing payment'],
        'customer'=>['type'=>'individual','id'=>substr('CWP'.$invoice,0,20),'email'=>$email],
        'billTo'=>[
            'firstName'=>$billing['first_name'],'lastName'=>$billing['last_name'],'address'=>$billing['address'],
            'city'=>$billing['city'],'state'=>$billing['state'],'zip'=>$billing['zip'],'country'=>$billing['country']
        ]
    ];
    if($createProfile)$transaction['profile']=['createProfile'=>true];
    return ['refId'=>substr('F'.$invoice,0,20),'transactionRequest'=>$transaction];
}
function license_finance_anet_profile_ids(array $reply,array $details=[]):?array{
    $containers=[];
    foreach([$reply['profileResponse']??null,$reply['transactionResponse']['profileResponse']??null,$reply,$details['transaction']['profile']??null] as $candidate){
        if(is_array($candidate))$containers[]=$candidate;
    }
    foreach($containers as $profile){
        $customer=$profile['customerProfileId']??null;
        $payment=$profile['customerPaymentProfileId']??null;
        if($payment===null){
            $list=$profile['customerPaymentProfileIdList']['numericString']??null;
            if(is_array($list))$payment=$list[0]??null;elseif(is_string($list)||is_int($list))$payment=$list;
        }
        if((is_string($customer)||is_int($customer))&&(is_string($payment)||is_int($payment))
            &&preg_match('/^[0-9]{1,20}$/D',(string)$customer)&&preg_match('/^[0-9]{1,20}$/D',(string)$payment)){
            return ['customer_profile_id'=>(string)$customer,'payment_profile_id'=>(string)$payment];
        }
    }
    return null;
}
function license_finance_anet_profile_from_transaction(string $transactionId):?array{
    if(!preg_match('/^[0-9]{5,30}$/D',$transactionId))throw new InvalidArgumentException('Invalid financing transaction ID.');
    $reply=license_finance_anet_execute('createCustomerProfileFromTransactionRequest',['transId'=>$transactionId]);
    if(($reply['messages']['resultCode']??'')!=='Ok')return null;
    return license_finance_anet_profile_ids($reply);
}
function license_finance_cadence_months(string $cadence):int{return license_financing_cadence_months($cadence);}
function license_finance_anet_subscription_ref(string $offerId):string{return 'FIN-'.substr($offerId,0,16);}
function license_finance_anet_arb_profile_request(string $offerId,int $cents,string $start,int $months,int $occurrences,
    string $customerProfile,string $paymentProfile):array{
    license_finance_offer_id($offerId);
    if($cents<1||!in_array($months,[1,3,12],true)||$occurrences<1||$occurrences>360
        ||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$start)
        ||!checkdate((int)substr($start,5,2),(int)substr($start,8,2),(int)substr($start,0,4))
        ||$start<=gmdate('Y-m-d')||!preg_match('/^[0-9]{1,20}$/D',$customerProfile)
        ||!preg_match('/^[0-9]{1,20}$/D',$paymentProfile)){
        throw new InvalidArgumentException('Invalid financed recurring payment schedule.');
    }
    return ['refId'=>substr('F'.$offerId,0,20),'subscription'=>[
        'name'=>substr('CajunWebPro finance '.$offerId,0,50),
        'paymentSchedule'=>['interval'=>['length'=>$months,'unit'=>'months'],'startDate'=>$start,'totalOccurrences'=>$occurrences],
        'amount'=>software_anet_amount($cents),
        'profile'=>['customerProfileId'=>$customerProfile,'customerPaymentProfileId'=>$paymentProfile],
        'order'=>['invoiceNumber'=>license_finance_anet_subscription_ref($offerId),'description'=>'CajunWebPro financed website installments']
    ]];
}
function license_finance_anet_arb_opaque_request(string $offerId,int $cents,string $start,int $months,int $occurrences,
    string $descriptor,string $nonce):array{
    license_finance_offer_id($offerId);
    if($cents<1||!in_array($months,[1,3,12],true)||$occurrences<1||$occurrences>360
        ||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$start)
        ||!checkdate((int)substr($start,5,2),(int)substr($start,8,2),(int)substr($start,0,4))
        ||$start<=gmdate('Y-m-d'))throw new InvalidArgumentException('Invalid financed recurring payment schedule.');
    return ['refId'=>substr('F'.$offerId,0,20),'subscription'=>[
        'name'=>substr('CajunWebPro finance '.$offerId,0,50),
        'paymentSchedule'=>['interval'=>['length'=>$months,'unit'=>'months'],'startDate'=>$start,'totalOccurrences'=>$occurrences],
        'amount'=>software_anet_amount($cents),'payment'=>software_anet_nonce($descriptor,$nonce),
        'order'=>['invoiceNumber'=>license_finance_anet_subscription_ref($offerId),'description'=>'CajunWebPro financed website installments']
    ]];
}
function license_finance_anet_subscription_details(array $details,string $transactionId):array{
    $base=software_anet_subscription_charge_details($details,$transactionId);
    $tx=$details['transaction'];$pay=$tx['subscription']['payNum']??null;
    if(!is_string($pay)&&!is_int($pay))throw new RuntimeException('Financing subscription payment number is missing.');
    if(!preg_match('/^[1-9][0-9]{0,2}$/D',(string)$pay))throw new RuntimeException('Financing subscription payment number is invalid.');
    $base['pay_num']=(int)$pay;
    return $base;
}
function license_finance_anet_subscription_readback(array $details,string $offerId,string $subscriptionId,int $cents,
    int $months,int $occurrences,string $start):array{
    license_finance_offer_id($offerId);
    if(!preg_match('/^[0-9]{1,25}$/D',$subscriptionId)||$cents<1||!in_array($months,[1,3,12],true)
        ||$occurrences<1||$occurrences>360)throw new InvalidArgumentException('Invalid expected financed subscription identity.');
    $sub=$details['subscription']??null;
    if(($details['messages']['resultCode']??'')!=='Ok'||!is_array($sub))throw new RuntimeException('Financing subscription readback was not successful.');
    $status=(string)($sub['status']??'');
    if(!in_array($status,['active','expired','suspended','canceled','terminated'],true))throw new RuntimeException('Financing subscription status is unrecognized.');
    $schedule=$sub['paymentSchedule']??null;$interval=is_array($schedule)?($schedule['interval']??null):null;
    if((string)($sub['name']??'')!==substr('CajunWebPro finance '.$offerId,0,50)
        ||software_charge_exact_cents($sub['amount']??null)!==$cents
        ||(string)(is_array($interval)?($interval['length']??''):'')!==(string)$months
        ||(string)(is_array($interval)?($interval['unit']??''):'')!=='months'
        ||(string)(is_array($schedule)?($schedule['totalOccurrences']??''):'')!==(string)$occurrences
        ||(string)(is_array($schedule)?($schedule['startDate']??''):'')!==$start
        ||(string)($sub['order']['invoiceNumber']??'')!==license_finance_anet_subscription_ref($offerId)){
        throw new RuntimeException('Gateway financing subscription does not match the signed agreement.');
    }
    return ['status'=>$status,'subscription_id'=>$subscriptionId];
}

/** Confirm the old ARB can never collect again before a replacement is admitted. */
function license_finance_anet_retire_subscription(string $offerId,array $old,?callable $execute=null):string{
    $execute??=fn(string $method,array $request):array=>license_finance_anet_execute($method,$request);
    $id=(string)($old['gateway_subscription_id']??'');
    $read=function()use($execute,$offerId,$old,$id):array{
        $details=$execute('ARBGetSubscriptionRequest',['subscriptionId'=>$id,'includeTransactions'=>false]);
        return license_finance_anet_subscription_readback($details,$offerId,$id,(int)$old['amount_cents'],
            (int)$old['interval_months'],(int)$old['total_occurrences'],(string)$old['start_date']);
    };
    $status=(string)$read()['status'];
    if($status==='suspended'){
        $reply=$execute('ARBCancelSubscriptionRequest',['subscriptionId'=>$id]);
        if(($reply['messages']['resultCode']??'')!=='Ok')throw new RuntimeException('The prior financing subscription could not be retired. Reconcile it before replacing billing.');
        $status=(string)$read()['status'];
    }
    if(!in_array($status,['canceled','expired','terminated'],true)){
        throw new RuntimeException('The prior financing subscription may still collect payments. Reconcile it before replacing billing.');
    }
    return $status;
}
