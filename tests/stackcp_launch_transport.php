<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyISupportHandoff {
    // Exact production cURL method is exercised with isolated function doubles;
    // these functions cannot open a socket, including in minimal PHP.
    function function_exists(string $name): bool { return $name==='curl_init' || \function_exists($name); }
    function curl_init(string $url): object { return (object)['url'=>$url]; }
    function curl_setopt_array(object $handle,array $options): bool { $GLOBALS['sl_curl_options']=$options; return true; }
    function curl_exec(object $handle): bool {
        $GLOBALS['sl_curl_calls']++;
        $options=$GLOBALS['sl_curl_options'];
        $line=$GLOBALS['sl_curl_header'] ?? "Set-Cookie: PRIVATE_COOKIE=secret\r\n";
        if ($options[CURLOPT_HEADERFUNCTION]($handle,$line)!==strlen($line)) return false;
        $body=$GLOBALS['sl_curl_body'] ?? ($GLOBALS['sl_curl_response_by_url'][$handle->url] ?? '{}');
        return $options[CURLOPT_WRITEFUNCTION]($handle,$body)===strlen($body);
    }
    function curl_errno(object $handle): int { return 0; }
    function curl_getinfo(object $handle,int $option): int { return $GLOBALS['sl_curl_status'] ?? 200; }
}
namespace {
if (PHP_SAPI!=='cli') exit(2);
// Constants are only handles into the isolated options double above when curl is absent.
$names=['CURLOPT_POST','CURLOPT_POSTFIELDS','CURLOPT_HTTPHEADER','CURLOPT_HTTPGET','CURLOPT_RETURNTRANSFER','CURLOPT_HEADER',
    'CURLOPT_FOLLOWLOCATION','CURLOPT_MAXREDIRS','CURLOPT_PROTOCOLS','CURLOPT_REDIR_PROTOCOLS','CURLOPT_PROXY','CURLOPT_PRE_PROXY','CURLOPT_NOPROXY',
    'CURLOPT_NETRC','CURLOPT_HTTPAUTH','CURLOPT_SSL_VERIFYPEER','CURLOPT_SSL_VERIFYHOST','CURLOPT_SSLVERSION','CURLOPT_RESOLVE','CURLOPT_HTTP_VERSION',
    'CURLOPT_HTTP09_ALLOWED','CURLOPT_FRESH_CONNECT','CURLOPT_FORBID_REUSE','CURLOPT_NOSIGNAL','CURLOPT_CONNECTTIMEOUT_MS','CURLOPT_TIMEOUT_MS',
    'CURLOPT_LOW_SPEED_LIMIT','CURLOPT_LOW_SPEED_TIME','CURLOPT_BUFFERSIZE','CURLOPT_HEADERFUNCTION','CURLOPT_WRITEFUNCTION','CURLINFO_RESPONSE_CODE',
    'CURLPROTO_HTTPS','CURL_NETRC_IGNORED','CURLAUTH_NONE','CURL_SSLVERSION_TLSv1_2','CURL_HTTP_VERSION_1_1',
    'CURLE_COULDNT_CONNECT','CURLE_COULDNT_RESOLVE_HOST','CURLE_SSL_CACERT','CURLE_SSL_CONNECT_ERROR','CURLE_SSL_CERTPROBLEM','CURLE_SSL_CIPHER',
    'CURLE_SSL_CACERT_BADFILE','CURLE_OPERATION_TIMEDOUT','CURLOPT_COOKIE','CURLOPT_COOKIEFILE','CURLOPT_COOKIEJAR'];
foreach ($names as $i=>$name) if (!defined($name)) define($name,100000+$i);
require_once __DIR__.'/../foundation/twentyi-support-handoff/StackCpLaunchSender.php';
use CajunWebPro\Foundation\TwentyISupportHandoff\{StackCpHandoffContext,StackCpSensitiveResponse,StackCpDefaultBrand,StackCpLaunchSender,StackCpLaunchTransport,StackCpLaunchFailure};
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,TransportException};
$checks=[]; $completed=false;
set_error_handler(static function(int $level,string $message): never { throw new ErrorException('PHP diagnostic',0,$level); });
function sl_ok(string $name,bool $passed): void {
    global $checks;
    if (in_array($name,array_column($checks,'name'),true)) throw new LogicException('Duplicate check name.');
    $checks[]=['name'=>$name,'passed'=>$passed];
    if (!$passed) throw new RuntimeException('Named check failed.');
}
function sl_refuse(string $name,callable $call,string $class=StackCpLaunchFailure::class): ?Throwable {
    try { $call(); } catch (Throwable $e) {
        sl_ok($name,$e instanceof $class && !str_contains($e->getMessage(),'PRIVATE_')); return $e;
    }
    sl_ok($name,false); return null;
}
function sl_context(): StackCpHandoffContext {
    $package=['profile_id'=>str_repeat('1',32),'profile_revision'=>1,'key_fingerprint'=>str_repeat('2',64),'credential_sha256'=>str_repeat('3',64),
        'package_id'=>701,'client_id'=>9,'link_id'=>str_repeat('4',32),'link_sha256'=>str_repeat('5',64),'event_id'=>10,'event_sha256'=>str_repeat('6',64),
        'audit_id'=>11,'audit_sha256'=>str_repeat('7',64),'source_id'=>str_repeat('8',32),'source_seq'=>12,'source_sha256'=>str_repeat('9',64)];
    return new StackCpHandoffContext(['package'=>$package,'actor_id'=>1,'actor_auth_version'=>str_repeat('a',32),'client_auth_version'=>str_repeat('b',32),
        'auth_credential_id'=>str_repeat('c',32),'auth_revision'=>2,'auth_credential_sha256'=>str_repeat('d',64),'stack_user_identity'=>'stack-user:97',
        'membership_source_id'=>str_repeat('e',32),'membership_source_seq'=>13,'membership_source_sha256'=>str_repeat('f',64),
        'grants_source_id'=>str_repeat('0',32),'grants_source_seq'=>14,'grants_source_sha256'=>str_repeat('1',64),'observed_grants'=>['package:701'],
        'observed_at'=>1760000000,'permission_id'=>str_repeat('2',32),'permission_revision'=>3,'permission_sha256'=>str_repeat('3',64)]);
}
function sl_run(array $config,array &$events): mixed {
    $secrets=['general-api-key'=>'PRIVATE_GENERAL_KEY','auth-client-key'=>'PRIVATE_AUTH_KEY'];
    $current=''; $valid=true;
    $authorize=static function() use (&$events,&$valid): void { $events[]='authorize'; if (!$valid) throw new RuntimeException('PRIVATE_AUTHORITY_DETAIL'); };
    return StackCpLaunchSender::launch(sl_context(),
        static function(string $role) use (&$events,&$valid,$secrets,$config): mixed {
            $events[]='key:'.$role;
            if (($config['deny_key']??'')===$role) $valid=false;
            return $config['credentials'][$role] ?? $secrets[$role];
        },
        $authorize,
        static function(string $phase) use (&$events,&$current,&$valid,$config): void {
            $events[]='before:'.$phase; $current=$phase;
            if (($config['deny_before']??'')===$phase) $valid=false;
            if (($config['fault_before']??'')===$phase) throw new LogicException('Synthetic phase persistence fault.');
        },
        static function(string $phase) use (&$events,$config): void {
            $events[]='after:'.$phase;
            if (($config['fault_after']??'')===$phase) throw new LogicException('Synthetic phase persistence fault.');
        },
        static function(string $host) use (&$events,&$current,$config): array {
            $events[]='dns:'.$host;
            return ($config['bad_dns']??'')===$current ? [['type'=>'A','ip'=>'127.0.0.1']] : [['type'=>'A','ip'=>'8.8.8.8']];
        },
        static function(string $phase,string $address,callable $guard) use (&$events,&$valid,$config): HttpTransport {
            $events[]='factory:'.$phase.':'.$address;
            return new class($phase,$guard,$config,$events,$valid) implements HttpTransport {
                private array $events; private bool $valid;
                public function __construct(private string $phase,private mixed $guard,private array $config,array &$events,bool &$valid) { $this->events=&$events; $this->valid=&$valid; }
                public function send(#[SensitiveParameter] TransportRequest $request): TransportResponse {
                    ($this->guard)(); $this->events[]='send:'.$this->phase;
                    $validate=new ReflectionMethod(StackCpLaunchTransport::class,'validate');
                    $validate->invoke(new StackCpLaunchTransport($this->phase,'8.8.8.8',$this->guard),$request);
                    if (isset($this->config['inspect'])) ($this->config['inspect'])($this->phase,$request);
                    if (($this->config['throw']??'')===$this->phase) throw new TransportException('timeout');
                    if (($this->config['engine_error']??'')===$this->phase) throw new TypeError('Synthetic engine fault.');
                    if (($this->config['deny_after']??'')===$this->phase) $this->valid=false;
                    $body=$this->config['body'][$this->phase] ?? match($this->phase) {
                        'brand'=>'{ "brandDomain": null, "brandName":"Synthetic", "opaque":{"n":1,"list":[true,null,"x"]}}',
                        'token'=>'{"access_token":"PRIVATE_ACCESS_TOKEN","token_type":"bearer","expires_in":60,"scope":"interactive"}',
                        'session'=>'{"session_id":"PRIVATE_SESSION_ID+/="}',
                    };
                    return new TransportResponse($this->config['status'][$this->phase]??200,[],$body);
                }
            };
        });
}
try {
    $actual=' {"brandDomain":null,"opaque":{"nested":[1,true,null,{"label":"Studio"}]},"1":"a","01":"b"} ';
    $brand=StackCpDefaultBrand::fromResponse(200,$actual,['PRIVATE_GENERAL_KEY']);
    sl_ok('Actual complete default brand bytes are embedded unchanged',$brand->withBody(fn($body)=>$body)==='{"brand":'.$actual.'}');
    foreach (['{}','{"brandDomain":null}','{"brandDomain":""}','{"label":"Real observed brand"}'] as $i=>$body)
        sl_ok('Observed narrow default brand accepted '.$i,StackCpDefaultBrand::fromResponse(200,$body,['PRIVATE_KEY']) instanceof StackCpDefaultBrand);
    foreach (['{"brandDomain":"custom.invalid"}','{"brandDomain":"*"}','{"brandDomain":false}','{"brandDomain":0}',
        '{"brandDomain":[]}','{"x":1,"x":2}','{"nested":{"x":1,"\\u0078":2}}','{"x":1,"x":1}',
        '[]','null','true','"text"','{"x":1,}','{"x":[1,]}','{"x":"\uD800"}','{"x":1} trailing',
        '{"x":"PRIVATE_KEY"}','{"PRIVATE_KEY":"x"}','{"nested":["UFJJVkFURV9LRVk="]}',
        '{"x":"'.str_repeat('x',16384).'"}',str_repeat('{"x":',10).'0'.str_repeat('}',10),'{"x":1e999}'] as $i=>$body)
        sl_refuse('Malformed custom duplicate or echoed brand refuses '.$i,fn()=>StackCpDefaultBrand::fromResponse(200,$body,['PRIVATE_KEY']));
    $largeSecret='123456789012345678901234567890';
    foreach ([
        'numeric literal'=>'{"brandDomain":null,"retained":'.$largeSecret.'}',
        'base64 string'=>'{"retained":"'.base64_encode($largeSecret).'"}',
        'escaped value'=>'{"retained":"\u0031'.substr($largeSecret,1).'"}',
        'escaped field'=>'{"\u0031'.substr($largeSecret,1).'":"safe"}',
    ] as $kind=>$echoBody) {
        $echoFailure=sl_refuse('Retained brand rejects oversized credential '.$kind,
            fn()=>StackCpDefaultBrand::fromResponse(200,$echoBody,[$largeSecret]));
        sl_ok('Oversized credential refusal is finite '.$kind,
            $echoFailure->phase==='brand' && $echoFailure->reason==='credential_echo' && !$echoFailure->possibly_sent);
    }
    $laterBrand=StackCpDefaultBrand::fromResponse(200,'{"retained":'.$largeSecret.'}',['OTHER_PRIVATE_KEY']);
    sl_refuse('Later lazy credential check rejects original numeric bytes',fn()=>$laterBrand->assertNoEcho([$largeSecret]));
    sl_refuse('Brand cannot be serialized',fn()=>serialize($brand),LogicException::class);
    sl_refuse('Brand cannot be JSON exported',fn()=>json_encode($brand),LogicException::class);
    sl_ok('Brand debug excludes actual contents',!str_contains(print_r($brand,true),'Studio'));
    foreach ([201,301,401,429,500] as $status) sl_refuse('Only actual 200 brand accepted '.$status,fn()=>StackCpDefaultBrand::fromResponse($status,'{}',['PRIVATE_KEY']));

    $requests=[]; $events=[];
    $delivery=sl_run(['inspect'=>static function($phase,$request) use (&$requests): void { $requests[$phase]=$request; }],$events);
    $semantic=array_values(array_filter($events,fn($e)=>!str_starts_with($e,'authorize')&&!str_starts_with($e,'factory:')));
    sl_ok('Durable phase DNS lazy credential sends and accepted phases ordered',$semantic===[
        'before:brand','dns:api.20i.com','key:general-api-key','send:brand','after:brand',
        'before:token','dns:auth-api.20i.com','key:auth-client-key','send:token','after:token',
        'before:session','dns:www.stackcp.com','send:session','after:session']);
    sl_ok('SDK auth body has exact whole Stack user scope',$requests['token']->body==='{"grant_type":"client_credentials","scope":"stack-user:97"}');
    sl_ok('SDK session body carries actual retrieved brand object',$requests['session']->body==='{"brand":{ "brandDomain": null, "brandName":"Synthetic", "opaque":{"n":1,"list":[true,null,"x"]}}}');
    foreach (['brand'=>'PRIVATE_GENERAL_KEY','token'=>'PRIVATE_AUTH_KEY','session'=>'PRIVATE_ACCESS_TOKEN'] as $phase=>$secret)
        sl_ok('SDK base64 Bearer credential role '.$phase,$requests[$phase]->headers['Authorization']==='Bearer '.base64_encode($secret));
    sl_ok('Delivery has no public raw properties',get_object_vars($delivery)===[]);
    sl_refuse('Delivery PHP serialization denied',fn()=>serialize($delivery),LogicException::class);
    sl_refuse('Delivery JSON serialization denied',fn()=>json_encode($delivery),LogicException::class);
    sl_ok('Delivery debug has no session value',!str_contains(print_r($delivery,true),'PRIVATE_'));
    sl_ok('Delivery supplies only fixed HTTPS URL with safely encoded session',$delivery->consume(fn($url)=>$url==='https://www.stackcp.com/login/sso?PHPSESSID=PRIVATE_SESSION_ID%2B%2F%3D'));
    sl_refuse('Delivery cannot be consumed twice',fn()=>$delivery->consume(fn()=>null),LogicException::class);
    $events=[]; $interrupted=sl_run([],$events);
    sl_refuse('Delivery callback error propagates',fn()=>$interrupted->consume(fn()=>throw new RuntimeException('Synthetic interruption.')),RuntimeException::class);
    sl_refuse('Interrupted delivery cannot be consumed again',fn()=>$interrupted->consume(fn()=>null),LogicException::class);
    unset($requests);

    $events=[]; $e=sl_refuse('Custom brand stops before token',fn()=>sl_run(['body'=>['brand'=>'{"brandDomain":"custom.invalid"}']],$events));
    sl_ok('Custom branding has definite pre-POST refusal',$e->phase==='brand' && $e->reason==='custom_brand' && !$e->possibly_sent);
    // Explicit reference closure retains events after the refusal.
    $events=[]; try { sl_run(['body'=>['brand'=>'{"brandDomain":"custom.invalid"}']],$events); } catch (StackCpLaunchFailure $e) {}
    sl_ok('Custom brand only performed its exact GET',array_values(array_filter($events,fn($v)=>str_starts_with($v,'send:')))===['send:brand']);
    sl_ok('Custom brand never resolves auth credential',!in_array('key:auth-client-key',$events,true));
    foreach (['general-api-key'=>'brand','auth-client-key'=>'token'] as $role=>$phase) {
        $events=[];
        try { sl_run(['deny_key'=>$role],$events); sl_ok('Changed authority while resolving '.$role,false); }
        catch (StackCpLaunchFailure $e) { sl_ok('Changed authority after credential prevents '.$phase.' send',$e->reason==='access_changed' && !in_array('send:'.$phase,$events,true)); }
    }
    foreach (['general-api-key','auth-client-key'] as $role) {
        $events=[];
        try { sl_run(['credentials'=>[$role=>"PRIVATE_BAD\r\nKEY"]],$events); sl_ok('Invalid secret '.$role,false); }
        catch (StackCpLaunchFailure $e) { sl_ok('Invalid secret refuses before a POST '.$role,$e->reason==='credentials' && !$e->possibly_sent); }
    }
    $events=[];
    try { sl_run(['body'=>['brand'=>'{"theme":"PRIVATE_AUTH_KEY"}']],$events); sl_ok('Lazily known auth echo',false); }
    catch (StackCpLaunchFailure $e) { sl_ok('Retained brand is checked against newly resolved auth key',$e->phase==='token' && $e->reason==='credential_echo' && !in_array('send:token',$events,true)); }
    foreach (['brand','token','session'] as $phase) {
        foreach (['bad_dns','deny_before','deny_after','throw'] as $fault) {
            $events=[];
            try { sl_run([$fault=>$phase],$events); sl_ok('Fault '.$fault.' '.$phase,false); }
            catch (StackCpLaunchFailure $e) {
                sl_ok('Fault '.$fault.' '.$phase,$e->phase===$phase);
                $posted=in_array('send:token',$events,true)||in_array('send:session',$events,true);
                sl_ok('Fault keeps whole-launch possible send '.$fault.' '.$phase,$e->possibly_sent===$posted);
                sl_ok('Fault has no accepted phase '.$fault.' '.$phase,!in_array('after:'.$phase,$events,true));
                if ($fault==='bad_dns' && $phase!=='session') sl_ok('DNS refuses before relevant credential '.$phase,!in_array('key:'.($phase==='brand'?'general-api-key':'auth-client-key'),$events,true));
            }
        }
    }
    foreach (['token','session'] as $phase) foreach ([201,301,401,500] as $status) {
        $events=[];
        try { sl_run(['status'=>[$phase=>$status]],$events); sl_ok('POST status '.$phase.' '.$status,false); }
        catch (StackCpLaunchFailure $e) { sl_ok('POST status remains possibly sent '.$phase.' '.$status,$e->possibly_sent && $e->phase===$phase); }
    }
    foreach (['token'=>'{"access_token":"PRIVATE_AUTH_KEY","token_type":"bearer","expires_in":60,"scope":"interactive"}',
        'session'=>'{"session_id":"PRIVATE_ACCESS_TOKEN"}'] as $phase=>$body) {
        $events=[];
        try { sl_run(['body'=>[$phase=>$body]],$events); sl_ok('Sensitive echo '.$phase,false); }
        catch (StackCpLaunchFailure $e) { sl_ok('Sensitive echo refuses delivery '.$phase,$e->reason==='credential_echo' && $e->possibly_sent); }
    }
    foreach (['fault_before','fault_after','engine_error'] as $fault) { $events=[]; sl_refuse('Programmer or persistence error propagates '.$fault,fn()=>sl_run([$fault=>'token'],$events),$fault==='engine_error'?TypeError::class:LogicException::class); }

    $validate=new ReflectionMethod(StackCpLaunchTransport::class,'validate');
    $auth=['Authorization'=>'Bearer '.base64_encode('PRIVATE_GENERAL_KEY'),'Accept'=>'application/json'];
    foreach (['http://api.20i.com/reseller/*/customisations','https://api.20i.com:443/reseller/*/customisations',
        'https://api.20i.com/reseller/1/customisations','https://api.20i.com/reseller/*/customisations?x=1',
        'https://api.20i.com/reseller/*/customisations#x','https://evil.invalid/reseller/*/customisations'] as $i=>$url)
        sl_refuse('Altered brand URL refuses '.$i,fn()=>$validate->invoke(new StackCpLaunchTransport('brand','8.8.8.8',fn()=>null),new TransportRequest('GET',$url,$auth)),InvalidArgumentException::class);
    sl_refuse('Extra request cookie refuses',fn()=>$validate->invoke(new StackCpLaunchTransport('brand','8.8.8.8',fn()=>null),new TransportRequest('GET',StackCpLaunchTransport::url('brand'),$auth+['Cookie'=>'PRIVATE_COOKIE'])),InvalidArgumentException::class);
    sl_refuse('Unsafe entire DNS answer set refuses',fn()=>StackCpLaunchTransport::resolve('brand',fn()=>[['type'=>'A','ip'=>'8.8.8.8'],['type'=>'A','ip'=>'127.0.0.1']]),InvalidArgumentException::class);
    sl_refuse('Private pinned address refuses',fn()=>new StackCpLaunchTransport('brand','127.0.0.1',fn()=>null),InvalidArgumentException::class);
    foreach (['{"grant_type":"client_credentials","scope":"stack-user:97","scope":"stack-user:97"}',
        '{"grant_type":"client_credentials","scope":"stack-user:97<interactive>"}',
        '{"grant_type":"refresh_token","scope":"stack-user:97"}'] as $i=>$body)
        sl_refuse('Unsupported auth body refuses '.$i,fn()=>$validate->invoke(new StackCpLaunchTransport('token','8.8.8.8',fn()=>null),new TransportRequest('POST',StackCpLaunchTransport::url('token'),$auth+['Content-Type'=>'application/json'],$body)),InvalidArgumentException::class);
    foreach (['{"brand":{},"url":"https://evil.invalid"}','{"brand":{"brandDomain":"*"}}','{"brand":{"x":1,"x":2}}'] as $i=>$body)
        sl_refuse('Unsupported session body refuses '.$i,fn()=>$validate->invoke(new StackCpLaunchTransport('session','8.8.8.8',fn()=>null),new TransportRequest('POST',StackCpLaunchTransport::url('session'),$auth+['Content-Type'=>'application/json'],$body)),InvalidArgumentException::class);

    $GLOBALS['sl_curl_calls']=0;
    foreach (['brand','token','session'] as $phase) {
        $body=match($phase){'brand'=>null,'token'=>'{"grant_type":"client_credentials","scope":"stack-user:97"}','session'=>'{"brand":{}}'};
        $headers=$body===null?$auth:$auth+['Content-Type'=>'application/json']; $calls=0;
        $response=(new StackCpLaunchTransport($phase,'8.8.8.8',function() use (&$calls): void {$calls++;}))->send(new TransportRequest($body===null?'GET':'POST',StackCpLaunchTransport::url($phase),$headers,$body));
        $o=$GLOBALS['sl_curl_options']; [, $host,$port]=StackCpLaunchTransport::endpoint($phase);
        sl_ok('Actual curl options pin exact phase host and port '.$phase,$o[CURLOPT_RESOLVE]===[$host.':'.$port.':8.8.8.8']);
        sl_ok('Actual curl options require TLS and prevent redirects '.$phase,$o[CURLOPT_SSL_VERIFYPEER]===true && $o[CURLOPT_SSL_VERIFYHOST]===2 && $o[CURLOPT_FOLLOWLOCATION]===false && $o[CURLOPT_MAXREDIRS]===0 && $o[CURLOPT_PROTOCOLS]===CURLPROTO_HTTPS);
        sl_ok('Actual curl options disable proxy netrc and reusable connections '.$phase,$o[CURLOPT_PROXY]==='' && $o[CURLOPT_PRE_PROXY]==='' && $o[CURLOPT_NETRC]===CURL_NETRC_IGNORED && $o[CURLOPT_FRESH_CONNECT]===true && $o[CURLOPT_FORBID_REUSE]===true);
        sl_ok('Actual curl method enforces bounded deadlines '.$phase,$o[CURLOPT_CONNECTTIMEOUT_MS]===5000 && $o[CURLOPT_TIMEOUT_MS]===15000 && $o[CURLOPT_LOW_SPEED_TIME]===5);
        sl_ok('Actual curl method rechecks authority and discards headers '.$phase,$calls===3 && $response->headers===[]);
        sl_ok('Actual curl options never activate or supply cookies '.$phase,!isset($o[CURLOPT_COOKIE],$o[CURLOPT_COOKIEFILE],$o[CURLOPT_COOKIEJAR]) && !array_key_exists(CURLOPT_COOKIE,$o) && !array_key_exists(CURLOPT_COOKIEFILE,$o) && !array_key_exists(CURLOPT_COOKIEJAR,$o));
    }
    $GLOBALS['sl_curl_response_by_url']=[StackCpLaunchTransport::url('brand')=>'{"brandName":"Observed"}',
        StackCpLaunchTransport::url('token')=>'{"access_token":"PRIVATE_NATIVE_TOKEN","token_type":"bearer","expires_in":60,"scope":"interactive"}',
        StackCpLaunchTransport::url('session')=>'{"session_id":"PRIVATE_NATIVE_SESSION"}'];
    $beforeCurl=$GLOBALS['sl_curl_calls']; $phases=[];
    $native=StackCpLaunchSender::launch(sl_context(),fn($role)=>$role==='general-api-key'?'PRIVATE_GENERAL_KEY':'PRIVATE_AUTH_KEY',fn()=>null,
        function($phase) use (&$phases): void {$phases[]='before:'.$phase;},function($phase) use (&$phases): void {$phases[]='after:'.$phase;},
        fn()=>[['type'=>'A','ip'=>'8.8.8.8']]);
    sl_ok('Default native sender invokes actual fixed transport for all three phases',$GLOBALS['sl_curl_calls']-$beforeCurl===3 && $phases===['before:brand','after:brand','before:token','after:token','before:session','after:session']);
    sl_ok('Default transport chain yields only one-use fixed destination',$native->consume(fn($url)=>$url==='https://www.stackcp.com/login/sso?PHPSESSID=PRIVATE_NATIVE_SESSION'));
    unset($GLOBALS['sl_curl_response_by_url']);
    $GLOBALS['sl_curl_body']=str_repeat('x',16385);
    sl_refuse('Actual curl body callback refuses overflow',fn()=>(new StackCpLaunchTransport('brand','8.8.8.8',fn()=>null))->send(new TransportRequest('GET',StackCpLaunchTransport::url('brand'),$auth)),TransportException::class);
    unset($GLOBALS['sl_curl_body']); $GLOBALS['sl_curl_header']=str_repeat('x',16385);
    sl_refuse('Actual curl header callback refuses overflow',fn()=>(new StackCpLaunchTransport('brand','8.8.8.8',fn()=>null))->send(new TransportRequest('GET',StackCpLaunchTransport::url('brand'),$auth)),TransportException::class);
    $completed=true;
} catch (Throwable $e) { $failure=get_class($e); }
$passed=$completed && !in_array(false,array_column($checks,'passed'),true);
echo json_encode(['passed'=>$passed,'scope'=>'Pure launch parser/sender and exact transport methods with offline DNS/HTTP/curl doubles; no provider, SQL or browser login.','checks'=>$checks,'count'=>count($checks),'failure'=>$failure??null],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
exit($passed?0:1);
}
