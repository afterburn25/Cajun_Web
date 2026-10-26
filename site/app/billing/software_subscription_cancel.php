<?php
/** Client-owned ARB cancellation, independent from paid-up license access. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/software_charge_guard.php';

function software_subscription_cancel_mark_review(int $subscriptionId):string{
    if($subscriptionId<1)throw new InvalidArgumentException('Invalid subscription cancellation identity.');
    query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=? AND status=?',
        ['review',$subscriptionId,'cancelling']);
    $current=one('SELECT status FROM '.table('software_subscriptions').' WHERE id=?',[$subscriptionId]);
    return ($current['status']??'')==='cancelled'?'cancelled':'review';
}

function software_subscription_cancel_finalize(int $subscriptionId,int $clientId,int $invoice):string{
    if($subscriptionId<1||$clientId<1||$invoice<1)throw new InvalidArgumentException('Invalid subscription cancellation identity.');
    if(support_current())throw new RuntimeException('Subscription cancellation unavailable during support access.');
    if(db()->inTransaction())throw new LogicException('Subscription cancellation finalization owns its transaction.');
    db()->beginTransaction();
    try{
        $sub=one('SELECT s.id,s.status FROM '.table('software_subscriptions').' s JOIN '.table('software_orders').' o ON o.invoice_id=s.invoice_id WHERE s.id=? AND s.invoice_id=? AND s.client_id=? AND o.client_id=? FOR UPDATE',
            [$subscriptionId,$invoice,$clientId,$clientId]);
        if(!$sub)throw new RuntimeException('Subscription cancellation ownership is inconsistent.');
        if($sub['status']==='cancelling'){
            query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',
                ['cancelled',$subscriptionId]);
            $sub['status']='cancelled';
        }
        if($sub['status']!=='cancelled'){
            license_audit($clientId,'subscription.cancel.review',
                'Gateway confirmed cancellation but subscription '.$subscriptionId.' changed state concurrently; owner review retained');
            db()->commit();return 'review';
        }
        license_audit($clientId,'subscription.cancel',
            'Cancelled recurring subscription '.$subscriptionId.' for invoice '.$invoice);
        db()->commit();return 'cancelled';
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        throw $e;
    }
}

function software_subscription_cancel(array $client,int $invoice):string{
    if(support_current()||!software_commerce_ready())throw new RuntimeException('Subscription cancellation unavailable.');
    if(db()->inTransaction())throw new LogicException('Subscription cancellation owns its transaction.');
    software_anet_settings();
    db()->beginTransaction();
    try{
        $sub=one('SELECT s.*,o.created_at AS order_created_at,o.billing_term FROM '.table('software_subscriptions').' s JOIN '.table('software_orders').' o ON o.invoice_id=s.invoice_id WHERE s.invoice_id=? AND s.client_id=? AND o.client_id=? FOR UPDATE',
            [$invoice,(int)$client['id'],(int)$client['id']]);
        if(!$sub)throw new RuntimeException('This subscription does not belong to you.');
        if(!in_array($sub['status'],['active','pending_first_payment','sandbox_test'],true)){
            db()->commit();return 'not_active';
        }
        if(!is_string($sub['gateway_subscription_id'])||!preg_match('/^[0-9]{1,25}$/D',$sub['gateway_subscription_id'])){
            throw new RuntimeException('Gateway subscription is not verified.');
        }
        query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',
            ['cancelling',$sub['id']]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    try{
        $response=software_anet_execute('ARBCancelSubscriptionRequest',
            ['subscriptionId'=>$sub['gateway_subscription_id']]);
        if(($response['messages']['resultCode']??'')==='Ok'){
            $details=software_anet_execute('ARBGetSubscriptionRequest',[
                'subscriptionId'=>$sub['gateway_subscription_id'],'includeTransactions'=>false
            ]);
            $gatewayStart=(string)($details['subscription']['paymentSchedule']['startDate']??'');
            $created=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',(string)$sub['created_at'],new DateTimeZone('UTC'));
            $errors=DateTimeImmutable::getLastErrors();
            if($created===false||($errors!==false&&($errors['warning_count']||$errors['error_count']))){
                throw new RuntimeException('Local subscription creation timestamp is invalid.');
            }
            $allowedStarts=[
                $created->modify('+2 days')->format('Y-m-d'),
                $created->modify('+3 days')->format('Y-m-d'),
            ];
            if(!in_array($gatewayStart,$allowedStarts,true)){
                throw new RuntimeException('Gateway subscription start date is outside the original enrollment window.');
            }
            $profile=software_anet_subscription_profile_details(
                $details,(string)$sub['gateway_subscription_id'],$invoice,(int)$sub['amount_cents'],
                (int)$sub['interval_months'],$gatewayStart
            );
            if((string)$profile['status']!=='canceled'){
                return software_subscription_cancel_mark_review((int)$sub['id']);
            }
            return software_subscription_cancel_finalize((int)$sub['id'],(int)$client['id'],$invoice);
        }
        return software_subscription_cancel_mark_review((int)$sub['id']);
    }catch(Throwable $e){
        $state=software_subscription_cancel_mark_review((int)$sub['id']);
        report_error($e);
        return $state;
    }
}
