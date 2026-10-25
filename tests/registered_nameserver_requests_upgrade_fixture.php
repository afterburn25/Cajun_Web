<?php
/** Installed-code companion. Synthetic provider transports only; never a deployed route. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==4)exit(2);
if(function_exists('posix_geteuid')&&posix_geteuid()===0)exit(2);
$port=getenv('CWP_TEST_PORT');if(!is_string($port)||!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
$repo=realpath(__DIR__.'/..');$root=realpath($argv[1]);$web=realpath($argv[3]);
$expected=$repo.'/test-runtime/registered-nameserver-requests-upgrade/rnru-upgrade/web';
if(!$repo||!$root||!$web||$web!==$expected||$root!==$web.'/subfolder'||$argv[1]!==$root||$argv[3]!==$web)exit(2);
for($path=$root;$path!==$repo;$path=dirname($path))if(is_link($path)||$path===dirname($path))exit(2);
$mode=$argv[2];if(!in_array($mode,['preflight','snapshot','seed','verify','baseline-seed','unprepared','damage','refuse'],true))exit(2);


use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyI\Operations\{RegistrarNameserverChange,TwentyIOperationAdapter};
use CajunWebPro\Foundation\TwentyIRegistrarNameservers\{RegisteredNameserverBinding,RegisteredNameserverObservation,RegisteredNameservers};
const RNRU_PASSWORD='Synthetic client nameserver requests upgrade owner passphrase';
const RNRU_CLIENT_PASSWORD='Synthetic client nameserver requests upgrade client passphrase';
const RNRU_SECRET='SYNTHETIC_CLIENT_NAMESERVER_REQUESTS_UPGRADE_KEY';
const RNRU_LABEL='Client nameserver requests upgrade fixture';
$checks=[];$dns=0;$gets=0;$posts=0;$wire=[];$readBody='';$readStatus=200;$boundaryErrors=[];
function rnru_id(int $id):string{return sprintf('%032x',$id);}
function rnru_ok(string $name,bool $passed):void{global $checks;if(in_array($name,array_column($checks,'name'),true))throw new LogicException('Duplicate named outcome.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function rnru_deny(string $name,callable $call):void{$refused=false;try{$call();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$refused=true;}rnru_ok($name,$refused);}
function rnru_scope(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function rnru_mutex():bool{$key='cwp-staff-'.substr(hash('sha256','cwp_test|rnru_'),0,40);return (int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$key])->fetchColumn()===1;}
function rnru_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
/** Same identity encoding used by the retained published-package comparator. */
function rnru_identity(string $name,array $row):string{
    $keys=array_column(all("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=? AND constraint_name='PRIMARY' ORDER BY ORDINAL_POSITION",['rnru_'.$name]),'COLUMN_NAME');
    if(!$keys)throw new RuntimeException('Missing primary identity.');$identity=[];foreach($keys as $key)$identity[$key]=$row[$key];
    return hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));
}
/** Exact original column and private-byte hashes; no plaintext account data is exported. */
function rnru_snapshot():array{
    $out=['version'=>CWP_VERSION,'tables'=>[],'private_files'=>[]];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ? ORDER BY TABLE_NAME',['rnru_']) as $entry){
        $name=substr($entry['TABLE_NAME'],5);if(!preg_match('/\A[a-z][a-z0-9_]*\z/',$name))throw new RuntimeException('Unexpected fixture table.');
        if($name==='rate_limits')continue;$rows=[];
        foreach(all('SELECT * FROM '.table($name)) as $row){$id=rnru_identity($name,$row);$columns=[];
            foreach($row as $column=>$value){if($name==='users'&&$column==='last_login')continue;$columns[$column]=hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
            ksort($columns);if(isset($rows[$id]))throw new RuntimeException('Duplicate primary identity.');$rows[$id]=$columns;
        }ksort($rows);$out['tables'][$name]=['count'=>count($rows),'rows'=>(object)$rows];
    }
    $private=realpath((string)config()['storage']);
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $file){
        if($file->isLink())throw new RuntimeException('Unexpected private link.');
        $relative=str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($private)+1));
        // Existing updater journals and authentication sessions are separate from business/private-key retention.
        if(preg_match('~^(?:structure-[^/]+|sessions)(?:/|$)~D',$relative))continue;
        if($file->isFile())$out['private_files'][$relative]=['bytes'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getPathname())];
    }ksort($out['private_files']);$out['private_files']=(object)$out['private_files'];return $out;
}
function rnru_digest():string{return hash('sha256',json_encode(rnru_snapshot(),JSON_THROW_ON_ERROR));}
function rnru_owner():void{
    $u=one('SELECT * FROM '.table('users').' WHERE email=?',['rnru-upgrade@example.invalid']);
    if(!$u||!password_verify(RNRU_PASSWORD,$u['password_hash']))throw new RuntimeException('Wrong installed owner.');
    $_SESSION=['uid'=>(int)$u['id'],'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);
}
function rnru_client():int{
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE email=?',['rnru-upgrade-client@example.invalid']);
    if(!$c||!password_verify(RNRU_CLIENT_PASSWORD,$c['password_hash']))throw new RuntimeException('Wrong original client.');
    $_SESSION=['client_id'=>(int)$c['id'],'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);return (int)$c['id'];
}

const RNRU_OLD=['ns1.upgrade.invalid','ns2.upgrade.invalid'];
const RNRU_NEW=['ns3.upgrade.invalid','ns4.upgrade.invalid'];
function rnru_wait(string $profile):void{
    if(db()->inTransaction()||rnru_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');
    $deadline=microtime(true)+32;
    while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
/** The existing read executor releases its intent mutex before DNS. */
function rnru_read_dns():string{
    global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';
}
/** The new write service deliberately retains its mutex through DNS and one send. */
function rnru_write_dns():string{
    global $dns;
    if(db()->inTransaction()||!rnru_mutex())throw new LogicException('Write DNS needs owning mutex without transaction.');
    $row=one('SELECT id FROM '.table('provider_nameserver_change_requests').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Approval was not durable before DNS.');
    $request=registered_nameserver_changes_request($row['id']);
    if($request['grant']['consumed_key']!=='nameservers:'.$row['id']||$request['ledger']!==null
        ||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',
            ['provider.registered_nameserver_changes_request','Registered nameserver change request '.$row['id'].' %'])->fetchColumn()!==1)
        throw new LogicException('Original consumed approval and audit must precede DNS.');
    $dns++;return '8.8.8.8';
}
function rnru_boundary(callable $authorize):void{
    $authorize();if(db()->inTransaction()||!rnru_mutex())throw new LogicException('Wire boundary needs owning mutex without transaction.');
}
function rnru_transports():void {
final class RnruReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readStatus,$readBody;rnru_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null||$request->headers!==['Authorization'=>'Bearer '.base64_encode(RNRU_SECRET),'Accept'=>'application/json'])throw new LogicException('Unexpected synthetic GET.');
        $gets++;return new TransportResponse($readStatus,[],$readBody);
    }
}
final class RnruWriteTransport implements HttpTransport{
    public function __construct(private $authorize,private ProviderOperation $operation){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$wire;rnru_boundary($this->authorize);RegistrarNameserverChange::wire($request);
        if(!in_array($request->url,['https://api.20i.com/domain/14704/nameservers','https://api.20i.com/domain/14705/nameservers'],true))throw new LogicException('Unexpected synthetic POST domain.');
        if(($request->headers['Authorization']??null)!=='Bearer '.base64_encode(RNRU_SECRET))throw new LogicException('Synthetic POST credential mismatch.');
        $expected=RegistrarNameserverChange::request($this->operation);$ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$this->operation->idempotencyKey]);
        if($request->body!==$expected->body||$request->url!==$expected->url||!in_array($this->operation->idempotencyKey,['nameservers:'.rnru_id(42),'nameservers:'.rnru_id(52)],true)||!$ledger||$ledger['status']!=='claimed'||$ledger['operation']!==null||(int)$ledger['attempts']!==0)throw new LogicException('Exact original gateway claim must precede synthetic send.');
        $posts++;$wire[]=['method'=>$request->method,'url'=>$request->url,'body'=>$request->body];
        if(json_decode($request->body,true,16,JSON_THROW_ON_ERROR)!==['ns'=>RNRU_NEW,'old-ns'=>RNRU_OLD])throw new LogicException('Expected exact whole replacement sets.');
        return new TransportResponse(200,[],'{}');
    }
}
}
function rnru_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?RegisteredNameserverBinding $nameserverContext=null):InventorySnapshot|RegisteredNameserverObservation{
    rnru_boundary($authorize);
    if($key!==RNRU_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read credential.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{rnru_boundary($authorize);return $key;});
    if($operation===InventoryRead::DOMAINS_LIST)return InventoryReader::read(new RnruReadTransport($authorize,'https://api.20i.com/domain'),$credential,$operation,null,time());
    if($operation!==RegisteredNameservers::OPERATION||!$nameserverContext)throw new LogicException('Unexpected synthetic read operation.');
    return RegisteredNameservers::read(new RnruReadTransport($authorize,'https://api.20i.com/domain/'.$nameserverContext->data['domain_id'].'/nameservers'),$credential,$nameserverContext,time());
}
function rnru_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    rnru_boundary($authorize);
    if($key!==RNRU_SECRET||$address!=='8.8.8.8'||$operation->name!==RegistrarNameserverChange::OPERATION)throw new LogicException('Wrong synthetic write identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),
        static function()use($authorize,$key):string{rnru_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new RnruWriteTransport($authorize,$operation),new ProviderOperationLedger()))->perform($credential,$operation);
}
function rnru_refresh(array $link,bool $wait=true):array{
    if($wait)rnru_wait($link['profile_id']);$profile=provider_settings_load($link['profile_id']);$source=provider_registrar_latest($link['profile_id']);
    return registered_nameservers_refresh($link['id'],(int)$link['revision'],(int)$profile['revision'],$source['id'],RNRU_PASSWORD,'rnru_read_dns','rnru_read');
}

