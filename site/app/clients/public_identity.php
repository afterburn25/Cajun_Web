<?php
/** Public self-registration, email verification, and primary-client recovery. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/catalog_login.php';
require_once CWP_CODE_ROOT.'/app/mail/transport.php';

function client_identity_ready(): bool {
    return studio_ready()&&contacts_ready()&&setting('client_identity_schema_version')==='1';
}

/** Public requests are offered only when their bounded delivery path is usable. */
function client_identity_request_ready(): bool {
    if(!client_identity_ready())return false;
    $mode=setting('email_mode','disabled');$from=setting('mail_from');
    if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))return false;
    if($mode==='mail')return function_exists('mail')&&function_exists('pcntl_async_signals')
        &&function_exists('pcntl_signal')&&function_exists('pcntl_signal_get_handler')&&function_exists('pcntl_alarm');
    if($mode!=='smtp')return false;
    $host=setting('smtp_host');$port=(int)setting('smtp_port','587');$security=setting('smtp_security','tls');
    return preg_match('/^[a-zA-Z0-9.-]+$/D',$host)===1&&strlen($host)<=253
        &&in_array($port,[465,587,2525],true)&&in_array($security,['tls','ssl'],true)
        &&(setting('smtp_user')===''||setting('smtp_password')!=='');
}

