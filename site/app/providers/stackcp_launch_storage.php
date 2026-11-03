<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/stackcp_access.php';
require_once __DIR__.'/stackcp_launch_document.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_stackcp_launch_schema.php';

function provider_stackcp_launch_actor(?string $password=null):array {
    provider_stackcp_guard();
    // Capture forbidden session contexts before support/admin normalization can clear them.
    if (array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||support_current()!==null||array_key_exists('support',$_GET)) throw new RuntimeException('Leave client support before opening StackCP.');
    unset($GLOBALS['cwp_settings']);
    return $password===null?provider_settings_manager():provider_settings_owner($password);
}
function provider_stackcp_launch_hmac(string $purpose,string $bytes):string {
    return provider_stackcp_proof(provider_stackcp_vault(),'launch-'.$purpose,$bytes);
}
function provider_stackcp_launch_exists():bool {return provider_stackcp_access_exists('provider_stackcp_launch_events');}
function provider_stackcp_launch_validate_schema():void {
    $name='provider_stackcp_launch_events';$sql=provider_stackcp_launch_schema(config()['db']['prefix'])[$name];
    catalog_validate_schema($name,$sql,['launch_ordinal'=>['ordinal'],'launch_phase'=>['admission_id','kind','phase']]);
    foreach (all('SHOW FULL COLUMNS FROM '.table($name)) as $c) if ($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==(preg_match('/^(?:char|varchar|longtext|text)/i',$c['Type'])?'ascii_bin':null)) throw new RuntimeException('StackCP launch columns need recovery.');
    $expected=['PRIMARY'=>[0,['id']],'launch_ordinal'=>[0,['ordinal']],'launch_phase'=>[0,['admission_id','kind','phase']],'launch_package'=>[1,['profile_id','package_id']],'launch_actor'=>[1,['actor_id']]];$actual=[];
    foreach (all('SHOW INDEX FROM '.table($name)) as $i) {
        if ($i['Sub_part']!==null||$i['Index_type']!=='BTREE') throw new RuntimeException('StackCP launch indexes need recovery.');
        $actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];
    }
    foreach ($actual as &$index) {ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);ksort($actual);ksort($expected);
    if ($actual!==$expected) throw new RuntimeException('StackCP launch indexes need recovery.');
    $actual=[];
    foreach (all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]) as $fk) {
        if ($fk['REFERENCED_COLUMN_NAME']!=='id'||!in_array($fk['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($fk['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)||isset($actual[$fk['COLUMN_NAME']])) throw new RuntimeException('StackCP launch relationships need recovery.');
        $actual[$fk['COLUMN_NAME']]=$fk['REFERENCED_TABLE_NAME'];
    }
    $expected=['profile_id'=>config()['db']['prefix'].'provider_profiles','actor_id'=>config()['db']['prefix'].'users'];ksort($actual);ksort($expected);
    if ($actual!==$expected) throw new RuntimeException('StackCP launch relationships need recovery.');
}
function provider_stackcp_launch_detail(array $row):string {return 'StackCP launch event '.$row['id'].' '.$row['document_sha256'].' '.$row['proof'];}
function provider_stackcp_launch_setting_names():array {return ['schema_version','prepared','prepared_proof','count','admission_count','head','checkpoint_proof'];}
function provider_stackcp_launch_checkpoint(array $settings):array {
    $out=['version'=>1];
    foreach (['count','admission_count'] as $key) {
        $v=$settings[$key]??'';
        if (!is_string($v)||preg_match('/\A(?:0|[1-9][0-9]{0,4})\z/',$v)!==1||(int)$v>10000) throw new RuntimeException('StackCP launch counts need recovery.');
        $out[$key]=(int)$v;
    }
    $out['head']=$settings['head']??'';provider_stackcp_launch_hex($out['head'],64);
    if ($out['admission_count']>$out['count']||($out['count']===0&&$out['head']!==str_repeat('0',64))||!hash_equals(provider_stackcp_launch_hmac('checkpoint',provider_stackcp_launch_json($out)),$settings['checkpoint_proof']??'')) throw new RuntimeException('StackCP launch checkpoint needs recovery.');
    return $out;
}
function provider_stackcp_launch_save_checkpoint(int $count,int $admissions,string $head):void {
    provider_stackcp_guard(true);$c=['version'=>1,'count'=>$count,'admission_count'=>$admissions,'head'=>$head];
    save_setting('provider_stackcp_launch_count',(string)$count);save_setting('provider_stackcp_launch_admission_count',(string)$admissions);save_setting('provider_stackcp_launch_head',$head);save_setting('provider_stackcp_launch_checkpoint_proof',provider_stackcp_launch_hmac('checkpoint',provider_stackcp_launch_json($c)));
}
function provider_stackcp_launch_row(array $row):array {
    try {
        if (!is_string($row['document_json'])||strlen($row['document_json'])>16384) throw new InvalidArgumentException();
        $raw=json_decode($row['document_json'],true,12,JSON_THROW_ON_ERROR);
        if (!is_array($raw)) throw new InvalidArgumentException();$d=provider_stackcp_launch_document($raw);$json=provider_stackcp_launch_json($d);
        if ($json!==$row['document_json']||!hash_equals(hash('sha256',$json),$row['document_sha256'])||!hash_equals(provider_stackcp_launch_hmac('event',$json),$row['proof'])) throw new InvalidArgumentException();
        foreach (['id','admission_id','profile_id','kind','phase'] as $key) if ($d[$key]!==$row[$key]) throw new InvalidArgumentException();
        foreach (['ordinal','package_id','actor_id'] as $key) if ((string)$d[$key]!== (string)$row[$key]) throw new InvalidArgumentException();
        return $d;
    } catch (JsonException|InvalidArgumentException|TypeError $e) {throw new RuntimeException('StackCP launch history needs recovery.',0,$e);}
}
/** Historical provenance only: do not demand current credentials or fresh observations. */
function provider_stackcp_launch_sources(array $admission):void {
    $a=$admission['payload']['approval'];$b=$a['binding'];
    if (!hash_equals(provider_stackcp_launch_hmac('review',provider_stackcp_launch_json($a)),$admission['payload']['review_proof'])) throw new RuntimeException('StackCP launch review proof needs recovery.');
    $member=one('SELECT * FROM '.table('provider_stackcp_reads').' WHERE id=?',[$b['membership_id']]);$grants=one('SELECT * FROM '.table('provider_stackcp_reads').' WHERE id=?',[$a['grants_id']]);
    if (!$member||!$grants) throw new RuntimeException('StackCP launch observations need recovery.');
    $m=provider_stackcp_access_saved($member);$g=provider_stackcp_access_saved($grants);
    $base=$b;$base['operation']=\CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding::MEMBERSHIP;$base['membership_id']='';$base['membership_seq']=0;$base['membership_sha256']='';
    if ($m['binding']->data!==$base||$g['binding']->data!==$b||!$m['finish']||!$g['finish']||$m['finish']['state']!=='complete'||$g['finish']['state']!=='complete'||!in_array($b['stack_identity'],$m['finish']['values'],true)||$g['finish']['values']!==['package:'.$b['package_id']]||$m['row']['document_sha256']!==$b['membership_sha256']||$g['row']['document_sha256']!==$a['grants_sha256']||(int)$m['start']['common']['seq']!==$b['membership_seq']||(int)$g['start']['common']['seq']!==$a['grants_seq']||$m['finish']['observed_epoch']!==$a['membership_observed_epoch']||$g['finish']['observed_epoch']!==$a['grants_observed_epoch']) throw new RuntimeException('StackCP launch observation provenance needs recovery.');
    foreach ([$m,$g] as $saved) if ($saved['start']['common']['started_epoch']<$admission['recorded_epoch']-900||$saved['start']['common']['started_epoch']>$admission['recorded_epoch']+30||$saved['finish']['observed_epoch']>$admission['recorded_epoch']+30) throw new RuntimeException('StackCP launch observations were not current at approval.');
}
/** Complete closed family; null means genuinely unprepared, never damaged established history. */
function provider_stackcp_launch_events():?array {
    provider_stackcp_guard();$prefix=config()['db']['prefix'];$own='provider_stackcp_launch_';$settings=[];
    foreach (all('SELECT name,value FROM '.table('settings').' WHERE BINARY LEFT(name,?)=?',[strlen($own),$own]) as $r) {
        $key=substr($r['name'],strlen($own));if (!in_array($key,provider_stackcp_launch_setting_names(),true)) throw new RuntimeException('Unknown StackCP launch setting.');$settings[$key]=$r['value'];
    }
    $tables=all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=?',[strlen($prefix.$own),$prefix.$own]);
    foreach ($tables as $t) if ($t['TABLE_NAME']!==$prefix.'provider_stackcp_launch_events') throw new RuntimeException('Unknown StackCP launch table.');
    $actions=all('SELECT DISTINCT action FROM '.table('audit').' WHERE BINARY LEFT(action,?)=?',[strlen('provider.stackcp_launch_'),'provider.stackcp_launch_']);
    foreach ($actions as $r) if (!in_array($r['action'],['provider.stackcp_launch_prepared','provider.stackcp_launch_event'],true)) throw new RuntimeException('Unknown StackCP launch audit.');
    if ($tables) provider_stackcp_launch_validate_schema();
    if (($settings['schema_version']??'')==='') {
        if ($settings!==[]||$actions!==[]||($tables&&(int)query('SELECT COUNT(*) FROM '.table('provider_stackcp_launch_events'))->fetchColumn()!==0)) throw new RuntimeException('Restore the original StackCP launch history.');
        return null;
    }
    if ($settings['schema_version']!=='1'||count($settings)!==count(provider_stackcp_launch_setting_names())||count($tables)!==1||!provider_stackcp_access_ready()) throw new RuntimeException('StackCP launch storage needs recovery.');
    $raw=$settings['prepared'];
    try {
        $p=json_decode($raw,true,4,JSON_THROW_ON_ERROR);if (!is_array($p)||array_keys($p)!==['version','actor_id','actor_auth_version','recorded_epoch']||$p['version']!==1) throw new InvalidArgumentException();
        provider_stackcp_launch_number($p['actor_id']);provider_stackcp_launch_hex($p['actor_auth_version']);provider_stackcp_launch_number($p['recorded_epoch']);
        if (provider_stackcp_launch_json($p)!==$raw||!hash_equals(provider_stackcp_launch_hmac('prepared',$raw),$settings['prepared_proof'])) throw new InvalidArgumentException();
    } catch (JsonException|InvalidArgumentException|TypeError $e) {throw new RuntimeException('StackCP launch preparation proof needs recovery.',0,$e);}
    $audit=table('audit');$detail='StackCP launch storage ready '.$settings['prepared_proof'];
    if ((int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='provider.stackcp_launch_prepared'")->fetchColumn()!==1||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='provider.stackcp_launch_prepared' AND BINARY detail=? AND user_id=? AND created_at=?",[$detail,$p['actor_id'],gmdate('Y-m-d H:i:s',$p['recorded_epoch'])])->fetchColumn()!==1) throw new RuntimeException('StackCP launch preparation audit needs recovery.');
    $c=provider_stackcp_launch_checkpoint($settings);$table=table('provider_stackcp_launch_events');
    if ((int)query("SELECT COUNT(*) FROM $table")->fetchColumn()!==$c['count']||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='provider.stackcp_launch_event'")->fetchColumn()!==$c['count']) throw new RuntimeException('StackCP launch history counts disagree.');
    $events=[];$seen=0;$head=str_repeat('0',64);$lastEpoch=$p['recorded_epoch'];$lastAudit=(int)query("SELECT id FROM $audit WHERE BINARY action='provider.stackcp_launch_prepared'")->fetchColumn();$admissions=0;
    while ($seen<$c['count']) {
        $detail="CONCAT(BINARY 'StackCP launch event ',r.id,' ',r.document_sha256,' ',r.proof)";
        $rows=all("SELECT r.*,(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action='provider.stackcp_launch_event' AND BINARY a.detail=BINARY $detail) AS audit_count,(SELECT MIN(a.id) FROM $audit a WHERE BINARY a.action='provider.stackcp_launch_event' AND BINARY a.detail=BINARY $detail) AS audit_id FROM $table r WHERE r.ordinal>? ORDER BY r.ordinal LIMIT 200",[$seen]);
        if (!$rows) throw new RuntimeException('StackCP launch history is incomplete.');
        foreach ($rows as $row) {
            $d=provider_stackcp_launch_row($row);
            // Compare UTC in PHP: DB session timezones are not an authorization input.
            $a=one("SELECT user_id,created_at FROM $audit WHERE id=?",[$row['audit_id']]);
            if ($d['ordinal']!==++$seen||$d['previous_sha256']!==$head||$d['recorded_epoch']<$lastEpoch||(int)$row['audit_count']!==1||!$a||(int)$a['user_id']!==$d['actor_id']||$a['created_at']!==gmdate('Y-m-d H:i:s',$d['recorded_epoch'])||(int)$row['audit_id']<=$lastAudit) throw new RuntimeException('StackCP launch chain or audit needs recovery.');
            $head=$row['document_sha256'];$lastEpoch=$d['recorded_epoch'];$lastAudit=(int)$row['audit_id'];$events[]=$d;
            if ($d['kind']==='admitted') {++$admissions;provider_stackcp_launch_sources($d);}
        }
    }
    if ($head!==$c['head']||$admissions!==$c['admission_count']) throw new RuntimeException('StackCP launch retained head needs recovery.');
    provider_stackcp_launch_project($events);return $events;
}
function provider_stackcp_launch_ready():bool {return provider_stackcp_launch_events()!==null;}
function provider_stackcp_launch_prepare(#[SensitiveParameter] string $password):void {
    if (db()->inTransaction()) throw new LogicException('StackCP launch preparation must own its transaction.');
    staff_locked(function()use($password):void {
        $actor=provider_stackcp_launch_actor($password);if (provider_stackcp_launch_ready()) return;
        if (!provider_stackcp_access_ready()) throw new RuntimeException('Prepare StackCP access observations first.');
        if (!provider_stackcp_launch_exists()) foreach (provider_stackcp_launch_schema(config()['db']['prefix']) as $sql) db()->exec($sql);
        provider_stackcp_launch_validate_schema();
        staff_transaction(function()use($actor):void {
            if (provider_stackcp_launch_events()!==null) throw new RuntimeException('StackCP launch preparation changed.');
            $p=['version'=>1,'actor_id'=>(int)$actor['id'],'actor_auth_version'=>$actor['auth_version'],'recorded_epoch'=>time()];$raw=provider_stackcp_launch_json($p);$proof=provider_stackcp_launch_hmac('prepared',$raw);
            save_setting('provider_stackcp_launch_schema_version','1');save_setting('provider_stackcp_launch_prepared',$raw);save_setting('provider_stackcp_launch_prepared_proof',$proof);provider_stackcp_launch_save_checkpoint(0,0,str_repeat('0',64));
            query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$p['actor_id'],'provider.stackcp_launch_prepared','StackCP launch storage ready '.$proof,gmdate('Y-m-d H:i:s',$p['recorded_epoch'])]);
            if (!provider_stackcp_launch_ready()) throw new LogicException('StackCP launch preparation did not complete.');
        });
    });
}
function provider_stackcp_launch_append(string $admissionId,string $kind,string $phase,array $payload,?array $newApproval=null):array {
    provider_stackcp_guard(true);$events=provider_stackcp_launch_events();if ($events===null) throw new RuntimeException('Prepare StackCP launches first.');
    $states=provider_stackcp_launch_project($events);$last=$events?end($events):null;$existing=$states[$admissionId]['admission']??null;
    if ($kind==='admitted') {
        if ($existing||$newApproval===null||count($events)>9992) throw new RuntimeException('StackCP launch admission is unavailable.');
        $b=$newApproval['binding'];
    } else {
        if (!$existing||$newApproval!==null) throw new RuntimeException('StackCP launch admission is missing.');$b=$existing['payload']['approval']['binding'];
    }
    if (count($events)>=10000) throw new RuntimeException('StackCP launch history capacity requires review.');
    $d=provider_stackcp_launch_document(['version'=>1,'id'=>$kind==='admitted'?$admissionId:bin2hex(random_bytes(16)),'ordinal'=>count($events)+1,'previous_sha256'=>$last?hash('sha256',provider_stackcp_launch_json($last)):str_repeat('0',64),'admission_id'=>$admissionId,'profile_id'=>$b['profile_id'],'package_id'=>$b['package_id'],'actor_id'=>$b['actor_id'],'actor_auth_version'=>$b['actor_auth_version'],'recorded_epoch'=>time(),'kind'=>$kind,'phase'=>$phase,'payload'=>$payload]);
    $floor=$last['recorded_epoch']??json_decode(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_stackcp_launch_prepared'])['value'],true,4,JSON_THROW_ON_ERROR)['recorded_epoch'];
    if ($d['recorded_epoch']<$floor) throw new RuntimeException('StackCP launch clock moved backwards.');
    provider_stackcp_launch_project([...$events,$d]);if ($kind==='admitted') provider_stackcp_launch_sources($d);
    $json=provider_stackcp_launch_json($d);$row=[];foreach (['id','ordinal','admission_id','profile_id','package_id','actor_id','kind','phase'] as $key) $row[$key]=$d[$key];
    $row+=['document_json'=>$json,'document_sha256'=>hash('sha256',$json),'proof'=>provider_stackcp_launch_hmac('event',$json)];provider_stackcp_launch_row($row);
    query('INSERT INTO '.table('provider_stackcp_launch_events').' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    provider_stackcp_launch_save_checkpoint(count($events)+1,count($states)+($kind==='admitted'?1:0),$row['document_sha256']);
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$d['actor_id'],'provider.stackcp_launch_event',provider_stackcp_launch_detail($row),gmdate('Y-m-d H:i:s',$d['recorded_epoch'])]);
    return $d;
}
