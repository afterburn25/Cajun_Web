<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
foreach (['StackCpHandoffContext','StackCpHandoffContract','StackCpSensitiveResponse'] as $class) require_once __DIR__.'/../foundation/twentyi-support-handoff/'.$class.'.php';
use CajunWebPro\Foundation\TwentyISupportHandoff\{StackCpHandoffContext,StackCpHandoffContract,StackCpSensitiveResponse};
$checks=[];
function sh_ok(string $name, bool $passed): void { global $checks; $checks[]=['name'=>$name,'passed'=>$passed]; }
function sh_deny(string $name, callable $call, string $class=InvalidArgumentException::class): void {
    try { $call(); sh_ok($name,false); }
    catch (Throwable $e) { sh_ok($name,$e instanceof $class && !str_contains($e->getMessage(),'PRIVATE_MARKER')); }
}
function sh_restore(array $values): StackCpHandoffContext {
    $r=(new ReflectionClass(StackCpHandoffContext::class))->newInstanceWithoutConstructor();
    foreach($values as $k=>$v)(new ReflectionProperty(StackCpHandoffContext::class,$k))->setValue($r,$v);
    return $r;
}
$package=['profile_id'=>str_repeat('1',32),'profile_revision'=>1,'key_fingerprint'=>str_repeat('2',64),'credential_sha256'=>str_repeat('3',64),
    'package_id'=>701,'client_id'=>9,'link_id'=>str_repeat('4',32),'link_sha256'=>str_repeat('5',64),'event_id'=>10,'event_sha256'=>str_repeat('6',64),
    'audit_id'=>11,'audit_sha256'=>str_repeat('7',64),'source_id'=>str_repeat('8',32),'source_seq'=>12,'source_sha256'=>str_repeat('9',64)];
$input=['package'=>$package,'actor_id'=>1,'actor_auth_version'=>str_repeat('a',32),'client_auth_version'=>str_repeat('b',32),
    'auth_credential_id'=>str_repeat('c',32),'auth_revision'=>2,'auth_credential_sha256'=>str_repeat('d',64),
    'stack_user_identity'=>'stack-user:97','membership_source_id'=>str_repeat('e',32),'membership_source_seq'=>13,'membership_source_sha256'=>str_repeat('f',64),
    'grants_source_id'=>str_repeat('0',32),'grants_source_seq'=>14,'grants_source_sha256'=>str_repeat('1',64),'observed_grants'=>['package:701'],
    'observed_at'=>1760000000,'permission_id'=>str_repeat('2',32),'permission_revision'=>3,'permission_sha256'=>str_repeat('3',64)];
