<?php
/** Owner-approved background GET/package. The durable approval, never a browser session, is its identity. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/schedule_storage.php';
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};

final class ProviderScheduleAuthorityChanged extends RuntimeException {}
function provider_schedule_approval(array $ctx):?array {return $ctx['state']['approval']===null?null:provider_schedule_lookup($ctx['state']['approval']);}
function provider_schedule_paths():array {
    $document=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));$root=realpath(CWP_ROOT);$code=realpath(CWP_CODE_ROOT);
    if(!$document||!$root||$root!==$code||!is_dir($document))throw new RuntimeException('Review scheduled reads from the installed website with its verified document root.');
    $normal=static fn(string $p):string=>strtolower(str_replace('\\','/',rtrim($p,'/\\'))).'/';
    if(!str_starts_with($normal($root),$normal($document)))throw new RuntimeException('The installed website must be within its verified document root.');
    return [$document,$root];
}
/** Current database identity only: no admin_user(), session impersonation, password cache or private API key here. */
function provider_schedule_authority(array $ctx,array $approval,int $now):array {
    provider_schedule_guard();$d=$approval['data'];
    if(!$ctx['ready']||$ctx['state']['paused']||$ctx['state']['approval']!==$approval['id']||$ctx['state']['approval_sha256']!==$approval['sha256']||!$d['enabled'])throw new ProviderScheduleAuthorityChanged('Scheduled reads are disabled.');
    if($now<$approval['at']||$now<($ctx['last']['at']??0))throw new ProviderScheduleAuthorityChanged('Check the server clock before resuming scheduled reads.');
    if(!staff_ready()||!provider_settings_ready()||!provider_inventory_ready())throw new ProviderScheduleAuthorityChanged('Required account or inventory storage is unavailable.');
    $owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$approval['actor']]);$staff=$owner?staff_profile($owner):null;
    if(!$staff||$staff['role']!=='owner'||$staff['password_hash']===''||!hash_equals($d['owner_auth_sha256'],hash('sha256',$staff['auth_version'])))throw new ProviderScheduleAuthorityChanged('The approving owner’s access or password changed. Review and approve the schedule again.');
    try{$row=provider_settings_load($d['profile_id']);}catch(InvalidArgumentException){throw new ProviderScheduleAuthorityChanged('The approved provider connection is unavailable.');}
    if((int)$row['revision']!==$d['profile_revision']||(int)$row['enabled']!==1||$row['credentials']===''||!hash_equals($d['credential_sha256'],hash('sha256',$row['credentials'])))throw new ProviderScheduleAuthorityChanged('The saved provider settings or key changed. Review and approve the schedule again.');
    if(realpath(CWP_ROOT)!==$d['installation_root']||realpath(CWP_CODE_ROOT)!==$d['installation_root']||realpath($d['document_root'])!==$d['document_root']||!is_dir($d['document_root']))throw new ProviderScheduleAuthorityChanged('The approved installation paths changed. Review scheduled reads from the installed website.');
    $normal=static fn(string $p):string=>strtolower(str_replace('\\','/',rtrim($p,'/\\'))).'/';
    if(!str_starts_with($normal($d['installation_root']),$normal($d['document_root'])))throw new ProviderScheduleAuthorityChanged('The approved document root is no longer valid.');
    if(PHP_SAPI!=='cli'&&realpath((string)($_SERVER['DOCUMENT_ROOT']??''))!==$d['document_root'])throw new ProviderScheduleAuthorityChanged('The website document root changed. Review the schedule again.');
    return $row;
}
function provider_schedule_configure(int $expected,string $profile,int $revision,int $interval,bool $reviewed,#[SensitiveParameter] string $password):void {
    if(!$reviewed||!in_array($interval,[900,1800,3600,21600,86400],true))throw new InvalidArgumentException('Choose a schedule and approve repeated read-only access to the hosting list.');
    staff_locked(fn()=>staff_transaction(function()use($expected,$profile,$revision,$interval,$password):void{
        [$actor,$row]=provider_inventory_authority($profile,$revision,$password);$ctx=provider_schedule_state();
        if(!$ctx['ready']||$ctx['last']['sequence']!==$expected)throw new RuntimeException('Reload the current scheduled refresh settings.');
        if($ctx['state']['pending']!==null)throw new RuntimeException('Review the unfinished attempt before approving a new schedule.');
        [$document,$root]=provider_schedule_paths();provider_settings_vault_key();$at=time();if($at<$ctx['last']['at'])throw new RuntimeException('Check the server clock before approving scheduled reads.');
        $id=bin2hex(random_bytes(16));$data=['profile_id'=>$profile,'profile_revision'=>$revision,'credential_sha256'=>hash('sha256',$row['credentials']),'owner_auth_sha256'=>hash('sha256',$actor['auth_version']),'document_root'=>$document,'installation_root'=>$root,'enabled'=>true,'interval'=>$interval];
        $state=['approval'=>$id,'approval_sha256'=>null,'pending'=>null,'next'=>$at,'failures'=>0,'paused'=>false];
        provider_schedule_append($ctx,'approval',$id,(int)$actor['id'],$at,$data,$state,$id);
    }));
}
function provider_schedule_disable(int $expected,#[SensitiveParameter] string $password):void {
    staff_locked(fn()=>staff_transaction(function()use($expected,$password):void{
        $actor=provider_settings_owner($password);$ctx=provider_schedule_state();if(!$ctx['ready']||$ctx['last']['sequence']!==$expected)throw new RuntimeException('Reload the current scheduled refresh settings.');
        $s=$ctx['state'];$s['paused']=true;$s['next']=0;provider_schedule_append($ctx,'pause',bin2hex(random_bytes(16)),(int)$actor['id'],time(),[],$s);
    }));
}
/** A connection-scoped job mutex remains held through DNS, transport and commit, even beyond the durable lease. */
function provider_schedule_locked(callable $work):mixed {
    if(db()->inTransaction())throw new LogicException('Scheduled reads cannot inherit a transaction.');
    $key='cwp-inventory-job-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if((int)query('SELECT GET_LOCK(?,0)',[$key])->fetchColumn()!==1)return 'busy';
    try{return $work();}finally{query('SELECT RELEASE_LOCK(?)',[$key]);}
}
function provider_schedule_result_hash(string $id):string {
    $row=one('SELECT `'.implode('`,`',provider_schedule_result_columns()).'` FROM '.table('provider_refresh_results').' WHERE id=?',[$id]);if(!$row)throw new RuntimeException('Original scheduled result is missing.');
    foreach($row as &$v)$v=(string)$v;unset($v);return hash('sha256',provider_schedule_json($row));
}
function provider_schedule_start(array $ctx,array $approval,int $now):array {
    provider_schedule_guard(true);$row=provider_schedule_authority($ctx,$approval,$now);$owner=$approval['actor'];
    if(one('SELECT id FROM '.table('provider_refreshes').' WHERE profile_id=? AND started_epoch>? LIMIT 1',[$row['id'],$now-30])||(int)query('SELECT COUNT(*) FROM '.table('provider_refreshes').' WHERE actor_id=? AND started_epoch>?',[$owner,$now-3600])->fetchColumn()>=20)throw new ProviderScheduleAuthorityChanged('The inventory refresh cooldown is active.');
    $id=bin2hex(random_bytes(16));$started=['id'=>$id,'profile_id'=>$row['id'],'profile_revision'=>(int)$row['revision'],'actor_id'=>$owner,'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now,'operation'=>InventoryRead::PACKAGES_LIST];
    query('INSERT INTO '.table('provider_refreshes').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch,operation) VALUES (?,?,?,?,?,?,?)',array_values($started));provider_settings_audit($owner,'refresh_started','Refresh '.$id.' profile '.$row['id'].' revision '.$row['revision']);
    $s=$ctx['state'];$s['pending']=$id;$s['next']=0;$data=['approval_id'=>$approval['id'],'approval_sha256'=>$approval['sha256'],'profile_id'=>$row['id'],'profile_revision'=>(int)$row['revision'],'lease_until'=>$now+120];
    return ['start'=>provider_schedule_append($ctx,'start',$id,$owner,$now,$data,$s),'inventory'=>$started,'approval'=>$approval];
}
/** Inventory outcome, typed observations, audit, schedule outcome and next due time commit together. */
function provider_schedule_finish(array $receipt,string $code,?InventorySnapshot $snapshot,string $fingerprint,int $elapsed,bool $recovered=false,?int $reviewer=null):string {
    provider_schedule_guard(true);$ctx=provider_schedule_state();$start=$receipt['start'];$approval=$receipt['approval'];$id=$start['subject'];
    if($ctx['state']['pending']!==$id||provider_schedule_lookup($start['id'])!==$start)throw new RuntimeException('The scheduled attempt changed. Restore its original history.');
    $good=$snapshot!==null&&provider_inventory_observed($code);$self=$snapshot!==null&&$snapshot->state===$code;
    $reason=$recovered?'interrupted':($good?'':($self?$snapshot->reason:$code));
    provider_inventory_record_transaction($receipt['inventory'],$code,$reason,$self?$snapshot->scope:'',$good?$snapshot->coverage:'unknown',$good?count($snapshot->packages):0,$self?$snapshot->skipped:0,$self?json_encode(array_values($snapshot->skipReasons)):'[]',$elapsed,$good?$snapshot:null,$fingerprint);
    $at=max(time(),$ctx['last']['at']);$s=$ctx['state'];$s['pending']=null;
    $s['failures']=$code==='complete'?0:min(16,$s['failures']+1);
    if($code==='access_changed'||$recovered)$s['paused']=true;
    $delay=min(86400,$approval['data']['interval']*(2**min(6,$s['failures'])));$s['next']=$s['paused']?0:$at+$delay;
    $data=['start_id'=>$start['id'],'start_sha256'=>$start['sha256'],'result_sha256'=>provider_schedule_result_hash($id),'outcome'=>$code,'reason'=>$reason,'recovered'=>$recovered];
    provider_schedule_append($ctx,'finish',$id,$reviewer??$start['actor'],$at,$data,$s);return $code;
}
/** One due read at most. Test-only injected resolver/typed reader are never accepted from HTTP/CLI input. */
function provider_schedule_run(?callable $resolver=null,?callable $reader=null):string {
    return provider_schedule_locked(function()use($resolver,$reader):string{
        $receipt=staff_locked(fn()=>staff_transaction(function():array|string{
            $ctx=provider_schedule_state();if(!$ctx['ready'])return 'not_prepared';if($ctx['state']['pending']!==null)return 'needs_recovery';if($ctx['state']['paused'])return 'disabled';
            $approval=provider_schedule_approval($ctx);if(!$approval)return 'disabled';
            try{provider_schedule_authority($ctx,$approval,time());if(time()<$ctx['state']['next'])return 'not_due';return provider_schedule_start($ctx,$approval,time());}
            catch(ProviderScheduleAuthorityChanged){return 'approval_required';}
        }));
        if(is_string($receipt))return $receipt;
        $begin=hrtime(true);$address='';$code=null;
        try{$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];}catch(Throwable $e){if($e instanceof PDOException)throw $e;$code='unavailable';}
        return staff_locked(function()use($receipt,$reader,$begin,$address,$code):string{
            $elapsed=static fn():int=>(int)((hrtime(true)-$begin)/1e6);$approval=$receipt['approval'];
            $authorize=static function()use($approval,$receipt,$elapsed):array{
                if($elapsed()>=30000||time()>=$receipt['start']['data']['lease_until'])throw new ProviderScheduleAuthorityChanged('The scheduled read budget expired.');
                $ctx=provider_schedule_state();if($ctx['state']['pending']!==$receipt['start']['subject'])throw new ProviderScheduleAuthorityChanged('The scheduled attempt changed.');
                return provider_schedule_authority($ctx,$approval,time());
            };
            $snapshot=null;$fingerprint='';$hadRoot=array_key_exists('DOCUMENT_ROOT',$_SERVER);$oldRoot=$_SERVER['DOCUMENT_ROOT']??null;
            try{
                try{$row=$authorize();}catch(ProviderScheduleAuthorityChanged){$code='access_changed';}
                if($code===null){
                    // The saved canonical root comes only from owner approval on the installed website.
                    // It remains input to the original protected-vault validation; CLI arguments cannot replace it.
                    if(PHP_SAPI==='cli')$_SERVER['DOCUMENT_ROOT']=$approval['data']['document_root'];
                    try{$secret=provider_settings_key_value(provider_settings_unseal($row['id'],$row['environment'],$row['credentials']));$fingerprint=provider_inventory_fingerprint($secret);}
                    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$code='credential';}
                    if($code===null)try{
                        $snapshot=($reader??'provider_inventory_read')($row,$secret,$address,$authorize,InventoryRead::PACKAGES_LIST);
                        if(!$snapshot instanceof InventorySnapshot||$snapshot->scope!=='twenty-i|'.$row['id'].'|'.InventoryRead::PACKAGES_LIST)throw new LogicException('Invalid scheduled read scope.');
                        $code=$snapshot->state;
                    }catch(ProviderScheduleAuthorityChanged|ProviderInventoryAccessChanged){$code='access_changed';}
                    catch(Throwable $e){if($e instanceof PDOException)throw $e;$code='unavailable';}
                    finally{unset($secret);}
                }
                try{$authorize();}catch(ProviderScheduleAuthorityChanged){$code='access_changed';}
                return staff_transaction(fn()=>provider_schedule_finish($receipt,$code??'refused',$snapshot,$fingerprint,$elapsed()));
            }finally{if($hadRoot)$_SERVER['DOCUMENT_ROOT']=$oldRoot;else unset($_SERVER['DOCUMENT_ROOT']);}
        });
    });
}
function provider_schedule_recover(int $expected,string $pending,#[SensitiveParameter] string $password):string {
    return provider_schedule_locked(fn()=>staff_locked(fn()=>staff_transaction(function()use($expected,$pending,$password):string{
        $actor=provider_settings_owner($password);$ctx=provider_schedule_state();if(!$ctx['ready']||$ctx['last']['sequence']!==$expected||$ctx['state']['pending']!==$pending)throw new RuntimeException('Reload the unfinished scheduled attempt.');
        $row=one('SELECT * FROM '.table('provider_schedule_events')." WHERE kind='start' AND subject_id=?",[$pending]);$start=provider_schedule_event($row);
        if(time()<$start['data']['lease_until'])throw new RuntimeException('The active attempt still holds its lease. Wait for it to finish.');
        $inventory=one('SELECT id,profile_id,profile_revision,actor_id,started_at,started_epoch,operation FROM '.table('provider_refreshes').' WHERE id=?',[$pending]);
        return provider_schedule_finish(['start'=>$start,'inventory'=>$inventory,'approval'=>provider_schedule_lookup($start['data']['approval_id'])],'refused',null,'',0,true,(int)$actor['id']);
    })));
}
/** Owner-only bounded history. Internal paths and credential/auth hashes never enter this projection. */
function provider_schedule_context(int $page=1):array {
    if($page<1||$page>100000)throw new InvalidArgumentException('Choose an available scheduled refresh history page.');
    return staff_locked(function()use($page):array{
        provider_settings_manager();$ctx=provider_schedule_state();$a=$ctx['ready']?provider_schedule_approval($ctx):null;$status=$ctx['ready']?'disabled':'not_prepared';$message='Scheduled reads are disabled.';
        if($a&&!$ctx['state']['paused'])try{provider_schedule_authority($ctx,$a,time());$status=$ctx['state']['next']<=time()?'due':'scheduled';$message='The approved hosting list will be checked when the scheduled job is next run.';}catch(ProviderScheduleAuthorityChanged $e){$status='approval_required';$message=$e->getMessage();}
        $lease=null;if($ctx['state']['pending']!==null){$r=provider_schedule_event(one('SELECT * FROM '.table('provider_schedule_events')." WHERE kind='start' AND subject_id=?",[$ctx['state']['pending']]));$lease=$r['data']['lease_until'];$status=$lease>time()?'running':'needs_recovery';$message=$lease>time()?'A recorded attempt is still within its lease.':'An unfinished attempt needs owner review. No automatic retry will run.';}
        $history=[];if($ctx['ready'])foreach(all('SELECT * FROM '.table('provider_schedule_events').' ORDER BY sequence DESC LIMIT 21 OFFSET '.(($page-1)*20)) as $row){$e=provider_schedule_event($row);if($e['kind']==='finish'&&provider_schedule_result_hash($e['subject'])!==$e['data']['result_sha256'])throw new RuntimeException('Original scheduled result evidence differs.');$history[]=['kind'=>$e['kind'],'at'=>$e['at'],'actor'=>$e['actor'],'outcome'=>$e['data']['outcome']??null,'reason'=>$e['data']['reason']??null,'subject'=>$e['subject'],'recovered'=>$e['data']['recovered']??false];}
        return ['ready'=>$ctx['ready'],'sequence'=>$ctx['last']['sequence']??0,'status'=>$status,'message'=>$message,'pending'=>$ctx['state']['pending'],'lease'=>$lease,'next'=>$ctx['state']['next'],'failures'=>$ctx['state']['failures'],'interval'=>$a['data']['interval']??3600,'reviewer'=>$a['actor']??null,'reviewed_at'=>$a['at']??null,'history'=>$history];
    });
}
