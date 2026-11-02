<?php
/** Additive financing recovery history. No payment tokens or credentials. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function license_finance_recovery_schema(string $prefix):array{
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid database prefix.');
    $t=static fn(string $name):string=>chr(96).$prefix.$name.chr(96);
    $e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'license_finance_replacement_attempts'=>'CREATE TABLE '.$t('license_finance_replacement_attempts').' ('.
            'id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,'.
            'offer_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'open_offer_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,'.
            'old_gateway_subscription_id VARCHAR(25) NOT NULL,'.
            'gateway_subscription_id VARCHAR(25) NULL UNIQUE,'.
            'contract_json TEXT NOT NULL,'.
            'contract_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'predecessor_json TEXT NOT NULL,'.
            'predecessor_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'agreement_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'environment VARCHAR(10) NOT NULL,'.
            'account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'gateway_revision INT UNSIGNED NOT NULL,'.
            'state VARCHAR(20) NOT NULL,'.
            'predecessor_terminal_status VARCHAR(25) NULL,'.
            'retirement_required TINYINT NOT NULL DEFAULT 0,'.
            'dispatched_at DATETIME NULL,'.
            'created_at DATETIME NOT NULL,'.
            'updated_at DATETIME NOT NULL,'.
            'INDEX offer_lookup(offer_id,created_at),'.
            'FOREIGN KEY (offer_id) REFERENCES '.$t('license_finance_offers').'(id) ON DELETE RESTRICT'.
            ')'.$e,
        'license_finance_collection_reviews'=>'CREATE TABLE '.$t('license_finance_collection_reviews').' ('.
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'.
            'offer_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'gateway_subscription_id VARCHAR(25) NOT NULL,'.
            'gateway_transaction_id VARCHAR(30) NOT NULL UNIQUE,'.
            'period_index INT UNSIGNED NOT NULL,'.
            'invoice_id INT UNSIGNED NULL,'.
            'amount_cents INT UNSIGNED NOT NULL,'.
            'environment VARCHAR(10) NOT NULL,'.
            'account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'gateway_revision INT UNSIGNED NOT NULL,'.
            'contract_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'.
            'reason VARCHAR(40) NOT NULL,'.
            'state VARCHAR(20) NOT NULL,'.
            'created_at DATETIME NOT NULL,'.
            'INDEX offer_lookup(offer_id,id),'.
            'FOREIGN KEY (offer_id) REFERENCES '.$t('license_finance_offers').'(id) ON DELETE RESTRICT,'.
            'FOREIGN KEY (invoice_id) REFERENCES '.$t('studio_invoices').'(id) ON DELETE RESTRICT'.
            ')'.$e,
    ];
}
