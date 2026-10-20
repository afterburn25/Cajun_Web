<?php
/** Isolated real SQL client request -> owner admission with closed synthetic transport. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
$worker=count($argv)===2&&$argv[1]==='--worker';
if(count($argv)!==($worker?2:1))exit(2);
$port=getenv('CWP_TEST_PORT')?:'33306';
if(!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/registered_nameserver_requests.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIRegistrarNameservers\{RegisteredNameserverBinding,RegisteredNameserverObservation,RegisteredNameservers};
use CajunWebPro\Foundation\TwentyI\Operations\{RegistrarNameserverChange,TwentyIOperationAdapter};
const RNCR_PASSWORD='Synthetic client nameserver request owner passphrase';
const RNCR_SECRET='SYNTHETIC_CLIENT_NAMESERVER_REQUEST_KEY';
const RNCR_PREFIX='rncr_';
const RNCR_OLD=['ns1.client-request.invalid','ns2.client-request.invalid'];
const RNCR_NEW=['ns3.client-request.invalid','ns4.client-request.invalid'];
$root=realpath(__DIR__.'/..');if(!$root)exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';
$private=$runtime.'/registered-nameserver-requests-private';$report=$qa.'/registered-nameserver-requests-native-results.json';
foreach([$runtime,$qa] as $path){
    if(is_link($path)||(file_exists($path)&&!is_dir($path)))exit(2);
    if(!is_dir($path)&&!mkdir($path,0700))exit(2);
}
if(is_link($private)||($worker?!is_dir($private):file_exists($private))||(!$worker&&(is_link($report)||file_exists($report))))exit(2);
if(!$worker&&!mkdir($private,0700))exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/nameserver-requests','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-client-nameserver-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$port,
    'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>RNCR_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root.'/site';start_session();
$checks=[];$stage='preflight';$dns=0;$gets=0;$posts=0;$wire=[];$workerFailure=null;
$readStatus=200;$readBody=json_encode(['result'=>RNCR_OLD]);$writeMode='unknown';$duringDns=null;$duringSend=null;

function rncr_ok(string $name,bool $passed):void{
    global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);
}
function rncr_deny(string $name,callable $call,?string $message=null):void{
    $caught=null;try{$call();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    rncr_ok($name,$caught!==null&&($message===null||$caught->getMessage()===$message));
}
function rncr_owner(int $id=1):void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function rncr_client(int $id=1):void{
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);
    $_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function rncr_locked(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function rncr_mutex():bool{
    $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.RNCR_PREFIX),0,40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;
}
function rncr_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function rncr_digest():string{
    $rows=[];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(RNCR_PREFIX),RNCR_PREFIX]) as $table){
        $name=$table['TABLE_NAME'];if(!preg_match('/\Arncr_[a-z0-9_]+\z/',$name))throw new LogicException('Unexpected fixture table.');
        $data=all('SELECT * FROM `'.$name.'`');usort($data,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$data;
    }
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
/** Genuine bounded wait, outside locks and transactions. Never changes a stored clock. */
function rncr_wait(string $profile):void{
    if(db()->inTransaction()||rncr_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');
    $deadline=microtime(true)+32;
    while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
/** The existing read executor releases its intent mutex before DNS. */
function rncr_read_dns():string{
    global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';
}
/** The new write service deliberately retains its mutex through DNS and one send. */
function rncr_write_dns():string{
    global $dns,$duringDns;
    if(db()->inTransaction()||!rncr_mutex())throw new LogicException('Write DNS needs owning mutex without transaction.');
    $row=one('SELECT id FROM '.table('provider_nameserver_change_requests').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Approval was not durable before DNS.');
    $request=registered_nameserver_changes_request($row['id']);
    if($request['grant']['consumed_key']!=='nameservers:'.$row['id']||$request['ledger']!==null
        ||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',
            ['provider.registered_nameserver_changes_request','Registered nameserver change request '.$row['id'].' %'])->fetchColumn()!==1)
        throw new LogicException('Original consumed approval and audit must precede DNS.');
    $dns++;if($duringDns){$call=$duringDns;$duringDns=null;$call();}return '8.8.8.8';
}
function rncr_boundary(callable $authorize):void{
    $authorize();if(db()->inTransaction()||!rncr_mutex())throw new LogicException('Wire boundary needs owning mutex without transaction.');
}
final class RncrReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readStatus,$readBody;rncr_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null)throw new LogicException('Unexpected synthetic GET.');
        $gets++;return new TransportResponse($readStatus,[],$readBody);
    }
}
function rncr_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?RegisteredNameserverBinding $nameserverContext=null):InventorySnapshot|RegisteredNameserverObservation{
    rncr_boundary($authorize);
    if($key!==RNCR_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read credential.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{rncr_boundary($authorize);return $key;});
    if($operation===InventoryRead::DOMAINS_LIST)return InventoryReader::read(new RncrReadTransport($authorize,'https://api.20i.com/domain'),$credential,$operation,null,time());
    if($operation!==RegisteredNameservers::OPERATION||!$nameserverContext)throw new LogicException('Unexpected synthetic read operation.');
    return RegisteredNameservers::read(new RncrReadTransport($authorize,'https://api.20i.com/domain/'.$nameserverContext->data['domain_id'].'/nameservers'),$credential,$nameserverContext,time());
}
final class RncrWriteTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$wire,$writeMode,$duringSend;rncr_boundary($this->authorize);RegistrarNameserverChange::wire($request);
        if(!in_array($request->url,['https://api.20i.com/domain/8701/nameservers','https://api.20i.com/domain/8702/nameservers'],true))throw new LogicException('Unexpected synthetic POST domain.');
        if(($request->headers['Authorization']??null)!=='Bearer '.base64_encode(RNCR_SECRET))throw new LogicException('Synthetic POST credential mismatch.');
        $posts++;$wire[]=['method'=>$request->method,'url'=>$request->url,'body'=>$request->body];
        if($duringSend){$call=$duringSend;$duringSend=null;$call();}
        if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
        if($writeMode!=='unknown')throw new LogicException('Unexpected synthetic POST mode.');
        return new TransportResponse(200,[],'{}');
    }
}
function rncr_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    rncr_boundary($authorize);
    if($key!==RNCR_SECRET||$address!=='8.8.8.8'||$operation->name!==RegistrarNameserverChange::OPERATION)throw new LogicException('Wrong synthetic write identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),
        static function()use($authorize,$key):string{rncr_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new RncrWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function rncr_refresh(array $link,bool $wait=true):array{
    if($wait)rncr_wait($link['profile_id']);$profile=provider_settings_load($link['profile_id']);$source=provider_registrar_latest($link['profile_id']);
    return registered_nameservers_refresh($link['id'],(int)$link['revision'],(int)$profile['revision'],$source['id'],RNCR_PASSWORD,'rncr_read_dns','rncr_read');
}
/** Public service projections are used as they are, never as an owner-impersonation shortcut. */
function rncr_screen(?int $client=null,?string $id=null):array{return registered_nameserver_requests_screen($client,1,$id);}
function rncr_item(string $id,?int $client=null):array{
    $page=rncr_screen($client,$id);$matches=array_values(array_filter($page['items'],fn($row)=>$row['id']===$id));
    if(count($matches)!==1)throw new RuntimeException('Expected retained request item.');return $matches[0];
}
function rncr_option(string $linkId,int $client=1):array{
    $matches=array_values(array_filter(rncr_screen($client)['options'],fn($row)=>$row['link_id']===$linkId));
    if(count($matches)!==1)throw new RuntimeException('Expected current client request option.');return $matches[0];
}
function rncr_create(string $id,int $client,array $option,array $desired=RNCR_NEW):mixed{
    return registered_nameserver_requests_create($id,$client,$option['link_id'],$option['review_sha256'],$desired,true);
}
function rncr_approve(string $id,array $item,string $dispatchId):mixed{
    return registered_nameserver_requests_approve($id,$item['latest_event'],$item['request_sha256'],$dispatchId,
        $item['approval']['review_sha256'],true,RNCR_PASSWORD,'rncr_write_dns','rncr_perform');
}
function rncr_seed():array{
    global $readBody;
    rncr_ok('Dedicated client-request namespace is empty',(string)query('SELECT DATABASE()')->fetchColumn()==='cwp_test'
        &&(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(table_name,?)=BINARY ?',[strlen(RNCR_PREFIX),RNCR_PREFIX])->fetchColumn()===0);
    foreach(schema_statements(RNCR_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $key=>$value)save_setting($key,$value);
    foreach([1,2] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Client nameserver staff '.$id,'rncr'.$id.'@example.invalid',password_hash(RNCR_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (2,\'administrator\',1,?)',[json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',
        ['Client nameserver client '.$id,'rncr-client'.$id.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    rncr_owner();provider_settings_migrate(RNCR_PASSWORD);
    $profile=provider_settings_save('',0,'Client nameserver request native fixture',true,RNCR_SECRET,RNCR_PASSWORD);
    provider_inventory_migrate(RNCR_PASSWORD);provider_registrar_prepare(RNCR_PASSWORD);registered_nameservers_prepare(RNCR_PASSWORD);
    provider_operations_migrate(RNCR_PASSWORD);registered_nameserver_changes_prepare(RNCR_PASSWORD);
    $readBody=json_encode([['id'=>8701,'name'=>'one.client-request.invalid'],['id'=>8702,'name'=>'two.client-request.invalid']],JSON_THROW_ON_ERROR);
    $source=provider_inventory_refresh_attempt($profile,1,RNCR_PASSWORD,'rncr_read_dns','rncr_read',InventoryRead::DOMAINS_LIST);
    rncr_ok('Real registrar read establishes both synthetic domain identities',$source['state']==='complete');
    foreach([8701,8702] as $domain)provider_registrar_link($profile,$domain,1,1,$source['id'],RNCR_PASSWORD);
    $links=all('SELECT * FROM '.table('provider_registrar_links').' WHERE profile_id=? ORDER BY domain_id',[$profile]);
    $readBody=json_encode(['result'=>RNCR_OLD]);$receipt=rncr_refresh($links[0]);
    rncr_ok('Real owner read establishes complete old nameserver anchor',$receipt['state']==='complete');
    return ['profile'=>$profile,'source'=>$source,'links'=>$links,'anchor'=>registered_nameservers_read_row($receipt['id'])];
}

/** A late real dispatch audit fault witnesses all three inserted records before rollback. */
function rncr_admission_fault(callable $call):bool{
    $tables=['provider_registrar_ns_requests_events','provider_operation_grants','provider_nameserver_change_requests'];
    $before=array_map('rncr_count',$tables);
    if(one('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND BINARY trigger_name=?',['rncr_admission_fault']))throw new RuntimeException('Existing admission fault fixture.');
    db()->exec('CREATE TRIGGER rncr_admission_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.registered_nameserver_requests_event' THEN SET @rncr_hits=COALESCE(@rncr_hits,0)+1; SET @rncr_events=(SELECT COUNT(*) FROM ".table($tables[0]).'); SET @rncr_grants=(SELECT COUNT(*) FROM '.table($tables[1]).'); SET @rncr_requests=(SELECT COUNT(*) FROM '.table($tables[2])."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic client nameserver dispatch audit fault'; END IF; END");
    query('SET @rncr_hits=0,@rncr_events=0,@rncr_grants=0,@rncr_requests=0');$caught=false;
    try{$call();}catch(PDOException $e){
        $seen=one('SELECT @rncr_hits AS hits,@rncr_events AS events,@rncr_grants AS grants,@rncr_requests AS requests');
        $caught=$e->getCode()==='45000'&&(int)$seen['hits']===1
            &&[(int)$seen['events'],(int)$seen['grants'],(int)$seen['requests']]===array_map(fn($n)=>$n+1,$before);
    }finally{db()->exec('DROP TRIGGER rncr_admission_fault');}
    return $caught;
}
function rncr_launch(array $input):array{
    $process=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('Could not start client-request worker.');
    $json=json_encode($input,JSON_THROW_ON_ERROR);
    if(fwrite($pipes[0],$json)!==strlen($json))throw new RuntimeException('Could not send worker input.');
    fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    return ['process'=>$process,'pipes'=>$pipes,'stdout'=>'','stderr'=>'','connection'=>null];
}
/** Require a real blocked child GET_LOCK while this connection owns the exact mutex. */
function rncr_blocked(array &$worker):void{
    if(!rncr_mutex()||db()->inTransaction())throw new LogicException('Race observer needs owning mutex without transaction.');
    $deadline=microtime(true)+2.5;$parent=(int)query('SELECT CONNECTION_ID()')->fetchColumn();
    do{
        $worker['stdout'].=stream_get_contents($worker['pipes'][1]);$worker['stderr'].=stream_get_contents($worker['pipes'][2]);
        if(strlen($worker['stdout'])>8192||strlen($worker['stderr'])>2048||$worker['stderr']!=='')throw new RuntimeException('Worker readiness output invalid.');
        if($worker['connection']===null&&str_contains($worker['stdout'],"\n")){
            [$line,$worker['stdout']]=explode("\n",$worker['stdout'],2);$ready=json_decode($line,true,4,JSON_THROW_ON_ERROR);
            if(!is_array($ready)||array_keys($ready)!==['ready','connection']||$ready['ready']!==true||!is_int($ready['connection'])||$ready['connection']<1||$ready['connection']===$parent)throw new RuntimeException('Invalid independent worker identity.');
            $worker['connection']=$ready['connection'];
        }
        if($worker['connection']!==null){
            $row=one('SELECT ID,STATE,INFO FROM information_schema.PROCESSLIST WHERE ID=?',[$worker['connection']]);
            if($row&&stripos((string)$row['INFO'],'GET_LOCK')!==false&&stripos((string)$row['STATE'],'lock')!==false&&rncr_mutex())return;
        }
        $status=proc_get_status($worker['process']);if(!$status['running'])throw new RuntimeException('Worker exited before observed lock contention.');
        if(microtime(true)>$deadline)throw new RuntimeException('Actual blocked worker was not observed within lock budget.');usleep(20000);
    }while(true);
}
function rncr_join(array &$worker):array{
    $exit=null;$deadline=microtime(true)+25;
    try{do{
        $worker['stdout'].=stream_get_contents($worker['pipes'][1]);$worker['stderr'].=stream_get_contents($worker['pipes'][2]);
        if(strlen($worker['stdout'])>8192||strlen($worker['stderr'])>2048)throw new RuntimeException('Worker result bound exceeded.');
        $status=proc_get_status($worker['process']);if(!$status['running']){$exit=$status['exitcode'];break;}
        if(microtime(true)>$deadline)throw new RuntimeException('Worker result deadline exceeded.');usleep(20000);
    }while(true);$worker['stdout'].=stream_get_contents($worker['pipes'][1]);$worker['stderr'].=stream_get_contents($worker['pipes'][2]);}
    finally{$status=proc_get_status($worker['process']);if($status['running'])proc_terminate($worker['process']);fclose($worker['pipes'][1]);fclose($worker['pipes'][2]);$closed=proc_close($worker['process']);if($exit===null)$exit=$closed;}
    if($exit!==0||$worker['stderr']!==''||strlen($worker['stdout'])>8192)throw new RuntimeException('Worker failed after real lock contention.');
    $result=json_decode(trim($worker['stdout']),true,5,JSON_THROW_ON_ERROR);
    if(!is_array($result)||array_keys($result)!==['outcome','dns','gets','posts']||!in_array($result['outcome'],['completed','refused'],true))throw new RuntimeException('Invalid worker result.');
    foreach(['dns','gets','posts'] as $key)if(!is_int($result[$key])||$result[$key]<0)throw new RuntimeException('Invalid worker transport counter.');return $result;
}
/** Private stdin carries only synthetic identities/review hashes; no production override. */
function rncr_worker():never{
    global $dns,$gets,$posts;
    try{
        $input=json_decode(stream_get_contents(STDIN,4097),true,6,JSON_THROW_ON_ERROR);
        if(!is_array($input)||array_keys($input)!==['action','id','expected','event','request_sha256','review_sha256']||!in_array($input['action'],['cancel','approve'],true))throw new RuntimeException('Invalid worker command.');
        foreach(['id','expected','event'] as $key)if(!is_string($input[$key])||!preg_match('/\A[a-f0-9]{32}\z/',$input[$key]))throw new RuntimeException('Invalid worker ID.');
        foreach(['request_sha256','review_sha256'] as $key)if(!is_string($input[$key])||!preg_match('/\A[a-f0-9]{64}\z/',$input[$key]))throw new RuntimeException('Invalid worker digest.');
        if($input['action']==='cancel')rncr_client();else rncr_owner();
        echo json_encode(['ready'=>true,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR)."\n";flush();
        $outcome='completed';
        for($attempt=1;;$attempt++)try{
            if($input['action']==='cancel')registered_nameserver_requests_change($input['id'],$input['expected'],$input['event'],'cancel','',1);
            else registered_nameserver_requests_approve($input['id'],$input['expected'],$input['request_sha256'],$input['event'],$input['review_sha256'],true,RNCR_PASSWORD,'rncr_write_dns','rncr_perform');
            break;
        }catch(InvalidArgumentException|RuntimeException $e){
            if(!($e instanceof PDOException)&&$e->getMessage()==='Another account change is in progress. Please retry.'&&$attempt<3)continue;
            $expected=$input['action']==='cancel'?'This nameserver request changed. Reload its review.':'This request was already reviewed or withdrawn.';
            if($e instanceof PDOException||$e->getMessage()!==$expected)throw $e;$outcome='refused';break;
        }
        echo json_encode(['outcome'=>$outcome,'dns'=>$dns,'gets'=>$gets,'posts'=>$posts],JSON_THROW_ON_ERROR)."\n";exit;
    }catch(Throwable $e){echo json_encode(['failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR)."\n";exit(1);}
}

function rncr_pending(array $link):?array{return rncr_locked(fn()=>registered_nameserver_changes_pending($link['profile_id'],(int)$link['domain_id']));}
function rncr_state():array{return rncr_locked(fn()=>registered_nameserver_requests_state());}
function rncr_verify(string $id,string $profile):array{
    rncr_wait($profile);return registered_nameserver_changes_verify($id,RNCR_PASSWORD,'rncr_read_dns','rncr_read');
}
function rncr_damage(string $name,callable $mutate):void{
    global $dns,$gets,$posts;
    $before=rncr_digest();$io=[$dns,$gets,$posts];$session=$_SESSION;
    staff_locked(function()use($name,$mutate):void{
        db()->beginTransaction();try{$mutate();rncr_deny($name,fn()=>registered_nameserver_requests_state());}
        finally{if(db()->inTransaction())db()->rollBack();}
    });
    $_SESSION=$session;unset($GLOBALS['cwp_settings']);
    rncr_ok($name.' restores exact original rows without provider work',rncr_digest()===$before&&[$dns,$gets,$posts]===$io&&is_array(rncr_state()));
}
function rncr_event_count():int{return rncr_count('provider_registrar_ns_requests_events');}
function rncr_event_audits():int{return (int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.registered_nameserver_requests_event'])->fetchColumn();}
function rncr_head():string{return (string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['registered_nameserver_requests_head'])['value']??'');}
function rncr_worker_input(string $action,string $id,string $event,array $item):array{
    return ['action'=>$action,'id'=>$id,'expected'=>$item['latest_event'],'event'=>$event,'request_sha256'=>$item['request_sha256'],'review_sha256'=>$item['approval']['review_sha256']];
}
/** Observe the blocked child before the parent makes the winning public transition. */
function rncr_race(array $input,callable $winner,string $label):array{
    $child=null;
    try{staff_locked(function()use($input,$winner,$label,&$child):void{
        $child=rncr_launch($input);rncr_blocked($child);rncr_ok($label.' observes an independent blocked GET_LOCK',true);$winner();
    });}finally{if($child!==null)$result=rncr_join($child);}
    return $result;
}

if($worker)rncr_worker();
$handle=fopen($report,'xb');if($handle===false)exit(2);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException('Native fixture PHP diagnostic',0,$severity,$file,$line);});
try{
    $stage='setup';$seed=rncr_seed();$profile=$seed['profile'];[$link,$otherLink]=$seed['links'];
    rncr_ok('New client request storage is absent until explicit owner preparation',rncr_screen()['ready']===false);
    rncr_deny('Wrong password cannot prepare client request history',fn()=>registered_nameserver_requests_prepare('incorrect'),'Your current password is not correct.');
    rncr_owner(2);rncr_deny('Permission-rich nonowner cannot prepare client request history',fn()=>registered_nameserver_requests_prepare(RNCR_PASSWORD));rncr_owner();
    $_GET['support']='synthetic';rncr_deny('Support route cannot prepare client request history',fn()=>registered_nameserver_requests_prepare(RNCR_PASSWORD));$_GET=[];
    db()->exec('CREATE TABLE rncr_provider_registrar_ns_requests_unknown (id INT) ENGINE=InnoDB');
    try{rncr_deny('Unknown request namespace is not adopted by setup',fn()=>registered_nameserver_requests_prepare(RNCR_PASSWORD));}
    finally{db()->exec('DROP TABLE rncr_provider_registrar_ns_requests_unknown');}
    db()->exec(registered_nameserver_requests_schema(RNCR_PREFIX));
    rncr_ok('Exact empty unmarked request table remains unprepared',rncr_screen()['ready']===false);
    registered_nameserver_requests_prepare(RNCR_PASSWORD);
    rncr_ok('Explicit setup resumes exact empty schema and establishes an empty authenticated head',rncr_screen()['ready']===true&&rncr_event_count()===0&&str_starts_with(rncr_head(),'0:'));
    $before=rncr_digest();registered_nameserver_requests_prepare(RNCR_PASSWORD);
    rncr_ok('Exact setup retry preserves every original row',rncr_digest()===$before);

    $stage='primary-client';rncr_client();$option=rncr_option($link['id']);$before=rncr_digest();$io=[$dns,$gets,$posts];
    rncr_ok('Real client has no staff identity and sees only assigned domain choices',admin_user()===null&&count(rncr_screen(1)['options'])===2);
    rncr_deny('Client cannot select a different authenticated client ID',fn()=>rncr_screen(2));
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;rncr_deny('Null '.$key.' marker is rejected before primary normalization',fn()=>rncr_screen(1),'Sign in to your primary client account.');unset($_SESSION[$key]);}
    $_GET['support']='synthetic';rncr_deny('Client support query is rejected before request reads',fn()=>rncr_screen(1));$_GET=[];
    $GLOBALS['cwp_support']=['id'=>'synthetic'];rncr_deny('Resolved support context cannot submit a primary request',fn()=>rncr_create(bin2hex(random_bytes(16)),1,$option));unset($GLOBALS['cwp_support']);
    rncr_deny('Client confirmation is explicit',fn()=>registered_nameserver_requests_create(bin2hex(random_bytes(16)),1,$link['id'],$option['review_sha256'],RNCR_NEW,false));
    rncr_deny('Changed local form hash cannot create a request',fn()=>registered_nameserver_requests_create(bin2hex(random_bytes(16)),1,$link['id'],str_repeat('0',64),RNCR_NEW,true));
    rncr_deny('Invalid nameserver values cannot become local history',fn()=>rncr_create(bin2hex(random_bytes(16)),1,$option,['https://not-a-host.invalid']));
    rncr_ok('Primary-session and input denials make no rows or provider intents',rncr_digest()===$before&&[$dns,$gets,$posts]===$io);
    $cancelId=bin2hex(random_bytes(16));$events=rncr_event_count();$audits=rncr_event_audits();$head=rncr_head();$grants=rncr_count('provider_operation_grants');$ledger=rncr_count('provider_operations');
    $created=rncr_create($cancelId,1,$option);
    rncr_ok('Client intake appends exactly one event and audit with a new head',$created['state']==='pending'&&$created['can_cancel']===true&&rncr_event_count()===$events+1&&rncr_event_audits()===$audits+1&&rncr_head()!==$head);
    rncr_ok('Local intake uses no owner approval, ledger, DNS, GET or POST',rncr_count('provider_operation_grants')===$grants&&rncr_count('provider_operations')===$ledger&&[$dns,$gets,$posts]===$io);
    $request=rncr_state()['requests'][$cancelId]['request'];
    rncr_ok('Intake retains actual client actor and exact original signed assignment',registered_nameserver_requests_json($request['actor'])===registered_nameserver_requests_json(['kind'=>'client','id'=>1,'auth_sha256'=>hash('sha256',(string)$_SESSION['client_auth'])])
        &&$request['payload']['resource']['binding']['link_id']===$link['id']&&$request['payload']['resource']['binding']['source_refresh_id']===$seed['source']['id']);
    $before=rncr_digest();rncr_ok('Exact client intake replay returns same pending item',rncr_create($cancelId,1,$option)===$created&&rncr_digest()===$before&&[$dns,$gets,$posts]===$io);
    rncr_deny('Same client request ID cannot change desired values',fn()=>rncr_create($cancelId,1,$option,['ns5.client-request.invalid']));
    rncr_deny('A second pending local request cannot bypass the resource reservation',fn()=>rncr_create(bin2hex(random_bytes(16)),1,$option));
    rncr_client(2);rncr_ok('Another real client sees no foreign requests or choices',rncr_screen(2)['items']===[]&&rncr_screen(2)['options']===[]);
    rncr_deny('Forged request detail remains client scoped',fn()=>rncr_item($cancelId,2));
    rncr_deny('Other client cannot withdraw original request',fn()=>registered_nameserver_requests_change($cancelId,$cancelId,bin2hex(random_bytes(16)),'cancel','',2));

    $stage='withdraw-race';rncr_owner();$review=rncr_item($cancelId);$cancelEvent=bin2hex(random_bytes(16));$losingDispatch=bin2hex(random_bytes(16));
    $race=rncr_race(rncr_worker_input('approve',$cancelId,$losingDispatch,$review),function()use($cancelId,$cancelEvent):void{rncr_client();registered_nameserver_requests_change($cancelId,$cancelId,$cancelEvent,'cancel','',1);},'Withdrawal-wins race');
    rncr_ok('Blocked approval loses to committed client withdrawal without provider work',$race===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&rncr_item($cancelId,1)['state']==='cancelled'&&rncr_count('provider_operation_grants')===$grants&&[$dns,$gets,$posts]===$io);
    $before=rncr_digest();$cancelled=registered_nameserver_requests_change($cancelId,$cancelId,$cancelEvent,'cancel','',1);
    rncr_ok('Withdrawal exact replay and original create replay retain the cancelled state',$cancelled['state']==='cancelled'&&rncr_create($cancelId,1,$option)['state']==='cancelled'&&rncr_digest()===$before);

    $declineId=bin2hex(random_bytes(16));$option=rncr_option($link['id']);rncr_create($declineId,1,$option);rncr_owner();$declineEvent=bin2hex(random_bytes(16));
    $declined=registered_nameserver_requests_change($declineId,$declineId,$declineEvent,'decline','Please confirm your DNS service.',null,RNCR_PASSWORD);
    rncr_ok('Owner decline records a visible note without dispatch',$declined['state']==='declined'&&$declined['note']==='Please confirm your DNS service.'&&rncr_count('provider_operation_grants')===$grants&&[$dns,$gets,$posts]===$io);
    $before=rncr_digest();registered_nameserver_requests_change($declineId,$declineId,$declineEvent,'decline','Please confirm your DNS service.',null,RNCR_PASSWORD);
    rncr_ok('Exact decline retry does not add another event',rncr_digest()===$before);

    $stage='admission-rollback';rncr_client();$mainId=bin2hex(random_bytes(16));$option=rncr_option($link['id']);rncr_create($mainId,1,$option);
    rncr_owner();$review=rncr_item($mainId);$dispatch=bin2hex(random_bytes(16));$before=rncr_digest();$io=[$dns,$gets,$posts];
    rncr_ok('Owner approval displays exact separately observed old set',is_array($review['approval'])&&$review['approval']['old_nameservers']===RNCR_OLD);
    rncr_deny('Approval requires explicit complete-set review',fn()=>registered_nameserver_requests_approve($mainId,$mainId,$review['request_sha256'],$dispatch,$review['approval']['review_sha256'],false,RNCR_PASSWORD,'rncr_write_dns','rncr_perform'));
    rncr_deny('Approval requires current owner password',fn()=>registered_nameserver_requests_approve($mainId,$mainId,$review['request_sha256'],$dispatch,$review['approval']['review_sha256'],true,'incorrect','rncr_write_dns','rncr_perform'),'Your current password is not correct.');
    rncr_owner(2);rncr_deny('Permission-rich staff cannot approve client nameservers',fn()=>rncr_approve($mainId,$review,$dispatch));rncr_owner();
    rncr_ok('Late client-dispatch audit failure witnesses inserted event grant and owner request',rncr_admission_fault(fn()=>rncr_approve($mainId,$review,$dispatch)));
    rncr_ok('Admission fault rolls back the complete graph and performs no DNS or wire work',rncr_digest()===$before&&[$dns,$gets,$posts]===$io&&rncr_item($mainId)['state']==='pending');

    $stage='approval-race';$losingCancel=bin2hex(random_bytes(16));
    $race=rncr_race(rncr_worker_input('cancel',$mainId,$losingCancel,$review),function()use($mainId,$review,$dispatch):void{rncr_owner();rncr_approve($mainId,$review,$dispatch);},'Approval-wins race');
    rncr_ok('Blocked withdrawal loses to one atomic approval and one send',$race===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&$posts===$io[2]+1&&$dns===$io[0]+1&&$gets===$io[1]);
    $ownerRequest=rncr_locked(fn()=>registered_nameserver_changes_request($dispatch));$state=rncr_state();$dispatchEvent=end($state['requests'][$mainId]['events']);
    rncr_ok('Version two owner approval and dispatch mutually bind original client request',$ownerRequest['document']['version']===2
        &&$ownerRequest['document']['client_origin']===['id'=>$mainId,'request_sha256'=>$review['request_sha256']]
        &&$dispatchEvent['id']===$dispatch&&$dispatchEvent['payload']['owner_request_id']===$dispatch
        &&$dispatchEvent['payload']['owner_request_sha256']===$ownerRequest['row']['document_sha256']);
    rncr_ok('Shared gateway has one finished uncertain attempt with exact canonical whole-set POST',$ownerRequest['ledger']['status']==='uncertain'&&(int)$ownerRequest['ledger']['attempts']===1
        &&$ownerRequest['document']['before_nameservers']===RNCR_OLD
        &&$wire===[['method'=>'POST','url'=>'https://api.20i.com/domain/8701/nameservers','body'=>json_encode(['ns'=>RNCR_NEW,'old-ns'=>RNCR_OLD],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]]);
    $before=rncr_digest();$io=[$dns,$gets,$posts];$approved=rncr_approve($mainId,$review,$dispatch);
    rncr_ok('Exact owner approval replay returns original uncertain result without resend',$approved['state']==='uncertain'&&$approved['owner_request_id']===$dispatch&&rncr_digest()===$before&&[$dns,$gets,$posts]===$io);
    rncr_deny('Another dispatch identifier cannot reuse a transitioned client request',fn()=>rncr_approve($mainId,$review,bin2hex(random_bytes(16))));
    rncr_deny('Changed client request hash cannot replay approval',fn()=>registered_nameserver_requests_approve($mainId,$mainId,str_repeat('0',64),$dispatch,$review['approval']['review_sha256'],true,RNCR_PASSWORD,'rncr_write_dns','rncr_perform'));
    rncr_client();$clientItem=rncr_item($mainId,1);
    rncr_ok('Client sees uncertain status without private owner grant or approval details',$clientItem['state']==='uncertain'&&$clientItem['owner_request_id']===null&&$clientItem['approval']===null&&$clientItem['dispatch']===null&&$clientItem['last_check']===null);
    rncr_ok('Original intake replay after dispatch preserves history without provider work',rncr_create($mainId,1,$option)===$clientItem&&rncr_digest()===$before&&[$dns,$gets,$posts]===$io);
    rncr_owner();rncr_ok('Unresolved provider outcome keeps the original domain fenced',rncr_pending($link)['id']===$dispatch&&rncr_pending($otherLink)===null);

    $stage='separate-readback';$readStatus=403;$failed=rncr_verify($dispatch,$profile);
    rncr_client();$failedClient=rncr_item($mainId,1);
    rncr_ok('Fresh failed GET is visible as unavailable without releasing the client request',$failed['last_check']['disposition']==='unavailable'&&$failedClient['state']==='uncertain'&&$failedClient['last_check']['nameservers']===null&&rncr_pending($link)['id']===$dispatch);
    rncr_owner();$readStatus=200;$readBody=json_encode(['result'=>RNCR_OLD]);$different=rncr_verify($dispatch,$profile);
    rncr_client();$differentClient=rncr_item($mainId,1);
    rncr_ok('Fresh different GET remains unresolved and shows its dated observed set',$different['last_check']['disposition']==='different'&&$differentClient['state']==='uncertain'&&$differentClient['last_check']['nameservers']===RNCR_OLD&&rncr_pending($link)['id']===$dispatch);
    rncr_owner();$readBody=json_encode(['result'=>RNCR_NEW]);$matched=rncr_verify($dispatch,$profile);
    rncr_client();$matchedClient=rncr_item($mainId,1);$before=rncr_digest();$readIo=[$dns,$gets,$posts];rncr_screen(1);
    rncr_ok('Only separate matching GET yields observed client status and releases fence',$matched['status']==='observed'&&$matchedClient['state']==='observed'&&$matchedClient['last_check']['nameservers']===RNCR_NEW&&rncr_pending($link)===null&&$posts===$io[2]);
    rncr_ok('Client status refresh neither reads provider nor changes retained history',rncr_digest()===$before&&[$dns,$gets,$posts]===$readIo);

    $stage='origin-integrity';rncr_owner();
    $check=one('SELECT c.* FROM '.table('provider_nameserver_change_checks').' c JOIN '.table('provider_refreshes').' f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1',[$dispatch]);
    $mutations=[
        'local request'=>fn()=>query('DELETE FROM '.table('provider_registrar_ns_requests_events').' WHERE id=?',[$mainId]),
        'local dispatch'=>fn()=>query('DELETE FROM '.table('provider_registrar_ns_requests_events').' WHERE id=?',[$dispatch]),
        'paired dispatch and audit'=>function()use($dispatch):void{query('DELETE FROM '.table('provider_registrar_ns_requests_events').' WHERE id=?',[$dispatch]);query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.registered_nameserver_requests_event','Registered nameserver request event '.$dispatch.' %']);},
        'local canonical document'=>fn()=>query('UPDATE '.table('provider_registrar_ns_requests_events').' SET document=? WHERE id=?',['{}',$mainId]),
        'local head'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['registered_nameserver_requests_head']),
        'local marker'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['registered_nameserver_requests_schema_version']),
        'local setup audit'=>fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=?',['provider.registered_nameserver_requests_prepared']),
        'local event audit'=>fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.registered_nameserver_requests_event','Registered nameserver request event '.$mainId.' %']),
        'owner request'=>function()use($dispatch):void{query('DELETE FROM '.table('provider_nameserver_change_checks').' WHERE request_id=?',[$dispatch]);query('DELETE FROM '.table('provider_nameserver_change_terminals').' WHERE id=?',[$dispatch]);query('DELETE FROM '.table('provider_nameserver_change_requests').' WHERE id=?',[$dispatch]);},
        'owner grant payload'=>fn()=>query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',['{}',$dispatch]),
        'owner terminal'=>fn()=>query('DELETE FROM '.table('provider_nameserver_change_terminals').' WHERE id=?',[$dispatch]),
        'shared ledger'=>fn()=>query('DELETE FROM '.table('provider_operations').' WHERE idempotency_key=?',['nameservers:'.$dispatch]),
        'paired observed check and audit'=>function()use($check):void{query('DELETE FROM '.table('provider_nameserver_change_checks').' WHERE id=?',[$check['id']]);query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.registered_nameserver_changes_check','Registered nameserver change check '.$check['id'].' %']);},
        'independent readback set'=>fn()=>query('DELETE FROM '.table('registered_nameserver_sets').' WHERE id=?',[$check['receipt_id']]),
    ];
    foreach($mutations as $name=>$mutate)rncr_damage('Damaged '.$name.' fails full client-origin history',$mutate);

    $stage='latest-failure-intake';$readStatus=503;rncr_refresh($link);$readStatus=200;
    rncr_client();$failedId=bin2hex(random_bytes(16));$failedOption=rncr_option($link['id']);$beforeIo=[$dns,$gets,$posts];$failedIntake=rncr_create($failedId,1,$failedOption,RNCR_OLD);
    rncr_ok('Newest failed provider read still permits only local assigned-client intake',$failedIntake['state']==='pending'&&[$dns,$gets,$posts]===$beforeIo);
    rncr_owner();$failedReview=rncr_item($failedId);
    rncr_ok('Failed newest read provides no owner approval set',$failedReview['approval']===null);
    rncr_deny('Local request cannot use an older observation for owner dispatch',fn()=>registered_nameserver_requests_approve($failedId,$failedId,$failedReview['request_sha256'],bin2hex(random_bytes(16)),$review['approval']['review_sha256'],true,RNCR_PASSWORD,'rncr_write_dns','rncr_perform'));
    registered_nameserver_requests_change($failedId,$failedId,bin2hex(random_bytes(16)),'decline','Refresh the domain before a new review.',null,RNCR_PASSWORD);
    rncr_ok('Failed-read intake and owner refusal send nothing',[$dns,$gets,$posts]===$beforeIo);

    $stage='current-client-boundary';$readBody=json_encode(['result'=>RNCR_OLD]);rncr_refresh($link);rncr_client();
    $revokeId=bin2hex(random_bytes(16));rncr_create($revokeId,1,rncr_option($link['id']));rncr_owner();$revokeReview=rncr_item($revokeId);$revokeDispatch=bin2hex(random_bytes(16));
    $clientAuth=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=1')['auth_version'];$beforePosts=$posts;
    $duringDns=fn()=>query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);
    try{$revoked=rncr_approve($revokeId,$revokeReview,$revokeDispatch);
        rncr_ok('Client authentication changed during DNS prevents credential and send',$revoked['state']==='not_sent'&&$revoked['current_assignment']===false&&$posts===$beforePosts&&!one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['nameservers:'.$revokeDispatch]));
        rncr_deny('New client authentication cannot replay original consent approval',fn()=>rncr_approve($revokeId,$revokeReview,$revokeDispatch));
    }finally{query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[$clientAuth]);rncr_owner();}
    rncr_ok('Reversible authority probe retains original consent and truthful no-send terminal',rncr_state()['requests'][$revokeId]['request']['actor']['auth_sha256']===hash('sha256',$clientAuth)&&rncr_pending($link)===null);

    $stage='unresolved-fence';$readBody=json_encode(['result'=>RNCR_OLD]);rncr_refresh($otherLink);rncr_client();$fenceId=bin2hex(random_bytes(16));rncr_create($fenceId,1,rncr_option($otherLink['id']));rncr_owner();$fenceReview=rncr_item($fenceId);$fenceDispatch=bin2hex(random_bytes(16));
    $unenteredLedger=static function($profile,$key,$address,$authorize,$operation):OperationResult{rncr_boundary($authorize);throw new RuntimeException('Synthetic performer failure before ledger.');};
    $fenced=registered_nameserver_requests_approve($fenceId,$fenceId,$fenceReview['request_sha256'],$fenceDispatch,$fenceReview['approval']['review_sha256'],true,RNCR_PASSWORD,'rncr_write_dns',$unenteredLedger);
    rncr_ok('Entered performer without ledger stays uncertain and cannot be verified',$fenced['state']==='uncertain'&&$fenced['dispatch']['can_verify']===false&&rncr_pending($otherLink)['id']===$fenceDispatch&&$posts===$beforePosts);
    $before=rncr_digest();$beforeIo=[$dns,$gets,$posts];rncr_approve($fenceId,$fenceReview,$fenceDispatch);
    rncr_ok('Missing-ledger client-origin replay preserves the fence without another attempt',rncr_digest()===$before&&[$dns,$gets,$posts]===$beforeIo);
    rncr_deny('A missing ledger cannot become a client-visible successful GET',fn()=>registered_nameserver_changes_verify($fenceDispatch,RNCR_PASSWORD,'rncr_read_dns','rncr_read'));
    rncr_deny('A missing ledger cannot be locally reconstructed as a send',fn()=>registered_nameserver_changes_recover_finished($fenceDispatch,str_repeat('0',64),RNCR_PASSWORD));
    provider_settings_save($profile,1,'Rotated client nameserver synthetic key',true,'SYNTHETIC_REPLACEMENT_CLIENT_NAMESERVER_KEY',RNCR_PASSWORD);
    rncr_deny('Changed credentials refuse original client approval replay',fn()=>rncr_approve($fenceId,$fenceReview,$fenceDispatch));
    rncr_client();$item=rncr_item($fenceId,1);
    rncr_ok('Changed connection keeps honest historical client status',$item['state']==='uncertain'&&$item['current_assignment']===false&&$item['dispatch']===null&&rncr_pending($otherLink)['id']===$fenceDispatch&&[$dns,$gets,$posts]===$beforeIo);
    rncr_owner();provider_settings_save($profile,2,'Restored synthetic key with a new revision',true,RNCR_SECRET,RNCR_PASSWORD);
    rncr_wait($profile);$readBody=json_encode([['id'=>8701,'name'=>'one.client-request.invalid'],['id'=>8702,'name'=>'two.client-request.invalid']]);
    $newSource=provider_inventory_refresh_attempt($profile,3,RNCR_PASSWORD,'rncr_read_dns','rncr_read',InventoryRead::DOMAINS_LIST);
    rncr_ok('Actual new registrar observation is complete at the replacement profile revision',$newSource['state']==='complete'&&$newSource['id']!==$seed['source']['id']);
    provider_registrar_unlink($otherLink['id'],1,RNCR_PASSWORD);provider_registrar_link($profile,8702,2,3,$newSource['id'],RNCR_PASSWORD);
    $newLink=one('SELECT * FROM '.table('provider_registrar_links').' WHERE profile_id=? AND domain_id=?',[$profile,8702]);
    rncr_client(2);$otherScreen=rncr_screen(2);$newOption=array_values(array_filter($otherScreen['options'],fn($x)=>$x['link_id']===$newLink['id']));
    rncr_ok('Actual reassignment cannot expose original client history or evade global fence',$otherScreen['items']===[]&&$newOption===[]&&rncr_pending($newLink)['id']===$fenceDispatch&&$posts===$beforePosts);
    $resource=rncr_locked(fn()=>registered_nameserver_requests_resource($newLink['id']));
    $context=rncr_locked(fn()=>registered_nameserver_requests_context($resource,registered_nameserver_requests_actor(2)));
    rncr_deny('New client cannot directly submit around original provider resource fence',fn()=>registered_nameserver_requests_create(bin2hex(random_bytes(16)),2,$newLink['id'],$context,RNCR_NEW,true));
    rncr_client();$old=rncr_item($fenceId,1);
    rncr_ok('Original client retains only labelled historical request after reassignment',$old['state']==='uncertain'&&$old['current_assignment']===false&&$old['can_cancel']===false);
    rncr_deny('Original client cannot resubmit old consent after reassignment',fn()=>registered_nameserver_requests_create($fenceId,1,$otherLink['id'],str_repeat('0',64),RNCR_NEW,true));

    $stage='established-storage';rncr_owner();
    db()->exec('RENAME TABLE '.table('provider_registrar_ns_requests_events').' TO rncr_hidden_request_events');
    try{
        rncr_deny('Lost established request table cannot be silently recreated',fn()=>registered_nameserver_requests_prepare(RNCR_PASSWORD));
        rncr_ok('Refused setup leaves the established request table absent',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?',[RNCR_PREFIX.'provider_registrar_ns_requests_events']));
    }finally{db()->exec('RENAME TABLE rncr_hidden_request_events TO '.table('provider_registrar_ns_requests_events'));}
    $retained=json_encode([all('SELECT * FROM '.table('provider_registrar_ns_requests_events')),all('SELECT * FROM '.table('provider_nameserver_change_requests')),all('SELECT * FROM '.table('provider_nameserver_change_terminals')),all('SELECT * FROM '.table('provider_nameserver_change_checks')),all('SELECT * FROM '.table('provider_operations')),all('SELECT * FROM '.table('audit'))],JSON_THROW_ON_ERROR);
    rncr_ok('Retained public history excludes synthetic keys and authorization headers',!str_contains($retained,RNCR_SECRET)&&!str_contains($retained,base64_encode(RNCR_SECRET))&&!str_contains($retained,'Bearer '));
    $final=rncr_state();
    rncr_ok('All six genuine client requests retain distinct local and shared outcomes',count($final['requests'])===6&&$final['requests'][$cancelId]['state']==='cancelled'&&$final['requests'][$declineId]['state']==='declined'&&$final['requests'][$mainId]['state']==='dispatched'&&$final['requests'][$failedId]['state']==='declined'&&$final['requests'][$revokeId]['state']==='dispatched'&&$final['requests'][$fenceId]['state']==='dispatched');
    rncr_ok('Final fixture retains one provider POST and original unresolved resource fence',$posts===1&&rncr_pending($newLink)['id']===$fenceDispatch&&count($wire)===1);
}catch(Throwable $e){
    $checks[]=['name'=>'Unexpected native failure','passed'=>false];
    $failure=['stage'=>$stage,'class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null];
}
$databaseVersion=null;try{$databaseVersion=(string)query('SELECT VERSION()')->fetchColumn();}catch(PDOException){}
$ok=count($checks)>0&&!array_filter($checks,fn($row)=>$row['passed']!==true);
$result=['passed'=>$ok,'scope'=>'Real isolated SQL, primary-client requests, owner admission, gateway and separate GETs with synthetic transport; no provider network, browser or live acceptance','runtime'=>['php'=>PHP_VERSION,'database'=>$databaseVersion],'checks'=>$checks,'failure'=>$failure??null];
$json=json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
if(fwrite($handle,$json)!==strlen($json))throw new RuntimeException('Could not preserve native report.');fclose($handle);
echo ($ok?'PASS':'FAIL').': '.count($checks).' registered nameserver request native assertions'.PHP_EOL;exit($ok?0:1);
