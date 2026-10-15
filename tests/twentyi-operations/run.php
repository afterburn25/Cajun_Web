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
check($register->names()===['packages.create','packages.update','domains.renew','domains.register','domains.transfer','domains.nameservers','domains.dns','packages.userstatus','packages.delete'],'closed catalog is exactly the nine documented operations');
check($register->buildableNames()===['packages.create','packages.update','domains.renew','packages.delete'],'only shape-complete operations are buildable');
check($register->get('packages.create')->mayTriggerEmail&&$register->get('packages.create')->mayChargeBilling,'create flags email and billing risk');
check($register->get('packages.update')->destructive&&$register->get('packages.update')->mayChargeBilling,'update flags mixed delete and billing');
check($register->get('domains.renew')->mayChargeBilling&&!$register->get('domains.renew')->destructive,'domain renew flags billing only');
check($register->get('packages.delete')->shapeComplete&&$register->get('packages.delete')->destructive,'delete uses the documented array contract');
foreach(['domains.register','domains.transfer','domains.nameservers','domains.dns'] as $name){
    check(!$register->get($name)->shapeComplete,'domain operation documented but shape-incomplete '.$name);
    check(refused(static fn()=>$builder->build(new ProviderOperation($name,'k',['resellerId'=>'*','packageId'=>1,'domainId'=>1,'name'=>'a.example','years'=>1]))),'shape-incomplete domain operation refuses to build '.$name);
}
check(count($register->exclusions())>=10,'exclusion register covers credential, transfer, billing and access surfaces');

