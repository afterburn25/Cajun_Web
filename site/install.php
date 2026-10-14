<?php
/** Temporary one-page setup entry; all business code lives in organized modules. */
declare(strict_types=1);
const CWP_INSTALLER_BUILD = '1.19.0';
const CWP_INSTALLER_LAYOUT = 'one-page';
ini_set('display_errors', '0');
header('Cache-Control: no-store, private, max-age=0, must-revalidate');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-CWP-Installer: one-page-'.CWP_INSTALLER_BUILD);
// lstat reads the guard's existence without executing config or following links.
function cwp_installer_installed(): bool {
    foreach (['config.php', '.installed.php', 'storage/install.lock.php'] as $name) {
        $path = __DIR__ . '/' . $name;
        clearstatcache(true, $path);
        if (@lstat($path) !== false) return true;
    }
    return false;
}
function cwp_installer_stop(string $heading, string $message, int $status): never {
    http_response_code($status);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['cwp_check'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false, 'installer_build'=>CWP_INSTALLER_BUILD,
            'key_required'=>false, 'message'=>$message], JSON_UNESCAPED_SLASHES);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'");
        $h = htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $m = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="images/icons/favicon.png?v='.CWP_INSTALLER_BUILD.'"><title>CajunWebPro · Setup '.CWP_INSTALLER_BUILD.'</title><body style="background:#031021;color:#eaf2ff;font:17px/1.65 system-ui;padding:8vw;max-width:900px"><p style="color:#f6cd74">CAJUNWEBPRO · KEY-FREE SETUP '.CWP_INSTALLER_BUILD.'</p><h1>'.$h.'</h1><p>'.$m.'</p></body></html>';
    }
    exit;
}
if (cwp_installer_installed()) {
    cwp_installer_stop('This website is already installed.',
        'An installed-site configuration or lock exists. This is not an installation-key request. Keep config.php, .installed.php and storage locks intact. Sign in at admin/index.php. If a prior installation failed, back up the files and database before asking for recovery.', 403);
}
if (PHP_VERSION_ID < 80200) {
    cwp_installer_stop('PHP 8.2 or newer is required.', 'Select PHP 8.2 or newer for this domain in your hosting panel, then reopen this page.', 503);
}

foreach (['install/bootstrap.generated.php', 'install/inventory.generated.php', 'install/actions/package.php', 'install/actions/storage.php', 'install/actions/checks.php', 'install/actions/cleanup.php', 'install/actions/deploy.php', 'install/controller.php', 'install/views/form.php', 'install/views/styles.php', 'install/views/script.php'] as $required) {
    if (!is_file(__DIR__.'/'.$required) || is_link(__DIR__.'/'.$required)) cwp_installer_stop('Setup files are missing.', 'Upload the entire COMPLETE-UPLOAD package. Missing setup support: '.$required.'. Your existing configuration and files were not changed.',503);
}
define('CWP_BOOT',true);
define('CWP_SETUP',true);
define('CWP_ROOT',__DIR__);
define('CWP_CODE_ROOT',__DIR__);
require __DIR__.'/install/bootstrap.generated.php';
require __DIR__.'/install/inventory.generated.php';
require __DIR__.'/install/actions/package.php';
require __DIR__.'/install/actions/storage.php';
require __DIR__.'/install/actions/checks.php';
require __DIR__.'/install/actions/cleanup.php';
require __DIR__.'/install/actions/deploy.php';
require __DIR__.'/install/controller.php';
