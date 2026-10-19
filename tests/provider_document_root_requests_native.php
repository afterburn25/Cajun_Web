<?php
/** Isolated native SQL/owner/gateway acceptance. All provider responses are synthetic. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_DOCUMENT_ROOT_REQUESTS')!=='1'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
$worker=count($argv)===2&&$argv[1]==='--worker';
if(count($argv)!==($worker?2:1))exit(2);
$port=getenv('CWP_TEST_PORT')?:'33306';if(!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/document_root_requests.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIDocumentRoots\{DocumentRootsBinding,DocumentRootsObservation,DocumentRootsReader};
use CajunWebPro\Foundation\TwentyI\Operations\{DocumentRootChange,TwentyIOperationAdapter};

const DRRQ_PREFIX='drrq_';
const DRRQ_PASSWORD='Synthetic client Website folder owner passphrase';
const DRRQ_SECRET='SYNTHETIC_DOCUMENT_ROOT_REQUEST_KEY';
const DRRQ_NAME='site801.synthetic.invalid';
$root=realpath(__DIR__.'/..');if(!$root)exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';$private=$runtime.'/document-root-requests-private';
$report=$qa.'/provider-document-root-requests-native-results.json';
foreach([$runtime,$qa] as $path){if(is_link($path)||(file_exists($path)&&!is_dir($path)))exit(2);if(!is_dir($path)&&!mkdir($path,0700))exit(2);}
if(is_link($private)||($worker?!is_dir($private):file_exists($private))||(!$worker&&(is_link($report)||file_exists($report))))exit(2);
if(!$worker&&!mkdir($private,0700))exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/document-root-requests','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-document-root-requests-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$port,'name'=>'cwp_test',
    'user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>DRRQ_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root.'/site';start_session();
$checks=[];$stage='preflight';$dns=0;$gets=0;$posts=0;$wire=[];$duringDns=null;$duringPost=null;$duringRead=null;$capturedAuthorize=null;$capturedRequest=null;
$readStatus=200;$readBody='{}';

function drrq_ok(string $name,bool $passed):void{global $checks;foreach($checks as $check)if($check['name']===$name)throw new LogicException('Duplicate fixture assertion.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function drrq_deny(string $name,callable $call,?string $message=null):void{
    $caught=null;try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    drrq_ok($name,$caught!==null&&($message===null||$caught->getMessage()===$message));
}
function drrq_owner(int $id=1):void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function drrq_client(int $id=1):void{
    $u=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);
    $_SESSION=['client_id'=>$id,'client_auth'=>$u['auth_version'],'client_login'=>time(),'client_last'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function drrq_locked(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function drrq_mutex():bool{
    $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.DRRQ_PREFIX),0,40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;
}
function drrq_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function drrq_exists():bool{return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY TABLE_NAME=?',[DRRQ_PREFIX.'provider_website_folder_requests_events'])!==null;}
function drrq_counts():array{global $dns,$gets,$posts;return [$dns,$gets,$posts,...array_map('drrq_count',['provider_website_folder_requests_events','provider_document_root_changes','provider_document_root_dispatch_ends','provider_document_root_change_checks','provider_operation_grants','provider_operations','provider_refreshes','audit'])];}
function drrq_rows():array{
    $rows=[];foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(DRRQ_PREFIX),DRRQ_PREFIX]) as $t){
        $name=$t['TABLE_NAME'];if(!preg_match('/\Adrrq_[a-z0-9_]+\z/',$name))throw new LogicException('Unexpected fixture table.');
        $data=all('SELECT * FROM `'.$name.'`');usort($data,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$data;
    }return $rows;
}
function drrq_digest():string{return hash('sha256',json_encode(drrq_rows(),JSON_THROW_ON_ERROR));}
/** A genuine bounded cooldown; no saved date, quota or clock is changed. */
function drrq_wait(string $profile):void{
    if(db()->inTransaction()||drrq_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');
    $deadline=microtime(true)+32;while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
function drrq_launch(array $input):array{
    $process=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('Could not start client-request worker.');
    $json=json_encode($input,JSON_THROW_ON_ERROR);
    if(fwrite($pipes[0],$json)!==strlen($json))throw new RuntimeException('Could not send worker input.');
    fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    return ['process'=>$process,'pipes'=>$pipes,'stdout'=>'','stderr'=>'','connection'=>null];
}
/** Require a real blocked child GET_LOCK while this connection owns the exact mutex. */
function drrq_blocked(array &$worker):void{
    if(!drrq_mutex()||db()->inTransaction())throw new LogicException('Race observer needs owning mutex without transaction.');
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
            if($row&&stripos((string)$row['INFO'],'GET_LOCK')!==false&&stripos((string)$row['STATE'],'lock')!==false&&drrq_mutex())return;
        }
        $status=proc_get_status($worker['process']);if(!$status['running'])throw new RuntimeException('Worker exited before observed lock contention.');
        if(microtime(true)>$deadline)throw new RuntimeException('Actual blocked worker was not observed within lock budget.');usleep(20000);
    }while(true);
}
function drrq_join(array &$worker):array{
    $exit=null;$deadline=microtime(true)+12;
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
function drrq_race(array $input,callable $winner,string $label):array{
    $child=null;try{staff_locked(function()use($input,$winner,$label,&$child):void{
        $child=drrq_launch($input);drrq_blocked($child);drrq_ok($label.' observes a separate blocked GET_LOCK',true);$winner();
    });}finally{if($child!==null)$result=drrq_join($child);}return $result;
}
function drrq_boundary(callable $authorize):void{$authorize();if(db()->inTransaction()||!drrq_mutex())throw new LogicException('Transport needs owning mutex outside transaction.');}
function drrq_read_dns():string{global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';}
function drrq_write_dns():string{
    global $dns,$duringDns;if(db()->inTransaction()||!drrq_mutex())throw new LogicException('Write DNS needs owning mutex outside transaction.');
    $row=one('SELECT * FROM '.table('provider_document_root_changes').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Missing durable approval before DNS.');$q=provider_document_root_controls_request($row['id']);
    if($q['grant']['consumed_key']!=='documentroot:'.$row['id']||$q['ledger']!==null
        ||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',
            ['provider.document_root_control_request','Document-root change request '.$row['id'].' %'])->fetchColumn()!==1)throw new LogicException('Consumed approval audit must precede DNS.');
    if($q['document']['version']===2){$origin=$q['document']['client_origin'];
        if(!one('SELECT id FROM '.table('provider_website_folder_requests_events').' WHERE id=? AND subject_id=? AND kind=?',[$row['id'],$origin['id'],'dispatch']))throw new LogicException('Client dispatch must commit with approval before DNS.');}
    $dns++;if($duringDns){$call=$duringDns;$duringDns=null;$call();}return '8.8.8.8';
}
final class DrrqReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readBody,$readStatus,$duringRead;drrq_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null
            ||$request->headers!==['Authorization'=>'Bearer '.base64_encode(DRRQ_SECRET),'Accept'=>'application/json'])throw new LogicException('Unexpected fixed synthetic GET.');
        $gets++;if($duringRead){$call=$duringRead;$duringRead=null;$call();}return new TransportResponse($readStatus,[],$readBody);
    }
}
function drrq_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?DocumentRootsBinding $documentRootsContext=null):InventorySnapshot|DocumentRootsObservation{
    drrq_boundary($authorize);if($key!==DRRQ_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{drrq_boundary($authorize);return $key;});
    if($operation===InventoryRead::PACKAGES_LIST)return InventoryReader::read(new DrrqReadTransport($authorize,'https://api.20i.com/package'),$credential,$operation,null,time());
    if($operation!==DocumentRootsBinding::OPERATION||!$documentRootsContext)throw new LogicException('Wrong Website folder read context.');
    return DocumentRootsReader::read(new DrrqReadTransport($authorize,'https://api.20i.com/package/'.$documentRootsContext->data['package']['package_id'].'/web/documentRoots'),$credential,$documentRootsContext,time());
}
final class DrrqWriteTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$wire,$duringPost,$capturedRequest;drrq_boundary($this->authorize);DocumentRootChange::wire($request);
        if(!in_array($request->url,['https://api.20i.com/package/801/web/updateDocRoot','https://api.20i.com/package/802/web/updateDocRoot'],true)
            ||($request->headers['Authorization']??null)!=='Bearer '.base64_encode(DRRQ_SECRET))throw new LogicException('Wrong synthetic write account.');
        $capturedRequest=$request;$posts++;$wire[]=['url'=>$request->url,'body'=>$request->body];if($duringPost){$call=$duringPost;$duringPost=null;$call();}
        return new TransportResponse(200,[],'true');
    }
}
function drrq_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    global $capturedAuthorize;$capturedAuthorize=$authorize;drrq_boundary($authorize);
    if($key!==DRRQ_SECRET||$address!=='8.8.8.8'||$operation->name!==DocumentRootChange::OPERATION)throw new LogicException('Wrong synthetic operation identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),static function()use($authorize,$key):string{drrq_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new DrrqWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function drrq_package(int $id):array{return ['id'=>$id,'name'=>'site'.$id.'.synthetic.invalid','names'=>['site'.$id.'.synthetic.invalid'],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];}
function drrq_hosting(string $profile):array{
    global $readBody,$readStatus;drrq_wait($profile);$readStatus=200;$readBody=json_encode(array_map('drrq_package',[801,802]),JSON_THROW_ON_ERROR);
    return provider_inventory_refresh_attempt($profile,(int)provider_settings_load($profile)['revision'],DRRQ_PASSWORD,'drrq_read_dns','drrq_read');
}
function drrq_map_body(int $id,string $path):string {
    return json_encode(['site'.$id.'.synthetic.invalid'=>$path,'other'.$id.'.synthetic.invalid'=>'public_html/other-site'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
}
function drrq_map(array $link,string $path):array{
    global $readBody,$readStatus;drrq_wait($link['profile_id']);$readStatus=200;$readBody=drrq_map_body((int)$link['package_id'],$path);
    $binding=provider_document_roots_binding($link['id']);$r=provider_document_roots_refresh($link['id'],$binding,DRRQ_PASSWORD,'drrq_read_dns','drrq_read');
    if($r['state']!=='complete')throw new RuntimeException('Synthetic map did not complete.');return provider_document_roots_observation(provider_document_roots_source($r['id']));
}
function drrq_screen(?int $client=null,?string $id=null):array{return provider_document_root_requests_screen($client,1,$id);}
function drrq_item(string $id,?int $client=null):array{
    $items=array_values(array_filter(drrq_screen($client,$id)['items'],fn($r)=>$r['id']===$id));if(count($items)!==1)throw new RuntimeException('Expected original request.');return $items[0];
}
function drrq_option(array $link,int $client=1):array{
    $items=array_values(array_filter(drrq_screen($client)['options'],fn($r)=>$r['link_id']===$link['id']));if(count($items)!==1)throw new RuntimeException('Expected shared assigned package.');return $items[0];
}
function drrq_create(string $id,array $option,string $path='public_html/new',int $client=1):array{return provider_document_root_requests_create($id,$client,$option['link_id'],$option['review_sha256'],'site'.$option['package_id'].'.synthetic.invalid',$path,true);}
function drrq_approve(string $id,array $item,?callable $resolver=null):array{
    $a=$item['approval'];return provider_document_root_requests_approve($id,$item['latest_event'],$item['request_sha256'],$a['request_id'],$a['anchor_id'],$a['binding_sha256'],true,DRRQ_PASSWORD,$resolver??'drrq_write_dns','drrq_perform');
}
function drrq_verify(string $id,string $ownerId,string $profile):array{drrq_wait($profile);return provider_document_root_requests_verify($id,$ownerId,DRRQ_PASSWORD,'drrq_read_dns','drrq_read');}
function drrq_graph():array{return drrq_locked(fn()=>provider_document_root_requests_graph());}
function drrq_pending(array $link):?array{return drrq_locked(fn()=>provider_document_root_controls_pending($link['profile_id'],(int)$link['package_id']));}
function drrq_head():string{return (string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_website_folder_requests_head'])['value']??'');}
function drrq_event_audits():int{return (int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.website_folder_requests_event'])->fetchColumn();}
/** Real INSERT failure and connection variables witness rows before rollback. */
function drrq_fault(string $phase,callable $call):bool{
    $actions=['prepare'=>'provider.website_folder_requests_prepared','intake'=>'provider.website_folder_requests_event','dispatch'=>'provider.website_folder_requests_event','terminal'=>'provider.document_root_control_terminal'];
    if(!isset($actions[$phase]))throw new LogicException('Unknown fixture fault.');
    $tables=['provider_website_folder_requests_events','provider_document_root_changes','provider_operation_grants','provider_document_root_dispatch_ends'];$before=array_map('drrq_count',$tables);
    $sample='';foreach($tables as $i=>$t)$sample.='SET @drrq_'.$i.'=(SELECT COUNT(*) FROM '.table($t).'); ';
    db()->exec('CREATE TRIGGER drrq_audit_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='".$actions[$phase]."' THEN SET @drrq_hit=COALESCE(@drrq_hit,0)+1; ".$sample."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic Website folder audit fault'; END IF; END");
    query('SET @drrq_hit=0');$caught=false;try{$call();}catch(PDOException $e){
        $delta=match($phase){'prepare'=>[0,0,0,0],'intake'=>[1,0,0,0],'dispatch'=>[1,1,1,0],'terminal'=>[1,1,1,1]};
        $caught=$e->getCode()==='45000'&&(int)query('SELECT @drrq_hit')->fetchColumn()===1;
        foreach($before as $i=>$n)$caught=$caught&&(int)query('SELECT @drrq_'.$i)->fetchColumn()===$n+$delta[$i];
    }finally{db()->exec('DROP TRIGGER drrq_audit_fault');}return $caught;
}
function drrq_damage(string $name,callable $mutate):void{
    global $dns,$gets,$posts;$before=drrq_digest();$io=[$dns,$gets,$posts];$session=$_SESSION;
    staff_locked(function()use($name,$mutate):void{db()->beginTransaction();try{$mutate();unset($GLOBALS['cwp_settings']);drrq_deny($name,fn()=>provider_document_root_requests_state());}finally{if(db()->inTransaction())db()->rollBack();}});
    $_SESSION=$session;unset($GLOBALS['cwp_settings']);drrq_ok($name.' exact rollback restores history',drrq_digest()===$before&&[$dns,$gets,$posts]===$io&&is_array(drrq_locked(fn()=>provider_document_root_requests_state())));
}
function drrq_recovery_delta(array $before,string $id):bool{
    $after=drrq_rows();$terminal=one('SELECT * FROM '.table('provider_document_root_dispatch_ends').' WHERE id=?',[$id]);if(!$terminal)return false;
    $detail='Document-root change terminal '.$id.' '.provider_document_roots_digest(provider_document_root_controls_values('terminal',$terminal));
    foreach(['provider_document_root_dispatch_ends'=>fn($r)=>$r['id']===$id,'audit'=>fn($r)=>$r['action']==='provider.document_root_control_terminal'&&$r['detail']===$detail&&(int)$r['user_id']===1] as $name=>$match){
        $key=DRRQ_PREFIX.$name;if(count(array_filter($after[$key],$match))!==1||array_filter($before[$key],$match)!==[])return false;$after[$key]=array_values(array_filter($after[$key],fn($r)=>!$match($r)));
    }
    $name='provider_document_root_control_terminal_count';$key=DRRQ_PREFIX.'settings';$old=array_values(array_filter($before[$key],fn($r)=>$r['name']===$name));if(count($old)!==1)return false;$found=0;
    foreach($after[$key] as &$row)if($row['name']===$name){if((int)$row['value']!==(int)$old[0]['value']+1)return false;$row=$old[0];$found++;}unset($row);if($found!==1)return false;
    foreach($after as &$rows)usort($rows,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));unset($rows);return $after===$before;
}
/** Disposable negative fixture: authenticated attempted terminal, but no eligible finished ledger.
 * Rebind only its terminal proof/audit so verification reaches the durable-completion gate;
 * finally restore the exact original ledger, terminal and audit. No production recovery uses this. */
function drrq_unfinished_verify(string $id,array $ledger,string $mode):void{
    global $dns,$gets,$posts;
    if(!in_array($mode,['missing','claimed'],true))throw new LogicException('Unknown incomplete ledger fixture.');
    $before=drrq_digest();$io=[$dns,$gets,$posts];
    $terminal=one('SELECT * FROM '.table('provider_document_root_dispatch_ends').' WHERE id=?',[$id]);
    $detail='Document-root change terminal '.$id.' '.provider_document_roots_digest(provider_document_root_controls_values('terminal',$terminal));
    $audit=one('SELECT * FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.document_root_control_terminal',$detail]);
    if(!$audit)throw new LogicException('Original terminal audit absent.');
    $replacement=null;if($mode==='claimed'){$replacement=$ledger;foreach(['operation','provider_code','external_id','resolved_epoch','reconciled_outcome','reconciled_epoch'] as $key)$replacement[$key]=null;$replacement['status']='claimed';$replacement['result_message']='';$replacement['attempts']=0;}
    $write=static function(?array $row,array $end,string $auditDetail)use($ledger,$id,$audit):void{
        query('DELETE FROM '.table('provider_operations').' WHERE id=?',[$ledger['id']]);
        if($row)query('INSERT INTO '.table('provider_operations').' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
        query('UPDATE '.table('provider_document_root_dispatch_ends').' SET ledger_sha256=?,document_json=?,document_sha256=?,proof=? WHERE id=?',[$end['ledger_sha256'],$end['document_json'],$end['document_sha256'],$end['proof'],$id]);
        query('UPDATE '.table('audit').' SET detail=? WHERE id=?',[$auditDetail,$audit['id']]);
    };
    $end=$terminal;$d=json_decode($end['document_json'],true,20,JSON_THROW_ON_ERROR);$d['ledger_sha256']=provider_document_root_controls_ledger_digest($replacement);
    $end['ledger_sha256']=$d['ledger_sha256'];$end['document_json']=provider_document_roots_json($d);$end['document_sha256']=hash('sha256',$end['document_json']);unset($end['proof']);$end=provider_document_root_controls_prove('terminal',$end);
    $negativeDetail='Document-root change terminal '.$id.' '.provider_document_roots_digest(provider_document_root_controls_values('terminal',$end));
    try{
        drrq_locked(fn()=>$write($replacement,$end,$negativeDetail));
        drrq_ok('Authenticated attempted terminal with '.$mode.' ledger remains structurally readable',is_array(drrq_locked(fn()=>provider_document_root_requests_state())));
        drrq_deny('Crafted v2 verification refuses '.$mode.' ledger before DNS',fn()=>provider_document_root_controls_verify($id,DRRQ_PASSWORD,'drrq_read_dns','drrq_read'),'This request has no eligible durable dispatch completion.');
    }finally{drrq_locked(fn()=>$write($ledger,$terminal,$detail));}
    drrq_ok('Incomplete '.$mode.' ledger probe restores exact rows without IO',drrq_digest()===$before&&[$dns,$gets,$posts]===$io);
}
function drrq_worker_input(string $action,string $id,string $event,array $item):array{
    return ['action'=>$action,'id'=>$id,'expected'=>$item['latest_event'],'event'=>$event,'request_sha256'=>$item['request_sha256'],'anchor_id'=>$item['approval']['anchor_id'],'binding_sha256'=>$item['approval']['binding_sha256']];
}
function drrq_worker():never{
    global $dns,$gets,$posts;try{
        $in=json_decode(stream_get_contents(STDIN,8193),true,6,JSON_THROW_ON_ERROR);
        if(!is_array($in)||array_keys($in)!==['action','id','expected','event','request_sha256','anchor_id','binding_sha256']||!in_array($in['action'],['cancel','approve','approve-owner-revoked','approve-client-revoked'],true))throw new RuntimeException('Invalid worker command.');
        foreach(['id','expected','event','anchor_id'] as $key)if(!is_string($in[$key])||!preg_match('/\A[a-f0-9]{32}\z/',$in[$key]))throw new RuntimeException('Invalid worker identity.');
        foreach(['request_sha256','binding_sha256'] as $key)if(!is_string($in[$key])||!preg_match('/\A[a-f0-9]{64}\z/',$in[$key]))throw new RuntimeException('Invalid worker proof.');
        if($in['action']==='cancel')drrq_client();else drrq_owner();
        echo json_encode(['ready'=>true,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR)."\n";flush();$outcome='completed';
        try{if($in['action']==='cancel')provider_document_root_requests_change($in['id'],$in['expected'],$in['event'],'cancel','',1);
            else provider_document_root_requests_approve($in['id'],$in['expected'],$in['request_sha256'],$in['event'],$in['anchor_id'],$in['binding_sha256'],true,DRRQ_PASSWORD,'drrq_write_dns','drrq_perform');
        }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;
            $message=match($in['action']){
                'cancel'=>'This website folder request changed. Reload its review.',
                'approve-owner-revoked'=>'An active owner account is required for provider settings.',
                'approve-client-revoked'=>'The client account changed. Ask for a new website folder request.',
                default=>'This request was already reviewed or withdrawn.',
            };
            if($e->getMessage()!==$message)throw $e;$outcome='refused';}
        echo json_encode(['outcome'=>$outcome,'dns'=>$dns,'gets'=>$gets,'posts'=>$posts],JSON_THROW_ON_ERROR)."\n";exit;
    }catch(Throwable $e){echo json_encode(['failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR)."\n";exit(1);}
}
function drrq_seed():array{
    drrq_ok('Dedicated native namespace begins empty',(string)query('SELECT DATABASE()')->fetchColumn()==='cwp_test'&&(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(DRRQ_PREFIX),DRRQ_PREFIX])->fetchColumn()===0);
    foreach(schema_statements(DRRQ_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $k=>$v)save_setting($k,$v);
    foreach([1,2,3] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Website folder requests staff '.$id,'drrq'.$id.'@example.invalid',password_hash(DRRQ_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Website folder requests client '.$id,'drrq-client'.$id.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    drrq_owner();provider_settings_migrate(DRRQ_PASSWORD);provider_inventory_migrate(DRRQ_PASSWORD);provider_operations_migrate(DRRQ_PASSWORD);
    $profile=provider_settings_save('',0,'Client Website folder request fixture',true,DRRQ_SECRET,DRRQ_PASSWORD);provider_document_roots_prepare(DRRQ_PASSWORD);provider_document_root_controls_prepare(DRRQ_PASSWORD);
    drrq_ok('Real initial package reader completes with synthetic transport',drrq_hosting($profile)['state']==='complete');
    foreach([801,802] as $package)provider_inventory_link($profile,$package,$package===802?2:1,1,DRRQ_PASSWORD);
    $source=drrq_hosting($profile);drrq_ok('Real post-assignment package read completes',$source['state']==='complete');
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=801');$other=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=802');
    $oldAnchor=drrq_map($other,'');$oldBinding=provider_document_roots_binding($other['id']);$oldId=bin2hex(random_bytes(16));
    $old=provider_document_root_controls_change($oldId,$other['id'],$oldAnchor['id'],$oldBinding->sha256,'site802.synthetic.invalid','public_html/legacy',true,DRRQ_PASSWORD,'drrq_write_dns','drrq_perform');
    drrq_wait($profile);$GLOBALS['readBody']=drrq_map_body(802,'public_html/legacy');$old=provider_document_root_controls_verify($oldId,DRRQ_PASSWORD,'drrq_read_dns','drrq_read');
    drrq_ok('Existing owner-only v1 flow dispatches and observes before client preparation',$old['status']==='observed'&&drrq_locked(fn()=>provider_document_root_controls_request($oldId))['document']['version']===1&&!drrq_exists());
    $anchor=drrq_map($link,'');return compact('profile','source','link','other','anchor','oldId');
}
function drrq_old_rows(string $id):array{
    return [one('SELECT * FROM '.table('provider_document_root_changes').' WHERE id=?',[$id]),one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]),
        one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$id]),one('SELECT * FROM '.table('provider_document_root_dispatch_ends').' WHERE id=?',[$id]),
        all('SELECT * FROM '.table('provider_document_root_change_checks').' WHERE request_id=? ORDER BY id',[$id])];
}
if($worker)drrq_worker();
$handle=fopen($report,'xb');if($handle===false)exit(2);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException('Native fixture PHP diagnostic',0,$severity,$file,$line);});
try{
    $stage='setup';$seed=drrq_seed();extract($seed,EXTR_SKIP);$legacy=drrq_old_rows($oldId);
    drrq_ok('Request schema remains absent on owner GET before explicit preparation',drrq_screen()['ready']===false&&!drrq_exists());
    drrq_deny('Wrong password cannot prepare client request history',fn()=>provider_document_root_requests_prepare('wrong'),'Your current password is not correct.');
    drrq_owner(2);drrq_deny('Permission-rich staff cannot prepare client requests',fn()=>provider_document_root_requests_prepare(DRRQ_PASSWORD));drrq_owner();
    db()->beginTransaction();try{drrq_deny('Preparation refuses inherited transaction',fn()=>provider_document_root_requests_prepare(DRRQ_PASSWORD));drrq_ok('Refused preparation preserves caller transaction',db()->inTransaction());}finally{db()->rollBack();}
    db()->exec('CREATE TABLE drrq_provider_website_folder_requests_unknown (id INT) ENGINE=InnoDB');
    try{drrq_deny('Unknown namespace table cannot be adopted',fn()=>provider_document_root_requests_prepare(DRRQ_PASSWORD));}finally{db()->exec('DROP TABLE drrq_provider_website_folder_requests_unknown');}
    db()->exec('CREATE TABLE drrq_provider_website_folder_requests_events (id INT) ENGINE=InnoDB');
    try{drrq_deny('Malformed empty request table cannot be silently upgraded',fn()=>provider_document_root_requests_prepare(DRRQ_PASSWORD));drrq_ok('Malformed table remains unchanged after refusal',count(all('SHOW COLUMNS FROM drrq_provider_website_folder_requests_events'))===1);}finally{db()->exec('DROP TABLE drrq_provider_website_folder_requests_events');}
    db()->exec(provider_document_root_requests_schema(DRRQ_PREFIX));drrq_ok('Exact empty interrupted schema stays unprepared',drrq_screen()['ready']===false);
    drrq_ok('Real preparation audit fault rolls marker and head back',drrq_fault('prepare',fn()=>provider_document_root_requests_prepare(DRRQ_PASSWORD))&&drrq_screen()['ready']===false);
    provider_document_root_requests_prepare(DRRQ_PASSWORD);$before=drrq_digest();provider_document_root_requests_prepare(DRRQ_PASSWORD);
    drrq_ok('Explicit setup resumes exact schema and repeated setup is inert',drrq_screen()['ready']===true&&drrq_head()==='0:'.str_repeat('0',64)&&drrq_digest()===$before);
    drrq_ok('Prepared original owner readiness works outside transaction and mutex',!db()->inTransaction()&&!drrq_mutex()&&provider_document_root_controls_ready());
    drrq_ok('Existing v1 owner history survives new client storage preparation',drrq_old_rows($oldId)===$legacy);
    $before=drrq_digest();$io=[$dns,$gets,$posts];
    drrq_deny('Internal send refuses a caller without owning mutex',fn()=>provider_document_root_controls_send_locked($oldId,'drrq_write_dns','drrq_perform'),'Website folder dispatch requires the owning staff mutex.');
    drrq_ok('Unowned internal dispatch does not change original history',drrq_digest()===$before&&[$dns,$gets,$posts]===$io);

    $stage='sharing-primary-client';drrq_client();$before=drrq_digest();$io=[$dns,$gets,$posts];
    drrq_ok('Assigned client gets no choices before independent observation sharing',drrq_screen(1)['options']===[]&&admin_user()===null);
    drrq_ok('Unshared client GET performs no writes or provider work',drrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    drrq_owner();$share=provider_document_roots_share($link['id'],'',bin2hex(random_bytes(16)),true,$anchor['id'],DRRQ_PASSWORD);
    drrq_client();$option=drrq_option($link);$before=drrq_digest();$io=[$dns,$gets,$posts];
    drrq_ok('Explicit shared current assignment enables only original primary client option',count(drrq_screen(1)['options'])===1&&$option['package_id']===801);
    drrq_deny('Client cannot select another client identity',fn()=>drrq_screen(2));
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;drrq_deny('Raw null '.$key.' refuses before normalization',fn()=>drrq_screen(1));unset($_SESSION[$key]);}
    $_GET['support']='synthetic';drrq_deny('Support query cannot read client request history',fn()=>drrq_screen(1));$_GET=[];
    $GLOBALS['cwp_support']=['id'=>'synthetic'];drrq_deny('Resolved support cannot create client request',fn()=>drrq_create(bin2hex(random_bytes(16)),$option));unset($GLOBALS['cwp_support']);
    $session=$_SESSION;$_SESSION=[];drrq_deny('Anonymous caller cannot read client requests',fn()=>drrq_screen(1));$_SESSION=$session;
    drrq_deny('Request needs explicit consent',fn()=>provider_document_root_requests_create(bin2hex(random_bytes(16)),1,$link['id'],$option['review_sha256'],DRRQ_NAME,'public_html/new',false));
    drrq_deny('Changed reviewed resource hash refuses',fn()=>provider_document_root_requests_create(bin2hex(random_bytes(16)),1,$link['id'],str_repeat('0',64),DRRQ_NAME,'public_html/new',true));
    foreach(['foreign.synthetic.invalid','site801.synthetic.invalid/extra'] as $name)drrq_deny('Exact shared hostname selection refuses '.$name,fn()=>provider_document_root_requests_create(bin2hex(random_bytes(16)),1,$link['id'],$option['review_sha256'],$name,'public_html/new',true));
    foreach(['','/public_html/new','public_html/../private','public_html//new'] as $path)drrq_deny('Strict desired folder rejects '.($path===''?'empty':$path),fn()=>provider_document_root_requests_create(bin2hex(random_bytes(16)),1,$link['id'],$option['review_sha256'],DRRQ_NAME,$path,true));
    drrq_ok('Rejected client inputs leave all rows and transport untouched',drrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    $cancelId=bin2hex(random_bytes(16));$count=drrq_count('provider_website_folder_requests_events');$audits=drrq_event_audits();
    drrq_ok('Real intake audit failure witnesses newly inserted event',drrq_fault('intake',fn()=>drrq_create($cancelId,$option)));
    drrq_ok('Intake audit rollback leaves no intent grant or provider effect',drrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    $created=drrq_create($cancelId,$option);$original=drrq_graph()['requests'][$cancelId]['request'];
    drrq_ok('Primary client retains exact hostname empty submitted path and desired path with actual auth and sharing source',$created['state']==='pending'&&$created['can_cancel']===true
        &&$original['payload']['name']===DRRQ_NAME&&$original['payload']['before_path']===''&&$original['payload']['desired_path']==='public_html/new'&&$original['actor']['kind']==='client'&&$original['actor']['id']===1
        &&$original['actor']['auth_sha256']===hash('sha256',(string)$_SESSION['client_auth'])
        &&$original['payload']['resource']['binding']['package']['source_id']===$source['id']&&$original['payload']['resource']['sharing']['id']===$share['document']['id']);
    drrq_ok('Local request appends only one event audit and no owner grant or IO',drrq_count('provider_website_folder_requests_events')===$count+1&&drrq_event_audits()===$audits+1&&drrq_count('provider_operation_grants')===1&&[$dns,$gets,$posts]===$io);
    $before=drrq_digest();drrq_ok('Exact client create replay is inert',drrq_create($cancelId,$option)===$created&&drrq_digest()===$before);
    drrq_deny('Existing request identity cannot change desired folder',fn()=>drrq_create($cancelId,$option,'public_html/changed'));
    drrq_deny('Duplicate pending package request cannot reserve another nonce',fn()=>drrq_create(bin2hex(random_bytes(16)),$option));
    drrq_deny('Another shared hostname cannot bypass the whole-package client fence',fn()=>provider_document_root_requests_create(bin2hex(random_bytes(16)),1,$link['id'],$option['review_sha256'],'other801.synthetic.invalid','public_html/different-site',true));
    drrq_client(2);drrq_ok('Unshared foreign client has no options or foreign items',drrq_screen(2)['options']===[]&&drrq_screen(2)['items']===[]);
    drrq_deny('Foreign primary client cannot read request detail',fn()=>drrq_item($cancelId,2));
    drrq_deny('Foreign primary client cannot withdraw request',fn()=>provider_document_root_requests_change($cancelId,$cancelId,bin2hex(random_bytes(16)),'cancel','',2));

    $stage='withdraw-decline';drrq_owner();$cancelReview=drrq_item($cancelId);$cancelEvent=bin2hex(random_bytes(16));
    $race=drrq_race(drrq_worker_input('approve',$cancelId,$cancelReview['approval']['request_id'],$cancelReview),function()use($cancelId,$cancelEvent):void{drrq_client();provider_document_root_requests_change($cancelId,$cancelId,$cancelEvent,'cancel','',1);},'Withdrawal-wins race');
    drrq_ok('Blocked approval loses to actual withdrawal with no send',$race===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&drrq_item($cancelId,1)['state']==='cancelled'&&[$dns,$gets,$posts]===$io);
    $before=drrq_digest();provider_document_root_requests_change($cancelId,$cancelId,$cancelEvent,'cancel','',1);
    drrq_ok('Cancel and original create retries retain terminal client decision',drrq_create($cancelId,$option)['state']==='cancelled'&&drrq_digest()===$before);
    $declineId=bin2hex(random_bytes(16));drrq_create($declineId,$option);drrq_owner();$declineEvent=bin2hex(random_bytes(16));
    $declined=provider_document_root_requests_change($declineId,$declineId,$declineEvent,'decline','Please confirm which existing folder should serve the website first.',null,DRRQ_PASSWORD);
    drrq_ok('Owner decline records explanation without provider work',$declined['state']==='declined'&&$declined['note']==='Please confirm which existing folder should serve the website first.'&&[$dns,$gets,$posts]===$io);
    $before=drrq_digest();provider_document_root_requests_change($declineId,$declineId,$declineEvent,'decline','Please confirm which existing folder should serve the website first.',null,DRRQ_PASSWORD);
    drrq_ok('Exact decline retry is inert',drrq_digest()===$before);

    $stage='fresh-owner-admission';drrq_client();$mainId=bin2hex(random_bytes(16));drrq_create($mainId,$option);drrq_owner();$stale=drrq_item($mainId);
    drrq_ok('Ordinary fresh inventory retains pending static client assignment',drrq_hosting($profile)['state']==='complete');$anchor=drrq_map($link,'public_html/current');$before=drrq_digest();$io=[$dns,$gets,$posts];
    drrq_deny('Old owner anchor cannot newly admit after genuine later reads',fn()=>drrq_approve($mainId,$stale));
    $review=drrq_item($mainId);$approval=$review['approval'];$dispatch=$approval['request_id'];
    drrq_ok('Fresh owner review uses new exact path while retaining submitted empty path and original source',$review['state']==='pending'&&$approval['anchor_id']===$anchor['id']&&$approval['before_path']==='public_html/current'&&$review['before_path']===''&&$review['desired_path']==='public_html/new'&&drrq_graph()['requests'][$mainId]['request']['payload']['resource']['binding']['package']['source_id']===$source['id']);
    foreach(['owner'=>['staff_profiles','user_id','enabled',0], 'client'=>['studio_clients','id','auth_version',bin2hex(random_bytes(16))]] as $kind=>[$tableName,$pk,$column,$changed]){
        $originalCell=one('SELECT '.$column.' AS value FROM '.table($tableName).' WHERE '.$pk.'=1')['value'];
        try{$blocked=drrq_race(drrq_worker_input('approve-'.$kind.'-revoked',$mainId,$dispatch,$review),
            fn()=>query('UPDATE '.table($tableName).' SET '.$column.'=? WHERE '.$pk.'=1',[$changed]),'Current '.$kind.' authority race');}
        finally{query('UPDATE '.table($tableName).' SET '.$column.'=? WHERE '.$pk.'=1',[$originalCell]);drrq_owner();}
        drrq_ok('Blocked owner admission rechecks '.$kind.' authority after acquiring mutex',$blocked===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&drrq_digest()===$before&&[$dns,$gets,$posts]===$io&&drrq_item($mainId)['state']==='pending');
    }
    drrq_deny('Owner dispatch needs confirmation',fn()=>provider_document_root_requests_approve($mainId,$mainId,$review['request_sha256'],$dispatch,$approval['anchor_id'],$approval['binding_sha256'],false,DRRQ_PASSWORD,'drrq_write_dns','drrq_perform'));
    drrq_deny('Owner dispatch needs current password',fn()=>provider_document_root_requests_approve($mainId,$mainId,$review['request_sha256'],$dispatch,$approval['anchor_id'],$approval['binding_sha256'],true,'wrong','drrq_write_dns','drrq_perform'),'Your current password is not correct.');
    drrq_owner(2);drrq_deny('Ordinary staff cannot approve client request',fn()=>drrq_approve($mainId,$review));drrq_owner();
    drrq_ok('Late dispatch event audit sees both new owner request and consumed grant',drrq_fault('dispatch',fn()=>drrq_approve($mainId,$review)));
    drrq_ok('Failed atomic admission restores all rows and leaves pending intent',drrq_digest()===$before&&[$dns,$gets,$posts]===$io&&drrq_item($mainId)['state']==='pending');
    $duringPost=function()use($dispatch):void{
        global $capturedAuthorize;$session=$_SESSION;$digest=drrq_digest();
        $request=provider_document_root_controls_request($dispatch);
        foreach(['client authentication'=>['studio_clients','auth_version',1], 'owner authentication'=>['users','auth_version',1],
            'owner disabled'=>['staff_profiles','enabled',1], 'credential seal'=>['provider_profiles','credentials',$request['row']['profile_id']],
            'assignment client'=>['provider_package_links','client_id',$request['row']['link_id']]] as $label=>[$name,$column,$id]){
            $pk=$name==='staff_profiles'?'user_id':'id';$old=one('SELECT '.$column.' AS value FROM '.table($name).' WHERE '.$pk.'=?',[$id])['value'];
            $new=match($column){'client_id'=>2,'enabled'=>0,default=>bin2hex(random_bytes(16))};query('UPDATE '.table($name).' SET '.$column.'=? WHERE '.$pk.'=?',[$new,$id]);
            try{drrq_deny('Real credential-send callback denies changed '.$label,$capturedAuthorize);}finally{query('UPDATE '.table($name).' SET '.$column.'=? WHERE '.$pk.'=?',[$old,$id]);$_SESSION=$session;unset($GLOBALS['cwp_settings']);}
        }
        drrq_ok('Boundary probes restore exact rows and current owner session',drrq_digest()===$digest&&$_SESSION===$session);
    };
    $race=drrq_race(drrq_worker_input('cancel',$mainId,bin2hex(random_bytes(16)),$review),function()use($mainId,$review):void{drrq_owner();drrq_approve($mainId,$review);},'Approval-wins race');
    drrq_ok('Blocked withdrawal loses to single committed client-origin send',$race===['outcome'=>'refused','dns'=>0,'gets'=>0,'posts'=>0]&&$posts===2&&$gets===$io[1]&&$dns===$io[0]+1);
    $ownerRequest=drrq_locked(fn()=>provider_document_root_controls_request($dispatch));$event=drrq_graph()['requests'][$mainId]['events'][0];$ledger=$ownerRequest['ledger'];
    drrq_ok('Owner v2 and dispatch reciprocally authenticate original client consent',$ownerRequest['document']['version']===2&&$ownerRequest['document']['client_origin']===['id'=>$mainId,'request_sha256'=>$review['request_sha256']]&&$event['id']===$dispatch&&$event['payload']['owner_request_sha256']===$ownerRequest['row']['document_sha256']);
    drrq_ok('Client-origin full request retains unchanged compact shared grant commitment',strlen($ownerRequest['grant']['scope_params'])<4096&&$ownerRequest['grant']['scope_params']===provider_document_roots_json(provider_document_root_controls_grant_scope($dispatch,$ownerRequest['row']['document_json'])));
    drrq_ok('Actual gateway records one uncertain exact updateDocRoot POST',$ledger['status']==='uncertain'&&(int)$ledger['attempts']===1&&$wire[1]===['url'=>'https://api.20i.com/package/801/web/updateDocRoot','body'=>'{"update":{"site801.synthetic.invalid":"public_html/new"}}']);
    $lateAuthorize=$capturedAuthorize;$lateRequest=$capturedRequest;$lateBefore=drrq_digest();$lateIo=[$dns,$gets,$posts];
    staff_locked(fn()=>drrq_deny('Retained original transport callback cannot send a late second POST',fn()=>(new DrrqWriteTransport($lateAuthorize))->send($lateRequest)));
    drrq_ok('Late completed dispatch refusal changes no rows or provider counters',drrq_digest()===$lateBefore&&[$dns,$gets,$posts]===$lateIo);
    $before=drrq_digest();$io=[$dns,$gets,$posts];$approved=drrq_approve($mainId,$review);
    drrq_ok('Exact approval replay returns unknown without effects',$approved['state']==='unknown'&&$approved['owner_request_id']===$dispatch&&drrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    drrq_deny('Changed original request hash cannot replay approved intent',fn()=>provider_document_root_requests_approve($mainId,$mainId,str_repeat('0',64),$dispatch,$approval['anchor_id'],$approval['binding_sha256'],true,DRRQ_PASSWORD,'drrq_write_dns','drrq_perform'));
    drrq_client();$client=drrq_item($mainId,1);$json=json_encode($client,JSON_THROW_ON_ERROR);
    drrq_ok('Client receives unknown status without owner approval grants proofs or secrets',$client['state']==='unknown'&&$client['approval']===null&&$client['dispatch']===null&&$client['owner_request_id']===null&&$client['client']===null&&!str_contains($json,DRRQ_SECRET)&&!str_contains($json,$approval['binding_sha256']));
    drrq_deny('Client cannot call owner verification',fn()=>provider_document_root_requests_verify($mainId,$dispatch,DRRQ_PASSWORD,'drrq_read_dns','drrq_read'));
    drrq_deny('Unknown package effect fences fresh client nonce',fn()=>drrq_create(bin2hex(random_bytes(16)),drrq_option($link),'public_html/other'));
    drrq_ok('Client GET and exact intake replay do not write or call provider',drrq_create($mainId,$option)['state']==='unknown'&&drrq_digest()===$before&&[$dns,$gets,$posts]===$io);

    $stage='finished-ledger-boundaries';drrq_owner();
    foreach(['missing','claimed'] as $mode)drrq_unfinished_verify($dispatch,$ledger,$mode);
    $checksBefore=drrq_count('provider_document_root_change_checks');$readsBefore=$gets;$postsBefore=$posts;$readStatus=200;$readBody=drrq_map_body(801,'');
    $duringRead=fn()=>query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE id=?',['success',time(),$ledger['id']]);
    try{drrq_deny('Reconciliation during actual GET prevents saving client-origin check',fn()=>drrq_verify($mainId,$dispatch,$profile),'This request has no eligible durable dispatch completion.');}
    finally{query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE id=?',[$ledger['reconciled_outcome'],$ledger['reconciled_epoch'],$ledger['id']]);$duringRead=null;}
    $latest=provider_document_roots_latest($link['id']);
    drrq_ok('Post-read completion guard retains exact receipt but no new check or send',$gets===$readsBefore+1&&$posts===$postsBefore&&$latest['state']==='complete'&&drrq_count('provider_document_root_change_checks')===$checksBefore&&one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$ledger['id']])===$ledger);

    $stage='separate-readback';$readStatus=403;$readBody='null';$failed=drrq_verify($mainId,$dispatch,$profile);
    drrq_client();$failedClient=drrq_item($mainId,1);drrq_ok('Failed fresh GET stays unavailable without exposing a private path and stays fenced',$failed['state']==='unknown'&&$failedClient['last_check']['disposition']==='unavailable'&&!array_key_exists('path',$failedClient['last_check'])&&drrq_pending($link)['id']===$dispatch);
    drrq_owner();$readStatus=200;$readBody='{}';$missing=drrq_verify($mainId,$dispatch,$profile);
    drrq_ok('Complete map omitting hostname is different with null path and never releases fence',$missing['state']==='unknown'&&$missing['last_check']['disposition']==='different'&&$missing['dispatch']['last_check']['path']===null&&drrq_pending($link)['id']===$dispatch);
    drrq_owner();$readStatus=200;$readBody=drrq_map_body(801,'');$different=drrq_verify($mainId,$dispatch,$profile);
    drrq_client();$differentClient=drrq_item($mainId,1);drrq_ok('Different fresh empty path stays unknown and fenced',$different['last_check']['disposition']==='different'&&!array_key_exists('path',$differentClient['last_check'])&&$different['dispatch']['last_check']['path']==='' &&$differentClient['state']==='unknown'&&drrq_pending($link)['id']===$dispatch);
    drrq_owner();$before=drrq_digest();$io=[$dns,$gets,$posts];drrq_approve($mainId,$review);drrq_ok('Original owner approval replays after newer reads without resend',drrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    $readBody=drrq_map_body(801,'public_html/new');$matched=drrq_verify($mainId,$dispatch,$profile);drrq_client();$observed=drrq_item($mainId,1);
    drrq_ok('Only exact separate matching GET yields observed client status',$matched['state']==='observed'&&$observed['state']==='observed'&&$observed['last_check']['disposition']==='observed'&&!array_key_exists('path',$observed['last_check'])&&drrq_pending($link)===null);
    drrq_ok('Readback preserves original immutable gateway result',one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$ledger['id']])===$ledger&&$posts===2);

    $stage='terminal-recovery';$option2=drrq_option($link);$recoverId=bin2hex(random_bytes(16));drrq_create($recoverId,$option2,'public_html/other');drrq_owner();$recoverReview=drrq_item($recoverId);$recoverOwner=$recoverReview['approval']['request_id'];$terminalCount=drrq_count('provider_document_root_dispatch_ends');
    drrq_ok('Actual terminal audit failure occurs after one additional POST',drrq_fault('terminal',fn()=>drrq_approve($recoverId,$recoverReview))&&$posts===3);
    $interrupted=drrq_item($recoverId);$hash=$interrupted['dispatch']['recovery_ledger_sha256'];$recoverLedger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$recoverOwner]);
    drrq_ok('Failed local terminal retains origin and finished one-attempt ledger',$interrupted['state']==='interrupted'&&is_string($hash)&&strlen($hash)===64&&$recoverLedger['status']==='uncertain'&&(int)$recoverLedger['attempts']===1&&drrq_count('provider_document_root_dispatch_ends')===$terminalCount);
    $before=drrq_digest();$io=[$dns,$gets,$posts];drrq_approve($recoverId,$recoverReview);
    drrq_deny('Interrupted request cannot verify before local recovery',fn()=>provider_document_root_requests_verify($recoverId,$recoverOwner,DRRQ_PASSWORD,'drrq_read_dns','drrq_read'));
    drrq_deny('Recovery cannot attach another original approval',fn()=>provider_document_root_requests_recover($recoverId,$dispatch,$hash,DRRQ_PASSWORD));
    drrq_deny('Recovery requires exact finished ledger digest',fn()=>provider_document_root_requests_recover($recoverId,$recoverOwner,str_repeat('0',64),DRRQ_PASSWORD));
    drrq_owner(3);drrq_deny('Different actual owner cannot recover original approval',fn()=>provider_document_root_requests_recover($recoverId,$recoverOwner,$hash,DRRQ_PASSWORD));drrq_owner();
    foreach(['original client authentication'=>['studio_clients','auth_version',1], 'original owner disabled'=>['staff_profiles','enabled',1],
        'original credential seal'=>['provider_profiles','credentials',$profile], 'original assigned client'=>['provider_package_links','client_id',$link['id']]] as $label=>[$tableName,$column,$id]){
        $pk=$tableName==='staff_profiles'?'user_id':'id';$old=one('SELECT '.$column.' AS value FROM '.table($tableName).' WHERE '.$pk.'=?',[$id])['value'];
        $changed=match($column){'client_id'=>2,'enabled'=>0,default=>bin2hex(random_bytes(16))};$session=$_SESSION;
        query('UPDATE '.table($tableName).' SET '.$column.'=? WHERE '.$pk.'=?',[$changed,$id]);unset($GLOBALS['cwp_settings']);
        try{drrq_deny('Local terminal recovery rechecks '.$label,fn()=>provider_document_root_requests_recover($recoverId,$recoverOwner,$hash,DRRQ_PASSWORD));}
        finally{query('UPDATE '.table($tableName).' SET '.$column.'=? WHERE '.$pk.'=?',[$old,$id]);$_SESSION=$session;unset($GLOBALS['cwp_settings']);}
    }
    drrq_ok('Interrupted replay and refused recovery add no provider or database effects',drrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    $rows=drrq_rows();$recovered=provider_document_root_requests_recover($recoverId,$recoverOwner,$hash,DRRQ_PASSWORD);
    drrq_ok('Explicit recovery adds only local terminal audit and counter',$recovered['state']==='unknown'&&drrq_recovery_delta($rows,$recoverOwner)&&[$dns,$gets,$posts]===$io&&one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$recoverLedger['id']])===$recoverLedger);
    drrq_deny('Existing terminal cannot be recovered twice',fn()=>provider_document_root_requests_recover($recoverId,$recoverOwner,$hash,DRRQ_PASSWORD));
    $lateBefore=drrq_digest();$lateIo=[$dns,$gets,$posts];
    staff_locked(fn()=>drrq_deny('Old POST callback remains fenced after newer approval and local recovery',fn()=>(new DrrqWriteTransport($lateAuthorize))->send($lateRequest)));
    drrq_ok('Late old dispatch cannot alter the newer unresolved fence',drrq_pending($link)['id']===$recoverOwner&&drrq_digest()===$lateBefore&&[$dns,$gets,$posts]===$lateIo);

    $stage='history-integrity';$check=one('SELECT * FROM '.table('provider_document_root_change_checks').' WHERE request_id=? AND disposition=?',[$dispatch,'observed']);
    // Keep the exact valid empty table while removing every owned retained row.
    // Public screens run outside a transaction, so nested-transaction refusal cannot mask lost origin.
    $lostBefore=drrq_digest();$lostIo=[$dns,$gets,$posts];$lostSession=$_SESSION;
    $lostRows=['provider_website_folder_requests_events'=>all('SELECT * FROM '.table('provider_website_folder_requests_events').' ORDER BY ordinal'),
        'settings'=>all('SELECT * FROM '.table('settings').' WHERE BINARY name IN (?,?)',['provider_website_folder_requests_schema_version','provider_website_folder_requests_head']),
        'audit'=>all('SELECT * FROM '.table('audit').' WHERE BINARY action IN (?,?)',['provider.website_folder_requests_prepared','provider.website_folder_requests_event'])];
    try{
        drrq_locked(function():void{query('DELETE FROM '.table('provider_website_folder_requests_events'));query('DELETE FROM '.table('settings').' WHERE BINARY name IN (?,?)',['provider_website_folder_requests_schema_version','provider_website_folder_requests_head']);query('DELETE FROM '.table('audit').' WHERE BINARY action IN (?,?)',['provider.website_folder_requests_prepared','provider.website_folder_requests_event']);});
        drrq_ok('Complete local journal loss leaves authenticated owner v2 evidence present',drrq_locked(fn()=>provider_document_root_requests_ready())===false&&one('SELECT id FROM '.table('provider_document_root_changes').' WHERE id=?',[$dispatch])!==null);
        drrq_deny('Owner screen refuses absent journal with retained v2 origin',fn()=>drrq_screen());
        drrq_client();drrq_deny('Client screen refuses absent journal with retained v2 origin',fn()=>drrq_screen(1));
    }finally{
        drrq_locked(function()use($lostRows):void{foreach($lostRows as $name=>$rows)foreach($rows as $row)query('INSERT INTO '.table($name).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));});
        $_SESSION=$lostSession;unset($GLOBALS['cwp_settings']);
    }
    drrq_ok('Complete local journal loss probe restores exact original cells and no provider effect',drrq_digest()===$lostBefore&&[$dns,$gets,$posts]===$lostIo&&drrq_item($mainId)['state']==='observed');
    $ownerLostBefore=drrq_digest();$ownerLostIo=[$dns,$gets,$posts];$ownerLostSession=$_SESSION;
    $ownerLost=[];foreach(['provider_document_root_change_checks','provider_document_root_dispatch_ends','provider_document_root_changes','provider_operations','provider_operation_grants'] as $name)$ownerLost[$name]=all('SELECT * FROM '.table($name));
    $ownerLost['settings']=all('SELECT * FROM '.table('settings')." WHERE BINARY name LIKE 'provider_document_root_control_%'");
    $ownerLost['audit']=all('SELECT * FROM '.table('audit')." WHERE BINARY action LIKE 'provider.document_root_control_%'");
    try{
        drrq_locked(function()use($ownerLost):void{foreach($ownerLost as $name=>$rows){foreach($rows as $row){$pk=$name==='settings'?'name':'id';query('DELETE FROM '.table($name).' WHERE '.$pk.'=?',[$row[$pk]]);}}});
        unset($GLOBALS['cwp_settings']);
        drrq_ok('Surviving client journal remains independently authenticated after total owner-row loss',drrq_locked(fn()=>provider_document_root_requests_ready()));
        drrq_deny('Owner readiness refuses total owner-history loss with surviving client journal',fn()=>provider_document_root_controls_ready());
        drrq_deny('Owner preparation cannot recreate lost history behind surviving client dispatch',fn()=>provider_document_root_controls_prepare(DRRQ_PASSWORD));
        drrq_ok('Refused preparation leaves all lost owner tables empty and marker absent',drrq_count('provider_document_root_changes')===0&&drrq_count('provider_operation_grants')===0&&one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_document_root_control_schema_version'])===null);
    }finally{
        drrq_locked(function()use($ownerLost):void{foreach(['provider_operation_grants','provider_operations','provider_document_root_changes','provider_document_root_dispatch_ends','provider_document_root_change_checks','settings','audit'] as $name)foreach($ownerLost[$name] as $row)query('INSERT INTO '.table($name).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));});
        $_SESSION=$ownerLostSession;unset($GLOBALS['cwp_settings']);
    }
    drrq_ok('Owner-loss probe restores every original cell and provider counter',drrq_digest()===$ownerLostBefore&&[$dns,$gets,$posts]===$ownerLostIo&&drrq_item($mainId)['state']==='observed');
    foreach([
        'client request'=>fn()=>query('DELETE FROM '.table('provider_website_folder_requests_events').' WHERE id=?',[$mainId]),
        'client dispatch'=>fn()=>query('DELETE FROM '.table('provider_website_folder_requests_events').' WHERE id=?',[$dispatch]),
        'paired dispatch and audit'=>function()use($dispatch):void{query('DELETE FROM '.table('provider_website_folder_requests_events').' WHERE id=?',[$dispatch]);query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.website_folder_requests_event','Website folder request event '.$dispatch.' %']);},
        'canonical client payload'=>fn()=>query('UPDATE '.table('provider_website_folder_requests_events').' SET document=? WHERE id=?',['{}',$mainId]),
        'head'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_website_folder_requests_head']),
        'marker'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_website_folder_requests_schema_version']),
        'preparation audit'=>fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=?',['provider.website_folder_requests_prepared']),
        'unknown marker'=>fn()=>save_setting('provider_website_folder_requests_extra','1'),
        'owner origin commitment'=>fn()=>query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',['{}',$dispatch]),
        'owner approval'=>function()use($dispatch):void{query('DELETE FROM '.table('provider_document_root_change_checks').' WHERE request_id=?',[$dispatch]);query('DELETE FROM '.table('provider_document_root_dispatch_ends').' WHERE id=?',[$dispatch]);query('DELETE FROM '.table('provider_document_root_changes').' WHERE id=?',[$dispatch]);},
        'terminal'=>fn()=>query('DELETE FROM '.table('provider_document_root_dispatch_ends').' WHERE id=?',[$dispatch]),
        'paired check and audit'=>function()use($check):void{query('DELETE FROM '.table('provider_document_root_change_checks').' WHERE id=?',[$check['id']]);query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',['provider.document_root_control_check','Document-root change check '.$check['id'].' %']);},
        'reconciled original dispatch ledger'=>fn()=>query('UPDATE '.table('provider_operations').' SET reconciled_outcome=?,reconciled_epoch=? WHERE id=?',['success',time(),$ledger['id']]),
        'original full map observation'=>fn()=>query('DELETE FROM '.table('provider_document_roots_observations').' WHERE id=?',[$check['receipt_id']]),
    ] as $name=>$mutate)drrq_damage('Missing or changed '.$name.' refuses authenticated history',$mutate);

    $stage='revocation';$otherAnchor=drrq_map($other,'public_html/legacy');$otherShare=provider_document_roots_share($other['id'],'',bin2hex(random_bytes(16)),true,$otherAnchor['id'],DRRQ_PASSWORD);
    drrq_client(2);$otherOption=drrq_option($other,2);$revokeId=bin2hex(random_bytes(16));drrq_create($revokeId,$otherOption,'public_html/other',2);drrq_owner();$revokeReview=drrq_item($revokeId);$revokeOwner=$revokeReview['approval']['request_id'];
    $auth=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=2')['auth_version'];$beforePosts=$posts;
    $duringDns=fn()=>query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=2',[bin2hex(random_bytes(16))]);
    try{$revoked=drrq_approve($revokeId,$revokeReview);drrq_ok('Original client auth changed during DNS prevents credentials and send',$revoked['state']==='not_sent'&&$revoked['current_assignment']===false&&$posts===$beforePosts&&one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$revokeOwner])===null);
        drrq_deny('Revoked original client auth denies exact owner replay',fn()=>drrq_approve($revokeId,$revokeReview));
    }finally{query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=2',[$auth]);drrq_owner();}
    $stage='daily-intake-limit';drrq_client(2);$limitOption=drrq_option($other,2);$limitLast='';$limitEvent='';
    for($i=2;$i<=10;$i++){
        $limitLast=bin2hex(random_bytes(16));drrq_create($limitLast,$limitOption,'public_html/other',2);$limitEvent=bin2hex(random_bytes(16));
        provider_document_root_requests_change($limitLast,$limitLast,$limitEvent,'cancel','',2);
    }
    drrq_ok('Ten actual client attempts across all outcomes consume daily intake allowance',count(drrq_screen(2)['items'])===10);
    $before=drrq_digest();$io=[$dns,$gets,$posts];
    drrq_deny('Eleventh daily client attempt refuses before event or provider work',fn()=>drrq_create(bin2hex(random_bytes(16)),$limitOption,'public_html/other',2),'The daily website folder request limit has been reached. Please try later.');
    drrq_ok('Exact cancelled intake and decision retries do not consume daily capacity',drrq_create($limitLast,$limitOption,'public_html/other',2)['state']==='cancelled'
        &&provider_document_root_requests_change($limitLast,$limitLast,$limitEvent,'cancel','',2)['state']==='cancelled'&&drrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    drrq_owner();
    $shareNow=drrq_locked(fn()=>provider_document_roots_permission($link['id']));provider_document_roots_share($link['id'],$shareNow['document']['id'],bin2hex(random_bytes(16)),false,'',DRRQ_PASSWORD);
    $before=drrq_digest();$io=[$dns,$gets,$posts];drrq_deny('Sharing revocation blocks original owner verification',fn()=>provider_document_root_requests_verify($recoverId,$recoverOwner,DRRQ_PASSWORD,'drrq_read_dns','drrq_read'));
    drrq_deny('Direct owner verification also checks client-origin sharing',fn()=>provider_document_root_controls_verify($recoverOwner,DRRQ_PASSWORD,'drrq_read_dns','drrq_read'));
    drrq_client();$history=drrq_item($recoverId,1);drrq_ok('Revoked sharing hides choices but retains safe own unknown history',$history['state']==='unknown'&&$history['current_assignment']===false&&drrq_screen(1)['options']===[]&&drrq_pending($link)['id']===$recoverOwner);
    drrq_ok('Revoked read and GET history create no effects',drrq_digest()===$before&&[$dns,$gets,$posts]===$io);
    drrq_owner();provider_settings_save($profile,1,'Rotated Website folder request credential',true,'SYNTHETIC_ROTATED_DOCUMENT_ROOT_KEY',DRRQ_PASSWORD);
    drrq_deny('Rotated account credential blocks exact old owner replay',fn()=>drrq_approve($recoverId,$recoverReview));
    provider_settings_save($profile,2,'Restored key new account revision',true,DRRQ_SECRET,DRRQ_PASSWORD);drrq_ok('Replacement account revision gets real complete inventory',drrq_hosting($profile)['state']==='complete');
    provider_inventory_unlink($link['id'],DRRQ_PASSWORD);provider_inventory_link($profile,801,2,3,DRRQ_PASSWORD);drrq_ok('Reassignment gets genuine post-assignment inventory',drrq_hosting($profile)['state']==='complete');
    drrq_client(2);drrq_deny('New assignee cannot read old client request',fn()=>drrq_item($recoverId,2));
    drrq_ok('Original unknown effect fence survives key and client replacement',drrq_pending($link)['id']===$recoverOwner);
    drrq_client();drrq_ok('Original client retains dated history without changed-resource authority',drrq_item($recoverId,1)['current_assignment']===false);
    drrq_owner();drrq_ok('Entire original owner v1 request grant ledger and readback remain byte-exact',drrq_old_rows($oldId)===$legacy);
    drrq_ok('Final authenticated request graph remains valid',is_array(drrq_locked(fn()=>provider_document_root_requests_state())));
    drrq_ok('Exact bounded synthetic transport total retains fifteen GETs and three single POSTs',$gets===15&&$posts===3);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure in '.$stage,'passed'=>false];$failure=['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null];}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
$result=['passed'=>$passed,'scope'=>'Isolated native SQL, actual primary-client and owner wrappers, real shared gateway and observed process mutex races; provider transport synthetic. No live provider, browser, remote directory existence, hosting-platform compatibility, DNS or web reachability claim.','checks'=>$checks];
if(isset($failure))$result['failure']=$failure;fwrite($handle,json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");fclose($handle);
echo ($passed?'PASS':'FAIL').': '.count($checks).' Website folder request native checks'.PHP_EOL;exit($passed?0:1);
