<?php
declare(strict_types=1);
if (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }
security_headers();
header('X-CWP-Installer: one-page-'.CWP_INSTALLER_BUILD);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['cwp_check'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true, 'installer_build'=>CWP_INSTALLER_BUILD,
        'website_version'=>CWP_VERSION, 'key_required'=>false,
        'entrypoint'=>'install.php', 'scripts'=>'embedded','layout'=>CWP_INSTALLER_LAYOUT,
        'source_sha256'=>hash_file('sha256',CWP_ROOT.'/install.php'),
        'installed'=>false, 'package'=>setup_package_check()], JSON_UNESCAPED_SLASHES);
    exit;
}
start_session();
if(!isset($_SESSION['cwp_setup_fresh_token'])||!is_string($_SESSION['cwp_setup_fresh_token'])||!preg_match('/^[a-f0-9]{12}$/D',$_SESSION['cwp_setup_fresh_token']))$_SESSION['cwp_setup_fresh_token']=bin2hex(random_bytes(6));
// Clear legacy setup session data when switching installer builds. Never destroy
// administrator auth or unrelated session values. Raw passwords are not retained.
if (($_SESSION['cwp_setup_build'] ?? '') !== CWP_INSTALLER_BUILD) {
    unset($_SESSION['setup_db'], $_SESSION['setup_owner'], $_SESSION['setup_storage'],
        $_SESSION['setup_until'], $_SESSION['setup_unlocked'], $_SESSION['setup_unlocked_until']);
    $_SESSION['cwp_setup_build'] = CWP_INSTALLER_BUILD;
}
$secure = https_request() || local_request();
// Credentials stay in the current request, not in persistent wizard sessions.
unset($_SESSION['setup_db'],$_SESSION['setup_owner'],$_SESSION['setup_storage'],$_SESSION['setup_until']);
function setup_json(array $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8');echo json_encode(['installer_build'=>CWP_INSTALLER_BUILD,'key_required'=>false]+$data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit; }
if($_SERVER['REQUEST_METHOD']==='POST') {
    $handle=null;$result=null;$status=200;
    try {
        check_csrf();if(!$secure)throw new RuntimeException('Enable HTTPS before sending installation details.');
        $action=posted('action',30);
        if(!in_array($action,['checks','test_database','install'],true))throw new InvalidArgumentException('Unknown installation action.');
        if($action==='install'){
            if(!isset($_POST['confirm_install']))throw new InvalidArgumentException('Confirm the website directory and backup before installing.');
            $owner=setup_owner_input();$database=setup_database_input();
        }
        $package=setup_package_check();
        if(!$package['ok']){
            $result=setup_checks();$result['message']='Restore the listed missing or mismatched files from the complete package. Your form remains on this page.';
            setup_json($result,422);
        }
        setup_refresh_verified_php();
        // This lock serializes probes, marker creation and final installation.
        $handle=acquire_installation_mutex();clearstatcache();
        if(cwp_installer_installed())throw new RuntimeException('The website was already installed by another request. Open admin/index.php.');
        if($action==='checks')$result=setup_checks();
        elseif($action==='test_database'){$testDatabase=setup_database_input();$v=setup_database_test($testDatabase);$result=['ok'=>true,'message'=>'Connected to database '.$v.'. Table prefix: '.$testDatabase['prefix'].'. Read/write/create/drop probes passed; existing tables were not overwritten.'];}
        else {
            $checks=setup_checks();
            if(!$checks['ok']){$result=$checks+['message'=>'Installation is paused. Review the failed server checks for the cause and required action. Your form entries remain on this page.'];$status=422;}
            else{$v=setup_database_test($database);$result=setup_deploy($owner,$database,$checks['storage']);$result['checks']=$checks['checks'];$result['storage_note']=$checks['storage_note'];$result['php_uid']=$checks['php_uid'];}
        }
    }catch(Throwable $e){
        $status=422;$public=$e instanceof InvalidArgumentException||($e instanceof RuntimeException&&!($e instanceof PDOException));
        $result=['ok'=>false,'message'=>$public?$e->getMessage():'Installation could not complete. Reference: '.report_error($e).'. Check the host error log before retrying.'];
        // Read-only report also works if creation of the installation mutex was
        // blocked. It does not bypass locks or make the server appear ready.
        if(isset($action)&&$action==='checks'){
            $result['php_uid']=setup_worker_uid();$result['host_report']=setup_permission_report($result['message']);
            $result['checks']=[['label'=>'Server preparation','ok'=>false,'message'=>$result['message']]];
            $result['storage_note']='All database, account and website fields stay on this page. Resolve the permission issue in the server checks, then recheck.';
        }
    }
    finally{if(is_resource($handle)){flock($handle,LOCK_UN);fclose($handle);}}
    setup_json($result??['ok'=>false,'message'=>'No installation action was completed.'],$status);
}
$package=setup_package_check();
$path=base_path();$suggested=(https_request()?'https':'http').'://'.preg_replace('/[^A-Za-z0-9.\-:\[\]]/','',$_SERVER['HTTP_HOST']??'example.com').$path;
require CWP_ROOT.'/install/views/form.php';
