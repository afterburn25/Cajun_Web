<?php
/** Installed-code companion only. No deployed route and no real provider transport. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==4)exit(2);
$port=getenv('CWP_TEST_PORT');if(!is_string($port)||!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
$repo=realpath(__DIR__.'/..');$root=realpath($argv[1]);$web=realpath($argv[3]);
$expected=$repo.'/test-runtime/registered-nameserver-changes-upgrade/rnsc-upgrade/web';
if(!$repo||!$root||!$web||$web!==$expected||$root!==$web.'/subfolder')exit(2);
foreach([$root,$web,dirname($web),dirname(dirname($web)),$repo.'/test-runtime'] as $path)if(is_link($path))exit(2);
$mode=$argv[2];if(!in_array($mode,['preflight','snapshot','seed','verify','recover-terminal','damage','refuse'],true))exit(2);
$checks=[];
function rncu_check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
if($mode==='preflight'){
    $db=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4',(string)getenv('CWP_TEST_USER'),(string)getenv('CWP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $s=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(table_name,6)=BINARY ?');$s->execute(['rnscu_']);
    rncu_check('Dedicated installed namespace is empty',(int)$s->fetchColumn()===0);
    echo json_encode(['passed'=>true,'checks'=>$checks],JSON_THROW_ON_ERROR);exit;
}
if(!is_file($root.'/config.php')||is_link($root.'/config.php'))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';
if(config()['db']['name']!=='cwp_test'||config()['db']['prefix']!=='rnscu_'||config()['db']['host']!=='127.0.0.1'||(int)config()['db']['port']!==(int)$port)exit(2);

/** Exact original row/column hashes; only existing authentication bookkeeping is excluded. */
function rncu_snapshot():array{
    $prefix=config()['db']['prefix'];$out=['version'=>CWP_VERSION,'tables'=>[],'private_files'=>[]];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(table_name,?)=BINARY ? ORDER BY table_name',[strlen($prefix),$prefix]) as $entry){
        $physical=$entry['TABLE_NAME'];$name=substr($physical,strlen($prefix));if(!preg_match('/\A[a-z][a-z0-9_]*\z/',$name))throw new RuntimeException('Unexpected fixture table.');
        if($name==='rate_limits')continue;
        $keys=array_column(all("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=? AND constraint_name='PRIMARY' ORDER BY ORDINAL_POSITION",[$physical]),'COLUMN_NAME');
        if(!$keys)throw new RuntimeException('Missing primary identity.');$rows=[];
        foreach(all('SELECT * FROM '.table($name)) as $row){$identity=[];foreach($keys as $key)$identity[$key]=$row[$key];$id=hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));$columns=[];
            foreach($row as $column=>$value){if($name==='users'&&$column==='last_login')continue;$columns[$column]=hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
            ksort($columns);if(isset($rows[$id]))throw new RuntimeException('Duplicate identity.');$rows[$id]=$columns;
        }ksort($rows);$out['tables'][$name]=['count'=>count($rows),'rows'=>(object)$rows];
    }
    $private=realpath((string)config()['storage']);if(!$private||is_link($private))throw new RuntimeException('Unexpected private root.');
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS)) as $file){
        $relative=str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($private)+1));
        if(preg_match('~^(?:structure-[^/]+|sessions)(?:/|$)~D',$relative))continue;
        if($file->isLink())throw new RuntimeException('Unexpected private link.');
        if($file->isFile())$out['private_files'][$relative]=['sha256'=>hash_file('sha256',$file->getPathname()),'bytes'=>$file->getSize()];
    }ksort($out['private_files']);$out['private_files']=(object)$out['private_files'];return $out;
}
function rncu_digest():string{return hash('sha256',json_encode(rncu_snapshot(),JSON_THROW_ON_ERROR));}
if($mode==='snapshot'){echo json_encode(['passed'=>true,'checks'=>[],'snapshot'=>rncu_snapshot()],JSON_THROW_ON_ERROR);exit;}
require CWP_CODE_ROOT.'/app/providers/registered_nameserver_changes.php';
require_once CWP_CODE_ROOT.'/app/providers/registered_nameservers_views.php';
require_once CWP_CODE_ROOT.'/includes/layout/admin.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIRegistrarNameservers\{RegisteredNameserverBinding,RegisteredNameserverObservation,RegisteredNameservers};
use CajunWebPro\Foundation\TwentyI\Operations\{RegistrarNameserverChange,TwentyIOperationAdapter};


