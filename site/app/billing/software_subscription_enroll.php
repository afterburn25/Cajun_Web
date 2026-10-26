<?php
/** ARB registration reserves one durable attempt, never issues a paid license. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/software_charge_guard.php';

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
function software_subscription_enroll(array $client,int $invoice,string $descriptor,#[\SensitiveParameter]string $nonce):string{
    if(support_current())throw new RuntimeException('Subscription changes are blocked in support mode.');
    if(!software_commerce_ready()||!ledger_ready())throw new RuntimeException('Prepare software payments and billing first.');
    $gateway=software_anet_settings();
    if(db()->inTransaction())throw new LogicException('Subscription enrollment must own its transaction.');
    db()->beginTransaction();
    try{
        $order=one('SELECT * FROM '.table('software_orders').' WHERE invoice_id=? AND client_id=? FOR UPDATE',[$invoice,(int)$client['id']]);
        if(!$order||!in_array($order['billing_term'],['month','year'],true))
            throw new RuntimeException('Only your approved monthly or annual software order can create a subscription.');
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
        $state=$gateway['mode']==='sandbox'?'sandbox_test':'pending_first_payment';
        // No invoice payment record, license or download is created at enrollment.
        return software_subscription_enroll_finalize($invoice,(string)$id,$state);
    }catch(Throwable $error){
        query('UPDATE '.table('software_subscriptions').' SET status=? WHERE invoice_id=? AND status=?',['review',$invoice,'creating']);
        report_error($error);
        return 'review';
    }
}
