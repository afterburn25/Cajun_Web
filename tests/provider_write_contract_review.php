<?php
/** Independent real builder/adapter/transport validation. Stops before any cURL or DNS. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require_once __DIR__.'/twentyi/bootstrap.php';
foreach (['TwentyIOperation','TwentyIOperationRegister','TwentyIRequestBuilder','TwentyIResultClassifier','TwentyIOperationAdapter'] as $class) {
    require_once __DIR__.'/../foundation/twentyi-operations/'.$class.'.php';
}
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderOperation,ProviderCredential,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\{FixedHttpTransport,TwentyIAdapter};
use CajunWebPro\Foundation\TwentyI\Operations\{TwentyIOperationAdapter,TwentyIOperationRegister,TwentyIRequestBuilder};

final class ReviewStoppedBeforeNetwork extends LogicException {}
$checks=[];$observations=[];
function wc_check(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];}
function wc_validate(TransportRequest $request):array {
    $authorized=0;$error=null;
    $transport=new FixedHttpTransport('8.8.8.8',static function()use(&$authorized):void {
        ++$authorized; throw new ReviewStoppedBeforeNetwork('Synthetic boundary reached; no network allowed.');
    });
    try {$transport->send($request);throw new RuntimeException('Unexpected return from stopped transport.');}
    catch(ReviewStoppedBeforeNetwork) {}
    catch(InvalidArgumentException $e) {$error=$e->getMessage();}
    return ['authorized'=>$authorized,'error'=>$error];
}
final class ReviewCaptureWrite implements HttpTransport {
    public ?TransportRequest $request=null;
    public function send(#[SensitiveParameter] TransportRequest $request):TransportResponse {
        $this->request=$request;
        return new TransportResponse(200,[],'{"id":4321}');
    }
}
try {
    $auth='Bearer '.base64_encode('SYNTHETIC_WRITE_CONTRACT_KEY');
    $ordered=['Authorization'=>$auth,'Accept'=>'application/json','Content-Type'=>'application/json'];
    $probe=wc_validate(new TransportRequest('GET',TwentyIAdapter::URL,['Authorization'=>$auth,'Accept'=>'application/json']));
    wc_check('Actual fixed GET request reaches the pre-network authority guard',$probe['authorized']===1 && $probe['error']===null);
    $literal=wc_validate(new TransportRequest('POST','https://api.20i.com/reseller/*/addWeb',$ordered,'{"type":"synthetic","domain_name":"a.example"}',15));
    wc_check('Literal bounded POST reaches the pre-network authority guard',$literal['authorized']===1 && $literal['error']===null);
    $adapter=new TwentyIOperationAdapter();
    $credential=new ProviderCredential('twenty-i','synthetic-profile',static fn()=>'SYNTHETIC_WRITE_CONTRACT_KEY');
    foreach ([
        new ProviderOperation('packages.create','review-write-create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'a.example']),
        new ProviderOperation('packages.update','review-write-update',['resellerId'=>'*','id'=>4321]),
    ] as $operation) {
        $capture=new ReviewCaptureWrite();$adapter->execute($capture,$credential,$operation);
        $request=$capture->request;
        if (!$request) throw new RuntimeException('The registered adapter did not build the review request.');
        $facts=['method'=>$request->method,'url'=>$request->url,'timeout'=>$request->timeoutSeconds,'header_names'=>array_keys($request->headers)];
        $actual=wc_validate($request);
        $bounded=wc_validate(new TransportRequest($request->method,$request->url,$request->headers,$request->body,15));
        $orderedOnly=wc_validate(new TransportRequest($request->method,$request->url,$ordered,$request->body,$request->timeoutSeconds));
        $both=wc_validate(new TransportRequest($request->method,$request->url,$ordered,$request->body,15));
        $observations[$operation->name]=['request'=>$facts,'original'=>$actual,'timeout_only'=>$bounded,'headers_only'=>$orderedOnly,'both_normalized_control'=>$both];
        wc_check($operation->name.' real adapter request is accepted by its production fixed transport',$actual['authorized']===1 && $actual['error']===null);
        wc_check($operation->name.' request fits transport header contract independently of timeout',$bounded['authorized']===1 && $bounded['error']===null);
        wc_check($operation->name.' request fits transport timeout contract independently of headers',$orderedOnly['authorized']===1 && $orderedOnly['error']===null);
        wc_check($operation->name.' diagnostic control reaches guard after both contract differences are normalized',$both['authorized']===1 && $both['error']===null);
        $calls=0;$result=null;$reached=false;
        $transport=new FixedHttpTransport('8.8.8.8',static function()use(&$calls):void{++$calls;throw new ReviewStoppedBeforeNetwork('No network permitted.');});
        try {$result=$adapter->execute($transport,$credential,$operation);}catch(ReviewStoppedBeforeNetwork){$reached=true;}
        $observations[$operation->name]['actual_adapter_transport']=['authority_calls'=>$calls,'stopped_before_network'=>$reached,'status'=>$result?->status,'provider_code'=>$result?->providerCode];
        wc_check($operation->name.' actual adapter and fixed transport can reach the final authorization boundary',$reached && $calls===1);
    }
}catch(Throwable $e){$checks[]=['name'=>'Unexpected fixture failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e.PHP_EOL);}
$passed=!array_filter($checks,static fn($c)=>!$c['passed']);
$mode=in_array('--no-ini',$argv,true)?'no-ini':'default';
@mkdir(__DIR__.'/../qa',0700,true);
file_put_contents(__DIR__.'/../qa/provider-write-contract-review-'.$mode.'.json',json_encode(['passed'=>$passed,'source'=>'0a4462581ac43d14eea316d180164a0fcd9e99b5','php'=>PHP_VERSION,'scope'=>'Real registered builder and adapter against the production fixed transport validator; a throwing authorization guard prevents every DNS/cURL/provider call. Negative findings are not acceptance.','checks'=>$checks,'observations'=>$observations],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo ($passed?'PASS':'FINDINGS').': '.count($checks).' write-contract review checks'.PHP_EOL;
exit($passed?0:1);
