<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

/** Consent/readback witnesses only. Provider execution remains in provider_operations. */
function provider_directory_indexing_controls_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET ascii COLLATE ascii_bin';$tail=' ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin';
    return [
        'provider_directory_changes'=>'CREATE TABLE `'.$prefix.'provider_directory_changes` (id '.$id.' PRIMARY KEY,grant_seq BIGINT UNSIGNED NOT NULL,profile_id '.$id.' NOT NULL,package_id BIGINT UNSIGNED NOT NULL,link_id '.$id.' NOT NULL,actor_id INT UNSIGNED NOT NULL,document_json '.$text.' NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,UNIQUE KEY directory_indexing_change_seq(grant_seq),INDEX directory_indexing_change_resource(profile_id,package_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_operation_grants` (id) ON DELETE RESTRICT)'.$tail,
        'provider_directory_dispatch_ends'=>'CREATE TABLE `'.$prefix.'provider_directory_dispatch_ends` (id '.$id.' PRIMARY KEY,actor_id INT UNSIGNED NOT NULL,disposition VARCHAR(12) NOT NULL,ledger_sha256 '.$hash.' NOT NULL,document_json '.$text.' NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_directory_changes` (id) ON DELETE RESTRICT)'.$tail,
        'provider_directory_change_checks'=>'CREATE TABLE `'.$prefix.'provider_directory_change_checks` (id '.$id.' PRIMARY KEY,request_id '.$id.' NOT NULL,actor_id INT UNSIGNED NOT NULL,receipt_id '.$id.' NOT NULL,disposition VARCHAR(12) NOT NULL,document_json '.$text.' NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,UNIQUE KEY directory_indexing_check_receipt(receipt_id),INDEX directory_indexing_check_request(request_id),FOREIGN KEY (request_id) REFERENCES `'.$prefix.'provider_directory_changes` (id) ON DELETE RESTRICT,FOREIGN KEY (receipt_id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT)'.$tail,
    ];
}
