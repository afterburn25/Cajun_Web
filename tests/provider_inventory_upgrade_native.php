<?php
/** Real additive migration and PHP rendering against isolated MySQL/MariaDB; no credential or network use. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/inventory.php';
require __DIR__.'/../site/includes/layout/admin.php';
$prefix=getenv('CWP_TEST_PREFIX')?:'pciu_';
if(!preg_match('/^pciu_[a-z0-9_]{0,12}$/D',$prefix))exit(2);
$private=__DIR__.'/../test-runtime/provider-upgrade-'.$prefix;@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-upgrade-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>$prefix]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic upgrade owner passphrase';$profile=str_repeat('a',32);$refresh=str_repeat('b',32);$link=str_repeat('c',32);$fp=str_repeat('d',64);
function pu_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function pu_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;pu_ok($name,true);return;}pu_ok($name,false);}
function pu_version():string{return (string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_inventory_schema_version'])['value']??'');}
function pu_render():string {ob_start();try{require CWP_CODE_ROOT.'/admin/views/providers.php';return ob_get_contents();}finally{ob_end_clean();}}
function pu_snapshot():array {
    $out=[];foreach(['provider_profiles','provider_revisions','provider_refreshes','provider_refresh_results','provider_observation_packages','provider_package_links','provider_package_link_events'] as $name)$out[$name]=all('SELECT * FROM '.table($name).' ORDER BY id');return $out;
}
function pu_old_fields(array $rows,array $before):array {foreach($rows as $name=>&$table)foreach($table as $i=>&$row)$row=array_intersect_key($row,$before[$name][$i]);unset($table,$row);return $rows;}
function pu_audit_failure(int $version,callable $fn):void {
    $trigger=config()['db']['prefix'].'audit_fail';db()->exec('CREATE TRIGGER `'.$trigger.'` BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.inventory_prepared' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic migration audit failure'; END IF; END");
    try{$fn();pu_ok('Migration audit failure is surfaced from v'.$version,false);}catch(PDOException){pu_ok('Migration audit failure is surfaced from v'.$version,true);}finally{db()->exec('DROP TRIGGER `'.$trigger.'`');}
}
try {
    pu_ok('Isolated prefix is unused',(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(table_name,?)=?',[strlen($prefix),$prefix])->fetchColumn()===0);
    foreach(schema_statements($prefix) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('studio_schema_version','2');
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Upgrade owner','upgrade@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('e',32),'2026-01-01 00:00:00']);
    staff_seed_owner(1);$_SESSION=['uid'=>1,'auth_version'=>str_repeat('e',32),'login_at'=>time(),'last_activity'=>time()];
    foreach(['First','Second'] as $name)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',[$name,strtolower($name).'@example.invalid',bin2hex(random_bytes(16)),'2026-01-01 00:00:00']);
    provider_settings_migrate($password);
    // Synthetic disabled profile is seeded without credentials: migration must not require a live key.
    query('INSERT INTO '.table('provider_profiles').' (id,provider_code,environment,revision,label,credentials,enabled,updated_by,updated_at) VALUES (?,?,?,?,?,?,0,1,?)',[$profile,'twenty-i','live',7,'Synthetic upgrade profile','','2026-01-01 00:00:00']);
    $schema=provider_inventory_schema($prefix);
    // Historical v1 lacks both context-binding columns and both v3 domain tables.
    foreach($schema as $name=>$sql) {
        if(str_starts_with($name,'provider_domain_')||$name==='provider_observation_cloud')continue;
        $sql=str_replace(',operation VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL','',$sql);
        $sql=str_replace([',key_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',',profile_revision INT UNSIGNED NOT NULL,UNIQUE KEY link_target'],['',',UNIQUE KEY link_target'],$sql);db()->exec($sql);
    }
    save_setting('provider_inventory_schema_version','1');
    query('INSERT INTO '.table('provider_refreshes').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch) VALUES (?,?,7,1,?,?)',[$refresh,$profile,'2026-01-01 00:00:00',1767225600]);
    query('INSERT INTO '.table('provider_refresh_results').' (id,state,reason,scope,coverage,package_count,skipped,skip_reasons,finished_at,elapsed_ms) VALUES (?,?,?,?,?,2,0,?,?,1)',[$refresh,'complete','','twenty-i|'.$profile.'|packages.list','unknown','[]','2026-01-01 00:00:01']);
    foreach([777,888] as $i)query('INSERT INTO '.table('provider_observation_packages').' (id,refresh_id,package_id,name,names,enabled,created_at,created_epoch,type_ref,package_type_name,stack_users,package_labels,has_spec,unmapped_keys,unmapped_count) VALUES (?,?,?,?,?,1,?,1,9,?,?,?,0,?,0)',[str_pad((string)$i,32,'0',STR_PAD_LEFT),$refresh,$i,'private-'.$i.'.example',json_encode(['private-'.$i.'.example']),'2026-01-01T00:00:00Z','Retained plan','[]','[]','[]']);
    query('INSERT INTO '.table('provider_package_links').' (id,profile_id,package_id,client_id,linked_by,linked_at) VALUES (?,?,777,1,1,?)',[$link,$profile,'2026-01-01 00:00:00']);
    query('INSERT INTO '.table('provider_package_link_events').' (profile_id,package_id,client_id,event,actor_id,recorded_at) VALUES (?,777,1,?,1,?)',[$profile,'link','2026-01-01 00:00:00']);
    $before=pu_snapshot();
    pu_ok('Populated v1 is gated before domain queries',!provider_inventory_ready()&&provider_inventory_client_services(1)===[]&&provider_inventory_client_domains(1)===[]);
    pu_ok('Populated v1 owner view retains preparation without querying missing domain tables',str_contains(pu_render(),'Prepare inventory storage'));
    pu_deny('Populated v1 preparation requires current owner password',fn()=>provider_inventory_migrate('wrong'));
    pu_ok('Denied v1 preparation preserves schema version and records',pu_version()==='1'&&pu_snapshot()===$before);
    pu_audit_failure(1,fn()=>provider_inventory_migrate($password));
    pu_ok('Interrupted v1 preparation never stamps ready or rewrites retained facts',pu_version()==='1'&&!provider_inventory_ready()&&pu_old_fields(pu_snapshot(),$before)===$before);
    provider_inventory_migrate($password);
    pu_ok('Version-1 storage upgrades in place',pu_version()==='4'&&provider_inventory_ready()&&pu_old_fields(pu_snapshot(),$before)===$before);
    $services=provider_inventory_client_services(1);
    pu_ok('Pre-upgrade mappings load unverified and stay suppressed',count($services)===1&&$services[0]['context_changed']&&!$services[0]['present']&&$services[0]['package']===null);
    pu_ok('Legacy link does not inherit a credential fingerprint or revision',$services[0]['link']['key_fingerprint']===''&&(int)$services[0]['link']['profile_revision']===0);
    pu_ok('Legacy link never leaks to another client',provider_inventory_client_services(2)===[]);
    $upgraded=pu_snapshot();provider_inventory_migrate($password);
    pu_ok('Repeated v1 preparation preserves original rows revisions fingerprints and history',pu_snapshot()===$upgraded&&pu_version()==='4');
    pu_ok('Prepared v1 owner view renders without a setup form',!str_contains(pu_render(),'Prepare inventory storage'));
    // Build a genuinely populated v2 database, with verified original bindings and no domain tables.
    db()->exec('DROP TABLE '.table('provider_observation_cloud'));db()->exec('ALTER TABLE '.table('provider_refreshes').' DROP COLUMN operation');
    foreach(['provider_domain_link_events','provider_domain_links'] as $name){pu_ok('Only empty isolated domain table removed for v2 fixture '.$name,(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()===0);db()->exec('DROP TABLE '.table($name));}
    query('UPDATE '.table('provider_refresh_results').' SET key_fingerprint=?',[$fp]);query('UPDATE '.table('provider_package_links').' SET key_fingerprint=?,profile_revision=7',[$fp]);
    query('INSERT INTO '.table('provider_package_links').' (id,profile_id,package_id,client_id,linked_by,linked_at,key_fingerprint,profile_revision) VALUES (?,?,888,2,1,?,?,7)',[str_repeat('f',32),$profile,'2026-01-01 00:00:00',$fp]);
    save_setting('provider_inventory_schema_version','2');$v2=pu_snapshot();
    pu_ok('Populated v2 is gated before missing domain table reads',!provider_inventory_ready()&&provider_inventory_client_services(1)===[]&&provider_inventory_client_domains(2)===[]);
    pu_ok('Populated v2 owner can reach explicit preparation',str_contains(pu_render(),'Prepare inventory storage')&&pu_old_fields(pu_snapshot(),$v2)===$v2&&pu_version()==='2');
    // The reported generated-column regression: same type/null/default, unexpected Extra behavior.
    db()->exec('ALTER TABLE '.table('provider_observation_packages').' DROP COLUMN package_type_name, ADD COLUMN package_type_name VARCHAR(120) AS (LEFT(name,120)) VIRTUAL AFTER type_ref');
    pu_deny('Unexpected VIRTUAL GENERATED package type is rejected before ordinary insert',fn()=>provider_inventory_validate_schema('provider_observation_packages',$schema['provider_observation_packages']));
    pu_deny('Incompatible generated schema cannot be marked prepared',fn()=>provider_inventory_migrate($password));
    pu_ok('Incompatible preparation retains v2 gate',pu_version()==='2'&&!provider_inventory_ready());
    db()->exec('ALTER TABLE '.table('provider_observation_packages').' DROP COLUMN package_type_name, ADD COLUMN package_type_name VARCHAR(120) NULL AFTER type_ref');
    foreach($v2['provider_observation_packages'] as $row)query('UPDATE '.table('provider_observation_packages').' SET package_type_name=? WHERE id=?',[$row['package_type_name'],$row['id']]);
    pu_ok('Fixture repair restores every original v2 fact',pu_old_fields(pu_snapshot(),$v2)===$v2);
    pu_audit_failure(2,fn()=>provider_inventory_migrate($password));
    pu_ok('Interrupted v2 upgrade retains old stamp and all original records',pu_version()==='2'&&pu_old_fields(pu_snapshot(),$v2)===$v2&&!provider_inventory_ready());
    provider_inventory_migrate($password);
    pu_ok('Populated v2 upgrades with identical bindings revisions and history',pu_version()==='4'&&pu_old_fields(pu_snapshot(),$v2)===$v2);
    foreach([1=>777,2=>888] as $client=>$package){$services=provider_inventory_client_services($client);pu_ok('Upgraded v2 client '.$client.' sees only its original approved service',count($services)===1&&$services[0]['present']&&(int)$services[0]['package']['package_id']===$package&&$services[0]['link']['key_fingerprint']===$fp);}
    provider_inventory_migrate($password);pu_ok('Repeated v2 preparation preserves every record and domain history',pu_old_fields(pu_snapshot(),$v2)===$v2&&all('SELECT * FROM '.table('provider_domain_link_events'))===[]);
    pu_ok('V2 owner page renders current inventory after migration',!str_contains(pu_render(),'Prepare inventory storage')&&str_contains(pu_render(),'private-777.example'));
    $domainLink=str_repeat('9',32);
    query('INSERT INTO '.table('provider_domain_links').' (id,profile_id,package_id,domain,client_id,linked_by,linked_at,key_fingerprint,profile_revision) VALUES (?,?,777,?,2,1,?,?,7)',[$domainLink,$profile,'private-777.example','2026-01-01 00:00:00',$fp]);
    $domains=provider_inventory_client_domains(2);
    pu_ok('Domain projection uses the approved pair and excludes sibling names',count($domains)===1&&$domains[0]['present']&&array_keys($domains[0]['package'])===['package_id','enabled']&&provider_inventory_client_domains(1)===[]);
    $moved=str_repeat('8',32);
    query('INSERT INTO '.table('provider_refreshes').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch) VALUES (?,?,7,1,?,?)',[$moved,$profile,'2026-01-01 00:02:00',1767225720]);
    query('INSERT INTO '.table('provider_refresh_results').' (id,state,reason,scope,coverage,package_count,skipped,skip_reasons,finished_at,elapsed_ms,key_fingerprint) VALUES (?,?,?,?,?,1,0,?,?,1,?)',[$moved,'complete','','twenty-i|'.$profile.'|packages.list','unknown','[]','2026-01-01 00:02:01',$fp]);
    query('INSERT INTO '.table('provider_observation_packages').' (id,refresh_id,package_id,name,names,enabled,created_at,created_epoch,type_ref,package_type_name,stack_users,package_labels,has_spec,unmapped_keys,unmapped_count) VALUES (?,?,301,?,?,1,?,1,9,?,?,?,0,?,0)',[str_repeat('7',32),$moved,'another-client-private.example','["private-777.example"]','2026-01-01T00:00:00Z','Retained plan','[]','[]','[]']);
    $html=pu_render();$domainSection=substr($html,strpos($html,'<h3>Observed domains</h3>'));$domainSection=explode('<h3>Link history</h3>',$domainSection)[0];
    pu_ok('Moved-domain owner view identifies original and observed packages',str_contains($domainSection,'package #301')&&str_contains($domainSection,'Approved package #777')&&str_contains($domainSection,'different package'));
    pu_ok('Moved-domain owner view requires unlink and new approval',str_contains($domainSection,'Unlink the old binding')&&str_contains($domainSection,'value="provider_inventory_domain_unlink"')&&!str_contains($domainSection,'value="provider_inventory_domain_relink"'));
    $domains=provider_inventory_client_domains(2);
    pu_ok('Moved domain discloses neither new package nor another customer primary name',count($domains)===1&&!$domains[0]['present']&&$domains[0]['package']===null&&provider_inventory_client_domains(1)===[]);
    pu_deny('Moved-domain reapproval cannot change the approved package',fn()=>provider_inventory_domain_relink($domainLink,7,$password));
    pu_ok('Rejected reapproval leaves binding and event journal untouched',(int)one('SELECT package_id FROM '.table('provider_domain_links').' WHERE id=?',[$domainLink])['package_id']===777&&all('SELECT * FROM '.table('provider_domain_link_events'))===[]);
    $contextLink=['profile_revision'=>7,'key_fingerprint'=>$fp];$contextProfile=['revision'=>7];
    pu_ok('Approval presentation requires matching observation revision and credential context',provider_inventory_link_context_matches($contextLink,$contextProfile,['profile_revision'=>7,'key_fingerprint'=>$fp])&&!provider_inventory_link_context_matches($contextLink,$contextProfile,['profile_revision'=>6,'key_fingerprint'=>$fp])&&!provider_inventory_link_context_matches($contextLink,$contextProfile,['profile_revision'=>7,'key_fingerprint'=>str_repeat('e',64)])&&!provider_inventory_link_context_matches($contextLink,$contextProfile,null));
    // Genuine populated v3 schema: domain links/history exist, no cloud table or operation column.
    db()->exec('DROP TABLE '.table('provider_observation_cloud'));db()->exec('ALTER TABLE '.table('provider_refreshes').' DROP COLUMN operation');save_setting('provider_inventory_schema_version','3');
    $v3=pu_snapshot();$domainBefore=all('SELECT * FROM '.table('provider_domain_links'));$domainEventsBefore=all('SELECT * FROM '.table('provider_domain_link_events'));
    pu_ok('Populated v3 routes retain explicit owner upgrade and safe client state',!provider_inventory_ready()&&str_contains(pu_render(),'Prepare inventory storage')&&provider_inventory_client_services(1)===[]&&provider_inventory_client_domains(2)===[]&&pu_snapshot()===$v3);
    pu_deny('Populated v3 migration rejects wrong current password',fn()=>provider_inventory_migrate('wrong'));
    pu_audit_failure(3,fn()=>provider_inventory_migrate($password));
    pu_ok('Interrupted v3 upgrade keeps original stamp and facts',pu_version()==='3'&&!provider_inventory_ready()&&pu_old_fields(pu_snapshot(),$v3)===$v3);
    provider_inventory_migrate($password);
    pu_ok('Populated v3 upgrade preserves original package and domain histories',pu_version()==='4'&&provider_inventory_ready()&&pu_old_fields(pu_snapshot(),$v3)===$v3&&all('SELECT * FROM '.table('provider_domain_links'))===$domainBefore&&all('SELECT * FROM '.table('provider_domain_link_events'))===$domainEventsBefore);
    pu_ok('Historical intents are package reads without rewriting rows',(int)one('SELECT COUNT(*) AS n FROM '.table('provider_refreshes').' WHERE operation IS NOT NULL')['n']===0&&provider_inventory_observation($profile)!==null&&provider_inventory_observation($profile,\CajunWebPro\Foundation\TwentyIInventory\InventoryRead::CLOUD_SERVERS_LIST)===null);
    $v4=pu_snapshot();provider_inventory_migrate($password);pu_ok('Repeated v4 preparation changes no retained fact',pu_snapshot()===$v4);
    db()->exec('RENAME TABLE '.table('provider_observation_cloud').' TO `'.$prefix.'retained_cloud`');
    pu_ok('Missing stamped cloud table keeps GET preparation safe',!provider_inventory_ready()&&str_contains(pu_render(),'Prepare inventory storage'));
    pu_deny('Missing stamped cloud table requires original database recovery',fn()=>provider_inventory_migrate($password));
    db()->exec('RENAME TABLE `'.$prefix.'retained_cloud` TO '.table('provider_observation_cloud'));
    // A current stamp cannot authorize reconstruction of a missing established table.
    db()->exec('RENAME TABLE '.table('provider_domain_link_events').' TO `'.$prefix.'retained_domain_events`');
    pu_deny('Missing established v4 storage requires original database recovery',fn()=>provider_inventory_migrate($password));
    db()->exec('RENAME TABLE `'.$prefix.'retained_domain_events` TO '.table('provider_domain_link_events'));
    save_setting('provider_inventory_schema_version','99');pu_deny('Future schema remains refused',fn()=>provider_inventory_migrate($password));save_setting('provider_inventory_schema_version','4');
    pu_ok('No credential or provider operation was needed',one('SELECT credentials FROM '.table('provider_profiles'))['credentials']===''&&!is_file($private.'/provider-master.key'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/provider-inventory-upgrade-results.json',json_encode(['passed'=>$ok,'scope'=>'Native MySQL/MariaDB populated v1/v2/v3 upgrades and actual PHP owner view rendering with synthetic records; no network or browser','checks'=>$checks],JSON_PRETTY_PRINT));echo ($ok?'PASS':'FAIL').': '.count($checks).' native inventory upgrade assertions'.PHP_EOL;exit($ok?0:1);
