<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function ledger_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    return ['invoice_payments'=>'CREATE TABLE `'.$prefix.'invoice_payments` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,kind VARCHAR(10) NOT NULL,amount_cents INT UNSIGNED NOT NULL,currency CHAR(3) NOT NULL,method VARCHAR(20) NOT NULL,external_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,private_note VARCHAR(500) NOT NULL,received_on VARCHAR(10) NOT NULL,invoice_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,invoice_reference VARCHAR(40) NOT NULL,invoice_title VARCHAR(160) NOT NULL,invoice_total_cents INT UNSIGNED NOT NULL,client_name VARCHAR(120) NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,reversal_of BIGINT UNSIGNED NULL UNIQUE,created_at DATETIME NOT NULL,INDEX invoice_history(invoice_id,id),INDEX receipt_reference(invoice_id,external_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
// Fresh read is necessary when an older request waited while another owner enabled the ledger.
