<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function provider_cloud_providers_schema(string $prefix):array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    return ['provider_observation_cloud_providers'=>'CREATE TABLE `'.$prefix.'provider_observation_cloud_providers` (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,identifiers TEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL,observed_epoch BIGINT UNSIGNED NOT NULL,proof CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_refreshes` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
