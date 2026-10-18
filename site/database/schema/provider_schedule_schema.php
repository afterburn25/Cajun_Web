<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function provider_schedule_schema(string $prefix):string {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    return 'CREATE TABLE `'.$prefix.'provider_schedule_events` (id '.$id.' PRIMARY KEY,sequence BIGINT UNSIGNED NOT NULL,kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,subject_id '.$id.' NOT NULL,actor_id INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,previous_sha256 '.$hash.' NOT NULL,document_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,document_sha256 '.$hash.' NOT NULL,UNIQUE KEY event_sequence(sequence),UNIQUE KEY event_subject(kind,subject_id),INDEX actor_id(actor_id),FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
}
