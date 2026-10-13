<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\EmailTemplates\EmailTemplate;
use CajunWebPro\Foundation\EmailTemplates\PlaceholderRegistry;

require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_templates_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_templates_migrate.php';
foreach (['PlaceholderRegistry','EmailTemplate'] as $class) require_once CWP_CODE_ROOT.'/includes/foundation/email-templates/'.$class.'.php';

function mail_templates_ready(): bool {
    return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_templates_schema_version'])['value']??'')==='1';
}
function mail_templates_registry(): PlaceholderRegistry {
    return new PlaceholderRegistry(['client_name'=>'Client name','project_name'=>'Project name','studio_name'=>'Studio name','portal_url'=>'Client sign-in address']);
}
function mail_templates_actor(bool $ready=true): array {
    if (support_current() || array_key_exists('support',$_GET)) throw new RuntimeException('Leave client support to use your own templates.');
    $actor=admin_user();
    if (!$actor || !staff_can('mail.templates.manage',$actor)) throw new RuntimeException('Template access unavailable.');
    if ($ready && !mail_templates_ready()) throw new RuntimeException('An owner must enable the template library first.');
    return $actor;
}
function mail_templates_audit(int $actor,string $action,string $detail): void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'mail_templates.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
/** Plain UTF-8 text only. Subjects/names can never carry mail-header line breaks. */
function mail_templates_text(string $value,string $label,int $max,bool $multiline=false): string {
    $value=trim(str_replace(["\r\n","\r"],"\n",$value));
    if ($value==='' || strlen($value)>$max || !preg_match('//u',$value) || preg_match($multiline?'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/':'/[\x00-\x1F\x7F]/',$value)) {
        throw new InvalidArgumentException($label.' must be valid text, at most '.$max.' bytes'.($multiline?'.':', on one line.'));
    }
    return $value;
}
function mail_templates_content(string $name,string $subject,string $body): array {
    return [mail_templates_text($name,'Template name',120),mail_templates_text($subject,'Subject',200),mail_templates_text($body,'Message',20000,true)];
}
function mail_templates_row(string $id,array $actor,bool $lock=false): array {
    if (!preg_match('/^[a-f0-9]{32}$/D',$id)) throw new InvalidArgumentException('Template unavailable.');
    $row=one('SELECT * FROM '.table('mail_templates')." WHERE id=? AND (visibility='shared' OR owner_id=?)".($lock?' FOR UPDATE':''),[$id,$actor['id']]);
    if (!$row) throw new InvalidArgumentException('Template unavailable.');
    return $row;
}
function mail_templates_editable(array $row,array $actor): bool {
    return (int)$row['owner_id']===(int)$actor['id'] || ($row['visibility']==='shared' && $actor['role']==='owner');
}
/** Reconstitute only validated non-transactional records; immutable history is adapter-owned. */
function mail_templates_model(array $row): EmailTemplate {
    [$name,$subject,$body]=mail_templates_content($row['name'],$row['subject'],$row['body']);
    $time=strtotime($row['updated_at'].' UTC');
    if ($time===false || !in_array($row['status'],['draft','active','archived'],true)) throw new RuntimeException('Stored template is invalid.');
    $model=EmailTemplate::draft($row['id'],$name,$subject,$body,$row['visibility'],(string)$row['owner_id'],false,(string)$row['updated_by'],$time,mail_templates_registry());
    return match($row['status']) {'active'=>$model->activate((string)$row['updated_by'],$time),'archived'=>$model->archive((string)$row['updated_by'],$time),default=>$model};
}
/** Called inside one transaction; snapshot + current row + strict audit succeed together. */
function mail_templates_persist(EmailTemplate $model,int $revision,int $actor,string $action,bool $create): void {
    $time=gmdate('Y-m-d H:i:s',$model->updatedAt);
    $content=[$model->name,$model->subject,$model->body,$model->status,$model->visibility,(int)$model->ownerId];
    if ($create) query('INSERT INTO '.table('mail_templates').' (id,revision,name,subject,body,status,visibility,owner_id,updated_by,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',[$model->id,$revision,...$content,$actor,$time]);
    else query('UPDATE '.table('mail_templates').' SET revision=?,name=?,subject=?,body=?,status=?,visibility=?,owner_id=?,updated_by=?,updated_at=? WHERE id=?',[$revision,...$content,$actor,$time,$model->id]);
    query('INSERT INTO '.table('mail_template_revisions').' (template_id,revision,name,subject,body,status,visibility,owner_id,action,actor_id,recorded_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$model->id,$revision,...$content,$action,$actor,$time]);
    mail_templates_audit($actor,$action,'Template '.$model->id.' revision '.$revision);
}
function mail_templates_create(string $name,string $subject,string $body,string $visibility): string {
    return staff_locked(fn()=>staff_transaction(function() use ($name,$subject,$body,$visibility): string {
        $actor=mail_templates_actor();[$name,$subject,$body]=mail_templates_content($name,$subject,$body);
        $id=bin2hex(random_bytes(16));
        $model=EmailTemplate::draft($id,$name,$subject,$body,$visibility,(string)$actor['id'],false,(string)$actor['id'],time(),mail_templates_registry());
        mail_templates_persist($model,1,(int)$actor['id'],'create',true);return $id;
    }));
}
function mail_templates_change(string $id,int $expected,string $action,array $content=[]): string {
    return staff_locked(fn()=>staff_transaction(function() use ($id,$expected,$action,$content): string {
        $actor=mail_templates_actor();$row=mail_templates_row($id,$actor,true);
        if ($expected<1 || (int)$row['revision']!==$expected) throw new RuntimeException('This template changed. Reload it before saving; your submitted text has been kept below.');
        $model=mail_templates_model($row);$at=time();$by=(string)$actor['id'];
        if ($action==='duplicate') {
            $name=mail_templates_text((string)($content['name']??''),'Copy name',120);
            $copy=$model->duplicate(bin2hex(random_bytes(16)),$name,$by,$at);
            mail_templates_persist($copy,1,(int)$actor['id'],'duplicate',true);return $copy->id;
        }
        if (!mail_templates_editable($row,$actor)) throw new RuntimeException('Only its creator or an owner can change a shared template. Duplicate it to make your own copy.');
        if ($action==='edit') {
            [$name,$subject,$body]=mail_templates_content((string)($content['name']??''),(string)($content['subject']??''),(string)($content['body']??''));
            // Canonical edit enforces lifecycle and placeholder validation before renaming the draft.
            $edited=$model->edit($subject,$body,$by,$at,mail_templates_registry());
            $model=EmailTemplate::draft($id,$name,$edited->subject,$edited->body,$edited->visibility,$edited->ownerId,false,$by,$at,mail_templates_registry());
        } elseif ($action==='activate') $model=$model->activate($by,$at);
        elseif ($action==='archive') $model=$model->archive($by,$at);
        else throw new InvalidArgumentException('Unknown template action.');
        mail_templates_persist($model,$expected+1,(int)$actor['id'],$action,false);return $id;
    }));
}
function mail_templates_get(string $id): array {
    $actor=mail_templates_actor();$row=mail_templates_row($id,$actor);mail_templates_model($row);
    return ['row'=>$row,'editable'=>mail_templates_editable($row,$actor)];
}
function mail_templates_history(string $id,int $page=1): array {
    $actor=mail_templates_actor();mail_templates_row($id,$actor);$page=max(1,min($page,100000));
    return all('SELECT r.*,u.name AS actor_name FROM '.table('mail_template_revisions').' r LEFT JOIN '.table('users').' u ON u.id=r.actor_id WHERE r.template_id=? ORDER BY r.revision DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id]);
}
function mail_templates_list(string $status='active',string $search='',int $page=1): array {
    $actor=mail_templates_actor();
    if (!in_array($status,['all','draft','active','archived'],true)) throw new InvalidArgumentException('Unknown template filter.');
    if (strlen($search)>120) throw new InvalidArgumentException('Search is too long.');
    $page=max(1,min($page,100000));$where="(visibility='shared' OR owner_id=?)";$args=[$actor['id']];
    if ($status!=='all') {$where.=' AND status=?';$args[]=$status;}
    if ($search!=='') {$where.=" AND name LIKE ? ESCAPE '!'";$args[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';}
    return all('SELECT id,revision,name,subject,status,visibility,owner_id,updated_at FROM '.table('mail_templates').' WHERE '.$where.' ORDER BY updated_at DESC,id DESC LIMIT 21 OFFSET '.(($page-1)*20),$args);
}
/** Manual sample fields only: preview never queries a client, sends mail or stores a message. */
function mail_templates_preview(string $id,array $content,array $samples): array {
    $actor=mail_templates_actor();
    if ($id!=='') {
        $row=mail_templates_row($id,$actor);
        if (!mail_templates_editable($row,$actor) || $row['status']==='archived') $content=$row;
    }
    [$name,$subject,$body]=mail_templates_content((string)($content['name']??''),(string)($content['subject']??''),(string)($content['body']??''));
    $model=EmailTemplate::draft($id?:str_repeat('0',32),$name,$subject,$body,'personal',(string)$actor['id'],false,(string)$actor['id'],time(),mail_templates_registry());
    $values=['portal_url'=>studio_url()];
    foreach (['client_name','project_name','studio_name'] as $key) {
        if (!is_string($samples[$key]??'')) throw new InvalidArgumentException('Preview values must be text.');
        $value=trim($samples[$key]??'');
        if ($value!=='') $values[$key]=mail_templates_text($value,'Preview value',200);
    }
    $preview=$model->preview($values);
    if (preg_match('/[\r\n]/',$preview['subject']) || strlen($preview['subject'])>998) throw new InvalidArgumentException('The preview subject is too long or contains a line break.');
    return $preview;
}
