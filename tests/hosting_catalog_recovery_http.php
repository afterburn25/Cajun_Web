<?php
/** Opt-in phase bridge and CLI-only packaged recovery fixture. Never a web endpoint. */
declare(strict_types=1);

function hch_phase(string $phase): void {
    static $phases=[];
    if ((['absent','empty','published','withdrawn'][count($phases)]??null)!==$phase || db()->inTransaction()) throw new LogicException('Unexpected HTTP recovery phase.');
    $before=hcn_state();
    // Keep the lexical venv path: resolving Ubuntu's interpreter symlink bypasses pyvenv.cfg.
    $python=__DIR__.'/../.ci-venv/bin/python';
    if (!is_file($python) || !is_executable($python) || !is_file(__DIR__.'/../.ci-venv/pyvenv.cfg') || PHP_OS_FAMILY==='Windows') throw new RuntimeException('Packaged recovery HTTP requires the Linux UI environment.');
    $proc=proc_open([$python,'-B',__DIR__.'/hosting_catalog_recovery_http.py',$phase],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc))throw new RuntimeException('Cannot start HTTP recovery phase.');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($proc);
    $path=__DIR__.'/../qa/hosting-catalog-recovery-http/'.$phase.'/results.json';
    $result=is_file($path)?json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR):null;
    $restored=hcn_state()===$before;
    $checks=is_array($result)?($result['checks']??[]):[];
    $checks[]=['name'=>'HTTP recovery '.$phase.' child preserves every parent database row','passed'=>$restored];
    $checks[]=['name'=>'HTTP recovery '.$phase.' child exits successfully without console data','passed'=>$status===0&&$out===''&&$err===''];
    $phases[]=['phase'=>$phase,'report_sha256'=>is_file($path)?hash_file('sha256',$path):null,'checks'=>$checks,
        'child'=>['exit_code'=>$status,'stdout_bytes'=>strlen($out),'stdout_sha256'=>hash('sha256',$out),'stderr_bytes'=>strlen($err),'stderr_sha256'=>hash('sha256',$err)]];
    $all=[];foreach($phases as $item)array_push($all,...$item['checks']);
    $passed=$result!==null&&($result['phase']??null)===$phase&&($result['passed']??false)===true&&!array_filter($all,static fn($c)=>($c['passed']??null)!==true);
    file_put_contents(__DIR__.'/../qa/hosting-catalog-recovery-http-results.json',json_encode([
        'passed'=>$passed,'complete'=>count($phases)===4,'phases'=>$phases,'checks'=>$all,
        'scope'=>'Real same-source packaged owner-update HTTP with synthetic complete journals in isolated sites. Not a published-baseline upgrade, interruption or release-acceptance result.',
    ],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    if(!$passed)throw new RuntimeException('Packaged HTTP recovery phase failed; inspect its retained finite report.');
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__)return;
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOSTING_RECOVERY_HTTP')!=='1')exit(2);
$op=$argv[1]??'';$phase=$argv[2]??'';
if(!in_array($op,['prepare','state','demote','restore-role','damage','restore-damage','cleanup'],true)||!in_array($phase,['absent','empty','published','withdrawn'],true))exit(2);
set_exception_handler(static function(Throwable $e)use($op):never{
    $class=get_class($e);
    fwrite(STDERR,json_encode(['operation'=>$op,'class'=>preg_match('/\A[A-Za-z_\\\\][A-Za-z0-9_\\\\]{0,119}\z/',$class)?$class:'Throwable',
        'message'=>$e instanceof PDOException?'Disposable SQL operation failed.':'Packaged recovery fixture precondition failed.',
        'file'=>basename($e->getFile()),'line'=>$e->getLine()],JSON_THROW_ON_ERROR).PHP_EOL);exit(1);
});
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool{
    if(!(error_reporting()&$severity))return false;
    throw new ErrorException('Disposable fixture PHP diagnostic.',0,$severity,$file,$line);
});
$checkout=realpath(__DIR__.'/..');$expected=$checkout.'/test-runtime/hosting-recovery-http/'.$phase;
foreach([$checkout.'/test-runtime',$checkout.'/test-runtime/hosting-recovery-http',$expected]as $path)if(is_link($path))exit(2);
$base=realpath($expected);
if(!$base||$base!==$expected||dirname($base)!==$checkout.'/test-runtime/hosting-recovery-http')exit(2);
$site=realpath($base.'/site');$private=realpath($base.'/private');
if(!$site||!$private||dirname($site)!==$base||dirname($private)!==$base||$site===realpath(__DIR__.'/../site'))exit(2);
$stages=glob($site.'/cwp-update-*');if(count($stages)!==1||is_link($stages[0]))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$site);define('CWP_CODE_ROOT',$stages[0].'/site');
require CWP_CODE_ROOT.'/includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/app/hosting/catalog_recovery.php';
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8092','storage'=>$private,'key'=>str_repeat('synthetic-hosting-catalog-',4),
    'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'hofc_']];

