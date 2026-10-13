<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function catalog_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'catalog_products'=>'CREATE TABLE '.$t('catalog_products')." (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sku VARCHAR(60) NOT NULL UNIQUE,title VARCHAR(120) NOT NULL,family VARCHAR(20) NOT NULL,status VARCHAR(20) NOT NULL,term VARCHAR(10) NOT NULL,currency CHAR(3) NOT NULL,amount_cents INT UNSIGNED NULL,setup_cents INT UNSIGNED NOT NULL,cost_cents INT UNSIGNED NULL,provider_ref VARCHAR(160) NOT NULL,summary TEXT NOT NULL,features TEXT NOT NULL,terms TEXT NOT NULL,sort_order INT NOT NULL,revision INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL)".$e,
        'catalog_revisions'=>'CREATE TABLE '.$t('catalog_revisions').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,product_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,snapshot LONGTEXT NOT NULL,actor_id INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,UNIQUE product_revision(product_id,revision))'.$e,
        'catalog_invoice_snapshots'=>'CREATE TABLE '.$t('catalog_invoice_snapshots').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL UNIQUE,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,product_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,quantity INT UNSIGNED NOT NULL,snapshot LONGTEXT NOT NULL,created_at DATETIME NOT NULL)'.$e,
    ];
}
