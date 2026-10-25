<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Bounded file migration plus journal-recorded schema-marker recovery. No data migrations, recursive deletion or other configuration changes. */
function structure_path(string $root, string $name): string {
    // Maintained locale files use @ modifiers; both dot-segment forms remain forbidden.
    if (!preg_match('~^[a-zA-Z0-9_@.-]+(?:/[a-zA-Z0-9_@.-]+)*$~D',$name) || array_intersect(['.','..'],explode('/',$name))) throw new RuntimeException('Unsafe update path.');
    $path=rtrim($root,'/');
    foreach(explode('/',$name) as $part) {
        $path.='/'.$part;
        if(is_link($path)) throw new RuntimeException('A symbolic link was preserved: '.$name);
    }
    return $path;
}
function structure_hash(string $path): ?string {
    clearstatcache(true,$path);
    if(is_link($path) || (file_exists($path) && !is_file($path))) throw new RuntimeException('Unexpected file type: '.basename($path));
    return is_file($path)?hash_file('sha256',$path):null;
}
function structure_store(): string {
    return structure_store_version(CWP_VERSION);
}
function structure_store_version(string $version): string {
    $store=realpath(config()['storage']);
    if(!$store || is_link(config()['storage'])) throw new RuntimeException('The configured private store is unavailable. It was not relocated.');
    $dir=structure_path($store,'structure-'.$version);
    if(!is_dir($dir) && !@mkdir($dir,0700)) throw new RuntimeException('Cannot create protected update recovery storage.');
    if(!@chmod($dir,0700)) throw new RuntimeException('Cannot protect update recovery storage.');
    foreach(['.htaccess'=>"Require all denied\n",'index.php'=>"<?php http_response_code(404); exit;\n"] as $name=>$bytes) {
        $path=structure_path($dir,$name);
        if(!file_exists($path)) write_private_file($path,$bytes);
        elseif(file_get_contents($path)!==$bytes) throw new RuntimeException('Recovery protection differs. Nothing was overwritten.');
    }
    return $dir;
}
function structure_lock(string $dir) {
    // Share the original permanent inode with retained 1.11.0 owner tools.
    // Recovery journals stay version-specific; competing versions cannot run together.
    $path=structure_path(structure_store_version('1.11.0'),'mutex.php');$mask=umask(0077);
    try{$fp=@fopen($path,'c+b');}finally{umask($mask);}
    if(!$fp) throw new RuntimeException('Cannot open the permanent update lock.');
    if(!flock($fp,LOCK_EX|LOCK_NB)){fclose($fp);throw new RuntimeException('Another update or recovery is running. Retry after it finishes.');}
    if(!@chmod($path,0600)){fclose($fp);throw new RuntimeException('Cannot secure the update lock.');}
    rewind($fp);fwrite($fp,CWP_GUARD);fflush($fp);
    return $fp; // Keep this inode permanently; never unlink a lock another request can hold.
}
function structure_journal(string $dir): ?array {
    $path=structure_path($dir,'journal.php');
    if(!file_exists($path))return null;
    $raw=file_get_contents($path);
    if(!str_starts_with($raw,CWP_GUARD))throw new RuntimeException('Recovery journal is unrecognized.');
    $j=json_decode(substr($raw,strlen(CWP_GUARD)),true,64,JSON_THROW_ON_ERROR);
    if(($j['root']??'')!==(realpath(CWP_ROOT)?:CWP_ROOT))throw new RuntimeException('Recovery belongs to another installation.');
    return $j;
}
function structure_save(string $dir,array $j): void {
    $tmp=$dir.'/journal-'.bin2hex(random_bytes(8)).'.php';
    write_private_file($tmp,CWP_GUARD.json_encode($j,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
    if(!@rename($tmp,structure_path($dir,'journal.php'))){@unlink($tmp);throw new RuntimeException('Could not commit the recovery journal.');}
}
function structure_schema_markers(): array {
    $markers=[];
    foreach(all('SELECT name,value FROM '.table('settings')) as $row)
        if(str_ends_with((string)$row['name'],'_schema_version'))$markers[(string)$row['name']]=(string)$row['value'];
    return $markers;
}
function structure_restore_markers(array $markers): void {
    // Only the journal-recorded marker names are restored; additive newer markers stay intact.
    foreach($markers as $name=>$value)save_setting($name,$value);
}
function structure_verified_payload(string $payload,array $manifest): void {
    if(($manifest['version']??'')!==CWP_VERSION || count($manifest['files']??[])<100)throw new RuntimeException('Unrecognized structure update manifest.');
    foreach($manifest['files'] as $name=>$hash) {
        if(structure_preserved_path($name))throw new RuntimeException('The update payload must not contain installed state: '.$name);
        if(!hash_equals($hash,structure_hash(structure_path($payload,$name))??''))throw new RuntimeException('Restore the exact UPDATE package; missing or mismatched replacement: '.$name);
    }
}
function structure_preserved_path(string $name): bool {
    return in_array($name,['config.php','proxy-config.php','.installed.php','.installation-mutex.php','install/.htaccess'],true)
        || str_starts_with($name,'storage/') || str_starts_with($name,'upload/');
}
function structure_unknown_files(array $known,array $current): array {
    $preserved=[];$count=0;
    foreach(new DirectoryIterator(CWP_ROOT) as $entry){
        if($entry->isDot())continue;$name=$entry->getFilename();
        if(isset($known[$name])||isset($current[$name])||in_array($name,['config.php','proxy-config.php','.installed.php','.installation-mutex.php','storage','upload','cwp-update-'.CWP_VERSION],true))continue;
        if($entry->isDir()&&!$entry->isLink()){
            if(in_array($name,['app','assets'],true)){
                $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entry->getPathname(),FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::LEAVES_ONLY);
                foreach($files as $file){
                    if(++$count>5000)throw new RuntimeException('More than 5000 legacy-area entries require owner review. Nothing was removed.');
                    $relative=str_replace('\\','/',substr($file->getPathname(),strlen(CWP_ROOT)+1));
                    if(!isset($known[$relative])&&!isset($current[$relative]))$preserved[]=$relative;
                }
            }elseif(!array_filter(array_keys($current),fn($n)=>str_starts_with($n,$name.'/')))$preserved[]=$name.'/';
        }else $preserved[]=$name;
    }
    return $preserved;
}
function structure_plan(string $payload,array $manifest,array $history): array {
    structure_verified_payload($payload,$manifest);
    if(!is_file(CWP_ROOT.'/config.php') || is_link(CWP_ROOT.'/config.php'))throw new RuntimeException('An existing installed configuration is required. Do not use this update for fresh setup.');
    $defaults=structure_hash(structure_path(CWP_ROOT,'config/defaults.php'));
    $legacy=$defaults===null;$identity=$legacy?structure_hash(structure_path(CWP_ROOT,'app/core.php')):$defaults;
    $identityPath=$legacy?'app/core.php':'config/defaults.php';$version=null;$known=[];
    foreach($history as $v=>$release){
        if($identity!==null && ($release['files'][$identityPath]??null)===$identity)$version=$v;
        foreach($release['files']+($release['upgrade_shims']??[]) as $name=>$hash)$known[$name][$hash]=true;
    }
    if(!$version)throw new RuntimeException('The starting application is not in this package\'s verified release inventory. Customized version/bootstrap files were preserved. Restore or review them from your backup first.');
    $ops=[];$preserved=structure_unknown_files($known,$manifest['files']);
    foreach($manifest['files'] as $name=>$hash){
        $old=structure_hash(structure_path(CWP_ROOT,$name));
        if($old===$hash)continue;
        if($old!==null && !isset($known[$name][$old]))throw new RuntimeException('Customized destination preserved: '.$name.'. Review it before retrying; the update has not started.');
        $ops[$name]=['old'=>$old,'new'=>$hash,'source'=>$name];
    }
    foreach($known as $name=>$hashes){
        if(isset($manifest['files'][$name]) || $name==='.htaccess' || structure_preserved_path($name))continue;
        $old=structure_hash(structure_path(CWP_ROOT,$name));
        if($old===null)continue;
        if(!isset($hashes[$old])){
            if(in_array($name,['admin.php','portal.php','api.php','activity.php','media.php','sandbox-notifications.php','index.php','install.php'],true))throw new RuntimeException('Customized legacy entry preserved: '.$name.'. Review this privileged entry before retrying; no switch occurred.');
            $preserved[]=$name;continue;
        }
        $ops[$name]=['old'=>$old,'new'=>null,'source'=>null];
    }
    // Only upgrade installations receive these direct-dispatch compatibility entries.
    foreach($manifest['shims'] as $name=>$bytes){
        $old=structure_hash(structure_path(CWP_ROOT,$name));
        if(!$legacy && $old===null)continue; // Never add old routes to a fresh organized installation.
        if($old!==null && !isset($known[$name][$old]))throw new RuntimeException('Customized compatibility entry preserved: '.$name);
        $ops[$name]=['old'=>$old,'new'=>hash('sha256',$bytes),'bytes'=>$bytes];
    }
    // Back up the old server policy even if it was already identical to the replacement.
    $ops['.htaccess']=['old'=>structure_hash(CWP_ROOT.'/.htaccess'),'new'=>$manifest['files']['.htaccess'],'source'=>'.htaccess'];
    return ['root'=>realpath(CWP_ROOT)?:CWP_ROOT,'version'=>$version,'target'=>CWP_VERSION,'commit'=>$manifest['source_commit'],'backup_id'=>bin2hex(random_bytes(12)),'state'=>'planned','schema_markers'=>structure_schema_markers(),'operations'=>$ops,'preserved'=>$preserved,'created_at'=>gmdate('c')];
}
function structure_backup(string $dir,string $name,string $hash,string $id): void {
    $backup=structure_path($dir,$id.'-'.hash('sha256',$name).'.php');
    if(file_exists($backup)){
        $data=file_get_contents($backup);
        if(!str_starts_with($data,CWP_GUARD)||!hash_equals($hash,hash('sha256',substr($data,strlen(CWP_GUARD)))))throw new RuntimeException('Recovery copy mismatch for '.$name);
        return;
    }
    $path=structure_path(CWP_ROOT,$name);
    if(!hash_equals($hash,structure_hash($path)??''))throw new RuntimeException('A source file changed before backup: '.$name);
    write_private_file($backup,CWP_GUARD.file_get_contents($path));
}
function structure_replace(string $name,string $bytes): void {
    $path=structure_path(CWP_ROOT,$name);$parent=dirname($path);
    if(!is_dir($parent) && !@mkdir($parent,0755,true))throw new RuntimeException('Cannot create replacement directory for '.$name);
    // Same-directory atomic rename prevents partially written PHP endpoints.
    $tmp=$parent.'/.structure-'.bin2hex(random_bytes(8)).'.php';
    write_private_file($tmp,$bytes);
    if(!@chmod($tmp,0644) || !@rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Cannot replace '.$name.'. Recovery copies remain available.');}
    structure_refresh_php($path);
}
function structure_refresh_php(string $path): void {
    if(str_ends_with($path,'.php') && function_exists('opcache_is_script_cached') && opcache_is_script_cached($path)) {
        if(!opcache_invalidate($path,true))throw new RuntimeException('The host could not invalidate cached PHP for '.basename($path).'. Keep maintenance protection and ask the host to clear this site cache before retrying.');
    }
}
function structure_maintenance(): string {
    return "# Temporary owner-authenticated structure update. Retry or recover using the staged owner page.\nOptions -Indexes\nRequire all denied\n<Files \"owner-update.php\">\nRequire all granted\n</Files>\n";
}
function structure_enter_maintenance(array $j): void {
    $actual=structure_hash(CWP_ROOT.'/.htaccess');$op=$j['operations']['.htaccess'];
    if(!in_array($actual,[$op['old'],$op['new'],hash('sha256',structure_maintenance())],true))throw new RuntimeException('Server policy changed after planning; preserved. Review .htaccess before retrying or recovering.');
    structure_replace('.htaccess',structure_maintenance());
}
/** Caller owns the persistent lock. A CLI-only hook supports real interruption/lock acceptance. */
function structure_apply(string $payload,array $manifest,array $history,string $dir,?callable $checkpoint=null): array {
    structure_verified_payload($payload,$manifest);
    $j=structure_journal($dir);
    if($j && ($j['commit']!==$manifest['source_commit'] || $j['target']!==CWP_VERSION))throw new RuntimeException('Recovery belongs to a different build. Use that exact UPDATE package.');
    if($j && $j['state']==='complete')return $j;
    if($j && $j['state']==='rolled-back'){
        $forward=$j['schema_markers_forward']??null;
        $next=structure_plan($payload,$manifest,$history);
        $archive=$dir.'/recovered-'.$j['backup_id'].'.php';$bytes=CWP_GUARD.json_encode($j,JSON_THROW_ON_ERROR);
        if(file_exists($archive)){if(is_link($archive)||file_get_contents($archive)!==$bytes)throw new RuntimeException('Recovered journal archive differs. Keep it for owner review.');}
        else write_private_file($archive,$bytes);
        if($forward!==null)$next['schema_markers_forward']=$forward;
        $j=$next;structure_save($dir,$j);
    }
    if(!$j){$j=structure_plan($payload,$manifest,$history);structure_save($dir,$j);}
    if(!isset($j['schema_markers'])){$j['schema_markers']=structure_schema_markers();structure_save($dir,$j);}
    if($j['state']==='planned'){
        foreach($j['operations'] as $name=>$op)if($op['old']!==null)structure_backup($dir,$name,$op['old'],$j['backup_id']);
        $j['state']='backed-up';structure_save($dir,$j);
    }
    structure_enter_maintenance($j);
    $j['state']='applying';structure_save($dir,$j);
    foreach($j['operations'] as $name=>$op){
        if($name==='.htaccess')continue;
        $path=structure_path(CWP_ROOT,$name);$actual=structure_hash($path);
        if($actual===$op['new'])continue; // Includes interrupted deletion and successful atomic replacement.
        if($actual!==$op['old'])throw new RuntimeException('File changed during update; preserved: '.$name.'. Keep maintenance protection and recover or review before retrying.');
        if($op['new']===null){structure_refresh_php($path);if(!@unlink($path))throw new RuntimeException('Cannot retire '.$name.'. Retry or recover; backup is retained.');}
        else{ $bytes=$op['bytes']??file_get_contents(structure_path($payload,$op['source'])); if(!hash_equals($op['new'],hash('sha256',$bytes)))throw new RuntimeException('Replacement changed: '.$name);structure_replace($name,$bytes); }
        $j['last_completed']=$name;structure_save($dir,$j);
        if(PHP_SAPI==='cli' && $checkpoint)$checkpoint($name);
    }
    foreach($j['operations'] as $name=>$op){
        if($name==='.htaccess')continue;$path=structure_path(CWP_ROOT,$name);
        if(structure_hash($path)!==$op['new'])throw new RuntimeException('Final verification failed: '.$name);
        if($op['new']!==null)structure_refresh_php($path);
    }
    structure_replace('.htaccess',file_get_contents($payload.'/.htaccess'));
    if(isset($j['schema_markers_forward'])){structure_restore_markers($j['schema_markers_forward']);unset($j['schema_markers_forward']);}
    $j['state']='complete';$j['completed_at']=gmdate('c');structure_save($dir,$j);return $j;
}
function structure_recover(string $dir): array {
    $j=structure_journal($dir);if(!$j)throw new RuntimeException('No saved update attempt exists.');
    if($j['state']==='rolled-back')return $j;
    if($j['state']==='planned')throw new RuntimeException('Backup preparation is incomplete. Retry preparation before recovery; application files have not changed.');
    structure_enter_maintenance($j);
    foreach(array_reverse($j['operations'],true) as $name=>$op){
        if($name==='.htaccess')continue;
        $path=structure_path(CWP_ROOT,$name);$actual=structure_hash($path);
        if($actual===$op['old'])continue;
        if($actual!==$op['new'])throw new RuntimeException('Recovery preserved a subsequently edited file: '.$name.'. Review before retrying recovery.');
        if($op['old']===null){if($actual!==null && !@unlink($path))throw new RuntimeException('Recovery cannot remove its unchanged new file: '.$name);}
        else{
            $backup=structure_path($dir,$j['backup_id'].'-'.hash('sha256',$name).'.php');$bytes=file_get_contents($backup);
            if(!str_starts_with($bytes,CWP_GUARD) || !hash_equals($op['old'],hash('sha256',substr($bytes,strlen(CWP_GUARD)))))throw new RuntimeException('Recovery copy invalid: '.$name);
            structure_replace($name,substr($bytes,strlen(CWP_GUARD)));
        }
        $j['last_restored']=$name;structure_save($dir,$j);
    }
    $old=$j['operations']['.htaccess']['old'];
    if($old!==null){$bytes=file_get_contents($dir.'/'.$j['backup_id'].'-'.hash('sha256','.htaccess').'.php');if(!str_starts_with($bytes,CWP_GUARD)||!hash_equals($old,hash('sha256',substr($bytes,strlen(CWP_GUARD)))))throw new RuntimeException('Server policy recovery copy invalid.');structure_replace('.htaccess',substr($bytes,strlen(CWP_GUARD)));}
    else if(!@unlink(CWP_ROOT.'/.htaccess'))throw new RuntimeException('Cannot restore absent server policy.');
    // Recovered code expects the marker state recorded before this update; the applied set is kept for reapplication.
    if(isset($j['schema_markers'])){$j['schema_markers_forward']=structure_schema_markers();structure_restore_markers($j['schema_markers']);}
    $j['state']='rolled-back';structure_save($dir,$j);return $j;
}
