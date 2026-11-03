<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once __DIR__.'/support_stackcp_launch.php';
require_once __DIR__.'/stackcp_launch_views.php';

function provider_support_stackcp_page_number(string $key):int {
    $raw=$_GET[$key]??'1';return is_string($raw)&&preg_match('/\A[1-9][0-9]{0,3}\z/',$raw)===1?min(500,(int)$raw):1;
}
function provider_support_stackcp_route(string $supportId,array $params=[]):string {
    if(preg_match('/\A[a-f0-9]{32}\z/',$supportId)!==1)throw new InvalidArgumentException('Support session unavailable.');
    return site_url('portal/index.php').'?'.http_build_query(['support'=>$supportId,'support_hosting_panel'=>1]+$params);
}
/** Retain only the originally submitted fields; never sign a new review after a rejected POST. */
function provider_support_stackcp_form_values(string $action,array $defaults):array {
    if(($_POST['action']??null)!==$action)return $defaults;
    $out=[];
    foreach($defaults as $name=>$value){
        $raw=provider_stackcp_post_value($name,$name==='review_proof'?64:32);
        $pattern=match($name){'support_id','permission_id','admission_id','profile_id'=>'/\A[a-f0-9]{32}\z/','review_proof'=>'/\A[a-f0-9]{64}\z/','enabled'=>'/\A[01]\z/',default=>'/\A[1-9][0-9]{0,9}\z/'};
        $out[$name]=preg_match($pattern,$raw)===1?$raw:'';
    }
    foreach(['support_id','permission_id','profile_id','package_id','staff_id','enabled'] as $name)if(isset($defaults[$name])&&$out[$name]!== (string)$defaults[$name])$out['review_proof']='';
    foreach($out as $value)if($value==='')$out['review_proof']='';
    return $out;
}
function provider_support_stackcp_retry_review(array $item,string $supportId):array {
    if(($_POST['action']??null)!=='support_stackcp_open')return ['available'=>false];
    $values=provider_support_stackcp_form_values('support_stackcp_open',['support_id'=>$supportId,'permission_id'=>(string)$item['permission_id'],'admission_id'=>'','expires_epoch'=>'','review_proof'=>'']);
    return $values['review_proof']===''?['available'=>false]:['available'=>true,'retry'=>true,'previous_unknown'=>false]+$values;
}
function provider_support_stackcp_permission_render(array $item):void {
    echo '<article class="workspace-card" data-support-stackcp-permission><h3>'.esc((string)$item['package_name']).'</h3><p>Client: <strong>'.esc((string)$item['client_name']).'</strong><br>Staff member: <strong>'.esc((string)$item['staff_name']).'</strong><br>StackCP account: <strong>'.esc((string)$item['stack_identity']).'</strong></p>';
    echo '<p>'.(!empty($item['stale'])?'Access needs a new owner review.':(!empty($item['enabled'])?'Hosting support access is enabled.':'Hosting support access is disabled.')).'</p>';
    $enabled=!empty($item['enabled'])?'0':'1';
    if($enabled==='1'&&empty($item['can_enable'])){echo '<p>Enable this staff member’s hosting support permission and the client’s support policy, then review the package assignment and refresh its membership and grants.</p></article>';return;}
    $values=provider_support_stackcp_form_values('provider_support_stackcp_permission',['profile_id'=>(string)$item['profile_id'],'package_id'=>(string)$item['package_id'],'staff_id'=>(string)$item['staff_id'],'review_proof'=>(string)$item['review_proof'],'enabled'=>$enabled]);
    echo '<form method="post" data-support-stackcp-permission-form>';admin_action('provider_support_stackcp_permission');provider_stackcp_hidden($values);
    admin_check('confirmed',$enabled==='1'?'Allow this named staff member to open the whole StackCP account during this client’s permitted support sessions.':'Disable new support launches for this staff member. Existing provider sessions will stay signed in.',false);
    provider_stackcp_password();echo '<button class="btn '.($enabled==='1'?'gold':'outline').'"'.($values['review_proof']===''?' disabled':'').'>'.($enabled==='1'?'Enable hosting support':'Disable hosting support').'</button></form></article>';
}
function provider_support_stackcp_owner_render():void {
    provider_settings_manager();
    echo '<details class="workspace-card" id="support-stackcp"'.(str_starts_with((string)($_POST['action']??''),'provider_support_stackcp_')||isset($_GET['support_stackcp_staff'])?' open':'').'><summary><strong>Staff hosting support access</strong></summary><p>Choose which staff member may open an assigned StackCP account during an authorized client support session. Every launch records the actual staff member and client.</p><p>This grants access to the whole StackCP user. Disabling it here prevents new launches; existing provider sessions remain signed in.</p>';
    try{
        $page=provider_support_stackcp_page_number('support_stackcp_page');$raw=$_GET['support_stackcp_staff']??null;$staffId=null;
        if($raw!==null){if(!is_string($raw)||preg_match('/\A[1-9][0-9]{0,9}\z/',$raw)!==1||(int)$raw>2147483647)throw new InvalidArgumentException('Choose a valid staff member.');$staffId=(int)$raw;}
        $snapshot=provider_support_stackcp_owner_snapshot($page,$staffId);
        if(!$snapshot['ready']){echo '<p>Complete StackCP setup and enable client support before preparing hosting support access.</p><form method="post" data-support-stackcp-prepare>';admin_action('provider_support_stackcp_prepare');provider_stackcp_password();echo '<button class="btn gold">Prepare hosting support</button></form></details>';return;}
        if(!$snapshot['staff_choices'])echo '<p>No staff accounts are available. Manage staff permissions under Administrators.</p>';
        else{
            echo '<form method="get" action="'.esc(admin_url('provider-settings')).'#support-stackcp" data-support-stackcp-staff>';provider_stackcp_hidden(['view'=>'provider-settings']);echo '<label>Staff member<select name="support_stackcp_staff">';
            foreach($snapshot['staff_choices'] as $choice)echo '<option value="'.(int)$choice['id'].'"'.((int)$choice['id']===(int)$snapshot['selected_staff_id']?' selected':'').'>'.esc((string)$choice['name']).'</option>';
            echo '</select></label><button class="btn outline">Show hosting accounts</button></form>';
        }
        if(!$snapshot['items'])echo '<p>No assigned hosting accounts are available for this staff member.</p>';
        foreach($snapshot['items'] as $item)provider_support_stackcp_permission_render($item);
        echo '<nav class="button-row" aria-label="Hosting support permissions">';
        $params=['support_stackcp_staff'=>(string)$snapshot['selected_staff_id']];
        if($page>1)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',$params+['support_stackcp_page'=>$page-1])).'#support-stackcp">Previous</a>';
        if($snapshot['has_more']&&$page<500)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',$params+['support_stackcp_page'=>$page+1])).'#support-stackcp">More accounts</a>';
        echo '</nav>';
    }catch(InvalidArgumentException|RuntimeException $error){if($error instanceof PDOException)throw $error;echo '<p class="notice error" role="alert">Hosting support access is unavailable. Review staff permissions, client support policy and saved StackCP access history.</p>';}
    echo '</details>';
}
function provider_support_stackcp_launch_render(array $item,array $review,array $history,string $supportId,int $page=1,?string $link=null):void {
    if($page<1||$page>500)throw new InvalidArgumentException('Invalid hosting support page.');
    $params=[];if($page>1)$params['support_panel_page']=$page;
    if($link!==null){if(preg_match('/\A[a-f0-9]{32}\z/',$link)!==1||$link!==($item['link_id']??null))throw new InvalidArgumentException('Invalid hosting service.');$params['service_link']=$link;}
    echo '<article class="workspace-card" data-support-stackcp-launch><h2>'.esc((string)$item['package_name']).'</h2>';
    if(!empty($review['available'])){
        echo '<p>Open the assigned StackCP user’s whole account as staff support. Current permissions are checked before creating a session. Ending this local support session does not sign out an existing StackCP session.</p>';
        if(!empty($review['previous_unknown']))echo '<p class="notice error" role="alert">An earlier attempt has an unknown outcome. A provider session may already exist. A new attempt does not cancel or recover it.</p>';
        $values=provider_support_stackcp_form_values('support_stackcp_open',['support_id'=>$supportId,'permission_id'=>(string)$item['permission_id'],'admission_id'=>(string)$review['admission_id'],'expires_epoch'=>(string)$review['expires_epoch'],'review_proof'=>(string)$review['review_proof']]);
        echo '<form method="post" action="'.esc(provider_support_stackcp_route($supportId,$params)).'" target="_blank" rel="noopener noreferrer" data-support-stackcp-open>'.support_csrf_field();provider_stackcp_hidden(['action'=>'support_stackcp_open']+$values);
        admin_check('confirmed',!empty($review['retry'])?'Continue this original whole-account support attempt; a provider session may already exist.':(!empty($review['previous_unknown'])?'Create a new support session even though an earlier provider session may exist.':'Open the whole StackCP account for this client’s support.'),false);
        echo '<button class="btn gold"'.($values['review_proof']===''?' disabled':'').'>Open hosting control panel</button><p class="hint">Your approval is single-use and expires within five minutes or when this support session ends.</p></form>';
    }else echo '<p>Hosting support access is unavailable. An owner must review the current permission and assignment.</p>';
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')echo '<p class="hint">This keeps the original approval. Submitting it again cannot repeat a completed exchange. Reload to review a new attempt.</p>';
    if(!empty($history['items'])){echo '<details><summary>Your recent support launches</summary><ul>';foreach($history['items'] as $row)echo '<li><time>'.esc(gmdate('Y-m-d H:i:s',(int)$row['created_epoch'])).' UTC</time> — '.esc(provider_stackcp_launch_state_label((string)$row['state'])).'</li>';echo '</ul></details>';}
    echo '</article>';
}
