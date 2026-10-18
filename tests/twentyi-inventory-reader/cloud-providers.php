<?php
declare(strict_types=1);
use CajunWebPro\Foundation\TwentyIInventory\{CloudProviderList,InventoryCloudProvider};
require __DIR__.'/../../foundation/twentyi-inventory/CloudProviderList.php';

$checks=[];
function ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)fwrite(STDERR,"FAIL: $name\n");}
function reject(string $name,callable $work):void {try{$work();ok($name,false);}catch(InvalidArgumentException $e){ok($name,!str_contains($e->getMessage(),'PRIVATE_VALUE'));}}
function identifiers(array $records):array {return array_map(static fn(InventoryCloudProvider $r):string=>$r->identifier,$records);}

$input=['20icloud','aws','gcp'];$records=CloudProviderList::fromValues($input);
ok('Documented provider values become typed exact identifiers',identifiers($records)===$input);
ok('Provider observations contain only the identifier',array_keys(get_object_vars($records[0]))===['identifier']);
$input[0]='changed';ok('Projection is detached from caller mutation',$records[0]->identifier==='20icloud');
$item='aws';$input=[&$item];$records=CloudProviderList::fromValues($input);$item='changed';ok('Projection detaches caller-held references',$records[0]->identifier==='aws');
ok('Empty JSON list is a valid empty observed list',CloudProviderList::fromValues(json_decode('[]'))===[]);
ok('Exact case and observed ordering are retained',identifiers(CloudProviderList::fromValues(['gcp','AWS','aws']))===['gcp','AWS','aws']);
ok('Numeric-looking strings retain exact identity',identifiers(CloudProviderList::fromValues(['1','01']))===['1','01']);
ok('Conservative identifier boundary is accepted',InventoryCloudProvider::fromValue(str_repeat('a',128))->identifier===str_repeat('a',128));
ok('Documented identity is not a catalog enumeration',InventoryCloudProvider::fromValue('Future.Provider_2-x')->identifier==='Future.Provider_2-x');
foreach([null,true,false,1,1.0,'PRIVATE_VALUE',new stdClass(),['provider'=>'aws'],[1=>'aws'],json_decode('{}'),json_decode('{"0":"aws"}'),json_decode('{"result":["aws"]}')] as $i=>$value){reject('Non-list envelope is refused '.$i,fn()=>CloudProviderList::fromValues($value));}
foreach([null,true,false,1,1.5,[],(object)['name'=>'aws'],'',' aws','aws ','PRIVATE_VALUE\n',"PRIVATE_VALUE\n",'aws/gcp','https://PRIVATE_VALUE','../PRIVATE_VALUE','PRIVATE_VALUE?x=1','PRIVATE_VALUE#x',"PRIVATE_VALUE\0",'caf'."\xc3\xa9",'PRIVATE_VALUE'."\xff",str_repeat('a',129)] as $i=>$value){reject('Malformed or unsupported identifier is refused without raw value '.$i,fn()=>CloudProviderList::fromValues(['aws',$value]));}
reject('Duplicate provider refuses entire collection',fn()=>CloudProviderList::fromValues(['aws','gcp','aws']));
reject('Mixed valid and invalid collection cannot become a partial choice list',fn()=>CloudProviderList::fromValues(['aws',false,'gcp']));
$limit=array_map(static fn(int $n):string=>'provider-'.$n,range(1,256));ok('Local list capacity accepts its boundary',count(CloudProviderList::fromValues($limit))===256);
$limit[]='provider-257';reject('Local list capacity refuses overflow',fn()=>CloudProviderList::fromValues($limit));
$record=InventoryCloudProvider::fromValue('aws');try{$record->identifier='gcp';ok('Observed identifier is immutable',false);}catch(Error){ok('Observed identifier is immutable',$record->identifier==='aws');}

