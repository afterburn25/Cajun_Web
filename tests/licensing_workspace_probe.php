<?php
/** Loopback-only synthetic licensing fixture. Never loads installed production configuration. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
$private=dirname(__DIR__).'/test-runtime/licensing-private';
$config=['site_url'=>'http://127.0.0.1:8094/studio','key'=>str_repeat('c',64),'storage'=>$private,'db'=>['host'=>'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER'),'pass'=>getenv('CWP_TEST_PASSWORD'),'prefix'=>'licui_']];
$GLOBALS['cwp_config']=$config;$mode=$argv[1]??'';
if($mode==='config'){echo '<?php return '.var_export($config,true).';';exit;}
if($mode==='seed'){
 if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['licui_users']))throw new RuntimeException('Refuse populated fixture prefix');
 @mkdir($private,0700,true);
 foreach(schema_statements('licui_') as $sql)db()->exec($sql);
 foreach(default_settings() as $k=>$v)save_setting($k,$v);
 $now=gmdate('Y-m-d H:i:s');$pass='Synthetic licensing passphrase';
 foreach([[1,'Licensing owner','owner@licensing.invalid'],[2,'Restricted staff','staff@licensing.invalid']] as [$id,$name,$email])query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?,?)',[$id,$name,$email,password_hash($pass,PASSWORD_DEFAULT),str_repeat((string)$id,32),$now]);
 staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,'[\"website.manage\"]')");
 foreach([[1,'Licensing Client','client@licensing.invalid'],[2,'Unrelated Client','other@licensing.invalid']] as [$id,$name,$email])query('INSERT INTO '.table('studio_clients').' (id,name,email,password_hash,auth_version,active,created_at) VALUES (?,?,?,?,?,1,?)',[$id,$name,$email,password_hash($pass,PASSWORD_DEFAULT),str_repeat((string)$id,32),$now]);
 echo json_encode(['seeded'=>true]);exit;
}
if($mode==='expire-trial'){
 query('UPDATE '.table('software_entitlements')." SET expires_at='2001-01-01 00:00:00' WHERE license_type='trial'");echo '{}';exit;
}
if($mode==='revoke-owner'){query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[str_repeat('f',32)]);echo '{}';exit;}
if($mode!=='snapshot')exit(2);
$has=fn(string $n):bool=>(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['licui_'.$n]);
$out=['license_count'=>(int)query('SELECT COUNT(*) FROM '.table('website_licenses'))->fetchColumn(),'licenses'=>[],'assets'=>[],'software_mode'=>setting('software_gateway_mode','disabled'),'client_count'=>(int)query('SELECT COUNT(*) FROM '.table('studio_clients'))->fetchColumn()];
if($has('software_entitlements'))$out['licenses']=all('SELECT l.id,l.license_key,l.product_id,l.is_active,e.license_type FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id ORDER BY l.id');
if($has('software_assets'))$out['assets']=all('SELECT id,sha256,is_published FROM '.table('software_assets').' ORDER BY id');
echo json_encode($out,JSON_THROW_ON_ERROR);
