<?php
/** Native licensing acceptance against disposable InnoDB fixtures only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='licenseci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];start_session();
$checks=[];$password='Synthetic license owner passphrase';
function lic_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function lic_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;lic_ok($name,true);return;}lic_ok($name,false);}
try{
    foreach(schema_statements('licenseci_') as $key=>$sql)db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['License client','license-client@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('b',32),gmdate('Y-m-d H:i:s')]);
    query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Second client','license-client2@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('c',32),gmdate('Y-m-d H:i:s')]);

    require_once CWP_CODE_ROOT.'/app/licenses/service.php';

    // --- key generation ----------------------------------------------------
    $k1=license_generate_key();$k2=license_generate_key();
    lic_ok('Key format is XXXX-XXXX-XXXX-XXXX hex',(bool)preg_match('/^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/D',$k1));
    lic_ok('Keys are unique',$k1!==$k2);

    // --- license creation ---------------------------------------------------
    $key=license_create(1,'WEBSITE-RESTAURANT');
    lic_ok('License persisted with customer and product',($r=license_get($key))&&(int)$r['customer_id']===1&&$r['product_id']==='WEBSITE-RESTAURANT'&&(int)$r['is_active']===1);
    lic_ok('Unknown key returns null',license_get('AAAA-BBBB-CCCC-DDDD')===null);

    // --- domain registration ------------------------------------------------
    $lic=license_get($key);$licId=(int)$lic['id'];
    license_register_domain($licId,'EXAMPLE.com');
    $domains=license_domains($licId);
    lic_ok('Domain lowercased on registration',count($domains)===1&&$domains[0]['domain']==='example.com'&&(int)$domains[0]['is_active']===1);
    lic_deny('Invalid domain rejected',fn()=>license_register_domain($licId,'not a domain'));
    lic_deny('Domain without TLD rejected',fn()=>license_register_domain($licId,'localhost'));
    lic_deny('Empty domain rejected',fn()=>license_register_domain($licId,'  '));

    // Second domain (public + test instances on same license)
    license_register_domain($licId,'test.example.com');
    lic_ok('Two active domains per license allowed',count(license_domains($licId))===2);

    // Third active domain exceeds the 2-instance cap
    lic_deny('Third active domain denied (2-instance cap)',fn()=>license_register_domain($licId,'third.example.com'));
    lic_ok('Cap leaves exactly two domain rows',count(license_domains($licId))===2);

    // www canonicalizes to apex — www.example.com is the same site, not a third instance
    license_register_domain($licId,'WWW.EXAMPLE.COM');
    $domains=license_domains($licId);
    lic_ok('www canonicalizes to apex row',count($domains)===2&&(int)array_column($domains,'is_active','domain')['example.com']===1);

    // Re-registering reactivates instead of duplicating
    license_deactivate_domain($licId,'example.com');
    license_register_domain($licId,'example.com');
    $domains=license_domains($licId);
    lic_ok('Re-register reactivates without duplicating',count($domains)===2&&(int)array_column($domains,'is_active','domain')['example.com']===1);

    // --- atomic domain replace (customer "change website") ---------------------
    license_replace_domain($licId,'test.example.com','NEW-SITE.example.com');
    $domains=license_domains($licId);$active=array_column($domains,'is_active','domain');
    lic_ok('Replace deactivates old domain',(int)$active['test.example.com']===0);
    lic_ok('Replace registers new domain canonicalized',(int)$active['new-site.example.com']===1);
    lic_ok('Replace keeps the other active domain',(int)$active['example.com']===1);
    lic_deny('Replace to same domain rejected',fn()=>license_replace_domain($licId,'example.com','example.com'));
    lic_deny('Replace of inactive domain rejected',fn()=>license_replace_domain($licId,'ghost.example.com','other.example.com'));
    $before=all('SELECT * FROM '.table('license_domains').' ORDER BY id');
    lic_deny('Invalid replacement domain is rejected',fn()=>license_replace_domain($licId,'example.com','not a domain'));
    lic_ok('Invalid replacement preserves all original rows',all('SELECT * FROM '.table('license_domains').' ORDER BY id')===$before&&!db()->inTransaction());
    db()->exec('CREATE TRIGGER licenseci_replace_fail BEFORE INSERT ON '.table('license_domains')." FOR EACH ROW BEGIN IF NEW.domain='rejected.example.com' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic replacement storage failure'; END IF; END");
    try {
        $failed=false;try{license_replace_domain($licId,'example.com','rejected.example.com');}catch(PDOException){$failed=true;}
        lic_ok('Failed replacement insert restores every original domain', $failed&&all('SELECT * FROM '.table('license_domains').' ORDER BY id')===$before&&!db()->inTransaction());
    } finally {db()->exec('DROP TRIGGER licenseci_replace_fail');}
    db()->beginTransaction();
    try {
        $refused=false;try{license_replace_domain($licId,'example.com','next.example.com');}catch(LogicException){$refused=true;}
        lic_ok('Replacement preserves an existing caller transaction', $refused&&db()->inTransaction()&&all('SELECT * FROM '.table('license_domains').' ORDER BY id')===$before);
    } finally {if(db()->inTransaction())db()->rollBack();}

    // --- deactivation --------------------------------------------------------
    license_deactivate_domain($licId,'test.example.com');
    $domains=license_domains($licId);
    lic_ok('Domain deactivation marks row inactive',(int)array_column($domains,'is_active','domain')['test.example.com']===0&&$domains[array_search('test.example.com',array_column($domains,'domain'))]['deactivated_at']!==null);

    license_deactivate($licId);
    lic_ok('License deactivation marks inactive',(int)license_get($key)['is_active']===0);

    // --- second license is independent ---------------------------------------
    $key2=license_create(2,'WEBSITE-SALON');
    lic_ok('Second license independent',(int)license_get($key2)['is_active']===1);

    // --- product→industry mapping --------------------------------------------
    require_once CWP_CODE_ROOT.'/app/billing/catalog.php'; // pulls fulfill.php
    lic_ok('WEBSITE-RESTAURANT maps to restaurant',license_product_industry(['sku'=>'WEBSITE-RESTAURANT'])==='restaurant');
    lic_ok('WEBSITE-SALON maps to salon',license_product_industry(['sku'=>'WEBSITE-SALON'])==='salon');
    lic_ok('Case-insensitive SKU mapping',license_product_industry(['sku'=>'website-restaurant'])==='restaurant');
    lic_ok('Non-website SKU does not map',license_product_industry(['sku'=>'MAINTENANCE-PLAN'])===null);
    lic_ok('Unknown industry does not map',license_product_industry(['sku'=>'WEBSITE-UNKNOWN'])===null);
    lic_ok('Missing SKU does not map',license_product_industry([])===null);

    // --- bundle builder guards ------------------------------------------------
    require_once CWP_CODE_ROOT.'/app/licenses/bundle.php';
    lic_deny('Bundle rejects unknown license key',fn()=>license_bundle('AAAA-BBBB-CCCC-DDDD','restaurant','/tmp','/tmp'));
    lic_deny('Bundle rejects deactivated license',fn()=>license_bundle($key,'restaurant','/tmp','/tmp'));
    lic_deny('Bundle rejects missing industry module',fn()=>license_bundle($key2,'nonexistent','/tmp','/tmp'));

    // --- license_bundles storage ----------------------------------------------
    license_bundle_store((int)license_get($key2)['id'],'/tmp/fake-bundle.zip','Test bundle');
    $bundles=all('SELECT * FROM '.table('license_bundles').' WHERE license_id=?',[(int)license_get($key2)['id']]);
    lic_ok('Bundle record stored',count($bundles)===1&&$bundles[0]['title']==='Test bundle');
}catch(Throwable $e){
    $report=['checks'=>$checks,'passed'=>false,'error'=>get_class($e).': '.$e->getMessage()];
    file_put_contents(__DIR__.'/../qa/license-native-results.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    fwrite(STDERR,'SETUP '.get_class($e).': '.$e->getMessage());exit(1);
}
$failed=array_filter($checks,fn($c)=>!$c['passed']);
$report=['checks'=>$checks,'passed'=>count($checks)-count($failed),'failed'=>count($failed)];
file_put_contents(__DIR__.'/../qa/license-native-results.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_THROW_ON_ERROR);
exit($failed?1:0);
