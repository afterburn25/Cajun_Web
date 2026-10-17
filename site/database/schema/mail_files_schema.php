<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
/** Additive attachment blob storage for private drafts and immutable submissions. */
function mail_files_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';$id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';
    $columns='id '.$id.' PRIMARY KEY,slot INT UNSIGNED NOT NULL,name VARCHAR(180) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,mime VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,bytes INT UNSIGNED NOT NULL,sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,data MEDIUMBLOB NOT NULL,created_at DATETIME NOT NULL';
    return [
        'mail_draft_files'=>'CREATE TABLE `'.$prefix.'mail_draft_files` ('.$columns.',draft_id '.$id.' NOT NULL,INDEX draft_files(draft_id),FOREIGN KEY (draft_id) REFERENCES `'.$prefix.'mail_drafts` (id) ON DELETE RESTRICT)'.$engine,
        'mail_submission_files'=>'CREATE TABLE `'.$prefix.'mail_submission_files` ('.$columns.',submission_id '.$id.' NOT NULL,INDEX submission_files(submission_id),FOREIGN KEY (submission_id) REFERENCES `'.$prefix.'mail_submissions` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
