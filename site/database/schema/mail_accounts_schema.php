<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function mail_accounts_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';
    return [
        'mail_accounts'=>'CREATE TABLE `'.$prefix.'mail_accounts` (id '.$id.' PRIMARY KEY,revision INT UNSIGNED NOT NULL,label VARCHAR(120) NOT NULL,address VARCHAR(190) NOT NULL,owner_id INT UNSIGNED NOT NULL,imap_host VARCHAR(253) NOT NULL,imap_port INT UNSIGNED NOT NULL,imap_encryption VARCHAR(10) NOT NULL,imap_username VARCHAR(190) NOT NULL,smtp_host VARCHAR(253) NOT NULL,smtp_port INT UNSIGNED NOT NULL, smtp_encryption VARCHAR(10) NOT NULL,smtp_username VARCHAR(190) NOT NULL,credentials TEXT NOT NULL,enabled INT UNSIGNED NOT NULL DEFAULT 0,updated_by INT UNSIGNED NOT NULL,updated_at DATETIME NOT NULL)'.$engine,
        'mail_account_revisions'=>'CREATE TABLE `'.$prefix.'mail_account_revisions` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id '.$id.' NOT NULL,revision INT UNSIGNED NOT NULL,metadata TEXT NOT NULL,action VARCHAR(20) NOT NULL,actor_id INT UNSIGNED NOT NULL,recorded_at DATETIME NOT NULL,UNIQUE account_revision(account_id,revision))'.$engine,
        'mail_account_grants'=>'CREATE TABLE `'.$prefix.'mail_account_grants` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id '.$id.' NOT NULL,user_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,can_read INT UNSIGNED NOT NULL DEFAULT 0,can_send INT UNSIGNED NOT NULL DEFAULT 0,enabled INT UNSIGNED NOT NULL DEFAULT 0,issued_at BIGINT UNSIGNED NOT NULL,expires_at BIGINT UNSIGNED NULL,issued_by INT UNSIGNED NOT NULL,updated_at DATETIME NOT NULL,UNIQUE account_staff(account_id,user_id),INDEX staff_mailboxes(user_id,enabled))'.$engine,
        'mail_grant_revisions'=>'CREATE TABLE `'.$prefix.'mail_grant_revisions` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id '.$id.' NOT NULL,user_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,can_read INT UNSIGNED NOT NULL,can_send INT UNSIGNED NOT NULL,enabled INT UNSIGNED NOT NULL,issued_at BIGINT UNSIGNED NOT NULL,expires_at BIGINT UNSIGNED NULL,actor_id INT UNSIGNED NOT NULL,recorded_at DATETIME NOT NULL,UNIQUE grant_revision(account_id,user_id,revision))'.$engine,
    ];
}
