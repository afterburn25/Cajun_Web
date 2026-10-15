<?php
/** Native disposable MySQL/MariaDB with the real shared gateway and synthetic transport. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/mailbox_views.php';
require __DIR__.'/../site/includes/layout/admin.php';
require __DIR__.'/../site/portal/controllers/mailboxes.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyI\Operations\{MailboxCreate,TwentyIOperationAdapter};
$private=__DIR__.'/../test-runtime/mailbox-native-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-mailbox-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pmb_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic mailbox owner passphrase';$secret='SYNTHETIC_MAILBOX_API_KEY';$mailPassword='Synthetic mailbox credential 123!';$dns=0;$sent=0;$mode='success';
function mb_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function mb_deny(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;mb_ok($name,true);return;}mb_ok($name,false);}
function mb_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time(),'csrf'=>bin2hex(random_bytes(32))];}
function mb_client(int $id=1):void {$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time(),'csrf'=>bin2hex(random_bytes(32))];}
function mb_dns():string {global $dns;$dns++;if(db()->inTransaction())throw new LogicException('DNS under transaction');return '8.8.8.8';}
function mb_id():string {return bin2hex(random_bytes(16));}
function mb_count(string $name):int {return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function mb_result(string $id):?array {return one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['mailbox:'.$id]);}
function mb_item(int $id,string $name):array {return ['id'=>$id,'typeRef'=>9,'enabled'=>true,'name'=>$name,'names'=>[$name],'created'=>'2024-01-02T03:04:05Z','packageTypeName'=>'Web','packageLabels'=>[],'stackUsers'=>[],'productSpec'=>new stdClass()];}
final class MbInventoryTransport implements HttpTransport {
    public function send(TransportRequest $request):TransportResponse {
        if($request->method!=='GET'||$request->url!=='https://api.20i.com/package')throw new LogicException('Unexpected inventory request');
        return new TransportResponse(200,[],json_encode([mb_item(101,'one.example'),mb_item(202,'two.example')],JSON_THROW_ON_ERROR));
    }
}
function mb_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation=InventoryRead::PACKAGES_LIST):InventorySnapshot {
    $authorize();return InventoryReader::read(new MbInventoryTransport(),new ProviderCredential('twenty-i',$profile['id'],static function()use($key,$authorize):string{$authorize();return $key;}),$operation,0,time());
}
final class MbTransport implements HttpTransport {
    public function __construct(private Closure $authorize){}
    public function send(TransportRequest $request):TransportResponse {
        global $sent,$mode;$sent++;($this->authorize)();
        if(db()->inTransaction())throw new LogicException('Transport under transaction');
        $wire=MailboxCreate::wire($request);if($wire===null)throw new LogicException('Unexpected mailbox wire body');
        if($mode==='timeout')throw new TransportException('timeout');
        if($mode==='failed')return new TransportResponse(403,[],'{"error":"denied"}');
        if($mode==='revoke'){query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');($this->authorize)();}
        $body=json_decode($request->body,true,16,JSON_THROW_ON_ERROR)['new']['mailbox'];
        $domain=rawurldecode(substr($request->url,strrpos($request->url,'/')+1));
        return new TransportResponse(200,[],json_encode(['result'=>['result'=>[['type'=>'mailbox','id'=>$body['local'],'generatedId'=>null,'password'=>$body['password']]],'name'=>$domain]],JSON_THROW_ON_ERROR));
    }
}
function mb_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $op,callable $resolvePassword):OperationResult {
    global $secret;if($key!==$secret||$address!=='8.8.8.8')throw new LogicException('Invalid credential boundary');
    $identity=$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key);
    $credential=new ProviderCredential('twenty-i',$identity,static function()use($key,$authorize):string{$authorize();return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter($resolvePassword),new MbTransport(Closure::fromCallable($authorize)),new ProviderOperationLedger()))->perform($credential,$op);
}
function mb_run(string $policy,string $local,?string $request=null,?int $clientId=1,?callable $resolver=null):string {
    global $password,$mailPassword;return provider_mailbox_run($policy,$request??mb_id(),$local,$mailPassword,$clientId,$clientId===null?$password:null,$resolver??'mb_dns','mb_perform');
}
function mb_policy(string $link,bool $enabled=true,bool $clients=true,int $limit=20):string {
    global $password;mb_actor();$old=provider_mailbox_policy($link);return provider_mailbox_configure($link,$old['id']??'',$enabled,$clients,$limit,24,true,$password);
}
function mb_audit_fail(string $action,callable $work):bool {
    db()->exec('CREATE TRIGGER pmb_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic mailbox audit failure'; END IF; END");
    try{$work();return false;}catch(PDOException){return true;}finally{db()->exec('DROP TRIGGER pmb_fail');}
}
function mb_render(bool $admin=false):string {
    $mailboxAdmin=$admin;$mailboxOptions=provider_mailbox_options($admin?null:1);$mailboxHistory=provider_mailbox_history($admin?null:1);$mailboxUnavailable=false;
    ob_start();require __DIR__.'/../site/portal/views/mailboxes.php';return ob_get_clean();
}
if(($argv[1]??'')==='worker'){
    mb_client();echo "ready\n";fflush(STDOUT);stream_set_timeout(STDIN,20);if(trim((string)fgets(STDIN))!=='start')exit(2);
    try{mb_run($argv[2],'concurrent',$argv[3]);echo json_encode(['sent'=>$sent,'state'=>'recorded']);}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo json_encode(['sent'=>$sent,'state'=>'refused']);}exit;
}
try {
    foreach(schema_statements('pmb_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $k=>$v)save_setting($k,$v);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Mailbox owner '.$i,'mailbox'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),mb_id(),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Mailbox client '.$id,'client'.$id.'@example.invalid',mb_id(),gmdate('Y-m-d H:i:s')]);
    mb_actor();provider_settings_migrate($password);$profileId=provider_settings_save('',0,'Mailbox native',true,$secret,$password);provider_operations_migrate($password);provider_inventory_migrate($password);
    mb_ok('Fresh mailbox GET creates no storage',provider_mailbox_ready()===false&&!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['pmb_provider_mailbox_requests']));
    mb_deny('Preparation requires current owner password',fn()=>provider_mailbox_prepare('wrong'));
    mb_actor(2);mb_deny('Administrator cannot prepare mailbox records',fn()=>provider_mailbox_prepare($password));mb_actor();
    mb_ok('Interrupted preparation rolls back marker and audit',mb_audit_fail('mailbox_prepared',fn()=>provider_mailbox_prepare($password))&&!one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_mailbox_schema_version']));
    provider_mailbox_prepare($password);mb_ok('Empty interrupted preparation resumes with exact schema',provider_mailbox_ready());
    provider_mailbox_prepare($password);mb_ok('Repeated preparation preserves empty history',mb_count('provider_mailbox_requests')===0&&mb_count('provider_mailbox_policies')===0);
    db()->exec('ALTER TABLE '.table('provider_mailbox_policies').' ADD COLUMN unexpected INT NULL');mb_deny('Incompatible extra column blocks preparation',fn()=>provider_mailbox_prepare($password));db()->exec('ALTER TABLE '.table('provider_mailbox_policies').' DROP COLUMN unexpected');
    mb_ok('Real provider reader supplies current package observation',provider_inventory_refresh($profileId,1,$password,'mb_dns','mb_read')==='complete');
    provider_inventory_domain_link($profileId,101,'one.example',1,1,$password);provider_inventory_domain_link($profileId,202,'two.example',2,1,$password);
    $links=provider_inventory_domain_links($profileId);$link1=array_values(array_filter($links,fn($r)=>$r['domain']==='one.example'))[0]['id'];$link2=array_values(array_filter($links,fn($r)=>$r['domain']==='two.example'))[0]['id'];
    mb_deny('Approval requires review of provider capacity',fn()=>provider_mailbox_configure($link1,'',true,true,20,24,false,$password));
    foreach([[0,24],[21,24],[5,0],[5,721]] as [$limit,$hours])mb_deny('Approval bounds '.$limit.'/'.$hours,fn()=>provider_mailbox_configure($link1,'',true,true,$limit,$hours,true,$password));
    mb_ok('Policy audit failure is atomic',mb_audit_fail('mailbox_policy',fn()=>mb_policy($link1))&&provider_mailbox_policy($link1)===null);
    $policy1=mb_policy($link1);$policy2=mb_policy($link2);
    mb_deny('Stale policy form cannot replace permission',fn()=>provider_mailbox_configure($link1,'',true,true,20,24,true,$password));
    mb_client();mb_ok('Client options contain only its approved domain',provider_mailbox_options(1)===[['id'=>$policy1,'domain'=>'one.example']]);
    mb_deny('Client cannot request another client domain',fn()=>mb_run($policy2,'foreign'));
    mb_deny('Forged client ID cannot inspect another history',fn()=>provider_mailbox_history(2));
    $_GET['support']='fixture';mb_deny('Support cannot read mailbox history',fn()=>provider_mailbox_history(1));mb_deny('Support cannot submit mailbox creation',fn()=>mb_run($policy1,'support'));unset($_GET['support']);
    mb_actor(2);mb_deny('Administrator cannot create mailboxes',fn()=>mb_run($policy1,'admin',null,null));mb_client();
    $before=$dns;foreach(['UPPER','a..b','a@b','a/b','<script>'] as $local)mb_deny('Invalid local refused '.$local,fn()=>mb_run($policy1,$local));
    mb_deny('Short password refused',fn()=>provider_mailbox_run($policy1,mb_id(),'short','x',1,null,'mb_dns','mb_perform'));
    mb_ok('Invalid requests never reach DNS or ledger',$dns===$before&&$sent===0&&mb_count('provider_operations')===0);
    $before=mb_count('provider_operation_grants');mb_ok('Request and consumed grant roll back on audit failure',mb_audit_fail('mailbox_started',fn()=>mb_run($policy1,'audit'))&&mb_count('provider_mailbox_requests')===0&&mb_count('provider_operation_grants')===$before);
    $request=mb_run($policy1,'hello');$result=mb_result($request);
    mb_ok('Exact provider success retained in shared native ledger',$result['status']==='succeeded'&&$result['external_id']==='hello'&&(int)$result['attempts']===1&&$sent===1);
    $history=provider_mailbox_history(1);mb_ok('Client receives a safe address and result',count($history)===1&&$history[0]['address']==='hello@one.example'&&$history[0]['status']==='succeeded'&&!isset($history[0]['password_proof']));
    $before=[$dns,$sent];mb_run($policy1,'hello',$request);mb_ok('Same request replay neither resolves nor sends',[$dns,$sent]===$before);
    mb_deny('Nonce cannot change mailbox name',fn()=>mb_run($policy1,'other',$request));
    mb_deny('Nonce cannot change password',fn()=>provider_mailbox_run($policy1,$request,'hello',$mailPassword.'changed',1,null,'mb_dns','mb_perform'));
    mb_deny('New nonce cannot recreate a confirmed address',fn()=>mb_run($policy1,'hello'));
    mb_client(2);mb_ok('Second client cannot view first client requests',provider_mailbox_history(2)===[]);mb_client();
    $mode='timeout';$unknown=mb_run($policy1,'unknown');mb_ok('Timeout remains uncertain',mb_result($unknown)['status']==='uncertain');mb_deny('Uncertain address cannot be recreated',fn()=>mb_run($policy1,'unknown'));$mode='success';
    $unfinished=mb_id();provider_mailbox_claim($policy1,$unfinished,'unfinished',$mailPassword,1,null);$before=[$dns,$sent];mb_run($policy1,'unfinished',$unfinished);mb_ok('Committed request without result is inert on replay',mb_result($unfinished)===null&&[$dns,$sent]===$before);mb_deny('Unfinished address blocks a new nonce',fn()=>mb_run($policy1,'unfinished'));
    $mode='failed';$failed=mb_run($policy1,'retry');mb_ok('Definite provider rejection retained',mb_result($failed)['status']==='failed');$mode='success';$retry=mb_run($policy1,'retry');mb_ok('New explicit request can follow definite failure',mb_result($retry)['status']==='succeeded'&&$retry!==$failed);
    $before=$sent;$noDns=mb_run($policy1,'dns',null,1,fn()=>'127.0.0.1');mb_ok('Private DNS fails safely before transport',mb_result($noDns)['status']==='failed'&&(int)mb_result($noDns)['attempts']===0&&$sent===$before);
    db()->beginTransaction();try{mb_deny('Caller transaction refuses creation',fn()=>mb_run($policy1,'transaction'));mb_ok('Caller transaction remains intact',db()->inTransaction());}finally{db()->rollBack();}
    $snapshot=one('SELECT * FROM '.table('provider_refreshes').' WHERE profile_id=? ORDER BY seq DESC LIMIT 1',[$profileId]);query('UPDATE '.table('provider_refreshes').' SET started_epoch=? WHERE id=?',[time()-901,$snapshot['id']]);
    mb_deny('Stale inventory refuses a new request',fn()=>mb_run($policy1,'stale'));query('UPDATE '.table('provider_refreshes').' SET started_epoch=? WHERE id=?',[$snapshot['started_epoch'],$snapshot['id']]);
    query('UPDATE '.table('provider_refresh_results').' SET state=? WHERE id=?',['partial',$snapshot['id']]);mb_deny('Partial latest inventory refuses creation',fn()=>mb_run($policy1,'partial'));query('UPDATE '.table('provider_refresh_results').' SET state=? WHERE id=?',['complete',$snapshot['id']]);
    query('UPDATE '.table('provider_observation_packages').' SET enabled=0 WHERE refresh_id=? AND package_id=?',[$snapshot['id'],101]);mb_deny('Disabled package refuses creation',fn()=>mb_run($policy1,'disabled'));query('UPDATE '.table('provider_observation_packages').' SET enabled=1 WHERE refresh_id=? AND package_id=?',[$snapshot['id'],101]);
    $version=one('SELECT auth_version FROM '.table('users').' WHERE id=1')['auth_version'];query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[mb_id()]);mb_deny('Changed approving owner revokes client permission',fn()=>mb_run($policy1,'old-owner'));query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$version]);
    $before=$sent;$revoked=mb_run($policy1,'dns-revoke',null,1,function():string{query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');return '8.8.8.8';});mb_ok('Client revoked during DNS is refused before send',mb_result($revoked)['status']==='failed'&&$sent===$before);query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');mb_client();
    $mode='revoke';$revoked=mb_run($policy1,'send-revoke');mb_ok('Authority loss during transport remains uncertain',mb_result($revoked)['status']==='uncertain');query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');mb_client();$mode='success';
    $policy1=mb_policy($link1,true,false);mb_client();mb_deny('Owner-only permission blocks client send',fn()=>mb_run($policy1,'client-disabled'));mb_actor();$ownerRequest=mb_run($policy1,'owner',null,null);mb_ok('Owner can use owner-only creation policy',mb_result($ownerRequest)['status']==='succeeded');
    $policy1=mb_policy($link1,true,true,1);mb_client();mb_deny('Shared allowance counts earlier policy revisions and unfinished requests',fn()=>mb_run($policy1,'quota'));
    $policy1=mb_policy($link1);mb_client();
    $workers=[];$nonce=mb_id();for($i=0;$i<2;$i++){$process=proc_open([PHP_BINARY,__FILE__,'worker',$policy1,$nonce],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Worker start failed');stream_set_timeout($pipes[1],30);if(trim((string)fgets($pipes[1]))!=='ready')throw new RuntimeException('Worker handshake failed');$workers[]=[$process,$pipes];}
    foreach($workers as [$p,$pipes]){fwrite($pipes[0],"start\n");fflush($pipes[0]);}
    $out=[];foreach($workers as [$p,$pipes]){fclose($pipes[0]);$text=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err!=='')throw new RuntimeException('Worker failed: '.$err);$out[]=json_decode($text,true,8,JSON_THROW_ON_ERROR);}
    mb_ok('Concurrent same-nonce processes send exactly once',array_sum(array_column($out,'sent'))===1&&mb_result($nonce)['status']==='succeeded');
    $_POST=['mailbox_local'=>'<script>alert(1)</script>','mailbox_password'=>$mailPassword,'mailbox_password_confirm'=>$mailPassword];$html=mb_render();mb_ok('Rendered client form escapes name and never redisplays passwords',str_contains($html,'&lt;script&gt;')&&!str_contains($html,$mailPassword)&&str_contains($html,'name="csrf"')&&str_contains($html,'client_mailbox_create'));
    mb_ok('Client form does not expose foreign domain or owner credentials',!str_contains($html,'two.example')&&!str_contains($html,'current_password')&&str_contains($html,'not a complete list'));$_POST=[];
    mb_actor();$html=mb_render(true);mb_ok('Rendered owner creation form requires current password',str_contains($html,'current_password')&&str_contains($html,'provider_inventory_mailbox_create'));
    $record=provider_settings_load($profileId);ob_start();require __DIR__.'/../site/admin/views/provider_mailboxes.php';$html=ob_get_clean();mb_ok('Native owner view exposes per-domain policy and truthful quota guidance',str_contains($html,'Maximum requests in 24 hours')&&str_contains($html,'does not set mailbox storage')&&!str_contains($html,$mailPassword));
    foreach(['provider_mailbox_requests','provider_mailbox_policies','provider_operation_grants','provider_operations','audit'] as $table){$json=json_encode(all('SELECT * FROM '.table($table)));mb_ok('No raw mailbox or API password in '.$table,!str_contains($json,$mailPassword)&&!str_contains($json,$secret));}
    save_setting('provider_mailbox_schema_version','');mb_deny('Lost mailbox marker cannot reset history',fn()=>provider_mailbox_prepare($password));save_setting('provider_mailbox_schema_version','1');
    foreach(['provider_mailbox_requests','provider_mailbox_policies'] as $table){db()->exec('RENAME TABLE '.table($table).' TO '.table($table.'_saved'));try{mb_deny('Missing '.$table.' refuses readiness',fn()=>provider_mailbox_ready());mb_deny('Missing '.$table.' cannot be recreated',fn()=>provider_mailbox_prepare($password));}finally{db()->exec('RENAME TABLE '.table($table.'_saved').' TO '.table($table));}}
    $original=one('SELECT * FROM '.table('provider_mailbox_requests').' WHERE id=?',[$unfinished]);query('DELETE FROM '.table('provider_mailbox_requests').' WHERE id=?',[$unfinished]);mb_deny('Missing original request row does not reopen creation allowance',fn()=>provider_mailbox_ready());query('INSERT INTO '.table('provider_mailbox_requests').' (id,policy_id,profile_id,client_id,created_epoch,domain_name,local_name,document,sha256) VALUES (?,?,?,?,?,?,?,?,?)',array_values($original));
    mb_ok('Original mailbox history restores without replay',provider_mailbox_ready());
    provider_settings_save($profileId,1,'Changed connection',true,'',$password);mb_client();mb_deny('Changed provider profile invalidates prior creation policy',fn()=>mb_run($policy1,'new-profile'));mb_ok('Old request history remains visible without active creation',provider_mailbox_options(1)===[]&&provider_mailbox_history(1)!==[]);
    $report=['passed'=>true,'checks'=>$checks,'scope'=>'Real disposable MySQL/MariaDB, application authorization/persistence, shared gateway/ledger, synthetic provider transport and actual PHP forms. No real API or browser acceptance.'];
    file_put_contents(__DIR__.'/../qa/provider-mailbox-native-results.json',json_encode($report,JSON_PRETTY_PRINT)."\n");echo json_encode($report,JSON_PRETTY_PRINT),"\n";
}catch(Throwable $e){$report=['passed'=>false,'checks'=>$checks,'error'=>$e->getMessage(),'line'=>$e->getLine()];file_put_contents(__DIR__.'/../qa/provider-mailbox-native-results.json',json_encode($report,JSON_PRETTY_PRINT)."\n");echo json_encode($report,JSON_PRETTY_PRINT),"\n";exit(1);}
