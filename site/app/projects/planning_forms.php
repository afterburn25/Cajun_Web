<?php
/** Bounded request capture; the native adapter remains the authority for every save. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/project-planning/ProjectPlan.php';
use CajunWebPro\Foundation\ProjectPlanning\ProjectPlan;
function project_planning_attempt(array $post):array {
    $limits=['id'=>10,'client_id'=>10,'request_id'=>32,'expected'=>64,'scope'=>10000,'assigned_contact'=>160,'target_date'=>10,'client_action'=>4000,'blockers'=>4000,'milestones_json'=>262144];$out=['invalid'=>[]];
    foreach($limits as $key=>$limit){$value=$post[$key]??'';
        if(!is_string($value)||strlen($value)>$limit||preg_match('//u',$value)!==1){$out['invalid'][]=$key;$value='';}
        $out[$key]=$value;
    }return $out;
}
function project_planning_milestones(string $raw):array {
    if(strlen($raw)>ProjectPlan::MAX_BYTES)throw new InvalidArgumentException('Milestone details are too large.');
    try{$items=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}
    catch(JsonException){throw new InvalidArgumentException('Milestone details are invalid. Reload the saved plan before editing milestones.');}
    if(!is_array($items)||!array_is_list($items)||count($items)>100||json_encode($items,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)!==$raw)throw new InvalidArgumentException('Milestone details are invalid. Reload the saved plan before editing milestones.');
    return $items;
}
/** Shape-only check: invalid dates, states and dependency graphs remain repairable drafts. */
function project_planning_draft_milestones(string $raw):?array {
    try{$items=project_planning_milestones($raw);}catch(InvalidArgumentException){return null;}$seen=[];
    foreach($items as $item){
        if(!is_array($item)||array_keys($item)!==ProjectPlan::MILESTONE_FIELDS)return null;
        foreach(['id'=>32,'title'=>160,'details'=>2000,'state'=>40,'owner'=>40] as $key=>$limit)if(!is_string($item[$key])||strlen($item[$key])>$limit)return null;
        if(!preg_match('/\A[a-f0-9]{32}\z/',$item['id'])||isset($seen[$item['id']]))return null;$seen[$item['id']]=true;
        if($item['due_date']!==null&&(!is_string($item['due_date'])||strlen($item['due_date'])>10))return null;
        if(!is_array($item['depends_on'])||!array_is_list($item['depends_on'])||count($item['depends_on'])>8)return null;
        foreach($item['depends_on'] as $id)if(!is_string($id)||!preg_match('/\A[a-f0-9]{32}\z/',$id))return null;
    }return $items;
}
function project_planning_form(array $attempt):array {
    if($attempt['invalid'])throw new InvalidArgumentException('Some planning values were oversized or malformed and could not be retained. Review the form before saving.');
    foreach(['id','client_id'] as $key)if(!preg_match('/\A[1-9][0-9]{0,9}\z/',$attempt[$key])||(int)$attempt[$key]>2147483647)throw new InvalidArgumentException('Choose a valid existing project and client.');
    if(!preg_match('/\A[a-f0-9]{32}\z/',$attempt['request_id'])||!preg_match('/\A[a-f0-9]{64}\z/',$attempt['expected']))throw new InvalidArgumentException('The original planning form is unavailable. Reload and review the saved plan.');
    $plan=ProjectPlan::fromArray(['scope'=>$attempt['scope'],'assigned_contact'=>$attempt['assigned_contact'],'target_date'=>$attempt['target_date']===''?null:$attempt['target_date'],'client_action'=>$attempt['client_action'],'blockers'=>$attempt['blockers'],'milestones'=>project_planning_milestones($attempt['milestones_json'])]);
    return ['id'=>(int)$attempt['id'],'client_id'=>(int)$attempt['client_id'],'request_id'=>$attempt['request_id'],'expected'=>$attempt['expected'],'fields'=>$plan->data()];
}
function project_planning_query(array $query):array {
    $page=$query['plan_page']??'0';$focus=$query['focus_plan']??'';
    if(!is_string($page)||!preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/',$page)||(int)$page>410||!is_string($focus)||($focus!==''&&!preg_match('/\A[a-f0-9]{32}\z/',$focus)))throw new InvalidArgumentException('This planning history link is invalid.');
    return [(int)$page,$focus];
}
