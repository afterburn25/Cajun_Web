<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function payments_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'payment_gateways'=>'CREATE TABLE '.$t('payment_gateways').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,environment VARCHAR(10) NOT NULL UNIQUE,credentials LONGTEXT NOT NULL,revision INT UNSIGNED NOT NULL,validation VARCHAR(30) NOT NULL,validated_at DATETIME NULL,updated_at DATETIME NOT NULL)'.$e,
        'payment_requests'=>'CREATE TABLE '.$t('payment_requests').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,invoice_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,link_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,link_secret VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,purpose VARCHAR(20) NOT NULL,status VARCHAR(20) NOT NULL,amount_cents INT UNSIGNED NOT NULL,currency CHAR(3) NOT NULL,expires_at BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,UNIQUE link_token(link_hash),INDEX invoice_lookup(invoice_id,id))'.$e,
    ];
}
