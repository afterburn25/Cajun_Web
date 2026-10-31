<?php
/** Shared-host financing scheduler. No HTTP access, arguments, session, or raw payment data. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argc??0)!==1){fwrite(STDERR,"This financing scheduled task takes no arguments.
");exit(2);}
define('CWP_BOOT',true);
require dirname(__DIR__,2).'/includes/bootstrap.php';
try{
    if(!is_file(CWP_ROOT.'/config.php'))throw new RuntimeException('Installation unavailable.');
    $config=require CWP_ROOT.'/config.php';
    if(!is_array($config)||!isset($config['db'],$config['key'],$config['site_url'],$config['storage']))throw new RuntimeException('Configuration unavailable.');
    $GLOBALS['cwp_config']=$config;date_default_timezone_set('UTC');
    require CWP_CODE_ROOT.'/app/licenses/finance_installments.php';
    $result=license_finance_scheduler_run();
    fwrite(STDOUT,'Financing schedule: '.$result['state'].'; offers '.$result['offers'].'; invoices '.$result['invoices'].'; suspended '.$result['suspended'].'; paid-off '.$result['paid_off'].'; errors '.$result['errors']."
");
    exit($result['state']==='partial'?1:0);
}catch(Throwable){
    fwrite(STDERR,"Financing schedule needs owner review. Open Website Licenses; no private diagnostic is printed here.
");exit(1);
}
