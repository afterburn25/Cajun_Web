<?php
/** Native catalog and invoice acceptance against disposable InnoDB fixtures only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='catalogci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];start_session();
$checks=[];$password='Synthetic catalog owner passphrase';
function cat_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function cat_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;cat_ok($name,true);return;}cat_ok($name,false);}
function cat_actor(int $id): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION['uid']=$id;$_SESSION['auth_version']=$u['auth_version'];$_SESSION['login_at']=$_SESSION['last_activity']=time();}
if(($argv[1]??'')==='issue-worker'){cat_actor(1);try{echo catalog_issue(1,(int)$argv[2],(int)$argv[3],2,'Concurrent invoice',str_repeat('e',64));exit;}catch(Throwable $e){fwrite(STDERR,$e->getMessage());exit(1);}}
try{
    foreach(schema_statements('catalogci_') as $key=>$sql)if(!str_starts_with($key,'catalog_'))db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Catalog owner','catalog-owner@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('a',32),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);cat_actor(1);
    query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Catalog client','catalog-client@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('b',32),gmdate('Y-m-d H:i:s')]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[1,'Catalog project','Existing project','Existing milestones',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
    $before=one('SELECT * FROM '.table('studio_clients'));
    cat_ok('Catalog public fallback before migration',catalog_public()===[]);
    cat_deny('Catalog setup requires correct owner password',fn()=>catalog_migrate('bad'));
    db()->exec(catalog_schema('catalogci_')['catalog_products']);db()->exec('ALTER TABLE '.table('catalog_products').' MODIFY amount_cents VARCHAR(12) NULL');
    cat_deny('Incompatible catalog schema rejected without replacement',fn()=>catalog_migrate($password));
    cat_ok('Incompatible existing column retained',one('SHOW COLUMNS FROM '.table('catalog_products')." LIKE 'amount_cents'")['Type']==='varchar(12)');
    db()->exec('ALTER TABLE '.table('catalog_products').' MODIFY amount_cents INT UNSIGNED NULL');catalog_migrate($password);catalog_migrate($password);
    cat_ok('Repeated additive setup preserves customer credentials',catalog_ready()&&one('SELECT * FROM '.table('studio_clients'))===$before);
    $data=['sku'=>'WEB-ONE','title'=>'Synthetic design service','family'=>'web-design','status'=>'draft','term'=>'once','currency'=>'USD','amount'=>'125.10','setup'=>'10.00','cost'=>'42.37','provider_ref'=>'private-provider-identifier','summary'=>'Approved synthetic scope','features'=>"First feature\nSecond feature",'terms'=>'One agreed project phase. No automatic renewal.','sort_order'=>'0'];
    $id=catalog_save(0,0,$data);cat_ok('New products stay draft and absent publicly',catalog_public()===[]);
    foreach(['1e2','-1.00','1,000','1.234','NaN','1000000.00'] as $invalid)cat_deny('Reject malformed money '.$invalid,fn()=>catalog_save(0,0,array_replace($data,['sku'=>'BAD','amount'=>$invalid])));
    cat_deny('Duplicate SKU denied',fn()=>catalog_save(0,0,$data));
    cat_deny('Unsupported currency denied',fn()=>catalog_save(0,0,array_replace($data,['currency'=>'EUR'])));
    cat_deny('Unconfigured publication denied',fn()=>catalog_save(0,0,array_replace($data,['sku'=>'NO-PRICE','status'=>'published','amount'=>''])));
    cat_deny('Unverified hosting publication denied',fn()=>catalog_save(0,0,array_replace($data,['sku'=>'HOST','status'=>'published','family'=>'hosting'])));
    $id=catalog_save($id,1,array_replace($data,['status'=>'published']));$public=catalog_public();
    cat_ok('Configured price appears in public projection',count($public)===1&&(int)$public[0]['amount_cents']===12510);
    cat_ok('Public projection excludes internal cost and provider reference',!array_key_exists('cost_cents',$public[0])&&!str_contains(json_encode($public),'private-provider-identifier'));
    cat_deny('Stale product edit rejected',fn()=>catalog_save($id,1,$data));
    cat_deny('Stale price cannot be silently invoiced',fn()=>catalog_issue(1,$id,1,2,'Manual instructions',str_repeat('1',64)));
    $quote=catalog_quote($id,2,3);cat_ok('Integer invoice total includes setup exactly once',$quote['total_cents']===38530);
    foreach([0,101,-1] as $qty)cat_deny('Quantity boundary '.$qty,fn()=>catalog_quote($id,2,$qty));
    $invoice=catalog_issue(1,$id,2,3,'Manual instructions',str_repeat('1',64));$snapshot=catalog_snapshot($invoice);
    cat_ok('Invoice reuses current project and client identities',one('SELECT job_id,amount_cents,status FROM '.table('studio_invoices').' WHERE id=?',[$invoice])==['job_id'=>1,'amount_cents'=>38530,'status'=>'issued']);
    cat_ok('Snapshot excludes private costs and provider IDs',!str_contains(json_encode($snapshot),'private-provider-identifier')&&!array_key_exists('cost_cents',$snapshot));
    cat_ok('Duplicate identical issue returns same invoice',catalog_issue(1,$id,2,3,'Manual instructions',str_repeat('1',64))===$invoice);
    cat_deny('Duplicate key cannot change invoice details',fn()=>catalog_issue(1,$id,2,4,'Manual instructions',str_repeat('1',64)));
    catalog_save($id,2,array_replace($data,['status'=>'published','amount'=>'500000.00','setup'=>'0.00','title'=>'Changed service']));
    cat_ok('Catalog changes cannot rewrite issued price or text',catalog_snapshot($invoice)===$snapshot&&one('SELECT amount_cents FROM '.table('studio_invoices').' WHERE id=?',[$invoice])['amount_cents']===38530);
    cat_deny('Overflowing invoice rejected',fn()=>catalog_quote($id,3,2));
    catalog_save($id,3,array_replace($data,['status'=>'retired']));cat_ok('Retired products disappear publicly',catalog_public()===[]);
    cat_deny('Retired product cannot produce new invoice',fn()=>catalog_issue(1,$id,4,1,'No',str_repeat('2',64)));
    cat_ok('Identical request replay survives retirement',catalog_issue(1,$id,2,3,'Manual instructions',str_repeat('1',64))===$invoice);
    catalog_save($id,4,array_replace($data,['status'=>'published']));
    $jobs=[];for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'issue-worker',(string)$id,'5'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$jobs[]=[$proc,$pipes];}
    $ids=[];foreach($jobs as [$proc,$pipes]){$ids[]=trim(stream_get_contents($pipes[1]));$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);cat_ok('Concurrent invoice worker succeeds',proc_close($proc)===0&&$err==='');}
    cat_ok('Concurrent duplicate submits issue once',$ids[0]===$ids[1]&&count(all('SELECT id FROM '.table('catalog_invoice_snapshots').' WHERE request_key=?',[str_repeat('e',64)]))===1);
    $staff=staff_save(0,'Catalog viewer','catalog-viewer@example.invalid','administrator',true,['catalog.manage'],$password);$link=staff_invite($staff,$password);parse_str(parse_url($link,PHP_URL_FRAGMENT),$parts);staff_activate($parts['token'],$password);cat_actor($staff);
    cat_deny('Catalog-only staff cannot issue client invoices',fn()=>catalog_issue(1,$id,5,1,'Denied',str_repeat('3',64)));
    cat_deny('Catalog administrator cannot run owner migration',fn()=>catalog_migrate($password));cat_actor(1);
    staff_save($staff,'Catalog viewer','catalog-viewer@example.invalid','administrator',true,['clients.manage'],$password);cat_actor($staff);
    cat_deny('Client-only staff cannot manage prices',fn()=>catalog_save($id,5,$data));cat_deny('Client-only staff cannot transition catalog invoices',fn()=>catalog_transition(1,$invoice,'paid','Unverified payment'));cat_actor(1);
    $GLOBALS['cwp_support']=['test'=>true];cat_deny('Support context cannot issue invoices',fn()=>catalog_issue(1,$id,5,1,'No support issue',str_repeat('4',64)));unset($GLOBALS['cwp_support']);
    $count=(int)query('SELECT COUNT(*) FROM '.table('studio_invoices'))->fetchColumn();
    db()->exec('CREATE TRIGGER catalogci_audit_failure BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action LIKE 'catalog.%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic catalog audit failure'; END IF; END");
    $failed=false;try{catalog_issue(1,$id,5,1,'Must roll back',str_repeat('5',64));}catch(PDOException $e){$failed=$e->getCode()==='45000';}
    cat_ok('Audit failure rolls back invoice and snapshot',$failed&&(int)query('SELECT COUNT(*) FROM '.table('studio_invoices'))->fetchColumn()===$count&&!one('SELECT id FROM '.table('catalog_invoice_snapshots').' WHERE request_key=?',[str_repeat('5',64)]));
    $failed=false;try{catalog_save($id,5,$data);}catch(PDOException $e){$failed=true;}cat_ok('Audit failure rolls back product revision',$failed&&(int)one('SELECT revision FROM '.table('catalog_products').' WHERE id=?',[$id])['revision']===5);
    $failed=false;try{catalog_transition(1,$invoice,'paid','Synthetic external payment');}catch(PDOException $e){$failed=true;}cat_ok('Audit failure rolls back paid transition',$failed&&one('SELECT status FROM '.table('studio_invoices').' WHERE id=?',[$invoice])['status']==='issued');
    db()->exec('DROP TRIGGER catalogci_audit_failure');catalog_transition(1,$invoice,'paid','Synthetic external payment');cat_ok('Verified manual payment retains immutable price',one('SELECT status FROM '.table('studio_invoices').' WHERE id=?',[$invoice])['status']==='paid'&&catalog_snapshot($invoice)===$snapshot);
    cat_deny('Paid invoice cannot be voided',fn()=>catalog_transition(1,$invoice,'void','Cannot cancel a payment'));
    catalog_transition(1,(int)$ids[0],'void','Synthetic cancellation');cat_ok('Unpaid void preserves snapshot',one('SELECT status FROM '.table('studio_invoices').' WHERE id=?',[$ids[0]])['status']==='void'&&catalog_snapshot((int)$ids[0])!==null);
    cat_deny('Void invoice cannot be marked paid',fn()=>catalog_transition(1,(int)$ids[0],'paid','Invalid payment'));
    cat_ok('Catalog revisions are retained',count(all('SELECT id FROM '.table('catalog_revisions').' WHERE product_id=?',[$id]))===5);
}catch(Throwable $e){$error=$e;}
file_put_contents(__DIR__.'/../qa/catalog-native-results.json',json_encode(['passed'=>!isset($error),'checks'=>$checks,'failure'=>isset($error)?get_class($error).': '.$error->getMessage():null],JSON_PRETTY_PRINT));
foreach($checks as $c)echo ($c['passed']?'PASS ':'FAIL ').$c['name']."\n";
if(isset($error)){fwrite(STDERR,$error."\n");exit(1);}echo count($checks)." catalog adapter checks passed\n";
