<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function studio_job(int $id,?int $clientId=null): array {
 $p=[$id];$sql='SELECT * FROM '.table('studio_jobs').' WHERE id=?';if($clientId!==null){$sql.=' AND client_id=?';$p[]=$clientId;}$j=one($sql,$p);if(!$j)throw new InvalidArgumentException('Project not found or access denied.');return $j;
}

function studio_decide(int $clientId,int $versionId,string $choice,string $feedback): void {
 if(support_current())throw new RuntimeException('Client decisions are blocked during support.');
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
