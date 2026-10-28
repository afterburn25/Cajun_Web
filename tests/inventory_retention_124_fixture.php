<?php
/** Published 1.24/1.25 package/link/unknown-operation history; test-only loopback fixture. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||!in_array(count($argv),[3,4],true))exit(2);
$baseline=(string)($argv[3]??'1.24.0');
if(!in_array($baseline,['1.24.0','1.25.0'],true))exit(2);
$root=realpath($argv[1]);$base=realpath(__DIR__.'/../test-runtime');
if(!$root||!$base||!str_starts_with($root,$base.DIRECTORY_SEPARATOR))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';if(config()['db']['name']!=='cwp_test')exit(2);
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';require_once CWP_CODE_ROOT.'/app/providers/operations.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,ProviderCredential,ProviderOperation,OperationResult,ProviderGateway};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryProjector,InventorySnapshot};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$root;start_session();
$actor=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$actor['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$password='Synthetic organized upgrade passphrase';$profile=provider_settings_profile();if(!$profile)throw new RuntimeException('Save the original profile through the actual HTTP owner form first.');
$requests=0;
final class Retained124Transport implements HttpTransport {
    public function send(TransportRequest $request):TransportResponse {global $requests;$requests++;if($request->method!=='POST')throw new LogicException('Expected one synthetic label request.');return new TransportResponse(200,[],'false');}
}
if($argv[2]==='seed'){
    $expectedSchema=$baseline==='1.24.0'?'3':'5';
    if(CWP_VERSION!==$baseline||setting('provider_inventory_schema_version')!==$expectedSchema)throw new RuntimeException('Seed only the declared published baseline and its exact inventory schema.');
    $read=static function(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize):InventorySnapshot {
        $authorize();if($address!=='8.8.8.8'||$secret!=='SYNTHETIC_ORIGINAL_120_GENERAL_KEY')throw new LogicException('Unexpected isolated fixture boundary.');
        $item=['id'=>101,'typeRef'=>9,'enabled'=>true,'name'=>'retained.example','names'=>['retained.example','retained-domain.example'],'created'=>'2024-01-02T03:04:05Z','packageTypeName'=>'Retained web','packageLabels'=>['Retained label'],'stackUsers'=>[],'productSpec'=>new stdClass()];
        return InventoryProjector::packagesList(new TransportResponse(200,[],json_encode([$item],JSON_THROW_ON_ERROR)),time());
    };
    if(provider_inventory_refresh($profile['id'],(int)$profile['revision'],$password,fn()=>'8.8.8.8',$read)!=='complete')throw new RuntimeException('Original inventory fixture failed.');
    provider_inventory_link($profile['id'],101,1,(int)$profile['revision'],$password);
    provider_inventory_domain_link($profile['id'],101,'retained-domain.example',1,(int)$profile['revision'],$password);
    $grant=provider_operation_grant($profile['id'],'packages.update',['resellerId'=>'*','id'=>[101],'labels'=>[101=>'Retained label']],$password);
    $perform=static function(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize,ProviderOperation $operation):OperationResult {
        if($address!=='8.8.8.8'||$secret!=='SYNTHETIC_ORIGINAL_120_GENERAL_KEY')throw new LogicException('Unexpected isolated fixture boundary.');
        $identity=$row['id'].':'.(int)$row['revision'].':'.provider_operations_credential_fingerprint($secret);
        $credential=new ProviderCredential('twenty-i',$identity,static function()use($secret,$authorize):string{$authorize();return $secret;});
        return (new ProviderGateway(new TwentyIOperationAdapter(),new Retained124Transport(),new ProviderOperationLedger()))->perform($credential,$operation);
    };
    $result=provider_operations_execute($profile['id'],(int)$profile['revision'],$grant,'retained124-label',$password,fn()=>'8.8.8.8',$perform);
    if($result->status!=='uncertain'||$requests!==1)throw new RuntimeException('Unknown original operation was not retained.');
    echo json_encode(['seeded'=>true,'synthetic_posts'=>$requests],JSON_THROW_ON_ERROR);exit;
}
if($argv[2]==='replay'){
    $grant=one('SELECT id FROM '.table('provider_operation_grants').' WHERE consumed_key=?',['retained124-label']);if(!$grant)throw new RuntimeException('Original consumed approval missing.');
    $resolver=static function()use(&$requests):string{$requests++;throw new LogicException('Recovered request must not resolve or send.');};
    $perform=static function()use(&$requests):OperationResult{$requests++;throw new LogicException('Recovered request must not send.');};
    $result=provider_operations_execute($profile['id'],(int)$profile['revision'],$grant['id'],'retained124-label',$password,$resolver,$perform);
    echo json_encode(['network_calls'=>$requests,'status'=>$result->status],JSON_THROW_ON_ERROR);exit;
}
if($argv[2]!=='snapshot')exit(2);
$out=[];foreach(['provider_refreshes','provider_refresh_results','provider_observation_packages','provider_package_links','provider_package_link_events','provider_domain_links','provider_domain_link_events','provider_operations','provider_operation_grants'] as $name){
    $rows=all('SELECT * FROM '.table($name).' ORDER BY id');
    // Only 1.24 lacks this column. Keep all original 1.25 operation values in exact snapshots.
    if($baseline==='1.24.0'&&$name==='provider_refreshes')foreach($rows as &$row){if(array_key_exists('operation',$row)){if($row['operation']!==null)throw new RuntimeException('Original package observation context was rewritten.');unset($row['operation']);}}unset($row);
    $out[$name]=$rows;
}
echo json_encode($out,JSON_THROW_ON_ERROR);