function rnru_link(int $domain=14701):array{
    $p=one('SELECT id FROM '.table('provider_profiles').' WHERE label=?',[RNRU_LABEL]);
    $l=$p?one('SELECT * FROM '.table('provider_registrar_links').' WHERE profile_id=? AND domain_id=?',[$p['id'],$domain]):null;
    if(!$l)throw new RuntimeException('Original registrar assignment missing.');return $l;
}
function rnru_domains():string{
    $rows=[];foreach(range(14701,14705) as $id)$rows[]=['id'=>$id,'name'=>'upgrade'.$id.'.example'];return json_encode($rows,JSON_THROW_ON_ERROR);
}
function rnru_refresh_domains(string $profile):array{
    global $readBody;rnru_owner();rnru_wait($profile);$readBody=rnru_domains();return provider_inventory_refresh_attempt($profile,1,RNRU_PASSWORD,'rnru_read_dns','rnru_read',InventoryRead::DOMAINS_LIST);
}
function rnru_state(int $id):array{return rnru_scope(fn()=>registered_nameserver_requests_state()['requests'][rnru_id($id)]??throw new RuntimeException('Retained request missing.'));}
function rnru_item(int $id,?int $client=null):array{
    $s=registered_nameserver_requests_screen($client,1,rnru_id($id));if(count($s['items'])!==1)throw new RuntimeException('Exact request item missing.');return $s['items'][0];
}
function rnru_create(int $id,int $domain):array{
    $client=rnru_client();$link=rnru_link($domain);$s=registered_nameserver_requests_screen($client);
    $o=array_values(array_filter($s['options'],fn($o)=>$o['link_id']===$link['id']));if(count($o)!==1)throw new RuntimeException('Expected exact current client option.');
    return registered_nameserver_requests_create(rnru_id($id),$client,$link['id'],$o[0]['review_sha256'],RNRU_NEW,true);
}
function rnru_replay(int $id):array{
    $r=rnru_state($id);$client=rnru_client();$p=$r['request']['payload'];$actor=registered_nameserver_requests_actor($client);
    return registered_nameserver_requests_create(rnru_id($id),$client,$p['resource']['binding']['link_id'],registered_nameserver_requests_context($p['resource'],$actor),$p['desired_nameservers'],true);
}
function rnru_approve(int $id):array{
    rnru_owner();$r=rnru_state($id);
    $review=rnru_scope(function()use($r):string{
        if($r['state']==='dispatched'){$d=$r['events'][array_key_last($r['events'])];$q=registered_nameserver_changes_request($d['payload']['owner_request_id']);return registered_nameserver_requests_approval_hash($r,$q['document']['anchor_id'],$q['binding']->sha256,$q['document']['before_nameservers']);}
        return registered_nameserver_requests_approval($r)['review_sha256'];
    });
    return registered_nameserver_requests_approve(rnru_id($id),rnru_id($id),registered_nameserver_requests_event_sha($r['request']),rnru_id($id+2),$review,true,RNRU_PASSWORD,'rnru_write_dns','rnru_perform');
}
function rnru_history():void{
    global $dns,$gets,$posts;$before=rnru_digest();$io=[$dns,$gets,$posts];rnru_owner();$link=rnru_link();
    $state=rnru_scope(fn()=>registered_nameserver_requests_state());rnru_ok('All five original request stages authenticate',count($state['requests'])===5);
    foreach([10=>'pending',20=>'cancelled',30=>'declined',40=>'dispatched',50=>'dispatched'] as $id=>$expected){
        $r=$state['requests'][rnru_id($id)];rnru_ok('Original state and actual client actor retained '.$id,$r['state']===$expected&&$r['request']['actor']['kind']==='client'&&$r['request']['client_id']===(int)$link['client_id']);
        $client=rnru_client();$item=rnru_item($id,$client);$replayed=rnru_replay($id);
        rnru_ok('Exact original intake replays transitioned history '.$id,$replayed===$item&&$replayed['owner_request_id']===null&&$replayed['dispatch']===null&&$replayed['approval']===null);
    }
    rnru_owner();$owner=provider_settings_owner(RNRU_PASSWORD);$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$link['client_id']]);
    foreach([40=>'observed',50=>'uncertain'] as $id=>$expected){
        $r=$state['requests'][rnru_id($id)];$event=$r['events'][array_key_last($r['events'])];$q=rnru_scope(fn()=>registered_nameserver_changes_request(rnru_id($id+2)));
        rnru_ok('Original client owner origin grant ledger and whole sets correspond '.$id,$q['document']['version']===2&&$q['document']['client_origin']===['id'=>rnru_id($id),'request_sha256'=>registered_nameserver_requests_event_sha($r['request'])]&&$event['payload']['owner_request_sha256']===$q['row']['document_sha256']&&$event['actor']['id']===(int)$owner['id']&&$event['actor']['auth_sha256']===hash('sha256',$owner['auth_version'])&&$r['request']['actor']['auth_sha256']===hash('sha256',$client['auth_version'])&&$q['grant']['consumed_key']==='nameservers:'.rnru_id($id+2)&&$q['ledger']['status']==='uncertain'&&(int)$q['ledger']['attempts']===1&&$q['document']['params']===['domainId'=>14700+intdiv($id,10),'ns'=>RNRU_NEW,'old-ns'=>RNRU_OLD]);
        $replay=rnru_approve($id);rnru_ok('Original owner approval replays without another send '.$id,$replay['state']===$expected&&$replay['owner_request_id']===rnru_id($id+2));
    }
    rnru_owner();$resolved=rnru_scope(fn()=>registered_nameserver_changes_pending($link['profile_id'],14704));$pending=rnru_scope(fn()=>registered_nameserver_changes_pending($link['profile_id'],14705));
    rnru_ok('Independent matching observation releases only its original resource',$resolved===null&&$pending['id']===rnru_id(52));
    $client=rnru_client();$screen=registered_nameserver_requests_screen($client);$items=array_column($screen['items'],null,'id');
    rnru_ok('Client status retains observed and unverified meanings without internal owner authority',count($items)===5&&$items[rnru_id(40)]['state']==='observed'&&$items[rnru_id(40)]['last_check']['nameservers']===RNRU_NEW&&$items[rnru_id(50)]['state']==='uncertain'&&$items[rnru_id(50)]['last_check']===null);
    foreach([10,50] as $id){$p=$state['requests'][rnru_id($id)]['request']['payload'];$actor=registered_nameserver_requests_actor($client);rnru_deny('New nonce cannot bypass pending local or provider resource '.$id,fn()=>registered_nameserver_requests_create(rnru_id(100+$id),$client,$p['resource']['binding']['link_id'],registered_nameserver_requests_context($p['resource'],$actor),RNRU_NEW,true));}
    rnru_owner();$profile=provider_settings_load($link['profile_id']);$key=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));rnru_ok('Original private vault opens the original synthetic provider credential',hash_equals(RNRU_SECRET,$key));unset($key);
    rnru_ok('History and replay preserve every retained cell and private byte with no IO',$before===rnru_digest()&&$io===[$dns,$gets,$posts]);
}
function rnru_refuse():void{
    global $dns,$gets,$posts;$before=rnru_digest();rnru_owner();
    rnru_deny('Lost established head refuses authenticated request screen',fn()=>registered_nameserver_requests_screen());
    rnru_deny('Explicit preparation never reconstructs the lost established head',fn()=>registered_nameserver_requests_prepare(RNRU_PASSWORD));
    rnru_deny('Original owner approval cannot bypass missing client origin',fn()=>rnru_scope(fn()=>registered_nameserver_changes_request(rnru_id(52))));
    rnru_deny('Shared readiness cannot downgrade lost client history to legacy',fn()=>rnru_scope(fn()=>registered_nameserver_changes_ready()));
    rnru_deny('Lost client history cannot release original provider fence',fn()=>rnru_scope(fn()=>registered_nameserver_changes_pending(rnru_link()['profile_id'],14705)));
    rnru_ok('Corruption refusals preserve all remaining rows keys and zero IO',$before===rnru_digest()&&[$dns,$gets,$posts]===[0,0,0]);
}
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
try{
    if($mode==='preflight'){
        // The selected loopback DSN uses the assigned host port, not the server-internal @@port.
        $db=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4',(string)getenv('CWP_TEST_USER'),(string)getenv('CWP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $s=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ?');$s->execute(['rnru_']);rnru_ok('Dedicated installed namespace is empty',(int)$s->fetchColumn()===0);echo json_encode(['passed'=>true,'checks'=>$checks],JSON_THROW_ON_ERROR);exit;
    }
    if(!is_file($root.'/config.php')||is_link($root.'/config.php'))throw new RuntimeException('Missing installed configuration.');
    define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';$GLOBALS['cwp_config']=require $root.'/config.php';
    if(CWP_CODE_ROOT!==$root||config()['db']['name']!=='cwp_test'||config()['db']['prefix']!=='rnru_'||config()['db']['host']!=='127.0.0.1'||(int)config()['db']['port']!==(int)$port)throw new RuntimeException('Unexpected installed identity.');
    $storage=(string)config()['storage'];$private=realpath($storage);
    if(!$private||$private!==$storage||!str_starts_with($private,dirname($web).'/cajunwebpro-private-')||str_starts_with($private,$web.'/'))throw new RuntimeException('Unexpected installed private root.');
    for($path=$private;$path!==$repo;$path=dirname($path))if(is_link($path)||$path===dirname($path))throw new RuntimeException('Unexpected private ancestor.');
    // Old-code rollback remains measurable without loading any candidate-only module.
    if($mode==='snapshot'){echo json_encode(['passed'=>true,'checks'=>[],'snapshot'=>rnru_snapshot()],JSON_THROW_ON_ERROR);exit;}
    require_once CWP_CODE_ROOT.'/app/providers/registrar.php';require_once CWP_CODE_ROOT.'/app/providers/operations.php';require_once CWP_CODE_ROOT.'/app/clients/accounts.php';
    if($mode!=='baseline-seed')require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests.php';rnru_transports();
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/subfolder/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$web;start_session();rnru_owner();provider_settings_owner(RNRU_PASSWORD);
    if($mode==='baseline-seed'){
        rnru_ok('Actual published source has no candidate client request service',!function_exists('registered_nameserver_requests_create')&&!is_file(CWP_CODE_ROOT.'/app/providers/registered_nameserver_requests.php'));
        if(!studio_ready())studio_migrate();provider_settings_migrate(RNRU_PASSWORD);provider_inventory_migrate(RNRU_PASSWORD);provider_operations_migrate(RNRU_PASSWORD);provider_registrar_prepare(RNRU_PASSWORD);
        $client=studio_client_save(0,'','Nameserver upgrade client','rnru-upgrade-client@example.invalid',true);$c=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=?',[$client]);$url=studio_new_invite($client,$c['auth_version']);parse_str((string)parse_url($url,PHP_URL_QUERY),$invite);studio_activate($invite['invite'],RNRU_CLIENT_PASSWORD);
        $profile=provider_settings_save('',0,RNRU_LABEL,true,RNRU_SECRET,RNRU_PASSWORD);$read=rnru_refresh_domains($profile);rnru_ok('Published genuine synthetic registrar inventory completes',$read['state']==='complete');
        foreach(range(14701,14705) as $id)provider_registrar_link($profile,$id,$client,1,$read['id'],RNRU_PASSWORD);
        rnru_ok('Published owner assigns all five real registrar resources',rnru_count('provider_registrar_links')===5);
        $f=fopen($private.'/rnru-retained-sentinel.bin','xb');if(!$f)throw new RuntimeException('Private sentinel already exists.');$bytes="Synthetic retained registrar media\x00\x01";if(fwrite($f,$bytes)!==strlen($bytes))throw new RuntimeException('Sentinel write failed.');fclose($f);chmod($private.'/rnru-retained-sentinel.bin',0600);
    }elseif($mode==='unprepared'){
        $before=rnru_digest();$screen=registered_nameserver_requests_screen();rnru_ok('Target update does not silently prepare local requests',!$screen['ready']&&$screen['items']===[]&&$screen['options']===[]&&$before===rnru_digest());
    }elseif($mode==='seed'){
        rnru_ok('Candidate client request preparation starts absent',!rnru_scope(fn()=>registered_nameserver_requests_ready()));if(!studio_ready())studio_migrate();registered_nameservers_prepare(RNRU_PASSWORD);registered_nameserver_changes_prepare(RNRU_PASSWORD);registered_nameserver_requests_prepare(RNRU_PASSWORD);
        $before=rnru_digest();registered_nameserver_requests_prepare(RNRU_PASSWORD);rnru_ok('Explicit owner setup is idempotent',rnru_scope(fn()=>registered_nameserver_requests_ready())&&$before===rnru_digest());
        $link=rnru_link();$source=rnru_refresh_domains($link['profile_id']);rnru_ok('Actual candidate current registrar refresh completes',$source['state']==='complete');
        rnru_create(10,14701);rnru_create(20,14702);$client=rnru_client();registered_nameserver_requests_change(rnru_id(20),rnru_id(20),rnru_id(21),'cancel','',$client);
        rnru_create(30,14703);rnru_owner();registered_nameserver_requests_change(rnru_id(30),rnru_id(30),rnru_id(31),'decline','Please review the destination with the studio.',null,RNRU_PASSWORD);
        rnru_create(40,14704);rnru_owner();$readBody=json_encode(['result'=>RNRU_OLD],JSON_THROW_ON_ERROR);$anchor=rnru_refresh(rnru_link(14704));rnru_ok('First approval has an actual complete current nameserver GET',$anchor['state']==='complete');$r=rnru_approve(40);rnru_ok('First actual client consent and owner approval sends once as uncertain',$r['state']==='uncertain'&&$posts===1);
        rnru_wait($link['profile_id']);$readBody=json_encode(['result'=>RNRU_NEW],JSON_THROW_ON_ERROR);$checked=registered_nameserver_changes_verify(rnru_id(42),RNRU_PASSWORD,'rnru_read_dns','rnru_read');rnru_ok('Separate complete current readback observes desired set without causal claim',$checked['status']==='observed'&&$checked['last_check']['nameservers']===RNRU_NEW&&$posts===1);
        rnru_create(50,14705);rnru_owner();$readBody=json_encode(['result'=>RNRU_OLD],JSON_THROW_ON_ERROR);$anchor=rnru_refresh(rnru_link(14705));rnru_ok('Second resource has its own actual complete nameserver GET',$anchor['state']==='complete');$r=rnru_approve(50);rnru_ok('Second original owner approval stays uncertain after one separate send',$r['state']==='uncertain'&&$posts===2);rnru_history();
    }elseif($mode==='verify')rnru_history();
    elseif($mode==='damage'){
        rnru_history();rnru_scope(function():void{rnru_ok('Deliberate loss removes exactly the established client history head',query('DELETE FROM '.table('settings').' WHERE name=?',['registered_nameserver_requests_head'])->rowCount()===1);});rnru_refuse();
    }else rnru_refuse();
    $expected=match($mode){'baseline-seed'=>[1,1,0],'seed'=>[6,4,2],default=>[0,0,0]};rnru_ok('Only explicit installed seeding performs synthetic IO',[$dns,$gets,$posts]===$expected&&$boundaryErrors===[]);
    echo json_encode(['passed'=>true,'checks'=>$checks,'synthetic_io'=>['dns'=>$dns,'gets'=>$gets,'posts'=>$posts]],JSON_THROW_ON_ERROR);
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR);exit(1);}
finally{restore_error_handler();}
