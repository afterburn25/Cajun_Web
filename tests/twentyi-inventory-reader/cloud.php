<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryCloudServer,InventoryRead,InventorySnapshot,InventoryProjector};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;

foreach(['HttpTransport','ProviderCredential','TransportRequest','TransportResponse','TransportException'] as $class) require __DIR__.'/../../foundation/provider/'.$class.'.php';
foreach(['InventoryRead','InventoryPackage','InventoryResource','InventoryCloudServer','InventorySnapshot','InventoryProjector'] as $class) require __DIR__.'/../../foundation/twentyi-inventory/'.$class.'.php';
require __DIR__.'/../../foundation/twentyi-inventory-reader/InventoryReader.php';
require __DIR__.'/../../foundation/mail-transport/EndpointPolicy.php';
require __DIR__.'/../../foundation/provider/ProviderAdapter.php';
require __DIR__.'/../../foundation/twentyi/TwentyIAdapter.php';
require __DIR__.'/../../foundation/twentyi/FixedHttpTransport.php';

$checks=[];
function ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)fwrite(STDERR,"FAIL: $name\n");}
function reject(string $name,callable $work):void {try{$work();ok($name,false);}catch(InvalidArgumentException){ok($name,true);}}
function cloud_item():stdClass {return json_decode('{"id":1111,"name":"cloud.synthetic.invalid","location":"dc_location-uk","productSpec":"cloud-20i","configuration":{"CpuCores":1,"RamMb":1024,"OsDiskSizeGb":25,"DataDiskSizeGb":0,"Provider":"20i","Zone":"20i","Country":"GB","VpsOsId":1,"spec":"micro"},"packageIds":[45,46],"count":"INF","cpanelInfo":{"password":"NEVER_RETAIN"},"isManaged":1,"unknown":"NEVER_RETAIN"}');}
function project(string $body,int $status=200):InventorySnapshot {return InventoryProjector::cloudServersList(new TransportResponse($status,[],$body),1700000000,'twenty-i|profile-a|cloudservers.list');}
final class CloudTransport implements HttpTransport {
    public array $sent=[];
    public function __construct(private TransportResponse|Throwable $result){}
    public function send(TransportRequest $request):TransportResponse {$this->sent[]=$request;if($this->result instanceof Throwable)throw $this->result;return $this->result;}
}
$i=cloud_item();$r=InventoryCloudServer::fromItem($i);
ok('Documented cloud fields retain original units',$r->id===1111&&$r->name==='cloud.synthetic.invalid'&&$r->configuration['RamMb']===1024&&$r->configuration['OsDiskSizeGb']===25&&$r->configuration['DataDiskSizeGb']===0&&$r->packageIds===[45,46]);
ok('Ambiguous flags and secrets are not retained',!str_contains(json_encode($r),'NEVER_RETAIN')&&!str_contains(json_encode($r),'INF')&&!property_exists($r,'isManaged')&&$r->unmappedCount===4);
$i->configuration->RamMb=999;$i->packageIds[0]=99;ok('Projection is detached from input objects',$r->configuration['RamMb']===1024&&$r->packageIds===[45,46]);
$minimal=InventoryCloudServer::fromItem((object)['id'=>'27']);ok('Missing optional facts remain unknown',$minimal->id===27&&$minimal->configuration===null&&$minimal->packageIds===null&&$minimal->name===null);
$i=cloud_item();$i->id='999999999999999999';$i->configuration->CpuCores='4';$i->packageIds=['45','46'];$r=InventoryCloudServer::fromItem($i);ok('Decimal strings normalize without floating point',$r->id===999999999999999999&&$r->configuration['CpuCores']===4&&$r->packageIds===[45,46]);
foreach([null,0,-1,true,1.5,'1e3','01',' 1','1000000000000000000',PHP_INT_MAX] as $n=>$bad){$i=cloud_item();$i->id=$bad;reject('Invalid allocated identity '.$n,fn()=>InventoryCloudServer::fromItem($i));}
foreach(['RamMb'=>0,'CpuCores'=>-1,'OsDiskSizeGb'=>1.5,'DataDiskSizeGb'=>'INF','VpsOsId'=>true] as $field=>$bad){$i=cloud_item();$i->configuration->$field=$bad;reject('Invalid numeric configuration '.$field,fn()=>InventoryCloudServer::fromItem($i));}
foreach([[],[1],false,'raw'] as $n=>$bad){$i=cloud_item();$i->configuration=$bad;reject('Configuration must be an object '.$n,fn()=>InventoryCloudServer::fromItem($i));}
foreach([['id'=>3],new stdClass(),[0],[1,1],[1,'1'],array_fill(0,4097,1)] as $n=>$bad){$i=cloud_item();$i->packageIds=$bad;reject('Invalid cloud package identities '.$n,fn()=>InventoryCloudServer::fromItem($i));}
foreach(['name','location','productSpec'] as $field){foreach([false,[],"bad\ntext",str_repeat('x',254),"\xff"] as $n=>$bad){$i=cloud_item();$i->$field=$bad;reject('Invalid text '.$field.' '.$n,fn()=>InventoryCloudServer::fromItem($i));}}
$i=cloud_item();$i->configuration->unknown=(object)['secret'=>'NEVER_RETAIN'];$r=InventoryCloudServer::fromItem($i);ok('Unknown nested fields are counted without raw values',$r->unmappedCount===5&&!str_contains(json_encode($r),'NEVER_RETAIN'));
$s=project(json_encode([cloud_item()]));ok('Cloud response complete means only returned items validated',$s->state==='complete'&&$s->coverage==='unknown'&&$s->scope==='twenty-i|profile-a|cloudservers.list'&&count($s->packages)===1&&$s->packages[0] instanceof InventoryCloudServer);
$s=project(json_encode([cloud_item(),cloud_item(),(object)['id'=>false]]));ok('Duplicate and malformed rows produce partial retained evidence',$s->state==='partial'&&$s->skipped===2&&count($s->packages)===1&&count($s->skipReasons)===2);
$s=project('[]');ok('Empty list differs from unavailable',$s->state==='complete'&&$s->packages===[]&&$s->coverage==='unknown');
foreach(['{}','null','false','{"0":{"id":1}}','{"result":[]}'] as $n=>$body){$s=project($body);ok('Non-list envelope refuses observation '.$n,$s->state==='unavailable'&&$s->reason==='unexpected_shape'&&$s->packages===[]);}
ok('Malformed JSON is unavailable',project('[')->reason==='malformed_body');
ok('Oversized body is unavailable',project(str_repeat(' ',1048577))->reason==='response_limit');
ok('Oversized collection is unavailable',project(json_encode(array_fill(0,8193,(object)['id'=>1])))->reason==='response_limit');
foreach([401=>'authentication',403=>'authentication',429=>'rate_limited',503=>'remote_error',202=>'unexpected_status'] as $status=>$reason)ok('HTTP status '.$status,project('[]',$status)->reason===$reason);
$resolved=0;$credential=new ProviderCredential('twenty-i','profile-a',function()use(&$resolved):string{$resolved++;return 'cloud-synthetic-secret';});
$t=new CloudTransport(new TransportResponse(200,[],json_encode([cloud_item()])));$s=InventoryReader::read($t,$credential,InventoryRead::CLOUD_SERVERS_LIST,null,1700000000);
ok('Existing reader sends one exact bounded cloud GET',$resolved===1&&count($t->sent)===1&&$t->sent[0]->method==='GET'&&$t->sent[0]->url==='https://api.20i.com/cloud_server'&&$t->sent[0]->body===null&&$t->sent[0]->timeoutSeconds===15.0&&$s->scope==='twenty-i|profile-a|cloudservers.list');
ok('Credential only appears in outgoing authorization',!str_contains(json_encode($s),'cloud-synthetic-secret')&&$t->sent[0]->headers===['Authorization'=>'Bearer '.base64_encode('cloud-synthetic-secret'),'Accept'=>'application/json']);
foreach(['vps.start','vps.vnc','cloudservers.delete','https://untrusted.invalid'] as $op){$before=$resolved;reject('No reader authority for '.$op,fn()=>InventoryReader::read($t,$credential,$op,null,1700000000));ok('Refusal precedes credentials and transport '.$op,$resolved===$before&&count($t->sent)===1);}
$t=new CloudTransport(new TransportException('timeout'));$s=InventoryReader::read($t,$credential,InventoryRead::CLOUD_SERVERS_LIST,null,1700000000);ok('Cloud timeout preserves operation scope without retry',$s->reason==='transport_timeout'&&str_ends_with($s->scope,'cloudservers.list')&&count($t->sent)===1);
$fixed=new FixedHttpTransport('8.8.8.8',fn()=>null);$validate=new ReflectionMethod($fixed,'validate');
$headers=['Authorization'=>'Bearer '.base64_encode('synthetic-key'),'Accept'=>'application/json'];
$validate->invoke($fixed,new TransportRequest('GET','https://api.20i.com/cloud_server',$headers,null,15.0));ok('Fixed transport admits exact cloud list read',true);
foreach(['https://api.20i.com/cloud_server/1','https://api.20i.com/cloud_server?x=1','https://api.20i.com/vps/1/start','https://other.invalid/cloud_server','http://api.20i.com/cloud_server','https://api.20i.com/cloud_server/../package'] as $n=>$url)reject('Transport refuses cloud path widening '.$n,fn()=>$validate->invoke($fixed,new TransportRequest('GET',$url,$headers,null,15.0)));
reject('Cloud GET cannot carry a body',fn()=>$validate->invoke($fixed,new TransportRequest('GET','https://api.20i.com/cloud_server',$headers,'{}',15.0)));
reject('Cloud POST remains unavailable',fn()=>$validate->invoke($fixed,new TransportRequest('POST','https://api.20i.com/cloud_server',$headers,'{}',15.0)));
$failed=count(array_filter($checks,fn($c)=>!$c['passed']));echo json_encode(['passed'=>$failed===0,'scope'=>'Synthetic cloud projection, existing reader and fixed transport validation. No live provider call or persistence.','checks'=>$checks],JSON_PRETTY_PRINT),"\n";fwrite(STDERR,count($checks)." checks, $failed failed\n");exit($failed?1:0);
