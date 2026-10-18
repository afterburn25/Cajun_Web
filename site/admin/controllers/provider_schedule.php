<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/schedule.php';
function provider_schedule_admin_action(string $action):void {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_inventory_schedule_prepare'){provider_schedule_prepare($password);flash('Scheduled refresh history is ready. Scheduling remains disabled.');redirect(admin_url('provider-settings').'#scheduled-refresh');}
    $number=static function(string $key):int{$v=$_POST[$key]??null;if(!is_string($v)||!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/',$v)||(int)$v>2147483647)throw new InvalidArgumentException('Reload the current scheduled refresh form.');return (int)$v;};
    $seq=$number('schedule_sequence');
    if($action==='provider_inventory_schedule_enable'){
        provider_schedule_configure($seq,posted('profile_id',32),$number('revision'),$number('schedule_interval'),posted('schedule_reviewed',1)==='1',$password);flash('Read-only hosting refresh approved. Add the scheduled task in your hosting control panel to run it automatically.');
    }elseif($action==='provider_inventory_schedule_disable'){
        provider_schedule_disable($seq,$password);flash('Scheduled refreshes are disabled. Their history is retained.');
    }elseif($action==='provider_inventory_schedule_recover'){
        $result=provider_schedule_recover($seq,posted('schedule_pending',32),$password);flash($result==='busy'?'An existing worker is still running. Retry after it has finished.':'The interrupted attempt is retained as unfinished work that was reviewed. The schedule is disabled until a fresh approval.',$result==='busy'?'error':'success');
    }elseif($action==='provider_inventory_schedule_run'){
        // Interactive confirmation gates this shortcut, but the worker still uses its durable approval identity.
        staff_locked(function()use($seq,$password):void{provider_settings_owner($password);$ctx=provider_schedule_state();if(!$ctx['ready']||$ctx['last']['sequence']!==$seq)throw new RuntimeException('Reload the current scheduled refresh form.');});
        $result=provider_schedule_run();$message=match($result){'complete'=>'The scheduled hosting list was saved.','partial'=>'A partial hosting list was saved. Review the result before relying on missing packages.','not_due'=>'The next refresh is not due yet.','busy'=>'Another scheduled worker is running.','disabled'=>'Scheduled reads are disabled.','needs_recovery'=>'Review the unfinished attempt before running another refresh.','approval_required'=>'Current approval or the refresh cooldown prevents this run. Review the schedule status.',default=>'The scheduled read did not finish successfully. Its bounded outcome is in the history below.'};
        flash($message,$result==='complete'?'success':'error');
    }else throw new InvalidArgumentException('Unknown scheduled refresh action.');
    redirect(admin_url('provider-settings').'#scheduled-refresh');
}
