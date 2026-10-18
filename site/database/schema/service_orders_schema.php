<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function service_orders_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';$id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    return [
        'service_orders'=>'CREATE TABLE `'.$prefix.'service_orders` (id '.$id.' PRIMARY KEY,agreement_id '.$id.' NOT NULL,invoice_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,created_by INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,initial_json '.$text.' NOT NULL,journal_json '.$text.' NOT NULL,journal_sha256 '.$hash.' NOT NULL,revision INT UNSIGNED NOT NULL,UNIQUE KEY agreement_id(agreement_id),UNIQUE KEY invoice_id(invoice_id),INDEX client_id(client_id),INDEX created_by(created_by),FOREIGN KEY (agreement_id) REFERENCES `'.$prefix.'service_subscriptions` (id) ON DELETE RESTRICT,FOREIGN KEY (invoice_id) REFERENCES `'.$prefix.'studio_invoices` (id) ON DELETE RESTRICT,FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT,FOREIGN KEY (created_by) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
        'service_order_events'=>'CREATE TABLE `'.$prefix.'service_order_events` (id '.$id.' PRIMARY KEY,order_id '.$id.' NOT NULL,actor_staff_id INT UNSIGNED NULL,actor_client_id INT UNSIGNED NULL,revision INT UNSIGNED NOT NULL,recorded_epoch BIGINT UNSIGNED NOT NULL,input_json '.$text.' NOT NULL,before_sha256 '.$hash.' NOT NULL,after_sha256 '.$hash.' NOT NULL,UNIQUE KEY order_revision(order_id,revision),INDEX actor_staff_id(actor_staff_id),INDEX actor_client_id(actor_client_id),FOREIGN KEY (order_id) REFERENCES `'.$prefix.'service_orders` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_staff_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT)'.$engine,
    ];
}
