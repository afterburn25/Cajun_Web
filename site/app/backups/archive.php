<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/backups/records.php';
use CajunWebPro\Foundation\Backups\{BackupManifest,RestorePlan};

/** Mirror of BackupManifest::FORBIDDEN applied during collection so a real forbidden file is skipped, not fatal. */
function backups_item_allowed(string $name): bool {
    return !preg_match('#^(config\.php|proxy-config\.php|\.installed\.php|\.env|.*\.(key|pem|p12)|storage/|.*payment-master)#i',$name)
        && !preg_match('#(^|/)(\.|\.git|__MACOSX)(/|$)#',$name)
        && !preg_match('#(^|/)\.\.(/|$)#',$name);
}

/** Enumerate the deployable tree into a deterministic identifier => sha256 map. */
function backups_collect(string $root): array {
    $real=realpath($root);if($real===false||is_link($root)||!is_dir($real))throw new RuntimeException('The deployed source root is unavailable.');
    $items=[];$bytes=0;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS|FilesystemIterator::CURRENT_AS_FILEINFO),RecursiveIteratorIterator::LEAVES_ONLY);
    foreach($it as $file){
        if($file->isLink()||!$file->isFile())continue;
        $name=str_replace('\\','/',substr($file->getPathname(),strlen($real)+1));
        if(!backups_item_allowed($name))continue;
        $hash=hash_file('sha256',$file->getPathname());if($hash===false)throw new RuntimeException('A source file could not be hashed: '.$name);
        $items[$name]=$hash;$bytes+=$file->getSize();
        if(count($items)>50000||$bytes>536870912)throw new RuntimeException('The deployable tree exceeds the supported backup bound (50,000 files / 512 MiB).');
    }
    ksort($items);if($items===[])throw new RuntimeException('No deployable files are available to back up.');
    return ['items'=>$items,'bytes'=>$bytes];
}

