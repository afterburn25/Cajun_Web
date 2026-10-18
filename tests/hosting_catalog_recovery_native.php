<?php
/** Linux-only continuation of hosting_catalog_native: real SQL and isolated real file recovery. */
declare(strict_types=1);

/** Called only between completed original fixture transactions; never recover in the parent. */
function hcr_phase(string $phase): void {
    static $phases=[],$evidence=null;
    $order=['absent','empty','published','withdrawn'];
    if (($order[count($phases)]??null)!==$phase || db()->inTransaction()) throw new LogicException('Unexpected recovery fixture phase.');
    $before=hcn_state();
    if($evidence===null){$evidence=__DIR__.'/../test-runtime/hosting-recovery-child-output-'.bin2hex(random_bytes(8));if(!mkdir($evidence,0700))throw new RuntimeException('Cannot preserve recovery child output.');}
    $proc=proc_open([PHP_BINARY,__FILE__,'--worker',$phase],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($proc)) throw new RuntimeException('Cannot start isolated recovery worker.');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($proc);
    file_put_contents($evidence.'/'.$phase.'.stdout.json',$out);file_put_contents($evidence.'/'.$phase.'.stderr.log',$err);
    $result=json_decode($out,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($result)||($result['phase']??null)!==$phase) throw new RuntimeException('Recovery worker did not return its exact phase.');
    $restored=hcn_state()===$before;
    $result['checks'][]=['name'=>'Recovery '.$phase.' child preserves the parent database state','passed'=>$restored];
    $result['checks'][]=['name'=>'Recovery '.$phase.' child exits successfully without stderr','passed'=>$status===0&&$err===''];
    $phases[]=$result;$checks=[];foreach($phases as $item)array_push($checks,...$item['checks']);
    $passed=$status===0&&$err===''&&$restored&&!array_filter($checks,static fn($c)=>$c['passed']!==true);
    file_put_contents(__DIR__.'/../qa/hosting-catalog-recovery-native-results.json',json_encode([
        'passed'=>$passed,'complete'=>count($phases)===4,'scope'=>'Actual disposable SQL plus real unchanged structure recovery in newly created isolated sites; no HTTP, upgrade-package or live-provider acceptance.',
        'phases'=>$phases,'checks'=>$checks,
    ],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    if (!$passed) throw new RuntimeException('Recovery '.$phase.' native continuation failed; retained recovery report and child evidence must be inspected.');
}

function hcr_ok(string $name,bool $passed): void {
    $GLOBALS['hcr_checks'][]=['name'=>$name,'passed'=>$passed];
    if (!$passed) throw new RuntimeException($name);
}
function hcr_files(string $root): array {
    $out=[];if(!is_dir($root))throw new RuntimeException('Missing fixture directory.');
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $entry){
        if($entry->isLink())throw new RuntimeException('Unexpected fixture symlink.');
        $name=str_replace('\\','/',substr($entry->getPathname(),strlen($root)+1));
        $out[$name]=$entry->isDir()?['directory'=>true,'mode'=>$entry->getPerms()&0777]:['sha256'=>hash_file('sha256',$entry->getPathname()),'bytes'=>$entry->getSize(),'mode'=>$entry->getPerms()&0777];
    }ksort($out);return $out;
}
function hcr_database(): array {
    $state=[];
    foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ? ORDER BY TABLE_NAME',['hofc_']) as $t){
        if(!preg_match('/\Ahofc_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unexpected disposable table.');
        $rows=array_map(static fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),all('SELECT * FROM `'.$t['name'].'`'));sort($rows,SORT_STRING);
        $state[$t['name']]=['rows'=>$rows,'schema'=>array_values(one('SHOW CREATE TABLE `'.$t['name'].'`'))[1]];
    }return $state;
}
function hcr_state(): array {
    return ['site'=>hcr_files(CWP_ROOT),'journal_and_backups'=>hcr_files($GLOBALS['hcr_recovery']),'database'=>hcr_database(),'private'=>hcr_files(config()['storage'])];
}
function hcr_digest(array $value): string {return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
function hcr_session(): void {
    $_SESSION=['uid'=>1,'auth_version'=>$GLOBALS['hcr_owner']['auth_version'],'login_at'=>time(),'last_activity'=>time()];
}
function hcr_call(string $password='Synthetic hosting catalog owner'): array {
    return hosting_catalog_recover_files($GLOBALS['hcr_recovery'],1,$GLOBALS['hcr_owner']['auth_version'],$password);
}
/** Only the exact expected policy refusal qualifies; SQL/runtime accidents do not count. */
function hcr_refusal(string $name,string $message,?callable $call=null): void {
    $before=hcr_state();$caught=null;
    try{($call??'hcr_call')();}catch(RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;$caught=$e;}
    $after=hcr_state();
    hcr_ok($name,$caught!==null&&$caught->getMessage()===$message&&$after===$before);
    $GLOBALS['hcr_refusals'][]=['name'=>$name,'before_sha256'=>hcr_digest($before),'after_sha256'=>hcr_digest($after),'exception'=>$caught->getMessage()];
}
function hcr_pdo(): PDO {
    $c=$GLOBALS['cwp_config']['db'];
    return new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['name'].';charset=utf8mb4',$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>8]);
}
function hcr_lock_key(): string {return 'cwp-staff-'.substr(hash('sha256','cwp_test|hofc_'),0,40);}
/** Observes real statement execution; never substitutes SQL, results or parameters. */
final class HostingRecoveryStatement extends PDOStatement {
    protected function __construct() {}
    public function execute(?array $params=null): bool {
        if(($GLOBALS['hcr_observe_lock']??false)&&str_contains($this->queryString,'GET_LOCK('))$GLOBALS['hcr_lock_attempts']++;
        return parent::execute($params);
    }
}

