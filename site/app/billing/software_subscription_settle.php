<?php
/** Authenticated recurring transaction reconciliation and invoice renewal. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/billing/software_charge_guard.php';
require_once CWP_CODE_ROOT.'/app/billing/software_charge.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_fulfill.php';
require_once CWP_CODE_ROOT.'/app/billing/authorize_net_webhooks.php';
require_once CWP_CODE_ROOT.'/app/billing/software_payment_review.php';
require_once CWP_CODE_ROOT.'/app/billing/software_subscriptions.php';
require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
require_once CWP_CODE_ROOT.'/app/billing/finance_charge.php';

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
    $verified=software_anet_subscription_charge_details($details,$transactionId);
    if($gateway['mode']!=='production')return 'sandbox_verified';
    $id=$verified['subscription_id'];
    if(db()->inTransaction())throw new LogicException('Subscription settlement must own the transaction.');
    $notifyInvoice=null;$renewalReview=false;
    db()->beginTransaction();
    try{
        $sub=one('SELECT * FROM '.table('software_subscriptions').' WHERE gateway_subscription_id=? FOR UPDATE',[$id]);
        if(!$sub) {db()->commit();return 'unknown_subscription';}
        $previous=one('SELECT invoice_id FROM '.table('software_subscription_receipts').' WHERE gateway_transaction_id=?',[$transactionId]);
        if($previous){db()->commit();return 'replay';}
        $history=one('SELECT id FROM '.table('software_subscription_receipts').' WHERE subscription_invoice_id=? ORDER BY id LIMIT 1',
            [(int)$sub['invoice_id']]);
        $phase=software_subscription_settlement_phase((string)$sub['status'],(bool)$history);
        if($phase===null){db()->commit();return 'subscription_not_settleable';}
        $original=ledger_invoice((int)$sub['invoice_id']);
        if((int)$sub['client_id']!==(int)$original['client_id']||$original['currency']!=='USD'
            ||$verified['amount_cents']!==(int)$sub['amount_cents']
            ||$verified['invoice_number']!==substr('CWP-'.$original['id'],0,20)){
            throw new RuntimeException('Subscription owner, invoice or payment amount disagrees with gateway.');
        }
        $first=(bool)$phase['first'];
        if($first){
            $i=$original;$balance=ledger_balance($i);
            if($i['status']!=='issued'||$balance['paid_cents']!==0
                ||$balance['due_cents']!==(int)$sub['amount_cents']){
                throw new RuntimeException('First-period invoice changed before gateway confirmation.');
            }
            $invoiceId=(int)$i['id'];
        }else{
            $grants=all('SELECT e.license_id,e.license_type,e.expires_at,e.updates_until,l.is_active,l.is_master FROM '.table('software_entitlements').' e JOIN '.table('website_licenses').' l ON l.id=e.license_id WHERE e.invoice_id=? FOR UPDATE',[$original['id']]);
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
            query('INSERT INTO '.table('software_notices').' (invoice_id,client_id,status,created_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE invoice_id=invoice_id',
                [$invoiceId,$sub['client_id'],'pending',gmdate('Y-m-d H:i:s')]);
            $nextStatus=software_subscription_post_payment_status((string)$sub['status'],true,false);
            if($nextStatus!==(string)$sub['status']){
                query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',[$nextStatus,$sub['id']]);
                if($nextStatus==='active'&&in_array((string)$sub['status'],['payment_failed','billing_suspended'],true)){
                    license_audit(0,'subscription.billing.resume',
                        'Verified recurring transaction '.$transactionId.' restored billing for software invoice '.$original['id']);
                }
            }
            $notifyInvoice=$invoiceId;
        }else{
            $duration=(int)$sub['interval_months'];
            if(!in_array($duration,[1,12],true))throw new RuntimeException('Invalid subscription renewal interval.');
            foreach($grants as $g){
                $plan=software_subscription_renewal_grant_plan($g);
                if($plan['review'])$renewalReview=true;
                if(!$plan['extend'])throw new RuntimeException('Verified renewal plan refused paid-through extension.');
                $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
                $extended=software_subscription_extend_expiry((string)$g['expires_at'],$duration,$now);
                query('UPDATE '.table('software_entitlements').' SET expires_at=?,updates_until=? WHERE license_id=?',
                    [$extended,$extended,$g['license_id']]);
            }
            $nextStatus=software_subscription_post_payment_status((string)$sub['status'],false,$renewalReview);
            if($nextStatus!==(string)$sub['status']){
                query('UPDATE '.table('software_subscriptions').' SET status=? WHERE id=?',[$nextStatus,$sub['id']]);
            }
            if($renewalReview){
                license_audit(0,'subscription.renewal.review',
                    'Verified recurring transaction '.$transactionId.' paid invoice '.$invoiceId.
                    ' and extended paid-through entitlement, but at least one software license remains manually suspended or revoked; owner review is required before access can resume');
            }else{
                if($nextStatus==='active'&&in_array((string)$sub['status'],['payment_failed','billing_suspended'],true)){
                    license_audit(0,'subscription.billing.resume',
                        'Verified recurring transaction '.$transactionId.' restored billing for software invoice '.$original['id']);
                }
                query('INSERT INTO '.table('software_notices').' (invoice_id,client_id,status,created_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE invoice_id=invoice_id',
                    [$invoiceId,$sub['client_id'],'pending',gmdate('Y-m-d H:i:s')]);
                $notifyInvoice=$invoiceId;
            }
        }
        db()->commit();
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
    if($notifyInvoice!==null){
        try{
            if($first){
                license_fulfill_invoice($notifyInvoice);
            }else{
                require_once CWP_CODE_ROOT.'/app/licenses/software_notices.php';
                software_delivery_notice_send($notifyInvoice);
            }
        }catch(Throwable $e){report_error($e);}
    }
    return $first?'first_payment_recorded':($renewalReview?'renewal_recorded_review':'renewal_recorded');
}

/** Owner recovery for a verified recurring transaction whose webhook could not settle locally. */
function software_subscription_charge_reconcile(int $invoice,string $transactionId,#[\SensitiveParameter]string $password):string{
    if(support_current())throw new RuntimeException('Recurring payment reconciliation is unavailable during support access.');
    if(!software_commerce_ready()||!ledger_ready())throw new RuntimeException('Prepare software payments and billing first.');
    if($invoice<1||!preg_match('/^[0-9]{5,30}$/D',$transactionId)){
        throw new InvalidArgumentException('Enter the software invoice and verified recurring transaction ID.');
    }
    staff_require('staff.manage');
    staff_owner($password); // authenticate before gateway readback
    $sub=one('SELECT gateway_subscription_id,amount_cents FROM '.table('software_subscriptions').' WHERE invoice_id=?',[$invoice]);
    if(!$sub||$sub['gateway_subscription_id']===null){
        throw new RuntimeException('Reconcile the gateway subscription identity before reconciling its charges.');
    }
    $gateway=software_anet_settings();
    $details=software_anet_execute('getTransactionDetailsRequest',['transId'=>$transactionId]);
    $verified=software_anet_subscription_charge_details($details,$transactionId);
    if((string)$verified['subscription_id']!==(string)$sub['gateway_subscription_id']
        ||(int)$verified['amount_cents']!==(int)$sub['amount_cents']
        ||(string)$verified['invoice_number']!==substr('CWP-'.$invoice,0,20)){
        throw new RuntimeException('Recurring transaction does not exactly match this software subscription invoice.');
    }
    if($gateway['mode']!=='production'){
        staff_locked(function()use($password,$invoice,$transactionId):void{
            $actor=staff_owner($password);
            license_audit((int)$actor['id'],'subscription.charge.reconcile.sandbox',
                'Sandbox recurring transaction '.$transactionId.' verified for software invoice '.$invoice.'; no paid transition');
        });
        return 'sandbox_verified';
    }
    staff_locked(function()use($password,$invoice,$transactionId):void{
        $actor=staff_owner($password);
        license_audit((int)$actor['id'],'subscription.charge.reconcile.begin',
            'Owner authorized recurring transaction reconciliation for software invoice '.$invoice.
            ' transaction '.$transactionId);
    });
    $state=software_subscription_settle($details,$transactionId);
    try{
        staff_locked(function()use($password,$invoice,$transactionId,$state):void{
            $actor=staff_owner($password);
            license_audit((int)$actor['id'],'subscription.charge.reconcile',
                'Software invoice '.$invoice.' recurring transaction '.$transactionId.' reconciled as '.$state);
        });
    }catch(Throwable $auditError){report_error($auditError);}
    return $state;
}

