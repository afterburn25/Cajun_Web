<?php
/** Independent reconciliation review of supplied native fixtures; all reads are scripted. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require_once __DIR__.'/../site/app/providers/reconcile.php';
use CajunWebPro\Foundation\TwentyIInventory\{InventoryPackage,InventorySnapshot};
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,ProviderCredential,ProviderGateway,ProviderOperation,OperationResult};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;
$private=realpath(__DIR__.'/../test-runtime/provider-execution-private');if(!$private)throw new RuntimeException('Run supplied execution fixtures first.');
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>$private,'key'=>str_repeat('synthetic-exec-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pex_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$owner=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];
$password='Synthetic execution owner passphrase';$checks=[];$observations=[];$reads=0;$expectedSecret='SYNTHETIC_PRIVATE_GENERAL_KEY';$reviewSnapshot=null;
function rr_check(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];}
function rr_resolve():string{if(db()->inTransaction())throw new LogicException('Unexpected resolver transaction.');return '8.8.8.8';}
final class ReconciliationSeedTransport implements HttpTransport {
    public function send(#[SensitiveParameter] TransportRequest $request):TransportResponse {return new TransportResponse(503,[],'');}
}
function rr_seed_perform(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize,ProviderOperation $operation):OperationResult {
    if($secret!=='SYNTHETIC_PRIVATE_GENERAL_KEY'||$address!=='8.8.8.8')throw new LogicException('Independent seed credential boundary mismatch.');
    $identity=$row['id'].':'.(int)$row['revision'].':'.provider_operations_credential_fingerprint($secret);
    $credential=new ProviderCredential('twenty-i',$identity,static function()use($secret,$authorize):string{$authorize();return $secret;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new ReconciliationSeedTransport(),new ProviderOperationLedger()))->perform($credential,$operation);
}
function rr_read(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize):InventorySnapshot{
    global $reads,$expectedSecret,$reviewSnapshot;$reads++;if($secret!==$expectedSecret||$address!=='8.8.8.8')throw new LogicException('Review credential boundary mismatch.');$authorize();if($reviewSnapshot!==null)return $reviewSnapshot;
    return new InventorySnapshot('complete',[InventoryPackage::fromItem((object)['id'=>901,'name'=>'gone.example','names'=>['gone.example'],'enabled'=>true,'created'=>'2026-01-01T00:00:00Z','typeRef'=>1])],time());
}
try{
    $profile=provider_settings_profile();$id=$profile['id'];$revision=(int)$profile['revision'];$key='review-rec-gone';
    // Supplied fixtures now intentionally replace their context at the end. Seed independent
    // current-generation uncertain operations through the real owner/grant/execution APIs.
    provider_settings_save($id,$revision,'Independent reconciliation fixture',true,'SYNTHETIC_PRIVATE_GENERAL_KEY',$password);
    $revision=(int)provider_settings_load($id)['revision'];
    foreach ([['review-rec-gone','packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'gone.example']],['review-rec-del','packages.update',['resellerId'=>'*','id'=>42,'delete-id'=>77]]] as [$seedKey,$operation,$scope]) {
        $grant=provider_operation_grant($id,$operation,$scope,$password);
        $seed=provider_operations_execute($id,$revision,$grant,$seedKey,$password,'rr_resolve','rr_seed_perform');
        if($seed->status!==OperationResult::UNCERTAIN)throw new RuntimeException('Independent uncertain seed did not complete.');
    }
    $initial=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$key]);
    rr_check('Current reconciliation fixture has an uncertain scoped operation',$initial['status']==='uncertain'&&provider_operations_ready());
    $before=(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action='provider.operation_reconcile'")->fetchColumn();
    $result=provider_operations_reconcile($id,$revision,$key,$password,'rr_resolve','rr_read');
    rr_check('Same account reconciliation performs one authorized scripted observation',$result['outcome']==='observed'&&$reads===1);
    rr_check('Successful reconciliation has a corresponding audit entry',(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action='provider.operation_reconcile'")->fetchColumn()===$before+1);
    // Set a distinct prior result so a failed-audit write is observable even within the same second.
    query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE idempotency_key=?',['not_observed',time()-10,$key]);
    $before=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$key]);$failed=false;
    db()->exec('CREATE TRIGGER pex_reconcile_review_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic reconciliation audit failure'");
    try{provider_operations_reconcile($id,$revision,$key,$password,'rr_resolve','rr_read');}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER pex_reconcile_review_fail');}
    $after=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$key]);
    $observations['audit_failure']=['request_failed'=>$failed,'before_outcome'=>$before['reconciled_outcome'],'after_outcome'=>$after['reconciled_outcome'],'row_unchanged'=>$before===$after];
    rr_check('Audit failure is surfaced by the actual reconciliation API',$failed);
    rr_check('Failed reconciliation audit rolls back its durable observation',$failed&&$before===$after);
    query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE idempotency_key=?',[$before['reconciled_outcome'],$before['reconciled_epoch'],$key]);
    foreach(['complete','partial'] as $state){
        $reviewSnapshot=new InventorySnapshot($state,[],time(),$state==='partial'?1:0,$state==='partial'?['unprojectable_item']:[]);
        $result=provider_operations_reconcile($id,$revision,'review-rec-del',$password,'rr_resolve','rr_read');
        $observations['absence_'.$state]=['snapshot_state'=>$state,'coverage'=>$reviewSnapshot->coverage,'skipped'=>$reviewSnapshot->skipped,'outcome'=>$result['outcome']];
        rr_check('Deletion remains inconclusive with '.$state.' response and unknown account coverage',in_array($result['outcome'],['inconclusive','unavailable'],true));
    }
    $reviewSnapshot=new InventorySnapshot('complete',[InventoryPackage::fromItem((object)['id'=>77,'name'=>'still-present.example','names'=>['still-present.example'],'enabled'=>true,'created'=>'2026-01-01T00:00:00Z','typeRef'=>1])],time());
    $result=provider_operations_reconcile($id,$revision,'review-rec-del',$password,'rr_resolve','rr_read');rr_check('Observed target still present honestly refutes claimed deletion',$result['outcome']==='not_observed');$reviewSnapshot=null;
    // Two genuinely approved scopes can consume the same caller key: one is uncertain,
    // the other conflicts. Reconciliation must bind the original ledger fingerprint,
    // not whichever matching grant the storage query returns first.
    $candidates=[];
    foreach(['collision-a.example','collision-b.example'] as $domain) {
        $scope=['resellerId'=>'*','type'=>'synthetic','domain_name'=>$domain];
        $candidate=provider_operation_grant($id,'packages.create',$scope,$password);
        $candidates[$candidate]=$scope;
    }
    ksort($candidates,SORT_STRING);$collider=array_key_first($candidates);$originalGrant=array_key_last($candidates);$collisionKey='review-reconciliation-conflicting-grant';
    $originalResult=provider_operations_execute($id,$revision,$originalGrant,$collisionKey,$password,'rr_resolve','rr_seed_perform');
    $conflictingResult=provider_operations_execute($id,$revision,$collider,$collisionKey,$password,'rr_resolve','rr_seed_perform');
    rr_check('Conflicting approved scope cannot execute over an uncertain original key',$originalResult->status===OperationResult::UNCERTAIN && $conflictingResult->status===OperationResult::CONFLICT);
    $chosen=one('SELECT operation,scope_params,profile_revision FROM '.table('provider_operation_grants').' WHERE consumed_key=? AND profile_id=?',[$collisionKey,$id]);
    $chosenScope=json_decode($chosen['scope_params'],true);
    if($chosenScope['domain_name']!==$candidates[$collider]['domain_name'])throw new RuntimeException('Collision fixture did not select the conflicting valid grant.');
    $reviewSnapshot=new InventorySnapshot('complete',[InventoryPackage::fromItem((object)['id'=>990,'name'=>$candidates[$collider]['domain_name'],'names'=>[$candidates[$collider]['domain_name']],'enabled'=>true,'created'=>'2026-01-01T00:00:00Z','typeRef'=>1])],time());
    $collisionResult=provider_operations_reconcile($id,$revision,$collisionKey,$password,'rr_resolve','rr_read');$reviewSnapshot=null;
    $observations['conflicting_consumed_grant']=['original_domain'=>$candidates[$originalGrant]['domain_name'],'conflicting_domain'=>$candidates[$collider]['domain_name'],'selected_domain'=>$chosenScope['domain_name'],'conflicting_execution'=>$conflictingResult->status,'reconciled_outcome'=>$collisionResult['outcome']];
    rr_check('A conflicting consumed grant cannot confirm a different original operation',$collisionResult['outcome']!=='observed');
    $grant=one('SELECT profile_revision FROM '.table('provider_operation_grants').' WHERE consumed_key=? AND profile_id=?',[$key,$id]);
    provider_settings_save($id,$revision,'Reconciliation replacement account',true,'SYNTHETIC_RECONCILE_REPLACEMENT_KEY',$password);$replacement=provider_settings_load($id);$expectedSecret='SYNTHETIC_RECONCILE_REPLACEMENT_KEY';$readsBefore=$reads;$error=null;$result=null;
    try{$result=provider_operations_reconcile($id,(int)$replacement['revision'],$key,$password,'rr_resolve','rr_read');}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$error=$e->getMessage();}
    $after=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$key]);
    $observations['replacement_account']=['approved_revision'=>(int)$grant['profile_revision'],'current_revision'=>(int)$replacement['revision'],'reads'=>$reads-$readsBefore,'outcome'=>$result['outcome']??null,'error'=>$error,'stored_outcome'=>$after['reconciled_outcome']];
    rr_check('Review replaced the actual configured account after the uncertain write',(int)$replacement['revision']>(int)$grant['profile_revision']);
    rr_check('A replacement account cannot reconcile the previous account operation',$reads===$readsBefore&&($error!==null||in_array($result['outcome']??'', ['refused','unavailable'],true)));
    rr_check('Reconciliation never rewrites the uncertain provider result',$after['status']===$initial['status']&&$after['fingerprint']===$initial['fingerprint']&&$after['result_message']===$initial['result_message']);
    // Restore through the real owner API for the existing execution review that follows.
    provider_settings_save($id,(int)$replacement['revision'],'Restored synthetic execution account',true,'SYNTHETIC_PRIVATE_GENERAL_KEY',$password);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected fixture failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e.PHP_EOL);}
$passed=!array_filter($checks,static fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/provider-reconciliation-review-results.json',json_encode(['passed'=>$passed,'source'=>'8e068cf705b7cb352eb8c786d92e230ed150b86e','php'=>PHP_VERSION,'scope'=>'Independent native reconciliation with scripted reads and synthetic owner settings. No DNS or provider call; behavioral findings are not acceptance.','checks'=>$checks,'observations'=>$observations],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo ($passed?'PASS':'FINDINGS').': '.count($checks).' reconciliation review checks'.PHP_EOL;exit($passed?0:1);
