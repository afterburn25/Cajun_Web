<?php
/** Separate PHP connection for actual owner revocation or a waiting customer mutation. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';
$GLOBALS['cwp_config']['db']['prefix']='swci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'POST'];
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/billing/software_charge.php';
require_once CWP_CODE_ROOT.'/app/billing/software_subscription_enroll.php';
require_once CWP_CODE_ROOT.'/app/billing/software_subscription_cancel.php';
require_once __DIR__.'/software_commerce_authority.php';
if(query('SELECT DATABASE()')->fetchColumn()!=='cwp_test')exit(2);
$input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
$connection=(int)query('SELECT CONNECTION_ID()')->fetchColumn();
if(($argv[1]??'')==='revoke'){
    $_SESSION=['uid'=>1,'auth_version'=>str_repeat('a',32),'login_at'=>time(),'last_activity'=>time()];
    $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[(int)$input['id']]);
    studio_client_save((int)$client['id'],(string)$input['expected'],(string)$client['name'],(string)$client['email'],(bool)$input['active']);
    $after=one('SELECT active,auth_version FROM '.table('studio_clients').' WHERE id=?',[(int)$client['id']]);
    echo json_encode(['connection'=>$connection,'changed'=>!hash_equals((string)$input['expected'],(string)$after['auth_version']),
        'active'=>(int)$after['active'],'transaction'=>db()->inTransaction()],JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
if(($argv[1]??'')!=='mutate')exit(2);
$_SESSION=$input['session'];$GLOBALS['swa_calls']=[];$GLOBALS['swa_gateway_id']=(int)$input['gateway_id'];
$GLOBALS['swa_provider']=$input['provider'];
$ready=$argv[2]??'';
if(!is_dir(dirname($ready))||file_exists($ready))exit(2);
file_put_contents($ready,json_encode(['connection'=>$connection],JSON_THROW_ON_ERROR));
$outcome=swa_invoke($input['case']);
echo json_encode($outcome+['connection'=>$connection,'calls'=>$GLOBALS['swa_calls'],'transaction'=>db()->inTransaction()],JSON_THROW_ON_ERROR).PHP_EOL;
