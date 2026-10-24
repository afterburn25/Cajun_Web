<?php
/** Actual document/projection bodies and canonical encoder; no SQL or provider execution. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__,2).'/site');
function db():never{throw new LogicException('Pure test must not open SQL.');}
function one(mixed ...$args):never{throw new LogicException('Pure test must not read SQL.');}
function all(mixed ...$args):never{throw new LogicException('Pure test must not read SQL.');}
function query(mixed ...$args):never{throw new LogicException('Pure test must not write SQL.');}
require CWP_CODE_ROOT.'/app/providers/cloud_site_requests_storage.php';
use CajunWebPro\Foundation\TwentyICloudAssignment\CloudAssignmentBinding;
$checks=[];
function csr_check(string $name,bool $passed):void{
    global $checks;foreach($checks as $c)if($c['name']===$name)throw new LogicException('Duplicate result label.');
    $checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);
}
function csr_deny(string $name,callable $work):void{
    $denied=false;try{$work();}catch(InvalidArgumentException|RuntimeException $e){$denied=true;}csr_check($name,$denied);
}
function csr_binding():array{return ['profile_id'=>str_repeat('1',32),'profile_revision'=>2,'credential_sha256'=>str_repeat('2',64),'key_fingerprint'=>str_repeat('3',64),'server_id'=>701,'client_id'=>7,'link_id'=>str_repeat('4',32),'link_revision'=>3,'link_sha256'=>str_repeat('5',64),'event_id'=>19,'event_proof'=>str_repeat('6',64),'source_id'=>str_repeat('7',32),'source_seq'=>29,'start_proof'=>str_repeat('8',64),'finish_proof'=>str_repeat('9',64),'server_sha256'=>str_repeat('a',64)];}
function csr_request(string $id='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'):array{
    return ['version'=>1,'id'=>$id,'subject_id'=>$id,'client_id'=>7,'kind'=>'request','actor'=>['kind'=>'client','id'=>7,'auth_sha256'=>str_repeat('b',64)],'recorded_epoch'=>1700000000,'ordinal'=>1,'previous'=>str_repeat('0',64),'payload'=>['binding'=>csr_binding(),'domain'=>'website.example.invalid','aliases'=>['alias.example.invalid'],'confirmed'=>true]];
}
function csr_decision(string $kind,array $request,?array $binding=null):array{
    $b=$binding??$request['payload']['binding'];$e=$request;$e['id']=str_repeat($kind==='cancel'?'c':($kind==='decline'?'d':'e'),32);$e['subject_id']=$request['id'];$e['kind']=$kind;$e['ordinal']=2;$e['recorded_epoch']++;
    if($kind!=='cancel')$e['actor']=['kind'=>'owner','id'=>2,'auth_sha256'=>str_repeat('f',64)];
    $e['payload']=$kind==='dispatch'?['previous_event'=>$request['id'],'grant_id'=>str_repeat('9',32),'binding'=>$b,'cloud_binding'=>['link_id'=>$b['link_id'],'link_revision'=>$b['link_revision'],'client_id'=>$b['client_id'],'server_id'=>$b['server_id'],'profile_revision'=>$b['profile_revision'],'key_fingerprint'=>$b['key_fingerprint'],'refresh_id'=>$b['source_id'],'credentials_hash'=>$b['credential_sha256'],'actor_auth'=>$e['actor']['auth_sha256']],'confirmed'=>true]:['previous_event'=>$request['id'],'note'=>$kind==='cancel'?'':'Capacity review required.'];return $e;
}
function csr_changed(array $event,string $path,mixed $value):array{
    $cursor=&$event;foreach(explode('.',$path) as $key)$cursor=&$cursor[$key];$cursor=$value;unset($cursor);return $event;
}
try{
    $r=csr_request();$d=csr_decision('dispatch',$r);
    provider_cloud_site_requests_event_document($r);provider_cloud_site_requests_event_document($d);
    csr_check('Cold production includes perform no SQL',true);
    csr_check('Empty history projects no requests',provider_cloud_site_requests_project([])===['requests'=>[]]);
    $p=provider_cloud_site_requests_project([$r]);
    csr_check('Intake is pending and retains exact original review',$p['requests'][$r['id']]===['request'=>$r,'stage'=>'pending','latest_event'=>$r['id'],'latest'=>$r,'dispatch'=>null]);
    foreach(['cancel'=>'cancelled','decline'=>'declined','dispatch'=>'dispatched'] as $kind=>$stage){
        $event=csr_decision($kind,$r);$p=provider_cloud_site_requests_project([$r,$event])['requests'][$r['id']];
        csr_check('Exact '.$kind.' produces only its local stage',$p['stage']===$stage&&$p['request']===$r&&$p['latest']===$event&&$p['latest_event']===$event['id']&&$p['dispatch']===($kind==='dispatch'?$event:null));
    }
    $json=provider_cloud_site_requests_json($d);$restored=json_decode($json,true,16,JSON_THROW_ON_ERROR);
    csr_check('Canonical JSON actually sorts associative binding fields',array_keys($restored['payload']['binding'])!==array_keys(csr_binding()));
    provider_cloud_site_requests_event_document($restored);
    csr_check('Sorted JSON round trip reconstructs the declared foundation order',provider_cloud_site_requests_binding($restored['payload']['binding'])===(new CloudAssignmentBinding(csr_binding()))->data);
    $events=json_decode(provider_cloud_site_requests_json([$r,$d]),true,16,JSON_THROW_ON_ERROR);
    csr_check('Canonical restored events project dispatched history',provider_cloud_site_requests_project($events)['requests'][$r['id']]['stage']==='dispatched');
    csr_check('Canonical event bytes are stable after historical parse',provider_cloud_site_requests_json($restored)===$json);
    $historical=csr_changed($r,'recorded_epoch',1);provider_cloud_site_requests_event_document($historical);
    csr_check('Historical parsing does not invent current authority or expiry',$historical['recorded_epoch']===1);
    $changed=csr_binding();foreach(['source_id','source_seq','start_proof','finish_proof','server_sha256'] as $key)$changed[$key]=is_int($changed[$key])?$changed[$key]+1:str_repeat('c',strlen($changed[$key]));
    csr_check('Later complete observation may retain the original assignment',provider_cloud_site_requests_same_assignment(csr_binding(),$changed));
    csr_check('Dispatch accepts changed observation evidence for the same assignment',provider_cloud_site_requests_project([$r,csr_decision('dispatch',$r,$changed)])['requests'][$r['id']]['stage']==='dispatched');
    foreach(array_keys(array_slice(csr_binding(),0,11,true)) as $key){
        $b=csr_binding();$b[$key]=is_int($b[$key])?$b[$key]+1:str_repeat('c',strlen($b[$key]));
        csr_check('Static assignment difference detected: '.$key,!provider_cloud_site_requests_same_assignment(csr_binding(),$b));
        csr_deny('Dispatch refuses changed static assignment: '.$key,fn()=>provider_cloud_site_requests_project([$r,csr_decision('dispatch',$r,$b)]));
    }
    foreach(array_keys(csr_binding()) as $key){
        $b=csr_binding();unset($b[$key]);csr_deny('Binding requires exact field: '.$key,fn()=>provider_cloud_site_requests_binding($b));
    }
    $b=csr_binding();$b['extra']='private';csr_deny('Binding rejects unknown fields',fn()=>provider_cloud_site_requests_binding($b));
    foreach(['string server'=>['server_id','701'],'zero source'=>['source_seq',0],'invalid hash'=>['link_sha256','a'],'uppercase ID'=>['profile_id',str_repeat('A',32)],'float revision'=>['profile_revision',2.0]] as $label=>[$key,$value]){
        $b=csr_binding();$b[$key]=$value;csr_deny('Binding rejects '.$label,fn()=>provider_cloud_site_requests_binding($b));
    }
    foreach(['version'=>2,'id'=>str_repeat('A',32),'subject_id'=>str_repeat('d',32),'client_id'=>'7','recorded_epoch'=>0,'ordinal'=>0,'previous'=>'bad','kind'=>'accepted','actor.kind'=>'owner','actor.id'=>8,'actor.auth_sha256'=>str_repeat('A',64),'payload.confirmed'=>1,'payload.domain'=>'Website.example.invalid','payload.aliases'=>['website.example.invalid']] as $path=>$value){
        $bad=csr_changed($r,$path,$value);csr_deny('Request refuses changed '.$path,fn()=>provider_cloud_site_requests_event_document($bad));
    }
    foreach(['top'=>['extra'=>true],'actor'=>['extra'=>true],'payload'=>['extra'=>true]] as $where=>$extra){
        $bad=$r;if($where==='top')$bad+=$extra;else $bad[$where]+=$extra;
        csr_deny('Request rejects extra '.$where.' fields',fn()=>provider_cloud_site_requests_event_document($bad));
    }
    foreach(['empty'=>'','url'=>'https://website.example.invalid','trailing dot'=>'website.example.invalid.','IP'=>'127.0.0.1','unicode'=>'café.example.invalid'] as $label=>$value){
        $bad=csr_changed($r,'payload.domain',$value);csr_deny('No domain normalization for '.$label,fn()=>provider_cloud_site_requests_event_document($bad));
    }
    $aliases=[];for($i=0;$i<20;$i++)$aliases[]='alias'.$i.'.example.invalid';
    $max=csr_changed($r,'payload.aliases',$aliases);provider_cloud_site_requests_event_document($max);csr_check('Twenty distinct aliases retain exact ordering',$max['payload']['aliases']===$aliases);
    $aliases[]='extra.example.invalid';csr_deny('Twenty one aliases refuse',fn()=>provider_cloud_site_requests_event_document(csr_changed($r,'payload.aliases',$aliases)));
    foreach(['duplicate'=>['a.example.invalid','a.example.invalid'],'associative'=>['alias'=>'a.example.invalid'],'nonstring'=>[7]] as $label=>$value)csr_deny('Alias '.$label.' refuses',fn()=>provider_cloud_site_requests_event_document(csr_changed($r,'payload.aliases',$value)));
    foreach(['previous_event'=>str_repeat('0',32),'client_id'=>8,'actor.kind'=>'client','actor.auth_sha256'=>str_repeat('0',64),'payload.confirmed'=>false] as $path=>$value){
        $actual=$path==='previous_event'?'payload.previous_event':$path;$bad=csr_changed($d,$actual,$value);
        csr_deny('Dispatch refuses mismatch '.$path,fn()=>provider_cloud_site_requests_project([$r,$bad]));
    }
    foreach(array_keys($d['payload']['cloud_binding']) as $key){
        $value=$d['payload']['cloud_binding'][$key];$bad=csr_changed($d,'payload.cloud_binding.'.$key,is_int($value)?$value+1:str_repeat('0',strlen($value)));
        csr_deny('Dispatch cross binding refuses '.$key,fn()=>provider_cloud_site_requests_event_document($bad));
    }
    $bad=$d;$bad['payload']['cloud_binding']['extra']='x';csr_deny('Dispatch cloud binding is closed',fn()=>provider_cloud_site_requests_event_document($bad));
    $cancel=csr_decision('cancel',$r);
    csr_deny('Cancellation requires original client authorization hash',fn()=>provider_cloud_site_requests_project([$r,csr_changed($cancel,'actor.auth_sha256',str_repeat('1',64))]));
    csr_deny('Cancellation cannot add a review note',fn()=>provider_cloud_site_requests_event_document(csr_changed($cancel,'payload.note','note')));
    $decline=csr_decision('decline',$r);
    foreach(['empty'=>'','untrimmed'=>' message ','newline'=>"line\nnext",'tab'=>"line\tnext",'NUL'=>"line\0next",'Unicode control'=>"line\u{0085}next",'invalid UTF8'=>"\xff",'oversize'=>str_repeat('x',1001)] as $label=>$note)csr_deny('Decline refuses '.$label.' note',fn()=>provider_cloud_site_requests_event_document(csr_changed($decline,'payload.note',$note)));
    $note=str_repeat('é',500);provider_cloud_site_requests_event_document(csr_changed($decline,'payload.note',$note));csr_check('Decline permits exact 1000 byte UTF8 note',strlen($note)===1000);
    csr_check('Review note helper returns trimmed safe text',provider_cloud_site_requests_note(' message ')==='message');
    csr_deny('Orphan terminal event refuses',fn()=>provider_cloud_site_requests_project([$d]));
    csr_deny('Duplicate event ID refuses',fn()=>provider_cloud_site_requests_project([$r,$r]));
    csr_deny('Nonlist event history refuses',fn()=>provider_cloud_site_requests_project(['first'=>$r]));
    foreach(['cancel','decline','dispatch'] as $first){foreach(['cancel','decline','dispatch'] as $second){
        $a=csr_decision($first,$r);$b=csr_decision($second,$r);$b['id']=str_repeat('1',32);$b['payload']['previous_event']=$a['id'];
        csr_deny('Only one terminal transition '.$first.' then '.$second,fn()=>provider_cloud_site_requests_project([$r,$a,$b]));
    }}
    $other=csr_request(str_repeat('2',32));$otherDispatch=csr_decision('dispatch',$other);$otherDispatch['id']=str_repeat('3',32);
    csr_deny('One shared grant cannot dispatch two local requests',fn()=>provider_cloud_site_requests_project([$r,$d,$other,$otherDispatch]));
    $otherDispatch['payload']['grant_id']=str_repeat('4',32);
    csr_check('Independent requests may each receive a distinct dispatch',count(provider_cloud_site_requests_project([$r,$d,$other,$otherDispatch])['requests'])===2);
    $meta=['request_id'=>$r['id'],'request_hash'=>hash('sha256',provider_cloud_site_requests_json($r)),'dispatch_id'=>$d['id'],'dispatch_hash'=>hash('sha256',$json)];provider_cloud_site_requests_meta($meta);
    csr_check('Closed signed grant metadata carries exact document hashes',strlen($meta['request_hash'])===64&&strlen($meta['dispatch_hash'])===64);
    foreach(array_keys($meta) as $key){$bad=$meta;unset($bad[$key]);csr_deny('Metadata requires '.$key,fn()=>provider_cloud_site_requests_meta($bad));$bad=$meta;$bad[$key]='wrong';csr_deny('Metadata validates '.$key,fn()=>provider_cloud_site_requests_meta($bad));}
    $bad=$meta;$bad['result']='succeeded';csr_deny('Metadata cannot invent provider result fields',fn()=>provider_cloud_site_requests_meta($bad));
    $sql=provider_cloud_site_requests_schema('cwri_');csr_check('Schema separates actor documents from shared grants',str_contains($sql,'provider_cloud_site_request_events')&&str_contains($sql,'UNIQUE KEY event_ordinal(ordinal)')&&str_contains($sql,'studio_clients')&&!str_contains($sql,'provider_operation_grants'));
    foreach(['','x','prefix;drop','123_bad',str_repeat('a',21)] as $prefix)csr_deny('Schema refuses invalid prefix '.json_encode($prefix),fn()=>provider_cloud_site_requests_schema($prefix));
}catch(Throwable $error){$checks[]=['name'=>'Unexpected '.get_class($error).': '.$error->getMessage(),'passed'=>false];fwrite(STDERR,$error->__toString().PHP_EOL);}
$passed=!array_filter($checks,fn($c)=>$c['passed']!==true);
echo json_encode(['passed'=>$passed,'scope'=>'Actual pure document/projection/canonical/schema bodies; SQL sentinels only. No native authority, provider dispatch or UI acceptance.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
