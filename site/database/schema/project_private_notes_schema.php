<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function project_private_notes_schema(string $prefix): array
{
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    return ['project_private_notes'=>'CREATE TABLE `'.$prefix.'project_private_notes` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,project_id INT UNSIGNED NOT NULL,staff_id INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,body TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,INDEX project_notes(project_id,id),INDEX staff_id(staff_id),FOREIGN KEY (project_id) REFERENCES `'.$prefix.'studio_jobs` (id) ON DELETE RESTRICT ON UPDATE RESTRICT,FOREIGN KEY (staff_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT ON UPDATE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
