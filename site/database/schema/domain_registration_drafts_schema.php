<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function domain_registration_drafts_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$word='VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin';$tail=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'domain_registration_drafts'=>'CREATE TABLE `'.$prefix.'domain_registration_drafts` (id '.$id.' PRIMARY KEY,client_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,state '.$word.' NOT NULL,current_id '.$id.' NOT NULL,current_sha256 '.$hash.' NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,updated_epoch BIGINT UNSIGNED NOT NULL,INDEX client_drafts(client_id,updated_epoch),FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT)'.$tail,
        'domain_registration_revisions'=>'CREATE TABLE `'.$prefix.'domain_registration_revisions` (id '.$id.' PRIMARY KEY,draft_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,state '.$word.' NOT NULL,action '.$word.' NOT NULL,actor_kind '.$word.' NOT NULL,actor_id INT UNSIGNED NOT NULL,recorded_epoch BIGINT UNSIGNED NOT NULL,document_json LONGTEXT NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,ciphertext LONGTEXT NOT NULL,UNIQUE KEY draft_revision(draft_id,revision),INDEX revision_client(client_id),FOREIGN KEY (draft_id) REFERENCES `'.$prefix.'domain_registration_drafts` (id) ON DELETE RESTRICT,FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT)'.$tail,
    ];
}
