<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/domain_renewals_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function domain_renewals_tables():array {
    $out=[];foreach(domain_renewals_schema(config()['db']['prefix']) as $name=>$sql)$out[$name]=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;return $out;
}
function domain_renewals_has_history():bool {return one('SELECT name FROM '.table('settings').' WHERE name=?',['domain_renewals_request_count'])!==null||one('SELECT id FROM '.table('audit').' WHERE BINARY action IN (?,?,?,?,?) LIMIT 1',['domain_renewals.prepared','domain_renewals.created','domain_renewals.reviewed','domain_renewals.declined','domain_renewals.withdrawn'])!==null;}
function domain_renewals_ready():bool {
    foreach(['audit','settings'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Renewal request audit storage must be transactional.');}
    $v=one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_renewals_schema_version'])['value']??'';$tables=domain_renewals_tables();
    if($v===''){
        if(domain_renewals_has_history())throw new RuntimeException('Renewal request history needs recovery from the original database.');
        foreach($tables as $name=>$exists)if($exists&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)throw new RuntimeException('Unmarked renewal request history needs recovery.');return false;
    }
    if($v!=='1'||in_array(false,$tables,true))throw new RuntimeException('Renewal request storage needs recovery.');
    foreach(array_keys($tables) as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Renewal request history must be transactional.');}
    if(one('SELECT e.id FROM '.table('domain_renewal_events').' e LEFT JOIN '.table('domain_renewal_requests').' r ON r.id=e.request_id WHERE r.id IS NULL LIMIT 1'))throw new RuntimeException('Orphan renewal review history needs recovery.');
    $witness=one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_renewals_request_count'])['value']??'';
    $requestCount=(int)query('SELECT COUNT(*) FROM '.table('domain_renewal_requests'))->fetchColumn();
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/',$witness)||(string)$requestCount!==$witness)throw new RuntimeException('Renewal request count history needs recovery.');
    $created=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['domain_renewals.created'])->fetchColumn();
    $events=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action IN (?,?,?)',['domain_renewals.reviewed','domain_renewals.declined','domain_renewals.withdrawn'])->fetchColumn();
    if($created!==(int)query('SELECT COUNT(*) FROM '.table('domain_renewal_requests'))->fetchColumn()||$events!==(int)query('SELECT COUNT(*) FROM '.table('domain_renewal_events'))->fetchColumn()||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['domain_renewals.prepared'])->fetchColumn()!==1)throw new RuntimeException('Renewal request history is incomplete. Restore the original database.');return true;
}
function domain_renewals_validate_schema(string $name,string $sql):void {
    $unique=$name==='domain_renewal_events'?['request_revision'=>['request_id','revision']]:[];catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){$ascii=in_array($c['Field'],['id','link_id','request_id','document_sha256','current_sha256','state'],true);$text=$c['Field']==='document_json';if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==($ascii?'ascii_bin':($text?'utf8mb4_unicode_ci':null)))throw new RuntimeException('Incompatible renewal request columns.');}
    $expected=['PRIMARY'=>[0,['id']]];
    if($name==='domain_renewal_events')$expected['request_revision']=[0,['request_id','revision']];else{$expected['client_requests']=[1,['client_id','created_epoch']];$expected['link_requests']=[1,['link_id','state']];}
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible renewal request indexes.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible renewal request indexes.');
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    $column=$name==='domain_renewal_events'?'request_id':'client_id';$target=$name==='domain_renewal_events'?'domain_renewal_requests':'studio_clients';
    if(count($relations)!==1||$relations[0]['COLUMN_NAME']!==$column||$relations[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$relations[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($relations[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($relations[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible renewal request relationships.');
}
function domain_renewals_audit(int $staff,string $command,string $detail):void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$staff,'domain_renewals.'.$command,$detail,gmdate('Y-m-d H:i:s')]);}
function domain_renewals_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Renewal request preparation cannot run inside a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);if(!provider_registrar_ready()||!studio_ready()||!staff_ready())throw new RuntimeException('Prepare registered domains and client accounts first.');
        $v=one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_renewals_schema_version'])['value']??'';
        if(!in_array($v,['','1'],true))throw new RuntimeException('Unsupported renewal request storage.');domain_renewals_ready();$tables=domain_renewals_tables();
        foreach(domain_renewals_schema(config()['db']['prefix']) as $name=>$sql)if($tables[$name])domain_renewals_validate_schema($name,$sql);
        foreach(domain_renewals_schema(config()['db']['prefix']) as $name=>$sql){if(!$tables[$name])db()->exec($sql);domain_renewals_validate_schema($name,$sql);}
        if($v==='')staff_transaction(function()use($actor):void{save_setting('domain_renewals_schema_version','1');save_setting('domain_renewals_request_count','0');domain_renewals_audit((int)$actor['id'],'prepared','Renewal request storage ready');});
    });
}
