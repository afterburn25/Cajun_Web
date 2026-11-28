<?php
/** Isolated native storage, migration, integrity and activation-lock tests. */
declare(strict_types=1);

$database=(string)getenv('CWP_TEST_DATABASE');$port=(int)getenv('CWP_TEST_PORT');
if(PHP_SAPI!=='cli'||$database!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||$port!==33306)exit(2);
$foreignDatabase=(string)getenv('CWP_TEST_PROJECT_PROGRESS_FOREIGN_DATABASE');
if($foreignDatabase!=='cwp_progress_foreign')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',realpath(__DIR__.'/../site'));
require CWP_CODE_ROOT.'/includes/bootstrap.php';
require CWP_CODE_ROOT.'/database/schema/base.php';
require CWP_CODE_ROOT.'/database/migrations/project_progress_migrate.php';

$ddlWorker=getenv('CWP_PROJECT_PROGRESS_DDL_WORKER')==='1';
$staleWorker=getenv('CWP_PROJECT_PROGRESS_STALE_WORKER')==='1';
$prefix=($ddlWorker||$staleWorker)?(string)getenv('CWP_PROJECT_PROGRESS_WORKER_PREFIX'):'progressci_';
if(!in_array($prefix,['progressci_','progressloss_','progresspartial_','progressaudit_','progressindex_','progressforeign_'],true))exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1','storage'=>realpath(__DIR__.'/../test-runtime'),'key'=>'',
    'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>$database,'user'=>(string)getenv('CWP_TEST_USER'),
        'pass'=>(string)getenv('CWP_TEST_PASSWORD'),'prefix'=>$prefix]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SESSION=[];

if($ddlWorker){
    try{
        db()->exec('SET SESSION lock_wait_timeout=2');
        db()->exec('ALTER TABLE '.table('studio_progress_events').' ADD COLUMN forbidden_race INT NULL');
    }catch(PDOException $error){
        $message=strtolower($error->getMessage());
        if(str_contains($message,'lock wait timeout')||str_contains($message,'lock timeout')){echo "blocked\n";exit(0);}
        fwrite(STDERR,$error->getMessage().PHP_EOL);exit(3);
    }
    fwrite(STDERR,"Concurrent DDL bypassed the activation metadata lock.\n");exit(4);
}
if($staleWorker){
    $version=(string)getenv('CWP_PROJECT_PROGRESS_STALE_AUTH_VERSION');
    if(!preg_match('/\A[0-9a-f]{32}\z/D',$version))exit(2);
    $_SESSION=['uid'=>1,'auth_version'=>$version,'login_at'=>time(),'last_activity'=>time()];
    project_progress_migrate();
    fwrite(STDERR,"Stale project-progress worker unexpectedly returned.\n");exit(3);
}

