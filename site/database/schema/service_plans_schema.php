<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function service_plans_schema(string $prefix,bool $checkout=false):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';$id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    $document='created_by INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,initial_json '.$text.' NOT NULL,journal_json '.$text.' NOT NULL,journal_sha256 '.$hash.' NOT NULL,revision INT UNSIGNED NOT NULL';
    $event='revision INT UNSIGNED NOT NULL,recorded_epoch BIGINT UNSIGNED NOT NULL,input_json '.$text.' NOT NULL,before_sha256 '.$hash.' NOT NULL,after_sha256 '.$hash.' NOT NULL';
    $schema=[
        'service_plans'=>'CREATE TABLE `'.$prefix.'service_plans` (id '.$id.' PRIMARY KEY,'.$document.',INDEX created_by(created_by),FOREIGN KEY (created_by) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
        'service_plan_events'=>'CREATE TABLE `'.$prefix.'service_plan_events` (id '.$id.' PRIMARY KEY,plan_id '.$id.' NOT NULL,actor_id INT UNSIGNED NOT NULL,'.$event.',UNIQUE KEY plan_revision(plan_id,revision),INDEX actor_id(actor_id),FOREIGN KEY (plan_id) REFERENCES `'.$prefix.'service_plans` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
        'service_subscriptions'=>'CREATE TABLE `'.$prefix.'service_subscriptions` (id '.$id.' PRIMARY KEY,plan_id '.$id.' NOT NULL,plan_revision INT UNSIGNED NOT NULL,plan_sha256 '.$hash.' NOT NULL,client_id INT UNSIGNED NOT NULL,'.$document.',INDEX plan_id(plan_id),INDEX client_id(client_id),INDEX created_by(created_by),FOREIGN KEY (plan_id) REFERENCES `'.$prefix.'service_plans` (id) ON DELETE RESTRICT,FOREIGN KEY (client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT,FOREIGN KEY (created_by) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT)'.$engine,
        'service_subscription_events'=>'CREATE TABLE `'.$prefix.'service_subscription_events` (id '.$id.' PRIMARY KEY,subscription_id '.$id.' NOT NULL,actor_staff_id INT UNSIGNED NULL,actor_client_id INT UNSIGNED NULL,'.$event.',UNIQUE KEY subscription_revision(subscription_id,revision),INDEX actor_staff_id(actor_staff_id),INDEX actor_client_id(actor_client_id),FOREIGN KEY (subscription_id) REFERENCES `'.$prefix.'service_subscriptions` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_staff_id) REFERENCES `'.$prefix.'users` (id) ON DELETE RESTRICT,FOREIGN KEY (actor_client_id) REFERENCES `'.$prefix.'studio_clients` (id) ON DELETE RESTRICT)'.$engine,
    ];
    if($checkout){
        $schema['service_subscriptions']=str_replace('created_by INT UNSIGNED NOT NULL,','created_by INT UNSIGNED NULL,',$schema['service_subscriptions']);
        $schema['service_subscriptions']=str_replace('revision INT UNSIGNED NOT NULL,INDEX plan_id','revision INT UNSIGNED NOT NULL,created_by_client INT UNSIGNED NULL,INDEX created_by_client(created_by_client),FOREIGN KEY (created_by_client) REFERENCES '.$prefix.'studio_clients (id) ON DELETE RESTRICT,INDEX plan_id',$schema['service_subscriptions']);
        $schema['service_subscription_events']=str_replace('after_sha256 '.$hash.' NOT NULL,UNIQUE KEY','after_sha256 '.$hash.' NOT NULL,actor_system VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,UNIQUE KEY',$schema['service_subscription_events']);
    }
    return $schema;
}
