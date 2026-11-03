<?php
declare(strict_types=1);
// Portable production-body checks only: synthetic transports and in-memory ledger; no network or SQL.
require __DIR__.'/twentyi/bootstrap.php';
foreach (['TwentyIOperation','TwentyIOperationRegister','TwentyIRequestBuilder','TwentyIResultClassifier','TwentyIOperationAdapter'] as $c) require_once __DIR__.'/../foundation/twentyi-operations/'.$c.'.php';
require_once __DIR__.'/../foundation/twentyi-registrar-nameservers/RegisteredNameservers.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException,ProviderOperation,ProviderGateway,OperationLedger,LedgerClaim,OperationResult,RetryPolicy};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyI\Operations\{NameserverChange,RegistrarNameserverChange,TwentyIOperationRegister,TwentyIRequestBuilder,TwentyIOperationAdapter,TwentyIResultClassifier};
use CajunWebPro\Foundation\TwentyIRegistrarNameservers\{RegisteredNameserverBinding,RegisteredNameserverObservation,RegisteredNameservers};

$checks=[];
function rn_ok(string $name,bool $pass):void { global $checks; if (in_array($name,array_column($checks,'name'),true)) throw new LogicException('Duplicate check name.'); $checks[]=['name'=>$name,'passed'=>$pass]; if (!$pass) throw new RuntimeException($name); }
function rn_refuses(callable $fn):bool { try {$fn();return false;} catch (InvalidArgumentException) {return true;} }
function rn_data():array { return ['profile_id'=>str_repeat('a',32),'profile_revision'=>1,'key_fingerprint'=>str_repeat('b',64),'domain_id'=>701,'domain'=>'owned.synthetic.invalid','link_id'=>str_repeat('c',32),'link_revision'=>1,'client_id'=>3,'link_event_id'=>4,'link_event_sha256'=>str_repeat('d',64),'link_audit_id'=>5,'link_audit_sha256'=>str_repeat('e',64),'source_refresh_id'=>str_repeat('f',32),'source_seq'=>6,'source_sha256'=>str_repeat('0',64)]; }
final class RnTransport implements HttpTransport {
    public array $sent=[];
    public function __construct(public TransportResponse|Throwable $reply) {}
    public function send(TransportRequest $request):TransportResponse { $this->sent[]=$request;if ($this->reply instanceof Throwable) throw $this->reply;return $this->reply; }
}
final class RnLedger implements OperationLedger {
    public array $rows=[];public bool $failRecord=false;
    public function claim(string $key,string $fingerprint,int $now):LedgerClaim {
        if (!isset($this->rows[$key])) {$this->rows[$key]=[$fingerprint,null];return LedgerClaim::claimed();}
        if ($this->rows[$key][0]!==$fingerprint) return LedgerClaim::conflict();
        return $this->rows[$key][1]===null?LedgerClaim::uncertain():LedgerClaim::replay($this->rows[$key][1]);
    }
    public function record(string $key,string $fingerprint,OperationResult $result,int $now):void {
        if ($this->failRecord) throw new RuntimeException('Synthetic record failure');
        if (!isset($this->rows[$key]) || $this->rows[$key][0]!==$fingerprint || $this->rows[$key][1]!==null) throw new LogicException('Invalid modeled record.');
        $this->rows[$key][1]=$result;
    }
}
$b=new RegisteredNameserverBinding(rn_data());$at=1700000000;
$project=static fn(string $body,int $status=200)=>RegisteredNameservers::project(new TransportResponse($status,[],$body),$b,$at);
$r=RegisteredNameservers::request(701);
rn_ok('Registrar GET uses the allocated domain ID and no package slot',$r->method==='GET'&&$r->url==='https://api.20i.com/domain/701/nameservers'&&$r->headers===['Accept'=>'application/json']&&$r->body===null&&$r->timeoutSeconds===15.0);
$observed=$project('{"result":["NS2.EXAMPLE.","ns1.example"]}');
rn_ok('SDK result string list reuses canonical nameserver normalization',$observed->state==='complete'&&$observed->nameservers===['ns1.example','ns2.example']&&$observed->domainId===701&&$observed->domainName==='owned.synthetic.invalid'&&$observed->observedAt===$at);
rn_ok('Read scope is distinct from contract and package scopes',str_contains($b->scope,'|registrar.nameservers.read|')&&strlen($b->scope)<=190);
rn_ok('An exact empty result remains observed empty without inventing permission',$project('{"result":[]}')->nameservers===[]);
rn_ok('Valid JSON whitespace and escaped key are accepted',$project(" { \"res\\u0075lt\" : [ \"ns1.example\" ] }\n")->nameservers===['ns1.example']);
foreach (['[]','null','true','"ns1.example"','{"result":null}','{"result":{}}','{"result":"ns1.example"}','{"nameservers":["ns1.example"]}','{"result":["ns1.example"],"error":"private"}','{"result":["ns1.example"],"result":["ns2.example"]}','{"result":["ns1.example"],"res\\u0075lt":["ns2.example"]}'] as $i=>$body) rn_ok('Unknown mixed or duplicate response shape refuses '.$i,$project($body)->state==='unavailable'&&$project($body)->nameservers===null);
foreach (['{','{"result":[NaN]}',"{\"result\":[\"bad\x80\"]}",'{"result":["\\ud800"]}'] as $i=>$body) rn_ok('Malformed nonfinite or invalid Unicode JSON refuses '.$i,$project($body)->reason==='malformed_body');
foreach ([['ns1.example','NS1.EXAMPLE.'],['127.0.0.1'],['https://ns1.example'],['ns1.example:53'],['ns1.example/path'],['bad..example'],[' ns1.example'],['ns1.example '],['localhost'],['nämé.example'],[1],[null],[true],[['ns1.example']],array_fill(0,65,'ns1.example')] as $i=>$names) rn_ok('Invalid observed nameservers refuse '.$i,$project(json_encode(['result'=>$names],JSON_THROW_ON_ERROR))->reason==='invalid_nameservers');
foreach ([401=>'authentication',403=>'authentication',429=>'rate_limited',500=>'remote_error',202=>'unexpected_status',204=>'unexpected_status',301=>'unexpected_status',404=>'unexpected_status'] as $status=>$reason) rn_ok('Read status stays unavailable '.$status,$project('{"result":["ns1.example"]}',$status)->reason===$reason);
rn_ok('Oversize provider body refuses',$project(str_repeat(' ',65537))->reason==='response_limit');
rn_ok('Invalid observed time refuses',rn_refuses(fn()=>RegisteredNameservers::project(new TransportResponse(200,[],'{}'),$b,-1)));
foreach ([0,-1,'701',1.5,'owned.synthetic.invalid',null,1000000000000000000] as $i=>$id) rn_ok('Registrar ID input refuses '.$i,rn_refuses(fn()=>RegisteredNameservers::request($id)));
foreach (['domain_id'=>'701','domain'=>'UPPER.invalid','profile_revision'=>0,'source_seq'=>0,'link_audit_sha256'=>'bad'] as $key=>$value) {$data=rn_data();$data[$key]=$value;rn_ok('Binding remains strict '.$key,rn_refuses(fn()=>new RegisteredNameserverBinding($data)));}
$data=rn_data();$data['packageId']=701;rn_ok('Package slot cannot masquerade as registrar binding',rn_refuses(fn()=>new RegisteredNameserverBinding($data)));
$data=rn_data();$data['domain_id']=702;rn_ok('Registrar identity changes bound scope',(new RegisteredNameserverBinding($data))->scope!==$b->scope);
$ref='owned.synthetic.invalid';$data=rn_data();$data['domain']=&$ref;$detached=new RegisteredNameserverBinding($data);$ref='other.synthetic.invalid';rn_ok('Exact provenance detaches external references',$detached->data['domain']==='owned.synthetic.invalid');
$resolves=0;$credential=new ProviderCredential('twenty-i',$b->data['profile_id'],function()use(&$resolves):string {$resolves++;return 'SYNTHETIC_NS_KEY';});
$transport=new RnTransport(new TransportResponse(200,[],'{"result":["ns1.example","ns2.example"]}'));
$read=RegisteredNameservers::read($transport,$credential,$b,$at);
rn_ok('Reader resolves one credential and sends one exact request',$resolves===1&&count($transport->sent)===1&&$transport->sent[0]->url===$r->url&&$transport->sent[0]->headers===['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_NS_KEY'),'Accept'=>'application/json']&&$read->state==='complete');
rn_ok('Observation does not expose the credential',!str_contains(json_encode($read),'SYNTHETIC_NS_KEY'));
rn_ok('Wrong provider account refuses before credential resolution',rn_refuses(fn()=>RegisteredNameservers::read($transport,new ProviderCredential('twenty-i',str_repeat('9',32),fn()=>throw new LogicException('Unexpected credential access')),$b,$at))&&$resolves===1);
foreach (['ns1.example','NS1.EXAMPLE','ns1'] as $i=>$secret) {$t=new RnTransport(new TransportResponse(200,[],json_encode(['result'=>[$secret==='NS1.EXAMPLE'?'NS1.EXAMPLE':'ns1.example']])));rn_ok('Decoded credential echo refuses before storage '.$i,RegisteredNameservers::read($t,new ProviderCredential('twenty-i',$b->data['profile_id'],fn()=>$secret),$b,$at)->reason==='invalid_nameservers');}
foreach (['connect','timeout','tls','protocol','unavailable','unknown'] as $kind) {$t=new RnTransport(new TransportException($kind));$s=RegisteredNameservers::read($t,$credential,$b,$at);rn_ok('Read failure is finite and sends once '.$kind,$s->reason==='transport_'.$kind&&$s->nameservers===null&&count($t->sent)===1);}
$t=new RnTransport(new RuntimeException('Authority changed'));
try {RegisteredNameservers::read($t,$credential,$b,$at);rn_ok('Unknown authority failures propagate',false);} catch (RuntimeException $e) {rn_ok('Unknown authority failures propagate',$e->getMessage()==='Authority changed'&&count($t->sent)===1);}

