<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/password_reset_template_views.php';

/** Template source is exact text: the general posted() helper trims strings. */
function provider_password_reset_template_post_content(bool $sealed=false):string {
    $value=$_POST[$sealed?'content_base64':'content']??null;
    if(!is_string($value)||strlen($value)>($sealed?87384:65536))throw new InvalidArgumentException('Enter a template of up to 64 KiB.');
    if($sealed){$decoded=base64_decode($value,true);if($decoded===false||base64_encode($decoded)!==$value)throw new InvalidArgumentException('Review the template again before saving.');return $decoded;}
    return $value;
}
function provider_password_reset_template_post_revision():int {
    $value=posted('revision',10);
    if(!ctype_digit($value)||(int)$value<1||(int)$value>2147483647)throw new InvalidArgumentException('Reload the current 20i connection.');
    return (int)$value;
}
function provider_password_reset_template_admin_action(string $action):void {
    provider_settings_manager();$password=posted('current_password',200);
    if($action==='provider_reset_template_prepare'){
        provider_password_reset_template_prepare($password);flash('Password-reset template storage is ready.');
    }elseif($action==='provider_reset_template_refresh'){
        provider_password_reset_template_refresh(posted('profile_id',32),provider_password_reset_template_post_revision(),$password);
        flash('Template check recorded. Review the result below.');
    }elseif($action==='provider_reset_template_review'){
        $GLOBALS['providerResetTemplateReview']=provider_password_reset_template_review(posted('request_id',32),posted('profile_id',32),provider_password_reset_template_post_revision(),posted('observation_id',32),provider_password_reset_template_post_content());
        return;
    }elseif($action==='provider_reset_template_change'){
        provider_password_reset_template_change(posted('request_id',32),posted('profile_id',32),provider_password_reset_template_post_revision(),posted('observation_id',32),posted('review_sha256',64),provider_password_reset_template_post_content(true),posted('reviewed',1)==='1',$password);
        flash('Change request recorded. Check the current template separately to confirm what 20i reports.');
    }elseif($action==='provider_reset_template_verify'){
        provider_password_reset_template_verify(posted('request_id',32),$password);flash('Follow-up check recorded. Review the result below.');
    }elseif($action==='provider_reset_template_recover'){
        if(posted('reviewed',1)!=='1')throw new InvalidArgumentException('Review the saved request before restoring its history.');
        provider_password_reset_template_recover_finished(posted('request_id',32),posted('recovery_digest',64),$password);
        flash('Saved request history restored. Check the current template separately to confirm what 20i reports.');
    }else throw new InvalidArgumentException('Unknown password-reset template action.');
    redirect(admin_url('provider-settings',['section'=>'advanced']).'#password-reset-template');
}
