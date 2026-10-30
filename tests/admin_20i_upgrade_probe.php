<?php
/** Disposable upgrade fixture only. Never a route in the shipped application. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==3)exit(2);
$allowed=realpath(__DIR__.'/../test-runtime/admin-20i-populated');$root=realpath($argv[1]);$mode=$argv[2];
if(!$allowed||!$root||!str_starts_with($root,$allowed.DIRECTORY_SEPARATOR)||is_link($argv[1])||!in_array($mode,['seed','snapshot','revoke','capabilities'],true))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';$c=config();
if($c['db']['name']!=='cwp_test'||$c['db']['host']!=='127.0.0.1'||!preg_match('/^ug(?:125|126)[rs]_$/D',$c['db']['prefix']))exit(2);
if(!str_starts_with($c['site_url'],'http://127.0.0.1:8093'))exit(2);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8093','SCRIPT_NAME'=>'/admin/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root;start_session();require_once $root.'/app/staff/accounts.php';
$owner=one('SELECT * FROM '.table('users').' WHERE id=1');
if(!$owner||$owner['email']!=='organized@example.invalid')throw new RuntimeException('Disposable owner identity missing');
$_SESSION=['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$password='Synthetic organized upgrade passphrase';
if($mode==='seed'){
    if((int)query('SELECT COUNT(*) FROM '.table('studio_clients'))->fetchColumn()!==0)throw new RuntimeException('Refuse to seed a populated prefix');
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Retained synthetic client','upgrade-client@example.invalid',str_repeat('e',32),$now]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,stage,summary,milestones,created_at,updated_at) VALUES (1,?,?,?,?,?,?)',['Retained project','design','Preserved summary','Preserved milestones',$now,$now]);
    query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,instructions,created_at) VALUES (1,?,?,?,?,?)',['UPGRADE-RETAIN','Retained invoice',25500,'Original instructions',$now]);
    query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (1,?,?,?,?)',['admin',1,'Retained synthetic project message',$now]);
    require_once $root.'/app/billing/catalog.php';catalog_migrate($password);
    catalog_save(0,0,['sku'=>'UPGRADE-RETAIN','title'=>'Retained local service','family'=>'web-design','status'=>'draft','term'=>'once','currency'=>'USD','amount'=>'255.00','setup'=>'0.00','cost'=>'20.00','summary'=>'Original owner text','features'=>'Original feature','terms'=>'Original terms','sort_order'=>'7','provider_ref'=>'']);
    require_once $root.'/app/providers/settings.php';provider_settings_migrate($password);
    provider_settings_save('',0,'Synthetic retained provider',true,'SYNTHETIC_UPGRADE_PROVIDER_KEY',$password);
    require_once $root.'/app/providers/inventory.php';provider_inventory_migrate($password);
    require_once $root.'/app/providers/catalog_import.php';provider_catalog_import_prepare($password);
    if(is_file($root.'/app/licenses/service.php')){
        require_once $root.'/app/licenses/service.php';require_once $root.'/database/migrations/license_migrate.php';
        license_migrate($password);$key=license_create(1,'website_core',1);
        $license=license_get($key);license_register_domain((int)$license['id'],'retained.example.invalid',1);
    }
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Restricted synthetic administrator','restricted@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('b',32),$now]);
    $staff=(int)db()->lastInsertId();
    query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,\'administrator\',1,?)',[$staff,'["clients.manage"]']);
    $private=realpath($c['storage']);if(!$private||str_starts_with($private,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Unsafe private fixture');
    write_private_file($private.'/retained-upload.php',CWP_GUARD.'SYNTHETIC ORIGINAL CUSTOMER UPLOAD');
    save_setting('owner_upgrade_retained_setting','Original site content — unchanged');
    log_event('fixture.seed','Synthetic upgrade records only; no provider or merchant call');
    echo json_encode(['seeded'=>true,'version'=>CWP_VERSION],JSON_THROW_ON_ERROR);exit;
}
if($mode==='revoke'){
    query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[str_repeat('f',32)]);
    echo json_encode(['revoked'=>true],JSON_THROW_ON_ERROR);exit;
}
if($mode==='capabilities'){
    require_once $root.'/app/providers/catalog_import.php';
    $p=provider_settings_profile();$row=one('SELECT * FROM '.table('provider_profiles').' WHERE id=?',[$p['id']]);
    $secret=provider_settings_unseal($row['id'],$row['environment'],$row['credentials']);
    $result=['version'=>CWP_VERSION,'provider_secret_retained'=>hash_equals('SYNTHETIC_UPGRADE_PROVIDER_KEY',$secret),'catalog_ready'=>catalog_ready(),'import_ready'=>provider_catalog_import_ready(),'studio_ready'=>studio_ready()];
    unset($secret);
    if(is_file($root.'/app/providers/package_types.php')){require_once $root.'/app/providers/package_types.php';$result['package_types_ready']=provider_package_types_ready();}
    echo json_encode($result,JSON_THROW_ON_ERROR);exit;
}
$out=['version'=>CWP_VERSION,'tables'=>[],'private_files'=>[],'configuration_sha256'=>hash_file('sha256',$root.'/config.php'),'lock_sha256'=>hash_file('sha256',$root.'/.installed.php')];
$tables=all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,?)=? ORDER BY TABLE_NAME',[strlen($c['db']['prefix']),$c['db']['prefix']]);
foreach($tables as $entry){
    $name=substr($entry['TABLE_NAME'],strlen($c['db']['prefix']));
    if(!preg_match('/^[a-z][a-z0-9_]*$/D',$name))throw new RuntimeException('Unexpected table identity');
    if($name==='rate_limits')continue;
    $keys=array_column(all("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=? AND constraint_name='PRIMARY' ORDER BY ORDINAL_POSITION",[$entry['TABLE_NAME']]),'COLUMN_NAME');
    if(!$keys)throw new RuntimeException('Missing stable row identity: '.$name);
    $rows=[];
    foreach(all('SELECT * FROM '.table($name)) as $row){
        $identity=[];foreach($keys as $key)$identity[$key]=$row[$key];
        $columns=[];foreach($row as $column=>$value){if($name==='users'&&$column==='last_login')continue;$columns[$column]=hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
        ksort($columns);$rows[hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR))]=$columns;
    }
    ksort($rows);$out['tables'][$name]=['count'=>count($rows),'rows'=>(object)$rows];
}
$private=realpath($c['storage']);if(!$private||is_link($private))throw new RuntimeException('Missing private fixture');
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS)) as $file){
    $relative=str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($private)+1));
    if(preg_match('~^(?:structure-[^/]+|sessions)(?:/|$)~D',$relative))continue;
    if($file->isLink())throw new RuntimeException('Unexpected private symlink');
    if($file->isFile())$out['private_files'][$relative]=['sha256'=>hash_file('sha256',$file->getPathname()),'bytes'=>$file->getSize()];
}
ksort($out['private_files']);$out['private_files']=(object)$out['private_files'];echo json_encode($out,JSON_THROW_ON_ERROR);
