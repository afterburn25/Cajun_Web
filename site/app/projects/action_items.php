<?php
/** Authorized, privacy-minimal client actions derived from retained review rows. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/projects/ProjectActionItems.php';
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/clients/contacts.php';
use CajunWebPro\Foundation\Projects\{ProjectActionItems,ProjectReviewAction};

function project_action_items(int $jobId,int $clientId):array {
    if($jobId<1||$jobId>2147483647||$clientId<1||$clientId>2147483647)throw new InvalidArgumentException('Project actions are unavailable.');
    $client=studio_client();if(!$client||(int)$client['id']!==$clientId)throw new RuntimeException('Current project access is required.');
    $support=support_current();
    if(array_key_exists('support',$_GET)&&!$support)throw new RuntimeException('Current project access is required.');
    if($support){if(!support_can('support.login')||(int)($support['client']['id']??0)!==$clientId)throw new RuntimeException('Current project access is required.');}
    else contact_require_permission('projects',$clientId);
    studio_job($jobId,$clientId);
    $rows=all('SELECT id,kind,version_no,decision FROM '.table('studio_versions').' WHERE job_id=? ORDER BY id LIMIT '.(ProjectActionItems::MAX_REVIEW_ROWS+1),[$jobId]);
    if(count($rows)>ProjectActionItems::MAX_REVIEW_ROWS)throw new RuntimeException('Project review history exceeds the supported action bound. Existing reviews were preserved.');
    $authorized=[];
    foreach($rows as $row){
        foreach(['id','version_no'] as $key)if(!is_int($row[$key]??null)&&(!is_string($row[$key]??null)||!preg_match('/\A[1-9][0-9]{0,9}\z/D',$row[$key])))throw new RuntimeException('Project review identity is inconsistent.');
        $authorized[]=['id'=>(int)$row['id'],'kind'=>(string)($row['kind']??''),'version_no'=>(int)$row['version_no'],'decision'=>(string)($row['decision']??'')];
    }
    return ProjectActionItems::pendingReviews($authorized);
}

function project_action_items_render(int $jobId,array $actions):void {
    if(!$actions)return;
    echo '<section class="workspace-card project-actions" aria-labelledby="project-actions-title"><h2 id="project-actions-title">Action items</h2><p>Review the latest pending design or proposal versions for this project.</p><ul>';
    foreach($actions as $action){
        if(!$action instanceof ProjectReviewAction)throw new LogicException('Invalid project action projection.');
        $label=ucfirst($action->kind).' version '.$action->version;
        echo '<li><a href="'.esc(studio_url(['job'=>$jobId,'focus_review'=>$action->reviewId]).'#review-'.$action->reviewId).'">'.esc($label).' needs your review</a></li>';
    }
    echo '</ul></section>';
}
