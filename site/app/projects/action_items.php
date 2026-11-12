<?php
/** Current-authority adapter for privacy-minimal client project actions. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

require_once CWP_CODE_ROOT.'/includes/foundation/projects/ProjectActionItems.php';

use CajunWebPro\Foundation\Projects\ProjectActionItems;

/**
 * Reauthorize the current client and project grant before reading review state.
 * Support sessions deliberately receive no customer action projection.
 *
 * @return list<CajunWebPro\Foundation\Projects\ProjectReviewAction>
 */
function project_current_review_actions(int $jobId,int $clientId): array {
    if($jobId<1||$clientId<1)throw new InvalidArgumentException('Project not found or access denied.');
    if(support_current())return [];
    $current=studio_client();
    if(!$current||(int)$current['id']!==$clientId)throw new InvalidArgumentException('Project not found or access denied.');
    contact_require_permission('projects',$clientId);
    studio_job($jobId,$clientId);
    $rows=all('SELECT id,kind,version_no,decision FROM '.table('studio_versions').' WHERE job_id=? ORDER BY id DESC LIMIT 1001',[$jobId]);
    $authorized=[];
    foreach($rows as $row){
        if(!is_array($row)||array_keys($row)!==['id','kind','version_no','decision'])throw new RuntimeException('Project review state is unavailable.');
        foreach(['id','version_no'] as $field){
            $value=$row[$field];
            if(!is_int($value)&&(!is_string($value)||preg_match('/\A[1-9][0-9]*\z/',$value)!==1))throw new RuntimeException('Project review state is unavailable.');
            $row[$field]=(int)$value;
        }
        $authorized[]=$row;
    }
    return ProjectActionItems::pendingReviews($authorized);
}
