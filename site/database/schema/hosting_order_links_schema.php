<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function hosting_order_links_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    return ['hosting_order_links'=>'CREATE TABLE `'.$prefix.'hosting_order_links` (id '.$id.' PRIMARY KEY,account_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,created_by INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,profile_id '.$id.' NOT NULL,profile_revision INT UNSIGNED NOT NULL,binding_json '.$text.' NOT NULL,binding_sha256 '.$hash.' NOT NULL,UNIQUE KEY account_id(account_id),INDEX client_id(client_id),INDEX created_by(created_by),INDEX profile_id(profile_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'service_orders` (id) ON DELETE RESTRICT,FOREIGN KEY (account_id) REFERENCES `'.$prefix.'hosting_records` (id) ON DELETE RESTRICT,FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT,FOREIGN KEY (created_by) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT,FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
