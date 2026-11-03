<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
if (!defined('CWP_BOOT')) define('CWP_BOOT',true);
if (!defined('CWP_CODE_ROOT')) define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require_once CWP_CODE_ROOT.'/app/providers/support_stackcp_documents.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_support_stackcp_schema.php';
function ssd_id(int $n):string {return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function ssd_actor():array {return ['kind'=>'support','id'=>3,'auth_version'=>ssd_id(30),'client_id'=>9,'client_auth_version'=>ssd_id(4),'support_id'=>ssd_id(31),'binding_sha256'=>str_repeat('7',64),'expires_epoch'=>1760000900];}
function ssd_resource():array {
    $b=['operation'=>'stackcp.grants','profile_id'=>ssd_id(1),'profile_revision'=>1,'credential_sha256'=>str_repeat('2',64),'key_fingerprint'=>str_repeat('3',64),'package_id'=>701,'client_id'=>9,'client_auth_version'=>ssd_id(4),'link_id'=>ssd_id(5),'link_sha256'=>str_repeat('6',64),'event_id'=>7,'event_sha256'=>str_repeat('8',64),'audit_id'=>9,'audit_sha256'=>str_repeat('a',64),'source_id'=>ssd_id(11),'source_seq'=>12,'source_sha256'=>str_repeat('b',64),'actor_id'=>2,'actor_auth_version'=>ssd_id(12),'assignment_id'=>ssd_id(13),'assignment_revision'=>1,'assignment_sha256'=>str_repeat('c',64),'assignment_proof'=>str_repeat('d',64),'auth_id'=>ssd_id(14),'auth_revision'=>1,'auth_sha256'=>str_repeat('e',64),'auth_proof'=>str_repeat('f',64),'auth_ciphertext_sha256'=>str_repeat('1',64),'stack_identity'=>'stack-user:97','membership_id'=>ssd_id(15),'membership_seq'=>13,'membership_sha256'=>str_repeat('2',64)];
    return ['binding'=>$b,'grants_id'=>ssd_id(16),'grants_seq'=>14,'grants_sha256'=>str_repeat('3',64),'membership_observed_epoch'=>1760000000,'grants_observed_epoch'=>1760000000,'staff'=>['id'=>3,'auth_version'=>ssd_id(30)]];
}
function ssd_permission():array {
    return provider_support_stackcp_document(['version'=>1,'id'=>ssd_id(20),'ordinal'=>1,'previous_sha256'=>str_repeat('0',64),'permission_id'=>ssd_id(20),'admission_id'=>'','profile_id'=>ssd_id(1),'package_id'=>701,'client_id'=>9,'staff_id'=>3,'actor'=>['kind'=>'owner','id'=>2,'auth_version'=>ssd_id(12)],'recorded_epoch'=>1760000000,'kind'=>'permission','phase'=>'permission','payload'=>['revision'=>1,'previous_permission_id'=>'','previous_permission_sha256'=>str_repeat('0',64),'enabled'=>true,'resource'=>ssd_resource(),'confirmed'=>true]]);
}
function ssd_event(array $events,string $kind,string $phase,array $payload):array {
    $permission=$events[0];$admissionId=ssd_id(21);$last=$events[array_key_last($events)];
    return provider_support_stackcp_document(['version'=>1,'id'=>$kind==='admitted'?$admissionId:ssd_id(20+count($events)),'ordinal'=>count($events)+1,'previous_sha256'=>hash('sha256',provider_support_stackcp_json($last)),'permission_id'=>$permission['id'],'admission_id'=>$admissionId,'profile_id'=>$permission['profile_id'],'package_id'=>$permission['package_id'],'client_id'=>9,'staff_id'=>3,'actor'=>ssd_actor(),'recorded_epoch'=>1760000000+count($events),'kind'=>$kind,'phase'=>$phase,'payload'=>$payload]);
}
function ssd_admitted():array {
    $p=ssd_permission();$a=['version'=>1,'admission_id'=>ssd_id(21),'permission_id'=>$p['id'],'permission_sha256'=>hash('sha256',provider_support_stackcp_json($p)),'actor'=>ssd_actor(),'link_id'=>ssd_id(5),'expires_epoch'=>1760000300,'previous_admission_id'=>'','previous_event_sha256'=>'','confirmed'=>true,'provider_session_scope'=>'whole-stack-user','remote_scope_restricted'=>false];
    return [$p,ssd_event([$p],'admitted','admission',['approval'=>$a,'review_proof'=>str_repeat('a',64)])];
}
function ssd_chain():array {
    $events=ssd_admitted();foreach (['membership','grants','brand','token','session'] as $phase) {
        $events[]=ssd_event($events,'started',$phase,[]);$payload=[];
        if (in_array($phase,['membership','grants'],true)) $payload=['values'=>$phase==='membership'?['stack-user:97']:['package:701'],'observed_epoch'=>1760000000+count($events)];
        $events[]=ssd_event($events,'accepted',$phase,$payload);
    }
    $events[]=ssd_event($events,'terminal','delivery',['state'=>'handoff_attempted','reason'=>'']);return $events;
}
if (defined('SCSD_LIBRARY')) return;
$checks=[];$completed=false;
set_error_handler(static function(int $level,string $message):never {throw new ErrorException('PHP diagnostic',0,$level);});
function ssd_ok(string $name,bool $passed):void {global $checks;if (in_array($name,array_column($checks,'name'),true)) throw new LogicException('Duplicate check.');$checks[]=['name'=>$name,'passed'=>$passed];if (!$passed) throw new RuntimeException($name);}
function ssd_no(string $name,callable $call):void {try {$call();}catch (InvalidArgumentException|RuntimeException $e) {ssd_ok($name,true);return;}ssd_ok($name,false);}
try {
    $p=ssd_permission();$chain=ssd_chain();$state=provider_support_stackcp_project($chain);
    ssd_ok('Complete journal retains owner permission and distinct staff support admission',$state['permissions'][ssd_id(1).':701:3']['actor']['kind']==='owner'&&$state['admissions'][ssd_id(21)]['admission']['actor']['kind']==='support');
    ssd_ok('Twelve-event launch completes only after both real-read and all exchange phases',count($chain)===13&&$state['admissions'][ssd_id(21)]['step']===10&&$state['admissions'][ssd_id(21)]['terminal']['payload']['state']==='handoff_attempted');
    ssd_ok('Whole-user consent never asserts package confinement',$chain[1]['payload']['approval']['provider_session_scope']==='whole-stack-user'&&$chain[1]['payload']['approval']['remote_scope_restricted']===false);
    ssd_ok('Policy has no recurring artificial expiry',!array_key_exists('expires_epoch',$p['payload']));
    ssd_ok('Canonical document round trip is exact',provider_support_stackcp_document(json_decode(provider_support_stackcp_json($p),true,16,JSON_THROW_ON_ERROR))===$p);
    foreach (array_keys($p) as $key) {$bad=$p;unset($bad[$key]);ssd_no('Event requires '.$key,fn()=>provider_support_stackcp_document($bad));}
    foreach (['token','session_id','url','owner_password','raw_body'] as $key) {$bad=$p;$bad['payload'][$key]='PRIVATE';ssd_no('No secret or arbitrary field '.$key,fn()=>provider_support_stackcp_document($bad));}
    foreach (['owner','client','contact'] as $kind) {$bad=$chain[1];$bad['actor']['kind']=$kind;ssd_no('Admission rejects '.$kind.' actor',fn()=>provider_support_stackcp_document($bad));}
    foreach (['confirmed'=>false,'remote_scope_restricted'=>true,'provider_session_scope'=>'package'] as $key=>$value) {$bad=$chain[1];$bad['payload']['approval'][$key]=$value;ssd_no('Admission rejects misleading '.$key,fn()=>provider_support_stackcp_document($bad));}
    foreach (['permission_sha256'=>str_repeat('b',64),'link_id'=>ssd_id(88),'previous_admission_id'=>ssd_id(88)] as $key=>$value) {$bad=ssd_admitted();$bad[1]['payload']['approval'][$key]=$value;ssd_no('Admission exact binding '.$key,fn()=>provider_support_stackcp_project($bad));}
    $bad=$chain[1];$bad['payload']['approval']['expires_epoch']=$bad['recorded_epoch']+301;ssd_no('Support form expires within five minutes',fn()=>provider_support_stackcp_document($bad));
    $bad=$chain[1];$bad['payload']['approval']['expires_epoch']=$bad['recorded_epoch']-1;ssd_no('Expired admission is refused',fn()=>provider_support_stackcp_document($bad));
    foreach (['ordinal'=>3,'previous_sha256'=>str_repeat('f',64),'recorded_epoch'=>1759999999] as $key=>$value) {$bad=ssd_admitted();$bad[1][$key]=$value;ssd_no('Whole journal protects '.$key,fn()=>provider_support_stackcp_project($bad));}
    foreach (['membership','grants','brand','token','session'] as $phase) {
        ssd_no('Phase cannot skip started '.$phase,function()use($phase){$partial=ssd_admitted();$payload=in_array($phase,['membership','grants'],true)?['values'=>$phase==='membership'?['stack-user:97']:['package:701'],'observed_epoch'=>1760000002]:[];$partial[]=ssd_event($partial,'accepted',$phase,$payload);provider_support_stackcp_project($partial);});
    }
    $partial=ssd_admitted();$partial[]=ssd_event($partial,'started','membership',[]);
    foreach ([[],['stack-user:98'],['stack-user:97','stack-user:97']] as $i=>$values) ssd_no('Membership must admit selected identity '.$i,function()use($partial,$values){$partial[]=ssd_event($partial,'accepted','membership',['values'=>$values,'observed_epoch'=>1760000003]);provider_support_stackcp_project($partial);});
    $partial=array_slice($chain,0,5);
    foreach ([[],['package:702'],['package:701','server:9'],['package:701','package:701']] as $i=>$values) ssd_no('Grants must be exact singleton '.$i,function()use($partial,$values){$partial[]=ssd_event($partial,'accepted','grants',['values'=>$values,'observed_epoch'=>1760000005]);provider_support_stackcp_project($partial);});
    $partial=ssd_admitted();$partial[]=ssd_event($partial,'terminal','admission',['state'=>'not_issued','reason'=>'dns']);ssd_ok('Pre-effect failure records not issued',provider_support_stackcp_project($partial)['admissions'][ssd_id(21)]['terminal']['payload']['state']==='not_issued');
    $partial=array_slice($chain,0,9);$partial[]=ssd_event($partial,'terminal','token',['state'=>'unknown','reason'=>'transport']);ssd_ok('Started token failure remains unknown',provider_support_stackcp_project($partial)['admissions'][ssd_id(21)]['possible_effect']);
    $partial[array_key_last($partial)]['payload']['state']='not_issued';ssd_no('Started token can never become definitely not issued',fn()=>provider_support_stackcp_project($partial));
    $partial=ssd_admitted();$partial[]=ssd_event($partial,'terminal','delivery',['state'=>'handoff_attempted','reason'=>'']);ssd_no('Delivery cannot precede all phases',fn()=>provider_support_stackcp_project($partial));
    $extra=$chain;$extra[]=ssd_event($extra,'started','brand',[]);ssd_no('Completed admission can never resend',fn()=>provider_support_stackcp_project($extra));
    $bad=$chain;$bad[3]['payload']['values']=['stack-user:97','stack-user:98'];ssd_no('Changed retained observation invalidates later hashes',fn()=>provider_support_stackcp_project($bad));
    $rev=$p;$rev['id']=ssd_id(90);$rev['permission_id']=$rev['id'];$rev['ordinal']=2;$rev['previous_sha256']=hash('sha256',provider_support_stackcp_json($p));$rev['payload']['revision']=2;$rev['payload']['previous_permission_id']=$p['id'];$rev['payload']['previous_permission_sha256']=$rev['previous_sha256'];$rev['payload']['enabled']=false;
    ssd_ok('Owner can revoke exact retained source without requiring fresh observation',provider_support_stackcp_project([$p,$rev])['permissions'][ssd_id(1).':701:3']['payload']['enabled']===false);
    $bad=$rev;$bad['payload']['resource']['binding']['stack_identity']='stack-user:98';ssd_no('Revocation cannot rewrite original selected identity',fn()=>provider_support_stackcp_project([$p,$bad]));
    $bad=$rev;$bad['payload']['previous_permission_id']=ssd_id(99);ssd_no('Revocation must reference exact preceding policy',fn()=>provider_support_stackcp_project([$p,$bad]));
    $bad=$p;$bad['actor']['id']=3;ssd_no('Enable requires actual observing owner consent',fn()=>provider_support_stackcp_document($bad));
    $sql=provider_support_stackcp_schema('cst_')['provider_support_stackcp_events'];
    ssd_ok('Storage separates delegated staff from actual staff actor and client',str_contains($sql,'staff_id INT UNSIGNED NOT NULL')&&str_contains($sql,'actor_staff_id INT UNSIGNED NOT NULL')&&!str_contains($sql,'actor_client_id')&&str_contains($sql,'support_stackcp_resource(profile_id,package_id,staff_id)'));
    ssd_ok('Both staff references use real user foreign keys',str_contains($sql,'FOREIGN KEY (staff_id) REFERENCES `cst_users`')&&str_contains($sql,'FOREIGN KEY (actor_staff_id) REFERENCES `cst_users`'));
    ssd_ok('Storage is transactional with independent global journal order',str_contains($sql,'ENGINE=InnoDB')&&str_contains($sql,'UNIQUE KEY support_stackcp_ordinal(ordinal)'));
    foreach (['x','../bad','a-','a`','123_'] as $prefix) ssd_no('Invalid schema prefix '.$prefix,fn()=>provider_support_stackcp_schema($prefix));
    foreach (['id'=>4,'auth_version'=>ssd_id(77),'client_id'=>10,'client_auth_version'=>ssd_id(77),'support_id'=>ssd_id(77),'binding_sha256'=>str_repeat('9',64),'expires_epoch'=>1760000800] as $key=>$value) {
        $bad=ssd_admitted();$bad[1]['actor'][$key]=$value;ssd_no('Admission cannot replace support principal '.$key,fn()=>provider_support_stackcp_project($bad));
    }
    foreach (['id'=>4,'auth_version'=>ssd_id(77)] as $key=>$value) {
        $bad=ssd_admitted();$bad[0]['payload']['resource']['staff'][$key]=$value;$bad[1]['previous_sha256']=hash('sha256',provider_support_stackcp_json($bad[0]));$bad[1]['payload']['approval']['permission_sha256']=$bad[1]['previous_sha256'];ssd_no('Admission requires delegated staff '.$key,fn()=>provider_support_stackcp_project($bad));
    }
    $bad=ssd_admitted()[1];$bad['payload']['approval']['expires_epoch']=1760000901;ssd_no('Form cannot outlive browser-bound support session',fn()=>provider_support_stackcp_document($bad));
    $bad=ssd_admitted()[1];$bad['payload']['approval']['actor']['client_auth_version']=ssd_id(77);ssd_no('Approval retains exact client authorization version',fn()=>provider_support_stackcp_document($bad));
    $other=$p;$other['id']=ssd_id(91);$other['permission_id']=$other['id'];$other['ordinal']=2;$other['previous_sha256']=hash('sha256',provider_support_stackcp_json($p));$other['staff_id']=4;$other['payload']['resource']['staff']=['id'=>4,'auth_version'=>ssd_id(40)];
    ssd_ok('Two staff may hold independent delegation chains for one package',count(provider_support_stackcp_project([$p,$other])['permissions'])===2);
    $changed=$chain;$changed[2]['actor']['support_id']=ssd_id(99);ssd_no('Later phase cannot switch support session',fn()=>provider_support_stackcp_project($changed));
    $completed=true;
} catch (Throwable $e) {$checks[]=['name'=>'Unexpected document test failure','passed'=>false,'error_class'=>get_class($e)];fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");}
$report=['passed'=>$completed&&!in_array(false,array_column($checks,'passed'),true),'scope'=>'Actual pure support StackCP documents, journal semantics and schema; no SQL/provider calls','checks'=>$checks];$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if (isset($argv[1])) file_put_contents($argv[1],$json);echo $json;exit($report['passed']?0:1);
