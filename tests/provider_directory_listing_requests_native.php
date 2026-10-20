<?php
/** Isolated native SQL/owner/gateway acceptance. All provider responses are synthetic. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_DIRECTORY_REQUESTS')!=='1'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
$worker=count($argv)===2&&$argv[1]==='--worker';
if(count($argv)!==($worker?2:1))exit(2);
$port=getenv('CWP_TEST_PORT')?:'33306';if(!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/directory_listing_requests.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIDirectoryListing\{DirectoryListingBinding,DirectoryListingObservation,DirectoryListingReader};
use CajunWebPro\Foundation\TwentyI\Operations\{DirectoryIndexingChange,TwentyIOperationAdapter};

const DLRQ_PREFIX='dlrq_';
const DLRQ_PASSWORD='Synthetic client Directory listing owner passphrase';
const DLRQ_SECRET='SYNTHETIC_DIRECTORY_REQUEST_KEY';
const DLRQ_NAME='site801.synthetic.invalid';
$root=realpath(__DIR__.'/..');if(!$root)exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';$private=$runtime.'/directory-listing-requests-private';
$report=$qa.'/provider-directory-listing-requests-native-results.json';
foreach([$runtime,$qa] as $path){if(is_link($path)||(file_exists($path)&&!is_dir($path)))exit(2);if(!is_dir($path)&&!mkdir($path,0700))exit(2);}
if(is_link($private)||($worker?!is_dir($private):file_exists($private))||(!$worker&&(is_link($report)||file_exists($report))))exit(2);
if(!$worker&&!mkdir($private,0700))exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/directory-listing-requests','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-directory-listing-requests-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$port,'name'=>'cwp_test',
    'user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>DLRQ_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root.'/site';start_session();
$checks=[];$stage='preflight';$dns=0;$gets=0;$posts=0;$wire=[];$duringDns=null;$duringPost=null;$duringRead=null;$capturedAuthorize=null;$capturedRequest=null;
$readStatus=200;$readBody='false';

function dlrq_ok(string $name,bool $passed):void{global $checks;foreach($checks as $check)if($check['name']===$name)throw new LogicException('Duplicate fixture assertion.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function dlrq_deny(string $name,callable $call,?string $message=null):void{
    $caught=null;try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    dlrq_ok($name,$caught!==null&&($message===null||$caught->getMessage()===$message));
}
function dlrq_owner(int $id=1):void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function dlrq_client(int $id=1):void{
    $u=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);
    $_SESSION=['client_id'=>$id,'client_auth'=>$u['auth_version'],'client_login'=>time(),'client_last'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function dlrq_locked(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function dlrq_mutex():bool{
    $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.DLRQ_PREFIX),0,40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;
}
function dlrq_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function dlrq_exists():bool{return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY TABLE_NAME=?',[DLRQ_PREFIX.'provider_directory_listing_requests_events'])!==null;}
function dlrq_counts():array{global $dns,$gets,$posts;return [$dns,$gets,$posts,...array_map('dlrq_count',['provider_directory_listing_requests_events','provider_directory_changes','provider_directory_dispatch_ends','provider_directory_change_checks','provider_operation_grants','provider_operations','provider_refreshes','audit'])];}
function dlrq_rows():array{
    $rows=[];foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(DLRQ_PREFIX),DLRQ_PREFIX]) as $t){
        $name=$t['TABLE_NAME'];if(!preg_match('/\Adlrq_[a-z0-9_]+\z/',$name))throw new LogicException('Unexpected fixture table.');
        $data=all('SELECT * FROM `'.$name.'`');usort($data,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$data;
    }return $rows;
}
function dlrq_digest():string{return hash('sha256',json_encode(dlrq_rows(),JSON_THROW_ON_ERROR));}
/** A genuine bounded cooldown; no saved date, quota or clock is changed. */
function dlrq_wait(string $profile):void{
    if(db()->inTransaction()||dlrq_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');
    $deadline=microtime(true)+32;while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
function dlrq_launch(array $input):array{
    $process=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('Could not start client-request worker.');
    $json=json_encode($input,JSON_THROW_ON_ERROR);
    if(fwrite($pipes[0],$json)!==strlen($json))throw new RuntimeException('Could not send worker input.');
    fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    return ['process'=>$process,'pipes'=>$pipes,'stdout'=>'','stderr'=>'','connection'=>null];
}
/** Require a real blocked child GET_LOCK while this connection owns the exact mutex. */
function dlrq_blocked(array &$worker):void{
    if(!dlrq_mutex()||db()->inTransaction())throw new LogicException('Race observer needs owning mutex without transaction.');
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
            if($row&&stripos((string)$row['INFO'],'GET_LOCK')!==false&&stripos((string)$row['STATE'],'lock')!==false&&dlrq_mutex())return;
        }
        $status=proc_get_status($worker['process']);if(!$status['running'])throw new RuntimeException('Worker exited before observed lock contention.');
        if(microtime(true)>$deadline)throw new RuntimeException('Actual blocked worker was not observed within lock budget.');usleep(20000);
    }while(true);
}
function dlrq_join(array &$worker):array{
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
function dlrq_race(array $input,callable $winner,string $label):array{
    $child=null;try{staff_locked(function()use($input,$winner,$label,&$child):void{
        $child=dlrq_launch($input);dlrq_blocked($child);dlrq_ok($label.' observes a separate blocked GET_LOCK',true);$winner();
    });}finally{if($child!==null)$result=dlrq_join($child);}return $result;
}
function dlrq_boundary(callable $authorize):void{$authorize();if(db()->inTransaction()||!dlrq_mutex())throw new LogicException('Transport needs owning mutex outside transaction.');}
function dlrq_read_dns():string{global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';}
function dlrq_write_dns():string{
    global $dns,$duringDns;if(db()->inTransaction()||!dlrq_mutex())throw new LogicException('Write DNS needs owning mutex outside transaction.');
    $row=one('SELECT * FROM '.table('provider_directory_changes').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Missing durable approval before DNS.');$q=provider_directory_indexing_controls_request($row['id']);
    if($q['grant']['consumed_key']!=='directoryindexing:'.$row['id']||$q['ledger']!==null
        ||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',
            ['provider.directory_indexing_control_request','Directory listing change request '.$row['id'].' %'])->fetchColumn()!==1)throw new LogicException('Consumed approval audit must precede DNS.');
    if($q['document']['version']===2){$origin=$q['document']['client_origin'];
        if(!one('SELECT id FROM '.table('provider_directory_listing_requests_events').' WHERE id=? AND subject_id=? AND kind=?',[$row['id'],$origin['id'],'dispatch']))throw new LogicException('Client dispatch must commit with approval before DNS.');}
    $dns++;if($duringDns){$call=$duringDns;$duringDns=null;$call();}return '8.8.8.8';
}
final class DlrqReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readBody,$readStatus,$duringRead;dlrq_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null
            ||$request->headers!==['Authorization'=>'Bearer '.base64_encode(DLRQ_SECRET),'Accept'=>'application/json'])throw new LogicException('Unexpected fixed synthetic GET.');
        $gets++;if($duringRead){$call=$duringRead;$duringRead=null;$call();}return new TransportResponse($readStatus,[],$readBody);
    }
}
function dlrq_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?DirectoryListingBinding $directoryIndexingContext=null):InventorySnapshot|DirectoryListingObservation{
    dlrq_boundary($authorize);if($key!==DLRQ_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{dlrq_boundary($authorize);return $key;});
    if($operation===InventoryRead::PACKAGES_LIST)return InventoryReader::read(new DlrqReadTransport($authorize,'https://api.20i.com/package'),$credential,$operation,null,time());
    if($operation!==DirectoryListingReader::OPERATION||!$directoryIndexingContext)throw new LogicException('Wrong Directory listing read context.');
    return DirectoryListingReader::read(new DlrqReadTransport($authorize,'https://api.20i.com/package/'.$directoryIndexingContext->data['package_id'].'/web/directoryIndexing'),$credential,$directoryIndexingContext,time());
}
final class DlrqWriteTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$wire,$duringPost,$capturedRequest;dlrq_boundary($this->authorize);DirectoryIndexingChange::wire($request);
        if(!in_array($request->url,['https://api.20i.com/package/801/web/directoryIndexing','https://api.20i.com/package/802/web/directoryIndexing'],true)
            ||($request->headers['Authorization']??null)!=='Bearer '.base64_encode(DLRQ_SECRET))throw new LogicException('Wrong synthetic write account.');
        $capturedRequest=$request;$posts++;$wire[]=['url'=>$request->url,'body'=>$request->body];if($duringPost){$call=$duringPost;$duringPost=null;$call();}
        return new TransportResponse(200,[],'true');
    }
}
function dlrq_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    global $capturedAuthorize;$capturedAuthorize=$authorize;dlrq_boundary($authorize);
    if($key!==DLRQ_SECRET||$address!=='8.8.8.8'||$operation->name!==DirectoryIndexingChange::OPERATION)throw new LogicException('Wrong synthetic operation identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),static function()use($authorize,$key):string{dlrq_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new DlrqWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function dlrq_package(int $id):array{return ['id'=>$id,'name'=>'site'.$id.'.synthetic.invalid','names'=>['site'.$id.'.synthetic.invalid'],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];}
function dlrq_hosting(string $profile):array{
    global $readBody,$readStatus;dlrq_wait($profile);$readStatus=200;$readBody=json_encode(array_map('dlrq_package',[801,802]),JSON_THROW_ON_ERROR);
    return provider_inventory_refresh_attempt($profile,(int)provider_settings_load($profile)['revision'],DLRQ_PASSWORD,'dlrq_read_dns','dlrq_read');
}
function dlrq_flag(array $link,bool $value):array{
    global $readBody,$readStatus;dlrq_wait($link['profile_id']);$readStatus=200;$readBody=$value?'true':'false';
    $binding=provider_directory_indexing_binding($link['id']);$r=provider_directory_indexing_refresh($link['id'],$binding,DLRQ_PASSWORD,'dlrq_read_dns','dlrq_read');
    if($r['state']!=='complete')throw new RuntimeException('Synthetic flag did not complete.');return provider_directory_indexing_observation(provider_directory_indexing_source($r['id']));
}
function dlrq_screen(?int $client=null,?string $id=null):array{return provider_directory_indexing_requests_screen($client,1,$id);}
function dlrq_item(string $id,?int $client=null):array{
    $items=array_values(array_filter(dlrq_screen($client,$id)['items'],fn($r)=>$r['id']===$id));if(count($items)!==1)throw new RuntimeException('Expected original request.');return $items[0];
}
function dlrq_option(array $link,int $client=1):array{
    $items=array_values(array_filter(dlrq_screen($client)['options'],fn($r)=>$r['link_id']===$link['id']));if(count($items)!==1)throw new RuntimeException('Expected shared assigned package.');return $items[0];
}
function dlrq_create(string $id,array $option,bool $desired=true,int $client=1):array{return provider_directory_indexing_requests_create($id,$client,$option['link_id'],$option['review_sha256'],$desired,true);}
function dlrq_approve(string $id,array $item,?callable $resolver=null):array{
    $a=$item['approval'];return provider_directory_indexing_requests_approve($id,$item['latest_event'],$item['request_sha256'],$a['request_id'],$a['anchor_id'],$a['binding_sha256'],true,DLRQ_PASSWORD,$resolver??'dlrq_write_dns','dlrq_perform');
}
function dlrq_verify(string $id,string $ownerId,string $profile):array{dlrq_wait($profile);return provider_directory_indexing_requests_verify($id,$ownerId,DLRQ_PASSWORD,'dlrq_read_dns','dlrq_read');}
function dlrq_graph():array{return dlrq_locked(fn()=>provider_directory_indexing_requests_graph());}
function dlrq_pending(array $link):?array{return dlrq_locked(fn()=>provider_directory_indexing_controls_pending($link['profile_id'],(int)$link['package_id']));}
function dlrq_head():string{return (string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_directory_listing_requests_head'])['value']??'');}
function dlrq_event_audits():int{return (int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.directory_listing_requests_event'])->fetchColumn();}
/** Real INSERT failure and connection variables witness rows before rollback. */
function dlrq_fault(string $phase,callable $call):bool{
    $actions=['prepare'=>'provider.directory_listing_requests_prepared','intake'=>'provider.directory_listing_requests_event','dispatch'=>'provider.directory_listing_requests_event','terminal'=>'provider.directory_indexing_control_terminal'];
    if(!isset($actions[$phase]))throw new LogicException('Unknown fixture fault.');
    $tables=['provider_directory_listing_requests_events','provider_directory_changes','provider_operation_grants','provider_directory_dispatch_ends'];$before=array_map('dlrq_count',$tables);
    $sample='';foreach($tables as $i=>$t)$sample.='SET @dlrq_'.$i.'=(SELECT COUNT(*) FROM '.table($t).'); ';
    db()->exec('CREATE TRIGGER dlrq_audit_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='".$actions[$phase]."' THEN SET @dlrq_hit=COALESCE(@dlrq_hit,0)+1; ".$sample."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic Directory listing audit fault'; END IF; END");
    query('SET @dlrq_hit=0');$caught=false;try{$call();}catch(PDOException $e){
        $delta=match($phase){'prepare'=>[0,0,0,0],'intake'=>[1,0,0,0],'dispatch'=>[1,1,1,0],'terminal'=>[1,1,1,1]};
        $caught=$e->getCode()==='45000'&&(int)query('SELECT @dlrq_hit')->fetchColumn()===1;
        foreach($before as $i=>$n)$caught=$caught&&(int)query('SELECT @dlrq_'.$i)->fetchColumn()===$n+$delta[$i];
    }finally{db()->exec('DROP TRIGGER dlrq_audit_fault');}return $caught;
}
function dlrq_damage(string $name,callable $mutate):void{
    global $dns,$gets,$posts;$before=dlrq_digest();$io=[$dns,$gets,$posts];$session=$_SESSION;
    staff_locked(function()use($name,$mutate):void{db()->beginTransaction();try{$mutate();unset($GLOBALS['cwp_settings']);dlrq_deny($name,fn()=>provider_directory_indexing_requests_state());}finally{if(db()->inTransaction())db()->rollBack();}});
    $_SESSION=$session;unset($GLOBALS['cwp_settings']);dlrq_ok($name.' exact rollback restores history',dlrq_digest()===$before&&[$dns,$gets,$posts]===$io&&is_array(dlrq_locked(fn()=>provider_directory_indexing_requests_state())));
}
function dlrq_recovery_delta(array $before,string $id):bool{
    $after=dlrq_rows();$terminal=one('SELECT * FROM '.table('provider_directory_dispatch_ends').' WHERE id=?',[$id]);if(!$terminal)return false;
    $detail='Directory listing change terminal '.$id.' '.provider_directory_indexing_digest(provider_directory_indexing_controls_values('terminal',$terminal));
    foreach(['provider_directory_dispatch_ends'=>fn($r)=>$r['id']===$id,'audit'=>fn($r)=>$r['action']==='provider.directory_indexing_control_terminal'&&$r['detail']===$detail&&(int)$r['user_id']===1] as $name=>$match){
        $key=DLRQ_PREFIX.$name;if(count(array_filter($after[$key],$match))!==1||array_filter($before[$key],$match)!==[])return false;$after[$key]=array_values(array_filter($after[$key],fn($r)=>!$match($r)));
    }
    $name='provider_directory_indexing_control_terminal_count';$key=DLRQ_PREFIX.'settings';$old=array_values(array_filter($before[$key],fn($r)=>$r['name']===$name));if(count($old)!==1)return false;$found=0;
    foreach($after[$key] as &$row)if($row['name']===$name){if((int)$row['value']!==(int)$old[0]['value']+1)return false;$row=$old[0];$found++;}unset($row);if($found!==1)return false;
    foreach($after as &$rows)usort($rows,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));unset($rows);return $after===$before;
}
/** Disposable negative fixture: authenticated attempted terminal, but no eligible finished ledger.
 * Rebind only its terminal proof/audit so verification reaches the durable-completion gate;
 * finally restore the exact original ledger, terminal and audit. No production recovery uses this. */
function dlrq_unfinished_verify(string $id,array $ledger,string $mode):void{
    global $dns,$gets,$posts;
    if(!in_array($mode,['missing','claimed'],true))throw new LogicException('Unknown incomplete ledger fixture.');
    $before=dlrq_digest();$io=[$dns,$gets,$posts];
    $terminal=one('SELECT * FROM '.table('provider_directory_dispatch_ends').' WHERE id=?',[$id]);
    $detail='Directory listing change terminal '.$id.' '.provider_directory_indexing_digest(provider_directory_indexing_controls_values('terminal',$terminal));
    $audit=one('SELECT * FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.directory_indexing_control_terminal',$detail]);
    if(!$audit)throw new LogicException('Original terminal audit absent.');
    $replacement=null;if($mode==='claimed'){$replacement=$ledger;foreach(['operation','provider_code','external_id','resolved_epoch','reconciled_outcome','reconciled_epoch'] as $key)$replacement[$key]=null;$replacement['status']='claimed';$replacement['result_message']='';$replacement['attempts']=0;}
    $write=static function(?array $row,array $end,string $auditDetail)use($ledger,$id,$audit):void{
        query('DELETE FROM '.table('provider_operations').' WHERE id=?',[$ledger['id']]);
        if($row)query('INSERT INTO '.table('provider_operations').' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
        query('UPDATE '.table('provider_directory_dispatch_ends').' SET ledger_sha256=?,document_json=?,document_sha256=?,proof=? WHERE id=?',[$end['ledger_sha256'],$end['document_json'],$end['document_sha256'],$end['proof'],$id]);
        query('UPDATE '.table('audit').' SET detail=? WHERE id=?',[$auditDetail,$audit['id']]);
    };
    $end=$terminal;$d=json_decode($end['document_json'],true,20,JSON_THROW_ON_ERROR);$d['ledger_sha256']=provider_directory_indexing_controls_ledger_digest($replacement);
    $end['ledger_sha256']=$d['ledger_sha256'];$end['document_json']=provider_directory_indexing_json($d);$end['document_sha256']=hash('sha256',$end['document_json']);unset($end['proof']);$end=provider_directory_indexing_controls_prove('terminal',$end);
    $negativeDetail='Directory listing change terminal '.$id.' '.provider_directory_indexing_digest(provider_directory_indexing_controls_values('terminal',$end));
    try{
        dlrq_locked(fn()=>$write($replacement,$end,$negativeDetail));
        dlrq_ok('Authenticated attempted terminal with '.$mode.' ledger remains structurally readable',is_array(dlrq_locked(fn()=>provider_directory_indexing_requests_state())));
        dlrq_deny('Crafted v2 verification refuses '.$mode.' ledger before DNS',fn()=>provider_directory_indexing_controls_verify($id,DLRQ_PASSWORD,'dlrq_read_dns','dlrq_read'),'This request has no eligible durable dispatch completion.');
    }finally{dlrq_locked(fn()=>$write($ledger,$terminal,$detail));}
    dlrq_ok('Incomplete '.$mode.' ledger probe restores exact rows without IO',dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);
}
function dlrq_worker_input(string $action,string $id,string $event,array $item):array{
    return ['action'=>$action,'id'=>$id,'expected'=>$item['latest_event'],'event'=>$event,'request_sha256'=>$item['request_sha256'],'anchor_id'=>$item['approval']['anchor_id'],'binding_sha256'=>$item['approval']['binding_sha256']];
}
function dlrq_worker():never{
    global $dns,$gets,$posts;try{
        $in=json_decode(stream_get_contents(STDIN,8193),true,6,JSON_THROW_ON_ERROR);
        if(!is_array($in)||array_keys($in)!==['action','id','expected','event','request_sha256','anchor_id','binding_sha256']||!in_array($in['action'],['cancel','approve','approve-owner-revoked','approve-client-revoked'],true))throw new RuntimeException('Invalid worker command.');
        foreach(['id','expected','event','anchor_id'] as $key)if(!is_string($in[$key])||!preg_match('/\A[a-f0-9]{32}\z/',$in[$key]))throw new RuntimeException('Invalid worker identity.');
        foreach(['request_sha256','binding_sha256'] as $key)if(!is_string($in[$key])||!preg_match('/\A[a-f0-9]{64}\z/',$in[$key]))throw new RuntimeException('Invalid worker proof.');
        if($in['action']==='cancel')dlrq_client();else dlrq_owner();
        echo json_encode(['ready'=>true,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR)."\n";flush();$outcome='completed';
        for($attempt=1;;$attempt++)try{if($in['action']==='cancel')provider_directory_indexing_requests_change($in['id'],$in['expected'],$in['event'],'cancel','',1);
            else provider_directory_indexing_requests_approve($in['id'],$in['expected'],$in['request_sha256'],$in['event'],$in['anchor_id'],$in['binding_sha256'],true,DLRQ_PASSWORD,'dlrq_write_dns','dlrq_perform');
            break;
        }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;
            if($e->getMessage()==='Another account change is in progress. Please retry.'&&$attempt<3)continue;
            $message=match($in['action']){
                'cancel'=>'This directory listing request changed. Reload its review.',
                'approve-owner-revoked'=>'An active owner account is required for provider settings.',
                'approve-client-revoked'=>'The client account changed. Ask for a new directory listing request.',
                default=>'This request was already reviewed or withdrawn.',
            };
            if($e->getMessage()!==$message)throw $e;$outcome='refused';}
        echo json_encode(['outcome'=>$outcome,'dns'=>$dns,'gets'=>$gets,'posts'=>$posts],JSON_THROW_ON_ERROR)."\n";exit;
    }catch(Throwable $e){echo json_encode(['failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR)."\n";exit(1);}
}
function dlrq_seed():array{
    dlrq_ok('Dedicated native namespace begins empty',(string)query('SELECT DATABASE()')->fetchColumn()==='cwp_test'&&(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(DLRQ_PREFIX),DLRQ_PREFIX])->fetchColumn()===0);
    foreach(schema_statements(DLRQ_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $k=>$v)save_setting($k,$v);
    foreach([1,2,3] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Directory listing requests staff '.$id,'dlrq'.$id.'@example.invalid',password_hash(DLRQ_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Directory listing requests client '.$id,'dlrq-client'.$id.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    dlrq_owner();provider_settings_migrate(DLRQ_PASSWORD);provider_inventory_migrate(DLRQ_PASSWORD);provider_operations_migrate(DLRQ_PASSWORD);
    $profile=provider_settings_save('',0,'Client Directory listing request fixture',true,DLRQ_SECRET,DLRQ_PASSWORD);provider_directory_indexing_prepare(DLRQ_PASSWORD);provider_directory_indexing_controls_prepare(DLRQ_PASSWORD);
    dlrq_ok('Real initial package reader completes with synthetic transport',dlrq_hosting($profile)['state']==='complete');
    foreach([801,802] as $package)provider_inventory_link($profile,$package,$package===802?2:1,1,DLRQ_PASSWORD);
    $source=dlrq_hosting($profile);dlrq_ok('Real post-assignment package read completes',$source['state']==='complete');
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=801');$other=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=802');
    $oldAnchor=dlrq_flag($other,false);$oldBinding=provider_directory_indexing_binding($other['id']);$oldId=bin2hex(random_bytes(16));
    $old=provider_directory_indexing_controls_change($oldId,$other['id'],$oldAnchor['id'],$oldBinding->sha256,true,true,DLRQ_PASSWORD,'dlrq_write_dns','dlrq_perform');
    dlrq_wait($profile);$GLOBALS['readBody']='true';$old=provider_directory_indexing_controls_verify($oldId,DLRQ_PASSWORD,'dlrq_read_dns','dlrq_read');
    dlrq_ok('Existing owner-only v1 flow dispatches and observes before client preparation',$old['status']==='observed'&&dlrq_locked(fn()=>provider_directory_indexing_controls_request($oldId))['document']['version']===1&&!dlrq_exists());
    $anchor=dlrq_flag($link,false);return compact('profile','source','link','other','anchor','oldId');
}
function dlrq_old_rows(string $id):array{
    return [one('SELECT * FROM '.table('provider_directory_changes').' WHERE id=?',[$id]),one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]),
        one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['directoryindexing:'.$id]),one('SELECT * FROM '.table('provider_directory_dispatch_ends').' WHERE id=?',[$id]),
        all('SELECT * FROM '.table('provider_directory_change_checks').' WHERE request_id=? ORDER BY id',[$id])];
}
if($worker)dlrq_worker();
$handle=fopen($report,'xb');if($handle===false)exit(2);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException('Native fixture PHP diagnostic',0,$severity,$file,$line);});
try{
    $stage='setup';$seed=dlrq_seed();extract($seed,EXTR_SKIP);$legacy=dlrq_old_rows($oldId);
    dlrq_ok('Request schema remains absent on owner GET before explicit preparation',dlrq_screen()['ready']===false&&!dlrq_exists());
    dlrq_deny('Wrong password cannot prepare client request history',fn()=>provider_directory_indexing_requests_prepare('wrong'),'Your current password is not correct.');
    dlrq_owner(2);dlrq_deny('Permission-rich staff cannot prepare client requests',fn()=>provider_directory_indexing_requests_prepare(DLRQ_PASSWORD));dlrq_owner();
    db()->beginTransaction();try{dlrq_deny('Preparation refuses inherited transaction',fn()=>provider_directory_indexing_requests_prepare(DLRQ_PASSWORD));dlrq_ok('Refused preparation preserves caller transaction',db()->inTransaction());}finally{db()->rollBack();}
    db()->exec('CREATE TABLE dlrq_provider_directory_listing_requests_unknown (id INT) ENGINE=InnoDB');
    try{dlrq_deny('Unknown namespace table cannot be adopted',fn()=>provider_directory_indexing_requests_prepare(DLRQ_PASSWORD));}finally{db()->exec('DROP TABLE dlrq_provider_directory_listing_requests_unknown');}
    db()->exec('CREATE TABLE dlrq_provider_directory_listing_requests_events (id INT) ENGINE=InnoDB');
    try{dlrq_deny('Malformed empty request table cannot be silently upgraded',fn()=>provider_directory_indexing_requests_prepare(DLRQ_PASSWORD));dlrq_ok('Malformed table remains unchanged after refusal',count(all('SHOW COLUMNS FROM dlrq_provider_directory_listing_requests_events'))===1);}finally{db()->exec('DROP TABLE dlrq_provider_directory_listing_requests_events');}
    db()->exec(provider_directory_indexing_requests_schema(DLRQ_PREFIX));dlrq_ok('Exact empty interrupted schema stays unprepared',dlrq_screen()['ready']===false);
    dlrq_ok('Real preparation audit fault rolls marker and head back',dlrq_fault('prepare',fn()=>provider_directory_indexing_requests_prepare(DLRQ_PASSWORD))&&dlrq_screen()['ready']===false);
    provider_directory_indexing_requests_prepare(DLRQ_PASSWORD);$before=dlrq_digest();provider_directory_indexing_requests_prepare(DLRQ_PASSWORD);
    dlrq_ok('Explicit setup resumes exact schema and repeated setup is inert',dlrq_screen()['ready']===true&&dlrq_head()==='0:'.str_repeat('0',64)&&dlrq_digest()===$before);
    dlrq_ok('Existing v1 owner history survives new client storage preparation',dlrq_old_rows($oldId)===$legacy);
    $before=dlrq_digest();$io=[$dns,$gets,$posts];
    dlrq_deny('Internal send refuses a caller without owning mutex',fn()=>provider_directory_indexing_controls_send_locked($oldId,'dlrq_write_dns','dlrq_perform'),'Directory listing dispatch requires the owning staff mutex.');
    dlrq_ok('Unowned internal dispatch does not change original history',dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);

    $stage='sharing-primary-client';dlrq_client();$before=dlrq_digest();$io=[$dns,$gets,$posts];
    dlrq_ok('Assigned client gets no choices before independent observation sharing',dlrq_screen(1)['options']===[]&&admin_user()===null);
    dlrq_ok('Unshared client GET performs no writes or provider work',dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    dlrq_owner();$share=provider_directory_indexing_share($link['id'],'',bin2hex(random_bytes(16)),true,$anchor['id'],DLRQ_PASSWORD);
    dlrq_client();$option=dlrq_option($link);$before=dlrq_digest();$io=[$dns,$gets,$posts];
    dlrq_ok('Explicit shared current assignment enables only original primary client option',count(dlrq_screen(1)['options'])===1&&$option['package_id']===801);
    dlrq_deny('Client cannot select another client identity',fn()=>dlrq_screen(2));
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;dlrq_deny('Raw null '.$key.' refuses before normalization',fn()=>dlrq_screen(1));unset($_SESSION[$key]);}
    $_GET['support']='synthetic';dlrq_deny('Support query cannot read client request history',fn()=>dlrq_screen(1));$_GET=[];
    $GLOBALS['cwp_support']=['id'=>'synthetic'];dlrq_deny('Resolved support cannot create client request',fn()=>dlrq_create(bin2hex(random_bytes(16)),$option));unset($GLOBALS['cwp_support']);
    $session=$_SESSION;$_SESSION=[];dlrq_deny('Anonymous caller cannot read client requests',fn()=>dlrq_screen(1));$_SESSION=$session;
    dlrq_deny('Request needs explicit consent',fn()=>provider_directory_indexing_requests_create(bin2hex(random_bytes(16)),1,$link['id'],$option['review_sha256'],true,false));
    dlrq_deny('Changed reviewed resource hash refuses',fn()=>provider_directory_indexing_requests_create(bin2hex(random_bytes(16)),1,$link['id'],str_repeat('0',64),true,true));
    dlrq_ok('Rejected client inputs leave all rows and transport untouched',dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    $cancelId=bin2hex(random_bytes(16));$count=dlrq_count('provider_directory_listing_requests_events');$audits=dlrq_event_audits();
    dlrq_ok('Real intake audit failure witnesses newly inserted event',dlrq_fault('intake',fn()=>dlrq_create($cancelId,$option)));
    dlrq_ok('Intake audit rollback leaves no intent grant or provider effect',dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    $created=dlrq_create($cancelId,$option);$original=dlrq_graph()['requests'][$cancelId]['request'];
    dlrq_ok('Primary client intake retains bool desired and actual auth with sharing source',$created['state']==='pending'&&$created['can_cancel']===true
        &&$original['payload']['desired']===true&&$original['actor']['kind']==='client'&&$original['actor']['id']===1
        &&$original['actor']['auth_sha256']===hash('sha256',(string)$_SESSION['client_auth'])
        &&$original['payload']['resource']['binding']['source_id']===$source['id']&&$original['payload']['resource']['sharing_id']===$share['document']['id']);
    dlrq_ok('Local request appends only one event audit and no owner grant or IO',dlrq_count('provider_directory_listing_requests_events')===$count+1&&dlrq_event_audits()===$audits+1&&dlrq_count('provider_operation_grants')===1&&[$dns,$gets,$posts]===$io);
    $before=dlrq_digest();dlrq_ok('Exact client create replay is inert',dlrq_create($cancelId,$option)===$created&&dlrq_digest()===$before);
    dlrq_deny('Existing request identity cannot change desired flag',fn()=>dlrq_create($cancelId,$option,false));
    dlrq_deny('Duplicate pending package request cannot reserve another nonce',fn()=>dlrq_create(bin2hex(random_bytes(16)),$option));
    dlrq_client(2);dlrq_ok('Unshared foreign client has no options or foreign items',dlrq_screen(2)['options']===[]&&dlrq_screen(2)['items']===[]);
    dlrq_deny('Foreign primary client cannot read request detail',fn()=>dlrq_item($cancelId,2));
    dlrq_deny('Foreign primary client cannot withdraw request',fn()=>provider_directory_indexing_requests_change($cancelId,$cancelId,bin2hex(random_bytes(16)),'cancel','',2));

    $stage='withdraw-decline';dlrq_owner();$cancelReview=dlrq_item($cancelId);$cancelEvent=bin2hex(random_bytes(16));
    $race=dlrq_race(dlrq_worker_input('approve',$cancelId,$cancelReview['approval']['request_id'],$cancelReview),function()use($cancelId,$cancelEvent):void{dlrq_client();provider_directory_indexing_requests_change($cancelId,$cancelId,$cancelEvent,'cancel','',1);},'Withdrawal-wins race');
    dlrq_ok('Blocked approval loses to actual withdrawal with no send',$race===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&dlrq_item($cancelId,1)['state']==='cancelled'&&[$dns,$gets,$posts]===$io);
    $before=dlrq_digest();provider_directory_indexing_requests_change($cancelId,$cancelId,$cancelEvent,'cancel','',1);
    dlrq_ok('Cancel and original create retries retain terminal client decision',dlrq_create($cancelId,$option)['state']==='cancelled'&&dlrq_digest()===$before);
    $declineId=bin2hex(random_bytes(16));dlrq_create($declineId,$option);dlrq_owner();$declineEvent=bin2hex(random_bytes(16));
    $declined=provider_directory_indexing_requests_change($declineId,$declineId,$declineEvent,'decline','Please review which files should be publicly listed first.',null,DLRQ_PASSWORD);
    dlrq_ok('Owner decline records explanation without provider work',$declined['state']==='declined'&&$declined['note']==='Please review which files should be publicly listed first.'&&[$dns,$gets,$posts]===$io);
    $before=dlrq_digest();provider_directory_indexing_requests_change($declineId,$declineId,$declineEvent,'decline','Please review which files should be publicly listed first.',null,DLRQ_PASSWORD);
    dlrq_ok('Exact decline retry is inert',dlrq_digest()===$before);

    $stage='fresh-owner-admission';dlrq_client();$mainId=bin2hex(random_bytes(16));dlrq_create($mainId,$option);dlrq_owner();$stale=dlrq_item($mainId);
    dlrq_ok('Ordinary fresh inventory retains pending static client assignment',dlrq_hosting($profile)['state']==='complete');$anchor=dlrq_flag($link,false);$before=dlrq_digest();$io=[$dns,$gets,$posts];
    dlrq_deny('Old owner anchor cannot newly admit after genuine later reads',fn()=>dlrq_approve($mainId,$stale));
    $review=dlrq_item($mainId);$approval=$review['approval'];$dispatch=$approval['request_id'];
    dlrq_ok('Fresh owner review uses new exact flag while retaining original client source',$review['state']==='pending'&&$approval['anchor_id']===$anchor['id']&&$approval['desired']===true&&$approval['before_value']===false&&dlrq_graph()['requests'][$mainId]['request']['payload']['resource']['binding']['source_id']===$source['id']);
    foreach(['owner'=>['staff_profiles','user_id','enabled',0], 'client'=>['studio_clients','id','auth_version',bin2hex(random_bytes(16))]] as $kind=>[$tableName,$pk,$column,$changed]){
        $originalCell=one('SELECT '.$column.' AS value FROM '.table($tableName).' WHERE '.$pk.'=1')['value'];
        try{$blocked=dlrq_race(dlrq_worker_input('approve-'.$kind.'-revoked',$mainId,$dispatch,$review),
            fn()=>query('UPDATE '.table($tableName).' SET '.$column.'=? WHERE '.$pk.'=1',[$changed]),'Current '.$kind.' authority race');}
        finally{query('UPDATE '.table($tableName).' SET '.$column.'=? WHERE '.$pk.'=1',[$originalCell]);dlrq_owner();}
        dlrq_ok('Blocked owner admission rechecks '.$kind.' authority after acquiring mutex',$blocked===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&dlrq_digest()===$before&&[$dns,$gets,$posts]===$io&&dlrq_item($mainId)['state']==='pending');
    }
    dlrq_deny('Owner dispatch needs confirmation',fn()=>provider_directory_indexing_requests_approve($mainId,$mainId,$review['request_sha256'],$dispatch,$approval['anchor_id'],$approval['binding_sha256'],false,DLRQ_PASSWORD,'dlrq_write_dns','dlrq_perform'));
    dlrq_deny('Owner dispatch needs current password',fn()=>provider_directory_indexing_requests_approve($mainId,$mainId,$review['request_sha256'],$dispatch,$approval['anchor_id'],$approval['binding_sha256'],true,'wrong','dlrq_write_dns','dlrq_perform'),'Your current password is not correct.');
    dlrq_owner(2);dlrq_deny('Ordinary staff cannot approve client request',fn()=>dlrq_approve($mainId,$review));dlrq_owner();
    dlrq_ok('Late dispatch event audit sees both new owner request and consumed grant',dlrq_fault('dispatch',fn()=>dlrq_approve($mainId,$review)));
    dlrq_ok('Failed atomic admission restores all rows and leaves pending intent',dlrq_digest()===$before&&[$dns,$gets,$posts]===$io&&dlrq_item($mainId)['state']==='pending');
    $duringPost=function()use($dispatch):void{
        global $capturedAuthorize;$session=$_SESSION;$digest=dlrq_digest();
        $request=provider_directory_indexing_controls_request($dispatch);
        foreach(['client authentication'=>['studio_clients','auth_version',1], 'owner authentication'=>['users','auth_version',1],
            'owner disabled'=>['staff_profiles','enabled',1], 'credential seal'=>['provider_profiles','credentials',$request['row']['profile_id']],
            'assignment client'=>['provider_package_links','client_id',$request['row']['link_id']]] as $label=>[$name,$column,$id]){
            $pk=$name==='staff_profiles'?'user_id':'id';$old=one('SELECT '.$column.' AS value FROM '.table($name).' WHERE '.$pk.'=?',[$id])['value'];
            $new=match($column){'client_id'=>2,'enabled'=>0,default=>bin2hex(random_bytes(16))};query('UPDATE '.table($name).' SET '.$column.'=? WHERE '.$pk.'=?',[$new,$id]);
            try{dlrq_deny('Real credential-send callback denies changed '.$label,$capturedAuthorize);}finally{query('UPDATE '.table($name).' SET '.$column.'=? WHERE '.$pk.'=?',[$old,$id]);$_SESSION=$session;unset($GLOBALS['cwp_settings']);}
        }
        dlrq_ok('Boundary probes restore exact rows and current owner session',dlrq_digest()===$digest&&$_SESSION===$session);
    };
    $race=dlrq_race(dlrq_worker_input('cancel',$mainId,bin2hex(random_bytes(16)),$review),function()use($mainId,$review):void{dlrq_owner();dlrq_approve($mainId,$review);},'Approval-wins race');
    dlrq_ok('Blocked withdrawal loses to single committed client-origin send',$race===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&$posts===2&&$gets===$io[1]&&$dns===$io[0]+1);
    $ownerRequest=dlrq_locked(fn()=>provider_directory_indexing_controls_request($dispatch));$event=dlrq_graph()['requests'][$mainId]['events'][0];$ledger=$ownerRequest['ledger'];
    dlrq_ok('Owner v2 and dispatch reciprocally authenticate original client consent',$ownerRequest['document']['version']===2&&$ownerRequest['document']['client_origin']===['id'=>$mainId,'request_sha256'=>$review['request_sha256']]&&$event['id']===$dispatch&&$event['payload']['owner_request_sha256']===$ownerRequest['row']['document_sha256']);
    dlrq_ok('Actual gateway records one uncertain exact boolean POST',$ledger['status']==='uncertain'&&(int)$ledger['attempts']===1&&$wire[1]===['url'=>'https://api.20i.com/package/801/web/directoryIndexing','body'=>'{"value":true}']);
    $lateAuthorize=$capturedAuthorize;$lateRequest=$capturedRequest;$lateBefore=dlrq_digest();$lateIo=[$dns,$gets,$posts];
    staff_locked(fn()=>dlrq_deny('Retained original transport callback cannot send a late second POST',fn()=>(new DlrqWriteTransport($lateAuthorize))->send($lateRequest)));
    dlrq_ok('Late completed dispatch refusal changes no rows or provider counters',dlrq_digest()===$lateBefore&&[$dns,$gets,$posts]===$lateIo);
    $before=dlrq_digest();$io=[$dns,$gets,$posts];$approved=dlrq_approve($mainId,$review);
    dlrq_ok('Exact approval replay returns unknown without effects',$approved['state']==='unknown'&&$approved['owner_request_id']===$dispatch&&dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    dlrq_deny('Changed original request hash cannot replay approved intent',fn()=>provider_directory_indexing_requests_approve($mainId,$mainId,str_repeat('0',64),$dispatch,$approval['anchor_id'],$approval['binding_sha256'],true,DLRQ_PASSWORD,'dlrq_write_dns','dlrq_perform'));
    dlrq_client();$client=dlrq_item($mainId,1);$json=json_encode($client,JSON_THROW_ON_ERROR);
    dlrq_ok('Client receives unknown status without owner approval grants proofs or secrets',$client['state']==='unknown'&&$client['approval']===null&&$client['dispatch']===null&&$client['owner_request_id']===null&&$client['client']===null&&!str_contains($json,DLRQ_SECRET)&&!str_contains($json,$approval['binding_sha256']));
    dlrq_deny('Client cannot call owner verification',fn()=>provider_directory_indexing_requests_verify($mainId,$dispatch,DLRQ_PASSWORD,'dlrq_read_dns','dlrq_read'));
    dlrq_deny('Unknown package effect fences fresh client nonce',fn()=>dlrq_create(bin2hex(random_bytes(16)),dlrq_option($link),false));
    dlrq_ok('Client GET and exact intake replay do not write or call provider',dlrq_create($mainId,$option)['state']==='unknown'&&dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);

    $stage='finished-ledger-boundaries';dlrq_owner();
    foreach(['missing','claimed'] as $mode)dlrq_unfinished_verify($dispatch,$ledger,$mode);
    $checksBefore=dlrq_count('provider_directory_change_checks');$readsBefore=$gets;$postsBefore=$posts;$readStatus=200;$readBody='false';
    $duringRead=fn()=>query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE id=?',['success',time(),$ledger['id']]);
    try{dlrq_deny('Reconciliation during actual GET prevents saving client-origin check',fn()=>dlrq_verify($mainId,$dispatch,$profile),'This request has no eligible durable dispatch completion.');}
    finally{query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE id=?',[$ledger['reconciled_outcome'],$ledger['reconciled_epoch'],$ledger['id']]);$duringRead=null;}
    $latest=provider_directory_indexing_latest($link['id']);
    dlrq_ok('Post-read completion guard retains exact receipt but no new check or send',$gets===$readsBefore+1&&$posts===$postsBefore&&$latest['state']==='complete'&&dlrq_count('provider_directory_change_checks')===$checksBefore&&one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$ledger['id']])===$ledger);

    $stage='separate-readback';$readStatus=403;$readBody='null';$failed=dlrq_verify($mainId,$dispatch,$profile);
    dlrq_client();$failedClient=dlrq_item($mainId,1);dlrq_ok('Failed fresh GET is unavailable not false and stays fenced',$failed['state']==='unknown'&&$failedClient['last_check']['disposition']==='unavailable'&&$failedClient['last_check']['value']===null&&dlrq_pending($link)['id']===$dispatch);
    dlrq_owner();$readStatus=200;$readBody='false';$different=dlrq_verify($mainId,$dispatch,$profile);
    dlrq_client();$differentClient=dlrq_item($mainId,1);dlrq_ok('Different fresh boolean stays unknown and fenced',$different['last_check']['disposition']==='different'&&$differentClient['last_check']['value']===false&&$differentClient['state']==='unknown'&&dlrq_pending($link)['id']===$dispatch);
    dlrq_owner();$before=dlrq_digest();$io=[$dns,$gets,$posts];dlrq_approve($mainId,$review);dlrq_ok('Original owner approval replays after newer reads without resend',dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    $readBody='true';$matched=dlrq_verify($mainId,$dispatch,$profile);dlrq_client();$observed=dlrq_item($mainId,1);
    dlrq_ok('Only exact separate matching GET yields observed client status',$matched['state']==='observed'&&$observed['state']==='observed'&&$observed['last_check']['disposition']==='observed'&&$observed['last_check']['value']===true&&dlrq_pending($link)===null);
    dlrq_ok('Readback preserves original immutable gateway result',one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$ledger['id']])===$ledger&&$posts===2);

    $stage='terminal-recovery';$option2=dlrq_option($link);$recoverId=bin2hex(random_bytes(16));dlrq_create($recoverId,$option2,false);dlrq_owner();$recoverReview=dlrq_item($recoverId);$recoverOwner=$recoverReview['approval']['request_id'];$terminalCount=dlrq_count('provider_directory_dispatch_ends');
    dlrq_ok('Actual terminal audit failure occurs after one additional POST',dlrq_fault('terminal',fn()=>dlrq_approve($recoverId,$recoverReview))&&$posts===3);
    $interrupted=dlrq_item($recoverId);$hash=$interrupted['dispatch']['recovery_ledger_sha256'];$recoverLedger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['directoryindexing:'.$recoverOwner]);
    dlrq_ok('Failed local terminal retains origin and finished one-attempt ledger',$interrupted['state']==='interrupted'&&is_string($hash)&&strlen($hash)===64&&$recoverLedger['status']==='uncertain'&&(int)$recoverLedger['attempts']===1&&dlrq_count('provider_directory_dispatch_ends')===$terminalCount);
    $before=dlrq_digest();$io=[$dns,$gets,$posts];dlrq_approve($recoverId,$recoverReview);
    dlrq_deny('Interrupted request cannot verify before local recovery',fn()=>provider_directory_indexing_requests_verify($recoverId,$recoverOwner,DLRQ_PASSWORD,'dlrq_read_dns','dlrq_read'));
    dlrq_deny('Recovery cannot attach another original approval',fn()=>provider_directory_indexing_requests_recover($recoverId,$dispatch,$hash,DLRQ_PASSWORD));
    dlrq_deny('Recovery requires exact finished ledger digest',fn()=>provider_directory_indexing_requests_recover($recoverId,$recoverOwner,str_repeat('0',64),DLRQ_PASSWORD));
    dlrq_owner(3);dlrq_deny('Different actual owner cannot recover original approval',fn()=>provider_directory_indexing_requests_recover($recoverId,$recoverOwner,$hash,DLRQ_PASSWORD));dlrq_owner();
    foreach(['original client authentication'=>['studio_clients','auth_version',1], 'original owner disabled'=>['staff_profiles','enabled',1],
        'original credential seal'=>['provider_profiles','credentials',$profile], 'original assigned client'=>['provider_package_links','client_id',$link['id']]] as $label=>[$tableName,$column,$id]){
        $pk=$tableName==='staff_profiles'?'user_id':'id';$old=one('SELECT '.$column.' AS value FROM '.table($tableName).' WHERE '.$pk.'=?',[$id])['value'];
        $changed=match($column){'client_id'=>2,'enabled'=>0,default=>bin2hex(random_bytes(16))};$session=$_SESSION;
        query('UPDATE '.table($tableName).' SET '.$column.'=? WHERE '.$pk.'=?',[$changed,$id]);unset($GLOBALS['cwp_settings']);
        try{dlrq_deny('Local terminal recovery rechecks '.$label,fn()=>provider_directory_indexing_requests_recover($recoverId,$recoverOwner,$hash,DLRQ_PASSWORD));}
        finally{query('UPDATE '.table($tableName).' SET '.$column.'=? WHERE '.$pk.'=?',[$old,$id]);$_SESSION=$session;unset($GLOBALS['cwp_settings']);}
    }
    dlrq_ok('Interrupted replay and refused recovery add no provider or database effects',dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    $rows=dlrq_rows();$recovered=provider_directory_indexing_requests_recover($recoverId,$recoverOwner,$hash,DLRQ_PASSWORD);
    dlrq_ok('Explicit recovery adds only local terminal audit and counter',$recovered['state']==='unknown'&&dlrq_recovery_delta($rows,$recoverOwner)&&[$dns,$gets,$posts]===$io&&one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$recoverLedger['id']])===$recoverLedger);
    dlrq_deny('Existing terminal cannot be recovered twice',fn()=>provider_directory_indexing_requests_recover($recoverId,$recoverOwner,$hash,DLRQ_PASSWORD));
    $lateBefore=dlrq_digest();$lateIo=[$dns,$gets,$posts];
    staff_locked(fn()=>dlrq_deny('Old POST callback remains fenced after newer approval and local recovery',fn()=>(new DlrqWriteTransport($lateAuthorize))->send($lateRequest)));
    dlrq_ok('Late old dispatch cannot alter the newer unresolved fence',dlrq_pending($link)['id']===$recoverOwner&&dlrq_digest()===$lateBefore&&[$dns,$gets,$posts]===$lateIo);

    $stage='history-integrity';$check=one('SELECT * FROM '.table('provider_directory_change_checks').' WHERE request_id=? AND disposition=?',[$dispatch,'observed']);
    // Keep the exact valid empty table while removing every owned retained row.
    // Public screens run outside a transaction, so nested-transaction refusal cannot mask lost origin.
    $lostBefore=dlrq_digest();$lostIo=[$dns,$gets,$posts];$lostSession=$_SESSION;
    $lostRows=['provider_directory_listing_requests_events'=>all('SELECT * FROM '.table('provider_directory_listing_requests_events').' ORDER BY ordinal'),
        'settings'=>all('SELECT * FROM '.table('settings').' WHERE BINARY name IN (?,?)',['provider_directory_listing_requests_schema_version','provider_directory_listing_requests_head']),
        'audit'=>all('SELECT * FROM '.table('audit').' WHERE BINARY action IN (?,?)',['provider.directory_listing_requests_prepared','provider.directory_listing_requests_event'])];
    try{
        dlrq_locked(function():void{query('DELETE FROM '.table('provider_directory_listing_requests_events'));query('DELETE FROM '.table('settings').' WHERE BINARY name IN (?,?)',['provider_directory_listing_requests_schema_version','provider_directory_listing_requests_head']);query('DELETE FROM '.table('audit').' WHERE BINARY action IN (?,?)',['provider.directory_listing_requests_prepared','provider.directory_listing_requests_event']);});
        dlrq_ok('Complete local journal loss leaves authenticated owner v2 evidence present',dlrq_locked(fn()=>provider_directory_indexing_requests_ready())===false&&one('SELECT id FROM '.table('provider_directory_changes').' WHERE id=?',[$dispatch])!==null);
        dlrq_deny('Owner screen refuses absent journal with retained v2 origin',fn()=>dlrq_screen());
        dlrq_client();dlrq_deny('Client screen refuses absent journal with retained v2 origin',fn()=>dlrq_screen(1));
    }finally{
        dlrq_locked(function()use($lostRows):void{foreach($lostRows as $name=>$rows)foreach($rows as $row)query('INSERT INTO '.table($name).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));});
        $_SESSION=$lostSession;unset($GLOBALS['cwp_settings']);
    }
    dlrq_ok('Complete local journal loss probe restores exact original cells and no provider effect',dlrq_digest()===$lostBefore&&[$dns,$gets,$posts]===$lostIo&&dlrq_item($mainId)['state']==='observed');
    foreach([
        'client request'=>fn()=>query('DELETE FROM '.table('provider_directory_listing_requests_events').' WHERE id=?',[$mainId]),
        'client dispatch'=>fn()=>query('DELETE FROM '.table('provider_directory_listing_requests_events').' WHERE id=?',[$dispatch]),
        'paired dispatch and audit'=>function()use($dispatch):void{query('DELETE FROM '.table('provider_directory_listing_requests_events').' WHERE id=?',[$dispatch]);query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.directory_listing_requests_event','Directory listing request event '.$dispatch.' %']);},
        'canonical client payload'=>fn()=>query('UPDATE '.table('provider_directory_listing_requests_events').' SET document=? WHERE id=?',['{}',$mainId]),
        'head'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_directory_listing_requests_head']),
        'marker'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_directory_listing_requests_schema_version']),
        'preparation audit'=>fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=?',['provider.directory_listing_requests_prepared']),
        'unknown marker'=>fn()=>save_setting('provider_directory_listing_requests_extra','1'),
        'owner origin commitment'=>fn()=>query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',['{}',$dispatch]),
        'owner approval'=>function()use($dispatch):void{query('DELETE FROM '.table('provider_directory_change_checks').' WHERE request_id=?',[$dispatch]);query('DELETE FROM '.table('provider_directory_dispatch_ends').' WHERE id=?',[$dispatch]);query('DELETE FROM '.table('provider_directory_changes').' WHERE id=?',[$dispatch]);},
        'terminal'=>fn()=>query('DELETE FROM '.table('provider_directory_dispatch_ends').' WHERE id=?',[$dispatch]),
        'paired check and audit'=>function()use($check):void{query('DELETE FROM '.table('provider_directory_change_checks').' WHERE id=?',[$check['id']]);query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.directory_indexing_control_check','Directory listing change check '.$check['id'].' %']);},
        'reconciled original dispatch ledger'=>fn()=>query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE id=?',['success',time(),$ledger['id']]),
        'original flag observation'=>fn()=>query('DELETE FROM '.table('provider_directory_indexing_observations').' WHERE id=?',[$check['receipt_id']]),
    ] as $name=>$mutate)dlrq_damage('Missing or changed '.$name.' refuses authenticated history',$mutate);

    $stage='revocation';$otherAnchor=dlrq_flag($other,true);$otherShare=provider_directory_indexing_share($other['id'],'',bin2hex(random_bytes(16)),true,$otherAnchor['id'],DLRQ_PASSWORD);
    dlrq_client(2);$otherOption=dlrq_option($other,2);$revokeId=bin2hex(random_bytes(16));dlrq_create($revokeId,$otherOption,false,2);dlrq_owner();$revokeReview=dlrq_item($revokeId);$revokeOwner=$revokeReview['approval']['request_id'];
    $auth=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=2')['auth_version'];$beforePosts=$posts;
    $duringDns=fn()=>query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=2',[bin2hex(random_bytes(16))]);
    try{$revoked=dlrq_approve($revokeId,$revokeReview);dlrq_ok('Original client auth changed during DNS prevents credentials and send',$revoked['state']==='not_sent'&&$revoked['current_assignment']===false&&$posts===$beforePosts&&one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['directoryindexing:'.$revokeOwner])===null);
        dlrq_deny('Revoked original client auth denies exact owner replay',fn()=>dlrq_approve($revokeId,$revokeReview));
    }finally{query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=2',[$auth]);dlrq_owner();}
    $stage='daily-intake-limit';dlrq_client(2);$limitOption=dlrq_option($other,2);$limitLast='';$limitEvent='';
    for($i=2;$i<=10;$i++){
        $limitLast=bin2hex(random_bytes(16));dlrq_create($limitLast,$limitOption,false,2);$limitEvent=bin2hex(random_bytes(16));
        provider_directory_indexing_requests_change($limitLast,$limitLast,$limitEvent,'cancel','',2);
    }
    dlrq_ok('Ten actual client attempts across all outcomes consume daily intake allowance',count(dlrq_screen(2)['items'])===10);
    $before=dlrq_digest();$io=[$dns,$gets,$posts];
    dlrq_deny('Eleventh daily client attempt refuses before event or provider work',fn()=>dlrq_create(bin2hex(random_bytes(16)),$limitOption,false,2),'The daily directory listing request limit has been reached. Please try later.');
    dlrq_ok('Exact cancelled intake and decision retries do not consume daily capacity',dlrq_create($limitLast,$limitOption,false,2)['state']==='cancelled'
        &&provider_directory_indexing_requests_change($limitLast,$limitLast,$limitEvent,'cancel','',2)['state']==='cancelled'&&dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    dlrq_owner();
    $shareNow=dlrq_locked(fn()=>provider_directory_indexing_permission($link['id']));provider_directory_indexing_share($link['id'],$shareNow['document']['id'],bin2hex(random_bytes(16)),false,'',DLRQ_PASSWORD);
    $before=dlrq_digest();$io=[$dns,$gets,$posts];dlrq_deny('Sharing revocation blocks original owner verification',fn()=>provider_directory_indexing_requests_verify($recoverId,$recoverOwner,DLRQ_PASSWORD,'dlrq_read_dns','dlrq_read'));
    dlrq_deny('Direct owner verification also checks client-origin sharing',fn()=>provider_directory_indexing_controls_verify($recoverOwner,DLRQ_PASSWORD,'dlrq_read_dns','dlrq_read'));
    dlrq_client();$history=dlrq_item($recoverId,1);dlrq_ok('Revoked sharing hides choices but retains safe own unknown history',$history['state']==='unknown'&&$history['current_assignment']===false&&dlrq_screen(1)['options']===[]&&dlrq_pending($link)['id']===$recoverOwner);
    dlrq_ok('Revoked read and GET history create no effects',dlrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    dlrq_owner();provider_settings_save($profile,1,'Rotated Directory listing request credential',true,'SYNTHETIC_ROTATED_DIRECTORY_KEY',DLRQ_PASSWORD);
    dlrq_deny('Rotated account credential blocks exact old owner replay',fn()=>dlrq_approve($recoverId,$recoverReview));
    provider_settings_save($profile,2,'Restored key new account revision',true,DLRQ_SECRET,DLRQ_PASSWORD);dlrq_ok('Replacement account revision gets real complete inventory',dlrq_hosting($profile)['state']==='complete');
    provider_inventory_unlink($link['id'],DLRQ_PASSWORD);provider_inventory_link($profile,801,2,3,DLRQ_PASSWORD);dlrq_ok('Reassignment gets genuine post-assignment inventory',dlrq_hosting($profile)['state']==='complete');
    dlrq_client(2);dlrq_deny('New assignee cannot read old client request',fn()=>dlrq_item($recoverId,2));
    dlrq_ok('Original unknown effect fence survives key and client replacement',dlrq_pending($link)['id']===$recoverOwner);
    dlrq_client();dlrq_ok('Original client retains dated history without changed-resource authority',dlrq_item($recoverId,1)['current_assignment']===false);
    dlrq_owner();dlrq_ok('Entire original owner v1 request grant ledger and readback remain byte-exact',dlrq_old_rows($oldId)===$legacy);
    dlrq_ok('Final authenticated request graph remains valid',is_array(dlrq_locked(fn()=>provider_directory_indexing_requests_state())));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure in '.$stage,'passed'=>false];$failure=['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null];}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
$result=['passed'=>$passed,'scope'=>'Isolated native SQL, actual primary-client and owner wrappers, real shared gateway and observed process mutex races; provider transport synthetic. No live provider, browser, filesystem, index-file delivery or web reachability claim.','checks'=>$checks];
if(isset($failure))$result['failure']=$failure;fwrite($handle,json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");fclose($handle);
echo ($passed?'PASS':'FAIL').': '.count($checks).' Directory listing request native checks'.PHP_EOL;exit($passed?0:1);
