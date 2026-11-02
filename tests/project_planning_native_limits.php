<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
use CajunWebPro\Foundation\ProjectPlanning\{ProjectPlan,ProjectPlanEvent};
pl_staff();$token=pl_view()['expected'];$before=pl_snapshot();
pl_deny('Malformed original request identity refuses',fn()=>project_planning_save(1,1,'invalid',$token,pl_fields()));pl_deny('Malformed original version refuses',fn()=>project_planning_save(1,1,pl_id(500),'invalid',pl_fields()));pl_deny('Plan cannot create a phantom project',fn()=>project_planning_save(0,1,pl_id(500),$token,pl_fields()));pl_deny('Unknown private fields cannot enter public planning',fn()=>project_planning_save(1,1,pl_id(500),$token,pl_fields()+['private_note'=>'Internal']));pl_ok('Invalid input refusals preserve all rows',pl_snapshot()===$before);
$initial4=pl_view(4)['expected'];$fields=pl_fields('Capacity');$fields['milestones']=[];project_planning_save(4,1,pl_id(900),$initial4,$fields);
$row=one('SELECT event_json FROM '.table('studio_plan_events').' WHERE id=?',[pl_id(900)]);$head=ProjectPlanEvent::fromJson($row['event_json']);$bytes=pl_view(4)['retained_bytes'];$at=$head->data()['occurred_at'];
// Populate validated boundary history in one isolated fixture transaction, avoiding quadratic save setup.
db()->beginTransaction();
for($i=1;$i<PROJECT_PLANNING_MAX_EVENTS;$i++){
    $fields['scope']='Capacity '.$i;$plan=ProjectPlan::fromArray($fields);$expected=$head->fingerprint();$head=$head->update(pl_id(10000+$i),1,$at,$expected,$plan);$input=project_planning_input(4,1,1,$expected,$plan);project_planning_append($head,$input);$bytes+=strlen($head->json())+strlen($input);
}
project_planning_store_head($head,$bytes,true);db()->commit();
$full=project_planning_staff_get(4,410);pl_ok('Full4096-event history validates with the final bounded page',$full['count']===4096&&$full['page']===410&&$full['pages']===410&&count($full['items'])===6&&$full['items'][0]['revision']===4090&&$full['items'][5]['revision']===4095);
pl_ok('Same-second revisions retain exact focused history across pages',project_planning_staff_get(4,0,pl_id(10101))['page']===11);
$audit=(int)query('SELECT COUNT(*) FROM '.table('audit'))->fetchColumn();$savedHead=one('SELECT * FROM '.table('studio_plan_heads').' WHERE id=4');
pl_deny('Event capacity refuses new work without dropping old events',fn()=>project_planning_save(4,1,pl_id(901),$full['expected'],array_replace($fields,['scope'=>'Cannot fit'])));pl_ok('Event-capacity refusal preserves anchored state and audit',one('SELECT * FROM '.table('studio_plan_heads').' WHERE id=4')===$savedHead&&(int)query('SELECT COUNT(*) FROM '.table('audit'))->fetchColumn()===$audit);
$initialFields=pl_fields('Capacity');$initialFields['milestones']=[];pl_ok('Original request replay still works at full event capacity',project_planning_save(4,1,pl_id(900),$initial4,$initialFields)['replayed']);

$current=pl_view(3);$head=ProjectPlanEvent::fromJson(one('SELECT event_json FROM '.table('studio_plan_events').' WHERE id=?',[$current['items'][2]['id']])['event_json']);$bytes=$current['retained_bytes'];$at=time();$i=0;
db()->beginTransaction();
while(true){
    $large=pl_fields(str_pad('Byte capacity '.$i,10000,'x'));$large['client_action']=str_repeat('a',4000);$large['blockers']=str_repeat('b',4000);$large['milestones'][0]['details']=str_repeat('c',2000);$large['milestones'][1]['details']=str_repeat('d',2000);$plan=ProjectPlan::fromArray($large);$expected=$head->fingerprint();$event=$head->update(pl_id(20000+$i),1,$at,$expected,$plan);$input=project_planning_input(3,1,1,$expected,$plan);$next=$bytes+strlen($event->json())+strlen($input);
    if($next>PROJECT_PLANNING_MAX_BYTES)break;
    project_planning_append($event,$input);$head=$event;$bytes=$next;$i++;if($i>1000)throw new RuntimeException('Byte-capacity fixture did not reach the bound.');
}
project_planning_store_head($head,$bytes,true);db()->commit();$bounded=pl_view(3);$savedHead=one('SELECT * FROM '.table('studio_plan_heads').' WHERE id=3');$audit=(int)query('SELECT COUNT(*) FROM '.table('audit'))->fetchColumn();
pl_ok('Complete history near32MiB remains readable in bounded pages',$bounded['count']<4096&&$bounded['retained_bytes']<=PROJECT_PLANNING_MAX_BYTES&&$bounded['retained_bytes']>PROJECT_PLANNING_MAX_BYTES-100000&&count($bounded['items'])<=10);
pl_deny('Byte capacity refuses the next valid full plan without pruning',fn()=>project_planning_save(3,1,pl_id(22000),$bounded['expected'],$large));pl_ok('Byte-capacity refusal preserves full head and audit',one('SELECT * FROM '.table('studio_plan_heads').' WHERE id=3')===$savedHead&&(int)query('SELECT COUNT(*) FROM '.table('audit'))->fetchColumn()===$audit);
pl_ok('Original first plan remains replayable near byte capacity',project_planning_save(3,1,pl_id(700),$initial3,pl_fields('A'))['replayed']);
