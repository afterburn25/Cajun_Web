<?php
/** Owner-granted operation execution against real MariaDB + real gateway/ledger; injected transport only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/providers/operations.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,OperationResult,ProviderCredential,ProviderGateway,ProviderOperation,TransportException,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;
$private=__DIR__.'/../test-runtime/provider-execution-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-exec-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pex_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic execution owner passphrase';$secret='SYNTHETIC_PRIVATE_GENERAL_KEY';$sentRequests=[];$scripted=[];$onSend=null;$onResolve=null;
function pe_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function pe_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;pe_ok($name,true);return;}pe_ok($name,false);}
function pe_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function pe_resolve():string {if(db()->inTransaction())throw new LogicException('DNS under transaction');global $onResolve;if($onResolve!==null){$h=$onResolve;$onResolve=null;$h();}return '8.8.8.8';}
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
    if(!in_array($secret,['SYNTHETIC_PRIVATE_GENERAL_KEY','SYNTHETIC_REPLACEMENT_KEY'],true)||$address!=='8.8.8.8')throw new LogicException('Invalid credential/network boundary');
    $identity=$row['id'].':'.(int)$row['revision'].':'.provider_operations_credential_fingerprint($secret);
    $credential=new ProviderCredential('twenty-i',$identity,static function()use($secret,$authorize):string{$authorize();return $secret;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new PeTransport(),new ProviderOperationLedger()))->perform($credential,$operation);
}
function pe_exec(string $profile,int $revision,string $grantId,string $key,string $pw):OperationResult {
    return provider_operations_execute($profile,$revision,$grantId,$key,$pw,'pe_resolve','pe_perform');
}
try {
    foreach(schema_statements('pex_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Execution staff '.$i,'exec'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[2,'administrator',json_encode(array_keys(staff_permissions()))]);
    pe_actor();provider_settings_migrate($password);provider_operations_migrate($password);
    $profile=provider_settings_save('',0,'20i fixture',true,$secret,$password);
    $scope=['resellerId'=>'*','type'=>'synthetic','domain_name'=>'client.example'];
    $deadGrant=str_repeat('0',32);

    // ---- authority gates ----
    pe_deny('Execution requires owner password',fn()=>pe_exec($profile,1,$deadGrant,'k1','wrong'));
    pe_actor(2);pe_deny('Administrator cannot execute',fn()=>pe_exec($profile,1,$deadGrant,'k1',$password));pe_actor();
    pe_deny('Stale profile revision refused before any work',fn()=>pe_exec($profile,9,$deadGrant,'k1',$password));
    pe_deny('Malformed idempotency key refused',fn()=>pe_exec($profile,1,$deadGrant,"bad\nkey",$password));
    pe_ok('No transport without authority',$sentRequests===[]);

    // ---- grant gate ----
    $r=pe_exec($profile,1,$deadGrant,'idem-none-1',$password);
    pe_ok('Unknown grant refuses with no_grant',$r->status===OperationResult::FAILED&&$r->providerCode==='no_grant'&&$sentRequests===[]);
    pe_ok('Refusal writes no ledger row',0===(int)query('SELECT COUNT(*) FROM '.table('provider_operations'))->fetchColumn());
    pe_ok('Refusal is audited',1===(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action='provider.operation_refused'")->fetchColumn());
    pe_deny('Shape-incomplete documented op cannot be granted',fn()=>provider_operation_grant($profile,'packages.delete',['resellerId'=>'*'],$password));
    pe_deny('Unregistered op cannot be granted',fn()=>provider_operation_grant($profile,'vps.power',['resellerId'=>'*'],$password));
    pe_deny('Oversized scope cannot be granted',fn()=>provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'x.example','label'=>str_repeat('l',5000)],$password));
    pe_ok('Ungrantable attempts leave no rows',0===(int)query('SELECT COUNT(*) FROM '.table('provider_operation_grants'))->fetchColumn());

    // ---- granted execution over the real adapter+gateway+ledger ----
    $grant1=provider_operation_grant($profile,'packages.create',$scope,$password);
    pe_ok('Grant stores the bounded approved scope',1===(int)query("SELECT COUNT(*) FROM ".table('provider_operation_grants')." WHERE id=? AND scope_params IS NOT NULL AND scope_fingerprint<>''",[$grant1])->fetchColumn());
    $scripted=[new TransportResponse(200,[],'{}')];
    $r=pe_exec($profile,1,$grant1,'idem-create-1',$password);
    pe_ok('Granted operation executes once','succeeded'===$r->status&&$r->operation==='packages.create'&&count($sentRequests)===1);
    $request=$sentRequests[0];
    pe_ok('Write posts to the documented path','POST'===$request->method&&str_contains($request->url,'/reseller/*/addWeb'));
    pe_ok('Authorization attaches only at send time','Bearer '.base64_encode($secret)===$request->headers['Authorization']);
    $body=json_decode((string)$request->body,true);
    ksort($body);
    pe_ok('Body carries only allowlisted fields',$body===['domain_name'=>'client.example','type'=>'synthetic']);
    pe_ok('The grant is single-use and records its key',1===(int)query("SELECT COUNT(*) FROM ".table('provider_operation_grants')." WHERE id=? AND consumed_key='idem-create-1' AND consumed_at IS NOT NULL",[$grant1])->fetchColumn());
    $r=pe_exec($profile,1,$grant1,'idem-create-1b',$password);
    pe_ok('A consumed grant cannot execute again','no_grant'===$r->providerCode&&count($sentRequests)===1);
    $grant1b=provider_operation_grant($profile,'packages.create',$scope,$password);
    $r=pe_exec($profile,1,$grant1b,'idem-create-1',$password);
    pe_ok('Same key under a fresh grant replays from the ledger','succeeded'===$r->status&&$r->replayed&&count($sentRequests)===1);

    // ---- key conflicts under another grant ----
    $grant2=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'other.example'],$password);
    $r=pe_exec($profile,1,$grant2,'idem-create-1',$password);
    pe_ok('Granted-but-foreign fingerprint on a used key conflicts','conflict'===$r->status&&count($sentRequests)===1);
    pe_ok('The colliding attempt consumed its approval','used'===one('SELECT CASE WHEN consumed_key IS NOT NULL THEN \'used\' ELSE \'open\' END s FROM '.table('provider_operation_grants').' WHERE id=?',[$grant2])['s']);
    $grant2b=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'other.example'],$password);
    $scripted=[new TransportResponse(200,[],'{}')];
    $r=pe_exec($profile,1,$grant2b,'idem-create-other',$password);
    pe_ok('A fresh grant executes its own key','succeeded'===$r->status&&count($sentRequests)===2);

    // ---- conservative classification ----
    $grant3=provider_operation_grant($profile,'packages.update',['resellerId'=>'*','id'=>42,'labels'=>['a'=>'b']],$password);
    $scripted=[new TransportResponse(503,[],'')];
    $r=pe_exec($profile,1,$grant3,'idem-upd-1',$password);
    pe_ok('5xx after send classifies uncertain','uncertain'===$r->status&&$r->needsReconciliation());
    $grant3b=provider_operation_grant($profile,'packages.update',['resellerId'=>'*','id'=>42,'labels'=>['a'=>'b']],$password);
    $r=pe_exec($profile,1,$grant3b,'idem-upd-1',$password);
    pe_ok('Uncertain result replays without re-sending','uncertain'===$r->status&&$r->replayed&&count($sentRequests)===3);
    $grant4=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d2.example'],$password);
    $scripted=[new TransportException('timeout')];
    $r=pe_exec($profile,1,$grant4,'idem-create-4',$password);
    pe_ok('Post-send timeout is uncertain, never retried','uncertain'===$r->status&&$r->needsReconciliation());
    $grant5=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d2b.example'],$password);
    $scripted=[new TransportException('connect')];
    $r=pe_exec($profile,1,$grant5,'idem-create-5',$password);
    pe_ok('Pre-send failure is a clean failed','failed'===$r->status);
    $grant5b=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d2c.example'],$password);
    $scripted=[new InvalidArgumentException('Unsupported provider write request.')];
    $r=pe_exec($profile,1,$grant5b,'idem-create-5b',$password);
    pe_ok('A transport-side refusal is provable pre-send failed, not uncertain','failed'===$r->status&&$r->providerCode==='refused');
    $scripted=[];

    // ---- access change mid-send ----
    $grant6=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d3.example'],$password);
    $onSend=function():void {query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');};
    $r=pe_exec($profile,1,$grant6,'idem-create-6',$password);
    pe_ok('Access change after a landed write surfaces uncertain','uncertain'===$r->status);
    query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pe_actor();
    $grant6b=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d3.example'],$password);
    $r=pe_exec($profile,1,$grant6b,'idem-create-6',$password);
    pe_ok('Ledger replays the provider-truthful recorded result','succeeded'===$r->status&&$r->replayed);

    // ---- revocation and expiry ----
    $grant7=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d4.example'],$password);
    provider_operation_revoke($grant7,$password);
    $sent=count($sentRequests);
    $r=pe_exec($profile,1,$grant7,'idem-create-7',$password);
    pe_ok('Revoked grant refuses before transport','no_grant'===$r->providerCode&&count($sentRequests)===$sent);
    $grant8=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d5.example'],$password,60);
    query('UPDATE '.table('provider_operation_grants').' SET expires_epoch=? WHERE id=?',[time()-1,$grant8]);
    $r=pe_exec($profile,1,$grant8,'idem-create-8',$password);
    pe_ok('Expired grant refuses before transport','no_grant'===$r->providerCode);

    // ---- send-boundary re-verification ----
    $grantR=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d6.example'],$password);
    $sent=count($sentRequests);
    $onResolve=fn()=>provider_operation_revoke($grantR,$password);
    $r=pe_exec($profile,1,$grantR,'idem-create-rev',$password);
    pe_ok('Revocation between consumption and send aborts the request','uncertain'===$r->status&&count($sentRequests)===$sent);

    // ---- credential-context binding ----
    $grantA=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'acct-a.example'],$password);
    $r=pe_exec($profile,1,$grantA,'idem-acct-1',$password);
    pe_ok('First account write succeeds','succeeded'===$r->status);
    provider_settings_save($profile,1,'20i fixture',true,'SYNTHETIC_REPLACEMENT_KEY',$password); // revision 2, replacement credential context
    $grantB=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'acct-a.example'],$password);
    $sent=count($sentRequests);
    $r=pe_exec($profile,2,$grantB,'idem-acct-1',$password);
    pe_ok('Replacement account cannot replay the old account\'s result','conflict'===$r->status&&count($sentRequests)===$sent);
    provider_settings_save($profile,2,'20i fixture',true,$secret,$password); // restore fixture secret at revision 3
    $grantC=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'acct-c.example'],$password);
    $r=pe_exec($profile,3,$grantC,'idem-acct-c',$password);
    pe_ok('Restored context executes normally','succeeded'===$r->status);

    // ---- real fixed-transport request validation ----
    $transport=new FixedHttpTransport('8.8.8.8',function():void{throw new RuntimeException('authorize marker reached');});
    $credential=new ProviderCredential('twenty-i','fixture-identity',static fn():string=>'SYNTHETIC_PRIVATE_GENERAL_KEY');
    $adapter=new TwentyIOperationAdapter();
    foreach([['packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'w1.example']],['packages.update',['resellerId'=>'*','id'=>42,'labels'=>['a'=>'b']]]] as $i=>[$name,$params]){
        try{$adapter->execute($transport,$credential,new ProviderOperation($name,'itx-'.$i,$params));pe_ok('Real transport accepts the '.$name.' request to the send boundary',false);}
        catch(RuntimeException $e){pe_ok('Real transport accepts the '.$name.' request to the send boundary',$e->getMessage()==='authorize marker reached');}
    }
    try{$transport->send(new TransportRequest('POST','https://api.20i.com/unknown/path',['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_PRIVATE_GENERAL_KEY'),'Accept'=>'application/json','Content-Type'=>'application/json'],'{}',15));pe_ok('Undocumented transport paths still refuse',false);}
    catch(InvalidArgumentException $e){pe_ok('Undocumented transport paths still refuse',true);}
    $rev=(int)one('SELECT revision FROM '.table('provider_profiles').' WHERE id=?',[$profile])['revision'];
    $grant9=provider_operation_grant($profile,'packages.create',$scope,$password);
    pe_deny('A grant refuses to execute against another profile',fn()=>pe_exec(str_repeat('f',32),$rev,$grant9,'idem-cross-1',$password));
    $r=pe_exec($profile,$rev,$grant9,'idem-cross-1',$password);
    pe_ok('The grant still executes on its own profile','succeeded'===$r->status);
    $durable=json_encode([all('SELECT * FROM '.table('provider_operations')),all('SELECT * FROM '.table('provider_operation_grants')),all('SELECT * FROM '.table('audit'))]);
    pe_ok('No durable row contains secret or auth material',!str_contains($durable,$secret)&&!str_contains($durable,'Bearer'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/provider-execution-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Owner-granted single-use execution over real gateway+ledger against MariaDB with scripted transport; no provider call','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native provider execution assertions'.PHP_EOL;exit($ok?0:1);