/** Write the collected items into a fresh ZIP archive; returns the archive size. */
function backups_write_archive(string $root,array $items,string $dest): int {
    if(!class_exists('ZipArchive'))throw new RuntimeException('Ask your host to enable the PHP ZIP extension before creating backups.');
    $real=realpath($root);$zip=new ZipArchive();
    if($zip->open($dest,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Backup archive could not be created.');
    try{foreach($items as $name=>$hash)if(!$zip->addFile($real.'/'.$name,$name))throw new RuntimeException('A source file could not be archived: '.$name);}
    finally{$zip->close();}
    $size=filesize($dest);if($size===false||$size===0)throw new RuntimeException('Backup archive was not written.');return $size;
}

/** Live hashes for the manifest's items, for honest restore previews. */
function backups_current_hashes(string $root,array $items): array {
    $real=realpath($root);$current=[];
    foreach(array_keys($items) as $name){$path=$real.'/'.$name;$current[$name]=(is_file($path)&&!is_link($path))?hash_file('sha256',$path):null;}
    return $current;
}

/** Apply the authorized plan: extract each differing item and write it atomically, then prove the result hash. */
function backups_apply(string $root,BackupManifest $manifest,RestorePlan $plan,string $archive): int {
    if($plan->state()!==RestorePlan::AUTHORIZED)throw new LogicException('Restore cannot run before deliberate authorization.');
    if(!class_exists('ZipArchive'))throw new RuntimeException('Ask your host to enable the PHP ZIP extension before restoring.');
    $real=realpath($root);$zip=new ZipArchive();
    if($zip->open($archive,ZipArchive::RDONLY|ZipArchive::CHECKCONS)!==true)throw new RuntimeException('The stored backup archive is corrupt or unsupported.');
    $changed=$plan->changedItems();$written=0;$touched=[];
    try{
        foreach($changed as $entry){$name=$entry['item'];
            if(!backups_item_allowed($name))throw new RuntimeException('Manifest contains a path outside the restorable scope: '.$name);
            $data=$zip->getFromName($name);if($data===false)throw new RuntimeException('The stored archive is missing an item: '.$name);
            if(!hash_equals($manifest->items[$name],hash('sha256',$data)))throw new RuntimeException('The stored archive no longer matches its manifest: '.$name);
            $target=$real.'/'.$name;$dir=dirname($target);
            if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Restore directory could not be created for: '.$name);
            if(is_link($target))throw new RuntimeException('Refusing to overwrite a symbolic link: '.$name);
            $tmp=tempnam($dir,'.backup-');if($tmp===false||file_put_contents($tmp,$data)!==strlen($data))throw new RuntimeException('Restore write failed for: '.$name);
            if(!rename($tmp,$target)){@unlink($tmp);throw new RuntimeException('Restore replace failed for: '.$name);}
            $touched[$name]=$target;$written++;
        }
    }finally{$zip->close();}
    foreach($touched as $name=>$path){$actual=hash_file('sha256',$path);if($actual===false||!$manifest->verifyItem($name,$actual))throw new RuntimeException('Post-restore verification failed for: '.$name);}
    return $written;
}

/** Full create: collect, archive and persist under exact-request replay protection. */
function backups_create(array $data,string $key): string {
    $actor=backups_require();
    $notes=trim((string)($data['notes']??''));if(strlen($notes)>5000)throw new InvalidArgumentException('Keep backup notes under 5000 characters.');
    $days=(int)($data['retain_days']??30);if($days<7||$days>365)throw new InvalidArgumentException('Choose a retention between 7 and 365 days.');
    if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('Reload the form before creating a backup.');
    $request=hash('sha256',json_encode([$notes,$days],JSON_THROW_ON_ERROR));
    return staff_locked(function() use($actor,$notes,$days,$key,$request): string {
        $old=one('SELECT * FROM '.table('backups').' WHERE request_key=?',[$key]);
        if($old){if((int)$old['created_by']!==(int)$actor['id']||!hash_equals($old['request_hash'],$request))throw new RuntimeException('This backup request was already used with different details.');return $old['id'];}
        $id=bin2hex(random_bytes(16));$created=gmdate('Y-m-d\TH:i:s\Z');$retain=gmdate('Y-m-d',strtotime('+'.$days.' days'));
        $collected=backups_collect(CWP_CODE_ROOT);
        $manifest=new BackupManifest($id,'files','staff:'.$actor['id'],$created,$collected['items'],$retain.'T00:00:00Z',$notes);
        $file='backup-'.$id.'.zip';$dest=backups_directory().'/'.$file;$written=null;
        try{$size=staff_transaction(function() use($id,$manifest,$file,$dest,$key,$request,$actor,$retain,&$written): int {
            $size=backups_write_archive(CWP_CODE_ROOT,$manifest->items,$dest);$written=$dest;
            query('INSERT INTO '.table('backups').' (id,kind,manifest,manifest_hash,items,archive_file,bytes,request_key,request_hash,status,retain_until,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,'files',json_encode(['format'=>BackupManifest::FORMAT_VERSION,'id'=>$manifest->id,'kind'=>$manifest->kind,'created_by'=>$manifest->createdBy,'created_at'=>$manifest->createdAt,'items'=>$manifest->items,'retain_until'=>$manifest->retainUntil,'notes'=>$manifest->notes],JSON_UNESCAPED_SLASHES),$manifest->manifestHash(),count($manifest->items),$file,$size,$key,$request,'ready',$retain,$actor['id'],gmdate('Y-m-d H:i:s')]);
            backups_audit((int)$actor['id'],'create','Backup '.substr($id,0,8).' — '.count($manifest->items).' items, '.$size.' bytes');
            return $size;
        });}catch(Throwable $e){if($written!==null)@unlink($written);throw $e;}
        return $id;
    });
}

/** Preview a restore for the given backup row: verified manifest + live impact. */
function backups_preview(array $row): RestorePlan {
    $manifest=backups_manifest($row);
    if($row['kind']!=='files')throw new RuntimeException('This backup kind cannot be restored by this installation.');
    if(!is_file(backups_archive_path($row)))throw new RuntimeException('The stored archive for this backup is missing.');
    return RestorePlan::preview($row['id'],$manifest,backups_current_hashes(CWP_CODE_ROOT,$manifest->items),BackupManifest::FORMAT_VERSION);
}

function backups_restore_phrase(array $row): string {return 'RESTORE '.strtoupper(substr($row['id'],0,8));}

/** Deliberate restore: owner re-authentication, exact phrase, atomic item writes, recorded outcome. Replaying an applied restore is naturally a no-op — every item verifies identical. */
function backups_restore(string $id,string $confirmation,#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($id,$confirmation,$password): void {
        $actor=staff_owner($password);$row=backups_row($id);
        $plan=backups_preview($row);
        $plan=$plan->authorize('staff:'.$actor['id'],$confirmation,backups_restore_phrase($row),time());
        $written=backups_apply(CWP_CODE_ROOT,$plan->manifest,$plan,backups_archive_path($row));
        staff_transaction(function() use($row,$actor,$written): void {
            query('UPDATE '.table('backups').' SET status=?,restored_by=?,restored_at=? WHERE id=?',['applied',$actor['id'],gmdate('Y-m-d H:i:s'),$row['id']]);
            backups_audit((int)$actor['id'],'restore','Backup '.substr($row['id'],0,8).' restored '.$written.' items by '.$actor['email']);
        });
    });
}

/** Remove expired backups (rows + archives). Owner re-authentication required. */
function backups_prune(#[\SensitiveParameter] string $password): int {
    return staff_locked(function() use($password): int {
        $actor=staff_owner($password);$cutoff=gmdate('Y-m-d');
        $expired=all('SELECT * FROM '.table('backups').' WHERE retain_until < ?',[$cutoff]);$count=0;
        staff_transaction(function() use($expired,$actor,&$count): void {
            foreach($expired as $row){$path=backups_directory().'/'.$row['archive_file'];if(is_file($path)&&!is_link($path))unlink($path);query('DELETE FROM '.table('backups').' WHERE id=?',[$row['id']]);$count++;}
            backups_audit((int)$actor['id'],'prune','Removed '.$count.' expired backups');
        });
        return $count;
    });
}
