<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
if ($result!==null) {
    echo '<section class="workspace-card"><h2>Your hosting invoice</h2><p>Invoice '.esc($result['invoice_reference']).' is saved for $'.esc(gmoney($result['amount_cents'])).' '.esc($result['currency']).'.</p><p>Invoice status: '.esc(ucfirst($result['invoice_status'])).'. Hosting setup follows verified payment and owner approval.</p><div class="button-row">';
    foreach (['View invoice'=>['invoice'=>$result['invoice_id']],'View project'=>['job'=>$result['project_id']],'Service agreement'=>['agreement'=>$result['agreement_id']],'Billing schedule'=>['recurring'=>1,'job'=>$result['project_id'],'schedule'=>$result['schedule_id']]] as $label=>$target) echo '<a class="btn outline" href="'.esc(studio_url($target)).'">'.esc($label).'</a>';
    echo '<a class="btn outline" href="'.esc(studio_url(['hosting_purchases'=>1])).'">My hosting purchases</a></div></section>';return;
}
if ($review!==null) {
    $b=$review['billing'];$p=$b['first_period'];
    $last=(new DateTimeImmutable($p['end'],new DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');
    echo '<section class="workspace-card"><h2>Confirm your plan and first invoice</h2>';hosting_catalog_card($review['offer'],'h3');
    echo '<dl><dt>Project</dt><dd>'.($review['project']['mode']==='existing'?'Existing project #'.esc((string)$review['project']['id']):'A new dedicated hosting project').'</dd><dt>First billing period</dt><dd>'.esc($p['start']).' through '.esc($last).'</dd><dt>First invoice due</dt><dd>'.esc($p['due']).'</dd><dt>Next billing period starts</dt><dd>'.esc($p['next_start']).'</dd><dt>Billing timezone</dt><dd>'.esc($b['timezone']).'</dd></dl>';
    echo '<p>Confirming saves your service agreement, starts the displayed billing period and creates an unpaid invoice for the full first period. Pay through the invoice. Hosting setup follows verified payment and owner approval.</p>';
    $expires=(new DateTimeImmutable('@'.$review['expires_at']))->setTimezone(new DateTimeZone($b['timezone']))->format('Y-m-d H:i:s T');
    echo '<p class="hint">Review valid until '.esc($expires).'. If the terms or billing date change, review them again.</p><form method="post" action="'.esc(studio_url(['hosting'=>'checkout'])).'">'.csrf_field();
    hosting_checkout_portal_hidden(['action'=>'hosting_catalog_checkout_confirm','request_id'=>$review['request_id'],'review_sha256'=>$review['review_sha256']]);
    echo '<label class="check"><input type="checkbox" name="confirm" value="1" required>I agree to this plan, its recurring billing dates and the full first-period invoice of $'.esc(gmoney($b['amount_cents'])).' '.esc($b['currency']).'.</label><button class="btn gold">Agree and create first invoice</button></form></section>';return;
}
if ($unavailable) {
    if ($attempt!==null) {
        echo '<section class="workspace-card"><h2>Submitted selection retained</h2><p>Review is required before this submission can continue. Your original selection has not been replaced.</p><form method="post">';
        hosting_checkout_portal_hidden($attempt);echo '<button class="btn outline" disabled>Review required</button></form></section>';
    }
    echo '<p><a class="btn outline" href="'.esc(studio_url(['hosting'=>'offers'])).'">Return to available plans</a></p>';return;
}
$offer=$data['offer'];$context=['hosting'=>'checkout','offer'=>$values['publication_id'],'offer_sha256'=>$values['publication_sha256'],'project_search'=>$data['search']];
echo '<section class="workspace-card">';hosting_catalog_card($offer,'h2');echo '<h3>Choose the project</h3><p>Use an existing website project or create a dedicated project for this hosting service.</p><form method="get">';
foreach(['hosting'=>'checkout','offer'=>$values['publication_id'],'offer_sha256'=>$values['publication_sha256']] as $key=>$value)echo '<input type="hidden" name="'.esc($key).'" value="'.esc($value).'">';
echo '<label>Find an existing project<input type="search" name="project_search" maxlength="160" value="'.esc($data['search']).'"></label><button class="btn outline">Find projects</button></form><div class="button-row">';
if($data['page']>1)echo '<a class="btn outline" href="'.esc(studio_url($context+['p'=>$data['page']-1])).'">Previous projects</a>';
if($data['has_more'])echo '<a class="btn outline" href="'.esc(studio_url($context+['p'=>$data['page']+1])).'">More projects</a>';
echo '</div><form method="post" action="'.esc(studio_url($context+['p'=>$data['page']])).'">'.csrf_field();
hosting_checkout_portal_hidden(['action'=>'hosting_catalog_checkout_preview','request_id'=>$values['request_id'],'publication_id'=>$values['publication_id'],'publication_sha256'=>$values['publication_sha256']]);
echo '<label>Project choice<select name="project_mode" required>';
foreach(['new-hosting'=>'Create a dedicated hosting project','existing'=>'Use an existing project'] as $key=>$label)echo '<option value="'.esc($key).'"'.($values['project_mode']===$key?' selected':'').'>'.esc($label).'</option>';
if(!in_array($values['project_mode'],['new-hosting','existing'],true))echo '<option value="'.esc($values['project_mode']).'" selected disabled>Review your project choice</option>';
echo '</select></label><label>Existing project<select name="project_id"><option value="">Choose an existing project</option>';$found=false;
foreach($data['projects'] as $project){$selected=$values['project_id']===(string)$project['id'];$found=$found||$selected;echo '<option value="'.(int)$project['id'].'"'.($selected?' selected':'').'>'.esc($project['title']).' · #'.(int)$project['id'].'</option>';}
if($values['project_id']!==''&&!$found)echo '<option value="'.esc($values['project_id']).'" selected disabled>Previously selected project · review required</option>';
echo '</select></label><p class="hint">This step saves a review only. Your invoice is created after you agree to the displayed terms.</p><button class="btn gold">Review plan and billing dates</button></form></section>';
