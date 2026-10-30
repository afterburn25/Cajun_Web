<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<div class="provider-hub-group-heading" id="twentyi-domains"><span class="eyebrow">04 / DOMAINS &amp; MAIL</span><h2>Domains, registration &amp; mailboxes</h2><p>Check domain availability and observed registrar details, then review documented renewal or mailbox operations. Searches and observations are not purchases; submitted changes may have separate approvals.</p></div>
<?php require CWP_CODE_ROOT.'/admin/views/provider_domain_search.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_registration_periods.php'; ?>

<?php require CWP_CODE_ROOT.'/admin/views/provider_mailboxes.php'; ?>

<?php require CWP_CODE_ROOT.'/admin/views/provider_registrar.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/domain_contracts.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/domain_expiry.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/domain_renewals.php'; ?>
