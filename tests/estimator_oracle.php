<?php
/** CLI-only comparison against the verified published calculator, with no database access. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$baseline=($argv[1]??'')==='baseline';
if(!$baseline && ($argv[1]??'')!=='current')exit(2);
$root=__DIR__.'/../'.($baseline?'test-runtime/released-1.11.1':'site');
if($baseline){
    $history=json_decode(file_get_contents(__DIR__.'/../tools/legacy-release-files.json'),true,512,JSON_THROW_ON_ERROR);
    foreach(['config/defaults.php','app/marketing/growth.php'] as $name){
        if(!is_file($root.'/'.$name)||!hash_equals($history['1.11.1']['files'][$name],hash_file('sha256',$root.'/'.$name)))exit(3);
    }
}
define('CWP_BOOT',true);define('CWP_ROOT',realpath($root));
require CWP_ROOT.'/includes/bootstrap.php';
require CWP_ROOT.'/app/marketing/growth.php';
$cases=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$results=[];
foreach($cases as $case){
    try{$results[]=['value'=>growth_estimate($case['input'],$case['rules'])];}
    catch(InvalidArgumentException $e){$results[]=['error'=>get_class($e),'message'=>$e->getMessage()];}
}
echo json_encode($results,JSON_THROW_ON_ERROR);
