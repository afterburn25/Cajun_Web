<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function provider_cloud_assignment_schema(string $prefix):array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $ascii=' CHARACTER SET ascii COLLATE ascii_bin';
    return ['provider_cloud_assignment_evidence'=>'CREATE TABLE `'.$prefix.'provider_cloud_assignment_evidence` (id BIGINT UNSIGNED PRIMARY KEY,kind VARCHAR(8)'.$ascii.' NOT NULL,source_id VARCHAR(32)'.$ascii.' NOT NULL,profile_id CHAR(32)'.$ascii.' NOT NULL,actor_id INT UNSIGNED NOT NULL,document_json LONGTEXT'.$ascii.' NOT NULL,document_sha256 CHAR(64)'.$ascii.' NOT NULL,previous_sha256 CHAR(64)'.$ascii.' NOT NULL,proof CHAR(64)'.$ascii.' NOT NULL,UNIQUE KEY evidence_source(kind,source_id),INDEX profile_history(profile_id,id),INDEX actor_history(actor_id),FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
