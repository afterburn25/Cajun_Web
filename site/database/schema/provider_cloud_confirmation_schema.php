<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

/** Append-only local evidence, separate from the immutable provider outcome. */
function provider_cloud_confirmation_schema(string $prefix):array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$ascii=' CHARACTER SET ascii COLLATE ascii_bin';
    return ['provider_cloud_confirmations'=>'CREATE TABLE `'.$prefix.'provider_cloud_confirmations` (id '.$id.' PRIMARY KEY,seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,profile_id '.$id.' NOT NULL,package_id BIGINT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,refresh_id '.$id.' NOT NULL,link_id '.$id.' NOT NULL,actor_id INT UNSIGNED NOT NULL,confirmed_at DATETIME NOT NULL,document TEXT'.$ascii.' NOT NULL,fingerprint CHAR(64)'.$ascii.' NOT NULL,UNIQUE KEY confirmed_package(profile_id,package_id),INDEX confirmation_client(client_id),INDEX confirmation_refresh(refresh_id),INDEX confirmation_actor(actor_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_operation_grants` (id) ON DELETE RESTRICT,FOREIGN KEY (profile_id) REFERENCES `'.$prefix.'provider_profiles` (id) ON DELETE RESTRICT,FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT,FOREIGN KEY (refresh_id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
