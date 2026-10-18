<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function provider_plan_catalog_schema(string $prefix):string {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';
    return 'CREATE TABLE `'.$prefix.'provider_plan_catalog` (id '.$id.' PRIMARY KEY,plan_id '.$id.' NOT NULL,revision INT UNSIGNED NOT NULL,created_by INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,binding_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,binding_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,UNIQUE KEY plan_revision(plan_id,revision),INDEX created_by(created_by),FOREIGN KEY (plan_id) REFERENCES `'.$prefix.'service_plans` (id) ON DELETE RESTRICT,FOREIGN KEY (created_by) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
}
