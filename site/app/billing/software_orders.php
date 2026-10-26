<?php
/** Customer-owned software checkout orders on the existing studio ledger. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_license_policy.php';
require_once CWP_CODE_ROOT.'/app/billing/catalog.php';

function software_order_offer(array $product):bool {
    return preg_match('/^(SOFTWARE|WEBSITE|APP)-[A-Z0-9_-]+$/D',(string)($product['sku']??''))===1
        && in_array($product['term']??'', ['once','month','year'],true);
}
function software_order_place(array $client,int $productId,int $revision,int $quantity,
    string $domain,string $key,bool $subscribe):int {
    if(!software_commerce_ready() || !catalog_ready())throw new RuntimeException('Software ordering is unavailable.');
    if(support_current())throw new RuntimeException('Purchases cannot be made in support mode.');
    if($quantity<1||$quantity>25||!preg_match('/^[a-f0-9]{64}$/D',$key)){
        throw new InvalidArgumentException('Invalid order request.');
    }
    $domain=SoftwareLicensePolicy::domain($domain);
    $clientId=(int)$client['id'];
    if(!db()->inTransaction()){
        db()->beginTransaction();
    }else throw new LogicException('Software checkout must own the transaction.');
    try{
        $account=one('SELECT id,name FROM '.table('studio_clients').' WHERE id=? AND active=1 FOR UPDATE',[$clientId]);
        if(!$account)throw new RuntimeException('Customer account no longer active.');
        $hash=hash('sha256',json_encode([$clientId,$productId,$revision,$quantity,$domain,$subscribe],JSON_THROW_ON_ERROR));
        $previous=one('SELECT * FROM '.table('software_orders').' WHERE request_key=? FOR UPDATE',[$key]);
        if($previous){
            if((int)$previous['client_id']!==$clientId||!hash_equals($previous['request_hash'],$hash))
                throw new RuntimeException('Order request was already used for a different purchase.');
            db()->commit();return (int)$previous['invoice_id'];
        }
        $quote=catalog_quote($productId,$revision,$quantity,true);
        if(!software_order_offer($quote))throw new InvalidArgumentException('Choose a published software product.');
        if($quote['term']!=='once'&&!$subscribe)throw new InvalidArgumentException('Confirm the subscription period and recurring price.');
        $job=one('SELECT id FROM '.table('studio_jobs').' WHERE client_id=? AND title=? ORDER BY id LIMIT 1',
            [$clientId,'Software purchases']);
        $now=gmdate('Y-m-d H:i:s');
        if(!$job){
            query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',
                [$clientId,'Software purchases','Purchased software and license administration','care','', $now,$now]);
            $jobId=(int)db()->lastInsertId();
        }else $jobId=(int)$job['id'];
        $reference='SW-'.strtoupper(bin2hex(random_bytes(6)));
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',
            [$jobId,$reference,$quote['title'],$quote['total_cents'],'USD',
                'Payment is required before license issuance. Checkout uses tokenized Authorize.net card submission.', $now]);
        $invoiceId=(int)db()->lastInsertId();
        query('INSERT INTO '.table('software_orders').' (client_id,invoice_id,product_id,sku,quantity,domain,request_key,request_hash,snapshot,billing_term,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$clientId,$invoiceId,$productId,$quote['sku'],$quantity,$domain,$key,$hash,
                json_encode($quote,JSON_THROW_ON_ERROR),$quote['term'],$now]);
        db()->commit();return $invoiceId;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
