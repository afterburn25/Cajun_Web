<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/provider_force_ssl_controls_schema.php';

function provider_force_ssl_controls_tables():array {return ['request'=>'provider_force_ssl_changes','terminal'=>'provider_force_ssl_dispatch_ends','check'=>'provider_force_ssl_change_checks'];}
function provider_force_ssl_controls_exists(string $name):bool {
    if(!in_array($name,provider_force_ssl_controls_tables(),true))throw new LogicException('Unknown Force SSL control table.');
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;
}
function provider_force_ssl_controls_counter(string $kind):int {
    if(!isset(provider_force_ssl_controls_tables()[$kind]))throw new LogicException('Unknown Force SSL control counter.');
    $n=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_force_ssl_control_'.$kind.'_count'])['value']??'';
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,14})\z/',$n))throw new RuntimeException('Force SSL change counts need recovery.');return (int)$n;
}
function provider_force_ssl_controls_hmac(string $kind,array $data):string {return hash_hmac('sha256','force-ssl-control|'.$kind.'|'.provider_force_ssl_json($data),provider_settings_vault_key());}
function provider_force_ssl_controls_columns(string $kind):array {
    return match($kind){
        'request'=>['id','grant_seq','profile_id','package_id','link_id','actor_id','document_json','document_sha256','proof'],
        'terminal'=>['id','actor_id','disposition','ledger_sha256','document_json','document_sha256','proof'],
        'check'=>['id','request_id','actor_id','receipt_id','disposition','document_json','document_sha256','proof'],
        default=>throw new LogicException('Unknown Force SSL change witness.'),
    };
}
function provider_force_ssl_controls_values(string $kind,array $row):array {return array_map(static fn($key)=>(string)$row[$key],provider_force_ssl_controls_columns($kind));}
function provider_force_ssl_controls_ledger_values(array $row):array {
    return array_map(static fn($key)=>(string)($row[$key]??''),['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch']);
}
function provider_force_ssl_controls_ledger_digest(?array $row):string {return $row===null?str_repeat('0',64):provider_force_ssl_digest(provider_force_ssl_controls_ledger_values($row));}
function provider_force_ssl_controls_ledger_sql():string {
    return provider_force_ssl_sql_digest(array_map(static fn($key)=>"COALESCE(CAST(p.$key AS CHAR),'')",['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch']));
}
/** No schema repair on GET. An absent, never-used feature is the only no-op case. */
function provider_force_ssl_controls_ready():bool {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_force_ssl_control_schema_version'])['value']??'';
    $tables=provider_force_ssl_controls_tables();$a=table('audit');$count=static fn(string $sql):int=>(int)query($sql)->fetchColumn();
    if($version===''){
        $used=one("SELECT id FROM $a WHERE BINARY action LIKE 'provider.force_ssl_control_%' LIMIT 1")!==null||one('SELECT name FROM '.table('settings')." WHERE name LIKE 'provider_force_ssl_control_%_count' LIMIT 1")!==null;
        foreach($tables as $name)if(provider_force_ssl_controls_exists($name)&&$count('SELECT COUNT(*) FROM '.table($name))>0)$used=true;
        // A lost common marker must not hide a surviving approval or claim.
        foreach(['provider_operation_grants'=>"BINARY operation='packages.forcessl'",'provider_operations'=>"BINARY operation='packages.forcessl' OR BINARY idempotency_key LIKE 'forcessl:%'"] as $name=>$where)if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])&&one('SELECT id FROM '.table($name).' WHERE '.$where.' LIMIT 1'))$used=true;
        if($used)throw new RuntimeException('Restore the original Force SSL change history.');return false;
    }
    if($version!=='1'||!provider_operations_ready()||!provider_force_ssl_ready())throw new RuntimeException('Force SSL change storage needs recovery.');
    foreach([...array_values($tables),'provider_operation_grants','provider_operations','settings','audit'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Force SSL changes require their original transactional storage.');}
    if($count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.force_ssl_control_prepared'")!==1||$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.force_ssl_control_prepared' AND BINARY detail='Force SSL change storage ready'")!==1)throw new RuntimeException('Force SSL change preparation needs recovery.');
    foreach($tables as $kind=>$name){
        $t=table($name);$action='provider.force_ssl_control_'.$kind;$n=provider_force_ssl_controls_counter($kind);
        if($n!==$count("SELECT COUNT(*) FROM $t")||$n!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='$action'"))throw new RuntimeException('Force SSL change history counts disagree.');
        $digest=provider_force_ssl_sql_digest(array_map(static fn($key)=>'w.'.$key,provider_force_ssl_controls_columns($kind)));$detail="CONCAT(BINARY 'Force SSL change $kind ',w.id,' ',$digest)";
        if(one("SELECT w.id FROM $t w WHERE BINARY w.document_sha256<>BINARY SHA2(w.document_json,256) OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail AND a.user_id=w.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail)<>1 LIMIT 1"))throw new RuntimeException('Force SSL change witness correspondence needs recovery.');
    }
    $q=table($tables['request']);$t=table($tables['terminal']);$c=table($tables['check']);$g=table('provider_operation_grants');$p=table('provider_operations');
    if(provider_force_ssl_controls_counter('request')!==$count("SELECT COUNT(*) FROM $g WHERE BINARY operation='packages.forcessl'"))throw new RuntimeException('Force SSL change approval history needs recovery.');
    if(one("SELECT q.id FROM $q q LEFT JOIN $g g ON g.id=q.id WHERE g.id IS NULL OR BINARY g.operation<>'packages.forcessl' OR g.seq<>q.grant_seq OR BINARY g.profile_id<>BINARY q.profile_id OR g.granted_by<>q.actor_id OR BINARY g.scope_params<>BINARY q.document_json OR BINARY g.consumed_key<>BINARY CONCAT(BINARY 'forcessl:',q.id) OR g.consumed_at IS NULL LIMIT 1"))throw new RuntimeException('Force SSL change approval correspondence needs recovery.');
    if(one("SELECT p.id FROM $p p LEFT JOIN $q q ON BINARY p.idempotency_key=BINARY CONCAT(BINARY 'forcessl:',q.id) WHERE (BINARY p.operation='packages.forcessl' OR BINARY p.idempotency_key LIKE 'forcessl:%') AND q.id IS NULL LIMIT 1"))throw new RuntimeException('Force SSL execution has lost its original approval.');
    $ledger=provider_force_ssl_controls_ledger_sql();
    if(one("SELECT t.id FROM $t t LEFT JOIN $q q ON q.id=t.id LEFT JOIN $p p ON BINARY p.idempotency_key=BINARY CONCAT(BINARY 'forcessl:',t.id) WHERE q.id IS NULL OR BINARY t.disposition NOT IN ('not_sent','attempted') OR (BINARY t.disposition='not_sent' AND p.id IS NOT NULL) OR BINARY t.ledger_sha256<>BINARY IF(p.id IS NULL,REPEAT('0',64),$ledger) LIMIT 1"))throw new RuntimeException('Force SSL dispatch completion needs recovery.');
    if(one("SELECT c.id FROM $c c LEFT JOIN $q q ON q.id=c.request_id LEFT JOIN $t t ON t.id=q.id LEFT JOIN ".table('provider_refreshes')." f ON f.id=c.receipt_id WHERE q.id IS NULL OR t.id IS NULL OR BINARY t.disposition<>'attempted' OR f.id IS NULL OR BINARY f.operation<>'package.force-ssl' OR BINARY c.disposition NOT IN ('observed','different','unavailable') LIMIT 1"))throw new RuntimeException('Force SSL verification history needs recovery.');
    provider_force_ssl_controls_authenticated_history();
    return true;
}
/** Immutable evidence and independent counter commit with the exact audit. */
function provider_force_ssl_controls_insert(string $kind,array $row):void {
    provider_force_ssl_guard();$tables=provider_force_ssl_controls_tables();if(!isset($tables[$kind])||array_keys($row)!==provider_force_ssl_controls_columns($kind))throw new LogicException('Invalid Force SSL evidence fields.');
    $count=provider_force_ssl_controls_counter($kind);if($count>=999999999999999)throw new RuntimeException('Force SSL change history is full.');
    query('INSERT INTO '.table($tables[$kind]).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    provider_settings_audit((int)$row['actor_id'],'force_ssl_control_'.$kind,'Force SSL change '.$kind.' '.$row['id'].' '.provider_force_ssl_digest(provider_force_ssl_controls_values($kind,$row)));
    save_setting('provider_force_ssl_control_'.$kind.'_count',(string)($count+1));
}
function provider_force_ssl_controls_validate_schema():void {
    $indexes=[
        'provider_force_ssl_changes'=>['PRIMARY'=>[0,['id']],'force_ssl_change_seq'=>[0,['grant_seq']],'force_ssl_change_resource'=>[1,['profile_id','package_id']]],
        'provider_force_ssl_dispatch_ends'=>['PRIMARY'=>[0,['id']]],
        'provider_force_ssl_change_checks'=>['PRIMARY'=>[0,['id']],'force_ssl_check_receipt'=>[0,['receipt_id']],'force_ssl_check_request'=>[1,['request_id']]],
    ];
    $foreigns=['provider_force_ssl_changes'=>['id'=>'provider_operation_grants'],'provider_force_ssl_dispatch_ends'=>['id'=>'provider_force_ssl_changes'],'provider_force_ssl_change_checks'=>['request_id'=>'provider_force_ssl_changes','receipt_id'=>'provider_refreshes']];
    foreach(provider_force_ssl_controls_schema(config()['db']['prefix']) as $name=>$sql){
        $unique=[];foreach($indexes[$name] as $key=>$index)if($key!=='PRIMARY'&&$index[0]===0)$unique[$key]=$index[1];catalog_validate_schema($name,$sql,$unique);
        foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column){$text=preg_match('/^(?:char|varchar|longtext)/i',$column['Type'])===1;if($column['Extra']!==''||$column['Default']!==null||$column['Collation']!==($text?'ascii_bin':null))throw new RuntimeException('Incompatible Force SSL change columns.');}
        $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible Force SSL change indexes.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);$expected=$indexes[$name];ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible Force SSL change indexes.');
        $actual=[];foreach(all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]) as $f){if($f['REFERENCED_COLUMN_NAME']!=='id'||!in_array($f['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($f['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible Force SSL change relationships.');$actual[$f['COLUMN_NAME']]=$f['REFERENCED_TABLE_NAME'];}$expected=array_map(static fn($target)=>config()['db']['prefix'].$target,$foreigns[$name]);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible Force SSL change relationships.');
    }
}
function provider_force_ssl_controls_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Force SSL change preparation cannot run in a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);if(!provider_force_ssl_ready()||!provider_operations_ready())throw new RuntimeException('Prepare Force SSL observations and provider operations first.');
        if(provider_force_ssl_controls_ready()){provider_force_ssl_controls_validate_schema();return;}
        foreach(provider_force_ssl_controls_schema(config()['db']['prefix']) as $name=>$sql)if(!provider_force_ssl_controls_exists($name))db()->exec($sql);
        provider_force_ssl_controls_validate_schema();staff_transaction(function()use($actor):void{save_setting('provider_force_ssl_control_schema_version','1');foreach(array_keys(provider_force_ssl_controls_tables()) as $kind)save_setting('provider_force_ssl_control_'.$kind.'_count','0');provider_settings_audit((int)$actor['id'],'force_ssl_control_prepared','Force SSL change storage ready');});
    });
}
