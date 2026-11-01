<?php
/** Fresh disposable loopback fixture only; never loads deployed config. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
$private=__DIR__.'/../test-runtime/private';if(!is_dir($private.'/media'))mkdir($private.'/media',0700,true);
$cfg=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('c',64),'db'=>['host'=>'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER'),'pass'=>getenv('CWP_TEST_PASSWORD'),'prefix'=>'ci_']];
$GLOBALS['cwp_config']=$cfg;file_put_contents(__DIR__.'/../test-runtime/config-fixture.php','<?php return '.var_export($cfg,true).';');
if(($argv[1]??'')==='config')exit;
foreach(schema_statements('ci_') as $sql)db()->exec($sql);foreach(default_settings() as $k=>$v)save_setting($k,$v);save_setting('email_mode','disabled');save_setting('staff_schema_version','1');save_setting('studio_schema_version','2');
query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Synthetic ticket owner','owner@example.invalid',password_hash('Synthetic owner passphrase 2026',PASSWORD_DEFAULT),str_repeat('a',32),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);
foreach(['alice','bob'] as $index=>$name)query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,active,created_at) VALUES (?,?,?,?,1,?)',[ucfirst($name).' Synthetic',$name.'@example.invalid',password_hash('Synthetic client passphrase 2026',PASSWORD_DEFAULT),str_repeat((string)($index+1),32),gmdate('Y-m-d H:i:s')]);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$_SESSION=['uid'=>1,'auth_version'=>str_repeat('a',32),'login_at'=>time(),'last_activity'=>time()];
support_migrate();
query('INSERT INTO '.table('studio_jobs').' (client_id,title,stage,summary,milestones,created_at,updated_at) VALUES (1,?,?,?,?,?,?)',['Preexisting unrelated project','design','Private to the preexisting client','Retain original progress',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
echo json_encode(['passed'=>true,'scope'=>'Synthetic loopback fixture with existing unrelated client project and support policy; tickets not prepared']).PHP_EOL;
