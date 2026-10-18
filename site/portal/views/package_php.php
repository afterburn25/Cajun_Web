<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<section class="workspace-card" data-client-php-version><?php provider_php_render($view);?></section>
