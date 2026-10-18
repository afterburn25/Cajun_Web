<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use CajunWebPro\Foundation\Provider\{TransportException,TransportRequest};
use CajunWebPro\Foundation\TwentyI\{FixedHttpTransport,TwentyIAdapter};
$checks=[];
function check(bool $pass,string $name):void {global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function refuses(callable $work,string $name):void {try{$work();}catch(InvalidArgumentException){check(true,$name);return;}check(false,$name);}
foreach(['127.0.0.1','10.0.0.1','169.254.169.254','100.64.0.1','::1','::ffff:8.8.8.8','2001:db8::1','api.20i.com','8.8.8.8:443',''] as $ip){
    refuses(fn()=>new FixedHttpTransport($ip,static fn()=>null),'reject unsafe or nonliteral pin '.$ip);
}
$authorized=0;$transport=new FixedHttpTransport('8.8.8.8',static function()use(&$authorized):void{++$authorized;throw new LogicException('Fixture stops before cURL.');});
$headers=['Authorization'=>'Bearer '.base64_encode('SYNTHETIC'),'Accept'=>'application/json'];
foreach([[],[['type'=>'A','ip'=>'127.0.0.1']],[['type'=>'A','ip'=>'8.8.8.8'],['type'=>'A','ip'=>'10.0.0.1']],[['type'=>'AAAA','ipv6'=>'fd00::1']]] as $i=>$records){
    try{FixedHttpTransport::resolve(static fn(string $host):array=>$records);check(false,'DNS refusal '.$i);}
    catch(TransportException $e){check($e->kind==='unavailable','reject empty private and mixed DNS answers before connect '.$i);}
}
$lookups=[];$pinned=FixedHttpTransport::resolve(static function(string $host)use(&$lookups):array{$lookups[]=$host;return [['type'=>'A','ip'=>count($lookups)===1?'8.8.8.8':'127.0.0.1']];});
check($pinned==='8.8.8.8'&&$lookups===['api.20i.com'],'resolve the fixed host once and pin accepted answer without relookup');
$lookups=[];$pinned=FixedHttpTransport::resolve(static function(string $host)use(&$lookups):array{$lookups[]=$host;return $host==='api.20i.com'?[['type'=>'CNAME','target'=>'edge.example.net.']]:[['type'=>'AAAA','ipv6'=>'2001:4860:4860::8888']];});
check($pinned==='2001:4860:4860::8888'&&$lookups===['api.20i.com','edge.example.net'],'bounded CNAME resolution preserves the original request hostname');
foreach([
    ['POST',TwentyIAdapter::URL,$headers,null,15],
    ['GET','https://api.20i.com/package/',$headers,null,15],
    ['GET','https://api.20i.com/package?x=1',$headers,null,15],
    ['GET','https://api.20i.com:443/package',$headers,null,15],
    ['GET','https://other.invalid/package',$headers,null,15],
    ['GET',TwentyIAdapter::URL,$headers,'',15],
    ['GET',TwentyIAdapter::URL,$headers,null,16],
    ['GET',TwentyIAdapter::URL,$headers+['Host'=>'evil.invalid'],null,15],
    ['GET',TwentyIAdapter::URL,['Authorization'=>'Bearer Zm9v\r\nInjected: true','Accept'=>'application/json'],null,15],
    ['GET',TwentyIAdapter::URL,['Authorization'=>'Bearer '.base64_encode("bad\0key"),'Accept'=>'application/json'],null,15],
    ['GET',TwentyIAdapter::URL,['Authorization'=>'Bearer '.base64_encode(str_repeat('x',4097)),'Accept'=>'application/json'],null,15],
    ['GET',TwentyIAdapter::URL,['Authorization'=>'Bearer','Accept'=>'application/json'],null,15],
    ['GET',TwentyIAdapter::URL,['Authorization'=>'Bearer Zm9v====','Accept'=>'application/json'],null,15],
    ['GET',TwentyIAdapter::URL,['Authorization'=>'Bearer '.base64_encode('synthetic'),'Accept'=>'text/html'],null,15],
] as $i=>$args)refuses(fn()=>$transport->send(new TransportRequest(...$args)),'invalid request stops before authorization/network '.$i);
check($authorized===0,'all request rejections avoid credential authority callback and network');
try{$transport->send(new TransportRequest('GET',TwentyIAdapter::URL,$headers));}catch(LogicException){check($authorized===1,'current authority can stop valid request before network');}
$writeHeaders=$headers+['Content-Type'=>'application/json'];
$before=$authorized;
refuses(fn()=>$transport->send(new TransportRequest('POST','https://api.20i.com/reseller/*/renewDomain',$writeHeaders,'{"delete-id":[42]}')),'renewal refuses unrelated write body before authorization');
check($authorized===$before,'malformed renewal body never reaches authority/network');
foreach(['addWeb','updatePackage','deleteWeb','renewDomain'] as $endpoint){
    $before=$authorized;
    $body=$endpoint==='renewDomain'?'{"name":"renew.example","years":2,"renewPrivacy":false}':'{"delete-id":[42]}';
    try{$transport->send(new TransportRequest('POST','https://api.20i.com/reseller/*/'.$endpoint,$writeHeaders,$body));check(false,'write authorization gate '.$endpoint);}
    catch(LogicException){check($authorized===$before+1,'current authority stops catalogued write before network '.$endpoint);}
}
foreach(['../7','0','self','12.3','123?x=1','*/*','foo-bar'] as $id){
    $before=$authorized;
    refuses(fn()=>$transport->send(new TransportRequest('POST','https://api.20i.com/reseller/'.$id.'/deleteWeb',$writeHeaders,'{"delete-id":[42]}')),'invalid write reseller path '.$id);
    check($authorized===$before,'bad write path never reaches authority/network '.$id);
}
if(!function_exists('curl_init')){
    $transport=new FixedHttpTransport('8.8.8.8',static fn()=>null);
    try{$transport->send(new TransportRequest('GET',TwentyIAdapter::URL,$headers));check(false,'missing extension refuses');}
    catch(TransportException $e){check($e->kind==='unavailable','missing cURL fails with finite unavailable classification');}
}
echo json_encode(['passed'=>true,'scope'=>'No network: request and destination rejection; unavailable extension when applicable','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
