<?php
/** Gateway charge fences: never issue a second charge after an uncertain result. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/software_orders.php';
require_once CWP_CODE_ROOT.'/app/billing/ledger.php';
require_once CWP_CODE_ROOT.'/app/billing/authorize_net_client.php';
require_once CWP_CODE_ROOT.'/app/billing/authorize_net_contract.php';

function software_charge_prepare(int $invoice,int $clientId,string $requestKey):int{
    if(!software_commerce_ready()||!ledger_ready())throw new RuntimeException('Software checkout is not prepared.');
    if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new InvalidArgumentException('Invalid checkout request.');
    if(db()->inTransaction())throw new LogicException('Charge preparation requires its own transaction.');
    db()->beginTransaction();
    try{
        $order=one('SELECT * FROM '.table('software_orders').' WHERE invoice_id=? AND client_id=? FOR UPDATE',[$invoice,$clientId]);
        if(!$order)throw new InvalidArgumentException('Order does not belong to this customer.');
        if($order['billing_term']!=='once')throw new RuntimeException('Recurring plans must use subscription checkout, not a single-payment charge.');
        $policy=software_order_policy_snapshot($order);
        if(!software_dependencies_satisfied($clientId,$policy['dependency_skus'])){
            throw new RuntimeException('Required software dependencies are no longer licensed. Review the order before charging.');
        }
        $i=ledger_invoice($invoice);$balance=ledger_balance($i);
        if($i['status']!=='issued'||$balance['paid_cents']!==0||$balance['due_cents']!==(int)$i['amount_cents'])
            throw new RuntimeException('Payment amount or invoice state changed. Reload.');
        $existing=one('SELECT * FROM '.table('software_charges').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        $now=gmdate('Y-m-d H:i:s');
        if($existing){
            if(!in_array($existing['status'],['declined','sandbox_test'],true)){
                throw new RuntimeException('A gateway attempt already exists. Its outcome must be reconciled before another charge.');
            }
            query('UPDATE '.table('software_charges').' SET status=?,request_key=?,transaction_id=NULL,updated_at=? WHERE id=?',
                ['processing',$requestKey,$now,$existing['id']]);
        }else{
            query('INSERT INTO '.table('software_charges').' (invoice_id,client_id,amount_cents,status,request_key,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',
                [$invoice,$clientId,$i['amount_cents'],'processing',$requestKey,$now,$now]);
        }
        query('INSERT INTO '.table('software_charge_attempts').' (invoice_id,request_key,amount_cents,status,created_at,updated_at) VALUES (?,?,?,?,?,?)',
            [$invoice,$requestKey,$i['amount_cents'],'processing',$now,$now]);
        db()->commit();return (int)$i['amount_cents'];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function software_charge_mark(int $invoice,string $state,?string $transactionId=null):void{
    if(!in_array($state,['declined','uncertain','review','sandbox_test'],true))throw new InvalidArgumentException('Unsupported payment outcome.');
    if(db()->inTransaction())throw new LogicException('Charge outcome marking must own the transaction.');
    db()->beginTransaction();
    try{
        $charge=one('SELECT * FROM '.table('software_charges').' WHERE invoice_id=? FOR UPDATE',[$invoice]);
        if(!$charge||$charge['status']!=='processing'){db()->commit();return;}
        $now=gmdate('Y-m-d H:i:s');
        query('UPDATE '.table('software_charge_attempts').' SET status=?,transaction_id=?,updated_at=? WHERE request_key=? AND status=?',
            [$state,$transactionId,$now,$charge['request_key'],'processing']);
        query('UPDATE '.table('software_charges').' SET status=?,transaction_id=?,updated_at=? WHERE id=?',
            [$state,$transactionId,$now,$charge['id']]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

/** Defensive readback: gateway approved response is verified against invoice. */
function software_charge_confirmed(array $details,string $transactionId,int $invoice,int $cents):bool{
    $tx=$details['transaction']??null;
    if(($details['messages']['resultCode']??'')!=='Ok'||!is_array($tx))return false;
    return (string)($tx['transId']??'')===$transactionId
        && (string)($tx['order']['invoiceNumber']??'')===substr('CWP-'.$invoice,0,20)
        && software_charge_exact_cents($tx['authAmount']??null)===$cents
        && in_array($tx['transactionStatus']??'', ['capturedPendingSettlement','settledSuccessfully'],true);
}
