<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT'))exit(2);

$oldBody=one('SELECT body FROM '.table('studio_versions').' WHERE id=1')['body'];query('UPDATE '.table('studio_versions').' SET body=? WHERE id=1',[$oldBody.' changed']);
dp_ok('Historical financial terms remain readable after proposal changes',dp_view()['schedule']['id']===dp_id(4));
dp_ok('Original offer retry does not reauthorize changed proposal or duplicate action',design_payments_publish(1,1,dp_id(1),$initial,$fields)['replayed']);
$bad=dp_fields(6);$bad['proposal']['content_sha256']=$fields['proposal']['content_sha256'];dp_deny('New offer cannot retain a changed proposal digest',fn()=>design_payments_publish(1,1,dp_id(6),dp_view()['expected'],$bad));query('UPDATE '.table('studio_versions').' SET body=? WHERE id=1',[$oldBody]);
query('INSERT INTO '.table('studio_versions')." (job_id,kind,version_no,title,body,preview_url,decision,feedback,decided_by,decided_at,created_at) VALUES (1,'proposal',2,'Replacement proposal','Replacement scope','','approved','',1,'2025-03-01 02:00:00','2025-03-01 01:00:00')");
$bad=dp_fields(6);$bad['proposal']=$fields['proposal'];dp_deny('Superseded approved proposal cannot support new offer',fn()=>design_payments_publish(1,1,dp_id(6),dp_view()['expected'],$bad));
$fresh=dp_fields(6);$freshExpected=dp_view()['expected'];design_payments_publish(1,1,dp_id(6),$freshExpected,$fresh);$freshOffer=dp_view();
query('UPDATE '.table('studio_versions').' SET decided_by=2 WHERE id=?',[$fresh['proposal']['id']]);dp_client();dp_deny('Financial consent requires proposal approval by this client',fn()=>dp_decide(7,$freshOffer));query('UPDATE '.table('studio_versions').' SET decided_by=1 WHERE id=?',[$fresh['proposal']['id']]);
$before=dp_snapshot();dp_ok('Client event persistence failure rolls back without a receipt',dp_failure(fn()=>dp_decide(7,$freshOffer),'design_payment_events')&&dp_snapshot()===$before);dp_staff();

project_planning_migrate($password);$plan=['scope'=>'Exact milestone scope','assigned_contact'=>'Studio','target_date'=>null,'client_action'=>'Review','blockers'=>'','milestones'=>[['id'=>dp_id(9005),'title'=>'Design review','details'=>'Review the design','state'=>'planned','owner'=>'studio','due_date'=>null,'depends_on'=>[]]]];
project_planning_save(3,1,dp_id(301),project_planning_staff_get(3)['expected'],$plan);
$milestone=dp_fields(30,3);$milestone['stages']=[['id'=>dp_id(9010),'kind'=>'milestone','title'=>'Design milestone','calculation'=>'fixed','value'=>2500,'due_date'=>'','milestone_id'=>dp_id(9005)],['id'=>dp_id(9011),'kind'=>'final','title'=>'Final balance','calculation'=>'remainder','value'=>0,'due_date'=>'','milestone_id'=>'']];
$milestoneInitial=dp_view(3)['expected'];$bad=$milestone;$bad['stages'][0]['milestone_id']=dp_id(9999);dp_deny('Foreign or nonexistent milestone refuses a financial offer',fn()=>design_payments_publish(3,1,dp_id(30),$milestoneInitial,$bad));
design_payments_publish(3,1,dp_id(30),$milestoneInitial,$milestone);$milestoneOffer=dp_view(3);dp_ok('Current project milestone binds its quoted installment',$milestoneOffer['stages'][0]['amount_cents']===2500&&$milestoneOffer['stages'][1]['amount_cents']===7501);
$plan['milestones'][0]['state']='cancelled';project_planning_save(3,1,dp_id(302),project_planning_staff_get(3)['expected'],$plan);dp_client();
dp_deny('Cancelled milestone cannot receive new financial consent',fn()=>design_payments_decide(3,1,dp_id(31),$milestoneOffer['expected'],$milestone['id'],$milestoneOffer['fingerprint'],'accepted'));
dp_ok('Cancelled milestone does not rewrite historical financial terms',design_payments_client_get(3,1)['schedule']===$milestone);dp_staff();
design_payments_withdraw(3,1,dp_id(31),$milestoneOffer['expected'],$milestone['id'],$milestoneOffer['fingerprint'],'Milestone cancelled');dp_ok('Staff may withdraw stale-source offer without changing project facts',dp_view(3)['status']==='withdrawn');

save_setting('support_policy_confirmed','1');support_policy(1,true,$password);dp_staff(5);$support=support_start(1);$_GET['support']=$support;
dp_ok('Explicit scoped support can inspect client financial history',design_payments_client_get(1,1,0,$support)['schedule']===$fresh);
dp_deny('Support requires its explicit scoped read context',fn()=>design_payments_client_get(1,1));dp_deny('Support cannot inspect another client financial terms',fn()=>design_payments_client_get(2,2,0,$support));
dp_deny('Support cannot impersonate client financial acceptance',fn()=>dp_decide(7,$freshOffer));dp_deny('Support cannot publish financial offers',fn()=>design_payments_publish(1,1,dp_id(8),$freshOffer['expected'],dp_fields(8)));support_end($support);dp_staff();

