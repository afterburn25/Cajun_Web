<?php
/** Pure direct-catalog projection, fixed-request and draft contract; no network or database. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);
$base=dirname(__DIR__);
foreach(['HttpTransport','ProviderCredential','TransportRequest','TransportResponse','TransportException'] as $class)require_once $base.'/site/includes/foundation/provider/'.$class.'.php';
foreach(['InventoryRead','InventoryPackage','InventoryResource','InventoryCloudServer','InventoryDomain','InventoryPackageType','InventorySnapshot','InventoryProjector','InventoryCapability','InventoryRegister'] as $class)require_once $base.'/site/includes/foundation/twentyi-inventory/'.$class.'.php';
require_once $base.'/site/includes/foundation/twentyi-inventory-reader/PackageTypes.php';
require_once $base.'/site/includes/foundation/twentyi-inventory-reader/InventoryReader.php';
require_once $base.'/site/includes/foundation/mail-transport/EndpointPolicy.php';
require_once $base.'/site/includes/foundation/provider/ProviderAdapter.php';
require_once $base.'/site/includes/foundation/twentyi/TwentyIAdapter.php';
require_once $base.'/site/includes/foundation/twentyi/FixedHttpTransport.php';
require_once $base.'/site/app/providers/catalog_import_document.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryPackageType,InventoryRead,InventorySnapshot,InventoryRegister};
use CajunWebPro\Foundation\TwentyIInventoryReader\{PackageTypes,InventoryReader};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
$checks=[];
function pt_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function pt_deny(string $name,callable $call):void{try{$call();}catch(InvalidArgumentException $e){pt_ok($name,!str_contains($e->getMessage(),'PRIVATE_CATALOG'));return;}pt_ok($name,false);}
function pt_project(string $body,int $status=200):InventorySnapshot{return PackageTypes::project(new TransportResponse($status,[],$body),1700000000,'twenty-i|synthetic-profile|package.types');}
function pt_rows(InventorySnapshot $snapshot):array{return array_map(static fn($r)=>get_object_vars($r),$snapshot->packages);}
function pt_unavailable(string $name,string $body,string $reason='unexpected_shape',int $status=200):void{$s=pt_project($body,$status);pt_ok($name,$s->state==='unavailable'&&$s->packages===[]&&$s->skipped===0&&$s->skipReasons===[]&&$s->reason===$reason&&$s->coverage==='unknown'&&$s->scope==='twenty-i|synthetic-profile|package.types');}
final class PackageTypesTestTransport implements HttpTransport{public array $sent=[];public function __construct(private TransportResponse|Throwable $result){}public function send(TransportRequest $request):TransportResponse{$this->sent[]=$request;if($this->result instanceof Throwable)throw $this->result;return $this->result;}}
try{
    $wire=(object)['id'=>7001,'label'=>'Hébergement & care','platform'=>'PRIVATE_CATALOG_PLATFORM','image'=>'https://private.invalid/image','price'=>999,'private_customer_key'=>['PRIVATE_CATALOG_VALUE']];
    $record=InventoryPackageType::fromItem($wire);
    pt_ok('Catalog wire projection contains exactly canonical ID and observed label',get_object_vars($record)===['id'=>'7001','label'=>'Hébergement & care']);
    $wire->label='Changed';pt_ok('Catalog projection detaches mutable provider objects',$record->label==='Hébergement & care');
    try{$record->label='Changed';pt_ok('Catalog record is immutable',false);}catch(Error){pt_ok('Catalog record is immutable',$record->label==='Hébergement & care');}
    pt_ok('Catalog integer maximum and UTF8 byte boundary are retained',InventoryPackageType::fromItem((object)['id'=>999999999999999999,'label'=>str_repeat('é',60)])->id==='999999999999999999');
    foreach([true,false,null,0,-1,1.5,1.0,'1','01','1e3',1000000000000000000,[],new stdClass] as $i=>$id)pt_deny('Catalog wire identity rejects nonpositive noninteger or oversized value '.$i,fn()=>InventoryPackageType::fromItem((object)['id'=>$id,'label'=>'PRIVATE_CATALOG_LABEL']));
    foreach(['',"\t",' ',str_repeat('a',121),str_repeat('é',61),"Name\n","Name\0","\x7f","\xff",12,true,[],new stdClass] as $i=>$label)pt_deny('Catalog label rejects unsafe or unsupported value '.$i,fn()=>InventoryPackageType::fromItem((object)['id'=>1,'label'=>$label]));
    foreach([null,[],['id'=>1,'label'=>'A'],(object)['id'=>1],(object)['label'=>'A']] as $i=>$item)pt_deny('Catalog wire requires object and both fields '.$i,fn()=>InventoryPackageType::fromItem($item));
    pt_ok('Catalog label is preserved without HTML interpretation or invented fields',InventoryPackageType::fromItem((object)['id'=>1,'label'=>'<b>Local label</b>'])->label==='<b>Local label</b>');
    $saved=['id'=>'7001','label'=>'Hébergement & care'];pt_ok('Catalog saved record round trips exactly',get_object_vars(InventoryPackageType::fromStored($saved))===$saved);
    foreach([['id'=>7001,'label'=>'A'],['id'=>'07001','label'=>'A'],['id'=>'7001','label'=>'A','image'=>'PRIVATE_CATALOG'],['label'=>'A','id'=>'7001'],(object)$saved] as $i=>$row)pt_deny('Catalog stored representation refuses noncanonical record '.$i,fn()=>InventoryPackageType::fromStored($row));
    $valid='[{"id":7002,"label":"Catalog only"},{"id":1,"label":"Shared","image":"https://PRIVATE_CATALOG.invalid","price":999,"customer_key":{"value":"PRIVATE_CATALOG"}}]';
    $s=pt_project($valid);pt_ok('Catalog complete projection sorts exact records and retains unknown coverage',$s->state==='complete'&&pt_rows($s)===[['id'=>'1','label'=>'Shared'],['id'=>'7002','label'=>'Catalog only']]&&$s->observedAt===1700000000&&$s->coverage==='unknown'&&$s->skipped===0);
    pt_ok('Catalog unknown field names values prices and URLs never escape projection',!str_contains(json_encode($s,JSON_THROW_ON_ERROR),'PRIVATE_CATALOG')&&!str_contains(json_encode($s),'image')&&!str_contains(json_encode($s),'price')&&!str_contains(json_encode($s),'customer_key'));
    pt_ok('Catalog empty response is an honest complete zero-row observation',pt_project('[]')->state==='complete'&&pt_project('[]')->packages===[]&&pt_project('[]')->coverage==='unknown');
    foreach(['{}','null','false','123','"PRIVATE_CATALOG"','{"0":{"id":1,"label":"A"}}','{"result":[]}','[[]]','[{"id":1,"label":"A"},false]','[{"id":1,"label":"A"},{"id":1,"label":"A"}]','[{"id":1,"label":"A"},{"id":1,"label":"B"}]','[{"id":"1","label":"A"}]'] as $i=>$body)pt_unavailable('Catalog unsafe envelope or item rejects the whole observation '.$i,$body);
    foreach(['[','[{"id":1,"id":2,"label":"A"}]','[{"id":1,"\\u0069d":2,"label":"A"}]','[{"id":1,"label":"A","extra":{"x":1,"x":2}}]','[{"id":1,"label":"A","extra":1e999}]',"[{\"id\":1,\"label\":\"\xff\"}]"] as $i=>$body)pt_unavailable('Catalog malformed ambiguous or nonfinite JSON is refused '.$i,$body,'malformed_body');
    pt_unavailable('Catalog excessive nesting is refused','[{"id":1,"label":"A","extra":'.str_repeat('[',20).'0'.str_repeat(']',20).'}]','malformed_body');
    pt_unavailable('Catalog decoded node work is bounded even in discarded fields',json_encode([['id'=>1,'label'=>'A','extra'=>array_fill(0,50000,0)]]),'malformed_body');
    pt_unavailable('Catalog response bytes are bounded before decode',str_repeat(' ',1048577),'response_limit');
    $rows=array_map(static fn($id)=>['id'=>$id,'label'=>'Type '.$id],range(1,8192));pt_ok('Catalog item capacity accepts its exact boundary',count(pt_project(json_encode($rows))->packages)===8192);$rows[]=['id'=>8193,'label'=>'Overflow'];pt_unavailable('Catalog item capacity refuses overflow',json_encode($rows),'response_limit');
    foreach([401=>'authentication',403=>'authentication',429=>'rate_limited',503=>'remote_error',202=>'unexpected_status',302=>'unexpected_status',204=>'unexpected_status'] as $status=>$reason)pt_unavailable('Catalog HTTP status stays unavailable '.$status,'PRIVATE_CATALOG',$reason,$status);
    $document=ProviderCatalogImportDocument::fromPackageTypes(pt_rows($s));pt_ok('Catalog-only type becomes an explicit choice without installed packages',$document->types===[['type_ref'=>'1','name'=>'Shared'],['type_ref'=>'7002','name'=>'Catalog only']]&&$document->unnamed===0);
    pt_ok('Catalog document order is stable',ProviderCatalogImportDocument::fromPackageTypes(array_reverse(pt_rows($s)))->types===$document->types);
    foreach(['once','month','year'] as $period)pt_ok('Catalog local period creates only an unpriced draft '.$period,$document->draft('7002','LOCAL-SKU',$period)===['sku'=>'LOCAL-SKU','title'=>'Catalog only','family'=>'hosting','status'=>'draft','term'=>$period,'currency'=>'USD','amount'=>'','setup'=>'0.00','cost'=>'','provider_ref'=>'20i/observed-type/7002','summary'=>'','features'=>'','terms'=>'','sort_order'=>'0']);
    pt_ok('Empty catalog document invents no types',ProviderCatalogImportDocument::fromPackageTypes([])->types===[]);
    pt_deny('Saved catalog duplicate ID is not silently deduplicated',fn()=>ProviderCatalogImportDocument::fromPackageTypes([$saved,$saved]));
    pt_deny('Saved catalog unknown selection is refused',fn()=>$document->type('7003'));
    pt_deny('Catalog local service period remains an explicit owner choice',fn()=>$document->draft('7002','SKU','annual'));
    $resolved=0;$credential=new ProviderCredential('twenty-i','synthetic-profile',function()use(&$resolved):string{$resolved++;return 'SYNTHETIC_CATALOG_SECRET';});
    $transport=new PackageTypesTestTransport(new TransportResponse(200,[],$valid));$read=InventoryReader::read($transport,$credential,InventoryRead::PACKAGE_TYPES,'*',1700000000);$request=$transport->sent[0];
    pt_ok('Catalog reader sends exactly one fixed self GET with no body',$resolved===1&&count($transport->sent)===1&&$request->method==='GET'&&$request->url==='https://api.20i.com/reseller/*/packageTypes'&&$request->body===null&&$request->timeoutSeconds===15.0);
    pt_ok('Catalog credential occurs only in outgoing authorization',$request->headers===['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_CATALOG_SECRET'),'Accept'=>'application/json']&&!str_contains(json_encode($read),'SYNTHETIC_CATALOG_SECRET')&&$read->scope==='twenty-i|synthetic-profile|package.types');
    pt_ok('Catalog operation is executable only with a registered projector',InventoryReader::supports(InventoryRead::PACKAGE_TYPES)&&InventoryRegister::projectable(InventoryRead::PACKAGE_TYPES));
    foreach([null,0,1,'1','01','https://PRIVATE_CATALOG'] as $i=>$selector){$before=$resolved;pt_deny('Catalog numeric or arbitrary reseller selector is refused '.$i,fn()=>InventoryReader::read($transport,$credential,InventoryRead::PACKAGE_TYPES,$selector,1700000000));pt_ok('Catalog rejected selector never opens credentials or sends '.$i,$resolved===$before&&count($transport->sent)===1);}
    $transport=new PackageTypesTestTransport(new TransportException('timeout'));$failed=InventoryReader::read($transport,$credential,InventoryRead::PACKAGE_TYPES,'*',1700000000);pt_ok('Catalog timeout remains one failed attempt without retry',$failed->reason==='transport_timeout'&&$failed->packages===[]&&count($transport->sent)===1);
    $fixed=new FixedHttpTransport('8.8.8.8',fn()=>null);$validate=new ReflectionMethod($fixed,'validate');$headers=['Authorization'=>'Bearer '.base64_encode('synthetic-key'),'Accept'=>'application/json'];
    $validate->invoke($fixed,new TransportRequest('GET','https://api.20i.com/reseller/*/packageTypes',$headers,null,15.0));pt_ok('Catalog fixed transport admits the exact bodyless self path',true);
    foreach(['https://api.20i.com/reseller/1/packageTypes','https://api.20i.com/reseller/*/packageTypes?all=1','https://api.20i.com/reseller/*/packageTypes/','https://api.20i.com/reseller/*/packagetypes','https://PRIVATE_CATALOG/reseller/*/packageTypes','http://api.20i.com/reseller/*/packageTypes'] as $i=>$url)pt_deny('Catalog fixed transport rejects destination widening '.$i,fn()=>$validate->invoke($fixed,new TransportRequest('GET',$url,$headers,null,15.0)));
    pt_deny('Catalog GET cannot carry a body',fn()=>$validate->invoke($fixed,new TransportRequest('GET','https://api.20i.com/reseller/*/packageTypes',$headers,'{}',15.0)));
    pt_deny('Catalog POST is never enabled',fn()=>$validate->invoke($fixed,new TransportRequest('POST','https://api.20i.com/reseller/*/packageTypes',$headers+['Content-Type'=>'application/json'],'{}',15.0)));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected package types failure: '.$e->getMessage(),'passed'=>false];}
$passed=!array_filter($checks,static fn($c)=>$c['passed']!==true);echo json_encode(['passed'=>$passed,'scope'=>'Pure synthetic catalog projection, fixed request validation and draft document; no network, database, browser or provider acceptance.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;exit($passed?0:1);
