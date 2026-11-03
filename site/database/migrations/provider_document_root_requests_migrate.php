<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/provider_document_root_requests_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function provider_document_root_requests_validate_schema():void {
    $name='provider_website_folder_requests_events';catalog_validate_schema($name,provider_document_root_requests_schema(config()['db']['prefix']),['event_ordinal'=>['ordinal']]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){$ascii=in_array($c['Field'],['id','subject_id','kind','fingerprint'],true);if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==($ascii?'ascii_bin':($c['Field']==='document'?'utf8mb4_unicode_ci':null)))throw new RuntimeException('Website folder request history columns are incompatible.');}
    $expected=['PRIMARY'=>[0,['id']],'event_ordinal'=>[0,['ordinal']],'website_folder_request_subject'=>[1,['subject_id','ordinal']],'website_folder_request_client'=>[1,['client_id','ordinal']]];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Website folder request history indexes are incompatible.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Website folder request history indexes are incompatible.');
    $r=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($r)!==1||$r[0]['COLUMN_NAME']!=='client_id'||$r[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].'studio_clients'||$r[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Website folder request history relationships are incompatible.');
}
/** Explicit owner action only. Merely including this migration never creates SQL state. */
function provider_document_root_requests_migrate(#[SensitiveParameter] string $password):void {
    require_once CWP_CODE_ROOT.'/app/providers/document_root_requests.php';
    require_once CWP_CODE_ROOT.'/app/providers/document_root_requests_storage.php';provider_document_root_requests_prepare($password);
}
