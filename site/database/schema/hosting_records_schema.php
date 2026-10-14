<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function hosting_records_schema(string $prefix):array {
    if (!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    return [
        'hosting_records'=>'CREATE TABLE `'.$prefix.'hosting_records` (id '.$id.' PRIMARY KEY,client_id INT UNSIGNED NOT NULL,created_by INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,initial_json '.$text.' NOT NULL,journal_json '.$text.' NOT NULL,journal_sha256 '.$hash.' NOT NULL,revision INT UNSIGNED NOT NULL,INDEX client_id(client_id),INDEX created_by(created_by),FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT,FOREIGN KEY (created_by) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
        'hosting_record_events'=>'CREATE TABLE `'.$prefix.'hosting_record_events` (id '.$id.' PRIMARY KEY,account_id '.$id.' NOT NULL,revision INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,recorded_epoch BIGINT UNSIGNED NOT NULL,input_json '.$text.' NOT NULL,before_sha256 '.$hash.' NOT NULL,after_sha256 '.$hash.' NOT NULL,UNIQUE KEY account_revision(account_id,revision),INDEX actor_id(actor_id),FOREIGN KEY (account_id) REFERENCES `'.$prefix.'hosting_records` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
