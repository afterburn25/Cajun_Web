<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function attempts_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'payment_attempts'=>'CREATE TABLE '.$t('payment_attempts').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id INT UNSIGNED NOT NULL UNIQUE,invoice_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,attempt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,reference CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,environment VARCHAR(10) NOT NULL,account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,gateway_revision INT UNSIGNED NOT NULL,amount_cents INT UNSIGNED NOT NULL,currency CHAR(3) NOT NULL,state VARCHAR(30) NOT NULL,transaction_id VARCHAR(20) NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE gateway_transaction(environment,account_hash,transaction_id),INDEX invoice_state(invoice_id,state))'.$e,
        'payment_attempt_events'=>'CREATE TABLE '.$t('payment_attempt_events').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,attempt_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,event VARCHAR(30) NOT NULL,created_at DATETIME NOT NULL,INDEX attempt_history(attempt_id,id))'.$e,
    ];
}
