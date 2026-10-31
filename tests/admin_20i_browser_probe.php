<?php
/** CLI-only state/config for the fixed synthetic SQL fixture; not shipped. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
$op=$argv[1]??'';$fixture=$argv[2]??'provider-package-types';
if(!in_array($fixture,['provider-package-types','hosting-billing'],true))exit(2);
$root=dirname(__DIR__);$storage=$root.'/test-runtime/'.($fixture==='provider-package-types'?'provider-package-types-private':'hosting-catalog-billing-private');
if(!is_dir($storage)||is_link($storage))exit(2);
$c=['site_url'=>'http://127.0.0.1:8091/'.$fixture,'storage'=>$storage,'key'=>str_repeat($fixture==='provider-package-types'?'synthetic-package-types-':'synthetic-hosting-billing-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)getenv('CWP_TEST_PORT'),'name'=>'cwp_test','user'=>'cwp_test','pass'=>'local-ci-only','prefix'=>$fixture==='provider-package-types'?'ptici_':'hcbn_']];
if($op==='config'){echo '<?php return '.var_export($c,true).';';exit;}
if($fixture!=='provider-package-types')exit(2);
define('CWP_BOOT',true);require $root.'/site/includes/bootstrap.php';$GLOBALS['cwp_config']=$c;
if(query('SELECT DATABASE()')->fetchColumn()!=='cwp_test')exit(2);
if($op==='snapshot'){
 $rows=[];foreach(['catalog_products','catalog_revisions','catalog_provider_imports','media'] as $table)$rows[$table]=all('SELECT * FROM '.table($table).' ORDER BY id');
 $mapping=one('SELECT product_id FROM '.table('catalog_provider_imports').' WHERE type_ref=?',['7002']);
 $product=$mapping?one('SELECT sku,title,family,status,amount_cents,features,terms FROM '.table('catalog_products').' WHERE id=?',[$mapping['product_id']]):null;
 echo json_encode(['sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)),'mapping_count'=>(int)query('SELECT COUNT(*) FROM '.table('catalog_provider_imports').' WHERE type_ref=?',['7002'])->fetchColumn(),'product'=>$product],JSON_THROW_ON_ERROR);exit;
}
if($op==='revoke'){
 $row=one('SELECT email,auth_version FROM '.table('users').' WHERE id=1');
 if(!$row||$row['email']!=='types1@example.invalid'||$row['auth_version']!==str_repeat('a',32))exit(2);
 query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[str_repeat('e',32)]);echo "Synthetic owner session revoked\n";exit;
}
exit(2);
