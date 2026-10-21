<?php
/** Isolated native SQL/owner/gateway acceptance. All provider responses are synthetic. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_FORCE_SSL_REQUESTS')!=='1'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
$worker=count($argv)===2&&$argv[1]==='--worker';
if(count($argv)!==($worker?2:1))exit(2);
$port=getenv('CWP_TEST_PORT')?:'33306';if(!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/force_ssl_requests.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIPackageForceSsl\{PackageForceSslBinding,PackageForceSslObservation,PackageForceSsl};
use CajunWebPro\Foundation\TwentyI\Operations\{ForceSslChange,TwentyIOperationAdapter};

const FSRN_PREFIX='fsrn_';
const FSRN_PASSWORD='Synthetic client Force SSL owner passphrase';
const FSRN_SECRET='SYNTHETIC_FORCE_SSL_REQUEST_KEY';
const FSRN_NAME='site801.synthetic.invalid';
$root=realpath(__DIR__.'/..');if(!$root)exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';$private=$runtime.'/force-ssl-requests-private';
$report=$qa.'/provider-force-ssl-requests-native-results.json';
foreach([$runtime,$qa] as $path){if(is_link($path)||(file_exists($path)&&!is_dir($path)))exit(2);if(!is_dir($path)&&!mkdir($path,0700))exit(2);}
if(is_link($private)||($worker?!is_dir($private):file_exists($private))||(!$worker&&(is_link($report)||file_exists($report))))exit(2);
if(!$worker&&!mkdir($private,0700))exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/force-ssl-requests','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-force-ssl-requests-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$port,'name'=>'cwp_test',
    'user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>FSRN_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root.'/site';start_session();
$checks=[];$stage='preflight';$dns=0;$gets=0;$posts=0;$wire=[];$duringDns=null;$duringPost=null;$duringRead=null;$capturedAuthorize=null;
$readStatus=200;$readBody='false';

function fsrn_ok(string $name,bool $passed):void{global $checks;foreach($checks as $check)if($check['name']===$name)throw new LogicException('Duplicate fixture assertion.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function fsrn_deny(string $name,callable $call,?string $message=null):void{
    $caught=null;try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    fsrn_ok($name,$caught!==null&&($message===null||$caught->getMessage()===$message));
}
function fsrn_owner(int $id=1):void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function fsrn_client(int $id=1):void{
    $u=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);
    $_SESSION=['client_id'=>$id,'client_auth'=>$u['auth_version'],'client_login'=>time(),'client_last'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function fsrn_locked(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function fsrn_mutex():bool{
    $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.FSRN_PREFIX),0,40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;
}
function fsrn_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function fsrn_exists():bool{return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY TABLE_NAME=?',[FSRN_PREFIX.'provider_force_ssl_requests_events'])!==null;}
function fsrn_counts():array{global $dns,$gets,$posts;return [$dns,$gets,$posts,...array_map('fsrn_count',['provider_force_ssl_requests_events','provider_force_ssl_changes','provider_force_ssl_dispatch_ends','provider_force_ssl_change_checks','provider_operation_grants','provider_operations','provider_refreshes','audit'])];}
function fsrn_rows():array{
    $rows=[];foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(FSRN_PREFIX),FSRN_PREFIX]) as $t){
        $name=$t['TABLE_NAME'];if(!preg_match('/\Afsrn_[a-z0-9_]+\z/',$name))throw new LogicException('Unexpected fixture table.');
        $data=all('SELECT * FROM `'.$name.'`');usort($data,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$data;
    }return $rows;
}
function fsrn_digest():string{return hash('sha256',json_encode(fsrn_rows(),JSON_THROW_ON_ERROR));}
/** A genuine bounded cooldown; no saved date, quota or clock is changed. */
function fsrn_wait(string $profile):void{
    if(db()->inTransaction()||fsrn_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');
    $deadline=microtime(true)+32;while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
function fsrn_launch(array $input):array{
    $process=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('Could not start client-request worker.');
    $json=json_encode($input,JSON_THROW_ON_ERROR);
    if(fwrite($pipes[0],$json)!==strlen($json))throw new RuntimeException('Could not send worker input.');
    fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    return ['process'=>$process,'pipes'=>$pipes,'stdout'=>'','stderr'=>'','connection'=>null];
}
/** Require a real blocked child GET_LOCK while this connection owns the exact mutex. */
function fsrn_blocked(array &$worker):void{
    if(!fsrn_mutex()||db()->inTransaction())throw new LogicException('Race observer needs owning mutex without transaction.');
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
            if($row&&stripos((string)$row['INFO'],'GET_LOCK')!==false&&stripos((string)$row['STATE'],'lock')!==false&&fsrn_mutex())return;
        }
        $status=proc_get_status($worker['process']);if(!$status['running'])throw new RuntimeException('Worker exited before observed lock contention.');
        if(microtime(true)>$deadline)throw new RuntimeException('Actual blocked worker was not observed within lock budget.');usleep(20000);
    }while(true);
}
function fsrn_join(array &$worker):array{
    $exit=null;$deadline=microtime(true)+150;
    try{do{
        $worker['stdout'].=stream_get_contents($worker['pipes'][1]);$worker['stderr'].=stream_get_contents($worker['pipes'][2]);
        if(strlen($worker['stdout'])>8192||strlen($worker['stderr'])>2048)throw new RuntimeException('Worker result bound exceeded.');
        $status=proc_get_status($worker['process']);if(!$status['running']){$exit=$status['exitcode'];break;}
        if(microtime(true)>$deadline){global $checks;$proc='none';try{$proc=json_encode(all('SELECT STATE,TIME,INFO FROM information_schema.PROCESSLIST WHERE ID=?',[$worker['connection']]),JSON_PARTIAL_OUTPUT_ON_ERROR);}catch(Throwable){}$checks[]=['name'=>'Worker deadline detail out='.substr(trim($worker['stdout']),0,300).' err='.substr(trim($worker['stderr']),0,200).' proc='.substr((string)$proc,0,300),'passed'=>false];throw new RuntimeException('Worker result deadline exceeded.');}usleep(20000);
    }while(true);$worker['stdout'].=stream_get_contents($worker['pipes'][1]);$worker['stderr'].=stream_get_contents($worker['pipes'][2]);}
    finally{$status=proc_get_status($worker['process']);if($status['running'])proc_terminate($worker['process']);fclose($worker['pipes'][1]);fclose($worker['pipes'][2]);$closed=proc_close($worker['process']);if($exit===null)$exit=$closed;}
    if($exit!==0||$worker['stderr']!==''||strlen($worker['stdout'])>8192){global $checks;$checks[]=['name'=>'Worker failure detail exit='.$exit.' out='.substr(trim($worker['stdout']),0,300).' err='.substr(trim($worker['stderr']),0,200),'passed'=>false];throw new RuntimeException('Worker failed after real lock contention.');}
    $result=json_decode(trim($worker['stdout']),true,5,JSON_THROW_ON_ERROR);
    if(!is_array($result)||array_keys($result)!==['outcome','dns','gets','posts']||!in_array($result['outcome'],['completed','refused'],true))throw new RuntimeException('Invalid worker result.');
    foreach(['dns','gets','posts'] as $key)if(!is_int($result[$key])||$result[$key]<0)throw new RuntimeException('Invalid worker transport counter.');return $result;
}
function fsrn_race(array $input,callable $winner,string $label):array{
    $child=null;try{staff_locked(function()use($input,$winner,$label,&$child):void{
        $child=fsrn_launch($input);fsrn_blocked($child);fsrn_ok($label.' observes a separate blocked GET_LOCK',true);$winner();
    });}finally{if($child!==null)$result=fsrn_join($child);}return $result;
}
function fsrn_boundary(callable $authorize):void{$authorize();if(db()->inTransaction()||!fsrn_mutex())throw new LogicException('Transport needs owning mutex outside transaction.');}
function fsrn_read_dns():string{global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';}
function fsrn_write_dns():string{
    global $dns,$duringDns;if(db()->inTransaction()||!fsrn_mutex())throw new LogicException('Write DNS needs owning mutex outside transaction.');
    $row=one('SELECT * FROM '.table('provider_force_ssl_changes').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Missing durable approval before DNS.');$q=provider_force_ssl_controls_request($row['id']);
    if($q['grant']['consumed_key']!=='forcessl:'.$row['id']||$q['ledger']!==null
        ||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',
            ['provider.force_ssl_control_request','Force SSL change request '.$row['id'].' %'])->fetchColumn()!==1)throw new LogicException('Consumed approval audit must precede DNS.');
    if($q['document']['version']===2){$origin=$q['document']['client_origin'];
        if(!one('SELECT id FROM '.table('provider_force_ssl_requests_events').' WHERE id=? AND subject_id=? AND kind=?',[$row['id'],$origin['id'],'dispatch']))throw new LogicException('Client dispatch must commit with approval before DNS.');}
    $dns++;if($duringDns){$call=$duringDns;$duringDns=null;$call();}return '8.8.8.8';
}
final class FsrnReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readBody,$readStatus,$duringRead;fsrn_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null
            ||$request->headers!==['Authorization'=>'Bearer '.base64_encode(FSRN_SECRET),'Accept'=>'application/json'])throw new LogicException('Unexpected fixed synthetic GET.');
        $gets++;if($duringRead){$call=$duringRead;$duringRead=null;$call();}return new TransportResponse($readStatus,[],$readBody);
    }
}
function fsrn_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?PackageForceSslBinding $forceSslContext=null):InventorySnapshot|PackageForceSslObservation{
    fsrn_boundary($authorize);if($key!==FSRN_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{fsrn_boundary($authorize);return $key;});
    if($operation===InventoryRead::PACKAGES_LIST)return InventoryReader::read(new FsrnReadTransport($authorize,'https://api.20i.com/package'),$credential,$operation,null,time());
    if($operation!==PackageForceSsl::OPERATION||!$forceSslContext)throw new LogicException('Wrong Force SSL read context.');
    return PackageForceSsl::read(new FsrnReadTransport($authorize,'https://api.20i.com/package/'.$forceSslContext->data['package_id'].'/web/forceSSL'),$credential,$forceSslContext,time());
}
final class FsrnWriteTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$wire,$duringPost;fsrn_boundary($this->authorize);ForceSslChange::wire($request);
        if(!in_array($request->url,['https://api.20i.com/package/801/web/forceSSL','https://api.20i.com/package/802/web/forceSSL'],true)
            ||($request->headers['Authorization']??null)!=='Bearer '.base64_encode(FSRN_SECRET))throw new LogicException('Wrong synthetic write account.');
        $posts++;$wire[]=['url'=>$request->url,'body'=>$request->body];if($duringPost){$call=$duringPost;$duringPost=null;$call();}
        return new TransportResponse(200,[],'true');
    }
}
function fsrn_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    global $capturedAuthorize;$capturedAuthorize=$authorize;fsrn_boundary($authorize);
    if($key!==FSRN_SECRET||$address!=='8.8.8.8'||$operation->name!==ForceSslChange::OPERATION)throw new LogicException('Wrong synthetic operation identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),static function()use($authorize,$key):string{fsrn_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new FsrnWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function fsrn_package(int $id):array{return ['id'=>$id,'name'=>'site'.$id.'.synthetic.invalid','names'=>['site'.$id.'.synthetic.invalid'],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];}
function fsrn_hosting(string $profile):array{
    global $readBody,$readStatus;fsrn_wait($profile);$readStatus=200;$readBody=json_encode(array_map('fsrn_package',[801,802]),JSON_THROW_ON_ERROR);
    return provider_inventory_refresh_attempt($profile,(int)provider_settings_load($profile)['revision'],FSRN_PASSWORD,'fsrn_read_dns','fsrn_read');
}
function fsrn_flag(array $link,bool $value):array{
    global $readBody,$readStatus;fsrn_wait($link['profile_id']);$readStatus=200;$readBody=$value?'true':'false';
    $binding=provider_force_ssl_binding($link['id']);$r=provider_force_ssl_refresh($link['id'],$binding,FSRN_PASSWORD,'fsrn_read_dns','fsrn_read');
    if($r['state']!=='complete')throw new RuntimeException('Synthetic flag did not complete.');return provider_force_ssl_observation(provider_force_ssl_source($r['id']));
}
function fsrn_screen(?int $client=null,?string $id=null):array{return provider_force_ssl_requests_screen($client,1,$id);}
function fsrn_item(string $id,?int $client=null):array{
    $items=array_values(array_filter(fsrn_screen($client,$id)['items'],fn($r)=>$r['id']===$id));if(count($items)!==1)throw new RuntimeException('Expected original request.');return $items[0];
}
function fsrn_option(array $link,int $client=1):array{
    $items=array_values(array_filter(fsrn_screen($client)['options'],fn($r)=>$r['link_id']===$link['id']));if(count($items)!==1)throw new RuntimeException('Expected shared assigned package.');return $items[0];
}
function fsrn_create(string $id,array $option,bool $desired=true,int $client=1):array{return provider_force_ssl_requests_create($id,$client,$option['link_id'],$option['review_sha256'],$desired,true);}
function fsrn_approve(string $id,array $item,?callable $resolver=null):array{
    $a=$item['approval'];return provider_force_ssl_requests_approve($id,$item['latest_event'],$item['request_sha256'],$a['request_id'],$a['anchor_id'],$a['binding_sha256'],true,FSRN_PASSWORD,$resolver??'fsrn_write_dns','fsrn_perform');
}
function fsrn_verify(string $id,string $ownerId,string $profile):array{fsrn_wait($profile);return provider_force_ssl_requests_verify($id,$ownerId,FSRN_PASSWORD,'fsrn_read_dns','fsrn_read');}
function fsrn_graph():array{return fsrn_locked(fn()=>provider_force_ssl_requests_graph());}
function fsrn_pending(array $link):?array{return fsrn_locked(fn()=>provider_force_ssl_controls_pending($link['profile_id'],(int)$link['package_id']));}
function fsrn_head():string{return (string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_force_ssl_requests_head'])['value']??'');}
function fsrn_event_audits():int{return (int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.force_ssl_requests_event'])->fetchColumn();}
/** Real INSERT failure and connection variables witness rows before rollback. */
function fsrn_fault(string $phase,callable $call):bool{
    $actions=['prepare'=>'provider.force_ssl_requests_prepared','intake'=>'provider.force_ssl_requests_event','dispatch'=>'provider.force_ssl_requests_event','terminal'=>'provider.force_ssl_control_terminal'];
    if(!isset($actions[$phase]))throw new LogicException('Unknown fixture fault.');
    $tables=['provider_force_ssl_requests_events','provider_force_ssl_changes','provider_operation_grants','provider_force_ssl_dispatch_ends'];$before=array_map('fsrn_count',$tables);
    $sample='';foreach($tables as $i=>$t)$sample.='SET @fsrn_'.$i.'=(SELECT COUNT(*) FROM '.table($t).'); ';
    db()->exec('CREATE TRIGGER fsrn_audit_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='".$actions[$phase]."' THEN SET @fsrn_hit=COALESCE(@fsrn_hit,0)+1; ".$sample."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic Force SSL audit fault'; END IF; END");
    query('SET @fsrn_hit=0');$caught=false;try{$call();}catch(PDOException $e){
        $delta=match($phase){'prepare'=>[0,0,0,0],'intake'=>[1,0,0,0],'dispatch'=>[1,1,1,0],'terminal'=>[1,1,1,1]};
        $caught=$e->getCode()==='45000'&&(int)query('SELECT @fsrn_hit')->fetchColumn()===1;
        foreach($before as $i=>$n)$caught=$caught&&(int)query('SELECT @fsrn_'.$i)->fetchColumn()===$n+$delta[$i];
    }finally{db()->exec('DROP TRIGGER fsrn_audit_fault');}return $caught;
}
function fsrn_damage(string $name,callable $mutate):void{
    global $dns,$gets,$posts;$before=fsrn_digest();$io=[$dns,$gets,$posts];$session=$_SESSION;
    staff_locked(function()use($name,$mutate):void{db()->beginTransaction();try{$mutate();unset($GLOBALS['cwp_settings']);fsrn_deny($name,fn()=>provider_force_ssl_requests_state());}finally{if(db()->inTransaction())db()->rollBack();}});
    $_SESSION=$session;unset($GLOBALS['cwp_settings']);fsrn_ok($name.' exact rollback restores history',fsrn_digest()===$before&&[$dns,$gets,$posts]===$io&&is_array(fsrn_locked(fn()=>provider_force_ssl_requests_state())));
}
function fsrn_recovery_delta(array $before,string $id):bool{
    $after=fsrn_rows();$terminal=one('SELECT * FROM '.table('provider_force_ssl_dispatch_ends').' WHERE id=?',[$id]);if(!$terminal)return false;
    $detail='Force SSL change terminal '.$id.' '.provider_force_ssl_digest(provider_force_ssl_controls_values('terminal',$terminal));
    foreach(['provider_force_ssl_dispatch_ends'=>fn($r)=>$r['id']===$id,'audit'=>fn($r)=>$r['action']==='provider.force_ssl_control_terminal'&&$r['detail']===$detail&&(int)$r['user_id']===1] as $name=>$match){
        $key=FSRN_PREFIX.$name;if(count(array_filter($after[$key],$match))!==1||array_filter($before[$key],$match)!==[])return false;$after[$key]=array_values(array_filter($after[$key],fn($r)=>!$match($r)));
    }
    $name='provider_force_ssl_control_terminal_count';$key=FSRN_PREFIX.'settings';$old=array_values(array_filter($before[$key],fn($r)=>$r['name']===$name));if(count($old)!==1)return false;$found=0;
    foreach($after[$key] as &$row)if($row['name']===$name){if((int)$row['value']!==(int)$old[0]['value']+1)return false;$row=$old[0];$found++;}unset($row);if($found!==1)return false;
    foreach($after as &$rows)usort($rows,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));unset($rows);return $after===$before;
}
/** Disposable negative fixture: authenticated attempted terminal, but no eligible finished ledger.
 * Rebind only its terminal proof/audit so verification reaches the durable-completion gate;
 * finally restore the exact original ledger, terminal and audit. No production recovery uses this. */
function fsrn_unfinished_verify(string $id,array $ledger,string $mode):void{
    global $dns,$gets,$posts;
    if(!in_array($mode,['missing','claimed'],true))throw new LogicException('Unknown incomplete ledger fixture.');
    $before=fsrn_digest();$io=[$dns,$gets,$posts];
    $terminal=one('SELECT * FROM '.table('provider_force_ssl_dispatch_ends').' WHERE id=?',[$id]);
    $detail='Force SSL change terminal '.$id.' '.provider_force_ssl_digest(provider_force_ssl_controls_values('terminal',$terminal));
    $audit=one('SELECT * FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.force_ssl_control_terminal',$detail]);
    if(!$audit)throw new LogicException('Original terminal audit absent.');
    $replacement=null;if($mode==='claimed'){$replacement=$ledger;foreach(['operation','provider_code','external_id','resolved_epoch','reconciled_outcome','reconciled_epoch'] as $key)$replacement[$key]=null;$replacement['status']='claimed';$replacement['result_message']='';$replacement['attempts']=0;}
    $write=static function(?array $row,array $end,string $auditDetail)use($ledger,$id,$audit):void{
        query('DELETE FROM '.table('provider_operations').' WHERE id=?',[$ledger['id']]);
        if($row)query('INSERT INTO '.table('provider_operations').' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
        query('UPDATE '.table('provider_force_ssl_dispatch_ends').' SET ledger_sha256=?,document_json=?,document_sha256=?,proof=? WHERE id=?',[$end['ledger_sha256'],$end['document_json'],$end['document_sha256'],$end['proof'],$id]);
        query('UPDATE '.table('audit').' SET detail=? WHERE id=?',[$auditDetail,$audit['id']]);
    };
    $end=$terminal;$d=json_decode($end['document_json'],true,20,JSON_THROW_ON_ERROR);$d['ledger_sha256']=provider_force_ssl_controls_ledger_digest($replacement);
    $end['ledger_sha256']=$d['ledger_sha256'];$end['document_json']=provider_force_ssl_json($d);$end['document_sha256']=hash('sha256',$end['document_json']);unset($end['proof']);$end=provider_force_ssl_controls_prove('terminal',$end);
    $negativeDetail='Force SSL change terminal '.$id.' '.provider_force_ssl_digest(provider_force_ssl_controls_values('terminal',$end));
    try{
        fsrn_locked(fn()=>$write($replacement,$end,$negativeDetail));
        fsrn_ok('Authenticated attempted terminal with '.$mode.' ledger remains structurally readable',is_array(fsrn_locked(fn()=>provider_force_ssl_requests_state())));
        fsrn_deny('Crafted v2 verification refuses '.$mode.' ledger before DNS',fn()=>provider_force_ssl_controls_verify($id,FSRN_PASSWORD,'fsrn_read_dns','fsrn_read'),'This request has no eligible durable dispatch completion.');
    }finally{fsrn_locked(fn()=>$write($ledger,$terminal,$detail));}
    fsrn_ok('Incomplete '.$mode.' ledger probe restores exact rows without IO',fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);
}
function fsrn_worker_input(string $action,string $id,string $event,array $item):array{
    return ['action'=>$action,'id'=>$id,'expected'=>$item['latest_event'],'event'=>$event,'request_sha256'=>$item['request_sha256'],'anchor_id'=>$item['approval']['anchor_id'],'binding_sha256'=>$item['approval']['binding_sha256']];
}
function fsrn_worker():never{
    global $dns,$gets,$posts;try{
        $in=json_decode(stream_get_contents(STDIN,8193),true,6,JSON_THROW_ON_ERROR);
        if(!is_array($in)||array_keys($in)!==['action','id','expected','event','request_sha256','anchor_id','binding_sha256']||!in_array($in['action'],['cancel','approve'],true))throw new RuntimeException('Invalid worker command.');
        foreach(['id','expected','event','anchor_id'] as $key)if(!is_string($in[$key])||!preg_match('/\A[a-f0-9]{32}\z/',$in[$key]))throw new RuntimeException('Invalid worker identity.');
        foreach(['request_sha256','binding_sha256'] as $key)if(!is_string($in[$key])||!preg_match('/\A[a-f0-9]{64}\z/',$in[$key]))throw new RuntimeException('Invalid worker proof.');
        if($in['action']==='cancel')fsrn_client();else fsrn_owner();
        echo json_encode(['ready'=>true,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR)."\n";flush();$outcome='completed';
        for($budget=microtime(true)+120;;)try{if($in['action']==='cancel')provider_force_ssl_requests_change($in['id'],$in['expected'],$in['event'],'cancel','',1);
            else provider_force_ssl_requests_approve($in['id'],$in['expected'],$in['request_sha256'],$in['event'],$in['anchor_id'],$in['binding_sha256'],true,FSRN_PASSWORD,'fsrn_write_dns','fsrn_perform');
            break;
        }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;
            if($e->getMessage()==='Another account change is in progress. Please retry.'&&microtime(true)<$budget)continue;
            $message=$in['action']==='cancel'?'This HTTPS request changed. Reload its review.':'This request was already reviewed or withdrawn.';
            if($e->getMessage()!==$message)throw $e;$outcome='refused';break;}
        echo json_encode(['outcome'=>$outcome,'dns'=>$dns,'gets'=>$gets,'posts'=>$posts],JSON_THROW_ON_ERROR)."\n";exit;
    }catch(Throwable $e){echo json_encode(['failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR)."\n";exit(1);}
}
function fsrn_seed():array{
    fsrn_ok('Dedicated native namespace begins empty',(string)query('SELECT DATABASE()')->fetchColumn()==='cwp_test'&&(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(FSRN_PREFIX),FSRN_PREFIX])->fetchColumn()===0);
    foreach(schema_statements(FSRN_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','4');foreach(default_settings() as $k=>$v)save_setting($k,$v);
    foreach([1,2,3] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Force SSL requests staff '.$id,'fsrn'.$id.'@example.invalid',password_hash(FSRN_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Force SSL requests client '.$id,'fsrn-client'.$id.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    fsrn_owner();provider_settings_migrate(FSRN_PASSWORD);provider_inventory_migrate(FSRN_PASSWORD);provider_operations_migrate(FSRN_PASSWORD);
    $profile=provider_settings_save('',0,'Client Force SSL request fixture',true,FSRN_SECRET,FSRN_PASSWORD);provider_force_ssl_prepare(FSRN_PASSWORD);provider_force_ssl_controls_prepare(FSRN_PASSWORD);
    fsrn_ok('Real initial package reader completes with synthetic transport',fsrn_hosting($profile)['state']==='complete');
    foreach([801,802] as $package)provider_inventory_link($profile,$package,$package===802?2:1,1,FSRN_PASSWORD);
    $source=fsrn_hosting($profile);fsrn_ok('Real post-assignment package read completes',$source['state']==='complete');
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=801');$other=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=802');
    $oldAnchor=fsrn_flag($other,false);$oldBinding=provider_force_ssl_binding($other['id']);$oldId=bin2hex(random_bytes(16));
    $old=provider_force_ssl_controls_change($oldId,$other['id'],$oldAnchor['id'],$oldBinding->sha256,true,true,FSRN_PASSWORD,'fsrn_write_dns','fsrn_perform');
    fsrn_wait($profile);$GLOBALS['readBody']='true';$old=provider_force_ssl_controls_verify($oldId,FSRN_PASSWORD,'fsrn_read_dns','fsrn_read');
    fsrn_ok('Existing owner-only v1 flow dispatches and observes before client preparation',$old['status']==='observed'&&fsrn_locked(fn()=>provider_force_ssl_controls_request($oldId))['document']['version']===1&&!fsrn_exists());
    $anchor=fsrn_flag($link,false);return compact('profile','source','link','other','anchor','oldId');
}
function fsrn_old_rows(string $id):array{
    return [one('SELECT * FROM '.table('provider_force_ssl_changes').' WHERE id=?',[$id]),one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]),
        one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['forcessl:'.$id]),one('SELECT * FROM '.table('provider_force_ssl_dispatch_ends').' WHERE id=?',[$id]),
        all('SELECT * FROM '.table('provider_force_ssl_change_checks').' WHERE request_id=? ORDER BY id',[$id])];
}
if($worker)fsrn_worker();
$handle=fopen($report,'xb');if($handle===false)exit(2);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException('Native fixture PHP diagnostic',0,$severity,$file,$line);});
try{
    $stage='setup';$seed=fsrn_seed();extract($seed,EXTR_SKIP);$legacy=fsrn_old_rows($oldId);
    fsrn_ok('Request schema remains absent on owner GET before explicit preparation',fsrn_screen()['ready']===false&&!fsrn_exists());
    fsrn_deny('Wrong password cannot prepare client request history',fn()=>provider_force_ssl_requests_prepare('wrong'),'Your current password is not correct.');
    fsrn_owner(2);fsrn_deny('Permission-rich staff cannot prepare client requests',fn()=>provider_force_ssl_requests_prepare(FSRN_PASSWORD));fsrn_owner();
    db()->beginTransaction();try{fsrn_deny('Preparation refuses inherited transaction',fn()=>provider_force_ssl_requests_prepare(FSRN_PASSWORD));fsrn_ok('Refused preparation preserves caller transaction',db()->inTransaction());}finally{db()->rollBack();}
    db()->exec('CREATE TABLE fsrn_provider_force_ssl_requests_unknown (id INT) ENGINE=InnoDB');
    try{fsrn_deny('Unknown namespace table cannot be adopted',fn()=>provider_force_ssl_requests_prepare(FSRN_PASSWORD));}finally{db()->exec('DROP TABLE fsrn_provider_force_ssl_requests_unknown');}
    db()->exec('CREATE TABLE fsrn_provider_force_ssl_requests_events (id INT) ENGINE=InnoDB');
    try{fsrn_deny('Malformed empty request table cannot be silently upgraded',fn()=>provider_force_ssl_requests_prepare(FSRN_PASSWORD));fsrn_ok('Malformed table remains unchanged after refusal',count(all('SHOW COLUMNS FROM fsrn_provider_force_ssl_requests_events'))===1);}finally{db()->exec('DROP TABLE fsrn_provider_force_ssl_requests_events');}
    db()->exec(provider_force_ssl_requests_schema(FSRN_PREFIX));fsrn_ok('Exact empty interrupted schema stays unprepared',fsrn_screen()['ready']===false);
    fsrn_ok('Real preparation audit fault rolls marker and head back',fsrn_fault('prepare',fn()=>provider_force_ssl_requests_prepare(FSRN_PASSWORD))&&fsrn_screen()['ready']===false);
    provider_force_ssl_requests_prepare(FSRN_PASSWORD);$before=fsrn_digest();provider_force_ssl_requests_prepare(FSRN_PASSWORD);
    fsrn_ok('Explicit setup resumes exact schema and repeated setup is inert',fsrn_screen()['ready']===true&&fsrn_head()==='0:'.str_repeat('0',64)&&fsrn_digest()===$before);
    fsrn_ok('Existing v1 owner history survives new client storage preparation',fsrn_old_rows($oldId)===$legacy);
    $before=fsrn_digest();$io=[$dns,$gets,$posts];
    fsrn_deny('Internal send refuses a caller without owning mutex',fn()=>provider_force_ssl_controls_send_locked($oldId,'fsrn_write_dns','fsrn_perform'),'Force SSL dispatch requires the owning staff mutex.');
    fsrn_ok('Unowned internal dispatch does not change original history',fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);

    $stage='sharing-primary-client';fsrn_client();$before=fsrn_digest();$io=[$dns,$gets,$posts];
    fsrn_ok('Assigned client gets no choices before independent observation sharing',fsrn_screen(1)['options']===[]&&admin_user()===null);
    fsrn_ok('Unshared client GET performs no writes or provider work',fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    fsrn_owner();$share=provider_force_ssl_share($link['id'],'',bin2hex(random_bytes(16)),true,$anchor['id'],FSRN_PASSWORD);
    fsrn_client();$option=fsrn_option($link);$before=fsrn_digest();$io=[$dns,$gets,$posts];
    fsrn_ok('Explicit shared current assignment enables only original primary client option',count(fsrn_screen(1)['options'])===1&&$option['package_id']===801);
    fsrn_deny('Client cannot select another client identity',fn()=>fsrn_screen(2));
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;fsrn_deny('Raw null '.$key.' refuses before normalization',fn()=>fsrn_screen(1));unset($_SESSION[$key]);}
    $_GET['support']='synthetic';fsrn_deny('Support query cannot read client request history',fn()=>fsrn_screen(1));$_GET=[];
    $GLOBALS['cwp_support']=['id'=>'synthetic'];fsrn_deny('Resolved support cannot create client request',fn()=>fsrn_create(bin2hex(random_bytes(16)),$option));unset($GLOBALS['cwp_support']);
    $session=$_SESSION;$_SESSION=[];fsrn_deny('Anonymous caller cannot read client requests',fn()=>fsrn_screen(1));$_SESSION=$session;
    fsrn_deny('Request needs explicit consent',fn()=>provider_force_ssl_requests_create(bin2hex(random_bytes(16)),1,$link['id'],$option['review_sha256'],true,false));
    fsrn_deny('Changed reviewed resource hash refuses',fn()=>provider_force_ssl_requests_create(bin2hex(random_bytes(16)),1,$link['id'],str_repeat('0',64),true,true));
    fsrn_ok('Rejected client inputs leave all rows and transport untouched',fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    $cancelId=bin2hex(random_bytes(16));$count=fsrn_count('provider_force_ssl_requests_events');$audits=fsrn_event_audits();
    fsrn_ok('Real intake audit failure witnesses newly inserted event',fsrn_fault('intake',fn()=>fsrn_create($cancelId,$option)));
    fsrn_ok('Intake audit rollback leaves no intent grant or provider effect',fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    $created=fsrn_create($cancelId,$option);$original=fsrn_graph()['requests'][$cancelId]['request'];
    fsrn_ok('Primary client intake retains bool desired and actual auth with sharing source',$created['state']==='pending'&&$created['can_cancel']===true
        &&$original['payload']['desired']===true&&$original['actor']['kind']==='client'&&$original['actor']['id']===1
        &&$original['actor']['auth_sha256']===hash('sha256',(string)$_SESSION['client_auth'])
        &&$original['payload']['resource']['binding']['source_id']===$source['id']&&$original['payload']['resource']['sharing_id']===$share['document']['id']);
    fsrn_ok('Local request appends only one event audit and no owner grant or IO',fsrn_count('provider_force_ssl_requests_events')===$count+1&&fsrn_event_audits()===$audits+1&&fsrn_count('provider_operation_grants')===1&&[$dns,$gets,$posts]===$io);
    $before=fsrn_digest();fsrn_ok('Exact client create replay is inert',fsrn_create($cancelId,$option)===$created&&fsrn_digest()===$before);
    fsrn_deny('Existing request identity cannot change desired flag',fn()=>fsrn_create($cancelId,$option,false));
    fsrn_deny('Duplicate pending package request cannot reserve another nonce',fn()=>fsrn_create(bin2hex(random_bytes(16)),$option));
    fsrn_client(2);fsrn_ok('Unshared foreign client has no options or foreign items',fsrn_screen(2)['options']===[]&&fsrn_screen(2)['items']===[]);
    fsrn_deny('Foreign primary client cannot read request detail',fn()=>fsrn_item($cancelId,2));
    fsrn_deny('Foreign primary client cannot withdraw request',fn()=>provider_force_ssl_requests_change($cancelId,$cancelId,bin2hex(random_bytes(16)),'cancel','',2));

    $stage='withdraw-decline';fsrn_owner();$cancelReview=fsrn_item($cancelId);$cancelEvent=bin2hex(random_bytes(16));
    $race=fsrn_race(fsrn_worker_input('approve',$cancelId,$cancelReview['approval']['request_id'],$cancelReview),function()use($cancelId,$cancelEvent):void{fsrn_client();provider_force_ssl_requests_change($cancelId,$cancelId,$cancelEvent,'cancel','',1);},'Withdrawal-wins race');
    fsrn_ok('Blocked approval loses to actual withdrawal with no send',$race===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&fsrn_item($cancelId,1)['state']==='cancelled'&&[$dns,$gets,$posts]===$io);
    $before=fsrn_digest();provider_force_ssl_requests_change($cancelId,$cancelId,$cancelEvent,'cancel','',1);
    fsrn_ok('Cancel and original create retries retain terminal client decision',fsrn_create($cancelId,$option)['state']==='cancelled'&&fsrn_digest()===$before);
    $declineId=bin2hex(random_bytes(16));fsrn_create($declineId,$option);fsrn_owner();$declineEvent=bin2hex(random_bytes(16));
    $declined=provider_force_ssl_requests_change($declineId,$declineId,$declineEvent,'decline','Please verify the website certificate first.',null,FSRN_PASSWORD);
    fsrn_ok('Owner decline records explanation without provider work',$declined['state']==='declined'&&$declined['note']==='Please verify the website certificate first.'&&[$dns,$gets,$posts]===$io);
    $before=fsrn_digest();provider_force_ssl_requests_change($declineId,$declineId,$declineEvent,'decline','Please verify the website certificate first.',null,FSRN_PASSWORD);
    fsrn_ok('Exact decline retry is inert',fsrn_digest()===$before);

    $stage='fresh-owner-admission';fsrn_client();$mainId=bin2hex(random_bytes(16));fsrn_create($mainId,$option);fsrn_owner();$stale=fsrn_item($mainId);
    fsrn_ok('Ordinary fresh inventory retains pending static client assignment',fsrn_hosting($profile)['state']==='complete');$anchor=fsrn_flag($link,false);$before=fsrn_digest();$io=[$dns,$gets,$posts];
    fsrn_deny('Old owner anchor cannot newly admit after genuine later reads',fn()=>fsrn_approve($mainId,$stale));
    $review=fsrn_item($mainId);$approval=$review['approval'];$dispatch=$approval['request_id'];
    fsrn_ok('Fresh owner review uses new exact flag while retaining original client source',$review['state']==='pending'&&$approval['anchor_id']===$anchor['id']&&$approval['desired']===true&&$approval['before_value']===false&&fsrn_graph()['requests'][$mainId]['request']['payload']['resource']['binding']['source_id']===$source['id']);
    fsrn_deny('Owner dispatch needs confirmation',fn()=>provider_force_ssl_requests_approve($mainId,$mainId,$review['request_sha256'],$dispatch,$approval['anchor_id'],$approval['binding_sha256'],false,FSRN_PASSWORD,'fsrn_write_dns','fsrn_perform'));
    fsrn_deny('Owner dispatch needs current password',fn()=>provider_force_ssl_requests_approve($mainId,$mainId,$review['request_sha256'],$dispatch,$approval['anchor_id'],$approval['binding_sha256'],true,'wrong','fsrn_write_dns','fsrn_perform'),'Your current password is not correct.');
    fsrn_owner(2);fsrn_deny('Ordinary staff cannot approve client request',fn()=>fsrn_approve($mainId,$review));fsrn_owner();
    fsrn_ok('Late dispatch event audit sees both new owner request and consumed grant',fsrn_fault('dispatch',fn()=>fsrn_approve($mainId,$review)));
    fsrn_ok('Failed atomic admission restores all rows and leaves pending intent',fsrn_digest()===$before&&[$dns,$gets,$posts]===$io&&fsrn_item($mainId)['state']==='pending');
    $duringPost=function()use($dispatch):void{
        global $capturedAuthorize;$session=$_SESSION;$digest=fsrn_digest();
        $request=provider_force_ssl_controls_request($dispatch);
        foreach(['client authentication'=>['studio_clients','auth_version',1], 'owner authentication'=>['users','auth_version',1],
            'owner disabled'=>['staff_profiles','enabled',1], 'credential seal'=>['provider_profiles','credentials',$request['row']['profile_id']],
            'assignment client'=>['provider_package_links','client_id',$request['row']['link_id']]] as $label=>[$name,$column,$id]){
            $pk=$name==='staff_profiles'?'user_id':'id';$old=one('SELECT '.$column.' AS value FROM '.table($name).' WHERE '.$pk.'=?',[$id])['value'];
            $new=match($column){'client_id'=>2,'enabled'=>0,default=>bin2hex(random_bytes(16))};query('UPDATE '.table($name).' SET '.$column.'=? WHERE '.$pk.'=?',[$new,$id]);
            try{fsrn_deny('Real credential-send callback denies changed '.$label,$capturedAuthorize);}finally{query('UPDATE '.table($name).' SET '.$column.'=? WHERE '.$pk.'=?',[$old,$id]);$_SESSION=$session;unset($GLOBALS['cwp_settings']);}
        }
        fsrn_ok('Boundary probes restore exact rows and current owner session',fsrn_digest()===$digest&&$_SESSION===$session);
    };
    $race=fsrn_race(fsrn_worker_input('cancel',$mainId,bin2hex(random_bytes(16)),$review),function()use($mainId,$review):void{fsrn_owner();fsrn_approve($mainId,$review);},'Approval-wins race');
    fsrn_ok('Blocked withdrawal loses to single committed client-origin send',$race===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&$posts===2&&$gets===$io[1]&&$dns===$io[0]+1);
    $ownerRequest=fsrn_locked(fn()=>provider_force_ssl_controls_request($dispatch));$event=fsrn_graph()['requests'][$mainId]['events'][0];$ledger=$ownerRequest['ledger'];
    fsrn_ok('Owner v2 and dispatch reciprocally authenticate original client consent',$ownerRequest['document']['version']===2&&$ownerRequest['document']['client_origin']===['id'=>$mainId,'request_sha256'=>$review['request_sha256']]&&$event['id']===$dispatch&&$event['payload']['owner_request_sha256']===$ownerRequest['row']['document_sha256']);
    fsrn_ok('Actual gateway records one uncertain exact boolean POST',$ledger['status']==='uncertain'&&(int)$ledger['attempts']===1&&$wire[1]===['url'=>'https://api.20i.com/package/801/web/forceSSL','body'=>'{"value":true}']);
    $before=fsrn_digest();$io=[$dns,$gets,$posts];$approved=fsrn_approve($mainId,$review);
    fsrn_ok('Exact approval replay returns unknown without effects',$approved['state']==='unknown'&&$approved['owner_request_id']===$dispatch&&fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    fsrn_deny('Changed original request hash cannot replay approved intent',fn()=>provider_force_ssl_requests_approve($mainId,$mainId,str_repeat('0',64),$dispatch,$approval['anchor_id'],$approval['binding_sha256'],true,FSRN_PASSWORD,'fsrn_write_dns','fsrn_perform'));
    fsrn_client();$client=fsrn_item($mainId,1);$json=json_encode($client,JSON_THROW_ON_ERROR);
    fsrn_ok('Client receives unknown status without owner approval grants proofs or secrets',$client['state']==='unknown'&&$client['approval']===null&&$client['dispatch']===null&&$client['owner_request_id']===null&&$client['client']===null&&!str_contains($json,FSRN_SECRET)&&!str_contains($json,$approval['binding_sha256']));
    fsrn_deny('Client cannot call owner verification',fn()=>provider_force_ssl_requests_verify($mainId,$dispatch,FSRN_PASSWORD,'fsrn_read_dns','fsrn_read'));
    fsrn_deny('Unknown package effect fences fresh client nonce',fn()=>fsrn_create(bin2hex(random_bytes(16)),fsrn_option($link),false));
    fsrn_ok('Client GET and exact intake replay do not write or call provider',fsrn_create($mainId,$option)['state']==='unknown'&&fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);

    $stage='finished-ledger-boundaries';fsrn_owner();
    foreach(['missing','claimed'] as $mode)fsrn_unfinished_verify($dispatch,$ledger,$mode);
    $checksBefore=fsrn_count('provider_force_ssl_change_checks');$readsBefore=$gets;$postsBefore=$posts;$readStatus=200;$readBody='false';
    $duringRead=fn()=>query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE id=?',['success',time(),$ledger['id']]);
    try{fsrn_deny('Reconciliation during actual GET prevents saving client-origin check',fn()=>fsrn_verify($mainId,$dispatch,$profile),'This request has no eligible durable dispatch completion.');}
    finally{query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE id=?',[$ledger['reconciled_outcome'],$ledger['reconciled_epoch'],$ledger['id']]);$duringRead=null;}
    $latest=provider_force_ssl_latest($link['id']);
    fsrn_ok('Post-read completion guard retains exact receipt but no new check or send',$gets===$readsBefore+1&&$posts===$postsBefore&&$latest['state']==='complete'&&fsrn_count('provider_force_ssl_change_checks')===$checksBefore&&one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$ledger['id']])===$ledger);

    $stage='separate-readback';$readStatus=403;$readBody='null';$failed=fsrn_verify($mainId,$dispatch,$profile);
    fsrn_client();fsrn_ok('Failed fresh GET is unavailable not false and stays fenced',$failed['state']==='unknown'&&fsrn_item($mainId,1)['last_check']['disposition']==='unavailable'&&fsrn_pending($link)['id']===$dispatch);
    fsrn_owner();$readStatus=200;$readBody='false';$different=fsrn_verify($mainId,$dispatch,$profile);
    fsrn_client();fsrn_ok('Different fresh boolean stays unknown and fenced',$different['last_check']['disposition']==='different'&&fsrn_item($mainId,1)['state']==='unknown'&&fsrn_pending($link)['id']===$dispatch);
    fsrn_owner();$before=fsrn_digest();$io=[$dns,$gets,$posts];fsrn_approve($mainId,$review);fsrn_ok('Original owner approval replays after newer reads without resend',fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    $readBody='true';$matched=fsrn_verify($mainId,$dispatch,$profile);fsrn_client();$observed=fsrn_item($mainId,1);
    fsrn_ok('Only exact separate matching GET yields observed client status',$matched['state']==='observed'&&$observed['state']==='observed'&&$observed['last_check']['disposition']==='observed'&&fsrn_pending($link)===null);
    fsrn_ok('Readback preserves original immutable gateway result',one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$ledger['id']])===$ledger&&$posts===2);

    $stage='terminal-recovery';$option2=fsrn_option($link);$recoverId=bin2hex(random_bytes(16));fsrn_create($recoverId,$option2,false);fsrn_owner();$recoverReview=fsrn_item($recoverId);$recoverOwner=$recoverReview['approval']['request_id'];$terminalCount=fsrn_count('provider_force_ssl_dispatch_ends');
    fsrn_ok('Actual terminal audit failure occurs after one additional POST',fsrn_fault('terminal',fn()=>fsrn_approve($recoverId,$recoverReview))&&$posts===3);
    $interrupted=fsrn_item($recoverId);$hash=$interrupted['dispatch']['recovery_ledger_sha256'];$recoverLedger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['forcessl:'.$recoverOwner]);
    fsrn_ok('Failed local terminal retains origin and finished one-attempt ledger',$interrupted['state']==='interrupted'&&is_string($hash)&&strlen($hash)===64&&$recoverLedger['status']==='uncertain'&&(int)$recoverLedger['attempts']===1&&fsrn_count('provider_force_ssl_dispatch_ends')===$terminalCount);
    $before=fsrn_digest();$io=[$dns,$gets,$posts];fsrn_approve($recoverId,$recoverReview);
    fsrn_deny('Interrupted request cannot verify before local recovery',fn()=>provider_force_ssl_requests_verify($recoverId,$recoverOwner,FSRN_PASSWORD,'fsrn_read_dns','fsrn_read'));
    fsrn_deny('Recovery cannot attach another original approval',fn()=>provider_force_ssl_requests_recover($recoverId,$dispatch,$hash,FSRN_PASSWORD));
    fsrn_deny('Recovery requires exact finished ledger digest',fn()=>provider_force_ssl_requests_recover($recoverId,$recoverOwner,str_repeat('0',64),FSRN_PASSWORD));
    fsrn_owner(3);fsrn_deny('Different actual owner cannot recover original approval',fn()=>provider_force_ssl_requests_recover($recoverId,$recoverOwner,$hash,FSRN_PASSWORD));fsrn_owner();
    fsrn_ok('Interrupted replay and refused recovery add no provider or database effects',fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    $rows=fsrn_rows();$recovered=provider_force_ssl_requests_recover($recoverId,$recoverOwner,$hash,FSRN_PASSWORD);
    fsrn_ok('Explicit recovery adds only local terminal audit and counter',$recovered['state']==='unknown'&&fsrn_recovery_delta($rows,$recoverOwner)&&[$dns,$gets,$posts]===$io&&one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$recoverLedger['id']])===$recoverLedger);
    fsrn_deny('Existing terminal cannot be recovered twice',fn()=>provider_force_ssl_requests_recover($recoverId,$recoverOwner,$hash,FSRN_PASSWORD));

    $stage='history-integrity';$check=one('SELECT * FROM '.table('provider_force_ssl_change_checks').' WHERE request_id=? AND disposition=?',[$dispatch,'observed']);
    foreach([
        'client request'=>fn()=>query('DELETE FROM '.table('provider_force_ssl_requests_events').' WHERE id=?',[$mainId]),
        'client dispatch'=>fn()=>query('DELETE FROM '.table('provider_force_ssl_requests_events').' WHERE id=?',[$dispatch]),
        'paired dispatch and audit'=>function()use($dispatch):void{query('DELETE FROM '.table('provider_force_ssl_requests_events').' WHERE id=?',[$dispatch]);query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.force_ssl_requests_event','Force SSL request event '.$dispatch.' %']);},
        'canonical client payload'=>fn()=>query('UPDATE '.table('provider_force_ssl_requests_events').' SET document=? WHERE id=?',['{}',$mainId]),
        'head'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_force_ssl_requests_head']),
        'marker'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_force_ssl_requests_schema_version']),
        'preparation audit'=>fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=?',['provider.force_ssl_requests_prepared']),
        'unknown marker'=>fn()=>save_setting('provider_force_ssl_requests_extra','1'),
        'owner origin commitment'=>fn()=>query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',['{}',$dispatch]),
        'owner approval'=>function()use($dispatch):void{query('DELETE FROM '.table('provider_force_ssl_change_checks').' WHERE request_id=?',[$dispatch]);query('DELETE FROM '.table('provider_force_ssl_dispatch_ends').' WHERE id=?',[$dispatch]);query('DELETE FROM '.table('provider_force_ssl_changes').' WHERE id=?',[$dispatch]);},
        'terminal'=>fn()=>query('DELETE FROM '.table('provider_force_ssl_dispatch_ends').' WHERE id=?',[$dispatch]),
        'paired check and audit'=>function()use($check):void{query('DELETE FROM '.table('provider_force_ssl_change_checks').' WHERE id=?',[$check['id']]);query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.force_ssl_control_check','Force SSL change check '.$check['id'].' %']);},
        'reconciled original dispatch ledger'=>fn()=>query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE id=?',['success',time(),$ledger['id']]),
        'original flag observation'=>fn()=>query('DELETE FROM '.table('provider_force_ssl_observations').' WHERE id=?',[$check['receipt_id']]),
    ] as $name=>$mutate)fsrn_damage('Missing or changed '.$name.' refuses authenticated history',$mutate);

    $stage='revocation';$otherAnchor=fsrn_flag($other,true);$otherShare=provider_force_ssl_share($other['id'],'',bin2hex(random_bytes(16)),true,$otherAnchor['id'],FSRN_PASSWORD);
    fsrn_client(2);$otherOption=fsrn_option($other,2);$revokeId=bin2hex(random_bytes(16));fsrn_create($revokeId,$otherOption,false,2);fsrn_owner();$revokeReview=fsrn_item($revokeId);$revokeOwner=$revokeReview['approval']['request_id'];
    $auth=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=2')['auth_version'];$beforePosts=$posts;
    $duringDns=fn()=>query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=2',[bin2hex(random_bytes(16))]);
    try{$revoked=fsrn_approve($revokeId,$revokeReview);fsrn_ok('Original client auth changed during DNS prevents credentials and send',$revoked['state']==='not_sent'&&$revoked['current_assignment']===false&&$posts===$beforePosts&&one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['forcessl:'.$revokeOwner])===null);
        fsrn_deny('Revoked original client auth denies exact owner replay',fn()=>fsrn_approve($revokeId,$revokeReview));
    }finally{query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=2',[$auth]);fsrn_owner();}
    $stage='daily-intake-limit';fsrn_client(2);$limitOption=fsrn_option($other,2);$limitLast='';$limitEvent='';
    for($i=2;$i<=10;$i++){
        $limitLast=bin2hex(random_bytes(16));fsrn_create($limitLast,$limitOption,false,2);$limitEvent=bin2hex(random_bytes(16));
        provider_force_ssl_requests_change($limitLast,$limitLast,$limitEvent,'cancel','',2);
    }
    fsrn_ok('Ten actual client attempts across all outcomes consume daily intake allowance',count(fsrn_screen(2)['items'])===10);
    $before=fsrn_digest();$io=[$dns,$gets,$posts];
    fsrn_deny('Eleventh daily client attempt refuses before event or provider work',fn()=>fsrn_create(bin2hex(random_bytes(16)),$limitOption,false,2),'The daily HTTPS request limit has been reached. Please try later.');
    fsrn_ok('Exact cancelled intake and decision retries do not consume daily capacity',fsrn_create($limitLast,$limitOption,false,2)['state']==='cancelled'
        &&provider_force_ssl_requests_change($limitLast,$limitLast,$limitEvent,'cancel','',2)['state']==='cancelled'&&fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    fsrn_owner();
    $shareNow=fsrn_locked(fn()=>provider_force_ssl_permission($link['id']));provider_force_ssl_share($link['id'],$shareNow['document']['id'],bin2hex(random_bytes(16)),false,'',FSRN_PASSWORD);
    $before=fsrn_digest();$io=[$dns,$gets,$posts];fsrn_deny('Sharing revocation blocks original owner verification',fn()=>provider_force_ssl_requests_verify($recoverId,$recoverOwner,FSRN_PASSWORD,'fsrn_read_dns','fsrn_read'));
    fsrn_deny('Direct owner verification also checks client-origin sharing',fn()=>provider_force_ssl_controls_verify($recoverOwner,FSRN_PASSWORD,'fsrn_read_dns','fsrn_read'));
    fsrn_client();$history=fsrn_item($recoverId,1);fsrn_ok('Revoked sharing hides choices but retains safe own unknown history',$history['state']==='unknown'&&$history['current_assignment']===false&&fsrn_screen(1)['options']===[]&&fsrn_pending($link)['id']===$recoverOwner);
    fsrn_ok('Revoked read and GET history create no effects',fsrn_digest()===$before&&[$dns,$gets,$posts]===$io);
    fsrn_owner();provider_settings_save($profile,1,'Rotated Force SSL request credential',true,'SYNTHETIC_ROTATED_FORCE_SSL_KEY',FSRN_PASSWORD);
    fsrn_deny('Rotated account credential blocks exact old owner replay',fn()=>fsrn_approve($recoverId,$recoverReview));
    provider_settings_save($profile,2,'Restored key new account revision',true,FSRN_SECRET,FSRN_PASSWORD);fsrn_ok('Replacement account revision gets real complete inventory',fsrn_hosting($profile)['state']==='complete');
    provider_inventory_unlink($link['id'],FSRN_PASSWORD);provider_inventory_link($profile,801,2,3,FSRN_PASSWORD);fsrn_ok('Reassignment gets genuine post-assignment inventory',fsrn_hosting($profile)['state']==='complete');
    fsrn_client(2);fsrn_deny('New assignee cannot read old client request',fn()=>fsrn_item($recoverId,2));
    fsrn_ok('Original unknown effect fence survives key and client replacement',fsrn_pending($link)['id']===$recoverOwner);
    fsrn_client();fsrn_ok('Original client retains dated history without changed-resource authority',fsrn_item($recoverId,1)['current_assignment']===false);
    fsrn_owner();fsrn_ok('Entire original owner v1 request grant ledger and readback remain byte-exact',fsrn_old_rows($oldId)===$legacy);
    fsrn_ok('Final authenticated request graph remains valid',is_array(fsrn_locked(fn()=>provider_force_ssl_requests_state())));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure in '.$stage,'passed'=>false];$failure=['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null];}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
$result=['passed'=>$passed,'scope'=>'Isolated native SQL, actual primary-client and owner wrappers, real shared gateway and observed process mutex races; provider transport synthetic. No live provider, browser, certificate or HTTPS reachability claim.','checks'=>$checks];
if(isset($failure))$result['failure']=$failure;fwrite($handle,json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");fclose($handle);
echo ($passed?'PASS':'FAIL').': '.count($checks).' Force SSL request native checks'.PHP_EOL;exit($passed?0:1);
