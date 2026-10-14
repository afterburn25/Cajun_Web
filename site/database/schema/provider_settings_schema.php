<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function provider_settings_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$ascii=' CHARACTER SET ascii COLLATE ascii_bin';
    return [
        'provider_profiles'=>'CREATE TABLE `'.$prefix.'provider_profiles` (id '.$id.' PRIMARY KEY,provider_code VARCHAR(30)'.$ascii.' NOT NULL,environment VARCHAR(16)'.$ascii.' NOT NULL,revision INT UNSIGNED NOT NULL,label VARCHAR(120) NOT NULL,credentials TEXT'.$ascii.' NOT NULL,enabled INT UNSIGNED NOT NULL,updated_by INT UNSIGNED NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY provider_environment(provider_code,environment),INDEX updated_by(updated_by),FOREIGN KEY (updated_by) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
        'provider_revisions'=>'CREATE TABLE `'.$prefix.'provider_revisions` (id '.$id.' PRIMARY KEY,profile_id '.$id.' NOT NULL,revision INT UNSIGNED NOT NULL,label VARCHAR(120) NOT NULL,enabled INT UNSIGNED NOT NULL,has_credentials INT UNSIGNED NOT NULL,action VARCHAR(16)'.$ascii.' NOT NULL,actor_id INT UNSIGNED NOT NULL,recorded_at DATETIME NOT NULL,UNIQUE KEY profile_revision(profile_id,revision),INDEX actor_id(actor_id),FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
        'provider_checks'=>'CREATE TABLE `'.$prefix.'provider_checks` (id '.$id.' PRIMARY KEY,profile_id '.$id.' NOT NULL,profile_revision INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,started_at DATETIME NOT NULL,started_epoch BIGINT UNSIGNED NOT NULL,INDEX profile_checks(profile_id,started_epoch),INDEX actor_checks(actor_id,started_epoch),FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
        'provider_check_results'=>'CREATE TABLE `'.$prefix.'provider_check_results` (id '.$id.' PRIMARY KEY,state VARCHAR(30)'.$ascii.' NOT NULL,result_code VARCHAR(30)'.$ascii.' NOT NULL,finished_at DATETIME NOT NULL,elapsed_ms INT UNSIGNED NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_checks` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
