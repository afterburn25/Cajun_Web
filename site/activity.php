<?php
/** Private count only; never extends an idle login and never changes read state. */
declare(strict_types=1);
define('CWP_BOOT',true);require __DIR__.'/app/core.php';boot();require __DIR__.'/app/workspace.php';
header('Cache-Control: no-store, private');header('Vary: Cookie');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit;}
try{
    $audience=is_string($_GET['audience']??null)?$_GET['audience']:'';$count=activity_passive_count($audience);
    header('Content-Type: application/json; charset=UTF-8');echo json_encode(['count'=>$count,'capped'=>$count>=100,'url'=>activity_url($audience)],JSON_THROW_ON_ERROR);
}catch(PDOException $e){report_error($e);http_response_code(503);header('Content-Type: application/json; charset=UTF-8');echo '{"unavailable":true}';}
catch(InvalidArgumentException|RuntimeException){http_response_code(204);}
