<?php
/** Additional tables for software licenses and orders. Existing tables untouched. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function software_commerce_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D', $prefix)) {
        throw new InvalidArgumentException('Invalid database prefix.');
    }
    $t = static fn(string $name): string => chr(96) . $prefix . $name . chr(96);
    $e = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'software_entitlements' => 'CREATE TABLE '.$t('software_entitlements').' (license_id INT UNSIGNED NOT NULL PRIMARY KEY,license_type VARCHAR(20) NOT NULL,licensee VARCHAR(190) NOT NULL,expires_at DATETIME NULL,issued_by INT UNSIGNED NOT NULL DEFAULT 0,purpose VARCHAR(200) NOT NULL DEFAULT '',invoice_id INT UNSIGNED NULL,unit_number SMALLINT UNSIGNED NULL,created_at DATETIME NOT NULL,UNIQUE invoice_unit(invoice_id,unit_number),INDEX expires_lookup(license_type,expires_at),FOREIGN KEY (license_id) REFERENCES '.$t('website_licenses').'(id) ON DELETE RESTRICT)'.$e,
        'software_orders' => 'CREATE TABLE '.$t('software_orders').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,client_id INT UNSIGNED NOT NULL,invoice_id INT UNSIGNED NOT NULL UNIQUE,product_id INT UNSIGNED NOT NULL,sku VARCHAR(60) NOT NULL,quantity SMALLINT UNSIGNED NOT NULL,domain VARCHAR(253) NOT NULL,request_key CHAR(64) NOT NULL UNIQUE,request_hash CHAR(64) NOT NULL,snapshot LONGTEXT NOT NULL,billing_term VARCHAR(10) NOT NULL DEFAULT \'once\',created_at DATETIME NOT NULL,INDEX client_lookup(client_id,created_at))'.$e,
        'software_assets' => 'CREATE TABLE '.$t('software_assets').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sku VARCHAR(60) NOT NULL,version VARCHAR(40) NOT NULL,title VARCHAR(120) NOT NULL,file_path VARCHAR(500) NOT NULL,sha256 CHAR(64) NOT NULL,byte_size BIGINT UNSIGNED NOT NULL,is_published TINYINT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,UNIQUE sku_version(sku,version))'.$e,
        'software_charges' => 'CREATE TABLE '.$t('software_charges').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL UNIQUE,client_id INT UNSIGNED NOT NULL,amount_cents INT UNSIGNED NOT NULL,status VARCHAR(20) NOT NULL,request_key CHAR(64) NOT NULL UNIQUE,transaction_id VARCHAR(32) NULL UNIQUE,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL)'.$e,
        'software_subscriptions' => 'CREATE TABLE '.$t('software_subscriptions').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL UNIQUE,client_id INT UNSIGNED NOT NULL,gateway_subscription_id VARCHAR(25) NULL UNIQUE,interval_months SMALLINT UNSIGNED NOT NULL,amount_cents INT UNSIGNED NOT NULL,status VARCHAR(25) NOT NULL,consented_at DATETIME NOT NULL,created_at DATETIME NOT NULL)'.$e,
        'software_subscription_receipts' => 'CREATE TABLE '.$t('software_subscription_receipts').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,subscription_invoice_id INT UNSIGNED NOT NULL,gateway_transaction_id VARCHAR(30) NOT NULL UNIQUE,invoice_id INT UNSIGNED NOT NULL UNIQUE,created_at DATETIME NOT NULL,INDEX subscription_lookup(subscription_invoice_id,id))'.$e,
        'software_notices' => 'CREATE TABLE '.$t('software_notices').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL UNIQUE,client_id INT UNSIGNED NOT NULL,status VARCHAR(15) NOT NULL DEFAULT \'pending\',attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,last_attempt DATETIME NULL,sent_at DATETIME NULL,last_error VARCHAR(120) NOT NULL DEFAULT \'\',INDEX client_lookup(client_id))'.$e,
        'software_usage' => 'CREATE TABLE '.$t('software_usage').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,license_id INT UNSIGNED NOT NULL,domain VARCHAR(253) NOT NULL,first_seen DATETIME NOT NULL,last_seen DATETIME NOT NULL,check_count INT UNSIGNED NOT NULL DEFAULT 1,UNIQUE license_domain(license_id,domain),FOREIGN KEY (license_id) REFERENCES '.$t('website_licenses').'(id) ON DELETE RESTRICT)'.$e
    ];
}
