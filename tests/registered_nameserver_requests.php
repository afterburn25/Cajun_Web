<?php
declare(strict_types=1);
// Real service, document, approval and gateway bodies with finite SQL/session models.
// Native transaction/mutex/audit and packaged HTTP acceptance remain separate.
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
$legacy=file_get_contents(__DIR__.'/registered_nameserver_changes.php');
$start=strpos($legacy,'$repo=dirname(__DIR__);');$end=strpos($legacy,"reset_model();\ntry {");
if($start===false||$end===false)throw new LogicException('Retained owner fixture boundary changed.');
$fixture=substr($legacy,$start,$end-$start);
$hookStart=strpos($fixture,'// This retained v1 suite');$hookEnd=strpos($fixture,'$s=file_get_contents($storage);',$hookStart);
if($hookStart===false||$hookEnd===false)throw new LogicException('Retained fixture hook changed.');
$fixture=substr_replace($fixture,'',$hookStart,$hookEnd-$hookStart);
foreach(['one'=>'legacy_one','all'=>'legacy_all','reset_model'=>'legacy_reset_model'] as $before=>$after)$fixture=str_replace('function '.$before.'(', 'function '.$after.'(',$fixture);
eval($fixture);
$documentSource=file_get_contents(CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests_document.php');
eval('use CajunWebPro\\Foundation\\TwentyIRegistrarNameservers\\RegisteredNameserverBinding;use CajunWebPro\\Foundation\\TwentyI\\Operations\\NameserverChange;'.substr($documentSource,strpos($documentSource,'function registered_nameserver_requests_keys')));
$clientService=CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests.php';$clientSource=file_get_contents($clientService);
eval(substr($clientSource,strpos($clientSource,'function registered_nameserver_requests_actor')));

function one(string $sql,array $args=[]):?array {
    if(str_contains($sql,'FROM studio_clients')){$c=$GLOBALS['m']['client'];return $c['active']&&(int)$args[0]===$c['id']?$c:null;}
    if(str_contains($sql,'FROM users'))return (int)$args[0]===$GLOBALS['m']['assigning_owner']['id']?$GLOBALS['m']['assigning_owner']:null;
    return legacy_one($sql,$args);
}
function all(string $sql,array $args=[]):array {
    if(str_contains($sql,'FROM provider_registrar_links'))return $GLOBALS['m']['linked']&&(int)$args[0]===$GLOBALS['m']['client']['id']?[['id'=>$GLOBALS['m']['binding']->data['link_id']]]:[];
    return legacy_all($sql,$args);
}
function support_current():?array {return $GLOBALS['m']['support']?['synthetic'=>true]:null;}
function studio_client():?array {$c=$GLOBALS['m']['client'];return $c['active']&&($_SESSION['client_id']??null)===$c['id']&&($_SESSION['client_auth']??null)===$c['auth_version']?$c:null;}
function staff_profile(array $owner):?array {return $owner['enabled']?$owner:null;}
function provider_settings_load(string $id):array {if($id!==$GLOBALS['m']['profile']['id'])throw new InvalidArgumentException('Provider profile unavailable.');return $GLOBALS['m']['profile'];}
function domain_contracts_link(string $id,int $revision):array {if(!$GLOBALS['m']['linked']||$id!==$GLOBALS['m']['link']['id']||$revision!==$GLOBALS['m']['link']['revision'])throw new RuntimeException('Assignment changed.');return [$GLOBALS['m']['link'],$GLOBALS['m']['link_event'],$GLOBALS['m']['link_audit']];}
function domain_contracts_source(string $id,int $domainId):array {if($id!==$GLOBALS['m']['source']['id']||$domainId!==14701)throw new RuntimeException('Original source changed.');return $GLOBALS['m']['source'];}
function registered_nameserver_requests_events():?array {registered_nameservers_guard(false);return $GLOBALS['m']['client_ready']?$GLOBALS['m']['client_events']:null;}
function registered_nameserver_requests_ready():bool {return registered_nameserver_requests_events()!==null;}
function registered_nameserver_requests_append(string $id,string $subject,int $clientId,string $kind,array $actor,array $payload):array {
    registered_nameservers_guard();$events=$GLOBALS['m']['client_events'];
    $event=['version'=>1,'id'=>$id,'subject_id'=>$subject,'client_id'=>$clientId,'kind'=>$kind,'actor'=>$actor,'recorded_epoch'=>time(),'ordinal'=>count($events)+1,'previous'=>str_repeat('0',64),'payload'=>$payload];
    registered_nameserver_requests_project([...$events,$event]);
    // Exercise exact associative ordering obtained from real canonical SQL JSON.
    $event=json_decode(registered_nameserver_requests_json($event),true,16,JSON_THROW_ON_ERROR);$GLOBALS['m']['client_events'][]=$event;
    if($GLOBALS['m']['fail']==='client_'.$kind)throw new PDOException('Synthetic final client event audit failure.');return $event;
}
function reset_client_model():void {
    legacy_reset_model();$m=&$GLOBALS['m'];$b=$m['binding']->data;
    $m['client']=['id'=>1,'active'=>true,'auth_version'=>str_repeat('7',32)];$m['assigning_owner']=['id'=>1,'enabled'=>true,'role'=>'owner','auth_version'=>str_repeat('a',32)];
    $m['link']=['id'=>$b['link_id'],'revision'=>1,'profile_id'=>$b['profile_id'],'profile_revision'=>1,'key_fingerprint'=>$b['key_fingerprint'],'domain_id'=>14701,'domain'=>'owned.test','client_id'=>1];
    $m['link_event']=['id'=>1,'actor_id'=>1,'refresh_id'=>$b['source_refresh_id']];$m['link_audit']=['id'=>1,'detail'=>'synthetic original assignment'];
    $m['source']=['id'=>$b['source_refresh_id'],'seq'=>1,'profile_id'=>$b['profile_id'],'profile_revision'=>1,'key_fingerprint'=>$b['key_fingerprint'],'domain'=>'owned.test'];
    $b['link_event_sha256']=hash('sha256',registered_nameservers_json($m['link_event']));$b['link_audit_sha256']=hash('sha256',registered_nameservers_json($m['link_audit']));$b['source_sha256']=hash('sha256',registered_nameservers_json($m['source']));
    $m['binding']=new \CajunWebPro\Foundation\TwentyIRegistrarNameservers\RegisteredNameserverBinding($b);$m['reads'][str_repeat('1',32)]['binding']=$m['binding'];
    $m['client_events']=[];$m['client_ready']=true;$_GET=[];$_SESSION=['client_id'=>1,'client_auth'=>$m['client']['auth_version']];
}
function submit_client(string $id='33333333333333333333333333333333',array $desired=['new2.test','new1.test']):array {
    $option=registered_nameserver_requests_screen(1)['options'][0]??null;if(!$option)throw new LogicException('Missing synthetic option.');
    return registered_nameserver_requests_create($id,1,$option['link_id'],$option['review_sha256'],$desired,true);
}
function approve_client(?array $item=null,?callable $resolver=null,?callable $send=null,string $dispatch='44444444444444444444444444444444'):array {
    $item??=registered_nameserver_requests_screen(null,1,str_repeat('3',32))['items'][0];
    return registered_nameserver_requests_approve($item['id'],$item['latest_event'],$item['request_sha256'],$dispatch,$item['approval']['review_sha256'],true,'Owner password',$resolver??function(){registered_nameservers_guard(false);if(db()->inTransaction())throw new LogicException('Resolver inherited transaction.');$GLOBALS['calls'][]='dns';return '8.8.8.8';},$send??'perform');
}
try {
    reset_client_model();$m=&$GLOBALS['m'];$calls=&$GLOBALS['calls'];$checks=[];
    $m['actor']['active']=false;$r=submit_client();ck('Primary client submits without owner session or provider activity',$r['state']==='pending'&&$calls===[]&&$m['grants']===[]&&$m['request']===[]);
    ck('Client request is canonical whole desired set',$r['desired_nameservers']===['new1.test','new2.test']);
    ck('Client projection has no owner approval or dispatch',$r['approval']===null&&$r['dispatch']===null&&$r['owner_request_id']===null);
    $context=local(fn()=>registered_nameserver_requests_context(registered_nameserver_requests_resource(str_repeat('b',32)),registered_nameserver_requests_actor(1)));
    $again=registered_nameserver_requests_create($r['id'],1,str_repeat('b',32),$context,['new1.test','new2.test'],true);ck('Canonical decoded actor allows exact create retry',count($m['client_events'])===1&&$again['id']===$r['id']);
    refuse('Changed request bytes cannot reuse ID',fn()=>registered_nameserver_requests_create($r['id'],1,str_repeat('b',32),$context,['other.test'],true));
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;refuse('Null contact marker rejected before hostname parsing '.$key,fn()=>registered_nameserver_requests_create('bad',1,'bad','bad',['bad hostname'],true));unset($_SESSION[$key]);}
    $_GET['support']='';refuse('Support query rejected before normalization',fn()=>registered_nameserver_requests_create('bad',1,'bad','bad',[],false));$_GET=[];
    $cancel=registered_nameserver_requests_change($r['id'],$r['latest_event'],str_repeat('5',32),'cancel','',1);ck('Actual client withdraw leaves no owner grant',$cancel['state']==='cancelled'&&$m['grants']===[]);
    $again=registered_nameserver_requests_change($r['id'],$r['latest_event'],str_repeat('5',32),'cancel','',1);ck('Exact canonical cancel retry is read only',$again['state']==='cancelled'&&count($m['client_events'])===2);
    $m['actor']['active']=true;refuse('Withdrawn request cannot be approved',fn()=>registered_nameserver_requests_approve($r['id'],$r['latest_event'],$r['request_sha256'],str_repeat('4',32),str_repeat('0',64),true,'Owner password',fn()=>'8.8.8.8','perform'));
    reset_client_model();$r=submit_client();$item=registered_nameserver_requests_screen(null,1,$r['id'])['items'][0];ck('Owner sees exact fresh complete old set',$item['approval']['old_nameservers']===['old1.test','old2.test']);
    $before=$m;$m['fail']='client_dispatch';refuse('Late dispatch audit failure rolls back atomic admission',fn()=>approve_client($item));ck('No owner grant request or dispatch survives late rollback',$m['grants']===[]&&$m['request']===[]&&count($m['client_events'])===1&&$calls===[]);$m['fail']='';
    $approved=approve_client($item,function()use(&$m){ck('Both original request and linked grant commit before resolver',!db()->inTransaction()&&count($m['grants'])===1&&count($m['request'])===1&&count($m['client_events'])===2);$GLOBALS['calls'][]='dns';return '8.8.8.8';});
    ck('Client approval uses unchanged real numeric registrar gateway',count(array_filter($calls,'is_array'))===1&&array_values(array_filter($calls,'is_array'))[0]['url']==='https://api.20i.com/domain/14701/nameservers');
    ck('Successful HTTP remains uncertain until separate matching read',$approved['state']==='uncertain'&&$approved['dispatch']['can_verify']);
    $saved=$calls;$retry=approve_client($item);ck('Exact approval retry performs no resolution unseal or send',$calls===$saved&&$retry['owner_request_id']===$approved['owner_request_id']);
    $client=registered_nameserver_requests_screen(1,1,$r['id'])['items'][0];ck('Client sees uncertainty without owner controls',$client['state']==='uncertain'&&$client['dispatch']===null&&$client['owner_request_id']===null&&$client['approval']===null&&!$client['can_cancel']);
    $snapshot=$m;$m['client_events']=[];refuse('Lost client origin cannot downgrade dispatched owner history',fn()=>local(fn()=>registered_nameserver_changes_ready()));$m=$snapshot;
    $m['client_events'][1]['payload']['owner_request_sha256']=str_repeat('0',64);refuse('Changed reciprocal approval SHA fails closed',fn()=>local(fn()=>registered_nameserver_changes_ready()));$m=$snapshot;
    $ownerId=$approved['owner_request_id'];$m['read_names']=['different.test'];registered_nameserver_changes_verify($ownerId,'Owner password');$client=registered_nameserver_requests_screen(1,1,$r['id'])['items'][0];ck('Different follow-up is not successful fulfillment',$client['state']==='uncertain'&&$client['last_check']['disposition']==='different');
    $m['read_names']=['new1.test','new2.test'];registered_nameserver_changes_verify($ownerId,'Owner password');$client=registered_nameserver_requests_screen(1,1,$r['id'])['items'][0];ck('Independent matching read reaches client history',$client['state']==='observed'&&$client['last_check']['nameservers']===['new1.test','new2.test']);
    ck('All review and readback paths preserve exactly one provider POST',count(array_filter($calls,'is_array'))===1);
    $_SESSION['client_id']=2;refuse('Another client cannot access exact original request',fn()=>registered_nameserver_requests_screen(2,1,$r['id']));
    reset_client_model();$m['reads'][str_repeat('1',32)]['observed_epoch']=time()-3600;$r=submit_client();ck('Local request remains possible with an aged nameserver observation',$r['state']==='pending');$owner=registered_nameserver_requests_screen(null,1,$r['id'])['items'][0];ck('Aged observation never offers owner approval',$owner['approval']===null);
    reset_client_model();$m['reads'][str_repeat('1',32)]['state']='unavailable';$m['reads'][str_repeat('1',32)]['nameservers']=null;$r=submit_client();ck('Failed latest nameserver read does not erase local intake',$r['state']==='pending'&&registered_nameserver_requests_screen(null,1,$r['id'])['items'][0]['approval']===null);
    reset_client_model();$r=submit_client();$item=registered_nameserver_requests_screen(null,1,$r['id'])['items'][0];$m['client']['auth_version']=str_repeat('8',32);refuse('Client credential change invalidates old owner review',fn()=>approve_client($item));ck('Credential change refusal precedes all transport',$calls===[]&&$m['grants']===[]);
    reset_client_model();$r=submit_client();$item=registered_nameserver_requests_screen(null,1,$r['id'])['items'][0];$out=approve_client($item,function()use(&$m){$m['client']['auth_version']=str_repeat('8',32);$GLOBALS['calls'][]='dns';return '8.8.8.8';});ck('Client auth revocation during resolution prevents unseal and send',$out['state']==='not_sent'&&$calls===['dns']&&$m['ledgers']===[]);
    reset_client_model();$r=submit_client();$item=registered_nameserver_requests_screen(null,1,$r['id'])['items'][0];$m['profile']['credentials']='replacement-cipher';refuse('Changed sealed credential invalidates static consent',fn()=>approve_client($item));
    reset_client_model();$r=submit_client();$item=registered_nameserver_requests_screen(null,1,$r['id'])['items'][0];$m['assigning_owner']['auth_version']=str_repeat('9',32);refuse('Changed assigning-owner credential invalidates static consent',fn()=>approve_client($item));
    reset_client_model();$r=submit_client();$item=registered_nameserver_requests_screen(null,1,$r['id'])['items'][0];$declined=registered_nameserver_requests_change($r['id'],$r['latest_event'],str_repeat('6',32),'decline','Please check these nameservers.',null,'Owner password');ck('Owner decline reaches client history without provider work',$declined['state']==='declined'&&registered_nameserver_requests_screen(1,1,$r['id'])['items'][0]['note']==='Please check these nameservers.'&&$calls===[]);
    $again=registered_nameserver_requests_change($r['id'],$r['latest_event'],str_repeat('6',32),'decline','Please check these nameservers.',null,'Owner password');ck('Exact decoded decline retry is idempotent',count($m['client_events'])===2&&$again['state']==='declined');
    reset_client_model();$r=submit_client(desired:['old1.test','replacement.test']);ck('Unsupported overlapping desired set remains a local review request',registered_nameserver_requests_screen(null,1,$r['id'])['items'][0]['approval']===null&&$m['grants']===[]);
    echo json_encode(['passed'=>true,'count'=>count($checks),'checks'=>$checks,'service_sha256'=>hash_file('sha256',$clientService),'owner_service_sha256'=>hash_file('sha256',$service),'scope'=>'Actual request/approval/one-send/readback bodies, canonical documents and typed gateway; modeled SQL/session/storage/reads. No native SQL or network acceptance.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
}catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().' at '.$e->getLine()."\n");echo json_encode(['passed'=>false,'checks'=>$checks],JSON_PRETTY_PRINT),"\n";exit(1);}
