<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||config()['db']['prefix']!=='pgrs_')exit(2);
pg_staff();$f=pg_fields('Retained reverted history');$tail=project_progress_save(0,1,pg_id(700),'',$f)['job_id'];
project_progress_save($tail,1,pg_id(701),pg_saved($tail)['expected'],array_replace($f,['summary'=>'A newer state']));
project_progress_save($tail,1,pg_id(702),pg_saved($tail)['expected'],$f);
$original=pg_saved($tail);$anchor=one('SELECT * FROM '.table('studio_progress_heads').' WHERE id=?',[$tail]);
pg_ok('An independent saved head anchors the actual latest revision',$original['count']===3&&$anchor['event_id']===pg_id(702)&&(int)$anchor['revision']===2&&$anchor['event_sha256']===$original['expected']);
db()->beginTransaction();$blocked=false;
try{query('DELETE FROM '.table('studio_progress_requests').' WHERE id=?',[pg_id(702)]);try{query('DELETE FROM '.table('studio_progress_events').' WHERE id=?',[pg_id(702)]);}catch(PDOException $e){$blocked=$e->getCode()==='23000';}}finally{db()->rollBack();}
pg_ok('Restrictive head relationship blocks ordinary current-event deletion',$blocked&&pg_saved($tail)['count']===3);
$events=all('SELECT * FROM '.table('studio_progress_events').' WHERE job_id=? AND revision>0 ORDER BY revision',[$tail]);$requests=all('SELECT r.* FROM '.table('studio_progress_requests').' r JOIN '.table('studio_progress_events').' e ON e.id=r.id WHERE e.job_id=? AND e.revision>0 ORDER BY e.revision',[$tail]);
// Simulate storage loss rather than a permitted application action. Restore every exact row.
db()->exec('SET FOREIGN_KEY_CHECKS=0');
try{
    query('DELETE r FROM '.table('studio_progress_requests').' r JOIN '.table('studio_progress_events').' e ON e.id=r.id WHERE e.job_id=? AND e.revision>0',[$tail]);
    query('DELETE FROM '.table('studio_progress_events').' WHERE job_id=? AND revision>0',[$tail]);$lost=pg_snapshot();
    pg_deny('Lost reverted tail is detected even when current values equal baseline',fn()=>pg_saved($tail));
    pg_client();pg_deny('Client cannot receive a falsely complete truncated history',fn()=>project_progress_client_get($tail,1));pg_staff();
    pg_deny('Original creation replay cannot conceal a lost history tail',fn()=>project_progress_save(0,1,pg_id(700),'',$f));
    pg_deny('A fresh save cannot repair or replace missing history silently',fn()=>project_progress_save($tail,1,pg_id(703),$original['expected'],array_replace($f,['stage'=>'care'])));
    pg_ok('Truncated-history refusals preserve all remaining rows',pg_snapshot()===$lost);
}finally{
    foreach(['studio_progress_events'=>$events,'studio_progress_requests'=>$requests] as $name=>$rows)foreach($rows as $row)query('INSERT INTO '.table($name).' (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    db()->exec('SET FOREIGN_KEY_CHECKS=1');
}
query('DELETE FROM '.table('studio_progress_heads').' WHERE id=?',[$tail]);
try{pg_deny('Missing anchor cannot turn retained events into an untracked project',fn()=>pg_saved($tail));}finally{query('INSERT INTO '.table('studio_progress_heads').' (id,event_id,event_sha256,revision) VALUES (?,?,?,?)',[$anchor['id'],$anchor['event_id'],$anchor['event_sha256'],$anchor['revision']]);}
query('UPDATE '.table('studio_progress_heads').' SET event_sha256=? WHERE id=?',[str_repeat('0',64),$tail]);
try{pg_deny('Altered anchor hash refuses otherwise valid history',fn()=>pg_saved($tail));}finally{query('UPDATE '.table('studio_progress_heads').' SET event_sha256=? WHERE id=?',[$anchor['event_sha256'],$tail]);}
pg_ok('Restored exact history anchor and requests validate again',pg_saved($tail)===$original);
