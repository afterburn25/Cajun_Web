<?php
/** Actual archive writer with deterministic ZIP outcomes and real private files. Run with php -n. */
declare(strict_types=1);
if(extension_loaded('zip'))throw new RuntimeException('Run this boundary fixture with php -n.');
if(!class_exists('ZipArchive',false)){
    final class ZipArchive {
        public const CREATE=1; public const EXCL=2;
        public static string $mode='ok';
        public static int $closes=0;
        private string $path='';
        public function open(string $path,int $flags): bool {if(file_exists($path))return false;$this->path=$path;return true;}
        public function addFile(string $path,string $name): bool {
            if(self::$mode==='add-throws')throw new RuntimeException('Synthetic add failure.');
            return self::$mode!=='add-fails';
        }
        public function close(): bool {
            self::$closes++;
            file_put_contents($this->path,self::$mode==='empty'?'':'synthetic finalized ZIP');
            return self::$mode!=='close-fails';
        }
    }
}
$source=file_get_contents(__DIR__.'/../../site/app/backups/archive.php');
$start=strpos($source,'function backups_write_archive(');$end=strpos($source,'/** Live hashes',$start?:0);
if($start===false||$end===false||substr_count($source,'function backups_write_archive(')!==1)throw new RuntimeException('Unique archive writer declaration required.');
eval(substr($source,$start,$end-$start));
$root=sys_get_temp_dir().'/cwp-archive-commit-'.bin2hex(random_bytes(8));mkdir($root,0700);file_put_contents($root.'/source.txt','original');
$checks=[];
function ac_check(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new AssertionError($name);}
try{
    foreach(['close-fails','add-fails','add-throws','empty'] as $mode){
        ZipArchive::$mode=$mode;ZipArchive::$closes=0;$dest=$root.'/'.$mode.'.zip';$failure=null;
        try{backups_write_archive($root,['source.txt'=>hash('sha256','original')],$dest);}catch(Throwable $e){$failure=$e;}
        ac_check($mode.' is refused rather than recorded ready',$failure instanceof RuntimeException);
        ac_check($mode.' releases the writer exactly once',ZipArchive::$closes===1);
        ac_check($mode.' removes only its incomplete private destination',!file_exists($dest)&&file_get_contents($root.'/source.txt')==='original');
    }
    ZipArchive::$mode='ok';ZipArchive::$closes=0;$dest=$root.'/complete.zip';
    $size=backups_write_archive($root,['source.txt'=>hash('sha256','original')],$dest);
    ac_check('Successful close returns the complete file size',$size===strlen('synthetic finalized ZIP')&&file_get_contents($dest)==='synthetic finalized ZIP');
    ac_check('Successful writer closes exactly once',ZipArchive::$closes===1);
    $failure=null;
    try{backups_write_archive($root,['source.txt'=>hash('sha256','original')],$dest);}catch(Throwable $e){$failure=$e;}
    ac_check('Exclusive-open refusal preserves an existing archive',$failure instanceof RuntimeException&&file_get_contents($dest)==='synthetic finalized ZIP'&&ZipArchive::$closes===1);
}catch(Throwable $e){$failureMessage=get_class($e).': '.$e->getMessage();}
finally{foreach(glob($root.'/*')?:[] as $file)unlink($file);rmdir($root);}
echo json_encode(['passed'=>!isset($failureMessage),'scope'=>'Actual archive writer with synthetic ZIP failure outcomes and private filesystem; not native ZIP acceptance','checks'=>$checks,'failure'=>$failureMessage??null],JSON_PRETTY_PRINT).PHP_EOL;
exit(isset($failureMessage)?1:0);
