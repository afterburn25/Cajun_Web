<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/stackcp_assignments.php';

/** Nonthrowing redisplay only. Mutation validators remain strict and credential fields are never read here. */
function provider_stackcp_post_value(string $name,int $limit): string {
    $v=$_POST[$name]??'';return is_string($v)&&strlen($v)<=$limit&&preg_match('//u',$v)===1&&!preg_match('/[\x00-\x1f\x7f]/',$v)?$v:'';
}
function provider_stackcp_form_values(string $action,array $defaults): array {
    if (($_POST['action']??null)!==$action) return $defaults;
    $out=[];foreach ($defaults as $key=>$value) {
        $raw=provider_stackcp_post_value($key,$key==='review_sha256'?64:32);
        $out[$key]=match($key) {
            'profile_id','link_id'=>preg_match('/\A[a-f0-9]{32}\z/',$raw)?$raw:'',
            'review_sha256'=>preg_match('/\A[a-f0-9]{64}\z/',$raw)?$raw:'',
            'package_id'=>preg_match('/\A[1-9][0-9]{0,9}\z/',$raw)&&(int)$raw<=2147483647?$raw:'',
            'stack_identity'=>$raw,
            default=>'',
        };
    }
    // Only this exact selected form may retain its captured authority. Invalid or sibling selectors never acquire a fresh review.
    foreach (['profile_id','package_id'] as $selector) if (isset($defaults[$selector])&&($out[$selector]??'')!==(string)$defaults[$selector]) $out['review_sha256']='';
    return $out;
}
function provider_stackcp_hidden(array $values): void {foreach ($values as $name=>$value) echo '<input type="hidden" name="'.esc($name).'" value="'.esc($value).'">';}
function provider_stackcp_password(): void {admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');}
function provider_stackcp_auth_label(string $status): string {return match($status) {'configured'=>'Authentication key stored; not checked with 20i','disabled'=>'Authentication configuration disabled','stale'=>'Stored configuration needs owner review','not_configured'=>'No separate authentication key stored',default=>'Authentication setup unavailable'};}
function provider_stackcp_assignment_label(string $status): string {return match($status) {'unverified'=>'Unverified local assignment','revoked'=>'Local draft revoked','stale'=>'Local assignment needs owner review','not_assigned'=>'No local StackCP assignment',default=>'Local assignment unavailable'};}
/** Pure renderer takes safe projected metadata only. No provider/client link or launch action exists. */
function provider_stackcp_auth_render(array $view,array $history=[]): void {
    echo '<article class="workspace-card" data-stackcp-auth><h3>Separate authentication key</h3><p><strong>'.esc(provider_stackcp_auth_label($view['status'])).'</strong></p><p>Use the distinct OAuth client key, not the General API key. Saving a key makes no provider request and does not enable login.</p>';
    $defaults=['profile_id'=>$view['profile_id'],'review_sha256'=>$view['review_sha256']];
    echo '<form method="post" data-stackcp-auth-save>';admin_action('provider_inventory_stackcp_auth_save');provider_stackcp_hidden(provider_stackcp_form_values('provider_inventory_stackcp_auth_save',$defaults));
    admin_input('auth_client_key','OAuth client key','','password','required maxlength="4096" autocomplete="new-password"');echo '<p class="hint">Enter the key again for every save or rotation. Keys and passwords are never filled back in after an error.</p>';provider_stackcp_password();echo '<button class="btn gold">Save key</button></form>';
    if ($view['revision']>0) {echo '<form method="post" data-stackcp-auth-disable>';admin_action('provider_inventory_stackcp_auth_disable');provider_stackcp_hidden(provider_stackcp_form_values('provider_inventory_stackcp_auth_disable',$defaults));provider_stackcp_password();echo '<button class="btn outline">Disable key</button></form>';}
    if ($history) {echo '<details><summary>Authentication configuration history</summary>';foreach (array_slice($history,0,20) as $r) echo '<p>Version '.(int)$r['revision'].' · '.esc($r['state']==='disabled'?'Disabled':'Key stored, unverified').' · '.esc(gmdate('Y-m-d H:i:s',$r['recorded_epoch'])).' UTC</p>';echo '</details>';}
    echo '</article>';
}
function provider_stackcp_assignment_render(array $view,array $history=[]): void {
    echo '<article class="workspace-card" data-stackcp-assignment><h3>Package '.(int)$view['package_id'].'</h3><p><strong>'.esc(provider_stackcp_assignment_label($view['status'])).'</strong></p>';
    if ($view['stack_identity']!=='') echo '<p>Recorded identity: '.esc($view['stack_identity']).' · Recorded client #'.(int)$view['client_id'].'</p>';
    echo '<p>This records the owner’s local choice only. Saving a draft does not verify remote membership or grants and does not open StackCP. Complete the assignment checks, then review Open StackCP below.</p>';
    if ($view['can_save_draft']) {
        $defaults=['profile_id'=>$view['profile_id'],'package_id'=>(string)$view['package_id'],'link_id'=>$view['current_link_id'],'review_sha256'=>$view['review_sha256'],'stack_identity'=>$view['status']==='unverified'?$view['stack_identity']:''];$values=provider_stackcp_form_values('provider_inventory_stackcp_assignment_save',$defaults);$identity=$values['stack_identity'];unset($values['stack_identity']);
        echo '<form method="post" data-stackcp-assignment-save>';admin_action('provider_inventory_stackcp_assignment_save');provider_stackcp_hidden($values);admin_input('stack_identity','Allocated Stack-user identity',$identity,'text','required maxlength="32" placeholder="stack-user:123"');
        admin_check('acknowledged','I understand this is an unverified local draft and does not enable login.',false);provider_stackcp_password();echo '<button class="btn gold">Save draft</button></form>';
    } else echo '<p class="hint">A current client/package assignment and enabled separate authentication configuration are needed to save a draft.</p>';
    if ($view['assignment_id']!=='') {
        $defaults=['profile_id'=>$view['profile_id'],'package_id'=>(string)$view['package_id'],'link_id'=>$view['original_link_id'],'review_sha256'=>$view['review_sha256'],'stack_identity'=>$view['stack_identity']];
        echo '<form method="post" data-stackcp-assignment-revoke>';admin_action('provider_inventory_stackcp_assignment_revoke');provider_stackcp_hidden(provider_stackcp_form_values('provider_inventory_stackcp_assignment_revoke',$defaults));provider_stackcp_password();echo '<button class="btn outline">Revoke draft</button><p class="hint">This changes local setup only. It does not end a provider session.</p></form>';
    }
    if ($history) {echo '<details><summary>Local assignment history</summary>';foreach (array_slice($history,0,20) as $r) echo '<p>Version '.(int)$r['revision'].' · '.esc($r['stack_identity']).' · Client #'.(int)$r['client_id'].' · '.esc($r['state']==='revoked'?'Revoked':'Unverified').' · '.esc(gmdate('Y-m-d H:i:s',$r['recorded_epoch'])).' UTC</p>';echo '</details>';}
    echo '</article>';
}
function provider_stackcp_admin_render(): void {
    provider_settings_manager();echo '<section class="workspace-card" id="stackcp-prerequisites"><h2>StackCP setup</h2><p>Store the separate authentication key and review local Stack-user assignments. This setup does not create provider sessions or enable one-click access.</p>';
    try {
        $raw=$_GET['stackcp_page']??'1';$page=is_string($raw)&&preg_match('/\A[1-9][0-9]{0,3}\z/',$raw)?min(500,(int)$raw):1;
        $snapshot=ProviderStackCpReadProjection::ownerSnapshot($page);$auth=$snapshot['auth'];
        if ($snapshot['error']!==null && $auth===null) throw new RuntimeException($snapshot['error']);
        if ($snapshot['state']==='account_missing') {echo '<p>Save your 20i account first.</p></section>';return;}
        if ($snapshot['state']==='auth_unprepared') {echo '<p>Prepare local protected setup storage first. Back up the database and private provider key together.</p><form method="post">';admin_action('provider_inventory_stackcp_auth_prepare');provider_stackcp_password();echo '<button class="btn gold">Prepare key storage</button></form></section>';return;}
        if ($auth!==null && $auth['prepared']) provider_stackcp_auth_render($auth,$snapshot['auth_history']);
        if ($snapshot['error']!==null) throw new RuntimeException($snapshot['error']);
        if ($snapshot['state']==='inventory_unprepared') {echo '<p>Prepare hosting inventory before creating local assignments.</p></section>';return;}
        if ($snapshot['state']==='assignment_unprepared') {echo '<form method="post">';admin_action('provider_inventory_stackcp_assignment_prepare');provider_stackcp_password();echo '<button class="btn gold">Prepare assignments</button></form></section>';return;}
        if (!$snapshot['packages']) echo '<p>Assign a hosting package to a client before reviewing a Stack-user draft.</p>';
        foreach ($snapshot['packages'] as $row) {
            if ($row['error']!==null) {echo '<p class="notice error" role="alert">Package '.$row['package_id'].': '.esc($row['error']).'</p>';continue;}
            provider_stackcp_assignment_render($row['view'],$row['history']);
        }
        echo '<nav class="button-row" aria-label="Local StackCP assignment pages">';if($page>1)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['stackcp_page'=>$page-1])).'#stackcp-prerequisites">Previous</a>';if($snapshot['has_more']&&$page<500)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['stackcp_page'=>$page+1])).'#stackcp-prerequisites">More assignments</a>';echo '</nav>';
    } catch (InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;echo '<p class="notice error" role="alert">'.esc($e->getMessage()).'</p>';}
    echo '</section>';
}
