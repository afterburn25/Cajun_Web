<?php
/** Read-only purchase markup. Native readers own current authority and retained-history validation. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function hosting_purchases_ui_page(array $query): int {
    $page=$query['p']??'1';
    if (!is_string($page)||!preg_match('/\A[1-9][0-9]{0,5}\z/',$page)||(int)$page>100000) throw new InvalidArgumentException('Choose an available purchases page.');
    return (int)$page;
}
function hosting_purchases_ui_url(bool $owner,int $page=1): string {
    if ($page<1||$page>100000) throw new InvalidArgumentException('Choose an available purchases page.');
    return $owner?admin_url('hosting-purchases',['p'=>$page]):studio_url(['hosting_purchases'=>1,'p'=>$page]);
}
/** Fixed local destinations only. The native projection supplies these exact record identities. */
function hosting_purchases_ui_links(array $row,bool $owner): array {
    foreach (['request_id'=>$row['request_id']??null,'agreement_id'=>$row['agreement']['id']??null] as $id) {
        if (!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/',$id)!==1) throw new LogicException('Purchase record identity unavailable.');
    }
    foreach ([$row['invoice']['id']??null,$row['project']['id']??null] as $id) {
        if (!is_int($id)||$id<1||$id>2147483647) throw new LogicException('Purchase record identity unavailable.');
    }
    return [
        'agreement'=>$owner?admin_url('service-agreement',['record'=>$row['agreement']['id']]):studio_url(['agreement'=>$row['agreement']['id']]),
        'invoice'=>$owner?admin_url('invoice-payments',['invoice_id'=>$row['invoice']['id']]):studio_url(['invoice'=>$row['invoice']['id']]),
        'project'=>$owner?admin_url('studio-job',['id'=>$row['project']['id']]):studio_url(['job'=>$row['project']['id']]),
    ];
}
function hosting_purchases_ui_card(array $row,bool $owner): void {
    $links=hosting_purchases_ui_links($row,$owner);$order=$row['order'];$phase=$order['phase']??null;
    $agreement=match($row['agreement']['state']) {
        'pending-consent'=>'Awaiting agreement','active'=>'Active agreement','paused'=>'Paused agreement',
        'cancelled'=>'Ended agreement','abandoned'=>'Declined agreement',default=>'Agreement status unavailable',
    };
    $invoice=match($row['invoice']['status']) {
        'draft'=>'Draft','issued'=>'Awaiting payment','paid'=>'Paid','void'=>'Void',default=>'Status unavailable',
    };
    $setup=match($phase) {
        null=>$row['agreement']['state']==='active'?'Awaiting studio setup review':'No hosting setup offered','offered'=>'Website address needed','accepted'=>'Hosting request accepted',
        'cancelled'=>'Hosting request cancelled','declined'=>'Hosting offer declined','withdrawn'=>'Hosting offer withdrawn',default=>'Setup status unavailable',
    };
    $next=$owner?($order===null&&$row['agreement']['state']==='active'?'Review setup':'View setup details'):
        ($phase==='offered'&&$row['agreement']['state']==='active'?'Add website address':($phase==='accepted'?'View hosting progress':'View agreement'));
    echo '<article class="workspace-card" data-hosting-purchase="'.esc($row['request_id']).'" style="overflow-wrap:anywhere"><h2>'.esc($row['offer_title']).'</h2>';
    echo '<p class="hint">Purchased '.esc(gmdate('Y-m-d H:i:s',$row['created_epoch'])).' UTC</p><dl>';
    if ($owner) echo '<dt>Client</dt><dd>'.esc($row['client']['name']).'</dd>';
    echo '<dt>Project</dt><dd>'.esc($row['project']['title']).'</dd><dt>Original invoice</dt><dd>'.esc($row['invoice']['reference']).' · $'.esc(gmoney($row['invoice']['amount_cents'])).' '.esc($row['invoice']['currency']).' · '.esc($invoice).'</dd>';
    echo '<dt>Service agreement</dt><dd>'.esc($agreement).'</dd><dt>Hosting setup</dt><dd>'.esc($setup).'</dd>';
    if ($order!==null&&is_string($order['domain_name'])&&$order['domain_name']!=='') echo '<dt>Website address</dt><dd>'.esc($order['domain_name']).'</dd>';
    echo '</dl>';
    if ($phase==='accepted') echo '<p class="hint">Open hosting progress for payment, setup and service status.</p>';
    elseif ($phase==='offered'&&$row['agreement']['state']==='active') echo '<p>'.($owner?'The client can review this hosting offer and add their website address in the agreement.':'Review the hosting offer and confirm your website address in the agreement.').'</p>';
    elseif ($order===null) echo '<p>'.($row['agreement']['state']!=='active'?'Open the agreement for its current terms and status.':($owner?'Open the agreement to review the original invoice and offer hosting setup.':'The studio will review setup. You can view your invoice and agreement below.')).'</p>';
    echo '<div class="button-row"><a class="btn gold" data-hosting-purchase-next href="'.esc($links['agreement']).'">'.esc($next).'</a><a class="btn outline" href="'.esc($links['invoice']).'">View invoice</a><a class="btn outline" href="'.esc($links['project']).'">View project</a></div></article>';
}
/** A failed read never becomes an empty successful history. No data or action is inferred from POST. */
function hosting_purchases_ui_render(?array $page,bool $owner): void {
    echo '<div data-hosting-purchases="'.($owner?'owner':'client').'">';
    if ($page===null) {echo '<p class="notice error" role="alert">Hosting purchases are temporarily unavailable. Please try again later.</p></div>';return;}
    if (!$page['ready']) {echo '<section class="workspace-card"><h2>Hosting purchase history is not ready</h2><p>'.($owner?'Enable invoice checkout from Hosting offers to start recording purchases.':'The studio has not enabled hosting invoice checkout yet.').'</p></section></div>';return;}
    // Validate all link identities before any card is written, avoiding a partly rendered invalid page.
    foreach ($page['items'] as $row) hosting_purchases_ui_links($row,$owner);
    echo '<p>Follow each purchase through its original invoice and agreement. A confirmed hosting request does not mean the service is active.</p>';
    foreach ($page['items'] as $row) hosting_purchases_ui_card($row,$owner);
    if (!$page['items']) echo '<section class="workspace-card"><h2>No purchases on this page</h2><p>'.($owner?'Confirmed hosting checkouts will appear here.':'Your confirmed hosting checkouts will appear here.').'</p></section>';
    echo '<nav class="button-row" aria-label="Hosting purchase pages">';
    if ($page['page']>1) echo '<a class="btn outline" rel="prev" href="'.esc(hosting_purchases_ui_url($owner,$page['page']-1)).'">Previous</a>';
    if ($page['has_more']&&$page['page']<100000) echo '<a class="btn outline" rel="next" href="'.esc(hosting_purchases_ui_url($owner,$page['page']+1)).'">Next</a>';
    echo '</nav></div>';
}
