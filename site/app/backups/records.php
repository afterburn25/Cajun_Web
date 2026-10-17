<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/includes/foundation/backups/BackupManifest.php';
require_once CWP_CODE_ROOT.'/includes/foundation/backups/RestorePlan.php';
require_once CWP_CODE_ROOT.'/database/schema/backups_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/backups_migrate.php';
use CajunWebPro\Foundation\Backups\{BackupManifest,RestorePlan};
function backups_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['backups_schema_version'])['value']??'')==='2';}
function backups_require(): array {if(support_current())throw new RuntimeException('Leave support before managing backups.');if(!backups_ready())throw new RuntimeException('An owner must prepare backups first.');return staff_require('staff.manage');}
function backups_audit(int $actor,string $action,string $detail): void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'backups.'.$action,$detail,gmdate('Y-m-d H:i:s')]);}
function backups_directory(): string {
    $storage=config()['storage'];$real=realpath($storage);if($real===false||is_link($storage)||!is_dir($real))throw new RuntimeException('Private backup storage is unavailable.');
    $canonical=fn($p)=>strtolower(str_replace('\\','/',rtrim($p,'/\\')));
    foreach([CWP_ROOT,$_SERVER['DOCUMENT_ROOT']??''] as $root){if($root===''||($web=realpath($root))===false)continue;$a=$canonical($real);$b=$canonical($web);if($a===$b||str_starts_with($a,$b.'/'))throw new RuntimeException('Backups require private storage outside the web document root.');}
    $dir=$real.'/backups';if(is_link($dir))throw new RuntimeException('Backup storage cannot be a symbolic link.');
    if(!is_dir($dir)&&!mkdir($dir,0700))throw new RuntimeException('Backup storage could not be created.');
    if(($resolved=realpath($dir))===false||$canonical($resolved)!==$canonical($dir))throw new RuntimeException('Backup storage path could not be verified.');return $resolved;
}
function backups_row(string $id): array {$row=one('SELECT * FROM '.table('backups').' WHERE id=?',[$id]);if(!$row)throw new InvalidArgumentException('Backup not found.');return $row;}
function backups_archive_path(array $row): string {
    $path=backups_directory().'/'.$row['archive_file'];
    if(is_link($path)||realpath($path)!==$path)throw new RuntimeException('Backup archive path could not be verified.');
    return $path;
}
function backups_manifest(array $row): BackupManifest {
    $data=json_decode($row['manifest'],true,64,JSON_THROW_ON_ERROR);
    if(!is_array($data)||($data['format']??0)!==BackupManifest::FORMAT_VERSION)throw new RuntimeException('This backup format is not supported by this installation.');
    $manifest=new BackupManifest($data['id'],$data['kind'],$data['created_by'],$data['created_at'],$data['items'],$data['retain_until'],$data['notes']??'');
    if(!hash_equals($row['manifest_hash'],$manifest->manifestHash()))throw new RuntimeException('Stored backup manifest does not match its recorded integrity hash.');
    return $manifest;
}
function backups_download(string $id): never {
    backups_require();$row=backups_row($id);$path=backups_archive_path($row);
    if(!is_file($path))throw new RuntimeException('The stored archive for this backup is missing.');
    $size=filesize($path);header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="cajunwebpro-backup-'.substr($id,0,8).'-'.gmdate('Ymd',strtotime($row['created_at'])).'.zip"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Length: '.$size);
    $fh=fopen($path,'rb');if(!$fh)throw new RuntimeException('Backup archive could not be read.');fpassthru($fh);fclose($fh);exit;
}
