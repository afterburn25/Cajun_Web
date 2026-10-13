<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\Invitations\StaffInvitationStore;
function staff_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    return [
        'staff_profiles'=>'CREATE TABLE `'.$prefix.'staff_profiles` (user_id INT UNSIGNED PRIMARY KEY,role VARCHAR(20) NOT NULL,enabled TINYINT NOT NULL,permissions TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'staff_invitations'=>StaffInvitationStore::createTableSql($prefix.'staff_invitations'),
    ];
}
