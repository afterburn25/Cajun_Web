<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/provider_subdomain_add_schema.php';

function provider_subdomain_add_tables():array {return ['request'=>'provider_subdomain_add_requests','terminal'=>'provider_subdomain_add_terminals','check'=>'provider_subdomain_add_checks'];}
function provider_subdomain_add_exists(string $name):bool {
    if(!in_array($name,provider_subdomain_add_tables(),true))throw new LogicException('Unknown Subdomain add table.');
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;
}
/** Only this feature's exact table namespace may participate in preparation. */
function provider_subdomain_add_present():array {
    $prefix=config()['db']['prefix'].'provider_subdomain_add_';$known=array_values(provider_subdomain_add_tables());$found=[];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen($prefix),$prefix]) as $row){
        $name=substr($row['TABLE_NAME'],strlen(config()['db']['prefix']));
        if(!in_array($name,$known,true))throw new RuntimeException('Unknown Subdomain add storage needs recovery.');$found[]=$name;
    }
    return array_values(array_filter($known,static fn($name)=>in_array($name,$found,true)));
}
function provider_subdomain_add_counter(string $kind):int {
    if(!isset(provider_subdomain_add_tables()[$kind]))throw new LogicException('Unknown Subdomain add counter.');
    $n=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_subdomain_add_'.$kind.'_count'])['value']??'';
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,14})\z/',$n))throw new RuntimeException('Subdomain add counts need recovery.');return (int)$n;
}
function provider_subdomain_add_hmac(string $kind,array $data):string {return hash_hmac('sha256','subdomain-add|'.$kind.'|'.provider_document_roots_json($data),provider_settings_vault_key());}
function provider_subdomain_add_columns(string $kind):array {
    return match($kind){
        'request'=>['id','grant_seq','profile_id','package_id','subdomain','link_id','actor_id','document_json','document_sha256','proof'],
        'terminal'=>['id','actor_id','disposition','ledger_sha256','document_json','document_sha256','proof'],
        'check'=>['id','request_id','actor_id','receipt_id','disposition','document_json','document_sha256','proof'],
        default=>throw new LogicException('Unknown Subdomain add witness.'),
    };
}
function provider_subdomain_add_values(string $kind,array $row):array {return array_map(static fn($key)=>(string)$row[$key],provider_subdomain_add_columns($kind));}
function provider_subdomain_add_ledger_values(array $row):array {
    return array_map(static fn($key)=>(string)($row[$key]??''),['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch','reconciled_outcome','reconciled_epoch']);
}
function provider_subdomain_add_ledger_digest(?array $row):string {return $row===null?str_repeat('0',64):provider_document_roots_digest(provider_subdomain_add_ledger_values($row));}
function provider_subdomain_add_ledger_sql():string {
    return provider_document_roots_sql_digest(array_map(static fn($key)=>"COALESCE(CAST(p.$key AS CHAR),'')",['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch','reconciled_outcome','reconciled_epoch']));
}
/** No schema repair on GET. An absent, never-used feature is the only no-op case. */
function provider_subdomain_add_ready():bool {
    provider_subdomain_add_guard(false);
    $marker='provider_subdomain_add_schema_version';$settingPrefix='provider_subdomain_add_';$settings=[];$allowed=[$marker];
    foreach(array_keys(provider_subdomain_add_tables()) as $kind)$allowed[]=$settingPrefix.$kind.'_count';
    foreach(all('SELECT name,value FROM '.table('settings').' WHERE BINARY LEFT(name,?)=BINARY ?',[strlen($settingPrefix),$settingPrefix]) as $row){
        if(!in_array($row['name'],$allowed,true)||array_key_exists($row['name'],$settings))throw new RuntimeException('Unknown Subdomain add settings need recovery.');$settings[$row['name']]=$row['value'];
    }
    $version=$settings[$marker]??'';$present=provider_subdomain_add_present();
    $auditPrefix='provider.subdomain_add_';$actions=array_map(static fn($kind)=>$auditPrefix.$kind,['prepared','request','terminal','check']);
    foreach(all('SELECT DISTINCT BINARY action AS action FROM '.table('audit').' WHERE BINARY LEFT(action,?)=BINARY ?',[strlen($auditPrefix),$auditPrefix]) as $row)if(!in_array($row['action'],$actions,true))throw new RuntimeException('Unknown Subdomain add audits need recovery.');
    $tables=provider_subdomain_add_tables();$a=table('audit');$count=static fn(string $sql):int=>(int)query($sql)->fetchColumn();
    if($version===''){
        $used=$settings!==[]||one("SELECT id FROM $a WHERE BINARY LEFT(action,?)=BINARY ? LIMIT 1",[strlen($auditPrefix),$auditPrefix])!==null;
        if($present!==array_slice(array_values($tables),0,count($present)))throw new RuntimeException('Unrecognized partial subdomain schema needs recovery.');
        provider_subdomain_add_validate_schema($present);
        foreach($present as $name)if($count('SELECT COUNT(*) FROM '.table($name))>0)$used=true;
        // A lost common marker must not hide a surviving approval or claim.
        foreach(['provider_operation_grants'=>"BINARY operation='packages.subdomainadd'",'provider_operations'=>"BINARY operation='packages.subdomainadd' OR BINARY idempotency_key LIKE 'subdomainadd:%'"] as $name=>$where)if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])&&one('SELECT id FROM '.table($name).' WHERE '.$where.' LIMIT 1'))$used=true;
        if($used)throw new RuntimeException('Restore the original Subdomain add history.');return false;
    }
    if($version!=='1'||!provider_operations_ready()||!provider_document_roots_ready())throw new RuntimeException('Subdomain add storage needs recovery.');
    if(count($settings)!==count($allowed)||$present!==array_values($tables))throw new RuntimeException('Subdomain add storage needs recovery.');
    provider_subdomain_add_validate_schema();
    foreach([...array_values($tables),'provider_operation_grants','provider_operations','settings','audit'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Subdomain adds require their original transactional storage.');}
    if($count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.subdomain_add_prepared'")!==1||$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.subdomain_add_prepared' AND BINARY detail='Subdomain add storage ready'")!==1)throw new RuntimeException('Subdomain add preparation needs recovery.');
    foreach($tables as $kind=>$name){
        $t=table($name);$action='provider.subdomain_add_'.$kind;$n=provider_subdomain_add_counter($kind);
        if($n!==$count("SELECT COUNT(*) FROM $t")||$n!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='$action'"))throw new RuntimeException('Subdomain add history counts disagree.');
        $digest=provider_document_roots_sql_digest(array_map(static fn($key)=>'w.'.$key,provider_subdomain_add_columns($kind)));$detail="CONCAT(BINARY 'Subdomain add $kind ',w.id,' ',$digest)";
        if(one("SELECT w.id FROM $t w WHERE BINARY w.document_sha256<>BINARY SHA2(w.document_json,256) OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail AND a.user_id=w.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail)<>1 LIMIT 1"))throw new RuntimeException('Subdomain add witness correspondence needs recovery.');
    }
    $q=table($tables['request']);$t=table($tables['terminal']);$c=table($tables['check']);$g=table('provider_operation_grants');$p=table('provider_operations');
    if(provider_subdomain_add_counter('request')!==$count("SELECT COUNT(*) FROM $g WHERE BINARY operation='packages.subdomainadd'"))throw new RuntimeException('Subdomain add approval history needs recovery.');
    $commitment="CONCAT(BINARY '{\"version\":1,\"request_id\":\"',q.id,'\",\"request_sha256\":\"',q.document_sha256,'\"}')";
    if(one("SELECT q.id FROM $q q LEFT JOIN $g g ON g.id=q.id WHERE g.id IS NULL OR BINARY g.operation<>'packages.subdomainadd' OR g.seq<>q.grant_seq OR BINARY g.profile_id<>BINARY q.profile_id OR g.granted_by<>q.actor_id OR BINARY g.scope_params<>BINARY $commitment OR BINARY g.consumed_key<>BINARY CONCAT(BINARY 'subdomainadd:',q.id) OR g.consumed_at IS NULL LIMIT 1"))throw new RuntimeException('Subdomain add approval correspondence needs recovery.');
    if(one("SELECT p.id FROM $p p LEFT JOIN $q q ON BINARY p.idempotency_key=BINARY CONCAT(BINARY 'subdomainadd:',q.id) WHERE (BINARY p.operation='packages.subdomainadd' OR BINARY p.idempotency_key LIKE 'subdomainadd:%') AND q.id IS NULL LIMIT 1"))throw new RuntimeException('Subdomain execution has lost its original approval.');
    $ledger=provider_subdomain_add_ledger_sql();
    if(one("SELECT t.id FROM $t t LEFT JOIN $q q ON q.id=t.id LEFT JOIN $p p ON BINARY p.idempotency_key=BINARY CONCAT(BINARY 'subdomainadd:',t.id) WHERE q.id IS NULL OR BINARY t.disposition NOT IN ('not_sent','attempted') OR (BINARY t.disposition='not_sent' AND p.id IS NOT NULL) OR BINARY t.ledger_sha256<>BINARY IF(p.id IS NULL,REPEAT('0',64),$ledger) LIMIT 1"))throw new RuntimeException('Subdomain dispatch completion needs recovery.');
    if(one("SELECT c.id FROM $c c LEFT JOIN $q q ON q.id=c.request_id LEFT JOIN $t t ON t.id=q.id LEFT JOIN ".table('provider_refreshes')." f ON f.id=c.receipt_id WHERE q.id IS NULL OR t.id IS NULL OR BINARY t.disposition<>'attempted' OR f.id IS NULL OR BINARY f.operation<>'package.document-roots' OR BINARY c.disposition NOT IN ('observed','different','unavailable') LIMIT 1"))throw new RuntimeException('Subdomain verification history needs recovery.');
    provider_subdomain_add_authenticated_history();
    return true;
}
/** Immutable evidence and independent counter commit with the exact audit. */
function provider_subdomain_add_insert(string $kind,array $row):void {
    provider_document_roots_guard();$tables=provider_subdomain_add_tables();if(!isset($tables[$kind])||array_keys($row)!==provider_subdomain_add_columns($kind))throw new LogicException('Invalid Subdomain evidence fields.');
    $count=provider_subdomain_add_counter($kind);if($count>=999999999999999)throw new RuntimeException('Subdomain add history is full.');
    query('INSERT INTO '.table($tables[$kind]).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    provider_settings_audit((int)$row['actor_id'],'subdomain_add_'.$kind,'Subdomain add '.$kind.' '.$row['id'].' '.provider_document_roots_digest(provider_subdomain_add_values($kind,$row)));
    save_setting('provider_subdomain_add_'.$kind.'_count',(string)($count+1));
}
/** An explicit known existing subset permits validation before any preparation DDL. */
function provider_subdomain_add_validate_schema(?array $existing=null):void {
    $indexes=[
        'provider_subdomain_add_requests'=>['PRIMARY'=>[0,['id']],'subdomain_add_seq'=>[0,['grant_seq']],'subdomain_add_resource'=>[1,['profile_id','package_id','subdomain']]],
        'provider_subdomain_add_terminals'=>['PRIMARY'=>[0,['id']]],
        'provider_subdomain_add_checks'=>['PRIMARY'=>[0,['id']],'subdomain_add_check_receipt'=>[0,['receipt_id']],'subdomain_add_check_request'=>[1,['request_id']]],
    ];
    $foreigns=['provider_subdomain_add_requests'=>['id'=>'provider_operation_grants'],'provider_subdomain_add_terminals'=>['id'=>'provider_subdomain_add_requests'],'provider_subdomain_add_checks'=>['request_id'=>'provider_subdomain_add_requests','receipt_id'=>'provider_refreshes']];
    $schemas=provider_subdomain_add_schema(config()['db']['prefix']);
    if($existing!==null){
        if(!array_is_list($existing))throw new LogicException('Unknown Subdomain schema subset.');$seen=[];
        foreach($existing as $name){if(!is_string($name)||!isset($schemas[$name])||isset($seen[$name]))throw new LogicException('Unknown Subdomain schema subset.');$seen[$name]=true;}
    }
    foreach($schemas as $name=>$sql){
        if($existing!==null&&!in_array($name,$existing,true))continue;
        $unique=[];foreach($indexes[$name] as $key=>$index)if($key!=='PRIMARY'&&$index[0]===0)$unique[$key]=$index[1];catalog_validate_schema($name,$sql,$unique);
        foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column){$text=preg_match('/^(?:char|varchar|longtext)/i',$column['Type'])===1;if($column['Extra']!==''||$column['Default']!==null||$column['Collation']!==($text?'ascii_bin':null))throw new RuntimeException('Incompatible Subdomain add columns.');}
        $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible Subdomain add indexes.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);$expected=$indexes[$name];ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible Subdomain add indexes.');
        $actual=[];foreach(all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]) as $f){if($f['REFERENCED_COLUMN_NAME']!=='id'||!in_array($f['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($f['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible Subdomain add relationships.');$actual[$f['COLUMN_NAME']]=$f['REFERENCED_TABLE_NAME'];}$expected=array_map(static fn($target)=>config()['db']['prefix'].$target,$foreigns[$name]);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible Subdomain add relationships.');
    }
}
function provider_subdomain_add_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Subdomain add preparation cannot run in a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_subdomain_add_actor($password);if(!provider_document_roots_ready()||!provider_operations_ready())throw new RuntimeException('Prepare Document-root observations and provider operations first.');
        if(provider_subdomain_add_ready()){provider_subdomain_add_validate_schema();return;}
        foreach(provider_subdomain_add_schema(config()['db']['prefix']) as $name=>$sql)if(!provider_subdomain_add_exists($name))db()->exec($sql);
        provider_subdomain_add_validate_schema();staff_transaction(function()use($actor):void{save_setting('provider_subdomain_add_schema_version','1');foreach(array_keys(provider_subdomain_add_tables()) as $kind)save_setting('provider_subdomain_add_'.$kind.'_count','0');provider_settings_audit((int)$actor['id'],'subdomain_add_prepared','Subdomain add storage ready');});
    });
}

function provider_subdomain_add_guard(bool $transaction=true):void {
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(($transaction&&!db()->inTransaction())||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1)throw new LogicException('Subdomain additions require the owning mutex and appropriate transaction.');
}
