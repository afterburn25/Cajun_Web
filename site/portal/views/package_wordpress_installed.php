<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<section class="workspace-card" data-client-wordpress-installed><?php provider_wp_installed_render($view);?></section>
