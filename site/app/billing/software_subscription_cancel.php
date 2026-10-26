<?php
/** Client-owned ARB cancellation, independent from paid-up license access. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/software_charge_guard.php';

function software_subscription_cancel(array $client,int $invoice):string{
    if(support_current()||!software_commerce_ready())throw new RuntimeException('Subscription cancellation unavailable.');
    if(db()->inTransaction())throw new LogicException('Subscription cancellation owns its transaction.');
    software_anet_settings();
    db()->beginTransaction();
    try{
        $sub=one('SELECT s.* FROM '.table('software_subscriptions').' s JOIN '.table('software_orders').' o ON o.invoice_id=s.invoice_id WHERE s.invoice_id=? AND s.client_id=? AND o.client_id=? FOR UPDATE',
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
            query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=? AND status=?',
                ['cancelled',$sub['id'],'cancelling']);
            license_audit((int)$client['id'],'subscription.cancel',
                'Cancelled recurring subscription '.$sub['id'].' for invoice '.$invoice);
            return 'cancelled';
        }
        query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',
            ['review',$sub['id']]);
        return 'review';
    }catch(Throwable $e){
        query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',
            ['review',$sub['id']]);
        report_error($e);
        return 'review';
    }
}
