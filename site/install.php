<?php
/**
 * CajunWebPro browser installer 1.1.0-rc.1 — single-page installer; flat complete package; embedded bootstrap and file inventory.
 * Replace the existing install.php beside index.php with THIS file.
 * No installation-key file, input, token, activation or unlock action is used.
 * Bootstrap and branding are embedded so missing website files never hide the form.
 * Exact package integrity is checked before storage/database changes.
 * CSS and JavaScript are embedded: old installer assets cannot restore the gate.
 * Existing-site guards remain. Never delete config.php or permanent locks.
 */
declare(strict_types=1);
const CWP_INSTALLER_BUILD = '1.1.0-rc.1';
const CWP_INSTALLER_LAYOUT = 'one-page';
ini_set('display_errors', '0');
header('Cache-Control: no-store, private, max-age=0, must-revalidate');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-CWP-Installer: one-page-1.1.0-rc.1');
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
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="assets/favicon.png?v=1.1.0-rc.1"><title>CajunWebPro · Setup 1.1.0-rc.1</title><body style="background:#031021;color:#eaf2ff;font:17px/1.65 system-ui;padding:8vw;max-width:900px"><p style="color:#f6cd74">CAJUNWEBPRO · KEY-FREE SETUP 1.1.0-rc.1</p><h1>'.$h.'</h1><p>'.$m.'</p></body></html>';
    }
    exit;
}
if (cwp_installer_installed()) {
    cwp_installer_stop('This website is already installed.',
        'An installed-site configuration or lock exists. This is not an installation-key request. Keep config.php, .installed.php and storage locks intact. Sign in at admin.php. If a prior installation failed, back up the files and database before asking for recovery.', 403);
}
if (PHP_VERSION_ID < 80200) {
    cwp_installer_stop('PHP 8.2 or newer is required.', 'Select PHP 8.2 or newer for this domain in your hosting panel, then reopen this page.', 503);
}
// Embedded release bootstrap. The website uses app/core.php independently.
// Do not require an unverified or missing application file to render setup.
define('CWP_BOOT', true);
defined('CWP_BOOT') || exit;
ini_set('display_errors','0');
ini_set('log_errors','1');
const CWP_VERSION = '1.1.0-rc.1';
const CWP_GUARD = "<?php http_response_code(404); exit; ?>\n";
const CWP_ROOT = __DIR__;

