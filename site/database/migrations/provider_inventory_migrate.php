<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_inventory_schema.php';

function provider_inventory_validate_schema(string $name,string $sql): void {
    $unique=match($name) {
        'provider_refreshes'=>['seq'=>['seq']],
        'provider_refresh_results','provider_package_link_events','provider_domain_link_events'=>[],
        'provider_observation_packages'=>['observation_identity'=>['refresh_id','package_id']],
        'provider_package_links'=>['link_target'=>['profile_id','package_id']],
        'provider_domain_links'=>['link_target'=>['profile_id','domain']],
        default=>throw new LogicException('Unknown provider inventory storage.'),
    };
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $sequenceColumn=(in_array($name,['provider_package_link_events','provider_domain_link_events'],true) && $column['Field']==='id')
            || ($name==='provider_refreshes' && $column['Field']==='seq');
        if($column['Extra']!=='' && !($sequenceColumn && $column['Extra']==='auto_increment')) throw new RuntimeException('Incompatible provider inventory columns. Existing data was preserved.');
        if($column['Default']!==null) throw new RuntimeException('Incompatible provider inventory columns. Existing data was preserved.');
        $expected=match(true) {
            ($name==='provider_package_link_events' || $name==='provider_domain_link_events') && $column['Field']==='id'=>null,
            in_array($column['Field'],['id','profile_id','refresh_id','state','reason','scope','coverage','skip_reasons','created_at','unmapped_keys','event','key_fingerprint'],true)=>'ascii_bin',
            in_array($column['Field'],['name','names','package_type_name','stack_users','package_labels','domain'],true)=>'utf8mb4_unicode_ci',
            default=>null,
        };
        if($column['Collation']!==$expected) throw new RuntimeException('Incompatible provider inventory identifier or text storage. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    foreach($unique as $key=>$columns)if($key!=='PRIMARY')$expected[$key]=[0,$columns];
    $other=match($name) {
        'provider_refreshes'=>['profile_refreshes'=>['profile_id','started_epoch'],'actor_refreshes'=>['actor_id','started_epoch']],
        'provider_refresh_results'=>[],
        'provider_observation_packages'=>['package_history'=>['package_id','refresh_id']],
        'provider_package_links'=>['client_links'=>['client_id'],'link_actor'=>['linked_by']],
        'provider_package_link_events'=>['profile_events'=>['profile_id','recorded_at'],'client_events'=>['client_id','recorded_at'],'event_actor'=>['actor_id']],
        'provider_domain_links'=>['client_links'=>['client_id'],'link_actor'=>['linked_by']],
        'provider_domain_link_events'=>['profile_events'=>['profile_id','recorded_at'],'client_events'=>['client_id','recorded_at'],'event_actor'=>['actor_id']],
    };
    foreach($other as $key=>$columns)$expected[$key]=[1,$columns];
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null || $index['Index_type']!=='BTREE') throw new RuntimeException('Incompatible provider inventory indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];
        $actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible provider inventory indexes. Existing data was preserved.');
    $expected=match($name) {
        'provider_refreshes'=>['profile_id'=>['provider_profiles','id'],'actor_id'=>['users','id']],
        'provider_refresh_results'=>['id'=>['provider_refreshes','id']],
        'provider_observation_packages'=>['refresh_id'=>['provider_refreshes','id']],
        'provider_package_links'=>['profile_id'=>['provider_profiles','id'],'client_id'=>['studio_clients','id'],'linked_by'=>['users','id']],
        'provider_package_link_events'=>['actor_id'=>['users','id']],
        'provider_domain_links'=>['profile_id'=>['provider_profiles','id'],'client_id'=>['studio_clients','id'],'linked_by'=>['users','id']],
        'provider_domain_link_events'=>['actor_id'=>['users','id']],
    };
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible provider inventory relationships. Existing data was preserved.');
    $seen=[];
    foreach($relations as $relation) {
        $e=$expected[$relation['COLUMN_NAME']]??null;
        if(!$e || isset($seen[$relation['COLUMN_NAME']]) || $relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0] || $relation['REFERENCED_COLUMN_NAME']!==$e[1] || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible provider inventory relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}

/** Adds the binding columns to version-1 storage. Columns are appended so existing data and column order are preserved. */
function provider_inventory_upgrade_v2(): void {
    $add=function(string $name,string $column,string $definition):void {
        if(!one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].$name,$column]))db()->exec('ALTER TABLE '.table($name).' ADD COLUMN '.$column.' '.$definition);
    };
    $add('provider_refresh_results','key_fingerprint','CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    $add('provider_package_links','key_fingerprint','CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    $add('provider_package_links','profile_revision','INT UNSIGNED NOT NULL');
}

/**
 * Prepares inventory storage. Requires provider settings and the client
 * workspace to exist first — links reference both. The owner's current
 * password authorizes the change; existing tables are validated, never
 * rebuilt or truncated.
 */
function provider_inventory_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function()use($password):void {
        $actor=provider_settings_owner($password);
        if(!in_array((one('SELECT value FROM '.table('settings').' WHERE name=?',['studio_schema_version'])['value']??''),['2','3'],true))throw new RuntimeException('Prepare the client workspace before provider inventory.');
        $version=(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_inventory_schema_version'])['value']??'');
        if(!in_array($version,['','1','2','3'],true))throw new RuntimeException('This provider inventory storage version is not supported. Existing data was preserved.');
        $missing=[];
        foreach(provider_inventory_schema(config()['db']['prefix']) as $name=>$sql) {
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))continue;
            if($version==='3' || ($version!=='' && !in_array($name,['provider_domain_links','provider_domain_link_events'],true)))throw new RuntimeException('Expected provider inventory storage is missing. Restore the original database before preparing; existing data was preserved.');
            $missing[$name]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        if($version==='1')provider_inventory_upgrade_v2();
        foreach(provider_inventory_schema(config()['db']['prefix']) as $name=>$sql)provider_inventory_validate_schema($name,$sql);
        staff_transaction(function()use($actor):void {
            save_setting('provider_inventory_schema_version','3');
            provider_settings_audit((int)$actor['id'],'inventory_prepared','20i inventory storage ready');
        });
    });
}
