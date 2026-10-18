<?php
declare(strict_types=1);
require __DIR__.'/../twentyi/bootstrap.php';
require __DIR__.'/../provider/FileLedger.php';
foreach(['TwentyIOperation','TwentyIOperationRegister','TwentyIRequestBuilder','TwentyIResultClassifier','TwentyIOperationAdapter'] as $class)require_once __DIR__.'/../../foundation/twentyi-operations/'.$class.'.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderOperation,ProviderCredential,ProviderGateway,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyI\Operations\{CloudWebsiteCreate,TwentyIRequestBuilder,TwentyIOperationRegister,TwentyIOperationAdapter,TwentyIResultClassifier};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
$checks=[];
function cs_ok(bool $ok,string $name):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function cs_deny(callable $fn):bool{try{$fn();return false;}catch(InvalidArgumentException){return true;}}
$params=['cloudServerId'=>123,'domain_name'=>'site.example','extra_domain_names'=>['www.site.example']];
$builder=new TwentyIRequestBuilder(new TwentyIOperationRegister());$op=new ProviderOperation(CloudWebsiteCreate::OPERATION,'cloud-example',$params);$request=$builder->build($op);
cs_ok($request->url==='https://api.20i.com/cloud_server/123/addWeb'&&$request->method==='POST'&&json_decode($request->body,true)===['domain_name'=>'site.example','extra_domain_names'=>['www.site.example']],'Exact documented server endpoint and body');
cs_ok(!(new TwentyIOperationRegister())->allowsGenericApproval($op->name,$params),'Generic approval cannot authorize cloud website creation');
foreach(['cloudServerId'=>[0,-1,'123',true,1.1,1000000000000000000],'domain_name'=>['SITE.example','site.example.','http://site.example','a..example','127.0.0.1','x.123',"a\n.example",'é.example'],'extra_domain_names'=>[['site.example'],['www.site.example','www.site.example'],['bad'],[1],array_fill(0,21,'a.example'),new stdClass()]] as $field=>$values)foreach($values as $i=>$value){$bad=$params;$bad[$field]=$value;cs_ok(cs_deny(fn()=>$builder->build(new ProviderOperation($op->name,'bad',$bad))),'Reject malformed '.$field.' '.$i);}
foreach(['stackUser','billing','type','resellerId','cloudServerId/..'] as $extra)cs_ok(cs_deny(fn()=>$builder->build(new ProviderOperation($op->name,'bad',$params+[$extra=>'x']))),'Reject extra field '.$extra);
$valid=new TransportRequest('POST',$request->url,['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_KEY'),'Accept'=>'application/json','Content-Type'=>'application/json'],$request->body,15.0);
$fixed=new FixedHttpTransport('8.8.8.8',fn()=>throw new LogicException('no network'));
$validate=new ReflectionMethod($fixed,'validate');$validate->invoke($fixed,$valid);cs_ok(true,'Fixed transport admits exact cloud website body before network');
foreach(['https://api.20i.com/cloud_server/123/reboot','https://api.20i.com/cloud_server/123/addWeb?x=1','https://api.20i.com/cloud_server/0123/addWeb','https://evil.example/cloud_server/123/addWeb','https://api.20i.com/vps/123/start'] as $url)cs_ok(cs_deny(fn()=>$validate->invoke($fixed,new TransportRequest('POST',$url,$valid->headers,$valid->body,15.0))),'Fixed transport refuses alternate route '.$url);
foreach(['{}','{"domain_name":"site.example","extra_domain_names":[],"stackUser":"x"}','{"domain_name":"bad.example","domain_name":"site.example","extra_domain_names":["www.site.example"]}'] as $body)cs_ok(cs_deny(fn()=>$validate->invoke($fixed,new TransportRequest('POST',$valid->url,$valid->headers,$body,15.0))),'Refuse malformed or ambiguous wire body '.hash('sha256',$body));
foreach([200,201,202] as $code){$r=TwentyIResultClassifier::fromResponse($op->name,new TransportResponse($code,[],'{"result":456}'));cs_ok($r->status==='accepted_pending'&&$r->externalId==='456','Returned package ID stays pending HTTP '.$code);}
foreach(['true','{}','{"result":true}','{"result":"456"}','{"result":456,"error":"PRIVATE_MARKER"}','{"result":0}'] as $body){$r=TwentyIResultClassifier::fromResponse($op->name,new TransportResponse(200,[],$body));cs_ok($r->status==='uncertain'&&!str_contains(json_encode($r),'PRIVATE_MARKER'),'Unverified response remains uncertain '.hash('sha256',$body));}
final class CloudSiteFixture implements HttpTransport {public int $calls=0;public bool $timeout=false;public function send(TransportRequest $request):TransportResponse{++$this->calls;if($this->timeout)throw new TransportException('timeout');return new TransportResponse(200,[],'{"result":456}');}}
$path=tempnam(sys_get_temp_dir(),'cwp-cloud-site-');
try{
    $transport=new CloudSiteFixture();$ledger=new FileLedger($path);$gateway=new ProviderGateway(new TwentyIOperationAdapter(),$transport,$ledger);$credential=new ProviderCredential('twenty-i','synthetic-account',fn()=>'SYNTHETIC_KEY');
    $r=$gateway->perform($credential,$op);$again=$gateway->perform($credential,$op);
    cs_ok($r->status==='accepted_pending'&&$again->replayed&&$transport->calls===1,'Shared gateway durable replay sends accepted request once');
    $bad=$params;$bad['domain_name']='other.example';cs_ok($gateway->perform($credential,new ProviderOperation($op->name,$op->idempotencyKey,$bad))->status==='conflict'&&$transport->calls===1,'Changed same-key payload conflicts before transport');
    $transport->timeout=true;$unknown=new ProviderOperation($op->name,'cloud-timeout',$params);$r=$gateway->perform($credential,$unknown);$again=$gateway->perform($credential,$unknown);
    cs_ok($r->status==='uncertain'&&$again->replayed&&$transport->calls===2,'Timeout stays uncertain with no retry or replay send');
}finally{if(is_file($path))unlink($path);}
$report=['scope'=>'Offline synthetic contract, fixed-transport admission and shared file-ledger gateway; no native database or provider calls.','checks'=>$checks];if(isset($argv[1])){if(!is_dir(dirname($argv[1])))mkdir(dirname($argv[1]),0700,true);file_put_contents($argv[1],json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));}echo count($checks)." cloud website checks passed\n";
