<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function support_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'support_clients'=>'CREATE TABLE '.$t('support_clients').' (client_id INT UNSIGNED PRIMARY KEY,allowed TINYINT NOT NULL,updated_by INT UNSIGNED NOT NULL,updated_at BIGINT UNSIGNED NOT NULL)'.$e,
        'support_sessions'=>'CREATE TABLE '.$t('support_sessions')." (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,binding_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_id INT UNSIGNED NOT NULL,actor_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,client_id INT UNSIGNED NOT NULL,client_version CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,started_at BIGINT UNSIGNED NOT NULL,last_seen_at BIGINT UNSIGNED NOT NULL,expires_at BIGINT UNSIGNED NOT NULL,ended_at BIGINT UNSIGNED NULL,end_reason VARCHAR(40) NOT NULL DEFAULT '',context VARCHAR(160) NOT NULL,INDEX browser_sessions(binding_hash,ended_at))".$e,
        'support_events'=>'CREATE TABLE '.$t('support_events').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,support_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,actor_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,action VARCHAR(40) NOT NULL,object_type VARCHAR(20) NOT NULL,object_id VARCHAR(64) NOT NULL,outcome VARCHAR(40) NOT NULL,occurred_at BIGINT UNSIGNED NOT NULL,INDEX session_events(support_id,id))'.$e,
    ];
}
