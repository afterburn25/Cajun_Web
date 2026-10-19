<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once __DIR__.'/stackcp_launch.php';
require_once __DIR__.'/support_stackcp_documents.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_support_stackcp_schema.php';

function provider_support_stackcp_hmac(string $purpose,string $bytes):string {return provider_stackcp_proof(provider_stackcp_vault(),'support-stackcp-'.$purpose,$bytes);}
function provider_support_stackcp_exists():bool {return provider_stackcp_access_exists('provider_support_stackcp_events');}
function provider_support_stackcp_validate_schema():void {
    $name='provider_support_stackcp_events';$sql=provider_support_stackcp_schema(config()['db']['prefix'])[$name];
    catalog_validate_schema($name,$sql,['support_stackcp_ordinal'=>['ordinal']]);
    foreach (all('SHOW FULL COLUMNS FROM '.table($name)) as $c) if ($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==(preg_match('/^(?:char|varchar|longtext|text)/i',$c['Type'])?'ascii_bin':null)) throw new RuntimeException('Support StackCP columns need recovery.');
    $expected=['PRIMARY'=>[0,['id']],'support_stackcp_ordinal'=>[0,['ordinal']],'support_stackcp_permission'=>[1,['permission_id']],'support_stackcp_admission'=>[1,['admission_id']],'support_stackcp_resource'=>[1,['profile_id','package_id','staff_id']],'support_stackcp_client'=>[1,['client_id']],'support_stackcp_staff'=>[1,['actor_staff_id']],'support_stackcp_delegate'=>[1,['staff_id']]];$actual=[];
    foreach (all('SHOW INDEX FROM '.table($name)) as $i) {
        if ($i['Sub_part']!==null||$i['Index_type']!=='BTREE') throw new RuntimeException('Support StackCP indexes need recovery.');
        $actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];
    }
    foreach ($actual as &$index) {ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);ksort($actual);ksort($expected);
    if ($actual!==$expected) throw new RuntimeException('Support StackCP indexes need recovery.');
    $actual=[];
    foreach (all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]) as $fk) {
        if ($fk['REFERENCED_COLUMN_NAME']!=='id'||!in_array($fk['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($fk['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)||isset($actual[$fk['COLUMN_NAME']])) throw new RuntimeException('Support StackCP relationships need recovery.');
        $actual[$fk['COLUMN_NAME']]=$fk['REFERENCED_TABLE_NAME'];
    }
    $expected=['profile_id'=>config()['db']['prefix'].'provider_profiles','client_id'=>config()['db']['prefix'].'studio_clients','actor_staff_id'=>config()['db']['prefix'].'users','staff_id'=>config()['db']['prefix'].'users'];ksort($actual);ksort($expected);
    if ($actual!==$expected) throw new RuntimeException('Support StackCP relationships need recovery.');
}
function provider_support_stackcp_setting_names():array {return ['schema_version','prepared','prepared_proof','count','permission_count','admission_count','head','checkpoint_proof'];}
function provider_support_stackcp_detail(array $row):string {return 'Support StackCP event '.$row['id'].' '.$row['document_sha256'].' '.$row['proof'];}
function provider_support_stackcp_checkpoint(array $settings):array {
    $c=['version'=>1];foreach (['count','permission_count','admission_count'] as $key) {
        $v=$settings[$key]??'';if (!is_string($v)||preg_match('/\A(?:0|[1-9][0-9]{0,4})\z/',$v)!==1||(int)$v>10000) throw new RuntimeException('Support StackCP counts need recovery.');$c[$key]=(int)$v;
    }
    $c['head']=$settings['head']??'';provider_stackcp_launch_hex($c['head'],64);
    if ($c['permission_count']+$c['admission_count']>$c['count']||($c['count']===0&&$c['head']!==str_repeat('0',64))||!hash_equals(provider_support_stackcp_hmac('checkpoint',provider_support_stackcp_json($c)),$settings['checkpoint_proof']??'')) throw new RuntimeException('Support StackCP checkpoint needs recovery.');
    return $c;
}
function provider_support_stackcp_save_checkpoint(int $count,int $permissions,int $admissions,string $head):void {
    provider_stackcp_guard(true);$c=['version'=>1,'count'=>$count,'permission_count'=>$permissions,'admission_count'=>$admissions,'head'=>$head];
    foreach (array_slice($c,1,null,true) as $key=>$value) save_setting('provider_support_stackcp_'.$key,(string)$value);
    save_setting('provider_support_stackcp_checkpoint_proof',provider_support_stackcp_hmac('checkpoint',provider_support_stackcp_json($c)));
}
function provider_support_stackcp_row(array $row):array {
    try {
        if (!is_string($row['document_json'])||strlen($row['document_json'])>131072) throw new InvalidArgumentException();
        $raw=json_decode($row['document_json'],true,16,JSON_THROW_ON_ERROR);if (!is_array($raw)) throw new InvalidArgumentException();
        $d=provider_support_stackcp_document($raw);$json=provider_support_stackcp_json($d);
        if ($json!==$row['document_json']||!hash_equals(hash('sha256',$json),$row['document_sha256'])||!hash_equals(provider_support_stackcp_hmac('event',$json),$row['proof'])) throw new InvalidArgumentException();
        foreach (['id','permission_id','admission_id','profile_id','kind','phase'] as $key) if ($d[$key]!==$row[$key]) throw new InvalidArgumentException();
        foreach (['ordinal','package_id','client_id','staff_id'] as $key) if ((string)$d[$key]!== (string)$row[$key]) throw new InvalidArgumentException();
        if ((string)$row['actor_staff_id']!==(string)$d['actor']['id']) throw new InvalidArgumentException();
        return $d;
    } catch (JsonException|InvalidArgumentException|TypeError $e) {throw new RuntimeException('Support StackCP history needs recovery.',0,$e);}
}
/** Original signed reads remain mandatory after rotation/revocation, without current-authority demands. */
function provider_support_stackcp_sources(array $event):void {
    if ($event['kind']==='admitted') {
        if (!hash_equals(provider_support_stackcp_hmac('review',provider_support_stackcp_json($event['payload']['approval'])),$event['payload']['review_proof'])) throw new RuntimeException('Support StackCP form proof needs recovery.');return;
    }
    if ($event['kind']!=='permission') return;
    $r=$event['payload']['resource'];$b=$r['binding'];
    $member=one('SELECT * FROM '.table('provider_stackcp_reads').' WHERE id=?',[$b['membership_id']]);$grants=one('SELECT * FROM '.table('provider_stackcp_reads').' WHERE id=?',[$r['grants_id']]);
    if (!$member||!$grants) throw new RuntimeException('Support StackCP permission observations need recovery.');
    $m=provider_stackcp_access_saved($member);$g=provider_stackcp_access_saved($grants);$base=$b;$base['operation']=\CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding::MEMBERSHIP;$base['membership_id']='';$base['membership_seq']=0;$base['membership_sha256']='';
    if ($m['binding']->data!==$base||$g['binding']->data!==$b||!$m['finish']||!$g['finish']||$m['finish']['state']!=='complete'||$g['finish']['state']!=='complete'||!in_array($b['stack_identity'],$m['finish']['values'],true)||$g['finish']['values']!==['package:'.$b['package_id']]||$m['row']['document_sha256']!==$b['membership_sha256']||$g['row']['document_sha256']!==$r['grants_sha256']||(int)$m['start']['common']['seq']!==$b['membership_seq']||(int)$g['start']['common']['seq']!==$r['grants_seq']||$m['finish']['observed_epoch']!==$r['membership_observed_epoch']||$g['finish']['observed_epoch']!==$r['grants_observed_epoch']) throw new RuntimeException('Support StackCP permission provenance needs recovery.');
    if ($event['payload']['enabled']) foreach ([$m,$g] as $saved) if ($saved['start']['common']['started_epoch']<$event['recorded_epoch']-900||$saved['start']['common']['started_epoch']>$event['recorded_epoch']+30||$saved['finish']['observed_epoch']>$event['recorded_epoch']+30) throw new RuntimeException('Support StackCP access was not current at enablement.');
}
/** Null is clean absence; damaged/unknown/partly erased established state always refuses. */
function provider_support_stackcp_events():?array {
    provider_stackcp_guard();$prefix=config()['db']['prefix'];$own='provider_support_stackcp_';$settings=[];
    foreach (all('SELECT name,value FROM '.table('settings').' WHERE BINARY LEFT(name,?)=?',[strlen($own),$own]) as $r) {$key=substr($r['name'],strlen($own));if (!in_array($key,provider_support_stackcp_setting_names(),true)) throw new RuntimeException('Unknown support StackCP setting.');$settings[$key]=$r['value'];}
    $tables=all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=?',[strlen($prefix.$own),$prefix.$own]);
    foreach ($tables as $t) if ($t['TABLE_NAME']!==$prefix.'provider_support_stackcp_events') throw new RuntimeException('Unknown support StackCP table.');
    $actions=all('SELECT DISTINCT action FROM '.table('audit').' WHERE BINARY LEFT(action,?)=?',[strlen('provider.support_stackcp_'),'provider.support_stackcp_']);
    foreach ($actions as $r) if (!in_array($r['action'],['provider.support_stackcp_prepared','provider.support_stackcp_event'],true)) throw new RuntimeException('Unknown support StackCP audit.');
    if ($tables) provider_support_stackcp_validate_schema();
    if (($settings['schema_version']??'')==='') {
        if ($settings!==[]||$actions!==[]||($tables&&(int)query('SELECT COUNT(*) FROM '.table('provider_support_stackcp_events'))->fetchColumn()!==0)) throw new RuntimeException('Restore the original support StackCP history.');return null;
    }
    if ($settings['schema_version']!=='1'||count($settings)!==count(provider_support_stackcp_setting_names())||count($tables)!==1||!provider_stackcp_access_ready()) throw new RuntimeException('Support StackCP storage needs recovery.');
    foreach (['settings','audit','provider_profiles','users','studio_clients'] as $name) if (strtolower(one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$prefix.$name])['ENGINE']??'')!=='innodb') throw new RuntimeException('Support StackCP requires transactional prerequisites.');
    try {
        $raw=$settings['prepared'];$p=json_decode($raw,true,5,JSON_THROW_ON_ERROR);if (!is_array($p)) throw new InvalidArgumentException();provider_support_stackcp_keys($p,['version','actor','recorded_epoch']);$actor=provider_support_stackcp_principal($p['actor']);provider_stackcp_launch_number($p['recorded_epoch']);
        if ($p['version']!==1||$actor['kind']!=='owner'||provider_support_stackcp_json($p)!==$raw||!hash_equals(provider_support_stackcp_hmac('prepared',$raw),$settings['prepared_proof'])) throw new InvalidArgumentException();
    } catch (JsonException|InvalidArgumentException|TypeError $e) {throw new RuntimeException('Support StackCP preparation proof needs recovery.',0,$e);}
    $audit=table('audit');$detail='Support StackCP storage ready '.$settings['prepared_proof'];
    if ((int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='provider.support_stackcp_prepared'")->fetchColumn()!==1||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='provider.support_stackcp_prepared' AND BINARY detail=? AND user_id=? AND created_at=?",[$detail,$actor['id'],gmdate('Y-m-d H:i:s',$p['recorded_epoch'])])->fetchColumn()!==1) throw new RuntimeException('Support StackCP preparation audit needs recovery.');
    $c=provider_support_stackcp_checkpoint($settings);$table=table('provider_support_stackcp_events');
    if ((int)query("SELECT COUNT(*) FROM $table")->fetchColumn()!==$c['count']||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='provider.support_stackcp_event'")->fetchColumn()!==$c['count']) throw new RuntimeException('Support StackCP history counts disagree.');
    $events=[];$seen=0;$lastAudit=(int)query("SELECT id FROM $audit WHERE BINARY action='provider.support_stackcp_prepared'")->fetchColumn();$permissions=0;$admissions=0;
    while ($seen<$c['count']) {
        $detail="CONCAT(BINARY 'Support StackCP event ',r.id,' ',r.document_sha256,' ',r.proof)";
        $rows=all("SELECT r.*,(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action='provider.support_stackcp_event' AND BINARY a.detail=BINARY $detail) AS audit_count,(SELECT MIN(a.id) FROM $audit a WHERE BINARY a.action='provider.support_stackcp_event' AND BINARY a.detail=BINARY $detail) AS audit_id FROM $table r WHERE r.ordinal>? ORDER BY r.ordinal LIMIT 200",[$seen]);
        if (!$rows) throw new RuntimeException('Support StackCP history is incomplete.');
        foreach ($rows as $row) {
            $d=provider_support_stackcp_row($row);$a=one("SELECT user_id,created_at FROM $audit WHERE id=?",[$row['audit_id']]);$actorId=$d['actor']['id'];
            if ($d['ordinal']!==++$seen||$d['recorded_epoch']<$p['recorded_epoch']||(int)$row['audit_count']!==1||!$a||(int)$a['user_id']!==$actorId||$a['created_at']!==gmdate('Y-m-d H:i:s',$d['recorded_epoch'])||(int)$row['audit_id']<=$lastAudit) throw new RuntimeException('Support StackCP audit needs recovery.');
            $lastAudit=(int)$row['audit_id'];$events[]=$d;
            if ($d['kind']==='permission') ++$permissions;if ($d['kind']==='admitted') ++$admissions;provider_support_stackcp_sources($d);
        }
    }
    $last=$events?end($events):null;$head=$last?hash('sha256',provider_support_stackcp_json($last)):str_repeat('0',64);
    if ($head!==$c['head']||$permissions!==$c['permission_count']||$admissions!==$c['admission_count']) throw new RuntimeException('Support StackCP retained head needs recovery.');
    provider_support_stackcp_project($events);return $events;
}
function provider_support_stackcp_ready():bool {return provider_support_stackcp_events()!==null;}
/** Internal exact lookup after whole-family validation. */
function provider_support_stackcp_permission(string $id,?array $events=null):array {
    provider_stackcp_guard();provider_stackcp_launch_hex($id);$events??=provider_support_stackcp_events();
    foreach ($events??[] as $event) if ($event['kind']==='permission'&&$event['id']===$id) return $event;
    throw new RuntimeException('This support StackCP permission is unavailable.');
}
function provider_support_stackcp_resource(array $permission):array {return provider_support_stackcp_document($permission)['payload']['resource']['binding'];}
/** Caller owns the transaction. All callback phases inherit the original actual staff support principal. */
function provider_support_stackcp_append(string $kind,string $phase,array $payload,?array $seed=null):array {
    provider_stackcp_guard(true);$events=provider_support_stackcp_events();if ($events===null) throw new RuntimeException('Prepare support StackCP first.');
    if ($seed===null||!isset($seed['id'])) throw new InvalidArgumentException('Support StackCP event identity is required.');provider_stackcp_launch_hex($seed['id']);
    $states=provider_support_stackcp_project($events);$last=$events?end($events):null;
    if (count($events)>=10000||($kind==='admitted'&&count($events)>9988)) throw new RuntimeException('Support StackCP history capacity requires review.');
    if ($kind==='permission') {
        provider_support_stackcp_keys($seed,['id','actor']);$b=$payload['resource']['binding']??[];$staffId=$payload['resource']['staff']['id']??0;$permissionId=$seed['id'];$admissionId='';$actor=provider_support_stackcp_principal($seed['actor']);
    } elseif ($kind==='admitted') {
        provider_support_stackcp_keys($seed,['id','permission_id','actor']);$permission=provider_support_stackcp_permission($seed['permission_id'],$events);$b=provider_support_stackcp_resource($permission);$staffId=$permission['staff_id'];$permissionId=$permission['id'];$admissionId=$seed['id'];$actor=provider_support_stackcp_principal($seed['actor']);
    } else {
        provider_support_stackcp_keys($seed,['id']);$admission=$states['admissions'][$seed['id']]['admission']??null;if (!$admission) throw new RuntimeException('Support StackCP admission is missing.');
        $b=$admission;$staffId=$admission['staff_id'];$permissionId=$admission['permission_id'];$admissionId=$admission['id'];$actor=$admission['actor'];
    }
    $d=provider_support_stackcp_document(['version'=>1,'id'=>in_array($kind,['permission','admitted'],true)?$seed['id']:bin2hex(random_bytes(16)),'ordinal'=>count($events)+1,'previous_sha256'=>$last?hash('sha256',provider_support_stackcp_json($last)):str_repeat('0',64),'permission_id'=>$permissionId,'admission_id'=>$admissionId,'profile_id'=>$b['profile_id'],'package_id'=>$b['package_id'],'client_id'=>$b['client_id'],'staff_id'=>$staffId,'actor'=>$actor,'recorded_epoch'=>time(),'kind'=>$kind,'phase'=>$phase,'payload'=>$payload]);
    $floor=$last['recorded_epoch']??json_decode(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_support_stackcp_prepared'])['value'],true,5,JSON_THROW_ON_ERROR)['recorded_epoch'];if ($d['recorded_epoch']<$floor) throw new RuntimeException('Support StackCP clock moved backwards.');
    provider_support_stackcp_project([...$events,$d]);provider_support_stackcp_sources($d);$json=provider_support_stackcp_json($d);$row=[];
    foreach (['id','ordinal','permission_id','admission_id','profile_id','package_id','client_id','staff_id'] as $key) $row[$key]=$d[$key];
    $row['actor_staff_id']=$actor['id'];
    foreach (['kind','phase'] as $key) $row[$key]=$d[$key];$row+=['document_json'=>$json,'document_sha256'=>hash('sha256',$json),'proof'=>provider_support_stackcp_hmac('event',$json)];provider_support_stackcp_row($row);
    query('INSERT INTO '.table('provider_support_stackcp_events').' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    $permissionCount=0;foreach ($events as $event) if ($event['kind']==='permission') ++$permissionCount;
    provider_support_stackcp_save_checkpoint(count($events)+1,$permissionCount+($kind==='permission'?1:0),count($states['admissions'])+($kind==='admitted'?1:0),$row['document_sha256']);
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor['id'],'provider.support_stackcp_event',provider_support_stackcp_detail($row),gmdate('Y-m-d H:i:s',$d['recorded_epoch'])]);return $d;
}
