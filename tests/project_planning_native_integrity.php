<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$initial3=pl_view(3)['expected'];project_planning_save(3,1,pl_id(700),$initial3,pl_fields('A'));
project_planning_save(3,1,pl_id(701),pl_view(3)['expected'],pl_fields('B'));project_planning_save(3,1,pl_id(702),pl_view(3)['expected'],pl_fields('A'));
$original=pl_view(3);$anchor=one('SELECT * FROM '.table('studio_plan_heads').' WHERE id=3');
pl_ok('Independent current plan and head retain A to B to A history',$original['count']===3&&$anchor['event_id']===pl_id(702)&&$anchor['event_sha256']===$original['expected']);
foreach([
    ['studio_plan_events','event_sha256',pl_id(701),str_repeat('0',64),'middle event hash'],
    ['studio_plan_events','staff_id',pl_id(701),2,'event actor column'],
    ['studio_plan_requests','input_sha256',pl_id(701),str_repeat('0',64),'original request hash'],
    ['studio_plan_requests','input_json',pl_id(701),'{}','original request content'],
    ['studio_plan_heads','event_sha256',3,str_repeat('0',64),'independent head hash'],
    ['studio_plan_heads','plan_sha256',3,str_repeat('0',64),'current plan hash'],
    ['studio_plan_heads','plan_json',3,CajunWebPro\Foundation\ProjectPlanning\ProjectPlan::fromArray(pl_fields('Different'))->json(),'current plan contents'],
    ['studio_plan_heads','retained_bytes',3,1,'retained byte anchor'],
    ['studio_plan_heads','plan_json',3,str_repeat('x',262145),'oversized current plan'],
] as [$table,$column,$id,$changed,$label]){
    $prior=one('SELECT `'.$column.'` FROM '.table($table).' WHERE id=?',[$id])[$column];query('UPDATE '.table($table).' SET `'.$column.'`=? WHERE id=?',[$changed,$id]);
    try{pl_deny('Corrupt '.$label.' blocks current history',fn()=>pl_view(3));pl_deny('Corrupt '.$label.' blocks exact original replay',fn()=>project_planning_save(3,1,pl_id(700),$initial3,pl_fields('A')));}finally{query('UPDATE '.table($table).' SET `'.$column.'`=? WHERE id=?',[$prior,$id]);}
}
$rows=all('SELECT * FROM '.table('studio_plan_events').' WHERE job_id=3 AND revision>0 ORDER BY revision');$requests=all('SELECT r.* FROM '.table('studio_plan_requests').' r JOIN '.table('studio_plan_events').' e ON e.id=r.id WHERE e.job_id=3 AND e.revision>0 ORDER BY e.revision');
db()->beginTransaction();$restricted=false;try{query('DELETE FROM '.table('studio_plan_requests').' WHERE id=?',[pl_id(702)]);try{query('DELETE FROM '.table('studio_plan_events').' WHERE id=?',[pl_id(702)]);}catch(PDOException $e){$restricted=$e->getCode()==='23000';}}finally{db()->rollBack();}pl_ok('Restrictive head relation blocks ordinary tail deletion',$restricted);
db()->exec('SET FOREIGN_KEY_CHECKS=0');
try{
    query('DELETE r FROM '.table('studio_plan_requests').' r JOIN '.table('studio_plan_events').' e ON e.id=r.id WHERE e.job_id=3 AND e.revision>0');query('DELETE FROM '.table('studio_plan_events').' WHERE job_id=3 AND revision>0');$lost=pl_snapshot();
    pl_deny('Lost reverted tail refuses even when the current plan equals the initial plan',fn()=>pl_view(3));pl_client();pl_deny('Client cannot receive falsely complete truncated plan history',fn()=>project_planning_client_get(3,1));pl_staff();
    pl_deny('Original plan retry cannot conceal lost later history',fn()=>project_planning_save(3,1,pl_id(700),$initial3,pl_fields('A')));pl_deny('Fresh plan cannot silently replace missing history',fn()=>project_planning_save(3,1,pl_id(703),$original['expected'],pl_fields('C')));pl_ok('Lost-history refusals preserve remaining rows',pl_snapshot()===$lost);
}finally{
    foreach(['studio_plan_events'=>$rows,'studio_plan_requests'=>$requests] as $table=>$records)foreach($records as $record)query('INSERT INTO '.table($table).' (`'.implode('`,`',array_keys($record)).'`) VALUES ('.implode(',',array_fill(0,count($record),'?')).')',array_values($record));db()->exec('SET FOREIGN_KEY_CHECKS=1');
}
query('DELETE FROM '.table('studio_plan_heads').' WHERE id=3');try{pl_deny('Missing independent head cannot turn history into an unplanned project',fn()=>pl_view(3));}finally{query('INSERT INTO '.table('studio_plan_heads').' (`'.implode('`,`',array_keys($anchor)).'`) VALUES ('.implode(',',array_fill(0,count($anchor),'?')).')',array_values($anchor));}
save_setting('project_planning_schema_version','');try{pl_deny('Missing preparation marker blocks ordinary reads',fn()=>pl_view(3));pl_deny('Unrecognized populated history cannot be adopted',fn()=>project_planning_migrate($password));}finally{save_setting('project_planning_schema_version','1');}
query('UPDATE '.table('studio_jobs').' SET client_id=2 WHERE id=3');try{pl_deny('Project reassignment cannot carry old client planning into a new owner view',fn()=>pl_view(3));pl_client(2);pl_deny('New client cannot receive prior client structured plan',fn()=>project_planning_client_get(3,2));}finally{query('UPDATE '.table('studio_jobs').' SET client_id=1 WHERE id=3');pl_staff();}
pl_ok('Restored exact history validates without repair or adoption',pl_view(3)===$original);