/** Separate SQL connection watches real, in-progress filesystem restoration; no production hook. */
function hcr_watch(string $root,int $connection): never {
    $pdo=hcr_pdo();$owner=$pdo->prepare('SELECT IS_USED_LOCK(?)');$acquire=$pdo->prepare('SELECT GET_LOCK(?,0)');$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');
    $observations=0;$failures=[];$deadline=microtime(true)+60;
    file_put_contents($root.'/watch-ready','ready',LOCK_EX);
    while(!is_file($root.'/watch-done')&&microtime(true)<$deadline){
        clearstatcache();
        $journal=@file_get_contents($root.'/recovery/journal.php');
        if($journal!==false&&str_starts_with($journal,CWP_GUARD)){
            $j=json_decode(substr($journal,strlen(CWP_GUARD)),true);
            if(is_array($j)&&isset($j['last_restored'])&&$j['state']!=='rolled-back'&&@file_get_contents($root.'/site/.htaccess')===structure_maintenance()){
                $owner->execute([hcr_lock_key()]);$row=[$owner->fetchColumn()];$acquire->execute([hcr_lock_key()]);$row[]=$acquire->fetchColumn();
                if((int)$row[1]===1)$release->execute([hcr_lock_key()]);
                // Ignore a sample which crossed final policy restoration/lock release.
                if(@file_get_contents($root.'/site/.htaccess')===structure_maintenance()&&!is_file($root.'/watch-done')){
                    $observations++;if((int)$row[0]!==$connection||(int)$row[1]!==0)$failures[]='Staff lock was not retained during actual restoration.';
                }
            }
        }usleep(1000);
    }
    echo json_encode(['observations'=>$observations,'failures'=>$failures,'finished'=>is_file($root.'/watch-done')],JSON_THROW_ON_ERROR);exit(0);
}

