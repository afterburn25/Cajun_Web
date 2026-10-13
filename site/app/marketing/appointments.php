<?php
/** One studio calendar. Pending requests and explicit staff decisions; no mail/provider. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\Scheduling\Consultation;
use CajunWebPro\Foundation\Scheduling\TimeWindow;

require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/marketing/appointment_content.php';
require_once CWP_CODE_ROOT.'/database/schema/appointments_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/appointments_migrate.php';

function appointments_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['appointments_schema_version'])['value']??'')==='1';}
function appointments_actor(bool $ready=true): array {
    if (support_current() || array_key_exists('support',$_GET)) throw new RuntimeException('Leave client support before using consultations.');
    $actor=admin_user();if (!$actor || !staff_can('website.manage',$actor)) throw new RuntimeException('Current website and inquiry permission is required.');
    if ($ready && !appointments_ready()) throw new RuntimeException('An owner must prepare consultation scheduling first.');return $actor;
}
function appointments_settings(bool $lock=false): array {
    if ($lock && !db()->inTransaction()) throw new LogicException('Calendar lock requires a transaction.');
    $row=one('SELECT * FROM '.table('appointment_settings').' WHERE id=1'.($lock?' FOR UPDATE':''));
    if (!$row) throw new RuntimeException('Scheduling configuration is unavailable. An owner must verify its setup.');return $row;
}
function appointments_audit(int $actor,string $action,string $detail): void {query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'appointments.'.$action,$detail,gmdate('Y-m-d H:i:s')]);}
function appointments_settings_snapshot(int $actor): void {
    $row=appointments_settings();query('INSERT INTO '.table('appointment_settings_revisions').' (revision,snapshot,actor_id,recorded_at) VALUES (?,?,?,?)',[$row['revision'],json_encode($row,JSON_THROW_ON_ERROR),$actor,gmdate('Y-m-d H:i:s')]);
    appointments_audit($actor,'configuration','Calendar revision '.$row['revision']);
}
function appointments_seed(int $actor=0): void {
    query('INSERT INTO '.table('appointment_settings')." (id,revision,timezone,slot_minutes,buffer_minutes,notice_minutes,enabled,rules,updated_by,updated_at) VALUES (1,1,'UTC',30,15,120,0,'[]',?,?)",[$actor,gmdate('Y-m-d H:i:s')]);appointments_settings_snapshot($actor);
}
function appointments_active(int $from,int $until): array {
    return all('SELECT a.*,l.name,l.email FROM '.table('appointments').' a JOIN '.table('leads')." l ON l.id=a.lead_id WHERE a.status IN ('requested','confirmed') AND a.ends_at>? AND a.starts_at<? ORDER BY a.starts_at,a.id",[$from,$until]);
}
function appointments_context(array $settings,int $now): array {
    $buffer=(int)$settings['buffer_minutes']*60;$until=$now+30*86400;
    $rows=appointments_active(max(0,$now-$buffer),$until+$buffer);
    return [$rows,appointments_calendar($settings,$rows,$now,$until)];
}
/** Public projection contains offered times only; never contacts or private appointments. */
function appointments_public(): array {
    if (!appointments_ready()) return ['enabled'=>false];$settings=appointments_settings();
    if (!(int)$settings['enabled']) return ['enabled'=>false];$now=time();[, $calendar]=appointments_context($settings,$now);
    $slots=$calendar->slots($now,$now+30*86400,$now);$result=[];
    foreach ($slots as $slot) $result[$slot->startsAt]=['start'=>$slot->startsAt,'end'=>$slot->endsAt,'label'=>appointments_label($slot->startsAt,$slot->endsAt,$settings['timezone'])];
    return ['enabled'=>true,'revision'=>(int)$settings['revision'],'timezone'=>$settings['timezone'],'slots'=>array_values($result)];
}
/** Validate form identity before intake; the transaction checks it again under the calendar lock. */
function appointments_intake(array $input): ?array {
    $mode=appointments_text($input['appointment_mode']??'','the scheduling form',20,false);
    $settings=appointments_ready()?appointments_settings():null;
    if (!$settings || !(int)$settings['enabled']) {
        if ($mode==='slots') throw new RuntimeException('Availability changed. Reload the consultation page before sending.');return null;
    }
    if ($mode!=='slots') throw new RuntimeException('Scheduled requests are now enabled. Reload and choose an available time.');
    $revision=appointments_uint($input['calendar_revision']??null,'the availability version',1,2147483647);
    if ($revision!==(int)$settings['revision']) throw new RuntimeException('Availability changed. Reload before choosing a time.');
    $slot=appointments_slot($input['slot']??null,$settings,time());$zone=appointments_zone($input['timezone']??null);
    return ['revision'=>$revision,'start'=>$slot->startsAt,'zone'=>$zone,'detail'=>'Requested: '.appointments_label($slot->startsAt,$slot->endsAt,$zone)."\nPending studio confirmation. No calendar provider or confirmation message has been used."];
}
function appointments_check_intake(?array $intake,array $settings): void {
    if ((bool)$settings['enabled']!==($intake!==null) || ($intake!==null && $intake['revision']!==(int)$settings['revision'])) throw new RuntimeException('Availability changed. Reload the consultation page before sending.');
}
function appointments_configure(int $revision,array $input,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function() use ($revision,$input,$password): void {
        appointments_actor();$actor=staff_owner($password);$current=appointments_settings(true);
        if ($revision<1 || (int)$current['revision']!==$revision) throw new RuntimeException('Availability changed. Reload before saving; your submitted values remain below.');
        $next=appointments_config_values($input);$rows=appointments_active(time(),4102444800);$end=null;
        foreach ($rows as $row) {if ($end!==null && (int)$row['starts_at']<$end+$next['buffer_minutes']*60) throw new InvalidArgumentException('That buffer conflicts with existing appointments. Keep their current spacing or reschedule them first.');$end=max($end??0,(int)$row['ends_at']);}
        query('UPDATE '.table('appointment_settings').' SET revision=?,timezone=?,slot_minutes=?,buffer_minutes=?,notice_minutes=?,enabled=?,rules=?,updated_by=?,updated_at=? WHERE id=1',[$revision+1,$next['timezone'],$next['slot_minutes'],$next['buffer_minutes'],$next['notice_minutes'],$next['enabled'],$next['rules'],$actor['id'],gmdate('Y-m-d H:i:s')]);
        appointments_settings_snapshot((int)$actor['id']);
    }));
}
function appointments_lead(int $id,bool $lock=false): array {
    $lead=$id>0?one('SELECT * FROM '.table('leads').' WHERE id=?'.($lock?' FOR UPDATE':''),[$id]):null;
    if (!$lead || $lead['service']!=='Consultation request') throw new InvalidArgumentException('Consultation inquiry unavailable.');return $lead;
}
function appointments_row(int $leadId,bool $lock=false): ?array {return one('SELECT * FROM '.table('appointments').' WHERE lead_id=?'.($lock?' FOR UPDATE':''),[$leadId]);}
function appointments_snapshot(int $id,int $actor,string $action): void {
    $row=one('SELECT * FROM '.table('appointments').' WHERE id=?',[$id]);
    query('INSERT INTO '.table('appointment_revisions').' (appointment_id,revision,snapshot,action,actor_id,recorded_at) VALUES (?,?,?,?,?,?)',[$id,$row['revision'],json_encode($row,JSON_THROW_ON_ERROR),$action,$actor,gmdate('Y-m-d H:i:s')]);appointments_audit($actor,$action,'Appointment '.$id.' revision '.$row['revision']);
}
/** Called only inside a transaction after taking the configuration lock. */
function appointments_capture(int $leadId,array $settings,mixed $start,mixed $zone,int $actor=0): void {
    if (!db()->inTransaction() || !appointments_ready()) throw new LogicException('Appointment capture requires prepared storage and a transaction.');
    if (!(int)$settings['enabled']) throw new RuntimeException('Scheduled requests are currently unavailable. Reload the consultation page.');
    $lead=appointments_lead($leadId,true);if ((int)$lead['consent']!==1) throw new InvalidArgumentException('Contact consent is required.');
    $now=time();$slot=appointments_slot($start,$settings,$now);$zone=appointments_zone($zone);[, $calendar]=appointments_context($settings,$now);
    if (appointments_row($leadId,true)) throw new RuntimeException('This inquiry already has an appointment. Reload its current version.');
    $model=$calendar->request(new Consultation((string)$leadId,$lead['name'],$lead['email'],$slot,$zone,Consultation::REQUESTED,$now),$now);
    query('INSERT INTO '.table('appointments')." (lead_id,status,starts_at,ends_at,timezone,requested_at,revision,updated_by,updated_at,created_at) VALUES (?,?,?,?,?,?,1,?,?,?)",[$leadId,$model->status,$slot->startsAt,$slot->endsAt,$zone,$now,$actor,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);appointments_snapshot((int)db()->lastInsertId(),$actor,$actor?'adopt':'requested');
}
function appointments_adopt(int $leadId,mixed $start,mixed $zone): void {
    staff_locked(fn()=>staff_transaction(function() use ($leadId,$start,$zone): void {$actor=appointments_actor();$settings=appointments_settings(true);appointments_capture($leadId,$settings,$start,$zone,(int)$actor['id']);}));
}
function appointments_get(int $leadId,int $page=1): array {
    appointments_actor();$lead=appointments_lead($leadId);$row=appointments_row($leadId);$page=max(1,min($page,100000));
    return ['lead'=>$lead,'row'=>$row,'history'=>$row?all('SELECT h.*,u.name AS actor_name FROM '.table('appointment_revisions').' h LEFT JOIN '.table('users').' u ON u.id=h.actor_id WHERE h.appointment_id=? ORDER BY h.revision DESC LIMIT 21 OFFSET '.(($page-1)*20),[$row['id']]):[]];
}
function appointments_mutate(int $leadId,int $revision,string $action,mixed $start=null,mixed $zone=null,#[\SensitiveParameter] string $password=''): void {
    staff_locked(fn()=>staff_transaction(function() use ($leadId,$revision,$action,$start,$zone,$password): void {
        $actor=appointments_actor();$settings=appointments_settings(true);$lead=appointments_lead($leadId,true);$row=appointments_row($leadId,true);
        if (!$row) throw new InvalidArgumentException('Start scheduling this inquiry first.');
        if ($revision<1 || (int)$row['revision']!==$revision) throw new RuntimeException('This appointment changed. Reload before saving; your submitted values remain below.');
        $now=time();$model=appointments_model($row+$lead);[$rows,$calendar]=appointments_context($settings,$now);
        // The context only loads active nearby rows; always track the exact current record for terminal/error decisions.
        if (!array_filter($rows,fn($v)=>(int)$v['id']===(int)$row['id'])) {$rows[]=$row+$lead;$calendar=appointments_calendar($settings,$rows,$now,$now+30*86400);}
        if (in_array($action,['confirm','reschedule'],true)) {
            if (!(int)$settings['enabled'] || (int)$lead['consent']!==1) throw new InvalidArgumentException('Enable scheduling and verify contact consent before confirming or rescheduling.');
            if ((int)$row['starts_at']<=$now) throw new InvalidArgumentException('Past appointments cannot be confirmed or rescheduled.');
        }
        if ($action==='confirm') {
            if (!password_verify($password,$actor['password_hash'])) throw new InvalidArgumentException('Your current password is not correct.');
            // Notice applies to a new request/reschedule, not to confirming an already held future slot.
            $confirmSettings=$settings;$confirmSettings['notice_minutes']=0;$calendar=appointments_calendar($confirmSettings,$rows,$now,$now+30*86400);
            if (!$calendar->isBookable($model->slot,$model->id,$now)) throw new InvalidArgumentException('The requested time no longer matches current availability or length. Reschedule before confirming.');
            $next=$calendar->transition($model,Consultation::CONFIRMED);
        } elseif ($action==='reschedule') {
            $slot=appointments_slot($start,$settings,$now);$zone=appointments_zone($zone);$moved=$calendar->reschedule($model,$slot,$now);
            $next=new Consultation($moved->id,$moved->name,$moved->email,$moved->slot,$zone,$moved->status,$moved->requestedAt);
        } elseif (in_array($action,['cancel','decline'],true)) $next=$calendar->transition($model,$action==='cancel'?Consultation::CANCELLED:Consultation::DECLINED);
        else throw new InvalidArgumentException('Choose a valid appointment action.');
        query('UPDATE '.table('appointments').' SET revision=?,status=?,starts_at=?,ends_at=?,timezone=?,updated_by=?,updated_at=? WHERE id=?',[$revision+1,$next->status,$next->slot->startsAt,$next->slot->endsAt,$next->timeZone,$actor['id'],gmdate('Y-m-d H:i:s'),$row['id']]);appointments_snapshot((int)$row['id'],(int)$actor['id'],$action);
    }));
}
