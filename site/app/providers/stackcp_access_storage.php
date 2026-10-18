<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/stackcp_assignments.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_stackcp_access_schema.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_access_documents.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_access_sources.php';
use CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding;

function provider_stackcp_access_table(string $kind):string {return match($kind){'started'=>'provider_stackcp_reads','finished'=>'provider_stackcp_observations',default=>throw new LogicException('Unknown StackCP observation family.')};}
function provider_stackcp_access_exists(string $name):bool {return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;}
function provider_stackcp_access_validate_schema():void {
    foreach (provider_stackcp_access_schema(config()['db']['prefix']) as $name=>$sql) {
        catalog_validate_schema($name,$sql,['ordinal'=>['ordinal']]);
        foreach (all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {if ($column['Extra']!==''||$column['Default']!==null||$column['Collation']!==(preg_match('/^(?:char|varchar|longtext|text)/i',$column['Type'])?'ascii_bin':null)) throw new RuntimeException('Incompatible StackCP observation columns.');}
        $expected=['PRIMARY'=>[0,['id']],'ordinal'=>[0,['ordinal']]];
        if ($name==='provider_stackcp_reads') $expected+=['stackcp_package'=>[1,['profile_id','package_id']],'stackcp_user'=>[1,['profile_id','stack_identity']],'assignment_id'=>[1,['assignment_id']]];
        $actual=[];foreach (all('SHOW INDEX FROM '.table($name)) as $index) {if ($index['Sub_part']!==null||$index['Index_type']!=='BTREE') throw new RuntimeException('Incompatible StackCP observation indexes.');$actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];}
        foreach ($actual as &$index) {ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);ksort($actual);ksort($expected);if ($actual!==$expected) throw new RuntimeException('Incompatible StackCP observation indexes.');
        $actual=[];foreach (all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]) as $fk) {if ($fk['REFERENCED_COLUMN_NAME']!=='id'||!in_array($fk['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($fk['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)||isset($actual[$fk['COLUMN_NAME']])) throw new RuntimeException('Incompatible StackCP observation relationships.');$actual[$fk['COLUMN_NAME']]=$fk['REFERENCED_TABLE_NAME'];}
        $expected=['id'=>config()['db']['prefix'].($name==='provider_stackcp_reads'?'provider_refreshes':'provider_stackcp_reads')];if ($name==='provider_stackcp_reads') $expected['assignment_id']=config()['db']['prefix'].'provider_stackcp_assignments';ksort($actual);ksort($expected);if ($actual!==$expected) throw new RuntimeException('Incompatible StackCP observation relationships.');
    }
}
function provider_stackcp_access_checkpoint():array {
    $raw=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_stackcp_access_checkpoint'])['value']??'';
    $proof=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_stackcp_access_checkpoint_proof'])['value']??'';
    try {$d=json_decode($raw,true,4,JSON_THROW_ON_ERROR);if (!is_array($d)||array_keys($d)!==['version','started','started_sha256','finished','finished_sha256']||$d['version']!==1) throw new InvalidArgumentException();foreach (['started','finished'] as $kind) {if (!is_int($d[$kind])||$d[$kind]<0||$d[$kind]>10000) throw new InvalidArgumentException();provider_stackcp_hex($d[$kind.'_sha256'],64);if ($d[$kind]===0&&$d[$kind.'_sha256']!==str_repeat('0',64)) throw new InvalidArgumentException();}if ($d['finished']>$d['started']||provider_stackcp_access_json($d)!==$raw||!hash_equals(provider_stackcp_access_hmac('checkpoint',$d),$proof)) throw new InvalidArgumentException();return $d;
    } catch (JsonException|InvalidArgumentException|TypeError $e) {throw new RuntimeException('StackCP access checkpoint needs recovery.',0,$e);}
}
function provider_stackcp_access_checkpoint_save(array $d):void {provider_stackcp_guard(true);save_setting('provider_stackcp_access_checkpoint',provider_stackcp_access_json($d));save_setting('provider_stackcp_access_checkpoint_proof',provider_stackcp_access_hmac('checkpoint',$d));}
function provider_stackcp_access_detail(string $kind,array $r):string {return 'StackCP access '.$kind.' '.$r['id'].' '.$r['document_sha256'].' '.$r['proof'];}
function provider_stackcp_access_common_row(string $id,bool $finished):?array {
    $f=table('provider_refreshes');$z=table('provider_refresh_results');$select='f.*';if ($finished) foreach (array_slice(provider_stackcp_access_common_keys(true),8) as $field) $select.=',z.'.$field;
    return one("SELECT $select FROM $f f".($finished?" JOIN $z z ON z.id=f.id":'').' WHERE f.id=?',[$id]);
}
/** No DDL, decryption, history adoption or network. Validate every signed row before indexed availability. */
function provider_stackcp_access_ready():bool {
    return ProviderStackCpReadProjection::accessReady();
}
function provider_stackcp_access_prepare(#[SensitiveParameter] string $password):void {
    if (db()->inTransaction()) throw new LogicException('StackCP observation preparation cannot run in a transaction.');
    staff_locked(function()use($password):void {
        $actor=provider_settings_owner($password);if (!provider_stackcp_ready('assignment')) throw new RuntimeException('Prepare protected StackCP assignments first.');if (provider_stackcp_access_ready()) return;
        foreach (provider_stackcp_access_schema(config()['db']['prefix']) as $name=>$sql) if (!provider_stackcp_access_exists($name)) db()->exec($sql);provider_stackcp_access_validate_schema();$proof=provider_stackcp_proof(provider_stackcp_vault(),'access-prepared','1');
        staff_transaction(function()use($actor,$proof):void {save_setting('provider_stackcp_access_schema_version','1');save_setting('provider_stackcp_access_proof',$proof);provider_stackcp_access_checkpoint_save(['version'=>1,'started'=>0,'started_sha256'=>str_repeat('0',64),'finished'=>0,'finished_sha256'=>str_repeat('0',64)]);provider_settings_audit((int)$actor['id'],'stackcp_access_prepared','StackCP access observation storage ready '.$proof);});
    });
}
/** Internal recorder hook. Full readiness must precede the common INSERT in the same locked transaction. */
function provider_stackcp_access_append(string $kind,array $document):array {
    provider_stackcp_guard(true);$d=provider_stackcp_access_document($kind,$document);$head=provider_stackcp_access_checkpoint();
    if ($head[$kind]>=10000||$d['ordinal']!==$head[$kind]+1||$d['previous_sha256']!==$head[$kind.'_sha256']) throw new RuntimeException('StackCP access history capacity or order needs review.');
    $json=provider_stackcp_access_json($d);$row=['id'=>$d['id'],'ordinal'=>$d['ordinal']];if ($kind==='started') foreach (['profile_id','package_id','client_id','stack_identity','operation','assignment_id'] as $key) $row[$key]=$d['binding'][$key];
    $row+=['document_json'=>$json,'document_sha256'=>hash('sha256',$json),'proof'=>provider_stackcp_access_hmac($kind,$d)];provider_stackcp_access_row($kind,$row);
    query('INSERT INTO '.table(provider_stackcp_access_table($kind)).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    $head[$kind]++;$head[$kind.'_sha256']=$row['document_sha256'];provider_stackcp_access_checkpoint_save($head);provider_settings_audit((int)$d['common']['actor_id'],'stackcp_access_'.$kind,provider_stackcp_access_detail($kind,$row));return $row;
}
