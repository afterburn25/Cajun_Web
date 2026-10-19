<?php
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',realpath(__DIR__.'/../site'));
$checks=[];
function nrq_check(string $name,bool $value):void {global $checks;foreach($checks as $c)if($c['name']===$name)throw new LogicException('Duplicate test name.');$checks[]=['name'=>$name,'passed'=>$value];if(!$value)throw new RuntimeException($name);}
function nrq_refuse(string $name,callable $work):void {try{$work();}catch(LogicException|RuntimeException|JsonException){nrq_check($name,true);return;}nrq_check($name,false);}
function nrq_event(string $id,string $subject,int $ordinal,string $kind,array $payload,array $actor):array {return ['version'=>1,'id'=>$id,'subject_id'=>$subject,'client_id'=>7,'kind'=>$kind,'actor'=>$actor,'recorded_epoch'=>1700000000+$ordinal,'ordinal'=>$ordinal,'previous'=>str_repeat('0',64),'payload'=>$payload];}
try{
    // Load only the actual existing standalone canonical function, not provider/DB bootstrap.
    $source=file_get_contents(CWP_CODE_ROOT.'/app/providers/operations.php');
    if(preg_match_all('/^function provider_operations_canonical\(mixed \$value,int \$depth,int &\$nodes\): string \{.*?^\}/ms',$source,$matches)!==1)throw new RuntimeException('Canonical production function is not uniquely extractable.');
    eval($matches[0][0]);
    require CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests_document.php';
    require CWP_CODE_ROOT.'/database/schema/registered_nameserver_requests_schema.php';
    $binding=['profile_id'=>str_repeat('a',32),'profile_revision'=>3,'key_fingerprint'=>str_repeat('b',64),'domain_id'=>14701,'domain'=>'example.invalid','link_id'=>str_repeat('c',32),'link_revision'=>2,'client_id'=>7,'link_event_id'=>8,'link_event_sha256'=>str_repeat('d',64),'link_audit_id'=>9,'link_audit_sha256'=>str_repeat('e',64),'source_refresh_id'=>str_repeat('f',32),'source_seq'=>10,'source_sha256'=>str_repeat('1',64)];
    $resource=['binding'=>$binding,'credential_sha256'=>str_repeat('2',64),'owner_id'=>11,'owner_auth_sha256'=>str_repeat('3',64)];
    $client=['kind'=>'client','id'=>7,'auth_sha256'=>hash('sha256',str_repeat('4',32))];$owner=['kind'=>'owner','id'=>12,'auth_sha256'=>hash('sha256',str_repeat('5',32))];
    $requestId=str_repeat('6',32);$dispatchId=str_repeat('7',32);
    $request=nrq_event($requestId,$requestId,1,'request',['resource'=>$resource,'desired_nameservers'=>['ns1.new.invalid','ns2.new.invalid'],'confirmed'=>true],$client);
    registered_nameserver_requests_event_document($request);$json=registered_nameserver_requests_json($request);$decoded=json_decode($json,true,16,JSON_THROW_ON_ERROR);
    nrq_check('Canonical original event roundtrip is byte stable',registered_nameserver_requests_json($decoded)===$json);
    nrq_check('Binding restores exact foundation constructor field order',registered_nameserver_requests_binding($decoded['payload']['resource']['binding'])===$binding);
    nrq_check('Full original assignment source survives canonical ordering',registered_nameserver_requests_binding($decoded['payload']['resource']['binding'])['source_refresh_id']===$binding['source_refresh_id']);
    nrq_check('Request actor retains auth digest rather than raw auth token',$decoded['actor']['auth_sha256']===$client['auth_sha256']&&!str_contains($json,str_repeat('4',32)));
    $project=registered_nameserver_requests_project([$decoded]);$r=$project['requests'][$requestId];
    nrq_check('Initial projection has exact documented keys',array_keys($project)===['requests']&&array_keys($r)===['request','state','latest_event','events']);
    nrq_check('Local request is pending with original event and empty later history',$r['state']==='pending'&&$r['request']===$decoded&&$r['latest_event']===$requestId&&$r['events']===[]);
    nrq_check('Empty local graph is explicitly empty',registered_nameserver_requests_project([])===['requests'=>[]]);
    foreach(['version'=>2,'id'=>'bad','subject_id'=>$dispatchId,'client_id'=>8,'kind'=>'fulfilled','recorded_epoch'=>0,'ordinal'=>0,'previous'=>'x','payload'=>null] as $key=>$value){$x=$request;$x[$key]=$value;nrq_refuse('Malformed request refuses '.$key,fn()=>registered_nameserver_requests_event_document($x));}
    foreach(['recorded_epoch','ordinal'] as $key){$x=$request;$x[$key]=2147483648;nrq_refuse('Local signed32 bound refuses '.$key,fn()=>registered_nameserver_requests_event_document($x));}
    $x=$request;$x['extra']=true;nrq_refuse('Unknown top-level fields refuse',fn()=>registered_nameserver_requests_event_document($x));
    foreach(['kind'=>'owner','id'=>8,'auth_sha256'=>str_repeat('4',32)] as $key=>$value){$x=$request;$x['actor'][$key]=$value;nrq_refuse('Wrong original client actor refuses '.$key,fn()=>registered_nameserver_requests_event_document($x));}
    $x=$request;$x['actor']['auth_version']=str_repeat('4',32);nrq_refuse('Raw auth-version actor field refuses',fn()=>registered_nameserver_requests_event_document($x));
    foreach([false,1,'true',null] as $i=>$value){$x=$request;$x['payload']['confirmed']=$value;nrq_refuse('Confirmation must be exact true '.$i,fn()=>registered_nameserver_requests_event_document($x));}
    foreach([[],['ns1.new.invalid','ns1.new.invalid'],['NS1.new.invalid'],['ns1.new.invalid.'],['ns2.new.invalid','ns1.new.invalid'],['https://ns1.new.invalid'],['127.0.0.1'],['localhost'],['ns1.new.invalid:53'],['ns1.new.invalid/path'],['a_b.invalid'],['nested'=>['ns1.new.invalid']],['ns1.new.invalid',12],array_fill(0,65,'ns1.new.invalid')] as $i=>$desired){$x=$request;$x['payload']['desired_nameservers']=$desired;nrq_refuse('Malformed or noncanonical full desired set refuses '.$i,fn()=>registered_nameserver_requests_event_document($x));}
    $x=$request;$x['payload']['old-ns']=['ns1.old.invalid'];nrq_refuse('Client cannot retain a caller-supplied old set',fn()=>registered_nameserver_requests_event_document($x));
    foreach($binding as $key=>$value){$x=$request;unset($x['payload']['resource']['binding'][$key]);nrq_refuse('Every original binding field is required '.$key,fn()=>registered_nameserver_requests_event_document($x));}
    foreach(['domain_id'=>'14701','domain'=>'EXAMPLE.invalid','client_id'=>0,'source_seq'=>0,'profile_revision'=>4294967296,'source_sha256'=>'bad'] as $key=>$value){$x=$request;$x['payload']['resource']['binding'][$key]=$value;nrq_refuse('Invalid typed binding refuses '.$key,fn()=>registered_nameserver_requests_event_document($x));}
    foreach(['credential_sha256'=>'x','owner_auth_sha256'=>str_repeat('0',32),'owner_id'=>0] as $key=>$value){$x=$request;$x['payload']['resource'][$key]=$value;nrq_refuse('Invalid static resource witness refuses '.$key,fn()=>registered_nameserver_requests_event_document($x));}
    // Valid semantic edits are different historical documents; live/pinned authority is separate.
    foreach(['credential_sha256','owner_auth_sha256'] as $key){$x=$request;$x['payload']['resource'][$key]=str_repeat('9',64);registered_nameserver_requests_event_document($x);nrq_check('Valid alternate resource changes original canonical hash '.$key,hash('sha256',registered_nameserver_requests_json($x))!==hash('sha256',$json));}
    $x=$request;$x['actor']['auth_sha256']=str_repeat('9',64);registered_nameserver_requests_event_document($x);nrq_check('Changed client auth changes retained request hash',hash('sha256',registered_nameserver_requests_json($x))!==hash('sha256',$json));
    $dispatch=nrq_event($dispatchId,$requestId,2,'dispatch',['previous_event'=>$requestId,'owner_request_id'=>$dispatchId,'owner_request_sha256'=>str_repeat('8',64)],$owner);
    $done=registered_nameserver_requests_project([$request,$dispatch])['requests'][$requestId];
    nrq_check('Owner admission records dispatched but never succeeded',$done['state']==='dispatched'&&$done['latest_event']===$dispatchId&&$done['events']===[$dispatch]);
    nrq_check('Approving owner remains distinct from original assignment owner',$done['events'][0]['actor']['id']===12&&$done['request']['payload']['resource']['owner_id']===11);
    foreach(['owner_request_id'=>str_repeat('9',32),'owner_request_sha256'=>'bad','previous_event'=>str_repeat('9',32)] as $key=>$value){$x=$dispatch;$x['payload'][$key]=$value;nrq_refuse('Changed dispatch origin refuses '.$key,fn()=>registered_nameserver_requests_project([$request,$x]));}
    $x=$dispatch;$x['actor']=$client;nrq_refuse('Client cannot dispatch',fn()=>registered_nameserver_requests_project([$request,$x]));
    $x=$dispatch;$x['payload']['dispatch_sha256']=str_repeat('9',64);nrq_refuse('Circular extra dispatch identity field refuses',fn()=>registered_nameserver_requests_project([$request,$x]));
    $x=$dispatch;$x['payload']['owner_request_sha256']=str_repeat('9',64);registered_nameserver_requests_event_document($x);nrq_check('Owner approval hash is pinned data rather than self-authenticating authority',registered_nameserver_requests_json($x)!==registered_nameserver_requests_json($dispatch));
    $cancel=nrq_event(str_repeat('a',32),$requestId,2,'cancel',['previous_event'=>$requestId,'note'=>''],$client);
    $decline=nrq_event(str_repeat('b',32),$requestId,2,'decline',['previous_event'=>$requestId,'note'=>'Please confirm the complete replacement set.'],$owner);
    foreach(['cancelled'=>$cancel,'declined'=>$decline] as $state=>$event){$p=registered_nameserver_requests_project([$request,$event]);nrq_check('Terminal local state '.$state,$p['requests'][$requestId]['state']===$state&&$p['requests'][$requestId]['events']===[$event]);}
    foreach([['cancel',$cancel,'note','not empty'],['decline',$decline,'note',''],['decline',$decline,'note',' padded '],['decline',$decline,'note',str_repeat('a',1001)],['decline',$decline,'note',"bad\0note"],['decline',$decline,'note',"\xFF"]] as $i=>$case){$x=$case[1];$x['payload'][$case[2]]=$case[3];nrq_refuse('Invalid decision note refuses '.$i,fn()=>registered_nameserver_requests_project([$request,$x]));}
    $x=$cancel;$x['actor']['id']=8;nrq_refuse('Another client cannot cancel',fn()=>registered_nameserver_requests_project([$request,$x]));
    $x=$decline;$x['client_id']=8;nrq_refuse('Decision cannot change original client',fn()=>registered_nameserver_requests_project([$request,$x]));
    $x=$decline;$x['payload']['note']=str_repeat('x',1000);registered_nameserver_requests_project([$request,$x]);nrq_check('Decision note exact1000-byte boundary is accepted',strlen($x['payload']['note'])===1000);
    nrq_check('Note normalizer preserves printable markup for escaped rendering',registered_nameserver_requests_note(' <request> & review ')==='<request> & review');
    foreach([$cancel,$decline,$dispatch] as $i=>$event){$x=$cancel;$x['id']=str_repeat('d',32);$x['ordinal']=3;$x['recorded_epoch']++;$x['payload']['previous_event']=$event['id'];nrq_refuse('Terminal request cannot transition again '.$i,fn()=>registered_nameserver_requests_project([$request,$event,$x]));}
    $second=$request;$second['id']=str_repeat('c',32);$second['subject_id']=$second['id'];$second['ordinal']=2;$second['recorded_epoch']++;
    nrq_refuse('Second pending request for same registrar resource refuses',fn()=>registered_nameserver_requests_project([$request,$second]));
    $x=$second;$x['payload']['resource']['binding']['link_id']=str_repeat('e',32);$x['payload']['resource']['binding']['link_revision']=3;nrq_refuse('Reassignment cannot bypass same-resource pending history',fn()=>registered_nameserver_requests_project([$request,$x]));
    foreach(['domain_id'=>14702,'profile_id'=>str_repeat('d',32)] as $key=>$value){$x=$second;$x['payload']['resource']['binding'][$key]=$value;nrq_check('Different resource may have independent pending request '.$key,count(registered_nameserver_requests_project([$request,$x])['requests'])===2);}
    $second['ordinal']=3;$second['recorded_epoch']++;nrq_check('Terminal local request permits later separately approved intake',count(registered_nameserver_requests_project([$request,$cancel,$second])['requests'])===2);
    $x=$dispatch;$x['ordinal']=3;nrq_refuse('Missing ordinal refuses',fn()=>registered_nameserver_requests_project([$request,$x]));
    $x=$dispatch;$x['recorded_epoch']=$request['recorded_epoch']-1;nrq_refuse('Backward event time refuses',fn()=>registered_nameserver_requests_project([$request,$x]));
    nrq_refuse('Missing original request refuses',fn()=>registered_nameserver_requests_project([$dispatch]));
    nrq_refuse('Reordered history refuses',fn()=>registered_nameserver_requests_project([$dispatch,$request]));
    nrq_refuse('Associative event list refuses',fn()=>registered_nameserver_requests_project(['request'=>$request]));
    $x=$dispatch;$x['id']=$requestId;$x['payload']['owner_request_id']=$requestId;nrq_refuse('Repeated event identity refuses',fn()=>registered_nameserver_requests_project([$request,$x]));
    $large=$request;$names=[];for($i=0;$i<64;$i++)$names[]=sprintf('ns%02d.',$i).str_repeat('a',63).'.'.str_repeat('b',63).'.'.str_repeat('c',63).'.invalid';sort($names,SORT_STRING);$large['payload']['desired_nameservers']=$names;registered_nameserver_requests_event_document($large);nrq_check('Existing64-host structural bound fits local event cap',strlen(registered_nameserver_requests_json($large))<32768&&count($names)===64);
    $sql=registered_nameserver_requests_schema(str_repeat('p',19).'_');preg_match('/CREATE TABLE `([^`]+)`/',$sql,$table);
    nrq_check('Longest supported prefix fits table and generated FK identifiers',strlen($table[1])<=64&&strlen($table[1].'_ibfk_1')<=64);
    nrq_check('Request table is outside old registered_nameserver observation namespace',!str_starts_with(substr($table[1],20),'registered_nameserver_'));
    nrq_check('Schema retains unique sequence and actual client FK',str_contains($sql,'UNIQUE KEY event_ordinal(ordinal)')&&str_contains($sql,'FOREIGN KEY (client_id)')&&str_contains($sql,'ON DELETE RESTRICT'));
    nrq_refuse('Schema refuses injected prefix',fn()=>registered_nameserver_requests_schema('x`;DROP'));
    nrq_check('Pure document execution loads no database or provider authority',!function_exists('db')&&!function_exists('provider_settings_owner')&&!function_exists('provider_operations_perform'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected '.get_class($e).': '.$e->getMessage(),'passed'=>false];}
$passed=!array_filter($checks,fn($c)=>$c['passed']!==true);echo json_encode(['passed'=>$passed,'count'=>count($checks),'checks'=>$checks,'scope'=>'Actual pure local request documents, canonical production function and historical transition graph; no SQL, live authority, owner dispatch, provider or browser acceptance'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($passed?0:1);
