<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/document_root_controls.php';

function provider_document_root_controls_label(string $status):string {
    return match($status){
        'observed'=>'Requested folder observed after the request',
        'not_sent'=>'Refused before provider dispatch',
        'interrupted'=>'Interrupted request — local recovery required',
        default=>'Folder change requested — effect is uncertain',
    };
}

/** Retain only a bounded failed form for this exact saved context; never renew its approval. */
function provider_document_root_controls_retry(string $linkId,string $anchorId,string $bindingHash,array $entries):?array {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'||($_POST['action']??null)!=='provider_document_root_control_change'||($_POST['link_id']??null)!==$linkId)return null;
    foreach(['request_id'=>32,'anchor_id'=>32,'binding_sha256'=>64,'name'=>253,'desired_path'=>1024] as $key=>$limit){
        $value=$_POST[$key]??null;
        if(!is_string($value)||strlen($value)>$limit||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))return ['stale'=>true];
    }
    if(preg_match('/\A[a-f0-9]{32}\z/',$_POST['request_id'])!==1||$_POST['anchor_id']!==$anchorId||$_POST['binding_sha256']!==$bindingHash)return ['stale'=>true];
    if(!in_array($_POST['name'],array_column($entries,'name'),true))return ['stale'=>true];
    return ['stale'=>false,'request_id'=>$_POST['request_id'],'name'=>$_POST['name'],'desired_path'=>$_POST['desired_path']];
}

function provider_document_root_controls_path_text(string $path):void {
    if($path===''){echo '<p>Empty text value</p>';return;}
    echo '<pre style="white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;max-width:100%">'.esc($path).'</pre>';
}