$builder=new TwentyIRequestBuilder(new TwentyIOperationRegister());$desired=NameserverChange::observed(['NS4.EXAMPLE.','ns3.example']);
$op=new ProviderOperation(RegistrarNameserverChange::OPERATION,'synthetic-registered-ns',['domainId'=>$read->domainId,'ns'=>$desired,'old-ns'=>$read->nameservers]);$write=$builder->build($op);
rn_ok('Registrar write includes entire desired and entire observed sets',$write->url===$r->url&&$write->method==='POST'&&$write->body==='{"ns":["ns3.example","ns4.example"],"old-ns":["ns1.example","ns2.example"]}'&&!isset($write->headers['Authorization']));
rn_ok('Read identity and complete original set are bound explicitly by admission',$op->params['domainId']===$read->domainId&&$op->params['old-ns']===$read->nameservers&&$read->binding->sha256===$b->sha256);
// Only the legacy package-domain API uses subtraction. Registrar fields above never do.
$packageChange=NameserverChange::between($read->nameservers,['ns2.example','ns3.example']);
$old=$builder->build(new ProviderOperation('domains.nameservers','legacy',['packageId'=>201,'domainId'=>901]+$packageChange->fields()));
rn_ok('Original package-domain delta behavior remains unchanged',$old->url==='https://api.20i.com/package/201/domain/901/nameservers'&&$old->body==='{"ns":["ns3.example"],"old-ns":["ns1.example"]}');
$register=new TwentyIOperationRegister();rn_ok('Both nameserver operations remain outside generic owner approval',$register->genericNames()===['packages.update']&&!$register->allowsGenericApproval($op->name,$op->params)&&!$register->allowsGenericApproval('domains.nameservers',['packageId'=>201,'domainId'=>901]+$packageChange->fields()));
foreach ([['ns'=>['ns3.example'],'old-ns'=>[]],['ns'=>[],'old-ns'=>['ns1.example']],['ns'=>['ns2.example','ns3.example'],'old-ns'=>['ns1.example','ns2.example']],['ns'=>['ns1.example'],'old-ns'=>['NS1.EXAMPLE.']],['ns'=>['NS2.EXAMPLE.','NS1.EXAMPLE'],'old-ns'=>['ns1.example','ns2.example']]] as $i=>$fields) rn_ok('Empty add-only remove-only overlapping or no-op registrar replacement refuses '.$i,rn_refuses(fn()=>$builder->build(new ProviderOperation($op->name,'restricted',['domainId'=>701]+$fields))));
$full=RegistrarNameserverChange::params(['domainId'=>701,'ns'=>['NS4.EXAMPLE.','ns3.example'],'old-ns'=>['NS2.EXAMPLE.','ns1.example']]);
rn_ok('Registrar normalization preserves all full-set members',$full===['domainId'=>701,'ns'=>['ns3.example','ns4.example'],'old-ns'=>['ns1.example','ns2.example']]);
foreach ([['domainId'=>701,'ns'=>[],'old-ns'=>[]],['domainId'=>701,'ns'=>['ns1.example'],'old-ns'=>['NS1.EXAMPLE.']],['domainId'=>701,'ns'=>['ns3.example'],'old-ns'=>['ns1.example'],'packageId'=>201],['domainId'=>'owned.synthetic.invalid','ns'=>['ns3.example'],'old-ns'=>[]],['domainId'=>701,'ns'=>['ns3.example']],['domainId'=>701,'ns'=>['127.0.0.1'],'old-ns'=>[]]] as $i=>$params) rn_ok('Malformed widened registrar write refuses '.$i,rn_refuses(fn()=>$builder->build(new ProviderOperation($op->name,'bad',$params))));

