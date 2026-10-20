<?php
/** Real isolated owner/SQL/ledger lifecycle; synthetic transport only, never live provider access. */
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
require __DIR__.'/../site/app/providers/registered_nameserver_changes.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIRegistrarNameservers\{RegisteredNameserverBinding,RegisteredNameserverObservation,RegisteredNameservers};
use CajunWebPro\Foundation\TwentyI\Operations\{RegistrarNameserverChange,TwentyIOperationAdapter};

const RNC_PASSWORD='Synthetic nameserver change owner passphrase';
const RNC_SECRET='SYNTHETIC_REGISTERED_NAMESERVER_CHANGE_KEY';
const RNC_PREFIX='rnsc_';
const RNC_OLD=['ns1.synthetic.invalid','ns2.synthetic.invalid'];
const RNC_NEW=['ns3.synthetic.invalid','ns4.synthetic.invalid'];
$root=realpath(__DIR__.'/..');if(!$root)exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';
$private=$runtime.'/registered-nameserver-changes-private';$report=$qa.'/registered-nameserver-changes-native-results.json';
foreach([$runtime,$qa] as $path){
    if(is_link($path)||(file_exists($path)&&!is_dir($path)))exit(2);
    if(!is_dir($path)&&!mkdir($path,0700))exit(2);
}
if(is_link($private)||($worker?!is_dir($private):file_exists($private))||(!$worker&&(is_link($report)||file_exists($report))))exit(2);
if(!$worker&&!mkdir($private,0700))exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/nameserver-changes','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-nameserver-change-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$port,
    'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>RNC_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root.'/site';start_session();
$checks=[];$stage='preflight';$dns=0;$gets=0;$posts=0;$wire=[];$workerFailure=null;
$readStatus=200;$readBody=json_encode(['result'=>RNC_OLD]);$writeMode='unknown';$duringDns=null;$duringSend=null;

