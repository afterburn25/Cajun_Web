<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function provider_catalog_import_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    return ['catalog_provider_imports'=>'CREATE TABLE `'.$prefix.'catalog_provider_imports` (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,profile_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,type_ref VARCHAR(18) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,product_id INT UNSIGNED NOT NULL,source_revision INT UNSIGNED NOT NULL,refresh_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,source_key_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,type_name VARCHAR(120) NOT NULL,source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_id INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,media_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,media_revision INT UNSIGNED NOT NULL,UNIQUE provider_type(profile_id,type_ref),UNIQUE product_id(product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
