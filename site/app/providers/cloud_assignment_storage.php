<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/provider_cloud_assignment_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function provider_cloud_assignment_guard():void {
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(!db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()!==1)throw new LogicException('Cloud assignment evidence requires the owning account transaction and mutex.');
}
function provider_cloud_assignment_json(array $value):string{return json_encode($value,JSON_THROW_ON_ERROR);}
function provider_cloud_assignment_hmac(string $kind,array $value):string{return hash_hmac('sha256','cloud.assignment|'.$kind.'|'.provider_cloud_assignment_json($value),provider_settings_vault_key());}
function provider_cloud_assignment_error():never{throw new RuntimeException('Cloud assignment history needs recovery from the original database.');}
function provider_cloud_assignment_exists():bool{return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_cloud_assignment_evidence'])!==null;}
function provider_cloud_assignment_uint(mixed $v,bool $zero=false):int {
    if((!is_string($v)&&!is_int($v))||!preg_match('/\A(?:0|[1-9][0-9]{0,17})\z/D',(string)$v)||(int)$v<($zero?0:1))provider_cloud_assignment_error();return (int)$v;
}
function provider_cloud_assignment_fields(array $row,array $keys):array {
    $out=[];foreach($keys as $key){if(!array_key_exists($key,$row)||$row[$key]===null||!is_scalar($row[$key]))provider_cloud_assignment_error();$out[$key]=(string)$row[$key];}return $out;
}
function provider_cloud_assignment_start_fields(array $r):array{return provider_cloud_assignment_fields($r,['id','seq','profile_id','profile_revision','actor_id','started_at','started_epoch','operation']);}
function provider_cloud_assignment_result_fields(array $r):array{return provider_cloud_assignment_fields($r,['id','state','reason','scope','coverage','package_count','skipped','skip_reasons','finished_at','elapsed_ms','key_fingerprint']);}
function provider_cloud_assignment_event_fields(array $r):array{return provider_cloud_assignment_fields($r,['id','link_id','profile_id','server_id','client_id','revision','event','actor_id','recorded_at','key_fingerprint','profile_revision','refresh_id']);}
function provider_cloud_assignment_link_fields(array $r):array{return provider_cloud_assignment_fields($r,['id','profile_id','server_id','client_id','revision','linked_by','linked_at','key_fingerprint','profile_revision','refresh_id']);}
function provider_cloud_assignment_preparation_detail(array $head):string {
    return 'Cloud assignment preparation '.$head['preparation_id'].' '.hash('sha256',provider_cloud_assignment_json(array_intersect_key($head,array_flip(['preparation_id','prepared_by','prepared_epoch','baseline_seq','baseline_event_id']))));
}
function provider_cloud_assignment_head():?array {
    $value=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_assignment_head'])['value']??null;
    if($value===null)return null;
    try{$row=json_decode($value,true,8,JSON_THROW_ON_ERROR);}catch(JsonException){provider_cloud_assignment_error();}
    if(!is_array($row)||array_keys($row)!==['head','proof']||!is_array($row['head']))provider_cloud_assignment_error();$h=$row['head'];
    if(array_keys($h)!==['version','preparation_id','prepared_by','prepared_epoch','baseline_seq','baseline_event_id','count','tail']||$h['version']!==1||!is_string($h['preparation_id'])||!preg_match('/\A[a-f0-9]{32}\z/D',$h['preparation_id'])||!is_string($h['tail'])||!preg_match('/\A[a-f0-9]{64}\z/D',$h['tail']))provider_cloud_assignment_error();
    foreach(['prepared_by','prepared_epoch','baseline_seq','baseline_event_id','count'] as $key)if(!is_int($h[$key])||$h[$key]<($key==='prepared_by'?1:0)||$h[$key]>999999999999999999)provider_cloud_assignment_error();
    if(provider_cloud_assignment_json($row)!==$value||!is_string($row['proof'])||!hash_equals(provider_cloud_assignment_hmac('head',$h),$row['proof']))provider_cloud_assignment_error();return $h;
}
function provider_cloud_assignment_save_head(array $head):void {provider_cloud_assignment_guard();save_setting('provider_cloud_assignment_head',provider_cloud_assignment_json(['head'=>$head,'proof'=>provider_cloud_assignment_hmac('head',$head)]));}
function provider_cloud_assignment_validate_schema():void {
    $name='provider_cloud_assignment_evidence';catalog_validate_schema($name,provider_cloud_assignment_schema(config()['db']['prefix'])[$name],['evidence_source'=>['kind','source_id']]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==(in_array($c['Field'],['id','actor_id'],true)?null:'ascii_bin'))provider_cloud_assignment_error();
    $indexes=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')provider_cloud_assignment_error();$indexes[$i['Key_name']][0]=(int)$i['Non_unique'];$indexes[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}foreach($indexes as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($indexes);
    if($indexes!==['PRIMARY'=>[0,['id']],'actor_history'=>[1,['actor_id']],'evidence_source'=>[0,['kind','source_id']],'profile_history'=>[1,['profile_id','id']]])provider_cloud_assignment_error();
    $foreign=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.COLUMN_NAME',[config()['db']['prefix'].$name]);
    if(count($foreign)!==2)provider_cloud_assignment_error();foreach($foreign as $i=>$f){[$column,$target]=$i===0?['actor_id','users']:['profile_id','provider_profiles'];if($f['COLUMN_NAME']!==$column||$f['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$f['REFERENCED_COLUMN_NAME']!=='id'||!in_array($f['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($f['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))provider_cloud_assignment_error();}
}
/** Fixed SQL correspondence prevents surviving common audits from being ignored. */
function provider_cloud_assignment_common_history():void {
    $f=table('provider_refreshes');$r=table('provider_refresh_results');$a=table('audit');$e=table('provider_cloud_link_events');
    $count=static fn(string $sql):int=>(int)query($sql)->fetchColumn();
    if($count("SELECT COUNT(*) FROM $f")!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.refresh_started'")||$count("SELECT COUNT(*) FROM $r")!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='provider.refresh_finished'"))provider_cloud_assignment_error();
    $start="CONCAT(BINARY 'Refresh ',f.id,' profile ',f.profile_id,' revision ',f.profile_revision)";$finish="CONCAT(BINARY 'Refresh ',f.id,' ',r.state,IF(r.reason='','',' '),r.reason)";
    foreach(['provider.refresh_started'=>[$start,"$f f"],'provider.refresh_finished'=>[$finish,"$f f JOIN $r r ON r.id=f.id"]] as $action=>[$detail,$from])if(one("SELECT f.id FROM $from WHERE (SELECT COUNT(*) FROM $a a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail AND a.user_id=f.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail)<>1 LIMIT 1",[$action,$action]))provider_cloud_assignment_error();
    if(one("SELECT r.id FROM $r r LEFT JOIN $f f ON f.id=r.id WHERE f.id IS NULL LIMIT 1"))provider_cloud_assignment_error();
    $detail="CONCAT(BINARY 'Cloud link ',e.link_id,' profile ',e.profile_id,' server ',e.server_id,' client ',e.client_id,' revision ',e.revision)";
    foreach(['link','relink','unlink'] as $kind){$action='provider.cloud_'.$kind;if((int)query("SELECT COUNT(*) FROM $e WHERE BINARY event=?",[$kind])->fetchColumn()!==(int)query("SELECT COUNT(*) FROM $a WHERE BINARY action=?",[$action])->fetchColumn()||one("SELECT e.id FROM $e e WHERE BINARY e.event=? AND ((SELECT COUNT(*) FROM $a a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail AND a.user_id=e.actor_id)<>1 OR (SELECT COUNT(*) FROM $a a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail)<>1) LIMIT 1",[$kind,$action,$action]))provider_cloud_assignment_error();}
    if(one("SELECT id FROM $e WHERE BINARY event NOT IN ('link','relink','unlink') LIMIT 1"))provider_cloud_assignment_error();
}
/** Bounded-memory fingerprint of the original saved metadata family. */
function provider_cloud_assignment_cloud_digest(string $refreshId):array {
    $hash=hash_init('sha256');$cursor=null;$count=0;
    do{$rows=all('SELECT * FROM '.table('provider_observation_cloud').' WHERE refresh_id=?'.($cursor===null?'':' AND BINARY id>BINARY ?').' ORDER BY BINARY id LIMIT 64',$cursor===null?[$refreshId]:[$refreshId,$cursor]);foreach($rows as $row){if(++$count>8192||!preg_match('/\A[a-f0-9]{32}\z/D',$row['id'])||strlen($row['metadata'])>1048576)provider_cloud_assignment_error();$values=provider_cloud_assignment_fields($row,['id','refresh_id','server_id','metadata','unmapped_count']);$json=provider_cloud_assignment_json($values);hash_update($hash,strlen($json).':'.$json);$cursor=$row['id'];}}while(count($rows)===64);
    if($count!==(int)query('SELECT COUNT(*) FROM '.table('provider_observation_cloud').' WHERE refresh_id=?',[$refreshId])->fetchColumn())provider_cloud_assignment_error();return ['count'=>$count,'sha256'=>hash_final($hash)];
}
/** Unconditional first page also validates an empty/trimmed physical source ID. */
function provider_cloud_assignment_evidence(string $kind,string $sourceId):?array {return one('SELECT * FROM '.table('provider_cloud_assignment_evidence').' WHERE BINARY kind=? AND BINARY source_id=?',[$kind,$sourceId]);}
function provider_cloud_assignment_decode(array $row):array {
    if(strlen($row['document_json'])>16384)provider_cloud_assignment_error();try{$doc=json_decode($row['document_json'],true,16,JSON_THROW_ON_ERROR);}catch(JsonException){provider_cloud_assignment_error();}
    if(!is_array($doc)||array_keys($doc)!==['version','id','kind','source_id','profile_id','actor_id','legacy','source','extra']||$doc['version']!==1||!is_bool($doc['legacy'])||!is_array($doc['source'])||!is_array($doc['extra']))provider_cloud_assignment_error();
    if(!is_int($doc['id'])||$doc['id']!==provider_cloud_assignment_uint($row['id'])||!is_int($doc['actor_id'])||$doc['actor_id']!==provider_cloud_assignment_uint($row['actor_id']))provider_cloud_assignment_error();
    foreach(['kind','source_id','profile_id'] as $key)if(!is_string($doc[$key])||$doc[$key]!==$row[$key])provider_cloud_assignment_error();
    if(!in_array($doc['kind'],['start','finish','event'],true)||!preg_match('/\A[a-f0-9]{32}\z/D',$doc['profile_id'])||!preg_match($doc['kind']==='event'?'/\A[1-9][0-9]{0,17}\z/D':'/\A[a-f0-9]{32}\z/D',$doc['source_id']))provider_cloud_assignment_error();
    if(provider_cloud_assignment_json($doc)!==$row['document_json']||!hash_equals(hash('sha256',$row['document_json']),$row['document_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/D',$row['previous_sha256'])||!hash_equals(provider_cloud_assignment_hmac('row',[$row['document_sha256'],$row['previous_sha256']]),$row['proof']))provider_cloud_assignment_error();return $doc;
}
function provider_cloud_assignment_append(string $kind,string $sourceId,string $profileId,int $actor,bool $legacy,array $source,array $extra):array {
    provider_cloud_assignment_guard();$head=provider_cloud_assignment_head();if(!$head||$head['count']>=999999999999999998)provider_cloud_assignment_error();
    $doc=['version'=>1,'id'=>$head['count']+1,'kind'=>$kind,'source_id'=>$sourceId,'profile_id'=>$profileId,'actor_id'=>$actor,'legacy'=>$legacy,'source'=>$source,'extra'=>$extra];$json=provider_cloud_assignment_json($doc);$sha=hash('sha256',$json);$row=['id'=>$doc['id'],'kind'=>$kind,'source_id'=>$sourceId,'profile_id'=>$profileId,'actor_id'=>$actor,'document_json'=>$json,'document_sha256'=>$sha,'previous_sha256'=>$head['tail'],'proof'=>provider_cloud_assignment_hmac('row',[$sha,$head['tail']])];
    provider_cloud_assignment_decode($row);query('INSERT INTO '.table('provider_cloud_assignment_evidence').' (id,kind,source_id,profile_id,actor_id,document_json,document_sha256,previous_sha256,proof) VALUES (?,?,?,?,?,?,?,?,?)',array_values($row));provider_settings_audit($actor,'cloud_assignment_evidence','Cloud assignment evidence '.$doc['id'].' '.$sha.' '.$row['proof']);$head['count']=$doc['id'];$head['tail']=$sha;provider_cloud_assignment_save_head($head);return $row;
}
