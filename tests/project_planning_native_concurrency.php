<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$start=pl_view();$workers=staff_locked(function()use($start):array{
    $workers=[pl_worker('save',2,1,1,pl_id(40),$start['expected'],pl_fields('Editor A')),pl_worker('save',2,1,1,pl_id(41),$start['expected'],pl_fields('Editor B'))];
    foreach($workers as $i=>$worker)pl_ok('Competing editor '.($i+1).' reaches the shared-lock barrier',trim(fgets($worker[1][1]))==='ready');return $workers;
});
$results=array_map('pl_finish',$workers);sort($results);pl_ok('Only one competing stale-version editor commits',$results===['denied','new']&&pl_view()['count']===$start['count']+1);
$start=pl_view();$workers=staff_locked(function()use($start):array{
    $workers=[pl_worker('save',2,1,1,pl_id(42),$start['expected'],pl_fields('Exact concurrent retry')),pl_worker('save',2,1,1,pl_id(42),$start['expected'],pl_fields('Exact concurrent retry'))];
    foreach($workers as $i=>$worker)pl_ok('Duplicate request '.($i+1).' reaches the shared-lock barrier',trim(fgets($worker[1][1]))==='ready');return $workers;
});
$results=array_map('pl_finish',$workers);sort($results);pl_ok('Concurrent exact retries create one event and replay once',$results===['new','replay']&&pl_view()['count']===$start['count']+1);
$start=pl_view();$worker=staff_locked(function()use($start):array{
    $worker=pl_worker('save',2,1,1,pl_id(43),$start['expected'],pl_fields('Revoked editor'));pl_ok('Waiting staff writer reaches authority barrier',trim(fgets($worker[1][1]))==='ready');query('UPDATE '.table('staff_profiles')." SET permissions='[]' WHERE user_id=2");return $worker;
});pl_ok('Permission revoked while waiting prevents the save',pl_finish($worker)==='denied'&&pl_view()['count']===$start['count']);query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[json_encode(['clients.manage'])]);
$worker=staff_locked(function()use($start):array{
    $worker=pl_worker('save',2,1,1,pl_id(44),$start['expected'],pl_fields('Inactive client'));pl_ok('Waiting writer reaches client-state barrier',trim(fgets($worker[1][1]))==='ready');query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');return $worker;
});pl_ok('Client deactivated while waiting prevents the save',pl_finish($worker)==='denied');query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
$worker=staff_locked(function()use($start):array{
    $worker=pl_worker('save',2,1,1,pl_id(45),$start['expected'],pl_fields('Changed ownership'));pl_ok('Waiting writer reaches ownership barrier',trim(fgets($worker[1][1]))==='ready');query('UPDATE '.table('studio_jobs').' SET client_id=2 WHERE id=1');return $worker;
});pl_ok('Ownership changed while waiting prevents the save',pl_finish($worker)==='denied');query('UPDATE '.table('studio_jobs').' SET client_id=1 WHERE id=1');
$auth=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=1')['auth_version'];
$worker=staff_locked(function():array{
    $worker=pl_worker('client',0,1,1,'','',[]);pl_ok('Waiting client reader reaches authority barrier',trim(fgets($worker[1][1]))==='ready');query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[str_repeat('f',32)]);return $worker;
});pl_ok('Client authentication revoked while waiting prevents the read',pl_finish($worker)==='denied');query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[$auth]);
pl_staff(2);$_SESSION['auth_version']=str_repeat('0',32);pl_deny('Retired staff session cannot read planning',fn()=>pl_view());pl_staff(2);query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=2');pl_deny('Disabled staff cannot read planning',fn()=>pl_view());query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=2');pl_staff();
for($i=0;$i<16;$i++)project_planning_save(1,1,pl_id(100+$i),pl_view()['expected'],pl_fields('Dated update '.$i));
$firstPage=project_planning_staff_get(1,1);$last=pl_view();pl_ok('History pages retain bounded nonoverlapping revision order',count($firstPage['items'])===10&&count($last['items'])===1&&$last['page']===3&&$last['items'][0]['revision']===20&&!array_intersect(array_column($firstPage['items'],'id'),array_column($last['items'],'id')));
pl_ok('Exact old event links resolve the retained page',project_planning_staff_get(1,0,pl_id(1))['page']===1);pl_deny('Foreign project event cannot be a focus cursor',fn()=>project_planning_staff_get(1,0,pl_id(20)));pl_deny('Unavailable history page refuses',fn()=>project_planning_staff_get(1,4));pl_deny('Negative history page refuses',fn()=>project_planning_staff_get(1,-1));
support_migrate($password);support_policy(1,true,$password);pl_staff(3);$sid=support_start(1);$view=project_planning_client_get(1,1,0,'',$sid);
pl_ok('Scoped support receives public projection with real staff attribution',$view['count']===21&&!isset($view['expected'],$view['items'][0]['staff_id'])&&one('SELECT actor_id,client_id FROM '.table('support_events')." WHERE action='project.planning' ORDER BY id DESC LIMIT 1")===['actor_id'=>3,'client_id'=>1]);
$before=pl_snapshot();$idle=$_SESSION['last_activity'];project_planning_client_get(1,1,0,'',$sid,false);pl_ok('Passive support view preserves audit and idle time',$_SESSION['last_activity']===$idle&&pl_snapshot()===$before);
pl_deny('Support cannot switch to another client plan',fn()=>project_planning_client_get(2,2,0,'',$sid));$_GET['support']=$sid;pl_deny('Support cannot fall back to an ordinary client view',fn()=>project_planning_client_get(1,1));pl_deny('Client-manager staff cannot edit a plan inside support',fn()=>project_planning_save(1,1,pl_id(200),$last['expected'],pl_fields()));unset($_GET['support']);
pl_ok('Support audit failure rolls back general and support audit',pl_fail(fn()=>project_planning_client_get(1,1,0,'',$sid),'support_events')&&pl_snapshot()===$before);
query('UPDATE '.table('support_clients').' SET allowed=0 WHERE client_id=1');pl_deny('Revoked support scope blocks further plan reads',fn()=>project_planning_client_get(1,1,0,'',$sid));pl_staff();
