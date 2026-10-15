<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Website licensing schema — for DRM/licensing of bundled Website Core products.
 * See Website_Core/docs/LICENSING_API.md for the API contract.
 */
function license_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid database prefix.');
    $t=fn($n)=>'`'.$prefix.$n.'`';$engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'website_licenses'=>'CREATE TABLE '.$t('website_licenses').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,license_key VARCHAR(255) NOT NULL UNIQUE,customer_id INT UNSIGNED NOT NULL,product_id INT UNSIGNED NOT NULL,is_active TINYINT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX customer_lookup(customer_id),INDEX product_lookup(product_id),FOREIGN KEY (customer_id) REFERENCES '.$t('studio_clients').'(id) ON DELETE CASCADE)'.$engine,
        'license_domains'=>'CREATE TABLE '.$t('license_domains').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,license_id INT UNSIGNED NOT NULL,domain VARCHAR(255) NOT NULL,is_active TINYINT NOT NULL DEFAULT 1,registered_at DATETIME NOT NULL,deactivated_at DATETIME NULL,UNIQUE license_domain(license_id,domain),INDEX active_lookup(license_id,is_active),FOREIGN KEY (license_id) REFERENCES '.$t('website_licenses').'(id) ON DELETE CASCADE)'.$engine,
        'license_bundles'=>'CREATE TABLE '.$t('license_bundles').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,license_id INT UNSIGNED NOT NULL,file_path VARCHAR(500) NOT NULL,title VARCHAR(200) NOT NULL,created_at DATETIME NOT NULL,INDEX license_lookup(license_id),FOREIGN KEY (license_id) REFERENCES '.$t('website_licenses').'(id) ON DELETE CASCADE)'.$engine,
    ];
}
