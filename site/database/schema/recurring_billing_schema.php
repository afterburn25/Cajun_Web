<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function recurring_billing_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $columns=[
        'recurring_billing_sources'=>'id '.$id.' PRIMARY KEY,latest_id '.$id.' NULL,latest_sha256 '.$hash.' NULL,generations INT UNSIGNED NOT NULL',
        'recurring_billing_schedules'=>'id '.$id.' PRIMARY KEY,subscription_id '.$id.' NOT NULL,generation INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,job_id INT UNSIGNED NOT NULL,created_by INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,previous_id '.$id.' NULL,previous_sha256 '.$hash.' NULL,initial_json '.$text.' NOT NULL,journal_json '.$text.' NOT NULL,journal_sha256 '.$hash.' NOT NULL,revision INT UNSIGNED NOT NULL',
        'recurring_billing_events'=>'id '.$id.' PRIMARY KEY,schedule_id '.$id.' NOT NULL,actor_staff_id INT UNSIGNED NULL,actor_client_id INT UNSIGNED NULL,revision INT UNSIGNED NOT NULL,recorded_epoch BIGINT UNSIGNED NOT NULL,input_json '.$text.' NOT NULL,before_sha256 '.$hash.' NOT NULL,after_sha256 '.$hash.' NOT NULL',
        'recurring_billing_invoices'=>'id '.$id.' PRIMARY KEY,schedule_id '.$id.' NOT NULL,period_index INT UNSIGNED NOT NULL,invoice_id INT UNSIGNED NOT NULL,staff_id INT UNSIGNED NOT NULL,issued_epoch BIGINT UNSIGNED NOT NULL,schedule_revision INT UNSIGNED NOT NULL,schedule_sha256 '.$hash.' NOT NULL,request_sha256 '.$hash.' NOT NULL,snapshot_json '.$text.' NOT NULL,snapshot_sha256 '.$hash.' NOT NULL',
    ];
    $out=[];
    foreach($columns as $name=>$definition){
        $c=recurring_billing_constraints($name);
        foreach($c['unique'] as $key=>$fields)$definition.=',UNIQUE KEY '.$key.'('.implode(',',$fields).')';
        foreach($c['indexes'] as $key=>$fields)$definition.=',INDEX '.$key.'('.implode(',',$fields).')';
        foreach($c['relations'] as $field=>$target)$definition.=',FOREIGN KEY ('.$field.') REFERENCES `'.$prefix.$target[0].'` ('.$target[1].') ON DELETE RESTRICT';
        $out[$name]='CREATE TABLE `'.$prefix.$name.'` ('.$definition.')'.$engine;
    }
    return $out;
}
function recurring_billing_constraints(string $name):array {
    return match($name){
        'recurring_billing_sources'=>['unique'=>[],'indexes'=>[],'relations'=>['id'=>['service_subscriptions','id']]],
        'recurring_billing_schedules'=>['unique'=>['source_generation'=>['subscription_id','generation']],'indexes'=>['client_id'=>['client_id'],'job_id'=>['job_id'],'created_by'=>['created_by']],'relations'=>['subscription_id'=>['recurring_billing_sources','id'],'client_id'=>['studio_clients','id'],'job_id'=>['studio_jobs','id'],'created_by'=>['users','id']]],
        'recurring_billing_events'=>['unique'=>['schedule_revision'=>['schedule_id','revision']],'indexes'=>['actor_staff_id'=>['actor_staff_id'],'actor_client_id'=>['actor_client_id']],'relations'=>['schedule_id'=>['recurring_billing_schedules','id'],'actor_staff_id'=>['users','id'],'actor_client_id'=>['studio_clients','id']]],
        'recurring_billing_invoices'=>['unique'=>['obligation'=>['schedule_id','period_index'],'invoice_id'=>['invoice_id']],'indexes'=>['staff_id'=>['staff_id']],'relations'=>['schedule_id'=>['recurring_billing_schedules','id'],'invoice_id'=>['studio_invoices','id'],'staff_id'=>['users','id']]],
        default=>throw new LogicException('Unknown recurring billing storage.'),
    };
}