$fixed=new FixedHttpTransport('8.8.8.8',static fn()=>throw new LogicException('No network permitted in fixture'));$validate=new ReflectionMethod(FixedHttpTransport::class,'validate');
$validate->invoke($fixed,$transport->sent[0]);rn_ok('Fixed transport admits exact registrar GET',true);
$authenticated=new TransportRequest('POST',$write->url,['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_NS_KEY'),'Accept'=>'application/json','Content-Type'=>'application/json'],$write->body,15.0);
$validate->invoke($fixed,$authenticated);rn_ok('Fixed transport admits canonical registrar POST',true);
$badUrls=['http://api.20i.com/domain/701/nameservers','https://api.20i.com:443/domain/701/nameservers','https://evil.invalid/domain/701/nameservers','https://api.20i.com/domain/0/nameservers','https://api.20i.com/domain/0701/nameservers','https://api.20i.com/domain/owned.synthetic.invalid/nameservers','https://api.20i.com/domain/701/nameservers?x=1','https://api.20i.com/domain/701/nameservers/','https://api.20i.com/domain/701%2f/nameservers','https://api.20i.com/package/201/domain/701/nameservers','https://api.20i.com/domain/701/authCode'];
foreach ($badUrls as $i=>$url) {rn_ok('Reader route is exact '.$i,!RegisteredNameservers::acceptsUrl($url));foreach (['GET','POST'] as $method) rn_ok('Transport refuses alternate route '.$method.' '.$i,rn_refuses(fn()=>$validate->invoke($fixed,new TransportRequest($method,$url,$method==='GET'?$transport->sent[0]->headers:$authenticated->headers,$method==='GET'?null:$write->body,15.0))));}
foreach (['{"ns":["ns3.example"],"old-ns":["ns1.example"],"force":true}','{"old-ns":["ns1.example"],"ns":["ns3.example"]}','{"ns":["ns3.example"],"ns":["ns4.example"],"old-ns":["ns1.example"]}','{"ns":["NS3.EXAMPLE"],"old-ns":["ns1.example"]}','{"ns":[],"old-ns":[]}','{"ns":["ns3.example"],"old-ns":[]}','{"ns":[],"old-ns":["ns1.example"]}','{"ns":["ns2.example","ns3.example"],"old-ns":["ns1.example","ns2.example"]}'] as $i=>$body) rn_ok('Transport refuses noncanonical or ambiguous write '.$i,rn_refuses(fn()=>$validate->invoke($fixed,new TransportRequest('POST',$write->url,$authenticated->headers,$body,15.0))));
rn_ok('GET cannot carry a body',rn_refuses(fn()=>$validate->invoke($fixed,new TransportRequest('GET',$r->url,$transport->sent[0]->headers,'{}',15.0))));
rn_ok('Transport timeout cannot exceed original bound',rn_refuses(fn()=>$validate->invoke($fixed,new TransportRequest('GET',$r->url,$transport->sent[0]->headers,null,16.0))));

