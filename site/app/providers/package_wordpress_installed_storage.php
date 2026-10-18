<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/provider_package_wordpress_installed_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function provider_wp_installed_guard():void {
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(!db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1)throw new LogicException('WordPress installation observation persistence requires the caller transaction and mutex.');
}
function provider_wp_installed_exists(string $name):bool {
    if(!array_key_exists($name,provider_wp_installed_schema(config()['db']['prefix'])))throw new LogicException('Unknown WordPress installation observation table.');
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;
}
function provider_wp_installed_json(array $value):string {return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function provider_wp_installed_digest(array $values):string {$bytes='';foreach($values as $value){$value=(string)$value;$bytes.=strlen($value).':'.$value;}return hash('sha256',$bytes);}
/** Fixed SQL column families only, matching the WordPress installation byte digest on both supported databases. */
function provider_wp_installed_sql_digest(array $columns):string {
    $parts=[];foreach($columns as $column){$parts[]='OCTET_LENGTH(CAST('.$column.' AS CHAR))';$parts[]="':'";$parts[]='CAST('.$column.' AS CHAR)';}
    return "SHA2(CONCAT(BINARY '',".implode(',',$parts).'),256)';
}
function provider_wp_installed_counter(string $kind):int {
    if(!in_array($kind,['started','finished','permission'],true))throw new LogicException('Unknown WordPress installation observation counter.');
    $value=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_wp_installed_'.$kind.'_count'])['value']??'';
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,14})\z/',$value))throw new RuntimeException('WordPress installation observation counts need recovery.');return (int)$value;
}
function provider_wp_installed_advance(string $kind):void {provider_wp_installed_guard();$count=provider_wp_installed_counter($kind);if($count>=999999999999999)throw new RuntimeException('WordPress installation observation history is full.');save_setting('provider_wp_installed_'.$kind.'_count',(string)($count+1));}
function provider_wp_installed_start_values(array $f,array $b):array {return [$f['id'],$f['profile_id'],$f['profile_revision'],$f['actor_id'],$f['started_at'],$f['started_epoch'],$f['operation'],$f['seq'],$b['link_id'],$b['profile_id'],$b['package_id'],$b['client_id'],$b['binding_json'],$b['binding_sha256'],$b['proof']];}
function provider_wp_installed_start_sql():string {return provider_wp_installed_sql_digest(['f.id','f.profile_id','f.profile_revision','f.actor_id','f.started_at','f.started_epoch','f.operation','f.seq','b.link_id','b.profile_id','b.package_id','b.client_id','b.binding_json','b.binding_sha256','b.proof']);}
function provider_wp_installed_result_values(array $r):array {
    $values=[];foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation','seq','state','reason','scope','coverage','package_count','skipped','skip_reasons','finished_at','elapsed_ms','key_fingerprint'] as $key)$values[]=(string)$r[$key];return $values;
}
function provider_wp_installed_finish_values(array $r,array $b,?array $p):array {return [...provider_wp_installed_result_values($r),$b['proof'],$p['value_json']??'',$p['observed_epoch']??'',$p['proof']??''];}
function provider_wp_installed_finish_sql():string {return provider_wp_installed_sql_digest(['f.id','f.profile_id','f.profile_revision','f.actor_id','f.started_at','f.started_epoch','f.operation','f.seq','r.state','r.reason','r.scope','r.coverage','r.package_count','r.skipped','r.skip_reasons','r.finished_at','r.elapsed_ms','r.key_fingerprint','b.proof',"COALESCE(p.value_json,'')","COALESCE(CAST(p.observed_epoch AS CHAR),'')","COALESCE(p.proof,'')"]);}
function provider_wp_installed_permission_values(array $p):array {return [$p['id'],$p['link_id'],$p['client_id'],$p['revision'],$p['actor_id'],$p['previous_sha256'],$p['document_json'],$p['document_sha256']];}
function provider_wp_installed_permission_sql():string {return provider_wp_installed_sql_digest(['p.id','p.link_id','p.client_id','p.revision','p.actor_id','p.previous_sha256','p.document_json','p.document_sha256']);}
/** No DDL or history repair on any read. Independent counters fence paired losses. */
function provider_wp_installed_ready():bool {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_wp_installed_schema_version'])['value']??'';$names=array_keys(provider_wp_installed_schema(config()['db']['prefix']));
    foreach(['audit','settings','provider_refreshes','provider_refresh_results'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('WordPress installation observations require transactional storage.');}
    if($version===''){
        $witness=one('SELECT id FROM '.table('audit').' WHERE BINARY action IN (?,?,?,?) LIMIT 1',['provider.wp_installed_prepared','provider.wp_installed_started','provider.wp_installed_finished','provider.wp_installed_permission'])!==null||one('SELECT name FROM '.table('settings').' WHERE name IN (?,?,?) LIMIT 1',['provider_wp_installed_started_count','provider_wp_installed_finished_count','provider_wp_installed_permission_count'])!==null;
        if(provider_inventory_ready()&&one('SELECT id FROM '.table('provider_refreshes').' WHERE BINARY operation=? LIMIT 1',['package.wordpress-installed']))$witness=true;
        foreach($names as $name)if(provider_wp_installed_exists($name)&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)$witness=true;
        if($witness)throw new RuntimeException('Restore the original WordPress installation observation history.');return false;
    }
    if($version!=='1'||!provider_inventory_ready())throw new RuntimeException('WordPress installation observation storage needs recovery.');
    foreach($names as $name){if(!provider_wp_installed_exists($name))throw new RuntimeException('WordPress installation observation storage needs recovery.');$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('WordPress installation observation history must be transactional.');}
    $f=table('provider_refreshes');$r=table('provider_refresh_results');$b=table('provider_wp_installed_reads');$p=table('provider_wp_installed_observations');$a=table('audit');$q=table('provider_wp_installed_permissions');$op="BINARY f.operation='package.wordpress-installed'";$count=static fn(string $sql):int=>(int)query($sql)->fetchColumn();
    $started=provider_wp_installed_counter('started');$finished=provider_wp_installed_counter('finished');$permissions=provider_wp_installed_counter('permission');
    if($count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.wp_installed_prepared'")!==1||$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.wp_installed_prepared' AND BINARY detail='WordPress installation observation storage ready'")!==1)throw new RuntimeException('WordPress installation observation preparation needs recovery.');
    if($started!==$count("SELECT COUNT(*) FROM $f f WHERE $op")||$started!==$count("SELECT COUNT(*) FROM $b")||$started!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.wp_installed_started'")||$finished!==$count("SELECT COUNT(*) FROM $f f JOIN $r r ON r.id=f.id WHERE $op")||$finished!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.wp_installed_finished'")||$permissions!==$count("SELECT COUNT(*) FROM $q")||$permissions!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.wp_installed_permission'"))throw new RuntimeException('WordPress installation observation history counts disagree.');
    $witness=static fn(string $action,string $detail,string $actor='f.actor_id'):string=>"(SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail AND a.user_id=$actor)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail)<>1";
    $start="CONCAT(BINARY 'WordPress installation read ',f.id,' started ',".provider_wp_installed_start_sql().')';$commonStart="CONCAT(BINARY 'Refresh ',f.id,' profile ',f.profile_id,' revision ',f.profile_revision)";
    if(one("SELECT f.id FROM $f f LEFT JOIN $b b ON b.id=f.id WHERE $op AND (b.id IS NULL OR BINARY b.binding_sha256<>BINARY SHA2(b.binding_json,256) OR ".$witness('provider.wp_installed_started',$start).' OR '.$witness('provider.refresh_started',$commonStart).') LIMIT 1'))throw new RuntimeException('WordPress installation observation intent history needs recovery.');
    if(one("SELECT b.id FROM $b b LEFT JOIN $f f ON f.id=b.id WHERE f.id IS NULL OR NOT ($op) LIMIT 1"))throw new RuntimeException('WordPress installation observation binding is orphaned.');
    $finish="CONCAT(BINARY 'WordPress installation read ',f.id,' finished ',".provider_wp_installed_finish_sql().')';$commonFinish="CONCAT(BINARY 'Refresh ',f.id,' ',r.state,IF(r.reason='','',' '),r.reason)";
    if(one("SELECT f.id FROM $f f JOIN $r r ON r.id=f.id JOIN $b b ON b.id=f.id LEFT JOIN $p p ON p.id=f.id WHERE $op AND ((BINARY r.state='complete' AND p.id IS NULL) OR (BINARY r.state<>'complete' AND p.id IS NOT NULL) OR ".$witness('provider.wp_installed_finished',$finish).' OR '.$witness('provider.refresh_finished',$commonFinish).') LIMIT 1'))throw new RuntimeException('WordPress installation observation result history needs recovery.');
    if(one("SELECT p.id FROM $p p LEFT JOIN $b b ON b.id=p.id LEFT JOIN $r r ON r.id=p.id WHERE b.id IS NULL OR r.id IS NULL OR BINARY r.state<>'complete' LIMIT 1"))throw new RuntimeException('WordPress installation observation value is orphaned.');
    $permission="CONCAT(BINARY 'WordPress installation permission ',p.id,' ',".provider_wp_installed_permission_sql().')';
    if(one("SELECT p.id FROM $q p LEFT JOIN $q prev ON prev.link_id=p.link_id AND prev.revision=p.revision-1 WHERE BINARY p.document_sha256<>BINARY SHA2(p.document_json,256) OR (p.revision=1 AND BINARY p.previous_sha256<>BINARY REPEAT('0',64)) OR (p.revision>1 AND (prev.id IS NULL OR BINARY prev.document_sha256<>BINARY p.previous_sha256 OR prev.client_id<>p.client_id)) OR ".$witness('provider.wp_installed_permission',$permission,'p.actor_id').' LIMIT 1'))throw new RuntimeException('WordPress installation sharing history needs recovery.');
    return true;
}
function provider_wp_installed_validate_schema():void {
    $expectedIndexes=[
        'provider_wp_installed_reads'=>['PRIMARY'=>[0,['id']],'wp_installed_link'=>[1,['link_id']]],
        'provider_wp_installed_observations'=>['PRIMARY'=>[0,['id']]],
        'provider_wp_installed_permissions'=>['PRIMARY'=>[0,['id']],'wp_installed_permission_revision'=>[0,['link_id','revision']],'wp_installed_permission_client'=>[1,['client_id']]],
    ];
    foreach(provider_wp_installed_schema(config()['db']['prefix']) as $name=>$sql){
        catalog_validate_schema($name,$sql,$name==='provider_wp_installed_permissions'?['wp_installed_permission_revision'=>['link_id','revision']]:[]);
        foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column){$text=preg_match('/^(?:char|longtext)/i',$column['Type'])===1;if($column['Extra']!==''||$column['Default']!==null||$column['Collation']!==($text?'ascii_bin':null))throw new RuntimeException('Incompatible WordPress installation observation columns.');}
        $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible WordPress installation observation indexes.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);$expected=$expectedIndexes[$name];ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible WordPress installation observation indexes.');
        $foreign=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
        [$column,$target]=$name==='provider_wp_installed_permissions'?['client_id','studio_clients']:['id',$name==='provider_wp_installed_reads'?'provider_refreshes':'provider_wp_installed_reads'];$f=$foreign[0]??[];
        if(count($foreign)!==1||($f['COLUMN_NAME']??'')!==$column||$f['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$f['REFERENCED_COLUMN_NAME']!=='id'||!in_array($f['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($f['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible WordPress installation observation relationships.');
    }
}
function provider_wp_installed_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('WordPress installation observation preparation cannot run in a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);if(!provider_inventory_ready()||!staff_ready()||!studio_ready())throw new RuntimeException('Prepare hosting inventory and clients first.');
        if(provider_wp_installed_ready()){provider_wp_installed_validate_schema();return;}
        foreach(provider_wp_installed_schema(config()['db']['prefix']) as $name=>$sql)if(!provider_wp_installed_exists($name))db()->exec($sql);
        provider_wp_installed_validate_schema();staff_transaction(function()use($actor):void{save_setting('provider_wp_installed_schema_version','1');foreach(['started','finished','permission'] as $kind)save_setting('provider_wp_installed_'.$kind.'_count','0');provider_settings_audit((int)$actor['id'],'wp_installed_prepared','WordPress installation observation storage ready');});
    });
}
