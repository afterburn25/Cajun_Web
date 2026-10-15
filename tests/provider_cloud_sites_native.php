<?php
/** Native disposable database + actual shared gateway, synthetic API transport only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/admin/controllers/providers.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyI\Operations\{CloudWebsiteCreate,TwentyIOperationAdapter};
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
$private=__DIR__.'/../test-runtime/provider-cloud-sites-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-cloud-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pcws_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic cloud owner passphrase';$secret='SYNTHETIC_CLOUD_API_KEY';$calls=0;$dnsCalls=0;$mode='accepted';
function cw_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function cw_deny(string $name,callable $fn):void{try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;cw_ok($name,true);return;}cw_ok($name,false);}
function cw_owner(int $id=1):void{$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function cw_client(int $id):void{$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];}
function cw_dns():string{global $dnsCalls;++$dnsCalls;if(db()->inTransaction())throw new LogicException('DNS under transaction');return '8.8.8.8';}
function cw_restore(string $tableName,array $row):void{query('INSERT INTO '.table($tableName).' (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));}
final class CwTransport implements HttpTransport {
    public function __construct(private $authorize,private bool $inventory=false){}
    public function send(TransportRequest $request):TransportResponse{
        global $calls,$mode;($this->authorize)();if(db()->inTransaction())throw new LogicException('Transport under transaction');
        if($this->inventory)return new TransportResponse(200,[],json_encode([['id'=>1201,'name'=>'cloud.synthetic.invalid','location'=>'dc_location-uk','packageIds'=>[]]]));
        ++$calls;if(!one('SELECT id FROM '.table('provider_operations').' WHERE status=?',['claimed']))throw new LogicException('Missing pre-send durable claim');
        if($mode==='timeout')throw new TransportException('timeout');
        return new TransportResponse(200,[],'{"result":8881}');
    }
}
function cw_read(array $row,string $key,string $address,callable $authorize,string $operation):\CajunWebPro\Foundation\TwentyIInventory\InventorySnapshot{return InventoryReader::read(new CwTransport($authorize,true),new ProviderCredential('twenty-i',$row['id'],fn()=>$key),$operation,0,time());}
function cw_perform(array $row,string $key,string $address,callable $authorize,ProviderOperation $operation):\CajunWebPro\Foundation\Provider\OperationResult{
    global $secret;if($key!==$secret||$address!=='8.8.8.8')throw new LogicException('Wrong credential/address');
    $credential=new ProviderCredential('twenty-i',$row['id'].':'.$row['revision'].':'.provider_operations_credential_fingerprint($key),function()use($key,$authorize){$authorize();return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new CwTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function cw_create(string $domain,string $nonce='',?callable $dns=null,string $pass=''): \CajunWebPro\Foundation\Provider\OperationResult {global $link,$refresh,$password;return provider_cloud_sites_create($nonce?:bin2hex(random_bytes(16)),$link['id'],(int)$link['revision'],1,$refresh,$domain,[],true,$pass?:$password,$dns??'cw_dns','cw_perform');}
if(($argv[1]??'')==='paused-request'){
    cw_owner();$profileId=one('SELECT id FROM '.table('provider_profiles'))['id'];$link=provider_cloud_links($profileId)[0];$refresh=provider_inventory_observation($profileId,InventoryRead::CLOUD_SERVERS_LIST)['id'];
    $result=cw_create('concurrent.example',$argv[2],function(){echo "resolving\n";fflush(STDOUT);stream_set_timeout(STDIN,20);if(trim((string)fgets(STDIN))!=='continue')throw new RuntimeException('Synchronization failed');return cw_dns();});
    echo $result->status.':'.$calls;exit;
}
try{
    foreach(schema_statements('pcws_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $key=>$value)save_setting($key,$value);
    foreach([1,2] as $i)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Cloud staff '.$i,'cloud'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (2,?,1,?)',['administrator',json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $i)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Cloud client '.$i,'cloud-client'.$i.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    cw_owner();provider_settings_migrate($password);$profileId=provider_settings_save('',0,'Cloud fixture',true,$secret,$password);provider_inventory_migrate($password);
    $beforePreparation=all('SHOW TABLES');db()->beginTransaction();save_setting('cloud_preparation_transaction_sentinel','pending');$refusedTransaction=false;
    try{try{provider_operations_migrate($password);}catch(LogicException){$refusedTransaction=true;}cw_ok('Fresh preparation refuses caller transaction before any table or marker change',$refusedTransaction&&db()->inTransaction()&&all('SHOW TABLES')===$beforePreparation&&!one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_operations_schema_version']));}finally{if(db()->inTransaction())db()->rollBack();}
    cw_ok('Refused preparation preserves caller rollback of unrelated writes',!one('SELECT value FROM '.table('settings').' WHERE name=?',['cloud_preparation_transaction_sentinel']));provider_operations_migrate($password);
    cw_ok('Native complete cloud observation',provider_inventory_refresh($profileId,1,$password,'cw_dns','cw_read',InventoryRead::CLOUD_SERVERS_LIST)==='complete');
    $refresh=provider_inventory_observation($profileId,InventoryRead::CLOUD_SERVERS_LIST)['id'];provider_cloud_link($profileId,1201,1,1,$refresh,$password);$link=provider_cloud_links($profileId)[0];$dnsCalls=0;
    cw_deny('Wrong password refuses cloud request',fn()=>cw_create('wrong.example','',null,'wrong'));
    cw_owner(2);cw_deny('Administrator cannot approve cloud website creation',fn()=>cw_create('admin.example'));cw_owner();
    $_GET['support']='test';cw_deny('Support selector cannot approve cloud website creation',fn()=>cw_create('support.example'));unset($_GET['support']);
    cw_client(1);cw_deny('Client cannot submit owner creation form',fn()=>cw_create('client.example'));cw_owner();
    cw_deny('Missing review consent refuses request',fn()=>provider_cloud_sites_create(bin2hex(random_bytes(16)),$link['id'],1,1,$refresh,'review.example',[],false,$password,'cw_dns','cw_perform'));
    cw_ok('Rejected actions have no DNS, send or approval records',$dnsCalls===0&&$calls===0&&(int)query('SELECT COUNT(*) FROM '.table('provider_operation_grants'))->fetchColumn()===0);
    query('UPDATE '.table('provider_refresh_results').' SET state=? WHERE id=?',['partial',$refresh]);cw_deny('Partial cloud inventory cannot authorize creation',fn()=>cw_create('partial.example'));query('UPDATE '.table('provider_refresh_results').' SET state=? WHERE id=?',['complete',$refresh]);
    query('UPDATE '.table('provider_refreshes').' SET started_epoch=started_epoch-1000 WHERE id=?',[$refresh]);cw_deny('Old cloud inventory cannot authorize creation',fn()=>cw_create('stale.example'));query('UPDATE '.table('provider_refreshes').' SET started_epoch=started_epoch+1000 WHERE id=?',[$refresh]);
    $incomplete=bin2hex(random_bytes(16));query('INSERT INTO '.table('provider_refreshes').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch,operation) VALUES (?,?,1,1,?,?,?)',[$incomplete,$profileId,gmdate('Y-m-d H:i:s'),time(),InventoryRead::CLOUD_SERVERS_LIST]);cw_deny('New unfinished refresh cannot fall back to older valid observation',fn()=>cw_create('incomplete.example'));query('DELETE FROM '.table('provider_refreshes').' WHERE id=?',[$incomplete]);
    db()->exec("CREATE TRIGGER pcws_fail BEFORE INSERT ON ".table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.cloudsite_started' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'; END IF; END");$failed=false;try{cw_create('audit.example');}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER pcws_fail');}
    cw_ok('Audit failure rolls approval back before DNS',$failed&&$dnsCalls===0&&(int)query('SELECT COUNT(*) FROM '.table('provider_operation_grants'))->fetchColumn()===0);
    $nonce=bin2hex(random_bytes(16));$result=cw_create('first.example',$nonce);cw_ok('Native request uses shared gateway and retains allocated package pending',$result->status==='accepted_pending'&&$result->externalId==='8881'&&$calls===1);
    $beforeDns=$dnsCalls;$again=cw_create('first.example',$nonce);cw_ok('Repeated original form makes no DNS or second provider call',$again->status==='accepted_pending'&&$calls===1&&$dnsCalls===$beforeDns);
    cw_deny('Changed same nonce is refused',fn()=>cw_create('changed.example',$nonce));cw_deny('Fresh nonce cannot recreate accepted domain',fn()=>cw_create('first.example'));
    cw_ok('Returned package is not automatically linked or active',(int)query('SELECT COUNT(*) FROM '.table('provider_package_links'))->fetchColumn()===0&&(int)query('SELECT COUNT(*) FROM '.table('provider_observation_packages'))->fetchColumn()===0);
    $parallel=bin2hex(random_bytes(16));$worker=proc_open([PHP_BINARY,__FILE__,'paused-request',$parallel],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($worker))throw new RuntimeException('Cannot start concurrency fixture');stream_set_timeout($pipes[1],25);
    try{
        if(trim((string)fgets($pipes[1]))!=='resolving')throw new RuntimeException('Worker did not reach DNS boundary');
        $beforeDns=$dnsCalls;$second=cw_create('concurrent.example',$parallel);
        cw_ok('Concurrent original form sees durable pending approval without DNS or send',$second->status==='uncertain'&&$dnsCalls===$beforeDns&&$calls===1);
        fwrite($pipes[0],"continue\n");fclose($pipes[0]);$workerOutput=stream_get_contents($pipes[1]);$workerError=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($worker);$worker=null;
        cw_ok('Concurrent worker alone sends once through native durable gateway',$exit===0&&$workerOutput==='accepted_pending:1'&&$workerError===''&&one('SELECT status FROM '.table('provider_operations').' WHERE idempotency_key=?',['cloudsite:'.$parallel])['status']==='accepted_pending');
    }finally{if(is_resource($worker)){proc_terminate($worker);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($worker);}}
    $mode='timeout';$unknown=bin2hex(random_bytes(16));$result=cw_create('timeout.example',$unknown);cw_ok('Timeout durable status is uncertain with one attempt',$result->status==='uncertain'&&$calls===2);cw_create('timeout.example',$unknown);cw_deny('Fresh nonce cannot retry unknown domain',fn()=>cw_create('timeout.example'));cw_ok('Unknown replay never sends again',$calls===2);$mode='accepted';
    $failedDns=cw_create('dns.example','',fn()=>'127.0.0.1');cw_ok('Private DNS refusal records a zero-send failure',$failedDns->status==='failed'&&$failedDns->attempts===0&&$calls===2);cw_ok('New reviewed request after definite no-send failure may proceed',cw_create('dns.example')->status==='accepted_pending'&&$calls===3);
    $before=$calls;$result=cw_create('revoked.example','',function(){query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');return cw_dns();});query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');cw_ok('Client deactivation during DNS prevents send',$result->status==='failed'&&$result->attempts===0&&$calls===$before);
    $revoked=bin2hex(random_bytes(16));$result=cw_create('grant.example',$revoked,function()use($revoked,$password){provider_operation_revoke($revoked,$password);return cw_dns();});cw_ok('Approval revocation during DNS prevents send',$result->status==='failed'&&$calls===$before);
    $result=cw_create('reassigned.example','',function()use($link){query('UPDATE '.table('provider_cloud_links').' SET revision=2 WHERE id=?',[$link['id']]);return cw_dns();});query('UPDATE '.table('provider_cloud_links').' SET revision=1 WHERE id=?',[$link['id']]);cw_ok('Assignment revision change during DNS prevents send',$result->status==='failed'&&$calls===$before);
    $beforeTables=all('SHOW TABLES');require_once CWP_CODE_ROOT.'/includes/layout/admin.php';$record=provider_settings_projection(provider_settings_load($profileId));$cloudObservation=provider_inventory_observation($profileId,InventoryRead::CLOUD_SERVERS_LIST);ob_start();require CWP_CODE_ROOT.'/admin/views/provider_cloud_sites.php';$html=ob_get_clean();cw_ok('Actual owner view includes typed consent/password and pending-ID guidance',str_contains($html,'data-cloud-website-create')&&str_contains($html,'name="reviewed"')&&str_contains($html,'name="current_password"')&&str_contains($html,'Returned package #8881')&&!str_contains($html,$secret)&&all('SHOW TABLES')===$beforeTables);
    cw_client(1);$mine=provider_cloud_sites_client_history(1201);cw_ok('Actual client sees own safe pending request summary',count($mine)>=1&&in_array('first.example',array_column($mine,'domain'),true)&&!str_contains(json_encode($mine),'8881')&&!str_contains(json_encode($mine),$profileId));cw_client(2);cw_ok('Other client cannot see assigned server request history',provider_cloud_sites_client_history(1201)===[]);cw_owner();
    $_GET['support']='test';cw_ok('Support selector cannot view private cloud request history',provider_cloud_sites_client_history(1201)===[]);unset($_GET['support']);
    $stored=json_encode([all('SELECT * FROM '.table('provider_operation_grants')),all('SELECT * FROM '.table('provider_operations')),all('SELECT * FROM '.table('audit'))]);cw_ok('Approval ledger and audit never retain API key/password',!str_contains($stored,$secret)&&!str_contains($stored,$password));
    $saved=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$nonce]);query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',['{}',$nonce]);cw_deny('Corrupt immutable request scope blocks history',fn()=>provider_cloud_sites_history($profileId));query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',[$saved['scope_params'],$nonce]);
    query('DELETE FROM '.table('provider_operation_grants').' WHERE id=?',[$nonce]);cw_deny('Missing request history cannot be reconstructed as a new attempt',fn()=>cw_create('first.example'));cw_restore('provider_operation_grants',$saved);
    $startAudit=one('SELECT * FROM '.table('audit').' WHERE action=? AND detail LIKE ?',['provider.cloudsite_started','Cloud website request '.$nonce.' %']);$resultAudit=one('SELECT * FROM '.table('audit').' WHERE action=? AND detail LIKE ?',['provider.cloudsite_result','Cloud website request '.$nonce.' %']);$savedLedger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['cloudsite:'.$nonce]);
    query('DELETE FROM '.table('provider_operation_grants').' WHERE id=?',[$nonce]);query('DELETE FROM '.table('audit').' WHERE id IN (?,?)',[$startAudit['id'],$resultAudit['id']]);$beforeDns=$dnsCalls;$beforeCalls=$calls;
    cw_deny('Paired approval and audit loss cannot bypass surviving provider ledger',fn()=>cw_create('first.example'));
    cw_ok('Ledger orphan refusal preserves exact outcome without DNS or transport',$dnsCalls===$beforeDns&&$calls===$beforeCalls&&one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['cloudsite:'.$nonce])===$savedLedger);
    cw_restore('provider_operation_grants',$saved);cw_restore('audit',$startAudit);cw_restore('audit',$resultAudit);
    $orphanKey='cloudsite:'.bin2hex(random_bytes(16));(new ProviderOperationLedger())->claim($orphanKey,str_repeat('b',64),time());cw_deny('Unresolved cloud namespace reservation without approval blocks new requests',fn()=>cw_create('orphan.example'));query('DELETE FROM '.table('provider_operations').' WHERE idempotency_key=?',[$orphanKey]);
    query('DELETE FROM '.table('provider_operation_grants').' WHERE id=?',[$nonce]);query('DELETE FROM '.table('audit').' WHERE id=?',[$startAudit['id']]);query('DELETE FROM '.table('provider_operations').' WHERE idempotency_key=?',['cloudsite:'.$nonce]);
    cw_deny('Retained result audit independently blocks paired approval and ledger loss',fn()=>cw_create('first.example'));cw_restore('provider_operation_grants',$saved);cw_restore('audit',$startAudit);cw_restore('provider_operations',$savedLedger);
    query('DELETE FROM '.table('settings').' WHERE name=?',['provider_operations_schema_version']);db()->exec('RENAME TABLE '.table('provider_operation_grants').' TO `pcws_held_grants`');
    try{cw_deny('Missing preparation marker with retained execution witnesses refuses recovery',fn()=>provider_operations_migrate($password));cw_ok('Preparation witness refusal creates no empty replacement approval table',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['pcws_provider_operation_grants']));}finally{db()->exec('RENAME TABLE `pcws_held_grants` TO '.table('provider_operation_grants'));save_setting('provider_operations_schema_version','4');}
    file_put_contents(__DIR__.'/../qa/provider-cloud-sites-native-results.json',json_encode(['scope'=>'Actual native database, permissions, forms and shared ledger with synthetic transport. No live provider call.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo count($checks)." native cloud website checks passed\n";
}catch(Throwable $e){file_put_contents(__DIR__.'/../qa/provider-cloud-sites-native-results.json',json_encode(['checks'=>$checks,'failure'=>$e->getMessage()],JSON_PRETTY_PRINT));fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