/** Verify a sandbox capture against local state without mutating payment or entitlement records. */
function software_sandbox_capture_verify(array $details,string $transactionId):string{
    if(!preg_match('/^[0-9]{5,30}$/D',$transactionId))throw new InvalidArgumentException('Invalid gateway transaction ID.');
    $tx=$details['transaction']??null;
    if(!is_array($tx))throw new RuntimeException('Sandbox transaction readback is invalid.');
    if(isset($tx['subscription']['id'])){
        $verified=software_anet_subscription_charge_details($details,$transactionId);
        $sub=one('SELECT invoice_id,amount_cents,gateway_subscription_id FROM '.table('software_subscriptions').
            ' WHERE gateway_subscription_id=?',[$verified['subscription_id']]);
        if(!$sub)return 'sandbox_subscription_unmatched';
        if((string)$sub['gateway_subscription_id']!==(string)$verified['subscription_id']
            ||(int)$sub['amount_cents']!==(int)$verified['amount_cents']
            ||(string)$verified['invoice_number']!==substr('CWP-'.(int)$sub['invoice_id'],0,20)){
            throw new RuntimeException('Sandbox recurring transaction does not match local subscription state.');
        }
        return 'sandbox_subscription_verified';
    }
    $invoiceNumber=(string)($tx['order']['invoiceNumber']??'');
    if(!preg_match('/^CWP-([1-9][0-9]{0,14})$/D',$invoiceNumber,$match))return 'sandbox_payment_unmatched';
    $invoice=(int)$match[1];
    $charge=one('SELECT amount_cents,transaction_id FROM '.table('software_charges').' WHERE invoice_id=?',[$invoice]);
    if(!$charge)return 'sandbox_payment_unmatched';
    if($charge['transaction_id']!==null&&!hash_equals((string)$charge['transaction_id'],$transactionId)){
        throw new RuntimeException('Sandbox transaction disagrees with retained local transaction identity.');
    }
    if(!software_charge_confirmed($details,$transactionId,$invoice,(int)$charge['amount_cents'])){
        throw new RuntimeException('Sandbox one-time transaction does not exactly match local invoice and amount.');
    }
    return 'sandbox_payment_verified';
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
    if($gateway['mode']==='sandbox'){
        if(in_array($event['type'],[
            'net.authorize.payment.refund.created',
            'net.authorize.payment.void.created'
        ],true)){
            if(($event['entity']??'')!=='transaction')throw new InvalidArgumentException('Invalid payment event entity.');
            $txId=$event['resource_id'];
            if(!preg_match('/^[0-9]{5,30}$/D',$txId))throw new InvalidArgumentException('Invalid gateway transaction ID.');
            $details=software_anet_execute('getTransactionDetailsRequest',['transId'=>$txId]);
            software_anet_reversal_details($details,$txId,$event['type']);
            return 'sandbox_reversal_verified';
        }
        if(str_starts_with($event['type'],'net.authorize.customer.subscription.')){
            if(($event['entity']??'')!=='subscription'||!preg_match('/^[0-9]{1,25}$/D',$event['resource_id']))
                throw new InvalidArgumentException('Invalid subscription event entity.');
            if(license_finance_gateway_ready()&&one('SELECT id FROM '.table('license_finance_subscriptions').' WHERE gateway_subscription_id=?',[$event['resource_id']])){
                return 'sandbox_finance_subscription_event';
            }
            return 'sandbox_subscription_event';
        }
        if(in_array($event['type'],[
            'net.authorize.payment.authcapture.created',
            'net.authorize.payment.priorAuthCapture.created',
            'net.authorize.payment.capture.created'
        ],true)){
            if(($event['entity']??'')!=='transaction')throw new InvalidArgumentException('Invalid payment event entity.');
            $txId=$event['resource_id'];
            if(!preg_match('/^[0-9]{5,30}$/D',$txId))throw new InvalidArgumentException('Invalid gateway transaction ID.');
            $details=software_anet_execute('getTransactionDetailsRequest',['transId'=>$txId]);
            if(isset($details['transaction']['subscription']['id'])){
                $sid=(string)$details['transaction']['subscription']['id'];
                if(license_finance_gateway_ready()&&one('SELECT id FROM '.table('license_finance_subscriptions').' WHERE gateway_subscription_id=?',[$sid])){
                    return license_finance_subscription_settle($details,$txId);
                }
            }else{
                $finance=license_finance_charge_webhook_review($details,$txId);
                if($finance!=='finance_payment_unmatched')return $finance;
            }
            return software_sandbox_capture_verify($details,$txId);
        }
        return 'sandbox_event';
    }

    if(in_array($event['type'],[
        'net.authorize.payment.refund.created',
        'net.authorize.payment.void.created'
    ],true)){
        if(($event['entity']??'')!=='transaction')throw new InvalidArgumentException('Invalid payment event entity.');
        $txId=$event['resource_id'];
        if(!preg_match('/^[0-9]{5,30}$/D',$txId))throw new InvalidArgumentException('Invalid gateway transaction ID.');
        $details=software_anet_execute('getTransactionDetailsRequest',['transId'=>$txId]);
        return software_payment_reversal_review($details,$txId,$event['type']);
    }

    if(str_starts_with($event['type'],'net.authorize.customer.subscription.')){
        if(($event['entity']??'')!=='subscription')throw new InvalidArgumentException('Invalid subscription event entity.');
        if(license_finance_gateway_ready()&&one('SELECT id FROM '.table('license_finance_subscriptions').' WHERE gateway_subscription_id=?',[$event['resource_id']])){
            return license_finance_subscription_lifecycle_review($event['type'],$event['resource_id']);
        }
        return software_subscription_lifecycle_review($event['type'],$event['resource_id']);
    }

    if(!in_array($event['type'],[
        'net.authorize.payment.authcapture.created',
        'net.authorize.payment.priorAuthCapture.created',
        'net.authorize.payment.capture.created'
    ],true))return 'ignored';
    if(($event['entity']??'')!=='transaction')throw new InvalidArgumentException('Invalid payment event entity.');
    $txId=$event['resource_id'];
    if(!preg_match('/^[0-9]{5,30}$/D',$txId))throw new InvalidArgumentException('Invalid gateway transaction ID.');
    $details=software_anet_execute('getTransactionDetailsRequest',['transId'=>$txId]);
    if(isset($details['transaction']['subscription']['id'])){
        $sid=(string)$details['transaction']['subscription']['id'];
        if(license_finance_gateway_ready()&&one('SELECT id FROM '.table('license_finance_subscriptions').' WHERE gateway_subscription_id=?',[$sid])){
            return license_finance_subscription_settle($details,$txId);
        }
        return software_subscription_settle($details,$txId);
    }
    $finance=license_finance_charge_webhook_review($details,$txId);
    if($finance!=='finance_payment_unmatched')return $finance;
    return software_charge_capture_webhook_review($details,$txId);
}
