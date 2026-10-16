<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/schedule_document.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_schedule_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function provider_schedule_guard(bool $transaction=false):void {
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(($transaction&&!db()->inTransaction())||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()!==1)throw new LogicException('Scheduled refresh storage requires its owning account guard.');
}
function provider_schedule_schema_validate():void {
    $name='provider_schedule_events';catalog_validate_schema($name,provider_schedule_schema(config()['db']['prefix']),['event_sequence'=>['sequence'],'event_subject'=>['kind','subject_id']]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){
        $collation=match($c['Field']){'id','kind','subject_id','previous_sha256','document_sha256'=>'ascii_bin','document_json'=>'utf8mb4_bin',default=>null};
        if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==$collation)throw new RuntimeException('Scheduled refresh storage differs. Existing records were preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']],'event_sequence'=>[0,['sequence']],'event_subject'=>[0,['kind','subject_id']],'actor_id'=>[1,['actor_id']]];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Scheduled refresh indexes differ.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($expected);ksort($actual);if($actual!==$expected)throw new RuntimeException('Scheduled refresh indexes differ.');
    $r=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($r)!==1||$r[0]['COLUMN_NAME']!=='actor_id'||$r[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].'users'||$r[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Scheduled refresh relationships differ.');
}
function provider_schedule_event(array $row):array {
    $d=provider_schedule_document($row['document_json']);$sha=hash('sha256',$row['document_json']);
    if(!hash_equals($row['document_sha256'],$sha)||$d['id']!==$row['id']||$d['sequence']!==(int)$row['sequence']||$d['kind']!==$row['kind']||$d['subject']!==$row['subject_id']||$d['actor']!==(int)$row['actor_id']||$d['at']!==(int)$row['created_epoch']||$d['previous']!==$row['previous_sha256'])throw new RuntimeException('Original scheduled refresh event differs.');
    return $d+['sha256'=>$sha];
}
function provider_schedule_lookup(string $id):array {
    if(!provider_schedule_id($id)||!($row=one('SELECT * FROM '.table('provider_schedule_events').' WHERE id=?',[$id])))throw new RuntimeException('Original scheduled refresh event is missing.');
    return provider_schedule_event($row);
}
function provider_schedule_initial():array {return ['approval'=>null,'approval_sha256'=>null,'pending'=>null,'next'=>0,'failures'=>0,'paused'=>true];}
/** These inventory result columns are ASCII/scalars; PHP and SQL use the same ordered JSON-string projection. */
function provider_schedule_result_columns():array {return ['id','state','reason','scope','coverage','package_count','skipped','skip_reasons','finished_at','elapsed_ms','key_fingerprint'];}
function provider_schedule_result_sql_hash():string {
    $parts=[];foreach(provider_schedule_result_columns() as $index=>$column){$parts[]="'".($index===0?'{':',').'"'.$column.'":'."'";$parts[]='JSON_QUOTE(CAST(o.`'.$column.'` AS CHAR))';}$parts[]="'}'";
    return 'CAST(SHA2(CONCAT('.implode(',',$parts).'),256) AS BINARY)';
}
/** Fixed-query integrity checks retain independent settings/audit/inventory witnesses. No history is rewritten or adopted. */
function provider_schedule_state():array {
    provider_schedule_guard();
    $marker=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_schedule_version'])['value']??null;
    $head=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_schedule_head'])['value']??null;
    $exists=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_schedule_events']);
    $audits=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['provider.schedule_event'])->fetchColumn();
    $count=$exists?(int)query('SELECT COUNT(*) FROM '.table('provider_schedule_events'))->fetchColumn():0;
    if($marker===null&&$head===null&&$audits===0&&$count===0)return ['ready'=>false,'exists'=>$exists,'last'=>null,'state'=>provider_schedule_initial()];
    if($marker!=='1'||!is_string($head)||!$exists||$count<1||$count!==$audits)throw new RuntimeException('Original scheduled refresh history is incomplete. Restore the original database.');
    provider_schedule_schema_validate();
    // Check exact retained bytes in one database query, rather than fetching/replaying an ever-growing history.
    if(one('SELECT id FROM '.table('provider_schedule_events').' WHERE OCTET_LENGTH(document_json)>16384 OR JSON_VALID(document_json)=0 OR CAST(SHA2(document_json,256) AS BINARY)<>CAST(document_sha256 AS BINARY) LIMIT 1'))throw new RuntimeException('Original scheduled refresh journal bytes differ.');
    $last=provider_schedule_event(one('SELECT * FROM '.table('provider_schedule_events').' ORDER BY sequence DESC LIMIT 1'));
    if($last['sequence']!==$count||$head!==$count.':'.$last['sha256']||(int)query('SELECT MIN(sequence) FROM '.table('provider_schedule_events'))->fetchColumn()!==1)throw new RuntimeException('Original scheduled refresh head is missing.');
    if(one('SELECT e.id FROM '.table('provider_schedule_events').' e LEFT JOIN '.table('provider_schedule_events').' p ON p.sequence=e.sequence-1 WHERE e.sequence>1 AND (p.id IS NULL OR e.previous_sha256<>p.document_sha256 OR e.created_epoch<p.created_epoch) LIMIT 1'))throw new RuntimeException('Original scheduled refresh chain is incomplete.');
    if((int)query('SELECT COUNT(*) FROM '.table('provider_schedule_events')." WHERE kind='setup'")->fetchColumn()!==1)throw new RuntimeException('Original scheduled refresh setup differs.');
    $bad=one('SELECT e.id FROM '.table('provider_schedule_events').' e WHERE (SELECT COUNT(*) FROM '.table('audit')." a WHERE a.action='provider.schedule_event' AND a.user_id=e.actor_id AND CAST(a.detail AS BINARY)=CAST(CONCAT('Schedule event ',e.id,' ',e.kind,' sha256 ',e.document_sha256) AS BINARY))<>1 LIMIT 1");
    if($bad)throw new RuntimeException('Original scheduled refresh audit is missing.');
    $s=$last['state'];if($last['kind']==='approval')$s['approval_sha256']=$last['sha256'];
    if($s['approval']!==null){$approval=provider_schedule_lookup($s['approval']);if($approval['kind']!=='approval'||$approval['sha256']!==$s['approval_sha256'])throw new RuntimeException('Original scheduled read approval differs.');}
    $pending=all('SELECT s.* FROM '.table('provider_schedule_events')." s LEFT JOIN ".table('provider_schedule_events')." f ON f.kind='finish' AND f.subject_id=s.subject_id WHERE s.kind='start' AND f.id IS NULL LIMIT 2");
    if(count($pending)!==($s['pending']===null?0:1)||($pending&&$pending[0]['subject_id']!==$s['pending']))throw new RuntimeException('Original scheduled attempt history is incomplete.');
    if(one('SELECT f.id FROM '.table('provider_schedule_events')." f LEFT JOIN ".table('provider_schedule_events')." s ON s.kind='start' AND s.subject_id=f.subject_id WHERE f.kind='finish' AND (s.id IS NULL OR f.sequence<=s.sequence) LIMIT 1"))throw new RuntimeException('Original scheduled attempt start is missing.');
    // Historical references must retain their exact original approval/start, even after later reapproval.
    $j=static fn(string $alias,string $field):string=>"CAST(JSON_UNQUOTE(JSON_EXTRACT(".$alias.".document_json,'$.".$field."')) AS BINARY)";
    $headers=['id'=>'id','sequence'=>'sequence','kind'=>'kind','subject_id'=>'subject','actor_id'=>'actor','created_epoch'=>'at','previous_sha256'=>'previous'];$different=[];
    foreach($headers as $column=>$field)$different[]='NOT (CAST(e.`'.$column.'` AS CHAR) <=> '.$j('e',$field).')';
    if(one('SELECT e.id FROM '.table('provider_schedule_events').' e WHERE '.implode(' OR ',$different).' LIMIT 1'))throw new RuntimeException('Original scheduled event identity differs.');
    if(one('SELECT s.id FROM '.table('provider_schedule_events')." s LEFT JOIN ".table('provider_schedule_events')." a ON a.id=".$j('s','data.approval_id')." AND a.kind='approval' WHERE s.kind='start' AND (a.id IS NULL OR a.sequence>=s.sequence OR NOT (".$j('s','data.approval_sha256')." <=> a.document_sha256) OR NOT (".$j('s','data.profile_id').' <=> '.$j('a','data.profile_id').') OR NOT ('.$j('s','data.profile_revision').' <=> '.$j('a','data.profile_revision').') OR s.actor_id<>a.actor_id) LIMIT 1'))throw new RuntimeException('Original scheduled read approval binding differs.');
    if(one('SELECT f.id FROM '.table('provider_schedule_events')." f JOIN ".table('provider_schedule_events')." s ON s.kind='start' AND s.subject_id=f.subject_id WHERE f.kind='finish' AND (NOT (".$j('f','data.start_id').' <=> s.id) OR NOT ('.$j('f','data.start_sha256').' <=> s.document_sha256)) LIMIT 1'))throw new RuntimeException('Original scheduled result start binding differs.');
    if(provider_inventory_ready()){
        if(one('SELECT s.id FROM '.table('provider_schedule_events')." s LEFT JOIN ".table('provider_refreshes')." r ON r.id=s.subject_id LEFT JOIN ".table('provider_schedule_events')." f ON f.kind='finish' AND f.subject_id=s.subject_id LEFT JOIN ".table('provider_refresh_results')." o ON o.id=s.subject_id WHERE s.kind='start' AND (r.id IS NULL OR r.operation<>'packages.list' OR r.actor_id<>s.actor_id OR r.started_epoch<>s.created_epoch OR (f.id IS NULL AND o.id IS NOT NULL) OR (f.id IS NOT NULL AND o.id IS NULL)) LIMIT 1"))throw new RuntimeException('Original scheduled inventory evidence is incomplete.');
        if(one('SELECT s.id FROM '.table('provider_schedule_events')." s JOIN ".table('provider_refreshes')." r ON r.id=s.subject_id WHERE s.kind='start' AND (NOT (r.profile_id <=> ".$j('s','data.profile_id').') OR NOT (CAST(r.profile_revision AS CHAR) <=> '.$j('s','data.profile_revision').') OR r.started_at<>TIMESTAMPADD(SECOND,r.started_epoch,\'1970-01-01 00:00:00\')) LIMIT 1'))throw new RuntimeException('Original scheduled inventory intent differs.');
        if(one('SELECT f.id FROM '.table('provider_schedule_events')." f JOIN ".table('provider_refresh_results')." o ON o.id=f.subject_id WHERE f.kind='finish' AND (NOT (".$j('f','data.result_sha256').' <=> '.provider_schedule_result_sql_hash().') OR NOT ('.$j('f','data.outcome').' <=> o.state) OR NOT ('.$j('f','data.reason').' <=> o.reason)) LIMIT 1'))throw new RuntimeException('Original scheduled inventory result differs.');
    }elseif($s['approval']!==null)throw new RuntimeException('Restore the original inventory storage before scheduled reads.');
    return ['ready'=>true,'exists'=>true,'last'=>$last,'state'=>$s];
}
/** Append within the caller's transaction. A failed result or audit rolls back the complete outcome. */
function provider_schedule_append(array $context,string $kind,string $subject,int $actor,int $at,array $data,array $state,?string $id=null):array {
    provider_schedule_guard(true);$last=$context['last'];$id??=bin2hex(random_bytes(16));
    $d=['format'=>1,'sequence'=>($last['sequence']??0)+1,'id'=>$id,'kind'=>$kind,'subject'=>$subject,'actor'=>$actor,'at'=>max($at,$last['at']??0),'previous'=>$last['sha256']??str_repeat('0',64),'data'=>$data,'state'=>$state];
    $bytes=provider_schedule_json($d);provider_schedule_document($bytes);$sha=hash('sha256',$bytes);
    query('INSERT INTO '.table('provider_schedule_events').' (id,sequence,kind,subject_id,actor_id,created_epoch,previous_sha256,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$id,$d['sequence'],$kind,$subject,$actor,$d['at'],$d['previous'],$bytes,$sha]);
    provider_settings_audit($actor,'schedule_event','Schedule event '.$id.' '.$kind.' sha256 '.$sha);
    save_setting('provider_schedule_head',$d['sequence'].':'.$sha);return $d+['sha256'=>$sha];
}
function provider_schedule_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Scheduled refresh preparation cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);if(!provider_inventory_ready())throw new RuntimeException('Prepare hosting inventory first.');
        $ctx=provider_schedule_state();if(!$ctx['exists'])db()->exec(provider_schedule_schema(config()['db']['prefix']));provider_schedule_schema_validate();
        if(!$ctx['ready'])staff_transaction(function()use($ctx,$actor):void{save_setting('provider_schedule_version','1');provider_schedule_append($ctx,'setup',str_repeat('0',32),(int)$actor['id'],time(),[],provider_schedule_initial());});
        provider_schedule_state();
    });
}
