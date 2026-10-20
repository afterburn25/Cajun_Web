<?php
/** Installed-code companion only. No deployed route and no real provider transport. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==4)exit(2);
$port=getenv('CWP_TEST_PORT');if(!is_string($port)||!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
$repo=realpath(__DIR__.'/..');$root=realpath($argv[1]);$web=realpath($argv[3]);
$expected=$repo.'/test-runtime/registered-nameservers-upgrade/rns-upgrade/web';
if(!$repo||!$root||!$web||$web!==$expected||$root!==$web.'/subfolder')exit(2);
foreach([$root,$web,dirname($web),dirname(dirname($web)),$repo.'/test-runtime'] as $path)if(is_link($path))exit(2);
$mode=$argv[2];if(!in_array($mode,['preflight','snapshot','seed','verify','damage','refuse'],true))exit(2);
$checks=[];
function rnu_check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
if($mode==='preflight'){
    $db=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cwp_test;charset=utf8mb4',(string)getenv('CWP_TEST_USER'),(string)getenv('CWP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $s=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(table_name,5)=BINARY ?');$s->execute(['rnsu_']);
    rnu_check('Dedicated installed namespace is empty',(int)$s->fetchColumn()===0);
    echo json_encode(['passed'=>true,'checks'=>$checks],JSON_THROW_ON_ERROR);exit;
}
if(!is_file($root.'/config.php')||is_link($root.'/config.php'))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';
if(config()['db']['name']!=='cwp_test'||config()['db']['prefix']!=='rnsu_'||config()['db']['host']!=='127.0.0.1'||(int)config()['db']['port']!==(int)$port)exit(2);

/** Exact original row/column hashes; only existing authentication bookkeeping is excluded. */
function rnu_snapshot():array{
    $prefix=config()['db']['prefix'];$out=['version'=>CWP_VERSION,'tables'=>[],'private_files'=>[]];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(table_name,?)=BINARY ? ORDER BY table_name',[strlen($prefix),$prefix]) as $entry){
        $physical=$entry['TABLE_NAME'];$name=substr($physical,strlen($prefix));if(!preg_match('/\A[a-z][a-z0-9_]*\z/',$name))throw new RuntimeException('Unexpected fixture table.');
        if($name==='rate_limits')continue;
        $keys=array_column(all("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=? AND constraint_name='PRIMARY' ORDER BY ORDINAL_POSITION",[$physical]),'COLUMN_NAME');
        if(!$keys)throw new RuntimeException('Missing primary identity.');$rows=[];
        foreach(all('SELECT * FROM '.table($name)) as $row){$identity=[];foreach($keys as $key)$identity[$key]=$row[$key];$id=hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));$columns=[];
            foreach($row as $column=>$value){if($name==='users'&&$column==='last_login')continue;$columns[$column]=hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
            ksort($columns);if(isset($rows[$id]))throw new RuntimeException('Duplicate identity.');$rows[$id]=$columns;
        }ksort($rows);$out['tables'][$name]=['count'=>count($rows),'rows'=>(object)$rows];
    }
    $private=realpath((string)config()['storage']);if(!$private||is_link($private))throw new RuntimeException('Unexpected private root.');
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS)) as $file){
        $relative=str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($private)+1));
        if(preg_match('~^(?:structure-[^/]+|sessions)(?:/|$)~D',$relative))continue;
        if($file->isLink())throw new RuntimeException('Unexpected private link.');
        if($file->isFile())$out['private_files'][$relative]=['sha256'=>hash_file('sha256',$file->getPathname()),'bytes'=>$file->getSize()];
    }ksort($out['private_files']);$out['private_files']=(object)$out['private_files'];return $out;
}
function rnu_digest():string{return hash('sha256',json_encode(rnu_snapshot(),JSON_THROW_ON_ERROR));}
if($mode==='snapshot'){echo json_encode(['passed'=>true,'checks'=>[],'snapshot'=>rnu_snapshot()],JSON_THROW_ON_ERROR);exit;}
require CWP_CODE_ROOT.'/app/providers/registered_nameservers.php';

