<?php
/** Isolated native SQL/owner/gateway acceptance. All provider responses are synthetic. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_SUBDOMAIN_REQUESTS')!=='1'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
$worker=count($argv)===2&&$argv[1]==='--worker';
if(count($argv)!==($worker?2:1))exit(2);
$port=getenv('CWP_TEST_PORT')?:'33306';if(!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/subdomain_requests.php';
require_once __DIR__.'/../site/app/activity/events.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIDocumentRoots\{DocumentRootsBinding,DocumentRootsObservation,DocumentRootsReader};
use CajunWebPro\Foundation\TwentyI\Operations\{SubdomainAdd,TwentyIOperationAdapter};

const SDRN_PREFIX='sdrn_';
const SDRN_PASSWORD='Synthetic client subdomain owner passphrase';
const SDRN_SECRET='SYNTHETIC_SUBDOMAIN_REQUEST_KEY';
const SDRN_NAME='site801.synthetic.invalid';
const SDRN_OLD='public_html/original';
const SDRN_NEW='shop.site801.synthetic.invalid';
$root=realpath(__DIR__.'/..');if(!$root)exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';$private=$runtime.'/subdomain-requests-private';
$report=$qa.'/provider-subdomain-requests-native-results.json';
foreach([$runtime,$qa] as $path){if(is_link($path)||(file_exists($path)&&!is_dir($path)))exit(2);if(!is_dir($path)&&!mkdir($path,0700))exit(2);}
if(is_link($private)||($worker?!is_dir($private):file_exists($private))||(!$worker&&(is_link($report)||file_exists($report))))exit(2);
if(!$worker&&!mkdir($private,0700))exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/subdomain-requests','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-subdomain-requests-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$port,'name'=>'cwp_test',
    'user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>SDRN_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root.'/site';start_session();
$checks=[];$stage='preflight';$dns=0;$gets=0;$posts=0;$wire=[];$duringDns=null;$duringPost=null;$capturedAuthorize=null;
$readStatus=200;$readBody='';$writeMode='unknown';

function sdrn_ok(string $name,bool $passed):void{global $checks;foreach($checks as $check)if($check['name']===$name)throw new LogicException('Duplicate fixture assertion.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function sdrn_deny(string $name,callable $call,?string $message=null):void{
    $caught=null;try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    sdrn_ok($name,$caught!==null&&($message===null||$caught->getMessage()===$message));
}
function sdrn_owner(int $id=1):void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function sdrn_client(int $id=1):void{
    $u=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);
    $_SESSION=['client_id'=>$id,'client_auth'=>$u['auth_version'],'client_login'=>time(),'client_last'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function sdrn_locked(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function sdrn_mutex():bool{
    $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.SDRN_PREFIX),0,40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;
}
function sdrn_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function sdrn_exists():bool{return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY TABLE_NAME=?',[SDRN_PREFIX.'provider_subdomain_requests_events'])!==null;}
function sdrn_counts():array{global $dns,$gets,$posts;return [$dns,$gets,$posts,...array_map('sdrn_count',['provider_subdomain_requests_events','provider_subdomain_add_requests','provider_subdomain_add_terminals','provider_subdomain_add_checks','provider_operation_grants','provider_operations','provider_refreshes','audit'])];}
function sdrn_rows():array{
    $rows=[];foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(SDRN_PREFIX),SDRN_PREFIX]) as $t){
        $name=$t['TABLE_NAME'];if(!preg_match('/\Asdrn_[a-z0-9_]+\z/',$name))throw new LogicException('Unexpected fixture table.');
        $data=all('SELECT * FROM `'.$name.'`');usort($data,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$data;
    }return $rows;
}
function sdrn_digest():string{return hash('sha256',json_encode(sdrn_rows(),JSON_THROW_ON_ERROR));}
function sdrn_recovery_delta(array $before,string $ownerId):bool{
    $after=sdrn_rows();$terminal=one('SELECT * FROM '.table('provider_subdomain_add_terminals').' WHERE id=?',[$ownerId]);if(!$terminal)return false;
    $detail='Subdomain add terminal '.$ownerId.' '.provider_document_roots_digest(provider_subdomain_add_values('terminal',$terminal));
    foreach(['provider_subdomain_add_terminals'=>fn($r)=>$r['id']===$ownerId,'audit'=>fn($r)=>$r['action']==='provider.subdomain_add_terminal'&&$r['detail']===$detail&&(int)$r['user_id']===1] as $tableName=>$isNew){
        $key=SDRN_PREFIX.$tableName;$new=array_values(array_filter($after[$key],$isNew));if(count($new)!==1||array_filter($before[$key],$isNew)!==[])return false;
        $after[$key]=array_values(array_filter($after[$key],fn($r)=>!$isNew($r)));
    }
    $name='provider_subdomain_add_terminal_count';$key=SDRN_PREFIX.'settings';$old=array_values(array_filter($before[$key],fn($r)=>$r['name']===$name));if(count($old)!==1)return false;$found=0;
    foreach($after[$key] as &$row)if($row['name']===$name){if((int)$row['value']!==(int)$old[0]['value']+1)return false;$row=$old[0];$found++;}unset($row);if($found!==1)return false;
    foreach($after as &$rows)usort($rows,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));unset($rows);return $after===$before;
}
/** A genuine bounded cooldown; no saved date, quota or clock is changed. */
function sdrn_wait(string $profile):void{
    if(db()->inTransaction()||sdrn_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');
    $deadline=microtime(true)+32;while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
function sdrn_read_dns():string{global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';}
function sdrn_write_dns():string{
    global $dns,$duringDns;if(db()->inTransaction()||!sdrn_mutex())throw new LogicException('Write DNS requires mutex outside transaction.');
    $row=one('SELECT * FROM '.table('provider_subdomain_add_requests').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Approval not durable before DNS.');
    $q=provider_subdomain_add_request($row['id']);
    if($q['grant']['consumed_key']!=='subdomainadd:'.$row['id']||$q['ledger']!==null
        ||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',
            ['provider.subdomain_add_request','Subdomain add request '.$row['id'].' %'])->fetchColumn()!==1)
        throw new LogicException('Consumed approval and audit must precede DNS.');
    $origin=$q['document']['client_origin']??null;
    if(!is_array($origin)||!one('SELECT id FROM '.table('provider_subdomain_requests_events').' WHERE id=? AND subject_id=? AND kind=?',[$row['id'],$origin['id'],'dispatch']))throw new LogicException('Client dispatch must be durable before DNS.');
    $dns++;if($duringDns){$call=$duringDns;$duringDns=null;$call();}return '8.8.8.8';
}
function sdrn_boundary(callable $authorize):void{$authorize();if(db()->inTransaction()||!sdrn_mutex())throw new LogicException('Transport boundary requires owning mutex outside transaction.');}
final class SdrnReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readBody,$readStatus;sdrn_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null
            ||$request->headers!==['Authorization'=>'Bearer '.base64_encode(SDRN_SECRET),'Accept'=>'application/json'])throw new LogicException('Unexpected synthetic GET.');
        $gets++;return new TransportResponse($readStatus,[],$readBody);
    }
}
function sdrn_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?DocumentRootsBinding $documentRootsContext=null):InventorySnapshot|DocumentRootsObservation{
    sdrn_boundary($authorize);if($key!==SDRN_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{sdrn_boundary($authorize);return $key;});
    if($operation===InventoryRead::PACKAGES_LIST)return InventoryReader::read(new SdrnReadTransport($authorize,'https://api.20i.com/package'),$credential,$operation,null,time());
    if($operation!==DocumentRootsBinding::OPERATION||!$documentRootsContext)throw new LogicException('Unexpected subdomains read context.');
    return DocumentRootsReader::read(new SdrnReadTransport($authorize,'https://api.20i.com/package/'.$documentRootsContext->data['package']['package_id'].'/web/documentRoots'),$credential,$documentRootsContext,time());
}
final class SdrnWriteTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$wire,$writeMode,$duringPost;sdrn_boundary($this->authorize);SubdomainAdd::wire($request);
        if(!in_array($request->url,['https://api.20i.com/package/801/web/subdomains','https://api.20i.com/package/802/web/subdomains'],true)
            ||($request->headers['Authorization']??null)!=='Bearer '.base64_encode(SDRN_SECRET))throw new LogicException('Unexpected synthetic POST account.');
        $posts++;$wire[]=['url'=>$request->url,'body'=>$request->body];if($duringPost){$call=$duringPost;$duringPost=null;$call();}
        if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
        if($writeMode!=='unknown')throw new LogicException('Unexpected synthetic response mode.');
        return new TransportResponse(200,[],'true');
    }
}
function sdrn_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    global $capturedAuthorize;$capturedAuthorize=$authorize;sdrn_boundary($authorize);
    if($key!==SDRN_SECRET||$address!=='8.8.8.8'||$operation->name!==SubdomainAdd::OPERATION)throw new LogicException('Wrong synthetic write identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),
        static function()use($authorize,$key):string{sdrn_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new SdrnWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function sdrn_package(int $id):array{return ['id'=>$id,'name'=>'site'.$id.'.synthetic.invalid','names'=>['site'.$id.'.synthetic.invalid','other'.$id.'.synthetic.invalid'],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];}
function sdrn_hosting(string $profile):array{
    global $readBody,$readStatus;sdrn_wait($profile);$readStatus=200;$readBody=json_encode(array_map('sdrn_package',[801,802]),JSON_THROW_ON_ERROR);
    return provider_inventory_refresh_attempt($profile,(int)provider_settings_load($profile)['revision'],SDRN_PASSWORD,'sdrn_read_dns','sdrn_read');
}
function sdrn_map(array $link,string $path=SDRN_OLD,bool $wait=true):array{
    global $readBody,$readStatus;if($wait)sdrn_wait($link['profile_id']);$readStatus=200;
    $readBody=json_encode(['site'.$link['package_id'].'.synthetic.invalid'=>$path,'other'.$link['package_id'].'.synthetic.invalid'=>'public_html/other'],JSON_THROW_ON_ERROR);
    $b=provider_document_roots_binding($link['id']);$r=provider_document_roots_refresh($link['id'],$b,SDRN_PASSWORD,'sdrn_read_dns','sdrn_read');
    if($r['state']!=='complete')throw new LogicException('Synthetic subdomains anchor did not complete.');
    return provider_document_roots_observation(provider_document_roots_source($r['id']));
}
function sdrn_launch(array $input):array{
    $process=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('Could not start client-request worker.');
    $json=json_encode($input,JSON_THROW_ON_ERROR);
    if(fwrite($pipes[0],$json)!==strlen($json))throw new RuntimeException('Could not send worker input.');
    fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    return ['process'=>$process,'pipes'=>$pipes,'stdout'=>'','stderr'=>'','connection'=>null];
}
/** Require a real blocked child GET_LOCK while this connection owns the exact mutex. */
function sdrn_blocked(array &$worker):void{
    if(!sdrn_mutex()||db()->inTransaction())throw new LogicException('Race observer needs owning mutex without transaction.');
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
            if($row&&stripos((string)$row['INFO'],'GET_LOCK')!==false&&stripos((string)$row['STATE'],'lock')!==false&&sdrn_mutex())return;
        }
        $status=proc_get_status($worker['process']);if(!$status['running'])throw new RuntimeException('Worker exited before observed lock contention.');
        if(microtime(true)>$deadline)throw new RuntimeException('Actual blocked worker was not observed within lock budget.');usleep(20000);
    }while(true);
}
function sdrn_join(array &$worker):array{
    $exit=null;$deadline=microtime(true)+12;
    $deadline=microtime(true)+25;
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
function sdrn_screen(?int $client=null,?string $id=null):array{return provider_subdomain_requests_screen($client,1,$id);}
function sdrn_item(string $id,?int $client=null):array{
    $items=array_values(array_filter(sdrn_screen($client,$id)['items'],fn($r)=>$r['id']===$id));
    if(count($items)!==1)throw new RuntimeException('Expected retained request.');return $items[0];
}
function sdrn_option(array $link,int $client=1):array{
    $rows=array_values(array_filter(sdrn_screen($client)['options'],fn($r)=>$r['link_id']===$link['id']&&$r['parent_domain']==='site'.$link['package_id'].'.synthetic.invalid'));
    if(count($rows)!==1)throw new RuntimeException('Expected assigned parent option.');return $rows[0];
}
function sdrn_create(string $id,array $option,string $child,int $client=1):array{
    return provider_subdomain_requests_create($id,$client,$option['link_id'],$option['review_sha256'],$option['parent_domain'],$child,true);
}
function sdrn_approve(string $id,array $item,?callable $resolver=null,?callable $perform=null):array{
    return provider_subdomain_requests_approve($id,$item['latest_event'],$item['request_sha256'],$item['approval']['request_id'],$item['approval']['review_proof'],true,SDRN_PASSWORD,$resolver??'sdrn_write_dns',$perform??'sdrn_perform');
}
function sdrn_verify(string $id,string $ownerId,string $profile):array{
    sdrn_wait($profile);return provider_subdomain_requests_verify($id,$ownerId,SDRN_PASSWORD,'sdrn_read_dns','sdrn_read');
}
function sdrn_pending(array $link,string $child):?array{return sdrn_locked(fn()=>provider_subdomain_add_pending($link['profile_id'],(int)$link['package_id'],$child));}
function sdrn_graph():array{return sdrn_locked(fn()=>provider_subdomain_requests_graph());}
function sdrn_event_audits():int{return (int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.subdomain_requests_event'])->fetchColumn();}
function sdrn_head():string{return (string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_subdomain_requests_head'])['value']??'');}
/** A real audit INSERT fault; connection variables survive rollback and prove the late insertion. */
function sdrn_fault(string $phase,callable $call):bool{
    $actions=['prepare'=>'provider.subdomain_requests_prepared','intake'=>'provider.subdomain_requests_event','dispatch'=>'provider.subdomain_requests_event','terminal'=>'provider.subdomain_add_terminal','check'=>'provider.subdomain_add_check'];
    if(!isset($actions[$phase]))throw new LogicException('Unknown fixture fault.');
    $tables=['provider_subdomain_requests_events','provider_subdomain_add_requests','provider_operation_grants','provider_subdomain_add_terminals','provider_subdomain_add_checks'];
    $before=array_map('sdrn_count',$tables);$sample='';foreach($tables as $i=>$t)$sample.='SET @sdrn_'.$i.'=(SELECT COUNT(*) FROM '.table($t).'); ';
    db()->exec('CREATE TRIGGER sdrn_audit_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='".$actions[$phase]."' THEN SET @sdrn_hit=COALESCE(@sdrn_hit,0)+1; ".$sample."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic request audit fault'; END IF; END");
    query('SET @sdrn_hit=0');$caught=false;
    try{$call();}catch(PDOException $e){
        $delta=match($phase){'prepare'=>[0,0,0,0,0],'intake'=>[1,0,0,0,0],'dispatch'=>[1,1,1,0,0],'terminal'=>[1,1,1,1,0],'check'=>[0,0,0,0,1]};
        $caught=$e->getCode()==='45000'&&(int)query('SELECT @sdrn_hit')->fetchColumn()===1;
        foreach($before as $i=>$n)$caught=$caught&&((int)query('SELECT @sdrn_'.$i)->fetchColumn()===$n+$delta[$i]);
    }finally{db()->exec('DROP TRIGGER sdrn_audit_fault');}return $caught;
}
/** Private worker input contains only synthetic IDs and a real retained signed form. */
function sdrn_worker():never{
    global $dns,$gets,$posts;
    try{
        $in=json_decode(stream_get_contents(STDIN,8193),true,6,JSON_THROW_ON_ERROR);
        if(!is_array($in)||array_keys($in)!==['action','id','expected','event','request_sha256','review_proof']||!in_array($in['action'],['cancel','approve'],true))throw new RuntimeException('Invalid worker command.');
        foreach(['id','expected','event'] as $k)if(!is_string($in[$k])||!preg_match('/\A[a-f0-9]{32}\z/',$in[$k]))throw new RuntimeException('Invalid worker ID.');
        if(!is_string($in['request_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$in['request_sha256'])||!is_string($in['review_proof'])||strlen($in['review_proof'])>4096)throw new RuntimeException('Invalid worker proof.');
        if($in['action']==='cancel')sdrn_client();else sdrn_owner();
        echo json_encode(['ready'=>true,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR)."\n";flush();
        for($attempt=1;;$attempt++):
        $outcome='completed';try{
            if($in['action']==='cancel')provider_subdomain_requests_change($in['id'],$in['expected'],$in['event'],'cancel','',1);
            else provider_subdomain_requests_approve($in['id'],$in['expected'],$in['request_sha256'],$in['event'],$in['review_proof'],true,SDRN_PASSWORD,'sdrn_write_dns','sdrn_perform');
            break;
        }catch(InvalidArgumentException|RuntimeException $e){
            if($e->getMessage()==='Another account change is in progress. Please retry.'&&$attempt<3)continue;
            $expected=$in['action']==='cancel'?'This subdomain request changed. Reload its review.':'This request was already reviewed or withdrawn.';
            if($e instanceof PDOException||$e->getMessage()!==$expected)throw $e;$outcome='refused';
            break;
        }
        endfor;
        echo json_encode(['outcome'=>$outcome,'dns'=>$dns,'gets'=>$gets,'posts'=>$posts],JSON_THROW_ON_ERROR)."\n";exit;
    }catch(Throwable $e){echo json_encode(['failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR)."\n";exit(1);}
}
function sdrn_worker_input(string $action,string $id,string $event,array $item):array{
    return ['action'=>$action,'id'=>$id,'expected'=>$item['latest_event'],'event'=>$event,'request_sha256'=>$item['request_sha256'],'review_proof'=>$item['approval']['review_proof']];
}
function sdrn_race(array $input,callable $winner,string $label):array{
    $child=null;try{staff_locked(function()use($input,$winner,$label,&$child):void{
        $child=sdrn_launch($input);sdrn_blocked($child);sdrn_ok($label.' observes a separate blocked GET_LOCK',true);$winner();
    });}finally{if($child!==null)$result=sdrn_join($child);}return $result;
}
function sdrn_seed():array{
    sdrn_ok('Dedicated native database namespace begins empty',(string)query('SELECT DATABASE()')->fetchColumn()==='cwp_test'&&(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(SDRN_PREFIX),SDRN_PREFIX])->fetchColumn()===0);
    foreach(schema_statements(SDRN_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $k=>$v)save_setting($k,$v);
    foreach([1,2,3] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Subdomain requests staff '.$id,'sdrn'.$id.'@example.invalid',password_hash(SDRN_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Subdomain requests client '.$id,'sdrn-client'.$id.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    sdrn_owner();provider_settings_migrate(SDRN_PASSWORD);provider_inventory_migrate(SDRN_PASSWORD);provider_operations_migrate(SDRN_PASSWORD);
    activity_migrate(SDRN_PASSWORD);sdrn_ok('Notifications explicitly prepare real per-person read storage',activity_ready()&&sdrn_count('activity_reads')===0);
    $profile=provider_settings_save('',0,'Client subdomain request fixture',true,SDRN_SECRET,SDRN_PASSWORD);provider_document_roots_prepare(SDRN_PASSWORD);provider_subdomain_add_prepare(SDRN_PASSWORD);
    sdrn_ok('Actual package list reader records initial synthetic inventory',sdrn_hosting($profile)['state']==='complete');
    foreach([801,802] as $package)provider_inventory_link($profile,$package,$package===802?2:1,1,SDRN_PASSWORD);
    $source=sdrn_hosting($profile);sdrn_ok('Actual post-assignment package inventory completes',$source['state']==='complete');
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=801');$other=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=802');$anchor=sdrn_map($link);
    return compact('profile','source','link','other','anchor');
}
function sdrn_damage(string $name,callable $mutate):void{
    global $dns,$gets,$posts;$before=sdrn_digest();$io=[$dns,$gets,$posts];$session=$_SESSION;
    staff_locked(function()use($name,$mutate):void{db()->beginTransaction();try{$mutate();unset($GLOBALS['cwp_settings']);sdrn_deny($name,fn()=>provider_subdomain_requests_state());}finally{if(db()->inTransaction())db()->rollBack();}});
    $_SESSION=$session;unset($GLOBALS['cwp_settings']);sdrn_ok($name.' exact rollback restores history',sdrn_digest()===$before&&[$dns,$gets,$posts]===$io&&is_array(sdrn_locked(fn()=>provider_subdomain_requests_state())));
}
/** Activity source rows are fixture-only legacy facts; provider facts above use real services. */
function sdrn_activity_rows(string $audience):array{
    $rows=[];$cursor='';$pages=0;
    do{
        if(++$pages>8)throw new RuntimeException('Unexpected activity pagination.');
        $page=activity_feed($audience,false,$cursor);
        if(count($page['rows'])>30)throw new RuntimeException('Activity page exceeded its bound.');
        array_push($rows,...$page['rows']);$cursor=$page['next'];
    }while($cursor!=='');
    return $rows;
}
function sdrn_activity_keys(string $audience):array{return array_column(sdrn_activity_rows($audience),'event_key');}
function sdrn_activity_provider_keys(string $audience):array{return array_values(array_filter(sdrn_activity_keys($audience),'activity_provider_key'));}
function sdrn_notifications(string $cancelId,string $cancelEvent,string $declineId,string $declineEvent,string $requestId,string $approvalId,array $review):void{
    global $dns,$gets,$posts;
    // These legacy tables have controller-only writers, not a callable service. Use the
    // same synthetic facts as activity_native, with real UTC timestamps and no edits.
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (1,?,?,?,?,?)',['Notification legacy project','Private summary','Private milestones',$now,$now]);$job=(int)db()->lastInsertId();
    $legacy=[];for($n=0;$n<101;$n++){
        query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,\'admin\',1,?,?)',[$job,'Synthetic private notification body '.$n,gmdate('Y-m-d H:i:s')]);$legacy[]='message:'.db()->lastInsertId();
    }
    query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,\'client\',1,?,?)',[$job,'Synthetic private client reply',gmdate('Y-m-d H:i:s')]);$reply='message:'.db()->lastInsertId();
    $terminal='subdomain-terminal:'.$approvalId;$requested='subdomain-request:'.$requestId;
    $ownerKeys=array_map(static fn($id)=>'subdomain-request:'.$id,[$cancelId,$cancelEvent,$declineId,$requestId]);
    $clientKeys=['subdomain-request:'.$declineEvent,'subdomain-request:'.$approvalId,$terminal];
    $checks=all('SELECT id,disposition FROM '.table('provider_subdomain_add_checks').' WHERE request_id=? ORDER BY id',[$approvalId]);
    foreach($checks as $row)$clientKeys[]='subdomain-check:'.$row['id'];
    sdrn_owner();$before=sdrn_digest();$io=[$dns,$gets,$posts];$ownerRows=sdrn_activity_rows('admin');
    $actual=array_values(array_filter(array_column($ownerRows,'event_key'),'activity_provider_key'));sort($actual);sort($ownerKeys);
    sdrn_ok('Notifications owner sees exact requests and withdrawal alongside client reply',$actual===$ownerKeys&&in_array($reply,array_column($ownerRows,'event_key'),true));
    sdrn_ok('Notifications owner exact link opens original client request',activity_destination('admin',$requested)===admin_url('subdomain-requests',['request_id'=>$requestId]));
    sdrn_client();$rows=sdrn_activity_rows('client');$keys=array_column($rows,'event_key');$expected=[...$legacy,...$clientKeys];sort($expected);$sorted=$keys;sort($sorted);
    $ordered=$rows;usort($ordered,static fn($a,$b)=>strcmp($b['occurred_at'],$a['occurred_at'])?:strcmp($b['event_key'],$a['event_key']));
    sdrn_ok('Notifications mixed paging visits every legacy and provider event exactly once',$sorted===$expected&&count(array_unique($keys))===count($keys)&&$rows===$ordered&&count($rows)>100&&activity_feed('client')['next']!=='');
    sdrn_ok('Notifications count caps healthy mixed unread history at one hundred',activity_count('client')===100&&activity_feed('client')['count']===100);
    sdrn_ok('Notifications client exact link opens original request without an implicit mark',activity_destination('client',$terminal)===studio_url(['subdomain_requests'=>1,'request_id'=>$requestId])&&sdrn_count('activity_reads')===0);
    $unknown=activity_source('client',$terminal);
    sdrn_ok('Notifications terminal remains uncertain after a later matching check',$unknown['kind']==='subdomain_uncertain'&&!str_contains(strtolower($unknown['item_title']),'success'));
    foreach($checks as $row){$source=activity_source('client','subdomain-check:'.$row['id']);sdrn_ok('Notifications retain separate '.$row['disposition'].' readback',$source['kind']==='subdomain_'.$row['disposition']&&$source['target_id']===$requestId);}
    $safe=json_encode([...$ownerRows,...$rows],JSON_THROW_ON_ERROR);$forbidden=[SDRN_SECRET,SDRN_PASSWORD,SDRN_OLD,'Please confirm the requested website name.','Synthetic private notification body','Private summary',$review['approval']['review_proof'],$review['request_sha256']];
    sdrn_ok('Notifications omit private messages notes paths credentials and proof fields',array_filter($forbidden,static fn($value)=>str_contains($safe,$value))===[]&&!str_contains($safe,'credential_sha256')&&!str_contains($safe,'request_sha256'));
    sdrn_ok('Notifications repeated feed source destination and count are read only',sdrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    activity_mark('client',[$legacy[0],$terminal],true);$readRows=all('SELECT * FROM '.table('activity_reads').' ORDER BY id');activity_mark('client',[$legacy[0],$terminal],true);
    sdrn_ok('Notifications mixed mark retry preserves first read rows and timestamps',count($readRows)===2&&all('SELECT * FROM '.table('activity_reads').' ORDER BY id')===$readRows);
    $unread=array_column(activity_feed('client',true)['rows'],'event_key');
    sdrn_ok('Notifications marked provider and legacy events leave unread view',!in_array($legacy[0],$unread,true)&&!in_array($terminal,$unread,true));
    activity_mark('client',[$legacy[0],$terminal],false);
    sdrn_owner();activity_mark('admin',[$requested,$reply],true);sdrn_owner(3);
    sdrn_ok('Notifications owner read states are independent',activity_count('admin')===5&&sdrn_count('activity_reads')===2);
    sdrn_owner();sdrn_ok('Notifications first owner retains its own read state',activity_count('admin')===3);activity_mark('admin',[$requested,$reply],false);
    sdrn_owner(2);sdrn_ok('Notifications permission rich nonowner sees legacy reply only',sdrn_activity_keys('admin')===[$reply]);sdrn_deny('Notifications nonowner cannot open provider key',fn()=>activity_destination('admin',$requested));
    sdrn_owner();$ownerAuth=one('SELECT auth_version FROM '.table('users').' WHERE id=1')['auth_version'];
    try{query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);sdrn_deny('Notifications stale owner session cannot read provider feed',fn()=>activity_feed('admin'));}
    finally{query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$ownerAuth]);sdrn_owner();}
    sdrn_client(2);sdrn_ok('Notifications foreign client has no provider event',sdrn_activity_provider_keys('client')===[]);sdrn_deny('Notifications foreign client cannot navigate original event',fn()=>activity_destination('client',$terminal));
    sdrn_client();$before=sdrn_digest();sdrn_deny('Notifications mixed unauthorized mark is atomic',fn()=>activity_mark('client',[$legacy[0],$requested],true));sdrn_ok('Notifications refused mixed mark leaves all rows and audits unchanged',sdrn_digest()===$before);
    foreach(['contact_id','contact_auth'] as $field){
        sdrn_client();$_SESSION[$field]=null;$providerKeys=sdrn_activity_provider_keys('client');
        sdrn_ok('Notifications raw '.$field.' presence excludes provider before normalization',$providerKeys===[]&&in_array($legacy[0],sdrn_activity_keys('client'),true));
        sdrn_deny('Notifications raw '.$field.' cannot open provider key',fn()=>activity_destination('client',$terminal));
    }
    sdrn_client();$_GET['support']='synthetic';sdrn_deny('Notifications support query cannot read feed',fn()=>activity_feed('client'));$_GET=[];
    $GLOBALS['cwp_support']=['client'=>one('SELECT * FROM '.table('studio_clients').' WHERE id=1')];sdrn_deny('Notifications resolved support cannot mark provider event',fn()=>activity_mark('client',[$terminal],true));unset($GLOBALS['cwp_support']);
    $original=one('SELECT active,auth_version FROM '.table('studio_clients').' WHERE id=1');
    foreach(['active'=>0,'auth_version'=>bin2hex(random_bytes(16))] as $field=>$value){
        try{
            query('UPDATE '.table('studio_clients').' SET '.$field.'=? WHERE id=1',[$value]);
            if($field==='auth_version')sdrn_deny('Notifications stale client session cannot read provider feed',fn()=>activity_feed('client'));
            sdrn_client();
            if($field==='active')sdrn_deny('Notifications deactivated primary client cannot read feed',fn()=>activity_feed('client'));
            else{sdrn_ok('Notifications new client credential does not inherit original provider event',sdrn_activity_provider_keys('client')===[]);sdrn_deny('Notifications rotated client credential cannot open original event',fn()=>activity_source('client',$terminal));}
        }finally{query('UPDATE '.table('studio_clients').' SET '.$field.'=? WHERE id=1',[$original[$field]]);sdrn_client();}
    }
    // Corrupt only the disposable namespace, preserve exact cell bytes, and restore
    // before continuing. No invented source repair and no nested mark transaction.
    $head=sdrn_head();$before=sdrn_digest();$io=[$dns,$gets,$posts];
    try{
        query('UPDATE '.table('settings').' SET value=? WHERE name=?',['damaged-head','provider_subdomain_requests_head']);unset($GLOBALS['cwp_settings']);
        $caught=false;try{activity_count('client');}catch(ActivityProviderUnavailable){$caught=true;}
        sdrn_ok('Notifications corrupt provider graph refuses count even above legacy cap',$caught);
        $feed=activity_feed('client');
        sdrn_ok('Notifications corrupt provider graph retains legacy page with unknown count',$feed['count']===null&&count($feed['rows'])===30&&count($feed['notices'])===1&&str_contains($feed['notices'][0],'temporarily unavailable')&&array_filter($feed['rows'],static fn($r)=>activity_provider_key($r['event_key']))===[]);
        sdrn_deny('Notifications corrupt provider graph refuses exact source',fn()=>activity_source('client',$terminal));
        sdrn_deny('Notifications corrupt provider graph refuses provider cursor',fn()=>activity_feed('client',false,$terminal));
        $damaged=sdrn_digest();sdrn_deny('Notifications corrupt provider mixed mark refuses atomically',fn()=>activity_mark('client',[$legacy[0],$terminal],true));
        sdrn_ok('Notifications corrupt mixed mark cannot change legacy read state or audit',sdrn_digest()===$damaged);
    }finally{query('UPDATE '.table('settings').' SET value=? WHERE name=?',[$head,'provider_subdomain_requests_head']);unset($GLOBALS['cwp_settings']);}
    sdrn_ok('Notifications exact restoration recovers all original sources without sends',sdrn_digest()===$before&&in_array($terminal,sdrn_activity_keys('client'),true)&&[$dns,$gets,$posts]===$io);
    sdrn_client();
}
if($worker)sdrn_worker();
$handle=fopen($report,'xb');if($handle===false)exit(2);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException('Native fixture PHP diagnostic',0,$severity,$file,$line);});
try{
    $stage='setup';$seed=sdrn_seed();extract($seed,EXTR_SKIP);
    sdrn_ok('Client request schema stays absent until explicit preparation',sdrn_screen()['ready']===false&&!sdrn_exists());
    sdrn_deny('Wrong password cannot enable client requests',fn()=>provider_subdomain_requests_prepare('wrong'),'Your current password is not correct.');
    sdrn_owner(2);sdrn_deny('Permission-rich staff cannot enable client requests',fn()=>provider_subdomain_requests_prepare(SDRN_PASSWORD));sdrn_owner();
    $_GET['support']='synthetic';sdrn_deny('Support route cannot enable client requests',fn()=>provider_subdomain_requests_prepare(SDRN_PASSWORD));$_GET=[];
    db()->beginTransaction();try{sdrn_deny('Preparation refuses an inherited transaction',fn()=>provider_subdomain_requests_prepare(SDRN_PASSWORD));sdrn_ok('Refused preparation leaves caller transaction active',db()->inTransaction());}finally{db()->rollBack();}
    db()->exec('CREATE TABLE sdrn_provider_subdomain_requests_unknown (id INT) ENGINE=InnoDB');
    try{sdrn_deny('Unknown request namespace is never adopted',fn()=>provider_subdomain_requests_prepare(SDRN_PASSWORD));}finally{db()->exec('DROP TABLE sdrn_provider_subdomain_requests_unknown');}
    db()->exec(provider_subdomain_requests_schema(SDRN_PREFIX));sdrn_ok('Exact empty unmarked request table is unprepared',sdrn_screen()['ready']===false);
    sdrn_ok('Real preparation audit fault leaves empty unmarked schema',sdrn_fault('prepare',fn()=>provider_subdomain_requests_prepare(SDRN_PASSWORD))&&sdrn_screen()['ready']===false);
    provider_subdomain_requests_prepare(SDRN_PASSWORD);$before=sdrn_digest();provider_subdomain_requests_prepare(SDRN_PASSWORD);
    sdrn_ok('Explicit preparation resumes and exact retry adds no history',sdrn_screen()['ready']===true&&sdrn_head()==='0:'.str_repeat('0',64)&&sdrn_digest()===$before);
    sdrn_ok('Client request setup does not initialize unrelated renewal or billing',one('SELECT name FROM '.table('settings').' WHERE name IN (?,?)',['domain_renewal_schema_version','recurring_billing_schema_version'])===null);

    $stage='primary-client';sdrn_client();$option=sdrn_option($link);$before=sdrn_digest();$io=[$dns,$gets,$posts];
    sdrn_ok('Primary client has no staff identity and sees only its actual attached parents',admin_user()===null&&count(sdrn_screen(1)['options'])===2&&array_unique(array_column(sdrn_screen(1)['options'],'package_id'))===[801]);
    sdrn_deny('Client ID argument cannot choose another primary account',fn()=>sdrn_screen(2));
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;sdrn_deny('Null '.$key.' provenance refuses before client normalization',fn()=>sdrn_screen(1),'Sign in to your primary client account.');unset($_SESSION[$key]);}
    $_GET['support']='synthetic';sdrn_deny('Support query cannot read client requests',fn()=>sdrn_screen(1));$_GET=[];
    $GLOBALS['cwp_support']=['id'=>'synthetic'];sdrn_deny('Resolved support context cannot create client intent',fn()=>sdrn_create(bin2hex(random_bytes(16)),$option,SDRN_NEW));unset($GLOBALS['cwp_support']);
    sdrn_deny('Client intent requires explicit confirmation',fn()=>provider_subdomain_requests_create(bin2hex(random_bytes(16)),1,$link['id'],$option['review_sha256'],SDRN_NAME,SDRN_NEW,false));
    sdrn_deny('Changed parent review hash refuses',fn()=>provider_subdomain_requests_create(bin2hex(random_bytes(16)),1,$link['id'],str_repeat('0',64),SDRN_NAME,SDRN_NEW,true));
    sdrn_deny('Unattached parent cannot become local intent',fn()=>provider_subdomain_requests_create(bin2hex(random_bytes(16)),1,$link['id'],$option['review_sha256'],'foreign.synthetic.invalid','shop.foreign.synthetic.invalid',true));
    sdrn_deny('Invalid child cannot become local intent',fn()=>sdrn_create(bin2hex(random_bytes(16)),$option,'https://shop.'.SDRN_NAME));
    $clientSession=$_SESSION;$_SESSION=[];sdrn_deny('Anonymous caller cannot read primary client requests',fn()=>sdrn_screen(1));$_SESSION=$clientSession;
    sdrn_ok('All rejected client inputs leave database and transport untouched',sdrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    $cancelId=bin2hex(random_bytes(16));$events=sdrn_count('provider_subdomain_requests_events');$audits=sdrn_event_audits();$head=sdrn_head();
    sdrn_ok('Real intake audit fault follows inserted request event',sdrn_fault('intake',fn()=>sdrn_create($cancelId,$option,SDRN_NEW)));
    sdrn_ok('Failed intake rolls event head and audit back before any provider work',sdrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    $created=sdrn_create($cancelId,$option,SDRN_NEW);
    sdrn_ok('Local intake appends exactly one event head and audit',$created['state']==='pending'&&$created['can_cancel']===true&&sdrn_count('provider_subdomain_requests_events')===$events+1&&sdrn_event_audits()===$audits+1&&sdrn_head()!==$head);
    sdrn_ok('Client request has no grant ledger DNS GET or POST',sdrn_count('provider_operation_grants')===0&&sdrn_count('provider_operations')===0&&[$dns,$gets,$posts]===$io);
    $original=sdrn_graph()['requests'][$cancelId]['request'];
    sdrn_ok('Immutable event retains real primary actor and original post-assignment package source',$original['actor']['kind']==='client'&&$original['actor']['id']===1&&$original['actor']['auth_sha256']===hash('sha256',(string)$_SESSION['client_auth'])&&$original['payload']['resource']['binding']['source_id']===$source['id']);
    $before=sdrn_digest();sdrn_ok('Exact pending create replay returns original item',sdrn_create($cancelId,$option,SDRN_NEW)===$created&&sdrn_digest()===$before);
    sdrn_deny('Same request identifier cannot change child',fn()=>sdrn_create($cancelId,$option,'changed.'.SDRN_NAME));
    sdrn_deny('Second pending local request cannot reserve same hostname',fn()=>sdrn_create(bin2hex(random_bytes(16)),$option,SDRN_NEW));
    sdrn_client(2);sdrn_ok('Other client sees only its own package and no foreign request',sdrn_screen(2)['items']===[]&&array_unique(array_column(sdrn_screen(2)['options'],'package_id'))===[802]);
    sdrn_deny('Other client cannot read retained foreign detail',fn()=>sdrn_item($cancelId,2));
    sdrn_deny('Other client cannot withdraw original request',fn()=>provider_subdomain_requests_change($cancelId,$cancelId,bin2hex(random_bytes(16)),'cancel','',2));

    $stage='withdraw-race';sdrn_owner();$cancelReview=sdrn_item($cancelId);$cancelEvent=bin2hex(random_bytes(16));
    $race=sdrn_race(sdrn_worker_input('approve',$cancelId,$cancelReview['approval']['request_id'],$cancelReview),function()use($cancelId,$cancelEvent):void{sdrn_client();provider_subdomain_requests_change($cancelId,$cancelId,$cancelEvent,'cancel','',1);},'Withdrawal-wins race');
    sdrn_ok('Blocked approval loses to committed withdrawal without provider work',$race===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&sdrn_item($cancelId,1)['state']==='cancelled'&&[$dns,$gets,$posts]===$io&&sdrn_count('provider_operation_grants')===0);
    $before=sdrn_digest();provider_subdomain_requests_change($cancelId,$cancelId,$cancelEvent,'cancel','',1);
    sdrn_ok('Exact cancel and original create retries preserve cancelled state',sdrn_create($cancelId,$option,SDRN_NEW)['state']==='cancelled'&&sdrn_digest()===$before);
    $declineId=bin2hex(random_bytes(16));sdrn_create($declineId,$option,'decline.'.SDRN_NAME);sdrn_owner();$declineEvent=bin2hex(random_bytes(16));
    $declined=provider_subdomain_requests_change($declineId,$declineId,$declineEvent,'decline','Please confirm the requested website name.',null,SDRN_PASSWORD);
    sdrn_ok('Owner decline records a safe note and no provider approval',$declined['state']==='declined'&&$declined['note']==='Please confirm the requested website name.'&&[$dns,$gets,$posts]===$io&&sdrn_count('provider_operation_grants')===0);
    $before=sdrn_digest();provider_subdomain_requests_change($declineId,$declineId,$declineEvent,'decline','Please confirm the requested website name.',null,SDRN_PASSWORD);sdrn_ok('Exact decline replay appends nothing',sdrn_digest()===$before);

    $stage='atomic-admission';sdrn_client();$mainId=bin2hex(random_bytes(16));sdrn_create($mainId,$option,SDRN_NEW);sdrn_owner();$review=sdrn_item($mainId);$dispatch=$review['approval']['request_id'];$before=sdrn_digest();$io=[$dns,$gets,$posts];
    sdrn_ok('Owner approval provides a separate signed owner form for exact pending request',is_array($review['approval'])&&$review['approval']['observed_epoch']===$anchor['observed_epoch']);
    sdrn_deny('Owner approval requires explicit confirmation',fn()=>provider_subdomain_requests_approve($mainId,$mainId,$review['request_sha256'],$dispatch,$review['approval']['review_proof'],false,SDRN_PASSWORD,'sdrn_write_dns','sdrn_perform'));
    sdrn_deny('Owner approval requires current password',fn()=>provider_subdomain_requests_approve($mainId,$mainId,$review['request_sha256'],$dispatch,$review['approval']['review_proof'],true,'wrong','sdrn_write_dns','sdrn_perform'),'Your current password is not correct.');
    sdrn_owner(2);sdrn_deny('Permission-rich staff cannot approve client subdomain',fn()=>sdrn_approve($mainId,$review));sdrn_owner();
    sdrn_ok('Late dispatch audit fault witnesses client event grant and owner request',sdrn_fault('dispatch',fn()=>sdrn_approve($mainId,$review)));
    sdrn_ok('Atomic admission failure restores all rows and performs no provider work',sdrn_digest()===$before&&[$dns,$gets,$posts]===$io&&sdrn_item($mainId)['state']==='pending');
    $duringPost=function()use($dispatch):void{
        global $capturedAuthorize;$session=$_SESSION;$digest=sdrn_digest();
        foreach(['client authentication','owner authentication','credential seal','assignment client'] as $case){
            if($case==='client authentication'){$tableName='studio_clients';$column='auth_version';$id=1;}
            elseif($case==='owner authentication'){$tableName='users';$column='auth_version';$id=1;}
            elseif($case==='credential seal'){$tableName='provider_profiles';$column='credentials';$id=one('SELECT profile_id FROM '.table('provider_subdomain_add_requests').' WHERE id=?',[$dispatch])['profile_id'];}
            else{$tableName='provider_package_links';$column='client_id';$id=one('SELECT link_id FROM '.table('provider_subdomain_add_requests').' WHERE id=?',[$dispatch])['link_id'];}
            $old=one('SELECT '.$column.' AS value FROM '.table($tableName).' WHERE id=?',[$id])['value'];
            query('UPDATE '.table($tableName).' SET '.$column.'=? WHERE id=?',[$column==='client_id'?2:bin2hex(random_bytes(16)),$id]);
            try{sdrn_deny('Actual credential and transport callback rejects changed '.$case,$capturedAuthorize);}
            finally{query('UPDATE '.table($tableName).' SET '.$column.'=? WHERE id=?',[$old,$id]);$_SESSION=$session;unset($GLOBALS['cwp_settings']);}
        }
        sdrn_ok('Boundary mutation probes restore exact original rows and real session',sdrn_digest()===$digest&&$_SESSION===$session);
    };
    $race=sdrn_race(sdrn_worker_input('cancel',$mainId,bin2hex(random_bytes(16)),$review),function()use($mainId,$review):void{sdrn_owner();sdrn_approve($mainId,$review);},'Approval-wins race');
    sdrn_ok('Blocked withdrawal loses to one atomic owner admission and send',$race===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&$posts===1&&$gets===$io[1]&&$dns===$io[0]+1);
    $ownerRequest=sdrn_locked(fn()=>provider_subdomain_add_request($dispatch));$dispatchEvent=sdrn_graph()['requests'][$mainId]['events'][0];$ledger=$ownerRequest['ledger'];
    sdrn_ok('Owner v2 approval and local dispatch mutually bind original client consent',$ownerRequest['document']['version']===2&&$ownerRequest['document']['client_origin']===['id'=>$mainId,'request_sha256'=>$review['request_sha256']]&&$dispatchEvent['id']===$dispatch&&$dispatchEvent['payload']['owner_request_sha256']===$ownerRequest['row']['document_sha256']);
    sdrn_ok('Actual gateway retains one unknown attempt and exact add-only wire',$ledger['status']==='uncertain'&&(int)$ledger['attempts']===1&&$wire===[['url'=>'https://api.20i.com/package/801/web/subdomains','body'=>'{"add":["shop.site801.synthetic.invalid"],"rem":[]}']]);
    $before=sdrn_digest();$io=[$dns,$gets,$posts];$approved=sdrn_approve($mainId,$review);
    sdrn_ok('Exact approval replay returns original unknown status without transport',$approved['state']==='unknown'&&$approved['owner_request_id']===$dispatch&&sdrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    sdrn_deny('Different owner request ID cannot reuse transitioned client request',fn()=>provider_subdomain_requests_approve($mainId,$mainId,$review['request_sha256'],bin2hex(random_bytes(16)),$review['approval']['review_proof'],true,SDRN_PASSWORD,'sdrn_write_dns','sdrn_perform'));
    sdrn_deny('Changed client origin hash cannot replay approval',fn()=>provider_subdomain_requests_approve($mainId,$mainId,str_repeat('0',64),$dispatch,$review['approval']['review_proof'],true,SDRN_PASSWORD,'sdrn_write_dns','sdrn_perform'));
    sdrn_client();$client=sdrn_item($mainId,1);
    sdrn_ok('Client sees unknown status with no private approval dispatch or folder paths',$client['state']==='unknown'&&$client['approval']===null&&$client['dispatch']===null&&$client['owner_request_id']===null&&$client['last_check']===null&&!str_contains(json_encode($client),'public_html'));
    sdrn_ok('Original intake replay after dispatch is local and exact',sdrn_create($mainId,$option,SDRN_NEW)===$client&&sdrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    sdrn_deny('Primary client cannot trigger owner verification',fn()=>provider_subdomain_requests_verify($mainId,$dispatch,SDRN_PASSWORD,'sdrn_read_dns','sdrn_read'));
    sdrn_deny('New client request cannot evade unknown same-host fence',fn()=>sdrn_create(bin2hex(random_bytes(16)),$option,SDRN_NEW));sdrn_owner();

    $stage='independent-readback';$readStatus=403;$readBody='null';$failed=sdrn_verify($mainId,$dispatch,$profile);
    sdrn_client();$failedClient=sdrn_item($mainId,1);sdrn_ok('Separate failed GET is unavailable and keeps unknown status and fence',$failed['state']==='unknown'&&$failedClient['last_check']['disposition']==='unavailable'&&sdrn_pending($link,SDRN_NEW)['id']===$dispatch);
    sdrn_owner();$readStatus=200;$readBody=json_encode([SDRN_NAME=>SDRN_OLD]);$different=sdrn_verify($mainId,$dispatch,$profile);
    sdrn_client();sdrn_ok('Separate complete map without hostname never releases unknown outcome',$different['last_check']['disposition']==='different'&&sdrn_item($mainId,1)['state']==='unknown'&&sdrn_pending($link,SDRN_NEW)['id']===$dispatch);
    sdrn_owner();$before=sdrn_digest();$io=[$dns,$gets,$posts];sdrn_approve($mainId,$review);sdrn_ok('Original approval proof still replays after newer map with zero effects',sdrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    $readBody=json_encode([SDRN_NAME=>SDRN_OLD,SDRN_NEW=>'']);$matched=sdrn_verify($mainId,$dispatch,$profile);
    sdrn_client();$matchedClient=sdrn_item($mainId,1);$before=sdrn_digest();$io=[$dns,$gets,$posts];sdrn_screen(1);
    sdrn_ok('Only separate exact hostname receipt yields observed client status',$matched['state']==='observed'&&$matchedClient['state']==='observed'&&array_keys($matchedClient['last_check'])===['disposition','recorded_epoch']&&$matchedClient['last_check']['disposition']==='observed'&&sdrn_pending($link,SDRN_NEW)===null);
    sdrn_ok('Client status and independent confirmation preserve provider ledger and all rows',sdrn_digest()===$before&&[$dns,$gets,$posts]===$io&&one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$ledger['id']])===$ledger&&$posts===1);

    $stage='notification-projections';sdrn_notifications($cancelId,$cancelEvent,$declineId,$declineEvent,$mainId,$dispatch,$review);

    $stage='stale-review-recovery';$recoveryId=bin2hex(random_bytes(16));$recoveryChild='recover.'.SDRN_NAME;sdrn_create($recoveryId,$option,$recoveryChild);sdrn_owner();$staleReview=sdrn_item($recoveryId);
    sdrn_map($link);$before=sdrn_digest();$io=[$dns,$gets,$posts];
    sdrn_deny('An old owner proof cannot newly admit after a genuine later map',fn()=>sdrn_approve($recoveryId,$staleReview));
    sdrn_ok('Stale approval refusal is local and leaves pending client intent',sdrn_digest()===$before&&[$dns,$gets,$posts]===$io&&sdrn_item($recoveryId)['state']==='pending');
    $recoveryReview=sdrn_item($recoveryId);$recoveryOwner=$recoveryReview['approval']['request_id'];$terminals=sdrn_count('provider_subdomain_add_terminals');
    sdrn_ok('Terminal audit fault follows real client dispatch and one additional send',sdrn_fault('terminal',fn()=>sdrn_approve($recoveryId,$recoveryReview))&&$posts===2);
    $interrupted=sdrn_item($recoveryId);$recoveryHash=$interrupted['dispatch']['recovery_ledger_sha256'];$recoveryLedger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['subdomainadd:'.$recoveryOwner]);
    sdrn_ok('Failed terminal transaction retains exact origin and finished ledger',$interrupted['state']==='interrupted'&&is_string($recoveryHash)&&strlen($recoveryHash)===64&&$recoveryLedger['status']==='uncertain'&&(int)$recoveryLedger['attempts']===1&&sdrn_count('provider_subdomain_add_terminals')===$terminals);
    sdrn_client();sdrn_ok('Notifications durable review survives failed terminal audit without invented terminal',in_array('subdomain-request:'.$recoveryOwner,sdrn_activity_keys('client'),true)&&!in_array('subdomain-terminal:'.$recoveryOwner,sdrn_activity_keys('client'),true));sdrn_owner();
    $before=sdrn_digest();$io=[$dns,$gets,$posts];sdrn_approve($recoveryId,$recoveryReview);
    sdrn_deny('Interrupted client request cannot verify before local witness recovery',fn()=>provider_subdomain_requests_verify($recoveryId,$recoveryOwner,SDRN_PASSWORD,'sdrn_read_dns','sdrn_read'));
    sdrn_deny('Recovery cannot attach a different owner approval',fn()=>provider_subdomain_requests_recover($recoveryId,$dispatch,$recoveryHash,SDRN_PASSWORD));
    sdrn_deny('Recovery requires exact finished ledger digest',fn()=>provider_subdomain_requests_recover($recoveryId,$recoveryOwner,str_repeat('0',64),SDRN_PASSWORD));
    sdrn_owner(3);sdrn_deny('Different actual owner cannot recover original client approval',fn()=>provider_subdomain_requests_recover($recoveryId,$recoveryOwner,$recoveryHash,SDRN_PASSWORD));sdrn_owner();
    sdrn_client();sdrn_deny('Client cannot use owner recovery',fn()=>provider_subdomain_requests_recover($recoveryId,$recoveryOwner,$recoveryHash,SDRN_PASSWORD));sdrn_owner();
    sdrn_ok('Interrupted replay and invalid reconciliation never resend or alter rows',sdrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    $recoveryBefore=sdrn_rows();$recovered=provider_subdomain_requests_recover($recoveryId,$recoveryOwner,$recoveryHash,SDRN_PASSWORD);
    sdrn_ok('Explicit local recovery appends only a terminal counter and audit without resend',$recovered['state']==='unknown'&&$recovered['dispatch']['can_verify']===true&&sdrn_count('provider_subdomain_add_terminals')===$terminals+1&&[$dns,$gets,$posts]===$io&&sdrn_recovery_delta($recoveryBefore,$recoveryOwner)&&one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$recoveryLedger['id']])===$recoveryLedger);
    sdrn_deny('Recovery cannot overwrite an existing terminal',fn()=>provider_subdomain_requests_recover($recoveryId,$recoveryOwner,$recoveryHash,SDRN_PASSWORD));
    sdrn_client();sdrn_ok('Notifications local recovery exposes exactly one uncertain terminal',count(array_keys(sdrn_activity_keys('client'),'subdomain-terminal:'.$recoveryOwner,true))===1&&activity_source('client','subdomain-terminal:'.$recoveryOwner)['kind']==='subdomain_uncertain');sdrn_owner();
    $readBody=json_encode([SDRN_NAME=>SDRN_OLD,SDRN_NEW=>'']);$readStatus=200;$different=sdrn_verify($recoveryId,$recoveryOwner,$profile);
    sdrn_ok('A fresh map lacking recovered hostname remains unknown and fenced',$different['state']==='unknown'&&$different['last_check']['disposition']==='different'&&sdrn_pending($link,$recoveryChild)['id']===$recoveryOwner&&$posts===2);

    $stage='origin-integrity';$check=one('SELECT * FROM '.table('provider_subdomain_add_checks').' WHERE request_id=? AND disposition=?',[$dispatch,'observed']);
    $mutations=[
        'client request'=>fn()=>query('DELETE FROM '.table('provider_subdomain_requests_events').' WHERE id=?',[$mainId]),
        'client dispatch'=>fn()=>query('DELETE FROM '.table('provider_subdomain_requests_events').' WHERE id=?',[$dispatch]),
        'paired dispatch and audit'=>function()use($dispatch):void{query('DELETE FROM '.table('provider_subdomain_requests_events').' WHERE id=?',[$dispatch]);query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.subdomain_requests_event','Subdomain request event '.$dispatch.' %']);},
        'event canonical payload'=>fn()=>query('UPDATE '.table('provider_subdomain_requests_events').' SET document=? WHERE id=?',['{}',$mainId]),
        'request head'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_subdomain_requests_head']),
        'request marker'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_subdomain_requests_schema_version']),
        'request preparation audit'=>fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=?',['provider.subdomain_requests_prepared']),
        'client event audit'=>fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.subdomain_requests_event','Subdomain request event '.$mainId.' %']),
        'owner request'=>function()use($dispatch):void{query('DELETE FROM '.table('provider_subdomain_add_checks').' WHERE request_id=?',[$dispatch]);query('DELETE FROM '.table('provider_subdomain_add_terminals').' WHERE id=?',[$dispatch]);query('DELETE FROM '.table('provider_subdomain_add_requests').' WHERE id=?',[$dispatch]);},
        'compact grant commitment'=>fn()=>query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',['{}',$dispatch]),
        'owner terminal'=>fn()=>query('DELETE FROM '.table('provider_subdomain_add_terminals').' WHERE id=?',[$dispatch]),
        'finished original ledger'=>fn()=>query('DELETE FROM '.table('provider_operations').' WHERE idempotency_key=?',['subdomainadd:'.$dispatch]),
        'paired confirmation and audit'=>function()use($check):void{query('DELETE FROM '.table('provider_subdomain_add_checks').' WHERE id=?',[$check['id']]);query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.subdomain_add_check','Subdomain add check '.$check['id'].' %']);},
        'independent map observation'=>fn()=>query('DELETE FROM '.table('provider_document_roots_observations').' WHERE id=?',[$check['receipt_id']]),
        'original inventory start audit'=>fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.refresh_started','Refresh '.$source['id'].' profile '.$profile.' revision 1']),
    ];
    foreach($mutations as $name=>$mutate)sdrn_damage('Missing or corrupt '.$name.' refuses complete history',$mutate);

    $stage='latest-inventory';$originalOption=$option;sdrn_wait($profile);$readStatus=403;$readBody='null';$failedInventory=provider_inventory_refresh_attempt($profile,1,SDRN_PASSWORD,'sdrn_read_dns','sdrn_read');
    sdrn_client();$before=sdrn_digest();$io=[$dns,$gets,$posts];
    sdrn_ok('Latest failed inventory hides new intake choices without older fallback',$failedInventory['state']==='unavailable'&&sdrn_screen(1)['options']===[]);
    sdrn_deny('Saved client form cannot create new intent through failed latest inventory',fn()=>sdrn_create(bin2hex(random_bytes(16)),$originalOption,'later.'.SDRN_NAME));
    sdrn_ok('Original immutable intake replay remains local after later failed inventory',sdrn_create($mainId,$originalOption,SDRN_NEW)['state']==='observed'&&sdrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    sdrn_owner();sdrn_wait($profile);$refreshCount=sdrn_count('provider_refreshes');
    try{provider_inventory_refresh_attempt($profile,1,SDRN_PASSWORD,static fn()=>throw new PDOException('Synthetic unfinished package intent'),'sdrn_read');sdrn_ok('Real package resolver interruption propagates after durable intent',false);}catch(PDOException){sdrn_ok('Real package resolver interruption propagates after durable intent',true);}
    sdrn_client();sdrn_ok('Genuine unfinished latest package intent suppresses new intake',sdrn_count('provider_refreshes')===$refreshCount+1&&sdrn_screen(1)['options']===[]);
    sdrn_deny('Unfinished latest package read cannot use an older client form',fn()=>sdrn_create(bin2hex(random_bytes(16)),$originalOption,'unfinished.'.SDRN_NAME));
    sdrn_owner();sdrn_wait($profile);$readStatus=200;$readBody=json_encode([sdrn_package(802)]);$omitted=provider_inventory_refresh_attempt($profile,1,SDRN_PASSWORD,'sdrn_read_dns','sdrn_read');sdrn_client();
    sdrn_ok('Complete latest inventory omitting package offers no intake choice',$omitted['state']==='complete'&&sdrn_screen(1)['options']===[]);
    sdrn_deny('Omitted package cannot use retained form for a new request',fn()=>sdrn_create(bin2hex(random_bytes(16)),$originalOption,'omitted.'.SDRN_NAME));
    sdrn_owner();sdrn_ok('Actual complete inventory restores new request choices',sdrn_hosting($profile)['state']==='complete');
    sdrn_client();$option=sdrn_option($link);sdrn_ok('New source changes new-form proof but preserves original replay',!hash_equals($option['review_sha256'],$originalOption['review_sha256'])&&sdrn_create($mainId,$originalOption,SDRN_NEW)['state']==='observed');

    $stage='current-client-boundary';sdrn_owner();sdrn_map($other);sdrn_client(2);$otherOption=sdrn_option($other,2);$revokedId=bin2hex(random_bytes(16));$otherChild='revoked.site802.synthetic.invalid';sdrn_create($revokedId,$otherOption,$otherChild,2);
    sdrn_owner();$revokedReview=sdrn_item($revokedId);$revokedOwner=$revokedReview['approval']['request_id'];$clientAuth=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=2')['auth_version'];$beforePosts=$posts;
    $duringDns=fn()=>query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=2',[bin2hex(random_bytes(16))]);
    try{$revoked=sdrn_approve($revokedId,$revokedReview);sdrn_ok('Client auth revoked during actual DNS boundary stops credentials and send',$revoked['state']==='not_sent'&&$revoked['current_assignment']===false&&$posts===$beforePosts&&one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['subdomainadd:'.$revokedOwner])===null);
        sdrn_deny('Changed original client auth refuses exact owner approval replay',fn()=>sdrn_approve($revokedId,$revokedReview));
    }finally{query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=2',[$clientAuth]);sdrn_owner();}
    sdrn_ok('Original client consent and truthful no-send terminal remain retained',sdrn_graph()['requests'][$revokedId]['request']['actor']['auth_sha256']===hash('sha256',$clientAuth)&&sdrn_pending($other,$otherChild)===null);
    sdrn_client(2);sdrn_ok('Notifications retained no-send witness is distinct from uncertain result',activity_source('client','subdomain-terminal:'.$revokedOwner)['kind']==='subdomain_not_sent');sdrn_owner();

    $stage='missing-completion';sdrn_map($other);sdrn_client(2);$missingId=bin2hex(random_bytes(16));$missingChild='interrupted.site802.synthetic.invalid';sdrn_create($missingId,$otherOption,$missingChild,2);sdrn_owner();$missingReview=sdrn_item($missingId);$missingOwner=$missingReview['approval']['request_id'];
    try{sdrn_approve($missingId,$missingReview,static fn()=>throw new PDOException('Synthetic interrupted resolver'));sdrn_ok('Real admitted resolver interruption propagates',false);}catch(PDOException){sdrn_ok('Real admitted resolver interruption propagates',true);}
    $missing=sdrn_item($missingId);$before=sdrn_digest();$io=[$dns,$gets,$posts];sdrn_approve($missingId,$missingReview);
    sdrn_deny('Never-finished request cannot verify',fn()=>provider_subdomain_requests_verify($missingId,$missingOwner,SDRN_PASSWORD,'sdrn_read_dns','sdrn_read'));
    sdrn_deny('Never-finished request cannot fabricate a terminal from missing ledger',fn()=>provider_subdomain_requests_recover($missingId,$missingOwner,str_repeat('0',64),SDRN_PASSWORD));
    sdrn_ok('Missing-ledger replay stays interrupted and fenced with no resend',$missing['state']==='interrupted'&&$missing['dispatch']['can_verify']===false&&$missing['dispatch']['recovery_ledger_sha256']===null&&sdrn_digest()===$before&&[$dns,$gets,$posts]===$io&&sdrn_pending($other,$missingChild)['id']===$missingOwner);

    sdrn_client(2);sdrn_ok('Notifications missing local terminal is not invented as an outcome',!in_array('subdomain-terminal:'.$missingOwner,sdrn_activity_keys('client'),true));sdrn_owner();
    $stage='changed-assignment';provider_settings_save($profile,1,'Changed synthetic client subdomain key',true,'SYNTHETIC_CHANGED_SUBDOMAIN_REQUEST_KEY',SDRN_PASSWORD);
    sdrn_client();sdrn_ok('Notifications changed provider credential hides original provider events but preserves legacy',sdrn_activity_provider_keys('client')===[]&&count(sdrn_activity_keys('client'))===101);sdrn_deny('Notifications changed provider credential denies stale exact link',fn()=>activity_destination('client','subdomain-terminal:'.$dispatch));sdrn_owner();
    sdrn_deny('Changed credentials refuse old client-origin dispatch replay',fn()=>sdrn_approve($recoveryId,$recoveryReview));
    sdrn_client();$historical=sdrn_item($recoveryId,1);sdrn_ok('Changed credentials retain safe unknown client history',$historical['state']==='unknown'&&$historical['current_assignment']===false&&$historical['dispatch']===null&&sdrn_pending($link,$recoveryChild)['id']===$recoveryOwner);
    sdrn_owner();provider_settings_save($profile,2,'Restored synthetic key with new revision',true,SDRN_SECRET,SDRN_PASSWORD);sdrn_ok('Replacement profile revision gets a real complete inventory',sdrn_hosting($profile)['state']==='complete');
    provider_inventory_unlink($link['id'],SDRN_PASSWORD);provider_inventory_link($profile,801,2,3,SDRN_PASSWORD);$replacement=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=801');
    sdrn_ok('Real post-reassignment inventory establishes new client source',sdrn_hosting($profile)['state']==='complete');sdrn_client(2);$newOption=sdrn_option($replacement,2);$before=sdrn_digest();$io=[$dns,$gets,$posts];
    sdrn_deny('Reassignment cannot evade unresolved exact-host fence',fn()=>sdrn_create(bin2hex(random_bytes(16)),$newOption,$recoveryChild,2));
    sdrn_deny('Reassigned client cannot read original client request',fn()=>sdrn_item($recoveryId,2));
    sdrn_ok('Same-host fence survives real key and client reassignment without writes',sdrn_pending($replacement,$recoveryChild)['id']===$recoveryOwner&&sdrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    sdrn_client();$old=sdrn_item($recoveryId,1);sdrn_ok('Original client retains historical status without current authority',$old['current_assignment']===false&&$old['state']==='unknown'&&$old['can_cancel']===false);
    sdrn_deny('Original client cannot reuse consent after actual reassignment',fn()=>sdrn_create($recoveryId,$originalOption,$recoveryChild));

    sdrn_client();sdrn_ok('Notifications reassignment cannot leave former client provider events',sdrn_activity_provider_keys('client')===[]);sdrn_client(2);sdrn_deny('Notifications reassignment cannot transfer original client notification',fn()=>activity_source('client','subdomain-terminal:'.$dispatch));
    $stage='established-history';sdrn_owner();db()->exec('RENAME TABLE '.table('provider_subdomain_requests_events').' TO sdrn_hidden_request_events');
    try{sdrn_deny('Missing established request table cannot be recreated',fn()=>provider_subdomain_requests_prepare(SDRN_PASSWORD));sdrn_ok('Failed setup leaves established table absent',!sdrn_exists());}finally{db()->exec('RENAME TABLE sdrn_hidden_request_events TO '.table('provider_subdomain_requests_events'));}
    $final=sdrn_locked(fn()=>provider_subdomain_requests_state());sdrn_ok('All six real requests retain their distinct immutable transitions',count($final['requests'])===6&&$final['requests'][$cancelId]['state']==='cancelled'&&$final['requests'][$declineId]['state']==='declined'&&$final['requests'][$mainId]['state']==='dispatched'&&$final['requests'][$recoveryId]['state']==='dispatched'&&$final['requests'][$revokedId]['state']==='dispatched'&&$final['requests'][$missingId]['state']==='dispatched');
    $public=json_encode([sdrn_screen(),sdrn_screen(null,1,$mainId),all('SELECT * FROM '.table('provider_subdomain_requests_events')),all('SELECT * FROM '.table('provider_subdomain_add_requests')),all('SELECT * FROM '.table('provider_operations')),all('SELECT * FROM '.table('audit'))],JSON_THROW_ON_ERROR);
    sdrn_ok('Retained evidence excludes synthetic credential password and bearer header',!str_contains($public,SDRN_SECRET)&&!str_contains($public,base64_encode(SDRN_SECRET))&&!str_contains($public,SDRN_PASSWORD)&&!str_contains($public,'Bearer '));
    sdrn_ok('Final state retains exactly two synthetic provider POSTs and both unresolved hostname fences',$posts===2&&count($wire)===2&&sdrn_pending($replacement,$recoveryChild)['id']===$recoveryOwner&&sdrn_pending($other,$missingChild)['id']===$missingOwner);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected native client subdomain failure at '.$stage,'passed'=>false];$failure=['stage'=>$stage,'class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null];}
finally{restore_error_handler();}
$passed=count($checks)>0&&!array_filter($checks,fn($r)=>$r['passed']!==true);
$result=['passed'=>$passed,'scope'=>'Actual isolated Linux SQL, primary-client request and owner gateway workflow with synthetic transport only. Genuine cooldowns and observed mutex races; no provider network, browser or live acceptance. A separately observed hostname is not DNS, health, causal creation or automatic retry authority.','checks'=>$checks,'failure'=>$failure??null];
$bytes=json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";if(fwrite($handle,$bytes)!==strlen($bytes)||!fflush($handle))exit(1);fclose($handle);echo ($passed?'PASS':'FAIL').': '.count($checks)." client subdomain native checks\n";exit($passed?0:1);
