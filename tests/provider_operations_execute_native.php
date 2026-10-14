<?php
/** Owner-granted operation execution against real MariaDB + real gateway/ledger; injected transport only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/providers/operations.php';
require_once __DIR__.'/../site/app/providers/reconcile.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,OperationResult,ProviderCredential,ProviderGateway,ProviderOperation,TransportException,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryPackage,InventorySnapshot};
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
function pe_exec(string $profile,int $revision,string $grantId,string $key,string $pw):OperationResult {
    return provider_operations_execute($profile,$revision,$grantId,$key,$pw,'pe_resolve','pe_perform');
}
$scriptedReads=[];$readCount=0;
/** Scripted reconcile read: replays queued InventorySnapshot fixtures or throws a queued failure. */
function pe_read(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize):InventorySnapshot {
    global $scriptedReads,$readCount;$readCount++;
    if($secret!=='SYNTHETIC_PRIVATE_GENERAL_KEY'||$address!=='8.8.8.8')throw new LogicException('Invalid credential/network boundary');
    $next=array_shift($scriptedReads)??new InventorySnapshot('complete',[],time());
    if($next instanceof Throwable)throw $next;
    return $next;
}
/** Validated package fixture carrying the given names and id. */
function pe_package(int $id,array $names):InventoryPackage {
    return InventoryPackage::fromItem((object)['id'=>$id,'name'=>$names[0],'names'=>$names,'enabled'=>true,'created'=>'2026-01-01T00:00:00Z','typeRef'=>1]);
}
function pe_snapshot(array $packages):InventorySnapshot {return new InventorySnapshot('complete',$packages,time());}
function pe_reconcile(string $profile,int $revision,string $key,string $pw):array {
    return provider_operations_reconcile($profile,$revision,$key,$pw,'pe_resolve','pe_read');
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
    $r=pe_exec($profile,1,$grant7,'idem-create-7',$password);
    pe_ok('Revoked grant refuses before transport','no_grant'===$r->providerCode&&count($sentRequests)===6);
    $grant8=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'d5.example'],$password,60);
    query('UPDATE '.table('provider_operation_grants').' SET expires_epoch=? WHERE id=?',[time()-1,$grant8]);
    $r=pe_exec($profile,1,$grant8,'idem-create-8',$password);
    pe_ok('Expired grant refuses before transport','no_grant'===$r->providerCode);
    $grant9=provider_operation_grant($profile,'packages.create',$scope,$password);
    pe_deny('A grant refuses to execute against another profile',fn()=>pe_exec(str_repeat('f',32),1,$grant9,'idem-cross-1',$password));
    $r=pe_exec($profile,1,$grant9,'idem-cross-1',$password);
    pe_ok('The grant still executes on its own profile','succeeded'===$r->status);

    // ---- reconciliation of uncertain results over the scripted read ----
    $grantR=provider_operation_grant($profile,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'gone.example'],$password);
    $scripted=[new TransportResponse(503,[],'')];
    pe_exec($profile,1,$grantR,'idem-rec-gone',$password);
    $grantR2=provider_operation_grant($profile,'packages.update',['resellerId'=>'*','id'=>42,'delete-id'=>77],$password);
    $scripted=[new TransportResponse(503,[],'')];
    pe_exec($profile,1,$grantR2,'idem-rec-del',$password);
    pe_ok('Two uncertain rows exist for reconciliation',2<=(int)query("SELECT COUNT(*) FROM ".table('provider_operations')." WHERE status='uncertain'")->fetchColumn());
    pe_deny('Reconcile requires the owner password',fn()=>pe_reconcile($profile,1,'idem-rec-gone','wrong'));
    pe_deny('Reconcile refuses a stale revision',fn()=>pe_reconcile($profile,9,'idem-rec-gone',$password));
    pe_actor(2);pe_deny('Administrator cannot reconcile',fn()=>pe_reconcile($profile,1,'idem-rec-gone',$password));pe_actor();
    $r=pe_reconcile($profile,1,'idem-create-1',$password);
    pe_ok('Resolved rows cannot be reconciled','refused'===$r['outcome']);
    $r=pe_reconcile($profile,1,'no-such-key',$password);
    pe_ok('Unknown keys refuse reconciliation','refused'===$r['outcome']);
    query('INSERT INTO '.table('provider_operations').' (id,idempotency_key,fingerprint,operation,status,result_message,attempts,claimed_at,claimed_epoch) VALUES (?,?,?,?,?,?,?,?,?)',[bin2hex(random_bytes(16)),'idem-orphan',hash('sha256','orphan'),'packages.create','uncertain','',0,gmdate('Y-m-d H:i:s'),time()]);
    $r=pe_reconcile($profile,1,'idem-orphan',$password);
    pe_ok('An uncertain row without an approved scope refuses','refused'===$r['outcome']);
    // The domain was never observed: honest not_observed, no retry.
    $scriptedReads=[pe_snapshot([pe_package(90,['other.example'])])];$readCount=0;
    $r=pe_reconcile($profile,1,'idem-rec-gone',$password);
    pe_ok('Absent effect reconciles not_observed','not_observed'===$r['outcome']&&1===$readCount);
    pe_ok('Reconcile result is durably recorded','not_observed'===one('SELECT reconciled_outcome FROM '.table('provider_operations').' WHERE idempotency_key=?',['idem-rec-gone'])['reconciled_outcome']);
    pe_ok('The recorded provider outcome stays truthful','uncertain'===one('SELECT status FROM '.table('provider_operations').' WHERE idempotency_key=?',['idem-rec-gone'])['status']);
    pe_ok('Reconciliation is audited',1<=(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action='provider.operation_reconcile'")->fetchColumn());
    // A fresh observation showing the domain updates the reconcile marker.
    $scriptedReads=[pe_snapshot([pe_package(91,['gone.example'])])];
    $r=pe_reconcile($profile,1,'idem-rec-gone',$password);
    pe_ok('Observed effect reconciles','observed'===$r['outcome']);
    // packages.update with delete-id: target absent = removal is visible.
    $scriptedReads=[pe_snapshot([pe_package(42,['u.example'])])];
    $r=pe_reconcile($profile,1,'idem-rec-del',$password);
    pe_ok('Removed package reconciles observed','observed'===$r['outcome']);
    $scriptedReads=[pe_snapshot([pe_package(77,['still.example']),pe_package(42,['u.example'])])];
    $r=pe_reconcile($profile,1,'idem-rec-del',$password);
    pe_ok('Still-present target reconciles not_observed','not_observed'===$r['outcome']);
    // A field-only update cannot be observed: inconclusive, no read sent.
    $readCount=0;
    $r=pe_reconcile($profile,1,'idem-upd-1',$password);
    pe_ok('Field-only update reconciles inconclusive without a read','inconclusive'===$r['outcome']&&0===$readCount);
    // Read failures keep the row uncertain without a conclusion.
    $scriptedReads=[new TransportException('timeout')];
    $r=pe_reconcile($profile,1,'idem-rec-gone',$password);
    pe_ok('A failed observation reconciles unavailable','unavailable'===$r['outcome']);
    $scriptedReads=[new InventorySnapshot('unavailable',[],time(),0,[],'authentication')];
    $r=pe_reconcile($profile,1,'idem-rec-gone',$password);
    pe_ok('A refused observation reconciles unavailable','unavailable'===$r['outcome']);
    $durable=json_encode([all('SELECT * FROM '.table('provider_operations')),all('SELECT * FROM '.table('provider_operation_grants')),all('SELECT * FROM '.table('audit'))]);
    pe_ok('No durable row contains secret or auth material',!str_contains($durable,$secret)&&!str_contains($durable,'Bearer'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/provider-execution-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Owner-granted single-use execution over real gateway+ledger against MariaDB with scripted transport; no provider call','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native provider execution assertions'.PHP_EOL;exit($ok?0:1);
