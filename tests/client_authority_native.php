<?php
/** Disposable native MariaDB fixtures. Synthetic client identities; no external operations. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require_once __DIR__.'/../site/app/clients/workspace.php';
$private=__DIR__.'/../test-runtime/client-authority-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-client-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'caci_']];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic client manager passphrase';
function ca_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function ca_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;ca_ok($name,true);return;}ca_ok($name,false);}
function ca_actor(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];}
function ca_id(int $i):string {return str_pad(dechex($i),32,'0',STR_PAD_LEFT);}
function ca_snapshot():string {return json_encode([all('SELECT * FROM '.table('studio_clients').' ORDER BY id'),all('SELECT * FROM '.table('audit').' ORDER BY id')]);}
function ca_audit_failure(callable $fn):bool {
    db()->exec('CREATE TRIGGER caci_fail BEFORE INSERT ON '.table('audit')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic client audit failure'");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER caci_fail');}return $failed;
}
function ca_worker(string $mode,array $args=[]):array {
    $p=proc_open([PHP_BINARY,__FILE__,$mode,...$args],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Cannot start client fixture worker');
    foreach([1,2] as $stream)stream_set_timeout($pipes[$stream],15);return [$p,$pipes];
}
function ca_finish(array $worker):string {
    [$p,$pipes]=$worker;fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($p)!==0||$error!=='')throw new RuntimeException('Client fixture worker failed: '.$error);return $out;
}
function ca_client(int $id):array {return one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);}
function ca_version(int $id):string {return ca_client($id)['auth_version'];}
function ca_token(string $url):string {parse_str((string)parse_url($url,PHP_URL_QUERY),$args);return $args['invite'];}
function ca_client_session(int $id):void {$c=ca_client($id);$_SESSION['client_id']=$id;$_SESSION['client_auth']=$c['auth_version'];$_SESSION['client_last']=$_SESSION['client_login']=time();}
function ca_contact_session(int $id):void {$contact=one('SELECT * FROM '.table('studio_contacts').' WHERE id=?',[$id]);$_SESSION=[];ca_client_session((int)$contact['client_id']);$_SESSION['contact_id']=$id;$_SESSION['contact_auth']=$contact['auth_version'];}
if(($argv[1]??'')==='save-worker') {
    ca_actor((int)$argv[2]);echo "ready\n";fflush(STDOUT);
    try{studio_client_save((int)$argv[3],$argv[4],$argv[5],$argv[6],true);echo 'saved';}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo str_contains($e->getMessage(),'changed')?'stale':'denied';}exit;
}
if(($argv[1]??'')==='invite-worker') {
    ca_actor((int)$argv[2]);echo "ready\n";fflush(STDOUT);
    try{studio_new_invite((int)$argv[3],$argv[4]);echo 'issued';}
    catch(RuntimeException $e){if($e instanceof PDOException)throw $e;echo str_contains($e->getMessage(),'changed')?'stale':'denied';}exit;
}
if(($argv[1]??'')==='activate-worker') {
    $_SESSION=[];echo "ready\n";fflush(STDOUT);
    try{studio_activate($argv[2],'Synthetic activated client passphrase');echo 'activated';}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo 'denied';}exit;
}
if(($argv[1]??'')==='upload-worker') {
    $_SESSION=['client_auth'=>$argv[5],'contact_auth'=>$argv[6],'client_last'=>time(),'client_login'=>time()];
    $media=$private.'/media';@mkdir($media,0700,true);$bytes=random_bytes(64);
    echo "ready\n";fflush(STDOUT);
    try{studio_upload_write((int)$argv[2],'contact',['id'=>(int)$argv[3]],['id'=>(int)$argv[4]],$argv[7],$media.'/studio-'.$argv[7].'.php','worker-upload.png','image/png',strlen($bytes),$bytes);echo 'written';}
    catch(InvalidArgumentException|RuntimeException|LogicException $e){if($e instanceof PDOException)throw $e;echo 'denied';}exit;
}
if(($argv[1]??'')==='activity-worker') {
    ca_contact_session((int)$argv[2]);echo "ready\n";fflush(STDOUT);
    try{activity_mark('client',[$argv[3]],true);echo 'written';}
    catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo 'denied';}exit;
}
try {
    foreach(schema_statements('caci_') as $sql)db()->exec($sql);
    foreach(default_settings() as $key=>$value)save_setting($key,$value);
    save_setting('studio_schema_version','2');
    for($i=1;$i<=4;$i++)query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Synthetic staff '.$i,'client-staff'.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);
    foreach([2=>['administrator',['clients.manage']],3=>['administrator',['website.manage']],4=>['owner',[]]] as $id=>[$role,$permissions])query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode($permissions)]);
    ca_actor();$before=ca_snapshot();
    foreach([['','valid@example.invalid'],["Bad\nName",'valid@example.invalid'],[str_repeat('x',121),'valid@example.invalid'],["\xFF",'valid@example.invalid'],['Valid','not-an-email'],['Valid',str_repeat('a',191).'@example.invalid']] as $n=>[$name,$email])ca_deny('Invalid client details refused '.$n,fn()=>studio_client_save(0,'',$name,$email,true));
    ca_deny('New client rejects an existing account version',fn()=>studio_client_save(0,str_repeat('a',32),'New','new@example.invalid',true));
    ca_ok('Invalid creation leaves accounts and audit unchanged',ca_snapshot()===$before);
    ca_ok('Strict creation audit rolls back inserted client',ca_audit_failure(fn()=>studio_client_save(0,'','New','new@example.invalid',true))&&ca_snapshot()===$before);
    $id=studio_client_save(0,'','Cajun café','CLIENT@EXAMPLE.INVALID',true);$row=ca_client($id);
    ca_ok('Create keeps Unicode name canonical email and active access',$row['name']==='Cajun café'&&$row['email']==='client@example.invalid'&&(int)$row['active']===1&&$row['password_hash']===''&&$row['invite_hash']===null);
    ca_ok('Create records a single attributed audit',count(all('SELECT * FROM '.table('audit')) )===1&&one('SELECT user_id,action,detail FROM '.table('audit'))===['user_id'=>1,'action'=>'studio_client','detail'=>'Client '.$id]);
    $before=ca_snapshot();try{studio_client_save(0,'','Duplicate','client@example.invalid',true);$denied=false;}catch(PDOException){$denied=true;}ca_ok('Duplicate email rolls back creation and audit',$denied&&ca_snapshot()===$before);
    foreach(['',str_repeat('0',32),$row['auth_version']."\n",'A'.substr($row['auth_version'],1)] as $n=>$expected)ca_deny('Client save rejects missing stale or malformed version '.$n,fn()=>studio_client_save($id,$expected,'Changed','client@example.invalid',true));
    ca_deny('Unknown client is not created by update',fn()=>studio_client_save(999,$row['auth_version'],'Changed','other@example.invalid',true));
    ca_ok('Strict update audit rolls back account version and access',ca_audit_failure(fn()=>studio_client_save($id,$row['auth_version'],'Changed','client@example.invalid',false))&&ca_snapshot()===$before);
    ca_actor(2);studio_client_save($id,$row['auth_version'],'Delegated change','client@example.invalid',true);ca_ok('Currently granted administrator can manage clients',ca_client($id)['name']==='Delegated change');$version=ca_version($id);
    ca_actor(3);ca_deny('Website-only administrator cannot create clients',fn()=>studio_client_save(0,'','Denied','denied@example.invalid',true));ca_deny('Website-only administrator cannot edit clients',fn()=>studio_client_save($id,$version,'Denied','client@example.invalid',true));ca_deny('Website-only administrator cannot issue invitations',fn()=>studio_new_invite($id,$version));
    $_SESSION=[];ca_deny('Unauthenticated create is denied',fn()=>studio_client_save(0,'','Denied','denied@example.invalid',true));ca_actor();
    $_GET['support']='';ca_deny('Support selector denies client changes',fn()=>studio_client_save($id,$version,'Denied','client@example.invalid',true));ca_deny('Support selector denies invitations',fn()=>studio_new_invite($id,$version));unset($_GET['support']);
    $GLOBALS['cwp_support']=['client'=>['id'=>$id]];ca_deny('Active support context denies client administration',fn()=>studio_client_save($id,$version,'Denied','client@example.invalid',true));unset($GLOBALS['cwp_support']);
    $_SESSION['auth_version']=str_repeat('0',32);ca_deny('Changed staff authentication version denies mutation',fn()=>studio_client_save($id,$version,'Denied','client@example.invalid',true));ca_actor();
    $before=ca_snapshot();ca_ok('Strict invitation audit rolls back token and account version',ca_audit_failure(fn()=>studio_new_invite($id,$version))&&ca_snapshot()===$before);
    $token=ca_token(studio_new_invite($id,$version));$row=ca_client($id);
    ca_ok('Invitation stores only a hash with bounded expiry and rotates access',$row['invite_hash']===hash('sha256',$token)&&!str_contains(json_encode($row),$token)&&(int)$row['invite_expires']>time()+86390&&(int)$row['invite_expires']<=time()+86400&&$row['auth_version']!==$version);
    $before=ca_snapshot();ca_deny('Old invitation form cannot replace a newer invitation',fn()=>studio_new_invite($id,$version));ca_deny('Old edit form cannot reset the newly issued invitation',fn()=>studio_client_save($id,$version,'Old form','client@example.invalid',false));ca_ok('Stale forms preserve latest token version and audit',ca_snapshot()===$before);
    $GLOBALS['cwp_support']=['client'=>['id'=>$id]];ca_deny('Support session cannot activate a client identity',fn()=>studio_activate($token,'Synthetic client passphrase 2026'));unset($GLOBALS['cwp_support']);
    ca_deny('Activation refuses newline token',fn()=>studio_activate($token."\n",'Synthetic client passphrase 2026'));
    ca_deny('Activation refuses short password',fn()=>studio_activate($token,'short'));
    ca_ok('Strict activation audit leaves the invite reusable and password unchanged',ca_audit_failure(fn()=>studio_activate($token,'Synthetic client passphrase 2026'))&&ca_snapshot()===$before);
    $_SESSION=[];studio_activate($token,'Synthetic client passphrase 2026');$activated=ca_client($id);
    ca_ok('Public activation hashes password consumes token and rotates access',password_verify('Synthetic client passphrase 2026',$activated['password_hash'])&&$activated['invite_hash']===null&&$activated['invite_expires']===null&&$activated['auth_version']!==$row['auth_version']);
    ca_ok('Public activation audit has no invented staff identity',one('SELECT user_id,action,detail FROM '.table('audit').' ORDER BY id DESC LIMIT 1')===['user_id'=>0,'action'=>'studio_activate','detail'=>'Client '.$id]);
    ca_deny('Consumed invitation cannot be replayed',fn()=>studio_activate($token,'Another client passphrase 2026'));ca_actor();
    ca_client_session($id);ca_ok('Activated client session is current before deactivation',studio_client()!==null);$version=ca_version($id);studio_client_save($id,$version,'Disabled','client@example.invalid',false);ca_ok('Deactivation revokes current client session and preserves password',studio_client()===null&&(int)ca_client($id)['active']===0&&ca_client($id)['password_hash']===$activated['password_hash']);ca_deny('Inactive client cannot receive an invitation',fn()=>studio_new_invite($id,ca_version($id)));
    $version=ca_version($id);studio_client_save($id,$version,'Restored','client@example.invalid',true);ca_client_session($id);ca_ok('Restored client session is current before invitation rotation',studio_client()!==null);$token=ca_token(studio_new_invite($id,ca_version($id)));ca_ok('New invitation immediately revokes prior client sessions',studio_client()===null);
    $version=ca_version($id);studio_client_save($id,$version,'Updated','client@example.invalid',true);ca_ok('Client edit clears outstanding invitation without changing password',ca_client($id)['invite_hash']===null&&ca_client($id)['password_hash']===$activated['password_hash']);ca_deny('Revoked invitation cannot activate after client edit',fn()=>studio_activate($token,'Synthetic client passphrase 2026'));
    db()->beginTransaction();ca_deny('Save refuses an inherited transaction',fn()=>studio_client_save($id,ca_version($id),'Nested','client@example.invalid',true));ca_deny('Invite refuses an inherited transaction',fn()=>studio_new_invite($id,ca_version($id)));ca_deny('Activation refuses an inherited transaction',fn()=>studio_activate(str_repeat('a',64),'Synthetic client passphrase 2026'));ca_ok('Rejected nested operations leave caller transaction intact',db()->inTransaction());db()->rollBack();
    $version=ca_version($id);$workers=[ca_worker('save-worker',['1',(string)$id,$version,'Concurrent first','client@example.invalid']),ca_worker('save-worker',['4',(string)$id,$version,'Concurrent second','client@example.invalid'])];$out=array_map('ca_finish',$workers);sort($out);ca_ok('Competing edit forms commit one change and reject the stale writer',$out===["ready\nsaved","ready\nstale"]&&ca_version($id)!==$version);
    $version=ca_version($id);$workers=[ca_worker('invite-worker',['1',(string)$id,$version]),ca_worker('invite-worker',['4',(string)$id,$version])];$out=array_map('ca_finish',$workers);sort($out);ca_ok('Competing invitation forms issue exactly one new token',$out===["ready\nissued","ready\nstale"]&&ca_version($id)!==$version);
    $token=ca_token(studio_new_invite($id,ca_version($id)));$beforeCount=(int)query('SELECT COUNT(*) FROM '.table('audit')." WHERE action='studio_activate'")->fetchColumn();$workers=[ca_worker('activate-worker',[$token]),ca_worker('activate-worker',[$token])];$out=array_map('ca_finish',$workers);sort($out);ca_ok('Competing public activations consume invitation once',$out===["ready\nactivated","ready\ndenied"]&&(int)query('SELECT COUNT(*) FROM '.table('audit')." WHERE action='studio_activate'")->fetchColumn()===$beforeCount+1);
    $version=ca_version($id);
    staff_locked(function()use($id,$version):void {$GLOBALS['client_waiter']=ca_worker('save-worker',['2',(string)$id,$version,'Revoked writer','client@example.invalid']);if(trim(fgets($GLOBALS['client_waiter'][1][1]))!=='ready')throw new RuntimeException('Client writer did not reach guard');query('UPDATE '.table('staff_profiles')." SET permissions='[]' WHERE user_id=2");});$before=ca_snapshot();ca_ok('Staff permission revoked while waiting is checked after the guard',ca_finish($GLOBALS['client_waiter'])==='denied'&&ca_snapshot()===$before);query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[json_encode(['clients.manage'])]);
    staff_locked(function()use($id,$version):void {$GLOBALS['invite_waiter']=ca_worker('invite-worker',['4',(string)$id,$version]);if(trim(fgets($GLOBALS['invite_waiter'][1][1]))!=='ready')throw new RuntimeException('Invitation writer did not reach guard');query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=4');});$before=ca_snapshot();ca_ok('Owner disabled while waiting cannot issue an invitation',ca_finish($GLOBALS['invite_waiter'])==='denied'&&ca_snapshot()===$before);query('UPDATE '.table('staff_profiles').' SET enabled=1 WHERE user_id=4');
    $token=ca_token(studio_new_invite($id,ca_version($id)));$row=ca_client($id);
    staff_locked(function()use($id,$token,$row):void {$GLOBALS['activation_waiter']=ca_worker('activate-worker',[$token]);if(trim(fgets($GLOBALS['activation_waiter'][1][1]))!=='ready')throw new RuntimeException('Activation did not reach guard');usleep(500000);ca_ok('Public activation cannot bypass the shared hosting guard',ca_client($id)===$row);studio_client_save($id,$row['auth_version'],'Revoked while waiting','client@example.invalid',false);});$before=ca_snapshot();ca_ok('Client deactivated while activation waits cannot establish credentials',ca_finish($GLOBALS['activation_waiter'])==='denied'&&ca_snapshot()===$before);
    studio_client_save($id,ca_version($id),'Restored','client@example.invalid',true);$token=ca_token(studio_new_invite($id,ca_version($id)));
    staff_locked(function()use($id,$token):void {$GLOBALS['expired_waiter']=ca_worker('activate-worker',[$token]);if(trim(fgets($GLOBALS['expired_waiter'][1][1]))!=='ready')throw new RuntimeException('Expiry activation did not reach guard');query('UPDATE '.table('studio_clients').' SET invite_expires=? WHERE id=?',[time()-1,$id]);});$before=ca_snapshot();ca_ok('Token expiry is rechecked after waiting for shared guard',ca_finish($GLOBALS['expired_waiter'])==='denied'&&ca_snapshot()===$before);
    save_setting('contacts_schema_version','1');
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$id,'Upload boundary','s','m',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);$job=(int)db()->lastInsertId();
    query('INSERT INTO '.table('studio_contacts').' (client_id,name,email,permissions,enabled,auth_version,created_at) VALUES (?,?,?,?,1,?,?)',[$id,'Boundary contact','boundary-contact@example.invalid',json_encode(['projects']),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);$contact=(int)db()->lastInsertId();
    $clientAuth=ca_version($id);
    $fileRows=fn()=>all('SELECT * FROM '.table('studio_files'));$contactAudit=fn()=>all('SELECT * FROM '.table('audit')." WHERE action='file.upload.contact'");$media=fn()=>glob($private.'/media/studio-*')?:[];
    foreach([
      'disabled contact'=>fn()=>query('UPDATE '.table('studio_contacts').' SET enabled=0 WHERE id=?',[$contact]),
      'rotated contact credentials'=>fn()=>query('UPDATE '.table('studio_contacts').' SET auth_version=? WHERE id=?',[bin2hex(random_bytes(16)),$contact]),
      'removed project permission'=>fn()=>query('UPDATE '.table('studio_contacts')." SET permissions='[\"billing\"]' WHERE id=?",[$contact]),
      'deactivated client'=>fn()=>query('UPDATE '.table('studio_clients').' SET active=0,auth_version=? WHERE id=?',[bin2hex(random_bytes(16)),$id]),
    ] as $label=>$revoke){
        $contactAuth=one('SELECT auth_version FROM '.table('studio_contacts').' WHERE id=?',[$contact])['auth_version'];
        staff_locked(function()use($job,$id,$contact,$clientAuth,$contactAuth,$revoke,$label):void{
            $GLOBALS['upload_waiter']=ca_worker('upload-worker',[(string)$job,(string)$id,(string)$contact,$clientAuth,$contactAuth,ca_id(9100+strlen($label))]);
            if(trim(fgets($GLOBALS['upload_waiter'][1][1]))!=='ready')throw new RuntimeException('Upload writer did not reach the guard');
            $revoke();
        });
        ca_ok('Contact upload denied after '.$label.' committed at the write boundary',ca_finish($GLOBALS['upload_waiter'])==='denied'&&$fileRows()===[]&&$contactAudit()===[]&&$media()===[]);
        query('UPDATE '.table('studio_contacts')." SET enabled=1,permissions='[\"projects\"]' WHERE id=?",[$contact]);
        query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=?',[$id]);$clientAuth=ca_version($id);
    }
    $contactAuth=one('SELECT auth_version FROM '.table('studio_contacts').' WHERE id=?',[$contact])['auth_version'];
    ca_ok('Permitted contact upload writes the file row with contact attribution',ca_finish(ca_worker('upload-worker',[(string)$job,(string)$id,(string)$contact,$clientAuth,$contactAuth,ca_id(9999)]))==="ready\nwritten"&&$fileRows()!==[]&&$fileRows()[0]['author_type']==='contact'&&$contactAudit()!==[]&&count($media())===1);
    $audit=json_encode(all('SELECT * FROM '.table('audit')));ca_ok('Client audit excludes names emails passwords raw invitations and password hashes',!str_contains($audit,'@example.invalid')&&!str_contains($audit,'Cajun')&&!str_contains($audit,$password)&&!str_contains($audit,$token)&&!str_contains($audit,ca_client($id)['password_hash']));
    ca_ok('Client account changes create no provider or email credentials',!is_file($private.'/provider-master.key')&&!is_file($private.'/mail-master.key')&&!all('SELECT * FROM '.table('provider_profiles'))&&!all('SELECT * FROM '.table('mail_accounts')));
    // Primary-only upgrades do not require the optional contacts schema before it is explicitly prepared.
    ca_actor();save_setting('contacts_schema_version','');db()->exec('RENAME TABLE '.table('studio_contacts').' TO '.table('unprepared_contacts'));
    try{
        $primaryA=studio_client_save(0,'','Transition primary A','transition-a@example.invalid',true);
        studio_client_save($primaryA,ca_version($primaryA),'Updated transition primary A','transition-a@example.invalid',true);
        $primaryB=studio_client_save(0,'','Transition primary B','transition-b@example.invalid',true);
        ca_ok('Primary creation and update work before contacts schema activation',ca_client($primaryA)['name']==='Updated transition primary A'&&ca_client($primaryB)['email']==='transition-b@example.invalid');
    }finally{db()->exec('RENAME TABLE '.table('unprepared_contacts').' TO '.table('studio_contacts'));save_setting('contacts_schema_version','1');}
    $primaryPassword='Synthetic transition primary passphrase';$contactPassword='Synthetic transition contact passphrase';
    query('UPDATE '.table('studio_clients').' SET password_hash=? WHERE id IN (?,?)',[password_hash($primaryPassword,PASSWORD_DEFAULT),$primaryA,$primaryB]);
    query('INSERT INTO '.table('studio_contacts').' (client_id,name,email,password_hash,permissions,enabled,auth_version,created_at) VALUES (?,?,?,?,?,1,?,?)',[$primaryA,'Transition contact','transition-contact@example.invalid',password_hash($contactPassword,PASSWORD_DEFAULT),'["projects"]',bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);$transitionContact=(int)db()->lastInsertId();
    $identityState=static fn()=>[ca_snapshot(),all('SELECT * FROM '.table('studio_contacts').' ORDER BY id')];
    foreach([1,0] as $enabled){
        query('UPDATE '.table('studio_contacts').' SET enabled=? WHERE id=?',[$enabled,$transitionContact]);$before=$identityState();
        foreach(['create','update'] as $operation){$error=null;
            try{studio_client_save($operation==='create'?0:$primaryB,$operation==='create'?'':ca_version($primaryB),'Forbidden primary collision','TRANSITION-CONTACT@EXAMPLE.INVALID',true);}catch(Throwable $e){$error=$e;}
            ca_ok('Primary '.$operation.' rejects '.($enabled?'enabled':'disabled').' contact email without changing any identity or audit record',$error instanceof InvalidArgumentException&&$error->getMessage()==='That email belongs to an additional client contact.'&&$identityState()===$before&&!db()->inTransaction());
        }
    }
    query('UPDATE '.table('studio_contacts').' SET enabled=1 WHERE id=?',[$transitionContact]);
    // Another request can prepare contacts after this request cached the old readiness marker.
    save_setting('contacts_schema_version','');ca_ok('Primary-email race fixture captures contacts as not yet prepared',setting('contacts_schema_version')==='');
    query('UPDATE '.table('settings').' SET value=? WHERE name=?',['1','contacts_schema_version']);$before=$identityState();
    try{foreach(['create','update'] as $operation){$error=null;
        try{studio_client_save($operation==='create'?0:$primaryB,$operation==='create'?'':ca_version($primaryB),'Stale-readiness collision','transition-contact@example.invalid',true);}catch(Throwable $e){$error=$e;}
        ca_ok('Fresh transaction readiness blocks '.$operation.' collision after cached absent contacts marker',$error instanceof InvalidArgumentException&&$error->getMessage()==='That email belongs to an additional client contact.'&&$identityState()===$before&&(one('SELECT value FROM '.table('settings').' WHERE name=?',['contacts_schema_version'])['value']??'')==='1'&&!db()->inTransaction());
    }}finally{save_setting('contacts_schema_version','1');}
    foreach(['idle'=>['client_last',1801],'absolute'=>['client_login',28801]] as $expiry=>[$clock,$elapsed])foreach(['same parent'=>$primaryA,'different parent'=>$primaryB] as $parentLabel=>$target){
        $_SESSION=[];studio_login('transition-contact@example.invalid',$contactPassword);
        ca_ok('Contact login installs scoped identity before '.$expiry.' expiry for '.$parentLabel,(int)studio_client()['id']===$primaryA&&(int)studio_contact()['id']===$transitionContact&&!contact_can('billing'));
        $_SESSION[$clock]=time()-$elapsed;ca_ok('Contact '.$expiry.' expiry refuses the old session for '.$parentLabel,studio_client()===null);
        $oldSession=session_id();$oldCsrf=$_SESSION['csrf'];$before=$identityState();$targetClient=ca_client($target);
        studio_login($targetClient['email'],$primaryPassword);
        ca_ok('Successful primary login after '.$expiry.' contact expiry selects '.$parentLabel.' without inherited contact scope',(int)studio_client()['id']===$target&&!array_key_exists('contact_id',$_SESSION)&&!array_key_exists('contact_auth',$_SESSION)&&contact_can('projects')&&contact_can('billing')&&contact_can('downloads')&&session_id()!==$oldSession&&$_SESSION['csrf']!==$oldCsrf&&$identityState()===$before);
    }
    $_SESSION=[];studio_login('transition-contact@example.invalid',$contactPassword);$beforeSession=$_SESSION;$before=$identityState();
    ca_deny('Invalid primary credentials cannot clear an authenticated contact identity',fn()=>studio_login('transition-b@example.invalid','Incorrect synthetic primary password'));
    ca_ok('Failed primary login preserves the exact contact session and stored identities',$_SESSION===$beforeSession&&(int)studio_contact()['id']===$transitionContact&&!contact_can('billing')&&$identityState()===$before);

    // Additional contacts must not inherit the parent's financial feed or personal read/preferences state.
    require_once CWP_CODE_ROOT.'/app/notifications/events.php';
    require_once CWP_CODE_ROOT.'/includes/layout/notifications.php';
    foreach(notification_feed_schema('caci_') as $sql)db()->exec($sql);
    foreach(['notification_feed_version','activity_schema_version','invoice_ledger_schema_version','delivery_schema_version'] as $marker)save_setting($marker,'1');
    save_setting('notification_email_enabled','0');$now=gmdate('Y-m-d H:i:s');$events=[];
    foreach([10001,10002] as $parent){
        query('INSERT INTO '.table('studio_clients').' (id,name,email,password_hash,auth_version,active,created_at) VALUES (?,?,?,?,?,1,?)',[$parent,'Read boundary '.$parent,'read-parent-'.$parent.'@example.invalid',password_hash('Synthetic read boundary passphrase',PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$now]);
        query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$parent,'Private project '.$parent,'Private scope','Private milestone',$now,$now]);$jid=(int)db()->lastInsertId();
        query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,\'admin\',1,?,?)',[$jid,'Private studio message '.$parent,$now]);$mid=(int)db()->lastInsertId();
        query('INSERT INTO '.table('studio_versions').' (job_id,kind,version_no,title,body,decision,feedback,decided_by,decided_at,created_at) VALUES (?,\'design\',1,?,?,\'approved\',\'\',?,?,?)',[$jid,'Private review '.$parent,'Private review body',$parent,$now,$now]);$vid=(int)db()->lastInsertId();
        $fid=ca_id($parent+20000);query('INSERT INTO '.table('studio_files').' (id,job_id,author_type,original_name,stored_name,mime,bytes,sha256,created_at) VALUES (?,?,\'admin\',?,?,\'application/pdf\',4,?,?)',[$fid,$jid,'Private file '.$parent.'.pdf','read-fixture-'.$parent,str_repeat('a',64),$now]);
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,status,instructions,created_at,paid_at) VALUES (?,?,?,9000,\'paid\',\'Private billing instructions\',?,?)',[$jid,'PRIVATE-BILL-'.$parent,'Private invoice '.$parent,$now,$now]);$iid=(int)db()->lastInsertId();
        query('INSERT INTO '.table('invoice_payments').' (invoice_id,job_id,client_id,actor_id,kind,amount_cents,currency,method,private_note,received_on,invoice_hash,invoice_reference,invoice_title,invoice_total_cents,client_name,created_at) VALUES (?,?,?,1,\'manual\',9000,\'USD\',\'other\',\'Private receipt note\',?,?,?,?,9000,?,?)',[$iid,$jid,$parent,gmdate('Y-m-d'),str_repeat('b',64),'PRIVATE-BILL-'.$parent,'Private invoice '.$parent,'Read boundary '.$parent,$now]);$pay=(int)db()->lastInsertId();
        $rid=ca_id($parent+30000);query('INSERT INTO '.table('website_releases').' (id,job_id,client_id,release_no,version,title,scope,notes,source_revision,bytes,sha256,manifest,request_key,request_hash,status,created_by,created_at,published_by,published_at) VALUES (?,?,?,1,\'1.0\',?,\'static\',\'Private release notes\',\'fixture\',4,?,\'{}\',?,?,\'published\',1,?,1,?)',[$rid,$jid,$parent,'Private release '.$parent,str_repeat('c',64),hash('sha256','release-request-'.$parent),hash('sha256','release-hash-'.$parent),$now,$now]);
        $events[$parent]=['job'=>$jid,'message'=>'message:'.$mid,'review'=>'review:'.$vid,'file'=>'file:'.$fid,'invoice'=>'invoice:'.$iid,'payment'=>'payment:'.$pay,'release'=>'release:'.$rid];
    }
    // Contact IDs deliberately overlap both parent IDs; read rows must use a distinct namespace.
    foreach([10001,10002] as $contactId)query('INSERT INTO '.table('studio_contacts').' (id,client_id,name,email,permissions,enabled,auth_version,created_at) VALUES (?,10001,?,?,?,1,?,?)',[$contactId,'Read contact '.$contactId,'read-contact-'.$contactId.'@example.invalid','["projects"]',bin2hex(random_bytes(16)),$now]);
    $readState=static function():string{$state=[];foreach(['studio_clients','studio_contacts','studio_jobs','studio_messages','studio_versions','studio_files','studio_invoices','invoice_payments','website_releases','activity_reads','notification_feed','notification_prefs','notification_deliveries','audit'] as $name)$state[$name]=all('SELECT * FROM '.table($name).' ORDER BY id');return json_encode($state);};
    query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,\'contact\',10001,?,?)',[$events[10001]['job'],'Scoped contact reply',$now]);$reply=(int)db()->lastInsertId();
    $message=one('SELECT * FROM '.table('studio_messages').' WHERE id=?',[$reply]);$staffIds=notification_staff_ids('clients.manage');sort($staffIds);
    notification_project_message($events[10001]['job'],$reply,'contact');$notices=all('SELECT * FROM '.table('notification_feed').' ORDER BY recipient_id');$recipients=array_map(static fn($row)=>(int)$row['recipient_id'],$notices);
    ca_ok('Contact project reply notifies every currently permitted staff recipient and retains contact authorship',$recipients===$staffIds&&count($notices)>0&&!array_filter($notices,static fn($row)=>$row['audience']!=='staff'||$row['kind']!=='support-reply')&&one('SELECT * FROM '.table('studio_messages').' WHERE id=?',[$reply])===$message);
    $before=$readState();notification_project_message($events[10001]['job'],$reply,'contact');ca_ok('Contact reply notification replay creates no duplicate feed delivery or business change',$readState()===$before);
    notification_project_message($events[10001]['job'],(int)substr($events[10001]['message'],8),'staff');
    $parentNotice=one('SELECT * FROM '.table('notification_feed')." WHERE audience='client' AND recipient_id=10001");
    ca_ok('Original staff project message still reaches the primary client only',$parentNotice!==null&&$parentNotice['kind']==='project-update'&&count(all('SELECT * FROM '.table('notification_feed')." WHERE audience='client'"))===1);
    ca_ok('Project notices remain local with email disabled',!all('SELECT * FROM '.table('notification_deliveries')." WHERE channel='email'")&&!is_file($private.'/mail-master.key'));
    $keys=$events[10001];$otherKeys=$events[10002];$grants=[['projects'],['projects','billing'],['projects','downloads'],['projects','billing','downloads']];
    foreach($grants as $permissions){
        query('UPDATE '.table('studio_contacts').' SET permissions=? WHERE id=10001',[json_encode($permissions)]);ca_contact_session(10001);$before=$readState();$feed=activity_feed('client');
        $expected=['message','review'];if(in_array('billing',$permissions,true))array_push($expected,'invoice','payment');if(in_array('downloads',$permissions,true))array_push($expected,'file','release');sort($expected);$actual=array_column($feed['rows'],'kind');sort($actual);
        ca_ok('Contact activity projects only its current grants: '.implode('+',$permissions),$actual===$expected&&!array_filter($feed['rows'],static fn($row)=>(int)$row['client_id']!==10001)&&activity_count('client')===count($expected)&&$readState()===$before);
        foreach(['invoice','payment','file','release'] as $kind)if(!in_array($kind,$expected,true)){
            ca_deny('Contact cannot resolve hidden '.$kind.' source with '.implode('+',$permissions),fn()=>activity_source('client',$keys[$kind]));
            ca_deny('Contact cannot mark hidden '.$kind.' through mixed visible and forbidden keys with '.implode('+',$permissions),fn()=>activity_mark('client',[$keys['message'],$keys[$kind]],true));
            ca_ok('Hidden '.$kind.' refusal preserves all business and personal state for '.implode('+',$permissions),$readState()===$before&&!db()->inTransaction());
        }
        ca_deny('Contact activity cannot resolve a different parent with '.implode('+',$permissions),fn()=>activity_source('client',$otherKeys['message']));
        foreach(['feed'=>fn()=>notification_feed('client',10001),'mark'=>fn()=>notification_mark('client',10001,$parentNotice['id'],true),'mark all'=>fn()=>notification_mark_all('client',10001),'preference'=>fn()=>notification_pref_email('client',10001,'billing',false,'10001'),'preference form'=>fn()=>notification_prefs_save('client',10001)] as $operation=>$fn){
            ca_deny('Contact cannot inherit primary notification '.$operation.' with '.implode('+',$permissions),$fn);
            ca_ok('Denied primary notification '.$operation.' retains exact rows for '.implode('+',$permissions),$readState()===$before&&!db()->inTransaction());
        }
    }
    foreach([['billing'],['downloads']] as $permissions){query('UPDATE '.table('studio_contacts').' SET permissions=? WHERE id=10001',[json_encode($permissions)]);ca_contact_session(10001);$before=$readState();ca_deny('Non-project contact cannot call activity service for '.implode('+',$permissions),fn()=>activity_feed('client'));ca_ok('Non-project activity denial retains personal and business rows for '.implode('+',$permissions),$readState()===$before);}
    query('UPDATE '.table('studio_contacts').' SET permissions=? WHERE id IN (10001,10002)',['["projects"]']);ca_contact_session(10001);activity_mark('client',[$keys['message']],true);
    ca_ok('Contact activity read state is bound to its own namespace despite a matching parent ID',all('SELECT actor_type,actor_id,event_key FROM '.table('activity_reads'))===[['actor_type'=>'contact','actor_id'=>10001,'event_key'=>$keys['message']]]&&activity_count('client')===1);
    ca_contact_session(10002);ca_ok('Another contact on the same parent retains an independent unread feed',activity_count('client')===2);activity_mark('client',[$keys['message']],true);
    $_SESSION=[];ca_client_session(10001);ca_ok('Primary activity actor and all six original event categories remain unaffected',activity_actor('client')===['type'=>'client','id'=>10001]&&activity_count('client')===6&&count(activity_feed('client')['rows'])===6);activity_mark('client',[$keys['message']],true);
    ca_ok('Parent and two contacts retain three independent read records',all('SELECT actor_type,actor_id,event_key FROM '.table('activity_reads').' ORDER BY actor_type,actor_id')===[['actor_type'=>'client','actor_id'=>10001,'event_key'=>$keys['message']],['actor_type'=>'contact','actor_id'=>10001,'event_key'=>$keys['message']],['actor_type'=>'contact','actor_id'=>10002,'event_key'=>$keys['message']]]);
    ca_ok('Primary client keeps its own notification feed',notification_feed('client',10001)->unreadCount()===1);notification_mark('client',10001,$parentNotice['id'],true);notification_pref_email('client',10001,'billing',false,'10001');ca_ok('Primary notification read state and preferences remain writable',one('SELECT read_at FROM '.table('notification_feed').' WHERE id=?',[$parentNotice['id']])['read_at']!==null&&one('SELECT email FROM '.table('notification_prefs')." WHERE audience='client' AND recipient_id=10001 AND kind='billing'")===['email'=>0]);
    ca_actor();ca_ok('Original staff activity and notification identities remain unaffected',activity_actor('admin')===['type'=>'admin','id'=>1]&&notification_feed('staff',1)->unreadCount()===1);
    foreach(['removed project permission'=>"UPDATE ".table('studio_contacts')." SET permissions='[\"billing\"]' WHERE id=10001",'disabled contact'=>"UPDATE ".table('studio_contacts')." SET enabled=0 WHERE id=10001",'rotated contact credentials'=>"UPDATE ".table('studio_contacts')." SET auth_version='".str_repeat('e',32)."' WHERE id=10001"] as $reason=>$sql){
        query('UPDATE '.table('studio_contacts').' SET permissions=?,enabled=1,auth_version=? WHERE id=10001',['["projects"]',bin2hex(random_bytes(16))]);
        staff_locked(function()use($keys,$sql):void{$GLOBALS['activity_waiter']=ca_worker('activity-worker',['10001',$keys['review']]);if(trim(fgets($GLOBALS['activity_waiter'][1][1]))!=='ready')throw new RuntimeException('Activity writer did not reach the guard');query($sql);});$before=$readState();
        ca_ok('Contact activity rechecks '.$reason.' after waiting for the write guard',ca_finish($GLOBALS['activity_waiter'])==='denied'&&$readState()===$before);
    }
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
$ok=!array_filter($checks,fn($c)=>!$c['passed']);file_put_contents(__DIR__.'/../qa/client-authority-native-results.json',json_encode(['passed'=>$ok,'scope'=>'Native MariaDB current staff/client authority, strict audit and competing public activation fixtures; no real customer messaging or provider operation','checks'=>$checks],JSON_PRETTY_PRINT));
echo ($ok?'PASS':'FAIL').': '.count($checks).' native client authority checks'.PHP_EOL;exit($ok?0:1);
