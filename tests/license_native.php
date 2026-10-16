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

    // --- storage readiness (fresh deploy marks license_schema_version) -------
    lic_ok('Storage not ready before version mark',!license_ready());
    save_setting('license_schema_version','3');
    lic_ok('Storage ready after version mark',license_ready());

    // --- key generation ----------------------------------------------------
    $k1=license_generate_key();$k2=license_generate_key();
    lic_ok('Key format is XXXX-XXXX-XXXX-XXXX hex',(bool)preg_match('/^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/D',$k1));
    lic_ok('Keys are unique',$k1!==$k2);

    // --- license creation ---------------------------------------------------
    $key=license_create(1,'WEBSITE-RESTAURANT');
    lic_ok('License persisted with customer and product',($r=license_get($key))&&(int)$r['customer_id']===1&&$r['product_id']==='WEBSITE-RESTAURANT'&&(int)$r['is_active']===1);
    lic_ok('Unknown key returns null',license_get('AAAA-BBBB-CCCC-DDDD')===null);
    lic_ok('License creation audited',(bool)one('SELECT id FROM '.table('audit')." WHERE action='license.create'"));

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
    lic_ok('Domain mutations audited',(bool)one('SELECT id FROM '.table('audit')." WHERE action='license.domain.replace'")
        &&(bool)one('SELECT id FROM '.table('audit')." WHERE action='license.domain.register'"));

    // --- deactivation --------------------------------------------------------
    license_deactivate_domain($licId,'test.example.com');
    $domains=license_domains($licId);
    lic_ok('Domain deactivation marks row inactive',(int)array_column($domains,'is_active','domain')['test.example.com']===0&&$domains[array_search('test.example.com',array_column($domains,'domain'))]['deactivated_at']!==null);

    license_deactivate($licId);
    lic_ok('License deactivation marks inactive',(int)license_get($key)['is_active']===0);

    license_reactivate($licId);
    lic_ok('License reactivation restores active',(int)license_get($key)['is_active']===1);
    lic_ok('Reactivation audited',(bool)one('SELECT id FROM '.table('audit')." WHERE action='license.reactivate'"));
    license_deactivate($licId);
    lic_ok('License deactivates again for bundle-guard checks',(int)license_get($key)['is_active']===0);

    // --- second license is independent ---------------------------------------
    $key2=license_create(2,'WEBSITE-SALON');
    lic_ok('Second license independent',(int)license_get($key2)['is_active']===1);

    // --- master keys (staff test keys — authorize any domain) -------------------
    $key3=license_create(2,'TEST-MASTER',0,true);
    $lic3=license_get($key3);
    lic_ok('Master flag persisted',(int)$lic3['is_master']===1);
    lic_ok('Master creation audited',(bool)one('SELECT id FROM '.table('audit')." WHERE action='license.create' AND detail LIKE '%master%'"));
    lic_ok('Master authorizes unregistered domain',license_domain_authorized($lic3,'anything.example.org'));
    lic_ok('Master authorizes second unregistered domain',license_domain_authorized($lic3,'another.test-site.com'));
    lic_ok('Regular license denies unregistered domain',!license_domain_authorized(license_get($key2),'ghost.example.net'));
    license_register_domain((int)license_get($key2)['id'],'real.example.net');
    lic_ok('Regular license authorizes registered domain',license_domain_authorized(license_get($key2),'real.example.net'));
    lic_ok('www variant authorized for registered apex',license_domain_authorized(license_get($key2),'www.real.example.net'));

    // --- product→industry mapping --------------------------------------------
    require_once CWP_CODE_ROOT.'/app/billing/catalog.php'; // pulls fulfill.php
    lic_ok('WEBSITE-RESTAURANT maps to restaurant',license_product_industry(['sku'=>'WEBSITE-RESTAURANT'])==='restaurant');
    lic_ok('WEBSITE-SALON maps to salon',license_product_industry(['sku'=>'WEBSITE-SALON'])==='salon');
    lic_ok('Case-insensitive SKU mapping',license_product_industry(['sku'=>'website-restaurant'])==='restaurant');
    lic_ok('Non-website SKU does not map',license_product_industry(['sku'=>'MAINTENANCE-PLAN'])===null);
    lic_ok('Unknown industry does not map',license_product_industry(['sku'=>'WEBSITE-UNKNOWN'])===null);
    lic_ok('Missing SKU does not map',license_product_industry([])===null);

    // --- fulfilment: one license per paid invoice -------------------------------
    // A second purchase of the same product must mint a NEW license — the
    // pre-invoice_id check keyed on customer+product wrongly returned the
    // existing license. UNIQUE(invoice_id) also collapses concurrent runs.
    save_setting('staff_schema_version','1');save_setting('studio_schema_version','2');save_setting('catalog_schema_version','1');
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[1,'License fixture job','Fixture','[]',$now,$now]);
    $jobId=(int)db()->lastInsertId();
    $mkInvoice=function(string $ref) use($jobId,$now): int {
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,status,instructions,created_at,paid_at) VALUES (?,?,?,?,?,?,?,?,?)',[$jobId,$ref,'Website',50000,'USD','paid','',$now,$now]);
        $inv=(int)db()->lastInsertId();
        query('INSERT INTO '.table('catalog_invoice_snapshots').' (invoice_id,job_id,client_id,actor_id,request_key,request_hash,product_id,revision,quantity,snapshot,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$inv,$jobId,1,1,hash('sha256',$ref),hash('sha256','hash'.$ref),1,1,1,json_encode(['sku'=>'WEBSITE-RESTAURANT','title'=>'Restaurant Website','total_cents'=>50000]),$now]);
        return $inv;
    };
    $inv1=$mkInvoice('LIC-FIXTURE-1');
    $f1=license_fulfill_invoice($inv1);
    lic_ok('Fulfilment creates license linked to invoice',(bool)$f1&&(int)$f1['invoice_id']===$inv1);
    lic_ok('Repeat fulfilment returns same license',license_fulfill_invoice($inv1)['license_key']===$f1['license_key']);
    $inv2=$mkInvoice('LIC-FIXTURE-2');
    $f2=license_fulfill_invoice($inv2);
    lic_ok('Second paid invoice mints a second license',(bool)$f2&&$f2['license_key']!==$f1['license_key']&&(int)$f2['invoice_id']===$inv2);
    lic_ok('Admin-created license has no invoice link',license_get($key)['invoice_id']===null);
    $dupRejected=false;
    try{license_create(1,'WEBSITE-RESTAURANT',0,false,$inv1);}catch(PDOException $e){$dupRejected=(int)($e->errorInfo[1]??0)===1062;}
    lic_ok('Duplicate invoice_id rejected by unique index',$dupRejected);

    // --- bundle builder guards ------------------------------------------------
    require_once CWP_CODE_ROOT.'/app/licenses/bundle.php';
    lic_deny('Bundle rejects unknown license key',fn()=>license_bundle('AAAA-BBBB-CCCC-DDDD','restaurant','/tmp','/tmp'));
    lic_deny('Bundle rejects deactivated license',fn()=>license_bundle($key,'restaurant','/tmp','/tmp'));
    lic_deny('Bundle rejects missing industry module',fn()=>license_bundle($key2,'nonexistent','/tmp','/tmp'));

    // --- license_bundles storage ----------------------------------------------
    $bundleFile=tempnam(sys_get_temp_dir(),'bf-');file_put_contents($bundleFile,'zip-bytes-fixture');
    license_bundle_store((int)license_get($key2)['id'],$bundleFile,'Test bundle');
    $bundles=all('SELECT * FROM '.table('license_bundles').' WHERE license_id=?',[(int)license_get($key2)['id']]);
    lic_ok('Bundle record stored',count($bundles)===1&&$bundles[0]['title']==='Test bundle');
    lic_ok('Bundle SHA-256 recorded for download integrity',$bundles[0]['sha256']===hash_file('sha256',$bundleFile));
    @unlink($bundleFile);
}catch(Throwable $e){fwrite(STDERR,'SETUP '.get_class($e).': '.$e->getMessage());exit(1);}
$failed=array_filter($checks,fn($c)=>!$c['passed']);
echo json_encode(['checks'=>$checks,'passed'=>count($checks)-count($failed),'failed'=>count($failed)],JSON_THROW_ON_ERROR);
exit($failed?1:0);
