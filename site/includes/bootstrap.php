<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
ini_set('display_errors','0');
ini_set('log_errors','1');
require_once __DIR__.'/paths.php';
require_once CWP_CODE_ROOT.'/config/defaults.php';
require_once CWP_CODE_ROOT.'/includes/security/input.php';
require_once CWP_CODE_ROOT.'/includes/security/http.php';
require_once CWP_CODE_ROOT.'/includes/security/csrf.php';
require_once CWP_CODE_ROOT.'/includes/security/crypto.php';
require_once CWP_CODE_ROOT.'/includes/security/rate-limits.php';
require_once CWP_CODE_ROOT.'/includes/auth/session.php';
require_once CWP_CODE_ROOT.'/includes/auth/admin.php';
require_once CWP_CODE_ROOT.'/includes/database.php';
require_once CWP_CODE_ROOT.'/app/settings/store.php';
require_once CWP_CODE_ROOT.'/includes/urls.php';
require_once CWP_CODE_ROOT.'/includes/filesystem/private.php';
require_once CWP_CODE_ROOT.'/includes/filesystem/setup.php';
require_once CWP_CODE_ROOT.'/app/media/images.php';
require_once CWP_CODE_ROOT.'/includes/runtime.php';
require_once CWP_CODE_ROOT.'/includes/filesystem/uploads.php';
