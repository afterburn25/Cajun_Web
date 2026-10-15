<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function hosting_reconciliation_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    return [
        'hosting_observations'=>'CREATE TABLE `'.$prefix.'hosting_observations` (id '.$id.' PRIMARY KEY,dispatch_id '.$id.' NOT NULL,revision INT UNSIGNED NOT NULL,journal_sha256 '.$hash.' NOT NULL,recorded_epoch BIGINT UNSIGNED NOT NULL,observation_json '.$text.' NOT NULL,INDEX dispatch_id(dispatch_id),FOREIGN KEY (dispatch_id) REFERENCES `'.$prefix.'hosting_dispatches` (id) ON DELETE RESTRICT)'.$engine,
        'hosting_reconciliations'=>'CREATE TABLE `'.$prefix.'hosting_reconciliations` (id '.$id.' PRIMARY KEY,observation_id '.$id.' NOT NULL,event_id '.$id.' NOT NULL,UNIQUE KEY observation_id(observation_id),UNIQUE KEY event_id(event_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'hosting_dispatches` (id) ON DELETE RESTRICT,FOREIGN KEY (observation_id) REFERENCES `'.$prefix.'hosting_observations` (id) ON DELETE RESTRICT,FOREIGN KEY (event_id) REFERENCES `'.$prefix.'hosting_record_events` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
