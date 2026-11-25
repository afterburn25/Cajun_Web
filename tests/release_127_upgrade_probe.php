<?php
/** Disposable upgrade fixture only. Never a route in the shipped application. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==3)exit(2);
$blockedTransports=['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','stream_socket_server','stream_socket_sendto','socket_create','socket_create_pair','socket_import_stream','socket_connect','socket_send','socket_sendto','socket_sendmsg','socket_write','ftp_connect','ftp_ssl_connect','ftp_nb_connect','imap_open','ssh2_connect','ldap_connect','pg_connect','dns_get_record','gethostbyaddr','gethostbyname','gethostbynamel','checkdnsrr','snmpget','snmpgetnext','snmpwalk','mail','exec','shell_exec','system','passthru','popen','proc_open','pcntl_exec'];
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOL))exit(2);
foreach($blockedTransports as $function)if(function_exists($function))exit(2);
$allowed=realpath(__DIR__.'/../test-runtime/release127-populated');$root=realpath($argv[1]);$mode=$argv[2];
if(!$allowed||!$root||!str_starts_with($root,$allowed.DIRECTORY_SEPARATOR)||is_link($argv[1])||!in_array($mode,['seed','snapshot','revoke','capabilities','release-state','candidate-data','candidate-replay','representative-data','representative-replay'],true))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';$c=config();
if($c['db']['name']!=='cwp_test'||$c['db']['host']!=='127.0.0.1'||!preg_match('/^ug127[rs]_$/D',$c['db']['prefix']))exit(2);
if(!str_starts_with($c['site_url'],'http://127.0.0.1:8093'))exit(2);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8093','SCRIPT_NAME'=>'/admin/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root;start_session();require_once $root.'/app/staff/accounts.php';
// Match the original admin application's workspace/catalog dependency loading.
require_once $root.'/app/clients/workspace.php';
$owner=one('SELECT * FROM '.table('users').' WHERE id=1');
if(!$owner||$owner['email']!=='organized@example.invalid')throw new RuntimeException('Disposable owner identity missing');
$_SESSION=['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$password='Synthetic organized upgrade passphrase';
function release127_table(string $name):void {
    if(!preg_match('/^[a-z][a-z0-9_]*$/D',$name)||!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))throw new RuntimeException('Required release fixture table is unavailable: '.$name);
}
function release127_candidate_state():array {
    $private=realpath(config()['storage']);if(!$private||is_link($private))throw new RuntimeException('Missing private fixture');
    $path=$private.'/release-127-candidate-retained.txt';$present=is_file($path)&&!is_link($path);
    return ['setting'=>setting('release_127_candidate_retained')==='1','private_present'=>$present,
        'private_sha256'=>$present?hash_file('sha256',$path):null,'private_bytes'=>$present?filesize($path):null,
        'audit_rows'=>(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['fixture.release127.candidate'])->fetchColumn(),
        'network_calls'=>0,'live_operations'=>0,'transport_guard'=>true];
}
function release127_candidate_create():array {
    if(CWP_VERSION!=='1.27.0')throw new RuntimeException('Candidate-era fixture requires 1.27.0 code.');
    $state=release127_candidate_state();if($state['setting']||$state['private_present']||$state['audit_rows']!==0)throw new RuntimeException('Candidate-era fixture already exists.');
    $private=realpath(config()['storage']);$path=$private.'/release-127-candidate-retained.txt';
    write_private_file($path,CWP_GUARD.'SYNTHETIC 1.27 CANDIDATE PRIVATE RECORD');
    try{
        db()->beginTransaction();save_setting('release_127_candidate_retained','1');
        query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (1,?,?,?)',['fixture.release127.candidate','Synthetic candidate-era database/private retention witness; no transport',gmdate('Y-m-d H:i:s')]);db()->commit();
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();@unlink($path);throw $error;}
    return release127_candidate_state();
}
function release127_representative_state():array {
    $keys=[
        'studio_client_registrations'=>['client_id',1],
        'studio_client_tokens'=>['token_digest',hash_hmac('sha256','client-token-v1|verify|'.str_repeat('1',64),config()['key'])],
        'studio_invoices'=>['reference','REL127-PAYMENT'],
        'payment_attempts'=>['request_id',127001],
        'payment_attempt_events'=>['event','release127_retained'],
        'invoice_payments'=>['request_key',hash('sha256','release127-retained-payment-attempt')],
        'gateway_receipts'=>['source_id',hash('sha256','release127-retained-source')],
        'notification_feed'=>['id',str_repeat('7',32)],
        'notification_deliveries'=>['notification_id',str_repeat('7',32)],
        'project_private_notes'=>['body','Synthetic retained private release note'],
        'mail_accounts'=>['id',str_repeat('a',32)],
        'mail_message_reads'=>['id',str_repeat('b',32)],
        'mail_drafts'=>['id',str_repeat('c',32)],
        'mail_reply_drafts'=>['id',str_repeat('c',32)],
    ];
    $rows=[];$counts=[];
    foreach($keys as $table=>$identity){release127_table($table);[$column,$value]=$identity;$row=one('SELECT * FROM '.table($table).' WHERE '.$column.'=?',[$value]);$counts[$table]=$row?1:0;if($row){ksort($row);$rows[$table]=$row;}}
    ksort($rows);ksort($counts);
    $origin=isset($rows['mail_reply_drafts']['origin_json'])?json_decode((string)$rows['mail_reply_drafts']['origin_json'],true):null;
    $states=[
        'identity_pending'=>(($rows['studio_client_registrations']['verified_at']??false)===null&&($rows['studio_client_tokens']['purpose']??null)==='verify'),
        'payment_attempt_unknown'=>(($rows['payment_attempts']['environment']??null)==='sandbox'&&($rows['payment_attempts']['state']??null)==='unknown'&&($rows['payment_attempt_events']['event']??null)==='release127_retained'),
        'gateway_receipt_verified'=>(($rows['studio_invoices']['status']??null)==='paid'&&($rows['gateway_receipts']['environment']??null)==='production'&&($rows['gateway_receipts']['status']??null)==='verified'&&($rows['invoice_payments']['kind']??null)==='gateway'&&($rows['gateway_receipts']['invoice_id']??null)===($rows['studio_invoices']['id']??null)&&($rows['invoice_payments']['invoice_id']??null)===($rows['studio_invoices']['id']??null)),
        'notification_portal_only'=>((int)query('SELECT COUNT(*) FROM '.table('notification_deliveries').' WHERE notification_id=?',[str_repeat('7',32)])->fetchColumn()===1&&($rows['notification_deliveries']['channel']??null)==='portal'),
        'private_note_retained'=>($counts['project_private_notes']===1),
        'mail_account_disabled'=>(isset($rows['mail_accounts'])&&(int)$rows['mail_accounts']['enabled']===0&&$rows['mail_accounts']['credentials']===''),
        'mail_reply_thread_bound'=>(is_array($origin)&&($origin['thread']['in_reply_to']??null)==='<release127@example.invalid>'&&($origin['thread']['references']??null)===['<release127@example.invalid>']&&($rows['mail_drafts']['status']??null)==='source'&&($rows['mail_reply_drafts']['status']??null)==='draft'),
    ];
    return ['marker'=>setting('release_127_representative_fixture')==='1','counts'=>$counts,
        'states'=>$states,
        'audit_rows'=>(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['fixture.release127.representative'])->fetchColumn(),
        'fingerprint'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)),
        'network_calls'=>0,'live_operations'=>0,'transport_guard'=>true];
}
function release127_representative_complete(array $state):bool {
    return $state['marker']&&$state['audit_rows']===1&&array_sum($state['counts'])===count($state['counts'])&&!in_array(false,$state['states'],true);
}
function release127_representative_create():array {
    if(CWP_VERSION!=='1.27.0')throw new RuntimeException('Representative fixture requires 1.27.0 code.');
    foreach(['client_identity_schema_version','payment_attempts_schema_version','invoice_ledger_schema_version','gateway_receipts_schema_version','notification_feed_version','project_private_notes_schema_version','mail_accounts_schema_version','mail_drafts_schema_version','mail_messages_schema_version','mail_replies_schema_version'] as $marker)if(setting($marker)!=='1')throw new RuntimeException('Required release schema is not current: '.$marker);
    $before=release127_representative_state();if($before['marker']||$before['audit_rows']!==0||array_sum($before['counts'])!==0)throw new RuntimeException('Representative release fixture already exists.');
    $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');$job=one('SELECT * FROM '.table('studio_jobs').' WHERE id=1');$invoice=one('SELECT * FROM '.table('studio_invoices').' WHERE id=1');
    if(!$client||!$job||!$invoice||(int)$job['client_id']!==1||(int)$invoice['job_id']!==1)throw new RuntimeException('Representative retained business records differ.');
    require_once CWP_ROOT.'/app/billing/gateway_receipts.php';
    require_once CWP_ROOT.'/includes/foundation/mail-compose/CopiedDraftSource.php';
    require_once CWP_ROOT.'/includes/foundation/mail-compose/DraftOrigin.php';
    $now=gmdate('Y-m-d H:i:s');$epoch=time();$emailFingerprint=hash_hmac('sha256','client-email-v1|'.strtolower((string)$client['email']),config()['key']);
    $token=hash_hmac('sha256','client-token-v1|verify|'.str_repeat('1',64),config()['key']);$attempt=hash('sha256','release127-retained-payment-attempt');$sandboxHash=hash('sha256','sandbox|release127-synthetic-sandbox-account');$gatewayAccountHash=hash('sha256','authorize-net|release127-synthetic-production-account');$sourceId=hash('sha256','release127-retained-source');
    $account=str_repeat('a',32);$read=str_repeat('b',32);$draft=str_repeat('c',32);$notification=str_repeat('7',32);
    $raw="From: Synthetic Source <source@example.invalid>\r\nTo: Retained Mail <retained-mail@example.invalid>\r\nSubject: Synthetic retained source\r\nMessage-ID: <release127@example.invalid>\r\nDate: Tue, 17 Nov 2026 12:00:00 +0000\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nSynthetic retained source body.\r\n";
    $copied=\CajunWebPro\Foundation\MailCompose\CopiedDraftSource::fromRead(1,$account,1,'127','1',$read,'reply',$raw);
    $origin=\CajunWebPro\Foundation\MailCompose\DraftOrigin::create($draft,1,$account,1,'127','1',$read,$account,1,$copied->draft);
    $content=\CajunWebPro\Foundation\MailCompose\DraftOrigin::contentJson($copied->draft->content);
    $contentFingerprint=hash('sha256',json_encode([$origin->fingerprint,$draft,1,$account,1,1,'draft',$content],JSON_THROW_ON_ERROR));
    try{
        db()->beginTransaction();
        query('INSERT INTO '.table('studio_client_registrations').' (client_id,email_fingerprint,verified_at,created_at) VALUES (?,?,NULL,?)',[1,$emailFingerprint,$epoch]);
        query('INSERT INTO '.table('studio_client_tokens').' (token_digest,client_id,purpose,email_fingerprint,auth_version,resume_json,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?)',[$token,1,'verify',$emailFingerprint,(string)$client['auth_version'],'[]',$epoch+31536000,$epoch]);
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,status,instructions,paid_note,created_at,paid_at) VALUES (1,?,?,1,\'USD\',\'issued\',\'Synthetic release-upgrade payment retention witness\',\'\',?,NULL)',['REL127-PAYMENT','Synthetic release payment',$now]);
        $releaseInvoiceId=(int)db()->lastInsertId();
        query('INSERT INTO '.table('payment_attempts').' (request_id,invoice_id,actor_id,attempt_key,reference,environment,account_hash,gateway_revision,amount_cents,currency,state,transaction_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[127001,$releaseInvoiceId,1,$attempt,'CW127000000000000001','sandbox',$sandboxHash,1,1,'USD','unknown',null,$now,$now]);
        $paymentAttempt=(int)db()->lastInsertId();query('INSERT INTO '.table('payment_attempt_events').' (attempt_id,actor_id,event,created_at) VALUES (?,?,?,?)',[$paymentAttempt,1,'release127_retained',$now]);
        $ledgerInvoice=ledger_invoice($releaseInvoiceId);$invoiceHash=ledger_invoice_hash($ledgerInvoice);$transaction='127000001';
        query('INSERT INTO '.table('invoice_payments').' (invoice_id,job_id,client_id,actor_id,kind,amount_cents,currency,method,external_hash,private_note,received_on,invoice_hash,invoice_reference,invoice_title,invoice_total_cents,client_name,request_key,request_hash,reversal_of,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,?)',[$releaseInvoiceId,1,1,0,'gateway',(int)$ledgerInvoice['amount_cents'],'USD','authorize_net',hash('sha256','authorize-net|'.$transaction),'Synthetic test-database gateway retention witness',gmdate('Y-m-d'),$invoiceHash,(string)$ledgerInvoice['reference'],(string)$ledgerInvoice['title'],(int)$ledgerInvoice['amount_cents'],(string)$client['name'],$attempt,hash('sha256','release127-ledger-payload'),$now]);
        $ledger=(int)db()->lastInsertId();
        $binding=['environment'=>'production','account_sha256'=>$gatewayAccountHash,'gateway_revision'=>1,'transaction_id'=>$transaction,'source'=>'release_upgrade','source_id'=>$sourceId,'attempt_key'=>$attempt,'transaction_status'=>'settledSuccessfully'];
        $readback=['transaction_id'=>$transaction,'transaction_type'=>'authCaptureTransaction','transaction_status'=>'settledSuccessfully','invoice_number'=>substr('CWP-'.$releaseInvoiceId,0,20),'amount_cents'=>(int)$ledgerInvoice['amount_cents'],'currency'=>'USD','customer_id'=>'release127-synthetic','customer_email'=>strtolower((string)$client['email'])];
        gateway_receipt_record($ledgerInvoice,$ledger,$binding,$readback);query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',['paid','Synthetic release-upgrade receipt '.$ledger,$now,$releaseInvoiceId]);
        query('INSERT INTO '.table('notification_feed').' (id,audience,recipient_id,kind,ref,title,body,created_at,read_at) VALUES (?,?,?,?,?,?,?,?,NULL)',[$notification,'staff',1,'project-update','job:1','Synthetic retained notification','No message was sent.',$now]);
        query('INSERT INTO '.table('notification_deliveries')." (notification_id,channel,state,attempts,last_error,sent_at,next_at,updated_at) VALUES (?,'portal','sent',1,'',?,NULL,?)",[$notification,$epoch,$now]);
        query('INSERT INTO '.table('project_private_notes').' (project_id,staff_id,created_epoch,body) VALUES (1,1,?,?)',[$epoch,'Synthetic retained private release note']);
        query('INSERT INTO '.table('mail_accounts').' (id,revision,label,address,owner_id,imap_host,imap_port,imap_encryption,imap_username,smtp_host,smtp_port,smtp_encryption,smtp_username,credentials,enabled,updated_by,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,1,?)',[$account,1,'Synthetic disabled retained mailbox','retained-mail@example.invalid',1,'imap.example.invalid',993,'ssl','retained-mail@example.invalid','smtp.example.invalid',465,'ssl','retained-mail@example.invalid','',$now]);
        query('INSERT INTO '.table('mail_account_revisions').' (account_id,revision,metadata,action,actor_id,recorded_at) VALUES (?,?,?,?,?,?)',[$account,1,'{"synthetic":true,"enabled":false}','create',1,$now]);
        query('INSERT INTO '.table('mail_message_reads').' (id,account_id,account_revision,actor_id,folder,uidvalidity,uid,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?)',[$read,$account,1,1,'INBOX','127','1',$now,$epoch]);
        query('INSERT INTO '.table('mail_message_results').' (id,outcome,result_code,finished_at,elapsed_ms,message_bytes) VALUES (?,?,?,?,?,?)',[$read,'success','ok',$now,1,strlen($raw)]);
        query('INSERT INTO '.table('mail_drafts')." (id,owner_id,account_id,account_revision,revision,status,create_fingerprint,to_json,cc_json,bcc_json,subject,body,created_at,updated_at) VALUES (?,?,?,?,1,'source',?,'[]','[]','[]','Copied message draft','',?,?)",[$draft,1,$account,1,$origin->fingerprint,$now,$now]);
        query('INSERT INTO '.table('mail_draft_revisions')." (id,draft_id,revision,account_id,account_revision,status,action,actor_id,recorded_at) VALUES (?,?,1,?,1,'draft','reply',1,?)",[str_repeat('d',32),$draft,$account,$now]);
        query('INSERT INTO '.table('mail_reply_drafts')." (id,source_account_id,source_read_id,revision,status,origin_json,content_json,content_fingerprint) VALUES (?,?,?,1,'draft',?,?,?)",[$draft,$account,$read,$origin->json,$content,$contentFingerprint]);
        save_setting('release_127_representative_fixture','1');query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (1,?,?,?)',['fixture.release127.representative','Synthetic identity, payment, notification, note, and disabled-mail retention witnesses; no transport',$now]);db()->commit();
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
    $after=release127_representative_state();if(!release127_representative_complete($after))throw new RuntimeException('Representative release fixture is incomplete.');return $after;
}
if($mode==='candidate-data'){
    echo json_encode(['mode'=>$mode,'created'=>true,'state'=>release127_candidate_create(),'network_calls'=>0,'live_operations'=>0],JSON_THROW_ON_ERROR);exit;
}
if($mode==='candidate-replay'){
    $before=release127_candidate_state();if(!$before['setting']||!$before['private_present']||$before['audit_rows']!==1)throw new RuntimeException('Candidate-era fixture is incomplete.');
    $refused=false;try{release127_candidate_create();}catch(RuntimeException $error){$refused=$error->getMessage()==='Candidate-era fixture already exists.';if(!$refused)throw $error;}
    $after=release127_candidate_state();$unchanged=$before===$after;if(!$refused||!$unchanged)throw new RuntimeException('Candidate-era replay changed retained data.');
    echo json_encode(['mode'=>$mode,'duplicate_refused'=>true,'unchanged'=>true,'state'=>$after,'network_calls'=>0,'live_operations'=>0],JSON_THROW_ON_ERROR);exit;
}
if($mode==='representative-data'){
    echo json_encode(['mode'=>$mode,'created'=>true,'state'=>release127_representative_create(),'network_calls'=>0,'live_operations'=>0],JSON_THROW_ON_ERROR);exit;
}
if($mode==='representative-replay'){
    $before=release127_representative_state();if(!release127_representative_complete($before))throw new RuntimeException('Representative release fixture is incomplete.');
    $refused=false;try{release127_representative_create();}catch(RuntimeException $error){$refused=$error->getMessage()==='Representative release fixture already exists.';if(!$refused)throw $error;}
    $after=release127_representative_state();$unchanged=$before===$after;if(!$refused||!$unchanged)throw new RuntimeException('Representative replay changed retained data.');
    echo json_encode(['mode'=>$mode,'duplicate_refused'=>true,'unchanged'=>true,'state'=>$after,'network_calls'=>0,'live_operations'=>0],JSON_THROW_ON_ERROR);exit;
}
if($mode==='seed'){
    if((int)query('SELECT COUNT(*) FROM '.table('studio_clients'))->fetchColumn()!==0)throw new RuntimeException('Refuse to seed a populated prefix');
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Retained synthetic client','upgrade-client@example.invalid',str_repeat('e',32),$now]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,stage,summary,milestones,created_at,updated_at) VALUES (1,?,?,?,?,?,?)',['Retained project','design','Preserved summary','Preserved milestones',$now,$now]);
    query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,instructions,created_at) VALUES (1,?,?,?,?,?)',['UPGRADE-RETAIN','Retained invoice',25500,'Original instructions',$now]);
    query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (1,?,?,?,?)',['admin',1,'Retained synthetic project message',$now]);
    require_once $root.'/app/billing/catalog.php';catalog_migrate($password);
    catalog_save(0,0,['sku'=>'UPGRADE-RETAIN','title'=>'Retained local service','family'=>'web-design','status'=>'draft','term'=>'once','currency'=>'USD','amount'=>'255.00','setup'=>'0.00','cost'=>'20.00','summary'=>'Original owner text','features'=>'Original feature','terms'=>'Original terms','sort_order'=>'7','provider_ref'=>'']);
    require_once $root.'/app/providers/settings.php';provider_settings_migrate($password);
    provider_settings_save('',0,'Synthetic retained provider',true,'SYNTHETIC_UPGRADE_PROVIDER_KEY',$password);
    require_once $root.'/app/providers/inventory.php';provider_inventory_migrate($password);
    require_once $root.'/app/providers/catalog_import.php';provider_catalog_import_prepare($password);
    if(is_file($root.'/app/licenses/service.php')){
        require_once $root.'/app/licenses/service.php';require_once $root.'/database/migrations/license_migrate.php';
        license_migrate($password);$key=license_create(1,'website_core',1);
        $license=license_get($key);license_register_domain((int)$license['id'],'retained.example.invalid',1);
    }
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Restricted synthetic administrator','restricted@example.invalid',password_hash($password,PASSWORD_DEFAULT),str_repeat('b',32),$now]);
    $staff=(int)db()->lastInsertId();
    query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,\'administrator\',1,?)',[$staff,'["clients.manage"]']);
    $legacyAccount=str_repeat('f',32);$legacyDraft=str_repeat('e',32);
    query('INSERT INTO '.table('mail_accounts').' (id,revision,label,address,owner_id,imap_host,imap_port,imap_encryption,imap_username,smtp_host,smtp_port,smtp_encryption,smtp_username,credentials,enabled,updated_by,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,1,?)',[$legacyAccount,1,'Published 1.26.1 retained mailbox','legacy-mail@example.invalid',1,'imap.example.invalid',993,'ssl','legacy-mail@example.invalid','smtp.example.invalid',465,'ssl','legacy-mail@example.invalid','',$now]);
    query('INSERT INTO '.table('mail_drafts')." (id,owner_id,account_id,account_revision,revision,status,create_fingerprint,to_json,cc_json,bcc_json,subject,body,created_at,updated_at) VALUES (?,?,?,?,1,'draft',?,'[]','[]','[]','Published 1.26.1 retained draft','Legacy plain-text body',?,?)",[$legacyDraft,1,$legacyAccount,1,hash('sha256','release127-legacy-draft'),$now,$now]);
    $private=realpath($c['storage']);if(!$private||str_starts_with($private,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Unsafe private fixture');
    write_private_file($private.'/retained-upload.php',CWP_GUARD.'SYNTHETIC ORIGINAL CUSTOMER UPLOAD');
    save_setting('owner_upgrade_retained_setting','Original site content — unchanged');
    log_event('fixture.seed','Synthetic upgrade records only; no provider or merchant call');
    echo json_encode(['seeded'=>true,'version'=>CWP_VERSION],JSON_THROW_ON_ERROR);exit;
}
if($mode==='revoke'){
    query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[str_repeat('f',32)]);
    echo json_encode(['revoked'=>true],JSON_THROW_ON_ERROR);exit;
}
if($mode==='capabilities'){
    require_once $root.'/app/providers/catalog_import.php';
    $p=provider_settings_profile();$row=one('SELECT * FROM '.table('provider_profiles').' WHERE id=?',[$p['id']]);
    $secret=provider_settings_unseal($row['id'],$row['environment'],$row['credentials']);
    $result=['version'=>CWP_VERSION,'provider_secret_retained'=>hash_equals('SYNTHETIC_UPGRADE_PROVIDER_KEY',$secret),'catalog_ready'=>catalog_ready(),'import_ready'=>provider_catalog_import_ready(),'studio_ready'=>studio_ready()];
    unset($secret);
    if(is_file($root.'/app/providers/package_types.php')){require_once $root.'/app/providers/package_types.php';$result['package_types_ready']=provider_package_types_ready();}
    echo json_encode($result,JSON_THROW_ON_ERROR);exit;
}
if($mode==='release-state'){
    $markers=['software_onboarding_version','client_identity_schema_version','hosting_storefront_version','gateway_receipts_schema_version','notification_feed_version','hosting_payments_schema_version','project_private_notes_schema_version','mail_copies_schema_version','mail_drafts_html_ready','mail_files_schema_version','mail_flags_schema_version','mail_inbox_schema_version','mail_messages_schema_version','mail_replies_schema_version'];
    $htmlColumn=one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$c['db']['prefix'].'mail_drafts','body_html']);
    $legacyDraft=one('SELECT * FROM '.table('mail_drafts').' WHERE id=?',[str_repeat('e',32)]);
    $state=['markers'=>[],'side_effect_rows'=>0,'hosting_payments_enabled'=>setting('hosting_payments_enabled')==='1',
        'mail_drafts_body_html'=>$htmlColumn!==null,
        'legacy_mail_draft'=>['present'=>$legacyDraft!==null,'subject'=>$legacyDraft['subject']??null,
            'body'=>$legacyDraft['body']??null,'body_html'=>$legacyDraft['body_html']??null]];
    foreach($markers as $marker)$state['markers'][$marker]=setting($marker);
    foreach(['studio_client_registrations','studio_client_tokens','software_upload_requests','gateway_receipts','notification_feed','notification_prefs','notification_deliveries','hosting_payment_charges','hosting_payment_attempts','hosting_payment_events','project_private_notes','mail_reply_drafts'] as $name){
        $exists=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$c['db']['prefix'].$name]);
        if($exists)$state['side_effect_rows']+=(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();
    }
    echo json_encode($state,JSON_THROW_ON_ERROR);exit;
}
$out=['version'=>CWP_VERSION,'tables'=>[],'private_files'=>[],'configuration_sha256'=>hash_file('sha256',$root.'/config.php'),'lock_sha256'=>hash_file('sha256',$root.'/.installed.php')];
$tables=all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,?)=? ORDER BY TABLE_NAME',[strlen($c['db']['prefix']),$c['db']['prefix']]);
foreach($tables as $entry){
    $name=substr($entry['TABLE_NAME'],strlen($c['db']['prefix']));
    if(!preg_match('/^[a-z][a-z0-9_]*$/D',$name))throw new RuntimeException('Unexpected table identity');
    if($name==='rate_limits')continue;
    $keys=array_column(all("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=? AND constraint_name='PRIMARY' ORDER BY ORDINAL_POSITION",[$entry['TABLE_NAME']]),'COLUMN_NAME');
    if(!$keys)throw new RuntimeException('Missing stable row identity: '.$name);
    $rows=[];
    foreach(all('SELECT * FROM '.table($name)) as $row){
        $identity=[];foreach($keys as $key)$identity[$key]=$row[$key];
        $columns=[];foreach($row as $column=>$value){if($name==='users'&&$column==='last_login')continue;$columns[$column]=hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
        ksort($columns);$rows[hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR))]=$columns;
    }
    ksort($rows);$out['tables'][$name]=['count'=>count($rows),'rows'=>(object)$rows];
}
$private=realpath($c['storage']);if(!$private||is_link($private))throw new RuntimeException('Missing private fixture');
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS)) as $file){
    $relative=str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($private)+1));
    if(preg_match('~^(?:structure-[^/]+|sessions)(?:/|$)~D',$relative))continue;
    if($file->isLink())throw new RuntimeException('Unexpected private symlink');
    if($file->isFile())$out['private_files'][$relative]=['sha256'=>hash_file('sha256',$file->getPathname()),'bytes'=>$file->getSize()];
}
ksort($out['private_files']);$out['private_files']=(object)$out['private_files'];echo json_encode($out,JSON_THROW_ON_ERROR);
