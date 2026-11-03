<?php
declare(strict_types=1);
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIPackagePhp\{PackagePhpVersionBinding,PackagePhpVersionObservation,PackagePhpVersion};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
foreach(['HttpTransport','ProviderCredential','TransportRequest','TransportResponse','TransportException','ProviderAdapter'] as $class)require __DIR__.'/../../foundation/provider/'.$class.'.php';
foreach(['PackagePhpVersionBinding','PackagePhpVersionObservation','PackagePhpVersion'] as $class)require __DIR__.'/../../foundation/twentyi-package-php/'.$class.'.php';
require __DIR__.'/../../foundation/mail-transport/EndpointPolicy.php';
require __DIR__.'/../../foundation/twentyi/TwentyIAdapter.php';
require __DIR__.'/../../foundation/twentyi/FixedHttpTransport.php';
$checks=[];
function ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)fwrite(STDERR,"FAIL $name\n");}
function rejects(string $name,callable $work):void{try{$work();ok($name,false);}catch(InvalidArgumentException){ok($name,true);}}
function binding_data():array{return ['profile_id'=>str_repeat('a',32),'profile_revision'=>2,'key_fingerprint'=>str_repeat('b',64),'credential_sha256'=>str_repeat('3',64),'package_id'=>123,'client_id'=>4,'link_id'=>str_repeat('c',32),'link_sha256'=>str_repeat('d',64),'event_id'=>5,'event_sha256'=>str_repeat('e',64),'audit_id'=>6,'audit_sha256'=>str_repeat('f',64),'source_id'=>str_repeat('1',32),'source_seq'=>7,'source_sha256'=>str_repeat('2',64)];}
function project(string $body,int $status=200):PackagePhpVersionObservation{return PackagePhpVersion::project(new TransportResponse($status,[],$body),new PackagePhpVersionBinding(binding_data()),1700000000);}
final class PhpVersionTransport implements HttpTransport{
    public array $sent=[];
    public function __construct(private TransportResponse|Throwable $response){}
    public function send(TransportRequest $request):TransportResponse{$this->sent[]=$request;if($this->response instanceof Throwable)throw $this->response;return $this->response;}
}
$data=binding_data();$binding=new PackagePhpVersionBinding($data);
ok('Canonical binding is independent of input key order',(new PackagePhpVersionBinding(array_reverse($data,true)))->json===$binding->json);
ok('Scope binds exact profile and complete context',$binding->scope==='twenty-i|'.str_repeat('a',32).'|package.php-version|'.hash('sha256',$binding->json));
$data['package_id']=999;ok('Binding is detached from caller changes',$binding->data['package_id']===123);
foreach(binding_data() as $key=>$value){$data=binding_data();$data[$key]=is_int($value)?$value+1:str_repeat($value[0]==='9'?'8':'9',strlen($value));ok('Binding witness covers '.$key,(new PackagePhpVersionBinding($data))->sha256!==$binding->sha256);}
$data=binding_data();$data['extra']='untrusted';rejects('Unknown binding keys refused',fn()=>new PackagePhpVersionBinding($data));
$data=binding_data();unset($data['audit_id']);rejects('Missing audit identity refused',fn()=>new PackagePhpVersionBinding($data));
foreach([0,-1,true,1.5,'123',1000000000000000000] as $i=>$bad){$data=binding_data();$data['package_id']=$bad;rejects('Invalid allocated package '.$i,fn()=>new PackagePhpVersionBinding($data));}
$descriptor=PackagePhpVersion::request($binding);ok('Only exact GET path and no body',$descriptor->method==='GET'&&$descriptor->url==='https://api.20i.com/package/123/web/phpVersion'&&$descriptor->body===null&&$descriptor->timeoutSeconds===15.0&&$descriptor->headers===['Accept'=>'application/json']);
foreach(['php82','PHP-custom','next version','<literal>','php82/variant'] as $value){$r=project(json_encode($value,JSON_THROW_ON_ERROR));ok('Exact scalar string retained '.$value,$r->state==='complete'&&$r->value===$value&&$r->observedAt===1700000000&&$r->binding->sha256===$binding->sha256);}
foreach(['null','false','82','8.2','[]','["php82"]','{"value":"php82"}','{"version":"php82"}','{"result":"php82"}'] as $i=>$body){$r=project($body);ok('No guessed shape '.$i,$r->state==='unavailable'&&$r->reason==='unexpected_shape'&&$r->value===null);}
foreach(['php82','"unterminated','"php82" trailing','',"\xff"] as $i=>$body)ok('Malformed JSON refused '.$i,project($body)->reason==='malformed_body');
foreach(['','   ',"php82\n",'php'.chr(0),str_repeat('x',129)] as $i=>$bad){$r=project(json_encode($bad));ok('Defensive text bound '.$i,$r->reason==='invalid_value'&&$r->value===null);}
ok('Response capacity bounded',project(str_repeat(' ',4097))->reason==='response_limit');
foreach([401=>'authentication',403=>'authentication',429=>'rate_limited',500=>'remote_error',302=>'unexpected_status',201=>'unexpected_status'] as $status=>$reason){$r=project('"php82"',$status);ok('Status refusal '.$status,$r->reason===$reason&&$r->value===null);}
rejects('Complete observation requires a value',fn()=>new PackagePhpVersionObservation($binding,0,'complete','',null));
rejects('Unavailable observation cannot carry a value',fn()=>new PackagePhpVersionObservation($binding,0,'unavailable','authentication','php82'));
rejects('Scalar observation cannot claim partial coverage',fn()=>new PackagePhpVersionObservation($binding,0,'partial','',null));
$resolved=0;$credential=new ProviderCredential('twenty-i',str_repeat('a',32),function()use(&$resolved):string{$resolved++;return 'synthetic-private-php-key';});
$transport=new PhpVersionTransport(new TransportResponse(200,[],'"php82"'));$r=PackagePhpVersion::read($transport,$credential,$binding,1700000000);
ok('One authenticated bounded request',$resolved===1&&count($transport->sent)===1&&$transport->sent[0]->headers===['Authorization'=>'Bearer '.base64_encode('synthetic-private-php-key'),'Accept'=>'application/json']&&$r->value==='php82');
ok('Result retains no credential or raw response',!str_contains(json_encode($r),'synthetic-private-php-key')&&!property_exists($r,'body'));
$foreign=new ProviderCredential('twenty-i',str_repeat('9',32),function()use(&$resolved):string{$resolved++;return 'foreign';});rejects('Foreign account refused before secret',fn()=>PackagePhpVersion::read($transport,$foreign,$binding,1700000000));ok('Foreign read made no request',$resolved===1&&count($transport->sent)===1);
$secret='synthetic-private-php-key';$encoded=base64_encode($secret);
$echoBodies=['raw'=>json_encode($secret),'raw with surrounding text'=>json_encode('prefix '.$secret.' suffix'),'Base64'=>json_encode($encoded),'Bearer with surrounding text'=>json_encode('prefix Bearer '.$encoded.' suffix'),'JSON escaped raw'=>'"prefix \\u0073'.substr($secret,1).' suffix"','JSON escaped Base64'=>'"prefix \\u0063'.substr($encoded,1).' suffix"'];
foreach($echoBodies as $label=>$body){$t=new PhpVersionTransport(new TransportResponse(200,[],$body));$r=PackagePhpVersion::read($t,$credential,$binding,1700000000);ok('Credential echo refused after JSON decoding: '.$label,count($t->sent)===1&&$r->state==='unavailable'&&$r->reason==='invalid_value'&&$r->value===null&&$r->scope===$binding->scope);$serialized=json_encode($r);ok('Credential echo result is redacted: '.$label,!str_contains($serialized,$secret)&&!str_contains($serialized,$encoded));}
foreach(['connect','tls','timeout','protocol','unavailable','unknown'] as $kind){$t=new PhpVersionTransport(new TransportException($kind));$r=PackagePhpVersion::read($t,$credential,$binding,1700000000);ok('No retry for transport '.$kind,count($t->sent)===1&&$r->reason==='transport_'.$kind&&$r->value===null&&$r->scope===$binding->scope);}
$fixed=new FixedHttpTransport('8.8.8.8',fn()=>null);$validate=new ReflectionMethod($fixed,'validate');$headers=['Authorization'=>'Bearer '.base64_encode('synthetic-key'),'Accept'=>'application/json'];
$validate->invoke($fixed,new TransportRequest('GET',$descriptor->url,$headers));ok('Fixed transport admits exact PHP observation path',true);
foreach(['0','01','-1','1e2','1000000000000000000','%31','1/../2','1?x=1'] as $i=>$id)rejects('Transport package identity/path refusal '.$i,fn()=>$validate->invoke($fixed,new TransportRequest('GET','https://api.20i.com/package/'.$id.'/web/phpVersion',$headers)));
foreach(['https://api.20i.com/package/123/web/phpVersion?x=1','https://api.20i.com/package/123/web/phpVersion/','https://api.20i.com/package/123/web/phpVersions','https://api.20i.com/package/123/web/PHPVersion','https://other.invalid/package/123/web/phpVersion'] as $i=>$url)rejects('Transport path widening refusal '.$i,fn()=>$validate->invoke($fixed,new TransportRequest('GET',$url,$headers)));
rejects('PHP GET body refused',fn()=>$validate->invoke($fixed,new TransportRequest('GET',$descriptor->url,$headers,'{}')));
rejects('PHP POST refuses a malformed setter value',fn()=>$validate->invoke($fixed,new TransportRequest('POST',$descriptor->url,$headers+['Content-Type'=>'application/json'],'{"value":82}')));
$validate->invoke($fixed,new TransportRequest('POST',$descriptor->url,$headers+['Content-Type'=>'application/json'],'{"value":"php82"}'));ok('Exact PHP setter wire is admitted separately from read authority',true);
ok('PHP observation descriptor remains a bodyless GET',$descriptor->method==='GET'&&$descriptor->body===null);
$failed=count(array_filter($checks,fn($r)=>!$r['passed']));echo json_encode(['passed'=>$failed===0,'scope'=>'Pure typed scalar parsing and fixed transport validation; no network, persistence or provider entitlement.','checks'=>$checks],JSON_PRETTY_PRINT),"\n";fwrite(STDERR,count($checks)." checks; $failed failed\n");exit($failed?1:0);
