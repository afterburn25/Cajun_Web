<?php
/** Additive public client registration, verification, and recovery storage. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function client_identity_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=fn(string $name):string=>'`'.$prefix.$name.'`';
    $e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'studio_client_registrations'=>'CREATE TABLE '.$t('studio_client_registrations').' ('
            .'client_id INT UNSIGNED PRIMARY KEY,'
            .'email_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            .'verified_at BIGINT UNSIGNED NULL,'
            .'created_at BIGINT UNSIGNED NOT NULL,'
            .'UNIQUE KEY registration_email (email_fingerprint),'
            .'FOREIGN KEY (client_id) REFERENCES '.$t('studio_clients').' (id) ON DELETE RESTRICT ON UPDATE RESTRICT'
            .')'.$e,
        'studio_client_tokens'=>'CREATE TABLE '.$t('studio_client_tokens').' ('
            .'token_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,'
            .'client_id INT UNSIGNED NOT NULL,'
            .'purpose VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            .'email_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            .'auth_version CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            .'resume_json VARCHAR(300) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            .'expires_at BIGINT UNSIGNED NOT NULL,'
            .'created_at BIGINT UNSIGNED NOT NULL,'
            .'INDEX token_client_purpose (client_id,purpose,created_at),'
            .'INDEX token_expiry (expires_at),'
            .'FOREIGN KEY (client_id) REFERENCES '.$t('studio_clients').' (id) ON DELETE RESTRICT ON UPDATE RESTRICT'
            .')'.$e,
    ];
}
