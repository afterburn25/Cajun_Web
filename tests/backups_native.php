<?php
/** Isolated MariaDB/private files, no customer data or provider calls. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require __DIR__.'/../site/app/backups/archive.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='backupci_';
$private=__DIR__.'/../test-runtime/backups-native-private';if(!is_dir($private))mkdir($private,0700);$GLOBALS['cwp_config']['storage']=realpath($private);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$password='Synthetic backups owner passphrase';$checks=[];
function b_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function b_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;b_ok($name,true);return;}b_ok($name,false);}
function b_actor(int $id=1): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function b_audit_failure(callable $fn): bool {db()->exec('CREATE TRIGGER backupci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER backupci_fail');}return $failed;}
$fixtureFile=CWP_CODE_ROOT.'/backups-fixture.txt';
try{
    foreach(schema_statements('backupci_') as $name=>$sql)db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);save_setting('staff_schema_version','1');
    $now=gmdate('Y-m-d H:i:s');for($id=1;$id<=3;$id++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Backups staff '.$id,'backups'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);staff_seed_owner(1);
    foreach([2=>['staff.manage'],3=>['website.manage']] as $id=>$grants)query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (?,'administrator',1,?)",[$id,json_encode($grants)]);b_actor();
    file_put_contents($fixtureFile,'original fixture bytes');
    b_deny('Setup requires owner reauthentication',fn()=>backups_migrate('wrong'));
    foreach(backups_schema('backupci_') as $sql)db()->exec($sql);
    query('INSERT INTO '.table('backups')." (id,kind,manifest,manifest_hash,items,archive_file,bytes,request_key,request_hash,status,retain_until,created_by,created_at) VALUES ('".str_repeat('e',32)."','files','{}','".str_repeat('0',64)."',0,'x.zip',0,'".str_repeat('f',64)."','".str_repeat('0',64)."','unknown','2030-01-01',1,?)",[$now]);
    b_deny('Setup refuses unknown populated backup records',fn()=>backups_migrate($password));query('DELETE FROM '.table('backups'));
    b_ok('Setup audit failure rolls back readiness',b_audit_failure(fn()=>backups_migrate($password))&&!backups_ready());backups_migrate($password);b_ok('Setup is additive and prepares private storage',backups_ready()&&is_dir(backups_directory())&&(fileperms(backups_directory())&0777)===0700);backups_migrate($password);b_ok('Repeated setup is safe',backups_ready());
    $storage=config()['storage'];$GLOBALS['cwp_config']['storage']=realpath(CWP_CODE_ROOT);b_deny('Backup storage cannot be inside web root',fn()=>backups_directory());$GLOBALS['cwp_config']['storage']=$storage;
    b_deny('Create refuses a malformed request key',fn()=>backups_create([],'short'));
    b_actor(3);b_deny('Website-only staff cannot create backups',fn()=>backups_create([],bin2hex(random_bytes(32))));b_actor();
    db()->exec('CREATE TABLE backupci_probe (id INT PRIMARY KEY, marker VARCHAR(40))');query("INSERT INTO backupci_probe VALUES (1,'MARKER-DATABASE-ROW')");
    query("INSERT INTO ".table('rate_limits')." (bucket,attempts,expires) VALUES ('TRANSIENT-THROTTLE-ROW',1,1)");
    $key=bin2hex(random_bytes(32));$id=backups_create(['notes'=>'Synthetic snapshot','retain_days'=>30],$key);$row=backups_row($id);
    b_ok('Create persists manifest, archive and owner attribution',strlen($id)===32&&$row['status']==='ready'&&(int)$row['created_by']===1&&is_file(backups_archive_path($row))&&$row['manifest_hash']===backups_manifest($row)->manifestHash());
    $section=backups_database($row);$zip=new ZipArchive();$zip->open(backups_archive_path($row));$dump=$zip->getFromName('database/snapshot.sql');$zip->close();
    b_ok('Backup preserves a consistent database export inside private storage',$section!==null&&$dump!==false&&hash_equals($section['sha256'],hash('sha256',$dump))&&$section['tables']>=2&&$section['rows']>=1);
    b_ok('Database export contains real rows and skips transient state',str_contains($dump,"INSERT INTO `backupci_probe`")&&str_contains($dump,'MARKER-DATABASE-ROW')&&!str_contains($dump,'TRANSIENT-THROTTLE-ROW'));
    b_ok('Manifest excludes secrets and private storage structurally',!isset(backups_manifest($row)->items['config.php'],backups_manifest($row)->items['.env'])&&!array_filter(array_keys(backups_manifest($row)->items),fn($n)=>str_starts_with($n,'storage/')||str_contains($n,'payment-master')));
    b_ok('Same request returns same record and bytes',backups_create(['notes'=>'Synthetic snapshot','retain_days'=>30],$key)===$id&&(int)query('SELECT COUNT(*) FROM '.table('backups'))->fetchColumn()===1);
    b_deny('Reusing create key with changed details rejected',fn()=>backups_create(['notes'=>'changed','retain_days'=>30],$key));
    b_actor(2);b_deny('Another actor cannot reuse create request',fn()=>backups_create(['notes'=>'Synthetic snapshot','retain_days'=>30],$key));b_actor();
    $before=glob(backups_directory().'/*');b_ok('Create audit failure rolls back row and uncommitted archive',b_audit_failure(fn()=>backups_create([],bin2hex(random_bytes(32))))&&glob(backups_directory().'/*')===$before);
    backups_migrate($password);b_ok('Re-running setup preserves recorded backups and archives',backups_row($id)===$row&&is_file(backups_archive_path($row)));
    $plan=backups_preview($row);b_ok('Fresh backup previews as fully identical',$plan->changedItems()===[]);
    file_put_contents($fixtureFile,'tampered fixture bytes');$plan=backups_preview(backups_row($id));
    b_ok('Preview marks the changed file differing',$plan->impact['backups-fixture.txt']['current']==='differs');
    b_deny('Restore refuses the wrong confirmation phrase',fn()=>backups_restore($id,'WRONG PHRASE',$password));
    b_deny('Restore requires the current owner password',fn()=>backups_restore($id,backups_restore_phrase($row),'wrong'));
    b_actor(2);b_deny('Non-owner staff cannot restore even with the phrase',fn()=>backups_restore($id,backups_restore_phrase($row),$password));b_actor();
    backups_restore($id,backups_restore_phrase($row),$password);
    b_ok('Authorized restore rewrote only the changed file',file_get_contents($fixtureFile)==='original fixture bytes'&&backups_row($id)['status']==='applied');
    unlink($fixtureFile);$plan=backups_preview(backups_row($id));b_ok('Preview marks a deleted file missing',$plan->impact['backups-fixture.txt']['current']==='missing');
    backups_restore($id,backups_restore_phrase($row),$password);b_ok('Second restore recreates the missing file',file_get_contents($fixtureFile)==='original fixture bytes');
    b_ok('File restores never write the database export into the deployed tree',!file_exists(CWP_CODE_ROOT.'/database/snapshot.sql'));
    $drill=backups_drill($id);b_ok('Restore drill verifies every item in isolated storage',$drill['result']==='verified'&&$drill['verified']===(int)$row['items']+1&&$drill['missing']===0&&$drill['mismatched']===0&&$drill['verified']>0);
    b_ok('Drill attempt is recorded durably',(one('SELECT result FROM '.table('backup_drills').' WHERE id=?',[$drill['id']])['result']??'')==='verified');
    b_ok('Drill leaves no scratch and no deployed change',glob(backups_directory().'/drill-*')===[]&&file_get_contents($fixtureFile)==='original fixture bytes');
    b_actor(3);b_deny('Website-only staff cannot run a restore drill',fn()=>backups_drill($id));b_actor();
    $before_drills=(int)query('SELECT COUNT(*) FROM '.table('backup_drills'))->fetchColumn();
    b_ok('Drill audit failure rolls back the record',b_audit_failure(fn()=>backups_drill($id))&&(int)query('SELECT COUNT(*) FROM '.table('backup_drills'))->fetchColumn()===$before_drills);
    $archive=backups_archive_path(backups_row($id));$bytes=file_get_contents($archive);file_put_contents($archive,substr($bytes,0,-64));
    file_put_contents($fixtureFile,'tampered again');b_deny('Corrupt stored archive fails closed',fn()=>backups_restore($id,backups_restore_phrase(backups_row($id)),$password));
    b_deny('Corrupt stored archive fails the drill too',fn()=>backups_drill($id));
    b_ok('Corrupt drill attempt is still recorded',(one('SELECT result FROM '.table('backup_drills')." WHERE backup_id=? AND result='corrupt' ORDER BY finished_at DESC LIMIT 1",[$id])['result']??'')==='corrupt');
    file_put_contents($archive,$bytes);
    b_ok('File unchanged after archive rejection',file_get_contents($fixtureFile)==='tampered again');
    $zip=new ZipArchive();$zip->open($archive);$zip->addFromString('backups-fixture.txt','substituted bytes');$zip->close();
    $d2=backups_drill($id);b_ok('Drill flags archive bytes that no longer match the manifest',$d2['result']==='incomplete'&&$d2['mismatched']===1&&$d2['verified']===(int)$row['items']);
    file_put_contents($archive,$bytes);
    $zip=new ZipArchive();$zip->open($archive);$zip->deleteName('backups-fixture.txt');$zip->close();
    $d3=backups_drill($id);b_ok('Drill flags a missing archive entry',$d3['result']==='incomplete'&&$d3['missing']===1);
    file_put_contents($archive,$bytes);
    query('UPDATE '.table('backups')." SET retain_until='2000-01-01' WHERE id=?",[$id]);file_put_contents($fixtureFile,'original fixture bytes');
    b_deny('Expired backup cannot be restored',fn()=>backups_restore($id,backups_restore_phrase(backups_row($id)),$password));
    b_deny('Prune requires the current owner password',fn()=>backups_prune('wrong'));
    b_ok('Prune removes expired rows and archives only',backups_prune($password)===1&&!one('SELECT id FROM '.table('backups').' WHERE id=?',[$id])&&!is_file($archive));
    query('DELETE FROM '.table('backups'));b_ok('Prune with nothing expired is a no-op',backups_prune($password)===0);
    b_ok('Audit records setup, create and restore',(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action IN ('backups.setup','backups.create','backups.restore')")->fetchColumn()>=3);
}catch(Throwable $e){fwrite(STDERR,'backups_native failed: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n");exit(1);}
finally{if(is_file($fixtureFile))unlink($fixtureFile);foreach(glob($private.'/backups/*')?:[] as $f)@unlink($f);}
echo 'backups_native: '.count($checks).' checks passed'.PHP_EOL;