function client_identity_email(string $email): string {
    $email=strtolower(trim($email));
    if($email===''||strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email address.');
    return $email;
}

function client_identity_email_fingerprint(string $email): string {
    return hash_hmac('sha256','client-email-v1|'.client_identity_email($email),config()['key']);
}

function client_identity_token_digest(string $purpose,#[\SensitiveParameter] string $token): string {
    if(!in_array($purpose,['verify','recover'],true)||!preg_match('/\A[a-f0-9]{64}\z/D',$token))throw new InvalidArgumentException('This security link is invalid.');
    return hash_hmac('sha256','client-token-v1|'.$purpose.'|'.$token,config()['key']);
}

function client_identity_destination(array $query): array {
    return hosting_catalog_login_destination($query);
}

function client_identity_destination_json(array $destination): string {
    $normalized=client_identity_destination($destination);
    $json=json_encode($normalized,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    if(strlen($json)>300)throw new LogicException('Client identity destination is too large.');
    return $json;
}

function client_identity_destination_from_json(string $json): array {
    try{$decoded=json_decode($json,true,8,JSON_THROW_ON_ERROR);}catch(Throwable){throw new RuntimeException('This security link cannot resume its saved destination.');}
    if(!is_array($decoded)||array_is_list($decoded)&&$decoded!==[])throw new RuntimeException('This security link cannot resume its saved destination.');
    $normalized=client_identity_destination($decoded);
    if(!hash_equals(client_identity_destination_json($normalized),$json))throw new RuntimeException('This security link cannot resume its saved destination.');
    return $normalized;
}

/** Best-effort read-only public summary. Checkout performs the authoritative recheck after sign-in. */
function client_identity_public_offer(array $destination): ?array {
    $destination=client_identity_destination($destination);
    if(($destination['hosting']??null)!=='checkout')return null;
    try{
        require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers_context.php';
        require_once CWP_CODE_ROOT.'/app/hosting/catalog_checkout_storage.php';
        require_once CWP_CODE_ROOT.'/app/billing/recurring_billing.php';
        return recurring_billing_locked(function()use($destination):?array {
            $publication=hosting_checkout_publication($destination['offer']);
            if(!hash_equals($publication->fingerprint(),$destination['offer_sha256']))return null;
            $scope=hosting_catalog_read_scope([$publication->data()['product']['id']]);
            return $publication->publicProjection(hosting_catalog_current_context($publication,$scope),time());
        });
    }catch(PDOException $e){report_error($e);return null;}
    catch(RuntimeException|InvalidArgumentException){return null;}
    catch(Throwable $e){report_error($e);return null;}
}

function client_identity_url(string $route,#[\SensitiveParameter] string $token,array $destination): string {
    if(!in_array($route,['verify','reset'],true)||!preg_match('/\A[a-f0-9]{64}\z/D',$token))throw new LogicException('Invalid client identity link.');
    return studio_url([$route=>$token]+client_identity_destination($destination));
}

/** Resolve both login tables, including disabled rows. A cross-table match is always ambiguous. */
function client_identity_namespace(string $email,bool $forUpdate=false): array {
    $email=client_identity_email($email);$lock=$forUpdate?' FOR UPDATE':'';
    $primary=studio_ready()?one('SELECT * FROM '.table('studio_clients').' WHERE email=?'.$lock,[$email]):null;
    $contact=contacts_ready()?one('SELECT * FROM '.table('studio_contacts').' WHERE email=?'.$lock,[$email]):null;
    return ['email'=>$email,'primary'=>$primary?:null,'contact'=>$contact?:null,'ambiguous'=>(bool)($primary&&$contact)];
}

function client_identity_pending(int $client): bool {
    if(!client_identity_ready()||$client<1)return false;
    $row=one('SELECT verified_at FROM '.table('studio_client_registrations').' WHERE client_id=?',[$client]);
    return $row!==null&&$row['verified_at']===null;
}

/** Establish the same bounded primary session after login or verification. */
function client_identity_session_start(array $client,?array $contact=null): void {
    if(session_status()!==PHP_SESSION_ACTIVE||headers_sent())throw new RuntimeException('A secure client session is unavailable. Please sign in again.');
    if(!session_regenerate_id(true))throw new RuntimeException('A secure client session could not be started. Please sign in again.');
    unset($_SESSION['contact_id'],$_SESSION['contact_auth']);
    $_SESSION['client_id']=(int)$client['id'];$_SESSION['client_auth']=(string)$client['auth_version'];
    if($contact){$_SESSION['contact_id']=(int)$contact['id'];$_SESSION['contact_auth']=(string)$contact['auth_version'];}
    $_SESSION['client_last']=$_SESSION['client_login']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
}

function client_identity_require_ready(bool $delivery=false): void {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Account security is unavailable during client review.');
    if(!client_identity_ready())throw new RuntimeException('Public client accounts are not available yet.');
    if($delivery&&!client_identity_request_ready())throw new RuntimeException('Public account email is not available yet. Contact the studio for an invitation.');
}

/** Caller holds the shared mutex and transaction. */
function client_identity_issue_token(array $client,string $purpose,array $destination,int $lifetime): ?array {
    if(!db()->inTransaction())throw new LogicException('Client identity token issuance requires a transaction.');
    $token=bin2hex(random_bytes(32));$digest=client_identity_token_digest($purpose,$token);$now=time();
    $fingerprint=client_identity_email_fingerprint((string)$client['email']);$resume=client_identity_destination_json($destination);
    query('DELETE FROM '.table('studio_client_tokens').' WHERE client_id=? AND purpose=? AND expires_at<=?',[(int)$client['id'],$purpose,$now]);
    $active=query('SELECT token_digest FROM '.table('studio_client_tokens').' WHERE client_id=? AND purpose=? ORDER BY created_at,token_digest FOR UPDATE',[(int)$client['id'],$purpose])->fetchAll(PDO::FETCH_COLUMN);
    if(count($active)>=2)return null;
    query('INSERT INTO '.table('studio_client_tokens').' (token_digest,client_id,purpose,email_fingerprint,auth_version,resume_json,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?)',[$digest,(int)$client['id'],$purpose,$fingerprint,(string)$client['auth_version'],$resume,$now+$lifetime,$now]);
    return ['token'=>$token,'digest'=>$digest,'retire_digest'=>$active[0]??null,'email'=>(string)$client['email'],'destination'=>$destination];
}

function client_identity_finalize_delivery(int $client,string $purpose,string $digest,?string $retireDigest,string $outcome): void {
    try{staff_locked(fn()=>staff_transaction(function()use($client,$purpose,$digest,$retireDigest,$outcome):void {
        if($outcome==='accepted'&&$retireDigest!==null)query('DELETE FROM '.table('studio_client_tokens').' WHERE client_id=? AND purpose=? AND token_digest=?',[$client,$purpose,$retireDigest]);
        elseif($outcome==='not_configured')query('DELETE FROM '.table('studio_client_tokens').' WHERE client_id=? AND purpose=? AND token_digest=?',[$client,$purpose,$digest]);
    }));}catch(Throwable $e){report_error($e);}
}

function client_identity_deliver(int $client,string $purpose,array $issued): string {
    $route=$purpose==='verify'?'verify':'reset';$link=client_identity_url($route,$issued['token'],$issued['destination']);
    $subject=$purpose==='verify'?'Verify your CajunWebPro account':'Reset your CajunWebPro password';
    $body=$purpose==='verify'
        ?"Confirm your email address to finish creating your CajunWebPro client account.\n\nOpen this link, then use the confirmation button:\n".$link."\n\nThis link expires in 24 hours. If you did not create this account, you can ignore this message."
        :"A password reset was requested for your CajunWebPro client account.\n\nOpen this link to choose a new password:\n".$link."\n\nThis link expires in one hour. If you did not request it, you can ignore this message.";
    $outcome='uncertain';$mode=setting('email_mode','disabled');$restoreAsync=null;$restoreHandler=null;
    try{
        if($mode==='mail'){
            $restoreAsync=pcntl_async_signals(true);$restoreHandler=pcntl_signal_get_handler(SIGALRM);
            pcntl_signal(SIGALRM,static function():never{throw new RuntimeException('Account email exceeded its delivery time budget.');});pcntl_alarm(6);
        }
        $outcome=send_message($issued['email'],$subject,$body,null,6.0);
    }catch(Throwable $e){report_error($e);$outcome='uncertain';}
    finally{if($mode==='mail'&&$restoreHandler!==null){pcntl_alarm(0);pcntl_signal(SIGALRM,$restoreHandler);pcntl_async_signals((bool)$restoreAsync);}}
    client_identity_finalize_delivery($client,$purpose,$issued['digest'],$issued['retire_digest'],$outcome);
    return $outcome;
}

function client_identity_throttle(string $scope,string $email,int $ipLimit,int $emailLimit): void {
    $fingerprint=client_identity_email_fingerprint($email);
    $ip=consume_rate(ip_key('client-'.$scope),$ipLimit,900);
    $address=consume_rate(hash_hmac('sha256','client-'.$scope.'|'.$fingerprint,config()['key']),$emailLimit,900);
    if(!$ip||!$address)throw new RuntimeException('Too many attempts. Wait 15 minutes before trying again.');
}

/** Bound expensive password work to an IP and a purpose-bound, non-reversible token digest. */
function client_identity_token_throttle(string $purpose,#[\SensitiveParameter] string $token): void {
    $digest=client_identity_token_digest($purpose,$token);
    $ip=consume_rate(ip_key('client-'.$purpose.'-password'),20,900);
    $link=consume_rate(hash_hmac('sha256','client-'.$purpose.'-password|'.$digest,config()['key']),8,900);
    if(!$ip||!$link)throw new RuntimeException('Too many attempts. Wait 15 minutes before trying again.');
}

/** Delivery is capped at six seconds; every neutral request returns after the same seven-second floor. */
function client_identity_neutral_finish(int $started): void {
    $remaining=7000000000-(hrtime(true)-$started);
    if($remaining>0)usleep((int)min(7000000,intdiv($remaining+999,1000)));
}

function client_identity_register(string $name,string $email,array $destination): void {
    client_identity_require_ready(true);$name=trim($name);$email=client_identity_email($email);$destination=client_identity_destination($destination);
    if($name===''||strlen($name)>120||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1F\x7F]/',$name))throw new InvalidArgumentException('Enter your name using no more than 120 bytes.');
    client_identity_throttle('register',$email,10,5);
    $started=hrtime(true);
    try{
        $created=staff_locked(fn()=>staff_transaction(function()use($name,$email,$destination):?array {
            $namespace=client_identity_namespace($email,true);
            if($namespace['primary']||$namespace['contact'])return null;
            $version=bin2hex(random_bytes(16));$now=time();
            query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,active,invite_hash,invite_expires,created_at) VALUES (?,?,\'\',?,1,NULL,NULL,?)',[$name,$email,$version,gmdate('Y-m-d H:i:s',$now)]);
            $client=['id'=>(int)db()->lastInsertId(),'email'=>$email,'auth_version'=>$version];
            query('INSERT INTO '.table('studio_client_registrations').' (client_id,email_fingerprint,verified_at,created_at) VALUES (?,?,NULL,?)',[$client['id'],client_identity_email_fingerprint($email),$now]);
            $issued=client_identity_issue_token($client,'verify',$destination,86400);if(!$issued)throw new LogicException('Initial verification token could not be issued.');studio_client_audit(0,'client.registered',$client['id']);
            return ['client'=>$client,'issued'=>$issued];
        }));
        if($created)client_identity_deliver((int)$created['client']['id'],'verify',$created['issued']);
    }finally{client_identity_neutral_finish($started);}
}

/** Neutral resend: only a pending public primary account receives mail. */
function client_identity_resend(string $email,array $destination): void {
    client_identity_require_ready(true);$email=client_identity_email($email);$destination=client_identity_destination($destination);client_identity_throttle('verify-resend',$email,12,4);
    $started=hrtime(true);
    try{
        $result=staff_locked(fn()=>staff_transaction(function()use($email,$destination):?array {
            $namespace=client_identity_namespace($email,true);
            if($namespace['ambiguous']||!$namespace['primary']||$namespace['contact']||(int)$namespace['primary']['active']!==1)return null;
            $client=$namespace['primary'];$registration=one('SELECT * FROM '.table('studio_client_registrations').' WHERE client_id=? FOR UPDATE',[(int)$client['id']]);
            if(!$registration||$registration['verified_at']!==null||!hash_equals($registration['email_fingerprint'],client_identity_email_fingerprint($email)))return null;
            $issued=client_identity_issue_token($client,'verify',$destination,86400);return $issued?['client'=>$client,'issued'=>$issued]:null;
        }));
        if($result)client_identity_deliver((int)$result['client']['id'],'verify',$result['issued']);
    }finally{client_identity_neutral_finish($started);}
}

/** Neutral request: contacts, pending registrations, unused invites, and ambiguous identities receive no token. */
function client_identity_recovery_request(string $email,array $destination): void {
    client_identity_require_ready(true);$email=client_identity_email($email);$destination=client_identity_destination($destination);client_identity_throttle('recover',$email,12,4);
    $started=hrtime(true);
    try{
        $result=staff_locked(fn()=>staff_transaction(function()use($email,$destination):?array {
            $namespace=client_identity_namespace($email,true);
            if($namespace['ambiguous']||!$namespace['primary']||$namespace['contact'])return null;
            $client=$namespace['primary'];if((int)$client['active']!==1||$client['password_hash']===''||$client['invite_hash']!==null)return null;
            $registration=one('SELECT verified_at,email_fingerprint FROM '.table('studio_client_registrations').' WHERE client_id=? FOR UPDATE',[(int)$client['id']]);
            if($registration&&($registration['verified_at']===null||!hash_equals($registration['email_fingerprint'],client_identity_email_fingerprint($email))))return null;
            $issued=client_identity_issue_token($client,'recover',$destination,3600);return $issued?['client'=>$client,'issued'=>$issued]:null;
        }));
        if($result)client_identity_deliver((int)$result['client']['id'],'recover',$result['issued']);
    }finally{client_identity_neutral_finish($started);}
}

/** Read-only validation for GET confirmation and reset pages. */
function client_identity_token_preview(string $purpose,#[\SensitiveParameter] string $token): ?array {
    if(!client_identity_ready())return null;
    try{$digest=client_identity_token_digest($purpose,$token);}catch(InvalidArgumentException){return null;}
    $row=one('SELECT t.*,c.email,c.auth_version current_auth,c.active,c.password_hash,c.invite_hash,r.verified_at,r.email_fingerprint registration_fingerprint FROM '.table('studio_client_tokens').' t JOIN '.table('studio_clients').' c ON c.id=t.client_id LEFT JOIN '.table('studio_client_registrations').' r ON r.client_id=c.id WHERE t.token_digest=? AND t.purpose=?',[$digest,$purpose]);
    if(!$row||(int)$row['active']!==1||(int)$row['expires_at']<=time()||!hash_equals($row['auth_version'],$row['current_auth'])||!hash_equals($row['email_fingerprint'],client_identity_email_fingerprint($row['email'])))return null;
    if($purpose==='verify'&&($row['registration_fingerprint']===null||$row['verified_at']!==null||!hash_equals($row['registration_fingerprint'],$row['email_fingerprint'])))return null;
    if($purpose==='recover'&&($row['password_hash']===''||$row['invite_hash']!==null||($row['registration_fingerprint']!==null&&($row['verified_at']===null||!hash_equals($row['registration_fingerprint'],$row['email_fingerprint'])))))return null;
    try{$row['destination']=client_identity_destination_from_json($row['resume_json']);}catch(RuntimeException){return null;}
    return $row;
}

function client_identity_verify(#[\SensitiveParameter] string $token,#[\SensitiveParameter] string $password): array {
    client_identity_require_ready();client_identity_token_throttle('verify',$token);validate_password($password);$digest=client_identity_token_digest('verify',$token);$hash=password_hash($password,PASSWORD_DEFAULT);
    $verified=staff_locked(fn()=>staff_transaction(function()use($digest,$hash):array {
        $tokenRow=one('SELECT * FROM '.table('studio_client_tokens').' WHERE token_digest=? AND purpose=? FOR UPDATE',[$digest,'verify']);
        if(!$tokenRow)throw new InvalidArgumentException('This verification link expired or has already been used.');
        $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? FOR UPDATE',[(int)$tokenRow['client_id']]);
        $registration=one('SELECT * FROM '.table('studio_client_registrations').' WHERE client_id=? FOR UPDATE',[(int)$tokenRow['client_id']]);
        if(!$client||!$registration||(int)$client['active']!==1||(int)$tokenRow['expires_at']<=time()||$registration['verified_at']!==null||!hash_equals($tokenRow['auth_version'],$client['auth_version'])||!hash_equals($tokenRow['email_fingerprint'],$registration['email_fingerprint'])||!hash_equals($tokenRow['email_fingerprint'],client_identity_email_fingerprint($client['email'])))throw new InvalidArgumentException('This verification link expired or has already been used.');
        $destination=client_identity_destination_from_json($tokenRow['resume_json']);$version=bin2hex(random_bytes(16));$now=time();
        query('UPDATE '.table('studio_client_registrations').' SET verified_at=? WHERE client_id=?',[$now,(int)$client['id']]);
        query('UPDATE '.table('studio_clients').' SET password_hash=?,auth_version=? WHERE id=?',[$hash,$version,(int)$client['id']]);
        query('DELETE FROM '.table('studio_client_tokens').' WHERE client_id=?',[(int)$client['id']]);
        studio_client_audit(0,'client.verified',(int)$client['id']);$client['auth_version']=$version;
        return ['client'=>$client,'destination'=>$destination];
    }));
    client_identity_session_start($verified['client']);return $verified['destination'];
}

function client_identity_recovery_reset(#[\SensitiveParameter] string $token,#[\SensitiveParameter] string $password): array {
    client_identity_require_ready();client_identity_token_throttle('recover',$token);validate_password($password);$digest=client_identity_token_digest('recover',$token);$hash=password_hash($password,PASSWORD_DEFAULT);
    return staff_locked(fn()=>staff_transaction(function()use($digest,$hash):array {
        $tokenRow=one('SELECT * FROM '.table('studio_client_tokens').' WHERE token_digest=? AND purpose=? FOR UPDATE',[$digest,'recover']);
        if(!$tokenRow)throw new InvalidArgumentException('This password reset link expired or has already been used.');
        $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? FOR UPDATE',[(int)$tokenRow['client_id']]);
        $registration=one('SELECT * FROM '.table('studio_client_registrations').' WHERE client_id=? FOR UPDATE',[(int)$tokenRow['client_id']]);
        if(!$client||(int)$client['active']!==1||(int)$tokenRow['expires_at']<=time()||$client['password_hash']===''||$client['invite_hash']!==null||($registration&&($registration['verified_at']===null||!hash_equals($registration['email_fingerprint'],$tokenRow['email_fingerprint'])))||!hash_equals($tokenRow['auth_version'],$client['auth_version'])||!hash_equals($tokenRow['email_fingerprint'],client_identity_email_fingerprint($client['email'])))throw new InvalidArgumentException('This password reset link expired or has already been used.');
        $destination=client_identity_destination_from_json($tokenRow['resume_json']);$version=bin2hex(random_bytes(16));
        query('UPDATE '.table('studio_clients').' SET password_hash=?,auth_version=?,invite_hash=NULL,invite_expires=NULL WHERE id=?',[$hash,$version,(int)$client['id']]);
        query('DELETE FROM '.table('studio_client_tokens').' WHERE client_id=?',[(int)$client['id']]);
        studio_client_audit(0,'client.recovered',(int)$client['id']);return $destination;
    }));
}
