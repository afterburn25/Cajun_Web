<?php
declare(strict_types=1);
if (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }
function setup_owner_input(): array {
    $name=posted('owner_name',120);$email=posted('owner_email',190);$password=posted('owner_password',72);validate_password($password);
    if($password!==posted('owner_confirm',72))throw new InvalidArgumentException('The administrator passwords do not match.');
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter your name and a valid administrator email.');
    $url=rtrim(posted('site_url',500),'/');if($url==='')throw new InvalidArgumentException('Enter the website URL.');
    safe_url($url,!local_request());$parts=parse_url($url);
    if(isset($parts['query'])||isset($parts['fragment']))throw new InvalidArgumentException('The website URL cannot contain a query string or fragment.');
    $contact=posted('business_email',190);if($contact!==''&&!filter_var($contact,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid public business email, or leave it blank.');
    return ['name'=>$name,'email'=>strtolower($email),'hash'=>password_hash($password,PASSWORD_DEFAULT),'site_url'=>$url,'business_email'=>$contact,'seed'=>isset($_POST['seed']),'preview'=>isset($_POST['preview'])];
}
function setup_database_input(): array {
    $c=['host'=>posted('db_host',253),'port'=>(int)posted('db_port',5),'name'=>posted('db_name',64),'user'=>posted('db_user',128),'pass'=>posted('db_password',512),'prefix'=>posted('db_prefix',20)];
    if(!preg_match('/^[a-zA-Z0-9.-]+$/D',$c['host'])||$c['port']<1||$c['port']>65535||!preg_match('/^[a-zA-Z0-9_$-]+$/D',$c['name'])||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$c['prefix'])||$c['user']==='')throw new InvalidArgumentException('Check the database host, port, full database/user names and table prefix. The prefix must be 2–20 letters, digits or underscores, beginning with a letter.');
    return $c;
}
function setup_database_test(array $c): string {
    if(!extension_loaded('pdo_mysql'))throw new RuntimeException('Enable the pdo_mysql extension in your hosting PHP settings.');
    require_once CWP_CODE_ROOT.'/database/schema/base.php';
    try {
        $pdo=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['name'].';charset=utf8mb4',$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>8]);
        foreach(array_keys(schema_statements($c['prefix'])) as $name){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?');$s->execute([$c['name'],$c['prefix'].$name]);if((int)$s->fetchColumn()>0)throw new InvalidArgumentException('Application tables with this prefix already exist. Nothing was overwritten. Use the existing website or choose a different empty database/prefix.');}
        $probe=$c['prefix'].'probe_'.bin2hex(random_bytes(5));
        $pdo->exec('CREATE TABLE `'.$probe.'` (id INT PRIMARY KEY) ENGINE=InnoDB');
        try{$pdo->exec('INSERT INTO `'.$probe.'` VALUES (1)');$pdo->query('SELECT id FROM `'.$probe.'`')->fetchColumn();$pdo->exec('UPDATE `'.$probe.'` SET id=2');$pdo->exec('DELETE FROM `'.$probe.'`');}finally{$pdo->exec('DROP TABLE `'.$probe.'`');}
        return (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    } catch(PDOException $e){report_error($e);throw new RuntimeException('Database connection or permissions failed. Check the exact host, full prefixed database/user names and password. Assign this user SELECT, INSERT, UPDATE, DELETE, CREATE, DROP and INDEX for this database.');}
}
/** Caller holds the installation-wide mutex. No wizard/session prerequisite. */
function setup_deploy(array $o,array $c,string $storage): array {
    $created=[];$committed=false;$events=[];$warnings=[];$temp=null;
    try {
        clearstatcache();if(cwp_installer_installed())throw new RuntimeException('This website is already installed.');
        foreach([setup_root(),$storage,$storage.'/media'] as $dir){$probe=writable_probe($dir,false,0700);if(!$probe['ok'])throw new RuntimeException('Final write check failed: '.$dir.'. '.$probe['message']);}
        $cfg=['installer_version'=>CWP_INSTALLER_BUILD,'version'=>CWP_VERSION,'installed_at'=>gmdate('c'),'site_url'=>$o['site_url'],'key'=>bin2hex(random_bytes(32)),'storage'=>$storage,'db'=>$c];$GLOBALS['cwp_config']=$cfg;
        require_once CWP_CODE_ROOT.'/database/schema/base.php';$pdo=db();
        foreach(schema_statements($c['prefix']) as $name=>$sql){$pdo->exec($sql);$created[]=$name;}
        $events[]='Created '.count($created).' isolated application tables.';
        $pdo->beginTransaction();foreach(default_settings() as $k=>$v)save_setting($k,$v);
        save_setting('contact_email',$o['business_email']);save_setting('notification_email',$o['email']);save_setting('mail_from',$o['business_email']);save_setting('site_published',$o['preview']?'0':'1');
        query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',[$o['name'],$o['email'],$o['hash'],bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
        staff_seed_owner((int)$pdo->lastInsertId());
        save_setting('support_schema_version','1');save_setting('catalog_schema_version','1');save_setting('payments_schema_version','1');save_setting('payment_attempts_schema_version','1');save_setting('payment_notifications_schema_version','1');save_setting('invoice_ledger_schema_version','1');save_setting('delivery_schema_version','1');save_setting('activity_schema_version','1');save_setting('mail_templates_schema_version','1');save_setting('mail_accounts_schema_version','1');save_setting('website_reviews_schema_version','1');appointments_seed();save_setting('appointments_schema_version','1');save_setting('mail_connections_schema_version','1');save_setting('mail_inbox_schema_version','1');save_setting('mail_messages_schema_version','1');save_setting('mail_notices_schema_version','1');save_setting('provider_settings_schema_version','1');save_setting('provider_inventory_schema_version','5');save_setting('provider_operations_schema_version','4');save_setting('mail_drafts_schema_version','1');save_setting('mail_submissions_schema_version','1');save_setting('license_schema_version','3');save_setting('license_invoice_cutover','0');
        foreach(default_services() as $i=>$v)query('INSERT INTO '.table('services').' (slug,title,summary,body,icon,sort_order,active) VALUES (?,?,?,?,?,?,1)',[...$v,$i]);
        if($o['seed'])foreach(default_projects() as $i=>$v)query('INSERT INTO '.table('projects').' (slug,title,category,summary,description,challenge,solution,deliverables,cover,status,is_demo,featured,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,\'published\',1,1,?,?,?)',[...$v,$i,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
        log_event('install.complete','Installer '.CWP_INSTALLER_BUILD);$pdo->commit();$events[]='Saved the administrator account and website content.';
        $data="<?php\ndeclare(strict_types=1);\nif (!defined('CWP_BOOT')) { http_response_code(404); exit; }\nreturn ".var_export($cfg,true).";\n";
        $temp=setup_root().'/config-pending-'.bin2hex(random_bytes(10)).'.php';write_private_file($temp,$data);
        clearstatcache();if(cwp_installer_installed())throw new RuntimeException('Configuration appeared during setup. It was not overwritten.');
        if(!@rename($temp,setup_root().'/config.php'))throw new RuntimeException('The server could not activate config.php.');$committed=true;
        $events[]='Saved the permanent storage path in owner-only config.php.';
        foreach([setup_root().'/.installed.php',$storage.'/install.lock.php'] as $lock){try{write_private_file($lock,CWP_GUARD.'Installed '.gmdate('c'));}catch(Throwable $e){$warnings[]='Secondary installation lock could not be created. config.php still blocks reinstallation.';}}
        $events[]='Locked the website against accidental reinstallation.';
        $legacy=[];foreach(['install-key.php','install.php','assets/install.js','assets/install.css','setup-1.0.4.php'] as $f)$legacy[$f]=@lstat(setup_root().'/'.$f)!==false;
        try{$deleted=cwp_installer_cleanup();}catch(Throwable $e){$deleted=[];$warnings[]='The website is installed and locked, but installer cleanup needs attention in your hosting File Manager.';}
        foreach($deleted as $file=>$gone){if($gone&&!empty($legacy[$file]))$events[]='Removed '.$file.'.';elseif(!$gone)$warnings[]=$file.' could not be deleted. The installed-site guard blocks setup. Remove only this installer file in File Manager.';}
        $events[]='Private files and media use: '.$storage;
        unset($_SESSION['setup_db'],$_SESSION['setup_owner'],$_SESSION['setup_storage'],$_SESSION['setup_until']);
        @session_regenerate_id(true);
        return ['ok'=>true,'events'=>$events,'warnings'=>$warnings,'deleted'=>$deleted,'storage'=>$storage,'admin'=>$o['site_url'].'/admin/index.php','site'=>$o['site_url'].'/'];
    } catch(Throwable $e){
        if(!$committed){
            if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
            if(isset($pdo))foreach(array_reverse($created) as $name){try{$pdo->exec('DROP TABLE '.table($name));}catch(Throwable $ignored){error_log('CWP installer rollback: newly created table cleanup needs attention.');}}
            if($temp&&@is_file($temp))@unlink($temp);
            throw $e;
        }
        // A post-commit cleanup fault must never drop a successfully installed DB.
        report_error($e);
        return ['ok'=>true,'events'=>$events,'warnings'=>['The website configuration is active. A final cleanup action needs attention; sign in rather than reinstall.'],'deleted'=>[],'storage'=>$storage,'admin'=>$o['site_url'].'/admin/index.php','site'=>$o['site_url'].'/'];
    }
}
// Request dispatch: a single Install action validates every field and prerequisite.
