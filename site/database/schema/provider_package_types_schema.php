<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
/** Additive catalog observations; existing inventory rows and imported products are never migrated. */
function provider_package_types_schema(string $prefix):array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$ascii=' CHARACTER SET ascii COLLATE ascii_bin';
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'provider_package_type_intents'=>'CREATE TABLE `'.$prefix.'provider_package_type_intents` (id '.$id.' PRIMARY KEY,actor_auth_version VARCHAR(64)'.$ascii.' NOT NULL,key_fingerprint CHAR(64)'.$ascii.' NOT NULL,proof CHAR(64)'.$ascii.' NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT)'.$engine,
        'provider_observation_package_types'=>'CREATE TABLE `'.$prefix.'provider_observation_package_types` (id '.$id.' PRIMARY KEY,types LONGTEXT'.$ascii.' NOT NULL,observed_epoch BIGINT UNSIGNED NOT NULL,proof CHAR(64)'.$ascii.' NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
