<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function notifications_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    return ['payment_notifications'=>'CREATE TABLE `'.$prefix.'payment_notifications` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,notification_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,webhook_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,gateway_revision INT UNSIGNED NOT NULL,body_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,event_type VARCHAR(100) NOT NULL,event_date VARCHAR(40) NOT NULL,transaction_id VARCHAR(20) NULL,reference CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,status VARCHAR(20) NOT NULL,attempt_id INT UNSIGNED NULL,actor_id INT UNSIGNED NULL,received_at DATETIME NOT NULL,verified_at DATETIME NULL,INDEX inbox_status(status,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