function rnc_ok(string $name,bool $passed):void{
    global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);
}
function rnc_deny(string $name,callable $call,?string $message=null):void{
    $caught=null;try{$call();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    rnc_ok($name,$caught!==null&&($message===null||$caught->getMessage()===$message));
}
function rnc_owner(int $id=1):void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function rnc_client():void{
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');
    $_SESSION=['client_id'=>1,'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function rnc_locked(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function rnc_mutex():bool{
    $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.RNC_PREFIX),0,40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;
}
function rnc_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function rnc_digest():string{
    $rows=[];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(RNC_PREFIX),RNC_PREFIX]) as $table){
        $name=$table['TABLE_NAME'];if(!preg_match('/\Arnsc_[a-z0-9_]+\z/',$name))throw new LogicException('Unexpected fixture table.');
        $data=all('SELECT * FROM `'.$name.'`');usort($data,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$data;
    }
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
/** Genuine bounded wait, outside locks and transactions. Never changes a stored clock. */
function rnc_wait(string $profile):void{
    if(db()->inTransaction()||rnc_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');
    $deadline=microtime(true)+32;
    while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
/** The existing read executor releases its intent mutex before DNS. */
function rnc_read_dns():string{
    global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';
}
/** The new write service deliberately retains its mutex through DNS and one send. */
function rnc_write_dns():string{
    global $dns,$duringDns;
    if(db()->inTransaction()||!rnc_mutex())throw new LogicException('Write DNS needs owning mutex without transaction.');
    $row=one('SELECT id FROM '.table('provider_nameserver_change_requests').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Approval was not durable before DNS.');
    $request=registered_nameserver_changes_request($row['id']);
    if($request['grant']['consumed_key']!=='nameservers:'.$row['id']||$request['ledger']!==null
        ||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',
            ['provider.registered_nameserver_changes_request','Registered nameserver change request '.$row['id'].' %'])->fetchColumn()!==1)
        throw new LogicException('Original consumed approval and audit must precede DNS.');
    $dns++;if($duringDns){$call=$duringDns;$duringDns=null;$call();}return '8.8.8.8';
}
function rnc_boundary(callable $authorize):void{
    $authorize();if(db()->inTransaction()||!rnc_mutex())throw new LogicException('Wire boundary needs owning mutex without transaction.');
}
final class RncReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readStatus,$readBody;rnc_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null)throw new LogicException('Unexpected synthetic GET.');
        $gets++;return new TransportResponse($readStatus,[],$readBody);
    }
}
function rnc_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?RegisteredNameserverBinding $nameserverContext=null):InventorySnapshot|RegisteredNameserverObservation{
    rnc_boundary($authorize);
    if($key!==RNC_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read credential.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{rnc_boundary($authorize);return $key;});
    if($operation===InventoryRead::DOMAINS_LIST)return InventoryReader::read(new RncReadTransport($authorize,'https://api.20i.com/domain'),$credential,$operation,null,time());
    if($operation!==RegisteredNameservers::OPERATION||!$nameserverContext)throw new LogicException('Unexpected synthetic read operation.');
    return RegisteredNameservers::read(new RncReadTransport($authorize,'https://api.20i.com/domain/'.$nameserverContext->data['domain_id'].'/nameservers'),$credential,$nameserverContext,time());
}
final class RncWriteTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$wire,$writeMode,$duringSend;rnc_boundary($this->authorize);RegistrarNameserverChange::wire($request);
        if(!in_array($request->url,['https://api.20i.com/domain/701/nameservers','https://api.20i.com/domain/702/nameservers'],true))throw new LogicException('Unexpected synthetic POST domain.');
        if(($request->headers['Authorization']??null)!=='Bearer '.base64_encode(RNC_SECRET))throw new LogicException('Synthetic POST credential mismatch.');
        $posts++;$wire[]=['method'=>$request->method,'url'=>$request->url,'body'=>$request->body];
        if($duringSend){$call=$duringSend;$duringSend=null;$call();}
        if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
        if($writeMode!=='unknown')throw new LogicException('Unexpected synthetic POST mode.');
        return new TransportResponse(200,[],'{}');
    }
}
function rnc_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    rnc_boundary($authorize);
    if($key!==RNC_SECRET||$address!=='8.8.8.8'||$operation->name!==RegistrarNameserverChange::OPERATION)throw new LogicException('Wrong synthetic write identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),
        static function()use($authorize,$key):string{rnc_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new RncWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function rnc_refresh(array $link,bool $wait=true):array{
    if($wait)rnc_wait($link['profile_id']);$profile=provider_settings_load($link['profile_id']);$source=provider_registrar_latest($link['profile_id']);
    return registered_nameservers_refresh($link['id'],(int)$link['revision'],(int)$profile['revision'],$source['id'],RNC_PASSWORD,'rnc_read_dns','rnc_read');
}
function rnc_change(string $id,array $link,array $anchor,array $desired=RNC_NEW):array{
    return registered_nameserver_changes_change($id,$link['id'],$anchor['id'],$anchor['binding']->sha256,$desired,true,RNC_PASSWORD,'rnc_write_dns','rnc_perform');
}
function rnc_anchor(array $link):array{
    $receipt=rnc_refresh($link);if($receipt['state']!=='complete')throw new LogicException('Synthetic anchor did not complete.');
    return registered_nameservers_read_row($receipt['id']);
}
function rnc_verify(string $request,array $link):array{
    rnc_wait($link['profile_id']);return registered_nameserver_changes_verify($request,RNC_PASSWORD,'rnc_read_dns','rnc_read');
}
function rnc_summary(string $id):array{return rnc_locked(fn()=>registered_nameserver_changes_summary($id));}
function rnc_ready():bool{return rnc_locked(fn()=>registered_nameserver_changes_ready());}
function rnc_pending(array $link):?array{return rnc_locked(fn()=>registered_nameserver_changes_pending($link['profile_id'],(int)$link['domain_id']));}
function rnc_damage(string $name,callable $mutate):void{
    $before=rnc_digest();$session=$_SESSION;
    staff_locked(function()use($name,$mutate):void{db()->beginTransaction();try{$mutate();rnc_deny($name,fn()=>registered_nameserver_changes_ready());}finally{if(db()->inTransaction())db()->rollBack();}});
    $_SESSION=$session;unset($GLOBALS['cwp_settings']);rnc_ok($name.' restores exact retained bytes',rnc_digest()===$before&&rnc_ready());
}
function rnc_launch(array $input):array{
    $process=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('Could not start isolated owner worker.');
    $json=json_encode($input,JSON_THROW_ON_ERROR);if(fwrite($pipes[0],$json)!==strlen($json))throw new RuntimeException('Could not send worker input.');
    fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);return [$process,$pipes];
}
function rnc_join(array $worker):array{
    global $workerFailure;
    [$process,$pipes]=$worker;$out='';$err='';$exit=null;$deadline=microtime(true)+90;
    try{do{
        $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);
        if(strlen($out)>4096||strlen($err)>4096)throw new RuntimeException('Worker output bound exceeded.');
        $status=proc_get_status($process);if(!$status['running']){$exit=$status['exitcode'];break;}
        if(microtime(true)>$deadline)throw new RuntimeException('Worker deadline exceeded.');usleep(20000);
    }while(true);$out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);}
    finally{$status=proc_get_status($process);if($status['running'])proc_terminate($process);fclose($pipes[1]);fclose($pipes[2]);$closed=proc_close($process);if($exit===null)$exit=$closed;}
    if($exit!==0||$err!==''||strlen($out)>4096){
        if($err===''&&strlen($out)<=4096){$closed=json_decode($out,true);if(is_array($closed)&&array_keys($closed)===['failure'])$workerFailure=$closed['failure'];}
        throw new RuntimeException('Isolated owner worker failed.');
    }
    $result=json_decode($out,true,8,JSON_THROW_ON_ERROR);if(!is_array($result)||array_keys($result)!==['status','posts','connection'])throw new RuntimeException('Invalid worker receipt.');return $result;
}

