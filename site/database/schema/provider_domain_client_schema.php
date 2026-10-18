<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

/** Additive client attribution and owner grants; existing staff search history stays intact. */
function provider_domain_client_schema(string $prefix):array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$ascii=' CHARACTER SET ascii COLLATE ascii_bin';
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'provider_domain_client_policies'=>'CREATE TABLE '.$prefix.'provider_domain_client_policies (id '.$id.' PRIMARY KEY,revision INT UNSIGNED NOT NULL,owner_id INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,document LONGTEXT'.$ascii.' NOT NULL,sha256 CHAR(64)'.$ascii.' NOT NULL,UNIQUE KEY policy_revision(revision),INDEX policy_owner(owner_id),FOREIGN KEY (owner_id) REFERENCES '.$prefix.'users (id) ON DELETE RESTRICT)'.$engine,
        'provider_domain_client_searches'=>'CREATE TABLE '.$prefix.'provider_domain_client_searches (id '.$id.' PRIMARY KEY,profile_id '.$id.' NOT NULL,profile_revision INT UNSIGNED NOT NULL,config_revision INT UNSIGNED NOT NULL,policy_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,client_auth_version CHAR(32)'.$ascii.' NOT NULL,query_name VARCHAR(253)'.$ascii.' NOT NULL,tlds TEXT'.$ascii.' NOT NULL,started_at DATETIME NOT NULL,started_epoch BIGINT UNSIGNED NOT NULL,INDEX profile_searches(profile_id,started_epoch),INDEX client_searches(client_id,started_epoch),INDEX search_policy(policy_id),FOREIGN KEY (profile_id) REFERENCES '.$prefix.'provider_profiles (id) ON DELETE RESTRICT,FOREIGN KEY (client_id) REFERENCES '.$prefix.'studio_clients (id) ON DELETE RESTRICT,FOREIGN KEY (policy_id) REFERENCES '.$prefix.'provider_domain_client_policies (id) ON DELETE RESTRICT)'.$engine,
        'provider_domain_client_results'=>'CREATE TABLE '.$prefix.'provider_domain_client_results (id '.$id.' PRIMARY KEY,state VARCHAR(16)'.$ascii.' NOT NULL,reason VARCHAR(40)'.$ascii.' NOT NULL,result LONGTEXT'.$ascii.' NOT NULL,key_fingerprint CHAR(64)'.$ascii.' NOT NULL,finished_at DATETIME NOT NULL,finished_epoch BIGINT UNSIGNED NOT NULL,elapsed_ms INT UNSIGNED NOT NULL,FOREIGN KEY (id) REFERENCES '.$prefix.'provider_domain_client_searches (id) ON DELETE RESTRICT)'.$engine,
    ];
}
