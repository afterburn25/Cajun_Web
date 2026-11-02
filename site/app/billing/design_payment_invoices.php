<?php
/** One existing-portal invoice per explicitly accepted stage; no charge or progress change. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/design_payments.php';
require_once CWP_CODE_ROOT.'/database/schema/design_payment_invoices_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/design_payment_invoices_migrate.php';
function design_payment_invoices_ready():bool{return (one('SELECT value FROM '.table('settings').' WHERE name=?',['design_payment_invoices_schema_version'])['value']??'')==='1';}
function design_payment_invoice_snapshot(array $invoice):array {
    return ['id'=>(int)$invoice['id'],'project_id'=>(int)$invoice['job_id'],'reference'=>$invoice['reference'],'title'=>$invoice['title'],'amount_cents'=>(int)$invoice['amount_cents'],'currency'=>$invoice['currency'],'instructions'=>$invoice['instructions'],'created_at'=>$invoice['created_at']];
}
function design_payment_invoice_command(int $job,int $client,int $actor,string $expected,string $schedule,string $fingerprint,string $stage):string {
    return hash('sha256',design_payments_json(['project_id'=>$job,'client_id'=>$client,'staff_id'=>$actor,'expected'=>$expected,'schedule_id'=>$schedule,'schedule_sha256'=>$fingerprint,'stage_id'=>$stage]));
}
function design_payment_invoice_instructions(string $schedule,array $stage):string{return 'Agreed design payment schedule '.$schedule.'; stage '.$stage['id'].'.'.($stage['due_date']!==''?' Agreed due date: '.$stage['due_date'].'.':'').' Complete accepted terms are retained in your project financial history.';}
function design_payment_invoice_transition(array $state,array $event):array {
    $p=$event['payload'];
    if($event['actor_type']!=='staff'||$state['status']!=='accepted'||!$state['schedule']||!($state['consent_id']??null)||array_keys($p)!==['schedule_id','schedule_sha256','stage_id','consent_id','request_sha256','invoice'])throw new RuntimeException('Invalid financial invoice event.');
    $schedule=$state['schedule'];$stage=$schedule->stage($p['stage_id']);$invoice=$p['invoice'];
    if($p['schedule_id']!==$schedule->data()['id']||$p['schedule_sha256']!==$schedule->fingerprint()||$p['consent_id']!==$state['consent_id']||isset($state['issued'][$p['stage_id']])||$p['request_sha256']!==design_payment_invoice_command($event['project_id'],$event['client_id'],$event['actor_id'],$event['expected'],$p['schedule_id'],$p['schedule_sha256'],$p['stage_id'])||!is_array($invoice)||array_keys($invoice)!==['id','project_id','reference','title','amount_cents','currency','instructions','created_at'])throw new RuntimeException('Invoice consent or obligation mismatch.');
    if(!is_int($invoice['id'])||$invoice['id']<1||$invoice['id']>2147483647||$invoice['project_id']!==$event['project_id']||$invoice['amount_cents']!==$stage['amount_cents']||$invoice['currency']!=='USD'||$invoice['title']!==$stage['title']||$invoice['instructions']!==design_payment_invoice_instructions($p['schedule_id'],$stage)||!is_string($invoice['reference'])||!preg_match('/\ADP-[A-F0-9]{24}\z/',$invoice['reference'])||$invoice['created_at']!==gmdate('Y-m-d H:i:s',$event['occurred_at']))throw new RuntimeException('Invoice differs from the accepted stage.');
    foreach($state['issued']??[] as $previous)if($previous['payload']['invoice']['id']===$invoice['id'])throw new RuntimeException('An invoice cannot cover two payment stages.');
    $state['issued'][$p['stage_id']]=$event;return $state;
}
/** Every issued event must have one exact map and unchanged invoice; extra maps also refuse. */
function design_payment_invoices_validate(array $job,array $state):void {
    $issued=$state['issued']??[];
    if(!design_payment_invoices_ready()){if($issued)throw new RuntimeException('Invoice obligation storage is unavailable.');return;}
    if(!ledger_ready())throw new RuntimeException('The invoice payment ledger is unavailable.');
    $rows=all('SELECT * FROM '.table('design_payment_invoices').' WHERE job_id=? ORDER BY id LIMIT 33',[$job['id']]);
    if(count($rows)!==count($issued))throw new RuntimeException('Financial invoice bindings are incomplete.');
    foreach($rows as $row){
        $event=$issued[$row['stage_id']]??null;if(!$event)throw new RuntimeException('An invoice has no retained stage issuance.');$p=$event['payload'];$snapshot=$p['invoice'];
        if($row['id']!==$event['id']||(int)$row['job_id']!==$event['project_id']||(int)$row['client_id']!==$event['client_id']||(int)$row['staff_id']!==$event['actor_id']||$row['schedule_id']!==$p['schedule_id']||$row['schedule_sha256']!==$p['schedule_sha256']||$row['consent_id']!==$p['consent_id']||(int)$row['invoice_id']!==$snapshot['id']||$row['request_sha256']!==$p['request_sha256']||$row['invoice_sha256']!==hash('sha256',design_payments_json($snapshot)))throw new RuntimeException('Financial invoice metadata mismatch.');
        $invoice=one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$row['invoice_id']]);
        if(!$invoice||design_payment_invoice_snapshot($invoice)!==$snapshot||!in_array($invoice['status'],['issued','paid'],true)||catalog_snapshot((int)$invoice['id'])!==null)throw new RuntimeException('The issued invoice no longer matches its accepted terms.');
    }
}
/** Invoked by the existing ledger before balance/receipt/payment-request operations. */
function design_payment_invoice_guard(array $invoice):void {
    if(!design_payment_invoices_ready()){if(str_starts_with($invoice['reference'],'DP-'))throw new RuntimeException('Design invoice binding is unavailable.');return;}
    $map=one('SELECT job_id,client_id FROM '.table('design_payment_invoices').' WHERE invoice_id=?',[$invoice['id']]);
    if(!$map){if(str_starts_with($invoice['reference'],'DP-'))throw new RuntimeException('Design invoice binding is missing.');return;}
    $job=one('SELECT * FROM '.table('studio_jobs').' WHERE id=?',[$map['job_id']]);
    if(!$job||(int)$job['client_id']!==(int)$map['client_id']||(int)$invoice['job_id']!==(int)$map['job_id'])throw new RuntimeException('Design invoice ownership changed.');
    $state=design_payments_validate($job);$found=false;
    foreach($state['issued'] as $event){if($event['payload']['invoice']['id']===(int)$invoice['id']){$found=true;if(design_payment_invoice_snapshot($invoice)!==$event['payload']['invoice']||(isset($invoice['client_id'])&&(int)$invoice['client_id']!==(int)$map['client_id']))throw new RuntimeException('The reviewed invoice does not match its accepted stage.');}}
    if(!$found)throw new RuntimeException('Retained invoice issuance is missing.');
}
function design_payments_issue(int $job,int $client,string $request,string $expected,string $schedule,string $fingerprint,string $stage):array {
    project_progress_id($request);project_progress_id($schedule);project_progress_id($stage);
    if(!preg_match('/\A[a-f0-9]{64}\z/',$expected)||!preg_match('/\A[a-f0-9]{64}\z/',$fingerprint))throw new InvalidArgumentException('Keep the reviewed financial version.');
    return project_progress_locked(function()use($job,$client,$request,$expected,$schedule,$fingerprint,$stage):array{
        $actor=design_payments_staff();if(!design_payment_invoices_ready()||!ledger_ready())throw new RuntimeException('An owner must prepare invoice obligations first.');
        project_progress_client_row($client);$row=project_progress_job($job,$client);$state=design_payments_validate($row);$command=design_payment_invoice_command($job,$client,$actor['id'],$expected,$schedule,$fingerprint,$stage);
        $prior=one('SELECT * FROM '.table('design_payment_invoices').' WHERE id=?',[$request]);
        if($prior){if((int)$prior['job_id']!==$job||!hash_equals($prior['request_sha256'],$command))throw new RuntimeException('This invoice request was used with different details.');return ['job_id'=>$job,'event_id'=>$request,'invoice_id'=>(int)$prior['invoice_id'],'replayed'=>true];}
        if(one('SELECT id FROM '.table('design_payment_events').' WHERE id=?',[$request]))throw new RuntimeException('This financial request was already used.');
        if($state['status']!=='accepted'||!$state['schedule']||$state['schedule']->data()['id']!==$schedule||$state['schedule']->fingerprint()!==$fingerprint||!hash_equals($state['expected'],$expected)||isset($state['issued'][$stage]))throw new RuntimeException('Review the current accepted and unissued payment stage.');
        design_payments_source($row,$state['schedule']);$allocation=$state['schedule']->stage($stage);$occurredAt=time();$now=gmdate('Y-m-d H:i:s',$occurredAt);
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',[$job,'DP-'.strtoupper(bin2hex(random_bytes(12))),$allocation['title'],$allocation['amount_cents'],'USD',design_payment_invoice_instructions($schedule,$allocation),$now]);$invoice=(int)db()->lastInsertId();
        $snapshot=design_payment_invoice_snapshot(one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$invoice]));
        $payload=['schedule_id'=>$schedule,'schedule_sha256'=>$fingerprint,'stage_id'=>$stage,'consent_id'=>$state['consent_id'],'request_sha256'=>$command,'invoice'=>$snapshot];
        $result=design_payments_append($row,$actor,$request,$expected,'issued',$payload,$occurredAt);
        query('INSERT INTO '.table('design_payment_invoices').' (id,job_id,client_id,staff_id,schedule_id,schedule_sha256,stage_id,consent_id,invoice_id,invoice_sha256,request_sha256) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$request,$job,$client,$actor['id'],$schedule,$fingerprint,$stage,$state['consent_id'],$invoice,hash('sha256',design_payments_json($snapshot)),$command]);
        return $result+['invoice_id'=>$invoice];
    });
}
