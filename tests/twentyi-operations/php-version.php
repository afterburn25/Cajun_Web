<?php
declare(strict_types=1);
// Included by run.php: real builders/adapters/gateway with synthetic transport and ledger.
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,TransportException,ProviderOperation,ProviderCredential,ProviderGateway,OperationLedger,LedgerClaim,OperationResult};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyI\Operations\{PhpVersionChange,TwentyIOperationAdapter,TwentyIResultClassifier};

require_once __DIR__.'/../twentyi/bootstrap.php';
require_once __DIR__.'/../../foundation/twentyi-operations/TwentyIOperationAdapter.php';

foreach ([1,42,999999999999999999] as $id) {
    $op = new ProviderOperation(PhpVersionChange::OPERATION, 'php-contract', ['packageId'=>$id,'value'=>'php82']);
    $request = $builder->build($op);
    check($request->method === 'POST' && $request->url === 'https://api.20i.com/package/'.$id.'/web/phpVersion'
        && $request->body === '{"value":"php82"}' && $request->timeoutSeconds === 15.0,
        'PHP exact setter method path and sole string body '.$id);
    PhpVersionChange::wire($request);
    check(!array_key_exists('Authorization', $request->headers), 'PHP descriptor has no credential '.$id);
}
foreach ([0,-1,1000000000000000000,'1',1.0,true,null] as $i=>$id) {
    check(refused(fn()=>PhpVersionChange::params(['packageId'=>$id,'value'=>'php82'])), 'PHP invalid package refused '.$i);
}
foreach ([null,false,82,8.2,[],(object)[], '', ' ', "php82\n", "php\0", "\xff", str_repeat('a',129)] as $i=>$value) {
    check(refused(fn()=>PhpVersionChange::params(['packageId'=>1,'value'=>$value])), 'PHP invalid version text refused '.$i);
}
foreach ([[],['packageId'=>1],['value'=>'php82'],['packageId'=>1,'value'=>'php82','title'=>'PHP 8.2']] as $i=>$params) {
    check(refused(fn()=>PhpVersionChange::params($params)), 'PHP missing or extra request fields refused '.$i);
}
$id=1; $value='php82'; $params=PhpVersionChange::params(['packageId'=>&$id,'value'=>&$value]);
$id=2; $value='php83';
check($params===['packageId'=>1,'value'=>'php82'], 'PHP validated scalars detach caller references');
check(refused(fn()=>PhpVersionChange::request(new ProviderOperation('packages.update','wrong-op',$params))), 'PHP setter refuses a different operation');
check(!$register->allowsGenericApproval(PhpVersionChange::OPERATION,$params)
    && !in_array(PhpVersionChange::OPERATION,$register->genericNames(),true), 'PHP generic owner approval remains denied');
// Bounds validate strings; provider option membership belongs to the dedicated application flow.
$arbitrary='synthetic-option';
check(PhpVersionChange::params(['packageId'=>1,'value'=>$arbitrary])['value']===$arbitrary,
    'PHP wire contract does not invent a static version support list');

$headers=['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_PHP_KEY'),'Accept'=>'application/json','Content-Type'=>'application/json'];
$authorityCalls=0;
$transport=new FixedHttpTransport('8.8.8.8',static function()use(&$authorityCalls):void {
    ++$authorityCalls;
    throw new LogicException('Offline stop before network.');
});
$url='https://api.20i.com/package/1/web/phpVersion';
$canonicalBody='{"value":"php82"}';
foreach (['GET','PUT','PATCH','DELETE'] as $method) {
    check(refused(fn()=>$transport->send(new TransportRequest($method,$url,$headers,$canonicalBody))), 'PHP wrong method denied before authorization '.$method);
}
foreach ([
    'http://api.20i.com/package/1/web/phpVersion', 'https://api.20i.com.evil.invalid/package/1/web/phpVersion',
    'https://api.20i.com:443/package/1/web/phpVersion', 'https://u@api.20i.com/package/1/web/phpVersion',
    'https://api.20i.com/package/01/web/phpVersion', 'https://api.20i.com/package/0/web/phpVersion',
    'https://api.20i.com/package/1000000000000000000/web/phpVersion', $url.'?x=1', $url.'#fragment', $url.'/',
    'https://api.20i.com/package/1/web/phpversion', 'https://api.20i.com/package/1/web/%70hpVersion',
    'https://api.20i.com/package/1/web/availablePhpVersions',
] as $i=>$badUrl) {
    check(refused(fn()=>$transport->send(new TransportRequest('POST',$badUrl,$headers,$canonicalBody))), 'PHP nonexact endpoint denied before authorization '.$i);
}
foreach ([null,'','true','[]','{}','{"value":null}','{"value":82}','{"value":false}',
    '{"value":"php82","value":"php83"}', '{"value":"php82","title":"PHP 8.2"}',
    '{"value": "php82"}', '{"Value":"php82"}', '{"value":"php82"}' . "\n", '{"value":"\\u0070hp82"}',
] as $i=>$body) {
    check(refused(fn()=>$transport->send(new TransportRequest('POST',$url,$headers,$body))), 'PHP ambiguous or noncanonical body denied before authorization '.$i);
}
check($authorityCalls===0, 'PHP invalid requests never reach authorization or network');
try {
    $transport->send(new TransportRequest('POST',$url,$headers,$canonicalBody));
    check(false,'PHP fixed transport requires current authority');
} catch (LogicException) {
    check($authorityCalls===1, 'PHP exact request reaches current authority once before network');
}

