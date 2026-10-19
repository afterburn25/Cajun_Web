<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/projects/progress.php';
require_once CWP_CODE_ROOT.'/app/projects/progress_forms.php';
/** A missing/unknown marker cannot silently bypass retained history. */
function project_progress_mode():bool {
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['project_progress_schema_version'])['value']??'';
    if($version==='1')return true;if($version!=='')throw new RuntimeException('Project history storage needs review. Existing records were preserved.');
    foreach(['studio_progress_events','studio_progress_requests','studio_progress_heads'] as $name){
        if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)throw new RuntimeException('Retained project history is not activated. An owner must review storage before editing.');
    }
    return false;
}
function project_progress_editor(?array $saved,array $clients=[]):void {
    $id=(int)($saved['project']['id']??0);$attempt=$GLOBALS['cwp_progress_attempt']??null;
    if(!is_array($attempt)||(string)$id!==$attempt['id'])$attempt=null;
    $fields=$attempt??($saved['project']??['title'=>'','summary'=>'','stage'=>'discovery','milestones'=>'']);
    $client=$attempt['client_id']??($saved['client_id']??'');$request=$attempt['request_id']??bin2hex(random_bytes(16));$expected=$attempt['expected']??($saved['expected']??'');
    echo '<section class="workspace-card progress-editor"><h2>'.($id?'Edit project':'Create a project').'</h2><p>These details and saved changes are visible to the client.</p>';
    if($attempt!==null)echo '<p class="notice warning">Your unsaved draft is shown below. The original version and request are retained; a stale draft cannot overwrite a newer change. <a href="'.esc($id?admin_url('studio-job',['id'=>$id]):admin_url('studio-clients')).'">Reload saved details and start a new edit</a>.</p>';
    echo '<form method="post" action="'.esc($id?admin_url('studio-job',['id'=>$id]):admin_url('studio-clients')).'">';admin_action('studio_job');
    foreach(['id'=>$id,'request_id'=>$request,'expected'=>$expected] as $key=>$value)studio_hidden($key,$value);
    if($id)studio_hidden('client_id',$client);else admin_select('client_id','Client',$clients,$client);
    admin_input('title','Project title',$fields['title'],'text','required maxlength="160"');$stages=studio_stages();if(!isset($stages[$fields['stage']]))$stages[$fields['stage']]='Invalid stage: '.$fields['stage'];admin_select('stage','Stage',$stages,$fields['stage']);
    admin_textarea('summary','Summary',$fields['summary'],5,10000);admin_textarea('milestones','Milestones',$fields['milestones'],6,15000);
    echo '<button class="btn gold">'.($id?'Save project update':'Create project').'</button></form></section>';
    if($attempt!==null&&$saved!==null){echo '<section class="workspace-card"><h2>Current saved project</h2>';project_progress_fields($saved['project']);echo '</section>';}
}
function project_progress_fields(array $fields):void {
    echo '<dl class="progress-fields"><dt>Project</dt><dd>'.esc($fields['title']).'</dd><dt>Stage</dt><dd>'.esc(studio_stages()[$fields['stage']]??$fields['stage']).'</dd><dt>Summary</dt><dd>'.nl2br(esc($fields['summary'])).'</dd><dt>Milestones</dt><dd>'.nl2br(esc($fields['milestones'])).'</dd></dl>';
}
function project_progress_timeline(array $view,bool $staff,string $focus=''):void {
    $id=$view['project']['id'];$labels=['title'=>'Project title','summary'=>'Summary','stage'=>'Stage','milestones'=>'Milestones'];
    echo '<section class="project-progress" aria-labelledby="progress-title"><h2 id="progress-title">Project history</h2><p>Saved client-visible details, in the order they were recorded. All times are UTC.</p>';
    if(!$view['tracked'])echo '<p>History has not started for this project. Its first saved change will retain a snapshot of the details currently on file.</p>';
    foreach(array_reverse($view['items']) as $event){$baseline=$event['kind']==='tracking_started';$changed=array_map(fn($key)=>$labels[$key],$event['changed_fields']);
        echo '<article class="workspace-card progress-event" id="progress-'.esc($event['id']).'"'.($event['id']===$focus?' tabindex="-1" aria-label="Selected project update"':'').'><span class="eyebrow">'.($baseline?'Tracking started':'Project updated').'</span><h3>'.esc($baseline?'Snapshot when tracking began':implode(', ',$changed).' updated').'</h3><time datetime="'.esc(gmdate('Y-m-d\TH:i:s\Z',$event['occurred_at'])).'">'.esc(gmdate('M j, Y · H:i:s',$event['occurred_at'])).' UTC</time>';
        if($baseline)echo '<p>This snapshot records what was on file when tracking began. Earlier changes are not reconstructed.</p>';
        project_progress_fields($event['after']);echo '</article>';
    }
    if($view['tracked']){
        echo '<nav class="progress-pages" aria-label="Project history pages"><span>Page '.(int)$view['page'].' of '.(int)$view['pages'].'</span>';
        foreach(['Older updates'=>$view['page']-1,'Newer updates'=>$view['page']+1] as $label=>$page)if($page>=1&&$page<=$view['pages']){$params=[$staff?'id':'job'=>$id,'progress_page'=>$page];echo '<a class="btn outline" href="'.esc(($staff?admin_url('studio-job',$params):studio_url($params)).'#progress-title').'">'.esc($label).'</a>';}
        echo '</nav>';
    }
    echo '</section>';
}
function project_progress_setup_form():void {
    echo '<section class="workspace-card"><h2>Project history</h2>';
    try{$ready=project_progress_mode();}catch(RuntimeException $e){echo '<p class="notice error">'.esc($e->getMessage()).'</p>';$ready=false;}
    if($ready)echo '<p>Dated project updates are enabled. Existing projects receive their first history snapshot when they next change.</p>';
    else echo '<p>Enable dated project changes for the client portal. Existing details are preserved; past changes are not reconstructed.</p>';
    if(staff_can('staff.manage')){echo '<form method="post">';admin_action('project_progress_setup');admin_input('password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','I have a current database backup.',false);echo '<button class="btn outline">'.($ready?'Verify project history storage':'Enable project history').'</button></form>';}
    echo '</section>';
}
