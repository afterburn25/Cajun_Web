<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function provider_php_control_delegation_schema(string $prefix):array {
    if(preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix)!==1)throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    return ['provider_php_control_delegations'=>'CREATE TABLE `'.$prefix.'provider_php_control_delegations` (id '.$id.' PRIMARY KEY,link_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,owner_id INT UNSIGNED NOT NULL,recorded_epoch BIGINT UNSIGNED NOT NULL,previous_sha256 '.$hash.' NOT NULL,document_json LONGTEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,UNIQUE KEY php_control_delegation_revision(link_id,revision),INDEX php_control_delegation_client(client_id),INDEX php_control_delegation_owner(owner_id),FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT,FOREIGN KEY (owner_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
