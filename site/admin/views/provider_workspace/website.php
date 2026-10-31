<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<div class="provider-hub-group-heading" id="twentyi-site-tools"><span class="eyebrow">05 / WEBSITE OPERATIONS</span><h2>Package configuration &amp; website tools</h2><p>Review service status, PHP versions, document roots, SSL, directory listing, WordPress installation and forwarding. The page distinguishes observations from separately authorized changes.</p></div>
<?php require CWP_CODE_ROOT.'/admin/views/service_observations.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_package_php.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_document_roots.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_package_force_ssl.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_directory_listing.php'; ?>
<?php provider_force_ssl_controls_admin_render(); ?>
<?php provider_directory_indexing_controls_admin_render(); ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_package_wordpress_installed.php'; ?>
<?php require CWP_CODE_ROOT.'/admin/views/provider_forwarding.php'; ?>
<?php provider_cloud_site_requests_admin_render(); ?>
