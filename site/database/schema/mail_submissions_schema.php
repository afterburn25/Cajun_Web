<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function mail_submissions_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'mail_submissions'=>'CREATE TABLE '.$prefix.'mail_submissions (
            id '.$id.' PRIMARY KEY,
            attempt_id '.$id.' NOT NULL,
            owner_id INT UNSIGNED NOT NULL,
            draft_id '.$id.' NOT NULL,
            draft_revision INT UNSIGNED NOT NULL,
            account_id '.$id.' NOT NULL,
            account_revision INT UNSIGNED NOT NULL,
            prepared_epoch BIGINT UNSIGNED NOT NULL,
            payload_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            snapshot_fingerprint '.$hash.' NOT NULL,
            mime_bytes LONGTEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            mime_sha256 '.$hash.' NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE attempt_id(attempt_id),
            UNIQUE draft_revision(draft_id,draft_revision),
            INDEX owner_history(owner_id,created_at,id),
            INDEX account_id(account_id),
            FOREIGN KEY (owner_id) REFERENCES '.$prefix.'users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            FOREIGN KEY (draft_id) REFERENCES '.$prefix.'mail_drafts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            FOREIGN KEY (account_id) REFERENCES '.$prefix.'mail_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT
        )'.$engine,
        'mail_submission_dispatches'=>'CREATE TABLE '.$prefix.'mail_submission_dispatches (
            id '.$id.' PRIMARY KEY,
            submission_id '.$id.' NOT NULL,
            snapshot_fingerprint '.$hash.' NOT NULL,
            mime_sha256 '.$hash.' NOT NULL,
            started_epoch BIGINT UNSIGNED NOT NULL,
            started_at DATETIME NOT NULL,
            UNIQUE submission_id(submission_id),
            INDEX started_epoch(started_epoch),
            FOREIGN KEY (submission_id) REFERENCES '.$prefix.'mail_submissions (id) ON DELETE RESTRICT ON UPDATE RESTRICT
        )'.$engine,
        'mail_submission_results'=>'CREATE TABLE '.$prefix.'mail_submission_results (
            id '.$id.' PRIMARY KEY,
            submission_id '.$id.' NOT NULL,
            snapshot_fingerprint '.$hash.' NOT NULL,
            mime_sha256 '.$hash.' NOT NULL,
            scope VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            phase VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            reason VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            smtp_code INT UNSIGNED,
            finished_epoch BIGINT UNSIGNED NOT NULL,
            finished_at DATETIME NOT NULL,
            UNIQUE submission_id(submission_id),
            FOREIGN KEY (submission_id) REFERENCES '.$prefix.'mail_submissions (id) ON DELETE RESTRICT ON UPDATE RESTRICT
        )'.$engine,
    ];
}
