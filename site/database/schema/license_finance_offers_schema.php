<?php
/** Immutable client-specific finance offers and electronic acceptance evidence. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function license_finance_offers_schema(string $prefix):array{
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid database prefix.');
    $t=static fn(string $n):string=>chr(96).$prefix.$n.chr(96);
    $e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'license_finance_offers'=>'CREATE TABLE '.$t('license_finance_offers').' ('.
            'id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,'.
            'license_id INT UNSIGNED NOT NULL,'.
            'client_id INT UNSIGNED NOT NULL,'.
            'job_id INT UNSIGNED NOT NULL,'.
            'schedule_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'schedule_revision INT UNSIGNED NOT NULL,'.
            'schedule_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'status VARCHAR(20) NOT NULL,'.
            'title VARCHAR(190) NOT NULL,'.
            'cash_price_cents INT UNSIGNED NOT NULL,'.
            'initial_payment_cents INT UNSIGNED NOT NULL,'.
            'amount_financed_cents INT UNSIGNED NOT NULL,'.
            'finance_charge_cents INT UNSIGNED NOT NULL,'.
            'apr_basis_points INT UNSIGNED NOT NULL,'.
            'installments INT UNSIGNED NOT NULL,'.
            'grace_days INT UNSIGNED NOT NULL,'.
            'payment_link_days INT UNSIGNED NOT NULL,'.
            'terms_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,'.
            'terms_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'created_by INT UNSIGNED NOT NULL,'.
            'expires_at BIGINT UNSIGNED NOT NULL,'.
            'signed_name VARCHAR(190) NULL,'.
            'signed_email VARCHAR(190) NULL,'.
            'signed_at DATETIME NULL,'.
            'signed_terms_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,'.
            'signature_evidence_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,'.
            'activation_invoice_id INT UNSIGNED NULL,'.
            'payment_request_id INT UNSIGNED NULL,'.
            'created_at DATETIME NOT NULL,'.
            'updated_at DATETIME NOT NULL,'.
            'UNIQUE KEY schedule_lookup(schedule_id),'.
            'INDEX client_lookup(client_id,status),'.
            'INDEX license_lookup(license_id,status),'.
            'FOREIGN KEY (license_id) REFERENCES '.$t('website_licenses').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (client_id) REFERENCES '.$t('studio_clients').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (job_id) REFERENCES '.$t('studio_jobs').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (schedule_id) REFERENCES '.$t('recurring_billing_schedules').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (created_by) REFERENCES '.$t('users').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (activation_invoice_id) REFERENCES '.$t('studio_invoices').'(id) ON DELETE RESTRICT'.
            ')'.$e,
    ];
}
