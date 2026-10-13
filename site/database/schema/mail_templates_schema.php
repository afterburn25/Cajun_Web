<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function mail_templates_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D', $prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $content='name VARCHAR(120) NOT NULL,subject VARCHAR(200) NOT NULL,body TEXT NOT NULL,status VARCHAR(10) NOT NULL,visibility VARCHAR(10) NOT NULL,owner_id INT UNSIGNED NOT NULL';
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'mail_templates'=>'CREATE TABLE `'.$prefix.'mail_templates` (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,revision INT UNSIGNED NOT NULL,'.$content.',updated_by INT UNSIGNED NOT NULL,updated_at DATETIME NOT NULL,INDEX library(visibility,owner_id,status))'.$engine,
        'mail_template_revisions'=>'CREATE TABLE `'.$prefix.'mail_template_revisions` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,template_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,revision INT UNSIGNED NOT NULL,'.$content.',action VARCHAR(20) NOT NULL,actor_id INT UNSIGNED NOT NULL,recorded_at DATETIME NOT NULL,UNIQUE template_revision(template_id,revision))'.$engine,
    ];
}
