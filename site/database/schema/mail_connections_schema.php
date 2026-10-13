<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function mail_connections_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';$id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';
    return [
        'mail_connection_checks'=>'CREATE TABLE `'.$prefix.'mail_connection_checks` (id '.$id.' PRIMARY KEY,account_id '.$id.' NOT NULL,account_revision INT UNSIGNED NOT NULL,target VARCHAR(10) NOT NULL,actor_id INT UNSIGNED NOT NULL,started_at DATETIME NOT NULL,started_epoch BIGINT UNSIGNED NOT NULL,INDEX account_checks(account_id,started_epoch),INDEX actor_checks(actor_id,started_epoch),FOREIGN KEY (account_id) REFERENCES `'.$prefix.'mail_accounts` (id) ON DELETE RESTRICT)'.$engine,
        'mail_connection_results'=>'CREATE TABLE `'.$prefix.'mail_connection_results` (id '.$id.' PRIMARY KEY,outcome VARCHAR(10) NOT NULL,result_code VARCHAR(30) NOT NULL,finished_at DATETIME NOT NULL,elapsed_ms INT UNSIGNED NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'mail_connection_checks` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
