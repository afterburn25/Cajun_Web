<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}

/** Explicitly prepared, append-only one-use handoff journal; no provider secrets. */
function provider_stackcp_launch_schema(string $prefix):array {
    if (preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix)!==1) throw new InvalidArgumentException('Invalid database prefix.');
    $ascii=' CHARACTER SET ascii COLLATE ascii_bin';$id='CHAR(32)'.$ascii;$hash='CHAR(64)'.$ascii;
    return ['provider_stackcp_launch_events'=>'CREATE TABLE `'.$prefix.'provider_stackcp_launch_events` (id '.$id.' PRIMARY KEY,ordinal INT UNSIGNED NOT NULL,admission_id '.$id.' NOT NULL,profile_id '.$id.' NOT NULL,package_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,kind VARCHAR(16)'.$ascii.' NOT NULL,phase VARCHAR(16)'.$ascii.' NOT NULL,document_json LONGTEXT'.$ascii.' NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,UNIQUE KEY launch_ordinal(ordinal),UNIQUE KEY launch_phase(admission_id,kind,phase),INDEX launch_package(profile_id,package_id),INDEX launch_actor(actor_id),FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
