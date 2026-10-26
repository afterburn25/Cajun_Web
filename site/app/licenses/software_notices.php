<?php
/** Durable best-effort email: customer must log in to retrieve keys and ZIPs. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/mail/transport.php';
function software_delivery_notice_send(int $invoiceId):bool{
    if(!software_commerce_ready())return false;
    if(db()->inTransaction())throw new LogicException('Send notices after payment transaction commit.');
    db()->beginTransaction();
    try{
        $row=one('SELECT * FROM '.table('software_notices').' WHERE invoice_id=? FOR UPDATE',[$invoiceId]);
        if(!$row||$row['status']==='sent'){db()->commit();return true;}
        $now=gmdate('Y-m-d H:i:s');
        if($row['status']==='sending'&&$row['last_attempt']!==null
            &&$row['last_attempt']>gmdate('Y-m-d H:i:s',time()-3600)){
            db()->commit();return false;
        }
        if((int)$row['attempts']>=10){db()->commit();return false;}
        $invoice=one('SELECT status FROM '.table('studio_invoices').' WHERE id=?',[$invoiceId]);
        if(!$invoice||$invoice['status']!=='paid'){db()->commit();return false;}
        query('UPDATE '.table('software_notices').' SET status=?,attempts=attempts+1,last_attempt=? WHERE id=?',
            ['sending',$now,$row['id']]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    $client=one('SELECT name,email FROM '.table('studio_clients').' WHERE id=?',[$row['client_id']]);
    try{
        if(!$client||!filter_var($client['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Client email unavailable.');
        $order=one('SELECT sku FROM '.table('software_orders').' WHERE invoice_id=?',[$invoiceId]);
        $renewal=false;
        if(!$order){
            $receipt=one('SELECT subscription_invoice_id FROM '.table('software_subscription_receipts').' WHERE invoice_id=?',[$invoiceId]);
            if($receipt){
                $order=one('SELECT sku FROM '.table('software_orders').' WHERE invoice_id=?',[(int)$receipt['subscription_invoice_id']]);
                $renewal=true;
            }
        }
        if(!$order)throw new RuntimeException('Software order history unavailable for delivery notice.');
        $body='Hello '.$client['name'].",\n\n".
            ($renewal
                ? 'Your software subscription renewal for '.$order['sku'].' has been verified and your paid-through access has been extended.'
                : 'Your software purchase '.$order['sku'].' has been approved and is available in your CajunWebPro client account.')."\n\n".
            'Sign in to view your software licenses, registered domains, invoices and purchased downloads:'."\n".
            site_url('portal/index.php?software=1')."\n\n".
            'For security, your license keys and downloadable files are not attached to this email.'."\n\n".'CajunWebPro';
        $delivery=send_message($client['email'],
            $renewal?'Your CajunWebPro software renewal is confirmed':'Your CajunWebPro software purchase is ready',$body);
        if($delivery!=='accepted')throw new RuntimeException('Software delivery email was not accepted by the configured mail transport.');
        query('UPDATE '.table('software_notices').' SET status=?,sent_at=?,last_error=? WHERE invoice_id=?',
            ['sent',gmdate('Y-m-d H:i:s'),'',$invoiceId]);
        return true;
    }catch(Throwable $e){
        query('UPDATE '.table('software_notices').' SET status=?,last_error=? WHERE invoice_id=?',
            ['pending','Delivery failed; retry after mail configuration review',$invoiceId]);
        report_error($e);
        return false;
    }
}
