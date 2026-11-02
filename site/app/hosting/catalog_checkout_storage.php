<?php
/** Retained checkout history proves exact local records, never present authentication. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_offers_storage.php';
require_once __DIR__.'/catalog_checkout_billing_document.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
require_once CWP_CODE_ROOT.'/database/schema/hosting_checkout_schema.php';

use CajunWebPro\Foundation\RecurringBilling\RecurringBillingSchedule;

function hosting_checkout_recovery(): never { throw new RuntimeException('Hosting checkout history needs recovery. Restore its original tables, records and audit history.'); }
function hosting_checkout_tables(): array { return array_keys(hosting_checkout_schema(config()['db']['prefix'])); }
function hosting_checkout_table(string $name): void { if (!in_array($name,hosting_checkout_tables(),true)) throw new LogicException('Unknown checkout table.'); }
function hosting_checkout_exists(string $name): bool {
    hosting_checkout_table($name);
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;
}
function hosting_checkout_namespace(): array {
    $prefix=config()['db']['prefix'].'hosting_checkout_';
    $names=array_column(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen($prefix),$prefix]),'TABLE_NAME');
    $allowed=array_map(static fn(string $name):string=>config()['db']['prefix'].$name,hosting_checkout_tables());
    foreach ($names as $name) if (!in_array($name,$allowed,true)) hosting_checkout_recovery();
    sort($names);return $names;
}

function hosting_checkout_validate_schema(string $name,string $sql): void {
    $expected=hosting_checkout_indexes($name);$unique=[];
    foreach ($expected as $key=>[$nonUnique,$columns]) if ($key!=='PRIMARY'&&$nonUnique===0) $unique[$key]=$columns;
    catalog_validate_schema($name,$sql,$unique);
    foreach (all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $auto='';
        $collation=match($column['Field']) {
            'id','publication_id','agreement_id','schedule_id','issuance_id','publication_sha256','input_sha256','consent_sha256','document_sha256'=>'ascii_bin',
            'document_json','consent_json'=>'utf8mb4_bin',
            default=>null,
        };
        if ($column['Extra']!==$auto || $column['Default']!==null || $column['Collation']!==$collation) hosting_checkout_recovery();
    }
    $actual=[];
    foreach (all('SHOW INDEX FROM '.table($name)) as $index) {
        if ($index['Sub_part']!==null || $index['Index_type']!=='BTREE') hosting_checkout_recovery();
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];
        $actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach ($actual as &$index) { ksort($index[1]);$index[1]=array_values($index[1]); } unset($index);
    ksort($actual);ksort($expected);if ($actual!==$expected) hosting_checkout_recovery();
    $relations=all('SELECT k.CONSTRAINT_NAME,k.ORDINAL_POSITION,k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    $groups=[];
    foreach ($relations as $relation) {
        if ($relation['REFERENCED_TABLE_SCHEMA']!==config()['db']['name'] || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)) hosting_checkout_recovery();
        $groups[$relation['CONSTRAINT_NAME']][(int)$relation['ORDINAL_POSITION']]=$relation;
    }
    $actual=[];
    foreach ($groups as $rows) {
        ksort($rows);$rows=array_values($rows);
        if (count(array_unique(array_column($rows,'REFERENCED_TABLE_NAME')))!==1) hosting_checkout_recovery();
        $actual[]=json_encode([array_column($rows,'COLUMN_NAME'),$rows[0]['REFERENCED_TABLE_NAME'],array_column($rows,'REFERENCED_COLUMN_NAME')],JSON_THROW_ON_ERROR);
    }
    $expected=array_map(static fn(array $r):string=>json_encode([$r[0],config()['db']['prefix'].$r[1],$r[2]],JSON_THROW_ON_ERROR),hosting_checkout_relations($name));
    sort($actual);sort($expected);if ($actual!==$expected) hosting_checkout_recovery();
}

function hosting_checkout_action(string $name): string {
    return match($name) {'hosting_checkout_reviews'=>'hosting_checkout.reviewed','hosting_checkout_results'=>'hosting_checkout.completed',default=>throw new LogicException('Unknown checkout table.')};
}
function hosting_checkout_witness(string $name,array $row): array {
    return [0,hosting_checkout_action($name),'Client '.hosting_catalog_integer($row['client_id']).' request '.$row['id'].' sha256 '.$row['document_sha256'],gmdate('Y-m-d H:i:s',hosting_catalog_integer($row['created_epoch'],253402300799))];
}
function hosting_checkout_assert_witness(string $name,array $row): void {
    if (hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('audit').' WHERE user_id=? AND BINARY action=? AND BINARY detail=? AND created_at=?',hosting_checkout_witness($name,$row))->fetchColumn())!==1) hosting_checkout_recovery();
}
function hosting_checkout_recorded(string $name,array $row): void {
    hosting_catalog_guard();hosting_checkout_table($name);
    $count=one('SELECT value FROM '.table('settings').' WHERE name=?',[$name.'_count']);
    if (!$count) hosting_checkout_recovery();
    $n=hosting_catalog_integer($count['value'],999999998);
    if (hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())!==$n+1 || hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',[hosting_checkout_action($name)])->fetchColumn())!==$n) hosting_checkout_recovery();
    save_setting($name.'_count',(string)($n+1));
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',hosting_checkout_witness($name,$row));
}
function hosting_checkout_publication(string $id): HostingCatalogPublication {
    hosting_catalog_guard();return HostingCatalogPublication::fromJson(hosting_catalog_row('hosting_catalog_publications',$id)['document_json']);
}
function hosting_checkout_review_row(array $row): HostingCheckoutReview {
    service_plans_id($row['id']);hosting_catalog_hash($row['publication_sha256']);hosting_catalog_hash($row['document_sha256']);
    $publication=hosting_checkout_publication($row['publication_id']);
    $review=HostingCheckoutReview::fromJson($row['document_json'],$publication);$r=$review->data();
    service_plans_client_row($r['client_id'],false);
    if ($r['request_id']!==$row['id'] || $r['client_id']!==hosting_catalog_integer($row['client_id']) || $r['publication_sha256']!==$row['publication_sha256']
        || $r['reviewed_at']!==hosting_catalog_integer($row['created_epoch']) || $r['expires_at']!==hosting_catalog_integer($row['expires_epoch'])
        || ($r['project']['mode']==='existing'?$r['project']['id']:null)!==($row['project_id']===null?null:hosting_catalog_integer($row['project_id']))
        || $review->fingerprint()!==$row['document_sha256']) hosting_checkout_recovery();
    // Project ownership remains necessary even for historical/replayed client access.
    if ($row['project_id']!==null) recurring_billing_job(hosting_catalog_integer($row['project_id']),$r['client_id']);
    hosting_checkout_assert_witness('hosting_checkout_reviews',$row);return $review;
}
function hosting_checkout_result_document(HostingCheckoutConsent $consent,ServiceSubscriptionDocument $initial,RecurringBillingSchedule $schedule,array $map): string {
    $c=$consent->data();$r=$consent->review()->data();$s=$initial->data();$b=$schedule->data();
    if ($s['format']!==2 || $initial->revision()!==2 || $s['checkout']['consent_json']!==$consent->json() || $s['checkout']['consent_sha256']!==$consent->fingerprint()
        || $s['checkout']['request_input_sha256']!==$consent->inputHash() || $s['client_id']!==$c['client_id'] || $s['created_at']!==$c['accepted_at']
        || $b['format']!==2 || $schedule->revision()!==1 || $b['terms']['source_subscription']['id']!==$s['id'] || $b['terms']['source_subscription']['sha256']!==$initial->fingerprint()
        || $b['created_at']!==$c['accepted_at'] || $map['schedule_id']!==$b['terms']['id'] || hosting_catalog_integer($map['period_index'])!==0) hosting_checkout_recovery();
    hosting_checkout_billing_assert(\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($b['terms']),$initial);
    return service_plans_json(['format'=>1,'request_id'=>$c['request_id'],'review_sha256'=>$consent->review()->fingerprint(),'client_id'=>$c['client_id'],
        'publication_id'=>$r['publication_id'],'publication_sha256'=>$r['publication_sha256'],'project_id'=>$s['checkout']['project_id'],
        'accepted_at'=>$c['accepted_at'],'input_sha256'=>$consent->inputHash(),'consent_sha256'=>$consent->fingerprint(),
        'agreement_id'=>$s['id'],'agreement_initial_sha256'=>$initial->fingerprint(),'schedule_id'=>$b['terms']['id'],'schedule_initial_sha256'=>$schedule->fingerprint(),
        'invoice_id'=>hosting_catalog_integer($map['invoice_id']),'issuance_id'=>$map['id'],'invoice_snapshot_sha256'=>$map['snapshot_sha256']]);
}
/** Complete graph validation uses original prefixes while allowing later genuine lifecycle events. */
function hosting_checkout_result_row(array $row): array {
    hosting_catalog_guard();service_plans_id($row['id']);hosting_catalog_hash($row['document_sha256']);
    $reviewRow=one('SELECT * FROM '.table('hosting_checkout_reviews').' WHERE id=?',[$row['id']]);
    if (!$reviewRow) hosting_checkout_recovery();
    $review=hosting_checkout_review_row($reviewRow);$publication=hosting_checkout_publication($review->data()['publication_id']);
    $consent=HostingCheckoutConsent::fromJson($row['consent_json'],$publication);$c=$consent->data();
    if ($consent->review()->json()!==$review->json() || $consent->fingerprint()!==$row['consent_sha256'] || $consent->inputHash()!==$row['input_sha256']) hosting_checkout_recovery();
    $subscriptionRow=service_plans_row($row['agreement_id'],true,true);$subscription=service_plans_validate($subscriptionRow,true);
    $initial=ServiceSubscriptionDocument::fromJson($subscriptionRow['initial_json']);
    $family=recurring_billing_family($row['agreement_id']);
    if ($family['count']<1) hosting_checkout_recovery();
    $scheduleRow=recurring_billing_row($row['schedule_id']);$currentSchedule=recurring_billing_validate($scheduleRow,$subscription);
    $schedule=RecurringBillingSchedule::fromJson($scheduleRow['initial_json']);
    if ((int)$scheduleRow['generation']!==1 || $scheduleRow['previous_id']!==null || $scheduleRow['previous_sha256']!==null) hosting_checkout_recovery();
    $map=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE id=?',[$row['issuance_id']]);
    if (!$map) hosting_checkout_recovery();
    $invoice=recurring_billing_invoice_validate($map,$currentSchedule);
    $expected=hosting_checkout_result_document($consent,$initial,$schedule,$map);
    if (strlen($row['document_json'])>4096 || $expected!==$row['document_json'] || hash('sha256',$expected)!==$row['document_sha256']) hosting_checkout_recovery();
    $d=json_decode($expected,true,8,JSON_THROW_ON_ERROR);
    foreach (['publication_id','agreement_id','schedule_id','issuance_id','input_sha256','consent_sha256'] as $key) if ($d[$key]!==$row[$key]) hosting_checkout_recovery();
    foreach (['client_id','project_id','invoice_id'] as $key) if ($d[$key]!==hosting_catalog_integer($row[$key])) hosting_checkout_recovery();
    if ($d['request_id']!==$row['id'] || $d['accepted_at']!==hosting_catalog_integer($row['created_epoch']) || $c['client_id']!==$d['client_id']) hosting_checkout_recovery();
    recurring_billing_job($d['project_id'],$d['client_id']);hosting_checkout_assert_witness('hosting_checkout_results',$row);
    return ['document'=>$d,'invoice'=>$invoice,'publication'=>$publication];
}

