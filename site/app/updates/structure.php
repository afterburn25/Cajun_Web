<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Bounded file migration only. No database migrations, recursive deletion or configuration changes. */
function structure_path(string $root, string $name): string {
    if (!preg_match('~^[a-zA-Z0-9_.-]+(?:/[a-zA-Z0-9_.-]+)*$~D',$name) || in_array('..',explode('/',$name),true)) throw new RuntimeException('Unsafe update path.');
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
    $store=realpath(config()['storage']);
    if(!$store || is_link(config()['storage'])) throw new RuntimeException('The configured private store is unavailable. It was not relocated.');
    $dir=structure_path($store,'structure-1.11.0');
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
    $path=structure_path($dir,'mutex.php');$mask=umask(0077);
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
function structure_verified_payload(string $payload,array $manifest): void {
    if(($manifest['version']??'')!==CWP_VERSION || count($manifest['files']??[])<100)throw new RuntimeException('Unrecognized structure update manifest.');
    foreach($manifest['files'] as $name=>$hash) {
        if(!hash_equals($hash,structure_hash(structure_path($payload,$name))??''))throw new RuntimeException('Restore the exact UPDATE package; missing or mismatched replacement: '.$name);
    }
}
function structure_plan(string $payload,array $manifest,array $history): array {
    structure_verified_payload($payload,$manifest);
    if(!is_file(CWP_ROOT.'/config.php') || is_link(CWP_ROOT.'/config.php'))throw new RuntimeException('An existing installed configuration is required. Do not use this update for fresh setup.');
    $core=structure_hash(structure_path(CWP_ROOT,'app/core.php'));$version=null;$known=[];
    foreach($history as $v=>$release){
        if(($release['files']['app/core.php']??null)===$core)$version=$v;
        foreach($release['files'] as $name=>$hash)$known[$name][$hash]=true;
    }
    if(!$version)throw new RuntimeException('The starting application is not a recognized 1.0.8–1.10.0 release. Customized bootstrap files were preserved. Restore or review them from your backup first.');
    $ops=[];$preserved=[];
    foreach($manifest['files'] as $name=>$hash){
        $old=structure_hash(structure_path(CWP_ROOT,$name));
        if($old===$hash)continue;
        if($old!==null && !isset($known[$name][$old]))throw new RuntimeException('Customized destination preserved: '.$name.'. Review it before retrying; the update has not started.');
        $ops[$name]=['old'=>$old,'new'=>$hash,'source'=>$name];
    }
    foreach($known as $name=>$hashes){
        if(isset($manifest['files'][$name]) || in_array($name,['.htaccess'],true))continue;
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
        if($old!==null && !isset($known[$name][$old]))throw new RuntimeException('Customized compatibility entry preserved: '.$name);
        $ops[$name]=['old'=>$old,'new'=>hash('sha256',$bytes),'bytes'=>$bytes];
    }
    // Back up the old server policy even if it was already identical to the replacement.
    $ops['.htaccess']=['old'=>structure_hash(CWP_ROOT.'/.htaccess'),'new'=>$manifest['files']['.htaccess'],'source'=>'.htaccess'];
    return ['root'=>realpath(CWP_ROOT)?:CWP_ROOT,'version'=>$version,'target'=>CWP_VERSION,'commit'=>$manifest['source_commit'],'state'=>'planned','operations'=>$ops,'preserved'=>$preserved,'created_at'=>gmdate('c')];
}
function structure_backup(string $dir,string $name,string $hash): void {
    $backup=structure_path($dir,hash('sha256',$name).'.php');
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
    if(function_exists('opcache_invalidate'))@opcache_invalidate($path,true);
}
function structure_maintenance(): string {
    return "# Temporary owner-authenticated structure update. Retry or recover using the staged owner page.\nOptions -Indexes\nRequire all denied\n<Files \"owner-update.php\">\nRequire all granted\n</Files>\n";
}
/** Caller owns the persistent lock. A CLI-only hook supports real interruption/lock acceptance. */
function structure_apply(string $payload,array $manifest,array $history,string $dir,?callable $checkpoint=null): array {
    structure_verified_payload($payload,$manifest);
    $j=structure_journal($dir);
    if($j && ($j['commit']!==$manifest['source_commit'] || $j['target']!==CWP_VERSION))throw new RuntimeException('Recovery belongs to a different build. Use that exact UPDATE package.');
    if($j && $j['state']==='complete')return $j;
    if($j && $j['state']==='rolled-back')throw new RuntimeException('This attempt was recovered. Keep its backup; start again with a fresh copy after the owner reviews it.');
    if(!$j){$j=structure_plan($payload,$manifest,$history);structure_save($dir,$j);}
    if($j['state']==='planned'){
        foreach($j['operations'] as $name=>$op)if($op['old']!==null)structure_backup($dir,$name,$op['old']);
        $j['state']='backed-up';structure_save($dir,$j);
    }
    structure_replace('.htaccess',structure_maintenance());
    $j['state']='applying';structure_save($dir,$j);
    foreach($j['operations'] as $name=>$op){
        if($name==='.htaccess')continue;
        $path=structure_path(CWP_ROOT,$name);$actual=structure_hash($path);
        if($actual===$op['new'])continue; // Includes interrupted deletion and successful atomic replacement.
        if($actual!==$op['old'])throw new RuntimeException('File changed during update; preserved: '.$name.'. Keep maintenance protection and recover or review before retrying.');
        if($op['new']===null){if(!@unlink($path))throw new RuntimeException('Cannot retire '.$name.'. Retry or recover; backup is retained.');}
        else{ $bytes=$op['bytes']??file_get_contents(structure_path($payload,$op['source'])); if(!hash_equals($op['new'],hash('sha256',$bytes)))throw new RuntimeException('Replacement changed: '.$name);structure_replace($name,$bytes); }
        $j['last_completed']=$name;structure_save($dir,$j);
        if(PHP_SAPI==='cli' && $checkpoint)$checkpoint($name);
    }
    foreach($j['operations'] as $name=>$op)if($name!=='.htaccess' && structure_hash(structure_path(CWP_ROOT,$name))!==$op['new'])throw new RuntimeException('Final verification failed: '.$name);
    structure_replace('.htaccess',file_get_contents($payload.'/.htaccess'));
    $j['state']='complete';$j['completed_at']=gmdate('c');structure_save($dir,$j);return $j;
}
function structure_recover(string $dir): array {
    $j=structure_journal($dir);if(!$j)throw new RuntimeException('No saved update attempt exists.');
    if($j['state']==='rolled-back')return $j;
    if($j['state']==='planned')throw new RuntimeException('Backup preparation is incomplete. Retry preparation before recovery; application files have not changed.');
    structure_replace('.htaccess',structure_maintenance());
    foreach(array_reverse($j['operations'],true) as $name=>$op){
        if($name==='.htaccess')continue;
        $path=structure_path(CWP_ROOT,$name);$actual=structure_hash($path);
        if($actual===$op['old'])continue;
        if($actual!==$op['new'])throw new RuntimeException('Recovery preserved a subsequently edited file: '.$name.'. Review before retrying recovery.');
        if($op['old']===null){if($actual!==null && !@unlink($path))throw new RuntimeException('Recovery cannot remove its unchanged new file: '.$name);}
        else{
            $backup=structure_path($dir,hash('sha256',$name).'.php');$bytes=file_get_contents($backup);
            if(!str_starts_with($bytes,CWP_GUARD) || !hash_equals($op['old'],hash('sha256',substr($bytes,strlen(CWP_GUARD)))))throw new RuntimeException('Recovery copy invalid: '.$name);
            structure_replace($name,substr($bytes,strlen(CWP_GUARD)));
        }
        $j['last_restored']=$name;structure_save($dir,$j);
    }
    $old=$j['operations']['.htaccess']['old'];
    if($old!==null){$bytes=file_get_contents($dir.'/'.hash('sha256','.htaccess').'.php');if(!str_starts_with($bytes,CWP_GUARD)||!hash_equals($old,hash('sha256',substr($bytes,strlen(CWP_GUARD)))))throw new RuntimeException('Server policy recovery copy invalid.');structure_replace('.htaccess',substr($bytes,strlen(CWP_GUARD)));}
    else if(!@unlink(CWP_ROOT.'/.htaccess'))throw new RuntimeException('Cannot restore absent server policy.');
    $j['state']='rolled-back';structure_save($dir,$j);return $j;
}