const RNU_PASSWORD='Synthetic nameserver upgrade owner passphrase';
const RNU_SECRET='SYNTHETIC_NAMESERVER_UPGRADE_KEY';
const RNU_LABEL='Nameserver upgrade fixture';
const RNU_SET=['ns1.synthetic.invalid','ns2.synthetic.invalid'];
function rnu_link():array{$p=one('SELECT id FROM '.table('provider_profiles').' WHERE label=?',[RNU_LABEL]);if(!$p)throw new RuntimeException('Missing original fixture profile.');$l=one('SELECT * FROM '.table('provider_registrar_links').' WHERE profile_id=?',[$p['id']]);if(!$l)throw new RuntimeException('Missing original fixture assignment.');return $l;}
function rnu_resolve():string{if(db()->inTransaction())throw new LogicException('DNS inside transaction.');return '8.8.8.8';}
function rnu_refresh(array $link):array{
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$link['profile_id']])->fetchColumn();$wait=$at+30-time();
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if($wait>31||db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()===1)throw new LogicException('Invalid real cooldown.');if($wait>0)sleep($wait);
    $p=provider_settings_load($link['profile_id']);$s=provider_registrar_latest($link['profile_id']);
    return registered_nameservers_refresh($link['id'],(int)$link['revision'],(int)$p['revision'],$s['id'],RNU_PASSWORD,'rnu_resolve','rnu_transport');
}
final class RnuTransport implements \CajunWebPro\Foundation\Provider\HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(\CajunWebPro\Foundation\Provider\TransportRequest $r):\CajunWebPro\Foundation\Provider\TransportResponse{($this->authorize)();registered_nameservers_guard(false);if(db()->inTransaction()||$r->method!=='GET'||$r->body!==null||$r->url!==$this->url)throw new LogicException('Unexpected synthetic request.');return new \CajunWebPro\Foundation\Provider\TransportResponse($GLOBALS['rnu_status']??200,[],$GLOBALS['rnu_body']);}
}
function rnu_transport(array $profile,#[SensitiveParameter] string $secret,string $address,callable $authorize,string $operation,?\CajunWebPro\Foundation\TwentyIRegistrarNameservers\RegisteredNameserverBinding $nameserverContext=null):\CajunWebPro\Foundation\TwentyIInventory\InventorySnapshot|\CajunWebPro\Foundation\TwentyIRegistrarNameservers\RegisteredNameserverObservation{
    if($secret!==RNU_SECRET||$address!=='8.8.8.8'||db()->inTransaction())throw new LogicException('Wrong synthetic credential boundary.');
    $credential=new \CajunWebPro\Foundation\Provider\ProviderCredential('twenty-i',$profile['id'],static function()use($authorize,$secret):string{$authorize();return $secret;});
    if($operation===\CajunWebPro\Foundation\TwentyIInventory\InventoryRead::DOMAINS_LIST)return \CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader::read(new RnuTransport($authorize,'https://api.20i.com/domain'),$credential,$operation,null,time());
    if($operation!==\CajunWebPro\Foundation\TwentyIRegistrarNameservers\RegisteredNameservers::OPERATION||!$nameserverContext)throw new LogicException('Wrong synthetic operation.');
    return \CajunWebPro\Foundation\TwentyIRegistrarNameservers\RegisteredNameservers::read(new RnuTransport($authorize,'https://api.20i.com/domain/701/nameservers'),$credential,$nameserverContext,time());
}
function rnu_verify():void{
    $link=rnu_link();rnu_check('All retained history validates with actual installed reader',registered_nameservers_ready());$p=registered_nameservers_project($link['id'],(int)$link['revision']);
    rnu_check('Unfinished newest intent continues masking older sets',$p['status']==='unfinished'&&$p['nameservers']===null&&array_column($p['history'],'state')===['unfinished','unavailable','complete']&&$p['history'][2]['nameservers']===RNU_SET);
    rnu_check('Renewal-date setup remains absent',!one('SELECT name FROM '.table('settings').' WHERE name=?',['domain_contracts_schema_version'])&&!array_filter(domain_contracts_tables()));
}
function rnu_denied(callable $call):bool{try{$call();}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;return true;}return false;}
function rnu_refuse():void{
    $before=rnu_digest();$link=rnu_link();
    rnu_check('Lost saved set refuses readiness',rnu_denied(fn()=>registered_nameservers_ready()));
    rnu_check('Lost saved set refuses explicit preparation',rnu_denied(fn()=>registered_nameservers_prepare(RNU_PASSWORD)));
    rnu_check('Lost saved set refuses owner projection',rnu_denied(fn()=>registered_nameservers_project($link['id'],(int)$link['revision'])));
    rnu_check('Refusals leave missing history and all other retained bytes untouched',$before===rnu_digest()&&(int)query('SELECT COUNT(*) FROM '.table('registered_nameserver_sets'))->fetchColumn()===0);
}
function rnu_missing_probes():void{
    $link=rnu_link();
    foreach(['binding','observed_audit','marker'] as $kind){$before=rnu_digest();staff_locked(function()use($kind,$link):void{db()->beginTransaction();try{
        if($kind==='binding'){query('DELETE FROM '.table('registered_nameserver_sets'));query('DELETE FROM '.table('registered_nameserver_bindings'));}
        elseif($kind==='observed_audit')query('DELETE FROM '.table('audit').' WHERE BINARY action=?',['registered_nameservers.observed']);
        else query('DELETE FROM '.table('settings').' WHERE name=?',['registered_nameservers_schema_version']);
        rnu_check('Missing '.$kind.' refuses the actual locked projection',rnu_denied(fn()=>registered_nameservers_project_locked($link['id'],(int)$link['revision'])));
    }finally{db()->rollBack();}});unset($GLOBALS['cwp_settings']);rnu_check('Probe rollback exactly restores '.$kind,$before===rnu_digest()&&registered_nameservers_ready());}
}

