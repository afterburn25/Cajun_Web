<?php
/** Authenticated recurring transaction reconciliation and invoice renewal. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/billing/software_charge_guard.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_fulfill.php';
require_once CWP_CODE_ROOT.'/app/billing/authorize_net_webhooks.php';

/** Compatibility wrapper retaining strict amount rejection in recurring settlement. */
function software_anet_cents(mixed $amount): int {
    $cents = software_charge_exact_cents($amount);
    if ($cents === null) throw new RuntimeException('Unrecognized gateway transaction amount.');
    return $cents;
}

/** Transaction details are fetched directly from Authorize.net after a signed notification. */
function software_subscription_settle(array $details,string $transactionId): string {
    if(!software_commerce_ready()||!ledger_ready())throw new RuntimeException('Payments not prepared.');
    $gateway=software_anet_settings();
    if($gateway['mode']!=='production')return 'sandbox_ignored';
    $tx=$details['transaction']??null;
    if(($details['messages']['resultCode']??'')!=='Ok'||!is_array($tx)
        ||(string)($tx['transId']??'')!==$transactionId
        ||!in_array($tx['transactionStatus']??'', ['capturedPendingSettlement','settledSuccessfully'],true)
        ||!isset($tx['subscription']['id'])||!preg_match('/^[0-9]{1,25}$/D',(string)$tx['subscription']['id'])){
        throw new RuntimeException('Gateway transaction is not a verified captured subscription charge.');
    }
    $id=(string)$tx['subscription']['id'];
    if(db()->inTransaction())throw new LogicException('Subscription settlement must own the transaction.');
    $notifyInvoice=null;
    db()->beginTransaction();
    try{
        $sub=one('SELECT * FROM '.table('software_subscriptions').' WHERE gateway_subscription_id=? FOR UPDATE',[$id]);
        if(!$sub) {db()->commit();return 'unknown_subscription';}
        if(!in_array($sub['status'],['pending_first_payment','active'],true)){
            db()->commit();return 'subscription_not_active';
        }
        $previous=one('SELECT invoice_id FROM '.table('software_subscription_receipts').' WHERE gateway_transaction_id=?',[$transactionId]);
        if($previous){db()->commit();return 'replay';}
        $original=ledger_invoice((int)$sub['invoice_id']);
        if((int)$sub['client_id']!==(int)$original['client_id']||$original['currency']!=='USD'
            ||software_anet_cents($tx['authAmount']??null)!==(int)$sub['amount_cents']
            ||(string)($tx['order']['invoiceNumber']??'')!==substr('CWP-'.$original['id'],0,20)){
            throw new RuntimeException('Subscription owner, invoice or payment amount disagrees with gateway.');
        }
        $first=$sub['status']==='pending_first_payment';
        if($first){
            $i=$original;$balance=ledger_balance($i);
            if($i['status']!=='issued'||$balance['paid_cents']!==0
                ||$balance['due_cents']!==(int)$sub['amount_cents']){
                throw new RuntimeException('First-period invoice changed before gateway confirmation.');
            }
            $invoiceId=(int)$i['id'];
        }else{
            $grants=all('SELECT e.license_id,e.license_type,e.expires_at,l.is_active,l.is_master FROM '.table('software_entitlements').' e JOIN '.table('website_licenses').' l ON l.id=e.license_id WHERE e.invoice_id=? FOR UPDATE',[$original['id']]);
            if(!$grants)throw new RuntimeException('Renewal entitlement history is absent.');
            $now=gmdate('Y-m-d H:i:s');$reference='SWR-'.strtoupper(bin2hex(random_bytes(6)));
            query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',
                [$original['job_id'],$reference,'Software renewal · '.$original['title'],(int)$sub['amount_cents'],'USD',
                    'Verified recurring Authorize.net charge '.$transactionId,$now]);
            $invoiceId=(int)db()->lastInsertId();$i=ledger_invoice($invoiceId);
        }
        $ref=hash('sha256','authorize-net|'.$transactionId);
        $entry=ledger_insert($i,0,'gateway',(int)$sub['amount_cents'],'authorize_net',
            'Verified subscription transaction '.$transactionId,gmdate('Y-m-d'),null,null,$ref);
        query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',
            ['paid','Verified gateway receipt '.$entry,gmdate('Y-m-d H:i:s'),$invoiceId]);
        query('INSERT INTO '.table('software_subscription_receipts').' (subscription_invoice_id,gateway_transaction_id,invoice_id,created_at) VALUES (?,?,?,?)',
            [$original['id'],$transactionId,$invoiceId,gmdate('Y-m-d H:i:s')]);
        if($first){
            query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',['active',$sub['id']]);
            $notifyInvoice=$invoiceId;
        }else{
            $duration=(int)$sub['interval_months'];
            foreach($grants as $g){
                if($g['license_type']!=='temporary'||(int)$g['is_master']===1
                    ||$g['expires_at']===null)throw new RuntimeException('Renewal grant metadata is inconsistent.');
                if((int)$g['is_active']!==1)continue;
                $existing=new DateTimeImmutable($g['expires_at'],new DateTimeZone('UTC'));
                $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
                $base=$existing>$now?$existing:$now;
                $extended=$base->modify('+'.$duration.' months')->format('Y-m-d H:i:s');
                query('UPDATE '.table('software_entitlements').' SET expires_at=? WHERE license_id=?',
                    [$extended,$g['license_id']]);
            }
            $notifyInvoice=$invoiceId;
        }
        db()->commit();
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
    if($first && $notifyInvoice!==null){
        try{license_fulfill_invoice($notifyInvoice);}catch(Throwable $e){report_error($e);}
    }
    return $first?'first_payment_recorded':'renewal_recorded';
}

function software_webhook_process(string $raw,string $signature):string{
    $gateway=software_anet_settings();
    require_once CWP_CODE_ROOT.'/app/billing/payments.php';
    $secret=one('SELECT credentials FROM '.table('payment_gateways').' WHERE environment=?',[$gateway['mode']]);
    if(!$secret)throw new RuntimeException('Gateway credentials missing.');
    $keys=payments_unseal($gateway['mode'],$secret['credentials']);
    if(!software_anet_signature_matches($raw,$signature,(string)$keys['signature_key'])){
        throw new InvalidArgumentException('Invalid webhook signature.');
    }
    $event=software_anet_event_details($raw);
    if($gateway['mode']!=='production')return 'sandbox_event';
    if(!in_array($event['type'],[
        'net.authorize.payment.authcapture.created',
        'net.authorize.payment.priorAuthCapture.created',
        'net.authorize.payment.capture.created'
    ],true))return 'ignored';
    $txId=$event['transaction_id'];
    if(!preg_match('/^[0-9]{5,30}$/D',$txId))throw new InvalidArgumentException('Invalid gateway transaction ID.');
    $details=software_anet_execute('getTransactionDetailsRequest',['transId'=>$txId]);
    return software_subscription_settle($details,$txId);
}
