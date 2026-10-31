<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
echo '<p>Publish hosting offers from your imported drafts and reviewed service plans. Choose studio-reviewed requests or a client confirmation that creates an unpaid first invoice. Invoice checkout requires verified payment and a separate owner approval before hosting setup.</p>';
echo '<div class="button-row"><a class="btn outline" href="'.esc(admin_url('hosting-offers')).'">Offers</a><a class="btn outline" href="'.esc(admin_url('hosting-offers',['tab'=>'requests'])).'">Client requests</a><a class="btn outline" href="'.esc(admin_url('hosting-purchases')).'">Hosting purchases</a><a class="btn outline" href="'.esc(admin_url('catalog')).'">Edit products and images</a><a class="btn outline" href="'.esc(admin_url('service-plans')).'">Service plans</a></div>';
if (!$data['ready']) {
    echo '<section class="workspace-card"><h2>Enable hosting offers</h2><p>Prepare the offer and request history after setting up your product catalog, service plans and hosting mappings. Existing products stay as drafts.</p><form method="post">';
    hosting_catalog_admin_fields('hosting_catalog_setup',[]);
    hosting_catalog_admin_confirmation('I confirm that hosting offer history should be enabled.');
    echo '<button class="btn gold">Enable hosting offers</button></form></section>';
    if ($setup['retry']) hosting_catalog_setup_form($setup);
    return;
}
if ($data['tab']==='requests') {
    echo '<section class="workspace-card"><h2>Client requests</h2><form method="get"><input type="hidden" name="view" value="hosting-offers"><input type="hidden" name="tab" value="requests">';
    admin_input('search','Find requests by client name',$data['search'],'search','maxlength="120"');
    echo '<button class="btn outline">Find requests</button></form>';
    if (!$data['requests']) echo '<p>No requests on this page.</p>';
    foreach ($data['requests'] as $item) {
        echo '<article><h3>'.esc($item['client']['name']).' · '.esc($item['offer']['title']).'</h3><p>'.esc(ucfirst($item['status'])).' · '.esc(gmdate('Y-m-d H:i',$item['created_at'])).' UTC</p><a class="btn outline" href="'.esc(admin_url('hosting-offers',['tab'=>'requests','request_id'=>$item['id']])).'">Review request</a></article>';
    }
    hosting_catalog_admin_pagination($data['page'],$data['has_more'],['tab'=>'requests','search'=>$data['search']]);echo '</section>';
    if (!$data['request']) return;
    $item=$data['request'];
    echo '<section class="workspace-card"><h2>Request from '.esc($item['client']['name']).'</h2><p>'.esc(gmdate('Y-m-d H:i',$item['created_at'])).' UTC · '.esc(ucfirst($item['status'])).'</p>';
    hosting_catalog_card($item['offer']);
    if ($item['reason']!=='') echo '<p><strong>Reason:</strong> '.nl2br(esc($item['reason'])).'</p>';
    if ($item['agreement_id']!==null) echo '<p><a class="btn outline" href="'.esc(admin_url('service-agreement',['record'=>$item['agreement_id']])).'">Review service agreement</a></p>';
    if ($item['status']!=='pending') {echo '<p>This request has already been handled. Later changes belong to its agreement.</p></section>';return;}
    if (!$item['client']['active']) echo '<p class="notice warning">This client account is inactive. You can decline the request, but cannot offer a new agreement.</p>';
    echo '<h3>Offer an agreement</h3><p>Choose a project belonging to this client. The client must agree to the saved terms before activation. Payment authorization and hosting setup remain separate.</p><form method="get"><input type="hidden" name="view" value="hosting-offers"><input type="hidden" name="tab" value="requests"><input type="hidden" name="request_id" value="'.esc($item['id']).'">';
    admin_input('job_search','Find this client’s project',$data['job_search'],'search','maxlength="160"');
    echo '<button class="btn outline">Find projects</button></form>';
    hosting_catalog_admin_pagination($data['job_page'],$data['jobs_more'],['tab'=>'requests','request_id'=>$item['id'],'job_search'=>$data['job_search']],'job_page');
    $convert=hosting_catalog_admin_retry('hosting_catalog_convert',['decision_id'=>bin2hex(random_bytes(16)),'request_id'=>$item['id'],'request_sha256'=>$item['sha256'],'job_id'=>'']);
    echo '<form method="post">';
    hosting_catalog_admin_fields('hosting_catalog_convert',array_diff_key($convert,['job_id'=>true]));
    $options=[''=>'Choose this client’s project'];foreach ($data['jobs'] as $job) $options[(string)$job['id']]=$job['title'].' · Project '.$job['id'];
    if ($convert['job_id']!==''&&!array_key_exists($convert['job_id'],$options)) $options[$convert['job_id']]='Previously selected project · review required';
    admin_select('job_id','Client project',$options,$convert['job_id']);
    if (!$data['jobs']) echo '<p>No matching project on this page. Create one in the client workspace or change the search.</p>';
    hosting_catalog_admin_confirmation('I confirm this client, project and the exact terms shown above.');
    echo '<button class="btn gold"'.(!$item['client']['active']?' disabled':'').'>Offer agreement</button></form>';
    echo '<h3>Decline this request</h3><p>The reason will be visible to the client.</p><form method="post">';
    $decline=hosting_catalog_admin_retry('hosting_catalog_decline',['decision_id'=>bin2hex(random_bytes(16)),'request_id'=>$item['id'],'request_sha256'=>$item['sha256'],'reason'=>'']);
    hosting_catalog_admin_fields('hosting_catalog_decline',array_diff_key($decline,['reason'=>true]));
    admin_textarea('reason','Reason',$decline['reason'],3,1000);
    hosting_catalog_admin_confirmation('I confirm that this request should be declined.');
    echo '<button class="btn outline">Decline request</button></form></section>';return;
}
echo '<section class="workspace-card"><h2>Enable invoice checkout</h2><p>After service plans, recurring billing and payments are prepared, enable client confirmation and first-invoice history. Existing offers keep their current behavior. You will choose invoice checkout separately for each new offer.</p><form method="post">';
hosting_catalog_admin_fields('hosting_catalog_checkout_setup',[]);
hosting_catalog_admin_confirmation('Enable or verify invoice checkout while preserving existing records.');
echo '<button class="btn outline">Enable or verify invoice checkout</button></form></section>';
echo '<section class="workspace-card"><h2>Imported hosting drafts</h2><p>Set a price, description and image in Products first. The matching active plan must use the same name, USD price and monthly or yearly period, without a separate setup fee.</p><form method="get"><input type="hidden" name="view" value="hosting-offers">';
admin_input('search','Find an imported draft by name or SKU',$data['search'],'search','maxlength="120"');
echo '<button class="btn outline">Find drafts</button></form>';
if (!$data['products']) echo '<p>No imported hosting drafts on this page. Import hosting products from the saved 20i catalog in Products.</p>';
foreach ($data['products'] as $item) {
    echo '<article><h3>'.esc($item['title']).'</h3><p>'.esc($item['sku']).' · '.($item['amount_cents']===null?'Price not set':'$'.esc(gmoney($item['amount_cents'])).' '.esc($item['currency'])).'</p><a class="btn outline" href="'.esc(admin_url('hosting-offers',['product_id'=>$item['id']])).'">Choose this draft</a></article>';
}
hosting_catalog_admin_pagination($data['page'],$data['has_more'],['search'=>$data['search']]);echo '</section>';
if (!$data['product']) {if ($setup['retry']) hosting_catalog_setup_form($setup);return;}
$product=$data['product'];
hosting_catalog_setup_form($setup);
echo '<section class="workspace-card"><h2>Prepare '.esc($product['title']).'</h2><p>Choose an active plan. Its hosting mapping and the latest saved 20i catalog are checked when you preview.</p><form method="get"><input type="hidden" name="view" value="hosting-offers"><input type="hidden" name="product_id" value="'.(int)$product['id'].'">';
admin_input('plan_search','Find a matching plan by saved text or plan ID',$data['plan_search'],'search','maxlength="120"');
echo '<button class="btn outline">Find plans</button></form>';
hosting_catalog_admin_pagination($data['plan_page'],$data['plans_more'],['product_id'=>$product['id'],'plan_search'=>$data['plan_search']],'plan_page');
if ($data['plans_more']) echo '<p class="hint">More saved plans may match on the next page.</p>';
if ($data['plan_notice']!=='') echo '<p class="notice warning" role="alert">'.esc($data['plan_notice']).'</p>';
$publicationId=in_array($values['action'],['hosting_catalog_preview','hosting_catalog_publish'],true)?$values['publication_id']:bin2hex(random_bytes(16));
$selectedPlan=in_array($values['action'],['hosting_catalog_preview','hosting_catalog_publish'],true)?$values['plan_id']:$data['selected_plan_id'];
$selectedProduct=in_array($values['action'],['hosting_catalog_preview','hosting_catalog_publish'],true)?$values['product_id']:(string)$product['id'];
echo '<form method="post">';
hosting_catalog_admin_fields('hosting_catalog_preview',['publication_id'=>$publicationId,'product_id'=>$selectedProduct]);
$options=[''=>'Choose a matching plan'];
foreach ($data['plans'] as $plan) $options[$plan['id']]=$plan['name'].' · Version '.$plan['revision'].' · '.gmdate('Y-m-d H:i',$plan['created_at']).' UTC';
if ($selectedPlan!==''&&!array_key_exists($selectedPlan,$options)) $options[$selectedPlan]='Previously selected plan · review required';
admin_select('plan_id','Active service plan',$options,$selectedPlan);
if (!$data['plans']) echo '<p>No matching active plan on this page. Review the product price and service plan terms, or try the next page.</p>';
$retryPublication=in_array($values['action'],['hosting_catalog_preview','hosting_catalog_publish'],true);
$checkoutMode=$retryPublication?(array_key_exists('checkout_mode',$_POST)?$values['checkout_mode']:'request-only'):'request-only';
$billingTimezone=$retryPublication?$values['billing_timezone']:'America/Chicago';
$billingAlignment=$retryPublication?$values['billing_alignment']:'day-of-month';
admin_select('checkout_mode','How clients start',['request-only'=>'Request a studio review','invoice-on-confirmation'=>'Confirm terms and create the first invoice'],$checkoutMode);
admin_input('billing_timezone','Invoice checkout billing timezone',$billingTimezone,'text','maxlength="64" placeholder="America/Chicago"');
admin_select('billing_alignment','Recurring billing date',['day-of-month'=>'Same day each month or year','month-end'=>'Month end · confirmation only on the last day of a month'],$billingAlignment);
echo '<p class="hint">Invoice checkout starts a full first period on the confirmation date in this timezone. Month-end offers can only be confirmed on the last day of a month. The first invoice is due that day. Reviews last up to 15 minutes and expire when the date changes. Payment and hosting activation are separate steps.</p>';
echo '<button class="btn gold">Preview offer</button></form></section>';
if ($previewError!=='') echo '<div class="notice warning" role="alert">'.esc($previewError).' <a href="'.esc(admin_url('hosting-offers',['product_id'=>$product['id']])).'">Start a new review</a></div>';
if ($preview!==null||$values['action']==='hosting_catalog_publish') {
    echo '<section class="workspace-card"><h2>Review before publishing</h2>';
    if ($preview!==null) hosting_catalog_card($preview['offer']);
    else echo '<p>The previously reviewed card is no longer available for publishing. Your submitted review remains unchanged below.</p>';
    $publish=['publication_id'=>$publicationId,'product_id'=>$selectedProduct,'plan_id'=>$selectedPlan,'review_sha256'=>$preview['review_sha256']??'','checkout_mode'=>$checkoutMode,'billing_timezone'=>$billingTimezone,'billing_alignment'=>$billingAlignment];
    $publish=hosting_catalog_admin_retry('hosting_catalog_publish',$publish);
    echo '<p>Publishing makes the reviewed offer and its chosen starting method available to clients. A later edit requires a new review. Existing agreements keep their original terms.</p><form method="post">';
    hosting_catalog_admin_fields('hosting_catalog_publish',$publish);
    hosting_catalog_admin_confirmation('I have reviewed this public card and confirm publication.');
    echo '<button class="btn gold"'.($preview===null?' disabled':'').'>Publish hosting offer</button></form></section>';
}
$withdrawalOffer=$data['withdrawal_offer']??($data['latest']['offer']??null);
if ($withdrawalOffer!==null) {
    echo '<section class="workspace-card"><h2>Published offer</h2><p>These are the saved published terms. Availability for new requests is checked against the current product and hosting review.</p>';
    hosting_catalog_card($withdrawalOffer);
    $retry=$values['action']==='hosting_catalog_withdraw';
    if (($data['latest']['withdrawn']??false)&&!$retry) echo '<p>This offer has been withdrawn.</p>';
    else {
        if ($retry) echo '<p>Your original withdrawal selection is preserved. This action will not withdraw a different or newer offer.</p>';
        echo '<p>Withdrawal blocks new requests. It does not cancel existing client agreements.</p><form method="post">';
        $withdraw=hosting_catalog_admin_retry('hosting_catalog_withdraw',['withdrawal_id'=>bin2hex(random_bytes(16)),'publication_id'=>$withdrawalOffer['publication_id'],'publication_sha256'=>$withdrawalOffer['publication_sha256'],'reason'=>'']);
        hosting_catalog_admin_fields('hosting_catalog_withdraw',array_diff_key($withdraw,['reason'=>true]));
        admin_textarea('reason','Why are you withdrawing this offer?',$withdraw['reason'],3,500);
        hosting_catalog_admin_confirmation('I confirm that this saved offer should be withdrawn.');
        echo '<button class="btn outline">Withdraw offer</button></form>';
    }
    echo '</section>';
}
