<?php
/** ARB registration reserves one durable attempt, never issues a paid license. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/software_charge_guard.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/billing/software_subscriptions.php';

function software_subscription_enroll_mark_review(int $invoice,?string $gatewayId=null):void{
    if($invoice<1||($gatewayId!==null&&!preg_match('/^[0-9]{1,25}$/D',$gatewayId))){
        throw new InvalidArgumentException('Invalid uncertain subscription identity.');
    }
    if(db()->inTransaction())throw new LogicException('Subscription enrollment review marker owns its transaction.');
    db()->beginTransaction();
    try{
        $sub=one('SELECT id,status,gateway_subscription_id FROM '.table('software_subscriptions').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        if(!$sub){db()->commit();return;}
        if(!in_array((string)$sub['status'],['creating','review'],true)){db()->commit();return;}
        if($gatewayId!==null){
            if($sub['gateway_subscription_id']===null){
                $other=one('SELECT invoice_id FROM '.table('software_subscriptions').' WHERE gateway_subscription_id=? AND invoice_id<>? FOR UPDATE',
                    [$gatewayId,$invoice]);
                if($other)throw new RuntimeException('Gateway subscription id is already assigned to another software invoice.');
                query('UPDATE '.table('software_subscriptions').' SET gateway_subscription_id=? WHERE id=?',
                    [$gatewayId,$sub['id']]);
            }elseif((string)$sub['gateway_subscription_id']!==$gatewayId){
                throw new RuntimeException('Local subscription already references a different gateway subscription.');
            }
        }
        query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['review',$sub['id']]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function software_subscription_enroll_finalize(int $invoice,string $gatewayId,string $state):string{
    if(!preg_match('/^[0-9]{1,25}$/D',$gatewayId)
        ||!in_array($state,['sandbox_test','pending_first_payment'],true)){
        throw new InvalidArgumentException('Invalid subscription enrollment result.');
    }
    if(db()->inTransaction())throw new LogicException('Subscription enrollment finalization owns its transaction.');
    db()->beginTransaction();
    try{
        $stored=one('SELECT id,status,gateway_subscription_id FROM '.table('software_subscriptions').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        if(!$stored)throw new RuntimeException('Subscription enrollment record disappeared after gateway creation.');
        if((string)$stored['status']!=='creating'){
            if(($stored['gateway_subscription_id']??null)===null){
                query('UPDATE '.table('software_subscriptions').' SET gateway_subscription_id=? WHERE id=?',
                    [$gatewayId,$stored['id']]);
            }elseif((string)$stored['gateway_subscription_id']!==$gatewayId){
                throw new RuntimeException('Local subscription state references a different gateway subscription.');
            }
            db()->commit();
            return 'review';
        }
        query('UPDATE '.table('software_subscriptions').' SET gateway_subscription_id=?,status=? WHERE id=?',
            [$gatewayId,$state,$stored['id']]);
        db()->commit();
        return $state;
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        throw $e;
    }
}
function software_subscription_reconcile_finalize(int $invoice,string $gatewayId,string $gatewayStatus,
    bool $sandbox,#[\SensitiveParameter]string $password):string{
    if($invoice<1||!preg_match('/^[0-9]{1,25}$/D',$gatewayId)
        ||!in_array($gatewayStatus,['active','expired','suspended','canceled','terminated'],true)){
        throw new InvalidArgumentException('Invalid reconciled subscription state.');
    }
    $target=match($gatewayStatus){
        'active'=>$sandbox?'sandbox_test':'pending_first_payment',
        'expired','canceled'=>'cancelled',
        'suspended','terminated'=>'review',
    };
    if(db()->inTransaction())throw new LogicException('Subscription reconciliation finalization owns its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($invoice,$gatewayId,$gatewayStatus,$sandbox,$password,$target):string{
        $actor=staff_owner($password);
        $actorId=(int)$actor['id'];
        $sub=one('SELECT * FROM '.table('software_subscriptions').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        if(!$sub)throw new RuntimeException('Subscription enrollment history is missing.');
        if($sub['gateway_subscription_id']!==null&&(string)$sub['gateway_subscription_id']!==$gatewayId){
            throw new RuntimeException('Local subscription references a different gateway subscription.');
        }
        $other=one('SELECT invoice_id FROM '.table('software_subscriptions').' WHERE gateway_subscription_id=? AND invoice_id<>? FOR UPDATE',
            [$gatewayId,$invoice]);
        if($other)throw new RuntimeException('Gateway subscription is already assigned to another software invoice.');
        if(in_array((string)$sub['status'],['pending_first_payment','sandbox_test','active','cancelled'],true)){
            if((string)($sub['gateway_subscription_id']??'')!==$gatewayId){
                throw new RuntimeException('Finalized local subscription is missing its expected gateway identity.');
            }
            $local=(string)$sub['status'];
            $resolution=software_subscription_finalized_reconcile_state($local,$gatewayStatus,$sandbox);
            if($resolution==='same')return 'already_linked';
            if($resolution==='cancelled'){
                query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['cancelled',$sub['id']]);
                license_audit($actorId,'subscription.enroll.reconcile',
                    'Software invoice '.$invoice.' reconciled finalized subscription '.$gatewayId.
                    ' from local '.$local.' to cancelled after gateway '.$gatewayStatus);
                return 'cancelled';
            }
            query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['review',$sub['id']]);
            license_audit($actorId,'subscription.enroll.reconcile.review',
                'Software invoice '.$invoice.' finalized local state '.$local.
                ' disagrees with gateway subscription '.$gatewayId.' status '.$gatewayStatus);
            return 'review';
        }
        if(!in_array((string)$sub['status'],['creating','review'],true)){
            throw new RuntimeException('This subscription state cannot be reconciled from enrollment.');
        }
        $order=one('SELECT client_id,billing_term FROM '.table('software_orders').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        $i=ledger_invoice($invoice);$balance=ledger_balance($i);
        $expectedMonths=($order['billing_term']??'')==='year'?12:(($order['billing_term']??'')==='month'?1:0);
        if(!$order||(int)$order['client_id']!==(int)$sub['client_id']||(int)$i['client_id']!==(int)$sub['client_id']
            ||$expectedMonths!==(int)$sub['interval_months']
            ||$i['status']!=='issued'||$balance['paid_cents']!==0
            ||$balance['due_cents']!==(int)$sub['amount_cents']){
            throw new RuntimeException('Subscription order or first invoice changed before reconciliation.');
        }
        query('UPDATE '.table('software_subscriptions').' SET gateway_subscription_id=?,status=? WHERE id=?',
            [$gatewayId,$target,$sub['id']]);
        license_audit($actorId,'subscription.enroll.reconcile',
            'Software invoice '.$invoice.' reconciled to gateway subscription '.$gatewayId.' status '.$gatewayStatus.
            ($sandbox?' (sandbox only)':''));
        return $target;
    }));
}

/** Owner recovery when ARB creation succeeded but the local subscription id/state was not retained. */
function software_subscription_enroll_reconcile(int $invoice,string $gatewayId,#[\SensitiveParameter]string $password):string{
    if(support_current())throw new RuntimeException('Subscription reconciliation is unavailable during support access.');
    if(!software_commerce_ready()||!ledger_ready())throw new RuntimeException('Prepare software payments and billing first.');
    if($invoice<1||!preg_match('/^[0-9]{1,25}$/D',$gatewayId)){
        throw new InvalidArgumentException('Enter the software invoice and verified gateway subscription ID.');
    }
    staff_require('staff.manage');
    staff_owner($password); // authenticate before gateway lookup
    $sub=one('SELECT * FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice]);
    if(!$sub)throw new RuntimeException('Subscription enrollment history is missing.');
    if(!in_array((string)$sub['status'],['creating','review','pending_first_payment','sandbox_test','active','cancelled'],true)){
        throw new RuntimeException('This subscription state cannot be reconciled from enrollment.');
    }
    if($sub['gateway_subscription_id']!==null&&(string)$sub['gateway_subscription_id']!==$gatewayId){
        throw new RuntimeException('Local subscription references a different gateway subscription.');
    }
    $created=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',(string)$sub['created_at'],new DateTimeZone('UTC'));
    $errors=DateTimeImmutable::getLastErrors();
    if($created===false||($errors!==false&&($errors['warning_count']||$errors['error_count']))){
        throw new RuntimeException('Local subscription creation timestamp is invalid.');
    }
    $gateway=software_anet_settings();
    $details=software_anet_execute('ARBGetSubscriptionRequest',[
        'subscriptionId'=>$gatewayId,'includeTransactions'=>false
    ]);
    $gatewayStart=(string)($details['subscription']['paymentSchedule']['startDate']??'');
    $allowedStarts=[
        $created->modify('+2 days')->format('Y-m-d'),
        $created->modify('+3 days')->format('Y-m-d'),
    ];
    if(!in_array($gatewayStart,$allowedStarts,true)){
        throw new RuntimeException('Gateway subscription start date is outside the original enrollment window.');
    }
    $profile=software_anet_subscription_profile_details(
        $details,$gatewayId,$invoice,(int)$sub['amount_cents'],(int)$sub['interval_months'],$gatewayStart
    );
    return software_subscription_reconcile_finalize(
        $invoice,$gatewayId,(string)$profile['status'],$gateway['mode']!=='production',$password
    );
}

function software_subscription_enroll(array $client,int $invoice,string $descriptor,#[\SensitiveParameter]string $nonce):string{
    if(support_current())throw new RuntimeException('Subscription changes are blocked in support mode.');
    if(!software_commerce_ready()||!ledger_ready())throw new RuntimeException('Prepare software payments and billing first.');
    software_anet_nonce($descriptor,$nonce); // malformed local input must not consume the one allowed enrollment attempt
    $gateway=software_anet_settings();
    if(db()->inTransaction())throw new LogicException('Subscription enrollment must own its transaction.');
    db()->beginTransaction();
    try{
        $order=one('SELECT * FROM '.table('software_orders').' WHERE invoice_id=? AND client_id=? FOR UPDATE',[$invoice,(int)$client['id']]);
        if(!$order||!in_array($order['billing_term'],['month','year'],true))
            throw new RuntimeException('Only your approved monthly or annual software order can create a subscription.');
        $policy=software_order_policy_snapshot($order);
        if(!software_dependencies_satisfied((int)$client['id'],$policy['dependency_skus'])){
            throw new RuntimeException('Required software dependencies are no longer licensed. Review the subscription before enrollment.');
        }
        $i=ledger_invoice($invoice);$balance=ledger_balance($i);
        if($i['status']!=='issued'||$balance['paid_cents']!==0||$balance['due_cents']!==(int)$i['amount_cents'])
            throw new RuntimeException('Subscription invoice has changed; review it again.');
        $quote=json_decode($order['snapshot'],true,20,JSON_THROW_ON_ERROR);
        if(!is_array($quote)||($quote['sku']??null)!==$order['sku']||(int)($quote['total_cents']??0)!==(int)$i['amount_cents']
            ||(int)($quote['setup_cents']??1)!==0)throw new RuntimeException('Subscription must have a zero setup fee and exact invoice amount.');
        if(one('SELECT id FROM '.table('software_subscriptions').' WHERE invoice_id=? FOR UPDATE',[$invoice]))
            throw new RuntimeException('A subscription has already been attempted. Reconcile that attempt before retrying.');
        $now=gmdate('Y-m-d H:i:s');$months=$order['billing_term']==='year'?12:1;
        query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?)',
            [$invoice,(int)$client['id'],$months,(int)$i['amount_cents'],'creating',$order['created_at'],$now]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    $approvedId=null;
    try{
        // ARB is not an immediate charge: the first payment is scheduled later.
        $start=gmdate('Y-m-d',time()+2*86400);
        $request=software_anet_arb_opaque($invoice,(int)$i['amount_cents'],$months,$start,$descriptor,$nonce);
        $response=software_anet_execute('ARBCreateSubscriptionRequest',$request);
        $id=$response['subscriptionId']??null;
        if(($response['messages']['resultCode']??'')!=='Ok'||(!is_string($id)&&!is_int($id))
            ||!preg_match('/^[0-9]{1,25}$/D',(string)$id)){
            query('UPDATE '.table('software_subscriptions').' SET status=? WHERE invoice_id=?',['review',$invoice]);
            return 'review';
        }
        $approvedId=(string)$id;
        $state=$gateway['mode']==='sandbox'?'sandbox_test':'pending_first_payment';
        // No invoice payment record, license or download is created at enrollment.
        return software_subscription_enroll_finalize($invoice,$approvedId,$state);
    }catch(Throwable $error){
        try{software_subscription_enroll_mark_review($invoice,$approvedId);}
        catch(Throwable $reviewError){report_error($reviewError);}
        report_error($error);
        return 'review';
    }
}
