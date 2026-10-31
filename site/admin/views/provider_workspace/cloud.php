<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<div class="provider-hub-group-heading" id="twentyi-cloud"><span class="eyebrow">03 / CLOUD</span><h2>Cloud servers</h2><p>Review observed cloud servers and available client-assignment tools. This area is separate from shared-hosting plans and does not imply that cloud purchasing is enabled.</p></div>
<?php require CWP_CODE_ROOT.'/admin/views/provider_cloud.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_cloud_providers.php'; ?>
<section class="workspace-card provider-hub-limits"><h2>What the connection does not enable automatically</h2>
<p>A successful connection check confirms access to the hosting list. It does not confirm that every service is available or approved for use.</p>
<p>20i's Reseller API offers many operations, but having an API key does not mean every operation has been integrated, tested, approved or made available to your clients here. The controls below are feature-specific: some are read-only observations, some save local assignments, and others require explicit approval.</p>
<p>Do not assume account balance, automatic purchases, renewals, VPS actions, backup restore, SSH or StackCP sign-in are ready merely because the package-list check passes. Use the dedicated controls and their recorded results to confirm individual capabilities. See <a href="<?=esc(admin_url('provider-operations'))?>">20i operations</a> for separately reviewed provider-side changes.</p></section>