/** Session variables witness the real INSERT before the intentionally failing audit. */
function rnc_fault(string $kind,callable $call,?string $witness=null):bool{
    if(!in_array($kind,['prepared','request','terminal','check','read_observed'],true)||($witness!==null&&!in_array($witness,[...array_values(registered_nameserver_changes_tables()),'registered_nameserver_sets'],true)))throw new LogicException('Unknown audit fault.');
    $before=$witness===null?0:rnc_count($witness);
    $sample=$witness===null?'':'SET @rnsc_rows=(SELECT COUNT(*) FROM '.table($witness).'); ';
    $action=$kind==='read_observed'?'registered_nameservers.observed':'provider.registered_nameserver_changes_'.$kind;
    db()->exec('CREATE TRIGGER rnsc_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='".$action."' THEN SET @rnsc_hits=COALESCE(@rnsc_hits,0)+1; ".$sample."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic nameserver change audit fault'; END IF; END");
    query('SET @rnsc_hits=0,@rnsc_rows=0');$caught=false;
    try{$call();}catch(PDOException $e){$caught=$e->getCode()==='45000'&&(int)query('SELECT @rnsc_hits')->fetchColumn()===1&&($witness===null||(int)query('SELECT @rnsc_rows')->fetchColumn()===$before+1);}
    finally{db()->exec('DROP TRIGGER rnsc_fault');}
    return $caught;
}
if($worker){
    try{
        $input=json_decode(stream_get_contents(STDIN,1025),true,5,JSON_THROW_ON_ERROR);
        if(!is_array($input)||array_keys($input)!==['request','link','anchor','binding','desired'])throw new RuntimeException('Invalid worker input.');
        foreach(['request','link','anchor'] as $field)if(!is_string($input[$field])||!preg_match('/\A[a-f0-9]{32}\z/',$input[$field]))throw new RuntimeException('Invalid worker identity.');
        if(!is_string($input['binding'])||!preg_match('/\A[a-f0-9]{64}\z/',$input['binding'])||$input['desired']!==RNC_NEW)throw new RuntimeException('Invalid worker reviewed set.');
        rnc_owner();
        $out=null;$retry=0;
        while(true){
            try{$out=registered_nameserver_changes_change($input['request'],$input['link'],$input['anchor'],$input['binding'],$input['desired'],true,RNC_PASSWORD,'rnc_write_dns','rnc_perform');break;}
            catch(RuntimeException $e){if($e instanceof PDOException||!str_contains($e->getMessage(),'Another account change is in progress')||++$retry>12)throw $e;usleep(250000);}
        }
        echo json_encode(['status'=>$out['status'],'posts'=>$posts,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR);exit;
    }catch(Throwable $e){echo json_encode(['failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),
        'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR);exit(1);}
}
$handle=fopen($report,'xb');if(!$handle)exit(2);
try{
    rnc_ok('Fixed isolated database and namespace are empty',(string)query('SELECT DATABASE()')->fetchColumn()==='cwp_test'
        &&(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(table_name,?)=BINARY ?',[strlen(RNC_PREFIX),RNC_PREFIX])->fetchColumn()===0);
    $stage='seed';
    foreach(schema_statements(RNC_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $key=>$value)save_setting($key,$value);
    foreach([1,2] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Nameserver change staff '.$id,'rnsc'.$id.'@example.invalid',password_hash(RNC_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (2,\'administrator\',1,?)',[json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',
        ['Nameserver change client '.$id,'rnsc-client'.$id.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    rnc_owner();provider_settings_migrate(RNC_PASSWORD);
    $profile=provider_settings_save('',0,'Nameserver change native fixture',true,RNC_SECRET,RNC_PASSWORD);
    provider_inventory_migrate(RNC_PASSWORD);provider_registrar_prepare(RNC_PASSWORD);registered_nameservers_prepare(RNC_PASSWORD);provider_operations_migrate(RNC_PASSWORD);
    $stage='preparation';
    rnc_ok('Change storage is not silently enabled',!rnc_ready()&&!array_filter(registered_nameserver_changes_inventory()));
    db()->beginTransaction();try{
        $caught=false;try{registered_nameserver_changes_prepare(RNC_PASSWORD);}catch(LogicException){$caught=true;}
        rnc_ok('Explicit setup rejects inherited transaction before DDL',$caught&&db()->inTransaction()&&!array_filter(registered_nameserver_changes_inventory()));
    }finally{db()->rollBack();}
    rnc_deny('Wrong owner password cannot enable changes',fn()=>registered_nameserver_changes_prepare('wrong'));
    rnc_owner(2);rnc_deny('Administrator cannot enable changes',fn()=>registered_nameserver_changes_prepare(RNC_PASSWORD));rnc_owner();
    $_GET['support']='synthetic';rnc_deny('Support context cannot enable changes',fn()=>registered_nameserver_changes_prepare(RNC_PASSWORD));unset($_GET['support']);
    $schema=registered_nameserver_changes_schema(RNC_PREFIX);
    db()->exec($schema['provider_nameserver_change_requests']);
    rnc_ok('Exact empty first-table prefix remains unprepared',!rnc_ready());
    rnc_ok('Preparation audit failure leaves all exact tables empty and marker absent',rnc_fault('prepared',fn()=>registered_nameserver_changes_prepare(RNC_PASSWORD))
        &&!rnc_ready()&&!one('SELECT name FROM '.table('settings').' WHERE name=?',['registered_nameserver_changes_schema_version'])
        &&!array_filter(array_map('rnc_count',array_values(registered_nameserver_changes_tables()))));
    registered_nameserver_changes_prepare(RNC_PASSWORD);registered_nameserver_changes_prepare(RNC_PASSWORD);
    rnc_ok('Explicit preparation resumes once and exact retry is idempotent',rnc_ready()
        &&(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.registered_nameserver_changes_prepared'])->fetchColumn()===1);
    rnc_ok('Changes do not enable unrelated renewal-date storage',!one('SELECT name FROM '.table('settings').' WHERE name=?',['domain_contracts_schema_version'])&&!array_filter(domain_contracts_tables()));
    $stage='registrar';
    $readBody=json_encode([['id'=>701,'name'=>'one.synthetic.invalid'],['id'=>702,'name'=>'two.synthetic.invalid']],JSON_THROW_ON_ERROR);
    $source=provider_inventory_refresh_attempt($profile,1,RNC_PASSWORD,'rnc_read_dns','rnc_read',InventoryRead::DOMAINS_LIST);
    rnc_ok('Real registrar executor retains exact two-domain source',$source['state']==='complete'&&$gets===1);
    foreach([701,702] as $domain)provider_registrar_link($profile,$domain,1,1,$source['id'],RNC_PASSWORD);
    $link=one('SELECT * FROM '.table('provider_registrar_links').' WHERE profile_id=? AND domain_id=701',[$profile]);
    $otherLink=one('SELECT * FROM '.table('provider_registrar_links').' WHERE profile_id=? AND domain_id=702',[$profile]);
    $readBody=json_encode(['result'=>RNC_OLD]);$anchor=rnc_anchor($link);
    rnc_ok('Actual nameserver GET supplies the whole canonical reviewed anchor',$anchor['state']==='complete'&&$anchor['nameservers']===RNC_OLD
        &&$anchor['binding']->data['source_refresh_id']===$source['id']);

    $stage='admission';$id=bin2hex(random_bytes(16));$before=rnc_digest();$beforeIo=[$dns,$gets,$posts];
    $form=rnc_locked(fn()=>registered_nameserver_changes_form($link['id'],1));
    rnc_ok('Owner form binds exact latest set and current registrar provenance',$form['ready']===true&&$form['eligible']===true
        &&$form['anchor_id']===$anchor['id']&&$form['binding_sha256']===$anchor['binding']->sha256&&$form['old_nameservers']===RNC_OLD);
    rnc_client();rnc_deny('Primary client cannot approve registrar changes',fn()=>rnc_change($id,$link,$anchor));rnc_owner();
    rnc_owner(2);rnc_deny('Administrator cannot approve registrar changes',fn()=>rnc_change($id,$link,$anchor));rnc_owner();
    $_GET['support']='synthetic';rnc_deny('Support context cannot approve registrar changes',fn()=>rnc_change($id,$link,$anchor));unset($_GET['support']);
    rnc_deny('Wrong current owner password refuses before reservation',fn()=>registered_nameserver_changes_change($id,$link['id'],$anchor['id'],$anchor['binding']->sha256,RNC_NEW,true,'wrong','rnc_write_dns','rnc_perform'));
    rnc_deny('Unreviewed set refuses before reservation',fn()=>registered_nameserver_changes_change($id,$link['id'],$anchor['id'],$anchor['binding']->sha256,RNC_NEW,false,RNC_PASSWORD,'rnc_write_dns','rnc_perform'));
    rnc_deny('Foreign binding hash refuses before reservation',fn()=>registered_nameserver_changes_change($id,$link['id'],$anchor['id'],str_repeat('0',64),RNC_NEW,true,RNC_PASSWORD,'rnc_write_dns','rnc_perform'));
    rnc_deny('Foreign link cannot reuse the reviewed read',fn()=>rnc_change($id,$otherLink,$anchor));
    rnc_deny('Empty desired set refuses before reservation',fn()=>rnc_change($id,$link,$anchor,[]));
    rnc_deny('Overlapping desired set refuses before reservation',fn()=>rnc_change($id,$link,$anchor,['ns1.synthetic.invalid','ns3.synthetic.invalid']));
    rnc_deny('Generic operation approval cannot bypass dedicated nameserver review',fn()=>provider_operation_grant($profile,RegistrarNameserverChange::OPERATION,
        ['domainId'=>701,'ns'=>RNC_NEW,'old-ns'=>RNC_OLD],RNC_PASSWORD));
    rnc_ok('All admission denials leave exact SQL and transport counts unchanged',rnc_digest()===$before&&[$dns,$gets,$posts]===$beforeIo);
    rnc_ok('Request audit failure rolls back inserted request and consumed grant',rnc_fault('request',fn()=>rnc_change($id,$link,$anchor),'provider_nameserver_change_requests')
        &&rnc_digest()===$before&&[$dns,$gets,$posts]===$beforeIo);

    $stage='single-send';
    $input=['request'=>$id,'link'=>$link['id'],'anchor'=>$anchor['id'],'binding'=>$anchor['binding']->sha256,'desired'=>RNC_NEW];
    $workers=[rnc_launch($input),rnc_launch($input)];
    try{$a=rnc_join($workers[0]);}finally{$b=rnc_join($workers[1]);}
    rnc_ok('Two separate owner processes reserve one request and send once',$a['status']==='uncertain'&&$b['status']==='uncertain'
        &&$a['posts']+$b['posts']===1&&$a['connection']!==$b['connection']);
    $request=rnc_locked(fn()=>registered_nameserver_changes_request($id));$summary=rnc_summary($id);
    $expectedParams=['domainId'=>701,'ns'=>RNC_NEW,'old-ns'=>RNC_OLD];
    rnc_ok('Stored request derives complete old set from original retained read',$request['document']['params']===$expectedParams
        &&$request['document']['anchor_id']===$anchor['id']&&$request['document']['anchor_seq']===$anchor['seq']&&$request['document']['before_nameservers']===RNC_OLD);
    rnc_ok('Original grant exactly binds request owner scope sequence and single-use key',$request['grant']['scope_params']===$request['row']['document_json']
        &&$request['grant']['consumed_key']==='nameservers:'.$id&&(int)$request['grant']['granted_by']===1
        &&(int)$request['grant']['seq']===(int)$request['row']['grant_seq']
        &&hash_equals($request['grant']['scope_fingerprint'],provider_operations_scope(RegistrarNameserverChange::OPERATION,$request['document'])));
    rnc_ok('Real gateway records one uncertain attempt without claiming remote success',$request['ledger']['operation']===RegistrarNameserverChange::OPERATION
        &&$request['ledger']['status']==='uncertain'&&(int)$request['ledger']['attempts']===1&&$summary['status']==='uncertain'&&$summary['last_check']===null&&$summary['can_verify']===true);
    $before=rnc_digest();$beforeIo=[$dns,$gets,$posts];
    $again=rnc_change($id,$link,$anchor);
    rnc_ok('Exact completed request replay never calls resolver or transport',$again===$summary&&rnc_digest()===$before&&[$dns,$gets,$posts]===$beforeIo);
    rnc_deny('Reusing a request ID with changed desired set refuses',fn()=>rnc_change($id,$link,$anchor,['ns5.synthetic.invalid']));
    rnc_deny('A second request for the unresolved profile and domain refuses',fn()=>rnc_change(bin2hex(random_bytes(16)),$link,$anchor));
    rnc_ok('Pending request suppresses old current-control claims',rnc_pending($link)['id']===$id&&!rnc_locked(fn()=>registered_nameserver_changes_current($link['id'])));
    rnc_ok('A different domain in the same profile is not fenced',rnc_pending($otherLink)===null);
    $originalAuth=one('SELECT auth_version FROM '.table('users').' WHERE id=1')['auth_version'];
    query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);rnc_owner();
    try{rnc_deny('New owner authentication cannot replay an old approval',fn()=>rnc_change($id,$link,$anchor));}
    finally{query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$originalAuth]);rnc_owner();}
    rnc_ok('Auth probe restores exact history without another send',rnc_digest()===$before&&[$dns,$gets,$posts]===$beforeIo);

    $stage='readback';$beforePosts=$posts;
    $readStatus=403;$failed=rnc_verify($id,$link);
    rnc_ok('Fresh failed readback remains unavailable and does not release fence',$failed['status']==='uncertain'
        &&$failed['last_check']['disposition']==='unavailable'&&$failed['last_check']['nameservers']===null&&rnc_pending($link)['id']===$id&&$posts===$beforePosts);
    $readStatus=200;$readBody=json_encode(['result'=>RNC_OLD]);$different=rnc_verify($id,$link);
    rnc_ok('Fresh different whole set keeps the original request unresolved',$different['status']==='uncertain'
        &&$different['last_check']['disposition']==='different'&&$different['last_check']['nameservers']===RNC_OLD&&rnc_pending($link)['id']===$id);
    $readBody=json_encode(['result'=>RNC_NEW]);rnc_wait($profile);$beforeChecks=rnc_count('provider_nameserver_change_checks');$beforeReads=rnc_count('registered_nameserver_bindings');
    rnc_ok('Readback audit fault rolls back check but preserves its independent completed GET',rnc_fault('check',fn()=>registered_nameserver_changes_verify($id,RNC_PASSWORD,'rnc_read_dns','rnc_read'),'provider_nameserver_change_checks')
        &&rnc_count('provider_nameserver_change_checks')===$beforeChecks&&rnc_count('registered_nameserver_bindings')===$beforeReads+1&&rnc_pending($link)['id']===$id);
    $matched=rnc_verify($id,$link);
    rnc_ok('Only a newly returned matching readback resolves the original request',$matched['status']==='observed'&&$matched['last_check']['disposition']==='observed'
        &&$matched['last_check']['nameservers']===RNC_NEW&&$matched['can_verify']===false&&rnc_pending($link)===null&&$posts===$beforePosts);
    $check=one('SELECT c.* FROM '.table('provider_nameserver_change_checks').' c JOIN '.table('provider_refreshes').' f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1',[$id]);
    $checkDoc=json_decode($check['document_json'],true,20,JSON_THROW_ON_ERROR);$receipt=registered_nameservers_read_row($check['receipt_id']);
    rnc_ok('Readback witness binds exact receipt after captured sequence floor',$receipt['id']===$checkDoc['receipt_id']&&$receipt['seq']===$checkDoc['receipt_seq']
        &&$receipt['seq']>$checkDoc['floor']&&$checkDoc['floor']>=$anchor['seq']&&(int)$check['actor_id']===1
        &&hash_equals($checkDoc['receipt_sha256'],registered_nameserver_changes_receipt_hash($receipt)));
    rnc_deny('Already observed request cannot reuse another readback',fn()=>registered_nameserver_changes_verify($id,RNC_PASSWORD,'rnc_read_dns','rnc_read'));

    $stage='history-integrity';
    $mutations=[
        'request row'=>function()use($id):void{query('DELETE FROM '.table('provider_nameserver_change_checks').' WHERE request_id=?',[$id]);query('DELETE FROM '.table('provider_nameserver_change_terminals').' WHERE id=?',[$id]);query('DELETE FROM '.table('provider_nameserver_change_requests').' WHERE id=?',[$id]);},
        'grant body'=>fn()=>query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',['{}',$id]),
        'grant owner'=>fn()=>query('UPDATE '.table('provider_operation_grants').' SET granted_by=2 WHERE id=?',[$id]),
        'request digest'=>fn()=>query('UPDATE '.table('provider_nameserver_change_requests').' SET document_sha256=? WHERE id=?',[str_repeat('0',64),$id]),
        'terminal row'=>fn()=>query('DELETE FROM '.table('provider_nameserver_change_terminals').' WHERE id=?',[$id]),
        'check row'=>fn()=>query('DELETE FROM '.table('provider_nameserver_change_checks').' WHERE id=?',[$check['id']]),
        'ledger row'=>fn()=>query('DELETE FROM '.table('provider_operations').' WHERE idempotency_key=?',['nameservers:'.$id]),
        'ledger attempts'=>fn()=>query('UPDATE '.table('provider_operations').' SET attempts=2 WHERE idempotency_key=?',['nameservers:'.$id]),
        'readback result'=>fn()=>query('DELETE FROM '.table('provider_refresh_results').' WHERE id=?',[$check['receipt_id']]),
        'readback set'=>fn()=>query('DELETE FROM '.table('registered_nameserver_sets').' WHERE id=?',[$check['receipt_id']]),
        'marker'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['registered_nameserver_changes_schema_version']),
        'unknown version'=>fn()=>save_setting('registered_nameserver_changes_schema_version','99'),
        'counter'=>fn()=>save_setting('registered_nameserver_changes_request_count','00'),
    ];
    foreach($mutations as $label=>$mutate)rnc_damage('Lost or corrupt '.$label.' refuses readiness',$mutate);
    foreach(['prepared','request','terminal','check'] as $kind)rnc_damage('Lost '.$kind.' audit refuses readiness',fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=?',['provider.registered_nameserver_changes_'.$kind]));

    $stage='recovery';$anchor2=registered_nameserver_changes_latest($link['id']);$id2=bin2hex(random_bytes(16));$beforePosts=$posts;
    $writeMode='timeout';$duringSend=fn()=>query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');
    rnc_ok('Terminal audit failure occurs after actual one-send timeout and rolls back its witness',rnc_fault('terminal',fn()=>rnc_change($id2,$link,$anchor2,RNC_OLD),'provider_nameserver_change_terminals')
        &&$posts===$beforePosts+1&&!one('SELECT id FROM '.table('provider_nameserver_change_terminals').' WHERE id=?',[$id2]));
    $request2=rnc_locked(fn()=>registered_nameserver_changes_request($id2));
    rnc_ok('Finished SQL ledger survives terminal rollback and client revocation',$request2['ledger']['status']==='uncertain'&&(int)$request2['ledger']['attempts']===1
        &&rnc_summary($id2)['status']==='interrupted'&&rnc_pending($link)['id']===$id2);
    rnc_deny('Revoked assigned client prevents readback or recovery',fn()=>registered_nameserver_changes_verify($id2,RNC_PASSWORD,'rnc_read_dns','rnc_read'));
    query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');$pending=rnc_summary($id2);$before=rnc_digest();$beforeIo=[$dns,$gets,$posts];
    rnc_ok('Interrupted exact replay preserves original ledger and never resends',rnc_change($id2,$link,$anchor2,RNC_OLD)===$pending&&rnc_digest()===$before&&[$dns,$gets,$posts]===$beforeIo);
    rnc_deny('Changed reviewed ledger hash refuses recovery',fn()=>registered_nameserver_changes_recover_finished($id2,str_repeat('0',64),RNC_PASSWORD));
    $recovered=registered_nameserver_changes_recover_finished($id2,$pending['recovery_ledger_sha256'],RNC_PASSWORD);
    rnc_ok('Original owner can recover only the local terminal from finished original ledger',$recovered['status']==='uncertain'&&$recovered['recovered_dispatch']===true&&[$dns,$gets,$posts]===$beforeIo);
    rnc_deny('A recovered terminal cannot be recovered twice',fn()=>registered_nameserver_changes_recover_finished($id2,$pending['recovery_ledger_sha256'],RNC_PASSWORD));
    rnc_ok('Actual canonical POST contains complete old and desired sets only',$wire[0]===['method'=>'POST','url'=>'https://api.20i.com/domain/701/nameservers',
        'body'=>json_encode(['ns'=>RNC_OLD,'old-ns'=>RNC_NEW],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
    $readBody=json_encode(['result'=>RNC_OLD]);$resolved=rnc_verify($id2,$link);
    rnc_ok('Recovered dispatch still requires its separate fresh matching readback',$resolved['status']==='observed'&&rnc_pending($link)===null&&$posts===$beforePosts+1);

    $stage='latest-anchor';$latest=registered_nameserver_changes_latest($link['id']);$readStatus=503;rnc_refresh($link);
    rnc_deny('Newest failed read masks an earlier complete approval anchor',fn()=>rnc_change(bin2hex(random_bytes(16)),$link,$latest));
    rnc_ok('Owner form exposes no old set after newest failed read',!rnc_locked(fn()=>registered_nameserver_changes_form($link['id'],1))['eligible']);
    $readStatus=200;rnc_wait($profile);
    rnc_ok('Real read result audit fault leaves a durable unfinished newest intent',rnc_fault('read_observed',fn()=>rnc_refresh($link,false),'registered_nameserver_sets')
        &&registered_nameserver_changes_latest($link['id'])['state']==='unfinished');
    rnc_deny('Newest unfinished read masks the earlier complete approval anchor',fn()=>rnc_change(bin2hex(random_bytes(16)),$link,$latest));
    $anchor3=rnc_anchor($link);$id3=bin2hex(random_bytes(16));$beforePosts=$posts;
    $duringDns=fn()=>query('UPDATE '.table('staff_profiles').' SET role=? WHERE user_id=1',['administrator']);
    try{rnc_deny('Owner role revocation during DNS prevents wire dispatch',fn()=>rnc_change($id3,$link,$anchor3));}
    finally{query('UPDATE '.table('staff_profiles').' SET role=? WHERE user_id=1',['owner']);rnc_owner();}
    rnc_ok('DNS revocation retains truthful no-send result without ledger',$posts===$beforePosts&&rnc_summary($id3)['status']==='not_sent'
        &&!one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['nameservers:'.$id3])&&rnc_pending($link)===null);
    rnc_deny('No-send request cannot reuse its old anchor for a new approval',fn()=>rnc_change(bin2hex(random_bytes(16)),$link,$anchor3));

    $stage='source-and-fence';
    rnc_wait($profile);$readBody=json_encode([['id'=>701,'name'=>'one.synthetic.invalid'],['id'=>702,'name'=>'two.synthetic.invalid']]);
    $newSource=provider_inventory_refresh_attempt($profile,1,RNC_PASSWORD,'rnc_read_dns','rnc_read',InventoryRead::DOMAINS_LIST);
    rnc_ok('New actual registrar source replaces the previous approval binding',$newSource['state']==='complete'&&$newSource['id']!==$source['id']);
    rnc_deny('Old anchor cannot silently adopt a newer registrar source',fn()=>rnc_change(bin2hex(random_bytes(16)),$link,$anchor3));
    $readBody=json_encode(['result'=>RNC_OLD]);$anchor4=rnc_anchor($link);$id4=bin2hex(random_bytes(16));$beforePosts=$posts;
    $unenteredLedger=static function($profile,$key,$address,$authorize,$operation):OperationResult{rnc_boundary($authorize);throw new RuntimeException('Synthetic performer failure before ledger.');};
    $missing=registered_nameserver_changes_change($id4,$link['id'],$anchor4['id'],$anchor4['binding']->sha256,RNC_NEW,true,RNC_PASSWORD,'rnc_write_dns',$unenteredLedger);
    rnc_ok('Entered performer without durable ledger remains fenced rather than retryable',$missing['status']==='uncertain'&&$posts===$beforePosts
        &&$missing['can_verify']===false&&!one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['nameservers:'.$id4])&&rnc_pending($link)['id']===$id4);
    rnc_deny('Missing execution ledger cannot be promoted by a later GET',fn()=>registered_nameserver_changes_verify($id4,RNC_PASSWORD,'rnc_read_dns','rnc_read'));
    rnc_deny('Missing execution ledger cannot be reconstructed as a finished send',fn()=>registered_nameserver_changes_recover_finished($id4,str_repeat('0',64),RNC_PASSWORD));
    rnc_ok('Missing-ledger exact replay never enters performer again',rnc_change($id4,$link,$anchor4)['status']==='uncertain'&&$posts===$beforePosts);

    $stage='current-context';
    $otherAnchor=rnc_anchor($otherLink);$keyChangedId=bin2hex(random_bytes(16));
    $duringDns=fn()=>provider_settings_save($profile,1,'Rotated synthetic nameserver key',true,'SYNTHETIC_REPLACEMENT_NAMESERVER_CHANGE_KEY',RNC_PASSWORD);
    $keyChanged=rnc_change($keyChangedId,$otherLink,$otherAnchor);
    rnc_ok('Actual owner key rotation during DNS prevents credentials or POST',$keyChanged['status']==='not_sent'&&$keyChanged['current_assignment']===false
        &&$posts===$beforePosts&&!one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['nameservers:'.$keyChangedId]));
    rnc_deny('Changed saved credential refuses replay and verification of old binding',fn()=>rnc_change($id4,$link,$anchor4));
    rnc_ok('Resource fence survives a legitimate profile credential change',rnc_pending($link)['id']===$id4&&$posts===$beforePosts);
    provider_settings_save($profile,2,'Restored synthetic nameserver key',true,RNC_SECRET,RNC_PASSWORD);
    rnc_wait($profile);$readBody=json_encode([['id'=>701,'name'=>'one.synthetic.invalid'],['id'=>702,'name'=>'two.synthetic.invalid']]);
    $rotatedSource=provider_inventory_refresh_attempt($profile,3,RNC_PASSWORD,'rnc_read_dns','rnc_read',InventoryRead::DOMAINS_LIST);
    provider_registrar_unlink($link['id'],1,RNC_PASSWORD);provider_registrar_link($profile,701,2,3,$rotatedSource['id'],RNC_PASSWORD);
    $newLink=one('SELECT * FROM '.table('provider_registrar_links').' WHERE profile_id=? AND domain_id=701',[$profile]);
    $readBody=json_encode(['result'=>RNC_OLD]);$newAnchor=rnc_anchor($newLink);
    rnc_deny('Fresh new-client assignment cannot evade the original profile-domain fence',fn()=>rnc_change(bin2hex(random_bytes(16)),$newLink,$newAnchor));
    rnc_ok('Reassignment preserves original request client and current fence',$newLink['id']!==$link['id']&&(int)$newLink['client_id']===2
        &&rnc_pending($newLink)['id']===$id4&&rnc_locked(fn()=>registered_nameserver_changes_request($id4))['binding']->data['client_id']===1&&$posts===$beforePosts);

    $stage='established-storage';
    foreach(array_reverse(array_values(registered_nameserver_changes_tables())) as $name){
        db()->exec('SET FOREIGN_KEY_CHECKS=0');try{
            db()->exec('RENAME TABLE '.table($name).' TO `rnsc_hidden`');try{
                rnc_deny('Lost established '.$name.' cannot be recreated',fn()=>registered_nameserver_changes_prepare(RNC_PASSWORD));
                rnc_ok('Missing '.$name.' remains absent',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?',[RNC_PREFIX.$name]));
            }finally{db()->exec('RENAME TABLE `rnsc_hidden` TO '.table($name));}
        }finally{db()->exec('SET FOREIGN_KEY_CHECKS=1');}
    }
    db()->exec('CREATE TABLE rnsc_provider_nameserver_change_unknown (id INT) ENGINE=InnoDB');
    try{rnc_deny('Unknown namespace table refuses explicit setup',fn()=>registered_nameserver_changes_prepare(RNC_PASSWORD));}
    finally{db()->exec('DROP TABLE rnsc_provider_nameserver_change_unknown');}
    $retained=json_encode([all('SELECT * FROM '.table('provider_nameserver_change_requests')),all('SELECT * FROM '.table('provider_nameserver_change_terminals')),all('SELECT * FROM '.table('provider_nameserver_change_checks')),all('SELECT * FROM '.table('provider_operations')),all('SELECT * FROM '.table('audit'))],JSON_THROW_ON_ERROR);
    rnc_ok('Retained history excludes synthetic secrets and authorization headers',!str_contains($retained,RNC_SECRET)&&!str_contains($retained,base64_encode(RNC_SECRET))&&!str_contains($retained,'Bearer '));
    rnc_ok('Complete retained history is readable while unresolved work remains fenced',rnc_ready()&&rnc_pending($newLink)['id']===$id4);
}catch(Throwable $e){
    $checks[]=['name'=>'Unexpected native failure','passed'=>false];
    $failure=['stage'=>$stage,'class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null];
}
$databaseVersion=null;try{$databaseVersion=(string)query('SELECT VERSION()')->fetchColumn();}catch(PDOException){}
$ok=!array_filter($checks,fn($row)=>$row['passed']!==true);
$result=['passed'=>$ok,'scope'=>'Isolated real SQL/owner/read/change/gateway/ledger with synthetic transport; no provider network, UI or live acceptance',
    'runtime'=>['php'=>PHP_VERSION,'database'=>$databaseVersion],'checks'=>$checks,'failure'=>$failure??null,'worker_failure'=>$workerFailure];
$json=json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
if(fwrite($handle,$json)!==strlen($json))throw new RuntimeException('Could not preserve native report.');fclose($handle);
echo ($ok?'PASS':'FAIL').': '.count($checks).' registered nameserver change native assertions'.PHP_EOL;exit($ok?0:1);
