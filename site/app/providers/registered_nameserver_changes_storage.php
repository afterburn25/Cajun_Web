<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/registered_nameserver_changes_schema.php';

function registered_nameserver_changes_tables():array {return ['request'=>'provider_nameserver_change_requests','terminal'=>'provider_nameserver_change_terminals','check'=>'provider_nameserver_change_checks'];}
function registered_nameserver_changes_json(array $value):string {return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function registered_nameserver_changes_digest(array $values):string {$bytes='';foreach($values as $value){$value=(string)$value;$bytes.=strlen($value).':'.$value;}return hash('sha256',$bytes);}
/** Internal fixed SQL expressions, using the same byte framing as the PHP witness. */
function registered_nameserver_changes_sql_digest(array $columns):string {
    $parts=[];foreach($columns as $column){$parts[]='OCTET_LENGTH(CAST('.$column.' AS CHAR))';$parts[]="':'";$parts[]='CAST('.$column.' AS CHAR)';}
    return "SHA2(CONCAT(BINARY '',".implode(',',$parts).'),256)';
}
function registered_nameserver_changes_exists(string $name):bool {
    if(!in_array($name,registered_nameserver_changes_tables(),true))throw new LogicException('Unknown Registered nameserver change table.');
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?',[config()['db']['prefix'].$name])!==null;
}
/** Literal namespace matching: SQL LIKE underscores must not admit unrelated tables. */
function registered_nameserver_changes_inventory():array {
    $out=[];foreach(registered_nameserver_changes_tables() as $name)$out[$name]=registered_nameserver_changes_exists($name);
    $prefix=config()['db']['prefix'].'provider_nameserver_change_';$found=array_column(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen($prefix),$prefix]),'TABLE_NAME');
    $expected=[];foreach($out as $name=>$exists)if($exists)$expected[]=config()['db']['prefix'].$name;sort($found);sort($expected);
    if($found!==$expected)throw new RuntimeException('Unknown registered nameserver change storage needs recovery.');return $out;
}
function registered_nameserver_changes_counter(string $kind):int {
    if(!isset(registered_nameserver_changes_tables()[$kind]))throw new LogicException('Unknown Registered nameserver change counter.');
    $n=one('SELECT value FROM '.table('settings').' WHERE name=?',['registered_nameserver_changes_'.$kind.'_count'])['value']??'';
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,14})\z/',$n))throw new RuntimeException('Registered nameserver change counts need recovery.');return (int)$n;
}
function registered_nameserver_changes_hmac(string $kind,array $data):string {if(!isset(registered_nameserver_changes_tables()[$kind]))throw new LogicException('Unknown registered nameserver change proof.');return hash_hmac('sha256','registered-nameserver-change|'.$kind.'|'.registered_nameserver_changes_json($data),provider_settings_vault_key());}
function registered_nameserver_changes_columns(string $kind):array {
    return match($kind){
        'request'=>['id','grant_seq','profile_id','domain_id','link_id','actor_id','document_json','document_sha256','proof'],
        'terminal'=>['id','actor_id','disposition','ledger_sha256','document_json','document_sha256','proof'],
        'check'=>['id','request_id','actor_id','receipt_id','disposition','document_json','document_sha256','proof'],
        default=>throw new LogicException('Unknown Registered nameserver change witness.'),
    };
}
function registered_nameserver_changes_values(string $kind,array $row):array {return array_map(static fn($key)=>(string)$row[$key],registered_nameserver_changes_columns($kind));}
function registered_nameserver_changes_ledger_values(array $row):array {
    return array_map(static fn($key)=>(string)($row[$key]??''),['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch']);
}
function registered_nameserver_changes_ledger_digest(?array $row):string {return $row===null?str_repeat('0',64):registered_nameserver_changes_digest(registered_nameserver_changes_ledger_values($row));}
function registered_nameserver_changes_ledger_sql():string {
    return registered_nameserver_changes_sql_digest(array_map(static fn($key)=>"COALESCE(CAST(p.$key AS CHAR),'')",['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch']));
}
/** No schema repair on GET. An absent, never-used feature is the only no-op case. */
function registered_nameserver_changes_ready():bool {
    registered_nameservers_guard(false);
    foreach(['settings','audit'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Registered nameserver changes require transactional settings and audit.');}
    $inventory=registered_nameserver_changes_inventory();registered_nameserver_changes_validate_schema(array_keys(array_filter($inventory)));
    $versionRow=one('SELECT value FROM '.table('settings').' WHERE name=?',['registered_nameserver_changes_schema_version']);$version=$versionRow['value']??null;
    $tables=registered_nameserver_changes_tables();$a=table('audit');$count=static fn(string $sql):int=>(int)query($sql)->fetchColumn();
    $auditPrefix='provider.registered_nameserver_changes_';$settingPrefix='registered_nameserver_changes_';
    $settings=array_column(all('SELECT name FROM '.table('settings').' WHERE BINARY LEFT(name,?)=BINARY ?',[strlen($settingPrefix),$settingPrefix]),'name');
    $allowedSettings=['registered_nameserver_changes_schema_version'];foreach(array_keys($tables) as $kind)$allowedSettings[]='registered_nameserver_changes_'.$kind.'_count';
    if(array_diff($settings,$allowedSettings))throw new RuntimeException('Unknown registered nameserver change settings need recovery.');
    $allowedActions=[$auditPrefix.'prepared'];foreach(array_keys($tables) as $kind)$allowedActions[]=$auditPrefix.$kind;
    if(one("SELECT id FROM $a WHERE BINARY LEFT(action,?)=BINARY ? AND (BINARY action NOT IN (?,?,?,?) OR user_id<1) LIMIT 1",[strlen($auditPrefix),$auditPrefix,...$allowedActions]))throw new RuntimeException('Unknown registered nameserver change audits need recovery.');
    if($versionRow===null){
        $used=$settings!==[]||one("SELECT id FROM $a WHERE BINARY LEFT(action,?)=BINARY ? LIMIT 1",[strlen($auditPrefix),$auditPrefix])!==null;
        $missing=false;foreach($inventory as $name=>$exists){if(!$exists)$missing=true;elseif($missing)throw new RuntimeException('Unrecognized registered nameserver change preparation state.');if($exists&&$count('SELECT COUNT(*) FROM '.table($name))>0)$used=true;}
        // A lost common marker must not hide a surviving approval or claim.
        foreach(['provider_operation_grants'=>"BINARY operation='registrar.nameservers'",'provider_operations'=>"BINARY operation='registrar.nameservers' OR BINARY idempotency_key LIKE 'nameservers:%'"] as $name=>$where)if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])&&one('SELECT id FROM '.table($name).' WHERE '.$where.' LIMIT 1'))$used=true;
        if($used)throw new RuntimeException('Restore the original Registered nameserver change history.');return false;
    }
    if($version!=='1'||in_array(false,$inventory,true)||!provider_operations_ready()||!registered_nameservers_ready())throw new RuntimeException('Registered nameserver change storage needs recovery.');
    foreach([...array_values($tables),'provider_operation_grants','provider_operations','settings','audit'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Registered nameserver changes require their original transactional storage.');}
    if($count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.registered_nameserver_changes_prepared'")!==1||$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.registered_nameserver_changes_prepared' AND BINARY detail='Registered nameserver changes ready' AND user_id>0")!==1)throw new RuntimeException('Registered nameserver change preparation needs recovery.');
    $prepared=(int)one("SELECT id FROM $a WHERE BINARY action='provider.registered_nameserver_changes_prepared'")['id'];
    foreach($tables as $kind=>$name){
        $t=table($name);$action='provider.registered_nameserver_changes_'.$kind;$n=registered_nameserver_changes_counter($kind);
        if($n!==$count("SELECT COUNT(*) FROM $t")||$n!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='$action'"))throw new RuntimeException('Registered nameserver change history counts disagree.');
        $digest=registered_nameserver_changes_sql_digest(array_map(static fn($key)=>'w.'.$key,registered_nameserver_changes_columns($kind)));$detail="CONCAT(BINARY 'Registered nameserver change $kind ',w.id,' ',$digest)";
        if(one("SELECT w.id FROM $t w WHERE w.actor_id<1 OR OCTET_LENGTH(w.document_json)>4096 OR BINARY w.document_sha256<>BINARY SHA2(w.document_json,256) OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail AND a.user_id=w.actor_id AND a.id>$prepared)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail)<>1 LIMIT 1"))throw new RuntimeException('Registered nameserver change witness correspondence needs recovery.');
    }
    $q=table($tables['request']);$t=table($tables['terminal']);$c=table($tables['check']);$g=table('provider_operation_grants');$p=table('provider_operations');
    if(registered_nameserver_changes_counter('request')!==$count("SELECT COUNT(*) FROM $g WHERE BINARY operation='registrar.nameservers'"))throw new RuntimeException('Registered nameserver change approval history needs recovery.');
    if(one("SELECT q.id FROM $q q LEFT JOIN $g g ON g.id=q.id WHERE g.id IS NULL OR BINARY g.operation<>'registrar.nameservers' OR g.seq<>q.grant_seq OR BINARY g.profile_id<>BINARY q.profile_id OR g.granted_by<>q.actor_id OR BINARY g.scope_params<>BINARY q.document_json OR BINARY g.consumed_key<>BINARY CONCAT(BINARY 'nameservers:',q.id) OR g.consumed_at IS NULL LIMIT 1"))throw new RuntimeException('Registered nameserver change approval correspondence needs recovery.');
    if(one("SELECT p.id FROM $p p LEFT JOIN $q q ON BINARY p.idempotency_key=BINARY CONCAT(BINARY 'nameservers:',q.id) WHERE (BINARY p.operation='registrar.nameservers' OR BINARY p.idempotency_key LIKE 'nameservers:%') AND q.id IS NULL LIMIT 1"))throw new RuntimeException('Registered nameserver execution has lost its original approval.');
    $ledger=registered_nameserver_changes_ledger_sql();
    if(one("SELECT t.id FROM $t t LEFT JOIN $q q ON q.id=t.id LEFT JOIN $p p ON BINARY p.idempotency_key=BINARY CONCAT(BINARY 'nameservers:',t.id) WHERE q.id IS NULL OR BINARY t.disposition NOT IN ('not_sent','attempted') OR (BINARY t.disposition='not_sent' AND p.id IS NOT NULL) OR BINARY t.ledger_sha256<>BINARY IF(p.id IS NULL,REPEAT('0',64),$ledger) LIMIT 1"))throw new RuntimeException('Registered nameserver dispatch completion needs recovery.');
    if(one("SELECT c.id FROM $c c LEFT JOIN $q q ON q.id=c.request_id LEFT JOIN $t t ON t.id=q.id LEFT JOIN ".table('provider_refreshes')." f ON f.id=c.receipt_id WHERE q.id IS NULL OR t.id IS NULL OR BINARY t.disposition<>'attempted' OR f.id IS NULL OR BINARY f.operation<>'registrar.nameservers.read' OR BINARY c.disposition NOT IN ('observed','different','unavailable') LIMIT 1"))throw new RuntimeException('Registered nameserver verification history needs recovery.');
    registered_nameserver_changes_authenticated_history();
    return true;
}
/** Immutable evidence and independent counter commit with the exact audit. */
function registered_nameserver_changes_insert(string $kind,array $row):void {
    registered_nameservers_guard();$tables=registered_nameserver_changes_tables();if(!isset($tables[$kind])||array_keys($row)!==registered_nameserver_changes_columns($kind))throw new LogicException('Invalid registered nameserver evidence fields.');
    if((int)$row['actor_id']<1||!is_string($row['document_json'])||strlen($row['document_json'])>4096||!hash_equals(hash('sha256',$row['document_json']),(string)$row['document_sha256']))throw new LogicException('Invalid registered nameserver change document.');
    $count=registered_nameserver_changes_counter($kind);if($count>=999999999999999)throw new RuntimeException('Registered nameserver change history is full.');
    query('INSERT INTO '.table($tables[$kind]).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    provider_settings_audit((int)$row['actor_id'],'registered_nameserver_changes_'.$kind,'Registered nameserver change '.$kind.' '.$row['id'].' '.registered_nameserver_changes_digest(registered_nameserver_changes_values($kind,$row)));
    save_setting('registered_nameserver_changes_'.$kind.'_count',(string)($count+1));
}
/** Validate every present table before any DDL, including an interrupted empty prefix. */
function registered_nameserver_changes_validate_schema(?array $names=null):void {
    $schema=registered_nameserver_changes_schema(config()['db']['prefix']);$names??=array_keys($schema);
    if(count(array_unique($names))!==count($names)||array_diff($names,array_keys($schema)))throw new LogicException('Unknown registered nameserver change schema.');
    $indexes=[
        'provider_nameserver_change_requests'=>['PRIMARY'=>[0,['id']],'nameserver_change_seq'=>[0,['grant_seq']],'nameserver_change_resource'=>[1,['profile_id','domain_id']]],
        'provider_nameserver_change_terminals'=>['PRIMARY'=>[0,['id']]],
        'provider_nameserver_change_checks'=>['PRIMARY'=>[0,['id']],'nameserver_check_receipt'=>[0,['receipt_id']],'nameserver_check_request'=>[1,['request_id']]],
    ];
    $foreigns=['provider_nameserver_change_requests'=>['id'=>'provider_operation_grants'],'provider_nameserver_change_terminals'=>['id'=>'provider_nameserver_change_requests'],'provider_nameserver_change_checks'=>['request_id'=>'provider_nameserver_change_requests','receipt_id'=>'provider_refreshes']];
    foreach($names as $name){$sql=$schema[$name];
        $unique=[];foreach($indexes[$name] as $key=>$index)if($key!=='PRIMARY'&&$index[0]===0)$unique[$key]=$index[1];catalog_validate_schema($name,$sql,$unique);
        foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column){$text=preg_match('/^(?:char|varchar|longtext)/i',$column['Type'])===1;if($column['Extra']!==''||$column['Default']!==null||$column['Collation']!==($text?'ascii_bin':null))throw new RuntimeException('Incompatible Registered nameserver change columns.');}
        $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible Registered nameserver change indexes.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);$expected=$indexes[$name];ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible Registered nameserver change indexes.');
        $actual=[];foreach(all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND BINARY k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]) as $f){if(isset($actual[$f['COLUMN_NAME']])||$f['REFERENCED_COLUMN_NAME']!=='id'||!in_array($f['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($f['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible Registered nameserver change relationships.');$actual[$f['COLUMN_NAME']]=$f['REFERENCED_TABLE_NAME'];}$expected=array_map(static fn($target)=>config()['db']['prefix'].$target,$foreigns[$name]);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible Registered nameserver change relationships.');
    }
}
function registered_nameserver_changes_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Registered nameserver change preparation cannot run in a transaction.');
    staff_locked(function()use($password):void{
        unset($GLOBALS['cwp_settings']);$actor=provider_settings_owner($password);if(!registered_nameservers_ready()||!provider_operations_ready())throw new RuntimeException('Prepare registered nameserver observations and provider operations first.');
        if(registered_nameserver_changes_ready()){registered_nameserver_changes_validate_schema();return;}
        foreach(registered_nameserver_changes_schema(config()['db']['prefix']) as $name=>$sql){if(!registered_nameserver_changes_exists($name))db()->exec($sql);registered_nameserver_changes_validate_schema([$name]);}
        registered_nameserver_changes_validate_schema();staff_transaction(function()use($actor):void{save_setting('registered_nameserver_changes_schema_version','1');foreach(array_keys(registered_nameserver_changes_tables()) as $kind)save_setting('registered_nameserver_changes_'.$kind.'_count','0');provider_settings_audit((int)$actor['id'],'registered_nameserver_changes_prepared','Registered nameserver changes ready');});
        if(!registered_nameserver_changes_ready())throw new RuntimeException('Registered nameserver change preparation is incomplete.');
    });
}
