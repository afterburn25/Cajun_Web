<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function studio_upload(int $jobId,string $actor): void {
 if($actor==='admin'){require_admin();studio_job($jobId);}elseif($actor==='client'){$c=studio_client();if(!$c)throw new InvalidArgumentException('Sign in to upload.');studio_job($jobId,(int)$c['id']);}else throw new InvalidArgumentException('Invalid file actor.');
 $f=$_FILES['document']??null;
 if(!is_array($f)||!is_int($f['error']??null)||$f['error']!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name']))throw new InvalidArgumentException('Choose a PDF, PNG or JPEG document.');
 $size=filesize($f['tmp_name']);if($size===false||$size<1||$size>8*1024*1024)throw new InvalidArgumentException('Files must be between 1 byte and 8 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);if(!in_array($mime,['application/pdf','image/png','image/jpeg'],true))throw new InvalidArgumentException('Only PDF, PNG and JPEG files are accepted.');
 $bytes=file_get_contents($f['tmp_name']);if($bytes===false||strlen($bytes)!==$size)throw new RuntimeException('File could not be read completely.');
 $extension=strtolower(pathinfo(is_string($f['name']??null)?$f['name']:'',PATHINFO_EXTENSION));
 if(!in_array($extension,['pdf','png','jpg','jpeg'],true)||preg_match('/<\?(?:php|=)/i',$bytes))throw new InvalidArgumentException('Only PDF, PNG and JPEG files without executable code are accepted.');
 $name=substr(preg_replace('/[^a-zA-Z0-9 ._()-]/','_',basename(is_string($f['name']??null)?$f['name']:'document')),0,180)?:'document';
 $id=bin2hex(random_bytes(16));$path=config()['storage'].'/media/studio-'.$id.'.php';
 db()->beginTransaction();$written=false;try {
  if($ctx=support_current()){
   $fresh=support_check($ctx['session']['id']);if(!$fresh||!staff_can('support.write',$fresh['actor']))throw new RuntimeException('Support upload is no longer permitted.');
   if(!one('SELECT id FROM '.table('studio_jobs').' WHERE id=? AND client_id=? FOR UPDATE',[$jobId,$fresh['client']['id']]))throw new InvalidArgumentException('Project not found.');
  }elseif(!one('SELECT id FROM '.table('studio_jobs').' WHERE id=? FOR UPDATE',[$jobId]))throw new InvalidArgumentException('Project not found.');
  $sum=(int)query('SELECT COALESCE(SUM(bytes),0) FROM '.table('studio_files').' WHERE job_id=?',[$jobId])->fetchColumn();if($sum+$size>100*1024*1024)throw new RuntimeException('This project has reached its 100 MB file limit.');
  write_private_file($path,CWP_GUARD.$bytes);$written=true;
  query('INSERT INTO '.table('studio_files').' (id,job_id,author_type,original_name,stored_name,mime,bytes,sha256,created_at) VALUES (?,?,?,?,?,?,?,?,?)',[$id,$jobId,$actor,$name,basename($path),$mime,$size,hash('sha256',$bytes),gmdate('Y-m-d H:i:s')]);if(support_current())support_record('file.upload','file',$id);db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($written)@unlink($path);throw $e;}
}

function studio_download(string $id,?int $clientId): never {
 if($ctx=support_current()){
  $fresh=support_resolve($ctx['session']['id']);if(!$fresh||!staff_can('support.download',$fresh['actor'])||$clientId!==(int)$fresh['client']['id']){support_record('file.download','file',preg_match('/^[a-f0-9]{32}$/D',$id)?$id:'','denied');fail_page('Support download is not permitted.',403);}
  $GLOBALS['cwp_support']=$fresh;
 }
 if(!preg_match('/^[a-f0-9]{32}$/D',$id))fail_page('File not found.',404);
 $p=[$id];$sql='SELECT f.* FROM '.table('studio_files').' f JOIN '.table('studio_jobs').' j ON j.id=f.job_id WHERE f.id=?';if($clientId!==null){$c=studio_client();if(!$c||(int)$c['id']!==$clientId)fail_page('Sign in to view this file.',403);$sql.=' AND j.client_id=?';$p[]=$clientId;}else require_admin();
 $f=one($sql,$p);if(!$f){if(support_current())support_record('file.download','file',$id,'denied');fail_page('File not found.',404);}if(!preg_match('/^studio-[a-f0-9]{32}\.php$/D',$f['stored_name']))fail_page('Invalid file record.',500);
 $path=config()['storage'].'/media/'.$f['stored_name'];if(is_link($path)||!is_file($path))fail_page('File unavailable.',404);$fh=fopen($path,'rb');if(!$fh)fail_page('File unavailable.',404);if(fread($fh,strlen(CWP_GUARD))!==CWP_GUARD){fclose($fh);fail_page('File failed its safety check.',500);}$ctx=hash_init('sha256');$count=hash_update_stream($ctx,$fh);if($count!==(int)$f['bytes']||!hash_equals($f['sha256'],hash_final($ctx))){fclose($fh);fail_page('File integrity check failed.',500);}fseek($fh,strlen(CWP_GUARD));if(support_current())support_record('file.download','file',$id);header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],'',$f['original_name']).'"');header('Content-Length: '.$f['bytes']);header('Cache-Control: no-store, private');fpassthru($fh);fclose($fh);exit;
}

