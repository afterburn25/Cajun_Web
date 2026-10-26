<?php
/** Gateway state for financed website payments. No raw card data is stored. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function license_finance_gateway_schema(string $prefix):array{
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid database prefix.');
    $t=static fn(string $n):string=>chr(96).$prefix.$n.chr(96);
    $e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'license_finance_charges'=>'CREATE TABLE '.$t('license_finance_charges').' ('.
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'.
            'request_id INT UNSIGNED NOT NULL UNIQUE,'.
            'invoice_id INT UNSIGNED NOT NULL UNIQUE,'.
            'offer_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'amount_cents INT UNSIGNED NOT NULL,'.
            'status VARCHAR(20) NOT NULL,'.
            'request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,'.
            'transaction_id VARCHAR(32) NULL UNIQUE,'.
            'customer_profile_id VARCHAR(20) NULL,'.
            'payment_profile_id VARCHAR(20) NULL,'.
            'created_at DATETIME NOT NULL,'.
            'updated_at DATETIME NOT NULL,'.
            'INDEX offer_lookup(offer_id,status),'.
            'FOREIGN KEY (request_id) REFERENCES '.$t('payment_requests').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (invoice_id) REFERENCES '.$t('studio_invoices').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (offer_id) REFERENCES '.$t('license_finance_offers').'(id) ON DELETE RESTRICT'.
            ')'.$e,
        'license_finance_charge_attempts'=>'CREATE TABLE '.$t('license_finance_charge_attempts').' ('.
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'.
            'invoice_id INT UNSIGNED NOT NULL,'.
            'request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,'.
            'amount_cents INT UNSIGNED NOT NULL,'.
            'status VARCHAR(20) NOT NULL,'.
            'transaction_id VARCHAR(32) NULL UNIQUE,'.
            'created_at DATETIME NOT NULL,'.
            'updated_at DATETIME NOT NULL,'.
            'INDEX invoice_lookup(invoice_id,id),'.
            'FOREIGN KEY (invoice_id) REFERENCES '.$t('studio_invoices').'(id) ON DELETE RESTRICT'.
            ')'.$e,
        'license_finance_subscriptions'=>'CREATE TABLE '.$t('license_finance_subscriptions').' ('.
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'.
            'offer_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,'.
            'gateway_subscription_id VARCHAR(25) NULL UNIQUE,'.
            'customer_profile_id VARCHAR(20) NOT NULL,'.
            'payment_profile_id VARCHAR(20) NOT NULL,'.
            'interval_months INT UNSIGNED NOT NULL,'.
            'amount_cents INT UNSIGNED NOT NULL,'.
            'total_occurrences INT UNSIGNED NOT NULL,'.
            'start_date VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'status VARCHAR(25) NOT NULL,'.
            'created_at DATETIME NOT NULL,'.
            'updated_at DATETIME NOT NULL,'.
            'INDEX status_lookup(status),'.
            'FOREIGN KEY (offer_id) REFERENCES '.$t('license_finance_offers').'(id) ON DELETE RESTRICT'.
            ')'.$e,
        'license_finance_subscription_receipts'=>'CREATE TABLE '.$t('license_finance_subscription_receipts').' ('.
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'.
            'offer_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'gateway_transaction_id VARCHAR(30) NOT NULL UNIQUE,'.
            'period_index INT UNSIGNED NOT NULL,'.
            'invoice_id INT UNSIGNED NOT NULL UNIQUE,'.
            'created_at DATETIME NOT NULL,'.
            'UNIQUE KEY offer_period(offer_id,period_index),'.
            'FOREIGN KEY (offer_id) REFERENCES '.$t('license_finance_offers').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (invoice_id) REFERENCES '.$t('studio_invoices').'(id) ON DELETE RESTRICT'.
            ')'.$e,
    ];
}
