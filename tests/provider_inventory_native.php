<?php
/** Actual database refresh/link orchestration with synthetic resolver/transport; no provider API. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/app/providers/inventory.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportException,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryProjector,InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
$private=__DIR__.'/../test-runtime/provider-inventory-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-inv-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pcin_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic inventory owner passphrase';$secret='SYNTHETIC_PRIVATE_GENERAL_KEY';$dnsCalls=0;$transportCalls=0;
function pi_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function pi_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;pi_ok($name,true);return;}pi_ok($name,false);}
function pi_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function pi_resolve():string {global $dnsCalls;$dnsCalls++;if(db()->inTransaction())throw new LogicException('DNS under transaction');return '8.8.8.8';}
/** Fixture transport: records the authenticated request, returns queued bodies. */
final class PiTransport implements HttpTransport {
    public array $sent=[];private array $script;public $authorize=null;
    public function __construct(array $script){$this->script=$script;}
    public function send(TransportRequest $request):TransportResponse {global $transportCalls;$transportCalls++;$this->sent[]=$request;($this->authorize)();$next=array_shift($this->script)??new TransportResponse(500,[],'no fixture');if($next instanceof Throwable)throw $next;return $next;}
}
function pi_item(int $id,string $name='pkg.example'):array {return ['id'=>$id,'typeRef'=>9,'enabled'=>true,'name'=>$name,'names'=>[$name],'created'=>'2024-01-02T03:04:05Z','packageTypeName'=>'Web','packageLabels'=>['LBL'],'stackUsers'=>[],'productSpec'=>new stdClass(),'extra'=>'dropped'];}
function pi_read(array $row,#[SensitiveParameter] string $key,string $address,callable $authorize):InventorySnapshot {
    global $secret,$scripted;if(db()->inTransaction()||$key!==$secret||$address!=='8.8.8.8')throw new LogicException('Invalid credential/network boundary');
    $transport=new PiTransport($scripted);$transport->authorize=$authorize;
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($key,$authorize):string{$authorize();return $key;});
    return InventoryReader::read($transport,$credential,InventoryRead::PACKAGES_LIST,0,time());
}
function pi_refresh(string $id,?callable $resolver=null,?callable $transport=null,int $revision=1):string {global $password;return provider_inventory_refresh($id,$revision,$password,$resolver??'pi_resolve',$transport??'pi_read');}
function pi_cool():void {query('UPDATE '.table('provider_refreshes').' SET started_epoch=? WHERE started_epoch>?',[time()-31,time()-30]);}
function pi_count(string $name):int {return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function pi_audit_failure(string $action,callable $fn):bool {
    db()->exec('CREATE TRIGGER pcin_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic inventory audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER pcin_fail');}return $failed;
}
function pi_worker(string $mode,string $id,int $revision=1):array {
    $p=proc_open([PHP_BINARY,__FILE__,$mode,$id,(string)$revision],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Cannot start worker');foreach([1,2] as $s)stream_set_timeout($pipes[$s],20);return [$p,$pipes];
}
function pi_finish(array $worker):string {[$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err!=='')throw new RuntimeException('Worker failed: '.$err);return $out;}
$scripted=[];
if(in_array($argv[1]??'',['pause-dns','edit-worker'],true)) {
    pi_actor();$id=$argv[2];$revision=(int)$argv[3];
    if($argv[1]==='edit-worker'){echo "ready\n";fflush(STDOUT);provider_settings_save($id,$revision,'Concurrent update',true,'',$password);echo 'saved';}
    else{$GLOBALS['scripted']=[new TransportResponse(200,[],json_encode([pi_item(1)]))];echo provider_inventory_refresh($id,$revision,$password,function()use($argv):string {echo "resolving\n";fflush(STDOUT);stream_set_timeout(STDIN,15);if(trim(fgets(STDIN))!=='continue')throw new RuntimeException('Fixture synchronization');return pi_resolve();},'pi_read').':'.$transportCalls;}
    exit;
}
try {
    foreach(schema_statements('pcin_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Inventory staff '.$i,'inv'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    foreach([[1,'Client One','one@example.invalid'],[2,'Client Two','two@example.invalid']] as $c)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',[$c[1],$c[2],bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    pi_actor();provider_settings_migrate($password);$id=provider_settings_save('',0,'20i fixture',true,$secret,$password);
    provider_inventory_migrate($password);pi_ok('Inventory storage prepared','1'===(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_inventory_schema_version'])['value']??''));
    pi_deny('Wrong current password prevents DNS and refresh intent',fn()=>provider_inventory_refresh($id,1,'wrong','pi_resolve','pi_read'));
    pi_deny('Stale revision prevents DNS and refresh intent',fn()=>pi_refresh($id,null,null,2));
    pi_actor(2);pi_deny('Administrator cannot refresh provider inventory',fn()=>pi_refresh($id));pi_deny('Administrator cannot read inventory',fn()=>provider_inventory_latest($id));pi_actor();
    $_GET['support']='fixture';pi_deny('Client support cannot refresh inventory',fn()=>pi_refresh($id));unset($_GET['support']);
    $_SESSION=['client_id'=>1,'client_auth'=>'synthetic'];pi_deny('Client cannot refresh inventory',fn()=>pi_refresh($id));pi_actor();
    query('UPDATE '.table('provider_profiles').' SET enabled=0 WHERE id=?',[$id]);pi_deny('Disabled profile cannot refresh',fn()=>pi_refresh($id));query('UPDATE '.table('provider_profiles').' SET enabled=1 WHERE id=?',[$id]);
    pi_ok('Rejected starts use no network and retain no refresh rows',$dnsCalls===0&&$transportCalls===0&&pi_count('provider_refreshes')===0);
    pi_ok('Intent audit failure prevents DNS and durable start',pi_audit_failure('refresh_started',fn()=>pi_refresh($id))&&$dnsCalls===0&&$transportCalls===0&&pi_count('provider_refreshes')===0);
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(101,'a.example'),pi_item(102,'b.example')]))];
    pi_ok('Accepted refresh persists a complete observation',pi_refresh($id)==='complete'&&$dnsCalls===1&&$transportCalls===1);
    $latest=provider_inventory_latest($id);pi_ok('Result is bound to profile revision scope and unknown coverage',$latest['state']==='complete'&&$latest['profile_revision']===1&&$latest['scope']==='twenty-i|'.$id.'|packages.list'&&$latest['coverage']==='unknown'&&$latest['package_count']===2);
    $pkgs=provider_inventory_packages($latest['id']);pi_ok('Validated package rows persisted verbatim',count($pkgs)===2&&(int)$pkgs[0]['package_id']===101&&$pkgs[0]['name']==='a.example'&&$pkgs[0]['names']===['a.example']&&$pkgs[0]['unmapped_keys']===['extra']&&(int)$pkgs[0]['unmapped_count']===1);
    pi_deny('Profile cooldown prevents immediate duplicate refresh',fn()=>pi_refresh($id));pi_actor(3);pi_deny('Another owner cannot bypass shared cooldown',fn()=>pi_refresh($id));pi_actor();pi_cool();
    $before=$transportCalls;pi_ok('Private resolver result is refused before credentials and transport',pi_refresh($id,fn()=>'127.0.0.1')==='unavailable'&&$transportCalls===$before);pi_cool();
    pi_ok('Resolver exceptions produce only a bounded result',pi_refresh($id,fn()=>throw new RuntimeException('SYNTHETIC_PRIVATE_GENERAL_KEY DNS'))==='unavailable');pi_cool();
    foreach(['connect','tls','unavailable','timeout','protocol','unknown'] as $kind){$scripted=[new TransportException($kind)];pi_ok('Transport failure classified '.$kind,pi_refresh($id)==='unavailable');$r=provider_inventory_latest($id);pi_ok('Finite reason retained '.$kind,$r['state']==='unavailable'&&$r['reason']==='transport_'.$kind&&$r['package_count']===0);pi_cool();}
    $scripted=[new TransportResponse(401,[],'{}')];pi_ok('HTTP 401 classifies unavailable authentication',pi_refresh($id)==='unavailable'&&provider_inventory_latest($id)['reason']==='authentication');pi_cool();
    $scripted=[new TransportResponse(200,[],'not json')];pi_ok('Malformed body classifies unavailable',pi_refresh($id)==='unavailable'&&provider_inventory_latest($id)['reason']==='malformed_body');pi_cool();
    $bad=pi_item(103);unset($bad['id']);
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(101,'a.example'),$bad]))];pi_ok('Partial observation retains valid rows only',pi_refresh($id)==='partial'&&provider_inventory_latest($id)['skipped']===1);$good=provider_inventory_observation($id);pi_ok('Last good observation is the partial result',$good['state']==='partial'&&$good['skipped']===1);pi_cool();
    $scripted=[new TransportResponse(503,[],'{}')];pi_refresh($id);pi_ok('Unavailable refresh preserves the last good observation',provider_inventory_latest($id)['state']==='unavailable'&&provider_inventory_observation($id)['state']==='partial');pi_cool();
    $observation=provider_inventory_observation($id);
    // Linking: explicit owner approval over the observed identity only.
    pi_deny('Link without password refused',fn()=>provider_inventory_link($id,101,1,1,'wrong'));
    pi_actor(2);pi_deny('Administrator cannot link',fn()=>provider_inventory_link($id,101,1,1,$password));pi_actor();
    pi_deny('Stale revision cannot link',fn()=>provider_inventory_link($id,101,1,2,$password));
    pi_deny('Unobserved package cannot link',fn()=>provider_inventory_link($id,9999,1,1,$password));
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=2');pi_deny('Inactive client cannot link',fn()=>provider_inventory_link($id,101,2,1,$password));query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=2');
    provider_inventory_link($id,101,1,1,$password);pi_ok('Owner link persisted with event',count(provider_inventory_links($id))===1&&(int)provider_inventory_links($id)[0]['client_id']===1&&count(provider_inventory_link_events($id))===1);
    pi_deny('Duplicate package link refused',fn()=>provider_inventory_link($id,101,2,1,$password));
    $services=provider_inventory_client_services(1);pi_ok('Client sees only the linked observed package',count($services)===1&&(int)$services[0]['link']['package_id']===101&&$services[0]['present']&&$services[0]['package']['name']==='a.example'&&!isset($services[0]['package']['stack_users']));
    pi_ok('Other client sees no linked services',provider_inventory_client_services(2)===[]);
    $link=provider_inventory_links($id)[0];pi_deny('Unlink requires owner password',fn()=>provider_inventory_unlink($link['id'],'wrong'));
    provider_inventory_unlink($link['id'],$password);pi_ok('Unlink removes mapping and records event',provider_inventory_links($id)===[]&&count(provider_inventory_link_events($id))===2&&provider_inventory_link_events($id)[0]['event']==='unlink');
    pi_ok('Unlinked package absent from client view',provider_inventory_client_services(1)===[]);
    // Mid-flight races.
    $worker=pi_worker('pause-dns',$id);pi_ok('Refresh worker commits intent before blocking DNS',trim(fgets($worker[1][1]))==='resolving'&&pi_count('provider_refreshes')>pi_count('provider_refresh_results'));
    $row=provider_settings_profile();provider_settings_state($id,1,'disable',$password);fwrite($worker[1][0],"continue\n");fflush($worker[1][0]);pi_ok('Profile change during DNS prevents credential transport',pi_finish($worker)==='access_changed:0');provider_settings_state($id,2,'enable',$password);pi_cool();
    $worker=pi_worker('pause-dns',$id,3);pi_ok('Race worker reaches DNS',trim(fgets($worker[1][1]))==='resolving');query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');fwrite($worker[1][0],"continue\n");fflush($worker[1][0]);pi_ok('Owner revocation during DNS prevents credential transport',pi_finish($worker)==='access_changed:0');query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pi_actor();pi_cool();
    $code=provider_inventory_refresh($id,3,$password,'pi_resolve',function($row,$secret,$address,$authorize){query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');return InventorySnapshot::unavailable('transport_unknown',time(),'twenty-i|x|packages.list');});
    pi_ok('Owner revocation before result discards the read',$code==='access_changed'&&pi_count('provider_observation_packages')===3);query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pi_actor();pi_cool();
    $before=pi_count('provider_refresh_results');pi_ok('Terminal audit failure leaves an incomplete retained intent',pi_audit_failure('refresh_finished',fn()=>pi_refresh($id,null,null,3))&&pi_count('provider_refresh_results')===$before);pi_cool();
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(201,'c.example')]))];
    $code=provider_inventory_refresh($id,3,$password,'pi_resolve',function($row,$secret,$address,$authorize)use($id):InventorySnapshot {
        $worker=pi_worker('edit-worker',$id,3);pi_ok('Concurrent edit reaches shared lock during read',trim(fgets($worker[1][1]))==='ready');$GLOBALS['inv_edit_worker']=$worker;
        $authorize();pi_ok('Profile remains unchanged throughout locked read',(int)provider_settings_load($id)['revision']===3);return pi_read($row,$secret,$address,$authorize);
    });
    pi_ok('Current refresh completes before serialized edit',$code==='complete'&&pi_finish($GLOBALS['inv_edit_worker'])==='saved'&&(int)provider_settings_profile()['revision']===4);pi_cool();
    $durable=json_encode([all('SELECT * FROM '.table('provider_refreshes')),all('SELECT * FROM '.table('provider_refresh_results')),all('SELECT * FROM '.table('provider_observation_packages')),all('SELECT * FROM '.table('provider_package_links')),all('SELECT * FROM '.table('provider_package_link_events')),all('SELECT * FROM '.table('audit'))]);
    pi_ok('No durable row contains the secret or auth material',!str_contains($durable,$secret)&&!str_contains($durable,base64_encode($secret))&&!str_contains($durable,'Bearer')&&!str_contains($durable,'Authorization'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/provider-inventory-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native database refresh/observation/link orchestration with synthetic DNS and transport; no real 20i API or UI acceptance','checks'=>$checks],JSON_PRETTY_PRINT));echo ($ok?'PASS':'FAIL').': '.count($checks).' native provider inventory assertions'.PHP_EOL;exit($ok?0:1);