function esc(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function posted(string $key, int $limit = 5000): string { $v = $_POST[$key] ?? ''; if (!is_string($v) || strlen($v) > $limit) throw new InvalidArgumentException('Please check the length and format of ' . str_replace('_',' ',$key) . '.'); return (str_contains($key,'password') || $key==='owner_confirm') ? $v : trim($v); }
function https_request(): bool {
    if ((!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    $trusted = []; $path = CWP_ROOT . '/proxy-config.php';
    if (is_file($path)) { $v = require $path; if (is_array($v)) $trusted = $v; }
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted, true) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}
function local_request(): bool { return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true) && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:[0-9]+)?$/i', $_SERVER['HTTP_HOST'] ?? '') === 1; }
function base_path(): string { $p = str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')); return $p === '/' || $p === '.' ? '' : rtrim($p, '/'); }
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1'); ini_set('session.use_trans_sid','0');
    session_name('cwp_' . substr(hash('sha256', realpath(CWP_ROOT) ?: CWP_ROOT),0,12));
    session_set_cookie_params(['lifetime'=>0,'path'=>base_path() . '/','secure'=>https_request(),'httponly'=>true,'samesite'=>'Lax']);
    if (!session_start()) fail_page('The server cannot start a secure session. Ask your host to check PHP session storage and available disk space.');
}
function security_headers(): void {
    $GLOBALS['cwp_nonce'] = base64_encode(random_bytes(18));
    header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . $GLOBALS['cwp_nonce'] . "'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('Cache-Control: no-store, private');
    if (https_request()) header('Strict-Transport-Security: max-age=31536000');
}
function nonce(): string { return esc($GLOBALS['cwp_nonce'] ?? ''); }
function csrf(): string { if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . esc(csrf()) . '">'; }
function check_csrf(): void { if (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf(),$_POST['csrf'])) throw new RuntimeException('Your session expired. Refresh the page and try again.'); }
function config(): array { return $GLOBALS['cwp_config']; }
function site_url(string $path=''): string { return rtrim(config()['site_url'], '/') . '/' . ltrim($path,'/'); }
function page_url(string $page='home', array $params=[]): string { if ($page === 'home' && !$params) return site_url(); return site_url('index.php?' . http_build_query(['page'=>$page] + $params)); }
function admin_url(string $view='dashboard',array $params=[]): string { return site_url('admin.php?' . http_build_query(['view'=>$view] + $params)); }
/** Release-versioned URLs prevent an old stylesheet surviving an upgrade. */
function asset(string $path): string {
    return site_url('assets/' . $path) . '?v=' . rawurlencode(CWP_VERSION);
}
function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }
function flash(string $text, string $kind='success'): void { $_SESSION['flash'] = [$kind, $text]; }
function show_flash(): void { if(isset($_SESSION['flash'])) { [$k,$t]=$_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="notice ' . esc($k) . '" role="status">' . esc($t) . '</div>'; } }
function db(): PDO {
    static $connection;
    if (!$connection) { $c=config()['db']; $dsn='mysql:host=' . $c['host'] . ';port=' . (int)$c['port'] . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $connection = new PDO($dsn,$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>8]); }
    return $connection;
}
function table(string $name): string { if (!preg_match('/^[a-z_]+$/D', $name)) throw new LogicException('Invalid table'); return '`' . config()['db']['prefix'] . $name . '`'; }
function query(string $sql,array $params=[]): PDOStatement { $stmt=db()->prepare($sql); $stmt->execute($params); return $stmt; }
function all(string $sql,array $params=[]): array { return query($sql,$params)->fetchAll(); }
function one(string $sql,array $params=[]): ?array { return query($sql,$params)->fetch() ?: null; }
function setting(string $key,string $default=''): string { if(!isset($GLOBALS['cwp_settings'])) $GLOBALS['cwp_settings']=array_column(all('SELECT name,value FROM ' . table('settings')),'value','name'); return (string)($GLOBALS['cwp_settings'][$key] ?? $default); }
function save_setting(string $key,string $value): void { query('INSERT INTO ' . table('settings') . ' (name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]); unset($GLOBALS['cwp_settings']); }
function log_event(string $action,string $detail=''): void { try { query('INSERT INTO ' . table('audit') . ' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[(int)($_SESSION['uid'] ?? 0),substr($action,0,80),substr($detail,0,400),gmdate('Y-m-d H:i:s')]); } catch(Throwable $e) { error_log('CWP audit write failed'); } }
function report_error(Throwable $e): string { $ref=strtoupper(bin2hex(random_bytes(4))); error_log('CWP ['.$ref.'] '.get_class($e).' code='.$e->getCode().' in '.basename($e->getFile()).':'.$e->getLine()); return $ref; }
function fail_page(string $message,int $status=503): never { http_response_code($status); echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CajunWebPro</title><body style="background:#06101f;color:#eef2f8;font:18px system-ui;padding:10vw;line-height:1.6"><h1>CajunWebPro</h1><p>' . esc($message) . '</p></body></html>'; exit; }
function boot(bool $session=true): void {
    security_headers();
    if(!is_file(CWP_ROOT . '/config.php')) { if(is_file(CWP_ROOT . '/install.php')) { header('Location: ' . base_path() . '/install.php',true,302); exit; } fail_page('Installation is not complete. Upload the installer from your original package.'); }
    $c=require CWP_ROOT . '/config.php'; if(!is_array($c) || !isset($c['db'],$c['key'],$c['site_url'])) fail_page('Configuration needs attention. Contact the site owner.'); $GLOBALS['cwp_config']=$c;
    if(!https_request() && !local_request()) { header('Location: ' . site_url(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php')) . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . str_replace(["\r","\n"], '', $_SERVER['QUERY_STRING']) : ''), true, 302); exit; }
    if($session) start_session();
    date_default_timezone_set('UTC');
    set_exception_handler(function(Throwable $e): void { $ref=report_error($e); fail_page('We could not complete this request. Please try again. Reference: ' . $ref,500); });
}
function ip_key(string $scope): string { return hash_hmac('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'),config()['key']); }
function consume_rate(string $key,int $limit,int $window): bool {
    $bucket=hash('sha256',$key.'|'.(string)floor(time()/$window));
    query('INSERT INTO '.table('rate_limits').' (bucket,attempts,expires) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=attempts+1',[$bucket,time()+$window]);
    $r=one('SELECT attempts FROM '.table('rate_limits').' WHERE bucket=?',[$bucket]);
    return (int)$r['attempts'] <= $limit;
}
function admin_user(): ?array {
    if(empty($_SESSION['uid'])) return null;
    if(time()-(int)($_SESSION['last_activity'] ?? 0)>1800 || time()-(int)($_SESSION['login_at'] ?? 0)>28800) { unset($_SESSION['uid'],$_SESSION['auth_version']); return null; }
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[(int)$_SESSION['uid']]);
    if(!$u || !hash_equals((string)$u['auth_version'],(string)($_SESSION['auth_version'] ?? ''))) { unset($_SESSION['uid']); return null; }
    $_SESSION['last_activity']=time(); return $u;
}
function require_admin(): array { $u=admin_user(); if(!$u) redirect(admin_url('login')); return $u; }
function login_user(array $u): void { session_regenerate_id(true); $_SESSION['uid']=(int)$u['id']; $_SESSION['auth_version']=(string)$u['auth_version']; $_SESSION['login_at']=$_SESSION['last_activity']=time(); $_SESSION['csrf']=bin2hex(random_bytes(32)); query('UPDATE '.table('users').' SET last_login=? WHERE id=?',[gmdate('Y-m-d H:i:s'),$u['id']]); }
function validate_password(string $password): void { if(str_contains($password,"\0") || strlen($password)<14 || strlen($password)>72) throw new InvalidArgumentException('Use a password between 14 and 72 bytes. A long, unique passphrase works well.'); }
function safe_url(string $url, bool $httpsOnly=false): string { if($url==='') return ''; $p=parse_url($url); if(!filter_var($url,FILTER_VALIDATE_URL) || !$p || !in_array(strtolower($p['scheme']??''),$httpsOnly?['https']:['https','http'],true) || isset($p['user']) || isset($p['pass']) || preg_match('/[\r\n]/',$url)) throw new InvalidArgumentException('Enter a valid ' . ($httpsOnly?'HTTPS':'HTTP or HTTPS') . ' URL.'); return $url; }
function slugify(string $text): string { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)) ?? '', '-'); }
function encrypt_secret(string $text): string { if($text==='') return ''; $iv=random_bytes(12); $key=hash('sha256',config()['key'],true); $cipher=openssl_encrypt($text,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag); if($cipher===false) throw new RuntimeException('Cannot protect the credential.'); return base64_encode($iv.$tag.$cipher); }
function decrypt_secret(string $encoded): string { if($encoded==='') return ''; $raw=base64_decode($encoded,true); if($raw===false || strlen($raw)<28) throw new RuntimeException('Invalid stored credential.'); $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',config()['key'],true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16)); if($plain===false) throw new RuntimeException('Could not decrypt the saved credential.'); return $plain; }
function image_url(array $item): string { return !empty($item['media_id']) ? site_url('media.php?id='.rawurlencode($item['media_id'])) : asset(in_array($item['cover']??'', ['project-restaurant.webp','project-construction.webp','project-wellness.webp'],true)?$item['cover']:'project-placeholder.svg'); }
function upload_image(string $field='image'): ?string {
    $file=$_FILES[$field]??null; if(!$file || ($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    if(is_array($file['error']??null) || $file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('The upload did not complete. Please try a smaller image.');
    if($file['size']>6*1024*1024) throw new InvalidArgumentException('Images must be no larger than 6 MB.');
    if(!extension_loaded('gd')) throw new RuntimeException('Enable the PHP GD extension to upload images.');
    $info=@getimagesize($file['tmp_name']); $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if(!$info || !in_array($mime,['image/jpeg','image/png','image/webp'],true) || $info[0]*$info[1]>16000000 || $info[0]<1 || $info[1]<1) throw new InvalidArgumentException('Use a valid JPG, PNG or WebP image up to 16 megapixels. SVG and animated files are not accepted.');
    $mem=ini_get('memory_limit'); $limit=(int)$mem; if(str_ends_with(strtolower($mem),'g')) $limit*=1024*1024*1024; elseif(str_ends_with(strtolower($mem),'m')) $limit*=1024*1024; elseif(str_ends_with(strtolower($mem),'k')) $limit*=1024;
    if($limit>0 && memory_get_usage(true)+$info[0]*$info[1]*8+32*1024*1024>$limit) throw new InvalidArgumentException('This image is too large for the server memory limit. Resize it before uploading.');
    $im=@imagecreatefromstring(file_get_contents($file['tmp_name'])); if(!$im) throw new InvalidArgumentException('The image could not be decoded.');
    $ratio=min(1,2400/max($info[0],$info[1])); $w=(int)round($info[0]*$ratio);$h=(int)round($info[1]*$ratio);$out=imagecreatetruecolor($w,$h);
    imagealphablending($out,false); imagesavealpha($out,true); imagecopyresampled($out,$im,0,0,0,0,$w,$h,$info[0],$info[1]);
    ob_start(); imagepng($out,null,6); $bytes=ob_get_clean(); imagedestroy($im);imagedestroy($out);
    $id=bin2hex(random_bytes(16)); $fileName=$id.'.php'; $dest=rtrim(config()['storage'],'/').'/media/'.$fileName;
    write_private_file($dest,CWP_GUARD.$bytes);
    try { query('INSERT INTO '.table('media').' (id,filename,mime,bytes,width,height,original_name,created_at) VALUES (?,?,?,?,?,?,?,?)',[$id,$fileName,'image/png',strlen($bytes),$w,$h,substr(basename($file['name']),0,180),gmdate('Y-m-d H:i:s')]); } catch(Throwable $e) { @unlink($dest); throw $e; }
    log_event('media.upload',$id); return $id;
}
/**
 * Save a NEW private file. Permissions are applied before writing sensitive bytes.
 * Never overwrites a file or follows a caller-supplied symlink. All callers use
 * fixed application paths or random filenames inside the private media folder.
 */
function write_private_file(string $path, string $data): void {
    if (is_link($path)) throw new RuntimeException('A symbolic link cannot be used as a private file.');
    $oldMask=umask(0077);
    try { $fp=@fopen($path,'xb'); } finally { umask($oldMask); }
    if (!$fp) throw new RuntimeException('The server cannot create a private application file. Check directory ownership and quota.');
    $complete=false;
    try {
        if (!@chmod($path,0600)) throw new RuntimeException('Owner-only file permissions could not be applied. Ask your host to align PHP ownership.');
        clearstatcache(true,$path);
        if ((fileperms($path)&0777)!==0600) throw new RuntimeException('The server did not retain private file permissions (0600).');
        $offset=0; $length=strlen($data);
        while ($offset<$length) {
            $written=fwrite($fp,substr($data,$offset));
            if ($written===false || $written===0) throw new RuntimeException('The private file write was incomplete. Check available disk space.');
            $offset+=$written;
        }
        if (!fflush($fp)) throw new RuntimeException('The private file could not be flushed to storage.');
        $complete=true;
    } finally {
        fclose($fp);
        if (!$complete) @unlink($path);
    }
}

/** One lock per installation directory, independent of optional storage choice. */
function acquire_installation_mutex() {
    $path=CWP_ROOT.'/.installation-mutex.php';
    if (is_link($path)) throw new RuntimeException('Installation lock is a symbolic link. Ask the hosting owner to inspect it.');
    $oldMask=umask(0077);
    try { $fp=@fopen($path,'c+b'); } finally { umask($oldMask); }
    if (!$fp) throw new RuntimeException('Cannot create the installation lock. Check the website-directory owner and permissions.');
    if (!flock($fp,LOCK_EX|LOCK_NB)) { fclose($fp); throw new RuntimeException('Another installation is in progress. Wait for it to finish.'); }
    try {
        if (!@chmod($path,0600)) throw new RuntimeException('Cannot secure the installation lock. Ask your host to align file ownership.');
        rewind($fp);
        if (fwrite($fp,CWP_GUARD)!==strlen(CWP_GUARD) || !fflush($fp)) throw new RuntimeException('Cannot write the installation lock. Check available disk space.');
        return $fp;
    } catch (Throwable $e) { flock($fp,LOCK_UN);fclose($fp);throw $e; }
    // The lock inode remains permanently in place. Unlinking a live lock can
    // let another process acquire a different inode and bypass serialization.
}

/** Fixed allowlist: never recursively deletes files or accepts a user path. */
function cleanup_installer_files(): array {
    if (!is_file(CWP_ROOT.'/config.php') && !is_file(CWP_ROOT.'/.installed.php')) {
        throw new RuntimeException('Installer cleanup is only available after installation is confirmed.');
    }
    $deleted=[];
    foreach (['install-key.php','install.php','assets/install.js','assets/install.css'] as $file) {
        $path=CWP_ROOT.'/'.$file;
        if (file_exists($path) || is_link($path)) @unlink($path);
        clearstatcache(true,$path);
        $deleted[$file]=!file_exists($path) && !is_link($path);
    }
    return $deleted;
}

function writable_probe(string $dir,bool $repair=false,int $mode=0750): array {
    $result=['path'=>$dir,'ok'=>false,'owner'=>null,'group'=>null,'mode'=>'—','message'=>'','permissions_ok'=>null];
    if (is_link($dir)) { $result['message']='Symbolic links are not accepted for writable application directories.';return $result; }
    if (!is_dir($dir)) {
        if (!$repair || !@mkdir($dir,$mode,true)) { $result['message']='Directory is missing or PHP cannot create it.';return $result; }
    }
    if ($repair) @chmod($dir,$mode);
    clearstatcache(true,$dir);
    $result['owner']=@fileowner($dir);$result['group']=@filegroup($dir);
    $bits=@fileperms($dir);
    $result['mode']=$bits===false?'unknown':substr(sprintf('%o',$bits),-4);
    $result['permissions_ok']=!$repair || ($bits!==false && ($bits&0777)===$mode);
    $f=$dir.'/.cwp-probe-'.bin2hex(random_bytes(8)).'.php';$r=$f.'.php';
    try {
        $content=CWP_GUARD.'probe';write_private_file($f,$content);
        $ok=@file_get_contents($f)===$content;
        $ok=$ok && @file_put_contents($f,'+',FILE_APPEND)===1;
        $ok=$ok && @rename($f,$r);
        $ok=$ok && @file_get_contents($r)===$content.'+';
        $ok=$ok && @unlink($r);
        $result['ok']=$ok && $result['permissions_ok'];
        $result['message']=$result['ok']?'Create, read, append, rename and delete passed.':
            (!$result['permissions_ok']?'The host did not apply the requested private-directory permissions. Check owner/group or a targeted ACL. Do not use 0777.':
            'A file operation failed. Check owner/group, ACLs, quota and parent-directory permissions.');
    } catch (Throwable $e) {
        $result['message']='PHP cannot securely create and manage files here. Ask your host to align this directory owner/group with the PHP worker or grant a targeted ACL. Do not use 0777.';
    } finally {
        if (file_exists($f)) @unlink($f);
        if (file_exists($r)) @unlink($r);
    }
    return $result;
}
function format_date(?string $date): string { return $date ? gmdate('M j, Y',strtotime($date)) : '—'; }

security_headers();
// Re-assert after common headers. No external installer assets or key file
// are loaded. This is the original install.php entry point, replaced in place.
header('Cache-Control: no-store, private, max-age=0, must-revalidate');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-CWP-Installer: one-page-1.1.0-rc.1');
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['cwp_check'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true, 'installer_build'=>CWP_INSTALLER_BUILD,
        'website_version'=>CWP_VERSION, 'key_required'=>false,
        'entrypoint'=>basename(__FILE__), 'scripts'=>'embedded','layout'=>CWP_INSTALLER_LAYOUT,
        'source_sha256'=>hash_file('sha256',__FILE__),
        'installed'=>false, 'package'=>setup_package_check()], JSON_UNESCAPED_SLASHES);
    exit;
}

/** Only immutable public application files: never config, locks or customer data. */
function setup_expected_files(): array { return [
        '.htaccess' => '2cbbbf4de31257d939c6ddaabb570e1f309a2c418be31fdcc7b269f19b3078fb',
        'admin.php' => 'd9ff2b94749be581913e291db3fba760ea55f196165e8ff6a96de277cd5292ab',
        'api.php' => '500e1a3b1d33ed693bf91f6fa9fa723451dea38c49f83b061fa9de8eabd688b2',
        'app/.htaccess' => 'fa28d421a28ed9b6f4b997d5119c2b8adbacff6e2dd119a179b71230f9664934',
        'app/admin-view.php' => 'e1f86ed283eb86e5b39c0186c7b9de4de71a3b18e069d69407b93929f0f0dd5c',
        'app/core.php' => '209b05158ac5c09e523114c1725986a8a09e1d34c6329814f152bf040eda8514',
        'app/growth-pages.php' => 'dec268de56c11ba4faa393596365083bf7f6ea8a86821b1735b49e19a7951c3d',
        'app/growth.php' => '9d83c5e45ef6a9eb53e0448eacf830a188c02093da252fd5f07f977e6fbfef35',
        'app/home.php' => '884af04ad7d0b584af6db50a87df30af9327c6cabbae5fd752da28e3da53d243',
        'app/index.php' => '696138b31bbb2a20c4bb53eb98b68dbe25557947dd6a7513c81e1c8b9446ce0d',
        'app/mail.php' => 'b841a8f23acc147748a829dea230e5e0053489623688c6cbf3f8b18af71fe3d8',
        'app/schema.php' => '02eb78c3aa85b69467905dce3efeff81eddd2c0fe3ab517c5a520f868e793a66',
        'app/studio-admin.php' => 'cca648c03d851c63bfd47a36b0a06e8c583ab51b91daba8c311396507dc3a682',
        'app/view.php' => 'ebf2a24ebf86ff7fba26abdb6c475ffc48be3b90a25dcc6c7a59745a9173f1a4',
        'app/workspace.php' => 'b24ce5f685768e056e6d9b59643c3b0c1abee09a1fa711fa515f0993b839eef7',
        'assets/admin.css' => 'd502889e32bedc3e7ca7cae57f39606330e5632069c79066d821d3016b3d49a0',
        'assets/admin.js' => '6f01319f40402e806cb29b32629b38edc5a3960a568fef150bed89fb8d95ae83',
        'assets/bayou-detail.webp' => '8c1752557b818bc8a35a32ffc4017cef94ee1d6cd247eaa484e2949ee6da2723',
        'assets/bayou.webp' => '9a50abd55f4ea8d6a86e3557e2f0c463cb124279cdc193cc0fc1413887f3d82a',
        'assets/emblem.png' => 'eef8a21455d89155e742548ac37faa98a686264cde46fe1e3e0da8818fa6697f',
        'assets/favicon.png' => '4b55c0ef2c0117b427e568218c7209637ad4cd8d6e2a5772ca5b9860c4a270a1',
        'assets/growth.css' => '6c0475619fc575bf6d1262e1c5fd2b79179c0705560f06f6c2e3d8a4bee32b55',
        'assets/growth.js' => '37364b1a92697785a2146ef54bc747c0017571b119d34a5ac659e1627934d22a',
        'assets/hero-detail.webp' => 'b7844d0006499521be68cda07f62f09115e40b49a01469f0b42fff60ff07ff9d',
        'assets/hero.webp' => '5a00c10a6514db0760dc8d3c915975c22aa3a8745b85dbe74c48954ffd1c41d5',
        'assets/logo.png' => '9c44559bb716076c51ec40daff0f394956a32d24039efa6e3fe33039fc10fe29',
        'assets/micrograin.png' => 'bcb31a9b59c9330ba24da53a2dea6eae06ea242326212fd941af255b51b7303a',
        'assets/premium.css' => '46dc5170e9e4d393af18cd60f513f1141700c957b5d0afc7bfa25242667aa0cf',
        'assets/project-construction.webp' => '222847f2b35bc0e665fb51902c396cb73b3f24d0e02088a56ee8c62cbdfcbd61',
        'assets/project-placeholder.svg' => '690a4304eb273f3ec01765350883dcde4a4787852f27de7f501edb442c49fa11',
        'assets/project-restaurant.webp' => 'efdb940754d22aef36985860e289e313c8d1d3b8f909658fe97ed97125581e3a',
        'assets/project-wellness.webp' => '6166fbc5728bb77fc86352c7e36cabe3adc9117a4a183311bacf6896541085f1',
        'assets/site.css' => 'bc0437271aa5582fd3cbfc4d0b19ddfa33eff4562e02366cd6f443f2d6196304',
        'assets/site.js' => 'b5a1b911565bc507472d6ccd6c3527af92767c12766dd539e13265454e2cc341',
        'favicon.ico' => 'c4e4fe3038ef2cf63978f940d9c43402d9474b1c93e4e0334bbd73a0c8a9ec13',
        'index.php' => '4b1ea57157dc87fc7b7af5d5ffb12dd67127fd8130f62f432d9a9e76e9115cd1',
        'media.php' => '233adcefeb5f0e4a7abad8ec90fa5162a3e274506f5e56d764c2aafec9282a83',
        'portal.php' => '49d1148ac1149755910fca1f23cf05c9dea111ce1d7c7729284028cf308bf5af',
        'robots.php' => '954fce8a35cbc1832f58d386ffd611bf9f45c54456de5f6f7be532c3a3fd7c90',
        'sitemap.php' => '508fcb6f6c3d5511a6c41944a1b11aff6140e410641f1735d67f12d3b61d834b',
    ]; }
function setup_package_check(): array {
    $issues=[]; $passed=0;
    foreach(setup_expected_files() as $relative=>$expected) {
        $path=__DIR__.'/'.$relative;
        clearstatcache(true,$path);
        // No following a symlink in app/assets or in the actual file.
        $walk=__DIR__; $linked=false;
        foreach(explode('/',$relative) as $piece){$walk.='/'.$piece;if(@is_link($walk)){$linked=true;break;}}
        if($linked){$problem='Symbolic link: use the regular file from this package.';}
        elseif(!@is_file($path)){$problem='Missing file.';}
        elseif(!@is_readable($path)){$problem='PHP cannot read this file.';}
        elseif(!hash_equals($expected,(string)@hash_file('sha256',$path))){$problem='Different or incomplete file: merge the complete 1.1.0-rc.1 package here.';}
        else{$passed++;continue;}
        $issues[]=['path'=>$relative,'problem'=>$problem];
    }
    return ['ok'=>!$issues,'passed'=>$passed,'expected'=>count(setup_expected_files()),'issues'=>$issues];
}
function setup_package_row(array $package): array {
    return ['label'=>'Complete website files','ok'=>$package['ok'],
      'message'=>$package['ok']?$package['passed'].' / '.$package['expected'].' required files verified.':
       'Missing or mismatched files: '.implode(', ',array_column($package['issues'],'path')).'. Extract the COMPLETE flat ZIP into this directory with overwrite enabled.'];
}
/** Invalidate only verified site PHP files before including schema on this request. */
function setup_refresh_verified_php(): void {
    if(!function_exists('opcache_invalidate'))return;
    foreach(setup_expected_files() as $path=>$hash){
        if(str_ends_with($path,'.php'))@opcache_invalidate(__DIR__.'/'.$path,true);
    }
}

start_session();
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
/** Storage is persistent and site-specific; never use system temporary storage. */
final class SetupStorageConflict extends RuntimeException {}
function setup_root(): string { return realpath(__DIR__) ?: __DIR__; }
function setup_binding(): string { return hash('sha256',setup_root()); }
function setup_worker_uid(): ?int { return function_exists('posix_geteuid') ? posix_geteuid() : null; }
/** Read-only ownership report; never changes parent permissions or runs commands. */
function setup_permission_report(?string $reason=null): array {
    $uid=setup_worker_uid();$gid=function_exists('posix_getegid')?posix_getegid():null;
    $pw=$uid!==null&&function_exists('posix_getpwuid')?posix_getpwuid($uid):false;
    $name=is_array($pw)?(string)($pw['name']??''):'';
    $identity=($name!==''?$name.' ':'').'UID '.($uid??'unavailable').' / GID '.($gid??'unavailable');
    $paths=[];
    foreach([setup_root(),setup_root().'/storage',setup_root().'/storage/media',setup_root().'/.installation-mutex.php'] as $path){
        clearstatcache(true,$path);$st=@lstat($path);
        $paths[]=['path'=>$path,'exists'=>$st!==false,'owner'=>$st!==false?$st['uid']:null,
            'group'=>$st!==false?$st['gid']:null,'mode'=>$st!==false?sprintf('%04o',$st['mode']&07777):'missing'];
    }
    $text="CajunWebPro installer ".CWP_INSTALLER_BUILD." — hosting permissions report\nPHP worker: ".$identity."\n";
    foreach($paths as $row)$text.=$row['path'].' | owner '.($row['owner']??'unknown').' | group '.($row['group']??'unknown').' | mode '.$row['mode']."\n";
    $text.="Please grant THIS domain's PHP worker targeted write/create/rename/delete access to the website directory for config.php, installation locking and installer removal, and an owner-only permanent private storage location. Preserve existing website files, media and configuration. Do not recursively change the whole account or use 0777.";
    if($reason!==null)$text.="\nReported error: ".$reason;
    return ['identity'=>$identity,'paths'=>$paths,'text'=>$text];
}
function setup_inside(string $path,string $parent): bool { return $path===$parent || str_starts_with($path,rtrim($parent,'/').'/'); }
function setup_document_root(): string { return @realpath($_SERVER['DOCUMENT_ROOT']??'') ?: setup_root(); }
function setup_is_temporary(string $path): bool {
    foreach (array_unique(['/tmp','/var/tmp',rtrim(sys_get_temp_dir(),'/')]) as $temp) {
        if ($temp!=='' && $temp!=='/' && setup_inside($path,$temp)) return true;
    }
    return false;
}
/** Reject symlinks in every segment, not merely the final directory. */
function setup_real_parent(string $path): string {
    if ($path==='' || !str_starts_with($path,'/') || str_contains($path,"\0") || str_contains($path,'\\')) throw new InvalidArgumentException('Use an existing absolute private parent directory.');
    $current='';
    foreach (explode('/',trim($path,'/')) as $part) {
        if ($part==='' || $part==='.' || $part==='..') throw new InvalidArgumentException('Use a direct directory path without dot segments.');
        $current.='/'.$part;
        if (@is_link($current)) throw new InvalidArgumentException('Private storage cannot follow symbolic links.');
    }
    $real=@realpath($path);
    if (!$real || !@is_dir($real) || $real==='/') throw new InvalidArgumentException('The private parent directory must already exist and be accessible to PHP.');
    if (setup_inside($real,setup_document_root()) || setup_inside($real,setup_root())) throw new InvalidArgumentException('The optional private parent must be outside your public document root. Leave it blank for automatic selection.');
    if (setup_is_temporary($real)) throw new InvalidArgumentException('Choose permanent storage, not /tmp or another temporary directory.');
    return $real;
}
function setup_storage_candidates(string $requested): array {
    $name='cajunwebpro-private-'.substr(setup_binding(),0,12);
    if ($requested!=='') return [['path'=>setup_real_parent(rtrim($requested,'/')).'/'.$name,'type'=>'custom private folder']];
    $parents=[dirname(setup_document_root()),dirname(setup_root())];
    // Account home is a fallback only when it is an ancestor of this website.
    if (function_exists('posix_getpwuid') && setup_worker_uid()!==null) {
        $pw=posix_getpwuid(setup_worker_uid());
        if (is_array($pw) && !empty($pw['dir']) && setup_inside(setup_root(),rtrim($pw['dir'],'/'))) $parents[]=rtrim($pw['dir'],'/');
    }
    if (preg_match('~^(/home/[^/]+)/~',setup_root(),$m)) $parents[]=$m[1];
    $out=[];$seen=[];
    foreach (array_unique($parents) as $parent) {
        try {$real=setup_real_parent($parent);} catch(Throwable $e) {continue;}
        $path=$real.'/'.$name;if(isset($seen[$path]))continue;$seen[$path]=true;
        $out[]=['path'=>$path,'type'=>'outside the public document root'];
    }
    // The known local storage URL is already denied by the application's server
    // rules. Media additionally has a PHP exit guard; no arbitrary public path.
    $out[]=['path'=>setup_root().'/storage','type'=>'protected local folder'];
    return $out;
}
function setup_storage_marker(): string { return CWP_GUARD.json_encode(['app'=>'CajunWebPro','binding'=>setup_binding(),'format'=>1],JSON_UNESCAPED_SLASHES)."\n"; }
/** Never adopt another installation's media or overwrite unexpected content. */
function setup_storage_inventory(string $dir): void {
    if (@is_link($dir)) throw new SetupStorageConflict('Storage is a symbolic link. No target files were changed: '.$dir);
    if (!@is_dir($dir)) {
        if (@lstat($dir)!==false) throw new SetupStorageConflict('The storage path is not a real directory: '.$dir);
        return;
    }
    $items=@scandir($dir);if($items===false)throw new SetupStorageConflict('Cannot inspect the existing storage folder safely. Ask your host to correct its ownership: '.$dir);
    foreach ($items as $item) {
        if ($item==='.' || $item==='..')continue;
        $p=$dir.'/'.$item;
        if (@is_link($p)) throw new SetupStorageConflict('A storage entry is a symbolic link. Nothing was overwritten: '.$p);
        if ($item==='install.lock.php') throw new SetupStorageConflict('This storage contains a completed-installation lock. Keep it intact; do not reinstall over existing data.');
        if ($item==='media' && @is_dir($p)) {
            $media=@scandir($p);if($media===false)throw new SetupStorageConflict('Cannot inspect existing media safely. Correct ownership before setup.');
            foreach ($media as $m) if ($m!=='.' && $m!=='..') {
                if (!in_array($m,['.htaccess','index.php'],true) || @is_link($p.'/'.$m) || !@is_file($p.'/'.$m)) throw new SetupStorageConflict('Existing media was found. It is preserved; use recovery rather than a fresh install.');
            }
            continue;
        }
        if (!in_array($item,['.htaccess','index.php','.cwp-storage.php'],true) || !@is_file($p)) throw new SetupStorageConflict('Unexpected existing storage content was preserved: '.$p.'. Use a backed-up recovery workflow, not a fresh install.');
    }
    $marker=$dir.'/.cwp-storage.php';
    if (@is_file($marker) && @file_get_contents($marker)!==setup_storage_marker()) throw new SetupStorageConflict('This private folder belongs to a different website. Its files were left unchanged.');
}
function setup_guard(string $path,string $content): void {
    if (@is_link($path)) throw new RuntimeException('A protection file is a symbolic link: '.$path);
    if (@lstat($path)===false) {write_private_file($path,$content);return;}
    if (!@is_file($path) || trim((string)@file_get_contents($path))!==trim($content)) throw new RuntimeException('An unexpected protection file was preserved: '.$path);
}
function setup_private_directory(string $dir): array {
    $result=['path'=>$dir,'ok'=>false,'owner'=>null,'group'=>null,'mode'=>'—','message'=>''];
    if (@is_link($dir)) {$result['message']='A symbolic link cannot be private storage.';return $result;}
    if (!@is_dir($dir)) {
        if (@lstat($dir)!==false) {$result['message']='A file already occupies this path.';return $result;}
        $mask=umask(0077);try{$created=@mkdir($dir,0700,false);}finally{umask($mask);}
        if(!$created){$result['message']='PHP cannot create this directory in its parent.';return $result;}
    }
    clearstatcache(true,$dir);$owner=@fileowner($dir);$group=@filegroup($dir);$bits=@fileperms($dir);
    $result['owner']=$owner===false?null:$owner;$result['group']=$group===false?null:$group;
    $result['mode']=$bits===false?'unknown':sprintf('%04o',$bits&07777);
    $uid=setup_worker_uid();
    if ($uid!==null && $owner!==$uid) {
        $result['message']='Owned by UID '.($owner===false?'unknown':$owner).', not PHP worker UID '.$uid.'. Left unchanged; trying another private location.';return $result;
    }
    // Only this dedicated application's directory, never a parent or code tree.
    if (!@chmod($dir,0700)) {$result['message']='PHP cannot apply owner-only permissions to this dedicated directory.';return $result;}
    $result=writable_probe($dir,false,0700);clearstatcache(true,$dir);$bits=@fileperms($dir);
    if ($bits===false || ($bits&0777)!==0700) {$result['ok']=false;$result['message']='The server did not retain owner-only directory permissions.';}
    return $result;
}
/** Only an empty, exact shipped storage skeleton can be replaced. No data deletion. */
function setup_pristine_storage(string $dir): bool {
    if(@is_link($dir)||!@is_dir($dir))return false;
    $items=@scandir($dir);if($items===false)return false;
    $expected=['.htaccess'=>"Require all denied",'index.php'=>"<?php http_response_code(404); exit;"];
    foreach($items as $name){
        if($name==='.'||$name==='..')continue;$path=$dir.'/'.$name;
        if(@is_link($path))return false;
        if($name==='media'&&@is_dir($path)){
            $children=@scandir($path);if($children===false)return false;
            foreach($children as $child){
                if($child==='.'||$child==='..')continue;
                if(!isset($expected[$child])||@is_link($path.'/'.$child)||!@is_file($path.'/'.$child)||trim((string)@file_get_contents($path.'/'.$child))!==$expected[$child])return false;
            }
        }elseif(!isset($expected[$name])||!@is_file($path)||trim((string)@file_get_contents($path))!==$expected[$name])return false;
    }
    return true;
}
/**
 * A non-root worker can rename an entry when its parent permits it. Preserve the
 * old template beside the site and create a new 0700 directory owned by PHP.
 * Caller holds the installation-wide mutex. Never recurse or chown anything.
 */
function setup_replace_packaged_storage(): ?string {
    $dir=setup_root().'/storage';
    if(!setup_pristine_storage($dir))return null;
    $backup=setup_root().'/.cwp-packaged-storage-'.bin2hex(random_bytes(8));
    if(@lstat($backup)!==false||!setup_pristine_storage($dir)||!@rename($dir,$backup))return null;
    $mask=umask(0077);try{$made=@mkdir($dir,0700,false);}finally{umask($mask);}
    if(!$made){
        clearstatcache(true,$dir);
        if(@lstat($dir)===false&&@rename($backup,$dir))return null;
        throw new RuntimeException('The empty bundled storage template is preserved at '.$backup.'. PHP could not create its replacement. Ask your host to correct write access; no template was deleted.');
    }
    return $backup;
}

function setup_prepare_storage(string $requested): array {
    $local=setup_root().'/storage';
    // Do not sidestep previous data by quietly switching to an empty folder.
    setup_storage_inventory($local);
    $candidates=setup_storage_candidates($requested);$attempts=[];
    foreach ($candidates as $candidate) {
        $dir=$candidate['path'];setup_storage_inventory($dir);$templateBackup=null;
        // An empty shipped media folder may also belong to root. Detect that
        // before placing a binding marker so the empty skeleton stays verifiable.
        $uid=setup_worker_uid();$foreign=false;
        if($uid!==null && $dir===$local && $requested===''){
            foreach([$dir,$dir.'/media'] as $owned){
                if(@is_dir($owned) && @fileowner($owned)!==$uid)$foreign=true;
            }
            if($foreign && setup_pristine_storage($dir))$templateBackup=setup_replace_packaged_storage();
        }
        $probe=setup_private_directory($dir);$attempts[]=$probe;
        if(!$probe['ok'] && $dir===$local && $requested==='' && $templateBackup===null){
            $templateBackup=setup_replace_packaged_storage();
            if($templateBackup!==null){$probe=setup_private_directory($dir);$attempts[]=$probe;}
        }
        if(!$probe['ok'])continue;
        try {
            setup_guard($dir.'/.htaccess',"Require all denied\n");
            setup_guard($dir.'/index.php',"<?php http_response_code(404); exit;\n");
            setup_guard($dir.'/.cwp-storage.php',setup_storage_marker());
            $media=setup_private_directory($dir.'/media');
            if(!$media['ok']){$attempts[]=$media;continue;}
            setup_guard($dir.'/media/.htaccess',"Require all denied\n");
            setup_guard($dir.'/media/index.php',"<?php http_response_code(404); exit;\n");
            $probe['message']='Ready: '.$candidate['type'].'. PHP-owned, persistent, 0700; file create/read/append/rename/delete verified.';
            $media['message']='Ready. Protected media folder created and verified under the selected private storage.';
            return ['ok'=>true,'storage'=>$dir,'private'=>$probe,'media'=>$media,'attempts'=>$attempts,
                'message'=>$templateBackup!==null?'Created PHP-owned storage. The empty original package template is preserved at '.$templateBackup.'.':($dir!==$local && @is_dir($local)?'The bundled storage folder was left unchanged. The website will use the selected private folder instead.':'The selected storage path will be saved in config.php.')];
        }catch(Throwable $e){$attempts[]=['path'=>$dir,'ok'=>false,'message'=>$e->getMessage()];}
    }
    return ['ok'=>false,'storage'=>'','attempts'=>$attempts,'message'=>'No safe, persistent location was writable by PHP. Your host must grant this domain\'s PHP worker access to a private parent folder and the website folder. Do not make the whole site world-writable.'];
}
function setup_checks(): array {
    $rows=[];$storage='';$attempts=[];$note='';
    $package=setup_package_check();$rows[]=setup_package_row($package);
    if(!$package['ok'])return ['ok'=>false,'checks'=>$rows,'package'=>$package,'storage'=>'','storage_note'=>'All setup fields remain visible. Restore these website files before installation. No database or storage changes were made.','attempts'=>[],'php_uid'=>setup_worker_uid(),'host_report'=>setup_permission_report()];
    $rows[]=['label'=>'PHP runtime','ok'=>PHP_VERSION_ID>=80200,'message'=>'PHP '.PHP_VERSION];
    $rows[]=['label'=>'Secure connection','ok'=>https_request()||local_request(),'message'=>https_request()?'HTTPS is active.':(local_request()?'Local development connection.':'Enable HTTPS before sending credentials.')];
    foreach(['pdo_mysql'=>'MySQL connector','openssl'=>'Encryption','fileinfo'=>'File validation','gd'=>'Image processing'] as $ext=>$label)$rows[]=['label'=>$label,'ok'=>extension_loaded($ext),'message'=>extension_loaded($ext)?$ext.' available':'Enable '.$ext.' in your hosting PHP settings.'];
    $root=writable_probe(setup_root(),false,0755);$rows[]=['label'=>'Website directory','ok'=>$root['ok'],'message'=>$root['message'],'details'=>$root];
    try {
        if(!$root['ok'])throw new RuntimeException('PHP needs targeted write access to the website directory to save config.php and remove install.php. Website code permissions were not changed.');
        $s=setup_prepare_storage(posted('private_parent',1000));$storage=$s['storage'];$attempts=$s['attempts'];$note=$s['message'];
        if($s['ok']){
            foreach(['private'=>'Private storage','media'=>'Protected media'] as $key=>$label)$rows[]=['label'=>$label,'ok'=>true,'message'=>$s[$key]['message'],'details'=>$s[$key]];
        }else{$rows[]=['label'=>'Private storage','ok'=>false,'message'=>$s['message']];$rows[]=['label'=>'Protected media','ok'=>false,'message'=>'Waiting for a writable private location; this is not a separate missing-folder problem.'];}
    }catch(Throwable $e){$rows[]=['label'=>'Private storage','ok'=>false,'message'=>$e->getMessage()];$rows[]=['label'=>'Protected media','ok'=>false,'message'=>'No media or existing storage was overwritten. Resolve the private-storage check first.'];}
    return ['ok'=>!in_array(false,array_column($rows,'ok'),true),'checks'=>$rows,'storage'=>$storage,'storage_note'=>$note,'attempts'=>$attempts,'php_uid'=>setup_worker_uid(),'host_report'=>setup_permission_report(),'package'=>$package];
}

/** Cleanup only after committed config exists; no caller-provided paths. */
function cwp_installer_cleanup(): array {
    if (!cwp_installer_installed()) throw new RuntimeException('Installer cleanup is only available after installation is confirmed.');
    // The site's fixed cleanup allowlist preserves configuration and private data.
    $self = __FILE__;
    if (function_exists('opcache_invalidate')) {
        foreach ([$self, __DIR__.'/setup-1.0.4.php', __DIR__.'/install-key.php'] as $path) {
            if (is_file($path)) @opcache_invalidate($path, true);
        }
    }
    $deleted = cleanup_installer_files();
    // Retire the optional previous workaround only after config is committed.
    // No recursion, no wildcard or request-supplied path. Absence is not logged
    // as a deletion. A same-named directory is reported, not removed.
    $legacy = __DIR__ . '/setup-1.0.4.php';
    clearstatcache(true, $legacy);
    if (@lstat($legacy) !== false) {
        @unlink($legacy);
        clearstatcache(true, $legacy);
        $deleted['setup-1.0.4.php'] = @lstat($legacy) === false;
    }
    // Normally cleanup_installer_files already removed install.php. Keep the
    // exact self-path check for direct copies of this file as well.
    if (file_exists($self) || is_link($self)) @unlink($self);
    clearstatcache(true, $self);
    $deleted[basename($self)] = @lstat($self) === false;
    return $deleted;
}


function setup_owner_input(): array {
    $name=posted('owner_name',120);$email=posted('owner_email',190);$password=posted('owner_password',72);validate_password($password);
    if($password!==posted('owner_confirm',72))throw new InvalidArgumentException('The administrator passwords do not match.');
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter your name and a valid administrator email.');
    $url=rtrim(posted('site_url',500),'/');if($url==='')throw new InvalidArgumentException('Enter the website URL.');
    safe_url($url,!local_request());$parts=parse_url($url);
    if(isset($parts['query'])||isset($parts['fragment']))throw new InvalidArgumentException('The website URL cannot contain a query string or fragment.');
    $contact=posted('business_email',190);if($contact!==''&&!filter_var($contact,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid public business email, or leave it blank.');
    return ['name'=>$name,'email'=>strtolower($email),'hash'=>password_hash($password,PASSWORD_DEFAULT),'site_url'=>$url,'business_email'=>$contact,'seed'=>isset($_POST['seed']),'preview'=>isset($_POST['preview'])];
}
function setup_database_input(): array {
    $c=['host'=>posted('db_host',253),'port'=>(int)posted('db_port',5),'name'=>posted('db_name',64),'user'=>posted('db_user',128),'pass'=>posted('db_password',512),'prefix'=>posted('db_prefix',20)];
    if(!preg_match('/^[a-zA-Z0-9.-]+$/D',$c['host'])||$c['port']<1||$c['port']>65535||!preg_match('/^[a-zA-Z0-9_$-]+$/D',$c['name'])||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$c['prefix'])||$c['user']==='')throw new InvalidArgumentException('Check the database host, port, full database/user names and table prefix. The prefix must be 2–20 letters, digits or underscores, beginning with a letter.');
    return $c;
}
function setup_database_test(array $c): string {
    if(!extension_loaded('pdo_mysql'))throw new RuntimeException('Enable the pdo_mysql extension in your hosting PHP settings.');
    require_once __DIR__.'/app/schema.php';
    try {
        $pdo=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['name'].';charset=utf8mb4',$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>8]);
        foreach(array_keys(schema_statements($c['prefix'])) as $name){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?');$s->execute([$c['name'],$c['prefix'].$name]);if((int)$s->fetchColumn()>0)throw new InvalidArgumentException('Application tables with this prefix already exist. Nothing was overwritten. Use the existing website or choose a different empty database/prefix.');}
        $probe=$c['prefix'].'probe_'.bin2hex(random_bytes(5));
        $pdo->exec('CREATE TABLE `'.$probe.'` (id INT PRIMARY KEY) ENGINE=InnoDB');
        try{$pdo->exec('INSERT INTO `'.$probe.'` VALUES (1)');$pdo->query('SELECT id FROM `'.$probe.'`')->fetchColumn();$pdo->exec('UPDATE `'.$probe.'` SET id=2');$pdo->exec('DELETE FROM `'.$probe.'`');}finally{$pdo->exec('DROP TABLE `'.$probe.'`');}
        return (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    } catch(PDOException $e){report_error($e);throw new RuntimeException('Database connection or permissions failed. Check the exact host, full prefixed database/user names and password. Assign this user SELECT, INSERT, UPDATE, DELETE, CREATE, DROP and INDEX for this database.');}
}
/** Caller holds the installation-wide mutex. No wizard/session prerequisite. */
function setup_deploy(array $o,array $c,string $storage): array {
    $created=[];$committed=false;$events=[];$warnings=[];$temp=null;
    try {
        clearstatcache();if(cwp_installer_installed())throw new RuntimeException('This website is already installed.');
        foreach([setup_root(),$storage,$storage.'/media'] as $dir){$probe=writable_probe($dir,false,0700);if(!$probe['ok'])throw new RuntimeException('Final write check failed: '.$dir.'. '.$probe['message']);}
        $cfg=['installer_version'=>CWP_INSTALLER_BUILD,'version'=>CWP_VERSION,'installed_at'=>gmdate('c'),'site_url'=>$o['site_url'],'key'=>bin2hex(random_bytes(32)),'storage'=>$storage,'db'=>$c];$GLOBALS['cwp_config']=$cfg;
        require_once __DIR__.'/app/schema.php';$pdo=db();
        foreach(schema_statements($c['prefix']) as $name=>$sql){$pdo->exec($sql);$created[]=$name;}
        $events[]='Created '.count($created).' isolated application tables.';
        $pdo->beginTransaction();foreach(default_settings() as $k=>$v)save_setting($k,$v);
        save_setting('contact_email',$o['business_email']);save_setting('notification_email',$o['email']);save_setting('mail_from',$o['business_email']);save_setting('site_published',$o['preview']?'0':'1');
        query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',[$o['name'],$o['email'],$o['hash'],bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
        foreach(default_services() as $i=>$v)query('INSERT INTO '.table('services').' (slug,title,summary,body,icon,sort_order,active) VALUES (?,?,?,?,?,?,1)',[...$v,$i]);
        if($o['seed'])foreach(default_projects() as $i=>$v)query('INSERT INTO '.table('projects').' (slug,title,category,summary,description,challenge,solution,deliverables,cover,status,is_demo,featured,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,\'published\',1,1,?,?,?)',[...$v,$i,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
        log_event('install.complete','Installer '.CWP_INSTALLER_BUILD);$pdo->commit();$events[]='Saved the administrator account and website content.';
        $data="<?php\ndeclare(strict_types=1);\nif (!defined('CWP_BOOT')) { http_response_code(404); exit; }\nreturn ".var_export($cfg,true).";\n";
        $temp=setup_root().'/config-pending-'.bin2hex(random_bytes(10)).'.php';write_private_file($temp,$data);
        clearstatcache();if(cwp_installer_installed())throw new RuntimeException('Configuration appeared during setup. It was not overwritten.');
        if(!@rename($temp,setup_root().'/config.php'))throw new RuntimeException('The server could not activate config.php.');$committed=true;
        $events[]='Saved the permanent storage path in owner-only config.php.';
        foreach([setup_root().'/.installed.php',$storage.'/install.lock.php'] as $lock){try{write_private_file($lock,CWP_GUARD.'Installed '.gmdate('c'));}catch(Throwable $e){$warnings[]='Secondary installation lock could not be created. config.php still blocks reinstallation.';}}
        $events[]='Locked the website against accidental reinstallation.';
        $legacy=[];foreach(['install-key.php','install.php','assets/install.js','assets/install.css','setup-1.0.4.php'] as $f)$legacy[$f]=@lstat(setup_root().'/'.$f)!==false;
        try{$deleted=cwp_installer_cleanup();}catch(Throwable $e){$deleted=[];$warnings[]='The website is installed and locked, but installer cleanup needs attention in your hosting File Manager.';}
        foreach($deleted as $file=>$gone){if($gone&&!empty($legacy[$file]))$events[]='Removed '.$file.'.';elseif(!$gone)$warnings[]=$file.' could not be deleted. The installed-site guard blocks setup. Remove only this installer file in File Manager.';}
        $events[]='Private files and media use: '.$storage;
        unset($_SESSION['setup_db'],$_SESSION['setup_owner'],$_SESSION['setup_storage'],$_SESSION['setup_until']);
        @session_regenerate_id(true);
        return ['ok'=>true,'events'=>$events,'warnings'=>$warnings,'deleted'=>$deleted,'storage'=>$storage,'admin'=>$o['site_url'].'/admin.php','site'=>$o['site_url'].'/'];
    } catch(Throwable $e){
        if(!$committed){
            if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
            if(isset($pdo))foreach(array_reverse($created) as $name){try{$pdo->exec('DROP TABLE '.table($name));}catch(Throwable $ignored){error_log('CWP installer rollback: newly created table cleanup needs attention.');}}
            if($temp&&@is_file($temp))@unlink($temp);
            throw $e;
        }
        // A post-commit cleanup fault must never drop a successfully installed DB.
        report_error($e);
        return ['ok'=>true,'events'=>$events,'warnings'=>['The website configuration is active. A final cleanup action needs attention; sign in rather than reinstall.'],'deleted'=>[],'storage'=>$storage,'admin'=>$o['site_url'].'/admin.php','site'=>$o['site_url'].'/'];
    }
}
// Request dispatch: a single Install action validates every field and prerequisite.
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
        if(cwp_installer_installed())throw new RuntimeException('The website was already installed by another request. Open admin.php.');
        if($action==='checks')$result=setup_checks();
        elseif($action==='test_database'){$v=setup_database_test(setup_database_input());$result=['ok'=>true,'message'=>'Connected to database '.$v.'. Read/write/create/drop probes passed; existing tables were not overwritten.'];}
        else {
            $checks=setup_checks();
            if(!$checks['ok']){$result=$checks+['message'=>'Resolve the highlighted server checks, then click Install again. Your form entries remain on this page.'];$status=422;}
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
            $result['storage_note']='All database, account and website fields stay on this page. Correct only the targeted hosting permissions shown below, then recheck.';
        }
    }
    finally{if(is_resource($handle)){flock($handle,LOCK_UN);fclose($handle);}}
    setup_json($result??['ok'=>false,'message'=>'No installation action was completed.'],$status);
}
$package=setup_package_check();
$path=base_path();$suggested=(https_request()?'https':'http').'://'.preg_replace('/[^A-Za-z0-9.\-:\[\]]/','',$_SERVER['HTTP_HOST']??'example.com').$path;
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="csrf-token" content="<?=esc(csrf())?>"><title>CajunWebPro · One-page installer 1.1.0-rc.1</title><link rel="icon" href="assets/favicon.png">
<style>
:root{color-scheme:dark;--gold:#f5c96c;--blue:#60b9ff;--muted:#a0b4cf;--line:#47628565;--surface:#091b32;--white:#edf5ff;--ease:cubic-bezier(.2,.8,.2,1)}*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at 80% 0%,#153f716b,transparent 48%),radial-gradient(ellipse at 0% 62%,#09274b9c,transparent 50%),#020914;color:var(--white);font:14px/1.6 "Segoe UI",Arial,sans-serif;-webkit-font-smoothing:antialiased}body:before{content:"";pointer-events:none;position:fixed;inset:0;z-index:-1;background:linear-gradient(115deg,transparent 10%,#c9a25808 11%,transparent 12%),repeating-linear-gradient(90deg,transparent 0 95px,#4261820c 96px);mask-image:linear-gradient(#000,transparent 80%)}a{color:#ffe0a2;text-decoration:none}a:hover{text-decoration:underline}button,input{font:inherit}button{cursor:pointer;touch-action:manipulation}button:disabled{cursor:wait;opacity:.6}input:disabled{opacity:.75}h1,h2,p{margin:0}h1{font-size:38px;line-height:1.15;letter-spacing:-.045em;font-weight:750}h2{font-size:21px;letter-spacing:-.025em;line-height:1.25}strong{font-weight:650}[hidden]{display:none!important}img{display:block;max-width:100%;height:auto}::selection{background:var(--gold);color:#07101e}:focus-visible{outline:2px solid #ffe1a0;outline-offset:4px}.shell{width:min(1180px,calc(100% - 64px));margin:0 auto}.topbar{height:106px;display:flex;align-items:center;justify-content:space-between;gap:20px;border-bottom:1px solid var(--line)}.logo{width:308px}.build{color:#ffdf91;font-size:11px;letter-spacing:.07em;text-align:right;line-height:1.8}.build span{display:block;color:#82a9d4;font-size:10px;letter-spacing:.1em}.intro{display:flex;align-items:center;justify-content:space-between;gap:25px;padding:32px 0}.eyebrow{font-size:10px;font-weight:700;letter-spacing:.2em;color:var(--gold);text-transform:uppercase;margin-bottom:10px}.metal{background:linear-gradient(168deg,#fff8e1,#f5cd78 40%,#c79030 67%,#fff1c5);color:transparent;background-clip:text;-webkit-background-clip:text}.intro p{font-size:13px;color:#adc2db;max-width:590px;margin-top:12px}.intro-sign{display:flex;align-items:center;gap:15px;color:#c9dcf0;font-size:10px;letter-spacing:.1em;white-space:nowrap}.intro-sign img{width:64px;filter:drop-shadow(0 0 17px #f0b73635)}.intro-sign span{display:block;color:#b3965d;margin-top:5px;font-size:9px}.panel{position:relative;border:1px solid #466c9878;border-top-color:#c7a7689c;border-radius:14px;background:radial-gradient(ellipse at 100% 0%,#254f7526,transparent 75%),linear-gradient(145deg,#0a2038f5,#060f20fa);box-shadow:0 13px 30px #0003,inset 0 1px 0 #ffffff09;margin-bottom:22px;overflow:hidden}.panel-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 25px;border-bottom:1px solid #6380a230;background:linear-gradient(95deg,#1837516b,#13223422)}.head-title{display:flex;align-items:center;gap:13px}.section-icon{display:grid;place-items:center;width:39px;height:39px;border:1px solid #c19b595e;background:linear-gradient(140deg,#284159,#111c29);box-shadow:inset 0 0 10px #b5904825;border-radius:9px;color:var(--gold);font-size:19px;flex-shrink:0}.head-copy p{color:#8fabcc;font-size:11px;margin-top:5px}.pill{border:1px solid #8babb843;padding:5px 11px;border-radius:30px;font-size:10px;letter-spacing:.06em;color:#b0cae3;white-space:nowrap}.body{padding:24px 25px}.button{border-radius:8px;padding:10px 17px;line-height:1.3;display:inline-flex;align-items:center;justify-content:center;gap:15px;font-size:12px;font-weight:650;text-decoration:none;min-height:41px;border:1px solid #768fac75;color:#dfeaff;background:linear-gradient(#1e3953,#0a1729);box-shadow:inset 0 1px 0 #d8e6ff16;transition:filter .2s,transform .2s}.button:hover:not(:disabled){filter:brightness(1.12);text-decoration:none}.button.gold{background:linear-gradient(175deg,#fff2b5 0%,#f3cc73 31%,#d8a342 75%,#fbd68c 100%);color:#171208;border:1px solid #ffe3a4;box-shadow:inset 0 1px 2px #fff9d9,0 0 20px #d9a63828}.button.gold:hover:not(:disabled){transform:translateY(-1px)}.server-summary{font-size:13px;color:#b0c6df;margin-bottom:16px}.server-summary.ready{color:#abdabb}.server-summary.attention{color:#ffd38c}.checks-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:18px}.check-row{display:flex;align-items:flex-start;gap:10px;padding:12px;border:1px solid #536f8e45;border-radius:8px;background:#03101e80;min-width:0}.check-icon{border:1px solid #5b9c8280;border-radius:50%;color:#a4e2ba;display:grid;place-items:center;width:21px;height:21px;font-size:12px;flex-shrink:0}.check-row.failed{border-color:#c1884d62}.failed .check-icon{color:#ffd396;border-color:#bb885c}.check-row strong{font-size:11px;display:block;margin-bottom:3px}.check-row small{font-size:10px;line-height:1.5;display:block;color:#9bb3cc}.check-row .path{font:9px/1.6 ui-monospace,Consolas,monospace;color:#82a3c6;margin-top:7px;overflow-wrap:anywhere}.check-row>div{min-width:0}.storage-line{display:grid;grid-template-columns:1fr 1fr;gap:22px;padding-top:18px;border-top:1px solid #576f8a40}.storage-line p{color:#93adca;font-size:11px;line-height:1.8;align-self:center}.storage-line label{margin:0}.hint{font-size:11px;line-height:1.8;color:#8faac7;margin-top:9px}.path-note{font-size:11px;color:#c3d5e9;overflow-wrap:anywhere;margin:0 0 16px}.diagnostics{font-size:11px;color:#94b0cd;margin:12px 0 0}.diagnostics summary{cursor:pointer;color:#d9c18d}.diagnostics li{overflow-wrap:anywhere;margin-top:9px}.fields-columns{display:grid;grid-template-columns:1fr 1fr;gap:22px}.fields-columns>.panel{height:calc(100% - 22px)}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px}.fields .full{grid-column:1/-1}.host-port{grid-template-columns:minmax(0,1fr) 105px}label{display:block;font-size:12px;font-weight:550;color:#cad9e9;margin:0 0 17px}.fields label{margin-bottom:0}.optional{font-size:10px;font-weight:400;color:#829dba}input:not([type="checkbox"]){display:block;min-width:0;width:100%;border:1px solid #4260829c;background:linear-gradient(180deg,#020b17,#061326);border-radius:7px;color:#f0f6ff;font-size:14px;padding:10px 12px;min-height:43px;outline:none;margin-top:7px;transition:border-color .2s,box-shadow .2s}input:not([type="checkbox"]):focus{border-color:#e6c16e;box-shadow:0 0 0 3px #a7853822}input::placeholder{color:#6c88a5}input[aria-invalid="true"]{border-color:#de9e71}.spaced{margin-top:17px}.db-test{display:flex;gap:15px;align-items:center;margin-top:18px;min-height:41px}.inline-result{color:#9ebcd8;font-size:11px;overflow-wrap:anywhere}.inline-result.bad{color:#ffd195}.checkbox{display:flex;gap:10px;align-items:flex-start;font-size:11px;font-weight:400;line-height:1.7;color:#adbed0;margin-bottom:13px}.checkbox input{width:16px;height:16px;accent-color:#e6bd67;flex-shrink:0;margin:2px 0 0}.option-row{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-top:21px}.option-row strong{color:#d4e1ee;display:block;font-size:12px;margin-bottom:3px}.launch-bar{background:radial-gradient(ellipse at 100% 0%,#d8a2451c,transparent 65%),linear-gradient(100deg,#0b2440,#071321);border:1px solid #d5ac616b;border-radius:12px;padding:23px 25px;display:flex;align-items:center;justify-content:space-between;gap:30px;box-shadow:inset 0 1px 0 #e7bd612a,0 15px 30px #0003;margin:0 0 18px}.launch-bar .checkbox{margin:0;max-width:645px;font-size:12px}.launch-bar .button{font-size:14px;min-width:237px;min-height:53px;padding:14px 20px}.status-box{border:1px solid #5189b278;background:#0d294466;border-radius:10px;padding:18px 22px;margin:0 0 20px;color:#bed8f2;font-size:13px;line-height:1.8;overflow-wrap:anywhere}.status-box.error{border-color:#b9816270;background:#61372030;color:#ffcfaf}.status-box.success{border-color:#719e797d;background:#194c3036;color:#c0e8c9}.wait{display:flex;align-items:center;gap:14px}.spinner{width:24px;height:24px;border:2px solid #d9c38635;border-top-color:var(--gold);border-radius:50%;animation:spin 1s linear infinite;flex-shrink:0}.fineprint{color:#819bb6;font-size:11px;line-height:1.9;margin:0 0 26px}.bottom{display:flex;justify-content:space-between;gap:20px;padding:22px 0 30px;border-top:1px solid var(--line);color:#6e8ead;font-size:10px;letter-spacing:.09em}.complete{padding:30px}.complete h2{font-size:30px;color:#ffe1a2;margin-bottom:14px}.complete p{color:#b3ccdf}.complete ul{padding-left:21px;margin:22px 0;color:#bde4ce}.complete li{padding:7px 0}.complete .button{margin:10px 12px 0 0}.warning{border:1px solid #aa89525e;background:#6651282e;color:#f6d699;font-size:12px;padding:13px;border-radius:7px;margin-bottom:9px;overflow-wrap:anywhere}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:1000px){.shell{width:calc(100% - 40px)}.intro-sign{display:none}.checks-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.panel-head{padding:20px}.body{padding:21px 20px}.head-title{gap:10px}h2{font-size:19px}.pill{font-size:9px;padding:4px 8px}.fields-columns{gap:17px}.launch-bar{gap:20px}.launch-bar .button{min-width:210px}.section-icon{width:35px;height:35px}}@media(max-width:720px){.shell{width:calc(100% - 28px)}.topbar{height:88px;gap:12px}.logo{width:225px}.build{font-size:9px;line-height:1.6}.build span{font-size:8px;letter-spacing:0}.intro{padding:27px 3px}.intro h1{font-size:33px}.intro p{font-size:12px}.eyebrow{font-size:9px}.fields-columns{grid-template-columns:1fr;gap:0}.fields-columns>.panel{height:auto}.panel{margin-bottom:17px;border-radius:11px}.panel-head{padding:18px}.body{padding:18px}.checks-grid{gap:8px}.check-row{padding:10px;gap:8px}.check-row strong{font-size:10px}.check-row small{font-size:9px}.check-row .path{font-size:9px}.storage-line{grid-template-columns:1fr;gap:10px}.option-row{grid-template-columns:1fr;gap:0}.launch-bar{padding:20px;flex-direction:column;align-items:stretch;gap:20px}.launch-bar .button{width:100%}.launch-bar .checkbox{font-size:11px}.bottom{font-size:8px;gap:10px}.complete{padding:23px}.complete h2{font-size:27px}input:not([type="checkbox"]){font-size:16px}.fields-columns .panel-head{min-height:84px}}@media(max-width:430px){.shell{width:calc(100% - 22px)}.logo{width:200px}.topbar{height:82px}.build{font-size:8px}.build span{font-size:7px}.intro h1{font-size:30px}.checks-grid{grid-template-columns:1fr}.check-row{min-height:58px}.check-row strong{font-size:11px}.check-row small{font-size:10px}.panel-head{padding:16px 14px;gap:8px}.body{padding:17px 14px}h2{font-size:18px}.head-copy p{font-size:10px}.button{font-size:11px;padding:9px 11px;gap:9px}.head-title{gap:9px}.section-icon{width:30px;height:33px;font-size:17px}.fields{grid-template-columns:1fr;gap:15px}.host-port{grid-template-columns:minmax(0,1fr) 90px}.panel-head .pill{display:none}.launch-bar{padding:18px}.bottom{flex-direction:column;gap:6px}.check-row .path{font-size:9px}.db-test{align-items:flex-start;flex-direction:column;gap:8px}}@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;scroll-behavior:auto!important}}

/* Keep configuration visible and editable during automatic background checks. */
.package-panel .compact{padding-top:13px;padding-bottom:14px}.package-panel .path-note{margin-bottom:0}.package-issues{padding-left:20px;color:#ffcfaf;font-size:12px;overflow-wrap:anywhere}.package-issues li{margin:6px 0}.package-issues code{color:#ffe2a5}.package-panel .panel-head{padding-top:15px;padding-bottom:15px}
.onepage-note{display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px 24px;padding:14px 20px;margin:0 0 20px;border:1px solid #b38d463b;border-radius:12px;background:#bda05208;font-size:12px;line-height:1.65;color:#ced9e8}
.onepage-note strong{color:#f3d48b;font-weight:600}.onepage-note span{color:#9eafc4}
#host-report{width:100%;box-sizing:border-box;min-height:180px;border:1px solid #556a844f;background:#040d19;color:#dae5f4;border-radius:8px;font:12px/1.6 ui-monospace,monospace;padding:14px;resize:vertical}
@media(max-width:600px){.onepage-note{padding:12px 15px;font-size:11px}#host-report{font-size:11px}}
</style></head><body><main class="shell">
<header class="topbar"><img class="logo" src="assets/logo.png" alt="CajunWebPro"><div class="build"><strong>ONE-PAGE INSTALLER · 1.1.0-rc.1</strong><span>NO INSTALLATION KEY</span></div></header>
<div class="intro"><div><div class="eyebrow">LAUNCH STUDIO / YOUR SERVER. YOUR WEBSITE.</div><h1>Everything here.<br><span class="metal">Ready for launch.</span></h1><p>Connect your database, create your administrator account, and install. All fields are shown below. Enter your details once, then press Install.</p></div><div class="intro-sign"><img src="assets/emblem.png" alt=""><div>BUILT ON YOUR TERMS<span>PHP / MYSQL / PRIVATE STORAGE</span></div></div></div>
<noscript><div class="status-box error">Enable JavaScript for the inline server checks and one-page installation form.</div></noscript>
<?php if(!$secure):?><div class="status-box error">HTTPS is not active. Enable SSL for this domain before entering credentials. Behind a proxy? Configure only the trusted proxy IPs in proxy-config.php.</div><?php endif?>
<div class="onepage-note"><strong>Database · Administrator · Website · Server checks</strong><span>One form. No Next/Back screens. No wizard. No key. Missing files and storage errors stay on this screen.</span></div>

<section id="package-panel" class="panel package-panel" aria-labelledby="package-heading">
<div class="panel-head"><div class="head-title"><span class="section-icon" aria-hidden="true">✓</span><div class="head-copy"><h2 id="package-heading">Website files</h2><p id="package-summary"><?=esc($package['ok'] ? $package['passed'].' / '.$package['expected'].' required files verified.' : count($package['issues']).' missing or mismatched files. Your setup form is still below.')?></p></div></div><span class="pill">BUILD 1.1.0-rc.1</span></div>
<div class="body compact"><p class="path-note">This installer is running from <strong><?=esc(__FILE__)?></strong></p><ul id="package-issues" class="package-issues" <?=$package['ok']?'hidden':''?>><?php foreach($package['issues'] as $issue):?><li><code><?=esc($issue['path'])?></code> — <?=esc($issue['problem'])?></li><?php endforeach?></ul><p class="hint">The ZIP extracts directly into this website folder. There is no enclosing upload/ or version folder. Configuration and private media are created during installation, not supplied as empty credential files.</p></div></section>
<div id="setup-error" class="status-box error" role="alert" hidden></div>
<section id="completion" class="panel complete" aria-live="polite" hidden><div class="eyebrow">INSTALLATION COMPLETE</div><h2>Your website is ready.</h2><p>The completed actions and any cleanup warnings appear below.</p><ul id="completion-log"></ul><div id="completion-warnings"></div><a class="button gold" id="open-dashboard">Open dashboard →</a><a class="button" id="open-website">View website ↗</a></section>
<form id="install-form" method="post" action="install.php" autocomplete="on">
<?=csrf_field()?><input type="hidden" name="action" value="install">
<div class="fields-columns"><section class="panel" aria-labelledby="db-heading"><div class="panel-head"><div class="head-title"><span class="section-icon" aria-hidden="true">▤</span><div class="head-copy"><h2 id="db-heading">Database connection</h2><p>Your hosting database, not a new subscription.</p></div></div><span class="pill">MYSQL / MARIADB</span></div><div class="body">
<div class="fields host-port"><label>Database host<input name="db_host" value="localhost" required maxlength="253" autocomplete="off" spellcheck="false"></label><label>Port<input name="db_port" type="number" value="3306" min="1" max="65535" required></label></div>
<div class="fields spaced"><label>Database name<input name="db_name" required maxlength="64" placeholder="account_database" autocomplete="off" spellcheck="false"></label><label>Database username<input name="db_user" required maxlength="128" placeholder="account_dbuser" autocomplete="off" spellcheck="false"></label><label class="full">Database password<input name="db_password" type="password" maxlength="512" required autocomplete="new-password"></label><label class="full">Table prefix<input name="db_prefix" value="cwp_" pattern="[a-zA-Z][a-zA-Z0-9_]{1,19}" required maxlength="20" spellcheck="false"></label></div>
<p class="hint">Create the database and assign its user in your hosting panel first. Existing application tables are never replaced.</p><div class="db-test"><button type="button" class="button" id="test-database" <?=$secure?'':'disabled'?>>Test connection</button><span id="db-result" class="inline-result" role="status">Optional. Also checked during installation.</span></div></div></section>
<section class="panel" aria-labelledby="owner-heading"><div class="panel-head"><div class="head-title"><span class="section-icon" aria-hidden="true">♙</span><div class="head-copy"><h2 id="owner-heading">Administrator account</h2><p>You own the website. These are your sign-in details.</p></div></div><span class="pill">PRIVATE ACCESS</span></div><div class="body">
<div class="fields"><label class="full">Your name<input name="owner_name" required maxlength="120" autocomplete="name" placeholder="First and last name"></label><label class="full">Administrator email<input name="owner_email" type="email" required maxlength="190" autocomplete="email" placeholder="you@example.com"></label><label class="full">Administrator password<input name="owner_password" type="password" required minlength="14" maxlength="72" autocomplete="new-password" placeholder="A unique passphrase of 14+ characters"></label><label class="full">Confirm administrator password<input name="owner_confirm" type="password" required minlength="14" maxlength="72" autocomplete="new-password"></label></div>
<p class="hint">No default login and no installation key. Your administrator password is hashed before it is saved. Do not reuse your hosting or database password.</p></div></section></div>
<section class="panel" aria-labelledby="website-heading"><div class="panel-head"><div class="head-title"><span class="section-icon" aria-hidden="true">⌘</span><div class="head-copy"><h2 id="website-heading">Website details</h2><p>Keep your approved navy-and-gold PHP website intact.</p></div></div><span class="pill">CAJUNWEBPRO</span></div><div class="body"><div class="fields"><label>Website URL<input name="site_url" type="url" required maxlength="500" value="<?=esc($suggested)?>" autocomplete="url" spellcheck="false"></label><label>Public business email <span class="optional">optional</span><input name="business_email" type="email" maxlength="190" placeholder="hello@yourdomain.com" autocomplete="off"></label></div><div class="option-row"><label class="checkbox"><input name="seed" type="checkbox" checked><span><strong>Include the portfolio design concepts</strong>Three clearly labeled examples that you can replace with your actual work.</span></label><label class="checkbox"><input name="preview" type="checkbox" checked><span><strong>Keep search indexing disabled for now</strong>Review your content and settings before making the website searchable.</span></label></div></div></section>
<section class="panel" aria-labelledby="server-heading"><div class="panel-head"><div class="head-title"><span class="section-icon" aria-hidden="true">◇</span><div class="head-copy"><h2 id="server-heading">Server & private storage</h2><p>Automatic folder preparation. No root ownership changes.</p></div></div><button type="button" class="button" id="run-checks" <?=$secure?'':'disabled'?>>Recheck server ↻</button></div>
<div class="body"><div class="server-summary" id="server-summary" role="status">Checking your server and preparing private storage…</div><div id="system-checks" class="checks-grid"></div><p class="path-note" id="storage-note"></p><div class="storage-line"><label for="private-parent">Private storage parent <span class="optional">optional</span><input id="private-parent" name="private_parent" maxlength="1000" placeholder="Automatic — leave blank" autocomplete="off" spellcheck="false"></label><p>A PHP-owned permanent folder outside your public directory is preferred. Existing data is preserved. An empty bundled folder can be replaced with PHP-owned storage when needed. The selected path is saved in your configuration.</p></div><details class="diagnostics" id="storage-diagnostics" hidden><summary>Storage diagnostics</summary><p id="worker-note"></p><ul id="storage-attempts"></ul></details><details class="diagnostics" id="host-help" hidden><summary>Exact hosting-permission report</summary><p>Only needed when the server denies all safe write locations. This report contains no passwords.</p><textarea id="host-report" readonly rows="8" aria-label="Hosting permission report" spellcheck="false"></textarea></details></div></section>
<div class="launch-bar"><label class="checkbox"><input name="confirm_install" type="checkbox" required><span>I confirm this is the intended website directory and I have backed up any existing files. Existing application tables and installed websites will not be overwritten.</span></label><button type="submit" class="button gold" id="install-button" <?=$secure?'':'disabled'?>>Install CajunWebPro ↗</button></div>
<div id="working" class="status-box wait" role="status" hidden><span class="spinner" aria-hidden="true"></span><span id="working-text">Installing… Keep this page open.</span></div>
<p class="fineprint">Installation repeats the server and database checks, saves your account and configuration, locks setup, and attempts to delete this installer and its legacy files. Any failed deletion is reported. Finish setup promptly: an unfinished key-free installer is accessible to anyone who can reach this address.</p>
</form><footer class="bottom"><span>CAJUNWEBPRO / LAUNCH STUDIO</span><span>ONE PAGE · BUILD 1.1.0-rc.1 · NO KEY</span></footer></main>
<script nonce="<?=nonce()?>">
'use strict';
const form=document.getElementById('install-form');
const errorBox=document.getElementById('setup-error');
const secure=<?=json_encode($secure)?>;
const csrfToken=document.querySelector('meta[name="csrf-token"]').content;
let busy=false;
function showError(message){errorBox.textContent=message;errorBox.hidden=false;errorBox.scrollIntoView({block:'nearest'});}
function setBusy(value,label='Installing… Keep this page open.',freezeFields=false){
 busy=value;form.setAttribute('aria-busy',String(value));
 form.querySelectorAll('button').forEach(el=>el.disabled=value||!secure);
 form.querySelectorAll('input').forEach(el=>el.disabled=value&&freezeFields);
 document.getElementById('working').hidden=!value;
 document.getElementById('working-text').textContent=label;
 document.getElementById('install-button').textContent=value&&freezeFields?'Installing…':'Install CajunWebPro ↗';
}
async function send(action,data){
 data.set('action',action);data.set('csrf',csrfToken);
 const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),action==='install'?180000:30000);
 let res,result;
 try{
  const endpoint=window.location.pathname+'?build=1.1.0-rc.1';
  res=await fetch(endpoint,{method:'POST',body:data,cache:'no-store',credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}});
  try{result=await res.json();}catch(e){if(e.name==='AbortError')throw e;throw new Error('The server returned an unexpected response. Check admin.php before retrying an interrupted installation.');}
 }catch(e){if(e.name==='AbortError')throw new Error(action==='install'?'The request timed out; installation may still be running on the server. Open admin.php to check before trying again.':'The server check timed out. All your fields remain available. Recheck after reviewing the hosting error log.');throw e;}
 finally{clearTimeout(timer);}
 if(result.installer_build!=='1.1.0-rc.1')throw new Error('This response came from another installer build. Replace install.php in this website folder and reload. The page must say ONE-PAGE INSTALLER 1.1.0-rc.1.');
 if(!res.ok&&!result.message)result.message='This request could not complete. Check the host error log.';
 return result;
}
function renderChecks(result){
 if(result.package){
  const p=result.package,list=document.getElementById('package-issues');list.replaceChildren();
  document.getElementById('package-summary').textContent=p.ok?p.passed+' / '+p.expected+' required files verified.':p.issues.length+' missing or mismatched files. Your form remains available.';
  list.hidden=p.ok;
  for(const issue of p.issues){const li=document.createElement('li'),code=document.createElement('code');code.textContent=issue.path;li.append(code,document.createTextNode(' — '+issue.problem));list.append(li);}
 }
 if(!Array.isArray(result.checks))return;
 const holder=document.getElementById('system-checks');holder.replaceChildren();
 for(const c of result.checks){
  const row=document.createElement('div');row.className='check-row '+(c.ok?'passed':'failed');
  const icon=document.createElement('span');icon.className='check-icon';icon.setAttribute('aria-hidden','true');icon.textContent=c.ok?'✓':'!';
  const copy=document.createElement('div'),title=document.createElement('strong'),desc=document.createElement('small');
  title.textContent=c.label;desc.textContent=c.message;copy.append(title,desc);
  if(c.details){const p=document.createElement('small');p.className='path';p.textContent=c.details.path+' · owner '+(c.details.owner??'unknown')+' · group '+(c.details.group??'unknown')+' · '+(c.details.mode??'unknown');copy.append(p);}
  row.append(icon,copy);holder.append(row);
 }
 const ok=result.checks.every(c=>c.ok),summary=document.getElementById('server-summary');
 summary.className='server-summary '+(ok?'ready':'attention');summary.textContent=ok?'Server ready. Complete your details and press Install.':'Some checks need attention. All setup fields remain available on this page.';
 document.getElementById('storage-note').textContent=result.storage_note||'';
 const diagnostics=document.getElementById('storage-diagnostics');diagnostics.hidden=false;
 document.getElementById('worker-note').textContent='PHP worker: '+(result.host_report?.identity||('UID '+(result.php_uid??'unavailable')))+'. Only dedicated application storage is prepared; parent-directory permissions are not changed.';
 const permissionsFailed=result.checks.some(c=>['Website directory','Private storage','Protected media','Server preparation'].includes(c.label)&&!c.ok);
 const hostHelp=document.getElementById('host-help');hostHelp.hidden=!permissionsFailed||!result.host_report;hostHelp.open=!hostHelp.hidden;
 document.getElementById('host-report').value=result.host_report?.text||'';
 diagnostics.open=permissionsFailed;
 const list=document.getElementById('storage-attempts');list.replaceChildren();
 for(const a of result.attempts||[]){const li=document.createElement('li');li.textContent=a.path+' — '+a.message;list.append(li);}
}
async function checkServer(){
 if(busy||!secure)return;const data=new FormData();data.set('private_parent',form.elements.private_parent.value);
 errorBox.hidden=true;setBusy(true,'Checking server permissions and preparing a permanent private folder…');
 try{const r=await send('checks',data);renderChecks(r);if(!r.checks&&!r.ok)showError(r.message||'Server checks failed.');}
 catch(e){document.getElementById('server-summary').textContent='Checks could not finish. Your form remains available.';showError(e.message);}
 finally{setBusy(false);}
}
document.getElementById('run-checks').addEventListener('click',checkServer);
document.getElementById('private-parent').addEventListener('input',()=>{document.getElementById('server-summary').textContent='Storage preference changed. It will be rechecked when you install.';});
document.getElementById('test-database').addEventListener('click',async()=>{
 if(busy||!secure)return;const data=new FormData();
 for(const field of form.querySelectorAll('[name^="db_"]')){if(!field.reportValidity())return;data.set(field.name,field.value);}
 setBusy(true,'Testing your database connection…');errorBox.hidden=true;const box=document.getElementById('db-result');
 try{const r=await send('test_database',data);box.textContent=r.message||'Database test completed.';box.className='inline-result '+(r.ok?'':'bad');}
 catch(e){box.textContent=e.message;box.className='inline-result bad';}finally{setBusy(false);}
});
form.addEventListener('submit',async event=>{
 event.preventDefault();if(busy||!secure)return;if(!form.reportValidity())return;
 if(form.elements.owner_password.value!==form.elements.owner_confirm.value){showError('The administrator passwords do not match.');form.elements.owner_confirm.focus();return;}
 const data=new FormData(form);errorBox.hidden=true;setBusy(true,'Installing… Checking the server and database, creating your website, and removing setup files. Keep this page open.',true);
 try{
  const r=await send('install',data);renderChecks(r);if(!r.ok){showError(r.message||'Installation could not complete. Your entries are still here.');return;}
  form.querySelectorAll('input[type="password"]').forEach(input=>input.value='');
  const log=document.getElementById('completion-log');log.replaceChildren();for(const text of r.events||[]){const li=document.createElement('li');li.textContent=text;log.append(li);}
  const warnings=document.getElementById('completion-warnings');warnings.replaceChildren();for(const text of r.warnings||[]){const p=document.createElement('p');p.className='warning';p.textContent=text;warnings.append(p);}
  document.getElementById('open-dashboard').href=r.admin;document.getElementById('open-website').href=r.site;
  form.hidden=true;const done=document.getElementById('completion');done.hidden=false;done.scrollIntoView({block:'start'});
 }catch(e){showError(e.message+' If the final request was interrupted, open admin.php before starting another installation.');}
 finally{setBusy(false);}
});
if(secure)checkServer();else document.getElementById('server-summary').textContent='Enable HTTPS, then reload to run the server checks.';
</script></body></html>
