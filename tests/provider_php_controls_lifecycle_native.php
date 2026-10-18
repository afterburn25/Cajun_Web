<?php
/** Real disposable SQL/authority/lock checks. Closed synthetic transport only; no provider network. */
declare(strict_types=1);
if (PHP_SAPI!=='cli' || PHP_OS_FAMILY==='Windows' || getenv('CWP_TEST_DATABASE')!=='cwp_test'
    || (getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1') exit(2);
$worker=($argv??[])!==[] && count($argv)===2 && $argv[1]==='--worker';
if (count($argv)!==($worker?2:1)) exit(2);
$portText=getenv('CWP_TEST_PORT')?:'33306';
if (!ctype_digit($portText) || (int)$portText<1 || (int)$portText>65535) exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/php_controls_authority.php';
require __DIR__.'/../site/app/providers/php_controls_connection.php';
require __DIR__.'/../site/app/providers/operations.php';
require __DIR__.'/../site/app/providers/php_controls_reads.php';
require __DIR__.'/../site/app/providers/php_controls_changes.php';
require __DIR__.'/../site/app/providers/php_controls_projection.php';
use CajunWebPro\Foundation\Provider\{TransportResponse,TransportRequest,TransportException,HttpTransport,ProviderCredential,ProviderOperation,ProviderGateway,OperationResult};
use CajunWebPro\Foundation\TwentyIPackagePhp\{PackagePhpVersion,PackagePhpVersionBinding,PackagePhpVersionObservation,PackagePhpOptionSet};
use CajunWebPro\Foundation\TwentyI\Operations\{PhpVersionChange,TwentyIOperationAdapter};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventoryProjector};

const PCL_PASSWORD='Synthetic PHP lifecycle owner passphrase';
const PCL_CLIENT_PASSWORD='Synthetic PHP lifecycle client passphrase';
const PCL_SECRET='SYNTHETIC_PHP_LIFECYCLE_KEY';
const PCL_PREFIX='phplc_';
$root=realpath(__DIR__.'/..');
if (!$root) exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';$private=$runtime.'/php-controls-lifecycle-private';
foreach ([$runtime,$qa] as $dir) {
    if (is_link($dir) || (file_exists($dir)&&!is_dir($dir))) exit(2);
    if (!is_dir($dir)&&!mkdir($dir,0700)) exit(2);
}
$report=$qa.'/provider-php-controls-lifecycle-native-results.json';
if (is_link($private) || ($worker?!is_dir($private):file_exists($private)) || (!$worker&&(is_link($report)||file_exists($report)))) exit(2);
if (!$worker&&!mkdir($private,0700)) exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/php-controls','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-php-lifecycle-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$portText,
    'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>PCL_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=realpath($root.'/site');start_session();
$checks=[];$stage='preflight';
function pcl_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function pcl_deny(string $name,callable $fn,?string $message=null):void {
    $caught=null;try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    pcl_ok($name,$caught!==null&&($message===null||$caught->getMessage()===$message));
}
function pcl_owner(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);$_GET=[];}
function pcl_client(int $id=1):void {$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);$_GET=[];}
function pcl_scope(callable $fn):mixed {return staff_locked(fn()=>staff_transaction($fn));}
/** Intent/source rows are new synthetic observations; no retained clocks or proofs are rewritten. */
function pcl_inventory(string $profile):string {
    return pcl_scope(function()use($profile):string {
        $now=time();$r=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$profile,'profile_revision'=>1,'actor_id'=>1,
            'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now,'operation'=>InventoryRead::PACKAGES_LIST];
        query('INSERT INTO '.table('provider_refreshes').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch,operation) VALUES (?,?,?,?,?,?,?)',array_values($r));
        provider_settings_audit(1,'refresh_started','Refresh '.$r['id'].' profile '.$profile.' revision 1');
        $items=[];foreach([701,702] as $id)$items[]=['id'=>$id,'name'=>'site'.$id.'.synthetic.invalid','names'=>['site'.$id.'.synthetic.invalid'],
            'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];
        $s=InventoryProjector::packagesList(new TransportResponse(200,[],json_encode($items,JSON_THROW_ON_ERROR)),$now,'twenty-i|'.$profile.'|packages.list');
        if($s->state!=='complete'||count($s->packages)!==2)throw new LogicException('Synthetic inventory projection failed.');
        provider_inventory_record_transaction($r,$s->state,$s->reason,$s->scope,'unknown',count($s->packages),$s->skipped,json_encode($s->skipReasons),0,$s,provider_inventory_fingerprint(PCL_SECRET));return $r['id'];
    });
}

function pcl_count(string $table):int{return (int)query('SELECT COUNT(*) FROM '.table($table))->fetchColumn();}
function pcl_ready():bool{return staff_locked(fn()=>provider_php_controls_lifecycle_ready());}
function pcl_get(string $kind,string $id):array{return pcl_scope(fn()=>provider_php_controls_lifecycle_get($kind,$id));}
function pcl_digest():string {
    $rows=[];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(PCL_PREFIX),PCL_PREFIX]) as $r){
        $name=$r['TABLE_NAME'];if(!preg_match('/\Aphplc_[a-z0-9_]+\z/',$name))throw new LogicException('Unexpected fixture table.');
        $v=all('SELECT * FROM `'.$name.'`');usort($v,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$v;
    }return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
function pcl_delegate(string $link,bool $enabled):array {
    $r=pcl_scope(fn()=>provider_php_controls_delegation_review($link,$enabled));
    return provider_php_controls_delegate($link,$r['permission_id'],bin2hex(random_bytes(16)),$enabled,$r['review_sha256'],PCL_PASSWORD);
}
/** Genuine finite wait outside every mutation lock; no saved timestamp is edited. */
function pcl_cool():void {
    global $profile;
    if(db()->inTransaction())throw new LogicException('Cooldown inherited SQL transaction.');
    $key='cwp-staff-'.substr(hash('sha256','cwp_test|'.PCL_PREFIX),0,40);
    if((int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1)throw new LogicException('Cooldown owns mutation mutex.');
    $latest=0;foreach(['provider_refreshes','provider_checks','provider_php_control_reads'] as $name){
        if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[PCL_PREFIX.$name]))continue;
        $latest=max($latest,(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table($name).' WHERE profile_id=?',[$profile])->fetchColumn());
    }
    if($latest>time()||$latest<0)throw new RuntimeException('Unexpected future fixture read.');
    $deadline=microtime(true)+32;while(time()<$latest+30){if(microtime(true)>$deadline)throw new RuntimeException('Cooldown bound exceeded.');usleep(100000);}
}
function pcl_boundary(callable $authorize):array {
    $r=$authorize();$key='cwp-staff-'.substr(hash('sha256','cwp_test|'.PCL_PREFIX),0,40);
    if(db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()!==1)throw new LogicException('Provider boundary needs mutex without SQL transaction.');return $r;
}
$dns=0;$reads=0;$optionsCalls=0;$writes=0;$currentValue='php82';$currentStatus=200;$writeMode='success';$duringWrite=null;$wire=[];
function pcl_dns():string {global $dns;if(db()->inTransaction())throw new LogicException('DNS inside transaction.');$dns++;return '8.8.8.8';}
final class PclReadTransport implements HttpTransport {
    public function __construct(private $authorize,private int $package){}
    public function send(TransportRequest $r):TransportResponse {global $reads,$currentValue,$currentStatus;
        pcl_boundary($this->authorize);
        if($r->method!=='GET'||$r->url!=='https://api.20i.com/package/'.$this->package.'/web/phpVersion'||$r->body!==null)throw new LogicException('Wrong scalar transport request.');
        $reads++;return new TransportResponse($currentStatus,[],json_encode($currentValue,JSON_THROW_ON_ERROR));
    }
}
function pcl_transport(array $row,string $secret,string $address,callable $authorize,PackagePhpVersionBinding $binding,string $kind):PackagePhpVersionObservation|PackagePhpOptionSet {
    global $optionsCalls;pcl_boundary($authorize);
    if($secret!==PCL_SECRET||$address!=='8.8.8.8'||$row['id']!==$binding->data['profile_id'])throw new LogicException('Wrong synthetic reader identity.');
    if($kind==='available'){$optionsCalls++;return PackagePhpOptionSet::fromNormalizedRows([
        ['value'=>'php82','label'=>'Synthetic PHP 8.2','handler'=>'Synthetic handler'],
        ['value'=>'php84','label'=>'Synthetic PHP 8.4','handler'=>'Synthetic handler'],
    ]);}
    if($kind!=='current')throw new LogicException('Unexpected read kind.');
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($authorize,$secret):string{pcl_boundary($authorize);return $secret;});
    return PackagePhpVersion::read(new PclReadTransport($authorize,$binding->data['package_id']),$credential,$binding,time());
}
final class PclWriteTransport implements HttpTransport {
    public function __construct(private $authorize){}
    public function send(TransportRequest $r):TransportResponse {global $writes,$writeMode,$duringWrite,$wire;
        pcl_boundary($this->authorize);PhpVersionChange::wire($r);
        if(!in_array($r->url,['https://api.20i.com/package/701/web/phpVersion','https://api.20i.com/package/702/web/phpVersion'],true))throw new LogicException('Unexpected synthetic write package.');
        $writes++;$wire[]=['method'=>$r->method,'url'=>$r->url,'body'=>$r->body];if($duringWrite)$duringWrite();
        if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
        return new TransportResponse(200,[],'{}');
    }
}
function pcl_perform(array $row,string $secret,string $address,callable $authorize,ProviderOperation $operation):OperationResult {
    if($secret!==PCL_SECRET||$address!=='8.8.8.8')throw new LogicException('Wrong synthetic sender identity.');
    pcl_boundary($authorize);
    $credential=new ProviderCredential('twenty-i',$row['id'].':'.(int)$row['revision'].':'.provider_operations_credential_fingerprint($secret),static function()use($secret,$authorize):string{pcl_boundary($authorize);return $secret;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new PclWriteTransport($authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
/** Existing owner-only scalar wrapper; this remains a separate saved observation. */
function pcl_legacy_transport(array $row,string $secret,string $address,callable $authorize,string $operation,?PackagePhpVersionBinding $packageContext=null):PackagePhpVersionObservation {
    global $reads,$currentValue;$authorize();
    if(db()->inTransaction()||$operation!==PackagePhpVersion::OPERATION||$secret!==PCL_SECRET||$address!=='8.8.8.8'||!$packageContext||$packageContext->data['profile_id']!==$row['id'])throw new LogicException('Unexpected legacy scalar boundary.');
    $reads++;return PackagePhpVersion::project(new TransportResponse(200,[],json_encode($currentValue,JSON_THROW_ON_ERROR)),$packageContext,time());
}
function pcl_read(string $link,string $kind,bool $synthetic=true):array {
    pcl_cool();return provider_php_controls_read_client(1,$link,$kind,'pcl_dns',$synthetic?'pcl_transport':null);
}
function pcl_review(string $link,array $current,array $options,string $desired='php84'):array {return provider_php_controls_review_client(1,$link,$current['id'],$options['id'],$desired);}
function pcl_change(string $id,string $link,array $current,array $options,array $review,string $desired='php84',string $password=PCL_CLIENT_PASSWORD):array {
    return provider_php_controls_change_client(1,$id,$link,$current['id'],$options['id'],$desired,$review['review_sha256'],true,$password,'pcl_dns','pcl_perform');
}
function pcl_activity():array {global $dns,$reads,$optionsCalls,$writes;return [$dns,$reads,$optionsCalls,$writes,pcl_count('provider_php_changes'),pcl_count('provider_operations'),pcl_count('provider_operation_grants')];}
function pcl_fault(string $kind,callable $work,?string $witnessTable=null):bool {
    if(!in_array($kind,['started','prepared','request','terminal','check','result'],true))throw new LogicException('Unknown lifecycle audit fault.');
    if($witnessTable!==null&&!in_array($witnessTable,['provider_php_changes','provider_php_dispatch_ends','provider_php_change_checks','provider_php_control_read_results'],true))throw new LogicException('Unknown fault witness.');
    $sample=$witnessTable===null?'':'SET @phplc_fault_rows=(SELECT COUNT(*) FROM '.table($witnessTable).'); ';
    db()->exec('CREATE TRIGGER phplc_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.php_control_lifecycle_".$kind."' THEN SET @phplc_fault_hits=COALESCE(@phplc_fault_hits,0)+1; ".$sample."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic lifecycle audit fault'; END IF; END");
    $beforeRows=$witnessTable===null?0:pcl_count($witnessTable);query('SET @phplc_fault_hits=0,@phplc_fault_rows=0');$hit=false;
    try{$work();}catch(PDOException $e){$hit=$e->getCode()==='45000'&&(int)query('SELECT @phplc_fault_hits')->fetchColumn()===1&&($witnessTable===null||(int)query('SELECT @phplc_fault_rows')->fetchColumn()===$beforeRows+1);}
    finally{db()->exec('DROP TRIGGER phplc_fault');}return $hit;
}
function pcl_damage(string $name,callable $mutate):void {
    $before=pcl_digest();staff_locked(function()use($name,$mutate):void{db()->beginTransaction();try{$mutate();pcl_deny($name,fn()=>provider_php_controls_lifecycle_ready());}finally{if(db()->inTransaction())db()->rollBack();}});
    pcl_ok($name.' restores exact rows',pcl_digest()===$before&&pcl_ready());
}
/** Current identity fault, restored exactly; never edits an immutable witness or clock. */
function pcl_identity_probe(string $name,string $table,string $column,int $id,string $replacement,callable $attempt):void {
    if(!in_array($table.'.'.$column,['users.auth_version','studio_clients.auth_version','staff_profiles.role'],true))throw new LogicException('Unexpected identity probe.');
    $key=$table==='staff_profiles'?'user_id':'id';$old=one('SELECT '.$column.' FROM '.table($table).' WHERE '.$key.'=?',[$id])[$column];$before=pcl_digest();
    pcl_scope(fn()=>query('UPDATE '.table($table).' SET '.$column.'=? WHERE '.$key.'=?',[$replacement,$id]));
    try{pcl_deny($name,$attempt);}finally{pcl_scope(fn()=>query('UPDATE '.table($table).' SET '.$column.'=? WHERE '.$key.'=?',[$old,$id]));pcl_client();}
    pcl_ok($name.' restores exact identity rows',pcl_digest()===$before);
}
function pcl_child(array $input):array {
    $process=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('Could not start lifecycle contender.');
    fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    $out='';$err='';$deadline=microtime(true)+18;$exit=null;
    try{do{$out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);if(strlen($out)>8192||strlen($err)>8192)throw new RuntimeException('Worker output bound.');$status=proc_get_status($process);if(!$status['running']){$exit=$status['exitcode'];break;}if(microtime(true)>$deadline)throw new RuntimeException('Worker deadline.');usleep(20000);}while(true);$out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);}
    finally{$status=proc_get_status($process);if($status['running'])proc_terminate($process);fclose($pipes[1]);fclose($pipes[2]);$closed=proc_close($process);if($exit===null)$exit=$closed;}
    if($exit!==0||$err!==''||strlen($out)>8192)throw new RuntimeException('Lifecycle contender failed.');return json_decode($out,true,8,JSON_THROW_ON_ERROR);
}
if($worker){
    try{$input=json_decode(stream_get_contents(STDIN,4097),true,8,JSON_THROW_ON_ERROR);
        if(!is_array($input)||array_keys($input)!==['request','link','current','options','review','owner_connection','expect_block']||!is_int($input['owner_connection'])||!is_bool($input['expect_block']))exit(2);
        foreach(['request','link','current','options'] as $k)provider_php_controls_lifecycle_id($input[$k]);provider_php_controls_lifecycle_hash($input['review']);
        pcl_client();$key='cwp-staff-'.substr(hash('sha256','cwp_test|'.PCL_PREFIX),0,40);
        $other=(int)query('SELECT CONNECTION_ID()')->fetchColumn()!==$input['owner_connection'];$owns=(int)query('SELECT IS_USED_LOCK(?)',[$key])->fetchColumn()===$input['owner_connection'];$before=pcl_digest();$blocked=false;$summary=null;
        try{$summary=provider_php_controls_change_client(1,$input['request'],$input['link'],$input['current'],$input['options'],'php84',$input['review'],true,PCL_CLIENT_PASSWORD,
            static function():never{throw new LogicException('Contender reached DNS.');},static function():never{throw new LogicException('Contender reached gateway.');});}
        catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$blocked=$e->getMessage()==='Another account change is in progress. Please retry.';if(!$blocked)throw $e;}
        $unchanged=pcl_digest()===$before;$ok=$other&&$unchanged&&($input['expect_block']?($owns&&$blocked):(!$blocked&&($summary['id']??null)===$input['request']));
        echo json_encode(['passed'=>$ok,'different_connection'=>$other,'parent_owns_lock'=>$owns,'blocked'=>$blocked,'unchanged'=>$unchanged,'replayed_id'=>$summary['id']??null]);exit($ok?0:1);
    }catch(Throwable $e){fwrite(STDERR,'Lifecycle contender refused: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine().PHP_EOL);exit(1);}
}

try {
    pcl_ok('Fresh disposable lifecycle namespace is empty',query('SELECT DATABASE()')->fetchColumn()==='cwp_test'&&!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? LIMIT 1',[strlen(PCL_PREFIX),PCL_PREFIX]));
    $stage='prerequisites';$base=schema_statements(PCL_PREFIX);
    foreach(['settings','users','audit','rate_limits','studio_clients','staff_profiles'] as $name)db()->exec($base[$name]);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','2');
    foreach([1,2,3] as $id)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['PHP lifecycle staff '.$id,'php-lifecycle-staff'.$id.'@example.invalid',password_hash(PCL_PASSWORD,PASSWORD_DEFAULT),str_repeat(dechex($id+9),32),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,'[]'),(3,'owner',1,'[]')");
    foreach([1,2] as $id)query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['PHP lifecycle client '.$id,'php-lifecycle-client'.$id.'@example.invalid',password_hash(PCL_CLIENT_PASSWORD,PASSWORD_DEFAULT),str_repeat(dechex($id+12),32),gmdate('Y-m-d H:i:s')]);
    pcl_owner();provider_settings_migrate(PCL_PASSWORD);provider_inventory_migrate(PCL_PASSWORD);provider_php_prepare(PCL_PASSWORD);provider_operations_migrate(PCL_PASSWORD);provider_php_controls_prepare(PCL_PASSWORD);
    $profile=provider_settings_save('',0,'Synthetic PHP lifecycle',true,PCL_SECRET,PCL_PASSWORD);
    pcl_ok('Synthetic vault uses actual POSIX protected storage',(fileperms($private.'/provider-master.key')&0777)===0600);
    pcl_inventory($profile);provider_inventory_link($profile,701,1,1,PCL_PASSWORD);provider_inventory_link($profile,702,2,1,PCL_PASSWORD);pcl_inventory($profile);
    $link=one('SELECT id FROM '.table('provider_package_links').' WHERE package_id=701')['id'];$other=one('SELECT id FROM '.table('provider_package_links').' WHERE package_id=702')['id'];
    $permission=pcl_delegate($link,true);
    pcl_cool();$legacyBinding=pcl_scope(fn()=>provider_php_binding($link));$legacy=provider_php_refresh($link,$legacyBinding,PCL_PASSWORD,'pcl_dns','pcl_legacy_transport');
    provider_php_share($link,'',bin2hex(random_bytes(16)),true,$legacy['id'],PCL_PASSWORD);
    $legacyProjection=pcl_scope(fn()=>provider_php_project($link,true));
    pcl_ok('Original shared scalar is current before a change',$legacy['state']==='complete'&&$legacyProjection['status']==='observed'&&$legacyProjection['rows'][0]['current']===true);
    $stage='lifecycle preparation';$before=pcl_digest();pcl_ok('Unprepared lifecycle read is local and creates nothing',!pcl_ready()&&pcl_digest()===$before);
    pcl_deny('Lifecycle setup rejects wrong owner password',fn()=>provider_php_controls_lifecycle_prepare('wrong'));pcl_owner(2);pcl_deny('Lifecycle setup rejects non-owner staff',fn()=>provider_php_controls_lifecycle_prepare(PCL_PASSWORD));pcl_owner();
    db()->beginTransaction();pcl_deny('Lifecycle setup rejects inherited transaction',fn()=>provider_php_controls_lifecycle_prepare(PCL_PASSWORD));pcl_ok('Setup refusal leaves caller transaction intact',db()->inTransaction());db()->rollBack();
    $before=pcl_digest();pcl_ok('Start audit failure rolls back explicit lifecycle marker',pcl_fault('started',fn()=>provider_php_controls_lifecycle_prepare(PCL_PASSWORD))&&pcl_digest()===$before&&!pcl_ready());
    pcl_ok('Prepared audit failure is reached after real lifecycle DDL',pcl_fault('prepared',fn()=>provider_php_controls_lifecycle_prepare(PCL_PASSWORD)));
    pcl_ok('Interrupted setup retains only preparing marker',one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_php_control_lifecycle_schema_version'])['value']==='preparing-1'&&!one('SELECT id FROM '.table('audit').' WHERE action=?',['provider.php_control_lifecycle_prepared']));
    pcl_deny('Preparing lifecycle refuses application readiness',fn()=>pcl_ready());
    db()->exec('ALTER TABLE '.table('provider_php_control_reads').' ADD synthetic_extra INT NULL');
    try{pcl_deny('Explicit setup refuses incompatible partial table',fn()=>provider_php_controls_lifecycle_prepare(PCL_PASSWORD));}finally{db()->exec('ALTER TABLE '.table('provider_php_control_reads').' DROP COLUMN synthetic_extra');}
    provider_php_controls_lifecycle_prepare(PCL_PASSWORD);$before=pcl_digest();provider_php_controls_lifecycle_prepare(PCL_PASSWORD);
    pcl_ok('Exact empty partial lifecycle resumes once',pcl_ready()&&pcl_digest()===$before);
    pcl_damage('Unknown lifecycle marker refuses',fn()=>save_setting('provider_php_control_lifecycle_schema_version','2'));
    pcl_damage('Unmarked surviving lifecycle schema refuses adoption',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_php_control_lifecycle_schema_version']));
    pcl_damage('Missing lifecycle preparation witness refuses',fn()=>query('DELETE FROM '.table('audit').' WHERE action=?',['provider.php_control_lifecycle_prepared']));

    $stage='authorized reads';pcl_client();pcl_ok('Primary client session has no owner identity',admin_user()===null);
    $before=[$dns,$reads,$optionsCalls];$unavailable=pcl_read($link,'available',false);
    pcl_ok('Default unknown choices retain unavailable without DNS or transport',$unavailable['kind']==='available'&&$unavailable['state']==='unavailable'&&$unavailable['reason']==='unexpected_response'&&[$dns,$reads,$optionsCalls]===$before);
    $current=pcl_read($link,'current');$options=pcl_read($link,'available');$review=pcl_review($link,$current,$options);
    pcl_ok('Actual scalar and synthetic typed choices produce an exact review',$current['state']==='complete'&&$options['state']==='complete'&&$review['current_read_id']===$current['id']&&$review['options_read_id']===$options['id']&&$review['before_value']==='php82'&&$review['desired_value']==='php84');
    $readRecord=pcl_get('read',$current['id']);$readAudit=one('SELECT user_id FROM '.table('audit').' WHERE id=?',[$readRecord['audit_id']]);
    pcl_ok('Client read has truthful client actor and independent audit',$readRecord['row']['actor_staff_id']===null&&(int)$readRecord['row']['actor_client_id']===1&&$readRecord['document']['context']['actor']['kind']==='client'&&(int)$readAudit['user_id']===0&&pcl_count('provider_operation_grants')===0);
    $before=pcl_activity();pcl_deny('Immediate next read honors actual profile cooldown',fn()=>provider_php_controls_read_client(1,$link,'current','pcl_dns','pcl_transport'));pcl_ok('Cooldown refusal has no outbound or operation work',pcl_activity()===$before);
    $id=bin2hex(random_bytes(16));$before=pcl_activity();
    pcl_deny('Unlisted choice refuses review',fn()=>pcl_review($link,$current,$options,'unlisted'));
    pcl_deny('Current value refuses no-op review',fn()=>pcl_review($link,$current,$options,'php82'));
    pcl_deny('Changed review hash refuses send',fn()=>pcl_change($id,$link,$current,$options,array_replace($review,['review_sha256'=>str_repeat('0',64)])));
    pcl_deny('Wrong actual client password refuses send',fn()=>pcl_change($id,$link,$current,$options,$review,password:'wrong'));
    pcl_client(2);pcl_deny('Other primary session cannot act as the original client',fn()=>pcl_change($id,$link,$current,$options,$review));pcl_client();
    pcl_deny('Foreign assigned package cannot use original review',fn()=>pcl_change($id,$other,$current,$options,$review));
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;pcl_deny('Contact marker cannot become primary change '.$key,fn()=>pcl_change($id,$link,$current,$options,$review));pcl_ok('Contact marker remains explicit '.$key,array_key_exists($key,$_SESSION));unset($_SESSION[$key]);}
    $_GET['support']='';pcl_deny('Support selector cannot authorize client send',fn()=>pcl_change($id,$link,$current,$options,$review));$_GET=[];
    pcl_identity_probe('Changed primary auth token refuses send','studio_clients','auth_version',1,str_repeat('f',64),fn()=>pcl_change($id,$link,$current,$options,$review));
    pcl_identity_probe('Changed delegating owner auth token refuses send','users','auth_version',1,str_repeat('e',64),fn()=>pcl_change($id,$link,$current,$options,$review));
    pcl_identity_probe('Demoted delegating owner refuses send','staff_profiles','role',1,'administrator',fn()=>pcl_change($id,$link,$current,$options,$review));
    pcl_ok('Pre-send negative batch performs no outbound or approval work',pcl_activity()===$before);
    $failed=pcl_read($link,'available',false);$before=pcl_activity();pcl_deny('Failed newest options block older successful review',fn()=>pcl_change($id,$link,$current,$options,$review));pcl_ok('Failed-newest refusal retains history without send',pcl_activity()===$before&&$failed['state']==='unavailable');
    pcl_cool();$resultsBefore=pcl_count('provider_php_control_read_results');
    pcl_ok('Result audit failure follows actual INSERT and retains only the read intent',pcl_fault('result',fn()=>provider_php_controls_read_client(1,$link,'available','pcl_dns','pcl_transport'),'provider_php_control_read_results')&&pcl_count('provider_php_control_read_results')===$resultsBefore);
    $unfinished=pcl_scope(fn()=>provider_php_controls_lifecycle_latest($link,'available'));$before=pcl_activity();
    pcl_ok('Interrupted newest read is retained without invented result',$unfinished['read']['document']['id']!==$options['id']&&$unfinished['result']===null&&pcl_ready());
    pcl_deny('Unfinished newest options also block older successful review',fn()=>pcl_change($id,$link,$current,$options,$review));pcl_ok('Unfinished-read refusal never sends',pcl_activity()===$before);
    $options=pcl_read($link,'available');$review=pcl_review($link,$current,$options);
    $before=pcl_digest();$work=pcl_activity();pcl_ok('Actual request-audit fault follows INSERT then rolls back all local work',pcl_fault('request',fn()=>pcl_change($id,$link,$current,$options,$review),'provider_php_changes')&&pcl_digest()===$before&&pcl_activity()===$work);

    $stage='single send';$contender=['request'=>$id,'link'=>$link,'current'=>$current['id'],'options'=>$options['id'],'review'=>$review['review_sha256'],'owner_connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn(),'expect_block'=>true];
    $duringWrite=function()use($contender,$id,$profile):void{
        $claim=pcl_scope(fn()=>provider_php_controls_lifecycle_ledger_snapshot($id));
        pcl_ok('Real gateway claim remains unresolved during transport',$claim['status']==='claimed'&&$claim['attempts']===0&&pcl_scope(fn()=>provider_php_controls_lifecycle_pending($profile,701))!==null);
        $r=pcl_child($contender);pcl_ok('Second actual connection is blocked before DNS or send',$r['passed']===true&&$r['different_connection']===true&&$r['parent_owns_lock']===true&&$r['blocked']===true&&$r['unchanged']===true);
    };
    $summary=pcl_change($id,$link,$current,$options,$review);$duringWrite=null;
    pcl_ok('One exact setter call retains uncertainty after HTTP success',$writes===1&&$wire[0]===['method'=>'POST','url'=>'https://api.20i.com/package/701/web/phpVersion','body'=>'{"value":"php84"}']&&$summary['id']===$id&&$summary['phase']==='attempted'&&$summary['readback']==='pending');
    $request=pcl_get('request',$id);$terminal=pcl_get('terminal',$id);$ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['phpversion:'.$id]);
    pcl_ok('Gateway ledger proves exactly one uncertain attempt without generic grant',(int)$ledger['attempts']===1&&$ledger['status']==='uncertain'&&$ledger['operation']==='packages.phpversion'&&pcl_count('provider_operation_grants')===0&&$request['document']['review']['context']['actor']['kind']==='client'&&$terminal['document']['recorded_by']['kind']==='client');
    $history=pcl_scope(fn()=>provider_php_project($link,true));
    pcl_ok('Pending change keeps the original shared scalar historical',$history['status']==='stale'&&$history['rows'][0]['value']==='php82'&&$history['rows'][0]['current']===false);
    $before=pcl_activity();$again=pcl_change($id,$link,$current,$options,$review);pcl_ok('Exact request replay returns original summary with no send',$again===$summary&&pcl_activity()===$before);
    pcl_deny('Reused request cannot change desired payload',fn()=>pcl_change($id,$link,$current,$options,$review,'php82'));
    pcl_deny('Fresh nonce cannot bypass unresolved package fence',fn()=>pcl_change(bin2hex(random_bytes(16)),$link,$current,$options,$review));pcl_ok('Rejected replays make no outbound work',pcl_activity()===$before);
    $contender['expect_block']=false;$r=pcl_child($contender);pcl_ok('After mutex release separate client worker replays without a send',$r['passed']===true&&!$r['blocked']&&$r['replayed_id']===$id);
    $stage='separate readback';$currentValue='php82';pcl_cool();$different=provider_php_controls_verify_client(1,$id,PCL_CLIENT_PASSWORD,'pcl_dns','pcl_transport');
    pcl_ok('Later different scalar retains unresolved request',$different['readback']==='different'&&$different['observed_value']==='php82'&&pcl_scope(fn()=>provider_php_controls_lifecycle_pending($profile,701))!==null&&$writes===1);
    $currentValue='php84';pcl_cool();$observed=provider_php_controls_verify_client(1,$id,PCL_CLIENT_PASSWORD,'pcl_dns','pcl_transport');
    pcl_ok('Later matching scalar records observed without rewriting original ledger',$observed['readback']==='observed'&&$observed['observed_value']==='php84'&&pcl_scope(fn()=>provider_php_controls_lifecycle_pending($profile,701))===null&&one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['phpversion:'.$id])===$ledger&&$writes===1);
    $history=pcl_scope(fn()=>provider_php_project($link,true));
    pcl_ok('Resolving change does not revive an older shared scalar',$history['rows'][0]['current']===false&&$history['rows'][0]['value']==='php82');
    pcl_ok('Same-second legacy observation remains conservatively historical',pcl_scope(fn()=>provider_php_controls_observation_current($link,$terminal['document']['recorded_epoch']))===false);

    $stage='post-send audit failure';$currentValue='php82';$current2=pcl_read($link,'current');$options2=pcl_read($link,'available');$review2=pcl_review($link,$current2,$options2);$id2=bin2hex(random_bytes(16));$writeMode='timeout';$beforeWrites=$writes;$terminalCount=pcl_count('provider_php_dispatch_ends');
    pcl_ok('Actual terminal audit failure occurs after one timeout send',pcl_fault('terminal',fn()=>pcl_change($id2,$link,$current2,$options2,$review2),'provider_php_dispatch_ends')&&$writes===$beforeWrites+1);
    $ledger2=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['phpversion:'.$id2]);
    pcl_ok('Post-send failure retains request and ledger without false terminal',pcl_get('request',$id2)['document']['id']===$id2&&!one('SELECT request_id FROM '.table('provider_php_dispatch_ends').' WHERE request_id=?',[$id2])&&pcl_count('provider_php_dispatch_ends')===$terminalCount&&(int)$ledger2['attempts']===1&&pcl_ready());
    $before=pcl_activity();pcl_change($id2,$link,$current2,$options2,$review2);pcl_deny('Interrupted request cannot be verified before local recovery',fn()=>provider_php_controls_verify_client(1,$id2,PCL_CLIENT_PASSWORD,'pcl_dns','pcl_transport'));pcl_ok('Interrupted replay never resumes send',pcl_activity()===$before);
    pcl_owner();$recovery=provider_php_controls_recovery_review_owner($id2);pcl_ok('Finished one-attempt ledger offers exact local recovery',$recovery['ready']===true&&$recovery['request_id']===$id2&&preg_match('/\A[a-f0-9]{64}\z/',$recovery['ledger_sha256'])===1);
    $before=pcl_activity();$recovered=provider_php_controls_recover_owner($id2,$recovery['ledger_sha256'],PCL_PASSWORD);$restored=pcl_get('terminal',$id2);
    pcl_ok('Owner restores only terminal with truthful distinct recorder',$recovered['recovered_from_ledger']===true&&$restored['document']['request_actor']['kind']==='client'&&$restored['document']['recorded_by']['kind']==='staff'&&pcl_activity()===$before&&one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['phpversion:'.$id2])===$ledger2);
    $currentValue='php84';pcl_cool();$ownerObserved=provider_php_controls_verify_owner($id2,PCL_PASSWORD,'pcl_dns','pcl_transport');
    pcl_ok('Independent owner readback preserves original client requester',$ownerObserved['readback']==='observed'&&pcl_get('request',$id2)['document']['review']['context']['actor']['kind']==='client'&&$writes===2);
    $before=pcl_activity();$binding=pcl_scope(fn()=>provider_php_binding($link));
    pcl_deny('Old scalar refresh honors new lifecycle read cooldown',fn()=>provider_php_refresh($link,$binding,PCL_PASSWORD,'pcl_dns','pcl_legacy_transport'));
    pcl_ok('Legacy quota refusal has no outbound or ledger work',pcl_activity()===$before);
    pcl_cool();$newLegacy=provider_php_refresh($link,$binding,PCL_PASSWORD,'pcl_dns','pcl_legacy_transport');$history=pcl_scope(fn()=>provider_php_project($link,true));
    pcl_ok('Genuine later legacy scalar becomes current after resolution',$newLegacy['state']==='complete'&&$history['status']==='observed'&&$history['rows'][0]['current']===true&&$history['rows'][0]['value']==='php84');

    $stage='retained integrity';
    pcl_damage('Lost independent result counter refuses',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_php_control_lifecycle_result_count']));
    pcl_damage('Lost retained result refuses referenced request',fn()=>query('DELETE FROM '.table('provider_php_control_read_results').' WHERE read_id=?',[$current['id']]));
    pcl_damage('Changed canonical request proof refuses',fn()=>query('UPDATE '.table('provider_php_changes').' SET proof=? WHERE id=?',[str_repeat('0',64),$id]));
    pcl_damage('Lost terminal audit refuses existing record',fn()=>query('DELETE FROM '.table('audit').' WHERE action=? AND detail LIKE ?',['provider.php_control_lifecycle_terminal','PHP lifecycle terminal '.$id.' %']));
    pcl_damage('Lost check history refuses its independent count',fn()=>query('DELETE FROM '.table('provider_php_change_checks').' WHERE request_id=?',[$id]));
    pcl_damage('Unknown nonempty preparing state refuses',fn()=>save_setting('provider_php_control_lifecycle_schema_version','preparing-1'));
    $stage='missing original ledger';pcl_client();$currentValue='php82';$current3=pcl_read($link,'current');$options3=pcl_read($link,'available');$review3=pcl_review($link,$current3,$options3);$id3=bin2hex(random_bytes(16));$beforeWrites=$writes;
    $missing=provider_php_controls_change_client(1,$id3,$link,$current3['id'],$options3['id'],'php84',$review3['review_sha256'],true,PCL_CLIENT_PASSWORD,'pcl_dns',static function():never{throw new RuntimeException('Synthetic adapter-entry interruption before claim.');});
    pcl_ok('Adapter-entry interruption cannot invent a finished original ledger',$missing['phase']==='attempted'&&$missing['readback']==='pending'&&$writes===$beforeWrites&&!one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['phpversion:'.$id3])&&pcl_scope(fn()=>provider_php_controls_lifecycle_pending($profile,701))!==null);
    $before=pcl_activity();pcl_change($id3,$link,$current3,$options3,$review3);
    pcl_deny('Missing ledger cannot be verified into release',fn()=>provider_php_controls_verify_client(1,$id3,PCL_CLIENT_PASSWORD,'pcl_dns','pcl_transport'));
    pcl_owner();$notRecoverable=provider_php_controls_recovery_review_owner($id3);
    pcl_ok('Missing ledger never offers recovery evidence',$notRecoverable['ready']===false&&$notRecoverable['ledger_sha256']===null);
    pcl_deny('Owner cannot manufacture a missing ledger terminal',fn()=>provider_php_controls_recover_owner($id3,str_repeat('0',64),PCL_PASSWORD));
    pcl_ok('Missing-ledger replay and recovery perform no additional work',pcl_activity()===$before);
    $history=pcl_scope(fn()=>provider_php_project($link,true));pcl_ok('Unresolved later request hides prior current observation',$history['rows'][0]['current']===false);
    $stage='current authority';pcl_owner();pcl_delegate($link,false);pcl_client();$before=pcl_activity();
    pcl_deny('Revoked client cannot replay original request',fn()=>pcl_change($id,$link,$current,$options,$review));pcl_deny('Revoked client cannot verify its old request',fn()=>provider_php_controls_verify_client(1,$id2,PCL_CLIENT_PASSWORD,'pcl_dns','pcl_transport'));pcl_ok('Revoked client performs no outbound work',pcl_activity()===$before);
    pcl_owner();provider_settings_save($profile,1,'Changed synthetic PHP lifecycle',true,'SYNTHETIC_REPLACEMENT_LIFECYCLE_KEY',PCL_PASSWORD);$before=pcl_activity();
    pcl_deny('Changed credential cannot verify an original request',fn()=>provider_php_controls_verify_owner($id2,PCL_PASSWORD,'pcl_dns','pcl_transport'));pcl_ok('Changed credential denial preserves recorded sends',pcl_activity()===$before);
    $public=json_encode([all('SELECT * FROM '.table('provider_php_changes')),all('SELECT * FROM '.table('provider_php_control_reads')),all('SELECT * FROM '.table('provider_php_control_read_results')),all('SELECT * FROM '.table('provider_php_dispatch_ends')),all('SELECT * FROM '.table('provider_php_change_checks')),all('SELECT * FROM '.table('audit'))],JSON_THROW_ON_ERROR);
    pcl_ok('Retained lifecycle and audit expose no raw key or password',!str_contains($public,PCL_SECRET)&&!str_contains($public,PCL_PASSWORD)&&!str_contains($public,PCL_CLIENT_PASSWORD));
    pcl_ok('Historical lifecycle remains ready after current credential changes',pcl_ready());
}catch(Throwable $e){$checks[]=['name'=>'Unexpected native PHP lifecycle failure','passed'=>false];$failure=['stage'=>$stage,'class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()];fwrite(STDERR,json_encode($failure,JSON_THROW_ON_ERROR).PHP_EOL);}
$passed=count($checks)>0&&count(array_filter($checks,fn($row)=>$row['passed']!==true))===0;
$result=['passed'=>$passed,'scope'=>'Actual disposable SQL, primary-client/owner sessions, mutex, real gateway and ledger with synthetic provider transport and synthetic normalized options. No live provider, options wire-schema, HTTP, browser or compatibility acceptance.','checks'=>$checks,'failure'=>$failure??null,'transport_counts'=>['dns'=>$dns,'scalar_get'=>$reads,'synthetic_options'=>$optionsCalls,'setter_post'=>$writes]];
$bytes=json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";$handle=fopen($report,'xb');if(!$handle)exit(1);$written=fwrite($handle,$bytes);fclose($handle);if($written!==strlen($bytes))exit(1);
echo ($passed?'PASS':'FAIL').': '.count($checks).' native PHP lifecycle checks'.PHP_EOL;exit($passed?0:1);
