<?php
declare(strict_types=1);
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryDomain,InventoryRead,InventorySnapshot,InventoryProjector};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
foreach(['HttpTransport','ProviderCredential','TransportRequest','TransportResponse','TransportException'] as $class)require __DIR__.'/../../foundation/provider/'.$class.'.php';
foreach(['InventoryRead','InventoryPackage','InventoryResource','InventoryCloudServer','InventoryDomain','InventorySnapshot','InventoryProjector'] as $class)require __DIR__.'/../../foundation/twentyi-inventory/'.$class.'.php';
require __DIR__.'/../../foundation/twentyi-inventory-reader/InventoryReader.php';require __DIR__.'/../../foundation/mail-transport/EndpointPolicy.php';require __DIR__.'/../../foundation/provider/ProviderAdapter.php';require __DIR__.'/../../foundation/twentyi/TwentyIAdapter.php';require __DIR__.'/../../foundation/twentyi/FixedHttpTransport.php';
$checks=[];
function ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)fwrite(STDERR,'FAIL '.$name.PHP_EOL);}
function deny(string $name,callable $f):void{try{$f();ok($name,false);}catch(InvalidArgumentException){ok($name,true);}}
function item():stdClass{return (object)['id'=>123,'name'=>'registered.synthetic.invalid','contact'=>['email'=>'PRIVATE_MARKER'],'authCode'=>'PRIVATE_MARKER','PRIVATE_MARKER'=>'PRIVATE_MARKER'];}
function project(string $body,int $status=200):InventorySnapshot{return InventoryProjector::domainsList(new TransportResponse($status,[],$body),1700000000,'twenty-i|profile|domains.list');}
$d=InventoryDomain::fromItem(item());ok('Only documented registrar id and name retained',$d->id===123&&$d->name==='registered.synthetic.invalid'&&$d->unmappedCount===3&&!str_contains(json_encode($d),'PRIVATE_MARKER'));
foreach([0,-1,1.5,true,null,[],'01','1e3',' 1','1000000000000000000',PHP_INT_MAX] as $n=>$bad){$i=item();$i->id=$bad;deny('Invalid allocated identity '.$n,fn()=>InventoryDomain::fromItem($i));}
foreach(['',false,null,[],"a.invalid\n",'A.invalid','a..invalid','a.invalid.','-a.invalid','a_.invalid','a','127.0.0.1','https://a.invalid','a.invalid/path','a.invalid:80',str_repeat('a',64).'.invalid',str_repeat('a.',128).'invalid','café.invalid'] as $n=>$bad){$i=item();$i->name=$bad;deny('Invalid canonical domain '.$n,fn()=>InventoryDomain::fromItem($i));}
foreach([[],false,null,'raw'] as $n=>$bad)deny('Non-object domain '.$n,fn()=>InventoryDomain::fromItem($bad));
$i=item();$i->id='999999999999999999';ok('Allocated numeric string remains exact',InventoryDomain::fromItem($i)->id===999999999999999999);
$s=project(json_encode([item()]));ok('Complete means returned items only; coverage stays unknown',$s->state==='complete'&&$s->coverage==='unknown'&&$s->packages[0] instanceof InventoryDomain);
ok('Known empty response remains empty observation',project('[]')->state==='complete'&&project('[]')->packages===[]);
foreach(['{}','null','false','{"0":{"id":1,"name":"a.invalid"}}','{"result":[]}','broken'] as $n=>$body)ok('Malformed or undocumented envelope '.$n,project($body)->state==='unavailable');
$i=item();$j=item();$j->id=124;ok('Duplicate name is partial',project(json_encode([$i,$j]))->state==='partial');$j->name='different.invalid';$j->id=123;ok('Duplicate id is partial',project(json_encode([$i,$j]))->state==='partial');ok('Invalid entry cannot fabricate empty success',project('[{}]')->state==='partial');
foreach([401=>'authentication',403=>'authentication',429=>'rate_limited',500=>'remote_error',202=>'unexpected_status',204=>'unexpected_status',302=>'unexpected_status'] as $status=>$reason)ok('HTTP classification '.$status,project('PRIVATE_MARKER',$status)->reason===$reason);
ok('Body bound enforced',project(str_repeat(' ',1048577))->reason==='response_limit');ok('Item count bound enforced',project(json_encode(array_fill(0,8193,(object)['id'=>1,'name'=>'a.invalid'])))->reason==='response_limit');
final class RegistrarTransport implements HttpTransport{public array $sent=[];public function __construct(private TransportResponse|Throwable $result){}public function send(TransportRequest $request):TransportResponse{$this->sent[]=$request;if($this->result instanceof Throwable)throw $this->result;return $this->result;}}
$resolved=0;$c=new ProviderCredential('twenty-i','profile',function()use(&$resolved):string{$resolved++;return 'SYNTHETIC_KEY';});$t=new RegistrarTransport(new TransportResponse(200,[],json_encode([item()])));$s=InventoryReader::read($t,$c,InventoryRead::DOMAINS_LIST,null,1700000000);$r=$t->sent[0];
ok('Existing reader sends exactly one bounded fixed registrar GET',$resolved===1&&count($t->sent)===1&&$r->url==='https://api.20i.com/domain'&&$r->method==='GET'&&$r->body===null&&$r->timeoutSeconds===15.0&&$s->scope==='twenty-i|profile|domains.list');
ok('No response secrets survive',!str_contains(json_encode($s),'PRIVATE_MARKER')&&!str_contains(json_encode($s),'SYNTHETIC_KEY'));
$validate=new ReflectionMethod(FixedHttpTransport::class,'validate');$fixed=new FixedHttpTransport('8.8.8.8',fn()=>null);$validate->invoke($fixed,$r);ok('Fixed transport admits exact registrar GET',true);
foreach(['https://api.20i.com/domain/','https://api.20i.com/domain?all=1','https://api.20i.com/domain/123/authCode','https://api.20i.com/domain/123/nameservers/extra','https://evil.invalid/domain'] as $n=>$url)deny('Other domain endpoints stay excluded '.$n,fn()=>$validate->invoke($fixed,new TransportRequest('GET',$url,$r->headers,null,15)));
$t=new RegistrarTransport(new RuntimeException('PRIVATE_MARKER'));$s=InventoryReader::read($t,$c,InventoryRead::DOMAINS_LIST,null,1700000000);ok('Unknown transport errors remain redacted',$s->reason==='transport_unknown'&&!str_contains(json_encode($s),'PRIVATE_MARKER'));
$passed=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$passed,'scope'=>'Synthetic registrar reader and fixed transport admission; no network or native database','checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
