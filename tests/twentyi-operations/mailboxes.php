<?php
declare(strict_types=1);
// Isolated contract/gateway fixtures. No provider connection, database or application authority is exercised.
require __DIR__.'/../twentyi/bootstrap.php';
require __DIR__.'/../provider/FileLedger.php';
foreach (['TwentyIOperation','TwentyIOperationRegister','TwentyIRequestBuilder','TwentyIResultClassifier','TwentyIOperationAdapter'] as $class) {
    require_once __DIR__.'/../../foundation/twentyi-operations/'.$class.'.php';
}

use CajunWebPro\Foundation\Provider\{ProviderOperation,ProviderCredential,ProviderGateway,OperationResult,TransportRequest,TransportResponse,HttpTransport,TransportException,OperationLedger,LedgerClaim};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyI\Operations\{MailboxCreate,TwentyIOperationRegister,TwentyIRequestBuilder,TwentyIOperationAdapter};

$checks=[];
function mc_check(bool $pass,string $name):void {global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function mc_refused(callable $work):bool {try{$work();return false;}catch(InvalidArgumentException){return true;}}
function mc_response(string $local='info',string $domain='example.invalid',string $password='SYNTHETIC-MAILBOX-ONLY'):string {
    return json_encode(['result'=>['result'=>[['type'=>'mailbox','id'=>$local,'generatedId'=>null,'password'=>$password]],'name'=>$domain]],JSON_THROW_ON_ERROR);
}

final class MailboxFixtureTransport implements HttpTransport {
    public int $calls=0;
    public ?TransportRequest $request=null;
    public string $mode='success';
    public function send(#[SensitiveParameter] TransportRequest $request):TransportResponse {
        ++$this->calls;$this->request=$request;
        if($this->mode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
        return new TransportResponse(200,[],mc_response());
    }
}

$path=tempnam(sys_get_temp_dir(),'cwp-mailbox-ledger-');
if($path===false)throw new RuntimeException('Temporary fixture unavailable.');
try {
    $params=['packageId'=>201,'domain'=>'example.invalid','local'=>'info','password_ref'=>str_repeat('a',32)];
    $op=new ProviderOperation(MailboxCreate::OPERATION,'mailbox-one',$params);
    $resolved=0;
    $resolver=static function(string $ref,ProviderOperation $operation)use(&$resolved,$params):string {
        ++$resolved;
        if($ref!==$params['password_ref'] || $operation->params!==$params)throw new RuntimeException('Fixture context mismatch.');
        return 'SYNTHETIC-MAILBOX-ONLY';
    };
    $register=new TwentyIOperationRegister();
    mc_check(!$register->allowsGenericApproval($op->name,$params),'Generic operation grants cannot authorize mailbox access');
    mc_check(!(new TwentyIOperationAdapter())->supports($op->name),'Default operation adapter cannot execute mailbox creation');
    mc_check(mc_refused(fn()=>(new TwentyIRequestBuilder($register))->build($op)),'Building without a dedicated password resolver is refused');
    $builder=new TwentyIRequestBuilder($register,$resolver);
    $request=$builder->build($op);
    mc_check($resolved===1 && $request->url==='https://api.20i.com/package/201/email/example.invalid' && $request->method==='POST','Exact approved package/domain mailbox endpoint');
    $payload=json_decode($request->body,true,8,JSON_THROW_ON_ERROR);
    mc_check($payload===['new'=>['mailbox'=>['local'=>'info','send'=>'true','receive'=>'true','password'=>'SYNTHETIC-MAILBOX-ONLY']]],'Documented nested creation payload uses string send/receive flags');
    mc_check(!str_contains(json_encode($op),'SYNTHETIC-MAILBOX-ONLY') && !str_contains($request->body,$params['password_ref']),'Operation metadata contains an opaque reference and provider body omits that reference');
    mc_check(!isset($request->headers['Authorization']),'Request builder does not attach API credentials');

    foreach ([['password'=>'SYNTHETIC-MAILBOX-ONLY'],['new'=>['mailbox'=>[]]],['send'=>false],['domainId'=>55]] as $i=>$extra) {
        $before=$resolved;
        mc_check(mc_refused(fn()=>$builder->build(new ProviderOperation($op->name,'bad',$params+$extra))) && $resolved===$before,'Extra/raw-secret operation parameters refused before resolution '.$i);
    }
    foreach (['packageId'=>[0,-1,'201',1.2,1000000000000000000], 'domain'=>['EXAMPLE.invalid','example.invalid/other','example.invalid?x=1','example.invalid#x','example.invalid.','127.0.0.1','x..invalid','%65xample.invalid'], 'local'=>['','Info','a..b','.info','info.','a@b','a/b',str_repeat('x',65)], 'password_ref'=>['password',str_repeat('a',31),str_repeat('A',32)]] as $field=>$values) {
        foreach($values as $i=>$value){
            $bad=$params;$bad[$field]=$value;$before=$resolved;
            mc_check(mc_refused(fn()=>$builder->build(new ProviderOperation($op->name,'bad',$bad))) && $resolved===$before,'Malformed '.$field.' refused before secret resolution '.$i);
        }
    }
    foreach (['short',str_repeat('a',129),"SYNTHETIC\nPASSWORD",null,123] as $i=>$password) {
        mc_check(mc_refused(fn()=>MailboxCreate::request($op,static fn()=>$password)),'Invalid resolved password refused '.$i);
    }
    mc_check(MailboxCreate::local('contact.team+sales-2')==='contact.team+sales-2','Bounded punctuation in a mailbox local part remains supported');

    $r=MailboxCreate::result($op,$request,new TransportResponse(200,[],mc_response()));
    mc_check($r->status===OperationResult::SUCCEEDED && $r->externalId==='info','Exact documented response confirms the requested mailbox with its provider local ID');
    mc_check(!str_contains(json_encode($r),'SYNTHETIC') && !str_contains(serialize($r),'password'),'Confirmed result drops every echoed password');
    $long=$params;$long['local']=str_repeat('a',64);$long['domain']=str_repeat('b',63).'.'.str_repeat('c',63).'.invalid';
    $longOp=new ProviderOperation($op->name,'long-address',$long);
    $longRequest=MailboxCreate::request($longOp,static fn()=>'SYNTHETIC-MAILBOX-ONLY');
    $longResult=MailboxCreate::result($longOp,$longRequest,new TransportResponse(200,[],mc_response($long['local'],$long['domain'])));
    mc_check($longResult->status===OperationResult::SUCCEEDED && strlen($longResult->externalId)===64,'Long valid addresses retain only the provider local ID within the existing ledger column');
    $badResponses=['true','false','null','{}','[]','{bad',mc_response('other'),mc_response('info','other.invalid'),mc_response('info','example.invalid','WRONG-SYNTHETIC-PASSWORD')];
    $base=json_decode(mc_response(),true);
    $bad=$base;$bad['error']='SYNTHETIC-SECRET';$badResponses[]=json_encode($bad);
    $bad=$base;$bad['result']['result'][]=$bad['result']['result'][0];$badResponses[]=json_encode($bad);
    $bad=$base;$bad['result']['result'][0]['type']='forwarder';$badResponses[]=json_encode($bad);
    $bad=$base;$bad['result']['result'][0]['generatedId']='123';$badResponses[]=json_encode($bad);
    $bad=$base;unset($bad['result']['result'][0]['password']);$badResponses[]=json_encode($bad);
    $bad=$base;$bad['result']['result'][0]['password']=['SYNTHETIC-SECRET'];$badResponses[]=json_encode($bad);
    $badResponses[]=str_repeat('x',65537);
    foreach($badResponses as $i=>$body){
        $r=MailboxCreate::result($op,$request,new TransportResponse(200,[],$body));
        mc_check($r->status===OperationResult::UNCERTAIN && !str_contains(json_encode($r),'SYNTHETIC'),'Mismatched or ambiguous creation response remains unknown without secret echo '.$i);
    }
    foreach ([202,301,408,409,425,429,500,503] as $status) {
        mc_check(MailboxCreate::result($op,$request,new TransportResponse($status,[],mc_response()))->status===OperationResult::UNCERTAIN,'HTTP status alone cannot confirm creation '.$status);
    }
    foreach ([400,401,403,404,422] as $status) {
        mc_check(MailboxCreate::result($op,$request,new TransportResponse($status,[],'SYNTHETIC-SECRET'))->status===OperationResult::FAILED,'Definite provider rejection is bounded '.$status);
    }

    $credentialCalls=0;$credential=new ProviderCredential('twenty-i','fixture-account',static function()use(&$credentialCalls):string{++$credentialCalls;return 'SYNTHETIC-API-ONLY';});
    $ledger=new FileLedger($path);$transport=new MailboxFixtureTransport();
    $before=file_get_contents($path);$priorResolutions=$resolved;
    $unsupported=(new ProviderGateway(new TwentyIOperationAdapter(),$transport,$ledger))->perform($credential,$op);
    mc_check($unsupported->status===OperationResult::UNSUPPORTED && file_get_contents($path)===$before && $transport->calls===0 && $credentialCalls===0 && $resolved===$priorResolutions,'Default gateway refuses mailbox creation before ledger, transport or secret resolution');
    $gateway=new ProviderGateway(new TwentyIOperationAdapter($resolver),$transport,$ledger);
    $before=$resolved;$result=$gateway->perform($credential,$op);
    mc_check($result->status===OperationResult::SUCCEEDED && $transport->calls===1 && $resolved===$before+1 && $credentialCalls===1,'Existing gateway claims then resolves each credential and dispatches once');
    mc_check(array_keys($transport->request->headers)===['Authorization','Accept','Content-Type'] && $transport->request->headers['Authorization']==='Bearer '.base64_encode('SYNTHETIC-API-ONLY'),'Existing adapter attaches the expected fixed authentication headers');
    $replay=$gateway->perform($credential,$op);
    mc_check($replay->replayed && $transport->calls===1 && $resolved===$before+1 && $credentialCalls===1,'Gateway replay performs no transport or secret resolution');
    $other=$params;$other['local']='support';
    mc_check($gateway->perform($credential,new ProviderOperation($op->name,$op->idempotencyKey,$other))->status===OperationResult::CONFLICT && $transport->calls===1,'Same key cannot create a different mailbox');
    $transport->mode='timeout';$unknown=new ProviderOperation($op->name,'mailbox-timeout',$params);
    $result=$gateway->perform($credential,$unknown);
    mc_check($result->status===OperationResult::UNCERTAIN && $result->attempts===1 && $transport->calls===2,'Post-send timeout remains uncertain after one attempt');
    $result=$gateway->perform($credential,$unknown);
    mc_check($result->replayed && $transport->calls===2,'Unknown creation cannot be automatically resubmitted');
    mc_check(!str_contains(file_get_contents($path),'SYNTHETIC') && !str_contains(file_get_contents($path),'password'),'Retained file-ledger fixture contains neither API nor mailbox password');
    $transport->mode='success';
    $failLedger=new class($ledger) implements OperationLedger {
        public function __construct(private OperationLedger $inner){}
        public function claim(string $idempotencyKey,string $fingerprint,int $now):LedgerClaim{return $this->inner->claim($idempotencyKey,$fingerprint,$now);}
        public function record(string $idempotencyKey,string $fingerprint,OperationResult $result,int $now):void{throw new RuntimeException('Synthetic storage failure.');}
    };
    $failedGateway=new ProviderGateway(new TwentyIOperationAdapter($resolver),$transport,$failLedger);
    $writeFailure=new ProviderOperation($op->name,'mailbox-record-failure',$params);
    $result=$failedGateway->perform($credential,$writeFailure);
    mc_check($result->status===OperationResult::UNCERTAIN && $transport->calls===3,'Failed result persistence does not claim confirmed creation');
    $failedGateway->perform($credential,$writeFailure);
    mc_check($transport->calls===3,'Unfinished ledger claim prevents another create after result storage failure');
    $revoked=new ProviderOperation($op->name,'mailbox-authority-revoked',$params);
    $revokedGateway=new ProviderGateway(new TwentyIOperationAdapter(static function():string{throw new RuntimeException('Approval revoked.');}),$transport,$ledger);
    $callsBefore=$credentialCalls;
    $denied=false;
    try{$revokedGateway->perform($credential,$revoked);}catch(RuntimeException){$denied=true;}
    mc_check($denied && $transport->calls===3 && $credentialCalls===$callsBefore,'Secret resolver authority refusal stops API credentials and transport');
    $gateway->perform($credential,$revoked);
    mc_check($transport->calls===3,'A lost authority check after claim cannot later resume the original request');

    $authorized=0;$fixed=new FixedHttpTransport('8.8.8.8',static function()use(&$authorized):void{++$authorized;throw new LogicException('Stop before network.');});
    $authenticated=$transport->request;
    try{$fixed->send($authenticated);mc_check(false,'Expected pre-network stop');}catch(LogicException){mc_check($authorized===1,'Exact mailbox request reaches current-authority check before network');}
    foreach (['https://api.20i.com/package/201/email/example.invalid?x=1','https://api.20i.com/package/201/email/example.invalid/delete','https://api.20i.com/package/0/email/example.invalid','https://api.20i.com/package/201/userStatus','https://other.invalid/package/201/email/example.invalid'] as $i=>$url) {
        mc_check(mc_refused(fn()=>$fixed->send(new TransportRequest('POST',$url,$authenticated->headers,$authenticated->body))) && $authorized===1,'Expanded mail path refused before authorization/network '.$i);
    }
    foreach (['{}','{"delete":["info"]}',str_replace('"true"','true',$authenticated->body),str_replace('"new":','"password":"SECRET","new":',$authenticated->body)] as $i=>$body) {
        mc_check(mc_refused(fn()=>$fixed->send(new TransportRequest('POST',$authenticated->url,$authenticated->headers,$body))) && $authorized===1,'Expanded mail body refused before authorization/network '.$i);
    }
    $report=['passed'=>true,'scope'=>'Synthetic documented mailbox request/response, shared gateway/file-ledger replay and fixed transport admission. No network, production database, native UI or live account acceptance.','checks'=>$checks];
} catch(Throwable $e) {
    $report=['passed'=>false,'checks'=>$checks,'failure'=>get_class($e)];
    if(isset($argv[1]))file_put_contents($argv[1],json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
    throw $e;
} finally { unlink($path); }
if(isset($argv[1]))file_put_contents($argv[1],json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo count($checks)." synthetic mailbox operation checks passed\n";
