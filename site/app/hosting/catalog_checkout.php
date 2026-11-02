<?php
/** Authenticated client confirmation creates one local unpaid invoice, never a charge or provider request. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_offers_context.php';
require_once __DIR__.'/catalog_checkout_storage.php';
require_once CWP_CODE_ROOT.'/database/migrations/hosting_checkout_migrate.php';

use CajunWebPro\Foundation\RecurringBilling\RecurringBillingSchedule;

function hosting_checkout_reject_contact(): void {
    // Exact established contact-session keys also carry the parent client identity; neither is primary consent.
    if (array_key_exists('contact_id',$_SESSION) || array_key_exists('contact_auth',$_SESSION)) throw new RuntimeException('Sign in as the primary client to confirm hosting terms.');
}
function hosting_checkout_primary_client(int $client): array {
    hosting_checkout_reject_contact();hosting_catalog_guard();
    return service_plans_current_client($client);
}
function hosting_checkout_locked(callable $work): mixed {
    // Capture contact provenance before any authority helper can normalize a revoked session.
    hosting_checkout_reject_contact();
    if (db()->inTransaction()) throw new LogicException('Hosting checkout cannot inherit a transaction.');
    return staff_locked(function()use($work):mixed {
        unset($GLOBALS['cwp_settings']);
        return staff_transaction($work);
    });
}
function hosting_checkout_require(): void {
    if (!hosting_checkout_ready()) throw new RuntimeException('An owner must prepare hosting checkout first.');
}
/** Review project facts are locked server-side. New-hosting is an instruction, not a fabricated project. */
function hosting_checkout_project_facts(array $project,int $client): array {
    $project=hosting_checkout_project($project);
    if ($project['mode']==='new-hosting') return [null,null];
    $row=recurring_billing_job($project['id'],$client);return [(int)$row['id'],(int)$row['client_id']];
}
function hosting_checkout_preview(string $request,string $publicationId,string $publicationHash,int $client,array $project): array {
    service_plans_id($request);service_plans_id($publicationId);hosting_catalog_hash($publicationHash);$project=hosting_checkout_project($project);
    return hosting_checkout_locked(function()use($request,$publicationId,$publicationHash,$client,$project):array {
        hosting_checkout_primary_client($client);hosting_checkout_require();
        $publication=hosting_checkout_publication($publicationId);
        if (!hash_equals($publication->fingerprint(),$publicationHash)) throw new RuntimeException('Review the current hosting offer.');
        $scope=hosting_catalog_read_scope([$publication->data()['product']['id']]);$context=hosting_catalog_current_context($publication,$scope);
        [$projectId,$projectClient]=hosting_checkout_project_facts($project,$client);$at=time();
        $old=one('SELECT * FROM '.table('hosting_checkout_reviews').' WHERE id=? FOR UPDATE',[$request]);
        if ($old) {
            $review=hosting_checkout_review_row($old);$r=$review->data();
            if ($r['client_id']!==$client || $r['publication_id']!==$publicationId || $r['publication_sha256']!==$publicationHash || $r['project']!==$project) throw new InvalidArgumentException('This checkout request was already used with different details.');
            if (one('SELECT id FROM '.table('hosting_checkout_results').' WHERE id=?',[$request])) throw new RuntimeException('This checkout is already complete. Open its retained invoice.');
            return $review->publicProjection($context,$client,$projectId,$projectClient,$at);
        }
        $review=HostingCheckoutReview::create($request,$publication,$client,$project,$at,$context);
        $safe=$review->publicProjection($context,$client,$projectId,$projectClient,$at);$r=$review->data();
        query('INSERT INTO '.table('hosting_checkout_reviews').' (id,publication_id,publication_sha256,client_id,project_id,created_epoch,expires_epoch,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$request,$publicationId,$publicationHash,$client,$projectId,$at,$r['expires_at'],$review->json(),$review->fingerprint()]);
        hosting_checkout_recorded('hosting_checkout_reviews',['id'=>$request,'client_id'=>$client,'created_epoch'=>$at,'document_sha256'=>$review->fingerprint()]);
        hosting_checkout_require();return $safe;
    });
}
function hosting_checkout_projection(array $validated,bool $replayed): array {
    $d=$validated['document'];$i=$validated['invoice'];
    return ['request_id'=>$d['request_id'],'project_id'=>$d['project_id'],'agreement_id'=>$d['agreement_id'],'schedule_id'=>$d['schedule_id'],
        'invoice_id'=>$d['invoice_id'],'invoice_reference'=>$i['reference'],'invoice_status'=>$i['status'],'amount_cents'=>(int)$i['amount_cents'],'currency'=>$i['currency'],
        'accepted_at'=>$d['accepted_at'],'replayed'=>$replayed,'automatic_charge'=>false,'provider_activation'=>false];
}
function hosting_checkout_result(string $request,int $client): array {
    service_plans_id($request);
    return hosting_checkout_locked(function()use($request,$client):array {
        hosting_checkout_primary_client($client);hosting_checkout_require();
        $row=one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=? AND client_id=?',[$request,$client]);
        if (!$row) throw new RuntimeException('Hosting checkout history unavailable.');
        return hosting_checkout_projection(hosting_checkout_result_row($row),true);
    });
}
function hosting_checkout_confirm(string $request,string $reviewHash,int $client,bool $confirmed): array {
    service_plans_id($request);hosting_catalog_hash($reviewHash);
    if (!$confirmed) throw new InvalidArgumentException('Confirm the exact service and invoice terms.');
    return hosting_checkout_locked(function()use($request,$reviewHash,$client):array {
        hosting_checkout_primary_client($client);hosting_checkout_require();
        $row=one('SELECT * FROM '.table('hosting_checkout_reviews').' WHERE id=? AND client_id=? FOR UPDATE',[$request,$client]);
        if (!$row) throw new RuntimeException('Hosting checkout review unavailable.');
        $review=hosting_checkout_review_row($row);
        if (!hash_equals($review->fingerprint(),$reviewHash)) throw new InvalidArgumentException('Confirm the exact retained review.');
        $old=one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=?',[$request]);
        if ($old) {
            if ((int)$old['client_id']!==$client) hosting_checkout_recovery();
            return hosting_checkout_projection(hosting_checkout_result_row($old),true);
        }
        $publication=hosting_checkout_publication($row['publication_id']);$r=$review->data();
        $scope=hosting_catalog_read_scope([$publication->data()['product']['id']]);$context=hosting_catalog_current_context($publication,$scope);
        [$projectId,$projectClient]=hosting_checkout_project_facts($r['project'],$client);
        // Every persisted business event below uses this one accepted instant.
        $at=time();$consent=HostingCheckoutConsent::create($review,$reviewHash,true,$publication,$context,$client,$projectId,$projectClient,$at);
        if ($projectId===null) {
            $offer=$review->historicalProjection()['offer'];$utc=gmdate('Y-m-d H:i:s',$at);
            query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$client,$offer['title'],'Dedicated hosting project for checkout '.$request,'discovery','',$utc,$utc]);
            $projectId=(int)db()->lastInsertId();recurring_billing_job($projectId,$client);
        }
        $agreement=bin2hex(random_bytes(16));$scheduleId=bin2hex(random_bytes(16));$issuance=bin2hex(random_bytes(16));
        $subscription=ServiceSubscriptionDocument::createCheckout($agreement,$consent,$publication,$projectId,bin2hex(random_bytes(16)),bin2hex(random_bytes(16)));
        $s=$subscription->data();$plan=ServicePlanDocument::fromJson($publication->data()['plan_json']);
        query('INSERT INTO '.table('service_subscriptions').' (id,plan_id,plan_revision,plan_sha256,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision,created_by_client) VALUES (?,?,?,?,?,NULL,?,?,?,?,?,?)',[$agreement,$plan->data()['id'],$plan->revision(),$plan->fingerprint(),$client,$at,$subscription->json(),$subscription->json(),$subscription->fingerprint(),2,$client]);
        $prefix=$s;$prefix['events']=[];$before=hash('sha256',service_plans_json($prefix));
        foreach ($s['events'] as $index=>$event) {
            $prefix['events'][]=$event;$after=hash('sha256',service_plans_json($prefix));
            $input=service_plans_json(['revision'=>$index,'command'=>$event['command'],'arguments'=>$event['arguments']]);
            query('INSERT INTO '.table('service_subscription_events').' (id,subscription_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256,actor_system) VALUES (?,?,NULL,?,?,?,?,?,?,?)',[$event['id'],$agreement,$index===0?$client:null,$index+1,$at,$input,$before,$after,$index===1?'hosting-checkout':null]);
            $before=$after;
        }
        $terms=hosting_checkout_billing_terms($scheduleId,$subscription);$schedule=RecurringBillingSchedule::createCheckout($terms,bin2hex(random_bytes(16)));
        $b=$schedule->data();$event=$b['events'][0];$zero=$b;$zero['events']=[];
        query('INSERT INTO '.table('recurring_billing_sources').' (id,latest_id,latest_sha256,generations) VALUES (?,?,?,1)',[$agreement,$scheduleId,$schedule->fingerprint()]);
        query('INSERT INTO '.table('recurring_billing_schedules').' (id,subscription_id,generation,client_id,job_id,created_by,created_epoch,previous_id,previous_sha256,initial_json,journal_json,journal_sha256,revision,created_by_client) VALUES (?,?,1,?,?,NULL,?,NULL,NULL,?,?,?,1,?)',[$scheduleId,$agreement,$client,$projectId,$at,$schedule->json(),$schedule->json(),$schedule->fingerprint(),$client]);
        query('INSERT INTO '.table('recurring_billing_events').' (id,schedule_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,NULL,?,1,?,?,?,?)',[$event['id'],$scheduleId,$client,$at,recurring_billing_json(['revision'=>0,'command'=>$event['command'],'arguments'=>$event['arguments']]),hash('sha256',recurring_billing_json($zero)),$schedule->fingerprint()]);
        $assessment=$schedule->assessment(0,$at);
        if (!$assessment['eligible']) throw new RuntimeException('The confirmed first billing period is unavailable.');
        $t=$terms->data();$period=$assessment['period'];$utc=gmdate('Y-m-d H:i:s',$at);
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,status,instructions,created_at) VALUES (?,?,?,?,?,?,?,?)',[$projectId,'RI-'.strtoupper(bin2hex(random_bytes(12))),$t['title'],$period['amount_cents'],$period['currency'],'issued',recurring_billing_invoice_instructions($scheduleId,$period),$utc]);
        $invoiceId=(int)db()->lastInsertId();$invoice=one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$invoiceId]);
        $snapshot=recurring_billing_json(['period'=>$period,'invoice'=>recurring_billing_invoice_snapshot($invoice)]);
        $map=['id'=>$issuance,'schedule_id'=>$scheduleId,'period_index'=>0,'invoice_id'=>$invoiceId,'staff_id'=>null,'issued_epoch'=>$at,'schedule_revision'=>1,
            'schedule_sha256'=>$schedule->fingerprint(),'request_sha256'=>recurring_billing_invoice_input($scheduleId,$client,1,$schedule->fingerprint(),0,'client:'.$client),
            'snapshot_json'=>$snapshot,'snapshot_sha256'=>hash('sha256',$snapshot),'client_id'=>$client];
        query('INSERT INTO '.table('recurring_billing_invoices').' (id,schedule_id,period_index,invoice_id,staff_id,issued_epoch,schedule_revision,schedule_sha256,request_sha256,snapshot_json,snapshot_sha256,client_id) VALUES (?,?,0,?,NULL,?,1,?,?,?,?,?)',[$issuance,$scheduleId,$invoiceId,$at,$map['schedule_sha256'],$map['request_sha256'],$snapshot,$map['snapshot_sha256'],$client]);
        query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (0,?,?,?)',['recurring_billing.checkout_issued',recurring_billing_checkout_invoice_detail($map,$schedule),$utc]);
        $document=hosting_checkout_result_document($consent,$subscription,$schedule,$map);$documentHash=hash('sha256',$document);
        query('INSERT INTO '.table('hosting_checkout_results').' (id,publication_id,client_id,project_id,agreement_id,schedule_id,invoice_id,issuance_id,created_epoch,input_sha256,consent_json,consent_sha256,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$request,$r['publication_id'],$client,$projectId,$agreement,$scheduleId,$invoiceId,$issuance,$at,$consent->inputHash(),$consent->json(),$consent->fingerprint(),$document,$documentHash]);
        hosting_checkout_recorded('hosting_checkout_results',['id'=>$request,'client_id'=>$client,'created_epoch'=>$at,'document_sha256'=>$documentHash]);
        // No format2 native reader is invoked while the graph is incomplete.
        hosting_checkout_require();
        $result=one('SELECT * FROM '.table('hosting_checkout_results').' WHERE id=?',[$request]);
        return hosting_checkout_projection(hosting_checkout_result_row($result),false);
    });
}
