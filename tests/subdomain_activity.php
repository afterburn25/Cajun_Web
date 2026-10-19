<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
/** Actual projection, event graph and current-client checks; modeled persistence only. */
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-document-roots/DocumentRootsObservation.php';
set_error_handler(static function(int $level,string $message):never{throw new ErrorException($message,0,$level);});
function sa_extract(string $file,array $names):void {
    $source=file_get_contents($file);
    foreach($names as $name){
        if(preg_match('/^function '.preg_quote($name,'/').'\([^\n]*[\s\S]*?(?=^function |\z)/m',$source,$m)!==1)throw new LogicException('Production extraction changed.');
        eval($m[0]);
    }
}
sa_extract(CWP_CODE_ROOT.'/app/providers/operations.php',['provider_operations_canonical']);
require CWP_CODE_ROOT.'/app/providers/subdomain_requests_document.php';
class SubdomainRequestUnavailable extends RuntimeException {}
sa_extract(CWP_CODE_ROOT.'/app/providers/subdomain_requests.php',['provider_subdomain_requests_actor','provider_subdomain_requests_current','provider_subdomain_requests_event_sha','provider_subdomain_requests_validate_origin','provider_subdomain_requests_owner_history','provider_subdomain_requests_graph']);
$source=file_get_contents(CWP_CODE_ROOT.'/app/providers/subdomain_activity.php');
$start=strpos($source,'function provider_subdomain_activity_row(');
if($start===false)throw new LogicException('Projection source missing.');eval(substr($source,$start));

