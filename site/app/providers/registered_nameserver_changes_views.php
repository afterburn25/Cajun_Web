<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_changes.php';

function registered_nameserver_changes_label(string $status): string {
    return match ($status) {
        'observed' => 'Requested nameservers reported after the request',
        'not_sent' => 'Request was not sent',
        'interrupted' => 'Request interrupted — review required',
        default => 'Change requested — result not yet confirmed',
    };
}

function registered_nameserver_changes_hidden(string $name, string $value): void {
    echo '<input type="hidden" name="'.esc($name).'" value="'.esc($value).'">';
}

function registered_nameserver_changes_prepare_form(): void {
    echo '<details data-nameserver-change-setup><summary>Enable nameserver changes</summary>';
    echo '<p>Enable owner approval and request history for replacing a domain’s complete nameserver list.</p><form method="post">';
    admin_action('provider_nameserver_change_prepare');
    admin_input('current_password', 'Your current owner password', '', 'password', 'required autocomplete="current-password"');
    echo '<button class="btn outline">Enable nameserver changes</button></form></details>';
}

/** Only the safe service projection is rendered. The old set is never a posted field. */
function registered_nameserver_changes_form_render(array $form): void {
    echo '<details data-nameserver-change><summary>Change nameservers</summary>';
    if (!$form['ready'] || !$form['eligible']) {
        $reason = match ($form['reason']) {
            'not_prepared' => 'Enable nameserver changes above first.',
            'pending' => 'A request for this domain remains unresolved. Review it before requesting another change.',
            default => 'Refresh nameservers for this assignment before approving a change.',
        };
        echo '<p>'.esc($reason).'</p>';
        if ($form['pending']) {
            registered_nameserver_changes_summary_render($form['pending'], false);
        }
        echo '</details>';
        return;
    }
    echo '<form method="post" data-nameserver-change-form>';
    admin_action('provider_nameserver_change_submit');
    foreach (['request_id' => bin2hex(random_bytes(16)), 'link_id' => $form['link_id'],
        'anchor_id' => $form['anchor_id'], 'binding_sha256' => $form['binding_sha256']] as $name => $value) {
        registered_nameserver_changes_hidden($name, $value);
    }
    echo '<p><strong>Current saved nameservers</strong> · '.esc(gmdate('Y-m-d H:i:s', $form['observed_epoch'])).' UTC</p>';
    registered_nameservers_set_render($form['old_nameservers']);
    echo '<p>This form replaces the complete list. Enter one hostname per line. The new list must not contain any current nameserver.</p>';
    echo '<label>New nameservers<textarea name="desired_nameservers" rows="4" maxlength="16384" required spellcheck="false" autocapitalize="none" autocomplete="off"></textarea></label>';
    echo '<label class="check"><input type="checkbox" name="reviewed" value="1" required> I reviewed the current and new nameservers for '.esc($form['domain']).' and approve this one request.</label>';
    admin_input('current_password', 'Your current owner password', '', 'password', 'required autocomplete="current-password"');
    echo '<button class="btn gold">Request nameserver change</button></form></details>';
}

