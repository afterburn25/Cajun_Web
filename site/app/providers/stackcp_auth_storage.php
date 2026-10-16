<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/settings.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_documents.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_auth_vault.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_stackcp_auth_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_stackcp_assignment_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function provider_stackcp_family(string $kind): array {
    return match($kind) {
        'auth'=>['table'=>'provider_stackcp_auth','setting'=>'provider_stackcp_auth','action'=>'stackcp_auth','label'=>'StackCP authentication'],
        'assignment'=>['table'=>'provider_stackcp_assignments','setting'=>'provider_stackcp_assignment','action'=>'stackcp_assignment','label'=>'StackCP assignment'],
        default=>throw new LogicException('Unknown StackCP history family.'),
    };
}
function provider_stackcp_guard(bool $write=false): void {
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if (($write&&!db()->inTransaction()) || (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1) throw new LogicException('StackCP history requires the owning staff mutex and writes require a transaction.');
}
function provider_stackcp_exists(string $kind): bool {
    $f=provider_stackcp_family($kind);return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$f['table']])!==null;
}
function provider_stackcp_schema_validate(string $kind): void {
    $f=provider_stackcp_family($kind);$name=$f['table'];$sql=provider_stackcp_journal_schema(config()['db']['prefix'],$kind)[$name];
    catalog_validate_schema($name,$sql,['stackcp_ordinal'=>['ordinal'],'stackcp_scope_revision'=>['scope_id','revision']]);
    foreach (all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $text=preg_match('/^(?:char|varchar|longtext|text)/i',$column['Type'])===1;
        if ($column['Extra']!=='' || $column['Default']!==null || $column['Collation']!==($text?'ascii_bin':null)) throw new RuntimeException('Incompatible StackCP history columns.');
    }
    $expected=['PRIMARY'=>[0,['id']],'stackcp_ordinal'=>[0,['ordinal']],'stackcp_scope_revision'=>[0,['scope_id','revision']],'stackcp_profile'=>[1,['profile_id']],'stackcp_actor'=>[1,['actor_id']]];
    if ($kind==='assignment') $expected['stackcp_client']=[1,['client_id']];
    $actual=[];foreach (all('SHOW INDEX FROM '.table($name)) as $index) {
        if ($index['Sub_part']!==null || $index['Index_type']!=='BTREE') throw new RuntimeException('Incompatible StackCP history indexes.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach ($actual as &$index) {ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);ksort($actual);ksort($expected);
    if ($actual!==$expected) throw new RuntimeException('Incompatible StackCP history indexes.');
    $actual=[];foreach (all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]) as $fk) {
        if ($fk['REFERENCED_COLUMN_NAME']!=='id' || !in_array($fk['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($fk['UPDATE_RULE'],['RESTRICT','NO ACTION'],true) || isset($actual[$fk['COLUMN_NAME']])) throw new RuntimeException('Incompatible StackCP history relationships.');
        $actual[$fk['COLUMN_NAME']]=$fk['REFERENCED_TABLE_NAME'];
    }
    $expected=['profile_id'=>config()['db']['prefix'].'provider_profiles','actor_id'=>config()['db']['prefix'].'users'];if ($kind==='assignment') $expected['client_id']=config()['db']['prefix'].'studio_clients';ksort($actual);ksort($expected);
    if ($actual!==$expected) throw new RuntimeException('Incompatible StackCP history relationships.');
}
function provider_stackcp_counter(string $kind): int {
    $f=provider_stackcp_family($kind);$value=one('SELECT value FROM '.table('settings').' WHERE name=?',[$f['setting'].'_count'])['value']??'';
    // Local safety bound, not a provider limit. Refuse additional history rather than prune it.
    if (preg_match('/\A(?:0|[1-9][0-9]{0,4})\z/',$value)!==1 || (int)$value>10000) throw new RuntimeException('StackCP history count needs recovery.');return (int)$value;
}
function provider_stackcp_prepared_detail(string $kind,string $proof): string {return provider_stackcp_family($kind)['label'].' storage ready '.$proof;}
function provider_stackcp_version_detail(string $kind,array $row): string {return provider_stackcp_family($kind)['label'].' version '.$row['id'].' '.$row['document_sha256'].' '.$row['proof'];}
function provider_stackcp_proof(ProviderStackCpAuthVault $vault,string $kind,string $bytes): string {return $vault->proof(config()['db']['prefix'].'|'.$kind,$bytes);}
/** Validate canonical signed bytes and every duplicated lookup field before any indexed selection. */
function provider_stackcp_row(string $kind,array $row,ProviderStackCpAuthVault $vault): array {
    try {
        $decoded=json_decode($row['document_json'],true,8,JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new InvalidArgumentException();$d=provider_stackcp_document($kind,$decoded);$json=provider_stackcp_json($d);
        if ($json!==$row['document_json'] || !hash_equals(hash('sha256',$json),$row['document_sha256']) || !hash_equals(provider_stackcp_proof($vault,$kind,$json),$row['proof'])) throw new InvalidArgumentException();
        foreach (['id','scope_id','profile_id','previous_sha256'] as $key) if ($d[$key]!==$row[$key]) throw new InvalidArgumentException();
        foreach (['ordinal','revision','actor_id'] as $key) if ((string)$d[$key]!== (string)$row[$key]) throw new InvalidArgumentException();
        if ($kind==='auth') {
            if (!hash_equals($d['ciphertext_sha256'],hash('sha256',$row['ciphertext']))) throw new InvalidArgumentException();
            $raw=base64_decode($row['ciphertext'],true);
            if (($d['enabled'] && ($raw===false || strlen($raw)<29 || strlen($raw)>8192 || base64_encode($raw)!==$row['ciphertext'])) || (!$d['enabled'] && $row['ciphertext']!=='')) throw new InvalidArgumentException();
        } elseif ((string)$d['client_id']!== (string)$row['client_id']) throw new InvalidArgumentException();
        return $d;
    } catch (JsonException|InvalidArgumentException|TypeError $e) {throw new RuntimeException('StackCP signed history needs recovery.',0,$e);}
}
/** No DDL, credential decryption, provider call or history repair. Master-key use is HMAC verification only. */
function provider_stackcp_ready(string $kind,?ProviderStackCpAuthVault $vault=null): bool {
    provider_stackcp_guard();$f=provider_stackcp_family($kind);$settings=table('settings');$audit=table('audit');$name=$f['table'];
    foreach (['settings','audit','provider_profiles','users'] as $required) {
        $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$required]);
        if (strtolower($engine['ENGINE']??'')!=='innodb') throw new RuntimeException('StackCP prerequisites require transactional storage.');
    }
    $version=one("SELECT value FROM $settings WHERE name=?",[$f['setting'].'_schema_version'])['value']??'';
    if ($version==='') {
        $witness=one("SELECT id FROM $audit WHERE BINARY action IN (?,?) LIMIT 1",['provider.'.$f['action'].'_prepared','provider.'.$f['action'].'_version'])!==null || one("SELECT name FROM $settings WHERE name IN (?,?) LIMIT 1",[$f['setting'].'_count',$f['setting'].'_proof'])!==null;
        if ($witness || (provider_stackcp_exists($kind) && (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)) throw new RuntimeException('Restore the original StackCP prerequisite history.');
        return false;
    }
    if ($version!=='1' || !provider_settings_ready() || !provider_stackcp_exists($kind)) throw new RuntimeException('StackCP prerequisite storage needs recovery.');
    provider_stackcp_schema_validate($kind);$vault??=provider_stackcp_vault();$proof=provider_stackcp_proof($vault,$kind.'-prepared','1');
    if ($kind==='assignment' && (!provider_inventory_ready() || !provider_stackcp_ready('auth',$vault))) throw new RuntimeException('StackCP assignment prerequisites need recovery.');
    if (!hash_equals($proof,one("SELECT value FROM $settings WHERE name=?",[$f['setting'].'_proof'])['value']??'')) throw new RuntimeException('StackCP preparation proof needs recovery.');
    $prepared='provider.'.$f['action'].'_prepared';
    if ((int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action=?",[$prepared])->fetchColumn()!==1 || (int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action=? AND BINARY detail=?",[$prepared,provider_stackcp_prepared_detail($kind,$proof)])->fetchColumn()!==1) throw new RuntimeException('StackCP preparation audit needs recovery.');
    $count=provider_stackcp_counter($kind);$table=table($name);$action='provider.'.$f['action'].'_version';
    if ($count!==(int)query("SELECT COUNT(*) FROM $table")->fetchColumn() || $count!==(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action=?",[$action])->fetchColumn()) throw new RuntimeException('StackCP history counts disagree.');
    $seen=0;$chains=[];$lastAudit=0;
    // At most 10,000 rows and 200 rows per fetch. No per-row database round trips.
    while ($seen<$count) {
        $detail="CONCAT(BINARY '".$f['label']." version ',r.id,' ',r.document_sha256,' ',r.proof)";
        $rows=all("SELECT r.*,(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail) AS audit_count,(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail AND a.user_id=r.actor_id) AS actor_count,(SELECT MIN(a.id) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail) AS journal_audit_id FROM $table r WHERE r.ordinal>? ORDER BY r.ordinal LIMIT 200",[$action,$action,$action,$seen]);
        if (!$rows) throw new RuntimeException('StackCP history order needs recovery.');
        foreach ($rows as $row) {
            $d=provider_stackcp_row($kind,$row,$vault);$previous=$chains[$d['scope_id']]??null;
            if ($d['ordinal']!==++$seen || (int)$row['audit_count']!==1 || (int)$row['actor_count']!==1 || (int)$row['journal_audit_id']<=$lastAudit || $d['revision']!==($previous['revision']??0)+1 || $d['previous_sha256']!==($previous['sha256']??str_repeat('0',64)) || $d['recorded_epoch']<($previous['epoch']??0)) throw new RuntimeException('StackCP history chain needs recovery.');
            $chains[$d['scope_id']]=['revision'=>$d['revision'],'sha256'=>$row['document_sha256'],'epoch'=>$d['recorded_epoch']];$lastAudit=(int)$row['journal_audit_id'];
        }
    }
    if ($kind==='assignment') provider_stackcp_assignment_sources($vault);
    return true;
}
function provider_stackcp_prepare_family(string $kind,#[SensitiveParameter] string $password): void {
    if (db()->inTransaction()) throw new LogicException('StackCP preparation cannot run in a transaction.');
    staff_locked(function()use($kind,$password):void {
        $actor=provider_settings_owner($password);if (!staff_ready() || !studio_ready()) throw new RuntimeException('Prepare administrator and client storage first.');
        if (provider_stackcp_ready($kind)) return;
        if ($kind==='assignment' && (!provider_inventory_ready() || !provider_stackcp_ready('auth'))) throw new RuntimeException('Prepare hosting inventory and StackCP authentication first.');
        foreach (provider_stackcp_journal_schema(config()['db']['prefix'],$kind) as $sql) if (!provider_stackcp_exists($kind)) db()->exec($sql);
        provider_stackcp_schema_validate($kind);
        // Creation is allowed only for a genuinely unprepared family. Existing profiles still enforce the unchanged vault guard.
        provider_settings_vault_key(true);$f=provider_stackcp_family($kind);$proof=provider_stackcp_proof(provider_stackcp_vault(),$kind.'-prepared','1');
        staff_transaction(function()use($f,$kind,$actor,$proof):void {
            save_setting($f['setting'].'_schema_version','1');save_setting($f['setting'].'_count','0');save_setting($f['setting'].'_proof',$proof);
            provider_settings_audit((int)$actor['id'],$f['action'].'_prepared',provider_stackcp_prepared_detail($kind,$proof));
        });
    });
}
/** Internal selection: callers must first validate the complete family under the shared mutex. */
function provider_stackcp_latest(string $kind,string $scope): ?array {
    provider_stackcp_guard();$f=provider_stackcp_family($kind);return one('SELECT * FROM '.table($f['table']).' WHERE scope_id=? ORDER BY revision DESC LIMIT 1',[$scope]);
}
function provider_stackcp_append(string $kind,array $document,string $cipher,ProviderStackCpAuthVault $vault): array {
    provider_stackcp_guard(true);if (!provider_stackcp_ready($kind,$vault)) throw new RuntimeException('Prepare StackCP prerequisite storage first.');
    $f=provider_stackcp_family($kind);$d=provider_stackcp_document($kind,$document);$count=provider_stackcp_counter($kind);$old=provider_stackcp_latest($kind,$d['scope_id']);
    $previous=$old?provider_stackcp_row($kind,$old,$vault):null;
    if ($d['revision']!==($previous['revision']??0)+1 || $d['previous_sha256']!==($old['document_sha256']??str_repeat('0',64)) || $d['recorded_epoch']<($previous['recorded_epoch']??0)) throw new RuntimeException('StackCP history changed before append.');
    if ($count>=10000 || $d['ordinal']!==$count+1) throw new RuntimeException('StackCP history capacity or order needs review.');
    $json=provider_stackcp_json($d);$row=['id'=>$d['id'],'ordinal'=>$d['ordinal'],'scope_id'=>$d['scope_id'],'profile_id'=>$d['profile_id'],'revision'=>$d['revision'],'actor_id'=>$d['actor_id'],'previous_sha256'=>$d['previous_sha256'],'document_json'=>$json,'document_sha256'=>hash('sha256',$json),'proof'=>provider_stackcp_proof($vault,$kind,$json)];
    if ($kind==='auth') $row['ciphertext']=$cipher;else $row['client_id']=$d['client_id'];provider_stackcp_row($kind,$row,$vault);
    query('INSERT INTO '.table($f['table']).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    save_setting($f['setting'].'_count',(string)($count+1));provider_settings_audit($d['actor_id'],$f['action'].'_version',provider_stackcp_version_detail($kind,$row));return $row;
}
