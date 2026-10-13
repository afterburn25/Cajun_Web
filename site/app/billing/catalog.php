<?php
/** Managed service prices and immutable snapshots on the existing project invoices. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/marketing/growth.php';

require_once CWP_CODE_ROOT.'/database/schema/catalog_schema.php';
function catalog_ready(): bool {return staff_ready()&&studio_ready()&&setting('catalog_schema_version')==='1';}
function catalog_families(): array {return ['web-design'=>'Web design','maintenance'=>'Website care','addon'=>'Additional service','hosting'=>'Hosting (provider pending)','domain'=>'Domain (provider pending)','vps'=>'VPS (provider pending)'];}
function catalog_terms(): array {return ['once'=>'One-time service','month'=>'One month of service','year'=>'One year of service'];}
function catalog_audit(int $actor,string $action,string $detail): void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'catalog.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/catalog_migrate.php';
function catalog_require(bool $invoices=false): array {
    if(!catalog_ready())throw new RuntimeException('Enable the service catalog first.');
    if(support_current())throw new RuntimeException('Billing changes are unavailable during a support session.');
    $actor=staff_require('catalog.manage');if($invoices)staff_require('clients.manage');return $actor;
}
function catalog_save(int $id,int $expected,array $input): int {
    $data=[];foreach(['sku'=>60,'title'=>120,'family'=>20,'status'=>20,'term'=>10,'currency'=>3,'provider_ref'=>160,'summary'=>1000,'features'=>4000,'terms'=>4000] as $key=>$limit)$data[$key]=gtext($input,$key,$limit);
    $data['sku']=strtoupper($data['sku']);
    if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$data['sku'])||$data['title']===''||!isset(catalog_families()[$data['family']])||!isset(catalog_terms()[$data['term']])||!in_array($data['status'],['draft','published','retired'],true)||$data['currency']!=='USD')throw new InvalidArgumentException('Enter a unique SKU, title, valid family, status, service period and USD currency.');
    $data['amount_cents']=gcents(gtext($input,'amount',20));$data['setup_cents']=gcents(gtext($input,'setup',20))??0;$data['cost_cents']=gcents(gtext($input,'cost',20));$data['sort_order']=gint($input,'sort_order',0,9999);
    if($data['status']==='published'&&(!in_array($data['family'],['web-design','maintenance','addon'],true)||($data['amount_cents']??0)<=0||$data['amount_cents']+$data['setup_cents']>99999999||$data['summary']===''||$data['terms']===''))throw new InvalidArgumentException('Publish only a configured design, care or additional service with a positive price, summary and agreed service terms. Unit price plus setup cannot exceed 999999.99 USD. Provider products remain draft.');
    return staff_locked(fn()=>staff_transaction(function() use($id,$expected,$data): int {
        $actor=catalog_require();$old=$id?one('SELECT * FROM '.table('catalog_products').' WHERE id=? FOR UPDATE',[$id]):null;
        if(($id&&!$old)||($old&&(int)$old['revision']!==$expected)||(!$id&&$expected!==0))throw new RuntimeException('This product changed. Reload and review its latest version before saving.');
        if(one('SELECT id FROM '.table('catalog_products').' WHERE sku=? AND id<>?',[$data['sku'],$id]))throw new InvalidArgumentException('Another product already uses this SKU.');
        $revision=$old?(int)$old['revision']+1:1;$now=gmdate('Y-m-d H:i:s');$columns=array_keys($data);$values=array_values($data);
        if($id)query('UPDATE '.table('catalog_products').' SET '.implode(',',array_map(fn($k)=>$k.'=?',$columns)).',revision=?,updated_at=? WHERE id=?',[...$values,$revision,$now,$id]);
        else {query('INSERT INTO '.table('catalog_products').' ('.implode(',',$columns).',revision,created_at,updated_at) VALUES ('.implode(',',array_fill(0,count($columns)+3,'?')).')',[...$values,$revision,$now,$now]);$id=(int)db()->lastInsertId();}
        query('INSERT INTO '.table('catalog_revisions').' (product_id,revision,snapshot,actor_id,created_at) VALUES (?,?,?,?,?)',[$id,$revision,json_encode($data,JSON_THROW_ON_ERROR),$actor['id'],$now]);
        catalog_audit((int)$actor['id'],'save','Product '.$id.' revision '.$revision.' '.$data['status']);return $id;
    }));
}
/** Only customer-safe fields may be used in public cards or invoice snapshots. */
function catalog_public_fields(): string {return 'id,sku,title,family,term,currency,amount_cents,setup_cents,summary,features,terms,revision';}
function catalog_public(): array {return catalog_ready()?all('SELECT '.catalog_public_fields().' FROM '.table('catalog_products')." WHERE status='published' AND family IN ('web-design','maintenance','addon') ORDER BY sort_order,id LIMIT 200"):[];}
function catalog_quote(int $productId,int $revision,int $quantity,bool $lock=false): array {
    if($quantity<1||$quantity>100)throw new InvalidArgumentException('Choose between 1 and 100 units.');
    $p=one('SELECT '.catalog_public_fields().' FROM '.table('catalog_products')." WHERE id=? AND status='published' AND family IN ('web-design','maintenance','addon')".($lock?' FOR UPDATE':''),[$productId]);
    if(!$p||(int)$p['revision']!==$revision||$p['currency']!=='USD'||!isset(catalog_terms()[$p['term']]))throw new RuntimeException('This product or price changed. Reload and review the current offer.');
    $total=(int)$p['amount_cents']*$quantity+(int)$p['setup_cents'];if((int)$p['amount_cents']<=0||$total>99999999||$total<=0)throw new InvalidArgumentException('The invoice total must be between 0.01 and 999999.99 USD.');
    return $p+['quantity'=>$quantity,'total_cents'=>$total];
}
function catalog_issue(int $jobId,int $productId,int $revision,int $quantity,string $instructions,string $key): int {
    if(!preg_match('/^[a-f0-9]{64}$/D',$key)||strlen($instructions)>6000)throw new InvalidArgumentException('Invalid invoice request.');
    return staff_locked(fn()=>staff_transaction(function() use($jobId,$productId,$revision,$quantity,$instructions,$key): int {
        $actor=catalog_require(true);$hash=hash('sha256',json_encode([$jobId,$productId,$revision,$quantity,$instructions],JSON_THROW_ON_ERROR));
        $existing=one('SELECT * FROM '.table('catalog_invoice_snapshots').' WHERE request_key=? FOR UPDATE',[$key]);
        if($existing){if((int)$existing['actor_id']!==(int)$actor['id']||!hash_equals($existing['request_hash'],$hash))throw new RuntimeException('Invoice request was already used with different details.');return (int)$existing['invoice_id'];}
        $job=one('SELECT j.id,j.client_id FROM '.table('studio_jobs').' j JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE j.id=? AND c.active=1 FOR UPDATE',[$jobId]);if(!$job)throw new InvalidArgumentException('Choose an active client project.');
        $snapshot=catalog_quote($productId,$revision,$quantity,true);$now=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',[$jobId,'INV-'.strtoupper(bin2hex(random_bytes(6))),$snapshot['title'],$snapshot['total_cents'],'USD',$instructions,$now]);$invoice=(int)db()->lastInsertId();
        query('INSERT INTO '.table('catalog_invoice_snapshots').' (invoice_id,job_id,client_id,actor_id,request_key,request_hash,product_id,revision,quantity,snapshot,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$invoice,$jobId,$job['client_id'],$actor['id'],$key,$hash,$productId,$revision,$quantity,json_encode($snapshot,JSON_THROW_ON_ERROR),$now]);
        catalog_audit((int)$actor['id'],'invoice.issue','Invoice '.$invoice.' project '.$jobId.' product '.$productId.' revision '.$revision);return $invoice;
    }));
}
function catalog_snapshot(int $invoiceId): ?array {
    if(!catalog_ready())return null;$r=one('SELECT snapshot FROM '.table('catalog_invoice_snapshots').' WHERE invoice_id=?',[$invoiceId]);return $r?json_decode($r['snapshot'],true,32,JSON_THROW_ON_ERROR):null;
}
function catalog_transition(int $jobId,int $invoiceId,string $status,string $note): void {
    if(!in_array($status,['paid','void'],true)||strlen(trim($note))<5||strlen($note)>500)throw new InvalidArgumentException('Record a payment reference or cancellation reason (5–500 characters).');
    staff_locked(fn()=>staff_transaction(function() use($jobId,$invoiceId,$status,$note): void {
        $actor=catalog_require(true);$r=one('SELECT i.* FROM '.table('studio_invoices').' i JOIN '.table('catalog_invoice_snapshots').' s ON s.invoice_id=i.id WHERE i.id=? AND i.job_id=? FOR UPDATE',[$invoiceId,$jobId]);
        if(!$r||$r['status']!=='issued')throw new RuntimeException('Only an unpaid issued catalog invoice can be changed.');
        attempts_assert_clear($invoiceId);
        if(ledger_ready()){if($status==='paid')throw new RuntimeException('Use Invoice payments to review the remaining balance and record a receipt.');if(ledger_balance(ledger_invoice($invoiceId))['paid_cents']!==0)throw new RuntimeException('An invoice with recorded payments cannot be voided.');}
        query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',[$status,$note,$status==='paid'?gmdate('Y-m-d H:i:s'):null,$invoiceId]);
        catalog_audit((int)$actor['id'],'invoice.'.$status,'Invoice '.$invoiceId.' project '.$jobId);
    }));
}
