<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function project_planning_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';$engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'studio_plan_events'=>'CREATE TABLE `'.$prefix.'studio_plan_events` (id '.$id.' PRIMARY KEY,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,staff_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,kind VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,created_at DATETIME NOT NULL,event_json '.$text.' NOT NULL,event_sha256 '.$hash.' NOT NULL,UNIQUE KEY job_revision(job_id,revision),INDEX client_updates(client_id,created_at,id),INDEX staff_id(staff_id),FOREIGN KEY (job_id) REFERENCES `'.$prefix.'studio_jobs` (id) ON DELETE RESTRICT,FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT,FOREIGN KEY (staff_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
        'studio_plan_requests'=>'CREATE TABLE `'.$prefix.'studio_plan_requests` (id '.$id.' PRIMARY KEY,input_json '.$text.' NOT NULL,input_sha256 '.$hash.' NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'studio_plan_events` (id) ON DELETE RESTRICT)'.$engine,
        'studio_plan_heads'=>'CREATE TABLE `'.$prefix.'studio_plan_heads` (id INT UNSIGNED PRIMARY KEY,event_id '.$id.' NOT NULL,event_sha256 '.$hash.' NOT NULL,revision INT UNSIGNED NOT NULL,plan_json '.$text.' NOT NULL,plan_sha256 '.$hash.' NOT NULL,retained_bytes BIGINT UNSIGNED NOT NULL,UNIQUE KEY head_event(event_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'studio_jobs` (id) ON DELETE RESTRICT,FOREIGN KEY (event_id) REFERENCES `'.$prefix.'studio_plan_events` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
