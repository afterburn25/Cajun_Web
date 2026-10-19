<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function provider_directory_indexing_requests_schema(string $prefix):string {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    return 'CREATE TABLE `'.$prefix.'provider_directory_listing_requests_events` (id '.$id.' PRIMARY KEY,ordinal BIGINT UNSIGNED NOT NULL,subject_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,kind VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,document LONGTEXT NOT NULL,fingerprint '.$hash.' NOT NULL,UNIQUE KEY event_ordinal(ordinal),INDEX directory_listing_request_subject(subject_id,ordinal),INDEX directory_listing_request_client(client_id,ordinal),CONSTRAINT `'.$prefix.'directory_request_client_fk` FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
}
