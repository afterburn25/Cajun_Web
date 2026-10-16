<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/provider_cloud_providers_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function provider_cloud_providers_table_exists():bool {
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_observation_cloud_providers'])!==null;
}
function provider_cloud_providers_history_exists():bool {
    return one('SELECT id FROM '.table('audit').' WHERE BINARY action IN (?,?,?) LIMIT 1',['provider.cloud_providers_prepared','provider.cloud_providers_started','provider.cloud_providers_finished'])!==null
        ||(provider_inventory_ready()&&one('SELECT id FROM '.table('provider_refreshes').' WHERE operation=? LIMIT 1',['cloud.providers.list'])!==null);
}
function provider_cloud_providers_guard():void {
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(!db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()!==1)throw new LogicException('Cloud provider persistence requires the owning account transaction and mutex.');
}
function provider_cloud_providers_start_detail(array $row):string {
    return 'Cloud provider refresh '.$row['id'].' profile '.$row['profile_id'].' revision '.(int)$row['profile_revision'].' sequence '.(int)$row['seq'];
}
/** Canonical retained common-result identity; never includes a credential or raw body. */
function provider_cloud_providers_result_context(array $row):array {
    $out=[];foreach(['id','profile_id','operation','started_at','state','reason','scope','coverage','skip_reasons','finished_at','key_fingerprint'] as $key)$out[$key]=(string)$row[$key];
    foreach(['profile_revision','actor_id','started_epoch','package_count','skipped','elapsed_ms','seq'] as $key)$out[$key]=(int)$row[$key];
    return $out;
}
function provider_cloud_providers_finish_detail(array $row,string $proof,string $identifiers='',?int $observedAt=null):string {
    $bytes='';foreach([...array_values(provider_cloud_providers_result_context($row)),$proof,$identifiers,$observedAt??''] as $value){$value=(string)$value;$bytes.=strlen($value).':'.$value;}
    return 'Cloud provider refresh '.$row['id'].' result '.hash('sha256',$bytes);
}
/** Exact SQL counterpart of the length-prefixed finish witness; fixed column family only. */
function provider_cloud_providers_finish_sql():string {
    $columns=['f.id','f.profile_id','f.operation','f.started_at','r.state','r.reason','r.scope','r.coverage','r.skip_reasons','r.finished_at','r.key_fingerprint','f.profile_revision','f.actor_id','f.started_epoch','r.package_count','r.skipped','r.elapsed_ms','f.seq',"COALESCE(p.proof,'')","COALESCE(p.identifiers,'')","COALESCE(CAST(p.observed_epoch AS CHAR),'')"];
    $parts=[];foreach($columns as $column){$parts[]='OCTET_LENGTH(CAST('.$column.' AS CHAR))';$parts[]="':'";$parts[]='CAST('.$column.' AS CHAR)';}
    return "CONCAT(BINARY 'Cloud provider refresh ',f.id,' result ',SHA2(CONCAT(BINARY '',".implode(',',$parts).'),256))';
}
function provider_cloud_providers_counter(string $kind):int {
    if(!in_array($kind,['started','finished'],true))throw new LogicException('Unsupported cloud provider counter.');
    $value=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_providers_'.$kind.'_count'])['value']??'';
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,14})\z/D',$value))throw new RuntimeException('Cloud provider count history needs recovery.');
    return (int)$value;
}
function provider_cloud_providers_advance(string $kind):void {
    provider_cloud_providers_guard();$count=provider_cloud_providers_counter($kind);
    if($count>=999999999999999)throw new RuntimeException('Cloud provider history capacity reached.');
    save_setting('provider_cloud_providers_'.$kind.'_count',(string)($count+1));
}
/** Bounded projection query: sequence, never wall clock, defines latest intent. */
function provider_cloud_providers_source(string $profileId,bool $complete=false):?array {
    return one('SELECT f.*,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.skip_reasons,r.finished_at,r.elapsed_ms,r.key_fingerprint FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.operation=? AND f.profile_id=?'.($complete?' AND r.state=\'complete\'':'').' ORDER BY f.seq DESC LIMIT 1',['cloud.providers.list',$profileId]);
}
/** Read-only recovery checks. Every attempt and result has an independent exact audit witness. */
function provider_cloud_providers_ready():bool {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_providers_schema_version'])['value']??'';
    if($version===''){
        if(provider_cloud_providers_history_exists()||one('SELECT name FROM '.table('settings').' WHERE name IN (?,?) LIMIT 1',['provider_cloud_providers_started_count','provider_cloud_providers_finished_count'])!==null||(provider_cloud_providers_table_exists()&&(int)query('SELECT COUNT(*) FROM '.table('provider_observation_cloud_providers'))->fetchColumn()>0))throw new RuntimeException('Cloud provider history needs recovery from the original database.');
        return false;
    }
    if($version!=='1'||!provider_inventory_ready()||!provider_cloud_providers_table_exists())throw new RuntimeException('Cloud provider storage needs recovery from the original database.');
    $f=table('provider_refreshes');$r=table('provider_refresh_results');$p=table('provider_observation_cloud_providers');$a=table('audit');$op="BINARY f.operation='cloud.providers.list'";
    $started=provider_cloud_providers_counter('started');$finished=provider_cloud_providers_counter('finished');
    $count=static fn(string $sql):int=>(int)query($sql)->fetchColumn();
    if($count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.cloud_providers_prepared'")!==1||$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.cloud_providers_prepared' AND BINARY detail='Cloud provider observation storage ready'")!==1)throw new RuntimeException('Cloud provider preparation history needs recovery.');
    if($started!==$count("SELECT COUNT(*) FROM $f f WHERE $op")||$started!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.cloud_providers_started'")||$finished!==$count("SELECT COUNT(*) FROM $f f JOIN $r r ON r.id=f.id WHERE $op")||$finished!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.cloud_providers_finished'"))throw new RuntimeException('Cloud provider count history needs recovery.');
    // Correspondence is checked in SQL, with fixed-size PHP results even as history grows.
    $start="CONCAT(BINARY 'Cloud provider refresh ',f.id,' profile ',f.profile_id,' revision ',f.profile_revision,' sequence ',f.seq)";
    $commonStart="CONCAT(BINARY 'Refresh ',f.id,' profile ',f.profile_id,' revision ',f.profile_revision)";
    $finish=provider_cloud_providers_finish_sql();
    $commonFinish="CONCAT(BINARY 'Refresh ',f.id,' ',r.state,IF(r.reason='','',' '),r.reason)";
    $witness=static fn(string $action,string $detail):string=>"(SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail AND a.user_id=f.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='$action' AND BINARY a.detail=BINARY $detail)<>1";
    if(one("SELECT f.id FROM $f f WHERE $op AND (".$witness('provider.cloud_providers_started',$start).' OR '.$witness('provider.refresh_started',$commonStart).') LIMIT 1')!==null)throw new RuntimeException('Cloud provider attempt history needs recovery.');
    if(one("SELECT f.id FROM $f f JOIN $r r ON r.id=f.id LEFT JOIN $p p ON p.id=f.id WHERE $op AND ((r.state='complete' AND p.id IS NULL) OR (r.state<>'complete' AND p.id IS NOT NULL) OR ".$witness('provider.cloud_providers_finished',$finish).' OR '.$witness('provider.refresh_finished',$commonFinish).') LIMIT 1')!==null)throw new RuntimeException('Cloud provider result history needs recovery.');
    if(one("SELECT p.id FROM $p p LEFT JOIN $f f ON f.id=p.id LEFT JOIN $r r ON r.id=p.id WHERE f.id IS NULL OR BINARY f.operation<>'cloud.providers.list' OR r.id IS NULL OR BINARY r.state<>'complete' LIMIT 1")!==null)throw new RuntimeException('Cloud provider observation history needs recovery.');
    return true;
}
function provider_cloud_providers_validate():void {
    $name='provider_observation_cloud_providers';$sql=provider_cloud_providers_schema(config()['db']['prefix'])[$name];catalog_validate_schema($name,$sql,[]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==($c['Field']==='observed_epoch'?null:'ascii_bin'))throw new RuntimeException('Incompatible cloud provider columns. Existing data was preserved.');}
    $indexes=all('SHOW INDEX FROM '.table($name));if(count($indexes)!==1||$indexes[0]['Key_name']!=='PRIMARY'||(int)$indexes[0]['Non_unique']!==0||$indexes[0]['Column_name']!=='id'||$indexes[0]['Sub_part']!==null||$indexes[0]['Index_type']!=='BTREE')throw new RuntimeException('Incompatible cloud provider indexes.');
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    $r=$relations[0]??[];if(count($relations)!==1||($r['COLUMN_NAME']??'')!=='id'||$r['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].'provider_refreshes'||$r['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible cloud provider relationships.');
}
function provider_cloud_providers_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Cloud provider preparation cannot run inside a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);if(!provider_inventory_ready())throw new RuntimeException('Prepare hosting inventory first.');
        if(provider_cloud_providers_ready()){provider_cloud_providers_validate();return;}
        $exists=provider_cloud_providers_table_exists();if($exists)provider_cloud_providers_validate();
        if(!$exists)foreach(provider_cloud_providers_schema(config()['db']['prefix']) as $sql)db()->exec($sql);
        provider_cloud_providers_validate();
        staff_transaction(function()use($actor):void{save_setting('provider_cloud_providers_schema_version','1');save_setting('provider_cloud_providers_started_count','0');save_setting('provider_cloud_providers_finished_count','0');provider_settings_audit((int)$actor['id'],'cloud_providers_prepared','Cloud provider observation storage ready');});
    });
}
