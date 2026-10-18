<?php
/** Immutable offer history. These helpers confer no current client, owner or provider authority. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/plans/records.php';
require_once __DIR__.'/catalog_offers_document.php';
require_once CWP_CODE_ROOT.'/database/schema/hosting_catalog_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function hosting_catalog_lock(): void {
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if ((int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$key])->fetchColumn()!==1) throw new LogicException('Hosting catalog requires its owning staff lock.');
}
function hosting_catalog_guard(): void {
    hosting_catalog_lock();
    if (!db()->inTransaction()) throw new LogicException('Hosting catalog requires its owning transaction.');
}
function hosting_catalog_tables(): array { return array_keys(hosting_catalog_schema(config()['db']['prefix'])); }
function hosting_catalog_table(string $name): void {
    if (!in_array($name,hosting_catalog_tables(),true)) throw new LogicException('Unknown hosting catalog table.');
}
function hosting_catalog_exists(string $name): bool {
    hosting_catalog_table($name);
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;
}
/** PDO may return integers as decimal strings, but never accept truncation, signs or floats. */
function hosting_catalog_integer(mixed $value,int $max=PHP_INT_MAX): int {
    if (is_int($value) && $value>=0 && $value<=$max) return $value;
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]*)\z/',$value) || strlen($value)>strlen((string)$max) || (strlen($value)===strlen((string)$max)&&strcmp($value,(string)$max)>0)) throw new RuntimeException('Hosting catalog numeric history is inconsistent.');
    return (int)$value;
}
function hosting_catalog_recovery(): never { throw new RuntimeException('Hosting offer history needs recovery. Restore its original tables, settings and audit records.'); }

function hosting_catalog_validate_schema(string $name,string $sql): void {
    $expected=hosting_catalog_indexes($name);$unique=[];
    foreach ($expected as $key=>[$nonUnique,$columns]) if ($key!=='PRIMARY'&&$nonUnique===0) $unique[$key]=$columns;
    catalog_validate_schema($name,$sql,$unique);
    foreach (all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $auto=$name==='hosting_catalog_publications'&&$column['Field']==='seq'?'auto_increment':'';
        $collation=match($column['Field']) {
            'id','plan_id','mapping_id','media_id','publication_id','request_id','agreement_id','kind','document_sha256'=>'ascii_bin',
            'document_json','reason'=>'utf8mb4_bin',
            default=>null,
        };
        if ($column['Extra']!==$auto || $column['Default']!==null || $column['Collation']!==$collation) hosting_catalog_recovery();
    }
    $actual=[];
    foreach (all('SHOW INDEX FROM '.table($name)) as $index) {
        if ($index['Sub_part']!==null || $index['Index_type']!=='BTREE') hosting_catalog_recovery();
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];
        $actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach ($actual as &$index) { ksort($index[1]);$index[1]=array_values($index[1]); } unset($index);
    ksort($actual);ksort($expected);if ($actual!==$expected) hosting_catalog_recovery();
    $relations=all('SELECT k.CONSTRAINT_NAME,k.ORDINAL_POSITION,k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    $groups=[];
    foreach ($relations as $relation) {
        if ($relation['REFERENCED_TABLE_SCHEMA']!==config()['db']['name'] || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)) hosting_catalog_recovery();
        $groups[$relation['CONSTRAINT_NAME']][(int)$relation['ORDINAL_POSITION']]=$relation;
    }
    $actual=[];
    foreach ($groups as $rows) {
        ksort($rows);$rows=array_values($rows);
        if (count(array_unique(array_column($rows,'REFERENCED_TABLE_NAME')))!==1) hosting_catalog_recovery();
        $actual[]=json_encode([array_column($rows,'COLUMN_NAME'),$rows[0]['REFERENCED_TABLE_NAME'],array_column($rows,'REFERENCED_COLUMN_NAME')],JSON_THROW_ON_ERROR);
    }
    $expected=array_map(static fn(array $r):string=>json_encode([$r[0],config()['db']['prefix'].$r[1],$r[2]],JSON_THROW_ON_ERROR),hosting_catalog_relations($name));
    sort($actual);sort($expected);if ($actual!==$expected) hosting_catalog_recovery();
}