function studio_preview_upload(int $jobId,int $versionId): void {
 require_admin();$job=studio_job($jobId);
 $version=one('SELECT id FROM '.table('studio_versions').' WHERE id=? AND job_id=?',[$versionId,$jobId]);if(!$version)throw new InvalidArgumentException('Choose a version that belongs to this project.');
 $f=$_FILES['document']??null;
 if(!is_array($f)||!is_int($f['error']??null)||$f['error']!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name']))throw new InvalidArgumentException('Choose a PDF, PNG or JPEG preview.');
 $size=filesize($f['tmp_name']);if($size===false||$size<1||$size>8*1024*1024)throw new InvalidArgumentException('Files must be between 1 byte and 8 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);if(!in_array($mime,['application/pdf','image/png','image/jpeg'],true))throw new InvalidArgumentException('Only PDF, PNG and JPEG files are accepted.');
 $bytes=file_get_contents($f['tmp_name']);if($bytes===false||strlen($bytes)!==$size)throw new RuntimeException('File could not be read completely.');
 $extension=strtolower(pathinfo(is_string($f['name']??null)?$f['name']:'',PATHINFO_EXTENSION));
 if(!in_array($extension,['pdf','png','jpg','jpeg'],true)||preg_match('/<\?(?:php|=)/i',$bytes))throw new InvalidArgumentException('Only PDF, PNG and JPEG files without executable code are accepted.');
 $name=substr(preg_replace('/[^a-zA-Z0-9 ._()-]/','_',basename(is_string($f['name']??null)?$f['name']:'preview')),0,180)?:'preview';
 studio_preview_store($jobId,$versionId,$name,$bytes,$mime,$size);
}

/** Persist an authorized preview after locking the same project row ordinary uploads use, so preview/preview and preview/upload quota checks cannot interleave. */
function studio_preview_store(int $jobId,int $versionId,string $name,string $bytes,string $mime,int $size): string {
 $id=bin2hex(random_bytes(16));$path=config()['storage'].'/media/studio-'.$id.'.php';
 db()->beginTransaction();$written=false;try {
  if(!one('SELECT id FROM '.table('studio_jobs').' WHERE id=? FOR UPDATE',[$jobId]))throw new InvalidArgumentException('Project not found.');
  if(!one('SELECT id FROM '.table('studio_versions').' WHERE id=? AND job_id=? FOR UPDATE',[$versionId,$jobId]))throw new InvalidArgumentException('Choose a version that belongs to this project.');
  $sum=(int)query('SELECT COALESCE(SUM(bytes),0) FROM '.table('studio_files').' WHERE job_id=?',[$jobId])->fetchColumn();if($sum+$size>100*1024*1024)throw new RuntimeException('This project has reached its 100 MB file limit.');
  write_private_file($path,CWP_GUARD.$bytes);$written=true;
  query('INSERT INTO '.table('studio_files').' (id,job_id,author_type,original_name,stored_name,mime,bytes,sha256,created_at) VALUES (?,?,?,?,?,?,?,?,?)',[$id,$jobId,'admin',$name,basename($path),$mime,$size,hash('sha256',$bytes),gmdate('Y-m-d H:i:s')]);
  query('INSERT INTO '.table('studio_version_files').' (version_id,file_id,created_at) VALUES (?,?,?)',[$versionId,$id,gmdate('Y-m-d H:i:s')]);db()->commit();return $id;
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($written)@unlink($path);throw $e;}
}

function studio_preview_download(int $versionId,?int $clientId): never {
 if($ctx=support_current()){
  $fresh=support_resolve($ctx['session']['id']);if(!$fresh||!staff_can('support.download',$fresh['actor'])||$clientId!==(int)$fresh['client']['id']){support_record('file.preview','version',(string)$versionId,'denied');fail_page('Support preview is not permitted.',403);}
  $GLOBALS['cwp_support']=$fresh;
 }
 $p=[$versionId];$sql='SELECT f.* FROM '.table('studio_version_files').' b JOIN '.table('studio_files').' f ON f.id=b.file_id JOIN '.table('studio_versions').' v ON v.id=b.version_id JOIN '.table('studio_jobs').' j ON j.id=v.job_id WHERE b.version_id=?';if($clientId!==null){$c=studio_client();if(!$c||(int)$c['id']!==$clientId)fail_page('Sign in to view this preview.',403);$sql.=' AND j.client_id=?';$p[]=$clientId;}else require_admin();
 $f=one($sql,$p);if(!$f){if(support_current())support_record('file.preview','version',(string)$versionId,'denied');fail_page('Preview not found.',404);}if(!preg_match('/^studio-[a-f0-9]{32}\.php$/D',$f['stored_name']))fail_page('Invalid file record.',500);if(!in_array($f['mime'],['application/pdf','image/png','image/jpeg'],true))fail_page('Invalid preview record.',500);
 $path=config()['storage'].'/media/'.$f['stored_name'];if(is_link($path)||!is_file($path))fail_page('Preview unavailable.',404);$fh=fopen($path,'rb');if(!$fh)fail_page('Preview unavailable.',404);if(fread($fh,strlen(CWP_GUARD))!==CWP_GUARD){fclose($fh);fail_page('Preview failed its safety check.',500);}$ctx=hash_init('sha256');$count=hash_update_stream($ctx,$fh);if($count!==(int)$f['bytes']||!hash_equals($f['sha256'],hash_final($ctx))){fclose($fh);fail_page('Preview integrity check failed.',500);}fseek($fh,strlen(CWP_GUARD));if(support_current())support_record('file.preview','version',(string)$versionId);header('Content-Type: '.$f['mime']);header('Content-Disposition: inline; filename="'.str_replace(['"',"\r","\n"],'',$f['original_name']).'"');header('Content-Length: '.$f['bytes']);header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');fpassthru($fh);fclose($fh);exit;
}

/** Verify existing table types, transaction engine and required unique keys before adoption. */
