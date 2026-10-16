<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/admin/controllers/mail_drafts.php';
$checks=[];
function df_check(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function df_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException){df_check($name,true);return;}df_check($name,false);}
df_check('Missing fields form an unfinished empty draft',mail_drafts_form_content([])->to===[]);
$content=mail_drafts_form_content(['to'=>" Writer+web@EXAMPLE.ORG , other@example.org",'cc'=>'copy@example.org','bcc'=>'private@example.org','subject'=>'  Subject  ','body'=>"  Indented\r\nbody  "]);
df_check('Comma input produces separate normalized recipient groups',$content->to===['Writer+web@example.org','other@example.org']&&$content->cc===['copy@example.org']&&$content->bcc===['private@example.org']);
df_check('Form parsing preserves subject and body whitespace',$content->subject==='  Subject  '&&$content->body==="  Indented\nbody  ");
foreach(['to','cc','bcc','subject'] as $key) {
    df_deny('Leading LF cannot be trimmed into valid '.$key,fn()=>mail_drafts_form_content([$key=>"\n".($key==='subject'?'Subject':'writer@example.org')]));
    df_deny('Trailing CRLF cannot be trimmed into valid '.$key,fn()=>mail_drafts_form_content([$key=>($key==='subject'?'Subject':'writer@example.org')."\r\n"]));
}
foreach([[],['injected'],true,42,null] as $i=>$bad)df_deny('Form rejects non-string scalar '.$i,fn()=>mail_drafts_form_content(['subject'=>$bad]));
foreach(['writer@example.org,',',writer@example.org','writer@example.org,,other@example.org','writer@example.org;other@example.org'] as $i=>$bad)df_deny('Ambiguous or empty recipient segment '.$i,fn()=>mail_drafts_form_content(['to'=>$bad]));
df_deny('Form cannot duplicate a visible recipient in Bcc',fn()=>mail_drafts_form_content(['to'=>'writer@EXAMPLE.ORG','bcc'=>'writer@example.org']));
df_check('Body raw budget allows CRLF normalization to canonical limit',strlen(mail_drafts_form_content(['body'=>str_repeat("\r\n",20000)])->body)===20000);
df_deny('Form rejects raw body over bounded budget',fn()=>mail_drafts_form_content(['body'=>str_repeat("\r\n",20000).'x']));
df_deny('Form still enforces canonical body bytes',fn()=>mail_drafts_form_content(['body'=>str_repeat('x',20001)]));
df_check('Space-only recipient fields remain unfinished',mail_drafts_form_content(['to'=>'  ','cc'=>' '])->to===[]);
$id=str_repeat('a',32);df_check('Sender reference carries exact account and saved revision',mail_drafts_form_reference($id.':12')===[$id,12]);
foreach([$id.':0',$id.':-1',$id.':2147483647',$id.":1\n",'bad:1'] as $i=>$bad)df_deny('Malformed or unbounded saved reference '.$i,fn()=>mail_drafts_form_reference($bad));
df_check('Draft create revision zero remains valid',mail_drafts_form_revision(['revision'=>'0'],'revision')===0);
foreach(['-1','2147483647','1.5',[],null] as $i=>$bad)df_deny('Malformed draft revision '.$i,fn()=>mail_drafts_form_revision(['revision'=>$bad],'revision'));
df_check('Closing textarea and script stay literal in draft form data',mail_drafts_form_content(['body'=>'</textarea><script>untrusted()</script>'])->body==='</textarea><script>untrusted()</script>');
df_check('Over-byte-limit Unicode subject is preserved for correction',mail_drafts_form_values(['subject'=>str_repeat('é',200)])['subject']===str_repeat('é',200));
df_check('Over-byte-limit Unicode body is preserved for correction',mail_drafts_form_values(['body'=>str_repeat('é',30000)])['body']===str_repeat('é',30000));
df_check('Error redisplay still bounds malicious oversized input',strlen(mail_drafts_form_values(['body'=>str_repeat('x',200000)])['body'])===160000);
df_check('Missing formatted field keeps a plain draft',mail_drafts_form_content(['body'=>'B'])->html===null);
if(extension_loaded('dom')) {
    df_check('Posted formatted markup is sanitized into the draft alternative',mail_drafts_form_content(['body'=>'B','body_html'=>'<p>Hi <b>x</b><script>bad()</script></p>'])->html==='<p>Hi <b>x</b></p>');
    df_check('Empty formatted input means plain text only',mail_drafts_form_content(['body'=>'B','body_html'=>'   '])->html===null);
    df_deny('Formatted input over the raw bound is refused',fn()=>mail_drafts_form_content(['body_html'=>str_repeat('x',65537)]));
    df_deny('Non-string formatted input is refused',fn()=>mail_drafts_form_content(['body_html'=>[]]));
    df_deny('Oversized formatted markup is refused',fn()=>mail_drafts_form_content(['body_html'=>'<p>'.str_repeat('x',40960).'</p>']));
    df_check('Form preserves formatted input for correction',strlen(mail_drafts_form_values(['body_html'=>str_repeat('x',300000)])['body_html'])===262144);
} else $checks[]=['name'=>'DOM unavailable; formatted form checks skipped','passed'=>true];
@mkdir(__DIR__.'/../qa',0700,true);file_put_contents(__DIR__.'/../qa/mail-drafts-form-results.json',json_encode(['passed'=>true,'php'=>PHP_VERSION,'ini_loaded'=>php_ini_loaded_file()!==false,'scope'=>'Pure form input boundary only; no database, HTTP rendering or mail transport acceptance','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo 'PASS: '.count($checks)." mail draft form checks\n";
