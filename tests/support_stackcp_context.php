<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require_once __DIR__.'/../foundation/twentyi-support-handoff/StackCpLaunchSender.php';
use CajunWebPro\Foundation\TwentyISupportHandoff\{StackCpSupportHandoffContext,StackCpHandoffContext,StackCpSensitiveResponse,StackCpLaunchSender,StackCpLaunchTransport,StackCpLaunchDelivery,StackCpLaunchFailure};
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse};

$checks=[];$completed=false;
set_error_handler(static function(int $level,string $message):never {throw new ErrorException('PHP diagnostic',0,$level);});
function sc_ok(string $name,bool $passed):void {
    global $checks;
    if(in_array($name,array_column($checks,'name'),true))throw new LogicException('Duplicate check name.');
    $checks[]=['name'=>$name,'passed'=>$passed];
    if(!$passed)throw new RuntimeException('Named support context check failed.');
}
function sc_refuse(string $name,callable $call,string $class=InvalidArgumentException::class):Throwable {
    try{$call();}catch(Throwable $error){sc_ok($name,$error instanceof $class&&!str_contains($error->getMessage(),'PRIVATE_'));return $error;}
    sc_ok($name,false);throw new LogicException('Unreachable.');
}
function sc_input():array {
    return ['package'=>['profile_id'=>str_repeat('1',32),'profile_revision'=>1,'key_fingerprint'=>str_repeat('2',64),'credential_sha256'=>str_repeat('3',64),
        'package_id'=>701,'client_id'=>9,'link_id'=>str_repeat('4',32),'link_sha256'=>str_repeat('5',64),'event_id'=>10,'event_sha256'=>str_repeat('6',64),
        'audit_id'=>11,'audit_sha256'=>str_repeat('7',64),'source_id'=>str_repeat('8',32),'source_seq'=>12,'source_sha256'=>str_repeat('9',64)],
        'staff_id'=>4,'staff_auth_version'=>str_repeat('6',32),'support_id'=>str_repeat('7',32),'support_binding_sha256'=>str_repeat('8',64),'support_expires_epoch'=>1760000900,
        'client_id'=>9,'client_auth_version'=>str_repeat('a',32),'delegation_id'=>str_repeat('b',32),'delegation_revision'=>3,'delegation_sha256'=>str_repeat('c',64),
        'delegation_owner_id'=>1,'delegation_owner_auth_version'=>str_repeat('d',32),'auth_credential_id'=>str_repeat('e',32),'auth_revision'=>2,'auth_credential_sha256'=>str_repeat('f',64),
        'stack_user_identity'=>'stack-user:97','membership_source_id'=>str_repeat('0',32),'membership_source_seq'=>15,'membership_source_sha256'=>str_repeat('1',64),
        'grants_source_id'=>str_repeat('2',32),'grants_source_seq'=>17,'grants_source_sha256'=>str_repeat('3',64),'observed_grants'=>['package:701'],'observed_at'=>1760000000,
        'permission_id'=>str_repeat('4',32),'permission_revision'=>1,'permission_sha256'=>str_repeat('5',64)];
}
function sc_owner(array $client):StackCpHandoffContext {
    $owner=['package'=>$client['package'],'actor_id'=>$client['delegation_owner_id'],'actor_auth_version'=>$client['delegation_owner_auth_version'],'client_auth_version'=>$client['client_auth_version']];
    foreach(['auth_credential_id','auth_revision','auth_credential_sha256','stack_user_identity','membership_source_id','membership_source_seq','membership_source_sha256',
        'grants_source_id','grants_source_seq','grants_source_sha256','observed_grants','observed_at','permission_id','permission_revision','permission_sha256'] as $key)$owner[$key]=$client[$key];
    return new StackCpHandoffContext($owner);
}
function sc_restore(array $properties):StackCpSupportHandoffContext {
    $object=(new ReflectionClass(StackCpSupportHandoffContext::class))->newInstanceWithoutConstructor();
    foreach($properties as $key=>$value)(new ReflectionProperty(StackCpSupportHandoffContext::class,$key))->setValue($object,$value);
    return $object;
}
function sc_session(StackCpHandoffContext|StackCpSupportHandoffContext $context):StackCpSensitiveResponse {
    return StackCpSensitiveResponse::parseSession($context,200,'{"session_id":"PRIVATE_SESSION_VALUE"}',
        ['general-api-key'=>'PRIVATE_GENERAL_KEY','auth-client-key'=>'PRIVATE_AUTH_KEY','access-token'=>'PRIVATE_TOKEN_VALUE']);
}
/** Real sender/parser/delivery and wire validation, with closed in-memory HTTP and DNS doubles. */
function sc_send(StackCpHandoffContext|StackCpSupportHandoffContext $context,array $options,array &$events,array &$requests):StackCpLaunchDelivery {
    $valid=true;
    $authorize=static function()use(&$valid,&$events):void {$events[]='authorize';if(!$valid)throw new RuntimeException('PRIVATE_AUTHORITY_DETAIL');};
    return StackCpLaunchSender::launch($context,
        static function(string $role)use(&$events):string {$events[]='key:'.$role;return match($role){'general-api-key'=>'PRIVATE_GENERAL_KEY','auth-client-key'=>'PRIVATE_AUTH_KEY',default=>throw new LogicException('Unexpected key role.')};},
        $authorize,
        static function(string $phase)use(&$events,&$valid,$options):void {$events[]='before:'.$phase;if(($options['deny_before']??'')===$phase)$valid=false;if(($options['fault_before']??'')===$phase)throw new PDOException('Synthetic persistence refusal.');},
        static function(string $phase)use(&$events):void {$events[]='after:'.$phase;},
        static function(string $host)use(&$events):array {$events[]='dns:'.$host;return [['type'=>'A','ip'=>'8.8.8.8']];},
        static function(string $phase,string $address,callable $guard)use(&$events,&$requests,&$valid,$options):HttpTransport {
            $events[]='factory:'.$phase;
            return new class($phase,$address,$guard,$events,$requests,$valid,$options) implements HttpTransport {
                private array $events;private array $requests;private bool $valid;
                public function __construct(private string $phase,private string $address,private mixed $guard,array &$events,array &$requests,bool &$valid,private array $options) {$this->events=&$events;$this->requests=&$requests;$this->valid=&$valid;}
                public function send(#[SensitiveParameter] TransportRequest $request):TransportResponse {
                    ($this->guard)();
                    (new ReflectionMethod(StackCpLaunchTransport::class,'validate'))->invoke(new StackCpLaunchTransport($this->phase,$this->address,$this->guard),$request);
                    $this->events[]='send:'.$this->phase;
                    $this->requests[]=['method'=>$request->method,'url'=>$request->url,'body'=>$request->body,'authorization'=>$request->headers['Authorization']];
                    if(($this->options['deny_after_send']??'')===$this->phase)$this->valid=false;
                    $body=match($this->phase){'brand'=>($this->options['custom_brand']??false)?'{"brandDomain":"other.invalid"}':'{"brandDomain":null}',
                        'token'=>'{"access_token":"PRIVATE_TOKEN_VALUE","token_type":"bearer","expires_in":60,"scope":"interactive"}',
                        'session'=>'{"session_id":"PRIVATE_SESSION_VALUE"}'};
                    return new TransportResponse(200,[],$body);
                }
            };
        });
}
try {
    $input=sc_input();$context=new StackCpSupportHandoffContext($input);$owner=sc_owner($input);
    sc_ok('Support principal is distinct from the delegating owner and bound package client',$context->data['actor_kind']==='support'&&$context->data['review']['client_id']===9&&$context->data['review']['delegation_owner_id']===1&&$context->data['review']['package']['client_id']===9&&!isset($context->data['review']['actor_id']));
    sc_ok('Support operation does not claim a package-restricted provider session',$context->data['operation']==='stackcp.staff-support-handoff'&&$context->data['provider_session_scope']==='whole-stack-user'&&$context->data['remote_scope_restricted']===false);
    sc_ok('Owner context remains explicitly owner-scoped and distinct',$owner->data['actor_kind']==='owner'&&$owner->data['operation']==='stackcp.support-handoff'&&$owner->sha256!==$context->sha256&&$owner->scope!==$context->scope);
    sc_ok('Support canonical digest and detached bytes agree',hash('sha256',$context->json)===$context->sha256&&json_decode($context->json,true)===$context->data&&StackCpSupportHandoffContext::validated($context)->json===$context->json);
    $reverse=array_reverse($input,true);$reverse['package']=array_reverse($reverse['package'],true);
    sc_ok('Input field order does not change the signed support review',(new StackCpSupportHandoffContext($reverse))->json===$context->json);
    foreach($input as $key=>$value){$bad=$input;unset($bad[$key]);sc_refuse('Missing support witness '.$key,fn()=>new StackCpSupportHandoffContext($bad));}
    foreach(['actor_id','actor_auth_version','observing_owner_id','confirmed','remote_scope_restricted','scope','return_url'] as $key){$bad=$input;$bad[$key]='PRIVATE_UNTRUSTED';sc_refuse('Unsupported authority or destination field '.$key,fn()=>new StackCpSupportHandoffContext($bad));}
    foreach(['staff_id','support_expires_epoch','client_id','delegation_revision','delegation_owner_id','auth_revision','membership_source_seq','grants_source_seq','permission_revision'] as $key){
        foreach([0,-1,'1',true,null] as $i=>$value){$bad=$input;$bad[$key]=$value;sc_refuse('Invalid positive support witness '.$key.' '.$i,fn()=>new StackCpSupportHandoffContext($bad));}
        $changed=$input;$changed[$key]++;if($key==='client_id')$changed['package']['client_id']=$changed[$key];
        sc_ok('Changed principal or sequence changes digest '.$key,(new StackCpSupportHandoffContext($changed))->sha256!==$context->sha256);
    }
    foreach(['staff_auth_version','support_id','support_binding_sha256','client_auth_version','delegation_id','delegation_owner_auth_version','auth_credential_id','membership_source_id','grants_source_id','permission_id',
        'delegation_sha256','auth_credential_sha256','membership_source_sha256','grants_source_sha256','permission_sha256'] as $key){
        $bad=$input;$bad[$key]='PRIVATE_INVALID';sc_refuse('Malformed identity or proof '.$key,fn()=>new StackCpSupportHandoffContext($bad));
        $changed=$input;$changed[$key]=str_repeat($changed[$key][0]==='8'?'9':'8',strlen($changed[$key]));sc_ok('Changed retained support witness changes digest '.$key,(new StackCpSupportHandoffContext($changed))->sha256!==$context->sha256);
    }
    foreach($input['package'] as $key=>$value){$bad=$input;unset($bad['package'][$key]);sc_refuse('Missing package witness '.$key,fn()=>new StackCpSupportHandoffContext($bad));
        $changed=$input;$changed['package'][$key]=is_int($value)?$value+1:str_repeat('b',strlen($value));
        if($key==='client_id')$changed['client_id']=$changed['package'][$key];if($key==='package_id')$changed['observed_grants']=['package:'.$changed['package'][$key]];
        sc_ok('Changed package witness changes digest '.$key,(new StackCpSupportHandoffContext($changed))->sha256!==$context->sha256);
    }
    $bad=$input;$bad['client_id']=10;sc_refuse('Different primary support cannot enter package context',fn()=>new StackCpSupportHandoffContext($bad));
    $bad=$input;$bad['package']['extra']='PRIVATE_FIELD';sc_refuse('Unknown package property refused',fn()=>new StackCpSupportHandoffContext($bad));
    foreach([[],['package:702'],['package:701','package:702'],['package:701','domain:1'],['server:1'],['package:701','package:701'],['Package:701'],['package:0701'],[1=>'package:701']] as $i=>$values){$bad=$input;$bad['observed_grants']=$values;sc_refuse('Fresh grant subset refuses ambiguous or additional scope '.$i,fn()=>new StackCpSupportHandoffContext($bad));}
    foreach(['','97','stack-user:0','stack-user:097','stack-user:1<interactive>','stack-user:9999999999999999999','Stack-user:97'] as $i=>$value){$bad=$input;$bad['stack_user_identity']=$value;sc_refuse('Stack user identity subset '.$i,fn()=>new StackCpSupportHandoffContext($bad));}
    foreach([-1,'1760000000',true] as $i=>$value){$bad=$input;$bad['observed_at']=$value;sc_refuse('Observed epoch type '.$i,fn()=>new StackCpSupportHandoffContext($bad));}
    $clientRef=9;$ownerRef=1;$reference=$input;$reference['client_id']=&$clientRef;$reference['package']['client_id']=&$clientRef;$reference['delegation_owner_id']=&$ownerRef;
    $detached=new StackCpSupportHandoffContext($reference);$clientRef=10;$ownerRef=2;
    sc_ok('Support and delegation nested references are detached',$detached->json===$context->json&&$detached->data['review']['client_id']===9&&$detached->data['review']['delegation_owner_id']===1);
    foreach(['json','sha256','scope'] as $field){$properties=get_object_vars($context);$properties[$field]='PRIVATE_DERIVED';$restored=sc_restore($properties);sc_refuse('Restored support derived value rejected '.$field,fn()=>StackCpSupportHandoffContext::validated($restored));}
    foreach(['actor_kind'=>'owner','operation'=>'stackcp.support-handoff','remote_scope_restricted'=>true] as $key=>$value){$properties=get_object_vars($context);$properties['data'][$key]=$value;$restored=sc_restore($properties);sc_refuse('Restored actor or scope cannot bypass support validation '.$key,fn()=>StackCpSupportHandoffContext::validated($restored));}
    sc_refuse('Owner validator does not accept a support context',fn()=>StackCpHandoffContext::validated($context),TypeError::class);
    sc_refuse('Support validator does not accept an owner context',fn()=>StackCpSupportHandoffContext::validated($owner),TypeError::class);
    sc_refuse('Owner payload cannot be relabeled as client',fn()=>new StackCpSupportHandoffContext($owner->data['review']));
    sc_refuse('Support payload cannot be relabeled as owner',fn()=>new StackCpHandoffContext($input));
    $credentials=['general-api-key'=>'PRIVATE_GENERAL_KEY','auth-client-key'=>'PRIVATE_AUTH_KEY'];
    $token=StackCpSensitiveResponse::parseToken($context,200,'{"access_token":"PRIVATE_TOKEN_VALUE","token_type":"bearer","expires_in":60,"scope":"interactive"}',$credentials);
    sc_ok('Support token uses unchanged sensitive parser and support digest',$token->state==='accepted_subset'&&$token->contextSha256===$context->sha256&&!str_contains(json_encode($token),'PRIVATE_'));
    $session=sc_session($context);sc_refuse('Support session cannot be delivered under owner context',fn()=>StackCpLaunchDelivery::fromSession($owner,$session));
    $changed=$input;$changed['delegation_revision']++;$other=new StackCpSupportHandoffContext($changed);
    sc_refuse('Support session cannot be delivered under changed delegation',fn()=>StackCpLaunchDelivery::fromSession($other,$session));
    $delivery=StackCpLaunchDelivery::fromSession($context,$session);sc_refuse('Support delivery cannot be serialized',fn()=>serialize($delivery),LogicException::class);
    sc_refuse('Support delivery cannot be JSON encoded',fn()=>json_encode($delivery),LogicException::class);
    sc_refuse('Support delivery cannot be cloned',fn()=>clone $delivery,Error::class);
    sc_ok('Support delivery debug excludes session and destination',!str_contains(print_r($delivery,true),'PRIVATE_')&&!str_contains(print_r($delivery,true),'https://'));
    $calls=0;sc_ok('Support delivery constructs only exact fixed HTTPS destination',$delivery->consume(function($url)use(&$calls):bool{$calls++;return $url==='https://www.stackcp.com/login/sso?PHPSESSID=PRIVATE_SESSION_VALUE';}));
    sc_refuse('Support delivery cannot be consumed twice',fn()=>$delivery->consume(function()use(&$calls){$calls++;}),LogicException::class);sc_ok('Second support delivery never invokes consumer',$calls===1);
    $delivery=StackCpLaunchDelivery::fromSession($context,sc_session($context));sc_refuse('Support callback failure consumes delivery',fn()=>$delivery->consume(fn()=>throw new RuntimeException('Synthetic callback failure.')),RuntimeException::class);
    sc_refuse('Support callback failure cannot replay',fn()=>$delivery->consume(fn()=>null),LogicException::class);
    $bad=StackCpSensitiveResponse::parseToken($context,200,'{"access_token":"PRIVATE_AUTH_KEY","token_type":"bearer","expires_in":60,"scope":"interactive"}',$credentials);
    sc_ok('Support path retains credential echo refusal',$bad->state==='unavailable'&&$bad->reason==='credential_echo'&&!str_contains(json_encode($bad),'PRIVATE_'));
    $bad=StackCpSensitiveResponse::parseSession($context,200,'{"session_id":"a","session_id":"b"}',$credentials+['access-token'=>'PRIVATE_TOKEN_VALUE']);
    sc_ok('Support path retains duplicate response refusal',$bad->state==='unavailable'&&$bad->reason==='duplicate_field');
    $events=[];$requests=[];$sent=sc_send($context,[],$events,$requests);
    sc_ok('Support sender preserves three exact SDK endpoints',array_column($requests,'url')===['https://api.20i.com/reseller/*/customisations','https://auth-api.20i.com:3000/login/authenticate','https://www.stackcp.com/login/implicitBranded']&&array_column($requests,'method')===['GET','POST','POST']);
    sc_ok('Support sender does not send local delegation or package authority',$requests[0]['body']===null&&$requests[1]['body']==='{"grant_type":"client_credentials","scope":"stack-user:97"}'&&$requests[2]['body']==='{"brand":{"brandDomain":null}}');
    sc_ok('Support sender preserves distinct credential roles',array_column($requests,'authorization')===array_map(fn($v)=>'Bearer '.base64_encode($v),['PRIVATE_GENERAL_KEY','PRIVATE_AUTH_KEY','PRIVATE_TOKEN_VALUE']));
    sc_ok('Support sender records phase boundaries before each request',array_values(array_filter($events,fn($v)=>str_starts_with($v,'before:')||str_starts_with($v,'send:')||str_starts_with($v,'after:')))===['before:brand','send:brand','after:brand','before:token','send:token','after:token','before:session','send:session','after:session']);
    sc_ok('Support sender resolves fixed DNS before lazy credentials',array_search('dns:api.20i.com',$events,true)<array_search('key:general-api-key',$events,true)&&array_search('dns:auth-api.20i.com',$events,true)<array_search('key:auth-client-key',$events,true));
    sc_ok('Actual support sender returns only one-use delivery',$sent->consume(fn($url)=>$url==='https://www.stackcp.com/login/sso?PHPSESSID=PRIVATE_SESSION_VALUE'));
    $events=[];$requests=[];try{sc_send($context,['custom_brand'=>true],$events,$requests);sc_ok('Support custom brand refuses before token',false);}catch(StackCpLaunchFailure $e){sc_ok('Support custom brand refuses before token',$e->reason==='custom_brand'&&!$e->possibly_sent&&count($requests)===1&&!in_array('key:auth-client-key',$events,true));}
    $events=[];$requests=[];try{sc_send($context,['deny_before'=>'brand'],$events,$requests);sc_ok('Support authority refusal precedes DNS and credentials',false);}catch(StackCpLaunchFailure $e){sc_ok('Support authority refusal precedes DNS and credentials',$e->reason==='access_changed'&&!$e->possibly_sent&&$requests===[]&&!in_array('dns:api.20i.com',$events,true));}
    $events=[];$requests=[];try{sc_send($context,['deny_after_send'=>'token'],$events,$requests);sc_ok('Changed support authority after token stays possibly sent',false);}catch(StackCpLaunchFailure $e){sc_ok('Changed support authority after token stays possibly sent',$e->reason==='access_changed'&&$e->possibly_sent&&$e->phase==='token'&&count($requests)===2&&!str_contains($e->getMessage(),'PRIVATE_'));}
    $events=[];$requests=[];sc_refuse('Support durable phase failure propagates without send',function()use($context,&$events,&$requests){sc_send($context,['fault_before'=>'brand'],$events,$requests);},PDOException::class);sc_ok('Support phase failure does not open credentials',$requests===[]&&!in_array('key:general-api-key',$events,true));
    $properties=get_object_vars($context);$properties['data']['actor_kind']='owner';$restored=sc_restore($properties);$events=[];$requests=[];
    sc_refuse('Sender revalidates a restored support context before callbacks',function()use($restored,&$events,&$requests){sc_send($restored,[],$events,$requests);});sc_ok('Invalid restored support context performs no work',$events===[]&&$requests===[]);
    sc_refuse('Sensitive parser revalidates restored support context',fn()=>StackCpSensitiveResponse::parseToken($restored,200,'{}',$credentials));
    sc_refuse('Delivery revalidates restored support context',fn()=>StackCpLaunchDelivery::fromSession($restored,sc_session($context)));
    $ownerSession=sc_session($owner);sc_ok('Owner parser remains bound to owner digest',$ownerSession->state==='accepted_subset'&&$ownerSession->contextSha256===$owner->sha256);
    sc_refuse('Owner session cannot be delivered under support context',fn()=>StackCpLaunchDelivery::fromSession($context,$ownerSession));
    sc_ok('Support actor and browser binding are explicit separate witnesses',$context->data['review']['staff_id']===4&&$context->data['review']['client_id']===9&&$context->data['review']['delegation_owner_id']===1&&$context->data['review']['support_id']===str_repeat('7',32)&&$context->data['review']['support_binding_sha256']===str_repeat('8',64));
    foreach([1760000000,1759999999] as $i=>$expiry){$bad=$input;$bad['support_expires_epoch']=$expiry;sc_refuse('Fresh observation cannot outlive support session '.$i,fn()=>new StackCpSupportHandoffContext($bad));}
    $clientInput=$input;foreach(['staff_id','staff_auth_version','support_id','support_binding_sha256','support_expires_epoch'] as $key)unset($clientInput[$key]);
    $client=new \CajunWebPro\Foundation\TwentyISupportHandoff\StackCpClientHandoffContext($clientInput);
    sc_ok('Same resource produces three distinct principal digests',count(array_unique([$owner->sha256,$client->sha256,$context->sha256]))===3);
    sc_refuse('Primary-client payload cannot become support without staff session witnesses',fn()=>new StackCpSupportHandoffContext($clientInput));
    sc_refuse('Support context cannot be restored as a primary client',fn()=>\CajunWebPro\Foundation\TwentyISupportHandoff\StackCpClientHandoffContext::validated($context),TypeError::class);
    $clientSession=StackCpSensitiveResponse::parseSession($client,200,'{"session_id":"PRIVATE_SESSION_VALUE"}',$credentials+['access-token'=>'PRIVATE_TOKEN_VALUE']);
    sc_refuse('Primary-client session cannot cross into support delivery',fn()=>StackCpLaunchDelivery::fromSession($context,$clientSession));
    sc_refuse('Support session cannot cross into primary-client delivery',fn()=>StackCpLaunchDelivery::fromSession($client,sc_session($context)));
    $completed=true;
}catch(Throwable $error){$failure=get_class($error);}
restore_error_handler();
$passed=$completed&&!in_array(false,array_column($checks,'passed'),true);
$report=['passed'=>$passed,'scope'=>'Actual detached support context and shared parser/sender/delivery with synthetic HTTP/DNS only; no current SQL authority, provider requests, browser or installed acceptance.','checks'=>$checks,'count'=>count($checks),'failure'=>$failure??null];
$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if(isset($argv[1]))file_put_contents($argv[1],$json);else echo $json;
exit($passed?0:1);