const RNCU_PASSWORD='Synthetic nameserver changes upgrade owner passphrase';
const RNCU_SECRET='SYNTHETIC_NAMESERVER_CHANGES_UPGRADE_KEY';
const RNCU_PREFIX='rnscu_';
const RNCU_LABEL='Nameserver changes upgrade fixture';
const RNCU_OLD=['ns1.synthetic.invalid','ns2.synthetic.invalid'];
const RNCU_NEW=['ns3.synthetic.invalid','ns4.synthetic.invalid'];
const RNCU_FIRST='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const RNCU_SECOND='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$dns=0;$gets=0;$posts=0;$wire=[];$readStatus=200;$readBody=json_encode(['result'=>RNCU_OLD]);
$writeMode='unknown';$duringDns=null;$duringSend=null;
function rncu_ok(string $name,bool $passed):void{rncu_check($name,$passed);}
function rncu_deny(string $name,callable $call):void{
    $caught=false;try{$call();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$caught=true;}rncu_ok($name,$caught);
}
function rncu_locked(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function rncu_mutex():bool{$key='cwp-staff-'.substr(hash('sha256','cwp_test|'.RNCU_PREFIX),0,40);return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;}
function rncu_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function rncu_link():array{
    $p=one('SELECT id FROM '.table('provider_profiles').' WHERE label=?',[RNCU_LABEL]);
    $l=$p?one('SELECT * FROM '.table('provider_registrar_links').' WHERE profile_id=?',[$p['id']]):null;
    if(!$l)throw new RuntimeException('Missing original fixture assignment.');return $l;
}
/** Genuine bounded wait, outside locks and transactions. Never changes a stored clock. */
function rncu_wait(string $profile):void{
    if(db()->inTransaction()||rncu_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');
    $deadline=microtime(true)+32;
    while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
/** The existing read executor releases its intent mutex before DNS. */
function rncu_read_dns():string{
    global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';
}
/** The new write service deliberately retains its mutex through DNS and one send. */
function rncu_write_dns():string{
    global $dns,$duringDns;
    if(db()->inTransaction()||!rncu_mutex())throw new LogicException('Write DNS needs owning mutex without transaction.');
    $row=one('SELECT id FROM '.table('provider_nameserver_change_requests').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Approval was not durable before DNS.');
    $request=registered_nameserver_changes_request($row['id']);
    if($request['grant']['consumed_key']!=='nameservers:'.$row['id']||$request['ledger']!==null
        ||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail LIKE ?',
            ['provider.registered_nameserver_changes_request','Registered nameserver change request '.$row['id'].' %'])->fetchColumn()!==1)
        throw new LogicException('Original consumed approval and audit must precede DNS.');
    $dns++;if($duringDns){$call=$duringDns;$duringDns=null;$call();}return '8.8.8.8';
}
function rncu_boundary(callable $authorize):void{
    $authorize();if(db()->inTransaction()||!rncu_mutex())throw new LogicException('Wire boundary needs owning mutex without transaction.');
}
final class RncuReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readStatus,$readBody;rncu_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null)throw new LogicException('Unexpected synthetic GET.');
        $gets++;return new TransportResponse($readStatus,[],$readBody);
    }
}
function rncu_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?RegisteredNameserverBinding $nameserverContext=null):InventorySnapshot|RegisteredNameserverObservation{
    rncu_boundary($authorize);
    if($key!==RNCU_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read credential.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{rncu_boundary($authorize);return $key;});
    if($operation===InventoryRead::DOMAINS_LIST)return InventoryReader::read(new RncuReadTransport($authorize,'https://api.20i.com/domain'),$credential,$operation,null,time());
    if($operation!==RegisteredNameservers::OPERATION||!$nameserverContext)throw new LogicException('Unexpected synthetic read operation.');
    return RegisteredNameservers::read(new RncuReadTransport($authorize,'https://api.20i.com/domain/'.$nameserverContext->data['domain_id'].'/nameservers'),$credential,$nameserverContext,time());
}
final class RncuWriteTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$wire,$writeMode,$duringSend;rncu_boundary($this->authorize);RegistrarNameserverChange::wire($request);
        if(!in_array($request->url,['https://api.20i.com/domain/701/nameservers','https://api.20i.com/domain/702/nameservers'],true))throw new LogicException('Unexpected synthetic POST domain.');
        if(($request->headers['Authorization']??null)!=='Bearer '.base64_encode(RNCU_SECRET))throw new LogicException('Synthetic POST credential mismatch.');
        $posts++;$wire[]=['method'=>$request->method,'url'=>$request->url,'body'=>$request->body];
        if($duringSend){$call=$duringSend;$duringSend=null;$call();}
        if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
        if($writeMode!=='unknown')throw new LogicException('Unexpected synthetic POST mode.');
        return new TransportResponse(200,[],'{}');
    }
}
function rncu_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    rncu_boundary($authorize);
    if($key!==RNCU_SECRET||$address!=='8.8.8.8'||$operation->name!==RegistrarNameserverChange::OPERATION)throw new LogicException('Wrong synthetic write identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),
        static function()use($authorize,$key):string{rncu_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new RncuWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function rncu_refresh(array $link,bool $wait=true):array{
    if($wait)rncu_wait($link['profile_id']);$profile=provider_settings_load($link['profile_id']);$source=provider_registrar_latest($link['profile_id']);
    return registered_nameservers_refresh($link['id'],(int)$link['revision'],(int)$profile['revision'],$source['id'],RNCU_PASSWORD,'rncu_read_dns','rncu_read');
}
function rncu_change(string $id,array $link,array $anchor,array $desired=RNCU_NEW):array{
    return registered_nameserver_changes_change($id,$link['id'],$anchor['id'],$anchor['binding']->sha256,$desired,true,RNCU_PASSWORD,'rncu_write_dns','rncu_perform');
}
function rncu_anchor(array $link):array{
    $receipt=rncu_refresh($link);if($receipt['state']!=='complete')throw new LogicException('Synthetic anchor did not complete.');
    return registered_nameservers_read_row($receipt['id']);
}
function rncu_readback(string $request,array $link):array{
    rncu_wait($link['profile_id']);return registered_nameserver_changes_verify($request,RNCU_PASSWORD,'rncu_read_dns','rncu_read');
}
function rncu_summary(string $id):array{return rncu_locked(fn()=>registered_nameserver_changes_summary($id));}
function rncu_ready():bool{return rncu_locked(fn()=>registered_nameserver_changes_ready());}
function rncu_pending(array $link):?array{return rncu_locked(fn()=>registered_nameserver_changes_pending($link['profile_id'],(int)$link['domain_id']));}
function rncu_damage(string $name,callable $mutate):void{
    $before=rncu_digest();$session=$_SESSION;
    staff_locked(function()use($name,$mutate):void{db()->beginTransaction();try{$mutate();rncu_deny($name,fn()=>registered_nameserver_changes_ready());}finally{if(db()->inTransaction())db()->rollBack();}});
    $_SESSION=$session;unset($GLOBALS['cwp_settings']);rncu_ok($name.' restores exact retained bytes',rncu_digest()===$before&&rncu_ready());
}

function rncu_replay(string $id,array $link):array{
    $q=rncu_locked(fn()=>registered_nameserver_changes_request($id));
    return registered_nameserver_changes_change($id,$link['id'],$q['document']['anchor_id'],$q['binding']->sha256,$q['document']['params']['ns'],true,RNCU_PASSWORD,'rncu_write_dns','rncu_perform');
}
/** Actual global renderer queries current installed history; HTML is never retained in QA. */
function rncu_history(bool $recovered):void{
    ob_start();try{registered_nameserver_changes_history_render();$html=ob_get_contents();}finally{ob_end_clean();}
    rncu_ok('Global history retains both original request identities',substr_count($html,' data-nameserver-change-history>')===2&&str_contains($html,RNCU_FIRST)&&str_contains($html,RNCU_SECOND));
    rncu_ok('Global history preserves resolved and pending meaning',str_contains($html,'Requested nameservers reported after the request')&&str_contains($html,$recovered?'Change requested — result not yet confirmed':'Request interrupted — review required'));
    rncu_ok('Global history never exposes original secret or private proof',!str_contains($html,RNCU_SECRET)&&!str_contains($html,base64_encode(RNCU_SECRET))&&!str_contains($html,'credential_sha256'));
}
function rncu_history_verify(bool $recovered=false):void{
    global $dns,$gets,$posts;
    $link=rncu_link();$before=rncu_digest();$io=[$dns,$gets,$posts];
    rncu_ok('Installed full history authenticates original request grant ledger terminal and checks',rncu_ready());
    $first=rncu_summary(RNCU_FIRST);$second=rncu_summary(RNCU_SECOND);
    rncu_ok('First original request remains resolved by exact later observation',$first['status']==='observed'&&$first['last_check']['nameservers']===RNCU_NEW);
    rncu_ok('Second original request retains unresolved profile-domain fence',$second['status']===($recovered?'uncertain':'interrupted')&&$second['recovered_dispatch']===$recovered&&rncu_pending($link)['id']===RNCU_SECOND);
    rncu_ok('Unresolved request suppresses current nameserver control',!rncu_locked(fn()=>registered_nameserver_changes_current($link['id']))&&registered_nameservers_project($link['id'],1)['nameservers']===null);
    rncu_ok('Both exact approvals replay without sending',rncu_replay(RNCU_FIRST,$link)===$first&&rncu_replay(RNCU_SECOND,$link)===$second);
    $latest=registered_nameserver_changes_latest($link['id']);
    rncu_deny('New approval cannot cross retained unresolved resource fence',fn()=>rncu_change(str_repeat('c',32),$link,$latest,RNCU_OLD));
    rncu_history($recovered);
    $profile=provider_settings_load($link['profile_id']);$key=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
    rncu_ok('Original encryption material still opens exact saved synthetic credential',hash_equals(RNCU_SECRET,$key));unset($key);
    rncu_ok('History verification and replays leave exact rows keys and transport counts unchanged',$before===rncu_digest()&&[$dns,$gets,$posts]===$io);
    rncu_ok('Unrelated renewal setup remains absent',!one('SELECT name FROM '.table('settings').' WHERE name=?',['domain_contracts_schema_version'])&&!array_filter(domain_contracts_tables()));
}
function rncu_probes():void{
    $check=one('SELECT id,receipt_id FROM '.table('provider_nameserver_change_checks').' WHERE request_id=? ORDER BY id LIMIT 1',[RNCU_FIRST]);
    $mutations=[
        'request'=>function():void{query('DELETE FROM '.table('provider_nameserver_change_checks').' WHERE request_id=?',[RNCU_FIRST]);query('DELETE FROM '.table('provider_nameserver_change_terminals').' WHERE id=?',[RNCU_FIRST]);query('DELETE FROM '.table('provider_nameserver_change_requests').' WHERE id=?',[RNCU_FIRST]);},
        'grant'=>fn()=>query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',['{}',RNCU_FIRST]),
        'terminal'=>fn()=>query('DELETE FROM '.table('provider_nameserver_change_terminals').' WHERE id=?',[RNCU_FIRST]),
        'check'=>fn()=>query('DELETE FROM '.table('provider_nameserver_change_checks').' WHERE id=?',[$check['id']]),
        'ledger'=>fn()=>query('DELETE FROM '.table('provider_operations').' WHERE idempotency_key=?',['nameservers:'.RNCU_FIRST]),
        'readback set'=>function():void{
            $rows=all('SELECT s.id FROM '.table('registered_nameserver_sets').' s LEFT JOIN '.table('provider_nameserver_change_checks').' c ON c.receipt_id=s.id ORDER BY (c.request_id IS NULL),s.id');
            if(!$rows)throw new RuntimeException('Installed readiness fixture requires an observed nameserver set.');
            query('DELETE FROM '.table('registered_nameserver_sets').' WHERE id=?',[$rows[0]['id']]);
        },
        'marker'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['registered_nameserver_changes_schema_version']),
        'counter'=>fn()=>save_setting('registered_nameserver_changes_check_count','00'),
        'terminal audit'=>fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=?',['provider.registered_nameserver_changes_terminal']),
    ];
    foreach($mutations as $name=>$mutate)rncu_damage('Lost or corrupted '.$name.' refuses installed readiness',$mutate);
}
function rncu_refuse():void{
    $before=rncu_digest();$link=rncu_link();
    rncu_deny('Lost established check refuses readiness',fn()=>rncu_ready());
    rncu_deny('Lost established check refuses explicit setup repair',fn()=>registered_nameserver_changes_prepare(RNCU_PASSWORD));
    rncu_deny('Lost established check cannot release pending fence',fn()=>rncu_pending($link));
    rncu_deny('Lost established check cannot replay a provider send',fn()=>rncu_replay(RNCU_SECOND,$link));
    rncu_ok('All corruption refusals preserve remaining history',$before===rncu_digest());
}
try{
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/subfolder/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$web;start_session();
    $actor=one('SELECT * FROM '.table('users').' WHERE email=?',['rnsc-upgrade@example.invalid']);
    if(!$actor||!password_verify(RNCU_PASSWORD,$actor['password_hash']))throw new RuntimeException('Wrong installed owner.');
    $_SESSION=['uid'=>(int)$actor['id'],'auth_version'=>$actor['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);
    provider_settings_owner(RNCU_PASSWORD);
    if($mode==='seed'){
        rncu_ok('Current update leaves both nameserver features unprepared',!array_filter(registered_nameservers_tables())&&!array_filter(registered_nameserver_changes_inventory())&&!one('SELECT name FROM '.table('settings').' WHERE name=?',['registered_nameserver_changes_schema_version']));
        provider_settings_migrate(RNCU_PASSWORD);$profile=provider_settings_save('',0,RNCU_LABEL,true,RNCU_SECRET,RNCU_PASSWORD);provider_inventory_migrate(RNCU_PASSWORD);provider_registrar_prepare(RNCU_PASSWORD);registered_nameservers_prepare(RNCU_PASSWORD);provider_operations_migrate(RNCU_PASSWORD);
        rncu_ok('Read and operation prerequisites never silently prepare change authority',!rncu_ready());
        registered_nameserver_changes_prepare(RNCU_PASSWORD);$before=rncu_digest();registered_nameserver_changes_prepare(RNCU_PASSWORD);
        rncu_ok('Explicit installed change setup is idempotent',rncu_ready()&&rncu_digest()===$before);
        require_once CWP_CODE_ROOT.'/app/clients/accounts.php';if(!studio_ready())studio_migrate();$client=studio_client_save(0,'','Nameserver change upgrade client','rnsc-upgrade-client@example.invalid',true);
        $readBody='[{"id":701,"name":"upgrade.synthetic.invalid"}]';
        $source=provider_inventory_refresh_attempt($profile,1,RNCU_PASSWORD,'rncu_read_dns','rncu_read',InventoryRead::DOMAINS_LIST);
        rncu_ok('Installed registrar source is a genuine complete synthetic GET',$source['state']==='complete');provider_registrar_link($profile,701,$client,1,$source['id'],RNCU_PASSWORD);$link=rncu_link();
        $readBody=json_encode(['result'=>RNCU_OLD]);$anchor=rncu_anchor($link);
        $one=rncu_change(RNCU_FIRST,$link,$anchor);
        rncu_ok('First approved installed request sends once and records uncertainty',$one['status']==='uncertain'&&$posts===1);
        $readStatus=403;$failed=rncu_readback(RNCU_FIRST,$link);$readStatus=200;
        rncu_ok('Genuine failed readback retains the original fence',$failed['last_check']['disposition']==='unavailable'&&rncu_pending($link)['id']===RNCU_FIRST);
        $different=rncu_readback(RNCU_FIRST,$link);
        rncu_ok('Genuine different set does not confirm the request',$different['last_check']['disposition']==='different'&&rncu_pending($link)['id']===RNCU_FIRST);
        $readBody=json_encode(['result'=>RNCU_NEW]);$matched=rncu_readback(RNCU_FIRST,$link);
        rncu_ok('Only new exact matching readback releases the first fence',$matched['status']==='observed'&&rncu_pending($link)===null&&$posts===1);
        $anchor2=registered_nameserver_changes_latest($link['id']);$writeMode='timeout';
        db()->exec('CREATE TRIGGER rnscu_terminal_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.registered_nameserver_changes_terminal' THEN SET @rnscu_hits=COALESCE(@rnscu_hits,0)+1; SET @rnscu_rows=(SELECT COUNT(*) FROM ".table('provider_nameserver_change_terminals')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic installed terminal audit fault'; END IF; END");
        query('SET @rnscu_hits=0,@rnscu_rows=0');$fault=false;
        try{rncu_change(RNCU_SECOND,$link,$anchor2,RNCU_OLD);}catch(PDOException $e){$fault=$e->getCode()==='45000'&&(int)query('SELECT @rnscu_hits')->fetchColumn()===1&&(int)query('SELECT @rnscu_rows')->fetchColumn()===2;}finally{db()->exec('DROP TRIGGER rnscu_terminal_fault');}
        rncu_ok('Actual second send finishes ledger before terminal audit rollback',$fault&&$posts===2&&rncu_count('provider_nameserver_change_terminals')===1&&rncu_count('provider_operations')===2);
        rncu_ok('Both actual wire bodies contain only their complete disjoint sets',$wire===[['method'=>'POST','url'=>'https://api.20i.com/domain/701/nameservers','body'=>json_encode(['ns'=>RNCU_NEW,'old-ns'=>RNCU_OLD],JSON_UNESCAPED_SLASHES)],['method'=>'POST','url'=>'https://api.20i.com/domain/701/nameservers','body'=>json_encode(['ns'=>RNCU_OLD,'old-ns'=>RNCU_NEW],JSON_UNESCAPED_SLASHES)]]);
        rncu_history_verify();
    }elseif($mode==='verify'){
        rncu_history_verify();rncu_probes();
    }elseif($mode==='recover-terminal'){
        $s=rncu_summary(RNCU_SECOND);$ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['nameservers:'.RNCU_SECOND]);
        rncu_deny('Changed reviewed ledger digest refuses local recovery',fn()=>registered_nameserver_changes_recover_finished(RNCU_SECOND,str_repeat('0',64),RNCU_PASSWORD));
        $recovered=registered_nameserver_changes_recover_finished(RNCU_SECOND,$s['recovery_ledger_sha256'],RNCU_PASSWORD);
        rncu_ok('Actual owner recovers only missing terminal without another call',$recovered['status']==='uncertain'&&$recovered['recovered_dispatch']===true&&[$dns,$gets,$posts]===[0,0,0]&&$ledger===one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['nameservers:'.RNCU_SECOND]));
        rncu_deny('Second recovery cannot replace established terminal',fn()=>registered_nameserver_changes_recover_finished(RNCU_SECOND,$s['recovery_ledger_sha256'],RNCU_PASSWORD));rncu_history_verify(true);rncu_probes();
    }elseif($mode==='damage'){
        rncu_history_verify(true);
        rncu_locked(function():void{$row=one('SELECT id FROM '.table('provider_nameserver_change_checks').' WHERE request_id=? ORDER BY id LIMIT 1',[RNCU_FIRST]);if(query('DELETE FROM '.table('provider_nameserver_change_checks').' WHERE id=?',[$row['id']])->rowCount()!==1)throw new RuntimeException('Expected one deliberate lost check.');});rncu_refuse();
    }else rncu_refuse();
    rncu_ok('This phase never invokes synthetic transport except explicit initial seed',$mode==='seed'?[$gets,$posts]===[5,2]:[$dns,$gets,$posts]===[0,0,0]);
    echo json_encode(['passed'=>true,'checks'=>$checks,'synthetic_io'=>['dns'=>$dns,'gets'=>$gets,'posts'=>$posts]],JSON_THROW_ON_ERROR);
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR);exit(1);}
