<?php
/** Shared-host cron entry. No request/session, secret argument, daemon or provider write path. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argc??0)!==1){fwrite(STDERR,"This scheduled task takes no arguments.\n");exit(2);}
define('CWP_BOOT',true);
require dirname(__DIR__,2).'/includes/bootstrap.php';
try{
    if(!is_file(CWP_ROOT.'/config.php'))throw new RuntimeException('Installation unavailable.');
    $config=require CWP_ROOT.'/config.php';if(!is_array($config)||!isset($config['db'],$config['key'],$config['site_url'],$config['storage']))throw new RuntimeException('Configuration unavailable.');$GLOBALS['cwp_config']=$config;
    date_default_timezone_set('UTC');
}catch(Throwable){
    fwrite(STDERR,"Scheduled tasks need owner review. Open the administration area; no private diagnostic is printed here.\n");exit(1);
}

$ok=true;
try{
    require CWP_CODE_ROOT.'/app/providers/schedule.php';
    $provider=provider_schedule_run();fwrite(STDOUT,'Hosting inventory schedule: '.$provider."\n");
    if(!in_array($provider,['complete','not_due','disabled','not_prepared','busy'],true))$ok=false;
}catch(Throwable){
    fwrite(STDERR,"Hosting inventory schedule needs owner review. Open the 20i connection page; no private diagnostic is printed here.\n");$ok=false;
}

try{
    require CWP_CODE_ROOT.'/app/licenses/finance_installments.php';
    $finance=license_finance_scheduler_run();
    // Preserve the historical shared-runner output until financing is explicitly prepared.
    if($finance['state']!=='not_prepared'){
        fwrite(STDOUT,'Financing schedule: '.$finance['state'].'; offers '.$finance['offers'].'; invoices '.$finance['invoices'].'; suspended '.$finance['suspended'].'; paid-off '.$finance['paid_off'].'; errors '.$finance['errors']."\n");
    }
    if(!in_array($finance['state'],['complete','not_prepared'],true))$ok=false;
}catch(Throwable){
    fwrite(STDERR,"Financing schedule needs owner review. Open Website Licenses; no private diagnostic is printed here.\n");$ok=false;
}

exit($ok?0:1);
