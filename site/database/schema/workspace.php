<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function studio_schema(string $prefix): array {
 if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
 $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';return [
 'studio_clients'=>'CREATE TABLE '.$t('studio_clients')." (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL DEFAULT '',auth_version CHAR(32) NOT NULL,active TINYINT NOT NULL DEFAULT 1,invite_hash CHAR(64) NULL,invite_expires BIGINT NULL,created_at DATETIME NOT NULL)".$e,
 'studio_jobs'=>'CREATE TABLE '.$t('studio_jobs')." (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,client_id INT UNSIGNED NOT NULL,title VARCHAR(160) NOT NULL,summary TEXT NOT NULL,stage VARCHAR(30) NOT NULL DEFAULT 'discovery',milestones LONGTEXT NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX client_lookup(client_id,id))".$e,
 'studio_messages'=>'CREATE TABLE '.$t('studio_messages')." (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,job_id INT UNSIGNED NOT NULL,author_type VARCHAR(10) NOT NULL,author_id INT UNSIGNED NOT NULL,body TEXT NOT NULL,created_at DATETIME NOT NULL,INDEX job_lookup(job_id,id))".$e,
 'studio_versions'=>'CREATE TABLE '.$t('studio_versions')." (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,job_id INT UNSIGNED NOT NULL,kind VARCHAR(20) NOT NULL,version_no INT UNSIGNED NOT NULL,title VARCHAR(160) NOT NULL,body LONGTEXT NOT NULL,preview_url VARCHAR(500) NOT NULL DEFAULT '',decision VARCHAR(20) NOT NULL DEFAULT 'pending',feedback TEXT NOT NULL,decided_by INT UNSIGNED NULL,decided_at DATETIME NULL,created_at DATETIME NOT NULL,UNIQUE version_lookup(job_id,kind,version_no))".$e,
 'studio_files'=>'CREATE TABLE '.$t('studio_files')." (id CHAR(32) PRIMARY KEY,job_id INT UNSIGNED NOT NULL,author_type VARCHAR(10) NOT NULL,original_name VARCHAR(190) NOT NULL,stored_name VARCHAR(80) NOT NULL,mime VARCHAR(100) NOT NULL,bytes INT UNSIGNED NOT NULL,sha256 CHAR(64) NOT NULL,created_at DATETIME NOT NULL,INDEX job_lookup(job_id,id))".$e,
 'studio_revisions'=>'CREATE TABLE '.$t('studio_revisions')." (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,setting_name VARCHAR(100) NOT NULL,old_value LONGTEXT NOT NULL,new_value LONGTEXT NOT NULL,author_id INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,INDEX content_lookup(setting_name,id))".$e,
 'studio_invoices'=>'CREATE TABLE '.$t('studio_invoices')." (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,job_id INT UNSIGNED NOT NULL,reference VARCHAR(40) NOT NULL UNIQUE,title VARCHAR(160) NOT NULL,amount_cents INT UNSIGNED NOT NULL,currency CHAR(3) NOT NULL DEFAULT 'USD',status VARCHAR(20) NOT NULL DEFAULT 'issued',instructions TEXT NOT NULL,paid_note VARCHAR(500) NOT NULL DEFAULT '',created_at DATETIME NOT NULL,paid_at DATETIME NULL,INDEX job_lookup(job_id,id))".$e,
 ];
}
