<?php
/** Release-only inspection of the actually installed package; never a deployed route. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||count($argv)!==3)exit(2);
$root=realpath($argv[1]);$base=realpath(__DIR__.'/../test-runtime');
if(!$root||!$base||!str_starts_with($root,$base.DIRECTORY_SEPARATOR))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';
$prefix=config()['db']['prefix'];
if(config()['db']['name']!=='cwp_test'||!preg_match('/^r126_[a-z0-9]{1,8}_$/D',$prefix))exit(2);
$mode=$argv[2];
if($mode==='identity'){
    echo json_encode(['version'=>CWP_VERSION,'users'=>all('SELECT id,email FROM '.table('users').' ORDER BY id'),'clients'=>all('SELECT id,name,email FROM '.table('studio_clients').' ORDER BY id'),
        'jobs'=>all('SELECT id,client_id,title FROM '.table('studio_jobs').' ORDER BY id'),
        'invoices'=>all('SELECT id,job_id,title,status FROM '.table('studio_invoices').' ORDER BY id'),
        'files'=>all('SELECT id,job_id,original_name FROM '.table('studio_files').' ORDER BY id'),
        'inventory_version'=>setting('provider_inventory_schema_version'),
        'catalog_imports'=>one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$prefix.'catalog_provider_imports'])?all('SELECT product_id,type_ref,type_name,source_hash,media_id,media_revision FROM '.table('catalog_provider_imports').' ORDER BY id'):[],
        'catalog_count'=>setting('provider_catalog_import_count'),
        'package_types_version'=>setting('provider_package_types_schema_version')],JSON_THROW_ON_ERROR);exit;
}
if($mode!=='snapshot')exit(2);
$tables=all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(table_name,?)=? ORDER BY table_name',[strlen($prefix),$prefix]);
$out=['version'=>CWP_VERSION,'tables'=>[],'private_files'=>[]];
foreach($tables as $entry){
    $physical=$entry['TABLE_NAME'];$name=substr($physical,strlen($prefix));
    if(!preg_match('/^[a-z][a-z0-9_]*$/D',$name))throw new RuntimeException('Unexpected fixture table identity.');
    // Authentication throttling is deliberately outside business retention. No table is deleted/reset.
    if($name==='rate_limits')continue;
    $keys=array_column(all("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=? AND constraint_name='PRIMARY' ORDER BY ORDINAL_POSITION",[$physical]),'COLUMN_NAME');
    if(!$keys)throw new RuntimeException('A retained table has no stable primary identity.');
    $rows=[];
    foreach(all('SELECT * FROM '.table($name)) as $row){
        $identity=[];foreach($keys as $key)$identity[$key]=$row[$key];
        $id=hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));$columns=[];
        foreach($row as $column=>$value){
            // Successful owner authentication updates only this documented non-authority timestamp.
            if($name==='users'&&$column==='last_login')continue;
            $columns[$column]=hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));
        }
        ksort($columns);if(isset($rows[$id]))throw new RuntimeException('Duplicate retained identity.');$rows[$id]=$columns;
    }
    ksort($rows);$out['tables'][$name]=['count'=>count($rows),'rows'=>(object)$rows];
}
$private=realpath((string)config()['storage']);
if(!$private||is_link($private))throw new RuntimeException('Expected isolated private root.');
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS)) as $file){
    $relative=str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($private)+1));
    // Updater journals and lock files are tested separately. Sessions are not private business files.
    if(preg_match('~^(?:structure-[^/]+|sessions)(?:/|$)~D',$relative))continue;
    if($file->isLink())throw new RuntimeException('Unexpected private fixture symlink.');
    if($file->isFile())$out['private_files'][$relative]=['sha256'=>hash_file('sha256',$file->getPathname()),'bytes'=>$file->getSize()];
}
ksort($out['private_files']);$out['private_files']=(object)$out['private_files'];echo json_encode($out,JSON_THROW_ON_ERROR);