foreach (['true','null','{"result":true}','{"result":["ns2.example","ns3.example"]}'] as $i=>$body) rn_ok('Successful POST body never establishes DNS completion '.$i,TwentyIResultClassifier::fromResponse($op->name,new TransportResponse(200,[],$body))->status===OperationResult::UNCERTAIN);
foreach (['timeout','protocol'] as $kind) {
    $t=new RnTransport(new TransportException($kind));$ledger=new RnLedger();$gateway=new ProviderGateway(new TwentyIOperationAdapter(),$t,$ledger,new RetryPolicy(4,0));$first=$gateway->perform($credential,$op);$replay=$gateway->perform($credential,$op);
    rn_ok('Uncertain write is one send and replay never retries '.$kind,$first->status===OperationResult::UNCERTAIN&&$first->attempts===1&&$replay->status===OperationResult::UNCERTAIN&&count($t->sent)===1);
}
$t=new RnTransport(new TransportResponse(200,[],'true'));$ledger=new RnLedger();$gateway=new ProviderGateway(new TwentyIOperationAdapter(),$t,$ledger);$first=$gateway->perform($credential,$op);
$later=RegisteredNameservers::read(new RnTransport(new TransportResponse(200,[],'{"result":["ns4.example","ns3.example"]}')),$credential,$b,$at+1);
rn_ok('Separate later GET may observe the desired set without rewriting the terminal',$later->observedAt>$read->observedAt&&$later->binding->sha256===$read->binding->sha256&&$later->nameservers===$desired&&$first->status===OperationResult::UNCERTAIN&&$gateway->perform($credential,$op)->status===OperationResult::UNCERTAIN&&count($t->sent)===1);
$different=RegisteredNameservers::read(new RnTransport(new TransportResponse(200,[],'{"result":["ns1.example"]}')),$credential,$b,$at+2);rn_ok('Mismatched readback cannot claim desired nameservers',$different->nameservers!==$desired&&$gateway->perform($credential,$op)->status===OperationResult::UNCERTAIN&&count($t->sent)===1);
rn_ok('Same idempotency key with another registrar ID conflicts',$gateway->perform($credential,new ProviderOperation($op->name,$op->idempotencyKey,array_replace($op->params,['domainId'=>702])))->status===OperationResult::CONFLICT&&count($t->sent)===1);
$ledger=new RnLedger();$ledger->failRecord=true;$t=new RnTransport(new TransportResponse(200,[],'true'));$gateway=new ProviderGateway(new TwentyIOperationAdapter(),$t,$ledger);$first=$gateway->perform($credential,$op);$again=$gateway->perform($credential,$op);
rn_ok('Lost local terminal leaves original reservation uncertain without resend',$first->status===OperationResult::UNCERTAIN&&$again->status===OperationResult::UNCERTAIN&&count($t->sent)===1);
echo json_encode(['passed'=>true,'count'=>count($checks),'scope'=>'Synthetic transport and modeled ledger only; no provider, DNS, SQL or native owner authorization. No live DNS change or completion claim.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
