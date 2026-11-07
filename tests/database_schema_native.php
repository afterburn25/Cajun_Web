<?php
/** Disposable SQL acceptance for the unified reviewed database-update boundary. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';

$database=(string)getenv('CWP_TEST_DATABASE');$port=(int)getenv('CWP_TEST_PORT');
$mappedPort=(string)getenv('CWP_TEST_DATABASE_DOCKER_PORT');
if(PHP_SAPI!=='cli'||$database!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||$port<1
    ||($mappedPort!==''&&$mappedPort!=='3306'))exit(2);
$site=realpath((string)(getenv('CWP_TEST_CODE_ROOT')?:__DIR__.'/../site'));
if(!$site||!is_file($site.'/app/settings/database_schemas.php'))exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',$site);
require CWP_CODE_ROOT.'/includes/bootstrap.php';
require CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/app/billing/payments.php';
require_once CWP_CODE_ROOT.'/app/settings/database_schemas.php';
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1','storage'=>'','key'=>'','db'=>[
    'host'=>'127.0.0.1','port'=>$port,'name'=>$database,'user'=>(string)getenv('CWP_TEST_USER'),
    'pass'=>(string)getenv('CWP_TEST_PASSWORD'),'prefix'=>'dbuci_',
]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SESSION=[];
$checks=[];$password='Synthetic database-update owner';
function dsn_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function dsn_bad(string $name,callable $work):void {try{$work();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;dsn_ok($name,true);return;}dsn_ok($name,false);}
function dsn_owner():void {$user=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$user['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function dsn_contacts_tables():array {return all("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,6)='dbuci_' AND TABLE_NAME LIKE 'dbuci_studio_contact%' ORDER BY TABLE_NAME");}

try {
    $serverPort=(int)query('SELECT @@port')->fetchColumn();
    dsn_ok('Fixture connects only to the explicitly owned database and client/server port mapping',
        query('SELECT DATABASE()')->fetchColumn()===$database&&(int)config()['db']['port']===$port
        &&$serverPort===($mappedPort===''?$port:(int)$mappedPort));
    dsn_ok('Owned database-update namespace is initially empty',
        !all("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,6)='dbuci_'"));
    $schema=schema_statements('dbuci_');
    foreach(['settings','users','audit','studio_clients','staff_profiles'] as $name)db()->exec($schema[$name]);
    query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (1,?,?,?,?,?)',[
        'Database update owner','database-update-owner@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('d',32),gmdate('Y-m-d H:i:s'),
    ]);
    staff_seed_owner(1);save_setting('studio_schema_version','2');dsn_owner();

    $before=database_schema_snapshot();$plan=database_schema_plan(['contacts_schema_version'],$before);
    dsn_ok('Exact review plans Contacts without mutating SQL or history',
        $plan['order']===['contacts_schema_version']&&strlen($plan['review_hash'])===64&&!dsn_contacts_tables()
        &&(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action LIKE 'database.update.%'")->fetchColumn()===0);

    save_setting('contacts_schema_version','unsupported-native-fixture');
    dsn_bad('Changed database state refuses the reviewed plan before migration',
        fn()=>database_schema_apply($plan['requested'],$plan['review_hash']));
    dsn_ok('Stale review refusal preserves schema and history',
        !dsn_contacts_tables()&&(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action LIKE 'database.update.%'")->fetchColumn()===0);
    save_setting('contacts_schema_version','');
    $plan=database_schema_plan(['contacts_schema_version'],database_schema_snapshot());

    db()->exec("CREATE TRIGGER dbuci_database_history_refusal BEFORE INSERT ON ".table('audit')." FOR EACH ROW BEGIN IF NEW.action='database.update.started' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic database history refusal'; END IF; END");
    try { dsn_bad('Unavailable start history prevents every migration side effect',fn()=>database_schema_apply($plan['requested'],$plan['review_hash'])); }
    finally { db()->exec('DROP TRIGGER dbuci_database_history_refusal'); }
    dsn_ok('Start-history refusal leaves Contacts wholly unprepared',
        setting('contacts_schema_version')===''&&!dsn_contacts_tables()&&(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action LIKE 'database.update.%'")->fetchColumn()===0);

    $result=database_schema_apply($plan['requested'],$plan['review_hash']);
    $history=all("SELECT action,detail FROM ".table('audit')." WHERE action LIKE 'database.update.%' ORDER BY id");
    $outcomes=array_map(fn(array $row)=>[$row['action'],json_decode($row['detail'],true,512,JSON_THROW_ON_ERROR)['marker']??''],$history);
    dsn_ok('Reviewed owner apply reaches the exact Contacts target once',
        $result['applied']===['contacts_schema_version']&&setting('contacts_schema_version')==='1'&&count(dsn_contacts_tables())===1);
    dsn_ok('Actual migration records ordered started and completed outcomes',
        $outcomes===[['database.update.started','contacts_schema_version'],['database.update.completed','contacts_schema_version']]);
    $tables=dsn_contacts_tables();$auditCount=count($history);
    dsn_bad('Completed reviewed plan cannot be replayed',fn()=>database_schema_apply($plan['requested'],$plan['review_hash']));
    dsn_ok('Replay refusal preserves completed schema and history',
        dsn_contacts_tables()===$tables&&setting('contacts_schema_version')==='1'
        &&(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action LIKE 'database.update.%'")->fetchColumn()===$auditCount);
    $report=['passed'=>true,'php'=>PHP_VERSION,'database_version'=>query('SELECT VERSION()')->fetchColumn(),'port'=>$port,
        'scope'=>'Actual disposable unified Contacts migration and audit boundary only; no customer data, provider access, messages, payments, provisioning, private files or production deployment.','checks'=>$checks];
} catch(Throwable $e) {
    $report=['passed'=>false,'php'=>PHP_VERSION,'checks'=>$checks,'failure'=>[
        'class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'code'=>(string)$e->getCode(),
        'message'=>$e instanceof PDOException?'Database error; disposable unified migration fixture did not complete.':$e->getMessage(),
    ]];
}
file_put_contents(__DIR__.'/../qa/database-schema-native-results.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
exit($report['passed']?0:1);
