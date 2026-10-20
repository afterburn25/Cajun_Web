<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$root=realpath($argv[1]);$base=realpath(__DIR__.'/../test-runtime');
if(!$root || !$base || !str_starts_with($root,$base.DIRECTORY_SEPARATOR))exit(2);
// Optional real web-server boundary for the release-only root/subfolder fixture.
// Legacy callers keep their existing per-mode defaults below.
$documentRoot=null;
if(array_key_exists(3,$argv)){
    $documentRoot=realpath($argv[3]);
    if(!$documentRoot || !is_dir($documentRoot) || !str_starts_with($documentRoot,$base.DIRECTORY_SEPARATOR)
        || !in_array($documentRoot,[$root,dirname($root)],true))exit(2);
}
define('CWP_BOOT',true);define('CWP_ROOT',$root);
require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';
if(config()['db']['name']!=='cwp_test')exit(2);
if(($argv[2]??'')==='recurring'){
    $saved=[];foreach(['recurring_billing_sources'=>'id','recurring_billing_schedules'=>'id','recurring_billing_events'=>'schedule_id,revision','recurring_billing_invoices'=>'id','studio_invoices'=>'id','invoice_payments'=>'id'] as $name=>$order)$saved[$name]=all('SELECT * FROM '.table($name).' ORDER BY '.$order);
    echo json_encode($saved,JSON_THROW_ON_ERROR);exit;
}
if(in_array($argv[2]??'',['owner-rate-exhaust','owner-rate-reset'],true)){
    // Synthetic CLI fixture only: isolate this localhost updater's time window.
    // Keep every other limiter and all business/private data unchanged.
    $_SERVER['REMOTE_ADDR']='127.0.0.1';
    $window=(int)floor(time()/900);$key=ip_key('structure-owner');
    foreach([-1,0,1] as $offset){
        $bucket=hash('sha256',$key.'|'.($window+$offset));
        query('DELETE FROM '.table('rate_limits').' WHERE bucket=?',[$bucket]);
        // Include the next window so crossing a clock boundary cannot evade the check.
        if($argv[2]==='owner-rate-exhaust' && $offset>=0){
            query('INSERT INTO '.table('rate_limits').' (bucket,attempts,expires) VALUES (?,10,?)',[$bucket,($window+$offset+1)*900]);
        }
    }
    echo json_encode(['isolated_owner_window'=>true],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='payments'){
    $saved=['marker'=>(one('SELECT value FROM '.table('settings')." WHERE name='payments_schema_version'")['value']??null),'tables'=>[]];
    foreach(['payment_gateways','payment_requests','invoice_payments'] as $name){
        if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
            $saved['tables'][$name]=all('SELECT * FROM '.table($name).' ORDER BY id');
        }
    }
    echo json_encode($saved,JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='plans'){
    $saved=[];
    foreach(['service_plans','service_plan_events','service_subscriptions','service_subscription_events'] as $name){
        $saved[$name]=all('SELECT * FROM '.table($name).' ORDER BY id');
    }
    echo json_encode($saved,JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='planning'){
    $saved=[];foreach(['studio_plan_events'=>'job_id,revision','studio_plan_requests'=>'id','studio_plan_heads'=>'id','activity_reads'=>'id'] as $name=>$order)$saved[$name]=all('SELECT * FROM '.table($name).' ORDER BY '.$order);
    echo json_encode($saved,JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='financial'){
    $financialReads=all('SELECT * FROM '.table('activity_reads')." WHERE event_key LIKE 'financial:%' ORDER BY id");
    $saved=[];foreach(['design_payment_events'=>'job_id,sequence','design_payment_heads'=>'id','design_payment_invoices'=>'id','studio_invoices'=>'id','invoice_payments'=>'id'] as $name=>$order)$saved[$name]=all('SELECT * FROM '.table($name).' ORDER BY '.$order);$saved['financial_activity_reads']=$financialReads;
    echo json_encode($saved,JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='progress'){
    $saved=[];
    foreach(['studio_jobs'=>'id','studio_progress_events'=>'job_id,revision','studio_progress_requests'=>'id','studio_progress_heads'=>'id','activity_reads'=>'id'] as $name=>$order)$saved[$name]=all('SELECT * FROM '.table($name).' ORDER BY '.$order);
    echo json_encode($saved,JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='tickets'){
    $saved=[];
    foreach(['support_tickets'=>'id','support_ticket_events'=>'revision','support_ticket_staff_reads'=>'ticket_id,user_id','support_ticket_client_reads'=>'ticket_id,client_id'] as $name=>$order){
        $saved[$name]=all('SELECT * FROM '.table($name).' ORDER BY '.$order);
    }
    echo json_encode($saved,JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='submissions'){
    echo json_encode(['intents'=>all('SELECT * FROM '.table('mail_submissions').' ORDER BY id'),'dispatches'=>all('SELECT * FROM '.table('mail_submission_dispatches').' ORDER BY id'),'results'=>all('SELECT * FROM '.table('mail_submission_results').' ORDER BY id')],JSON_THROW_ON_ERROR);exit;
}
if(in_array($argv[2]??'',['drafts-seed','submissions-seed'],true)){
    require_once $root.'/app/mail/drafts.php';
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$documentRoot??$root;
    start_session();$actor=one('SELECT auth_version FROM '.table('users').' WHERE id=1');
    $_SESSION=['uid'=>1,'auth_version'=>$actor['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $account=one('SELECT * FROM '.table('mail_accounts').' ORDER BY id LIMIT 1');
    if($argv[2]==='drafts-seed'){
        $password='Synthetic organized upgrade passphrase';
        $fields=array_intersect_key($account,array_flip(['label','address','imap_host','imap_port','imap_encryption','imap_username','smtp_host','smtp_port','smtp_encryption','smtp_username']));
        $fields['smtp_host']='smtp.example.net';$fields['imap_port']=(string)$fields['imap_port'];$fields['smtp_port']=(string)$fields['smtp_port'];
        mail_accounts_save($account['id'],(int)$account['revision'],$fields,true,['imap_password'=>'','smtp_password'=>''],$password);
        $grant=one('SELECT * FROM '.table('mail_account_grants').' WHERE account_id=? AND user_id=1',[$account['id']]);
        mail_accounts_grant($account['id'],1,(int)$grant['revision'],true,true,true,0,$password);
        $account=one('SELECT * FROM '.table('mail_accounts').' WHERE id=?',[$account['id']]);
        $id=bin2hex(random_bytes(16));$discard=bin2hex(random_bytes(16));
        $content=static fn(string $body)=>new \CajunWebPro\Foundation\MailCompose\DraftContent(['retained@example.invalid'],[],['private@example.invalid'],'Original 1.21 draft',$body);
        mail_drafts_save($id,0,$account['id'],(int)$account['revision'],$content('Original 1.21 first text'));
        mail_drafts_save($id,1,$account['id'],(int)$account['revision'],$content('Original 1.21 edited text'));
        mail_drafts_save($discard,0,$account['id'],(int)$account['revision'],$content('Original 1.21 discarded text'));mail_drafts_discard($discard,1);
    }else{
        require_once $root.'/app/mail/submissions.php';
        foreach(['accepted','unknown','unresolved','abandoned'] as $state){
            $draft=bin2hex(random_bytes(16));$id=bin2hex(random_bytes(16));
            $content=new \CajunWebPro\Foundation\MailCompose\DraftContent(['retained@example.invalid'],[],['private@example.invalid'],'Synthetic upgrade '.$state,'SYNTHETIC_UPGRADE_PRIVATE_BODY');
            mail_drafts_save($draft,0,$account['id'],(int)$account['revision'],$content);
            mail_submissions_prepare($id,$draft,1,(int)$account['revision']);
            if($state!=='abandoned'){
                query('UPDATE '.table('mail_submission_dispatches').' SET started_epoch=0');$claim=mail_submissions_claim($id);
                if(in_array($state,['accepted','unknown'],true))mail_submissions_record(new \CajunWebPro\Foundation\MailSubmission\TransportOutcome($claim['message'],$claim['row']['attempt_id'],$state,'confirmation',$state==='accepted'?'accepted':'timeout',$state==='accepted'?250:null));
            }
        }
    }
    echo json_encode(['fixture'=>'Synthetic persisted history only; no DNS credentials or SMTP transmission'],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='drafts'){
    echo json_encode(['drafts'=>all('SELECT * FROM '.table('mail_drafts').' ORDER BY id'),'history'=>all('SELECT * FROM '.table('mail_draft_revisions').' ORDER BY draft_id,revision')],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='provider-seed'){
    require_once $root.'/app/providers/checks.php';
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=$documentRoot??$root;
    start_session();$actor=one('SELECT auth_version FROM '.table('users').' WHERE id=1');
    $_SESSION=['uid'=>1,'auth_version'=>$actor['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $profile=provider_settings_profile();
    if(!$profile)throw new RuntimeException('Create the original provider profile through the authenticated owner form before seeding check history');
    $code=provider_checks_check($profile['id'],(int)$profile['revision'],'Synthetic organized upgrade passphrase',fn()=>'8.8.8.8',fn()=>\CajunWebPro\Foundation\Provider\ConnectionCheck::unknown('timeout'));
    if($code!=='timeout')throw new RuntimeException('Unexpected synthetic provider check result');
    staff_locked(fn()=>staff_transaction(function()use($profile):void {
        $started=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$profile['id'],'profile_revision'=>(int)$profile['revision'],'actor_id'=>1,'started_at'=>gmdate('Y-m-d H:i:s'),'started_epoch'=>time()];
        query('INSERT INTO '.table('provider_checks').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch) VALUES (?,?,?,?,?,?)',array_values($started));
        provider_settings_audit(1,'check_started','Synthetic incomplete check '.$started['id']);
    }));
    echo json_encode(['fixture'=>'Synthetic timeout and incomplete intent; no provider request'],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='provider'){
    echo json_encode(['profiles'=>all('SELECT * FROM '.table('provider_profiles').' ORDER BY id'),'history'=>all('SELECT * FROM '.table('provider_revisions').' ORDER BY profile_id,revision'),'checks'=>all('SELECT * FROM '.table('provider_checks').' ORDER BY id'),'results'=>all('SELECT * FROM '.table('provider_check_results').' ORDER BY id')],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='notices-seed'){
    require_once $root.'/app/mail/notices.php';
    $_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
    start_session();$actor=one('SELECT auth_version FROM '.table('users').' WHERE id=1');
    $_SESSION=['uid'=>1,'auth_version'=>$actor['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    $account=one('SELECT id,revision FROM '.table('mail_accounts').' ORDER BY id LIMIT 1');
    staff_locked(function()use($account):void {
        $started=['id'=>str_repeat('b',32),'account_id'=>$account['id'],'account_revision'=>(int)$account['revision'],'actor_id'=>1,'started_at'=>gmdate('Y-m-d H:i:s'),'started_epoch'=>time()];
        query('INSERT INTO '.table('mail_inbox_reads').' (id,account_id,account_revision,actor_id,started_at,started_epoch) VALUES (?,?,?,?,?,?)',array_values($started));
        $batch=['folder'=>'INBOX','uidvalidity'=>'1234','total'=>2,'messages'=>array_map(fn($uid)=>['uid'=>$uid,'seen'=>false,'bytes'=>'128','headers_truncated'=>false,'subject'=>'Synthetic recovery header','from'=>'Synthetic recovery sender','date'=>''],['82','81'])];
        mail_inbox_record($started,'ok',1,$batch);
    });
    $item=mail_notices_feed()[0];mail_notices_dismiss($item['handle'],$item);
    echo json_encode(['fixture'=>'Synthetic committed observation and local dismissal; no network attempted'],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='notices'){
    echo json_encode(['windows'=>all('SELECT * FROM '.table('mail_notice_windows').' ORDER BY account_id'),'items'=>all('SELECT * FROM '.table('mail_notice_items').' ORDER BY handle'),'states'=>all('SELECT * FROM '.table('mail_notice_states').' ORDER BY handle,actor_id')],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='messages-seed'){
    require_once $root.'/app/mail/messages.php';
    $account=one('SELECT id,revision FROM '.table('mail_accounts').' ORDER BY id LIMIT 1');
    $started=['id'=>str_repeat('a',32),'account_id'=>$account['id'],'account_revision'=>(int)$account['revision'],'actor_id'=>1,'folder'=>'INBOX','uidvalidity'=>'1234','uid'=>'82','started_at'=>gmdate('Y-m-d H:i:s'),'started_epoch'=>time()];
    query('INSERT INTO '.table('mail_message_reads').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?)',array_values($started));
    mail_message_record($started,'connection',1);
    echo json_encode(['fixture'=>'Synthetic failed message request only; no network attempted'],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='messages'){
    echo json_encode(['requests'=>all('SELECT * FROM '.table('mail_message_reads').' ORDER BY id'),'results'=>all('SELECT * FROM '.table('mail_message_results').' ORDER BY id')],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='inbox-seed'){
    require_once $root.'/app/mail/inbox.php';
    $account=one('SELECT id,revision FROM '.table('mail_accounts').' ORDER BY id LIMIT 1');
    $started=['id'=>str_repeat('f',32),'account_id'=>$account['id'],'account_revision'=>(int)$account['revision'],'actor_id'=>1,'started_at'=>gmdate('Y-m-d H:i:s'),'started_epoch'=>time()];
    query('INSERT INTO '.table('mail_inbox_reads').' (id,account_id,account_revision,actor_id,started_at,started_epoch) VALUES (?,?,?,?,?,?)',array_values($started));
    mail_inbox_record($started,'connection',1);
    echo json_encode(['fixture'=>'Synthetic failed inbox request only; no network attempted'],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='inbox'){
    echo json_encode(['requests'=>all('SELECT * FROM '.table('mail_inbox_reads').' ORDER BY id'),'results'=>all('SELECT * FROM '.table('mail_inbox_results').' ORDER BY id')],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='mail-checks-seed'){
    require_once $root.'/app/mail/connections.php';
    $account=one('SELECT id,revision FROM '.table('mail_accounts').' ORDER BY id LIMIT 1');
    $started=['id'=>str_repeat('e',32),'account_id'=>$account['id'],'account_revision'=>(int)$account['revision'],'target'=>'imap','actor_id'=>1,'started_at'=>gmdate('Y-m-d H:i:s'),'started_epoch'=>time()];
    query('INSERT INTO '.table('mail_connection_checks').' (id,account_id,account_revision,target,actor_id,started_at,started_epoch) VALUES (?,?,?,?,?,?,?)',array_values($started));
    mail_connections_record($started,'connection',1);
    echo json_encode(['fixture'=>'Synthetic failed-check history only; no network attempted'],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='mail-checks'){
    echo json_encode(['checks'=>all('SELECT * FROM '.table('mail_connection_checks').' ORDER BY id'),'results'=>all('SELECT * FROM '.table('mail_connection_results').' ORDER BY id')],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='appointments'){
    echo json_encode(['settings'=>all('SELECT * FROM '.table('appointment_settings')),'settings_history'=>all('SELECT * FROM '.table('appointment_settings_revisions')),'appointments'=>all('SELECT * FROM '.table('appointments')),'history'=>all('SELECT * FROM '.table('appointment_revisions'))],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='appointment-intake'){
    echo json_encode(one('SELECT * FROM '.table('leads').' WHERE reference=?',['ORGANIZED-APPT']),JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='reviews'){
    echo json_encode(['reviews'=>all('SELECT * FROM '.table('website_reviews')),'reports'=>all('SELECT * FROM '.table('website_review_reports')),'history'=>all('SELECT * FROM '.table('website_review_revisions'))],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='review-intake'){
    echo json_encode(one('SELECT * FROM '.table('leads').' WHERE reference=?',['ORGANIZED-REVIEW']),JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='templates'){
    echo json_encode(['templates'=>all('SELECT * FROM '.table('mail_templates')),'history'=>all('SELECT * FROM '.table('mail_template_revisions'))],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='mail-accounts'){
    echo json_encode(['accounts'=>all('SELECT * FROM '.table('mail_accounts')),'history'=>all('SELECT * FROM '.table('mail_account_revisions')),'grants'=>all('SELECT * FROM '.table('mail_account_grants')),'grant_history'=>all('SELECT * FROM '.table('mail_grant_revisions'))],JSON_THROW_ON_ERROR);exit;
}
if(($argv[2]??'')==='seed'){
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Organized retained client','organized-client@example.invalid',str_repeat('e',32),$now]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,stage,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[1,'Organized retained website','design','Retain summary','Retain progress',$now,$now]);
    query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,instructions,created_at) VALUES (1,?,?,?,?,?)',['ORGANIZED-RETAIN','Retained website invoice',25500,'Preserve invoice instructions',$now]);
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'mail_templates'])){
        $id=str_repeat('c',32);$values=['Earlier saved template','Earlier subject','Retained 1.12.0 draft','draft','personal',1];
        query('INSERT INTO '.table('mail_templates').' (id,revision,name,subject,body,status,visibility,owner_id,updated_by,updated_at) VALUES (?,1,?,?,?,?,?,?,1,?)',[$id,...$values,$now]);
        query('INSERT INTO '.table('mail_template_revisions').' (template_id,revision,name,subject,body,status,visibility,owner_id,action,actor_id,recorded_at) VALUES (?,1,?,?,?,?,?,?,?,1,?)',[$id,...$values,'create',$now]);
    }
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'mail_accounts'])){
        require_once $root.'/app/mail/accounts.php';$_SERVER['DOCUMENT_ROOT']=$documentRoot??dirname($root);$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/admin/index.php','REQUEST_METHOD'=>'GET'];start_session();
        $actor=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$actor['auth_version'],'login_at'=>time(),'last_activity'=>time()];
        $password='Synthetic organized upgrade passphrase';
        $id=mail_accounts_save('',0,['label'=>'Earlier saved email account','address'=>'earlier@example.invalid','imap_host'=>'imap.example.invalid','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'earlier-incoming','smtp_host'=>'smtp.example.invalid','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'earlier-outgoing'],true,['imap_password'=>'Synthetic earlier incoming password','smtp_password'=>'Synthetic earlier outgoing password'],$password);
        mail_accounts_grant($id,1,0,true,false,true,0,$password);
        query('INSERT INTO '.table('leads')." (reference,request_key,name,email,service,budget,timeline,message,consent,ip_hash,status,notes,created_at) VALUES (?,?,?,?,'Website review','Agreed','Flexible','Original review inquiry text',1,?,'new','Original private review note',?)",['ORGANIZED-REVIEW',str_repeat('a',64),'Earlier review prospect','review-upgrade@example.invalid',str_repeat('b',64),$now]);
    }
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'website_reviews'])){
        require_once $root.'/app/marketing/reviews.php';
        $lid=(int)one('SELECT id FROM '.table('leads').' WHERE reference=?',['ORGANIZED-REVIEW'])['id'];
        reviews_adopt($lid,'example.org/retained');reviews_stage($lid,1,'in-review');reviews_save_report($lid,2,'Earlier saved human review',[['title'=>'Earlier observation','detail'=>'Retained human-authored review detail','evidence'=>'human-review','source'=>'','observed_at'=>'']]);
        query('INSERT INTO '.table('leads')." (reference,request_key,name,email,service,budget,timeline,message,consent,ip_hash,status,notes,created_at) VALUES (?,?,?,?,'Consultation request','Agreed','Flexible','Original consultation preference text',1,?,'new','Original private consultation note',?)",['ORGANIZED-APPT',str_repeat('d',64),'Earlier consultation prospect','appointment-upgrade@example.invalid',str_repeat('b',64),$now]);
    }
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'appointment_settings'])){
        require_once $root.'/app/marketing/appointments.php';
        $hours=[];for($day=0;$day<7;$day++)$hours[$day]=['enabled'=>'1','start'=>'00:00','end'=>'24:00'];
        appointments_configure(1,['timezone'=>'UTC','slot_minutes'=>'30','buffer_minutes'=>'15','notice_minutes'=>'0','enabled'=>'1','hours'=>$hours],$password);
        $lid=(int)one('SELECT id FROM '.table('leads').' WHERE reference=?',['ORGANIZED-APPT'])['id'];
        appointments_adopt($lid,(int)(ceil((time()+86400)/1800)*1800),'America/Chicago');appointments_mutate($lid,1,'confirm',null,null,$password);
    }
}
$result=[];
foreach(['settings','services','projects','studio_clients','studio_jobs','studio_invoices'] as $name){
    $rows=query('SELECT * FROM '.table($name))->fetchAll();
    $encoded=array_map(fn($row)=>json_encode($row,JSON_THROW_ON_ERROR),$rows);sort($encoded);
    $result[$name]=['count'=>count($rows),'sha256'=>hash('sha256',json_encode($encoded,JSON_THROW_ON_ERROR))];
    if(in_array($name,['studio_clients','studio_jobs','studio_invoices'],true))$result[$name]['rows']=array_map(fn($row)=>substr($row,0,160),array_slice($encoded,0,12));
}
echo json_encode($result,JSON_THROW_ON_ERROR);
