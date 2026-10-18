<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/registered_nameservers.php';

/** Render only the bounded saved-data projection, never its private binding. */
function registered_nameservers_render(array $projection): void {
    $labels = [
        'not_observed' => 'No nameserver observation has been saved for this assignment.',
        'observed' => 'Latest saved observation',
        'stale' => 'The saved observation is older than 15 minutes. Refresh it to see a current result.',
        'unfinished' => 'The latest nameserver refresh has not completed.',
        'unavailable' => 'Nameservers are unavailable from the latest refresh.',
        'refused' => 'The latest nameserver refresh could not be completed.',
        'access_changed' => 'The assignment or account changed during the latest refresh.',
        'credential' => 'Review the saved 20i connection before refreshing nameservers.',
        'assignment_changed' => 'Refresh nameservers for the current assignment.',
    ];
    $status = $projection['status'] ?? '';
    echo '<div data-registered-nameservers><h3 style="overflow-wrap:anywhere">'.esc($projection['domain']).'</h3>';
    echo '<p>'.esc($labels[$status] ?? 'The saved nameserver observation needs review.').'</p>';
    if ($status === 'observed' && is_array($projection['nameservers']) && is_int($projection['observed_epoch'])) {
        echo '<div data-nameserver-current><p class="hint">Observed '.esc(gmdate('Y-m-d H:i:s', $projection['observed_epoch'])).' UTC</p>';
        registered_nameservers_set_render($projection['nameservers']);
        echo '</div>';
    }
    echo '<p class="hint">This saved observation does not confirm propagation or permission to change nameservers.</p>';
    if ($projection['history']) {
        $open = $projection['pagination']['page'] > 1 ? ' open' : '';
        echo '<details data-nameserver-history'.$open.'><summary>Saved nameserver history</summary><ol>';
        foreach ($projection['history'] as $row) {
            $complete = $row['state'] === 'complete' && is_array($row['nameservers']);
            echo '<li><p>'.($complete ? 'Observed ' : 'Refresh attempted ').esc(gmdate('Y-m-d H:i:s', $row['observed_epoch'])).' UTC';
            if (!$row['current_assignment']) echo ' <strong>— Previous assignment</strong>';
            echo '</p>';
            if ($complete) registered_nameservers_set_render($row['nameservers']);
            else echo '<p>'.esc($labels[$row['state']] ?? 'Nameservers were unavailable for this refresh.').'</p>';
            echo '</li>';
        }
        echo '</ol></details>';
    }
    echo '</div>';
}

function registered_nameservers_set_render(array $nameservers): void {
    if (!$nameservers) {
        echo '<p>No nameservers were returned in this observation.</p>';
        return;
    }
    echo '<ul style="overflow-wrap:anywhere">';
    foreach ($nameservers as $hostname) echo '<li>'.esc($hostname).'</li>';
    echo '</ul>';
}

function registered_nameservers_prepare_form(): void {
    echo '<form method="post" data-nameserver-prepare>';
    admin_action('provider_inventory_nameservers_prepare');
    admin_input('current_password', 'Your current owner password', '', 'password', 'required autocomplete="current-password"');
    echo '<button class="btn gold">Enable nameserver observations</button></form>';
}

function registered_nameservers_refresh_form(array $binding): void {
    echo '<form method="post" data-nameserver-refresh>';
    admin_action('provider_inventory_nameservers_refresh');
    foreach (['link_id' => $binding['link_id'], 'link_revision' => $binding['link_revision'],
        'profile_revision' => $binding['profile_revision'], 'refresh_id' => $binding['source_refresh_id']] as $name => $value) {
        echo '<input type="hidden" name="'.esc($name).'" value="'.esc((string)$value).'">';
    }
    admin_input('current_password', 'Your current owner password', '', 'password', 'required autocomplete="current-password"');
    echo '<button class="btn outline">Refresh nameservers</button></form>';
}

