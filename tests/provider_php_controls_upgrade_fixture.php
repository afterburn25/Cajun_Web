<?php
/** Installed-code companion only. No deployed route and no real provider transport. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==4)exit(2);
$port=getenv('CWP_TEST_PORT');if(!is_string($port)||!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
$repo=realpath(__DIR__.'/..');$root=realpath($argv[1]);$web=realpath($argv[3]);
$expected=$repo.'/test-runtime/provider-php-controls-upgrade/phc-upgrade/web';
if(!$repo||!$root||!$web||$web!==$expected||$root!==$web.'/subfolder')exit(2);
foreach([$root,$web,dirname($web),dirname(dirname($web)),$repo.'/test-runtime'] as $path)if(is_link($path))exit(2);
$mode=$argv[2];if(!in_array($mode,['preflight','snapshot','seed','verify','recover-terminal','damage','refuse'],true))exit(2);
$checks=[];
function phcu_check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
if($mode==='preflight'){
    $db=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4',(string)getenv('CWP_TEST_USER'),(string)getenv('CWP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $s=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(table_name,5)=BINARY ?');$s->execute(['phcu_']);
    phcu_check('Dedicated installed namespace is empty',(int)$s->fetchColumn()===0);
    echo json_encode(['passed'=>true,'checks'=>$checks],JSON_THROW_ON_ERROR);exit;
}
if(!is_file($root.'/config.php')||is_link($root.'/config.php'))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';
if(config()['db']['name']!=='cwp_test'||config()['db']['prefix']!=='phcu_'||config()['db']['host']!=='127.0.0.1'||(int)config()['db']['port']!==(int)$port)exit(2);

/** Exact original row/column hashes; only existing authentication bookkeeping is excluded. */
function phcu_snapshot():array{
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
function phcu_digest():string{return hash('sha256',json_encode(phcu_snapshot(),JSON_THROW_ON_ERROR));}
if($mode==='snapshot'){echo json_encode(['passed'=>true,'checks'=>[],'snapshot'=>phcu_snapshot()],JSON_THROW_ON_ERROR);exit;}
require CWP_CODE_ROOT.'/app/providers/php_controls_changes.php';
require_once CWP_CODE_ROOT.'/app/providers/php_controls_projection.php';
require_once CWP_CODE_ROOT.'/app/clients/accounts.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIPackagePhp\{PackagePhpVersion,PackagePhpVersionBinding,PackagePhpVersionObservation,PackagePhpOptionSet};
use CajunWebPro\Foundation\TwentyI\Operations\{PhpVersionChange,TwentyIOperationAdapter};

const PHCU_PASSWORD='Synthetic PHP controls upgrade owner passphrase';
const PHCU_CLIENT_PASSWORD='Synthetic PHP controls upgrade client passphrase';
const PHCU_SECRET='SYNTHETIC_PHP_CONTROLS_UPGRADE_KEY';
const PHCU_LABEL='PHP controls upgrade fixture';
const PHCU_FIRST='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const PHCU_SECOND='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$dns=0;$gets=0;$optionsCalls=0;$posts=0;$wire=[];$currentValue='php82';$writeMode='unknown';
function phcu_ok(string $name,bool $passed):void{phcu_check($name,$passed);}
function phcu_deny(string $name,callable $call):void{
    $caught=false;try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=true;}phcu_ok($name,$caught);
}
function phcu_scope(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function phcu_mutex():bool{$key='cwp-staff-'.substr(hash('sha256','cwp_test|phcu_'),0,40);return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1;}
function phcu_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function phcu_owner():void{
    $u=one('SELECT * FROM '.table('users').' WHERE email=?',['phc-upgrade@example.invalid']);
    if(!$u||!password_verify(PHCU_PASSWORD,$u['password_hash']))throw new RuntimeException('Wrong installed owner.');
    $_SESSION=['uid'=>(int)$u['id'],'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);
}
function phcu_client():int{
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE email=?',['phc-upgrade-client@example.invalid']);
    if(!$c||!password_verify(PHCU_CLIENT_PASSWORD,$c['password_hash']))throw new RuntimeException('Wrong original client.');
    $_SESSION=['client_id'=>(int)$c['id'],'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);return (int)$c['id'];
}
function phcu_link():array{
    $p=one('SELECT id FROM '.table('provider_profiles').' WHERE label=?',[PHCU_LABEL]);
    $l=$p?one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=701',[$p['id']]):null;
    if(!$l)throw new RuntimeException('Missing original hosting assignment.');return $l;
}
/** Real finite cooldown outside locks, never a timestamp edit or clock override. */
function phcu_wait(string $profile):void{
    if(db()->inTransaction()||phcu_mutex())throw new LogicException('Cooldown inside mutation guard.');
    $at=0;foreach(['provider_refreshes','provider_checks','provider_php_control_reads'] as $name){
        if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['phcu_'.$name]))continue;
        $at=max($at,(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table($name).' WHERE profile_id=?',[$profile])->fetchColumn());
    }
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture read.');$deadline=microtime(true)+32;
    while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
function phcu_dns():string{global $dns;if(db()->inTransaction())throw new LogicException('DNS inside transaction.');$dns++;return '8.8.8.8';}
function phcu_boundary(callable $authorize):void{$authorize();if(db()->inTransaction()||!phcu_mutex())throw new LogicException('Wire boundary requires mutex without transaction.');}
final class PhcuReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url,private string $body){}
    public function send(TransportRequest $r):TransportResponse{
        global $gets;phcu_boundary($this->authorize);
        if($r->method!=='GET'||$r->url!==$this->url||$r->body!==null)throw new LogicException('Wrong synthetic read wire.');
        $gets++;return new TransportResponse(200,[],$this->body);
    }
}
function phcu_inventory_transport(array $row,string $secret,string $address,callable $authorize,string $operation,?PackagePhpVersionBinding $packageContext=null):InventorySnapshot|PackagePhpVersionObservation{
    global $currentValue;phcu_boundary($authorize);
    if($secret!==PHCU_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic reader credential.');
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($authorize,$secret):string{phcu_boundary($authorize);return $secret;});
    if($operation===InventoryRead::PACKAGES_LIST){
        $body=json_encode([['id'=>701,'name'=>'upgrade.synthetic.invalid','names'=>['upgrade.synthetic.invalid'],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null]],JSON_THROW_ON_ERROR);
        return InventoryReader::read(new PhcuReadTransport($authorize,'https://api.20i.com/package',$body),$credential,$operation,null,time());
    }
    if($operation!==PackagePhpVersion::OPERATION||!$packageContext)throw new LogicException('Wrong legacy scalar operation.');
    return PackagePhpVersion::read(new PhcuReadTransport($authorize,'https://api.20i.com/package/701/web/phpVersion',json_encode($currentValue,JSON_THROW_ON_ERROR)),$credential,$packageContext,time());
}
/** Explicit synthetic normalized choices; never a claimed availablePhpVersions response parser. */
function phcu_transport(array $row,string $secret,string $address,callable $authorize,PackagePhpVersionBinding $binding,string $kind):PackagePhpVersionObservation|PackagePhpOptionSet{
    global $optionsCalls,$currentValue;phcu_boundary($authorize);
    if($secret!==PHCU_SECRET||$address!=='8.8.8.8'||$binding->data['package_id']!==701||$row['id']!==$binding->data['profile_id'])throw new LogicException('Wrong synthetic PHP context.');
    if($kind==='available'){$optionsCalls++;return PackagePhpOptionSet::fromNormalizedRows([
        ['value'=>'php82','label'=>'Synthetic PHP 8.2','handler'=>'Synthetic handler'],
        ['value'=>'php84','label'=>'Synthetic PHP 8.4','handler'=>'Synthetic handler']]);}
    if($kind!=='current')throw new LogicException('Wrong synthetic PHP read kind.');
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($authorize,$secret):string{phcu_boundary($authorize);return $secret;});
    return PackagePhpVersion::read(new PhcuReadTransport($authorize,'https://api.20i.com/package/701/web/phpVersion',json_encode($currentValue,JSON_THROW_ON_ERROR)),$credential,$binding,time());
}
final class PhcuWriteTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $r):TransportResponse{
        global $posts,$wire,$writeMode;phcu_boundary($this->authorize);PhpVersionChange::wire($r);
        if($r->url!=='https://api.20i.com/package/701/web/phpVersion'||($r->headers['Authorization']??null)!=='Bearer '.base64_encode(PHCU_SECRET))throw new LogicException('Wrong synthetic PHP write identity.');
        $posts++;$wire[]=['method'=>$r->method,'url'=>$r->url,'body'=>$r->body];
        if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
        if($writeMode!=='unknown')throw new LogicException('Wrong synthetic write mode.');return new TransportResponse(200,[],'{}');
    }
}
function phcu_perform(array $row,string $secret,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    phcu_boundary($authorize);if($secret!==PHCU_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic sender credential.');
    $credential=new ProviderCredential('twenty-i',$row['id'].':'.(int)$row['revision'].':'.provider_operations_credential_fingerprint($secret),static function()use($authorize,$secret):string{phcu_boundary($authorize);return $secret;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new PhcuWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function phcu_read(array $link,string $kind,bool $synthetic=true):array{
    phcu_wait($link['profile_id']);return provider_php_controls_read_client((int)$link['client_id'],$link['id'],$kind,'phcu_dns',$synthetic?'phcu_transport':null);
}
function phcu_get(string $kind,string $id):?array{return phcu_scope(fn()=>provider_php_controls_lifecycle_get($kind,$id));}
function phcu_ready():bool{return phcu_scope(fn()=>provider_php_controls_lifecycle_ready());}
function phcu_summary(string $id):array{return phcu_scope(fn()=>provider_php_controls_summary_locked($id));}
function phcu_pending(array $link):?array{return phcu_scope(fn()=>provider_php_controls_lifecycle_pending($link['profile_id'],701));}
function phcu_change(string $id,array $link,array $current,array $options,array $review,string $desired):array{
    return provider_php_controls_change_client((int)$link['client_id'],$id,$link['id'],$current['id'],$options['id'],$desired,$review['review_sha256'],true,PHCU_CLIENT_PASSWORD,'phcu_dns','phcu_perform');
}
function phcu_replay(string $id,array $link):array{
    $d=phcu_get('request',$id)['document'];$r=$d['review'];
    return provider_php_controls_change_client((int)$link['client_id'],$id,$link['id'],$r['current_read_id'],$r['options_read_id'],$r['selected']['value'],$d['review_sha256'],true,PHCU_CLIENT_PASSWORD,'phcu_dns','phcu_perform');
}
function phcu_history(bool $recovered=false):void{
    global $dns,$gets,$optionsCalls,$posts;$client=phcu_client();$link=phcu_link();$before=phcu_digest();$io=[$dns,$gets,$optionsCalls,$posts];
    phcu_ok('Original client session remains primary without staff identity',admin_user()===null&&(int)$link['client_id']===$client);
    phcu_ok('Complete original lifecycle and delegation validate',phcu_ready()&&phcu_scope(fn()=>provider_php_controls_ready()));
    $one=phcu_summary(PHCU_FIRST);$two=phcu_summary(PHCU_SECOND);
    phcu_ok('First request retains exact later observed scalar',$one['readback']==='observed'&&$one['observed_value']==='php84');
    phcu_ok('Second original request retains its unresolved resource fence',$two['phase']===($recovered?'attempted':'pending')&&$two['readback']==='pending'&&$two['recovered_from_ledger']===$recovered&&phcu_pending($link)['request']['document']['id']===PHCU_SECOND);
    phcu_ok('Both original client approvals replay without send',phcu_replay(PHCU_FIRST,$link)===$one&&phcu_replay(PHCU_SECOND,$link)===$two);
    $r=phcu_get('request',PHCU_SECOND)['document']['review'];
    phcu_deny('New approval cannot cross retained pending resource fence',fn()=>provider_php_controls_review_client($client,$link['id'],$r['current_read_id'],$r['options_read_id'],$r['selected']['value']));
    $projection=phcu_scope(fn()=>provider_php_project($link['id'],true));
    phcu_ok('Unresolved request keeps prior shared scalar historical',$projection['status']==='stale'&&$projection['rows'][0]['current']===false);
    foreach([PHCU_FIRST,PHCU_SECOND] as $id){
        $request=phcu_get('request',$id);$d=$request['document'];$ledger=phcu_scope(fn()=>provider_php_controls_lifecycle_ledger_snapshot($id));
        phcu_ok('Original client request and one-attempt ledger correspond '.$id,$d['review']['context']['actor']['kind']==='client'&&$d['review']['context']['actor']['id']===$client&&$d['idempotency_key']==='phpversion:'.$id&&$ledger['attempts']===1&&$ledger['status']==='uncertain'&&$ledger['operation']==='packages.phpversion');
    }
    if($recovered){$t=phcu_get('terminal',PHCU_SECOND)['document'];phcu_ok('Recovered terminal distinguishes original client and recording owner',$t['request_actor']['kind']==='client'&&$t['request_actor']['id']===$client&&$t['recorded_by']['kind']==='staff'&&$t['recorded_by']['id']===(int)one('SELECT id FROM '.table('users').' WHERE email=?',['phc-upgrade@example.invalid'])['id']);}
    phcu_owner();$profile=provider_settings_load($link['profile_id']);$key=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
    phcu_ok('Original vault key still opens exact saved synthetic account credential',hash_equals(PHCU_SECRET,$key));unset($key);
    phcu_ok('History inspection and exact replay change no original rows keys or transport counts',$before===phcu_digest()&&$io===[$dns,$gets,$optionsCalls,$posts]);
}
function phcu_damage_probe(string $label,callable $mutate):void{
    $before=phcu_digest();staff_locked(function()use($label,$mutate):void{db()->beginTransaction();try{$mutate();phcu_deny($label,fn()=>provider_php_controls_lifecycle_ready());}finally{if(db()->inTransaction())db()->rollBack();unset($GLOBALS['cwp_settings']);}});
    phcu_ok($label.' restores exact original rows',phcu_digest()===$before&&phcu_ready());
}
function phcu_probes():void{
    $r=phcu_get('request',PHCU_FIRST)['document'];
    foreach([
        'delegation proof'=>fn()=>query('UPDATE '.table('provider_php_control_delegations').' SET proof=?',[str_repeat('0',64)]),
        'request proof'=>fn()=>query('UPDATE '.table('provider_php_changes').' SET proof=? WHERE id=?',[str_repeat('0',64),PHCU_FIRST]),
        'original options result'=>fn()=>query('DELETE FROM '.table('provider_php_control_read_results').' WHERE read_id=?',[$r['review']['options_read_id']]),
        'terminal'=>fn()=>query('DELETE FROM '.table('provider_php_dispatch_ends').' WHERE request_id=?',[PHCU_FIRST]),
        'check'=>fn()=>query('DELETE FROM '.table('provider_php_change_checks').' WHERE request_id=?',[PHCU_FIRST]),
        'ledger'=>fn()=>query('DELETE FROM '.table('provider_operations').' WHERE idempotency_key=?',['phpversion:'.PHCU_FIRST]),
        'marker'=>fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_php_control_lifecycle_schema_version']),
        'terminal audit'=>fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=?',['provider.php_control_lifecycle_terminal']),
    ] as $name=>$mutate)phcu_damage_probe('Damaged '.$name.' refuses installed readiness',$mutate);
}
function phcu_refuse():void{
    $before=phcu_digest();$link=phcu_link();
    phcu_deny('Lost established check refuses readiness',fn()=>phcu_ready());
    phcu_deny('Lost established check refuses explicit setup repair',fn()=>provider_php_controls_lifecycle_prepare(PHCU_PASSWORD));
    phcu_deny('Lost established check cannot release resource fence',fn()=>phcu_pending($link));
    phcu_client();phcu_deny('Lost established check cannot replay a provider send',fn()=>phcu_replay(PHCU_SECOND,$link));phcu_owner();
    phcu_ok('All corruption refusals preserve remaining history',$before===phcu_digest());
}
try{
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/subfolder/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$web;start_session();phcu_owner();provider_settings_owner(PHCU_PASSWORD);
    if($mode==='seed'){
        phcu_ok('Target update leaves new PHP schemas unprepared',!phcu_scope(fn()=>provider_php_controls_ready())&&!phcu_ready());
        provider_settings_migrate(PHCU_PASSWORD);provider_inventory_migrate(PHCU_PASSWORD);provider_php_prepare(PHCU_PASSWORD);provider_operations_migrate(PHCU_PASSWORD);
        $profile=provider_settings_save('',0,PHCU_LABEL,true,PHCU_SECRET,PHCU_PASSWORD);
        $client=studio_client_save(0,'','PHP controls upgrade client','phc-upgrade-client@example.invalid',true);$clientRow=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=?',[$client]);
        $url=studio_new_invite($client,$clientRow['auth_version']);parse_str((string)parse_url($url,PHP_URL_QUERY),$invite);studio_activate($invite['invite'],PHCU_CLIENT_PASSWORD);
        $source=provider_inventory_refresh_attempt($profile,1,PHCU_PASSWORD,'phcu_dns','phcu_inventory_transport');phcu_ok('Installed source uses real complete package reader',$source['state']==='complete');
        provider_inventory_link($profile,701,$client,1,PHCU_PASSWORD);phcu_wait($profile);
        $source=provider_inventory_refresh_attempt($profile,1,PHCU_PASSWORD,'phcu_dns','phcu_inventory_transport');phcu_ok('Fresh complete source follows actual assignment',$source['state']==='complete');$link=phcu_link();
        provider_php_controls_prepare(PHCU_PASSWORD);$review=phcu_scope(fn()=>provider_php_controls_delegation_review($link['id'],true));
        provider_php_controls_delegate($link['id'],$review['permission_id'],str_repeat('d',32),true,$review['review_sha256'],PHCU_PASSWORD);
        phcu_wait($profile);$binding=phcu_scope(fn()=>provider_php_binding($link['id']));$legacy=provider_php_refresh($link['id'],$binding,PHCU_PASSWORD,'phcu_dns','phcu_inventory_transport');
        provider_php_share($link['id'],'',str_repeat('e',32),true,$legacy['id'],PHCU_PASSWORD);
        phcu_ok('Original saved PHP sharing is prepared separately',$legacy['state']==='complete'&&phcu_scope(fn()=>provider_php_project($link['id'],true))['status']==='observed');
        phcu_ok('Delegation alone does not prepare lifecycle',!phcu_ready());provider_php_controls_lifecycle_prepare(PHCU_PASSWORD);$before=phcu_digest();provider_php_controls_lifecycle_prepare(PHCU_PASSWORD);
        phcu_ok('Explicit lifecycle setup is idempotent',phcu_ready()&&phcu_digest()===$before);
        $client=phcu_client();$io=[$dns,$gets,$optionsCalls];$unavailable=phcu_read($link,'available',false);
        phcu_ok('Default available options remain unavailable without external work',$unavailable['state']==='unavailable'&&$unavailable['reason']==='unexpected_response'&&$io===[$dns,$gets,$optionsCalls]);
        $current=phcu_read($link,'current');$options=phcu_read($link,'available');$review=provider_php_controls_review_client($client,$link['id'],$current['id'],$options['id'],'php84');
        $first=phcu_change(PHCU_FIRST,$link,$current,$options,$review,'php84');
        phcu_ok('First actual client confirmation sends once and records uncertainty',$first['phase']==='attempted'&&$first['readback']==='pending'&&$posts===1);
        phcu_wait($profile);$different=provider_php_controls_verify_client($client,PHCU_FIRST,PHCU_CLIENT_PASSWORD,'phcu_dns','phcu_transport');
        phcu_ok('Independent different readback retains fence',$different['readback']==='different'&&phcu_pending($link)!==null);
        $currentValue='php84';phcu_wait($profile);$observed=provider_php_controls_verify_client($client,PHCU_FIRST,PHCU_CLIENT_PASSWORD,'phcu_dns','phcu_transport');
        phcu_ok('Independent matching readback resolves first request',$observed['readback']==='observed'&&phcu_pending($link)===null&&$posts===1);
        $current2=phcu_read($link,'current');$options2=phcu_read($link,'available');$review2=provider_php_controls_review_client($client,$link['id'],$current2['id'],$options2['id'],'php82');$writeMode='timeout';
        db()->exec('CREATE TRIGGER phcu_terminal_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.php_control_lifecycle_terminal' THEN SET @phcu_hits=COALESCE(@phcu_hits,0)+1; SET @phcu_rows=(SELECT COUNT(*) FROM ".table('provider_php_dispatch_ends')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic installed terminal audit fault'; END IF; END");
        query('SET @phcu_hits=0,@phcu_rows=0');$fault=false;
        try{phcu_change(PHCU_SECOND,$link,$current2,$options2,$review2,'php82');}catch(PDOException $e){$fault=$e->getCode()==='45000'&&(int)query('SELECT @phcu_hits')->fetchColumn()===1&&(int)query('SELECT @phcu_rows')->fetchColumn()===2;}finally{db()->exec('DROP TRIGGER phcu_terminal_fault');}
        phcu_ok('Second real send finishes ledger before terminal audit rollback',$fault&&$posts===2&&phcu_count('provider_php_dispatch_ends')===1&&phcu_count('provider_operations')===2);
        phcu_ok('Both exact wire bodies contain only selected value',$wire===[['method'=>'POST','url'=>'https://api.20i.com/package/701/web/phpVersion','body'=>'{"value":"php84"}'],['method'=>'POST','url'=>'https://api.20i.com/package/701/web/phpVersion','body'=>'{"value":"php82"}']]);
        phcu_history();
    }elseif($mode==='verify'){phcu_history();phcu_probes();}
    elseif($mode==='recover-terminal'){
        $ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['phpversion:'.PHCU_SECOND]);$review=provider_php_controls_recovery_review_owner(PHCU_SECOND);
        phcu_ok('Retained finished ledger offers exact local recovery',$review['ready']===true&&$review['request_id']===PHCU_SECOND);
        phcu_deny('Changed reviewed ledger hash refuses recovery',fn()=>provider_php_controls_recover_owner(PHCU_SECOND,str_repeat('0',64),PHCU_PASSWORD));
        $recovered=provider_php_controls_recover_owner(PHCU_SECOND,$review['ledger_sha256'],PHCU_PASSWORD);
        phcu_ok('Owner recovers original missing terminal without transport or ledger mutation',$recovered['phase']==='attempted'&&$recovered['recovered_from_ledger']===true&&$ledger===one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['phpversion:'.PHCU_SECOND]));
        phcu_deny('Recovery cannot replace established terminal',fn()=>provider_php_controls_recover_owner(PHCU_SECOND,$review['ledger_sha256'],PHCU_PASSWORD));phcu_history(true);phcu_probes();
    }elseif($mode==='damage'){
        phcu_history(true);phcu_scope(function():void{$c=one('SELECT id FROM '.table('provider_php_change_checks').' WHERE request_id=? ORDER BY id LIMIT 1',[PHCU_FIRST]);if(!$c||query('DELETE FROM '.table('provider_php_change_checks').' WHERE id=?',[$c['id']])->rowCount()!==1)throw new RuntimeException('Expected one deliberate lost check.');});phcu_refuse();
    }else phcu_refuse();
    phcu_ok('Only explicit seed phase can call synthetic transport',$mode==='seed'?($posts===2&&$optionsCalls===2):[$dns,$gets,$optionsCalls,$posts]===[0,0,0,0]);
    echo json_encode(['passed'=>true,'checks'=>$checks,'synthetic_io'=>['dns'=>$dns,'gets'=>$gets,'normalized_options'=>$optionsCalls,'posts'=>$posts]],JSON_THROW_ON_ERROR);
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>['class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null]],JSON_THROW_ON_ERROR);exit(1);}
