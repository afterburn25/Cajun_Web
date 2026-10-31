<?php
/** Actual restore writer and file modes; deterministic ZIP-member double, no SQL. */
declare(strict_types=1);
use CajunWebPro\Foundation\Backups\{BackupManifest,RestorePlan};
if(extension_loaded('zip'))throw new RuntimeException('Run this boundary test with php -n.');
final class ZipArchive {
 public const RDONLY=1;public const CHECKCONS=2;
 public function open(string $path,int $flags):bool{return true;}
 public function getFromName(string $name):string{return 'archived source bytes';}
 public function close():bool{return true;}
}
define('CWP_BOOT',true);define('CWP_ROOT',dirname(__DIR__,2));define('CWP_CODE_ROOT',CWP_ROOT.'/site');
require CWP_CODE_ROOT.'/app/backups/archive.php';
$root=sys_get_temp_dir().'/cwp-permissions-'.bin2hex(random_bytes(6));mkdir($root,0700);$path=$root.'/entry.php';$checks=[];
function rp_check(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];}
try{
 foreach([0644,0640] as $mode){
  file_put_contents($path,'newer source bytes');chmod($path,$mode);
  $manifest=new BackupManifest('permissions','files','staff:1','2020-01-01T00:00:00Z',['entry.php'=>hash('sha256','archived source bytes')],'2099-01-01T00:00:00Z');
  $plan=RestorePlan::preview('restore',$manifest,backups_current_hashes($root,$manifest->items),1)->authorize('staff:1','exact','exact',time());
  $count=backups_apply($root,$manifest,$plan,$root.'/synthetic.zip');clearstatcache(true,$path);
  rp_check('Existing mode '.decoct($mode).' survives restore',(fileperms($path)&0777)===$mode);
  rp_check('Restored bytes are still verified for '.decoct($mode),$count===1&&file_get_contents($path)==='archived source bytes');
 }
 unlink($path);$plan=RestorePlan::preview('missing',$manifest,[],1)->authorize('staff:1','exact','exact',time());backups_apply($root,$manifest,$plan,$root.'/synthetic.zip');clearstatcache(true,$path);
 rp_check('New deployed source receives ordinary readable permissions',(fileperms($path)&0777)===0644);
 rp_check('Successful restore removes private temporary originals',(glob($root.'/.orig-*')?:[])===[]&&(glob($root.'/.backup-*')?:[])===[]);
}catch(Throwable $e){$checks[]=['name'=>get_class($e).': '.$e->getMessage(),'passed'=>false];}
finally{foreach(scandir($root) as $f)if(!in_array($f,['.','..'],true))unlink($root.'/'.$f);rmdir($root);}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$passed,'scope'=>'Actual restore writer/file modes with a ZIP-read double; not native ZIP or SQL','checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
