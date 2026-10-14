<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_operations_schema.php';

function provider_operations_validate_schema(string $name,string $sql): void {
    $unique=match($name) {
        'provider_operations'=>['operation_key'=>['idempotency_key']],
        'provider_operation_grants'=>['seq'=>['seq']],
        default=>throw new LogicException('Unknown provider operation storage.'),
    };
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $sequence=$column['Field']==='seq'&&$name==='provider_operation_grants';
        if(($column['Extra']!=='')!==$sequence || ($column['Default']!==null&&!$sequence)) throw new RuntimeException('Incompatible provider operation columns. Existing data was preserved.');
        $expected=match($column['Field']) {
            'idempotency_key','fingerprint','operation','status','provider_code','external_id','id','profile_id','scope_fingerprint','consumed_key','reconciled_outcome'=>'ascii_bin',
            'result_message','scope_params'=>'utf8mb4_unicode_ci',
            default=>null,
        };
        if($column['Collation']!==$expected) throw new RuntimeException('Incompatible provider operation identifier or text storage. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    foreach($unique as $key=>$columns)$expected[$key]=[0,$columns];
    $other=match($name) {
        'provider_operations'=>['operation_history'=>['operation','claimed_epoch']],
        'provider_operation_grants'=>['grant_scope'=>['profile_id','operation'],'grant_actor'=>['granted_by'],'grant_revoker'=>['revoked_by']],
    };
    foreach($other as $key=>$columns)$expected[$key]=[1,$columns];
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null || $index['Index_type']!=='BTREE') throw new RuntimeException('Incompatible provider operation indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];
        $actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible provider operation indexes. Existing data was preserved.');
    $expected=match($name) {
        'provider_operations'=>[],
        'provider_operation_grants'=>['profile_id'=>['provider_profiles','id'],'granted_by'=>['users','id'],'revoked_by'=>['users','id']],
    };
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible provider operation relationships. Existing data was preserved.');
    $seen=[];
    foreach($relations as $relation) {
        $e=$expected[$relation['COLUMN_NAME']]??null;
        if(!$e || isset($seen[$relation['COLUMN_NAME']]) || $relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0] || $relation['REFERENCED_COLUMN_NAME']!==$e[1] || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible provider operation relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}

function provider_operations_ready(): bool {
    return (one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_operations_schema_version'])['value']??'')==='4';
}

/**
 * Append-only upgrade steps; columns are appended so existing data and
 * column order are preserved.
 *  v2 — approved-revision binding (profile_revision) on grants.
 *  v3 — approval-request storage (scope_params, consumed_key,
 *       consumed_at) for single-use executed grants.
 *  v4 — reconciliation results (reconciled_outcome, reconciled_epoch)
 *       on the ledger; the recorded provider outcome stays immutable.
 */
function provider_operations_upgrade_v2(): void {
    $add=function(string $column,string $definition):void {
        if(!one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].'provider_operation_grants',$column]))db()->exec('ALTER TABLE '.table('provider_operation_grants').' ADD COLUMN '.$column.' '.$definition);
    };
    $add('profile_revision','INT UNSIGNED NOT NULL');
}
function provider_operations_upgrade_v3(): void {
    $add=function(string $column,string $definition):void {
        if(!one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].'provider_operation_grants',$column]))db()->exec('ALTER TABLE '.table('provider_operation_grants').' ADD COLUMN '.$column.' '.$definition);
    };
    $add('scope_params','VARCHAR(4096) NULL');
    $add('consumed_key','VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NULL');
    $add('consumed_at','DATETIME NULL');
}
function provider_operations_upgrade_v4(): void {
    $add=function(string $column,string $definition):void {
        if(!one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].'provider_operations',$column]))db()->exec('ALTER TABLE '.table('provider_operations').' ADD COLUMN '.$column.' '.$definition);
    };
    $add('reconciled_outcome','VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL');
    $add('reconciled_epoch','BIGINT UNSIGNED NULL');
}

function provider_operations_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function()use($password):void {
        $actor=provider_settings_owner($password,false);
        $version=(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_operations_schema_version'])['value']??'');
        if(!in_array($version,['','1','2','3','4'],true))throw new RuntimeException('This provider operation storage version is not supported. Existing data was preserved.');
        foreach(provider_operations_schema(config()['db']['prefix']) as $name=>$sql) {
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
        }
        if($version==='1')provider_operations_upgrade_v2();
        if(in_array($version,['1','2'],true))provider_operations_upgrade_v3();
        if(in_array($version,['1','2','3'],true))provider_operations_upgrade_v4();
        foreach(provider_operations_schema(config()['db']['prefix']) as $name=>$sql)provider_operations_validate_schema($name,$sql);
        staff_transaction(function()use($actor,$version):void {
            if(!provider_operations_ready()) {
                if($version==='')foreach(array_keys(provider_operations_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized provider operation records exist. No data was adopted.');
                save_setting('provider_operations_schema_version','4');
            }
            provider_settings_audit((int)$actor['id'],'operations_setup','Additive provider operation storage verified');
        });
    });
}
