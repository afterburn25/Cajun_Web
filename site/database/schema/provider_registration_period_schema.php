<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function provider_registration_period_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$json='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    return [
        'provider_registration_period_reads'=>'CREATE TABLE `'.$prefix.'provider_registration_period_reads` (id '.$id.' PRIMARY KEY,ordinal BIGINT UNSIGNED NOT NULL,profile_id '.$id.' NOT NULL,profile_revision INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,started_epoch BIGINT UNSIGNED NOT NULL,refresh_seq BIGINT UNSIGNED NOT NULL,credential_sha256 '.$hash.' NOT NULL,document_json '.$json.' NOT NULL,document_sha256 '.$hash.' NOT NULL,UNIQUE KEY read_ordinal(ordinal),INDEX profile_id(profile_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT,FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'provider_observation_registration_periods'=>'CREATE TABLE `'.$prefix.'provider_observation_registration_periods` (id '.$id.' PRIMARY KEY,document_json '.$json.' NOT NULL,document_sha256 '.$hash.' NOT NULL,result_sha256 '.$hash.' NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_registration_period_reads` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];
}
