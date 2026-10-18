<?php
/** Permanent whole-period obligations in the existing invoice ledger. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};
function recurring_billing_invoice_snapshot(array $i):array {
    return ['id'=>(int)$i['id'],'project_id'=>(int)$i['job_id'],'reference'=>$i['reference'],'title'=>$i['title'],'amount_cents'=>(int)$i['amount_cents'],'currency'=>$i['currency'],'instructions'=>$i['instructions'],'created_at'=>$i['created_at']];
}
function recurring_billing_invoice_input(string $id,int $client,int $revision,string $hash,int $period,string $actor):string {
    return hash('sha256',recurring_billing_json(['schedule_id'=>$id,'client_id'=>$client,'revision'=>$revision,'sha256'=>$hash,'period_index'=>$period,'actor'=>$actor]));
}
function recurring_billing_invoice_instructions(string $id,array $period):string {
    return 'Agreed recurring invoice '.$id.'; period '.$period['index'].': '.$period['start'].' to '.$period['end'].' (end exclusive); timezone '.$period['timezone'].'. Due '.$period['due'].'. Full agreed billing terms are retained in your client account.';
}
/** The append-only issuance audit is an independent origin retained with every invoice.
 * Its exact structured detail predates this correction and is written atomically with
 * the map/invoice. Do not prune recurring_billing.issued history or infer origin from
 * editable display fields. A missing or conflicting origin requires DB restoration.
 */
