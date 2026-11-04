<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function contacts_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'studio_contacts'=>'CREATE TABLE '.$t('studio_contacts').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,client_id INT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL DEFAULT \'\',auth_version CHAR(32) NOT NULL,permissions VARCHAR(400) NOT NULL,enabled TINYINT NOT NULL DEFAULT 1,invite_hash CHAR(64) NULL,invite_expires BIGINT NULL,created_at DATETIME NOT NULL,INDEX contact_client(client_id))'.$e,
    ];
}
