<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
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
        if (function_exists('fsync') && !fsync($fp)) throw new RuntimeException('The private file could not be synchronized to storage.');
        $complete=true;
    } finally {
        fclose($fp);
        if (!$complete) @unlink($path);
    }
}

/** One lock per installation directory, independent of optional storage choice. */

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
