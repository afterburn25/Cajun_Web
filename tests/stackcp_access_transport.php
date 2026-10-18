<?php
declare(strict_types=1);
use CajunWebPro\Foundation\Provider\TransportRequest;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
foreach (['TransportRequest','TransportResponse','TransportException','HttpTransport','ProviderCredential','ProviderAdapter','ProviderOperation'] as $f) require __DIR__.'/../foundation/provider/'.$f.'.php';
require __DIR__.'/../foundation/mail-transport/EndpointPolicy.php';require __DIR__.'/../foundation/twentyi/TwentyIAdapter.php';require __DIR__.'/../foundation/twentyi/FixedHttpTransport.php';
$checks=[];$headers=['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_STACKCP_ACCESS_TRANSPORT_KEY'),'Accept'=>'application/json'];$transport=new FixedHttpTransport('8.8.8.8',static function():never {throw new LogicException('No authorization or network in admission checks.');});$validate=new ReflectionMethod(FixedHttpTransport::class,'validate');
function sat_check(string $name,callable $request,bool $accepted):void {global $checks,$validate,$transport;$ok=true;try {$validate->invoke($transport,$request());}catch(InvalidArgumentException) {$ok=false;}$checks[]=['name'=>$name,'passed'=>$ok===$accepted];}
foreach (['membership'=>fn($id)=>'/package/'.$id.'/stackUserList','grants'=>fn($id)=>'/reseller/*/stackUser/'.$id.'/grants'] as $kind=>$path) {
    foreach ([1,2501,2147483647] as $id) sat_check($kind.' exact GET '.$id,fn()=>new TransportRequest('GET','https://api.20i.com'.$path($id),$headers,null,15),true);
    foreach (['0','01','-1','1.0','2147483648','999999999999999999','stack-user:1','../1','%31'] as $i=>$id) sat_check($kind.' invalid identity '.$i,fn()=>new TransportRequest('GET','https://api.20i.com'.$path($id),$headers,null,15),false);
    foreach (['/','?token=private','#override','/../grants'] as $i=>$tail) sat_check($kind.' path override '.$i,fn()=>new TransportRequest('GET','https://api.20i.com'.$path(2501).$tail,$headers,null,15),false);
    foreach (['http://api.20i.com','https://other.invalid','https://api.20i.com:443','https://user@api.20i.com'] as $i=>$origin) sat_check($kind.' origin override '.$i,fn()=>new TransportRequest('GET',$origin.$path(2501),$headers,null,15),false);
    sat_check($kind.' body refused',fn()=>new TransportRequest('GET','https://api.20i.com'.$path(2501),$headers,'{}',15),false);
    sat_check($kind.' POST refused',fn()=>new TransportRequest('POST','https://api.20i.com'.$path(2501),$headers+['Content-Type'=>'application/json'],'{}',15),false);
    sat_check($kind.' extra header refused',fn()=>new TransportRequest('GET','https://api.20i.com'.$path(2501),$headers+['X-Scope'=>'package:2501'],null,15),false);
    sat_check($kind.' timeout bounded',fn()=>new TransportRequest('GET','https://api.20i.com'.$path(2501),$headers,null,16),false);
}
foreach (['/reseller/123/stackUser/1/grants','/reseller/*/stackUser/1/token','/package/2501/stackUsers','/package/2501/stackUser','/reseller/*/stackUser/1/login'] as $i=>$path) sat_check('No unapproved sensitive route '.$i,fn()=>new TransportRequest('GET','https://api.20i.com'.$path,$headers,null,15),false);
$passed=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$passed,'scope'=>'Actual fixed transport request admission only; no resolver, credentials or network','checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
