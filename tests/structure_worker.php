<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$root=realpath($argv[1]);$base=realpath(__DIR__.'/../test-runtime');
if(!$root || !$base || !str_starts_with($root,$base.DIRECTORY_SEPARATOR))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);define('CWP_CODE_ROOT',$root.'/cwp-update-1.11.0/site');
// Verify every staged file before loading any staged code: a partial upload must not fatal inside the payload.
$stage=CWP_ROOT.'/cwp-update-1.11.0';
try{$manifest=is_file($stage.'/manifest.php')?require $stage.'/manifest.php':null;}catch(Throwable){$manifest=null;}
if(!is_array($manifest)||count($manifest['files']??[])<100){fwrite(STDERR,"Unrecognized structure update manifest.\n");exit(1);}
foreach($manifest['files'] as $name=>$hash){
    if(!is_string($name)||!preg_match('~^[a-zA-Z0-9_.-]+(?:/[a-zA-Z0-9_.-]+)*$~D',$name)||str_contains($name,'..')){fwrite(STDERR,"Unsafe name in update manifest.\n");exit(1);}
    $path=$stage.'/site/'.$name;
    if(!is_file($path)||!hash_equals((string)$hash,(string)hash_file('sha256',$path))){fwrite(STDERR,'Restore the exact UPDATE package; missing or mismatched replacement: '.$name."\n");exit(1);}
}
require CWP_CODE_ROOT.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require CWP_ROOT.'/config.php';
require CWP_CODE_ROOT.'/app/updates/structure.php';
$history=json_decode(file_get_contents($stage.'/legacy-release-files.json'),true);
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
