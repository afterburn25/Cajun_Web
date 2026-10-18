<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/package_php.php';
function provider_php_label(string $status):string {return match($status){'observed'=>'Latest saved PHP version','stale'=>'Saved PHP version is out of date','unfinished'=>'Latest refresh has no saved result','complete'=>'Saved PHP version','assignment_changed'=>'The studio needs to confirm this hosting assignment','not_shared'=>'PHP version sharing is off','not_observed'=>'No PHP version has been saved','unavailable','refused','credential','access_changed'=>'Latest refresh did not provide a PHP version',default=>'PHP version is unavailable'};}
function provider_php_render(array $view):void {
    echo '<div data-php-observation><p><strong>'.esc(provider_php_label($view['status'])).'</strong></p><p class="hint">The PHP version reported for this website at the time shown. This page displays a saved observation.</p>';
    foreach($view['rows'] as $r)echo '<article class="workspace-message"><h3 style="overflow-wrap:anywhere">'.esc($r['value']).'</h3><p>'.esc(gmdate('Y-m-d H:i:s',$r['observed_epoch'])).' UTC · '.($r['current']?'Latest saved observation':'Historical observation').'</p></article>';
    echo '</div>';
}
function provider_php_admin_render():void {
    provider_settings_manager();echo '<section class="workspace-card" id="package-php-version"><h2>Website PHP versions</h2><p>Refresh the reported PHP version for an assigned hosting package. Share its saved observation with that client separately.</p>';
    try{
        if(!provider_inventory_ready()){echo '<p>Prepare hosting inventory first.</p></section>';return;}
        if(!provider_php_ready()){echo '<form method="post">';admin_action('provider_inventory_php_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable PHP observations</button></form></section>';return;}
        $page=max(1,min(10000,(int)($_GET['php_page']??1)));$links=all('SELECT l.id,l.package_id,c.name AS client_name FROM '.table('provider_package_links').' l JOIN '.table('studio_clients').' c ON c.id=l.client_id ORDER BY l.id LIMIT 20 OFFSET '.(($page-1)*20));if(!$links)echo '<p>Assign a hosting package to a client first.</p>';
        foreach($links as $link){echo '<article class="workspace-card" data-php-owner-package><h3>'.esc($link['client_name']).' · Package '.esc((string)$link['package_id']).'</h3>';
            try{
                $view=staff_locked(fn()=>staff_transaction(fn()=>provider_php_project($link['id'],false)));provider_php_render($view);$permission=$view['permission'];$binding=null;
                try{$binding=staff_locked(fn()=>staff_transaction(fn()=>provider_php_binding($link['id'])));}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="hint">'.esc($e->getMessage()).'</p>';}
                if($binding){echo '<form method="post" data-php-refresh>';admin_action('provider_inventory_php_refresh');foreach(['link_id'=>$link['id'],'binding_sha256'=>$binding->sha256] as $name=>$value)echo '<input type="hidden" name="'.$name.'" value="'.esc($value).'">';admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Refresh PHP version</button><p class="hint">One read for this website. Hosting inventory must be complete and less than 15 minutes old.</p></form>';}
                echo '<p>'.($permission&&$permission['document']['enabled']?'Sharing is approved for the recorded assignment.':'PHP version sharing is off.').'</p>';
                if($permission||$view['status']==='observed'){echo '<form method="post" data-php-sharing>';admin_action('provider_inventory_php_share');foreach(['link_id'=>$link['id'],'expected_id'=>$permission['document']['id']??'','event_id'=>bin2hex(random_bytes(16)),'anchor_id'=>$view['latest_id']??''] as $name=>$value)echo '<input type="hidden" name="'.$name.'" value="'.esc($value).'">';admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<div class="button-row">';if($view['status']==='observed')echo '<button class="btn gold" name="sharing" value="enable">Share saved PHP version</button>';if($permission)echo '<button class="btn outline" name="sharing" value="disable">Turn off PHP sharing</button>';echo '</div><p class="hint">Sharing covers this reviewed observation and later reads for the same approved assignment.</p></form>';}
            }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error">'.esc($e->getMessage()).'</p>';}echo '</article>';
        }
        echo '<nav class="button-row" aria-label="PHP observation assignments">';if($page>1)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['php_page'=>$page-1])).'#package-php-version">Previous assignments</a>';if(count($links)===20&&$page<10000)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['php_page'=>$page+1])).'#package-php-version">More assignments</a>';echo '</nav>';
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error">'.esc($e->getMessage()).'</p>';}echo '</section>';
}
