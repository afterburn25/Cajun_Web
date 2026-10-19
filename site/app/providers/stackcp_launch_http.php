<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Permit only the documented default destination on the owner launch page. */
function provider_stackcp_launch_form_policy(string $policy): string {
    $parts = explode(';', $policy);
    $matches = [];
    foreach ($parts as $index => $directive) {
        if (preg_match('/\A\s*form-action(?:\s|\z)/i', $directive) === 1) $matches[] = $index;
    }
    if (count($matches) !== 1 || trim($parts[$matches[0]]) !== "form-action 'self'") {
        throw new RuntimeException('The StackCP browser policy needs review.');
    }
    $parts[$matches[0]] = " form-action 'self' https://www.stackcp.com/login/sso";
    return implode(';', $parts);
}

/** Called after owner-page authorization, before rendering the launch form. */
function provider_stackcp_launch_page_headers(): void {
    $policies = [];
    foreach (headers_list() as $header) {
        if (str_starts_with(strtolower($header), 'content-security-policy:')) {
            $policies[] = trim(substr($header, strlen('Content-Security-Policy:')));
        }
    }
    if (count($policies) !== 1 || headers_sent()) {
        throw new RuntimeException('The StackCP browser policy needs review.');
    }
    header('Content-Security-Policy: '.provider_stackcp_launch_form_policy($policies[0]), true);
    header('Cache-Control: no-store, private, max-age=0');
    header('Referrer-Policy: no-referrer');
}

/** Secret-bearing destination stays in this response only; never use flash or HTML. */
function provider_stackcp_launch_redirect(#[\SensitiveParameter] string $url): never {
    $prefix = 'https://www.stackcp.com/login/sso?PHPSESSID=';
    if (!str_starts_with($url, $prefix) || strlen($url) > 16384) {
        throw new RuntimeException('The StackCP destination is unavailable.');
    }
    $encoded = substr($url, strlen($prefix));
    $session = rawurldecode($encoded);
    if ($session === '' || strlen($session) > 4096 || preg_match('//u', $session) !== 1
        || preg_match('/[\x00-\x20\x7f]/', $session) !== 0 || rawurlencode($session) !== $encoded
        || headers_sent()) {
        throw new RuntimeException('The StackCP destination is unavailable.');
    }
    unset($session, $encoded);
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Referrer-Policy: no-referrer');
    header('Cross-Origin-Opener-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action https://www.stackcp.com/login/sso; frame-ancestors 'none'");
    header('Location: '.$url, true, 303);
    exit;
}
