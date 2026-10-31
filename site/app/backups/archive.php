<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/backups/records.php';
use CajunWebPro\Foundation\Backups\{BackupManifest,RestorePlan};

/** Mirror of BackupManifest::FORBIDDEN applied during collection so a real forbidden file is skipped, not fatal. Every rule applies per path component, at any depth. */
function backups_item_allowed(string $name): bool {
    return !preg_match('#(^|/)(config\.php|proxy-config\.php|\.installed\.php|\.env)$|(^|/)[^/]*\.(key|pem|p12)$|(^|/)storage(/|$)|(^|/)[^/]*payment-master#i',$name)
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

/** Tables whose contents are transient runtime state, never business data — excluded from the database artifact. */
function backups_dump_excluded(string $name): bool {
    return in_array($name,['rate_limits','resets'],true);
}

/** Checked dump write: PHP fwrite may short-write or return false, and either result must fail the backup instead of recording a partial SQL file as ready. */
function backups_dump_write($out,string $data): void {
    $written=fwrite($out,$data);
    if($written===false||$written!==strlen($data))throw new RuntimeException('The database export could not write the complete SQL snapshot.');
}

/** Stream a consistent SQL dump of every non-transient table under the configured prefix into $dest; returns table/row/byte counts. The shared PDO is unbuffered only for the duration of the export and then restored. */
function backups_dump_database(string $dest): array {
    $prefix=config()['db']['prefix'];
    $pdo=db();
    $names=array_column(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE ? ORDER BY TABLE_NAME',[strtr($prefix,['%'=>'\%','_'=>'\_']).'%']),'TABLE_NAME');
    $out=@fopen($dest,'wb');if($out===false)throw new RuntimeException('The database export file could not be created.');
    $tables=0;$rows=0;$closed=false;
    $oldBuffered=$pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    try{
        backups_dump_write($out,"-- CajunWebPro database export\n-- This snapshot is preserved for operator-guided recovery; file restores never apply it.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach($names as $full){
            $name=substr($full,strlen($prefix));
            if(backups_dump_excluded($name))continue;
            $ddl=one('SHOW CREATE TABLE `'.$full.'`');if(!$ddl)throw new RuntimeException('The database export could not read a table definition: '.$full);
            $create=$ddl['Create Table']??end($ddl);
            backups_dump_write($out,'DROP TABLE IF EXISTS `'.$full."`;\n".$create.";\n\n");
            $stmt=$pdo->prepare('SELECT * FROM `'.$full.'`');$stmt->execute();
            while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
                $values=[];
                foreach($row as $v)$values[]=$v===null?'NULL':$pdo->quote((string)$v);
                backups_dump_write($out,'INSERT INTO `'.$full.'` VALUES ('.implode(',',$values).");\n");$rows++;
            }
            $stmt->closeCursor();backups_dump_write($out,"\n");$tables++;
        }
        backups_dump_write($out,"SET FOREIGN_KEY_CHECKS=1;\n");
        if(!fflush($out))throw new RuntimeException('The database export could not flush the complete SQL snapshot.');
        if(!fclose($out))throw new RuntimeException('The database export could not close the complete SQL snapshot.');
        $closed=true;
    }finally{
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$oldBuffered);
        if(!$closed)@fclose($out);
    }
    if($tables===0)throw new RuntimeException('The database export found no application tables.');
    $bytes=filesize($dest);if($bytes===false||$bytes===0)throw new RuntimeException('The database export was not written completely.');
    return ['tables'=>$tables,'rows'=>$rows,'bytes'=>$bytes];
}

