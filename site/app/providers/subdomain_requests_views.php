<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Exact HTTP values; the native service owns identity, policy and hostname validation. */
function provider_subdomain_requests_ui_text(array $input,string $key,int $limit): string {
    $value=$input[$key]??'';
    if(!is_string($value)||strlen($value)>$limit||preg_match('//u',$value)!==1)throw new InvalidArgumentException('Check the length and format of '.str_replace('_',' ',$key).'.');
    return $value;
}
function provider_subdomain_requests_ui_id(array $input,string $key,int $length=32): string {
    $value=provider_subdomain_requests_ui_text($input,$key,$length);
    if(preg_match('/\A[a-f0-9]{'.$length.'}\z/',$value)!==1)throw new InvalidArgumentException('Reload and review this request before continuing.');
    return $value;
}
function provider_subdomain_requests_ui_confirm(array $input,string $key): void {
    if(($input[$key]??null)!=='1')throw new InvalidArgumentException('Confirm the request before continuing.');
}
function provider_subdomain_requests_ui_query(array $input): array {
    $page=$input['request_page']??'1';
    if(!is_string($page)||preg_match('/\A[1-9][0-9]{0,4}\z/',$page)!==1||(int)$page>10000)throw new InvalidArgumentException('Choose a valid request page.');
    return [(int)$page,array_key_exists('request_id',$input)?provider_subdomain_requests_ui_id($input,'request_id'):null];
}
function provider_subdomain_requests_ui_url(bool $owner,?string $id=null,int $page=1): string {
    $params=['request_page'=>max(1,min(10000,$page))];
    if($id!==null)$params['request_id']=provider_subdomain_requests_ui_id(['request_id'=>$id],'request_id');
    return $owner?admin_url('subdomain-requests',$params):studio_url(['subdomain_requests'=>1]+$params);
}
function provider_subdomain_requests_ui_hidden(string $key,string $value): void {
    echo '<input type="hidden" name="'.esc($key).'" value="'.esc($value).'">';
}
function provider_subdomain_requests_ui_form(string $action,string $marker): void {
    echo '<form method="post" action="'.esc(provider_subdomain_requests_ui_url(str_starts_with($action,'provider_subdomain_request_'))).'" '.$marker.'>'.csrf_field();
    provider_subdomain_requests_ui_hidden('action',$action);
}
function provider_subdomain_requests_ui_password(): void {
    echo '<label>Your current owner password<input type="password" name="current_password" value="" required autocomplete="current-password"></label>';
}
function provider_subdomain_requests_ui_label(string $state): string {
    return match($state){
        'pending'=>'Awaiting studio review','cancelled'=>'Request withdrawn','declined'=>'Request declined',
        'not_sent'=>'Add request was not sent','interrupted'=>'Saved request needs studio review',
        'observed'=>'Subdomain appeared in a later check',
        'dispatched','unknown','uncertain'=>'Subdomain requested; result not yet confirmed',
        default=>'Request status needs review',
    };
}

