<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function hosting_operations_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$ascii=' CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    return [
        'hosting_dispatches'=>'CREATE TABLE `'.$prefix.'hosting_dispatches` (id '.$id.' PRIMARY KEY,account_id '.$id.' NOT NULL,profile_id '.$id.' NOT NULL,profile_revision INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,operation VARCHAR(32)'.$ascii.' NOT NULL,attempt_ref VARCHAR(128)'.$ascii.' NOT NULL,started_epoch BIGINT UNSIGNED NOT NULL,input_json '.$text.' NOT NULL,binding_json '.$text.' NOT NULL,UNIQUE KEY account_revision(account_id,revision),UNIQUE KEY account_attempt(account_id,attempt_ref),INDEX profile_id(profile_id),INDEX actor_id(actor_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'hosting_record_events` (id) ON DELETE RESTRICT,FOREIGN KEY (account_id) REFERENCES `'.$prefix.'hosting_records` (id) ON DELETE RESTRICT,FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
        'hosting_operation_results'=>'CREATE TABLE `'.$prefix.'hosting_operation_results` (id '.$id.' PRIMARY KEY,event_id '.$id.' NOT NULL,result_json '.$text.' NOT NULL,UNIQUE KEY event_id(event_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'hosting_dispatches` (id) ON DELETE RESTRICT,FOREIGN KEY (event_id) REFERENCES `'.$prefix.'hosting_record_events` (id) ON DELETE RESTRICT)'.$engine,
        'hosting_resources'=>'CREATE TABLE `'.$prefix.'hosting_resources` (id '.$id.' PRIMARY KEY,account_id '.$id.' NOT NULL,profile_id '.$id.' NOT NULL,resource_ref VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,UNIQUE KEY account_id(account_id),UNIQUE KEY provider_resource(profile_id,resource_ref),FOREIGN KEY (id) REFERENCES `'.$prefix.'hosting_operation_results` (id) ON DELETE RESTRICT,FOREIGN KEY (account_id) REFERENCES `'.$prefix.'hosting_records` (id) ON DELETE RESTRICT,FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
