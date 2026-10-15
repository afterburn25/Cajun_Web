<?php
/** Closed hosting storage families. Reads never repair or recreate retained history. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function hosting_storage_family(string $module):array {
    return match($module){
        'records'=>['hosting_records_schema_version',['hosting_records','hosting_record_events'],'hosting_records.setup'],
        'operations'=>['hosting_operations_schema_version',['hosting_dispatches','hosting_operation_results','hosting_resources'],'hosting_records.operations_setup'],
        'reconciliation'=>['hosting_reconciliation_schema_version',['hosting_observations','hosting_reconciliations'],'hosting_records.reconciliation_setup'],
        'order_links'=>['hosting_order_links_schema_version',['hosting_order_links'],'hosting_records.order_links_setup'],
        'fulfillment'=>['hosting_fulfillment_schema_version',['hosting_fulfillments'],'hosting_records.fulfillment_setup'],
        default=>throw new LogicException('Unknown hosting storage family.'),
    };
}
function hosting_storage_state(string $module):array {
    [$marker,$names,$audit]=hosting_storage_family($module);
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',[$marker])['value']??'';
    $existing=[];
    foreach($names as $name)if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))$existing[]=$name;
    return ['version'=>$version,'names'=>$names,'existing'=>$existing,'setup_action'=>$audit,'complete'=>count($existing)===count($names)];
}
function hosting_storage_ready(string $module):bool {
    $state=hosting_storage_state($module);return $state['version']==='1'&&$state['complete'];
}
function hosting_storage_require(string $module):void {
    $s=hosting_storage_state($module);
    if($s['version']==='1'&&$s['complete'])return;
    if($s['version']!==''||one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',[$s['setup_action']]))throw new RuntimeException('Established hosting storage is unavailable. Restore the original database before continuing.');
    throw new RuntimeException('An owner must prepare hosting '.$module.' storage first.');
}
/** Run under the existing owner mutex, before any additive DDL. */
function hosting_storage_prepare(string $module):void {
    $s=hosting_storage_state($module);
    if(!in_array($s['version'],['','1'],true))throw new RuntimeException('This hosting storage version is not supported. Existing data was preserved.');
    if($s['version']==='1'){
        if(!$s['complete'])throw new RuntimeException('Established hosting storage is missing. Restore the original database; setup cannot recreate lost history.');
        return;
    }
    // Setup audit survives a lost marker. Never reset an established family by clearing a setting.
    if(one('SELECT id FROM '.table('audit').' WHERE action=? LIMIT 1',[$s['setup_action']]))throw new RuntimeException('Hosting setup history exists without its marker. Restore the original database before continuing.');
    foreach($s['existing'] as $name)if(one('SELECT 1 AS present FROM '.table($name).' LIMIT 1'))throw new RuntimeException('Unrecognized populated hosting storage exists. Restore the original database; no data was adopted.');
}