// Exercise the actual shared reader and fixed transport boundary using an injected transport.
foreach(['HttpTransport','ProviderCredential','TransportRequest','TransportResponse','TransportException'] as $class)require __DIR__.'/../../foundation/provider/'.$class.'.php';
foreach(['InventoryRead','InventoryPackage','InventoryResource','InventoryCloudServer','InventoryDomain','InventorySnapshot','InventoryProjector','InventoryCapability','InventoryRegister'] as $class)require __DIR__.'/../../foundation/twentyi-inventory/'.$class.'.php';
require __DIR__.'/../../foundation/twentyi-inventory-reader/InventoryReader.php';
require __DIR__.'/../../foundation/mail-transport/EndpointPolicy.php';require __DIR__.'/../../foundation/provider/ProviderAdapter.php';require __DIR__.'/../../foundation/twentyi/TwentyIAdapter.php';require __DIR__.'/../../foundation/twentyi/FixedHttpTransport.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot,InventoryProjector,InventoryRegister};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
function project(string $body,int $status=200):InventorySnapshot{return InventoryProjector::cloudProvidersList(new TransportResponse($status,[],$body),1700000000,'twenty-i|profile-a|cloud.providers.list');}
final class CloudProvidersTransport implements HttpTransport {
    public array $sent=[];public function __construct(private TransportResponse|Throwable $result){}
    public function send(TransportRequest $request):TransportResponse{$this->sent[]=$request;if($this->result instanceof Throwable)throw $this->result;return $this->result;}
}
$snapshot=project('["20icloud","aws","gcp"]');ok('Shared projector preserves exact typed records and unknown coverage',$snapshot->state==='complete'&&identifiers($snapshot->packages)===['20icloud','aws','gcp']&&$snapshot->coverage==='unknown'&&$snapshot->scope==='twenty-i|profile-a|cloud.providers.list');
ok('Empty returned array is complete with no account-wide completeness claim',project('[]')->state==='complete'&&project('[]')->packages===[]&&project('[]')->coverage==='unknown');
foreach(['{}','null','false','{"0":"aws"}','{"result":["aws"]}','["aws",false]','["aws","aws"]'] as $i=>$body){$result=project($body);ok('Shared projection refuses unsafe envelope or item '.$i,$result->state==='unavailable'&&$result->reason==='unexpected_shape'&&$result->packages===[]);}
ok('Invalid JSON is redacted unavailable',project('[')->reason==='malformed_body');
ok('Body byte bound enforced before decode',project(str_repeat(' ',1048577))->reason==='response_limit');
ok('Provider list capacity enforced before projection',project(json_encode(array_fill(0,257,'aws')))->reason==='response_limit');
foreach([401=>'authentication',403=>'authentication',429=>'rate_limited',503=>'remote_error',202=>'unexpected_status',302=>'unexpected_status',204=>'unexpected_status'] as $status=>$reason)ok('Shared HTTP status classification '.$status,project('[]',$status)->reason===$reason);
$resolved=0;$credential=new ProviderCredential('twenty-i','profile-a',function()use(&$resolved):string{$resolved++;return 'SYNTHETIC_CLOUD_PROVIDERS_SECRET';});
$transport=new CloudProvidersTransport(new TransportResponse(200,[],'["aws"]'));$snapshot=InventoryReader::read($transport,$credential,InventoryRead::CLOUD_PROVIDERS_LIST,null,1700000000);$request=$transport->sent[0];
ok('Existing executor sends one fixed bounded bodyless request',$resolved===1&&count($transport->sent)===1&&$request->method==='GET'&&$request->url==='https://api.20i.com/reseller/*/cloudProviders'&&$request->body===null&&$request->timeoutSeconds===15.0);
ok('Secret is present only on outgoing authorization',$request->headers===['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_CLOUD_PROVIDERS_SECRET'),'Accept'=>'application/json']&&!str_contains(json_encode($snapshot),'SYNTHETIC_CLOUD_PROVIDERS_SECRET'));
ok('Read registry and descriptor union remains exact',array_keys(InventoryRegister::all())===InventoryRead::operations()&&InventoryRegister::projectable(InventoryRead::CLOUD_PROVIDERS_LIST));
foreach(['cloud.providers.specs','vps.start','https://PRIVATE_VALUE'] as $op){$before=$resolved;reject('Unsupported operation refused '.$op,fn()=>InventoryReader::read($transport,$credential,$op,null,1700000000));ok('Unsupported operation never opens secret or sends '.$op,$resolved===$before&&count($transport->sent)===1);}
$transport=new CloudProvidersTransport(new TransportException('timeout'));$snapshot=InventoryReader::read($transport,$credential,InventoryRead::CLOUD_PROVIDERS_LIST,null,1700000000);ok('Timeout is one attempt with exact scope and no retry',$snapshot->reason==='transport_timeout'&&$snapshot->scope==='twenty-i|profile-a|cloud.providers.list'&&count($transport->sent)===1);
$fixed=new FixedHttpTransport('8.8.8.8',fn()=>null);$validate=new ReflectionMethod($fixed,'validate');$headers=['Authorization'=>'Bearer '.base64_encode('synthetic-key'),'Accept'=>'application/json'];
$validate->invoke($fixed,new TransportRequest('GET','https://api.20i.com/reseller/*/cloudProviders',$headers,null,15.0));ok('Fixed transport admits only the documented self read',true);
foreach(['https://api.20i.com/reseller/1/cloudProviders','https://api.20i.com/reseller/*/cloudProviders?provider=aws','https://api.20i.com/reseller/*/cloudProviders/','https://api.20i.com/reseller/*/cloudproviders','https://PRIVATE_VALUE/reseller/*/cloudProviders','http://api.20i.com/reseller/*/cloudProviders'] as $i=>$url)reject('Transport refuses selector or path widening '.$i,fn()=>$validate->invoke($fixed,new TransportRequest('GET',$url,$headers,null,15.0)));
reject('Provider discovery GET cannot carry a body',fn()=>$validate->invoke($fixed,new TransportRequest('GET','https://api.20i.com/reseller/*/cloudProviders',$headers,'{}',15.0)));
reject('Specs lookup POST is not silently enabled',fn()=>$validate->invoke($fixed,new TransportRequest('POST','https://api.20i.com/reseller/*/cloudProviders',$headers+['Content-Type'=>'application/json'],'{"provider":"aws"}',15.0)));

$failed=count(array_filter($checks,static fn(array $c):bool=>!$c['passed']));
echo json_encode(['passed'=>$failed===0,'scope'=>'Synthetic cloud-provider projection, shared reader and fixed transport validation; no live HTTP, persistence or account acceptance.','checks'=>$checks],JSON_PRETTY_PRINT),"\n";
fwrite(STDERR,count($checks)." checks, $failed failed\n");exit($failed?1:0);
