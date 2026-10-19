<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require_once __DIR__.'/../foundation/twentyi-support-handoff/StackCpLaunchSender.php';
use CajunWebPro\Foundation\TwentyISupportHandoff\{StackCpClientHandoffContext,StackCpHandoffContext,StackCpSensitiveResponse,StackCpLaunchSender,StackCpLaunchTransport,StackCpLaunchDelivery,StackCpLaunchFailure};
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse};

$checks=[];$completed=false;
set_error_handler(static function(int $level,string $message):never {throw new ErrorException('PHP diagnostic',0,$level);});
function cc_ok(string $name,bool $passed):void {
    global $checks;
    if(in_array($name,array_column($checks,'name'),true))throw new LogicException('Duplicate check name.');
    $checks[]=['name'=>$name,'passed'=>$passed];
    if(!$passed)throw new RuntimeException('Named client context check failed.');
}
function cc_refuse(string $name,callable $call,string $class=InvalidArgumentException::class):Throwable {
    try{$call();}catch(Throwable $error){cc_ok($name,$error instanceof $class&&!str_contains($error->getMessage(),'PRIVATE_'));return $error;}
    cc_ok($name,false);throw new LogicException('Unreachable.');
}
function cc_input():array {
    return ['package'=>['profile_id'=>str_repeat('1',32),'profile_revision'=>1,'key_fingerprint'=>str_repeat('2',64),'credential_sha256'=>str_repeat('3',64),
        'package_id'=>701,'client_id'=>9,'link_id'=>str_repeat('4',32),'link_sha256'=>str_repeat('5',64),'event_id'=>10,'event_sha256'=>str_repeat('6',64),
        'audit_id'=>11,'audit_sha256'=>str_repeat('7',64),'source_id'=>str_repeat('8',32),'source_seq'=>12,'source_sha256'=>str_repeat('9',64)],
        'client_id'=>9,'client_auth_version'=>str_repeat('a',32),'delegation_id'=>str_repeat('b',32),'delegation_revision'=>3,'delegation_sha256'=>str_repeat('c',64),
        'delegation_owner_id'=>1,'delegation_owner_auth_version'=>str_repeat('d',32),'auth_credential_id'=>str_repeat('e',32),'auth_revision'=>2,'auth_credential_sha256'=>str_repeat('f',64),
        'stack_user_identity'=>'stack-user:97','membership_source_id'=>str_repeat('0',32),'membership_source_seq'=>15,'membership_source_sha256'=>str_repeat('1',64),
        'grants_source_id'=>str_repeat('2',32),'grants_source_seq'=>17,'grants_source_sha256'=>str_repeat('3',64),'observed_grants'=>['package:701'],'observed_at'=>1760000000,
        'permission_id'=>str_repeat('4',32),'permission_revision'=>1,'permission_sha256'=>str_repeat('5',64)];
}
function cc_owner(array $client):StackCpHandoffContext {
    $owner=['package'=>$client['package'],'actor_id'=>$client['delegation_owner_id'],'actor_auth_version'=>$client['delegation_owner_auth_version'],'client_auth_version'=>$client['client_auth_version']];
    foreach(['auth_credential_id','auth_revision','auth_credential_sha256','stack_user_identity','membership_source_id','membership_source_seq','membership_source_sha256',
        'grants_source_id','grants_source_seq','grants_source_sha256','observed_grants','observed_at','permission_id','permission_revision','permission_sha256'] as $key)$owner[$key]=$client[$key];
    return new StackCpHandoffContext($owner);
}
function cc_restore(array $properties):StackCpClientHandoffContext {
    $object=(new ReflectionClass(StackCpClientHandoffContext::class))->newInstanceWithoutConstructor();
    foreach($properties as $key=>$value)(new ReflectionProperty(StackCpClientHandoffContext::class,$key))->setValue($object,$value);
    return $object;
}
function cc_session(StackCpHandoffContext|StackCpClientHandoffContext $context):StackCpSensitiveResponse {
    return StackCpSensitiveResponse::parseSession($context,200,'{"session_id":"PRIVATE_SESSION_VALUE"}',
        ['general-api-key'=>'PRIVATE_GENERAL_KEY','auth-client-key'=>'PRIVATE_AUTH_KEY','access-token'=>'PRIVATE_TOKEN_VALUE']);
}
/** Real sender/parser/delivery and wire validation, with closed in-memory HTTP and DNS doubles. */
function cc_send(StackCpHandoffContext|StackCpClientHandoffContext $context,array $options,array &$events,array &$requests):StackCpLaunchDelivery {
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
    $input=cc_input();$context=new StackCpClientHandoffContext($input);$owner=cc_owner($input);
    cc_ok('Client principal is distinct from the delegating owner and bound package client',$context->data['actor_kind']==='client'&&$context->data['review']['client_id']===9&&$context->data['review']['delegation_owner_id']===1&&$context->data['review']['package']['client_id']===9&&!isset($context->data['review']['actor_id']));
    cc_ok('Client operation does not claim a package-restricted provider session',$context->data['operation']==='stackcp.client-handoff'&&$context->data['provider_session_scope']==='whole-stack-user'&&$context->data['remote_scope_restricted']===false);
    cc_ok('Owner context remains explicitly owner-scoped and distinct',$owner->data['actor_kind']==='owner'&&$owner->data['operation']==='stackcp.support-handoff'&&$owner->sha256!==$context->sha256&&$owner->scope!==$context->scope);
    cc_ok('Client canonical digest and detached bytes agree',hash('sha256',$context->json)===$context->sha256&&json_decode($context->json,true)===$context->data&&StackCpClientHandoffContext::validated($context)->json===$context->json);
    $reverse=array_reverse($input,true);$reverse['package']=array_reverse($reverse['package'],true);
    cc_ok('Input field order does not change the signed client review',(new StackCpClientHandoffContext($reverse))->json===$context->json);
    foreach($input as $key=>$value){$bad=$input;unset($bad[$key]);cc_refuse('Missing client witness '.$key,fn()=>new StackCpClientHandoffContext($bad));}
    foreach(['actor_id','actor_auth_version','observing_owner_id','confirmed','remote_scope_restricted','scope','return_url'] as $key){$bad=$input;$bad[$key]='PRIVATE_UNTRUSTED';cc_refuse('Unsupported authority or destination field '.$key,fn()=>new StackCpClientHandoffContext($bad));}
    foreach(['client_id','delegation_revision','delegation_owner_id','auth_revision','membership_source_seq','grants_source_seq','permission_revision'] as $key){
        foreach([0,-1,'1',true,null] as $i=>$value){$bad=$input;$bad[$key]=$value;cc_refuse('Invalid positive client witness '.$key.' '.$i,fn()=>new StackCpClientHandoffContext($bad));}
        $changed=$input;$changed[$key]++;if($key==='client_id')$changed['package']['client_id']=$changed[$key];
        cc_ok('Changed principal or sequence changes digest '.$key,(new StackCpClientHandoffContext($changed))->sha256!==$context->sha256);
    }
    foreach(['client_auth_version','delegation_id','delegation_owner_auth_version','auth_credential_id','membership_source_id','grants_source_id','permission_id',
        'delegation_sha256','auth_credential_sha256','membership_source_sha256','grants_source_sha256','permission_sha256'] as $key){
        $bad=$input;$bad[$key]='PRIVATE_INVALID';cc_refuse('Malformed identity or proof '.$key,fn()=>new StackCpClientHandoffContext($bad));
        $changed=$input;$changed[$key]=str_repeat('8',strlen($changed[$key]));cc_ok('Changed retained client witness changes digest '.$key,(new StackCpClientHandoffContext($changed))->sha256!==$context->sha256);
    }
    foreach($input['package'] as $key=>$value){$bad=$input;unset($bad['package'][$key]);cc_refuse('Missing package witness '.$key,fn()=>new StackCpClientHandoffContext($bad));
        $changed=$input;$changed['package'][$key]=is_int($value)?$value+1:str_repeat('b',strlen($value));
        if($key==='client_id')$changed['client_id']=$changed['package'][$key];if($key==='package_id')$changed['observed_grants']=['package:'.$changed['package'][$key]];
        cc_ok('Changed package witness changes digest '.$key,(new StackCpClientHandoffContext($changed))->sha256!==$context->sha256);
    }
    $bad=$input;$bad['client_id']=10;cc_refuse('Different primary client cannot enter package context',fn()=>new StackCpClientHandoffContext($bad));
    $bad=$input;$bad['package']['extra']='PRIVATE_FIELD';cc_refuse('Unknown package property refused',fn()=>new StackCpClientHandoffContext($bad));
    foreach([[],['package:702'],['package:701','package:702'],['package:701','domain:1'],['server:1'],['package:701','package:701'],['Package:701'],['package:0701'],[1=>'package:701']] as $i=>$values){$bad=$input;$bad['observed_grants']=$values;cc_refuse('Fresh grant subset refuses ambiguous or additional scope '.$i,fn()=>new StackCpClientHandoffContext($bad));}
    foreach(['','97','stack-user:0','stack-user:097','stack-user:1<interactive>','stack-user:9999999999999999999','Stack-user:97'] as $i=>$value){$bad=$input;$bad['stack_user_identity']=$value;cc_refuse('Stack user identity subset '.$i,fn()=>new StackCpClientHandoffContext($bad));}
    foreach([-1,'1760000000',true] as $i=>$value){$bad=$input;$bad['observed_at']=$value;cc_refuse('Observed epoch type '.$i,fn()=>new StackCpClientHandoffContext($bad));}
    $clientRef=9;$ownerRef=1;$reference=$input;$reference['client_id']=&$clientRef;$reference['package']['client_id']=&$clientRef;$reference['delegation_owner_id']=&$ownerRef;
    $detached=new StackCpClientHandoffContext($reference);$clientRef=10;$ownerRef=2;
    cc_ok('Client and delegation nested references are detached',$detached->json===$context->json&&$detached->data['review']['client_id']===9&&$detached->data['review']['delegation_owner_id']===1);
    foreach(['json','sha256','scope'] as $field){$properties=get_object_vars($context);$properties[$field]='PRIVATE_DERIVED';$restored=cc_restore($properties);cc_refuse('Restored client derived value rejected '.$field,fn()=>StackCpClientHandoffContext::validated($restored));}
    foreach(['actor_kind'=>'owner','operation'=>'stackcp.support-handoff','remote_scope_restricted'=>true] as $key=>$value){$properties=get_object_vars($context);$properties['data'][$key]=$value;$restored=cc_restore($properties);cc_refuse('Restored actor or scope cannot bypass client validation '.$key,fn()=>StackCpClientHandoffContext::validated($restored));}
    cc_refuse('Owner validator does not accept a client context',fn()=>StackCpHandoffContext::validated($context),TypeError::class);
    cc_refuse('Client validator does not accept an owner context',fn()=>StackCpClientHandoffContext::validated($owner),TypeError::class);
    cc_refuse('Owner payload cannot be relabeled as client',fn()=>new StackCpClientHandoffContext($owner->data['review']));
    cc_refuse('Client payload cannot be relabeled as owner',fn()=>new StackCpHandoffContext($input));
    $credentials=['general-api-key'=>'PRIVATE_GENERAL_KEY','auth-client-key'=>'PRIVATE_AUTH_KEY'];
    $token=StackCpSensitiveResponse::parseToken($context,200,'{"access_token":"PRIVATE_TOKEN_VALUE","token_type":"bearer","expires_in":60,"scope":"interactive"}',$credentials);
    cc_ok('Client token uses unchanged sensitive parser and client digest',$token->state==='accepted_subset'&&$token->contextSha256===$context->sha256&&!str_contains(json_encode($token),'PRIVATE_'));
    $session=cc_session($context);cc_refuse('Client session cannot be delivered under owner context',fn()=>StackCpLaunchDelivery::fromSession($owner,$session));
    $changed=$input;$changed['delegation_revision']++;$other=new StackCpClientHandoffContext($changed);
    cc_refuse('Client session cannot be delivered under changed delegation',fn()=>StackCpLaunchDelivery::fromSession($other,$session));
    $delivery=StackCpLaunchDelivery::fromSession($context,$session);cc_refuse('Client delivery cannot be serialized',fn()=>serialize($delivery),LogicException::class);
    cc_refuse('Client delivery cannot be JSON encoded',fn()=>json_encode($delivery),LogicException::class);
    cc_refuse('Client delivery cannot be cloned',fn()=>clone $delivery,Error::class);
    cc_ok('Client delivery debug excludes session and destination',!str_contains(print_r($delivery,true),'PRIVATE_')&&!str_contains(print_r($delivery,true),'https://'));
    $calls=0;cc_ok('Client delivery constructs only exact fixed HTTPS destination',$delivery->consume(function($url)use(&$calls):bool{$calls++;return $url==='https://www.stackcp.com/login/sso?PHPSESSID=PRIVATE_SESSION_VALUE';}));
    cc_refuse('Client delivery cannot be consumed twice',fn()=>$delivery->consume(function()use(&$calls){$calls++;}),LogicException::class);cc_ok('Second client delivery never invokes consumer',$calls===1);
    $delivery=StackCpLaunchDelivery::fromSession($context,cc_session($context));cc_refuse('Client callback failure consumes delivery',fn()=>$delivery->consume(fn()=>throw new RuntimeException('Synthetic callback failure.')),RuntimeException::class);
    cc_refuse('Client callback failure cannot replay',fn()=>$delivery->consume(fn()=>null),LogicException::class);
    $bad=StackCpSensitiveResponse::parseToken($context,200,'{"access_token":"PRIVATE_AUTH_KEY","token_type":"bearer","expires_in":60,"scope":"interactive"}',$credentials);
    cc_ok('Client path retains credential echo refusal',$bad->state==='unavailable'&&$bad->reason==='credential_echo'&&!str_contains(json_encode($bad),'PRIVATE_'));
    $bad=StackCpSensitiveResponse::parseSession($context,200,'{"session_id":"a","session_id":"b"}',$credentials+['access-token'=>'PRIVATE_TOKEN_VALUE']);
    cc_ok('Client path retains duplicate response refusal',$bad->state==='unavailable'&&$bad->reason==='duplicate_field');
    $events=[];$requests=[];$sent=cc_send($context,[],$events,$requests);
    cc_ok('Client sender preserves three exact SDK endpoints',array_column($requests,'url')===['https://api.20i.com/reseller/*/customisations','https://auth-api.20i.com:3000/login/authenticate','https://www.stackcp.com/login/implicitBranded']&&array_column($requests,'method')===['GET','POST','POST']);
    cc_ok('Client sender does not send local delegation or package authority',$requests[0]['body']===null&&$requests[1]['body']==='{"grant_type":"client_credentials","scope":"stack-user:97"}'&&$requests[2]['body']==='{"brand":{"brandDomain":null}}');
    cc_ok('Client sender preserves distinct credential roles',array_column($requests,'authorization')===array_map(fn($v)=>'Bearer '.base64_encode($v),['PRIVATE_GENERAL_KEY','PRIVATE_AUTH_KEY','PRIVATE_TOKEN_VALUE']));
    cc_ok('Client sender records phase boundaries before each request',array_values(array_filter($events,fn($v)=>str_starts_with($v,'before:')||str_starts_with($v,'send:')||str_starts_with($v,'after:')))===['before:brand','send:brand','after:brand','before:token','send:token','after:token','before:session','send:session','after:session']);
    cc_ok('Client sender resolves fixed DNS before lazy credentials',array_search('dns:api.20i.com',$events,true)<array_search('key:general-api-key',$events,true)&&array_search('dns:auth-api.20i.com',$events,true)<array_search('key:auth-client-key',$events,true));
    cc_ok('Actual client sender returns only one-use delivery',$sent->consume(fn($url)=>$url==='https://www.stackcp.com/login/sso?PHPSESSID=PRIVATE_SESSION_VALUE'));
    $events=[];$requests=[];try{cc_send($context,['custom_brand'=>true],$events,$requests);cc_ok('Client custom brand refuses before token',false);}catch(StackCpLaunchFailure $e){cc_ok('Client custom brand refuses before token',$e->reason==='custom_brand'&&!$e->possibly_sent&&count($requests)===1&&!in_array('key:auth-client-key',$events,true));}
    $events=[];$requests=[];try{cc_send($context,['deny_before'=>'brand'],$events,$requests);cc_ok('Client authority refusal precedes DNS and credentials',false);}catch(StackCpLaunchFailure $e){cc_ok('Client authority refusal precedes DNS and credentials',$e->reason==='access_changed'&&!$e->possibly_sent&&$requests===[]&&!in_array('dns:api.20i.com',$events,true));}
    $events=[];$requests=[];try{cc_send($context,['deny_after_send'=>'token'],$events,$requests);cc_ok('Changed client authority after token stays possibly sent',false);}catch(StackCpLaunchFailure $e){cc_ok('Changed client authority after token stays possibly sent',$e->reason==='access_changed'&&$e->possibly_sent&&$e->phase==='token'&&count($requests)===2&&!str_contains($e->getMessage(),'PRIVATE_'));}
    $events=[];$requests=[];cc_refuse('Client durable phase failure propagates without send',function()use($context,&$events,&$requests){cc_send($context,['fault_before'=>'brand'],$events,$requests);},PDOException::class);cc_ok('Client phase failure does not open credentials',$requests===[]&&!in_array('key:general-api-key',$events,true));
    $properties=get_object_vars($context);$properties['data']['actor_kind']='owner';$restored=cc_restore($properties);$events=[];$requests=[];
    cc_refuse('Sender revalidates a restored client context before callbacks',function()use($restored,&$events,&$requests){cc_send($restored,[],$events,$requests);});cc_ok('Invalid restored client context performs no work',$events===[]&&$requests===[]);
    cc_refuse('Sensitive parser revalidates restored client context',fn()=>StackCpSensitiveResponse::parseToken($restored,200,'{}',$credentials));
    cc_refuse('Delivery revalidates restored client context',fn()=>StackCpLaunchDelivery::fromSession($restored,cc_session($context)));
    $ownerSession=cc_session($owner);cc_ok('Owner parser remains bound to owner digest',$ownerSession->state==='accepted_subset'&&$ownerSession->contextSha256===$owner->sha256);
    cc_refuse('Owner session cannot be delivered under client context',fn()=>StackCpLaunchDelivery::fromSession($context,$ownerSession));
    $completed=true;
}catch(Throwable $error){$failure=get_class($error);}
restore_error_handler();
$passed=$completed&&!in_array(false,array_column($checks,'passed'),true);
$report=['passed'=>$passed,'scope'=>'Actual detached client context and shared parser/sender/delivery with synthetic HTTP/DNS only; no current SQL authority, provider requests, browser or installed acceptance.','checks'=>$checks,'count'=>count($checks),'failure'=>$failure??null];
$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if(isset($argv[1]))file_put_contents($argv[1],$json);else echo $json;
exit($passed?0:1);
