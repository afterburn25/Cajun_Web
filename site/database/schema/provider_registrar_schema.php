<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function provider_registrar_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$ascii=' CHARACTER SET ascii COLLATE ascii_bin';
    return [
        'provider_registrar_observations'=>'CREATE TABLE `'.$prefix.'provider_registrar_observations` (id '.$id.' PRIMARY KEY,refresh_id '.$id.' NOT NULL,domain_id BIGINT UNSIGNED NOT NULL,domain VARCHAR(253)'.$ascii.' NOT NULL,unmapped_count INT UNSIGNED NOT NULL,UNIQUE KEY observation_identity(refresh_id,domain_id),UNIQUE KEY observation_name(refresh_id,domain),FOREIGN KEY (refresh_id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT)'.$engine,
        'provider_registrar_links'=>'CREATE TABLE `'.$prefix.'provider_registrar_links` (id '.$id.' PRIMARY KEY,profile_id '.$id.' NOT NULL,domain_id BIGINT UNSIGNED NOT NULL,domain VARCHAR(253)'.$ascii.' NOT NULL,client_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,linked_by INT UNSIGNED NOT NULL,linked_at DATETIME NOT NULL,key_fingerprint CHAR(64)'.$ascii.' NOT NULL,profile_revision INT UNSIGNED NOT NULL,refresh_id '.$id.' NOT NULL,UNIQUE KEY link_target(profile_id,domain_id),UNIQUE KEY link_name(profile_id,domain),INDEX client_links(client_id),INDEX link_actor(linked_by),INDEX approved_observation(refresh_id),FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT,FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT,FOREIGN KEY (linked_by) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT,FOREIGN KEY (refresh_id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT)'.$engine,
        'provider_registrar_events'=>'CREATE TABLE `'.$prefix.'provider_registrar_events` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,link_id '.$id.' NOT NULL,profile_id '.$id.' NOT NULL,domain_id BIGINT UNSIGNED NOT NULL,domain VARCHAR(253)'.$ascii.' NOT NULL,client_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,event VARCHAR(8)'.$ascii.' NOT NULL,actor_id INT UNSIGNED NOT NULL,recorded_at DATETIME NOT NULL,key_fingerprint CHAR(64)'.$ascii.' NOT NULL,profile_revision INT UNSIGNED NOT NULL,refresh_id '.$id.' NOT NULL,INDEX profile_events(profile_id,recorded_at),INDEX client_events(client_id,recorded_at),INDEX event_actor(actor_id),INDEX link_events(link_id),FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
