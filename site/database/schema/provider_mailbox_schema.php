<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function provider_mailbox_schema(string $prefix):array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$ascii=' CHARACTER SET ascii COLLATE ascii_bin';
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'provider_mailbox_policies'=>'CREATE TABLE '.$prefix.'provider_mailbox_policies (id '.$id.' PRIMARY KEY,link_id '.$id.' NOT NULL,revision INT UNSIGNED NOT NULL,owner_id INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,document LONGTEXT'.$ascii.' NOT NULL,sha256 CHAR(64)'.$ascii.' NOT NULL,UNIQUE KEY link_revision(link_id,revision),INDEX policy_owner(owner_id),FOREIGN KEY (owner_id) REFERENCES '.$prefix.'users (id) ON DELETE RESTRICT)'.$engine,
        'provider_mailbox_requests'=>'CREATE TABLE '.$prefix.'provider_mailbox_requests (id '.$id.' PRIMARY KEY,policy_id '.$id.' NOT NULL,profile_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,created_epoch BIGINT UNSIGNED NOT NULL,domain_name VARCHAR(253)'.$ascii.' NOT NULL,local_name VARCHAR(64)'.$ascii.' NOT NULL,document LONGTEXT'.$ascii.' NOT NULL,sha256 CHAR(64)'.$ascii.' NOT NULL,INDEX mailbox_target(profile_id,domain_name,local_name),INDEX mailbox_client(client_id,created_epoch),INDEX mailbox_policy(policy_id),FOREIGN KEY (id) REFERENCES '.$prefix.'provider_operation_grants (id) ON DELETE RESTRICT,FOREIGN KEY (policy_id) REFERENCES '.$prefix.'provider_mailbox_policies (id) ON DELETE RESTRICT,FOREIGN KEY (profile_id) REFERENCES '.$prefix.'provider_profiles (id) ON DELETE RESTRICT,FOREIGN KEY (client_id) REFERENCES '.$prefix.'studio_clients (id) ON DELETE RESTRICT)'.$engine,
    ];
}
