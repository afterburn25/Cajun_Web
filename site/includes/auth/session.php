<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1'); ini_set('session.use_trans_sid','0');
    session_name('cwp_' . substr(hash('sha256', realpath(CWP_ROOT) ?: CWP_ROOT),0,12));
    session_set_cookie_params(['lifetime'=>0,'path'=>base_path() . '/','secure'=>https_request(),'httponly'=>true,'samesite'=>'Lax']);
    if (!session_start()) fail_page('The server cannot start a secure session. Ask your host to check PHP session storage and available disk space.');
}

function flash(string $text, string $kind='success'): void { $_SESSION['flash'] = [$kind, $text]; }

function show_flash(): void { if(isset($_SESSION['flash'])) { [$k,$t]=$_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="notice ' . esc($k) . '" role="status">' . esc($t) . '</div>'; } }
