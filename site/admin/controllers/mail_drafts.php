<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/drafts.php';
require_once CWP_CODE_ROOT.'/app/mail/replies.php';
require_once CWP_CODE_ROOT.'/app/mail/files.php';
require_once CWP_CODE_ROOT.'/app/mail/document.php';

use CajunWebPro\Foundation\MailCompose\DraftAttachment;
use CajunWebPro\Foundation\MailCompose\DraftContent;

/** Raw bounded fields: do not trim controls out of addresses or subjects. */
function mail_drafts_form_text(array $input,string $key,int $limit):string {
    $value=array_key_exists($key,$input)?$input[$key]:'';
    if(!is_string($value)||strlen($value)>$limit)throw new InvalidArgumentException('Check the length and format of '.$key.'.');
    return $value;
}
function mail_drafts_form_content(array $input):DraftContent {
    $lists=[];
    foreach(['to','cc','bcc'] as $key) {
        $value=mail_drafts_form_text($input,$key,5600);
        $lists[]=trim($value,' ')===''?[]:explode(',',$value);
    }
    $html=mail_drafts_form_text($input,'body_html',65536);
    return new DraftContent(...[...$lists,mail_drafts_form_text($input,'subject',200),mail_drafts_form_text($input,'body',40000),mail_drafts_html($html)]);
}
/** Display only: preserve entered Unicode after byte-limit errors; never use this for persistence. */
function mail_drafts_form_values(array $input):array {
    $values=[];
    // HTML maxlength measures characters, so allow four UTF-8 bytes per displayed character.
    foreach(['to'=>22400,'cc'=>22400,'bcc'=>22400,'subject'=>800,'body'=>160000,'body_html'=>262144] as $key=>$limit) {
        $value=$input[$key]??'';$values[$key]=is_string($value)?substr($value,0,$limit):'';
    }
    return $values;
}
function mail_drafts_form_revision(array $input,string $key):int {
    $value=mail_drafts_form_text($input,$key,10);
    if(preg_match('/^[0-9]+$/D',$value)!==1||(int)$value>=2147483647)throw new InvalidArgumentException('Reload the current draft form.');
    return (int)$value;
}
function mail_drafts_form_reference(string $value):array {
    if(!preg_match('/^([a-f0-9]{32}):([1-9][0-9]{0,9})$/D',$value,$parts)||(int)$parts[2]>=2147483647)throw new InvalidArgumentException('Choose a current saved account or template.');
    return [$parts[1],(int)$parts[2]];
}
/** Used only to decide whether to show navigation/setup. Data still needs exact mailbox authority. */
function mail_drafts_admin_access(array $actor):bool {
    $permissions=json_decode($actor['permissions']??'[]',true);
    return !support_current()&&(($actor['role']??'')==='owner'||(is_array($permissions)&&in_array('mail.send',$permissions,true)));
}
/** One bounded uploaded file; detection and bounds run before the locked attach. */
function mail_drafts_form_upload():DraftAttachment {
    $f=$_FILES['file']??null;
    if(!is_array($f)||!is_int($f['error']??null)||$f['error']!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name']))throw new InvalidArgumentException('Choose a file to attach.');
    $size=filesize($f['tmp_name']);
    if($size===false||$size<1||$size>DraftAttachment::MAX_BYTES)throw new InvalidArgumentException('Each attachment must be between 1 byte and 256 KiB.');
    $bytes=file_get_contents($f['tmp_name']);
    if($bytes===false||strlen($bytes)!==$size)throw new RuntimeException('The upload could not be read completely.');
    $mime='application/octet-stream';
    if(function_exists('finfo_open')) {$type=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);if(is_string($type)&&$type!=='')$mime=$type;}
    return new DraftAttachment(is_string($f['name']??null)?$f['name']:'attachment',$mime,$bytes);
}
function mail_drafts_admin_action(string $action):?DraftContent {
    if($action==='mail_drafts_files_setup') {
        mail_files_migrate(posted('current_password',200));flash('Draft attachments are ready.');redirect(admin_url('mail-drafts'));
    }
    if($action==='mail_drafts_setup') {
        mail_drafts_migrate(posted('current_password',200));flash('Local email drafts are ready.');redirect(admin_url('mail-drafts'));
    }
    if($action==='mail_drafts_replies_setup') {
        mail_replies_migrate(posted('current_password',200));flash('Protected Reply and Forward drafts are ready.');redirect(admin_url('mail-drafts'));
    }
    return staff_locked(function()use($action):?DraftContent {
        $actor=mail_drafts_actor();$id=posted('draft_id',32);mail_drafts_id($id);
        $revision=mail_drafts_form_revision($_POST,'revision');
        $copied=mail_replies_ready()&&one('SELECT id FROM '.table('mail_reply_drafts').' WHERE id=?',[$id]);
        if($action==='mail_drafts_attach') {
            mail_drafts_attach($id,$revision,mail_drafts_form_upload());
            flash('File attached to the saved draft. No email was sent.');redirect(admin_url('mail-draft',['draft_id'=>$id]));
        }
        if($action==='mail_drafts_detach') {
            mail_drafts_detach($id,$revision,posted('file_id',32));
            flash('Attachment removed from the saved draft.');redirect(admin_url('mail-draft',['draft_id'=>$id]));
        }
        if($action==='mail_drafts_file') {
            $stored=mail_drafts_file($id,posted('file_id',32));
            header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.$stored['name'].'"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Length: '.strlen($stored['data']));
            if(session_status()===PHP_SESSION_ACTIVE)session_write_close();echo $stored['data'];exit;
        }
        if($action==='mail_drafts_discard') {
            $old=$copied?mail_reply_drafts_get($id):mail_drafts_get($id);
            if($copied)mail_reply_drafts_discard($id,$revision);else mail_drafts_discard($id,$revision);
            flash('Draft discarded locally.');redirect(admin_url('mail-drafts',['account_id'=>$old['account_id'],'status'=>'discarded']));
        }
        [$account,$accountRevision]=mail_drafts_form_reference(posted('sender',43));
        mail_drafts_account($account,$accountRevision);
        // Preview/filter/insertion may not disclose or reuse a different author's saved draft.
        $old=one('SELECT id FROM '.table('mail_drafts').' WHERE id=?',[$id]);
        if($old) {
            $old=$copied?mail_reply_drafts_get($id):mail_drafts_get($id);
            if($old['status']!=='draft'||(int)$old['revision']!==$revision) {
                // Only save owns the exact initial-create replay rule.
                if(!($action==='mail_drafts_save'&&$revision===0&&!$copied))throw new RuntimeException('The draft changed. Reload before continuing.');
            }
        }elseif($revision!==0||$copied)throw new RuntimeException('The draft changed. Reload before continuing.');
        if(in_array($action,['mail_drafts_accounts','mail_drafts_templates'],true))return null;
        $content=mail_drafts_form_content($_POST);
        if($action==='mail_drafts_preview')return $content;
        if($action==='mail_drafts_insert') {
            if(($content->subject!==''||$content->body!=='')&&posted('replace_text',1)!=='1')throw new InvalidArgumentException('Confirm replacing the subject and message before inserting a template.');
            [$template,$templateRevision]=mail_drafts_form_reference(posted('template',43));
            $samples=[];foreach(['client_name','project_name','studio_name'] as $key)$samples[$key]=mail_drafts_form_text($_POST,'sample_'.$key,200);
            return mail_drafts_template($account,$accountRevision,$template,$templateRevision,$samples,$content->to,$content->cc,$content->bcc);
        }
        if($action!=='mail_drafts_save')throw new InvalidArgumentException('Unknown draft action.');
        if($copied)mail_reply_drafts_save($id,$revision,$account,$accountRevision,$content,posted('confirm_sender',1)==='1');
        else mail_drafts_save($id,$revision,$account,$accountRevision,$content,posted('confirm_sender',1)==='1');
        flash('Draft saved locally. No email was sent.');redirect(admin_url('mail-draft',['draft_id'=>$id]));
    });
}
/** Query and project all private display data under the same current-authority lock. */
function mail_drafts_admin_data(string $view,?DraftContent $preview):array {
    return staff_locked(function()use($view,$preview):array {
        $actor=mail_drafts_actor();$post=$_SERVER['REQUEST_METHOD']==='POST';
        $id=is_string($_GET['draft_id']??null)?$_GET['draft_id']:'';
        $row=$id!==''?mail_drafts_get($id):null;
        if($row&&$row['status']==='source') {
            // Copied-message drafts hold their real content and origin only in
            // prepared protected storage; the placeholder is never editable.
            if(!mail_replies_ready())throw new RuntimeException('This copied-message draft requires prepared Reply/Forward storage.');
            $row=mail_reply_drafts_get($id)+['copied'=>true];
        }
        $account=is_string($_GET['account_id']??null)?$_GET['account_id']:'';
        if($row)$account=$row['account_id'];
        $accountPage=max(1,min((int)($post?($_POST['account_page']??1):($_GET['account_page']??1)),100000));
        $assigned=mail_accounts_assigned($accountPage);$moreAccounts=count($assigned)>20;$accounts=[];
        foreach(array_slice($assigned,0,20) as $assignedRow)if($assignedRow['can_send'])$accounts[$assignedRow['id']]=mail_drafts_account($assignedRow['id']);
        if($account!=='')$accounts[$account]=mail_drafts_account($account);
        elseif($accounts)$account=array_key_first($accounts);
        $page=max(1,min((int)($_GET['p']??1),100000));$status=is_string($_GET['status']??null)?$_GET['status']:'draft';
        if($view==='mail-drafts') {
            // One stable ordering applies the page boundary across both draft kinds;
            // per-kind offsets would drop records between merged pages.
            $rows=$account===''?[]:(mail_replies_ready()?mail_drafts_combined_list($account,$status,$page):mail_drafts_list($account,$status,$page));
            return compact('actor','accounts','account','accountPage','moreAccounts','page','status')+['rows'=>$rows];
        }
        $values=['to'=>'','cc'=>'','bcc'=>'','subject'=>'','body'=>'','body_html'=>''];$revision=0;
        if($row) {
            foreach(['to','cc','bcc'] as $key)$values[$key]=implode(', ',$row[$key]);
            foreach(['subject','body'] as $key)$values[$key]=$row[$key];
            $values['body_html']=$row['html']??($row['body_html']??'');
            $revision=(int)$row['revision'];
        }
        $sender=$account!==''?$account.':'.($row['account_revision']??$accounts[$account]['revision']):'';
        $editable=(!$row||$row['status']==='draft')&&mail_drafts_html_ready();$unsaved=null;
        // A concurrent discard must not erase text submitted from an earlier edit tab.
        // Keep the retained saved record separate, under the same current author/account gate.
        if($post&&$row&&!$editable&&in_array($_POST['action']??null,['mail_drafts_save','mail_drafts_preview','mail_drafts_insert','mail_drafts_accounts','mail_drafts_templates'],true)) {
            $postedId=mail_drafts_form_text($_POST,'draft_id',32);mail_drafts_id($postedId);
            if(!hash_equals($id,$postedId))throw new InvalidArgumentException('Draft unavailable.');
            $revision=mail_drafts_form_revision($_POST,'revision');$unsaved=mail_drafts_form_values($_POST);
        }
        if($post&&$editable&&str_starts_with(is_string($_POST['action']??null)?$_POST['action']:'','mail_drafts_')) {
            $postedId=mail_drafts_form_text($_POST,'draft_id',32);mail_drafts_id($postedId);
            if($id!==''&&!hash_equals($id,$postedId))throw new InvalidArgumentException('Draft unavailable.');
            if($id===''&&one('SELECT id FROM '.table('mail_drafts').' WHERE id=?',[$postedId]))mail_drafts_get($postedId);
            $id=$postedId;
            // Keep the submitted revision; a stale form must never become an implicit overwrite.
            $revision=mail_drafts_form_revision($_POST,'revision');
            $sender=mail_drafts_form_text($_POST,'sender',43);
            [$chosen,$chosenRevision]=mail_drafts_form_reference($sender);$accounts[$chosen]=mail_drafts_account($chosen);
            $values=mail_drafts_form_values($_POST);
        }
        if($id==='')$id=bin2hex(random_bytes(16));
        if($preview&&($_POST['action']??'')==='mail_drafts_insert') {
            // The template supplies plain content only; replacing the message also clears the formatted alternative.
            $values['subject']=$preview->subject;$values['body']=$preview->body;$values['body_html']='';
        }
        $templates=[];$templatePage=max(1,min((int)($_POST['template_page']??1),100000));$templateSearch=mail_drafts_form_text($_POST,'template_search',120);
        if(staff_can('mail.templates.manage',$actor)&&mail_templates_ready())$templates=mail_templates_list('active',$templateSearch,$templatePage);
        $historyPage=max(1,min((int)($_GET['history_page']??1),100000));$history=$row?($row['copied']??false?mail_reply_drafts_history($id,$historyPage):mail_drafts_history($id,$historyPage)):[];
        $files=$row&&$row['status']==='draft'&&mail_files_ready()?mail_drafts_files($id):[];
        return compact('actor','row','accounts','account','accountPage','moreAccounts','id','values','revision','sender','editable','preview','templates','templatePage','templateSearch','historyPage','history','unsaved','files');
    });
}
function mail_drafts_admin_render(string $view,string $error,?DraftContent $preview):void {
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    try {
        $actor=mail_accounts_actor(false);
        if(!mail_drafts_admin_access($actor))throw new RuntimeException('Current email Send permission is required.');
    }catch(InvalidArgumentException|RuntimeException $e) {
        if($e instanceof PDOException)throw $e;http_response_code(403);admin_head('Draft access unavailable','mailboxes',require_admin());
        echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';admin_foot();exit;
    }
    admin_head($view==='mail-drafts'?'My email drafts':'Email draft','mailboxes',$actor);
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    if(!mail_accounts_ready())echo '<p class="notice">An owner must first prepare <a href="'.esc(admin_url('mail-accounts')).'">email accounts</a>.</p>';
    elseif(!mail_drafts_ready()) {
        echo '<section class="workspace-card"><h2>Prepare local drafts</h2><p>Write and save private drafts for your assigned sending accounts. Saving and previewing do not send mail.</p>';
        if($actor['role']==='owner') {
            echo '<form method="post">';admin_action('mail_drafts_setup');admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Prepare local drafts</button></form>';
        }else echo '<p>An owner must prepare drafts before you can use them.</p>';
        echo '</section>';
    }else {
        if(!mail_drafts_html_ready()) {
            echo '<section class="workspace-card"><h2>Prepare formatted drafts</h2><p>Writing drafts and the optional formatted alternative need current draft storage columns. An owner prepares this once; existing drafts and submission history are preserved.</p>';
            if($actor['role']==='owner') {echo '<form method="post">';admin_action('mail_drafts_setup');admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Prepare formatted drafts</button></form>';}
            else echo '<p>An owner must prepare formatted drafts before editing.</p>';
            echo '</section>';
        }
        if(!mail_replies_ready()) {
            echo '<section class="workspace-card"><h2>Prepare protected Reply and Forward drafts</h2><p>Replying to or forwarding an inbox message copies it into a protected private draft. An owner prepares this storage once; existing drafts and submission history are preserved.</p>';
            if($actor['role']==='owner') {echo '<form method="post">';admin_action('mail_drafts_replies_setup');admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Prepare Reply and Forward drafts</button></form>';}
            else echo '<p>An owner must prepare protected drafts before Reply and Forward appear on inbox messages.</p>';
            echo '</section>';
        }
        if(!mail_files_ready()) {
            echo '<section class="workspace-card"><h2>Prepare draft attachments</h2><p>Saved drafts can carry bounded private files that are copied into the reviewed message at send time. An owner prepares this storage once; existing drafts and submission history are preserved.</p>';
            if($actor['role']==='owner') {echo '<form method="post">';admin_action('mail_drafts_files_setup');admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Prepare attachments</button></form>';}
            echo '</section>';
        }
        try {$data=mail_drafts_admin_data($view,$preview);require CWP_CODE_ROOT.'/admin/views/mail_drafts.php';}
        catch(InvalidArgumentException|RuntimeException $e) {
            if($e instanceof PDOException)throw $e;echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';
        }
    }
    admin_foot();exit;
}
