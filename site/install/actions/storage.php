<?php
declare(strict_types=1);
if (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }
/** Storage is persistent and site-specific; never use system temporary storage. */
final class SetupStorageConflict extends RuntimeException {}
function setup_root(): string { return realpath(CWP_ROOT) ?: CWP_ROOT; }
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
        if (in_array($item,['media','upload'],true) && @is_dir($p)) {
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
            $prior=$GLOBALS['cwp_config']??null;$GLOBALS['cwp_config']=['storage'=>$dir];
            try{$staging=upload_staging_directory();$stageProbe=writable_probe($staging,false,0700);if(!$stageProbe['ok'])throw new RuntimeException('Upload staging operations failed.');}finally{if($prior===null)unset($GLOBALS['cwp_config']);else $GLOBALS['cwp_config']=$prior;}
            $probe['message']='Ready: '.$candidate['type'].'. PHP-owned, persistent, 0700; file create/read/append/rename/delete verified.';
            $media['message']='Ready. Protected media folder created and verified under the selected private storage.';
            return ['ok'=>true,'storage'=>$dir,'private'=>$probe,'media'=>$media,'attempts'=>$attempts,
                'message'=>$templateBackup!==null?'Created PHP-owned storage. The empty original package template is preserved at '.$templateBackup.'.':($dir!==$local && @is_dir($local)?'The bundled storage folder was left unchanged. The website will use the selected private folder instead.':'The selected storage path will be saved in config.php.')];
        }catch(Throwable $e){$attempts[]=['path'=>$dir,'ok'=>false,'message'=>$e->getMessage()];}
    }
    return ['ok'=>false,'storage'=>'','attempts'=>$attempts,'message'=>'No safe, persistent location was writable by PHP. Your host must grant this domain\'s PHP worker access to a private parent folder and the website folder. Do not make the whole site world-writable.'];
}
