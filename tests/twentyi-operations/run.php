<?php
declare(strict_types=1);
// Synthetic write-boundary checks only. This file never opens a network
// connection, resolves a credential, or touches a database.
use CajunWebPro\Foundation\Provider\{OperationResult,ProviderOperation,TransportException,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\Operations\{TwentyIOperationRegister,TwentyIRequestBuilder,TwentyIResultClassifier};

$canonical=__DIR__.'/../../foundation/provider/';
foreach (['TransportRequest','TransportResponse','TransportException','ProviderOperation','OperationResult'] as $class) require $canonical.$class.'.php';
$component=__DIR__.'/../../foundation/twentyi-operations/';
foreach (['TwentyIOperation','TwentyIOperationRegister','TwentyIRequestBuilder','TwentyIResultClassifier'] as $class) require $component.$class.'.php';

$checks=[];
function check(bool $pass,string $name): void { global $checks; $checks[]=['name'=>$name,'passed'=>$pass]; if(!$pass)throw new RuntimeException($name); }
function refused(callable $fn): bool { try{$fn();return false;}catch(InvalidArgumentException){return true;} }

$register=new TwentyIOperationRegister();
$builder=new TwentyIRequestBuilder($register);

// ---- catalog shape ----
check($register->names()===['packages.create','packages.update','packages.userstatus','packages.delete'],'closed catalog is exactly four documented operations');
check($register->buildableNames()===['packages.create','packages.update'],'only shape-complete operations are buildable');
check($register->get('packages.create')->mayTriggerEmail&&$register->get('packages.create')->mayChargeBilling,'create flags email and billing risk');
check($register->get('packages.update')->destructive&&$register->get('packages.update')->mayChargeBilling,'update flags mixed delete and billing');
check(!$register->get('packages.delete')->shapeComplete&&$register->get('packages.delete')->destructive,'delete documented but shape-incomplete');
check(count($register->exclusions())>=10,'exclusion register covers credential, transfer, billing and access surfaces');

// ---- build refusal: unknown / shape-incomplete operations ----
foreach(['packages.list','domains.register','vps.power','resellers.users','x',''] as $i=>$name){
    if($name===''||!preg_match('/^[a-z0-9]+(\.[a-z0-9]+)*$/',$name))continue;
    check(refused(static fn()=>$builder->build(new ProviderOperation($name,'k-'.$i))),'unregistered operation refused '.$name);
}
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.userstatus','k'))),'documented-but-shapeless userstatus refused');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.delete','k',['resellerId'=>'*']))),'documented-but-shapeless delete refused');

// ---- slot validation ----
foreach([0,-1,1.5,'abc','../admin','{x}',null,'* OR 1','*/*'] as $i=>$bad){
    check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>$bad,'type'=>'t','domain_name'=>'a.example']))),'bad resellerId refused '.$i);
}
check(!$register->get('packages.create')->shapeComplete===false,'descriptor retains buildable shape');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['type'=>'t','domain_name'=>'a.example']))),'missing required resellerId slot refused');

// ---- field allowlist and required fields ----
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>'*','type'=>'t']))),'missing required domain_name refused');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>'*','type'=>'t','domain_name'=>'a.example','undeclared'=>1]))),'undeclared body field refused');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>'*','type'=>'t','domain_name'=>'a.example','Authorization'=>'x']))),'auth material cannot be smuggled as a field');
foreach(['no-dot','dot..dot','-lead.example','trail-.example','a.'.str_repeat('x',250),"bad\nname.example",'*.*'] as $i=>$host){
    check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>'*','type'=>'t','domain_name'=>$host]))),'invalid hostname refused '.$i);
}
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>'*','type'=>'t','domain_name'=>'a.example','label'=>str_repeat('x',1025)]))),'oversized string field refused');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>'*','type'=>'t','domain_name'=>'a.example','label'=>"ctrl\x01"]))) ,'control bytes in string refused');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>'*','type'=>'t','domain_name'=>'a.example','extra_domain_names'=>['ok.example','not-a-host']]))) ,'hostname_list rejects bad member');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>'*','type'=>'t','domain_name'=>'a.example','extra_domain_names'=>array_fill(0,65,'a.example')]))) ,'hostname_list bounded at 64');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>'*','type'=>['nested'],'domain_name'=>'a.example']))),'non-scalar type field refused');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',['resellerId'=>'*','type'=>'t','domain_name'=>'a.example','documentRoots'=>[1=>'x']]))),'map field rejects list keys');

// ---- update requires documented id ----
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.update','k',['resellerId'=>'*','labels'=>[]]))),'update without id refused');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.update','k',['resellerId'=>'*','id'=>0]))),'update id must be positive');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.update','k',['resellerId'=>'*','id'=>1,'rm'=>1]))),'undeclared delete key refused');

