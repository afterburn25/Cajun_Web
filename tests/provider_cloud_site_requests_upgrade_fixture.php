<?php
/** Installed-code companion. Synthetic provider transports only; never a deployed route. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==4)exit(2);
if(function_exists('posix_geteuid')&&posix_geteuid()===0)exit(2);
$port=getenv('CWP_TEST_PORT');if(!is_string($port)||!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
$repo=realpath(__DIR__.'/..');$root=realpath($argv[1]);$web=realpath($argv[3]);
$expected=$repo.'/test-runtime/provider-cloud-site-requests-upgrade/cwr-upgrade/web';
if(!$repo||!$root||!$web||$web!==$expected||$root!==$web.'/subfolder'||$argv[1]!==$root||$argv[3]!==$web)exit(2);
for($path=$root;$path!==$repo;$path=dirname($path))if(is_link($path)||$path===dirname($path))exit(2);
$mode=$argv[2];if(!in_array($mode,['preflight','snapshot','seed','verify','baseline-seed','unprepared','damage','refuse'],true))exit(2);


use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyI\Operations\{CloudWebsiteCreate,TwentyIOperationAdapter};
const CWRU_PASSWORD='Synthetic client cloud website upgrade owner passphrase';
const CWRU_CLIENT_PASSWORD='Synthetic client cloud website upgrade client passphrase';
const CWRU_SECRET='SYNTHETIC_CLIENT_CLOUD_WEBSITE_UPGRADE_KEY';
const CWRU_LABEL='Client cloud website upgrade fixture';
$checks=[];$dns=0;$gets=0;$posts=0;$wire=[];$readBody='';$writeMode='accepted';$allocated=8900;$boundaryErrors=[];
function cwru_id(int $id):string{return sprintf('%032x',$id);}
function cwru_ok(string $name,bool $passed):void{global $checks;if(in_array($name,array_column($checks,'name'),true))throw new LogicException('Duplicate named outcome.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function cwru_deny(string $name,callable $call):void{$refused=false;try{$call();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$refused=true;}cwru_ok($name,$refused);}
function cwru_scope(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function cwru_mutex():bool{$key='cwp-staff-'.substr(hash('sha256','cwp_test|cwru_'),0,40);return (int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$key])->fetchColumn()===1;}
function cwru_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
/** Same identity encoding used by the retained published-package comparator. */
function cwru_identity(string $name,array $row):string{
    $keys=array_column(all("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=? AND constraint_name='PRIMARY' ORDER BY ORDINAL_POSITION",['cwru_'.$name]),'COLUMN_NAME');
    if(!$keys)throw new RuntimeException('Missing primary identity.');$identity=[];foreach($keys as $key)$identity[$key]=$row[$key];
    return hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));
}
/** Exact original column and private-byte hashes; no plaintext account data is exported. */
function cwru_snapshot():array{
    $out=['version'=>CWP_VERSION,'tables'=>[],'private_files'=>[]];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ? ORDER BY TABLE_NAME',['cwru_']) as $entry){
        $name=substr($entry['TABLE_NAME'],5);if(!preg_match('/\A[a-z][a-z0-9_]*\z/',$name))throw new RuntimeException('Unexpected fixture table.');
        if($name==='rate_limits')continue;$rows=[];
        foreach(all('SELECT * FROM '.table($name)) as $row){$id=cwru_identity($name,$row);$columns=[];
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
function cwru_digest():string{return hash('sha256',json_encode(cwru_snapshot(),JSON_THROW_ON_ERROR));}
function cwru_owner():void{
    $u=one('SELECT * FROM '.table('users').' WHERE email=?',['cwr-upgrade@example.invalid']);
    if(!$u||!password_verify(CWRU_PASSWORD,$u['password_hash']))throw new RuntimeException('Wrong installed owner.');
    $_SESSION=['uid'=>(int)$u['id'],'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);
}
function cwru_client():int{
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE email=?',['cwr-upgrade-client@example.invalid']);
    if(!$c||!password_verify(CWRU_CLIENT_PASSWORD,$c['password_hash']))throw new RuntimeException('Wrong original client.');
    $_SESSION=['client_id'=>(int)$c['id'],'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);return (int)$c['id'];
}

function cwru_link():array{$p=one('SELECT id FROM '.table('provider_profiles').' WHERE label=?',[CWRU_LABEL]);$l=$p?one('SELECT * FROM '.table('provider_cloud_links').' WHERE profile_id=? AND server_id=1701',[$p['id']]):null;if(!$l)throw new RuntimeException('Original cloud assignment missing.');return $l;}
function cwru_wait(string $profile):void{
    if(db()->inTransaction()||cwru_mutex())throw new LogicException('Cooldown requires no mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future read.');$until=microtime(true)+32;
    while(time()<$at+30){if(microtime(true)>$until)throw new RuntimeException('Cooldown exceeded.');usleep(100000);}
}
function cwru_dns():string{global $dns;if(db()->inTransaction()||cwru_mutex())throw new LogicException('Cloud resolver requires no transaction or mutation mutex.');$dns++;return '8.8.8.8';}
function cwru_boundary(callable $authorize):void{global $boundaryErrors;$authorize();if(db()->inTransaction()||!cwru_mutex()){$boundaryErrors[]='transport_guard';throw new LogicException('Transport needs owning mutex without transaction.');}}
/** Types are declared only after the actual installed code is loaded. */
function cwru_transports():void{
    class CwruReadTransport implements HttpTransport{
        public function __construct(private $authorize,private string $url){}
        public function send(TransportRequest $r):TransportResponse{
            global $gets,$readBody,$boundaryErrors;cwru_boundary($this->authorize);
            if($r->method!=='GET'||$r->url!==$this->url||$r->body!==null||$r->headers!==['Authorization'=>'Bearer '.base64_encode(CWRU_SECRET),'Accept'=>'application/json']){$boundaryErrors[]='read_wire';throw new LogicException('Wrong synthetic read.');}
            $gets++;return new TransportResponse(200,[],$readBody);
        }
    }
    class CwruWriteTransport implements HttpTransport{
        public function __construct(private $authorize,private ProviderOperation $op){}
        public function send(TransportRequest $r):TransportResponse{
            global $posts,$wire,$writeMode,$allocated,$boundaryErrors;cwru_boundary($this->authorize);CloudWebsiteCreate::wire($r);$expected=CloudWebsiteCreate::request($this->op);
            $ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$this->op->idempotencyKey]);
            if($r->url!==$expected->url||$r->body!==$expected->body||($r->headers['Authorization']??null)!=='Bearer '.base64_encode(CWRU_SECRET)||!$ledger||$ledger['status']!=='claimed'){$boundaryErrors[]='write_wire_claim';throw new LogicException('Wrong claimed synthetic send.');}
            $posts++;$wire[]=['key'=>$this->op->idempotencyKey,'body'=>$r->body];
            if($writeMode==='unknown')return new TransportResponse(200,[],'true');
            if($writeMode!=='accepted')throw new LogicException('Unknown synthetic mode.');return new TransportResponse(200,[],json_encode(['result'=>$allocated],JSON_THROW_ON_ERROR));
        }
    }
}
function cwru_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation):InventorySnapshot{
    cwru_boundary($authorize);if($key!==CWRU_SECRET||$address!=='8.8.8.8'||!in_array($operation,[InventoryRead::CLOUD_SERVERS_LIST,InventoryRead::PACKAGES_LIST],true))throw new LogicException('Wrong synthetic account or read.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{cwru_boundary($authorize);return $key;});
    return InventoryReader::read(new CwruReadTransport($authorize,$operation===InventoryRead::CLOUD_SERVERS_LIST?'https://api.20i.com/cloud_server':'https://api.20i.com/package'),$credential,$operation,null,time());
}
function cwru_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $op):OperationResult{
    cwru_boundary($authorize);if($key!==CWRU_SECRET||$address!=='8.8.8.8'||$op->name!==CloudWebsiteCreate::OPERATION)throw new LogicException('Wrong synthetic send account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),static function()use($authorize,$key):string{cwru_boundary($authorize);return $key;});
    return(new ProviderGateway(new TwentyIOperationAdapter(),new CwruWriteTransport($authorize,$op),new ProviderOperationLedger()))->perform($credential,$op);
}
function cwru_claim(array $profile,string $key,string $address,callable $authorize,ProviderOperation $op):OperationResult{
    cwru_boundary($authorize);if($key!==CWRU_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong interrupted account.');
    $fingerprint=ProviderGateway::requestFingerprint('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),$op);
    if((new ProviderOperationLedger())->claim($op->idempotencyKey,$fingerprint,time())->state!=='claimed')throw new LogicException('Expected actual new durable claim.');
    throw new PDOException('Synthetic interruption after actual durable claim.');
}
function cwru_cloud():string{return json_encode([['id'=>1701,'name'=>'upgrade.cloud.synthetic.invalid','location'=>'dc_location-uk','packageIds'=>[]]],JSON_THROW_ON_ERROR);}
function cwru_package(int $id,string $domain):array{return ['id'=>$id,'name'=>$domain,'names'=>[$domain,'www.'.$domain],'created'=>'2026-01-01T00:00:00+00:00','enabled'=>false,'typeRef'=>42,'stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];}
function cwru_refresh(string $profile,string $op,string $body):array{global $readBody;cwru_owner();cwru_wait($profile);$readBody=$body;return provider_inventory_refresh_attempt($profile,1,CWRU_PASSWORD,'cwru_dns','cwru_read',$op);}
function cwru_event_state(int $id):array{return cwru_scope(function()use($id):array{$s=provider_cloud_site_requests_state();return $s['requests'][cwru_id($id)]??throw new RuntimeException('Retained request missing.');});}
function cwru_intake(int $id,string $name):array{
    $client=cwru_client();$link=cwru_link();$options=provider_cloud_site_requests_options($client);if(count($options)!==1||$options[0]['binding']['link_id']!==$link['id'])throw new RuntimeException('Expected exact current request option.');
    return provider_cloud_site_requests_create(cwru_id($id),$client,$link['id'],$options[0]['context'],$name,['www.'.$name],true);
}
function cwru_create_replay(int $id):array{
    $r=cwru_event_state($id);$client=cwru_client();$p=$r['request']['payload'];$actor=provider_cloud_site_requests_actor($client);
    return provider_cloud_site_requests_create(cwru_id($id),$client,$p['binding']['link_id'],provider_cloud_site_requests_context($p['binding'],$actor),$p['domain'],$p['aliases'],true);
}
function cwru_dispatch(int $id,?callable $perform=null):OperationResult{
    cwru_owner();$r=cwru_event_state($id);$actor=provider_cloud_site_requests_actor(null,CWRU_PASSWORD);
    $binding=$r['dispatch']!==null?$r['dispatch']['payload']['binding']:cwru_scope(fn()=>provider_cloud_site_requests_fresh($r));
    return provider_cloud_site_requests_dispatch(cwru_id($id),cwru_id($id),cwru_id($id+1),cwru_id($id+2),provider_cloud_site_requests_context($binding,$actor,$r),true,CWRU_PASSWORD,'cwru_dns',$perform??'cwru_perform');
}
function cwru_history():void{
    global $dns,$gets,$posts;$before=cwru_digest();$io=[$dns,$gets,$posts];cwru_owner();$link=cwru_link();
    $states=cwru_scope(fn()=>provider_cloud_site_requests_state());cwru_ok('All original request stages authenticate',count($states['requests'])===6);
    foreach([10=>'pending',20=>'cancelled',30=>'declined',40=>'dispatched',50=>'dispatched',60=>'dispatched'] as $id=>$stage){
        $r=$states['requests'][cwru_id($id)];cwru_ok('Original stage and actual intake actor retained '.$id,$r['stage']===$stage&&$r['request']['actor']['kind']==='client'&&$r['request']['client_id']===(int)$link['client_id']);
        $replay=cwru_create_replay($id);cwru_ok('Exact original intake replay preserves transitioned history '.$id,$replay===$r);
    }
    cwru_owner();$owner=provider_settings_owner(CWRU_PASSWORD);$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$link['client_id']]);
    foreach([40=>'accepted_pending',50=>'uncertain',60=>'uncertain'] as $id=>$status){
        $r=cwru_event_state($id);$g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[cwru_id($id+2)]);$ledger=provider_cloud_sites_result($g);$d=provider_cloud_sites_document($g);
        cwru_ok('Original request dispatch signed grant and shared ledger correspond '.$id,$r['dispatch']['actor']['id']===(int)$owner['id']&&$r['request']['actor']['auth_sha256']===hash('sha256',$client['auth_version'])&&$r['dispatch']['actor']['auth_sha256']===hash('sha256',$owner['auth_version'])&&$d['client_request']['request_id']===cwru_id($id)&&$g['consumed_key']==='cloudsite:'.cwru_id($id+2)&&$ledger!==null);
        $replay=cwru_dispatch($id);cwru_ok('Exact original owner dispatch replays without a newer observation '.$id,$replay->status===$status&&($id!==40||$replay->externalId==='8901'));
    }
    $claim=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['cloudsite:'.cwru_id(62)]);
    cwru_ok('Interrupted shared claim remains unresolved and never fabricated',$claim['status']==='claimed'&&(int)$claim['attempts']===0&&$claim['resolved_epoch']===null);
    $clientId=cwru_client();$history=provider_cloud_site_requests_history($clientId);$byId=array_column($history,null,'id');
    cwru_ok('Client sees exact confirmed request without health or internal grant claim',count($history)===6&&$byId[cwru_id(40)]['confirmation']['assigned']===true&&$byId[cwru_id(40)]['package_id']===null&&$byId[cwru_id(40)]['grant_id']===null&&$byId[cwru_id(50)]['status']==='uncertain'&&$byId[cwru_id(60)]['status']==='claimed');
    $o=provider_cloud_site_requests_options($clientId)[0];
    foreach([10,50,60] as $id){$p=$states['requests'][cwru_id($id)]['request']['payload'];cwru_deny('Fresh nonce cannot recreate pending unknown or interrupted domain '.$id,fn()=>provider_cloud_site_requests_create(cwru_id(100+$id),$clientId,$link['id'],$o['context'],$p['domain'],$p['aliases'],true));}
    cwru_owner();$legacy=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[cwru_id(2)]);$legacyDoc=provider_cloud_sites_document($legacy);
    cwru_ok('Original published owner approval remains original and confirmed',!array_key_exists('client_request',$legacyDoc)&&provider_cloud_confirmation_projection(cwru_id(2))['assigned']===true);
    $profile=provider_settings_load($link['profile_id']);$key=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));cwru_ok('Original private vault opens exact original provider credential',hash_equals(CWRU_SECRET,$key));unset($key);
    cwru_ok('History and replay preserve every original row and private byte with zero IO',$before===cwru_digest()&&$io===[$dns,$gets,$posts]);
}
function cwru_refuse():void{
    global $dns,$gets,$posts;$before=cwru_digest();cwru_owner();
    cwru_deny('Lost established head refuses authenticated request history',fn()=>provider_cloud_site_requests_history());
    cwru_deny('Explicit preparation never reconstructs missing established head',fn()=>provider_cloud_site_requests_prepare(CWRU_PASSWORD));
    cwru_deny('Exact replay cannot bypass damaged origin history',fn()=>cwru_dispatch(60));
    $g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[cwru_id(62)]);cwru_deny('Shared origin decoder refuses the missing established request head',fn()=>provider_cloud_sites_document($g));
    cwru_ok('Every corruption refusal keeps remaining rows and private keys unchanged',$before===cwru_digest()&&[$dns,$gets,$posts]===[0,0,0]);
}
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
try{
    if($mode==='preflight'){
        // The selected loopback DSN uses the assigned host port; server @@port may be an internal mapped port.
        $db=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4',(string)getenv('CWP_TEST_USER'),(string)getenv('CWP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $s=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ?');$s->execute(['cwru_']);
        cwru_ok('Dedicated installed namespace is empty',(int)$s->fetchColumn()===0);echo json_encode(['passed'=>true,'checks'=>$checks],JSON_THROW_ON_ERROR);exit;
    }
    if(!is_file($root.'/config.php')||is_link($root.'/config.php'))throw new RuntimeException('Missing installed configuration.');
    define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';$GLOBALS['cwp_config']=require $root.'/config.php';
    if(CWP_CODE_ROOT!==$root||config()['db']['name']!=='cwp_test'||config()['db']['prefix']!=='cwru_'||config()['db']['host']!=='127.0.0.1'||(int)config()['db']['port']!==(int)$port)throw new RuntimeException('Unexpected installed identity.');
    $storage=(string)config()['storage'];$private=realpath($storage);
    if(!$private||$private!==$storage||!str_starts_with($private,dirname($web).'/cajunwebpro-private-')||str_starts_with($private,$web.'/'))throw new RuntimeException('Unexpected installed private root.');
    for($path=$private;$path!==$repo;$path=dirname($path))if(is_link($path)||$path===dirname($path))throw new RuntimeException('Unexpected private ancestor.');
    // This branch intentionally loads no new feature code; it also runs after rollback to published files.
    if($mode==='snapshot'){echo json_encode(['passed'=>true,'checks'=>[],'snapshot'=>cwru_snapshot()],JSON_THROW_ON_ERROR);exit;}

    require_once CWP_CODE_ROOT.'/app/providers/cloud_sites.php';require_once CWP_CODE_ROOT.'/app/clients/accounts.php';
    if($mode!=='baseline-seed')require_once CWP_CODE_ROOT.'/app/providers/cloud_site_requests.php';cwru_transports();
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/subfolder/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$web;
    start_session();cwru_owner();provider_settings_owner(CWRU_PASSWORD);
    if($mode==='baseline-seed'){
        cwru_ok('Actual published source has no candidate request service',!function_exists('provider_cloud_site_requests_create')&&!is_file(CWP_CODE_ROOT.'/app/providers/cloud_site_requests.php'));
        if(!studio_ready())studio_migrate();provider_settings_migrate(CWRU_PASSWORD);provider_inventory_migrate(CWRU_PASSWORD);provider_operations_migrate(CWRU_PASSWORD);
        $client=studio_client_save(0,'','Cloud website upgrade client','cwr-upgrade-client@example.invalid',true);$c=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=?',[$client]);$url=studio_new_invite($client,$c['auth_version']);parse_str((string)parse_url($url,PHP_URL_QUERY),$invite);studio_activate($invite['invite'],CWRU_CLIENT_PASSWORD);
        $profile=provider_settings_save('',0,CWRU_LABEL,true,CWRU_SECRET,CWRU_PASSWORD);provider_cloud_assignment_prepare(CWRU_PASSWORD);provider_cloud_confirmation_prepare(CWRU_PASSWORD);
        $read=cwru_refresh($profile,InventoryRead::CLOUD_SERVERS_LIST,cwru_cloud());cwru_ok('Published actual cloud read completes',$read['state']==='complete');provider_cloud_link($profile,1701,$client,1,$read['id'],CWRU_PASSWORD);$link=cwru_link();
        $r=provider_cloud_sites_create(cwru_id(2),$link['id'],(int)$link['revision'],1,$read['id'],'published.cloud.example',['www.published.cloud.example'],true,CWRU_PASSWORD,'cwru_dns','cwru_perform');cwru_ok('Published owner approval uses actual synthetic gateway once',$r->status==='accepted_pending'&&$r->externalId==='8900'&&$posts===1);
        $read=cwru_refresh($profile,InventoryRead::PACKAGES_LIST,json_encode([cwru_package(8900,'published.cloud.example')],JSON_THROW_ON_ERROR));$review=provider_cloud_confirmation_review(cwru_id(2));provider_cloud_confirmation_confirm(cwru_id(2),$read['id'],$review['review'],true,CWRU_PASSWORD);cwru_ok('Published exact allocation already has independently observed assignment',provider_cloud_confirmation_projection(cwru_id(2))['assigned']===true);
        $f=fopen($private.'/cwru-retained-sentinel.bin','xb');if(!$f)throw new RuntimeException('Private sentinel already exists.');$bytes="Synthetic retained cloud media\x00\x01";if(fwrite($f,$bytes)!==strlen($bytes))throw new RuntimeException('Sentinel write failed.');fclose($f);chmod($private.'/cwru-retained-sentinel.bin',0600);
    }elseif($mode==='unprepared'){
        $before=cwru_digest();cwru_ok('Updated feature remains absent until owner explicitly prepares',!provider_cloud_site_requests_ready()&&provider_cloud_site_requests_options()===[]&&provider_cloud_site_requests_history()===[]&&$before===cwru_digest());
    }elseif($mode==='seed'){
        cwru_ok('Candidate request preparation is initially absent',!provider_cloud_site_requests_ready());provider_cloud_site_requests_prepare(CWRU_PASSWORD);$before=cwru_digest();provider_cloud_site_requests_prepare(CWRU_PASSWORD);cwru_ok('Owner explicit setup retains exact repeated preparation',provider_cloud_site_requests_ready()&&$before===cwru_digest());
        $link=cwru_link();$read=cwru_refresh($link['profile_id'],InventoryRead::CLOUD_SERVERS_LIST,cwru_cloud());cwru_ok('Candidate real current cloud source refresh completes',$read['state']==='complete');
        cwru_intake(10,'pending.cloud.example');cwru_intake(20,'cancelled.cloud.example');$client=cwru_client();provider_cloud_site_requests_change(cwru_id(20),cwru_id(20),cwru_id(21),'cancel','',$client);
        cwru_intake(30,'declined.cloud.example');cwru_owner();provider_cloud_site_requests_change(cwru_id(30),cwru_id(30),cwru_id(31),'decline','Please review the domain with the studio.',null,CWRU_PASSWORD);
        cwru_intake(40,'accepted.cloud.example');$allocated=8901;$r=cwru_dispatch(40);cwru_ok('Actual client intake and owner approval retain accepted package ID',$r->status==='accepted_pending'&&$r->externalId==='8901'&&$posts===1);
        cwru_intake(50,'uncertain.cloud.example');$writeMode='unknown';$r=cwru_dispatch(50);$writeMode='accepted';cwru_ok('Unverified synthetic reply retains exact uncertain send',$r->status==='uncertain'&&$posts===2);
        cwru_intake(60,'interrupted.cloud.example');$stopped=false;try{cwru_dispatch(60,'cwru_claim');}catch(PDOException){$stopped=true;}cwru_ok('Genuine shared claim survives injected local interruption with no third send',$stopped&&$posts===2&&cwru_count('provider_operations')===4);
        $read=cwru_refresh($link['profile_id'],InventoryRead::PACKAGES_LIST,json_encode([cwru_package(8900,'published.cloud.example'),cwru_package(8901,'accepted.cloud.example')],JSON_THROW_ON_ERROR));$review=provider_cloud_confirmation_review(cwru_id(42));provider_cloud_confirmation_confirm(cwru_id(42),$read['id'],$review['review'],true,CWRU_PASSWORD);
        cwru_ok('Candidate accepted allocation receives independent observation and actual original-client link',provider_cloud_confirmation_projection(cwru_id(42))['assigned']===true&&cwru_count('provider_cloud_confirmations')===2);
        // Advance the real cloud observation so exact replay demonstrably uses its retained origin.
        $read=cwru_refresh($link['profile_id'],InventoryRead::CLOUD_SERVERS_LIST,cwru_cloud());cwru_ok('Later genuine cloud observation is retained without rebasing prior requests',$read['state']==='complete');cwru_history();
    }elseif($mode==='verify')cwru_history();
    elseif($mode==='damage'){
        cwru_history();cwru_scope(function():void{cwru_ok('Deliberate loss removes exactly the established head',query('DELETE FROM '.table('settings').' WHERE name=?',['provider_cloud_site_request_head'])->rowCount()===1);});cwru_refuse();
    }else cwru_refuse();
    $expected=match($mode){'baseline-seed'=>[3,2,1],'seed'=>[6,3,2],default=>[0,0,0]};cwru_ok('Only explicit installed seeding performs synthetic IO',[$dns,$gets,$posts]===$expected&&$boundaryErrors===[]);
    echo json_encode(['passed'=>true,'checks'=>$checks,'synthetic_io'=>['dns'=>$dns,'gets'=>$gets,'posts'=>$posts]],JSON_THROW_ON_ERROR);
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR);exit(1);}
finally{restore_error_handler();}
