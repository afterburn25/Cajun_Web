<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function domain_renewal_agreements_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'domain_renewal_agreements'=>'CREATE TABLE `'.$prefix.'domain_renewal_agreements` (id '.$id.' PRIMARY KEY,request_id '.$id.' NOT NULL,agreement_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,document_json LONGTEXT NOT NULL,document_sha256 '.$hash.' NOT NULL,UNIQUE KEY agreement_identity(agreement_id),UNIQUE KEY request_revision(request_id,revision),INDEX client_offers(client_id,created_epoch),FOREIGN KEY (request_id) REFERENCES `'.$prefix.'domain_renewal_requests` (id) ON DELETE RESTRICT,FOREIGN KEY (agreement_id) REFERENCES `'.$prefix.'service_subscriptions` (id) ON DELETE RESTRICT)'.$engine,
        'domain_renewal_agreement_heads'=>'CREATE TABLE `'.$prefix.'domain_renewal_agreement_heads` (id '.$id.' PRIMARY KEY,binding_count INT UNSIGNED NOT NULL,current_id '.$id.' NOT NULL,current_sha256 '.$hash.' NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'domain_renewal_requests` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
