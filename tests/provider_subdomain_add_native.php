<?php
/** Isolated native SQL/owner/gateway acceptance. All provider responses are synthetic. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_SUBDOMAIN_ADD')!=='1'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
$worker=count($argv)===2&&$argv[1]==='--contender';
if(count($argv)!==($worker?2:1))exit(2);
$port=getenv('CWP_TEST_PORT')?:'33306';if(!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/subdomain_add.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIDocumentRoots\{DocumentRootsBinding,DocumentRootsObservation,DocumentRootsReader};
use CajunWebPro\Foundation\TwentyI\Operations\{SubdomainAdd,TwentyIOperationAdapter};

const SDAN_PREFIX='sdan_';
const SDAN_PASSWORD='Synthetic subdomain owner passphrase';
const SDAN_SECRET='SYNTHETIC_SUBDOMAIN_ADD_KEY';
const SDAN_NAME='site701.synthetic.invalid';
const SDAN_OLD='public_html/original';
const SDAN_NEW='shop.site701.synthetic.invalid';
$root=realpath(__DIR__.'/..');if(!$root)exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';$private=$runtime.'/subdomain-add-private';
$report=$qa.'/provider-subdomain-add-native-results.json';
foreach([$runtime,$qa] as $path){if(is_link($path)||(file_exists($path)&&!is_dir($path)))exit(2);if(!is_dir($path)&&!mkdir($path,0700))exit(2);}
if(is_link($private)||($worker?!is_dir($private):file_exists($private))||(!$worker&&(is_link($report)||file_exists($report))))exit(2);
if(!$worker&&!mkdir($private,0700))exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/subdomain-add','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-subdomain-add-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$port,'name'=>'cwp_test',
    'user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>SDAN_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root.'/site';start_session();
$checks=[];$stage='preflight';$dns=0;$gets=0;$posts=0;$wire=[];$duringDns=null;$duringPost=null;$capturedAuthorize=null;
$readStatus=200;$readBody='';$writeMode='unknown';

function sdan_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function sdan_deny(string $name,callable $call,?string $message=null):void{
    $caught=null;try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    sdan_ok($name,$caught!==null&&($message===null||$caught->getMessage()===$message));
}
function sdan_owner(int $id=1):void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function sdan_client(int $id=1):void{
    $u=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);
    $_SESSION=['client_id'=>$id,'client_auth'=>$u['auth_version'],'client_login'=>time(),'client_last'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function sdan_locked(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function sdan_mutex():bool{
    $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.SDAN_PREFIX),0,40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;
}
function sdan_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function sdan_counts():array{global $dns,$gets,$posts;return [$dns,$gets,$posts,...array_map('sdan_count',['provider_subdomain_add_requests','provider_subdomain_add_terminals','provider_subdomain_add_checks','provider_operation_grants','provider_operations','provider_refreshes','audit'])];}
function sdan_digest():string{
    $rows=[];foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(SDAN_PREFIX),SDAN_PREFIX]) as $t){
        $name=$t['TABLE_NAME'];if(!preg_match('/\Asdan_[a-z0-9_]+\z/',$name))throw new LogicException('Unexpected fixture table.');
        $data=all('SELECT * FROM `'.$name.'`');usort($data,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$data;
    }return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
/** A genuine bounded cooldown; no saved date, quota or clock is changed. */
function sdan_wait(string $profile):void{
    if(db()->inTransaction()||sdan_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');
    $deadline=microtime(true)+32;while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
function sdan_read_dns():string{global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';}
function sdan_write_dns():string{
    global $dns,$duringDns;if(db()->inTransaction()||!sdan_mutex())throw new LogicException('Write DNS requires mutex outside transaction.');
    $row=one('SELECT * FROM '.table('provider_subdomain_add_requests').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Approval not durable before DNS.');
    $q=provider_subdomain_add_request($row['id']);
    if($q['grant']['consumed_key']!=='subdomainadd:'.$row['id']||$q['ledger']!==null
        ||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',
            ['provider.subdomain_add_request','Subdomain add request '.$row['id'].' %'])->fetchColumn()!==1)
        throw new LogicException('Consumed approval and audit must precede DNS.');
    $dns++;if($duringDns){$call=$duringDns;$duringDns=null;$call();}return '8.8.8.8';
}
function sdan_boundary(callable $authorize):void{$authorize();if(db()->inTransaction()||!sdan_mutex())throw new LogicException('Transport boundary requires owning mutex outside transaction.');}
final class SubdomainAddReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readBody,$readStatus;sdan_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null
            ||$request->headers!==['Authorization'=>'Bearer '.base64_encode(SDAN_SECRET),'Accept'=>'application/json'])throw new LogicException('Unexpected synthetic GET.');
        $gets++;return new TransportResponse($readStatus,[],$readBody);
    }
}
function sdan_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?DocumentRootsBinding $documentRootsContext=null):InventorySnapshot|DocumentRootsObservation{
    sdan_boundary($authorize);if($key!==SDAN_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{sdan_boundary($authorize);return $key;});
    if($operation===InventoryRead::PACKAGES_LIST)return InventoryReader::read(new SubdomainAddReadTransport($authorize,'https://api.20i.com/package'),$credential,$operation,null,time());
    if($operation!==DocumentRootsBinding::OPERATION||!$documentRootsContext)throw new LogicException('Unexpected subdomains read context.');
    return DocumentRootsReader::read(new SubdomainAddReadTransport($authorize,'https://api.20i.com/package/'.$documentRootsContext->data['package']['package_id'].'/web/documentRoots'),$credential,$documentRootsContext,time());
}
final class SubdomainAddWriteTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$wire,$writeMode,$duringPost;sdan_boundary($this->authorize);SubdomainAdd::wire($request);
        if(!in_array($request->url,['https://api.20i.com/package/701/web/subdomains','https://api.20i.com/package/702/web/subdomains','https://api.20i.com/package/703/web/subdomains'],true)
            ||($request->headers['Authorization']??null)!=='Bearer '.base64_encode(SDAN_SECRET))throw new LogicException('Unexpected synthetic POST account.');
        $posts++;$wire[]=['url'=>$request->url,'body'=>$request->body];if($duringPost){$call=$duringPost;$duringPost=null;$call();}
        if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
        if($writeMode!=='unknown')throw new LogicException('Unexpected synthetic response mode.');
        return new TransportResponse(200,[],'true');
    }
}
function sdan_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    global $capturedAuthorize;$capturedAuthorize=$authorize;sdan_boundary($authorize);
    if($key!==SDAN_SECRET||$address!=='8.8.8.8'||$operation->name!==SubdomainAdd::OPERATION)throw new LogicException('Wrong synthetic write identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),
        static function()use($authorize,$key):string{sdan_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new SubdomainAddWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function sdan_package(int $id):array{return ['id'=>$id,'name'=>'site'.$id.'.synthetic.invalid','names'=>['site'.$id.'.synthetic.invalid','other'.$id.'.synthetic.invalid'],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];}
function sdan_hosting(string $profile):array{
    global $readBody,$readStatus;sdan_wait($profile);$readStatus=200;$readBody=json_encode(array_map('sdan_package',[701,702,703]),JSON_THROW_ON_ERROR);
    return provider_inventory_refresh_attempt($profile,(int)provider_settings_load($profile)['revision'],SDAN_PASSWORD,'sdan_read_dns','sdan_read');
}
function sdan_map(array $link,string $path=SDAN_OLD,bool $wait=true):array{
    global $readBody,$readStatus;if($wait)sdan_wait($link['profile_id']);$readStatus=200;
    $readBody=json_encode(['site'.$link['package_id'].'.synthetic.invalid'=>$path,'other'.$link['package_id'].'.synthetic.invalid'=>'public_html/other'],JSON_THROW_ON_ERROR);
    $b=provider_document_roots_binding($link['id']);$r=provider_document_roots_refresh($link['id'],$b,SDAN_PASSWORD,'sdan_read_dns','sdan_read');
    if($r['state']!=='complete')throw new LogicException('Synthetic subdomains anchor did not complete.');
    return provider_document_roots_observation(provider_document_roots_source($r['id']));
}
function sdan_change(array $review,string $child=SDAN_NEW,?callable $resolver=null,?callable $perform=null):array{
    return provider_subdomain_add_add($review['request_id'],$review['link_id'],'site'.$review['package_id'].'.synthetic.invalid',$child,$review['review_proof'],true,SDAN_PASSWORD,$resolver??'sdan_write_dns',$perform??'sdan_perform');
}
function sdan_verify(string $id,array $link):array{sdan_wait($link['profile_id']);return provider_subdomain_add_verify($id,SDAN_PASSWORD,'sdan_read_dns','sdan_read');}
function sdan_summary(string $id):array{return sdan_locked(fn()=>provider_subdomain_add_summary($id));}
function sdan_ready():bool{return sdan_locked(fn()=>provider_subdomain_add_ready());}
function sdan_pending(array $link,string $child=SDAN_NEW):?array{return sdan_locked(fn()=>provider_subdomain_add_pending($link['profile_id'],(int)$link['package_id'],$child));}
function sdan_recover(string $id,?string $hash=null):array{return provider_subdomain_add_recover_finished($id,$hash??sdan_summary($id)['recovery_ledger_sha256'],SDAN_PASSWORD);}
function sdan_damage(string $name,callable $mutate):void{
    $before=sdan_digest();$session=$_SESSION;staff_locked(function()use($name,$mutate):void{db()->beginTransaction();try{$mutate();unset($GLOBALS['cwp_settings']);sdan_deny($name,fn()=>provider_subdomain_add_ready());}finally{if(db()->inTransaction())db()->rollBack();}});
    $_SESSION=$session;unset($GLOBALS['cwp_settings']);sdan_ok($name.' exact rollback restores retained rows',sdan_digest()===$before&&sdan_ready());
}
/** Real audit failure after INSERT, witnessed by a connection variable surviving rollback. */
function sdan_fault(string $kind,callable $call,?string $tableName=null):bool{
    if(!in_array($kind,['prepared','request','terminal','check'],true)||($tableName!==null&&!in_array($tableName,array_values(provider_subdomain_add_tables()),true)))throw new LogicException('Unknown audit fault.');
    $before=$tableName===null?0:sdan_count($tableName);$sample=$tableName===null?'':'SET @sdan_rows=(SELECT COUNT(*) FROM '.table($tableName).'); ';
    db()->exec('CREATE TRIGGER sdan_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.subdomain_add_".$kind."' THEN SET @sdan_hits=COALESCE(@sdan_hits,0)+1; ".$sample."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic subdomain audit fault'; END IF; END");
    query('SET @sdan_hits=0,@sdan_rows=0');$caught=false;
    try{$call();}catch(PDOException $e){$caught=$e->getCode()==='45000'&&(int)query('SELECT @sdan_hits')->fetchColumn()===1&&($tableName===null||(int)query('SELECT @sdan_rows')->fetchColumn()===$before+1);}
    finally{db()->exec('DROP TRIGGER sdan_fault');}return $caught;
}
function sdan_contender(array $input):void{
    $before=sdan_counts();$process=proc_open([PHP_BINARY,__FILE__,'--contender'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('Could not start isolated contender.');
    $out='';$err='';$exit=null;
    try{
        $json=json_encode($input,JSON_THROW_ON_ERROR);if(fwrite($pipes[0],$json)!==strlen($json))throw new RuntimeException('Contender input write failed.');fclose($pipes[0]);
        stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$deadline=microtime(true)+15;
        do{$out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);if(strlen($out)>2048||strlen($err)>2048)throw new RuntimeException('Contender output bound exceeded.');
            $state=proc_get_status($process);if(!$state['running']){$exit=$state['exitcode'];break;}if(microtime(true)>$deadline)throw new RuntimeException('Contender exceeded deadline.');usleep(20000);
        }while(true);$out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);
    }finally{$state=proc_get_status($process);if($state['running'])proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);$closed=proc_close($process);if($exit===null)$exit=$closed;}
    $result=json_decode($out,true);
    sdan_ok('Separate current owner loses actual held mutex before DNS or POST',$exit===0&&$err===''&&is_array($result)&&$result['refused_on_mutex']===true&&$result['dns']===0&&$result['posts']===0&&$result['connection']!==(int)query('SELECT CONNECTION_ID()')->fetchColumn());
    sdan_ok('Blocked contender retains exact parent counts',sdan_counts()===$before);
}
if($worker){
    try{
        $input=json_decode(stream_get_contents(STDIN,8193),true,5,JSON_THROW_ON_ERROR);
        if(!is_array($input)||array_keys($input)!==['request_id','link_id','package_id','review_proof']||$input['package_id']!==701)throw new RuntimeException('Invalid contender review.');
        foreach(['request_id','link_id'] as $key)if(!is_string($input[$key])||!preg_match('/\A[a-f0-9]{32}\z/',$input[$key]))throw new RuntimeException('Invalid contender identity.');
        if(!is_string($input['review_proof'])||strlen($input['review_proof'])>4096)throw new RuntimeException('Invalid contender proof.');
        sdan_owner();$denied=false;
        try{sdan_change($input);}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$denied=$e->getMessage()==='Another account change is in progress. Please retry.';}
        echo json_encode(['refused_on_mutex'=>$denied,'dns'=>$dns,'posts'=>$posts,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR);exit($denied&&$dns===0&&$posts===0?0:1);
    }catch(Throwable $e){echo json_encode(['failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR);exit(1);}
}
$handle=fopen($report,'xb');if(!$handle)exit(2);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
try{
    sdan_ok('Fixed disposable database begins with an empty isolated namespace',(string)query('SELECT DATABASE()')->fetchColumn()==='cwp_test'&&(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(SDAN_PREFIX),SDAN_PREFIX])->fetchColumn()===0);
    $stage='seed';foreach(schema_statements(SDAN_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $name=>$value)save_setting($name,$value);
    foreach([1,2,3] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Subdomain staff '.$id,'sdan'.$id.'@example.invalid',password_hash(SDAN_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Subdomain client '.$id,'sdan-client'.$id.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    sdan_owner();provider_settings_migrate(SDAN_PASSWORD);provider_inventory_migrate(SDAN_PASSWORD);provider_operations_migrate(SDAN_PASSWORD);
    $profile=provider_settings_save('',0,'Subdomain native controls',true,SDAN_SECRET,SDAN_PASSWORD);provider_document_roots_prepare(SDAN_PASSWORD);
    $stage='preparation';sdan_ok('Absent add feature stays unprepared without creating tables',!sdan_ready()&&!provider_subdomain_add_exists('provider_subdomain_add_requests'));
    db()->beginTransaction();try{sdan_deny('Preparation refuses inherited transaction',fn()=>provider_subdomain_add_prepare(SDAN_PASSWORD));sdan_ok('Preparation leaves caller transaction and absent tables intact',db()->inTransaction()&&!provider_subdomain_add_exists('provider_subdomain_add_requests'));}finally{db()->rollBack();}
    sdan_owner(2);sdan_deny('Administrator cannot prepare additions',fn()=>provider_subdomain_add_prepare(SDAN_PASSWORD));sdan_owner();sdan_deny('Wrong owner preparation password refuses',fn()=>provider_subdomain_add_prepare('wrong'));
    db()->exec('CREATE TABLE sdan_provider_subdomain_add_requests (id INT PRIMARY KEY) ENGINE=InnoDB');
    try{sdan_deny('Even an empty incompatible known table refuses preparation before other DDL',fn()=>provider_subdomain_add_prepare(SDAN_PASSWORD));sdan_ok('Incompatible empty prefix is not completed',!provider_subdomain_add_exists('provider_subdomain_add_terminals')&&!provider_subdomain_add_exists('provider_subdomain_add_checks'));}finally{db()->exec('DROP TABLE sdan_provider_subdomain_add_requests');}
    $schemas=provider_subdomain_add_schema(SDAN_PREFIX);db()->exec($schemas['provider_subdomain_add_requests']);
    sdan_ok('Known exact empty first-table interruption is recoverable',!sdan_ready()&&provider_subdomain_add_exists('provider_subdomain_add_requests'));
    sdan_ok('Preparation audit rollback leaves no accepted marker',sdan_fault('prepared',fn()=>provider_subdomain_add_prepare(SDAN_PASSWORD))&&!sdan_ready());
    provider_subdomain_add_prepare(SDAN_PASSWORD);$auditCount=sdan_count('audit');provider_subdomain_add_prepare(SDAN_PASSWORD);
    sdan_ok('Explicit preparation resumes exact empty tables and is idempotent',sdan_ready()&&sdan_count('audit')===$auditCount&&$dns===0&&$gets===0&&$posts===0);
    sdan_ok('Add preparation does not initialize unrelated renewal storage',one('SELECT name FROM '.table('settings').' WHERE name=?',['domain_renewal_schema_version'])===null);

    $stage='observations';sdan_ok('Initial real inventory completes through actual reader',sdan_hosting($profile)['state']==='complete');
    foreach([701,702,703] as $package)provider_inventory_link($profile,$package,$package===702?2:1,1,SDAN_PASSWORD);
    sdan_ok('Post-assignment real inventory completes through actual reader',sdan_hosting($profile)['state']==='complete');
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=701');$anchor=sdan_map($link);$review=provider_subdomain_add_review($link['id']);
    sdan_ok('Review exposes only current attached parents and a fresh signed request',$review['ready']===true&&$review['package_id']===701&&$review['parent_domains']===['other701.synthetic.invalid',SDAN_NAME]&&preg_match('/\A[a-f0-9]{32}\z/',$review['request_id'])===1&&$review['expires_epoch']>time());
    $before=sdan_counts();provider_subdomain_add_review($link['id']);provider_subdomain_add_history();sdan_ok('Review and history GET wrappers perform no writes or provider reads',sdan_counts()===$before);
    sdan_owner(2);sdan_deny('Administrator cannot approve a subdomain',fn()=>sdan_change($review));sdan_client();sdan_deny('Primary client cannot exercise owner add authority',fn()=>sdan_change($review));sdan_owner();
    $_GET['support']='synthetic';sdan_deny('Support context cannot approve a subdomain',fn()=>sdan_change($review));unset($_GET['support']);
    $_SESSION['contact_id']=91;sdan_deny('Contact provenance is refused before normalized owner context',fn()=>sdan_change($review));unset($_SESSION['contact_id']);
    foreach(['confirmation','password','parent','child','proof','request'] as $case){
        sdan_deny('Exact add review refuses '.$case,fn()=>provider_subdomain_add_add($case==='request'?str_repeat('a',32):$review['request_id'],$link['id'],$case==='parent'?'foreign.synthetic.invalid':SDAN_NAME,
            $case==='child'?SDAN_NAME:SDAN_NEW,$case==='proof'?$review['review_proof'].'0':$review['review_proof'],$case!=='confirmation',$case==='password'?'wrong':SDAN_PASSWORD,'sdan_write_dns','sdan_perform'));
    }
    sdan_ok('Rejected add inputs create no grant ledger DNS GET or POST',sdan_counts()===$before);
    sdan_wait($profile);$readStatus=401;$readBody='null';$failed=provider_document_roots_refresh($link['id'],$anchor['binding'],SDAN_PASSWORD,'sdan_read_dns','sdan_read');
    sdan_ok('Latest unavailable roots observation masks older complete review',$failed['state']==='unavailable'&&provider_subdomain_add_review($link['id'])['ready']===false);
    sdan_deny('Captured old complete review cannot bypass failed latest read',fn()=>sdan_change($review));
    $anchor=sdan_map($link);$review=provider_subdomain_add_review($link['id']);$before=sdan_counts();
    sdan_ok('Request audit rollback removes consumed grant and request before DNS',sdan_fault('request',fn()=>sdan_change($review),'provider_subdomain_add_requests')&&sdan_counts()===$before&&sdan_ready());

    $stage='dispatch';$first=$review['request_id'];$firstReview=$review;$firstAnchor=$anchor;
    $duringPost=function()use($first,$link,$review):void{
        global $capturedAuthorize;
        $before=sdan_counts();sdan_deny('In-flight claimed request cannot issue verification GET',fn()=>provider_subdomain_add_verify($first,SDAN_PASSWORD,'sdan_read_dns','sdan_read'));sdan_ok('In-flight verification refusal is local',sdan_counts()===$before);
        $session=$_SESSION;$digest=sdan_digest();
        foreach(['owner auth','credential','client','grant'] as $case){
            if($case==='owner auth'){$old=one('SELECT auth_version FROM '.table('users').' WHERE id=1')['auth_version'];query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);}
            elseif($case==='credential'){$old=provider_settings_load($link['profile_id'])['credentials'];query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',['changed-synthetic-seal',$link['profile_id']]);}
            elseif($case==='client'){$old=$link['client_id'];query('UPDATE '.table('provider_package_links').' SET client_id=2 WHERE id=?',[$link['id']]);}
            else{query('UPDATE '.table('provider_operation_grants').' SET revoked_at=? WHERE id=?',[gmdate('Y-m-d H:i:s'),$first]);}
            try{sdan_deny('Actual send callback refuses changed '.$case,$capturedAuthorize);}
            finally{if($case==='owner auth')query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$old]);elseif($case==='credential')query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[$old,$link['profile_id']]);elseif($case==='client')query('UPDATE '.table('provider_package_links').' SET client_id=? WHERE id=?',[$old,$link['id']]);else query('UPDATE '.table('provider_operation_grants').' SET revoked_at=NULL WHERE id=?',[$first]);$_SESSION=$session;unset($GLOBALS['cwp_settings']);}
        }
        sdan_ok('Authority mutation probes restore exact fixture rows and session',sdan_digest()===$digest&&sdan_counts()===$before&&$_SESSION===$session);
        sdan_contender(['request_id'=>$review['request_id'],'link_id'=>$review['link_id'],'package_id'=>$review['package_id'],'review_proof'=>$review['review_proof']]);
    };
    $result=sdan_change($review);$ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['subdomainadd:'.$first]);
    sdan_ok('Actual gateway sends one exact add-only POST and retains unknown result',$posts===1&&$wire[0]===['url'=>'https://api.20i.com/package/701/web/subdomains','body'=>'{"add":["shop.site701.synthetic.invalid"],"rem":[]}']&&$result['status']==='unknown'&&$ledger['status']==='uncertain'&&(int)$ledger['attempts']===1);
    sdan_ok('Summary binds exact requested parent and child without creation success',$result['id']===$first&&$result['parent_domain']===SDAN_NAME&&$result['subdomain']===SDAN_NEW&&$result['can_verify']===true);
    sdan_deny('Completed authorization cannot be reused',$capturedAuthorize);$before=sdan_counts();$replay=sdan_change($firstReview);
    sdan_ok('Exact request replay returns history without another send or audit',$replay['status']==='unknown'&&sdan_counts()===$before);
    sdan_deny('Same identifier cannot approve another child',fn()=>sdan_change($firstReview,'otherchild.'.SDAN_NAME));
    sdan_ok('Unchanged saved map cannot authorize another approval',provider_subdomain_add_review($link['id'])['ready']===false&&sdan_pending($link)['id']===$first);
    sdan_ok('Pending exact hostname remains locally fenced without granting client sharing',!sdan_locked(fn()=>provider_subdomain_add_current($link['id'],SDAN_NEW))&&sdan_count('provider_document_roots_permissions')===0);

    $stage='readback';$readBody=json_encode([SDAN_NAME=>SDAN_OLD],JSON_THROW_ON_ERROR);$different=sdan_verify($first,$link);
    sdan_ok('Complete independent map without child remains unknown',$different['status']==='unknown'&&$different['last_check']['disposition']==='different'&&sdan_pending($link)['id']===$first);
    $parallelReview=provider_subdomain_add_review($link['id']);$parallelChild='parallel.'.SDAN_NAME;
    sdan_deny('Fresh review cannot repeat the same unresolved hostname',fn()=>sdan_change($parallelReview));
    $parallel=sdan_change($parallelReview,$parallelChild);
    sdan_ok('Separate approved child may dispatch while first name remains unknown',$parallel['status']==='unknown'&&$posts===2&&sdan_pending($link)['id']===$first&&sdan_pending($link,$parallelChild)['id']===$parallel['id']);
    $readBody='null';$unknown=sdan_verify($first,$link);sdan_ok('Malformed independent map is unavailable and keeps fence',$unknown['status']==='unknown'&&$unknown['last_check']['disposition']==='unavailable'&&sdan_pending($link)['id']===$first);
    $readBody=json_encode([SDAN_NAME=>SDAN_OLD,SDAN_NEW=>''],JSON_THROW_ON_ERROR);sdan_wait($profile);$checkCount=sdan_count('provider_subdomain_add_checks');
    sdan_ok('Check audit fault rolls back confirmation after actual new GET',sdan_fault('check',fn()=>provider_subdomain_add_verify($first,SDAN_PASSWORD,'sdan_read_dns','sdan_read'),'provider_subdomain_add_checks')&&sdan_count('provider_subdomain_add_checks')===$checkCount&&sdan_pending($link)['id']===$first);
    $verified=sdan_verify($first,$link);
    sdan_ok('New exact child key with an empty path is observed and releases fence',$verified['status']==='observed'&&$verified['last_check']['disposition']==='observed'&&sdan_pending($link)===null&&$posts===2);
    sdan_ok('Independent readback preserves original ledger bytes',one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['subdomainadd:'.$first])===$ledger);
    sdan_deny('Observed request cannot reuse verification to refresh again',fn()=>provider_subdomain_add_verify($first,SDAN_PASSWORD,'sdan_read_dns','sdan_read'));

    $stage='recovery';$review=provider_subdomain_add_review($link['id']);$second=$review['request_id'];$secondChild='second.'.SDAN_NAME;$terminals=sdan_count('provider_subdomain_add_terminals');
    sdan_deny('Existing child in latest full map cannot be added again',fn()=>sdan_change($review,SDAN_NEW));
    sdan_ok('Actual terminal audit fault follows exactly one additional send',sdan_fault('terminal',fn()=>sdan_change($review,$secondChild),'provider_subdomain_add_terminals')&&$posts===3);
    $interrupted=sdan_summary($second);$hash=$interrupted['recovery_ledger_sha256'];$secondLedger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['subdomainadd:'.$second]);
    sdan_ok('Terminal rollback keeps finished ledger and explicit local recovery',$interrupted['status']==='interrupted'&&is_string($hash)&&strlen($hash)===64&&sdan_count('provider_subdomain_add_terminals')===$terminals&&$secondLedger['status']==='uncertain');
    $before=sdan_counts();sdan_change($review,$secondChild);sdan_deny('Interrupted request cannot verify before local recovery',fn()=>provider_subdomain_add_verify($second,SDAN_PASSWORD,'sdan_read_dns','sdan_read'));
    sdan_deny('Wrong recovery digest refuses',fn()=>sdan_recover($second,str_repeat('f',64)));sdan_deny('Wrong recovery password refuses',fn()=>provider_subdomain_add_recover_finished($second,$hash,'wrong'));
    sdan_owner(3);sdan_deny('Another owner cannot recover original approval',fn()=>sdan_recover($second,$hash));sdan_owner();sdan_ok('Interrupted replay and recovery refusals cause no provider or business changes',sdan_counts()===$before);
    $digest=sdan_digest();sdan_ok('Recovery audit fault rolls back actual inserted witness',sdan_fault('terminal',fn()=>sdan_recover($second,$hash),'provider_subdomain_add_terminals')&&sdan_digest()===$digest&&sdan_ready());
    $recovered=sdan_recover($second,$hash);sdan_ok('Local recovery appends witness without resending or success claim',$recovered['status']==='unknown'&&$recovered['can_verify']===true&&$posts===3&&one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['subdomainadd:'.$second])===$secondLedger);
    sdan_deny('Recovery cannot overwrite existing witness',fn()=>sdan_recover($second,$hash));
    $readBody=json_encode([SDAN_NAME=>SDAN_OLD,SDAN_NEW=>'', $secondChild=>'public_html/second'],JSON_THROW_ON_ERROR);$confirmed=sdan_verify($second,$link);
    sdan_ok('Recovered request still requires separate child observation',$confirmed['status']==='observed'&&$posts===3&&sdan_pending($link,$secondChild)===null&&sdan_pending($link,$parallelChild)['id']===$parallel['id']);

    $stage='history';sdan_damage('Lost feature marker refuses established history',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_subdomain_add_schema_version']));
    sdan_damage('Changed consumed approval scope refuses',fn()=>query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',['{}',$second]));
    sdan_damage('Lost original ledger cannot become no-send',fn()=>query('DELETE FROM '.table('provider_operations').' WHERE id=?',[$secondLedger['id']]));
    sdan_damage('Changed original full map invalidates retained request',fn()=>query('UPDATE '.table('provider_document_roots_observations').' SET entries_json=? WHERE id=?',['[]',$firstAnchor['id']]));
    foreach(['terminal','check'] as $kind){$tableName=provider_subdomain_add_tables()[$kind];$row=one('SELECT * FROM '.table($tableName).($kind==='terminal'?' WHERE id=?':' WHERE request_id=? AND disposition=\'observed\''),[$kind==='terminal'?$parallel['id']:$second]);
        sdan_damage('Paired '.$kind.' and audit deletion cannot hide established history',function()use($kind,$tableName,$row):void{
            sdan_ok('Intended '.$kind.' row is deleted',query('DELETE FROM '.table($tableName).' WHERE id=?',[$row['id']])->rowCount()===1);
            $detail='Subdomain add '.$kind.' '.$row['id'].' '.provider_document_roots_digest(provider_subdomain_add_values($kind,$row));
            sdan_ok('Intended '.$kind.' audit is deleted',query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.subdomain_add_'.$kind,$detail])->rowCount()===1);
        });
    }
    foreach(['request','terminal','check'] as $kind){$tableName=provider_subdomain_add_tables()[$kind];$row=one('SELECT * FROM '.table($tableName).' ORDER BY id LIMIT 1');$sql=provider_document_roots_sql_digest(array_map(fn($key)=>'w.'.$key,provider_subdomain_add_columns($kind)));
        sdan_ok('Actual SQL and PHP witness bytes agree '.$kind,query('SELECT '.$sql.' FROM '.table($tableName).' w WHERE id=?',[$row['id']])->fetchColumn()===provider_document_roots_digest(provider_subdomain_add_values($kind,$row)));
    }
    $stage='nonrecovery';$review=provider_subdomain_add_review($link['id']);$local=sdan_change($review,'local.'.SDAN_NAME,static fn()=>throw new RuntimeException('Synthetic resolver refusal'));
    sdan_ok('Provable pre-perform refusal is not-sent without a ledger',$local['status']==='not_sent'&&$posts===3&&one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['subdomainadd:'.$local['id']])===null);
    sdan_ok('No-send does not authorize another approval on same saved map',provider_subdomain_add_review($link['id'])['ready']===false);
    $other=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=702');sdan_map($other);$missingReview=provider_subdomain_add_review($other['id']);$missing=$missingReview['request_id'];
    try{sdan_change($missingReview,'shop.site702.synthetic.invalid',static fn()=>throw new PDOException('Synthetic interrupted resolver'));sdan_ok('Interrupted resolver propagates',false);}catch(PDOException){sdan_ok('Interrupted resolver propagates',true);}
    sdan_ok('Interrupted pre-DNS work has no invented ledger or recovery',$posts===3&&sdan_summary($missing)['status']==='interrupted'&&sdan_summary($missing)['recovery_ledger_sha256']===null&&one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['subdomainadd:'.$missing])===null);
    $before=sdan_counts();sdan_deny('Missing ledger cannot be recovered',fn()=>sdan_recover($missing,str_repeat('0',64)));sdan_deny('Missing ledger cannot verify',fn()=>provider_subdomain_add_verify($missing,SDAN_PASSWORD,'sdan_read_dns','sdan_read'));sdan_change($missingReview,'shop.site702.synthetic.invalid');sdan_ok('Missing-completion replay stays local and fenced',sdan_counts()===$before&&sdan_pending($other,'shop.site702.synthetic.invalid')['id']===$missing);
    $claimedLink=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=703');sdan_map($claimedLink);$claimedReview=provider_subdomain_add_review($claimedLink['id']);$claimed=$claimedReview['request_id'];
    $claimOnly=static function(array $profile,string $key,string $address,callable $authorize,ProviderOperation $op):never{sdan_boundary($authorize);$fp=ProviderGateway::requestFingerprint('twenty-i',$profile['id'].':'.$profile['revision'].':'.provider_operations_credential_fingerprint($key),$op);(new ProviderOperationLedger())->claim($op->idempotencyKey,$fp,time());throw new PDOException('Synthetic interrupted claimed worker');};
    try{sdan_change($claimedReview,'shop.site703.synthetic.invalid',null,$claimOnly);sdan_ok('Claimed worker interruption propagates',false);}catch(PDOException){sdan_ok('Claimed worker interruption propagates',true);}
    $claim=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['subdomainadd:'.$claimed]);
    sdan_ok('Actual zero-attempt claim is interrupted and unrecoverable',$claim['status']==='claimed'&&(int)$claim['attempts']===0&&sdan_summary($claimed)['status']==='interrupted'&&sdan_summary($claimed)['recovery_ledger_sha256']===null);
    $before=sdan_counts();sdan_deny('Claimed ledger cannot recover as finished',fn()=>sdan_recover($claimed,provider_subdomain_add_ledger_digest($claim)));sdan_deny('Claimed ledger cannot verify or release fence',fn()=>provider_subdomain_add_verify($claimed,SDAN_PASSWORD,'sdan_read_dns','sdan_read'));sdan_change($claimedReview,'shop.site703.synthetic.invalid');sdan_ok('Claimed replay and refusals retain exact claim and counts',sdan_counts()===$before&&one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$claim['id']])===$claim);
    provider_inventory_unlink($claimedLink['id'],SDAN_PASSWORD);
    $history=provider_subdomain_add_history();$retained=array_values(array_filter($history['items'],fn($r)=>$r['id']===$claimed));
    sdan_ok('Global history retains orphaned request without recovery or verification authority',count($retained)===1&&$retained[0]['current_assignment']===false&&$retained[0]['can_verify']===false&&$retained[0]['recovery_ledger_sha256']===null&&sdan_pending($claimedLink,'shop.site703.synthetic.invalid')['id']===$claimed);
    sdan_ok('All resolved and unresolved immutable evidence remains internally ready',sdan_ready());
    $public=json_encode(['checks'=>$checks,'history'=>$history],JSON_THROW_ON_ERROR);sdan_ok('Safe public evidence excludes credential and password',!str_contains($public,SDAN_SECRET)&&!str_contains($public,base64_encode(SDAN_SECRET))&&!str_contains($public,SDAN_PASSWORD));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected native subdomain add failure at '.$stage,'passed'=>false];$failure=['stage'=>$stage,'class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null];}
finally{restore_error_handler();}
$passed=count($checks)>0&&!array_filter($checks,fn($c)=>$c['passed']!==true);
$result=['passed'=>$passed,'scope'=>'Actual isolated Linux SQL, owner authority and shared gateway with synthetic provider only. Genuine cooldowns; no rewritten observations, live provider or browser. Observed hostname does not establish causation, folder creation, DNS, TLS or site readiness. Source and syntax checks are not native acceptance.','checks'=>$checks];if(isset($failure))$result['failure']=$failure;
$bytes=json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";if(fwrite($handle,$bytes)!==strlen($bytes)||!fflush($handle))exit(1);fclose($handle);echo ($passed?'PASS':'FAIL').': '.count($checks)." subdomain add native checks\n";exit($passed?0:1);
