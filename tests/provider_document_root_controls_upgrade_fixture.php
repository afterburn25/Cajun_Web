<?php
/** Installed-code companion. Synthetic provider transports only; never a deployed route. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==4)exit(2);
if(function_exists('posix_geteuid')&&posix_geteuid()===0)exit(2);
$port=getenv('CWP_TEST_PORT');if(!is_string($port)||!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
$repo=realpath(__DIR__.'/..');$root=realpath($argv[1]);$web=realpath($argv[3]);
$expected=$repo.'/test-runtime/provider-document-root-controls-upgrade/drc-upgrade/web';
if(!$repo||!$root||!$web||$web!==$expected||$root!==$web.'/subfolder'||$argv[1]!==$root||$argv[3]!==$web)exit(2);
for($path=$root;$path!==$repo;$path=dirname($path))if(is_link($path)||$path===dirname($path))exit(2);
$mode=$argv[2];if(!in_array($mode,['preflight','snapshot','seed','verify','recover-terminal','damage','refuse'],true))exit(2);

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIDocumentRoots\{DocumentRootsBinding,DocumentRootsObservation,DocumentRootsReader};
use CajunWebPro\Foundation\TwentyI\Operations\{DocumentRootChange,TwentyIOperationAdapter};

const DRCU_PASSWORD='Synthetic document root controls upgrade owner passphrase';
const DRCU_CLIENT_PASSWORD='Synthetic document root controls upgrade client passphrase';
const DRCU_SECRET='SYNTHETIC_DOCUMENT_ROOT_CONTROLS_UPGRADE_KEY';
const DRCU_LABEL='Document root controls upgrade fixture';
const DRCU_NAME='upgrade.synthetic.invalid';
const DRCU_OLD='public_html/original';
const DRCU_NEW='public_html/reviewed';
const DRCU_NEXT='public_html/second';
const DRCU_FIRST='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const DRCU_SECOND='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$checks=[];$dns=0;$gets=0;$posts=0;$wire=[];$mapPath=DRCU_OLD;$writeMode='unknown';
function drcu_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function drcu_deny(string $name,callable $call):void{
    $caught=false;try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=true;}drcu_ok($name,$caught);
}
function drcu_scope(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function drcu_mutex():bool{$key='cwp-staff-'.substr(hash('sha256','cwp_test|drcu_'),0,40);return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;}
function drcu_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
/** Same identity encoding used by the retained published-package comparator. */
function drcu_identity(string $name,array $row):string{
    $keys=array_column(all("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=? AND constraint_name='PRIMARY' ORDER BY ORDINAL_POSITION",['drcu_'.$name]),'COLUMN_NAME');
    if(!$keys)throw new RuntimeException('Missing primary identity.');$identity=[];foreach($keys as $key)$identity[$key]=$row[$key];
    return hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));
}
/** Exact original column and private-byte hashes; no plaintext account data is exported. */
function drcu_snapshot():array{
    $out=['version'=>CWP_VERSION,'tables'=>[],'private_files'=>[]];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ? ORDER BY TABLE_NAME',['drcu_']) as $entry){
        $name=substr($entry['TABLE_NAME'],5);if(!preg_match('/\A[a-z][a-z0-9_]*\z/',$name))throw new RuntimeException('Unexpected fixture table.');
        if($name==='rate_limits')continue;$rows=[];
        foreach(all('SELECT * FROM '.table($name)) as $row){$id=drcu_identity($name,$row);$columns=[];
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
function drcu_digest():string{return hash('sha256',json_encode(drcu_snapshot(),JSON_THROW_ON_ERROR));}
function drcu_owner():void{
    $u=one('SELECT * FROM '.table('users').' WHERE email=?',['drc-upgrade@example.invalid']);
    if(!$u||!password_verify(DRCU_PASSWORD,$u['password_hash']))throw new RuntimeException('Wrong installed owner.');
    $_SESSION=['uid'=>(int)$u['id'],'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);
}
function drcu_client():int{
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE email=?',['drc-upgrade-client@example.invalid']);
    if(!$c||!password_verify(DRCU_CLIENT_PASSWORD,$c['password_hash']))throw new RuntimeException('Wrong original client.');
    $_SESSION=['client_id'=>(int)$c['id'],'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);return (int)$c['id'];
}
function drcu_link():array{
    $p=one('SELECT id FROM '.table('provider_profiles').' WHERE label=?',[DRCU_LABEL]);
    $l=$p?one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=701',[$p['id']]):null;
    if(!$l)throw new RuntimeException('Missing original assignment.');return $l;
}
/** Genuine bounded cooldown outside every mutex/transaction; never rewrite dates or quotas. */
function drcu_wait(string $profile):void{
    if(db()->inTransaction()||drcu_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture intent.');$deadline=microtime(true)+32;
    while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
function drcu_read_dns():string{global $dns;if(db()->inTransaction())throw new LogicException('Read DNS inside transaction.');$dns++;return '8.8.8.8';}
function drcu_write_dns():string{
    global $dns;if(db()->inTransaction()||!drcu_mutex())throw new LogicException('Write DNS requires owning mutex outside transaction.');
    $row=one('SELECT id FROM '.table('provider_document_root_changes').' ORDER BY grant_seq DESC LIMIT 1');
    if(!$row)throw new LogicException('Approval must precede DNS.');$request=provider_document_root_controls_request($row['id']);
    if($request['grant']['consumed_key']!=='documentroot:'.$row['id']||$request['ledger']!==null)throw new LogicException('Wrong pre-dispatch state.');
    $dns++;return '8.8.8.8';
}
function drcu_boundary(callable $authorize):void{$authorize();if(db()->inTransaction()||!drcu_mutex())throw new LogicException('Transport requires owning mutex outside transaction.');}
// Interfaces are loaded exclusively from the installed code before these transport classes are declared.
function drcu_transports():void{
    if(class_exists('DrcuReadTransport',false))throw new LogicException('Duplicate transport initialization.');
    class DrcuReadTransport implements HttpTransport{
        public function __construct(private $authorize,private string $url,private string $body){}
        public function send(TransportRequest $r):TransportResponse{
            global $gets;drcu_boundary($this->authorize);
            if($r->method!=='GET'||$r->url!==$this->url||$r->body!==null||$r->headers!==['Authorization'=>'Bearer '.base64_encode(DRCU_SECRET),'Accept'=>'application/json'])throw new LogicException('Wrong synthetic GET.');
            $gets++;return new TransportResponse(200,[],$this->body);
        }
    }
    class DrcuWriteTransport implements HttpTransport{
        public function __construct(private $authorize){}
        public function send(TransportRequest $r):TransportResponse{
            global $posts,$wire,$writeMode;drcu_boundary($this->authorize);DocumentRootChange::wire($r);
            if($r->url!=='https://api.20i.com/package/701/web/updateDocRoot'||($r->headers['Authorization']??null)!=='Bearer '.base64_encode(DRCU_SECRET))throw new LogicException('Wrong synthetic POST.');
            $posts++;$wire[]=$r->body;
            if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
            if($writeMode!=='unknown')throw new LogicException('Unknown fixture response.');return new TransportResponse(200,[],'true');
        }
    }
}
function drcu_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation,?DocumentRootsBinding $documentRootsContext=null):InventorySnapshot|DocumentRootsObservation{
    global $mapPath;drcu_boundary($authorize);if($key!==DRCU_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic read account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$key):string{drcu_boundary($authorize);return $key;});
    if($operation===InventoryRead::PACKAGES_LIST){
        $body=json_encode([['id'=>701,'name'=>DRCU_NAME,'names'=>[DRCU_NAME],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null]],JSON_THROW_ON_ERROR);
        return InventoryReader::read(new DrcuReadTransport($authorize,'https://api.20i.com/package',$body),$credential,$operation,null,time());
    }
    if($operation!==DocumentRootsBinding::OPERATION||!$documentRootsContext||$documentRootsContext->data['package']['package_id']!==701||$documentRootsContext->data['package']['profile_id']!==$profile['id'])throw new LogicException('Wrong map context.');
    return DocumentRootsReader::read(new DrcuReadTransport($authorize,'https://api.20i.com/package/701/web/documentRoots',json_encode([DRCU_NAME=>$mapPath],JSON_THROW_ON_ERROR)),$credential,$documentRootsContext,time());
}
function drcu_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    drcu_boundary($authorize);if($key!==DRCU_SECRET||$address!=='8.8.8.8'||$operation->name!==DocumentRootChange::OPERATION)throw new LogicException('Wrong synthetic write account.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),
        static function()use($authorize,$key):string{drcu_boundary($authorize);return $key;});
    return(new ProviderGateway(new TwentyIOperationAdapter(),new DrcuWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function drcu_change(string $id,array $link,array $anchor,string $path):array{
    return provider_document_root_controls_change($id,$link['id'],$anchor['id'],$anchor['binding']->sha256,DRCU_NAME,$path,true,DRCU_PASSWORD,'drcu_write_dns','drcu_perform');
}
function drcu_replay(string $id):array{
    $q=drcu_scope(fn()=>provider_document_root_controls_request($id));$d=$q['document'];
    return provider_document_root_controls_change($id,$q['row']['link_id'],$d['anchor_id'],$q['binding']->sha256,$d['params']['name'],$d['params']['path'],true,DRCU_PASSWORD,'drcu_write_dns','drcu_perform');
}
function drcu_ready():bool{return drcu_scope(fn()=>provider_document_root_controls_ready());}
function drcu_summary(string $id):array{return drcu_scope(fn()=>provider_document_root_controls_summary($id));}
function drcu_pending(array $link):?array{return drcu_scope(function()use($link):?array{if(!provider_document_root_controls_ready())throw new RuntimeException('Original controls are unavailable.');return provider_document_root_controls_pending($link['profile_id'],701);});}
function drcu_history(bool $recovered=false):void{
    global $dns,$gets,$posts;$before=drcu_digest();$io=[$dns,$gets,$posts];$link=drcu_link();
    drcu_ok('Full installed history authenticates before replay',drcu_ready());$first=drcu_summary(DRCU_FIRST);$second=drcu_summary(DRCU_SECOND);
    drcu_ok('First request retains its independent exact observed path',$first['status']==='observed'&&$first['last_check']['disposition']==='observed'&&$first['last_check']['path']===DRCU_NEW);
    drcu_ok('Second original approval retains its unresolved package fence',$second['status']===($recovered?'uncertain':'interrupted')&&$second['last_check']===null&&$second['recovered_dispatch']===$recovered&&drcu_pending($link)['id']===DRCU_SECOND);
    drcu_ok('Exact resolved and interrupted retries never send',drcu_replay(DRCU_FIRST)===$first&&drcu_replay(DRCU_SECOND)===$second);
    $q=drcu_scope(fn()=>provider_document_root_controls_request(DRCU_SECOND));
    drcu_deny('Fresh nonce cannot cross retained package fence',fn()=>provider_document_root_controls_change(str_repeat('c',32),$link['id'],$q['document']['anchor_id'],$q['binding']->sha256,DRCU_NAME,'public_html/third',true,DRCU_PASSWORD,'drcu_write_dns','drcu_perform'));
    $owner=one('SELECT * FROM '.table('users').' WHERE email=?',['drc-upgrade@example.invalid']);
    foreach(['first'=>DRCU_FIRST,'second'=>DRCU_SECOND] as $label=>$id){$q=drcu_scope(fn()=>provider_document_root_controls_request($id));$scope=['version'=>1,'request_id'=>$id,'request_sha256'=>hash('sha256',$q['row']['document_json'])];
        drcu_ok('Original owner compact grant and one-attempt ledger correspond '.$label,(int)$q['row']['actor_id']===(int)$owner['id']&&$q['document']['actor_auth']===hash('sha256',$owner['auth_version'])
            &&$q['grant']['scope_params']===provider_document_roots_json($scope)&&strlen($q['grant']['scope_params'])<=4096&&$q['grant']['consumed_key']==='documentroot:'.$id
            &&$q['ledger']['status']==='uncertain'&&(int)$q['ledger']['attempts']===1&&$q['ledger']['operation']===DocumentRootChange::OPERATION);
    }
    $client=drcu_client();$view=provider_document_roots_client($client,$link['id']);
    drcu_ok('Original client sharing remains historical while package is fenced',$view['status']==='stale'&&count($view['rows'])===1&&$view['rows'][0]['current']===false&&$view['rows'][0]['entries']===[['name'=>DRCU_NAME,'path'=>DRCU_NEW]]);drcu_owner();
    $profile=provider_settings_load($link['profile_id']);$key=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
    drcu_ok('Original vault key still opens the original account credential',hash_equals(DRCU_SECRET,$key));unset($key);
    drcu_ok('History replay and client projection preserve every original row and private byte',$before===drcu_digest()&&$io===[$dns,$gets,$posts]);
}
function drcu_refuse():void{
    global $dns,$gets,$posts;$before=drcu_digest();$link=drcu_link();
    drcu_deny('Paired established check and audit loss refuses readiness',fn()=>drcu_ready());
    drcu_deny('Explicit setup cannot recreate established missing history',fn()=>provider_document_root_controls_prepare(DRCU_PASSWORD));
    drcu_deny('Authenticated pending inspection refuses damaged history',fn()=>drcu_pending($link));
    drcu_deny('Exact retry cannot reconstruct or resend damaged history',fn()=>drcu_replay(DRCU_SECOND));
    drcu_deny('Local terminal recovery cannot repair unrelated lost history',fn()=>provider_document_root_controls_recover_finished(DRCU_SECOND,str_repeat('0',64),DRCU_PASSWORD));
    drcu_ok('All corruption refusals preserve remaining rows keys and zero transport',$before===drcu_digest()&&[$dns,$gets,$posts]===[0,0,0]);
}

set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
try{
    if($mode==='preflight'){
        // The selected loopback DSN uses the assigned host port; server @@port may be an internal mapped port.
        $db=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4',(string)getenv('CWP_TEST_USER'),(string)getenv('CWP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $s=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ?');$s->execute(['drcu_']);
        drcu_ok('Dedicated installed namespace is empty',(int)$s->fetchColumn()===0);echo json_encode(['passed'=>true,'checks'=>$checks],JSON_THROW_ON_ERROR);exit;
    }
    if(!is_file($root.'/config.php')||is_link($root.'/config.php'))throw new RuntimeException('Missing installed configuration.');
    define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';$GLOBALS['cwp_config']=require $root.'/config.php';
    if(CWP_CODE_ROOT!==$root||config()['db']['name']!=='cwp_test'||config()['db']['prefix']!=='drcu_'||config()['db']['host']!=='127.0.0.1'||(int)config()['db']['port']!==(int)$port)throw new RuntimeException('Unexpected installed identity.');
    $storage=(string)config()['storage'];$private=realpath($storage);
    if(!$private||$private!==$storage||!str_starts_with($private,dirname($web).'/cajunwebpro-private-')||str_starts_with($private,$web.'/'))throw new RuntimeException('Unexpected installed private root.');
    for($path=$private;$path!==$repo;$path=dirname($path))if(is_link($path)||$path===dirname($path))throw new RuntimeException('Unexpected private ancestor.');
    // This branch intentionally loads no new feature code; it also runs after rollback to published files.
    if($mode==='snapshot'){echo json_encode(['passed'=>true,'checks'=>[],'snapshot'=>drcu_snapshot()],JSON_THROW_ON_ERROR);exit;}
    require CWP_CODE_ROOT.'/app/providers/document_root_controls.php';require_once CWP_CODE_ROOT.'/app/clients/accounts.php';drcu_transports();
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/subfolder/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$web;
    start_session();drcu_owner();provider_settings_owner(DRCU_PASSWORD);
    if($mode==='seed'){
        provider_settings_migrate(DRCU_PASSWORD);provider_inventory_migrate(DRCU_PASSWORD);provider_operations_migrate(DRCU_PASSWORD);if(!studio_ready())studio_migrate();provider_document_roots_prepare(DRCU_PASSWORD);
        drcu_ok('Installed target leaves change storage explicitly unprepared',!drcu_ready());provider_document_root_controls_prepare(DRCU_PASSWORD);$before=drcu_digest();provider_document_root_controls_prepare(DRCU_PASSWORD);
        drcu_ok('Explicit change setup is ready and idempotent',drcu_ready()&&$before===drcu_digest());
        $profile=provider_settings_save('',0,DRCU_LABEL,true,DRCU_SECRET,DRCU_PASSWORD);
        $client=studio_client_save(0,'','Document root controls upgrade client','drc-upgrade-client@example.invalid',true);$clientRow=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=?',[$client]);
        $url=studio_new_invite($client,$clientRow['auth_version']);parse_str((string)parse_url($url,PHP_URL_QUERY),$invite);studio_activate($invite['invite'],DRCU_CLIENT_PASSWORD);
        $source=provider_inventory_refresh_attempt($profile,1,DRCU_PASSWORD,'drcu_read_dns','drcu_read');drcu_ok('Installed real package reader completes',$source['state']==='complete');
        provider_inventory_link($profile,701,$client,1,DRCU_PASSWORD);drcu_wait($profile);
        $source=provider_inventory_refresh_attempt($profile,1,DRCU_PASSWORD,'drcu_read_dns','drcu_read');drcu_ok('Fresh package source follows actual assignment',$source['state']==='complete');$link=drcu_link();
        drcu_wait($profile);$binding=drcu_scope(fn()=>provider_document_roots_binding($link['id']));$read=provider_document_roots_refresh($link['id'],$binding,DRCU_PASSWORD,'drcu_read_dns','drcu_read');
        drcu_ok('Complete installed map is the exact reviewed anchor',$read['state']==='complete');$anchor=provider_document_roots_observation(provider_document_roots_source($read['id']));
        provider_document_roots_share($link['id'],'',str_repeat('d',32),true,$anchor['id'],DRCU_PASSWORD);
        drcu_ok('Saved client sharing is separately enabled before changes',drcu_scope(fn()=>provider_document_roots_project($link['id'],true))['status']==='observed');
        $first=drcu_change(DRCU_FIRST,$link,$anchor,DRCU_NEW);drcu_ok('First actual owner approval sends once and records uncertainty',$first['status']==='uncertain'&&$posts===1);
        $mapPath=DRCU_NEW;drcu_wait($profile);$observed=provider_document_root_controls_verify(DRCU_FIRST,DRCU_PASSWORD,'drcu_read_dns','drcu_read');
        drcu_ok('Separate exact map GET resolves the first approval',$observed['status']==='observed'&&$observed['last_check']['path']===DRCU_NEW&&drcu_pending($link)===null&&$posts===1);
        $anchor=provider_document_roots_observation(provider_document_roots_latest($link['id']));$writeMode='timeout';
        db()->exec('CREATE TRIGGER drcu_terminal_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.document_root_control_terminal' THEN SET @drcu_hits=COALESCE(@drcu_hits,0)+1; SET @drcu_rows=(SELECT COUNT(*) FROM ".table('provider_document_root_dispatch_ends')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic installed terminal audit fault'; END IF; END");
        query('SET @drcu_hits=0,@drcu_rows=0');$fault=false;
        try{drcu_change(DRCU_SECOND,$link,$anchor,DRCU_NEXT);}catch(PDOException $e){$fault=$e->getCode()==='45000'&&(int)query('SELECT @drcu_hits')->fetchColumn()===1&&(int)query('SELECT @drcu_rows')->fetchColumn()===2;}finally{db()->exec('DROP TRIGGER drcu_terminal_fault');}
        drcu_ok('Real terminal audit failure rolls back inserted witness after second send',$fault&&$posts===2&&drcu_count('provider_document_root_dispatch_ends')===1&&drcu_count('provider_operations')===2);
        drcu_ok('Both actual gateway sends contain only the approved one-host update',$wire===[json_encode(['update'=>[DRCU_NAME=>DRCU_NEW]],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),json_encode(['update'=>[DRCU_NAME=>DRCU_NEXT]],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
        drcu_history();
    }elseif($mode==='verify')drcu_history();
    elseif($mode==='recover-terminal'){
        $ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.DRCU_SECOND]);$summary=drcu_summary(DRCU_SECOND);$before=drcu_snapshot();
        drcu_ok('Original finished ledger offers exact local recovery',is_string($summary['recovery_ledger_sha256'])&&strlen($summary['recovery_ledger_sha256'])===64&&$summary['can_verify']===false);
        drcu_deny('Wrong reviewed ledger hash refuses local recovery',fn()=>provider_document_root_controls_recover_finished(DRCU_SECOND,str_repeat('0',64),DRCU_PASSWORD));
        $result=provider_document_root_controls_recover_finished(DRCU_SECOND,$summary['recovery_ledger_sha256'],DRCU_PASSWORD);
        drcu_ok('Recovery adds only local dispatch evidence and keeps original ledger',$result['status']==='uncertain'&&$result['recovered_dispatch']===true&&$ledger===one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['documentroot:'.DRCU_SECOND]));
        $terminal=one('SELECT * FROM '.table('provider_document_root_dispatch_ends').' WHERE id=?',[DRCU_SECOND]);
        $detail='Document-root change terminal '.DRCU_SECOND.' '.provider_document_roots_digest(provider_document_root_controls_values('terminal',$terminal));
        $audit=one('SELECT * FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.document_root_control_terminal',$detail]);$counter=one('SELECT * FROM '.table('settings').' WHERE name=?',['provider_document_root_control_terminal_count']);
        if(!$audit||!$counter)throw new RuntimeException('Missing exact recovery witnesses.');
        $recoveryTransition=['terminal_row_id'=>drcu_identity('provider_document_root_dispatch_ends',$terminal),'audit_row_id'=>drcu_identity('audit',$audit),'counter_row_id'=>drcu_identity('settings',$counter)];
        $after=drcu_snapshot();$expected=$after;$oldRows=(array)$before['tables']['settings']['rows'];$newRows=(array)$expected['tables']['settings']['rows'];$id=$recoveryTransition['counter_row_id'];
        drcu_ok('Recovery counter is exactly one to two',$oldRows[$id]['value']===hash('sha256','"1"')&&$newRows[$id]['value']===hash('sha256','"2"'));
        $newRows[$id]['value']=$oldRows[$id]['value'];$expected['tables']['settings']['rows']=(object)$newRows;
        foreach(['provider_document_root_dispatch_ends'=>'terminal_row_id','audit'=>'audit_row_id'] as $table=>$field){$rows=(array)$expected['tables'][$table]['rows'];$id=$recoveryTransition[$field];
            if(isset(((array)$before['tables'][$table]['rows'])[$id])||!isset($rows[$id]))throw new RuntimeException('Recovery append identity mismatch.');unset($rows[$id]);$expected['tables'][$table]['rows']=(object)$rows;$expected['tables'][$table]['count']--;
        }
        drcu_ok('Only exact terminal audit and counter transition changes the full snapshot',json_encode($expected,JSON_THROW_ON_ERROR)===json_encode($before,JSON_THROW_ON_ERROR));
        drcu_deny('Recovery cannot replace an established terminal',fn()=>provider_document_root_controls_recover_finished(DRCU_SECOND,$summary['recovery_ledger_sha256'],DRCU_PASSWORD));drcu_history(true);
    }elseif($mode==='damage'){
        drcu_history(true);drcu_scope(function():void{
            $check=one('SELECT * FROM '.table('provider_document_root_change_checks').' WHERE request_id=? AND disposition=?',[DRCU_FIRST,'observed']);if(!$check)throw new RuntimeException('Expected established check.');
            $detail='Document-root change check '.$check['id'].' '.provider_document_roots_digest(provider_document_root_controls_values('check',$check));
            drcu_ok('Deliberate paired loss removes exact original check',query('DELETE FROM '.table('provider_document_root_change_checks').' WHERE id=?',[$check['id']])->rowCount()===1);
            drcu_ok('Deliberate paired loss removes its exact independent audit',query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.document_root_control_check',$detail])->rowCount()===1);
        });drcu_refuse();
    }else drcu_refuse();
    drcu_ok('Only explicit seed phase invokes synthetic DNS GET or POST',$mode==='seed'?($dns===6&&$gets===4&&$posts===2):[$dns,$gets,$posts]===[0,0,0]);
    $out=['passed'=>true,'checks'=>$checks,'synthetic_io'=>['dns'=>$dns,'gets'=>$gets,'posts'=>$posts]];if(isset($recoveryTransition))$out['recovery_transition']=$recoveryTransition;
    echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR);exit(1);}
finally{restore_error_handler();}
