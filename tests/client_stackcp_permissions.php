<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CCSD_LIBRARY',true);require_once __DIR__.'/client_stackcp_documents.php';
$checks=[];$completed=false;
set_error_handler(static function(int $level,string $message):never {throw new ErrorException('PHP diagnostic',0,$level);});
function csp_ok(string $name,bool $passed):void {global $checks;if (in_array($name,array_column($checks,'name'),true)) throw new LogicException('Duplicate check.');$checks[]=['name'=>$name,'passed'=>$passed];if (!$passed) throw new RuntimeException($name);}
function csp_no(string $name,callable $call,string $class=RuntimeException::class):void {try {$call();}catch (Throwable $e) {csp_ok($name,$e instanceof $class);return;}csp_ok($name,false);}
/** Fixed production top-level function slices, with only SQL/authority dependencies modeled. */
function csp_load(string $path,array $names):void {
    $source=file_get_contents($path);preg_match_all('/^function (provider_client_stackcp_[a-z_]+)\(/m',$source,$matches,PREG_OFFSET_CAPTURE);
    foreach ($names as $name) {
        $found=false;foreach ($matches[1] as $i=>[$candidate]) if ($candidate===$name) {$start=$matches[0][$i][1];$end=$matches[0][$i+1][1]??strlen($source);eval(substr($source,$start,$end-$start));$found=true;break;}
        if (!$found) throw new RuntimeException('Missing production function.');
    }
}
class ProviderStackCpAccessUnavailable extends RuntimeException {}
class CspDb {public bool $tx=false;public function inTransaction():bool{return $this->tx;}}
function db():CspDb {return $GLOBALS['csp_db'];}
function staff_locked(callable $f):mixed {$old=$GLOBALS['csp_lock'];$GLOBALS['csp_lock']=true;try{return $f();}finally{$GLOBALS['csp_lock']=$old;}}
function staff_transaction(callable $f):mixed {if(db()->tx)throw new LogicException('Nested transaction.');db()->tx=true;try{return $f();}finally{db()->tx=false;}}
function provider_stackcp_guard(bool $write=false):void {if(!$GLOBALS['csp_lock']||($write&&!db()->tx))throw new LogicException('Missing boundary.');}
function support_current():?array {return $GLOBALS['csp_support'];}
function studio_client():?array {$GLOBALS['csp_normalizations']++;return $GLOBALS['csp_client'];}
function provider_settings_manager(bool $ready=true):array {$GLOBALS['csp_normalizations']++;if($GLOBALS['csp_owner']===null)throw new RuntimeException('Owner required.');return $GLOBALS['csp_owner'];}
function provider_settings_owner(string $password):array {if($password!=='owner-password')throw new InvalidArgumentException('Your current password is not correct.');return provider_settings_manager();}
function table(string $name):string {return $name;}
function provider_stackcp_actor_current(int $id,string $version):bool {return !isset($GLOBALS['csp_invalid_owners'][$id])&&$version===csd_id(12);}
function provider_stackcp_ready(string $kind):bool {if($GLOBALS['csp_corruption'])throw new RuntimeException('Original signed history is damaged.');return true;}
function provider_stackcp_local_binding(string $profile,int $package):?array {return $GLOBALS['csp_local'];}
function provider_stackcp_vault():mixed {return null;}
function provider_stackcp_latest(string $kind,string $scope):?array {return $GLOBALS['csp_rows'][$kind]??null;}
function provider_stackcp_row(string $kind,array $row,mixed $vault):array {return $row['document'];}
function provider_client_stackcp_events():?array {return $GLOBALS['csp_events'];}
function provider_client_stackcp_hmac(string $purpose,string $bytes):string {return hash_hmac('sha256',$purpose.'|'.$bytes,'synthetic-test-key');}
function provider_stackcp_launch_resource(string $profile,int $package):array {if($GLOBALS['csp_candidate']===null)throw new ProviderStackCpAccessUnavailable('Refresh required.');return $GLOBALS['csp_candidate'];}
function provider_stackcp_access_package_source(string $id,int $package):array {return ['package'=>['name'=>'Client hosting <example>']];}
function one(string $sql,array $args=[]):?array {if(str_starts_with($sql,'SELECT id,name FROM studio_clients'))return ['id'=>9,'name'=>'Primary client <name>'];throw new LogicException('Unexpected modeled SQL.');}
function all(string $sql,array $args=[]):array {if($sql==='SELECT profile_id,package_id FROM provider_package_links ORDER BY profile_id,package_id')return [['profile_id'=>csd_id(1),'package_id'=>701]];throw new LogicException('Unexpected modeled SQL.');}
function provider_client_stackcp_append(string $kind,string $phase,array $payload,?array $seed=null):array {
    provider_stackcp_guard(true);$events=$GLOBALS['csp_events'];$last=$events?end($events):null;$b=$payload['resource']['binding'];
    $d=provider_client_stackcp_document(['version'=>1,'id'=>$seed['id'],'ordinal'=>count($events)+1,'previous_sha256'=>$last?hash('sha256',provider_client_stackcp_json($last)):str_repeat('0',64),'permission_id'=>$seed['id'],'admission_id'=>'','profile_id'=>$b['profile_id'],'package_id'=>$b['package_id'],'client_id'=>$b['client_id'],'actor'=>$seed['actor'],'recorded_epoch'=>1760000001,'kind'=>$kind,'phase'=>$phase,'payload'=>$payload]);
    provider_client_stackcp_project([...$events,$d]);$GLOBALS['csp_events'][]=$d;$GLOBALS['csp_writes']++;return $d;
}
function csp_reset():void {
    $_SESSION=[];$_GET=[];$GLOBALS['csp_db']=new CspDb();$GLOBALS['csp_lock']=true;$GLOBALS['csp_support']=null;$GLOBALS['csp_normalizations']=0;$GLOBALS['csp_writes']=0;$GLOBALS['csp_invalid_owners']=[];$GLOBALS['csp_corruption']=false;
    $GLOBALS['csp_owner']=['id'=>2,'auth_version'=>csd_id(12)];$GLOBALS['csp_client']=['id'=>9,'active'=>1,'auth_version'=>csd_id(4),'password_hash'=>password_hash('client-password',PASSWORD_DEFAULT)];$GLOBALS['csp_events']=[csd_permission()];$GLOBALS['csp_candidate']=csd_resource();$b=csd_resource()['binding'];
    $local=[];foreach(['link_id','package_id','client_id','client_auth_version','profile_revision','key_fingerprint','link_sha256','event_id','event_sha256','audit_id','audit_sha256'] as $key)$local[$key]=$b[$key];$local['profile_credentials_sha256']=$b['credential_sha256'];$GLOBALS['csp_local']=$local;
    $a=$b;$a['id']=$b['assignment_id'];$a['revision']=$b['assignment_revision'];$a['state']='unverified';
    $k=['id'=>$b['auth_id'],'revision'=>$b['auth_revision'],'enabled'=>true,'ciphertext_sha256'=>$b['auth_ciphertext_sha256'],'profile_revision'=>$b['profile_revision'],'profile_credentials_sha256'=>$b['credential_sha256'],'actor_id'=>2,'actor_auth_version'=>csd_id(12)];
    $GLOBALS['csp_rows']=['assignment'=>['document'=>$a,'document_sha256'=>$b['assignment_sha256'],'proof'=>$b['assignment_proof']],'auth'=>['document'=>$k,'document_sha256'=>$b['auth_sha256'],'proof'=>$b['auth_proof']]];
}
try {
    csp_load(CWP_CODE_ROOT.'/app/providers/client_stackcp_permissions.php',['provider_client_stackcp_actor','provider_client_stackcp_current','provider_client_stackcp_permission_candidate','provider_client_stackcp_permission_review','provider_client_stackcp_permission_change','provider_client_stackcp_owner_snapshot','provider_client_stackcp_client_snapshot']);
    csp_load(CWP_CODE_ROOT.'/app/providers/client_stackcp_storage.php',['provider_client_stackcp_resource','provider_client_stackcp_permission','provider_client_stackcp_row','provider_client_stackcp_checkpoint']);
    csp_reset();$permission=csd_permission();
    csp_ok('Actual static validator admits the exact primary-client permission',provider_client_stackcp_current($permission));
    $GLOBALS['csp_owner']=null;csp_ok('Client static validation never depends on ambient owner login',provider_client_stackcp_current($permission));
    $GLOBALS['csp_candidate']=null;csp_ok('Expired or later observations do not expire stable owner permission',provider_client_stackcp_current($permission));
    csp_reset();$GLOBALS['csp_candidate']['binding']['source_id']=csd_id(99);$GLOBALS['csp_candidate']['binding']['membership_id']=csd_id(98);csp_ok('Legitimate fresh read replacement does not change static authority',provider_client_stackcp_current($permission));
    foreach (['client_id','client_auth_version','profile_revision','profile_credentials_sha256','key_fingerprint','link_id','link_sha256','event_id','event_sha256','audit_id','audit_sha256','package_id'] as $key) {csp_reset();$v=$GLOBALS['csp_local'][$key];$GLOBALS['csp_local'][$key]=is_int($v)?$v+1:str_repeat('9',strlen($v));csp_ok('Current permission refuses changed '.$key,!provider_client_stackcp_current($permission));}
    foreach (['assignment'=>['id','revision','state','stack_identity','actor_auth_version'],'auth'=>['id','revision','enabled','ciphertext_sha256','profile_revision','profile_credentials_sha256','actor_auth_version']] as $family=>$keys) foreach($keys as $key){csp_reset();$v=$GLOBALS['csp_rows'][$family]['document'][$key];$GLOBALS['csp_rows'][$family]['document'][$key]=is_bool($v)?!$v:(is_int($v)?$v+1:'changed');csp_ok('Current permission refuses changed '.$family.' '.$key,!provider_client_stackcp_current($permission));}
    foreach(['assignment','auth'] as $family)foreach(['document_sha256','proof'] as $key){csp_reset();$GLOBALS['csp_rows'][$family][$key]=str_repeat('9',64);csp_ok('Current permission refuses changed '.$family.' '.$key,!provider_client_stackcp_current($permission));}
    csp_reset();$GLOBALS['csp_invalid_owners'][2]=true;csp_ok('Original authorizing owner revocation invalidates permission',!provider_client_stackcp_current($permission));
    csp_reset();$GLOBALS['csp_local']=null;csp_ok('Removed hosting assignment invalidates permission',!provider_client_stackcp_current($permission));
    csp_reset();$GLOBALS['csp_corruption']=true;csp_no('Damaged original authority is not silently absent',fn()=>provider_client_stackcp_current($permission));
    csp_reset();$GLOBALS['csp_rows']['auth']=null;csp_no('Missing protected authority history refuses',fn()=>provider_client_stackcp_current($permission));
    csp_reset();$GLOBALS['csp_lock']=false;csp_no('Static validator requires actual owning mutex',fn()=>provider_client_stackcp_current($permission),LogicException::class);
    foreach(['contact_id','contact_auth','uid'] as $key){csp_reset();$_SESSION[$key]=null;csp_no('Client raw '.$key.' presence denied before normalization',fn()=>provider_client_stackcp_actor(9));csp_ok('No normalization for forbidden '.$key,$GLOBALS['csp_normalizations']===0);}
    foreach(['contact_id','contact_auth'] as $key){csp_reset();$_SESSION[$key]=null;csp_no('Owner raw '.$key.' presence denied before normalization',fn()=>provider_client_stackcp_actor());}
    csp_reset();$_GET['support']='';csp_no('Raw support route excludes primary client',fn()=>provider_client_stackcp_actor(9));
    csp_reset();$GLOBALS['csp_support']=['client'=>9];csp_no('Live support session excludes client launch',fn()=>provider_client_stackcp_actor(9));
    csp_reset();csp_ok('Primary client is recorded independently of owner',provider_client_stackcp_actor(9)===['kind'=>'client','id'=>9,'auth_version'=>csd_id(4)]);
    csp_no('Other client cannot select the admitted principal',fn()=>provider_client_stackcp_actor(10));
    $GLOBALS['csp_client']=null;csp_no('Anonymous client cannot receive a principal',fn()=>provider_client_stackcp_actor(9));
    csp_reset();csp_no('Wrong owner password refuses before permission writes',fn()=>provider_client_stackcp_permission_change(csd_id(1),701,str_repeat('a',64),false,true,'bad'),InvalidArgumentException::class);csp_ok('Wrong password has no permission effect',$GLOBALS['csp_writes']===0);
    csp_reset();$review=provider_client_stackcp_permission_review(csd_id(1),701);csp_ok('Owner safe review has meaningful labels and separate revoke proof',$review['enabled']&&$review['client_name']==='Primary client <name>'&&$review['package_name']==='Client hosting <example>'&&$review['review_proof']===$review['revoke_review']&&$review['enable_review']!==$review['revoke_review']);
    csp_no('Permission change requires explicit whole-user confirmation',fn()=>provider_client_stackcp_permission_change(csd_id(1),701,$review['revoke_review'],false,false,'owner-password'),InvalidArgumentException::class);
    csp_no('Enable proof cannot be substituted into revoke',fn()=>provider_client_stackcp_permission_change(csd_id(1),701,$review['enable_review'],false,true,'owner-password'));
    $GLOBALS['csp_candidate']=null;$GLOBALS['csp_local']=null;$stale=provider_client_stackcp_permission_review(csd_id(1),701);csp_ok('Stale enabled permission remains explicitly revocable',$stale['stale']&&!$stale['can_enable']&&$stale['revoke_review']!==''&&$stale['review_proof']===$review['revoke_review']);
    $result=provider_client_stackcp_permission_change(csd_id(1),701,$stale['revoke_review'],false,true,'owner-password');csp_ok('Actual revoke appends a new owner event retaining old history',!$result['enabled']&&$GLOBALS['csp_writes']===1&&count($GLOBALS['csp_events'])===2&&$GLOBALS['csp_events'][0]===$permission);
    csp_no('Same stale permission POST cannot create another revision',fn()=>provider_client_stackcp_permission_change(csd_id(1),701,$stale['revoke_review'],false,true,'owner-password'));
    csp_reset();$review=provider_client_stackcp_permission_review(csd_id(1),701);$GLOBALS['csp_candidate']['grants_id']=csd_id(88);csp_no('Captured enable review is never silently replaced by a fresh observation',fn()=>provider_client_stackcp_permission_change(csd_id(1),701,$review['enable_review'],true,true,'owner-password'));
    csp_reset();$snapshot=provider_client_stackcp_client_snapshot(9);csp_ok('Client cards contain only own current permission and safe package labels',count($snapshot['items'])===1&&array_keys($snapshot['items'][0])===['profile_id','package_id','package_name','link_id','permission_id']);
    csp_ok('Exact unrelated link selection leaks no grant',provider_client_stackcp_client_snapshot(9,1,csd_id(99))['items']===[]);
    $GLOBALS['csp_local']['client_auth_version']=csd_id(77);csp_ok('Client auth change hides permission cards',provider_client_stackcp_client_snapshot(9)['items']===[]);
    csp_reset();$GLOBALS['csp_events']=null;csp_ok('Clean absent family gives owner preparation screen',provider_client_stackcp_owner_snapshot()['reason']==='unprepared');csp_ok('Clean absent family gives no client launch cards',provider_client_stackcp_client_snapshot(9)['items']===[]);csp_ok('Read-only snapshots never append permissions',$GLOBALS['csp_writes']===0);
    csp_reset();$row=[];foreach(['id','ordinal','permission_id','admission_id','profile_id','package_id','client_id','kind','phase'] as $key)$row[$key]=$permission[$key];$row['actor_staff_id']=2;$row['actor_client_id']=null;$row['document_json']=provider_client_stackcp_json($permission);$row['document_sha256']=hash('sha256',$row['document_json']);$row['proof']=provider_client_stackcp_hmac('event',$row['document_json']);
    csp_ok('Actual row validator binds canonical signed permission and indexed fields',provider_client_stackcp_row($row)===$permission);
    foreach(['actor_staff_id'=>3,'actor_client_id'=>9,'client_id'=>10,'permission_id'=>csd_id(99),'document_sha256'=>str_repeat('0',64),'proof'=>str_repeat('0',64)] as $key=>$value){$bad=$row;$bad[$key]=$value;csp_no('Signed row refuses changed '.$key,fn()=>provider_client_stackcp_row($bad));}
    $admission=csd_admitted()[1];foreach(['id','ordinal','permission_id','admission_id','profile_id','package_id','client_id','kind','phase'] as $key)$row[$key]=$admission[$key];$row['actor_staff_id']=null;$row['actor_client_id']=9;$row['document_json']=provider_client_stackcp_json($admission);$row['document_sha256']=hash('sha256',$row['document_json']);$row['proof']=provider_client_stackcp_hmac('event',$row['document_json']);csp_ok('Client row has its own principal and no owner audit substitution',provider_client_stackcp_row($row)===$admission);
    $row['actor_staff_id']=2;csp_no('Client row cannot also claim a staff principal',fn()=>provider_client_stackcp_row($row));
    $checkpoint=['version'=>1,'count'=>1,'permission_count'=>1,'admission_count'=>0,'head'=>hash('sha256',provider_client_stackcp_json($permission))];$settings=[];foreach(array_slice($checkpoint,1,null,true) as $key=>$value)$settings[$key]=(string)$value;$settings['checkpoint_proof']=provider_client_stackcp_hmac('checkpoint',provider_client_stackcp_json($checkpoint));csp_ok('Actual checkpoint validates independent counts and retained head',provider_client_stackcp_checkpoint($settings)===$checkpoint);
    foreach(['count','permission_count','admission_count','head'] as $key){$bad=$settings;$bad[$key]=$key==='head'?str_repeat('f',64):'2';csp_no('Checkpoint refuses changed '.$key,fn()=>provider_client_stackcp_checkpoint($bad));}
    $completed=true;
} catch (Throwable $e) {$checks[]=['name'=>'Unexpected permissions test failure','passed'=>false,'error_class'=>get_class($e)];fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");}
$report=['passed'=>$completed&&!in_array(false,array_column($checks,'passed'),true),'scope'=>'Actual extracted permission/actor/current-resource/row/checkpoint functions with modeled SQL and existing authority dependencies; no native SQL/provider calls','checks'=>$checks];$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";if(isset($argv[1]))file_put_contents($argv[1],$json);echo $json;exit($report['passed']?0:1);
