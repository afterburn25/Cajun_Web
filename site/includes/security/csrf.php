<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function csrf(): string { if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }

function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . esc(csrf()) . '">'; }

function check_csrf(): void { if (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf(),$_POST['csrf'])) throw new RuntimeException('Your session expired. Refresh the page and try again.'); }
