<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
foreach (['HttpTransport','TransportRequest','TransportResponse','TransportException','ProviderCredential'] as $class) require_once __DIR__.'/../foundation/provider/'.$class.'.php';
foreach (['StackCpAccessBinding','StackCpAccessJson','StackCpAccessObservation','StackCpAccessRead'] as $class) require_once __DIR__.'/../foundation/twentyi-stackcp-access/'.$class.'.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,TransportException,ProviderCredential};
use CajunWebPro\Foundation\TwentyIStackCpAccess\{StackCpAccessBinding as B,StackCpAccessRead as R,StackCpAccessObservation as O};
$checks=[];
function sa_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];}
function sa_deny(string $name,callable $f):void {try {$f();sa_ok($name,false);}catch(InvalidArgumentException $e) {sa_ok($name,!str_contains($e->getMessage(),'PRIVATE_MARKER'));}}
function sa_data(string $op=B::MEMBERSHIP):array {return ['operation'=>$op,'profile_id'=>str_repeat('1',32),'profile_revision'=>1,'credential_sha256'=>str_repeat('2',64),'key_fingerprint'=>str_repeat('3',64),'package_id'=>2501,'client_id'=>9,'client_auth_version'=>str_repeat('4',32),'link_id'=>str_repeat('5',32),'link_sha256'=>str_repeat('6',64),'event_id'=>10,'event_sha256'=>str_repeat('7',64),'audit_id'=>11,'audit_sha256'=>str_repeat('8',64),'source_id'=>str_repeat('9',32),'source_seq'=>12,'source_sha256'=>str_repeat('a',64),'actor_id'=>1,'actor_auth_version'=>str_repeat('b',32),'assignment_id'=>str_repeat('c',32),'assignment_revision'=>2,'assignment_sha256'=>str_repeat('d',64),'assignment_proof'=>str_repeat('e',64),'auth_id'=>str_repeat('f',32),'auth_revision'=>1,'auth_sha256'=>str_repeat('1',64),'auth_proof'=>str_repeat('2',64),'auth_ciphertext_sha256'=>str_repeat('3',64),'stack_identity'=>'stack-user:123','membership_id'=>$op===B::MEMBERSHIP?'':str_repeat('4',32),'membership_seq'=>$op===B::MEMBERSHIP?0:13,'membership_sha256'=>$op===B::MEMBERSHIP?'':str_repeat('5',64)];}
function sa_restored(string $class,array $fields):object {$o=(new ReflectionClass($class))->newInstanceWithoutConstructor();foreach ($fields as $key=>$value)(new ReflectionProperty($class,$key))->setValue($o,$value);return $o;}
final class SaTransport implements HttpTransport {public int $calls=0;public ?TransportRequest $request=null;public function __construct(public string $body,public int $status=200,public ?Throwable $failure=null){}public function send(TransportRequest $request):TransportResponse {$this->calls++;$this->request=$request;if ($this->failure) throw $this->failure;return new TransportResponse($this->status,[],$this->body);}}
function sa_read(string $body,string $op=B::MEMBERSHIP,string $secret='SYNTHETIC_PRIVATE_MARKER',int $status=200,?Throwable $failure=null):O {$b=new B(sa_data($op));return R::read(new SaTransport($body,$status,$failure),new ProviderCredential('twenty-i',$b->data['profile_id'],fn()=>$secret),$b,1760000000);}
function sa_unavailable(string $name,string $body,string $reason,string $op=B::MEMBERSHIP):void {$o=sa_read($body,$op);sa_ok($name,$o->state==='unavailable'&&$o->reason===$reason&&$o->values===null&&!str_contains(json_encode($o),'PRIVATE_MARKER'));}
set_error_handler(static function(int $level,string $message):never {throw new ErrorException($message,0,$level);});
try {
    foreach ([B::MEMBERSHIP=>'/package/2501/stackUserList',B::GRANTS=>'/reseller/*/stackUser/123/grants'] as $op=>$path) {
        $b=new B(sa_data($op));$r=R::request($b);sa_ok('Exact read-only descriptor '.$op,$r->method==='GET'&&$r->url==='https://api.20i.com'.$path&&$r->headers===['Accept'=>'application/json']&&$r->body===null&&$r->timeoutSeconds===15.0);
        $t=new SaTransport($op===B::MEMBERSHIP?'[{"identity":"stack-user:123","email":"DISCARDED_PRIVATE_METADATA","unknown":{"x":[1,true,null]}}]':'["package:2501"]');$calls=0;
        $o=R::read($t,new ProviderCredential('twenty-i',$b->data['profile_id'],function()use(&$calls){$calls++;return 'SYNTHETIC_PRIVATE_MARKER';}),$b,1760000000);
        sa_ok('One General key resolution and one request '.$op,$calls===1&&$t->calls===1&&$t->request->headers===['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_PRIVATE_MARKER'),'Accept'=>'application/json']);
        sa_ok('Exact selected observation no login or metadata '.$op,$o->selectedMatch()&&$o->scope===$b->scope&&!str_contains(json_encode($o),'DISCARDED_PRIVATE_METADATA')&&$o->jsonSerialize()['login_ready']===false&&$o->jsonSerialize()['remote_scope_restricted']===false);
        $empty=sa_read('[]',$op);sa_ok('Explicit empty list never becomes match '.$op,$empty->state==='complete'&&$empty->values===[]&&!$empty->selectedMatch());
    }
    foreach (sa_data() as $key=>$value) {
        $d=sa_data();unset($d[$key]);sa_deny('Missing binding field '.$key,fn()=>new B($d));
        $d=sa_data();$d[$key]=null;sa_deny('Null binding field '.$key,fn()=>new B($d));
    }
    $d=sa_data();$d['extra']=1;sa_deny('Unknown binding field',fn()=>new B($d));
    foreach (['operation'=>'packages.list','package_id'=>'2501','profile_id'=>str_repeat('A',32),'stack_identity'=>'stack-user:01','membership_id'=>str_repeat('1',32),'membership_seq'=>1,'membership_sha256'=>str_repeat('a',64)] as $key=>$value) {$d=sa_data();$d[$key]=$value;sa_deny('Malformed binding value '.$key,fn()=>new B($d));}
    $d=sa_data(B::GRANTS);$d['membership_seq']=0;sa_deny('Grants must bind prior membership receipt',fn()=>new B($d));
    $id=2501;$d=sa_data();$d['package_id']=&$id;$b=new B($d);$id=9001;sa_ok('Constructed binding detaches caller scalar references',$b->data['package_id']===2501&&str_contains(R::request($b)->url,'/2501/'));
    $base=new B(sa_data());foreach (['json'=>'{}','sha256'=>str_repeat('0',64),'scope'=>'PRIVATE_MARKER'] as $key=>$v) {$fields=get_object_vars($base);$fields[$key]=$v;$o=sa_restored(B::class,$fields);sa_deny('Restored derived binding field refused '.$key,fn()=>R::request($o));}
    $restoredId=2501;$fields=get_object_vars($base);$fields['data']['package_id']=&$restoredId;$restored=sa_restored(B::class,$fields);$ob=new O($restored,1,'complete','',['stack-user:123']);$restoredId=999;sa_ok('Observation detaches restored reference binding',$ob->binding->data['package_id']===2501&&$ob->selectedMatch());
    $fields=get_object_vars($ob);$fields['scope']='forged';sa_deny('Restored observation scope refused',fn()=>sa_restored(O::class,$fields)->validated());
    foreach (['null','{}','true','123','"PRIVATE_MARKER"'] as $i=>$bad) sa_unavailable('Unsupported top-level shape '.$i,$bad,'unexpected_shape');
    foreach (['','[','[1,]','[NaN]','[1e400]',"[\"\xFF\"]",'[{"identity":"stack-user:123","identity":"stack-user:456"}]','[{"identity":"stack-user:123","\u0069dentity":"stack-user:456"}]','[{"identity":"stack-user:123","extra":{"k":1,"\u006b":2}}]','[{"identity":"stack-user:123","extra":"\uD800"}]','[]true'] as $i=>$bad) sa_unavailable('Malformed or duplicate JSON '.$i,$bad,'malformed_body');
    foreach (['["stack-user:123"]','[null]','[{}]','[{"identity":123}]','[{"identity":null}]'] as $i=>$bad) sa_unavailable('Membership entry shape '.$i,$bad,'unexpected_shape');
    foreach (['stack-user:0','stack-user:0123','stack-user:2147483648','stack-user:123/other','STACK-USER:123','stack-user:123 ','stack-user:123?x','PRIVATE_MARKER'] as $i=>$bad) sa_unavailable('Unsupported membership identity '.$i,json_encode([['identity'=>$bad]]),'invalid_value');
    sa_unavailable('Duplicate identity is not deduplicated','[{"identity":"stack-user:123"},{"identity":"stack-user:123"}]','invalid_value');
    $o=sa_read('[{"identity":"stack-user:456"},{"identity":"stack-user:123"}]');sa_ok('Select exact assigned identity never first',$o->selectedMatch()&&$o->values===['stack-user:456','stack-user:123']);
    $o=sa_read('[{"identity":"stack-user:456"}]');sa_ok('Absent selected identity remains observed non-match',$o->state==='complete'&&!$o->selectedMatch());
    foreach (['[1]','[null]','[{"grant":"package:2501"}]'] as $i=>$bad) sa_unavailable('Grant entry shape '.$i,$bad,'unexpected_shape',B::GRANTS);
    foreach (['package:0','package:02501','Package:2501','package:2501/other','package:*','package:2501 ','other:PRIVATE_MARKER'] as $i=>$bad) sa_unavailable('Unsupported grant tuple '.$i,json_encode([$bad]),'invalid_value',B::GRANTS);
    sa_unavailable('Duplicate grants refused','["package:2501","package:2501"]','invalid_value',B::GRANTS);
    foreach (['["package:2502"]','["package:2501","domain:3"]','["package:2501","server:5"]','["unknown-kind:1"]'] as $i=>$body) {$o=sa_read($body,B::GRANTS);sa_ok('Whole-user extra or unknown grant never match '.$i,$o->state==='complete'&&!$o->selectedMatch());}
    foreach ([[B::MEMBERSHIP,'123','[{"identity":"stack-user:123"}]'],[B::GRANTS,'2501','["package:2501"]'],[B::MEMBERSHIP,'123','[{"identity":"stack-user:\\u0031\\u0032\\u0033"}]'],[B::GRANTS,'jjj','["ampq:1"]'],[B::GRANTS,'jjj','["\\u0061mpq:1"]']] as $i=>[$op,$secret,$body]) {
        $o=sa_read($body,$op,$secret);sa_ok('Credential raw Base64 or escaped echo refuses all values '.$i,$o->state==='unavailable'&&$o->reason==='invalid_value'&&$o->values===null);
    }
    $o=sa_read('[{"identity":"stack-user:123","email":"SYNTHETIC_PRIVATE_MARKER"}]');sa_ok('Discarded private metadata never survives safe observation',$o->selectedMatch()&&!str_contains(json_encode($o),'PRIVATE_MARKER'));
    foreach ([401=>'authentication',403=>'authentication',429=>'rate_limited',500=>'remote_error',204=>'unexpected_status',302=>'unexpected_status',404=>'unexpected_status'] as $status=>$reason) {$o=sa_read('PRIVATE_MARKER',B::MEMBERSHIP,'SYNTHETIC_KEY',$status);sa_ok('Finite HTTP classification '.$status,$o->reason===$reason&&$o->values===null&&!str_contains(json_encode($o),'PRIVATE_MARKER'));}
    foreach (['connect','timeout','tls','protocol','unavailable','unknown'] as $kind) {$o=sa_read('',B::MEMBERSHIP,'SYNTHETIC_KEY',200,new TransportException($kind));sa_ok('Finite transport classification '.$kind,$o->reason===(in_array($kind,['connect','timeout','tls','protocol'],true)?'transport_'.$kind:'transport_unknown')&&$o->values===null);}
    $o=sa_read('',B::MEMBERSHIP,'SYNTHETIC_KEY',200,new RuntimeException('PRIVATE_MARKER'));sa_ok('Unclassified transport never leaks exception',$o->reason==='transport_unknown'&&!str_contains(json_encode($o),'PRIVATE_MARKER'));
    foreach ([['foreign',str_repeat('1',32)],['twenty-i',str_repeat('2',32)]] as $i=>[$provider,$account]) {$resolved=0;$t=new SaTransport('[]');sa_deny('Wrong provider/account before resolver '.$i,fn()=>R::read($t,new ProviderCredential($provider,$account,function()use(&$resolved){$resolved++;return 'secret';}),$base,1));sa_ok('No resolver or send for mismatch '.$i,$resolved===0&&$t->calls===0);}
    sa_unavailable('Response byte bound',str_repeat(' ',131073),'response_limit');
    sa_unavailable('List entry bound',json_encode(array_fill(0,1001,['identity'=>'stack-user:123'])),'malformed_body');
    $row=['identity'=>'stack-user:123'];for($i=0;$i<64;$i++)$row['extra'.$i]=null;sa_unavailable('Object member bound',json_encode([$row]),'malformed_body');
    $deep=null;for($i=0;$i<10;$i++)$deep=[$deep];sa_unavailable('Nested metadata depth bound',json_encode([['identity'=>'stack-user:123','extra'=>$deep]]),'malformed_body');
} catch(Throwable $e) {$checks[]=['name'=>'Unexpected: '.$e->getMessage(),'passed'=>false];}
restore_error_handler();$passed=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$passed,'scope'=>'Synthetic pure typed requests, duplicate-aware parsing, detached bindings and injected transport only; no network or native storage.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($passed?0:1);
