<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
/** Directed per-recipient notification feed (P-07). Distinct from `notifications_schema` (payment webhooks) and `activity_schema` (shared event stream). */
function notification_feed_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';return [
    'notification_feed'=>'CREATE TABLE `'.$prefix.'notification_feed` (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,audience VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,recipient_id INT UNSIGNED NOT NULL,kind VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,ref VARCHAR(90) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,title VARCHAR(200) NOT NULL,body VARCHAR(1000) NOT NULL DEFAULT \'\',created_at DATETIME NOT NULL,read_at DATETIME NULL,UNIQUE feed_recipient(audience,recipient_id,id))'.$engine,
    'notification_prefs'=>'CREATE TABLE `'.$prefix.'notification_prefs` (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,audience VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,recipient_id INT UNSIGNED NOT NULL,kind VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,email TINYINT(1) NOT NULL DEFAULT 1,updated_at DATETIME NOT NULL,UNIQUE prefs_scope(audience,recipient_id,kind))'.$engine,
    'notification_deliveries'=>'CREATE TABLE `'.$prefix.'notification_deliveries` (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,notification_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,channel VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,state VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,attempts INT UNSIGNED NOT NULL DEFAULT 0,last_error VARCHAR(300) NOT NULL DEFAULT \'\',sent_at INT UNSIGNED NULL,next_at INT UNSIGNED NULL,updated_at DATETIME NOT NULL,UNIQUE delivery_channel(notification_id,channel))'.$engine,
    'notification_announcements'=>'CREATE TABLE `'.$prefix.'notification_announcements` (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,actor_id INT UNSIGNED NOT NULL,title VARCHAR(200) NOT NULL,body VARCHAR(1000) NOT NULL,recipients INT UNSIGNED NOT NULL DEFAULT 0,created_at DATETIME NOT NULL)'.$engine];
}
