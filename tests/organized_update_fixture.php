<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$root=realpath($argv[1]);$base=realpath(__DIR__.'/../test-runtime');
if(!$root || !$base || !str_starts_with($root,$base.DIRECTORY_SEPARATOR))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);
require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';
if(config()['db']['name']!=='cwp_test')exit(2);
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
        require_once $root.'/app/mail/accounts.php';$_SERVER['DOCUMENT_ROOT']=dirname($root);$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1','SCRIPT_NAME'=>'/admin/index.php','REQUEST_METHOD'=>'GET'];start_session();
        $actor=one('SELECT * FROM '.table('users').' WHERE id=1');$_SESSION=['uid'=>1,'auth_version'=>$actor['auth_version'],'login_at'=>time(),'last_activity'=>time()];
        $password='Synthetic organized upgrade passphrase';
        $id=mail_accounts_save('',0,['label'=>'Earlier saved email account','address'=>'earlier@example.invalid','imap_host'=>'imap.example.invalid','imap_port'=>'993','imap_encryption'=>'ssl','imap_username'=>'earlier-incoming','smtp_host'=>'smtp.example.invalid','smtp_port'=>'587','smtp_encryption'=>'starttls','smtp_username'=>'earlier-outgoing'],true,['imap_password'=>'Synthetic earlier incoming password','smtp_password'=>'Synthetic earlier outgoing password'],$password);
        mail_accounts_grant($id,1,0,true,false,true,0,$password);
        query('INSERT INTO '.table('leads')." (reference,request_key,name,email,service,budget,timeline,message,consent,ip_hash,status,notes,created_at) VALUES (?,?,?,?,'Website review','Agreed','Flexible','Original review inquiry text',1,?,'new','Original private review note',?)",['ORGANIZED-REVIEW',str_repeat('a',64),'Earlier review prospect','review-upgrade@example.invalid',str_repeat('b',64),$now]);
    }
}
$result=[];
foreach(['settings','services','projects','studio_clients','studio_jobs','studio_invoices'] as $name){
    $rows=query('SELECT * FROM '.table($name))->fetchAll();
    $encoded=array_map(fn($row)=>json_encode($row,JSON_THROW_ON_ERROR),$rows);sort($encoded);
    $result[$name]=['count'=>count($rows),'sha256'=>hash('sha256',json_encode($encoded,JSON_THROW_ON_ERROR))];
}
echo json_encode($result,JSON_THROW_ON_ERROR);
