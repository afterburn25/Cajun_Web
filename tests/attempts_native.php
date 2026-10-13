<?php
/** Disposable native DB, process concurrency, and synthetic gateway boundary. Never provider acceptance. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/app/core.php';require __DIR__.'/../site/app/schema.php';
$GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';$GLOBALS['cwp_config']['db']['prefix']='trialci_';
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$password='Synthetic sandbox owner passphrase';$checks=[];
function trial_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function trial_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;trial_ok($name,true);return;}trial_ok($name,false);}
function trial_actor(int $id=1): void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function trial_request(): int {return payments_prepare(1,'full',7,payments_invoice_hash(payments_invoice(1)),bin2hex(random_bytes(32)));}
function trial_transport(string $body): array {
    global $calls,$mode,$reference,$transaction,$overrides,$during;
    $r=json_decode($body,true,32,JSON_THROW_ON_ERROR);$method=array_key_first($r);$calls[]=$method;
    $ok=['messages'=>['resultCode'=>'Ok','message'=>[['code'=>'I00001','text'=>'Synthetic success']]]];
    if($method==='getMerchantDetailsRequest')return [200,json_encode($ok+['isTestMode'=>$mode==='test-mode','currencies'=>$mode==='other-currency'?['EUR']:['USD']])];
    if($method==='createTransactionRequest'){
        $reference=$r[$method]['refId'];$t=$r[$method]['transactionRequest'];
        trial_ok('Charge uses saved full amount and token only',$t['amount']==='125.10'&&array_keys($t['payment'])===['opaqueData']&&$t['payment']['opaqueData']['dataValue']==='SyntheticNonceOnly'&&$t['order']['invoiceNumber']===$reference);
        trial_ok('Sandbox disables receipts partial auth and uses duplicate guard',$t['transactionSettings']['setting']===[['settingName'=>'emailCustomer','settingValue'=>'false'],['settingName'=>'allowPartialAuth','settingValue'=>'false'],['settingName'=>'duplicateWindow','settingValue'=>'28800']]);
        if($during)$during();
        if($mode==='timeout')throw new RuntimeException('Synthetic provider timeout; must not be logged or retried');
        return [200,json_encode($ok+['refId'=>$reference,'transactionResponse'=>['responseCode'=>'1','transId'=>$transaction]])];
    }
    if($method==='getTransactionDetailsRequest')return [200,json_encode($ok+['transaction'=>array_replace_recursive(['transId'=>$transaction,'transactionType'=>'authCaptureTransaction','transactionStatus'=>'capturedPendingSettlement','responseCode'=>1,'order'=>['invoiceNumber'=>$reference],'authAmount'=>125.10,'settleAmount'=>125.10],$overrides)])];
    throw new LogicException('Unexpected provider method');
}
if(($argv[1]??'')==='duplicate-worker'){trial_actor();echo attempts_submit((int)$argv[2],1,$argv[3],'SyntheticNonceOnly',$password,fn()=>throw new RuntimeException('Duplicate must not dispatch'));exit;}
try{
    foreach(schema_statements('trialci_') as $name=>$sql)if(!in_array($name,['payment_attempts','payment_attempt_events'],true))db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);
    foreach(['staff','support','catalog','payments'] as $module)save_setting($module.'_schema_version','1');
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Trial owner','trial-owner@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('a',32),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);trial_actor();
    query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Trial client','trial-client@example.invalid',str_repeat('b',32),gmdate('Y-m-d H:i:s')]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (1,?,?,?,?,?)',['Trial project','Retain summary','Retain milestones',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
    query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,instructions,created_at) VALUES (1,?,?,?,?,?)',['TRIAL-FIXTURE','Synthetic trial invoice',12510,'Retain manual instructions',gmdate('Y-m-d H:i:s')]);$invoice=one('SELECT * FROM '.table('studio_invoices'));
    trial_deny('Trial migration requires current owner password',fn()=>attempts_migrate('wrong'));
    db()->exec(attempts_schema('trialci_')['payment_attempts']);db()->exec('ALTER TABLE '.table('payment_attempts').' DROP INDEX request_id');
    trial_deny('Migration rejects missing unique request lock',fn()=>attempts_migrate($password));
    trial_ok('Migration does not create other tables before validating all existing tables',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=\'trialci_payment_attempt_events\''));
    db()->exec('ALTER TABLE '.table('payment_attempts').' ADD UNIQUE request_id(request_id)');attempts_migrate($password);attempts_migrate($password);
    trial_ok('Additive repeated migration retains invoice',attempts_ready()&&one('SELECT * FROM '.table('studio_invoices'))===$invoice);
    $creds=['api_login_id'=>'SandboxTrialFixture','transaction_key'=>'FixtureKey123456','signature_key'=>str_repeat('b',128),'public_client_key'=>'SandboxPublicFixture'];payments_save('sandbox',0,$creds,$password);query('UPDATE '.table('payment_gateways')." SET validation='authenticated' WHERE environment='sandbox'");
    $calls=[];$mode='ok';$reference='';$transaction='900001';$overrides=[];$during=null;$key=str_repeat('a',64);$request=trial_request();
    trial_deny('Trial refuses wrong owner reauthentication',fn()=>attempts_submit($request,1,$key,'SyntheticNonceOnly','wrong','trial_transport'));
    trial_deny('Trial refuses malformed token before gateway traffic',fn()=>attempts_submit($request,1,$key,'<raw card>',$password,'trial_transport'));
    trial_deny('Trial refuses stale gateway revision',fn()=>attempts_submit($request,2,$key,'SyntheticNonceOnly',$password,'trial_transport'));
    trial_ok('Denied trial has no provider traffic or ledger rows',!$calls&&!all('SELECT * FROM '.table('payment_attempts')));
    foreach(['test-mode','other-currency'] as $mode)trial_deny('Preflight refuses '.$mode,fn()=>attempts_submit($request,1,$key,'SyntheticNonceOnly',$password,'trial_transport'));
    trial_ok('Failed merchant preflight never sends a transaction',!in_array('createTransactionRequest',$calls,true)&&!all('SELECT * FROM '.table('payment_attempts')));
    $mode='ok';$calls=[];
    $during=function() use($request,$key,$password): void {
        $pending=one('SELECT * FROM '.table('payment_attempts'));trial_ok('Intent and strict audit commit before network send',$pending['state']==='submitting'&&(int)query('SELECT COUNT(*) FROM '.table('payment_attempt_events'))->fetchColumn()===1&&!db()->inTransaction());
        trial_deny('In-flight trial blocks manual paid transition',fn()=>payments_manual_paid(1,1,'Verified bank receipt'));
        trial_deny('In-flight trial blocks request replacement',fn()=>trial_request());
        trial_deny('In-flight trial blocks request revocation',fn()=>payments_revoke($request));
        $proc=proc_open([PHP_BINARY,__FILE__,'duplicate-worker',(string)$request,$key],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $output=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);
        trial_ok('Concurrent PHP retry returns original intent without a second send',$code===0&&trim($output)===(string)$pending['id']&&$stderr==='');
    };
    $id=attempts_submit($request,1,$key,'SyntheticNonceOnly',$password,'trial_transport');$during=null;
    $a=one('SELECT * FROM '.table('payment_attempts').' WHERE id=?',[$id]);trial_ok('Capture needs matching reporting details',$a['state']==='captured'&&$a['transaction_id']===$transaction&&$calls===['getMerchantDetailsRequest','createTransactionRequest','getTransactionDetailsRequest']);
    trial_ok('Sandbox capture never pays actual invoice',one('SELECT * FROM '.table('studio_invoices'))===$invoice);
    $before=$calls;trial_ok('Idempotent retry never calls provider again',attempts_submit($request,1,$key,'SyntheticNonceOnly',$password,'trial_transport')===$id&&$calls===$before);
    trial_deny('Different submission key cannot charge same request',fn()=>attempts_submit($request,1,str_repeat('b',64),'SyntheticNonceOnly',$password,'trial_transport'));
    $overrides=['transactionStatus'=>'settledSuccessfully'];attempts_reconcile($id,$transaction,1,$password,'trial_transport');trial_ok('Matching settlement recorded as sandbox only',one('SELECT state FROM '.table('payment_attempts').' WHERE id=?',[$id])['state']==='settled'&&one('SELECT * FROM '.table('studio_invoices'))===$invoice);
    $request=trial_request();$key=str_repeat('b',64);$mode='timeout';$overrides=[];$transaction='900002';$calls=[];
    $id=attempts_submit($request,1,$key,'SyntheticNonceOnly',$password,'trial_transport');$a=one('SELECT * FROM '.table('payment_attempts').' WHERE id=?',[$id]);trial_ok('Ambiguous send is durable unknown with no automatic retry',$a['state']==='unknown'&&$a['transaction_id']===null&&count(array_filter($calls,fn($m)=>$m==='createTransactionRequest'))===1);
    trial_deny('Unknown attempt blocks later manual payment',fn()=>payments_manual_paid(1,1,'Verified external receipt'));
    $mode='ok';
    foreach([['transId'=>'900999'],['order'=>['invoiceNumber'=>'UnrelatedReference']],['authAmount'=>125.11],['settleAmount'=>1],['transactionType'=>'refundTransaction'],['currencyCode'=>'EUR'],['splitTenderId'=>'123'],['transactionStatus'=>'unknownStatus'],['responseCode'=>2]] as $bad){$overrides=$bad;trial_deny('Mismatched reporting facts do not resolve '.array_key_first($bad),fn()=>attempts_reconcile($id,$transaction,1,$password,'trial_transport'));}
    trial_ok('Unmatched typed transaction IDs were never attached',one('SELECT transaction_id FROM '.table('payment_attempts').' WHERE id=?',[$id])['transaction_id']===null);
    $overrides=['transactionStatus'=>'FDSAuthorizedPendingReview'];attempts_reconcile($id,$transaction,1,$password,'trial_transport');trial_ok('Held result remains unresolved',one('SELECT state FROM '.table('payment_attempts').' WHERE id=?',[$id])['state']==='held');
    trial_deny('Held result blocks request replacement',fn()=>trial_request());
    trial_deny('Attached transaction cannot be swapped',fn()=>attempts_reconcile($id,'900099',1,$password,'trial_transport'));
    $overrides=[];attempts_reconcile($id,$transaction,1,$password,'trial_transport');trial_ok('Verified details resolve timeout to captured',one('SELECT state FROM '.table('payment_attempts').' WHERE id=?',[$id])['state']==='captured');
    $stale=one('SELECT * FROM '.table('payment_attempts').' WHERE id=?',[$id]);$stale['event_count']=(int)query('SELECT COUNT(*) FROM '.table('payment_attempt_events').' WHERE attempt_id=?',[$id])->fetchColumn();
    $overrides=['transactionStatus'=>'settledSuccessfully'];attempts_reconcile($id,$transaction,1,$password,'trial_transport');attempts_record($stale,['state'=>'unknown','transaction_id'=>$transaction],1,'response');trial_ok('Delayed observation cannot regress newer settlement',one('SELECT state FROM '.table('payment_attempts').' WHERE id=?',[$id])['state']==='settled');
    payments_save('sandbox',1,['api_login_id'=>'AnotherSandboxAccount'],$password);query('UPDATE '.table('payment_gateways')." SET validation='authenticated'");trial_deny('Different merchant cannot reconcile retained attempt',fn()=>attempts_reconcile($id,$transaction,2,$password,'trial_transport'));
    payments_save('sandbox',2,$creds,$password);query('UPDATE '.table('payment_gateways')." SET validation='authenticated'");attempts_reconcile($id,$transaction,3,$password,'trial_transport');trial_ok('Same merchant key rotation supports reconciliation',true);
    $request=trial_request();$key=str_repeat('c',64);$calls=[];$overrides=[];$transaction='900003';
    db()->exec('CREATE TRIGGER trialci_audit_failure BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
    $count=(int)query('SELECT COUNT(*) FROM '.table('payment_attempts'))->fetchColumn();try{attempts_submit($request,3,$key,'SyntheticNonceOnly',$password,'trial_transport');$failed=false;}catch(PDOException){$failed=true;}
    trial_ok('Intent audit failure rolls back ledger and never sends charge',$failed&&(int)query('SELECT COUNT(*) FROM '.table('payment_attempts'))->fetchColumn()===$count&&!in_array('createTransactionRequest',$calls,true));
    try{payments_manual_paid(1,1,'Verified external receipt');$failed=false;}catch(PDOException){$failed=true;}trial_ok('Manual payment audit failure rolls back financial update',$failed&&one('SELECT * FROM '.table('studio_invoices'))===$invoice);db()->exec('DROP TRIGGER trialci_audit_failure');
    $during=function(): void {db()->exec('CREATE TRIGGER trialci_response_audit_failure BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic response audit failure'");};
    try{attempts_submit($request,3,$key,'SyntheticNonceOnly',$password,'trial_transport');$failed=false;}catch(PDOException){$failed=true;}$during=null;
    $a=one('SELECT * FROM '.table('payment_attempts').' WHERE request_id=?',[$request]);trial_ok('Post-send audit failure preserves unresolved committed intent',$failed&&$a['state']==='submitting'&&$a['transaction_id']===null);db()->exec('DROP TRIGGER trialci_response_audit_failure');
    attempts_reconcile((int)$a['id'],$transaction,3,$password,'trial_transport');trial_ok('Reporting recovers a post-send persistence failure',one('SELECT state FROM '.table('payment_attempts').' WHERE id=?',[$a['id']])['state']==='captured');
    $GLOBALS['cwp_support']=['actor'=>['id'=>1]];trial_deny('Support context cannot submit a trial',fn()=>attempts_submit($request,3,$key,'SyntheticNonceOnly',$password,'trial_transport'));trial_deny('Support context cannot verify transactions',fn()=>attempts_reconcile((int)$a['id'],$transaction,3,$password,'trial_transport'));unset($GLOBALS['cwp_support']);
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Trial staff','trial-staff@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('d',32),gmdate('Y-m-d H:i:s')]);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,?)",['["payments.manage","clients.manage"]']);trial_actor(2);
    trial_deny('Payment staff cannot run owner-only trials',fn()=>attempts_submit($request,3,$key,'SyntheticNonceOnly',$password,'trial_transport'));trial_actor();
    $saved=json_encode([all('SELECT * FROM '.table('payment_attempts')),all('SELECT * FROM '.table('payment_attempt_events')),all('SELECT * FROM '.table('audit'))]);trial_ok('Persisted trial/audit facts exclude nonces credentials and provider text',!str_contains($saved,'SyntheticNonce')&&!str_contains($saved,'SandboxTrialFixture')&&!str_contains($saved,'FixtureKey')&&!str_contains($saved,'Synthetic success'));
    trial_ok('No trial changed actual invoice financial state',one('SELECT * FROM '.table('studio_invoices'))===$invoice);
    payments_manual_paid(1,1,'Verified independent bank receipt');trial_ok('Resolved sandbox permits independently audited real payment',one('SELECT status FROM '.table('studio_invoices'))['status']==='paid');
    foreach(['[]','null','true','{"messages":null}','invalid'] as $raw)trial_ok('Malformed reporting fails closed '.hash('crc32b',$raw),authorize_sandbox_call('getTransactionDetailsRequest',$creds,['transId'=>'1'],fn()=>[200,$raw])===null||!authorize_sandbox_ok(json_decode($raw,true)));
}catch(Throwable $e){$error=$e;}
file_put_contents(__DIR__.'/../qa/attempts-native-results.json',json_encode(['passed'=>!isset($error),'gateway'=>'Synthetic transport only; no real sandbox operations','checks'=>$checks,'failure'=>isset($error)?get_class($error).': '.$error->getMessage():null],JSON_PRETTY_PRINT));
foreach($checks as $c)echo ($c['passed']?'PASS ':'FAIL ').$c['name']."\n";
if(isset($error)){fwrite(STDERR,$error."\n");exit(1);}echo count($checks)." sandbox native checks passed\n";