/** Closed transition documents. Their caller separately owns current password/client authorization. */
function hosting_catalog_withdrawal_document(array $d): string {
    service_plans_keys($d,['format','id','publication_id','publication_sha256','actor_id','created_at','reason']);
    if ($d['format']!==1) hosting_catalog_recovery();
    service_plans_id($d['id']);service_plans_id($d['publication_id']);hosting_catalog_hash($d['publication_sha256']);
    service_plans_actor($d['actor_id']);service_plans_time($d['created_at']);hosting_catalog_text($d['reason'],500);
    return service_plans_json($d);
}
function hosting_catalog_decision_document(array $d): string {
    service_plans_keys($d,['format','id','request_id','request_sha256','kind','agreement_id','job_id','actor_staff_id','actor_client_id','created_at','reason']);
    if ($d['format']!==1 || !in_array($d['kind'],['offered','declined','withdrawn'],true) || (($d['actor_staff_id']===null)===($d['actor_client_id']===null))) hosting_catalog_recovery();
    service_plans_id($d['id']);service_plans_id($d['request_id']);hosting_catalog_hash($d['request_sha256']);service_plans_time($d['created_at']);hosting_catalog_text($d['reason'],1000,true,true);
    if ($d['actor_staff_id']!==null) service_plans_actor($d['actor_staff_id']);
    if ($d['actor_client_id']!==null) { service_plans_actor($d['actor_client_id']);if ($d['kind']!=='withdrawn') hosting_catalog_recovery(); }
    if ($d['kind']==='offered') {
        service_plans_id($d['agreement_id']);service_plans_actor($d['job_id']);
        if ($d['actor_staff_id']===null) hosting_catalog_recovery();
    } elseif ($d['agreement_id']!==null || $d['job_id']!==null) hosting_catalog_recovery();
    return service_plans_json($d);
}
function hosting_catalog_transition(string $bytes,bool $decision): array {
    if (strlen($bytes)>4096) hosting_catalog_recovery();
    $d=json_decode($bytes,true,8,JSON_THROW_ON_ERROR);
    if (!is_array($d) || ($decision?hosting_catalog_decision_document($d):hosting_catalog_withdrawal_document($d))!==$bytes) hosting_catalog_recovery();
    return $d;
}

