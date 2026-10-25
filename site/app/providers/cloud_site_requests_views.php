<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

/** Exact HTTP text; never trim, truncate or normalize an approved identity. */
function provider_cloud_site_requests_input(array $input,string $key,int $limit):string {
    $value=$input[$key]??'';
    if(!is_string($value)||strlen($value)>$limit||preg_match('//u',$value)!==1)throw new InvalidArgumentException('Please check the length and format of '.str_replace('_',' ',$key).'.');
    return $value;
}
function provider_cloud_site_requests_input_id(array $input,string $key,int $length=32):string {
    $value=provider_cloud_site_requests_input($input,$key,$length);
    if(!preg_match('/\A[a-f0-9]{'.$length.'}\z/D',$value))throw new InvalidArgumentException('Reload this request and review its current details.');
    return $value;
}
function provider_cloud_site_requests_input_aliases(array $input):array {
    $value=provider_cloud_site_requests_input($input,'aliases',5120);
    if($value==='')return [];
    $names=preg_split('/\r\n|\n|\r/',$value);
    if(count($names)>20)throw new InvalidArgumentException('Use at most 20 additional domains, one per line.');
    foreach($names as $name)if($name===''||strlen($name)>253)throw new InvalidArgumentException('Use one complete domain per line, without blank lines.');
    return $names;
}
/** Only safe display/replay fields survive a failed form; no passwords or consent. */
function provider_cloud_site_requests_retry(array $input):array {
    $action=$input['action']??null;
    if(!is_string($action)||!in_array($action,['cloud_website_request_create','cloud_website_request_cancel','provider_inventory_cloud_request_decline','provider_inventory_cloud_request_dispatch'],true))return [];
    $retry=['action'=>$action];
    foreach(['request_id'=>32,'link_id'=>32,'context'=>64,'domain'=>253,'aliases'=>5120,'expected_event'=>32,'event_id'=>32,'dispatch_id'=>32,'grant_id'=>32,'dispatch_context'=>64,'note'=>1000] as $key=>$limit){
        if(is_string($input[$key]??null)&&strlen($input[$key])<=$limit&&preg_match('//u',$input[$key])===1&&!str_contains($input[$key],"\0"))$retry[$key]=$input[$key];
    }
    return $retry;
}
function provider_cloud_site_requests_page(array $input):int {
    $value=$input['cloud_websites_page']??'1';
    return is_string($value)&&preg_match('/\A[1-9][0-9]{0,3}\z/D',$value)?(int)$value:1;
}
function provider_cloud_site_requests_hidden(string $name,string $value):void {
    echo '<input type="hidden" name="'.esc($name).'" value="'.esc($value).'">';
}
function provider_cloud_site_requests_form(string $action,array $fields):void {
    echo '<form method="post" data-cloud-website-form="'.esc($action).'">'.csrf_field();
    provider_cloud_site_requests_hidden('action',$action);
    foreach($fields as $name=>$value)provider_cloud_site_requests_hidden($name,$value);
}
function provider_cloud_site_requests_password():void {
    echo '<label>Your current owner password<input type="password" name="current_password" value="" autocomplete="current-password" required></label>';
}
function provider_cloud_site_requests_confirmation_url(string $grantId):string {
    if(!preg_match('/\A[a-f0-9]{32}\z/D',$grantId))throw new InvalidArgumentException('Reload the accepted website request.');
    return admin_url('provider-settings',['cloud_website_confirm'=>$grantId]).'#cloud-website-confirmation';
}
function provider_cloud_site_requests_form_id(array $retry,string $key):?string {
    if(!array_key_exists($key,$retry))return bin2hex(random_bytes(16));
    return is_string($retry[$key])&&preg_match('/\A[a-f0-9]{32}\z/D',$retry[$key])?$retry[$key]:null;
}
function provider_cloud_site_requests_render_history(array $row,bool $owner,array $retry=[]):void {
    $id=$row['id'];
    $status=match($row['status']){
        'pending'=>'Pending studio review','cancelled'=>'Cancelled','declined'=>'Declined',
        'accepted_pending'=>'Accepted by 20i; confirmation needed','failed'=>'Creation failed',
        'uncertain'=>'Creation outcome is uncertain','claimed','dispatched','interrupted'=>'Creation outcome needs review',
        default=>'Request status needs review'
    };
    echo '<article class="workspace-card" id="cloud-website-request-'.esc($id).'" data-cloud-website-request="'.esc($id).'" style="min-width:0;overflow-wrap:anywhere"><h3>'.esc($row['domain']).'</h3><p>Server: '.esc($row['server_label']).'</p><p><strong>'.esc($status).'</strong></p><p class="hint">Requested '.esc($row['created_at']).'</p>';
    if($row['aliases'])echo '<p>Additional domains: '.esc(implode(', ',$row['aliases'])).'</p>';
    if($row['note']!=='')echo '<p>'.nl2br(esc($row['note'])).'</p>';
    $confirmation=$row['confirmation']??null;
    if(is_array($confirmation)){
        echo '<p data-cloud-website-confirmation>'.esc(($confirmation['assigned']??false)===true?'Allocated package observed and assigned.':'Package was observed; assignment has changed.').'</p><p class="hint">This confirms the saved package observation, not website health or billing activation.</p>';
        if(!$owner&&($confirmation['assigned']??false)===true)echo '<a class="btn outline" href="'.esc(studio_url(['services'=>1])).'">View hosting services</a>';
    }elseif($row['status']==='accepted_pending')echo '<p>The studio must refresh hosting inventory and confirm the exact returned package before it appears as an assigned hosting service.</p>';
    if($owner&&is_string($row['package_id']??null))echo '<p>Returned package: '.esc($row['package_id']).'</p>';
    if($owner&&$row['status']==='accepted_pending'&&is_string($row['grant_id']??null)&&preg_match('/\A[a-f0-9]{32}\z/D',$row['grant_id']))echo '<a class="btn outline" href="'.esc(provider_cloud_site_requests_confirmation_url($row['grant_id'])).'">Review package confirmation</a>';
    if($owner&&($row['reason']??'')!=='')echo '<p class="notice">This request cannot be submitted in its current context. Review the current server assignment and refresh cloud inventory.</p>';
    $retry=($retry['request_id']??null)===$id?$retry:[];
    $action=$retry['action']??'';
    $stale=$retry&&(($retry['expected_event']??null)!==$row['latest_event']);
    if($action==='provider_inventory_cloud_request_dispatch'&&($retry['dispatch_context']??null)!==($row['dispatch_context']??null))$stale=true;
    if($stale){echo '<p class="notice" role="status">These details changed. Reload and review the request before trying again.</p></article>';return;}
    if(!$owner&&($row['can_cancel']??false)===true){
        $event=provider_cloud_site_requests_form_id($action==='cloud_website_request_cancel'?$retry:[],'event_id');
        if($event!==null){provider_cloud_site_requests_form('cloud_website_request_cancel',['request_id'=>$id,'expected_event'=>$row['latest_event'],'event_id'=>$event]);echo '<label class="check"><input type="checkbox" name="confirm" value="1" required> Cancel this request before studio submission.</label><button class="btn outline">Cancel request</button></form>';}
    }
    if($owner&&($row['can_dispatch']??false)===true){
        $saved=$action==='provider_inventory_cloud_request_dispatch'?$retry:[];
        $dispatch=provider_cloud_site_requests_form_id($saved,'dispatch_id');$grant=provider_cloud_site_requests_form_id($saved,'grant_id');
        if($dispatch!==null&&$grant!==null){provider_cloud_site_requests_form('provider_inventory_cloud_request_dispatch',['request_id'=>$id,'expected_event'=>$row['latest_event'],'dispatch_id'=>$dispatch,'grant_id'=>$grant,'dispatch_context'=>$row['dispatch_context']]);echo '<p>Review the requested domains and the existing server before submitting to 20i.</p><label class="check"><input type="checkbox" name="reviewed" value="1" required> I verified authority for every requested domain and that this server has capacity for this website.</label>';provider_cloud_site_requests_password();echo '<button class="btn gold">Create requested website</button></form>';}
    }
    if($owner&&($row['can_decline']??false)===true){
        $saved=$action==='provider_inventory_cloud_request_decline'?$retry:[];$event=provider_cloud_site_requests_form_id($saved,'event_id');
        if($event!==null){provider_cloud_site_requests_form('provider_inventory_cloud_request_decline',['request_id'=>$id,'expected_event'=>$row['latest_event'],'event_id'=>$event]);echo '<label>Message for the client<textarea name="note" maxlength="1000" rows="3" required>'.esc($saved['note']??'').'</textarea></label><label class="check"><input type="checkbox" name="confirm" value="1" required> Decline this request.</label>';provider_cloud_site_requests_password();echo '<button class="btn outline">Decline request</button></form>';}
    }
    echo '</article>';
}
/** Safe projected rows only. No database, authentication or provider access. */
function provider_cloud_site_requests_render(array $options,array $history,bool $owner,bool $ready,int $page=1,array $retry=[]):void {
    $page=max(1,min(9999,$page));$retry=provider_cloud_site_requests_retry($retry);
    echo '<section id="cloud-website-requests" data-cloud-website-requests><h2>'.($owner?'Client website requests':'Request a website').'</h2><p>Request a website on an existing assigned cloud server. The studio reviews each request before submitting it to 20i. This does not purchase a server or take payment.</p>';
    if(!$ready){
        if($owner){echo '<p>Prepare request history before clients can submit requests.</p>';provider_cloud_site_requests_form('provider_inventory_cloud_request_prepare',[]);provider_cloud_site_requests_password();echo '<button class="btn gold">Enable website requests</button></form>';}
        else echo '<p class="notice">Website requests are not available yet. Please contact the studio.</p>';
        echo '</section>';return;
    }
    if(!$owner){
        if(!$options)echo '<p class="notice">No current cloud server assignment is ready for a new request. Ask the studio to review your assignment and refresh the cloud server list.</p>';
        if(($retry['action']??null)==='cloud_website_request_create'&&!in_array($retry['link_id']??null,array_map(static fn(array $o):string=>$o['binding']['link_id'],$options),true)){
            echo '<article class="workspace-card" data-cloud-website-unsaved style="overflow-wrap:anywhere"><h3>Unsubmitted request</h3><p>'.esc($retry['domain']??'').'</p><p>'.nl2br(esc($retry['aliases']??'')).'</p><p class="notice">The selected server is no longer available for this request. Reload the forms or contact the studio. These domains have not been resubmitted.</p></article>';
        }
        echo '<div class="workspace-grid">';
        foreach($options as $option){
            $link=$option['binding']['link_id'];$saved=($retry['action']??null)==='cloud_website_request_create'&&($retry['link_id']??null)===$link?$retry:[];
            echo '<article class="workspace-card" data-cloud-website-option="'.esc($link).'" style="min-width:0;overflow-wrap:anywhere"><h3>'.esc($option['label']).'</h3>';
            $request=provider_cloud_site_requests_form_id($saved,'request_id');
            if($request===null||($saved&&($saved['context']??null)!==$option['context'])){echo '<p class="notice">These details changed. Reload and review the server before trying again.</p></article>';continue;}
            provider_cloud_site_requests_form('cloud_website_request_create',['request_id'=>$request,'link_id'=>$link,'context'=>$option['context']]);
            echo '<label>Website domain<input type="text" name="domain" maxlength="253" autocomplete="off" autocapitalize="none" spellcheck="false" value="'.esc($saved['domain']??'').'" placeholder="example.com" required></label><label>Additional domains (optional)<textarea name="aliases" maxlength="5120" rows="3" autocapitalize="none" spellcheck="false">'.esc($saved['aliases']??'').'</textarea></label><p class="hint">Use lowercase domains without URLs. Enter at most 20 additional domains, one per line.</p><label class="check"><input type="checkbox" name="request_confirm" value="1" required> Submit these domains for studio review.</label><button class="btn gold">Submit for review</button></form></article>';
        }
        echo '</div>';
    }
    echo '<h3>'.($owner?'Request history':'My requests').'</h3><div class="workspace-grid">';
    if(!$history)echo '<p>No requests on this page.</p>';
    foreach($history as $row)provider_cloud_site_requests_render_history($row,$owner,$retry);
    echo '</div><nav class="button-row" aria-label="Website request pages">';
    $url=static fn(int $p):string=>$owner?admin_url('provider-settings',['cloud_websites_page'=>$p]).'#cloud-website-requests':studio_url(['cloud_websites'=>1,'cloud_websites_page'=>$p]);
    if($page>1)echo '<a class="btn outline" href="'.esc($url($page-1)).'">Newer requests</a>';
    if(count($history)===20&&$page<9999)echo '<a class="btn outline" href="'.esc($url($page+1)).'">Older requests</a>';
    echo '</nav><a class="btn outline" href="'.esc($url($page)).'">Reload request forms</a></section>';
}