function registered_nameserver_changes_summary_render(array $view, bool $withAnchor = true): void {
    echo '<article class="workspace-message"'.($withAnchor ? ' id="nameserver-request-'.esc($view['id']).'"' : '').' data-nameserver-change-history>';
    echo '<h4 style="overflow-wrap:anywhere">'.esc($view['domain']).'</h4><p><strong>'.esc(registered_nameserver_changes_label($view['status'])).'</strong></p>';
    echo '<p class="hint">Requested '.esc(gmdate('Y-m-d H:i:s', $view['requested_epoch'])).' UTC</p>';
    echo '<details><summary>Current and requested nameservers at approval</summary><p>Saved before the request</p>';
    registered_nameservers_set_render($view['old_nameservers']);
    echo '<p>Requested nameservers</p>';
    registered_nameservers_set_render($view['desired_nameservers']);
    echo '</details>';
    if ($view['status'] === 'interrupted') {
        echo '<p>Part of this request’s saved history is missing. Another change remains blocked.</p>';
        if ($view['recovery_ledger_sha256'] !== null && $view['current_assignment']) {
            echo '<form method="post" data-nameserver-change-recover>';
            admin_action('provider_nameserver_change_recover');
            registered_nameserver_changes_hidden('request_id', $view['id']);
            registered_nameserver_changes_hidden('ledger_sha256', $view['recovery_ledger_sha256']);
            echo '<p>The saved attempt allows its missing history to be restored. Restoring history does not resend the change or confirm its result.</p>';
            echo '<label class="check"><input type="checkbox" name="reviewed" value="1" required> I reviewed this request and want to restore its saved history.</label>';
            admin_input('current_password', 'Your current owner password', '', 'password', 'required autocomplete="current-password"');
            echo '<button class="btn outline">Restore saved request history</button></form>';
        } else {
            echo '<p>This request’s history cannot be restored here. Its outcome needs review before another change can be requested.</p>';
        }
    } elseif ($view['status'] === 'not_sent') {
        echo '<p>The change was not sent. Refresh nameservers before approving a new request.</p>';
    } elseif ($view['status'] === 'observed') {
        echo '<p>A later check reported the requested nameservers. This does not prove this request caused the change or confirm DNS propagation.</p>';
    } else {
        echo '<p>The outcome is unresolved. An unavailable or different result does not allow this request to be sent again.</p>';
        if ($view['current_assignment'] && $view['can_verify']) {
            echo '<form method="post" data-nameserver-change-verify>';
            admin_action('provider_nameserver_change_verify');
            registered_nameserver_changes_hidden('request_id', $view['id']);
            admin_input('current_password', 'Your current owner password', '', 'password', 'required autocomplete="current-password"');
            echo '<button class="btn outline">Check current nameservers</button><p class="hint">Checks the provider once, subject to refresh limits. This does not resend the change.</p></form>';
        } elseif ($view['current_assignment']) {
            echo '<p>The saved dispatch record needs review before this request can be checked. Another change remains blocked.</p>';
        }
    }
    if ($view['last_check']) {
        $check = $view['last_check'];
        $label = match ($check['disposition']) {
            'observed' => 'The requested nameservers were reported',
            'different' => 'Different nameservers were reported',
            default => 'Nameservers were unavailable',
        };
        echo '<p>'.esc($label).' · '.esc(gmdate('Y-m-d H:i:s', $check['recorded_epoch'])).' UTC</p>';
        if (in_array($check['disposition'], ['observed', 'different'], true) && is_array($check['nameservers'])) {
            registered_nameservers_set_render($check['nameservers']);
        }
    }
    if ($view['recovered_dispatch']) echo '<p>Missing request history was restored without resending the change.</p>';
    if (!$view['current_assignment']) echo '<p>The assignment or provider connection has changed. This request remains saved for review.</p>';
    echo '</article>';
}

/** Global history remains available after an assignment is removed or replaced. */
function registered_nameserver_changes_history_render(): void {
    provider_settings_manager();
    $page = registered_nameservers_query_page($_GET['nameserver_change_page'] ?? null, 10000);
    [$views, $more] = staff_locked(fn() => staff_transaction(function() use ($page): array {
        if (!registered_nameserver_changes_ready()) return [[], false];
        $rows = all('SELECT id FROM '.table('provider_nameserver_change_requests').' ORDER BY grant_seq DESC LIMIT 21 OFFSET '.(($page - 1) * 20));
        $more = count($rows) > 20;
        return [array_map(fn($row) => registered_nameserver_changes_summary($row['id']), array_slice($rows, 0, 20)), $more];
    }));
    if (!$views && $page === 1) return;
    echo '<details id="nameserver-change-history" data-nameserver-change-history-list'.($page > 1 ? ' open' : '').'><summary>Nameserver change request history</summary>';
    if (!$views) echo '<p>No requests on this page.</p>';
    foreach ($views as $view) registered_nameserver_changes_summary_render($view);
    echo '<nav class="button-row" aria-label="Nameserver change request pages">';
    foreach (['Newer requests' => $page - 1, 'Older requests' => $page + 1] as $label => $target) {
        if ($target < 1 || $target > 10000 || ($target > $page && !$more)) continue;
        echo '<a class="btn outline" href="'.esc(admin_url('provider-settings', ['nameserver_change_page' => $target])).'#nameserver-change-history">'.esc($label).'</a>';
    }
    echo '</nav></details>';
}