/** Validate document and indexed row values without a database or present session. */
function hosting_catalog_row_document(string $name,array $row): HostingCatalogPublication|HostingCatalogRequest|array {
    hosting_catalog_table($name);service_plans_id($row['id']);hosting_catalog_hash($row['document_sha256']);
    if (!is_string($row['document_json']) || !hash_equals(hash('sha256',$row['document_json']),$row['document_sha256'])) hosting_catalog_recovery();
    $value=match($name) {
        'hosting_catalog_publications'=>HostingCatalogPublication::fromJson($row['document_json']),
        'hosting_catalog_requests'=>HostingCatalogRequest::fromJson($row['document_json']),
        default=>hosting_catalog_transition($row['document_json'],$name==='hosting_catalog_decisions'),
    };
    $d=is_array($value)?$value:$value->data();
    if ($d['id']!==$row['id'] || $d['created_at']!==hosting_catalog_integer($row['created_epoch'],253402300799)) hosting_catalog_recovery();
    if ($name==='hosting_catalog_publications') {
        $plan=ServicePlanDocument::fromJson($d['plan_json']);$mapping=provider_plan_catalog_document($d['mapping_json']);
        if (hosting_catalog_integer($row['seq'])<1 || $d['product']['id']!==hosting_catalog_integer($row['product_id']) || $d['product']['revision']!==hosting_catalog_integer($row['product_revision']) || $plan->data()['id']!==$row['plan_id'] || $mapping['id']!==$row['mapping_id'] || $d['product']['media_id']!==$row['media_id'] || $d['publisher']['id']!==hosting_catalog_integer($row['created_by'])) hosting_catalog_recovery();
    } elseif ($name==='hosting_catalog_requests') {
        if ($d['publication_id']!==$row['publication_id'] || $d['client_id']!==hosting_catalog_integer($row['client_id'])) hosting_catalog_recovery();
    } elseif ($name==='hosting_catalog_withdrawals') {
        if ($d['publication_id']!==$row['publication_id'] || $d['actor_id']!==hosting_catalog_integer($row['actor_id']) || $d['reason']!==$row['reason']) hosting_catalog_recovery();
    } else {
        foreach (['request_id','kind','agreement_id'] as $key) if ($d[$key]!==$row[$key]) hosting_catalog_recovery();
        foreach (['job_id','actor_staff_id','actor_client_id'] as $key) if ($d[$key]!==($row[$key]===null?null:hosting_catalog_integer($row[$key]))) hosting_catalog_recovery();
    }
    return $value;
}
function hosting_catalog_action(string $name): string {
    return 'hosting_catalog.'.match($name) {
        'hosting_catalog_publications'=>'publication','hosting_catalog_withdrawals'=>'withdrawal',
        'hosting_catalog_requests'=>'request','hosting_catalog_decisions'=>'decision',
        default=>throw new LogicException('Unknown hosting catalog table.'),
    };
}
/** Sequence is outside the pure document and therefore explicitly sealed by this independent witness. */
function hosting_catalog_witness(string $name,array $row): array {
    $actor=match($name) {
        'hosting_catalog_publications'=>hosting_catalog_integer($row['created_by']),
        'hosting_catalog_withdrawals'=>hosting_catalog_integer($row['actor_id']),
        'hosting_catalog_requests'=>0,
        'hosting_catalog_decisions'=>$row['actor_staff_id']===null?0:hosting_catalog_integer($row['actor_staff_id']),
        default=>throw new LogicException('Unknown hosting catalog table.'),
    };
    $detail='Record '.$row['id'].' sha256 '.$row['document_sha256'];
    if ($name==='hosting_catalog_publications') $detail.=' seq '.hosting_catalog_integer($row['seq']);
    if ($name==='hosting_catalog_requests') $detail.=' client '.hosting_catalog_integer($row['client_id']);
    if ($name==='hosting_catalog_decisions'&&$row['actor_client_id']!==null) $detail.=' client '.hosting_catalog_integer($row['actor_client_id']);
    return [$actor,hosting_catalog_action($name),$detail,gmdate('Y-m-d H:i:s',hosting_catalog_integer($row['created_epoch'],253402300799))];
}
function hosting_catalog_assert_witness(string $name,array $row): void {
    if (hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=? AND created_at=?',hosting_catalog_witness($name,$row))->fetchColumn())!==1) hosting_catalog_recovery();
}
function hosting_catalog_row(string $name,string $id): array {
    hosting_catalog_table($name);service_plans_id($id);
    $row=one('SELECT * FROM '.table($name).' WHERE id=?',[$id]);
    if (!$row) hosting_catalog_recovery();
    hosting_catalog_row_document($name,$row);hosting_catalog_assert_witness($name,$row);return $row;
}

/** Cleanup first validates readiness once under this same mutex. History retains superseded images. */
function hosting_catalog_image_used(string $id): bool {
    hosting_catalog_lock();service_plans_id($id);
    return one('SELECT id FROM '.table('hosting_catalog_publications').' WHERE media_id=? LIMIT 1',[$id])!==null;
}

/** Historical links use retained revisions; later credentials, retirement and password changes are irrelevant. */
function hosting_catalog_validate_links(string $name,array $row): void {
    $value=hosting_catalog_row_document($name,$row);$d=is_array($value)?$value:$value->data();
    if ($name==='hosting_catalog_publications') {
        $saved=one('SELECT snapshot FROM '.table('catalog_revisions').' WHERE product_id=? AND revision=?',[$row['product_id'],$row['product_revision']]);
        if (!$saved) hosting_catalog_recovery();
        $product=json_decode($saved['snapshot'],true,32,JSON_THROW_ON_ERROR);
        foreach ($d['product'] as $key=>$v) if (!in_array($key,['id','revision','media_id'],true)&&(!array_key_exists($key,$product)||$product[$key]!==$v)) hosting_catalog_recovery();
        $plan=service_plans_validate(service_plans_row($row['plan_id']))->prefix(ServicePlanDocument::fromJson($d['plan_json'])->revision());
        if ($plan->json()!==$d['plan_json']) hosting_catalog_recovery();
        $mapping=one('SELECT binding_json,binding_sha256 FROM '.table('provider_plan_catalog').' WHERE id=?',[$row['mapping_id']]);
        if (!$mapping || $mapping['binding_json']!==$d['mapping_json'] || !hash_equals(hash('sha256',$d['mapping_json']),$mapping['binding_sha256'])) hosting_catalog_recovery();
    } elseif ($name==='hosting_catalog_requests'||$name==='hosting_catalog_withdrawals') {
        $publication=HostingCatalogPublication::fromJson(hosting_catalog_row('hosting_catalog_publications',$row['publication_id'])['document_json']);
        if ($name==='hosting_catalog_requests') $value->assertPublication($publication);
        elseif ($d['publication_sha256']!==$publication->fingerprint()||$d['created_at']<$publication->data()['created_at']) hosting_catalog_recovery();
    } else {
        $request=HostingCatalogRequest::fromJson(hosting_catalog_row('hosting_catalog_requests',$row['request_id'])['document_json']);$r=$request->data();
        if ($d['request_sha256']!==$request->fingerprint() || $d['created_at']<$r['created_at'] || ($d['actor_client_id']!==null&&$d['actor_client_id']!==$r['client_id'])) hosting_catalog_recovery();
        if ($d['kind']==='offered') {
            $publication=HostingCatalogPublication::fromJson(hosting_catalog_row('hosting_catalog_publications',$r['publication_id'])['document_json']);$request->assertPublication($publication);
            $agreement=service_plans_validate(service_plans_row($d['agreement_id'],true),true)->data();
            $job=one('SELECT client_id FROM '.table('studio_jobs').' WHERE id=?',[$d['job_id']]);
            if (!$job || hosting_catalog_integer($job['client_id'])!==$r['client_id'] || $agreement['client_id']!==$r['client_id'] || $agreement['created_by']!==$d['actor_staff_id'] || $agreement['created_at']!==$d['created_at'] || service_plans_json($agreement['plan'])!==$publication->data()['plan_json']) hosting_catalog_recovery();
        }
    }
}

/** Read-only, under the shared mutex. Missing established history never becomes an empty store. */
function hosting_catalog_ready(): bool {
    hosting_catalog_lock();$tables=hosting_catalog_tables();
    $marker=one('SELECT value FROM '.table('settings').' WHERE name=?',['hosting_catalog_version']);
    $counts=[];$exists=[];$rows=[];
    foreach ($tables as $name) {
        $counts[$name]=one('SELECT value FROM '.table('settings').' WHERE name=?',[$name.'_count']);
        $exists[$name]=hosting_catalog_exists($name);
        $rows[$name]=$exists[$name]?hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table($name))->fetchColumn(),999999999):0;
    }
    $events=hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY LEFT(action,16)=?',['hosting_catalog.'])->fetchColumn());
    if (!$marker) {
        if (array_filter($counts)||array_sum($rows)!==0||$events!==0) hosting_catalog_recovery();
        return false;
    }
    if ($marker['value']!=='1'||$events!==1+array_sum($rows)) hosting_catalog_recovery();
    $setup=all('SELECT user_id,detail FROM '.table('audit').' WHERE BINARY action=?',['hosting_catalog.prepared']);
    if (count($setup)!==1||hosting_catalog_integer($setup[0]['user_id'])<1||$setup[0]['detail']!=='Hosting offer storage version 1') hosting_catalog_recovery();
    foreach (hosting_catalog_schema(config()['db']['prefix']) as $name=>$sql) {
        if (!$exists[$name]||!$counts[$name]||hosting_catalog_integer($counts[$name]['value'],999999999)!==$rows[$name]) hosting_catalog_recovery();
        hosting_catalog_validate_schema($name,$sql);
        if (hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',[hosting_catalog_action($name)])->fetchColumn())!==$rows[$name]) hosting_catalog_recovery();
        foreach (hosting_catalog_relations($name) as [$local,$parent,$foreign]) {
            $join=[];foreach ($local as $i=>$field) $join[]='c.'.$field.'=p.'.$foreign[$i];
            if (one('SELECT c.id FROM '.table($name).' c LEFT JOIN '.table($parent).' p ON '.implode(' AND ',$join).' WHERE c.'.$local[0].' IS NOT NULL AND p.'.$foreign[0].' IS NULL LIMIT 1')) hosting_catalog_recovery();
        }
        $cursor=null;$seen=0;
        do {
            $batch=$cursor===null?all('SELECT * FROM '.table($name).' ORDER BY id LIMIT 64'):all('SELECT * FROM '.table($name).' WHERE id>? ORDER BY id LIMIT 64',[$cursor]);
            foreach ($batch as $row) { hosting_catalog_validate_links($name,$row);hosting_catalog_assert_witness($name,$row);$cursor=$row['id'];$seen++; }
        } while (count($batch)===64);
        if ($seen!==$rows[$name]) hosting_catalog_recovery();
    }
    return true;
}

