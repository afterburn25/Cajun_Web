<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function provider_stackcp_auth_schema(string $prefix): array {
    return provider_stackcp_journal_schema($prefix,'auth');
}
/** Both fixed families share the same immutable journal contract; arbitrary table names are refused. */
function provider_stackcp_journal_schema(string $prefix,string $kind): array {
    if (preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix)!==1 || !in_array($kind,['auth','assignment'],true)) throw new InvalidArgumentException('Invalid StackCP schema context.');
    $name=$kind==='auth'?'provider_stackcp_auth':'provider_stackcp_assignments';
    $ascii=' CHARACTER SET ascii COLLATE ascii_bin';$id='CHAR(32)'.$ascii;$hash='CHAR(64)'.$ascii;
    return [$name=>'CREATE TABLE `'.$prefix.$name.'` (id '.$id.' PRIMARY KEY,ordinal INT UNSIGNED NOT NULL,scope_id VARCHAR(64)'.$ascii.' NOT NULL,profile_id '.$id.' NOT NULL,revision INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,previous_sha256 '.$hash.' NOT NULL,document_json LONGTEXT'.$ascii.' NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL'.($kind==='auth'?',ciphertext TEXT'.$ascii.' NOT NULL':',client_id INT UNSIGNED NOT NULL').',UNIQUE KEY stackcp_ordinal(ordinal),UNIQUE KEY stackcp_scope_revision(scope_id,revision),INDEX stackcp_profile(profile_id),INDEX stackcp_actor(actor_id)'.($kind==='assignment'?',INDEX stackcp_client(client_id),FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT':'').',FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
