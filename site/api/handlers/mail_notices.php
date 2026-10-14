<?php
/** Passive private metadata only. No mailbox connection, read mutation or idle renewal. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
boot();require_once CWP_CODE_ROOT.'/app/mail/notices.php';
header('Cache-Control: no-store, private');header('Vary: Cookie');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit;}
try {
    $summary=mail_notices_passive_summary();header('Content-Type: application/json; charset=UTF-8');echo json_encode($summary,JSON_THROW_ON_ERROR);
}catch(PDOException $e){report_error($e);http_response_code(503);header('Content-Type: application/json; charset=UTF-8');echo '{"unavailable":true}';}
catch(InvalidArgumentException|RuntimeException){http_response_code(204);}
