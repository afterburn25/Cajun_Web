<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function activity_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    return ['activity_reads'=>'CREATE TABLE `'.$prefix.'activity_reads` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,actor_type VARCHAR(10) NOT NULL,actor_id INT UNSIGNED NOT NULL,event_key VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,read_at DATETIME NOT NULL,UNIQUE read_event(actor_type,actor_id,event_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
