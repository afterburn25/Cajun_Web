<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/domain_renewal_agreements_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function domain_renewal_agreements_tables():array {
    $out=[];foreach(domain_renewal_agreements_schema(config()['db']['prefix']) as $name=>$sql)$out[$name]=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;return $out;
}
function domain_renewal_agreements_audit(int $actor,string $command,string $detail):void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'domain_renewal_agreements.'.$command,$detail,gmdate('Y-m-d H:i:s')]);
}
function domain_renewal_agreements_has_history():bool {
    return one('SELECT name FROM '.table('settings').' WHERE name=?',['domain_renewal_agreements_count'])!==null||one('SELECT id FROM '.table('audit').' WHERE BINARY action IN (?,?) LIMIT 1',['domain_renewal_agreements.prepared','domain_renewal_agreements.bound'])!==null;
}
/** Independent heads and total count prevent paired row/audit loss from reopening an older offer. */
function domain_renewal_agreements_ready():bool {
    foreach(['settings','audit'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Renewal agreement witnesses must be transactional.');}
    $v=one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_renewal_agreements_schema_version'])['value']??'';$tables=domain_renewal_agreements_tables();
    if($v===''){
        if(domain_renewal_agreements_has_history())throw new RuntimeException('Renewal agreement history needs recovery.');
        foreach($tables as $name=>$exists)if($exists&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)throw new RuntimeException('Unmarked renewal agreement history needs recovery.');return false;
    }
    if($v!=='1'||in_array(false,$tables,true))throw new RuntimeException('Renewal agreement storage needs recovery.');
    foreach(array_keys($tables) as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Renewal agreement history must be transactional.');}
    $b=table('domain_renewal_agreements');$h=table('domain_renewal_agreement_heads');$a=table('audit');
    $count=(int)query("SELECT COUNT(*) FROM $b")->fetchColumn();$witness=one('SELECT value FROM '.table('settings').' WHERE name=?',['domain_renewal_agreements_count'])['value']??'';
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/',$witness)||(string)$count!==$witness||(int)query("SELECT COUNT(*) FROM $a WHERE BINARY action='domain_renewal_agreements.bound'")->fetchColumn()!==$count||(int)query("SELECT COUNT(*) FROM $a WHERE BINARY action='domain_renewal_agreements.prepared' AND BINARY detail='Renewal agreement storage ready'")->fetchColumn()!==1)throw new RuntimeException('Renewal agreement count history needs recovery.');
    if((int)query("SELECT COUNT(*) FROM $a WHERE BINARY action='domain_renewal_agreements.prepared'")->fetchColumn()!==1)throw new RuntimeException('Renewal agreement preparation audit needs recovery.');
    // A moved index must not make the original agreement appear unbound before its document is loaded.
    $identity=['id'=>['id','STRING'],'request_id'=>['request_id','STRING'],'agreement_id'=>['agreement_id','STRING'],'client_id'=>['registrar.client_id','INTEGER'],'actor_id'=>['actor_id','INTEGER'],'revision'=>['revision','INTEGER'],'created_epoch'=>['created_epoch','INTEGER']];
    $invalid=['BINARY SHA2(b.document_json,256)<>BINARY b.document_sha256'];
    foreach($identity as $column=>[$path,$type]){$value="JSON_EXTRACT(b.document_json,'$.".$path."')";$invalid[]="COALESCE(JSON_TYPE($value),'')<>'$type'";$invalid[]="NOT (BINARY JSON_UNQUOTE($value)<=>BINARY CAST(b.$column AS CHAR))";}
    if(one("SELECT b.id FROM $b b WHERE CASE WHEN JSON_VALID(b.document_json) THEN (".implode(' OR ',$invalid).") ELSE 1 END LIMIT 1"))throw new RuntimeException('Renewal agreement indexed identity needs recovery.');
    $detail="CONCAT(BINARY 'Binding ',b.id,BINARY ' request ',b.request_id,BINARY ' revision ',b.revision,BINARY ' sha256 ',b.document_sha256)";
    if(one("SELECT b.id FROM $b b WHERE (SELECT COUNT(*) FROM $a a WHERE a.user_id=b.actor_id AND BINARY a.action='domain_renewal_agreements.bound' AND BINARY a.detail=BINARY $detail)<>1 LIMIT 1"))throw new RuntimeException('Renewal agreement audit correspondence needs recovery.');
    if(one("SELECT b.id FROM $b b LEFT JOIN $h h ON h.id=b.request_id WHERE h.id IS NULL LIMIT 1")||one("SELECT h.id FROM $h h LEFT JOIN $b b ON b.id=h.current_id AND b.request_id=h.id AND b.revision=h.binding_count AND BINARY b.document_sha256=BINARY h.current_sha256 WHERE b.id IS NULL OR h.binding_count<1 OR h.binding_count>128 OR h.binding_count<>(SELECT COUNT(*) FROM $b x WHERE x.request_id=h.id) OR h.binding_count<>(SELECT MAX(revision) FROM $b x WHERE x.request_id=h.id) LIMIT 1"))throw new RuntimeException('Renewal agreement head history needs recovery.');
    if(one('SELECT b.id FROM '.$b.' b LEFT JOIN '.table('domain_renewal_requests').' r ON r.id=b.request_id LEFT JOIN '.table('service_subscriptions').' s ON s.id=b.agreement_id WHERE r.id IS NULL OR s.id IS NULL OR r.client_id<>b.client_id OR s.client_id<>b.client_id LIMIT 1'))throw new RuntimeException('Renewal agreement parent history needs recovery.');
    return true;
}
function domain_renewal_agreements_validate_schema(string $name,string $sql):void {
    $bindings=$name==='domain_renewal_agreements';catalog_validate_schema($name,$sql,$bindings?['agreement_identity'=>['agreement_id'],'request_revision'=>['request_id','revision']]:[]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){$ascii=in_array($c['Field'],['id','request_id','agreement_id','document_sha256','current_id','current_sha256'],true);$text=$c['Field']==='document_json';if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==($ascii?'ascii_bin':($text?'utf8mb4_unicode_ci':null)))throw new RuntimeException('Incompatible renewal agreement columns.');}
    $expected=$bindings?['PRIMARY'=>[0,['id']],'agreement_identity'=>[0,['agreement_id']],'request_revision'=>[0,['request_id','revision']],'client_offers'=>[1,['client_id','created_epoch']]]:['PRIMARY'=>[0,['id']]];
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible renewal agreement indexes.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible renewal agreement indexes.');
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    $wanted=[$bindings?'request_id':'id'=>'domain_renewal_requests'];if($bindings)$wanted['agreement_id']='service_subscriptions';if(count($relations)!==count($wanted))throw new RuntimeException('Incompatible renewal agreement relationships.');
    foreach($relations as $r){if(!isset($wanted[$r['COLUMN_NAME']])||$r['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$wanted[$r['COLUMN_NAME']]||$r['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible renewal agreement relationships.');unset($wanted[$r['COLUMN_NAME']]);}
}
function domain_renewal_agreements_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Renewal agreement preparation cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);service_plans_password($password);if(!domain_renewals_ready()||!provider_registrar_ready())throw new RuntimeException('Prepare renewal requests and registered domains first.');
        $ready=domain_renewal_agreements_ready();$tables=domain_renewal_agreements_tables();
        foreach(domain_renewal_agreements_schema(config()['db']['prefix']) as $name=>$sql)if($tables[$name])domain_renewal_agreements_validate_schema($name,$sql);
        foreach(domain_renewal_agreements_schema(config()['db']['prefix']) as $name=>$sql){if(!$tables[$name])db()->exec($sql);domain_renewal_agreements_validate_schema($name,$sql);}
        if(!$ready)staff_transaction(function()use($actor):void{save_setting('domain_renewal_agreements_schema_version','1');save_setting('domain_renewal_agreements_count','0');domain_renewal_agreements_audit((int)$actor['id'],'prepared','Renewal agreement storage ready');});
    });
}