function recurring_billing_invoice_origin(int $invoice):?array {
    if($invoice<1)throw new InvalidArgumentException('Choose an existing invoice.');
    $rows=all('SELECT user_id,detail,created_at FROM '.table('audit').' WHERE action=? AND detail LIKE ? ORDER BY id LIMIT 2',['recurring_billing.issued','% invoice '.$invoice]);
    if(!$rows)return null;
    if(count($rows)!==1||!preg_match('/\Astaff:([1-9][0-9]*); Schedule ([a-f0-9]{32}) period (0|[1-9][0-9]*) invoice ([1-9][0-9]*)\z/',$rows[0]['detail'],$m)||(int)$m[1]!== (int)$rows[0]['user_id']||(int)$m[4]!==$invoice)throw new RuntimeException('Recurring invoice origin history is inconsistent. Restore the original database.');
    return ['staff_id'=>(int)$m[1],'schedule_id'=>$m[2],'period_index'=>(int)$m[3],'created_at'=>$rows[0]['created_at']];
}
function recurring_billing_invoice_validate(array $map,RecurringBillingSchedule $current):array {
    $origin=recurring_billing_invoice_origin((int)$map['invoice_id']);
    if($origin===null||$origin['staff_id']!==(int)$map['staff_id']||$origin['schedule_id']!==$map['schedule_id']||$origin['period_index']!==(int)$map['period_index'])throw new RuntimeException('The recurring invoice has no matching original issuance history. Restore the original database.');
    $data=$current->data();$revision=(int)$map['schedule_revision'];
    if($data['terms']['id']!==$map['schedule_id']||$revision<1||$revision>$current->revision())throw new RuntimeException('The recurring invoice has no retained agreement prefix.');
    $data['events']=array_slice($data['events'],0,$revision);$prefix=RecurringBillingSchedule::fromJson(recurring_billing_json($data));
    if($prefix->fingerprint()!==$map['schedule_sha256'])throw new RuntimeException('The invoiced agreement prefix changed.');
    $assessment=$prefix->assessment((int)$map['period_index'],(int)$map['issued_epoch']);$p=$assessment['period'];$t=$data['terms'];
    if(!$assessment['eligible']||$map['request_sha256']!==recurring_billing_invoice_input($map['schedule_id'],$t['client_id'],$revision,$prefix->fingerprint(),(int)$map['period_index'],'staff:'.$map['staff_id']))throw new RuntimeException('The recurring invoice lacks its eligible agreed period.');
    if(strlen($map['snapshot_json'])>16384||hash('sha256',$map['snapshot_json'])!==$map['snapshot_sha256'])throw new RuntimeException('The recurring invoice snapshot changed.');
    $snapshot=json_decode($map['snapshot_json'],true,8,JSON_THROW_ON_ERROR);
    if(!is_array($snapshot)||array_keys($snapshot)!==['period','invoice']||$snapshot['period']!==$p||!is_array($snapshot['invoice'])||recurring_billing_json($snapshot)!==$map['snapshot_json'])throw new RuntimeException('The recurring invoice period changed.');
    $i=one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$map['invoice_id']]);
    if(!$i||!in_array($i['status'],['issued','paid'],true)||catalog_snapshot((int)$i['id'])!==null||recurring_billing_invoice_snapshot($i)!==$snapshot['invoice']||(int)$i['job_id']!==$t['project_id']||$i['title']!==$t['title']||(int)$i['amount_cents']!==$p['amount_cents']||$i['currency']!==$p['currency']||$i['created_at']!==gmdate('Y-m-d H:i:s',(int)$map['issued_epoch'])||$i['instructions']!==recurring_billing_invoice_instructions($map['schedule_id'],$p)||!preg_match('/\ARI-[A-F0-9]{24}\z/',$i['reference']))throw new RuntimeException('The invoice no longer matches its agreed recurring obligation.');
    return $i;
}
function recurring_billing_invoice_guard(array $invoice):void {
    if(!recurring_billing_ready()){
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['recurring_billing_schema_version'])['value']??'';
        // A missing established mapping can no longer prove which invoices were recurring.
        // Preserve legacy behavior only before this feature was ever prepared.
        $mapped=false;
        if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'recurring_billing_invoices']))$mapped=one('SELECT id FROM '.table('recurring_billing_invoices').' WHERE invoice_id=?',[$invoice['id']])!==null;
        if($version!==''||$mapped||recurring_billing_invoice_origin((int)$invoice['id'])!==null||str_starts_with($invoice['reference'],'RI-'))throw new RuntimeException('Recurring invoice storage is unavailable. Restore the original database if it was previously prepared.');
        return;
    }
    $work=function()use($invoice):void{
        $map=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE invoice_id=?',[$invoice['id']]);
        if(!$map){if(recurring_billing_invoice_origin((int)$invoice['id'])!==null||str_starts_with($invoice['reference'],'RI-'))throw new RuntimeException('The recurring invoice binding is missing.');return;}
        recurring_billing_require();
        $row=recurring_billing_row($map['schedule_id']);$family=recurring_billing_family($row['subscription_id']);$doc=recurring_billing_validate($row,$family['source']);$i=recurring_billing_invoice_validate($map,$doc);
        if(recurring_billing_invoice_snapshot($invoice)!==recurring_billing_invoice_snapshot($i)||(isset($invoice['client_id'])&&(int)$invoice['client_id']!==(int)$row['client_id']))throw new RuntimeException('The reviewed recurring invoice ownership changed.');
    };
    if(db()->inTransaction())$work();else staff_locked(fn()=>staff_transaction($work));
}
function recurring_billing_issue(string $id,int $client,int $revision,string $hash,int $period,string $request):array {
    service_plans_id($request);if(!preg_match('/\A[a-f0-9]{64}\z/',$hash))throw new InvalidArgumentException('Keep the reviewed recurring agreement fingerprint.');
    $result=recurring_billing_locked(function()use($id,$client,$revision,$hash,$period,$request):array{
        $actor=recurring_billing_staff();service_plans_client_row($client);$row=recurring_billing_row($id);
        if((int)$row['client_id']!==$client)throw new RuntimeException('Recurring agreement unavailable.');
        $family=recurring_billing_family($row['subscription_id']);$doc=recurring_billing_validate($row,$family['source']);
        $input=recurring_billing_invoice_input($id,$client,$revision,$hash,$period,$actor);$prior=one('SELECT * FROM '.table('recurring_billing_invoices').' WHERE id=?',[$request]);
        if($prior){
            if($prior['schedule_id']!==$id||$prior['request_sha256']!==$input)throw new RuntimeException('This invoice request was used with different details.');
            $invoice=recurring_billing_invoice_validate($prior,$doc);return ['invoice_id'=>(int)$invoice['id'],'replayed'=>true];
        }
        if($doc->revision()!==$revision||$doc->fingerprint()!==$hash)throw new RuntimeException('Review the current recurring agreement before issuing an invoice.');
        $terms=RecurringBillingTerms::fromArray($doc->data()['terms']);recurring_billing_source($terms,true,false,$family['source']);
        $at=time();$a=$doc->assessment($period,$at);if(!$a['eligible'])throw new RuntimeException('This agreed period is not eligible for invoicing.');
        if(one('SELECT id FROM '.table('recurring_billing_invoices').' WHERE schedule_id=? AND period_index=?',[$id,$period]))throw new RuntimeException('This recurring period already has an invoice.');
        if(one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ? LIMIT 1',['recurring_billing.issued','staff:%; Schedule '.$id.' period '.$period.' invoice %']))throw new RuntimeException('This recurring period has retained issuance history without its binding. Restore the original database before continuing.');
        $t=$terms->data();$p=$a['period'];query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',[$t['project_id'],'RI-'.strtoupper(bin2hex(random_bytes(12))),$t['title'],$p['amount_cents'],$p['currency'],recurring_billing_invoice_instructions($id,$p),gmdate('Y-m-d H:i:s',$at)]);
        $invoice=(int)db()->lastInsertId();$snapshot=recurring_billing_json(['period'=>$p,'invoice'=>recurring_billing_invoice_snapshot(one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$invoice]))]);
        query('INSERT INTO '.table('recurring_billing_invoices').' (id,schedule_id,period_index,invoice_id,staff_id,issued_epoch,schedule_revision,schedule_sha256,request_sha256,snapshot_json,snapshot_sha256) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$request,$id,$period,$invoice,(int)substr($actor,6),$at,$revision,$hash,$input,$snapshot,hash('sha256',$snapshot)]);
        recurring_billing_audit($actor,'issued','Schedule '.$id.' period '.$period.' invoice '.$invoice);return ['invoice_id'=>$invoice,'replayed'=>false];
    });
    if(!$result['replayed']){require_once CWP_CODE_ROOT.'/app/notifications/feed.php';try{$inv=one('SELECT reference,title FROM '.table('studio_invoices').' WHERE id=?',[$result['invoice_id']]);if($inv)notification_emit('client',$client,'billing','invoice:'.$result['invoice_id'],'Recurring invoice issued',$inv['reference'].' · '.$inv['title']);}catch(Throwable $e){report_error($e);}}
    return $result;
}
