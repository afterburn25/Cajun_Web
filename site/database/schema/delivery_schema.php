<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function delivery_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';return [
    'website_releases'=>'CREATE TABLE `'.$prefix.'website_releases` (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,release_no INT UNSIGNED NOT NULL,version VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,title VARCHAR(160) NOT NULL,scope VARCHAR(10) NOT NULL,notes TEXT NOT NULL,source_revision VARCHAR(64) NOT NULL,bytes INT UNSIGNED NOT NULL,sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,manifest LONGTEXT NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,status VARCHAR(20) NOT NULL,created_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,published_by INT UNSIGNED NULL,published_at DATETIME NULL,UNIQUE project_version(job_id,version),UNIQUE project_sequence(job_id,release_no),INDEX client_releases(client_id,status,job_id))'.$engine,
    'delivery_events'=>'CREATE TABLE `'.$prefix.'delivery_events` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,release_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,client_id INT UNSIGNED NOT NULL,actor_type VARCHAR(10) NOT NULL,actor_id INT UNSIGNED NOT NULL,action VARCHAR(30) NOT NULL,detail VARCHAR(160) NOT NULL,created_at DATETIME NOT NULL,INDEX release_events(release_id,id))'.$engine];
}
