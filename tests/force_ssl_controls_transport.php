<?php
declare(strict_types=1);
use CajunWebPro\Foundation\Provider\TransportRequest;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
foreach(['TransportRequest','TransportResponse','TransportException','HttpTransport','ProviderCredential','ProviderAdapter','ProviderOperation'] as $f)require __DIR__.'/../foundation/provider/'.$f.'.php';
require __DIR__.'/../foundation/mail-transport/EndpointPolicy.php';require __DIR__.'/../foundation/twentyi/TwentyIAdapter.php';require __DIR__.'/../foundation/twentyi/FixedHttpTransport.php';
$checks=[];$headers=['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_FORCE_SSL_CONTROL_KEY'),'Accept'=>'application/json','Content-Type'=>'application/json'];$transport=new FixedHttpTransport('8.8.8.8',static function():never{throw new LogicException('Admission tests never authorize or send.');});$validate=new ReflectionMethod(FixedHttpTransport::class,'validate');
function ct_check(string $name,callable $request,bool $accepted):void{global $checks,$transport,$validate;$ok=true;try{$validate->invoke($transport,$request());}catch(InvalidArgumentException){$ok=false;}$checks[]=['name'=>$name,'passed'=>$ok===$accepted];}
foreach([1,701,999999999999999999] as $id)foreach([true,false] as $value)ct_check('Exact boolean POST '.$id.'/'.($value?'true':'false'),fn()=>new TransportRequest('POST','https://api.20i.com/package/'.$id.'/web/forceSSL',$headers,json_encode(['value'=>$value]),15),true);
foreach(['0','01','-1','1.0','1000000000000000000','../1','1%2fweb'] as $i=>$id)ct_check('Invalid control package '.$i,fn()=>new TransportRequest('POST','https://api.20i.com/package/'.$id.'/web/forceSSL',$headers,'{"value":true}',15),false);
foreach(['/','?value=false','#other','/../directoryIndexing'] as $i=>$tail)ct_check('Control path override refused '.$i,fn()=>new TransportRequest('POST','https://api.20i.com/package/701/web/forceSSL'.$tail,$headers,'{"value":true}',15),false);
foreach(['http://api.20i.com','https://other.invalid','https://api.20i.com:443','https://user@api.20i.com'] as $i=>$host)ct_check('Control origin override refused '.$i,fn()=>new TransportRequest('POST',$host.'/package/701/web/forceSSL',$headers,'{"value":true}',15),false);
foreach(['{}','[]','null','{"value":null}','{"value":1}','{"value":"false"}','{"value":false,"value":true}','{"value":true,"extra":1}'] as $i=>$body)ct_check('Control body shape refused '.$i,fn()=>new TransportRequest('POST','https://api.20i.com/package/701/web/forceSSL',$headers,$body,15),false);
foreach(['directoryIndexing','documentRoots','phpVersion','forceSSLx'] as $name)ct_check('Other package setting POST remains refused '.$name,fn()=>new TransportRequest('POST','https://api.20i.com/package/701/web/'.$name,$headers,'{"value":true}',15),false);
ct_check('Extra control header refused',fn()=>new TransportRequest('POST','https://api.20i.com/package/701/web/forceSSL',$headers+['X-Override'=>'true'],'{"value":true}',15),false);
ct_check('Control timeout remains bounded',fn()=>new TransportRequest('POST','https://api.20i.com/package/701/web/forceSSL',$headers,'{"value":true}',16),false);
ct_check('Existing independent flag GET remains admitted',fn()=>new TransportRequest('GET','https://api.20i.com/package/701/web/forceSSL',array_slice($headers,0,2),null,15),true);
ct_check('Flag GET still cannot carry setter body',fn()=>new TransportRequest('GET','https://api.20i.com/package/701/web/forceSSL',array_slice($headers,0,2),'{"value":true}',15),false);
$passed=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$passed,'scope'=>'Fixed production transport admission only; no credential access, authorization or network. Requires the separately reviewed control admission patch.','checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
