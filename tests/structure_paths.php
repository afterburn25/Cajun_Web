<?php
/** Pure path grammar checks; no installed configuration, network or mutations. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/app/updates/structure.php';
$checks=[];
foreach(['aa_ER@saaho','be_BY@latin','ks_IN@devanagari','nan_TW@latin','sd_IN@devanagari','sr_RS@latin','tt_RU@iqtelif','uz_UZ@cyrillic'] as $locale){
    $name='includes/foundation/mail-receiving/vendor/nesbot/carbon/src/Carbon/Lang/'.$locale.'.php';
    $checks[]=['name'=>'Updater accepts exact maintained locale filename '.$locale,'passed'=>structure_path('/synthetic-root',$name)==='/synthetic-root/'.$name];
}
foreach(['../escape','x@locale/../../escape','/absolute','x\\escape','C:/escape','x//file','x/./file','./file','x/%2e%2e/file',"x/\0file","x/\nfile",'https://example.invalid/file'] as $i=>$name){
    try{structure_path('/synthetic-root',$name);$rejected=false;}catch(RuntimeException){$rejected=true;}
    $checks[]=['name'=>'Updater refuses noncanonical path case '.($i+1),'passed'=>$rejected];
}
echo json_encode($checks,JSON_THROW_ON_ERROR)."\n";exit(array_filter($checks,fn($c)=>!$c['passed'])?1:0);
