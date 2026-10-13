<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/marketing/appointments.php';

function appointments_value(array $values,string $key,string $default=''): string {return is_string($values[$key]??null)?substr($values[$key],0,500):$default;}
function appointments_zone_select(string $value): void {admin_select('timezone','Your display time zone',array_combine(DateTimeZone::listIdentifiers(),DateTimeZone::listIdentifiers()),$value);}
function appointments_slot_select(array $offered,string $value): void {
    $options=[''=>'Choose an offered time'];foreach ($offered['slots']??[] as $slot) $options[(string)$slot['start']]=$slot['label'];
    if ($value!=='' && !array_key_exists($value,$options)) $options[$value]='Previously selected time — choose an available time';
    admin_select('slot','Requested time (studio time zone)', $options,$value);
}
function appointments_admin_action(string $action): void {
    if ($action==='appointments_setup') {appointments_migrate(posted('current_password',200));flash('Scheduling storage prepared. Public slot requests remain disabled until you configure availability.');redirect(admin_url('consultation-settings'));}
    $revision=appointments_uint($_POST['revision']??null,'the saved version',1,2147483647);
    if ($action==='appointments_configure') {appointments_configure($revision,$_POST,posted('current_password',200));flash('Availability saved. Existing appointments and history remain available.');redirect(admin_url('consultation-settings'));}
    $id=appointments_uint($_POST['id']??null,'the consultation inquiry',1,2147483647);
    if ($action==='appointments_adopt') appointments_adopt($id,$_POST['slot']??null,$_POST['timezone']??null);
    else appointments_mutate($id,$revision,substr($action,13),$_POST['slot']??null,$_POST['timezone']??null,posted('current_password',200));
    flash('Appointment record saved. Contact the prospect separately; no confirmation or cancellation message was sent.');redirect(admin_url('consultation',['id'=>$id]));
}
function appointments_admin_render(string $view,string $error): void {
    try {$actor=appointments_actor(false);if ($view==='consultation-settings' && !staff_can('staff.manage',$actor)) throw new RuntimeException('Only an owner can change availability.');}
    catch (RuntimeException $e) {if ($e instanceof PDOException) throw $e;http_response_code(403);admin_head('Consultation access unavailable','consultations',require_admin());echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';admin_foot();exit;}
    admin_head($view==='consultation-settings'?'Consultation availability':'Consultations','consultations',$actor);
    if ($error!=='') echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    echo '<p><a href="'.esc(admin_url('consultations')).'">Appointments</a>';
    if (staff_can('staff.manage',$actor)) echo ' · <a href="'.esc(admin_url('consultation-settings')).'">Availability settings</a>';
    echo '</p>';
    if (!appointments_ready()) {
        echo '<section class="workspace-card"><h2>Prepare consultation scheduling</h2><p>Set available hours and manage pending appointment requests. Existing inquiries remain unchanged.</p>';
        if (staff_can('staff.manage',$actor)) {echo '<form method="post">';admin_action('appointments_setup');admin_input('current_password','Your current password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable scheduling storage</button></form>';}
        else echo '<p>An owner must prepare scheduling first.</p>';
        echo '</section>';
    } else {
        try {if ($view==='consultation-settings') require CWP_CODE_ROOT.'/admin/views/appointment_settings.php';elseif ($view==='consultation') require CWP_CODE_ROOT.'/admin/views/appointment.php';else require CWP_CODE_ROOT.'/admin/views/appointments.php';}
        catch (InvalidArgumentException|RuntimeException $e) {if ($e instanceof PDOException) throw $e;echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';}
    }
    admin_foot();exit;
}