/** Native format2 subscriptions cannot exist here without their original completed result. */
function hosting_checkout_assert_origins(bool $prepared): void {
    $cursor=null;
    do {
        $rows=$cursor===null?all('SELECT id,initial_json FROM '.table('service_subscriptions').' ORDER BY id LIMIT 64'):all('SELECT id,initial_json FROM '.table('service_subscriptions').' WHERE id>? ORDER BY id LIMIT 64',[$cursor]);
        foreach ($rows as $row) {
            $doc=ServiceSubscriptionDocument::fromJson($row['initial_json']);$d=$doc->data();$cursor=$row['id'];
            if ($d['format']!==2) continue;
            if (!$prepared) hosting_checkout_recovery();
            $result=one('SELECT id FROM '.table('hosting_checkout_results').' WHERE agreement_id=?',[$row['id']]);
            if (!$result || $result['id']!==$d['checkout']['request_id']) hosting_checkout_recovery();
        }
    } while (count($rows)===64);
}
/** Shared mutex + transaction, fresh history checks, no cache and no present provider credential authority. */
function hosting_checkout_ready(): bool {
    hosting_catalog_guard();hosting_checkout_namespace();
    if (!hosting_catalog_ready() || !service_plans_checkout_ready() || !recurring_billing_checkout_ready()) throw new RuntimeException('Prepare the hosting and checkout billing dependencies first.');
    recurring_billing_require();
    service_plans_checkout_validate_history(false);
    $marker=one('SELECT value FROM '.table('settings').' WHERE name=?',['hosting_checkout_version']);
    $counts=[];$rows=[];$exists=[];
    foreach (hosting_checkout_schema(config()['db']['prefix']) as $name=>$sql) {
        $counts[$name]=one('SELECT value FROM '.table('settings').' WHERE name=?',[$name.'_count']);
        $exists[$name]=hosting_checkout_exists($name);
        if ($exists[$name]) hosting_checkout_validate_schema($name,$sql);
        $rows[$name]=$exists[$name]?hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table($name))->fetchColumn(),999999999):0;
    }
    $events=hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY LEFT(action,17)=BINARY ?',['hosting_checkout.'])->fetchColumn());
    if (!$marker) {
        if (array_filter($counts)||array_sum($rows)!==0||$events!==0) hosting_checkout_recovery();
        hosting_checkout_assert_origins(false);return false;
    }
    if ($marker['value']!=='1'||$events!==1+array_sum($rows)) hosting_checkout_recovery();
    $setup=all('SELECT user_id,detail FROM '.table('audit').' WHERE BINARY action=?',['hosting_checkout.prepared']);
    if (count($setup)!==1||hosting_catalog_integer($setup[0]['user_id'])<1||$setup[0]['detail']!=='Hosting checkout storage version 1') hosting_checkout_recovery();
    foreach (hosting_checkout_tables() as $name) {
        if (!$exists[$name]||!$counts[$name]||hosting_catalog_integer($counts[$name]['value'],999999999)!==$rows[$name]) hosting_checkout_recovery();
        if (hosting_catalog_integer(query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',[hosting_checkout_action($name)])->fetchColumn())!==$rows[$name]) hosting_checkout_recovery();
        $cursor=null;$seen=0;
        do {
            $batch=$cursor===null?all('SELECT * FROM '.table($name).' ORDER BY id LIMIT 64'):all('SELECT * FROM '.table($name).' WHERE id>? ORDER BY id LIMIT 64',[$cursor]);
            foreach ($batch as $row) { if ($name==='hosting_checkout_reviews') hosting_checkout_review_row($row);else hosting_checkout_result_row($row);$cursor=$row['id'];$seen++; }
        } while (count($batch)===64);
        if ($seen!==$rows[$name]) hosting_checkout_recovery();
    }
    hosting_checkout_assert_origins(true);return true;
}

/** Required downstream origin seam. Caller must separately enforce verified receipt and owner provisioning. */
function hosting_checkout_agreement_origin(string $agreement,int $client): ?array {
    hosting_catalog_guard();service_plans_id($agreement);service_plans_actor($client);
    if (!hosting_checkout_ready()) return null;
    $row=one('SELECT * FROM '.table('hosting_checkout_results').' WHERE agreement_id=?',[$agreement]);
    if (!$row) return null;
    $result=hosting_checkout_result_row($row);
    if ($result['document']['client_id']!==$client) throw new RuntimeException('Hosting checkout history unavailable.');
    $p=$result['publication']->data();$mapping=provider_plan_catalog_document($p['mapping_json']);
    return ['request_id'=>$row['id'],'publication_id'=>$p['id'],'publication_sha256'=>$result['publication']->fingerprint(),'job_id'=>$result['document']['project_id'],
        'mapping_id'=>$mapping['id'],'mapping_json'=>$p['mapping_json'],'mapping_sha256'=>hash('sha256',$p['mapping_json']),
        'invoice_id'=>$result['document']['invoice_id'],'requires_verified_payment'=>true,'requires_owner_provisioning'=>true];
}
