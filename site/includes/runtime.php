<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function boot(bool $session=true): void {
    security_headers();
    if(!is_file(CWP_ROOT . '/config.php')) {
        foreach(['config.php','.installed.php','storage/install.lock.php'] as $guard) {
            if(@lstat(CWP_ROOT.'/'.$guard)!==false)fail_page('An existing installation was found, but its configuration is missing or unreadable. Restore the original config.php from your backup to retain the database connection, encryption key and private-storage location. Uploading website files does not require reinstalling.');
        }
        if(is_file(CWP_ROOT . '/install.php')) { header('Location: ' . base_path() . '/install.php',true,302); exit; }
        fail_page('Installation is not complete. Upload the installer from your original package.');
    }
    $c=require CWP_ROOT . '/config.php'; if(!is_array($c) || !isset($c['db'],$c['key'],$c['site_url'])) fail_page('Configuration needs attention. Contact the site owner.'); $GLOBALS['cwp_config']=$c;
    if(!https_request() && !local_request()) { header('Location: ' . site_url(ltrim(substr((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'),strlen(base_path())),'/')) . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . str_replace(["\r","\n"], '', $_SERVER['QUERY_STRING']) : ''), true, 307); exit; }
    if($session) start_session();
    date_default_timezone_set('UTC');
    set_exception_handler(function(Throwable $e): void { $ref=report_error($e); fail_page('We could not complete this request. Please try again. Reference: ' . $ref,500); });
}
