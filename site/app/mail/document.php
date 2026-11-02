<?php
/** Sandboxed formatted-fragment document shared by message read and draft preview. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Sandboxed preview document; the fragment must already be sanitized. */
function mail_message_document(#[\SensitiveParameter] string $sanitized): string {
    $token=$GLOBALS['cwp_nonce']??'';
    if(!is_string($token)||!preg_match('/\A[A-Za-z0-9+\/]{24}\z/D',$token))throw new RuntimeException('Message formatting could not be prepared.');
    $policy="default-src 'none'; script-src 'none'; style-src 'nonce-".$token."'; img-src 'none'; base-uri 'none'; form-action 'none'; connect-src 'none'; object-src 'none'; frame-src 'none'";
    $style='body{margin:16px;background:#102338;color:#eff6ff;font:16px/1.6 system-ui;overflow-wrap:anywhere}a{color:#efc36b}table{max-width:100%;border-collapse:collapse}td,th{padding:4px;border:1px solid #476079}pre{white-space:pre-wrap}blockquote{margin-left:12px;padding-left:12px;border-left:3px solid #efc36b}';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="referrer" content="no-referrer"><meta http-equiv="Content-Security-Policy" content="'.esc($policy).'"><style nonce="'.esc($token).'">'.$style.'</style></head><body>'.$sanitized.'</body></html>';
}
