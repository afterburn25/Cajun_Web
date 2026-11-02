<?php
/** Disposable native database; shared reader with synthetic transport, never a provider account. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';

if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/admin/controllers/providers.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
$private=__DIR__.'/../test-runtime/provider-cloud-providers-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-cloud-providers-',3),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pcpv_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic provider owner passphrase';$secret='SYNTHETIC_CLOUD_PROVIDER_KEY';$calls=0;$dnsCalls=0;$body='["provider-A","007","Provider_A"]';$status=200;
function cp_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function cp_deny(string $name,callable $fn):void{try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;cp_ok($name,true);return;}cp_ok($name,false);}
function cp_actor(int $id=1):void{$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function cp_count(string $name):int{return(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function cp_profile():string{global $password,$secret;return provider_settings_save('',0,'Cloud provider fixture',true,$secret,$password);}
/** Exercise the real cooldown without rewriting retained proof or production clocks. */
function cp_next():string{global $profile;$last=(int)query('SELECT MAX(started_epoch) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();$wait=$last+31-time();if($wait>0)sleep($wait);return $profile;}
function cp_dns():string{global $dnsCalls;++$dnsCalls;if(db()->inTransaction())throw new LogicException('DNS inside transaction');return '8.8.8.8';}
final class CpTransport implements HttpTransport{
    public function __construct(private $authorize){}
    public function send(TransportRequest $request):TransportResponse{
        global $calls,$body,$status;($this->authorize)();
        if(db()->inTransaction()||$request->url!=='https://api.20i.com/reseller/*/cloudProviders'||$request->method!=='GET'||$request->body!==null)throw new LogicException('Wrong fixed cloud provider boundary');
        ++$calls;return new TransportResponse($status,[],$body);
    }
}
function cp_read(array $row,string $key,string $address,callable $authorize,string $operation):InventorySnapshot{
    global $secret;if($key!==$secret||$address!=='8.8.8.8')throw new LogicException('Wrong credential/address');
    return InventoryReader::read(new CpTransport($authorize),new ProviderCredential('twenty-i',$row['id'],function()use($authorize,$key){$authorize();return $key;}),$operation,0,time());
}
function cp_refresh(string $id,?callable $resolver=null,?callable $transport=null):array{global $password;return provider_inventory_refresh_attempt($id,1,$password,$resolver??'cp_dns',$transport??'cp_read',InventoryRead::CLOUD_PROVIDERS_LIST);}
function cp_fault(string $action,callable $fn):bool{
    db()->exec('CREATE TRIGGER pcpv_failure BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic cloud provider audit failure'; END IF; END");
    try{$fn();return false;}catch(PDOException){return true;}finally{db()->exec('DROP TRIGGER pcpv_failure');}
}
/** Test-only transactional corruption always restores original bytes. */
function cp_damage(string $label,callable $mutate,?callable $read=null):void{
    db()->beginTransaction();try{$mutate();cp_deny($label,$read??fn()=>provider_cloud_providers_ready());}finally{db()->rollBack();}
    cp_ok($label.' exact rollback restores readiness',provider_cloud_providers_ready());
}
/** Fixtures can add a later intent without waiting or changing retained proof timestamps. */
function cp_intent(string $profile,int $epoch,?string $state=null,int $actor=1):string{
    return staff_locked(fn()=>staff_transaction(function()use($profile,$epoch,$state,$actor):string{
        $row=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$profile,'profile_revision'=>1,'actor_id'=>$actor,'started_at'=>gmdate('Y-m-d H:i:s',$epoch),'started_epoch'=>$epoch,'operation'=>InventoryRead::CLOUD_PROVIDERS_LIST];
        query('INSERT INTO '.table('provider_refreshes').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch,operation) VALUES (?,?,?,?,?,?,?)',array_values($row));
        provider_settings_audit($actor,'refresh_started','Refresh '.$row['id'].' profile '.$profile.' revision 1');provider_cloud_providers_started($row);
        if($state!==null)provider_inventory_record_transaction($row,$state,$state,'','unknown',0,0,'[]',0,null);
        return $row['id'];
    }));
}
try{
    foreach(schema_statements('pcpv_') as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    save_setting('studio_schema_version','2');foreach(default_settings() as $key=>$value)save_setting($key,$value);
    for($i=1;$i<=3;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Provider staff '.$i,'providers'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);foreach([2=>'administrator',3=>'owner'] as $id=>$role)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()))]);
    cp_actor();provider_settings_migrate($password);provider_inventory_migrate($password);$profile=cp_profile();
    cp_ok('Unprepared read does not create projection table',!provider_cloud_providers_ready()&&!provider_cloud_providers_table_exists());
    cp_deny('Unprepared refresh sends nothing',fn()=>cp_refresh($profile));
    db()->beginTransaction();save_setting('cloud_provider_rollback_probe','keep');cp_deny('Fresh preparation refuses caller transaction before DDL',fn()=>provider_cloud_providers_prepare($password));
    cp_ok('Preparation guard preserves caller transaction and absent table',db()->inTransaction()&&!provider_cloud_providers_table_exists());db()->rollBack();
    cp_ok('Caller rollback removes probe and no preparation marker appears',one('SELECT value FROM '.table('settings').' WHERE name=?',['cloud_provider_rollback_probe'])===null&&one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_providers_schema_version'])===null);
    cp_deny('Revoked owner refuses: Wrong preparation password refuses',fn()=>cwp_test_owner_revoked(fn()=>provider_cloud_providers_prepare('wrong')));
    cp_actor(2);cp_deny('Administrator cannot prepare',fn()=>provider_cloud_providers_prepare($password));cp_actor();
    cp_ok('Preparation audit failure rolls back marker',cp_fault('cloud_providers_prepared',fn()=>provider_cloud_providers_prepare($password))&&one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_providers_schema_version'])===null);
    provider_cloud_providers_prepare($password);provider_cloud_providers_prepare($password);
    cp_ok('Explicit preparation is valid and idempotent',provider_cloud_providers_ready()&&cp_count('provider_observation_cloud_providers')===0);
    cp_deny('Cloud persistence refuses missing caller transaction and mutex',fn()=>provider_cloud_providers_started([]));
    db()->beginTransaction();cp_deny('Cloud persistence refuses transaction without owning mutex',fn()=>provider_cloud_providers_started([]));db()->rollBack();
    cp_actor(2);cp_deny('Administrator cannot refresh',fn()=>cp_refresh($profile));cp_deny('Administrator cannot inspect saved identifiers',fn()=>provider_cloud_providers_view($profile));cp_actor();
    $_GET['support']='fixture';cp_deny('Support context cannot refresh',fn()=>cp_refresh($profile));unset($_GET['support']);
    $_SESSION=['client_id'=>1,'client_auth'=>'synthetic'];cp_deny('Client cannot inspect provider choices',fn()=>provider_cloud_providers_view($profile));cp_actor();
    cp_deny('Revoked owner refuses: Wrong refresh password refuses before DNS',fn()=>cwp_test_owner_revoked(fn()=>provider_inventory_refresh_attempt($profile,1,'wrong','cp_dns','cp_read',InventoryRead::CLOUD_PROVIDERS_LIST)));
    cp_deny('Stale revision refuses before DNS',fn()=>provider_inventory_refresh_attempt($profile,2,$password,'cp_dns','cp_read',InventoryRead::CLOUD_PROVIDERS_LIST));
    cp_ok('Denied starts retain no intent and make no network call',cp_count('provider_refreshes')===0&&$calls===0&&$dnsCalls===0);
    cp_ok('Start witness failure atomically prevents intent and DNS',cp_fault('cloud_providers_started',fn()=>cp_refresh($profile))&&cp_count('provider_refreshes')===0&&$dnsCalls===0);
    $receipt=cp_refresh($profile);$first=provider_cloud_providers_source($profile);$document=one('SELECT * FROM '.table('provider_observation_cloud_providers').' WHERE id=?',[$receipt['id']]);$view=provider_cloud_providers_view($profile);
    cp_ok('One fixed read stores a current exact ordered identifier document',$receipt['state']==='complete'&&$calls===1&&$dnsCalls===1&&$view['current']&&$view['saved']['identifiers']===['provider-A','007','Provider_A']&&$document['id']===$receipt['id']);
    cp_ok('Observation references common exact profile revision key and unknown coverage',$first['profile_id']===$profile&&(int)$first['profile_revision']===1&&$first['key_fingerprint']===provider_inventory_fingerprint($secret)&&$first['coverage']==='unknown'&&(int)$first['package_count']===3);
    $sqlWitness=one('SELECT '.provider_cloud_providers_finish_sql().' AS detail FROM '.table('provider_refreshes').' f JOIN '.table('provider_refresh_results').' r ON r.id=f.id LEFT JOIN '.table('provider_observation_cloud_providers').' p ON p.id=f.id WHERE f.id=?',[$receipt['id']])['detail'];
    cp_ok('Native SQL and PHP complete projection witness bytes agree',$sqlWitness===provider_cloud_providers_finish_detail($first,$document['proof'],$document['identifiers'],(int)$document['observed_epoch']));
    cp_deny('Immediate replay respects common profile cooldown',fn()=>cp_refresh($profile));cp_actor(3);cp_deny('Second owner cannot bypass profile cooldown',fn()=>cp_refresh($profile));cp_actor();
    cp_ok('Expired observation remains historical with exact identifiers',!provider_cloud_providers_view($profile,time()+901)['current']&&provider_cloud_providers_view($profile,time()+901)['saved']===$view['saved']);
    cp_damage('Changed retained identifiers refuse integrity check',fn()=>query('UPDATE '.table('provider_observation_cloud_providers').' SET identifiers=? WHERE id=?',['["different"]',$receipt['id']]),fn()=>provider_cloud_providers_view($profile));
    $foreign=bin2hex(random_bytes(16));query('INSERT INTO '.table('provider_profiles').' (id,provider_code,environment,revision,label,credentials,enabled,updated_by,updated_at) VALUES (?,?,?,?,?,?,?,?,?)',[$foreign,'synthetic-other','live',1,'Foreign fixture','',0,1,gmdate('Y-m-d H:i:s')]);
    foreach(['profile_id'=>$foreign,'profile_revision'=>2,'actor_id'=>3,'operation'=>'packages.list','started_epoch'=>time()-180] as $field=>$value)cp_damage('Changed original intent '.$field.' refuses',fn()=>query('UPDATE '.table('provider_refreshes').' SET `'.$field.'`=? WHERE id=?',[$value,$receipt['id']]));
    foreach(['scope'=>'wrong','state'=>'unavailable','reason'=>'changed','coverage'=>'complete','package_count'=>9,'key_fingerprint'=>str_repeat('a',64),'elapsed_ms'=>9999] as $field=>$value)cp_damage('Changed original result '.$field.' refuses',fn()=>query('UPDATE '.table('provider_refresh_results').' SET `'.$field.'`=? WHERE id=?',[$value,$receipt['id']]));
    cp_damage('Missing complete projection refuses',fn()=>query('DELETE FROM '.table('provider_observation_cloud_providers').' WHERE id=?',[$receipt['id']]));
    cp_damage('Missing start audit refuses',fn()=>query('DELETE FROM '.table('audit').' WHERE action=? AND detail=?',['provider.cloud_providers_started',provider_cloud_providers_start_detail($first)]));
    cp_damage('Missing finish audit refuses',fn()=>query('DELETE FROM '.table('audit').' WHERE action=? AND detail=?',['provider.cloud_providers_finished',provider_cloud_providers_finish_detail($first,$document['proof'],$document['identifiers'],(int)$document['observed_epoch'])]));
    cp_damage('Missing preparation audit refuses',fn()=>query('DELETE FROM '.table('audit').' WHERE action=?',['provider.cloud_providers_prepared']));
    cp_damage('Missing marker refuses reconstruction',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_cloud_providers_schema_version']));
    foreach(['started','finished'] as $kind){cp_damage('Missing '.$kind.' counter refuses',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_cloud_providers_'.$kind.'_count']));cp_damage('Changed '.$kind.' counter refuses',fn()=>save_setting('provider_cloud_providers_'.$kind.'_count','99'));}
    foreach(['refresh_started','refresh_finished'] as $action)cp_damage('Missing common '.$action.' audit refuses',fn()=>query('DELETE FROM '.table('audit').' WHERE action=?',['provider.'.$action]));
    foreach(['unavailable',null] as $state){
        $later=cp_intent($profile,time()-7200,$state,3);$laterSource=provider_cloud_providers_source($profile);
        cp_ok('Later '.($state??'unfinished').' intent wins despite older clock',provider_cloud_providers_view($profile)['latest']['id']===$later&&!provider_cloud_providers_view($profile)['current']&&provider_cloud_providers_view($profile)['saved']===$view['saved']);
        cp_damage('Reordered later '.($state??'unfinished').' sequence refuses before old choice revival',fn()=>query('UPDATE '.table('provider_refreshes').' SET seq=0 WHERE id=?',[$later]),fn()=>provider_cloud_providers_view($profile));
        cp_damage('Lost later '.($state??'unfinished').' intent cannot revive old choices',function()use($later):void{query('DELETE FROM '.table('provider_refresh_results').' WHERE id=?',[$later]);query('DELETE FROM '.table('provider_refreshes').' WHERE id=?',[$later]);});
        cp_damage('Paired later other-owner '.($state??'unfinished').' intent and dedicated start audit loss refuses',function()use($later,$laterSource):void{
            query('DELETE FROM '.table('audit').' WHERE action=? AND detail=?',['provider.cloud_providers_started',provider_cloud_providers_start_detail($laterSource)]);query('DELETE FROM '.table('provider_refresh_results').' WHERE id=?',[$later]);query('DELETE FROM '.table('provider_refreshes').' WHERE id=?',[$later]);
        });
    }
    $empty=cp_next();$body='[]';$emptyReceipt=cp_refresh($empty);cp_ok('Complete empty list has its own retained document',provider_cloud_providers_view($empty)['current']&&provider_cloud_providers_view($empty)['saved']['identifiers']===[]&&one('SELECT identifiers FROM '.table('provider_observation_cloud_providers').' WHERE id=?',[$emptyReceipt['id']])['identifiers']==='[]');
    $newKey=provider_settings_seal($empty,'live','DIFFERENT_SYNTHETIC_KEY');
    // Currentness checks for exact same profile use the separate empty observation, with fixture rollback.
    db()->beginTransaction();try{query('UPDATE '.table('provider_profiles').' SET revision=1,credentials=? WHERE id=?',[$newKey,$empty]);cp_ok('Changed credential cannot make retained choices current',!provider_cloud_providers_view($empty)['current']);}finally{db()->rollBack();}
    cp_ok('Restored exact credential restores current observation',provider_cloud_providers_view($empty)['current']);
    $invalid=cp_next();$body='["valid",false]';$bad=cp_refresh($invalid);cp_ok('One malformed item refuses whole list and retains previous empty observation',$bad['state']==='unavailable'&&!provider_cloud_providers_view($invalid)['current']&&provider_cloud_providers_view($invalid)['saved']['id']===$emptyReceipt['id']&&one('SELECT id FROM '.table('provider_observation_cloud_providers').' WHERE id=?',[$bad['id']])===null&&provider_cloud_providers_ready());
    $failed=cp_next();$body='private provider body';$status=401;$failure=cp_refresh($failed);cp_ok('Unavailable authentication keeps exact finish witness without new projection',$failure['state']==='unavailable'&&provider_cloud_providers_ready()&&!provider_cloud_providers_view($failed)['current']);$status=200;
    $revoked=cp_next();$body='["provider-A"]';$beforeCalls=$calls;$revokedResult=cp_refresh($revoked,function()use($revoked):string{query('UPDATE '.table('provider_profiles').' SET enabled=0 WHERE id=?',[$revoked]);return cp_dns();});cp_ok('Revocation after intent prevents send and retains witnessed access change',$revokedResult['state']==='access_changed'&&$calls===$beforeCalls&&provider_cloud_providers_ready());query('UPDATE '.table('provider_profiles').' SET enabled=1 WHERE id=?',[$revoked]);
    $auditProfile=cp_next();$beforeDocuments=cp_count('provider_observation_cloud_providers');cp_ok('Finish witness failure rolls back common result and projection',cp_fault('cloud_providers_finished',fn()=>cp_refresh($auditProfile))&&cp_count('provider_observation_cloud_providers')===$beforeDocuments&&provider_cloud_providers_view($auditProfile)['latest']['state']==='unfinished'&&provider_cloud_providers_ready());
    cp_next();$body='[]';cp_refresh($profile);cp_ok('A later successful explicit refresh restores current observation',provider_cloud_providers_view($profile)['current']);
    provider_settings_save($profile,1,'Changed profile',true,'',$password);cp_ok('Profile edit leaves saved choices historical',!provider_cloud_providers_view($profile)['current']&&provider_cloud_providers_view($profile)['saved']['identifiers']===[]);
    $serialized=json_encode([all('SELECT * FROM '.table('audit')),all('SELECT * FROM '.table('provider_observation_cloud_providers'))]);cp_ok('No password raw credential or private HTTP body is persisted',!str_contains($serialized,$secret)&&!str_contains($serialized,$password)&&!str_contains($serialized,'private provider body'));
    cp_ok('No write ledger or hosting package rows are produced',cp_count('provider_observation_packages')===0&&cp_count('provider_package_links')===0);
    $report=['scope'=>'Native disposable MariaDB/MySQL, synthetic fixed GET transport, production storage and authority; no real provider account.','checks'=>$checks];
    file_put_contents(__DIR__.'/../qa/provider-cloud-providers-native-results.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo count($checks)." native cloud provider checks passed\n";
}catch(Throwable $e){file_put_contents(__DIR__.'/../qa/provider-cloud-providers-native-results.json',json_encode(['checks'=>$checks,'failure'=>$e->getMessage()],JSON_PRETTY_PRINT));fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