$parallel=dp_fields(40,4);$parallelExpected=dp_view(4)['expected'];
$workers=staff_locked(function()use($parallel,$parallelExpected):array{
    $a=dp_worker('publish',1,4,1,40,$parallelExpected,$parallel);$b=dp_worker('publish',1,4,1,40,$parallelExpected,$parallel);
    dp_ok('Parallel offer workers reached the lock',trim(fgets($a[1][1]))==='ready'&&trim(fgets($b[1][1]))==='ready');return [$a,$b];
});$out=[dp_finish($workers[0]),dp_finish($workers[1])];sort($out);dp_ok('Concurrent original publish creates once and replays once',$out===['new','replay']&&dp_view(4)['count']===1);
$parallelOffer=dp_view(4);$payload=['schedule'=>$parallelOffer['schedule']['id'],'fingerprint'=>$parallelOffer['fingerprint'],'choice'=>'accepted','comment'=>''];
$workers=staff_locked(function()use($parallelOffer,$payload):array{
    $a=dp_worker('decide',0,4,1,41,$parallelOffer['expected'],$payload);$other=$payload;$other['choice']='declined';$other['comment']='Please change the price';$b=dp_worker('decide',0,4,1,42,$parallelOffer['expected'],$other);
    dp_ok('Competing financial decision workers reached the lock',trim(fgets($a[1][1]))==='ready'&&trim(fgets($b[1][1]))==='ready');return [$a,$b];
});$out=[dp_finish($workers[0]),dp_finish($workers[1])];sort($out);dp_ok('Competing financial decisions commit exactly once',$out===['denied','new']&&dp_view(4)['count']===2);
$fifth=dp_fields(50,5);$fifthExpected=dp_view(5)['expected'];
$worker=staff_locked(function()use($fifth,$fifthExpected):array{$worker=dp_worker('publish',2,5,1,50,$fifthExpected,$fifth);dp_ok('Revoked-staff worker reached the lock',trim(fgets($worker[1][1]))==='ready');query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',['["clients.manage"]']);return $worker;});
dp_ok('Current financial permission is rechecked after lock wait',dp_finish($worker)==='denied'&&dp_view(5)['count']===0);query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',['["clients.manage","payments.manage"]']);
$payload=['schedule'=>$fresh['id'],'fingerprint'=>$freshOffer['fingerprint'],'choice'=>'accepted','comment'=>''];
$worker=staff_locked(function()use($freshOffer,$payload):array{$worker=dp_worker('decide',0,1,1,7,$freshOffer['expected'],$payload);dp_ok('Revoked-client worker reached the lock',trim(fgets($worker[1][1]))==='ready');query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);return $worker;});
dp_ok('Current client authentication is rechecked after lock wait',dp_finish($worker)==='denied'&&dp_view()['status']==='offered');
$sixth=dp_fields(60,6);$sixthExpected=dp_view(6)['expected'];
$worker=staff_locked(function()use($sixth,$sixthExpected):array{$worker=dp_worker('publish',1,6,1,60,$sixthExpected,$sixth);dp_ok('Transferred-project worker reached the lock',trim(fgets($worker[1][1]))==='ready');query('UPDATE '.table('studio_jobs').' SET client_id=2 WHERE id=6');return $worker;});
dp_ok('Current project ownership is rechecked after lock wait',dp_finish($worker)==='denied');query('UPDATE '.table('studio_jobs').' SET client_id=1 WHERE id=6');

$event=one('SELECT * FROM '.table('design_payment_events').' WHERE id=?',[dp_id(6)]);$anchor=one('SELECT * FROM '.table('design_payment_heads').' WHERE id=1');
query('UPDATE '.table('design_payment_events').' SET event_sha256=? WHERE id=?',[str_repeat('0',64),dp_id(6)]);dp_deny('Corrupted financial fingerprint refuses reads',fn()=>dp_view());query('UPDATE '.table('design_payment_events').' SET event_sha256=? WHERE id=?',[$event['event_sha256'],dp_id(6)]);
query('UPDATE '.table('design_payment_events').' SET input_sha256=? WHERE id=?',[str_repeat('0',64),dp_id(6)]);dp_deny('Corrupted original request fingerprint refuses replay',fn()=>design_payments_publish(1,1,dp_id(6),$freshExpected,$fresh));query('UPDATE '.table('design_payment_events').' SET input_sha256=? WHERE id=?',[$event['input_sha256'],dp_id(6)]);
query('UPDATE '.table('design_payment_heads').' SET event_count=event_count+1 WHERE id=1');dp_deny('Lost tail evidence cannot become a new empty schedule',fn()=>dp_view());query('UPDATE '.table('design_payment_heads').' SET event_count=? WHERE id=1',[$anchor['event_count']]);
query('UPDATE '.table('design_payment_events').' SET event_json=? WHERE id=?',[str_repeat('x',DESIGN_PAYMENTS_EVENT_BYTES+1),dp_id(6)]);query('UPDATE '.table('design_payment_heads').' SET retained_bytes=? WHERE id=1',[(int)$anchor['retained_bytes']-strlen($event['event_json'])+DESIGN_PAYMENTS_EVENT_BYTES+1]);dp_deny('Oversized saved financial event is refused before JSON hydration',fn()=>dp_view());query('UPDATE '.table('design_payment_events').' SET event_json=? WHERE id=?',[$event['event_json'],dp_id(6)]);query('UPDATE '.table('design_payment_heads').' SET retained_bytes=? WHERE id=1',[$anchor['retained_bytes']]);
query('UPDATE '.table('studio_jobs').' SET client_id=2 WHERE id=1');dp_deny('Ownership transfer cannot expose retained financial history to new owner',fn()=>dp_view());query('UPDATE '.table('studio_jobs').' SET client_id=1 WHERE id=1');
dp_ok('Restored financial records retain their original head',dp_view()['expected']===$freshOffer['expected']);

require __DIR__.'/design_payments_native_limits.php';
