<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require_once __DIR__.'/../site/admin/controllers/mail_submissions.php';
require_once __DIR__.'/../site/includes/layout/admin.php';
$GLOBALS['cwp_config']=['site_url'=>'https://example.invalid/studio'];$_SESSION=['csrf'=>str_repeat('c',64)];
$checks=[];
function sf_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function sf_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException){sf_ok($name,true);return;}sf_ok($name,false);}
function sf_render(string $view,array $data):string {ob_start();require __DIR__.'/../site/admin/views/mail_submissions.php';return ob_get_clean();}
$id=str_repeat('a',32);$draft=str_repeat('b',32);$account=str_repeat('d',32);
$input=['submission_id'=>$id,'draft_id'=>$draft,'draft_revision'=>'2','account_revision'=>'3','confirm_send'=>'1'];
sf_ok('Reviewed form returns exact identities and positive saved versions',mail_submissions_form($input)===['id'=>$id,'draft'=>$draft,'revision'=>2,'accountRevision'=>3]);
foreach(['submission_id','draft_id'] as $field)foreach(['',str_repeat('a',31),str_repeat('a',33),str_repeat('A',32),$id."\n",[],null] as $i=>$bad)sf_deny('Raw opaque identity refuses '.$field.' '.$i,fn()=>mail_submissions_form(array_replace($input,[$field=>$bad])));
foreach(['draft_revision','account_revision'] as $field)foreach(['0','-1','1.5','2147483647',"2\n",[],null] as $i=>$bad)sf_deny('Saved version refuses '.$field.' '.$i,fn()=>mail_submissions_form(array_replace($input,[$field=>$bad])));
foreach([null,'0',1,true,[],"1\n"] as $i=>$bad)sf_deny('Explicit confirmation requires exact checkbox value '.$i,fn()=>mail_submissions_form(array_replace($input,['confirm_send'=>$bad])));
sf_ok('Error redisplay preserves exact intent without granting confirmation',mail_submissions_form(array_replace($input,['confirm_send'=>'0']),false)['id']===$id);
sf_ok('Caller content endpoint and driver fields cannot enter send arguments',mail_submissions_form($input+['body'=>'INJECTED','smtp_host'=>'127.0.0.1','transport'=>'arbitrary'])===mail_submissions_form($input));
$_SERVER['REQUEST_METHOD']='GET';sf_deny('GET cannot invoke submission action',fn()=>mail_submissions_admin_action('mail_submissions_send'));
$_SERVER['REQUEST_METHOD']='POST';$_POST=$input;sf_deny('Missing CSRF fails before any authority database work',fn()=>mail_submissions_admin_action('mail_submissions_send'));
$_POST['csrf']=$_SESSION['csrf'];sf_deny('Unknown action cannot fall through to sending',fn()=>mail_submissions_admin_action('mail_submissions_other'));
foreach(['accepted'=>'Server accepted','rejected'=>'Server rejected','unknown'=>'Confirmation missing','not_submitted'=>'Not submitted','bad'=>'Confirmation missing'] as $status=>$label)sf_ok('Finite status label '.$status,mail_submissions_status(['status'=>$status,'reason'=>'RAW_SECRET_SERVER_TEXT'],null)['title']===$label&&!str_contains(json_encode(mail_submissions_status(['status'=>$status,'reason'=>'RAW_SECRET_SERVER_TEXT'],null)),'RAW_SECRET_SERVER_TEXT'));
sf_ok('Interrupted preparation has its own state',mail_submissions_status(null,null)['title']==='Preparation incomplete');
sf_ok('Dispatched interruption warns against resend',str_contains(mail_submissions_status(null,['started_at'=>'now'])['text'],'Do not resend'));
sf_ok('Acceptance makes no recipient delivery promise',str_contains(mail_submissions_status(['status'=>'accepted'],null)['text'],'does not confirm recipient delivery'));
$content=['from'=>'sender@example.invalid','to'=>['client@example.invalid'],'cc'=>[],'bcc'=>['private@example.invalid'],'subject'=>'</h3><script>SYNTHETIC_SUBJECT</script>','body'=>"</textarea><script>SYNTHETIC_BODY</script>\nLiteral next line"];
$saved=['id'=>$draft,'revision'=>2,'account_revision'=>3]+$content;
$data=['existing'=>null,'submission_id'=>$id,'draft'=>$saved,'account'=>['id'=>$account,'revision'=>3,'address'=>$content['from']]];
$html=sf_render('mail-send',$data);
sf_ok('Review escapes hostile saved subject and body',!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;SYNTHETIC_BODY&lt;/script&gt;'));
sf_ok('Review separates Bcc recipients explicitly',str_contains($html,'aria-label="Blind copy recipients"')&&str_contains($html,'private@example.invalid'));
sf_ok('Confirmation is required and never preselected',str_contains($html,'name="confirm_send" value="1" required')&&!str_contains($html,' checked'));
sf_ok('Send form carries saved version and stable submission identity',str_contains($html,'name="submission_id" value="'.$id.'"')&&str_contains($html,'name="draft_revision" value="2"')&&str_contains($html,'name="account_revision" value="3"'));
sf_ok('Send form carries CSRF and explicit POST',str_contains($html,'method="post"')&&str_contains($html,'name="csrf"'));
sf_ok('Message content is display only and cannot become mutable hidden form data',!preg_match('/name="(body|to|cc|bcc|subject|smtp_host|credentials)"/',$html));
$row=['id'=>$id,'draft_id'=>$draft,'draft_revision'=>2,'created_at'=>'2026-10-14 00:00:00','content'=>$content,'dispatch'=>['started_at'=>'2026-10-14 00:00:00'],'result'=>['status'=>'unknown','reason'=>'timeout','finished_at'=>'2026-10-14 00:00:01']];
$html=sf_render('mail-submission',['row'=>$row,'status'=>mail_submissions_status($row['result'],$row['dispatch'])]);
sf_ok('Recorded message is escaped and read only',!str_contains($html,'<script>')&&str_contains($html,'SYNTHETIC_BODY')&&!str_contains($html,'mail_submissions_send'));
sf_ok('Uncertain detail warns against resend',str_contains($html,'Do not resend'));
$html=sf_render('mail-send',['existing'=>$row]);sf_ok('Existing intent review offers history instead of another send',str_contains($html,'View the existing submission')&&!str_contains($html,'mail_submissions_send'));
$html=sf_render('mail-submissions',['accounts'=>[$account=>['label'=>'Selected','address'=>'sender@example.invalid']],'account'=>$account,'accountPage'=>1,'moreAccounts'=>false,'page'=>1,'rows'=>[['id'=>$id,'subject'=>$content['subject'],'draft_revision'=>2,'created_at'=>$row['created_at'],'status'=>mail_submissions_status($row['result'],$row['dispatch'])]]]);
sf_ok('History renders escaped subject without body or recipients',!str_contains($html,'<script>')&&str_contains($html,'SYNTHETIC_SUBJECT')&&!str_contains($html,'SYNTHETIC_BODY')&&!str_contains($html,'private@example.invalid'));
@mkdir(__DIR__.'/../qa',0700,true);file_put_contents(__DIR__.'/../qa/mail-submission-forms-results.json',json_encode(['passed'=>true,'php'=>PHP_VERSION,'ini_loaded'=>php_ini_loaded_file()!==false,'scope'=>'Unwired form input and escaped template output only; no HTTP/browser/database/SMTP acceptance','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");echo 'PASS: '.count($checks)." submission form/template checks\n";
