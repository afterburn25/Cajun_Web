<?php
declare(strict_types=1);
// Synthetic adapter acceptance only. This file never opens a network connection.
use CajunWebPro\Foundation\Provider\{ConnectionCheck,HttpTransport,OperationResult,ProviderCredential,ProviderGateway,ProviderOperation,TransportException,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\{TwentyIAdapter,ProbeOnlyLedger};
$canonical=__DIR__.'/../../foundation/provider/';
foreach (['ProviderCapability','ProviderCredential','TransportRequest','TransportResponse','TransportException','HttpTransport','ProviderOperation','OperationResult','ConnectionCheck','RetryPolicy','LedgerClaim','OperationLedger','ProviderAdapter','ProviderGateway'] as $class) require $canonical.$class.'.php';
require __DIR__.'/../../foundation/twentyi/TwentyIAdapter.php'; require __DIR__.'/../../foundation/twentyi/ProbeOnlyLedger.php';
$checks=[];
function check(bool $pass,string $name): void { global $checks; $checks[]=['name'=>$name,'passed'=>$pass]; if(!$pass)throw new RuntimeException($name); }
final class SyntheticTransport implements HttpTransport {
    public array $requests=[];
    public function __construct(public TransportResponse|TransportException $response) {}
    public function send(#[\SensitiveParameter] TransportRequest $request): TransportResponse {
        $this->requests[]=$request;
        if($this->response instanceof TransportException)throw $this->response;
        return $this->response;
    }
}
$adapter=new TwentyIAdapter(); $ledger=new ProbeOnlyLedger(); $resolutions=0;
$credential=new ProviderCredential('twenty-i','synthetic-profile',static function()use(&$resolutions):string{++$resolutions;return 'SYNTHETIC_RAW_GENERAL_KEY';});
$transport=new SyntheticTransport(new TransportResponse(200,[],'[]'));
$gateway=new ProviderGateway($adapter,$transport,$ledger);
check($gateway->checkConnection($credential)->state===ConnectionCheck::VERIFIED,'empty actual JSON list authenticates the fixed probe');
check($resolutions===1,'one probe resolves the credential exactly once');
$request=$transport->requests[0];
check($request->method==='GET'&&$request->url==='https://api.20i.com/package'&&$request->body===null,'fixed method path host and no request body');
check($request->headers===['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_RAW_GENERAL_KEY'),'Accept'=>'application/json'],'raw General key base64 encoded exactly once');
check($request->timeoutSeconds===15.0,'bounded request timeout');
foreach(['{}','{"0":"synthetic"}','{"0":{},"1":{}}','null','true','42','"list"','[] trailing','not json','[','["'.chr(255).'"]',str_repeat('[',40).str_repeat(']',40)] as $i=>$body){
    $transport->response=new TransportResponse(200,[],$body);
    $result=$gateway->checkConnection($credential);
    check($result->state===ConnectionCheck::UNKNOWN&&$result->detail==='unexpected_response','reject non-list malformed or excessive-depth body '.$i);
}
foreach(['[]','[{}]','["synthetic",1,null]',' [ ] '] as $i=>$body){
    $transport->response=new TransportResponse(200,[],$body);
    check($gateway->checkConnection($credential)->detail==='connected','accept JSON list shape without inventing item schemas '.$i);
}
$transport->response=new TransportResponse(200,[],str_repeat(' ',TwentyIAdapter::MAX_BODY_BYTES).'[]');
check($gateway->checkConnection($credential)->detail==='response_limit','adapter independently rejects oversized body');
foreach([201,204,206,301,302,307,308,400,404,405,410,429,500,503,599] as $status){
    $transport->response=new TransportResponse($status,['Location'=>'https://secret.invalid'],'[]');
    $before=count($transport->requests);$result=$gateway->checkConnection($credential);
    check($result->state===ConnectionCheck::UNKNOWN&&!$result->ok(),'nonaccepted status cannot verify '.$status);
    check(count($transport->requests)===$before+1,'probe never retries or follows response '.$status);
}
foreach([401,403] as $status){
    $transport->response=new TransportResponse($status,[],'SYNTHETIC_RAW_GENERAL_KEY');
    $result=$gateway->checkConnection($credential);
    check($result->state===ConnectionCheck::AUTHENTICATION_FAILED&&$result->detail==='authentication','fixed auth rejection without body disclosure '.$status);
}
foreach(['connect','tls','unavailable','timeout','protocol','unknown','SYNTHETIC_PRIVATE_URL'] as $kind){
    $transport->response=new TransportException($kind);
    $result=$gateway->checkConnection($credential);
    check($result->state===(in_array($kind,['connect','tls','unavailable'],true)?ConnectionCheck::UNREACHABLE:ConnectionCheck::UNKNOWN),'gateway finite pre/post-send classification '.$kind);
    check(!str_contains($result->detail,'SYNTHETIC'),'exception detail does not escape '.$kind);
}
$before=count($transport->requests);$beforeResolution=$resolutions;
foreach($adapter->capabilities() as $capability){
    check(!$adapter->supports($capability->operation)&&$capability->state==='unverified','capability remains unverified '.$capability->operation);
    $operation=new ProviderOperation($capability->operation,'synthetic-'.$capability->operation);
    check($gateway->perform($credential,$operation)->status===OperationResult::UNSUPPORTED,'gateway refuses operation '.$capability->operation);
    check($adapter->execute($transport,$credential,$operation)->status===OperationResult::UNSUPPORTED,'direct execution refuses operation '.$capability->operation);
}
check(count($transport->requests)===$before&&$resolutions===$beforeResolution,'all disabled operations avoid transport and credential access');
$wrong=new ProviderCredential('wrong-provider','synthetic',static fn():string=>throw new RuntimeException('must not resolve'));
check($gateway->checkConnection($wrong)->detail==='wrong_provider','wrong provider refused before secret resolution');
foreach(['',str_repeat('x',4097),"bad\r\nvalue","bad\0value"] as $i=>$key){
    $bad=new ProviderCredential('twenty-i','synthetic',static fn():string=>$key);
    check($gateway->checkConnection($bad)->detail==='invalid_credential','invalid credential rejected locally '.$i);
}
check(count($transport->requests)===$before,'invalid credentials never reach transport');
if(isset($argv[1])) {
    $json=json_encode(['passed'=>true,'scope'=>'Synthetic adapter only; no HTTP transport, application, database, browser or provider call','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    if(file_put_contents($argv[1],$json)!==strlen($json))throw new RuntimeException('Could not preserve named adapter results.');
}
echo count($checks)." synthetic 20i adapter checks passed\n";
