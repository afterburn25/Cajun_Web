<?php
/** Exact-function modeled recovery boundary; no database or application HTTP execution. */
declare(strict_types=1);
namespace HostingRecoveryProbe;
if (PHP_SAPI!=='cli') exit(2);
$root=dirname(__DIR__);$checks=[];
function probe_check(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new \RuntimeException($name);}
function probe_function(string $path,string $name):string {
    $tokens=token_get_all(file_get_contents($path));$matches=[];
    foreach($tokens as $i=>$token){
        if(!is_array($token)||$token[0]!==T_FUNCTION)continue;
        $j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
        if(!isset($tokens[$j])||!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING||$tokens[$j][1]!==$name)continue;
        $source='';$depth=0;$body=false;
        for($k=$i;$k<count($tokens);$k++){
            $t=$tokens[$k];$source.=is_array($t)?$t[1]:$t;
            if($t==='{'){$depth++;$body=true;}elseif($t==='}'){$depth--;if($body&&$depth===0){$matches[]=$source;break;}}
        }
    }
    if(count($matches)!==1)throw new \RuntimeException('Expected one exact source function: '.$name);
    return $matches[0];
}
$functions=[
    'site/app/staff/accounts.php'=>['staff_locked','staff_ready','staff_profile'],
    'site/app/settings/store.php'=>['setting'],
    'site/app/hosting/catalog_offers_storage.php'=>['hosting_catalog_lock','hosting_catalog_integer','hosting_catalog_ready','hosting_catalog_action','hosting_catalog_recovery'],
    'site/app/hosting/catalog_recovery.php'=>['hosting_catalog_recover_files'],
];
$source=[];
foreach($functions as $path=>$names){
    $source[$path]=hash_file('sha256',$root.'/'.$path);
    foreach($names as $name)eval('namespace HostingRecoveryProbe; use \\RuntimeException; use \\LogicException;'.probe_function($root.'/'.$path,$name));
}
// These seams model PDO/catalog rows, session identity and file recovery. The ten functions
// above execute their exact production bodies; schema/document checks are modeled outcomes.
function config():array {return ['db'=>['name'=>'synthetic','prefix'=>'probe_']];}
function db():object {return new class {public function inTransaction():bool{return $GLOBALS['probe']['transaction'];}};}
function table(string $name):string {return '`probe_'.$name.'`';}
function hosting_catalog_tables():array {return ['hosting_catalog_publications','hosting_catalog_withdrawals','hosting_catalog_requests','hosting_catalog_decisions'];}
function hosting_catalog_schema(string $prefix):array {return array_fill_keys(hosting_catalog_tables(),'synthetic schema');}
function hosting_catalog_exists(string $name):bool {return in_array($name,$GLOBALS['probe']['tables'],true);}
function hosting_catalog_indexes(string $name):array {return [];}
function hosting_catalog_relations(string $name):array {return [];}
function hosting_catalog_validate_schema(string $name,string $sql):void {if($GLOBALS['probe']['schema_bad'])throw new \RuntimeException('Modeled invalid schema.');}
function hosting_catalog_validate_links(string $name,array $row):void {if($GLOBALS['probe']['row_bad'])throw new \RuntimeException('Modeled invalid immutable row.');}
function hosting_catalog_assert_witness(string $name,array $row):void {if($GLOBALS['probe']['witness_bad'])throw new \RuntimeException('Modeled invalid witness.');}
function admin_user():?array {
    probe_check('Owner is re-read while the mutex is held '.$GLOBALS['probe']['case'],$GLOBALS['probe']['locked']);
    $actor=$GLOBALS['probe']['actor'];if($actor===null)return null;
    unset($actor['role'],$actor['enabled'],$actor['permissions']);
    return staff_profile($actor);
}
function staff_permissions():array {return [];}
final class Statement {public function __construct(private mixed $value){}public function fetchColumn():mixed{return $this->value;}}
function query(string $sql,array $args=[]):Statement {
    $p=&$GLOBALS['probe'];$p['queries'][]=$sql;
    if(str_starts_with($sql,'SELECT GET_LOCK(')){
        if($p['lock_denied'])return new Statement(0);
        if($p['locked'])throw new \LogicException('Unexpected nested mutex.');$p['locked']=true;$p['events'][]='lock';
        if($p['on_lock'])($p['on_lock'])();return new Statement(1);
    }
    if(str_starts_with($sql,'SELECT RELEASE_LOCK(')){$p['events'][]='release';$p['locked']=false;return new Statement(1);}
    if(str_starts_with($sql,'SELECT COALESCE(IS_USED_LOCK('))return new Statement($p['locked']?1:0);
    if($p['database_error'])throw new \PDOException('Modeled database failure.');
    if(!str_starts_with($sql,'SELECT '))throw new \LogicException('A SQL write was attempted.');
    if($sql==='SELECT COUNT(*) FROM `probe_audit` WHERE BINARY LEFT(action,16)=?')return new Statement($p['audit_count']);
    if($sql==='SELECT COUNT(*) FROM `probe_audit` WHERE BINARY action=?'){
        foreach(hosting_catalog_tables() as $name)if(hosting_catalog_action($name)===$args[0])return new Statement(count($p['rows'][$name]));
    }
    foreach(hosting_catalog_tables() as $name)if($sql==='SELECT COUNT(*) FROM '.table($name))return new Statement(count($p['rows'][$name]));
    throw new \LogicException('Unexpected modeled query.');
}
function one(string $sql,array $args=[]):?array {
    $p=&$GLOBALS['probe'];$p['queries'][]=$sql;if($p['database_error'])throw new \PDOException('Modeled database failure.');
    if($sql==='SELECT value FROM `probe_settings` WHERE name=?')return array_key_exists($args[0],$p['settings'])?['value'=>$p['settings'][$args[0]]]:null;
    if($sql==='SELECT role,enabled,permissions FROM `probe_staff_profiles` WHERE user_id=?')return $p['actor']===null?null:['role'=>$p['actor']['role'],'enabled'=>$p['actor']['enabled'],'permissions'=>'[]'];
    throw new \LogicException('Unexpected modeled one.');
}
function all(string $sql,array $args=[]):array {
    $p=&$GLOBALS['probe'];$p['queries'][]=$sql;
    if($p['database_error'])throw new \PDOException('Modeled database failure.');
    if($sql==='SELECT name,value FROM `probe_settings`')return array_map(static fn($name,$value)=>['name'=>$name,'value'=>$value],array_keys($p['settings']),array_values($p['settings']));
    if($sql==='SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME'){
        if($args!==[strlen('probe_hosting_catalog_'),'probe_hosting_catalog_'])throw new \LogicException('Namespace query lost its exact literal prefix.');
        return array_map(static fn($name)=>['TABLE_NAME'=>'probe_'.$name],array_filter($p['tables'],static fn($name)=>str_starts_with($name,'hosting_catalog_')));
    }
    if($sql==='SELECT user_id,detail FROM `probe_audit` WHERE BINARY action=?')return $p['setup'];
    foreach(hosting_catalog_tables() as $name)if($sql==='SELECT * FROM '.table($name).' ORDER BY id LIMIT 64')return $p['rows'][$name];
    throw new \LogicException('Unexpected modeled all.');
}
function structure_recover(string $directory):array {
    $p=&$GLOBALS['probe'];$p['recover_calls']++;$p['events'][]='recover';
    if(!$p['locked'])throw new \LogicException('Recovery lost the shared mutex.');
    if($p['recover_error'])throw new \RuntimeException('Modeled recovery failure.');
    foreach(['application.php','.htaccess','journal.php'] as $file)file_put_contents($directory.'/'.$file,'recovered '.$file);
    return ['state'=>'rolled-back'];
}
function reset_probe(string $case,string $kind='absent'):void {
    unset($GLOBALS['cwp_settings']);
    $settings=['staff_schema_version'=>'1'];$tables=[];$rows=array_fill_keys(hosting_catalog_tables(),[]);$setup=[];$events=0;
    if($kind!=='absent'){
        $tables=hosting_catalog_tables();$settings['hosting_catalog_version']='1';
        foreach($tables as $name)$settings[$name.'_count']='0';
        $setup=[['user_id'=>'1','detail'=>'Hosting offer storage version 1']];$events=1;
    }
    $GLOBALS['probe']=['case'=>$case,'locked'=>false,'lock_denied'=>false,'on_lock'=>null,'events'=>[],'queries'=>[],'transaction'=>false,
        'actor'=>['id'=>1,'role'=>'owner','enabled'=>1,'auth_version'=>str_repeat('a',32),'password_hash'=>password_hash('synthetic recovery owner',PASSWORD_DEFAULT)],
        'settings'=>$settings,'tables'=>$tables,'rows'=>$rows,'setup'=>$setup,'audit_count'=>$events,
        'database_error'=>false,'schema_bad'=>false,'row_bad'=>false,'witness_bad'=>false,'recover_error'=>false,'recover_calls'=>0];
    foreach(['application.php','.htaccess','journal.php','backup.php'] as $file)file_put_contents($GLOBALS['temp'].'/'.$file,'original '.$file);
}
function publication():void {
    $p=&$GLOBALS['probe'];$p['rows']['hosting_catalog_publications']=[['id'=>str_repeat('1',32)]];
    $p['settings']['hosting_catalog_publications_count']='1';$p['audit_count']=2;
}
function snapshot():array {$out=[];foreach(['application.php','.htaccess','journal.php','backup.php'] as $f)$out[$f]=hash_file('sha256',$GLOBALS['temp'].'/'.$f);return $out;}
function attempt(string $name,bool $allowed,string $error=''):void {
    $before=snapshot();$exception=null;$result=null;
    try{$result=hosting_catalog_recover_files($GLOBALS['temp'],1,str_repeat('a',32),'synthetic recovery owner');}catch(\Throwable $e){$exception=$e;}
    $p=$GLOBALS['probe'];
    probe_check($name,$allowed?($exception===null&&$result===['state'=>'rolled-back']&&$p['recover_calls']===1&&$p['events']===['lock','recover','release']):(($exception instanceof \RuntimeException||$exception instanceof \LogicException)&&$error!==''&&str_contains($exception->getMessage(),$error)&&$p['recover_calls']===0&&snapshot()===$before));
    probe_check($name.' releases the shared mutex',!$p['locked']);
    probe_check($name.' performs no SQL writes',!array_filter($p['queries'],static fn($q)=>!str_starts_with($q,'SELECT ')));
}
$temp=sys_get_temp_dir().'/cwp-hosting-recovery-probe-'.bin2hex(random_bytes(8));
if(!mkdir($temp,0700))throw new \RuntimeException('Cannot create isolated probe directory.');
$GLOBALS['temp']=$temp;
try {
    reset_probe('unprepared');attempt('Completely absent hosting storage permits file recovery',true);
    reset_probe('legacy-staff');unset($GLOBALS['probe']['settings']['staff_schema_version']);attempt('Legacy owner without prepared staff storage can recover absent hosting history',true);
    reset_probe('prepared-empty','empty');attempt('Validated prepared empty history permits file recovery',true);
    reset_probe('interruption');attempt('Unprepared interruption recovery remains available',true);
    reset_probe('published','ready');publication();attempt('Any publication refuses before application policy or journal change',false,'Published hosting offers');
    reset_probe('withdrawn','ready');publication();$GLOBALS['probe']['rows']['hosting_catalog_withdrawals']=[['id'=>str_repeat('2',32)]];$GLOBALS['probe']['settings']['hosting_catalog_withdrawals_count']='1';$GLOBALS['probe']['audit_count']=3;attempt('Withdrawal does not erase the original publication recovery boundary',false,'Published hosting offers');
    foreach(['complete','backed-up','applying','partially-restored','rolled-back','same-version','unknown-future-target'] as $journal){
        reset_probe('journal-'.$journal,'ready');publication();file_put_contents($temp.'/journal.php',$journal);attempt('Published history has no journal or target exemption '.$journal,false,'Published hosting offers');
    }
    reset_probe('before-lock','ready');$GLOBALS['probe']['on_lock']=fn()=>publication();attempt('Publication completed before lock acquisition is observed and refused',false,'Published hosting offers');
    reset_probe('partial');$GLOBALS['probe']['tables']=['hosting_catalog_publications'];attempt('An unmarked partial empty schema is refused',false,'Hosting offer storage is incomplete or unrecognized.');
    reset_probe('all-unmarked');$GLOBALS['probe']['tables']=hosting_catalog_tables();attempt('An unmarked complete empty schema is not mistaken for verified absence',false,'Hosting offer storage is incomplete or unrecognized.');
    reset_probe('unknown-unprepared');$GLOBALS['probe']['tables']=['hosting_catalog_future'];attempt('Unknown hosting table without known storage is refused',false,'Hosting offer storage is incomplete or unrecognized.');
    reset_probe('unknown-prepared','empty');$GLOBALS['probe']['tables'][]='hosting_catalog_future';attempt('Unknown hosting table beside validated empty storage is refused',false,'Hosting offer storage is incomplete or unrecognized.');
    reset_probe('unrelated-table');$GLOBALS['probe']['tables']=['other_hosting_catalog_future'];attempt('Unrelated table outside exact hosting namespace does not block recovery',true);
    reset_probe('inherited-transaction');$GLOBALS['probe']['transaction']=true;attempt('Inherited transaction refuses before lock and history reads',false,'File recovery cannot run inside a database transaction.');probe_check('Inherited transaction performs no SQL or mutex acquisition',$GLOBALS['probe']['queries']===[]&&$GLOBALS['probe']['events']===[]);
    reset_probe('unknown-marker','empty');$GLOBALS['probe']['settings']['hosting_catalog_version']='2';attempt('Unknown hosting marker is refused',false,'Hosting offer history needs recovery.');
    reset_probe('missing-table','empty');array_pop($GLOBALS['probe']['tables']);attempt('Prepared history with a missing table is refused',false,'Hosting offer history needs recovery.');
    reset_probe('bad-count','empty');$GLOBALS['probe']['settings']['hosting_catalog_publications_count']='1';attempt('Changed retained counter is refused',false,'Hosting offer history needs recovery.');
    reset_probe('bad-audit','empty');$GLOBALS['probe']['audit_count']=0;attempt('Missing preparation audit is refused',false,'Hosting offer history needs recovery.');
    reset_probe('bad-schema','empty');$GLOBALS['probe']['schema_bad']=true;attempt('Invalid schema validator result is refused',false,'Modeled invalid schema.');
    reset_probe('bad-row','ready');publication();$GLOBALS['probe']['row_bad']=true;attempt('Invalid retained document result is refused',false,'Modeled invalid immutable row.');
    reset_probe('bad-witness','ready');publication();$GLOBALS['probe']['witness_bad']=true;attempt('Invalid immutable witness result is refused',false,'Modeled invalid witness.');
    reset_probe('database');$GLOBALS['probe']['database_error']=true;attempt('Database failure refuses before any recovery write',false,'Modeled database failure.');
    reset_probe('cached-legacy');unset($GLOBALS['probe']['settings']['staff_schema_version']);probe_check('Synthetic pre-lock lookup cached the absent staff marker',staff_ready()===false&&isset($GLOBALS['cwp_settings']));
    $GLOBALS['probe']['on_lock']=static function():void{$GLOBALS['probe']['settings']['staff_schema_version']='1';$GLOBALS['probe']['actor']['enabled']=0;};attempt('New staff preparation with disabled owner invalidates pre-lock legacy cache',false,'Your current owner account');
    foreach(['revoked','changed-id','changed-auth','changed-role','changed-password'] as $kind){
        reset_probe($kind);$GLOBALS['probe']['on_lock']=static function()use($kind):void {
            if($kind==='revoked')$GLOBALS['probe']['actor']=null;
            elseif($kind==='changed-id')$GLOBALS['probe']['actor']['id']=2;
            elseif($kind==='changed-auth')$GLOBALS['probe']['actor']['auth_version']=str_repeat('b',32);
            elseif($kind==='changed-role')$GLOBALS['probe']['actor']['role']='administrator';
            else $GLOBALS['probe']['actor']['password_hash']=password_hash('different synthetic password',PASSWORD_DEFAULT);
        };attempt('Current owner '.$kind.' while waiting is refused',false,'Your current owner account');
    }
    reset_probe('lock-denied');$GLOBALS['probe']['lock_denied']=true;attempt('Mutex acquisition refusal never enters recovery',false,'Another account change is in progress.');
    reset_probe('recovery-throws');$GLOBALS['probe']['recover_error']=true;$before=snapshot();$failed=false;
    try{hosting_catalog_recover_files($temp,1,str_repeat('a',32),'synthetic recovery owner');}catch(\RuntimeException){$failed=true;}
    probe_check('Filesystem recovery failure propagates while releasing mutex',$failed&&!$GLOBALS['probe']['locked']&&$GLOBALS['probe']['events']===['lock','recover','release']&&snapshot()===$before);
    $report=['passed'=>true,'php'=>PHP_VERSION,'scope'=>'Exact extracted adapter, staff mutex and hosting readiness bodies with synthetic SQL/auth/schema/document/filesystem seams. No actual PDO, HTTP, native concurrency or real site restore.','source_sha256'=>$source,'checks'=>$checks];
} catch(\Throwable $e){$report=['passed'=>false,'php'=>PHP_VERSION,'checks'=>$checks,'failure'=>get_class($e).': '.$e->getMessage()];}
finally {foreach(['application.php','.htaccess','journal.php','backup.php'] as $file)if(is_file($temp.'/'.$file))unlink($temp.'/'.$file);rmdir($temp);}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['passed']?0:1);
