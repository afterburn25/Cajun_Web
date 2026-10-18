<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function domain_expiry_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    return [
        'domain_expiry_bindings'=>'CREATE TABLE `'.$prefix.'domain_expiry_bindings` (id '.$id.' PRIMARY KEY,link_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,started_epoch BIGINT UNSIGNED NOT NULL,binding_json LONGTEXT NOT NULL,binding_sha256 '.$hash.' NOT NULL,INDEX link_reads(link_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'domain_expiry_timestamps'=>'CREATE TABLE `'.$prefix.'domain_expiry_timestamps` (id '.$id.' PRIMARY KEY,expiry_timestamp VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,observed_epoch BIGINT UNSIGNED NOT NULL,document_sha256 '.$hash.' NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'domain_expiry_bindings` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];
}
