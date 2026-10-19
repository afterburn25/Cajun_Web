<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
if (!defined('CWP_BOOT')) define('CWP_BOOT',true);
if (!defined('CWP_CODE_ROOT')) define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require_once CWP_CODE_ROOT.'/app/providers/client_stackcp_documents.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_client_stackcp_schema.php';
function csd_id(int $n):string {return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function csd_resource():array {
    $b=['operation'=>'stackcp.grants','profile_id'=>csd_id(1),'profile_revision'=>1,'credential_sha256'=>str_repeat('2',64),'key_fingerprint'=>str_repeat('3',64),'package_id'=>701,'client_id'=>9,'client_auth_version'=>csd_id(4),'link_id'=>csd_id(5),'link_sha256'=>str_repeat('6',64),'event_id'=>7,'event_sha256'=>str_repeat('8',64),'audit_id'=>9,'audit_sha256'=>str_repeat('a',64),'source_id'=>csd_id(11),'source_seq'=>12,'source_sha256'=>str_repeat('b',64),'actor_id'=>2,'actor_auth_version'=>csd_id(12),'assignment_id'=>csd_id(13),'assignment_revision'=>1,'assignment_sha256'=>str_repeat('c',64),'assignment_proof'=>str_repeat('d',64),'auth_id'=>csd_id(14),'auth_revision'=>1,'auth_sha256'=>str_repeat('e',64),'auth_proof'=>str_repeat('f',64),'auth_ciphertext_sha256'=>str_repeat('1',64),'stack_identity'=>'stack-user:97','membership_id'=>csd_id(15),'membership_seq'=>13,'membership_sha256'=>str_repeat('2',64)];
    return ['binding'=>$b,'grants_id'=>csd_id(16),'grants_seq'=>14,'grants_sha256'=>str_repeat('3',64),'membership_observed_epoch'=>1760000000,'grants_observed_epoch'=>1760000000];
}
function csd_permission():array {
    return provider_client_stackcp_document(['version'=>1,'id'=>csd_id(20),'ordinal'=>1,'previous_sha256'=>str_repeat('0',64),'permission_id'=>csd_id(20),'admission_id'=>'','profile_id'=>csd_id(1),'package_id'=>701,'client_id'=>9,'actor'=>['kind'=>'owner','id'=>2,'auth_version'=>csd_id(12)],'recorded_epoch'=>1760000000,'kind'=>'permission','phase'=>'permission','payload'=>['revision'=>1,'previous_permission_id'=>'','previous_permission_sha256'=>str_repeat('0',64),'enabled'=>true,'resource'=>csd_resource(),'confirmed'=>true]]);
}
function csd_event(array $events,string $kind,string $phase,array $payload):array {
    $permission=$events[0];$admissionId=csd_id(21);$last=$events[array_key_last($events)];
    return provider_client_stackcp_document(['version'=>1,'id'=>$kind==='admitted'?$admissionId:csd_id(20+count($events)),'ordinal'=>count($events)+1,'previous_sha256'=>hash('sha256',provider_client_stackcp_json($last)),'permission_id'=>$permission['id'],'admission_id'=>$admissionId,'profile_id'=>$permission['profile_id'],'package_id'=>$permission['package_id'],'client_id'=>9,'actor'=>['kind'=>'client','id'=>9,'auth_version'=>csd_id(4)],'recorded_epoch'=>1760000000+count($events),'kind'=>$kind,'phase'=>$phase,'payload'=>$payload]);
}
function csd_admitted():array {
    $p=csd_permission();$a=['version'=>1,'admission_id'=>csd_id(21),'permission_id'=>$p['id'],'permission_sha256'=>hash('sha256',provider_client_stackcp_json($p)),'client_id'=>9,'client_auth_version'=>csd_id(4),'link_id'=>csd_id(5),'expires_epoch'=>1760000300,'previous_admission_id'=>'','previous_event_sha256'=>'','confirmed'=>true,'provider_session_scope'=>'whole-stack-user','remote_scope_restricted'=>false];
    return [$p,csd_event([$p],'admitted','admission',['approval'=>$a,'review_proof'=>str_repeat('a',64)])];
}
function csd_chain():array {
    $events=csd_admitted();foreach (['membership','grants','brand','token','session'] as $phase) {
        $events[]=csd_event($events,'started',$phase,[]);$payload=[];
        if (in_array($phase,['membership','grants'],true)) $payload=['values'=>$phase==='membership'?['stack-user:97']:['package:701'],'observed_epoch'=>1760000000+count($events)];
        $events[]=csd_event($events,'accepted',$phase,$payload);
    }
    $events[]=csd_event($events,'terminal','delivery',['state'=>'handoff_attempted','reason'=>'']);return $events;
}
if (defined('CCSD_LIBRARY')) return;
$checks=[];$completed=false;
set_error_handler(static function(int $level,string $message):never {throw new ErrorException('PHP diagnostic',0,$level);});
function csd_ok(string $name,bool $passed):void {global $checks;if (in_array($name,array_column($checks,'name'),true)) throw new LogicException('Duplicate check.');$checks[]=['name'=>$name,'passed'=>$passed];if (!$passed) throw new RuntimeException($name);}
function csd_no(string $name,callable $call):void {try {$call();}catch (InvalidArgumentException|RuntimeException $e) {csd_ok($name,true);return;}csd_ok($name,false);}
try {
    $p=csd_permission();$chain=csd_chain();$state=provider_client_stackcp_project($chain);
    csd_ok('Complete journal retains owner permission and distinct client admission',$state['permissions'][csd_id(1).':701']['actor']['kind']==='owner'&&$state['admissions'][csd_id(21)]['admission']['actor']['kind']==='client');
    csd_ok('Twelve-event launch completes only after both real-read and all exchange phases',count($chain)===13&&$state['admissions'][csd_id(21)]['step']===10&&$state['admissions'][csd_id(21)]['terminal']['payload']['state']==='handoff_attempted');
    csd_ok('Whole-user consent never asserts package confinement',$chain[1]['payload']['approval']['provider_session_scope']==='whole-stack-user'&&$chain[1]['payload']['approval']['remote_scope_restricted']===false);
    csd_ok('Policy has no recurring artificial expiry',!array_key_exists('expires_epoch',$p['payload']));
    csd_ok('Canonical document round trip is exact',provider_client_stackcp_document(json_decode(provider_client_stackcp_json($p),true,16,JSON_THROW_ON_ERROR))===$p);
    foreach (array_keys($p) as $key) {$bad=$p;unset($bad[$key]);csd_no('Event requires '.$key,fn()=>provider_client_stackcp_document($bad));}
    foreach (['token','session_id','url','owner_password','raw_body'] as $key) {$bad=$p;$bad['payload'][$key]='PRIVATE';csd_no('No secret or arbitrary field '.$key,fn()=>provider_client_stackcp_document($bad));}
    foreach (['owner','support','contact'] as $kind) {$bad=$chain[1];$bad['actor']['kind']=$kind;csd_no('Admission rejects '.$kind.' actor',fn()=>provider_client_stackcp_document($bad));}
    foreach (['confirmed'=>false,'remote_scope_restricted'=>true,'provider_session_scope'=>'package'] as $key=>$value) {$bad=$chain[1];$bad['payload']['approval'][$key]=$value;csd_no('Admission rejects misleading '.$key,fn()=>provider_client_stackcp_document($bad));}
    foreach (['permission_sha256'=>str_repeat('b',64),'client_auth_version'=>csd_id(88),'link_id'=>csd_id(88),'previous_admission_id'=>csd_id(88)] as $key=>$value) {$bad=csd_admitted();$bad[1]['payload']['approval'][$key]=$value;csd_no('Admission exact binding '.$key,fn()=>provider_client_stackcp_project($bad));}
    $bad=$chain[1];$bad['payload']['approval']['expires_epoch']=$bad['recorded_epoch']+301;csd_no('Client form expires within five minutes',fn()=>provider_client_stackcp_document($bad));
    $bad=$chain[1];$bad['payload']['approval']['expires_epoch']=$bad['recorded_epoch']-1;csd_no('Expired admission is refused',fn()=>provider_client_stackcp_document($bad));
    foreach (['ordinal'=>3,'previous_sha256'=>str_repeat('f',64),'recorded_epoch'=>1759999999] as $key=>$value) {$bad=csd_admitted();$bad[1][$key]=$value;csd_no('Whole journal protects '.$key,fn()=>provider_client_stackcp_project($bad));}
    foreach (['membership','grants','brand','token','session'] as $phase) {
        csd_no('Phase cannot skip started '.$phase,function()use($phase){$partial=csd_admitted();$payload=in_array($phase,['membership','grants'],true)?['values'=>$phase==='membership'?['stack-user:97']:['package:701'],'observed_epoch'=>1760000002]:[];$partial[]=csd_event($partial,'accepted',$phase,$payload);provider_client_stackcp_project($partial);});
    }
    $partial=csd_admitted();$partial[]=csd_event($partial,'started','membership',[]);
    foreach ([[],['stack-user:98'],['stack-user:97','stack-user:97']] as $i=>$values) csd_no('Membership must admit selected identity '.$i,function()use($partial,$values){$partial[]=csd_event($partial,'accepted','membership',['values'=>$values,'observed_epoch'=>1760000003]);provider_client_stackcp_project($partial);});
    $partial=array_slice($chain,0,5);
    foreach ([[],['package:702'],['package:701','server:9'],['package:701','package:701']] as $i=>$values) csd_no('Grants must be exact singleton '.$i,function()use($partial,$values){$partial[]=csd_event($partial,'accepted','grants',['values'=>$values,'observed_epoch'=>1760000005]);provider_client_stackcp_project($partial);});
    $partial=csd_admitted();$partial[]=csd_event($partial,'terminal','admission',['state'=>'not_issued','reason'=>'dns']);csd_ok('Pre-effect failure records not issued',provider_client_stackcp_project($partial)['admissions'][csd_id(21)]['terminal']['payload']['state']==='not_issued');
    $partial=array_slice($chain,0,9);$partial[]=csd_event($partial,'terminal','token',['state'=>'unknown','reason'=>'transport']);csd_ok('Started token failure remains unknown',provider_client_stackcp_project($partial)['admissions'][csd_id(21)]['possible_effect']);
    $partial[array_key_last($partial)]['payload']['state']='not_issued';csd_no('Started token can never become definitely not issued',fn()=>provider_client_stackcp_project($partial));
    $partial=csd_admitted();$partial[]=csd_event($partial,'terminal','delivery',['state'=>'handoff_attempted','reason'=>'']);csd_no('Delivery cannot precede all phases',fn()=>provider_client_stackcp_project($partial));
    $extra=$chain;$extra[]=csd_event($extra,'started','brand',[]);csd_no('Completed admission can never resend',fn()=>provider_client_stackcp_project($extra));
    $bad=$chain;$bad[3]['payload']['values']=['stack-user:97','stack-user:98'];csd_no('Changed retained observation invalidates later hashes',fn()=>provider_client_stackcp_project($bad));
    $rev=$p;$rev['id']=csd_id(90);$rev['permission_id']=$rev['id'];$rev['ordinal']=2;$rev['previous_sha256']=hash('sha256',provider_client_stackcp_json($p));$rev['payload']['revision']=2;$rev['payload']['previous_permission_id']=$p['id'];$rev['payload']['previous_permission_sha256']=$rev['previous_sha256'];$rev['payload']['enabled']=false;
    csd_ok('Owner can revoke exact retained source without requiring fresh observation',provider_client_stackcp_project([$p,$rev])['permissions'][csd_id(1).':701']['payload']['enabled']===false);
    $bad=$rev;$bad['payload']['resource']['binding']['stack_identity']='stack-user:98';csd_no('Revocation cannot rewrite original selected identity',fn()=>provider_client_stackcp_project([$p,$bad]));
    $bad=$rev;$bad['payload']['previous_permission_id']=csd_id(99);csd_no('Revocation must reference exact preceding policy',fn()=>provider_client_stackcp_project([$p,$bad]));
    $bad=$p;$bad['actor']['id']=3;csd_no('Enable requires actual observing owner consent',fn()=>provider_client_stackcp_document($bad));
    $sql=provider_client_stackcp_schema('cst_')['provider_client_stackcp_events'];
    csd_ok('Storage has distinct nullable staff and client principals',str_contains($sql,'actor_staff_id INT UNSIGNED NULL')&&str_contains($sql,'actor_client_id INT UNSIGNED NULL')&&str_contains($sql,'REFERENCES `cst_studio_clients`'));
    csd_ok('Storage is transactional with independent global journal order',str_contains($sql,'ENGINE=InnoDB')&&str_contains($sql,'UNIQUE KEY client_stackcp_ordinal(ordinal)'));
    foreach (['x','../bad','a-','a`','123_'] as $prefix) csd_no('Invalid schema prefix '.$prefix,fn()=>provider_client_stackcp_schema($prefix));
    $completed=true;
} catch (Throwable $e) {$checks[]=['name'=>'Unexpected document test failure','passed'=>false,'error_class'=>get_class($e)];fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");}
$report=['passed'=>$completed&&!in_array(false,array_column($checks,'passed'),true),'scope'=>'Actual pure client StackCP documents, journal semantics and schema; no SQL/provider calls','checks'=>$checks];$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if (isset($argv[1])) file_put_contents($argv[1],$json);echo $json;exit($report['passed']?0:1);