// ---- build refusal: unknown / shape-incomplete operations ----
foreach(['packages.list','domains.transferx','vps.power','resellers.users','x',''] as $i=>$name){
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
check($request->timeoutSeconds===15.0,'bounded write timeout');
$body=json_decode((string)$request->body,true);
check(is_array($body)&&!isset($body['resellerId'])&&$body['domain_name']==='client-site.example'&&count($body)===7,'body contains allowlisted fields only, slot consumed');
check(!isset($request->headers['Authorization'])&&!isset($request->headers['authorization']),'request never carries an Authorization header');
check(!str_contains($request->url,'*')||str_contains($request->url,'/reseller/*/'),'star alias only at the documented slot');

$numeric=$builder->build(new ProviderOperation('packages.create','idem-2',['resellerId'=>7001,'type'=>'t','domain_name'=>'a.example']));
check($numeric->url==='https://api.20i.com/reseller/7001/addWeb','allocated reseller id substitutes literally');

$update=$builder->build(new ProviderOperation('packages.update','idem-3',['resellerId'=>'*','id'=>[42],'labels'=>[42=>'prod'],'delete-id'=>[7]]));
check(str_contains($update->url,'/reseller/*/updatePackage')&&str_contains((string)$update->body,'"delete-id":[7]'),'update carries documented mixed fields');

// ---- domains.renew: documented bounded billing write ----
$renew=$builder->build(new ProviderOperation('domains.renew','idem-4',['resellerId'=>'*','name'=>'client-site.example','years'=>2,'renewPrivacy'=>true]));
check($renew->url==='https://api.20i.com/reseller/*/renewDomain','renew posts to the documented reseller path');
$rbody=json_decode((string)$renew->body,true);
check($rbody===['name'=>'client-site.example','years'=>2,'renewPrivacy'=>true],'renew body carries only the three documented fields');
check(refused(static fn()=>$builder->build(new ProviderOperation('domains.renew','k',['resellerId'=>'*','years'=>1]))),'renew without a domain name refused');
check(refused(static fn()=>$builder->build(new ProviderOperation('domains.renew','k',['resellerId'=>'*','name'=>'a.example']))),'renew without a years period refused');
check(refused(static fn()=>$builder->build(new ProviderOperation('domains.renew','k',['resellerId'=>'*','name'=>'a.example','years'=>0]))),'renew years must be positive');
check(refused(static fn()=>$builder->build(new ProviderOperation('domains.renew','k',['resellerId'=>'*','name'=>'not a host','years'=>1]))),'renew name must be a hostname');
check(refused(static fn()=>$builder->build(new ProviderOperation('domains.renew','k',['resellerId'=>'*','name'=>'a.example','years'=>1,'undeclared'=>1]))),'undeclared renew field refused');
check(refused(static fn()=>$builder->build(new ProviderOperation('domains.renew','k',['resellerId'=>'*','name'=>'a.example','years'=>1,'renewPrivacy'=>'yes']))),'renewPrivacy must be boolean');

// ---- structural node bound across valid per-package containers ----
// id list: 2; package map: 1; one JSON tree: root + 4 arrays + 248 leaves = 253.
$edge=['resellerId'=>'*','id'=>[42],'billing'=>[42=>[array_fill(0,64,1),array_fill(0,64,1),array_fill(0,64,1),array_fill(0,56,1)]]];
check(!refused(static fn()=>$builder->build(new ProviderOperation('packages.update','k',$edge))),'exact-boundary per-package tree builds (256 nodes; synthetic structure only)');
$over=$edge;$over['billing'][42][3][]=1;
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.update','k',$over))),'one leaf over the 256-node bound refuses');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.update','k',['resellerId'=>'*','id'=>[42],'billing'=>[42=>array_fill(0,6,array_fill(0,64,1))]]))),'flat scalar leaves still count toward the bound');
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.update','k',['resellerId'=>'*','id'=>[42],'billing'=>[42=>array_fill(0,65,1)]]))),'nested list still refuses at the 64-item array bound');
foreach([42,[],[0],[1,1],['1'],array_fill(0,65,1),['id'=>1]] as $i=>$bad) {
    check(refused(static fn()=>$builder->build(new ProviderOperation('packages.delete','k',['resellerId'=>'*','delete-id'=>$bad]))),'invalid delete ID list refuses '.$i);
}
$delete=$builder->build(new ProviderOperation('packages.delete','k',['resellerId'=>'*','delete-id'=>[42,77]]));
check($delete->url==='https://api.20i.com/reseller/*/deleteWeb' && $delete->body==='{"delete-id":[42,77]}','delete emits exact documented array payload');
foreach([
    ['id'=>[42],'delete-id'=>[42]],
    ['id'=>[42],'labels'=>['other'=>'value']],
    ['id'=>[42],'labels'=>[77=>'value']],
    ['id'=>42,'labels'=>[42=>'value']],
    ['delete-id'=>[42],'labels'=>[42=>'value']],
] as $i=>$bad)check(refused(static fn()=>$builder->build(new ProviderOperation('packages.update','k',['resellerId'=>'*']+$bad))),'wrong/mixed package ownership refuses '.$i);
// hostname_list children count in the same shared budget: create with 64 extra domains + 188 map entries = 256 nodes.
$mixed=['resellerId'=>'*','type'=>'synthetic','domain_name'=>'a.example','extra_domain_names'=>array_fill(0,64,'b.example'),'documentRoots'=>[]];
for($i=0;$i<188;$i++)$mixed['documentRoots']['d'.$i]='public_html';
check(!refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',$mixed))),'hostname-list children inside the shared bound build (256 nodes)');
$mixed['documentRoots']['d188']='public_html';
check(refused(static fn()=>$builder->build(new ProviderOperation('packages.create','k',$mixed))),'one node over with hostname-list children refuses (257 nodes)');

// Constructible requests are not public approval authority.
check($register->genericNames()===['packages.update'],'generic menu exposes only the reviewed label subset');
check($register->allowsGenericApproval('packages.update',['resellerId'=>'*','id'=>[42],'labels'=>[42=>'Client site']]),'explicit label-only approval is eligible');
foreach([
 ['packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'a.example']],
 ['domains.renew',['resellerId'=>'*','name'=>'a.example','years'=>1]],
 ['packages.delete',['resellerId'=>'*','delete-id'=>[42]]],
 ['packages.update',['resellerId'=>'*','id'=>[42],'labels'=>[42=>'a'],'delete-id'=>[77]]],
 ['packages.update',['resellerId'=>'*','id'=>[42],'labels'=>[42=>'a'],'billing'=>[42=>[]]]],
 ['packages.update',['resellerId'=>'*','id'=>[42],'labels'=>[42=>'a'],'sendWelcomeEmail'=>true]],
 ['packages.update',['resellerId'=>'*','id'=>[42],'labels'=>[42=>['nested'=>'a']]]],
 ['packages.update',['resellerId'=>'*','id'=>[42]]],
] as $i=>[$name,$params])check(!$register->allowsGenericApproval($name,$params),'generic approval refuses high-risk or widened subset '.$i);

// ---- operation semantics: acknowledgement is not active fulfillment ----
foreach([200,201,202] as $status) {
    $accepted=TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse($status,[],'{"result":123}'));
    check($accepted->status===OperationResult::ACCEPTED_PENDING && $accepted->externalId==='123' && $accepted->needsReconciliation() && $accepted->isFinal(),'allocated package ID retained pending confirmation '.$status);
}
foreach(['false','null','true','{}','[]','not json','{"id":123}','{"result":false}','{"result":null}','{"result":"123"}','{"result":0}','{"result":123,"error":"partial"}','{"result":{"123":true,"456":false}}'] as $i=>$body) {
    $r=TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse(200,[],$body));
    check($r->status===OperationResult::UNCERTAIN && $r->externalId===null && $r->needsReconciliation(),'unverified or mixed response never completes a service '.$i);
}
check(TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse(200,[],str_repeat('x',65537)))->providerCode==='response_limit','oversized write response stays uncertain');
foreach(['packages.update','packages.delete','domains.renew'] as $name) {
    check(TwentyIResultClassifier::fromResponse($name,new TransportResponse(200,[],'{"result":123}'))->status===OperationResult::UNCERTAIN,'create response contract cannot be reused for '.$name);
}
foreach([203,204,205,206] as $status) {
    check(TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse($status,[],''))->status===OperationResult::UNCERTAIN,'unverified HTTP success remains uncertain '.$status);
}
foreach([400,404,405,422,499] as $s){
    $r=TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse($s,[],'{"error":"x"}'));
    check($r->status===OperationResult::FAILED&&$r->providerCode==='http_'.$s,'4xx rejects as failed '.$s);
}
foreach([401,403] as $s){
    $r=TwentyIResultClassifier::fromResponse('packages.create',new TransportResponse($s,[],'SYNTHETIC_RAW_GENERAL_KEY'));
    check($r->status===OperationResult::FAILED&&$r->providerCode==='authentication'&&!str_contains($r->message,'SYNTHETIC'),'auth failure bounded without body echo '.$s);
}
foreach([301,302,307,308,408,409,425,429,500,502,503,599] as $s){
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
