<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function admin_user(): ?array {
    if(empty($_SESSION['uid'])) return null;
    if(time()-(int)($_SESSION['last_activity'] ?? 0)>1800 || time()-(int)($_SESSION['login_at'] ?? 0)>28800) { unset($_SESSION['uid'],$_SESSION['auth_version']); return null; }
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[(int)$_SESSION['uid']]);
    if(!$u || !hash_equals((string)$u['auth_version'],(string)($_SESSION['auth_version'] ?? ''))) { unset($_SESSION['uid']); return null; }
    require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
    $u=staff_profile($u);
    if(!$u){unset($_SESSION['uid'],$_SESSION['auth_version']);return null;}
    $_SESSION['last_activity']=time(); return $u;
}

function require_admin(): array { $u=admin_user(); if(!$u) redirect(admin_url('login')); return $u; }

function login_user(array $u): void { session_regenerate_id(true); $_SESSION['uid']=(int)$u['id']; $_SESSION['auth_version']=(string)$u['auth_version']; $_SESSION['login_at']=$_SESSION['last_activity']=time(); $_SESSION['csrf']=bin2hex(random_bytes(32)); query('UPDATE '.table('users').' SET last_login=? WHERE id=?',[gmdate('Y-m-d H:i:s'),$u['id']]); }
