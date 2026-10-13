<?php
declare(strict_types=1);
if (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }
function cwp_installer_cleanup(): array {
    if (!cwp_installer_installed()) throw new RuntimeException('Installation is not confirmed.');
    return cleanup_installer_files();
}
