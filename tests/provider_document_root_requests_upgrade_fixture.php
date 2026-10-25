<?php
/** Installed-code companion. Synthetic provider transports only; never a deployed route. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==4)exit(2);
if(!function_exists('posix_geteuid')||posix_geteuid()===0)exit(2);
$port=getenv('CWP_TEST_PORT');if(!is_string($port)||!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
$repo=realpath(__DIR__.'/..');$root=realpath($argv[1]);$web=realpath($argv[3]);
$expected=$repo.'/test-runtime/provider-document-root-requests-upgrade/drr-upgrade/web';
if(!$repo||!$root||!$web||$web!==$expected||$root!==$web.'/subfolder'||$argv[1]!==$root||$argv[3]!==$web)exit(2);
for($path=$root;$path!==$repo;$path=dirname($path))if(is_link($path)||$path===dirname($path))exit(2);
$mode=$argv[2];if(!in_array($mode,['preflight','snapshot','seed','verify','recover-terminal','damage','refuse'],true))exit(2);

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIDocumentRoots\{DocumentRootsBinding,DocumentRootsObservation,DocumentRootsReader};
use CajunWebPro\Foundation\TwentyI\Operations\{DocumentRootChange,TwentyIOperationAdapter};

const DRRU_PASSWORD='Synthetic document root requests upgrade owner passphrase';
const DRRU_CLIENT_PASSWORD='Synthetic document root requests upgrade client passphrase';
const DRRU_SECRET='SYNTHETIC_DOCUMENT_ROOT_REQUESTS_UPGRADE_KEY';
const DRRU_LABEL='Document root requests upgrade fixture';
const DRRU_NAME='upgrade.synthetic.invalid';
const DRRU_OLD='public_html/original';
const DRRU_NEW='public_html/reviewed';
const DRRU_NEXT='public_html/second';
const DRRU_FIRST='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const DRRU_SECOND='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const DRRU_LEGACY='99999999999999999999999999999999';
const DRRU_LEGACY_PATH='public_html/direct-owner';
const DRRU_CANCEL='11111111111111111111111111111111';
const DRRU_DECLINE='22222222222222222222222222222222';
const DRRU_INTENT_FIRST='33333333333333333333333333333333';
const DRRU_INTENT_SECOND='44444444444444444444444444444444';
const DRRU_PENDING='55555555555555555555555555555555';
$checks=[];$dns=0;$gets=0;$posts=0;$wire=[];$mapPath=DRRU_OLD;$writeMode='unknown';
function drru_ok(string $name,bool $passed):void{global $checks;foreach($checks as $c)if($c['name']===$name)throw new LogicException('Duplicate fixture check.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function drru_deny(string $name,callable $call):void{
    $caught=false;try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=true;}drru_ok($name,$caught);
}
function drru_scope(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function drru_mutex():bool{$key='cwp-staff-'.substr(hash('sha256','cwp_test|drru_'),0,40);return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;}
function drru_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
/** Same identity encoding used by the retained published-package comparator. */
function drru_identity(string $name,array $row):string{
    $keys=array_column(all("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=? AND constraint_name='PRIMARY' ORDER BY ORDINAL_POSITION",['drru_'.$name]),'COLUMN_NAME');
    if(!$keys)throw new RuntimeException('Missing primary identity.');$identity=[];foreach($keys as $key)$identity[$key]=$row[$key];
    return hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));
}
/** Exact original column and private-byte hashes; no plaintext account data is exported. */
function drru_snapshot():array{
    $out=['version'=>CWP_VERSION,'tables'=>[],'private_files'=>[]];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ? ORDER BY TABLE_NAME',['drru_']) as $entry){
        $name=substr($entry['TABLE_NAME'],5);if(!preg_match('/\A[a-z][a-z0-9_]*\z/',$name))throw new RuntimeException('Unexpected fixture table.');
        if($name==='rate_limits')continue;$rows=[];
        foreach(all('SELECT * FROM '.table($name)) as $row){$id=drru_identity($name,$row);$columns=[];
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
function drru_digest():string{return hash('sha256',json_encode(drru_snapshot(),JSON_THROW_ON_ERROR));}
function drru_owner():void{
    $u=one('SELECT * FROM '.table('users').' WHERE email=?',['drr-upgrade@example.invalid']);
    if(!$u||!password_verify(DRRU_PASSWORD,$u['password_hash']))throw new RuntimeException('Wrong installed owner.');
    $_SESSION=['uid'=>(int)$u['id'],'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);
}
function drru_client():int{
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE email=?',['drr-upgrade-client@example.invalid']);
    if(!$c||!password_verify(DRRU_CLIENT_PASSWORD,$c['password_hash']))throw new RuntimeException('Wrong original client.');
    $_SESSION=['client_id'=>(int)$c['id'],'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);return (int)$c['id'];
}
function drru_link(int $package=701):array{
    $p=one('SELECT id FROM '.table('provider_profiles').' WHERE label=?',[DRRU_LABEL]);
    $l=$p?one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=?',[$p['id'],$package]):null;
    if(!$l)throw new RuntimeException('Missing original assignment.');return $l;
}
/** Genuine bounded cooldown outside every mutex/transaction; never rewrite dates or quotas. */
function drru_wait(string $profile):void{
    if(db()->inTransaction()||drru_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');$deadline=microtime(true)+32;
    while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
function drru_read_dns():string{global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';}
function drru_write_dns():string{
    global $dns;if(db()->inTransaction()||!drru_mutex())throw new LogicException('Write DNS requires owning mutex outside transaction.');
    $row=one('SELECT id FROM '.table('provider_document_root_changes').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Approval must precede DNS.');$request=provider_document_root_controls_request($row['id']);
    if($request['grant']['consumed_key']!=='documentroot:'.$row['id']||$request['ledger']!==null)throw new LogicException('Wrong pre-dispatch state.');
    $dns++;return '8.8.8.8';
}
function drru_boundary(callable $authorize):void{$authorize();if(db()->inTransaction()||!drru_mutex())throw new LogicException('Transport requires owning mutex outside transaction.');}
// Interfaces are loaded exclusively from the installed code before these transport classes are declared.
function drru_transports():void{
    if(class_exists('DrruReadTransport',false))throw new LogicException('Duplicate transport initialization.');
    class DrruReadTransport implements HttpTransport{
        public function __construct(private $authorize,private string $url,private string $body){}
        public function send(TransportRequest $r):TransportResponse{
            global $gets;drru_boundary($this->authorize);
            if($r->method!=='GET'||$r->url!==$this->url||$r->body!==null||$r->headers!==['Authorization'=>'Bearer '.base64_encode(DRRU_SECRET),'Accept'=>'application/json'])throw new LogicException('Wrong synthetic GET.');
            $gets++;return new TransportResponse(200,[],$this->body);
        }
    }
    class DrruWriteTransport implements HttpTransport{
        public function __construct(private $authorize){}
        public function send(TransportRequest $r):TransportResponse{
            global $posts,$wire,$writeMode;drru_boundary($this->authorize);DocumentRootChange::wire($r);
            if($r->url!=='https://api.20i.com/package/701/web/updateDocRoot'||($r->headers['Authorization']??null)!=='Bearer '.base64_encode(DRRU_SECRET))throw new LogicException('Wrong synthetic POST.');
            $posts++;$wire[]=$r->body;
            if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
            if($writeMode!=='unknown')throw new LogicException('Unknown fixture response.');return new TransportResponse(200,[],'true');
        }
    }
}
function drru_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?DocumentRootsBinding $documentRootsContext=null):InventorySnapshot|DocumentRootsObservation{
    global $mapPath;drru_boundary($authorize);if($key!==DRRU_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{drru_boundary($authorize);return $key;});
    if($operation===InventoryRead::PACKAGES_LIST){
        $packages=[];foreach([701=>DRRU_NAME,702=>'secondary.synthetic.invalid'] as $id=>$name)$packages[]=['id'=>$id,'name'=>$name,'names'=>[$name],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];$body=json_encode($packages,JSON_THROW_ON_ERROR);
        return InventoryReader::read(new DrruReadTransport($authorize,'https://api.20i.com/package',$body),$credential,$operation,null,time());
    }
    if($operation!==DocumentRootsBinding::OPERATION||!$documentRootsContext||!in_array($documentRootsContext->data['package']['package_id'],[701,702],true)||$documentRootsContext->data['package']['profile_id']!==$profile['id'])throw new LogicException('Wrong map context.');
    $id=$documentRootsContext->data['package']['package_id'];$map=$id===701?[DRRU_NAME=>$mapPath]:['secondary.synthetic.invalid'=>'public_html/secondary'];return DocumentRootsReader::read(new DrruReadTransport($authorize,'https://api.20i.com/package/'.$id.'/web/documentRoots',json_encode($map,JSON_THROW_ON_ERROR)),$credential,$documentRootsContext,time());
}
function drru_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    drru_boundary($authorize);if($key!==DRRU_SECRET||$address!=='8.8.8.8'||$operation->name!==DocumentRootChange::OPERATION)throw new LogicException('Wrong synthetic write account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),
        static function()use($authorize,$key):string{drru_boundary($authorize);return $key;});
    return(new ProviderGateway(new TwentyIOperationAdapter(),new DrruWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function drru_change(string $id,array $link,array $anchor,string $path):array{
    return provider_document_root_controls_change($id,$link['id'],$anchor['id'],$anchor['binding']->sha256,DRRU_NAME,$path,true,DRRU_PASSWORD,'drru_write_dns','drru_perform');
}
function drru_replay(string $id):array{
    $q=drru_scope(fn()=>provider_document_root_controls_request($id));$d=$q['document'];
    if($d['version']===1)return provider_document_root_controls_change($id,$q['row']['link_id'],$d['anchor_id'],$q['binding']->sha256,$d['params']['name'],$d['params']['path'],true,DRRU_PASSWORD,'drru_write_dns','drru_perform');
    $origin=$d['client_origin'];$state=drru_scope(fn()=>provider_document_root_requests_state());$intent=$state['requests'][$origin['id']];$event=$intent['events'][array_key_last($intent['events'])];
    return provider_document_root_requests_approve($origin['id'],$event['payload']['previous_event'],$origin['request_sha256'],$id,$d['anchor_id'],$q['binding']->sha256,true,DRRU_PASSWORD,'drru_write_dns','drru_perform');
}
function drru_intake(string $id,string $path,int $package=701):array{
    $client=drru_client();$link=drru_link($package);$actor=provider_document_root_requests_actor($client);$resource=drru_scope(fn()=>provider_document_root_requests_resource($link['id']));
    return provider_document_root_requests_create($id,$client,$link['id'],provider_document_root_requests_context($resource,$actor),$package===701?DRRU_NAME:'secondary.synthetic.invalid',$path,true);
}
function drru_approve(string $id,string $ownerId):array{
    drru_owner();$item=provider_document_root_requests_screen(null,1,$id)['items'][0];$a=$item['approval'];if(!$a)throw new RuntimeException('Missing actual fresh owner review.');
    return provider_document_root_requests_approve($id,$item['latest_event'],$item['request_sha256'],$ownerId,$a['anchor_id'],$a['binding_sha256'],true,DRRU_PASSWORD,'drru_write_dns','drru_perform');
}

function drru_ready():bool{return drru_scope(fn()=>provider_document_root_controls_ready());}
function drru_summary(string $id):array{return drru_scope(fn()=>provider_document_root_controls_summary($id));}
function drru_pending(array $link):?array{return drru_scope(function()use($link):?array{if(!provider_document_root_controls_ready())throw new RuntimeException('Original controls are unavailable.');return provider_document_root_controls_pending($link['profile_id'],701);});}
function drru_history(bool $recovered=false):void{
    global $dns,$gets,$posts;$before=drru_digest();$io=[$dns,$gets,$posts];$link=drru_link();
    drru_ok('Full installed owner and client graphs authenticate before replay',drru_ready()&&drru_scope(fn()=>provider_document_root_requests_ready()));
    $graph=drru_scope(fn()=>provider_document_root_requests_state());
    drru_ok('Five original client requests retain all local transition states',count($graph['requests'])===5&&$graph['requests'][DRRU_CANCEL]['state']==='cancelled'&&$graph['requests'][DRRU_DECLINE]['state']==='declined'&&$graph['requests'][DRRU_PENDING]['state']==='pending'&&$graph['requests'][DRRU_INTENT_FIRST]['state']==='dispatched'&&$graph['requests'][DRRU_INTENT_SECOND]['state']==='dispatched');
    drru_ok('Complete original client journal retains nine authenticated events',count(drru_scope(fn()=>provider_document_root_requests_events()))===9);
    $legacy=drru_summary(DRRU_LEGACY);$first=drru_summary(DRRU_FIRST);$second=drru_summary(DRRU_SECOND);
    drru_ok('Original version1 direct owner history remains independently observed',$legacy['status']==='observed'&&$legacy['last_check']['path']===DRRU_LEGACY_PATH&&drru_scope(fn()=>provider_document_root_controls_request(DRRU_LEGACY))['document']['version']===1);
    drru_ok('Client approved request retains independent exact observed path',$first['status']==='observed'&&$first['last_check']['disposition']==='observed'&&$first['last_check']['path']===DRRU_NEW);
    drru_ok('Second approval retains unresolved package fence and exact recovery state',$second['status']===($recovered?'uncertain':'interrupted')&&$second['last_check']===null&&$second['recovered_dispatch']===$recovered&&drru_pending($link)['id']===DRRU_SECOND);
    drru_ok('Exact original owner retry remains inert',drru_replay(DRRU_LEGACY)===$legacy);
    drru_ok('Exact client approval retries retain outcome without resend',drru_replay(DRRU_FIRST)['state']==='observed'&&drru_replay(DRRU_SECOND)['state']===($recovered?'unknown':'interrupted'));
    $q=drru_scope(fn()=>provider_document_root_controls_request(DRRU_SECOND));
    drru_deny('Fresh owner nonce cannot cross retained package fence',fn()=>provider_document_root_controls_change(str_repeat('c',32),$link['id'],$q['document']['anchor_id'],$q['binding']->sha256,DRRU_NAME,'public_html/third',true,DRRU_PASSWORD,'drru_write_dns','drru_perform'));
    foreach(['first'=>DRRU_FIRST,'second'=>DRRU_SECOND] as $label=>$id){$q=drru_scope(fn()=>provider_document_root_controls_request($id));$scope=['version'=>1,'request_id'=>$id,'request_sha256'=>hash('sha256',$q['row']['document_json'])];$origin=$q['document']['client_origin'];$request=$graph['requests'][$origin['id']];$dispatch=$request['events'][0];
        drru_ok('Original reciprocal client owner commitment and one-attempt ledger '.$label,$q['document']['version']===2&&$origin['request_sha256']===provider_document_root_requests_event_sha($request['request'])&&$dispatch['id']===$id&&$dispatch['payload']['owner_request_sha256']===$q['row']['document_sha256']
            &&$q['grant']['scope_params']===provider_document_roots_json($scope)&&strlen($q['grant']['scope_params'])<=4096&&$q['grant']['consumed_key']==='documentroot:'.$id&&$q['ledger']['status']==='uncertain'&&(int)$q['ledger']['attempts']===1);
    }
    $client=drru_client();$items=provider_document_root_requests_screen($client)['items'];$by=[];foreach($items as $item)$by[$item['id']]=$item;
    drru_ok('Original client sees pending cancelled declined observed and unresolved histories',count($by)===5&&$by[DRRU_CANCEL]['state']==='cancelled'&&$by[DRRU_DECLINE]['state']==='declined'&&$by[DRRU_PENDING]['can_cancel']===true&&$by[DRRU_INTENT_FIRST]['state']==='observed'&&$by[DRRU_INTENT_FIRST]['last_check']['disposition']==='observed'&&$by[DRRU_INTENT_SECOND]['state']===($recovered?'unknown':'interrupted'));
    drru_ok('Client request projection contains no owner dispatch or approval details',count(array_filter($items,static fn($item)=>$item['owner_request_id']!==null||$item['dispatch']!==null||$item['approval']!==null||$item['client']!==null))===0);
    drru_ok('Exact original pending intake replay stays pending',drru_intake(DRRU_PENDING,'public_html/pending',702)['state']==='pending');
    drru_deny('Changed original intake retry refuses',fn()=>drru_intake(DRRU_PENDING,'public_html/changed',702));
    $view=provider_document_roots_client($client,$link['id']);drru_ok('Original shared saved map remains historical while package is fenced',$view['status']==='stale'&&count($view['rows'])===1&&$view['rows'][0]['current']===false&&$view['rows'][0]['entries']===[['name'=>DRRU_NAME,'path'=>DRRU_NEW]]);drru_owner();
    $profile=provider_settings_load($link['profile_id']);$key=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));drru_ok('Original key still opens original encrypted credential',hash_equals(DRRU_SECRET,$key));unset($key);
    drru_ok('All history reads and exact retries retain every row private byte and zero provider effects',$before===drru_digest()&&$io===[$dns,$gets,$posts]);
}

function drru_refuse():void{
    global $dns,$gets,$posts;$before=drru_digest();$link=drru_link();
    drru_deny('Paired established client dispatch and audit loss refuses readiness',fn()=>drru_ready());
    drru_deny('Explicit client preparation cannot repair lost dispatch history',fn()=>provider_document_root_requests_prepare(DRRU_PASSWORD));
    drru_deny('Owner controls cannot hide surviving reciprocal client corruption',fn()=>provider_document_root_controls_prepare(DRRU_PASSWORD));
    drru_deny('Authenticated pending inspection refuses damaged client origin',fn()=>drru_pending($link));
    drru_deny('Exact retry cannot reconstruct or resend damaged client origin',fn()=>drru_replay(DRRU_SECOND));
    drru_deny('Local recovery cannot repair unrelated lost client history',fn()=>provider_document_root_requests_recover(DRRU_INTENT_SECOND,DRRU_SECOND,str_repeat('0',64),DRRU_PASSWORD));
    $client=drru_client();drru_deny('Original client screen refuses lost authenticated dispatch history',fn()=>provider_document_root_requests_screen($client));drru_owner();
    drru_ok('All corruption refusals preserve remaining rows keys and zero transport',$before===drru_digest()&&[$dns,$gets,$posts]===[0,0,0]);
}

set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
try{
    if($mode==='preflight'){
        // The selected loopback DSN uses the assigned host port; server @@port may be an internal mapped port.
        $db=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4',(string)getenv('CWP_TEST_USER'),(string)getenv('CWP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $s=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ?');$s->execute(['drru_']);
        drru_ok('Dedicated installed namespace is empty',(int)$s->fetchColumn()===0);echo json_encode(['passed'=>true,'checks'=>$checks],JSON_THROW_ON_ERROR);exit;
    }
    if(!is_file($root.'/config.php')||is_link($root.'/config.php'))throw new RuntimeException('Missing installed configuration.');
    define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';$GLOBALS['cwp_config']=require $root.'/config.php';
    if(CWP_CODE_ROOT!==$root||config()['db']['name']!=='cwp_test'||config()['db']['prefix']!=='drru_'||config()['db']['host']!=='127.0.0.1'||(int)config()['db']['port']!==(int)$port)throw new RuntimeException('Unexpected installed identity.');
    $storage=(string)config()['storage'];$private=realpath($storage);
    if(!$private||$private!==$storage||!str_starts_with($private,dirname($web).'/cajunwebpro-private-')||str_starts_with($private,$web.'/'))throw new RuntimeException('Unexpected installed private root.');
    for($path=$private;$path!==$repo;$path=dirname($path))if(is_link($path)||$path===dirname($path))throw new RuntimeException('Unexpected private ancestor.');
    // This branch intentionally loads no new feature code; it also runs after rollback to published files.
    if($mode==='snapshot'){echo json_encode(['passed'=>true,'checks'=>[],'snapshot'=>drru_snapshot()],JSON_THROW_ON_ERROR);exit;}
    require CWP_CODE_ROOT.'/app/providers/document_root_requests.php';require_once CWP_CODE_ROOT.'/app/clients/accounts.php';drru_transports();
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/subfolder/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$web;
    start_session();drru_owner();provider_settings_owner(DRRU_PASSWORD);
    if($mode==='seed'){
        provider_settings_migrate(DRRU_PASSWORD);provider_inventory_migrate(DRRU_PASSWORD);provider_operations_migrate(DRRU_PASSWORD);if(!studio_ready())studio_migrate();provider_document_roots_prepare(DRRU_PASSWORD);
        drru_ok('Installed target leaves change storage explicitly unprepared',!drru_ready());provider_document_root_controls_prepare(DRRU_PASSWORD);$before=drru_digest();provider_document_root_controls_prepare(DRRU_PASSWORD);
        drru_ok('Explicit change setup is ready and idempotent',drru_ready()&&$before===drru_digest());
        provider_document_root_requests_prepare(DRRU_PASSWORD);$ready=drru_digest();provider_document_root_requests_prepare(DRRU_PASSWORD);drru_ok('Explicit installed client request setup is ready and idempotent',drru_scope(fn()=>provider_document_root_requests_ready())&&$ready===drru_digest());
        $profile=provider_settings_save('',0,DRRU_LABEL,true,DRRU_SECRET,DRRU_PASSWORD);
        $client=studio_client_save(0,'','Website folder request upgrade client','drr-upgrade-client@example.invalid',true);$clientRow=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=?',[$client]);
        $url=studio_new_invite($client,$clientRow['auth_version']);parse_str((string)parse_url($url,PHP_URL_QUERY),$invite);studio_activate($invite['invite'],DRRU_CLIENT_PASSWORD);
        $source=provider_inventory_refresh_attempt($profile,1,DRRU_PASSWORD,'drru_read_dns','drru_read');drru_ok('Installed real package reader completes',$source['state']==='complete');
        provider_inventory_link($profile,701,$client,1,DRRU_PASSWORD);provider_inventory_link($profile,702,$client,1,DRRU_PASSWORD);drru_wait($profile);
        $source=provider_inventory_refresh_attempt($profile,1,DRRU_PASSWORD,'drru_read_dns','drru_read');drru_ok('Fresh package source follows both real assignments',$source['state']==='complete');$link=drru_link();
        foreach([701=>'d',702=>'e'] as $package=>$letter){drru_wait($profile);$l=drru_link($package);$binding=drru_scope(fn()=>provider_document_roots_binding($l['id']));$read=provider_document_roots_refresh($l['id'],$binding,DRRU_PASSWORD,'drru_read_dns','drru_read');drru_ok('Complete installed map supports explicit sharing package '.$package,$read['state']==='complete');provider_document_roots_share($l['id'],'',str_repeat($letter,32),true,$read['id'],DRRU_PASSWORD);}
        $anchor=provider_document_roots_observation(provider_document_roots_latest($link['id']));$legacy=drru_change(DRRU_LEGACY,$link,$anchor,DRRU_LEGACY_PATH);drru_ok('Original v1 owner workflow creates a separate uncertain request',$legacy['status']==='uncertain'&&$posts===1);
        $mapPath=DRRU_LEGACY_PATH;drru_wait($profile);$legacy=provider_document_root_controls_verify(DRRU_LEGACY,DRRU_PASSWORD,'drru_read_dns','drru_read');drru_ok('Independent GET observes original v1 request',$legacy['status']==='observed'&&$legacy['last_check']['path']===DRRU_LEGACY_PATH);
        $cancel=drru_intake(DRRU_CANCEL,'public_html/cancel');$cancel=provider_document_root_requests_change(DRRU_CANCEL,$cancel['latest_event'],str_repeat('6',32),'cancel','',$client);drru_ok('Actual primary client withdraws pending request',$cancel['state']==='cancelled');
        $decline=drru_intake(DRRU_DECLINE,'public_html/decline');drru_owner();$decline=provider_document_root_requests_change(DRRU_DECLINE,$decline['latest_event'],str_repeat('7',32),'decline','Please confirm an existing supported folder.',null,DRRU_PASSWORD);drru_ok('Actual owner declines original client request',$decline['state']==='declined');
        drru_intake(DRRU_PENDING,'public_html/pending',702);drru_intake(DRRU_INTENT_FIRST,DRRU_NEW);$first=drru_approve(DRRU_INTENT_FIRST,DRRU_FIRST);drru_ok('Atomic reciprocal client approval sends once and records uncertainty',$first['state']==='unknown'&&$posts===2);
        $mapPath=DRRU_NEW;drru_wait($profile);$observed=provider_document_root_requests_verify(DRRU_INTENT_FIRST,DRRU_FIRST,DRRU_PASSWORD,'drru_read_dns','drru_read');drru_ok('Separate exact map GET observes approved client path',$observed['state']==='observed'&&$observed['last_check']['disposition']==='observed'&&drru_pending($link)===null&&$posts===2);
        drru_intake(DRRU_INTENT_SECOND,DRRU_NEXT);drru_owner();$writeMode='timeout';
        db()->exec('CREATE TRIGGER drru_terminal_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.document_root_control_terminal' THEN SET @drru_hits=COALESCE(@drru_hits,0)+1; SET @drru_rows=(SELECT COUNT(*) FROM ".table('provider_document_root_dispatch_ends')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic installed terminal audit fault'; END IF; END");query('SET @drru_hits=0,@drru_rows=0');$fault=false;
        try{drru_approve(DRRU_INTENT_SECOND,DRRU_SECOND);}catch(PDOException $e){$fault=$e->getCode()==='45000'&&(int)query('SELECT @drru_hits')->fetchColumn()===1&&(int)query('SELECT @drru_rows')->fetchColumn()===3;}finally{db()->exec('DROP TRIGGER drru_terminal_fault');}
        drru_ok('Real terminal audit failure rolls back only completion after third send',$fault&&$posts===3&&drru_count('provider_document_root_dispatch_ends')===2&&drru_count('provider_operations')===3&&drru_count('provider_website_folder_requests_events')===9);
        drru_ok('All actual gateway sends contain exact separately approved one-host update',$wire===[json_encode(['update'=>[DRRU_NAME=>DRRU_LEGACY_PATH]],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),json_encode(['update'=>[DRRU_NAME=>DRRU_NEW]],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),json_encode(['update'=>[DRRU_NAME=>DRRU_NEXT]],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
        drru_history();
    }elseif($mode==='verify')drru_history();
    elseif($mode==='recover-terminal'){
        $ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.DRRU_SECOND]);$summary=drru_summary(DRRU_SECOND);$before=drru_snapshot();
        drru_ok('Original finished ledger offers exact local recovery',is_string($summary['recovery_ledger_sha256'])&&strlen($summary['recovery_ledger_sha256'])===64&&$summary['can_verify']===false);
        drru_deny('Wrong reviewed ledger hash refuses local recovery',fn()=>provider_document_root_requests_recover(DRRU_INTENT_SECOND,DRRU_SECOND,str_repeat('0',64),DRRU_PASSWORD));
        $result=provider_document_root_requests_recover(DRRU_INTENT_SECOND,DRRU_SECOND,$summary['recovery_ledger_sha256'],DRRU_PASSWORD);
        drru_ok('Recovery adds only local dispatch evidence and keeps original ledger',$result['state']==='unknown'&&$result['dispatch']['recovered_dispatch']===true&&$ledger===one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.DRRU_SECOND]));
        $terminal=one('SELECT * FROM '.table('provider_document_root_dispatch_ends').' WHERE id=?',[DRRU_SECOND]);
        $detail='Document-root change terminal '.DRRU_SECOND.' '.provider_document_roots_digest(provider_document_root_controls_values('terminal',$terminal));
        $audit=one('SELECT * FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.document_root_control_terminal',$detail]);$counter=one('SELECT * FROM '.table('settings').' WHERE name=?',['provider_document_root_control_terminal_count']);
        if(!$audit||!$counter)throw new RuntimeException('Missing exact recovery witnesses.');
        $recoveryTransition=['terminal_row_id'=>drru_identity('provider_document_root_dispatch_ends',$terminal),'audit_row_id'=>drru_identity('audit',$audit),'counter_row_id'=>drru_identity('settings',$counter)];
        $after=drru_snapshot();$expected=$after;$oldRows=(array)$before['tables']['settings']['rows'];$newRows=(array)$expected['tables']['settings']['rows'];$id=$recoveryTransition['counter_row_id'];
        drru_ok('Recovery counter is exactly two to three',$oldRows[$id]['value']===hash('sha256','"2"')&&$newRows[$id]['value']===hash('sha256','"3"'));
        $newRows[$id]['value']=$oldRows[$id]['value'];$expected['tables']['settings']['rows']=(object)$newRows;
        foreach(['provider_document_root_dispatch_ends'=>'terminal_row_id','audit'=>'audit_row_id'] as $table=>$field){$rows=(array)$expected['tables'][$table]['rows'];$id=$recoveryTransition[$field];
            if(isset(((array)$before['tables'][$table]['rows'])[$id])||!isset($rows[$id]))throw new RuntimeException('Recovery append identity mismatch.');unset($rows[$id]);$expected['tables'][$table]['rows']=(object)$rows;$expected['tables'][$table]['count']--;
        }
        drru_ok('Only exact terminal audit and counter transition changes the full snapshot',json_encode($expected,JSON_THROW_ON_ERROR)===json_encode($before,JSON_THROW_ON_ERROR));
        drru_deny('Recovery cannot replace an established terminal',fn()=>provider_document_root_requests_recover(DRRU_INTENT_SECOND,DRRU_SECOND,$summary['recovery_ledger_sha256'],DRRU_PASSWORD));drru_history(true);
    }elseif($mode==='damage'){
        drru_history(true);drru_scope(function():void{
            $event=one('SELECT * FROM '.table('provider_website_folder_requests_events').' WHERE id=?',[DRRU_FIRST]);if(!$event||$event['kind']!=='dispatch')throw new RuntimeException('Expected original client dispatch.');
            $detail='Website folder request event '.$event['id'].' '.$event['fingerprint'];
            drru_ok('Deliberate paired loss removes exact original client dispatch',query('DELETE FROM '.table('provider_website_folder_requests_events').' WHERE id=?',[$event['id']])->rowCount()===1);
            drru_ok('Deliberate paired loss removes exact client dispatch audit',query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.website_folder_requests_event',$detail])->rowCount()===1);
        });drru_refuse();
    }else drru_refuse();
    drru_ok('Only explicit seed phase invokes synthetic DNS GET or POST',$mode==='seed'?($dns===9&&$gets===6&&$posts===3):[$dns,$gets,$posts]===[0,0,0]);
    $out=['passed'=>true,'checks'=>$checks,'synthetic_io'=>['dns'=>$dns,'gets'=>$gets,'posts'=>$posts]];if(isset($recoveryTransition))$out['recovery_transition']=$recoveryTransition;
    echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR);exit(1);}
finally{restore_error_handler();}