if (realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__) return;
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||!in_array($argv[1]??'',['--worker','--watch'],true))exit(2);
$phase=$argv[2]??'';
if(!in_array($phase,['absent','empty','published','withdrawn'],true))exit(2);
$code=realpath(__DIR__.'/../site');$runtime=realpath(__DIR__.'/../test-runtime');
if(!$code||!$runtime)exit(2);
if($argv[1]==='--watch'){
    $root=realpath($argv[3]??'');
    if(!$root||dirname($root)!==$runtime||!preg_match('/\Ahosting-recovery-'.$phase.'-[a-f0-9]{16}\z/',basename($root)))exit(2);
}else{
    $root=$runtime.'/hosting-recovery-'.$phase.'-'.bin2hex(random_bytes(8));
    if(file_exists($root)||!mkdir($root,0700)||!mkdir($root.'/site',0700)||!mkdir($root.'/recovery',0700)||!mkdir($root.'/payload',0700))exit(2);
}
// This is the safety boundary: bootstrap cannot ever select checkout/site as installed root.
define('CWP_BOOT',true);define('CWP_CODE_ROOT',$code);define('CWP_ROOT',realpath($root.'/site'));
if(!CWP_ROOT||CWP_ROOT===$code||dirname(CWP_ROOT)!==$root)exit(2);
require CWP_CODE_ROOT.'/includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/app/hosting/catalog_recovery.php';
$private=realpath(__DIR__.'/../test-runtime/hosting-catalog-private');if(!$private)exit(2);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/hosting-catalog','storage'=>$private,'key'=>str_repeat('synthetic-hosting-catalog-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hofc_']];
if($argv[1]==='--watch')hcr_watch($root,(int)($argv[4]??0));
$GLOBALS['hcr_checks']=[];$GLOBALS['hcr_refusals']=[];$GLOBALS['hcr_recovery']=$root.'/recovery';$lock=null;$watch=null;$diagnostics=[];$failure=null;
try{
    if(!db()->setAttribute(PDO::ATTR_STATEMENT_CLASS,[HostingRecoveryStatement::class]))throw new RuntimeException('Native statement observer unavailable.');
    $owner=one('SELECT * FROM '.table('users').' WHERE id=1');
    if(!$owner||!password_verify('Synthetic hosting catalog owner',$owner['password_hash']))throw new RuntimeException('Expected synthetic owner is unavailable.');
    $GLOBALS['hcr_owner']=$owner;hcr_session();
    // Production updater creates all backups/journal bytes; synthetic payload is never executable.
    $old=['config/defaults.php'=>"<?php // synthetic previous version\n",'.htaccess'=>"# synthetic old policy\nOptions -Indexes\n",'index.php'=>"<?php // synthetic old entry\n"];
    $new=['config/defaults.php'=>"<?php // synthetic current version\n",'.htaccess'=>"# synthetic new policy\nOptions -Indexes\n",'index.php'=>"<?php // synthetic new entry\n"];
    for($i=0;$i<128;$i++){$name='assets/recovery-'.str_pad((string)$i,3,'0',STR_PAD_LEFT).'.txt';$old[$name]=str_repeat('old-'.$i."\n",128);$new[$name]=str_repeat('new-'.$i."\n",128);}
    $old['assets/retired.txt']='retired original';$new['assets/added.txt']='newly added';
    foreach([CWP_ROOT=>$old,$root.'/payload'=>$new] as $dir=>$files)foreach($files as $name=>$bytes){@mkdir(dirname($dir.'/'.$name),0755,true);file_put_contents($dir.'/'.$name,$bytes);}
    file_put_contents(CWP_ROOT.'/config.php',"<?php // isolated synthetic installed configuration; no credentials\n");
    file_put_contents(CWP_ROOT.'/.installed.php',"<?php exit; // isolated installed marker\n");
    $original=hcr_files(CWP_ROOT);$manifest=['version'=>CWP_VERSION,'source_commit'=>str_repeat('a',40),'files'=>array_map(static fn($v)=>hash('sha256',$v),$new),'shims'=>[]];
    $history=['synthetic-prior'=>['files'=>array_map(static fn($v)=>hash('sha256',$v),$old)]];
    $lock=structure_lock($GLOBALS['hcr_recovery']);$applied=structure_apply($root.'/payload',$manifest,$history,$GLOBALS['hcr_recovery']);
    hcr_ok('Recovery '.$phase.' worker stages real backups under an isolated installed root',$applied['state']==='complete'&&$applied['root']===CWP_ROOT&&CWP_ROOT!==CWP_CODE_ROOT&&count($applied['operations'])>=132);
    $entry=hcr_state();$authority='Your current owner account and password are required for file recovery.';
    $namespace='Hosting offer storage is incomplete or unrecognized. Keep the current files and restore or review its original history before file recovery.';
    $damaged='Hosting offer history needs recovery. Restore its original tables, settings and audit records.';
    $historyMessage='Published hosting offers need the current website files to preserve their history and images. Automatic file recovery is blocked. Restore a matching backup of website files, database and private storage with your hosting provider if recovery is needed.';
    if(in_array($phase,['absent','empty'],true)){
        $known=array_filter(hosting_catalog_tables(),static fn($n)=>hosting_catalog_exists($n));
        hcr_ok('Recovery '.$phase.' phase has exact intended SQL history',($phase==='absent'?count($known)===0:count($known)===4&&(int)query('SELECT COUNT(*) FROM '.table('hosting_catalog_publications'))->fetchColumn()===0));
        // The partial/unknown tables are test-owned and dropped exactly, never generalized cleanup.
        $unknown='hofc_hosting_catalog_future';db()->exec('CREATE TABLE `'.$unknown.'` (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
        try{hcr_refusal('Recovery '.$phase.' refuses unknown empty hosting namespace without changes',$namespace);}finally{db()->exec('DROP TABLE `'.$unknown.'`');}
        if($phase==='absent'){
            $schema=hosting_catalog_schema('hofc_');$first=array_key_first($schema);db()->exec($schema[$first]);
            try{hcr_refusal('Recovery absent refuses partial empty known storage without changes',$namespace);}finally{db()->exec('DROP TABLE '.table($first));}
        }else{
            hcr_refusal('Recovery refuses an incorrect current owner password without changes',$authority,fn()=>hcr_call('wrong'));
            hcr_refusal('Recovery refuses a different captured owner identity without changes',$authority,fn()=>hosting_catalog_recover_files($GLOBALS['hcr_recovery'],2,$GLOBALS['hcr_owner']['auth_version'],'Synthetic hosting catalog owner'));
            foreach(['role','enabled','auth_version','password_hash','cached_staff_marker'] as $case){
                hcr_session();$profile=one('SELECT * FROM '.table('staff_profiles').' WHERE user_id=1');$row=one('SELECT * FROM '.table('users').' WHERE id=1');
                if($case==='cached_staff_marker')$GLOBALS['cwp_settings']['staff_schema_version']='';
                else{unset($GLOBALS['cwp_settings']);if(!admin_user())throw new RuntimeException('Owner was not initially current.');}
                if($case==='role'||$case==='cached_staff_marker')query('UPDATE '.table('staff_profiles')." SET role='administrator' WHERE user_id=1");
                elseif($case==='enabled')query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');
                elseif($case==='auth_version')query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[str_repeat('e',32)]);
                else query('UPDATE '.table('users').' SET password_hash=? WHERE id=1',[password_hash('Different synthetic password',PASSWORD_DEFAULT)]);
                try{hcr_refusal('Recovery rechecks '.$case.' after prior owner admission without changes',$authority);}
                finally{query('UPDATE '.table('staff_profiles').' SET role=?,enabled=? WHERE user_id=1',[$profile['role'],$profile['enabled']]);query('UPDATE '.table('users').' SET auth_version=?,password_hash=? WHERE id=1',[$row['auth_version'],$row['password_hash']]);unset($GLOBALS['cwp_settings']);hcr_session();}
            }
            $other=hcr_pdo();$s=$other->prepare('SELECT GET_LOCK(?,0)');$s->execute([hcr_lock_key()]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('Cannot own competing lock for transaction refusal.');
            db()->beginTransaction();
            $GLOBALS['hcr_lock_attempts']=0;$GLOBALS['hcr_observe_lock']=true;
            try{hcr_refusal('Recovery refuses inherited transaction before waiting for another owner lock','File recovery cannot run inside a database transaction.');hcr_ok('Recovery inherited transaction remains owned and makes no GET_LOCK attempt',db()->inTransaction()&&$GLOBALS['hcr_lock_attempts']===0);}
            finally{$GLOBALS['hcr_observe_lock']=false;if(db()->inTransaction())db()->rollBack();$s=$other->prepare('SELECT RELEASE_LOCK(?)');$s->execute([hcr_lock_key()]);}
            foreach(['hosting_catalog_version','hosting_catalog_publications_count'] as $key){
                $saved=one('SELECT value FROM '.table('settings').' WHERE name=?',[$key]);
                if($key==='hosting_catalog_version')query('DELETE FROM '.table('settings').' WHERE name=?',[$key]);else save_setting($key,'1');
                try{hcr_refusal('Recovery empty refuses damaged '.$key.' without changes',$damaged);}finally{save_setting($key,$saved['value']);}
            }
        }
        hcr_ok('Recovery '.$phase.' negative probes restore exact rows and files',hcr_state()===$entry);
        $connection=(int)query('SELECT CONNECTION_ID()')->fetchColumn();
        $watch=proc_open([PHP_BINARY,__FILE__,'--watch',$phase,$root,(string)$connection],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$wp);
        if(!is_resource($watch))throw new RuntimeException('Cannot start actual second-connection observer.');fclose($wp[0]);
        $deadline=microtime(true)+10;while(!is_file($root.'/watch-ready')&&microtime(true)<$deadline)usleep(1000);
        if(!is_file($root.'/watch-ready'))throw new RuntimeException('Second-connection observer did not become ready.');
        try{$recovered=hcr_call();}finally{file_put_contents($root.'/watch-done','done',LOCK_EX);}
        $watchOut=stream_get_contents($wp[1]);$watchErr=stream_get_contents($wp[2]);fclose($wp[1]);fclose($wp[2]);$watchStatus=proc_close($watch);$watch=null;$w=json_decode($watchOut,true,16,JSON_THROW_ON_ERROR);
        hcr_ok('Recovery '.$phase.' holds the actual staff mutex during file restoration',$watchStatus===0&&$watchErr===''&&$w['finished']===true&&$w['observations']>0&&$w['failures']===[]);
        $other=hcr_pdo();$s=$other->prepare('SELECT GET_LOCK(?,0)');$s->execute([hcr_lock_key()]);$released=(int)$s->fetchColumn()===1;
        try{hcr_ok('Recovery '.$phase.' releases the staff mutex after restoration',$released);}finally{if($released){$s=$other->prepare('SELECT RELEASE_LOCK(?)');$s->execute([hcr_lock_key()]);}}
        hcr_ok('Recovery '.$phase.' restores exact files and server policy with real recovery',$recovered['state']==='rolled-back'&&structure_journal($GLOBALS['hcr_recovery'])===$recovered&&hcr_files(CWP_ROOT)===$original);
        hcr_ok('Recovery '.$phase.' leaves SQL and private media unchanged',hcr_database()===$entry['database']&&hcr_files(config()['storage'])===$entry['private']);
        $diagnostics['lock_observer']=$w;
    }else{
        $publications=(int)query('SELECT COUNT(*) FROM '.table('hosting_catalog_publications'))->fetchColumn();$withdrawals=(int)query('SELECT COUNT(*) FROM '.table('hosting_catalog_withdrawals'))->fetchColumn();
        hcr_ok('Recovery '.$phase.' phase uses actual publication history',$publications===1&&$withdrawals===($phase==='withdrawn'?1:0));
        hcr_refusal('Recovery '.$phase.' history blocks file recovery with every byte and row unchanged',$historyMessage);
        if($phase==='published'){
            $saved=one('SELECT document_sha256 FROM '.table('hosting_catalog_publications').' LIMIT 1');query('UPDATE '.table('hosting_catalog_publications').' SET document_sha256=?',[str_repeat('0',64)]);
            try{hcr_refusal('Recovery refuses damaged established publication without changing files or rows',$damaged);}finally{query('UPDATE '.table('hosting_catalog_publications').' SET document_sha256=?',[$saved['document_sha256']]);}
            hcr_ok('Recovery damaged publication probe restores original SQL and filesystem state',hcr_state()===$entry);
        }
    }
    $diagnostics+=['php'=>PHP_VERSION,'database_version'=>(string)query('SELECT VERSION()')->fetchColumn(),'pdo_driver'=>db()->getAttribute(PDO::ATTR_DRIVER_NAME),'isolated_root'=>basename($root).'/site','recovery_directory'=>basename($root).'/recovery'];
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();$GLOBALS['hcr_checks'][]=['name'=>'Unexpected recovery '.$phase.' native failure: '.$failure,'passed'=>false];}
finally{
    if(is_resource($watch)){file_put_contents($root.'/watch-done','done',LOCK_EX);proc_terminate($watch);foreach($wp??[] as $p)if(is_resource($p))fclose($p);proc_close($watch);}
    if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}
}
$diagnostics['source_files']=['adapter'=>hash_file('sha256',CWP_CODE_ROOT.'/app/hosting/catalog_recovery.php'),'structure'=>hash_file('sha256',CWP_CODE_ROOT.'/app/updates/structure.php'),'bootstrap'=>hash_file('sha256',CWP_CODE_ROOT.'/includes/bootstrap.php')];
$passed=!array_filter($GLOBALS['hcr_checks'],static fn($c)=>$c['passed']!==true);
echo json_encode(['phase'=>$phase,'passed'=>$passed,'checks'=>$GLOBALS['hcr_checks'],'refusals'=>$GLOBALS['hcr_refusals'],'runtime'=>$diagnostics,'failure'=>$failure],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);exit($passed?0:1);