$checks=[];$password='Synthetic project progress owner';
function pp_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function pp_snapshot():array{
    $prefix=config()['db']['prefix'];$tables=[];
    foreach(all("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE ? ORDER BY TABLE_NAME",[$prefix.'%']) as $entry){
        $name=(string)$entry['TABLE_NAME'];$definition=one('SHOW CREATE TABLE `'.str_replace('`','``',$name).'`');
        $rows=array_map('serialize',all('SELECT * FROM `'.str_replace('`','``',$name).'`'));sort($rows,SORT_STRING);
        $tables[$name]=['schema'=>(string)($definition['Create Table']??''),'rows'=>$rows];
    }
    return $tables;
}
function pp_refuse(string $name,callable $work,callable $matches,bool $unchanged=true):void{
    $before=$unchanged?pp_snapshot():null;$error=null;try{$work();}catch(Throwable $caught){$error=$caught;}
    pp_ok($name,$error!==null&&$matches($error)&&(!$unchanged||pp_snapshot()===$before));
}
function pp_exact(string $class,string $message):callable{return fn(Throwable $error):bool=>get_class($error)===$class&&$error->getMessage()===$message;}
function pp_fk_error(string|array $tables):callable{return function(Throwable $error)use($tables):bool{
    $sqlstate=$error instanceof PDOException?(string)($error->errorInfo[0]??$error->getCode()):'';$message=strtolower($error->getMessage());
    $tables=(array)$tables;$named=(bool)array_filter($tables,fn(string $table):bool=>str_contains($message,strtolower(config()['db']['prefix'].$table)));
    return $error instanceof PDOException&&$sqlstate==='23000'&&$named&&str_contains($message,'foreign key');
};}
function pp_fk_name(string $table,array $columns):string{
    $groups=[];
    foreach(all('SELECT CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[config()['db']['prefix'].$table]) as $row)$groups[(string)$row['CONSTRAINT_NAME']][(int)$row['ORDINAL_POSITION']]=(string)$row['COLUMN_NAME'];
    $matches=[];foreach($groups as $name=>$fields){ksort($fields);if(array_values($fields)===$columns)$matches[]=$name;}
    if(count($matches)!==1||!preg_match('/\A[A-Za-z0-9_]+\z/D',$matches[0]))throw new RuntimeException('Expected one exact synthetic foreign key.');
    return $matches[0];
}
function pp_exists(string $name):bool{return (bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);}
function pp_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function pp_owner(int $id=1):void{$user=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$user['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_support']);}
function pp_drop(string $prefix):void{
    $GLOBALS['cwp_config']['db']['prefix']=$prefix;unset($GLOBALS['cwp_settings']);db()->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE ? ORDER BY TABLE_NAME DESC',[$prefix.'%']) as $row)db()->exec('DROP TABLE `'.str_replace('`','``',(string)$row['TABLE_NAME']).'`');
    db()->exec('SET FOREIGN_KEY_CHECKS=1');
}
function pp_base(string $prefix):void{
    global $password;pp_drop($prefix);$sql=schema_statements($prefix);
    foreach(['settings','users','audit','studio_clients','staff_profiles','studio_jobs'] as $name)db()->exec($sql[$name]);
    foreach([1,2] as $id)query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?,?)',[$id,'Progress operator '.$id,'progress-operator'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat((string)$id,32),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles')." (user_id,role,enabled,permissions) VALUES (2,'administrator',1,'[]')");
    foreach([1,2] as $id){query('INSERT INTO '.table('studio_clients').' (id,name,email,auth_version,created_at) VALUES (?,?,?,?,?)',[$id,'Progress client '.$id,'progress-client'.$id.'@example.invalid',str_repeat('a',32),gmdate('Y-m-d H:i:s')]);query('INSERT INTO '.table('studio_jobs').' (id,client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',[$id,$id,'Retained project '.$id,'Retained scope','design','[]','2020-01-01 00:00:00','2020-01-01 00:00:00']);}
    save_setting('studio_schema_version','2');save_setting('project_progress_schema_version','');pp_owner();
}
function pp_event(string $id,int $job,int $client,int $revision):void{
    query('INSERT INTO '.table('studio_progress_events').' (id,job_id,client_id,staff_id,revision,kind,created_at,event_json,event_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$id,$job,$client,1,$revision,'progress','2020-01-02 00:00:00','{}',str_repeat('a',64)]);
}
function pp_ddl_worker(string $prefix):array{
    putenv('CWP_PROJECT_PROGRESS_DDL_WORKER=1');putenv('CWP_PROJECT_PROGRESS_WORKER_PREFIX='.$prefix);
    try{
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__],[1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__.'/..',null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Could not start the isolated DDL worker.');
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
        return ['exit'=>$exit,'stdout'=>$stdout,'stderr'=>$stderr];
    }finally{putenv('CWP_PROJECT_PROGRESS_DDL_WORKER');putenv('CWP_PROJECT_PROGRESS_WORKER_PREFIX');}
}
function pp_stale_worker(string $prefix,string $version):array{
    putenv('CWP_PROJECT_PROGRESS_STALE_WORKER=1');putenv('CWP_PROJECT_PROGRESS_WORKER_PREFIX='.$prefix);putenv('CWP_PROJECT_PROGRESS_STALE_AUTH_VERSION='.$version);
    try{
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__],[1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__.'/..',null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Could not start the isolated stale-authority worker.');
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
        return ['exit'=>$exit,'stdout'=>$stdout,'stderr'=>$stderr];
    }finally{putenv('CWP_PROJECT_PROGRESS_STALE_WORKER');putenv('CWP_PROJECT_PROGRESS_WORKER_PREFIX');putenv('CWP_PROJECT_PROGRESS_STALE_AUTH_VERSION');}
}

try{
    pp_base('progressci_');$jobs=all('SELECT * FROM '.table('studio_jobs').' ORDER BY id');
    project_progress_migrate();project_progress_migrate();
    foreach(array_keys(project_progress_schema(config()['db']['prefix'])) as $name)project_progress_validate_schema($name);
    project_progress_validate_workspace_identity();
    pp_ok('Fresh and repeated setup retain workspace rows and activate exact storage',setting('project_progress_schema_version')==='1'&&all('SELECT * FROM '.table('studio_jobs').' ORDER BY id')===$jobs&&pp_count('studio_progress_events')===0);
    $audit=all("SELECT detail FROM ".table('audit')." WHERE action='project.progress.setup'");
    pp_ok('Setup audit contains no client project or credential data',count($audit)===2&&!str_contains(json_encode($audit,JSON_THROW_ON_ERROR),'Progress client')&&!str_contains(json_encode($audit,JSON_THROW_ON_ERROR),$password));

    db()->exec('ALTER TABLE '.table('studio_progress_events').' ADD INDEX unexpected(kind)');
    pp_refuse('Validator rejects an unexpected event index without changing it',fn()=>project_progress_validate_schema('studio_progress_events'),pp_exact(RuntimeException::class,'Incompatible project progress indexes. Existing data was preserved.'));
    db()->exec('ALTER TABLE '.table('studio_progress_events').' DROP INDEX unexpected');
    db()->exec('ALTER TABLE '.table('studio_progress_events').' MODIFY kind VARCHAR(21) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    pp_refuse('Validator rejects an altered event column without changing it',fn()=>project_progress_validate_schema('studio_progress_events'),pp_exact(RuntimeException::class,'Incompatible project progress columns. Existing data was preserved.'));
    db()->exec('ALTER TABLE '.table('studio_progress_events').' MODIFY kind VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    $composite=pp_fk_name('studio_progress_events',['job_id','client_id']);
    db()->exec('ALTER TABLE '.table('studio_progress_events').' DROP FOREIGN KEY `'.$composite.'`, ADD FOREIGN KEY (job_id) REFERENCES '.table('studio_jobs').' (id) ON DELETE RESTRICT ON UPDATE RESTRICT');
    pp_refuse('Validator rejects a weakened single-column project relationship',fn()=>project_progress_validate_schema('studio_progress_events'),pp_exact(RuntimeException::class,'Incompatible project progress relationships. Existing data was preserved.'));
    $weakened=pp_fk_name('studio_progress_events',['job_id']);
    db()->exec('ALTER TABLE '.table('studio_progress_events').' DROP FOREIGN KEY `'.$weakened.'`, ADD FOREIGN KEY (job_id,client_id) REFERENCES '.table('studio_jobs').' (id,client_id) ON DELETE RESTRICT ON UPDATE RESTRICT');
    project_progress_validate_schema('studio_progress_events');

    pp_refuse('Composite event identity rejects a different project client',fn()=>pp_event(str_repeat('1',32),1,2,1),pp_fk_error('studio_progress_events'));
    pp_event(str_repeat('2',32),1,1,1);pp_event(str_repeat('3',32),2,2,1);
    pp_refuse('Composite head identity rejects an event from another project',fn()=>query('INSERT INTO '.table('studio_progress_heads').' (id,event_id,event_sha256,revision) VALUES (1,?,?,1)',[str_repeat('3',32),str_repeat('a',64)]),pp_fk_error('studio_progress_heads'));
    query('INSERT INTO '.table('studio_progress_heads').' (id,event_id,event_sha256,revision) VALUES (1,?,?,1)',[str_repeat('2',32),str_repeat('a',64)]);
    query('INSERT INTO '.table('studio_progress_requests').' (id,input_json,input_sha256) VALUES (?,?,?)',[str_repeat('2',32),'{}',str_repeat('b',64)]);
    pp_ok('Same-project event request and monotonic head facts are accepted',pp_count('studio_progress_events')===2&&pp_count('studio_progress_requests')===1&&pp_count('studio_progress_heads')===1);
    pp_refuse('Restrictive relationships preserve a project with progress history',fn()=>query('DELETE FROM '.table('studio_jobs').' WHERE id=1'),pp_fk_error(['studio_progress_events','studio_progress_heads']));

    db()->beginTransaction();project_progress_lock_and_validate(project_progress_schema(config()['db']['prefix']));$race=pp_ddl_worker(config()['db']['prefix']);db()->rollBack();
    pp_ok('Activation reads retain metadata locks against concurrent DDL',$race===['exit'=>0,'stdout'=>"blocked\n",'stderr'=>'']&&!one('SHOW COLUMNS FROM '.table('studio_progress_events')." LIKE 'forbidden_race'"));
    project_progress_validate_schema('studio_progress_events');

    pp_base('progressloss_');project_progress_migrate();db()->exec('DROP TABLE '.table('studio_progress_heads'));
    pp_refuse('Established marker refuses a missing table without recreating it',fn()=>project_progress_migrate(),pp_exact(RuntimeException::class,'Established project progress storage is missing. Restore the original database before continuing.'));
    pp_ok('Established-loss refusal preserves the remaining tables and marker',!pp_exists('studio_progress_heads')&&pp_exists('studio_progress_events')&&setting('project_progress_schema_version')==='1');

    pp_base('progresspartial_');db()->exec('ALTER TABLE '.table('studio_jobs').' ADD UNIQUE KEY project_progress_owner(id,client_id)');
    $schema=project_progress_schema(config()['db']['prefix']);db()->exec($schema['studio_progress_events']);pp_event(str_repeat('4',32),1,1,1);
    pp_refuse('Unmarked populated partial storage refuses adoption before creating peers',fn()=>project_progress_migrate(),pp_exact(RuntimeException::class,'Unrecognized project progress exists. No data was adopted.'));
    pp_ok('Partial-storage refusal preserves the event and creates no missing table',pp_count('studio_progress_events')===1&&!pp_exists('studio_progress_requests')&&!pp_exists('studio_progress_heads')&&setting('project_progress_schema_version')==='');

    pp_base('progressindex_');db()->exec('ALTER TABLE '.table('studio_jobs').' ADD UNIQUE KEY project_progress_owner(client_id,id)');
    pp_refuse('Migration rejects a malformed named workspace identity index',fn()=>project_progress_migrate(),pp_exact(RuntimeException::class,'Incompatible project progress workspace identity. Existing data was preserved.'));

    pp_base('progressforeign_');db()->exec('ALTER TABLE '.table('studio_jobs').' ADD UNIQUE KEY project_progress_owner(id,client_id)');
    $foreignPrefix=config()['db']['prefix'];$foreignTables=all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=? AND table_name LIKE ?',[$foreignDatabase,$foreignPrefix.'%']);
    if($foreignTables)throw new RuntimeException('Foreign project-progress fixture database is not isolated.');
    try{
        db()->exec('CREATE TABLE `'.$foreignDatabase.'`.`'.$foreignPrefix.'studio_clients` (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
        db()->exec('CREATE TABLE `'.$foreignDatabase.'`.`'.$foreignPrefix.'users` (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
        db()->exec('CREATE TABLE `'.$foreignDatabase.'`.`'.$foreignPrefix.'studio_jobs` (id INT UNSIGNED PRIMARY KEY,client_id INT UNSIGNED NOT NULL,UNIQUE KEY project_progress_owner(id,client_id)) ENGINE=InnoDB');
        $foreignSql=str_replace('REFERENCES `'.$foreignPrefix,'REFERENCES `'.$foreignDatabase.'`.`'.$foreignPrefix,project_progress_schema($foreignPrefix)['studio_progress_events']);
        db()->exec($foreignSql);
        pp_refuse('Validator rejects exact-looking relationships into another database',fn()=>project_progress_validate_schema('studio_progress_events'),pp_exact(RuntimeException::class,'Incompatible project progress relationships. Existing data was preserved.'));
    }finally{
        pp_drop('progressforeign_');
        foreach(['studio_jobs','users','studio_clients'] as $table)db()->exec('DROP TABLE IF EXISTS `'.$foreignDatabase.'`.`'.$foreignPrefix.$table.'`');
    }

    pp_base('progressaudit_');
    $retained=[];$retainedOrder=['users'=>'id','studio_clients'=>'id','staff_profiles'=>'user_id','studio_jobs'=>'id','settings'=>'name','audit'=>'id'];foreach($retainedOrder as $table=>$order)$retained[$table]=all('SELECT * FROM '.table($table).' ORDER BY '.$order);
    db()->exec('CREATE TRIGGER progressaudit_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
    pp_refuse('Activation audit failure rolls back readiness',fn()=>project_progress_migrate(),fn(Throwable $error):bool=>$error instanceof PDOException&&str_contains($error->getMessage(),'Synthetic audit failure'),false);
    foreach(array_keys(project_progress_schema(config()['db']['prefix'])) as $name)project_progress_validate_schema($name);project_progress_validate_workspace_identity();
    $preserved=true;foreach($retained as $table=>$rows)$preserved=$preserved&&all('SELECT * FROM '.table($table).' ORDER BY '.$retainedOrder[$table])===$rows;
    pp_ok('Audit rollback preserves all prior rows and leaves only empty exact additive storage',$preserved&&setting('project_progress_schema_version')===''&&pp_count('studio_progress_events')===0&&pp_count('studio_progress_requests')===0&&pp_count('studio_progress_heads')===0&&(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE action='project.progress.setup'")->fetchColumn()===0);
    db()->exec('DROP TRIGGER progressaudit_fail');project_progress_migrate();
    pp_ok('Verified empty storage recovers through normal activation',setting('project_progress_schema_version')==='1');

    pp_owner(2);pp_refuse('Non-owner administrator cannot prepare storage',fn()=>project_progress_migrate(),pp_exact(RuntimeException::class,'Your account does not have access to this action.'));
    pp_owner(1);$_GET['support']='synthetic';pp_refuse('Support context cannot prepare storage',fn()=>project_progress_migrate(),pp_exact(RuntimeException::class,'Leave client support before preparing project progress.'));unset($_GET['support']);
    $stale=(string)one('SELECT auth_version FROM '.table('users').' WHERE id=1')['auth_version'];$replacement=str_repeat('f',32);
    query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$replacement]);$rotated=pp_snapshot();
    try{$staleResult=pp_stale_worker(config()['db']['prefix'],$stale);pp_ok('Rotated owner session exits before setup and preserves all rotated state',$staleResult===['exit'=>0,'stdout'=>'','stderr'=>'']&&pp_snapshot()===$rotated);}
    finally{query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$stale]);pp_owner();}
    pp_ok('Authority refusals preserve the activated marker and empty storage',setting('project_progress_schema_version')==='1'&&pp_count('studio_progress_events')===0);
}catch(Throwable $error){if(db()->inTransaction())db()->rollBack();$failure=get_class($error).': '.$error->getMessage();}

if(!is_dir(__DIR__.'/../qa'))mkdir(__DIR__.'/../qa',0700,true);
$report=json_encode(['scope'=>'Disposable loopback database storage, migration, composite identity, DDL exclusion, authority, rollback and privacy tests; synthetic records only; no customer message, payment, provider or live operation.','checks'=>$checks,'passed'=>!isset($failure),'failure'=>$failure??null],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
if(file_put_contents(__DIR__.'/../qa/project-progress-schema-native-results.json',$report,LOCK_EX)!==strlen($report)){fwrite(STDERR,"Could not preserve project progress storage report.\n");exit(1);}
foreach($checks as $check)echo ($check['passed']?'PASS ':'FAIL ').$check['name'].PHP_EOL;
if(isset($failure)){fwrite(STDERR,$failure.PHP_EOL);exit(1);}
echo 'Native project progress storage checks: '.count($checks).' passed'.PHP_EOL;
