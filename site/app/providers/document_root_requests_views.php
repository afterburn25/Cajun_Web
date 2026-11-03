<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/document_root_requests.php';

function provider_document_root_requests_route(): array {
    $page = $_GET['folder_page'] ?? '1';
    if (!is_string($page) || !preg_match('/\A[1-9][0-9]{0,2}\z/', $page) || (int)$page > 500) $page = '1';
    $id = $_GET['request'] ?? null;
    if (!is_string($id) || !preg_match('/\A[a-f0-9]{32}\z/', $id)) $id = null;
    return ['page'=>(int)$page, 'request'=>$id];
}
function provider_document_root_requests_url(bool $owner, array $route, ?string $id = null): string {
    $query = ['folder_page'=>$route['page']];
    if ($id !== null) $query['request'] = $id;
    return $owner ? admin_url('folder-requests',$query) : studio_url(['folder_requests'=>1]+$query);
}
/** Reject oversized input rather than silently turning it into a different valid request. */
function provider_document_root_requests_post(string $name, int $max = 128): string {
    $value = $_POST[$name] ?? '';
    if (!is_string($value) || strlen($value) > $max) throw new InvalidArgumentException('Reload the request and review its exact details.');
    return $value;
}
/** Presentation only: bounded raw failed values never become replacement consent. */
function provider_document_root_requests_failed_value(string $name): string {
    $value = $_POST[$name] ?? '';
    return is_string($value) && strlen($value) <= 8192 ? $value : '';
}
function provider_document_root_requests_hidden(string $name, string $value): void {
    echo '<input type="hidden" name="'.esc($name).'" value="'.esc($value).'">';
}
function provider_document_root_requests_fields(array $values, bool $retry): void {
    foreach ($values as $name=>$value) provider_document_root_requests_hidden($name,$retry ? provider_document_root_requests_failed_value($name) : (string)$value);
}
function provider_document_root_requests_form(string $action, string $marker, bool $owner, array $route): void {
    echo '<form method="post" action="'.esc(provider_document_root_requests_url($owner,$route,$route['request'])).'" '.$marker.'>';
    echo csrf_field(); provider_document_root_requests_hidden('action',$action);
}
function provider_document_root_requests_password(): void {
    echo '<label>Your current owner password<input type="password" name="current_password" required autocomplete="current-password"></label>';
}
function provider_document_root_requests_path_text(string $path): void {
    if ($path === '') echo '<p class="hint">Empty saved text value</p>';
    else echo '<pre class="folder-request-path">'.esc($path).'</pre>';
}
function provider_document_root_requests_label(string $state): string {
    return match ($state) {
        'pending'=>'Waiting for studio review', 'cancelled'=>'Withdrawn by client', 'declined'=>'Declined by studio',
        'interrupted'=>'Request needs studio review', 'not_sent'=>'Change was not sent',
        'observed'=>'Requested folder reported', 'unknown','uncertain'=>'Change requested; result unconfirmed',
        default=>'Status unavailable',
    };
}
function provider_document_root_requests_error(Throwable $error): string {
    return $error instanceof PDOException ? 'Website folder request history is temporarily unavailable. Please try again later.' : $error->getMessage();
}
function provider_document_root_requests_render(array $screen, bool $owner, array $route): void {
    $posted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    echo '<div data-folder-requests><section class="workspace-card"><h2>Website folder requests</h2><p>Ask the studio to change where an existing website serves its pages. The studio reviews the saved folders and the destination before sending a change.</p><p class="hint">A reported folder does not prove that website routing works or that files are protected.</p>';
    if ($posted) echo '<p><a href="'.esc(provider_document_root_requests_url($owner,$route,$route['request'])).'">Reload current request details</a></p>';
    if (!$screen['ready']) {
        if ($owner) {
            echo '<p>Prepare client request history after setting up website folder observations and changes.</p>';
            provider_document_root_requests_form('provider_folder_request_prepare','data-folder-request-prepare',true,$route);
            provider_document_root_requests_password(); echo '<button class="btn gold">Enable client folder requests</button></form>';
        } else echo '<p>The studio has not enabled website folder requests yet. Contact the studio for help.</p>';
        echo '</section></div>'; return;
    }
    if ($owner) echo '<p>Review the client’s original request alongside a fresh saved folder map. Approval sends one change; follow-up reads are separate.</p><a class="btn outline" href="'.esc(admin_url('provider-settings')).'#document-roots">Website folders and sharing</a>';
    else {
        $options = $screen['options'];
        if (!$options && $route['request'] === null) echo '<p>No shared hosting service is available for a new request. Ask the studio to share its saved website folders first. Your request history remains below.</p>';
        if ($posted && $action === 'folder_request_create' && is_string($_POST['link_id'] ?? null) && !in_array($_POST['link_id'],array_column($options,'link_id'),true)) {
            $options[] = ['link_id'=>provider_document_root_requests_failed_value('link_id'),'package_name'=>'Previous request','review_sha256'=>'','entries'=>[],'observed_epoch'=>null,'retained_only'=>true];
        }
        foreach ($options as $option) {
            $retry = $posted && $action === 'folder_request_create' && ($_POST['link_id'] ?? null) === $option['link_id'];
            echo '<article class="workspace-message" data-folder-request-service><h3>'.esc($option['package_name']).'</h3>';
            if ($option['observed_epoch'] !== null) echo '<p class="hint">Shared folder observation · '.esc(gmdate('Y-m-d H:i:s',$option['observed_epoch'])).' UTC</p>';
            if ($posted && !$retry) { echo '<p>Reload this page to prepare another request.</p></article>'; continue; }
            if (!$option['entries'] && !$retry) { echo '<p>The shared map contains no website to select. Ask the studio to review this hosting service.</p></article>'; continue; }
            echo '<details class="folder-request-map"><summary>View saved website folders</summary>';
            foreach ($option['entries'] as $entry) { echo '<p><strong>'.esc($entry['name']).'</strong></p>'; provider_document_root_requests_path_text($entry['path']); }
            echo '</details>';
            provider_document_root_requests_form('folder_request_create','data-folder-request-create',false,$route);
            provider_document_root_requests_fields(['request_id'=>$retry ? '' : bin2hex(random_bytes(16)),'link_id'=>$option['link_id'],'review_sha256'=>$option['review_sha256']],$retry);
            $name = $retry ? provider_document_root_requests_failed_value('name') : '';
            echo '<label>Website<select name="name" required><option value="">Choose a shared website</option>';
            if ($name !== '' && !in_array($name,array_column($option['entries'],'name'),true)) echo '<option value="'.esc($name).'" selected>Previous selection: '.esc($name).'</option>';
            foreach ($option['entries'] as $entry) echo '<option value="'.esc($entry['name']).'"'.($name === $entry['name'] ? ' selected' : '').'>'.esc($entry['name']).'</option>';
            echo '</select></label><label>Requested relative folder<input type="text" name="desired_path" value="'.esc($retry ? provider_document_root_requests_failed_value('desired_path') : '').'" required maxlength="1024" autocomplete="off" spellcheck="false" placeholder="public_html/newsite"></label><p class="hint">Use a relative folder such as public_html/newsite. Folder names can contain letters, digits, underscores, hyphens and dots. Do not use a leading slash, spaces or parent-directory segments.</p><label class="check"><input type="checkbox" name="confirmed" value="1" required> I want the studio to review this website and folder.</label>';
            if ($option['retained_only'] ?? false) echo '<p>The shared assignment changed. Your submitted details are retained; reload after the studio reviews access.</p><button class="btn gold" disabled>Send request to studio</button>';
            else echo '<button class="btn gold">Send request to studio</button>';
            echo '</form></article>';
        }
    }
    echo '</section><section class="workspace-card"><h2>Request history</h2>';
    if (!$screen['items']) echo '<p>No requests to show.</p>';
    foreach ($screen['items'] as $item) {
        $retry = $posted && ($_POST['request_id'] ?? null) === $item['id'];
        $allow = !$posted || $retry;
        echo '<article class="workspace-message" data-folder-request-item="'.esc($item['id']).'" data-folder-request-state="'.esc($item['state']).'"><h3>'.esc($item['name']).'</h3><p>'.esc($item['package_name']).' · '.esc(gmdate('Y-m-d H:i:s',$item['created_epoch'])).' UTC</p><p><strong>'.esc(provider_document_root_requests_label($item['state'])).'</strong></p>';
        if ($owner && is_array($item['client'] ?? null)) echo '<p>Requested by '.esc($item['client']['name']).' · Client #'.esc((string)$item['client']['id']).'</p>';
        if ($owner || $item['current_assignment']) { echo '<p>Folder shared when requested</p>'; provider_document_root_requests_path_text($item['before_path']); }
        echo '<p>Requested folder</p>'; provider_document_root_requests_path_text($item['desired_path']);
        if ($item['note'] !== '') echo '<p>'.nl2br(esc($item['note'])).'</p>';
        if (!$item['current_assignment']) echo '<p>The account, sharing permission or hosting assignment changed. Contact the studio to review this request.</p>';
        if ($item['state'] === 'observed') echo '<p>A later provider check reported the requested folder. This does not prove that the request caused the change, that website routing works or that files are protected.</p>';
        elseif (in_array($item['state'],['unknown','uncertain','interrupted'],true)) echo '<p>The result is not confirmed. This request will not be sent again automatically.</p>';
        if (is_array($item['last_check'] ?? null)) echo '<p>Follow-up · '.esc(gmdate('Y-m-d H:i:s',$item['last_check']['recorded_epoch'])).' UTC: '.esc(match($item['last_check']['disposition']) {'observed'=>'requested folder reported','different'=>'different folder or no website entry reported',default=>'no usable folder map reported'}).'.</p>';
        echo '<a class="btn outline" href="'.esc(provider_document_root_requests_url($owner,$route,$item['id'])).'">Open request</a>';
        if (!$owner && $item['can_cancel'] && $allow && (!$retry || $action === 'folder_request_cancel')) {
            provider_document_root_requests_form('folder_request_cancel','data-folder-request-cancel',false,$route);
            provider_document_root_requests_fields(['request_id'=>$item['id'],'expected_event'=>$item['latest_event'],'event_id'=>$retry ? '' : bin2hex(random_bytes(16))],$retry);
            echo '<button class="btn outline">Withdraw request</button></form>';
        }
        if ($owner && $allow) provider_document_root_requests_owner_forms($item,$route,$retry,$action);
        echo '</article>';
    }
    echo '<nav class="button-row" aria-label="Website folder request pages">';
    if ($route['request'] !== null) echo '<a class="btn outline" href="'.esc(provider_document_root_requests_url($owner,$route)).'">All requests</a>';
    else {
        if ($screen['page'] > 1) echo '<a class="btn outline" href="'.esc(provider_document_root_requests_url($owner,['page'=>$screen['page']-1])).'">Newer requests</a>';
        if ($screen['has_more'] && $screen['page'] < 500) echo '<a class="btn outline" href="'.esc(provider_document_root_requests_url($owner,['page'=>$screen['page']+1])).'">Older requests</a>';
    }
    echo '</nav></section></div>';
}
function provider_document_root_requests_owner_forms(array $item,array $route,bool $retry,string $action): void {
    $approval = $item['approval'];
    if ($approval === null && ($item['approval_unavailable'] ?? false) === true) echo '<p class="hint">A current approval is unavailable. Review the hosting assignment and sharing, then refresh website folders in connection settings. A request already at its desired folder or awaiting a previous result cannot be sent again here.</p>';
    if ($approval === null && $item['state'] === 'pending' && $retry && $action === 'provider_folder_request_approve') $approval = ['request_id'=>'','anchor_id'=>'','binding_sha256'=>''];
    if (is_array($approval) && (!$retry || $action === 'provider_folder_request_approve')) {
        if (!$retry) { echo '<h4>Current owner review</h4><p>Latest saved folder · '.esc(gmdate('Y-m-d H:i:s',$approval['observed_epoch'])).' UTC</p>'; provider_document_root_requests_path_text($approval['before_path']); }
        else echo '<p>Resubmission keeps the original reviewed map and approval. Reload if those details changed.</p>';
        provider_document_root_requests_form('provider_folder_request_approve','data-folder-request-approve',true,$route);
        provider_document_root_requests_fields(['request_id'=>$item['id'],'expected_event'=>$item['latest_event'],'request_sha256'=>$item['request_sha256'],'owner_request_id'=>$approval['request_id'],'anchor_id'=>$approval['anchor_id'],'binding_sha256'=>$approval['binding_sha256']],$retry);
        echo '<p>20i does not support folder changes for Managed WordPress. This page cannot verify hosting eligibility or whether the destination folder exists.</p><label class="check"><input type="checkbox" name="reviewed" value="1" required> I verified that this hosting supports folder changes, that the requested destination folder already exists, and I approve this one request.</label>';
        provider_document_root_requests_password(); echo '<button class="btn gold"'.(!$item['current_assignment'] ? ' disabled' : '').'>Approve and request change</button></form>';
    }
    if ($item['can_decline'] && (!$retry || $action === 'provider_folder_request_decline')) {
        echo '<details class="folder-request-decline"'.($retry ? ' open' : '').'><summary>Decline this request</summary>';
        provider_document_root_requests_form('provider_folder_request_decline','data-folder-request-decline',true,$route);
        provider_document_root_requests_fields(['request_id'=>$item['id'],'expected_event'=>$item['latest_event'],'event_id'=>$retry ? '' : bin2hex(random_bytes(16))],$retry);
        echo '<label>Reason shown to the client<textarea name="note" maxlength="1000" required>'.esc($retry ? provider_document_root_requests_failed_value('note') : '').'</textarea></label>';
        provider_document_root_requests_password(); echo '<button class="btn outline">Decline request</button></form></details>';
    }
    $dispatch = $item['dispatch'];
    if (!is_array($dispatch) || !$item['current_assignment']) return;
    if (is_array($dispatch['last_check'] ?? null) && $dispatch['last_check']['path'] !== null) { echo '<p>Folder reported by the follow-up read</p>'; provider_document_root_requests_path_text($dispatch['last_check']['path']); }
    if (in_array($item['state'],['unknown','uncertain'],true) && ($dispatch['can_verify'] ?? false) && (!$retry || $action === 'provider_folder_request_verify')) {
        provider_document_root_requests_form('provider_folder_request_verify','data-folder-request-verify',true,$route);
        provider_document_root_requests_fields(['request_id'=>$item['id'],'owner_request_id'=>$item['owner_request_id']],$retry);
        provider_document_root_requests_password(); echo '<button class="btn outline">Read folders after this request</button><p class="hint">One fresh read. Does not resend the change.</p></form>';
    }
    if ($item['state'] === 'interrupted' && is_string($dispatch['recovery_ledger_sha256'] ?? null) && (!$retry || $action === 'provider_folder_request_recover')) {
        provider_document_root_requests_form('provider_folder_request_recover','data-folder-request-recover',true,$route);
        provider_document_root_requests_fields(['request_id'=>$item['id'],'owner_request_id'=>$item['owner_request_id'],'ledger_sha256'=>$dispatch['recovery_ledger_sha256']],$retry);
        echo '<label class="check"><input type="checkbox" name="confirmed" value="1" required> Restore the saved local request history without resending this change.</label>';
        provider_document_root_requests_password(); echo '<button class="btn outline">Restore saved request history</button></form>';
    }
}
