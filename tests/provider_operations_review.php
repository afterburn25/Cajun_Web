<?php
/** Independent PR109 negative controls after its supplied native fixture; no provider transport. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require_once __DIR__.'/../site/app/providers/operations.php';
$private=realpath(__DIR__.'/../test-runtime/provider-operations-private');
if(!$private)throw new RuntimeException('Run the supplied synthetic fixture first.');
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>$private,'key'=>str_repeat('synthetic-ops-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'prop_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$u=one('SELECT * FROM '.table('users').' WHERE id=1');
$_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$password='Synthetic operations owner passphrase';$checks=[];$observations=[];
function pr109_check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];}
try{
    $profile=provider_settings_profile();
    if(!$profile||!provider_operations_ready())throw new RuntimeException('Supplied native fixture did not complete.');
    $id=$profile['id'];$operation='packages.update';$scope=['resellerId'=>'*','id'=>42,'labels'=>['stage'=>'review']];
    $fingerprint=provider_operations_scope($operation,$scope);
    $grant=provider_operation_grant($id,$operation,$scope,$password);
    pr109_check('Original owner approval is active for the exact current scope',provider_operation_grant_active($id,$operation,$fingerprint));

    provider_settings_state($id,(int)provider_settings_load($id)['revision'],'disable',$password);
    $active=provider_operation_grant_active($id,$operation,$fingerprint);
    $observations['disabled_profile_grant_active']=$active;
    pr109_check('Disabled provider profile cannot retain an active executable approval',!$active);
    provider_settings_state($id,(int)provider_settings_load($id)['revision'],'enable',$password);
    provider_settings_save($id,(int)provider_settings_load($id)['revision'],'Replacement account fixture',true,'SYNTHETIC_DIFFERENT_GENERAL_KEY',$password);
    $newFingerprint=provider_operations_scope($operation,$scope);
    $active=provider_operation_grant_active($id,$operation,$newFingerprint);
    $observations['credential_replacement']=['scope_unchanged'=>hash_equals($fingerprint,$newFingerprint),'old_grant_active'=>$active];
    pr109_check('Replacing the configured credential requires fresh approval for that account',!$active);
    $replacement=provider_operation_grant($id,$operation,$scope,$password);
    pr109_check('Fresh explicit owner approval works after the credential replacement',provider_operation_grant_active($id,$operation,$newFingerprint));
    provider_operation_revoke($replacement,$password);
    pr109_check('Explicit revocation still refuses the replacement approval',!provider_operation_grant_active($id,$operation,$newFingerprint));

    $invalid=[];
    foreach(["\xff","\xfe"] as $key){
        try{$invalid[]=provider_operations_scope('packages.update',[$key=>'same']);}
        catch(InvalidArgumentException){$invalid[]=null;}
    }
    $observations['invalid_key_scopes']=['first_refused'=>$invalid[0]===null,'second_refused'=>$invalid[1]===null,'accepted_fingerprints_equal'=>$invalid[0]!==null&&$invalid[0]===$invalid[1]];
    pr109_check('Invalid UTF-8 parameter keys are refused instead of losing their identity',$invalid[0]===null&&$invalid[1]===null);

    $target=['resellerId'=>'*','id'=>101,'labels'=>['stage'=>'retained']];
    $targetFingerprint=provider_operations_scope($operation,$target);
    provider_operation_grant($id,$operation,$target,$password);
    pr109_check('Target scope approval starts active',provider_operation_grant_active($id,$operation,$targetFingerprint));
    for($i=0;$i<64;$i++)provider_operation_grant($id,$operation,['resellerId'=>'*','id'=>1000+$i],$password);
    $active=provider_operation_grant_active($id,$operation,$targetFingerprint);
    $observations['after_64_unrelated_scopes']=['target_approval_active'=>$active];
    pr109_check('Unrelated later scope approvals do not hide an unexpired exact grant',$active);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected fixture failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
file_put_contents(__DIR__.'/../qa/provider-operations-review-results.json',json_encode(['passed'=>$passed,'source'=>'318471ef3d2cc1fb28994efc94dfe8e0dec7b895','scope'=>'Independent synthetic MariaDB approval probes. A failed behavioral check is a review finding, not successful feature acceptance. No provider API or real credentials.','php'=>PHP_VERSION,'checks'=>$checks,'observations'=>$observations],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo ($passed?'PASS':'FINDINGS').': '.count($checks).' independent approval checks'.PHP_EOL;
exit($passed?0:1);
