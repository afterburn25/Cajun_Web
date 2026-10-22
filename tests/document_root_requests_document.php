<?php
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',realpath(($argv[1]??dirname(__DIR__)).'/site'));
$checks=[];
function nrq_check(string $name,bool $value):void {global $checks;foreach($checks as $c)if($c['name']===$name)throw new LogicException('Duplicate test name.');$checks[]=['name'=>$name,'passed'=>$value];if(!$value)throw new RuntimeException($name);}
function nrq_refuse(string $name,callable $work):void {try{$work();}catch(LogicException|RuntimeException|JsonException){nrq_check($name,true);return;}nrq_check($name,false);}
function nrq_event(string $id,string $subject,int $ordinal,string $kind,array $payload,array $actor):array {return ['version'=>1,'id'=>$id,'subject_id'=>$subject,'client_id'=>7,'kind'=>$kind,'actor'=>$actor,'recorded_epoch'=>1700000000+$ordinal,'ordinal'=>$ordinal,'previous'=>str_repeat('0',64),'payload'=>$payload];}
try{
    // Load only the actual existing standalone canonical function, not provider/DB bootstrap.
    $source=file_get_contents(CWP_CODE_ROOT.'/app/providers/operations.php');
    if(preg_match_all('/^function provider_operations_canonical\(mixed \$value,int \$depth,int &\$nodes\): string \{.*?^\}/ms',$source,$matches)!==1)throw new RuntimeException('Canonical production function is not uniquely extractable.');
    eval($matches[0][0]);
    require CWP_CODE_ROOT.'/app/providers/document_root_requests_document.php';
    require CWP_CODE_ROOT.'/database/schema/provider_document_root_requests_schema.php';
    $package=['profile_id'=>str_repeat('a',32),'profile_revision'=>3,'key_fingerprint'=>str_repeat('b',64),'credential_sha256'=>str_repeat('2',64),'package_id'=>701,'client_id'=>7,'link_id'=>str_repeat('c',32),'link_sha256'=>str_repeat('9',64),'event_id'=>8,'event_sha256'=>str_repeat('d',64),'audit_id'=>9,'audit_sha256'=>str_repeat('e',64),'source_id'=>str_repeat('f',32),'source_seq'=>10,'source_sha256'=>str_repeat('1',64)];
    $binding=['operation'=>'package.document-roots','package'=>$package];
    $resource=['binding'=>$binding,'owner_id'=>11,'owner_auth_sha256'=>str_repeat('3',64),'sharing'=>['id'=>str_repeat('8',32),'revision'=>1,'sha256'=>str_repeat('9',64),'owner_id'=>11,'owner_auth_sha256'=>str_repeat('3',64)],'observation'=>['id'=>str_repeat('a',32),'seq'=>11,'proof'=>str_repeat('b',64)]];
    $client=['kind'=>'client','id'=>7,'auth_sha256'=>hash('sha256',str_repeat('4',32))];$owner=['kind'=>'owner','id'=>12,'auth_sha256'=>hash('sha256',str_repeat('5',32))];
    $requestId=str_repeat('6',32);$dispatchId=str_repeat('7',32);
    $request=nrq_event($requestId,$requestId,1,'request',['resource'=>$resource,'name'=>'site.example.invalid','before_path'=>'public_html/old','desired_path'=>'public_html/new','confirmed'=>true],$client);
    provider_document_root_requests_event_document($request);$json=provider_document_root_requests_json($request);$decoded=json_decode($json,true,16,JSON_THROW_ON_ERROR);
    nrq_check('Canonical original event roundtrip is byte stable',provider_document_root_requests_json($decoded)===$json);
    nrq_check('Binding restores exact foundation constructor field order',provider_document_root_requests_binding($decoded['payload']['resource']['binding'])===$binding);
    nrq_check('Full original assignment source survives canonical ordering',provider_document_root_requests_binding($decoded['payload']['resource']['binding'])['package']['source_id']===$package['source_id']);
    nrq_check('Request actor retains auth digest rather than raw auth token',$decoded['actor']['auth_sha256']===$client['auth_sha256']&&!str_contains($json,str_repeat('4',32)));
    $project=provider_document_root_requests_project([$decoded]);$r=$project['requests'][$requestId];
    nrq_check('Initial projection has exact documented keys',array_keys($project)===['requests']&&array_keys($r)===['request','state','latest_event','events']);
    nrq_check('Local request is pending with original event and empty later history',$r['state']==='pending'&&$r['request']===$decoded&&$r['latest_event']===$requestId&&$r['events']===[]);
    nrq_check('Empty local graph is explicitly empty',provider_document_root_requests_project([])===['requests'=>[]]);
    foreach(['version'=>2,'id'=>'bad','subject_id'=>$dispatchId,'client_id'=>8,'kind'=>'fulfilled','recorded_epoch'=>0,'ordinal'=>0,'previous'=>'x','payload'=>null] as $key=>$value){$x=$request;$x[$key]=$value;nrq_refuse('Malformed request refuses '.$key,fn()=>provider_document_root_requests_event_document($x));}
    foreach(['recorded_epoch','ordinal'] as $key){$x=$request;$x[$key]=2147483648;nrq_refuse('Local signed32 bound refuses '.$key,fn()=>provider_document_root_requests_event_document($x));}
    $x=$request;$x['extra']=true;nrq_refuse('Unknown top-level fields refuse',fn()=>provider_document_root_requests_event_document($x));
    foreach(['kind'=>'owner','id'=>8,'auth_sha256'=>str_repeat('4',32)] as $key=>$value){$x=$request;$x['actor'][$key]=$value;nrq_refuse('Wrong original client actor refuses '.$key,fn()=>provider_document_root_requests_event_document($x));}
    $x=$request;$x['actor']['auth_version']=str_repeat('4',32);nrq_refuse('Raw auth-version actor field refuses',fn()=>provider_document_root_requests_event_document($x));
    foreach([false,1,'true',null] as $i=>$value){$x=$request;$x['payload']['confirmed']=$value;nrq_refuse('Confirmation must be exact true '.$i,fn()=>provider_document_root_requests_event_document($x));}
    foreach([1,0,true,false,null,[],new stdClass,'','/root','root/','a//b','.','..','a/../b','a/./b','a\\b','bad path','a%2fb',str_repeat('x',1025)] as $i=>$value){$x=$request;$x['payload']['desired_path']=$value;nrq_refuse('Requested path uses the unchanged typed write boundary '.$i,fn()=>provider_document_root_requests_event_document($x));}
    foreach(['', '../odd/雪', '/opaque/root', '  old path  ', str_repeat('雪',341).'x'] as $i=>$value){$x=$request;$x['payload']['before_path']=$value;provider_document_root_requests_event_document($x);$round=json_decode(provider_document_root_requests_json($x),true,16,JSON_THROW_ON_ERROR);nrq_check('Observed old path remains exact opaque text '.$i,$round['payload']['before_path']===$value);}
    foreach([null,false,[],str_repeat('x',1025),"bad\npath","\xFF"] as $i=>$value){$x=$request;$x['payload']['before_path']=$value;nrq_refuse('Invalid old observed path refuses '.$i,fn()=>provider_document_root_requests_event_document($x));}
    $x=$request;$x['payload']['desired_path']=str_repeat('x',1024);provider_document_root_requests_event_document($x);nrq_check('New path accepts exact unchanged1024-byte boundary',strlen($x['payload']['desired_path'])===1024);
    $x=$request;$x['payload']['desired_path']=$x['payload']['before_path'];nrq_refuse('Unchanged selected path refuses',fn()=>provider_document_root_requests_event_document($x));
    foreach(['example.invalid.','UPPER.example.invalid','*.example.invalid','127.0.0.1','https://example.invalid',null] as $i=>$value){$x=$request;$x['payload']['name']=$value;nrq_refuse('Unsupported hostname refuses '.$i,fn()=>provider_document_root_requests_event_document($x));}
    $x=$request;$x['payload']['entries']=[];nrq_refuse('Full observed map is not part of request payload',fn()=>provider_document_root_requests_event_document($x));
    foreach($package as $key=>$value){$x=$request;unset($x['payload']['resource']['binding']['package'][$key]);nrq_refuse('Every original binding field is required '.$key,fn()=>provider_document_root_requests_event_document($x));}
    foreach(['package_id'=>'701','client_id'=>0,'source_seq'=>0,'profile_revision'=>0,'source_sha256'=>'bad'] as $key=>$value){$x=$request;$x['payload']['resource']['binding']['package'][$key]=$value;nrq_refuse('Invalid typed binding refuses '.$key,fn()=>provider_document_root_requests_event_document($x));}
    foreach(['owner_auth_sha256'=>str_repeat('0',32),'owner_id'=>0] as $key=>$value){$x=$request;$x['payload']['resource'][$key]=$value;nrq_refuse('Invalid static resource witness refuses '.$key,fn()=>provider_document_root_requests_event_document($x));}
    foreach(['id'=>'bad','revision'=>0,'sha256'=>'bad','owner_id'=>0,'owner_auth_sha256'=>'bad'] as $key=>$value){$x=$request;$x['payload']['resource']['sharing'][$key]=$value;nrq_refuse('Invalid sharing authority refuses '.$key,fn()=>provider_document_root_requests_event_document($x));}
    foreach(['operation'=>'package.php-version','package'=>null] as $key=>$value){$x=$request;$x['payload']['resource']['binding'][$key]=$value;nrq_refuse('Wrong nested binding envelope refuses '.$key,fn()=>provider_document_root_requests_event_document($x));}
    foreach(['id'=>'bad','seq'=>0,'proof'=>'bad'] as $key=>$value){$x=$request;$x['payload']['resource']['observation'][$key]=$value;nrq_refuse('Invalid submission observation refuses '.$key,fn()=>provider_document_root_requests_event_document($x));}
    foreach(['sharing','observation'] as $part){$x=$request;$x['payload']['resource'][$part]['extra']=true;nrq_refuse('Closed nested evidence refuses extra '.$part,fn()=>provider_document_root_requests_event_document($x));}
    $x=$request;$x['payload']['before_path']=str_repeat('雪',341).'x';$x['payload']['desired_path']=str_repeat('n',1024);$maximum=provider_document_root_requests_json($x);provider_document_root_requests_event_document(json_decode($maximum,true,16,JSON_THROW_ON_ERROR));nrq_check('Large Unicode old path and maximum write path retain complete canonical evidence',strlen($x['payload']['before_path'])===1024&&strlen($x['payload']['desired_path'])===1024&&strlen($maximum)<32768&&provider_document_root_requests_json(json_decode($maximum,true,16,JSON_THROW_ON_ERROR))===$maximum);
    // Valid semantic edits are different historical documents; live/pinned authority is separate.
    foreach(['owner_auth_sha256'] as $key){$x=$request;$x['payload']['resource'][$key]=str_repeat('9',64);provider_document_root_requests_event_document($x);nrq_check('Valid alternate resource changes original canonical hash '.$key,hash('sha256',provider_document_root_requests_json($x))!==hash('sha256',$json));}
    $x=$request;$x['actor']['auth_sha256']=str_repeat('9',64);provider_document_root_requests_event_document($x);nrq_check('Changed client auth changes retained request hash',hash('sha256',provider_document_root_requests_json($x))!==hash('sha256',$json));
    $dispatch=nrq_event($dispatchId,$requestId,2,'dispatch',['previous_event'=>$requestId,'owner_request_id'=>$dispatchId,'owner_request_sha256'=>str_repeat('8',64)],$owner);
    $done=provider_document_root_requests_project([$request,$dispatch])['requests'][$requestId];
    nrq_check('Owner admission records dispatched but never succeeded',$done['state']==='dispatched'&&$done['latest_event']===$dispatchId&&$done['events']===[$dispatch]);
    nrq_check('Approving owner remains distinct from original assignment owner',$done['events'][0]['actor']['id']===12&&$done['request']['payload']['resource']['owner_id']===11);
    foreach(['owner_request_id'=>str_repeat('9',32),'owner_request_sha256'=>'bad','previous_event'=>str_repeat('9',32)] as $key=>$value){$x=$dispatch;$x['payload'][$key]=$value;nrq_refuse('Changed dispatch origin refuses '.$key,fn()=>provider_document_root_requests_project([$request,$x]));}
    $x=$dispatch;$x['actor']=$client;nrq_refuse('Client cannot dispatch',fn()=>provider_document_root_requests_project([$request,$x]));
    $x=$dispatch;$x['payload']['dispatch_sha256']=str_repeat('9',64);nrq_refuse('Circular extra dispatch identity field refuses',fn()=>provider_document_root_requests_project([$request,$x]));
    $x=$dispatch;$x['payload']['owner_request_sha256']=str_repeat('9',64);provider_document_root_requests_event_document($x);nrq_check('Owner approval hash is pinned data rather than self-authenticating authority',provider_document_root_requests_json($x)!==provider_document_root_requests_json($dispatch));
    $cancel=nrq_event(str_repeat('a',32),$requestId,2,'cancel',['previous_event'=>$requestId,'note'=>''],$client);
    $decline=nrq_event(str_repeat('b',32),$requestId,2,'decline',['previous_event'=>$requestId,'note'=>'Please confirm the requested website folder.'],$owner);
    foreach(['cancelled'=>$cancel,'declined'=>$decline] as $state=>$event){$p=provider_document_root_requests_project([$request,$event]);nrq_check('Terminal local state '.$state,$p['requests'][$requestId]['state']===$state&&$p['requests'][$requestId]['events']===[$event]);}
    foreach([['cancel',$cancel,'note','not empty'],['decline',$decline,'note',''],['decline',$decline,'note',' padded '],['decline',$decline,'note',str_repeat('a',1001)],['decline',$decline,'note',"bad\0note"],['decline',$decline,'note',"\xFF"]] as $i=>$case){$x=$case[1];$x['payload'][$case[2]]=$case[3];nrq_refuse('Invalid decision note refuses '.$i,fn()=>provider_document_root_requests_project([$request,$x]));}
    $x=$cancel;$x['actor']['id']=8;nrq_refuse('Another client cannot cancel',fn()=>provider_document_root_requests_project([$request,$x]));
    $x=$decline;$x['client_id']=8;nrq_refuse('Decision cannot change original client',fn()=>provider_document_root_requests_project([$request,$x]));
    $x=$decline;$x['payload']['note']=str_repeat('x',1000);provider_document_root_requests_project([$request,$x]);nrq_check('Decision note exact1000-byte boundary is accepted',strlen($x['payload']['note'])===1000);
    nrq_check('Note normalizer preserves printable markup for escaped rendering',provider_document_root_requests_note(' <request> & review ')==='<request> & review');
    foreach([$cancel,$decline,$dispatch] as $i=>$event){$x=$cancel;$x['id']=str_repeat('d',32);$x['ordinal']=3;$x['recorded_epoch']++;$x['payload']['previous_event']=$event['id'];nrq_refuse('Terminal request cannot transition again '.$i,fn()=>provider_document_root_requests_project([$request,$event,$x]));}
    $second=$request;$second['id']=str_repeat('c',32);$second['subject_id']=$second['id'];$second['ordinal']=2;$second['recorded_epoch']++;
    nrq_refuse('Second pending request for same hosting package refuses',fn()=>provider_document_root_requests_project([$request,$second]));
    $x=$second;$x['payload']['resource']['binding']['package']['link_id']=str_repeat('e',32);nrq_refuse('Reassignment cannot bypass same-resource pending history',fn()=>provider_document_root_requests_project([$request,$x]));
    foreach(['package_id'=>702,'profile_id'=>str_repeat('d',32)] as $key=>$value){$x=$second;$x['payload']['resource']['binding']['package'][$key]=$value;nrq_check('Different resource may have independent pending request '.$key,count(provider_document_root_requests_project([$request,$x])['requests'])===2);}
    $second['ordinal']=3;$second['recorded_epoch']++;nrq_check('Terminal local request permits later separately approved intake',count(provider_document_root_requests_project([$request,$cancel,$second])['requests'])===2);
    $x=$dispatch;$x['ordinal']=3;nrq_refuse('Missing ordinal refuses',fn()=>provider_document_root_requests_project([$request,$x]));
    $x=$dispatch;$x['recorded_epoch']=$request['recorded_epoch']-1;nrq_refuse('Backward event time refuses',fn()=>provider_document_root_requests_project([$request,$x]));
    nrq_refuse('Missing original request refuses',fn()=>provider_document_root_requests_project([$dispatch]));
    nrq_refuse('Reordered history refuses',fn()=>provider_document_root_requests_project([$dispatch,$request]));
    nrq_refuse('Associative event list refuses',fn()=>provider_document_root_requests_project(['request'=>$request]));
    $x=$dispatch;$x['id']=$requestId;$x['payload']['owner_request_id']=$requestId;nrq_refuse('Repeated event identity refuses',fn()=>provider_document_root_requests_project([$request,$x]));
    $x=$second;$x['ordinal']=2;$x['payload']['name']='other.example.invalid';nrq_refuse('Another hostname cannot bypass the pending package request',fn()=>provider_document_root_requests_project([$request,$x]));
    $sql=provider_document_root_requests_schema(str_repeat('p',19).'_');preg_match('/CREATE TABLE `([^`]+)`/',$sql,$table);
    preg_match('/CONSTRAINT `([^`]+)` FOREIGN KEY/',$sql,$constraint);
    nrq_check('Longest supported prefix fits table and explicit FK identifiers',strlen($table[1])<=64&&isset($constraint[1])&&strlen($constraint[1])<=64);
    nrq_check('Request table is outside existing owner lifecycle tables',substr($table[1],20)==='provider_website_folder_requests_events');
    nrq_check('Schema retains unique sequence and actual client FK',str_contains($sql,'UNIQUE KEY event_ordinal(ordinal)')&&str_contains($sql,'FOREIGN KEY (client_id)')&&str_contains($sql,'ON DELETE RESTRICT'));
    nrq_refuse('Schema refuses injected prefix',fn()=>provider_document_root_requests_schema('x`;DROP'));
    nrq_check('Pure document execution loads no database or provider authority',!function_exists('db')&&!function_exists('provider_settings_owner')&&!function_exists('provider_operations_perform'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected '.get_class($e).': '.$e->getMessage(),'passed'=>false];}
$passed=!array_filter($checks,fn($c)=>$c['passed']!==true);echo json_encode(['passed'=>$passed,'count'=>count($checks),'checks'=>$checks,'scope'=>'Actual pure local request documents, canonical production function and historical transition graph; no SQL, live authority, owner dispatch, provider or browser acceptance'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($passed?0:1);
