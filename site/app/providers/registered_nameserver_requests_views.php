<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Local HTTP bounds; services remain responsible for identity and authority. */
function registered_nameserver_requests_ui_text(array $input, string $key, int $limit): string {
    $value = $input[$key] ?? '';
    if (!is_string($value) || strlen($value) > $limit || preg_match('//u', $value) !== 1) throw new InvalidArgumentException('Check the length and format of '.str_replace('_', ' ', $key).'.');
    return $value;
}
function registered_nameserver_requests_ui_id(array $input, string $key, int $length = 32): string {
    $value = registered_nameserver_requests_ui_text($input, $key, $length);
    if (preg_match('/\A[a-f0-9]{'.$length.'}\z/', $value) !== 1) throw new InvalidArgumentException('Reload and review this request before continuing.');
    return $value;
}
function registered_nameserver_requests_ui_confirm(array $input, string $key): void {
    if (($input[$key] ?? null) !== '1') throw new InvalidArgumentException('Confirm the request before continuing.');
}
function registered_nameserver_requests_ui_desired(array $input): array {
    $text = registered_nameserver_requests_ui_text($input, 'desired_nameservers', 16384);
    if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text)) throw new InvalidArgumentException('Enter one nameserver hostname per line.');
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $values = array_values(array_filter(array_map('trim', $lines), static fn(string $v): bool => $v !== ''));
    if (!$values || count($values) > 64) throw new InvalidArgumentException('Enter a complete nameserver list, one hostname per line.');
    return $values; // The canonical provider hostname validator runs in the service.
}
function registered_nameserver_requests_ui_query(array $input): array {
    $raw = $input['request_page'] ?? '1';
    if (!is_string($raw) || preg_match('/\A[1-9][0-9]{0,4}\z/', $raw) !== 1 || (int)$raw > 10000) throw new InvalidArgumentException('Choose a valid request page.');
    $detail = array_key_exists('request_id', $input) ? registered_nameserver_requests_ui_id($input, 'request_id') : null;
    return [(int)$raw, $detail];
}
function registered_nameserver_requests_ui_url(bool $owner, ?string $id = null, int $page = 1): string {
    $params = ['request_page' => max(1, min(10000, $page))];
    if ($id !== null) $params['request_id'] = registered_nameserver_requests_ui_id(['request_id' => $id], 'request_id');
    return $owner ? admin_url('nameserver-requests', $params) : studio_url(['nameserver_requests' => 1] + $params);
}
/** Retain only bounded form inputs, never passwords, confirmations or provider context. */
function registered_nameserver_requests_ui_retry(array $input, bool $owner): array {
    $actions = $owner ? ['provider_nameserver_request_prepare', 'provider_nameserver_request_decline', 'provider_nameserver_request_approve'] : ['nameserver_request_create', 'nameserver_request_cancel'];
    if (!is_string($input['action'] ?? null) || !in_array($input['action'], $actions, true)) return [];
    $out = ['action' => $input['action']];
    foreach (['request_id' => 32, 'link_id' => 32, 'review_sha256' => 64, 'expected_event' => 32, 'event_id' => 32, 'request_sha256' => 64, 'dispatch_id' => 32, 'approval_review_sha256' => 64, 'desired_nameservers' => 16384, 'note' => 1000] as $key => $limit) {
        if (!array_key_exists($key, $input)) continue;
        $v = $input[$key];
        $out[$key] = is_string($v) && strlen($v) <= $limit && preg_match('//u', $v) === 1 && !preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $v) ? $v : '';
    }
    return $out;
}
function registered_nameserver_requests_ui_hidden(string $key, string $value): void {
    echo '<input type="hidden" name="'.esc($key).'" value="'.esc($value).'">';
}
function registered_nameserver_requests_ui_form(string $action, string $marker): void {
    echo '<form method="post" '.$marker.'>'.csrf_field();
    registered_nameserver_requests_ui_hidden('action', $action);
}
function registered_nameserver_requests_ui_password(): void {
    echo '<label>Your current owner password<input type="password" name="current_password" value="" required autocomplete="current-password"></label>';
}
function registered_nameserver_requests_ui_list(array $values): void {
    echo '<ul>';
    foreach ($values as $value) echo '<li style="overflow-wrap:anywhere">'.esc($value).'</li>';
    echo '</ul>';
}
function registered_nameserver_requests_ui_label(string $state): string {
    return match ($state) {
        'pending' => 'Awaiting studio review', 'cancelled' => 'Request withdrawn', 'declined' => 'Request declined',
        'not_sent' => 'Change was not sent', 'interrupted' => 'Saved request needs studio review',
        'observed' => 'Requested nameservers were later reported',
        'dispatched', 'uncertain' => 'Change requested — result not yet confirmed',
        default => 'Request status needs review',
    };
}
function registered_nameserver_requests_ui_create(array $option, array $retry = [], bool $available = true): void {
    $value = static fn(string $key, string $fallback): string => $retry ? ($retry[$key] ?? '') : $fallback;
    $matches = !$retry || (($retry['link_id'] ?? '') === $option['link_id'] && ($retry['review_sha256'] ?? '') === $option['review_sha256']);
    echo '<article class="workspace-card" data-nameserver-request-create><h3 style="overflow-wrap:anywhere">'.esc($option['domain']).'</h3>';
    registered_nameserver_requests_ui_form('nameserver_request_create', 'data-nameserver-request-create-form');
    foreach (['request_id' => bin2hex(random_bytes(16)), 'link_id' => $option['link_id'], 'review_sha256' => $option['review_sha256']] as $key => $fallback) registered_nameserver_requests_ui_hidden($key, $value($key, $fallback));
    echo '<label>Requested nameservers<textarea name="desired_nameservers" rows="4" maxlength="16384" required spellcheck="false" autocapitalize="none" autocomplete="off">'.esc($value('desired_nameservers', '')).'</textarea></label>';
    echo '<p class="hint">Enter the complete new list, one hostname per line. The studio will check the current list before approving a replacement.</p>';
    echo '<label class="check"><input type="checkbox" name="confirmed" value="1" required> Ask the studio to review this complete list.</label>';
    if (!$available || !$matches) echo '<p class="notice">These details changed or are unavailable. Reload and review the domain before trying again. Your entered list is shown above.</p>';
    echo '<button class="btn gold"'.(!$available || !$matches ? ' disabled' : '').'>Request nameserver review</button></form></article>';
}
function registered_nameserver_requests_ui_item(array $item, bool $owner, array $retry): void {
    $id = $item['id'];
    $same = ($retry['request_id'] ?? null) === $id;
    echo '<article class="workspace-card" id="registered-nameserver-request-'.esc($id).'" data-nameserver-request-history>';
    echo '<h3 style="overflow-wrap:anywhere">'.esc($item['domain']).'</h3><p><strong>'.esc(registered_nameserver_requests_ui_label($item['state'])).'</strong></p>';
    echo '<p class="hint">Requested '.esc(gmdate('Y-m-d H:i:s', $item['created_epoch'])).' UTC</p><p>Requested complete list</p>';
    registered_nameserver_requests_ui_list($item['desired_nameservers']);
    if ($item['note'] !== '') echo '<p style="white-space:pre-wrap;overflow-wrap:anywhere">'.esc($item['note']).'</p>';
    if (!$item['current_assignment']) echo '<p>The domain assignment or connection changed. This saved request remains available for review.</p>';
    if ($item['state'] === 'observed') echo '<p>A later check reported this list. This does not confirm worldwide DNS propagation or prove what caused the change.</p>';
    elseif (in_array($item['state'], ['dispatched', 'uncertain', 'interrupted'], true)) echo '<p>The studio is reviewing the outcome. This status does not mean the change is complete.</p>';
    if ($item['last_check'] !== null) {
        $check = $item['last_check'];
        $label = match ($check['disposition']) { 'observed' => 'The requested list was reported', 'different' => 'A different list was reported', default => 'The latest check was unavailable' };
        echo '<p>'.esc($label).' · '.esc(gmdate('Y-m-d H:i:s', $check['recorded_epoch'])).' UTC</p>';
        if (in_array($check['disposition'], ['observed', 'different'], true) && is_array($check['nameservers'])) registered_nameserver_requests_ui_list($check['nameservers']);
    }
    echo '<p><a class="btn outline" href="'.esc(registered_nameserver_requests_ui_url($owner, $id)).'">View this request</a></p>';
    if (!$owner && $item['can_cancel']) {
        $r = $same && ($retry['action'] ?? '') === 'nameserver_request_cancel' ? $retry : [];
        registered_nameserver_requests_ui_form('nameserver_request_cancel', 'data-nameserver-request-cancel');
        foreach (['request_id' => $id, 'expected_event' => $item['latest_event'], 'event_id' => bin2hex(random_bytes(16))] as $key => $fallback) registered_nameserver_requests_ui_hidden($key, $r ? ($r[$key] ?? '') : $fallback);
        echo '<label class="check"><input type="checkbox" name="confirmed" value="1" required> Withdraw this request before studio approval.</label><button class="btn outline">Withdraw request</button></form>';
    }
    if ($same && ((!$owner && !$item['can_cancel'] && ($retry['action'] ?? '') === 'nameserver_request_cancel') || ($owner && !$item['can_decline'] && ($retry['action'] ?? '') === 'provider_nameserver_request_decline'))) registered_nameserver_requests_ui_unavailable_retry($retry, $owner);
    if ($owner && $item['can_decline']) {
        $r = $same && ($retry['action'] ?? '') === 'provider_nameserver_request_decline' ? $retry : [];
        echo '<details><summary>Decline this request</summary>';
        registered_nameserver_requests_ui_form('provider_nameserver_request_decline', 'data-nameserver-request-decline');
        foreach (['request_id' => $id, 'expected_event' => $item['latest_event'], 'event_id' => bin2hex(random_bytes(16))] as $key => $fallback) registered_nameserver_requests_ui_hidden($key, $r ? ($r[$key] ?? '') : $fallback);
        echo '<label>Reason for the client<textarea name="note" rows="3" maxlength="1000" required>'.esc($r['note'] ?? '').'</textarea></label><label class="check"><input type="checkbox" name="reviewed" value="1" required> Decline this request.</label>';
        registered_nameserver_requests_ui_password();
        echo '<button class="btn outline">Decline request</button></form></details>';
    }
    if ($owner && ($item['approval'] !== null || ($same && ($retry['action'] ?? '') === 'provider_nameserver_request_approve'))) {
        $approval = $item['approval'];
        $r = $same && ($retry['action'] ?? '') === 'provider_nameserver_request_approve' ? $retry : [];
        $matches = $approval !== null && (!$r || (($r['expected_event'] ?? '') === $item['latest_event'] && ($r['request_sha256'] ?? '') === $item['request_sha256'] && ($r['approval_review_sha256'] ?? '') === $approval['review_sha256']));
        echo '<section data-nameserver-request-review><h4>Review and send</h4>';
        if ($approval !== null) {
            echo '<p>Current saved list · '.esc(gmdate('Y-m-d H:i:s', $approval['observed_epoch'])).' UTC</p>';
            registered_nameserver_requests_ui_list($approval['old_nameservers']);
        }
        registered_nameserver_requests_ui_form('provider_nameserver_request_approve', 'data-nameserver-request-approve');
        foreach (['request_id' => $id, 'expected_event' => $item['latest_event'], 'request_sha256' => $item['request_sha256'], 'dispatch_id' => bin2hex(random_bytes(16)), 'approval_review_sha256' => $approval['review_sha256'] ?? ''] as $key => $fallback) registered_nameserver_requests_ui_hidden($key, $r ? ($r[$key] ?? '') : $fallback);
        echo '<p>Sending replaces the complete list and can affect the website and email. A separate check is needed to observe the result.</p>';
        if (!$matches) echo '<p class="notice">These details changed. Reload and review both lists before trying again.</p>';
        echo '<label class="check"><input type="checkbox" name="reviewed" value="1" required> I reviewed both complete lists and approve this one change.</label>';
        registered_nameserver_requests_ui_password();
        echo '<button class="btn gold"'.(!$matches ? ' disabled' : '').'>Approve and request change</button></form></section>';
    } elseif ($owner && $item['state'] === 'pending') {
        echo '<p>Refresh and review this domain’s nameservers before approving. The current and requested lists must be complete and share no nameserver.</p><a class="btn outline" href="'.esc(admin_url('provider-settings')).'#registered-nameservers">Review domain observations</a>';
    }
    if ($owner && ($item['dispatch'] ?? null) !== null) registered_nameserver_changes_summary_render($item['dispatch']);
    echo '</article>';
}
function registered_nameserver_requests_ui_unavailable_retry(array $retry, bool $owner): void {
    if (!$retry) return;
    if (!$owner && ($retry['action'] ?? '') === 'nameserver_request_create') {
        registered_nameserver_requests_ui_create(['link_id' => '', 'domain' => 'Your unsent request', 'review_sha256' => ''], $retry, false);
        return;
    }
    echo '<section class="workspace-card" data-nameserver-request-retained><h2>Your unsaved review</h2><p>These details are retained for review. Reload the request before submitting again.</p>';
    registered_nameserver_requests_ui_form($retry['action'], 'data-nameserver-request-retained-form');
    foreach ($retry as $key => $value) if (!in_array($key, ['action', 'desired_nameservers', 'note'], true)) registered_nameserver_requests_ui_hidden($key, $value);
    if (array_key_exists('note', $retry)) echo '<label>Reason for the client<textarea name="note" rows="3">'.esc($retry['note']).'</textarea></label>';
    if ($owner) registered_nameserver_requests_ui_password();
    echo '<button class="btn outline" disabled>Reload to continue</button></form></section>';
}
/** Only an authenticated safe screen is rendered; this function performs no reads. */
function registered_nameserver_requests_render(?array $screen, bool $owner, array $retry = []): void {
    echo '<div data-nameserver-requests><p>'.($owner ? 'Review client requests before approving a complete nameserver replacement.' : 'Ask the studio to review a complete nameserver replacement. Submitting a request does not send a change.').'</p>';
    if ($screen === null) {
        echo '<p class="notice" role="alert">Request records are unavailable. Please try again later or contact the studio.</p>';
        registered_nameserver_requests_ui_unavailable_retry($retry, $owner);
    } else {
        if (!$screen['ready']) {
            if ($owner) {
                registered_nameserver_requests_ui_form('provider_nameserver_request_prepare', 'data-nameserver-request-prepare');
                echo '<p>Enable client nameserver requests and their saved review history.</p>';
                registered_nameserver_requests_ui_password();
                echo '<button class="btn gold">Enable client requests</button></form>';
            } else echo '<p>The studio has not enabled nameserver requests yet.</p>';
        }
        $createRetry = !$owner && ($retry['action'] ?? '') === 'nameserver_request_create';
        if (!$owner && ($screen['ready'] || $createRetry)) {
            echo '<section><h2>Request a review</h2><div class="workspace-grid">';
            if ($createRetry) {
                $selected = null;
                foreach ($screen['options'] as $option) if ($option['link_id'] === ($retry['link_id'] ?? '')) $selected = $option;
                registered_nameserver_requests_ui_create($selected ?? ['link_id' => '', 'domain' => 'Your unsent request', 'review_sha256' => ''], $retry, $screen['ready'] && $selected !== null);
            } else {
                foreach ($screen['options'] as $option) registered_nameserver_requests_ui_create($option);
                if (!$screen['options']) echo '<p>No domain is available for a new request. Ask the studio to review your domain assignment or an earlier request.</p>';
            }
            echo '</div></section>';
        }
        echo '<section data-nameserver-request-history-list><h2>'.($owner ? 'Client requests' : 'Your requests').'</h2>';
        $foundRetry = false;
        foreach ($screen['items'] as $item) {
            if ($item['id'] === ($retry['request_id'] ?? null)) $foundRetry = true;
            registered_nameserver_requests_ui_item($item, $owner, $retry);
        }
        if (!$screen['items']) echo '<p>No requests on this page.</p>';
        if ($retry && !$createRetry && !$foundRetry && ($retry['action'] ?? '') !== 'provider_nameserver_request_prepare') registered_nameserver_requests_ui_unavailable_retry($retry, $owner);
        echo '<nav class="button-row" aria-label="Nameserver request pages">';
        if ($screen['page'] > 1) echo '<a class="btn outline" href="'.esc(registered_nameserver_requests_ui_url($owner, null, $screen['page'] - 1)).'">Newer requests</a>';
        if ($screen['has_more'] && $screen['page'] < 10000) echo '<a class="btn outline" href="'.esc(registered_nameserver_requests_ui_url($owner, null, $screen['page'] + 1)).'">Older requests</a>';
        echo '</nav></section>';
    }
    echo '<p><a class="btn outline" href="'.esc(registered_nameserver_requests_ui_url($owner)).'">Reload request forms</a></p></div>';
}
