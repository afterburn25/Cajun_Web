<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
?>
<section class="workspace-card" data-client-force-ssl><?php provider_force_ssl_render($view);?></section>
<?php if(function_exists('provider_force_ssl_requests_session_blocked')&&!provider_force_ssl_requests_session_blocked()): ?>
<a class="btn outline" href="<?=esc(studio_url(['https_requests'=>1]))?>">Request an HTTPS redirect change</a>
<?php endif; ?>
