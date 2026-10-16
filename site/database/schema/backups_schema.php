<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function backups_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';return [
    'backups'=>'CREATE TABLE `'.$prefix.'backups` (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,kind VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,manifest LONGTEXT NOT NULL,manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,items INT UNSIGNED NOT NULL,archive_file VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,bytes BIGINT UNSIGNED NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,retain_until DATE NOT NULL,created_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,restored_by INT UNSIGNED NULL,restored_at DATETIME NULL,INDEX backups_retention(retain_until,status))'.$engine];
}
