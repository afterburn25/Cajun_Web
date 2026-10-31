<?php
/** Native disposable database, synthetic provider only. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/domain_search.php';
require __DIR__.'/../site/includes/layout/admin.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyIInventoryReader\DomainSearch;
$private=__DIR__.'/../test-runtime/domain-search-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-domain-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pds_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic domain owner passphrase';$secret='SYNTHETIC_DOMAIN_SEARCH_KEY';$dns=0;$sent=0;
function ds_native_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function ds_native_deny(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;ds_native_ok($name,true);return;}ds_native_ok($name,false);}
function ds_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function ds_dns():string {global $dns;$dns++;if(db()->inTransaction())throw new LogicException('DNS under transaction');return '8.8.8.8';}
function ds_cool():void {query('UPDATE '.table('provider_domain_searches').' SET started_epoch=started_epoch-120 WHERE started_epoch>?',[time()-30]);}
function ds_age():void {query('UPDATE '.table('provider_domain_searches').' SET started_epoch=started_epoch-7200');}
function ds_count(string $table):int {return (int)query('SELECT COUNT(*) FROM '.table($table))->fetchColumn();}
function ds_outcome(string $id):array {return one('SELECT * FROM '.table('provider_domain_search_results').' WHERE id=?',[$id]);}
function ds_audit_fail(string $action,callable $work):bool {
    db()->exec('CREATE TRIGGER pds_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic search audit failure'; END IF; END");
    try{$work();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER pds_fail');}return $failed;
}
final class DsNativeTransport implements HttpTransport {
    public function __construct(private TransportResponse $response,private Closure $authorize){}
    public function send(TransportRequest $request):TransportResponse {
        global $sent;$sent++;($this->authorize)();
        if($request->method!=='GET'||$request->url!=='https://api.20i.com/domain-search/example.invalid'||$request->body!==null)throw new LogicException('Unexpected synthetic search request.');
        return $this->response;
    }
}
function ds_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $query):array {
    global $secret,$response;
    if(db()->inTransaction()||$key!==$secret||$address!=='8.8.8.8')throw new LogicException('Invalid native credential boundary.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($key,$authorize):string{$authorize();return $key;});
    return DomainSearch::read(new DsNativeTransport($response,Closure::fromCallable($authorize)),$credential,$query,time());
}
function ds_run(string $id,int $profileRevision=1,int $configRevision=1,?callable $resolver=null,?callable $transport=null):string {
    return provider_domain_search_run($id,$profileRevision,$configRevision,'EXAMPLE.invalid',$resolver??'ds_dns',$transport??'ds_read');
}
function ds_render():string {$record=provider_settings_profile();ob_start();require __DIR__.'/../site/admin/views/provider_domain_search.php';return ob_get_clean();}
require __DIR__.'/provider_domain_client_cases.php';
if(($argv[1]??'')==='concurrent-client-search'){
    $clientId=(int)$argv[2];dsc_login($clientId);
    $response=new TransportResponse(200,[],'{"header":{"names":["example.invalid"]}}'."\n".'{"name":"example.invalid","can":"register"}');
    echo "ready\n";fflush(STDOUT);stream_set_timeout(STDIN,15);
    if(trim((string)fgets(STDIN))!=='start')exit(2);
    try{provider_domain_client_run($clientId,$argv[3],bin2hex(random_bytes(16)),'example.invalid','ds_dns','ds_read');echo json_encode(['state'=>'recorded','sent'=>$sent]);}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo json_encode(['state'=>'refused','sent'=>$sent]);}
    exit;
}
if(($argv[1]??'')==='concurrent-search') {
    ds_actor((int)$argv[3]);
    $response=new TransportResponse(200,[],'{"header":{"names":["example.invalid"]}}'."\n".'{"name":"example.invalid","can":"register"}');
    echo "ready\n";fflush(STDOUT);stream_set_timeout(STDIN,15);
    if(trim((string)fgets(STDIN))!=='start')exit(2);
    try{$request=ds_run($argv[2],1,6);echo json_encode(['state'=>'recorded','sent'=>$sent,'id'=>$request]);}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo json_encode(['state'=>'refused','sent'=>$sent]);}
    exit;
}
try {
    foreach(schema_statements('pds_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Search owner '.$i,'search'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>'administrator',3=>'owner'] as $uid=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$uid,$role,json_encode(array_keys(staff_permissions()))]);
    ds_actor();provider_settings_migrate($password);$id=provider_settings_save('',0,'Search fixture',true,$secret,$password);
    ds_native_ok('Search starts unprepared without implicit schema changes',!provider_domain_search_ready()&&str_contains(ds_render(),'Prepare domain search')&&!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['pds_provider_domain_searches']));
    ds_native_deny('Preparation requires current owner authority without changing records',fn()=>cwp_test_owner_revoked(fn()=>provider_domain_search_migrate('wrong')));
    provider_domain_search_migrate($password);provider_domain_search_migrate($password);
    ds_native_ok('Additive preparation and reapplication succeed with empty history',provider_domain_search_ready()&&ds_count('provider_domain_searches')===0);
    save_setting('provider_domain_search_schema_version','99');ds_native_deny('Unsupported search schema version is refused',fn()=>provider_domain_search_migrate($password));save_setting('provider_domain_search_schema_version','1');
    ds_native_deny('Default disabled policy prevents any lookup',fn()=>ds_run($id));
    ds_native_deny('An empty offering cannot enable search',fn()=>provider_domain_search_configure($id,1,0,true,'',$password));
    foreach(['.com','com?bad','xn--caf-dma','bad/suffix'] as $i=>$value)ds_native_deny('Malformed extension refused '.$i,fn()=>provider_domain_search_configure($id,1,0,true,$value,$password));
    ds_native_deny('Search configuration refuses revoked owner authority with retained state',fn()=>cwp_test_owner_revoked(fn()=>provider_domain_search_configure($id,1,0,true,'invalid','wrong')));
    ds_native_ok('Configuration audit failure rolls back settings',ds_audit_fail('domain_search_configured',fn()=>provider_domain_search_configure($id,1,0,true,'invalid',$password))&&provider_domain_search_configuration()['revision']===0);
    provider_domain_search_configure($id,1,0,true," INVALID\nco.uk\ninvalid",$password);
    ds_native_ok('Offering normalizes and deduplicates suffixes',provider_domain_search_configuration()['tlds']===['co.uk','invalid']);
    ds_native_deny('Stale configuration cannot overwrite current settings',fn()=>provider_domain_search_configure($id,1,0,true,'invalid',$password));
    ds_native_deny('Stale search form performs no request',fn()=>ds_run($id,1,0));
    ds_native_deny('Wrong provider revision performs no request',fn()=>ds_run($id,2,1));
    ds_native_deny('Unsold extension performs no request',fn()=>provider_domain_search_run($id,1,1,'example.com','ds_dns','ds_read'));
    ds_native_deny('Raw query injection performs no request',fn()=>provider_domain_search_run($id,1,1,'example.invalid?secret','ds_dns','ds_read'));
    ds_actor(2);ds_native_deny('Other administrator cannot search',fn()=>ds_run($id));ds_native_deny('Other administrator cannot inspect search history',fn()=>provider_domain_search_history($id));ds_actor();
    $_GET['support']='fixture';ds_native_deny('Client support cannot search',fn()=>ds_run($id));unset($_GET['support']);
    $_SESSION=['client_id'=>1,'client_auth'=>'synthetic'];ds_native_deny('Client cannot use owner search or reseller history',fn()=>ds_run($id));ds_actor();
    ds_native_ok('All rejected starts leave network and history untouched',$dns===0&&$sent===0&&ds_count('provider_domain_searches')===0);
    ds_native_ok('Intent audit failure prevents network work',ds_audit_fail('domain_search_started',fn()=>ds_run($id))&&$dns===0&&$sent===0&&ds_count('provider_domain_searches')===0);
    $body='{"header":{"names":["example.invalid"]}}'."\n".'{"name":"suggested.invalid","can":"register"}'."\n".'{"name":"unsold.com","can":"register"}'."\n".'{"name":"example.invalid","can":"register"}';
    $response=new TransportResponse(200,[],$body);
    $request=ds_run($id);$result=ds_outcome($request);$payload=json_decode($result['result'],true);
    ds_native_ok('Explicit search saves one canonical checked result',$dns===1&&$sent===1&&$result['state']==='checked'&&$payload['query']==='example.invalid'&&$payload['registration_available']===true);
    ds_native_ok('Unverified suggestions exclude locally unsold suffixes',$payload['suggestions']===['suggested.invalid']);
    $intent=one('SELECT * FROM '.table('provider_domain_searches').' WHERE id=?',[$request]);
    ds_native_ok('Intent retains original account settings actor query and offering',$intent['profile_id']===$id&&(int)$intent['profile_revision']===1&&(int)$intent['config_revision']===1&&(int)$intent['actor_id']===1&&$intent['query_name']==='example.invalid'&&json_decode($intent['tlds'],true)===['co.uk','invalid']);
    ds_native_ok('Credential is fingerprinted and excluded from saved history',$result['key_fingerprint']===provider_inventory_fingerprint($secret)&&!str_contains(json_encode(provider_domain_search_history($id)),$secret));
    ds_native_deny('Immediate repeated form is throttled',fn()=>ds_run($id));ds_actor(3);ds_native_deny('Another owner shares the same cooldown',fn()=>ds_run($id));ds_actor();
    ds_native_ok('Opening history and native forms performs no lookup',str_contains(ds_render(),'Available when checked')&&str_contains(ds_render(),'suggested.invalid')&&!str_contains(ds_render(),'unsold.com')&&$sent===1);
    $_POST=['action'=>'provider_inventory_domain_search_run','domain_query'=>'"><script>alert(1)</script>'];
    $view=ds_render();ds_native_ok('Invalid query is preserved as escaped text in the native form',str_contains($view,'&lt;script&gt;')&&!str_contains($view,'<script>alert'));$_POST=[];
    ds_cool();$before=$sent;$request=ds_run($id,1,1,fn()=>'127.0.0.1');
    ds_native_ok('Private destination never opens provider transport',ds_outcome($request)['reason']==='destination_unavailable'&&$sent===$before);
    ds_cool();$response=new TransportResponse(200,[],'{"name":"example.invalid","can":"register"}');$request=ds_run($id);
    ds_native_ok('Incomplete stream remains partial in durable history',ds_outcome($request)['state']==='partial'&&!json_decode(ds_outcome($request)['result'],true)['registration_available']);
    ds_cool();$response=new TransportResponse(401,[],'PRIVATE_REMOTE_DETAIL');$request=ds_run($id);
    ds_native_ok('Authentication failure retains bounded reason only',ds_outcome($request)['reason']==='authentication'&&!str_contains(ds_outcome($request)['result'],'PRIVATE_REMOTE_DETAIL'));
    ds_cool();$before=$sent;
    $request=ds_run($id,1,1,function()use($id,$password):string {provider_domain_search_configure($id,1,1,false,'invalid',$password);return ds_dns();});
    ds_native_ok('Revocation during DNS discards the read before transport',ds_outcome($request)['state']==='access_changed'&&ds_outcome($request)['result']==='null'&&$sent===$before);
    provider_domain_search_configure($id,1,2,true,'invalid',$password);ds_cool();$response=new TransportResponse(200,[],$body);
    $request=ds_run($id,1,3,null,function($row,$key,$address,$authorize,$query)use($id,$password):array {$r=ds_read($row,$key,$address,$authorize,$query);provider_domain_search_configure($id,1,3,false,'invalid',$password);return $r;});
    ds_native_ok('Revocation before result persistence discards availability',ds_outcome($request)['state']==='access_changed'&&ds_outcome($request)['result']==='null'&&ds_outcome($request)['key_fingerprint']==='');
    provider_domain_search_configure($id,1,4,true,'invalid',$password);ds_cool();
    $before=ds_count('provider_domain_search_results');$attempts=ds_count('provider_domain_searches');$beforeSent=$sent;
    ds_native_ok('Result audit failure retains incomplete intent without a verdict',ds_audit_fail('domain_search_finished',fn()=>ds_run($id,1,5))&&ds_count('provider_domain_search_results')===$before&&ds_count('provider_domain_searches')===$attempts+1&&$sent===$beforeSent+1);
    ds_native_deny('Incomplete request still consumes the shared cooldown',fn()=>ds_run($id,1,5));
    ds_cool();$response=new TransportResponse(429,[],'PRIVATE_RATE_DETAIL');$request=ds_run($id,1,5);ds_cool();
    ds_native_ok('Rate limit is persisted without provider text',ds_outcome($request)['reason']==='rate_limited'&&!str_contains(ds_outcome($request)['result'],'PRIVATE_RATE_DETAIL'));
    ds_native_deny('Rate limit pauses later requests for 24 hours',fn()=>ds_run($id,1,5));
    provider_domain_search_configure($id,1,5,true,'invalid',$password);ds_actor(3);
    ds_native_deny('Settings changes and different owner cannot bypass provider pause',fn()=>ds_run($id,1,6));ds_actor();
    query('UPDATE '.table('provider_domain_search_results').' SET finished_epoch=finished_epoch-86401 WHERE id=?',[$request]);ds_age();
    $response=new TransportResponse(200,[],$body);$request=ds_run($id,1,6);
    ds_native_ok('An explicit new search can run after the provider pause expires',ds_outcome($request)['state']==='checked');
    $stored=all('SELECT * FROM '.table('provider_domain_searches').' ORDER BY id');$results=all('SELECT * FROM '.table('provider_domain_search_results').' ORDER BY id');
    provider_domain_search_migrate($password);
    ds_native_ok('Repeated preparation preserves original intents fingerprints and outcomes',$stored===all('SELECT * FROM '.table('provider_domain_searches').' ORDER BY id')&&$results===all('SELECT * FROM '.table('provider_domain_search_results').' ORDER BY id'));
    save_setting('provider_domain_search_schema_version','');ds_native_deny('Lost version marker cannot adopt populated history',fn()=>provider_domain_search_migrate($password));save_setting('provider_domain_search_schema_version','1');
    db()->exec('RENAME TABLE '.table('provider_domain_search_results').' TO pds_saved_search_results');
    ds_native_ok('Missing stamped history disables lookup readiness',!provider_domain_search_ready());
    ds_native_deny('Missing stamped history cannot be silently recreated',fn()=>provider_domain_search_migrate($password));
    save_setting('provider_domain_search_schema_version','');
    ds_native_deny('Lost owner marker and result history cannot cause partial DDL',fn()=>provider_domain_search_migrate($password));
    ds_native_ok('Preflight preserves missing owner result table for exact recovery',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['pds_provider_domain_search_results']));
    save_setting('provider_domain_search_schema_version','1');
    db()->exec('RENAME TABLE pds_saved_search_results TO '.table('provider_domain_search_results'));
    ds_native_ok('Restoring original history restores readiness',provider_domain_search_ready()&&$results===all('SELECT * FROM '.table('provider_domain_search_results').' ORDER BY id'));
    db()->exec('ALTER TABLE '.table('provider_domain_search_results')." MODIFY reason VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'unexpected'");
    ds_native_deny('Unexpected schema defaults are refused',fn()=>provider_domain_search_migrate($password));
    db()->exec('ALTER TABLE '.table('provider_domain_search_results').' ALTER reason DROP DEFAULT');
    provider_domain_search_migrate($password);
    ds_age();$workers=[];
    foreach([1,3] as $actorId) {
        $process=proc_open([PHP_BINARY,__FILE__,'concurrent-search',$id,(string)$actorId],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Cannot start concurrent lookup fixture.');
        stream_set_timeout($pipes[1],20);stream_set_timeout($pipes[2],20);
        if(trim((string)fgets($pipes[1]))!=='ready')throw new RuntimeException('Concurrent lookup fixture did not reach its barrier.');
        $workers[]=[$process,$pipes];
    }
    foreach($workers as [$process,$pipes]){fwrite($pipes[0],"start\n");fflush($pipes[0]);fclose($pipes[0]);}
    $outcomes=[];
    foreach($workers as [$process,$pipes]) {
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0||$stderr!=='')throw new RuntimeException('Concurrent lookup worker failed: '.$stderr);
        $outcomes[]=json_decode($stdout,true,8,JSON_THROW_ON_ERROR);
    }
    ds_native_ok('Separate owner processes serialize intent and permit one provider read',count(array_filter($outcomes,fn($r)=>$r['state']==='recorded'))===1&&count(array_filter($outcomes,fn($r)=>$r['state']==='refused'))===1&&array_sum(array_column($outcomes,'sent'))===1);
    ds_cool();

    for($i=0;$i<20;$i++)query('INSERT INTO '.table('provider_domain_searches').' (id,profile_id,profile_revision,config_revision,actor_id,query_name,tlds,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?)',[bin2hex(random_bytes(16)),$id,1,6,$i%2===0?1:3,'example.invalid','["invalid"]',gmdate('Y-m-d H:i:s',time()-60),time()-60]);
    ds_native_deny('Shared hourly limit counts incomplete attempts across owners',fn()=>ds_run($id,1,6));ds_age();
    provider_settings_save($id,1,'Changed connection',true,'',$password);
    ds_native_deny('Connection changes require explicit new search policy approval',fn()=>ds_run($id,2,6));
    ds_native_ok('Old observations remain marked as earlier settings',str_contains(ds_render(),'Earlier settings')&&str_contains(ds_render(),'Changing the connection requires'));
    ds_native_ok('Search history never creates registrar ownership or commercial operations',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['pds_provider_domain_links']));
    ds_client_cases($id);
    $report=['passed'=>true,'checks'=>$checks,'scope'=>'Native isolated MariaDB/MySQL, synthetic provider, actual PHP form rendering. HTTP/browser and live provider acceptance remain separate.'];
    file_put_contents(__DIR__.'/../qa/provider-domain-search-native-results.json',json_encode($report,JSON_PRETTY_PRINT)."\n");echo json_encode($report,JSON_PRETTY_PRINT),"\n";
}catch(Throwable $e){
    $report=['passed'=>false,'checks'=>$checks,'error'=>$e->getMessage(),'line'=>$e->getLine()];
    file_put_contents(__DIR__.'/../qa/provider-domain-search-native-results.json',json_encode($report,JSON_PRETTY_PRINT)."\n");echo json_encode($report,JSON_PRETTY_PRINT),"\n";exit(1);
}
