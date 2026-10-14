<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function mail_drafts_schema(string $prefix):array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $identity='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'mail_drafts'=>'CREATE TABLE '.$prefix.'mail_drafts (
            id '.$identity.' PRIMARY KEY,
            owner_id INT UNSIGNED NOT NULL,
            account_id '.$identity.' NOT NULL,
            account_revision INT UNSIGNED NOT NULL,
            revision INT UNSIGNED NOT NULL,
            status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            create_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            to_json TEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            cc_json TEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            bcc_json TEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            subject VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX owner_drafts(owner_id,account_id,status,updated_at,id),
            INDEX account_id(account_id),
            FOREIGN KEY (owner_id) REFERENCES '.$prefix.'users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            FOREIGN KEY (account_id) REFERENCES '.$prefix.'mail_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT
        )'.$engine,
        'mail_draft_revisions'=>'CREATE TABLE '.$prefix.'mail_draft_revisions (
            id '.$identity.' PRIMARY KEY,
            draft_id '.$identity.' NOT NULL,
            revision INT UNSIGNED NOT NULL,
            account_id '.$identity.' NOT NULL,
            account_revision INT UNSIGNED NOT NULL,
            status VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            action VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            actor_id INT UNSIGNED NOT NULL,
            recorded_at DATETIME NOT NULL,
            UNIQUE draft_revision(draft_id,revision),
            INDEX account_id(account_id),
            INDEX actor_id(actor_id),
            FOREIGN KEY (draft_id) REFERENCES '.$prefix.'mail_drafts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            FOREIGN KEY (account_id) REFERENCES '.$prefix.'mail_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            FOREIGN KEY (actor_id) REFERENCES '.$prefix.'users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
        )'.$engine,
    ];
}
