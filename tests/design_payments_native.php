<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require_once __DIR__.'/../site/app/billing/design_payments.php';
$prefix=getenv('CWP_DESIGN_PAYMENTS_TEST_PREFIX')?:'dpay_';if(!preg_match('/\Adpay_[a-z0-9_]{0,8}\z/',$prefix))exit(2);
$private=__DIR__.'/../test-runtime/design-payments-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-design-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>$prefix]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic financial owner passphrase';
function dp_ok(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function dp_deny(string $name,callable $call):void{try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException|JsonException $error){if($error instanceof PDOException)throw $error;dp_ok($name,true);return;}dp_ok($name,false);}
function dp_id(int $n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function dp_staff(int $id=1):void{$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];}
function dp_client(int $id=1):void{$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];}
function dp_fields(int $id,int $job=1):array{
    $p=one('SELECT * FROM '.table('studio_versions')." WHERE job_id=? AND kind='proposal' ORDER BY version_no DESC LIMIT 1",[$job]);$j=studio_job($job);
    return ['format'=>1,'id'=>dp_id($id),'client_id'=>(int)$j['client_id'],'project_id'=>$job,'proposal'=>['id'=>(int)$p['id'],'version'=>(int)$p['version_no'],'content_sha256'=>design_payments_proposal($p)],'currency'=>'USD','title'=>'Agreed design work','terms'=>"Client financial terms\r\nOne-time work only.",'items'=>[['id'=>dp_id(8000),'description'=>'Design and build','quantity'=>1,'unit_cents'=>10001,'discount_cents'=>0,'tax_cents'=>0]],'stages'=>[['id'=>dp_id(9000),'kind'=>'full','title'=>'Full service','calculation'=>'remainder','value'=>0,'due_date'=>'','milestone_id'=>'']]];
}
function dp_view(int $job=1):array{return design_payments_staff_get($job);}
function dp_decide(int $id,array $view,string $choice='accepted',string $comment=''):array{return design_payments_decide(1,1,dp_id($id),$view['expected'],$view['schedule']['id'],$view['fingerprint'],$choice,$comment);}
function dp_snapshot():string{$rows=[];foreach(['design_payment_events','design_payment_heads','studio_invoices','audit'] as $table)$rows[$table]=all('SELECT * FROM '.table($table).' ORDER BY id');return design_payments_json($rows);}
function dp_failure(callable $call,string $table='audit',string $verb='INSERT'):bool{
    $trigger=config()['db']['prefix'].'fail';db()->exec('CREATE TRIGGER `'.$trigger.'` BEFORE '.$verb.' ON '.table($table)." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic persistence failure'");
    try{$call();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER `'.$trigger.'`');}return $failed;
}
function dp_worker(string $mode,int $actor,int $job,int $client,int $id,string $expected,array $payload):array{
    $p=proc_open([PHP_BINARY,__FILE__,'worker',$mode,(string)$actor,(string)$job,(string)$client,dp_id($id),$expected,base64_encode(design_payments_json($payload))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Worker unavailable.');foreach([1,2] as $i)stream_set_timeout($pipes[$i],20);return [$p,$pipes];
}
function dp_finish(array $worker):string{[$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err!=='')throw new RuntimeException('Worker failed: '.$err);return $out;}
if(($argv[1]??'')==='worker'){
    if(in_array($argv[2],['publish','issue'],true))dp_staff((int)$argv[3]);else dp_client((int)$argv[5]);$data=json_decode(base64_decode($argv[8],true),true,20,JSON_THROW_ON_ERROR);echo "ready\n";fflush(STDOUT);
    try{
        if($argv[2]==='publish')$r=design_payments_publish((int)$argv[4],(int)$argv[5],$argv[6],$argv[7],$data);
        elseif($argv[2]==='issue')$r=design_payments_issue((int)$argv[4],(int)$argv[5],$argv[6],$argv[7],$data['schedule'],$data['fingerprint'],$data['stage']);
        else $r=design_payments_decide((int)$argv[4],(int)$argv[5],$argv[6],$argv[7],$data['schedule'],$data['fingerprint'],$data['choice'],$data['comment']);
        echo $r['replayed']?'replay':'new';
    }catch(InvalidArgumentException|RuntimeException|LogicException|JsonException $error){if($error instanceof PDOException)throw $error;echo 'denied';}exit;
}
try{
    foreach(schema_statements($prefix) as $sql)db()->exec($sql);foreach(default_settings() as $key=>$value)save_setting($key,$value);foreach(['studio_schema_version'=>'4','payments_schema_version'=>'1','support_schema_version'=>'1'] as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=5;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Financial staff '.$i,'finance'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);
    foreach([2=>['clients.manage','payments.manage'],3=>['clients.manage'],4=>['payments.manage'],5=>['clients.manage','support.login']] as $id=>$permissions)query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (?,'administrator',1,?)",[$id,json_encode($permissions)]);
    for($i=1;$i<=2;$i++)query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Financial client '.$i,'finance-client'.$i.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    for($i=1;$i<=6;$i++){
        query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$i===2?2:1,'Existing design '.$i,'Existing scope','design','Existing milestone','2025-01-01 01:00:00','2025-02-01 01:00:00']);
        query('INSERT INTO '.table('studio_versions')." (job_id,kind,version_no,title,body,preview_url,decision,feedback,decided_by,decided_at,created_at) VALUES (?,'proposal',1,'Exact original proposal','Proposal body','', 'approved','',?,'2025-02-01 02:00:00','2025-02-01 01:00:00')",[$i,$i===2?2:1]);
    }
    dp_staff();$original=design_payments_json(all('SELECT * FROM '.table('studio_jobs').' ORDER BY id'));
    dp_ok('Existing installation does not activate financial schedules',!design_payments_ready());dp_deny('Unprepared reads refuse',fn()=>dp_view());dp_deny('Wrong owner password refuses setup',fn()=>design_payments_migrate('wrong'));
    dp_staff(2);dp_deny('Payment manager cannot prepare storage',fn()=>design_payments_migrate($password));dp_staff();$_GET['support']='';dp_deny('Support selector refuses setup',fn()=>design_payments_migrate($password));unset($_GET['support']);
    db()->beginTransaction();dp_deny('Setup rejects inherited transaction',fn()=>design_payments_migrate($password));dp_ok('Setup refusal preserves caller transaction',db()->inTransaction());db()->rollBack();
    dp_ok('Setup audit failure cannot activate tables',dp_failure(fn()=>design_payments_migrate($password))&&!design_payments_ready());design_payments_migrate($password);design_payments_migrate($password);
    dp_ok('Owner setup is additive and idempotent',design_payments_ready()&&dp_view()['status']==='none'&&$original===design_payments_json(all('SELECT * FROM '.table('studio_jobs').' ORDER BY id')));
    save_setting('design_payments_schema_version','99');dp_deny('Unknown schema version refuses setup',fn()=>design_payments_migrate($password));save_setting('design_payments_schema_version','1');
    db()->exec('ALTER TABLE '.table('design_payment_events').' ADD INDEX unexpected(created_at)');dp_deny('Unknown event index refuses setup',fn()=>design_payments_migrate($password));db()->exec('ALTER TABLE '.table('design_payment_events').' DROP INDEX unexpected');
    $initial=dp_view()['expected'];$fields=dp_fields(1);
    foreach([3,4,5] as $id){dp_staff($id);dp_deny('Both financial and client permissions required'.$id,fn()=>design_payments_publish(1,1,dp_id(1),$initial,$fields));}dp_staff();
    $bad=$fields;$bad['client_id']=2;dp_deny('Offer cannot substitute client binding',fn()=>design_payments_publish(1,1,dp_id(1),$initial,$bad));
    $bad=$fields;$bad['proposal']['content_sha256']=str_repeat('0',64);dp_deny('Offer must bind real proposal contents',fn()=>design_payments_publish(1,1,dp_id(1),$initial,$bad));
    query('UPDATE '.table('studio_versions')." SET decision='pending' WHERE id=1");dp_deny('Unapproved proposal cannot support financial offer',fn()=>design_payments_publish(1,1,dp_id(1),$initial,$fields));query('UPDATE '.table('studio_versions')." SET decision='approved' WHERE id=1");
    $before=dp_snapshot();dp_ok('Offer audit failure rolls back events and head',dp_failure(fn()=>design_payments_publish(1,1,dp_id(1),$initial,$fields))&&dp_snapshot()===$before);
    $created=design_payments_publish(1,1,dp_id(1),$initial,$fields);$offer=dp_view();dp_ok('Proposal approval never silently approves financial terms',$offer['status']==='offered'&&$offer['schedule']===$fields&&$offer['quote']['total_cents']===10001&&!$created['replayed']);
    save_setting('design_payments_schema_version','');dp_deny('Setup refuses unrecognized populated financial tables',fn()=>design_payments_migrate($password));save_setting('design_payments_schema_version','1');dp_ok('Refused adoption preserves original financial offer',dp_view()['expected']===$offer['expected']);
    db()->beginTransaction();dp_deny('Financial writes refuse inherited transactions',fn()=>design_payments_publish(1,1,dp_id(1),$initial,$fields));dp_ok('Write refusal preserves caller transaction',db()->inTransaction());db()->rollBack();
    dp_ok('Original offer retry returns its exact event',design_payments_publish(1,1,dp_id(1),$initial,$fields)['replayed']&&dp_view()['count']===1);
    $bad=$fields;$bad['items'][0]['unit_cents']=1;dp_deny('Request identity cannot substitute a lower price',fn()=>design_payments_publish(1,1,dp_id(1),$initial,$bad));
    dp_deny('Staff cannot provide client financial consent',fn()=>dp_decide(2,$offer));dp_client(2);dp_deny('Wrong client cannot consent to this project',fn()=>dp_decide(2,$offer));dp_client();
    $public=design_payments_client_get(1,1);dp_ok('Client reads exact terms without staff or request metadata',$public['schedule']===$fields&&!isset($public['items'][0]['actor_id'],$public['items'][0]['input_sha256']));
    $bad=$offer;$bad['fingerprint']=str_repeat('0',64);dp_deny('Client consent binds exact schedule hash',fn()=>dp_decide(2,$bad));$bad=$offer;$bad['expected']=$initial;dp_deny('Stale client consent cannot replace original expected version',fn()=>dp_decide(2,$bad));
    $before=dp_snapshot();dp_ok('Client consent audit failure rolls back',dp_failure(fn()=>dp_decide(2,$offer))&&dp_snapshot()===$before);
    dp_decide(2,$offer);$accepted=design_payments_client_get(1,1);dp_ok('Actual client explicitly accepts financial terms',$accepted['status']==='accepted'&&$accepted['count']===2&&$accepted['items'][1]['actor']==='client');
    dp_ok('Original financial consent retry cannot duplicate history',dp_decide(2,$offer)['replayed']);dp_deny('A new decision cannot replace accepted terms',fn()=>dp_decide(3,$accepted,'declined','Changed mind'));
    dp_deny('Client cannot withdraw accepted terms as staff',fn()=>design_payments_withdraw(1,1,dp_id(3),$accepted['expected'],$fields['id'],$accepted['fingerprint'],'Staff withdrawal'));
    dp_staff();$before=dp_snapshot();dp_ok('Withdrawal head failure rolls back',dp_failure(fn()=>design_payments_withdraw(1,1,dp_id(3),$accepted['expected'],$fields['id'],$accepted['fingerprint'],'Replacing terms'),'design_payment_heads','UPDATE')&&dp_snapshot()===$before);
    design_payments_withdraw(1,1,dp_id(3),$accepted['expected'],$fields['id'],$accepted['fingerprint'],'Replacing terms');dp_ok('Withdrawal retains exact prior financial acceptance',dp_view()['status']==='withdrawn'&&dp_view()['count']===3);
    $new=dp_fields(4);$new['items'][0]['unit_cents']=20001;design_payments_publish(1,1,dp_id(4),dp_view()['expected'],$new);$offered=dp_view();dp_ok('Changed offer requires new financial consent',$offered['status']==='offered'&&$offered['quote']['total_cents']===20001);
    dp_client();dp_deny('Prior financial consent cannot authorize replacement price',fn()=>dp_decide(5,$offer));dp_deny('Decline requires meaningful feedback',fn()=>dp_decide(5,$offered,'declined',''));dp_decide(5,$offered,'declined','Please revise the scope');dp_ok('Client decline is retained with exact terms',design_payments_client_get(1,1)['status']==='declined');
    dp_deny('Client cannot read another project',fn()=>design_payments_client_get(2,1));dp_deny('Client cannot substitute another authenticated identity',fn()=>design_payments_client_get(2,2));
    $_SESSION['client_last']=time()-40;$idle=$_SESSION['client_last'];design_payments_client_get(1,1,0,null,false);dp_ok('Passive financial read preserves client idle deadline',$_SESSION['client_last']===$idle);
    $_SESSION['client_auth']=str_repeat('0',32);dp_deny('Revoked authentication refuses financial history',fn()=>design_payments_client_get(1,1));dp_client();$_SESSION['client_last']=time()-1801;dp_deny('Expired idle session refuses consent history',fn()=>design_payments_client_get(1,1));dp_client();$_SESSION['client_login']=time()-28801;dp_deny('Expired absolute session refuses consent history',fn()=>design_payments_client_get(1,1));
    dp_staff();$_SESSION['last_activity']=time()-40;$idle=$_SESSION['last_activity'];design_payments_staff_get(1,0,false);dp_ok('Passive financial read preserves staff idle deadline',$_SESSION['last_activity']===$idle);
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');dp_ok('Staff can inspect inactive-client history',dp_view()['count']===5);dp_deny('Inactive client blocks original offer replay',fn()=>design_payments_publish(1,1,dp_id(1),$initial,$fields));dp_client();dp_deny('Inactive client cannot read financial history',fn()=>design_payments_client_get(1,1));query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');dp_staff();
    require __DIR__.'/design_payments_native_extra.php';
    dp_ok('Financial module does not change existing project fields',$original===design_payments_json(all('SELECT * FROM '.table('studio_jobs').' ORDER BY id')));
    dp_ok('Consent creates no invoice charge or provider record',(int)query('SELECT COUNT(*) FROM '.table('studio_invoices'))->fetchColumn()===0&&(int)query('SELECT COUNT(*) FROM '.table('payment_attempts'))->fetchColumn()===0&&(int)query('SELECT COUNT(*) FROM '.table('provider_profiles'))->fetchColumn()===0);
    $audit=design_payments_json(all('SELECT * FROM '.table('audit')));dp_ok('General audit excludes financial text and passwords',!str_contains($audit,'Client financial terms')&&!str_contains($audit,$password));
    require __DIR__.'/design_payments_invoice_native.php';
    require __DIR__.'/design_payments_forms_native.php';
    require __DIR__.'/design_payments_activity_native.php';
}catch(Throwable $error){$checks[]=['name'=>'Unexpected failure: '.$error->getMessage(),'passed'=>false];fwrite(STDERR,(string)$error.PHP_EOL);}
$passed=!in_array(false,array_column($checks,'passed'),true);file_put_contents(__DIR__.'/../qa/design-payments-native-results.json',json_encode(['passed'=>$passed,'scope'=>'Synthetic MariaDB financial offers, consent and unique obligation invoices; existing ledger, authority, immutable history, concurrency, replay and rollback. No HTTP/browser or gateway acceptance.','php'=>PHP_VERSION,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo ($passed?'PASS':'FAIL').': '.count($checks).' native financial checks'.PHP_EOL;exit($passed?0:1);
