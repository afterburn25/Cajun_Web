<?php
/** CLI-only queries against explicitly disposable acceptance fixtures. Never shipped. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('CWP_TEST_DATABASE') !== 'cwp_test') exit(2);
define('CWP_BOOT', true);
$request = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
// A populated catalog fixture calls the old authorization adapter. Its core must
// resolve CWP_ROOT to that same retained release, never include two staff adapters.
$oldCore=match($request['operation']??''){'seed-ledger-upgrade'=>'test-runtime/released-1.7.0','seed-notification-upgrade'=>'test-runtime/released-1.6.0','seed-trial-upgrade'=>'test-runtime/released-1.5.0','seed-payment-upgrade'=>'test-runtime/released-1.4.0',default=>'site'};
require __DIR__.'/../'.$oldCore.'/app/core.php';
$GLOBALS['cwp_config'] = require __DIR__.'/../test-runtime/config-fixture.php';
if (in_array($request['operation']??'', ['seed-upgrade','seed-staff-upgrade','seed-support-upgrade','seed-catalog-upgrade','seed-payment-upgrade','seed-trial-upgrade','seed-notification-upgrade','seed-ledger-upgrade'],true)) {
    $ledgerUpgrade=$request['operation']==='seed-ledger-upgrade';
    $notificationUpgrade=$ledgerUpgrade||$request['operation']==='seed-notification-upgrade';
    $trialUpgrade=$notificationUpgrade||$request['operation']==='seed-trial-upgrade';$paymentUpgrade=$trialUpgrade||$request['operation']==='seed-payment-upgrade';$catalogUpgrade=$paymentUpgrade||$request['operation']==='seed-catalog-upgrade';$supportUpgrade=$catalogUpgrade||$request['operation']==='seed-support-upgrade';$staffUpgrade=$supportUpgrade||$request['operation']==='seed-staff-upgrade';$folder=$trialUpgrade?'trial-upgrade':($paymentUpgrade?'payment-upgrade':($catalogUpgrade?'catalog-upgrade':($supportUpgrade?'support-upgrade':($staffUpgrade?'staff-upgrade':'upgrade'))));$prefix=$trialUpgrade?'trialup_':($paymentUpgrade?'paymentup_':($catalogUpgrade?'catalogup_':($supportUpgrade?'supportup_':($staffUpgrade?'staffup_':'upgrade_'))));
    if($notificationUpgrade){$folder='notification-upgrade';$prefix='notifyup_';}
    if($ledgerUpgrade){$folder='ledger-upgrade';$prefix='ledgerup_';}
    require __DIR__.'/../test-runtime/'.($ledgerUpgrade?'released-1.7.0':($notificationUpgrade?'released-1.6.0':($trialUpgrade?'released-1.5.0':($paymentUpgrade?'released-1.4.0':($catalogUpgrade?'released-1.3.0':($supportUpgrade?'released-1.2.0':($staffUpgrade?'released-1.1.0':'legacy'))))))).'/app/schema.php';
    $GLOBALS['cwp_config']['db']['prefix'] = $prefix;
    $GLOBALS['cwp_config']['site_url'] = 'http://127.0.0.1:8091/'.$folder;
    $private = __DIR__.'/../test-runtime/'.$folder.'-private';
    if (!is_dir($private)) mkdir($private, 0700, true);
    if (!is_dir($private.'/media')) mkdir($private.'/media', 0700);
    $GLOBALS['cwp_config']['storage'] = realpath($private);
    foreach (schema_statements($prefix) as $sql) db()->exec($sql);
    foreach (default_settings() as $key=>$value) save_setting($key,$value);
    save_setting('sentinel_original','Keep original '.($staffUpgrade?'1.1.0':'1.0.8').' settings');
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Upgrade owner','upgrade@example.invalid',password_hash('Synthetic upgrade passphrase 2026',PASSWORD_DEFAULT),str_repeat('c',32),$now]);
    query('INSERT INTO '.table('leads')." (reference,request_key,name,email,service,budget,timeline,message,consent,ip_hash,status,notes,created_at) VALUES (?,?,?,?,?,?,?,?,1,?,'new',?,?)",
        ['UPGRADE-LEAD',str_repeat('3',64),'Old inquiry','old@example.invalid','Design','Discuss','Flexible','Preserve the original inquiry.',str_repeat('4',64),'Keep this note',$now]);
    foreach(default_projects() as $i=>$v) query('INSERT INTO '.table('projects')." (slug,title,category,summary,description,challenge,solution,deliverables,cover,status,is_demo,featured,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'published',1,1,?,?,?)",[...$v,$i,$now,$now]);
    file_put_contents($private.'/media/retained.php', CWP_GUARD.'Original private media');
    if($staffUpgrade){
        query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Retained client','retained@example.invalid',str_repeat('e',32),$now]);
        query('INSERT INTO '.table('studio_jobs').' (client_id,title,stage,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[1,'Retained website project','design','Preserve project summary','Preserve project progress',$now,$now]);
        query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Other legacy administrator','other-legacy@example.invalid',password_hash('Synthetic other legacy passphrase',PASSWORD_DEFAULT),str_repeat('f',32),$now]);
    }
    if($supportUpgrade){
        staff_seed_owner(1);
        query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,?)",[json_encode(['website.manage','clients.manage'])]);
        query('UPDATE '.table('studio_clients').' SET password_hash=? WHERE id=1',[password_hash('Synthetic retained client passphrase',PASSWORD_DEFAULT)]);
        staff_store()->issue('2',str_repeat('f',32));
    }
    if($catalogUpgrade){
        save_setting('support_schema_version','1');save_setting('support_policy_confirmed','1');
        query('INSERT INTO '.table('support_clients').' (client_id,allowed,updated_by,updated_at) VALUES (1,1,1,?)',[time()]);
        query('INSERT INTO '.table('support_events')." (actor_id,client_id,action,object_type,object_id,outcome,occurred_at) VALUES (1,1,'client.allowed','client','1','allowed',?)",[time()]);
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,instructions,created_at) VALUES (1,?,?,?,?,?)',['OLD-MANUAL','Retained manual invoice',9900,'Preserve these instructions',$now]);
    }
    if($paymentUpgrade){
        save_setting('catalog_schema_version','1');
        $_SESSION=['uid'=>1,'auth_version'=>str_repeat('c',32),'login_at'=>time(),'last_activity'=>time()];
        $product=catalog_save(0,0,['sku'=>'RETAINED-DESIGN','title'=>'Retained catalog service','family'=>'web-design','status'=>'published','term'=>'once','currency'=>'USD','amount'=>'175.50','setup'=>'5.00','cost'=>'40.00','provider_ref'=>'Retained private reference','summary'=>'Retain scope','features'=>'Retain features','terms'=>'Retain agreed terms','sort_order'=>'0']);
        catalog_issue(1,$product,1,1,'Retained catalog invoice',str_repeat('9',64));
    }
    if($trialUpgrade){
        $_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../'.$oldCore);
        save_setting('payments_schema_version','1');
        payments_save('sandbox',0,['api_login_id'=>'RetainedSandboxFixture','transaction_key'=>'FixtureKey123456','signature_key'=>str_repeat('b',128),'public_client_key'=>'RetainedPublicFixture'],'Synthetic upgrade passphrase 2026');
        payments_prepare(1,'deposit',7,payments_invoice_hash(payments_invoice(1)),str_repeat('8',64));
    }
    if($notificationUpgrade){
        save_setting('payment_attempts_schema_version','1');
        query('INSERT INTO '.table('payment_attempts').' (request_id,invoice_id,actor_id,attempt_key,reference,environment,account_hash,gateway_revision,amount_cents,currency,state,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[1,1,1,str_repeat('7',64),'CW'.str_repeat('a',18),'sandbox',hash('sha256','sandbox|RetainedSandboxFixture'),1,9900,'USD','unknown',null,$now,$now]);
        attempts_event(1,1,'submitted');
    }
    if($ledgerUpgrade){
        save_setting('payment_notifications_schema_version','1');
        query('INSERT INTO '.table('payment_notifications').' (notification_id,webhook_id,account_hash,gateway_revision,body_hash,event_type,event_date,transaction_id,reference,status,received_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',['10000000-0000-0000-0000-000000000001','10000000-0000-0000-0000-000000000002',hash('sha256','sandbox|RetainedSandboxFixture'),1,str_repeat('a',64),'net.authorize.payment.authcapture.created','2026-10-13T01:02:03Z','910001','CW'.str_repeat('a',18),'queued',$now]);
        catalog_transition(1,2,'paid','Retained private external payment');
    }
    file_put_contents(__DIR__.'/../test-runtime/'.$folder.'-config.php','<?php return '.var_export(config(),true).';');
    echo json_encode(['ok'=>true]);
} else {
    $stmt=query($request['sql'],$request['params']??[]);
    echo json_encode($stmt->columnCount() ? $stmt->fetchAll() : ['changed'=>$stmt->rowCount()],JSON_THROW_ON_ERROR);
}
