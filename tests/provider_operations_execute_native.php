<?php
/** Owner-granted operation execution against real MariaDB + real gateway/ledger; injected transport only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/providers/operations.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,OperationResult,ProviderCredential,ProviderGateway,ProviderOperation,TransportException,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;
$private=__DIR__.'/../test-runtime/provider-execution-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-exec-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pex_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic execution owner passphrase';$secret='SYNTHETIC_PRIVATE_GENERAL_KEY';$sentRequests=[];$scripted=[];$onSend=null;
function pe_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function pe_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;pe_ok($name,true);return;}pe_ok($name,false);}
function pe_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function pe_resolve():string {if(db()->inTransaction())throw new LogicException('DNS under transaction');return '8.8.8.8';}
/** Scripted transport: records each request, replays queued responses/exceptions, optional mid-send hook. */
final class PeTransport implements HttpTransport {
    public function send(#[SensitiveParameter] TransportRequest $request):TransportResponse {
        global $sentRequests,$scripted,$onSend;
        $sentRequests[]=$request;
        if($onSend!==null){$hook=$onSend;$onSend=null;$hook();}
        $next=array_shift($scripted)??new TransportResponse(200,[],'{"id":7}');
        if($next instanceof Throwable)throw $next;
        return $next;
    }
}
/** The real execution path: gateway + write adapter + durable ledger over the scripted transport. */
function pe_perform(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize,ProviderOperation $operation):OperationResult {
    if($secret!=='SYNTHETIC_PRIVATE_GENERAL_KEY'||$address!=='8.8.8.8')throw new LogicException('Invalid credential/network boundary');
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($secret,$authorize):string{$authorize();return $secret;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new PeTransport(),new ProviderOperationLedger()))->perform($credential,$operation);
}
function pe_exec(string $profile,int $revision,ProviderOperation $op,string $pw):OperationResult {
    return provider_operations_execute($profile,$revision,$op,$pw,'pe_resolve','pe_perform');
}
try {
    foreach(schema_statements('pex_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Execution staff '.$i,'exec'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[2,'administrator',json_encode(array_keys(staff_permissions()))]);
    pe_actor();provider_settings_migrate($password);provider_operations_migrate($password);
    $profile=provider_settings_save('',0,'20i fixture',true,$secret,$password);
    $scope=['resellerId'=>'*','type'=>'synthetic','domain_name'=>'client.example'];
    $op=new ProviderOperation('packages.create','idem-create-1',$scope);

    // ---- authority gates ----
    pe_deny('Execution requires owner password',fn()=>pe_exec($profile,1,$op,'wrong'));
    pe_actor(2);pe_deny('Administrator cannot execute',fn()=>pe_exec($profile,1,$op,$password));pe_actor();
    pe_deny('Stale profile revision refused before any work',fn()=>pe_exec($profile,9,$op,$password));
    pe_ok('No transport without authority',$sentRequests===[]);

    // ---- grant gate ----
    $r=pe_exec($profile,1,$op,$password);
    pe_ok('Ungranted exact scope refuses with no_grant',$r->status===OperationResult::FAILED&&$r->providerCode==='no_grant'&&$sentRequests===[]);
    pe_ok('Refusal writes no ledger row',0===(int)query('SELECT COUNT(*) FROM '.table('provider_operations'))->fetchColumn());
    pe_ok('Refusal is audited',1===(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action='provider.operation_refused'")->fetchColumn());
    $r=pe_exec($profile,1,new ProviderOperation('packages.delete','idem-del-1'),$password);
    pe_ok('Shape-incomplete documented op refuses unsupported',$r->status===OperationResult::UNSUPPORTED);
    $r=pe_exec($profile,1,new ProviderOperation('vps.power','idem-vps-1'),$password);
    pe_ok('Unregistered op refuses unsupported',$r->status===OperationResult::UNSUPPORTED);
    pe_ok('Unsupported attempts never claim or call',0===(int)query('SELECT COUNT(*) FROM '.table('provider_operations'))->fetchColumn()&&$sentRequests===[]);

    // ---- granted execution over the real adapter+gateway+ledger ----
    provider_operation_grant($profile,'packages.create',$scope,$password);
    $scripted=[new TransportResponse(200,[],'{}')];
    $r=pe_exec($profile,1,$op,$password);
    pe_ok('Granted operation executes once','succeeded'===$r->status&&count($sentRequests)===1);
    $request=$sentRequests[0];
    pe_ok('Write posts to the documented path','POST'===$request->method&&str_contains($request->url,'/reseller/*/addWeb'));
    pe_ok('Authorization attaches only at send time','Bearer '.base64_encode($secret)===$request->headers['Authorization']);
    $body=json_decode((string)$request->body,true);
    pe_ok('Body carries only allowlisted fields',$body===['type'=>'synthetic','domain_name'=>'client.example']);
    $r=pe_exec($profile,1,$op,$password);
    pe_ok('Same idempotency key replays without re-sending','succeeded'===$r->status&&$r->replayed&&count($sentRequests)===1);
    $r=pe_exec($profile,1,new ProviderOperation('packages.create','idem-create-1',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'other.example']),$password);
    pe_ok('Reused key with different params conflicts','conflict'===$r->status&&count($sentRequests)===1);
    pe_ok('Ledger row recorded with bounded fields',1===(int)query("SELECT COUNT(*) FROM ".table('provider_operations')." WHERE idempotency_key='idem-create-1' AND status='succeeded' AND operation='packages.create'")->fetchColumn());

    // ---- grant scope isolation ----
    provider_operation_grant($profile,'packages.update',['resellerId'=>'*','id'=>42,'labels'=>['a'=>'b']],$password);
    $r=pe_exec($profile,1,new ProviderOperation('packages.update','idem-upd-1',['resellerId'=>'*','id'=>42,'labels'=>['a'=>'changed']]),$password);
    pe_ok('Grant does not cover changed params','no_grant'===$r->providerCode&&count($sentRequests)===1);
    $r=pe_exec($profile,1,new ProviderOperation('packages.update','idem-upd-2',['resellerId'=>'*','id'=>42,'labels'=>['a'=>'b']]),$password);
    pe_ok('Exact approved scope executes','succeeded'===$r->status&&count($sentRequests)===2);

    // ---- conservative classification ----
    provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d2.example'],$password);
    $scripted=[new TransportResponse(503,[],'')];
    $r=pe_exec($profile,1,new ProviderOperation('packages.create','idem-create-2',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d2.example']),$password);
    pe_ok('5xx after send classifies uncertain','uncertain'===$r->status&&$r->needsReconciliation());
    $r=pe_exec($profile,1,new ProviderOperation('packages.create','idem-create-2',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d2.example']),$password);
    pe_ok('Uncertain result replays without re-sending','uncertain'===$r->status&&$r->replayed&&count($sentRequests)===3);
    $scripted=[new TransportException('timeout')];
    $r=pe_exec($profile,1,new ProviderOperation('packages.create','idem-create-2b',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d2.example']),$password);
    pe_ok('Post-send timeout is uncertain, never retried','uncertain'===$r->status&&$r->needsReconciliation());
    $scripted=[new TransportException('connect')];
    $r=pe_exec($profile,1,new ProviderOperation('packages.create','idem-create-2c',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d2.example']),$password);
    pe_ok('Pre-send failure is a clean failed','failed'===$r->status);
    $scripted=[];

    // ---- access change mid-send ----
    provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d3.example'],$password);
    $onSend=function():void {query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');};
    $r=pe_exec($profile,1,new ProviderOperation('packages.create','idem-create-3',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d3.example']),$password);
    pe_ok('Access change after a landed write surfaces uncertain','uncertain'===$r->status);
    query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pe_actor();
    $r=pe_exec($profile,1,new ProviderOperation('packages.create','idem-create-3',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d3.example']),$password);
    pe_ok('Ledger replays the provider-truthful recorded result','succeeded'===$r->status&&$r->replayed);

    // ---- grant revocation ----
    provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d4.example'],$password);
    $gid=one('SELECT id FROM '.table('provider_operation_grants').' WHERE operation=? ORDER BY seq DESC LIMIT 1',['packages.create'])['id'];
    provider_operation_revoke($gid,$password);
    $r=pe_exec($profile,1,new ProviderOperation('packages.create','idem-create-4',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d4.example']),$password);
    pe_ok('Revoked grant refuses before transport','no_grant'===$r->providerCode);
    $durable=json_encode([all('SELECT * FROM '.table('provider_operations')),all('SELECT * FROM '.table('provider_operation_grants')),all('SELECT * FROM '.table('audit'))]);
    pe_ok('No durable row contains secret or auth material',!str_contains($durable,$secret)&&!str_contains($durable,'Bearer'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/provider-execution-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Owner-granted execution over real gateway+ledger against MariaDB with scripted transport; no provider call','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native provider execution assertions'.PHP_EOL;exit($ok?0:1);
