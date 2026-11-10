<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
if(!$client){
    $identityPreview=match($identityRoute){'verify'=>client_identity_token_preview('verify',$identityToken),'reset'=>client_identity_token_preview('recover',$identityToken),default=>null};
    $identityDestination=is_array($identityPreview['destination']??null)?$identityPreview['destination']:client_identity_destination($_GET);$identityOffer=client_identity_public_offer($identityDestination);
    login_head(match($identityRoute){'register'=>'Create your client account','verify'=>'Verify your email','resend'=>'Resend verification','forgot'=>'Recover your password','reset'=>'Choose a new password',default=>'Your client workspace'});
    if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if(($identityDestination['hosting']??null)==='checkout'){
        if($identityOffer){
            echo '<section class="workspace-card"><span class="eyebrow">SELECTED HOSTING OFFER</span><h3>'.esc($identityOffer['title']).'</h3><p>'.esc($identityOffer['summary']).'</p><p><strong>$'.esc(number_format((int)$identityOffer['amount_cents']/100,2)).' '.esc($identityOffer['currency']).' · '.esc($identityOffer['cadence']).'</strong></p><p class="hint">The exact offer and current terms will be rechecked before checkout continues.</p></section>';
        }else{
            echo '<div class="notice error" role="alert">The selected hosting offer is no longer available in its exact saved form. <a href="'.esc(studio_url(['hosting'=>'offers'])).'">Review current plans</a>; no substitute was selected.</div>';
        }
    }
    if(!studio_ready()){echo '<p>The studio is preparing this workspace. Contact us through the website.</p>';}
    elseif($invite!==''){?>
        <p>Set a unique password to activate or recover your account. Invitations expire after 24 hours.</p>
        <form method="post"><?=support_csrf_field()?><input type="hidden" name="action" value="activate"><input type="hidden" name="invite" value="<?=esc($invite)?>"><label>Password<input type="password" name="password" minlength="14" maxlength="72" required autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm_password" minlength="14" maxlength="72" required autocomplete="new-password"></label><button class="btn gold">Set password →</button></form>
    <?php }
    elseif($cinvite!==''){?>
        <p>Set a unique password to activate your company contact access. Invitations expire after 24 hours.</p>
        <form method="post"><?=support_csrf_field()?><input type="hidden" name="action" value="contact_activate"><input type="hidden" name="cinvite" value="<?=esc($cinvite)?>"><label>Password<input type="password" name="password" minlength="14" maxlength="72" required autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm_password" minlength="14" maxlength="72" required autocomplete="new-password"></label><button class="btn gold">Set password →</button></form>
    <?php }
    elseif($identityRoute!==''&&(!client_identity_ready()||(in_array($identityRoute,['register','resend','forgot'],true)&&!client_identity_request_ready()))){echo '<p>Public account access and email are not available yet. Contact the studio for an invitation.</p>';}
    elseif($identityRoute==='register'){?>
        <p>Create an account, verify your email, then choose your password and continue to your client workspace<?=(($identityDestination['hosting']??null)==='checkout')?' and selected hosting offer':''?>.</p>
        <form method="post"><?=support_csrf_field()?><input type="hidden" name="action" value="public_register"><label>Name<input type="text" name="name" required maxlength="120" autocomplete="name"></label><label>Email<input type="email" name="email" required maxlength="190" autocomplete="email"></label><button class="btn gold">Email verification link →</button></form>
        <p class="hint">Already have access? <a href="<?=esc(studio_url($identityDestination))?>">Sign in</a>.</p>
    <?php }
    elseif($identityRoute==='verify'){
        if(!$identityPreview){?>
            <p>This verification link expired, was replaced, or has already been used.</p><p><a class="btn outline" href="<?=esc(studio_url(['resend'=>1]+$identityDestination))?>">Request a new verification link</a></p>
        <?php }else{?>
            <p>Your link is valid. Choose a unique password, then verify your email and open your client workspace. Viewing this page alone does not use the link.</p><form method="post"><?=support_csrf_field()?><input type="hidden" name="action" value="public_verify"><label>Password<input type="password" name="password" minlength="14" maxlength="72" required autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm_password" minlength="14" maxlength="72" required autocomplete="new-password"></label><button class="btn gold">Set password, verify, and continue →</button></form>
        <?php }
    }
    elseif($identityRoute==='resend'){?>
        <p>Enter your email address. If it belongs to a pending public account, a new 24-hour verification link will be requested. An earlier valid link remains usable unless replacement delivery is accepted.</p><form method="post"><?=support_csrf_field()?><input type="hidden" name="action" value="public_resend"><label>Email<input type="email" name="email" required maxlength="190" autocomplete="email"></label><button class="btn gold">Send verification link →</button></form><p class="hint"><a href="<?=esc(studio_url($identityDestination))?>">Return to sign in</a>.</p>
    <?php }
    elseif($identityRoute==='forgot'){?>
        <p>Enter the primary client email. If the account is eligible, we will send a one-hour password reset link.</p><form method="post"><?=support_csrf_field()?><input type="hidden" name="action" value="public_recovery_request"><label>Email<input type="email" name="email" required maxlength="190" autocomplete="email"></label><button class="btn gold">Send reset link →</button></form><p class="hint"><a href="<?=esc(studio_url($identityDestination))?>">Return to sign in</a>.</p>
    <?php }
    elseif($identityRoute==='reset'){
        if(!$identityPreview){?>
            <p>This password reset link expired, was replaced, or has already been used.</p><p><a class="btn outline" href="<?=esc(studio_url(['forgot'=>1]+$identityDestination))?>">Request another reset link</a></p>
        <?php }else{?>
            <p>Choose a new unique password. Saving it revokes older client sessions and unused recovery links.</p><form method="post"><?=support_csrf_field()?><input type="hidden" name="action" value="public_recovery_reset"><label>New password<input type="password" name="password" minlength="14" maxlength="72" required autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm_password" minlength="14" maxlength="72" required autocomplete="new-password"></label><button class="btn gold">Reset password →</button></form>
        <?php }
    }
    else{?>
        <?php if(($identityDestination['hosting']??null)==='checkout'): ?><p>Sign in to review your selected hosting plan and its terms.</p><?php elseif($identityDestination): ?><p>Sign in to browse hosting plans.</p><?php else: ?><p>View your projects, messages and design reviews.</p><?php endif; ?>
        <form method="post"><?=support_csrf_field()?><input type="hidden" name="action" value="login"><label>Email<input type="email" name="email" required maxlength="190" autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="btn gold">Sign in →</button></form>
        <?php if(client_identity_request_ready()): ?><p class="hint"><a href="<?=esc(studio_url(['register'=>1]+$identityDestination))?>">Create an account</a> · <a href="<?=esc(studio_url(['forgot'=>1]+$identityDestination))?>">Forgot password?</a> · <a href="<?=esc(studio_url(['resend'=>1]+$identityDestination))?>">Resend verification</a></p><?php else: ?><p class="hint">Need access or a password reset? Ask the studio to issue a new private invitation link.</p><?php endif; ?>
        <p class="hint">Administrator? <a href="<?=esc(master_portal_url())?>">Open the client portal</a> using your administrator sign-in. Customer credentials are not needed for authorized client review.</p>
    <?php }
    login_foot();exit;
}
