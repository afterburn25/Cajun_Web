<?php
/** Review probe: real published functions, synthetic persistence/mutex and transport only. */
declare(strict_types=1);
define('CWP_BOOT',true);
$root='/workspace/scratch/8cc2b4f257ab/Cajun_Web_integration_candidate';
require $root.'/site/app/settings/store.php';
require $root.'/site/includes/foundation/notifications/NotificationPreferences.php';
require $root.'/site/includes/foundation/notifications/DeliveryAttempt.php';
require $root.'/site/app/notifications/outbox.php';
final class ProbeDb { public function inTransaction():bool{return $GLOBALS['transaction'];} }
final class ProbeStatement { public function rowCount():int{return 1;} }
function db():ProbeDb{return new ProbeDb();}
function table(string $name):string{return '`probe_'.$name.'`';}
function notification_owner():array{return ['id'=>1];}
function notifications_feed_require():void{}
function notification_recipient(string $audience,int $id,string $ref,string $kind):array{return ['email'=>'synthetic@example.invalid'];}
function notification_prefs_for(string $audience,int $id):CajunWebPro\Foundation\Notifications\NotificationPreferences{return new CajunWebPro\Foundation\Notifications\NotificationPreferences((string)$id,$audience);}
function site_url(string $path):string{return 'https://example.invalid/'.$path;}
function admin_url(string $view):string{return 'https://example.invalid/admin?view='.$view;}
function all(string $sql,array $args=[]):array {
    if($sql==='SELECT name,value FROM `probe_settings`'){
        $GLOBALS['settings_reads']++;
        return array_map(fn($name,$value)=>['name'=>$name,'value'=>$value],array_keys($GLOBALS['stored_settings']),array_values($GLOBALS['stored_settings']));
    }
    if(str_starts_with($sql,'SELECT notification_id FROM `probe_notification_deliveries`'))return [['notification_id'=>$GLOBALS['delivery']['notification_id']]];
    throw new LogicException('Unexpected probe SELECT-all: '.$sql);
}
function one(string $sql,array $args=[]):array {
    if(!$GLOBALS['transaction']||!str_starts_with($sql,'SELECT d.*,f.audience,f.recipient_id,f.kind,f.ref,f.title,f.body FROM `probe_notification_deliveries`'))throw new LogicException('Unexpected probe locked read');
    return $GLOBALS['delivery'];
}
function query(string $sql,array $args=[]):ProbeStatement {
    if(!$GLOBALS['transaction'])throw new LogicException('Unexpected nontransactional write');
    if(str_starts_with($sql,"UPDATE `probe_notification_deliveries` SET state='sending'")){$GLOBALS['delivery']['state']='sending';$GLOBALS['delivery']['attempts']++;$GLOBALS['delivery']['next_at']=null;}
    elseif(str_starts_with($sql,'INSERT INTO `probe_audit`'))$GLOBALS['audits'][]=$args;
    elseif(str_starts_with($sql,'UPDATE `probe_notification_deliveries` SET state=?,sent_at=?')){
        [$state,$sent,$next,$error]=$args;$GLOBALS['delivery']=array_replace($GLOBALS['delivery'],['state'=>$state,'sent_at'=>$sent,'next_at'=>$next,'last_error'=>$error]);
    }else throw new LogicException('Unexpected probe write: '.$sql);
    return new ProbeStatement();
}
function staff_transaction(callable $work):mixed {
    if($GLOBALS['transaction'])throw new LogicException('Nested probe transaction');
    $GLOBALS['transaction']=true;
    try{return $work();}finally{$GLOBALS['transaction']=false;}
}
function staff_locked(callable $work):mixed {
    // An independent owner transaction has committed while this request waits
    // for GET_LOCK. A different request cannot invalidate our PHP globals.
    foreach($GLOBALS['concurrent_settings'] as $key=>$value)$GLOBALS['stored_settings'][$key]=$value;
    $GLOBALS['settings_at_lock']=$GLOBALS['stored_settings'];
    $GLOBALS['cached_at_lock']=$GLOBALS['cwp_settings']??null;
    if($GLOBALS['clear_cache_at_lock'])unset($GLOBALS['cwp_settings']);
    return $work();
}
function scenario(string $name,array $change,bool $clear):array {
    unset($GLOBALS['cwp_settings']);
    $GLOBALS['stored_settings']=['notification_email_enabled'=>'1','email_mode'=>'smtp'];
    $GLOBALS['concurrent_settings']=$change;$GLOBALS['clear_cache_at_lock']=$clear;
    $GLOBALS['transaction']=false;$GLOBALS['settings_reads']=0;$GLOBALS['audits']=[];$GLOBALS['transport_calls']=0;
    $id=str_repeat('a',32);
    $GLOBALS['delivery']=['id'=>1,'notification_id'=>$id,'channel'=>'email','state'=>'pending','attempts'=>0,'last_error'=>'','sent_at'=>null,'next_at'=>time()-1,'audience'=>'client','recipient_id'=>1,'kind'=>'project-update','ref'=>'job:1','title'=>'Private account update','body'=>'No private payload sent'];
    $result=notification_outbox_pump(1,static function():string {
        if(db()->inTransaction()||$GLOBALS['delivery']['state']!=='sending'||count($GLOBALS['audits'])!==1)throw new LogicException('Probe did not execute the committed claim boundary');
        $GLOBALS['transport_calls']++;return 'accepted';
    });
    return ['name'=>$name,'concurrent_committed_settings'=>$GLOBALS['settings_at_lock'],'request_cache_at_lock'=>$GLOBALS['cached_at_lock'],'cache_cleared_at_lock_by_probe'=>$clear,'actual_dispatch_result'=>$result[$id],'synthetic_transport_calls'=>$GLOBALS['transport_calls'],'settings_database_reads'=>$GLOBALS['settings_reads'],'final_delivery_state'=>$GLOBALS['delivery']['state']];
}
$results=[scenario('enabled control',[],false),scenario('concurrent owner pause',['notification_email_enabled'=>'0'],false),scenario('concurrent outbound disable',['email_mode'=>'disabled'],false),scenario('pause with fresh-settings boundary control',['notification_email_enabled'=>'0'],true),scenario('disable with fresh-settings boundary control',['email_mode'=>'disabled'],true)];
if(array_column($results,'synthetic_transport_calls')!==[1,1,1,0,0])throw new LogicException('Probe did not reproduce expected source behavior');
$sources=[];foreach(['site/app/settings/store.php','site/app/notifications/outbox.php','site/includes/foundation/notifications/DeliveryAttempt.php','site/includes/foundation/notifications/NotificationPreferences.php'] as $path)$sources[$path]=hash_file('sha256',$root.'/'.$path);
echo json_encode(['candidate'=>'3cc10c144e2cd4e78ff5952cee9f60710a07a9ff','tree'=>'e93332563bc365ef15d0e6676ad644c466302d43','scope'=>'Actual published settings and notification dispatcher functions; synthetic persistence, authorized-owner/mutex dependencies and a recording transport callback. No database/server/network/mail execution.','finding_reproduced'=>true,'sources_sha256'=>$sources,'scenarios'=>$results],JSON_PRETTY_PRINT).PHP_EOL;