function hch_rows(): array {
    $rows=[];
    foreach(all('SELECT TABLE_NAME AS name FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,5)=BINARY ? ORDER BY TABLE_NAME',['hofc_'])as $t){
        if(!preg_match('/\Ahofc_[a-z0-9_]+\z/',$t['name']))throw new LogicException('Unexpected disposable table.');
        $list=all('SELECT * FROM `'.$t['name'].'`');usort($list,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$t['name']]=$list;
    }return $rows;
}
function hch_hash(array $value): string {return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
function hch_schemas(array $rows): array {
    $schemas=[];
    foreach(array_keys($rows)as $name)$schemas[$name]=preg_replace('/ AUTO_INCREMENT=\d+\b/','',array_values(one('SHOW CREATE TABLE `'.$name.'`'))[1]);
    return $schemas;
}
function hch_stable(array $rows): array {
    unset($rows['hofc_audit'],$rows['hofc_rate_limits']);
    foreach($rows['hofc_users']as &$u)$u['last_login']=null;unset($u);
    return $rows;
}
/** Only owner-login timestamp, the exact loopback owner rate bucket and appended updater audit are allowed. */
function hch_observe(array $saved): array {
    $rows=hch_rows();$now=time();if($now-$saved['epoch']>900)throw new RuntimeException('Fixture phase exceeded its side-effect window.');
    $oldUsers=array_column($saved['rows']['hofc_users'],null,'id');$login=0;
    foreach($rows['hofc_users']as $u){$old=$oldUsers[$u['id']]??null;if(!$old)throw new RuntimeException('Unexpected user.');
        if($u['last_login']!==$old['last_login']){
            $epoch=strtotime((string)$u['last_login'].' UTC');
            if((int)$u['id']!==1||$epoch===false||$epoch<$saved['epoch']||$epoch>$now)throw new RuntimeException('Unexpected login change.');$login++;
        }
    }
    $oldAudit=array_column($saved['rows']['hofc_audit'],null,'id');$actions=[];$newAudit=[];
    foreach($rows['hofc_audit']as $row){
        if(isset($oldAudit[$row['id']])){if($oldAudit[$row['id']]!==$row)throw new RuntimeException('Original audit changed.');unset($oldAudit[$row['id']]);continue;}
        if((int)$row['user_id']!==1||!in_array($row['action'],['structure.inspect','structure.recover'],true)
            ||$row['detail']!=='Version '.CWP_VERSION.'; state '.($row['action']==='structure.inspect'?'complete':'rolled-back'))throw new RuntimeException('Unexpected updater audit.');
        $actions[]=$row['action'];$newAudit[]=$row['id'];
    }if($oldAudit)throw new RuntimeException('Original audit removed.');sort($actions);
    $oldRates=array_column($saved['rows']['hofc_rate_limits'],null,'bucket');$attempts=0;$changed=[];
    $key=hash_hmac('sha256','structure-owner|127.0.0.1',config()['key']);$allowed=[];
    for($window=(int)floor($saved['epoch']/900);$window<=(int)floor($now/900);$window++)$allowed[]=hash('sha256',$key.'|'.$window);
    foreach($rows['hofc_rate_limits']as $r){$old=$oldRates[$r['bucket']]??null;unset($oldRates[$r['bucket']]);if($r===$old)continue;
        if(!in_array($r['bucket'],$allowed,true)||(int)$r['attempts']<=(int)($old['attempts']??0)
            ||($old!==null&&$r['expires']!==$old['expires'])||($old===null&&((int)$r['expires']<$saved['epoch']+900||(int)$r['expires']>$now+900)))throw new RuntimeException('Unexpected rate change.');
        $attempts+=(int)$r['attempts']-(int)($old['attempts']??0);$changed[$r['bucket']]=$old;
    }if($oldRates)throw new RuntimeException('Original rate row removed.');
    return ['stable_sha256'=>hch_hash(hch_stable($rows)),'schema_sha256'=>hch_hash(hch_schemas($rows)),'rows_sha256'=>hch_hash($rows),'rate_attempts'=>$attempts,'audit_actions'=>$actions,'owner_login_changed'=>$login===1,
        '_new_audit'=>$newAudit,'_changed_rates'=>$changed];
}
$savedFile=$base.'/fixture-state.php';
if($op==='prepare'){
    if(file_exists($savedFile)||file_exists(CWP_ROOT.'/config.php'))throw new RuntimeException('Preserve prior fixture state.');
    $owner=one('SELECT * FROM '.table('users').' WHERE id=1');
    if(!$owner||$owner['email']!=='hosting-staff1@example.invalid'||!password_verify('Synthetic hosting catalog owner',$owner['password_hash']))throw new RuntimeException('Expected owner absent.');
    $ready=staff_locked(fn()=>hosting_catalog_ready());
    $pub=$ready?(int)query('SELECT COUNT(*) FROM '.table('hosting_catalog_publications'))->fetchColumn():0;
    $withdraw=$ready?(int)query('SELECT COUNT(*) FROM '.table('hosting_catalog_withdrawals'))->fetchColumn():0;
    if($ready!==($phase!=='absent')||$pub!== (in_array($phase,['published','withdrawn'],true)?1:0)||$withdraw!==($phase==='withdrawn'?1:0))throw new RuntimeException('Wrong native phase.');
    $rows=hch_rows();$saved=['epoch'=>time(),'rows'=>$rows,'schemas'=>hch_schemas($rows),'profile'=>one('SELECT * FROM '.table('staff_profiles').' WHERE user_id=1'),
        'publication'=>$pub?one('SELECT id,document_sha256 FROM '.table('hosting_catalog_publications').' LIMIT 1'):null];
    file_put_contents($savedFile,CWP_GUARD.json_encode($saved,JSON_THROW_ON_ERROR));chmod($savedFile,0600);
    file_put_contents(CWP_ROOT.'/config.php','<?php return '.var_export(config(),true).';');chmod(CWP_ROOT.'/config.php',0600);
    file_put_contents(CWP_ROOT.'/.installed.php',CWP_GUARD.'synthetic installed guard');
    $actual=require $stages[0].'/manifest.php';
    // 100 unchanged real package files satisfy the unchanged production minimum; only inert probes and policy differ.
    $names=array_values(array_unique(array_merge(['config/defaults.php','.htaccess'],array_slice(array_keys($actual['files']),0,100))));
    $old=[];$new=[];$payload=$base.'/synthetic-payload';if(!mkdir($payload,0700))throw new RuntimeException('Cannot create synthetic payload.');
    foreach($names as $name)$old[$name]=$new[$name]=file_get_contents(CWP_CODE_ROOT.'/'.$name);
    $old['assets/recovery-http-retired.txt']='synthetic retained original';$old['assets/recovery-http-changed.txt']='synthetic before';
    $new['assets/recovery-http-changed.txt']='synthetic after';$new['assets/recovery-http-added.txt']='synthetic addition';$new['.htaccess'].="\n# Synthetic recovery HTTP policy probe\n";
    foreach($old as $name=>$bytes)if(str_starts_with($name,'assets/recovery-http-'))file_put_contents(CWP_ROOT.'/'.$name,$bytes);
    foreach($new as $name=>$bytes){if(!is_dir(dirname($payload.'/'.$name)))mkdir(dirname($payload.'/'.$name),0755,true);file_put_contents($payload.'/'.$name,$bytes);}
    $manifest=['version'=>CWP_VERSION,'source_commit'=>$actual['source_commit'],'files'=>array_map(static fn($b)=>hash('sha256',$b),$new),'shims'=>[]];
    $history=['synthetic-same-source'=>['files'=>array_map(static fn($b)=>hash('sha256',$b),$old)]];
    $dir=structure_store();$lock=structure_lock($dir);
    try{$journal=structure_apply($payload,$manifest,$history,$dir);}finally{flock($lock,LOCK_UN);fclose($lock);}
    if($journal['state']!=='complete'||count($journal['operations'])!==4)throw new RuntimeException('Synthetic journal did not complete.');
    echo json_encode(['prepared'=>true,'phase'=>$phase,'php'=>PHP_VERSION,'pdo_driver'=>db()->getAttribute(PDO::ATTR_DRIVER_NAME),'database_version'=>query('SELECT VERSION()')->fetchColumn(),
        'source'=>$actual['source_commit'],'version'=>CWP_VERSION,'journal'=>'complete','operations'=>4,'original_files'=>array_map(static fn($b)=>hash('sha256',$b),$old),'added'=>'assets/recovery-http-added.txt'],JSON_THROW_ON_ERROR);exit;
}
$raw=file_get_contents($savedFile);if(!str_starts_with($raw,CWP_GUARD))throw new RuntimeException('Unrecognized fixture state.');$saved=json_decode(substr($raw,strlen(CWP_GUARD)),true,64,JSON_THROW_ON_ERROR);
if($op==='demote'||$op==='restore-role'){
    if($phase!=='empty')throw new RuntimeException('Role probe phase differs.');
    staff_locked(fn()=>query('UPDATE '.table('staff_profiles').' SET role=? WHERE user_id=1',[$op==='demote'?'administrator':$saved['profile']['role']]));echo '{"changed":true}';exit;
}
if($op==='damage'||$op==='restore-damage'){
    if($phase!=='published'||!$saved['publication'])throw new RuntimeException('Damage probe phase differs.');
    staff_locked(fn()=>query('UPDATE '.table('hosting_catalog_publications').' SET document_sha256=? WHERE id=?',[$op==='damage'?str_repeat('0',64):$saved['publication']['document_sha256'],$saved['publication']['id']]));echo '{"changed":true}';exit;
}
$state=hch_observe($saved);
if($op==='cleanup'){
    if($state['stable_sha256']!==hch_hash(hch_stable($saved['rows']))||$state['schema_sha256']!==hch_hash($saved['schemas']))throw new RuntimeException('Do not erase unexpected business changes.');
    staff_locked(function()use($saved,$state):void{staff_transaction(function()use($saved,$state):void{
        $owner=array_column($saved['rows']['hofc_users'],null,'id')[1];query('UPDATE '.table('users').' SET last_login=? WHERE id=1',[$owner['last_login']]);
        foreach($state['_new_audit']as $id)query('DELETE FROM '.table('audit').' WHERE id=?',[$id]);
        foreach($state['_changed_rates']as $bucket=>$row){if($row===null)query('DELETE FROM '.table('rate_limits').' WHERE bucket=?',[$bucket]);else query('UPDATE '.table('rate_limits').' SET attempts=?,expires=? WHERE bucket=?',[$row['attempts'],$row['expires'],$bucket]);}
    });});
    if(hch_rows()!==$saved['rows'])throw new RuntimeException('Parent rows were not exactly restored.');echo '{"restored":true}';exit;
}
unset($state['_new_audit'],$state['_changed_rates']);$journal=structure_journal(structure_store());$state['journal']=$journal['state'];
echo json_encode($state,JSON_THROW_ON_ERROR);
