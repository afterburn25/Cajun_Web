<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function provider_password_reset_template_schema(string $prefix):array {
    if(preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix)!==1)throw new InvalidArgumentException('Invalid table prefix.');
    $name=$prefix.'provider_password_reset_template_events';
    return ['provider_password_reset_template_events'=>'CREATE TABLE `'.$name.'` ('
        .'id CHAR(32) NOT NULL PRIMARY KEY,seq INT UNSIGNED NOT NULL,kind VARCHAR(20) NOT NULL,'
        .'profile_id CHAR(32) NOT NULL,request_id CHAR(32) NOT NULL,actor_id INT UNSIGNED NOT NULL,recorded_epoch INT UNSIGNED NOT NULL,'
        .'document_json LONGTEXT NOT NULL,document_sha256 CHAR(64) NOT NULL,ciphertext LONGTEXT NOT NULL,proof CHAR(64) NOT NULL,'
        .'UNIQUE KEY reset_template_seq(seq),KEY reset_template_profile(profile_id,seq),KEY reset_template_request(request_id,seq)'
        .') ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin'];
}
