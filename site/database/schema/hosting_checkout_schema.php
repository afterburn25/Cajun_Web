<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Only immutable primary-client reviews and completed local first-invoice results. */
function hosting_checkout_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $t=static fn(string $name):string=>chr(96).$prefix.$name.chr(96);
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';
    $hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    $doc='document_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,document_sha256 '.$hash.' NOT NULL';
    $end=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'hosting_checkout_reviews'=>'CREATE TABLE '.$t('hosting_checkout_reviews')." (id $id PRIMARY KEY,publication_id $id NOT NULL,publication_sha256 $hash NOT NULL,client_id INT UNSIGNED NOT NULL,project_id INT UNSIGNED NULL,created_epoch BIGINT UNSIGNED NOT NULL,expires_epoch BIGINT UNSIGNED NOT NULL,$doc,INDEX publication_id(publication_id),INDEX client_created(client_id,created_epoch,id),INDEX project_id(project_id),FOREIGN KEY(publication_id) REFERENCES ".$t('hosting_catalog_publications').'(id),FOREIGN KEY(client_id) REFERENCES '.$t('studio_clients').'(id),FOREIGN KEY(project_id) REFERENCES '.$t('studio_jobs').'(id))'.$end,
        'hosting_checkout_results'=>'CREATE TABLE '.$t('hosting_checkout_results')." (id $id PRIMARY KEY,publication_id $id NOT NULL,client_id INT UNSIGNED NOT NULL,project_id INT UNSIGNED NOT NULL,agreement_id $id NOT NULL,schedule_id $id NOT NULL,invoice_id INT UNSIGNED NOT NULL,issuance_id $id NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,input_sha256 $hash NOT NULL,consent_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,consent_sha256 $hash NOT NULL,$doc,UNIQUE agreement_id(agreement_id),UNIQUE schedule_id(schedule_id),UNIQUE invoice_id(invoice_id),UNIQUE issuance_id(issuance_id),INDEX publication_id(publication_id),INDEX client_created(client_id,created_epoch,id),INDEX project_id(project_id),FOREIGN KEY(id) REFERENCES ".$t('hosting_checkout_reviews').'(id),FOREIGN KEY(publication_id) REFERENCES '.$t('hosting_catalog_publications').'(id),FOREIGN KEY(client_id) REFERENCES '.$t('studio_clients').'(id),FOREIGN KEY(project_id) REFERENCES '.$t('studio_jobs').'(id),FOREIGN KEY(agreement_id) REFERENCES '.$t('service_subscriptions').'(id),FOREIGN KEY(schedule_id) REFERENCES '.$t('recurring_billing_schedules').'(id),FOREIGN KEY(invoice_id) REFERENCES '.$t('studio_invoices').'(id),FOREIGN KEY(issuance_id) REFERENCES '.$t('recurring_billing_invoices').'(id))'.$end,
    ];
}
function hosting_checkout_indexes(string $name): array {
    $common=['publication_id'=>[1,['publication_id']],'client_created'=>[1,['client_id','created_epoch','id']],'project_id'=>[1,['project_id']]];
    return ['PRIMARY'=>[0,['id']]]+match($name) {
        'hosting_checkout_reviews'=>$common,
        'hosting_checkout_results'=>['agreement_id'=>[0,['agreement_id']],'schedule_id'=>[0,['schedule_id']],'invoice_id'=>[0,['invoice_id']],'issuance_id'=>[0,['issuance_id']]]+$common,
        default=>throw new LogicException('Unknown checkout table.'),
    };
}
function hosting_checkout_relations(string $name): array {
    $common=[[['publication_id'],'hosting_catalog_publications',['id']],[['client_id'],'studio_clients',['id']],[['project_id'],'studio_jobs',['id']]];
    return match($name) {
        'hosting_checkout_reviews'=>$common,
        'hosting_checkout_results'=>array_merge([[['id'],'hosting_checkout_reviews',['id']]],$common,[[['agreement_id'],'service_subscriptions',['id']],[['schedule_id'],'recurring_billing_schedules',['id']],[['invoice_id'],'studio_invoices',['id']],[['issuance_id'],'recurring_billing_invoices',['id']]]),
        default=>throw new LogicException('Unknown checkout table.'),
    };
}
