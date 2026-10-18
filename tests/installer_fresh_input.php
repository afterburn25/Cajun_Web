<?php
/** No-DB fresh-input and retained-file guards; never prepares a real installation. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||defined('CWP_ROOT')||defined('CWP_BOOT'))exit(2);

$source=realpath(__DIR__.'/../site');
$temporary=realpath(sys_get_temp_dir());
if($source===false||$temporary===false)exit(2);
$sandbox=$temporary.DIRECTORY_SEPARATOR.'cwp-fresh-input-'.bin2hex(random_bytes(12));
if(!mkdir($sandbox,0700))exit(2);
mkdir($sandbox.'/website',0700);mkdir($sandbox.'/website/storage',0700);mkdir($sandbox.'/website/storage/media',0700);
mkdir($sandbox.'/prior-private',0700);
define('CWP_BOOT',true);define('CWP_SETUP',true);define('CWP_ROOT',$sandbox.'/website');
define('CWP_GUARD',"<?php exit; ?>\n");define('CWP_INSTALLER_BUILD','fixture');
$_SERVER['DOCUMENT_ROOT']=CWP_ROOT;
$_SESSION=[];$_POST=[];
$checks=[];
function fresh_check(string $name,bool $passed): void {
    global $checks;$checks[]=['name'=>$name,'passed'=>$passed];
    if(!$passed)throw new RuntimeException($name);
}
function fresh_denied(string $name,callable $call,string $class,string $message): void {
    try{$call();}catch(Throwable $e){fresh_check($name,get_class($e)===$class&&$e->getMessage()===$message);return;}
    fresh_check($name,false);
}
/** Select complete source functions at explicit neighbouring declarations, not copies of their logic. */
function fresh_source_function(string $source,string $start,string $end): string {
    $from=strpos($source,$start);$to=$from===false?false:strpos($source,$end,$from+strlen($start));
    if($from===false||$to===false)throw new RuntimeException('Expected installer source boundaries changed.');
    return substr($source,$from,$to-$from);
}
function fresh_files(string $root): array {
    $out=[];
    $walk=function(string $dir)use(&$walk,&$out,$root):void{
        foreach(scandir($dir) as $name){
            if($name==='.'||$name==='..')continue;
            $p=$dir.'/'.$name;$relative=substr($p,strlen($root)+1);
            if(is_link($p))throw new RuntimeException('Unexpected fixture symbolic link.');
            if(is_dir($p)){$out[$relative]=['directory'];$walk($p);}
            else $out[$relative]=['file',hash_file('sha256',$p),filesize($p)];
        }
    };
    $walk($root);ksort($out);return $out;
}
/** Remove only this newly-created, resolved sandbox, never follow symbolic links. */
function fresh_cleanup(string $root,string $temporary): void {
    $resolved=realpath($root);
    if($resolved===false||dirname($resolved)!==$temporary||!preg_match('/^cwp-fresh-input-[a-f0-9]{24}$/D',basename($resolved)))throw new RuntimeException('Fixture cleanup path refused.');
    $remove=function(string $dir)use(&$remove,$resolved):void{
        foreach(scandir($dir) as $name){
            if($name==='.'||$name==='..')continue;$p=$dir.DIRECTORY_SEPARATOR.$name;
            if(is_link($p)){if(!unlink($p))throw new RuntimeException('Fixture link cleanup failed.');continue;}
            $real=realpath($p);
            if($real===false||!str_starts_with($real,$resolved.DIRECTORY_SEPARATOR))throw new RuntimeException('Fixture child cleanup path refused.');
            if(is_dir($p))$remove($p);elseif(!unlink($p))throw new RuntimeException('Fixture file cleanup failed.');
        }
        if(!rmdir($dir))throw new RuntimeException('Fixture directory cleanup failed.');
    };
    $remove($resolved);
}
try{
    require $source.'/includes/security/input.php';
    require $source.'/install/actions/storage.php';
    require $source.'/install/actions/deploy.php';
    $entry=file_get_contents($source.'/install.php');
    $guard=fresh_source_function($entry,'function cwp_installer_installed(): bool {','function cwp_installer_stop(');
    // The only guard seam relocates __DIR__ to our disposable website. Its lstat/body is unchanged.
    if(substr_count($guard,'__DIR__')!==1)throw new RuntimeException('Installed guard root changed.');
    eval(str_replace('__DIR__','CWP_ROOT',$guard));

    fresh_check('Fresh setup is absent by default',setup_fresh_token()==='');
    $_SESSION=['cwp_setup_fresh_token'=>'0123456789ab'];
    fresh_check('Session token alone never enables fresh setup',setup_fresh_token()==='');
    $_POST=['fresh_start'=>''];fresh_check('Empty posted intent keeps ordinary setup',setup_fresh_token()==='');
    foreach(['0','2','x'] as $intent){$_POST=['fresh_start'=>$intent];fresh_denied('Fresh intent rejects value '.$intent,fn()=>setup_fresh_token(),InvalidArgumentException::class,'Choose whether to start a fresh website.');}
    foreach([['1'],1,true,'11'] as $i=>$intent){$_POST=['fresh_start'=>$intent];fresh_denied('Fresh intent rejects wrong type or length '.$i,fn()=>setup_fresh_token(),InvalidArgumentException::class,'Please check the length and format of fresh start.');}
    $_POST=['fresh_start'=>'1'];
    foreach([null,1,['0123456789ab'],'0123456789a','0123456789abc','0123456789AB','0123456789xz',"0123456789a\0"] as $i=>$token){
        $_SESSION=['cwp_setup_fresh_token'=>$token];
        fresh_denied('Fresh intent requires exact session token '.$i,fn()=>setup_fresh_token(),RuntimeException::class,'Reload the installation form before choosing a fresh start.');
    }
    $_SESSION=['cwp_setup_fresh_token'=>'0123456789ab','unrelated'=>'retained'];
    $_POST=['fresh_start'=>'1','fresh_token'=>'ffffffffffff'];
    fresh_check('Only the valid session token selects fresh resources',setup_fresh_token()==='0123456789ab');
    $fields=['db_host'=>'localhost','db_port'=>'3306','db_name'=>'synthetic_database','db_user'=>'synthetic_user','db_password'=>'synthetic-only','db_prefix'=>'previous_'];
    $_POST=$fields;$ordinary=setup_database_input();
    fresh_check('Ordinary setup preserves the explicitly entered prefix',$ordinary['prefix']==='previous_');
    $_POST=$fields+['fresh_start'=>'1'];$post=$_POST;$session=$_SESSION;$fresh=setup_database_input();
    fresh_check('Fresh prefix is the bounded session-derived twenty-character prefix',$fresh['prefix']==='cwp_fresh_012345678_'&&strlen($fresh['prefix'])===20);
    $comparison=$fresh;$comparison['prefix']=$ordinary['prefix'];
    fresh_check('Fresh selection changes no database credentials or database name',$comparison===$ordinary);
    fresh_check('Repeated database selection is stable without session or POST mutation',setup_database_input()===$fresh&&$_SESSION===$session&&$_POST===$post);
    $_POST['db_prefix']='bad-';
    fresh_denied('Fresh intent preserves database input validation',fn()=>setup_database_input(),InvalidArgumentException::class,'Check the database host, port, full database/user names and table prefix. The prefix must be 2–20 letters, digits or underscores, beginning with a letter.');

    // Isolated path-selection model: only permanent-parent resolution is supplied.
    // Real setup_real_parent below must still reject our actual temporary directory.
    $storageSource=file_get_contents($source.'/install/actions/storage.php');
    $candidates=fresh_source_function($storageSource,'function setup_storage_candidates(string $requested): array {','function setup_storage_marker(): string {');
    $checksSource=file_get_contents($source.'/install/actions/checks.php');
    $checksBody=fresh_source_function($checksSource,'function setup_checks(): array {','/** Cleanup only after committed config exists;');
    eval(<<<'MODEL'
namespace InstallerFreshInputModel;
use Throwable;
function setup_fresh_token(): string {return \setup_fresh_token();}
function setup_root(): string {return '/fixture-account/public_html';}
function setup_binding(): string {return \setup_binding();}
function setup_document_root(): string {return setup_root();}
function setup_worker_uid(): ?int {return null;}
function setup_real_parent(string $path): string {if($path!=='/fixture-account')throw new \InvalidArgumentException('Model only resolves its one parent.');return $path;}
function posted(string $key,int $limit=5000): string {return \posted($key,$limit);}
function setup_package_check(): array {return ['ok'=>true];}
function setup_package_row(array $value): array {return ['label'=>'Package model','ok'=>$value['ok']];}
function https_request(): bool {return false;}
function local_request(): bool {return true;}
function writable_probe(string $dir,bool $repair=false,int $mode=0750): array {return ['ok'=>true,'message'=>'Read-only filesystem seam'];}
function setup_permission_report(): array {return [];}
function setup_prepare_storage(string $requested): array {
    if(!empty($GLOBALS['fresh_model_real_guard']))return \setup_prepare_storage($requested);
    $paths=setup_storage_candidates($requested);$GLOBALS['fresh_model_selected']=$paths;
    return ['ok'=>true,'storage'=>$paths[0]['path'],'attempts'=>[],'message'=>'Modeled selection only','private'=>['message'=>'Modeled'],'media'=>['message'=>'Modeled']];
}
MODEL
        ."\n".$candidates."\n".$checksBody);
    $_POST=$fields;
    $normalPaths=InstallerFreshInputModel\setup_storage_candidates('');
    $baseName='cajunwebpro-private-'.substr(setup_binding(),0,12);
    fresh_check('Ordinary candidate model retains private then protected-local choices',array_column($normalPaths,'path')===['/fixture-account/'.$baseName,'/fixture-account/public_html/storage']);
    $_POST=$fields+['fresh_start'=>'1'];
    $freshPaths=InstallerFreshInputModel\setup_storage_candidates('');
    $expected='/fixture-account/'.$baseName.'-fresh-0123456789ab';
    fresh_check('Fresh candidate model uses a separate full-token suffix',array_column($freshPaths,'path')===[$expected]);
    fresh_check('Explicit-parent model uses the same separate fresh destination',array_column(InstallerFreshInputModel\setup_storage_candidates('/fixture-account'),'path')===[$expected]);
    fresh_check('Fresh candidate model never offers protected public storage',!in_array('/fixture-account/public_html/storage',array_column($freshPaths,'path'),true));
    $_POST['private_parent']='';$checkSelection=InstallerFreshInputModel\setup_checks();
    fresh_check('Server-check source selects the same fresh destination as preparation',$checkSelection['storage']===$expected&&$GLOBALS['fresh_model_selected']===$freshPaths);
    fresh_check('Checks and database selection retain one session-bound fresh choice',setup_database_input()===$fresh&&$_SESSION===$session);
    $_POST=$fields;$checkSelection=InstallerFreshInputModel\setup_checks();
    fresh_check('Clearing fresh intent returns checks and prefix to ordinary selection',$checkSelection['storage']==='/fixture-account/'.$baseName&&setup_database_input()===$ordinary);

    file_put_contents(CWP_ROOT.'/storage/media/retained.php',CWP_GUARD.'synthetic retained media');
    file_put_contents($sandbox.'/prior-private/install.lock.php',CWP_GUARD.'synthetic previous installation lock');
    file_put_contents($sandbox.'/prior-private/mail-key.php',CWP_GUARD.'synthetic retained key');
    $retained=fresh_files($sandbox);
    $_POST=[];
    fresh_denied('Ordinary preparation refuses existing media',fn()=>setup_prepare_storage(''),SetupStorageConflict::class,'Existing media was found. It is preserved; use recovery rather than a fresh install.');
    fresh_check('Ordinary refusal leaves every retained file and directory unchanged',fresh_files($sandbox)===$retained);
    $_POST=['fresh_start'=>'1'];
    fresh_check('Real temporary sandbox supplies no fresh private candidate',setup_storage_candidates('')===[]);
    $blocked=setup_prepare_storage('');
    fresh_check('Fresh intent fails closed when no permanent private location exists',$blocked['ok']===false&&$blocked['storage']===''&&$blocked['attempts']===[]);
    fresh_check('Unavailable fresh destination never adopts or alters prior storage',fresh_files($sandbox)===$retained);

    foreach(['config.php','.installed.php','storage/install.lock.php'] as $name){
        $path=CWP_ROOT.'/'.$name;file_put_contents($path,"<?php throw new RuntimeException('Guard must never execute');\n");
        foreach([false,true] as $freshIntent){
            $_POST=$freshIntent?['fresh_start'=>'1']:[];$_SESSION=['cwp_setup_fresh_token'=>'invalid'];
            $before=fresh_files($sandbox);$label=$name.($freshIntent?' with fresh intent':' by default');
            fresh_check('Installed existence guard detects '.$label,cwp_installer_installed());
            fresh_denied('Installed guard precedes token and preparation for '.$label,fn()=>setup_prepare_storage(''),RuntimeException::class,'This website is already installed. Keep its configuration and storage locks intact.');
            fresh_check('Installed refusal preserves all files for '.$label,fresh_files($sandbox)===$before);
        }
        $GLOBALS['fresh_model_real_guard']=true;$response=InstallerFreshInputModel\setup_checks();unset($GLOBALS['fresh_model_real_guard']);
        $private=array_values(array_filter($response['checks'],fn($row)=>$row['label']==='Private storage'));
        fresh_check('Server-check source reports the installed refusal for '.$name,count($private)===1&&$private[0]['ok']===false&&$private[0]['message']==='This website is already installed. Keep its configuration and storage locks intact.'&&$response['storage']===''&&fresh_files($sandbox)===$before);
        unlink($path);
    }
    fresh_check('Retained private lock and media survive every guard scenario',fresh_files($sandbox)===$retained);
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
finally{
    try{fresh_cleanup($sandbox,$temporary);}catch(Throwable $e){$failure=($failure??'').' Cleanup: '.$e->getMessage();}
}
$passed=!isset($failure)&&!in_array(false,array_column($checks,'passed'),true);
echo json_encode(['passed'=>$passed,'scope'=>'No DB: actual input/storage refusal helpers and sandbox lstat guard; candidate/check dispatch uses explicit read-only parent/package/probe seams. No real private-directory allocation, SQL, browser or old-table preservation claim.','php'=>PHP_VERSION,'checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
exit($passed?0:1);
