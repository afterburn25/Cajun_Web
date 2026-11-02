<?php
/** Read-only SQL bridge to a completed disposable purchase; config setup only, no business writes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$operation=$argv[1]??'';if(!in_array($operation,['prepare','state'],true))exit(2);
set_exception_handler(static function(Throwable $error)use($operation):never {
    $message=match(true){$error instanceof PDOException=>'Disposable database operation failed.',$error instanceof ErrorException=>'Disposable fixture emitted a PHP diagnostic.',$error instanceof Error=>'Disposable fixture bootstrap or call failed.',default=>'Disposable fixture precondition or application guard failed.'};
    $safe=['operation'=>$operation,'class'=>get_class($error),'message'=>$message,'file'=>basename($error->getFile()),'line'=>$error->getLine()];
    if($error instanceof PDOException){$state=$error->errorInfo[0]??null;$safe['sqlstate']=is_string($state)&&preg_match('/\A[A-Z0-9]{5}\z/',$state)?$state:null;$code=$error->errorInfo[1]??null;$safe['driver_code']=is_int($code)?$code:null;}
    fwrite(STDERR,json_encode($safe,JSON_THROW_ON_ERROR).PHP_EOL);exit(1);
});
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool {if(!(error_reporting()&$severity))return false;throw new ErrorException('Fixture PHP diagnostic',0,$severity,$file,$line);});
$fullMode=getenv('CWP_TEST_HOSTING_PURCHASE_FULL_SUITE');if(!in_array($fullMode,[false,'','1'],true))throw new RuntimeException('Unknown purchase output mode');$fullSuite=$fullMode==='1';
$portText=getenv('CWP_TEST_PORT');if(!is_string($portText)||!preg_match('/\A[1-9][0-9]{0,4}\z/',$portText)||(int)$portText>65535)throw new RuntimeException('Assigned disposable database port required');$purchasePort=(int)$portText;
if($fullSuite){$version=getenv('CWP_PHP_VERSION');if(!in_array($version,['8.2','8.3','8.4'],true)||getenv('PHP_BINARY')!=='/usr/bin/php'.$version||PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION!==$version||realpath(PHP_BINARY)!==realpath((string)getenv('PHP_BINARY')))throw new RuntimeException('Selected supported PHP runtime required');}
elseif($purchasePort!==33306)throw new RuntimeException('Original focused database port required');
$root=realpath(dirname(__DIR__));$runtime=$root.'/test-runtime/hosting-purchase';$site=$root.'/test-runtime/hosting-purchase-web/hosting-purchase';$private=$runtime.'/private';
foreach([$runtime,$site,$private,$runtime.'/browser-sessions'] as $path){if(!is_dir($path)||realpath($path)!==$path)throw new RuntimeException('Expected isolated purchase directory');for($p=$path;$p!==dirname($p);$p=dirname($p))if(is_link($p))throw new RuntimeException('Purchase paths must not contain links');}
if(!is_file($private.'/provider-master.key')||is_link($private.'/provider-master.key'))throw new RuntimeException('Original private provider identity is missing');
$report=json_decode(file_get_contents($root.($fullSuite?'/qa/hosting-purchase-due-results.json':'/qa/hosting-purchase/due-results.json')),true,64,JSON_THROW_ON_ERROR);
if(($report['passed']??null)!==true||($report['phase']??null)!=='due'||!($report['checks']??[])||array_filter($report['checks'],static fn(array $c):bool=>($c['passed']??null)!==true))throw new RuntimeException('Completed native purchase required');
$joined=$report['joined'];$state=json_decode(file_get_contents($runtime.'/state.json'),true,16,JSON_THROW_ON_ERROR);
if($joined['client_id']!==1||$joined['project_id']!==1||$joined['package_id']!==876001||$joined['order']!==str_pad(dechex(70),32,'0',STR_PAD_LEFT)||$joined['account']!==str_pad(dechex(80),32,'0',STR_PAD_LEFT)||$joined['agreement']!==$state['agreement']||$joined['mapping']!==$state['mapping_id'])throw new RuntimeException('Joined purchase identity differs');
define('CWP_BOOT',true);define('CWP_ROOT',$site);require $site.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-purchase','storage'=>$private,'key'=>str_repeat('synthetic-joined-hosting-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hpci_']];
if(config()['db']['host']!=='127.0.0.1'||config()['db']['port']!==$purchasePort)throw new RuntimeException('Selected disposable database required');
require_once CWP_CODE_ROOT.'/app/hosting/fulfillment_views.php';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
ini_set('session.save_path',$runtime.'/browser-sessions');start_session();
$owner=one('SELECT * FROM '.table('users').' WHERE id=1');
if(!$owner||$owner['email']!=='purchase-owner@example.invalid'||!password_verify('Synthetic joined hosting owner',$owner['password_hash']))throw new RuntimeException('Unexpected original owner');
foreach([1,2] as $id){$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);if(!$client||$client['email']!=='purchase-client'.$id.'@example.invalid'||!password_verify('Synthetic joined hosting client',$client['password_hash']))throw new RuntimeException('Original client bootstrap credentials required');}
$_SESSION=['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];

function hph_business():string {
    $rows=[];
    foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=? ORDER BY TABLE_NAME',['hpci_']) as $t){
        if(!preg_match('/\Ahpci_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unexpected fixture table');$name=substr($t['name'],5);
        if(in_array($name,['provider_package_links','provider_package_link_events'],true))continue;
        if(!preg_match('/\A(?:hosting_|service_|recurring_|catalog_|provider_|payment_)/',$name)&&!in_array($name,['studio_invoices','invoice_payments'],true))continue;
        $values=array_map(static fn(array $r):string=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM '.table($name)));sort($values,SORT_STRING);$rows[$name]=$values;
    }
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
function hph_full_state():string {
    $rows=[];foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=? ORDER BY TABLE_NAME',['hpci_']) as $t){if(!preg_match('/\Ahpci_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unexpected fixture table');$values=array_map(static fn(array $r):string=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM `'.$t['name'].'`'));sort($values,SORT_STRING);$rows[$t['name']]=$values;}return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
$marker=$private.'/purchase-browser-prepared';
if($operation==='prepare'){
    if(file_exists($marker)||is_link($marker)||file_exists($site.'/config.php')||is_link($site.'/config.php')||file_exists($site.'/.installed.php')||is_link($site.'/.installed.php'))throw new RuntimeException('Preserve prior browser runtime');
    $before=hph_full_state();if(!hash_equals($joined['rows_sha256'],$before))throw new RuntimeException('Completed native purchase rows changed before continuation');
    $view=hosting_fulfillment_view($joined['order'],1);
    if($view['mode']!=='active'||($view['assignment']['state']??null)!=='linked'||$view['assignment']['package_id']!=='876001')throw new RuntimeException('Original confirmed linked purchase required');
    foreach([$site.'/config.php'=>'<?php return '.var_export(config(),true).';',$site.'/.installed.php'=>CWP_GUARD.'synthetic installed guard',$marker=>'prepared'] as $path=>$bytes){$f=fopen($path,'x');if(!$f)throw new RuntimeException('Existing fixture output');try{if(fwrite($f,$bytes)!==strlen($bytes))throw new RuntimeException('Incomplete fixture write');}finally{fclose($f);}if(!chmod($path,0600))throw new RuntimeException('Cannot protect fixture file');}
    if(hph_full_state()!==$before)throw new RuntimeException('Browser preparation changed native business rows');
    echo json_encode(['prepared'=>true,'prefix'=>'hpci_','agreement'=>$joined['agreement'],'order'=>$joined['order'],'mapping'=>$joined['mapping'],'profile'=>$state['profile'],'package_id'=>876001,'domain'=>'purchase.example'],JSON_THROW_ON_ERROR);exit;
}
if(!is_file($marker)||is_link($marker))throw new RuntimeException('Prepare the browser continuation first');
$snapshot=recurring_billing_locked(function()use($state,$joined):array{
    $link=one('SELECT id,package_id,client_id FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=?',[$state['profile'],876001]);
    if($link)$link=['id'=>$link['id'],'package_id'=>(int)$link['package_id'],'client_id'=>(int)$link['client_id']];
    $events=(int)query('SELECT COUNT(*) FROM '.table('provider_package_link_events').' WHERE profile_id=? AND package_id=?',[$state['profile'],876001])->fetchColumn();
    return ['commercial_sha256'=>hph_business(),'link'=>$link,'link_events'=>$events];
});
$snapshot['assignment']=hosting_fulfillment_view($joined['order'],1)['assignment'];
echo json_encode($snapshot,JSON_THROW_ON_ERROR);