$checks=[];
function sa_check(string $name,bool $passed):void {
    global $checks;foreach($checks as $check)if($check['name']===$name)throw new LogicException('Duplicate outcome.');
    $checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);
}
function sa_refuse(string $name,callable $work,string $class=RuntimeException::class):void {
    try{$work();}catch(Throwable $e){if(!$e instanceof $class)throw $e;sa_check($name,true);return;}throw new LogicException('Expected refusal: '.$name);
}
function sa_id(int $id):string{return str_pad(dechex($id),32,'0',STR_PAD_LEFT);}
function provider_subdomain_requests_guard(bool $tx=false):void {
    if(!$GLOBALS['m']['lock']||($tx&&!$GLOBALS['m']['tx']))throw new LogicException('Owning mutex and transaction required.');
}
function support_current():?array{return $GLOBALS['m']['support']?['id'=>1]:null;}
function admin_user():?array{++$GLOBALS['m']['auth_calls'];return $GLOBALS['m']['owner_session']?$GLOBALS['m']['owner']:null;}
function staff_ready():bool{return $GLOBALS['m']['staff_ready'];}
function staff_can(string $permission,?array $owner=null):bool{return $permission==='staff.manage'&&$GLOBALS['m']['permission'];}
function studio_client():?array {
    ++$GLOBALS['m']['auth_calls'];$c=$GLOBALS['m']['clients'][$_SESSION['client_id']??0]??null;
    return $c&&$c['active']&&hash_equals($c['auth_version'],(string)($_SESSION['client_auth']??''))?$c:null;
}
function provider_subdomain_add_actor(?string $password=null):array{throw new LogicException('Provider settings authority is not needed by the projection.');}
function provider_subdomain_requests_events():?array {
    ++$GLOBALS['m']['event_reads'];if($GLOBALS['m']['events_fault']!==null)throw $GLOBALS['m']['events_fault'];return $GLOBALS['m']['events'];
}
function provider_subdomain_add_ready():bool {
    ++$GLOBALS['m']['ready_calls'];if($GLOBALS['m']['owner_fault']!==null)throw $GLOBALS['m']['owner_fault'];
    if($GLOBALS['m']['owner_ready'])provider_subdomain_requests_owner_history();
    return $GLOBALS['m']['owner_ready'];
}
function provider_subdomain_requests_retained(array $resource):array {
    provider_subdomain_requests_resource_document($resource);
    if($GLOBALS['m']['source_fault'])throw new RuntimeException('Original source unavailable.');
    return ['enabled'=>true,'names'=>['example.invalid']];
}
function provider_subdomain_requests_static(string $linkId):array {
    if($GLOBALS['m']['unlinked'])throw new SubdomainRequestUnavailable('Assignment changed.');return $GLOBALS['m']['static'];
}
function table(string $name):string{return $name;}
function one(string $sql,array $args=[]):?array {
    if($sql!=='SELECT * FROM studio_clients WHERE id=? AND active=1')throw new LogicException('Unmodeled SQL.');
    $c=$GLOBALS['m']['clients'][$args[0]]??null;return $c&&$c['active']?$c:null;
}
function all(string $sql,array $args=[]):array {
    if($sql!=='SELECT * FROM provider_subdomain_add_checks WHERE request_id=? ORDER BY BINARY id')throw new LogicException('Unmodeled SQL or attempted write.');
    return array_values(array_filter($GLOBALS['m']['check_rows'],static fn($row)=>$row['request_id']===$args[0]));
}
function provider_subdomain_add_request(string $id):array {
    ++$GLOBALS['m']['request_reads'];$r=$GLOBALS['m']['owners'][$id]??throw new RuntimeException('Missing owner approval.');
    provider_subdomain_requests_validate_origin($r);return $r;
}
function provider_subdomain_add_terminal(array $request):?array {
    if($GLOBALS['m']['terminal_fault'])throw new RuntimeException('Terminal proof mismatch.');
    return $GLOBALS['m']['terminals'][$request['row']['id']]??null;
}
function provider_subdomain_add_check_document(array $row,array $request):array {
    if($GLOBALS['m']['check_fault'])throw new RuntimeException('Readback proof mismatch.');
    if(!isset($GLOBALS['m']['terminals'][$request['row']['id']]))throw new RuntimeException('Orphan readback.');
    return $row['document'];
}
function sa_reset():void {
    $h=str_repeat('a',64);$b=['profile_id'=>sa_id(90),'profile_revision'=>1,'key_fingerprint'=>$h,'credential_sha256'=>$h,'package_id'=>3101,'client_id'=>7,'link_id'=>sa_id(91),'link_sha256'=>$h,'event_id'=>2,'event_sha256'=>$h,'audit_id'=>3,'audit_sha256'=>$h,'source_id'=>sa_id(92),'source_seq'=>4,'source_sha256'=>$h];
    $resource=['binding'=>$b,'owner_id'=>1,'owner_auth_sha256'=>hash('sha256','owner-auth')];$static=$resource;
    foreach(['source_id','source_seq','source_sha256'] as $key)unset($static['binding'][$key]);
    $GLOBALS['m']=['lock'=>true,'tx'=>true,'support'=>false,'owner_session'=>true,'owner'=>['id'=>1,'role'=>'owner','enabled'=>1,'auth_version'=>'owner-auth'],'staff_ready'=>true,'permission'=>true,
        'clients'=>[7=>['id'=>7,'active'=>1,'auth_version'=>'client-auth'],8=>['id'=>8,'active'=>1,'auth_version'=>'other-auth']],
        'events'=>[],'owners'=>[],'terminals'=>[],'check_rows'=>[],'resource'=>$resource,'static'=>$static,'owner_ready'=>true,
        'events_fault'=>null,'owner_fault'=>null,'source_fault'=>false,'terminal_fault'=>false,'check_fault'=>false,'unlinked'=>false,
        'auth_calls'=>0,'event_reads'=>0,'ready_calls'=>0,'request_reads'=>0];
    $_SESSION=['client_id'=>7,'client_auth'=>'client-auth'];$_GET=[];
}
function sa_event(string $kind,int $id,?array $request=null):array {
    $resource=$GLOBALS['m']['resource'];$clientId=$request['client_id']??7;$subject=$request['id']??sa_id($id);$owner=in_array($kind,['decline','dispatch'],true);
    $payload=match($kind){
        'request'=>['resource'=>$resource,'parent_domain'=>'example.invalid','subdomain'=>'child'.$id.'.example.invalid','confirmed'=>true],
        'cancel','decline'=>['previous_event'=>$request['id'],'note'=>$kind==='decline'?'Private decision reason':''],
        'dispatch'=>['previous_event'=>$request['id'],'owner_request_id'=>sa_id($id),'owner_request_sha256'=>str_repeat('b',64)],
    };
    $e=['version'=>1,'id'=>sa_id($id),'subject_id'=>$subject,'client_id'=>$clientId,'kind'=>$kind,'actor'=>['kind'=>$owner?'owner':'client','id'=>$owner?1:$clientId,'auth_sha256'=>hash('sha256',$owner?'owner-auth':'client-auth')],
        'recorded_epoch'=>1700000000,'ordinal'=>count($GLOBALS['m']['events'])+1,'previous'=>str_repeat('0',64),'payload'=>$payload];
    $GLOBALS['m']['events'][]=$e;
    if($kind==='dispatch')$GLOBALS['m']['owners'][$e['id']]=['row'=>['id'=>$e['id'],'actor_id'=>1,'document_sha256'=>str_repeat('b',64)],'document'=>['version'=>2,'client_origin'=>['id'=>$request['id'],'request_sha256'=>provider_subdomain_requests_event_sha($request)],
        'actor_auth'=>hash('sha256','owner-auth'),'approved_epoch'=>1700000000,'params'=>['parentDomain'=>$request['payload']['parent_domain'],'subdomain'=>$request['payload']['subdomain']],
        'binding'=>['package'=>$request['payload']['resource']['binding']]]];
    return $e;
}
function sa_actor(string $audience='client',int $id=7):array{return ['type'=>$audience,'id'=>$id,'provider_excluded'=>false];}
function sa_snapshot(string $audience='client',int $id=7):array{return provider_subdomain_activity_snapshot_locked($audience,sa_actor($audience,$id));}
function sa_terminal(string $id,string $phase='attempted'):void{$GLOBALS['m']['terminals'][$id]=['row'=>['id'=>$id],'document'=>['phase'=>$phase,'ended_epoch'=>1700000001]];}
function sa_readback(string $owner,int $id,string $disposition):void{$GLOBALS['m']['check_rows'][]=['id'=>sa_id($id),'request_id'=>$owner,'document'=>['disposition'=>$disposition,'recorded_epoch'=>1700000002,'path'=>'SECRET/private/map','observation_proof'=>'SECRET-proof']];}

