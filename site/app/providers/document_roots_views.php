<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/document_roots.php';
function provider_document_roots_label(string $status):string {
    return match($status){'observed'=>'Latest saved website folders','stale'=>'Saved website folders are out of date','unfinished'=>'Latest refresh has no saved result','complete'=>'Saved website folders','assignment_changed'=>'The studio needs to confirm this hosting assignment','not_shared'=>'Website folder sharing is off','not_observed'=>'No website folders have been saved','unavailable','refused','credential','access_changed'=>'Latest refresh did not provide website folders',default=>'Website folders are unavailable'};
}
function provider_document_roots_render(array $view):void {
    echo '<div data-document-roots-observation><p><strong>'.esc(provider_document_roots_label($view['status'])).'</strong></p><p class="hint">Website names and folder values reported by the hosting provider at the time shown. These are saved observations.</p>';
    foreach($view['rows'] as $r){
        echo '<article class="workspace-message"><p>'.esc(gmdate('Y-m-d H:i:s',$r['observed_epoch'])).' UTC · '.($r['current']?'Latest saved observation':'Historical observation').'</p>';
        if($r['entries']===[])echo '<p data-document-roots-empty>The provider returned an empty map. This does not establish that the website has no names or folders.</p>';
        else foreach($r['entries'] as $entry){
            echo '<div data-document-root-entry><h3 style="overflow-wrap:anywhere">'.esc($entry['name']).'</h3><p class="hint">Observed folder value</p>';
            if($entry['path']==='')echo '<p data-document-root-empty-path>Empty text value</p>';
            else echo '<pre style="white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;max-width:100%">'.esc($entry['path']).'</pre>';
            echo '</div>';
        }
        echo '</article>';
    }
    echo '</div>';
}
function provider_document_roots_admin_render():void {
    provider_settings_manager();echo '<section class="workspace-card" id="document-roots"><h2>Website folders</h2><p>Refresh the reported website names and folder values for an assigned hosting package. Share its saved observation with that client separately.</p>';
    try{
        if(!provider_inventory_ready()){echo '<p>Prepare hosting inventory first.</p></section>';return;}
        if(!provider_document_roots_ready()){echo '<form method="post">';admin_action('provider_inventory_document_roots_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Enable website folder observations</button></form></section>';return;}
        $page=max(1,min(10000,(int)($_GET['document_roots_page']??1)));$links=all('SELECT l.id,l.package_id,c.name AS client_name FROM '.table('provider_package_links').' l JOIN '.table('studio_clients').' c ON c.id=l.client_id ORDER BY l.id LIMIT 20 OFFSET '.(($page-1)*20));if(!$links)echo '<p>Assign a hosting package to a client first.</p>';
        foreach($links as $link){echo '<article class="workspace-card" data-document-roots-owner-package><h3>'.esc($link['client_name']).' · Package '.esc((string)$link['package_id']).'</h3>';
            try{
                $view=staff_locked(fn()=>staff_transaction(fn()=>provider_document_roots_project($link['id'],false)));provider_document_roots_render($view);$permission=$view['permission'];$binding=null;
                try{$binding=staff_locked(fn()=>staff_transaction(fn()=>provider_document_roots_binding($link['id'])));}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="hint">'.esc($e->getMessage()).'</p>';}
                if($binding){echo '<form method="post" data-document-roots-refresh>';admin_action('provider_inventory_document_roots_refresh');foreach(['link_id'=>$link['id'],'binding_sha256'=>$binding->sha256] as $name=>$value)echo '<input type="hidden" name="'.$name.'" value="'.esc($value).'">';admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn gold">Refresh website folders</button><p class="hint">One read for this website. Hosting inventory must be complete and less than 15 minutes old.</p></form>';}
                echo '<p>'.($permission&&$permission['document']['enabled']?'Sharing is approved for the recorded assignment.':'Website folder sharing is off.').'</p>';
                if($permission||$view['status']==='observed'){echo '<form method="post" data-document-roots-sharing>';admin_action('provider_inventory_document_roots_share');foreach(['link_id'=>$link['id'],'expected_id'=>$permission['document']['id']??'','event_id'=>bin2hex(random_bytes(16)),'anchor_id'=>$view['latest_id']??''] as $name=>$value)echo '<input type="hidden" name="'.$name.'" value="'.esc($value).'">';admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<div class="button-row">';if($view['status']==='observed')echo '<button class="btn gold" name="sharing" value="enable">Share saved folders</button>';if($permission)echo '<button class="btn outline" name="sharing" value="disable">Turn off folder sharing</button>';echo '</div><p class="hint">Sharing covers this reviewed observation and later reads for the same approved assignment.</p></form>';}
            }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error">'.esc($e->getMessage()).'</p>';}echo '</article>';
        }
        echo '<nav class="button-row" aria-label="Website folder assignments">';if($page>1)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['document_roots_page'=>$page-1])).'#document-roots">Previous assignments</a>';if(count($links)===20&&$page<10000)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['document_roots_page'=>$page+1])).'#document-roots">More assignments</a>';echo '</nav>';
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error">'.esc($e->getMessage()).'</p>';}echo '</section>';
}