try{
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/subfolder/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$web;start_session();
    $actor=one('SELECT * FROM '.table('users').' WHERE email=?',['rns-upgrade@example.invalid']);
    if(!$actor||!password_verify(RNU_PASSWORD,$actor['password_hash']))throw new RuntimeException('Wrong installed owner.');
    $_SESSION=['uid'=>(int)$actor['id'],'auth_version'=>$actor['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];unset($GLOBALS['cwp_settings'],$GLOBALS['cwp_support']);
    provider_settings_owner(RNU_PASSWORD);
    if($mode==='seed'){
        rnu_check('Explicit fixture starts with no nameserver tables or marker',!array_filter(registered_nameservers_tables())&&!one('SELECT name FROM '.table('settings').' WHERE name=?',['registered_nameservers_schema_version']));
        provider_settings_migrate(RNU_PASSWORD);$profile=provider_settings_save('',0,RNU_LABEL,true,RNU_SECRET,RNU_PASSWORD);provider_inventory_migrate(RNU_PASSWORD);provider_registrar_prepare(RNU_PASSWORD);
        rnu_check('Provider prerequisites leave nameserver feature unprepared',!registered_nameservers_ready());
        registered_nameservers_prepare(RNU_PASSWORD);registered_nameservers_prepare(RNU_PASSWORD);
        rnu_check('Explicit nameserver setup is idempotent',registered_nameservers_ready()&&(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['registered_nameservers.prepared'])->fetchColumn()===1);
        require_once CWP_CODE_ROOT.'/app/clients/accounts.php';
        if(!studio_ready())studio_migrate();
        $clientId=studio_client_save(0,'','Nameserver upgrade client','rns-upgrade-client@example.invalid',true);
        $GLOBALS['rnu_body']='[{"id":701,"name":"upgrade.synthetic.invalid"}]';
        $source=provider_inventory_refresh_attempt($profile,1,RNU_PASSWORD,'rnu_resolve','rnu_transport',\CajunWebPro\Foundation\TwentyIInventory\InventoryRead::DOMAINS_LIST);
        rnu_check('Real registrar read completes through fixed synthetic GET',$source['state']==='complete');provider_registrar_link($profile,701,$clientId,1,$source['id'],RNU_PASSWORD);
        $link=one('SELECT * FROM '.table('provider_registrar_links').' WHERE profile_id=?',[$profile]);
        $GLOBALS['rnu_body']='{"result":["ns1.synthetic.invalid","ns2.synthetic.invalid"]}';$good=rnu_refresh($link);
        rnu_check('Real owner read retains exact set',$good['state']==='complete'&&registered_nameservers_project($link['id'],1)['nameservers']===RNU_SET);
        $GLOBALS['rnu_status']=403;$failed=rnu_refresh($link);$GLOBALS['rnu_status']=200;
        $projection=registered_nameservers_project($link['id'],1);
        rnu_check('Newest failure masks original saved set',$failed['state']==='unavailable'&&$projection['status']==='unavailable'&&$projection['nameservers']===null&&$projection['history'][1]['nameservers']===RNU_SET);
        db()->exec('CREATE TRIGGER rnsu_observed_fault BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='registered_nameservers.observed' THEN SET @rnsu_hits=COALESCE(@rnsu_hits,0)+1; SET @rnsu_sets=(SELECT COUNT(*) FROM ".table('registered_nameserver_sets')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic upgrade audit fault'; END IF; END");
        query('SET @rnsu_hits=0,@rnsu_sets=0');$fault=false;try{rnu_refresh($link);}catch(PDOException $error){$fault=$error->getCode()==='45000'&&(int)query('SELECT @rnsu_hits')->fetchColumn()===1&&(int)query('SELECT @rnsu_sets')->fetchColumn()===2;}finally{db()->exec('DROP TRIGGER rnsu_observed_fault');}
        rnu_check('Actual final audit failure rolls back payload and leaves intent',$fault&&(int)query('SELECT COUNT(*) FROM '.table('registered_nameserver_sets'))->fetchColumn()===1);
        rnu_verify();
    }elseif($mode==='verify'){
        rnu_verify();rnu_missing_probes();
    }elseif($mode==='damage'){
        rnu_verify();staff_locked(fn()=>staff_transaction(function():void{$id=one('SELECT id FROM '.table('registered_nameserver_sets'))['id'];$statement=query('DELETE FROM '.table('registered_nameserver_sets').' WHERE id=?',[$id]);if($statement->rowCount()!==1)throw new RuntimeException('Expected one deliberate lost set.');}));
        rnu_refuse();
    }else rnu_refuse();
    echo json_encode(['passed'=>true,'checks'=>$checks],JSON_THROW_ON_ERROR);
}catch(Throwable $error){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>['class'=>get_class($error),'file'=>basename($error->getFile()),'line'=>$error->getLine(),'sqlstate'=>$error instanceof PDOException?(string)$error->getCode():null]],JSON_THROW_ON_ERROR);exit(1);}
