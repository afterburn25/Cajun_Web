<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
echo '<nav class="studio-tabs" aria-label="Hosting">';
foreach (['offers'=>'Available plans','requests'=>'My requests'] as $key=>$label) echo '<a href="'.esc(studio_url(['hosting'=>$key])).'"'.($query['tab']===$key?' aria-current="page"':'').'>'.esc($label).'</a>';
echo '<a href="'.esc(studio_url(['hosting_purchases'=>1])).'">My hosting purchases</a></nav>';
if ($unavailable||($page['unavailable']??false)) echo '<p class="notice">Some hosting information is temporarily unavailable. Please try again later or contact the studio.</p>';
$attemptShown=false;
if ($query['tab']==='offers') {
    echo '<p class="hint">A request does not take payment or activate hosting. The studio will review your request before offering a service agreement.</p>';
    foreach ($page['offers'] as $offer) {
        echo '<article class="workspace-card">';hosting_catalog_card($offer,'h2');
        if (isset($offer['checkout_policy'])) {
            echo '<p><a class="btn gold" href="'.esc(studio_url(['hosting'=>'checkout','offer'=>$offer['publication_id'],'offer_sha256'=>$offer['publication_sha256']])).'">Review plan and first invoice</a></p></article>';
            continue;
        }
        if ($attempt&&$attempt['action']==='hosting_catalog_request'&&$attempt['publication_id']===$offer['publication_id']) {
            $values=$attempt;$attemptShown=true;$current=hash_equals($offer['publication_sha256'],$attempt['publication_sha256']);
            if (!$current) echo '<p class="notice warning">These details changed. Reload and review the plan before trying again.</p>';
        } else {$values=['action'=>'hosting_catalog_request','request_id'=>bin2hex(random_bytes(16)),'publication_id'=>$offer['publication_id'],'publication_sha256'=>$offer['publication_sha256']];$current=true;}
        hosting_catalog_portal_form($values,$current,$query['page']);echo '</article>';
    }
    if (!$page['offers']&&!$unavailable&&!($page['unavailable']??false)) echo '<section class="workspace-card"><h2>No plans on this page</h2><p>Contact the studio to discuss hosting for your website.</p></section>';
} else {
    foreach ($page['requests'] as $request) {
        echo '<article class="workspace-card">';hosting_catalog_card($request['offer'],'h2');
        $status=match($request['status']) {'pending'=>'Awaiting studio review','offered'=>'Service agreement offered','withdrawn'=>'Request withdrawn','declined'=>'Request declined',default=>'Status unavailable'};
        echo '<p class="workspace-status">'.esc($status).'</p><p class="hint">Requested '.esc(gmdate('Y-m-d H:i:s',$request['created_at'])).' UTC. Terms saved when you requested this plan.</p>';
        if ($request['reason']!=='') echo '<p>'.nl2br(esc($request['reason'])).'</p>';
        if ($request['status']==='pending') {
            if ($attempt&&$attempt['action']==='hosting_catalog_withdraw_request'&&$attempt['request_id']===$request['id']) {$values=$attempt;$attemptShown=true;$current=hash_equals($request['sha256'],$attempt['request_sha256']);}
            else {$values=['action'=>'hosting_catalog_withdraw_request','decision_id'=>bin2hex(random_bytes(16)),'request_id'=>$request['id'],'request_sha256'=>$request['sha256'],'reason'=>''];$current=true;}
            if (!$current) echo '<p class="notice warning">These details changed. Reload and review the request before trying again.</p>';
            hosting_catalog_portal_form($values,$current,$query['page']);
        } elseif ($request['status']==='offered') {
            echo '<p>Open the linked agreement for its current status, consent and next steps.</p><div class="button-row">';
            if (is_string($request['agreement_id'])&&preg_match('/\A[a-f0-9]{32}\z/',$request['agreement_id'])) echo '<a class="btn gold" href="'.esc(studio_url(['agreement'=>$request['agreement_id']])).'">Review agreement</a>';
            if (is_int($request['job_id'])&&$request['job_id']>0) echo '<a class="btn outline" href="'.esc(studio_url(['job'=>$request['job_id']])).'">View project</a>';
            echo '</div>';
        }
        echo '</article>';
    }
    if (!$page['requests']&&!$unavailable) echo '<section class="workspace-card"><h2>No requests on this page</h2><p>Your submitted hosting requests and the studio’s responses will appear here.</p></section>';
}
if ($attempt&&!$attemptShown) {
    echo '<section class="workspace-card"><h2>Earlier submission</h2><p class="notice warning">These details changed. Reload and review the plan or request before trying again.</p>';
    hosting_catalog_portal_form($attempt,false,$query['page']);echo '</section>';
}
echo '<nav class="button-row" aria-label="Hosting pages">';
if ($query['page']>1) echo '<a class="btn outline" href="'.esc(studio_url(['hosting'=>$query['tab'],'p'=>$query['page']-1])).'">Previous</a>';
if ($page['has_more']) echo '<a class="btn outline" href="'.esc(studio_url(['hosting'=>$query['tab'],'p'=>$query['page']+1])).'">Next</a>';
echo '</nav>';
