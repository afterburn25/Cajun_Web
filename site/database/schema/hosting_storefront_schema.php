<?php
/** Append-only storefront taxonomy and product classification history. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function hosting_storefront_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $t=static fn(string $name):string=>'`'.$prefix.$name.'`';
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';
    $slug='VARCHAR(63) CHARACTER SET ascii COLLATE ascii_bin';
    $hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    $doc='document_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,document_sha256 '.$hash.' NOT NULL';
    $end=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'hosting_storefront_categories'=>'CREATE TABLE '.$t('hosting_storefront_categories')." (id $id PRIMARY KEY,seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,slug $slug NOT NULL,revision INT UNSIGNED NOT NULL,created_by INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,$doc,UNIQUE category_seq(seq),UNIQUE slug_revision(slug,revision),INDEX slug_seq(slug,seq),INDEX created_by(created_by),FOREIGN KEY(created_by) REFERENCES ".$t('users').'(id))'.$end,
        'hosting_storefront_classifications'=>'CREATE TABLE '.$t('hosting_storefront_classifications')." (id $id PRIMARY KEY,seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,product_id INT UNSIGNED NOT NULL,product_revision INT UNSIGNED NOT NULL,state VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,category_slug $slug NULL,category_revision INT UNSIGNED NULL,source VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,created_by INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,$doc,UNIQUE classification_seq(seq),INDEX product_seq(product_id,seq),INDEX product_revision(product_id,product_revision),INDEX category_revision(category_slug,category_revision),INDEX created_by(created_by),FOREIGN KEY(product_id) REFERENCES ".$t('catalog_products').'(id),FOREIGN KEY(product_id,product_revision) REFERENCES '.$t('catalog_revisions').'(product_id,revision),FOREIGN KEY(category_slug,category_revision) REFERENCES '.$t('hosting_storefront_categories').'(slug,revision),FOREIGN KEY(created_by) REFERENCES '.$t('users').'(id))'.$end,
        'hosting_storefront_publications'=>'CREATE TABLE '.$t('hosting_storefront_publications')." (id $id PRIMARY KEY,seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,product_id INT UNSIGNED NOT NULL,category_slug $slug NOT NULL,category_revision INT UNSIGNED NOT NULL,classification_id $id NOT NULL,source_format TINYINT UNSIGNED NOT NULL,created_by INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,$doc,UNIQUE storefront_publication_seq(seq),INDEX product_seq(product_id,seq),INDEX category_seq(category_slug,seq),INDEX category_revision(category_slug,category_revision),INDEX classification_id(classification_id),INDEX created_by(created_by),FOREIGN KEY(id) REFERENCES ".$t('hosting_catalog_publications').'(id),FOREIGN KEY(product_id) REFERENCES '.$t('catalog_products').'(id),FOREIGN KEY(category_slug,category_revision) REFERENCES '.$t('hosting_storefront_categories').'(slug,revision),FOREIGN KEY(classification_id) REFERENCES '.$t('hosting_storefront_classifications').'(id),FOREIGN KEY(created_by) REFERENCES '.$t('users').'(id))'.$end,
    ];
}
function hosting_storefront_indexes(string $name): array {
    return ['PRIMARY'=>[0,['id']]] + match($name) {
        'hosting_storefront_categories'=>['category_seq'=>[0,['seq']],'slug_revision'=>[0,['slug','revision']],'slug_seq'=>[1,['slug','seq']],'created_by'=>[1,['created_by']]],
        'hosting_storefront_classifications'=>['classification_seq'=>[0,['seq']],'product_seq'=>[1,['product_id','seq']],'product_revision'=>[1,['product_id','product_revision']],'category_revision'=>[1,['category_slug','category_revision']],'created_by'=>[1,['created_by']]],
        'hosting_storefront_publications'=>['storefront_publication_seq'=>[0,['seq']],'product_seq'=>[1,['product_id','seq']],'category_seq'=>[1,['category_slug','seq']],'category_revision'=>[1,['category_slug','category_revision']],'classification_id'=>[1,['classification_id']],'created_by'=>[1,['created_by']]],
        default=>throw new LogicException('Unknown hosting storefront table.'),
    };
}
function hosting_storefront_relations(string $name): array {
    return match($name) {
        'hosting_storefront_categories'=>[[['created_by'],'users',['id']]],
        'hosting_storefront_classifications'=>[
            [['product_id'],'catalog_products',['id']],
            [['product_id','product_revision'],'catalog_revisions',['product_id','revision']],
            [['category_slug','category_revision'],'hosting_storefront_categories',['slug','revision']],
            [['created_by'],'users',['id']],
        ],
        'hosting_storefront_publications'=>[
            [['id'],'hosting_catalog_publications',['id']],
            [['product_id'],'catalog_products',['id']],
            [['category_slug','category_revision'],'hosting_storefront_categories',['slug','revision']],
            [['classification_id'],'hosting_storefront_classifications',['id']],
            [['created_by'],'users',['id']],
        ],
        default=>throw new LogicException('Unknown hosting storefront table.'),
    };
}

