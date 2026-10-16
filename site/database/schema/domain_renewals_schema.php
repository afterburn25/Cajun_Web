<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function domain_renewals_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'domain_renewal_requests'=>'CREATE TABLE `'.$prefix.'domain_renewal_requests` (id '.$id.' PRIMARY KEY,client_id INT UNSIGNED NOT NULL,link_id '.$id.' NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,document_json LONGTEXT NOT NULL,document_sha256 '.$hash.' NOT NULL,state VARCHAR(14) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,revision INT UNSIGNED NOT NULL,current_sha256 '.$hash.' NOT NULL,INDEX client_requests(client_id,created_epoch),INDEX link_requests(link_id,state),FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT)'.$engine,
        'domain_renewal_events'=>'CREATE TABLE `'.$prefix.'domain_renewal_events` (id '.$id.' PRIMARY KEY,request_id '.$id.' NOT NULL,revision INT UNSIGNED NOT NULL,document_json LONGTEXT NOT NULL,document_sha256 '.$hash.' NOT NULL,UNIQUE KEY request_revision(request_id,revision),FOREIGN KEY (request_id) REFERENCES `'.$prefix.'domain_renewal_requests` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
