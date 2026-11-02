<?php
/** Durable replacement reservations and receipt-triggered collection recovery. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/migrations/license_finance_gateway_migrate.php';

function license_finance_recovery_context(#[\SensitiveParameter]array $settings):array{
    if(!in_array($settings['mode']??null,['sandbox','production'],true)
        ||!is_string($settings['login']??null)||$settings['login']===''||(int)($settings['revision']??0)<1){
        throw new RuntimeException('Financing gateway recovery identity is unavailable.');
    }
    return ['environment'=>$settings['mode'],'account_hash'=>hash('sha256',$settings['login']),
        'gateway_revision'=>(int)$settings['revision']];
}
function license_finance_recovery_require_context(array $attempt,#[\SensitiveParameter]array $settings):void{
    $context=license_finance_recovery_context($settings);
    if($context['environment']!==$attempt['environment']||!hash_equals((string)$attempt['account_hash'],$context['account_hash'])){
        throw new RuntimeException('Restore the original financing gateway account and environment before recovery.');
    }
    // A credential rotation for this same account is allowed. Its original
    // revision remains evidence; a different merchant account is never adopted.
}
function license_finance_recovery_predecessor(array $row):array{
    return ['id'=>(int)$row['id'],'offer_id'=>(string)$row['offer_id'],
        'gateway_subscription_id'=>(string)$row['gateway_subscription_id'],
        'customer_profile_id'=>(string)$row['customer_profile_id'],'payment_profile_id'=>(string)$row['payment_profile_id'],
        'interval_months'=>(int)$row['interval_months'],'amount_cents'=>(int)$row['amount_cents'],
        'total_occurrences'=>(int)$row['total_occurrences'],'period_offset'=>(int)$row['period_offset'],
        'replacement_count'=>(int)$row['replacement_count'],'start_date'=>(string)$row['start_date']];
}
function license_finance_recovery_agreement_hash(array $offer):string{
    license_finance_offer_terms($offer);
    return hash('sha256',json_encode(array_map(static fn(string $key):string=>(string)($offer[$key]??''),
        ['id','client_id','job_id','license_id','schedule_id','terms_sha256','signature_evidence_hash','signed_at']),JSON_THROW_ON_ERROR));
}
function license_finance_recovery_open(string $offerId,bool $lock=false):?array{
    if(!license_finance_recovery_ready())return null;
    $row=one('SELECT * FROM '.table('license_finance_replacement_attempts').' WHERE open_offer_id=?'.($lock?' FOR UPDATE':''),[$offerId]);
    return $row?:null;
}
function license_finance_recovery_contract(array $attempt,array $offer,array $predecessor):array{
    if((string)$attempt['offer_id']!==(string)$offer['id']
        ||!hash_equals((string)$attempt['agreement_sha256'],license_finance_recovery_agreement_hash($offer))
        ||!hash_equals((string)$attempt['contract_sha256'],hash('sha256',(string)$attempt['contract_json']))
        ||!hash_equals((string)$attempt['predecessor_sha256'],hash('sha256',(string)$attempt['predecessor_json']))){
        throw new RuntimeException('Reserved financing replacement evidence changed. Preserve the original records.');
    }
    $contract=json_decode((string)$attempt['contract_json'],true,16,JSON_THROW_ON_ERROR);
    $old=json_decode((string)$attempt['predecessor_json'],true,16,JSON_THROW_ON_ERROR);
    if(!is_array($contract)||count($contract)!==5||$old!==license_finance_recovery_predecessor($predecessor)){
        throw new RuntimeException('Reserved financing replacement no longer matches its signed contract or predecessor.');
    }
    foreach(license_finance_subscription_signed_remaining_contract($offer,(int)($contract['period_offset']??-1)) as $field=>$expected){
        if(($contract[$field]??null)!==$expected)throw new RuntimeException('Reserved replacement differs from its signed installment contract.');
    }
    return $contract;
}
/** Called only inside the existing replacement reservation transaction. */
function license_finance_recovery_reserve(array $offer,array $old,array $contract,#[\SensitiveParameter]array $settings):array{
    if(!db()->inTransaction())throw new LogicException('Replacement evidence must share its reservation transaction.');
    if(!license_finance_recovery_ready())throw new RuntimeException('Prepare financing recovery storage before replacing automatic billing.');
    if(license_finance_recovery_open((string)$offer['id'],true))throw new RuntimeException('A prior replacement outcome remains unresolved. Reconcile it before another create.');
    $context=license_finance_recovery_context($settings);$now=gmdate('Y-m-d H:i:s');$id=bin2hex(random_bytes(16));
    $contractJson=json_encode($contract,JSON_THROW_ON_ERROR);$oldJson=json_encode(license_finance_recovery_predecessor($old),JSON_THROW_ON_ERROR);
    query('INSERT INTO '.table('license_finance_replacement_attempts').' (id,offer_id,open_offer_id,old_gateway_subscription_id,contract_json,contract_sha256,predecessor_json,predecessor_sha256,agreement_sha256,environment,account_hash,gateway_revision,state,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$id,$offer['id'],$offer['id'],$old['gateway_subscription_id'],$contractJson,hash('sha256',$contractJson),$oldJson,hash('sha256',$oldJson),
         license_finance_recovery_agreement_hash($offer),$context['environment'],$context['account_hash'],$context['gateway_revision'],'prepared',$now,$now]);
    license_audit(0,'financing.replacement.reserved','Finance agreement '.$offer['id'].' replacement reservation '.$id.' retains predecessor '.$old['gateway_subscription_id'].' and signed remaining contract');
    return license_finance_recovery_open((string)$offer['id'],true);
}
/** Commit the dispatch boundary before I/O, or prove that no create was sent. */
function license_finance_recovery_dispatch(string $offerId,string $oldId,string $terminal,#[\SensitiveParameter]array $settings):bool{
    if(!in_array($terminal,['canceled','expired','terminated'],true))throw new RuntimeException('Replacement predecessor is not terminal.');
    return staff_locked(fn()=>staff_transaction(function()use($offerId,$oldId,$terminal,$settings):bool{
        $row=license_finance_subscription_row($offerId,true);$attempt=license_finance_recovery_open($offerId,true);
        if(!$row||!$attempt||$attempt['state']!=='prepared'||$attempt['dispatched_at']!==null
            ||(string)$attempt['old_gateway_subscription_id']!==$oldId||(string)$row['gateway_subscription_id']!==$oldId){
            throw new RuntimeException('Financing replacement dispatch reservation changed.');
        }
        license_finance_recovery_require_context($attempt,$settings);
        $offer=license_finance_offer_row($offerId,null,true);$contract=license_finance_recovery_contract($attempt,$offer,$row);
        $paidOff=license_finance_subscription_payoff_due($offer,true);$eligible=!$paidOff&&(int)$attempt['retirement_required']===0;
        if($eligible)try{$eligible=license_finance_subscription_replacement_contract($offer)===$contract;}catch(RuntimeException){$eligible=false;}
        $now=gmdate('Y-m-d H:i:s');
        if(!$eligible){
            query('UPDATE '.table('license_finance_replacement_attempts').' SET state=?,open_offer_id=NULL,predecessor_terminal_status=?,retirement_required=1,updated_at=? WHERE id=?',
                ['not_dispatched',$terminal,$now,$attempt['id']]);
            query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE id=?',[$paidOff?'paid_off':'billing_suspended',$now,$row['id']]);
            license_audit(0,'financing.replacement.not_dispatched','Finance agreement '.$offerId.' reservation '.$attempt['id'].' closed before any create; predecessor verified '.$terminal);
            return false;
        }
        query('UPDATE '.table('license_finance_replacement_attempts').' SET state=?,predecessor_terminal_status=?,dispatched_at=?,updated_at=? WHERE id=?',
            ['dispatching',$terminal,$now,$now,$attempt['id']]);
        license_audit(0,'financing.replacement.dispatch','Finance agreement '.$offerId.' reservation '.$attempt['id'].' admitted one replacement create');
        return true;
    }));
}
/** Retain a known create result independently of later readback/finalization. */
function license_finance_recovery_observe(string $offerId,string $oldId,string $newId,#[\SensitiveParameter]?string $password=null):void{
    if(!preg_match('/^[0-9]{1,25}$/D',$newId)||$newId===$oldId)throw new RuntimeException('Invalid replacement candidate identity.');
    staff_locked(fn()=>staff_transaction(function()use($offerId,$oldId,$newId,$password):void{
        if($password!==null)staff_owner($password);
        $row=license_finance_subscription_row($offerId,true);$attempt=license_finance_recovery_open($offerId,true);
        if(!$row||!$attempt||(string)$row['gateway_subscription_id']!==$oldId
            ||(string)$attempt['old_gateway_subscription_id']!==$oldId||$attempt['dispatched_at']===null){
            throw new RuntimeException('Replacement create reservation is unavailable.');
        }
        license_finance_recovery_contract($attempt,license_finance_offer_row($offerId,null,true),$row);
        if($attempt['gateway_subscription_id']!==null){
            if((string)$attempt['gateway_subscription_id']!==$newId)throw new RuntimeException('Replacement reservation retains another candidate.');
            return;
        }
        if(license_finance_subscription_known_gateway_id($newId)
            ||one('SELECT id FROM '.table('license_finance_replacement_attempts').' WHERE gateway_subscription_id=? FOR UPDATE',[$newId])){
            throw new RuntimeException('Replacement candidate is already assigned.');
        }
        query('UPDATE '.table('license_finance_replacement_attempts').' SET gateway_subscription_id=?,state=?,updated_at=? WHERE id=?',
            [$newId,'observed',gmdate('Y-m-d H:i:s'),$attempt['id']]);
        license_audit(0,'financing.replacement.observed','Finance agreement '.$offerId.' reservation '.$attempt['id'].' retained candidate '.$newId);
    }));
}
/** An unknown successor stays open even when its predecessor is paid off. */
function license_finance_recovery_review(string $offerId,string $oldId):void{
    if(!db()->inTransaction())throw new LogicException('Replacement review must share its state transaction.');
    $attempt=license_finance_recovery_open($offerId,true);if(!$attempt)return;
    if((string)$attempt['old_gateway_subscription_id']!==$oldId)throw new RuntimeException('Replacement review identity changed.');
    $sent=$attempt['dispatched_at']!==null;$state=$sent?($attempt['gateway_subscription_id']===null?'unknown':'observed'):'not_dispatched';
    query('UPDATE '.table('license_finance_replacement_attempts').' SET state=?,open_offer_id=?,updated_at=? WHERE id=?',
        [$state,$sent?$offerId:null,gmdate('Y-m-d H:i:s'),$attempt['id']]);
    license_audit(0,'financing.replacement.review','Finance agreement '.$offerId.' reservation '.$attempt['id'].' state '.$state);
}
function license_finance_recovery_subscription_settings(array $sub):array{
    $settings=license_finance_anet_settings();
    if(license_finance_recovery_ready()){
        $attempt=one('SELECT * FROM '.table('license_finance_replacement_attempts').' WHERE gateway_subscription_id=?',[$sub['gateway_subscription_id']]);
        $attempt??=license_finance_recovery_open((string)$sub['offer_id']);
        if($attempt)license_finance_recovery_require_context($attempt,$settings);
    }
    return $settings;
}
/** Owner input, or an already retained candidate, never dispatches another create. */
function license_finance_recovery_reconcile(string $offerId,string $gatewayId,#[\SensitiveParameter]?string $password=null):string{
    if(db()->inTransaction())throw new LogicException('Replacement recovery cannot inherit a transaction.');
    $reserved=staff_locked(fn()=>staff_transaction(function()use($offerId,$gatewayId,$password):array{
        if($password!==null)staff_owner($password);
        $attempt=license_finance_recovery_open($offerId,true);$row=license_finance_subscription_row($offerId,true);
        if(!$attempt||!$row||$attempt['dispatched_at']===null){
            throw new RuntimeException('This older ambiguous replacement has no retained original contract. Preserve its records and reconcile the merchant account manually; no subscription was adopted.');
        }
        if($gatewayId===(string)$row['gateway_subscription_id']
            ||($attempt['gateway_subscription_id']!==null&&(string)$attempt['gateway_subscription_id']!==$gatewayId)){
            throw new RuntimeException('Recovery must use the existing successor retained for this reservation.');
        }
        $offer=license_finance_offer_row($offerId,null,true);
        return ['attempt'=>$attempt,'row'=>$row,'contract'=>license_finance_recovery_contract($attempt,$offer,$row)];
    }));
    $attempt=$reserved['attempt'];$row=$reserved['row'];$contract=$reserved['contract'];
    $settings=license_finance_anet_settings();license_finance_recovery_require_context($attempt,$settings);
    $execute=fn(string $method,array $request):array=>license_finance_anet_execute($method,$request,$settings);
    $details=$execute('ARBGetSubscriptionRequest',['subscriptionId'=>$gatewayId,'includeTransactions'=>false]);
    $verified=license_finance_anet_subscription_readback($details,$offerId,$gatewayId,$contract['amount_cents'],$contract['months'],$contract['occurrences'],$contract['start_date']);
    if($settings['mode']!=='production'){
        license_audit(0,'financing.subscription.reconcile.sandbox','Sandbox replacement '.$gatewayId.' verified against reservation '.$attempt['id'].'; retained identities unchanged');
        return 'sandbox_verified';
    }
    // Observation commits before later cancellation, binding, and strict audits.
    // A failure in any of those operations cannot erase this external identity.
    license_finance_recovery_observe($offerId,(string)$row['gateway_subscription_id'],$gatewayId,$password);
    $profile=$details['subscription']['profile']??[];$profiles=['customer_profile_id'=>'','payment_profile_id'=>''];
    if(is_array($profile)&&preg_match('/^[0-9]{1,20}$/D',(string)($profile['customerProfileId']??''))
        &&preg_match('/^[0-9]{1,20}$/D',(string)($profile['customerPaymentProfileId']??''))){
        $profiles=['customer_profile_id'=>(string)$profile['customerProfileId'],'payment_profile_id'=>(string)$profile['customerPaymentProfileId']];
    }
    $terminal=license_finance_anet_retire_subscription($offerId,$row,$execute);
    return license_finance_subscription_replacement_finalize($offerId,(string)$row['gateway_subscription_id'],$gatewayId,$contract,$profiles,
        $verified['status']==='active'?'pending_first_payment':'retirement_pending',$terminal,$password);
}
/** A real receipt and its obsolete-contract hold commit or roll back together. */
function license_finance_recovery_receipt(int $invoiceId,int $entryId,int $actor):void{
    if(!db()->inTransaction())throw new LogicException('Financing receipt recovery must share the receipt transaction.');
    if(!license_finance_gateway_ready()||!license_finance_offers_ready())return;
    require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
    $row=one('SELECT s.*,r.period_index FROM '.table('recurring_billing_invoices').' r JOIN '.table('license_finance_offers').
        ' o ON o.schedule_id=r.schedule_id JOIN '.table('license_finance_subscriptions').' s ON s.offer_id=o.id WHERE r.invoice_id=? AND o.status=? FOR UPDATE',[$invoiceId,'signed']);
    if(!$row||$row['gateway_subscription_id']===null||in_array((string)$row['status'],['paid_off','cancelled','sandbox_test'],true))return;
    $period=(int)$row['period_index'];$offset=(int)$row['period_offset'];
    if($period<$offset||$period>=$offset+(int)$row['total_occurrences'])return;
    // Repaying an already collected, subsequently reversed installment does
    // not reduce a future scheduled charge. Keep that established recovery path.
    if(one('SELECT id FROM '.table('license_finance_subscription_receipts').' WHERE offer_id=? AND period_index=? FOR UPDATE',[$row['offer_id'],$period]))return;
    license_finance_offer_row((string)$row['offer_id'],null,true);
    $attempt=license_finance_recovery_open((string)$row['offer_id'],true);$now=gmdate('Y-m-d H:i:s');
    if($attempt){
        query('UPDATE '.table('license_finance_replacement_attempts').' SET retirement_required=1,updated_at=? WHERE id=?',[$now,$attempt['id']]);
        // A create may already be in flight. Preserve its predecessor and
        // reservation; its finalizer must retain and retire any successor.
    }else{
        query('UPDATE '.table('license_finance_subscriptions').' SET status=?,updated_at=? WHERE id=?',['retirement_pending',$now,$row['id']]);
    }
    license_audit($actor,'financing.subscription.receipt.hold','Finance agreement '.$row['offer_id'].' receipt '.$entryId.' invoice '.$invoiceId.
        ' changes covered period '.$period.'; exact subscription '.$row['gateway_subscription_id'].' requires verified retirement'.($attempt?'; replacement reservation '.$attempt['id']:''));
}
/** Fresh serialized admission also fences previously prepared payment links. */
function license_finance_recovery_charge_guard(array $offer,int $invoiceId):void{
    if(!db()->inTransaction())throw new LogicException('Financing charge admission requires the financial transaction.');
    if((int)($offer['activation_invoice_id']??0)===$invoiceId)return;
    $sub=license_finance_subscription_row((string)$offer['id'],true);
    if(license_finance_recovery_open((string)$offer['id'],true)
        ||($sub&&!license_financing_recovery_link_allowed((string)$sub['status']))){
        throw new RuntimeException('Resolve the existing automatic financing contract before a separate installment charge.');
    }
}
