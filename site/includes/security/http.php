<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function https_request(): bool {
    if ((!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    $trusted = []; $path = CWP_ROOT . '/proxy-config.php';
    if (is_file($path)) { $v = require $path; if (is_array($v)) $trusted = $v; }
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted, true) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function local_request(): bool { return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true) && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:[0-9]+)?$/i', $_SERVER['HTTP_HOST'] ?? '') === 1; }

function security_headers(): void {
    $GLOBALS['cwp_nonce'] = base64_encode(random_bytes(18));
    header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . $GLOBALS['cwp_nonce'] . "'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('Cache-Control: no-store, private');
    if (https_request()) header('Strict-Transport-Security: max-age=31536000');
}


/** Narrow payment-page CSP extension for the documented Authorize.net Accept.js environments. */
function authorize_net_security_headers(string $mode): void {
    if(headers_sent())throw new LogicException('Authorize.net CSP must be selected before page output.');
    if(!in_array($mode,['sandbox','production'],true))throw new InvalidArgumentException('Invalid payment CSP mode.');
    $nonce=(string)($GLOBALS['cwp_nonce']??'');
    if($nonce==='')throw new LogicException('Security headers must be initialized first.');
    $script=$mode==='sandbox'?'https://jstest.authorize.net':'https://js.authorize.net';
    // Accept.js tokenizes directly from the browser. Sandbox uses apitest;
    // production deployments have historically used api/api2 endpoints, so
    // restrict connect-src to those Authorize.net hosts only.
    $connect=$mode==='sandbox'
        ? 'https://apitest.authorize.net'
        : 'https://api.authorize.net https://api2.authorize.net';
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-".$nonce."' ".$script."; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self' ".$connect."; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

function nonce(): string { return esc($GLOBALS['cwp_nonce'] ?? ''); }

function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }

function fail_page(string $message,int $status=503): never { if(!headers_sent())http_response_code($status); echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CajunWebPro</title><body style="background:#06101f;color:#eef2f8;font:18px system-ui;padding:10vw;line-height:1.6"><h1>CajunWebPro</h1><p>' . esc($message) . '</p></body></html>'; exit; }