function provider_document_root_controls_admin_render():void {
    provider_settings_manager();
    echo '<section class="workspace-card" id="website-folder-changes"><h2>Change a website folder</h2><p>Changing a website folder can affect where that website serves its pages. Review the saved folders, select an existing website and enter its new relative folder.</p><p>20i does not support folder changes for Managed WordPress. Confirm that this hosting supports the change and that the destination folder already exists. This page cannot check either condition. Client observation sharing remains separate.</p>';
    try{
        if(!provider_document_roots_ready()||!provider_operations_ready()){echo '<p>Prepare website folder observations and provider operations first.</p></section>';return;}
        if(!provider_document_root_controls_ready()){
            echo '<form method="post" data-document-root-control-prepare>';admin_action('provider_document_root_control_prepare');
            admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');
            echo '<button class="btn gold">Enable website folder change requests</button></form></section>';return;
        }
        $page=max(1,min(10000,(int)($_GET['document_root_control_page']??1)));
        $links=all('SELECT l.id,l.profile_id,l.package_id,c.name AS client_name FROM '.table('provider_package_links').' l JOIN '.table('studio_clients').' c ON c.id=l.client_id ORDER BY l.id LIMIT 20 OFFSET '.(($page-1)*20));
        if($links===[])echo '<p>No hosting assignments are on this page. Saved requests remain in the history below.</p>';
        foreach($links as $link){
            echo '<article class="workspace-card" data-document-root-control-package><h3>'.esc($link['client_name']).' · Package '.esc((string)$link['package_id']).'</h3>';
            try{
                $form=staff_locked(fn()=>staff_transaction(function()use($link):array{
                    if(!provider_document_root_controls_ready())throw new RuntimeException('Website folder change storage is unavailable.');
                    $pending=provider_document_root_controls_pending($link['profile_id'],(int)$link['package_id']);
                    if($pending)return ['pending'=>true];
                    $r=provider_document_roots_latest($link['id']);
                    if(!$r)throw new RuntimeException('Save a website folder observation first.');
                    $binding=provider_document_roots_binding($link['id']);
                    [$binding,$anchor]=provider_document_root_controls_precondition($link['id'],$r['id'],$binding->sha256);
                    return ['pending'=>false,'binding'=>$binding,'anchor'=>$anchor];
                }));
                if($form['pending'])echo '<p data-document-root-control-pending>A prior request for this package remains unresolved. Use its history below; another change cannot be sent.</p>';
                else{
                    $anchor=$form['anchor'];$entries=$anchor['entries'];
                    echo '<div data-document-root-control-map><h4>Latest saved folders</h4><p>'.esc(gmdate('Y-m-d H:i:s',$anchor['observed_epoch'])).' UTC</p>';
                    foreach($entries as $entry){echo '<div data-document-root-control-entry><p style="overflow-wrap:anywhere"><strong>'.esc($entry['name']).'</strong></p>';provider_document_root_controls_path_text($entry['path']);echo '</div>';}
                    if($entries===[])echo '<p>The provider returned an empty map. There is no saved website to select for a change.</p>';
                    echo '</div>';
                    $retry=provider_document_root_controls_retry($link['id'],$anchor['id'],$form['binding']->sha256,$entries);
                    if($retry!==null&&$retry['stale'])echo '<p class="notice error" data-document-root-control-stale>These details changed or the submitted form was invalid. Reload and review the latest folders before trying again.</p>';
                    elseif($entries!==[]){
                        echo '<form method="post" data-document-root-control-change>';admin_action('provider_document_root_control_change');
                        foreach(['request_id'=>$retry['request_id']??bin2hex(random_bytes(16)),'link_id'=>$link['id'],'anchor_id'=>$anchor['id'],'binding_sha256'=>$form['binding']->sha256] as $name=>$value)echo '<input type="hidden" name="'.$name.'" value="'.esc($value).'">';
                        echo '<label>Website<select name="name" required><option value="">Choose a saved website</option>';
                        foreach($entries as $entry)echo '<option value="'.esc($entry['name']).'"'.(($retry['name']??'')===$entry['name']?' selected':'').'>'.esc($entry['name']).'</option>';
                        echo '</select></label>';
                        admin_input('desired_path','New relative folder',$retry['desired_path']??'','text','required maxlength="1024" autocomplete="off" spellcheck="false" placeholder="public_html/newsite"');
                        echo '<p class="hint">Use a relative folder such as public_html/newsite, with letters, digits, underscores, hyphens or dots in folder names. No leading slash, spaces or parent-directory segments.</p><label class="check"><input type="checkbox" name="reviewed" value="1" required> I verified that this hosting supports website folder changes, that the destination folder already exists, and I approve this one request.</label>';
                        admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');
                        echo '<button class="btn gold">Request folder change</button><p class="hint">After sending, read the folder separately. A provider response alone does not confirm the change.</p></form>';
                    }
                }
            }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="hint">'.esc($e->getMessage()).'</p>';}
            echo '</article>';
        }
        echo '<nav class="button-row" aria-label="Website folder change assignments">';
        if($page>1)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['document_root_control_page'=>$page-1])).'#website-folder-changes">Previous assignments</a>';
        if(count($links)===20&&$page<10000)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['document_root_control_page'=>$page+1])).'#website-folder-changes">More assignments</a>';
        echo '</nav><h3>Change request history</h3>';
        $historyPage=max(1,min(10000,(int)($_GET['document_root_change_history_page']??1)));
        $history=all('SELECT id FROM '.table('provider_document_root_changes').' ORDER BY grant_seq DESC LIMIT 20 OFFSET '.(($historyPage-1)*20));
        if($history===[])echo '<p>No saved requests are on this page.</p>';
        foreach($history as $row){
            $view=staff_locked(fn()=>staff_transaction(fn()=>provider_document_root_controls_summary($row['id'])));
            echo '<article class="workspace-message" data-document-root-control-history><h4>'.esc(provider_document_root_controls_label($view['status'])).'</h4><p style="overflow-wrap:anywhere">'.esc($view['name']).' · Package '.esc((string)$view['package_id']).' · '.esc(gmdate('Y-m-d H:i:s',$view['requested_epoch'])).' UTC</p><p>Folder saved before the request</p>';
            provider_document_root_controls_path_text($view['before_path']);echo '<p>Requested folder</p>';provider_document_root_controls_path_text($view['requested_path']);
            if($view['status']==='interrupted'){
                echo '<p>The local dispatch record is missing. This request remains unresolved and another change cannot be sent.</p>';
                if($view['recovery_ledger_sha256']!==null){
                    echo '<form method="post" data-document-root-control-recover>';admin_action('provider_document_root_control_recover');
                    echo '<input type="hidden" name="request_id" value="'.esc($view['id']).'"><input type="hidden" name="ledger_sha256" value="'.esc($view['recovery_ledger_sha256']).'"><p>Restore only the missing local record from the saved completed attempt. This does not resend the change or confirm its effect.</p><label class="check"><input type="checkbox" name="reviewed" value="1" required> I reviewed this request and its saved attempt.</label>';
                    admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn outline">Restore local dispatch record</button></form>';
                }else echo '<p>No eligible completed attempt is available for recovery by this owner in the current context. Missing or unfinished provider records remain blocked.</p>';
            }elseif($view['status']==='not_sent')echo '<p>No provider dispatch was entered. Save a new website folder observation before a separate approval.</p>';
            elseif($view['status']==='observed')echo '<p>A later read reported the requested folder. This does not prove this request caused the value or that website routing works. The original request outcome remains unchanged.</p>';
            else{
                echo '<p>The provider response does not establish the effect of this change. A failed, unavailable or different later observation does not permit resending it.</p>';
                if($view['can_verify']){
                    echo '<form method="post" data-document-root-control-verify>';admin_action('provider_document_root_control_verify');echo '<input type="hidden" name="request_id" value="'.esc($view['id']).'">';
                    admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');echo '<button class="btn outline">Read folders after this request</button><p class="hint">One new read, subject to the existing refresh limits. This does not resend the change.</p></form>';
                }elseif($view['current_assignment'])echo '<p>This request has no eligible completed dispatch for follow-up verification. It remains unresolved; another change cannot be sent.</p>';
            }
            if($view['last_check']){
                echo '<div data-document-root-control-readback><p>Follow-up at '.esc(gmdate('Y-m-d H:i:s',$view['last_check']['recorded_epoch'])).' UTC: '.esc(match($view['last_check']['disposition']){'observed'=>'the requested folder was reported','different'=>'a different folder or no entry for this website was reported',default=>'no usable folder map was reported'}).'.</p>';
                if($view['last_check']['path']!==null)provider_document_root_controls_path_text($view['last_check']['path']);echo '</div>';
            }
            if($view['recovered_dispatch'])echo '<p data-document-root-control-recovered>The local dispatch record was restored from the saved attempt. Restoration did not resend the change or confirm its effect.</p>';
            if(!$view['current_assignment'])echo '<p>The original assignment or provider context is no longer current. The request remains saved for review.</p>';
            echo '</article>';
        }
        echo '<nav class="button-row" aria-label="Website folder change history">';
        if($historyPage>1)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['document_root_change_history_page'=>$historyPage-1])).'#website-folder-changes">Previous requests</a>';
        if((int)query('SELECT COUNT(*) FROM '.table('provider_document_root_changes'))->fetchColumn()>$historyPage*20&&$historyPage<10000)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['document_root_change_history_page'=>$historyPage+1])).'#website-folder-changes">More requests</a>';
        echo '</nav>';
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error">'.esc($e->getMessage()).'</p>';}
    echo '</section>';
}
