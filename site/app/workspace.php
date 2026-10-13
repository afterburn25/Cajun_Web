<?php
/** Optional private client workspace; database setup is explicit for existing sites. */
declare(strict_types=1);defined('CWP_BOOT') || exit;
function studio_schema(string $prefix): array {
 if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
 $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';return [
 'studio_clients'=>'CREATE TABLE '.$t('studio_clients')." (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL DEFAULT '',auth_version CHAR(32) NOT NULL,active TINYINT NOT NULL DEFAULT 1,invite_hash CHAR(64) NULL,invite_expires BIGINT NULL,created_at DATETIME NOT NULL)".$e,
 'studio_jobs'=>'CREATE TABLE '.$t('studio_jobs')." (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,client_id INT UNSIGNED NOT NULL,title VARCHAR(160) NOT NULL,summary TEXT NOT NULL,stage VARCHAR(30) NOT NULL DEFAULT 'discovery',milestones LONGTEXT NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX client_lookup(client_id,id))".$e,
 'studio_messages'=>'CREATE TABLE '.$t('studio_messages')." (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,job_id INT UNSIGNED NOT NULL,author_type VARCHAR(10) NOT NULL,author_id INT UNSIGNED NOT NULL,body TEXT NOT NULL,created_at DATETIME NOT NULL,INDEX job_lookup(job_id,id))".$e,
 'studio_versions'=>'CREATE TABLE '.$t('studio_versions')." (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,job_id INT UNSIGNED NOT NULL,kind VARCHAR(20) NOT NULL,version_no INT UNSIGNED NOT NULL,title VARCHAR(160) NOT NULL,body LONGTEXT NOT NULL,preview_url VARCHAR(500) NOT NULL DEFAULT '',decision VARCHAR(20) NOT NULL DEFAULT 'pending',feedback TEXT NOT NULL,decided_by INT UNSIGNED NULL,decided_at DATETIME NULL,created_at DATETIME NOT NULL,UNIQUE version_lookup(job_id,kind,version_no))".$e,
 'studio_files'=>'CREATE TABLE '.$t('studio_files')." (id CHAR(32) PRIMARY KEY,job_id INT UNSIGNED NOT NULL,author_type VARCHAR(10) NOT NULL,original_name VARCHAR(190) NOT NULL,stored_name VARCHAR(80) NOT NULL,mime VARCHAR(100) NOT NULL,bytes INT UNSIGNED NOT NULL,sha256 CHAR(64) NOT NULL,created_at DATETIME NOT NULL,INDEX job_lookup(job_id,id))".$e,
 'studio_revisions'=>'CREATE TABLE '.$t('studio_revisions')." (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,setting_name VARCHAR(100) NOT NULL,old_value LONGTEXT NOT NULL,new_value LONGTEXT NOT NULL,author_id INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,INDEX content_lookup(setting_name,id))".$e,
 'studio_invoices'=>'CREATE TABLE '.$t('studio_invoices')." (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,job_id INT UNSIGNED NOT NULL,reference VARCHAR(40) NOT NULL UNIQUE,title VARCHAR(160) NOT NULL,amount_cents INT UNSIGNED NOT NULL,currency CHAR(3) NOT NULL DEFAULT 'USD',status VARCHAR(20) NOT NULL DEFAULT 'issued',instructions TEXT NOT NULL,paid_note VARCHAR(500) NOT NULL DEFAULT '',created_at DATETIME NOT NULL,paid_at DATETIME NULL,INDEX job_lookup(job_id,id))".$e,
 ];
}
function studio_ready(): bool {return setting('studio_schema_version')==='2';}
function studio_migrate(): void {
 require_admin();$name='cwp-studio-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
 if((int)query('SELECT GET_LOCK(?,5)',[$name])->fetchColumn()!==1)throw new RuntimeException('Workspace update is already running.');
 try{foreach(studio_schema(config()['db']['prefix']) as $key=>$sql){
  $exists=one('SELECT table_name FROM information_schema.tables WHERE table_schema=? AND table_name=?',[config()['db']['name'],config()['db']['prefix'].$key]);
  if($exists){$columns=all('SHOW COLUMNS FROM '.table($key));$cols=array_column($columns,'Field');preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) (?:INT|BIGINT|VARCHAR|CHAR|TINYINT|DATETIME|LONGTEXT|TEXT)/',$sql,$m);if($cols!==$m[1]||!studio_schema_compatible($key,$sql,$columns))throw new RuntimeException('An incompatible table already uses '.$key.'. No existing table was overwritten.');}
  else db()->exec($sql);
 }save_setting('studio_schema_version','2');log_event('studio.schema','Additive workspace schema 2');}
 finally{query('SELECT RELEASE_LOCK(?)',[$name]);}
}
function studio_stages(): array {return ['discovery'=>'Discovery','design'=>'Design','development'=>'Development','review'=>'Final review','launched'=>'Launched','care'=>'Ongoing care','paused'=>'Paused'];}
function studio_url(array $p=[]): string {return site_url('portal.php').($p?'?'.http_build_query($p):'');}
function studio_client(): ?array {
 if(!studio_ready()||empty($_SESSION['client_id']))return null;
 if(time()-(int)($_SESSION['client_last']??0)>1800||time()-(int)($_SESSION['client_login']??0)>28800){unset($_SESSION['client_id']);return null;}
 $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[(int)$_SESSION['client_id']]);
 if(!$c||!hash_equals($c['auth_version'],(string)($_SESSION['client_auth']??''))){unset($_SESSION['client_id']);return null;}$_SESSION['client_last']=time();return $c;
}
function studio_job(int $id,?int $clientId=null): array {
 $p=[$id];$sql='SELECT * FROM '.table('studio_jobs').' WHERE id=?';if($clientId!==null){$sql.=' AND client_id=?';$p[]=$clientId;}$j=one($sql,$p);if(!$j)throw new InvalidArgumentException('Project not found or access denied.');return $j;
}
function studio_new_invite(int $id): string {require_admin();if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$id]))throw new InvalidArgumentException('Active client not found.');$token=bin2hex(random_bytes(32));query('UPDATE '.table('studio_clients').' SET invite_hash=?,invite_expires=?,auth_version=? WHERE id=?',[hash('sha256',$token),time()+86400,bin2hex(random_bytes(16)),$id]);return studio_url(['invite'=>$token]);}
function studio_activate(string $token,string $password): void {
 validate_password($password);if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new InvalidArgumentException('Invalid invitation.');
 db()->beginTransaction();try{$c=one('SELECT * FROM '.table('studio_clients').' WHERE invite_hash=? AND invite_expires>? AND active=1 FOR UPDATE',[hash('sha256',$token),time()]);if(!$c)throw new InvalidArgumentException('This invitation expired or has already been used. Ask the studio for a new link.');query('UPDATE '.table('studio_clients').' SET password_hash=?,auth_version=?,invite_hash=NULL,invite_expires=NULL WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),$c['id']]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function studio_login(string $email,string $password): void {
 $allow1=consume_rate(ip_key('client-login'),15,900);$allow2=consume_rate(hash_hmac('sha256','client|'.$email,config()['key']),8,900);if(!$allow1||!$allow2)throw new RuntimeException('Too many attempts. Wait 15 minutes.');
 $c=one('SELECT * FROM '.table('studio_clients').' WHERE email=? AND active=1',[$email]);$hash=$c['password_hash']??'$2y$12$k41gRE1TEYzHWgNYkc/yDuDTWYfPr6HvCaDvQfmUTDpAWPSgwOI6u';$ok=password_verify($password,$hash);if(!$c||!$ok)throw new InvalidArgumentException('Email or password not recognized.');session_regenerate_id(true);$_SESSION['client_id']=(int)$c['id'];$_SESSION['client_auth']=$c['auth_version'];$_SESSION['client_last']=$_SESSION['client_login']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
}
/** Decision attached to an immutable version, with a project row lock shared by publication. */
function studio_decide(int $clientId,int $versionId,string $choice,string $feedback): void {
 $acting=studio_client();if(!$acting||(int)$acting['id']!==$clientId)throw new InvalidArgumentException('Sign in to review your own project.');
 if(!in_array($choice,['approved','changes'],true)||strlen($feedback)>5000||($choice==='changes'&&strlen(trim($feedback))<5))throw new InvalidArgumentException('Add change details or choose approval.');
 db()->beginTransaction();try{
  $initial=one('SELECT job_id FROM '.table('studio_versions').' WHERE id=?',[$versionId]);if(!$initial)throw new InvalidArgumentException('Review not found.');
  $job=one('SELECT id FROM '.table('studio_jobs').' WHERE id=? AND client_id=? FOR UPDATE',[$initial['job_id'],$clientId]);if(!$job)throw new InvalidArgumentException('Review not found or access denied.');
  $v=one('SELECT * FROM '.table('studio_versions').' WHERE id=? FOR UPDATE',[$versionId]);
  $latest=(int)query('SELECT MAX(version_no) FROM '.table('studio_versions').' WHERE job_id=? AND kind=?',[$v['job_id'],$v['kind']])->fetchColumn();
  if($v['decision']!=='pending'||(int)$v['version_no']!==$latest)throw new InvalidArgumentException('This review is already decided or has been superseded. Refresh the project.');
  query('UPDATE '.table('studio_versions').' SET decision=?,feedback=?,decided_by=?,decided_at=? WHERE id=?',[$choice,$feedback,$clientId,gmdate('Y-m-d H:i:s'),$versionId]);
  query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,?,?,?,?)',[$v['job_id'],'client',$clientId,$v['kind'].' version '.$v['version_no'].': '.$choice."\n".$feedback,gmdate('Y-m-d H:i:s')]);db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function studio_publish(int $jobId,string $kind,string $title,string $body,string $url): void {
 require_admin();
 if(!in_array($kind,['design','proposal'],true)||$title===''||$body==='')throw new InvalidArgumentException('Add a title, body and valid review type.');safe_url($url,true);
 db()->beginTransaction();try{if(!one('SELECT id FROM '.table('studio_jobs').' WHERE id=? FOR UPDATE',[$jobId]))throw new InvalidArgumentException('Project not found.');$n=1+(int)query('SELECT COALESCE(MAX(version_no),0) FROM '.table('studio_versions').' WHERE job_id=? AND kind=?',[$jobId,$kind])->fetchColumn();query('INSERT INTO '.table('studio_versions')." (job_id,kind,version_no,title,body,preview_url,decision,feedback,created_at) VALUES (?,?,?,?,?,?,'pending','',?)",[$jobId,$kind,$n,$title,$body,$url,gmdate('Y-m-d H:i:s')]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function studio_upload(int $jobId,string $actor): void {
 if($actor==='admin'){require_admin();studio_job($jobId);}elseif($actor==='client'){$c=studio_client();if(!$c)throw new InvalidArgumentException('Sign in to upload.');studio_job($jobId,(int)$c['id']);}else throw new InvalidArgumentException('Invalid file actor.');
 $f=$_FILES['document']??null;
 if(!is_array($f)||!is_int($f['error']??null)||$f['error']!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name']))throw new InvalidArgumentException('Choose a PDF, PNG or JPEG document.');
 $size=filesize($f['tmp_name']);if($size===false||$size<1||$size>8*1024*1024)throw new InvalidArgumentException('Files must be between 1 byte and 8 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);if(!in_array($mime,['application/pdf','image/png','image/jpeg'],true))throw new InvalidArgumentException('Only PDF, PNG and JPEG files are accepted.');
 $bytes=file_get_contents($f['tmp_name']);if($bytes===false||strlen($bytes)!==$size)throw new RuntimeException('File could not be read completely.');
 $name=substr(preg_replace('/[^a-zA-Z0-9 ._()-]/','_',basename(is_string($f['name']??null)?$f['name']:'document')),0,180)?:'document';
 $id=bin2hex(random_bytes(16));$path=config()['storage'].'/media/studio-'.$id.'.php';
 db()->beginTransaction();$written=false;try {
  if(!one('SELECT id FROM '.table('studio_jobs').' WHERE id=? FOR UPDATE',[$jobId]))throw new InvalidArgumentException('Project not found.');
  $sum=(int)query('SELECT COALESCE(SUM(bytes),0) FROM '.table('studio_files').' WHERE job_id=?',[$jobId])->fetchColumn();if($sum+$size>100*1024*1024)throw new RuntimeException('This project has reached its 100 MB file limit.');
  write_private_file($path,CWP_GUARD.$bytes);$written=true;
  query('INSERT INTO '.table('studio_files').' (id,job_id,author_type,original_name,stored_name,mime,bytes,sha256,created_at) VALUES (?,?,?,?,?,?,?,?,?)',[$id,$jobId,$actor,$name,basename($path),$mime,$size,hash('sha256',$bytes),gmdate('Y-m-d H:i:s')]);db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($written)@unlink($path);throw $e;}
}
function studio_download(string $id,?int $clientId): never {
 if(!preg_match('/^[a-f0-9]{32}$/D',$id))fail_page('File not found.',404);
 $p=[$id];$sql='SELECT f.* FROM '.table('studio_files').' f JOIN '.table('studio_jobs').' j ON j.id=f.job_id WHERE f.id=?';if($clientId!==null){$c=studio_client();if(!$c||(int)$c['id']!==$clientId)fail_page('Sign in to view this file.',403);$sql.=' AND j.client_id=?';$p[]=$clientId;}else require_admin();
 $f=one($sql,$p);if(!$f)fail_page('File not found.',404);if(!preg_match('/^studio-[a-f0-9]{32}\.php$/D',$f['stored_name']))fail_page('Invalid file record.',500);
 $path=config()['storage'].'/media/'.$f['stored_name'];if(is_link($path)||!is_file($path))fail_page('File unavailable.',404);$fh=fopen($path,'rb');if(!$fh)fail_page('File unavailable.',404);if(fread($fh,strlen(CWP_GUARD))!==CWP_GUARD){fclose($fh);fail_page('File failed its safety check.',500);}$ctx=hash_init('sha256');$count=hash_update_stream($ctx,$fh);if($count!==(int)$f['bytes']||!hash_equals($f['sha256'],hash_final($ctx))){fclose($fh);fail_page('File integrity check failed.',500);}fseek($fh,strlen(CWP_GUARD));header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],'',$f['original_name']).'"');header('Content-Length: '.$f['bytes']);header('Cache-Control: no-store, private');fpassthru($fh);fclose($fh);exit;
}

/** Verify existing table types, transaction engine and required unique keys before adoption. */
function studio_schema_compatible(string $key,string $sql,array $columns): bool {
 $row=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$key]);if(strtolower((string)($row['ENGINE']??''))!=='innodb')return false;
 preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT|TINYINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\)|DATETIME|LONGTEXT|TEXT)([^,]*)/',$sql,$m,PREG_SET_ORDER);
 foreach($m as $i=>$expected){$actual=$columns[$i]??[];$type=preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower((string)($actual['Type']??'')));if($type!==strtolower($expected[2]))return false; $nullable=!str_contains($expected[3],'NOT NULL')&&!str_contains($expected[3],'PRIMARY KEY');if(($actual['Null']??'')!==($nullable?'YES':'NO'))return false;}
 $unique=[];foreach(all('SHOW INDEX FROM '.table($key)) as $index)if((int)$index['Non_unique']===0)$unique[$index['Key_name']][(int)$index['Seq_in_index']]=$index['Column_name'];
 $expected=$key==='studio_clients'?['PRIMARY'=>['id'],'email'=>['email']]:($key==='studio_versions'?['PRIMARY'=>['id'],'version_lookup'=>['job_id','kind','version_no']]:($key==='studio_invoices'?['PRIMARY'=>['id'],'reference'=>['reference']]:['PRIMARY'=>['id']]));
 foreach($expected as $name=>$fields){if(!isset($unique[$name]))return false;ksort($unique[$name]);if(array_values($unique[$name])!==$fields)return false;}return true;
}
