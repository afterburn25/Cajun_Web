<?php
/** Actual database/check orchestration with synthetic resolver/transport; no provider API. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/app/providers/checks.php';
use CajunWebPro\Foundation\Provider\{ConnectionCheck,TransportException};
$private=__DIR__.'/../test-runtime/provider-check-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-check-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pcci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic connection owner passphrase';$secret='SYNTHETIC_PRIVATE_GENERAL_KEY';$dnsCalls=0;$transportCalls=0;
function pc_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function pc_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;pc_ok($name,true);return;}pc_ok($name,false);}
function pc_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function pc_resolve():string {global $dnsCalls;$dnsCalls++;if(db()->inTransaction())throw new LogicException('DNS under transaction');return '8.8.8.8';}
function pc_transport(array $row,#[SensitiveParameter] string $key,string $address,callable $authorize):ConnectionCheck {
    global $secret,$transportCalls;$transportCalls++;if(db()->inTransaction()||$key!==$secret||$address!=='8.8.8.8')throw new LogicException('Invalid credential/network boundary');$authorize();return ConnectionCheck::verified('connected');
}
function pc_check(string $id,?callable $resolver=null,?callable $transport=null,int $revision=1):string {global $password;return provider_checks_check($id,$revision,$password,$resolver??'pc_resolve',$transport??'pc_transport');}
function pc_cool():void {query('UPDATE '.table('provider_checks').' SET started_epoch=? WHERE started_epoch>?',[time()-31,time()-30]);}
function pc_count(string $name):int {return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function pc_audit_failure(string $action,callable $fn):bool {
    db()->exec('CREATE TRIGGER pcci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic check audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER pcci_fail');}return $failed;
}
function pc_worker(string $mode,string $id,int $revision=1):array {
    $p=proc_open([PHP_BINARY,__FILE__,$mode,$id,(string)$revision],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Cannot start check worker');foreach([1,2] as $s)stream_set_timeout($pipes[$s],20);return [$p,$pipes];
}
function pc_finish(array $worker):string {[$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err!=='')throw new RuntimeException('Check worker failed: '.$err);return $out;}
if(in_array($argv[1]??'',['pause-dns','edit-worker','exit-dns'],true)) {
    pc_actor();$id=$argv[2];$revision=(int)$argv[3];
    if($argv[1]==='edit-worker'){echo "ready\n";fflush(STDOUT);provider_settings_save($id,$revision,'Concurrent update',true,'',$password);echo 'saved';}
    else echo pc_check($id,function()use($argv):string {echo "resolving\n";fflush(STDOUT);if($argv[1]==='exit-dns')exit;stream_set_timeout(STDIN,15);if(trim(fgets(STDIN))!=='continue')throw new RuntimeException('Fixture synchronization');return pc_resolve();},null,$revision).':'.$transportCalls;
    exit;
}
try {
    foreach(schema_statements('pcci_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Check staff '.$i,'check'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    pc_actor();provider_settings_migrate($password);$id=provider_settings_save('',0,'20i fixture',true,$secret,$password);
    pc_deny('Revoked owner role prevents DNS and check intent with stored state unchanged',fn()=>cwp_test_owner_revoked(fn()=>provider_checks_check($id,1,'','pc_resolve','pc_transport')));
    pc_deny('Stale revision prevents DNS and check intent',fn()=>pc_check($id,null,null,2));
    pc_actor(2);pc_deny('Administrator cannot check provider',fn()=>pc_check($id));pc_deny('Administrator cannot read check history',fn()=>provider_checks_history($id));pc_actor();
    $_GET['support']='fixture';pc_deny('Client support cannot check provider',fn()=>pc_check($id));unset($_GET['support']);
    $_SESSION=['client_id'=>1,'client_auth'=>'synthetic'];pc_deny('Client cannot check provider',fn()=>pc_check($id));pc_actor();
    query('UPDATE '.table('provider_profiles').' SET enabled=0 WHERE id=?',[$id]);pc_deny('Disabled profile cannot check provider',fn()=>pc_check($id));query('UPDATE '.table('provider_profiles').' SET enabled=1 WHERE id=?',[$id]);
    pc_ok('Rejected starts use no network and retain no check rows',$dnsCalls===0&&$transportCalls===0&&pc_count('provider_checks')===0);
    pc_ok('Intent audit failure prevents DNS and durable start',pc_audit_failure('check_started',fn()=>pc_check($id))&&$dnsCalls===0&&$transportCalls===0&&pc_count('provider_checks')===0);
    pc_ok('Accepted check uses current credentials outside DB transactions',pc_check($id)==='connected'&&$dnsCalls===1&&$transportCalls===1);
    $history=provider_checks_history($id);pc_ok('Successful terminal and intent are retained together',count($history)===1&&$history[0]['state']==='verified'&&$history[0]['result_code']==='connected'&&$history[0]['profile_revision']===1&&$history[0]['actor_id']===1&&$history[0]['finished_at']!==null);
    pc_deny('Profile cooldown prevents immediate duplicate',fn()=>pc_check($id));pc_actor(3);pc_deny('Another owner cannot bypass shared profile cooldown',fn()=>pc_check($id));pc_actor();pc_cool();
    $before=$transportCalls;pc_ok('Private resolver result is refused before credentials and transport',pc_check($id,fn()=>'127.0.0.1')==='unavailable'&&$transportCalls===$before);pc_cool();
    pc_ok('Resolver exceptions produce only a finite local result',pc_check($id,fn()=>throw new RuntimeException('SYNTHETIC_PRIVATE_GENERAL_KEY DNS body'))==='unavailable');pc_cool();
    foreach(['connect','tls','unavailable','timeout','protocol','unknown'] as $kind){pc_ok('Finite transport exception mapping '.$kind,pc_check($id,null,fn()=>throw new TransportException($kind))===$kind);pc_cool();}
    foreach([
        'authentication'=>ConnectionCheck::authenticationFailed('authentication'),
        'rate_limited'=>ConnectionCheck::unknown('rate_limited'),
        'unexpected_response'=>ConnectionCheck::unknown('unexpected_response'),
        'response_limit'=>ConnectionCheck::unknown('response_limit'),
    ] as $code=>$result){pc_ok('Allowed state and result pair retained '.$code,pc_check($id,null,fn()=>$result)===$code);pc_cool();}
    pc_ok('Short arbitrary adapter detail cannot enter history',pc_check($id,null,fn()=>ConnectionCheck::verified('SYNTHETIC_PRIVATE_GENERAL_KEY'))==='invalid_result');pc_cool();
    pc_ok('Allowed code with wrong success state is rejected',pc_check($id,null,fn()=>ConnectionCheck::verified('timeout'))==='invalid_result');pc_cool();
    pc_ok('Arbitrary transport return shape is rejected',pc_check($id,null,fn()=>['state'=>'verified','detail'=>'connected'])==='invalid_result');pc_cool();
    pc_ok('Unexpected exception is unknown without raw text',pc_check($id,null,fn()=>throw new RuntimeException('SYNTHETIC_PRIVATE_GENERAL_KEY response body'))==='unknown');pc_cool();
    query('UPDATE '.table('provider_checks').' SET started_epoch=?',[time()-7200]);
    $key=config()['storage'].'/provider-master.key';rename($key,$key.'.held');$before=$transportCalls;pc_ok('Missing private key refuses transport and retains finite result',pc_check($id)==='credential'&&$transportCalls===$before);rename($key.'.held',$key);pc_cool();
    $worker=pc_worker('pause-dns',$id);pc_ok('Worker commits intent before blocking DNS',trim(fgets($worker[1][1]))==='resolving'&&pc_count('provider_checks')>pc_count('provider_check_results'));
    $row=provider_settings_profile();provider_settings_state($id,1,'disable',$password);fwrite($worker[1][0],"continue\n");fflush($worker[1][0]);pc_ok('Profile change during DNS prevents credential transport',pc_finish($worker)==='access_changed:0');provider_settings_state($id,2,'enable',$password);pc_cool();
    $worker=pc_worker('pause-dns',$id,3);pc_ok('Authority race worker reaches DNS',trim(fgets($worker[1][1]))==='resolving');query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');fwrite($worker[1][0],"continue\n");fflush($worker[1][0]);pc_ok('Owner revocation during DNS prevents credential transport',pc_finish($worker)==='access_changed:0');query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pc_actor();pc_cool();
    $code=pc_check($id,null,function($row,$secret,$address,$authorize){query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');return ConnectionCheck::verified('connected');},3);pc_ok('Owner revocation before result discards claimed success',$code==='access_changed');query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pc_actor();pc_cool();
    $code=pc_check($id,null,function($row,$secret,$address,$authorize){query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');throw new RuntimeException('SYNTHETIC_PRIVATE_GENERAL_KEY');},3);pc_ok('Revoked authority overrides a thrown transport outcome',$code==='access_changed');query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=1');pc_actor();pc_cool();
    $before=pc_count('provider_check_results');pc_ok('Terminal audit failure leaves an incomplete retained intent',pc_audit_failure('check_finished',fn()=>pc_check($id,null,null,3))&&pc_count('provider_check_results')===$before);$incomplete=one('SELECT c.id FROM '.table('provider_checks').' c LEFT JOIN '.table('provider_check_results').' r ON r.id=c.id WHERE r.id IS NULL');pc_ok('Incomplete attempt is not invented as failed or verified',$incomplete!==null&&str_contains(provider_checks_message(null),'incomplete'));pc_cool();
    $before=pc_count('provider_checks');$worker=pc_worker('exit-dns',$id,3);pc_ok('Interrupted process exits after committed intent',pc_finish($worker)==="resolving\n"&&pc_count('provider_checks')===$before+1);pc_cool();
    $code=pc_check($id,null,function($row,$secret,$address,$authorize)use($id):ConnectionCheck {
        $worker=pc_worker('edit-worker',$id,3);pc_ok('Concurrent edit reaches shared lock during transport',trim(fgets($worker[1][1]))==='ready');$GLOBALS['provider_edit_worker']=$worker;
        $authorize();pc_ok('Profile remains unchanged throughout locked transport',provider_settings_load($id)['revision']===3);return pc_transport($row,$secret,$address,$authorize);
    },3);pc_ok('Current check completes before serialized edit',$code==='connected'&&pc_finish($GLOBALS['provider_edit_worker'])==='saved'&&provider_settings_profile()['revision']===4);pc_cool();
    $old=all('SELECT * FROM '.table('provider_checks').' WHERE profile_revision=3');pc_ok('Edit preserves historical attempted revisions',count($old)>0&&provider_settings_profile()['revision']===4);
    query('UPDATE '.table('provider_checks').' SET started_epoch=?',[time()-7200]);for($i=0;$i<20;$i++){pc_check($id,null,null,4);pc_cool();}
    $before=$dnsCalls;pc_deny('Twenty hourly checks prevent another network attempt',fn()=>pc_check($id,null,null,4));pc_ok('Hourly refusal uses no DNS or transport',$dnsCalls===$before);
    pc_actor(3);pc_ok('Hourly allowance belongs to current actor',pc_check($id,null,null,4)==='connected');pc_actor();
    $first=provider_checks_history($id);$older=provider_checks_history($id,2);pc_ok('Check pagination retains distinct attempts',count($first)===21&&count($older)>0&&!array_intersect(array_column(array_slice($first,0,20),'id'),array_column($older,'id')));
    $durable=json_encode([all('SELECT * FROM '.table('audit')),all('SELECT * FROM '.table('provider_check_results')),provider_checks_history($id)]);pc_ok('History and audit contain no raw General key body or headers',!str_contains($durable,$secret)&&!str_contains($durable,base64_encode($secret))&&!str_contains($durable,'Authorization')&&!str_contains($durable,'response body'));
    pc_ok('Check success does not grant any operation capability',count(array_filter((new CajunWebPro\Foundation\TwentyI\TwentyIAdapter())->capabilities(),fn($c)=>$c->state!=='unverified'))===0);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/provider-checks-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native database/current-authority orchestration with synthetic DNS and transport; no real 20i API or UI acceptance','checks'=>$checks],JSON_PRETTY_PRINT));echo ($ok?'PASS':'FAIL').': '.count($checks).' native provider check assertions'.PHP_EOL;exit($ok?0:1);
