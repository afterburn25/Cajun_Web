<?php
/** Owner-only structural update. This cannot create an installation or reset credentials. */
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_ROOT',dirname(__DIR__));
define('CWP_CODE_ROOT',__DIR__.'/site');
if(!is_file(CWP_ROOT.'/config.php') || is_link(CWP_ROOT.'/config.php')){http_response_code(403);exit('An existing installed website is required. Use COMPLETE-UPLOAD for fresh installation.');}
// An interrupted upload may omit the bootstrap itself. Fail before any application include.
try {
    if(!is_file(__DIR__.'/manifest.php') || is_link(__DIR__.'/manifest.php'))throw new RuntimeException('manifest.php');
    $manifest=require __DIR__.'/manifest.php';
    if(!is_array($manifest['files']??null) || count($manifest['files'])<100)throw new RuntimeException('manifest.php');
    if(!is_file(__DIR__.'/legacy-release-files.json') || is_link(__DIR__.'/legacy-release-files.json') || !hash_equals($manifest['history_sha256']??'',(string)hash_file('sha256',__DIR__.'/legacy-release-files.json')))throw new RuntimeException('legacy-release-files.json');
    foreach($manifest['files'] as $name=>$hash){
        if(!preg_match('~^[a-zA-Z0-9_@.-]+(?:/[a-zA-Z0-9_@.-]+)*$~D',$name) || array_intersect(['.','..'],explode('/',$name)))throw new RuntimeException('manifest.php');
        $path=CWP_CODE_ROOT;
        foreach(explode('/',$name) as $part){$path.='/'.$part;if(is_link($path))throw new RuntimeException($name);}
        if(!is_file($path) || !hash_equals($hash,(string)hash_file('sha256',$path)))throw new RuntimeException($name);
    }
} catch(Throwable $e) {
    http_response_code(503);header('Cache-Control: no-store');header('Content-Type: text/plain; charset=utf-8');
    exit('Update support is missing or mismatched. Restore the exact UPDATE package, including its staged site/ directory, then reopen this owner page. No application switch or configuration change was attempted.');
}
require CWP_CODE_ROOT.'/includes/bootstrap.php';
boot();
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/updates/structure.php';
header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
$history=json_decode(file_get_contents(__DIR__.'/legacy-release-files.json'),true,64,JSON_THROW_ON_ERROR);
$error='';$result=null;$actor=admin_user();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $lock=null;
    try{
        check_csrf();
        if(!consume_rate(ip_key('structure-owner'),10,900))throw new RuntimeException('Please wait before another owner authentication attempt.');
        $password=posted('owner_password',1000);
        if(!$actor){
            $user=one('SELECT * FROM '.table('users').' WHERE email=?',[strtolower(posted('email',190))]);
            if(!$user || !password_verify($password,$user['password_hash']) || !($profile=staff_profile($user)) || $profile['role']!=='owner')throw new RuntimeException('Owner authentication failed.');
            login_user($user);$actor=admin_user();
        }
        if(!$actor || $actor['role']!=='owner' || !password_verify($password,$actor['password_hash']))throw new RuntimeException('Your current owner password is required.');
        if(posted('backup_confirm',1)!=='1')throw new RuntimeException('Confirm a recoverable backup of website files, database and configured private storage first.');
        $dir=structure_store();$lock=structure_lock($dir);
        $action=posted('action',30);
        if($action==='inspect'){
            $j=structure_journal($dir);$result=$j?:structure_plan(__DIR__.'/site',$manifest,$history);
        }elseif($action==='apply')$result=structure_apply(__DIR__.'/site',$manifest,$history,$dir);
        elseif($action==='recover'){
            require_once CWP_CODE_ROOT.'/app/hosting/catalog_recovery.php';
            $result=hosting_catalog_recover_files($dir,(int)$actor['id'],(string)$actor['auth_version'],$password);
        }
        else throw new RuntimeException('Unknown update action.');
        log_event('structure.'.$action,'Version '.CWP_VERSION.'; state '.$result['state']);
    }catch(Throwable $e){$error=$e instanceof PDOException?'Database authentication is unavailable. No setup or reset was attempted.':$e->getMessage();}
    finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
}
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CajunWebPro · Owner update</title>
<style>body{max-width:850px;margin:5vh auto;padding:24px;background:#06101f;color:#edf2f8;font:17px/1.6 system-ui}label{display:block;margin:14px 0}input{font:inherit}input:not([type=checkbox]){display:block;max-width:100%;padding:10px}button{font:inherit;padding:12px;margin:8px 8px 8px 0}a{color:#f5cd78}.error{color:#ffb8a5}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style>
<h1>Update CajunWebPro to <?=esc(CWP_VERSION)?></h1>
<p>This owner tool verifies the staged package, preserves configuration and private data, and replaces only recognized release files. It does not create tables or enable new payment or provider features.</p>
<p>First download a recoverable copy of your website, database and configured private storage through your hosting panel. Keep this UPDATE directory available until you have verified the website. During file changes, the website briefly enters maintenance protection; this page remains available to retry or recover.</p>
<?php if($error):?><p class="error" role="alert"><?=esc($error)?></p><?php endif;?>
<?php if($result):?><h2><?=esc(ucfirst($result['state']))?></h2><p>Starting release: <?=esc($result['version'])?>. <?=count($result['operations'])?> bounded file operations.</p><?php if($result['preserved']):?><p>Customized files kept in place:</p><pre><?=esc(implode("\n",$result['preserved']))?></pre><?php endif;?>
<?php if($result['state']==='complete'):?><p>The organized website is active. <a href="<?=esc(admin_url())?>">Open administration</a>. Confirm public pages, private downloads and callbacks before removing the exact staging directory through your hosting panel. Keep the protected recovery copies with your backup.</p><?php endif;?>
<?php if($result['state']==='rolled-back'):?><p>Recognized original files were restored. Configuration, database and private files were not reset. Keep the recovery journal. Review the failure and your backup before starting another owner-confirmed attempt.</p><?php endif;?>
<?php endif;?>
<form method="post"><?=csrf_field()?>
<?php if(!$actor):?><label>Owner email<input name="email" type="email" required autocomplete="username"></label><?php else:?><p>Signed in as <?=esc($actor['email'])?>.</p><?php endif;?>
<label>Current owner password<input name="owner_password" type="password" required autocomplete="current-password"></label>
<label><input name="backup_confirm" value="1" type="checkbox" required> I have a recoverable backup of the website, database and configured private storage.</label>
<button name="action" value="inspect">Verify and inspect</button><button name="action" value="apply">Apply or retry update</button><button name="action" value="recover">Recover original files</button>
</form></html>
