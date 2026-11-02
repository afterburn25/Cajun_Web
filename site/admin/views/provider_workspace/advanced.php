<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<div class="provider-hub-group-heading" id="twentyi-access"><span class="eyebrow">06 / ACCESS &amp; ADVANCED</span><h2>StackCP access &amp; advanced management</h2><p>StackCP login and membership information require their own setup and verification; a saved General API key alone does not provide client login. Check the separate access records before enabling an owner-approved workflow.</p><p><a href="<?=esc(admin_url('provider-operations'))?>">Open reviewed 20i operations</a> · <a href="https://docs.20i.com/api/20i-api-documentation" target="_blank" rel="noopener noreferrer">Official API documentation</a></p></div>
<?php provider_stackcp_admin_render(); ?>
<?php provider_password_reset_template_admin_render(); ?>
<?php provider_stackcp_access_admin_render(); ?>
