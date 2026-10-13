<?php
declare(strict_types=1);
defined('CWP_BOOT') || exit;
require_once __DIR__.'/delivery-archive.php';
function delivery_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';return [
    'website_releases'=>'CREATE TABLE `'.$prefix.'website_releases` (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,release_no INT UNSIGNED NOT NULL,version VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,title VARCHAR(160) NOT NULL,scope VARCHAR(10) NOT NULL,notes TEXT NOT NULL,source_revision VARCHAR(64) NOT NULL,bytes INT UNSIGNED NOT NULL,sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,manifest LONGTEXT NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,status VARCHAR(20) NOT NULL,created_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,published_by INT UNSIGNED NULL,published_at DATETIME NULL,UNIQUE project_version(job_id,version),UNIQUE project_sequence(job_id,release_no),INDEX client_releases(client_id,status,job_id))'.$engine,
    'delivery_events'=>'CREATE TABLE `'.$prefix.'delivery_events` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,release_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,client_id INT UNSIGNED NOT NULL,actor_type VARCHAR(10) NOT NULL,actor_id INT UNSIGNED NOT NULL,action VARCHAR(30) NOT NULL,detail VARCHAR(160) NOT NULL,created_at DATETIME NOT NULL,INDEX release_events(release_id,id))'.$engine];
}
function delivery_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['delivery_schema_version'])['value']??'')==='1';}
function delivery_require(): array {if(support_current())throw new RuntimeException('Leave support before managing website releases.');if(!delivery_ready())throw new RuntimeException('Enable website deliveries first.');return staff_require('clients.manage');}
function delivery_audit(int $actor,string $action,string $detail): void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'deliveries.'.$action,$detail,gmdate('Y-m-d H:i:s')]);}
function delivery_event(array $r,string $actorType,int $actor,string $action,string $detail=''): void {query('INSERT INTO '.table('delivery_events').' (release_id,client_id,actor_type,actor_id,action,detail,created_at) VALUES (?,?,?,?,?,?,?)',[$r['id'],$r['client_id'],$actorType,$actor,$action,$detail,gmdate('Y-m-d H:i:s')]);}
function delivery_directory(): string {
    $storage=config()['storage'];$real=realpath($storage);if($real===false||is_link($storage)||!is_dir($real))throw new RuntimeException('Private delivery storage is unavailable.');
    $canonical=fn($p)=>strtolower(str_replace('\\','/',rtrim($p,'/\\')));
    foreach([CWP_ROOT,$_SERVER['DOCUMENT_ROOT']??''] as $root){if($root===''||($web=realpath($root))===false)continue;$a=$canonical($real);$b=$canonical($web);if($a===$b||str_starts_with($a,$b.'/'))throw new RuntimeException('Website deliveries require private storage outside the web document root.');}
    $dir=$real.'/deliveries';if(is_link($dir))throw new RuntimeException('Delivery storage cannot be a symbolic link.');
    if(!is_dir($dir)&&!mkdir($dir,0700))throw new RuntimeException('Delivery storage could not be created.');
    if(($resolved=realpath($dir))===false||$canonical($resolved)!==$canonical($dir))throw new RuntimeException('Delivery storage path could not be verified.');return $resolved;
}
function delivery_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {if(support_current())throw new RuntimeException('Leave support before setup.');$actor=staff_owner($password);if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');delivery_directory();
        foreach(delivery_schema(config()['db']['prefix']) as $name=>$sql){if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){catalog_validate_schema($name,$sql,$name==='website_releases'?['request_key'=>['request_key'],'project_version'=>['job_id','version'],'project_sequence'=>['job_id','release_no']]:[]);foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if(in_array($c['Field'],['id','release_id','version','sha256','request_key','request_hash'],true)&&str_contains(strtolower($c['Type']),'char')&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible delivery identifiers.');}else db()->exec($sql);}
        staff_transaction(function() use($actor): void {if(!delivery_ready()){foreach(['website_releases','delivery_events'] as $table)if((int)query('SELECT COUNT(*) FROM '.table($table))->fetchColumn())throw new RuntimeException('Unrecognized delivery records exist. No data was adopted.');save_setting('delivery_schema_version','1');}delivery_audit((int)$actor['id'],'setup','Additive website delivery schema verified');});
    });
}
function delivery_scopes(): array {return ['static'=>'Static website — no database required','files'=>'Website files only — database/content excluded'];}
function delivery_metadata(array $data): array {
    $out=[];foreach(['version'=>40,'title'=>160,'scope'=>10,'notes'=>5000,'source_revision'=>64] as $key=>$limit){$v=$data[$key]??'';if(!is_string($v)||strlen($v)>$limit)throw new InvalidArgumentException('Invalid release details.');$out[$key]=trim($v);}
    if(!preg_match('/^[a-z0-9][a-z0-9._-]{0,39}$/D',$out['version'])||$out['title']===''||!isset(delivery_scopes()[$out['scope']])||strlen($out['notes'])<20||$out['source_revision']!==''&&!preg_match('/^[a-f0-9]{7,64}$/D',$out['source_revision']))throw new InvalidArgumentException('Add a version, title, accurate package scope and handover notes (20–5000 characters). Source revision is an optional Git hash.');return $out;
}
function delivery_upload(int $job,array $data,string $path,string $key): string {
    delivery_require();$data=delivery_metadata($data);if(!preg_match('/^[a-f0-9]{64}$/D',$key)||PHP_SAPI!=='cli'&&!is_uploaded_file($path))throw new InvalidArgumentException('Choose a new ZIP upload and reload the form.');
    $scan=delivery_inspect($path,$data['scope']);$hash=hash('sha256',json_encode([$job,$data,$scan],JSON_THROW_ON_ERROR));
    return staff_locked(function() use($job,$data,$path,$key,$scan,$hash): string {$written=null;
        try{return staff_transaction(function() use($job,$data,$path,$key,$scan,$hash,&$written): string {
            $actor=delivery_require();$old=one('SELECT * FROM '.table('website_releases').' WHERE request_key=?',[$key]);if($old){if((int)$old['created_by']!==(int)$actor['id']||!hash_equals($old['request_hash'],$hash))throw new RuntimeException('This upload request was already used with different details.');return $old['id'];}
            $project=one('SELECT j.*,c.active FROM '.table('studio_jobs').' j JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE j.id=?',[$job]);if(!$project||(int)$project['active']!==1)throw new RuntimeException('Choose a project for an active client.');
            if(one('SELECT id FROM '.table('website_releases').' WHERE job_id=? AND version=?',[$job,$data['version']]))throw new RuntimeException('That project version already exists. Upload a new version without replacing it.');
            $limits=delivery_limits();$total=(int)query('SELECT COALESCE(SUM(bytes),0) FROM '.table('website_releases'))->fetchColumn();$used=(int)query('SELECT COALESCE(SUM(bytes),0) FROM '.table('website_releases').' WHERE job_id=?',[$job])->fetchColumn();if($total+$scan['bytes']>$limits['site']||$used+$scan['bytes']>$limits['project'])throw new RuntimeException('Delivery storage limit reached (250 MiB/project, 1 GiB/site). Retain existing releases and arrange a reviewed storage plan.');
            $id=bin2hex(random_bytes(16));$file=delivery_directory().'/release-'.$id.'.php';$dest=fopen($file,'xb');if(!$dest)throw new RuntimeException('Private release could not be created.');$written=$file;
            try{if(!chmod($file,0600)||fwrite($dest,CWP_GUARD)!==strlen(CWP_GUARD))throw new RuntimeException('Private release protection failed.');$source=fopen($path,'rb');if(!$source)throw new RuntimeException('Upload could not be read.');try{$n=stream_copy_to_stream($source,$dest,$scan['bytes']+1);}finally{fclose($source);}if($n!==$scan['bytes']||!fflush($dest))throw new RuntimeException('Private release write was incomplete.');}finally{fclose($dest);}
            $number=1+(int)query('SELECT COALESCE(MAX(release_no),0) FROM '.table('website_releases').' WHERE job_id=?',[$job])->fetchColumn();
            query('INSERT INTO '.table('website_releases').' (id,job_id,client_id,release_no,version,title,scope,notes,source_revision,bytes,sha256,manifest,request_key,request_hash,status,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$job,$project['client_id'],$number,$data['version'],$data['title'],$data['scope'],$data['notes'],$data['source_revision'],$scan['bytes'],$scan['sha256'],json_encode($scan,JSON_THROW_ON_ERROR),$key,$hash,'draft',$actor['id'],gmdate('Y-m-d H:i:s')]);
            $r=delivery_record($id);$fh=delivery_open($r);fclose($fh);delivery_event($r,'admin',(int)$actor['id'],'upload');delivery_audit((int)$actor['id'],'upload','Release '.$id.' project '.$job);return $id;
        });}catch(Throwable $e){if($written){try{if(!one('SELECT id FROM '.table('website_releases').' WHERE id=?',[substr(basename($written),8,32)]))@unlink($written);}catch(Throwable){/* Preserve an uncertain file for operator reconciliation; never remove a committed release. */}}throw $e;}
    });
}
function delivery_record(string $id): array {if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Release unavailable.');$r=one('SELECT * FROM '.table('website_releases').' WHERE id=?',[$id]);if(!$r)throw new RuntimeException('Release unavailable.');return $r;}
function delivery_review_hash(array $r): string {return hash('sha256',json_encode(array_intersect_key($r,array_flip(['id','job_id','client_id','release_no','version','title','scope','notes','source_revision','bytes','sha256','manifest','created_by','created_at'])),JSON_THROW_ON_ERROR));}
/** Open once, verify the exact bytes on that descriptor, then rewind past its PHP guard. */
function delivery_open(array $r) {
    if(!preg_match('/^[a-f0-9]{32}$/D',$r['id'])||!preg_match('/^[a-f0-9]{64}$/D',$r['sha256'])||(int)$r['bytes']<22||(int)$r['bytes']>delivery_limits()['archive'])throw new RuntimeException('Release integrity check failed.');
    $path=delivery_directory().'/release-'.$r['id'].'.php';if(is_link($path)||!is_file($path))throw new RuntimeException('Release file is unavailable.');$fh=fopen($path,'rb');if(!$fh)throw new RuntimeException('Release file is unavailable.');
    try{if(fread($fh,strlen(CWP_GUARD))!==CWP_GUARD)throw new RuntimeException('Release guard check failed.');$hash=hash_init('sha256');$n=hash_update_stream($hash,$fh,(int)$r['bytes']+1);if($n!==(int)$r['bytes']||!hash_equals($r['sha256'],hash_final($hash)))throw new RuntimeException('Release integrity check failed.');if(fseek($fh,strlen(CWP_GUARD))!==0)throw new RuntimeException('Release could not be read.');return $fh;}catch(Throwable $e){fclose($fh);throw $e;}
}
function delivery_publish(string $id,string $review,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function() use($id,$review,$password): void {$actor=delivery_require();staff_owner($password);$r=delivery_record($id);if(!hash_equals(delivery_review_hash($r),$review))throw new RuntimeException('Release review changed. Reload and inspect it again.');
        $job=one('SELECT j.client_id,c.active FROM '.table('studio_jobs').' j JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE j.id=?',[$r['job_id']]);if(!$job||(int)$job['active']!==1||(int)$job['client_id']!==(int)$r['client_id'])throw new RuntimeException('Release ownership or client access changed.');
        $fh=delivery_open($r);fclose($fh);if($r['status']==='published')return;if($r['status']!=='draft')throw new RuntimeException('Only a reviewed draft can be published.');
        query('UPDATE '.table('website_releases')." SET status='published',published_by=?,published_at=? WHERE id=?",[$actor['id'],gmdate('Y-m-d H:i:s'),$id]);delivery_event($r,'admin',(int)$actor['id'],'publish');delivery_audit((int)$actor['id'],'publish','Release '.$id.' project '.$r['job_id']);
    }));
}
function delivery_client_record(string $id): array {
    if(!delivery_ready()||!($client=studio_client()))throw new RuntimeException('Release unavailable.');$r=delivery_record($id);$job=studio_job((int)$r['job_id'],(int)$client['id']);if($r['status']!=='published'||(int)$r['client_id']!==(int)$client['id'])throw new RuntimeException('Release unavailable.');return $r;
}
function delivery_range(string $header,int $size): array {
    if($header==='')return [0,$size-1,200];if(!preg_match('/^bytes=(\d{0,10})-(\d{0,10})$/D',$header,$m)||$m[1]===''&&$m[2]==='')throw new InvalidArgumentException('Unsupported byte range.');
    if($m[1]===''){$length=(int)$m[2];if($length<1)throw new InvalidArgumentException('Invalid byte range.');return [max(0,$size-$length),$size-1,206];}
    $start=(int)$m[1];$end=$m[2]===''?$size-1:min((int)$m[2],$size-1);if($start>=$size||$start>$end)throw new InvalidArgumentException('Invalid byte range.');return [$start,$end,206];
}
function delivery_download(string $id,bool $admin=false): never {
    if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){header('Allow: GET, HEAD');fail_page('Method not allowed.',405);}
    $fh=null;
    try{[$r,$fh,$actorType,$actorId]=staff_locked(function() use($id,$admin): array {
        if($admin){$actor=delivery_require();$r=delivery_record($id);$type='admin';$actorId=(int)$actor['id'];}
        else{if($ctx=support_current()){$fresh=support_resolve($ctx['session']['id']);if(!$fresh||!staff_can('support.download',$fresh['actor'])){support_record('release.download','release',preg_match('/^[a-f0-9]{32}$/D',$id)?$id:'','denied');throw new RuntimeException('Download is unavailable.');}$GLOBALS['cwp_support']=$fresh;}$r=delivery_client_record($id);$type=support_current()?'support':'client';$actorId=(int)(support_current()['actor']['id']??studio_client()['id']);}
        return [$r,delivery_open($r),$type,$actorId];
    });}catch(InvalidArgumentException|RuntimeException){if(support_current())support_record('release.download','release',preg_match('/^[a-f0-9]{32}$/D',$id)?$id:'','denied');fail_page('Website release unavailable. Contact the studio.',404);}
    $size=(int)$r['bytes'];$etag='"'.$r['sha256'].'"';$range=$_SERVER['HTTP_RANGE']??'';if($_SERVER['REQUEST_METHOD']==='HEAD'||isset($_SERVER['HTTP_IF_RANGE'])&&$_SERVER['HTTP_IF_RANGE']!==$etag)$range='';
    try{[$start,$end,$status]=delivery_range($range,$size);}catch(InvalidArgumentException){fclose($fh);header('Content-Range: bytes */'.$size);fail_page('Requested range is unavailable.',416);}
    try{staff_locked(fn()=>staff_transaction(function() use($id,$admin,$r,$actorType,$actorId,$start,$end): void {
        if($admin)delivery_require();else{if($ctx=support_current()){$fresh=support_check($ctx['session']['id']);if(!$fresh||!staff_can('support.download',$fresh['actor']))throw new RuntimeException('Support access ended.');$GLOBALS['cwp_support']=$fresh;}delivery_client_record($id);}
        $action=$_SERVER['REQUEST_METHOD']==='HEAD'?'inspect':'download.started';delivery_event($r,$actorType,$actorId,$action,'bytes '.$start.'-'.$end);if(support_current())support_record('release.download','release',$id);elseif($admin)delivery_audit($actorId,$action,'Release '.$id);
    }));}catch(Throwable){fclose($fh);fail_page('Download could not be recorded. Try again or contact the studio.',503);}
    http_response_code($status);header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="website-'.$r['job_id'].'-'.$r['version'].'.zip"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('ETag: '.$etag);header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));if($status===206)header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();if($_SERVER['REQUEST_METHOD']==='GET'){fseek($fh,strlen(CWP_GUARD)+$start);$left=$end-$start+1;while($left>0&&!feof($fh)){ $chunk=fread($fh,min(65536,$left));if($chunk===false||$chunk==='')break;echo $chunk;$left-=strlen($chunk);}}fclose($fh);exit;
}