/** Never retain passwords, confirmations, arbitrary destinations or provider context. */
function provider_subdomain_requests_ui_retry(array $input,bool $owner): array {
    $actions=$owner?['provider_subdomain_request_prepare','provider_subdomain_request_decline','provider_subdomain_request_approve','provider_subdomain_request_verify','provider_subdomain_request_recover']:['subdomain_request_create','subdomain_request_cancel'];
    if(!is_string($input['action']??null)||!in_array($input['action'],$actions,true))return [];
    $out=['action'=>$input['action']];
    foreach(['request_id'=>32,'link_id'=>32,'parent_domain'=>253,'subdomain'=>253,'review_sha256'=>64,'expected_event'=>32,'event_id'=>32,'request_sha256'=>64,'owner_request_id'=>32,'review_proof'=>4096,'ledger_sha256'=>64,'note'=>1000] as $key=>$limit){
        if(!array_key_exists($key,$input))continue;
        $v=$input[$key];
        $out[$key]=is_string($v)&&strlen($v)<=$limit&&preg_match('//u',$v)===1&&!preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v)?$v:'';
    }
    return $out;
}
function provider_subdomain_requests_ui_create(array $option,array $retry=[],bool $available=true): void {
    $value=static fn(string $key,string $fallback):string=>$retry?($retry[$key]??''):$fallback;
    $matches=!$retry||(($retry['link_id']??'')===$option['link_id']&&($retry['parent_domain']??'')===$option['parent_domain']&&($retry['review_sha256']??'')===$option['review_sha256']);
    echo '<article class="workspace-card" data-subdomain-request-create><h3 style="overflow-wrap:anywhere">'.esc($option['parent_domain']?:'Your unsent request').'</h3>';
    if($option['package_id']!==null)echo '<p class="hint">Assigned hosting package '.esc((string)$option['package_id']).'</p>';
    provider_subdomain_requests_ui_form('subdomain_request_create','data-subdomain-request-create-form');
    foreach(['request_id'=>bin2hex(random_bytes(16)),'link_id'=>$option['link_id'],'review_sha256'=>$option['review_sha256'],'parent_domain'=>$option['parent_domain']] as $key=>$fallback)provider_subdomain_requests_ui_hidden($key,$value($key,$fallback));
    echo '<label>Full subdomain name<input name="subdomain" type="text" value="'.esc($value('subdomain','')).'" maxlength="253" required autocomplete="off" spellcheck="false" autocapitalize="none" placeholder="shop.example.com"></label>';
    echo '<p class="hint">Enter one name immediately below this parent, such as shop.example.com under example.com. The studio will check the current hosting details and allowance before approving.</p>';
    if(!$available||!$matches)echo '<p class="notice">Your entered name is retained. The assigned parent details changed or are unavailable; reload and review them before continuing.</p>';
    echo '<label class="check"><input type="checkbox" name="confirmed" value="1" required> Ask the studio to review this subdomain.</label><button class="btn gold"'.(!$available||!$matches?' disabled':'').'>Request subdomain review</button></form></article>';
}
function provider_subdomain_requests_ui_unavailable_retry(array $retry,bool $owner): void {
    if(!$retry)return;
    if(!$owner&&$retry['action']==='subdomain_request_create'){
        provider_subdomain_requests_ui_create(['link_id'=>'','package_id'=>null,'parent_domain'=>'','review_sha256'=>''],$retry,false);return;
    }
    echo '<section class="workspace-card" data-subdomain-request-retained><h3>Your unsaved review</h3><p>These details are retained. Reload the request before submitting again.</p>';
    provider_subdomain_requests_ui_form($retry['action'],'data-subdomain-request-retained-form');
    foreach($retry as $key=>$value)if(!in_array($key,['action','note','subdomain','parent_domain'],true))provider_subdomain_requests_ui_hidden($key,$value);
    if(array_key_exists('note',$retry))echo '<label>Reason for the client<textarea name="note" rows="3">'.esc($retry['note']).'</textarea></label>';
    if($owner)provider_subdomain_requests_ui_password();
    echo '<button class="btn outline" disabled>Reload to continue</button></form></section>';
}
function provider_subdomain_requests_ui_item(array $item,bool $owner,array $retry=[]): void {
    $id=$item['id'];$same=($retry['request_id']??null)===$id;
    echo '<article class="workspace-card" id="subdomain-request-'.esc($id).'" data-subdomain-request-history><h3 style="overflow-wrap:anywhere">'.esc($item['subdomain']).'</h3><p class="workspace-status">'.esc(provider_subdomain_requests_ui_label($item['state'])).'</p>';
    echo '<p style="overflow-wrap:anywhere">Parent website: <strong>'.esc($item['parent_domain']).'</strong></p><p class="hint">Requested '.esc(gmdate('Y-m-d H:i:s',$item['created_epoch'])).' UTC · Hosting package '.esc((string)$item['package_id']).'</p>';
    if($owner&&($item['client']??null)!==null)echo '<p style="overflow-wrap:anywhere" data-subdomain-request-client>Requested by <strong>'.esc($item['client']['name']).'</strong> · Client #'.esc((string)$item['client']['id']).'</p>';
    if($item['note']!=='')echo '<p style="white-space:pre-wrap;overflow-wrap:anywhere">'.esc($item['note']).'</p>';
    if(!$item['current_assignment'])echo '<p>The hosting assignment or connection changed. This saved request remains available for review.</p>';
    if($item['state']==='observed')echo '<p>A later check listed this subdomain. DNS, certificate and website availability have not been checked.</p>';
    elseif(in_array($item['state'],['dispatched','unknown','uncertain','interrupted'],true))echo '<p>The studio is reviewing the outcome. This status does not confirm that the subdomain is ready to use.</p>';
    if($item['last_check']!==null){
        $last=$item['last_check'];$label=match($last['disposition']){'observed'=>'Requested name reported','absent','different'=>'Requested name not reported',default=>'No usable result from the latest check'};
        echo '<p>'.esc($label).' · '.esc(gmdate('Y-m-d H:i:s',$last['recorded_epoch'])).' UTC</p>';
    }
    echo '<p><a class="btn outline" href="'.esc(provider_subdomain_requests_ui_url($owner,$id)).'">View this request</a></p>';
    if(!$owner&&$item['can_cancel']){
        $r=$same&&($retry['action']??'')==='subdomain_request_cancel'?$retry:[];
        provider_subdomain_requests_ui_form('subdomain_request_cancel','data-subdomain-request-cancel');
        foreach(['request_id'=>$id,'expected_event'=>$item['latest_event'],'event_id'=>bin2hex(random_bytes(16))] as $key=>$fallback)provider_subdomain_requests_ui_hidden($key,$r?($r[$key]??''):$fallback);
        $stale=$r&&($r['expected_event']??'')!==$item['latest_event'];
        if($stale)echo '<p class="notice">This request changed. Reload before withdrawing it.</p>';
        echo '<label class="check"><input type="checkbox" name="confirmed" value="1" required> Withdraw this request before studio approval.</label><button class="btn outline"'.($stale?' disabled':'').'>Withdraw request</button></form>';
    }
    if($same&&((!$owner&&!$item['can_cancel']&&($retry['action']??'')==='subdomain_request_cancel')||($owner&&!$item['can_decline']&&($retry['action']??'')==='provider_subdomain_request_decline')))provider_subdomain_requests_ui_unavailable_retry($retry,$owner);
    if($owner&&$item['can_decline']){
        $r=$same&&($retry['action']??'')==='provider_subdomain_request_decline'?$retry:[];
        echo '<details><summary>Decline this request</summary>';
        provider_subdomain_requests_ui_form('provider_subdomain_request_decline','data-subdomain-request-decline');
        foreach(['request_id'=>$id,'expected_event'=>$item['latest_event'],'event_id'=>bin2hex(random_bytes(16))] as $key=>$fallback)provider_subdomain_requests_ui_hidden($key,$r?($r[$key]??''):$fallback);
        echo '<label>Reason for the client<textarea name="note" rows="3" maxlength="1000" required>'.esc($r['note']??'').'</textarea></label><label class="check"><input type="checkbox" name="reviewed" value="1" required> Decline this request.</label>';
        provider_subdomain_requests_ui_password();
        echo '<button class="btn outline"'.($r&&($r['expected_event']??'')!==$item['latest_event']?' disabled':'').'>Decline request</button></form></details>';
    }
    if($owner&&($item['approval']!==null||($same&&($retry['action']??'')==='provider_subdomain_request_approve'))){
        $approval=$item['approval'];$r=$same&&($retry['action']??'')==='provider_subdomain_request_approve'?$retry:[];
        $matches=$approval!==null&&(!$r||(($r['expected_event']??'')===$item['latest_event']&&($r['request_sha256']??'')===$item['request_sha256']));
        echo '<section data-subdomain-request-review><h4>Review and add</h4><p>Approve adding this one subdomain to the assigned hosting package. The provider checks its allowance; a separate check is needed to observe the result.</p>';
        if($r)echo '<p class="hint">Your original approval is retained. If it expired or the hosting details changed, reload and review a new approval. Your password and confirmation are cleared.</p>';
        elseif($approval!==null)echo '<p class="hint">Website names checked '.esc(gmdate('Y-m-d H:i:s',$approval['observed_epoch'])).' UTC · Approval expires '.esc(gmdate('H:i:s',$approval['expires_epoch'])).' UTC.</p>';
        provider_subdomain_requests_ui_form('provider_subdomain_request_approve','data-subdomain-request-approve');
        foreach(['request_id'=>$id,'expected_event'=>$item['latest_event'],'request_sha256'=>$item['request_sha256'],'owner_request_id'=>$approval['request_id']??'','review_proof'=>$approval['review_proof']??''] as $key=>$fallback)provider_subdomain_requests_ui_hidden($key,$r?($r[$key]??''):$fallback);
        if(!$matches)echo '<p class="notice">This request needs a new review before approval.</p>';
        echo '<label class="check"><input type="checkbox" name="reviewed" value="1" required> I reviewed this parent website and approve adding the requested subdomain.</label>';
        provider_subdomain_requests_ui_password();
        echo '<button class="btn gold"'.(!$matches?' disabled':'').'>Approve and request subdomain</button></form></section>';
    }elseif($owner&&$item['state']==='pending')echo '<p>Review the current hosting assignment and website folders before approving this request.</p><a class="btn outline" href="'.esc(admin_url('provider-settings')).'#document-roots">Review website folders</a>';
    if($owner&&($item['dispatch']??null)!==null){
        $dispatch=$item['dispatch'];$r=$same&&in_array($retry['action']??'',['provider_subdomain_request_verify','provider_subdomain_request_recover'],true)?$retry:[];
        $action=!empty($dispatch['recovery_ledger_sha256'])?'provider_subdomain_request_recover':($dispatch['can_verify']?'provider_subdomain_request_verify':null);
        if($r&&$action!==$r['action'])provider_subdomain_requests_ui_unavailable_retry($r,true);
        if($action!==null){
            $retained=$r&&$action===$r['action']?$r:[];
            provider_subdomain_requests_ui_form($action,$action==='provider_subdomain_request_recover'?'data-subdomain-request-recover':'data-subdomain-request-verify');
            foreach(['request_id'=>$id,'owner_request_id'=>$dispatch['id']] as $key=>$fallback)provider_subdomain_requests_ui_hidden($key,$retained?($retained[$key]??''):$fallback);
            if($action==='provider_subdomain_request_recover'){
                provider_subdomain_requests_ui_hidden('ledger_sha256',$retained?($retained['ledger_sha256']??''):$dispatch['recovery_ledger_sha256']);
                echo '<p>Restore the saved outcome of the original attempt before checking reported names. This does not send the request again.</p><label class="check"><input type="checkbox" name="reviewed" value="1" required> Restore the local record of this saved attempt.</label>';
            }else echo '<p class="hint">Check the reported website names separately. This reads the provider once; it does not send the add request again.</p>';
            provider_subdomain_requests_ui_password();
            echo '<button class="btn outline">'.($action==='provider_subdomain_request_recover'?'Restore local request record':'Check reported website names').'</button></form>';
        }
    }elseif($owner&&$same&&in_array($retry['action']??'',['provider_subdomain_request_verify','provider_subdomain_request_recover'],true))provider_subdomain_requests_ui_unavailable_retry($retry,true);
    echo '</article>';
}
/** Safe detached screen only. Rendering performs no reads or writes. */
function provider_subdomain_requests_render(?array $screen,bool $owner,array $retry=[]): void {
    echo '<div class="subdomain-requests" data-subdomain-requests><p>'.($owner?'Review client requests, then approve each addition using the current hosting details.':'Ask the studio to add a subdomain beneath an explicitly assigned parent website. Submitting a request does not send a provider change.').'</p>';
    if($screen===null){
        echo '<p class="notice" role="alert">Request records are unavailable. Please try again later or contact the studio.</p>';
        provider_subdomain_requests_ui_unavailable_retry($retry,$owner);
    }else{
        if(!$screen['ready']){
            if($owner){provider_subdomain_requests_ui_form('provider_subdomain_request_prepare','data-subdomain-request-prepare');echo '<p>Enable client subdomain requests and their saved review history. Clients can ask for review; only the owner can approve provider changes.</p><label class="check"><input type="checkbox" name="reviewed" value="1" required> Enable client requests.</label>';provider_subdomain_requests_ui_password();echo '<button class="btn gold">Enable client requests</button></form>';}
            else echo '<p>The studio has not enabled subdomain requests yet.</p>';
        }
        $createRetry=!$owner&&($retry['action']??'')==='subdomain_request_create';
        if(!$owner&&($screen['ready']||$createRetry)){
            echo '<section><h2>Request a subdomain</h2><div class="workspace-grid">';
            if($createRetry){
                $selected=null;foreach($screen['options'] as $option)if($option['link_id']===($retry['link_id']??'')&&$option['parent_domain']===($retry['parent_domain']??''))$selected=$option;
                provider_subdomain_requests_ui_create($selected??['link_id'=>'','package_id'=>null,'parent_domain'=>'','review_sha256'=>''],$retry,$screen['ready']&&$selected!==null);
            }else{
                foreach($screen['options'] as $option)provider_subdomain_requests_ui_create($option);
                if(!$screen['options'])echo '<p>No assigned parent website is available for a new request. Ask the studio to review your hosting assignment.</p>';
            }
            echo '</div></section>';
        }
        echo '<section data-subdomain-request-history-list><h2>'.($owner?'Client requests':'Your requests').'</h2>';
        $found=false;foreach($screen['items'] as $item){if($item['id']===($retry['request_id']??null))$found=true;provider_subdomain_requests_ui_item($item,$owner,$retry);}
        if(!$screen['items'])echo '<p>No requests on this page.</p>';
        if($retry&&!$createRetry&&!$found&&$retry['action']!=='provider_subdomain_request_prepare')provider_subdomain_requests_ui_unavailable_retry($retry,$owner);
        echo '<nav class="button-row" aria-label="Subdomain request pages">';
        if($screen['page']>1)echo '<a class="btn outline" href="'.esc(provider_subdomain_requests_ui_url($owner,null,$screen['page']-1)).'">Newer requests</a>';
        if($screen['has_more']&&$screen['page']<10000)echo '<a class="btn outline" href="'.esc(provider_subdomain_requests_ui_url($owner,null,$screen['page']+1)).'">Older requests</a>';
        echo '</nav></section>';
    }
    echo '<p><a class="btn outline" href="'.esc(provider_subdomain_requests_ui_url($owner)).'">Reload request forms</a></p></div>';
}