// ---- valid construction ----
$request=$builder->build(new ProviderOperation('packages.create','idem-1',[
    'resellerId'=>'*','type'=>'synthetic','domain_name'=>'client-site.example',
    'extra_domain_names'=>['www.client-site.example'],'label'=>'Client site',
    'documentRoots'=>['web'=>'public_html'],'stackUser'=>'cu_1','packageTemplate'=>'default',
]));
check($request instanceof TransportRequest,'valid create builds a transport request');
check($request->method==='POST'&&$request->url==='https://api.20i.com/reseller/*/addWeb','create posts to the documented self-alias path');
check($request->timeoutSeconds===30.0,'bounded write timeout');
$body=json_decode((string)$request->body,true);
check(is_array($body)&&!isset($body['resellerId'])&&$body['domain_name']==='client-site.example'&&count($body)===7,'body contains allowlisted fields only, slot consumed');
check(!isset($request->headers['Authorization'])&&!isset($request->headers['authorization']),'request never carries an Authorization header');
check(!str_contains($request->url,'*')||str_contains($request->url,'/reseller/*/'),'star alias only at the documented slot');

$numeric=$builder->build(new ProviderOperation('packages.create','idem-2',['resellerId'=>7001,'type'=>'t','domain_name'=>'a.example']));
check($numeric->url==='https://api.20i.com/reseller/7001/addWeb','allocated reseller id substitutes literally');

$update=$builder->build(new ProviderOperation('packages.update','idem-3',['resellerId'=>'*','id'=>42,'labels'=>['env'=>'prod'],'delete-id'=>7]));
check(str_contains($update->url,'/reseller/*/updatePackage')&&str_contains((string)$update->body,'"delete-id":7'),'update carries documented mixed fields');

// ---- outcome classification ----
check(TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse(200,[],'{"id":123}'))->status===OperationResult::SUCCEEDED,'2xx classifies succeeded');
check(TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse(200,[],'{"id":123}'))->externalId===null,'no fabricated externalId without a documented id field');
check(TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse(201,[],'not json'))->status===OperationResult::SUCCEEDED,'2xx with unreadable body still succeeded (write landed)');
check(TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse(200,[],'{"id":123}'),'id')->externalId==='123','documented id field populates externalId when supplied');
foreach([400,404,405,409,422,499] as $s){
    $r=TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse($s,[],'{"error":"x"}'));
    check($r->status===OperationResult::FAILED&&$r->providerCode==='http_'.$s,'4xx rejects as failed '.$s);
}
foreach([401,403] as $s){
    $r=TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse($s,[],'SYNTHETIC_RAW_GENERAL_KEY'));
    check($r->status===OperationResult::FAILED&&$r->providerCode==='authentication'&&!str_contains($r->message,'SYNTHETIC'),'auth failure bounded without body echo '.$s);
}
foreach([301,302,307,308,429,500,502,503,599] as $s){
    $r=TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse($s,[],'[]'));
    check($r->status===OperationResult::UNCERTAIN&&$r->needsReconciliation(),'received-but-ambiguous status is uncertain '.$s);
}
foreach([TransportException::KIND_CONNECT,TransportException::KIND_TLS,TransportException::KIND_UNAVAILABLE] as $kind){
    $r=TwentyIResultClassifier::fromTransportFailure('packages.create',new TransportException($kind));
    check($r->status===OperationResult::FAILED,'provably unsent write fails clean '.$kind);
}
foreach([TransportException::KIND_TIMEOUT,TransportException::KIND_PROTOCOL,TransportException::KIND_UNKNOWN,'garbage-kind'] as $kind){
    $r=TwentyIResultClassifier::fromTransportFailure('packages.create',new TransportException($kind));
    check($r->status===OperationResult::UNCERTAIN,'post-send ambiguity is uncertain '.$kind);
}
check(!TwentyIResultClassifier::fromTransportFailure('packages.create',new TransportException('timeout'))->isFinal()===false,'uncertain is final for automatic purposes');

// ---- adapter still refuses: this component is descriptive until wired ----
$adapter=__DIR__.'/../../foundation/twentyi/TwentyIAdapter.php';
check(str_contains(file_get_contents($adapter),'public function supports(string $operation): bool { return false; }'),'adapter still refuses every operation; register is not execution authority');

if(isset($argv[1])) {
    $json=json_encode(['passed'=>true,'scope'=>'Synthetic write-boundary only; descriptors, request construction and classification. No HTTP transport, credential, database or provider call','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    if(file_put_contents($argv[1],$json)!==strlen($json))throw new RuntimeException('Could not preserve named operation results.');
}
echo count($checks)." synthetic 20i operation checks passed\n";
