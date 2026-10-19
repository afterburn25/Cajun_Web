<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function mail_replies_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';
    return ['mail_reply_drafts'=>'CREATE TABLE '.$prefix.'mail_reply_drafts (
        id '.$id.' PRIMARY KEY,
        source_account_id '.$id.' NOT NULL,
        source_read_id '.$id.' NOT NULL,
        revision INT UNSIGNED NOT NULL,
        status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        origin_json TEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        content_json TEXT NOT NULL,
        content_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        INDEX source_account_id(source_account_id),
        INDEX source_read_id(source_read_id),
        FOREIGN KEY (id) REFERENCES '.$prefix.'mail_drafts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
        FOREIGN KEY (source_account_id) REFERENCES '.$prefix.'mail_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
        FOREIGN KEY (source_read_id) REFERENCES '.$prefix.'mail_message_reads (id) ON DELETE RESTRICT ON UPDATE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
