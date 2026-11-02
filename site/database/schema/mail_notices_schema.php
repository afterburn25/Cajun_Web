<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function mail_notices_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$uint='VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin';
    $identity='account_id '.$id.' NOT NULL,account_revision INT UNSIGNED NOT NULL,folder VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,uidvalidity '.$uint.' NOT NULL';
    return [
        'mail_notice_windows'=>'CREATE TABLE `'.$prefix.'mail_notice_windows` ('.$identity.',request_id '.$id.' NOT NULL,checked_at DATETIME NOT NULL,PRIMARY KEY(account_id),FOREIGN KEY(account_id) REFERENCES `'.$prefix.'mail_accounts` (id) ON DELETE RESTRICT,FOREIGN KEY(request_id) REFERENCES `'.$prefix.'mail_inbox_reads` (id) ON DELETE RESTRICT)'.$engine,
        'mail_notice_items'=>'CREATE TABLE `'.$prefix.'mail_notice_items` (handle '.$id.' PRIMARY KEY,'.$identity.',uid '.$uint.' NOT NULL,slot INT UNSIGNED NOT NULL,server_seen INT UNSIGNED NOT NULL,flags_observed_at DATETIME NOT NULL,UNIQUE message_identity(account_id,account_revision,folder,uidvalidity,uid),FOREIGN KEY(account_id) REFERENCES `'.$prefix.'mail_notice_windows` (account_id) ON DELETE RESTRICT)'.$engine,
        'mail_notice_states'=>'CREATE TABLE `'.$prefix.'mail_notice_states` (handle '.$id.' NOT NULL,actor_id INT UNSIGNED NOT NULL,opened_at DATETIME NULL,dismissed_at DATETIME NULL,PRIMARY KEY(handle,actor_id),FOREIGN KEY(handle) REFERENCES `'.$prefix.'mail_notice_items` (handle) ON DELETE CASCADE,FOREIGN KEY(actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
