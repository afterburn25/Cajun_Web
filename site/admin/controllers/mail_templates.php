<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/templates.php';

function mail_templates_admin_action(string $action): void {
    if ($action==='mail_templates_setup') {
        mail_templates_migrate(posted('current_password',200));flash('Template library enabled.');redirect(admin_url('mail-templates'));
    }
    mail_templates_actor();$id=posted('template_id',32);
    $content=['name'=>posted('name',120),'subject'=>posted('subject',200),'body'=>posted('body',20000)];
    if ($action==='mail_templates_preview') {
        $samples=[];foreach (['client_name','project_name','studio_name'] as $key) $samples[$key]=posted('sample_'.$key,200);
        $GLOBALS['mail_template_preview']=mail_templates_preview($id,$content,$samples);return;
    }
    if ($action==='mail_templates_create') $id=mail_templates_create($content['name'],$content['subject'],$content['body'],posted('visibility',10));
    else {
        $operation=match($action) {'mail_templates_edit'=>'edit','mail_templates_duplicate'=>'duplicate','mail_templates_activate'=>'activate','mail_templates_archive'=>'archive',default=>throw new InvalidArgumentException('Unknown template action.')};
        $revision=posted('revision',10);
        if (!ctype_digit($revision) || (int)$revision<1) throw new InvalidArgumentException('Reload the template before saving.');
        if ($operation==='duplicate') $content['name']=posted('copy_name',120);
        $id=mail_templates_change($id,(int)$revision,$operation,$content);
    }
    flash('Template saved. No email was sent.');redirect(admin_url('mail-template',['template_id'=>$id]));
}
function mail_templates_admin_render(string $view,string $error): void {
    try {$actor=mail_templates_actor(false);}
    catch (RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        http_response_code(403);admin_head('Template access unavailable','mail-templates',require_admin());
        echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div>';admin_foot();exit;
    }
    admin_head($view==='mail-templates'?'Email templates':'Template details','mail-templates',$actor);
    if ($error!=='') echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if (!mail_templates_ready()) {
        echo '<section class="workspace-card"><h2>Enable your template library</h2><p>Save reusable drafts and preview personalized text. Existing messages and email settings are preserved.</p>';
        if (!staff_ready()) {
            echo '<p>First enable administrator accounts in <a href="'.esc(admin_url('staff')).'">Administrators</a>, then return here.</p>';
        } elseif (staff_can('staff.manage',$actor)) {
            echo '<form method="post">';admin_action('mail_templates_setup');admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable templates</button></form>';
        } else echo '<p>An owner must enable the library before you can use it.</p>';
        echo '</section>';admin_foot();exit;
    }
    try {require CWP_CODE_ROOT.'/admin/views/mail_templates.php';}
    catch (InvalidArgumentException|RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        echo '<div class="notice error" role="alert">'.esc($e->getMessage()).'</div><a class="btn outline" href="'.esc(admin_url('mail-templates')).'">Back to templates</a>';
    }
    admin_foot();exit;
}
