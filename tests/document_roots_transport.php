<?php
declare(strict_types=1);
use CajunWebPro\Foundation\Provider\TransportRequest;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
foreach(['TransportRequest','TransportResponse','TransportException','HttpTransport','ProviderCredential','ProviderAdapter'] as $f)require __DIR__.'/../foundation/provider/'.$f.'.php';
require __DIR__.'/../foundation/mail-transport/EndpointPolicy.php';require __DIR__.'/../foundation/twentyi/TwentyIAdapter.php';require __DIR__.'/../foundation/twentyi/FixedHttpTransport.php';
$checks=[];$headers=['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_ROOTS_TRANSPORT_KEY'),'Accept'=>'application/json'];$transport=new FixedHttpTransport('8.8.8.8',static function():never{throw new LogicException('No authorization or network in admission tests.');});$validate=new ReflectionMethod(FixedHttpTransport::class,'validate');
function rt_check(string $name,callable $request,bool $accepted):void {global $checks,$validate,$transport;$ok=true;try{$validate->invoke($transport,$request());}catch(InvalidArgumentException){$ok=false;}$checks[]=['name'=>$name,'passed'=>$ok===$accepted];}
foreach([1,701,999999999999999999] as $id)rt_check('Exact allocated package map route '.$id,fn()=>new TransportRequest('GET','https://api.20i.com/package/'.$id.'/web/documentRoots',$headers,null,15.0),true);
foreach(['0','01','-1','1.0','1000000000000000000','../1','1%2fweb','owned.invalid'] as $i=>$id)rt_check('Invalid package identity '.$i,fn()=>new TransportRequest('GET','https://api.20i.com/package/'.$id.'/web/documentRoots',$headers,null,15.0),false);
foreach(['/','?path=private','?domain=owned.invalid','#fragment','/../phpVersion'] as $i=>$tail)rt_check('No map route override '.$i,fn()=>new TransportRequest('GET','https://api.20i.com/package/701/web/documentRoots'.$tail,$headers,null,15.0),false);
foreach(['http://api.20i.com','https://other.invalid','https://api.20i.com:443','https://api.20i.com@other.invalid'] as $i=>$host)rt_check('Exact fixed map origin '.$i,fn()=>new TransportRequest('GET',$host.'/package/701/web/documentRoots',$headers,null,15.0),false);
rt_check('Map GET rejects body',fn()=>new TransportRequest('GET','https://api.20i.com/package/701/web/documentRoots',$headers,'{}',15.0),false);
rt_check('Map POST never admitted',fn()=>new TransportRequest('POST','https://api.20i.com/package/701/web/documentRoots',$headers+['Content-Type'=>'application/json'],'{}',15.0),false);
rt_check('Map extra header rejected',fn()=>new TransportRequest('GET','https://api.20i.com/package/701/web/documentRoots',$headers+['X-Path'=>'private'],null,15.0),false);
rt_check('Map timeout remains bounded',fn()=>new TransportRequest('GET','https://api.20i.com/package/701/web/documentRoots',$headers,null,16.0),false);
$passed=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$passed,'scope'=>'Production request validation only; no credential resolution, transport or network','checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
