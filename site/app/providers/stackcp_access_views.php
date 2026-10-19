<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/stackcp_access.php';
require_once CWP_CODE_ROOT.'/app/providers/stackcp_prerequisite_views.php';
use CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding;
function provider_stackcp_access_label(?array $saved):string {
    if ($saved===null) return 'Not observed';
    if ($saved['state']==='context_changed') return 'Saved observation belongs to an older account or assignment';
    if ($saved['state']==='unfinished') return 'The latest read did not finish';
    if ($saved['state']!=='complete') return 'The latest read is unavailable';
    if (!$saved['current']) return 'Saved observation needs a fresh check';
    if ($saved['empty']) return 'An empty response was saved; access is not verified';
    return $saved['matches']?'Selected assignment matched this observation':'The observation did not match the selected assignment';
}
/** Pure safe renderer; form retries retain only their submitted authority, never a newly issued binding. */
function provider_stackcp_access_render(array $view,int $page=1):void {
    echo '<article class="workspace-card" data-stackcp-access-package="'.(int)$view['package_id'].'"><h3>Package '.(int)$view['package_id'].'</h3>';
    if (isset($view['stack_identity'])) echo '<p>Selected local identity: '.esc($view['stack_identity']).'</p>';
    if ($view['status']==='assignment_or_source_unavailable') echo '<p>A current local assignment, separate authentication configuration and fresh hosting inventory are needed.</p>';
    if ($view['status']==='observed_match') echo '<p><strong>Assignment matched two saved observations.</strong></p>';
    echo '<p>These separate checks do not create a login or restrict a provider session. Whole-user grants can change between checks.</p>';
    foreach (['membership'=>[StackCpAccessBinding::MEMBERSHIP,'Package membership','Read members'],'grants'=>[StackCpAccessBinding::GRANTS,'Whole-user grants','Read grants']] as $key=>[$operation,$title,$button]) {
        $saved=$view[$key];echo '<div data-stackcp-access-kind="'.esc($key).'"><h4>'.esc($title).'</h4><p><strong>'.esc(provider_stackcp_access_label($saved)).'</strong></p>';
        if ($saved&&$saved['observed_epoch']>0) echo '<p>Observed '.esc(gmdate('Y-m-d H:i:s',$saved['observed_epoch'])).' UTC'.($saved['count']!==null?' · '.(int)$saved['count'].' validated entries':'').'</p>';
        if (isset($view['reviews'][$operation])) {
            $action='provider_inventory_stackcp_access_'.$key;$values=provider_stackcp_form_values($action,['profile_id'=>$view['profile_id'],'package_id'=>(string)$view['package_id'],'review_sha256'=>$view['reviews'][$operation]]);
            echo '<form method="post">';admin_action($action);provider_stackcp_hidden($values+['stackcp_access_page'=>(string)$page]);
            admin_check('confirmed','Read and save this access observation. This does not enable login.',false);provider_stackcp_password();echo '<button class="btn gold">'.esc($button).'</button></form>';
        } elseif ($key==='grants') echo '<p class="hint">First save a fresh package membership response containing this exact selected Stack user.</p>';
        echo '</div>';
    }
    echo '</article>';
}
function provider_stackcp_access_admin_render():void {
    provider_settings_manager();echo '<section class="workspace-card" id="stackcp-access-observations"><h2>Check StackCP assignments</h2><p>Read package membership and whole-user grants explicitly. Results are dated observations only. After both checks match, review the separate Open StackCP action below. Remote session revocation remains unavailable.</p>';
    try {
        $raw=$_GET['stackcp_access_page']??'1';$page=is_string($raw)&&preg_match('/\A[1-9][0-9]{0,3}\z/',$raw)?min(500,(int)$raw):1;
        $snapshot=ProviderStackCpReadProjection::ownerAccessSnapshot($page);
        if ($snapshot['error']!==null && !$snapshot['packages']) throw new RuntimeException($snapshot['error']);
        if ($snapshot['state']==='account_missing') {echo '<p>Save your 20i account first.</p></section>';return;}
        $ready=$snapshot['state']==='ready';
        if (!$ready) {echo '<p>Prepare protected StackCP key and assignment storage first, then enable these observation records. Back up the database and protected master key together.</p><form method="post">';admin_action('provider_inventory_stackcp_access_prepare');provider_stackcp_password();echo '<button class="btn gold">Prepare observations</button></form></section>';return;}
        $packages=$snapshot['packages'];
        if (!$packages) echo '<p>Save an owner-reviewed Stack-user assignment before reading access.</p>';
        foreach ($packages as $view) provider_stackcp_access_render($view,$page);
        if ($snapshot['error']!==null) throw new RuntimeException($snapshot['error']);
        echo '<nav class="button-row" aria-label="StackCP access observation pages">';if ($page>1) echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['stackcp_access_page'=>$page-1])).'#stackcp-access-observations">Previous</a>';if ($snapshot['has_more']&&$page<500) echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['stackcp_access_page'=>$page+1])).'#stackcp-access-observations">More packages</a>';echo '</nav>';
    } catch (InvalidArgumentException|RuntimeException $e) {if ($e instanceof PDOException) throw $e;echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';}
    echo '</section>';
}
