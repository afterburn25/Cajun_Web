<?php
/** Execute every financing native case on an isolated SQL database; never load live configuration. */
declare(strict_types=1);
require __DIR__.'/release_finance_fixture.php';
try {
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'users']))throw new RuntimeException('Refuse a populated fixture prefix.');
    foreach(schema_statements($prefix) as $sql)db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    save_setting('payments_schema_version','2');
    save_setting('invoice_ledger_schema_version','1');
    save_setting('support_schema_version','1');
    for($i=1;$i<=4;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Recurring staff '.$i,'recurring'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>['clients.manage','payments.manage'],3=>['clients.manage'],4=>['payments.manage']] as $id=>$permissions)query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (?,'administrator',1,?)",[$id,json_encode($permissions)]);
    for($i=1;$i<=2;$i++){
        query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Recurring client '.$i,'recurring-client'.$i.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
        query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$i,'Existing project '.$i,'Preserved scope','design','','2020-01-01 00:00:00','2020-01-01 00:00:00']);
    }
    rb_staff();service_plans_migrate('');recurring_billing_migrate('');
    rb_ok('Current owner session prepares real recurring storage without password re-entry',recurring_billing_ready());
    require __DIR__.'/license_financing_native_cases.php';
    $retained=[];
    foreach(['website_licenses','license_domains','license_financing','license_finance_offers','license_finance_subscriptions','license_finance_subscription_history','license_finance_subscription_receipts','license_finance_reversals','invoice_payments','license_financing_rights_grants'] as $name){
        $rows=array_map(static fn(array $row):string=>json_encode($row,JSON_THROW_ON_ERROR),all('SELECT * FROM '.table($name)));sort($rows);$retained[$name]=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
    }
    license_financing_prepare('');license_finance_offers_prepare('');license_finance_gateway_prepare('');
    foreach($retained as $name=>$hash){
        $rows=array_map(static fn(array $row):string=>json_encode($row,JSON_THROW_ON_ERROR),all('SELECT * FROM '.table($name)));sort($rows);
        rb_ok('Repeated preparation retains populated '.$name,hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))===$hash);
    }
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e.PHP_EOL);}
$passed=count($checks)>0&&!in_array(false,array_column($checks,'passed'),true);
file_put_contents(__DIR__.'/../qa/release-finance-native-results.json',json_encode(['passed'=>$passed,'source_sha'=>getenv('CWP_SOURCE_SHA'),'php'=>PHP_VERSION,'scope'=>'Actual SQL and complete existing finance native cases; synthetic gateway facts; no provider/merchant network calls. Repeated schema preparation is not an installed file upgrade.','checks'=>$checks,'expected_diagnostics'=>$GLOBALS['rb_expected_diagnostics']??[]],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['passed'=>$passed,'checks'=>count($checks)]).PHP_EOL;
exit($passed?0:1);
