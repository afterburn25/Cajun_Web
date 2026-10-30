<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function base_path(): string {
    if (isset($GLOBALS['cwp_config']['site_url'])) return rtrim((string)(parse_url($GLOBALS['cwp_config']['site_url'],PHP_URL_PATH) ?: ''),'/');
    // Before installation, derive the URL from the known root installer location.
    $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/install.php'));
    $file=str_replace('\\','/',(string)($_SERVER['SCRIPT_FILENAME']??CWP_ROOT.'/install.php'));
    $root=str_replace('\\','/',realpath(CWP_ROOT) ?: CWP_ROOT);
    $relative=str_starts_with($file,$root.'/')?substr($file,strlen($root)+1):'install.php';
    if (str_ends_with($script,'/'.$relative)) return rtrim(substr($script,0,-strlen($relative)),'/');
    return ''; // Never trust an arbitrary nested endpoint as the site base.
}

function config(): array { return $GLOBALS['cwp_config']; }

function site_url(string $path=''): string { return rtrim(config()['site_url'], '/') . '/' . ltrim($path,'/'); }

function page_url(string $page='home', array $params=[]): string { if ($page === 'home' && !$params) return site_url(); return site_url('index.php?' . http_build_query(['page'=>$page] + $params)); }

function admin_url(string $view='dashboard',array $params=[]): string { return site_url('admin/index.php?' . http_build_query(['view'=>$view] + $params)); }
/** Release-versioned URLs prevent an old stylesheet surviving an upgrade. */

function asset(string $path): string {
    static $paths = ['hosting-comparison.css'=>'assets/css/hosting-comparison.css','mail-notices.js'=>'assets/js/mail-notices.js','mail-notices.css'=>'assets/css/mail-notices.css','activity.js'=>'assets/js/activity.js','admin.css'=>'assets/css/admin.css','admin.js'=>'assets/js/admin.js','bayou-detail.webp'=>'images/backgrounds/bayou-detail.webp','bayou.webp'=>'images/backgrounds/bayou.webp','emblem.png'=>'images/branding/emblem.png','favicon.png'=>'images/icons/favicon.png','growth.css'=>'assets/css/growth.css','growth.js'=>'assets/js/growth.js','hero-detail.webp'=>'images/backgrounds/hero-detail.webp','hero.webp'=>'images/backgrounds/hero.webp','logo.png'=>'images/branding/logo.png','micrograin.png'=>'images/backgrounds/micrograin.png','payment-trial.js'=>'assets/js/payment-trial.js','premium.css'=>'assets/css/premium.css','project-construction.webp'=>'images/portfolio/project-construction.webp','project-placeholder.svg'=>'images/portfolio/project-placeholder.svg','project-restaurant.webp'=>'images/portfolio/project-restaurant.webp','project-wellness.webp'=>'images/portfolio/project-wellness.webp','site.css'=>'assets/css/site.css','site.js'=>'assets/js/site.js','staff-setup.js'=>'assets/js/staff-setup.js'];
    if (!isset($paths[$path])) throw new InvalidArgumentException('Unknown public asset.');
    return site_url($paths[$path]).'?v='.rawurlencode(CWP_VERSION);
}
