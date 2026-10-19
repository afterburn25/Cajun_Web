<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/subdomain_add.php';

function provider_subdomain_add_label(string $status): string {
    return match ($status) {
        'observed' => 'Subdomain appeared in a later website-folder check',
        'not_sent' => 'Request stopped before provider dispatch',
        'interrupted' => 'Request interrupted; check its saved history',
        default => 'Subdomain requested; result is not yet confirmed',
    };
}

/** Render only the detached review. Viewing a form never approves or sends it. */
function provider_subdomain_add_render_review(array $review): void {
    if (!$review['ready']) {
        $reason=match($review['reason']){
            'not_prepared'=>'Enable subdomain requests first.',
            'unresolved'=>'A previous request remains unresolved. Review its history below.',
            'no_attached_parent'=>'No eligible parent website is recorded for this active hosting package.',
            default=>'Refresh the assigned hosting inventory and website folders, then review a new request.',
        };
        echo '<p class="hint">'.esc($reason).'</p><a class="btn outline" href="#document-roots">Review website folders</a>';
        if(($_POST['action']??null)==='provider_inventory_subdomain_add'&&($_POST['link_id']??null)===$review['link_id']){
            echo '<div data-subdomain-retained><p>Your entered names are retained below while the hosting details need review.</p>';
            foreach(['parent_domain'=>'Entered parent website','subdomain'=>'Entered subdomain'] as $key=>$label){
                $value=is_string($_POST[$key]??null)?substr($_POST[$key],0,253):'';
                echo '<label>'.esc($label).'<input type="text" value="'.esc($value).'" readonly></label>';
            }
            echo '</div>';
        }
        return;
    }
    $requestId=$review['request_id'];$proof=$review['review_proof'];
    $parent=$review['parent_domains'][0]??'';$name='';
    $resubmission=($_POST['action']??null)==='provider_inventory_subdomain_add'
        &&($_POST['link_id']??null)===$review['link_id'];
    if ($resubmission) {
        // A failed POST keeps its original approval. It cannot obtain a new nonce/proof.
        $requestId=is_string($_POST['request_id']??null)&&preg_match('/\A[a-f0-9]{32}\z/',$_POST['request_id'])?$_POST['request_id']:'';
        $proof=is_string($_POST['review_proof']??null)&&strlen($_POST['review_proof'])<=4096?$_POST['review_proof']:'';
        $parent=is_string($_POST['parent_domain']??null)?substr($_POST['parent_domain'],0,253):'';
        $name=is_string($_POST['subdomain']??null)?substr($_POST['subdomain'],0,253):'';
    }
    echo '<form method="post" data-subdomain-add-form>';
    admin_action('provider_inventory_subdomain_add');
    foreach (['request_id'=>$requestId,'link_id'=>$review['link_id'],'review_proof'=>$proof] as $key=>$value) {
        echo '<input type="hidden" name="'.$key.'" value="'.esc($value).'">';
    }
    echo '<label>Attached parent website<select name="parent_domain" required>';
    if (!in_array($parent,$review['parent_domains'],true)) echo '<option value="'.esc($parent).'" selected>'.esc($parent===''?'Select a parent website':$parent.' — review required').'</option>';
    foreach ($review['parent_domains'] as $option) echo '<option value="'.esc($option).'"'.($option===$parent?' selected':'').'>'.esc($option).'</option>';
    echo '</select></label>';
    admin_input('subdomain','Full subdomain name',$name,'text','required maxlength="253" autocomplete="off" spellcheck="false" placeholder="shop.example.com"');
    echo '<p class="hint">Enter one name immediately below the selected website, such as shop.example.com under example.com. 20i checks the package’s subdomain allowance.</p>';
    if ($resubmission) echo '<p class="hint">Your original request details are retained. If the review expired or the hosting details changed, reload this page and review a new request.</p>';
    else echo '<p class="hint">This review expires at '.esc(gmdate('H:i:s',(int)$review['expires_epoch'])).' UTC.</p>';
    echo '<label class="check"><input type="checkbox" name="confirmed" value="1" required> I approve adding this subdomain to this hosting package.</label>';
    admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');
    echo '<button class="btn gold">Request subdomain</button></form>';
}