foreach ([200,201,202,204,408,409,429,500] as $status) {
    foreach (['true','null','{"result":true}','{"result":42}','"php82"','{}','invalid'] as $i=>$body) {
        $result=TwentyIResultClassifier::fromResponse(PhpVersionChange::OPERATION,new TransportResponse($status,[],$body));
        check($result->status===OperationResult::UNCERTAIN && $result->externalId===null,
            'PHP response never invents verified change '.$status.'/'.$i);
    }
}
// One in-memory ledger tests gateway integration, not native persistence/concurrency acceptance.
$ledger=new class implements OperationLedger {
    public ?string $fingerprint=null;
    public ?OperationResult $result=null;
    public function claim(string $idempotencyKey,string $fingerprint,int $now):LedgerClaim {
        if($this->fingerprint!==null && $this->fingerprint!==$fingerprint)return LedgerClaim::conflict();
        if($this->result!==null)return LedgerClaim::replay($this->result);
        if($this->fingerprint!==null)return LedgerClaim::inProgress();
        $this->fingerprint=$fingerprint;return LedgerClaim::claimed();
    }
    public function record(string $idempotencyKey,string $fingerprint,OperationResult $result,int $now):void {
        if($this->fingerprint!==$fingerprint || $this->result!==null)throw new RuntimeException('Synthetic ledger mismatch.');
        $this->result=$result;
    }
};
$fake=new class implements HttpTransport {
    public array $requests=[];
    public function send(TransportRequest $request):TransportResponse {
        $this->requests[]=$request;
        throw new TransportException(TransportException::KIND_TIMEOUT);
    }
};
$credential=new ProviderCredential('twenty-i','synthetic-account',static fn()=>'SYNTHETIC_PHP_KEY');
$gateway=new ProviderGateway(new TwentyIOperationAdapter(),$fake,$ledger);
$op=new ProviderOperation(PhpVersionChange::OPERATION,'php-once',['packageId'=>1,'value'=>'php82']);
$first=$gateway->perform($credential,$op);
check($first->status===OperationResult::UNCERTAIN && $first->attempts===1 && count($fake->requests)===1,
    'PHP ambiguous send has exactly one gateway attempt');
$second=$gateway->perform($credential,$op);
check($second->status===OperationResult::UNCERTAIN && count($fake->requests)===1,
    'PHP same request replays recorded uncertainty without another send');
$conflict=$gateway->perform($credential,new ProviderOperation(PhpVersionChange::OPERATION,'php-once',['packageId'=>1,'value'=>'php83']));
check($conflict->status===OperationResult::CONFLICT && count($fake->requests)===1,
    'PHP changed selection cannot reuse an existing gateway request');
check($fake->requests[0]->body===$canonicalBody && $fake->requests[0]->headers===$headers,
    'PHP adapter attaches only synthetic credential and exact reviewed request fields');
foreach (['twentyi-operations/PhpVersionChange.php','twentyi-operations/TwentyIRequestBuilder.php','twentyi-operations/TwentyIOperationRegister.php','twentyi/FixedHttpTransport.php'] as $file) {
    check(hash_file('sha256',__DIR__.'/../../foundation/'.$file)===hash_file('sha256',__DIR__.'/../../site/includes/foundation/'.$file),
        'PHP shipping copy matches canonical source '.$file);
}
