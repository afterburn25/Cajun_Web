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
    date_default_timezone_set('UTC');require CWP_CODE_ROOT.'/app/providers/schedule.php';
    $result=provider_schedule_run();fwrite(STDOUT,'Hosting inventory schedule: '.$result."\n");
    exit(in_array($result,['complete','not_due','disabled','not_prepared','busy'],true)?0:1);
}catch(Throwable){fwrite(STDERR,"Hosting inventory schedule needs owner review. Open the 20i connection page; no private diagnostic is printed here.\n");exit(1);}
