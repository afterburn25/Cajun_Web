<?php
/** Disposable SQL authority and publication-race checks for project review actions. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/projects/action_items.php';

$private=__DIR__.'/../test-runtime/project-action-items-private';@mkdir($private,0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-project-action-key-',3),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pari_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();

$checks=[];
function pani_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function pani_refuses(string $name,callable $work):void{try{$work();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;pani_ok($name,true);return;}pani_ok($name,false);}
function pani_client(int $id):array{return one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);}
function pani_primary(int $id):void{$c=pani_client($id);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];}
function pani_contact(int $id):void{$c=one('SELECT * FROM '.table('studio_contacts').' WHERE id=?',[$id]);pani_primary((int)$c['client_id']);$_SESSION['contact_id']=$id;$_SESSION['contact_auth']=$c['auth_version'];}
function pani_drop():void{foreach(array_reverse(array_keys(schema_statements('pari_'))) as $name)db()->exec('DROP TABLE IF EXISTS `pari_'.$name.'`');}
function pani_worker(int $clientId,string $auth,int $versionId):array{
    $command=[PHP_BINARY];$ini=php_ini_loaded_file();if(is_string($ini)&&$ini!=='')array_push($command,'-c',$ini);array_push($command,__FILE__,'decision-worker',(string)$clientId,$auth,(string)$versionId);
    $p=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Cannot start decision worker.');
    foreach([1,2] as $stream)stream_set_timeout($pipes[$stream],15);
    return [$p,$pipes];
}
function pani_finish(array $worker):string{[$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);if($exit!==0||$error!=='')throw new RuntimeException('Decision worker failed: '.$error);return $out;}

if(($argv[1]??'')==='decision-worker'){
    $_SESSION=['client_id'=>(int)$argv[2],'client_auth'=>$argv[3],'client_last'=>time(),'client_login'=>time()];
    echo "ready\n";fflush(STDOUT);
    try{studio_decide((int)$argv[2],(int)$argv[4],'approved','');echo 'decided';}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo str_contains($e->getMessage(),'superseded')?'superseded':'denied';}
    exit;
}

$failure=null;
try{
    pani_drop();foreach(schema_statements('pari_') as $sql)db()->exec($sql);foreach(default_settings() as $key=>$value)save_setting($key,$value);save_setting('contacts_schema_version','1');
    foreach([['Client One','one@example.invalid'],['Client Two','two@example.invalid']] as [$name,$email])query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',[$name,$email,bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    $client1=1;$client2=2;
    query('INSERT INTO '.table('studio_contacts').' (client_id,name,email,auth_version,permissions,created_at) VALUES (?,?,?,?,?,?)',[$client1,'Project Contact','project@example.invalid',bin2hex(random_bytes(16)),json_encode(['projects']),gmdate('Y-m-d H:i:s')]);$projectContact=(int)db()->lastInsertId();
    query('INSERT INTO '.table('studio_contacts').' (client_id,name,email,auth_version,permissions,created_at) VALUES (?,?,?,?,?,?)',[$client1,'Billing Contact','billing@example.invalid',bin2hex(random_bytes(16)),json_encode(['billing']),gmdate('Y-m-d H:i:s')]);$billingContact=(int)db()->lastInsertId();
    foreach([[$client1,'One project'],[$client2,'Two project'],[$client1,'Bounded project'],[$client1,'Corrupt project']] as [$owner,$title])query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$owner,$title,'Synthetic','Synthetic',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
    $job1=1;$job2=2;$boundedJob=3;$corruptJob=4;
    foreach([
        [$job1,'design',1,'approved'],[$job1,'proposal',1,'pending'],[$job1,'proposal',2,'pending'],[$job2,'design',1,'pending'],
    ] as [$job,$kind,$version,$decision])query('INSERT INTO '.table('studio_versions').' (job_id,kind,version_no,title,body,decision,feedback,created_at) VALUES (?,?,?,?,?,?,?,?)',[$job,$kind,$version,'Synthetic '.$kind,'Synthetic body',$decision,'',gmdate('Y-m-d H:i:s')]);
    $staleProposal=(int)one('SELECT id FROM '.table('studio_versions').' WHERE job_id=? AND kind=\'proposal\' AND version_no=2',[$job1])['id'];

    pani_primary($client1);$actions=project_current_review_actions($job1,$client1);
    pani_ok('Primary client sees only latest pending review',count($actions)===1&&$actions[0]->reviewId===$staleProposal&&$actions[0]->kind==='proposal'&&$actions[0]->version===2);
    pani_contact($projectContact);$contactActions=project_current_review_actions($job1,$client1);
    pani_ok('Current projects contact sees the same privacy-minimal action',json_encode($contactActions)===json_encode($actions));
    pani_contact($billingContact);pani_refuses('Billing-only contact cannot read project actions',fn()=>project_current_review_actions($job1,$client1));
    pani_primary($client1);pani_refuses('Client cannot read another client project actions',fn()=>project_current_review_actions($job2,$client1));

    $GLOBALS['cwp_support']=['client'=>pani_client($client1),'session'=>['id'=>str_repeat('a',32)]];
    pani_ok('Support session receives no customer action projection',project_current_review_actions($job1,$client1)===[]);unset($GLOBALS['cwp_support']);
    pani_contact($projectContact);query('UPDATE '.table('studio_contacts').' SET permissions=? WHERE id=?',[json_encode(['billing']),$projectContact]);
    pani_refuses('Contact grant revocation is observed without relogin',fn()=>project_current_review_actions($job1,$client1));
    query('UPDATE '.table('studio_contacts').' SET permissions=? WHERE id=?',[json_encode(['projects']),$projectContact]);
    pani_contact($projectContact);query('UPDATE '.table('studio_contacts').' SET enabled=0 WHERE id=?',[$projectContact]);
    pani_refuses('Disabled contact identity is observed without relogin',fn()=>project_current_review_actions($job1,$client1));
    query('UPDATE '.table('studio_contacts').' SET enabled=1 WHERE id=?',[$projectContact]);
    pani_primary($client1);query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=?',[$client1]);
    pani_refuses('Disabled primary identity is observed without relogin',fn()=>project_current_review_actions($job1,$client1));
    query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=?',[$client1]);pani_primary($client1);

    db()->beginTransaction();one('SELECT id FROM '.table('studio_jobs').' WHERE id=? FOR UPDATE',[$job1]);$worker=pani_worker($client1,pani_client($client1)['auth_version'],$staleProposal);pani_ok('Concurrent decision worker reaches the guarded operation',trim((string)fgets($worker[1][1]))==='ready');usleep(200000);
    query('INSERT INTO '.table('studio_versions').' (job_id,kind,version_no,title,body,decision,feedback,created_at) VALUES (?,\'proposal\',3,\'Synthetic proposal 3\',\'Synthetic body\',\'pending\',\'\',?)',[$job1,gmdate('Y-m-d H:i:s')]);$freshProposal=(int)db()->lastInsertId();db()->commit();
    pani_ok('Publication winning the project lock makes the stale decision fail',pani_finish($worker)==='superseded');
    pani_primary($client1);$afterPublish=project_current_review_actions($job1,$client1);
    pani_ok('Fresh latest review replaces the stale action',count($afterPublish)===1&&$afterPublish[0]->reviewId===$freshProposal&&$afterPublish[0]->version===3);
    studio_decide($client1,$freshProposal,'approved','');
    pani_ok('Successful exact-latest decision clears the action',project_current_review_actions($job1,$client1)===[]);

    db()->beginTransaction();for($version=1;$version<=1001;$version++)query('INSERT INTO '.table('studio_versions').' (job_id,kind,version_no,title,body,decision,feedback,created_at) VALUES (?,\'design\',?,?,\'Synthetic body\',\'approved\',\'\',?)',[$boundedJob,$version,'Bounded '.$version,gmdate('Y-m-d H:i:s')]);db()->commit();
    pani_refuses('More than 1000 review rows fail closed instead of truncating history',fn()=>project_current_review_actions($boundedJob,$client1));
    query('INSERT INTO '.table('studio_versions').' (job_id,kind,version_no,title,body,decision,feedback,created_at) VALUES (?,\'invoice\',1,\'Corrupt kind\',\'Synthetic body\',\'pending\',\'\',?)',[$corruptJob,gmdate('Y-m-d H:i:s')]);
    pani_refuses('Unexpected persisted review kind fails closed',fn()=>project_current_review_actions($corruptJob,$client1));
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$failure=get_class($e).': '.$e->getMessage();}
try{pani_drop();}catch(Throwable $cleanup){$failure=($failure?$failure.'; ':'').'cleanup '.get_class($cleanup).': '.$cleanup->getMessage();}
echo json_encode(['passed'=>$failure===null,'scope'=>'Disposable local SQL, real client/contact authority and concurrent project publication/decision lock; no HTTP/browser, customer message dispatch, payment, provider or live operation.','checks'=>$checks,'failure'=>$failure],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($failure===null?0:1);
