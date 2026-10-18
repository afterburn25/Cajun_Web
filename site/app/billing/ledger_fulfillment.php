<?php
/** Payment evidence for fulfillment review; never an order, mandate or dispatch authorization. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/billing/payments.php';

/**
 * Internal read under the caller's staff mutex, transaction and current client/staff authority.
 * The caller must separately bind an order to its agreed plan and this exact invoice.
 * Historical imported balances remain visible but cannot become new verified receipts.
 */
function ledger_fulfillment_evidence(int $invoice,int $client):array {
    if(!db()->inTransaction())throw new LogicException('Payment evidence needs its owning transaction.');
    if(!ledger_ready()||!payments_ready())throw new RuntimeException('Prepared payment history is required.');
    if($invoice<1||$client<1)throw new InvalidArgumentException('Choose an existing client invoice.');
    $i=one('SELECT i.*,j.client_id,c.active,c.name AS client_name FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE i.id=? FOR UPDATE',[$invoice]);
    if(!$i)throw new RuntimeException('Invoice unavailable for this client.');
    if((int)$i['client_id']!==$client)throw new RuntimeException('Invoice unavailable for this client.');
    $balance=ledger_balance($i);$hash=ledger_invoice_hash($i);
    if($i['currency']!=='USD'||(int)$i['amount_cents']<0||(int)$i['amount_cents']>99999999)throw new RuntimeException('Invoice amount or currency is unsupported for fulfillment.');
    // Bounded retained history: an oversized ledger needs explicit investigation, not truncation.
    $rows=all('SELECT * FROM '.table('invoice_payments').' WHERE invoice_id=? ORDER BY id LIMIT 4097',[$invoice]);
    if(count($rows)>4096)throw new RuntimeException('Payment history exceeds the review bound.');
    $auditRows=all('SELECT user_id,action,detail FROM '.table('audit')." WHERE action IN ('payments.ledger.manual','payments.ledger.legacy','payments.ledger.reversal') AND detail LIKE ? ORDER BY id LIMIT 8193",['Entry % invoice '.$invoice.' amount %']);
    if(count($auditRows)>8192)throw new RuntimeException('Payment origin history exceeds the review bound.');
    $origins=[];foreach($auditRows as $a)$origins[$a['action'].'|'.$a['detail']][]=$a;
    $originals=[];$reversed=[];$verified=0;$legacy=0;$receipts=[];
    foreach($rows as $p){
        $id=(int)$p['id'];$amount=(int)$p['amount_cents'];$kind=$p['kind'];
        if($id<1||isset($originals[$id])||!in_array($kind,['manual','legacy','reversal'],true)
            ||(int)$p['invoice_id']!==$invoice||(int)$p['job_id']!==(int)$i['job_id']||(int)$p['client_id']!==$client
            ||$p['currency']!==$i['currency']||$p['invoice_reference']!==$i['reference']||$p['invoice_title']!==$i['title']
            ||(int)$p['invoice_total_cents']!==(int)$i['amount_cents']||!hash_equals($hash,$p['invoice_hash'])
            ||$amount<1||$amount>(int)$i['amount_cents']||(int)$p['actor_id']<1)throw new RuntimeException('Retained payment identity differs from this invoice.');
        // The original atomic audit distinguishes a verified manual entry from an imported balance.
        $detail='Entry '.$id.' invoice '.$invoice.' amount '.$amount.' '.$p['currency'].($kind==='reversal'?' reverses '.$p['reversal_of']:'');
        $audit=$origins['payments.ledger.'.$kind.'|'.$detail]??[];
        if(count($audit)!==1||(int)$audit[0]['user_id']!==(int)$p['actor_id'])throw new RuntimeException('Payment receipt origin is missing or inconsistent.');
        if($kind==='legacy'){
            if($p['method']!=='legacy'||$p['reversal_of']!==null||$p['request_key']!==null||$p['request_hash']!==null||$p['external_hash']!==null)throw new RuntimeException('Imported payment history is inconsistent.');
            $originals[$id]=$p;continue;
        }
        if(!is_string($p['request_key'])||!preg_match('/\A[a-f0-9]{64}\z/',$p['request_key'])
            ||!is_string($p['request_hash'])||!preg_match('/\A[a-f0-9]{64}\z/',$p['request_hash'])
            ||!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/',$p['received_on'],$date)
            ||!checkdate((int)$date[2],(int)$date[3],(int)$date[1])||$p['received_on']<'1900-01-01'||$p['received_on']>gmdate('Y-m-d'))throw new RuntimeException('Payment recording evidence is incomplete.');
        if($kind==='manual'){
            if(!isset(ledger_methods()[$p['method']])||$p['reversal_of']!==null||strlen(trim($p['private_note']))<5
                ||!is_string($p['external_hash'])||!hash_equals(hash('sha256',$p['method'].'|'.strtolower(preg_replace('/\s+/',' ',trim($p['private_note'])))),$p['external_hash']))throw new RuntimeException('Verified external payment evidence is inconsistent.');
            $originals[$id]=$p;
        }else{
            $target=(int)$p['reversal_of'];$old=$originals[$target]??null;
            if(!$old||isset($reversed[$target])||$target>=$id||$amount!==(int)$old['amount_cents']||$p['method']!==$old['method']||$p['external_hash']!==null||strlen(trim($p['private_note']))<10)throw new RuntimeException('Payment correction does not match its original receipt.');
            $reversed[$target]=true;
        }
    }
    foreach($originals as $id=>$p){
        if(isset($reversed[$id]))continue;
        if($p['kind']==='manual'){$verified+=(int)$p['amount_cents'];$receipts[]=$id;}else $legacy+=(int)$p['amount_cents'];
    }
    if($verified+$legacy!==$balance['paid_cents'])throw new RuntimeException('Payment evidence does not match the retained balance.');
    $attemptVersion=one('SELECT value FROM '.table('settings').' WHERE name=?',['payment_attempts_schema_version'])['value']??'';
    $attemptTable=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'payment_attempts'])!==null;
    if(!in_array($attemptVersion,['','1'],true)||($attemptVersion==='1'&&!$attemptTable))throw new RuntimeException('Established payment-attempt history is unavailable.');
    // Read the table when it exists even if a restored setting marker is missing.
    $pending=$attemptTable&&one('SELECT id FROM '.table('payment_attempts')." WHERE invoice_id=? AND state IN ('submitting','unknown','held') LIMIT 1",[$invoice])!==null;
    $blockers=[];
    if((int)$i['active']!==1)$blockers[]='client-inactive';
    if($i['status']==='void')$blockers[]='invoice-void';
    if((int)$i['amount_cents']===0)$blockers[]='no-charge-policy-required';
    if($pending)$blockers[]='payment-review-required';
    if($legacy>0)$blockers[]='unverified-legacy-balance';
    if($verified<(int)$i['amount_cents'])$blockers[]='verified-payment-incomplete';
    $evidence=['invoice_id'=>$invoice,'job_id'=>(int)$i['job_id'],'client_id'=>$client,'invoice_sha256'=>$hash,
        'currency'=>$i['currency'],'total_cents'=>(int)$i['amount_cents'],'paid_cents'=>$balance['paid_cents'],
        'verified_cents'=>$verified,'legacy_cents'=>$legacy,'due_cents'=>$balance['due_cents'],'receipt_ids'=>$receipts,
        'history_sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),
        'payment_evidence_ready'=>$blockers===[],'blockers'=>$blockers];
    // Pin the complete current assessment; persisted consumers must recheck it at dispatch.
    $evidence['review_sha256']=hash('sha256',json_encode($evidence,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    return $evidence;
}
