<?php
/** Exercise the actual production helper's identity, stopping at lazy secret authorization. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require_once __DIR__.'/../site/app/providers/operations.php';
use CajunWebPro\Foundation\Provider\{ProviderOperation,OperationResult,TransportException};
$private=realpath(__DIR__.'/../test-runtime/provider-execution-private');if(!$private)throw new RuntimeException('Run supplied fixtures first.');
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>$private,'key'=>str_repeat('synthetic-exec-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pex_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$owner=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$checks=[];$observations=[];$calls=0;
function ir_check(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];}
try {
    $row=provider_settings_profile();$secret='SYNTHETIC_PRIVATE_GENERAL_KEY';
    $operation=new ProviderOperation('packages.create','review-real-helper-identity',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'identity.example']);
    $stop=static function()use(&$calls):void {++$calls;throw new TransportException(TransportException::KIND_CONNECT);};
    $result=provider_operations_perform($row,$secret,'8.8.8.8',$stop,$operation);
    ir_check('Actual production helper is stopped before transport by its credential callback',$calls===1 && $result->status===OperationResult::UNCERTAIN && !$result->replayed);
    $original=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$operation->idempotencyKey]);
    $result=provider_operations_perform($row,$secret,'8.8.8.8',$stop,$operation);
    ir_check('Unchanged production helper context replays without another secret callback',$calls===1 && $result->status===OperationResult::UNCERTAIN && $result->replayed);
    foreach(['profile','revision','secret'] as $field) {
        $changed=$row;$credential=$secret;
        if($field==='profile')$changed['id']=str_repeat('f',32);
        if($field==='revision')$changed['revision']=(int)$row['revision']+1;
        if($field==='secret')$credential='SYNTHETIC_DIFFERENT_CONTEXT_KEY';
        $result=provider_operations_perform($changed,$credential,'8.8.8.8',$stop,$operation);
        $observations[$field]=['status'=>$result->status,'credential_callbacks'=>$calls];
        ir_check('Actual production helper binds '.$field.' independently',$result->status===OperationResult::CONFLICT && $calls===1);
    }
    $after=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$operation->idempotencyKey]);
    ir_check('Conflicting production contexts preserve the original durable operation',$after===$original);
    ir_check('Identity ledger retains no synthetic secret or authorization header',!str_contains(json_encode($after),$secret) && !str_contains(json_encode($after),'Bearer'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected fixture failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e.PHP_EOL);}
$passed=!array_filter($checks,static fn($c)=>!$c['passed']);
file_put_contents(__DIR__.'/../qa/provider-identity-review-results.json',json_encode(['passed'=>$passed,'source'=>'0a4462581ac43d14eea316d180164a0fcd9e99b5','php'=>PHP_VERSION,'scope'=>'Actual provider_operations_perform with native ledger. Synthetic declared context permutations verify identity only; callback stops at lazy credential resolution before any transport or provider call.','checks'=>$checks,'observations'=>$observations],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo ($passed?'PASS':'FINDINGS').': '.count($checks).' production identity review checks'.PHP_EOL;exit($passed?0:1);
