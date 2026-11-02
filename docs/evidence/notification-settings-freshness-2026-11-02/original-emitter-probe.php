<?php
/** Review probe: actual 3cc emitter/settings/configure functions; synthetic SQL and request contexts only. */
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT','/workspace/scratch/8cc2b4f257ab/Cajun_Web_integration_candidate/site');
require CWP_CODE_ROOT.'/app/settings/store.php';
require CWP_CODE_ROOT.'/app/notifications/feed.php';
$_GET=[];
final class EmitterProbeDb { public function inTransaction():bool{return $GLOBALS['probe_transaction'];} }
final class EmitterProbeStatement { public function rowCount():int{return 1;} }
function db():EmitterProbeDb{return new EmitterProbeDb();}
function table(string $name):string{return '`probe_'.$name.'`';}
function staff_ready():bool{return true;}
function studio_ready():bool{return true;}
function support_current():?array{return null;}
function admin_user():array{return ['id'=>1,'role'=>'owner'];}
function admin_session_matches(array $actor):bool{return $actor['id']===1;}
function staff_can(string $permission,array $actor):bool{return $actor['id']===1&&$permission==='staff.manage';}
function all(string $sql,array $args=[]):array {
    if($sql==='SELECT name,value FROM `probe_settings`'){
        $GLOBALS['probe_settings_reads']++;
        return array_map(fn($name,$value)=>['name'=>$name,'value'=>$value],array_keys($GLOBALS['probe_settings']),array_values($GLOBALS['probe_settings']));
    }
    if($sql==='SELECT kind,email FROM `probe_notification_prefs` WHERE audience=? AND recipient_id=?'&&$args===['client',11])return [];
    throw new LogicException('Unexpected all: '.$sql);
}
function one(string $sql,array $args=[]):?array {
    if($sql==='SELECT * FROM `probe_studio_clients` WHERE id=? AND active=1'&&$args===[11])return ['id'=>11,'active'=>1,'email'=>'synthetic@example.invalid'];
    if($sql==='SELECT id FROM `probe_studio_jobs` WHERE id=? AND client_id=?'&&$args===[9,11])return ['id'=>9];
    if($sql==='SELECT * FROM `probe_notification_feed` WHERE id=? FOR UPDATE'&&$GLOBALS['probe_transaction'])return $GLOBALS['probe_feed'][$args[0]]??null;
    throw new LogicException('Unexpected one: '.$sql);
}
function query(string $sql,array $args=[]):EmitterProbeStatement {
    if(!$GLOBALS['probe_transaction'])throw new LogicException('Unexpected nontransactional write');
    if(str_starts_with($sql,'INSERT INTO `probe_settings` (name,value)'))$GLOBALS['probe_settings'][$args[0]]=$args[1];
    elseif(str_starts_with($sql,'INSERT INTO `probe_audit`'))$GLOBALS['probe_audit'][]=$args;
    elseif(str_starts_with($sql,'INSERT INTO `probe_notification_feed`')){
        $GLOBALS['probe_feed'][$args[0]]=array_combine(['id','audience','recipient_id','kind','ref','title','body','created_at'],$args);
    }elseif(str_starts_with($sql,'INSERT INTO `probe_notification_deliveries`')){
        $channel=str_contains($sql,"'portal','sent'")?'portal':(str_contains($sql,"'email','pending'")?'email':null);
        if($channel===null)throw new LogicException('Unexpected delivery write');
        $GLOBALS['probe_deliveries'][]=['notification_id'=>$args[0],'channel'=>$channel,'state'=>$channel==='portal'?'sent':'pending'];
    }else throw new LogicException('Unexpected query: '.$sql);
    return new EmitterProbeStatement();
}
function staff_locked(callable $work):mixed{return $work();}
function staff_transaction(callable $work):mixed {
    if($GLOBALS['probe_transaction'])throw new LogicException('Nested probe transaction');
    $GLOBALS['probe_transaction']=true;
    try{return $work();}finally{$GLOBALS['probe_transaction']=false;}
}
function emitterScenario(string $name,?bool $ownerSetting,bool $fresh):array {
    unset($GLOBALS['cwp_settings']);
    $GLOBALS['probe_settings']=['notification_feed_version'=>'1','notification_email_enabled'=>'1','email_mode'=>'smtp'];
    $GLOBALS['probe_transaction']=false;$GLOBALS['probe_settings_reads']=0;$GLOBALS['probe_audit']=[];$GLOBALS['probe_feed']=[];$GLOBALS['probe_deliveries']=[];
    // Emitter request has already loaded ordinary application settings.
    setting('notification_feed_version');$emitterCache=$GLOBALS['cwp_settings'];
    if($ownerSetting!==null){
        // A separate owner request runs the actual configure path and commits
        // before the emitter begins. Its PHP cache is deliberately separate.
        unset($GLOBALS['cwp_settings']);notification_email_configure($ownerSetting);
        if(db()->inTransaction()||count($GLOBALS['probe_audit'])!==1)throw new LogicException('Owner update did not finish its audited transaction');
        $GLOBALS['cwp_settings']=$emitterCache;
    }
    $cachedBefore=$GLOBALS['cwp_settings'];$storedBefore=$GLOBALS['probe_settings'];
    if($fresh)unset($GLOBALS['cwp_settings']);
    notification_emit('client',11,'project-update','job:9','Private account update','Synthetic portal body','committed-event-42');
    if(db()->inTransaction())throw new LogicException('Emitter left an open transaction');
    return ['name'=>$name,'owner_change_committed_before_emission'=>$ownerSetting!==null,'stored_settings_before_emission'=>$storedBefore,'emitter_request_cache_before_emission'=>$cachedBefore,'cache_cleared_before_emission_by_probe'=>$fresh,'feed_rows'=>count($GLOBALS['probe_feed']),'portal_rows'=>count(array_filter($GLOBALS['probe_deliveries'],fn($r)=>$r['channel']==='portal')),'email_rows'=>count(array_filter($GLOBALS['probe_deliveries'],fn($r)=>$r['channel']==='email')),'owner_audits'=>count($GLOBALS['probe_audit']),'settings_database_reads'=>$GLOBALS['probe_settings_reads'],'synthetic_transport_calls'=>0];
}
$results=[emitterScenario('enabled control',null,false),emitterScenario('owner pause committed before stale-cache emission',false,false),emitterScenario('owner pause with fresh-cache emission control',false,true)];
if(array_column($results,'email_rows')!==[1,1,0]||array_column($results,'feed_rows')!==[1,1,1]||array_column($results,'portal_rows')!==[1,1,1])throw new LogicException('Probe did not reproduce expected source behavior');
$sources=[];
foreach(['app/settings/store.php','app/notifications/feed.php','app/notifications/outbox.php','includes/foundation/notifications/Notification.php','includes/foundation/notifications/NotificationPreferences.php'] as $path)$sources['site/'.$path]=hash_file('sha256',CWP_CODE_ROOT.'/'.$path);
echo json_encode(['candidate'=>'3cc10c144e2cd4e78ff5952cee9f60710a07a9ff','tree'=>'e93332563bc365ef15d0e6676ad644c466302d43','scope'=>'Actual published settings, owner configuration, emitter and recipient/prefs functions. Synthetic SQL and separate-request cache contexts; owner configure transaction completes before emission begins. No database/server/network/mail execution.','finding_reproduced'=>true,'sources_sha256'=>$sources,'scenarios'=>$results],JSON_PRETTY_PRINT).PHP_EOL;
