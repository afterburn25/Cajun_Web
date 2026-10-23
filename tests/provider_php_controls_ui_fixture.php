<?php
/** CLI-only disposable packaged-UI bridge; no provider sockets or production overrides. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1'||count($argv)!==2)exit(2);
$phase=$argv[1];
if(!in_array($phase,['seed','status','cool','options','contact-add','contact-clear','fault-arm','fault-clear','inventory','gate'],true))exit(2);
$portText=getenv('CWP_TEST_PORT')?:'33306';if(!ctype_digit($portText)||(int)$portText<1||(int)$portText>65535)exit(2);
$raw=stream_get_contents(STDIN,4097);if(strlen($raw)>4096)exit(2);
try{$decoded=json_decode($raw,false,8,JSON_THROW_ON_ERROR);}catch(JsonException){exit(2);}
if(!$decoded instanceof stdClass)exit(2);$input=get_object_vars($decoded);unset($decoded,$raw);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
const PHUI_OWNER_PASSWORD='Synthetic PHP UI owner passphrase';
const PHUI_CLIENT_PASSWORD='Synthetic PHP UI client passphrase';
const PHUI_SECRET='SYNTHETIC_PHP_UI_KEY';
const PHUI_PREFIX='phcui_';
$checkout=realpath(__DIR__.'/..');if(!$checkout||$checkout!==dirname(__DIR__))exit(2);
$runtime=$checkout.'/test-runtime/php-controls-ui';$application=$runtime.'/web/php-controls-ui';$private=$runtime.'/private';$sessions=$runtime.'/sessions';$statePath=$runtime.'/state.json';
function phui_path(string $path,bool $exists=true):void {
    global $checkout;
    if(!str_starts_with($path,$checkout.'/'))throw new RuntimeException('Fixture path escaped checkout.');
    for($part=$path;$part!==$checkout;$part=dirname($part))if(is_link($part))throw new RuntimeException('Fixture path contains a link.');
    if($exists&&realpath($path)!==$path)throw new RuntimeException('Fixture path is not the exact existing path.');
}
function phui_keys(array $expected):void {
    global $input;$actual=array_keys($input);sort($actual,SORT_STRING);sort($expected,SORT_STRING);
    if($actual!==$expected)throw new InvalidArgumentException('Unexpected fixture phase fields.');
}
function phui_create(string $path,string $body,int $mode=0600):void {
    phui_path($path,false);if(file_exists($path)||is_link($path))throw new RuntimeException('Fixture output already exists.');
    $f=fopen($path,'xb');if($f===false)throw new RuntimeException('Cannot create exclusive fixture output.');
    try{if(fwrite($f,$body)!==strlen($body)||!fflush($f)||!chmod($path,$mode))throw new RuntimeException('Incomplete fixture output.');}finally{fclose($f);}
}
function phui_json_file(string $path,int $limit=8192):array {
    phui_path($path);if(!is_file($path)||filesize($path)>$limit)throw new RuntimeException('Invalid private fixture file.');
    $d=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);if(!is_array($d))throw new RuntimeException('Invalid fixture document.');return $d;
}
try {
    foreach([$runtime,$runtime.'/web',$application,$application.'/includes/bootstrap.php'] as $path)phui_path($path);
    if($phase==='seed') {
        phui_keys([]);
        foreach([$private,$sessions,$statePath,$application.'/config.php'] as $path){phui_path($path,false);if(file_exists($path)||is_link($path))throw new RuntimeException('Prior PHP UI fixture exists.');}
        foreach([$private,$sessions] as $path)if(!mkdir($path,0700)||!chmod($path,0700))throw new RuntimeException('Could not create protected fixture directory.');
        $config=['site_url'=>'http://127.0.0.1:8095/php-controls-ui','storage'=>$private,'key'=>bin2hex(random_bytes(32)),
            'db'=>['host'=>'127.0.0.1','port'=>(int)$portText,'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>PHUI_PREFIX]];
        phui_create($application.'/config.php',"<?php\nreturn ".var_export($config,true).";\n");
    }
    foreach([$private,$sessions,$application.'/config.php'] as $path)phui_path($path);
    if((fileperms($private)&0777)!==0700||(fileperms($sessions)&0777)!==0700||(fileperms($application.'/config.php')&0777)!==0600)throw new RuntimeException('Fixture private permissions changed.');
    $config=require $application.'/config.php';
    if(!is_array($config)||$config['site_url']!=='http://127.0.0.1:8095/php-controls-ui'||$config['storage']!==$private||$config['db']['host']!=='127.0.0.1'||$config['db']['port']!==(int)$portText||$config['db']['name']!=='cwp_test'||$config['db']['prefix']!==PHUI_PREFIX)throw new RuntimeException('Fixture application configuration changed.');
    define('CWP_BOOT',true);require $application.'/includes/bootstrap.php';require CWP_CODE_ROOT.'/database/schema/base.php';
    require_once CWP_CODE_ROOT.'/app/providers/php_controls_changes.php';require_once CWP_CODE_ROOT.'/app/providers/php_controls_projection.php';require_once CWP_CODE_ROOT.'/app/support/sessions.php';
    if(CWP_ROOT!==$application||CWP_CODE_ROOT!==$application)throw new RuntimeException('Fixture code root changed.');
    $GLOBALS['cwp_config']=$config;
    $_SERVER=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8095','DOCUMENT_ROOT'=>$runtime.'/web','SCRIPT_NAME'=>'/php-controls-ui/portal/index.php','REQUEST_METHOD'=>'GET'];$_GET=[];$_POST=[];
    ini_set('session.save_handler','files');ini_set('session.save_path',$sessions);
    if(query('SELECT DATABASE()')->fetchColumn()!=='cwp_test')throw new RuntimeException('Wrong disposable database.');
} catch(Throwable $e) {echo json_encode(['passed'=>false,'phase'=>$phase,'failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()]],JSON_THROW_ON_ERROR)."\n";exit(1);}

use CajunWebPro\Foundation\MailTransport\EndpointPolicy;
use CajunWebPro\Foundation\Provider\{TransportResponse,ProviderOperation};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyI\Operations\PhpVersionChange;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventoryProjector};
use CajunWebPro\Foundation\TwentyIPackagePhp\{PackagePhpVersion,PackagePhpVersionObservation,PackagePhpVersionBinding,PackagePhpOptionSet};

function phui_scope(callable $fn):mixed{return staff_locked(fn()=>staff_transaction($fn));}
function phui_probe(string $name,callable $fn,array &$gates):void {
    try{$gates[$name]=['ok'=>$fn()];}
    catch(Throwable $e){$gates[$name]=['ok'=>false,'error'=>get_class($e),'message'=>substr($e->getMessage(),0,160)];}
}
function phui_exists(string $name):bool{return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[PHUI_PREFIX.$name])!==null;}
function phui_owner():void {
    $u=one('SELECT * FROM '.table('users').' WHERE id=1');
    $_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function phui_state():array {
    global $statePath,$application;$s=phui_json_file($statePath);
    if(array_keys($s)!==['format','profile_id','link_id','other_link_id','config_sha256','bootstrap_sha256']||$s['format']!==1||!hash_equals($s['config_sha256'],hash_file('sha256',$application.'/config.php'))||!hash_equals($s['bootstrap_sha256'],hash_file('sha256',$application.'/includes/bootstrap.php')))throw new RuntimeException('Fixture input identity changed.');
    foreach(['profile_id','link_id','other_link_id'] as $key)provider_php_controls_lifecycle_id($s[$key]);return $s;
}
function phui_inventory(string $profile):void {
    phui_scope(function()use($profile):void {
        $now=time();$r=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$profile,'profile_revision'=>1,'actor_id'=>1,'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now,'operation'=>InventoryRead::PACKAGES_LIST];
        query('INSERT INTO '.table('provider_refreshes').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch,operation) VALUES (?,?,?,?,?,?,?)',array_values($r));
        provider_settings_audit(1,'refresh_started','Refresh '.$r['id'].' profile '.$profile.' revision 1');
        $items=[];foreach([701,702] as $id)$items[]=['id'=>$id,'name'=>'php-ui-'.$id.'.synthetic.invalid','names'=>['php-ui-'.$id.'.synthetic.invalid'],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];
        $snapshot=InventoryProjector::packagesList(new TransportResponse(200,[],json_encode($items,JSON_THROW_ON_ERROR)),$now,'twenty-i|'.$profile.'|packages.list');
        if($snapshot->state!=='complete'||count($snapshot->packages)!==2)throw new LogicException('Fixture package projection refused.');
        provider_inventory_record_transaction($r,$snapshot->state,$snapshot->reason,$snapshot->scope,'unknown',2,$snapshot->skipped,json_encode($snapshot->skipReasons),0,$snapshot,provider_inventory_fingerprint(PHUI_SECRET));
    });
}
function phui_next_read(string $profile):int {
    $latest=0;foreach(['provider_refreshes','provider_checks','provider_php_control_reads'] as $name)if(phui_exists($name))$latest=max($latest,(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table($name).' WHERE profile_id=?',[$profile])->fetchColumn());
    if($latest<0||$latest>time())throw new RuntimeException('Unexpected future fixture read.');return $latest===0?0:$latest+30;
}
function phui_cool(string $profile):int {
    if(db()->inTransaction())throw new LogicException('Cooldown inherited a transaction.');
    $lock='cwp-staff-'.substr(hash('sha256','cwp_test|'.PHUI_PREFIX),0,40);
    if((int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$lock])->fetchColumn()===1)throw new LogicException('Cooldown holds mutation mutex.');
    $next=phui_next_read($profile);$start=microtime(true);$deadline=$start+32;
    while(time()<$next){if(microtime(true)>$deadline)throw new RuntimeException('Fixture cooldown exceeded its bound.');usleep(100000);}return (int)ceil((microtime(true)-$start)*1000);
}
function phui_legacy_transport(array $profile,string $secret,string $address,callable $authorize,string $operation,?PackagePhpVersionBinding $packageContext=null):PackagePhpVersionObservation {
    $authorize();if(db()->inTransaction()||$secret!==PHUI_SECRET||$address!=='8.8.8.8'||$operation!==PackagePhpVersion::OPERATION||$packageContext===null||$packageContext->data['profile_id']!==$profile['id']||$packageContext->data['package_id']!==701)throw new LogicException('Wrong synthetic legacy read.');
    return PackagePhpVersion::project(new TransportResponse(200,[],'"php82"'),$packageContext,time());
}
function phui_session(string $id):void {
    global $sessions;
    if(preg_match('/\A[A-Za-z0-9,-]{24,128}\z/',$id)!==1)throw new InvalidArgumentException('Invalid fixture session selector.');
    $file=$sessions.'/sess_'.$id;phui_path($file);if(!is_file($file)||filesize($file)>65536)throw new RuntimeException('Missing existing browser session.');
    session_id($id);start_session();if(!hash_equals($id,session_id()))throw new RuntimeException('Browser session was not retained.');
}
function phui_protected_digest():string {
    $rows=[];$prefix=PHUI_PREFIX.'provider_';
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen($prefix),$prefix]) as $r){
        $name=$r['TABLE_NAME'];if(preg_match('/\Aphcui_provider_[a-z0-9_]+\z/',$name)!==1)throw new RuntimeException('Unexpected fixture table.');
        $values=all('SELECT * FROM `'.$name.'` LIMIT 5001');if(count($values)>5000)throw new RuntimeException('Fixture rows exceeded bound.');usort($values,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$values;
    }
    $rows['settings']=all('SELECT name,value FROM '.table('settings').' WHERE BINARY LEFT(name,9)=BINARY ? ORDER BY name',['provider_']);
    $rows['audit']=all('SELECT * FROM '.table('audit').' WHERE BINARY LEFT(action,9)=BINARY ? ORDER BY id',['provider.']);
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
function phui_status(array $state,array $ids):array {
    if(!array_is_list($ids)||count($ids)>8)throw new InvalidArgumentException('Invalid request selectors.');foreach($ids as $id){if(!is_string($id))throw new InvalidArgumentException('Invalid request selector.');provider_php_controls_lifecycle_id($id);}if(count(array_unique($ids,SORT_STRING))!==count($ids))throw new InvalidArgumentException('Repeated request selector.');
    return phui_scope(function()use($state,$ids):array {
        unset($GLOBALS['cwp_settings']);$delegation=provider_php_controls_ready();$lifecycle=provider_php_controls_lifecycle_ready();
        $tables=provider_php_controls_lifecycle_tables()+['ledger'=>'provider_operations','delegation'=>'provider_php_control_delegations'];$counts=[];
        foreach($tables as $kind=>$name)$counts[$kind]=phui_exists($name)?(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn():0;
        $latest=['current'=>null,'available'=>null];$requests=[];
        if($lifecycle)foreach(array_keys($latest) as $kind){$saved=provider_php_controls_lifecycle_latest($state['link_id'],$kind);if($saved!==null){$actor=$saved['read']['document']['context']['actor'];$latest[$kind]=['id'=>$saved['read']['document']['id'],'state'=>$saved['result']['document']['state']??'pending','actor_kind'=>$actor['kind'],'actor_id'=>$actor['id']];}}
        foreach($ids as $id){$record=$lifecycle?provider_php_controls_lifecycle_get('request',$id):null;if($record===null){$requests[$id]=null;continue;}
            $terminal=provider_php_controls_lifecycle_get('terminal',$id);$ledger=provider_php_controls_lifecycle_ledger_snapshot($id);$actor=$record['document']['review']['context']['actor'];$recorder=$terminal['document']['recorded_by']??null;
            $requests[$id]=provider_php_controls_summary_locked($id)+['actor_kind'=>$actor['kind'],'actor_id'=>$actor['id'],'terminal_actor_kind'=>$recorder['kind']??null,'terminal_actor_id'=>$recorder['id']??null,'ledger_id'=>$ledger['id']??null,'ledger_status'=>$ledger['status']??null,'ledger_attempts'=>$ledger['attempts']??null];
        }
        try{provider_php_binding($state['link_id']);$probe='ok';}catch(Throwable $e){$probe=get_class($e).':'.$e->getMessage();}
        $pk=one('SELECT id,started_epoch FROM '.table('provider_refreshes').' f WHERE f.profile_id=? AND (f.operation IS NULL OR BINARY f.operation=?) ORDER BY f.seq DESC LIMIT 1',[$state['profile_id'],InventoryRead::PACKAGES_LIST]);
        return ['passed'=>true,'phase'=>'status','protected_sha256'=>phui_protected_digest(),'counts'=>$counts,'markers'=>['delegation'=>one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_php_control_delegation_schema_version'])['value']??'','lifecycle'=>one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_php_control_lifecycle_schema_version'])['value']??''],'ready'=>['delegation'=>$delegation,'lifecycle'=>$lifecycle],'latest'=>$latest,'requests'=>(object)$requests,'next_read_epoch'=>phui_next_read($state['profile_id']),'fault_hits'=>phui_exists('fixture_fault_hits')?(int)query('SELECT COUNT(*) FROM '.table('fixture_fault_hits'))->fetchColumn():0,'binding_probe'=>$probe,'inventory_age'=>$pk===null?null:time()-(int)$pk['started_epoch']];
    });
}
function phui_options_transport(array $profile,string $secret,string $address,callable $authorize,PackagePhpVersionBinding $binding,string $kind):PackagePhpOptionSet {
    $current=$authorize();$lock='cwp-staff-'.substr(hash('sha256','cwp_test|'.PHUI_PREFIX),0,40);
    if(db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$lock])->fetchColumn()!==1||$secret!==PHUI_SECRET||$address!=='8.8.8.8'||$current['id']!==$profile['id']||$binding->data['profile_id']!==$profile['id']||!in_array($binding->data['package_id'],[701,702],true)||$kind!=='available')throw new LogicException('Unexpected typed synthetic option boundary.');
    return PackagePhpOptionSet::fromNormalizedRows($GLOBALS['phui_option_mode']==='empty'?[]:[['value'=>'php82','label'=>'Synthetic PHP 8.2','handler'=>'Synthetic handler'],['value'=>'php84','label'=>'Synthetic PHP 8.4','handler'=>'Synthetic handler']]);
}

try {
    if($phase==='seed') {
        if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? LIMIT 1',[strlen(PHUI_PREFIX),PHUI_PREFIX]))throw new RuntimeException('Prior PHP UI namespace exists.');
        foreach(schema_statements(PHUI_PREFIX) as $sql)db()->exec($sql);
        foreach(default_settings() as $name=>$value)save_setting($name,$value);save_setting('studio_schema_version','2');
        foreach([1,2] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['PHP UI '.($id===1?'owner':'staff'),'php-ui-'.($id===1?'owner':'staff').'@example.invalid',password_hash(PHUI_OWNER_PASSWORD,PASSWORD_DEFAULT),str_repeat($id===1?'a':'b',32),gmdate('Y-m-d H:i:s')]);
        staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,'[]')");
        foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['PHP UI client '.$id,'php-ui-client'.$id.'@example.invalid',password_hash(PHUI_CLIENT_PASSWORD,PASSWORD_DEFAULT),str_repeat($id===1?'c':'d',32),gmdate('Y-m-d H:i:s')]);
        phui_owner();provider_settings_migrate(PHUI_OWNER_PASSWORD);provider_inventory_migrate(PHUI_OWNER_PASSWORD);provider_php_prepare(PHUI_OWNER_PASSWORD);provider_operations_migrate(PHUI_OWNER_PASSWORD);support_migrate(PHUI_OWNER_PASSWORD);
        $profile=provider_settings_save('',0,'Synthetic PHP UI',true,PHUI_SECRET,PHUI_OWNER_PASSWORD);
        if((fileperms($private.'/provider-master.key')&0777)!==0600)throw new RuntimeException('Actual provider vault permissions failed.');
        phui_inventory($profile);provider_inventory_link($profile,701,1,1,PHUI_OWNER_PASSWORD);provider_inventory_link($profile,702,2,1,PHUI_OWNER_PASSWORD);phui_inventory($profile);
        $link=one('SELECT id FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=701',[$profile])['id'];$other=one('SELECT id FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=702',[$profile])['id'];
        phui_cool($profile);$binding=phui_scope(fn()=>provider_php_binding($link));$read=provider_php_refresh($link,$binding,PHUI_OWNER_PASSWORD,static fn():string=>'8.8.8.8','phui_legacy_transport');
        if($read['state']!=='complete'){$result=one('SELECT state,reason FROM '.table('provider_refresh_results').' WHERE id=?',[$read['id']])??[];throw new RuntimeException('Legacy synthetic observation ended '.$read['state'].' '.($result['reason']??''));}provider_php_share($link,'',bin2hex(random_bytes(16)),true,$read['id'],PHUI_OWNER_PASSWORD);
        $state=['format'=>1,'profile_id'=>$profile,'link_id'=>$link,'other_link_id'=>$other,'config_sha256'=>hash_file('sha256',$application.'/config.php'),'bootstrap_sha256'=>hash_file('sha256',$application.'/includes/bootstrap.php')];phui_create($statePath,json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
        $status=phui_status($state,[]);if($status['ready']['delegation']||$status['ready']['lifecycle'])throw new LogicException('Seed silently enabled PHP controls.');
        $output=['passed'=>true,'phase'=>'seed','profile_id'=>$profile,'link_id'=>$link,'other_link_id'=>$other,'package_id'=>701,'other_package_id'=>702,'protected_sha256'=>$status['protected_sha256'],'counts'=>$status['counts']];
    } else {
        $state=phui_state();
        if($phase==='status'){phui_keys(['request_ids']);$output=phui_status($state,$input['request_ids']);}
        elseif($phase==='gate'){
            // Post-failure probe: replays each pre-dispatch predicate of the send span in
            // php_controls_changes.php against current state. Endpoint replay uses the exact
            // synthetic DNS answer the packaged-server connector returns under cli-server;
            // its own SAPI guard is reported separately via 'sapi'.
            phui_keys(['request_id']);if(!is_string($input['request_id']))throw new InvalidArgumentException('Invalid request selector.');provider_php_controls_lifecycle_id($input['request_id']);phui_owner();
            $gates=['now'=>time(),'sapi'=>PHP_SAPI];
            $record=phui_scope(fn()=>provider_php_controls_lifecycle_get('request',$input['request_id']));
            $expected=$record['document']??null;$gates['request_present']=$expected!==null;
            if($expected!==null){
                $gates['expires_epoch']=$expected['expires_epoch'];$gates['expired']=$expected['expires_epoch']<=time();
                phui_probe('terminal_exists',fn()=>phui_scope(fn()=>provider_php_controls_lifecycle_get('terminal',$expected['id'])!==null),$gates);
                $reauthorize=function()use($expected){
                    $d=$expected['review']['context'];
                    return provider_php_controls_reauthorize(['document'=>$d,'sha256'=>provider_php_controls_lifecycle_sha($d),'binding'=>new PackagePhpVersionBinding($d['binding'])]);
                };
                phui_probe('reauthorize',function()use($reauthorize){phui_scope($reauthorize);return true;},$gates);
                phui_probe('review_match',function()use($expected,$reauthorize){
                    $context=phui_scope($reauthorize);
                    $r=phui_scope(fn()=>provider_php_controls_change_review($context,$expected['review']['current_read_id'],$expected['review']['options_read_id'],$expected['review']['selected']['value']));
                    return $r['review']===$expected['review']?'equal':'differs';
                },$gates);
                phui_probe('pending',function()use($expected,$reauthorize){
                    $b=phui_scope($reauthorize)['binding']->data;
                    $p=phui_scope(fn()=>provider_php_controls_lifecycle_pending($b['profile_id'],$b['package_id']));
                    return $p===null?'none':(($p['request']['document']['id']??null)===$expected['id']?'ours':'other');
                },$gates);
                $connect=function()use($expected){
                    $d=$expected['review']['context'];
                    return staff_locked(fn()=>provider_php_controls_connection(['document'=>$d,'sha256'=>provider_php_controls_lifecycle_sha($d),'binding'=>new PackagePhpVersionBinding($d['binding'])]));
                };
                phui_probe('connection',function()use($connect){$connect();return true;},$gates);
                phui_probe('fingerprint',function()use($expected,$connect){
                    $profile=$connect();
                    $secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
                    return hash_equals(provider_inventory_fingerprint($secret),$expected['review']['context']['binding']['key_fingerprint']);
                },$gates);
                phui_probe('endpoint',fn()=>EndpointPolicy::addresses([FixedHttpTransport::resolve(static fn(string $name)=>[['type'=>'A','ip'=>'8.8.8.8']])])!==[],$gates);
                phui_probe('operation',function()use($expected){new ProviderOperation(PhpVersionChange::OPERATION,$expected['idempotency_key'],$expected['review']['params']);return true;},$gates);
            }
            $output=['passed'=>true,'phase'=>'gate','gates'=>$gates];
        }
        elseif($phase==='cool'){phui_keys([]);$output=['passed'=>true,'phase'=>'cool','waited_ms'=>phui_cool($state['profile_id'])];}
        elseif($phase==='inventory'){phui_keys([]);phui_inventory($state['profile_id']);$output=['passed'=>true,'phase'=>'inventory'];}
        elseif($phase==='options'){
            phui_keys(['session_id','actor','link_id','mode']);if(!is_string($input['session_id'])||!in_array($input['actor'],['owner','client'],true)||!in_array($input['mode'],['normal','empty','unavailable'],true)||!in_array($input['link_id'],[$state['link_id'],$state['other_link_id']],true))throw new InvalidArgumentException('Invalid synthetic options request.');
            phui_session($input['session_id']);$GLOBALS['phui_option_mode']=$input['mode'];$transport=$input['mode']==='unavailable'?null:'phui_options_transport';
            if($input['actor']==='owner')$receipt=provider_php_controls_read_owner($input['link_id'],'available',PHUI_OWNER_PASSWORD,static fn():string=>'8.8.8.8',$transport);
            else{$client=phui_scope(function():array{$id=$_SESSION['client_id']??null;if(!is_int($id))throw new RuntimeException('Existing client session required.');return provider_php_controls_primary_client($id);});$receipt=provider_php_controls_read_client((int)$client['id'],$input['link_id'],'available',static fn():string=>'8.8.8.8',$transport);}
            $output=['passed'=>true,'phase'=>'options','synthetic_options'=>$transport!==null]+$receipt;
        } elseif($phase==='contact-add'||$phase==='contact-clear'){
            phui_keys(['session_id']);if(!is_string($input['session_id']))throw new InvalidArgumentException('Invalid session probe.');phui_session($input['session_id']);$saved=$private.'/contact-'.hash('sha256',$input['session_id']).'.json';
            if($phase==='contact-add'){
                phui_scope(function():void{$id=$_SESSION['client_id']??null;if(!is_int($id))throw new RuntimeException('Existing primary session required.');provider_php_controls_primary_client($id);});
                $before=[];foreach(['contact_id','contact_auth'] as $key)$before[$key]=['present'=>array_key_exists($key,$_SESSION),'value'=>$_SESSION[$key]??null];
                phui_create($saved,json_encode($before,JSON_THROW_ON_ERROR));$_SESSION['contact_id']=91;$_SESSION['contact_auth']='synthetic-revoked-contact-context';
            } else {
                $before=phui_json_file($saved,2048);
                if(array_keys($before)!==['contact_id','contact_auth']||($_SESSION['contact_id']??null)!==91||($_SESSION['contact_auth']??null)!=='synthetic-revoked-contact-context')throw new RuntimeException('Contact probe markers were unexpectedly normalized.');
                foreach($before as $key=>$old){if($old['present'])$_SESSION[$key]=$old['value'];else unset($_SESSION[$key]);}
                if(!unlink($saved))throw new RuntimeException('Could not close private contact probe.');
            }
            $output=['passed'=>true,'phase'=>$phase];
        } else {
            phui_keys([]);$trigger='phcui_php_ui_terminal_fault';
            $exists=one('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=?',[$trigger]);
            if($phase==='fault-arm'){
                if($exists||phui_exists('fixture_fault_hits'))throw new RuntimeException('Prior fault probe exists.');
                if(!phui_scope(fn()=>provider_php_controls_lifecycle_ready()))throw new RuntimeException('Prepare lifecycle before terminal fault.');
                // This fixture-only nontransactional hit witness survives the intentional
                // rollback. It is excluded from protected provider history and never shipped.
                db()->exec('CREATE TABLE '.table('fixture_fault_hits').' (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,terminal_rows INT UNSIGNED NOT NULL,finished_ledgers INT UNSIGNED NOT NULL) ENGINE=MyISAM');
                db()->exec('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.php_control_lifecycle_terminal' THEN INSERT INTO ".table('fixture_fault_hits').' (terminal_rows,finished_ledgers) SELECT (SELECT COUNT(*) FROM '.table('provider_php_dispatch_ends').'),(SELECT COUNT(*) FROM '.table('provider_operations')." WHERE status<>'claimed'); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic PHP terminal audit fault'; END IF; END");
                $output=['passed'=>true,'phase'=>'fault-arm'];
            } else {
                if(!$exists||!phui_exists('fixture_fault_hits'))throw new RuntimeException('No original fault probe to close.');
                $hits=all('SELECT id,terminal_rows,finished_ledgers FROM '.table('fixture_fault_hits').' ORDER BY id');
                if(count($hits)!==1||(int)$hits[0]['terminal_rows']<1||(int)$hits[0]['finished_ledgers']<1)throw new RuntimeException('Fault was not reached after genuine terminal and ledger writes.');
                db()->exec('DROP TRIGGER '.$trigger);$output=['passed'=>true,'phase'=>'fault-clear','fault_hits'=>1];
            }
        }
    }
    if(session_status()===PHP_SESSION_ACTIVE&&!session_write_close())throw new RuntimeException('Could not save fixture browser session.');
    echo json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if(session_status()===PHP_SESSION_ACTIVE)session_write_close();echo json_encode(['passed'=>false,'phase'=>$phase,'failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()]],JSON_THROW_ON_ERROR)."\n";exit(1);}
