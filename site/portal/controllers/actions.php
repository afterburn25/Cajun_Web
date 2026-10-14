<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{if($supportRequested)support_check_csrf($supportId);else check_csrf();if(!studio_ready())throw new RuntimeException('The client workspace has not been enabled. Please contact the studio.');$action=posted('action',40);
 if(support_current()){
  if(!in_array($action,['message','upload'],true)){support_record('action.blocked','portal','','denied');throw new RuntimeException('This action is blocked during support. Client decisions, payments and security changes require the proper independent workflow.');}
  if(!consume_rate('support-action-'.support_current()['actor']['id'],30,3600))throw new RuntimeException('Please wait before making more support changes.');
  if($action==='message')support_message($supportId,$jobId,posted('body',5000));else support_upload($supportId,$jobId);
  flash('Saved as Studio support.');redirect(studio_url(['job'=>$jobId]));
 }
 elseif($action==='activity_mark')activity_post('client');
 elseif($action==='activate'){$password=posted('password',72);if($password!==posted('confirm_password',72))throw new InvalidArgumentException('Passwords do not match.');if(!consume_rate(ip_key('client-activate'),10,900))throw new RuntimeException('Please wait before trying again.');studio_activate(is_string($_POST['invite']??null)?$_POST['invite']:'',$password);flash('Your password is saved. Sign in below.');redirect(studio_url());}
 elseif($action==='login'){studio_login(strtolower(posted('email',190)),posted('password',1000));redirect(studio_url());}
 elseif($action==='logout'){unset($_SESSION['client_id'],$_SESSION['client_auth'],$_SESSION['client_last'],$_SESSION['client_login']);session_regenerate_id(true);redirect(studio_url());}
 else{if(!$client)throw new RuntimeException('Sign in to continue.');$job=studio_job($jobId,(int)$client['id']);
 if($action==='message'){$body=posted('body',5000);if(strlen($body)<2)throw new InvalidArgumentException('Write a message.');if(!consume_rate(ip_key('client-message'),30,3600))throw new RuntimeException('Please wait before sending more messages.');query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,?,?,?,?)',[$jobId,'client',$client['id'],$body,gmdate('Y-m-d H:i:s')]);flash('Message saved.');}
 elseif($action==='decision'){if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm the decision for this version.');$version=(int)posted('version',12);if(!one('SELECT id FROM '.table('studio_versions').' WHERE id=? AND job_id=?',[$version,$jobId]))throw new InvalidArgumentException('Review not found.');studio_decide((int)$client['id'],$version,posted('choice',20),posted('feedback',5000));flash('Your decision was recorded against that version.');}
 elseif($action==='upload'){if(!consume_rate(ip_key('client-upload'),10,3600))throw new RuntimeException('Upload limit reached.');studio_upload($jobId,'client');flash('File saved privately.');}
 else throw new InvalidArgumentException('Unknown action.');redirect(studio_url(['job'=>$jobId]));}
 }catch(PDOException $e){$error='Request could not be recorded. Reference: '.report_error($e);}catch(InvalidArgumentException|RuntimeException $e){$error=$e->getMessage();}catch(Throwable $e){$error='Request failed. Reference: '.report_error($e);}
}
