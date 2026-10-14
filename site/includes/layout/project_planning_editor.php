<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function project_planning_editor(array $saved):void {
    $id=(int)$saved['project']['id'];$attempt=$GLOBALS['cwp_planning_attempt']??null;
    if(!is_array($attempt)||(string)$id!==$attempt['id'])$attempt=null;
    $fields=$attempt??($saved['plan']??['scope'=>'','assigned_contact'=>'','target_date'=>'','client_action'=>'','blockers'=>'','milestones'=>[]]);
    $raw=$attempt['milestones_json']??project_progress_json($fields['milestones']);$editable=project_planning_draft_milestones($raw)!==null;
    echo '<section class="workspace-card project-planning"><h2>Edit project plan</h2><p>Scope, contact, dates, actions and every milestone are visible to this client. Save the whole plan to record an update.</p>';
    if($attempt!==null)echo '<p class="notice warning">Your unsaved plan is shown below with its original version and request. A stale draft cannot replace newer changes. <a href="'.esc(admin_url('studio-job',['id'=>$id])).'#planning-title">Reload the saved plan and start a new edit</a>.</p>';
    echo '<form method="post" action="'.esc(admin_url('studio-job',['id'=>$id])).'" data-planning-editor data-editable="'.($editable?'1':'0').'" data-retained="'.esc(project_progress_json(array_column($saved['plan']['milestones']??[],'id'))).'">';admin_action('project_planning_save');
    foreach(['id'=>$id,'client_id'=>$attempt['client_id']??$saved['client_id'],'request_id'=>$attempt['request_id']??bin2hex(random_bytes(16)),'expected'=>$attempt['expected']??$saved['expected']] as $key=>$value)studio_hidden($key,$value);
    admin_textarea('scope','Agreed scope',$fields['scope'],5,10000);admin_input('assigned_contact','Studio contact (display label)',$fields['assigned_contact'],'text','maxlength="160"');
    admin_input('target_date','Target date (YYYY-MM-DD, optional)',$fields['target_date']??'','text','maxlength="10" placeholder="YYYY-MM-DD"');
    admin_textarea('client_action','What we need from the client',$fields['client_action'],3,4000);admin_textarea('blockers','Current blockers',$fields['blockers'],3,4000);
    echo '<input type="hidden" name="milestones_json" data-planning-data value="'.esc($raw).'">';
    echo '<p data-planning-help>'.($editable?'Milestone editing needs JavaScript. You can still save the fields above while preserving the current milestone details.':'These milestone details could not be opened for editing. Your submitted details are retained; reload the saved plan to recover the milestone editor.').'</p>';
    echo '<div data-planning-status role="status" aria-live="polite"></div><fieldset disabled data-planning-controls><legend>Milestones</legend><p>Choose a milestone to edit. Cancel saved milestones instead of removing them.</p><div class="planning-editor-grid"><div><ol data-planning-list class="planning-choice-list"></ol><button type="button" class="btn outline" data-planning-add>Add milestone</button></div><div data-planning-selected hidden>';
    echo '<label>Milestone title<input type="text" maxlength="160" data-planning-field="title"></label><label>Details<textarea rows="4" maxlength="2000" data-planning-field="details"></textarea></label><label>Status<select data-planning-field="state">';
    foreach(project_planning_states() as $key=>$label)echo '<option value="'.esc($key).'">'.esc($label).'</option>';
    echo '</select></label><label>Responsible party<select data-planning-field="owner"><option value="studio">Studio</option><option value="client">Client</option></select></label><label>Target date (YYYY-MM-DD, optional)<input type="text" maxlength="10" placeholder="YYYY-MM-DD" data-planning-field="due_date"></label><fieldset class="planning-dependencies"><legend>Prerequisites (up to eight)</legend><div data-planning-dependencies></div></fieldset><div class="planning-actions"><button type="button" class="btn outline" data-planning-earlier>Move earlier</button><button type="button" class="btn outline" data-planning-later>Move later</button><button type="button" class="btn outline" data-planning-remove>Remove unsaved milestone</button></div></div></div></fieldset><button class="btn gold">Save project plan</button></form></section>';
    if($attempt!==null)echo '<p class="notice info">The current saved plan is shown below for comparison.</p>';
    echo '<script src="'.esc(site_url('assets/js/project-planning.js')).'" defer></script>';
}