try {
    sa_reset();$GLOBALS['m']['lock']=false;sa_refuse('Snapshot refuses missing owning mutex',fn()=>sa_snapshot(),LogicException::class);
    sa_reset();$GLOBALS['m']['tx']=false;sa_refuse('Snapshot refuses missing SQL transaction',fn()=>sa_snapshot(),LogicException::class);
    sa_reset();sa_refuse('Snapshot refuses mismatched audience descriptor',fn()=>provider_subdomain_activity_snapshot_locked('admin',sa_actor()),LogicException::class);
    sa_refuse('Snapshot requires captured boolean provenance',fn()=>provider_subdomain_activity_snapshot_locked('client',['type'=>'client','id'=>7]),LogicException::class);
    foreach(['contact_id','contact_auth'] as $key){sa_reset();$_SESSION[$key]=null;$r=sa_snapshot();sa_check('Null '.$key.' provenance excludes before authority/history',$r===['status'=>'excluded','rows'=>[]]&&$GLOBALS['m']['auth_calls']===0&&$GLOBALS['m']['event_reads']===0);}
    sa_reset();$actor=sa_actor();$actor['provider_excluded']=true;$r=provider_subdomain_activity_snapshot_locked('client',$actor);sa_check('Captured contact presence survives session normalization',$r['status']==='excluded'&&$GLOBALS['m']['auth_calls']===0);
    foreach(['session','query'] as $mode){sa_reset();if($mode==='session')$GLOBALS['m']['support']=true;else $_GET['support']='';sa_check('Support '.$mode.' excludes provider subset before history',sa_snapshot()['status']==='excluded'&&$GLOBALS['m']['event_reads']===0);}
    foreach(['administrator','disabled','no_permission','wrong_actor','anonymous'] as $mode){sa_reset();if($mode==='administrator')$GLOBALS['m']['owner']['role']='administrator';if($mode==='disabled')$GLOBALS['m']['owner']['enabled']=0;if($mode==='no_permission')$GLOBALS['m']['permission']=false;if($mode==='wrong_actor')$GLOBALS['m']['owner']['id']=2;if($mode==='anonymous')$GLOBALS['m']['owner_session']=false;sa_check('Owner subset excludes '.$mode.' without history',sa_snapshot('admin',1)['status']==='excluded'&&$GLOBALS['m']['event_reads']===0);}
    sa_reset();unset($_SESSION['client_id']);sa_refuse('Client snapshot repeats actual login authority',fn()=>sa_snapshot());
    sa_reset();sa_refuse('Posted actor ID cannot select another client',fn()=>sa_snapshot('client',8));
    sa_reset();$GLOBALS['m']['clients'][7]['active']=0;sa_refuse('Inactive client has no provider snapshot',fn()=>sa_snapshot());
    sa_reset();$GLOBALS['m']['events']=null;$GLOBALS['m']['owner_ready']=false;sa_check('Clean never-used families are absent',sa_snapshot()===['status'=>'absent','rows'=>[]]);sa_check('Clean absence still checks surviving owner evidence',$GLOBALS['m']['ready_calls']===1);
    sa_reset();$GLOBALS['m']['events']=null;$GLOBALS['m']['owner_fault']=new RuntimeException('Orphaned owner grant');sa_refuse('Lost intake cannot hide orphaned owner history',fn()=>sa_snapshot());
    sa_reset();$GLOBALS['m']['owner_ready']=false;sa_refuse('Prepared intake with missing owner history is unavailable',fn()=>sa_snapshot());
    sa_reset();sa_check('Prepared empty history is a genuine empty snapshot',sa_snapshot()===['status'=>'ready','rows'=>[]]);
    sa_reset();$q=sa_event('request',1);sa_check('Owner receives initial request from the separate request node',array_column(sa_snapshot('admin',1)['rows'],'kind')===['subdomain_requested']);sa_check('Client does not receive own intake as a studio decision',sa_snapshot()['rows']===[]);
    sa_event('cancel',2,$q);$rows=sa_snapshot('admin',1)['rows'];sa_check('Withdrawal and intake have independent stable keys',array_column($rows,'event_key')===['subdomain-request:'.sa_id(2),'subdomain-request:'.sa_id(1)]);sa_check('Client does not receive own withdrawal',sa_snapshot()['rows']===[]);
    sa_reset();$q=sa_event('request',1);sa_event('decline',2,$q);$rows=sa_snapshot()['rows'];sa_check('Decline points to exact client request',count($rows)===1&&$rows[0]['target_id']===$q['id']&&$rows[0]['kind']==='subdomain_declined');sa_check('Decline text stays on authorized request detail',!str_contains(json_encode($rows),'Private decision reason'));
    sa_reset();$q=sa_event('request',1);$dispatch=sa_event('dispatch',2,$q);$rows=sa_snapshot()['rows'];sa_check('Durable dispatch is only a reviewed unconfirmed notice',count($rows)===1&&$rows[0]['kind']==='subdomain_reviewed'&&str_contains($rows[0]['item_title'],'not confirmed'));sa_check('Missing terminal does not invent failure or interruption event',array_column($rows,'event_key')===['subdomain-request:'.$dispatch['id']]);
    sa_terminal($dispatch['id']);sa_check('Attempted terminal stays uncertain',array_column(sa_snapshot()['rows'],'kind')===['subdomain_uncertain','subdomain_reviewed']);
    sa_readback($dispatch['id'],3,'different');sa_readback($dispatch['id'],4,'unavailable');sa_readback($dispatch['id'],5,'observed');$rows=sa_snapshot()['rows'];
    sa_check('Every independent readback retains its own truthful result',array_column($rows,'kind')===['subdomain_observed','subdomain_unavailable','subdomain_different','subdomain_uncertain','subdomain_reviewed']);
    sa_check('Matching hostname never promises DNS or website readiness',str_contains($rows[0]['item_title'],'does not confirm DNS or website readiness'));
    sa_check('Equal-second results use deterministic descending ASCII event keys',array_column(array_slice($rows,0,3),'source_id')===[sa_id(5),sa_id(4),sa_id(3)]);
    $keys=['event_key','kind','source_id','target_id','job_id','client_id','project_title','item_title','occurred_at'];
    sa_check('Rows expose only the closed activity DTO',array_reduce($rows,static fn($ok,$row)=>$ok&&array_keys($row)===$keys&&$row['job_id']===null&&strlen($row['event_key'])<80,true));
    sa_check('Paths credentials proof and private response data cannot enter activity',!str_contains(json_encode($rows),'SECRET')&&!str_contains(json_encode($rows),'owner_request_sha256'));
    sa_check('Source timestamps are retained without current-time replacement',$rows[0]['occurred_at']===gmdate('Y-m-d H:i:s',1700000002));
    sa_check('Replays preserve notice identity and rows',sa_snapshot()['rows']===$rows);
    $GLOBALS['m']['terminals'][$dispatch['id']]['document']['recovered_from_ledger']=true;sa_check('Local terminal recovery cannot duplicate the stable terminal event',sa_snapshot()['rows']===$rows);
    $GLOBALS['m']['terminals'][$dispatch['id']]['document']['phase']='not_sent';$GLOBALS['m']['check_rows']=[];sa_check('Only explicit not-sent terminal gets not-sent wording',sa_snapshot()['rows'][0]['kind']==='subdomain_not_sent');
    sa_reset();$q=sa_event('request',1);sa_event('decline',2,$q);$_SESSION=['client_id'=>8,'client_auth'=>'other-auth'];sa_check('Different primary client cannot see original request notices',sa_snapshot('client',8)['rows']===[]);
    foreach(['client_auth','assignment','credential','assigning_owner','unlinked'] as $mode){sa_reset();$q=sa_event('request',1);sa_event('decline',2,$q);
        if($mode==='client_auth'){$GLOBALS['m']['clients'][7]['auth_version']='rotated';$_SESSION['client_auth']='rotated';}
        if($mode==='assignment')$GLOBALS['m']['static']['binding']['event_id']=99;
        if($mode==='credential')$GLOBALS['m']['static']['binding']['credential_sha256']=str_repeat('e',64);
        if($mode==='assigning_owner')$GLOBALS['m']['static']['owner_auth_sha256']=str_repeat('f',64);
        if($mode==='unlinked')$GLOBALS['m']['unlinked']=true;
        sa_check('Client notices disappear after '.$mode.' change',sa_snapshot()['rows']===[]);
        sa_check('Current owner retains stale request notice after '.$mode.' change',array_column(sa_snapshot('admin',1)['rows'],'kind')===['subdomain_requested']);
    }
    sa_reset();$q=sa_event('request',1);sa_event('decline',2,$q);$GLOBALS['m']['owner']['id']=9;sa_check('A different current owner can review historical client requests',sa_snapshot('admin',9)['rows'][0]['kind']==='subdomain_requested');
    sa_reset();$q=sa_event('request',1);sa_event('decline',2,$q);$GLOBALS['m']['source_fault']=true;sa_refuse('Damaged retained original observation is not eligibility absence',fn()=>sa_snapshot());
    sa_reset();$GLOBALS['m']['events_fault']=new RuntimeException('Lost event head');sa_refuse('Event integrity failures propagate instead of empty success',fn()=>sa_snapshot());
    sa_reset();$GLOBALS['m']['events_fault']=new PDOException('Modeled database failure');sa_refuse('PDO failures retain their exact class for outer 503 handling',fn()=>sa_snapshot(),PDOException::class);
    sa_reset();$q=sa_event('request',1);$d=sa_event('dispatch',2,$q);unset($GLOBALS['m']['owners'][$d['id']]);sa_refuse('Missing reciprocal owner approval refuses notification projection',fn()=>sa_snapshot());
    sa_reset();$q=sa_event('request',1);$d=sa_event('dispatch',2,$q);$GLOBALS['m']['owners'][$d['id']]['document']['client_origin']['request_sha256']=str_repeat('0',64);sa_refuse('Wrong original request commitment cannot become a notice',fn()=>sa_snapshot());
    sa_reset();$q=sa_event('request',1);$d=sa_event('dispatch',2,$q);sa_terminal($d['id']);$GLOBALS['m']['terminal_fault']=true;sa_refuse('Terminal integrity failure is not not-sent',fn()=>sa_snapshot());
    sa_reset();$q=sa_event('request',1);$d=sa_event('dispatch',2,$q);sa_terminal($d['id']);sa_readback($d['id'],3,'different');$GLOBALS['m']['check_fault']=true;sa_refuse('Readback integrity failure is not a different observation',fn()=>sa_snapshot());
    sa_reset();$q=sa_event('request',1);$GLOBALS['m']['events'][]=$q;sa_refuse('Duplicate source event cannot fabricate duplicate notices',fn()=>sa_snapshot());
    sa_reset();$q=sa_event('request',1);sa_refuse('Closed row builder rejects outcome-family confusion',fn()=>provider_subdomain_activity_row('terminal',sa_id(2),'subdomain_observed',$q,1700000000),LogicException::class);
    sa_refuse('Closed row builder rejects invented out-of-range timestamp',fn()=>provider_subdomain_activity_row('request',sa_id(1),'subdomain_requested',$q,2147483648),LogicException::class);
    sa_reset();$q=sa_event('request',1);$d=sa_event('dispatch',2,$q);sa_terminal($d['id']);sa_readback($d['id'],3,'observed');$before=[$GLOBALS['m']['events'],$GLOBALS['m']['owners'],$GLOBALS['m']['terminals'],$GLOBALS['m']['check_rows'],$GLOBALS['m']['resource'],$GLOBALS['m']['static']];sa_snapshot();
    sa_check('Projection leaves all modeled source business state unchanged',$before===[$GLOBALS['m']['events'],$GLOBALS['m']['owners'],$GLOBALS['m']['terminals'],$GLOBALS['m']['check_rows'],$GLOBALS['m']['resource'],$GLOBALS['m']['static']]);
    sa_check('Top-level readiness executes once per snapshot',$GLOBALS['m']['ready_calls']===1);
    $passed=true;$error=null;
} catch(Throwable $e) {$passed=false;$error=['class'=>$e::class,'message'=>$e->getMessage()];}
echo json_encode(['passed'=>$passed,'scope'=>'Actual projection, pure event documents and actor/current-source functions with modeled SQL/history prerequisites; no native SQL or installed acceptance.','count'=>count($checks),'checks'=>$checks,'error'=>$error],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
