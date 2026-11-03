<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
use CajunWebPro\Foundation\Access\{AccessContext,PermissionPolicy};
define('SCSD_LIBRARY',true);require_once __DIR__.'/support_stackcp_documents.php';
foreach(['AuthorizationVersion','AccessContext','PermissionPolicy'] as $file) require_once dirname(__DIR__).'/foundation/access/'.$file.'.php';
$checks=[];$completed=false;
set_error_handler(static function(int $level,string $message):never {throw new ErrorException('PHP diagnostic',0,$level);});
function ssp_ok(string $name,bool $passed):void {global $checks;if (in_array($name,array_column($checks,'name'),true)) throw new LogicException('Duplicate check.');$checks[]=['name'=>$name,'passed'=>$passed];if (!$passed) throw new RuntimeException($name);}
function ssp_no(string $name,callable $call,string $class=RuntimeException::class):void {try {$call();}catch (Throwable $e) {ssp_ok($name,$e instanceof $class);return;}ssp_ok($name,false);}
/** Fixed production top-level function slices, with only SQL/authority dependencies modeled. */
function ssp_load(string $path,array $names):void {
    $source=file_get_contents($path);preg_match_all('/^function ([a-z_]+)\(/m',$source,$matches,PREG_OFFSET_CAPTURE);
    foreach ($names as $name) {
        $found=false;foreach ($matches[1] as $i=>[$candidate]) if ($candidate===$name) {$start=$matches[0][$i][1];$end=$matches[0][$i+1][1]??strlen($source);$slice=substr($source,$start,$end-$start);if($name==='provider_support_stackcp_events')$slice=str_replace('function provider_support_stackcp_events(', 'function ssp_real_events(', $slice);eval('use CajunWebPro\\Foundation\\Access\\AccessContext; use CajunWebPro\\Foundation\\Access\\PermissionPolicy; '.$slice);$found=true;break;}
        if (!$found) throw new RuntimeException('Missing production function.');
    }
}
class ProviderStackCpAccessUnavailable extends RuntimeException {}
class SspDb {public bool $tx=false;public function inTransaction():bool{return $this->tx;}}
function db():SspDb {return $GLOBALS['ssp_db'];}
function staff_locked(callable $f):mixed {$old=$GLOBALS['ssp_lock'];$GLOBALS['ssp_lock']=true;try{return $f();}finally{$GLOBALS['ssp_lock']=$old;}}
function staff_transaction(callable $f):mixed {if(db()->tx)throw new LogicException('Nested transaction.');$before=$GLOBALS['ssp_support_row'];$writes=$GLOBALS['ssp_writes'];db()->tx=true;try{return $f();}catch(Throwable $e){$GLOBALS['ssp_support_row']=$before;$GLOBALS['ssp_writes']=$writes;throw $e;}finally{db()->tx=false;}}
function provider_stackcp_guard(bool $write=false):void {if(!$GLOBALS['ssp_lock']||($write&&!db()->tx))throw new LogicException('Missing boundary.');}
function support_current():?array {return $GLOBALS['ssp_support'];}
function studio_client():?array {throw new LogicException('Support must not normalize client cookies.');}
function provider_settings_manager(bool $ready=true):array {$GLOBALS['ssp_normalizations']++;if($GLOBALS['ssp_owner']===null)throw new RuntimeException('Owner required.');return $GLOBALS['ssp_owner'];}
function provider_settings_owner(string $password):array {if($password!=='owner-password')throw new InvalidArgumentException('Your current password is not correct.');return provider_settings_manager();}
function table(string $name):string {return $name;}
function provider_stackcp_actor_current(int $id,string $version):bool {return !isset($GLOBALS['ssp_invalid_owners'][$id])&&$version===ssd_id(12);}
function provider_stackcp_ready(string $kind):bool {if($GLOBALS['ssp_corruption'])throw new RuntimeException('Original signed history is damaged.');return true;}
function provider_stackcp_local_binding(string $profile,int $package):?array {return $GLOBALS['ssp_local'];}
function provider_stackcp_vault():mixed {return null;}
function provider_stackcp_latest(string $kind,string $scope):?array {return $GLOBALS['ssp_rows'][$kind]??null;}
function provider_stackcp_row(string $kind,array $row,mixed $vault):array {return $row['document'];}
function provider_support_stackcp_events():?array {return $GLOBALS['ssp_events'];}
function provider_support_stackcp_hmac(string $purpose,string $bytes):string {return hash_hmac('sha256',$purpose.'|'.$bytes,'synthetic-test-key');}
function provider_stackcp_launch_resource(string $profile,int $package):array {if($GLOBALS['ssp_candidate']===null)throw new ProviderStackCpAccessUnavailable('Refresh required.');return $GLOBALS['ssp_candidate'];}
function provider_stackcp_access_package_source(string $id,int $package):array {return ['package'=>['name'=>'Client hosting <example>']];}
function staff_ready():bool{return $GLOBALS['ssp_staff_ready'];}
function support_ready():bool{return $GLOBALS['ssp_support_ready'];}
function setting(string $key):string {return $key==='support_policy_confirmed'?$GLOBALS['ssp_policy_confirmed']:'';}
function admin_user():?array{return $GLOBALS['ssp_admin'];}
function staff_profile(array $user):?array {return $GLOBALS['ssp_profiles'][$user['id']]??null;}
function support_end_row(array $row,string $reason):void {$GLOBALS['ssp_support_row']['ended_at']=time();$GLOBALS['ssp_writes']++;}
function one(string $sql,array $args=[]):?array {
    if($GLOBALS['ssp_storage_mode']??false)return ssp_storage_one($sql,$args);
    if(str_starts_with($sql,'SELECT * FROM users WHERE id='))return $GLOBALS['ssp_users'][$args[0]]??null;
    if(str_starts_with($sql,'SELECT id,name FROM users')){ $u=$GLOBALS['ssp_users'][$args[0]]??null;return $u?['id'=>$u['id'],'name'=>$u['name']]:null;}
    if(str_starts_with($sql,'SELECT * FROM support_sessions')) { $r=$GLOBALS['ssp_support_row'];return $r&&$r['id']===$args[0]&&$r['binding_hash']===$args[1]?$r:null; }
    if(str_starts_with($sql,'SELECT allowed FROM support_clients'))return ['allowed'=>$GLOBALS['ssp_policy_allowed']];
    if(str_starts_with($sql,'SELECT * FROM studio_clients'))return $GLOBALS['ssp_client'];
    if(str_starts_with($sql,'SELECT id,name FROM studio_clients'))return $GLOBALS['ssp_client']?['id'=>9,'name'=>'Primary client <name>']:null;
    throw new LogicException('Unexpected modeled SQL: '.$sql);
}
function all(string $sql,array $args=[]):array {
    if($GLOBALS['ssp_storage_mode']??false)return ssp_storage_all($sql,$args);
    if($sql==='SELECT profile_id,package_id FROM provider_package_links ORDER BY profile_id,package_id')return [['profile_id'=>ssd_id(1),'package_id'=>701]];
    if($sql==='SELECT id,name FROM users ORDER BY name,id LIMIT 10001')return array_map(fn($u)=>['id'=>$u['id'],'name'=>$u['name']],array_values($GLOBALS['ssp_users']));
    throw new LogicException('Unexpected modeled SQL.');
}
function query(string $sql,array $args=[]):object {if($GLOBALS['ssp_storage_mode']??false)return new SspScalar(ssp_storage_scalar($sql,$args));throw new LogicException('Unexpected SQL effect.');}
function provider_support_stackcp_append(string $kind,string $phase,array $payload,?array $seed=null):array {
    provider_stackcp_guard(true);$events=$GLOBALS['ssp_events'];$last=$events?end($events):null;$b=$payload['resource']['binding'];
    $d=provider_support_stackcp_document(['version'=>1,'id'=>$seed['id'],'ordinal'=>count($events)+1,'previous_sha256'=>$last?hash('sha256',provider_support_stackcp_json($last)):str_repeat('0',64),'permission_id'=>$seed['id'],'admission_id'=>'','profile_id'=>$b['profile_id'],'package_id'=>$b['package_id'],'client_id'=>$b['client_id'],'staff_id'=>$payload['resource']['staff']['id'],'actor'=>$seed['actor'],'recorded_epoch'=>1760000001,'kind'=>$kind,'phase'=>$phase,'payload'=>$payload]);
    provider_support_stackcp_project([...$events,$d]);$GLOBALS['ssp_events'][]=$d;$GLOBALS['ssp_writes']++;return $d;
}
function ssp_reset():void {
    $GLOBALS['ssp_storage_mode']=false;$_SESSION=[];$_GET=[];$GLOBALS['ssp_db']=new SspDb();$GLOBALS['ssp_lock']=true;$GLOBALS['ssp_support']=null;$GLOBALS['ssp_normalizations']=0;$GLOBALS['ssp_writes']=0;$GLOBALS['ssp_invalid_owners']=[];$GLOBALS['ssp_corruption']=false;
    $GLOBALS['ssp_owner']=['id'=>2,'auth_version'=>ssd_id(12)];$GLOBALS['ssp_client']=['id'=>9,'active'=>1,'auth_version'=>ssd_id(4),'password_hash'=>password_hash('client-password',PASSWORD_DEFAULT)];$GLOBALS['ssp_events']=[ssd_permission()];$GLOBALS['ssp_candidate']=ssd_resource();unset($GLOBALS['ssp_candidate']['staff']);$b=ssd_resource()['binding'];
    $local=[];foreach(['link_id','package_id','client_id','client_auth_version','profile_revision','key_fingerprint','link_sha256','event_id','event_sha256','audit_id','audit_sha256'] as $key)$local[$key]=$b[$key];$local['profile_credentials_sha256']=$b['credential_sha256'];$GLOBALS['ssp_local']=$local;
    $a=$b;$a['id']=$b['assignment_id'];$a['revision']=$b['assignment_revision'];$a['state']='unverified';
    $k=['id'=>$b['auth_id'],'revision'=>$b['auth_revision'],'enabled'=>true,'ciphertext_sha256'=>$b['auth_ciphertext_sha256'],'profile_revision'=>$b['profile_revision'],'profile_credentials_sha256'=>$b['credential_sha256'],'actor_id'=>2,'actor_auth_version'=>ssd_id(12)];
    $GLOBALS['ssp_staff_ready']=true;$GLOBALS['ssp_support_ready']=true;$GLOBALS['ssp_policy_confirmed']='1';$GLOBALS['ssp_policy_allowed']=1;
    $staff=['id'=>3,'name'=>'Support <staff>','auth_version'=>ssd_id(30),'password_hash'=>'activated'];$GLOBALS['ssp_users']=[3=>$staff,4=>['id'=>4,'name'=>'Other staff','auth_version'=>ssd_id(40),'password_hash'=>'activated']];
    $GLOBALS['ssp_profiles']=[3=>$staff+['role'=>'administrator','enabled'=>1,'permissions'=>'["support.login","support.stackcp"]'],4=>$GLOBALS['ssp_users'][4]+['role'=>'administrator','enabled'=>1,'permissions'=>'["support.login","support.stackcp"]']];
    $GLOBALS['ssp_admin']=$staff;$_SESSION=['uid'=>3,'auth_version'=>ssd_id(30),'support_binding'=>str_repeat('7',64)];
    $GLOBALS['ssp_support_row']=['id'=>ssd_id(31),'binding_hash'=>hash('sha256',$_SESSION['support_binding']),'actor_id'=>3,'actor_version'=>ssd_id(30),'client_id'=>9,'client_version'=>ssd_id(4),'ended_at'=>null,'expires_at'=>time()+900,'last_seen_at'=>time()-10];
    $GLOBALS['ssp_rows']=['assignment'=>['document'=>$a,'document_sha256'=>$b['assignment_sha256'],'proof'=>$b['assignment_proof']],'auth'=>['document'=>$k,'document_sha256'=>$b['auth_sha256'],'proof'=>$b['auth_proof']]];
}
class SspScalar {public function __construct(private mixed $value){}public function fetchColumn():mixed{return $this->value;}}
function config():array{return ['db'=>['prefix'=>'ssp_']];}
function provider_stackcp_access_ready():bool{return true;}
function provider_support_stackcp_validate_schema():void {if($GLOBALS['ssp_store']['schema_bad'])throw new RuntimeException('Modeled schema rejection.');}
function provider_support_stackcp_sources(array $event):void {if($GLOBALS['ssp_store']['source_bad'])throw new RuntimeException('Modeled signed source rejection.');}
function ssp_storage_fixture(bool $populated=true):void {
    ssp_reset();$GLOBALS['ssp_storage_mode']=true;$p=['version'=>1,'actor'=>['kind'=>'owner','id'=>2,'auth_version'=>ssd_id(12)],'recorded_epoch'=>1760000000];$raw=provider_support_stackcp_json($p);
    $events=$populated?[ssd_permission()]:[];$head=$events?hash('sha256',provider_support_stackcp_json($events[0])):str_repeat('0',64);$c=['version'=>1,'count'=>count($events),'permission_count'=>count($events),'admission_count'=>0,'head'=>$head];
    $settings=['schema_version'=>'1','prepared'=>$raw,'prepared_proof'=>provider_support_stackcp_hmac('prepared',$raw)];foreach(array_slice($c,1,null,true)as $key=>$value)$settings[$key]=(string)$value;$settings['checkpoint_proof']=provider_support_stackcp_hmac('checkpoint',provider_support_stackcp_json($c));
    $rows=[];foreach($events as $e){$row=[];foreach(['id','ordinal','permission_id','admission_id','profile_id','package_id','client_id','staff_id','kind','phase']as$key)$row[$key]=$e[$key];$row+=['actor_staff_id'=>$e['actor']['id'],'document_json'=>provider_support_stackcp_json($e),'document_sha256'=>hash('sha256',provider_support_stackcp_json($e)),'proof'=>provider_support_stackcp_hmac('event',provider_support_stackcp_json($e)),'audit_count'=>1,'audit_id'=>2];$rows[]=$row;}
    $GLOBALS['ssp_store']=['settings'=>$settings,'tables'=>[['TABLE_NAME'=>'ssp_provider_support_stackcp_events']],'actions'=>[['action'=>'provider.support_stackcp_prepared'],['action'=>'provider.support_stackcp_event']],'rows'=>$rows,'physical'=>count($rows),'audit_count'=>count($rows),'prepared_count'=>1,'prepared_match'=>1,'event_audit'=>['user_id'=>2,'created_at'=>'2025-10-09 08:53:20'],'schema_bad'=>false,'source_bad'=>false];
}
function ssp_storage_all(string $sql,array $args):array {
    $s=$GLOBALS['ssp_store'];
    if(str_starts_with($sql,'SELECT name,value FROM settings')){ $out=[];foreach($s['settings']as$key=>$value)$out[]=['name'=>'provider_support_stackcp_'.$key,'value'=>$value];return $out; }
    if(str_starts_with($sql,'SELECT TABLE_NAME FROM information_schema.tables'))return $s['tables'];
    if(str_starts_with($sql,'SELECT DISTINCT action FROM audit'))return $s['actions'];
    if(str_starts_with($sql,'SELECT r.*,'))return array_values(array_filter($s['rows'],fn($r)=>(int)$r['ordinal']>$args[0]));
    throw new LogicException('Unexpected journal all: '.$sql);
}
function ssp_storage_one(string $sql,array $args):?array {
    if(str_starts_with($sql,'SELECT ENGINE'))return ['ENGINE'=>'InnoDB'];
    if($sql==='SELECT user_id,created_at FROM audit WHERE id=?')return $GLOBALS['ssp_store']['event_audit'];
    throw new LogicException('Unexpected journal one: '.$sql);
}
function ssp_storage_scalar(string $sql,array $args):int {
    $s=$GLOBALS['ssp_store'];
    if($sql==='SELECT COUNT(*) FROM provider_support_stackcp_events')return $s['physical'];
    if($sql==="SELECT COUNT(*) FROM audit WHERE BINARY action='provider.support_stackcp_event'")return $s['audit_count'];
    if($sql==="SELECT COUNT(*) FROM audit WHERE BINARY action='provider.support_stackcp_prepared'")return $s['prepared_count'];
    if(str_starts_with($sql,"SELECT COUNT(*) FROM audit WHERE BINARY action='provider.support_stackcp_prepared' AND"))return $s['prepared_match'];
    if($sql==="SELECT id FROM audit WHERE BINARY action='provider.support_stackcp_prepared'")return 1;
    throw new LogicException('Unexpected journal scalar: '.$sql);
}
try {
    ssp_load(CWP_CODE_ROOT.'/app/providers/support_stackcp_permissions.php',['provider_support_stackcp_owner','provider_support_stackcp_actor','provider_support_stackcp_target','provider_support_stackcp_client_allowed','provider_support_stackcp_current','provider_support_stackcp_permission_candidate','provider_support_stackcp_permission_review','provider_support_stackcp_permission_change','provider_support_stackcp_owner_snapshot','provider_support_stackcp_staff_snapshot']);
    ssp_load(CWP_CODE_ROOT.'/app/providers/support_stackcp_storage.php',['provider_support_stackcp_resource','provider_support_stackcp_permission','provider_support_stackcp_row','provider_support_stackcp_checkpoint','provider_support_stackcp_setting_names','provider_support_stackcp_events']);
    ssp_load(CWP_CODE_ROOT.'/app/support/sessions.php',['support_binding','support_check']);
    ssp_load(CWP_CODE_ROOT.'/app/staff/accounts.php',['staff_can']);
    ssp_reset();$permission=ssd_permission();
    ssp_ok('Actual static validator admits the exact staff support permission',provider_support_stackcp_current($permission));
    $GLOBALS['ssp_owner']=null;ssp_ok('Staff static validation never depends on ambient owner login',provider_support_stackcp_current($permission));
    $GLOBALS['ssp_candidate']=null;ssp_ok('Expired or later observations do not expire stable owner permission',provider_support_stackcp_current($permission));
    ssp_reset();$GLOBALS['ssp_candidate']['binding']['source_id']=ssd_id(99);$GLOBALS['ssp_candidate']['binding']['membership_id']=ssd_id(98);ssp_ok('Legitimate fresh read replacement does not change static authority',provider_support_stackcp_current($permission));
    foreach (['client_id','client_auth_version','profile_revision','profile_credentials_sha256','key_fingerprint','link_id','link_sha256','event_id','event_sha256','audit_id','audit_sha256','package_id'] as $key) {ssp_reset();$v=$GLOBALS['ssp_local'][$key];$GLOBALS['ssp_local'][$key]=is_int($v)?$v+1:str_repeat('9',strlen($v));ssp_ok('Current permission refuses changed '.$key,!provider_support_stackcp_current($permission));}
    foreach (['assignment'=>['id','revision','state','stack_identity','actor_auth_version'],'auth'=>['id','revision','enabled','ciphertext_sha256','profile_revision','profile_credentials_sha256','actor_auth_version']] as $family=>$keys) foreach($keys as $key){ssp_reset();$v=$GLOBALS['ssp_rows'][$family]['document'][$key];$GLOBALS['ssp_rows'][$family]['document'][$key]=is_bool($v)?!$v:(is_int($v)?$v+1:'changed');ssp_ok('Current permission refuses changed '.$family.' '.$key,!provider_support_stackcp_current($permission));}
    foreach(['assignment','auth'] as $family)foreach(['document_sha256','proof'] as $key){ssp_reset();$GLOBALS['ssp_rows'][$family][$key]=str_repeat('9',64);ssp_ok('Current permission refuses changed '.$family.' '.$key,!provider_support_stackcp_current($permission));}
    ssp_reset();$GLOBALS['ssp_invalid_owners'][2]=true;ssp_ok('Original authorizing owner revocation invalidates permission',!provider_support_stackcp_current($permission));
    ssp_reset();$GLOBALS['ssp_local']=null;ssp_ok('Removed hosting assignment invalidates permission',!provider_support_stackcp_current($permission));
    ssp_reset();$GLOBALS['ssp_corruption']=true;ssp_no('Damaged original authority is not silently absent',fn()=>provider_support_stackcp_current($permission));
    ssp_reset();$GLOBALS['ssp_rows']['auth']=null;ssp_no('Missing protected authority history refuses',fn()=>provider_support_stackcp_current($permission));
    ssp_reset();$GLOBALS['ssp_lock']=false;ssp_no('Static validator requires actual owning mutex',fn()=>provider_support_stackcp_current($permission),LogicException::class);
    ssp_reset();ssp_ok('Target authorization ignores owner session version',provider_support_stackcp_target(3)!==null);$_SESSION['auth_version']=ssd_id(12);ssp_ok('Owner can review other actual staff auth version',provider_support_stackcp_target(3)!==null);
    foreach(['support.login','support.stackcp'] as $permissionName){ssp_reset();$GLOBALS['ssp_profiles'][3]['permissions']=json_encode(array_values(array_diff(['support.login','support.stackcp'],[$permissionName])));ssp_ok('Both target permissions required '.$permissionName,provider_support_stackcp_target(3)===null);}
    foreach(['contact_id','contact_auth'] as $key){ssp_reset();$_SESSION[$key]=null;ssp_no('Raw '.$key.' presence denied before support lookup',fn()=>staff_transaction(fn()=>provider_support_stackcp_actor(ssd_id(31))));ssp_no('Owner raw '.$key.' refused',fn()=>provider_support_stackcp_owner());}
    ssp_reset();ssp_no('Actor requires transaction as well as mutex',fn()=>provider_support_stackcp_actor(ssd_id(31)),LogicException::class);
    ssp_reset();$_GET['support']='';ssp_no('Malformed support route cannot adopt supplied session',fn()=>staff_transaction(fn()=>provider_support_stackcp_actor(ssd_id(31))));
    ssp_reset();$_SESSION['client_id']=999;$_SESSION['client_auth']='unrelated';$before=$GLOBALS['ssp_support_row'];$actor=staff_transaction(fn()=>provider_support_stackcp_actor(ssd_id(31)));
    ssp_ok('Actual support check returns staff and client without adopting unrelated client cookies',$actor['kind']==='support'&&$actor['id']===3&&$actor['client_id']===9&&$actor['support_id']===ssd_id(31));
    ssp_ok('Valid authority check does not touch idle time or expiry',$GLOBALS['ssp_support_row']===$before&&$GLOBALS['ssp_writes']===0);
    ssp_ok('Actor binds actual browser hash and expiry',$actor['binding_sha256']===$before['binding_hash']&&$actor['expires_epoch']===$before['expires_at']);
    foreach(['expired','idle','client_auth','staff_auth','policy','global_policy','staff_permission','no_staff','other_browser','wrong_session','ended'] as $case){
        ssp_reset();switch($case){case 'expired':$GLOBALS['ssp_support_row']['expires_at']=time();break;case 'idle':$GLOBALS['ssp_support_row']['last_seen_at']=time()-300;break;case 'client_auth':$GLOBALS['ssp_client']['auth_version']=ssd_id(88);break;case 'staff_auth':$GLOBALS['ssp_admin']['auth_version']=ssd_id(88);break;case 'policy':$GLOBALS['ssp_policy_allowed']=0;break;case 'global_policy':$GLOBALS['ssp_policy_confirmed']='0';break;case 'staff_permission':$GLOBALS['ssp_profiles'][3]['permissions']='["support.login"]';break;case 'no_staff':$GLOBALS['ssp_admin']=null;break;case 'other_browser':$_SESSION['support_binding']=str_repeat('8',64);break;case 'wrong_session':$GLOBALS['ssp_support_row']['id']=ssd_id(99);break;case 'ended':$GLOBALS['ssp_support_row']['ended_at']=time();break;}
        $before=$GLOBALS['ssp_support_row'];ssp_no('Support authority refuses '.$case,fn()=>staff_transaction(fn()=>provider_support_stackcp_actor(ssd_id(31))));ssp_ok('Denied authority rolls back support side effects '.$case,$GLOBALS['ssp_support_row']===$before&&$GLOBALS['ssp_writes']===0);
    }
    foreach(['target_auth','target_permissions','target_disabled','policy','global_policy'] as $case){ssp_reset();switch($case){case 'target_auth':$GLOBALS['ssp_profiles'][3]['auth_version']=ssd_id(88);break;case 'target_permissions':$GLOBALS['ssp_profiles'][3]['permissions']='["support.login"]';break;case 'target_disabled':$GLOBALS['ssp_profiles'][3]=null;break;case 'policy':$GLOBALS['ssp_policy_allowed']=0;break;case 'global_policy':$GLOBALS['ssp_policy_confirmed']='0';break;}ssp_ok('Current delegation refuses '.$case,!provider_support_stackcp_current($permission));}
    ssp_reset();ssp_no('Wrong owner password refuses before permission writes',fn()=>provider_support_stackcp_permission_change(ssd_id(1),701,3,str_repeat('a',64),false,true,'bad'),InvalidArgumentException::class);ssp_ok('Wrong password has no permission effect',$GLOBALS['ssp_writes']===0);
    ssp_reset();$review=provider_support_stackcp_permission_review(ssd_id(1),701,3);ssp_ok('Owner safe review has meaningful labels and separate revoke proof',$review['enabled']&&$review['client_name']==='Primary client <name>'&&$review['package_name']==='Client hosting <example>'&&$review['review_proof']===$review['revoke_review']&&$review['enable_review']!==$review['revoke_review']);
    ssp_no('Permission change requires explicit whole-user confirmation',fn()=>provider_support_stackcp_permission_change(ssd_id(1),701,3,$review['revoke_review'],false,false,'owner-password'),InvalidArgumentException::class);
    ssp_no('Enable proof cannot be substituted into revoke',fn()=>provider_support_stackcp_permission_change(ssd_id(1),701,3,$review['enable_review'],false,true,'owner-password'));
    $GLOBALS['ssp_candidate']=null;$GLOBALS['ssp_local']=null;$stale=provider_support_stackcp_permission_review(ssd_id(1),701,3);ssp_ok('Stale enabled permission remains explicitly revocable',$stale['stale']&&!$stale['can_enable']&&$stale['revoke_review']!==''&&$stale['review_proof']===$review['revoke_review']);
    $result=provider_support_stackcp_permission_change(ssd_id(1),701,3,$stale['revoke_review'],false,true,'owner-password');ssp_ok('Actual revoke appends a new owner event retaining old history',!$result['enabled']&&$GLOBALS['ssp_writes']===1&&count($GLOBALS['ssp_events'])===2&&$GLOBALS['ssp_events'][0]===$permission);
    ssp_no('Same stale permission POST cannot create another revision',fn()=>provider_support_stackcp_permission_change(ssd_id(1),701,3,$stale['revoke_review'],false,true,'owner-password'));
    ssp_reset();$review=provider_support_stackcp_permission_review(ssd_id(1),701,3);$GLOBALS['ssp_candidate']['grants_id']=ssd_id(88);ssp_no('Captured enable review is never silently replaced by a fresh observation',fn()=>provider_support_stackcp_permission_change(ssd_id(1),701,3,$review['enable_review'],true,true,'owner-password'));
    ssp_reset();$snapshot=provider_support_stackcp_staff_snapshot(ssd_id(31));ssp_ok('Support cards contain only delegated current permission and safe package labels',count($snapshot['items'])===1&&array_keys($snapshot['items'][0])===['profile_id','package_id','package_name','link_id','permission_id']);
    ssp_ok('Exact unrelated link selection leaks no grant',provider_support_stackcp_staff_snapshot(ssd_id(31),1,ssd_id(99))['items']===[]);
    $GLOBALS['ssp_local']['client_auth_version']=ssd_id(77);ssp_ok('Client auth change hides permission cards',provider_support_stackcp_staff_snapshot(ssd_id(31))['items']===[]);
    ssp_reset();$GLOBALS['ssp_events']=null;ssp_ok('Clean absent family gives owner preparation screen',provider_support_stackcp_owner_snapshot()['reason']==='unprepared');ssp_ok('Clean absent family gives no staff launch cards',provider_support_stackcp_staff_snapshot(ssd_id(31))['items']===[]);ssp_ok('Read-only snapshots never append permissions',$GLOBALS['ssp_writes']===0);
    ssp_reset();$row=[];foreach(['id','ordinal','permission_id','admission_id','profile_id','package_id','client_id','staff_id','kind','phase'] as $key)$row[$key]=$permission[$key];$row['actor_staff_id']=2;$row['document_json']=provider_support_stackcp_json($permission);$row['document_sha256']=hash('sha256',$row['document_json']);$row['proof']=provider_support_stackcp_hmac('event',$row['document_json']);
    ssp_ok('Actual row validator binds canonical signed permission and indexed fields',provider_support_stackcp_row($row)===$permission);
    foreach(['actor_staff_id'=>3,'staff_id'=>4,'client_id'=>10,'permission_id'=>ssd_id(99),'document_sha256'=>str_repeat('0',64),'proof'=>str_repeat('0',64)] as $key=>$value){$bad=$row;$bad[$key]=$value;ssp_no('Signed row refuses changed '.$key,fn()=>provider_support_stackcp_row($bad));}
    $admission=ssd_admitted()[1];foreach(['id','ordinal','permission_id','admission_id','profile_id','package_id','client_id','staff_id','kind','phase'] as $key)$row[$key]=$admission[$key];$row['actor_staff_id']=3;$row['document_json']=provider_support_stackcp_json($admission);$row['document_sha256']=hash('sha256',$row['document_json']);$row['proof']=provider_support_stackcp_hmac('event',$row['document_json']);ssp_ok('Support row retains actual staff audit identity',provider_support_stackcp_row($row)===$admission);
    $row['actor_staff_id']=2;ssp_no('Support row cannot substitute owner audit principal',fn()=>provider_support_stackcp_row($row));
    $checkpoint=['version'=>1,'count'=>1,'permission_count'=>1,'admission_count'=>0,'head'=>hash('sha256',provider_support_stackcp_json($permission))];$settings=[];foreach(array_slice($checkpoint,1,null,true) as $key=>$value)$settings[$key]=(string)$value;$settings['checkpoint_proof']=provider_support_stackcp_hmac('checkpoint',provider_support_stackcp_json($checkpoint));ssp_ok('Actual checkpoint validates independent counts and retained head',provider_support_stackcp_checkpoint($settings)===$checkpoint);
    foreach(['count','permission_count','admission_count','head'] as $key){$bad=$settings;$bad[$key]=$key==='head'?str_repeat('f',64):'2';ssp_no('Checkpoint refuses changed '.$key,fn()=>provider_support_stackcp_checkpoint($bad));}
    ssp_reset();$owner=provider_support_stackcp_owner_snapshot(1,3);ssp_ok('Owner staff selection retains exact selected staff and readable names',$owner['selected_staff_id']===3&&$owner['items'][0]['staff_id']===3&&$owner['items'][0]['staff_name']==='Support <staff>');
    ssp_no('Nonexistent selected staff does not silently select another',fn()=>provider_support_stackcp_owner_snapshot(1,99),InvalidArgumentException::class);
    $GLOBALS['ssp_profiles'][3]=null;$stale=provider_support_stackcp_permission_review(ssd_id(1),701,3);ssp_ok('Disabled staff delegation remains explicitly revocable',$stale['stale']&&$stale['revoke_review']!==''&&!$stale['can_enable']);
    ssp_reset();$review=provider_support_stackcp_permission_review(ssd_id(1),701,3);ssp_no('Enable proof cannot be applied to another staff',fn()=>provider_support_stackcp_permission_change(ssd_id(1),701,4,$review['enable_review'],true,true,'owner-password'));
    ssp_storage_fixture();ssp_ok('Actual journal authenticates all indexed events and paired owner audits',ssp_real_events()===[ssd_permission()]);
    ssp_storage_fixture(false);ssp_ok('Prepared empty journal is ready without writing rows',ssp_real_events()===[]);
    ssp_storage_fixture(false);$GLOBALS['ssp_store']['settings']=[];$GLOBALS['ssp_store']['actions']=[];ssp_ok('Unprepared empty exact table remains clean absence',ssp_real_events()===null);
    foreach(['orphan_count','missing_marker','unknown_setting','unknown_table','unknown_audit','extra_audit','missing_audit','pair_actor','pair_time','pair_duplicate','pair_order','row_tamper','schema','source']as$case){
        ssp_storage_fixture();switch($case){case 'orphan_count':$GLOBALS['ssp_store']['physical']=2;break;case 'missing_marker':unset($GLOBALS['ssp_store']['settings']['schema_version']);break;case 'unknown_setting':$GLOBALS['ssp_store']['settings']['unknown']='1';break;case 'unknown_table':$GLOBALS['ssp_store']['tables'][]=['TABLE_NAME'=>'ssp_provider_support_stackcp_unknown'];break;case 'unknown_audit':$GLOBALS['ssp_store']['actions'][]=['action'=>'provider.support_stackcp_unknown'];break;case 'extra_audit':$GLOBALS['ssp_store']['audit_count']=2;break;case 'missing_audit':$GLOBALS['ssp_store']['audit_count']=0;break;case 'pair_actor':$GLOBALS['ssp_store']['event_audit']['user_id']=0;break;case 'pair_time':$GLOBALS['ssp_store']['event_audit']['created_at']='2025-10-09 08:53:21';break;case 'pair_duplicate':$GLOBALS['ssp_store']['rows'][0]['audit_count']=2;break;case 'pair_order':$GLOBALS['ssp_store']['rows'][0]['audit_id']=1;break;case 'row_tamper':$GLOBALS['ssp_store']['rows'][0]['staff_id']=4;break;case 'schema':$GLOBALS['ssp_store']['schema_bad']=true;break;case 'source':$GLOBALS['ssp_store']['source_bad']=true;break;}
        ssp_no('Actual whole journal refuses '.$case,fn()=>ssp_real_events());
    }
    ssp_storage_fixture(false);$GLOBALS['ssp_store']['settings']=[];$GLOBALS['ssp_store']['actions']=[];$GLOBALS['ssp_store']['physical']=1;ssp_no('Orphan row cannot masquerade as unprepared storage',fn()=>ssp_real_events());
    $completed=true;
} catch (Throwable $e) {$checks[]=['name'=>'Unexpected permissions test failure','passed'=>false,'error_class'=>get_class($e)];fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");}
$report=['passed'=>$completed&&!in_array(false,array_column($checks,'passed'),true),'scope'=>'Actual extracted permission/actor/current-resource/row/checkpoint/whole-journal and existing support_check functions with modeled SQL and existing authority dependencies; no native SQL/provider calls','checks'=>$checks];$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";if(isset($argv[1]))file_put_contents($argv[1],$json);echo $json;exit($report['passed']?0:1);
