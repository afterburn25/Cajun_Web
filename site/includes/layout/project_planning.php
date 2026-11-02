<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/projects/planning.php';
require_once CWP_CODE_ROOT.'/app/projects/planning_forms.php';
require_once CWP_CODE_ROOT.'/includes/layout/project_planning_editor.php';
function project_planning_mode():bool {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['project_planning_schema_version'])['value']??'';
    if($version==='1')return true;if($version!=='')throw new RuntimeException('Project planning storage needs review. Existing records were preserved.');
    foreach(['studio_plan_events','studio_plan_requests','studio_plan_heads'] as $name){
        if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)throw new RuntimeException('Retained planning records are not activated. An owner must review storage before editing.');
    }return false;
}
function project_planning_states():array{return ['planned'=>'Planned','in_progress'=>'In progress','awaiting_client'=>'Awaiting client','blocked'=>'Blocked','completed'=>'Completed','cancelled'=>'Cancelled'];}
function project_planning_fields(array $plan):void {
    echo '<dl class="planning-facts">';
    foreach(['scope'=>'Agreed scope','assigned_contact'=>'Studio contact','target_date'=>'Target date','client_action'=>'What we need from you','blockers'=>'Current blockers'] as $key=>$label){$value=$plan[$key]??'';echo '<dt>'.esc($label).'</dt><dd>'.($value===''?'Not set':nl2br(esc($value))).'</dd>';}
    echo '</dl><h3>Milestones</h3>';$names=array_column($plan['milestones'],'title','id');$states=project_planning_states();
    if(!$plan['milestones'])echo '<p>No structured milestones have been added.</p>';
    echo '<ol class="planning-milestones">';foreach($plan['milestones'] as $item){
        echo '<li><div class="planning-milestone-heading"><strong>'.esc($item['title']).'</strong><span class="workspace-status">'.esc($states[$item['state']]).'</span></div><p class="planning-meta">Responsible: '.($item['owner']==='client'?'Client':'Studio').' · '.($item['due_date']===null?'No target date':'Target: '.esc($item['due_date'])).'</p>';
        if($item['details']!=='')echo '<p>'.nl2br(esc($item['details'])).'</p>';
        if($item['depends_on'])echo '<p><strong>Depends on:</strong> '.esc(implode(', ',array_map(fn($id)=>$names[$id],$item['depends_on']))).'</p>';echo '</li>';
    }echo '</ol>';
}
function project_planning_dashboard(array $view):void {
    echo '<section class="workspace-card project-planning" aria-labelledby="planning-title"><span class="eyebrow">YOUR PROJECT PLAN</span><h2 id="planning-title">Scope &amp; next steps</h2><p>Targets are calendar dates. Contact the studio in the conversation below if your requirements or availability change.</p>';
    if($view['planned'])project_planning_fields($view['plan']);else echo '<p>The studio has not saved a structured plan for this project yet.</p>';echo '</section>';
}
function project_planning_timeline(array $view,bool $staff,string $focus=''):void {
    echo '<section class="project-planning" aria-labelledby="planning-history"><h2 id="planning-history">Planning history</h2><p>Saved plans in recorded order. All times are UTC.</p>';
    $labels=['scope'=>'Scope','assigned_contact'=>'Studio contact','target_date'=>'Target date','client_action'=>'Client action','blockers'=>'Blockers','milestones'=>'Milestones'];
    foreach(array_reverse($view['items']) as $event){$first=$event['kind']==='plan_started';
        echo '<article class="workspace-card progress-event" id="planning-'.esc($event['id']).'"'.($event['id']===$focus?' tabindex="-1" aria-label="Selected planning update"':'').'><h3>'.($first?'Plan created':esc(implode(', ',array_map(fn($key)=>$labels[$key],$event['changed_fields']))).' updated').'</h3><time datetime="'.esc(gmdate('Y-m-d\TH:i:s\Z',$event['occurred_at'])).'">'.esc(gmdate('M j, Y · H:i:s',$event['occurred_at'])).' UTC</time>';
        if($first)echo '<p>This is the first saved plan. Earlier planning discussions are not reconstructed.</p>';
        echo '<details'.($event['id']===$focus?' open':'').'><summary>View this saved plan</summary>';project_planning_fields($event['after']);echo '</details></article>';
    }
    if($view['planned']){echo '<nav class="progress-pages" aria-label="Planning history pages"><span>Page '.(int)$view['page'].' of '.(int)$view['pages'].'</span>';
        foreach(['Older plans'=>$view['page']-1,'Newer plans'=>$view['page']+1] as $label=>$page)if($page>=1&&$page<=$view['pages']){$params=[$staff?'id':'job'=>$view['project']['id'],'plan_page'=>$page];echo '<a class="btn outline" href="'.esc(($staff?admin_url('studio-job',$params):studio_url($params)).'#planning-history').'">'.esc($label).'</a>';}
        echo '</nav>';
    }echo '</section>';
}
function project_planning_setup_form():void {
    echo '<section class="workspace-card"><h2>Project planning</h2>';
    try{$ready=project_planning_mode();}catch(RuntimeException $e){echo '<p class="notice error">'.esc($e->getMessage()).'</p>';$ready=false;}
    echo '<p>'.($ready?'Structured project plans and dated snapshots are enabled.':'Enable client-visible scope, dates, responsibilities and milestone dependencies. Existing project details are preserved.').'</p>';
    if(staff_can('staff.manage')){echo '<form method="post">';admin_action('project_planning_setup');admin_input('password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','I have a current database backup.',false);echo '<button class="btn outline">'.($ready?'Verify planning storage':'Enable project planning').'</button></form>';}
    echo '</section>';
}