function provider_subdomain_add_render_history(array $item): void {
    echo '<article class="workspace-card" data-subdomain-add-request><h3>'.esc($item['subdomain']).'</h3><p>'.esc(provider_subdomain_add_label($item['status'])).'</p><p class="hint">Parent: '.esc($item['parent_domain']).' · Requested '.esc($item['created_at']).' UTC</p>';
    if(isset($item['package_id']))echo '<p class="hint">Hosting package '.esc((string)$item['package_id']).'</p>';
    if ($item['status']==='observed') echo '<p>A later provider read listed this name. DNS, certificate and website availability have not been checked.</p>';
    elseif ($item['status']!=='not_sent') echo '<p>Check the reported website names before taking further action. A missing name or failed check does not establish that the original request was unsent.</p>';
    $last=$item['last_check']??null;
    if ($last) echo '<p class="hint">Last check: '.esc(gmdate('Y-m-d H:i:s',(int)$last['recorded_epoch'])).' UTC · '.esc(match($last['disposition']){'observed'=>'Name reported', 'different','absent'=>'Name not reported', default=>'No usable result'}).'</p>';
    if (!empty($item['recovery_ledger_sha256'])) {
        echo '<form method="post" data-subdomain-add-recover>';admin_action('provider_inventory_subdomain_recover');
        echo '<input type="hidden" name="request_id" value="'.esc($item['id']).'"><input type="hidden" name="ledger_sha256" value="'.esc($item['recovery_ledger_sha256']).'">';
        echo '<p>A finished attempt has a missing local completion record. Restore that record before checking the reported website names.</p><label class="check"><input type="checkbox" name="confirmed" value="1" required> I reviewed this saved attempt. Restore its local record without sending another request.</label>';
        admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');
        echo '<button class="btn outline">Restore local request record</button></form>';
    } elseif ($item['can_verify']) {
        echo '<form method="post" data-subdomain-add-verify>';admin_action('provider_inventory_subdomain_verify');
        echo '<input type="hidden" name="request_id" value="'.esc($item['id']).'">';
        admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');
        echo '<button class="btn outline">Check reported website names</button><p class="hint">One new read, subject to the existing refresh limits. This does not send the add request again.</p></form>';
    }
    echo '</article>';
}

function provider_subdomain_add_admin_render(): void {
    provider_settings_manager();
    echo '<section class="workspace-card" id="subdomain-requests"><h2>Add a subdomain</h2><p>Add a name beneath a website already attached to an assigned hosting package. Review the saved website folders, send one request, then check the reported names.</p>';
    try {
        if (!provider_document_roots_ready()||!provider_operations_ready()) {
            echo '<p>Enable website folder observations and provider operations first.</p><a class="btn outline" href="#document-roots">Website folders</a> <a class="btn outline" href="'.esc(admin_url('provider-operations')).'">Provider operations</a></section>';return;
        }
        $status=provider_subdomain_add_status();
        if (!$status['ready']) {
            echo '<form method="post" data-subdomain-add-prepare>';admin_action('provider_inventory_subdomain_prepare');
            admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');
            echo '<button class="btn gold">Enable subdomain requests</button></form></section>';return;
        }
        $page=max(1,min(10000,(int)($_GET['subdomain_page']??1)));
        $links=all('SELECT l.id,l.package_id,c.name AS client_name FROM '.table('provider_package_links').' l JOIN '.table('studio_clients').' c ON c.id=l.client_id ORDER BY l.id LIMIT 21 OFFSET '.(($page-1)*20));
        if (!$links) echo '<p>Assign a hosting package to a client to add a subdomain.</p>';
        foreach (array_slice($links,0,20) as $link) {
            echo '<article class="workspace-card" data-subdomain-add-package><h3>'.esc($link['client_name']).' · Package '.esc((string)$link['package_id']).'</h3>';
            try {provider_subdomain_add_render_review(provider_subdomain_add_review($link['id']));}
            catch (InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;echo '<p class="notice error">'.esc($e->getMessage()).'</p>';}
            echo '</article>';
        }
        echo '<nav class="button-row" aria-label="Subdomain hosting packages">';
        if ($page>1) echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['subdomain_page'=>$page-1])).'#subdomain-requests">Previous packages</a>';
        if (count($links)>20&&$page<10000) echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['subdomain_page'=>$page+1])).'#subdomain-requests">More packages</a>';
        echo '</nav><h3>Subdomain request history</h3>';
        $historyPage=max(1,min(10000,(int)($_GET['subdomain_history_page']??1)));
        $history=provider_subdomain_add_history(null,$historyPage);
        if (!$history['items']) echo '<p>No requests on this page.</p>';
        foreach ($history['items'] as $item) provider_subdomain_add_render_history($item);
        echo '<nav class="button-row" aria-label="Subdomain request history">';
        if ($historyPage>1) echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['subdomain_history_page'=>$historyPage-1])).'#subdomain-requests">Newer requests</a>';
        if ($history['has_more']&&$historyPage<10000) echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['subdomain_history_page'=>$historyPage+1])).'#subdomain-requests">Older requests</a>';
        echo '</nav>';
    } catch (InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;echo '<p class="notice error">'.esc($e->getMessage()).'</p>';}
    echo '</section>';
}
