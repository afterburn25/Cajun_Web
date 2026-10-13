<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$root=realpath($argv[1]);$base=realpath(__DIR__.'/../test-runtime');
if(!$root || !$base || !str_starts_with($root,$base.DIRECTORY_SEPARATOR))exit(2);
preg_match("/const CWP_VERSION = '([^']+)'/",file_get_contents(__DIR__.'/../site/config/defaults.php'),$version);
$stage=$root.'/cwp-update-'.$version[1];
define('CWP_BOOT',true);define('CWP_ROOT',$root);define('CWP_CODE_ROOT',$stage.'/site');
require CWP_CODE_ROOT.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require CWP_ROOT.'/config.php';
require CWP_CODE_ROOT.'/app/updates/structure.php';
$manifest=require $stage.'/manifest.php';$history=json_decode(file_get_contents($stage.'/legacy-release-files.json'),true);
$lock=null;
try{
    $dir=structure_store();$lock=structure_lock($dir);$action=$argv[2];
    if($action==='hold'){echo "LOCKED\n";flush();sleep(5);exit;}
    if($action==='inspect')$result=structure_plan($stage.'/site',$manifest,$history);
    elseif($action==='recover')$result=structure_recover($dir);
    else{
        $count=0;$stop=(int)($argv[3]??0);
        $result=structure_apply($stage.'/site',$manifest,$history,$dir,function(string $name)use(&$count,$stop):void{if(++$count===$stop)throw new RuntimeException('Synthetic interruption after durable journal: '.$name);});
    }
    echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
