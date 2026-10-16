<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/stackcp_assignments.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_stackcp_access_schema.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_access_documents.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_access_sources.php';
use CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding;

function provider_stackcp_access_table(string $kind):string {return match($kind){'started'=>'provider_stackcp_reads','finished'=>'provider_stackcp_observations',default=>throw new LogicException('Unknown StackCP observation family.')};}
function provider_stackcp_access_exists(string $name):bool {return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;}
function provider_stackcp_access_validate_schema():void {
    foreach (provider_stackcp_access_schema(config()['db']['prefix']) as $name=>$sql) {
        catalog_validate_schema($name,$sql,['ordinal'=>['ordinal']]);
        foreach (all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {if ($column['Extra']!==''||$column['Default']!==null||$column['Collation']!==(preg_match('/^(?:char|varchar|longtext|text)/i',$column['Type'])?'ascii_bin':null)) throw new RuntimeException('Incompatible StackCP observation columns.');}
        $expected=['PRIMARY'=>[0,['id']],'ordinal'=>[0,['ordinal']]];
        if ($name==='provider_stackcp_reads') $expected+=['stackcp_package'=>[1,['profile_id','package_id']],'stackcp_user'=>[1,['profile_id','stack_identity']],'assignment_id'=>[1,['assignment_id']]];
        $actual=[];foreach (all('SHOW INDEX FROM '.table($name)) as $index) {if ($index['Sub_part']!==null||$index['Index_type']!=='BTREE') throw new RuntimeException('Incompatible StackCP observation indexes.');$actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];}
        foreach ($actual as &$index) {ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);ksort($actual);ksort($expected);if ($actual!==$expected) throw new RuntimeException('Incompatible StackCP observation indexes.');
        $actual=[];foreach (all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]) as $fk) {if ($fk['REFERENCED_COLUMN_NAME']!=='id'||!in_array($fk['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($fk['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)||isset($actual[$fk['COLUMN_NAME']])) throw new RuntimeException('Incompatible StackCP observation relationships.');$actual[$fk['COLUMN_NAME']]=$fk['REFERENCED_TABLE_NAME'];}
        $expected=['id'=>config()['db']['prefix'].($name==='provider_stackcp_reads'?'provider_refreshes':'provider_stackcp_reads')];if ($name==='provider_stackcp_reads') $expected['assignment_id']=config()['db']['prefix'].'provider_stackcp_assignments';ksort($actual);ksort($expected);if ($actual!==$expected) throw new RuntimeException('Incompatible StackCP observation relationships.');
    }
}
function provider_stackcp_access_checkpoint():array {
    $raw=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_stackcp_access_checkpoint'])['value']??'';
    $proof=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_stackcp_access_checkpoint_proof'])['value']??'';
    try {$d=json_decode($raw,true,4,JSON_THROW_ON_ERROR);if (!is_array($d)||array_keys($d)!==['version','started','started_sha256','finished','finished_sha256']||$d['version']!==1) throw new InvalidArgumentException();foreach (['started','finished'] as $kind) {if (!is_int($d[$kind])||$d[$kind]<0||$d[$kind]>10000) throw new InvalidArgumentException();provider_stackcp_hex($d[$kind.'_sha256'],64);if ($d[$kind]===0&&$d[$kind.'_sha256']!==str_repeat('0',64)) throw new InvalidArgumentException();}if ($d['finished']>$d['started']||provider_stackcp_access_json($d)!==$raw||!hash_equals(provider_stackcp_access_hmac('checkpoint',$d),$proof)) throw new InvalidArgumentException();return $d;
    } catch (JsonException|InvalidArgumentException|TypeError $e) {throw new RuntimeException('StackCP access checkpoint needs recovery.',0,$e);}
}
function provider_stackcp_access_checkpoint_save(array $d):void {provider_stackcp_guard(true);save_setting('provider_stackcp_access_checkpoint',provider_stackcp_access_json($d));save_setting('provider_stackcp_access_checkpoint_proof',provider_stackcp_access_hmac('checkpoint',$d));}
function provider_stackcp_access_detail(string $kind,array $r):string {return 'StackCP access '.$kind.' '.$r['id'].' '.$r['document_sha256'].' '.$r['proof'];}
function provider_stackcp_access_common_row(string $id,bool $finished):?array {
    $f=table('provider_refreshes');$z=table('provider_refresh_results');$select='f.*';if ($finished) foreach (array_slice(provider_stackcp_access_common_keys(true),8) as $field) $select.=',z.'.$field;
    return one("SELECT $select FROM $f f".($finished?" JOIN $z z ON z.id=f.id":'').' WHERE f.id=?',[$id]);
}
/** No DDL, decryption, history adoption or network. Validate every signed row before indexed availability. */
function provider_stackcp_access_ready():bool {
    provider_stackcp_guard();$settings=table('settings');$audit=table('audit');$f=table('provider_refreshes');$z=table('provider_refresh_results');$reads=table('provider_stackcp_reads');$results=table('provider_stackcp_observations');
    $marker=one("SELECT value FROM $settings WHERE name=?",['provider_stackcp_access_schema_version'])['value']??'';
    $operations=[StackCpAccessBinding::MEMBERSHIP,StackCpAccessBinding::GRANTS];
    if ($marker==='') {
        $witness=one("SELECT name FROM $settings WHERE name IN (?,?,?) LIMIT 1",['provider_stackcp_access_proof','provider_stackcp_access_checkpoint','provider_stackcp_access_checkpoint_proof'])||one("SELECT id FROM $audit WHERE BINARY action IN (?,?,?) LIMIT 1",['provider.stackcp_access_prepared','provider.stackcp_access_started','provider.stackcp_access_finished']);
        foreach (['started','finished'] as $kind) if (provider_stackcp_access_exists(provider_stackcp_access_table($kind))&&(int)query('SELECT COUNT(*) FROM '.table(provider_stackcp_access_table($kind)))->fetchColumn()>0) $witness=true;
        if ($witness) throw new RuntimeException('Restore the original StackCP access observation history.');
        if (provider_stackcp_access_exists('provider_refreshes')) {
            $hasOperation=one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].'provider_refreshes','operation'])!==null;
            if (!$hasOperation) {
                $inventoryVersion=one("SELECT value FROM $settings WHERE name=?",['provider_inventory_schema_version'])['value']??'';
                // Explicit legacy inventories predate operation-scoped reads. Never adopt or repair them on GET.
                if (!in_array($inventoryVersion,['1','2','3'],true)) throw new RuntimeException('StackCP access inventory history needs recovery.');
            } elseif (one("SELECT id FROM $f WHERE BINARY operation IN (?,?) LIMIT 1",$operations)) throw new RuntimeException('Restore the original StackCP access observation history.');
        }
        return false;
    }
    if ($marker!=='1'||!provider_stackcp_access_exists('provider_stackcp_reads')||!provider_stackcp_access_exists('provider_stackcp_observations')||!provider_stackcp_ready('assignment')) throw new RuntimeException('StackCP access storage needs recovery.');
    provider_stackcp_access_validate_schema();$prepared=provider_stackcp_proof(provider_stackcp_vault(),'access-prepared','1');
    if (!hash_equals($prepared,one("SELECT value FROM $settings WHERE name=?",['provider_stackcp_access_proof'])['value']??'')||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='provider.stackcp_access_prepared'")->fetchColumn()!==1||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='provider.stackcp_access_prepared' AND BINARY detail=?",['StackCP access observation storage ready '.$prepared])->fetchColumn()!==1) throw new RuntimeException('StackCP access preparation proof needs recovery.');
    $checkpoint=provider_stackcp_access_checkpoint();
    if ((int)query("SELECT COUNT(*) FROM $f WHERE BINARY operation IN (?,?)",$operations)->fetchColumn()!==$checkpoint['started']||(int)query("SELECT COUNT(*) FROM $z z JOIN $reads r ON r.id=z.id")->fetchColumn()!==$checkpoint['finished']) throw new RuntimeException('StackCP common observation counts disagree.');
    foreach (['started','finished'] as $kind) {
        $table=table(provider_stackcp_access_table($kind));$action='provider.stackcp_access_'.$kind;
        if ((int)query("SELECT COUNT(*) FROM $table")->fetchColumn()!==$checkpoint[$kind]||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action=?",[$action])->fetchColumn()!==$checkpoint[$kind]) throw new RuntimeException('StackCP access history counts disagree.');
        $seen=0;$previous=str_repeat('0',64);$lastAudit=0;$lastCommonAudit=0;$lastSeq=0;
        while ($seen<$checkpoint[$kind]) {
            $detail="CONCAT(BINARY 'StackCP access $kind ',r.id,' ',r.document_sha256,' ',r.proof)";
            $select=['r.*'];foreach (provider_stackcp_access_common_keys($kind==='finished') as $field) $select[]=((in_array($field,provider_stackcp_access_common_keys(),true)?'f.':'z.').$field.' AS c_'.$field);
            $select[]="(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail) AS own_audit_count";
            $select[]="(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail AND a.user_id=f.actor_id) AS own_actor_count";
            $select[]="(SELECT MIN(a.id) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail) AS own_audit_id";
            $commonDetail=$kind==='started'?"CONCAT(BINARY 'Refresh ',f.id,' profile ',f.profile_id,' revision ',f.profile_revision)":"CONCAT(BINARY 'Refresh ',f.id,' ',z.state,IF(z.reason='','',' '),z.reason)";
            $commonAction='provider.refresh_'.($kind==='started'?'started':'finished');
            $select[]="(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $commonDetail) AS common_audit_count";
            $select[]="(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $commonDetail AND a.user_id=f.actor_id) AS common_actor_count";
            $select[]="(SELECT MIN(a.id) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $commonDetail) AS common_audit_id";
            $join='';if ($kind==='finished') {$select[]='s.document_json AS start_json';$select[]='s.document_sha256 AS start_sha256';$join=" LEFT JOIN $z z ON z.id=r.id LEFT JOIN $reads s ON s.id=r.id";}
            $rows=all('SELECT '.implode(',',$select)." FROM $table r LEFT JOIN $f f ON f.id=r.id $join WHERE r.ordinal>? ORDER BY r.ordinal LIMIT 200",[$action,$action,$action,$commonAction,$commonAction,$commonAction,$seen]);
            if (!$rows) throw new RuntimeException('StackCP access history order needs recovery.');$starts=[];
            foreach ($rows as $row) {
                $d=provider_stackcp_access_row($kind,$row);$common=[];foreach (provider_stackcp_access_common_keys($kind==='finished') as $field) $common[$field]=$row['c_'.$field];
                if ($d['ordinal']!==++$seen||$d['previous_sha256']!==$previous||$d['common']!==provider_stackcp_access_common($common,$kind==='finished')||(int)$row['own_audit_count']!==1||(int)$row['own_actor_count']!==1||(int)$row['common_audit_count']!==1||(int)$row['common_actor_count']!==1||(int)$row['own_audit_id']<=$lastAudit||(int)$row['common_audit_id']<=$lastCommonAudit) throw new RuntimeException('StackCP access history correspondence needs recovery.');
                if ($kind==='started') {if ((int)$common['seq']<=$lastSeq||(int)$row['own_audit_id']<=(int)$row['common_audit_id']) throw new RuntimeException('StackCP access intent sequence needs recovery.');$lastSeq=(int)$common['seq'];$starts[]=$d;}
                else {if ($row['start_json']===null||$d['read_sha256']!==$row['start_sha256']||(int)$row['own_audit_id']>=(int)$row['common_audit_id']) throw new RuntimeException('StackCP access result source needs recovery.');$start=provider_stackcp_access_document('started',json_decode($row['start_json'],true,16,JSON_THROW_ON_ERROR));provider_stackcp_access_finish_check($start,$d);}
                $lastAudit=(int)$row['own_audit_id'];$lastCommonAudit=(int)$row['common_audit_id'];$previous=$row['document_sha256'];
            }
            if ($starts) provider_stackcp_access_retained_sources($starts);
        }
        if ($previous!==$checkpoint[$kind.'_sha256']) throw new RuntimeException('StackCP access history head needs recovery.');
    }
    // A finish audit with its common result removed is an independent surviving witness.
    if (one("SELECT r.id FROM $reads r JOIN $audit a ON BINARY a.action='provider.refresh_finished' AND BINARY a.detail LIKE CONCAT('Refresh ',r.id,' %') LEFT JOIN $z z ON z.id=r.id WHERE z.id IS NULL LIMIT 1")) throw new RuntimeException('StackCP common result history needs recovery.');
    return true;
}
function provider_stackcp_access_prepare(#[SensitiveParameter] string $password):void {
    if (db()->inTransaction()) throw new LogicException('StackCP observation preparation cannot run in a transaction.');
    staff_locked(function()use($password):void {
        $actor=provider_settings_owner($password);if (!provider_stackcp_ready('assignment')) throw new RuntimeException('Prepare protected StackCP assignments first.');if (provider_stackcp_access_ready()) return;
        foreach (provider_stackcp_access_schema(config()['db']['prefix']) as $name=>$sql) if (!provider_stackcp_access_exists($name)) db()->exec($sql);provider_stackcp_access_validate_schema();$proof=provider_stackcp_proof(provider_stackcp_vault(),'access-prepared','1');
        staff_transaction(function()use($actor,$proof):void {save_setting('provider_stackcp_access_schema_version','1');save_setting('provider_stackcp_access_proof',$proof);provider_stackcp_access_checkpoint_save(['version'=>1,'started'=>0,'started_sha256'=>str_repeat('0',64),'finished'=>0,'finished_sha256'=>str_repeat('0',64)]);provider_settings_audit((int)$actor['id'],'stackcp_access_prepared','StackCP access observation storage ready '.$proof);});
    });
}
/** Internal recorder hook. Full readiness must precede the common INSERT in the same locked transaction. */
function provider_stackcp_access_append(string $kind,array $document):array {
    provider_stackcp_guard(true);$d=provider_stackcp_access_document($kind,$document);$head=provider_stackcp_access_checkpoint();
    if ($head[$kind]>=10000||$d['ordinal']!==$head[$kind]+1||$d['previous_sha256']!==$head[$kind.'_sha256']) throw new RuntimeException('StackCP access history capacity or order needs review.');
    $json=provider_stackcp_access_json($d);$row=['id'=>$d['id'],'ordinal'=>$d['ordinal']];if ($kind==='started') foreach (['profile_id','package_id','client_id','stack_identity','operation','assignment_id'] as $key) $row[$key]=$d['binding'][$key];
    $row+=['document_json'=>$json,'document_sha256'=>hash('sha256',$json),'proof'=>provider_stackcp_access_hmac($kind,$d)];provider_stackcp_access_row($kind,$row);
    query('INSERT INTO '.table(provider_stackcp_access_table($kind)).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    $head[$kind]++;$head[$kind.'_sha256']=$row['document_sha256'];provider_stackcp_access_checkpoint_save($head);provider_settings_audit((int)$d['common']['actor_id'],'stackcp_access_'.$kind,provider_stackcp_access_detail($kind,$row));return $row;
}
