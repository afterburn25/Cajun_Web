<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Additive immutable commercial records; generic catalog publication rules are unchanged. */
function hosting_catalog_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $t=static fn(string $name):string=>chr(96).$prefix.$name.chr(96);
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';
    $hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    $doc='document_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,document_sha256 '.$hash.' NOT NULL';
    $end=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'hosting_catalog_publications'=>'CREATE TABLE '.$t('hosting_catalog_publications')." (id $id PRIMARY KEY,seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,product_id INT UNSIGNED NOT NULL,product_revision INT UNSIGNED NOT NULL,plan_id $id NOT NULL,mapping_id $id NOT NULL,media_id $id NULL,created_by INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,$doc,UNIQUE publication_seq(seq),INDEX product_seq(product_id,seq),INDEX product_revision(product_id,product_revision),INDEX plan_id(plan_id),INDEX mapping_id(mapping_id),INDEX media_id(media_id),INDEX created_by(created_by),FOREIGN KEY(product_id) REFERENCES ".$t('catalog_products').'(id),FOREIGN KEY(product_id,product_revision) REFERENCES '.$t('catalog_revisions').'(product_id,revision),FOREIGN KEY(plan_id) REFERENCES '.$t('service_plans').'(id),FOREIGN KEY(mapping_id) REFERENCES '.$t('provider_plan_catalog').'(id),FOREIGN KEY(created_by) REFERENCES '.$t('users').'(id))'.$end,
        'hosting_catalog_withdrawals'=>'CREATE TABLE '.$t('hosting_catalog_withdrawals')." (id $id PRIMARY KEY,publication_id $id NOT NULL,actor_id INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,reason VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,$doc,UNIQUE publication_id(publication_id),INDEX actor_id(actor_id),FOREIGN KEY(publication_id) REFERENCES ".$t('hosting_catalog_publications').'(id),FOREIGN KEY(actor_id) REFERENCES '.$t('users').'(id))'.$end,
        'hosting_catalog_requests'=>'CREATE TABLE '.$t('hosting_catalog_requests')." (id $id PRIMARY KEY,publication_id $id NOT NULL,client_id INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,$doc,INDEX publication_id(publication_id),INDEX client_created(client_id,created_epoch,id),FOREIGN KEY(publication_id) REFERENCES ".$t('hosting_catalog_publications').'(id),FOREIGN KEY(client_id) REFERENCES '.$t('studio_clients').'(id))'.$end,
        'hosting_catalog_decisions'=>'CREATE TABLE '.$t('hosting_catalog_decisions')." (id $id PRIMARY KEY,request_id $id NOT NULL,kind VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,agreement_id $id NULL,job_id INT UNSIGNED NULL,actor_staff_id INT UNSIGNED NULL,actor_client_id INT UNSIGNED NULL,created_epoch BIGINT UNSIGNED NOT NULL,$doc,UNIQUE request_id(request_id),UNIQUE agreement_id(agreement_id),INDEX job_id(job_id),INDEX actor_staff_id(actor_staff_id),INDEX actor_client_id(actor_client_id),FOREIGN KEY(request_id) REFERENCES ".$t('hosting_catalog_requests').'(id),FOREIGN KEY(agreement_id) REFERENCES '.$t('service_subscriptions').'(id),FOREIGN KEY(job_id) REFERENCES '.$t('studio_jobs').'(id),FOREIGN KEY(actor_staff_id) REFERENCES '.$t('users').'(id),FOREIGN KEY(actor_client_id) REFERENCES '.$t('studio_clients').'(id))'.$end,
    ];
}

function hosting_catalog_indexes(string $name): array {
    return ['PRIMARY'=>[0,['id']]] + match($name) {
        'hosting_catalog_publications'=>['publication_seq'=>[0,['seq']],'product_seq'=>[1,['product_id','seq']],'product_revision'=>[1,['product_id','product_revision']],'plan_id'=>[1,['plan_id']],'mapping_id'=>[1,['mapping_id']],'media_id'=>[1,['media_id']],'created_by'=>[1,['created_by']]],
        'hosting_catalog_withdrawals'=>['publication_id'=>[0,['publication_id']],'actor_id'=>[1,['actor_id']]],
        'hosting_catalog_requests'=>['publication_id'=>[1,['publication_id']],'client_created'=>[1,['client_id','created_epoch','id']]],
        'hosting_catalog_decisions'=>['request_id'=>[0,['request_id']],'agreement_id'=>[0,['agreement_id']],'job_id'=>[1,['job_id']],'actor_staff_id'=>[1,['actor_staff_id']],'actor_client_id'=>[1,['actor_client_id']]],
        default=>throw new LogicException('Unknown hosting catalog table.'),
    };
}

/** Composite foreign keys remain one relationship; column membership alone is insufficient. */
function hosting_catalog_relations(string $name): array {
    return match($name) {
        'hosting_catalog_publications'=>[
            [['product_id'],'catalog_products',['id']],
            [['product_id','product_revision'],'catalog_revisions',['product_id','revision']],
            [['plan_id'],'service_plans',['id']],[['mapping_id'],'provider_plan_catalog',['id']],[['created_by'],'users',['id']],
        ],
        'hosting_catalog_withdrawals'=>[[['publication_id'],'hosting_catalog_publications',['id']],[['actor_id'],'users',['id']]],
        'hosting_catalog_requests'=>[[['publication_id'],'hosting_catalog_publications',['id']],[['client_id'],'studio_clients',['id']]],
        'hosting_catalog_decisions'=>[[['request_id'],'hosting_catalog_requests',['id']],[['agreement_id'],'service_subscriptions',['id']],[['job_id'],'studio_jobs',['id']],[['actor_staff_id'],'users',['id']],[['actor_client_id'],'studio_clients',['id']]],
        default=>throw new LogicException('Unknown hosting catalog table.'),
    };
}
