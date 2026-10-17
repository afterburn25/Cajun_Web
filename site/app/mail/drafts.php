<?php
/** Private local drafts. No credential decryption, network, MIME or delivery side effects. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\MailCompose\ComposeHtml;
use CajunWebPro\Foundation\MailCompose\DraftContent;
use CajunWebPro\Foundation\MailReceiving\ReceivingFailure;
require_once CWP_CODE_ROOT.'/app/mail/accounts.php';
require_once CWP_CODE_ROOT.'/app/mail/templates.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-compose/ComposeHtml.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-compose/DraftContent.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_drafts_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_drafts_migrate.php';

function mail_drafts_ready():bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_drafts_schema_version'])['value']??'')==='2';}
function mail_drafts_actor():array {
    $actor=mail_accounts_actor();
    // The canonical mailbox decision below still requires both global Send and an exact grant.
    $permissions=json_decode($actor['permissions']??'[]',true);
    if($actor['role']!=='owner' && (!is_array($permissions) || !in_array('mail.send',$permissions,true)))throw new RuntimeException('Current email Send permission is required.');
    if(!mail_drafts_ready())throw new RuntimeException('An owner must prepare local email drafts first.');
    return $actor;
}
function mail_drafts_account(string $id,?int $revision=null):array {
    if(!mail_accounts_can('mail.send',$id))throw new RuntimeException('Current Send access to this email account is required.');
    $row=mail_accounts_load($id);
    if($revision!==null && ($revision<1 || $revision!==(int)$row['revision']))throw new RuntimeException('The sending account changed. Reload and review the current sender.');
    return ['id'=>$row['id'],'revision'=>(int)$row['revision'],'label'=>$row['label'],'address'=>$row['address']];
}
function mail_drafts_id(string $id):void {if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Draft unavailable.');}
/** Bounded optional formatted alternative; '' input means plain text only. */
function mail_drafts_html(string $raw):?string {
    try {return ComposeHtml::sanitize($raw)?:null;}
    catch(ReceivingFailure $e){throw new InvalidArgumentException($e->getMessage()==='limit'?'The formatted message text is too long.':'The formatted message text is not valid HTML.');}
}
function mail_drafts_audit(int $actor,string $action,string $detail):void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'mail_drafts.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
/** Internal row lookup only; do not call from a route without the current actor/account gate. */
function mail_drafts_row(string $id,int $actor,bool $lock=false):array {
    mail_drafts_id($id);$row=one('SELECT * FROM '.table('mail_drafts').' WHERE id=? AND owner_id=?'.($lock?' FOR UPDATE':''),[$id,$actor]);
    if(!$row)throw new InvalidArgumentException('Draft unavailable.');return $row;
}
function mail_drafts_content(array $row):DraftContent {
    try {
        $lists=[];
        foreach(['to_json','cc_json','bcc_json'] as $key) {
            $value=json_decode($row[$key],false,8,JSON_THROW_ON_ERROR);
            if(!is_array($value))throw new InvalidArgumentException('Invalid recipient list.');$lists[]=$value;
        }
        return new DraftContent(...[...$lists,$row['subject'],$row['body'],$row['body_html']]);
    }catch(JsonException|InvalidArgumentException){throw new RuntimeException('Saved draft content is damaged. Existing data was preserved.');}
}
function mail_drafts_get(string $id):array {
    return staff_locked(function()use($id):array {
    $actor=mail_drafts_actor();$row=mail_drafts_row($id,(int)$actor['id']);$account=mail_drafts_account($row['account_id']);
    $content=mail_drafts_content($row);unset($row['create_fingerprint'],$row['to_json'],$row['cc_json'],$row['bcc_json']);
    return $row+['to'=>$content->to,'cc'=>$content->cc,'bcc'=>$content->bcc,'html'=>$content->html,'sender'=>$account,'sender_changed'=>(int)$row['account_revision']!==$account['revision']];
    });
}
function mail_drafts_list(string $account,string $status='draft',int $page=1):array {
    return staff_locked(function()use($account,$status,$page):array {
    $actor=mail_drafts_actor();mail_drafts_account($account);
    if(!in_array($status,['draft','discarded'],true))throw new InvalidArgumentException('Unknown draft filter.');
    $page=max(1,min($page,100000));
    // No recipients or body in list rows; a current-authorized detail read is separate.
    return all('SELECT id,revision,account_id,account_revision,status,subject,updated_at FROM '.table('mail_drafts').' WHERE owner_id=? AND account_id=? AND status=? ORDER BY updated_at DESC,id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$actor['id'],$account,$status]);
    });
}
function mail_drafts_history(string $id,int $page=1):array {
    return staff_locked(function()use($id,$page):array {
    $actor=mail_drafts_actor();$row=mail_drafts_row($id,(int)$actor['id']);mail_drafts_account($row['account_id']);$page=max(1,min($page,100000));
    return all('SELECT revision,account_id,account_revision,status,action,recorded_at FROM '.table('mail_draft_revisions').' WHERE draft_id=? ORDER BY revision DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id]);
    });
}
function mail_drafts_snapshot(array $row,int $actor,string $action):void {
    query('INSERT INTO '.table('mail_draft_revisions').' (id,draft_id,revision,account_id,account_revision,status,action,actor_id,recorded_at) VALUES (?,?,?,?,?,?,?,?,?)',[bin2hex(random_bytes(16)),$row['id'],$row['revision'],$row['account_id'],$row['account_revision'],$row['status'],$action,$actor,gmdate('Y-m-d H:i:s')]);
    mail_drafts_audit($actor,$action,'Draft '.$row['id'].' revision '.$row['revision']);
}
/** Stable random form id, exact create replay, optimistic edits and explicit sender review. */
function mail_drafts_save(string $id,int $revision,string $account,int $accountRevision,DraftContent $content,bool $confirmSenderChange=false):string {
    return staff_locked(fn()=>staff_transaction(function()use($id,$revision,$account,$accountRevision,$content,$confirmSenderChange):string {
        $actor=mail_drafts_actor();mail_drafts_id($id);mail_drafts_account($account,$accountRevision);
        if($revision<0 || $revision>=2147483647)throw new RuntimeException('The draft changed. Reload before saving.');
        $data=[json_encode($content->to,JSON_THROW_ON_ERROR),json_encode($content->cc,JSON_THROW_ON_ERROR),json_encode($content->bcc,JSON_THROW_ON_ERROR),$content->subject,$content->body,$content->html];
        $fingerprint=hash('sha256',json_encode([(int)$actor['id'],$account,$accountRevision,...$data],JSON_THROW_ON_ERROR));
        $old=one('SELECT * FROM '.table('mail_drafts').' WHERE id=? FOR UPDATE',[$id]);
        if($old && (int)$old['owner_id']!==(int)$actor['id'])throw new InvalidArgumentException('Draft unavailable.');
        if($old) {
            mail_drafts_account($old['account_id']);
            if($revision===0 && (int)$old['revision']===1 && $old['status']==='draft' && hash_equals($old['create_fingerprint'],$fingerprint))return $id;
            if((int)$old['revision']!==$revision || $old['status']!=='draft')throw new RuntimeException('The draft changed. Reload before saving.');
            if(($old['account_id']!==$account || (int)$old['account_revision']!==$accountRevision) && !$confirmSenderChange)throw new RuntimeException('Confirm the current sending account before saving this change.');
        }elseif($revision!==0)throw new RuntimeException('The draft changed. Reload before saving.');
        $now=gmdate('Y-m-d H:i:s');
        if($old)query('UPDATE '.table('mail_drafts').' SET account_id=?,account_revision=?,revision=?,to_json=?,cc_json=?,bcc_json=?,subject=?,body=?,body_html=?,updated_at=? WHERE id=?',[$account,$accountRevision,$revision+1,...$data,$now,$id]);
        else query('INSERT INTO '.table('mail_drafts')." (id,owner_id,account_id,account_revision,revision,status,create_fingerprint,to_json,cc_json,bcc_json,subject,body,body_html,created_at,updated_at) VALUES (?,?,?,?,1,'draft',?,?,?,?,?,?,?,?,?)",[$id,$actor['id'],$account,$accountRevision,$fingerprint,...$data,$now,$now]);
        mail_drafts_snapshot(mail_drafts_row($id,(int)$actor['id']),(int)$actor['id'],$old?'save':'create');return $id;
    }));
}
/** Retained local discard only; never deletes or changes a server message. */
function mail_drafts_discard(string $id,int $revision):void {
    staff_locked(fn()=>staff_transaction(function()use($id,$revision):void {
        $actor=mail_drafts_actor();$row=mail_drafts_row($id,(int)$actor['id'],true);mail_drafts_account($row['account_id']);
        if($revision<1 || $revision>=2147483647 || (int)$row['revision']!==$revision || $row['status']!=='draft')throw new RuntimeException('The draft changed. Reload before discarding.');
        query('UPDATE '.table('mail_drafts')." SET revision=?,status='discarded',updated_at=? WHERE id=?",[$revision+1,gmdate('Y-m-d H:i:s'),$id]);
        mail_drafts_snapshot(mail_drafts_row($id,(int)$actor['id']),(int)$actor['id'],'discard');
    }));
}
/** Manual values only; previews do not fetch client data or write a draft implicitly. */
function mail_drafts_template(string $account,int $accountRevision,string $id,int $revision,array $samples,array $to=[],array $cc=[],array $bcc=[]):DraftContent {
    return staff_locked(function()use($account,$accountRevision,$id,$revision,$samples,$to,$cc,$bcc):DraftContent {
        $actor=mail_drafts_actor();mail_drafts_account($account,$accountRevision);mail_templates_actor();
        $row=mail_templates_row($id,$actor);
        if($revision<1 || (int)$row['revision']!==$revision)throw new RuntimeException('The template changed. Reload before inserting.');
        $values=['portal_url'=>studio_url()];
        foreach(['client_name','project_name','studio_name'] as $key) {
            if(!is_string($samples[$key]??''))throw new InvalidArgumentException('Template values must be text.');
            $value=trim($samples[$key]??'');
            if($value!=='')$values[$key]=mail_templates_text($value,'Template value',200);
        }
        return DraftContent::fromTemplate(mail_templates_model($row),(string)$actor['id'],$values,$to,$cc,$bcc);
    });
}