/** Historical catalog origin, under the caller's current authority and owning transaction.
 * This is not a new publication/readiness grant and never selects a replacement mapping.
 */
function hosting_catalog_agreement_origin(string $agreement,int $client): ?array {
    hosting_catalog_guard();service_plans_id($agreement);service_plans_actor($client);
    if (!hosting_catalog_ready()) return null;
    $row=one('SELECT * FROM '.table('hosting_catalog_decisions').' WHERE agreement_id=?',[$agreement]);
    if (!$row) return null;
    $decision=hosting_catalog_row_document('hosting_catalog_decisions',$row);
    $request=HostingCatalogRequest::fromJson(hosting_catalog_row('hosting_catalog_requests',$decision['request_id'])['document_json']);
    $publication=HostingCatalogPublication::fromJson(hosting_catalog_row('hosting_catalog_publications',$request->data()['publication_id'])['document_json']);
    $request->assertPublication($publication);
    if ($decision['kind']!=='offered' || $decision['agreement_id']!==$agreement || $request->data()['client_id']!==$client) hosting_catalog_recovery();
    $mapping=provider_plan_catalog_document($publication->data()['mapping_json']);
    return ['publication_id'=>$publication->data()['id'],'job_id'=>$decision['job_id'],'mapping_id'=>$mapping['id'],'mapping_sha256'=>hash('sha256',$publication->data()['mapping_json'])];
}

/** After one new INSERT, in the caller's authorized transaction. Duplicate/retry paths do not call this. */
function hosting_catalog_recorded(string $name,string $id): array {
    hosting_catalog_guard();hosting_catalog_table($name);service_plans_id($id);
    if ((one('SELECT value FROM '.table('settings').' WHERE name=?',['hosting_catalog_version'])['value']??null)!=='1') hosting_catalog_recovery();
    $count=one('SELECT value FROM '.table('settings').' WHERE name=?',[$name.'_count']);
    if (!$count) hosting_catalog_recovery();
    $before=hosting_catalog_integer($count['value'],999999998);
    if (hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())!==$before+1 || hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',[hosting_catalog_action($name)])->fetchColumn())!==$before) hosting_catalog_recovery();
    $row=one('SELECT * FROM '.table($name).' WHERE id=?',[$id]);if (!$row) hosting_catalog_recovery();
    hosting_catalog_validate_links($name,$row);
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',hosting_catalog_witness($name,$row));
    save_setting($name.'_count',(string)($before+1));
    return $row;
}
