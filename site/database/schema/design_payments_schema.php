<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function design_payments_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';$engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'design_payment_events'=>'CREATE TABLE `'.$prefix.'design_payment_events` (id '.$id.' PRIMARY KEY,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,staff_id INT UNSIGNED NULL,client_actor_id INT UNSIGNED NULL,sequence INT UNSIGNED NOT NULL,kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,created_at DATETIME NOT NULL,event_json '.$text.' NOT NULL,event_sha256 '.$hash.' NOT NULL,input_sha256 '.$hash.' NOT NULL,UNIQUE KEY job_sequence(job_id,sequence),INDEX client_history(client_id,created_at,id),INDEX staff_id(staff_id),INDEX client_actor_id(client_actor_id),FOREIGN KEY (job_id) REFERENCES `'.$prefix.'studio_jobs` (id) ON DELETE RESTRICT,FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT,FOREIGN KEY (staff_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT,FOREIGN KEY (client_actor_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT)'.$engine,
        'design_payment_heads'=>'CREATE TABLE `'.$prefix.'design_payment_heads` (id INT UNSIGNED PRIMARY KEY,event_id '.$id.' NOT NULL,event_sha256 '.$hash.' NOT NULL,event_count INT UNSIGNED NOT NULL,retained_bytes INT UNSIGNED NOT NULL,UNIQUE KEY head_event(event_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'studio_jobs` (id) ON DELETE RESTRICT,FOREIGN KEY (event_id) REFERENCES `'.$prefix.'design_payment_events` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
