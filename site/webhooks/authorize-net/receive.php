<?php
/** Fixed sandbox receiver: no session, redirects, customer output or provider calls. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }


security_headers();header('Content-Type: application/json; charset=utf-8');
$status=503;
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');$status=405;}
    elseif(!https_request())$status=403;
    elseif(!preg_match('~^application/json(?:\s*;.*)?$~i',$_SERVER['CONTENT_TYPE']??''))$status=415;
    elseif((int)($_SERVER['CONTENT_LENGTH']??0)>32768)$status=413;
    elseif(is_file(CWP_ROOT.'/config.php')){
        $GLOBALS['cwp_config']=require CWP_ROOT.'/config.php';date_default_timezone_set('UTC');
        require CWP_CODE_ROOT.'/app/clients/workspace.php';require_once CWP_CODE_ROOT.'/app/billing/payments.php';
        $body=file_get_contents('php://input',false,null,0,32769);
        $status=$body===false?400:(strlen($body)>32768?413:notifications_receive($body,(string)($_SERVER['HTTP_X_ANET_SIGNATURE']??'')));
    }
}catch(Throwable $e){report_error($e);$status=503;}
http_response_code($status);echo $status===200?'{"received":true}':'{"received":false}';
