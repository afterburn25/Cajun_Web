<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function reviews_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $t=fn(string $name): string=>'`'.$prefix.$name.'`';$engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'website_reviews'=>'CREATE TABLE '.$t('website_reviews').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,lead_id INT UNSIGNED NOT NULL,website VARCHAR(2048) NOT NULL,stage VARCHAR(30) NOT NULL,revision INT UNSIGNED NOT NULL,updated_by INT UNSIGNED NOT NULL,updated_at DATETIME NOT NULL,created_at DATETIME NOT NULL,UNIQUE review_lead(lead_id),FOREIGN KEY (lead_id) REFERENCES '.$t('leads').'(id) ON DELETE CASCADE)'.$engine,
        'website_review_reports'=>'CREATE TABLE '.$t('website_review_reports').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,review_id INT UNSIGNED NOT NULL,summary TEXT NOT NULL,findings LONGTEXT NOT NULL,prepared_by INT UNSIGNED NOT NULL,prepared_name VARCHAR(120) NOT NULL,prepared_at BIGINT UNSIGNED NOT NULL,delivered_at BIGINT UNSIGNED NULL,delivery_channel VARCHAR(30) NOT NULL,delivery_reference VARCHAR(500) NOT NULL,UNIQUE review_report(review_id),FOREIGN KEY (review_id) REFERENCES '.$t('website_reviews').'(id) ON DELETE CASCADE)'.$engine,
        'website_review_revisions'=>'CREATE TABLE '.$t('website_review_revisions').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,review_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,snapshot LONGTEXT NOT NULL,action VARCHAR(30) NOT NULL,actor_id INT UNSIGNED NOT NULL,recorded_at DATETIME NOT NULL,UNIQUE review_revision(review_id,revision),FOREIGN KEY (review_id) REFERENCES '.$t('website_reviews').'(id) ON DELETE CASCADE)'.$engine,
    ];
}