$context=new StackCpHandoffContext($input);
$credentials=['general-api-key'=>'GENERAL_PRIVATE_MARKER_KEY','auth-client-key'=>'AUTH_PRIVATE_MARKER_KEY'];
$access='TOKEN_PRIVATE_MARKER_VALUE';$session='SESSION_PRIVATE_MARKER_VALUE';
$tokenFields=['access_token'=>$access,'token_type'=>'bearer','expires_in'=>60,'scope'=>'interactive'];
function sh_token(?array $fields=null,int $status=200,?array $roles=null): StackCpSensitiveResponse {
    global $context,$tokenFields,$credentials;
    return StackCpSensitiveResponse::parseToken($context,$status,json_encode($fields??$tokenFields,JSON_THROW_ON_ERROR),$roles??$credentials);
}
function sh_bad(string $name,string $body,string $reason,string $phase='token',int $status=200): void {
    global $context,$credentials,$access;
    $r=$phase==='token'?StackCpSensitiveResponse::parseToken($context,$status,$body,$credentials):StackCpSensitiveResponse::parseSession($context,$status,$body,$credentials+['access-token'=>$access]);
    sh_ok($name,$r->state==='unavailable'&&$r->reason===$reason&&!property_exists($r,'reportedExpiresIn')&&!str_contains(json_encode($r),'PRIVATE_MARKER'));
    sh_deny($name.' refuses consumption',fn()=>$r->consume($phase,fn()=>null),LogicException::class);
}
set_error_handler(static function(int $level,string $message):never{throw new ErrorException($message,0,$level);});
try {
    sh_ok('Context names whole Stack user and never remote package restriction',$context->data['operation']==='stackcp.support-handoff'&&$context->data['actor_kind']==='owner'&&$context->data['provider_session_scope']==='whole-stack-user'&&$context->data['remote_scope_restricted']===false);
    sh_ok('Canonical local review digest agrees',hash('sha256',$context->json)===$context->sha256&&json_decode($context->json,true)===$context->data);
    $reordered=array_reverse($input,true);$reordered['package']=array_reverse($package,true);
    sh_ok('Input object order does not change review document',(new StackCpHandoffContext($reordered))->json===$context->json);
    foreach(['actor_id','auth_revision','membership_source_seq','grants_source_seq','permission_revision'] as $key) {
        foreach([0,-1,'1',true] as $i=>$invalid){$bad=$input;$bad[$key]=$invalid;sh_deny('Invalid positive local field '.$key.' '.$i,fn()=>new StackCpHandoffContext($bad));}
        $changed=$input;$changed[$key]++;sh_ok('Changed local authority changes digest '.$key,(new StackCpHandoffContext($changed))->sha256!==$context->sha256);
    }
    foreach(['actor_auth_version','client_auth_version','auth_credential_id','membership_source_id','grants_source_id','permission_id','auth_credential_sha256','membership_source_sha256','grants_source_sha256','permission_sha256'] as $key){
        $bad=$input;$bad[$key]='PRIVATE_MARKER';sh_deny('Malformed identity or witness '.$key,fn()=>new StackCpHandoffContext($bad));
        $changed=$input;$changed[$key]=str_repeat('9',strlen($changed[$key]));sh_ok('Changed identity or witness changes digest '.$key,(new StackCpHandoffContext($changed))->sha256!==$context->sha256);
    }
    foreach(['client_id','profile_revision','event_id','audit_id','source_seq'] as $key){$changed=$input;$changed['package'][$key]++;sh_ok('Changed package authority changes digest '.$key,(new StackCpHandoffContext($changed))->sha256!==$context->sha256);}
    foreach(['profile_id','key_fingerprint','credential_sha256','link_id','link_sha256','event_sha256','audit_sha256','source_id','source_sha256'] as $key){$changed=$input;$changed['package'][$key]=str_repeat('a',strlen($changed['package'][$key]));sh_ok('Changed package witness changes digest '.$key,(new StackCpHandoffContext($changed))->sha256!==$context->sha256);}
    foreach([[],['package:702'],['package:701','package:702'],['package:701','domain:701'],['vps:701'],['reseller:*'],['package:701','package:701'],['package:0701'],['Package:701'],['0'=>'package:701','2'=>'package:701']] as $i=>$grants){$bad=$input;$bad['observed_grants']=$grants;sh_deny('Whole grant snapshot admission refusal '.$i,fn()=>new StackCpHandoffContext($bad));}
    foreach(['','97','stack-user:0','stack-user:097','stack-user:1/../../admin','stack-user:1<interactive>','stack-user:9999999999999999999','Stack-user:97'] as $i=>$id){$bad=$input;$bad['stack_user_identity']=$id;sh_deny('Local Stack user identity subset '.$i,fn()=>new StackCpHandoffContext($bad));}
    $bad=$input;$bad['role']='support';sh_deny('Unknown permission claims cannot enter owner document',fn()=>new StackCpHandoffContext($bad));
    $bad=$input;unset($bad['permission_sha256']);sh_deny('Missing permission witness refused',fn()=>new StackCpHandoffContext($bad));
    $bad=$input;$bad['package']['unexpected']='PRIVATE_MARKER';sh_deny('Unknown package field refused',fn()=>new StackCpHandoffContext($bad));
    foreach([-1,'1760000000',true] as $i=>$time){$bad=$input;$bad['observed_at']=$time;sh_deny('Observed time must be local integer '.$i,fn()=>new StackCpHandoffContext($bad));}
    $refPackage=701;$refActor=1;$referenced=$input;$referenced['package']['package_id']=&$refPackage;$referenced['actor_id']=&$refActor;$detached=new StackCpHandoffContext($referenced);$refPackage=999;$refActor=8;
    sh_ok('Readonly context detaches nested scalar references',$detached->data['review']['package']['package_id']===701&&$detached->data['review']['actor_id']===1&&$detached->json===$context->json);
    foreach(['json','sha256','scope'] as $field){$props=get_object_vars($context);$props[$field]='PRIVATE_MARKER';$restored=sh_restore($props);sh_deny('Restored derived field refused '.$field,fn()=>StackCpHandoffContext::validated($restored));}
    $props=get_object_vars($context);$props['data']['actor_kind']='staff';$restored=sh_restore($props);sh_deny('Restored owner-kind bypass refused',fn()=>StackCpHandoffContext::validated($restored));
    $ref=1;$props=get_object_vars($context);$props['data']['review']['actor_id']=&$ref;$restored=sh_restore($props);$copy=StackCpHandoffContext::validated($restored);$ref=9;
    sh_ok('Validation detaches restored scalar references',$copy->data['review']['actor_id']===1&&$copy->sha256===$context->sha256);
    sh_deny('Changed restored reference fails later revalidation',fn()=>StackCpHandoffContext::validated($restored));
    $expected=[
        'membership'=>['GET','https://api.20i.com','/package/701/stackUserList','general-api-key',false],
        'grants'=>['GET','https://api.20i.com','/reseller/*/stackUser/97/grants','general-api-key',false],
        'token'=>['POST','https://auth-api.20i.com:3000','/login/authenticate','auth-client-key',true],
        'brand'=>['GET','https://api.20i.com','/reseller/*/customisations','general-api-key',false],
        'session'=>['POST','https://www.stackcp.com','/login/implicitBranded','ephemeral-access-token',true],
        'browser'=>['GET','https://www.stackcp.com','/login/sso','ephemeral-session-query',true],
    ];
    foreach($expected as $phase=>$fields){$d=StackCpHandoffContract::describe($context,$phase);sh_ok('Exact SDK phase description '.$phase,[$d['method'],$d['origin'],$d['path'],$d['credential_role'],$d['access_mutation']]===$fields&&$d['executable']===false&&$d['remote_scope_restricted']===false);}
    sh_ok('Token description does not invent package-scoped OAuth',StackCpHandoffContract::describe($context,'token')['documented_fields']===['grant_type'=>'client_credentials','scope'=>'stack-user:97']);
    sh_ok('Browser access does not claim remote expiry or revoke',StackCpHandoffContract::describe($context,'browser')['remote_expiry_verified']===false&&StackCpHandoffContract::describe($context,'browser')['remote_revoke_verified']===false);
    sh_deny('No arbitrary endpoint description',fn()=>StackCpHandoffContract::describe($context,'https://evil.invalid'));
    sh_deny('Descriptors revalidate restored context',fn()=>StackCpHandoffContract::describe($restored,'token'));
    sh_ok('Default HTTPS destination exactly admitted',StackCpHandoffContract::defaultDestination('https://www.stackcp.com','/login/sso'));
    foreach(['http://www.stackcp.com','https://stackcp.com','https://www.stackcp.com:443','https://www.stackcp.com.evil.invalid','https://evil@www.stackcp.com','https://WWW.stackcp.com','https://www.stackcp.com/','//www.stackcp.com','https://www.stackcp.com#fragment'] as $i=>$origin)sh_ok('Destination origin ambiguity refused '.$i,!StackCpHandoffContract::defaultDestination($origin,'/login/sso'));
    foreach(['/login/sso?PHPSESSID=PRIVATE_MARKER','/login/sso#f','/login//sso','/login/%73so','/login/sso/','//evil.invalid','/login/../sso'] as $i=>$path)sh_ok('Destination path ambiguity refused '.$i,!StackCpHandoffContract::defaultDestination('https://www.stackcp.com',$path));
    $r=sh_token();sh_ok('Exact token field subset accepted without remote login claim',$r->state==='accepted_subset'&&$r->phase==='token'&&!property_exists($r,'reportedExpiresIn')&&$r->contextSha256===$context->sha256);
    foreach(['json'=>json_encode($r),'dump'=>(function()use($r){ob_start();var_dump($r);return ob_get_clean();})(),'print'=>print_r($r,true)] as $label=>$output)sh_ok('Normal '.$label.' output excludes bearer data',!str_contains($output,'PRIVATE_MARKER'));
    sh_deny('Serialization is prohibited',fn()=>serialize($r),LogicException::class);
    sh_deny('Restoration is prohibited',fn()=>$r->__unserialize(['opaque'=>'PRIVATE_MARKER']),LogicException::class);
    sh_deny('Cloning is prohibited',fn()=>clone $r,Error::class);
    sh_deny('Dynamic fields are prohibited',function()use($r){$r->secret='PRIVATE_MARKER';},LogicException::class);
    $calls=0;sh_deny('Wrong consumption phase refuses without calling consumer',fn()=>$r->consume('session',function()use(&$calls){$calls++;}),LogicException::class);sh_ok('Wrong phase preserves unused token',$calls===0&&$r->jsonSerialize()['consumed']===false);
    $returned=$r->consume('token',function($value)use(&$calls,$access){$calls++;return hash_equals($value,$access);});sh_ok('Explicit consumer receives exact bearer once',$returned===true&&$calls===1&&$r->jsonSerialize()['consumed']===true);
    sh_deny('Repeated consumption cannot replay bearer',fn()=>$r->consume('token',function()use(&$calls){$calls++;}),LogicException::class);sh_ok('Replay does not invoke consumer',$calls===1);
    $throws=sh_token();sh_deny('Consumer exception remains caller responsibility',fn()=>$throws->consume('token',fn()=>throw new RuntimeException('Synthetic refusal')),RuntimeException::class);sh_deny('Consumer exception cannot restore bearer',fn()=>$throws->consume('token',fn()=>null),LogicException::class);
    $sessionResponse=StackCpSensitiveResponse::parseSession($context,200,json_encode(['session_id'=>$session]),$credentials+['access-token'=>$access]);sh_ok('Session subset accepted without asserted lifetime',$sessionResponse->state==='accepted_subset'&&!property_exists($sessionResponse,'reportedExpiresIn'));sh_ok('Session exact ephemeral consumption',$sessionResponse->consume('session',fn($s)=>$s===$session));
    $fields=$tokenFields;$fields['refresh_token']='REFRESH_PRIVATE_MARKER_DISCARDED';$optional=sh_token($fields);sh_ok('Optional refresh token never appears in public response',$optional->state==='accepted_subset'&&!str_contains(json_encode($optional),'PRIVATE_MARKER'));
    $fields['refresh_token']=null;sh_ok('SDK nullable refresh token accepted',sh_token($fields)->state==='accepted_subset');
    foreach([1,86400] as $n){$fields=$tokenFields;$fields['expires_in']=$n;sh_ok('Local expiry-field bound accepted '.$n,sh_token($fields)->state==='accepted_subset');}
    foreach(['access_token'=>['',null,1,false,"bad\nvalue",str_repeat('a',4097)],'token_type'=>['Bearer','other',null],
        'expires_in'=>[null,0,-1,86401,'60',60.5,true],'scope'=>['manageWeb','interactive manageWeb',' interactive','interactive ',['interactive'],null],
        'refresh_token'=>[false,1,[],"bad\0value"]] as $field=>$values)foreach($values as $i=>$value){$bad=$tokenFields;$bad[$field]=$value;sh_bad('Unsupported token field '.$field.' '.$i,json_encode($bad),is_array($value)?'shape_unsupported':'field_unsupported');}
    foreach(array_keys($tokenFields) as $field){$bad=$tokenFields;unset($bad[$field]);sh_bad('Missing token field '.$field,json_encode($bad),'field_unsupported');}
    foreach(['null','[]','false','"url"','{"result":{"session_id":"x"}}','{"session_id":[]}'] as $i=>$body)sh_bad('Unsupported session envelope '.$i,$body,'shape_unsupported','session');
    foreach(['null','false','1','""','"bad\\nvalue"'] as $i=>$value)sh_bad('Unsupported session scalar '.$i,'{"session_id":'.$value.'}','field_unsupported','session');
    sh_bad('Unknown token fields refuse rather than infer permissions',json_encode($tokenFields+['admin'=>true]),'shape_unsupported');
    foreach(['','{','{"session_id":"x",}','{"session_id":"bad\\q"}',"{\"session_id\":\"bad\xff\"}",'{} trailing','{"session_id":"\\uD800"}'] as $i=>$body)sh_bad('Malformed response has finite reason '.$i,$body,'malformed_body','session');
    foreach(['{"session_id":"a","session_id":"b"}','{"session_id":"a","\\u0073ession_id":"b"}'] as $i=>$body)sh_bad('Decoded duplicate session key refused '.$i,$body,'duplicate_field','session');
    sh_bad('Duplicate token field refused',substr(json_encode($tokenFields),0,-1).',"access_token":"replacement"}','duplicate_field');
    sh_bad('Response byte cap enforced',str_repeat(' ',16385),'body_limit');
    $many=[];for($i=0;$i<9;$i++)$many['f'.$i]='x';sh_bad('Response field cap enforced',json_encode($many),'field_limit');
    foreach($credentials as $role=>$secret)foreach([$secret,base64_encode($secret)] as $i=>$echo){
        $bad=$tokenFields;$bad['access_token']=$echo;sh_bad('Owned credential echo refused '.$role.' '.$i,json_encode($bad),'credential_echo');
        $bad=$tokenFields;$bad['refresh_token']=$echo;sh_bad('Discarded refresh echo refused '.$role.' '.$i,json_encode($bad),'credential_echo');
        sh_bad('Unknown decoded field key echo refused '.$role.' '.$i,json_encode($tokenFields+[$echo=>'x']),'credential_echo');
    }
    $escaped=implode('',array_map(fn($c)=>sprintf('\\u%04x',ord($c)),str_split($credentials['auth-client-key'])));
    sh_bad('Escaped credential echo refused after decoding',str_replace('"'.$access.'"','"'.$escaped.'"',json_encode($tokenFields)),'credential_echo');
    foreach([$access,base64_encode($access)] as $i=>$echo)sh_bad('Session cannot echo its owned access token '.$i,json_encode(['session_id'=>$echo]),'credential_echo','session');
    foreach(['general-api-key','auth-client-key'] as $role) {
        $roles=$credentials;$roles[$role]='60';$numeric=sh_token(null,200,$roles);
        sh_ok('Decoded numeric expiry echo refused '.$role,$numeric->state==='unavailable'&&$numeric->reason==='credential_echo'&&!property_exists($numeric,'reportedExpiresIn'));
    }
    foreach(['true'=>true,'false'=>false,'null'=>null] as $secret=>$scalar) {
        $roles=$credentials;$roles['auth-client-key']=$secret;$fields=$tokenFields;$fields['refresh_token']=$scalar;$echo=sh_token($fields,200,$roles);
        sh_ok('Discarded decoded scalar echo refused '.$secret,$echo->state==='unavailable'&&$echo->reason==='credential_echo'&&!property_exists($echo,'reportedExpiresIn'));
    }
    foreach (['access_token','refresh_token'] as $field) {
        $returned=$tokenFields;$returned[$field]='60';$returned['expires_in']=60;
        $safe=sh_token($returned);
        sh_ok('Returned numeric bearer cannot escape through expiry '.$field,$safe->state==='accepted_subset'&&!property_exists($safe,'reportedExpiresIn')&&!array_key_exists('reported_expires_in',$safe->jsonSerialize()));
        $body=str_replace('"60"','"\\u0036\\u0030"',json_encode($returned));
        $safe=StackCpSensitiveResponse::parseToken($context,200,$body,$credentials);
        sh_ok('Escaped returned numeric bearer has no expiry projection '.$field,$safe->state==='accepted_subset'&&!array_key_exists('reported_expires_in',$safe->jsonSerialize()));
        $returned[$field]="\u{3374}";$encoded=base64_encode($returned[$field]);$returned['expires_in']=(int)$encoded;$safe=sh_token($returned);
        sh_ok('Returned Base64 bearer cannot escape through expiry '.$field,preg_match('/\A[0-9]+\z/',$encoded)===1&&$safe->state==='accepted_subset'&&!array_key_exists('reported_expires_in',$safe->jsonSerialize()));
    }
    sh_ok('Safe response metadata excludes every provider response field',array_keys(sh_token()->jsonSerialize())===['phase','state','reason','context_sha256','consumed']);
    foreach([[],['auth-client-key'=>'AUTH_PRIVATE_MARKER_KEY'],['general-api-key'=>'GENERAL_PRIVATE_MARKER_KEY','auth-client-key'=>''],['general-api-key'=>['PRIVATE_MARKER'],'auth-client-key'=>'x']] as $i=>$roles)sh_deny('Exact owned credential roles required '.$i,fn()=>sh_token(null,200,$roles));
    foreach([401=>'authentication',403=>'authentication',429=>'rate_limited',500=>'upstream_unavailable',503=>'upstream_unavailable',201=>'http_status',204=>'http_status',302=>'http_status',404=>'http_status'] as $status=>$reason)sh_bad('HTTP status does not expose provider body '.$status,'PRIVATE_MARKER raw error',$reason,'token',$status);
    sh_deny('Response parsing revalidates restored binding',fn()=>StackCpSensitiveResponse::parseToken($restored,200,json_encode($tokenFields),$credentials));
    sh_ok('No bearer URL is constructed or returned by response projection',!str_contains(json_encode($optional),'PHPSESSID')&&!str_contains(json_encode($optional),'https://'));
} catch (Throwable $e) { $checks[]=['name'=>'Unexpected contract fixture failure','passed'=>false,'class'=>get_class($e),'message'=>$e->getMessage()]; }
restore_error_handler();
$report=['checks'=>$checks,'passed'=>count(array_filter($checks,fn($c)=>!$c['passed']))===0,'scope'=>'Pure synthetic contract only; no network, database, current authority or provider acceptance'];
$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if(isset($argv[1]))file_put_contents($argv[1],$json);else echo $json;
exit($report['passed']?0:1);
