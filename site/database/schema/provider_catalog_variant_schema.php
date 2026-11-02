<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function provider_catalog_variant_schema(string $prefix): array {
    if(preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix)!==1)throw new InvalidArgumentException('Invalid prefix.');
    return ['catalog_provider_variants'=>'CREATE TABLE `'.$prefix.'catalog_provider_variants` (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,canonical_import_id INT UNSIGNED NOT NULL,canonical_product_id INT UNSIGNED NOT NULL,product_id INT UNSIGNED NOT NULL,document_json TEXT NOT NULL,document_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_id INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,media_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,media_revision INT UNSIGNED NOT NULL,UNIQUE product_id(product_id),KEY canonical_import_id(canonical_import_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
