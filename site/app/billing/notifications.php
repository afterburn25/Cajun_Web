<?php
/** Durable sandbox-only notification inbox. Receipt never changes financial state. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;

require_once CWP_CODE_ROOT.'/database/schema/notifications_schema.php';
function notifications_ready(): bool {return attempts_ready()&&setting('payment_notifications_schema_version')==='1';}
require_once CWP_CODE_ROOT.'/database/migrations/notifications_migrate.php';
function notifications_config(): array {
    // Read under the caller's mutex; the per-request settings cache may predate another worker.
    $raw=one('SELECT value FROM '.table('settings').' WHERE name=?',['sandbox_notification_receiver'])['value']??'';
    return $raw===''?['revision'=>0,'enabled'=>false,'webhook_id'=>'','account_hash'=>'','gateway_revision'=>0]:json_decode($raw,true,8,JSON_THROW_ON_ERROR);
}
function notifications_uuid(mixed $value): bool {return is_string($value)&&preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di',$value)===1;}
function notifications_configure(int $revision,int $gatewayRevision,string $webhook,bool $enabled,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function() use($revision,$gatewayRevision,$webhook,$enabled,$password): void {
        $actor=payments_owner($password);if(!notifications_ready())throw new RuntimeException('Enable the sandbox inbox first.');
        if(PHP_SAPI!=='cli'&&!https_request())throw new RuntimeException('Receiver configuration requires HTTPS.');
        $old=notifications_config();if($old['revision']!==$revision)throw new RuntimeException('Receiver settings changed. Reload this page.');
        $next=$old;$next['revision']=$revision+1;$next['enabled']=$enabled;
        if($enabled){
            if(!notifications_uuid($webhook))throw new InvalidArgumentException('Enter the sandbox webhook ID from Authorize.net.');
            $credentials=attempts_credentials($gatewayRevision);
            if(!preg_match('/^[a-f0-9]{128}$/Di',$credentials['signature_key']))throw new RuntimeException('Save the sandbox Signature Key first.');
            $next['webhook_id']=strtolower($webhook);$next['account_hash']=attempts_account($credentials);$next['gateway_revision']=$gatewayRevision;
        }
        save_setting('sandbox_notification_receiver',json_encode($next,JSON_THROW_ON_ERROR));
        payments_audit((int)$actor['id'],'notifications.configure',($enabled?'Enabled':'Disabled').' sandbox receipt revision '.$next['revision'].'; no provider registration performed');
    }));
}
function notifications_supported(string $event): bool {
    return in_array($event,['net.authorize.payment.authcapture.created','net.authorize.payment.capture.created','net.authorize.payment.priorAuthCapture.created','net.authorize.payment.void.created','net.authorize.payment.fraud.held','net.authorize.payment.fraud.approved','net.authorize.payment.fraud.declined'],true);
}
/** Exact bytes, binary Signature Key, constant-time comparison; no payload re-serialization. */
function notifications_signature(#[\SensitiveParameter] string $body,#[\SensitiveParameter] string $header,#[\SensitiveParameter] string $key): bool {
    if(!preg_match('/^sha512=([a-f0-9]{128})$/Di',$header,$m)||!preg_match('/^[a-f0-9]{128}$/Di',$key))return false;
    return hash_equals(hash_hmac('sha512',$body,hex2bin($key)),strtolower($m[1]));
}
/** HTTP status only. Caller handles generic responses. No raw payload/signature enters storage or logs. */
function notifications_receive(#[\SensitiveParameter] string $body,#[\SensitiveParameter] string $signature): int {
    if($body===''||strlen($body)>32768)return 400;
    return staff_locked(fn()=>staff_transaction(function() use($body,$signature): int {
        if(!notifications_ready())return 503;
        $cfg=notifications_config();if(!$cfg['enabled'])return 503;
        $g=one('SELECT * FROM '.table('payment_gateways')." WHERE environment='sandbox'");
        if(!$g||(int)$g['revision']!==$cfg['gateway_revision']||$g['validation']!=='authenticated')return 503;
        $credentials=payments_unseal('sandbox',$g['credentials']);
        if(!hash_equals($cfg['account_hash'],attempts_account($credentials)))return 503;
        if(!notifications_signature($body,$signature,$credentials['signature_key']))return 401;
        try{$v=json_decode($body,true,16,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);}catch(JsonException){return 400;}
        if(!is_array($v)||!notifications_uuid($v['notificationId']??null)||!notifications_uuid($v['webhookId']??null))return 400;
        if(!hash_equals($cfg['webhook_id'],strtolower($v['webhookId'])))return 401;
        $event=$v['eventType']??null;$date=$v['eventDate']??null;
        if(!is_string($event)||!preg_match('/^net\.authorize\.[a-zA-Z.]{1,80}$/D',$event)||!is_string($date)||!preg_match('/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:\.\d{1,7})?Z$/D',$date,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))return 400;
        $supported=notifications_supported($event);$transaction=null;$reference=null;
        if($supported){
            $p=$v['payload']??null;if(!is_array($p)||($p['entityName']??null)!=='transaction')return 400;
            $id=$p['id']??null;if(is_int($id))$id=(string)$id;
            if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,19}$/D',$id))return 400;$transaction=$id;
            $ref=$p['merchantReferenceId']??null;if(is_string($ref)&&preg_match('/^CW[a-f0-9]{18}$/D',$ref))$reference=$ref;
        }
        $notification=strtolower($v['notificationId']);$digest=hash('sha256',$body);
        $prior=one('SELECT body_hash,account_hash FROM '.table('payment_notifications').' WHERE notification_id=?',[$notification]);
        if($prior)return hash_equals($prior['body_hash'],$digest)&&hash_equals($prior['account_hash'],$cfg['account_hash'])?200:409;
        query('INSERT INTO '.table('payment_notifications').' (notification_id,webhook_id,account_hash,gateway_revision,body_hash,event_type,event_date,transaction_id,reference,status,received_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$notification,$cfg['webhook_id'],$cfg['account_hash'],$cfg['gateway_revision'],$digest,$event,$date,$transaction,$reference,$supported?'queued':'ignored',gmdate('Y-m-d H:i:s')]);
        $id=(int)db()->lastInsertId();payments_audit(0,'notifications.received','Sandbox notification '.$id.' '.($supported?'queued':'ignored'));
        return 200;
    }));
}
/** Explicit owner action: current reporting facts, never the notification amount/status, determine observation. */
function notifications_verify(int $id,int $revision,#[\SensitiveParameter] string $password,?callable $transport=null): void {
    $start=staff_locked(function() use($id,$revision,$password,$transport): array {
        $actor=attempts_require($password,$transport);if(!notifications_ready())throw new RuntimeException('Enable the sandbox inbox first.');
        $n=one('SELECT * FROM '.table('payment_notifications').' WHERE id=?',[$id]);
        if(!$n||$n['status']==='ignored')throw new RuntimeException('Choose a supported queued notification.');
        if($n['status']==='verified')return [];
        $credentials=attempts_credentials($revision);
        if(!hash_equals($n['account_hash'],attempts_account($credentials)))throw new RuntimeException('Restore the original sandbox merchant credentials before verifying this notification.');
        $matches=all('SELECT * FROM '.table('payment_attempts')." WHERE environment='sandbox' AND account_hash=? AND (transaction_id=? OR reference=?)",[$n['account_hash'],$n['transaction_id'],$n['reference']]);
        if(count($matches)!==1)throw new RuntimeException('No unique sandbox attempt matches this notification. Investigate in the sandbox portal; no payment was changed.');
        $a=$matches[0];
        if(($a['transaction_id']!==null&&$a['transaction_id']!==$n['transaction_id'])||($n['reference']!==null&&$a['reference']!==$n['reference']))throw new RuntimeException('Notification identifiers conflict with the retained attempt.');
        if(!consume_rate('sandbox-notification-verify-'.$actor['id'],20,900))throw new RuntimeException('Wait before requesting more sandbox checks.');
        $a['event_count']=(int)query('SELECT COUNT(*) FROM '.table('payment_attempt_events').' WHERE attempt_id=?',[$a['id']])->fetchColumn();
        return [$n,$a,$credentials];
    });
    if(!$start)return;
    [$n,$a,$credentials]=$start;
    if(!authorize_sandbox_merchant($credentials,$transport))throw new RuntimeException('Sandbox merchant could not be verified. Notification remains queued.');
    $facts=authorize_sandbox_details($credentials,$a,$n['transaction_id'],$transport);
    if($facts['state']==='unknown')throw new RuntimeException('Current transaction details could not be matched and verified. Notification remains queued.');
    staff_locked(fn()=>staff_transaction(function() use($n,$a,$facts,$revision,$password,$transport): void {
        $actor=attempts_require($password,$transport);$current=attempts_credentials($revision);
        if(!hash_equals($n['account_hash'],attempts_account($current)))throw new RuntimeException('Sandbox credentials changed. Reload and verify again.');
        $row=one('SELECT * FROM '.table('payment_notifications').' WHERE id=? FOR UPDATE',[$n['id']]);
        if($row['status']==='verified')return;
        if($row['status']!=='queued')throw new RuntimeException('Notification state changed. Reload.');
        if(one('SELECT id FROM '.table('payment_attempts')." WHERE environment='sandbox' AND account_hash=? AND transaction_id=? AND id<>?",[$n['account_hash'],$n['transaction_id'],$a['id']]))throw new RuntimeException('Transaction already belongs to another attempt.');
        if(!attempts_record_locked($a,$facts,(int)$actor['id'],'notification'))throw new RuntimeException('A newer observation was recorded. Reload and verify again.');
        query('UPDATE '.table('payment_notifications')." SET status='verified',attempt_id=?,actor_id=?,verified_at=? WHERE id=?",[$a['id'],$actor['id'],gmdate('Y-m-d H:i:s'),$n['id']]);
        payments_audit((int)$actor['id'],'notifications.verified','Sandbox notification '.$n['id'].' attempt '.$a['id'].'; real invoice unchanged');
    }));
}
