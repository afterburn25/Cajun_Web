<?php
/** Real external-payment records. Never consumes sandbox observations or calls a gateway. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;
function ledger_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    return ['invoice_payments'=>'CREATE TABLE `'.$prefix.'invoice_payments` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,kind VARCHAR(10) NOT NULL,amount_cents INT UNSIGNED NOT NULL,currency CHAR(3) NOT NULL,method VARCHAR(20) NOT NULL,external_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,private_note VARCHAR(500) NOT NULL,received_on VARCHAR(10) NOT NULL,invoice_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,invoice_reference VARCHAR(40) NOT NULL,invoice_title VARCHAR(160) NOT NULL,invoice_total_cents INT UNSIGNED NOT NULL,client_name VARCHAR(120) NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,reversal_of BIGINT UNSIGNED NULL UNIQUE,created_at DATETIME NOT NULL,INDEX invoice_history(invoice_id,id),INDEX receipt_reference(invoice_id,external_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
// Fresh read is necessary when an older request waited while another owner enabled the ledger.
function ledger_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['invoice_ledger_schema_version'])['value']??'')==='1';}
function ledger_require(): array {if(!ledger_ready())throw new RuntimeException('Enable invoice payments first.');return payments_require();}
function ledger_invoice(int $id): array {
    $i=one('SELECT i.*,j.client_id,c.active,c.name AS client_name FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE i.id=?',[$id]);
    if(!$i)throw new RuntimeException('Invoice not found.');return $i;
}
/** Independent of mutable payment status/history; protects the original invoiced obligation. */
function ledger_invoice_hash(array $i): string {return hash('sha256',json_encode([(int)$i['id'],(int)$i['job_id'],(int)$i['client_id'],(int)$i['amount_cents'],$i['currency'],$i['reference'],$i['title'],$i['instructions'],catalog_snapshot((int)$i['id'])],JSON_THROW_ON_ERROR));}
function ledger_balance(array $i): array {
    if(!ledger_ready())return ['paid_cents'=>$i['status']==='paid'?(int)$i['amount_cents']:0,'due_cents'=>$i['status']==='issued'?(int)$i['amount_cents']:0,'revision'=>0];
    $v=one('SELECT COUNT(*) AS entries,COALESCE(MAX(id),0) AS revision,COALESCE(SUM(CASE WHEN kind=\'reversal\' THEN -CAST(amount_cents AS SIGNED) ELSE amount_cents END),0) AS paid,MIN(invoice_hash) AS first_hash,MAX(invoice_hash) AS last_hash FROM '.table('invoice_payments').' WHERE invoice_id=?',[$i['id']]);
    $paid=(int)$v['paid'];$total=(int)$i['amount_cents'];
    if((int)$v['entries']&&($v['first_hash']!==$v['last_hash']||!hash_equals($v['first_hash'],ledger_invoice_hash($i))))throw new RuntimeException('Invoice details differ from retained payment history. Ask an owner to investigate.');
    if($paid<0||$paid>$total||!in_array($i['status'],['issued','paid','void'],true)||($i['status']==='paid'&&$paid!==$total)||($i['status']==='issued'&&$paid===$total)||($i['status']==='void'&&$paid!==0))throw new RuntimeException('Invoice status and payment history do not agree. Ask an owner to investigate.');
    return ['paid_cents'=>$paid,'due_cents'=>$i['status']==='void'?0:$total-$paid,'revision'=>(int)$v['revision']];
}
function ledger_review_hash(array $i): string {return hash('sha256',ledger_invoice_hash($i).'|'.json_encode(ledger_balance($i),JSON_THROW_ON_ERROR).'|'.$i['status']);}
function ledger_methods(): array {return ['bank'=>'Bank transfer','check'=>'Check','cash'=>'Cash','external'=>'Other verified external payment'];}
function ledger_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=payments_owner($password);if(!payments_ready())throw new RuntimeException('Enable payment preparation first.');
        foreach(ledger_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
                catalog_validate_schema($name,$sql,['request_key'=>['request_key'],'reversal_of'=>['reversal_of']]);
                foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if(in_array($c['Field'],['external_hash','invoice_hash','request_key','request_hash'],true)&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible invoice payment identifiers.');
            }else db()->exec($sql);
        }
        staff_transaction(function() use($actor): void {
            if(!ledger_ready()){
                if((int)query('SELECT COUNT(*) FROM '.table('invoice_payments'))->fetchColumn())throw new RuntimeException('Unrecognized invoice payment records exist. No data was adopted.');
                $last=0;$count=0;
                do{
                    $rows=all('SELECT id FROM '.table('studio_invoices')." WHERE status='paid' AND id>? ORDER BY id LIMIT 100",[$last]);
                    foreach($rows as $r){
                        $i=ledger_invoice((int)$r['id']);$last=(int)$i['id'];
                        if($i['currency']!=='USD'||(int)$i['amount_cents']<1||(int)$i['amount_cents']>99999999)throw new RuntimeException('A legacy paid invoice is outside the supported USD range. No balances were imported.');
                        ledger_insert($i,(int)$actor['id'],'legacy',(int)$i['amount_cents'],'legacy',$i['paid_note']?:'Legacy paid balance imported; no new payment verified',$i['paid_at']?substr($i['paid_at'],0,10):'');$count++;
                    }
                }while(count($rows)===100);
                save_setting('invoice_ledger_schema_version','1');payments_audit((int)$actor['id'],'ledger.setup','Imported '.$count.' legacy paid balances; no new payments or gateway evidence');
            }else payments_audit((int)$actor['id'],'ledger.setup','Schema verified; retained balances unchanged');
        });
    });
}
/** Append under the shared mutex/transaction. There is deliberately no entry update/delete operation. */
function ledger_insert(array $i,int $actor,string $kind,int $amount,string $method,string $note,string $date,?string $key=null,?string $hash=null,?string $external=null,?int $reversal=null): int {
    query('INSERT INTO '.table('invoice_payments').' (invoice_id,job_id,client_id,actor_id,kind,amount_cents,currency,method,external_hash,private_note,received_on,invoice_hash,invoice_reference,invoice_title,invoice_total_cents,client_name,request_key,request_hash,reversal_of,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$i['id'],$i['job_id'],$i['client_id'],$actor,$kind,$amount,$i['currency'],$method,$external,$note,$date,ledger_invoice_hash($i),$i['reference'],$i['title'],$i['amount_cents'],$i['client_name'],$key,$hash,$reversal,gmdate('Y-m-d H:i:s')]);
    $id=(int)db()->lastInsertId();payments_audit($actor,'ledger.'.$kind,'Entry '.$id.' invoice '.$i['id'].' amount '.$amount.' '.$i['currency'].($reversal?' reverses '.$reversal:''));return $id;
}
function ledger_key(string $key,string $review): void {if(!preg_match('/^[a-f0-9]{64}$/D',$key)||!preg_match('/^[a-f0-9]{64}$/D',$review))throw new InvalidArgumentException('Reload and review this invoice.');}
function ledger_receive(int $invoice,int $amount,string $method,string $reference,string $date,string $key,string $review): int {
    ledger_key($key,$review);$reference=trim($reference);
    if($amount<1||$amount>99999999||!isset(ledger_methods()[$method])||strlen($reference)<5||strlen($reference)>500||!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])||$date>gmdate('Y-m-d')||$date<'1900-01-01')throw new InvalidArgumentException('Enter a positive USD amount, payment method, verified reference and valid receipt date no later than today.');
    return staff_locked(fn()=>staff_transaction(function() use($invoice,$amount,$method,$reference,$date,$key,$review): int {
        $actor=ledger_require();$hash=hash('sha256',json_encode([$invoice,$amount,$method,$reference,$date,$review],JSON_THROW_ON_ERROR));
        $old=one('SELECT * FROM '.table('invoice_payments').' WHERE request_key=?',[$key]);
        if($old){if((int)$old['actor_id']!==(int)$actor['id']||$old['kind']!=='manual'||!hash_equals($old['request_hash'],$hash))throw new RuntimeException('This receipt request was already used with different details.');return (int)$old['id'];}
        $i=ledger_invoice($invoice);$balance=ledger_balance($i);
        if($i['status']!=='issued'||(int)$i['active']!==1||$i['currency']!=='USD'||(int)$i['amount_cents']>99999999)throw new RuntimeException('Choose an issued USD invoice for an active client.');
        if(!hash_equals(ledger_review_hash($i),$review))throw new RuntimeException('The invoice balance changed. Reload and review before recording payment.');
        if($amount>$balance['due_cents'])throw new RuntimeException('The receipt exceeds the remaining invoice balance. No payment was recorded.');
        attempts_assert_clear($invoice);
        $external=hash('sha256',$method.'|'.strtolower(preg_replace('/\s+/',' ',$reference)));
        if(one('SELECT p.id FROM '.table('invoice_payments').' p LEFT JOIN '.table('invoice_payments')." r ON r.reversal_of=p.id WHERE p.invoice_id=? AND p.kind='manual' AND p.external_hash=? AND r.id IS NULL",[$invoice,$external]))throw new RuntimeException('This external payment reference is already recorded for this invoice.');
        $id=ledger_insert($i,(int)$actor['id'],'manual',$amount,$method,$reference,$date,$key,$hash,$external);
        $paid=$balance['paid_cents']+$amount;$full=$paid===(int)$i['amount_cents'];
        query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',[$full?'paid':'issued',$full?'Payment history entry '.$id:'',$full?gmdate('Y-m-d H:i:s'):null,$invoice]);
        query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND status='prepared'",[$invoice]);
        return $id;
    }));
}
function ledger_reverse(int $entry,string $reason,string $key,string $review,#[\SensitiveParameter] string $password): int {
    ledger_key($key,$review);$reason=trim($reason);if(strlen($reason)<10||strlen($reason)>500)throw new InvalidArgumentException('Explain the recording correction (10–500 characters).');
    return staff_locked(fn()=>staff_transaction(function() use($entry,$reason,$key,$review,$password): int {
        $actor=payments_owner($password);ledger_require();$hash=hash('sha256',json_encode([$entry,$reason,$review],JSON_THROW_ON_ERROR));
        $old=one('SELECT * FROM '.table('invoice_payments').' WHERE request_key=?',[$key]);
        if($old){if((int)$old['actor_id']!==(int)$actor['id']||$old['kind']!=='reversal'||!hash_equals($old['request_hash'],$hash))throw new RuntimeException('This correction request was already used with different details.');return (int)$old['id'];}
        $p=one('SELECT * FROM '.table('invoice_payments').' WHERE id=?',[$entry]);
        if(!$p||!in_array($p['kind'],['manual','legacy'],true)||one('SELECT id FROM '.table('invoice_payments').' WHERE reversal_of=?',[$entry]))throw new RuntimeException('Choose an unreversed payment or legacy balance.');
        $i=ledger_invoice((int)$p['invoice_id']);$balance=ledger_balance($i);
        if(!hash_equals(ledger_review_hash($i),$review))throw new RuntimeException('The invoice balance changed. Reload before correcting it.');
        if($i['status']==='void'||$balance['paid_cents']<(int)$p['amount_cents'])throw new RuntimeException('This entry cannot be reversed against the current balance.');
        attempts_assert_clear((int)$i['id']);
        $id=ledger_insert($i,(int)$actor['id'],'reversal',(int)$p['amount_cents'],$p['method'],$reason,gmdate('Y-m-d'),$key,$hash,null,$entry);
        query('UPDATE '.table('studio_invoices')." SET status='issued',paid_note='',paid_at=NULL WHERE id=?",[$i['id']]);
        query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND status='prepared'",[$i['id']]);return $id;
    }));
}
/** Ownership is checked against both the current project and the immutable entry when printing. */
function ledger_client_invoice(int $id): array {
    $client=studio_client();if(!$client||!ledger_ready())throw new RuntimeException('Invoice unavailable.');
    $i=ledger_invoice($id);if((int)$i['client_id']!==(int)$client['id']||(int)$i['active']!==1)throw new RuntimeException('Invoice unavailable.');return $i;
}
function ledger_client_receipt(int $id): array {
    if(support_current())throw new RuntimeException('Receipts are unavailable during support.');
    if(!ledger_ready()||!($client=studio_client()))throw new RuntimeException('Receipt unavailable.');
    $p=one('SELECT p.id,p.invoice_id,p.client_id,p.kind,p.amount_cents,p.currency,p.method,p.received_on,p.invoice_reference,p.invoice_title,p.invoice_total_cents,p.client_name,p.created_at,r.id AS correction_id FROM '.table('invoice_payments').' p LEFT JOIN '.table('invoice_payments')." r ON r.reversal_of=p.id WHERE p.id=? AND p.kind='manual'",[$id]);
    if(!$p||(int)$p['client_id']!==(int)$client['id'])throw new RuntimeException('Receipt unavailable.');ledger_client_invoice((int)$p['invoice_id']);return $p;
}
