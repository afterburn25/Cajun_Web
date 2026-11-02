<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function pipeline_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';return [
    'lead_stage_history'=>'CREATE TABLE `'.$prefix.'lead_stage_history` (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,lead_id INT UNSIGNED NOT NULL,from_stage VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,to_stage VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_id INT UNSIGNED NOT NULL,note VARCHAR(500) NOT NULL DEFAULT \'\',created_at DATETIME NOT NULL,INDEX lead_history(lead_id,id),INDEX history_recorded(created_at))'.$engine];
}
