<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/support_stackcp_views.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_launch_http.php';

function provider_support_stackcp_requested():bool {
    return array_key_exists('support_hosting_panel',$_GET)||($_POST['action']??null)==='support_stackcp_open';
}
/** Inspect route provenance before support/client helpers normalize ambient session state. */
function provider_support_stackcp_route_preflight():void {
    if(!provider_support_stackcp_requested())return;
    header('Cache-Control: no-store, private, max-age=0');header('Referrer-Policy: no-referrer');
    $id=$_GET['support']??null;
    if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/',$id)!==1||!array_key_exists('uid',$_SESSION)
        ||array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))fail_page('Hosting support requires an authorized staff support session.',403);
}
function provider_support_stackcp_navigation():bool {
    $ctx=support_current();
    return $ctx!==null&&staff_can('support.login',$ctx['actor'])&&staff_can('support.stackcp',$ctx['actor']);
}
/** Called only inside the existing support branch after its support-specific CSRF check. */
function provider_support_stackcp_staff_action(string $supportId):never {
    if(!provider_support_stackcp_requested()||posted('support_id',32)!==$supportId)throw new RuntimeException('Reload this support session before opening hosting.');
    $expires=posted('expires_epoch',10);
    if(preg_match('/\A[1-9][0-9]{0,9}\z/',$expires)!==1||(int)$expires>2147483647)throw new InvalidArgumentException('Reload the current hosting support review.');
    $result=provider_support_stackcp_open(posted('admission_id',32),posted('permission_id',32),$supportId,(int)$expires,posted('review_proof',64),posted('confirmed',1)==='1','provider_stackcp_launch_redirect');
    throw new RuntimeException(provider_stackcp_launch_state_label((string)$result['state']));
}
function provider_support_stackcp_staff_render(string $supportId,string $error):never {
    $ctx=support_current();
    if(!$ctx||$ctx['session']['id']!==$supportId||!provider_support_stackcp_navigation())fail_page('Hosting support access is unavailable.',403);
    provider_stackcp_launch_page_headers();$snapshot=null;$unavailable=false;
    $page=provider_support_stackcp_page_number('support_panel_page');$link=$_GET['service_link']??null;
    try{
        if($link!==null&&(!is_string($link)||preg_match('/\A[a-f0-9]{32}\z/',$link)!==1))throw new InvalidArgumentException('Invalid service.');
        $snapshot=provider_support_stackcp_staff_snapshot($supportId,$page,$link);$unavailable=!$snapshot['ready'];
    }catch(InvalidArgumentException|RuntimeException $failure){if($failure instanceof PDOException)throw $failure;$unavailable=true;}
    support_record('portal.view','hosting-support',(string)$ctx['client']['id']);
    site_head('Hosting support','portal');support_banner();
    echo '<section class="page-hero compact"><div class="container"><span class="eyebrow">CLIENT SUPPORT</span><h1>Hosting <span class="gold-text">support</span></h1><p>'.esc((string)$ctx['actor']['name']).' supporting '.esc((string)$ctx['client']['name']).'</p><a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">Hosting services</a></div></section><section class="section"><div class="container">';
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    if($unavailable)echo '<p role="alert">Hosting support is unavailable. Ask an owner to review staff permissions, this client’s support policy and the saved hosting assignment.</p>';
    elseif(!$snapshot['items'])echo '<p>No hosting control panels are enabled for you to support this client. An owner can enable named staff access in the 20i account settings.</p>';
    else{
        echo '<div class="workspace-grid">';
        foreach($snapshot['items'] as $item){
            try{
                $review=($_SERVER['REQUEST_METHOD']??'GET')==='POST'?provider_support_stackcp_retry_review($item,$supportId):provider_support_stackcp_review((string)$item['permission_id'],$supportId);
                $history=provider_support_stackcp_history((string)$item['permission_id'],$supportId);
                provider_support_stackcp_launch_render($item,$review,$history,$supportId,$page,$link);
            }catch(InvalidArgumentException|RuntimeException $failure){if($failure instanceof PDOException)throw $failure;echo '<p role="alert">Hosting support access changed. Reload this session to review the available accounts.</p>';}
        }
        echo '</div><nav class="button-row" aria-label="Hosting support pages">';$params=$link===null?[]:['service_link'=>$link];
        if($page>1)echo '<a class="btn outline" href="'.esc(provider_support_stackcp_route($supportId,$params+['support_panel_page'=>$page-1])).'">Previous</a>';
        if($snapshot['has_more']&&$page<500)echo '<a class="btn outline" href="'.esc(provider_support_stackcp_route($supportId,$params+['support_panel_page'=>$page+1])).'">More accounts</a>';
        echo '</nav>';
    }
    echo '</div></section>';site_foot(false);exit;
}
