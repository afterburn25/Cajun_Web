<?php
/** Disposable loopback database and private storage only. Never deploy this file. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
$private=__DIR__.'/../test-runtime/private';@mkdir($private.'/media',0700,true);
$cfg=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-test-key-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER'),'pass'=>getenv('CWP_TEST_PASSWORD'),'prefix'=>'ci_']];
$GLOBALS['cwp_config']=$cfg;file_put_contents(__DIR__.'/../test-runtime/config-fixture.php','<?php return '.var_export($cfg,true).';');
if(($argv[1]??'')==='config')exit;
foreach(schema_statements('ci_') as $sql)db()->exec($sql);
foreach(default_settings() as $k=>$v)save_setting($k,$v);
save_setting('email_mode','disabled');
foreach([[1,'owner@example.invalid','Synthetic owner passphrase 2026','owner',[]],[2,'website-staff@example.invalid','Synthetic staff browser passphrase','administrator',['website.manage']]] as [$id,$email,$password,$role,$permissions]){
 query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?,?)',[$id,'Synthetic backup user '.$id,$email,password_hash($password,PASSWORD_DEFAULT),str_repeat((string)$id,32),gmdate('Y-m-d H:i:s')]);
 query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode($permissions)]);
}
save_setting('staff_schema_version','1');
echo json_encode(['passed'=>true,'scope'=>'Synthetic loopback fixture seed only']).PHP_EOL;
