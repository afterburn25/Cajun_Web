<?php
/** Finite Authorize.Net recurring enrollment and settlement for signed website financing. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/migrations/license_finance_gateway_migrate.php';
require_once CWP_CODE_ROOT.'/app/billing/finance_authorize_net.php';
require_once CWP_CODE_ROOT.'/app/licenses/finance_installments.php';

function license_finance_subscription_contract(array $offer):array{
    $terms=license_finance_offer_terms($offer);
    $months=license_finance_cadence_months((string)$terms['cadence']);
    $amount=(int)$terms['installment_amount_cents'];$occurrences=(int)$offer['installments'];$start=(string)$terms['anchor'];
    if($amount<1||$occurrences<1||$occurrences>360||$start<=gmdate('Y-m-d')){
        throw new RuntimeException('Financing recurring schedule is no longer enrollable. Reissue the agreement with a future start date.');
    }
    return ['months'=>$months,'amount_cents'=>$amount,'occurrences'=>$occurrences,'start_date'=>$start,'period_offset'=>0];
}
function license_finance_subscription_replacement_contract(array $offer):array{
    if((string)($offer['status']??'')!=='signed')throw new RuntimeException('Finance agreement must be signed first.');
    $schedule=recurring_billing_row((string)$offer['schedule_id']);
    $doc=recurring_billing_validate($schedule);
    $terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);
    $installments=(int)$offer['installments'];
    if($installments<1||$installments>360)throw new RuntimeException('Signed installment count is invalid.');
    $offset=null;
    for($period=0;$period<$installments;$period++){
        $map=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? AND period_index=?',
            [$offer['schedule_id'],$period]);
        if(!$map){$offset=$period;break;}
        $invoice=recurring_billing_invoice_validate($map,$doc);$balance=ledger_balance($invoice);
        if($invoice['status']==='paid'&&(int)$balance['due_cents']===0)continue;
        $offset=$period;break;
    }
    if($offset===null)throw new RuntimeException('All signed financing installments are already paid.');
    $period=$terms->period($offset);$start=(string)$period['start'];
    if($start<=gmdate('Y-m-d')){
        throw new RuntimeException('Pay every currently due financing installment before restoring automatic billing.');
    }
    $base=license_finance_subscription_contract($offer);
    $base['occurrences']=$installments-$offset;
    $base['start_date']=$start;
    $base['period_offset']=$offset;
    return $base;
}
function license_finance_subscription_history_row(string $gatewayId):?array{
    if(!preg_match('/^[0-9]{1,25}$/D',$gatewayId))return null;
    $row=one('SELECT * FROM '.table('license_finance_subscription_history').' WHERE gateway_subscription_id=?',[$gatewayId]);
    return $row?:null;
}

function license_finance_subscription_row(string $offerId,bool $lock=false):?array{
    if(!license_finance_gateway_ready())return null;
    $row=one('SELECT * FROM '.table('license_finance_subscriptions').' WHERE offer_id=?'.($lock?' FOR UPDATE':''),[$offerId]);
    return $row?:null;
}
function license_finance_subscription_mark_review(string $offerId,?string $gatewayId=null):void{
    if(!license_finance_gateway_ready())return;
    staff_locked(fn()=>staff_transaction(function()use($offerId,$gatewayId):void{
        $row=license_finance_subscription_row($offerId,true);if(!$row)return;
        if($gatewayId!==null){
            if(!preg_match('/^[0-9]{1,25}$/D',$gatewayId))throw new InvalidArgumentException('Invalid financing subscription identity.');
            if($row['gateway_subscription_id']===null)query('UPDATE '.table('license_finance_subscriptions').' SET gateway_subscription_id=? WHERE id=?',[$gatewayId,$row['id']]);
            elseif((string)$row['gateway_subscription_id']!==$gatewayId)throw new RuntimeException('Financing subscription already retains a different gateway identity.');
        }
        if(!in_array((string)$row['status'],['active','cancelled','paid_off','sandbox_test'],true)){
            query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE id=?',['review',gmdate('Y-m-d H:i:s'),$row['id']]);
        }
    }));
}
function license_finance_subscription_finalize(string $offerId,string $gatewayId,array $contract,array $profiles,string $state):string{
    if(!preg_match('/^[0-9]{1,25}$/D',$gatewayId)||!in_array($state,['pending_first_payment','sandbox_test'],true)){
        throw new InvalidArgumentException('Invalid financed subscription finalization.');
    }
    return staff_locked(fn()=>staff_transaction(function()use($offerId,$gatewayId,$contract,$profiles,$state):string{
        $row=license_finance_subscription_row($offerId,true);
        if(!$row)throw new RuntimeException('Financing subscription reservation disappeared.');
        if($row['gateway_subscription_id']!==null&&(string)$row['gateway_subscription_id']!==$gatewayId){
            throw new RuntimeException('Financing subscription retains a different gateway identity.');
        }
        if(!in_array((string)$row['status'],['creating','review'],true)){
            if((string)$row['status']===$state&&(string)$row['gateway_subscription_id']===$gatewayId)return $state;
            throw new RuntimeException('Financing subscription state changed before finalization.');
        }
        $other=one('SELECT offer_id FROM '.table('license_finance_subscriptions').' WHERE gateway_subscription_id=? AND offer_id<>? FOR UPDATE',[$gatewayId,$offerId]);
        if($other)throw new RuntimeException('Gateway financing subscription is already assigned to another agreement.');
        query('UPDATE '.table('license_finance_subscriptions').' SET gateway_subscription_id=?,customer_profile_id=?,payment_profile_id=?,interval_months=?,amount_cents=?,total_occurrences=?,period_offset=?,start_date=?,status=?,updated_at=? WHERE id=?',
            [$gatewayId,$profiles['customer_profile_id']??'', $profiles['payment_profile_id']??'',$contract['months'],$contract['amount_cents'],$contract['occurrences'],
             (int)($contract['period_offset']??0),$contract['start_date'],$state,gmdate('Y-m-d H:i:s'),$row['id']]);
        license_audit(0,'financing.subscription.created','Finance agreement '.$offerId.' gateway subscription '.$gatewayId.' state '.$state);
        return $state;
    }));
}
function license_finance_subscription_reserve(array $offer,array $contract,array $profiles):void{
    staff_locked(fn()=>staff_transaction(function()use($offer,$contract,$profiles):void{
        $existing=license_finance_subscription_row((string)$offer['id'],true);
        if($existing){
            if(in_array((string)$existing['status'],['creating','review'],true))throw new RuntimeException('A financing subscription attempt already exists and must be reconciled.');
            throw new RuntimeException('This finance agreement already has a recurring payment subscription.');
        }
        $now=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('license_finance_subscriptions').' (offer_id,gateway_subscription_id,customer_profile_id,payment_profile_id,interval_months,amount_cents,total_occurrences,period_offset,replacement_count,start_date,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$offer['id'],null,$profiles['customer_profile_id']??'',$profiles['payment_profile_id']??'',$contract['months'],$contract['amount_cents'],$contract['occurrences'],
             (int)($contract['period_offset']??0),0,$contract['start_date'],'creating',$now,$now]);
    }));
}
function license_finance_subscription_create_profile(string $offerId,array $profiles):string{
    if(!license_finance_gateway_ready())throw new RuntimeException('Prepare financing gateway storage first.');
    if(!preg_match('/^[0-9]{1,20}$/D',(string)($profiles['customer_profile_id']??''))
        ||!preg_match('/^[0-9]{1,20}$/D',(string)($profiles['payment_profile_id']??''))){
        throw new InvalidArgumentException('Verified customer payment profile is required.');
    }
    $offer=license_finance_offer_row($offerId);if((string)$offer['status']!=='signed')throw new RuntimeException('Finance agreement must be signed first.');
    $contract=license_finance_subscription_contract($offer);license_finance_subscription_reserve($offer,$contract,$profiles);
    $gatewayId=null;
    try{
        $request=license_finance_anet_arb_profile_request($offerId,$contract['amount_cents'],$contract['start_date'],$contract['months'],$contract['occurrences'],
            (string)$profiles['customer_profile_id'],(string)$profiles['payment_profile_id']);
        $reply=license_finance_anet_execute('ARBCreateSubscriptionRequest',$request);
        $id=$reply['subscriptionId']??null;
        if(($reply['messages']['resultCode']??'')!=='Ok'||(!is_string($id)&&!is_int($id))||!preg_match('/^[0-9]{1,25}$/D',(string)$id)){
            license_finance_subscription_mark_review($offerId);return 'review';
        }
        $gatewayId=(string)$id;
        $details=license_finance_anet_execute('ARBGetSubscriptionRequest',['subscriptionId'=>$gatewayId,'includeTransactions'=>false]);
        license_finance_anet_subscription_readback($details,$offerId,$gatewayId,$contract['amount_cents'],$contract['months'],$contract['occurrences'],$contract['start_date']);
        $mode=license_finance_anet_settings()['mode'];
        return license_finance_subscription_finalize($offerId,$gatewayId,$contract,$profiles,$mode==='sandbox'?'sandbox_test':'pending_first_payment');
    }catch(Throwable $e){
        try{license_finance_subscription_mark_review($offerId,$gatewayId);}catch(Throwable $reviewError){report_error($reviewError);}
        report_error($e);return 'review';
    }
}
function license_finance_subscription_enroll_opaque(string $offerId,int $clientId,string $descriptor,#[\SensitiveParameter]string $nonce):string{
    if(support_current())throw new RuntimeException('Financing enrollment is unavailable during support access.');
    software_anet_nonce($descriptor,$nonce);
    $offer=license_finance_offer_row($offerId,$clientId);if((string)$offer['status']!=='signed')throw new RuntimeException('Sign the finance agreement first.');
    if((int)$offer['initial_payment_cents']!==0)throw new RuntimeException('Complete the signed agreement initial payment instead.');
    $contract=license_finance_subscription_contract($offer);$profiles=['customer_profile_id'=>'','payment_profile_id'=>''];
    license_finance_subscription_reserve($offer,$contract,$profiles);$gatewayId=null;
    try{
        $reply=license_finance_anet_execute('ARBCreateSubscriptionRequest',
            license_finance_anet_arb_opaque_request($offerId,$contract['amount_cents'],$contract['start_date'],$contract['months'],$contract['occurrences'],$descriptor,$nonce));
        $id=$reply['subscriptionId']??null;
        if(($reply['messages']['resultCode']??'')!=='Ok'||(!is_string($id)&&!is_int($id))||!preg_match('/^[0-9]{1,25}$/D',(string)$id)){
            license_finance_subscription_mark_review($offerId);return 'review';
        }
        $gatewayId=(string)$id;
        $details=license_finance_anet_execute('ARBGetSubscriptionRequest',['subscriptionId'=>$gatewayId,'includeTransactions'=>false]);
        $verified=license_finance_anet_subscription_readback($details,$offerId,$gatewayId,$contract['amount_cents'],$contract['months'],$contract['occurrences'],$contract['start_date']);
        $profile=$details['subscription']['profile']??[];
        if(is_array($profile)&&preg_match('/^[0-9]{1,20}$/D',(string)($profile['customerProfileId']??''))
            &&preg_match('/^[0-9]{1,20}$/D',(string)($profile['customerPaymentProfileId']??''))){
            $profiles=['customer_profile_id'=>(string)$profile['customerProfileId'],'payment_profile_id'=>(string)$profile['customerPaymentProfileId']];
        }
        $mode=license_finance_anet_settings()['mode'];
        return license_finance_subscription_finalize($offerId,$gatewayId,$contract,$profiles,$mode==='sandbox'?'sandbox_test':'pending_first_payment');
    }catch(Throwable $e){
        try{license_finance_subscription_mark_review($offerId,$gatewayId);}catch(Throwable $reviewError){report_error($reviewError);}
        report_error($e);return 'review';
    }
}
function license_finance_subscription_replacement_reserve(string $offerId):array{
    return staff_locked(fn()=>staff_transaction(function()use($offerId):array{
        $row=license_finance_subscription_row($offerId,true);
        if(!$row||$row['gateway_subscription_id']===null)throw new RuntimeException('Suspended financing subscription is unavailable.');
        if((string)$row['status']!=='billing_suspended')throw new RuntimeException('Automatic financing replacement is available only after confirmed billing suspension.');
        query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE id=?',
            ['replacing',gmdate('Y-m-d H:i:s'),$row['id']]);
        $row['status']='replacing';
        return $row;
    }));
}
function license_finance_subscription_replacement_review(string $offerId,string $oldGatewayId):void{
    try{
        staff_locked(fn()=>staff_transaction(function()use($offerId,$oldGatewayId):void{
            $row=license_finance_subscription_row($offerId,true);
            if(!$row||!hash_equals((string)($row['gateway_subscription_id']??''),$oldGatewayId))return;
            if((string)$row['status']==='replacing'){
                query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE id=?',
                    ['review',gmdate('Y-m-d H:i:s'),$row['id']]);
            }
        }));
    }catch(Throwable $reviewError){report_error($reviewError);}
}
function license_finance_subscription_replacement_declined(string $offerId,string $oldGatewayId):void{
    staff_locked(fn()=>staff_transaction(function()use($offerId,$oldGatewayId):void{
        $row=license_finance_subscription_row($offerId,true);
        if(!$row||!hash_equals((string)($row['gateway_subscription_id']??''),$oldGatewayId)
            ||(string)$row['status']!=='replacing')throw new RuntimeException('Financing replacement state changed.');
        query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE id=?',
            ['billing_suspended',gmdate('Y-m-d H:i:s'),$row['id']]);
    }));
}
function license_finance_subscription_replacement_finalize(string $offerId,string $oldGatewayId,string $newGatewayId,
    array $contract,array $profiles,string $state):string{
    if(!preg_match('/^[0-9]{1,25}$/D',$oldGatewayId)||!preg_match('/^[0-9]{1,25}$/D',$newGatewayId)
        ||hash_equals($oldGatewayId,$newGatewayId)||!in_array($state,['pending_first_payment','sandbox_test'],true)){
        throw new InvalidArgumentException('Invalid financing replacement subscription identity.');
    }
    return staff_locked(fn()=>staff_transaction(function()use($offerId,$oldGatewayId,$newGatewayId,$contract,$profiles,$state):string{
        $row=license_finance_subscription_row($offerId,true);
        if(!$row||!hash_equals((string)($row['gateway_subscription_id']??''),$oldGatewayId)
            ||!in_array((string)$row['status'],['replacing','review'],true)){
            throw new RuntimeException('Financing replacement reservation changed.');
        }
        if(one('SELECT offer_id FROM '.table('license_finance_subscriptions').' WHERE gateway_subscription_id=? AND offer_id<>? FOR UPDATE',
            [$newGatewayId,$offerId])
            ||one('SELECT offer_id FROM '.table('license_finance_subscription_history').' WHERE gateway_subscription_id=? FOR UPDATE',[$newGatewayId])){
            throw new RuntimeException('Replacement gateway subscription is already assigned.');
        }
        $now=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('license_finance_subscription_history').' (offer_id,gateway_subscription_id,customer_profile_id,payment_profile_id,interval_months,amount_cents,total_occurrences,period_offset,start_date,terminal_status,replaced_by_gateway_subscription_id,replaced_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$offerId,$oldGatewayId,(string)$row['customer_profile_id'],(string)$row['payment_profile_id'],
             (int)$row['interval_months'],(int)$row['amount_cents'],(int)$row['total_occurrences'],(int)$row['period_offset'],
             (string)$row['start_date'],'billing_suspended',$newGatewayId,$now]);
        query('UPDATE '.table('license_finance_subscriptions').' SET gateway_subscription_id=?,customer_profile_id=?,payment_profile_id=?,interval_months=?,amount_cents=?,total_occurrences=?,period_offset=?,replacement_count=replacement_count+1,start_date=?,status=?,updated_at=? WHERE id=?',
            [$newGatewayId,$profiles['customer_profile_id']??'', $profiles['payment_profile_id']??'',
             $contract['months'],$contract['amount_cents'],$contract['occurrences'],$contract['period_offset'],
             $contract['start_date'],$state,$now,$row['id']]);
        license_audit(0,'financing.subscription.replaced',
            'Finance agreement '.$offerId.' replaced suspended gateway subscription '.$oldGatewayId.' with '.$newGatewayId.
            '; remaining period offset '.(int)$contract['period_offset'].'; occurrences '.(int)$contract['occurrences']);
        return $state;
    }));
}
function license_finance_subscription_reenroll_opaque(string $offerId,int $clientId,string $descriptor,#[\SensitiveParameter]string $nonce):string{
    if(support_current())throw new RuntimeException('Financing re-enrollment is unavailable during support access.');
    if(!license_finance_gateway_ready())throw new RuntimeException('Prepare financing gateway storage first.');
    software_anet_nonce($descriptor,$nonce);
    $offer=license_finance_offer_row($offerId,$clientId);
    if((string)$offer['status']!=='signed')throw new RuntimeException('Finance agreement must be signed first.');
    $plan=license_financing_sync((int)$offer['license_id']);
    if(!$plan||!in_array((string)$plan['state'],['active','grace'],true)){
        throw new RuntimeException('Catch up the financing obligation before restoring automatic payments.');
    }
    $contract=license_finance_subscription_replacement_contract($offer);
    $old=license_finance_subscription_replacement_reserve($offerId);
    $oldGatewayId=(string)$old['gateway_subscription_id'];$newGatewayId=null;
    $profiles=['customer_profile_id'=>'','payment_profile_id'=>''];
    try{
        $reply=license_finance_anet_execute('ARBCreateSubscriptionRequest',
            license_finance_anet_arb_opaque_request($offerId,$contract['amount_cents'],$contract['start_date'],$contract['months'],$contract['occurrences'],$descriptor,$nonce));
        $id=$reply['subscriptionId']??null;
        if(($reply['messages']['resultCode']??'')!=='Ok'||(!is_string($id)&&!is_int($id))||!preg_match('/^[0-9]{1,25}$/D',(string)$id)){
            license_finance_subscription_replacement_declined($offerId,$oldGatewayId);
            return 'billing_suspended';
        }
        $newGatewayId=(string)$id;
        $details=license_finance_anet_execute('ARBGetSubscriptionRequest',['subscriptionId'=>$newGatewayId,'includeTransactions'=>false]);
        license_finance_anet_subscription_readback($details,$offerId,$newGatewayId,$contract['amount_cents'],$contract['months'],$contract['occurrences'],$contract['start_date']);
        $profile=$details['subscription']['profile']??[];
        if(is_array($profile)&&preg_match('/^[0-9]{1,20}$/D',(string)($profile['customerProfileId']??''))
            &&preg_match('/^[0-9]{1,20}$/D',(string)($profile['customerPaymentProfileId']??''))){
            $profiles=['customer_profile_id'=>(string)$profile['customerProfileId'],'payment_profile_id'=>(string)$profile['customerPaymentProfileId']];
        }
        $mode=license_finance_anet_settings()['mode'];
        return license_finance_subscription_replacement_finalize($offerId,$oldGatewayId,$newGatewayId,$contract,$profiles,
            $mode==='sandbox'?'sandbox_test':'pending_first_payment');
    }catch(Throwable $e){
        license_finance_subscription_replacement_review($offerId,$oldGatewayId);
        report_error($e);
        return 'review';
    }
}

function license_finance_subscription_reconcile(string $offerId,string $gatewayId,#[\SensitiveParameter]string $password):string{
    if(support_current())throw new RuntimeException('Financing subscription reconciliation is unavailable during support access.');
    if(!license_finance_gateway_ready())throw new RuntimeException('Prepare financing gateway storage first.');
    license_finance_offer_id($offerId);
    if(!preg_match('/^[0-9]{1,25}$/D',$gatewayId))throw new InvalidArgumentException('Enter the verified Authorize.Net subscription ID.');
    $actor=staff_owner($password);
    $offer=license_finance_offer_row($offerId);
    if((string)$offer['status']!=='signed')throw new RuntimeException('Finance agreement is not signed.');
    $row=license_finance_subscription_row($offerId);
    if(!$row)throw new RuntimeException('Financing subscription reservation is missing.');

    $currentId=(string)($row['gateway_subscription_id']??'');
    $local=(string)$row['status'];
    $replacement=$currentId!==''&&!hash_equals($currentId,$gatewayId);
    if($replacement){
        if(!in_array($local,['replacing','review'],true)){
            throw new RuntimeException('Recorded financing subscription differs from the reconciliation request.');
        }
        $plan=license_financing_sync((int)$offer['license_id']);
        if(!$plan||!in_array((string)$plan['state'],['active','grace'],true)){
            throw new RuntimeException('Catch up the financing obligation before reconciling a replacement subscription.');
        }
        $contract=license_finance_subscription_replacement_contract($offer);
    }else{
        if($currentId!==''&&!hash_equals($currentId,$gatewayId)){
            throw new RuntimeException('Recorded financing subscription differs from the reconciliation request.');
        }
        $contract=[
            'months'=>(int)$row['interval_months'],
            'amount_cents'=>(int)$row['amount_cents'],
            'occurrences'=>(int)$row['total_occurrences'],
            'start_date'=>(string)$row['start_date'],
            'period_offset'=>(int)$row['period_offset'],
        ];
        if($contract['months']<1||$contract['amount_cents']<1||$contract['occurrences']<1
            ||$contract['period_offset']<0||$contract['period_offset']>=(int)$offer['installments']){
            throw new RuntimeException('Retained financing subscription contract is invalid.');
        }
    }

    if(!$replacement
        &&in_array($local,['active','pending_first_payment','sandbox_test'],true)
        &&hash_equals($currentId,$gatewayId))return 'already_linked';
    if(!$replacement&&!in_array($local,['creating','replacing','review','payment_failed','billing_suspended'],true)){
        throw new RuntimeException('This financing subscription state cannot be reconciled.');
    }

    $details=license_finance_anet_execute('ARBGetSubscriptionRequest',['subscriptionId'=>$gatewayId,'includeTransactions'=>false]);
    $verified=license_finance_anet_subscription_readback(
        $details,$offerId,$gatewayId,$contract['amount_cents'],$contract['months'],$contract['occurrences'],$contract['start_date']
    );
    $profile=$details['subscription']['profile']??[];
    $profiles=[
        'customer_profile_id'=>(string)($row['customer_profile_id']??''),
        'payment_profile_id'=>(string)($row['payment_profile_id']??'')
    ];
    if(is_array($profile)&&preg_match('/^[0-9]{1,20}$/D',(string)($profile['customerProfileId']??''))
        &&preg_match('/^[0-9]{1,20}$/D',(string)($profile['customerPaymentProfileId']??''))){
        $profiles=[
            'customer_profile_id'=>(string)$profile['customerProfileId'],
            'payment_profile_id'=>(string)$profile['customerPaymentProfileId']
        ];
    }
    $mode=license_finance_anet_settings()['mode'];
    $gatewayStatus=(string)$verified['status'];

    if($replacement){
        if($mode!=='production'){
            license_audit((int)$actor['id'],'financing.subscription.reconcile.sandbox',
                'Sandbox replacement finance subscription '.$gatewayId.' verified for agreement '.$offerId.
                '; retained subscription '.$currentId.' unchanged');
            return 'sandbox_verified';
        }
        if($gatewayStatus!=='active'){
            license_finance_subscription_replacement_review($offerId,$currentId);
            license_audit((int)$actor['id'],'financing.subscription.reconcile.review',
                'Replacement finance subscription '.$gatewayId.' for agreement '.$offerId.' is '.$gatewayStatus.
                '; retained suspended subscription '.$currentId.' remains authoritative pending review');
            return 'review';
        }
        $state=license_finance_subscription_replacement_finalize(
            $offerId,$currentId,$gatewayId,$contract,$profiles,'pending_first_payment'
        );
        license_audit((int)$actor['id'],'financing.subscription.reconcile',
            'Finance agreement '.$offerId.' reconciled replacement '.$currentId.' -> '.$gatewayId.' status '.$state);
        return $state;
    }

    if($mode!=='production'){
        license_audit((int)$actor['id'],'financing.subscription.reconcile.sandbox',
            'Sandbox finance subscription '.$gatewayId.' verified for agreement '.$offerId);
        return 'sandbox_verified';
    }
    if($gatewayStatus==='active'){
        if(in_array($local,['payment_failed','billing_suspended'],true)){
            $state=$local; // gateway profile status alone is not evidence that an overdue invoice was paid.
            license_audit((int)$actor['id'],'financing.subscription.reconcile.review',
                'Finance agreement '.$offerId.' gateway subscription is active but local '.$local.
                ' remains until a payment transaction is verified');
        }elseif($local==='replacing'){
            $state='review';
            staff_locked(fn()=>staff_transaction(function()use($offerId):void{
                $current=license_finance_subscription_row($offerId,true);
                if($current&&(string)$current['status']==='replacing'){
                    query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE id=?',
                        ['review',gmdate('Y-m-d H:i:s'),$current['id']]);
                }
            }));
        }else{
            $state=license_finance_subscription_finalize($offerId,$gatewayId,$contract,$profiles,'pending_first_payment');
        }
    }else{
        $state=match($gatewayStatus){'suspended'=>'billing_suspended','expired','canceled'=>'cancelled',default=>'review'};
        staff_locked(fn()=>staff_transaction(function()use($offerId,$gatewayId,$profiles,$state):void{
            $current=license_finance_subscription_row($offerId,true);
            if(!$current)throw new RuntimeException('Financing subscription reservation disappeared.');
            query('UPDATE '.table('license_finance_subscriptions').' SET gateway_subscription_id=?,customer_profile_id=?,payment_profile_id=?,status=?,updated_at=? WHERE id=?',
                [$gatewayId,$profiles['customer_profile_id'],$profiles['payment_profile_id'],$state,gmdate('Y-m-d H:i:s'),$current['id']]);
        }));
    }
    license_audit((int)$actor['id'],'financing.subscription.reconcile',
        'Finance agreement '.$offerId.' reconciled to gateway subscription '.$gatewayId.' status '.$state);
    return $state;
}

function license_finance_subscription_charge_reconcile(string $transactionId,#[\SensitiveParameter]string $password):string{
    if(support_current())throw new RuntimeException('Financing recurring-charge reconciliation is unavailable during support access.');
    if(!license_finance_gateway_ready())throw new RuntimeException('Prepare financing gateway storage first.');
    if(!preg_match('/^[0-9]{5,30}$/D',$transactionId))throw new InvalidArgumentException('Enter a verified recurring financing transaction ID.');
    $actor=staff_owner($password);
    $details=license_finance_anet_execute('getTransactionDetailsRequest',['transId'=>$transactionId]);
    $verified=license_finance_anet_subscription_details($details,$transactionId);
    if(!one('SELECT id FROM '.table('license_finance_subscriptions').' WHERE gateway_subscription_id=?',[$verified['subscription_id']])){
        throw new RuntimeException('Verified recurring transaction is not linked to a financing agreement.');
    }
    $state=license_finance_subscription_settle($details,$transactionId);
    license_audit((int)$actor['id'],'financing.subscription.charge.reconcile','Financing recurring transaction '.$transactionId.' reconciled as '.$state);
    return $state;
}

function license_finance_subscription_lifecycle_review(string $eventType,string $gatewayId):string{
    if(!license_finance_gateway_ready()||!preg_match('/^[0-9]{1,25}$/D',$gatewayId))return 'unknown_subscription';
    return staff_locked(fn()=>staff_transaction(function()use($eventType,$gatewayId):string{
        $sub=one('SELECT * FROM '.table('license_finance_subscriptions').' WHERE gateway_subscription_id=? FOR UPDATE',[$gatewayId]);
        if(!$sub){
            if(one('SELECT id FROM '.table('license_finance_subscription_history').' WHERE gateway_subscription_id=?',[$gatewayId])){
                return 'historical_subscription';
            }
            return 'unknown_subscription';
        }
        $current=(string)$sub['status'];
        if(in_array($current,['review','paid_off'],true))return 'replay';
        $target=match($eventType){
            'net.authorize.customer.subscription.failed'=>'payment_failed',
            'net.authorize.customer.subscription.suspended'=>'billing_suspended',
            'net.authorize.customer.subscription.terminated'=>'review',
            'net.authorize.customer.subscription.cancelled',
            'net.authorize.customer.subscription.expired'=>'cancelled',
            default=>null
        };
        if($target===null)return 'ignored';
        if($current===$target)return 'replay';
        query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE id=?',[$target,gmdate('Y-m-d H:i:s'),$sub['id']]);
        license_audit(0,'financing.subscription.lifecycle','Finance agreement '.$sub['offer_id'].' gateway '.$gatewayId.' event '.$eventType.' -> '.$target);
        return 'finance_subscription_'.$target;
    }));
}
function license_finance_subscription_settle(array $details,string $transactionId):string{
    if(!license_finance_gateway_ready()||!ledger_ready())throw new RuntimeException('Financing settlement is not prepared.');
    $verified=license_finance_anet_subscription_details($details,$transactionId);
    $gateway=license_finance_anet_settings();
    $sub=one('SELECT * FROM '.table('license_finance_subscriptions').' WHERE gateway_subscription_id=?',[$verified['subscription_id']]);
    if(!$sub){
        if(license_finance_subscription_history_row((string)$verified['subscription_id']))return 'historical_subscription_transaction';
        return 'unknown_subscription';
    }
    if($gateway['mode']!=='production')return 'sandbox_finance_subscription_verified';
    $offer=license_finance_offer_row((string)$sub['offer_id']);
    try{$period=license_financing_period_from_replacement_pay_num(
        (int)$verified['pay_num'],(int)$sub['total_occurrences'],(int)$sub['period_offset'],(int)$offer['installments']
    );}
    catch(InvalidArgumentException $e){throw new RuntimeException('Verified financing subscription charge does not match the retained installment contract.',0,$e);}
    if((int)$verified['amount_cents']!==(int)$sub['amount_cents'])throw new RuntimeException('Verified financing subscription charge does not match the retained installment amount.');
    $invoiceId=null;$offerId=(string)$sub['offer_id'];
    staff_locked(function()use($transactionId,$verified,$period,$offerId,&$invoiceId):void{
        staff_transaction(function()use($transactionId,$verified,$period,$offerId,&$invoiceId):void{
            $sub=one('SELECT * FROM '.table('license_finance_subscriptions').' WHERE offer_id=? FOR UPDATE',[$offerId]);
            if(!$sub||!hash_equals((string)$sub['gateway_subscription_id'],(string)$verified['subscription_id']))throw new RuntimeException('Financing subscription identity changed.');
            $old=one('SELECT invoice_id,period_index FROM '.table('license_finance_subscription_receipts').' WHERE gateway_transaction_id=? FOR UPDATE',[$transactionId]);
            if($old){
                if((int)$old['period_index']!==$period)throw new RuntimeException('Gateway financing transaction was reused for another installment.');
                $invoiceId=(int)$old['invoice_id'];return;
            }
            $offer=license_finance_offer_row($offerId,null,true);
            if($period<0||$period>=(int)$offer['installments'])throw new RuntimeException('Replacement financing subscription maps outside the signed installment count.');
            $schedule=recurring_billing_row((string)$offer['schedule_id']);$doc=recurring_billing_validate($schedule);
            $issued=license_finance_issue_period_tx($offer,$doc,$period,time());
            if((int)($issued['invoice_id']??0)<1)throw new RuntimeException('Financing installment invoice is unavailable.');
            $invoiceId=(int)$issued['invoice_id'];$invoice=ledger_invoice($invoiceId);$balance=ledger_balance($invoice);
            if($invoice['status']!=='issued'||$balance['paid_cents']!==0||$balance['due_cents']!==(int)$sub['amount_cents']){
                throw new RuntimeException('Financing installment changed before verified gateway settlement.');
            }
            $external=hash('sha256','authorize-net|'.$transactionId);
            $entry=ledger_insert($invoice,0,'gateway',(int)$sub['amount_cents'],'authorize_net',
                'Verified financing subscription transaction '.$transactionId,gmdate('Y-m-d'),null,null,$external);
            $now=gmdate('Y-m-d H:i:s');
            query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
                ['paid','Verified financing gateway receipt '.$entry,$now,$invoiceId]);
            query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND status='prepared'",[$invoiceId]);
            query('INSERT INTO '.table('license_finance_subscription_receipts').' (offer_id,gateway_transaction_id,period_index,invoice_id,created_at) VALUES (?,?,?,?,?)',
                [$offerId,$transactionId,$period,$invoiceId,$now]);
            query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE id=?',['active',$now,$sub['id']]);
            license_audit(0,'financing.subscription.payment','Finance agreement '.$offerId.' period '.$period.' paid by gateway transaction '.$transactionId);
        });
    });
    $offer=license_finance_offer_row($offerId);$plan=license_financing_sync((int)$offer['license_id']);
    if(($plan['state']??'')==='paid_off'){
        staff_locked(fn()=>staff_transaction(function()use($offerId):void{
            query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE offer_id=?',['paid_off',gmdate('Y-m-d H:i:s'),$offerId]);
        }));
    }
    return 'finance_subscription_payment_recorded';
}
