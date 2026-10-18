<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function hosting_fulfillment_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    return ['hosting_fulfillments'=>'CREATE TABLE `'.$prefix.'hosting_fulfillments` (id '.$id.' PRIMARY KEY,order_id '.$id.' NOT NULL,account_id '.$id.' NOT NULL,actor_id INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,input_json '.$text.' NOT NULL,binding_json '.$text.' NOT NULL,binding_sha256 '.$hash.' NOT NULL,INDEX order_id(order_id),INDEX account_id(account_id),INDEX actor_id(actor_id),FOREIGN KEY (id) REFERENCES `'.$prefix.'hosting_dispatches` (id) ON DELETE RESTRICT,FOREIGN KEY (order_id) REFERENCES `'.$prefix.'hosting_order_links` (id) ON DELETE RESTRICT,FOREIGN KEY (account_id) REFERENCES `'.$prefix.'hosting_records` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
