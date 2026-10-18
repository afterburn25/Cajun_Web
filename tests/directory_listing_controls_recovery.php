<?php
declare(strict_types=1);
use CajunWebPro\Foundation\Provider\{ProviderAdapter,ProviderCapability,ProviderCredential,ProviderOperation,ProviderGateway,HttpTransport,TransportRequest,TransportResponse,ConnectionCheck,OperationLedger,OperationResult,LedgerClaim};
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/app/providers/directory_listing_controls.php';
$checks=[];
function ck(string $name,bool $value):void {global $checks;$checks[]=['name'=>$name,'passed'=>$value];}
function denied(string $name,callable $work):void {try{$work();ck($name,false);}catch(RuntimeException $e){ck($name,$e->getMessage()==='This request has no eligible durable dispatch completion.');}}
function request(?array $row):array {
    $id=str_repeat('a',32);$profile=str_repeat('b',32);
    return ['row'=>['id'=>$id],'grant'=>['consumed_key'=>'directoryindexing:'.$id],
        'document'=>['approved_epoch'=>time()-20,'params'=>['packageId'=>701,'value'=>true]],
        'binding'=>(object)['data'=>['profile_id'=>$profile,'profile_revision'=>1,'key_fingerprint'=>str_repeat('c',64)]],
        'ledger'=>$row];
}
final class MemoryLedger implements OperationLedger {
    public ?array $row=null;public ?OperationResult $result=null;public int $claims=0;public int $records=0;public string $fault='';
    public function claim(string $key,string $fingerprint,int $now):LedgerClaim {
        $this->claims++;
        if($this->row){if($this->row['fingerprint']!==$fingerprint)return LedgerClaim::conflict();return $this->row['status']==='claimed'?LedgerClaim::inProgress():LedgerClaim::replay($this->result);}
        $this->row=['id'=>str_repeat('d',32),'idempotency_key'=>$key,'fingerprint'=>$fingerprint,'operation'=>null,'status'=>'claimed','result_message'=>'','provider_code'=>null,'external_id'=>null,'attempts'=>0,'claimed_at'=>gmdate('Y-m-d H:i:s',$now),'claimed_epoch'=>$now,'resolved_epoch'=>null,'reconciled_outcome'=>null,'reconciled_epoch'=>null];return LedgerClaim::claimed();
    }
    public function record(string $key,string $fingerprint,OperationResult $result,int $now):void {
        $this->records++;
        if($this->fault==='before')throw new RuntimeException('Synthetic record refusal before saved row');
        if(!$this->row||$this->row['status']!=='claimed'||$this->row['idempotency_key']!==$key||$this->row['fingerprint']!==$fingerprint)throw new RuntimeException('Immutable original claim differs');
        $this->row=array_replace($this->row,['operation'=>$result->operation,'status'=>$result->status,'result_message'=>$result->message,'provider_code'=>$result->providerCode,'external_id'=>$result->externalId,'attempts'=>$result->attempts,'resolved_epoch'=>$now]);$this->result=$result;
        if($this->fault==='after')throw new RuntimeException('Synthetic acknowledgement loss after saved row');
    }
}
final class NoNetwork implements HttpTransport {public int $calls=0;public function send(TransportRequest $request):TransportResponse {$this->calls++;throw new LogicException('Offline recovery contract cannot send.');}}
final class Adapter implements ProviderAdapter {
    public int $calls=0;public ?Closure $during=null;
    public function id():string{return 'twenty-i';}public function label():string{return 'Synthetic offline adapter';}
    public function capabilities():array{return [ProviderCapability::supported('packages.directoryindexing','',false)];}
    public function supports(string $operation):bool{return $operation==='packages.directoryindexing';}
    public function probe(HttpTransport $transport,ProviderCredential $credential):ConnectionCheck{throw new LogicException('No probe.');}
    public function execute(HttpTransport $transport,ProviderCredential $credential,ProviderOperation $operation):OperationResult {$this->calls++;if($this->during)($this->during)();return OperationResult::uncertain($operation->name,'response_unverified');}
}
$secretReads=0;$credential=new ProviderCredential('twenty-i',str_repeat('b',32).':1:'.str_repeat('c',64),function()use(&$secretReads):string{$secretReads++;throw new LogicException('No credential use.');});
$operation=new ProviderOperation('packages.directoryindexing','directoryindexing:'.str_repeat('a',32),['packageId'=>701,'value'=>true]);
$ledger=new MemoryLedger();$adapter=new Adapter();$transport=new NoNetwork();$gateway=new ProviderGateway($adapter,$transport,$ledger);
$adapter->during=function()use($ledger,$gateway,$credential,$operation,$adapter):void {
    denied('Actual gateway has only an ineligible claimed row while adapter is outstanding',fn()=>provider_directory_indexing_controls_finished_ledger(request($ledger->row),time()));
    $replay=$gateway->perform($credential,$operation);
    ck('Nested duplicate reaches real gateway claim path without another adapter call',$replay->status==='in_progress'&&$adapter->calls===1);
};
$result=$gateway->perform($credential,$operation);$request=request($ledger->row);$before=$request;
ck('Actual non-idempotent gateway returns one uncertain recorded attempt',$result->status==='uncertain'&&$result->attempts===1&&$adapter->calls===1&&$ledger->records===1);
$digest=provider_directory_indexing_controls_finished_ledger($request,time());
ck('Finished original gateway row is eligible without claiming remote success',$digest===provider_directory_indexing_controls_ledger_digest($ledger->row)&&$request===$before&&$ledger->row['status']==='uncertain');
$old=$ledger->row;$replay=$gateway->perform($credential,$operation);
ck('Delayed same-key actual gateway replay cannot re-enter adapter after finish',$replay->replayed&&$adapter->calls===1&&$ledger->records===1&&$ledger->row===$old);
foreach(['before','after'] as $fault){$l=new MemoryLedger();$l->fault=$fault;$a=new Adapter();$t=new NoNetwork();$g=new ProviderGateway($a,$t,$l);$r=$g->perform($credential,$operation);
    ck('Actual gateway retains uncertainty after '.$fault.'-save exception',$r->status==='uncertain'&&$r->attempts===1&&$a->calls===1);
    if($fault==='before')denied('No durable record cannot be adopted despite returned attempts1',fn()=>provider_directory_indexing_controls_finished_ledger(request($l->row),time()));
    else ck('Durably finished row remains eligible after lost record acknowledgement',provider_directory_indexing_controls_finished_ledger(request($l->row),time())===provider_directory_indexing_controls_ledger_digest($l->row));
    $g->perform($credential,$operation);ck('No duplicate adapter call after '.$fault.'-save exception',$a->calls===1&&$t->calls===0);
}
denied('Missing ledger is ineligible',fn()=>provider_directory_indexing_controls_finished_ledger(request(null),time()));
foreach(['claimed','succeeded','accepted_pending','unsupported','in_progress','retryable','conflict'] as $state){$r=$request;$r['ledger']['status']=$state;denied('Unproven or impossible Directory listing result state refused '.$state,fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));}
$r=$request;$r['ledger']['status']='failed';$r['ledger']['result_message']='Provider rejected the credential.';$r['ledger']['provider_code']='authentication';ck('Recorded HTTP refusal is eligible only as an attempted dispatch',strlen(provider_directory_indexing_controls_finished_ledger($r,time()))===64&&$r['ledger']['status']==='failed');
foreach([0,2,'01',true,null] as $i=>$attempts){$r=$request;$r['ledger']['attempts']=$attempts;denied('Attempts field cannot replace completed one-shot evidence '.$i,fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));}
foreach(['resolved_epoch'=>null,'operation'=>null,'fingerprint'=>str_repeat('0',64),'external_id'=>'701','reconciled_outcome'=>'confirmed','reconciled_epoch'=>time(),'claimed_at'=>'2000-01-01 00:00:00','id'=>'foreign','idempotency_key'=>'directoryindexing:'.str_repeat('e',32)] as $field=>$value){$r=$request;$r['ledger'][$field]=$value;denied('Finished source field mismatch refused '.$field,fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));}
foreach(array_keys($request['ledger']) as $field){$r=$request;unset($r['ledger'][$field]);denied('Missing completed ledger column refused '.$field,fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));}
$r=$request;$r['ledger']['resolved_epoch']=$r['ledger']['claimed_epoch']-1;denied('Resolved time before claim cannot be completion evidence',fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));
$r=$request;$r['document']['approved_epoch']=time()+1;denied('Claim before approved request is ineligible',fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));
$r=$request;$r['ledger']['resolved_epoch']=time()+31;denied('Future completed epoch is ineligible',fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));
$r=$request;$r['document']['params']['value']=false;denied('Desired flag change invalidates ledger fingerprint',fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));
$r=$request;$r['binding']=(object)['data'=>array_replace($request['binding']->data,['profile_revision'=>2])];denied('Account revision change invalidates ledger fingerprint',fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));
$r=$request;$r['grant']['consumed_key']='directoryindexing:'.str_repeat('e',32);denied('Exact consumed grant key is required',fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));
$r=$request;$r['ledger']['result_message']='private'.chr(10).'text';denied('Unbounded retained result channel is not adopted',fn()=>provider_directory_indexing_controls_finished_ledger($r,time()));
$r=$request;foreach(['attempts','claimed_epoch','resolved_epoch'] as $field)$r['ledger'][$field]=(string)$r['ledger'][$field];ck('Canonical PDO scalar numeric strings preserve exact digest',provider_directory_indexing_controls_finished_ledger($r,time())===$digest);
ck('Offline execution opened no credential and invoked no network transport',$secretReads===0&&$transport->calls===0);
$passed=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$passed,'scope'=>'Production completed-row eligibility plus actual ProviderGateway with an in-memory ledger and fake adapter; no DB, mutex execution, provider, credentials, application recovery or remote settlement proof.','checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
