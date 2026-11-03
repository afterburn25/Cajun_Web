<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/package_php.php';
require_once CWP_CODE_ROOT.'/app/providers/php_controls_delegation_document.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_php_control_delegation_schema.php';

/** No network action may inherit a transaction; local contexts use a fresh transaction under this mutex. */
function provider_php_controls_guard(bool $transaction=false):void {
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(($transaction&&!db()->inTransaction())||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()!==1)throw new LogicException('PHP change authority requires its owning mutex and local transaction.');
}
function provider_php_controls_namespaces():array {
    $prefix=config()['db']['prefix'].'provider_php_control_delegation';
    $tables=array_column(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen($prefix),$prefix]),'TABLE_NAME');
    $settings=array_column(all('SELECT name FROM '.table('settings').' WHERE BINARY LEFT(name,?)=BINARY ? ORDER BY name',[strlen('provider_php_control_delegation_'),'provider_php_control_delegation_']),'name');
    $actions=array_column(all('SELECT DISTINCT action FROM '.table('audit').' WHERE BINARY LEFT(action,?)=BINARY ? ORDER BY action',[strlen('provider.php_control_delegation_'),'provider.php_control_delegation_']),'action');
    return ['tables'=>$tables,'settings'=>$settings,'actions'=>$actions];
}
function provider_php_controls_validate_schema():void {
    $name='provider_php_control_delegations';$sql=provider_php_control_delegation_schema(config()['db']['prefix'])[$name];
    catalog_validate_schema($name,$sql,['php_control_delegation_revision'=>['link_id','revision']]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){$text=preg_match('/^(?:char|longtext)/i',$c['Type'])===1;if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==($text?'ascii_bin':null))throw new RuntimeException('PHP delegation columns need recovery.');}
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('PHP delegation indexes need recovery.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);
    $expected=['PRIMARY'=>[0,['id']],'php_control_delegation_client'=>[1,['client_id']],'php_control_delegation_owner'=>[1,['owner_id']],'php_control_delegation_revision'=>[0,['link_id','revision']]];
    if($actual!==$expected)throw new RuntimeException('PHP delegation indexes need recovery.');
    $foreign=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.COLUMN_NAME',[config()['db']['prefix'].$name]);
    if(count($foreign)!==2)throw new RuntimeException('PHP delegation relationships need recovery.');
    foreach(['client_id'=>'studio_clients','owner_id'=>'users'] as $column=>$target){$f=array_shift($foreign);if($f['COLUMN_NAME']!==$column||$f['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$f['REFERENCED_COLUMN_NAME']!=='id'||!in_array($f['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($f['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('PHP delegation relationships need recovery.');}
}
function provider_php_controls_counter():int {
    $v=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_php_control_delegation_count'])['value']??'';
    if(!is_string($v)||preg_match('/\A(?:0|[1-9][0-9]{0,14})\z/',$v)!==1)throw new RuntimeException('PHP delegation count needs recovery.');return (int)$v;
}
function provider_php_controls_row_values(array $r):array {
    $out=[];foreach(['id','link_id','client_id','revision','owner_id','recorded_epoch','previous_sha256','document_json','document_sha256'] as $key)$out[]=(string)$r[$key];return $out;
}
function provider_php_controls_proof(array $r):string {return hash_hmac('sha256','packages.phpversion|delegation|'.provider_php_controls_json(provider_php_controls_row_values($r)),provider_settings_vault_key());}
function provider_php_controls_audit_detail(array $r):string {return 'PHP change delegation '.$r['id'].' '.provider_php_digest([...provider_php_controls_row_values($r),$r['proof']]);}
function provider_php_controls_decode(array $r):array {
    try{$d=provider_php_controls_delegation_parse($r['document_json']);}catch(JsonException|InvalidArgumentException|TypeError $e){throw new RuntimeException('PHP delegation document needs recovery.',0,$e);}
    foreach(['id','link_id','previous_sha256'] as $key)if($r[$key]!==$d[$key])throw new RuntimeException('PHP delegation identity needs recovery.');
    foreach(['client_id','revision','owner_id','recorded_epoch'] as $key)if((string)$r[$key]!== (string)$d[$key])throw new RuntimeException('PHP delegation sequence needs recovery.');
    if(!hash_equals(hash('sha256',$r['document_json']),$r['document_sha256'])||!hash_equals(provider_php_controls_proof($r),$r['proof']))throw new RuntimeException('PHP delegation proof needs recovery.');
    $binding=new \CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding($d['binding']);provider_php_retained_binding($binding);
    $audit=all('SELECT id,user_id FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=? LIMIT 2',['provider.php_control_delegation_changed',provider_php_controls_audit_detail($r)]);
    if(count($audit)!==1||(int)$audit[0]['user_id']!==$d['owner_id'])throw new RuntimeException('PHP delegation audit needs recovery.');
    return ['document'=>$d,'sha256'=>$r['document_sha256'],'binding'=>$binding,'audit_id'=>(int)$audit[0]['id']];
}
function provider_php_controls_preparation_witness(string $kind):void {
    $detail=$kind==='started'?'PHP change delegation preparation started':'PHP change delegation storage ready';
    $rows=all('SELECT user_id,detail FROM '.table('audit').' WHERE BINARY action=? LIMIT 2',['provider.php_control_delegation_'.$kind]);
    if(count($rows)!==1||(int)$rows[0]['user_id']<1||$rows[0]['detail']!==$detail)throw new RuntimeException('PHP delegation preparation needs recovery.');
}
/** Full history validation, not a paginated-work guarantee. No DDL, repair or provider access. */
function provider_php_controls_ready():bool {
    provider_php_controls_guard();$n=provider_php_controls_namespaces();$version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_php_control_delegation_schema_version'])['value']??'';
    if($version===''){if($n['tables']||$n['settings']||$n['actions'])throw new RuntimeException('Restore original PHP delegation storage.');return false;}
    $expectedTable=config()['db']['prefix'].'provider_php_control_delegations';
    if($version!=='1'||$n['tables']!==[$expectedTable]||$n['settings']!==['provider_php_control_delegation_count','provider_php_control_delegation_schema_version']||array_diff($n['actions'],['provider.php_control_delegation_started','provider.php_control_delegation_prepared','provider.php_control_delegation_changed']))throw new RuntimeException('PHP delegation storage needs recovery.');
    if(!provider_php_ready())throw new RuntimeException('Original PHP observation storage is required.');provider_php_controls_validate_schema();
    provider_php_controls_preparation_witness('started');provider_php_controls_preparation_witness('prepared');$count=provider_php_controls_counter();
    if((int)query('SELECT COUNT(*) FROM '.table('provider_php_control_delegations'))->fetchColumn()!==$count||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.php_control_delegation_changed'])->fetchColumn()!==$count)throw new RuntimeException('PHP delegation history counts disagree.');
    $link='';$revision=0;$previous=null;$seen=0;
    do{$rows=all('SELECT * FROM '.table('provider_php_control_delegations').' WHERE link_id>? OR (link_id=? AND revision>?) ORDER BY link_id,revision LIMIT 64',[$link,$link,$revision]);
        foreach($rows as $row){$record=provider_php_controls_decode($row);$d=$record['document'];if($d['link_id']!==$link){$previous=null;$revision=0;}
            if($d['revision']!==$revision+1||$d['previous_id']!==($previous['document']['id']??'')||$d['previous_sha256']!==($previous['sha256']??str_repeat('0',64))||($previous&&($d['client_id']!==$previous['document']['client_id']||$d['recorded_epoch']<$previous['document']['recorded_epoch']||$record['audit_id']<=$previous['audit_id'])))throw new RuntimeException('PHP delegation chain needs recovery.');
            $link=$d['link_id'];$revision=$d['revision'];$previous=$record;$seen++;
        }
    }while(count($rows)===64);
    if($seen!==$count)throw new RuntimeException('PHP delegation sequence needs recovery.');return true;
}
function provider_php_controls_permission(string $linkId):?array {
    provider_php_controls_guard();provider_php_id($linkId);if(!provider_php_controls_ready())return null;
    $r=one('SELECT * FROM '.table('provider_php_control_delegations').' WHERE link_id=? ORDER BY revision DESC LIMIT 1',[$linkId]);return $r?provider_php_controls_decode($r):null;
}
function provider_php_controls_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Prepare PHP delegation outside a transaction.');
    staff_locked(function()use($password):void{
        unset($GLOBALS['cwp_settings']);$owner=provider_settings_owner($password);if(!provider_php_ready()||!studio_ready())throw new RuntimeException('Prepare hosting inventory, PHP observations and clients first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_php_control_delegation_schema_version'])['value']??'';
        if($version==='1'){provider_php_controls_ready();return;}
        if($version===''){
            provider_php_controls_ready();
            staff_transaction(function()use($owner):void{save_setting('provider_php_control_delegation_schema_version','preparing-1');provider_settings_audit((int)$owner['id'],'php_control_delegation_started','PHP change delegation preparation started');});
        }elseif($version!=='preparing-1')throw new RuntimeException('Unknown PHP delegation preparation state.');
        $n=provider_php_controls_namespaces();$target=config()['db']['prefix'].'provider_php_control_delegations';
        if(!in_array($n['tables'],[[],[$target]],true)||$n['settings']!==['provider_php_control_delegation_schema_version']||$n['actions']!==['provider.php_control_delegation_started'])throw new RuntimeException('PHP delegation preparation needs recovery.');
        provider_php_controls_preparation_witness('started');
        if($n['tables']){provider_php_controls_validate_schema();if((int)query('SELECT COUNT(*) FROM '.table('provider_php_control_delegations'))->fetchColumn()!==0)throw new RuntimeException('Restore original PHP delegation history.');}
        else db()->exec(provider_php_control_delegation_schema(config()['db']['prefix'])['provider_php_control_delegations']);
        provider_php_controls_validate_schema();
        staff_transaction(function()use($owner):void{save_setting('provider_php_control_delegation_count','0');save_setting('provider_php_control_delegation_schema_version','1');provider_settings_audit((int)$owner['id'],'php_control_delegation_prepared','PHP change delegation storage ready');});
        provider_php_controls_ready();
    });
}
