<?php
/** Isolated native SQL/owner/gateway acceptance. All provider responses are synthetic. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_DOCUMENT_ROOT_CONTROLS')!=='1'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
$worker=count($argv)===2&&$argv[1]==='--contender';
if(count($argv)!==($worker?2:1))exit(2);
$port=getenv('CWP_TEST_PORT')?:'33306';if(!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/document_root_controls.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIDocumentRoots\{DocumentRootsBinding,DocumentRootsObservation,DocumentRootsReader};
use CajunWebPro\Foundation\TwentyI\Operations\{DocumentRootChange,TwentyIOperationAdapter};

const DRC_PREFIX='drctl_';
const DRC_PASSWORD='Synthetic document root owner passphrase';
const DRC_SECRET='SYNTHETIC_DOCUMENT_ROOT_CONTROL_KEY';
const DRC_NAME='site701.synthetic.invalid';
const DRC_OLD='public_html/original';
const DRC_NEW='public_html/reviewed';
$root=realpath(__DIR__.'/..');if(!$root)exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';$private=$runtime.'/document-root-controls-private';
$report=$qa.'/provider-document-root-controls-native-results.json';
foreach([$runtime,$qa] as $path){if(is_link($path)||(file_exists($path)&&!is_dir($path)))exit(2);if(!is_dir($path)&&!mkdir($path,0700))exit(2);}
if(is_link($private)||($worker?!is_dir($private):file_exists($private))||(!$worker&&(is_link($report)||file_exists($report))))exit(2);
if(!$worker&&!mkdir($private,0700))exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/document-root-controls','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-document-root-control-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$port,'name'=>'cwp_test',
    'user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>DRC_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root.'/site';start_session();
$checks=[];$stage='preflight';$dns=0;$gets=0;$posts=0;$wire=[];$duringDns=null;$duringPost=null;$capturedAuthorize=null;
$readStatus=200;$readBody='';$writeMode='unknown';

function drc_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function drc_deny(string $name,callable $call,?string $message=null):void{
    $caught=null;try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    drc_ok($name,$caught!==null&&($message===null||$caught->getMessage()===$message));
}
function drc_owner(int $id=1):void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function drc_client(int $id=1):void{
    $u=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);
    $_SESSION=['client_id'=>$id,'client_auth'=>$u['auth_version'],'client_login'=>time(),'client_last'=>time()];
    $_GET=[];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function drc_locked(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function drc_mutex():bool{
    $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.DRC_PREFIX),0,40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;
}
function drc_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function drc_counts():array{global $dns,$gets,$posts;return [$dns,$gets,$posts,...array_map('drc_count',['provider_document_root_changes','provider_document_root_dispatch_ends','provider_document_root_change_checks','provider_operation_grants','provider_operations','provider_refreshes','audit'])];}
function drc_digest():string{
    $rows=[];foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(DRC_PREFIX),DRC_PREFIX]) as $t){
        $name=$t['TABLE_NAME'];if(!preg_match('/\Adrctl_[a-z0-9_]+\z/',$name))throw new LogicException('Unexpected fixture table.');
        $data=all('SELECT * FROM `'.$name.'`');usort($data,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$data;
    }return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
/** A genuine bounded cooldown; no saved date, quota or clock is changed. */
function drc_wait(string $profile):void{
    if(db()->inTransaction()||drc_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');
    $deadline=microtime(true)+32;while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
function drc_read_dns():string{global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';}
function drc_write_dns():string{
    global $dns,$duringDns;if(db()->inTransaction()||!drc_mutex())throw new LogicException('Write DNS requires mutex outside transaction.');
    $row=one('SELECT * FROM '.table('provider_document_root_changes').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Approval not durable before DNS.');
    $q=provider_document_root_controls_request($row['id']);
    if($q['grant']['consumed_key']!=='documentroot:'.$row['id']||$q['ledger']!==null
        ||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',
            ['provider.document_root_control_request','Document-root change request '.$row['id'].' %'])->fetchColumn()!==1)
        throw new LogicException('Consumed approval and audit must precede DNS.');
    $dns++;if($duringDns){$call=$duringDns;$duringDns=null;$call();}return '8.8.8.8';
}
function drc_boundary(callable $authorize):void{$authorize();if(db()->inTransaction()||!drc_mutex())throw new LogicException('Transport boundary requires owning mutex outside transaction.');}
final class DocumentRootControlReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readBody,$readStatus;drc_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null
            ||$request->headers!==['Authorization'=>'Bearer '.base64_encode(DRC_SECRET),'Accept'=>'application/json'])throw new LogicException('Unexpected synthetic GET.');
        $gets++;return new TransportResponse($readStatus,[],$readBody);
    }
}
function drc_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?DocumentRootsBinding $documentRootsContext=null):InventorySnapshot|DocumentRootsObservation{
    drc_boundary($authorize);if($key!==DRC_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{drc_boundary($authorize);return $key;});
    if($operation===InventoryRead::PACKAGES_LIST)return InventoryReader::read(new DocumentRootControlReadTransport($authorize,'https://api.20i.com/package'),$credential,$operation,null,time());
    if($operation!==DocumentRootsBinding::OPERATION||!$documentRootsContext)throw new LogicException('Unexpected document roots read context.');
    return DocumentRootsReader::read(new DocumentRootControlReadTransport($authorize,'https://api.20i.com/package/'.$documentRootsContext->data['package']['package_id'].'/web/documentRoots'),$credential,$documentRootsContext,time());
}
final class DocumentRootControlWriteTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$wire,$writeMode,$duringPost;drc_boundary($this->authorize);DocumentRootChange::wire($request);
        if(!in_array($request->url,['https://api.20i.com/package/701/web/updateDocRoot','https://api.20i.com/package/702/web/updateDocRoot','https://api.20i.com/package/703/web/updateDocRoot'],true)
            ||($request->headers['Authorization']??null)!=='Bearer '.base64_encode(DRC_SECRET))throw new LogicException('Unexpected synthetic POST account.');
        $posts++;$wire[]=['url'=>$request->url,'body'=>$request->body];if($duringPost){$call=$duringPost;$duringPost=null;$call();}
        if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
        if($writeMode!=='unknown')throw new LogicException('Unexpected synthetic response mode.');
        return new TransportResponse(200,[],'true');
    }
}
function drc_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    global $capturedAuthorize;$capturedAuthorize=$authorize;drc_boundary($authorize);
    if($key!==DRC_SECRET||$address!=='8.8.8.8'||$operation->name!==DocumentRootChange::OPERATION)throw new LogicException('Wrong synthetic write identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),
        static function()use($authorize,$key):string{drc_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new DocumentRootControlWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function drc_package(int $id):array{return ['id'=>$id,'name'=>'site'.$id.'.synthetic.invalid','names'=>['site'.$id.'.synthetic.invalid','other'.$id.'.synthetic.invalid'],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];}
function drc_hosting(string $profile):array{
    global $readBody,$readStatus;drc_wait($profile);$readStatus=200;$readBody=json_encode(array_map('drc_package',[701,702,703]),JSON_THROW_ON_ERROR);
    return provider_inventory_refresh_attempt($profile,(int)provider_settings_load($profile)['revision'],DRC_PASSWORD,'drc_read_dns','drc_read');
}
function drc_map(array $link,string $path=DRC_OLD,bool $wait=true):array{
    global $readBody,$readStatus;if($wait)drc_wait($link['profile_id']);$readStatus=200;
    $readBody=json_encode(['site'.$link['package_id'].'.synthetic.invalid'=>$path,'other'.$link['package_id'].'.synthetic.invalid'=>'public_html/other'],JSON_THROW_ON_ERROR);
    $b=provider_document_roots_binding($link['id']);$r=provider_document_roots_refresh($link['id'],$b,DRC_PASSWORD,'drc_read_dns','drc_read');
    if($r['state']!=='complete')throw new LogicException('Synthetic document roots anchor did not complete.');
    return provider_document_roots_observation(provider_document_roots_source($r['id']));
}
function drc_change(string $id,array $link,array $anchor,string $path=DRC_NEW,?callable $resolver=null,?callable $perform=null):array{
    return provider_document_root_controls_change($id,$link['id'],$anchor['id'],$anchor['binding']->sha256,'site'.$link['package_id'].'.synthetic.invalid',$path,true,DRC_PASSWORD,$resolver??'drc_write_dns',$perform??'drc_perform');
}
function drc_verify(string $id,array $link):array{drc_wait($link['profile_id']);return provider_document_root_controls_verify($id,DRC_PASSWORD,'drc_read_dns','drc_read');}
function drc_summary(string $id):array{return drc_locked(fn()=>provider_document_root_controls_summary($id));}
function drc_ready():bool{return drc_locked(fn()=>provider_document_root_controls_ready());}
function drc_pending(array $link):?array{return drc_locked(fn()=>provider_document_root_controls_pending($link['profile_id'],(int)$link['package_id']));}
function drc_recover(string $id,?string $hash=null):array{return provider_document_root_controls_recover_finished($id,$hash??drc_summary($id)['recovery_ledger_sha256'],DRC_PASSWORD);}
function drc_damage(string $name,callable $mutate):void{
    $before=drc_digest();$session=$_SESSION;staff_locked(function()use($name,$mutate):void{db()->beginTransaction();try{$mutate();unset($GLOBALS['cwp_settings']);drc_deny($name,fn()=>provider_document_root_controls_ready());}finally{if(db()->inTransaction())db()->rollBack();}});
    $_SESSION=$session;unset($GLOBALS['cwp_settings']);drc_ok($name.' exact rollback restores retained rows',drc_digest()===$before&&drc_ready());
}
/** Real audit failure after INSERT, witnessed by a connection variable surviving rollback. */
function drc_fault(string $kind,callable $call,?string $tableName=null):bool{
    if(!in_array($kind,['prepared','request','terminal','check'],true)||($tableName!==null&&!in_array($tableName,array_values(provider_document_root_controls_tables()),true)))throw new LogicException('Unknown audit fault.');
    $before=$tableName===null?0:drc_count($tableName);$sample=$tableName===null?'':'SET @drctl_rows=(SELECT COUNT(*) FROM '.table($tableName).'); ';
    db()->exec('CREATE TRIGGER drctl_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.document_root_control_".$kind."' THEN SET @drctl_hits=COALESCE(@drctl_hits,0)+1; ".$sample."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic document root audit fault'; END IF; END");
    query('SET @drctl_hits=0,@drctl_rows=0');$caught=false;
    try{$call();}catch(PDOException $e){$caught=$e->getCode()==='45000'&&(int)query('SELECT @drctl_hits')->fetchColumn()===1&&($tableName===null||(int)query('SELECT @drctl_rows')->fetchColumn()===$before+1);}
    finally{db()->exec('DROP TRIGGER drctl_fault');}return $caught;
}
function drc_contender(array $input):void{
    $before=drc_counts();$process=proc_open([PHP_BINARY,__FILE__,'--contender'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
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
    drc_ok('Separate current owner loses actual held mutex before DNS or POST',$exit===0&&$err===''&&is_array($result)&&$result['refused_on_mutex']===true&&$result['dns']===0&&$result['posts']===0&&$result['connection']!==(int)query('SELECT CONNECTION_ID()')->fetchColumn());
    drc_ok('Blocked contender retains exact parent counts',drc_counts()===$before);
}
if($worker){
    try{
        $input=json_decode(stream_get_contents(STDIN,2049),true,5,JSON_THROW_ON_ERROR);
        if(!is_array($input)||array_keys($input)!==['request','link','anchor','binding','name','path'])throw new RuntimeException('Invalid contender input.');
        foreach(['request','link','anchor'] as $key)if(!is_string($input[$key])||!preg_match('/\A[a-f0-9]{32}\z/',$input[$key]))throw new RuntimeException('Invalid contender identity.');
        if(!is_string($input['binding'])||!preg_match('/\A[a-f0-9]{64}\z/',$input['binding'])||$input['name']!==DRC_NAME||$input['path']!==DRC_NEW)throw new RuntimeException('Invalid contender review.');
        drc_owner(3);$denied=false;
        try{provider_document_root_controls_change($input['request'],$input['link'],$input['anchor'],$input['binding'],$input['name'],$input['path'],true,DRC_PASSWORD,'drc_write_dns','drc_perform');}
        catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$denied=$e->getMessage()==='Another account change is in progress. Please retry.';}
        echo json_encode(['refused_on_mutex'=>$denied,'dns'=>$dns,'posts'=>$posts,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR);exit($denied&&$dns===0&&$posts===0?0:1);
    }catch(Throwable $e){echo json_encode(['failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR);exit(1);}
}
$handle=fopen($report,'xb');if(!$handle)exit(2);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
try{
    drc_ok('Fixed disposable database has an empty isolated namespace',(string)query('SELECT DATABASE()')->fetchColumn()==='cwp_test'
        &&(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(DRC_PREFIX),DRC_PREFIX])->fetchColumn()===0);
    $stage='seed';foreach(schema_statements(DRC_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $name=>$value)save_setting($name,$value);
    foreach([1,2,3] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Document root staff '.$id,'drctl'.$id.'@example.invalid',password_hash(DRC_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Document root client '.$id,'drctl-client'.$id.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    drc_owner();provider_settings_migrate(DRC_PASSWORD);provider_inventory_migrate(DRC_PASSWORD);provider_operations_migrate(DRC_PASSWORD);
    $profile=provider_settings_save('',0,'Document root native controls',true,DRC_SECRET,DRC_PASSWORD);provider_document_roots_prepare(DRC_PASSWORD);
    $stage='preparation';drc_ok('Absent controls stay unprepared with no created control tables',!drc_ready()&&!provider_document_root_controls_exists('provider_document_root_changes'));
    db()->beginTransaction();try{save_setting('drctl_probe','keep');drc_deny('Explicit control preparation refuses inherited transaction',fn()=>provider_document_root_controls_prepare(DRC_PASSWORD));drc_ok('Preparation preserves caller transaction and absent schema',db()->inTransaction()&&!provider_document_root_controls_exists('provider_document_root_changes'));}finally{db()->rollBack();unset($GLOBALS['cwp_settings']);}
    drc_ok('Caller rollback remains effective',one('SELECT name FROM '.table('settings').' WHERE name=?',['drctl_probe'])===null);
    drc_owner(2);drc_deny('Administrator cannot prepare controls',fn()=>provider_document_root_controls_prepare(DRC_PASSWORD));drc_owner();
    drc_deny('Wrong owner preparation password refuses',fn()=>provider_document_root_controls_prepare('wrong'));
    drc_ok('Preparation audit fault leaves no feature marker',drc_fault('prepared',fn()=>provider_document_root_controls_prepare(DRC_PASSWORD))&&!drc_ready());
    provider_document_root_controls_prepare(DRC_PASSWORD);$auditCount=drc_count('audit');provider_document_root_controls_prepare(DRC_PASSWORD);
    drc_ok('Explicit preparation resumes empty schema and is idempotent',drc_ready()&&drc_count('audit')===$auditCount&&$dns===0&&$gets===0&&$posts===0);
    $stage='observation';drc_ok('Initial real package inventory completes',drc_hosting($profile)['state']==='complete');
    foreach([701,702,703] as $package)provider_inventory_link($profile,$package,$package===702?2:1,1,DRC_PASSWORD);
    drc_ok('Post-assignment real inventory completes',drc_hosting($profile)['state']==='complete');
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=701');$anchor=drc_map($link);$binding=$anchor['binding'];
    drc_ok('Complete current map binds exact package client and hostname',$binding->data['package']['package_id']===701&&$binding->data['package']['client_id']===1&&$anchor['entries'][0]===['name'=>DRC_NAME,'path'=>DRC_OLD]);
    $before=drc_counts();drc_owner(2);drc_deny('Administrator cannot change the document root',fn()=>drc_change(bin2hex(random_bytes(16)),$link,$anchor));
    drc_client();drc_deny('Primary client cannot use owner change authority',fn()=>drc_change(bin2hex(random_bytes(16)),$link,$anchor));drc_owner();
    $_GET['support']='synthetic';drc_deny('Support context cannot use owner change authority',fn()=>drc_change(bin2hex(random_bytes(16)),$link,$anchor));unset($_GET['support']);
    foreach(['attestation','password','membership','same path','binding','anchor'] as $case){
        drc_deny('Exact review refuses '.$case,fn()=>provider_document_root_controls_change(bin2hex(random_bytes(16)),$link['id'],$case==='anchor'?str_repeat('a',32):$anchor['id'],
            $case==='binding'?str_repeat('b',64):$binding->sha256,$case==='membership'?'missing.synthetic.invalid':DRC_NAME,$case==='same path'?DRC_OLD:DRC_NEW,$case!=='attestation',$case==='password'?'wrong':DRC_PASSWORD,'drc_write_dns','drc_perform'));
    }
    drc_ok('Review and authority refusals cause no grant ledger DNS read or POST',drc_counts()===$before);
    $failedAnchor=$anchor;drc_wait($profile);$readStatus=200;$readBody='null';$r=provider_document_roots_refresh($link['id'],$binding,DRC_PASSWORD,'drc_read_dns','drc_read');
    drc_ok('Newest malformed map is retained unavailable',$r['state']==='unavailable');drc_deny('Newest failed map masks old complete anchor',fn()=>drc_change(bin2hex(random_bytes(16)),$link,$failedAnchor));
    $anchor=drc_map($link);$binding=$anchor['binding'];$before=drc_counts();
    drc_ok('Request audit fault rolls back consumed grant and request',drc_fault('request',fn()=>drc_change(bin2hex(random_bytes(16)),$link,$anchor),'provider_document_root_changes')&&drc_counts()===$before&&drc_ready());
    $stage='dispatch';$first=bin2hex(random_bytes(16));$firstAnchor=$anchor;
    $duringDns=function()use($first):void{drc_ok('Approval exists before a gateway claim',one('SELECT id FROM '.table('provider_operation_grants').' WHERE id=?',[$first])!==null&&one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$first])===null);};
    $duringPost=function()use($first,$link,$anchor):void{
        global $capturedAuthorize;
        $before=drc_counts();drc_deny('Claimed in-flight request cannot verify',fn()=>provider_document_root_controls_verify($first,DRC_PASSWORD,'drc_read_dns','drc_read'));
        drc_ok('In-flight verification performs no read',drc_counts()===$before);
        $session=$_SESSION;$saved=drc_digest();
        foreach(['owner auth','credential','client','grant'] as $case){
            if($case==='owner auth'){$old=one('SELECT auth_version FROM '.table('users').' WHERE id=1')['auth_version'];query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);}
            elseif($case==='credential'){$old=provider_settings_load($link['profile_id'])['credentials'];query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',['changed-synthetic-seal',$link['profile_id']]);}
            elseif($case==='client'){$old=$link['client_id'];query('UPDATE '.table('provider_package_links').' SET client_id=2 WHERE id=?',[$link['id']]);}
            else{$old=null;query('UPDATE '.table('provider_operation_grants').' SET revoked_at=? WHERE id=?',[gmdate('Y-m-d H:i:s'),$first]);}
            try{drc_deny('Actual send-boundary reauthorization refuses changed '.$case,$capturedAuthorize);}
            finally{if($case==='owner auth')query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$old]);elseif($case==='credential')query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[$old,$link['profile_id']]);elseif($case==='client')query('UPDATE '.table('provider_package_links').' SET client_id=? WHERE id=?',[$old,$link['id']]);else query('UPDATE '.table('provider_operation_grants').' SET revoked_at=NULL WHERE id=?',[$first]);$_SESSION=$session;unset($GLOBALS['cwp_settings']);}
        }
        drc_ok('Boundary revocation probes restore exact SQL and original session',drc_digest()===$saved&&drc_counts()===$before&&$_SESSION===$session);
        drc_contender(['request'=>bin2hex(random_bytes(16)),'link'=>$link['id'],'anchor'=>$anchor['id'],'binding'=>$anchor['binding']->sha256,'name'=>DRC_NAME,'path'=>DRC_NEW]);
    };
    $result=drc_change($first,$link,$anchor);$ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$first]);
    drc_ok('Actual gateway sends one canonical one-host POST and retains unknown result',$posts===1&&$wire[0]===['url'=>'https://api.20i.com/package/701/web/updateDocRoot','body'=>'{"update":{"site701.synthetic.invalid":"public_html/reviewed"}}']&&$result['status']==='uncertain'&&$ledger['status']==='uncertain'&&(int)$ledger['attempts']===1);
    drc_ok('Summary retains exact name old path desired path and original request',$result['id']===$first&&$result['name']===DRC_NAME&&$result['before_path']===DRC_OLD&&$result['requested_path']===DRC_NEW);
    drc_deny('Completed authorization callback cannot dispatch later',$capturedAuthorize);
    $before=drc_counts();$replay=drc_change($first,$link,$firstAnchor);drc_ok('Exact replay retains history without another request or send',$replay['status']==='uncertain'&&drc_counts()===$before);
    drc_deny('Changed-path retry refuses',fn()=>drc_change($first,$link,$firstAnchor,'public_html/changed'));
    drc_deny('New request ID cannot bypass pending package fence',fn()=>drc_change(bin2hex(random_bytes(16)),$link,$anchor));
    drc_deny('Another hostname cannot bypass package-wide pending fence',fn()=>provider_document_root_controls_change(bin2hex(random_bytes(16)),$link['id'],$anchor['id'],$binding->sha256,'other701.synthetic.invalid','public_html/elsewhere',true,DRC_PASSWORD,'drc_write_dns','drc_perform'));
    drc_ok('Pending request suppresses old current map and creates no sharing',!drc_locked(fn()=>provider_document_root_controls_current($link['id']))&&drc_count('provider_document_roots_permissions')===0);
    $stage='readback';$readStatus=200;$readBody=json_encode(['other701.synthetic.invalid'=>DRC_NEW],JSON_THROW_ON_ERROR);$different=drc_verify($first,$link);
    drc_ok('Complete map missing exact hostname is different and keeps fence',$different['status']==='uncertain'&&$different['last_check']['disposition']==='different'&&$different['last_check']['path']===null&&drc_pending($link)['id']===$first);
    $readBody='null';$unknown=drc_verify($first,$link);drc_ok('Malformed fresh read is unavailable not a confirmed empty root',$unknown['status']==='uncertain'&&$unknown['last_check']['disposition']==='unavailable'&&$unknown['last_check']['path']===null&&drc_pending($link)['id']===$first);
    $readBody=json_encode([DRC_NAME=>DRC_NEW,'other701.synthetic.invalid'=>'public_html/other'],JSON_THROW_ON_ERROR);$verified=drc_verify($first,$link);
    drc_ok('Only a separate new exact hostname read releases the fence',$verified['status']==='observed'&&$verified['last_check']['path']===DRC_NEW&&drc_pending($link)===null&&$posts===1);
    drc_ok('Readback preserves original one-attempt ledger byte-exact',one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$first])===$ledger);
    $before=drc_counts();drc_deny('Observed request cannot reuse verify as another refresh',fn()=>provider_document_root_controls_verify($first,DRC_PASSWORD,'drc_read_dns','drc_read'));drc_ok('Repeated verification is local',drc_counts()===$before);
    $stage='recovery';$anchor=provider_document_roots_observation(provider_document_roots_latest($link['id']));$second=bin2hex(random_bytes(16));$beforePosts=$posts;$terminalCount=drc_count('provider_document_root_dispatch_ends');
    drc_ok('Actual terminal audit failure occurs after one provider send',drc_fault('terminal',fn()=>drc_change($second,$link,$anchor,'public_html/second'),'provider_document_root_dispatch_ends')&&$posts===$beforePosts+1);
    $saved=[one('SELECT * FROM '.table('provider_document_root_changes').' WHERE id=?',[$second]),one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$second]),one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$second])];
    $interrupted=drc_summary($second);$hash=$interrupted['recovery_ledger_sha256'];
    drc_ok('Missing local terminal retains finished ledger and an explicit recovery hash',$interrupted['status']==='interrupted'&&is_string($hash)&&strlen($hash)===64&&drc_count('provider_document_root_dispatch_ends')===$terminalCount);
    drc_deny('Interrupted terminal cannot verify before local recovery',fn()=>provider_document_root_controls_verify($second,DRC_PASSWORD,'drc_read_dns','drc_read'));
    $before=drc_counts();drc_change($second,$link,$anchor,'public_html/second');drc_ok('Interrupted exact retry never resumes dispatch',drc_counts()===$before);
    drc_deny('Wrong recovery hash refuses',fn()=>drc_recover($second,str_repeat('f',64)));drc_deny('Wrong recovery password refuses',fn()=>provider_document_root_controls_recover_finished($second,$hash,'wrong'));
    drc_owner(3);drc_deny('Another current owner cannot adopt original approval',fn()=>drc_recover($second,$hash));drc_owner();
    foreach(['owner auth','credential','client','grant'] as $case){
        if($case==='owner auth'){$old=one('SELECT auth_version FROM '.table('users').' WHERE id=1')['auth_version'];query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);drc_owner();}
        elseif($case==='credential'){$old=provider_settings_load($profile)['credentials'];query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',['changed-synthetic-seal',$profile]);}
        elseif($case==='client'){$old=$link['client_id'];query('UPDATE '.table('provider_package_links').' SET client_id=2 WHERE id=?',[$link['id']]);}
        else{$old=null;query('UPDATE '.table('provider_operation_grants').' SET revoked_at=? WHERE id=?',[gmdate('Y-m-d H:i:s'),$second]);}
        try{drc_deny('Changed '.$case.' refuses captured finished-ledger recovery',fn()=>drc_recover($second,$hash));}
        finally{if($case==='owner auth'){query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$old]);drc_owner();}elseif($case==='credential')query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[$old,$profile]);elseif($case==='client')query('UPDATE '.table('provider_package_links').' SET client_id=? WHERE id=?',[$old,$link['id']]);else query('UPDATE '.table('provider_operation_grants').' SET revoked_at=NULL WHERE id=?',[$second]);}
    }
    drc_ok('All recovery authority refusals preserve original sources and transport counts',drc_counts()===$before&&[one('SELECT * FROM '.table('provider_document_root_changes').' WHERE id=?',[$second]),one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$second]),one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$second])]===$saved);
    $digest=drc_digest();drc_ok('Recovery audit fault rolls back the actual inserted terminal',drc_fault('terminal',fn()=>drc_recover($second,$hash),'provider_document_root_dispatch_ends')&&drc_digest()===$digest&&drc_ready());
    $recovered=drc_recover($second,$hash);drc_ok('Recovery appends local witness without send or success claim',$recovered['status']==='uncertain'&&$recovered['recovered_dispatch']===true&&$posts===$beforePosts+1&&drc_count('provider_document_root_dispatch_ends')===$terminalCount+1);
    $before=drc_counts();drc_deny('Recovery cannot overwrite an existing terminal',fn()=>drc_recover($second,$hash));drc_ok('Recovery replay does not mutate state',drc_counts()===$before);
    $readBody=json_encode([DRC_NAME=>'public_html/second','other701.synthetic.invalid'=>'public_html/other'],JSON_THROW_ON_ERROR);$confirmed=drc_verify($second,$link);
    drc_ok('Recovered dispatch still needs independent exact readback',$confirmed['status']==='observed'&&$posts===$beforePosts+1&&drc_pending($link)===null);
    $stage='history';
    drc_damage('Lost prepared marker cannot hide established history',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_document_root_control_schema_version']));
    drc_damage('Changed approval scope fails historical validation',fn()=>query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',['{}',$second]));
    drc_damage('Lost original finished ledger is not a no-send result',fn()=>query('DELETE FROM '.table('provider_operations').' WHERE id=?',[$saved[2]['id']]));
    drc_damage('Original reviewed map corruption cannot be adopted',fn()=>query('UPDATE '.table('provider_document_roots_observations').' SET entries_json=? WHERE id=?',['[]',$firstAnchor['id']]));
    $check=one('SELECT * FROM '.table('provider_document_root_change_checks').' WHERE request_id=? AND disposition=?',[$second,'observed']);
    drc_damage('Paired check and audit deletion cannot release history',function()use($check):void{
        drc_ok('Intended check row removed',query('DELETE FROM '.table('provider_document_root_change_checks').' WHERE id=?',[$check['id']])->rowCount()===1);
        $detail='Document-root change check '.$check['id'].' '.provider_document_roots_digest(provider_document_root_controls_values('check',$check));
        drc_ok('Intended matching check audit removed',query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.document_root_control_check',$detail])->rowCount()===1);
    });
    $terminal=one('SELECT * FROM '.table('provider_document_root_dispatch_ends').' WHERE id=?',[$second]);
    drc_damage('Paired terminal and audit loss cannot be recreated as interruption',function()use($terminal):void{
        drc_ok('Intended established terminal removed',query('DELETE FROM '.table('provider_document_root_dispatch_ends').' WHERE id=?',[$terminal['id']])->rowCount()===1);
        $detail='Document-root change terminal '.$terminal['id'].' '.provider_document_roots_digest(provider_document_root_controls_values('terminal',$terminal));
        drc_ok('Intended established terminal audit removed',query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.document_root_control_terminal',$detail])->rowCount()===1);
    });
    foreach(['request','terminal','check'] as $kind){$tableName=provider_document_root_controls_tables()[$kind];$row=one('SELECT * FROM '.table($tableName).' ORDER BY id LIMIT 1');
        $sql=provider_document_roots_sql_digest(array_map(fn($key)=>'w.'.$key,provider_document_root_controls_columns($kind)));
        drc_ok('SQL and PHP exact witness bytes agree '.$kind,query('SELECT '.$sql.' FROM '.table($tableName).' w WHERE id=?',[$row['id']])->fetchColumn()===provider_document_roots_digest(provider_document_root_controls_values($kind,$row)));
    }
    $stage='nonrecovery';$anchor=provider_document_roots_observation(provider_document_roots_latest($link['id']));$localId=bin2hex(random_bytes(16));$beforePosts=$posts;
    $local=drc_change($localId,$link,$anchor,DRC_NEW,static fn()=>throw new RuntimeException('Synthetic pre-dispatch resolver refusal'));
    drc_ok('Provable pre-perform refusal is local not-sent without ledger',$local['status']==='not_sent'&&$posts===$beforePosts&&one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$localId])===null);
    drc_deny('No-send still requires a new saved anchor for another approval',fn()=>drc_change(bin2hex(random_bytes(16)),$link,$anchor,DRC_NEW));
    $other=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=702');$longOld=str_repeat('é',512);$longNew=str_repeat('p',1024);
    $otherAnchor=drc_map($other,$longOld);$missing=bin2hex(random_bytes(16));
    try{drc_change($missing,$other,$otherAnchor,$longNew,static fn()=>throw new PDOException('Synthetic interrupted resolver'));drc_ok('Interrupted resolver propagates',false);}catch(PDOException){drc_ok('Interrupted resolver propagates',true);}
    $longRequest=one('SELECT * FROM '.table('provider_document_root_changes').' WHERE id=?',[$missing]);$longGrant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$missing]);
    $commitment=['version'=>1,'request_id'=>$missing,'request_sha256'=>hash('sha256',$longRequest['document_json'])];$longDocument=json_decode($longRequest['document_json'],true,20,JSON_THROW_ON_ERROR);
    drc_ok('Maximum old and new path bytes retain full reviewed request with bounded grant commitment',strlen($longOld)===1024&&strlen($longNew)===1024&&strlen($longRequest['document_json'])>4096&&strlen($longRequest['document_json'])<=16384
        &&$longDocument['before_path']===$longOld&&$longDocument['params']['path']===$longNew&&$longDocument['anchor_proof']===$otherAnchor['proof']
        &&$longGrant['scope_params']===provider_document_roots_json($commitment)&&strlen($longGrant['scope_params'])<=4096
        &&$longGrant['scope_fingerprint']===provider_operations_scope(DocumentRootChange::OPERATION,$commitment)&&drc_ready());
    drc_ok('Interrupted pre-DNS request retains no ledger or recovery hash',drc_summary($missing)['status']==='interrupted'&&drc_summary($missing)['recovery_ledger_sha256']===null&&one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$missing])===null);
    drc_deny('Missing ledger cannot be reconstructed by owner recovery',fn()=>drc_recover($missing,str_repeat('0',64)));
    drc_deny('Missing ledger cannot issue a verification GET',fn()=>provider_document_root_controls_verify($missing,DRC_PASSWORD,'drc_read_dns','drc_read'));
    $before=drc_counts();drc_change($missing,$other,$otherAnchor,$longNew);drc_deny('Missing completion fences a new request on its package',fn()=>drc_change(bin2hex(random_bytes(16)),$other,$otherAnchor));drc_ok('Missing-completion replay never resumes work',drc_counts()===$before);
    $claimedLink=one('SELECT * FROM '.table('provider_package_links').' WHERE package_id=703');$claimedAnchor=drc_map($claimedLink);$claimed=bin2hex(random_bytes(16));
    $claimOnly=static function(array $profile,string $key,string $address,callable $authorize,ProviderOperation $op):never{
        drc_boundary($authorize);$fp=ProviderGateway::requestFingerprint('twenty-i',$profile['id'].':'.$profile['revision'].':'.provider_operations_credential_fingerprint($key),$op);
        (new ProviderOperationLedger())->claim($op->idempotencyKey,$fp,time());throw new PDOException('Synthetic interrupted claimed worker');
    };
    try{drc_change($claimed,$claimedLink,$claimedAnchor,DRC_NEW,null,$claimOnly);drc_ok('Claimed worker interruption propagates',false);}catch(PDOException){drc_ok('Claimed worker interruption propagates',true);}
    $claim=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.$claimed]);
    drc_ok('Real unfinished zero-attempt claim stays interrupted',$claim['status']==='claimed'&&(int)$claim['attempts']===0&&drc_summary($claimed)['status']==='interrupted'&&drc_summary($claimed)['recovery_ledger_sha256']===null);
    $before=drc_counts();drc_deny('Claimed ledger cannot be recovered as finished',fn()=>drc_recover($claimed,provider_document_root_controls_ledger_digest($claim)));
    drc_deny('Claimed ledger cannot verify or release its fence',fn()=>provider_document_root_controls_verify($claimed,DRC_PASSWORD,'drc_read_dns','drc_read'));
    drc_change($claimed,$claimedLink,$claimedAnchor);drc_ok('Claimed recovery denial and replay cause no GET or POST',drc_counts()===$before&&one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$claim['id']])===$claim&&drc_pending($claimedLink)['id']===$claimed);
    drc_ok('All retained resolved and unresolved history is internally ready',drc_ready());
    $public=json_encode(['checks'=>$checks,'requests'=>drc_locked(fn()=>[provider_document_root_controls_summary($first),provider_document_root_controls_summary($second),provider_document_root_controls_summary($missing),provider_document_root_controls_summary($claimed)])],JSON_THROW_ON_ERROR);
    drc_ok('Safe summaries and assertions disclose no fixture credential or password',!str_contains($public,DRC_PASSWORD)&&!str_contains($public,DRC_SECRET)&&!str_contains($public,base64_encode(DRC_SECRET)));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected native document root controls failure at '.$stage,'passed'=>false];$failure=['stage'=>$stage,'class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null];}
finally{restore_error_handler();}
$passed=count($checks)>0&&!array_filter($checks,fn($c)=>$c['passed']!==true);
$result=['passed'=>$passed,'scope'=>'Actual isolated Linux SQL, owner sessions and shared one-send gateway with synthetic provider transports. No live provider call. Review attestation does not prove directory existence or platform eligibility. Native execution is required; source and syntax alone are not acceptance.','checks'=>$checks];
if(isset($failure))$result['failure']=$failure;
$bytes=json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";if(fwrite($handle,$bytes)!==strlen($bytes)||!fflush($handle))exit(1);fclose($handle);
echo ($passed?'PASS':'FAIL').': '.count($checks)." document root controls native checks\n";exit($passed?0:1);
