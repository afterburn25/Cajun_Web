<?php
/** Actual retention admission and restore-plan policy; synthetic manifests only. */
declare(strict_types=1);
use CajunWebPro\Foundation\Backups\{BackupManifest,RestorePlan};
define('CWP_BOOT',true);define('CWP_ROOT',dirname(__DIR__,2));define('CWP_CODE_ROOT',CWP_ROOT.'/site');
require CWP_CODE_ROOT.'/app/backups/archive.php';
$checks=[];
function rt_check(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new AssertionError($name);}
function rt_deny(string $name,callable $f):void{try{$f();}catch(RuntimeException $e){rt_check($name,str_starts_with($e->getMessage(),'Recorded backup retention'));return;}rt_check($name,false);}
try{
 $future=new BackupManifest(str_repeat('a',32),'files','staff:1','2020-01-01T00:00:00Z',['index.php'=>str_repeat('a',64)],'2099-01-01T00:00:00Z');
 $row=['retain_until'=>'2099-01-01'];backups_restore_retention($row,$future);rt_check('Consistent future retention is admitted',true);
 foreach(['past'=>'2000-01-01','future'=>'2100-01-01','bad-date'=>'2099-02-31','relative'=>'tomorrow','empty'=>'','array'=>[]] as $name=>$value)rt_deny($name.' cannot contradict or replace retention',fn()=>backups_restore_retention(['retain_until'=>$value],$future));
 rt_deny('Missing retention is refused',fn()=>backups_restore_retention([],$future));
 $expired=new BackupManifest(str_repeat('a',32),'files','staff:1','1999-01-01T00:00:00Z',['index.php'=>str_repeat('a',64)],'2000-01-01T00:00:00Z');
 backups_restore_retention(['retain_until'=>'2000-01-01'],$expired);$failure=null;
 try{RestorePlan::preview('review',$expired,[],1)->authorize('staff:1','exact','exact',946684801);}catch(LogicException $e){$failure=$e;}
 rt_check('Consistently expired manifest reaches actual restore-plan refusal',$failure instanceof LogicException&&$failure->getMessage()==='Backup is past its retention date; restore needs a fresh archive.');
 $old=date_default_timezone_get();date_default_timezone_set('America/Chicago');backups_restore_retention($row,$future);date_default_timezone_set($old);rt_check('Indexed midnight is compared in UTC independently of server zone',true);
}catch(Throwable $e){$error=get_class($e).': '.$e->getMessage();}
echo json_encode(['passed'=>!isset($error),'scope'=>'Actual retention admission and restore-plan with synthetic manifests, no SQL or files changed','checks'=>$checks,'failure'=>$error??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($error)?1:0);
