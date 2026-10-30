<?php
/** Original1.25 inventory plus candidate1.26 catalog; fixed synthetic transports only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||!in_array(count($argv),[3,4],true))exit(2);
$root=realpath($argv[1]);$base=realpath(__DIR__.'/../test-runtime');
if(!$root||!$base||!str_starts_with($root,$base.DIRECTORY_SEPARATOR))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';if(config()['db']['name']!=='cwp_test'||!preg_match('/^r126_[a-z0-9]{1,8}_$/D',config()['db']['prefix']))exit(2);
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';require_once CWP_CODE_ROOT.'/app/providers/operations.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,ProviderCredential,ProviderOperation,OperationResult,ProviderGateway};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryProjector,InventorySnapshot};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$root;start_session();
$actor=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$actor['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$password='Synthetic organized upgrade passphrase';$profile=provider_settings_profile();if(!$profile)throw new RuntimeException('Save the original profile through the actual HTTP owner form first.');
$requests=0;
final class Retained126Transport implements HttpTransport {
    public function send(TransportRequest $request):TransportResponse {global $requests;$requests++;if($request->method!=='POST')throw new LogicException('Expected one synthetic label request.');return new TransportResponse(200,[],'false');}
}
if($argv[2]==='seed'){
    if(count($argv)!==3)exit(2);
    if(CWP_VERSION!=='1.25.0'||setting('provider_inventory_schema_version')!=='5')throw new RuntimeException('Seed only the original published1.25 inventory schema.');
    $read=static function(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize):InventorySnapshot {
        $authorize();if($address!=='8.8.8.8'||$secret!=='SYNTHETIC_ORIGINAL_120_GENERAL_KEY')throw new LogicException('Unexpected isolated fixture boundary.');
        $item=['id'=>101,'typeRef'=>9,'enabled'=>true,'name'=>'retained.example','names'=>['retained.example','retained-domain.example'],'created'=>'2024-01-02T03:04:05Z','packageTypeName'=>'Retained web','packageLabels'=>['Retained label'],'stackUsers'=>[],'productSpec'=>new stdClass()];
        return InventoryProjector::packagesList(new TransportResponse(200,[],json_encode([$item],JSON_THROW_ON_ERROR)),time(),'twenty-i|'.$row['id'].'|packages.list');
    };
    if(provider_inventory_refresh($profile['id'],(int)$profile['revision'],$password,fn()=>'8.8.8.8',$read)!=='complete')throw new RuntimeException('Original inventory fixture failed.');
    provider_inventory_link($profile['id'],101,1,(int)$profile['revision'],$password);
    provider_inventory_domain_link($profile['id'],101,'retained-domain.example',1,(int)$profile['revision'],$password);
    $grant=provider_operation_grant($profile['id'],'packages.update',['resellerId'=>'*','id'=>[101],'labels'=>[101=>'Retained label']],$password);
    $perform=static function(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize,ProviderOperation $operation):OperationResult {
        if($address!=='8.8.8.8'||$secret!=='SYNTHETIC_ORIGINAL_120_GENERAL_KEY')throw new LogicException('Unexpected isolated fixture boundary.');
        $identity=$row['id'].':'.(int)$row['revision'].':'.provider_operations_credential_fingerprint($secret);
        $credential=new ProviderCredential('twenty-i',$identity,static function()use($secret,$authorize):string{$authorize();return $secret;});
        return (new ProviderGateway(new TwentyIOperationAdapter(),new Retained126Transport(),new ProviderOperationLedger()))->perform($credential,$operation);
    };
    $result=provider_operations_execute($profile['id'],(int)$profile['revision'],$grant,'retained126-label',$password,fn()=>'8.8.8.8',$perform);
    if($result->status!=='uncertain'||$requests!==1)throw new RuntimeException('Unknown original operation was not retained.');
    echo json_encode(['seeded'=>true,'synthetic_posts'=>$requests],JSON_THROW_ON_ERROR);exit;
}
if($argv[2]==='replay'){
    if(count($argv)!==3)exit(2);
    $grant=one('SELECT id FROM '.table('provider_operation_grants').' WHERE consumed_key=?',['retained126-label']);if(!$grant)throw new RuntimeException('Original consumed approval missing.');
    $resolver=static function()use(&$requests):string{$requests++;throw new LogicException('Recovered request must not resolve or send.');};
    $perform=static function()use(&$requests):OperationResult{$requests++;throw new LogicException('Recovered request must not send.');};
    $result=provider_operations_execute($profile['id'],(int)$profile['revision'],$grant['id'],'retained126-label',$password,$resolver,$perform);
    echo json_encode(['network_calls'=>$requests,'status'=>$result->status],JSON_THROW_ON_ERROR);exit;
}

// Candidate-only modes are appended below; no other action is admitted.
if(!in_array($argv[2],['catalog-read','catalog-state','catalog-replay'],true))exit(2);
if(count($argv)!==4||!preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/',$argv[3])||trim(file_get_contents(__DIR__.'/../VERSION'))!==$argv[3]||CWP_VERSION!==$argv[3])throw new RuntimeException('New catalog actions require actual source-bound candidate files.');
require_once CWP_CODE_ROOT.'/app/providers/catalog_import.php';
require_once CWP_CODE_ROOT.'/app/providers/package_types.php';
if(!provider_package_types_ready())throw new RuntimeException('Enable catalog storage through the real HTTP owner form first.');
if($argv[2]==='catalog-read'){
    if(provider_package_types_source($profile['id'])!==null)throw new RuntimeException('New fixture catalog must start with no prior intent.');
    // Respect the original production throttle without changing any retained row or limiter.
    $last=one('SELECT MAX(started_epoch) AS epoch FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile['id']]);
    $remaining=$last&&$last['epoch']!==null?max(0,31-(time()-(int)$last['epoch'])):0;
    if($remaining>31)throw new RuntimeException('Unexpected future fixture observation.');
    if($remaining)sleep($remaining);
    $reads=0;
    $read=static function(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize)use(&$reads):InventorySnapshot{
        $reads++;$authorize();
        if($address!=='8.8.8.8'||$secret!=='SYNTHETIC_ORIGINAL_120_GENERAL_KEY')throw new LogicException('Unexpected synthetic catalog context.');
        $body=json_encode([['id'=>9,'label'=>'Later catalog name for existing type'],['id'=>901,'label'=>'Catalog-only retained hosting']],JSON_THROW_ON_ERROR);
        return \CajunWebPro\Foundation\TwentyIInventoryReader\PackageTypes::project(new TransportResponse(200,[],$body),time(),'twenty-i|'.$row['id'].'|package.types');
    };
    $state=provider_inventory_refresh($profile['id'],(int)$profile['revision'],$password,static fn()=>'8.8.8.8',$read,'package.types');
    if($state!=='complete'||$reads!==1)throw new RuntimeException('Synthetic catalog observation did not complete exactly once.');
    $absent=(int)query('SELECT COUNT(*) FROM '.table('provider_observation_packages').' WHERE type_ref=?',['901'])->fetchColumn()===0;
    if(!$absent)throw new RuntimeException('Catalog-only fixture type unexpectedly occurs in installed inventory.');
    echo json_encode(['state'=>$state,'synthetic_reads'=>$reads,'live_requests'=>0,'catalog_only_uninstalled'=>$absent],JSON_THROW_ON_ERROR);exit;
}
$preview=provider_catalog_import_preview(1,'package.types');
if($argv[2]==='catalog-replay'){
    $id=provider_catalog_import_create($preview['hash'],'9','IGNORED-REIMPORT','year',$password,'package.types');
    echo json_encode(['existing_product_id'=>$id],JSON_THROW_ON_ERROR);exit;
}
echo json_encode(['ready'=>provider_package_types_ready(),'operation'=>$preview['source_operation'],'types'=>$preview['types']],JSON_THROW_ON_ERROR);
