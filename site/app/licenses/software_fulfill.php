<?php
/** Order-line issue after a verified and fully-paid invoice. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
require_once CWP_CODE_ROOT.'/app/billing/ledger.php';
require_once CWP_CODE_ROOT.'/app/billing/software_orders.php';
require_once CWP_CODE_ROOT.'/app/billing/software_subscriptions.php';

function software_fulfill_paid_order(int $invoiceId):?array{
    if(!software_commerce_ready() || !ledger_ready())return null;
    if(db()->inTransaction())throw new LogicException('Fulfillment requires a distinct transaction.');
    db()->beginTransaction();
    try{
        $order=one('SELECT * FROM '.table('software_orders').' WHERE invoice_id=? FOR UPDATE',[$invoiceId]);
        if(!$order){db()->commit();return null;}
        $invoice=ledger_invoice($invoiceId);
        $balance=ledger_balance($invoice);
        if($invoice['status']!=='paid'||$balance['paid_cents']!==(int)$invoice['amount_cents']
            ||(int)$invoice['client_id']!==(int)$order['client_id']){
            throw new RuntimeException('Software is not released before verified full payment.');
        }
        $quote=json_decode($order['snapshot'],true,24,JSON_THROW_ON_ERROR);
        if(!is_array($quote)||($quote['sku']??null)!==$order['sku']
            ||(int)($quote['total_cents']??0)!==(int)$invoice['amount_cents']
            ||(int)($quote['quantity']??0)!==(int)$order['quantity']
            ||($quote['term']??null)!==$order['billing_term']){
            throw new RuntimeException('Order does not match the original price and invoice.');
        }
        $policy=software_order_policy_snapshot($order);
        if(!software_dependencies_satisfied((int)$order['client_id'],$policy['dependency_skus'])){
            throw new RuntimeException('Paid software dependency requirements are not currently satisfied. Owner review is required before fulfillment.');
        }
        $name=one('SELECT name FROM '.table('studio_clients').' WHERE id=?',[$order['client_id']])['name'];
        $first=null;
        $expiry=null;$type='standard';
        if($order['billing_term']!=='once'){
            $type='temporary';
            $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
            $expiry=software_subscription_extend_expiry(
                $now->format('Y-m-d H:i:s'),
                $order['billing_term']==='year'?12:1,
                $now
            );
        }
        for($unit=1;$unit<=(int)$order['quantity'];$unit++){
            $existing=one('SELECT l.* FROM '.table('software_entitlements').' e JOIN '.table('website_licenses').' l ON l.id=e.license_id WHERE e.invoice_id=? AND e.unit_number=?',
                [$invoiceId,$unit]);
            if($existing){$first=$first??$existing;continue;}
            $created=software_grant_create((int)$order['client_id'],$order['sku'],$type,
                $name,$order['domain'],$expiry,0,$invoiceId,$unit,'',$order['billing_term']!=='once',$policy);
            $first=$first??one('SELECT * FROM '.table('website_licenses').' WHERE id=?',[$created['id']]);
        }
        if($order['billing_term']!=='once'){
            query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE invoice_id=invoice_id',
                [$invoiceId,$order['client_id'],$order['billing_term']==='year'?12:1,
                 $invoice['amount_cents'],'awaiting_gateway_setup',$order['created_at'],gmdate('Y-m-d H:i:s')]);
        }
        query('INSERT INTO '.table('software_notices').' (invoice_id,client_id,status,created_at) VALUES (?,? ,?,?) ON DUPLICATE KEY UPDATE invoice_id=invoice_id',
            [$invoiceId,$order['client_id'],'pending',gmdate('Y-m-d H:i:s')]);
        db()->commit();return $first;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
