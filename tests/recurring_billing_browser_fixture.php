<?php
/** Explicit synthetic historical agreement for real HTTP invoice controls; never shipped. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||count($argv)!==4)exit(2);
$root=realpath($argv[1]);if(!$root||!is_file($root.'/config.php'))exit(2);
define('CWP_BOOT',true);require $root.'/includes/bootstrap.php';require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};
$GLOBALS['cwp_config']=require $root.'/config.php';if(config()['db']['name']!=='cwp_test')exit(2);
$client=(int)$argv[2];$job=(int)$argv[3];$owner=one('SELECT user_id FROM '.table('staff_profiles')." WHERE role='owner' AND enabled=1 ORDER BY user_id LIMIT 1");if(!$owner)exit(2);$staff=(int)$owner['user_id'];
if(!one('SELECT id FROM '.table('studio_jobs').' WHERE id=? AND client_id=?',[$job,$client]))exit(2);
recurring_billing_require();$at=1570000000;$id=fn()=>bin2hex(random_bytes(16));
$planId=$id();$sourceId=$id();$scheduleId=$id();
$p0=ServicePlanDocument::create($planId,$staff,$at,['name'=>'Synthetic historical care','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>['Synthetic historical service'],'exclusions'=>[],'limits'=>[],'support'=>'Synthetic fixture only']);$p=$p0->append($id(),'activate',$staff,$at+1,[]);
$s0=ServiceSubscriptionDocument::create($sourceId,$client,$staff,$at+2,$p);$s1=$s0->append($id(),'consent','client:'.$client,$at+3,['terms_hash'=>$p->model()->termsHash()]);$s=$s1->append($id(),'activate','staff:'.$staff,$at+4,[]);
$t=RecurringBillingTerms::fromArray(['format'=>1,'id'=>$scheduleId,'client_id'=>$client,'project_id'=>$job,'source_subscription'=>['id'=>$sourceId,'revision'=>2,'sha256'=>$s->fingerprint()],'title'=>'Synthetic historical invoice agreement','terms'=>'Synthetic historical consent for HTTP acceptance only. No merchant or customer payment.','currency'=>'USD','amount_cents'=>2500,'cadence'=>'monthly','anchor'=>'2020-02-01','alignment'=>'day-of-month','timezone'=>'America/Chicago','due_after_days'=>14,'collection'=>'invoice-only']);
$d0=RecurringBillingSchedule::create($t,$staff,1577836800);$d1=$d0->append($id(),'accept','client:'.$client,1577923200,['terms_sha256'=>$t->fingerprint()]);$d=$d1->append($id(),'activate','staff:'.$staff,1578009600,['period_index'=>0]);
staff_locked(fn()=>staff_transaction(function()use($p0,$p,$s0,$s1,$s,$d0,$d1,$d,$at,$staff,$client,$job,$planId,$sourceId,$scheduleId):void{
    query('INSERT INTO '.table('service_plans').' (id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,?,?,?,?,1)',[$planId,$staff,$at,$p0->json(),$p->json(),$p->fingerprint()]);
    $event=$p->data()['events'][0];query('INSERT INTO '.table('service_plan_events').' (id,plan_id,actor_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,1,?,?,?,?)',[$event['id'],$planId,$staff,$at+1,service_plans_json(['revision'=>0,'command'=>'activate','arguments'=>[]]),$p0->fingerprint(),$p->fingerprint()]);
    query('INSERT INTO '.table('service_subscriptions').' (id,plan_id,plan_revision,plan_sha256,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,1,?,?,?,?,?,?,?,2)',[$sourceId,$planId,$p->fingerprint(),$client,$staff,$at+2,$s0->json(),$s->json(),$s->fingerprint()]);
    foreach([[$s0,$s1,null,$client],[$s1,$s,$staff,null]] as [$before,$after,$actorStaff,$actorClient]){$e=$after->data()['events'][array_key_last($after->data()['events'])];query('INSERT INTO '.table('service_subscription_events').' (id,subscription_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$e['id'],$sourceId,$actorStaff,$actorClient,$after->revision(),$e['at'],service_plans_json(['revision'=>$before->revision(),'command'=>$e['command'],'arguments'=>$e['arguments']]),$before->fingerprint(),$after->fingerprint()]);}
    query('INSERT INTO '.table('recurring_billing_sources').' (id,latest_id,latest_sha256,generations) VALUES (?,?,?,1)',[$sourceId,$scheduleId,$d->fingerprint()]);
    query('INSERT INTO '.table('recurring_billing_schedules').' (id,subscription_id,generation,client_id,job_id,created_by,created_epoch,previous_id,previous_sha256,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,1,?,?,?,?,NULL,NULL,?,?,?,2)',[$scheduleId,$sourceId,$client,$job,$staff,1577836800,$d0->json(),$d->json(),$d->fingerprint()]);
    foreach([[$d0,$d1,null,$client],[$d1,$d,$staff,null]] as [$before,$after,$actorStaff,$actorClient]){$e=$after->data()['events'][array_key_last($after->data()['events'])];query('INSERT INTO '.table('recurring_billing_events').' (id,schedule_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$e['id'],$scheduleId,$actorStaff,$actorClient,$after->revision(),$e['at'],recurring_billing_json(['revision'=>$before->revision(),'command'=>$e['command'],'arguments'=>$e['arguments']]),$before->fingerprint(),$after->fingerprint()]);}
    recurring_billing_validate(recurring_billing_row($scheduleId));
}));
echo json_encode(['schedule_id'=>$scheduleId,'source_id'=>$sourceId],JSON_THROW_ON_ERROR);
