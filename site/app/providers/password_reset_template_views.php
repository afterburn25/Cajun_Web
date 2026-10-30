<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/password_reset_template.php';

function provider_password_reset_template_hidden(string $name,string $value):void {echo '<input type="hidden" name="'.esc($name).'" value="'.esc($value).'">';}
function provider_password_reset_template_password():void {admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');}
function provider_password_reset_template_source(string $label,string $content):void {
    echo '<div class="workspace-card"><h4>'.esc($label).'</h4><pre class="prose" style="white-space:pre-wrap;overflow-wrap:anywhere;max-height:28rem;overflow:auto">'."\n".esc($content).'</pre></div>';
}
function provider_password_reset_template_status(string $state):string {
    return match($state){'observed'=>'Requested template reported by 20i','not_sent'=>'Request was not sent','accepted_pending'=>'20i acknowledged the request; confirmation pending','different'=>'20i reports a different template','interrupted'=>'Request interrupted; review required',default=>'Result not yet confirmed'};
}
function provider_password_reset_template_admin_render():void {
    provider_settings_manager();
    echo '<details class="workspace-card" id="password-reset-template"'.(str_starts_with((string)($_POST['action']??''),'provider_reset_template_')?' open':'').'><summary><strong>StackCP password-reset email</strong></summary><p>Read and edit the email template used by your 20i account. Review the full replacement before saving, then check the template reported by 20i. This does not send an email or reset a password.</p>';
    try{
        $profile=provider_settings_profile();if(!$profile){echo '<p>Save your 20i connection first.</p></details>';return;}
        $page=max(1,min(10000,(int)($_GET['reset_template_page']??1)));
        $screen=provider_password_reset_template_screen($profile['id'],$page);
        if(!$screen['ready']){
            echo '<form method="post" data-reset-template-prepare>';admin_action('provider_reset_template_prepare');provider_password_reset_template_password();echo '<button class="btn gold">Set up template controls</button></form></details>';return;
        }
        echo '<form method="post" data-reset-template-refresh>';admin_action('provider_reset_template_refresh');provider_password_reset_template_hidden('profile_id',$profile['id']);provider_password_reset_template_hidden('revision',(string)$profile['revision']);provider_password_reset_template_password();echo '<button class="btn outline">Read current template from 20i</button></form>';
        $review=$GLOBALS['providerResetTemplateReview']??null;$retainedDraft=null;
        if($review===null&&($_POST['action']??'')==='provider_reset_template_change'){
            try{
                $retainedDraft=provider_password_reset_template_post_content(true);
                $retry=provider_password_reset_template_review(posted('request_id',32),posted('profile_id',32),provider_password_reset_template_post_revision(),posted('observation_id',32),$retainedDraft);
                // A retry keeps the original proof; rendering never approves changed or stale text.
                if(!hash_equals($retry['review_sha256'],posted('review_sha256',64)))throw new RuntimeException('This review is no longer current.');
                $review=$retry;
            }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error">This replacement needs a current review before it can be saved. Any previously admitted request remains in the history below.</p>';}
        }
        if(is_array($review)){
            echo '<article class="workspace-card" data-reset-template-review><h3>Review your replacement</h3><div class="workspace-grid">';provider_password_reset_template_source('Current saved template',$review['before_content']);provider_password_reset_template_source('Proposed template',$review['content']);echo '</div><form method="post">';admin_action('provider_reset_template_change');
            foreach(['request_id','profile_id','revision','observation_id','review_sha256'] as $key)provider_password_reset_template_hidden($key,(string)$review[$key]);
            provider_password_reset_template_hidden('content_base64',base64_encode($review['content']));
            echo '<label class="check"><input type="checkbox" name="reviewed" value="1" required> I reviewed the full replacement and approve saving it to this 20i account.</label>';provider_password_reset_template_password();echo '<button class="btn gold">Save reviewed template to 20i</button><a class="btn outline" href="'.esc(admin_url('provider-settings',['section'=>'advanced'])).'#password-reset-template">Return to editing</a></form></article>';
        }elseif(is_array($screen['review']??null)){
            $edit=$screen['review'];$content=$retainedDraft??(string)($screen['latest']['content']??'');
            if(($_POST['action']??'')==='provider_reset_template_review'&&is_string($_POST['content']??null)&&strlen($_POST['content'])<=65536)$content=$_POST['content'];
            echo '<form method="post" data-reset-template-edit>';admin_action('provider_reset_template_review');
            foreach(['request_id','profile_id','revision','observation_id'] as $key)provider_password_reset_template_hidden($key,(string)$edit[$key]);
            echo '<label>Replacement template<textarea name="content" rows="12" maxlength="65536" required spellcheck="false">'."\n".esc($content).'</textarea></label><p class="hint">Keep any placeholders required by your existing 20i template. The editor does not preview the final email.</p><button class="btn gold">Review replacement</button></form>';
        }else{
            echo '<p>Read the current template before editing. An unresolved request must be checked before another change can be sent.</p>';
            if($retainedDraft!==null)provider_password_reset_template_source('Your unsaved proposed template',$retainedDraft);
        }
        if(is_array($screen['latest']??null)&&is_string($screen['latest']['content']??null)){
            echo '<details><summary>Last saved template observation</summary>';provider_password_reset_template_source('Template reported by 20i',$screen['latest']['content']);echo '</details>';
        }elseif(is_array($screen['latest']??null)&&($screen['latest']['state']??'')==='unavailable'){
            echo '<p class="notice error">The latest check did not return a usable template. Check the saved 20i connection, then try another read.</p>';
        }
        echo '<h3>Change history</h3>';
        if(!$screen['items'])echo '<p>No template changes have been requested.</p>';
        foreach($screen['items'] as $item){
            echo '<article class="workspace-message" data-reset-template-history><h4>'.esc(provider_password_reset_template_status($item['status'])).'</h4>';
            if(is_int($item['requested_epoch']??null))echo '<p>'.esc(gmdate('Y-m-d H:i:s',$item['requested_epoch'])).' UTC</p>';
            if(!empty($item['can_recover'])){
                echo '<form method="post" data-reset-template-recover>';admin_action('provider_reset_template_recover');provider_password_reset_template_hidden('request_id',$item['id']);provider_password_reset_template_hidden('recovery_digest',$item['recovery_digest']);
                echo '<p>The saved attempt permits restoration of the missing history. This will not resend the change or confirm its result.</p><label class="check"><input type="checkbox" name="reviewed" value="1" required> I reviewed this request and want to restore its saved history.</label>';provider_password_reset_template_password();echo '<button class="btn outline">Restore saved request history</button></form>';
            }
            if(!empty($item['can_verify'])){echo '<form method="post" data-reset-template-verify>';admin_action('provider_reset_template_verify');provider_password_reset_template_hidden('request_id',$item['id']);provider_password_reset_template_password();echo '<button class="btn outline">Check current template</button><p class="hint">Reads the template once without resending the change.</p></form>';}
            if($item['status']==='observed')echo '<p>A later read reported the requested text. This does not confirm how the email renders or whether an email was delivered.</p>';
            else echo '<p>The saved history remains available. An uncertain request is never automatically sent again.</p>';
            echo '</article>';
        }
        echo '<nav class="button-row" aria-label="Template change history">';if($page>1)echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['section'=>'advanced','reset_template_page'=>$page-1])).'#password-reset-template">Previous requests</a>';if($screen['has_more'])echo '<a class="btn outline" href="'.esc(admin_url('provider-settings',['section'=>'advanced','reset_template_page'=>$page+1])).'#password-reset-template">More requests</a>';echo '</nav>';
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;echo '<p class="notice error">'.esc($e->getMessage()).'</p>';}
    echo '</details>';
}