function registered_nameservers_history_navigation(array $pagination, string $linkId, int $domainPage): void {
    if ($pagination['pages'] < 2) return;
    echo '<nav class="button-row" aria-label="Saved nameserver history pages">';
    foreach (['Newer observations' => $pagination['page'] - 1, 'Older observations' => $pagination['page'] + 1] as $label => $page) {
        if ($page < 1 || $page > $pagination['pages']) continue;
        $url = admin_url('provider-settings', ['nameserver_page' => $domainPage, 'nameserver_link' => $linkId, 'nameserver_history_page' => $page]);
        echo '<a class="btn outline" href="'.esc($url).'#nameserver-'.esc($linkId).'">'.esc($label).'</a>';
    }
    echo '<span class="hint">History page '.(int)$pagination['page'].' of '.(int)$pagination['pages'].'</span></nav>';
}

function registered_nameservers_query_page(mixed $value, int $maximum): int {
    if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,6}\z/', $value)) return 1;
    return min($maximum, (int)$value);
}

function registered_nameservers_admin_render(): void {
    provider_settings_manager();
    echo '<section class="workspace-card" id="registered-nameservers"><h2>Registered nameservers</h2>';
    echo '<p>Save the nameservers reported by 20i for an assigned registered domain.</p>';
    try {
        $ready = staff_locked(fn() => staff_transaction(fn() => provider_inventory_ready() && provider_registrar_ready() && registered_nameservers_ready()));
    } catch (InvalidArgumentException|RuntimeException $error) {
        if ($error instanceof PDOException) throw $error;
        echo '<p class="notice error">'.esc($error->getMessage()).'</p></section>';
        return;
    }
    if (!$ready) {
        registered_nameservers_prepare_form();
        echo '<p class="hint">Prepare registered domains above first.</p></section>';
        return;
    }
    $page = registered_nameservers_query_page($_GET['nameserver_page'] ?? null, 10000);
    $historyPage = registered_nameservers_query_page($_GET['nameserver_history_page'] ?? null, 1000000);
    $historyLink = $_GET['nameserver_link'] ?? '';
    if (!is_string($historyLink) || !preg_match('/\A[a-f0-9]{32}\z/', $historyLink)) $historyLink = '';
    $links = all('SELECT l.id,l.revision,c.name AS client_name FROM '.table('provider_registrar_links').' l JOIN '.table('studio_clients').' c ON c.id=l.client_id ORDER BY l.domain,l.id LIMIT 20 OFFSET '.(($page - 1) * 20));
    if (!$links) echo '<p>Assign a registered domain to a client first.</p>';
    foreach ($links as $link) {
        echo '<article class="workspace-card" id="nameserver-'.esc($link['id']).'"><p style="overflow-wrap:anywhere">'.esc($link['client_name']).'</p>';
        try {
            [$binding, $projection] = staff_locked(fn() => staff_transaction(fn() => [
                registered_nameservers_binding($link['id'], (int)$link['revision']),
                registered_nameservers_project_locked($link['id'], (int)$link['revision'], $historyLink === $link['id'] ? $historyPage : 1),
            ]));
            registered_nameservers_render($projection);
            registered_nameservers_history_navigation($projection['pagination'], $link['id'], $page);
            registered_nameservers_refresh_form($binding->data);
        } catch (InvalidArgumentException|RuntimeException $error) {
            if ($error instanceof PDOException) throw $error;
            echo '<p class="notice error">'.esc($error->getMessage()).'</p>';
        }
        echo '</article>';
    }
    echo '<nav class="button-row" aria-label="Registered nameserver domain pages">';
    if ($page > 1) echo '<a class="btn outline" href="'.esc(admin_url('provider-settings', ['nameserver_page' => $page - 1])).'#registered-nameservers">Previous domains</a>';
    if (count($links) === 20 && $page < 10000) echo '<a class="btn outline" href="'.esc(admin_url('provider-settings', ['nameserver_page' => $page + 1])).'#registered-nameservers">More domains</a>';
    echo '</nav></section>';
}
