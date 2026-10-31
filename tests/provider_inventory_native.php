<?php
/** Actual database refresh/link orchestration with synthetic resolver/transport; no provider API. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';

if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/admin/controllers/providers.php';
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
function pi_read(array $row,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation=InventoryRead::PACKAGES_LIST):InventorySnapshot {
    global $secret,$scripted;if(db()->inTransaction()||$key!==$secret||$address!=='8.8.8.8')throw new LogicException('Invalid credential/network boundary');
    $transport=new PiTransport($scripted);$transport->authorize=$authorize;
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($key,$authorize):string{$authorize();return $key;});
    return InventoryReader::read($transport,$credential,$operation,0,time());
}
function pi_refresh(string $id,?callable $resolver=null,?callable $transport=null,int $revision=1):string {global $password;return provider_inventory_refresh($id,$revision,$password,$resolver??'pi_resolve',$transport??'pi_read');}
function pi_cloud_wait():void {$epoch=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes'))->fetchColumn();$wait=max(0,$epoch+31-time());if($wait)sleep($wait);}
function pi_cool():void {if(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_assignment_head'])){query('UPDATE '.table('provider_refreshes').' SET started_epoch=started_epoch-120 WHERE started_epoch>? AND (operation IS NULL OR operation<>?)',[time()-30,InventoryRead::CLOUD_SERVERS_LIST]);pi_cloud_wait();}else query('UPDATE '.table('provider_refreshes').' SET started_epoch=started_epoch-120 WHERE started_epoch>?',[time()-30]);}
function pi_aged():void {if(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_assignment_head'])){query('UPDATE '.table('provider_refreshes').' SET started_epoch=started_epoch-7200 WHERE operation IS NULL OR operation<>?',[InventoryRead::CLOUD_SERVERS_LIST]);pi_cloud_wait();}else query('UPDATE '.table('provider_refreshes').' SET started_epoch=started_epoch-7200');}
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
    provider_inventory_migrate($password);pi_ok('Inventory storage prepared at v5','5'===(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_inventory_schema_version'])['value']??'')&&pi_count('provider_domain_links')===0&&pi_count('provider_domain_link_events')===0);
    // Populated v1/v2, interrupted migration and legacy suppression run in provider_inventory_upgrade_native.php.
    save_setting('provider_inventory_schema_version','99');pi_deny('Unsupported inventory storage version refused',fn()=>provider_inventory_migrate($password));save_setting('provider_inventory_schema_version','5');
    pi_deny('Revoked owner refuses: Wrong current password prevents DNS and refresh intent',fn()=>cwp_test_owner_revoked(fn()=>provider_inventory_refresh($id,1,'wrong','pi_resolve','pi_read')));
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
    pi_deny('Revoked owner refuses: Link without password refused',fn()=>cwp_test_owner_revoked(fn()=>provider_inventory_link($id,101,1,1,'wrong')));
    pi_actor(2);pi_deny('Administrator cannot link',fn()=>provider_inventory_link($id,101,1,1,$password));pi_actor();
    pi_deny('Stale revision cannot link',fn()=>provider_inventory_link($id,101,1,2,$password));
    pi_deny('Unobserved package cannot link',fn()=>provider_inventory_link($id,9999,1,1,$password));
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=2');pi_deny('Inactive client cannot link',fn()=>provider_inventory_link($id,101,2,1,$password));query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=2');
    provider_inventory_link($id,101,1,1,$password);pi_ok('Owner link persisted with event',count(provider_inventory_links($id))===1&&(int)provider_inventory_links($id)[0]['client_id']===1&&count(provider_inventory_link_events($id))===1);
    pi_deny('Duplicate package link refused',fn()=>provider_inventory_link($id,101,2,1,$password));
    $services=provider_inventory_client_services(1);pi_ok('Client sees only the linked observed package',count($services)===1&&(int)$services[0]['link']['package_id']===101&&$services[0]['present']&&$services[0]['package']['name']==='a.example'&&!isset($services[0]['package']['stack_users']));
    pi_ok('Other client sees no linked services',provider_inventory_client_services(2)===[]);
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(201,'c.example')]))];pi_ok('Refresh omitting the linked package completes',pi_refresh($id)==='complete');pi_cool();
    $services=provider_inventory_client_services(1);pi_ok('Linked mapping persists honestly when absent from the last good read',count($services)===1&&!$services[0]['present']&&$services[0]['package']===null&&provider_inventory_links($id)!==[]);
    $link=provider_inventory_links($id)[0];pi_deny('Revoked owner refuses: Unlink requires owner password',fn()=>cwp_test_owner_revoked(fn()=>provider_inventory_unlink($link['id'],'wrong')));
    provider_inventory_unlink($link['id'],$password);pi_ok('Unlink removes mapping and records event',provider_inventory_links($id)===[]&&count(provider_inventory_link_events($id))===2&&provider_inventory_link_events($id)[0]['event']==='unlink');
    pi_ok('Unlinked package absent from client view',provider_inventory_client_services(1)===[]);
    // Domain linking: explicit owner approval of one observed name,
    // independent of which client owns the carrying package.
    pi_deny('Revoked owner refuses: Domain link without password refused',fn()=>cwp_test_owner_revoked(fn()=>provider_inventory_domain_link($id,201,'c.example',2,1,'wrong')));
    pi_actor(2);pi_deny('Administrator cannot link a domain',fn()=>provider_inventory_domain_link($id,201,'c.example',2,1,$password));pi_actor();
    pi_deny('Stale revision cannot link a domain',fn()=>provider_inventory_domain_link($id,201,'c.example',2,2,$password));
    pi_deny('Unobserved domain cannot link',fn()=>provider_inventory_domain_link($id,201,'unseen.example',2,1,$password));
    pi_deny('Domain on an unobserved package cannot link',fn()=>provider_inventory_domain_link($id,9999,'c.example',2,1,$password));
    pi_deny('Malformed domain refused',fn()=>provider_inventory_domain_link($id,201,'bad..name',2,1,$password));
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=2');pi_deny('Inactive client cannot receive a domain link',fn()=>provider_inventory_domain_link($id,201,'c.example',2,1,$password));query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=2');
    provider_inventory_domain_link($id,201,'C.EXAMPLE',2,1,$password);
    $dlink=provider_inventory_domain_links($id)[0];
    pi_ok('Domain link persisted normalized with event',$dlink['domain']==='c.example'&&(int)$dlink['client_id']===2&&count(provider_inventory_domain_link_events($id))===1);
    pi_deny('Duplicate domain link refused',fn()=>provider_inventory_domain_link($id,201,'c.example',1,1,$password));
    $clientDomains=provider_inventory_client_domains(2);
    pi_ok('Client sees the linked observed domain',count($clientDomains)===1&&$clientDomains[0]['domain']==='c.example'&&$clientDomains[0]['present']&&!$clientDomains[0]['context_changed']);
    pi_ok('A domain link exposes no sibling hosting name or names collection',!isset($clientDomains[0]['package']['name'])&&!isset($clientDomains[0]['package']['names']));
    pi_ok('Other client sees no linked domains',provider_inventory_client_domains(1)===[]);
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(301,'c.example')]))];pi_refresh($id);pi_cool();
    $clientDomains=provider_inventory_client_domains(2);
    pi_ok('A moved domain retains its original approved pair without disclosing the new package',count($clientDomains)===1&&!$clientDomains[0]['present']&&$clientDomains[0]['package']===null&&(int)$clientDomains[0]['link']['package_id']===201);
    pi_deny('A moved domain cannot be silently re-approved on another package',fn()=>provider_inventory_domain_relink($dlink['id'],1,$password));
    pi_ok('A moved domain stays isolated from the other client',provider_inventory_client_domains(1)===[]);
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(301,'d.example')]))];pi_refresh($id);pi_cool();
    $clientDomains=provider_inventory_client_domains(2);
    pi_ok('Domain absent from the last good read stays honest',count($clientDomains)===1&&!$clientDomains[0]['present']);
    pi_deny('Re-approval requires the domain to reappear on the package',fn()=>provider_inventory_domain_relink($dlink['id'],1,$password));
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(201,'c.example')]))];pi_refresh($id);pi_cool();
    provider_inventory_domain_relink($dlink['id'],1,$password);
    pi_ok('Domain re-approval rebinds under the current observation',provider_inventory_domain_link_events($id)[0]['event']==='relink'&&provider_inventory_client_domains(2)[0]['present']);
    pi_deny('Revoked owner refuses: Domain unlink requires owner password',fn()=>cwp_test_owner_revoked(fn()=>provider_inventory_domain_unlink($dlink['id'],'wrong')));
    provider_inventory_domain_unlink($dlink['id'],$password);
    pi_ok('Domain unlink removes mapping and records event',provider_inventory_domain_links($id)===[]&&provider_inventory_domain_link_events($id)[0]['event']==='unlink'&&provider_inventory_client_domains(2)===[]);
    // Exercise the real hourly quota before moving this isolated fixture to
    // a new time window for unrelated concurrency/audit scenarios.
    $quota=(int)query('SELECT COUNT(*) FROM '.table('provider_refreshes').' WHERE actor_id=1 AND started_epoch>?',[time()-3600])->fetchColumn();
    pi_ok('Earlier fixture reads remain within the per-owner hourly quota',$quota<=20);
    for($i=$quota;$i<20;$i++){pi_cool();$scripted=[new TransportResponse(503,[],'{}')];pi_refresh($id);}
    pi_cool();$quotaDns=$dnsCalls;$quotaTransport=$transportCalls;$quotaRows=pi_count('provider_refreshes');
    pi_deny('Per-owner hourly quota refuses the next refresh',fn()=>pi_refresh($id));
    pi_ok('Hourly refusal persists no intent and performs no network work',$dnsCalls===$quotaDns&&$transportCalls===$quotaTransport&&pi_count('provider_refreshes')===$quotaRows);
    pi_aged();
    // Mid-flight races in the next synthetic hour. Original rows stay retained.
    $worker=pi_worker('pause-dns',$id);pi_ok('Refresh worker commits intent before blocking DNS',trim(fgets($worker[1][1]))==='resolving'&&pi_count('provider_refreshes')>pi_count('provider_refresh_results'));
    $row=provider_settings_profile();provider_settings_state($id,1,'disable',$password);fwrite($worker[1][0],"continue\n");fflush($worker[1][0]);pi_ok('Profile change during DNS prevents credential transport',pi_finish($worker)==='access_changed:0');provider_settings_state($id,2,'enable',$password);pi_cool();
    $worker=pi_worker('pause-dns',$id,3);pi_ok('Race worker reaches DNS',trim(fgets($worker[1][1]))==='resolving');query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');fwrite($worker[1][0],"continue\n");fflush($worker[1][0]);pi_ok('Owner revocation during DNS prevents credential transport',pi_finish($worker)==='access_changed:0');query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pi_actor();pi_cool();
    $beforeRevokedRead=pi_count('provider_observation_packages');
    $code=provider_inventory_refresh($id,3,$password,'pi_resolve',function($row,$secret,$address,$authorize){query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');return InventorySnapshot::unavailable('transport_unknown',time(),'twenty-i|x|packages.list');});
    pi_ok('Owner revocation before result discards the read',$code==='access_changed'&&pi_count('provider_observation_packages')===$beforeRevokedRead);query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pi_actor();pi_cool();
    $before=pi_count('provider_refresh_results');pi_ok('Terminal audit failure leaves an incomplete retained intent',pi_audit_failure('refresh_finished',fn()=>pi_refresh($id,null,null,3))&&pi_count('provider_refresh_results')===$before);pi_cool();
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(201,'c.example')]))];
    $code=provider_inventory_refresh($id,3,$password,'pi_resolve',function($row,$secret,$address,$authorize)use($id):InventorySnapshot {
        $worker=pi_worker('edit-worker',$id,3);pi_ok('Concurrent edit reaches shared lock during read',trim(fgets($worker[1][1]))==='ready');$GLOBALS['inv_edit_worker']=$worker;
        $authorize();pi_ok('Profile remains unchanged throughout locked read',(int)provider_settings_load($id)['revision']===3);return pi_read($row,$secret,$address,$authorize);
    });
    pi_ok('Current refresh completes before serialized edit',$code==='complete'&&pi_finish($GLOBALS['inv_edit_worker'])==='saved'&&(int)provider_settings_profile()['revision']===4);pi_cool();
    // Context binding: mappings are approved against the exact profile
    // revision and the credential context that produced the observation.
    pi_aged();$observation=provider_inventory_observation($id);
    pi_ok('Fixture ends with an older-revision last good observation',(int)$observation['profile_revision']===3&&(int)provider_settings_profile()['revision']===4);
    pi_deny('Linking refuses an observation from an older profile revision',fn()=>provider_inventory_link($id,201,1,4,$password));
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(201,'c.example')]))];
    pi_ok('Refresh under the current revision renews the binding context',pi_refresh($id,null,null,4)==='complete'&&(int)provider_inventory_observation($id)['profile_revision']===4);pi_cool();
    provider_inventory_link($id,201,1,4,$password);
    $link=provider_inventory_links($id)[0];$observation=provider_inventory_observation($id);
    pi_ok('Link binds the approved revision and credential context',(int)$link['profile_revision']===4&&(int)$link['current_revision']===4&&$link['key_fingerprint']!==''&&$link['key_fingerprint']===$observation['key_fingerprint']);
    $services=provider_inventory_client_services(1);
    pi_ok('Approved-context mapping serves its package',count($services)===1&&$services[0]['present']&&$services[0]['package']['name']==='c.example'&&$services[0]['context_changed']===false);
    $fingerprintOne=$observation['key_fingerprint'];
    // Replacing the configured key is a new context: the same package id in a
    // different account must never flow through the old mapping.
    provider_settings_save($id,4,'Rotated fixture key',true,'ROTATED_SYNTHETIC_GENERAL_KEY',$password);$secret='ROTATED_SYNTHETIC_GENERAL_KEY';
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(201,'other-account-private.example')]))];
    pi_ok('Replacement-context refresh completes under the new revision',pi_refresh($id,null,null,5)==='complete');pi_cool();
    $observation=provider_inventory_observation($id);pi_ok('Credential contexts produce distinct fingerprints',(int)$observation['profile_revision']===5&&$observation['key_fingerprint']!==$fingerprintOne);
    $services=provider_inventory_client_services(1);
    pi_ok('Changed context exposes nothing through the old mapping',count($services)===1&&!$services[0]['present']&&$services[0]['package']===null&&$services[0]['context_changed']===true);
    $link=provider_inventory_links($id)[0];pi_ok('Old mapping remains stored and is marked stale',(int)$link['profile_revision']===4&&(int)$link['current_revision']===5);
    pi_deny('Re-approval requires the exact current revision',fn()=>provider_inventory_relink($link['id'],4,$password));
    pi_deny('Revoked owner refuses: Re-approval requires the owner password',fn()=>cwp_test_owner_revoked(fn()=>provider_inventory_relink($link['id'],5,'wrong')));
    provider_inventory_relink($link['id'],5,$password);
    $link=provider_inventory_links($id)[0];
    pi_ok('Re-approval rebinds the mapping to the current context',(int)$link['profile_revision']===5&&$link['key_fingerprint']===$observation['key_fingerprint']&&provider_inventory_link_events($id)[0]['event']==='relink');
    $services=provider_inventory_client_services(1);
    pi_ok('Re-approved mapping serves the current-context data',count($services)===1&&$services[0]['present']&&$services[0]['package']['name']==='other-account-private.example'&&$services[0]['context_changed']===false);
    // Any settings change — even one that keeps the same key — suspends the
    // mapping because account continuity cannot be verified by the key bytes.
    provider_settings_save($id,5,'Renamed fixture profile',true,'',$password);
    $services=provider_inventory_client_services(1);
    pi_ok('A settings-only change still suspends the mapping',count($services)===1&&$services[0]['context_changed']===true&&!$services[0]['present']);
    pi_deny('Re-approval without a current-revision observation is refused',fn()=>provider_inventory_relink($link['id'],6,$password));
    provider_inventory_unlink($link['id'],$password);pi_ok('A context-stale mapping can still be removed',provider_inventory_links($id)===[]&&provider_inventory_client_services(1)===[]);
    pi_aged();
    $cloudItem=['id'=>1201,'name'=>'cloud.synthetic.invalid','configuration'=>['CpuCores'=>2,'RamMb'=>2048,'OsDiskSizeGb'=>40,'DataDiskSizeGb'=>0],'packageIds'=>[201],'unknown'=>['secret'=>'CLOUD_PRIVATE_MARKER']];
    $scripted=[new TransportResponse(200,[],json_encode([pi_item(201,'package-before-cloud.example')]))];
    pi_ok('Package observation before cloud refresh is retained',pi_refresh($id,null,null,6)==='complete');pi_cool();
    provider_inventory_link($id,201,1,6,$password);
    $packageBefore=provider_inventory_observation($id);$clientBefore=provider_inventory_client_services(1);$packagesBefore=all('SELECT * FROM '.table('provider_observation_packages'));
    provider_cloud_assignment_prepare($password);
    $cloudRefresh=fn($transport=null,$revision=6)=>provider_inventory_refresh($id,$revision,$password,'pi_resolve',$transport??'pi_read',InventoryRead::CLOUD_SERVERS_LIST);
    $scripted=[new TransportResponse(200,[],json_encode([$cloudItem]))];
    pi_ok('Cloud read uses the existing durable refresh engine',$cloudRefresh()==='complete');pi_cool();
    $cloud=provider_inventory_observation($id,InventoryRead::CLOUD_SERVERS_LIST);$cloudRows=provider_inventory_cloud_servers($cloud['id']);
    pi_ok('Cloud intent and result retain operation account revision and fingerprint',one('SELECT operation FROM '.table('provider_refreshes').' WHERE id=?',[$cloud['id']])['operation']===InventoryRead::CLOUD_SERVERS_LIST&&$cloud['scope']==='twenty-i|'.$id.'|cloudservers.list'&&(int)$cloud['profile_revision']===6&&$cloud['key_fingerprint']===$packageBefore['key_fingerprint']);
    pi_ok('Only sanitized cloud metadata is saved',count($cloudRows)===1&&$cloudRows[0]['server']->configuration['RamMb']===2048&&(int)$cloudRows[0]['server_id']===1201&&!str_contains(json_encode(all('SELECT * FROM '.table('provider_observation_cloud'))),'CLOUD_PRIVATE_MARKER'));
    pi_ok('Cloud cannot replace package observation or client service data',provider_inventory_observation($id)===$packageBefore&&provider_inventory_client_services(1)===$clientBefore&&all('SELECT * FROM '.table('provider_observation_packages'))===$packagesBefore&&provider_inventory_client_services(2)===[]);
    pi_ok('Package refresh cannot be projected as cloud metadata',provider_inventory_cloud_servers($packageBefore['id'])===[]);
    $scripted=[new TransportResponse(200,[],json_encode([$cloudItem,['id'=>false]]))];
    pi_ok('Mixed cloud response preserves validated entries with partial state',$cloudRefresh()==='partial');pi_cool();
    $partial=provider_inventory_observation($id,InventoryRead::CLOUD_SERVERS_LIST);
    pi_ok('Partial cloud counts identify the skipped row',(int)$partial['skipped']===1&&(int)$partial['package_count']===1&&count(provider_inventory_cloud_servers($partial['id']))===1);
    foreach([new TransportResponse(401,[],'[]'),new TransportResponse(200,[],'{}'),new TransportException('timeout')] as $n=>$response){$scripted=[$response];pi_ok('Cloud failure retains last valid cloud and package observation '.$n,$cloudRefresh()==='unavailable'&&provider_inventory_observation($id,InventoryRead::CLOUD_SERVERS_LIST)===$partial&&provider_inventory_observation($id)===$packageBefore);pi_cool();}
    pi_ok('Cloud history is separated from package history',count(provider_inventory_history($id,1,InventoryRead::CLOUD_SERVERS_LIST))===5&&provider_inventory_latest($id)['id']===$packageBefore['id']);
    $before=pi_count('provider_observation_cloud');$scripted=[new TransportResponse(200,[],json_encode([$cloudItem]))];
    pi_ok('Cloud result audit failure rolls back observations',pi_audit_failure('refresh_finished',fn()=>$cloudRefresh())&&pi_count('provider_observation_cloud')===$before&&provider_inventory_observation($id,InventoryRead::CLOUD_SERVERS_LIST)===$partial);pi_cool();
    $beforeDns=$dnsCalls;$beforeTransport=$transportCalls;$beforeIntent=pi_count('provider_refreshes');
    pi_deny('Unknown reader cannot persist intent or use credentials',fn()=>provider_inventory_refresh($id,6,$password,'pi_resolve','pi_read','cloudservers.delete'));
    pi_deny('Revoked owner refuses: Cloud refresh requires current owner password',fn()=>cwp_test_owner_revoked(fn()=>provider_inventory_refresh($id,6,'wrong','pi_resolve','pi_read',InventoryRead::CLOUD_SERVERS_LIST)));
    pi_ok('Refused cloud actions have no provider or storage effect',$dnsCalls===$beforeDns&&$transportCalls===$beforeTransport&&pi_count('provider_refreshes')===$beforeIntent);
    pi_ok('Mislabeled cloud snapshot is refused before persistence',$cloudRefresh(fn()=>InventorySnapshot::unavailable('transport_unknown',time(),'twenty-i|wrong-account|cloudservers.list'))==='refused'&&pi_count('provider_observation_cloud')===$before);pi_cool();
    $code=$cloudRefresh(function()use($id){query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');return InventorySnapshot::unavailable('transport_unknown',time(),'twenty-i|'.$id.'|cloudservers.list');});
    query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pi_actor();
    pi_ok('Revoked owner cloud result is discarded',$code==='access_changed'&&pi_count('provider_observation_cloud')===$before);pi_cool();
    $tablesBeforeView=all('SHOW TABLES');$settingsBeforeView=all('SELECT * FROM '.table('settings').' ORDER BY name');
    require_once CWP_CODE_ROOT.'/includes/layout/admin.php';
    $renderWorkspace=static function(string $section):string{$saved=$_GET;$_GET=['section'=>$section];ob_start();try{require CWP_CODE_ROOT.'/admin/views/providers.php';return ob_get_contents();}finally{ob_end_clean();$_GET=$saved;}};
    $checksBeforeRender=count($checks);$domainHtml=$renderWorkspace('domains');$cloudHtml=$renderWorkspace('cloud');
    pi_ok('Workspace rendering retains every prior native assertion',count($checks)===$checksBeforeRender);
    pi_ok('Normal controller loads the unprepared search panel without schema-changing GET',str_contains($domainHtml,'Prepare domain search')&&!provider_domain_search_ready()&&all('SHOW TABLES')===$tablesBeforeView&&all('SELECT * FROM '.table('settings').' ORDER BY name')===$settingsBeforeView);
    pi_ok('Owner cloud screen renders sanitized facts and honest coverage',str_contains($cloudHtml,'cloud.synthetic.invalid')&&str_contains($cloudHtml,'Memory (MB)')&&str_contains($cloudHtml,'2048')&&str_contains($cloudHtml,'coverage is unknown')&&!str_contains($cloudHtml,'CLOUD_PRIVATE_MARKER'));
    provider_settings_save($id,6,'Cloud context changed',true,'',$password);$cloudHtml=$renderWorkspace('cloud');
    pi_ok('Cloud screen hides metadata under changed saved settings',str_contains($cloudHtml,'earlier provider settings')&&!str_contains($cloudHtml,'cloud.synthetic.invalid'));
    require __DIR__.'/provider_inventory_cloud_link_cases.php';
    $durable=json_encode([all('SELECT * FROM '.table('provider_refreshes')),all('SELECT * FROM '.table('provider_refresh_results')),all('SELECT * FROM '.table('provider_observation_cloud')),all('SELECT * FROM '.table('provider_observation_packages')),all('SELECT * FROM '.table('provider_package_links')),all('SELECT * FROM '.table('provider_package_link_events')),all('SELECT * FROM '.table('provider_domain_links')),all('SELECT * FROM '.table('provider_domain_link_events')),all('SELECT * FROM '.table('audit'))]);
    pi_ok('No durable row contains the secret or auth material',!str_contains($durable,$secret)&&!str_contains($durable,base64_encode($secret))&&!str_contains($durable,'Bearer')&&!str_contains($durable,'Authorization'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/provider-inventory-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native database refresh/observation/link orchestration with synthetic DNS and transport; no real 20i API or UI acceptance','checks'=>$checks],JSON_PRETTY_PRINT));echo ($ok?'PASS':'FAIL').': '.count($checks).' native provider inventory assertions'.PHP_EOL;exit($ok?0:1);
