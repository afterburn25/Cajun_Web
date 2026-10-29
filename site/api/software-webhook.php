<?php
/** Authenticated Authorize.net notifications, no customer session required. */
declare(strict_types=1);
define('CWP_BOOT',true);
require __DIR__.'/../includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/app/billing/software_subscription_settle.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
$raw=file_get_contents('php://input',false,null,0,131073);
$signature=$_SERVER['HTTP_X_ANET_SIGNATURE']??'';
if(!is_string($raw)||strlen($raw)>131072||!is_string($signature)){
    http_response_code(400);exit;
}
try{
    boot();
    software_webhook_process($raw,$signature);
    http_response_code(200);echo 'Accepted';
}catch(InvalidArgumentException $e){
    http_response_code(403);echo 'Invalid notification';
}catch(Throwable $e){
    report_error($e);
    http_response_code(503);echo 'Temporarily unavailable';
}
