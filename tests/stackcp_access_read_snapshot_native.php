<?php
/** Disposable PDO profiling/equivalence. No provider transport and no production query hooks. */
declare(strict_types=1);
if (PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test') exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/includes/layout/admin.php';
require_once __DIR__.'/../site/app/providers/stackcp_access_views.php';
use CajunWebPro\Foundation\Provider\TransportResponse;
use CajunWebPro\Foundation\TwentyIInventory\InventoryProjector;
use CajunWebPro\Foundation\TwentyIStackCpAccess\{StackCpAccessBinding,StackCpAccessObservation};

$private=__DIR__.'/../test-runtime/stackcp-access-read-private';
@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-access-read-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'sapr_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$metrics=[];$runtime=null;$password='Synthetic access snapshot owner passphrase';
$general='SYNTHETIC_ACCESS_SNAPSHOT_GENERAL_KEY';$secret='SYNTHETIC_ACCESS_SNAPSHOT_AUTH_KEY';

function sap_ok(string $name,bool $passed):void {
    global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if (!$passed) throw new RuntimeException($name);
}
function sap_actor(int $id=1):void {
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time(),'csrf'=>str_repeat('a',64)];
}
function sap_state():string {
    $rows=[];
    foreach (['users','staff_profiles','studio_clients','settings','audit','provider_profiles','provider_revisions','provider_refreshes','provider_refresh_results','provider_observation_packages','provider_package_links','provider_package_link_events','provider_stackcp_auth','provider_stackcp_assignments','provider_stackcp_reads','provider_stackcp_observations'] as $name) {
        $order=match($name){'settings'=>'name','staff_profiles'=>'user_id','provider_observation_packages'=>'refresh_id,package_id',default=>'id'};
        $rows[$name]=provider_stackcp_access_exists($name)?all('SELECT * FROM '.table($name).' ORDER BY '.$order):null;
    }
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
function sap_lock_key():string {return 'cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);}
function sap_released():bool {return !db()->inTransaction()&&query('SELECT IS_FREE_LOCK(?)',[sap_lock_key()])->fetchColumn()==1;}
function sap_restore(string $name,array $row):void {
    query('INSERT INTO '.table($name).' ('.implode(',',array_map(fn($k)=>'`'.$k.'`',array_keys($row))).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
}
/** Test connection only. Counters contain categories/counts, never SQL, values or keys. */
final class StackCpAccessProfileStatement extends PDOStatement {
    protected function __construct() {}
    public function execute(?array $params=null):bool {
        $sql=$this->queryString;
        if (str_starts_with($sql,'SELECT package_id FROM')&&($GLOBALS['sap_lock_probe']??false)) {
            $GLOBALS['sap_lock_probe']=false;$GLOBALS['sap_lock_probe_result']=sap_finish(sap_worker('try-lock'));
        }
        if (str_starts_with($sql,'SELECT package_id FROM')&&($GLOBALS['sap_sql_fault']??false)) {
            $GLOBALS['sap_sql_fault']=false;db()->exec('SELECT * FROM `sapr_deliberately_absent`');
        }
        if (!($GLOBALS['sap_record']??false)) return parent::execute($params);
        $category=str_contains($sql,'GET_LOCK(')||str_contains($sql,'RELEASE_LOCK(')||str_contains($sql,'IS_USED_LOCK(')?'mutex':(str_contains($sql,'information_schema')||str_starts_with($sql,'SHOW ')?'schema':(str_contains($sql,'`sapr_audit`')?'audit':'data'));
        $start=hrtime(true);
        try {return parent::execute($params);} finally {
            $elapsed=(hrtime(true)-$start)/1e9;$GLOBALS['sap_profile']['statements']++;
            $GLOBALS['sap_profile']['categories'][$category]['count']=($GLOBALS['sap_profile']['categories'][$category]['count']??0)+1;
            $GLOBALS['sap_profile']['categories'][$category]['seconds']=($GLOBALS['sap_profile']['categories'][$category]['seconds']??0)+$elapsed;
            foreach (['auth'=>'provider_stackcp_auth','assignment'=>'provider_stackcp_assignments','started'=>'provider_stackcp_reads','finished'=>'provider_stackcp_observations'] as $kind=>$table) {
                if ($sql==='SHOW FULL COLUMNS FROM '.table($table)) $GLOBALS['sap_profile']['column_inventories'][$kind]=($GLOBALS['sap_profile']['column_inventories'][$kind]??0)+1;
            }
            if (!preg_match('/\A(?:SELECT|SHOW)\b/',$sql)) $GLOBALS['sap_profile']['non_read_statements']++;
        }
    }
}
function sap_status_counters():array {
    $wanted=['Questions','Com_select','Com_show_fields','Com_show_keys','Com_show_status','Com_stmt_execute','Com_begin','Com_commit','Com_rollback','Handler_read_key','Handler_read_rnd_next'];$out=[];
    foreach (all('SHOW SESSION STATUS') as $r) if (in_array($r['Variable_name'],$wanted,true)) $out[$r['Variable_name']]=(int)$r['Value'];
    if (!isset($out['Questions'],$out['Com_select'])) throw new RuntimeException('Required SQL session counters are unavailable.');
    return $out;
}
/** Exact old functions/class, with identifier-only namespacing for side-by-side execution. */
function sap_load_original():void {
    $raw=file_get_contents(__DIR__.'/fixtures/stackcp_access_read_original.json');
    if (!hash_equals('2c7e731489c862f021514c53efa2ef4893c3f4333c215cfa69b9eb3a321f138e',hash('sha256',$raw))) throw new RuntimeException('Changed original comparison baseline.');
    $baseline=json_decode($raw,true,64,JSON_THROW_ON_ERROR);$map=$baseline['rename'];
    foreach ($baseline['units'] as $name=>$unit) {
        if (!hash_equals($unit['sha256'],hash('sha256',$unit['code']))) throw new RuntimeException('Changed original comparison unit.');
        $local=$map;
        if ($name==='provider_stackcp_access_admin_render') $local['provider_stackcp_access_status']='sap_capture_legacy_status';
        $code='';foreach (token_get_all('<?php '.$unit['code']) as $token) {
            if (is_array($token)) {if ($token[0]===T_OPEN_TAG) continue;$code.=($token[0]===T_STRING&&isset($local[$token[1]]))?$local[$token[1]]:$token[1];}
            else $code.=$token;
        }
        eval('use CajunWebPro\\Foundation\\TwentyIStackCpAccess\\StackCpAccessBinding;use CajunWebPro\\Foundation\\TwentyIStackCpAccess\\StackCpAccessObservation;'.$code);
    }
}
function sap_capture_legacy_status(string $profile,int $package):array {
    $view=sap_original_provider_stackcp_access_status($profile,$package);$GLOBALS['sap_old_views'][]=$view;return $view;
}
function sap_render(bool $original,int|string $page):array {
    $_GET=['stackcp_access_page'=>$page];$GLOBALS['sap_old_views']=[];
    $before=sap_status_counters();$GLOBALS['sap_profile']=['statements'=>0,'categories'=>[],'column_inventories'=>[],'non_read_statements'=>0];
    $GLOBALS['sap_record']=true;$start=hrtime(true);ob_start();
    try {($original?'sap_original_provider_stackcp_access_admin_render':'provider_stackcp_access_admin_render')();$html=ob_get_contents();}
    finally {ob_end_clean();$GLOBALS['sap_record']=false;}
    $metric=$GLOBALS['sap_profile'];$metric['elapsed_seconds']=(hrtime(true)-$start)/1e9;$after=sap_status_counters();
    foreach ($before as $name=>$n) {$delta=$after[$name]-$n;if ($delta<0) throw new RuntimeException('SQL session counter went backwards.');$metric['session_status_delta'][$name]=$delta;}
    return [$html,$metric,$GLOBALS['sap_old_views']];
}
function sap_compare(string $name,int $page,int $displayed):void {
    global $metrics;$before=sap_state();[$old,$oldMetric,$views]=sap_render(true,(string)$page);[$new,$newMetric]=sap_render(false,(string)$page);
    sap_ok($name.' preserves exact complete HTML without normalization',$old===$new);
    sap_ok($name.' displays every expected package including unassigned links',substr_count($new,'data-stackcp-access-package=')===$displayed&&count($views)===$displayed);
    $snapshot=ProviderStackCpReadProjection::ownerAccessSnapshot($page);
    sap_ok($name.' preserves the exact ordered safe projection values',$snapshot['state']==='ready'&&$snapshot['error']===null&&$snapshot['packages']===$views);
    $inventories=$newMetric['column_inventories'];ksort($inventories);$expectedInventories=['auth'=>2,'assignment'=>2,'started'=>2,'finished'=>2];ksort($expectedInventories);
    sap_ok($name.' validates all four physical families once',$inventories===$expectedInventories);
    sap_ok($name.' reduces actual statement work without a timing threshold',$newMetric['statements']<$oldMetric['statements']&&$newMetric['session_status_delta']['Questions']>0&&$oldMetric['session_status_delta']['Questions']>0);
    sap_ok($name.' performs no data writes and retains all original rows',$newMetric['non_read_statements']===0&&$oldMetric['non_read_statements']===0&&sap_state()===$before);
    sap_ok($name.' returns after releasing mutex and transaction',sap_released());
    $metrics[]=['case'=>$name,'page'=>$page,'displayed_packages'=>$displayed,'access_rows'=>(int)query('SELECT COUNT(*) FROM '.table('provider_stackcp_reads'))->fetchColumn(),'original'=>$oldMetric,'snapshot'=>$newMetric];
}
function sap_html_equivalent(string $name,string $required):void {
    $before=sap_state();[$old]=sap_render(true,'1');[$new]=sap_render(false,'1');
    sap_ok($name,$old===$new&&str_contains($new,$required)&&sap_state()===$before&&sap_released());
}
/** Synthetic complete hosting observation through the actual typed common recorder. */
function sap_hosting():void {
    global $profile,$general;
    staff_locked(fn()=>staff_transaction(function()use($profile,$general):void {
        $p=provider_settings_load($profile);$epoch=time();$r=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$profile,'profile_revision'=>(int)$p['revision'],'actor_id'=>1,'started_at'=>gmdate('Y-m-d H:i:s',$epoch),'started_epoch'=>$epoch,'operation'=>'packages.list'];
        query('INSERT INTO '.table('provider_refreshes').' ('.implode(',',array_keys($r)).') VALUES ('.implode(',',array_fill(0,count($r),'?')).')',array_values($r));
        provider_settings_audit(1,'refresh_started','Refresh '.$r['id'].' profile '.$profile.' revision '.$r['profile_revision']);
        $items=[];foreach (range(701,721) as $id) $items[]=['id'=>$id,'name'=>'access-snapshot'.$id.'.synthetic.invalid','names'=>['access-snapshot'.$id.'.synthetic.invalid'],'enabled'=>true,'created'=>'2026-01-01T00:00:00+00:00','typeRef'=>12,'packageTypeName'=>'Synthetic hosting','stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];
        $s=InventoryProjector::packagesList(new TransportResponse(200,[],json_encode($items)),$epoch,'twenty-i|'.$profile.'|packages.list');
        provider_inventory_record_transaction($r,$s->state,$s->reason,$s->scope,'unknown',count($s->packages),$s->skipped,json_encode($s->skipReasons),0,$s,provider_inventory_fingerprint($general));
    }));
}
function sap_link(int $package,bool $assign=true):void {
    global $profile,$password;provider_inventory_link($profile,$package,1,1,$password);
    if ($assign) {$link=one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=?',[$profile,$package]);$view=provider_stackcp_assignment_status($profile,$package);provider_stackcp_assignment_change($profile,$package,$view['review_sha256'],$link['id'],'stack-user:'.$package,false,$password);}
}
/** Seed signed synthetic observations through real start/finish hooks; never transport or time rewrites. */
function sap_observe(int $package,string $operation):array {
    global $profile;
    return staff_locked(fn()=>staff_transaction(function()use($profile,$package,$operation):array {
        $binding=provider_stackcp_access_binding($profile,$package,$operation);provider_stackcp_access_authority($binding);$d=$binding->data;$epoch=time();
        $r=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$profile,'profile_revision'=>$d['profile_revision'],'actor_id'=>$d['actor_id'],'started_at'=>gmdate('Y-m-d H:i:s',$epoch),'started_epoch'=>$epoch,'operation'=>$operation];
        query('INSERT INTO '.table('provider_refreshes').' ('.implode(',',array_keys($r)).') VALUES ('.implode(',',array_fill(0,count($r),'?')).')',array_values($r));
        provider_settings_audit($d['actor_id'],'refresh_started','Refresh '.$r['id'].' profile '.$profile.' revision '.$r['profile_revision']);provider_stackcp_access_started($r,$binding);
        $values=$operation===StackCpAccessBinding::MEMBERSHIP?['stack-user:'.$package]:['package:'.$package];$observed=new StackCpAccessObservation($binding,$epoch,'complete','',$values);
        provider_inventory_record_transaction($r,'complete','',$binding->scope,'unknown',0,0,'[]',0,$observed,$d['key_fingerprint']);return $r;
    }));
}
function sap_worker(string $mode):array {
    $process=proc_open([PHP_BINARY,__FILE__,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start isolated snapshot worker.');
    fclose($pipes[0]);stream_set_timeout($pipes[1],20);stream_set_timeout($pipes[2],20);return [$process,$pipes];
}
function sap_finish(array $worker):string {
    [$process,$pipes]=$worker;$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if (proc_close($process)!==0||$error!=='') throw new RuntimeException('Isolated snapshot worker failed.');return $out;
}
if (($argv[1]??'')==='try-lock') {
    $key=sap_lock_key();$free=query('SELECT GET_LOCK(?,0)',[$key])->fetchColumn()==1;echo $free?'free':'held';if ($free) query('SELECT RELEASE_LOCK(?)',[$key]);exit;
}
if (($argv[1]??'')==='waiting-read') {
    sap_actor();echo "ready\n";fflush(STDOUT);
    try {ProviderStackCpReadProjection::ownerAccessSnapshot();echo 'unexpected';}
    catch (RuntimeException $e) {if ($e instanceof PDOException) throw $e;echo $e->getMessage()==='An active owner account is required for provider settings.'?'refused':'other';}exit;
}
try {
    db()->setAttribute(PDO::ATTR_STATEMENT_CLASS,[StackCpAccessProfileStatement::class]);sap_load_original();
    $runtime=['php'=>PHP_VERSION,'pdo_driver'=>db()->getAttribute(PDO::ATTR_DRIVER_NAME),'database_server'=>db()->getAttribute(PDO::ATTR_SERVER_VERSION)];
    sap_ok('Isolated fixture prefix starts without inherited tables',all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,5)=?',['sapr_'])===[]);
    foreach (schema_statements('sapr_') as $name=>$sql) if (!str_starts_with($name,'provider_')) db()->exec($sql);
    foreach (default_settings() as $key=>$value) save_setting($key,$value);save_setting('studio_schema_version','2');
    foreach ([1,2] as $id) {query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Access snapshot owner '.$id,'accesssnapshotowner'.$id.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);staff_seed_owner($id);query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Access snapshot client '.$id,'accesssnapshotclient'.$id.'@example.invalid',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);}
    sap_actor();provider_settings_migrate($password);provider_inventory_migrate($password);
    sap_html_equivalent('No account retains exact original HTML and absent access storage','Save your 20i account first.');
    $profile=provider_settings_save('',0,'Access snapshot fixture',true,$general,$password);
    sap_html_equivalent('Never-prepared access retains exact original preparation form','Prepare observations');
    sap_ok('Read-only unprepared paths create no optional families',!provider_stackcp_exists('auth')&&!provider_stackcp_exists('assignment')&&!provider_stackcp_access_exists('provider_stackcp_reads'));
    provider_stackcp_auth_prepare($password);provider_stackcp_auth_change($profile,provider_stackcp_auth_status($profile)['review_sha256'],true,$secret,$password);provider_stackcp_assignment_prepare($password);provider_stackcp_access_prepare($password);sap_hosting();
    sap_compare('Empty access history zero displayed packages',1,0);
    sap_link(701);sap_hosting();sap_compare('Empty access history one displayed package',1,1);
    foreach (range(702,720) as $package) sap_link($package,$package!==702);
    sap_hosting();sap_compare('Empty access history twenty displayed packages',1,20);
    sap_ok('Unassigned current link remains a visible unavailable card',provider_stackcp_access_status($profile,702)['status']==='assignment_or_source_unavailable'&&in_array(702,array_column(ProviderStackCpReadProjection::ownerAccessSnapshot()['packages'],'package_id'),true));
    sap_link(721);sap_hosting();
    $first=sap_observe(701,StackCpAccessBinding::MEMBERSHIP);sap_observe(701,StackCpAccessBinding::GRANTS);
    $outside=sap_observe(721,StackCpAccessBinding::MEMBERSHIP);$outsideGrant=sap_observe(721,StackCpAccessBinding::GRANTS);
    sap_compare('Retained access histories zero displayed packages',3,0);
    sap_compare('Retained access histories one displayed package',2,1);
    sap_compare('Retained access histories twenty displayed packages',1,20);
    $page=ProviderStackCpReadProjection::ownerAccessSnapshot();$next=ProviderStackCpReadProjection::ownerAccessSnapshot(2);
    sap_ok('Page boundary preserves twenty plus one without dropping current links',$page['has_more']===true&&$next['has_more']===false&&array_column($page['packages'],'package_id')===range(701,720)&&array_column($next['packages'],'package_id')===[721]);
    [$old]=sap_render(true,'bad');[$new]=sap_render(false,'bad');sap_ok('Malformed page selection retains exact original first-page HTML',$old===$new&&substr_count($new,'data-stackcp-access-package=')===20);

    $before=sap_state();$read=one('SELECT * FROM '.table('provider_stackcp_reads').' WHERE id=?',[$outside['id']]);
    sap_ok('Out-of-page corruption targets exact signed original package',is_array($read)&&(int)$read['package_id']===721&&$read['id']===$outside['id']);
    try {
        sap_ok('Out-of-page index mutation changes exactly one retained row',query('UPDATE '.table('provider_stackcp_reads').' SET package_id=720 WHERE id=?',[$read['id']])->rowCount()===1);
        $bad=ProviderStackCpReadProjection::ownerAccessSnapshot();
        sap_ok('Fresh page refuses out-of-page signed index corruption',$bad['packages']===[]&&$bad['error']==='StackCP access signed history needs recovery.');
        sap_html_equivalent('Old and new corrupted-history HTML retain exact refusal','StackCP access signed history needs recovery.');
        try {provider_stackcp_access_status($profile,701);sap_ok('Standalone status also rechecks global corruption',false);} catch (RuntimeException $e) {if ($e instanceof PDOException) throw $e;sap_ok('Standalone status also rechecks global corruption',$e->getMessage()==='StackCP access signed history needs recovery.');}
    } finally {query('UPDATE '.table('provider_stackcp_reads').' SET package_id=? WHERE id=?',[$read['package_id'],$read['id']]);}
    sap_ok('Exact index restoration permits only a newly validated read',sap_state()===$before&&ProviderStackCpReadProjection::ownerAccessSnapshot()['error']===null&&sap_released());

    $finish=one('SELECT * FROM '.table('provider_stackcp_observations').' WHERE id=?',[$outsideGrant['id']]);
    sap_ok('Paired loss selects the exact out-of-page original finish',is_array($finish)&&$finish['id']===$outsideGrant['id']);
    $audits=all('SELECT * FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.stackcp_access_finished',provider_stackcp_access_detail('finished',$finish)]);
    sap_ok('Paired loss selects exact original finish and matching actor audit',is_array($finish)&&$finish['id']===$outsideGrant['id']&&count($audits)===1&&(int)$audits[0]['user_id']===1);
    $audit=$audits[0];$before=sap_state();$removedFinish=false;$removedAudit=false;
    try {
        $removedFinish=query('DELETE FROM '.table('provider_stackcp_observations').' WHERE id=?',[$finish['id']])->rowCount()===1;
        sap_ok('Paired loss removes exactly one original finish',$removedFinish&&one('SELECT id FROM '.table('provider_stackcp_observations').' WHERE id=?',[$finish['id']])===null);
        $removedAudit=query('DELETE FROM '.table('audit').' WHERE id=?',[$audit['id']])->rowCount()===1;
        sap_ok('Paired loss removes exactly one corresponding audit',$removedAudit&&one('SELECT id FROM '.table('audit').' WHERE id=?',[$audit['id']])===null);
        $bad=ProviderStackCpReadProjection::ownerAccessSnapshot();sap_ok('Independent count still refuses paired out-of-page evidence loss',$bad['packages']===[]&&$bad['error']==='StackCP access history counts disagree.');
    } finally {if ($removedFinish) sap_restore('provider_stackcp_observations',$finish);if ($removedAudit) sap_restore('audit',$audit);}
    sap_ok('Exact paired restoration retains every row and revives only fresh calls',sap_state()===$before&&ProviderStackCpReadProjection::ownerAccessSnapshot()['error']===null&&sap_released());

    $key=config()['storage'].'/provider-master.key';$held=$key.'.snapshot-held';$keyHash=hash_file('sha256',$key);$before=sap_state();
    if (file_exists($held)||!rename($key,$held)) throw new RuntimeException('Cannot hold original synthetic master key.');
    try {[$old]=sap_render(true,'1');[$new]=sap_render(false,'1');clearstatcache(true,$key);sap_ok('Missing vault preserves exact refusal without recreation or package forms',$old===$new&&!str_contains($new,'data-stackcp-access-package=')&&!file_exists($key)&&sap_state()===$before&&sap_released());}
    finally {if (file_exists($key)) unlink($key);if (!rename($held,$key)) throw new RuntimeException('Restore original synthetic master key.');}
    clearstatcache(true,$key);sap_ok('Original key restoration requires fresh validation and preserves key bytes',hash_file('sha256',$key)===$keyHash&&(fileperms($key)&0777)===0600&&ProviderStackCpReadProjection::ownerAccessSnapshot()['error']===null);

    $GLOBALS['sap_lock_probe']=true;ProviderStackCpReadProjection::ownerAccessSnapshot();
    sap_ok('Actual second process cannot acquire mutex during page projection',($GLOBALS['sap_lock_probe_result']??null)==='held');
    sap_ok('Returned snapshot releases mutex to an actual second process',sap_finish(sap_worker('try-lock'))==='free'&&sap_released());
    sap_ok('Synthetic SQL fault table is absent',!provider_stackcp_access_exists('deliberately_absent'));
    $GLOBALS['sap_sql_fault']=true;
    try {ProviderStackCpReadProjection::ownerAccessSnapshot();sap_ok('PDO server exception propagates instead of safe fallback',false);} catch (PDOException $e) {sap_ok('PDO server exception propagates instead of safe fallback',$e->getCode()==='42S02');} finally {$GLOBALS['sap_sql_fault']=false;}
    sap_ok('PDO exception releases transaction and mutex to another process',sap_released()&&sap_finish(sap_worker('try-lock'))==='free');
    $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');$captured=provider_stackcp_access_status($profile,701)['reviews'][StackCpAccessBinding::MEMBERSHIP];$before=sap_state();
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');
    try {
        $snapshot=ProviderStackCpReadProjection::ownerAccessSnapshot();sap_ok('Next page rechecks every currently inactive client',count($snapshot['packages'])===20&&!array_filter($snapshot['packages'],fn($v)=>$v['reviews']!==[]));
        sap_ok('Standalone status cannot reuse earlier validated authority',provider_stackcp_access_status($profile,701)['status']==='assignment_or_source_unavailable');
        $during=sap_state();$dns=0;
        try {provider_stackcp_access_refresh($profile,701,StackCpAccessBinding::MEMBERSHIP,$captured,$password,function()use(&$dns):string {$dns++;throw new LogicException('No network allowed.');});sap_ok('Captured earlier form refuses before DNS after client revocation',false);} catch (RuntimeException $e) {if ($e instanceof PDOException) throw $e;sap_ok('Captured earlier form refuses before DNS after client revocation',$e->getMessage()==='Review a current protected StackCP assignment first.'&&$dns===0&&sap_state()===$during);}
    } finally {query('UPDATE '.table('studio_clients').' SET active=? WHERE id=1',[$client['active']]);}
    sap_ok('Exact client restoration preserves histories without retained permission state',sap_state()===$before&&provider_stackcp_access_status($profile,701)['status']==='observed_match');
    $owner=one('SELECT * FROM '.table('staff_profiles').' WHERE user_id=1');$worker=null;
    staff_locked(function()use(&$worker):void {$worker=sap_worker('waiting-read');sap_ok('Actual separate owner reaches lock-entry boundary',fgets($worker[1][1])==="ready\n");staff_transaction(fn()=>query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1'));});
    try {sap_ok('Owner waiting on real mutex is rechecked after entry',sap_finish($worker)==='refused');} finally {query('UPDATE '.table('staff_profiles').' SET enabled=? WHERE user_id=1',[$owner['enabled']]);sap_actor();}
    sap_ok('Rejected owner leaves mutex and transaction released',sap_released()&&sap_finish(sap_worker('try-lock'))==='free');
    db()->beginTransaction();try {try {ProviderStackCpReadProjection::ownerAccessSnapshot();sap_ok('Owner page rejects caller transaction without consuming it',false);} catch (LogicException $e) {sap_ok('Owner page rejects caller transaction without consuming it',db()->inTransaction()&&$e->getMessage()==='StackCP access owner read must own its transaction.');}} finally {db()->rollBack();}
    sap_ok('Fixture creates no provider operation or remote session storage',!provider_stackcp_access_exists('provider_operations')&&!str_contains(json_encode(all('SELECT * FROM '.table('audit'))),$general)&&!str_contains(json_encode(all('SELECT * FROM '.table('audit'))),$secret));
    $links=(int)query('SELECT COUNT(*) FROM '.table('provider_package_links').' WHERE profile_id=?',[$profile])->fetchColumn();$assignments=(int)query('SELECT COUNT(*) FROM '.table('provider_stackcp_assignments').' WHERE profile_id=?',[$profile])->fetchColumn();
    sap_ok('HTTP seed retains exactly twenty-one links and twenty local assignments',$links===21&&$assignments===20);
    file_put_contents(__DIR__.'/../qa/stackcp-access-read-snapshot-seed.json',json_encode(['passed'=>true,'scope'=>'Disposable synthetic native seed for an optional separately verified HTTP diagnostic; not HTTP evidence.','prefix'=>'sapr_','profile_id'=>$profile,'owner_email'=>'accesssnapshotowner1@example.invalid','storage_relative'=>'test-runtime/stackcp-access-read-private','master_key_sha256'=>hash_file('sha256',config()['storage'].'/provider-master.key'),'restored_database_sha256'=>sap_state(),'linked_packages'=>$links,'local_assignments'=>$assignments,'unassigned_package'=>702,'membership_and_grants_packages'=>[701,721]],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
} catch (Throwable $e) {$checks[]=['name'=>'Unexpected StackCP access snapshot native failure','passed'=>false,'error'=>get_class($e).': '.$e->getMessage()];fwrite(STDERR,$e->__toString().PHP_EOL);}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
file_put_contents(__DIR__.'/../qa/stackcp-access-read-snapshot-native-results.json',json_encode(['passed'=>$passed,'scope'=>'Actual disposable MySQL/MariaDB PDO work and session counters, original complete HTML/safe projection equivalence, signed-history restoration, fresh authority and real process locks. Synthetic typed observations only; no provider transport, HTTP/browser or full-page timing claim.','checks'=>$checks,'profiles'=>$metrics,'runtime'=>$runtime],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo ($passed?'PASS':'FAIL').': '.count($checks).' StackCP access snapshot native checks'.PHP_EOL;exit($passed?0:1);
