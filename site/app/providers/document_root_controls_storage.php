<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/provider_document_root_controls_schema.php';

function provider_document_root_controls_tables():array {return ['request'=>'provider_document_root_changes','terminal'=>'provider_document_root_dispatch_ends','check'=>'provider_document_root_change_checks'];}
function provider_document_root_controls_exists(string $name):bool {
    if(!in_array($name,provider_document_root_controls_tables(),true))throw new LogicException('Unknown Document-root control table.');
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;
}
/** Only this feature's exact table namespace may participate in preparation. */
function provider_document_root_controls_present():array {
    $prefix=config()['db']['prefix'].'provider_document_root_';$known=array_values(provider_document_root_controls_tables());$found=[];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen($prefix),$prefix]) as $row){
        $name=substr($row['TABLE_NAME'],strlen(config()['db']['prefix']));
        if(!in_array($name,$known,true))throw new RuntimeException('Unknown Document-root change storage needs recovery.');$found[]=$name;
    }
    return array_values(array_filter($known,static fn($name)=>in_array($name,$found,true)));
}
function provider_document_root_controls_counter(string $kind):int {
    if(!isset(provider_document_root_controls_tables()[$kind]))throw new LogicException('Unknown Document-root control counter.');
    $n=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_document_root_control_'.$kind.'_count'])['value']??'';
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,14})\z/',$n))throw new RuntimeException('Document-root change counts need recovery.');return (int)$n;
}
function provider_document_root_controls_hmac(string $kind,array $data):string {return hash_hmac('sha256','document-root-control|'.$kind.'|'.provider_document_roots_json($data),provider_settings_vault_key());}
function provider_document_root_controls_columns(string $kind):array {
    return match($kind){
        'request'=>['id','grant_seq','profile_id','package_id','link_id','actor_id','document_json','document_sha256','proof'],
        'terminal'=>['id','actor_id','disposition','ledger_sha256','document_json','document_sha256','proof'],
        'check'=>['id','request_id','actor_id','receipt_id','disposition','document_json','document_sha256','proof'],
        default=>throw new LogicException('Unknown Document-root change witness.'),
    };
}
function provider_document_root_controls_values(string $kind,array $row):array {return array_map(static fn($key)=>(string)$row[$key],provider_document_root_controls_columns($kind));}
function provider_document_root_controls_ledger_values(array $row):array {
    return array_map(static fn($key)=>(string)($row[$key]??''),['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch']);
}
function provider_document_root_controls_ledger_digest(?array $row):string {return $row===null?str_repeat('0',64):provider_document_roots_digest(provider_document_root_controls_ledger_values($row));}
function provider_document_root_controls_ledger_sql():string {
    return provider_document_roots_sql_digest(array_map(static fn($key)=>"COALESCE(CAST(p.$key AS CHAR),'')",['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch']));
}
/** No schema repair on GET. An absent, never-used feature is the only no-op case. */
function provider_document_root_controls_ready():bool {
    $marker='provider_document_root_control_schema_version';$settingPrefix='provider_document_root_control_';$settings=[];$allowed=[$marker];
    foreach(array_keys(provider_document_root_controls_tables()) as $kind)$allowed[]=$settingPrefix.$kind.'_count';
    foreach(all('SELECT name,value FROM '.table('settings').' WHERE BINARY LEFT(name,?)=BINARY ?',[strlen($settingPrefix),$settingPrefix]) as $row){
        if(!in_array($row['name'],$allowed,true)||array_key_exists($row['name'],$settings))throw new RuntimeException('Unknown Document-root change settings need recovery.');$settings[$row['name']]=$row['value'];
    }
    $version=$settings[$marker]??'';$present=provider_document_root_controls_present();
    $auditPrefix='provider.document_root_control_';$actions=array_map(static fn($kind)=>$auditPrefix.$kind,['prepared','request','terminal','check']);
    foreach(all('SELECT DISTINCT BINARY action AS action FROM '.table('audit').' WHERE BINARY LEFT(action,?)=BINARY ?',[strlen($auditPrefix),$auditPrefix]) as $row)if(!in_array($row['action'],$actions,true))throw new RuntimeException('Unknown Document-root change audits need recovery.');
    $tables=provider_document_root_controls_tables();$a=table('audit');$count=static fn(string $sql):int=>(int)query($sql)->fetchColumn();
    if($version===''){
        $used=$settings!==[]||one("SELECT id FROM $a WHERE BINARY LEFT(action,?)=BINARY ? LIMIT 1",[strlen($auditPrefix),$auditPrefix])!==null;
        provider_document_root_controls_validate_schema($present);
        foreach($present as $name)if($count('SELECT COUNT(*) FROM '.table($name))>0)$used=true;
        // A lost common marker must not hide a surviving approval or claim.
        foreach(['provider_operation_grants'=>"BINARY operation='packages.documentroot'",'provider_operations'=>"BINARY operation='packages.documentroot' OR BINARY idempotency_key LIKE 'documentroot:%'"] as $name=>$where)if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])&&one('SELECT id FROM '.table($name).' WHERE '.$where.' LIMIT 1'))$used=true;
        if($used)throw new RuntimeException('Restore the original Document-root change history.');return false;
    }
    if($version!=='1'||!provider_operations_ready()||!provider_document_roots_ready())throw new RuntimeException('Document-root change storage needs recovery.');
    if(count($settings)!==count($allowed)||$present!==array_values($tables))throw new RuntimeException('Document-root change storage needs recovery.');
    provider_document_root_controls_validate_schema();
    foreach([...array_values($tables),'provider_operation_grants','provider_operations','settings','audit'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Document-root changes require their original transactional storage.');}
    if($count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.document_root_control_prepared'")!==1||$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.document_root_control_prepared' AND BINARY detail='Document-root change storage ready'")!==1)throw new RuntimeException('Document-root change preparation needs recovery.');
    foreach($tables as $kind=>$name){
        $t=table($name);$action='provider.document_root_control_'.$kind;$n=provider_document_root_controls_counter($kind);
        if($n!==$count("SELECT COUNT(*) FROM $t")||$n!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='$action'"))throw new RuntimeException('Document-root change history counts disagree.');
        $digest=provider_document_roots_sql_digest(array_map(static fn($key)=>'w.'.$key,provider_document_root_controls_columns($kind)));$detail="CONCAT(BINARY 'Document-root change $kind ',w.id,' ',$digest)";
        if(one("SELECT w.id FROM $t w WHERE BINARY w.document_sha256<>BINARY SHA2(w.document_json,256) OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail AND a.user_id=w.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail)<>1 LIMIT 1"))throw new RuntimeException('Document-root change witness correspondence needs recovery.');
    }
    $q=table($tables['request']);$t=table($tables['terminal']);$c=table($tables['check']);$g=table('provider_operation_grants');$p=table('provider_operations');
    if(provider_document_root_controls_counter('request')!==$count("SELECT COUNT(*) FROM $g WHERE BINARY operation='packages.documentroot'"))throw new RuntimeException('Document-root change approval history needs recovery.');
    $commitment="CONCAT(BINARY '{\"version\":1,\"request_id\":\"',q.id,'\",\"request_sha256\":\"',q.document_sha256,'\"}')";
    if(one("SELECT q.id FROM $q q LEFT JOIN $g g ON g.id=q.id WHERE g.id IS NULL OR BINARY g.operation<>'packages.documentroot' OR g.seq<>q.grant_seq OR BINARY g.profile_id<>BINARY q.profile_id OR g.granted_by<>q.actor_id OR BINARY g.scope_params<>BINARY $commitment OR BINARY g.consumed_key<>BINARY CONCAT(BINARY 'documentroot:',q.id) OR g.consumed_at IS NULL LIMIT 1"))throw new RuntimeException('Document-root change approval correspondence needs recovery.');
    if(one("SELECT p.id FROM $p p LEFT JOIN $q q ON BINARY p.idempotency_key=BINARY CONCAT(BINARY 'documentroot:',q.id) WHERE (BINARY p.operation='packages.documentroot' OR BINARY p.idempotency_key LIKE 'documentroot:%') AND q.id IS NULL LIMIT 1"))throw new RuntimeException('Document-root execution has lost its original approval.');
    $ledger=provider_document_root_controls_ledger_sql();
    if(one("SELECT t.id FROM $t t LEFT JOIN $q q ON q.id=t.id LEFT JOIN $p p ON BINARY p.idempotency_key=BINARY CONCAT(BINARY 'documentroot:',t.id) WHERE q.id IS NULL OR BINARY t.disposition NOT IN ('not_sent','attempted') OR (BINARY t.disposition='not_sent' AND p.id IS NOT NULL) OR BINARY t.ledger_sha256<>BINARY IF(p.id IS NULL,REPEAT('0',64),$ledger) LIMIT 1"))throw new RuntimeException('Document-root dispatch completion needs recovery.');
    if(one("SELECT c.id FROM $c c LEFT JOIN $q q ON q.id=c.request_id LEFT JOIN $t t ON t.id=q.id LEFT JOIN ".table('provider_refreshes')." f ON f.id=c.receipt_id WHERE q.id IS NULL OR t.id IS NULL OR BINARY t.disposition<>'attempted' OR f.id IS NULL OR BINARY f.operation<>'package.document-roots' OR BINARY c.disposition NOT IN ('observed','different','unavailable') LIMIT 1"))throw new RuntimeException('Document-root verification history needs recovery.');
    provider_document_root_controls_authenticated_history();
    return true;
}
/** Immutable evidence and independent counter commit with the exact audit. */
function provider_document_root_controls_insert(string $kind,array $row):void {
    provider_document_roots_guard();$tables=provider_document_root_controls_tables();if(!isset($tables[$kind])||array_keys($row)!==provider_document_root_controls_columns($kind))throw new LogicException('Invalid Document-root evidence fields.');
    $count=provider_document_root_controls_counter($kind);if($count>=999999999999999)throw new RuntimeException('Document-root change history is full.');
    query('INSERT INTO '.table($tables[$kind]).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    provider_settings_audit((int)$row['actor_id'],'document_root_control_'.$kind,'Document-root change '.$kind.' '.$row['id'].' '.provider_document_roots_digest(provider_document_root_controls_values($kind,$row)));
    save_setting('provider_document_root_control_'.$kind.'_count',(string)($count+1));
}
/** An explicit known existing subset permits validation before any preparation DDL. */
function provider_document_root_controls_validate_schema(?array $existing=null):void {
    $indexes=[
        'provider_document_root_changes'=>['PRIMARY'=>[0,['id']],'document_root_change_seq'=>[0,['grant_seq']],'document_root_change_resource'=>[1,['profile_id','package_id']]],
        'provider_document_root_dispatch_ends'=>['PRIMARY'=>[0,['id']]],
        'provider_document_root_change_checks'=>['PRIMARY'=>[0,['id']],'document_root_check_receipt'=>[0,['receipt_id']],'document_root_check_request'=>[1,['request_id']]],
    ];
    $foreigns=['provider_document_root_changes'=>['id'=>'provider_operation_grants'],'provider_document_root_dispatch_ends'=>['id'=>'provider_document_root_changes'],'provider_document_root_change_checks'=>['request_id'=>'provider_document_root_changes','receipt_id'=>'provider_refreshes']];
    $schemas=provider_document_root_controls_schema(config()['db']['prefix']);
    if($existing!==null){
        if(!array_is_list($existing))throw new LogicException('Unknown Document-root schema subset.');$seen=[];
        foreach($existing as $name){if(!is_string($name)||!isset($schemas[$name])||isset($seen[$name]))throw new LogicException('Unknown Document-root schema subset.');$seen[$name]=true;}
    }
    foreach($schemas as $name=>$sql){
        if($existing!==null&&!in_array($name,$existing,true))continue;
        $unique=[];foreach($indexes[$name] as $key=>$index)if($key!=='PRIMARY'&&$index[0]===0)$unique[$key]=$index[1];catalog_validate_schema($name,$sql,$unique);
        foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column){$text=preg_match('/^(?:char|varchar|longtext)/i',$column['Type'])===1;if($column['Extra']!==''||$column['Default']!==null||$column['Collation']!==($text?'ascii_bin':null))throw new RuntimeException('Incompatible Document-root change columns.');}
        $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible Document-root change indexes.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);$expected=$indexes[$name];ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible Document-root change indexes.');
        $actual=[];foreach(all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]) as $f){if($f['REFERENCED_COLUMN_NAME']!=='id'||!in_array($f['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($f['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible Document-root change relationships.');$actual[$f['COLUMN_NAME']]=$f['REFERENCED_TABLE_NAME'];}$expected=array_map(static fn($target)=>config()['db']['prefix'].$target,$foreigns[$name]);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible Document-root change relationships.');
    }
}
function provider_document_root_controls_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Document-root change preparation cannot run in a transaction.');
    staff_locked(function()use($password):void{
        unset($GLOBALS['cwp_settings']);$actor=provider_settings_owner($password);if(!provider_document_roots_ready()||!provider_operations_ready())throw new RuntimeException('Prepare Document-root observations and provider operations first.');
        if(provider_document_root_controls_ready()){provider_document_root_controls_validate_schema();return;}
        foreach(provider_document_root_controls_schema(config()['db']['prefix']) as $name=>$sql)if(!provider_document_root_controls_exists($name))db()->exec($sql);
        provider_document_root_controls_validate_schema();staff_transaction(function()use($actor):void{save_setting('provider_document_root_control_schema_version','1');foreach(array_keys(provider_document_root_controls_tables()) as $kind)save_setting('provider_document_root_control_'.$kind.'_count','0');provider_settings_audit((int)$actor['id'],'document_root_control_prepared','Document-root change storage ready');});
    });
}
