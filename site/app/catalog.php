<?php
/** Managed service prices and immutable snapshots on the existing project invoices. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;
require_once __DIR__.'/staff.php';
require_once __DIR__.'/growth.php';

function catalog_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'catalog_products'=>'CREATE TABLE '.$t('catalog_products')." (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sku VARCHAR(60) NOT NULL UNIQUE,title VARCHAR(120) NOT NULL,family VARCHAR(20) NOT NULL,status VARCHAR(20) NOT NULL,term VARCHAR(10) NOT NULL,currency CHAR(3) NOT NULL,amount_cents INT UNSIGNED NULL,setup_cents INT UNSIGNED NOT NULL,cost_cents INT UNSIGNED NULL,provider_ref VARCHAR(160) NOT NULL,summary TEXT NOT NULL,features TEXT NOT NULL,terms TEXT NOT NULL,sort_order INT NOT NULL,revision INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL)".$e,
        'catalog_revisions'=>'CREATE TABLE '.$t('catalog_revisions').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,product_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,snapshot LONGTEXT NOT NULL,actor_id INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,UNIQUE product_revision(product_id,revision))'.$e,
        'catalog_invoice_snapshots'=>'CREATE TABLE '.$t('catalog_invoice_snapshots').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL UNIQUE,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,product_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,quantity INT UNSIGNED NOT NULL,snapshot LONGTEXT NOT NULL,created_at DATETIME NOT NULL)'.$e,
    ];
}
function catalog_ready(): bool {return staff_ready()&&studio_ready()&&setting('catalog_schema_version')==='1';}
function catalog_families(): array {return ['web-design'=>'Web design','maintenance'=>'Website care','addon'=>'Additional service','hosting'=>'Hosting (provider pending)','domain'=>'Domain (provider pending)','vps'=>'VPS (provider pending)'];}
function catalog_terms(): array {return ['once'=>'One-time service','month'=>'One month of service','year'=>'One year of service'];}
function catalog_audit(int $actor,string $action,string $detail): void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'catalog.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
function catalog_validate_schema(string $name,string $sql): void {
    $cols=all('SHOW FULL COLUMNS FROM '.table($name));$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
    if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Catalog tables require InnoDB.');
    preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\)|DATETIME|LONGTEXT|TEXT)([^,]*)/',$sql,$matches,PREG_SET_ORDER);
    if(count($cols)!==count($matches))throw new RuntimeException('Incompatible catalog columns. Existing data was preserved.');
    foreach($matches as $i=>$m){$c=$cols[$i];$type=preg_replace('/\b(int|bigint)\([0-9]+\)/','$1',strtolower($c['Type']));$nullable=!str_contains($m[3],'NOT NULL')&&!str_contains($m[3],'PRIMARY KEY');
        if($c['Field']!==$m[1]||$type!==strtolower($m[2])||$c['Null']!==($nullable?'YES':'NO')||(str_contains($m[3],'AUTO_INCREMENT')&&!str_contains($c['Extra'],'auto_increment')))throw new RuntimeException('Incompatible '.$name.' columns. Existing data was preserved.');
        if(in_array($c['Field'],['request_key','request_hash'],true)&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible invoice request identifiers.');
    }
    $unique=[];foreach(all('SHOW INDEX FROM '.table($name)) as $k)if((int)$k['Non_unique']===0)$unique[$k['Key_name']][(int)$k['Seq_in_index']]=$k['Column_name'];
    $expected=['PRIMARY'=>['id']]+match($name){'catalog_products'=>['sku'=>['sku']],'catalog_revisions'=>['product_revision'=>['product_id','revision']],default=>['invoice_id'=>['invoice_id'],'request_key'=>['request_key']]};
    foreach($expected as $key=>$fields){$actual=$unique[$key]??[];ksort($actual);if(array_values($actual)!==$fields)throw new RuntimeException('Incompatible catalog unique keys.');}
}
function catalog_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=staff_owner($password);if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $missing=[];foreach(catalog_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))catalog_validate_schema($name,$sql);else $missing[]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        staff_transaction(function() use($actor): void {
            if(setting('catalog_schema_version')!=='1')foreach(array_keys(catalog_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Unrecognized catalog records exist. No data was adopted.');
            save_setting('catalog_schema_version','1');catalog_audit((int)$actor['id'],'setup','Additive catalog schema 1');
        });
    });
}
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
        query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',[$status,$note,$status==='paid'?gmdate('Y-m-d H:i:s'):null,$invoiceId]);
        catalog_audit((int)$actor['id'],'invoice.'.$status,'Invoice '.$invoiceId.' project '.$jobId);
    }));
}
