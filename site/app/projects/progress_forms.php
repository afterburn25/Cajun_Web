<?php
/** Bounded, side-effect-free project form handling. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/projects/ProjectSnapshot.php';
function project_progress_attempt(array $post):array {
    $limits=['id'=>10,'client_id'=>10,'request_id'=>32,'expected'=>64,'title'=>160,'summary'=>10000,'stage'=>30,'milestones'=>15000];$out=['invalid'=>[]];
    foreach($limits as $key=>$limit){$v=$post[$key]??'';if(!is_string($v)||strlen($v)>$limit){$out['invalid'][]=$key;$v='';}$out[$key]=$v;}
    return $out;
}
function project_progress_form(array $attempt):array {
    if($attempt['invalid'])throw new InvalidArgumentException('Check the length and format of '.implode(', ',str_replace('_',' ',$attempt['invalid'])).'. Oversized or malformed values were not retained.');
    foreach(['id','client_id'] as $key)if(!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/',$attempt[$key])||(int)$attempt[$key]>2147483647)throw new InvalidArgumentException('Choose a valid project and client.');
    $id=(int)$attempt['id'];$client=(int)$attempt['client_id'];if($client<1)throw new InvalidArgumentException('Choose a client.');
    if(!preg_match('/\A[a-f0-9]{32}\z/',$attempt['request_id'])||($id===0?$attempt['expected']!=='':!preg_match('/\A[a-f0-9]{64}\z/',$attempt['expected'])))throw new InvalidArgumentException('The original project form is unavailable. Reload and review the saved project.');
    $fields=[];foreach(['title','summary','stage','milestones'] as $key)$fields[$key]=trim($attempt[$key]);
    $snapshot=\CajunWebPro\Foundation\Projects\ProjectSnapshot::fromArray($fields);
    return ['id'=>$id,'client_id'=>$client,'request_id'=>$attempt['request_id'],'expected'=>$attempt['expected'],'fields'=>$snapshot->data()];
}
function project_progress_query(array $query):array {
    $page=$query['progress_page']??'0';$focus=$query['focus_progress']??'';
    if(!is_string($page)||!preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/',$page)||(int)$page>137||!is_string($focus)||($focus!==''&&!preg_match('/\A[a-f0-9]{32}\z/',$focus)))throw new InvalidArgumentException('This project history link is invalid.');
    return [(int)$page,$focus];
}
