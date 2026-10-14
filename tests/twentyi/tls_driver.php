<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\{FixedHttpTransport,ProbeOnlyLedger,TwentyIAdapter};
$input=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
$GLOBALS['twentyi_fixture']=$input;require __DIR__.'/tls_connector.php';
$authorizations=0;$returnedHeaders=null;
$fixed=new FixedHttpTransport($input['address']??'8.8.8.8',static function()use(&$authorizations,$input):void{
    ++$authorizations;if(($input['revoke']??0)===$authorizations)throw new RuntimeException('access_changed');
});
// Test-only reduced transfer budget; the real adapter always chooses 15 seconds.
$transport=new class($fixed,$input,$returnedHeaders) implements HttpTransport {
    public function __construct(private FixedHttpTransport $fixed,private array $input,private mixed &$returnedHeaders){}
    public function send(#[\SensitiveParameter] TransportRequest $request):TransportResponse{
        $response=$this->fixed->send(new TransportRequest($request->method,$request->url,$request->headers,$request->body,(float)($this->input['budget']??15)));
        $this->returnedHeaders=count($response->headers);return $response;
    }
};
try{
    $result=(new ProviderGateway(new TwentyIAdapter(),$transport,new ProbeOnlyLedger()))->checkConnection(new ProviderCredential('twenty-i','synthetic-profile',static fn():string=>'SYNTHETIC_RAW_GENERAL_KEY'));
    $out=['state'=>$result->state,'code'=>$result->detail];
}catch(RuntimeException $e){if($e->getMessage()!=='access_changed')throw $e;$out=['state'=>'denied','code'=>'access_changed'];}
echo json_encode($out+['policy'=>$GLOBALS['twentyi_options_verified']??false,'authorizations'=>$authorizations,'returned_headers'=>$returnedHeaders],JSON_THROW_ON_ERROR);
