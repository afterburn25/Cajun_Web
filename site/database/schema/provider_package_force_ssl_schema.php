<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function provider_force_ssl_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET ascii COLLATE ascii_bin';$tail=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'provider_force_ssl_reads'=>'CREATE TABLE `'.$prefix.'provider_force_ssl_reads` (id '.$id.' PRIMARY KEY,link_id '.$id.' NOT NULL,profile_id '.$id.' NOT NULL,package_id BIGINT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,binding_json '.$text.' NOT NULL,binding_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,INDEX force_ssl_link(link_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT)'.$tail,
        'provider_force_ssl_observations'=>'CREATE TABLE `'.$prefix.'provider_force_ssl_observations` (id '.$id.' PRIMARY KEY,value_json '.$text.' NOT NULL,observed_epoch BIGINT UNSIGNED NOT NULL,proof '.$hash.' NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_force_ssl_reads` (id) ON DELETE RESTRICT)'.$tail,
        'provider_force_ssl_permissions'=>'CREATE TABLE `'.$prefix.'provider_force_ssl_permissions` (id '.$id.' PRIMARY KEY,link_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,previous_sha256 '.$hash.' NOT NULL,document_json '.$text.' NOT NULL,document_sha256 '.$hash.' NOT NULL,UNIQUE KEY force_ssl_permission_revision(link_id,revision),INDEX force_ssl_permission_client(client_id),FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT)'.$tail,
    ];
}
