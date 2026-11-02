<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function mail_copies_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';$id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$folder='VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    return [
        'mail_sent_folders'=>'CREATE TABLE `'.$prefix.'mail_sent_folders` (id '.$id.' PRIMARY KEY,sent_folder '.$folder.' NOT NULL,updated_by INT UNSIGNED NOT NULL,updated_at DATETIME NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'mail_accounts` (id) ON DELETE RESTRICT)'.$engine,
        'mail_copy_attempts'=>'CREATE TABLE `'.$prefix.'mail_copy_attempts` (id '.$id.' PRIMARY KEY,submission_id '.$id.' NOT NULL,account_id '.$id.' NOT NULL,account_revision INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,folder '.$folder.' NOT NULL,mime_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,started_at DATETIME NOT NULL,started_epoch BIGINT UNSIGNED NOT NULL,INDEX submission_copies(submission_id),INDEX account_copies(account_id,started_epoch),FOREIGN KEY (submission_id) REFERENCES `'.$prefix.'mail_submissions` (id) ON DELETE RESTRICT,FOREIGN KEY (account_id) REFERENCES `'.$prefix.'mail_accounts` (id) ON DELETE RESTRICT)'.$engine,
        'mail_copy_results'=>'CREATE TABLE `'.$prefix.'mail_copy_results` (id '.$id.' PRIMARY KEY,outcome VARCHAR(10) NOT NULL,result_code VARCHAR(30) NOT NULL,finished_at DATETIME NOT NULL,elapsed_ms INT UNSIGNED NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'mail_copy_attempts` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
