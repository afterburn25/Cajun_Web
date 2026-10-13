<?php
declare(strict_types=1);
defined('CWP_BOOT') || exit;
ini_set('display_errors','0');
ini_set('log_errors','1');
const CWP_VERSION = '1.7.0';
const CWP_GUARD = "<?php http_response_code(404); exit; ?>\n";
const CWP_ROOT = __DIR__ . '/..';

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
    require_once CWP_ROOT.'/app/staff.php';
    $u=staff_profile($u);
    if(!$u){unset($_SESSION['uid'],$_SESSION['auth_version']);return null;}
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
