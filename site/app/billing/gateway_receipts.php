<?php
/** Authoritative gateway evidence. A receipt records verified production readback; it never dispatches fulfillment. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/ledger.php';
require_once CWP_CODE_ROOT.'/database/schema/gateway_receipts_schema.php';

function gateway_receipts_ready():bool {
    if(setting('gateway_receipts_schema_version')!=='1')return false;
    foreach(gateway_receipts_schema(config()['db']['prefix']) as $name=>$sql){
        if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))return false;
        gateway_receipts_validate_schema();
    }
    return true;
}
function gateway_receipts_require():void {
    if(!gateway_receipts_ready())throw new RuntimeException('Verified gateway receipt storage is not prepared.');
}
function gateway_receipt_account_hash(string $login):string {
    if($login===''||strlen($login)>128||preg_match('/[\x00-\x1F\x7F]/',$login))throw new InvalidArgumentException('Invalid gateway account identity.');
    return hash('sha256','authorize-net|'.$login);
}
function gateway_receipt_json(array $value):string {
    return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
function gateway_receipt_readback(array $readback,array $invoice,array $binding):void {
    $keys=['transaction_id','transaction_type','transaction_status','invoice_number','amount_cents','currency','customer_id','customer_email'];$actual=array_keys($readback);sort($keys);sort($actual);
    if($actual!==$keys||($readback['transaction_id']??null)!==($binding['transaction_id']??null)||($readback['transaction_type']??null)!=='authCaptureTransaction'
        ||($readback['transaction_status']??null)!==($binding['transaction_status']??null)||($readback['invoice_number']??null)!==substr('CWP-'.(int)$invoice['id'],0,20)
        ||($readback['amount_cents']??null)!==(int)$invoice['amount_cents']||($readback['currency']??null)!==$invoice['currency']
        ||!is_string($readback['customer_id']??null)||!preg_match('/^[A-Za-z0-9-]{3,64}$/D',$readback['customer_id'])
        ||!is_string($readback['customer_email']??null)||strtolower($readback['customer_email'])!==$readback['customer_email']||!filter_var($readback['customer_email'],FILTER_VALIDATE_EMAIL)){
        throw new RuntimeException('Gateway readback does not match the retained receipt binding.');
    }
}
/** Caller owns the shared mutex and transaction and has revalidated the current production gateway context. */
function gateway_receipt_record(array $invoice,int $entry,array $binding,array $readback):array {
    if(!db()->inTransaction())throw new LogicException('Gateway receipt recording needs its owning transaction.');
    gateway_receipts_require();
    foreach(['environment','account_sha256','transaction_id','source','source_id','attempt_key','transaction_status'] as $key)if(!is_string($binding[$key]??null))throw new InvalidArgumentException('Gateway receipt binding is incomplete.');
    if($binding['environment']!=='production'||!preg_match('/^[a-f0-9]{64}$/D',$binding['account_sha256'])
        ||!preg_match('/^[0-9]{5,30}$/D',$binding['transaction_id'])||!preg_match('/^[a-z][a-z0-9_-]{2,39}$/D',$binding['source'])
        ||!preg_match('/^[a-f0-9]{32,64}$/D',$binding['source_id'])||!preg_match('/^[a-f0-9]{64}$/D',$binding['attempt_key'])
        ||!in_array($binding['transaction_status'],['capturedPendingSettlement','settledSuccessfully'],true)
        ||!is_int($binding['gateway_revision']??null)||$binding['gateway_revision']<1)throw new InvalidArgumentException('Gateway receipt binding is invalid.');
    if($entry<1||(int)$invoice['id']<1||(int)$invoice['amount_cents']<1||$invoice['currency']!=='USD')throw new InvalidArgumentException('Gateway receipt invoice is invalid.');
    $payment=one('SELECT * FROM '.table('invoice_payments').' WHERE id=? FOR UPDATE',[$entry]);
    if(!$payment||(int)$payment['invoice_id']!==(int)$invoice['id']||(int)$payment['job_id']!==(int)$invoice['job_id']
        ||(int)$payment['client_id']!==(int)$invoice['client_id']||(int)$payment['amount_cents']!==(int)$invoice['amount_cents']
        ||$payment['currency']!==$invoice['currency']||$payment['kind']!=='gateway'||(int)$payment['actor_id']!==0
        ||$payment['method']!=='authorize_net'||!hash_equals((string)$payment['invoice_hash'],ledger_invoice_hash($invoice))
        ||!hash_equals((string)$payment['request_key'],$binding['attempt_key'])
        ||!hash_equals((string)$payment['external_hash'],hash('sha256','authorize-net|'.$binding['transaction_id']))){
        throw new RuntimeException('Ledger entry does not match the verified gateway receipt.');
    }
    gateway_receipt_readback($readback,$invoice,$binding);$json=gateway_receipt_json($readback);$readbackHash=hash('sha256',$json);$now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('gateway_receipts').' (ledger_entry_id,invoice_id,job_id,client_id,amount_cents,currency,invoice_sha256,environment,account_sha256,gateway_revision,transaction_id,source,source_id,attempt_key,transaction_status,readback_json,readback_sha256,status,created_at,reviewed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)',[
        $entry,$invoice['id'],$invoice['job_id'],$invoice['client_id'],$invoice['amount_cents'],$invoice['currency'],ledger_invoice_hash($invoice),$binding['environment'],$binding['account_sha256'],$binding['gateway_revision'],$binding['transaction_id'],$binding['source'],$binding['source_id'],$binding['attempt_key'],$binding['transaction_status'],$json,$readbackHash,'verified',$now
    ]);
    $id=(int)db()->lastInsertId();$detail='Receipt '.$id.' entry '.$entry.' invoice '.$invoice['id'].' source '.$binding['source'].' '.$binding['source_id'].' transaction '.$binding['transaction_id'].' sha256 '.$readbackHash;
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (0,?,?,?)',['gateway_receipts.recorded',$detail,$now]);
    return gateway_receipt_for_entry($payment,$invoice);
}
/** Validate one actor-zero ledger entry against its exact central receipt and immutable audit witness. */
function gateway_receipt_for_entry(array $payment,array $invoice):array {
    gateway_receipts_require();$entry=(int)($payment['id']??0);
    $rows=all('SELECT * FROM '.table('gateway_receipts').' WHERE ledger_entry_id=? LIMIT 2',[$entry]);
    if(count($rows)!==1)throw new RuntimeException('Gateway receipt origin is missing or ambiguous.');
    $r=$rows[0];$json=(string)$r['readback_json'];
    if((int)$r['ledger_entry_id']!==$entry||(int)$r['invoice_id']!==(int)$invoice['id']||(int)$r['job_id']!==(int)$invoice['job_id']
        ||(int)$r['client_id']!==(int)$invoice['client_id']||(int)$r['amount_cents']!==(int)$payment['amount_cents']
        ||$r['currency']!==$invoice['currency']||!hash_equals($r['invoice_sha256'],ledger_invoice_hash($invoice))
        ||$r['environment']!=='production'||!preg_match('/^[a-f0-9]{64}$/D',$r['account_sha256'])
        ||(int)$r['gateway_revision']<1||!preg_match('/^[0-9]{5,30}$/D',$r['transaction_id'])
        ||!preg_match('/^[a-z][a-z0-9_-]{2,39}$/D',$r['source'])||!preg_match('/^[a-f0-9]{32,64}$/D',$r['source_id'])
        ||!preg_match('/^[a-f0-9]{64}$/D',$r['attempt_key'])||!hash_equals($r['attempt_key'],(string)$payment['request_key'])
        ||!in_array($r['transaction_status'],['capturedPendingSettlement','settledSuccessfully'],true)
        ||!in_array($r['status'],['verified','reversal_review'],true)||!hash_equals($r['readback_sha256'],hash('sha256',$json))
        ||!hash_equals((string)$payment['external_hash'],hash('sha256','authorize-net|'.$r['transaction_id']))){
        throw new RuntimeException('Gateway receipt differs from the retained invoice payment.');
    }
    $decoded=json_decode($json,true,32,JSON_THROW_ON_ERROR);if(!is_array($decoded))throw new RuntimeException('Gateway readback is invalid.');gateway_receipt_readback($decoded,$invoice,$r);
    $detail='Receipt '.$r['id'].' entry '.$entry.' invoice '.$invoice['id'].' source '.$r['source'].' '.$r['source_id'].' transaction '.$r['transaction_id'].' sha256 '.$r['readback_sha256'];
    $audit=all('SELECT user_id FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['gateway_receipts.recorded',$detail]);
    if(count($audit)!==1||(int)$audit[0]['user_id']!==0)throw new RuntimeException('Gateway receipt audit witness is missing.');
    return ['id'=>(int)$r['id'],'ledger_entry_id'=>$entry,'invoice_id'=>(int)$r['invoice_id'],'client_id'=>(int)$r['client_id'],
        'amount_cents'=>(int)$r['amount_cents'],'currency'=>$r['currency'],'environment'=>$r['environment'],'account_sha256'=>$r['account_sha256'],
        'gateway_revision'=>(int)$r['gateway_revision'],'transaction_id'=>$r['transaction_id'],'transaction_status'=>$r['transaction_status'],
        'source'=>$r['source'],'source_id'=>$r['source_id'],'attempt_key'=>$r['attempt_key'],'status'=>$r['status'],'readback_sha256'=>$r['readback_sha256']];
}
function gateway_receipt_mark_reversal_review(string $environment,string $accountSha256,string $transactionId,string $eventId):bool {
    if(!in_array($environment,['sandbox','production'],true)||!preg_match('/^[a-f0-9]{64}$/D',$accountSha256)||!preg_match('/^[0-9]{5,30}$/D',$transactionId)||!preg_match('/^[A-Za-z0-9._:-]{4,128}$/D',$eventId))throw new InvalidArgumentException('Invalid gateway reversal identity.');
    return staff_locked(fn()=>staff_transaction(function()use($environment,$accountSha256,$transactionId,$eventId):bool {
        gateway_receipts_require();$rows=all('SELECT * FROM '.table('gateway_receipts').' WHERE environment=? AND account_sha256=? AND transaction_id=? FOR UPDATE',[$environment,$accountSha256,$transactionId]);
        if(!$rows)return false;if(count($rows)!==1)throw new RuntimeException('Gateway transaction identity is ambiguous within the selected merchant account.');$row=$rows[0];
        if($row['status']==='reversal_review')return true;if($row['status']!=='verified')throw new RuntimeException('Gateway receipt state is unsupported.');
        query('UPDATE '.table('gateway_receipts').' SET status=?,reviewed_at=? WHERE id=?',['reversal_review',gmdate('Y-m-d H:i:s'),$row['id']]);
        payments_audit(0,'gateway_receipts.reversal_review','Receipt '.$row['id'].' transaction '.$transactionId.' event '.hash('sha256',$eventId));return true;
    }));
}
