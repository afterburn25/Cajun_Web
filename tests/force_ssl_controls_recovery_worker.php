<?php
/** Disposable native fixture: pause a real gateway worker before or after its durable record. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$input=json_decode((string)fgets(STDIN),true,20,JSON_THROW_ON_ERROR);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/app/providers/force_ssl_controls.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\Operations\{ForceSslChange,TwentyIOperationAdapter};
$GLOBALS['cwp_config']=$input['config'];$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');
start_session();$u=one('SELECT * FROM '.table('users').' WHERE id=?',[1]);$_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$dns=0;$posts=0;
function recovery_worker_pause(string $phase):void {
    global $input,$dns,$posts;
    $ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['forcessl:'.$input['request_id']]);
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    echo json_encode(['phase'=>$phase,'status'=>$ledger['status']??null,'resolved'=>$ledger['resolved_epoch']!==null,'attempts'=>(int)$ledger['attempts'],'dns'=>$dns,'posts'=>$posts,'mutex_owned'=>(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$mutex])->fetchColumn()===1,'in_transaction'=>db()->inTransaction()]).PHP_EOL;fflush(STDOUT);
    // Parent terminates this specific disposable process. The finite timeout prevents an orphaned fixture.
    stream_set_timeout(STDIN,20);fgets(STDIN);throw new PDOException('Synthetic paused worker stopped before local completion.');
}
final class RecoveryWorkerTransport implements HttpTransport {
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse {
        global $input,$posts;($this->authorize)();ForceSslChange::wire($request);
        if($request->url!=='https://api.20i.com/package/701/web/forceSSL'||db()->inTransaction())throw new LogicException('Unexpected synthetic worker request.');
        ++$posts;if($input['phase']==='outstanding')recovery_worker_pause('outstanding');return new TransportResponse(200,[],'true');
    }
}
try{
    provider_force_ssl_controls_change($input['request_id'],$input['link_id'],$input['anchor_id'],$input['binding_sha256'],true,true,$input['password'],function()use(&$dns):string{++$dns;return '8.8.8.8';},function(array $profile,string $key,string $address,callable $authorize,ProviderOperation $operation){
        $credential=new ProviderCredential('twenty-i',$profile['id'].':'.$profile['revision'].':'.provider_operations_credential_fingerprint($key),function()use($key,$authorize){$authorize();return $key;});
        $result=(new ProviderGateway(new TwentyIOperationAdapter(),new RecoveryWorkerTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
        recovery_worker_pause('finished');return $result;
    });
}catch(Throwable){exit(3);}
exit(4);
