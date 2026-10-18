<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function service_observations_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    return ['service_observation_permissions'=>'CREATE TABLE `'.$prefix.'service_observation_permissions` (id '.$id.' PRIMARY KEY,link_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,document_json LONGTEXT NOT NULL,document_sha256 '.$hash.' NOT NULL,UNIQUE KEY link_revision(link_id,revision),INDEX client_permissions(client_id),FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