/** Write the collected items into a fresh ZIP archive; returns the archive size. An optional database export is embedded as its own member. */
function backups_write_archive(string $root,array $items,string $dest,?string $database=null): int {
    if(!class_exists('ZipArchive'))throw new RuntimeException('Ask your host to enable the PHP ZIP extension before creating backups.');
    $real=realpath($root);$zip=new ZipArchive();
    if($zip->open($dest,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Backup archive could not be created.');
    try{
        foreach($items as $name=>$hash)if(!$zip->addFile($real.'/'.$name,$name))throw new RuntimeException('A source file could not be archived: '.$name);
        if($database!==null&&!$zip->addFile($database,'database/snapshot.sql'))throw new RuntimeException('The database export could not be archived.');
    }
    finally{$zip->close();}
    $size=filesize($dest);if($size===false||$size===0)throw new RuntimeException('Backup archive was not written.');return $size;
}

/** Live hashes for the manifest's items, for honest restore previews. */
function backups_current_hashes(string $root,array $items): array {
    $real=realpath($root);$current=[];
    foreach(array_keys($items) as $name){$path=$real.'/'.$name;$current[$name]=(is_file($path)&&!is_link($path))?hash_file('sha256',$path):null;}
    return $current;
}

/**
 * Resolve a manifest item's destination inside the verified source root.
 * Every existing ancestor must be a real (non-link, non-reparse) directory
 * contained inside the root; missing ancestors are returned for creation.
 * Containment is enforced for existing and missing destinations alike so a
 * restore can never escape the authorized tree.
 */
function backups_target(string $rootReal,string $name): array {
    if(str_contains($name,'\\')||str_contains($name,"\0")||str_starts_with($name,'/')||preg_match('#^[A-Za-z]:#',$name))throw new RuntimeException('Restore path is not a canonical relative name: '.$name);
    $parts=explode('/',$name);$file=array_pop($parts);
    if($file===''||$file==='.'||$file==='..')throw new RuntimeException('Restore path is not a canonical relative name: '.$name);
    $dir=$rootReal;$missing=[];
    foreach($parts as $seg){
        if($seg===''||$seg==='.'||$seg==='..')throw new RuntimeException('Restore path is not a canonical relative name: '.$name);
        $next=$dir.'/'.$seg;
        if(is_link($next))throw new RuntimeException('Restore refuses a linked parent directory: '.$name);
        if(is_dir($next)){
            $resolved=realpath($next);if($resolved===false)throw new RuntimeException('Restore parent could not be resolved: '.$name);
            $a=backups_canonical($resolved);$b=backups_canonical($rootReal);
            if($a!==$b&&!str_starts_with($a,$b.'/'))throw new RuntimeException('Restore parent resolves outside the source root: '.$name);
            $dir=$resolved;continue;
        }
        if(file_exists($next))throw new RuntimeException('Restore parent exists but is not a directory: '.$name);
        $dir=$next;$missing[]=$next;
    }
    $target=$dir.'/'.$file;
    if(is_link($target))throw new RuntimeException('Refusing to overwrite a symbolic link: '.$name);
    if(file_exists($target)&&!is_file($target))throw new RuntimeException('Restore target exists but is not a regular file: '.$name);
    return ['dir'=>$dir,'target'=>$target,'missing'=>$missing];
}

/** Apply the authorized plan: preflight every member and destination, then write atomically with recoverable originals and a post-restore hash proof. */
function backups_apply(string $root,BackupManifest $manifest,RestorePlan $plan,string $archive): int {
    if($plan->state()!==RestorePlan::AUTHORIZED)throw new LogicException('Restore cannot run before deliberate authorization.');
    if(!class_exists('ZipArchive'))throw new RuntimeException('Ask your host to enable the PHP ZIP extension before restoring.');
    $real=realpath($root);if($real===false)throw new RuntimeException('The deployed source root is unavailable.');
    $zip=new ZipArchive();
    if($zip->open($archive,ZipArchive::RDONLY|ZipArchive::CHECKCONS)!==true)throw new RuntimeException('The stored backup archive is corrupt or unsupported.');
    try{
        // Preflight every intended member and destination before the first live replacement.
        $staged=[];
        foreach($plan->changedItems() as $entry){$name=$entry['item'];
            if(!backups_item_allowed($name))throw new RuntimeException('Manifest contains a path outside the restorable scope: '.$name);
            if(!isset($manifest->items[$name]))throw new RuntimeException('The manifest does not cover a planned item: '.$name);
            $data=$zip->getFromName($name);if($data===false)throw new RuntimeException('The stored archive is missing an item: '.$name);
            if(!hash_equals($manifest->items[$name],hash('sha256',$data)))throw new RuntimeException('The stored archive no longer matches its manifest: '.$name);
            $staged[]=backups_target($real,$name)+['name'=>$name,'data'=>$data];
        }
    }finally{$zip->close();}
    $written=0;$touched=[];$originals=[];
    try{
        foreach($staged as $s){
            $dir=$s['dir'];
            if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Restore directory could not be created for: '.$s['name']);
            if(is_link($dir))throw new RuntimeException('Restore refuses a linked parent directory: '.$s['name']);
            $target=$s['target'];$original=null;
            if(file_exists($target)){
                $original=tempnam($dir,'.orig-');if($original===false||!copy($target,$original))throw new RuntimeException('The original file could not be preserved for: '.$s['name']);
            }
            $originals[]=['target'=>$target,'original'=>$original,'done'=>false];$oi=array_key_last($originals);
            $tmp=tempnam($dir,'.backup-');if($tmp===false||file_put_contents($tmp,$s['data'])!==strlen($s['data'])){@unlink($tmp);throw new RuntimeException('Restore write failed for: '.$s['name']);}
            if(!rename($tmp,$target)){@unlink($tmp);throw new RuntimeException('Restore replace failed for: '.$s['name']);}
            $originals[$oi]['done']=true;$touched[$s['name']]=$target;$written++;
        }
        foreach($touched as $name=>$path){$actual=hash_file('sha256',$path);if($actual===false||!$manifest->verifyItem($name,$actual))throw new RuntimeException('Post-restore verification failed for: '.$name);}
    }catch(Throwable $e){
        // Best-effort recovery: restore preserved originals in reverse and
        // remove targets that had no prior file, so a failed restore leaves
        // the tree as close to its authorized starting state as possible.
        foreach(array_reverse($originals) as $o){
            if($o['original']!==null){if($o['done']){@unlink($o['target']);@rename($o['original'],$o['target']);}else @unlink($o['original']);}
            elseif($o['done'])@unlink($o['target']);
        }
        throw $e;
    }
    foreach($originals as $o)if($o['original']!==null)@unlink($o['original']);
    return $written;
}

/** Full create: collect, archive and persist under exact-request replay protection. Authorization is refreshed inside the shared mutex so a grant revoked between the form read and the lock cannot write. */
function backups_create(array $data,string $key): string {
    $notes=trim((string)($data['notes']??''));if(strlen($notes)>5000)throw new InvalidArgumentException('Keep backup notes under 5000 characters.');
    $days=(int)($data['retain_days']??30);if($days<7||$days>365)throw new InvalidArgumentException('Choose a retention between 7 and 365 days.');
    if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('Reload the form before creating a backup.');
    $request=hash('sha256',json_encode([$notes,$days],JSON_THROW_ON_ERROR));
    return staff_locked(function() use($notes,$days,$key,$request): string {
        $actor=backups_require();
        $old=one('SELECT * FROM '.table('backups').' WHERE request_key=?',[$key]);
        if($old){if((int)$old['created_by']!==(int)$actor['id']||!hash_equals($old['request_hash'],$request))throw new RuntimeException('This backup request was already used with different details.');return $old['id'];}
        $id=bin2hex(random_bytes(16));$created=gmdate('Y-m-d\TH:i:s\Z');$retain=gmdate('Y-m-d',strtotime('+'.$days.' days'));
        $collected=backups_collect(CWP_CODE_ROOT);
        $manifest=new BackupManifest($id,'files','staff:'.$actor['id'],$created,$collected['items'],$retain.'T00:00:00Z',$notes);
        $file='backup-'.$id.'.zip';$dest=backups_directory().'/'.$file;$dump=backups_directory().'/dump-'.$id.'-'.bin2hex(random_bytes(4)).'.sql';$written=null;
        try{$size=staff_transaction(function() use($id,$manifest,$file,$dest,$dump,$key,$request,$actor,$retain,&$written): int {
            // The export runs inside this transaction so every table reflects one
            // consistent point-in-time view; the snapshot intentionally predates
            // this backups row's own insertion.
            $db=backups_dump_database($dump);
            $size=backups_write_archive(CWP_CODE_ROOT,$manifest->items,$dest,$dump);$written=$dest;
            $section=['format'=>1,'file'=>'database/snapshot.sql','sha256'=>hash_file('sha256',$dump),'bytes'=>$db['bytes'],'tables'=>$db['tables'],'rows'=>$db['rows']];
            $integrity=backups_manifest_integrity_hash($manifest,$section);
            query('INSERT INTO '.table('backups').' (id,kind,manifest,manifest_hash,items,archive_file,bytes,request_key,request_hash,status,retain_until,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,'files',json_encode(['format'=>BackupManifest::FORMAT_VERSION,'id'=>$manifest->id,'kind'=>$manifest->kind,'created_by'=>$manifest->createdBy,'created_at'=>$manifest->createdAt,'items'=>$manifest->items,'retain_until'=>$manifest->retainUntil,'notes'=>$manifest->notes,'database'=>$section],JSON_UNESCAPED_SLASHES),$integrity,count($manifest->items),$file,$size,$key,$request,'ready',$retain,$actor['id'],gmdate('Y-m-d H:i:s')]);
            backups_audit((int)$actor['id'],'create','Backup '.substr($id,0,8).' — '.count($manifest->items).' items, '.$db['tables'].' tables, '.$size.' bytes');
            return $size;
        });}catch(Throwable $e){if($written!==null)@unlink($written);throw $e;}
        finally{if(is_file($dump))@unlink($dump);}
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

/** Deliberate restore: current owner authorization, exact phrase, atomic item writes, recorded outcome. Replaying an applied restore is naturally a no-op — every item verifies identical. */
function backups_restore(string $id,string $confirmation,#[\SensitiveParameter] string $password=''): void {
    staff_locked(function() use($id,$confirmation,$password): void {
        $actor=backups_owner_session();$row=backups_row($id);
        $plan=backups_preview($row);
        $plan=$plan->authorize('staff:'.$actor['id'],$confirmation,backups_restore_phrase($row),time());
        staff_transaction(function() use($row,$actor): void {
            query('UPDATE '.table('backups').' SET status=? WHERE id=?',['restoring',$row['id']]);
            backups_audit((int)$actor['id'],'restore.attempt','Backup '.substr($row['id'],0,8).' restore authorized by '.$actor['email']);
        });
        try{$written=backups_apply(CWP_CODE_ROOT,$plan->manifest,$plan,backups_archive_path($row));}
        catch(Throwable $e){$message=$e->getMessage();
            try{staff_transaction(function() use($row,$actor,$message): void {
                query('UPDATE '.table('backups').' SET status=? WHERE id=?',['failed',$row['id']]);
                backups_audit((int)$actor['id'],'restore.failed','Backup '.substr($row['id'],0,8).' restore failed: '.substr($message,0,180));
            });}catch(Throwable $ignored){}
            throw $e;
        }
        staff_transaction(function() use($row,$actor,$written): void {
            query('UPDATE '.table('backups').' SET status=?,restored_by=?,restored_at=? WHERE id=?',['applied',$actor['id'],gmdate('Y-m-d H:i:s'),$row['id']]);
            backups_audit((int)$actor['id'],'restore','Backup '.substr($row['id'],0,8).' restored '.$written.' items by '.$actor['email']);
        });
    });
}

/** Remove an isolated drill scratch directory completely. */
function backups_scrub(string $dir): void {
    try{$base=backups_directory();}catch(Throwable){return;}
    $real=realpath($dir);
    if($real===false||!str_starts_with(backups_canonical($real).'/',backups_canonical(rtrim($base,'/')).'/'))return;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $f){$f->isDir()&&!$f->isLink()?rmdir($f->getPathname()):unlink($f->getPathname());}
    rmdir($real);
}

/** Isolated restore drill: extract every manifest item into private scratch storage and prove each written file matches its recorded hash. No deployed file is touched; every attempt is recorded. */
function backups_drill(string $id): array {
    $actor=backups_require();
    $row=backups_row($id);
    $manifest=backups_manifest($row);
    if($row['kind']!=='files')throw new RuntimeException('This backup kind cannot be drilled by this installation.');
    $archive=backups_archive_path($row);
    if(!is_file($archive))throw new RuntimeException('The stored archive for this backup is missing.');
    if(!class_exists('ZipArchive'))throw new RuntimeException('Ask your host to enable the PHP ZIP extension before drilling backups.');
    $start=hrtime(true);$verified=0;$missing=0;$mismatched=0;$failure=null;
    $scratch=backups_directory().'/drill-'.$id.'-'.bin2hex(random_bytes(6));
    if(!mkdir($scratch,0700))throw new RuntimeException('The isolated drill directory could not be created.');
    try {
        $zip=new ZipArchive();
        try {
            if($zip->open($archive,ZipArchive::RDONLY|ZipArchive::CHECKCONS)!==true)throw new RuntimeException('The stored backup archive is corrupt or unsupported.');
            foreach($manifest->items as $name=>$hash) {
                if(!backups_item_allowed($name))throw new RuntimeException('Manifest contains a path outside the restorable scope: '.$name);
                $data=$zip->getFromName($name);
                if($data===false){$missing++;continue;}
                if(!hash_equals($hash,hash('sha256',$data))){$mismatched++;continue;}
                $resolved=backups_target(realpath($scratch),$name);$dir=$resolved['dir'];$target=$resolved['target'];
                if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Drill directory could not be created for: '.$name);
                if(file_put_contents($target,$data)!==strlen($data))throw new RuntimeException('Drill write failed for: '.$name);
                if(hash_file('sha256',$target)!==$hash)throw new RuntimeException('Drill write verification failed for: '.$name);
                $verified++;
            }
            $db=backups_database($row);
            if($db!==null){
                $data=$zip->getFromName($db['file']);
                if($data===false){$missing++;}
                elseif(!hash_equals($db['sha256'],hash('sha256',$data))||!str_starts_with($data,"-- CajunWebPro database export\n")){$mismatched++;}
                else{$verified++;}
            }
        }finally{$zip->close();}
    }catch(Throwable $e){$failure=$e;}
    finally{backups_scrub($scratch);}
    $elapsed=max(0,min((int)((hrtime(true)-$start)/1e6),2147483647));
    $result=$failure!==null?'corrupt':(($missing===0&&$mismatched===0)?'verified':'incomplete');
    $record=staff_locked(fn()=>staff_transaction(function()use($row,$actor,$result,$verified,$missing,$mismatched,$start,$elapsed):array {
        $drill=bin2hex(random_bytes(16));$now=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('backup_drills').' (id,backup_id,actor_id,result,verified,missing,mismatched,elapsed_ms,started_at,finished_at) VALUES (?,?,?,?,?,?,?,?,?,?)',[$drill,$row['id'],(int)$actor['id'],$result,$verified,$missing,$mismatched,$elapsed,$now,$now]);
        backups_audit((int)$actor['id'],'drill','Backup '.substr($row['id'],0,8).' drill '.$result.': '.$verified.' verified, '.$missing.' missing, '.$mismatched.' mismatched, '.$elapsed.'ms');
        return ['id'=>$drill,'result'=>$result,'verified'=>$verified,'missing'=>$missing,'mismatched'=>$mismatched];
    }));
    if($failure!==null)throw $failure;
    return $record;
}

/** Remove expired backups (rows + archives). Owner re-authentication required. */
function backups_prune(#[\SensitiveParameter] string $password=''): int {
    return staff_locked(function() use($password): int {
        $actor=backups_owner_session();$cutoff=gmdate('Y-m-d');
        $expired=all('SELECT * FROM '.table('backups').' WHERE retain_until < ?',[$cutoff]);$count=0;
        staff_transaction(function() use($expired,$actor,&$count): void {
            foreach($expired as $row){$path=backups_directory().'/'.$row['archive_file'];if(is_file($path)&&!is_link($path))unlink($path);query('DELETE FROM '.table('backups').' WHERE id=?',[$row['id']]);$count++;}
            foreach(glob(backups_directory().'/dump-*.sql')?:[] as $stale)if(is_file($stale)&&!is_link($stale))unlink($stale);
            backups_audit((int)$actor['id'],'prune','Removed '.$count.' expired backups');
        });
        return $count;
    });
}
