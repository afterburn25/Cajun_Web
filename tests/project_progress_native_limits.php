<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||config()['db']['prefix']!=='pgrs_')exit(2);
pg_staff(4);pg_deny('Current staff without project permission cannot read history',fn()=>pg_saved($job));pg_staff(2);$_SESSION['auth_version']=str_repeat('0',32);pg_deny('Retired staff authentication cannot read history',fn()=>pg_saved($job));pg_staff(2);$_SESSION['last_activity']=time()-1801;pg_deny('Expired staff cannot read history',fn()=>pg_saved($job));pg_staff(2);query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=2');pg_deny('Disabled staff profile cannot read history',fn()=>pg_saved($job));query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=2');pg_staff();
$relation=one('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=\'job_id\' AND REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].'studio_progress_events'])['CONSTRAINT_NAME'];
if(!preg_match('/\Apgrs_[a-zA-Z0-9_]+\z/',$relation))throw new RuntimeException('Unexpected fixture constraint');
db()->exec('ALTER TABLE '.table('studio_progress_events').' DROP FOREIGN KEY `'.$relation.'`');
db()->exec('ALTER TABLE '.table('studio_progress_events').' ADD CONSTRAINT `'.$relation.'` FOREIGN KEY (job_id) REFERENCES '.table('studio_jobs').' (id) ON DELETE CASCADE');
pg_deny('Cascading history deletion is refused by setup',fn()=>project_progress_migrate($password));
db()->exec('ALTER TABLE '.table('studio_progress_events').' DROP FOREIGN KEY `'.$relation.'`');
db()->exec('ALTER TABLE '.table('studio_progress_events').' ADD CONSTRAINT `'.$relation.'` FOREIGN KEY (job_id) REFERENCES '.table('studio_jobs').' (id) ON DELETE RESTRICT');project_progress_migrate($password);
$before=pg_snapshot();$token=pg_saved($job)['expected'];
pg_deny('Malformed request identity cannot mutate project history',fn()=>project_progress_save($job,1,'invalid',$token,pg_fields()));
pg_deny('Unexpected private fields cannot enter native project history',fn()=>project_progress_save($job,1,pg_id(501),$token,pg_fields()+['private_note'=>'Do not expose']));
pg_deny('New project cannot carry an existing project token',fn()=>project_progress_save(0,1,pg_id(501),$token,pg_fields()));
pg_ok('Refused malformed requests preserve all native rows',pg_snapshot()===$before);

// Populate the boundary through pure validated events in one isolated fixture transaction.
// Calling the public save4095 times would repeat full validation and test quadratic fixture work.
$cap=project_progress_save(0,2,pg_id(900),'',pg_fields('Bounded history'))['job_id'];
$capHead=CajunWebPro\Foundation\Projects\ProjectProgressEvent::fromJson(one('SELECT event_json FROM '.table('studio_progress_events').' WHERE id=?',[pg_id(900)])['event_json']);$at=$capHead->data()['occurred_at'];
db()->beginTransaction();
for($i=1;$i<PROJECT_PROGRESS_MAX_EVENTS;$i++){
    $fields=pg_fields('Bounded history');$fields['summary']='Capacity fixture '.$i;$s=CajunWebPro\Foundation\Projects\ProjectSnapshot::fromArray($fields);$expected=$capHead->fingerprint();
    $capHead=$capHead->update(pg_id(10000+$i),1,$at,$expected,$s);project_progress_append($capHead);$input=project_progress_input($cap,2,1,$expected,$s);
    query('INSERT INTO '.table('studio_progress_requests').' (id,input_json,input_sha256) VALUES (?,?,?)',[$capHead->data()['id'],$input,hash('sha256',$input)]);
}
$f=$capHead->snapshot()->data();query('UPDATE '.table('studio_jobs').' SET title=?,summary=?,stage=?,milestones=?,updated_at=? WHERE id=?',[$f['title'],$f['summary'],$f['stage'],$f['milestones'],gmdate('Y-m-d H:i:s',$at),$cap]);project_progress_store_head($capHead,true);db()->commit();
$limit=project_progress_staff_get($cap,137);
pg_ok('Full history validates across every100-event database batch',$limit['count']===4096&&$limit['tracked']);
pg_ok('The final bounded page137 remains explicitly reachable',$limit['page']===137&&$limit['pages']===137&&count($limit['items'])===16&&$limit['items'][0]['revision']===4080&&$limit['items'][15]['revision']===4095);
pg_client(2);$limitPublic=project_progress_client_get($cap,2,0,pg_id(10101));pg_ok('Exact focus resolves across database batches and display pages',$limitPublic['page']===4&&$limitPublic['items'][11]['id']===pg_id(10101));pg_staff();
$last=one('SELECT * FROM '.table('studio_jobs').' WHERE id=?',[$cap]);$auditCount=(int)query('SELECT COUNT(*) FROM '.table('audit'))->fetchColumn();
pg_deny('Capacity refuses a new update without pruning old history',fn()=>project_progress_save($cap,2,pg_id(600),$limit['expected'],array_replace($f,['stage'=>'care'])));
pg_ok('Capacity refusal preserves project history requests and audit',one('SELECT * FROM '.table('studio_jobs').' WHERE id=?',[$cap])===$last&&(int)query('SELECT COUNT(*) FROM '.table('studio_progress_events').' WHERE job_id=?',[$cap])->fetchColumn()===4096&&(int)query('SELECT COUNT(*) FROM '.table('studio_progress_requests').' r JOIN '.table('studio_progress_events').' e ON e.id=r.id WHERE e.job_id=?',[$cap])->fetchColumn()===4096&&(int)query('SELECT COUNT(*) FROM '.table('audit'))->fetchColumn()===$auditCount);
pg_ok('Original creation retry remains available at full capacity',project_progress_save(0,2,pg_id(900),'',pg_fields('Bounded history'))['replayed']);
