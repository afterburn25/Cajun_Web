<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/provider_registrar_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function provider_registrar_tables():array {
    $out=[];foreach(provider_registrar_schema(config()['db']['prefix']) as $name=>$sql)$out[$name]=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;return $out;
}
function provider_registrar_history():bool {
    return one('SELECT id FROM '.table('audit').' WHERE action IN (?,?,?,?) LIMIT 1',['provider.registrar_prepared','provider.registrar_link','provider.registrar_relink','provider.registrar_unlink'])!==null
        ||(provider_inventory_ready()&&one('SELECT id FROM '.table('provider_refreshes').' WHERE operation=? LIMIT 1',['domains.list'])!==null);
}
function provider_registrar_ready():bool {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_registrar_schema_version'])['value']??'';
    if($version==='') {if(provider_registrar_history())throw new RuntimeException('Registered-domain history needs recovery from the original database.');return false;}
    if($version!=='1'||in_array(false,provider_registrar_tables(),true))throw new RuntimeException('Registered-domain storage needs recovery from the original database.');
    $events=(int)query('SELECT COUNT(*) FROM '.table('provider_registrar_events'))->fetchColumn();
    $audits=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action IN (?,?,?)',['provider.registrar_link','provider.registrar_relink','provider.registrar_unlink'])->fetchColumn();
    $observed=(int)query('SELECT COUNT(*) FROM '.table('provider_registrar_observations'))->fetchColumn();
    $expected=(int)query('SELECT COALESCE(SUM(r.package_count),0) FROM '.table('provider_refresh_results').' r JOIN '.table('provider_refreshes').' f ON f.id=r.id WHERE f.operation=?',['domains.list'])->fetchColumn();
    $missing=one('SELECT e.id FROM '.table('provider_registrar_events').' e LEFT JOIN '.table('provider_registrar_events').' newer ON newer.link_id=e.link_id AND newer.id>e.id LEFT JOIN '.table('provider_registrar_links').' l ON l.id=e.link_id WHERE newer.id IS NULL AND ((e.event IN (\'link\',\'relink\') AND l.id IS NULL) OR (e.event=\'unlink\' AND l.id IS NOT NULL)) LIMIT 1');
    if($events!==$audits||$observed!==$expected||$missing!==null)throw new RuntimeException('Registered-domain history is incomplete. Restore the original database.');
    return true;
}
/** Strict additive validation: generated columns, alternate indexes and relationships are refused. */
function provider_registrar_validate(string $name,string $sql):void {
    $unique=match($name){'provider_registrar_observations'=>['observation_identity'=>['refresh_id','domain_id'],'observation_name'=>['refresh_id','domain']], 'provider_registrar_links'=>['link_target'=>['profile_id','domain_id'],'link_name'=>['profile_id','domain']], 'provider_registrar_events'=>[]};
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column){
        $auto=$name==='provider_registrar_events'&&$column['Field']==='id';
        $ascii=in_array($column['Field'],['id','link_id','profile_id','refresh_id','domain','key_fingerprint','event'],true)&&!$auto;
        if($column['Extra']!==($auto?'auto_increment':'')||$column['Default']!==null||$column['Collation']!==($ascii?'ascii_bin':null))throw new RuntimeException('Incompatible registered-domain columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];foreach($unique as $key=>$columns)$expected[$key]=[0,$columns];
    $other=match($name){'provider_registrar_observations'=>[], 'provider_registrar_links'=>['client_links'=>['client_id'],'link_actor'=>['linked_by'],'approved_observation'=>['refresh_id']], 'provider_registrar_events'=>['profile_events'=>['profile_id','recorded_at'],'client_events'=>['client_id','recorded_at'],'event_actor'=>['actor_id'],'link_events'=>['link_id']]};
    foreach($other as $key=>$columns)$expected[$key]=[1,$columns];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index){if($index['Sub_part']!==null||$index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible registered-domain indexes.');$actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];}
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible registered-domain indexes.');
    $expected=match($name){'provider_registrar_observations'=>['refresh_id'=>'provider_refreshes'],'provider_registrar_links'=>['profile_id'=>'provider_profiles','client_id'=>'studio_clients','linked_by'=>'users','refresh_id'=>'provider_refreshes'],'provider_registrar_events'=>['actor_id'=>'users']};
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible registered-domain relationships.');$seen=[];
    foreach($relations as $r){$target=$expected[$r['COLUMN_NAME']]??null;if(!$target||isset($seen[$r['COLUMN_NAME']])||$r['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$r['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible registered-domain relationships.');$seen[$r['COLUMN_NAME']]=true;}
}
function provider_registrar_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Registered-domain preparation cannot run inside a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);if(!provider_inventory_ready())throw new RuntimeException('Prepare hosting inventory first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_registrar_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unsupported registered-domain storage.');
        if($version==='1')provider_registrar_ready();
        $tables=provider_registrar_tables();$history=provider_registrar_history();
        if(($version==='1'||$history)&&in_array(false,$tables,true))throw new RuntimeException('Registered-domain history needs recovery from the original database.');
        if($version===''&&$history)throw new RuntimeException('Registered-domain history marker needs recovery.');
        if($tables['provider_registrar_events']&&!one('SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?',[config()['db']['prefix'].'provider_registrar_events','link_events']))db()->exec('ALTER TABLE '.table('provider_registrar_events').' ADD INDEX link_events(link_id)');
        foreach(provider_registrar_schema(config()['db']['prefix']) as $name=>$sql)if($tables[$name]){
            provider_registrar_validate($name,$sql);
            if($version===''&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)throw new RuntimeException('Unmarked registered-domain history needs recovery.');
        }
        foreach(provider_registrar_schema(config()['db']['prefix']) as $name=>$sql){if(!$tables[$name])db()->exec($sql);provider_registrar_validate($name,$sql);}
        if($version==='')staff_transaction(function()use($actor):void{save_setting('provider_registrar_schema_version','1');provider_settings_audit((int)$actor['id'],'registrar_prepared','Registered-domain storage ready');});
    });
}
