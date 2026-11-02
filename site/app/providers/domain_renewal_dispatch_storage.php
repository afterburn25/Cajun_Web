<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/domain_renewal_dispatch_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function domain_renewal_dispatch_tables():array {return ['allocation'=>'domain_renewal_invoices','approval'=>'domain_renewal_dispatches','terminal'=>'domain_renewal_dispatch_ends'];}
function domain_renewal_dispatch_exists(string $name):bool {return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;}
function domain_renewal_dispatch_columns(string $kind):array {
    return match($kind){
      'allocation'=>['id','invoice_id','binding_id','request_id','client_id','actor_id','document_json','document_sha256','proof'],
      'approval'=>['id','allocation_id','request_id','grant_seq','profile_id','domain_id','domain','client_id','actor_id','document_json','document_sha256','proof'],
      'terminal'=>['id','actor_id','disposition','ledger_sha256','document_json','document_sha256','proof'],
      default=>throw new LogicException('Unknown renewal dispatch witness.'),
    };
}
function domain_renewal_dispatch_values(string $kind,array $row):array {return array_map(static fn($key)=>(string)$row[$key],domain_renewal_dispatch_columns($kind));}
function domain_renewal_dispatch_digest(array $values):string {return hash('sha256',implode('',array_map(static fn($v)=>strlen((string)$v).':'.(string)$v,$values)));}
function domain_renewal_dispatch_sql_digest(array $fields):string {return 'SHA2(CONCAT('.implode(',',array_map(static fn($v)=>"OCTET_LENGTH(CAST($v AS BINARY)),BINARY ':',CAST($v AS BINARY)",$fields)).'),256)';}
function domain_renewal_dispatch_prove(string $kind,array $row):array {
    $columns=domain_renewal_dispatch_columns($kind);array_pop($columns);if(array_keys($row)!==$columns)throw new LogicException('Invalid renewal witness fields.');
    $row['proof']=hash_hmac('sha256','renewal-dispatch|'.$kind.'|'.DomainRenewalInvoiceDocument::json(array_map('strval',array_values($row))),provider_settings_vault_key());return $row;
}
function domain_renewal_dispatch_decode(string $kind,array $row):array {
    $columns=domain_renewal_dispatch_columns($kind);$original=[];foreach($columns as $k)$original[$k]=$row[$k];$proof=$original['proof'];unset($original['proof']);
    if(!hash_equals($proof,domain_renewal_dispatch_prove($kind,$original)['proof'])||!hash_equals($row['document_sha256'],hash('sha256',$row['document_json'])))throw new RuntimeException('Renewal dispatch proof needs recovery.');
    $d=json_decode($row['document_json'],true,20,JSON_THROW_ON_ERROR);if(!is_array($d)||DomainRenewalInvoiceDocument::json($d)!==$row['document_json'])throw new RuntimeException('Renewal dispatch document needs recovery.');return $d;
}
/** Validate signed identity before indexed absence can authorize reuse or hide a prior domain attempt. */
function domain_renewal_dispatch_identity(string $kind,array $row):void {
    $d=domain_renewal_dispatch_decode($kind,$row);
    if($kind==='allocation'){
        $d=DomainRenewalInvoiceDocument::fromJson($row['document_json'])->data();$expected=['id'=>$d['id'],'invoice_id'=>$d['invoice']['id'],'binding_id'=>$d['binding']['id'],'request_id'=>$d['binding']['request_id'],'client_id'=>$d['binding']['registrar']['client_id'],'actor_id'=>$d['actor_id']];
    }elseif($kind==='approval'){
        $d=DomainRenewalDispatchDocument::fromJson($row['document_json'])->data();$expected=['id'=>$d['id'],'allocation_id'=>$d['allocation_id'],'request_id'=>$d['request_id'],'profile_id'=>$d['registrar']['profile_id'],'domain_id'=>$d['registrar']['domain_id'],'domain'=>$d['registrar']['domain'],'client_id'=>$d['client_id'],'actor_id'=>$d['actor_id']];
    }else{
        DomainRenewalInvoiceDocument::keys($d,['version','request_proof','phase','authority_current','ended_epoch','ledger_sha256']);if($d['version']!==1||!in_array($d['phase'],['not_sent','attempted'],true)||!is_bool($d['authority_current']))throw new RuntimeException('Renewal completion document needs recovery.');DomainRenewalInvoiceDocument::hash($d['request_proof']);DomainRenewalInvoiceDocument::hash($d['ledger_sha256']);DomainRenewalInvoiceDocument::number($d['ended_epoch'],1,PHP_INT_MAX);$expected=['disposition'=>$d['phase'],'ledger_sha256'=>$d['ledger_sha256']];
    }
    foreach($expected as $key=>$value)if((string)$row[$key]!== (string)$value)throw new RuntimeException('Renewal dispatch indexed identity needs recovery.');
}
function domain_renewal_dispatch_counter(string $kind):int {
    if(!isset(domain_renewal_dispatch_tables()[$kind]))throw new LogicException('Invalid renewal witness counter.');$s=one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_renewal_dispatch_'.$kind.'_count'])['value']??'';
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,14})\z/',$s))throw new RuntimeException('Renewal dispatch count needs recovery.');return (int)$s;
}
function domain_renewal_dispatch_ledger_fields():array{return ['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch'];}
function domain_renewal_dispatch_ledger_digest(?array $p):string {return $p===null?str_repeat('0',64):domain_renewal_dispatch_digest(array_map(static fn($k)=>(string)($p[$k]??''),domain_renewal_dispatch_ledger_fields()));}
/** Fixed-query correspondence protects all invoice indexes before a lookup can report unbound. */
function domain_renewal_dispatch_ready():bool {
    $v=one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_renewal_dispatch_schema_version'])['value']??'';$tables=domain_renewal_dispatch_tables();$a=table('audit');$count=static fn($sql)=>(int)query($sql)->fetchColumn();
    if($v===''){
        $used=one("SELECT id FROM $a WHERE BINARY action LIKE 'domain_renewal_dispatch.%' LIMIT 1")!==null||one('SELECT name FROM '.table('settings')." WHERE name LIKE 'domain_renewal_dispatch_%_count' LIMIT 1")!==null;
        foreach($tables as $name)if(domain_renewal_dispatch_exists($name)&&$count('SELECT COUNT(*) FROM '.table($name)))$used=true;
        foreach(['provider_operation_grants'=>"BINARY operation='domains.renew'",'provider_operations'=>"BINARY operation='domains.renew' OR BINARY idempotency_key LIKE 'domain-renewal:%'"] as $name=>$where)if(domain_renewal_dispatch_exists($name)&&one('SELECT id FROM '.table($name).' WHERE '.$where.' LIMIT 1'))$used=true;
        if(domain_renewal_dispatch_exists('studio_invoices')&&one("SELECT id FROM ".table('studio_invoices')." WHERE OCTET_LENGTH(reference)=35 AND reference COLLATE utf8mb4_bin REGEXP '^DR-[a-f0-9]{32}$' LIMIT 1"))$used=true;
        if($used)throw new RuntimeException('Renewal dispatch history needs recovery.');return false;
    }
    if($v!=='1'||!domain_renewal_agreements_ready()||!provider_operations_ready()||!payments_ready()||!ledger_ready())throw new RuntimeException('Renewal dispatch storage needs recovery.');
    foreach([...array_values($tables),'settings','audit','provider_operation_grants','provider_operations','studio_invoices','invoice_payments'] as $name){$e=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($e['ENGINE']??'')!=='innodb')throw new RuntimeException('Renewal dispatch requires original transactional storage.');}
    if($count("SELECT COUNT(*) FROM $a WHERE BINARY action='domain_renewal_dispatch.prepared'")!==1||$count("SELECT COUNT(*) FROM $a WHERE BINARY action='domain_renewal_dispatch.prepared' AND BINARY detail='Renewal dispatch storage ready'")!==1)throw new RuntimeException('Renewal dispatch preparation needs recovery.');
    foreach($tables as $kind=>$name){
        $t=table($name);$n=domain_renewal_dispatch_counter($kind);$action='domain_renewal_dispatch.'.$kind;
        if($n!==$count("SELECT COUNT(*) FROM $t")||$n!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='$action'"))throw new RuntimeException('Renewal dispatch history counts disagree.');
        $digest=domain_renewal_dispatch_sql_digest(array_map(static fn($k)=>'w.'.$k,domain_renewal_dispatch_columns($kind)));$detail="CONCAT(BINARY 'Renewal $kind ',w.id,BINARY ' ',$digest)";
        if(one("SELECT w.id FROM $t w WHERE BINARY w.document_sha256<>BINARY SHA2(w.document_json,256) OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail AND a.user_id=w.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail)<>1 LIMIT 1"))throw new RuntimeException('Renewal dispatch witness correspondence needs recovery.');
        // SQL counts/audits are independent retained evidence, not substitutes for the keyed original.
        // Stream a fixed table query so every signed row is checked without a whole-history PHP array.
        $originals=query("SELECT * FROM $t ORDER BY id");while($original=$originals->fetch(PDO::FETCH_ASSOC))domain_renewal_dispatch_identity($kind,$original);$originals->closeCursor();
    }
    $i=table($tables['allocation']);$q=table($tables['approval']);$t=table($tables['terminal']);$g=table('provider_operation_grants');$p=table('provider_operations');
    if(one("SELECT w.id FROM $i w LEFT JOIN ".table('domain_renewal_agreements')." b ON b.id=w.binding_id AND b.request_id=w.request_id AND b.client_id=w.client_id LEFT JOIN ".table('studio_invoices')." n ON n.id=w.invoice_id WHERE b.id IS NULL OR n.id IS NULL LIMIT 1"))throw new RuntimeException('Renewal invoice origin needs recovery.');
    if(domain_renewal_dispatch_counter('approval')!==$count("SELECT COUNT(*) FROM $g WHERE BINARY operation='domains.renew'"))throw new RuntimeException('Renewal approval history needs recovery.');
    if(one("SELECT q.id FROM $q q LEFT JOIN $g g ON g.id=q.id LEFT JOIN $i i ON i.id=q.allocation_id AND i.request_id=q.request_id AND i.client_id=q.client_id WHERE i.id IS NULL OR g.id IS NULL OR BINARY g.operation<>'domains.renew' OR g.seq<>q.grant_seq OR BINARY g.profile_id<>BINARY q.profile_id OR g.granted_by<>q.actor_id OR BINARY g.scope_params<>BINARY q.document_json OR BINARY g.consumed_key<>BINARY CONCAT(BINARY 'domain-renewal:',q.id) OR g.consumed_at IS NULL LIMIT 1"))throw new RuntimeException('Renewal approval correspondence needs recovery.');
    if(one("SELECT p.id FROM $p p LEFT JOIN $q q ON BINARY p.idempotency_key=BINARY CONCAT(BINARY 'domain-renewal:',q.id) WHERE (BINARY p.operation='domains.renew' OR BINARY p.idempotency_key LIKE 'domain-renewal:%') AND q.id IS NULL LIMIT 1"))throw new RuntimeException('Renewal execution lost its original approval.');
    $ledger=domain_renewal_dispatch_sql_digest(array_map(static fn($k)=>"COALESCE(CAST(p.$k AS CHAR),'')",domain_renewal_dispatch_ledger_fields()));
    if(one("SELECT t.id FROM $t t LEFT JOIN $q q ON q.id=t.id LEFT JOIN $p p ON BINARY p.idempotency_key=BINARY CONCAT(BINARY 'domain-renewal:',t.id) WHERE q.id IS NULL OR BINARY t.disposition NOT IN ('not_sent','attempted') OR (BINARY t.disposition='not_sent' AND p.id IS NOT NULL) OR BINARY t.ledger_sha256<>BINARY IF(p.id IS NULL,REPEAT('0',64),$ledger) LIMIT 1"))throw new RuntimeException('Renewal completion evidence needs recovery.');
    domain_renewal_dispatch_creation_ready();
    return true;
}
function domain_renewal_dispatch_insert(string $kind,array $row):void {
    domain_renewal_agreements_locked();if(array_keys($row)!==domain_renewal_dispatch_columns($kind))throw new LogicException('Invalid renewal witness insert.');$n=domain_renewal_dispatch_counter($kind);if($n>=999999999999999)throw new RuntimeException('Renewal history is full.');
    query('INSERT INTO '.table(domain_renewal_dispatch_tables()[$kind]).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$row['actor_id'],'domain_renewal_dispatch.'.$kind,'Renewal '.$kind.' '.$row['id'].' '.domain_renewal_dispatch_digest(domain_renewal_dispatch_values($kind,$row)),gmdate('Y-m-d H:i:s')]);save_setting('domain_renewal_dispatch_'.$kind.'_count',(string)($n+1));
}
function domain_renewal_dispatch_validate_schema():void {
    $indexes=[
      'domain_renewal_invoices'=>['PRIMARY'=>[0,['id']],'renewal_invoice_identity'=>[0,['invoice_id']],'renewal_invoice_binding'=>[0,['binding_id']],'renewal_invoice_request'=>[1,['request_id']]],
      'domain_renewal_dispatches'=>['PRIMARY'=>[0,['id']],'renewal_dispatch_allocation'=>[0,['allocation_id']],'renewal_dispatch_request'=>[0,['request_id']],'renewal_dispatch_seq'=>[0,['grant_seq']],'renewal_dispatch_domain'=>[1,['domain']]],
      'domain_renewal_dispatch_ends'=>['PRIMARY'=>[0,['id']]],
    ];$foreigns=['domain_renewal_invoices'=>['invoice_id'=>'studio_invoices','binding_id'=>'domain_renewal_agreements'],'domain_renewal_dispatches'=>['id'=>'provider_operation_grants','allocation_id'=>'domain_renewal_invoices'],'domain_renewal_dispatch_ends'=>['id'=>'domain_renewal_dispatches']];
    foreach(domain_renewal_dispatch_schema(config()['db']['prefix']) as $name=>$sql){
        $unique=[];foreach($indexes[$name] as $k=>$i)if($k!=='PRIMARY'&&$i[0]===0)$unique[$k]=$i[1];catalog_validate_schema($name,$sql,$unique);
        foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){$text=preg_match('/^(?:char|varchar|longtext)/i',$c['Type'])===1;if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==($text?'ascii_bin':null))throw new RuntimeException('Incompatible renewal dispatch columns.');}
        $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible renewal dispatch indexes.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);$expected=$indexes[$name];ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible renewal dispatch indexes.');
        $actual=[];foreach(all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]) as $f){if($f['REFERENCED_COLUMN_NAME']!=='id'||!in_array($f['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($f['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible renewal dispatch relationships.');$actual[$f['COLUMN_NAME']]=$f['REFERENCED_TABLE_NAME'];}$expected=array_map(static fn($target)=>config()['db']['prefix'].$target,$foreigns[$name]);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible renewal dispatch relationships.');
    }
}
function domain_renewal_dispatch_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Renewal dispatch preparation cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        $actor=domain_renewal_dispatch_owner($password,false);if(!domain_renewal_agreements_ready()||!provider_operations_ready())throw new RuntimeException('Prepare renewal agreements and provider operations first.');
        if(domain_renewal_dispatch_ready()){domain_renewal_dispatch_validate_schema();return;}
        foreach(domain_renewal_dispatch_schema(config()['db']['prefix']) as $name=>$sql)if(!domain_renewal_dispatch_exists($name))db()->exec($sql);domain_renewal_dispatch_validate_schema();
        staff_transaction(function()use($actor):void{save_setting('domain_renewal_dispatch_schema_version','1');foreach(array_keys(domain_renewal_dispatch_tables()) as $kind)save_setting('domain_renewal_dispatch_'.$kind.'_count','0');query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor['id'],'domain_renewal_dispatch.prepared','Renewal dispatch storage ready',gmdate('Y-m-d H:i:s')]);});
    });
}
