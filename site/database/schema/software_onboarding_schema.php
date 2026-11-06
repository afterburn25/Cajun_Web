<?php
/** Durable staging records for the guided software package workflow. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function software_onboarding_schema(string $prefix):array{
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid database prefix.');
    $table=chr(96).$prefix.'software_upload_requests'.chr(96);
    $end=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return ['software_upload_requests'=>'CREATE TABLE '.$table." (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_key CHAR(64) NOT NULL UNIQUE,actor_id INT UNSIGNED NOT NULL,state VARCHAR(20) NOT NULL,original_name VARCHAR(255) NOT NULL,stored_path VARCHAR(500) NOT NULL,sha256 CHAR(64) NOT NULL,byte_size BIGINT UNSIGNED NOT NULL,metadata LONGTEXT NOT NULL,product_id INT UNSIGNED NULL,asset_id BIGINT UNSIGNED NULL,last_error VARCHAR(500) NOT NULL DEFAULT '',created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,completed_at DATETIME NULL,INDEX actor_state(actor_id,state,updated_at),INDEX product_lookup(product_id),INDEX asset_lookup(asset_id))".$end];
}
