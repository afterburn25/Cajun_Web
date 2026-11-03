<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/directory_listing_requests.php';

/** Only local, finite navigation fields are carried through a form submission. */
function provider_directory_indexing_requests_route(): array {
    $page = $_GET['directory_page'] ?? '1';
    if (!is_string($page) || !preg_match('/\A[1-9][0-9]{0,2}\z/', $page) || (int)$page > 500) $page = '1';
    $id = $_GET['request'] ?? null;
    if (!is_string($id) || !preg_match('/\A[a-f0-9]{32}\z/', $id)) $id = null;
    return ['page'=>(int)$page, 'request'=>$id];
}
function provider_directory_indexing_requests_url(bool $owner, array $route, ?string $id = null): string {
    $query = ['directory_page'=>$route['page']];
    if ($id !== null) $query['request'] = $id;
    return $owner ? admin_url('directory-requests', $query) : studio_url(['directory_requests'=>1] + $query);
}
function provider_directory_indexing_requests_post(string $name, int $max = 128): string {
    $value = $_POST[$name] ?? '';
    if (!is_string($value) || strlen($value) > $max) throw new InvalidArgumentException('Reload the request and review its current details.');
    return $value;
}
function provider_directory_indexing_requests_hidden(string $name, string $value): void {
    echo '<input type="hidden" name="'.esc($name).'" value="'.esc($value).'">';
}
/** A failed form keeps its exact proof; no newly rendered consent replaces it. */
function provider_directory_indexing_requests_fields(array $fields, bool $retry): void {
    foreach ($fields as $name=>$value) {
        if ($retry) $value = is_string($_POST[$name] ?? null) ? substr($_POST[$name], 0, 4096) : '';
        provider_directory_indexing_requests_hidden($name, (string)$value);
    }
}
function provider_directory_indexing_requests_form_start(string $action, string $marker, bool $owner, array $route): void {
    echo '<form method="post" action="'.esc(provider_directory_indexing_requests_url($owner, $route, $route['request'])).'" '.$marker.'>';
    echo csrf_field(); provider_directory_indexing_requests_hidden('action', $action);
}
function provider_directory_indexing_requests_password(): void {
    echo '<label>Your current owner password<input type="password" name="current_password" required autocomplete="current-password"></label>';
}
function provider_directory_indexing_requests_label(string $state): string {
    return match ($state) {
        'pending'=>'Waiting for studio review', 'cancelled'=>'Withdrawn by client', 'declined'=>'Declined by studio',
        'interrupted'=>'Request needs studio review', 'not_sent'=>'Change was not sent',
        'observed'=>'Requested setting reported', 'unknown'=>'Change requested; result unconfirmed',
        default=>'Status unavailable',
    };
}
function provider_directory_indexing_requests_render(array $screen, bool $owner, array $route): void {
    $posted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    echo '<div data-directory-requests><section class="workspace-card"><h2>Directory listing</h2>';
    echo '<p>Ask the studio to enable or disable directory listing for a hosting service. The studio reviews each request before sending a change. Enabling directory listing can show a file list instead of an index page. The reported setting does not establish which files visitors can access.</p>';
    if ($posted) echo '<p><a href="'.esc(provider_directory_indexing_requests_url($owner,$route,$route['request'])).'">Reload current request details</a></p>';
    if (!$screen['ready']) {
        if ($owner) {
            echo '<p>Prepare request history after setting up directory-listing observations and changes.</p>';
            provider_directory_indexing_requests_form_start('provider_directory_request_prepare', 'data-directory-request-prepare', true, $route);
            provider_directory_indexing_requests_password(); echo '<button class="btn gold">Enable client directory-listing requests</button></form>';
        } else echo '<p>The studio has not enabled directory-listing requests yet. Contact the studio for help.</p>';
        echo '</section></div>'; return;
    }
    if (!$owner) {
        if (!$screen['options'] && $route['request'] === null) echo '<p>No hosting service is available for a new request. The studio must share its saved directory listing setting first. You can still review your requests below.</p>';
        $options = $screen['options'];
        if ($posted && $action === 'directory_request_create' && is_string($_POST['link_id'] ?? null) && !in_array($_POST['link_id'], array_column($options,'link_id'), true)) {
            $options[] = ['link_id'=>$_POST['link_id'], 'package_name'=>'Previous request', 'review_sha256'=>'', 'shared_value'=>null, 'observed_epoch'=>null, 'retained_only'=>true];
        }
        foreach ($options as $option) {
            $retry = $posted && $action === 'directory_request_create' && ($_POST['link_id'] ?? null) === $option['link_id'];
            echo '<article class="workspace-message"><h3>'.esc($option['package_name']).'</h3>';
            if (is_bool($option['shared_value'])) echo '<p>Last shared setting: <strong>'.($option['shared_value'] ? 'Enabled' : 'Disabled').'</strong>'.($option['observed_epoch'] ? ' · '.esc(gmdate('Y-m-d H:i:s', $option['observed_epoch'])).' UTC' : '').'</p>';
            else echo '<p>Last shared setting: <strong>Unavailable</strong></p>';
            if ($posted && !$retry) { echo '<p>Reload this page to prepare another request.</p></article>'; continue; }
            provider_directory_indexing_requests_form_start('directory_request_create', 'data-directory-request-create', false, $route);
            provider_directory_indexing_requests_fields(['request_id'=>$retry ? '' : bin2hex(random_bytes(16)), 'link_id'=>$option['link_id'], 'review_sha256'=>$option['review_sha256']], $retry);
            $desired = $retry && is_string($_POST['desired'] ?? null) ? substr($_POST['desired'],0,4096) : '';
            echo '<label>Requested directory listing setting<select name="desired" required><option value="">Choose a setting</option>';
            if (!in_array($desired,['','true','false'],true)) echo '<option value="'.esc($desired).'" selected>Previous selection unavailable — choose a setting</option>';
            echo '<option value="true"'.($desired === 'true' ? ' selected' : '').'>Enabled — allow directory listing</option><option value="false"'.($desired === 'false' ? ' selected' : '').'>Disabled — turn off directory listing</option></select></label>';
            echo '<label class="check"><input type="checkbox" name="confirmed" value="1" required> I want the studio to review this change.</label>';
            if ($option['retained_only'] ?? false) echo '<p>This service is no longer available for this request. Your selection is retained; reload after the studio reviews access.</p><button class="btn gold" disabled>Send request to studio</button>';
            else echo '<button class="btn gold">Send request to studio</button>';
            echo '</form></article>';
        }
    } else echo '<p>Review the client’s request, refresh the website’s saved flag if needed, then approve one provider request. Later checks show the reported flag separately.</p><a class="btn outline" href="'.esc(admin_url('provider-settings')).'#directory-listing">Website settings and sharing</a>';
    echo '</section><section class="workspace-card"><h2>Request history</h2>';
    if (!$screen['items']) echo '<p>No requests to show.</p>';
    foreach ($screen['items'] as $item) {
        $retry = $posted && ($_POST['request_id'] ?? null) === $item['id'];
        $allowForm = !$posted || $retry;
        echo '<article class="workspace-message" data-directory-request-item="'.esc($item['id']).'"><h3>'.esc($item['package_name']).'</h3><p><strong>'.esc(provider_directory_indexing_requests_label($item['state'])).'</strong> · Requested: '.($item['desired'] ? 'Enabled' : 'Disabled').'</p><p>'.esc(gmdate('Y-m-d H:i:s', $item['created_epoch'])).' UTC</p>';
        if ($owner && isset($item['client'])) echo '<p>Client: '.esc(is_array($item['client']) ? ($item['client']['name'] ?? '') : (string)$item['client']).'</p>';
        if ($item['note'] !== '') echo '<p>'.nl2br(esc($item['note'])).'</p>';
        if (!$item['current_assignment']) echo '<p>The account, sharing permission or hosting assignment changed. Contact the studio to review this request.</p>';
        if ($item['state'] === 'observed') echo '<p>A later provider check reported the requested setting. This does not establish which files visitors can access or prove this request caused the change.</p>';
        if (in_array($item['state'], ['unknown','interrupted'], true)) echo '<p>The studio has not confirmed the result. This request will not be sent again automatically.</p>';
        if (is_array($item['last_check'] ?? null)) echo '<p>Last follow-up: '.esc(match($item['last_check']['disposition'] ?? '') {'observed'=>'requested setting reported', 'different'=>'a different setting was reported', default=>'setting unavailable'}).'.</p>';
        echo '<a class="btn outline" href="'.esc(provider_directory_indexing_requests_url($owner, $route, $item['id'])).'">Open request</a>';
        if (!$owner && $item['can_cancel'] && $allowForm && (!$retry || $action === 'directory_request_cancel')) {
            provider_directory_indexing_requests_form_start('directory_request_cancel', 'data-directory-request-cancel', false, $route);
            provider_directory_indexing_requests_fields(['request_id'=>$item['id'], 'expected_event'=>$item['latest_event'], 'event_id'=>$retry ? '' : bin2hex(random_bytes(16))], $retry);
            echo '<button class="btn outline">Withdraw request</button></form>';
        }
        if ($owner && $allowForm) provider_directory_indexing_requests_owner_forms($item, $route, $retry, $action);
        echo '</article>';
    }
    echo '<nav class="button-row" aria-label="directory-listing request pages">';
    if ($route['request'] !== null) echo '<a class="btn outline" href="'.esc(provider_directory_indexing_requests_url($owner, $route)).'">All requests</a>';
    else {
        if ($screen['page'] > 1) echo '<a class="btn outline" href="'.esc(provider_directory_indexing_requests_url($owner, ['page'=>$screen['page']-1])).'">Newer requests</a>';
        if ($screen['has_more'] && $screen['page'] < 500) echo '<a class="btn outline" href="'.esc(provider_directory_indexing_requests_url($owner, ['page'=>$screen['page']+1])).'">Older requests</a>';
    }
    echo '</nav></section></div>';
}
function provider_directory_indexing_requests_owner_forms(array $item, array $route, bool $retry, string $action): void {
    $approval = $item['approval'] ?? null;
    if ($approval === null && is_string($item['approval_unavailable'] ?? null)) echo '<p class="hint">'.esc($item['approval_unavailable']).'</p>';
    if ($approval === null && $item['state'] === 'pending' && $retry && $action === 'provider_directory_request_approve') $approval = ['request_id'=>'','anchor_id'=>'','binding_sha256'=>''];
    if (is_array($approval) && (!$retry || $action === 'provider_directory_request_approve')) {
        provider_directory_indexing_requests_form_start('provider_directory_request_approve', 'data-directory-request-approve', true, $route);
        provider_directory_indexing_requests_fields(['request_id'=>$item['id'], 'expected_event'=>$item['latest_event'], 'request_sha256'=>$item['request_sha256'], 'owner_request_id'=>$approval['request_id'], 'anchor_id'=>$approval['anchor_id'], 'binding_sha256'=>$approval['binding_sha256']], $retry);
        if (!$retry) echo '<p>Reviewed saved setting: '.($approval['before_value'] ? 'Enabled' : 'Disabled').'. Client requested: '.($item['desired'] ? 'Enabled' : 'Disabled').'.</p>';
        if ($retry) echo '<p>Resubmission keeps the original review. Reload if the saved setting has changed.</p>';
        echo '<label class="check"><input type="checkbox" name="confirmed" value="1" required> I reviewed this website and approve this one change.</label>';
        provider_directory_indexing_requests_password(); echo '<button class="btn gold"'.(!$item['current_assignment'] ? ' disabled' : '').'>Approve and request change</button></form>';
    }
    if ($item['can_decline'] && (!$retry || $action === 'provider_directory_request_decline')) {
        echo '<details class="directory-request-decline"'.($retry ? ' open' : '').'><summary>Decline this request</summary>';
        provider_directory_indexing_requests_form_start('provider_directory_request_decline', 'data-directory-request-decline', true, $route);
        provider_directory_indexing_requests_fields(['request_id'=>$item['id'], 'expected_event'=>$item['latest_event'], 'event_id'=>$retry ? '' : bin2hex(random_bytes(16))], $retry);
        $note = $retry && is_string($_POST['note'] ?? null) ? substr($_POST['note'], 0, 1000) : '';
        echo '<label>Reason shown to the client<textarea name="note" maxlength="1000" required>'.esc($note).'</textarea></label>';
        provider_directory_indexing_requests_password(); echo '<button class="btn outline">Decline request</button></form></details>';
    }
    $dispatch = $item['dispatch'] ?? null;
    if (!is_array($dispatch) || !$item['current_assignment']) return;
    if ($item['state'] === 'unknown' && ($dispatch['can_verify'] ?? false) && (!$retry || $action === 'provider_directory_request_verify')) {
        provider_directory_indexing_requests_form_start('provider_directory_request_verify', 'data-directory-request-verify', true, $route);
        provider_directory_indexing_requests_fields(['request_id'=>$item['id'], 'owner_request_id'=>$item['owner_request_id']], $retry);
        provider_directory_indexing_requests_password(); echo '<button class="btn outline">Check reported setting</button><p class="hint">Checks once. Does not resend the change.</p></form>';
    }
    if ($item['state'] === 'interrupted' && is_string($dispatch['recovery_ledger_sha256'] ?? null) && (!$retry || $action === 'provider_directory_request_recover')) {
        provider_directory_indexing_requests_form_start('provider_directory_request_recover', 'data-directory-request-recover', true, $route);
        provider_directory_indexing_requests_fields(['request_id'=>$item['id'], 'owner_request_id'=>$item['owner_request_id'], 'ledger_sha256'=>$dispatch['recovery_ledger_sha256']], $retry);
        echo '<label class="check"><input type="checkbox" name="confirmed" value="1" required> Restore the saved request history without resending this change.</label>';
        provider_directory_indexing_requests_password(); echo '<button class="btn outline">Restore saved request history</button></form>';
    }
}
