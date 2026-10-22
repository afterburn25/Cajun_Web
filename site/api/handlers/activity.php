<?php
/** Private count only; never extends an idle login and never changes read state. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
boot();require CWP_CODE_ROOT.'/app/clients/workspace.php';require_once CWP_CODE_ROOT.'/app/support/ticket_notifications.php';
header('Cache-Control: no-store, private');header('Vary: Cookie');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit;}
try{
    $audience=is_string($_GET['audience']??null)?$_GET['audience']:'';$summary=workspace_activity_summary($audience);
    header('Content-Type: application/json; charset=UTF-8');echo json_encode($summary,JSON_THROW_ON_ERROR);
}catch(ActivityProviderUnavailable){http_response_code(503);header('Content-Type: application/json; charset=UTF-8');echo '{"unavailable":true}';}
catch(PDOException $e){report_error($e);http_response_code(503);header('Content-Type: application/json; charset=UTF-8');echo '{"unavailable":true}';}
catch(InvalidArgumentException|RuntimeException){http_response_code(204);}
