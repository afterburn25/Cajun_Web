<?php
/** Additive financed-site license bindings. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function license_financing_schema(string $prefix):array{
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid database prefix.');
    $t=static fn(string $name):string=>chr(96).$prefix.$name.chr(96);
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'license_financing'=>'CREATE TABLE '.$t('license_financing').' ('.
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'.
            'license_id INT UNSIGNED NOT NULL,'.
            'schedule_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'terms_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'installments INT UNSIGNED NOT NULL,'.
            'grace_days INT UNSIGNED NOT NULL,'.
            'state VARCHAR(20) NOT NULL DEFAULT \'active\','.
            'paid_off_at DATETIME NULL,'.
            'allow_resale TINYINT NOT NULL DEFAULT 0,'.
            'allow_distribution TINYINT NOT NULL DEFAULT 0,'.
            'exclusive_resale TINYINT NOT NULL DEFAULT 0,'.
            'exclusive_distribution TINYINT NOT NULL DEFAULT 0,'.
            'created_by INT UNSIGNED NOT NULL,'.
            'created_at DATETIME NOT NULL,'.
            'updated_at DATETIME NOT NULL,'.
            'UNIQUE KEY license_lookup(license_id),'.
            'UNIQUE KEY schedule_lookup(schedule_id),'.
            'INDEX state_lookup(state),'.
            'FOREIGN KEY (license_id) REFERENCES '.$t('website_licenses').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (schedule_id) REFERENCES '.$t('recurring_billing_schedules').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (created_by) REFERENCES '.$t('users').'(id) ON DELETE RESTRICT'.
            ')'.$engine,
    ];
}
