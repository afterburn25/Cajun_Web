<?php
declare(strict_types=1);
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot,RegistrationPeriod};
use CajunWebPro\Foundation\TwentyIInventoryReader\{InventoryReader,RegistrationPeriods};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
foreach(['HttpTransport','ProviderCredential','TransportRequest','TransportResponse','TransportException','ProviderAdapter'] as $class)require __DIR__.'/../../foundation/provider/'.$class.'.php';
foreach(['InventoryRead','InventoryPackage','InventoryResource','InventoryCloudServer','InventoryDomain','InventorySnapshot','InventoryProjector','RegistrationPeriod'] as $class)require __DIR__.'/../../foundation/twentyi-inventory/'.$class.'.php';
require __DIR__.'/../../foundation/twentyi-inventory-reader/RegistrationPeriods.php';require __DIR__.'/../../foundation/twentyi-inventory-reader/InventoryReader.php';require __DIR__.'/../../foundation/mail-transport/EndpointPolicy.php';require __DIR__.'/../../foundation/twentyi/TwentyIAdapter.php';require __DIR__.'/../../foundation/twentyi/FixedHttpTransport.php';
$checks=[];
function rp_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)fwrite(STDERR,'FAIL '.$name.PHP_EOL);}
function rp_deny(string $name,callable $f):void{try{$f();rp_ok($name,false);}catch(InvalidArgumentException){rp_ok($name,true);}}
function rp_project(string $body,int $status=200):InventorySnapshot{return RegistrationPeriods::project(new TransportResponse($status,[],$body),1700000000,'twenty-i|profile|domain.registration.periods');}
$s=rp_project('{".uk":[1,2,3,4,5,6,7,8,9,10]}');rp_ok('Exact documented map yields only observed registration years',$s->state==='complete'&&$s->packages[0]->tld==='.uk'&&$s->packages[0]->years===range(1,10)&&$s->coverage==='unknown'&&$s->observedAt===1700000000);
rp_ok('Complete empty object remains a real empty observation',rp_project('{}')->state==='complete'&&rp_project('{}')->packages===[]);
rp_ok('Explicit empty options remain known empty',rp_project('{".uk":[]}')->packages[0]->years===[]);
rp_ok('Option sets sort without inventing omitted years',rp_project('{".co.uk":[5,1,3]}')->packages[0]->years===[1,3,5]);
foreach(['[]','null','false','123','"PRIVATE_MARKER"','broken','{".uk":[1,]}'] as $i=>$body)rp_ok('Wrong map envelope or malformed JSON '.$i,rp_project($body)->state==='unavailable');
foreach(['{".uk":[1],".uk":[2]}','{".uk":[1],"\\u002euk":[2]}'] as $i=>$body)rp_ok('Duplicate decoded top-level keys refuse '.$i,rp_project($body)->reason==='malformed_body');
foreach(['uk','.UK','..uk','.bad_.uk','.-uk','.uk.','.https://bad','.'.str_repeat('a',64),'.é','.<script>','PRIVATE_MARKER',0] as $i=>$tld)rp_deny('Noncanonical extension '.$i,fn()=>RegistrationPeriod::fromItem($tld,[1]));
foreach([null,false,'1',(object)['0'=>1],[0],[-1],[1.0],[true],['1'],[1,1],[2147483648],array_fill(0,65,1),[2=>1]] as $i=>$years)rp_deny('Malformed or unbounded year list '.$i,fn()=>RegistrationPeriod::fromItem('.uk',$years));
rp_ok('Punycode and compound suffix keys remain exact',RegistrationPeriod::fromItem('.xn--p1ai',[1])->tld==='.xn--p1ai'&&RegistrationPeriod::fromItem('.co.uk',[1])->tld==='.co.uk');
$years=[1,3];$ref=&$years[0];$record=RegistrationPeriod::fromItem('.uk',$years);$ref=5;rp_ok('Accepted options detach caller references',$record->years===[1,3]);
$s=rp_project('{".uk":[1],".bad":["PRIVATE_MARKER"]}');rp_ok('Bad entry stays partial and discloses no body value',$s->state==='partial'&&$s->skipped===1&&count($s->packages)===1&&!str_contains(json_encode($s),'PRIVATE_MARKER'));
rp_ok('All malformed items never become empty complete',rp_project('{".uk":{"PRIVATE_MARKER":"SECRET"}}')->state==='partial');
foreach([401=>'authentication',403=>'authentication',429=>'rate_limited',500=>'remote_error',202=>'unexpected_status',204=>'unexpected_status',302=>'unexpected_status',404=>'unexpected_status'] as $status=>$reason)rp_ok('Finite redacted HTTP outcome '.$status,rp_project('PRIVATE_MARKER',$status)->reason===$reason);
rp_ok('Response byte bound refuses before decode',rp_project(str_repeat(' ',1048577))->reason==='response_limit');
$many=[];for($i=0;$i<4097;$i++)$many['.t'.$i]=[1];rp_ok('TLD count bound is explicit refusal',rp_project(json_encode($many))->reason==='response_limit');
final class PeriodTransport implements HttpTransport{public array $sent=[];public function __construct(private TransportResponse|Throwable $result){}public function send(TransportRequest $request):TransportResponse{$this->sent[]=$request;if($this->result instanceof Throwable)throw $this->result;return $this->result;}}
$calls=0;$key='SYNTHETIC_KEY';$credential=new ProviderCredential('twenty-i','profile',function()use(&$calls,$key):string{$calls++;return $key;});$t=new PeriodTransport(new TransportResponse(200,[],'{".uk":[1,3]}'));
$s=InventoryReader::read($t,$credential,InventoryRead::DOMAIN_REGISTRATION_PERIODS,null,1700000000);$request=$t->sent[0];
rp_ok('Existing authenticated reader makes one fixed bounded GET',$calls===1&&count($t->sent)===1&&$request->url==='https://api.20i.com/domain-period'&&$request->method==='GET'&&$request->body===null&&$request->timeoutSeconds===15.0&&$request->headers['Authorization']==='Bearer '.base64_encode($key)&&$s->scope==='twenty-i|profile|domain.registration.periods');
rp_ok('No credential leaks in observed data',!str_contains(json_encode($s),$key)&&!str_contains(json_encode($s),base64_encode($key)));
$validate=new ReflectionMethod(FixedHttpTransport::class,'validate');$fixed=new FixedHttpTransport('8.8.8.8',fn()=>null);$validate->invoke($fixed,$request);rp_ok('Fixed transport admits exact new read',true);
foreach(['https://api.20i.com/domain-period/','https://api.20i.com/domain-period?all=1','https://api.20i.com/domain-period/uk','https://evil.invalid/domain-period'] as $i=>$url)rp_deny('Similar arbitrary route refused '.$i,fn()=>$validate->invoke($fixed,new TransportRequest('GET',$url,$request->headers,null,15)));
rp_deny('GET body refused',fn()=>$validate->invoke($fixed,new TransportRequest('GET',$request->url,$request->headers,'{}',15)));
foreach([new TransportException('timeout',0,new RuntimeException('PRIVATE_MARKER')),new RuntimeException('PRIVATE_MARKER')] as $i=>$failure){$t=new PeriodTransport($failure);$s=InventoryReader::read($t,$credential,InventoryRead::DOMAIN_REGISTRATION_PERIODS,null,1700000000);rp_ok('Transport failure stays unavailable and redacted '.$i,$s->state==='unavailable'&&count($t->sent)===1&&!str_contains(json_encode($s),'PRIVATE_MARKER'));}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$passed,'scope'=>'Pure registration-period map and fixed authenticated reader; synthetic transport only','checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
