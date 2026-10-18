<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
function provider_stackcp_access_schema(string $prefix):array {
    if (preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix)!==1) throw new InvalidArgumentException('Invalid database prefix.');
    $ascii=' CHARACTER SET ascii COLLATE ascii_bin';$id='CHAR(32)'.$ascii;$hash='CHAR(64)'.$ascii;$tail=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'provider_stackcp_reads'=>'CREATE TABLE `'.$prefix.'provider_stackcp_reads` (id '.$id.' PRIMARY KEY,ordinal INT UNSIGNED NOT NULL UNIQUE,profile_id '.$id.' NOT NULL,package_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,stack_identity VARCHAR(32)'.$ascii.' NOT NULL,operation VARCHAR(32)'.$ascii.' NOT NULL,assignment_id '.$id.' NOT NULL,document_json LONGTEXT'.$ascii.' NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,INDEX stackcp_package(profile_id,package_id),INDEX stackcp_user(profile_id,stack_identity),FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT,FOREIGN KEY (assignment_id) REFERENCES `'.$prefix.'provider_stackcp_assignments` (id) ON DELETE RESTRICT)'.$tail,
        'provider_stackcp_observations'=>'CREATE TABLE `'.$prefix.'provider_stackcp_observations` (id '.$id.' PRIMARY KEY,ordinal INT UNSIGNED NOT NULL UNIQUE,document_json LONGTEXT'.$ascii.' NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_stackcp_reads` (id) ON DELETE RESTRICT)'.$tail,
    ];
}
